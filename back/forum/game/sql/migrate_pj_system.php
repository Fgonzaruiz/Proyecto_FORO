<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if ((int)($mybb->user['uid'] ?? 0) === 0 || (int)($mybb->usergroup['cancp'] ?? 0) !== 1) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}
game_require_staff_character();

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
    $db->write_query("INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used, active_pj_id) VALUES ({$admin_uid}, 2, 1, {$imu_id}) ON DUPLICATE KEY UPDATE active_pj_id = {$imu_id}, max_slots = 2, slots_used = 1");
    echo "<p class='ok'>[OK] Imu marcado como personaje activo del admin (2 slots)</p>";
} else {
    echo "<p class='ok'>[OK] Imu ya existe</p>";
}

// Set/update avatar for Imu
$db->write_query("UPDATE {$prefix}game_personajes SET avatar = 'https://placehold.co/290x450' WHERE name = 'Imu' AND user_id = {$admin_uid}");
echo "<p class='ok'>[OK] Avatar asignado a Imu (290x450)</p>";

// --- Kazan (personaje normal del admin) ---
$check_kazan = $db->query("SELECT id FROM {$prefix}game_personajes WHERE name = 'Kazan' AND user_id = {$admin_uid} LIMIT 1");
if (!$db->num_rows($check_kazan)) {
    $db->write_query("INSERT INTO {$prefix}game_personajes (user_id, name, race, race_name, occupation, occupation_name, `desc`, details, stat_fp, stat_dp, stat_rp, stat_ip, stat_vp, stat_hp, rango, tripulacion, recompensa, banner, avatar, is_staff) VALUES (
        {$admin_uid},
        'Kazan',
        'humano', 'Humano',
        'aventurero', 'Aventurero Errante',
        'Un viajero del Grand Line en busca de libertad.',
        'Kazan recorre las islas sin rumbo fijo, siempre dispuesto a ayudar a quien lo necesite.',
        30, 25, 35, 20, 25, 10,
        'Tripulante',
        '—',
        '0 Berries',
        'images/game/personaje_banner.png',
        '',
        0
    )");
    echo "<p class='ok'>[OK] Personaje 'Kazan' creado</p>";

    // Update slots_used to 2
    $db->write_query("UPDATE {$prefix}game_user_config SET max_slots = 2, slots_used = 2 WHERE user_id = {$admin_uid}");
    echo "<p class='ok'>[OK] Slots actualizados a 2/2</p>";
} else {
    echo "<p class='ok'>[OK] Kazan ya existe</p>";
}

// Set/update avatar for Kazan
$db->write_query("UPDATE {$prefix}game_personajes SET avatar = 'https://placehold.co/290x450' WHERE name = 'Kazan' AND user_id = {$admin_uid}");
echo "<p class='ok'>[OK] Avatar asignado a Kazan (290x450)</p>";

// --- game_post_characters table ---
try {
    $db->write_query("CREATE TABLE IF NOT EXISTS {$prefix}game_post_characters (
        post_id INT PRIMARY KEY,
        thread_id INT DEFAULT NULL,
        user_id INT NOT NULL,
        character_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_thread_id (thread_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p class='ok'>[OK] Tabla game_post_characters creada</p>";
} catch (Throwable $e) {
    echo "<p class='err'>[ERROR] " . htmlspecialchars($e->getMessage()) . "</p>";
    $ok = false;
}

// --- Add thread_id column if missing ---
try {
    $db->write_query("ALTER TABLE {$prefix}game_post_characters ADD COLUMN IF NOT EXISTS thread_id INT DEFAULT NULL AFTER post_id, ADD INDEX IF NOT EXISTS idx_thread_id (thread_id)");
    echo "<p class='ok'>[OK] Columna thread_id añadida a game_post_characters</p>";
} catch (Throwable $e) {
    try {
        $db->write_query("ALTER TABLE {$prefix}game_post_characters ADD COLUMN thread_id INT DEFAULT NULL AFTER post_id");
        echo "<p class='ok'>[OK] Columna thread_id añadida a game_post_characters</p>";
    } catch (Throwable $e2) {
        echo "<p class='err'>[WARN] thread_id podría ya existir: " . htmlspecialchars($e2->getMessage()) . "</p>";
    }
}

// --- Add postnum and threadnum columns to game_personajes ---
try {
    $db->write_query("ALTER TABLE {$prefix}game_personajes ADD COLUMN IF NOT EXISTS postnum INT NOT NULL DEFAULT 0, ADD COLUMN IF NOT EXISTS threadnum INT NOT NULL DEFAULT 0");
    echo "<p class='ok'>[OK] Columnas postnum y threadnum añadidas a game_personajes</p>";
} catch (Throwable $e) {
    try {
        $db->write_query("ALTER TABLE {$prefix}game_personajes ADD COLUMN postnum INT NOT NULL DEFAULT 0, ADD COLUMN threadnum INT NOT NULL DEFAULT 0");
        echo "<p class='ok'>[OK] Columnas postnum y threadnum añadidas a game_personajes</p>";
    } catch (Throwable $e2) {
        echo "<p class='err'>[WARN] postnum/threadnum podrían ya existir: " . htmlspecialchars($e2->getMessage()) . "</p>";
    }
}

// --- Recalculate character post and thread counts ---
try {
    // Reset all to 0
    $db->write_query("UPDATE {$prefix}game_personajes SET postnum = 0, threadnum = 0");
    
    // Count posts
    $post_counts = $db->query("SELECT character_id, COUNT(*) as c FROM {$prefix}game_post_characters GROUP BY character_id");
    while ($pc = $db->fetch_array($post_counts)) {
        $cid = (int)$pc['character_id'];
        $c = (int)$pc['c'];
        $db->write_query("UPDATE {$prefix}game_personajes SET postnum = {$c} WHERE id = {$cid}");
    }
    
    // Count threads
    $thread_counts = $db->query("SELECT character_id, COUNT(*) as c FROM {$prefix}game_post_characters WHERE thread_id IS NOT NULL GROUP BY character_id");
    while ($tc = $db->fetch_array($thread_counts)) {
        $cid = (int)$tc['character_id'];
        $c = (int)$tc['c'];
        $db->write_query("UPDATE {$prefix}game_personajes SET threadnum = {$c} WHERE id = {$cid}");
    }
    
    echo "<p class='ok'>[OK] Contadores de posts y temas de personajes actualizados</p>";
} catch (Throwable $e) {
    echo "<p class='err'>[ERROR] Fallo al recalcular los contadores: " . htmlspecialchars($e->getMessage()) . "</p>";
    $ok = false;
}

if ($ok) {
    echo "<p class='rpg-admin-done'>&#10003; Migración completada</p>";
} else {
    echo "<p class='rpg-admin-fail'>&#10007; Hubo errores</p>";
}

echo "</body></html>";
