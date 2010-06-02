<?php
require(dirname($_SERVER['SCRIPT_FILENAME']) . '/game_parser.php');

header('Content-Type: text/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="game_metadata.xml"');
echo get_metadata();
