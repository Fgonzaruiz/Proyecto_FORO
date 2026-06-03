<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer;

// Seguridad: Solo personajes staff pueden ejecutar este script
if ((int)($mybb->user['uid'] ?? 0) === 0 || (int)($mybb->usergroup['cancp'] ?? 0) !== 1) {
    error_no_permission();
}
game_require_staff_character();

$prefix = TABLE_PREFIX;

// Helper para ejecutar consultas y mostrar estado
function run_sql(string $sql, string $description): void {
    global $db;
    if ($db->write_query($sql)) {
        echo "<div class='rpg-admin-ok'>[OK] {$description}</div>";
    } else {
        echo "<div class='rpg-admin-error'>[ERROR] {$description}: " . htmlspecialchars($db->error()) . "</div>";
    }
}

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Instalador del Sistema RPG - Base de Datos</title>
    <link rel="stylesheet" href="{$mybb->settings['bburl']}/rpg_custom.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #f8fafc; padding: 30px; max-width: 800px; margin: 0 auto; }
        h1 { color: #818cf8; border-bottom: 2px solid #334155; padding-bottom: 10px; }
        .log-container { background: #1e293b; padding: 20px; border-radius: 8px; border: 1px solid #334155; margin-bottom: 20px; }
        .btn { display: inline-block; background: #4f46e5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .btn:hover { background: #4338ca; }
    </style>
</head>
<body>
    <h1>Instalador de Base de Datos del RPG</h1>
    <div class='rpg-admin-pre rpg-admin-log-box'>";

// 1. Eliminar tablas existentes (si existieran)
run_sql("DROP TABLE IF EXISTS {$prefix}game_personajes_revisiones", "Eliminando tabla de revisiones");
run_sql("DROP TABLE IF EXISTS {$prefix}game_user_config", "Eliminando tabla de configuración de usuarios");
run_sql("DROP TABLE IF EXISTS {$prefix}game_post_characters", "Eliminando tabla de personajes por post");
run_sql("DROP TABLE IF EXISTS {$prefix}game_tecnicas", "Eliminando tabla de técnicas");
run_sql("DROP TABLE IF EXISTS {$prefix}game_estilos", "Eliminando tabla de estilos");
run_sql("DROP TABLE IF EXISTS {$prefix}game_npc_profiles", "Eliminando tabla de NPCs");
run_sql("DROP TABLE IF EXISTS {$prefix}game_personajes", "Eliminando tabla de personajes");
run_sql("DROP TABLE IF EXISTS {$prefix}game_akuma_no_mi", "Eliminando tabla de Akuma no Mi");
run_sql("DROP TABLE IF EXISTS {$prefix}game_objetos", "Eliminando tabla de objetos");
run_sql("DROP TABLE IF EXISTS {$prefix}game_tripulaciones", "Eliminando tabla de tripulaciones");
run_sql("DROP TABLE IF EXISTS {$prefix}game_historia", "Eliminando tabla de historia");
run_sql("DROP TABLE IF EXISTS {$prefix}game_thread_meta", "Eliminando tabla de metadatos de hilos");

// 2. Crear tablas
$sql_npcs = "CREATE TABLE {$prefix}game_npc_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    imagen VARCHAR(500) NOT NULL DEFAULT '',
    tripulacion_id INT DEFAULT NULL,
    banner VARCHAR(255) NOT NULL DEFAULT 'images/game/npc_banner.png',
    identificacion JSON NOT NULL,
    perfil_fisico JSON NOT NULL,
    psicologia JSON NOT NULL,
    motivaciones JSON NOT NULL,
    perfil_estrategico JSON NOT NULL,
    cronologia JSON NOT NULL,
    relaciones JSON NOT NULL,
    stats JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_npc_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run_sql($sql_npcs, "Creando tabla de NPCs (perfiles JSON)");

$sql_trip = "CREATE TABLE {$prefix}game_tripulaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    imagen VARCHAR(500) NOT NULL DEFAULT '',
    descripcion TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run_sql($sql_trip, "Creando tabla de tripulaciones");

$sql_personajes = "CREATE TABLE {$prefix}game_personajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    race VARCHAR(50) NOT NULL,
    race_name VARCHAR(100) NOT NULL,
    occupation VARCHAR(50) NOT NULL,
    occupation_name VARCHAR(100) NOT NULL,
    `desc` TEXT NOT NULL,
    details TEXT NOT NULL,
    rango VARCHAR(100) NOT NULL,
    tripulacion VARCHAR(255) NOT NULL,
    recompensa VARCHAR(100) NOT NULL,
    banner VARCHAR(255) NOT NULL,
    avatar VARCHAR(500) NOT NULL DEFAULT '',
    is_staff TINYINT(1) NOT NULL DEFAULT 0,
    staff_level TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    postnum INT NOT NULL DEFAULT 0,
    threadnum INT NOT NULL DEFAULT 0,
    data_json LONGTEXT,
    stats_json LONGTEXT,
    faction VARCHAR(100) DEFAULT '',
    approved TINYINT(1) DEFAULT 0,
    cronologia_json LONGTEXT,
    tecnicas_json LONGTEXT,
    gestion_json LONGTEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run_sql($sql_personajes, "Creando tabla de personajes");

$sql_user_config = "CREATE TABLE {$prefix}game_user_config (
    user_id INT PRIMARY KEY,
    max_slots INT NOT NULL DEFAULT 1,
    slots_used INT NOT NULL DEFAULT 0,
    active_pj_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run_sql($sql_user_config, "Creando tabla de configuración de usuarios");

$sql_post_chars = "CREATE TABLE {$prefix}game_post_characters (
    post_id INT PRIMARY KEY,
    thread_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    character_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_thread_id (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run_sql($sql_post_chars, "Creando tabla de personajes por post");

$sql_revisiones = "CREATE TABLE {$prefix}game_personajes_revisiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personaje_id INT NOT NULL,
    staff_user_id INT NOT NULL,
    staff_char_id INT NOT NULL,
    status_anterior VARCHAR(20) NOT NULL DEFAULT '',
    status_nuevo VARCHAR(20) NOT NULL DEFAULT '',
    mensaje TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_personaje (personaje_id),
    INDEX idx_staff (staff_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
run_sql($sql_revisiones, "Creando tabla de revisiones de personajes");

$sql_estilos = "CREATE TABLE {$prefix}game_estilos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    type_name VARCHAR(100) NOT NULL,
    req VARCHAR(50) NOT NULL,
    req_name VARCHAR(100) NOT NULL,
    `desc` TEXT NOT NULL,
    details TEXT NOT NULL,
    req_fp VARCHAR(50) NOT NULL,
    req_dp VARCHAR(50) NOT NULL,
    consumo_estamina VARCHAR(50) NOT NULL,
    dificultad VARCHAR(50) NOT NULL,
    banner VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run_sql($sql_estilos, "Creando tabla de estilos");

$sql_tecnicas = "CREATE TABLE {$prefix}game_tecnicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estilo_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    `desc` TEXT NOT NULL,
    energy_cost VARCHAR(50) NOT NULL,
    damage VARCHAR(50) NOT NULL,
    FOREIGN KEY (estilo_id) REFERENCES {$prefix}game_estilos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run_sql($sql_tecnicas, "Creando tabla de técnicas");

$sql_akumas = "CREATE TABLE {$prefix}game_akuma_no_mi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    class VARCHAR(50) NOT NULL,
    class_name VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL,
    status_name VARCHAR(100) NOT NULL,
    `desc` TEXT NOT NULL,
    details TEXT NOT NULL,
    tipo_fruta VARCHAR(100) NOT NULL,
    usuario_actual VARCHAR(255) NOT NULL,
    habilidad_clave VARCHAR(255) NOT NULL,
    precio VARCHAR(100) NOT NULL,
    banner VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run_sql($sql_akumas, "Creando tabla de Akuma no Mi");

$sql_objetos = "CREATE TABLE {$prefix}game_objetos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    rarity VARCHAR(50) NOT NULL,
    rarity_name VARCHAR(100) NOT NULL,
    `desc` TEXT NOT NULL,
    details TEXT NOT NULL,
    tipo_objeto VARCHAR(100) NOT NULL,
    bono VARCHAR(255) NOT NULL,
    req_uso VARCHAR(255) NOT NULL,
    precio VARCHAR(100) NOT NULL,
    banner VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run_sql($sql_objetos, "Creando tabla de objetos");

$sql_historia = "CREATE TABLE {$prefix}game_historia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    saga VARCHAR(50) NOT NULL,
    saga_name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    type_name VARCHAR(100) NOT NULL,
    `desc` TEXT NOT NULL,
    details TEXT NOT NULL,
    epoca VARCHAR(100) NOT NULL,
    ubicacion VARCHAR(255) NOT NULL,
    personajes VARCHAR(255) NOT NULL,
    impacto VARCHAR(255) NOT NULL,
    banner VARCHAR(255) NOT NULL,
    event_date VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run_sql($sql_historia, "Creando tabla de historia");

$sql_thread_meta = "CREATE TABLE {$prefix}game_thread_meta (
    thread_id INT PRIMARY KEY,
    thread_type VARCHAR(20) NOT NULL DEFAULT 'Presente',
    day INT NOT NULL DEFAULT 1,
    season INT NOT NULL DEFAULT 0,
    year INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run_sql($sql_thread_meta, "Creando tabla de metadatos de hilos (tipo/fecha)");

$sql_card_requests = "CREATE TABLE IF NOT EXISTS {$prefix}game_card_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    character_id INT NOT NULL,
    card_id INT NOT NULL,
    request_type ENUM('upgrade', 'delete') NOT NULL,
    status ENUM('pendiente', 'aprobada', 'rechazada') NOT NULL DEFAULT 'pendiente',
    current_rank VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_by INT DEFAULT NULL,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    staff_message TEXT DEFAULT NULL,
    KEY idx_character (character_id),
    KEY idx_card (card_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
run_sql($sql_card_requests, "Creando tabla de solicitudes de cartas (upgrade/delete)");

// 3. Poblar tablas
echo "<h3>Poblando datos de juego...</h3>";

// 3.1 NPCs (perfiles JSON)
$npc_data = [
    [
        'Capitán Morgan "Mano de Hacha"',
        'https://picsum.photos/seed/morgan/200/200',
        1,
        '{
            "apodos": ["Mano de Hacha", "Tirano de Shells Town"],
            "edad": 42,
            "raza": "Humano",
            "afiliacion": "Marina",
            "ocupacion": "Capitán de Base",
            "estado_actual": "En busca y captura tras su derrota"
        }',
        '{
            "apariencia_general": "Hombre corpulento con un hacha enorme incrustada en el antebrazo derecho. Viste el abrigo clásico de la Marina.",
            "rasgos_distintivos": "Un hacha de batalla gigante reemplaza su antebrazo derecho",
            "vestimenta_habitual": "Uniforme de la Marina, capa blanca con hombreras doradas",
            "lenguaje_corporal": "Porte arrogante, pecho inflado, siempre mirando por encima del hombro"
        }',
        '{
            "descripcion": "Arrogante, autoritario y extremadamente vanidoso. Morgan gobierna mediante el miedo y la fuerza bruta. Su ego desmedido nubla su juicio táctico.",
            "arquetipo_principal": "Tirano corrupto",
            "rasgos_positivos": ["Impone orden por terror", "Lealtad al Gobierno Mundial"],
            "rasgos_negativos": ["Corrupto", "Cruel", "Arrogante"],
            "manias_tics": "Se ríe de forma exagerada cuando humilla a alguien",
            "miedos_fobias": "Perder su autoridad y estatus",
            "patron_habla": "Autoritario, se refiere a sí mismo en tercera persona"
        }',
        '{
            "objetivo_corto_plazo": "Recuperar su base y su rango perdido",
            "objetivo_largo_plazo": "Convertirse en Almirante de la Marina",
            "linea_roja": "Jamás mostrar debilidad ante sus subordinados",
            "alineamiento_moral": "Legal Malvado"
        }',
        '{
            "estilo_combate": "Combate cuerpo a cuerpo con su brazo-hacha. Golpes potentes pero lentos y predecibles.",
            "toma_decisiones": "Impulsivo, guiado por la ira y el orgullo",
            "tolerancia_riesgo": "Moderada, confía demasiado en su fuerza",
            "reaccion_presion": "Se vuelve errático y comete errores tácticos",
            "pros_estrategicos": ["Intimidación", "Ataques frontales devastadores"],
            "contras_estrategicas": ["Enemigos rápidos", "Combate en grupo"]
        }',
        '{
            "resumen": "Un capitán corrupto que aterrorizaba Shells Town hasta ser derrotado por Luffy y Zoro en su primer viaje.",
            "origen": "Ascendió en la Marina mediante sobornos y violencia",
            "evento_catalizador": "La llegada de los Sombrero de Paja a Shells Town",
            "hitos_timeline": [
                {"fecha": "Hace 10 años", "suceso": "Asciende a Capitán mediante corrupción"},
                {"fecha": "Hace 2 años", "suceso": "Derrotado por Monkey D. Luffy"}
            ],
            "secreto_inconfesable": "Teme profundamente a Garp y a los Almirantes"
        }',
        '{
            "aliados": [
                {"nombre": "Gobierno Mundial", "motivo": "Lealtad institucional"}
            ],
            "enemigos": [
                {"nombre": "Piratas de Sombrero de Paja", "motivo": "Lo humillaron y derrotaron"},
                {"nombre": "Roronoa Zoro", "motivo": "Le arrancó el brazo de hacha"}
            ],
            "trato_desconocidos": "Despótico, asume superioridad automática"
        }',
        '{"FP": 35, "DP": 20, "RP": 45, "IP": 15, "VP": 30, "HP": 0}'
    ],
    [
        'Kuro "El de las Cien Gatos"',
        'https://picsum.photos/seed/kuro/200/200',
        2,
        '{
            "apodos": ["El de las Cien Gatos", "Klaubert"],
            "edad": 36,
            "raza": "Humano",
            "afiliacion": "Piratas de Kuro",
            "ocupacion": "Capitán Pirata (retirado)",
            "estado_actual": "Vivo, escondido bajo identidad falsa como mayordomo"
        }',
        '{
            "apariencia_general": "Hombre delgado de aspecto refinado, viste traje de mayordomo cuando está encubierto. Manos cubiertas por guantes con garras.",
            "rasgos_distintivos": "Garras de acero largo en cada dedo. Gafas redondas cuando actúa como mayordomo.",
            "vestimenta_habitual": "Traje negro de mayordomo, guantes largos con garras retráctiles",
            "lenguaje_corporal": "Movimientos lentos y calculados; cuando combate se vuelve explosivo"
        }',
        '{
            "descripcion": "Maestro táctico frío y despiadado. Kuro es un genio estratega que fingió su propia muerte para vivir en paz, pero su naturaleza violenta siempre sale a la luz.",
            "arquetipo_principal": "Estratega maquiavélico",
            "rasgos_positivos": ["Inteligente", "Paciente", "Disciplinado"],
            "rasgos_negativos": ["Sádico", "Manipulador", "Cobarde ante rivales poderosos"],
            "manias_tics": "Se ajusta las gafas constantemente cuando miente",
            "miedos_fobias": "Que descubran que sigue vivo",
            "patron_habla": "Frío, susurrante, siempre mide sus palabras"
        }',
        '{
            "objetivo_corto_plazo": "Mantener su tapadera como Klaubert el mayordomo",
            "objetivo_largo_plazo": "Adueñarse de la fortuna de Kaya y vivir como noble",
            "linea_roja": "No dejar testigos vivos de su pasado pirata",
            "alineamiento_moral": "Legal Malvado"
        }',
        '{
            "estilo_combate": "Velocidad extrema con el Shakushi (técnica de paso invisible). Sus garras pueden desgarrar acero.",
            "toma_decisiones": "Calculador, planea múltiples pasos por adelantado",
            "tolerancia_riesgo": "Baja, solo actúa cuando tiene ventaja asegurada",
            "reaccion_presion": "Se retira y replantea la estrategia",
            "pros_estrategicos": ["Velocidad sobrehumana", "Planificación", "Sigilo"],
            "contras_estrategicas": ["Su técnica ciega su visión", "Pánico si el plan falla"]
        }',
        '{
            "resumen": "Un capitán pirata que fingió su muerte para retirarse, pero su plan fue descubierto por Usopp y los Sombrero de Paja.",
            "origen": "Capitán pirata del East Blue con una tripulación leal",
            "evento_catalizador": "Conoció a la heredera Kaya y tramó su asesinato",
            "hitos_timeline": [
                {"fecha": "Hace 3 años", "suceso": "Finge su muerte ante la Marina"},
                {"fecha": "Hace 1 año", "suceso": "Derrotado por Luffy en Villa Syrup"}
            ],
            "secreto_inconfesable": "En realidad jamás ha matado a nadie directamente; usa a sus subordinados"
        }',
        '{
            "aliados": [
                {"nombre": "Jango", "motivo": "Hipnotizador leal"}
            ],
            "enemigos": [
                {"nombre": "Usopp", "motivo": "Descubrió su tapadera"},
                {"nombre": "Monkey D. Luffy", "motivo": "Arruinó su plan en Villa Syrup"}
            ],
            "trato_desconocidos": "Amable y servicial en falso, cuchillo en la manga"
        }',
        '{"FP": 42, "DP": 78, "RP": 30, "IP": 85, "VP": 45, "HP": 0}'
    ],
    [
        'Zeff "Pies Rojos"',
        'https://picsum.photos/seed/zeff/200/200',
        3,
        '{
            "apodos": ["Pies Rojos", "Cocinero Jefe del Baratie"],
            "edad": 58,
            "raza": "Humano",
            "afiliacion": "Civiles",
            "ocupacion": "Chef Ejecutivo / Propietario",
            "estado_actual": "Jubilado tras pasar el mando a Sanji"
        }',
        '{
            "apariencia_general": "Hombre mayor de complexión robusta. Lleva un delantal de cocina manchado y una pierna ortopédica de madera.",
            "rasgos_distintivos": "Su pierna derecha es un palo de madera (se la amputó él mismo). Lleva un bigote poblado canoso.",
            "vestimenta_habitual": "Uniforme de chef blanco, delantal, pañuelo rojo en la cabeza",
            "lenguaje_corporal": "Postura firme y orgullosa, se mantiene erguido pese a su edad"
        }',
        '{
            "descripcion": "Ex-pirata legendario que cambió las espadas por los fogones. Zeff es un hombre de honor inquebrantable y un chef de talla mundial que valora la comida por encima de todo.",
            "arquetipo_principal": "Mentor rudo de buen corazón",
            "rasgos_positivos": ["Honorable", "Generoso", "Disciplinado"],
            "rasgos_negativos": ["Orgulloso", "Terco", "A veces demasiado severo"],
            "manias_tics": "Golpea el suelo con su pata de palo cuando se impacienta",
            "miedos_fobias": "Que la comida se desperdicie",
            "patron_habla": "Ronco y directo, usa términos marinos mezclados con cocina"
        }',
        '{
            "objetivo_corto_plazo": "Mantener el Baratie como el mejor restaurante del mar",
            "objetivo_largo_plazo": "Ver a Sanji convertirse en un gran chef",
            "linea_roja": "Nunca usar armas para matar; sus manos son para cocinar",
            "alineamiento_moral": "Neutral Bueno"
        }',
        '{
            "estilo_combate": "Pateado culinario: patadas giratorias y precisas con su pierna buena. Jamás usa las manos.",
            "toma_decisiones": "Sopesa el honor y la dignidad antes que la victoria",
            "tolerancia_riesgo": "Alta, no le teme a la muerte",
            "reaccion_presion": "Se mantiene estoico y calculador",
            "pros_estrategicos": ["Conocimiento del Grand Line", "Combate en espacios cerrados"],
            "contras_estrategicas": ["Movilidad reducida por su pierna", "Edad avanzada"]
        }',
        '{
            "resumen": "Antiguo capitán pirata del Grand Line que perdió su pierna para salvar a un joven Sanji. Fundó el Baratie como templo de la gastronomía.",
            "origen": "Fue un temible pirata en el Grand Line",
            "evento_catalizador": "Quedó varado en una isla con Sanji durante 85 días",
            "hitos_timeline": [
                {"fecha": "Hace 30 años", "suceso": "Navegó por el Grand Line como capitán"},
                {"fecha": "Hace 12 años", "suceso": "Rescató a Sanji y le amputó su pierna"},
                {"fecha": "Hace 10 años", "suceso": "Fundó el Baratie"}
            ],
            "secreto_inconfesable": "En realidad perdió la pierna al golpearla contra las rocas, no en combate"
        }',
        '{
            "aliados": [
                {"nombre": "Sanji", "motivo": "Lo crió y entrenó como chef"}
            ],
            "enemigos": [
                {"nombre": "Piratas del Fullbody", "motivo": "Intentaron saquear el Baratie"}
            ],
            "trato_desconocidos": "Gruñón pero acogedor, alimenta a todo el que llega"
        }',
        '{"FP": 75, "DP": 60, "RP": 70, "IP": 70, "VP": 80, "HP": 0}'
    ],
    [
        'Smoker "El Cazador Blanco"',
        'https://picsum.photos/seed/smoker/200/200',
        1,
        '{
            "apodos": ["Cazador Blanco", "Humo Blanco"],
            "edad": 34,
            "raza": "Humano",
            "afiliacion": "Marina",
            "ocupacion": "Vicealmirante",
            "estado_actual": "Destinado en el Nuevo Mundo, Base G-5"
        }',
        '{
            "apariencia_general": "Hombre alto y musculoso, de ojos penetrantes. Siempre lleva un puro en la boca. Parte de su cuerpo está cubierto por vendajes.",
            "rasgos_distintivos": "Dos puros de gran tamaño siempre encendidos en su boca. Lleva un jitte con punta de Kairoseki.",
            "vestimenta_habitual": "Abrigo larguirucho de la Marina (blanco), sin camiseta, pantalones oscuros",
            "lenguaje_corporal": "Desafiante y tranquilo, nunca retrocede ante nadie"
        }',
        '{
            "descripcion": "Un oficial de la Marina con un fuerte código de justicia personal. No sigue órdenes ciegamente y actúa según su propia brújula moral. Fuma constantemente como extensión de su identidad.",
            "arquetipo_principal": "Perseguidor justiciero",
            "rasgos_positivos": ["Justo", "Persistente", "Leal a sus principios"],
            "rasgos_negativos": ["Obsesivo", "Cabezota", "A veces imprudente"],
            "manias_tics": "Enciende un puro nuevo con el anterior aún encendido",
            "miedos_fobias": "Que los piratas poderosos campen a sus anchas",
            "patron_habla": "Seguro, grave, nunca grita pero impone autoridad"
        }',
        '{
            "objetivo_corto_plazo": "Capturar a Monkey D. Luffy",
            "objetivo_largo_plazo": "Limpiar el Nuevo Mundo de piratas peligrosos",
            "linea_roja": "No sacrificar inocentes por la justicia",
            "alineamiento_moral": "Legal Bueno"
        }',
        '{
            "estilo_combate": "Cuerpo de humo (Moku Moku no Mi) combinado con golpes de jitte de Kairoseki para anular frutas.",
            "toma_decisiones": "Firme en sus convicciones, actúa rápido",
            "tolerancia_riesgo": "Alta, se lanza al combate sin dudar",
            "reaccion_presion": "Se vuelve más agresivo y determinado",
            "pros_estrategicos": ["Inmunidad física (humo)", "Control de masas"],
            "contras_estrategicas": ["Usuarios de Haki avanzado", "Kairoseki"]
        }',
        '{
            "resumen": "Un Vicealmirante de la Marina que persiguió a Luffy desde el East Blue hasta el Nuevo Mundo. Consumió la Moku Moku no Mi.",
            "origen": "Marino de carrera desde joven",
            "evento_catalizador": "Presenció la ejecución de Gol D. Roger de niño",
            "hitos_timeline": [
                {"fecha": "Hace 15 años", "suceso": "Ingresa en la Marina"},
                {"fecha": "Hace 5 años", "suceso": "Ascendido a Vicealmirante"},
                {"fecha": "Hace 2 años", "suceso": "Participó en Marineford"}
            ],
            "secreto_inconfesable": "Admira en secreto la determinación de Luffy"
        }',
        '{
            "aliados": [
                {"nombre": "Tashigi", "motivo": "Subordinada directa de confianza"}
            ],
            "enemigos": [
                {"nombre": "Monkey D. Luffy", "motivo": "Obsesión por capturarlo"},
                {"nombre": "Sir Crocodile", "motivo": "Lo derrotó en Alabasta"}
            ],
            "trato_desconocidos": "Directo pero justo, juzga por acciones no por apariencias"
        }',
        '{"FP": 65, "DP": 75, "RP": 80, "IP": 65, "VP": 85, "HP": 30}'
    ],
    [
        'Sir Crocodile',
        'https://picsum.photos/seed/crocodile/200/200',
        4,
        '{
            "apodos": ["Sir Crocodile", "Rey del Desierto", "Ex-Shichibukai"],
            "edad": 44,
            "raza": "Humano",
            "afiliacion": "Cross Guild",
            "ocupacion": "Líder de Baroque Works / Co-líder de Cross Guild",
            "estado_actual": "Activo en el Nuevo Mundo como líder de Cross Guild"
        }',
        '{
            "apariencia_general": "Hombre alto y elegantemente vestido. Tiene el pelo engominado hacia atrás, cicatriz vertical en la nariz y un abrigo de piel sobre los hombros.",
            "rasgos_distintivos": "Garfio de oro en la mano izquierda. Cicatriz en el puente de la nariz. Siempre viste traje impecable.",
            "vestimenta_habitual": "Traje de gángster con abrigo de piel, puro habano, botas de cocodrilo",
            "lenguaje_corporal": "Lento, seguro, dominante. Cada movimiento transmite poder y control."
        }',
        '{
            "descripcion": "Ex-Shichibukai, cerebro de Baroque Works y actual co-líder de Cross Guild. Crocodile es un estratega nato, ambicioso y despiadado, con un carisma que atrae seguidores leales.",
            "arquetipo_principal": "Señor del crimen calculador",
            "rasgos_positivos": ["Carismático", "Estratégico", "Ambicioso"],
            "rasgos_negativos": ["Despiadado", "Vengativo", "Egoísta"],
            "manias_tics": "Pasa el garfio de oro por su barbilla cuando reflexiona",
            "miedos_fobias": "Perder el control de su organización",
            "patron_habla": "Refinado, autoritario, siempre seguro de sí mismo"
        }',
        '{
            "objetivo_corto_plazo": "Consolidar Cross Guild como fuerza dominante",
            "objetivo_largo_plazo": "Encontrar el arma ancestral Plutón",
            "linea_roja": "Nunca confiar plenamente en nadie",
            "alineamiento_moral": "Legal Malvado"
        }',
        '{
            "estilo_combate": "Arena (Suna Suna no Mi): deshidrata todo lo que toca, crea tormentas de arena, se transforma en arena para esquivar.",
            "toma_decisiones": "Calculador, nunca actúa sin un plan de respaldo",
            "tolerancia_riesgo": "Moderada, prefiere manipular desde las sombras",
            "reaccion_presion": "Se vuelve más frío y calculador",
            "pros_estrategicos": ["Poder de Fruta despertado", "Red de espías", "Recursos ilimitados"],
            "contras_estrategicas": ["Agua", "Haki de Armadura avanzado"]
        }',
        '{
            "resumen": "Uno de los Shichibukai más peligrosos. Infiltró Alabasta durante años buscando Plutón. Derrotado por Luffy, escapó de Impel Down y ahora lidera Cross Guild.",
            "origen": "Pirata del Nuevo Mundo que perdió su tripulación",
            "evento_catalizador": "Ser derrotado por Barbablanca en el pasado",
            "hitos_timeline": [
                {"fecha": "Hace 20 años", "suceso": "Nombrado Shichibukai"},
                {"fecha": "Hace 2 años", "suceso": "Derrotado en Alabasta y encarcelado en Impel Down"},
                {"fecha": "Actualidad", "suceso": "Co-líder de Cross Guild con Mihawk"}
            ],
            "secreto_inconfesable": "Teme a Barbablanca incluso después de su muerte"
        }',
        '{
            "aliados": [
                {"nombre": "Dracule Mihawk", "motivo": "Co-líder de Cross Guild"},
                {"nombre": "Mr. 1 (Daz Bones)", "motivo": "Subordinado más leal"}
            ],
            "enemigos": [
                {"nombre": "Monkey D. Luffy", "motivo": "Arruinó su plan en Alabasta"},
                {"nombre": "Barbablanca", "motivo": "Nunca pudo vencerlo"}
            ],
            "trato_desconocidos": "Cortés pero distante, evalúa su utilidad antes que su humanidad"
        }',
        '{"FP": 85, "DP": 80, "RP": 95, "IP": 105, "VP": 90, "HP": 60}'
    ],
    [
        'Sabo',
        'https://picsum.photos/seed/sabo/200/200',
        5,
        '{
            "apodos": ["El Jefe de Estado Mayor", "Hermano de Luffy"],
            "edad": 22,
            "raza": "Humano",
            "afiliacion": "Ejército Revolucionario",
            "ocupacion": "Jefe de Estado Mayor (2do al mando)",
            "estado_actual": "Activo, lidera operaciones revolucionarias en el mundo"
        }',
        '{
            "apariencia_general": "Joven de complexión atlética, cabello rubio y ondulado. Una gran cicatriz quemada le cruza la mejilla y el ojo izquierdo.",
            "rasgos_distintivos": "Cicatriz de quemadura en el lado izquierdo de la cara. Usa un sombrero de copa con gafas de aviador. Capa con plumas.",
            "vestimenta_habitual": "Abrigo largo de plumas naranjas, sombrero de copa, gafas protectoras, pantalón blanco",
            "lenguaje_corporal": "Desenfadado y enérgico, pero con un núcleo de acero cuando la situación lo requiere"
        }',
        '{
            "descripcion": "Hermano jurado de Luffy y Ace. Sabo es el estratega del Ejército Revolucionario, heredero de la voluntad de su mentor Monkey D. Dragon. Consumió la Mera Mera no Mi.",
            "arquetipo_principal": "Revolucionario idealista",
            "rasgos_positivos": ["Idealista", "Leal", "Valiente"],
            "rasgos_negativos": ["Impulsivo", "A veces imprudente"],
            "manias_tics": "Se ajusta las gafas de aviador antes de una batalla importante",
            "miedos_fobias": "Perder a otro hermano",
            "patron_habla": "Apasionado, directo, con un tono de liderazgo natural"
        }',
        '{
            "objetivo_corto_plazo": "Derrocar al Gobierno Mundial",
            "objetivo_largo_plazo": "Un mundo donde la gente pueda ser libre",
            "linea_roja": "Proteger a los débiles y oprimidos",
            "alineamiento_moral": "Caótico Bueno"
        }',
        '{
            "estilo_combate": "Puño de Fuego (Mera Mera no Mi) + Garras de Dragón (estilo del Ejército Revolucionario). Haki de Armadura avanzado.",
            "toma_decisiones": "Equilibra idealismo con pragmatismo revolucionario",
            "tolerancia_riesgo": "Alta, cree en la causa por encima de su vida",
            "reaccion_presion": "Se enfoca y canaliza su poder en erupciones de fuego",
            "pros_estrategicos": ["Poder de Fruta del Diablo", "Liderazgo", "Haki de Armadura"],
            "contras_estrategicas": ["Agua", "Emocional cuando Luffy está en peligro"]
        }',
        '{
            "resumen": "Hermano de Luffy y segundo al mando del Ejército Revolucionario. Heredó la voluntad de Ace y la Mera Mera no Mi tras su muerte en Marineford.",
            "origen": "Noble del Reino de Goa que escapó para ser libre",
            "evento_catalizador": "La muerte de Ace en Marineford",
            "hitos_timeline": [
                {"fecha": "Hace 12 años", "suceso": "Escapa del Reino de Goa"},
                {"fecha": "Hace 2 años", "suceso": "Recupera la memoria en Dressrosa"},
                {"fecha": "Actualidad", "suceso": "Segundo al mando del Ejército Revolucionario"}
            ],
            "secreto_inconfesable": "Se culpa por no haber estado con Ace en Marineford"
        }',
        '{
            "aliados": [
                {"nombre": "Monkey D. Dragon", "motivo": "Líder y mentor"},
                {"nombre": "Monkey D. Luffy", "motivo": "Hermano jurado"},
                {"nombre": "Koala", "motivo": "Compañera revolucionaria"}
            ],
            "enemigos": [
                {"nombre": "Gobierno Mundial", "motivo": "Opresión del pueblo"},
                {"nombre": "Almirantes de la Marina", "motivo": "Ejecutaron a Ace"}
            ],
            "trato_desconocidos": "Abierto y amigable, pero alerta"
        }',
        '{"FP": 110, "DP": 105, "RP": 100, "IP": 95, "VP": 115, "HP": 105}'
    ]
];

