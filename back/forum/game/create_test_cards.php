<?php
declare(strict_types=1);

/**
 * Script para crear cartas de prueba en el sistema RPG.
 * Ejecutar vía terminal: php back/forum/game/create_test_cards.php
 */

require_once __DIR__ . '/bootstrap.php';

global $db;
$prefix = TABLE_PREFIX;

echo "Inicializando creación de cartas de prueba...\n";

// Definición de las cartas de prueba
$test_cards = [
    // 1. Técnica que usa munición y escalado de DES
    [
        'name' => 'Disparo Certero',
        'card_type' => 'tecnica',
        'rank' => 'B',
        'activation' => 'activa',
        'tags_json' => json_encode(['ACTIVA', 'DISTANCIA LARGA', 'OFENSIVA', 'EJECUCIÓN: DES'], JSON_UNESCAPED_UNICODE),
        'description' => 'Un disparo preciso que utiliza la munición cargada para infligir daño adicional. Requiere equipar un arma de fuego y consume munición.',
        'cost_pe' => '15',
        'execution_stat' => 'DES',
        'dice' => '1d8 + [MUNICION] + DES',
        'effects_json' => json_encode([], JSON_UNESCAPED_UNICODE),
        'upgrade_json' => json_encode([], JSON_UNESCAPED_UNICODE),
        'notes' => 'Carta de técnica de prueba que demuestra el uso de tags dinámicos de munición.',
        'image_url' => '',
        'reposo' => 2,
        'duracion' => 0,
        'created_by' => 1
    ],
    // 2. Equipo Útil: Munición (daño dice = 1d4)
    [
        'name' => 'Bala de Punta Hueca',
        'card_type' => 'equipo',
        'rank' => 'C',
        'activation' => 'pasiva',
        'tags_json' => json_encode(['CONSUMIBLE', 'MUNICION'], JSON_UNESCAPED_UNICODE),
        'description' => 'Munición especial expansiva. Añade un dado de daño a las técnicas de tipo disparo que la consuman.',
        'cost_pe' => '—',
        'execution_stat' => '',
        'dice' => '1d4', // Dado de daño que se sumará a la técnica
        'effects_json' => json_encode([
            'equipo_type' => 'util',
            'subtipo' => 'municion',
            'damage_dice' => '',
            'damage_stat' => ''
        ], JSON_UNESCAPED_UNICODE),
        'upgrade_json' => json_encode([], JSON_UNESCAPED_UNICODE),
        'notes' => 'Carta de munición de prueba.',
        'image_url' => '',
        'reposo' => 0,
        'duracion' => 0,
        'created_by' => 1
    ],
    // 3. Equipo Arma: Mosquete de Chispas (dado = 1d10)
    [
        'name' => 'Mosquete de Chispas',
        'card_type' => 'equipo',
        'rank' => 'B',
        'activation' => 'pasiva',
        'tags_json' => json_encode(['ARMA', 'RIPLE'], JSON_UNESCAPED_UNICODE),
        'description' => 'Arma de fuego clásica de largo alcance que proporciona un tiro firme.',
        'cost_pe' => '—',
        'execution_stat' => 'DES',
        'dice' => '1d10',
        'effects_json' => json_encode([
            'equipo_type' => 'arma',
            'subtipo' => 'rifle',
            'damage_dice' => '',
            'damage_stat' => ''
        ], JSON_UNESCAPED_UNICODE),
        'upgrade_json' => json_encode([], JSON_UNESCAPED_UNICODE),
        'notes' => 'Carta de arma de prueba.',
        'image_url' => '',
        'reposo' => 0,
        'duracion' => 0,
        'created_by' => 1
    ],
    // 4. Akuma no mi (Zoan)
    [
        'name' => 'Fruta Ushi Ushi: Modelo Bisonte',
        'card_type' => 'akuma_no_mi',
        'rank' => 'B',
        'activation' => 'pasiva',
        'tags_json' => json_encode(['ZOAN'], JSON_UNESCAPED_UNICODE),
        'description' => 'Permite transformarse en bisonte y en forma híbrida, ganando gran fuerza y resistencia física.',
        'cost_pe' => '—',
        'execution_stat' => '',
        'dice' => '',
        'effects_json' => json_encode([
            'akuma_type' => 'zoan',
            'efectos' => 'Aumenta la fuerza física y resistencia. Permite embestidas devastadoras.',
            'limitaciones' => 'Requiere espacio abierto para embestir a máxima potencia.',
            'debilidades' => 'Agua de mar y Kairoseki.'
        ], JSON_UNESCAPED_UNICODE),
        'upgrade_json' => json_encode([], JSON_UNESCAPED_UNICODE),
        'notes' => 'Carta Akuma Zoan de prueba.',
        'image_url' => '',
        'reposo' => 0,
        'duracion' => 0,
        'created_by' => 1
    ],
    // 5. Haki (Busoshoku)
    [
        'name' => 'Koka (Endurecimiento)',
        'card_type' => 'haki',
        'rank' => 'A',
        'activation' => 'activa',
        'tags_json' => json_encode(['HAKI ARMAMENTO', 'CONTINUA', 'DEFENSIVA'], JSON_UNESCAPED_UNICODE),
        'description' => 'Cubre partes del cuerpo con una armadura espiritual negra brillante como la obsidiana.',
        'cost_pe' => '10',
        'execution_stat' => '',
        'dice' => '',
        'effects_json' => json_encode([
            'haki_type' => 'busoshoku',
            'haki_level' => 'medio',
            'efecto' => '+5 a la reducción de daño físico recibido y añade daño extra en golpes cuerpo a cuerpo.'
        ], JSON_UNESCAPED_UNICODE),
        'upgrade_json' => json_encode([], JSON_UNESCAPED_UNICODE),
        'notes' => 'Carta de Haki de prueba.',
        'image_url' => '',
        'reposo' => 3,
        'duracion' => 2,
        'created_by' => 1
    ],
    // 6. NPC Menor (NPC con acciones dinámicas)
    [
        'name' => 'Pirata Recluta',
        'card_type' => 'npc_menor',
        'rank' => 'C',
        'activation' => 'activa',
        'tags_json' => json_encode(['PIRATA', 'ALIADO TEMPORAL'], JSON_UNESCAPED_UNICODE),
        'description' => 'Un miembro raso de la tripulación listo para recibir órdenes y pelear de tu lado.',
        'cost_pe' => '20',
        'execution_stat' => '',
        'dice' => '',
        'effects_json' => json_encode([
            'npc_mascota_type' => 'npc',
            'vida' => 40,
            'tier' => 1,
            'acciones' => [
                'Disparo rápido (1d6 de daño a distancia)',
                'Espadazo descuidado (1d8 de daño físico)',
                'Grito de aliento (Cura 10 PE a un aliado)'
            ]
        ], JSON_UNESCAPED_UNICODE),
        'upgrade_json' => json_encode([], JSON_UNESCAPED_UNICODE),
        'notes' => 'Carta de NPC de prueba.',
        'image_url' => '',
        'reposo' => 4,
        'duracion' => 3,
        'created_by' => 1
    ],
    // 7. NPC Menor (Mascota con acciones seleccionables)
    [
        'name' => 'Halcón Mensajero',
        'card_type' => 'npc_menor',
        'rank' => 'B',
        'activation' => 'activa',
        'tags_json' => json_encode(['ALIADO TEMPORAL', 'SOPORTE'], JSON_UNESCAPED_UNICODE),
        'description' => 'Un halcón entrenado para entregar mensajes y atacar ojos enemigos en combate.',
        'cost_pe' => '15',
        'execution_stat' => '',
        'dice' => '',
        'effects_json' => json_encode([
            'npc_mascota_type' => 'mascota',
            'vida' => 30,
            'tier' => 2,
            'acciones' => [
                'Picotazo rápido (1d4 de daño físico)',
                'Cegar enemigo (Reduce en 2 la agilidad del enemigo por 1 turno)',
                'Reconocer terreno (Revela trampas o emboscadas)'
            ]
        ], JSON_UNESCAPED_UNICODE),
        'upgrade_json' => json_encode([], JSON_UNESCAPED_UNICODE),
        'notes' => 'Carta de Mascota de prueba.',
        'image_url' => '',
        'reposo' => 3,
        'duracion' => 4,
        'created_by' => 1
    ],
    // 8. Barco (Fragata)
    [
        'name' => 'El Vengador del Mar',
        'card_type' => 'barco',
        'rank' => 'A',
        'activation' => 'pasiva',
        'tags_json' => json_encode(['NAVE', 'MODIFICABLE'], JSON_UNESCAPED_UNICODE),
        'description' => 'Un veloz navío de tres mástiles equipado con cañones laterales y gran resistencia.',
        'cost_pe' => '—',
        'execution_stat' => '',
        'dice' => '',
        'effects_json' => json_encode([
            'barco_type' => 'fragata',
            'tier' => 3,
            'vida' => 500,
            'ataque' => 120,
            'velocidad' => 80,
            'resistencia' => 90
        ], JSON_UNESCAPED_UNICODE),
        'upgrade_json' => json_encode([], JSON_UNESCAPED_UNICODE),
        'notes' => 'Carta de Barco de prueba.',
        'image_url' => '',
        'reposo' => 0,
        'duracion' => 0,
        'created_by' => 1
    ]
];

