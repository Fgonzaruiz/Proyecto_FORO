<?php
declare(strict_types=1);

/**
 * Catálogo IC de estilos canónicos y cartas técnicas de ejemplo asociadas.
 *
 * @return array{estilos: list<array<string, mixed>>, cartas: list<array<string, mixed>>}
 */
function game_estilos_canonicos_seed_data(): array
{
    $estilos = [
        [
            'slug' => 'karate_gyojin',
            'name' => 'Karate Gyojin',
            'category' => 'artes_marciales',
            'category_label' => 'Artes marciales',
            'disciplina_slug' => 'cuerpo_a_cuerpo',
            'primary_stat' => 'fuerza',
            'short_desc' => 'Arte marcial submarino de los gyojin; golpes de puño que aprovechan la hidrodinámica.',
            'description' => 'Tradición de combate cuerpo a cuerpo desarrollada en el Reino Ryugu y entre pescadores de élite. Prioriza postura baja, desplazamiento en agua y impactos concentrados en puntos vitales.',
            'requirements' => [
                'Disciplina Cuerpo a Cuerpo grado II o superior',
                'FUE efectiva rango C+ (o narrativa de entrenamiento gyojin)',
                'Raza Gyojin, o aprendiz aceptado por un maestro del estilo',
            ],
            'advantages' => [
                'Combate sin penalización narrativa bajo el agua',
                'Puños y patadas reciben contexto de daño contundente reforzado en medio acuático',
                'Acceso a técnicas de barrido y rompimiento de defensa marina',
            ],
            'sort_order' => 10,
        ],
        [
            'slug' => 'okama_kenpo',
            'name' => 'Okama Kenpō',
            'category' => 'artes_marciales',
            'category_label' => 'Artes marciales',
            'disciplina_slug' => 'cuerpo_a_cuerpo',
            'primary_stat' => 'destreza',
            'short_desc' => 'Estilo acrobático del Reino Kamabakka; mezcla baile, ritmo y golpes impredecibles.',
            'description' => 'Escuela icónica del Nuevo Mundo que combina agilidad extrema, lectura del ritmo enemigo y técnicas de impacto sorpresa. Muy orientado al duelo uno a uno y al desequilibrio del rival.',
            'requirements' => [
                'Disciplina Cuerpo a Cuerpo grado II o superior',
                'AGI o DES efectiva rango C+',
                'Entrenamiento IC en Kamabakka o maestro reconocido del estilo',
            ],
            'advantages' => [
                'Bonificación narrativa en esquives acrobáticos y contraataques',
                'Técnicas que pueden imponer estados de desorientación o apertura',
                'Sinergia con cartas de movimiento y evasión',
            ],
            'sort_order' => 20,
        ],
        [
            'slug' => 'rokushiki',
            'name' => 'Rokushiki',
            'category' => 'estilo_especial',
            'category_label' => 'Técnica de combate especial',
            'disciplina_slug' => 'cuerpo_a_cuerpo',
            'primary_stat' => 'agilidad',
            'short_desc' => 'Las seis habilidades marciales del Gobierno Mundial: superación del cuerpo humano.',
            'description' => 'Conjunto de técnicas (Soru, Geppo, Tekkai, Shigan, Rankyaku, Kami-e) enseñadas a agentes de élite. Cada carta representa una de las artes; dominar el set completo es un hito narrativo de nivel alto.',
            'requirements' => [
                'Disciplina Cuerpo a Cuerpo grado III o superior',
                'Nivel de personaje 3+',
                'Entrenamiento IC con instructor CP o equivalente aprobado por staff',
            ],
            'advantages' => [
                'Movilidad superior (Soru/Geppo) y defensa rígida (Tekkai)',
                'Ataques a distancia con Rankyaku y perforación con Shigan',
                'Evasión pasiva con Kami-e en posts defensivos',
            ],
            'sort_order' => 30,
        ],
        [
            'slug' => 'santoryu',
            'name' => 'Santōryū',
            'category' => 'esgrima',
            'category_label' => 'Esgrima / espadas',
            'disciplina_slug' => 'armas_de_filo',
            'primary_stat' => 'fuerza',
            'short_desc' => 'Estilo de tres espadas simultáneas; potencia bruta y cobertura de ángulos.',
            'description' => 'Escuela de esgrima poco convencional que exige dominio del filo en tres planos. Ideal para personajes que buscan presión constante y finalizadores de alto impacto.',
            'requirements' => [
                'Disciplina Armas de Filo grado II o superior',
                'FUE efectiva rango C+',
                'Tres armas de filo equipables o narrativa de entrenamiento santōryū',
            ],
            'advantages' => [
                'Múltiples líneas de ataque en un mismo intercambio',
                'Técnicas de corte amplio con ventaja narrativa contra defensas frontales',
                'Combo natural con cartas de daño en área',
            ],
            'sort_order' => 40,
        ],
        [
            'slug' => 'ittoryu',
            'name' => 'Ittōryū',
            'category' => 'esgrima',
            'category_label' => 'Esgrima / espadas',
            'disciplina_slug' => 'armas_de_filo',
            'primary_stat' => 'destreza',
            'short_desc' => 'Esgrima de una espada; precisión, iaijutsu y corte en un solo movimiento.',
            'description' => 'Estilo clásico de espadachines del Grand Line. Enfatiza lectura del rival, desenfundado rápido y un golpe decisivo. Mecánicamente ligado a DES y disciplina de filo.',
            'requirements' => [
                'Disciplina Armas de Filo grado II o superior',
                'DES efectiva rango C+',
                'Una espada equipada o carta de arma de filo en el mazo',
            ],
            'advantages' => [
                'Bonificación narrativa en primer golpe del duelo o tras esquive',
                'Técnicas de contra con ventaja si el rival falla un ataque',
                'Menor coste de PE en técnicas de precisión (según carta)',
            ],
            'sort_order' => 50,
        ],
        [
            'slug' => 'black_leg',
            'name' => 'Black Leg',
            'category' => 'artes_marciales',
            'category_label' => 'Artes marciales',
            'disciplina_slug' => 'cuerpo_a_cuerpo',
            'primary_stat' => 'fuerza',
            'short_desc' => 'Arte de patadas del Baratie; solo piernas, nunca puños.',
            'description' => 'Filosofía de combate que prohíbe usar las manos para atacar. Patadas circulares, saltos y rotaciones. Muy visual en rol y fuerte en control de espacio.',
            'requirements' => [
                'Disciplina Cuerpo a Cuerpo grado II o superior',
                'FUE o AGI efectiva rango C+',
                'Juramento IC de no golpear con las manos (salvo excepciones staff)',
            ],
            'advantages' => [
                'Patadas con alcance superior en narrativa',
                'Combo con cartas de desplazamiento y ataque aéreo',
                'Estilo reconocible que facilita identidad de personaje',
            ],
            'sort_order' => 60,
        ],
        [
            'slug' => 'tirador',
            'name' => 'Maestría de tirador',
            'category' => 'tirador',
            'category_label' => 'Tirador / distancia',
            'disciplina_slug' => 'armas_a_distancia',
            'primary_stat' => 'destreza',
            'short_desc' => 'Escuela de combate a distancia con proyectiles, precisión y cobertura.',
            'description' => 'Agrupa francotiradores, arqueros y especialistas en pistolas del Nuevo Mundo. Depende de línea de visión, cobertura y lectura del viento.',
            'requirements' => [
                'Disciplina Armas a Distancia grado II o superior',
                'DES efectiva rango C+',
                'Arma a distancia equipada o carta de equipo equivalente',
            ],
            'advantages' => [
                'Ventaja narrativa en duelos con espacio abierto',
                'Técnicas de precisión con bonificación a primer impacto',
                'Sinergia con cartas de marcación y debilitación',
            ],
            'sort_order' => 70,
        ],
    ];

    $cartas = [
        ['estilo' => 'karate_gyojin', 'name' => 'Karakusagawara Seiken', 'rank' => 'C', 'dice' => '2d20+fue', 'cost_pe' => '12', 'description' => 'Puñetazo endurecido con agua comprimida; impacto contundente a corta distancia.'],
        ['estilo' => 'karate_gyojin', 'name' => 'Samehada Shotei', 'rank' => 'B', 'dice' => '2d20+fue', 'cost_pe' => '18', 'description' => 'Golpe de palma con vibración marina que rompe guardias bajo el agua.'],
        ['estilo' => 'okama_kenpo', 'name' => 'Estética Impactante', 'rank' => 'C', 'dice' => '2d20+des', 'cost_pe' => '10', 'description' => 'Golpe de puño con giro acrobático; puede abrir la guardia del rival.'],
        ['estilo' => 'okama_kenpo', 'name' => 'Parada de Kamabakka', 'rank' => 'B', 'dice' => '1d20+agi', 'cost_pe' => '14', 'description' => 'Evasión con contraataque inmediato si el rival falla.'],
        ['estilo' => 'rokushiki', 'name' => 'Soru', 'rank' => 'B', 'dice' => '1d20+agi', 'cost_pe' => '8', 'description' => 'Desplazamiento explosivo para reposicionarse o esquivar.'],
        ['estilo' => 'rokushiki', 'name' => 'Rankyaku', 'rank' => 'A', 'dice' => '2d20+des', 'cost_pe' => '22', 'description' => 'Corte de presión a distancia con la pierna.'],
        ['estilo' => 'rokushiki', 'name' => 'Tekkai', 'rank' => 'B', 'dice' => '', 'cost_pe' => '10', 'description' => 'Endurece el cuerpo; reduce daño recibido ese intercambio (declarativo).'],
        ['estilo' => 'santoryu', 'name' => 'Oni Giri', 'rank' => 'B', 'dice' => '2d20+fue', 'cost_pe' => '20', 'description' => 'Tres espadas en espiral; ataque frontal de alto impacto.'],
        ['estilo' => 'santoryu', 'name' => 'Yakkodori', 'rank' => 'C', 'dice' => '2d20+des', 'cost_pe' => '14', 'description' => 'Corte ascendente con las tres hojas al unísono.'],
        ['estilo' => 'ittoryu', 'name' => 'Iai cortante', 'rank' => 'C', 'dice' => '2d20+des', 'cost_pe' => '12', 'description' => 'Desenfundado y corte en un solo movimiento.'],
        ['estilo' => 'ittoryu', 'name' => 'Corte del dragón', 'rank' => 'A', 'dice' => '2d20+fue', 'cost_pe' => '24', 'description' => 'Tajo vertical cargado; técnica decisiva de duelo.'],
        ['estilo' => 'black_leg', 'name' => 'Collier', 'rank' => 'C', 'dice' => '2d20+fue', 'cost_pe' => '12', 'description' => 'Patada descendente al cuello o torso.'],
        ['estilo' => 'black_leg', 'name' => 'Diable Jambe', 'rank' => 'A', 'dice' => '2d20+fue', 'cost_pe' => '26', 'description' => 'Patada ígnea; daño elevado a costa de mayor PE.'],
        ['estilo' => 'tirador', 'name' => 'Disparo de precisión', 'rank' => 'C', 'dice' => '2d20+des', 'cost_pe' => '10', 'description' => 'Tiro a punto vital con bonificación narrativa si hay línea clara.'],
        ['estilo' => 'tirador', 'name' => 'Ráfaga supresiva', 'rank' => 'B', 'dice' => '3d20+des', 'cost_pe' => '18', 'description' => 'Varios proyectiles para forzar cobertura o daño en área reducida.'],
    ];

    return ['estilos' => $estilos, 'cartas' => $cartas];
}
