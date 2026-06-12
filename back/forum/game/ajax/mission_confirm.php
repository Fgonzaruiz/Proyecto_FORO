<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\PersonajeRepository;

header('Content-Type: application/json; charset=utf-8');

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$characterId = (int)($input['character_id'] ?? 0);
$activeMissionId = (int)($input['active_mission_id'] ?? 0);
$action = trim((string)($input['action'] ?? ''));

if ($characterId <= 0 || $activeMissionId <= 0 || !in_array($action, ['accept', 'decline'], true)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Parámetros inválidos.'], 400);
}

if (game_get_active_pj_id($uid) !== $characterId) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'Debes usar tu personaje activo.'], 403);
}

$prefix = TABLE_PREFIX;

// Fetch participant row
$pq = $db->query("
    SELECT * FROM {$prefix}game_mission_participants 
    WHERE active_mission_id = {$activeMissionId} AND character_id = {$characterId} LIMIT 1
");
$part = $db->fetch_array($pq);

if (!$part) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'No estás invitado a esta misión.'], 404);
}

if ((int)$part['confirmed'] !== 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Ya has respondido a esta invitación.'], 400);
}

// Fetch active mission details
$amQ = $db->query("
    SELECT ma.*, m.title, m.points_reward 
    FROM {$prefix}game_missions_active ma
    JOIN {$prefix}game_missions m ON ma.mission_id = m.id
    WHERE ma.id = {$activeMissionId} LIMIT 1
");
$activeMission = $db->fetch_array($amQ);

if (!$activeMission || !in_array($activeMission['status'], ['pending', 'active'])) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'La misión no está en un estado válido.'], 400);
}

// Fetch leader info
$leaderQ = $db->query("SELECT user_id, name FROM {$prefix}game_personajes WHERE id = " . (int)$activeMission['leader_character_id'] . " LIMIT 1");
$leader = $db->fetch_array($leaderQ);
$leaderUid = $leader ? (int)$leader['user_id'] : 0;
$leaderName = $leader ? $leader['name'] : 'Líder';

// Fetch current companion's name
$pjNameQ = $db->query("SELECT name FROM {$prefix}game_personajes WHERE id = {$characterId} LIMIT 1");
$companionName = $db->fetch_field($pjNameQ, 'name') ?: 'Acompañante';

if ($action === 'accept') {
    // Confirm participation
    $db->write_query("
        UPDATE {$prefix}game_mission_participants 
        SET confirmed = 1 
        WHERE active_mission_id = {$activeMissionId} AND character_id = {$characterId}
    ");

    // Check if all participants are confirmed
    $pendingQ = $db->query("
        SELECT COUNT(*) AS pending_count 
        FROM {$prefix}game_mission_participants 
        WHERE active_mission_id = {$activeMissionId} AND confirmed = 0
    ");
    $pendingCount = (int)$db->fetch_field($pendingQ, 'pending_count');

    if ($pendingCount === 0) {
        // All confirmed, activate mission
        $db->write_query("
            UPDATE {$prefix}game_missions_active 
            SET status = 'active', started_at = NOW() 
            WHERE id = {$activeMissionId}
        ");

        // Notify leader that mission is active
        if ($leaderUid > 0) {
            try {
                $notifService = new \Game\Application\Services\NotificationService();
                $notifService->create(
                    $leaderUid,
                    'system',
                    "Misión Iniciada: {$activeMission['title']}",
                    "Todos los compañeros han aceptado la invitación. La misión '{$activeMission['title']}' ha comenzado.",
                    "game/public/personaje.php?pj={$activeMission['leader_character_id']}",
                    (int)$activeMission['leader_character_id']
                );
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }

    // Notify leader of acceptance
    if ($leaderUid > 0 && $pendingCount > 0) {
        try {
            $notifService = new \Game\Application\Services\NotificationService();
            $notifService->create(
                $leaderUid,
                'system',
                "Invitación Aceptada",
                "{$companionName} ha aceptado unirse a la misión '{$activeMission['title']}'.",
                "game/public/personaje.php?pj={$activeMission['leader_character_id']}",
                (int)$activeMission['leader_character_id']
            );
        } catch (\Throwable $e) {
            // Ignore
        }
    }
} else {
    // Decline invitation (remove companion record)
    $db->write_query("
        DELETE FROM {$prefix}game_mission_participants 
        WHERE active_mission_id = {$activeMissionId} AND character_id = {$characterId}
    ");

    // Notify leader of rejection
    if ($leaderUid > 0) {
        try {
            $notifService = new \Game\Application\Services\NotificationService();
            $notifService->create(
                $leaderUid,
                'system',
                "Invitación Rechazada",
                "{$companionName} ha rechazado unirse a la misión '{$activeMission['title']}'.",
                "game/public/personaje.php?pj={$activeMission['leader_character_id']}",
                (int)$activeMission['leader_character_id']
            );
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    // Check if there are other participants. If only leader is left, and it was pending, activate it!
    $pendingQ = $db->query("
        SELECT COUNT(*) AS pending_count 
        FROM {$prefix}game_mission_participants 
        WHERE active_mission_id = {$activeMissionId} AND confirmed = 0
    ");
    $pendingCount = (int)$db->fetch_field($pendingQ, 'pending_count');

    if ($pendingCount === 0 && $activeMission['status'] === 'pending') {
        $db->write_query("
            UPDATE {$prefix}game_missions_active 
            SET status = 'active', started_at = NOW() 
            WHERE id = {$activeMissionId}
        ");
    }
}

GameAjax::json(true, [
    'active_mission_id' => $activeMissionId,
    'character_id' => $characterId,
    'action' => $action,
], null);
