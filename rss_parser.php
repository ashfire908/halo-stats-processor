<?php
require_once('parser.php');
// Bungie.net RSS Parser

// RSS settings
define('HALO3_RSS_FEED', 'stats/halo3rss.ashx');
define('RSS_DATE_FORMAT', 'D, d M Y H:i:s T');
define('ODST_RSS', 35);

// RSS feed game
class RSSGame {
    // Diffculty constants
    const EASY = 0;
    const NORMAL = 1;
    const HEROIC = 2;
    const LEGENDARY = 3;
    
    // Game attributes
    public $gameid;
    public $game;
    public $difficulty;
    public $duration;
    public $datetime;
    public $map;
    public $mode;
    public $players;
    public $score = -1;
    
    // Firefight stats
    public $waves = -1;    
}

// Load a feed of ODST games into RSS games
function odst_rss($gamertag) {
    $regex_link = '/gameid=([0-9]+)/';
    $regex_description = '/(?i:Difficulty: (?P<difficulty>(?i:Easy|Normal|Heroic|Legendary))).*?(?i:Game Duration: (?P<duration>[0-9\.]+) minutes).*?(?i:Players: (?P<players>[0-9]))/';
    $regex_waves = '/(?i:Waves: (?P<waves>[0-9]+))/';
    $regex_score = '/(?i:Score: (?P<score>[0-9]+))/';
    
    $games = array();
    
    // Get RSS
    $rss = new DOMDocument();
    $rss_url = 'http://' . BUNGIE_SERVER . '/' . HALO3_RSS_FEED . '?g=' .
                rawurlencode($gamertag) . '&md=' . ODST_RSS;
    $curl_rss = curl_init($rss_url);
    curl_setopt($curl_rss, CURLOPT_USERAGENT, HTTP_USER_AGENT);
    curl_setopt($curl_rss, CURLOPT_RETURNTRANSFER, 1);
    $rss->loadXML(curl_exec($curl_rss));
    curl_close($curl_rss);
    
    // Parse XML
    foreach($rss->getElementsByTagName('item') as $rss_game) {
        // Create new RSS game
        $game = new RSSGame;
        $game->game = GAME_ODST;
        
        // Get the data from the XML
        $title = $rss_game->getElementsByTagName('title')->item(0)->nodeValue;
        $link = $rss_game->getElementsByTagName('link')->item(0)->nodeValue;
        $date = $rss_game->getElementsByTagName('pubDate')->item(0)->nodeValue;
        $description = $rss_game->getElementsByTagName('description')->item(0)->nodeValue;
        
        // Mode, map
        list($game->mode, $game->map) = explode(' on ', $title, 2);
        
        // Game ID
        preg_match($regex_link, parse_url($link, PHP_URL_QUERY), $link_match);
        list(, $game->gameid) = explode("=", $link_match[0]);
        
        // Datetime
        $game->datetime = DateTime::createFromFormat(RSS_DATE_FORMAT, $date);
        
        // Remaining stats
        // Run the regexps on the description.
        // Yes, it's messy. If you can get a better (working) way...
        preg_match($regex_description, $description, $desc_match);
        preg_match($regex_score, $description, $desc_match_score);
        preg_match($regex_waves, $description, $desc_match_waves);
        $desc_match = array_merge($desc_match, $desc_match_score, $desc_match_waves);
        
        // Run through the results of the regexps
        switch ($desc_match['difficulty']) {
            case 'Easy':
                $game->difficulty = RSSGame::EASY;
                break;
            case 'Normal':
                $game->difficulty = RSSGame::NORMAL;
                break;
            case 'Heroic':
                $game->difficulty = RSSGame::HEROIC;
                break;
            case 'Legendary':
                $game->difficulty = RSSGame::LEGENDARY;
                break;
        }
        $game->duration = $desc_match['duration'] * 60;
        $game->players = $desc_match['players'];
        if (array_key_exists('score', $desc_match)) {
            $game->score = $desc_match['score'];
        }
        if ($game->mode === 'Firefight') {
            $game->waves = $desc_match['waves'];
        }
        
        // Append game to the array of games
        $games[] = $game;
    }
    
    return $games;
}