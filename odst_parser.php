<?php
/* Halo Stats Processor
 * ODST Parser
 * 
 * Copyright © 2010 Andrew Hampe
 * 
 * This file is part of Halo Stats Processor.
 * 
 * Halo Stats Processor is free software: you can redistribute it
 * and/or modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either version
 * 2.1 of the License, or (at your option) any later version.
 * 
 * Halo Stats Processor is distributed in the hope that it will be
 * useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with Halo Stats Processor. If not, see
 * <http://www.gnu.org/licenses/>.
 */

// Requires core parser
require_once('parser.php');

// ODST SOAP Settings
define('ODST_SOAP_SERVICE', 'api/odst/ODSTService');
define('ODST_SOAP_GAME', 'GetGameDetail');
define('ODST_SOAP_METADATA', 'GetGameMetaData');
define('ODST_SOAP_CLIENT_URI', 'http://tempuri.org/');
define('ODST_SOAP_CLIENT_VERSION', 2);
define('ODST_SOAP_REQUEST_VERSION', SOAP_1_1);

// ODST Game class
class ODSTGame {
    function __construct() {
        // Initialize the XML data variable
        $this->init_xml();
    }
    
    // Difficulty constants
    const EASY = 0;
    const NORMAL = 1;
    const HEROIC = 2;
    const LEGENDARY = 3;
    
    function init_xml() {
        // (Re)create a blank DOMDocument to hold the XML data
        $this->xml_data = new DOMDocument;
    }
    
    function load_xml($xml) {
        // Import XML into the XML data property
        $this->xml_data->loadXML($xml);
    }
    
    function dump_xml() {
        // Export the XML data as a string
        return $this->xml_data->saveXML();
    }
    
    // Retrieve Game Stats method
    function get_game($game_id) {
        $url = 'http://' . BUNGIE_SERVER . '/' . ODST_SOAP_SERVICE . '.svc';
        $soap_url = 'http://' . BUNGIE_SERVER . '/' . ODST_SOAP_SERVICE . '/'. ODST_SOAP_GAME;
        // Create the SOAP client
        $client = new SoapClient(null, array('location' => $url,
                                             'uri' => ODST_SOAP_CLIENT_URI,
                                             'soap_version' => ODST_SOAP_CLIENT_VERSION,
                                             'trace' => true));
        $soap_request = "<s:Envelope xmlns:s=\"http://schemas.xmlsoap.org/soap/envelope/\"><s:Body><GetGameDetail xmlns=\"http://www.bungie.net/api/odst\"><gameId>${game_id}</gameId></GetGameDetail></s:Body></s:Envelope>";
        // Send the SOAP request and save the response
        $this->xml_data->loadXML($client->__doRequest($soap_request, $url, $soap_url, ODST_SOAP_REQUEST_VERSION));
    }
    