foreach ($npc_data as $n) {
    $sql = "INSERT INTO {$prefix}game_npc_profiles (nombre, imagen, tripulacion_id, identificacion, perfil_fisico, psicologia, motivaciones, perfil_estrategico, cronologia, relaciones, stats) VALUES (
        '" . $db->escape_string($n[0]) . "',
        '" . $db->escape_string($n[1]) . "',
        " . (int)$n[2] . ",
        '" . $db->escape_string($n[3]) . "',
        '" . $db->escape_string($n[4]) . "',
        '" . $db->escape_string($n[5]) . "',
        '" . $db->escape_string($n[6]) . "',
        '" . $db->escape_string($n[7]) . "',
        '" . $db->escape_string($n[8]) . "',
        '" . $db->escape_string($n[9]) . "',
        '" . $db->escape_string($n[10]) . "'
    )";
    $db->write_query($sql);
}
echo "<div class='rpg-admin-ok'>[OK] NPCs poblados con éxito (perfiles JSON)</div>";

// 3.2 Tripulaciones
$trip_data = [
    ['Marina', 'https://picsum.photos/seed/marina/400/200', 'La fuerza militar naval del Gobierno Mundial.'],
    ['Piratas de Kuro', 'https://picsum.photos/seed/piratas/400/200', 'Una banda pirata del East Blue liderada por Kuro.'],
    ['Restaurante Baratie', 'https://picsum.photos/seed/baratie/400/200', 'El restaurante flotante más famoso.'],
    ['Cross Guild', 'https://picsum.photos/seed/crossguild/400/200', 'Organización de cazarrecompensas.'],
    ['Ejército Revolucionario', 'https://picsum.photos/seed/revo/400/200', 'La fuerza insurgente contra el Gobierno Mundial.'],
];
foreach ($trip_data as $t) {
    $sql = "INSERT INTO {$prefix}game_tripulaciones (nombre, imagen, descripcion) VALUES (
        '" . $db->escape_string($t[0]) . "',
        '" . $db->escape_string($t[1]) . "',
        '" . $db->escape_string($t[2]) . "'
    )";
    $db->write_query($sql);
}
echo "<div class='rpg-admin-ok'>[OK] Tripulaciones pobladas con éxito</div>";

