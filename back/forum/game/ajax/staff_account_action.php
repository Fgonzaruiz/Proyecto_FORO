<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\StaffAccountService;
use Game\Http\GameAjax;

header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

if (game_get_active_staff_level($uid) < 3) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'Se requiere nivel de staff 3 (Administrador).'], 403);
}

$targetUid = (int)($input['target_uid'] ?? 0);
$action = trim((string)($input['action'] ?? ''));

if ($targetUid <= 0 || $action === '') {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Parámetros inválidos.'], 400);
}

$service = new StaffAccountService($db, TABLE_PREFIX, $uid);

try {
    switch ($action) {
        case 'ban':
            $service->banUser($targetUid, trim((string)($input['reason'] ?? '')));
            break;
        case 'unban':
            $service->unbanUser($targetUid);
            break;
        case 'set_narrator':
            $service->setNarrator($targetUid, !empty($input['enabled']));
            break;
        case 'set_max_slots':
            $service->setMaxSlots($targetUid, (int)($input['max_slots'] ?? 1));
            break;
        case 'adjust_slots':
            $details = $service->getAccountDetails($targetUid);
            $current = (int)$details['config']['max_slots'];
            $delta = (int)($input['delta'] ?? 0);
            $service->setMaxSlots($targetUid, $current + $delta);
            break;
        case 'save_npc_assignments':
            $ids = $input['npc_ids'] ?? [];
            if (!is_array($ids)) {
                $ids = [];
            }
            $service->saveNpcAssignments($targetUid, array_map('intval', $ids));
            break;
        case 'suspend_posting':
            $service->setSuspendPosting($targetUid, !empty($input['enabled']));
            break;
        case 'moderate_posts':
            $service->setModeratePosts($targetUid, !empty($input['enabled']));
            break;
        case 'clear_active_pj':
            $service->clearActiveCharacter($targetUid);
            break;
        case 'sync_slots':
            $service->syncSlotsUsed($targetUid);
            break;
        default:
            GameAjax::json(false, null, ['code' => 400, 'message' => 'Acción desconocida.'], 400);
    }

    $details = $service->getAccountDetails($targetUid);
    GameAjax::json(true, ['message' => 'Cambios guardados.', 'account' => $details]);
} catch (\InvalidArgumentException $e) {
    GameAjax::json(false, null, ['code' => 400, 'message' => $e->getMessage()], 400);
} catch (\RuntimeException $e) {
    GameAjax::json(false, null, ['code' => 500, 'message' => $e->getMessage()], 500);
}