    // Parse/Load Game Stats method
    function load_game() {
        // Grab the GameDetail Result for simplexml
        if (is_null($this->xml_data->getElementsByTagName('GetGameDetailResult')->item(0))) {
            // Couldn't find response
            $this->error_details[0] = -1;
            $this->error_details[1] = "Internal Parser Error.";
            $this->error = true;
            return;
        }
        
        $xml = simplexml_import_dom($this->xml_data->getElementsByTagName('GetGameDetailResult')->item(0));
        
        // Error Handling
        $this->error_details[0] = (int) $xml->status;
        $this->error_details[1] = (string) $xml->reason;
        
        if ($this->error_details[0] != 7777) {
            $this->error = true;
            return;
        }
        
        // Get game data
        $game_data = $xml->children('a', true)->children('b', true);
        
        // General Information
        $this->difficulty = (int) $game_data->Difficulty;
        $this->duration = (int) $game_data->Duration;
        $this->map = (string) $game_data->MapName;
        $this->time_bonus = (float) $game_data->TimeBonus;
        
        // Date/Time
        $this->datetime = new DateTime($game_data->GameDate, new DateTimeZone('America/Los_Angeles'));
        
        // Scoring
        if ($game_data->IsScoreEnabled == 'true') {
            $this->scoring_enabled = true;
        }
        
        // Players
        foreach($game_data->GamePlayers->children('b', true) as $player) {
            $player_info = new ODSTPlayer;
            $player_info->id = (int) $player->DataIndex;
            $player_info->ghost = false; // Player was given to us
            $player_info->gamertag = rtrim((string) $player->Gamertag);
            $player_info->service_tag = (string) $player->ServiceTag;
            $player_info->armor_flags = unpack('C*', base64_decode((string) $player->ArmorFlags));
            $player_info->armor_type = (int) $player->ArmorType;
            $player_info->emblem_colors = unpack('C*', base64_decode((string) $player->EmblemColors));
            $player_info->emblem_flags = unpack('C*', base64_decode((string) $player->EmblemFlags));
            if ($this->scoring_enabled === true) {
                $player_info->score = 0;
            }
            $this->players[$player_info->id] = $player_info;
        }
        
        // Skulls
        if ($game_data->InitialPrimarySkulls != '') {
            $this->skulls_primary_start = explode(' ', $game_data->InitialPrimarySkulls);
        }
        if ($game_data->InitialSecondarySkulls != '') {
            $this->skulls_secondary_start = explode(' ', $game_data->InitialSecondarySkulls);
        }
        
        // Firefight
        if ($game_data->IsSurvival == 'true') {
            $this->firefight = true;
            // Calculate Set, Round, Wave reached and number of Bonus Rounds
            $this->total_waves = (int) $game_data->Waves;
            list($this->bonus_rounds, $this->set_reached, $this->round_reached,
                 $this->wave_reached) = ODSTGame::wave_position((int) $game_data->Waves);
            
            // Initialize Wave Stats
            $a = 1;
            foreach ($game_data->GameWaves->children('b', true) as $wave) {
                $this->wave_stats[$a] = new ODSTFirefightWave;
                $this->wave_stats[$a]->id = $a;
                if ($this->scoring_enabled) {
                    $this->wave_stats[$a]->score = 0;
                }
                $this->wave_stats[$a]->start = $wave->STR;
                if ($a > 1) {
                    $this->wave_stats[$a-1]->length = $wave->STR - $this->wave_stats[$a-1]->start;
                    $this->wave_stats[$a-1]->end = $this->wave_stats[$a-1]->start + $this->wave_stats[$a-1]->length;
                }
                ++$a;
            }
            $this->wave_stats[$a-1]->end = $this->duration;
            $this->wave_stats[$a-1]->length = $this->wave_stats[$a-1]->end - $this->wave_stats[$a-1]->start;
        } else {
            // Reset revert count
            $this->reverts = 0;
        }
        
        // Process game events
        //
        // Score is calculated per player, then the score from the players is
        // added up and returned as the total score. It has to be done this
        // way to correctly track the scores to make sure they don't dip into
        // the negatives.
        // 
        // Event variables key:
        // ET  - Event Type
        // PC  - Player that caused the event.
        //       -1 means it wasn't caused by a player
        // PE  - Player affected by the event.
        // S   - How much to change the score (up or down depending on the event type).
        // ST  - String information on the event.
        // ST2 - Second string information on the event.
        // T   - Time the event occurred.
        // WEP - The weapon involved in the event (if any).
        //
        // PC and PE's different meanings only apply in cases where the
        // distinction between the causing player and the affected player exists
        // (eg Betrayals). Otherwise, PC should be used, and PE if PC is
        // negative.
        
        foreach ($game_data->GameEvents->children('b', true) as $event) {
            // Start of game event loop
            // Event currently being processed is at $event
            
            if ($this->firefight) {
                // Find the wave
                foreach ($this->wave_stats as $wave) {
                    if ($event->T < $wave->end) {
                        $current_wave = $wave;
                        break;
                    }
                }
                // Since the wave obviously had an event, mark it as such.
                $current_wave->activity = true;
            }
            
            // Handle Event depending on the type of event
            switch ($event->ET) {
                case 'REVERT':     // The campaign game has been reverted to an
                                   // earlier part of the current game.
                    $this->event_revert($event);
                    break;
                case 'DEATH':      // A player died
                    if ($this->firefight) {
                        $this->event_death($event, $wave = $current_wave);
                    } else {
                        $this->event_death($event);
                    }
                    break;
                case 'SUICIDE':    // A player committed suicide
                    if ($this->firefight) {
                        $this->event_suicide($event, $current_wave);
                    } else {
                        $this->event_suicide($event);
                    }
                    break;
                case 'BETRAYAL':   // A player betrayed an ally
                    if ($this->firefight) {
                        $this->event_betrayal($event, false, $current_wave);
                    } else {
                        $this->event_betrayal($event, false);
                    }
                    break;
                case 'AIBETRAYAL': // A player betrayed an AI ally
                    if ($this->firefight) {
                        $this->event_betrayal($event, true, $current_wave);
                    } else {
                        $this->event_betrayal($event, true);
                    }
                    break;
                case 'KILL':      // A player killed an enemy
                   if ($this->firefight) {
                        $this->event_kill($event, $current_wave);
                    } else {
                        $this->event_kill($event);
                    }
                    break;
                case "MEDAL":     // A medal was earned
                    if ($this->firefight) {
                        $this->event_medal($event, $current_wave);
                    } else {
                        $this->event_medal($event);
                    }
                    break;
            }
            
            if ($this->scoring_enabled) {
                // If the score pegs into the negative, set it to zero.
                foreach ($this->players as $player) {
                    if ($player->score < 0) {
                        $player->score = 0;
                    }
                }
            }
            // End of event loop
        }
        
        // Post event processing score calculations
        if ($this->scoring_enabled) {
            // Handle time bonus
            if ($this->time_bonus > 1.0) {
                foreach ($this->players as $player) {
                    $player->raw_score = (int) $player->score;
                    $player->score = $player->score * $this->time_bonus;
                }
            } else {
                foreach ($this->players as $player) {
                    $player->raw_score = (int) $player->score;
                }
            }
            
            // Convert scores to integers
            foreach ($this->players as $player) {
                $player->score = (int) $player->score;
            }
            foreach ($this->wave_stats as $wave) {
                $wave->score = (int) $wave->score;
                $wave->raw_score = $wave->score;
            }
            
            // Calculate the main score
            $this->score = 0;
            $this->raw_score = 0;
            foreach ($this->players as $player) {
                $this->score += $player->score;
                $this->raw_score += $player->raw_score;
            }
        }
        
        // Calculate medal counts
        foreach($this->medals as $medal) {
            $this->medal_count += $medal;
        }
        foreach($this->players as $player) {
            foreach($player->medals as $medal) {
                $player->medal_count += $medal;
            }
        }
        
        // Calculate player count
        $this->player_count = count($this->players);
    }
    
