<?php
declare(strict_types=1);

/**
 * Provisiona foros MyBB de ubicaciones HxH y sincroniza game_forum_islands.
 * Idempotente: crea foros faltantes bajo la categoría genérica del índice.
 */

require_once __DIR__ . '/hxh_location_provision.php';

echo "=== Provision foros HxH ===\n\n";
game_hxh_provision_all_locations();
echo "\n[OK] Provision finalizada.\n";
