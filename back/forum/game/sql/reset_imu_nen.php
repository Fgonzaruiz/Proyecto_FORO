<?php
declare(strict_types=1);

/**
 * Reset Nen de Imu para repetir la prueba Mizushigure.
 * Uso: php back/forum/game/sql/reset_imu_nen.php
 */

require_once __DIR__ . '/../../global.php';
require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\NenService;

$prefix = TABLE_PREFIX;
$q = $db->query("SELECT id, name FROM {$prefix}game_personajes WHERE name = 'Imu' LIMIT 1");
$pj = $db->fetch_array($q);

if (!$pj) {
    echo "[ERROR] Personaje 'Imu' no encontrado.\n";
    exit(1);
}

$pjId = (int)$pj['id'];
$service = new NenService();
$service->resetNen($pjId);

echo "[OK] Nen eliminado para Imu (character_id={$pjId}). Puede repetir la prueba del agua.\n";