// 3.2 Personajes
$chars_data = [
    [
        'Monkey D. Luffy', 'humano', 'Humano', 'capitan', 'Capitán',
        'Capitán de los Piratas de Sombrero de Paja. Posee un cuerpo de goma y aspira a ser el Rey de los Piratas.',
        'Luffy es un joven alegre y temerario que comió la Fruta del Diablo Gomu Gomu. Destaca por su voluntad inquebrantable, su capacidad para hacer amigos rápidamente y sus poderosas transformaciones (Gears) que alteran su fisiología y fuerza de ataque.',
        115, 105, 110, 30, 120, 110, 'Yonko', 'Piratas de Sombrero de Paja', '3.000.000.000 Berries', 'images/game/personaje_banner.png'
    ],
    [
        'Roronoa Zoro', 'humano', 'Humano', 'espadachin', 'Combatiente',
        'Cazador de piratas y primer oficial de Luffy. Maestro del estilo Santoryu (Tres Espadas).',
        'Zoro es un espadachín de honor impecable y un entrenamiento físico extremo. Su objetivo es derrotar a Mihawk para convertirse en el Mejor Espadachín del Mundo. Maneja katanas legendarias como la Wado Ichimonji y la Enma, canalizando su Haki de forma devastadora.',
        110, 100, 105, 45, 115, 95, 'Supernova', 'Piratas de Sombrero de Paja', '1.111.000.000 Berries', 'images/game/personaje_banner.png'
    ],
    [
        'Vinsmoke Sanji', 'humano', 'Humano', 'cocinero', 'Cocinero / Combatiente',
        'Cocinero real del Baratie y oficial de combate. Pelea usando únicamente patadas cargadas de calor.',
        'Sanji es el príncipe modificado de la familia Vinsmoke, aunque rechaza su linaje. Su velocidad y destreza en combate son formidables, siendo capaz de generar fuego por fricción gracias a su pasión. Sigue un estricto código de nunca golpear a una mujer y nunca dañar sus manos.',
        100, 110, 95, 80, 90, 85, 'Oficial Mayor', 'Piratas de Sombrero de Paja', '1.032.000.000 Berries', 'images/game/personaje_banner.png'
    ],
    [
        'Nami', 'humano', 'Humano', 'navegante', 'Navegante',
        'La navegante de la tripulación, experta en cartografía, meteorología y el robo de tesoros.',
        'Nami es el cerebro estratégico que guía al Sombrero de Paja por los peligrosos climas del Grand Line. Utiliza el Clima-Tact, un bastón meteorológico diseñado por Usopp que le permite manipular nubes de tormenta, niebla y lanzar rayos de alto voltaje.',
        20, 60, 45, 110, 75, 0, 'Oficial', 'Piratas de Sombrero de Paja', '366.000.000 Berries', 'images/game/personaje_banner.png'
    ],
    [
        'Nico Robin', 'humano', 'Humano', 'arqueologo', 'Arqueóloga',
        'Única superviviente de la isla de Ohara, con la habilidad de descifrar los Poneglyphs.',
        'Robin comió la Hana Hana no Mi, permitiéndole florecer partes de su cuerpo en cualquier superficie a la vista. Es perseguida desde su infancia por el Gobierno Mundial debido a sus amplios conocimientos históricos del Siglo Vacío.',
        55, 75, 60, 115, 80, 0, 'Oficial', 'Piratas de Sombrero de Paja', '930.000.000 Berries', 'images/game/personaje_banner.png'
    ],
    [
        'Tony Tony Chopper', 'gyojin', 'Mink (Reno)', 'medico', 'Médico',
        'Un reno de nariz azul que comió la Hito Hito no Mi, adquiriendo conciencia y forma humana.',
        'Chopper es el médico de la tripulación. Desarrolló las Rumble Balls para alterar los puntos de transformación de su fruta, permitiéndole cambiar a formas de combate pesado (Heavy Point), agilidad (Walk Point) o su destructiva forma gigante (Monster Point).',
        75, 70, 85, 100, 65, 0, 'Oficial', 'Piratas de Sombrero de Paja', '1.000 Berries', 'images/game/personaje_banner.png'
    ]
];

