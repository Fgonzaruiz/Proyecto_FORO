<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if ((int)($mybb->user['uid'] ?? 0) === 0 || (int)($mybb->usergroup['cancp'] ?? 0) !== 1) {
    error_no_permission();
}
game_require_staff_character();

$prefix = TABLE_PREFIX;

function run_sql(string $sql, string $description): void {
    global $db;
    if ($db->write_query($sql)) {
        echo "<div style='color: #10b981;'>[OK] {$description}</div>";
    } else {
        echo "<div style='color: #ef4444;'>[ERROR] {$description}</div>";
    }
}

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Migración Aprobar PJ</title>
<style>body{font-family:monospace;background:#0f172a;color:#f8fafc;padding:20px;max-width:800px;margin:0 auto;}h1{color:#818cf8;}</style>
</head><body><h1>Migración: Sistema de Aprobación de Personajes</h1><div style='background:#1e293b;padding:20px;border-radius:8px;'>";

// 1. Add status column
$check = $db->query("SHOW COLUMNS FROM {$prefix}game_personajes LIKE 'status'");
if (!$db->num_rows($check)) {
    run_sql(
        "ALTER TABLE {$prefix}game_personajes ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pendiente' AFTER staff_level",
        "Columna 'status' añadida a game_personajes"
    );
} else {
    echo "<div style='color:#fbbf24;'>[OK] Columna 'status' ya existe</div>";
}

// 2. Migrate existing approved values to status
run_sql(
    "UPDATE {$prefix}game_personajes SET status = 'aprobada' WHERE approved = 1 AND status = 'pendiente'",
    "Personajes con approved=1 migrados a status='aprobada'"
);
run_sql(
    "UPDATE {$prefix}game_personajes SET status = 'pendiente' WHERE approved = 0 AND status = 'pendiente'",
    "Personajes con approved=0 migrados a status='pendiente'"
);

// 3. Create game_personajes_revisiones table
run_sql(
    "CREATE TABLE IF NOT EXISTS {$prefix}game_personajes_revisiones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        personaje_id INT NOT NULL,
        staff_user_id INT NOT NULL,
        staff_char_id INT NOT NULL,
        status_anterior VARCHAR(20) NOT NULL DEFAULT '',
        status_nuevo VARCHAR(20) NOT NULL DEFAULT '',
        mensaje TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_personaje (personaje_id),
        INDEX idx_staff (staff_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "Tabla 'game_personajes_revisiones' creada"
);

echo "</div><br><a href='../public/zona_staff_aprobar.php' style='color:#818cf8;'>Ir a Aprobar Personajes</a></body></html>";
