<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\CharacterProgression;

header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if (!$uid) {
    echo json_encode(['ok' => false, 'error' => ['code' => 401, 'message' => 'No autorizado.']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => ['code' => 405, 'message' => 'Method not allowed']]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$character_id = (int)($input['character_id'] ?? 0);

if ($character_id <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Parámetros inválidos.']]);
    exit;
}

$prefix = TABLE_PREFIX;

$char_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$character_id} LIMIT 1");
$character = $db->fetch_array($char_q);

if (!$character) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Personaje no encontrado.']]);
    exit;
}

if ((int)$character['user_id'] !== $uid) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No eres el propietario de este personaje.']]);
    exit;
}

if ($character['status'] !== 'aprobada') {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'El personaje debe estar aprobado.']]);
    exit;
}

$data = !empty($character['data_json']) ? json_decode($character['data_json'], true) : [];
if (!is_array($data)) {
    $data = [];
}

CharacterProgression::normalize($data);

if (CharacterProgression::getPendingLevels($data) < 1) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'No tienes subidas de nivel pendientes.']]);
    exit;
}

if (!CharacterProgression::canLevelUpThisWeek($data)) {
    $next = CharacterProgression::getNextLevelAvailableAt($data);
    $when = $next ? date('d/m/Y H:i', $next) : 'próximamente';
    echo json_encode(['ok' => false, 'error' => ['code' => 429, 'message' => "Solo puedes subir un nivel por semana. Próxima disponible: {$when}."]]);
    exit;
}

CharacterProgression::tryApplyPendingLevels($data);

$data_json_esc = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
$db->write_query("UPDATE {$prefix}game_personajes SET data_json = '{$data_json_esc}' WHERE id = {$character_id}");

$snapshot = CharacterProgression::snapshot($data);

echo json_encode([
    'ok' => true,
    'data' => $snapshot,
    'error' => null,
]);
exit;
