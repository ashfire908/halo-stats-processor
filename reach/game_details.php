<?php
require_once('reach.php');
// Halo: Reach Parser
// Game Details

// Reach API settings
define('REACH_API_GAME_DETAILS', '/game/details');

// Reach Game Details
class ReachGameDetails extends ReachBase {
    function get_details($game_id) {
        $url = 'http://' . BUNGIE_SERVER . REACH_API_JSON_ENDPOINT .
                           REACH_API_GAME_DETAILS . '/' . implode('/',
                           array(REACH_API_KEY, $game_id));
        // Set up cURL
        $curl_json = curl_init($url);
        curl_setopt($curl_json, CURLOPT_USERAGENT, HTTP_USER_AGENT);
        curl_setopt($curl_json, CURLOPT_RETURNTRANSFER, true);
        // Get data
        $data = curl_exec($curl_json);
        curl_close($curl_json);
        
        // Load JSON
        $this->load_json($data);
    }
    
    function load_details() {
        // Check for error
        $this->check_error();
        if ($this->error == True) {
            return False;
        }
        
        // General info
        $this->game_id = $this->json_data->GameDetails->GameId;
        $this->has_details = $this->json_data->GameDetails->HasDetails;
        $this->duration = $this->json_data->GameDetails->GameDuration;
        
        // Date/time
        $this->datetime = ReachBase::parse_timestamp($this->json_data->GameDetails->GameTimestamp);
        
        // Map info/Game type/Playlist
        $this->map_name = $this->json_data->GameDetails->MapName;
        $this->map_base = $this->json_data->GameDetails->BaseMapName;
        $this->variant_name = $this->json_data->GameDetails->GameVariantName;
        $this->variant_class = $this->json_data->GameDetails->GameVariantClass;
        $this->playlist = $this->json_data->GameDetails->PlaylistName;
        
        // Hashes
        $this->map_hash = $this->json_data->GameDetails->MapVariantHash;
        $this->variant_hash = $this->json_data->GameDetails->GameVariantHash;

        
        // Players
        $this->player_count = $this->json_data->GameDetails->PlayerCount;
        foreach($this->json_data->GameDetails->Players as $api_player) {
            if ($api_player->PlayerDetail->Initialized) {
                $player = new ReachGamePlayer;
                
                // Set the properties
                $player->id = $api_player->PlayerDataIndex;
                $player->finished = $api_player->DNF? False : True; // Reverse value
                $player->guest = $api_player->IsGuest;
                $player->team = $api_player->Team;
                
                // Performance
                $player->standing = $api_player->Standing;
                $player->standing_ffa = $api_player->IndividualStandingWithNoRegardForTeams;
                $player->rating = $api_player->Rating;
                $player->score = $api_player->Score;
                $player->score_team = $api_player->TeamScore;
                $player->points_over_time = $api_player->PointsOverTime;
                
                // Kills, Deaths, Medals, etc.
                $player->kill_death_ratio = $api_player->Kills - $api_player->Deaths;
                $player->kills = $api_player->Kills;
                $player->kills_over_time = $api_player->KillsOverTime;
                $player->deaths = $api_player->Deaths;
                $player->deaths_over_time = $api_player->DeathsOverTime;
                $player->betrayals = $api_player->Betrayals;
                $player->suicides = $api_player->Suicides;
                $player->assists = $api_player->Assists;
                $player->headshots = $api_player->Headshots;
                foreach($api_player->SpecificMedalCounts as $medal) {
                    $player->medals[$medal->Key] = $medal->Value;
                }
                $player->medals_over_time = $api_player->MedalsOverTime;
                
                // Medal counts
                $player->medal_count["total"] = $api_player->TotalMedalCount;
                $player->medal_count["style"] = $api_player->StyleMedalCount;
                $player->medal_count["multi"] = $api_player->MultiMedalCount;
                $player->medal_count["spree"] = $api_player->SpreeMedalCount;
                $player->medal_count["other"] = $api_player->OtherMedalCount;
                
                $player->medal_count_unique["total"] = $api_player->UniqueTotalMedalCount;
                $player->medal_count_unique["style"] = $api_player->UniqueStyleMedalCount;
                $player->medal_count_unique["multi"] = $api_player->UniqueMultiMedalCount;
                $player->medal_count_unique["spree"] = $api_player->UniqueSpreeMedalCount;
                $player->medal_count_unique["other"] = $api_player->UniqueOtherMedalCount;
                
                // Killed most, Killed most by, Average distances
                $player->killed_most[0] = $api_player->PlayerKilledMost;
                $player->killed_most[1] = $api_player->KilledMostCount;
                $player->killed_most_by[0] = $api_player->PlayerKilledByMost;
                $player->killed_most_by[1] = $api_player->KilledMostByCount;
                $player->average_kill_distance = $api_player->AvgKillDistanceMeters;
                $player->average_death_distance = $api_player->AvgDeathDistanceMeters;
                
                
                // Player info
                $player_info = new ReachGamePlayerInfo;
                
                // General info
                $player_info->initialized = $api_player->PlayerDetail->Initialized;
                $player_info->guest = $api_player->PlayerDetail->IsGuest;
                $player_info->gamertag = $api_player->PlayerDetail->gamertag;
                $player_info->service_tag = $api_player->PlayerDetail->service_tag;
                
                // Emblem
                $player_info->emblem["foreground_index"] = $api_player->PlayerDetail->ReachEmblem->foreground_index;
                $player_info->emblem["background_index"] = $api_player->PlayerDetail->ReachEmblem->background_index;
                $player_info->emblem["primary_armor"] = $api_player->PlayerDetail->ReachEmblem->change_colors[0];
                $player_info->emblem["secondary_armor"] = $api_player->PlayerDetail->ReachEmblem->change_colors[1];
                $player_info->emblem["primary_emblem"] = $api_player->PlayerDetail->ReachEmblem->change_colors[2];
                $player_info->emblem["secondary_emblem"] = $api_player->PlayerDetail->ReachEmblem->change_colors[3];
                $player_info->emblem["flags"] = $api_player->PlayerDetail->ReachEmblem->flags;
                
                // Recent games
                $player_info->first_active = ReachBase::parse_timestamp($api_player->PlayerDetail->first_active);
                $player_info->last_active = ReachBase::parse_timestamp($api_player->PlayerDetail->last_active);
                $player_info->last_gametype = $api_player->PlayerDetail->LastGameVariantClassPlayed;
                
                // Progress
                $player_info->total_games = $api_player->PlayerDetail->games_total;
                $player_info->campaign_progress["solo"] = $api_player->PlayerDetail->CampaignProgressSp;
                $player_info->campaign_progress["coop"] = $api_player->PlayerDetail->CampaignProgressCoop;
                $player_info->armory_progress = $api_player->PlayerDetail->armor_completion_percentage;
                $player_info->challenges_completed["weekly"] = $api_player->PlayerDetail->weekly_challenges_completed;
                $player_info->challenges_completed["daily"] = $api_player->PlayerDetail->daily_challenges_completed;
                
                // Append player info to player
                $player->info = $player_info;
                
                
                // Weapon stats
                foreach($api_player->WeaponCarnageReport as $api_weapon) {
                    // Create new weapon instance
                    $weapon = new ReachGameWeapon;
                    
                    
                    $weapon->id = $api_weapon->WeaponId;
                    $weapon->kills = $api_weapon->Kills;
                    $weapon->deaths = $api_weapon->Deaths;
                    $weapon->penalties = $api_weapon->Penalties;
                    $weapon->headshots = $api_weapon->Headshots;
                    
                    // Add to the weapon stats
                    $player->weapon_stats[$weapon->id] = $weapon;
                    
                    // Append weapon to the used weapons list
                    $player->weapons_used[] = $weapon->id;
                }
                
                
                // Campaign stats
                if ($this->variant_class == 4) {
                    foreach($api_player->AiEventAggregates as $api_enemy) {
                        // Create new enemy instance
                        $enemy = new ReachGameEnemy;
                        
                        // Enemy info
                        $enemy->enemy_id = $api_enemy->Key;
                        $enemy->enemy_type = $api_enemy->Value->aiTypeClass;
                        
                        // Points/penalties
                        $enemy->points = $api_enemy->Value->Points;
                        $enemy->penalties = $api_enemy->Value->PenaltyPoints;
                        
                        // Kill stats
                        $enemy->kills = $api_enemy->Value->PlayerKilledAiCount;
                        $enemy->kills_over_time = $api_enemy->Value->PlayerKilledAiTimeIndexes;
                        $enemy->kills_per_hour = $api_enemy->Value->PlayerKilledAiPerHour;
                        $enemy->kills_by_distance = $api_enemy->Value->PlayerKilledAiDistancesInMeters;
                        $enemy->average_kill_distance = $api_enemy->Value->PlayerKilledAiAverageDistanceInMeters;
                        
                        // Death stats
                        $enemy->deaths = $api_enemy->Value->PlayerKilledByAiCount;
                        $enemy->deaths_over_time = $api_enemy->Value->PlayerKilledByAiTimeIndexes;
                        $enemy->deaths_by_distance = $api_enemy->Value->PlayerKilledByAiDistancesInMeters;
                        $enemy->average_death_distance = $api_enemy->Value->PlayerKilledByAiAverageDistanceInMeters;
                        
                        // Betrayals
                        $enemy->betrayals = $api_enemy->Value->PlayerBetrayedAiCount;
                        
                        // Add enemy to the by enemy stats
                        $player->by_enemy[$enemy->enemy_id] = $enemy;
                    }
                }
                
                
                // Sort stats
                ksort($player->medals, SORT_NUMERIC);
                ksort($player->weapon_stats, SORT_NUMERIC);
                sort($player->weapons_used, SORT_NUMERIC);
                
                // Add player to list of players
                $this->players[$player->id] = $player;
            }
        }
        
        
        // Teams
        $this->team_game = $this->json_data->GameDetails->IsTeamGame;
        if ($this->team_game) {
            foreach($this->json_data->GameDetails->Teams as $api_team) {
                if ($api_team->Exists) {
                    // Create new team instance
                    $team = new ReachGameTeam;
                    
                    // Set the properties
                    $team->id = $api_team->Index;
                    $team->exists = $api_team->Exists;
                    $team->standing = $api_team->Standing;
                    $team->score = $api_team->Score;
                    $team->metagame_score =  $api_team->MetagameScore;
                    
                    // Kill/Death Ratio
                    $team->kill_death_ratio = $api_team->TeamTotalKills - $api_team->TeamTotalDeaths;
                    
                    // Kills, Deaths, Medals, etc.
                    $team->kills = $api_team->TeamTotalKills;
                    $team->kills_over_time = $api_team->KillsOverTime;
                    $team->deaths = $api_team->TeamTotalDeaths;
                    $team->deaths_over_time = $api_team->DeathsOverTime;
                    $team->betrayals = $api_team->TeamTotalBetrayals;
                    $team->suicides = $api_team->TeamTotalSuicides;
                    $team->assists = $api_team->TeamTotalAssists;
                    $team->medal_count = $api_team->TeamTotalMedals;
                    $team->medals_over_time = $api_team->MedalsOverTime;
                    
                    // Custom Stats
                    $team->custom_stat[1] = $api_team->TeamTotalGameVariantCustomStat_1;
                    $team->custom_stat[2] = $api_team->TeamTotalGameVariantCustomStat_2;
                    $team->custom_stat[3] = $api_team->TeamTotalGameVariantCustomStat_3;
                    $team->custom_stat[4] = $api_team->TeamTotalGameVariantCustomStat_4;
                    
                    // Add the team to the list of teams
                    $this->teams[$team->id] = $team;
                    
                    // Add to the team count
                    $this->team_count++;
                }
            }
        }
        
        
        // Campaign
        if ($this->variant_class == 4) {
            // Create new campaign instance
            $campaign = new ReachGameCampaign;
            
            // General stats
            $campaign->score = $this->json_data->GameDetails->CampaignGlobalScore;
            $campaign->scoring_enabled = $this->json_data->GameDetails->CampaignMetagameEnabled;
            $campaign->difficulty = $this->json_data->GameDetails->CampaignDifficulty;
            
            // By enemy
            foreach($this->players as $player) {
                foreach($player->by_enemy as $key => $enemy) {
                    if (array_key_exists($key, $campaign->by_enemy)) {
                        // Merge the two enemies together
                        $campaign->by_enemy[$key]->points += $enemy->points;
                        $campaign->by_enemy[$key]->penalties += $enemy->penalties;
                        
                        $campaign->by_enemy[$key]->kills += $enemy->kills;
                        $campaign->by_enemy[$key]->kills_over_time = array_merge(
                                    $campaign->by_enemy[$key]->kills_over_time,
                                    $enemy->kills_over_time);
                        $campaign->by_enemy[$key]->kills_per_hour += $enemy->kills_per_hour;
                        $campaign->by_enemy[$key]->kills_by_distance = array_merge(
                                    $campaign->by_enemy[$key]->kills_by_distance,
                                    $enemy->kills_by_distance);
                        //$campaign->by_enemy[$key]->average_kill_distance += $enemy->average_kill_distance;
                        
                        $campaign->by_enemy[$key]->deaths += $enemy->deaths;
                        $campaign->by_enemy[$key]->deaths_over_time = array_merge(
                                    $campaign->by_enemy[$key]->deaths_over_time,
                                    $enemy->deaths_over_time);
                        $campaign->by_enemy[$key]->deaths_by_distance = array_merge(
                                    $campaign->by_enemy[$key]->deaths_by_distance,
                                    $enemy->deaths_by_distance);
                        //$campaign->by_enemy[$key]->average_death_distance += $enemy->average_death_distance;
                        
                        $campaign->by_enemy[$key]->betrayals += $enemy->betrayals;
                    } else {
                        // Copy the enemy over
                        $campaign->by_enemy[$key] = $enemy;
                    }
                }
            }
            
            // Add campaign stats section
            $this->campaign = $campaign;
        }
        
        
        // Global stats
        $global_stats = new ReachGameGlobal;
        
        // Take score from teams if this was a team game
        if ($this->team_game) {
            foreach($this->teams as $team) {
                $global_stats->score += $team->score;
            }
        }
        
        // Process players
        foreach($this->players as $player) {
            // Count guests
            if ($player->guest) {
                $global_stats->guest_count++;
            }
            
            // Merge player stats into the global stats
            if (!$this->team_game) {
                $global_stats->score += $player->score;
                $global_stats->points_over_time = array_merge(
                    $global_stats->points_over_time, $player->points_over_time);
            }
            $global_stats->kills += $player->kills;
            $global_stats->kills_over_time = array_merge(
                    $global_stats->kills_over_time, $player->kills_over_time);
            $global_stats->deaths += $player->deaths;
            $global_stats->deaths_over_time = array_merge(
                    $global_stats->deaths_over_time, $player->deaths_over_time);
            $global_stats->suicides += $player->suicides;
            $global_stats->betrayals += $player->betrayals;
            $global_stats->assists += $player->assists;
            $global_stats->headshots += $player->headshots;
            
            // Merge medals
            foreach($player->medals as $key => $medal) {
                if (array_key_exists($key, $global_stats->medals)) {
                    $global_stats->medals[$key] += $medal;
                } else {
                    $global_stats->medals[$key] = $medal;
                }
            }
            $global_stats->medals_over_time = array_merge(
                    $global_stats->medals_over_time, $player->medals_over_time);
            foreach($player->medal_count as $key => $count) {
                $global_stats->medal_count[$key] += $count;
            }
            foreach($player->medal_count_unique as $key => $count) {
                $global_stats->medal_count_unique[$key] += $count;
            }
            
            // Weapons
            $global_stats->weapons_used = array_merge($global_stats->weapons_used,
                                                      $player->weapons_used);
            foreach($player->weapon_stats as $key => $weapon) {
                if (array_key_exists($key, $global_stats->weapon_stats)) {
                    // Merge the two weapons together
                    $global_stats->weapon_stats[$key]->kills += $weapon->kills;
                    $global_stats->weapon_stats[$key]->deaths += $weapon->deaths;
                    $global_stats->weapon_stats[$key]->penalties += $weapon->penalties;
                    $global_stats->weapon_stats[$key]->headshots += $weapon->headshots;
                } else {
                    // Copy the weapon over
                    $global_stats->weapon_stats[$key] = $weapon;
                }
            }
        }
        
        // Remove duplicate weapons used
        $global_stats->weapons_used = array_unique($global_stats->weapons_used);
        
        // Clean up merged stats
        ksort($global_stats->medals, SORT_NUMERIC);
        ksort($global_stats->weapon_stats, SORT_NUMERIC);
        sort($global_stats->weapons_used, SORT_NUMERIC);
        
        // Add global stats section
        $this->global_stats = $global_stats;
        
        
        // Sort stats
        ksort($this->players, SORT_NUMERIC);
        ksort($this->teams, SORT_NUMERIC);
    }
    
