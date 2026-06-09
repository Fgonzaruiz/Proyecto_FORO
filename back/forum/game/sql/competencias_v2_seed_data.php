<?php
declare(strict_types=1);

/**
 * Datos de seed para migrate_competencias_v2.php (oficios, disciplinas Haki, grado_unlock_json).
 *
 * @return array{oficios: list<array>, disciplinas_haki: list<array>, oficio_unlocks: array<string, array<string, string>>, disciplina_unlocks: array<string, array<string, string>>}
 */
function game_competencias_v2_seed_data(): array
{
    $oficios = [
        ['carpintero', 'Carpintero', 'Reparación y construcción de barcos.', 'crafteo', 'fa-ship', 55],
        ['domador', 'Domador', 'Vínculo con bestias del mundo.', 'utilidad', 'fa-paw', 60],
        ['arqueologo', 'Arqueólogo', 'Historia, ruinas y poneglyphs.', 'lore', 'fa-scroll', 65],
        ['musico', 'Músico/Artista', 'Carisma, moral y desconcierto.', 'utilidad', 'fa-music', 70],
        ['espia', 'Espía/Infiltrador', 'Sigilo, disfraz e información.', 'sigilo', 'fa-mask', 75],
        ['mercader', 'Mercader', 'Comercio, economía y tratos.', 'economia', 'fa-coins', 80],
    ];

    $disciplinasHaki = [];

    $oficioUnlocks = [
        'navegante' => [
            '1' => 'Puede leer el Log Pose e iniciar viajes simples. Oráculos de navegación básicos. Crafteo tier 1.',
            '2' => 'Bonus +1 en oráculos de navegación. Reduce en 1 el peligro efectivo de la ruta. Crafteo tier 2.',
            '3' => 'Lectura de Clima: una vez por viaje, el oráculo climático tira dos veces y se queda el mejor resultado.',
            '4' => 'Bonus +2 en oráculos de navegación (total +3). Puede trazar rutas hacia islas sin ruta registrada.',
            '5' => 'Gran Línea: cruza zonas de peligro extremo. 25% de ignorar eventos negativos de navegación.',
        ],
        'medico' => [
            '1' => 'Tratamiento básico de heridas. Oráculos de diagnóstico.',
            '2' => 'Bonus +1 en oráculos de curación. Recuperación de PV a aliados al final de arco.',
            '3' => 'Operación de Emergencia: estabiliza aliado a 0 PV dejándolo a 1 PV (1 vez por hilo de combate).',
            '4' => 'Bonus +2 en oráculos de veneno y enfermedad (total +3). Crafteo tier 4 medicinales.',
            '5' => 'Tratamiento Milagroso: curación completa fuera de combate (1 vez por arco, post 300+ palabras).',
        ],
        'cocinero' => [
            '1' => 'Crafteo tier 1: consumibles alimenticios. Rol narrativo en celebraciones.',
            '2' => 'Bonus +1 en oráculos de supervivencia. Consumibles crafteados +10% recuperación PE/PV.',
            '3' => 'Bento de Batalla: consumible semanal con +5% PV máximo durante un hilo.',
            '4' => 'Crafteo tier 4. Bonus +2 en oráculos de provisiones (total +3).',
            '5' => 'Festín Épico: buff +10% PV y PE para participantes en el siguiente combate (1 vez por arco).',
        ],
        'herrero' => [
            '1' => 'Crafteo tier 1: armas y armaduras simples. Reparación narrativa de equipo.',
            '2' => 'Crafteo tier 2. Propiedad menor en ítems forjados. Bonus +1 en oráculos de forja.',
            '3' => 'Forja Reforzada: ítems crafteados +5% al stat relevante al equipar. Crafteo tier 3.',
            '4' => 'Crafteo tier 4: armas con dos propiedades. Bonus +2 en oráculos de forja (total +3).',
            '5' => 'Obra Maestra: intentar forjar ítem Legendario (1 vez por mes real, materiales raros).',
        ],
        'carpintero' => [
            '1' => 'Reparación menor del barco fuera de combate. Crafteo tier 1: mejoras básicas.',
            '2' => 'Crafteo tier 2. Bonus +1 en oráculos de reparación naval.',
            '3' => 'Reparación de Emergencia: recupera 20% PV del barco (1 vez por hilo naval). Crafteo tier 3.',
            '4' => 'Crafteo tier 4: mejoras avanzadas. Bonus +2 en reparación naval (total +3).',
            '5' => 'Arquitecto Naval: diseñar barco único (1 vez por nivel de PJ, materiales raros).',
        ],
        'cientifico' => [
            '1' => 'Crafteo tier 1: gadgets simples. Oráculos de investigación.',
            '2' => 'Crafteo tier 2: trampas. Bonus +1 en oráculos de investigación.',
            '3' => 'Prototipo: carta de equipo única con propiedad mecánica (1 vez por nivel de PJ).',
            '4' => 'Crafteo tier 4. Bonus +2 en oráculos de tecnología (total +3).',
            '5' => 'Invención Revolucionaria: proponer ítem nuevo al catálogo general.',
        ],
        'domador' => [
            '1' => 'Cartas npc_menor bestia en inventario. Bonus +1 en oráculos de bestias.',
            '2' => 'Bestias +10% PV base. Domesticación por oráculo (grado II+).',
            '3' => 'Vínculo: bestia actúa independiente en un post (1 vez por hilo).',
            '4' => 'Solicitar bestia única. Crafteo tier 4: equipo para bestias.',
            '5' => 'Manada: hasta 3 bestias activas en combate. Bestias +1 rango efectivo en stats.',
        ],
        'arqueologo' => [
            '1' => 'Bonus +1 en oráculos de descubrimiento. Leer ruinas en posts narrativos.',
            '2' => 'Descifrar textos antiguos y poneglyphs parciales. Bonus +2 en historia.',
            '3' => 'Conocimiento Ancestral: pista de lore del staff (1 vez por arco).',
            '4' => 'Oráculos exclusivos de secretos del mundo. Bonus +2 en historia.',
            '5' => 'Poneglyph Completo: leer poneglyphs completos. Acceso a lore máximo.',
        ],
        'musico' => [
            '1' => 'Aliados +1 en oráculos de voluntad en hilos donde participa.',
            '2' => 'Bonus +1 moral/intimidación. Actuación de batalla: +5 PE temporal (1/hilo).',
            '3' => 'Canción Perturbadora: -1 a inst del objetivo durante 2 posts (1/hilo).',
            '4' => 'Bonus +2 en moral (total +3). Actuaciones afectan tripulación del barco.',
            '5' => 'Melodía Legendaria: cambiar oráculo de rendición de PNJ relevante (1/arco).',
        ],
        'espia' => [
            '1' => 'Acciones de sigilo. Bonus +1 en oráculos de sigilo y espionaje.',
            '2' => 'Identidad alternativa (narrativo). Bonus +2 en oráculos de engaño.',
            '3' => 'Infiltración: acción encubierta con oráculo bonus adicional.',
            '4' => 'Bonus +2 en sigilo (total +3). Acceso a zonas restringidas como observador.',
            '5' => 'Maestro del Engaño: impersonar con consentimiento. Bonus +3 acumulado en sigilo/engaño.',
        ],
        'mercader' => [
            '1' => 'Descuento 5% en tienda. Oráculos de mercado.',
            '2' => 'Descuento 10%. Intercambio entre PJs aprobado por staff.',
            '3' => 'Trato Especial: ítem fuera de stock (1/semana in-game). Descuento 15%.',
            '4' => 'Tienda propia narrativa. Bonus +2 en oráculos de mercado (total +3).',
            '5' => 'Monopolio: descuento 20%. Influir en economía de una isla (evento staff).',
        ],
    ];

    $disciplinaUnlocks = [
        'cuerpo_a_cuerpo' => [
            '1' => 'Golpes y combos básicos. Cartas tier 1 accesibles.',
            '2' => 'Golpes potentes con impacto visible en el entorno. Cartas tier 2.',
            '3' => 'Impacto Penetrante: ignora defensas físicas menores. Cartas tier 3.',
            '4' => 'Golpe Sísmico: AoE narrativo en radio. Cartas tier 4.',
            '5' => 'Ola de Impacto: ataques cuerpo a cuerpo a alcance medio sin contacto. Cartas tier 5.',
        ],
        'armas_de_filo' => [
            '1' => 'Manejo básico de espadas y cuchillos. Cartas tier 1.',
            '2' => 'Cortes precisos que marcan el entorno. Cartas tier 2.',
            '3' => 'Corte Presurizado: ondas de filo a distancia corta. Cartas tier 3.',
            '4' => 'Corte Múltiple: varias cuchilladas simultáneas. Cartas tier 4.',
            '5' => 'Tajo Definitivo: secciona estructuras; sangrado narrativo. Cartas tier 5.',
        ],
        'armas_de_asta' => [
            '1' => 'Lanzas y tridentes con alcance inherente. Cartas tier 1.',
            '2' => 'Barridos y embestidas de amplio alcance. Cartas tier 2.',
            '3' => 'Barrido Total: AoE de barrido. Cartas tier 3.',
            '4' => 'Posición Defensiva: guardia de asta. Cartas tier 4.',
            '5' => 'Lanzada del Cielo: lanza el arma y regresa a la mano. Cartas tier 5.',
        ],
        'armas_contundentes' => [
            '1' => 'Mazas y martillos básicos. Cartas tier 1.',
            '2' => 'Golpes que desequilibran o derriban. Cartas tier 2.',
            '3' => 'Golpe Demoledor: destruye objetos y daña armadura rival. Cartas tier 3.',
            '4' => 'Tremor: onda expansiva en el suelo (AoE). Cartas tier 4.',
            '5' => 'Impacto Sísmico: derriba estructuras del escenario. Cartas tier 5.',
        ],
        'armas_a_distancia' => [
            '1' => 'Arcos y tirachinas básicos. Cartas tier 1.',
            '2' => 'Disparo a cualquier punto del hilo sin interacción previa. Cartas tier 2.',
            '3' => 'Disparo Predictivo: neutraliza esquivas básicas. Cartas tier 3.',
            '4' => 'Lluvia de Flechas: hasta 3 objetivos. Cartas tier 4.',
            '5' => 'Tiro Imposible: ignora cobertura física. Cartas tier 5.',
        ],
        'armas_de_fuego' => [
            '1' => 'Pistolas y rifles básicos. Cartas tier 1.',
            '2' => 'Disparos que perforan materiales ligeros. Cartas tier 2.',
            '3' => 'Disparo Cargado: preparación visible, potencia devastadora. Cartas tier 3.',
            '4' => 'Fuego Sostenido: ráfaga en un post. Cartas tier 4.',
            '5' => 'Bala Perforadora: atraviesa barreras no mágicas. Cartas tier 5.',
        ],
        'armas_exoticas' => [
            '1' => 'Látigos y cadenas básicos. Cartas tier 1.',
            '2' => 'Cuerpo a cuerpo y distancia corta en el mismo post. Cartas tier 2.',
            '3' => 'Técnica Versátil: combina dos movimientos en un post. Cartas tier 3.',
            '4' => 'Inmovilización: atrapa o restringe hasta el siguiente turno. Cartas tier 4.',
            '5' => 'Arte Caótico: hasta tres efectos en un combo. Cartas tier 5.',
        ],
        'escudo' => [
            '1' => 'Bloqueos y guardias básicos. Cartas tier 1.',
            '2' => 'Bloqueos que absorben ataques menores. Cartas tier 2.',
            '3' => 'Contra-Golpe: contraataque tras bloqueo exitoso. Cartas tier 3.',
            '4' => 'Muro Viviente: protección sobre un aliado. Cartas tier 4.',
            '5' => 'Escudo Indestructible: niega un ataque (1/hilo, no dos posts seguidos). Cartas tier 5.',
        ],
        'haki_conquistador' => [
            '1' => 'PNJs tier 1 caen inconscientes sin tirada en multitudes.',
            '2' => 'Afecta rivales con ESP bajo (< rango 2). Cartas tier 2.',
            '3' => 'Oleada de Conquista: AoE masivo vs enemigos de menor nivel. Cartas tier 3.',
            '4' => 'Infundir armas con Haki de Conquistador. Cartas tier 4 exclusivas.',
            '5' => 'Rey de los Mares: debuff pasivo; forzar rendición PNJ mayor (1/arco). Cartas tier 5.',
        ],
    ];

    return [
        'oficios' => $oficios,
        'disciplinas_haki' => $disciplinasHaki,
        'oficio_unlocks' => $oficioUnlocks,
        'disciplina_unlocks' => $disciplinaUnlocks,
    ];
}
