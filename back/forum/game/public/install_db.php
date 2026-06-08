<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

game_deny_public_maintenance();
game_require_admin_cp();
// game_require_staff_character(); // Comentado: se ejecuta antes de crear el personaje staff

global $db, $header, $footer;

$prefix = TABLE_PREFIX;
require_once __DIR__ . '/../sql/install_schema_fragments.php';
require_once __DIR__ . '/../sql/migration_helpers.php';

// Helper para ejecutar consultas y mostrar estado
function run_sql(string $sql, string $description): void {
    global $db;
    if ($db->write_query($sql)) {
        echo "<div class='rpg-admin-ok'>[OK] {$description}</div>";
    } else {
        echo "<div class='rpg-admin-error'>[ERROR] {$description}: " . htmlspecialchars($db->error()) . "</div>";
    }
}

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Instalador del Sistema RPG - Base de Datos</title>
    <link rel=\"stylesheet\" href=\"{$mybb->settings['bburl']}/rpg_custom.css\">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #f8fafc; padding: 30px; max-width: 800px; margin: 0 auto; }
        h1 { color: #818cf8; border-bottom: 2px solid #334155; padding-bottom: 10px; }
        .log-container { background: #1e293b; padding: 20px; border-radius: 8px; border: 1px solid #334155; margin-bottom: 20px; }
        .btn { display: inline-block; background: #4f46e5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .btn:hover { background: #4338ca; }
    </style>
</head>
<body>
    <h1>Instalador de Base de Datos del RPG</h1>
    <div class='rpg-admin-pre rpg-admin-log-box'>";

// 1. Eliminar tablas existentes (orden seguro por dependencias)
echo "<h3>Recreando esquema RPG…</h3>";
foreach (game_install_drop_table_order() as $table) {
    run_sql("DROP TABLE IF EXISTS {$prefix}{$table}", "Eliminando {$table}");
}

// 2. Crear tablas (esquema completo alineado con migrate_*.php)
foreach (game_install_create_tables($prefix) as $label => $sql) {
    run_sql($sql, "Creando tabla: {$label}");
}

// 3. Registrar migraciones como aplicadas (instalación limpia = esquema actual)
game_migration_ensure_tracking_table();
foreach (game_migration_ordered_scripts() as $script) {
    game_migration_mark_applied($script);
}
echo "<div class='rpg-admin-ok'>[OK] " . count(game_migration_ordered_scripts()) . " migraciones registradas en game_schema_migrations</div>";

echo "</div>
    <div class='rpg-admin-footer'>
        <p class='rpg-admin-info'>Esquema creado. Sin datos de ejemplo — usa las herramientas del foro o seeds opcionales si los necesitas.</p>
        <a href='zona_staff.php' class='btn'>Ir a Zona Staff</a>
    </div>
</body>
</html>";
