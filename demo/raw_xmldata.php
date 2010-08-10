<?php
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/shared.php');
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/../odst_parser.php');
// Check if we were told what mode
if ($_GET['mode'] == '') {
    echo "Error: No mode given.";
    trigger_error("No mode given", E_USER_ERROR);
}
switch ($_GET['mode']) {
    case 'odst_game':
        // Check for a Game ID
        if ($_GET['gameid'] == '') {
            echo "Error: No Game ID given. Aborting...";
            trigger_error("No Game ID given", E_USER_ERROR);
        }
        $gameid = $_GET['gameid'];
        
        // Set the HTTP headers
        header('Content-Type: text/xml; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"game_${gameid}.xml\"");
        // Download and return the game XML
        $odst_game = new ODSTGame;
        $odst_game->get_game($gameid);
        echo $odst_game->dump_xml();
        break;
    case 'odst_metadata':
        // Set the HTTP headers
        header('Content-Type: text/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="game_metadata.xml"');
        // Download and return the metadata XML
        $metadata = new ODSTMetadata;
        $metadata->get_metadata();
        echo $metadata->dump_xml();
        break;
    default:
        // Given nothing to do.
    	echo 'No operation selected.';
    	break;
}

