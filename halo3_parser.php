<?php
require_once('parser.php');
// Halo 3 Parser

// Halo 3 Parser Settings
define('HALO3_URL_CAMPAIGN_GAME', 'Stats/GameStatsCampaignHalo3.aspx');

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

// Halo 3 Campaign Game class
class Halo3CampaignGame extends Halo3Game {
    function get_game($game_id) {
        $page_url = 'http://' . BUNGIE_SERVER . '/' . HALO3_URL_CAMPAIGN_GAME . '?gameid=' . $game_id;
        
        // Set up cURL
        $curl_page = curl_init($page_url);
        curl_setopt($curl_rss, CURLOPT_USERAGENT, HTTP_USER_AGENT);
        curl_setopt($curl_rss, CURLOPT_RETURNTRANSFER, 1);
        // Get RSS
        $this->html_data->loadHTML(curl_exec($curl_page));
        curl_close($curl_page);
    }
}