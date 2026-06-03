<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/autoload.php';

use Game\Application\Services\AdminRequestService;
use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
GameAjax::requireCsrf($_POST);

if (!$db->table_exists('game_admin_requests')) {
    GameAjax::fail(503, 'Sistema de peticiones no instalado. Ejecuta migrate_akuma_peticiones.php');
}

try {
    $characterId = AdminRequestService::requireActiveCharacter($uid);
} catch (\RuntimeException $e) {
    GameAjax::fail(400, $e->getMessage());
}

$source = trim((string)($_POST['source'] ?? 'manual'));
$requestKind = trim((string)($_POST['request_kind'] ?? 'otro'));
$title = trim((string)($_POST['title'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$link = trim((string)($_POST['link'] ?? ''));
$akumaFruitId = (int)($_POST['akuma_fruit_id'] ?? 0);
$motivo = trim((string)($_POST['motivo'] ?? ''));
$justificacion = trim((string)($_POST['justificacion'] ?? ''));

if ($title === '' && $requestKind === 'fruta_diablo' && $source === 'akuma_demand') {
    $prefix = TABLE_PREFIX;
    if ($akumaFruitId > 0) {
        $f_q = $db->query("SELECT name FROM {$prefix}game_akuma_no_mi WHERE id = {$akumaFruitId} LIMIT 1");
        $f = $db->fetch_array($f_q);
        if ($f) {
            $title = 'Akuma bajo demanda: ' . $f['name'];
        }
    }
}

if ($title === '') {
    GameAjax::fail(400, 'El título es obligatorio');
}
if (strlen($description) < 10 && $source !== 'akuma_demand') {
    GameAjax::fail(400, 'La descripción es demasiado corta (mínimo 10 caracteres)');
}

if ($source === 'akuma_demand') {
    if ($akumaFruitId <= 0) {
        GameAjax::fail(400, 'Debes seleccionar una Akuma no Mi');
    }
    $prefix = TABLE_PREFIX;
    $f_q = $db->query("SELECT id, name, is_occupied, status FROM {$prefix}game_akuma_no_mi WHERE id = {$akumaFruitId} LIMIT 1");
    $fruit = $db->fetch_array($f_q);
    if (!$fruit) {
        GameAjax::fail(404, 'Fruta no encontrada');
    }
    try {
        AdminRequestService::reserveAkumaFruit($akumaFruitId);
    } catch (\RuntimeException $e) {
        GameAjax::fail(409, $e->getMessage());
    }

    $description = "Petición bajo demanda.\n\nMotivo: {$motivo}\n\nJustificación: {$justificacion}";
    if (strlen(trim($description)) < 10) {
        GameAjax::fail(400, 'Completa motivo y justificación');
    }
    $requestKind = 'fruta_diablo';
}

$payload = [
    'motivo' => $motivo,
    'justificacion' => $justificacion,
    'request_kind' => $requestKind,
];
if ($akumaFruitId > 0) {
    $payload['akuma_fruit_id'] = $akumaFruitId;
}

$requestId = AdminRequestService::create(
    $uid,
    $characterId,
    $source,
    $requestKind,
    $title,
    $description,
    $link !== '' ? $link : null,
    $payload,
    $akumaFruitId > 0 ? $akumaFruitId : null
);

AdminRequestService::notifyStaffPending(
    "Nueva petición administrativa: {$title}",
    '/game/public/zona_staff_peticiones.php'
);

GameAjax::json(true, ['request_id' => $requestId]);
