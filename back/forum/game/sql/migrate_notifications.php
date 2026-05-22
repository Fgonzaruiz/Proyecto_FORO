<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer;

if ((int)($mybb->user['uid'] ?? 0) === 0 || (int)($mybb->usergroup['cancp'] ?? 0) !== 1) {
    error_no_permission();
}
game_require_staff_character();

$prefix = TABLE_PREFIX;

function run_sql(string $sql, string $description): void {
    global $db;
    if ($db->write_query($sql)) {
        echo "<div style='color: #10b981; font-family: monospace; margin: 4px 0;'>[OK] {$description}</div>";
    } else {
        echo "<div style='color: #ef4444; font-family: monospace; margin: 4px 0;'>[ERROR] {$description}</div>";
    }
}

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Migración - Notificaciones</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #f8fafc; padding: 30px; max-width: 800px; margin: 0 auto; }
        h1 { color: #818cf8; border-bottom: 2px solid #334155; padding-bottom: 10px; }
        .log-container { background: #1e293b; padding: 20px; border-radius: 8px; border: 1px solid #334155; margin-bottom: 20px; }
        .back { display: inline-block; background: #4f46e5; color: white; padding: 8px 16px; text-decoration: none; border-radius: 6px; margin-top: 10px; }
    </style>
</head>
<body>
    <h1>Migración - Sistema de Notificaciones</h1>
    <div class='log-container'>";

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "Creando tabla {$prefix}game_notifications");

echo "</div>
    <p style='color: #94a3b8;'>Migración completada.</p>
    <a href='{$mybb->settings['bburl']}/game/public/notificaciones.php' class='back'>Ir a Notificaciones</a>
</body>
</html>";
