<?php
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/shared.php');
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/../odst_parser.php');
// In case short tag is enabled
echo '<?xml version="1.0" encoding="UTF-8" ?>';?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <title>Halo SP - Results for ODST gameid <?php echo $_GET['gameid']; ?></title>
  <link rel="stylesheet" href="demo_css.css" type="text/css" />
</head>
<body>
  <div class="header">
    <h1>Halo SP</h1>
    <p>Demonstration pages to show off the features of Halo Stats Processor</p>
  </div>
  
  <div id="data_menu">
    <h2>Data for gameid <?php echo $_GET['gameid']; ?>:</h2>
    <p>
      <a href="index.html" title="Main Page">Return to main page</a>
      &middot;
      <a href="http://www.bungie.net/Stats/ODSTg.aspx?gameid=<?php echo $_GET['gameid']; ?>" title="Link to game <?php echo $_GET['gameid']; ?> on Bungie.net">Bungie.net Link</a>
      &middot;
      <a href="raw_xmldata.php?mode=odst_game&amp;gameid=<?php echo $_GET['gameid']; ?>" title="Download raw XML/SOAP">Download raw SOAP/XML for this game</a>
    </p>
  </div>
  
  <p>Processing...<code><?php
$use_metadata = false;
if ($_GET['gameid'] == '') {
  trigger_error('No Game ID given', E_USER_ERROR);
}
if (array_key_exists('use_metadata', $_GET) and $_GET['use_metadata'] == 'true') {
    if (is_readable(METADATA_FILE)) {
        $use_metadata = true;
        $metadata = unserialize(file_get_contents(METADATA_FILE));
    } else {
        trigger_error('Error, could not read the metadata file. Please <a href="metadata.php" title="Generate local metadata copy">generate the local copy</a>.', E_USER_WARNING);
    }
}
$game = new ODSTGame;
$game->get_game($_GET['gameid']);
$game->load_game();
?></code> Done.</p>

  <h3>Error</h3>
  <dl>
    <dt>Error:</dt>
    <dd><?php echo btt($game->error);?><br /></dd>
    
    <dt>Status Code:</dt>
    <dd><?php echo $game->error_details[0];?><br /></dd>
    
    <dt>Reason:</dt>
    <dd><?php echo $game->error_details[1];?><br /></dd>
  </dl>