    // General info
    public $game_id;
    public $has_details;
    public $datetime;
    public $duration;
    
    // Map info
    public $map_name;
    public $map_base;
    public $map_hash;
    
    // Game type/Playlist
    public $variant_name;
    public $variant_class;
    public $variant_hash;
    public $playlist;
    
    // Players/Teams
    public $player_count;
    public $players = array();
    public $team_game;
    public $team_count = 0;
    public $teams = array();
    
    // Campaign/Global stats
    public $campaign;
    public $global_stats;
}

// Reach Game Details Player
class ReachGamePlayer {
    // Basic player details
    public $id;
    public $info;
    public $guest;
    public $finished;
    public $team;
    
    // Player performance
    public $standing;
    public $standing_ffa;
    public $rating;
    public $score;
    public $score_team;
    public $points_over_time;
    public $kill_death_ratio;
    
    // Kills, Deaths, Medals, etc.
    public $kills;
    public $kills_over_time = array();
    public $deaths;
    public $deaths_over_time = array();
    public $betrayals;
    public $suicides;
    public $assists;
    public $headshots;
    public $medals = array();
    public $medals_over_time = array();
    public $medal_count = array('total' => 0, 'style' => 0, 'multi' => 0,
                                'spree' => 0, 'other' => 0);
    public $medal_count_unique = array('total' => 0, 'style' => 0, 'multi' => 0,
                                       'spree' => 0, 'other' => 0);
    
