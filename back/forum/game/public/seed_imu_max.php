<?php
declare(strict_types=1);

/**
 * Demo staff: deja al personaje Imu al máximo en stats, PP, disciplinas y oficios.
 * Ejecutar una vez como admin CP. Idempotente.
 */
require_once __DIR__ . '/../bootstrap.php';

use Game\Shared\StatScale;

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    game_require_admin_cp();
}

global $db;
$prefix = TABLE_PREFIX;

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Seed Imu MAX</title>';
    echo '<style>body{font-family:system-ui;background:#0f172a;color:#f8fafc;padding:24px;max-width:720px;margin:0 auto}';
    echo '.ok{color:#10b981}.warn{color:#fbbf24}.err{color:#ef4444}.seed-link{color:#818cf8}pre{background:#1e293b;padding:16px;border-radius:8px}</style></head><body>';
    echo '<h1>Imu — showcase staff al máximo</h1><pre>';
} else {
    echo "=== Seed Imu MAX (CLI) ===\n";
}

function seed_imu_log(string $msg, string $class = 'ok'): void
{
    global $isCli;
    if ($isCli) {
        echo $msg . "\n";
        return;
    }
    echo "<span class='{$class}'>{$msg}</span>\n";
}

$imuQ = $db->query("SELECT * FROM {$prefix}game_personajes WHERE name = 'Imu' ORDER BY id ASC LIMIT 1");
$imu = $db->fetch_array($imuQ);
if (!$imu) {
    seed_imu_log('[ERROR] No se encontró el personaje «Imu». Ejecuta migrate_pj_system.php primero.', 'err');
    if (!$isCli) {
        echo '</pre></body></html>';
    }
    exit(1);
}

$imuId = (int)$imu['id'];
$userId = (int)$imu['user_id'];
seed_imu_log("[OK] Imu encontrado (id {$imuId}, user_id {$userId})");

// Asegurar tablas de disciplinas / oficios (include migraciones si faltan)
foreach (['migrate_disciplinas_system.php', 'migrate_oficios_system.php'] as $migrate) {
    $path = dirname(__DIR__) . '/sql/' . $migrate;
    if (is_file($path)) {
        ob_start();
        include $path;
        ob_end_clean();
        seed_imu_log("[OK] Migración incluida: {$migrate}");
    }
}

$maxStat = 6;
$stats = StatScale::sanitizeRanks(array_fill_keys(StatScale::STAT_KEYS, $maxStat));
$globalRank = StatScale::globalRankFromSum(StatScale::sumRanks($stats));
$nivel = StatScale::globalNivelFromRank($globalRank);

$existingData = !empty($imu['data_json']) ? json_decode($imu['data_json'], true) : [];
if (!is_array($existingData)) {
    $existingData = [];
}

$dataJson = array_merge($existingData, [
    'age' => 'Desconocida',
    'origin' => 'Mariejois',
    'pb' => 'Ninguno',
    'physique' => 'Presencia imponente e insondable. Viste ropajes formales del Gobierno Mundial que ocultan un poder que trasciende lo humano.',
    'psychology' => 'Frío, calculador y absoluto. Observa el tablero mundial desde las sombras con paciencia eterna.',
    'history' => 'Entidad suprema del Gobierno Mundial. Desde su trono en Mariejois dirige los hilos del Nuevo Mundo y del Grand Line.',
    'extras' => 'Personaje de demostración staff — stats, disciplinas y oficios al máximo para preview de UI.',
    'disciplina' => 'Armas de Filo',
    'job' => 'Médico',
    'race' => 'Humano',
    'faction' => 'Gobierno',
    'faction_rank' => 'Administrador Supremo',
    'avatar' => !empty($imu['avatar']) ? $imu['avatar'] : 'https://placehold.co/290x450',
    'rank' => $globalRank,
    'nivel' => $nivel,
    'pp' => 99999,
    'pp_linaje' => 9999,
    'pp_spent_eligible' => 0,
    'stat_points_purchased' => 42,
    'character_level' => 50,
    'linaje' => [
        'version' => 2,
        'pasivas' => [],
        'elegidos_racial' => [],
        'elegidos_general' => [],
        'maxPoints' => 20,
        'usedPoints' => 0,
        'sobrantePoints' => 20,
        'bonusPP' => 10,
        'maxSlotsRacial' => 2,
        'maxSlotsGeneral' => 2,
    ],
]);