    // Event Handlers
    // Event - Find single player
    private function event_player_auto($event) {
        // Event players
        $player_1 = (int) $event->PC;
        $player_2 = (int) $event->PE;
        
        // Find the right player
        if ($player_1 > -1 and $player_1 < 4) {
            // Player 1 is the player
            if (!array_key_exists($player_1, $this->players)) {
                // Create missing (ghost) player
                $player = new ODSTPlayer;
                $player->id = $player_1;
                $player->ghost = true;
                $this->players[$player_1] = $player;
            }
            return $player_1;
        } elseif ($player_2 > -1 and $player_2 < 4) {
            // Player 2 is the player
            if (!array_key_exists($player_2, $this->players)) {
                // Create missing (ghost) player
                $player = new ODSTPlayer;
                $player->id = $player_2;
                $player->ghost = true;
                $this->players[$player_2] = $player;
            }
            return $player_2;
        } else {
            // Event is not tied to a valid player
            return null;
        }
    }
    
    // Event - Find both players
    private function event_player_both($event) {
        $players = array(0 => null, 1 => null);
        
        // Event players
        $player_1 = (int) $event->PC;
        $player_2 = (int) $event->PE;
        
        // Check player 1
        if ($player_1 > -1 and $player_1 < 4) {
            // Player 1 is valid
            if (!array_key_exists($player_1, $this->players)) {
                // Create missing (ghost) player
                $player = new ODSTPlayer;
                $player->id = $player_1;
                $player->ghost = true;
                $this->players[$player_1] = $player;
            }
            $players[0] = $player_1;
        }
        
        // Check player 2
        if ($player_2 > -1 and $player_2 < 4) {
            // Player 2 is valid
            if (!array_key_exists($player_2, $this->players)) {
                // Create missing (ghost) player
                $player = new ODSTPlayer;
                $player->id = $player_2;
                $player->ghost = true;
                $this->players[$player_2] = $player;
            }
            $players[1] = $player_2;
        }
        
        return $players;
    }
    
    // Event - Dropped due to invalid player
    private function event_drop_player($event, $player = 1) {
        if ($player == 1) {
            trigger_error('Event type ' . $event->ET . ' at ' . $event->T .
                          ' dropped (Invalid player)', E_USER_NOTICE);
        } elseif ($player == 2) {
            trigger_error('Event type ' . $event->ET . ' at ' . $event->T .
                          ' dropped for player 2 (Invalid player)', E_USER_NOTICE);
        }
    }
    
