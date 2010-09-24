<?php
require_once('reach.php');
// Halo: Reach Parser
// Player Game History

// Reach API Settings
define('REACH_API_PLAYER_HISTORY', '/player/gamehistory');

// Reach Player History Settings
define('REACH_PLAYER_HISTORY_CAMPAIGN', 'Campaign');
define('REACH_PLAYER_HISTORY_FIREFIGHT', 'Firefight');
define('REACH_PLAYER_HISTORY_COMPETITIVE', 'Competitive');
define('REACH_PLAYER_HISTORY_ARENA', 'Arena');
define('REACH_PLAYER_HISTORY_INVASION', 'Invasion');
define('REACH_PLAYER_HISTORY_CUSTOM', 'Custom');
define('REACH_PLAYER_HISTORY_ALL', 'Unknown');

// Reach Player Game History
class ReachPlayerHistory extends ReachBase {
    function get_history($gamertag, $varient_class, $page) {
        $url = 'http://' . BUNGIE_SERVER . REACH_API_JSON_ENDPOINT .
                           REACH_API_PLAYER_HISTORY . '/' . implode('/', array(
                           REACH_API_KEY, $gamertag, $variant_class, $page));
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
}
