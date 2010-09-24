<?php
require_once('reach.php');
// Halo: Reach Parser
// File Search

// Reach API Settings
define('REACH_API_FILE_SEARCH', '/file/search');
// Categories
define('REACH_API_FILE_SEARCH_CAT_IMAGE', 'Image');
define('REACH_API_FILE_SEARCH_CAT_VIDEO', 'GameClip');
define('REACH_API_FILE_SEARCH_CAT_MAP', 'GameMap');
define('REACH_API_FILE_SEARCH_CAT_GAMEVARIANT', 'GameSettings');
// Engine
define('REACH_API_FILE_SEARCH_ENG_ALL', 'null');
define('REACH_API_FILE_SEARCH_ENG_CAMPAIGN', 'Campaign');
define('REACH_API_FILE_SEARCH_ENG_FORGE', 'Forge');
define('REACH_API_FILE_SEARCH_ENG_MULTIPLAYER', 'Multiplayer');
define('REACH_API_FILE_SEARCH_ENG_FIREFIGHT', 'Firefight');
// Date
define('REACH_API_FILE_SEARCH_DATE_DAY', 'Day');
define('REACH_API_FILE_SEARCH_DATE_WEEK', 'Week');
define('REACH_API_FILE_SEARCH_DATE_MONTH', 'Month');
define('REACH_API_FILE_SEARCH_DATE_ALL', 'All');
// Sort
define('REACH_API_FILE_SEARCH_SORT_RELEVANT', 'MostRelevant');
define('REACH_API_FILE_SEARCH_SORT_RECENT', 'MostRecent');
define('REACH_API_FILE_SEARCH_SORT_DOWNLOADS', 'MostDownloads');
define('REACH_API_FILE_SEARCH_SORT_RATING', 'HighestRated');

// Reach File Search
class ReachFileSearch extends ReachBase {
    function get_search($category, $date, $sort, $page, $map = NULL,
                        $engine = REACH_API_FILE_SEARCH_ENG_ALL, $tags = NULL) {
        if ($map === NULL) {
            $map = 'null';
        }
        if ($tags === NULL) {
            $tags = array();
        }
        $url = 'http://' . BUNGIE_SERVER . REACH_API_JSON_ENDPOINT .
                           REACH_API_FILE_SEARCH . '/' . implode('/',
                           array(REACH_API_KEY, $category, $map, $engine, $date,
                                                $sort, $page)) . 
                           '?tags=' . implode(';', $tags);
        // Set up cURL
        $curl_json = curl_init($url);
        curl_setopt($curl_json, CURLOPT_USERAGENT, HTTP_USER_AGENT);
        curl_setopt($curl_json, CURLOPT_RETURNTRANSFER, true);
        // Get data
        $data = curl_exec($curl_json);
        curl_close($curl_json);
        
        $this->load_json($data);
    }
    
    function load_search() {
        
    }
}
