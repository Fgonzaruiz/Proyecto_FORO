<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if ((int)($mybb->user['uid'] ?? 0) === 0 || (int)($mybb->usergroup['cancp'] ?? 0) !== 1) {
    error_no_permission();
}

$prefix = TABLE_PREFIX;
$bburl = $mybb->settings['bburl'];

function dm_run_sql(string $sql, string $description): void {
    global $db;
    if ($db->write_query($sql)) {
        echo "<div class='rpg-admin-ok'>[OK] {$description}</div>";
    } else {
        echo "<div class='rpg-admin-error'>[ERROR] {$description}</div>";
    }
}

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Migración - Mensajes Directos por Personaje</title>
    <link rel='stylesheet' href='{$bburl}/rpg_custom.css'>
</head>
<body class='rpg-admin-pre'>
    <h1>Migración - Buzón (MD por personaje)</h1>
    <div class='rpg-admin-log-box'>";

dm_run_sql("CREATE TABLE IF NOT EXISTS {$prefix}game_direct_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_character_id INT NOT NULL,
    to_character_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    sender_deleted TINYINT(1) NOT NULL DEFAULT 0,
    recipient_deleted TINYINT(1) NOT NULL DEFAULT 0,
    legacy_pmid INT DEFAULT NULL,
    thread_id INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_legacy_pmid (legacy_pmid),
    INDEX idx_to_char (to_character_id, recipient_deleted, is_read),
    INDEX idx_from_char (from_character_id, sender_deleted),
    INDEX idx_thread (thread_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "Creando tabla {$prefix}game_direct_messages");

if ($db->table_exists('game_direct_messages') && !$db->field_exists('thread_id', 'game_direct_messages')) {
    dm_run_sql("ALTER TABLE {$prefix}game_direct_messages ADD COLUMN thread_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER recipient_deleted", "Añadiendo columna thread_id");
    dm_run_sql("UPDATE {$prefix}game_direct_messages SET thread_id = id WHERE thread_id = 0", "Inicializando thread_id en mensajes existentes");
    dm_run_sql("ALTER TABLE {$prefix}game_direct_messages ADD INDEX idx_thread (thread_id)", "Índice idx_thread");
}

echo "</div>
    <p class='rpg-admin-info'>Migración completada.</p>
    <a href='{$bburl}/game/public/buzon.php' class='rpg-admin-link'>Ir al Buzón</a>
</body>
</html>";
