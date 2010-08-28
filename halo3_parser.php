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
    
    // HTML data
    public $html_data;
    
    // Errors
    public $error = false;
    public $error_details = array(0, '');
}

// Halo 3 Campaign Game class
class Halo3CampaignGame extends Halo3Game {
    // Difficulty constants
    const EASY = 0;
    const NORMAL = 1;
    const HEROIC = 2;
    const LEGENDARY = 3;
    
    function get_game($game_id) {
        $page_url = 'http://' . BUNGIE_SERVER . '/' . HALO3_URL_CAMPAIGN_GAME . '?gameid=' . $game_id;
        
        // Set up cURL
        $curl_page = curl_init($page_url);
        curl_setopt($curl_page, CURLOPT_USERAGENT, HTTP_USER_AGENT);
        curl_setopt($curl_page, CURLOPT_RETURNTRANSFER, 1);
        // Get RSS
        $this->html_data->loadHTML(curl_exec($curl_page));
        curl_close($curl_page);
    }
    
    function load_game() {
        // Regular Expressions for scraping the page.
        // Summary
        
        // TODO: Check if any errors occured with getting the page.
        
        // Pick out the game details part of the page.
        $game = $this->html_data->getElementById('ctl00_mainContent_bnetpcgd_pnlGameDetails');
        
        // Scan for and pick out game summary and overview parts
        $next_item = search_class($this->html_data->getElementsByTagName('div'),
                                  'stats_overview');
        $summary = search_class($next_item->getElementsByTagName('ul'),
                                'summary');
        // Overview parts
        $results = $this->html_data->getElementById('divResults');
        $carnage = $this->html_data->getElementById('divCarnage');
        $enemy_kills = $this->html_data->getElementById('divEnemyKills');
        $vehicle_kills = $this->html_data->getElementById('divVehicleKills');
        
        // Get the player(s)
        $player_1 = $this->html_data->getElementById('ctl00_mainContent_bnetpcgd_rptGamePlayers_ctl01_pnlPlayerDetails');
        $player_2 = $this->html_data->getElementById('ctl00_mainContent_bnetpcgd_rptGamePlayers_ctl02_pnlPlayerDetails');
        $player_3 = $this->html_data->getElementById('ctl00_mainContent_bnetpcgd_rptGamePlayers_ctl03_pnlPlayerDetails');
        $player_4 = $this->html_data->getElementById('ctl00_mainContent_bnetpcgd_rptGamePlayers_ctl04_pnlPlayerDetails');
        
        // TODO: Handle if anything coming out of DOM is null.
        
        // Parse Summary
        $current_line = 0;
        foreach($summary->getElementsByTagName('li') as $item) {
            switch($current_line) {
                case 0:
                    list($this->map, $difficulty) = explode(' on ', $item->nodeValue, 2);
                    switch ($difficulty) {
                        case 'Easy':
                            $this->difficulty = Halo3CampaignGame::EASY;
                            break;
                        case 'Normal':
                            $this->difficulty = Halo3CampaignGame::NORMAL;
                            break;
                        case 'Heroic':
                            $this->difficulty = Halo3CampaignGame::HEROIC;
                            break;
                        case 'Legendary':
                            $this->difficulty = Halo3CampaignGame::LEGENDARY;
                            break;
                    }
                    break;
                case 1:
                    $this->datetime = new DateTime($item->nodeValue, new DateTimeZone('America/Los_Angeles'));
                    break;
                case 2:
                    list(, $score) = explode('Total Score: ', $item->nodeValue, 2);
                    if ($score != 'scoring off') {
                        $this->scoring_enabled = true;
                        $this->score = (int) str_replace(',', '', $score);
                    }
                    break;
                case 3:
                    list(, $time) = explode('Total Time: ', $item->nodeValue, 2);
                    list($hours, $minutes, $seconds) = explode(':', $time, 3);
                    $this->duration = (int) $hours * 3600 + (int) $minutes * 60 + (int) $seconds;
                    break;
                case 4:
                    list(, $player_count) = explode('Players: ', $item->nodeValue, 2);
                    $this->player_count = (int) $player_count;
                    break;
            }
            $current_line++;
        }
        
    }
    
    // General info
    public $difficulty;
    public $duration;
    public $datetime;
    public $map;
    public $scoring_enabled = false;
    public $score = -1;
    
    // Players
    public $player_count = -1;
}

function search_class($elements, $class) {
    foreach($elements as $object) {
        // Check if the object has the class attribute
        if ($object->hasAttribute('class') == True) {
            // It does, look for the class.
            $classes = explode(' ', $object->getAttribute('class'));
            if (in_array($class, $classes)) {
                // Found our object. Return it and break.
                return $object;
            }
        }
    }
    return null;
}