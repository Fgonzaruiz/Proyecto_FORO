<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\PersonajeRepository;

global $mybb, $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$pj_id = (int)($input['pj_id'] ?? 0);

if ($pj_id <= 0) {
    GameAjax::json(false, null, ['code' => 'invalid_input', 'message' => 'pj_id requerido'], 400);
}

$prefix = TABLE_PREFIX;
$personajes = new PersonajeRepository();

if ($personajes->findByIdForUser($pj_id, $uid) === null) {
    GameAjax::json(false, null, ['code' => 'forbidden', 'message' => 'Ese personaje no te pertenece'], 403);
}

$db->write_query("INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used, active_pj_id) VALUES ({$uid}, 1, 0, {$pj_id})
    ON DUPLICATE KEY UPDATE active_pj_id = {$pj_id}");

$pj_q = $db->query("SELECT id, name, race_name, avatar, banner, is_staff, staff_level FROM {$prefix}game_personajes WHERE id = {$pj_id} LIMIT 1");
$pj = $db->fetch_array($pj_q);

function pj_img_url(string $path, string $bb): string
{
    if ($path === '') {
        return '';
    }
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    return rtrim($bb, '/') . '/' . ltrim($path, '/');
}

$bb = $mybb->settings['bburl'];
$img = $pj['avatar'] ?: $pj['banner'];
$avatar = $img ? pj_img_url($img, $bb) : pj_img_url('images/game/personaje_banner.png', $bb);

GameAjax::json(true, [
    'pj_id' => $pj_id,
    'name' => $pj['name'],
    'race_name' => $pj['race_name'],
    'avatar' => $avatar,
    'is_staff' => (bool)$pj['is_staff'],
    'staff_level' => (int)$pj['staff_level'],
], null);
