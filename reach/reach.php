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
    
    function check_error() {
        // Check for JSON and API errors
        
        // Check for JSON error
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                // No error
                break;
            case JSON_ERROR_DEPTH:
                $this->error = True;
                $this->error_details(-2, 'JSON Error - Maximum stack depth has been exceeded');
                break;
            case JSON_ERROR_CTRL_CHAR:
                $this->error = True;
                $this->error_details(-2, 'JSON Error - Control character error (possibly incorrectly encoded)');
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $this->error = True;
                $this->error_details(-2, 'JSON Error - Invalid or malformed JSON');
                break;
            case JSON_ERROR_SYNTAX:
                $this->error = True;
                $this->error_details(-2, 'JSON Error - Syntax error');
                break;
            case JSON_ERROR_UTF8:
                $this->error = True;
                $this->error_details(-2, 'JSON Error - Malformed UTF-8 characters (possibly incorrectly encoded)');
                break;
        }
        
        // Stop if we found an error
        if ($this->error == True) {
            return;
        }
        
        // Check for API error
        $this->error_details[0] = $this->json_data->status;
        $this->error_details[1] = $this->json_data->reason;
        
        if ($this->json_data->status != 0) {
            // Error
            $this->error = True;
        }
        
        return;
    }
    
    // JSON data
    public $json_data;
    
    // Errors
    public $error = false;
    public $error_details = array(0, '');
}
