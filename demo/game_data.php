<?php
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/../odst_parser.php');
function btt($var) {
  if ($var === true) {
    return 'Yes';
  } elseif ($var === false) {
    return 'No';
  }
}
// In case short tag is enabled
echo '<?xml version="1.0" encoding="UTF-8" ?>';?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <title>ODST Game Data - Results for gameid <?php echo $_GET['gameid']; ?></title>
  <link rel="stylesheet" href="odst_data.css" type="text/css" />
</head>
<body>
  <div class="header">
    <h1>ODST Game Data</h1>
    <p>Quick-and-Dirty Page to test parts of the parser</p>
  </div>
  
  <div id="data_menu">
    <h2>Data for gameid <?php echo $_GET['gameid']; ?>:</h2>
    <p>
      <a href="http://www.bungie.net/Stats/ODSTg.aspx?gameid=<?php echo $_GET['gameid']; ?>" title="Link to game <?php echo $_GET['gameid']; ?> on Bungie.net">Bungie.net Link</a>
      &middot;
      <a href="game_request.html">Request data on another game</a>
      &middot;
      <a href="raw_xmldata.php?mode=game&amp;gameid=<?php echo $_GET['gameid']; ?>">Get raw SOAP/XML data</a>
    </p>
  </div>
  
  <p>Processing...<?php
$gamenum = $_GET['gameid'];
if ($gamenum == '') {
  print "Error: No Game ID given. Aborting...";
  trigger_error("No Game ID given", E_USER_ERROR);
}
$game = new ODSTGame;
$game->get_game($gamenum);
$game->load_game();
?> Done.</p>

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
    
    <dt>Duration (in seconds):</dt>
    <dd><?php echo $game->duration;?><br /></dd>
    
    <dt>Date/Time:</dt>
    <dd><?php echo $game->datetime->format('Y-m-d H:i:s');?><br /></dd>
    
    <dt>Map:</dt>
    <dd><?php echo $game->map;?><br /></dd>
    
    <dt>Scoring Enabled?</dt>
    <dd><?php echo btt($game->scoring_enabled);?><br /></dd>
    
<?php if ($game->scoring_enabled) { ?>
    <dt>Score:</dt>
    <dd><?php echo $game->score;?><br /></dd>
    
    <dt>Time Bonus:</dt>
    <dd><?php echo $game->time_bonus;?><br /></dd>
<?php } ?>
    
    <dt>Firefight Game?</dt>
    <dd><?php echo btt($game->firefight);?><br /></dd>
  </dl>
  
  <div class="header">
    <h3>Players</h3>
    <p>Number of players: <?php echo $game->player_count;?></p>
  </div>
<?php
foreach ($game->players as $player) {
?>    <div class="subdata">
      <h4>Player ID <?php echo $player->id;?></h4>
      <dl>
        <dt>Gamertag</dt>
        <dd><?php echo $player->gamertag;?><br /></dd>
  
        <dt>Service Tag</dt>
        <dd><?php echo $player->service_tag;?><br /></dd>
  
        <dt>Score</dt>
        <dd><?php echo $player->score;?><br /></dd>
  
        <dt>Kills</dt>
        <dd><?php echo $player->kills;?><br /></dd>
    
        <dt>Deaths</dt>
        <dd><?php echo $player->deaths;?><br /></dd>
  
        <dt>Suicides</dt>
        <dd><?php echo $player->suicides;?><br /></dd>
    
        <dt>Betrayals</dt>
        <dd><?php echo $player->betrayals;?><br /></dd>
  
        <dt>Weapons Used</dt>
        <dd>
          <ul>
<?php
foreach ($player->weapons_used as $weapon) {
  echo '            <li>' . $weapon . '</li>
';
}
?>
          </ul>
        </dd>
        
        <dt>Medals</dt>
        <dd>
          <dl>
<?php foreach ($player->medals as $medal => $count) {
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

  <h3>Initial Skulls</h3>
    <div class="subdata">
    <h4>Primary Skulls:</h4>
      <ul>
<?php
foreach ($game->skulls_primary_start as $skull) {
  echo '        <li>' . $skull . '</li>
';
}
?>
      </ul>
    <h4>Secondary Skulls:</h4>
      <ul>
<?php
foreach ($game->skulls_secondary_start as $skull) {
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
      <dd><?php echo $game->total_waves;?><br /></dd>
      
      <dt>Bonus Rounds:</dt>
      <dd><?php echo $game->bonus_rounds;?><br /></dd>
      
      <dt>Set Reached:</dt>
      <dd><?php echo $game->set_reached;?><br /></dd>
      
      <dt>Round Reached:</dt>
      <dd><?php echo $game->round_reached;?><br /></dd>
        
      <dt>Wave Reached:</dt>
      <dd><?php echo $game->wave_reached;?><br /></dd>
    </dl>

  <h3>Wave Stats</h3>
<?php
foreach ($game->wave_stats as $wave) {
?>
    <div class="subdata">
      <h4>Wave <?php echo $wave->id;?></h4>
      <dl>
        <dt>Activity?</dt>
        <dd><?php echo btt($wave->activity);?><br /></dd>
        
        <dt>Wave Length:</dt>
        <dd><?php echo $wave->length;?><br /></dd>
        
        <dt>Wave Start:</dt>
        <dd><?php echo $wave->start;?><br /></dd>
          
        <dt>Wave End:</dt>
        <dd><?php echo $wave->end;?><br /></dd>
          
        <dt>Score:</dt>
        <dd><?php echo $wave->score;?><br /></dd>
          
        <dt>Kills:</dt>
        <dd><?php echo $wave->kills;?><br /></dd>
          
        <dt>Deaths:</dt>
        <dd><?php echo $wave->deaths;?><br /></dd>
          
        <dt>Sucides:</dt>
        <dd><?php echo $wave->suicides;?><br /></dd>
          
        <dt>Betrayals:</dt>
        <dd><?php echo $wave->betrayals;?><br /></dd>
        
        <dt>Weapons Used</dt>
        <dd>
          <ul>
<?php
foreach ($wave->weapons_used as $weapon) {
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
  echo '        <li>' . $weapon . '</li>
';
}
?>
      </ul>

    <h3>Medals:</h3>
      <dl>
<?php foreach ($game->medals as $medal => $count) {
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
