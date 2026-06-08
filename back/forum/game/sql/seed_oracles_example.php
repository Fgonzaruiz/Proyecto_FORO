<?php
declare(strict_types=1);

/**
 * Seed: oráculos temáticos de One Piece.
 * Ejecutar: php back/forum/game/sql/seed_oracles_example.php
 */

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;
$table = "{$prefix}game_oracles";

// Buscar un admin real
$admin_q = $db->query("SELECT uid FROM {$prefix}users WHERE usergroup = 4 LIMIT 1");
$admin = $db->fetch_array($admin_q);
$admin_uid = $admin ? (int)$admin['uid'] : 1;

$oracles = [
    // 1. yes_no
    [
        'name' => 'El Mar lo Decide',
        'description' => 'Un sí/no que los marinos usan cuando el viento no sopla claro.',
        'oracle_type' => 'yes_no',
        'subtype' => 'navegacion',
        'category' => '',
        'tags_json' => '["navegacion","basico"]',
        'results_json' => '[{"range":"1-10","result":"Sí","description":"El mar te concede el paso."},{"range":"11-17","result":"No","description":"Las corrientes se oponen."},{"range":"18-20","result":"Sí, pero...","description":"Concedido, pero con un costo inesperado."}]',
        'variations_json' => '{}',
        'auto_invoke_json' => '[]',
        'dice_type' => 'd20',
    ],
    // 2. action
    [
        'name' => 'Acciones de la Tripulación',
        'description' => 'Determina qué hace un miembro de la tripulación en un momento dado.',
        'oracle_type' => 'action',
        'subtype' => 'tripulacion',
        'category' => '',
        'tags_json' => '["tripulacion","pnj"]',
        'results_json' => '[' .
            '{"range":"1","result":"Observa el horizonte"},' .
            '{"range":"2","result":"Repara aparejos"},' .
            '{"range":"3","result":"Cocina"},' .
            '{"range":"4","result":"Entrena combate"},' .
            '{"range":"5","result":"Duerme"},' .
            '{"range":"6","result":"Lee un libro"},' .
            '{"range":"7","result":"Canta"},' .
            '{"range":"8","result":"Pesca"},' .
            '{"range":"9","result":"Limpia cubierta"},' .
            '{"range":"10","result":"Discute"},' .
            '{"range":"11","result":"Bebe"},' .
            '{"range":"12","result":"Escribe en diario"},' .
            '{"range":"13","result":"Cuenta historias"},' .
            '{"range":"14","result":"Juega"},' .
            '{"range":"15","result":"Medita"},' .
            '{"range":"16","result":"Espía"},' .
            '{"range":"17","result":"Nada"},' .
            '{"range":"18","result":"Inspecciona el barco"},' .
            '{"range":"19","result":"Discute con el capitán"},' .
            '{"range":"20","result":"¡Ataca!"}' .
        ']',
        'variations_json' => '{}',
        'auto_invoke_json' => '[]',
        'dice_type' => 'd20',
    ],
    // 3. theme
    [
        'name' => 'Tema de Aventura',
        'description' => 'Define el tema central de la aventura o arco narrativo.',
        'oracle_type' => 'theme',
        'subtype' => 'narrativa',
        'category' => '',
        'tags_json' => '["narrativa","trama"]',
        'results_json' => '[' .
            '{"range":"1-5","result":"Venganza","description":"Alguien busca ajustar cuentas."},' .
            '{"range":"6-10","result":"Protección","description":"Deben proteger a alguien o algo."},' .
            '{"range":"11-15","result":"Exploración","description":"Un territorio desconocido los llama."},' .
            '{"range":"16-20","result":"Supervivencia","description":"Luchan contra elementos hostiles."},' .
            '{"range":"21-25","result":"Misterio","description":"Algo extraño está ocurriendo."},' .
            '{"range":"26-30","result":"Competencia","description":"Una carrera o torneo."},' .
            '{"range":"31-35","result":"Traición","description":"Alguien en quien confiaban los traiciona."},' .
            '{"range":"36-40","result":"Redención","description":"Buscan limpiar su honor."},' .
            '{"range":"41-45","result":"Conquista","description":"Ambición de poder o territorio."},' .
            '{"range":"46-50","result":"Huida","description":"Escapan de algo o alguien."},' .
            '{"range":"51-55","result":"Rescate","description":"Salvar a alguien cautivo."},' .
            '{"range":"56-60","result":"Construcción","description":"Edificar o restaurar algo."},' .
            '{"range":"61-65","result":"Alianza","description":"Formar o romper una alianza."},' .
            '{"range":"66-70","result":"Ritual","description":"Una ceremonia ancestral."},' .
            '{"range":"71-75","result":"Tormenta","description":"Una tormenta se aproxima."},' .
            '{"range":"76-80","result":"Descubrimiento","description":"Revelación de un secreto."},' .
            '{"range":"81-85","result":"Guerra","description":"Conflicto a gran escala."},' .
            '{"range":"86-90","result":"Enfermedad","description":"Una plaga o maldición azota."},' .
            '{"range":"91-95","result":"Tesoro","description":"Un tesoro legendario aparece."},' .
            '{"range":"96-100","result":"Destino","description":"El futuro está escrito."}' .
        ']',
        'variations_json' => '{}',
        'auto_invoke_json' => '[]',
        'dice_type' => 'd100',
    ],
    // 4. action_theme
    [
        'name' => 'Encuentro en el Mar',
        'description' => 'Combina una acción con un tema para generar encuentros marítimos únicos.',
        'oracle_type' => 'action_theme',
        'subtype' => 'encuentro',
        'category' => '',
        'tags_json' => '["encuentro","navegacion"]',
        'results_json' => '[{"range":"1-100","result":"(Ver Acción) + (Ver Tema)","description":"Tira Acción y Tema por separado y combínalos."}]',
        'variations_json' => '{}',
        'auto_invoke_json' => '[]',
        'dice_type' => 'd100',
    ],
    // 5. place_descriptor
    [
        'name' => 'Descriptor de Isla',
        'description' => 'Describe el aspecto o ambiente general de una isla.',
        'oracle_type' => 'place_descriptor',
        'subtype' => 'exploracion',
        'category' => '',
        'tags_json' => '["exploracion","lugares"]',
        'results_json' => '[' .
            '{"range":"1-5","result":"Frondosa","description":"Vegetación espesa."},' .
            '{"range":"6-10","result":"Árida","description":"Tierra seca y desértica."},' .
            '{"range":"11-15","result":"Helada","description":"Cubierta de hielo y nieve."},' .
            '{"range":"16-20","result":"Vulcánica","description":"Suelo negro y volcanes."},' .
            '{"range":"21-25","result":"Flotante","description":"Isla en el cielo."},' .
            '{"range":"26-30","result":"Sumergida","description":"Parcialmente bajo el agua."},' .
            '{"range":"31-35","result":"Mecánica","description":"Engranajes y metal."},' .
            '{"range":"36-40","result":"Encantada","description":"Brillo místico."},' .
            '{"range":"41-45","result":"Pantánica","description":"Ciénagas y manglares."},' .
            '{"range":"46-50","result":"Montañosa","description":"Picos escarpados."},' .
            '{"range":"51-55","result":"Laberíntica","description":"Cavernas intrincadas."},' .
            '{"range":"56-60","result":"Dorada","description":"Arena dorada y sol."},' .
            '{"range":"61-65","result":"Tormentosa","description":"Relámpagos constantes."},' .
            '{"range":"66-70","result":"Coralina","description":"Arrecifes de coral."},' .
            '{"range":"71-75","result":"Olvidada","description":"Ruinas de civilización."},' .
            '{"range":"76-80","result":"Festiva","description":"Luces y celebraciones."},' .
            '{"range":"81-85","result":"Sombría","description":"Niebla densa."},' .
            '{"range":"86-90","result":"Celestial","description":"Maravillas astronómicas."},' .
            '{"range":"91-95","result":"Infernal","description":"Ríos de lava."},' .
            '{"range":"96-100","result":"Prístina","description":"Naturaleza virgen."}' .
        ']',
        'variations_json' => '{}',
        'auto_invoke_json' => '[]',
        'dice_type' => 'd100',
    ],
    // 6. place_focus
    [
        'name' => 'Foco de Exploración',
        'description' => 'Determina el punto de interés principal al llegar a un lugar nuevo.',
        'oracle_type' => 'place_focus',
        'subtype' => 'exploracion',
        'category' => '',
        'tags_json' => '["exploracion","lugares"]',
        'results_json' => '[' .
            '{"range":"1-5","result":"Una torre antigua"},' .
            '{"range":"6-10","result":"Un mercado bullicioso"},' .
            '{"range":"11-15","result":"Un puerto devastado"},' .
            '{"range":"16-20","result":"Una cueva oculta"},' .
            '{"range":"21-25","result":"Un palacio majestuoso"},' .
            '{"range":"26-30","result":"Un bosque prohibido"},' .
            '{"range":"31-35","result":"Un santuario"},' .
            '{"range":"36-40","result":"Un acantilado"},' .
            '{"range":"41-45","result":"Un cementerio de barcos"},' .
            '{"range":"46-50","result":"Una fuente termal"},' .
            '{"range":"51-55","result":"Un jardín colgante"},' .
            '{"range":"56-60","result":"Una mina abandonada"},' .
            '{"range":"61-65","result":"Un faro"},' .
            '{"range":"66-70","result":"Una arena de combate"},' .
            '{"range":"71-75","result":"Un laboratorio"},' .
            '{"range":"76-80","result":"Una biblioteca"},' .
            '{"range":"81-85","result":"Una taberna"},' .
            '{"range":"86-90","result":"Una estatua gigante"},' .
            '{"range":"91-95","result":"Un campo de batalla"},' .
            '{"range":"96-100","result":"Una puerta sellada"}' .
        ']',
        'variations_json' => '{}',
        'auto_invoke_json' => '[]',
        'dice_type' => 'd100',
    ],
    // 7. character_role
    [
        'name' => 'Rol de PNJ',
        'description' => 'Determina la ocupación o rol de un personaje no jugador.',
        'oracle_type' => 'character_role',
        'subtype' => 'pnj',
        'category' => '',
        'tags_json' => '["pnj","personajes"]',
        'results_json' => '[' .
            '{"range":"1","result":"Marino"},' .
            '{"range":"2","result":"Pirata"},' .
            '{"range":"3","result":"Cazarrecompensas"},' .
            '{"range":"4","result":"Mercader"},' .
            '{"range":"5","result":"Carpintero naval"},' .
            '{"range":"6","result":"Cocinero"},' .
            '{"range":"7","result":"Médico"},' .
            '{"range":"8","result":"Navegante"},' .
            '{"range":"9","result":"Pescador"},' .
            '{"range":"10","result":"Artista"},' .
            '{"range":"11","result":"Granjero"},' .
            '{"range":"12","result":"Herrero"},' .
            '{"range":"13","result":"Guardián"},' .
            '{"range":"14","result":"Erudito"},' .
            '{"range":"15","result":"Mendigo"},' .
            '{"range":"16","result":"Ladrón"},' .
            '{"range":"17","result":"Noble"},' .
            '{"range":"18","result":"Místico"},' .
            '{"range":"19","result":"Revolucionario"},' .
            '{"range":"20","result":"Gobierno Mundial"}' .
        ']',
        'variations_json' => '{}',
        'auto_invoke_json' => '[]',
        'dice_type' => 'd20',
    ],
    // 8. character_trait
    [
        'name' => 'Rasgo de PNJ',
        'description' => 'Define un rasgo de personalidad o apariencia de un PNJ.',
        'oracle_type' => 'character_trait',
        'subtype' => 'pnj',
        'category' => '',
        'tags_json' => '["pnj","personajes"]',
        'results_json' => '[' .
            '{"range":"1-5","result":"Audaz"},' .
            '{"range":"6-10","result":"Desconfiado"},' .
            '{"range":"11-15","result":"Carismático"},' .
            '{"range":"16-20","result":"Torpe"},' .
            '{"range":"21-25","result":"Sabio"},' .
            '{"range":"26-30","result":"Cicatrizado"},' .
            '{"range":"31-35","result":"Silencioso"},' .
            '{"range":"36-40","result":"Glotón"},' .
            '{"range":"41-45","result":"Noble"},' .
            '{"range":"46-50","result":"Travieso"},' .
            '{"range":"51-55","result":"Melancólico"},' .
            '{"range":"56-60","result":"Fanfarrón"},' .
            '{"range":"61-65","result":"Leal"},' .
            '{"range":"66-70","result":"Ambicioso"},' .
            '{"range":"71-75","result":"Tímido"},' .
            '{"range":"76-80","result":"Excéntrico"},' .
            '{"range":"81-85","result":"Violento"},' .
            '{"range":"86-90","result":"Misterioso"},' .
            '{"range":"91-95","result":"Protector"},' .
            '{"range":"96-100","result":"Profético"}' .
        ']',
        'variations_json' => '{}',
        'auto_invoke_json' => '[]',
        'dice_type' => 'd100',
    ],
    // 9. character_goal
    [
        'name' => 'Meta de PNJ',
        'description' => 'Determina el objetivo o deseo de un PNJ.',
        'oracle_type' => 'character_goal',
        'subtype' => 'pnj',
        'category' => '',
        'tags_json' => '["pnj","personajes"]',
        'results_json' => '[' .
            '{"range":"1-5","result":"Encontrar un tesoro"},' .
            '{"range":"6-10","result":"Vengar a alguien"},' .
            '{"range":"11-15","result":"Proteger a su familia"},' .
            '{"range":"16-20","result":"Obtener poder"},' .
            '{"range":"21-25","result":"Escapar del pasado"},' .
            '{"range":"26-30","result":"Construir un imperio"},' .
            '{"range":"31-35","result":"Curar una enfermedad"},' .
            '{"range":"36-40","result":"Completar un mapa"},' .
            '{"range":"41-45","result":"Demostrar su valía"},' .
            '{"range":"46-50","result":"Reunir algo"},' .
            '{"range":"51-55","result":"Encontrar el All Blue"},' .
            '{"range":"56-60","result":"Navegar el mundo"},' .
            '{"range":"61-65","result":"Derrocar un régimen"},' .
            '{"range":"66-70","result":"Revelar una verdad"},' .
            '{"range":"71-75","result":"Forjar una alianza"},' .
            '{"range":"76-80","result":"Recuperar un recuerdo"},' .
            '{"range":"81-85","result":"Convertirse en leyenda"},' .
            '{"range":"86-90","result":"Pagar una deuda"},' .
            '{"range":"91-95","result":"Encontrar el One Piece"},' .
            '{"range":"96-100","result":"Sobrevivir"}' .
        ']',
        'variations_json' => '{}',
        'auto_invoke_json' => '[]',
        'dice_type' => 'd100',
    ],
    // 10. pay_the_price
    [
        'name' => 'Paga el Precio (One Piece)',
        'description' => 'Sufre las consecuencias de una acción arriesgada o fallo crítico.',
        'oracle_type' => 'pay_the_price',
        'subtype' => 'nucleo',
        'category' => '',
        'tags_json' => '["nucleo","movidas"]',
        'results_json' => '[' .
            '{"range":"1-5","result":"Pérdida de recursos"},' .
            '{"range":"6-10","result":"Daño físico"},' .
            '{"range":"11-15","result":"Llamas la atención"},' .
            '{"range":"16-20","result":"Tu barco sufre"},' .
            '{"range":"21-25","result":"Alguien resulta herido"},' .
            '{"range":"26-30","result":"Pierdes el rumbo"},' .
            '{"range":"31-35","result":"Un aliado te traiciona"},' .
            '{"range":"36-40","result":"Tormenta repentina"},' .
            '{"range":"41-45","result":"Agotamiento extremo"},' .
            '{"range":"46-50","result":"Revelas tu posición"},' .
            '{"range":"51-55","result":"Objeto valioso destruido"},' .
            '{"range":"56-60","result":"Enemigos aparecen"},' .
            '{"range":"61-65","result":"Maldición o enfermedad"},' .
            '{"range":"66-70","result":"Pérdida de reputación"},' .
            '{"range":"71-75","result":"Separación del grupo"},' .
            '{"range":"76-80","result":"Captura"},' .
            '{"range":"81-85","result":"Efecto de Fruta del Diablo"},' .
            '{"range":"86-90","result":"Deuda o juramento"},' .
            '{"range":"91-95","result":"Pérdida de memoria"},' .
            '{"range":"96-100","result":"En la lista del Gobierno"}' .
        ']',
        'variations_json' => '{}',
        'auto_invoke_json' => '[]',
        'dice_type' => 'd100',
    ],
    // 11. custom: clima con variaciones
    [
        'name' => 'Clima en la Grand Line',
        'description' => 'Condiciones meteorológicas impredecibles típicas de la Grand Line. Varía según la isla.',
        'oracle_type' => 'custom',
        'subtype' => 'clima',
        'category' => '',
        'tags_json' => '["clima","navegacion"]',
        'results_json' => '[' .
            '{"range":"1-10","result":"Tormenta eléctrica"},' .
            '{"range":"11-20","result":"Calma chicha"},' .
            '{"range":"21-30","result":"Lluvia torrencial"},' .
            '{"range":"31-40","result":"Niebla espesa"},' .
            '{"range":"41-50","result":"Sol abrasador"},' .
            '{"range":"51-60","result":"Vientos huracanados"},' .
            '{"range":"61-70","result":"Granizo"},' .
            '{"range":"71-80","result":"Arcoíris doble"},' .
            '{"range":"81-90","result":"Maremoto"},' .
            '{"range":"91-100","result":"Calima misteriosa"}' .
        ']',
        'variations_json' => json_encode([
            'Arabasta' => [
                ['range'=>'1-15','result'=>'Tormenta de arena','description'=>'El desierto se levanta.'],
                ['range'=>'16-30','result'=>'Sol implacable','description'=>'Calor extremo.'],
                ['range'=>'31-50','result'=>'Noche estrellada','description'=>'Cielo despejado.'],
                ['range'=>'51-65','result'=>'Oasis','description'=>'Un espejismo real.'],
                ['range'=>'66-80','result'=>'Viento seco','description'=>'Arena en el aire.'],
                ['range'=>'81-90','result'=>'Lluvia bendita','description'=>'La lluvia tan esperada.'],
                ['range'=>'91-100','result'=>'Mirage','description'=>'El calor distorsiona.'],
            ],
            'Drum' => [
                ['range'=>'1-20','result'=>'Ventisca','description'=>'Nieve y viento cegador.'],
                ['range'=>'21-40','result'=>'Nevada','description'=>'Copos sin parar.'],
                ['range'=>'41-55','result'=>'Hielo negro','description'=>'Suelo traicionero.'],
                ['range'=>'56-70','result'=>'Avalancha','description'=>'La montaña ruge.'],
                ['range'=>'71-85','result'=>'Cielo despejado','description'=>'Frío pero sol.'],
                ['range'=>'86-95','result'=>'Noche polar','description'=>'Oscuridad eterna.'],
                ['range'=>'96-100','result'=>'Aurora boreal','description'=>'Luces en el cielo.'],
            ],
            'Skypiea' => [
                ['range'=>'1-15','result'=>'Cielo diáfano','description'=>'Visibilidad perfecta.'],
                ['range'=>'16-35','result'=>'Mar de nubes','description'=>'Nubes espesas.'],
                ['range'=>'36-50','result'=>'Tormenta celestial','description'=>'Rayos desde arriba.'],
                ['range'=>'51-65','result'=>'Viento ascendente','description'=>'Corriente que eleva.'],
                ['range'=>'66-80','result'=>'Niebla de nubes','description'=>'Todo blanco.'],
                ['range'=>'81-90','result'=>'Lluvia de ángeles','description'=>'Agua purísima.'],
                ['range'=>'91-100','result'=>'Vórtex','description'=>'Torbellino celeste.'],
            ],
            'Water 7' => [
                ['range'=>'1-15','result'=>'Marea alta','description'=>'El agua sube.'],
                ['range'=>'16-30','result'=>'Bruma matutina','description'=>'Niebla ligera.'],
                ['range'=>'31-45','result'=>'Acqua Laguna','description'=>'La gran ola se acerca.'],
                ['range'=>'46-60','result'=>'Brisa marina','description'=>'Viento suave.'],
                ['range'=>'61-75','result'=>'Lluvia fina','description'=>'Llovizna que cala.'],
                ['range'=>'76-90','result'=>'Cielo despejado','description'=>'Día perfecto.'],
                ['range'=>'91-100','result'=>'Niebla tóxica','description'=>'Vapor de canales.'],
            ],
        ], JSON_UNESCAPED_UNICODE),
        'auto_invoke_json' => '[]',
        'dice_type' => 'd100',
    ],
    // 12. custom: horizonte
    [
        'name' => '¿Qué hay en el Horizonte?',
        'description' => 'Algo aparece en la línea del mar. ¿Qué será?',
        'oracle_type' => 'custom',
        'subtype' => 'avistamiento',
        'category' => '',
        'tags_json' => '["avistamiento","navegacion"]',
        'results_json' => '[' .
            '{"range":"1","result":"Barco mercante"},' .
            '{"range":"2","result":"Isla desconocida"},' .
            '{"range":"3","result":"Restos de naufragio"},' .
            '{"range":"4","result":"Rey del Mar"},' .
            '{"range":"5","result":"Tormenta"},' .
            '{"range":"6","result":"Barco de la Marina"},' .
            '{"range":"7","result":"Ballena"},' .
            '{"range":"8","result":"Balsa a la deriva"},' .
            '{"range":"9","result":"Grupo de aves"},' .
            '{"range":"10","result":"Columna de humo"},' .
            '{"range":"11","result":"Barco pirata"},' .
            '{"range":"12","result":"Iceberg"},' .
            '{"range":"13","result":"Lluvia de peces"},' .
            '{"range":"14","result":"Una sirena"},' .
            '{"range":"15","result":"Tonel flotante"},' .
            '{"range":"16","result":"Niebla mágica"},' .
            '{"range":"17","result":"Barco fantasma"},' .
            '{"range":"18","result":"Cascada en el mar"},' .
            '{"range":"19","result":"Flores en el agua"},' .
            '{"range":"20","result":"Visión de Gol D. Roger"}' .
        ']',
        'variations_json' => '{}',
        'auto_invoke_json' => '[]',
        'dice_type' => 'd20',
    ],
    // 13. custom: tesoro
    [
        'name' => 'Tesoro Escondido',
        'description' => '¿Qué tipo de tesoro han descubierto?',
        'oracle_type' => 'custom',
        'subtype' => 'tesoro',
        'category' => '',
        'tags_json' => '["tesoro","objetos"]',
        'results_json' => '[' .
            '{"range":"1-10","result":"Bolsa de berries"},' .
            '{"range":"11-20","result":"Joyas"},' .
            '{"range":"21-30","result":"Arma antigua"},' .
            '{"range":"31-40","result":"Mapa del tesoro"},' .
            '{"range":"41-50","result":"Fruta del Diablo"},' .
            '{"range":"51-60","result":"Libro antiguo"},' .
            '{"range":"61-70","result":"Pergamino del Gobierno"},' .
            '{"range":"71-80","result":"Poneglyph"},' .
            '{"range":"81-90","result":"Arma ancestral"},' .
            '{"range":"91-95","result":"Arma Definitiva"},' .
            '{"range":"96-100","result":"One Piece"}' .
        ']',
        'variations_json' => '{}',
        'auto_invoke_json' => '[]',
        'dice_type' => 'd100',
    ],
    // 14. delve_theme
    [
        'name' => 'Tema de Mazmorra',
        'description' => 'Define la ambientación de una mazmorra.',
        'oracle_type' => 'delve_theme',
        'subtype' => 'mazmorra',
        'category' => '',
        'tags_json' => '["mazmorra","exploracion"]',
        'results_json' => '[' .
            '{"range":"1-2","result":"Acuática"},' .
            '{"range":"3-4","result":"Fortaleza militar"},' .
            '{"range":"5-6","result":"Templo antiguo"},' .
            '{"range":"7-8","result":"Bosque encantado"},' .
            '{"range":"9-10","result":"Cueva de hielo"},' .
            '{"range":"11-12","result":"Volcán activo"},' .
            '{"range":"13-14","result":"Laberinto subterráneo"},' .
            '{"range":"15-16","result":"Nave abandonada"},' .
            '{"range":"17-18","result":"Ciudad flotante"},' .
            '{"range":"19-20","result":"Sótano del Gobierno"}' .
        ']',
        'variations_json' => '{}',
        'auto_invoke_json' => '[]',
        'dice_type' => 'd20',
    ],
    // 15. delve_domain
    [
        'name' => 'Dominio de Mazmorra',
        'description' => 'Determina el tipo de criaturas o desafíos en la mazmorra.',
        'oracle_type' => 'delve_domain',
        'subtype' => 'mazmorra',
        'category' => '',
        'tags_json' => '["mazmorra","exploracion"]',
        'results_json' => '[' .
            '{"range":"1-2","result":"Hombres-pez"},' .
            '{"range":"3-4","result":"Marines corruptos"},' .
            '{"range":"5-6","result":"Bestias salvajes"},' .
            '{"range":"7-8","result":"Espíritus antiguos"},' .
            '{"range":"9-10","result":"Piratas rivales"},' .
            '{"range":"11-12","result":"Pacifistas"},' .
            '{"range":"13-14","result":"Cazarrecompensas"},' .
            '{"range":"15-16","result":"Fruteros"},' .
            '{"range":"17-18","result":"CP9"},' .
            '{"range":"19-20","result":"Ancestrales"}' .
        ']',
        'variations_json' => '{}',
        'auto_invoke_json' => '[]',
        'dice_type' => 'd20',
    ],
];

echo "=== Sembrando oráculos de ejemplo (One Piece) ===\n\n";

$inserted = 0;
$skipped = 0;
foreach ($oracles as $o) {
    // Verificar si ya existe
    $check = $db->query("SELECT id FROM {$table} WHERE name = '" . $db->escape_string($o['name']) . "' LIMIT 1");
    if ($db->num_rows($check)) {
        echo "[--] Ya existe: {$o['name']}\n";
        $skipped++;
        continue;
    }

    $o['created_by'] = $admin_uid;
    $db->insert_query('game_oracles', $o);
    echo "[OK] Insertado: {$o['name']} ({$o['oracle_type']})\n";
    $inserted++;
}

echo "\n=== Resumen: {$inserted} insertados, {$skipped} omitidos ===\n";
