<?php
declare(strict_types=1);

/**
 * Script de diagnóstico para la baraja del personaje "Imu"
 * Se puede ejecutar visitando: http://localhost/Foro/game/public/check_imu_deck.php
 */

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

global $db;
$prefix = TABLE_PREFIX;

ob_start();

echo "=== DIAGNÓSTICO DE PERSONAJE Y DECK ===\n\n";

// 1. Buscar personaje "Imu"
$name = "Imu";
$name_escaped = $db->escape_string($name);
echo "1. Buscando personaje con nombre '{$name}':\n";
$pj_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE name = '{$name_escaped}' LIMIT 1");
if ($db->num_rows($pj_q) === 0) {
    echo "ERROR: No se encontró ningún personaje con nombre '{$name}'.\n";
    
    // Listar todos los personajes para ver si está escrito diferente (ej. minúsculas o espacios)
    echo "\nListando todos los personajes disponibles en la BD:\n";
    $all_pj_q = $db->query("SELECT id, name, user_id FROM {$prefix}game_personajes ORDER BY id ASC");
    while ($p = $db->fetch_array($all_pj_q)) {
        echo " - ID: {$p['id']}, Nombre: '{$p['name']}', User ID: {$p['user_id']}\n";
    }
    $output = ob_get_clean();
    file_put_contents(__DIR__ . '/check_results.txt', $output);
    echo $output;
    exit;
}

$pj = $db->fetch_array($pj_q);
$char_id = (int)$pj['id'];
echo " - Encontrado Personaje: ID: {$pj['id']}, Nombre: '{$pj['name']}', User ID: {$pj['user_id']}, Rango RPG: {$pj['rango']}\n\n";

// 2. Comprobar mazo del personaje
echo "2. Consultando tabla de cartas del personaje (game_character_cards) para char_id = {$char_id}:\n";
$deck_q = $db->query("
    SELECT cc.*, c.name as card_name, c.card_type 
    FROM {$prefix}game_character_cards cc
    JOIN {$prefix}game_cards c ON cc.card_id = c.id
    WHERE cc.character_id = {$char_id}
");

$num_cards = $db->num_rows($deck_q);
echo " - Cartas encontradas: {$num_cards}\n";
if ($num_cards === 0) {
    echo " ADVERTENCIA: Este personaje no tiene ninguna carta asignada en la base de datos.\n";
} else {
    while ($row = $db->fetch_array($deck_q)) {
        echo "   - ID Carta: {$row['card_id']}, Nombre: '{$row['card_name']}', Tipo: {$row['card_type']}, Rango Actual: {$row['current_rank']}\n";
    }
}

// 3. Comprobar si hay configuración activa del personaje para el usuario
$user_id = (int)$pj['user_id'];
echo "\n3. Comprobando configuración activa de usuario (game_user_config) para user_id = {$user_id}:\n";
$cfg_q = $db->query("SELECT * FROM {$prefix}game_user_config WHERE user_id = {$user_id} LIMIT 1");
if ($db->num_rows($cfg_q) === 0) {
    echo " - No hay registro en game_user_config. El usuario no tiene personaje activo.\n";
} else {
    $cfg = $db->fetch_array($cfg_q);
    echo " - Registro activo: User ID: {$cfg['user_id']}, Active PJ ID: {$cfg['active_pj_id']}\n";
    if ((int)$cfg['active_pj_id'] !== $char_id) {
        echo "   ADVERTENCIA: El personaje '{$pj['name']}' (ID {$char_id}) no está seleccionado como el personaje activo (el activo es ID {$cfg['active_pj_id']}).\n";
    }
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";

$output = ob_get_clean();
file_put_contents(__DIR__ . '/check_results.txt', $output);
echo $output;