    // Event - Add weapon to stats
    private function event_weapon_add($target, $event, $player, $wave = null) {
        // Check for new weapon (global, per-user, per-wave)
        if (!array_key_exists((string) $event->WEP, $this->$target)) {
            $this->$target[(string) $event->WEP] = 0;
        }
        if (!array_key_exists((string) $event->WEP, $this->players[$player]->$target)) {
            $this->players[$player]->$target[(string) $event->WEP] = 0;
        }
        if ($this->firefight and !array_key_exists((string) $event->WEP, $wave->$target)) {
            $wave->$target[(string) $event->WEP] = 0;
        }
        
        // By weapon stats (global, per-user, per-wave)
        $this->$target[(string) $event->WEP]++;
        $this->players[$player]->$target[(string) $event->WEP]++;
        if ($this->firefight) {
            $wave->$target[(string) $event->WEP]++;
        }
    }
    
    // Event - Revert
    private function event_revert($event) {
        if ($this->scoring_enabled) {
            // Subtract from score of each player
            foreach ($this->players as $player) {
                $player->score -= $event->S;
            }
        }
        // Add to revert counts
        $this->reverts++;
        // Over time stat
        $this->reverts_over_time[] = array((int) $event->T, $this->reverts);
    }
    
    // Event - Death
    private function event_death($event, $wave = null) {
        $player = $this->event_player_auto($event);
        
        // Check for invalid player
        if (is_null($player)) {
            $this->event_drop_player($event);
            return;
        }
        
        if ($this->scoring_enabled) {
            // Subtract from player score
            $this->players[$player]->score -= $event->S;
        }
        
        // Add to death count (global, per-user, per-wave)
        $this->deaths++;
        $this->players[$player]->deaths++;
        if ($this->firefight) {
            $wave->deaths++;
            // Subtract from wave score
            $wave->score -= $event->S;
        }
        
        // Over time stats (global, per-user)
        $this->deaths_over_time[] = array((int) $event->T, $this->deaths);
        $this->players[$player]->deaths_over_time[] =
             array((int) $event->T, $this->players[$player]->deaths);
        
        // By weapon stats
        $this->event_weapon_add("deaths_by_weapon", $event, $player, $wave);
    }
    
    // Event - Suicide
    private function event_suicide($event, $wave = null) {
        $player = $this->event_player_auto($event);
        
        // Check for invalid player
        if (is_null($player)) {
            $this->event_drop_player($event);
            return;
        }
        
        if ($this->scoring_enabled) {
            // Subtract from player score
            $this->players[$player]->score -= $event->S;
        }
        
        // Add to suicide count (global, per-user, per-wave)
        $this->suicides++;
        $this->players[$player]->suicides++;
        if ($this->firefight) {
            $wave->suicides++;
            // Subtract from wave score
            $wave->score -= $event->S;
        }
        
        // Over time stats (global, per-user)
        $this->suicides_over_time[] = array((int) $event->T, $this->suicides);
        $this->players[$player]->suicides_over_time[] =
             array((int) $event->T, $this->players[$player]->suicides);
        
        // By weapon stats
        $this->event_weapon_add('suicides_by_weapon', $event, $player, $wave);
    }
    
    // Event - Betrayal
    private function event_betrayal($event, $ai_betrayal, $wave = null) {
        if (!$ai_betrayal) {
            list($player_1, $player_2) = $this->event_player_both($event);
        } else {
            $player_1 = $this->event_player_auto($event);
        }
        
        // Player 2 stats
        if (!$ai_betrayal) {
            // Check for invalid player 2
            if (is_null($player_2)) {
                $this->event_drop_player($event, 2);
            } else {
                // Add to death count (global, per-user, per-wave)
                $this->deaths++;
                $this->players[$player_2]->deaths++;
                if ($this->firefight) {
                    $wave->deaths++;
                }
                
                // Over time stats (global, per-user)
                $this->deaths_over_time[] = array((int) $event->T, $this->deaths);
                $this->players[$player_2]->deaths_over_time[] =
                     array((int) $event->T, $this->players[$player_2]->deaths);
                
                // By weapon stats
                $this->event_weapon_add('deaths_by_weapon', $event, $player_2, $wave);
            }
        }
        
        // Check for invalid player 1
        if (is_null($player_1)) {
            $this->event_drop_player($event);
            return;
        }

        if ($this->scoring_enabled) {
            // Subtract from player score
            $this->players[$player_1]->score -= $event->S;
        }
        // Add to betrayal count (global, per-user, per-wave)
        $this->betrayals++;
        $this->players[$player_1]->betrayals++;
        if ($this->firefight) {
            $wave->betrayals++;
            // Subtract from wave score
            $wave->score -= $event->S;
        }
        
        // Over time stats (global, per-user)
        $this->betrayals_over_time[] = array((int) $event->T, $this->betrayals);
        $this->players[$player_1]->betrayals_over_time[] =
             array((int) $event->T, $this->players[$player_1]->betrayals);
        
        // By weapon stats
        $this->event_weapon_add('betrayals_by_weapon', $event, $player_1, $wave);
    }
    
