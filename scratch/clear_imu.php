<?php
define('IN_MYBB', 1);
require_once __DIR__ . '/../back/forum/global.php';

global $db;
$prefix = TABLE_PREFIX;

$characterId = 1; // Imu's ID

// Clear cooldown
$db->query("UPDATE {$prefix}game_mission_participants SET cooldown_until = '2026-06-01 00:00:00' WHERE character_id = {$characterId}");

// Also check if there are any active missions in pending/active/review state and force complete/delete them if necessary
$db->query("UPDATE {$prefix}game_missions_active SET status = 'completed' WHERE id IN (
    SELECT active_mission_id FROM {$prefix}game_mission_participants WHERE character_id = {$characterId}
)");

echo "Cooldown cleared and missions set to completed for character ID {$characterId} (Imu).\n";