<?php
if ($game->error === false) {
?>

  <h3>General Data</h3>
  <dl>
    <dt>Difficulty:</dt>
    <dd><?php
switch ($game->difficulty) {
  case 0:
    echo 'Easy';
    break;
  case 1:
    echo 'Normal';
    break;
  case 2:
    echo 'Heroic';
    break;
  case 3:
    echo 'Legendary';
    break;
}
?><br /></dd>
    
    <dt>Duration:</dt>
    <dd><?php echo tperiod($game->duration); ?><br /></dd>
    
    <dt>Date/Time:</dt>
    <dd><?php echo $game->datetime->format('Y-m-d H:i:s'); ?><br /></dd>
    
    <dt>Map:</dt>
    <dd><?php echo $game->map; ?><br /></dd>
    
    <dt>Scoring Enabled?</dt>
    <dd><?php echo btt($game->scoring_enabled); ?><br /></dd>
    
<?php if ($game->scoring_enabled) { ?>
    <dt>Score:</dt>
    <dd><?php echo $game->score; ?><br /></dd>
    
    <dt>Time Bonus:</dt>
    <dd><?php echo $game->time_bonus; ?><br /></dd>
<?php } ?>
    
    <dt>Firefight Game?</dt>
    <dd><?php echo btt($game->firefight); ?><br /></dd>
    
    <dt>Kills:</dt>
    <dd><?php echo $game->kills; ?><br /></dd>
    
    <dt>Deaths:</dt>
    <dd><?php echo $game->deaths; ?><br /></dd>
    
    <dt>Suicides:</dt>
    <dd><?php echo $game->suicides; ?><br /></dd>
    
    <dt>Betrayals:</dt>
    <dd><?php echo $game->betrayals; ?><br /></dd>
<?php if (! $game->firefight) { ?>
    
    <dt>Reverts:</dt>
    <dd><?php echo $game->reverts; ?><br /></dd>
<?php } ?>
  </dl>
  
  <div class="header">
    <h3>Players</h3>
    <p>Number of players: <?php echo $game->player_count; ?></p>
  </div>
<?php
foreach ($game->players as $player) {
?>    <div class="subdata">
      <h4>Player ID <?php echo $player->id; ?></h4>
      <dl>
        <dt>Ghost</dt>
        <dd><?php echo btt($player->ghost); ?><br /></dd>
<?php if ($player->ghost === false) { ?>

        <dt>Emblem</dt>
        <dd>
          <a href="<?php echo $player->emblem_url(100); ?>" title="Player <?php echo $player->id; ?>'s Emblem">
            <img src="<?php echo $player->emblem_url(100); ?>" height="100" width="100" alt="Player <?php echo $player->id; ?>'s Emblem" />
          </a>
          <br />
        </dd>
        
        <dt>Gamertag</dt>
        <dd>
          <?php echo $player->gamertag; ?>
          <sup><a href="recent_games.php?gamertag=<?php echo rawurlencode($player->gamertag); ?>&amp;game=<?php echo GAME_ODST; ?>" title="Recent ODST games for <?php echo $player->gamertag; ?>">View Recent ODST Games</a></sup>
          <br />
        </dd>
  
        <dt>Service Tag</dt>
        <dd><?php echo $player->service_tag; ?><br /></dd>
        
        <dt>ArmorFlags</dt>
        <dd><?php echo implode(', ', $player->armor_flags); ?><br /></dd>
  
        <dt>ArmorType</dt>
        <dd><?php echo $player->armor_type; ?><br /></dd>
    
        <dt>EmblemColors</dt>
        <dd><?php echo implode(', ', $player->emblem_colors); ?><br /></dd>
  
        <dt>EmblemFlags</dt>
        <dd><?php echo implode(', ', $player->emblem_flags); ?><br /></dd>
<?php } ?>
  
        <dt>Score</dt>
        <dd><?php echo $player->score; ?><br /></dd>
  
        <dt>Kills</dt>
        <dd><?php echo $player->kills; ?><br /></dd>
    
        <dt>Deaths</dt>
        <dd><?php echo $player->deaths; ?><br /></dd>
  
        <dt>Suicides</dt>
        <dd><?php echo $player->suicides; ?><br /></dd>
    
        <dt>Betrayals</dt>
        <dd><?php echo $player->betrayals; ?><br /></dd>
  
        <dt>Weapons Used</dt>
        <dd>
          <ul>
<?php
foreach ($player->weapons_used as $weapon) {
    if ($use_metadata) {
        $weapon = $metadata->weapons[$weapon]->display_name;
    }
    echo '            <li>' . $weapon . '</li>
';
}
?>
          </ul>
        </dd>
        
        <dt>Medals</dt>
        <dd>
          <span class="subtitle">Total Medals: <?php echo $player->medal_count; ?></span>
          <dl>
<?php foreach ($player->medals as $medal => $count) {
    if ($use_metadata) {
        $medal = $metadata->medals[$medal]->display_name;
    }
?>
            <dt><?php echo $medal; ?></dt>
            <dd><?php echo $count; ?><br /></dd>
<?php }
?>
          </dl>
        </dd>
      </dl>
    </div>
<?php } ?>

  <h3>Initial Skulls</h3><?php 
if ($use_metadata) { ?>
    <div class="skull_display">
<?php
    foreach ($metadata->skulls as $skull) {
        if (in_array($skull->id, $game->skulls_primary_start) or in_array($skull->id, $game->skulls_secondary_start)) {
            $url = $skull->image_url(true);
            $title = ' (On)';
        } else {
            $url = $skull->image_url(false);
            $title = ' (Off)';
        }
?>      <img src="<?php echo $url; ?>" title="<?php echo $skull->display_name . $title; ?>" class="skull_<?php echo $skull->order + 1; ?>" />
<?php
    }
}
?>    
    </div>
    <div class="subdata">
    <h4>Primary Skulls:</h4>
      <ul>
<?php
foreach ($game->skulls_primary_start as $skull) {
    if ($use_metadata) {
        $skull = $metadata->skulls[$skull]->display_name;
    }
    echo '        <li>' . $skull . '</li>
';
}
?>
      </ul>
    <h4>Secondary Skulls:</h4>
      <ul>
<?php
foreach ($game->skulls_secondary_start as $skull) {
    if ($use_metadata) {
        $skull = $metadata->skulls[$skull]->display_name;
    }
    echo '        <li>' . $skull . '</li>
';
}
?>
      </ul>
    </div>
<?php if ($game->firefight == true) { ?>

  <h3>Waves</h3>
    <dl>
      <dt>Total Waves:</dt>
      <dd><?php echo $game->total_waves; ?><br /></dd>
      
      <dt>Bonus Rounds:</dt>
      <dd><?php echo $game->bonus_rounds; ?><br /></dd>
      
      <dt>Set Reached:</dt>
      <dd><?php echo $game->set_reached; ?><br /></dd>
      
      <dt>Round Reached:</dt>
      <dd><?php echo $game->round_reached; ?><br /></dd>
        
      <dt>Wave Reached:</dt>
      <dd><?php echo $game->wave_reached; ?><br /></dd>
    </dl>

  <h3>Wave Stats</h3>
<?php
foreach ($game->wave_stats as $wave) {
?>
    <div class="subdata">
      <h4>Wave <?php echo $wave->id; ?></h4>
      <dl>
        <dt>Activity?</dt>
        <dd><?php echo btt($wave->activity); ?><br /></dd>
        
        <dt>Wave Length:</dt>
        <dd><?php echo tperiod($wave->length); ?><br /></dd>
        
        <dt>Wave Start:</dt>
        <dd><?php echo tperiod($wave->start); ?><br /></dd>
          
        <dt>Wave End:</dt>
        <dd><?php echo tperiod($wave->end); ?><br /></dd>
          
        <dt>Score:</dt>
        <dd><?php echo $wave->score; ?><br /></dd>
          
        <dt>Kills:</dt>
        <dd><?php echo $wave->kills; ?><br /></dd>
          
        <dt>Deaths:</dt>
        <dd><?php echo $wave->deaths; ?><br /></dd>
          
        <dt>Suicides:</dt>
        <dd><?php echo $wave->suicides; ?><br /></dd>
          
        <dt>Betrayals:</dt>
        <dd><?php echo $wave->betrayals; ?><br /></dd>
        
        <dt>Weapons Used</dt>
        <dd>
          <ul>
<?php
foreach ($wave->weapons_used as $weapon) {
    if ($use_metadata) {
        $weapon = $metadata->weapons[$weapon]->display_name;
    }
    echo '            <li>' . $weapon . '</li>
';
}
?>
          </ul>
        </dd>
        
        <dt>Medals</dt>
        <dd>
          <dl>
<?php foreach ($wave->medals as $medal => $count) {
    if ($use_metadata) {
        $medal = $metadata->medals[$medal]->display_name;
    }
?>
            <dt><?php echo $medal; ?></dt>
            <dd><?php echo $count; ?><br /></dd>
<?php } 
?>
          </dl>
        </dd>
      </dl>
    </div>

<?php
  }
}
?>    
    <h3>Weapons Used:</h3>
      <ul>
<?php
foreach ($game->weapons_used as $weapon) {
    if ($use_metadata) {
        $weapon = $metadata->weapons[$weapon]->display_name;
    }
    echo '        <li>' . $weapon . '</li>
';
}
?>
      </ul>
    <div class="header">
      <h3>Medals:</h3>
      <p>Total medals: <?php echo $game->medal_count; ?></p>
    </div>
      <dl>
<?php foreach ($game->medals as $medal => $count) {
    if ($use_metadata) {
        $medal = $metadata->medals[$medal]->display_name;
    }
?>
        <dt><?php echo $medal; ?></dt>
        <dd><?php echo $count; ?><br /></dd>
<?php } 
?>
      </dl>
<?php
}
?>
</body>
</html>
