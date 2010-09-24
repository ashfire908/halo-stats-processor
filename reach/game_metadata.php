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
        
        $this->load_json($data);
    }
    
    function load_metadata() {
        
    }
    
    // Metadata Sections
    public $commendations = array();
    public $enemies = array();
    public $maps = array();
    public $medals = array();
    public $weapons = array();
    public $game_variant_classes = array();
}

// Reach Game Commendations
class ReachGameCommendations {
    public $id;
    public $name;
    public $description;
    public $iron = false;
    public $bronze = false;
    public $silver = false;
    public $gold = false;
    public $onyx = false;
    public $max = false;
}

// Reach Game Enemies
class ReachGameEnemies {
    public $id;
    public $name;
    public $image_name;
    public $description;
}

// Reach Game Maps {
class ReachGameMaps {
    public $id;
    public $name;
    public $map_type;
    public $image_name;
}

// Reach Game Weapons
class ReachGameWeapons {
    public $id;
    public $name;
    public $description;
}