    // Killed most, Killed most by, Average distances
    public $killed_most = array(0 => NULL, 1 => NULL);
    public $killed_most_by = array(0 => NULL, 1 => NULL);
    public $average_kill_distance;
    public $average_death_distance;
    
    // Weapons
    public $weapons_used = array();
    public $weapon_stats = array();
    
    // Campaign - by enemy
    public $by_enemy = array();
}

class ReachGamePlayerInfo {
    function emblem_url($size) {
        // Generate URL for the player's emblem
        list($a_pri, $a_sec, $e_pri, $e_sec, $e_design, $b_design, $e_toggle) = array(
           $this->emblem['primary_armor'],    $this->emblem['secondary_armor'],
           $this->emblem['primary_emblem'],   $this->emblem['secondary_emblem'],
           $this->emblem['foreground_index'], $this->emblem['background_index'],
           $this->emblem['flags']);
        
        // Reverse emblem toggle for emblem generator
        $e_toggle = $e_toggle? 0 : 1;

        $url = 'http://' . BUNGIE_SERVER . '/' . EMBLEM_PATH .
        "?s=$size&0=$a_pri&1=$a_sec&2=$e_pri&3=$e_sec&fi=$e_design&bi=$b_design&fl=$e_toggle&m="
         . EMBLEM_GAME_REACH;
        return $url;
    }
    
