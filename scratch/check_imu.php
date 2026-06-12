<?php
declare(strict_types=1);

define('IN_MYBB', 1);
require_once __DIR__ . '/../back/forum/global.php';

global $db;
$prefix = TABLE_PREFIX;

// Find Imu
$q = $db->query("SELECT id, name, status, data_json FROM {$prefix}game_personajes WHERE name LIKE '%Imu%' LIMIT 1");
$imu = $db->fetch_array($q);

if (!$imu) {
    echo "Imu not found.\n";
    exit;
}

echo "Character: {$imu['name']} (ID: {$imu['id']})\n";
echo "Status: {$imu['status']}\n";
$data = json_decode($imu['data_json'], true) ?: [];
echo "Level: " . ($data['nivel'] ?? '1') . "\n";

// Check active missions
$maQ = $db->query("
    SELECT ma.*, mp.character_id, mp.confirmed 
    FROM {$prefix}game_missions_active ma
    JOIN {$prefix}game_mission_participants mp ON mp.active_mission_id = ma.id
    WHERE mp.character_id = {$imu['id']}
");
echo "\nMissions Active:\n";
while ($row = $db->fetch_array($maQ)) {
    echo "- Active ID: {$row['id']}, Mission ID: {$row['mission_id']}, Status: {$row['status']}, Confirmed: {$row['confirmed']}, Cooldown: " . ($row['cooldown_until'] ?? 'none') . "\n";
}

// Check participants
$partQ = $db->query("SELECT * FROM {$prefix}game_mission_participants WHERE character_id = {$imu['id']}");
echo "\nParticipants records:\n";
while ($row = $db->fetch_array($partQ)) {
    echo "- Active Mission ID: {$row['active_mission_id']}, Confirmed: {$row['confirmed']}, Cooldown Until: {$row['cooldown_until']}\n";
}
