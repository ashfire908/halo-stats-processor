<?php
// Game Parser

// Bungie.net Settings
define('BUNGIE_SERVER', 'www.bungie.net');
define('ODST_SERVICE', 'api/odst/ODSTService');
define('ODST_GAME', 'GetGameDetail');
define('ODST_METADATA', 'GetGameMetaData');
define('ODST_SOAP_CLIENT_URI', 'http://tempuri.org/');
define('SOAP_CLIENT_VERSION', 2);
define('SOAP_REQUEST_VERSION', SOAP_1_1);
// Parser Settings
define('DATE_FORMAT', 'Y-m-d\TH:i:s'); // For date parsing
date_default_timezone_set('America/Los_Angeles');

// ODST Game class
class ODSTGame {
    function __construct() {
        // Create SOAP/XML DOMDocument
        $this->xml_data = new DOMDocument;
    }
    
    // Diffculty constants
    const EASY = 0;
    const NORMAL = 1;
    const HEROIC = 2;
    const LEGENDARY = 3;
    
    // Retrieve Game Stats method
    function get_game($game_id) {
        $url = 'http://' . BUNGIE_SERVER . '/' . ODST_SERVICE . '.svc';
        $soap_url = 'http://' . BUNGIE_SERVER . '/' . ODST_SERVICE . '/'. ODST_GAME;
        // Get/make the client
        $client = new SoapClient(null, array('location' => $url,
                                             'uri' => ODST_SOAP_CLIENT_URI,
                                             'soap_version' => SOAP_CLIENT_VERSION,
                                             'trace' => true));
        $soap_request = "<s:Envelope xmlns:s=\"http://schemas.xmlsoap.org/soap/envelope/\"><s:Body><GetGameDetail xmlns=\"http://www.bungie.net/api/odst\"><gameId>${game_id}</gameId></GetGameDetail></s:Body></s:Envelope>";
        $this->xml_data->loadXML($client->__doRequest($soap_request, $url, $soap_url, SOAP_1_1));
    }
    
