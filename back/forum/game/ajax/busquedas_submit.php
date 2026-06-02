<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

if (!isset($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

$uid = (int)$mybb->user['uid'];
$prefix = TABLE_PREFIX;

// Get active character
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$cid = $cfg ? (int)$cfg['active_pj_id'] : 0;

if ($cid <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Debes tener un personaje activo para enviar una búsqueda']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

GameAjax::requireCsrf($_POST);

$titulo = trim($_POST['titulo'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$imagen_url = trim($_POST['imagen_url'] ?? '');

if (strlen($titulo) < 3) {
    echo json_encode(['ok' => false, 'error' => 'El título es demasiado corto (mínimo 3 caracteres)']);
    exit;
}
if (strlen($descripcion) < 10) {
    echo json_encode(['ok' => false, 'error' => 'La descripción es demasiado corta (mínimo 10 caracteres)']);
    exit;
}

$titulo_esc = $db->escape_string($titulo);
$desc_esc = $db->escape_string($descripcion);
$img_esc = $db->escape_string($imagen_url);

$db->write_query("
    INSERT INTO {$prefix}game_busquedas (user_id, character_id, titulo, descripcion, imagen_url, status)
    VALUES ({$uid}, {$cid}, '{$titulo_esc}', '{$desc_esc}', '{$img_esc}', 'pendiente')
");

// Send notification to all staff
$staff_q = $db->query("SELECT user_id FROM {$prefix}game_personajes WHERE staff_level >= 2");
while ($staff_row = $db->fetch_array($staff_q)) {
    $staff_uid = (int)$staff_row['user_id'];
    if ($staff_uid !== $uid) {
        $pj_q = $db->query("SELECT name FROM {$prefix}game_personajes WHERE id = {$cid} LIMIT 1");
        $pj = $db->fetch_array($pj_q);
        $pj_name = $pj['name'] ?? 'Alguien';
        game_create_notification(
            $staff_uid,
            'busqueda_pendiente',
            "Nueva búsqueda de rol: «{$titulo}» por {$pj_name}",
            '',
            $mybb->settings['bburl'] . "/game/public/zona_staff_busquedas.php"
        );
    }
}

echo json_encode(['ok' => true, 'data' => null, 'error' => null]);
exit;
