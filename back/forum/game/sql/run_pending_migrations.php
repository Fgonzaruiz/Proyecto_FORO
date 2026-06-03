<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/migration_helpers.php';

game_require_admin_cp();
game_migration_ensure_tracking_table();

$dir = __DIR__;
$scripts = game_migration_ordered_scripts();

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Run pending migrations</title>
<style>body{font-family:system-ui;background:#0f172a;color:#f8fafc;padding:30px;max-width:900px;margin:0 auto}
h2{color:#818cf8;margin-top:28px}.skip{color:#94a3b8}.ok{color:#10b981}.run{color:#fbbf24}
section{border:1px solid #334155;border-radius:8px;padding:16px;margin:12px 0;background:#1e293b}
</style></head><body><h1>Migraciones pendientes</h1>";

foreach ($scripts as $script) {
    $path = $dir . '/' . $script;
    echo '<section><h2>' . htmlspecialchars($script) . '</h2>';

    if (!is_file($path)) {
        echo '<p class="skip">[SKIP] Archivo no encontrado.</p></section>';
        continue;
    }

    if (game_migration_applied($script)) {
        echo '<p class="skip">[SKIP] Ya aplicada.</p></section>';
        continue;
    }

    echo '<p class="run">[RUN] Ejecutando…</p>';
    ob_start();
    include $path;
    $output = ob_get_clean();
    echo $output;
    game_migration_mark_applied($script);
    echo '<p class="ok">[OK] Registrada en game_schema_migrations.</p></section>';
}

echo '<p><strong>Fin.</strong></p></body></html>';