    // Event - Kill
    private function event_kill($event, $wave = null) {
        $player = $this->event_player_auto($event);
        
        // Check for invalid player
        if (is_null($player)) {
            $this->event_drop_player($event);
            return;
        }
        
        if ($this->scoring_enabled) {
            // Add to player's score
            $this->players[$player]->score += $event->S;
        }
        // Add to kill count (global, per-user, per-wave)
        $this->kills++;
        $this->players[$player]->kills++;
        if ($this->firefight) {
            $wave->kills++;
            // Add to wave score
            $wave->score += $event->S;
        }
        // Check for new weapon (global, per-user, per-wave)
        if (!in_array($event->WEP, $this->weapons_used)) {
            $this->weapons_used[] = (string) $event->WEP;
        }
        if (!in_array($event->WEP, $this->players[$player]->weapons_used)) {
            $this->players[$player]->weapons_used[] = (string) $event->WEP;
        }
        if ($this->firefight and !in_array($event->WEP, $wave->weapons_used)) {
            $wave->weapons_used[] = (string) $event->WEP;
        }
        
        // Over time stats (global, per-user)
        $this->kills_over_time[] = array((int) $event->T, $this->kills);
        $this->players[$player]->kills_over_time[] =
             array((int) $event->T, $this->players[$player]->kills);
        
        // By weapon stats
        $this->event_weapon_add('kills_by_weapon', $event, $player, $wave);
    }
    
    // Event - Medal
    private function event_medal($event, $wave = null) {
        $player = $this->event_player_auto($event);
        
        // Check for invalid player
        if (is_null($player)) {
            $this->event_drop_player($event);
            return;
        }
        
        if ($this->scoring_enabled) {
            // Add to player's score
            $this->players[$player]->score += $event->S;
        }
        if ($this->firefight) {
            // Add to wave score
            $wave->score += $event->S;
        }
        // Check for new medal (global, per-user, per-wave)
        if (!array_key_exists((string) $event->ST, $this->medals)) {
            $this->medals[(string) $event->ST] = 0;
        }
        if (!array_key_exists((string) $event->ST, $this->players[$player]->medals)) {
            $this->players[$player]->medals[(string) $event->ST] = 0;
        }
        if ($this->firefight and !array_key_exists((string) $event->ST, $wave->medals)) {
            $wave->medals[(string) $event->ST] = 0;
        }
        // Add to medal count (global, per-user, per-wave)
        $this->medals[(string) $event->ST]++;
        $this->players[$player]->medals[(string) $event->ST]++;
        if ($this->firefight) {
            $wave->medals[(string) $event->ST]++;
        }
        
        // Over time stats (global, per-user)
        $this->medals_over_time[] = array((int) $event->T, (string) $event->ST,
                                          $this->medals[(string) $event->ST]);
        $this->players[$player]->medals_over_time[] =
                    array((int) $event->T, (string) $event->ST,
                          $this->players[$player]->medals[(string) $event->ST]);
    }
    
    // Apply a post-game multiplier and/or change the time bonus
    function calc_multiplier($multi = null, $time = null) {
        if ($this->scoring_enabled) {
            if (!is_null($multi)) {
                $this->post_multiplier = $multi;
            }
            if (!is_null($time)) {
                $this->time_bonus = $time;
            }
            
            // Reset scores
            foreach ($this->players as $player) {
                $player->score = $player->raw_score;
            }
            foreach ($this->wave_stats as $wave) {
                $wave->score = $player->raw_score;
            }
            
            // Calculate time bonus
            if ($this->time_bonus > 1.0) {
                foreach ($this->players as $player) {
                    $player->score = $player->score * $this->time_bonus;
                }
            }
            
            // Calculate post-game multiplier scores
            foreach ($this->players as $player) {
                $player->score = (int) ((int) $player->score * $this->post_multiplier);
            }
            foreach ($this->wave_stats as $wave) {
                $wave->score = (int) ($wave->score * $this->post_multiplier);
            }
            
            // Calculate the main score
            $this->score = 0;
            foreach ($this->players as $player) {
                $this->score += $player->score;
            }
        }
    }
    
