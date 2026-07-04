<?php
declare(strict_types=1);

/**
 * Endpoint legado para la gestión de grupo.
 * Redirige a la nueva ficha premium de grupo.
 */
require_once __DIR__ . '/../bootstrap.php';

global $db, $mybb;
$prefix = TABLE_PREFIX;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$active_pj_id = (int)($db->fetch_field(
    $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1"),
    "active_pj_id"
) ?? 0);

if ($active_pj_id > 0) {
    $crew_id = (int)($db->fetch_field(
        $db->query("SELECT tripulacion_id FROM {$prefix}game_personajes WHERE id = {$active_pj_id}"),
        "tripulacion_id"
    ) ?? 0);
    
    if ($crew_id === 0) {
        $m_data = $db->fetch_array($db->query("SELECT tripulacion_id FROM {$prefix}game_tripulacion_miembros WHERE pj_id = {$active_pj_id}"));
        if ($m_data) {
            $crew_id = (int)$m_data['tripulacion_id'];
        }
    }
    
    if ($crew_id > 0) {
        header("Location: grupo.php?id={$crew_id}#gestion");
        exit;
    }
}

// Si no tiene grupo, lo mandamos a la biblioteca general
header("Location: biblioteca_grupos.php");
exit;
