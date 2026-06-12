<?php
define('GAME_DEBUG', true);
require_once __DIR__ . '/../bootstrap.php';
global $db;
$prefix = TABLE_PREFIX;
$res = $db->query("SELECT DISTINCT card_type FROM {$prefix}game_cards");
while ($row = $db->fetch_array($res)) {
    print_r($row);
}
