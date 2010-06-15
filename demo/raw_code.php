<?php
header('Content-Type: text/plain');
switch ($_GET['file']) {
    case 'parser':
        $file = '../odst_parser.php';
        break;
    case 'data':
        $file = 'game_data.php';
        break;
    default:
        $file = '../odst_parser.php';
        break;
}
header("Content-Disposition: attachment; filename=\"${file}\"");
readfile(dirname($_SERVER['SCRIPT_FILENAME']) . "/${file}");