$update = [
    'name' => $db->escape_string('Imu'),
    'race' => $db->escape_string('humano'),
    'race_name' => $db->escape_string('Humano'),
    'occupation' => $db->escape_string('medico'),
    'occupation_name' => $db->escape_string('Médico'),
    'desc' => $db->escape_string((string)$dataJson['physique']),
    'details' => $db->escape_string((string)$dataJson['psychology'] . "\n\n" . $dataJson['extras']),
    'faction' => $db->escape_string('Gobierno'),
    'rango' => $db->escape_string('Administrador Supremo'),
    'tripulacion' => $db->escape_string('Gobierno Mundial'),
    'recompensa' => $db->escape_string('99.999.999.999 Berries'),
    'banner' => $db->escape_string('images/game/personaje_banner.png'),
    'avatar' => $db->escape_string((string)$dataJson['avatar']),
    'data_json' => $db->escape_string(json_encode($dataJson, JSON_UNESCAPED_UNICODE)),
    'stats_json' => $db->escape_string(json_encode($stats, JSON_UNESCAPED_UNICODE)),
    'status' => $db->escape_string('aprobada'),
    'approved' => 1,
    'is_staff' => 1,
    'staff_level' => 3,
];

if ($db->field_exists('berries', 'game_personajes')) {
    $update['berries'] = 999999999;
}

$db->update_query('game_personajes', $update, "id = {$imuId}");
seed_imu_log("[OK] Ficha base actualizada — rango global {$globalRank}, nivel {$nivel}, stats SS×7");

$maxGrado = 5;
$discCount = 0;
if ($db->table_exists('game_disciplinas') && $db->table_exists('game_character_disciplinas')) {
    $dq = $db->query("SELECT id FROM {$prefix}game_disciplinas WHERE is_active = 1");
    while ($d = $db->fetch_array($dq)) {
        game_disciplina_set_character_rank($imuId, (int)$d['id'], $maxGrado);
        $discCount++;
    }
    seed_imu_log("[OK] {$discCount} disciplinas asignadas al grado V");
} else {
    seed_imu_log('[WARN] Tablas de disciplinas no disponibles', 'warn');
}

$ofCount = 0;
if ($db->table_exists('game_oficios') && $db->table_exists('game_character_oficios')) {
    $oq = $db->query("SELECT id FROM {$prefix}game_oficios WHERE is_active = 1");
    while ($o = $db->fetch_array($oq)) {
        game_oficio_set_character_rank($imuId, (int)$o['id'], $maxGrado);
        $ofCount++;
    }
    seed_imu_log("[OK] {$ofCount} oficios asignados al grado V");
} else {
    seed_imu_log('[WARN] Tablas de oficios no disponibles', 'warn');
}

if ($userId > 0) {
    $db->write_query("INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used, active_pj_id)
        VALUES ({$userId}, 10, 1, {$imuId})
        ON DUPLICATE KEY UPDATE active_pj_id = {$imuId}, max_slots = GREATEST(max_slots, 10)");
    seed_imu_log("[OK] Imu marcado como personaje activo del usuario {$userId} (10 slots)");
}

seed_imu_log('=== Completado ===');
if ($isCli) {
    echo "Ficha: /foro/game/public/personaje.php?pj={$imuId}\n";
} else {
    echo "\n<span class='ok'>=== Completado ===</span>\n";
    echo "Abre la ficha: <a class=\"seed-link\" href=\"../public/personaje.php?pj={$imuId}\">personaje.php?pj={$imuId}</a>\n";
    echo "\n<span class='warn'>Elimina este archivo en producción cuando no lo necesites.</span>\n";
    echo '</pre></body></html>';
}
