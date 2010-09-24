<?php
require_once('reach.php');
// Halo: Reach Parser
// Rendered Videos

// Reach API Settings
define('REACH_API_FILE_VIDEOS', '/file/videos');

// Reach File Share
class ReachFileShare extends ReachBase {
    function get_videos($gamertag, $page) {
        $url = 'http://' . BUNGIE_SERVER . REACH_API_JSON_ENDPOINT .
                           REACH_API_FILE_VIDEOS . '/' . implode('/',
                           array(REACH_API_KEY, $gamertag, $page));
        // Set up cURL
        $curl_json = curl_init($url);
        curl_setopt($curl_json, CURLOPT_USERAGENT, HTTP_USER_AGENT);
        curl_setopt($curl_json, CURLOPT_RETURNTRANSFER, true);
        // Get data
        $data = curl_exec($curl_json);
        curl_close($curl_json);
        
        $this->load_json($data);
    }
    
    function load_videos() {
        
    }
}
