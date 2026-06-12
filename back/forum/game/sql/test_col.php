<?php
define('IN_MYBB', 1);
require_once dirname(__DIR__, 2) . '/inc/init.php';
global $db;
$q = $db->query("SHOW COLUMNS FROM mybb_game_tripulaciones");
while($r = $db->fetch_array($q)) {
    print_r($r);
}
