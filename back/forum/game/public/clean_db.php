<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Check if user is logged in and is admin
global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if (!$uid) {
    http_response_code(401);
    die("<h1>No autorizado</h1><p>Debes iniciar sesión en el foro primero.</p>");
}

// Check if user is admin (group 4 is usually Admins in MyBB, or we can check $mybb->usergroup['cancp'])
if (!$mybb->usergroup['cancp'] && (int)$mybb->user['usergroup'] !== 4) {
    http_response_code(403);
    die("<h1>Acceso denegado</h1><p>Solo los administradores pueden ejecutar este script.</p>");
}

$prefix = TABLE_PREFIX;

echo "<h1>Limpieza de Base de Datos y Personajes</h1>";
echo "<pre>";

// 1. Delete mockups
$mockups = [
    'Monkey D. Luffy',
    'Roronoa Zoro',
    'Vinsmoke Sanji',
    'Nami',
    'Nico Robin',
    'Tony Tony Chopper',
    'Kazan'
];

$escaped_mockups = array_map(function($name) use ($db) {
    return "'" . $db->escape_string($name) . "'";
}, $mockups);

$mockups_str = implode(',', $escaped_mockups);

// Find IDs of characters to delete to clean up references
$pj_ids_q = $db->simple_select('game_personajes', 'id', "name IN ($mockups_str)");
$pj_ids = [];
while ($pj = $db->fetch_array($pj_ids_q)) {
    $pj_ids[] = (int)$pj['id'];
}

if (!empty($pj_ids)) {
    $pj_ids_str = implode(',', $pj_ids);
    
    // Delete from game_personajes
    $db->write_query("DELETE FROM {$prefix}game_personajes WHERE id IN ($pj_ids_str)");
    echo "Eliminados personajes mockups: " . count($pj_ids) . " filas.\n";
    
    // Delete references in other tables
    $db->write_query("DELETE FROM {$prefix}game_character_cards WHERE character_id IN ($pj_ids_str)");
    $db->write_query("DELETE FROM {$prefix}game_personajes_revisiones WHERE personaje_id IN ($pj_ids_str)");
    $db->write_query("DELETE FROM {$prefix}game_post_characters WHERE character_id IN ($pj_ids_str)");
    echo "Eliminadas referencias en tablas de cartas, revisiones y posts de personaje.\n";
} else {
    echo "No se encontraron personajes mockups para eliminar.\n";
}

// 2. Approve Imu
$imu_q = $db->simple_select('game_personajes', 'id, status, approved', "name = 'Imu' LIMIT 1");
$imu = $db->fetch_array($imu_q);
if ($imu) {
    $db->write_query("UPDATE {$prefix}game_personajes SET status = 'aprobada', approved = 1, is_staff = 1, staff_level = 3 WHERE name = 'Imu'");
    echo "Personaje 'Imu' aprobado (status = 'aprobada', approved = 1, is_staff = 1, staff_level = 3).\n";
} else {
    echo "Advertencia: No se encontró el personaje 'Imu' en la base de datos.\n";
}

// 3. Recalculate slots for users
$users_q = $db->simple_select('game_user_config', 'user_id');
while ($u_cfg = $db->fetch_array($users_q)) {
    $c_uid = (int)$u_cfg['user_id'];
    
    // Count active characters
    $cnt_q = $db->query("SELECT COUNT(*) AS cnt FROM {$prefix}game_personajes WHERE user_id = {$c_uid}");
    $actual = (int)$db->fetch_field($cnt_q, 'cnt');
    
    // Get active_pj_id. If the active character is one of the deleted ones or none exists, set to the remaining one if any
    $active_pj_q = $db->simple_select('game_personajes', 'id', "user_id = {$c_uid} LIMIT 1");
    $active_pj = $db->fetch_array($active_pj_q);
    
    $active_id_val = $active_pj ? (int)$active_pj['id'] : "NULL";
    
    $db->write_query("UPDATE {$prefix}game_user_config SET slots_used = {$actual}, active_pj_id = {$active_id_val} WHERE user_id = {$c_uid}");
    echo "Actualizada configuración de slots para usuario ID {$c_uid}: {$actual} personajes activos, Personaje activo ID: " . ($active_pj ? $active_pj['id'] : 'NULL') . ".\n";
}

echo "\n¡Limpieza completada con éxito!</pre>";
echo "<p style='color: green; font-weight: bold;'>Por favor, elimina este archivo (game/public/clean_db.php) de tu servidor por motivos de seguridad.</p>";
