<?php
require_once('parser.php');
// Bungie.net RSS Parser

// RSS settings
define('RSS_FEED_HALO3', 'stats/halo3rss.ashx');
define('RSS_MODE_HALO3', 3);
define('RSS_MODE_ODST', 35);

// Recent game
class RecentGame {
    // Diffculty constants
    const EASY = 0;
    const NORMAL = 1;
    const HEROIC = 2;
    const LEGENDARY = 3;
    
    // Game attributes
    public $gameid;
    public $game;
    public $datetime;
    public $map;
    public $players;
}

// Recent Halo 3 Game
class RecentHalo3Game extends RecentGame {
    // Halo 3 Stats
    public $playlist;
    public $gametype;
    public $teams = true;
}

// Player in recent Halo 3 Game
class RecentHalo3Player {
    // Player info
    public $gamertag;
    public $team;
    
    // Player stats
    public $standing;
    public $score;
    public $kills;
    public $deaths;
    public $assists;
}

// Recent ODST Game
class RecentODSTGame extends RecentGame {
    // ODST stats
    public $difficulty;
    public $duration;
    public $mode;
    public $score = -1;
    
    // Firefight stats
    public $waves = -1;
}

// Get RSS data
function get_rss($gamertag, $game) {
    $rss = new DOMDocument();
    $rss_url = 'http://' . BUNGIE_SERVER . '/' . RSS_FEED_HALO3 . '?g=' .
               rawurlencode($gamertag) . '&md=' . $game;
    // Set up cURL
    $curl_rss = curl_init($rss_url);
    curl_setopt($curl_rss, CURLOPT_USERAGENT, HTTP_USER_AGENT);
    curl_setopt($curl_rss, CURLOPT_RETURNTRANSFER, 1);
    // Get RSS
    $rss->loadXML(curl_exec($curl_rss));
    curl_close($curl_rss);
    
    return $rss;
}

// Load a feed of Halo 3 Games
function halo3_rss($gamertag) {
    // Regular expressions
    $regex_link = '/gameid=([0-9]+)/';
    $regex_description = '/(?:Playlist: (?P<playlist>[A-Za-z0-9 ]+)).*?(?P<gametype>[A-Za-z0-9 ]+) on (?P<map>[A-Za-z0-9 ]+)/';
    $regex_player = '/(?P<gamertag>[A-Za-z0-9 ]+)(?: \((?P<team>[A-Za-z]+)\))?: (?P<standing>[0-9]+[stndrh]+), (?P<score>[0-9]+), (?P<kills>[0-9]+), (?P<deaths>[0-9]+), (?P<assists>[0-9]+)/';
    
    $games = array();
    
    // Get RSS
    $rss = get_rss($gamertag, RSS_MODE_HALO3);
    
    // Parse XML
    foreach($rss->getElementsByTagName('item') as $rss_game) {
        // Create new RSS game
        $game = new RecentHalo3Game;
        $game->game = GAME_HALO_3;
        
        // Get the data from the XML
        //$title = $rss_game->getElementsByTagName('title')->item(0)->nodeValue;
        $link = $rss_game->getElementsByTagName('link')->item(0)->nodeValue;
        $date = $rss_game->getElementsByTagName('pubDate')->item(0)->nodeValue;
        $description = $rss_game->getElementsByTagName('description')->item(0)->nodeValue;
        
        // Game ID
        preg_match($regex_link, parse_url($link, PHP_URL_QUERY), $link_match);
        list(, $game->gameid) = explode("=", $link_match[0]);
        
        // Datetime
        $game->datetime = date_create($date);
        
        // Playlist, gametype, map
        preg_match($regex_description, $description, $desc_match);
        $game->gametype = $desc_match['gametype'];
        $game->map = $desc_match['map'];
        if ($game->gametype == $game->map) {
            $game->playlist = "Forge";
        } else {
            $game->playlist = $desc_match['playlist'];
        }
        
        // Players
        preg_match_all($regex_player, $description, $players_match, PREG_SET_ORDER);
        
        foreach($players_match as $player_match) {
            $player = new RecentHalo3Player;
            
            // Set the stats
            $player->gamertag = $player_match['gamertag'];
            if (array_key_exists('team', $player_match)) {
                $player->team = $player_match['team'];
            } else {
                $game->teams = false;
            }
            $player->standing = $player_match['standing'];
            $player->score = $player_match['score'];
            $player->kills = $player_match['kills'];
            $player->deaths = $player_match['deaths'];
            $player->assists = $player_match['assists'];
            
            // Add player to array
            $game->players[] = $player;
        }
        
        // Append game to the array of games
        $games[] = $game;
    }
    
    return $games;
}

// Load a feed of ODST games
function odst_rss($gamertag) {
    // Regular expressions
    $regex_link = '/gameid=([0-9]+)/';
    $regex_description = '/(?:Difficulty: (?P<difficulty>(?:Easy|Normal|Heroic|Legendary))).*?(?:Game Duration: (?P<duration>[0-9\.]+) minutes).*?(?:Players: (?P<players>[0-9]))/';
    $regex_waves = '/(?:Waves: (?P<waves>[0-9]+))/';
    $regex_score = '/(?:Score: (?P<score>[0-9]+))/';
    
    $games = array();
    
    // Get RSS
    $rss = get_rss($gamertag, RSS_MODE_ODST);
    
    // Parse XML
    foreach($rss->getElementsByTagName('item') as $rss_game) {
        // Create new RSS game
        $game = new RecentODSTGame;
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
        $game->datetime = date_create($date);
        
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
                $game->difficulty = RecentODSTGame::EASY;
                break;
            case 'Normal':
                $game->difficulty = RecentODSTGame::NORMAL;
                break;
            case 'Heroic':
                $game->difficulty = RecentODSTGame::HEROIC;
                break;
            case 'Legendary':
                $game->difficulty = RecentODSTGame::LEGENDARY;
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