<?php
declare(strict_types=1);

/**
 * Seed de Ubicaciones para Hunter x Hunter (HxH).
 * Provisiona foros faltantes y puebla metadatos en game_forum_islands.
 */

require_once __DIR__ . '/hxh_location_provision.php';

echo "=== Seeding Hunter x Hunter Locations ===\n\n";
game_hxh_provision_all_locations();
echo "\n[OK] Semilla de HxH finalizada.\n";
