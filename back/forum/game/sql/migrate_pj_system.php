<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if ((int)($mybb->user['uid'] ?? 0) === 0 || (int)($mybb->usergroup['cancp'] ?? 0) !== 1) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}

$prefix = TABLE_PREFIX;
$ok = true;

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Migración - Sistema de Personajes</title>
<style>body{font-family:system-ui;background:#0f172a;color:#f8fafc;padding:30px;max-width:600px;margin:0 auto}
h1{color:#818cf8}.ok{color:#10b981}.err{color:#ef4444}</style></head><body>
<h1>Migración: Sistema de Personajes</h1>";

try {
    $db->write_query("ALTER TABLE {$prefix}game_personajes ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL AFTER id");
    echo "<p class='ok'>[OK] Columna user_id añadida a game_personajes</p>";
} catch (Throwable $e) {
    try {
        $db->write_query("ALTER TABLE {$prefix}game_personajes ADD COLUMN user_id INT DEFAULT NULL AFTER id");
        echo "<p class='ok'>[OK] Columna user_id añadida a game_personajes</p>";
    } catch (Throwable $e2) {
        echo "<p class='err'>[WARN] user_id podría ya existir: " . htmlspecialchars($e2->getMessage()) . "</p>";
    }
}

try {
    $db->write_query("ALTER TABLE {$prefix}game_personajes ADD COLUMN IF NOT EXISTS avatar VARCHAR(500) NOT NULL DEFAULT '' AFTER banner");
    echo "<p class='ok'>[OK] Columna avatar añadida a game_personajes</p>";
} catch (Throwable $e) {
    try {
        $db->write_query("ALTER TABLE {$prefix}game_personajes ADD COLUMN avatar VARCHAR(500) NOT NULL DEFAULT '' AFTER banner");
        echo "<p class='ok'>[OK] Columna avatar añadida a game_personajes</p>";
    } catch (Throwable $e2) {
        echo "<p class='err'>[WARN] avatar podría ya existir: " . htmlspecialchars($e2->getMessage()) . "</p>";
    }
}

try {
    $db->write_query("CREATE TABLE IF NOT EXISTS {$prefix}game_user_config (
        user_id INT PRIMARY KEY,
        max_slots INT NOT NULL DEFAULT 1,
        slots_used INT NOT NULL DEFAULT 0,
        active_pj_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p class='ok'>[OK] Tabla game_user_config creada</p>";
} catch (Throwable $e) {
    echo "<p class='err'>[ERROR] " . htmlspecialchars($e->getMessage()) . "</p>";
    $ok = false;
}

try {
    $db->write_query("ALTER TABLE {$prefix}game_personajes ADD COLUMN IF NOT EXISTS is_staff TINYINT(1) NOT NULL DEFAULT 0 AFTER avatar");
    echo "<p class='ok'>[OK] Columna is_staff añadida a game_personajes</p>";
} catch (Throwable $e) {
    try {
        $db->write_query("ALTER TABLE {$prefix}game_personajes ADD COLUMN is_staff TINYINT(1) NOT NULL DEFAULT 0 AFTER avatar");
        echo "<p class='ok'>[OK] Columna is_staff añadida a game_personajes</p>";
    } catch (Throwable $e2) {
        echo "<p class='err'>[WARN] is_staff podría ya existir: " . htmlspecialchars($e2->getMessage()) . "</p>";
    }
}

$admin_uid = 1;
$check_admin = $db->query("SELECT id FROM {$prefix}game_personajes WHERE name = 'Imu' LIMIT 1");
if (!$db->num_rows($check_admin)) {
    $db->write_query("INSERT INTO {$prefix}game_personajes (user_id, name, race, race_name, occupation, occupation_name, `desc`, details, stat_fp, stat_dp, stat_rp, stat_ip, stat_vp, stat_hp, rango, tripulacion, recompensa, banner, avatar, is_staff) VALUES (
        {$admin_uid},
        'Imu',
        'humano', 'Humano',
        'gobernante', 'Gobernante Supremo',
        'Entidad suprema que gobierna desde las sombras el mundo entero.',
        'Poseedor del conocimiento absoluto y líder de los Diosas Solares.',
        200, 200, 200, 200, 200, 200,
        'Administrador',
        'Gobierno Mundial',
        '∞ Berries',
        'images/game/personaje_banner.png',
        '',
        1
    )");
    echo "<p class='ok'>[OK] Personaje 'Imu' creado para admin</p>";

    $imu_id = $db->insert_id();
    $db->write_query("INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used, active_pj_id) VALUES ({$admin_uid}, 5, 1, {$imu_id}) ON DUPLICATE KEY UPDATE active_pj_id = {$imu_id}, max_slots = 5, slots_used = 1");
    echo "<p class='ok'>[OK] Imu marcado como personaje activo del admin (5 slots)</p>";
} else {
    echo "<p class='ok'>[OK] Imu ya existe</p>";
}

if ($ok) {
    echo "<p style='color:#34d399;font-size:18px;margin-top:20px;'>&#10003; Migración completada</p>";
} else {
    echo "<p style='color:#ef4444;font-size:18px;margin-top:20px;'>&#10007; Hubo errores</p>";
}

echo "</body></html>";
