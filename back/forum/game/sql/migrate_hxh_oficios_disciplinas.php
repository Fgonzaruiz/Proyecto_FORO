<?php
declare(strict_types=1);

/**
 * Migración HxH: Poblar Oficios y Disciplinas Marciales.
 * Trunca y puebla las tablas de game_oficios y game_disciplinas con contenido de HxH.
 */

global $db;
$prefix = TABLE_PREFIX;

if (!$db->table_exists('game_oficios') || !$db->table_exists('game_disciplinas')) {
    echo "[SKIP] Las tablas game_oficios o game_disciplinas no existen.\n";
    return;
}

echo "=== Migrando Oficios y Disciplinas a HxH ===\n\n";

// 1. Limpiar datos antiguos para evitar duplicados/conflictos
$db->write_query("TRUNCATE TABLE {$prefix}game_oficios");
$db->write_query("TRUNCATE TABLE {$prefix}game_disciplinas");
echo "[OK] Tablas truncadas para inserción limpia.\n";

// 2. Oficios HxH
$oficios = [
    [
        'slug' => 'armero',
        'name' => 'Armero / Artífice',
        'description' => 'Forja y repara armas y herramientas metálicas, y fabrica artefactos.',
        'category' => 'crafteo',
        'icon' => 'fa-hammer',
        'sort_order' => 10,
        'unlocks' => [
            '1' => 'Reparación menor de armas y armaduras simples. Crafteo tier 1.',
            '2' => 'Crafteo tier 2. Propiedad menor en ítems forjados. Bonus +1 en oráculos de forja.',
            '3' => 'Forja Reforzada: ítems crafteados +5% al stat relevante al equipar. Crafteo tier 3.',
            '4' => 'Crafteo tier 4: armas con dos propiedades. Bonus +2 en oráculos de forja.',
            '5' => 'Obra Maestra: intentar forjar o fabricar un ítem único o artefacto (1 vez por mes real, materiales raros).',
        ]
    ],
    [
        'slug' => 'medico',
        'name' => 'Médico',
        'description' => 'Diagnostica y trata heridas, venenos y enfermedades.',
        'category' => 'sanacion',
        'icon' => 'fa-user-md',
        'sort_order' => 20,
        'unlocks' => [
            '1' => 'Tratamiento básico de heridas. Oráculos de diagnóstico.',
            '2' => 'Bonus +1 en oráculos de curación. Recuperación de PV a aliados al final de un arco.',
            '3' => 'Operación de Emergencia: estabiliza a un aliado a 0 PV dejándolo a 1 PV (1 vez por combate).',
            '4' => 'Bonus +2 en oráculos de veneno y enfermedad. Crafteo tier 4 medicinales.',
            '5' => 'Tratamiento Milagroso: curación completa fuera de combate (1 vez por arco, post de 300+ palabras).',
        ]
    ],
    [
        'slug' => 'cocinero',
        'name' => 'Cocinero / Chef',
        'description' => 'Prepara comidas y raciones alimenticias especiales para restaurar energías.',
        'category' => 'artesania',
        'icon' => 'fa-utensils',
        'sort_order' => 30,
        'unlocks' => [
            '1' => 'Crafteo tier 1: consumibles alimenticios. Rol narrativo en celebraciones.',
            '2' => 'Bonus +1 en oráculos de supervivencia. Consumibles crafteados +10% recuperación PE/PV.',
            '3' => 'Bento de Batalla: consumible semanal con +5% PV máximo durante un hilo de combate.',
            '4' => 'Crafteo tier 4. Bonus +2 en oráculos de provisiones (total +3).',
            '5' => 'Festín Épico: buff de +10% PV y PE para participantes en el siguiente combate (1 vez por arco).',
        ]
    ],
    [
        'slug' => 'cientifico',
        'name' => 'Científico / Investigador',
        'description' => 'Investiga fenómenos raros, inventa gadgets y analiza compuestos químicos.',
        'category' => 'ciencia',
        'icon' => 'fa-flask',
        'sort_order' => 40,
        'unlocks' => [
            '1' => 'Crafteo tier 1: gadgets simples. Oráculos de investigación.',
            '2' => 'Crafteo tier 2: trampas. Bonus +1 en oráculos de investigación.',
            '3' => 'Prototipo: carta de equipo única con propiedad mecánica (1 vez por nivel de PJ).',
            '4' => 'Crafteo tier 4. Bonus +2 en oráculos de tecnología.',
            '5' => 'Invención Revolucionaria: proponer un ítem completamente nuevo al catálogo general.',
        ]
    ],
    [
        'slug' => 'domador',
        'name' => 'Domador de Bestias',
        'description' => 'Establece vínculos y domestica criaturas del mundo salvaje para usarlas en combate.',
        'category' => 'utilidad',
        'icon' => 'fa-paw',
        'sort_order' => 5,
        'unlocks' => [
            '1' => 'Cartas de bestia en el inventario. Bonus +1 en oráculos de interacción de bestias.',
            '2' => 'Bestias con +10% PV base. Domesticación de criaturas de rango II.',
            '3' => 'Vínculo Primario: la bestia actúa independientemente en un post (1 vez por hilo).',
            '4' => 'Solicitar bestia única al staff. Crafteo de equipo para bestias tier 4.',
            '5' => 'Manada: hasta 3 bestias activas en combate. Las bestias ganan +1 rango efectivo en stats.',
        ]
    ],
    [
        'slug' => 'investigador',
        'name' => 'Investigador de Reliquias',
        'description' => 'Estudia ruinas antiguas, descifra escrituras perdidas y analiza vestigios Nen.',
        'category' => 'lore',
        'icon' => 'fa-scroll',
        'sort_order' => 60,
        'unlocks' => [
            '1' => 'Bonus +1 en oráculos de descubrimiento. Identificar inscripciones antiguas.',
            '2' => 'Descifrar lenguajes perdidos y textos antiguos. Bonus +2 en historia.',
            '3' => 'Conocimiento Ancestral: pista de lore o secretos de ruinas del staff (1 vez por arco).',
            '4' => 'Oráculos exclusivos de secretos y reliquias del mundo. Bonus +2 en historia.',
            '5' => 'Reliquia Completa: descifrar y activar runas o reliquias Nen (1 vez por arco).',
        ]
    ],
    [
        'slug' => 'espia',
        'name' => 'Espía / Infiltrador',
        'description' => 'Se infiltra en organizaciones, recolecta información y utiliza disfraces.',
        'category' => 'sigilo',
        'icon' => 'fa-mask',
        'sort_order' => 70,
        'unlocks' => [
            '1' => 'Acciones de sigilo avanzadas. Bonus +1 en oráculos de sigilo y espionaje.',
            '2' => 'Identidad alternativa aprobada por staff. Bonus +2 en oráculos de engaño.',
            '3' => 'Infiltración: acción encubierta con oráculo de sigilo bonificado.',
            '4' => 'Bonus +2 en sigilo (total +3). Acceso a zonas restringidas como observador en hilos.',
            '5' => 'Maestro del Engaño: impersonar con consentimiento. Bonus +3 acumulado en sigilo/engaño.',
        ]
    ],
    [
        'slug' => 'mercader',
        'name' => 'Mercader',
        'description' => 'Gestiona intercambios comerciales, manipula el mercado y optimiza el Jenny.',
        'category' => 'economia',
        'icon' => 'fa-coins',
        'sort_order' => 80,
        'unlocks' => [
            '1' => 'Descuento del 5% en la tienda. Oráculos de mercado.',
            '2' => 'Descuento del 10%. Intercambio entre personajes aprobado por staff.',
            '3' => 'Trato Especial: obtener un ítem fuera de stock (1 vez por semana real). Descuento del 15%.',
            '4' => 'Negocio Propio narrativo. Bonus +2 en oráculos de mercado (total +3).',
            '5' => 'Monopolio: descuento del 20%. Influir en la economía de una región (evento staff).',
        ]
    ],
    [
        'slug' => 'rastreador',
        'name' => 'Rastreador',
        'description' => 'Especialista en la búsqueda de objetivos, caza de recompensas y supervivencia en exteriores.',
        'category' => 'utilidad',
        'icon' => 'fa-compass',
        'sort_order' => 90,
        'unlocks' => [
            '1' => 'Rastreo básico de objetivos. Bonus +1 en supervivencia.',
            '2' => 'Bonus +1 en oráculos de caza. Reducción de fatiga en viajes interregionales.',
            '3' => 'Sentido de la Caza: detectar emboscadas u ocultamiento Nen básico (Gyo/En menor).',
            '4' => 'Bonus +2 en supervivencia (total +3). Encontrar refugios y recursos en climas extremos.',
            '5' => 'Gran Rastreador: localizar cualquier objetivo en la misma región sin fallar (1 vez por arco).',
        ]
    ],
    [
        'slug' => 'informante',
        'name' => 'Informante de la Mafia',
        'description' => 'Acceso a los contactos subterráneos, filtración de rumores y comercio de datos confidenciales.',
        'category' => 'sigilo',
        'icon' => 'fa-users',
        'sort_order' => 100,
        'unlocks' => [
            '1' => 'Acceso a rumores locales. Bonus +1 en espionaje.',
            '2' => 'Contactos en el bajo mundo de Yorknew. Descuento menor en compra de información.',
            '3' => 'Soplón: recibir una pista confidencial sobre el staff o eventos en curso (1 vez por arco).',
            '4' => 'Bonus +2 en espionaje (total +3). Localizar casas seguras y rutas de escape urbanas.',
            '5' => 'Red de Información: acceso a la base de datos de la mafia o lista negra (1 vez por arco).',
        ]
    ]
];

