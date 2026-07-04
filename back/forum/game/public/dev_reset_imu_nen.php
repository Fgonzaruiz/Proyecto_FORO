<?php
declare(strict_types=1);

/**
 * Reset Nen de Imu (solo entorno local / staff admin).
 * Visitar una vez: /game/public/dev_reset_imu_nen.php
 */

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\NenService;

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    header('Location: ' . $mybb->settings['bburl'] . '/member.php?action=login');
    exit;
}

$staffLevel = game_get_active_staff_level($uid);
if ($staffLevel < 3) {
    http_response_code(403);
    echo 'Solo administradores (staff_level >= 3).';
    exit;
}

$prefix = TABLE_PREFIX;
$q = $db->query("SELECT id, name FROM {$prefix}game_personajes WHERE name = 'Imu' LIMIT 1");
$pj = $db->fetch_array($q);

header('Content-Type: text/plain; charset=utf-8');

if (!$pj) {
    echo "Personaje Imu no encontrado.\n";
    exit;
}

$pjId = (int)$pj['id'];
$service = new NenService();
$service->resetNen($pjId);

echo "OK — Nen eliminado para Imu (character_id={$pjId}).\n";
echo "Ve a /game/public/nen.php y repite la Prueba del Agua.\n";
