<?php
declare(strict_types=1);

require_once __DIR__ . '/hxh_world_structure.php';
require_once __DIR__ . '/migration_helpers.php';

echo "=== Provision estructura mundial HxH ===\n\n";
game_hxh_provision_world_structure();
game_migration_mark_applied('provision_hxh_world_structure_v1');
echo "\n[OK] Estructura Mundo Conocido provisionada.\n";
