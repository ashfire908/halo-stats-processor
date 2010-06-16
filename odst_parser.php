<?php
require_once('parser.php');
// ODST Game Parser

define('ODST_EMBLEM_GAME', 2);
// ODST SOAP Settings
define('ODST_SERVICE', 'api/odst/ODSTService');
define('ODST_GAME', 'GetGameDetail');
define('ODST_METADATA', 'GetGameMetaData');
define('ODST_SOAP_CLIENT_URI', 'http://tempuri.org/');
define('SOAP_CLIENT_VERSION', 2);
define('SOAP_REQUEST_VERSION', SOAP_1_1);

// ODST Game class
class ODSTGame {
    function __construct() {
        $this->init_xml();
    }
    
    // Diffculty constants
    const EASY = 0;
    const NORMAL = 1;
    const HEROIC = 2;
    const LEGENDARY = 3;
    
    function init_xml() {
        // (Re)create a blank DOMDocument for SOAP/XML data
        $this->xml_data = new DOMDocument;
    }
    
    function dump_xml() {
        // Recreate the XML document into a string
        return $this->xml_data->saveXML();
    }
    
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
        
        // Break down string into date and time
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
            $player_info->armor_flags = unpack('C*', base64_decode((string) $player->ArmorFlags));
            $player_info->armor_type = (int) $player->ArmorType;
            $player_info->emblem_colors = unpack('C*', base64_decode((string) $player->EmblemColors));
            $player_info->emblem_flags = unpack('C*', base64_decode((string) $player->EmblemFlags));
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
            
            // Convert scores to integers
            $this->score = (int) $this->score;
            foreach ($this->players as $player) {
                $player->score = (int) $player->score;
            }
            foreach ($this->wave_stats as $wave) {
                $wave->score = (int) $wave->score;
            }
        }
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
    public $time_bonus = 0.0;
    public $firefight = false;
    
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
    // Medals
    public $medals = array();
}

// ODST Player class
class ODSTPlayer {
    function emblem_url($size) {
        list(, $a_pri, $a_sec, $e_pri, $e_sec) = $this->emblem_colors;
        list(, $e_design, $b_design, $e_toggle) = $this->emblem_flags;
        
        // Reverse Toggle setting
        $e_toggle = $e_toggle? 0 : 1;

        $url = 'http://' . BUNGIE_SERVER . '/' . EMBLEM_PATH .
        "?s=$size&0=$a_pri&1=$a_sec&2=$e_pri&3=$e_sec&fi=$e_design&bi=$b_design&fl=$e_toggle&m="
         . ODST_EMBLEM_GAME;
        return $url;
    }
    
    public $id;
    public $gamertag;
    public $service_tag;
    public $armor_flags;
    public $armor_type;
    public $emblem_colors;
    public $emblem_flags;
    public $score = -1;
    public $kills = 0;
    public $deaths = 0;
    public $suicides = 0;
    public $betrayals = 0;
    public $weapons_used = array();
    public $medals = array();
}

// ODST Firefight wave stats class
class ODSTFirefightWave {
    public $id;
    public $activity = false;
    public $start;
    public $end;
    public $length;
    public $score = -1;
    public $kills = 0;
    public $deaths = 0;
    public $suicides = 0;
    public $betrayals = 0;
    public $weapons_used = array();
    public $medals = array();
}

// ODST Metadata class
class ODSTMetadata {
    function __construct() {
        // Create SOAP/XML DOMDocument
        $this->init_xml();
    }
    
    // Constants for image size/type
    const IMAGE_SMALL = 'sm';
    const IMAGE_MEDIUM = 'med';
    const IMAGE_LARGE = 'large';
    const IMAGE_PNG = 'png';
    const IMAGE_GIF = 'gif';
    
    function init_xml() {
        // (Re)create a blank DOMDocument for SOAP/XML data
        $this->xml_data = new DOMDocument;
    }
    
    function dump_xml() {
        // Recreate the XML document into a string
        return $this->xml_data->saveXML();
    }
    
    function image_url($object, $mode, $size = NULL, $type = NULL) {
        if ($object->image_gen === false) {
            return;
        }
        if ($size == NULL) {
            if ($this->image_default_size == NULL) {
                return;
            }
            $size = $this->image_default_size;
        }
        if ($type == NULL) {
            if ($this->image_default_type == NULL) {
                return;
            }
            $type = $this->image_default_type;
        }
        $path = $object->image_path;
        $path = str_replace('{0}', $type, $path);
        $path = str_replace('{1}', $size, $path);
        $path = str_replace('{2}', $object->image_name, $path);
        $path = str_replace('{3}', $type, $path);
        switch ($mode) {
            case 'get':
                return 'http://' . BUNGIE_SERVER . $path;
                break;
            case 'set':
                $object->image_url = 'http://' . BUNGIE_SERVER . $path;
                break;
        }
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
        $this->xml_data->loadXML($client->__doRequest($soap_request, $url, $soap_url, SOAP_1_1));
    }
    