foreach ($chars_data as $char) {
    $stats = [
        'fue' => $char[7],
        'agi' => $char[8],
        'des' => $char[9],
        'int' => $char[10],
        'inst' => $char[11],
        'esp' => $char[12],
    ];
    $data = [
        'age' => 'Desconocida',
        'origin' => 'Desconocido',
        'race' => $char[1],
        'job' => $char[3],
        'faction' => $char[14],
        'pb' => 'Desconocido',
        'physique' => '',
        'psychology' => $char[6],
        'extras' => '',
        'arquetipo' => 'Desconocido',
        'linaje' => ['geneNames' => []],
    ];
    $stats_json = $db->escape_string(json_encode($stats, JSON_UNESCAPED_UNICODE));
    $data_json = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
    $sql = "INSERT INTO {$prefix}game_personajes (name, race, race_name, occupation, occupation_name, `desc`, details, rango, tripulacion, recompensa, banner, avatar, stats_json, data_json, faction) VALUES (
        '" . $db->escape_string($char[0]) . "',
        '" . $db->escape_string($char[1]) . "',
        '" . $db->escape_string($char[2]) . "',
        '" . $db->escape_string($char[3]) . "',
        '" . $db->escape_string($char[4]) . "',
        '" . $db->escape_string($char[5]) . "',
        '" . $db->escape_string($char[6]) . "',
        '" . $db->escape_string($char[13]) . "',
        '" . $db->escape_string($char[14]) . "',
        '" . $db->escape_string($char[15]) . "',
        '" . $db->escape_string($char[16]) . "',
        '',
        '{$stats_json}',
        '{$data_json}',
        '" . $db->escape_string($char[14]) . "'
    )";
    $db->write_query($sql);
}
echo "<div class='rpg-admin-ok'>[OK] Personajes poblados con éxito</div>";

