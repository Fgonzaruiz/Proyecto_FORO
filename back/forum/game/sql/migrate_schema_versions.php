<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/migration_helpers.php';

game_require_admin_cp();

game_migration_ensure_tracking_table();

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Schema migrations table</title>
<style>body{font-family:system-ui;background:#0f172a;color:#f8fafc;padding:30px;max-width:640px;margin:0 auto}
.ok{color:#10b981}</style></head><body>";
echo "<h1>Tabla de versionado</h1>";
echo "<p class='ok'>[OK] <code>game_schema_migrations</code> lista.</p>";
echo "<p><a href='run_pending_migrations.php'>Ejecutar migraciones pendientes</a></p>";
echo "</body></html>";
