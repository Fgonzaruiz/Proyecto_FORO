<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

$user_id = (int)($mybb->user['uid'] ?? 0);
if (!$user_id) {
    echo json_encode(['ok' => false, 'error' => ['code' => 401, 'message' => 'No autorizado.']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => ['code' => 405, 'message' => 'Method not allowed']]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['pj_id']) || empty($input['type'])) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Payload inválido.']]);
    exit;
}

$pj_id = (int)$input['pj_id'];
$type = $input['type']; // 'diario' or 'relacion'

$prefix = TABLE_PREFIX;
$query = $db->query("SELECT id, user_id, cronologia_json FROM {$prefix}game_personajes WHERE id = {$pj_id} LIMIT 1");
$char = $db->fetch_array($query);

if (!$char) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Personaje no encontrado.']]);
    exit;
}

if ((int)$char['user_id'] !== $user_id) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No es tu personaje.']]);
    exit;
}

$cronologia = !empty($char['cronologia_json']) ? json_decode($char['cronologia_json'], true) : [];
if (!is_array($cronologia)) $cronologia = [];
if (!isset($cronologia['diario'])) $cronologia['diario'] = [];
if (!isset($cronologia['relaciones'])) $cronologia['relaciones'] = [];
if (!isset($cronologia['groups'])) $cronologia['groups'] = [];
if (!isset($cronologia['connections'])) $cronologia['connections'] = [];

$action = $input['action'] ?? 'save';
$entry_id = $input['entry_id'] ?? '';

if ($type === 'diario') {
    if ($action === 'delete') {
        foreach ($cronologia['diario'] as $k => $v) {
            if (($v['id'] ?? '') === $entry_id) { array_splice($cronologia['diario'], $k, 1); break; }
        }
    } else {
        $allowed_cats = ['Pasado','Presente','Mision','Evento','Trama','Fic'];
        $cat = $input['category'] ?? 'Presente';
        if (!in_array($cat, $allowed_cats)) $cat = 'Presente';
        $new_entry = [
            'id' => $entry_id ?: uniqid(),
            'day' => (int)($input['day'] ?? 1),
            'season' => (int)($input['season'] ?? 0),
            'year' => (int)($input['year'] ?? 1),
            'category' => $cat,
            'desc' => htmlspecialchars($input['desc'] ?? ''),
            'link' => htmlspecialchars($input['link'] ?? '')
        ];
        if ($entry_id) {
            foreach ($cronologia['diario'] as $k => $v) {
                if (($v['id'] ?? '') === $entry_id) { $cronologia['diario'][$k] = $new_entry; break; }
            }
        } else {
            $cronologia['diario'][] = $new_entry;
        }
    }
} elseif ($type === 'relacion') {
    if ($action === 'delete') {
        foreach ($cronologia['relaciones'] as $k => $v) {
            if (($v['id'] ?? '') === $entry_id) { array_splice($cronologia['relaciones'], $k, 1); break; }
        }
    } else {
        $is_npc = !empty($input['is_npc']);
        $tags = $input['tags'] ?? [];
        if (!is_array($tags)) $tags = [$tags];
        if (empty($tags)) $tags = ['Conocido'];
        $new_entry = [
            'id' => $entry_id ?: uniqid(),
            'pj_id' => $is_npc ? null : (int)($input['target_pj_id'] ?? 0),
            'name' => $is_npc ? ($input['npc_name'] ?? '') : ($input['target_pj_name'] ?? ''),
            'tags' => $tags,
            'relation' => $tags[0] ?? 'Conocido',
            'desc' => $input['desc'] ?? '',
            'image' => $input['image'] ?? '',
            'is_npc' => $is_npc
        ];
        if ($entry_id) {
            foreach ($cronologia['relaciones'] as $k => $v) {
                if (($v['id'] ?? '') === $entry_id) { $cronologia['relaciones'][$k] = $new_entry; break; }
            }
        } else {
            $cronologia['relaciones'][] = $new_entry;
        }
    }
} elseif ($type === 'group') {
    if ($action === 'delete') {
        foreach ($cronologia['groups'] as $k => $v) {
            if (($v['id'] ?? '') === $entry_id) { array_splice($cronologia['groups'], $k, 1); break; }
        }
    } else {
        $members = $input['members'] ?? [];
        if (!is_array($members)) $members = [];
        
        $new_group = [
            'id' => $entry_id ?: uniqid('grp_'),
            'name' => htmlspecialchars($input['name'] ?? 'Nuevo Grupo'),
            'color' => htmlspecialchars($input['color'] ?? '#6366f1'),
            'members' => $members
        ];
        
        if ($entry_id) {
            foreach ($cronologia['groups'] as $k => $v) {
                if (($v['id'] ?? '') === $entry_id) { $cronologia['groups'][$k] = $new_group; break; }
            }
        } else {
            $cronologia['groups'][] = $new_group;
        }
    }
} elseif ($type === 'connection') {
    if ($action === 'delete') {
        foreach ($cronologia['connections'] as $k => $v) {
            if (($v['id'] ?? '') === $entry_id) { array_splice($cronologia['connections'], $k, 1); break; }
        }
    } else {
        $src_name = '???'; $tgt_name = '???';
        foreach ($cronologia['relaciones'] as $r) {
            if (($r['id'] ?? '') === ($input['source'] ?? '')) $src_name = $r['name'];
            if (($r['id'] ?? '') === ($input['target'] ?? '')) $tgt_name = $r['name'];
        }
        $new_conn = [
            'id' => $entry_id ?: uniqid('conn_'),
            'source' => $input['source'] ?? '',
            'target' => $input['target'] ?? '',
            'source_name' => $src_name,
            'target_name' => $tgt_name,
            'label' => htmlspecialchars($input['label'] ?? ''),
            'color' => htmlspecialchars($input['color'] ?? '#ec4899')
        ];
        
        if ($entry_id) {
            foreach ($cronologia['connections'] as $k => $v) {
                if (($v['id'] ?? '') === $entry_id) { $cronologia['connections'][$k] = $new_conn; break; }
            }
        } else {
            $cronologia['connections'][] = $new_conn;
        }
    }
} elseif ($type === 'network_batch') {
    // Override the arrays completely
    if (isset($input['data']['relaciones']) && is_array($input['data']['relaciones'])) {
        $cronologia['relaciones'] = $input['data']['relaciones'];
    }
    if (isset($input['data']['groups']) && is_array($input['data']['groups'])) {
        $cronologia['groups'] = $input['data']['groups'];
    }
    if (isset($input['data']['connections']) && is_array($input['data']['connections'])) {
        $cronologia['connections'] = $input['data']['connections'];
    }
}
$new_json = $db->escape_string(json_encode($cronologia, JSON_UNESCAPED_UNICODE));
$db->write_query("UPDATE {$prefix}game_personajes SET cronologia_json = '{$new_json}' WHERE id = {$pj_id}");

echo json_encode(['ok' => true, 'data' => ['success' => true], 'error' => null]);
exit;
