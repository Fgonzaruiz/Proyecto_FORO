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

$prefix = TABLE_PREFIX;
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    $staff_level = (int)($pj['staff_level'] ?? 0);
}

if ($staff_level < 2) {
    GameAjax::fail(403, 'Sin permisos');
}

$id = (int)($_POST['id'] ?? 0);
$accion = trim((string)($_POST['accion'] ?? ''));
$nota = trim((string)($_POST['nota'] ?? ''));

if ($id <= 0 || !in_array($accion, ['aprobar', 'denegar'], true)) {
    GameAjax::fail(400, 'Parámetros inválidos');
}

try {
    $result = AdminRequestService::resolve($id, $uid, $active_pj_id, $accion, $nota);
} catch (\Throwable $e) {
    GameAjax::fail(400, $e->getMessage());
}

GameAjax::json(true, $result);
