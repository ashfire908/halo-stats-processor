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
        $page_url = 'http://' . BUNGIE_SERVER . '/' . HALO3_URL_CAMPAIGN_GAME .
                    '?gameid=' . $game_id;
        
        // Set up cURL
        $curl_page = curl_init($page_url);
        curl_setopt($curl_page, CURLOPT_USERAGENT, HTTP_USER_AGENT);
        curl_setopt($curl_page, CURLOPT_RETURNTRANSFER, 1);
        // Get RSS
        $this->html_data->loadHTML(curl_exec($curl_page));
        curl_close($curl_page);
    }
    
    function load_game() {
        $player_id = 'ctl00_mainContent_bnetpcgd_rptGamePlayers_ctl0*_pnlPlayerDetails';
        $skull_id = 'ctl00_mainContent_bnetpcgd_bnetSkulls_rptSkulls_ctl*_imgSkull';
        $skull_map = array(1 => 'Iron', 2 => 'BlackEye', 3 => 'ToughLuck',
                           4 => 'Catch', 5 => 'Fog', 6 => 'Famine',
                           7 => 'ThunderStorm', 8 => 'Tilt', 9 => 'Mythic',
                           12 => 'Blind', 13 => 'Cowbell',
                           14 => 'GruntBirthdayParty', 15 => 'IWHBYD');
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
        
        // Process players
        for ($i = 0; $i <= 3; $i++) {
            $id = str_replace('*', $i + 1, $player_id);
            if ($this->html_data->getElementById($id) != null) {
                $this->load_player($i);
            }
        }
        
        // Skulls
        foreach($skull_map as $skull_num => $skull_name) {
            $id = str_replace('*', str_pad($skull_num, 2, '0', STR_PAD_LEFT), $skull_id);
            if ($this->html_data->getElementById($id) != null) {
                $this->skulls[] = $skull_name;
            }
        }
        
        // Kills, Deaths, Medals, etc.
        foreach($this->players as $player) {
            $this->kills += $player->kills;
            $this->kills_enemy += $player->kills_enemy;
            $this->kills_vehicle += $player->kills_vehicle;
            $this->deaths += $player->deaths;
            $this->betrayals += $player->betrayals;
            $this->betrayals_player += $player->betrayals_player;
            $this->betrayals_ally += $player->betrayals_ally;
            
            foreach($player->kills_type_enemy as $key => $count) {
                $this->kills_type_enemy[$key] += $count;
            }
            foreach($player->kills_type_vehicle as $key => $count) {
                $this->kills_type_vehicle[$key] += $count;
            }
        }
        
    }
    
    protected function load_player($player_id) {
        // Get the ids to look for
        $player_num = $player_id + 1;
        $id_gamertag = "ctl00_mainContent_bnetpcgd_rptGamePlayers_ctl0${player_num}_hypGamertag";
        $id_emblem = "ctl00_mainContent_bnetpcgd_rptGamePlayers_ctl0${player_num}_EmblemCtrl_imgEmblem";
        $id_details = "ctl00_mainContent_bnetpcgd_rptGamePlayers_ctl0${player_num}_pnlPlayerDetails";
        $id_carnage = "ctl00_mainContent_bnetpcgd_rptCarnage_ctl0${player_num}_trPlayerRow";
        $id_enemy = "ctl00_mainContent_bnetpcgd_rptKills_ctl0${player_num}_trPlayerRow";
        $id_vehicle = "ctl00_mainContent_bnetpcgd_rptVehicles_ctl0${player_num}_trPlayerRow";
        
        // Create a player
        $player = new Halo3CampaignPlayer;
        
        // ID and Gamertag
        $player->id = $player_id;
        $player->gamertag = rtrim($this->html_data->
                                  getElementById($id_gamertag)->nodeValue, ' ');
        
        // Emblem
        $emblem_url = $this->html_data->getElementById($id_emblem)->
                      getAttribute('src');
        $emblem = array();
        foreach(explode('&', parse_url($emblem_url, PHP_URL_QUERY)) as $field) {
            $split = explode('=', $field, 2);
            $emblem[$split[0]] = $split[1];
        }
        $player->emblem_colors = array($emblem['0'], $emblem['1'], $emblem['2'], $emblem['3']);
        $player->emblem_design = array($emblem['fi'], $emblem['bi'], $emblem['fl']);
        
        // Player Score
        if ($this->scoring_enabled === true) {
            $score = $this->html_data->getElementById($id_details)->
                     getElementsByTagName('table')->item(0)->firstChild->
                     getElementsByTagName('td')->item(1)->nodeValue;
            if ($score == '-') {
                $player->score = 0;
            } else {
                $player->score = (int) str_replace(',', '', $score);
            }
        }
        
        // Kills, Deaths, and Betrayals
        $carnage_row = $this->html_data->getElementById($id_carnage)->
                       getElementsByTagName('td');
        
        $player->kills = (int) $carnage_row->item(1)->nodeValue;
        $player->kills_enemy = (int) $carnage_row->item(2)->nodeValue;
        $player->kills_vehicle = (int) $carnage_row->item(3)->nodeValue;
        $player->deaths = (int) $carnage_row->item(4)->nodeValue;
        $player->betrayals_player = (int) $carnage_row->item(5)->nodeValue;
        $player->betrayals_ally = (int) $carnage_row->item(6)->nodeValue;
        $player->betrayals = $player->betrayals_player + $player->betrayals_ally;
        
        // Kills by class
        $enemy_row = $this->html_data->getElementById($id_enemy)->
                     getElementsByTagName('td');
        $player->kills_type_enemy['infantry'] = (int) $enemy_row->item(2)->nodeValue;
        $player->kills_type_enemy['specialists'] = (int) $enemy_row->item(3)->nodeValue;
        $player->kills_type_enemy['leader'] = (int) $enemy_row->item(4)->nodeValue;
        $player->kills_type_enemy['hero'] = (int) $enemy_row->item(5)->nodeValue;
        
        $vehicle_row = $this->html_data->getElementById($id_vehicle)->
                       getElementsByTagName('td');
        $player->kills_type_vehicle['light'] = (int) $vehicle_row->item(2)->nodeValue;
        $player->kills_type_vehicle['medium'] = (int) $vehicle_row->item(3)->nodeValue;
        $player->kills_type_vehicle['heavy'] = (int) $vehicle_row->item(4)->nodeValue;
        $player->kills_type_vehicle['giant'] = (int) $vehicle_row->item(5)->nodeValue;
        
        
        // Add the player to the players
        $this->players[$player_id] = $player;
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
    public $players = array();
    
    // Skulls
    public $skulls = array();
    
    // Kills, Deaths, Medals, etc.
    public $kills = 0;
    public $kills_enemy = 0;
    public $kills_vehicle = 0;
    public $deaths = 0;
    public $betrayals = 0;
    public $betrayals_player = 0;
    public $betrayals_ally = 0;
    
    // Kills by type
    public $kills_type_enemy = array('infantry' => 0, 'specialists' => 0,
                                     'leader' => 0, 'hero' => 0);
    public $kills_type_vehicle = array('light' => 0, 'medium' => 0,
                                       'heavy' => 0, 'giant' => 0);
}

