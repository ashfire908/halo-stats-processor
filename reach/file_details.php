<?php
require_once('reach.php');
// Halo: Reach Parser
// File Details

// Reach API Settings
define('REACH_API_FILE_DETAILS', '/file/details');

// Reach File Details
class ReachFileDetails extends ReachBase {
    function get_details($file_id) {
        $url = 'http://' . BUNGIE_SERVER . REACH_API_JSON_ENDPOINT .
                           REACH_API_FILE_DETAILS . '/' . implode('/',
                           array(REACH_API_KEY, $file_id));
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