    // Firefight Wave Position Calculator
    static function wave_position($waves) {
        $bonus_rounds = (int) floor($waves / 16);
        $waves -= $bonus_rounds + 1;
        $set_reached = (int) floor($waves / 15) + 1;
        $round_reached = (int) floor($waves % 15 / 5) + 1;
        $wave_reached = $waves % 15 % 5 + 1;
        
        return array($bonus_rounds, $set_reached, $round_reached, $wave_reached);
    }
    
    // SOAP/XML data
    public $xml_data;
    
    // Errors
    public $error = false;
    public $error_details = array(0, '');
    
    // General info
    public $difficulty;
    public $duration;
    public $datetime;
    public $map;
    public $scoring_enabled = false;
    public $score = -1;
    public $raw_score = -1;
    public $time_bonus = 1.0;
    public $post_multiplier = 1.0;
    public $firefight = false;
    
    // Kills, Deaths, Medals, etc.
    public $kills = 0;
    public $deaths = 0;
    public $suicides = 0;
    public $betrayals = 0;
    public $reverts = -1;
    public $medal_count = 0;
    public $medals = array();
    // Over Time
    public $kills_over_time = array();
    public $deaths_over_time = array();
    public $suicides_over_time = array();
    public $betrayals_over_time = array();
    public $reverts_over_time = array();
    public $medals_over_time = array();
    // By weapon
    public $kills_by_weapon = array();
    public $deaths_by_weapon = array();
    public $suicides_by_weapon = array();
    public $betrayals_by_weapon = array();
    
    // Players
    public $player_count;
    public $players = array();    
    
    // Skulls
    public $skulls_primary_start = array();
    public $skulls_secondary_start = array();
    
    // Sets, Rounds, Waves, and Bonus Rounds
    public $total_waves = -1;
    public $bonus_rounds = -1;
    public $set_reached = -1;
    public $round_reached = -1;
    public $wave_reached = -1;
    
    // Wave stats
    public $wave_stats = array();
    
    // Weapons
    public $weapons_used = array();
}

// ODST Player class
class ODSTPlayer {
    function emblem_url($size) {
        // Generate URL for the player's emblem
        list(, $a_pri, $a_sec, $e_pri, $e_sec) = $this->emblem_colors;
        list(, $e_design, $b_design, $e_toggle) = $this->emblem_flags;
        
        // Reverse emblem toggle for emblem generator
        $e_toggle = $e_toggle? 0 : 1;

        $url = 'http://' . BUNGIE_SERVER . '/' . EMBLEM_PATH .
        "?s=$size&0=$a_pri&1=$a_sec&2=$e_pri&3=$e_sec&fi=$e_design&bi=$b_design&fl=$e_toggle&m="
         . EMBLEM_GAME_ODST;
        return $url;
    }
    
    // Basic player details
    public $id;
    public $ghost; // If the player was given in the data or was a "ghost" we detected
    public $gamertag;
    public $service_tag;
    
    // Armor/Emblem data
    public $armor_flags;
    public $armor_type;
    public $emblem_colors;
    public $emblem_flags;
    
    // Kills, Deaths, Medals, etc.
    public $score = -1;
    public $raw_score = -1;
    public $kills = 0;
    public $deaths = 0;
    public $suicides = 0;
    public $betrayals = 0;
    public $medal_count = 0;
    public $medals = array();
    // Over Time
    public $kills_over_time = array();
    public $deaths_over_time = array();
    public $suicides_over_time = array();
    public $betrayals_over_time = array();
    public $medals_over_time = array();
    // By weapon
    public $kills_by_weapon = array();
    public $deaths_by_weapon = array();
    public $suicides_by_weapon = array();
    public $betrayals_by_weapon = array();
    
    // Weapons
    public $weapons_used = array();
}

// ODST Firefight wave stats class
class ODSTFirefightWave {
    // Basic wave info
    public $id;
    public $activity = false;
    public $start;
    public $end;
    public $length;
    
    // Kills, Deaths, Medals, etc.
    public $score = -1;
    public $raw_score = -1;
    public $kills = 0;
    public $deaths = 0;
    public $suicides = 0;
    public $betrayals = 0;
    public $medals = array();
    // By weapon
    public $kills_by_weapon = array();
    public $deaths_by_weapon = array();
    public $suicides_by_weapon = array();
    public $betrayals_by_weapon = array();
    
    // Weapons
    public $weapons_used = array();
}