// Halo 3 Player class
class Halo3Player {
    function emblem_url($size) {
        // Generate URL for the player's emblem
        list($a_pri, $a_sec, $e_pri, $e_sec) = $this->emblem_colors;
        list($e_design, $b_design, $e_toggle) = $this->emblem_design;

        $url = 'http://' . BUNGIE_SERVER . '/' . EMBLEM_PATH .
        "?s=$size&0=$a_pri&1=$a_sec&2=$e_pri&3=$e_sec&fi=$e_design&bi=$b_design&fl=$e_toggle&m="
         . EMBLEM_GAME_HALO_3;
        return $url;
    }
    
    // Basic player details
    public $id;
    public $gamertag;
    
    // Emblems
    public $emblem_colors = array();
    public $emblem_design = array();
}

class Halo3CampaignPlayer extends Halo3Player {
    // Kills, Deaths, Medals, etc.
    public $score = -1;
    public $kills = 0;
    public $kills_enemy = 0;
    public $kills_vehicle = 0;
    public $deaths = 0;
    public $betrayals = 0;
    public $betrayals_player = 0;
    public $betrayals_ally = 0;
    
    // Kills by type
    public $kills_type_enemy = array('infantry' => 0, 'specialists' => 0,
                                     'leader' => 0, 'hero' => 0);
    public $kills_type_vehicle = array('light' => 0, 'medium' => 0,
                                       'heavy' => 0, 'giant' => 0);
    
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
