<?php
require_once __DIR__ . '/../back/forum/bootstrap.php';
global $db;
$q = $db->query("SELECT id, user_id, name, is_staff, staff_level FROM " . TABLE_PREFIX . "game_personajes");
$res = [];
while ($row = $db->fetch_array($q)) {
    $res[] = $row;
}
echo json_encode($res, JSON_PRETTY_PRINT);