foreach ($test_cards as $card) {
    // Comprobar si ya existe
    $escaped_name = $db->escape_string($card['name']);
    $check_q = $db->query("SELECT id FROM {$prefix}game_cards WHERE name = '{$escaped_name}' LIMIT 1");
    if ($db->num_rows($check_q) > 0) {
        $existing = $db->fetch_array($check_q);
        echo "La carta '{$card['name']}' ya existe con ID: {$existing['id']}. Saltando...\n";
        continue;
    }

    $insert = [
        'name' => $db->escape_string($card['name']),
        'card_type' => $db->escape_string($card['card_type']),
        'rank' => $db->escape_string($card['rank']),
        'activation' => $db->escape_string($card['activation']),
        'tags_json' => $db->escape_string($card['tags_json']),
        'description' => $db->escape_string($card['description']),
        'cost_pe' => $db->escape_string($card['cost_pe']),
        'execution_stat' => $db->escape_string($card['execution_stat']),
        'dice' => $db->escape_string($card['dice']),
        'effects_json' => $db->escape_string($card['effects_json']),
        'upgrade_json' => $db->escape_string($card['upgrade_json']),
        'notes' => $db->escape_string($card['notes']),
        'image_url' => $db->escape_string($card['image_url']),
        'reposo' => $card['reposo'],
        'duracion' => $card['duracion'],
        'created_by' => $card['created_by']
    ];

    $db->insert_query('game_cards', $insert);
    $card_id = $db->insert_id();
    echo "Creada la carta '{$card['name']}' (Tipo: {$card['card_type']}) con ID: {$card_id}\n";
}

echo "Proceso finalizado. Cartas de prueba creadas correctamente.\n";
