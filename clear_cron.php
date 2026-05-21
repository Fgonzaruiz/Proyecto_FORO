<?php
require_once __DIR__ . '/back/forum/bootstrap.php';
global $db;
$prefix = TABLE_PREFIX;
$db->write_query("UPDATE {$prefix}game_personajes SET cronologia_json = ''");
echo "Cronologia cleared.";