// ODST Metadata class
class ODSTMetadata {
    function __construct() {
        // Initialize the XML data variable
        $this->init_xml();
    }
    
    // Constants for image size/type
    const IMAGE_SMALL = 'sm';
    const IMAGE_MEDIUM = 'med';
    const IMAGE_LARGE = 'large';
    const IMAGE_PNG = 'png';
    const IMAGE_GIF = 'gif';
    
    function init_xml() {
        // (Re)create a blank DOMDocument to hold the XML data
        $this->xml_data = new DOMDocument;
    }
    
    function load_xml($xml) {
        // Import XML into the XML data property
        $this->xml_data->loadXML($xml);
    }
    
    function dump_xml() {
        // Export the XML data as a string
        return $this->xml_data->saveXML();
    }
    
    function image_url($object, $size = null, $type = null) {
        // Check if the object supports image URL generation
        if (!is_subclass_of($object, 'ODSTImageGen')) {
            return;
        }
        // Default for the size argument
        if (is_null($size)) {
            if (is_null($this->image_default_size)) {
               return;
            }
            $size = $this->image_default_size;
        }
        // Default for the type argument
        if (is_null($type)) {
            if (is_null($this->image_default_type)) {
               return;
            }
            $type = $this->image_default_type;
        }
        
        // Generate the URL using the template
        $path = $object->image_path;
        $path = str_replace('{0}', $type, $path);
        $path = str_replace('{1}', $size, $path);
        $path = str_replace('{2}', $object->image_name, $path);
        $path = str_replace('{3}', $type, $path);
        
        return 'http://' . BUNGIE_SERVER . $path;
    }
    
    function get_metadata() {
        $url = 'http://' . BUNGIE_SERVER . '/' . ODST_SOAP_SERVICE . '.svc';
        $soap_url = 'http://' . BUNGIE_SERVER . '/' . ODST_SOAP_SERVICE . '/' . ODST_SOAP_METADATA;
        // Create the SOAP client
        $client = new SoapClient(null, array('location' => $url,
                                             'uri' => ODST_SOAP_CLIENT_URI,
                                             'soap_version' => ODST_SOAP_CLIENT_VERSION,
                                             'trace' => true));
        $soap_request = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><GetGameMetaData xmlns="http://www.bungie.net/api/odst" /></s:Body></s:Envelope>';
        // Send the SOAP request and save the response
        $this->xml_data->loadXML($client->__doRequest($soap_request, $url, $soap_url, ODST_SOAP_REQUEST_VERSION));
    }
    
