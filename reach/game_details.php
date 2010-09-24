<?php
require_once('reach.php');
// Halo: Reach Parser
// Game Details

// Reach API settings
define('REACH_API_GAME_DETAILS', '/game/details');

// Reach Game Details
class ReachGameDetails extends ReachBase {
    function get_details($game_id) {
        $url = 'http://' . BUNGIE_SERVER . REACH_API_JSON_ENDPOINT .
                           REACH_API_GAME_DETAILS . '/' . implode('/',
                           array(REACH_API_KEY, $game_id));
        // Set up cURL
        $curl_json = curl_init($url);
        curl_setopt($curl_json, CURLOPT_USERAGENT, HTTP_USER_AGENT);
        curl_setopt($curl_json, CURLOPT_RETURNTRANSFER, true);
        // Get data
        $data = curl_exec($curl_json);
        curl_close($curl_json);
        
        $this->load_json($data);
    }
    
    function load_details() {
        
    }
}
