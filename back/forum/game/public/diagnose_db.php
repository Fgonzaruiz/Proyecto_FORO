<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
global $db;

header('Content-Type: text/plain; charset=utf-8');

$prefix = TABLE_PREFIX;

echo "=== DIAGNOSTICO DE BASE DE DATOS RPG ===\n\n";

$tables = [
    'game_personajes',
    'game_user_config',
    'game_post_characters',
    'game_personajes_revisiones',
    'game_cards',
    'game_character_cards'
];

foreach ($tables as $t) {
    $fullName = $prefix . $t;
    $check = $db->query("SHOW TABLES LIKE '{$fullName}'");
    if ($db->num_rows($check)) {
        echo "[OK] Tabla {$fullName} existe.\n";
        // Show columns
        $cols = $db->query("SHOW COLUMNS FROM {$fullName}");
        while ($c = $db->fetch_array($cols)) {
            echo "   - {$c['Field']} ({$c['Type']})\n";
        }
    } else {
        echo "[ERROR] Tabla {$fullName} NO existe.\n";
    }
    echo "\n";
}

echo "=== COMPROBANDO CONSULTAS ===\n\n";

$where = "status != 'aprobada'";
$query = "SELECT p.id, p.user_id, p.name, p.status, p.avatar, p.rango, p.faction, p.race_name,
                 p.occupation_name, u.username
          FROM {$prefix}game_personajes p
          LEFT JOIN {$prefix}users u ON u.uid = p.user_id
          WHERE {$where}
          LIMIT 1";

echo "Running: $query\n";
try {
    $res = $db->query($query);
    if ($res) {
        echo "[OK] Consulta de listado exitosa.\n";
    } else {
        echo "[ERROR] Consulta falló (retornó false).\n";
    }
} catch (Throwable $e) {
    echo "[EXCEPCION] " . $e->getMessage() . "\n";
}
