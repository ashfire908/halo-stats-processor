<?php
require_once('../parser.php');
// Halo: Reach Parser
// Shared

// Reach API Settings
define('REACH_API_KEY', NULL);
define('REACH_API_JSON_ENDPOINT', '/api/reach/reachapijson.svc');

// Base class for Reach Parser
class ReachBase {
    function load_json($json) {
        // Load JSON into the JSON data property
        $this->json_data = json_decode($json);
    }
    
    function dump_json() {
        // Dumps the JSON in the data property as a string
        return json_encode($this->json_data);
    }
    
    // JSON data
    public $json_data;
    
    // Errors
    public $error = false;
    public $error_details = array(0, '');
}
