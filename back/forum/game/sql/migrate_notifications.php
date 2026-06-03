<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer;

if ((int)($mybb->user['uid'] ?? 0) === 0 || (int)($mybb->usergroup['cancp'] ?? 0) !== 1) {
    error_no_permission();
}
game_require_staff_character();

$prefix = TABLE_PREFIX;
$bburl = $mybb->settings['bburl'];

function run_sql(string $sql, string $description): void {
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
    <title>Migración - Notificaciones</title>
    <link rel='stylesheet' href='{$bburl}/rpg_custom.css'>
</head>
<body class='rpg-admin-pre'>
    <h1>Migración - Sistema de Notificaciones</h1>
    <div class='rpg-admin-log-box'>";

run_sql("CREATE TABLE IF NOT EXISTS {$prefix}game_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    character_id INT DEFAULT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'system',
    title VARCHAR(255) NOT NULL,
    body TEXT,
    link VARCHAR(500) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_dismissed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "Creando tabla {$prefix}game_notifications");

echo "</div>
    <p class='rpg-admin-info'>Migración completada.</p>
    <a href='{$bburl}/game/public/notificaciones.php' class='rpg-admin-link'>Ir a Notificaciones</a>
</body>
</html>";