    function load_metadata() {
        // Grab the GetGameMetaDataResult Result for simplexml
        if (is_null($this->xml_data->getElementsByTagName('GetGameMetaDataResult')->item(0))) {
            // Couldn't find response
            $this->error_details[0] = -1;
            $this->error_details[1] = "Internal Parser Error.";
            $this->error = true;
            return;
        }
        $xml = simplexml_import_dom($this->xml_data->getElementsByTagName('GetGameMetaDataResult')->item(0));
        
        // Error Handling
        $this->error_details[0] = (int) $xml->status;
        $this->error_details[1] = (string) $xml->reason;
        
        if ($this->error_details[0] != 7777) {
            $this->error = true;
            return;
        }
        
        // Get metadata
        $metadata = $xml->children('a', true)->children('b', true);
        
        // Process Characters
        foreach($metadata->CharacterInfo->children('c', true) as $character_class) {
            foreach($character_class->Value->KeyValueOfstringChar93kMfpyL as $character) {
                // Create character instance
                $odst_character = new ODSTCharacter;
                $character_attr = $character->Value->children('d', true);
                
                // Fill instance with data
                $odst_character->id = (string) $character_attr->Id;
                list($odst_character->name, $odst_character->class) = explode('_', $odst_character->id);
                $odst_character->display_name = (string) $character_attr->Disp;
                $odst_character->image_name = (string) $character_attr->ImgName;
                $odst_character->image_path = (string) $character_attr->ImgPath;
                $odst_character->description = (string) $character_attr->Desc;
                $odst_character->points = (int) $character_attr->Pnts;
                if ($character_attr->Vehic == 'true') {
                    $odst_character->vehicle = true;
                } else {
                    $odst_character->vehicle = false;
                }
                
                // Add to other characters
                $this->characters[$odst_character->id] = $odst_character;
            }
        }
        
        // Process Medals
        foreach($metadata->MedalInfo->children('c', true) as $medal) {
            // Create medal instance
            $odst_medal = new ODSTMedal;
            $medal_attr = $medal->Value->children('d', true);
            
            // Fill instance with data
            $odst_medal->id = (string) $medal_attr->Type;
            $odst_medal->display_name = (string) $medal_attr->Disp;
            $odst_medal->image_name = (string) $medal_attr->ImgName;
            $odst_medal->image_path = (string) $medal_attr->ImgPath;
            $odst_medal->group = (string) $medal_attr->RowDisplay;
            $odst_medal->description = (string) $medal_attr->Desc;
            $odst_medal->points = (int) $medal_attr->Points;
            $odst_medal->display_row = (int) $medal_attr->Row;
            $odst_medal->tier = (int) $medal_attr->Tier;
            
            // Add to other medals
            $this->medals[$odst_medal->id] = $odst_medal;
        }
        
        // Process Skulls
        foreach($metadata->SkullInfo->children('c', true) as $skull) {
            // Create skull instance
            $odst_skull = new ODSTSkull;
            $skull_attr = $skull->Value->children('d', true);
            
            // Fill instance with data
            $odst_skull->id = (string) $skull_attr->ID;
            $odst_skull->display_name = (string) $skull_attr->Display;
            $odst_skull->image_enabled = (string) $skull_attr->Image;
            $odst_skull->image_disabled = (string) $skull_attr->ImageOff;
            $odst_skull->description = (string) $skull_attr->Desc;
            $odst_skull->score_multiplier = (float) $skull_attr->Multiplier;
            $odst_skull->order = (int) $skull_attr->Sort;
            
            // Add to other skulls
            $this->skulls[$odst_skull->id] = $odst_skull;
        }
        
        // Process Weapons
        foreach ($metadata->WeaponInfo->children('c', true) as $weapon) {
            // Create weapon instance
            $odst_weapon = new ODSTWeapon;
            $weapon_attr = $weapon->Value->children('d', true);
            
            // Fill instance with data
            $odst_weapon->id = (string) $weapon_attr->Type;
            $odst_weapon->display_name = (string) $weapon_attr->Disp;
            $odst_weapon->image_name = (string) $weapon_attr->ImgName;
            $odst_weapon->image_path = (string) $weapon_attr->ImgPath;
            $odst_weapon->description = (string) $weapon_attr->Desc;
            
            // Add to other weapons
            $this->weapons[$odst_weapon->id] = $odst_weapon;
        }
        
        // Get default image size and type
        $this->image_default_size = (string) $metadata->ImageSizeEnum;
        $this->image_default_type = (string) $metadata->ImageTypeEnum;
    }
    
    // SOAP/XML data
    public $xml_data;
    
    // Errors
    public $error = false;
    public $error_details = array(0, '');
    
    // General Groups
    public $characters = array(); // Characters
    public $medals = array(); // Medals
    public $skulls = array(); // Skulls
    public $weapons = array(); // Weapons
    
    // Image defaults
    public $image_default_size;
    public $image_default_type;
}

// ODST Metadata dummy parent class
// Extending this class with enable use of ODSTMetadata::image_gen()
class ODSTImageGen {
}

// ODST Metadata Character
class ODSTCharacter extends ODSTImageGen {
    // Image URL Generation supported
    
    public $id; // Internal name
    public $name; // Character name
    public $class; // Character class
    public $display_name; // Human-friendly name
    public $image_name;
    public $image_path;
    public $description;
    public $points = 0;
    public $vehicle = false;
}

// ODST Metadata Medal
class ODSTMedal extends ODSTImageGen {
    // Image URL Generation supported
    
    public $id; // Internal name
    public $display_name; // Human-friendly name
    public $image_name;
    public $image_path;
    public $group;
    public $description;
    public $points = 0;
    public $display_row;
    public $tier;
}

// ODST Metadata Skull
class ODSTSkull {    
    function image_url($enabled) {
        if ($enabled) {
            return 'http://' . BUNGIE_SERVER . $this->image_enabled;
        } else {
            return 'http://' . BUNGIE_SERVER . $this->image_disabled;
        }
    }
    public $id; // Internal name
    public $display_name; // Human-friendly name
    public $image_enabled;
    public $image_disabled;
    public $description;
    public $score_multiplier;
    public $order;
}

// ODST Metadata Weapon
class ODSTWeapon extends ODSTImageGen {
    // Image URL Generation supported
    
    public $id; // Internal name
    public $display_name; // Human-friendly name
    public $image_name;
    public $image_path;
    public $description;
}
