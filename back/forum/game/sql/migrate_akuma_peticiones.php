<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;

echo '<h2>Migración: Akuma no Mi (peticiones) + peticiones administrativas</h2>';

if ($db->table_exists('game_akuma_no_mi')) {
    if (!$db->field_exists('is_occupied', 'game_akuma_no_mi')) {
        $db->write_query("ALTER TABLE {$prefix}game_akuma_no_mi ADD COLUMN is_occupied TINYINT(1) NOT NULL DEFAULT 0");
        echo "<p class='rpg-admin-ok'>[OK] Columna is_occupied añadida.</p>";
    } else {
        echo "<p class='rpg-admin-warn'>[INFO] is_occupied ya existe.</p>";
    }

    if (!$db->field_exists('power_range', 'game_akuma_no_mi')) {
        $db->write_query("ALTER TABLE {$prefix}game_akuma_no_mi ADD COLUMN power_range VARCHAR(32) NOT NULL DEFAULT 'Sin asignar'");
        echo "<p class='rpg-admin-ok'>[OK] Columna power_range añadida.</p>";
    } else {
        echo "<p class='rpg-admin-warn'>[INFO] power_range ya existe.</p>";
    }

    if (!$db->field_exists('is_reserved', 'game_akuma_no_mi')) {
        $db->write_query("ALTER TABLE {$prefix}game_akuma_no_mi ADD COLUMN is_reserved TINYINT(1) NOT NULL DEFAULT 0");
        echo "<p class='rpg-admin-ok'>[OK] Columna is_reserved añadida.</p>";
    } else {
        echo "<p class='rpg-admin-warn'>[INFO] is_reserved ya existe.</p>";
    }

    $db->write_query("UPDATE {$prefix}game_akuma_no_mi SET is_occupied = 1, is_reserved = 0 WHERE status != 'disponible'");
    $db->write_query("UPDATE {$prefix}game_akuma_no_mi SET is_occupied = 0 WHERE status = 'disponible' AND is_occupied = 0");
    echo "<p class='rpg-admin-ok'>[OK] is_occupied sincronizado con status.</p>";

    $seed = [
        ['Yuki Yuki no Mi (Prueba)', 'logia', 'Logia', 'disponible', 'Disponible (Libre)', 'Fruta de nieve de prueba para tiradas.', 'Control del invierno y creación de copos destructivos.', 'Logia elemental', 'Ninguno (Libre)', 'Congelación progresiva', 'Rango B', 'images/game/akuma_banner.png', 'Rango B'],
        ['Ushi Ushi no Mi: Modelo Bisonte (Prueba)', 'zoan', 'Zoan', 'disponible', 'Disponible (Libre)', 'Zoan que otorga fuerza bruta y cuernos reforzados.', 'Transformación híbrida ideal para combate cuerpo a cuerpo.', 'Zoan clásica', 'Ninguno (Libre)', 'Carga devastadora', 'Rango C', 'images/game/akuma_banner.png', 'Rango C'],
        ['Baku Baku no Mi (Prueba)', 'paramecia', 'Paramecia', 'disponible', 'Disponible (Libre)', 'Permite devorar y asimilar materiales para transformar el cuerpo.', 'Versátil en creatividad de combate y utilidad.', 'Paramecia', 'Ninguno (Libre)', 'Asimilación de materiales', 'Rango D', 'images/game/akuma_banner.png', 'Rango D'],
        ['Pika Pika no Mi (Prueba Reservada)', 'logia', 'Logia', 'disponible', 'Disponible (Libre)', 'Logia de luz para pruebas de reserva manual.', 'Velocidad extrema y ataques de fotones.', 'Logia elemental', 'Ninguno (Libre)', 'Desplazamiento lumínico', 'Rango S', 'images/game/akuma_banner.png', 'Rango S'],
    ];
    foreach ($seed as $row) {
        $nameEsc = $db->escape_string($row[0]);
        $exists = $db->fetch_array($db->query("SELECT id FROM {$prefix}game_akuma_no_mi WHERE name = '{$nameEsc}' LIMIT 1"));
        if ($exists) {
            continue;
        }
        $db->write_query("INSERT INTO {$prefix}game_akuma_no_mi (name, class, class_name, status, status_name, `desc`, details, tipo_fruta, usuario_actual, habilidad_clave, precio, banner, power_range, is_occupied, is_reserved) VALUES (
            '{$nameEsc}',
            '" . $db->escape_string($row[1]) . "',
            '" . $db->escape_string($row[2]) . "',
            '" . $db->escape_string($row[3]) . "',
            '" . $db->escape_string($row[4]) . "',
            '" . $db->escape_string($row[5]) . "',
            '" . $db->escape_string($row[6]) . "',
            '" . $db->escape_string($row[7]) . "',
            '" . $db->escape_string($row[8]) . "',
            '" . $db->escape_string($row[9]) . "',
            '" . $db->escape_string($row[10]) . "',
            '" . $db->escape_string($row[11]) . "',
            '" . $db->escape_string($row[12]) . "',
            0, 0
        )");
    }
    echo "<p class='rpg-admin-ok'>[OK] Frutas de prueba insertadas (si no existían).</p>";
} else {
    echo "<p class='rpg-admin-warn'>[WARN] Tabla game_akuma_no_mi no existe. Ejecuta install_db o crea la biblioteca primero.</p>";
}

if (!$db->table_exists('game_admin_requests')) {
    $db->write_query("
    CREATE TABLE {$prefix}game_admin_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        character_id INT NOT NULL,
        source VARCHAR(32) NOT NULL,
        request_kind VARCHAR(64) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        link VARCHAR(500) DEFAULT NULL,
        payload_json TEXT DEFAULT NULL,
        akuma_fruit_id INT DEFAULT NULL,
        status ENUM('pendiente','aprobada','denegada') NOT NULL DEFAULT 'pendiente',
        staff_nota TEXT DEFAULT NULL,
        staff_user_id INT DEFAULT NULL,
        staff_char_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_character (character_id),
        INDEX idx_akuma (akuma_fruit_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p class='rpg-admin-ok'>[OK] Tabla game_admin_requests creada.</p>";
} else {
    echo "<p class='rpg-admin-warn'>[INFO] game_admin_requests ya existe.</p>";
}

echo '<p>Migración completada.</p>';