    function load_metadata() {
        // Grab the GetGameMetaDataResult Result for simplexml
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
        
        $character_xml = $metadata->CharacterInfo; // Characters
        $medal_xml = $metadata->MedalInfo; // Medals
        $skull_xml = $metadata->SkullInfo; // Skulls
        $weapon_xml = $metadata->WeaponInfo; // Weapons
        
        // Process Characters
        foreach($character_xml->children('c', true) as $character_class) {
            foreach($character_class->Value->KeyValueOfstringChar93kMfpyL as $character) {
                $odst_character = new ODSTCharacter;
                $character_attr = $character->Value->children('d', true);
                
                $odst_character->id = (string) $character_attr->Id;
                list($odst_character->name, $odst_character->class) = explode('_', $odst_character->id);
                $odst_character->display_name = (string) $character_attr->Disp;
                $odst_character->image_name = (string) $character_attr->ImgName;
                $odst_character->image_path = (string) $character_attr->ImgPath;
                $odst_character->description = (string) $character_attr->Desc;
                $odst_character->points = (int) $character_attr->Pnts;
                if ($character_attr->Vehic == 'true') {
                    $odst_character->vehicle = true;
                } elseif ($character_attr->Vehic == 'false') {
                    $odst_character->vehicle = false;
                }
                
                if (! array_key_exists($odst_character->class, $this->characters)) {
                    $this->characters[$odst_character->class] = array();
                }
                $this->characters[$odst_character->class][$odst_character->name] = $odst_character;
            }
        }
        
        // Process Medals
        foreach($medal_xml->children('c', true) as $medal) {
            $odst_medal = new ODSTMedal;
            $medal_attr = $medal->Value->children('d', true);
            
            $odst_medal->id = (string) $medal_attr->Type;
            $odst_medal->display_name = (string) $medal_attr->Disp;
            $odst_medal->image_name = (string) $medal_attr->ImgName;
            $odst_medal->image_path = (string) $medal_attr->ImgPath;
            $odst_medal->group = (string) $medal_attr->RowGroup;
            $odst_medal->description = (string) $medal_attr->Desc;
            $odst_medal->points = (int) $medal_attr->Points;
            $odst_medal->display_row = (int) $medal_attr->Row;
            $odst_medal->tier = (int) $medal_attr->Tier;
            
            $this->medals[$odst_medal->id] = $odst_medal;
        }
        
        // Process Skulls
        foreach($skull_xml->children('c', true) as $skull) {
            $odst_skull = new ODSTSkull;
            $skull_attr = $skull->Value->children('d', true);
            
            $odst_skull->id = (string) $skull_attr->ID;
            $odst_skull->display_name = (string) $skull_attr->Display;
            $odst_skull->image_enabled = (string) $skull_attr->Image;
            $odst_skull->image_disabled = (string) $skull_attr->ImageOff;
            $odst_skull->description = (string) $skull_attr->Desc;
            $odst_skull->score_multiplier = (float) $skull_attr->Multiplier;
            $odst_skull->order = (int) $skull_attr->Sort;
            
            $this->skulls[$odst_skull->id] = $odst_skull;
        }
        
        // Process Weapons
        foreach ($weapon_xml->children('c', true) as $weapon) {
            $odst_weapon = new ODSTWeapon;
            $weapon_attr = $weapon->Value->children('d', true);
            
            $odst_weapon->id = (string) $weapon_attr->Type;
            $odst_weapon->display_name = (string) $weapon_attr->Disp;
            $odst_weapon->image_name = (string) $weapon_attr->ImgName;
            $odst_weapon->image_path = (string) $weapon_attr->ImgPath;
            $odst_weapon->description = (string) $weapon_attr->Desc;
            
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

// ODST Metadata Character
class ODSTCharacter {
    public $image_gen = true; // Image URL Generation supported
    
    public $id; // Internal name
    public $name; // Character name
    public $class; // Character class
    public $display_name; // Human-friendly name
    public $image_name;
    public $image_path;
    public $image_url;
    public $description;
    public $points = 0;
    public $vehicle = false;
}

// ODST Metadata Medal
class ODSTMedal {
    public $image_gen = true; // Image URL Generation supported
    
    public $id; // Internal name
    public $display_name; // Human-friendly name
    public $image_name;
    public $image_path;
    public $image_url;
    public $group;
    public $description;
    public $points = 0;
    public $display_row;
    public $tier;
}

// ODST Metadata Skull
class ODSTSkull {
    public $image_gen = false; // Image URL Generation not supported
    
    function image_url($type) {
        switch ($type) {
            case 'enabled':
                return 'http://' . BUNGIE_SERVER . $self->image_enabled;
                break;
            case 'disabled':
                return 'http://' . BUNGIE_SERVER . $self->image_disabled;
                break;
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
class ODSTWeapon {
    public $image_gen = true;  // Image URL Generation supported
    
    public $id; // Internal name
    public $display_name; // Human-friendly name
    public $image_name;
    public $image_path;
    public $image_url;
    public $description;
}

// Firefight Wave Position Calculator
function wave_position($total_waves) {
    $bonus_rounds = (int) floor($total_waves / 16);
    $calc_waves = $total_waves - $bonus_rounds - 1;
    $set_reached = (int) floor($calc_waves / 15) + 1;
    $round_reached = (int) floor($calc_waves % 15 / 5) + 1;
    $wave_reached = $calc_waves % 15 % 5 + 1;
    
    return array($bonus_rounds, $set_reached, $round_reached, $wave_reached);
}
