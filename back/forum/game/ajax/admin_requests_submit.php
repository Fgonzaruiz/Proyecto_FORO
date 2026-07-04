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
    GameAjax::fail(503, 'Sistema de peticiones no instalado.');
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

if ($title === '') {
    GameAjax::fail(400, 'El título es obligatorio');
}
if (strlen($description) < 10) {
    GameAjax::fail(400, 'La descripción es demasiado corta (mínimo 10 caracteres)');
}

$payload = [
    'motivo' => trim((string)($_POST['motivo'] ?? '')),
    'justificacion' => trim((string)($_POST['justificacion'] ?? '')),
    'request_kind' => $requestKind,
];

$requestId = AdminRequestService::create(
    $uid,
    $characterId,
    $source,
    $requestKind,
    $title,
    $description,
    $link !== '' ? $link : null,
    $payload
);

AdminRequestService::notifyStaffPending(
    "Nueva petición administrativa: {$title}",
    '/game/public/zona_staff_peticiones.php'
);

GameAjax::json(true, ['request_id' => $requestId]);
