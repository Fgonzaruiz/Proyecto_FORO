<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $mybb, $db;

$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
$thread_id = isset($_GET['thread_id']) ? (int)$_GET['thread_id'] : 0;
$last_post_for_thread_id = isset($_GET['last_post_for_thread_id']) ? (int)$_GET['last_post_for_thread_id'] : 0;
$top_poster = isset($_GET['top_poster']) ? (int)$_GET['top_poster'] : 0;
$global_top_poster = isset($_GET['global_top_poster']) ? (int)$_GET['global_top_poster'] : 0;

if ($uid <= 0 && $post_id <= 0 && $thread_id <= 0 && $last_post_for_thread_id <= 0 && $global_top_poster <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'data' => null, 'error' => ['code' => 'invalid_input', 'message' => 'uid required']]);
    exit;
}

$prefix = TABLE_PREFIX;
$bb = $mybb->settings['bburl'];
$result = null;

function _pj_result(array $row, string $bb, int $thread_id = 0): array {
    global $db;
    $prefix = TABLE_PREFIX;
    
    $img = $row['avatar'] ?: $row['banner'];
    if ($img && strpos($img, 'http://') !== 0 && strpos($img, 'https://') !== 0) {
        $img = rtrim($bb, '/') . '/' . ltrim($img, '/');
    }
    
    // Parse data_json and stats_json
    $data = !empty($row['data_json']) ? json_decode($row['data_json'], true) : [];
    if (!is_array($data)) $data = [];
    $stats_raw = !empty($row['stats_json']) ? json_decode($row['stats_json'], true) : [];
    if (!is_array($stats_raw)) $stats_raw = [];
    
    $stats = [
        'fue' => (int)($stats_raw['fue'] ?? $stats_raw['str'] ?? 5),
        'agi' => (int)($stats_raw['agi'] ?? 5),
        'des' => (int)($stats_raw['des'] ?? $stats_raw['res'] ?? 5),
        'inst' => (int)($stats_raw['inst'] ?? $stats_raw['vol'] ?? 5),
        'esp' => (int)($stats_raw['esp'] ?? $stats_raw['vol'] ?? 5),
        'int' => (int)($stats_raw['int'] ?? 5),
        'vit' => (int)($stats_raw['vit'] ?? 5),
    ];
    
    $nivel = (int)($data['nivel'] ?? 1);
    
    // Calculate max stats
    $max_pv = ($stats['fue'] * 4) + ($stats['agi'] * 2) + ($stats['esp'] * 3) + ($stats['int'] * 1);
    $max_pe = ($stats['esp'] * 4) + ($stats['des'] * 3) + ($stats['agi'] * 2) + ($stats['int'] * 1);
    
    $current_pv = $max_pv;
    $current_pe = $max_pe;
    
    // Fetch thread specific health/energy if available
    if ($thread_id > 0 && $db->table_exists('game_thread_pj_state')) {
        $state_q = $db->query("
            SELECT current_pv, current_pe
            FROM {$prefix}game_thread_pj_state
            WHERE thread_id = {$thread_id} AND character_id = " . (int)$row['id'] . "
            LIMIT 1
        ");
        $state = $db->fetch_array($state_q);
        if ($state) {
            $current_pv = (int)$state['current_pv'];
            $current_pe = (int)$state['current_pe'];
        }
    }
    
    // Parse signature if any
    $firma_html = '';
    $raw_firma = $row['firma'] ?? '';
    if ($raw_firma !== '') {
        require_once MYBB_ROOT . 'inc/class_parser.php';
        $parser = new postParser();
        $parser_options = [
            'allow_html' => 0,
            'allow_mycode' => 1,
            'allow_smilies' => 0,
            'allow_imgcode' => 1,
            'filter_badwords' => 1
        ];
        $firma_html = $parser->parse_message($raw_firma, $parser_options);
    }
    
    return [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'race_name' => $row['race_name'],
        'occupation_name' => $row['occupation_name'],
        'rango' => $row['rango'],
        'tripulacion' => $row['tripulacion'],
        'avatar' => $img ?: '',
        'is_staff' => (bool)$row['is_staff'],
        'staff_level' => (int)($row['staff_level'] ?? 0),
        'postnum' => (int)($row['postnum'] ?? 0),
        'threadnum' => (int)($row['threadnum'] ?? 0),
        'firma' => $raw_firma,
        'firma_html' => $firma_html,
        'faction' => $row['faction'] ?: 'Civil',
        'nivel' => $nivel,
        'stats' => $stats,
        'current_pv' => $current_pv,
        'current_pe' => $current_pe,
        'max_pv' => $max_pv,
        'max_pe' => $max_pe,
    ];
}

$fields = "id, name, race_name, occupation_name, rango, tripulacion, avatar, banner, is_staff, staff_level, postnum, threadnum, status, is_npc, faction, stats_json, data_json, firma";

// Try to fetch thread_id if post_id is provided
if ($post_id > 0 && $thread_id <= 0) {
    $p_q = $db->query("SELECT tid FROM {$prefix}posts WHERE pid = {$post_id} LIMIT 1");
    $p = $db->fetch_array($p_q);
    if ($p) {
        $thread_id = (int)$p['tid'];
    }
}
if ($last_post_for_thread_id > 0 && $thread_id <= 0) {
    $thread_id = $last_post_for_thread_id;
}

// If post_id provided, try to get character stored at post creation time
if ($post_id > 0) {
    $pc_q = $db->query("SELECT character_id FROM {$prefix}game_post_characters WHERE post_id = {$post_id} LIMIT 1");
    $pc = $db->fetch_array($pc_q);
    if ($pc) {
        $pj_q = $db->query("SELECT {$fields} FROM {$prefix}game_personajes WHERE id = " . (int)$pc['character_id'] . " LIMIT 1");
        $pj = $db->fetch_array($pj_q);
        if ($pj) $result = _pj_result($pj, $bb, $thread_id);
    }
} elseif ($thread_id > 0) {
    // thread_id provided: look up character stored when thread was created
    $pc_q = $db->query("SELECT character_id FROM {$prefix}game_post_characters WHERE thread_id = {$thread_id} LIMIT 1");
    $pc = $db->fetch_array($pc_q);
    if ($pc) {
        $pj_q = $db->query("SELECT {$fields} FROM {$prefix}game_personajes WHERE id = " . (int)$pc['character_id'] . " LIMIT 1");
        $pj = $db->fetch_array($pj_q);
        if ($pj) $result = _pj_result($pj, $bb, $thread_id);
    }
} elseif ($last_post_for_thread_id > 0) {
    // Look up the character of the latest post in this thread
    $pc_q = $db->query("
        SELECT gpc.character_id 
        FROM {$prefix}posts p 
        JOIN {$prefix}game_post_characters gpc ON p.pid = gpc.post_id 
        WHERE p.tid = {$last_post_for_thread_id} 
        ORDER BY p.dateline DESC 
        LIMIT 1
    ");
    $pc = $db->fetch_array($pc_q);
    if ($pc) {
        $pj_q = $db->query("SELECT {$fields} FROM {$prefix}game_personajes WHERE id = " . (int)$pc['character_id'] . " LIMIT 1");
        $pj = $db->fetch_array($pj_q);
        if ($pj) $result = _pj_result($pj, $bb, $thread_id);
    }
} elseif ($top_poster > 0 && $uid > 0) {
    // For stats: get the character of this user with the highest postnum
    $pj_q = $db->query("SELECT {$fields} FROM {$prefix}game_personajes WHERE user_id = {$uid} ORDER BY postnum DESC LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) $result = _pj_result($pj, $bb, $thread_id);
} elseif ($global_top_poster > 0) {
    // Return the character with the absolute highest postnum across the whole forum
    $pj_q = $db->query("SELECT {$fields} FROM {$prefix}game_personajes ORDER BY postnum DESC LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) $result = _pj_result($pj, $bb, $thread_id);
}

// Fallback to current active character if no post/thread record was found (or if neither was provided)
if (!$result && $uid > 0) {
    $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
    $cfg = $db->fetch_array($cfg_q);
    if ($cfg && $cfg['active_pj_id']) {
        $pj_q = $db->query("SELECT {$fields} FROM {$prefix}game_personajes WHERE id = " . (int)$cfg['active_pj_id'] . " LIMIT 1");
        $pj = $db->fetch_array($pj_q);
        if ($pj) $result = _pj_result($pj, $bb, $thread_id);
    }
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'data' => $result,
    'error' => null,
    'meta' => ['endpoint' => 'get_active_pj_for_user'],
]);
exit;
