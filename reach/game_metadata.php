<?php
require_once('reach.php');
// Halo: Reach Parser
// Game Metadata

// Reach API settings
define('REACH_API_GAME_METADATA', '/game/metadata');

// Reach Game Metadata
class ReachGameMetadata extends ReachBase {
    function get_metadata() {
        $url = 'http://' . BUNGIE_SERVER . REACH_API_JSON_ENDPOINT .
                           REACH_API_GAME_METADATA . '/' . REACH_API_KEY;
        // Set up cURL
        $curl_json = curl_init($url);
        curl_setopt($curl_json, CURLOPT_USERAGENT, HTTP_USER_AGENT);
        curl_setopt($curl_json, CURLOPT_RETURNTRANSFER, true);
        // Get data
        $data = curl_exec($curl_json);
        curl_close($curl_json);
        
        // Load JSON
        $this->load_json($data);
    }
    
    function load_metadata() {
        // Check for error
        $this->check_error();
        if ($this->error == True) {
            return False;
        }
        
        // Commendations
        foreach($this->json_data->Data->AllCommendationsById as $commend) {
            $commendation = new ReachMetadataCommendation;
            
            // Set the commendation properties
            $commendation->id = $commend->Value->Id;
            $commendation->name = $commend->Value->Name;
            $commendation->description = $commend->Value->Description;
            $commendation->thresholds['iron'] = $commend->Value->Iron;
            $commendation->thresholds['bronze'] = $commend->Value->Bronze;
            $commendation->thresholds['silver'] = $commend->Value->Silver;
            $commendation->thresholds['gold'] = $commend->Value->Gold;
            $commendation->thresholds['onyx'] = $commend->Value->Onyx;
            $commendation->thresholds['max'] = $commend->Value->Max;
            
            // Add to the list of commendations
            $this->commendations[$commendation->id] = $commendation;
        }
        
        // Enemies
        foreach($this->json_data->Data->AllEnemiesById as $api_enemy) {
            $enemy = new ReachMetadataEnemy;
            
            // Set the enemy properties
            $enemy->id = $api_enemy->Value->Id;
            $enemy->name = $api_enemy->Value->Name;
            $enemy->image_name = $api_enemy->Value->ImageName;
            $enemy->description = $api_enemy->Value->Description;
            
            // Add to the list of enemies
            $this->enemies[$enemy->id] = $enemy;
        }
        
        // Maps
        foreach($this->json_data->Data->AllMapsById as $api_map) {
            $map = new ReachMetadataMap;
            
            // Set the map properties
            $map->id = $api_map->Value->Id;
            $map->name = $api_map->Value->Name;
            $map->map_type = $api_map->Value->MapType;
            $map->image_name = $api_map->Value->ImageName;
            
            // Add to the list of maps
            $this->maps[$map->id] = $map;
        }
        
        // Medals
        foreach($this->json_data->Data->AllMedalsById as $api_medal) {
            $medal = new ReachMetadataMedal;
            
            // Set the medal properties
            $medal->id = $api_medal->Value->Id;
            $medal->name = $api_medal->Value->Name;
            $medal->image_name = $api_medal->Value->ImageName;
            $medal->description = $api_medal->Value->Description;
            
            // Add to the list of medals
            $this->medals[$medal->id] = $medal;
        }
        
        // Weapons
        foreach($this->json_data->Data->AllWeaponsById as $api_weapon) {
            $weapon = new ReachMetadataWeapon;
            
            // Set the weapon properties
            $weapon->id = $api_medal->Value->Id;
            $weapon->name = $api_medal->Value->Name;
            $weapon->description = $api_medal->Value->Description;
            
            // Add to the list of weapons
            $this->weapons[$weapon->id] = $weapon;
        }
        
        // Game Variant Classes
        foreach($this->json_data->Data->GameVariantClassesKeysAndValues as $class) {
            $this->game_variant_classes[$class->Value] = $class->Key;
        }
        
        // Parsed successfully
        return True;
    }
    
    // Metadata Sections
    public $commendations = array();
    public $enemies = array();
    public $maps = array();
    public $medals = array();
    public $weapons = array();
    public $game_variant_classes = array();
}

// Reach Game Metadata Commendation
class ReachMetadataCommendation {
    public $id;
    public $name;
    public $description;
    public $thresholds = array('iron' => -1, 'bronze' => -1, 'silver' => -1,
                               'gold' => -1, 'onyx' => -1, 'max' => -1);
}

// Reach Game Metadata Enemy
class ReachMetadataEnemy {
    public $id;
    public $name;
    public $image_name;
    public $description;
}

// Reach Game Metadata Map {
class ReachMetadataMap {
    public $id;
    public $name;
    public $map_type;
    public $image_name;
}

// Reach Game Metadata Medal
class ReachMetadataMedal {
    public $id;
    public $name;
    public $image_name;
    public $description;
}

// Reach Game Weapon
class ReachGameWeapon {
    public $id;
    public $name;
    public $description;
}
