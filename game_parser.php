<?php
// ODST Game Parser

// Bungie.net Settings
define('BUNGIE_SERVER', 'www.bungie.net');
define('ODST_SERVICE', 'api/odst/ODSTService');
define('ODST_GAME', 'GetGameDetail');
define('ODST_METADATA', 'GetGameMetaData');
define('ODST_SOAP_CLIENT_URI', 'http://tempuri.org/');
define('SOAP_CLIENT_VERSION', 2);
define('SOAP_REQUEST_VERSION', SOAP_1_1);
// Parser Settings
define('DATE_FORMAT', 'Y-m-d\TH:i:s'); // For date_create_from_format()
date_default_timezone_set('America/Los_Angeles');

// ODST Game class
class ODSTGame {
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

function dump_data($game_id) {
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

function get_data($game_ids) {
    $url = 'http://' . BUNGIE_SERVER . '/' . ODST_SERVICE . '.svc';
    $soap_url = 'http://' . BUNGIE_SERVER . '/' . ODST_SERVICE . '/'. ODST_GAME;
    // Get/make the client
    $client = new SoapClient(null, array('location' => $url,
                                         'uri' => ODST_SOAP_CLIENT_URI,
                                         'soap_version' => SOAP_CLIENT_VERSION,
                                         'trace' => true));
    $game_data = array();
    foreach ($game_ids as $game_id) {
      $soap_request = "<s:Envelope xmlns:s=\"http://schemas.xmlsoap.org/soap/envelope/\"><s:Body><GetGameDetail xmlns=\"http://www.bungie.net/api/odst\"><gameId>${game_id}</gameId></GetGameDetail></s:Body></s:Envelope>";
      $game_data[$game_id] = $client->__doRequest($soap_request, $url, $soap_url, SOAP_1_1);
    }
    return $game_data;
}

function parse_data($game_xml) {
    $game = new ODSTGame;    
    
    // Load the XML into DOM and grab the GameDetail Result for simplexml
    $dom = new DOMDocument;
    $dom->loadXML($game_xml);
    $xml = simplexml_import_dom($dom->getElementsByTagName('GetGameDetailResult')->item(0));
    
    // Error Handling
    $game->error_details[0] = (int) $xml->status;
    $game->error_details[1] = (string) $xml->reason;
    
    if ($game->error_details[0] != 7777) {
        $game->error = true;
        return $game;
    }
    
    // Get game data
    $game_response = $xml->children('a', true);
    $game_data = $game_response->children('b', true);
    
    // Game Events
    $game_events = $game_data->GameEvents;
    // Players
    $players = $game_data->GamePlayers;
    // Firefight waves
    $waves = $game_data->GameWaves;
    
    // General Information
    $game->difficulty = (int) $game_data->Difficulty;
    $game->duration = (int) $game_data->Duration;
    $game->map = (string) $game_data->MapName;
    $game->time_bonus = (float) $game_data->TimeBonus;
    
    // Try strptime first, then date_create_from_format, fallback to manual
    // string parsing
    // After we find the target platform, eliminate the other two.
    //if (function_exists('strptime')) {
    //	$game->datetime = strptime($game_data->GameDate, DATE_FORMAT);
    //} elseif (function_exists('data_create_from_format')) {
    if (function_exists('data_create_from_format')) {
    	$game->datetime = date_create_from_format(DATE_FORMAT, $game_data->GameDate);
    } else {
    	// Break down the string and feed it into mktime.
        // Break down date into date and time
        $datetime = explode('T', $game_data->GameDate);
        $date = explode('-', $datetime[0]);
        $time = explode(':', $datetime[1]);
        
        // Set up time
        $dt_hour = (int) $time[0];
        $dt_minute = (int) $time[1];
        $dt_second = (int) $time[2];
        
        // Set up date
        $dt_year = (int) $date[0];
        $dt_month = (int) $date[1];
        $dt_day = (int) $date[2];
        
        // Set datetime
        $game->datetime = date_create('@' . (string) mktime($dt_hour, $dt_minute,
                                      $dt_second, $dt_month, $dt_day, $dt_year));
    }
    
    if ($game_data->IsScoreEnabled == 'true') {
        $game->scoring_enabled = true;
    }
    if ($game_data->IsSurvival == 'true') {
        $game->firefight = true;
    }
    
    // Players
    $game->player_count = count($players->children('b', true));
    foreach($players->children('b', true) as $player) {
        $player_info = new ODSTPlayer;
        $player_info->id = (int) $player->DataIndex;
        $player_info->gamertag = rtrim((string) $player->Gamertag);
        $player_info->service_tag = (string) $player->ServiceTag;
        if ($game->scoring_enabled) {
        	$player_info->score = 0;
        }
        $game->players[$player_info->id] = $player_info;
    }
    
    // Skulls
    if ($game_data->InitialPrimarySkulls != '') {
    	$game->skulls_primary_start = explode(' ', $game_data->InitialPrimarySkulls);
    }
    if ($game_data->InitialSecondarySkulls != '') {
        $game->skulls_secondary_start = explode(' ', $game_data->InitialSecondarySkulls);
    }
    
    // Calculate Sets, Rounds, Waves, and Bonus Rounds
    if ($game->firefight == true) {
        $game->total_waves = (int) $game_data->Waves;
        $wave_pos = wave_position((int) $game_data->Waves);
        $game->bonus_rounds = $wave_pos[0];
        $game->set_reached = $wave_pos[1];
        $game->round_reached = $wave_pos[2];
        $game->wave_reached = $wave_pos[3];
    }
    
    // Initliaze Wave Stats
    if ($game->firefight == true) {
    	$a = 1;
    	foreach ($waves->children('b', true) as $wave) {
    		$game->wave_stats[$a] = new ODSTFirefightWave;
    		$game->wave_stats[$a]->id = $a;
    		$game->wave_stats[$a]->start = $wave->STR;
    		if ($a > 1) {
    			$game->wave_stats[$a-1]->length = $wave->STR - $game->wave_stats[$a-1]->start;
    			$game->wave_stats[$a-1]->end = $game->wave_stats[$a-1]->start + $game->wave_stats[$a-1]->length;
    		}
    		++$a;
    	}
    	$game->wave_stats[$a-1]->end = $game->duration;
    	$game->wave_stats[$a-1]->length = $game->wave_stats[$a-1]->end - $game->wave_stats[$a-1]->start;
    }
    
    // Process game events - score and weapons
    if ($game->scoring_enabled) {
    	
    	foreach ($game->wave_stats as $wave) {
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
        	
        	
        	if ($game->firefight === true) {
        		// Find the wave
	        	foreach ($game->wave_stats as $wave) {
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
                    foreach ($game->players as $player) {
                    	$player->score -= $event->S;
                    }
                    // Since reverts only happen in campaign, don't mess with
                    // the wave.
                    break;
                case 'DEATH':      // A player died
                	// Subtract from player score
        			$game->players[$player_auto]->score -= $event->S;
        			// Add to player's death count
        		    $game->players[$player_auto]->deaths++;
        			// Update wave stats
        			if ($game->firefight === true) {
        				$current_wave->score -= $event->S;
        		    	$current_wave->deaths++;
        		    }
        			break;
        		case 'SUICIDE':    // A player commited suicide
        			// Subtract from player score
        			$game->players[$player_auto]->score -= $event->S;
        			// Add to player's suicide count
        		    $game->players[$player_auto]->suicides++;
        		    // Update wave stats
        			if ($game->firefight === true) {
        				$current_wave->score -= $event->S;
        		    	$current_wave->suicides++;
        			}
        			break;
        		case 'BETRAYAL':   // A player betrayed an ally
        			// Add to betrayed player's death count
        			$game->players[$player_2]->deaths++;
        			// Update wave stats
        			if ($game->firefight === true) {
        				$current_wave->deaths++;
        			}
                case 'AIBETRAYAL': // A player betrayed an AI ally
        			// Subtract from player score
                    $game->players[$player_1]->score -= $event->S;
                    // Add to player's betrayal count
                    $game->players[$player_1]->betrayals++;
                    // Update wave stats
        			if ($game->firefight === true) {
        				$current_wave->score -= $event->S;
                    	$current_wave->suicides++;
        			}
        			break;
        		case 'KILL':      // A player killed an enemy
        			// Check for new weapon
        			// Add to player's kills count
                    $game->players[$player_auto]->kills++;
                    // Add to player's score
                    $game->players[$player_auto]->score += $event->S;
                    // Update wave stats
                    if ($game->firefight === true) {
                    	$current_wave->score += $event->S;
                    	$current_wave->kills++;
                    }
                    // Check for new weapon (global, per-user, per-wave)
                   	if (! in_array($event->WEP, $game->weapons_used)) {
                        $game->weapons_used[] = (string) $event->WEP;
                    }
        	        if (! in_array($event->WEP, $game->players[$player_auto]->weapons_used)) {
                        $game->players[$player_auto]->weapons_used[] = (string) $event->WEP;
                    }
                    if ($game->firefight === true and ! in_array($event->WEP, $current_wave->weapons_used)) {
                    	$current_wave->weapons_used[] = (string) $event->WEP;
                    }
                    break;
        		case "MEDAL":
        		    // Add to player's score
        	        $game->players[$player_auto]->score += $event->S;
        	        // Update wave stats
                    if ($game->firefight === true) {
                    	$current_wave->score += $event->S;
                    }
                    // Check for new medal (global, per-user, per-wave)
                   	if (! array_key_exists((string) $event->ST, $game->medals)) {
                        $game->medals[(string) $event->ST] = 0;
                    }
        	        if (! array_key_exists((string) $event->ST, $game->players[$player_auto]->medals)) {
                        $game->players[$player_auto]->medals[(string) $event->ST] = 0;
                    }
                    if ($game->firefight === true and ! array_key_exists((string) $event->ST, $current_wave->medals)) {
                    	$current_wave->medals[(string) $event->ST] = 0;
                    }
                    // Add to medal count (global, per-user, per-wave)
                    $game->medals[(string) $event->ST]++;
                    $game->players[$player_auto]->medals[(string) $event->ST]++;
                    $current_wave->medals[(string) $event->ST]++;
        		    break;
                // No default because all the events are handled
        	}
        	
        	// If the score pegs into the negative, set it to zero.
            foreach ($game->players as $player) {
	            if ($player->score < 0) {
	        		$player->score = 0;
	        	}
        	}
            // End of event loop
        }
        
        // Calculate the main score
        $game->score = (float) 0;
        foreach ($game->players as $player) {
        	$game->score += $player->score;
        }
        
        // Handle time bonus
        if ($game->time_bonus > 1.0) {
        	$game->score = $game->score * $game->time_bonus;
        	foreach ($game->players as $player) {
        		$player->score = $player->score * $game->time_bonus;
        	}
        }
    }
    
    return $game;
}

function wave_position($total_waves) {
	$bonus_rounds = (int) floor($total_waves / 16);
    $calc_waves = $total_waves - $bonus_rounds - 1;
    $set_reached = (int) floor($calc_waves / 15) + 1;
    $round_reached = (int) floor($calc_waves % 15 / 5) + 1;
    $wave_reached = $calc_waves % 15 % 5 + 1;
    
    return array($bonus_rounds, $set_reached, $round_reached, $wave_reached);
}