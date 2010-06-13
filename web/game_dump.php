<?php
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/../game_parser.php');
switch ($_GET['mode']) {
    case 'game':
        $gameid = $_GET['gameid'];
        if ($gameid == '') {
            echo "Error: No Game ID given. Aborting...";
            trigger_error("No Game ID given", E_USER_ERROR);
        }
        header('Content-Type: text/xml; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"game_${gameid}.xml\"");
        echo get_gamexml($gameid);
        break;
    case 'metadata':
        header('Content-Type: text/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="game_metadata.xml"');
        echo get_metadata();
        break;
    default:
    	echo 'No operation selected.';
    	break;
}