// 3.2.1 Personaje Admin "Imu"
$imu_check = $db->query("SELECT id FROM {$prefix}game_personajes WHERE name = 'Imu' LIMIT 1");
if (!$db->num_rows($imu_check)) {
    $stats = ['fue' => 200, 'agi' => 200, 'des' => 200, 'int' => 200, 'inst' => 200, 'esp' => 200];
    $data = [
        'age' => 'Desconocida', 'origin' => 'Desconocido', 'race' => 'humano', 'job' => 'gobernante',
        'faction' => 'Gobierno Mundial', 'pb' => 'Desconocido', 'physique' => '', 'psychology' => 'Poseedor del conocimiento absoluto.',
        'extras' => '', 'arquetipo' => 'Administrador', 'linaje' => ['geneNames' => []]
    ];
    $stats_json = $db->escape_string(json_encode($stats));
    $data_json = $db->escape_string(json_encode($data));
    $db->write_query("INSERT INTO {$prefix}game_personajes (user_id, name, race, race_name, occupation, occupation_name, `desc`, details, rango, tripulacion, recompensa, banner, avatar, is_staff, staff_level, stats_json, data_json, faction) VALUES (
        1, 'Imu', 'humano', 'Humano', 'gobernante', 'Gobernante Supremo',
        'Entidad suprema que gobierna desde las sombras el mundo entero.',
        'Poseedor del conocimiento absoluto y líder de los Diosas Solares.',
        'Administrador', 'Gobierno Mundial', '∞ Berries', 'images/game/personaje_banner.png', 'https://placehold.co/290x450', 1, 3,
        '{$stats_json}', '{$data_json}', 'Gobierno Mundial'
    )");
    $imu_id = $db->insert_id();
    $db->write_query("INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used, active_pj_id) VALUES (1, 2, 1, {$imu_id}) ON DUPLICATE KEY UPDATE active_pj_id = {$imu_id}, max_slots = 2, slots_used = 1");
    echo "<div class='rpg-admin-ok'>[OK] Personaje Admin 'Imu' creado como staff</div>";
} else {
    $db->write_query("UPDATE {$prefix}game_personajes SET avatar = 'https://placehold.co/290x450' WHERE name = 'Imu' LIMIT 1");
    echo "<div class='rpg-admin-warn'>[OK] Personaje Admin 'Imu' ya existe — avatar actualizado</div>";
}

