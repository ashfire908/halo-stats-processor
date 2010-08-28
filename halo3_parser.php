<?php
require_once('parser.php');
// Halo 3 Parser

// Halo 3 Parser Settings
define('HALO3_URL_CAMPAIGN_GAME', '/Stats/GameStatsCampaignHalo3.aspx');

// Halo 3 Game class
class Halo3Game {
    function __construct() {
        // Initialize the HTML data variable
        $this->init_html();
    }
    
    function init_html() {
        // (Re)create a blank DOMDocument to hold the HTML data
        $this->html_data = new DOMDocument;
    }
    
    function load_html($html) {
        // Import HTML into the HTML data property
        $this->html_data->loadHTML($html);
    }
    
    function dump_html() {
        // Export the HTML data as a string
        return $this->html_data->saveHTML();
    }
}