<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);
$character_id = (int)($input['character_id'] ?? 0);
$type = trim((string)($input['type'] ?? '')); // 'create' or 'add_existing'

if ($character_id <= 0 || !in_array($type, ['create', 'add_existing'], true)) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Parámetros inválidos.']]);
    exit;
}

$prefix = TABLE_PREFIX;

// Verify character ownership
$char_q = $db->query("SELECT user_id, name FROM {$prefix}game_personajes WHERE id = {$character_id} LIMIT 1");
$character = $db->fetch_array($char_q);

if (!$character) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Personaje no encontrado.']]);
    exit;
}

if ((int)$character['user_id'] !== $uid) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No eres el propietario de este personaje.']]);
    exit;
}

if ($type === 'create') {
    $card_name = trim((string)($input['card_name'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $card_type = trim((string)($input['card_type'] ?? 'tecnica')); // tecnica, equipo, etc.
    $effects = $input['effects'] ?? [];

    if ($card_name === '' || $description === '') {
        echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Nombre y descripción son requeridos.']]);
        exit;
    }

    $valid_card_types = ['tecnica', 'equipo', 'akuma_no_mi', 'haki', 'npc_menor', 'barco'];
    if (!in_array($card_type, $valid_card_types, true)) {
        $card_type = 'tecnica';
    }

    // Prepare details JSON
    $details = [
        'name' => $card_name,
        'card_type' => $card_type,
        'rank' => 'C',
        'activation' => 'activa',
        'cost_pe' => '—',
        'execution_stat' => '',
        'dice' => '',
        'description' => $description,
        'image_url' => '',
        'tags' => [],
        'effects' => $effects,
        'notes' => '',
        'reposo' => 0,
        'duracion' => 0
    ];

    // Initial message in discussion
    $discussion = [
        [
            'sender' => 'player',
            'sender_name' => $character['name'],
            'message' => "Solicitud de creación de carta (" . strtoupper($card_type) . "). Descripción inicial:\n" . $description,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    $insert = [
        'character_id' => $character_id,
        'card_id' => 0,
        'request_type' => 'create',
        'status' => 'pendiente',
        'current_rank' => 'C',
        'card_details_json' => $db->escape_string(json_encode($details, JSON_UNESCAPED_UNICODE)),
        'discussion_json' => $db->escape_string(json_encode($discussion, JSON_UNESCAPED_UNICODE))
    ];

    $db->insert_query('game_card_requests', $insert);
    $req_id = $db->insert_id();

    echo json_encode(['ok' => true, 'data' => ['request_id' => $req_id], 'error' => null]);
    exit;

} else { // add_existing
    $card_id = (int)($input['card_id'] ?? 0);
    $note = trim((string)($input['note'] ?? ''));

    if ($card_id <= 0) {
        echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Debe seleccionar una carta.']]);
        exit;
    }

    // Verify card exists in master catalog
    $card_q = $db->query("SELECT * FROM {$prefix}game_cards WHERE id = {$card_id} LIMIT 1");
    $card = $db->fetch_array($card_q);

    if (!$card) {
        echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Carta no encontrada en el catálogo.']]);
        exit;
    }

    // Check if character already has this card
    $own_q = $db->query("SELECT id FROM {$prefix}game_character_cards WHERE character_id = {$character_id} AND card_id = {$card_id} LIMIT 1");
    if ($db->num_rows($own_q) > 0) {
        echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'El personaje ya posee esta carta.']]);
        exit;
    }

    // Check if there is already a pending request for this card and character
    $pend_q = $db->query("SELECT id FROM {$prefix}game_card_requests WHERE character_id = {$character_id} AND card_id = {$card_id} AND status = 'pendiente' LIMIT 1");
    if ($db->num_rows($pend_q) > 0) {
        echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Ya tienes una solicitud pendiente para esta carta.']]);
        exit;
    }

    $discussion = [
        [
            'sender' => 'player',
            'sender_name' => $character['name'],
            'message' => "Solicitud para añadir la carta «" . $card['name'] . "» del catálogo." . ($note !== '' ? "\nNota: " . $note : ""),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    $insert = [
        'character_id' => $character_id,
        'card_id' => $card_id,
        'request_type' => 'add_existing',
        'status' => 'pendiente',
        'current_rank' => $card['rank'],
        'card_details_json' => null,
        'discussion_json' => $db->escape_string(json_encode($discussion, JSON_UNESCAPED_UNICODE))
    ];

    $db->insert_query('game_card_requests', $insert);
    $req_id = $db->insert_id();

    echo json_encode(['ok' => true, 'data' => ['request_id' => $req_id], 'error' => null]);
    exit;
}
