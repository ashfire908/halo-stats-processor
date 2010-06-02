<?php
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/game_parser.php');
$gameid = $_GET['gameid'];
if ($gameid == '') {
  print "Error: No Game ID given. Aborting...";
  trigger_error("No Game ID given", E_USER_ERROR);
}
header('Content-Type: text/xml; charset=utf-8');
header("Content-Disposition: attachment; filename=\"game_${gameid}.xml\"");
echo dump_data($gameid);
