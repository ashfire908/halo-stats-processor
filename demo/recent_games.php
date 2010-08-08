<?php
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/shared.php');
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/../rss_parser.php');
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/../odst_parser.php');
// In case short tag is enabled
echo '<?xml version="1.0" encoding="UTF-8" ?>';?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <title>Halo SP - Recent <?php 
switch ($_GET['game']) {
    case GAME_HALO_3:
        echo 'Halo 3';
        break;
    case GAME_ODST:
        echo 'ODST';
        break;
    case GAME_REACH:
        echo 'Reach';
        break;
}?> games for <?php echo $_GET['gamertag']; ?></title>
  <link rel="stylesheet" href="demo_css.css" type="text/css" />
</head>
<body>
  <div class="header">
    <h1>Halo SP</h1>
    <p>Demonstation pages to show off the features of Halo Stats Processor</p>
  </div>
  
  <div id="data_menu">
    <h2>Recent games for <?php echo $_GET['gamertag']; ?>:</h2>
    <p>
      <a href="index.html" title="Main Page">Return to main page</a>
    </p>
  </div>
  
  <p>Processing...<?php
$gamertag = $_GET['gamertag'];
if ($gamertag == '') {
  echo 'Error: No Game ID given. Aborting...';
  trigger_error('No Game ID given', E_USER_ERROR);
}
switch ($_GET['game']) {
  case GAME_HALO_3:
    $games = halo3_rss($gamertag);
    break;
  case GAME_ODST:
    $games = odst_rss($gamertag);
    break;
  case GAME_REACH:
    echo 'Error: Halo: Reach is not supported.';
    trigger_error('Halo: Reach is not supported.', E_USER_ERROR);
    break;
}
?> Done.</p>

<?php foreach ($games as $game) {?>
  <div class="header">
    <h3>Recent Game <?php echo $game->gameid; ?></h3>
    <?php if ($game->game == GAME_ODST) { ?><p><a href="./game_stats?gameid=<?php echo $game->gameid; ?>" title="Show the data for this game">Show data for this game</a> <a href="./game_stats?gameid=<?php echo $game->gameid; ?>&use_metadata=true" title="Show the data for this game, with metadata">(with metadata)</a></p><?php } ?>
  </div>
  <dl>
    
    <dt>Game:</dt>
    <dd><?php 
switch ($game->game) {
    case GAME_HALO_3:
        echo 'Halo 3';
        break;
    case GAME_ODST:
        echo 'Halo 3: ODST';
        break;
    case GAME_REACH:
        echo 'Halo: Reach';
        break;
}?><br /></dd>
    
    <dt>Game ID:</dt>
    <dd><?php echo $game->gameid; ?><br /></dd>
    
    <?php if ($game->game == GAME_ODST) { ?><dt>Mode:</dt>
    <dd><?php echo $game->mode; ?><br /></dd>
    
    <dt>Difficulty:</dt>
    <dd><?php
switch ($game->difficulty) {
    case RecentGame::EASY:
        echo 'Easy';
        break;
    case RecentGame::NORMAL:
        echo 'Normal';
        break;
    case RecentGame::HEROIC:
        echo 'Heroic';
        break;
    case RecentGame::LEGENDARY:
        echo 'Legendary';
        break;
}?><br /></dd>

    <dt>Duration:</dt>
    <dd><?php echo tperiod($game->duration); ?><br /></dd><?php } ?>
    
    <dt>Date/time:</dt>
    <dd><?php echo $game->datetime->format('Y-m-d H:i:s');?><br /></dd>
    
    <?php if ($game->game == GAME_HALO_3) { ?><dt>Team game:</dt>
    <dd><?php echo btt($game->teams); ?><br /></dd>
    
    <dt>Playlist:</dt>
    <dd><?php echo $game->playlist; ?><br /></dd>
    
    <dt>Gametype:</dt>
    <dd><?php echo $game->gametype; ?><br /></dd><?php } ?>
    
    <dt>Map:</dt>
    <dd><?php echo $game->map; ?><br /></dd>
    
    <?php if ($game->game == GAME_ODST) { ?><dt>Players:</dt>
    <dd><?php echo $game->players; ?><br /></dd>
    
    <dt>Score:</dt>
    <dd><?php echo $game->score; ?><br /></dd>
    
<?php
if ($game->waves > 0) {
    
    list($bonus_rounds, $set_reached, $round_reached, $wave_reached) = 
    wave_position($game->waves);
?>
    <dt>Total Waves:</dt>
    <dd><?php echo $game->waves;?><br /></dd>
    
    <dt>Bonus Rounds:</dt>
    <dd><?php echo $bonus_rounds;?><br /></dd>
    
    <dt>Set Reached:</dt>
    <dd><?php echo $set_reached;?><br /></dd>
    
    <dt>Round Reached:</dt>
    <dd><?php echo $round_reached;?><br /></dd>
    
    <dt>Wave Reached:</dt>
    <dd><?php echo $wave_reached;?><br /></dd><?php
    }
  } ?>
  </dl><?php 
  if ($game->game == GAME_HALO_3) { ?>
  <table class="standings subdata" summary="Standings at the end of the Game.">
    <caption>Standings:</caption>
    <thead>
      <tr>
        <th>Gamertag</th><?php if ($game->teams === true) { ?>
        <th>Team</th><?php } ?>
        <th>Standing</th>
        <th>Score</th>
        <th>Kills</th>
        <th>Deaths</th>
        <th>Assists</th>
      </tr>
    </thead>
    <tbody><?php foreach($game->players as $player) { ?>
      <tr>
        <td><?php echo $player->gamertag; ?></td><?php if ($game->teams === true) { ?>
        <td><?php echo $player->team; ?></td><?php } ?>
        <td><?php echo $player->standing; ?></td>
        <td><?php echo $player->score; ?></td>
        <td><?php echo $player->kills; ?></td>
        <td><?php echo $player->deaths; ?></td>
        <td><?php echo $player->assists; ?></td>
      </tr>
<?php } ?>
    </tbody>
  </table>
<?php }
} ?>
</body>
</html>