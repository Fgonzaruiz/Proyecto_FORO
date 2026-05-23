<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if ((int)($mybb->user['uid'] ?? 0) === 0 || (int)($mybb->usergroup['cancp'] ?? 0) !== 1) {
    http_response_code(403);
    echo 'Acceso denegado';
    exit;
}
game_require_staff_character();

$prefix = TABLE_PREFIX;
$ok = true;

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Migración - Thread Meta</title>
<style>body{font-family:system-ui;background:#0f172a;color:#f8fafc;padding:30px;max-width:600px;margin:0 auto}
h1{color:#818cf8}.ok{color:#10b981}.err{color:#ef4444}</style></head><body>
<h1>Migración: Tipos y Fechas de Hilos</h1>";

try {
    $db->write_query("CREATE TABLE IF NOT EXISTS {$prefix}game_thread_meta (
        thread_id INT PRIMARY KEY,
        thread_type VARCHAR(20) NOT NULL DEFAULT 'Presente',
        day INT NOT NULL DEFAULT 1,
        season INT NOT NULL DEFAULT 0,
        year INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p class='ok'>[OK] Tabla game_thread_meta creada</p>";
} catch (Throwable $e) {
    echo "<p class='err'>[ERROR] " . htmlspecialchars($e->getMessage()) . "</p>";
    $ok = false;
}

if ($ok) {
    echo "<p style='color:#34d399;font-size:18px;margin-top:20px;'>&#10003; Migración completada</p>";
} else {
    echo "<p style='color:#ef4444;font-size:18px;margin-top:20px;'>&#10007; Hubo errores</p>";
}

echo "</body></html>";