foreach ($oficios as $o) {
    $escSlug = $db->escape_string($o['slug']);
    $escName = $db->escape_string($o['name']);
    $escDesc = $db->escape_string($o['description']);
    $escCat = $db->escape_string($o['category']);
    $escIcon = $db->escape_string($o['icon']);
    $sort = (int)$o['sort_order'];
    $json = $db->escape_string(json_encode($o['unlocks'], JSON_UNESCAPED_UNICODE));

    $db->write_query("INSERT INTO {$prefix}game_oficios (slug, name, description, category, icon, is_active, sort_order, grado_unlock_json)
        VALUES ('{$escSlug}', '{$escName}', '{$escDesc}', '{$escCat}', '{$escIcon}', 1, {$sort}, '{$json}')");
    echo "[OK] Oficio HxH creado: {$o['name']}\n";
}

// 3. Disciplinas HxH (Sin Haki)
$disciplinas = [
    [
        'slug' => 'cuerpo_a_cuerpo',
        'name' => 'Combate Cuerpo a Cuerpo',
        'description' => 'Combate con puños, patadas y lucha libre. Ideal para potenciadores Nen.',
        'category' => 'combate',
        'icon' => 'fa-fist-raised',
        'sort_order' => 10,
        'requires_esp_rank' => null,
        'staff_grant_only' => 0,
        'fixed_pp_cost' => null,
        'unlocks' => [
            '1' => 'Golpes y combos básicos. Cartas tier 1 accesibles.',
            '2' => 'Golpes potentes con impacto visible en el entorno. Cartas tier 2.',
            '3' => 'Impacto Penetrante: ignora defensas físicas menores. Cartas tier 3.',
            '4' => 'Golpe Sísmico: AoE narrativo en un radio corto. Cartas tier 4.',
            '5' => 'Ola de Impacto: ataques cuerpo a cuerpo a alcance medio sin contacto físico. Cartas tier 5.',
        ]
    ],
    [
        'slug' => 'armas_de_filo',
        'name' => 'Armas de Filo',
        'description' => 'Uso de espadas, katanas, cuchillos y navajas con maestría.',
        'category' => 'combate',
        'icon' => 'fa-sword',
        'sort_order' => 20,
        'requires_esp_rank' => null,
        'staff_grant_only' => 0,
        'fixed_pp_cost' => null,
        'unlocks' => [
            '1' => 'Manejo básico de espadas y cuchillos. Cartas tier 1.',
            '2' => 'Cortes precisos que marcan el escenario. Cartas tier 2.',
            '3' => 'Corte Presurizado: ondas de filo a distancia corta. Cartas tier 3.',
            '4' => 'Corte Múltiple: varias cuchilladas simultáneas. Cartas tier 4.',
            '5' => 'Tajo Definitivo: secciona estructuras; sangrado narrativo severo. Cartas tier 5.',
        ]
    ],
    [
        'slug' => 'armas_de_asta',
        'name' => 'Armas de Asta',
        'description' => 'Uso de lanzas, guadañas y bastones, priorizando el control de distancia.',
        'category' => 'combate',
        'icon' => 'fa-heading', // Icono genérico representativo
        'sort_order' => 30,
        'requires_esp_rank' => null,
        'staff_grant_only' => 0,
        'fixed_pp_cost' => null,
        'unlocks' => [
            '1' => 'Lanzas y tridentes con alcance inherente. Cartas tier 1.',
            '2' => 'Barridos y embestidas de amplio alcance. Cartas tier 2.',
            '3' => 'Barrido Total: AoE de barrido a media distancia. Cartas tier 3.',
            '4' => 'Posición Defensiva: guardia de asta impenetrable. Cartas tier 4.',
            '5' => 'Lanzada del Cielo: lanza el arma y regresa automáticamente a la mano. Cartas tier 5.',
        ]
    ],
    [
        'slug' => 'armas_contundentes',
        'name' => 'Armas Contundentes',
        'description' => 'Manejo de mazos, martillos, porras y bates para romper defensas.',
        'category' => 'combate',
        'icon' => 'fa-gavel',
        'sort_order' => 40,
        'requires_esp_rank' => null,
        'staff_grant_only' => 0,
        'fixed_pp_cost' => null,
        'unlocks' => [
            '1' => 'Mazas y martillos básicos. Cartas tier 1.',
            '2' => 'Golpes que desequilibran o derriban al oponente. Cartas tier 2.',
            '3' => 'Golpe Demoledor: destruye coberturas y daña armaduras. Cartas tier 3.',
            '4' => 'Tremor: onda expansiva en el suelo (AoE). Cartas tier 4.',
            '5' => 'Impacto Sísmico: derriba estructuras y genera aturdimiento masivo. Cartas tier 5.',
        ]
    ],
    [
        'slug' => 'armas_a_distancia',
        'name' => 'Armas a Distancia',
        'description' => 'Uso de arcos, tirachinas, ballestas y armas arrojadizas.',
        'category' => 'combate',
        'icon' => 'fa-bullseye',
        'sort_order' => 50,
        'requires_esp_rank' => null,
        'staff_grant_only' => 0,
        'fixed_pp_cost' => null,
        'unlocks' => [
            '1' => 'Arcos y tirachinas básicos. Cartas tier 1.',
            '2' => 'Disparo a cualquier punto del escenario sin interacción previa. Cartas tier 2.',
            '3' => 'Disparo Predictivo: neutraliza esquivas básicas enemigas. Cartas tier 3.',
            '4' => 'Lluvia de Proyectiles: ataca hasta 3 objetivos en el mismo turno. Cartas tier 4.',
            '5' => 'Tiro Imposible: ignora coberturas físicas completas. Cartas tier 5.',
        ]
    ],
    [
        'slug' => 'armas_de_fuego',
        'name' => 'Armas de Fuego',
        'description' => 'Pistolas, revólveres, rifles y ametralladoras convencionales.',
        'category' => 'combate',
        'icon' => 'fa-crosshairs',
        'sort_order' => 60,
        'requires_esp_rank' => null,
        'staff_grant_only' => 0,
        'fixed_pp_cost' => null,
        'unlocks' => [
            '1' => 'Pistolas y rifles básicos. Cartas tier 1.',
            '2' => 'Disparos que perforan materiales ligeros. Cartas tier 2.',
            '3' => 'Disparo Cargado: preparación visible, potencia devastadora. Cartas tier 3.',
            '4' => 'Fuego Sostenido: ráfaga continua en un post. Cartas tier 4.',
            '5' => 'Bala Perforadora: atraviesa barreras no mágicas o armaduras de acero. Cartas tier 5.',
        ]
    ],
    [
        'slug' => 'armas_exoticas',
        'name' => 'Armas Exóticas',
        'description' => 'Manejo de látigos, cadenas, abanicos de combate y otras armas inusuales.',
        'category' => 'combate',
        'icon' => 'fa-link',
        'sort_order' => 70,
        'requires_esp_rank' => null,
        'staff_grant_only' => 0,
        'fixed_pp_cost' => null,
        'unlocks' => [
            '1' => 'Látigos y cadenas básicos. Cartas tier 1.',
            '2' => 'Combate cuerpo a cuerpo y distancia corta alternable en un turno. Cartas tier 2.',
            '3' => 'Técnica Versátil: combina dos movimientos en un post. Cartas tier 3.',
            '4' => 'Inmovilización: atrapa o restringe hasta el siguiente post. Cartas tier 4.',
            '5' => 'Arte Caótico: hasta tres efectos en un solo combo. Cartas tier 5.',
        ]
    ],
    [
        'slug' => 'escudo',
        'name' => 'Defensa con Escudo',
        'description' => 'Maestría defensiva utilizando escudos ligeros, pesados o tácticos.',
        'category' => 'defensa',
        'icon' => 'fa-shield-alt',
        'sort_order' => 80,
        'requires_esp_rank' => null,
        'staff_grant_only' => 0,
        'fixed_pp_cost' => null,
        'unlocks' => [
            '1' => 'Bloqueos y guardias básicas con escudo. Cartas tier 1.',
            '2' => 'Bloqueos que absorben ataques físicos menores. Cartas tier 2.',
            '3' => 'Contra-Golpe: contraataque inmediato tras un bloqueo exitoso. Cartas tier 3.',
            '4' => 'Muro Viviente: protección sobre un aliado en el post. Cartas tier 4.',
            '5' => 'Escudo Indestructible: niega un ataque (1 vez por hilo, no consecutiva). Cartas tier 5.',
        ]
    ]
];

foreach ($disciplinas as $d) {
    $escSlug = $db->escape_string($d['slug']);
    $escName = $db->escape_string($d['name']);
    $escDesc = $db->escape_string($d['description']);
    $escCat = $db->escape_string($d['category']);
    $escIcon = $db->escape_string($d['icon']);
    $sort = (int)$d['sort_order'];
    $espSql = $d['requires_esp_rank'] !== null ? (int)$d['requires_esp_rank'] : 'NULL';
    $staffSql = (int)$d['staff_grant_only'];
    $fixedSql = $d['fixed_pp_cost'] !== null ? (int)$d['fixed_pp_cost'] : 'NULL';
    $json = $db->escape_string(json_encode($d['unlocks'], JSON_UNESCAPED_UNICODE));

    $db->write_query("INSERT INTO {$prefix}game_disciplinas (
        slug, name, description, category, icon, is_active, sort_order, requires_esp_rank, staff_grant_only, fixed_pp_cost, grado_unlock_json
    ) VALUES (
        '{$escSlug}', '{$escName}', '{$escDesc}', '{$escCat}', '{$escIcon}', 1, {$sort}, {$espSql}, {$staffSql}, {$fixedSql}, '{$json}'
    )");
    echo "[OK] Disciplina HxH creada: {$d['name']}\n";
}

echo "\n[OK] Semilla de Oficios y Disciplinas finalizada.\n";
