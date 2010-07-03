<?php
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/shared.php');
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/../rss_parser.php');
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/../odst_parser.php');
// In case short tag is enabled
echo '<?xml version="1.0" encoding="UTF-8" ?>';?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <title>ODST Game Data - Recent games for <?php echo $_GET['gamertag']; ?></title>
  <link rel="stylesheet" href="demo_css.css" type="text/css" />
</head>
<body>
  <div class="header">
    <h1>ODST Game Data</h1>
    <p>Demonstation pages to show off the features of ODST GSP</p>
  </div>
  
  <div id="data_menu">
    <h2>Recent games for <?php echo $_GET['gamertag']; ?>:</h2>
    <p>
      <a href="index.html">Request data on another person</a>
    </p>
  </div>
  
  <p>Processing...<?php
$gamertag = $_GET['gamertag'];
if ($gamertag == '') {
  print "Error: No Game ID given. Aborting...";
  trigger_error("No Game ID given", E_USER_ERROR);
}
$games = odst_rss($gamertag);
?> Done.</p>

<?php foreach ($games as $game) {?>
  <div class="header">
    <h3>Recent Game <?php echo $game->gameid; ?></h3>
    <p><a href="./game_data?gameid=<?php echo $game->gameid; ?>" title="Show the data for this game">Show data for this game</a> <a href="./game_data?gameid=<?php echo $game->gameid; ?>&use_metadata=true" title="Show the data for this game, with metadata">(with metadata)</a></p>
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
    
    <dt>Mode:</dt>
    <dd><?php echo $game->mode; ?><br /></dd>
    
    <dt>Difficulty:</dt>
    <dd><?php
switch ($game->difficulty) {
    case RSSGame::EASY:
        echo 'Easy';
        break;
    case RSSGame::NORMAL:
        echo 'Normal';
        break;
    case RSSGame::HEROIC:
        echo 'Heroic';
        break;
    case RSSGame::LEGENDARY:
        echo 'Legendary';
        break;
}?><br /></dd>

    <dt>Duration:</dt>
    <dd><?php echo tperiod($game->duration); ?><br /></dd>
    
    <dt>Date/time:</dt>
    <dd><?php echo $game->datetime->format('Y-m-d H:i:s');?><br /></dd>
    
    <dt>Map:</dt>
    <dd><?php echo $game->map; ?><br /></dd>
    
    <dt>Players:</dt>
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
} ?>
  </dl><?php 
} ?>
</body>
</html>