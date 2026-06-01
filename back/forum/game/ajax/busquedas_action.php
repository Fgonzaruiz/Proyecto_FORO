<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

if (!isset($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

$uid = (int)$mybb->user['uid'];
$prefix = TABLE_PREFIX;

// Check staff level
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$cid = $cfg ? (int)$cfg['active_pj_id'] : 0;

if ($cid > 0) {
    $pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = {$cid} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    $staff_level = (int)($pj['staff_level'] ?? 0);
} else {
    $staff_level = 0;
}

if ($staff_level < 2) {
    echo json_encode(['ok' => false, 'error' => 'Sin permisos']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$accion = $_POST['accion'] ?? '';
$nota = trim($_POST['nota'] ?? '');

if ($id <= 0 || !in_array($accion, ['aprobar', 'denegar'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

$new_status = $accion === 'aprobar' ? 'aprobada' : 'denegada';
$nota_esc = $db->escape_string($nota);

$db->write_query("
    UPDATE {$prefix}game_busquedas
    SET status = '{$new_status}', staff_nota = '{$nota_esc}', updated_at = NOW()
    WHERE id = {$id}
");

// Notify the owner
$b_q = $db->query("SELECT user_id, titulo FROM {$prefix}game_busquedas WHERE id = {$id} LIMIT 1");
$b = $db->fetch_array($b_q);
if ($b) {
    $msg = $accion === 'aprobar'
        ? "Tu búsqueda de rol «{$b['titulo']}» ha sido aprobada y ya aparece en el tablón."
        : "Tu búsqueda de rol «{$b['titulo']}» ha sido denegada." . ($nota ? " Nota: {$nota}" : '');
    game_create_notification(
        (int)$b['user_id'],
        'busqueda_respuesta',
        $msg,
        '',
        $mybb->settings['bburl'] . '/index.php'
    );
}

echo json_encode(['ok' => true, 'data' => ['status' => $new_status], 'error' => null]);
exit;