// 3.2.2 Personaje Admin normal "Kazan"
$kazan_check = $db->query("SELECT id FROM {$prefix}game_personajes WHERE name = 'Kazan' AND user_id = 1 LIMIT 1");
if (!$db->num_rows($kazan_check)) {
    $stats = ['fue' => 30, 'agi' => 25, 'des' => 35, 'int' => 20, 'inst' => 25, 'esp' => 10];
    $data = [
        'age' => 'Desconocida', 'origin' => 'Desconocido', 'race' => 'humano', 'job' => 'aventurero',
        'faction' => '—', 'pb' => 'Desconocido', 'physique' => '', 'psychology' => 'Kazan recorre las islas sin rumbo fijo.',
        'extras' => '', 'arquetipo' => 'Aventurero', 'linaje' => ['geneNames' => []]
    ];
    $stats_json = $db->escape_string(json_encode($stats));
    $data_json = $db->escape_string(json_encode($data));
    $db->write_query("INSERT INTO {$prefix}game_personajes (user_id, name, race, race_name, occupation, occupation_name, `desc`, details, rango, tripulacion, recompensa, banner, avatar, is_staff, staff_level, stats_json, data_json, faction) VALUES (
        1, 'Kazan', 'humano', 'Humano', 'aventurero', 'Aventurero Errante',
        'Un viajero del Grand Line en busca de libertad.',
        'Kazan recorre las islas sin rumbo fijo, siempre dispuesto a ayudar a quien lo necesite.',
        'Tripulante', '—', '0 Berries', 'images/game/personaje_banner.png', 'https://placehold.co/290x450', 1, 1,
        '{$stats_json}', '{$data_json}', '—'
    )");
    $db->write_query("UPDATE {$prefix}game_user_config SET max_slots = 2, slots_used = 2 WHERE user_id = 1");
    echo "<div class='rpg-admin-ok'>[OK] Personaje 'Kazan' creado, slots 2/2</div>";
} else {
    $db->write_query("UPDATE {$prefix}game_personajes SET avatar = 'https://placehold.co/290x450' WHERE name = 'Kazan' AND user_id = 1 LIMIT 1");
    echo "<div class='rpg-admin-warn'>[OK] Personaje 'Kazan' ya existe — avatar actualizado</div>";
}

// 3.3 Estilos de combate
$estilos_data = [
    [
        1, 'Santoryu (Tres Espadas)', 'espadachin', 'Estilo Espadachín', 'destreza', 'Destreza Alta',
        'Técnica de combate letal que consiste en blandir una espada en cada mano y una tercera en la boca.',
        'El Santoryu es un estilo de esgrima tradicional de Wano y perfeccionado en el Dojo Shimotsuki. Exige una fuerza de mandíbula increíble y un equilibrio corporal sobrehumano para coordinar los tajos de las tres hojas. Cuenta con poderosas técnicas cortantes.',
        '45 FP', '65 DP', '15 por golpe', 'Muy Alta', 'images/game/estilos_banner.png'
    ],
    [
        2, 'Karate Gyojin', 'artes-marciales', 'Artes Marciales', 'fuerza', 'Fuerza Requerida',
        'Arte marcial gyojin letal capaz de manipular las partículas de agua presentes en la atmósfera.',
        'El Karate Gyojin es una disciplina física milenaria cuyo verdadero poder reside en golpear el agua a nivel celular. Esto permite dañar a los contrincantes desde el interior, atravesando defensas sólidas o armaduras físicas.',
        '70 FP', '30 DP', '18 por golpe', 'Media-Alta', 'images/game/estilos_banner.png'
    ],
    [
        3, 'Estilo Pierna Negra (Black Leg)', 'artes-marciales', 'Artes Marciales', 'destreza', 'Destreza Alta',
        'Arte marcial acrobático basado únicamente en el uso de patadas fulminantes para preservar las manos.',
        'Desarrollado por Zeff y heredado por Sanji. Se enfoca en la velocidad, giros dinámicos e impactos precisos en puntos vitales. Las manos se mantienen libres o en los bolsillos para no dañar las herramientas de un cocinero.',
        '50 FP', '70 DP', '12 por patada', 'Alta', 'images/game/estilos_banner.png'
    ],
    [
        4, 'Kabuto & Pop Greens', 'tirador', 'Tirador', 'destreza', 'Destreza Alta',
        'Combate a distancia utilizando proyectiles orgánicos modificados que eclosionan en plantas carnívoras.',
        'Este estilo combina el uso de un tirachinas gigante de largo alcance con "Pop Greens", semillas recolectadas del archipiélago Boin. Al impactar, liberan trampas de enredaderas o plantas gigantes.',
        '25 FP', '80 DP', '8 por disparo', 'Media', 'images/game/estilos_banner.png'
    ],
    [
        5, 'Ittoryu (Una Espada)', 'espadachin', 'Estilo Espadachín', 'destreza', 'Destreza Alta',
        'Estilo de corte rápido centrado en la concentración, el desenvainado veloz y los tajos precisos.',
        'El Ittoryu destaca por sus técnicas de desenvainado instantáneo (Iaijutsu). Permite al espadachín cortar elementos sólidos como el acero alineando su respiración con la naturaleza.',
        '35 FP', '55 DP', '10 por golpe', 'Baja-Media', 'images/game/estilos_banner.png'
    ],
    [
        6, 'Rokushiki (Seis Técnicas)', 'artes-marciales', 'Artes Marciales', 'haki', 'Haki Requerido',
        'Estilo de combate militar y sobrehumano utilizado por agentes secretos del CP9 y CP0.',
        'Consiste en dominar seis habilidades corporales definitivas: Soru (velocidad), Geppo (saltar en el aire), Tekkai (endurecer el cuerpo), Rankyaku (patadas de viento cortante), Kami-e (esquiva elástica) y Shigan (dedo perforador).',
        '80 FP', '80 DP', '25 por técnica', 'Legendaria', 'images/game/estilos_banner.png'
    ]
];

foreach ($estilos_data as $estilo) {
    $sql = "INSERT INTO {$prefix}game_estilos (id, name, type, type_name, req, req_name, `desc`, details, req_fp, req_dp, consumo_estamina, dificultad, banner) VALUES (
        {$estilo[0]},
        '" . $db->escape_string($estilo[1]) . "',
        '" . $db->escape_string($estilo[2]) . "',
        '" . $db->escape_string($estilo[3]) . "',
        '" . $db->escape_string($estilo[4]) . "',
        '" . $db->escape_string($estilo[5]) . "',
        '" . $db->escape_string($estilo[6]) . "',
        '" . $db->escape_string($estilo[7]) . "',
        '" . $db->escape_string($estilo[8]) . "',
        '" . $db->escape_string($estilo[9]) . "',
        '" . $db->escape_string($estilo[10]) . "',
        '" . $db->escape_string($estilo[11]) . "',
        '" . $db->escape_string($estilo[12]) . "'
    )";
    $db->write_query($sql);
}
echo "<div class='rpg-admin-ok'>[OK] Estilos de combate poblados con éxito</div>";

// 3.4 Técnicas
$tecnicas_data = [
    // Santoryu (1)
    [1, 'Oni Giri (Corte del Demonio)', 'Cruce de brazos con tres espadas que intercepta al enemigo a gran velocidad, efectuando un tajo cruzado letal.', '12 Estamina', 'Fuerza x 1.5'],
    [1, 'Tora Gari (Caza del Tigre)', 'Alinea las dos espadas laterales hacia abajo y la de la boca en perpendicular para realizar un golpe vertical descendente.', '15 Estamina', 'Fuerza x 1.8'],
    [1, 'San-Zen-Se-Kai (Tres Mil Mundos)', 'Gira las espadas a gran velocidad creando un torbellino cortante que impacta en múltiples direcciones simultáneas.', '25 Estamina', 'Fuerza x 2.5'],
    
    // Karate Gyojin (2)
    [2, 'Karakusa Kawara Seiken', 'Golpe de puño al aire que transmite ondas de choque a través de la humedad del ambiente, golpeando a múltiples objetivos.', '15 Estamina', 'Fuerza x 1.4 + Int. x 0.5'],
    [2, 'Goshioha (Ruptura de Cinco Mares)', 'Dispara un chorro a alta presión de agua concentrada en la palma de la mano capaz de atravesar barcos de guerra.', '22 Estamina', 'Fuerza x 2.2'],
    
    // Black Leg (3)
    [3, 'Collier Shot', 'Salto acrobático que descarga una patada contundente directamente en el cuello del oponente.', '10 Estamina', 'Destreza x 1.3'],
    [3, 'Mouton Shot', 'Patada trasera con ambas piernas en rotación que lanza al enemigo con una fuerza tremenda.', '15 Estamina', 'Destreza x 1.7'],
    [3, 'Diable Jambe: Flambage Shot', 'Activa la fricción ardiente en la pierna, propinando una patada ígnea descendente que calcina al rival.', '30 Estamina', 'Destreza x 2.6 (Fuego)'],

    // Kabuto (4)
    [4, 'Midori Boshi: Rafflesia', 'Dispara una Pop Green que eclosiona en una flor gigante y libera un olor fétido y paralizante.', '10 Estamina', 'Efecto Alterado (Aturdir)'],
    [4, 'Midori Boshi: Take Bamboo', 'Dispara una semilla al suelo que hace brotar lanzas de bambú gigantescas que empalan al enemigo.', '12 Estamina', 'Destreza x 1.4'],

    // Ittoryu (5)
    [5, 'Shishi Sonson (Canción del León)', 'Desenvainado veloz (Iai) de precisión milimétrica que corta incluso el acero y se envaina al instante.', '15 Estamina', 'Destreza x 2.0'],
    [5, 'Sanjuroku Pound Ho (Cañón de 36 Libras)', 'Tajo cortante que proyecta una ráfaga de aire comprimido a gran distancia.', '10 Estamina', 'Destreza x 1.2'],

    // Rokushiki (6)
    [6, 'Soru (Afeitado)', 'Velocidad sobrehumana al pisar el suelo 10 veces en una fracción de segundo, permitiendo teletransportarse a corta distancia.', '10 Estamina', 'Movimiento Instantáneo'],
    [6, 'Tekkai (Masa de Hierro)', 'Endurece los músculos del cuerpo hasta obtener la resistencia del acero sólido a cambio de no poder moverse.', '15 Estamina', 'Absorbe 50% Daño'],
    [6, 'Rankyaku (Tempestad de Patadas)', 'Patada de alta velocidad que proyecta una cuchilla de viento cortante capaz de rebanar edificios.', '18 Estamina', 'Destreza x 1.8']
];

