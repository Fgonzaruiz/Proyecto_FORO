<?php
require_once __DIR__ . '/../back/forum/bootstrap.php';
global $db;
$q = $db->query("SELECT title FROM " . TABLE_PREFIX . "templates WHERE template LIKE '%my_personajes.php%'");
while ($row = $db->fetch_array($q)) {
    echo $row['title'] . "\n";
}
