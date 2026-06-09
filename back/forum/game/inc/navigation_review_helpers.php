<?php
declare(strict_types=1);

require_once __DIR__ . '/rol_calendar_helpers.php';

/** Inserta respuesta automática en el hilo del viaje (mensaje de staff/sistema). */
function game_navigation_post_thread_reply(int $threadId, int $userId, string $username, string $message): ?int
{
    global $db;

    if ($threadId <= 0 || $userId <= 0 || trim($message) === '') {
        return null;
    }

    $prefix = TABLE_PREFIX;
    $thread = $db->fetch_array($db->query("SELECT tid, fid, subject FROM {$prefix}threads WHERE tid = " . (int)$threadId . " LIMIT 1"));
    if (!$thread) {
        return null;
    }

    if (!defined('MYBB_ROOT')) {
        define('MYBB_ROOT', dirname(__DIR__, 2) . '/');
    }
    require_once MYBB_ROOT . 'inc/datahandlers/post.php';

    $subject = (string)$thread['subject'];
    if (stripos($subject, 'RE:') !== 0) {
        $subject = 'RE: ' . $subject;
    }

    $posthandler = new PostDataHandler('insert');
    $post = [
        'tid' => $threadId,
        'fid' => (int)$thread['fid'],
        'subject' => $subject,
        'uid' => $userId,
        'username' => $username,
        'message' => $message,
        'ipaddress' => my_inet_pton(get_ip()),
        'options' => [
            'signature' => 0,
            'emailnotify' => 0,
            'disablesmilies' => 0,
        ],
    ];
    $posthandler->set_data($post);
    if (!$posthandler->validate_post()) {
        return null;
    }
    $info = $posthandler->insert_post();

    return isset($info['pid']) ? (int)$info['pid'] : null;
}

/**
 * @return array{ok:bool, message?:string, post_id?:int}
 */
function game_navigation_review_voyage(int $voyageId, int $staffUid, string $staffUsername, string $decision): array
{
    global $db;

    $decision = strtolower(trim($decision));
    if (!in_array($decision, ['approve', 'deny'], true)) {
        return ['ok' => false, 'message' => 'Decisión inválida.'];
    }

    if (!$db->table_exists('game_navigation_voyages')) {
        return ['ok' => false, 'message' => 'Sistema de navegación no instalado.'];
    }

    $prefix = TABLE_PREFIX;
    $voyage = $db->fetch_array($db->query("SELECT * FROM {$prefix}game_navigation_voyages WHERE id = " . (int)$voyageId . " LIMIT 1"));
    if (!$voyage) {
        return ['ok' => false, 'message' => 'Viaje no encontrado.'];
    }

    $currentReview = (string)($voyage['staff_review'] ?? 'pending');
    if ($currentReview !== 'pending') {
        return ['ok' => false, 'message' => 'Este viaje ya fue revisado.'];
    }

    $fromName = '';
    $toName = '';
    $fq = $db->query("SELECT fid, name FROM {$prefix}forums WHERE fid IN (" . (int)$voyage['island_from_fid'] . ',' . (int)$voyage['island_to_fid'] . ')');
    while ($f = $db->fetch_array($fq)) {
        if ((int)$f['fid'] === (int)$voyage['island_from_fid']) {
            $fromName = (string)$f['name'];
        }
        if ((int)$f['fid'] === (int)$voyage['island_to_fid']) {
            $toName = (string)$f['name'];
        }
    }

    if ($decision === 'approve') {
        $staffReview = 'approved';
        $status = 'arrived';
        $body = '[b][Navegación — Staff][/b] La travesía de [b]' . $fromName . '[/b] a [b]' . $toName . '[/b] se ha completado con éxito. El viaje concluyó según lo previsto.';
    } else {
        $staffReview = 'denied';
        $status = 'cancelled';
        $body = '[b][Navegación — Staff][/b] La travesía de [b]' . $fromName . '[/b] a [b]' . $toName . '[/b] no pudo completarse. El staff ha denegado la navegación.';
    }

    $postId = game_navigation_post_thread_reply((int)$voyage['thread_id'], $staffUid, $staffUsername, $body);
    if ($postId === null) {
        return ['ok' => false, 'message' => 'No se pudo publicar el mensaje automático en el hilo.'];
    }

    $escReview = $db->escape_string($staffReview);
    $escStatus = $db->escape_string($status);
    $now = TIME_NOW;
    $db->write_query("UPDATE {$prefix}game_navigation_voyages SET
        staff_review = '{$escReview}',
        status = '{$escStatus}',
        reviewed_at = {$now},
        reviewed_by_uid = " . (int)$staffUid . ",
        staff_notice_post_id = {$postId}
        WHERE id = " . (int)$voyageId);

    return ['ok' => true, 'post_id' => $postId];
}

/** @param array<string, mixed> $row */
function game_navigation_voyage_enrich_row(array $row): array
{
    global $mybb;

    $startRol = (int)($row['start_rol_days'] ?? 0);
    $endRol = (int)($row['expected_end_rol_days'] ?? 0);
    if ($endRol <= 0 && $startRol > 0) {
        $endRol = $startRol + (int)($row['duration_days'] ?? 0);
    }

    $row['expected_end_rol_label'] = $endRol > 0 ? game_rol_date_label($endRol) : '';
    $row['start_rol_label'] = $startRol > 0 ? game_rol_date_label($startRol) : '';
    $row['staff_review'] = (string)($row['staff_review'] ?? 'pending');

    $bb = rtrim((string)($mybb->settings['bburl'] ?? ''), '/');
    $tid = (int)($row['thread_id'] ?? 0);
    $pid = (int)($row['post_id'] ?? 0);
    $row['post_url'] = ($bb && $tid > 0 && $pid > 0)
        ? "{$bb}/showthread.php?tid={$tid}&pid={$pid}#pid{$pid}"
        : '';

    return $row;
}
