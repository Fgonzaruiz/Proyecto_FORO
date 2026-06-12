<?php
declare(strict_types=1);

define('IN_MYBB', 1);
require_once __DIR__ . '/../../global.php';

global $db;
$prefix = TABLE_PREFIX;

$blues_json = json_encode([
    ['range' => '1-5', 'result' => 'Viento a favor / Mar calmado (Favorable)', 'description' => 'Condiciones óptimas para navegar. Otorga un bonus narrativo de navegación (+ velocidad o ventaja).'],
    ['range' => '6-10', 'result' => 'Lluvia moderada / Neblina (Moderado)', 'description' => 'Reduce levemente la visibilidad o hace resbaladiza la cubierta. Ligeras penalizaciones a tiradas.'],
    ['range' => '11-15', 'result' => 'Tormenta menor / Mar picado (Severo)', 'description' => 'Vientos fuertes y olas que sacuden la nave. Dificultad para moverse en cubierta.'],
    ['range' => '16-19', 'result' => 'Mar encalmado total (Extremo)', 'description' => 'Cero viento; el barco no avanza si depende de velas. Aumenta considerablemente la duración del viaje.'],
    ['range' => '20', 'result' => 'Corriente desfavorable fuerte (Singular)', 'description' => 'Una corriente empuja el barco en dirección contraria. Atraso significativo o desvío de ruta.']
], JSON_UNESCAPED_UNICODE);

$gl_json = json_encode([
    ['range' => '1-5', 'result' => 'Corriente inversa favorable (Favorable)', 'description' => 'Corrientes salvajes que milagrosamente empujan al destino. Acorta el tiempo del viaje.'],
    ['range' => '6-10', 'result' => 'Nieve en verano / Lluvia cálida (Moderado)', 'description' => 'Alteración extrema de la temperatura en minutos. Confusión, necesidad de adaptar vestimenta.'],
    ['range' => '11-15', 'result' => 'Rayos sin nubes / Calor extremo (Severo)', 'description' => 'Descargas eléctricas o soles abrasadores súbitos. Riesgo leve; penalización a acciones físicas prolongadas.'],
    ['range' => '16-19', 'result' => 'Tornado súbito / Mar de nubes (Extremo)', 'description' => 'Fenómenos altamente destructivos que aparecen de la nada. Posible daño directo al barco si no se elude.'],
    ['range' => '20', 'result' => 'Lluvia de meteoritos / Erupción submarina (Singular)', 'description' => 'Catástrofe natural súbita de gran escala. Daño casi seguro a la integridad del barco.']
], JSON_UNESCAPED_UNICODE);

$nw_json = json_encode([
    ['range' => '1-5', 'result' => 'Ojo del huracán (Favorable)', 'description' => 'Una perturbadora e inusual calma total en medio del caos. Respiro vital antes de que todo vuelva a enloquecer.'],
    ['range' => '6-10', 'result' => 'Niebla desorientadora / Lluvia constante (Moderado)', 'description' => 'Niebla espesa magnética. El Log Pose gira erráticamente por unas horas.'],
    ['range' => '11-15', 'result' => 'Mar de lava / Lluvia de fuego (Severo)', 'description' => 'Ascuas del cielo o agua hirviendo. Casco dañado si no está recubierto; imposible pelear en cubierta.'],
    ['range' => '16-19', 'result' => 'Tornado de hielo / Tormenta eléctrica rastreadora (Extremo)', 'description' => 'Escarcha instantánea o rayos apuntan al barco. Inutilización de artillería, daño estructural grave al barco.'],
    ['range' => '20', 'result' => 'Isla de fuego flotante / Ballena de tormenta / Vórtice gigante (Singular)', 'description' => 'Eventos épicos y catastróficos. Amenaza de destrucción inminente. Resetea la aguja del Log Pose.']
], JSON_UNESCAPED_UNICODE);

$db->write_query("UPDATE {$prefix}game_oracles SET results_json = '" . $db->escape_string($blues_json) . "' WHERE subtype = 'nav_1_2'");
echo "[OK] Oráculo de Blues actualizado.\n";

$db->write_query("UPDATE {$prefix}game_oracles SET results_json = '" . $db->escape_string($gl_json) . "' WHERE subtype = 'nav_3'");
echo "[OK] Oráculo de Grand Line actualizado.\n";

$db->write_query("UPDATE {$prefix}game_oracles SET results_json = '" . $db->escape_string($nw_json) . "' WHERE subtype = 'nav_4_5'");
echo "[OK] Oráculo de New World actualizado.\n";