    // Parse/Load Game Stats method
    function load_game() {
        $game = new ODSTGame;    
        
        // Grab the GameDetail Result for simplexml
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
        
        // Game Events
        $game_events = $game_data->GameEvents;
        // Players
        $players = $game_data->GamePlayers;
        // Firefight waves
        $waves = $game_data->GameWaves;
        
        // General Information
        $this->difficulty = (int) $game_data->Difficulty;
        $this->duration = (int) $game_data->Duration;
        $this->map = (string) $game_data->MapName;
        $this->time_bonus = (float) $game_data->TimeBonus;
        
        // Break down the string and feed it into mktime.
        // Break down date into date and time
        $datetime = explode('T', $game_data->GameDate);
        list($dt_year, $dt_month, $dt_day) = explode('-', $datetime[0]);
        list($dt_hour, $dt_minute, $dt_second) = explode(':', $datetime[1]);
        
        // Set datetime
        $this->datetime = date_create('@' . (string) mktime($dt_hour, $dt_minute,
                                      $dt_second, $dt_month, $dt_day, $dt_year));
        
        if ($game_data->IsScoreEnabled == 'true') {
            $this->scoring_enabled = true;
        }
        if ($game_data->IsSurvival == 'true') {
            $this->firefight = true;
        }
        
        // Players
        $this->player_count = count($players->children('b', true));
        foreach($players->children('b', true) as $player) {
            $player_info = new ODSTPlayer;
            $player_info->id = (int) $player->DataIndex;
            $player_info->gamertag = rtrim((string) $player->Gamertag);
            $player_info->service_tag = (string) $player->ServiceTag;
            if ($this->scoring_enabled) {
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
        
        // Calculate Sets, Rounds, Waves, and Bonus Rounds
        if ($this->firefight == true) {
            $this->total_waves = (int) $game_data->Waves;
            list($this->bonus_rounds, $this->set_reached, $this->round_reached,
                 $this->wave_reached) = wave_position((int) $game_data->Waves);
        }
        
        // Initliaze Wave Stats
        if ($this->firefight == true) {
            $a = 1;
            foreach ($waves->children('b', true) as $wave) {
                $this->wave_stats[$a] = new ODSTFirefightWave;
                $this->wave_stats[$a]->id = $a;
                $this->wave_stats[$a]->start = $wave->STR;
                if ($a > 1) {
                    $this->wave_stats[$a-1]->length = $wave->STR - $this->wave_stats[$a-1]->start;
                    $this->wave_stats[$a-1]->end = $this->wave_stats[$a-1]->start + $this->wave_stats[$a-1]->length;
                }
                ++$a;
            }
            $this->wave_stats[$a-1]->end = $this->duration;
            $game->wave_stats[$a-1]->length = $this->wave_stats[$a-1]->end - $this->wave_stats[$a-1]->start;
        }
        
        // Process game events - score and weapons
        if ($this->scoring_enabled) {
            
            foreach ($this->wave_stats as $wave) {
                $wave->score = 0;
            }
            
            // Score is calculated per player, then the score from the players is
            // added up and returned as the total score. It has to be done this
            // way to correctly track the scores to make sure they don't dip into
            // the negatives.
    
            foreach ($game_events->children('b', true) as $event) {
                // Start of Game event loop
                // Event currently being processed at $event
    
                // If PC is not set, use PE.
                if ($event->PC > -1) {
                    $player_auto = (int) $event->PC;
                } else {
                    $player_auto = (int) $event->PE;
                }
                $player_1 = (int) $event->PC;
                $player_2 = (int) $event->PE;
                
                if ($this->firefight === true) {
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
                                       // eariler part of the current game.
                        // Subtract from score of each player
                        foreach ($this->players as $player) {
                            $player->score -= $event->S;
                        }
                        // Since reverts only happen in campaign, don't mess with
                        // the wave.
                        break;
                    case 'DEATH':      // A player died
                        // Subtract from player score
                        $this->players[$player_auto]->score -= $event->S;
                        // Add to player's death count
                        $this->players[$player_auto]->deaths++;
                        // Update wave stats
                        if ($this->firefight === true) {
                            $current_wave->score -= $event->S;
                            $current_wave->deaths++;
                        }
                        break;
                    case 'SUICIDE':    // A player commited suicide
                        // Subtract from player score
                        $this->players[$player_auto]->score -= $event->S;
                        // Add to player's suicide count
                        $this->players[$player_auto]->suicides++;
                        // Update wave stats
                        if ($this->firefight === true) {
                            $current_wave->score -= $event->S;
                            $current_wave->suicides++;
                        }
                        break;
                    case 'BETRAYAL':   // A player betrayed an ally
                        // Add to betrayed player's death count
                        $self->players[$player_2]->deaths++;
                        // Update wave stats
                        if ($this->firefight === true) {
                            $current_wave->deaths++;
                        }
                    case 'AIBETRAYAL': // A player betrayed an AI ally
                        // Subtract from player score
                        $this->players[$player_1]->score -= $event->S;
                        // Add to player's betrayal count
                        $this->players[$player_1]->betrayals++;
                        // Update wave stats
                        if ($this->firefight === true) {
                            $current_wave->score -= $event->S;
                            $current_wave->suicides++;
                        }
                        break;
                    case 'KILL':      // A player killed an enemy
                        // Check for new weapon
                        // Add to player's kills count
                        $this->players[$player_auto]->kills++;
                        // Add to player's score
                        $this->players[$player_auto]->score += $event->S;
                        // Update wave stats
                        if ($this->firefight === true) {
                            $current_wave->score += $event->S;
                            $current_wave->kills++;
                        }
                        // Check for new weapon (global, per-user, per-wave)
                           if (! in_array($event->WEP, $this->weapons_used)) {
                            $this->weapons_used[] = (string) $event->WEP;
                        }
                        if (! in_array($event->WEP, $this->players[$player_auto]->weapons_used)) {
                            $this->players[$player_auto]->weapons_used[] = (string) $event->WEP;
                        }
                        if ($this->firefight === true and ! in_array($event->WEP, $current_wave->weapons_used)) {
                            $current_wave->weapons_used[] = (string) $event->WEP;
                        }
                        break;
                    case "MEDAL":
                        // Add to player's score
                        $this->players[$player_auto]->score += $event->S;
                        // Update wave stats
                        if ($this->firefight === true) {
                            $current_wave->score += $event->S;
                        }
                        // Check for new medal (global, per-user, per-wave)
                           if (! array_key_exists((string) $event->ST, $this->medals)) {
                            $this->medals[(string) $event->ST] = 0;
                        }
                        if (! array_key_exists((string) $event->ST, $this->players[$player_auto]->medals)) {
                            $this->players[$player_auto]->medals[(string) $event->ST] = 0;
                        }
                        if ($this->firefight === true and ! array_key_exists((string) $event->ST, $current_wave->medals)) {
                            $current_wave->medals[(string) $event->ST] = 0;
                        }
                        // Add to medal count (global, per-user, per-wave)
                        $this->medals[(string) $event->ST]++;
                        $this->players[$player_auto]->medals[(string) $event->ST]++;
                        $current_wave->medals[(string) $event->ST]++;
                        break;
                    // No default because all the events are handled
                }
                
                // If the score pegs into the negative, set it to zero.
                foreach ($this->players as $player) {
                    if ($player->score < 0) {
                        $player->score = 0;
                    }
                }
                // End of event loop
            }
            
            // Calculate the main score
            $this->score = (float) 0;
            foreach ($this->players as $player) {
                $this->score += $player->score;
            }
            
            // Handle time bonus
            if ($this->time_bonus > 1.0) {
                $this->score = $this->score * $this->time_bonus;
                foreach ($this->players as $player) {
                    $player->score = $player->score * $this->time_bonus;
                }
            }
        }
    }
    
    // SOAP/XML data
    Public $xml_data;
    
    // Errors
    Public $error = false;
    Public $error_details = array(0, '');
    
    // General info
    Public $difficulty;
    Public $duration;
    Public $datetime;
    Public $map;
    Public $scoring_enabled = false;
    Public $score = -1;
    Public $time_bonus = 0.0;
    Public $firefight = false;
    
    // Players
    Public $player_count;
    Public $players = array();    
    
    // Skulls
    Public $skulls_primary_start = array();
    Public $skulls_secondary_start = array();
    
    // Sets, Rounds, Waves, and Bonus Rounds
    Public $total_waves = -1;
    Public $bonus_rounds = -1;
    Public $set_reached = -1;
    Public $round_reached = -1;
    Public $wave_reached = -1;
    
    // Wave stats
    Public $wave_stats = array();
    
    // Weapons
    Public $weapons_used = array();
    // Medals
    Public $medals = array();
}

// ODST Player class
class ODSTPlayer {
    Public $id;
    Public $gamertag;
    Public $service_tag;
    Public $score = -1;
    Public $kills = 0;
    Public $deaths = 0;
    Public $suicides = 0;
    Public $betrayals = 0;
    Public $weapons_used = array();
    Public $medals = array();
}

// ODST Firefight wave stats class
class ODSTFirefightWave {
    Public $id;
    Public $activity = false;
    Public $start;
    Public $end;
    Public $length;
    Public $score = -1;
    Public $kills = 0;
    Public $deaths = 0;
    Public $suicides = 0;
    Public $betrayals = 0;
    Public $weapons_used = array();
    Public $medals = array();
}

function get_metadata() {
    $url = 'http://' . BUNGIE_SERVER . '/' . ODST_SERVICE . '.svc';
    $soap_url = 'http://' . BUNGIE_SERVER . '/' . ODST_SERVICE . '/' . ODST_METADATA;
    // Get/make the client
    $client = new SoapClient(null, array('location' => $url,
                                         'uri' => ODST_SOAP_CLIENT_URI,
                                         'soap_version' => SOAP_CLIENT_VERSION,
                                         'trace' => true));
    $soap_request = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><GetGameMetaData xmlns="http://www.bungie.net/api/odst" /></s:Body></s:Envelope>';
    return $client->__doRequest($soap_request, $url, $soap_url, SOAP_1_1);
}

function get_gamexml($game_id) {
    $url = 'http://' . BUNGIE_SERVER . '/' . ODST_SERVICE . '.svc';
    $soap_url = 'http://' . BUNGIE_SERVER . '/' . ODST_SERVICE . '/'. ODST_GAME;
    // Get/make the client
    $client = new SoapClient(null, array('location' => $url,
                                         'uri' => ODST_SOAP_CLIENT_URI,
                                         'soap_version' => SOAP_CLIENT_VERSION,
                                         'trace' => true));
    $soap_request = "<s:Envelope xmlns:s=\"http://schemas.xmlsoap.org/soap/envelope/\"><s:Body><GetGameDetail xmlns=\"http://www.bungie.net/api/odst\"><gameId>${game_id}</gameId></GetGameDetail></s:Body></s:Envelope>";
    return $client->__doRequest($soap_request, $url, $soap_url, SOAP_1_1);
}

function wave_position($total_waves) {
    $bonus_rounds = (int) floor($total_waves / 16);
    $calc_waves = $total_waves - $bonus_rounds - 1;
    $set_reached = (int) floor($calc_waves / 15) + 1;
    $round_reached = (int) floor($calc_waves % 15 / 5) + 1;
    $wave_reached = $calc_waves % 15 % 5 + 1;
    
    return array($bonus_rounds, $set_reached, $round_reached, $wave_reached);
}