<?php
require_once('reach.php');
// Halo: Reach Parser
// Player Details

// Reach API Settings
define('REACH_API_PLAYER_DETAILS', '/player/details');

// Reach Player Details Settings
define('REACH_PLAYER_DETAILS_STATS_MAP', 'bymap');
define('REACH_PLAYER_DETAILS_STATS_PLAYLIST', 'byplaylist');
define('REACH_PLAYER_DETAILS_NO_STATS', 'nostats');

// Reach Player Details
class ReachPlayerDetails extends ReachBase {
    function get_details($gamertag, $stats_mode) {
        $url = 'http://' . BUNGIE_SERVER . REACH_API_JSON_ENDPOINT .
                           REACH_API_PLAYER_DETAILS . '/' . implode('/',
                           array(REACH_API_KEY, $gamertag, $stats_mode));
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
