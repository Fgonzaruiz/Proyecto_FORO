<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/migration_helpers.php';

game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;
$migrationName = 'migrate_hxh_initial.php';

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración Inicial Hunter × Hunter (Fases 0, 1 y 2) ===\n\n";

if (game_migration_applied($migrationName)) {
    echo "[--] Ya aplicada.\n</pre>";
    exit;
}

// 1. Eliminar tablas OP
$tables_to_drop = [
    'game_akuma_no_mi',
    'game_haki_progress',
    'game_navigation_routes',
    'game_navigation_voyages',
    'game_navigation_events',
    'game_wanted',
    'game_estilos_canonicos'
];

foreach ($tables_to_drop as $tbl) {
    if ($db->table_exists($tbl)) {
        $db->write_query("DROP TABLE {$prefix}{$tbl}");
        echo "[OK] Tabla eliminada: {$tbl}\n";
    } else {
        echo "[--] Tabla no existe (omitida): {$tbl}\n";
    }
}

// 2. Modificar tablas existentes
// A) Moneda en personajes
if ($db->field_exists('berries', 'game_personajes')) {
    $db->write_query("ALTER TABLE {$prefix}game_personajes CHANGE berries jenny INT NOT NULL DEFAULT 0");
    echo "[OK] Renombrada columna 'berries' a 'jenny' en game_personajes\n";
} else {
    echo "[--] Columna 'berries' ya renombrada o no existe en game_personajes\n";
}

// B) Moneda en cartas
if ($db->field_exists('cost_berries', 'game_cards')) {
    $db->write_query("ALTER TABLE {$prefix}game_cards CHANGE cost_berries cost_jenny INT NOT NULL DEFAULT 0");
    echo "[OK] Renombrada columna 'cost_berries' a 'cost_jenny' en game_cards\n";
} else {
    echo "[--] Columna 'cost_berries' ya renombrada o no existe en game_cards\n";
}

// C) Tipo de cartas (reducir ENUM para HxH)
// Primero, limpiamos/cambiamos cualquier carta que tuviera tipos eliminados a 'tecnica' para evitar errores de truncado
$db->write_query("UPDATE {$prefix}game_cards SET card_type = 'tecnica' WHERE card_type IN ('akuma_no_mi', 'haki', 'barco')");
$db->write_query("ALTER TABLE {$prefix}game_cards MODIFY COLUMN card_type ENUM('tecnica', 'equipo', 'npc_menor', 'objeto', 'consumible') NOT NULL DEFAULT 'tecnica'");
echo "[OK] Modificado ENUM card_type en game_cards\n";

// D) Slots de inventario
// Cambiar el tipo de slot para remover 'barco'
$db->write_query("DELETE FROM {$prefix}game_character_inventory WHERE slot_type = 'barco'");
$db->write_query("ALTER TABLE {$prefix}game_character_inventory MODIFY COLUMN slot_type ENUM('carga', 'companero') NOT NULL");
echo "[OK] Modificado ENUM slot_type en game_character_inventory\n";

// E) Tripulaciones (remover campos barco)
$cols_to_drop_crews = ['ship_name', 'ship_image_url', 'ship_data'];
foreach ($cols_to_drop_crews as $col) {
    if ($db->field_exists($col, 'game_tripulaciones')) {
        $db->write_query("ALTER TABLE {$prefix}game_tripulaciones DROP COLUMN {$col}");
        echo "[OK] Columna eliminada en game_tripulaciones: {$col}\n";
    }
}

// F) Islas / Ubicaciones (remover campos náuticos y agregar campos HxH)
$cols_to_drop_islands = ['sea_zone', 'requires_log_pose', 'requires_compass'];
foreach ($cols_to_drop_islands as $col) {
    if ($db->field_exists($col, 'game_forum_islands')) {
        $db->write_query("ALTER TABLE {$prefix}game_forum_islands DROP COLUMN {$col}");
        echo "[OK] Columna eliminada en game_forum_islands: {$col}\n";
    }
}

if (!$db->field_exists('region_slug', 'game_forum_islands')) {
    $db->write_query("ALTER TABLE {$prefix}game_forum_islands ADD COLUMN region_slug VARCHAR(64) DEFAULT NULL AFTER fid");
    echo "[OK] Columna agregada en game_forum_islands: region_slug\n";
}
if (!$db->field_exists('country', 'game_forum_islands')) {
    $db->write_query("ALTER TABLE {$prefix}game_forum_islands ADD COLUMN country VARCHAR(128) DEFAULT NULL AFTER region_slug");
    echo "[OK] Columna agregada en game_forum_islands: country\n";
}
if (!$db->field_exists('travel_difficulty', 'game_forum_islands')) {
    $db->write_query("ALTER TABLE {$prefix}game_forum_islands ADD COLUMN travel_difficulty TINYINT DEFAULT 1 COMMENT '1=facil, 5=Dark Continent' AFTER country");
    echo "[OK] Columna agregada en game_forum_islands: travel_difficulty\n";
}

// 3. Crear tablas del Sistema Nen
$db->write_query("CREATE TABLE IF NOT EXISTS {$prefix}game_nen (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id INT UNSIGNED NOT NULL UNIQUE,
  nen_type ENUM('enhancement','transmutation','emission','conjuration','manipulation','specialization') DEFAULT NULL,
  nen_type_locked TINYINT(1) DEFAULT 0,
  aura_color VARCHAR(32) DEFAULT NULL,
  vows_json JSON DEFAULT NULL,
  notes TEXT,
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  KEY idx_type (nen_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "[OK] Tabla creada o verificada: game_nen\n";

$db->write_query("CREATE TABLE IF NOT EXISTS {$prefix}game_nen_progress (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id INT UNSIGNED NOT NULL,
  principle ENUM('ten','zetsu','ren','hatsu') NOT NULL,
  level TINYINT UNSIGNED DEFAULT 0 COMMENT '0=sin entrenar, 1=basico, 2=intermedio, 3=avanzado, 4=maestria',
  experience INT UNSIGNED DEFAULT 0,
  unlocked_at INT UNSIGNED DEFAULT NULL,
  UNIQUE KEY uq_char_principle (character_id, principle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "[OK] Tabla creada o verificada: game_nen_progress\n";

$db->write_query("CREATE TABLE IF NOT EXISTS {$prefix}game_nen_abilities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id INT UNSIGNED NOT NULL,
  name VARCHAR(128) NOT NULL,
  description TEXT,
  `rank` ENUM('D','C','B','A','S','SS') DEFAULT 'D',
  nen_cost INT DEFAULT 0,
  conditions_json JSON DEFAULT NULL,
  card_id INT UNSIGNED DEFAULT NULL,
  approved TINYINT(1) DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_character (character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "[OK] Tabla creada o verificada: game_nen_abilities\n";

game_migration_mark_applied($migrationName);
echo "\n[OK] Migración finalizada y registrada.\n";
echo "=== Fin ===\n</pre>";
