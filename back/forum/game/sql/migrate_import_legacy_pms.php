<?php
declare(strict_types=1);

/**
 * Importa mensajes privados MyBB (privatemessages) al buzón por personaje.
 * Idempotente: usa legacy_pmid para no duplicar.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/migration_helpers.php';

global $mybb, $db;

if ((int)($mybb->user['uid'] ?? 0) === 0 || (int)($mybb->usergroup['cancp'] ?? 0) !== 1) {
    error_no_permission();
}

$prefix = TABLE_PREFIX;
$bburl = $mybb->settings['bburl'];

/** @var array<int, int|null> */
$charCache = [];

function import_resolve_character_id(int $userId): ?int
{
    global $db, $prefix, $charCache;

    if ($userId <= 0) {
        return null;
    }
    if (array_key_exists($userId, $charCache)) {
        return $charCache[$userId];
    }

    $cfgQ = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$userId} LIMIT 1");
    $cfg = $db->fetch_array($cfgQ);
    $activeId = $cfg ? (int)$cfg['active_pj_id'] : 0;
    if ($activeId > 0) {
        $check = $db->query("SELECT id FROM {$prefix}game_personajes WHERE id = {$activeId} AND user_id = {$userId} LIMIT 1");
        if ($db->fetch_array($check)) {
            $charCache[$userId] = $activeId;
            return $activeId;
        }
    }

    $fallbackQ = $db->query("
        SELECT id FROM {$prefix}game_personajes
        WHERE user_id = {$userId}
        ORDER BY postnum DESC, id ASC
        LIMIT 1
    ");
    $row = $db->fetch_array($fallbackQ);
    $charCache[$userId] = $row ? (int)$row['id'] : null;
    return $charCache[$userId];
}

function import_system_sender_character_id(): ?int
{
    global $db, $prefix;

    static $cached = null;
    if ($cached !== null) {
        return $cached > 0 ? $cached : null;
    }

    $q = $db->query("
        SELECT id FROM {$prefix}game_personajes
        WHERE is_staff = 1
        ORDER BY staff_level DESC, id ASC
        LIMIT 1
    ");
    $row = $db->fetch_array($q);
    $cached = $row ? (int)$row['id'] : 0;
    return $cached > 0 ? $cached : null;
}

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Importar MD legacy → Buzón</title>
    <link rel='stylesheet' href='{$bburl}/rpg_custom.css'>
</head>
<body class='rpg-admin-pre'>
    <h1>Importar mensajes privados MyBB al Buzón</h1>
    <div class='rpg-admin-log-box'>";

if (!$db->table_exists('game_direct_messages')) {
    $db->write_query("CREATE TABLE IF NOT EXISTS {$prefix}game_direct_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_character_id INT NOT NULL,
        to_character_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        sender_deleted TINYINT(1) NOT NULL DEFAULT 0,
        recipient_deleted TINYINT(1) NOT NULL DEFAULT 0,
        legacy_pmid INT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_legacy_pmid (legacy_pmid),
        INDEX idx_to_char (to_character_id, recipient_deleted, is_read),
        INDEX idx_from_char (from_character_id, sender_deleted),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<div class='rpg-admin-ok'>[OK] Tabla game_direct_messages creada</div>";
} elseif (!$db->field_exists('legacy_pmid', 'game_direct_messages')) {
    $db->write_query("ALTER TABLE {$prefix}game_direct_messages ADD COLUMN legacy_pmid INT DEFAULT NULL");
    $db->write_query("ALTER TABLE {$prefix}game_direct_messages ADD UNIQUE KEY uk_legacy_pmid (legacy_pmid)");
    echo "<div class='rpg-admin-ok'>[OK] Columna legacy_pmid añadida</div>";
}

if (!$db->table_exists('privatemessages')) {
    echo "<div class='rpg-admin-warn'>[SKIP] No existe tabla privatemessages.</div>";
    echo "</div></body></html>";
    exit;
}

$imported = 0;
$skipped = 0;
$errors = 0;

$q = $db->query("
    SELECT pmid, uid, toid, fromid, folder, subject, message, status, dateline
    FROM {$prefix}privatemessages
    WHERE folder IN (0, 1, 2)
      AND subject != ''
      AND message != ''
    ORDER BY pmid ASC, FIELD(folder, 1, 0, 2)
");

$seenPmids = [];

while ($row = $db->fetch_array($q)) {
    $pmid = (int)$row['pmid'];
    if (isset($seenPmids[$pmid])) {
        continue;
    }
    $seenPmids[$pmid] = true;

    $existsQ = $db->query("SELECT id FROM {$prefix}game_direct_messages WHERE legacy_pmid = {$pmid} LIMIT 1");
    if ($db->fetch_array($existsQ)) {
        $skipped++;
        continue;
    }

    $folder = (int)$row['folder'];
    if ($folder === 2) {
        $senderUid = (int)$row['uid'];
        $recipientUid = (int)$row['toid'];
    } else {
        $senderUid = (int)$row['fromid'];
        $recipientUid = (int)$row['uid'];
    }

    if ($recipientUid <= 0) {
        $skipped++;
        continue;
    }

    $fromCharId = $senderUid > 0 ? import_resolve_character_id($senderUid) : import_system_sender_character_id();
    $toCharId = import_resolve_character_id($recipientUid);

    if ($fromCharId === null || $toCharId === null) {
        $skipped++;
        echo "<div class='rpg-admin-warn'>[SKIP] pmid {$pmid}: sin personaje (from uid {$senderUid} → " . ($fromCharId ?? 'null') . ", to uid {$recipientUid} → " . ($toCharId ?? 'null') . ")</div>";
        continue;
    }

    if ($fromCharId === $toCharId) {
        $skipped++;
        continue;
    }

    $subject = trim((string)$row['subject']);
    $body = trim((string)$row['message']);
    if ($subject === '' || $body === '') {
        $skipped++;
        continue;
    }

    if (mb_strlen($subject) > 255) {
        $subject = mb_substr($subject, 0, 252) . '...';
    }

    $isRead = ((int)$row['status'] === 1) ? 1 : 0;
    $dateline = (int)$row['dateline'];
    $createdAt = $dateline > 0 ? date('Y-m-d H:i:s', $dateline) : date('Y-m-d H:i:s');

    $ok = $db->write_query("
        INSERT INTO {$prefix}game_direct_messages
            (from_character_id, to_character_id, subject, body, is_read, legacy_pmid, created_at)
        VALUES (
            {$fromCharId},
            {$toCharId},
            '{$db->escape_string($subject)}',
            '{$db->escape_string($body)}',
            {$isRead},
            {$pmid},
            '{$db->escape_string($createdAt)}'
        )
    ");

    if ($ok) {
        $imported++;
    } else {
        $errors++;
        echo "<div class='rpg-admin-error'>[ERROR] pmid {$pmid}</div>";
    }
}

game_migration_mark_applied('migrate_import_legacy_pms.php');

echo "<div class='rpg-admin-ok'>[DONE] Importados: {$imported} · Omitidos: {$skipped} · Errores: {$errors}</div>";
echo "</div>
    <p class='rpg-admin-info'>Los MD importados no generan notificaciones nuevas. Conservan fecha original y estado leído/no leído. Se asignan al personaje activo o, si no hay, a cualquier ficha del usuario (aprobada o no).</p>
    <a href='{$bburl}/game/public/buzon.php' class='rpg-admin-link'>Ir al Buzón</a>
</body>
</html>";
