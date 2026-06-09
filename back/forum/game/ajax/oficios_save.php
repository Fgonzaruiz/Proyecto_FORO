<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
$staffLevel = game_get_active_staff_level($uid);
if ($staffLevel < 3) {
    GameAjax::fail(403, 'No tienes permiso para editar oficios');
}

GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$prefix = TABLE_PREFIX;
$id = (int)($input['id'] ?? 0);
$slug = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($input['slug'] ?? ''));
$name = trim((string)($input['name'] ?? ''));
$description = trim((string)($input['description'] ?? ''));
$category = trim((string)($input['category'] ?? 'oficio'));
$icon = trim((string)($input['icon'] ?? 'fa-briefcase'));
$isActive = !empty($input['is_active']) ? 1 : 0;
$sortOrder = (int)($input['sort_order'] ?? 0);

if ($slug === '' || $name === '') {
    GameAjax::fail(400, 'Slug y nombre son obligatorios');
}

$escSlug = $db->escape_string($slug);
$escName = $db->escape_string($name);
$escDesc = $db->escape_string($description);
$escCat = $db->escape_string($category);
$escIcon = $db->escape_string($icon);

if ($id > 0) {
    $db->write_query("UPDATE {$prefix}game_oficios SET
        slug = '{$escSlug}', name = '{$escName}', description = '{$escDesc}',
        category = '{$escCat}', icon = '{$escIcon}', is_active = {$isActive}, sort_order = {$sortOrder}
        WHERE id = {$id}");
    GameAjax::json(true, ['id' => $id]);
}

$dup = $db->query("SELECT 1 FROM {$prefix}game_oficios WHERE slug = '{$escSlug}' LIMIT 1");
if ($db->num_rows($dup)) {
    GameAjax::fail(409, 'Ya existe un oficio con ese slug');
}

$db->insert_query('game_oficios', [
    'slug' => $slug,
    'name' => $name,
    'description' => $description,
    'category' => $category,
    'icon' => $icon,
    'is_active' => $isActive,
    'sort_order' => $sortOrder,
]);
GameAjax::json(true, ['id' => (int)$db->insert_id()]);
