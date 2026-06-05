<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;
$prefix = TABLE_PREFIX;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
if (!$cfg || !$cfg['active_pj_id']) {
    GameAjax::fail(400, 'Sin personaje activo');
}

$pj_q = $db->query("SELECT is_staff, staff_level FROM {$prefix}game_personajes WHERE id = " . (int)$cfg['active_pj_id'] . " AND user_id = {$uid} LIMIT 1");
$pj = $db->fetch_array($pj_q);
if (!$pj || !(int)$pj['is_staff'] || (int)$pj['staff_level'] < 3) {
    GameAjax::fail(403, 'Nivel de staff insuficiente');
}

$data = GameAjax::postJson();
GameAjax::requireCsrf($data);

$eras = $data['eras'] ?? null;
$eventos = $data['eventos'] ?? null;

if ($eras === null || $eventos === null) {
    GameAjax::fail(400, 'Datos de eras y eventos requeridos');
}

// Estructurar el array saneado final
$json_data = [
    'eras' => [],
    'eventos' => []
];

// Validar y sanear eras
foreach ($eras as $era) {
    $json_data['eras'][] = [
        'id' => (int)($era['id'] ?? 0),
        'name' => trim((string)($era['name'] ?? '')),
        'numeral' => trim((string)($era['numeral'] ?? '')),
        'start_year' => (int)($era['start_year'] ?? 0),
        'end_year' => (int)($era['end_year'] ?? 0),
        'intro_quote' => trim((string)($era['intro_quote'] ?? '')),
        'intro_text' => trim((string)($era['intro_text'] ?? ''))
    ];
}

// Validar y sanear eventos (campo 'link' solo si apunta a un hilo real del foro)
foreach ($eventos as $ev) {
    $evento = [
        'id' => (int)($ev['id'] ?? 0),
        'era_id' => (int)($ev['era_id'] ?? 0),
        'name' => trim((string)($ev['name'] ?? '')),
        'type' => trim((string)($ev['type'] ?? '')),
        'type_name' => trim((string)($ev['type_name'] ?? '')),
        'desc' => trim((string)($ev['desc'] ?? '')),
        'details' => trim((string)($ev['details'] ?? '')),
        'ubicacion' => trim((string)($ev['ubicacion'] ?? '')),
        'personajes' => trim((string)($ev['personajes'] ?? '')),
        'impacto' => trim((string)($ev['impacto'] ?? '')),
        'start_year' => (int)($ev['start_year'] ?? 0),
        'end_year' => (int)($ev['end_year'] ?? 0),
    ];

    $link = trim((string)($ev['link'] ?? ''));
    if ($link !== '') {
        $evento['link'] = $link;
    }

    $json_data['eventos'][] = $evento;
}

$jsonPath = __DIR__ . '/../lore.json';
$encoded = json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (file_put_contents($jsonPath, $encoded) === false) {
    GameAjax::fail(500, 'No se pudo guardar la información en lore.json');
}

GameAjax::json(true, ['message' => 'Línea de tiempo guardada exitosamente']);
