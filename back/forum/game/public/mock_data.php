<?php
require_once __DIR__ . '/../bootstrap.php';
global $db;
$prefix = TABLE_PREFIX;

echo "<h3>Poblando datos de prueba para Kazan e Imu...</h3>";

$cronologia_kazan = [
    'diario' => [
        [
            'id' => uniqid(),
            'date' => '20/05/2026',
            'link' => '#',
            'desc' => 'Llegada a la isla. El ambiente es tenso, pero el objetivo está claro. El primer contacto fue hostil.'
        ],
        [
            'id' => uniqid(),
            'date' => '22/05/2026',
            'link' => '#',
            'desc' => 'Encuentro con la resistencia local. Prometieron ayuda a cambio de liberar a sus prisioneros.'
        ]
    ],
    'relaciones' => [
        [
            'id' => uniqid(),
            'name' => 'Monkey D. Luffy',
            'relation' => 'Rival Respetado',
            'image' => 'https://i.pinimg.com/736x/87/b9/eb/87b9ebb7478d5e1b219fb0a2ea9c7d42.jpg',
            'link' => '#'
        ],
        [
            'id' => uniqid(),
            'name' => 'Zoro',
            'relation' => 'Compañero de Armas',
            'image' => 'https://i.pinimg.com/736x/6f/30/4e/6f304e2865d4b53ce70c2e399bdba118.jpg',
            'link' => '#'
        ]
    ]
];

$cronologia_imu = [
    'diario' => [
        [
            'id' => uniqid(),
            'date' => 'Hace 800 años',
            'link' => '#',
            'desc' => 'La gran guerra ha terminado. El trono vacío no está tan vacío después de todo.'
        ],
        [
            'id' => uniqid(),
            'date' => 'Día de la Reverie',
            'link' => '#',
            'desc' => 'Una luz debe ser borrada de la historia nuevamente.'
        ]
    ],
    'relaciones' => [
        [
            'id' => uniqid(),
            'name' => 'Joy Boy',
            'relation' => 'Archienemigo',
            'image' => 'https://i.pinimg.com/736x/5f/57/58/5f5758cd0dc6f57161b9d4c2084c6c0e.jpg',
            'link' => '#'
        ],
        [
            'id' => uniqid(),
            'name' => 'Gorosei',
            'relation' => 'Sirvientes Leales',
            'image' => 'https://i.pinimg.com/736x/c8/1d/15/c81d15c8e2f89c0f0d3a5a8f4df0f720.jpg',
            'link' => '#'
        ]
    ]
];

$json_kazan = $db->escape_string(json_encode($cronologia_kazan, JSON_UNESCAPED_UNICODE));
$json_imu = $db->escape_string(json_encode($cronologia_imu, JSON_UNESCAPED_UNICODE));

$db->write_query("UPDATE {$prefix}game_personajes SET cronologia_json = '{$json_kazan}' WHERE name LIKE '%Kazan%'");
$rows_kazan = $db->affected_rows();
echo "<p>Kazan actualizado: {$rows_kazan} filas afectadas.</p>";

$db->write_query("UPDATE {$prefix}game_personajes SET cronologia_json = '{$json_imu}' WHERE name LIKE '%Imu%'");
$rows_imu = $db->affected_rows();
echo "<p>Imu actualizado: {$rows_imu} filas afectadas.</p>";

echo "<h3>Completado!</h3>";