foreach ($tecnicas_data as $tec) {
    $sql = "INSERT INTO {$prefix}game_tecnicas (estilo_id, name, `desc`, energy_cost, damage) VALUES (
        {$tec[0]},
        '" . $db->escape_string($tec[1]) . "',
        '" . $db->escape_string($tec[2]) . "',
        '" . $db->escape_string($tec[3]) . "',
        '" . $db->escape_string($tec[4]) . "'
    )";
    $db->write_query($sql);
}
echo "<div class='rpg-admin-ok'>[OK] Técnicas de combate pobladas con éxito</div>";

// 3.5 Akuma no Mi
$akumas_data = [
    [
        'Gomu Gomu no Mi (Fruta Goma Goma)', 'paramecia', 'Paramecia', 'activa', 'Activa (En Uso)',
        'Convierte el cuerpo del consumidor en goma, haciéndolo inmune a impactos físicos directos y electricidad.',
        'Esta fruta otorga al usuario un cuerpo completamente elástico. Puede estirar sus extremidades a gran distancia, amortiguar caídas y rebotar balas de cañón ordinarias. Inicialmente clasificada como Paramecia, oculta un origen y despertar de proporciones divinas.',
        'Paramecia (Oficial)', 'Monkey D. Luffy', 'Inmunidad a Rayos y Golpes', '100.000.000 Berries', 'images/game/akuma_banner.png'
    ],
    [
        'Mera Mera no Mi (Fruta Fuego Fuego)', 'logia', 'Logia', 'disponible', 'Disponible (Libre)',
        'Permite al usuario crear, controlar y transformarse en fuego a voluntad.',
        'Al ser de tipo Logia, otorga intangibilidad contra cualquier ataque físico ordinario que no esté imbuido en Haki de Armadura. El usuario puede liberar columnas gigantes de fuego ("Enkai"), ráfagas de proyectiles térmicos o su ataque definitivo, el sol de fuego "Dai Enkai: Entei".',
        'Logia elemental', 'Ninguno (Libre en cofre)', 'Intangibilidad Térmica', '500.000.000 Berries', 'images/game/akuma_banner.png'
    ],
    [
        'Hito Hito no Mi: Modelo Nika', 'zoan-mitologica', 'Zoan Mitológica', 'activa', 'Activa (En Uso)',
        'Concede las propiedades físicas de la goma apoyadas por el poder divino de la libertad e imaginación.',
        'Esta fruta Zoan Mitológica representa al Dios del Sol "Nika". Al despertar (Gear 5), el usuario adquiere total libertad para alterar el entorno y su propio cuerpo como si estuviera en una caricatura, convirtiendo en goma todo lo que toca, incluso elementos incorpóreos como el rayo.',
        'Zoan Mitológica', 'Monkey D. Luffy', 'Despertar "Gear 5" / Libertad Total', 'Incalculable', 'images/game/akuma_banner.png'
    ],
    [
        'Tori Tori no Mi: Modelo Fénix', 'zoan-mitologica', 'Zoan Mitológica', 'activa', 'Activa (En Uso)',
        'Permite transformarse en un fénix de llamas azules curativas con regeneración instantánea.',
        'Una de las Zoan Mitológicas más raras. El consumidor puede convertirse en un majestuoso fénix cubierto de "Llamas Azules de la Resurrección". Estas llamas no queman ni propagan calor, sino que restauran y regeneran heridas de gravedad de forma casi instantánea.',
        'Zoan Mitológica', 'Marco el Fénix', 'Regeneración Rápida Automática', '1.200.000.000 Berries', 'images/game/akuma_banner.png'
    ],
    [
        'Suna Suna no Mi (Fruta Arena Arena)', 'logia', 'Logia', 'activa', 'Activa (En Uso)',
        'Permite al usuario crear, manipular y transformarse en arena del desierto, absorbiendo humedad.',
        'El consumidor puede deshidratar y marchitar plantas, rocas o seres vivos con el simple contacto de su mano derecha. También es capaz de convocar tormentas de arena destructivas y cuchillas de viento seco. Su debilidad crítica es que se vuelve tangible al contacto con cualquier líquido.',
        'Logia terrestre', 'Sir Crocodile', 'Deshidratación por contacto', '380.000.000 Berries', 'images/game/akuma_banner.png'
    ],
    [
        'Ope Ope no Mi (Fruta Operación)', 'paramecia', 'Paramecia', 'disponible', 'Disponible (Libre)',
        'Otorga el poder de crear un espacio delimitado ("Room") donde el usuario opera como un cirujano supremo.',
        'Considerada la Fruta del Diablo Definitiva. Dentro de su zona de efecto, el usuario puede teletransportarse, cortar o intercambiar partes de cuerpos sin causar dolor ni derramar sangre. Su habilidad secreta concede la "Juventud Eterna" a cambio de la vida del consumidor.',
        'Paramecia Suprema', 'Ninguno (Libre)', 'Operación Quirúrgica del Espacio', '5.000.000.000 Berries', 'images/game/akuma_banner.png'
    ]
];

foreach ($akumas_data as $akuma) {
    $sql = "INSERT INTO {$prefix}game_akuma_no_mi (name, class, class_name, status, status_name, `desc`, details, tipo_fruta, usuario_actual, habilidad_clave, precio, banner) VALUES (
        '" . $db->escape_string($akuma[0]) . "',
        '" . $db->escape_string($akuma[1]) . "',
        '" . $db->escape_string($akuma[2]) . "',
        '" . $db->escape_string($akuma[3]) . "',
        '" . $db->escape_string($akuma[4]) . "',
        '" . $db->escape_string($akuma[5]) . "',
        '" . $db->escape_string($akuma[6]) . "',
        '" . $db->escape_string($akuma[7]) . "',
        '" . $db->escape_string($akuma[8]) . "',
        '" . $db->escape_string($akuma[9]) . "',
        '" . $db->escape_string($akuma[10]) . "',
        '" . $db->escape_string($akuma[11]) . "'
    )";
    $db->write_query($sql);
}
echo "<div class='rpg-admin-ok'>[OK] Frutas del Diablo pobladas con éxito</div>";

// 3.6 Objetos
$objetos_data = [
    [
        'Wado Ichimonji (El Camino de la Armonía)', 'armas', 'Armas', 'epico', 'Épico',
        'Una de las 21 katanas de grado O Wazamono. Forjada por el legendario artesano Shimotsuki Kozaburo.',
        'Esta katana cuenta con una hoja de filo blanco pulcro y una empuñadura impecable del mismo color. Destaca por su increíble resistencia y capacidad de canalizar el Haki del usuario de forma fluida. Perteneció a Kuina antes de pasar a manos de Zoro.',
        'Katana (O Wazamono)', '+45 Daño Cortante', 'Destreza 50 / Espadachín', 'No comercializable', 'images/game/objetos_banner.png'
    ],
    [
        'Clima-Tact (Bastón del Clima)', 'armas', 'Armas', 'raro', 'Raro',
        'Bastón segmentable inventado por Usopp que puede emitir burbujas térmicas de aire.',
        'Este bastón se divide en tres secciones capaces de generar Heat Balls, Cool Balls y Thunder Balls. Al mezclarse en la atmósfera, permiten alterar el clima local, convocando tormentas eléctricas de gran escala o bancos densos de niebla.',
        'Bastón Meteorológico', '+25 Daño Elemental (Rayo/Viento)', 'Inteligencia 30 / Navegante', '150.000 Berries', 'images/game/objetos_banner.png'
    ],
    [
        'Poción de Cola (Super Energizante)', 'consumibles', 'Consumibles', 'comun', 'Común',
        'Refresco de cola concentrado altamente carbonatado que restaura energía vital.',
        'Un refresco carbonatado especial que se utiliza comercialmente como fuente de energía estándar para cyborgs o maquinaria pesada. Al beberse, restaura instantáneamente la barra de estamina y energía del personaje.',
        'Consumible / Combustible', '+50 Estamina / 1 Carga Cola', 'Ninguno (Especial para Cyborgs)', '1.500 Berries', 'images/game/objetos_banner.png'
    ],
    [
        'Acero Templado de Wano', 'materiales', 'Materiales raros', 'epico', 'Épico',
        'Metal refinado mediante técnicas de forja secretas en la Tierra de Wano, extremadamente resistente.',
        'Este lingote de acero especial contiene trazas de minerales volcánicos purificados a alta temperatura. Es el material predilecto por los mejores herreros del mundo para fabricar hojas indestructibles o blindajes navales superlativos.',
        'Material de Forja', 'Aumenta rareza de arma al forjar', 'Nivel de Herrería 4', '180.000 Berries', 'images/game/objetos_banner.png'
    ],
    [
        'Sandai Kitetsu (Matadora de Demonios III)', 'armas', 'Armas', 'raro', 'Raro',
        'Una de las katanas malditas de grado Wazamono, famosa por cortar sin el consentimiento del portador.',
        'Esta espada posee un filo ondulado de color rojo oscuro y una empuñadura decorada con motivos de llamas. Se dice que carga una maldición que augura un destino trágico a sus portadores. Es extremadamente afilada y veloz.',
        'Katana Maldita (Wazamono)', '+32 Daño Cortante / +5% Crítico', 'Destreza 35 / Voluntad 40', 'No comercializable', 'images/game/objetos_banner.png'
    ],
    [
        'Kokuto Yoru (Espada Negra de la Noche)', 'armas', 'Armas', 'legendario', 'Legendario',
        'La espada negra más fuerte del mundo, forjada como una espada gigante de una sola mano.',
        'Una de las 12 Saijo O Wazamono (espadas de grado supremo). Su hoja negra está templada permanentemente gracias al Haki del Rey y Armadura de su portador, Dracule Mihawk. Mide más de dos metros de largo y posee un guardamanos en forma de cruz enjoyada.',
        'Gran Espada Negra (Saijo O Wazamono)', '+80 Daño Físico / Cortes a Distancia', 'Destreza 80 / Haki Máximo', 'Invalorable', 'images/game/objetos_banner.png'
    ]
];

