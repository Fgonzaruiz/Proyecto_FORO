<?php
declare(strict_types=1);

/**
 * Oráculos de navegación por nivel de peligro + resoluciones auto-invocadas.
 */
global $db;

if (!$db->table_exists('game_oracles')) {
    echo '<p class="skip">[SKIP] game_oracles no existe.</p>';
    return;
}

$prefix = TABLE_PREFIX;

$upsert = static function (array $oracle) use ($db, $prefix): int {
    $escName = $db->escape_string($oracle['name']);
    $q = $db->query("SELECT id FROM {$prefix}game_oracles WHERE name = '{$escName}' LIMIT 1");
    if ($row = $db->fetch_array($q)) {
        return (int)$row['id'];
    }
    $escDesc = $db->escape_string($oracle['description']);
    $escSubtype = $db->escape_string($oracle['subtype']);
    $escTags = $db->escape_string($oracle['tags_json']);
    $escResults = $db->escape_string($oracle['results_json']);
    $escDice = $db->escape_string($oracle['dice_type']);
    $db->write_query("INSERT INTO {$prefix}game_oracles (name, description, oracle_type, subtype, category, tags_json, results_json, dice_type, is_system, created_by)
        VALUES ('{$escName}', '{$escDesc}', 'custom', '{$escSubtype}', '', '{$escTags}', '{$escResults}', '{$escDice}', 1, 0)");
    return (int)$db->insert_id();
};

$navalId = $upsert([
    'name' => 'Resolución — Encuentro naval',
    'description' => 'Desenlace automático cuando un encuentro con otro barco escala a combate o negociación.',
    'subtype' => 'nav_resolve_naval',
    'tags_json' => '["navegacion","encuentro","auto"]',
    'dice_type' => 'd6',
    'results_json' => json_encode([
        ['range' => '1-2', 'result' => 'Huida limpia', 'description' => 'El otro barco no alcanza a interceptaros.'],
        ['range' => '3-4', 'result' => 'Intercambio tenso', 'description' => 'Palabras cruzadas a distancia. Nadie abre fuego… por ahora.'],
        ['range' => '5', 'result' => 'Escaramuza menor', 'description' => 'Disparos de advertencia y maniobras bruscas. Daños leves posibles.'],
        ['range' => '6', 'result' => 'Abordaje', 'description' => 'El combate cuerpo a cuerpo en cubierta es inevitable.'],
    ], JSON_UNESCAPED_UNICODE),
]);

$seaBeastId = $upsert([
    'name' => 'Resolución — Criatura marina',
    'description' => 'Desenlace automático cuando algo enorme acecha bajo el casco.',
    'subtype' => 'nav_resolve_beast',
    'tags_json' => '["navegacion","criatura","auto"]',
    'dice_type' => 'd6',
    'results_json' => json_encode([
        ['range' => '1-2', 'result' => 'Solo avistamiento', 'description' => 'La sombra se hunde sin atacar.'],
        ['range' => '3-4', 'result' => 'Golpe al casco', 'description' => 'Sacudida fuerte. Revisar averías.'],
        ['range' => '5-6', 'result' => 'Ataque directo', 'description' => 'La criatura ataca el barco con intención clara.'],
    ], JSON_UNESCAPED_UNICODE),
]);

$upsert([
    'name' => 'Navegación — Brisa del East Blue',
    'description' => 'Sucesos menores en aguas tranquilas (peligro 1).',
    'subtype' => 'nav_1',
    'tags_json' => '["navegacion","east_blue","tranquilo"]',
    'dice_type' => 'd12',
    'results_json' => json_encode([
        ['range' => '1-3', 'result' => 'Mar en calma', 'description' => 'El horizonte está despejado.'],
        ['range' => '4-6', 'result' => 'Gaviotas de ruta', 'description' => 'Aves marinas indican tierra cercana.'],
        ['range' => '7-9', 'result' => 'Corriente suave', 'description' => 'Una corriente ayuda sin desviar el rumbo.'],
        ['range' => '10-11', 'result' => 'Pesca casual', 'description' => 'La tripulación puede reponer provisiones.'],
        ['range' => '12', 'result' => 'Viento cambiante', 'description' => 'El viento rota; el navegante debe ajustar velas.'],
    ], JSON_UNESCAPED_UNICODE),
]);

$upsert([
    'name' => 'Navegación — Incidente en ruta',
    'description' => 'Complicaciones moderadas entre islas (peligro 2).',
    'subtype' => 'nav_2',
    'tags_json' => '["navegacion","incidente"]',
    'dice_type' => 'd20',
    'results_json' => json_encode([
        ['range' => '1-4', 'result' => 'Lluvia persistente', 'description' => 'Cubierta resbaladiza y visibilidad reducida.'],
        ['range' => '5-8', 'result' => 'Arrecife oculto', 'description' => 'Hay que frenar y rodear con cuidado.'],
        ['range' => '9-12', 'result' => 'Barco pesquero', 'description' => 'Pescadores comparten rumores del destino.'],
        ['range' => '13-15', 'result' => 'Viento en contra', 'description' => 'La travesía se alarga un día.'],
        ['range' => '16-17', 'result' => 'Humo en el horizonte', 'description' => 'Algo arde a lo lejos. ¿Piratas? ¿Incendio?'],
        ['range' => '18-19', 'result' => 'Emboscada leve', 'description' => 'Un bote rápido intenta acercarse por la popa.', 'auto_invoke' => ['oracle_id' => $navalId]],
        ['range' => '20', 'result' => 'Sombra bajo el agua', 'description' => 'Algo grande nada paralelo al barco.', 'auto_invoke' => ['oracle_id' => $seaBeastId]],
    ], JSON_UNESCAPED_UNICODE),
]);

$upsert([
    'name' => 'Navegación — Corsarios y patrullas',
    'description' => 'Amenazas serias en mares disputados (peligro 4).',
    'subtype' => 'nav_4',
    'tags_json' => '["navegacion","corsario","marina"]',
    'dice_type' => 'd20',
    'results_json' => json_encode([
        ['range' => '1-4', 'result' => 'Señal de humo', 'description' => 'Otra embarcación pide auxilio. Puede ser trampa.'],
        ['range' => '5-8', 'result' => 'Patrulla lejana', 'description' => 'Una fragata avistada no cambia de rumbo… aún.'],
        ['range' => '9-12', 'result' => 'Mina flotante', 'description' => 'Restos de guerra flotan en la ruta.'],
        ['range' => '13-15', 'result' => 'Flota corsaria', 'description' => 'Dos bergantines bloquean parcialmente el paso.', 'auto_invoke' => ['oracle_id' => $navalId]],
        ['range' => '16-18', 'result' => 'Caza marina', 'description' => 'La Marina intercepta y exige identificación.'],
        ['range' => '19-20', 'result' => 'Kraken menor', 'description' => 'Tentáculos rozan el casco.', 'auto_invoke' => ['oracle_id' => $seaBeastId]],
    ], JSON_UNESCAPED_UNICODE),
]);

$upsert([
    'name' => 'Navegación — Abismo extremo',
    'description' => 'Fenómenos letales (peligro 5).',
    'subtype' => 'nav_5',
    'tags_json' => '["navegacion","extremo","new_world"]',
    'dice_type' => 'd12',
    'results_json' => json_encode([
        ['range' => '1-2', 'result' => 'Anomalía temporal', 'description' => 'El reloj de a bordo pierde horas sin explicación.'],
        ['range' => '3-4', 'result' => 'Lluvia de meteoritos', 'description' => 'Impactos en cubierta; daños estructurales posibles.'],
        ['range' => '5-6', 'result' => 'Muro de tormenta', 'description' => 'Un frente negro obliga a atravesar o rodear.'],
        ['range' => '7-8', 'result' => 'Territorio Yonko', 'description' => 'Banderas hostiles en islas cercanas.'],
        ['range' => '9-10', 'result' => 'Kraken adulto', 'description' => 'La bestia ataca sin vacilar.', 'auto_invoke' => ['oracle_id' => $seaBeastId]],
        ['range' => '11-12', 'result' => 'Colisión inevitable', 'description' => 'Otro coloso aparece de la niebla.', 'auto_invoke' => ['oracle_id' => $navalId]],
    ], JSON_UNESCAPED_UNICODE),
]);

// Enriquecer oráculo base con auto-invocaciones (si existe).
$tranquiloName = $db->escape_string('Evento de Navegación — Mar Tranquilo');
$tq = $db->query("SELECT id, results_json FROM {$prefix}game_oracles WHERE name = '{$tranquiloName}' LIMIT 1");
if ($row = $db->fetch_array($tq)) {
    $results = json_decode($row['results_json'] ?? '[]', true);
    if (is_array($results)) {
        foreach ($results as &$entry) {
            $text = (string)($entry['result'] ?? '');
            if ($text === 'Encuentro pirata' || $text === 'Tormenta menor') {
                $entry['auto_invoke'] = ['oracle_id' => $navalId];
            }
            if ($text === 'Tormenta brutal') {
                $entry['auto_invoke'] = ['oracle_id' => $seaBeastId];
            }
        }
        unset($entry);
        $esc = $db->escape_string(json_encode($results, JSON_UNESCAPED_UNICODE));
        $db->write_query("UPDATE {$prefix}game_oracles SET results_json = '{$esc}' WHERE id = " . (int)$row['id']);
        echo "<p class='ok'>[OK] Mar Tranquilo actualizado con auto-invocaciones.</p>";
    }
}

echo '<p class="ok">[OK] Oráculos de navegación ampliados (nav_1, nav_2, nav_4, nav_5 + resoluciones).</p>';
