<?php
/* Halo Stats Processor
 * Bungie.net RSS Parser
 * 
 * Copyright © 2010 Andrew Hampe
 * 
 * This file is part of Halo Stats Processor.
 * 
 * Halo Stats Processor is free software: you can redistribute it
 * and/or modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either version
 * 2.1 of the License, or (at your option) any later version.
 * 
 * Halo Stats Processor is distributed in the hope that it will be
 * useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with Halo Stats Processor. If not, see
 * <http://www.gnu.org/licenses/>.
 */

// Requires core parser
require_once('parser.php');

// RSS settings
define('RSS_FEED_HALO3', 'stats/halo3rss.ashx');
define('RSS_MODE_HALO3', 3);
define('RSS_MODE_ODST', 35);

// Recent game base class
class RecentGame {
    // Difficulty constants
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
    public $gamevariant;
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
    // Regular expressions for parsing the RSS
    $regex_link = '/gameid=([0-9]+)/';
    $regex_description = '/(?:Playlist: (?P<playlist>[A-Za-z0-9 ]+)).*?(?P<gamevariant>[A-Za-z0-9 ]+) on (?P<map>[A-Za-z0-9 ]+)/';
    $regex_player = '/(?P<gamertag>[A-Za-z0-9 ]+)(?: \((?P<team>[A-Za-z]+)\))?: (?P<standing>[0-9]+)[stndrh]+, (?P<score>[0-9]+), (?P<kills>[0-9]+), (?P<deaths>[0-9]+), (?P<assists>[0-9]+)/';
    
    $games = array();
    
    // Get RSS
    $rss = get_rss($gamertag, RSS_MODE_HALO3);
    
    // Parse XML
    foreach($rss->getElementsByTagName('item') as $rss_game) {
        // Create new RSS game
        $game = new RecentHalo3Game;
        $game->game = GAME_HALO_3;
        
        // Get the data from the XML
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
        $game->gamevariant = $desc_match['gamevariant'];
        $game->map = $desc_match['map'];
        $game->playlist = $desc_match['playlist'];
        
        // Players
        preg_match_all($regex_player, $description, $players_match, PREG_SET_ORDER);
        
        foreach($players_match as $player_match) {
            $player = new RecentHalo3Player;
            
            // Set the stats
            $player->gamertag = $player_match['gamertag'];
            if (!is_null($player_match['team'])) {
                $player->team = $player_match['team'];
            } else {
                $game->teams = false;
            }
            $player->standing = (int) $player_match['standing'];
            $player->score = (int) $player_match['score'];
            $player->kills = (int) $player_match['kills'];
            $player->deaths = (int) $player_match['deaths'];
            $player->assists = (int) $player_match['assists'];
            
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
    // Regular expressions for parsing the RSS
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
        // Run the regular expressions on the description.
        // TODO: Make this more clean; merge the reg exps
        preg_match($regex_description, $description, $desc_match);
        preg_match($regex_score, $description, $desc_match_score);
        preg_match($regex_waves, $description, $desc_match_waves);
        $desc_match = array_merge($desc_match, $desc_match_score, $desc_match_waves);
        
        // Run through the results of the regular expressions
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
        if (array_key_exists('score', $desc_match) and !is_null($desc_match['score'])) {
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