    // Basic info
    public $initialized;
    public $guest;
    public $gamertag;
    public $service_tag;
    public $emblem = array('foreground_index' => NULL, 'background_index' => NULL,
                           'primary_armor' => NULL, 'secondary_armor' => NULL,
                           'primary_emblem' => NULL, 'secondary_emblem' => NULL,
                           'flags' => NULL);
    
    // Recent games
    public $first_active;
    public $last_active;
    public $last_gametype;
    
    // Progress
    public $total_games;
    public $campaign_progress = array('solo' => NULL, 'coop' => 'NULL');
    public $armory_progress;
    public $challenges_completed = array('weekly' => 0, 'daily' => 0);
}

// Reach Game Details Team
class ReachGameTeam {
    // Basic team info
    public $id;
    public $exists;
    
    // Team performance
    public $standing;
    public $score;
    public $metagame_score;
    
    // Kills, Deaths, Medals, etc.
    public $kills;
    public $kills_over_time = array();
    public $deaths;
    public $deaths_over_time = array();
    public $betrayals;
    public $suicides;
    public $assists;
    public $medal_count;
    public $medals_over_time = array();
    public $custom_stat = array(1 => NULL, 2 => NULL, 3 => NULL, 4 => NULL);
}

// Reach Game Details Global Stats
class ReachGameGlobal {
    // Score
    public $score;
    public $points_over_time = array();
    
