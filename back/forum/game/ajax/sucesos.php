<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
global $db, $mybb;

header('Content-Type: application/json');

if (!isset($mybb->user['uid']) || (int)$mybb->user['uid'] === 0) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'submit_suceso') {
    $uid = (int)$mybb->user['uid'];
    $prefix = TABLE_PREFIX;

    $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
    $cfg = $db->fetch_array($cfg_q);
    $pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;

    if ($pj_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Debes tener un personaje activo']);
        exit;
    }

    $url = $db->escape_string($_POST['url'] ?? '');
    $title = $db->escape_string($_POST['title'] ?? '');
    $desc = $db->escape_string($_POST['desc'] ?? '');
    $now = time();

    if (empty($url) || empty($title) || empty($desc)) {
        echo json_encode(['success' => false, 'error' => 'Faltan datos']);
        exit;
    }

    try {
        $db->query("INSERT INTO {$prefix}game_sucesos (user_id, pj_id, thread_url, title, description, status, created_at) 
                    VALUES ({$uid}, {$pj_id}, '{$url}', '{$title}', '{$desc}', 'pendiente', {$now})");
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error al guardar en la base de datos']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);
