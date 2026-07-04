<?php
declare(strict_types=1);

/**
 * Ficha completa de Grupo
 * Unifica biblioteca detallada y gestión del líder.
 */
require_once __DIR__ . '/../bootstrap.php';

global $db, $mybb;
$prefix = TABLE_PREFIX;

$uid = (int)($mybb->user['uid'] ?? 0);
$crew_id = (int)($_GET['id'] ?? 0);

// ── 1. CARGAR DATOS DEL GRUPO ──
if ($crew_id <= 0) {
    if ($uid > 0) {
        $active_pj_id = (int)($db->fetch_field(
            $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1"),
            "active_pj_id"
        ) ?? 0);
        if ($active_pj_id > 0) {
            $crew_id = (int)($db->fetch_field(
                $db->query("SELECT tripulacion_id FROM {$prefix}game_personajes WHERE id = {$active_pj_id}"),
                "tripulacion_id"
            ) ?? 0);
        }
    }
    if ($crew_id <= 0) {
        header('Location: biblioteca_grupos.php');
        exit;
    }
}

$crew = $db->fetch_array($db->query("
    SELECT t.*, p.name AS leader_name, p.avatar AS leader_avatar, p.id AS leader_pj_id_check
    FROM {$prefix}game_tripulaciones t
    LEFT JOIN {$prefix}game_personajes p ON t.leader_pj_id = p.id
    WHERE t.id = {$crew_id}
"));

if (!$crew) {
    die("Grupo no encontrado o no existe.");
}

// ── 2. DETECTAR ROL DEL USUARIO ACTUAL ──
$is_leader = false;
$is_member = false;
$my_pj_id = 0;

if ($uid > 0) {
    $active_pj_id = (int)($db->fetch_field(
        $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1"),
        "active_pj_id"
    ) ?? 0);
    
    if ($active_pj_id > 0) {
        $my_pj_info = $db->fetch_array($db->query("SELECT tripulacion_id FROM {$prefix}game_personajes WHERE id = {$active_pj_id}"));
        $my_membership = $db->fetch_array($db->query("
            SELECT role, status_peticion 
            FROM {$prefix}game_tripulacion_miembros 
            WHERE pj_id = {$active_pj_id} AND tripulacion_id = {$crew_id}
        "));
        if ($my_membership && $my_membership['status_peticion'] === 'aprobada') {
            $is_member = true;
            $my_pj_id = $active_pj_id;
            if ($my_membership['role'] === 'Líder') {
                $is_leader = true;
            }
        }
    }
}

$can_join = ($active_pj_id > 0 && empty($my_pj_info['tripulacion_id']) && empty($my_membership));
$is_pending = (!empty($my_membership) && $my_membership['status_peticion'] === 'pendiente');

// ── 3. CARGAR MIEMBROS CON DATOS ENRIQUECIDOS ──
$members = [];
$aspirants = [];

$mq = $db->query("
    SELECT m.pj_id, m.role, m.role_custom, m.status_peticion, m.joined_at,
           p.name, p.avatar, p.faction, p.rango, p.recompensa,
           p.stats_json, p.race_name, p.user_id
    FROM {$prefix}game_tripulacion_miembros m
    JOIN {$prefix}game_personajes p ON m.pj_id = p.id
    WHERE m.tripulacion_id = {$crew_id}
    ORDER BY 
        CASE m.role WHEN 'Líder' THEN 0 ELSE 1 END,
        m.joined_at ASC
");

while ($r = $db->fetch_array($mq)) {
    // Calcular rango global del miembro
    $stats_data = json_decode($r['stats_json'] ?: '{}', true) ?: [];
    $sum_ranks = 0;
    foreach (['fue','res','agi','des','int','inst','esp'] as $s) {
        $sum_ranks += (int)($stats_data[$s] ?? 1);
    }
    $r['global_rank'] = \Game\Shared\StatScale::globalRankFromSum($sum_ranks);
    $r['global_rank_class'] = \Game\Shared\StatScale::globalRankCssClass(
        \Game\Shared\StatScale::globalRankFromSum($sum_ranks)
    );
    
    if ($r['status_peticion'] === 'pendiente') {
        $aspirants[] = $r;
    } else {
        $members[] = $r;
    }
}

// ── 4. CARGAR RELACIONES Y OTROS GRUPOS ──
$tag_colors = [
    'Aliado' => '#10b981',
    'Compañero' => '#3b82f6',
    'Rival' => '#f59e0b',
    'Enemigo' => '#ef4444',
    'Pacto de no agresión' => '#3b82f6',
    'Bajo protección' => '#06b6d4',
    'Tributario' => '#f97316',
    'Superior' => '#8b5cf6',
    'Subordinado' => '#64748b'
];

$crew_relations_data = json_decode($crew['relations'] ?? '', true);
if (!is_array($crew_relations_data)) {
    $crew_relations_data = [
        'relations' => [],
        'groups' => [],
        'connections' => []
    ];
}

$all_crews = [];
if ($is_leader) {
    $all_crews_q = $db->query("SELECT id, name, image_url FROM {$prefix}game_tripulaciones WHERE id != {$crew_id} ORDER BY name ASC");
    while ($c = $db->fetch_array($all_crews_q)) {
        $all_crews[] = $c;
    }
}

// ── 5. VARIABLES DE VISTA ──
$bburl = $mybb->settings['bburl'] ?? '';
$member_count = count($members);
$aspirant_count = count($aspirants);
$founded_date = date('d/m/Y', strtotime($crew['created_at']));

// ── 6. RENDERIZAR ──
ob_start();
require __DIR__ . '/../views/grupo/page_layout_1.php';
$content = ob_get_clean();

game_render_page(htmlspecialchars($crew['name']) . ' — Grupo', $content);
