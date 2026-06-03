<?php
require_once __DIR__ . '/../back/forum/bootstrap.php';
global $db;

$template = file_get_contents(__DIR__ . '/../front/templates/mybb/global/headerinclude.html');
// Fix encoding if needed
$template = str_replace("\r\n", "\n", $template);
$template_esc = $db->escape_string($template);

$db->write_query("UPDATE " . TABLE_PREFIX . "templates SET template = '{$template_esc}' WHERE title = 'headerinclude'");
echo "OK. headerinclude template updated successfully! Please refresh the page (CTRL + F5) on the forum.";
