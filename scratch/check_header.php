<?php
require_once __DIR__ . '/../back/forum/bootstrap.php';
global $db;
$q = $db->query("SELECT template FROM " . TABLE_PREFIX . "templates WHERE title = 'header'");
$row = $db->fetch_array($q);
echo htmlspecialchars($row['template']);
