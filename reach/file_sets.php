<?php
require_once('reach.php');
// Halo: Reach Parser
// File Sets

// Reach API Settings
define('REACH_API_FILE_SET', '/file/sets');
define('REACH_API_FILE_SET_FILES', '/file/sets/files');

// Reach File Sets
class ReachFileSets extends ReachBase {
    function get_sets($gamertag) {
        $url = 'http://' . BUNGIE_SERVER . REACH_API_JSON_ENDPOINT .
                           REACH_API_FILE_SET . '/' . implode('/',
                           array(REACH_API_KEY, $gamertag));
        // Set up cURL
        $curl_json = curl_init($url);
        curl_setopt($curl_json, CURLOPT_USERAGENT, HTTP_USER_AGENT);
        curl_setopt($curl_json, CURLOPT_RETURNTRANSFER, true);
        // Get data
        $data = curl_exec($curl_json);
        curl_close($curl_json);
        
        $this->load_json($data);
    }
    
    function load_sets() {
        
    }
}

// Reach File Set Files
class ReachFileSetFiles extends ReachBase {
    function get_setfiles($gamertag, $fileset_id) {
        $url = 'http://' . BUNGIE_SERVER . REACH_API_JSON_ENDPOINT .
                           REACH_API_FILE_SET_FILES . '/' . implode('/',
                           array(REACH_API_KEY, $gamertag, $fileset_id)) . '/';
        // Set up cURL
        $curl_json = curl_init($url);
        curl_setopt($curl_json, CURLOPT_USERAGENT, HTTP_USER_AGENT);
        curl_setopt($curl_json, CURLOPT_RETURNTRANSFER, true);
        // Get data
        $data = curl_exec($curl_json);
        curl_close($curl_json);
        
        $this->load_json($data);
    }
    
    function load_setfiles() {
        
    }
}
