<?php
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="game_parser.php"');
readfile(dirname($_SERVER['SCRIPT_FILENAME']) . '/game_parser.php');