foreach ($objetos_data as $obj) {
    $sql = "INSERT INTO {$prefix}game_objetos (name, category, category_name, rarity, rarity_name, `desc`, details, tipo_objeto, bono, req_uso, precio, banner) VALUES (
        '" . $db->escape_string($obj[0]) . "',
        '" . $db->escape_string($obj[1]) . "',
        '" . $db->escape_string($obj[2]) . "',
        '" . $db->escape_string($obj[3]) . "',
        '" . $db->escape_string($obj[4]) . "',
        '" . $db->escape_string($obj[5]) . "',
        '" . $db->escape_string($obj[6]) . "',
        '" . $db->escape_string($obj[7]) . "',
        '" . $db->escape_string($obj[8]) . "',
        '" . $db->escape_string($obj[9]) . "',
        '" . $db->escape_string($obj[10]) . "',
        '" . $db->escape_string($obj[11]) . "'
    )";
    $db->write_query($sql);
}
echo "<div class='rpg-admin-ok'>[OK] Objetos poblados con éxito</div>";

// 3.7 Historia
$historia_data = [
    [
        'El Siglo Vacío (Void Century)', 'nuevomundo', 'Nuevo Mundo', 'trama-global', 'Trama Global',
        'El período de 100 años borrado deliberadamente de los registros oficiales por el Gobierno Mundial.',
        'Un lapso de 100 años acontecido hace ocho siglos donde tuvo lugar una guerra mundial entre el Gran Reino Antiguo y una alianza de 20 reinos monárquicos. Las dinastías victoriosas fundaron el Gobierno Mundial actual y prohibieron cualquier tipo de registro para ocultar la verdad.',
        'Hace 800-900 años', 'Laugh Tale', 'Joy Boy, Los 20 Fundadores', 'Lore supremo y objetivo de arqueólogos', 'images/game/historia_banner.png', '0800_vacío'
    ],
    [
        'La Ejecución de Gol D. Roger', 'oriente', 'Mar de Oriente', 'lore-basal', 'Lore Basal',
        'El suceso trascendental en Loguetown que dio origen a la legendaria Gran Era de los Piratas.',
        'El Rey de los Piratas, Gol D. Roger, se entregó a la Marina tras alcanzar Laugh Tale y contraer una enfermedad terminal. Justo antes de ser ejecutado en la plaza pública, pronunció sus famosas últimas palabras, incitando a miles de almas a lanzarse al océano en busca de su legendario tesoro: el One Piece.',
        'Hace 24 años', 'Loguetown (plaza pública)', 'Gol D. Roger, Vicealmirante Garp', 'Inicio de la Gran Era Pirata', 'images/game/historia_banner.png', '1976_roger'
    ],
    [
        'La Tragedia de Ohara', 'grandline', 'Grand Line', 'trama-global', 'Trama Global',
        'La destrucción total de la isla de los eruditos arqueológicos mediante una Buster Call del Gobierno Mundial.',
        'Ohara albergaba el Árbol del Conocimiento y la mayor biblioteca de arqueólogos del mundo. Tras descubrir que estudiaban en secreto el Poneglyph y el Siglo Vacío prohibido, el Gobierno activó una llamada de exterminio militar que borró la isla del mapa, dejando a Robin como única superviviente.',
        'Hace 22 años', 'Isla de Ohara (West Blue)', 'Nico Robin, Vicealmirante Kuzan', 'Prohibición absoluta del estudio de Poneglyphs', 'images/game/historia_banner.png', '1978_ohara'
    ],
    [
        'La Caída de Baroque Works', 'grandline', 'Grand Line', 'batalla-historica', 'Batalla Histórica',
        'El fin del complot de Baroque Works en Alabasta y la derrota militar del Shichibukai Sir Crocodile.',
        'Crocodile conspiró en secreto durante años para instigar una guerra civil en Alabasta y tomar el trono del desierto. Los Sombreros de Paja, junto a la princesa Nefertari Vivi, intervinieron desvelando la verdad tras la lluvia artificial y derrotando al Shichibukai en las tumbas reales.',
        'Hace 2 años', 'Reino de Alabasta', 'Monkey D. Luffy, Crocodile, Nefertari Vivi', 'Aumento drástico de recompensas', 'images/game/historia_banner.png', '1998_baroque'
    ],
    [
        'La Gran Guerra de Marineford', 'marineford', 'Marineford', 'batalla-historica', 'Batalla Histórica',
        'El mayor choque bélico del siglo entre la Marina y la Flota de Barbablanca por rescatar a Portgas D. Ace.',
        'Tras la captura de Ace por parte de Barbanegra, la Marina organizó una ejecución pública. Barbablanca movilizó toda su flota pirata y atacó la fortaleza militar de Marineford. La guerra concluyó con la trágica muerte de Ace y Barbablanca, marcando un cambio radical en la geopolítica del Nuevo Mundo.',
        'Hace 2 años', 'Fortaleza de Marineford', 'Edward Newgate, Ace, Almirante Akainu', 'Fin de la Era de Barbablanca y desorden mundial', 'images/game/historia_banner.png', '1998_marineford'
    ],
    [
        'La Alianza de los Cuatro Emperadores', 'nuevomundo', 'Nuevo Mundo', 'organizacion', 'Organización',
        'La reestructuración del balance de poder tras la caída de Kaido y Big Mom a manos de la Peor Generación.',
        'Tras la legendaria batalla en el tejado de Onigashima en Wano, las dos fuerzas imperiales más antiguas fueron derrotadas. Esto elevó a Luffy y a Buggy el Payaso a la categoría de Yonko, uniéndose a Shanks y a Barbanegra como los cuatro piratas dominantes del Nuevo Mundo.',
        'Actualidad', 'País de Wano / Isla Egghead', 'Sombreros de Paja, Cross Guild, Red Hair, Barbanegra', 'Estado de poder geopolítico actual', 'images/game/historia_banner.png', '2000_yonko'
    ]
];

foreach ($historia_data as $ev) {
    $sql = "INSERT INTO {$prefix}game_historia (name, saga, saga_name, type, type_name, `desc`, details, epoca, ubicacion, personajes, impacto, banner, event_date) VALUES (
        '" . $db->escape_string($ev[0]) . "',
        '" . $db->escape_string($ev[1]) . "',
        '" . $db->escape_string($ev[2]) . "',
        '" . $db->escape_string($ev[3]) . "',
        '" . $db->escape_string($ev[4]) . "',
        '" . $db->escape_string($ev[5]) . "',
        '" . $db->escape_string($ev[6]) . "',
        '" . $db->escape_string($ev[7]) . "',
        '" . $db->escape_string($ev[8]) . "',
        '" . $db->escape_string($ev[9]) . "',
        '" . $db->escape_string($ev[10]) . "',
        '" . $db->escape_string($ev[11]) . "',
        '" . $db->escape_string($ev[12]) . "'
    )";
    $db->write_query($sql);
}
echo "<div class='rpg-admin-ok'>[OK] Eventos históricos poblados con éxito</div>";

echo "</div>
    <div class='rpg-admin-footer'>
        <p class='rpg-admin-info'>Todo configurado correctamente. Ya puedes visitar las bibliotecas.</p>
        <a href='npc.php' class='btn'>Ir a la Biblioteca NPCs</a>
    </div>
</body>
</html>";
