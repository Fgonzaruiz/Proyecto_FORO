<?php
require_once __DIR__ . '/../bootstrap.php';
global $db;
$prefix = TABLE_PREFIX;

echo "<h3>Iniciando Migración v2 - Sistema de Personajes (JSON)...</h3>";

// 1. Añadir columnas nuevas si no existen
$columnas = [
    'data_json' => "LONGTEXT",
    'stats_json' => "LONGTEXT",
    'faction' => "VARCHAR(100) DEFAULT ''"
];

foreach ($columnas as $col => $tipo) {
    if (!$db->field_exists($col, "game_personajes")) {
        $db->write_query("ALTER TABLE {$prefix}game_personajes ADD {$col} {$tipo}");
        echo "<p>Columna <b>{$col}</b> añadida.</p>";
    } else {
        echo "<p>Columna <b>{$col}</b> ya existía.</p>";
    }
}

// 2. Eliminar columnas viejas de stats si existen
$viejas = ['stat_fp', 'stat_dp', 'stat_rp', 'stat_ip', 'stat_vp', 'stat_hp'];
foreach ($viejas as $col) {
    if ($db->field_exists($col, "game_personajes")) {
        $db->write_query("ALTER TABLE {$prefix}game_personajes DROP COLUMN {$col}");
        echo "<p>Columna <b>{$col}</b> eliminada.</p>";
    }
}

echo "<h3>¡Migración completada!</h3>";
