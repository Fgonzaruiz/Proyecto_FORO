<?php
declare(strict_types=1);

/**
 * Helpers para el sistema de Misiones RPG.
 */

if (defined('MYBB_ROOT') && is_file(MYBB_ROOT . 'inc/functions_post.php')) {
    require_once MYBB_ROOT . 'inc/functions_post.php';
}

function game_get_character_active_mission(int $characterId): ?array
{
    global $db;
    $prefix = TABLE_PREFIX;
    $characterId = (int)$characterId;

    $q = $db->query("
        SELECT ma.*, m.title, m.description, m.rank, m.points_reward, m.jenny_reward, m.isla, m.categoria, m.max_posts
        FROM {$prefix}game_missions_active ma
        JOIN {$prefix}game_missions m ON ma.mission_id = m.id
        JOIN {$prefix}game_mission_participants mp ON mp.active_mission_id = ma.id
        WHERE mp.character_id = {$characterId} AND ma.status IN ('pending', 'active')
        LIMIT 1
    ");

    return $db->fetch_array($q) ?: null;
}

function game_character_has_cooldown(int $characterId, ?string &$cooldown_until_label = null): bool
{
    global $db;
    $prefix = TABLE_PREFIX;
    $characterId = (int)$characterId;

    $q = $db->query("
        SELECT MAX(cooldown_until) AS max_cooldown 
        FROM {$prefix}game_mission_participants 
        WHERE character_id = {$characterId} AND cooldown_until > NOW()
    ");
    $res = $db->fetch_array($q);
    if ($res && $res['max_cooldown']) {
        if ($cooldown_until_label !== null) {
            $cooldown_until_label = $res['max_cooldown'];
        }
        return true;
    }
    return false;
}

function game_character_can_accept_mission(int $characterId, int $missionId, string &$error = ''): bool
{
    global $db;
    $prefix = TABLE_PREFIX;
    $characterId = (int)$characterId;
    $missionId = (int)$missionId;

    // 1. Fetch character details (level)
    $pjQ = $db->query("SELECT name, status, data_json FROM {$prefix}game_personajes WHERE id = {$characterId} LIMIT 1");
    $pj = $db->fetch_array($pjQ);
    if (!$pj) {
        $error = "El personaje no existe.";
        return false;
    }
    if ($pj['status'] !== 'aprobada') {
        $error = "El personaje debe estar aprobado por el staff.";
        return false;
    }

    $pjData = !empty($pj['data_json']) ? json_decode($pj['data_json'], true) : [];
    $pjLevel = (int)($pjData['nivel'] ?? 1);

    // 2. Fetch mission requirements
    $mQ = $db->query("SELECT * FROM {$prefix}game_missions WHERE id = {$missionId} AND is_active = 1 LIMIT 1");
    $mission = $db->fetch_array($mQ);
    if (!$mission) {
        $error = "La misión no existe o está inactiva.";
        return false;
    }

    if ($pjLevel < (int)$mission['min_level'] || $pjLevel > (int)$mission['max_level']) {
        $error = "El nivel del personaje ({$pjLevel}) no cumple con los requisitos de la misión (Nivel {$mission['min_level']} - {$mission['max_level']}).";
        return false;
    }

    // 3. Check active mission
    $active = game_get_character_active_mission($characterId);
    if ($active) {
        $error = "Ya estás participando en una misión activa ('{$active['title']}').";
        return false;
    }

    // 4. Check cooldown
    $cooldown_label = '';
    if (game_character_has_cooldown($characterId, $cooldown_label)) {
        $error = "El personaje está en cooldown de misiones hasta: {$cooldown_label}.";
        return false;
    }

    return true;
}

function game_accept_mission(int $leaderCharacterId, int $missionId, array $participantCharacterIds, string &$error = ''): ?int
{
    global $db;
    $prefix = TABLE_PREFIX;
    $leaderCharacterId = (int)$leaderCharacterId;
    $missionId = (int)$missionId;

    // Validate leader
    if (!game_character_can_accept_mission($leaderCharacterId, $missionId, $error)) {
        return null;
    }

    // Fetch mission details
    $mQ = $db->query("SELECT * FROM {$prefix}game_missions WHERE id = {$missionId} LIMIT 1");
    $mission = $db->fetch_array($mQ);
    if (!$mission) {
        $error = "Misión no encontrada.";
        return null;
    }

    // Validate companions
    $participants = [$leaderCharacterId];
    $validatedCompanions = [];
    foreach ($participantCharacterIds as $cId) {
        $cId = (int)$cId;
        if ($cId === $leaderCharacterId || $cId <= 0) {
            continue;
        }
        if (!game_character_can_accept_mission($cId, $missionId, $companionError)) {
            $error = "El acompañante no puede unirse: " . $companionError;
            return null;
        }
        $participants[] = $cId;
        $validatedCompanions[] = $cId;
    }

    // Find target forum ID for the island of the mission
    $forumId = 2; // Default fallback "My Forum"
    $islaEsc = $db->escape_string($mission['isla']);
    $fq = $db->query("SELECT fid FROM {$prefix}forums WHERE name = '{$islaEsc}' AND type='f' LIMIT 1");
    if ($f = $db->fetch_array($fq)) {
        $forumId = (int)$f['fid'];
    } else {
        // Look for any forum named "Misiones" or "Tablon de Misiones"
        $fq2 = $db->query("SELECT fid FROM {$prefix}forums WHERE (name LIKE '%Misiones%' OR name LIKE '%Tablón%') AND type='f' LIMIT 1");
        if ($f2 = $db->fetch_array($fq2)) {
            $forumId = (int)$f2['fid'];
        }
    }

    // Fetch Narrator user UID
    $narratorUid = 2;
    $uq = $db->query("SELECT uid FROM {$prefix}users WHERE username = 'Narrador' LIMIT 1");
    if ($u = $db->fetch_array($uq)) {
        $narratorUid = (int)$u['uid'];
    }

    // Construct mission post content
    $participantNames = [];
    $pjNamesQ = $db->query("SELECT id, name FROM {$prefix}game_personajes WHERE id IN (" . implode(',', $participants) . ")");
    while ($pjn = $db->fetch_array($pjNamesQ)) {
        $participantNames[$pjn['id']] = $pjn['name'];
    }

    $leaderName = $participantNames[$leaderCharacterId] ?? 'Desconocido';
    $companionsStr = '';
    if (!empty($validatedCompanions)) {
        $compNames = [];
        foreach ($validatedCompanions as $cId) {
            $compNames[] = $participantNames[$cId] ?? 'Desconocido';
        }
        $companionsStr = "\n[b]Acompañantes:[/b] " . implode(', ', $compNames);
    }

    $subject = "[Misión Rango {$mission['rank']}] {$mission['title']}";
    $message = "[align=center][b][size=large]Misión Oficial del Narrador[/size][/b][/align]\n\n" .
        "[b]Título:[/b] {$mission['title']}\n" .
        "[b]Rango:[/b] {$mission['rank']}\n" .
        "[b]Lugar/Isla:[/b] {$mission['isla']}\n" .
        "[b]Categoría:[/b] " . ucfirst($mission['categoria']) . "\n" .
        "[b]Recompensas:[/b] [color=#eab308]{$mission['points_reward']} PD[/color] | [color=#10b981]{$mission['jenny_reward']} Jenny[/color]\n" .
        "[b]Límite de Posts:[/b] {$mission['max_posts']}\n" .
        "[b]Líder del Grupo:[/b] {$leaderName}{$companionsStr}\n\n" .
        "[hr]\n\n" .
        "[b]Descripción del Encargo:[/b]\n" .
        "{$mission['description']}\n\n" .
        "[b]Objetivos principales:[/b]\n" .
        "1. Realizar un mínimo de posts que cumplan con la coherencia narrativa.\n" .
        "2. Completar o reportar el desenlace del encargo.\n\n" .
        "[color=#eab308][b]¡Buena suerte en vuestra aventura![/b][/color]";

    // Create MyBB Thread under Narrador User
    require_once MYBB_ROOT . 'inc/datahandlers/post.php';
    $posthandler = new PostDataHandler('insert');
    $posthandler->action = 'thread';

    $new_thread = [
        'fid' => $forumId,
        'subject' => $subject,
        'prefix' => 0,
        'icon' => 0,
        'uid' => $narratorUid,
        'username' => 'Narrador',
        'message' => $message,
        'ipaddress' => my_inet_pton('127.0.0.1'),
        'posthash' => md5((string)$narratorUid . random_str()),
        'options' => [
            'signature' => 0,
            'emailnotify' => 0,
            'disablesmilies' => 0,
        ]
    ];

    $posthandler->set_data($new_thread);
    if (!$posthandler->validate_thread()) {
        $errs = $posthandler->get_friendly_errors();
        $error = "Error MyBB: " . implode(', ', $errs);
        return null;
    }

    $thread_info = $posthandler->insert_thread();
    $tid = (int)$thread_info['tid'];

    // Calculate current forum date (Presente)
    $epoch = strtotime('2026-05-01');
    $now = time();
    $diff_seconds = max(0, $now - $epoch);
    $diff_days_float = $diff_seconds / 86400;
    $rol_days = (int)floor($diff_days_float * 1.5) + 1;
    
    $days_per_season = 65;
    $days_per_year = $days_per_season * 4;
    
    $year = (int)floor(($rol_days - 1) / $days_per_year) + 1;
    $day_of_year = (($rol_days - 1) % $days_per_year) + 1;
    $season = (int)floor(($day_of_year - 1) / $days_per_season);
    $day = (($day_of_year - 1) % $days_per_season) + 1;

    $db->write_query("
        INSERT INTO {$prefix}game_thread_meta (thread_id, thread_type, day, season, year)
        VALUES ({$tid}, 'Presente', {$day}, {$season}, {$year})
        ON DUPLICATE KEY UPDATE thread_type='Presente', day={$day}, season={$season}, year={$year}
    ");

    // Insert active mission record
    // Status is active if no companions, else pending (until companions confirm)
    $status = empty($validatedCompanions) ? 'active' : 'pending';
    $startedAtStr = empty($validatedCompanions) ? 'NOW()' : 'NULL';

    $db->write_query("
        INSERT INTO {$prefix}game_missions_active (mission_id, tid, leader_character_id, status, post_count, started_at, created_at)
        VALUES ({$missionId}, {$tid}, {$leaderCharacterId}, '{$status}', 0, {$startedAtStr}, NOW())
    ");
    $activeId = $db->insert_id();

    // Insert participants records
    // Leader
    $leaderUserQ = $db->query("SELECT user_id FROM {$prefix}game_personajes WHERE id = {$leaderCharacterId} LIMIT 1");
    $leaderUid = (int)$db->fetch_field($leaderUserQ, 'user_id');
    $db->write_query("
        INSERT INTO {$prefix}game_mission_participants (active_mission_id, character_id, user_id, confirmed)
        VALUES ({$activeId}, {$leaderCharacterId}, {$leaderUid}, 1)
    ");

    // Companions
    foreach ($validatedCompanions as $cId) {
        $cUserQ = $db->query("SELECT user_id FROM {$prefix}game_personajes WHERE id = {$cId} LIMIT 1");
        $cUid = (int)$db->fetch_field($cUserQ, 'user_id');
        $db->write_query("
            INSERT INTO {$prefix}game_mission_participants (active_mission_id, character_id, user_id, confirmed)
            VALUES ({$activeId}, {$cId}, {$cUid}, 0)
        ");

        // Send notification to companion
        try {
            $notifService = new \Game\Application\Services\NotificationService();
            $notifService->create(
                $cUid,
                'system',
                "Invitación a Misión: {$mission['title']}",
                "{$leaderName} te ha invitado a participar en la misión '{$mission['title']}'. Confirma tu asistencia en tu ficha (Gestión).",
                "game/public/personaje.php?pj={$cId}",
                $cId
            );
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    return $activeId;
}
