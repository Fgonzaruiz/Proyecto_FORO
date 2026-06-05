<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\DirectMessageService;
use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\BusquedasRepository;

global $mybb, $db;

$uid = GameAjax::requireLogin();
$prefix = TABLE_PREFIX;

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$cid = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
if ($cid > 0) {
    $pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = {$cid} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    $staff_level = (int)($pj['staff_level'] ?? 0);
}
if ($staff_level < 2) {
    GameAjax::fail(403, 'Sin permisos');
}

GameAjax::requirePost();
GameAjax::requireCsrf($_POST);

$id = (int)($_POST['id'] ?? 0);
$accion = $_POST['accion'] ?? '';
$nota = trim($_POST['nota'] ?? '');

if ($id <= 0 || !in_array($accion, ['aprobar', 'denegar'], true)) {
    GameAjax::fail(400, 'Parámetros inválidos');
}

$new_status = $accion === 'aprobar' ? 'aprobada' : 'denegada';
$repo = new BusquedasRepository();
$repo->updateStatus($id, $new_status, $nota);

$b = $repo->findOwnerMeta($id);
$staffCharId = game_get_active_pj_id($uid);
if ($b) {
    $msg = $accion === 'aprobar'
        ? "Tu búsqueda de rol «{$b['titulo']}» ha sido aprobada y ya aparece en el tablón."
        : "Tu búsqueda de rol «{$b['titulo']}» ha sido denegada." . ($nota ? " Nota: {$nota}" : '');
    if ($staffCharId > 0 && (int)$b['character_id'] > 0) {
        try {
            DirectMessageService::send(
                $staffCharId,
                (int)$b['character_id'],
                "Búsqueda de rol: {$b['titulo']}",
                $msg
            );
        } catch (\Throwable $e) {
            game_create_notification(
                $b['user_id'],
                'dm',
                $msg,
                '',
                'game/public/buzon.php',
                (int)$b['character_id']
            );
        }
    } else {
        game_create_notification(
            $b['user_id'],
            'busqueda_respuesta',
            $msg,
            '',
            $mybb->settings['bburl'] . '/index.php',
            (int)$b['character_id']
        );
    }
}

GameAjax::json(true, ['status' => $new_status]);
