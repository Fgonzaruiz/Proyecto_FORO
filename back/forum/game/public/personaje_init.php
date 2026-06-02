<?php
declare(strict_types=1);

/**
 * Contexto compartido para la vista de ficha (personaje.php).
 * @var array<string, mixed> $sheet
 */

use Game\Application\Services\CharacterSheetLoader;

global $mybb, $db;
$prefix = TABLE_PREFIX;
$user_id = (int)($mybb->user['uid'] ?? 0);
$req_pj_id = isset($_GET['pj']) ? (int)$_GET['pj'] : 0;

$loader = new CharacterSheetLoader();
$loaded = $loader->load($db, $prefix, $user_id, $req_pj_id);

$char = $loaded['char'];
$row = $loaded['row'];
$active_id = $loaded['active_id'];
$cfg = $loaded['cfg'];
$pj_progression = $loaded['pj_progression'];
$pp_available = $loaded['pp_available'];
$can_edit = $loaded['can_edit'];
$can_view_private = $loaded['can_view_private'];
$is_active_pj = $loaded['is_active_pj'];
$active_char_is_staff = $loaded['active_char_is_staff'];

$tag_colors = CharacterSheetLoader::TAG_COLORS;
$global_date_string = game_global_rol_date();
$all_chars = $loader->loadAllCharacterNames($db, $prefix);

$bb = preg_replace('/^https?:/', '', $mybb->settings['bburl']);
$b_url = $bb . '/images/game/personaje_banner.png';

$cat_list = [
    'Pasado' => '#8b5cf6', 'Presente' => '#10b981', 'Mision' => '#f59e0b',
    'Evento' => '#3b82f6', 'Trama' => '#ef4444', 'Fic' => '#ec4899', 'Off_Rol' => '#6b7280',
];
$cat_names = [
    'Pasado' => 'Pasado', 'Presente' => 'Presente', 'Mision' => 'Misión',
    'Evento' => 'Evento', 'Trama' => 'Trama', 'Fic' => 'Fic', 'Off_Rol' => 'Off Rol',
];
$cat_list_display = $cat_list;
