<?php
require_once __DIR__ . '/../back/forum/bootstrap.php';
global $db;
$q = $db->query("SELECT template FROM " . TABLE_PREFIX . "templates WHERE title = 'header_welcomeblock_member'");
while ($row = $db->fetch_array($q)) {
    echo "=== TEMPLATE IN DB ===\n";
    echo $row['template'] . "\n";
    echo "======================\n";
}