    // Kills, Deaths, Medals, etc.
    public $kills;
    public $kills_over_time = array();
    public $deaths;
    public $deaths_over_time = array();
    public $suicides;
    public $betrayals;
    public $assists;
    public $headshots;
    public $medals = array();
    public $medals_over_time = array();
    public $medal_count = array('total' => 0, 'style' => 0, 'multi' => 0,
                                'spree' => 0, 'other' => 0);
    public $medal_count_unique = array('total' => 0, 'style' => 0, 'multi' => 0,
                                       'spree' => 0, 'other' => 0);
    
    // Weapon stats
    public $weapon_stats = array();
    public $weapons_used = array();
}

// Reach Game Details Campaign Stats
class ReachGameCampaign {
    // Difficulty constants
    const EASY = 0;
    const NORMAL = 1;
    const HEROIC = 2;
    const LEGENDARY = 3;
    
    // Campaign stats
    public $difficulty;
    public $scoring_enabled = false;
    public $score;
    public $by_enemy = array();
}

// Reach Game Details Weapon Stats
class ReachGameWeapon {
    // Weapon stats
    public $id;
    public $kills;
    public $deaths;
    public $penalties;
    public $headshots;
}

// Reach Game Details Enemy Stats
class ReachGameEnemy {
    // Enemy info
    public $enemy_id;
    public $enemy_type;
    
    // Points/penalties
    public $points;
    public $penalties;
    
    // Kill stats
    public $kills;
    public $kills_over_time = array();
    public $kills_per_hour;
    public $kills_by_distance = array();
    public $average_kill_distance = NULL;
    
    // Deaths stats
    public $deaths;
    public $deaths_over_time = array();
    public $deaths_by_distance = array();
    public $average_death_distance = NULL;
    
    // Betrayals
    public $betrayals;
}
