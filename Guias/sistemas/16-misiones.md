# 16. Sistema de Misiones

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 16
> **Propósito:** Documentar exhaustivamente el subsistema de Misiones: modelo de datos, servicios, AJAX, flujos de creación/asignación/ejecución/revisión, sistema de recompensas, filosofía de diseño, y consejos operativos para jugadores y staff.
> **Audiencia:** Desarrolladores, game masters, y jugadores avanzados que necesitan entender el ciclo de vida completo de una misión.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [Modelo de Datos](#2-modelo-de-datos)
3. [Tablas Auxiliares](#3-tablas-auxiliares)
4. [Categorías de Misión](#4-categorías-de-misión)
5. [Rangos de Misión](#5-rangos-de-misión)
6. [Ciclo de Vida de una Misión](#6-ciclo-de-vida-de-una-misión)
7. [Flujo de Creación (Staff)](#7-flujo-de-creación-staff)
8. [Flujo de Asignación (Jugador)](#8-flujo-de-asignación-jugador)
9. [Flujo de Ejecución y Posteo](#9-flujo-de-ejecución-y-posteo)
10. [Flujo de Finalización y Revisión](#10-flujo-de-finalización-y-revisión)
11. [Sistema de Recompensas](#11-sistema-de-recompensas)
12. [Sistema de Invitaciones y Grupo](#12-sistema-de-invitaciones-y-grupo)
13. [Cooldown y Restricciones](#13-cooldown-y-restricciones)
14. [Integración con Tablón de Misiones](#14-integración-con-tablón-de-misiones)
15. [Integración con Zona Staff](#15-integración-con-zona-staff)
16. [Archivos del Subsistema](#16-archivos-del-subsistema)
17. [Flujo de Datos Completo](#17-flujo-de-datos-completo)
18. [Filosofía de Diseño](#18-filosofía-de-diseño)
19. [Consejos para Jugadores](#19-consejos-para-jugadores)
20. [Consejos para Staff](#20-consejos-para-staff)
21. [Guía de Troubleshooting](#21-guía-de-troubleshooting)

---

## 1. Arquitectura General

### 1.1 Capas del Subsistema

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CLIENTE (Navegador)                          │
│  ┌──────────────────────┐  ┌──────────────────────────────┐        │
│  │ tablon_misiones.js   │  │ zona_staff_misiones.js       │        │
│  │ (ver, filtrar,       │  │ (CRUD catálogo de misiones)  │        │
│  │  aceptar, completar) │  │                              │        │
│  └──────────┬───────────┘  └──────────────┬───────────────┘        │
└─────────────┼──────────────────────────────┼────────────────────────┘
              │ POST (JSON)                  │ POST (JSON)
              ▼                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    AJAX (game/ajax/)                                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────────┐  │
│  │mission_accept│  │mission_confirm│  │admin_missions_action     │  │
│  │aceptar misión│  │responder inv.│  │crear/editar/desactivar   │  │
│  └──────┬───────┘  └──────┬───────┘  └──────────────┬───────────┘  │
│  ┌──────────────┐  ┌──────┴────────┐                 │              │
│  │mission_      │  │mission_       │                 │              │
│  │complete.php  │  │confirm.php    │                 │              │
│  └──────┬───────┘  └───────────────┘                 │              │
└─────────┼────────────────────────────────────────────┼──────────────┘
          ▼                                            ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     CAPA DE DOMINIO (game/inc/)                     │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ mission_helpers.php                                           │  │
│  │ - game_get_character_active_mission()                         │  │
│  │ - game_character_has_cooldown()                               │  │
│  │ - game_character_can_accept_mission()                         │  │
│  │ - game_accept_mission()                                       │  │
│  │ (crea hilo MyBB, inserts en missions_active + participants)   │  │
│  └──────────────────────────────────────────────────────────────┘  │
└──────────────────────────┬──────────────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      BASE DE DATOS (MySQL)                          │
│  ┌──────────────┐  ┌──────────────────┐  ┌──────────────────────┐  │
│  │ game_misiones │  │game_missions_   │  │game_mission_         │  │
│  │ (catálogo)    │  │active (ejecución)│  │participants (roles)  │  │
│  └──────────────┘  └──────────────────┘  └──────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

### 1.2 Principio Arquitectónico

El sistema sigue el patrón **PHP orquesta, MySQL ejecuta** (B-ORO-01). Los controladores PHP (`tablon_misiones.php`, `zona_staff_misiones.php`, y los AJAX endpoints) validan entrada, aplican reglas de negocio ligeras, y delegan toda la lógica de misión crítica a `mission_helpers.php`. Las reglas de elegibilidad (nivel, cooldown, estado del personaje) se evalúan siempre del lado servidor — nunca se confía en el cliente.

### 1.3 Relación con el Ecosistema

| Sistema | Relación con Misiones |
|---------|----------------------|
| Personajes (`game_personajes`) | Cada misión requiere personajes aprobados. Se validan nivel, estado y facción. |
| Puntos Destino (PD) | Las misiones son la **fuente principal** de PD. Las recompensas se asignan por rango. |
| Berries | Recompensa secundaria en berries. Se distribuyen entre participantes. |
| Cards | Las misiones pueden dropear cards como recompensa adicional (integración futura). |
| Hilos/Posts | Cada misión genera un hilo en el foro. El progreso se mide por conteo de posts. |
| Notificaciones | Invitaciones, cambios de estado y finalización disparan notificaciones. |
| Reputación de isla/tripulación | Misiones completadas afectan la reputación local (integración futura). |
| Admin Requests | Al completar, se genera una solicitud de revisión para el staff (`AdminRequestService`). |
| Facción | Las misiones se filtran por facción. Un marine no ve misiones de piratas. |

---

## 2. Modelo de Datos

### 2.1 Tabla `game_misiones` — Catálogo de Misiones

Esta tabla almacena las **definiciones** de misiones que el staff crea y que aparecen en el tablón público.

```sql
CREATE TABLE `mybb_game_misiones` (
    `id`              INT             AUTO_INCREMENT PRIMARY KEY,
    `title`           VARCHAR(255)    NOT NULL,
    `description`     TEXT            NOT NULL,
    `rank`            VARCHAR(10)     NOT NULL COMMENT 'D|C|B|A|S',
    `min_level`       INT             NOT NULL DEFAULT 1,
    `max_level`       INT             NOT NULL DEFAULT 99,
    `points_reward`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `berry_reward`    INT             NOT NULL DEFAULT 0,
    `isla`            VARCHAR(100)    NOT NULL COMMENT 'Isla de destino',
    `categoria`       VARCHAR(64)     NOT NULL DEFAULT 'mision' COMMENT 'combate|exploracion|sigilo|escolta|supervivencia|diplomacia',
    `faction`         VARCHAR(64)     NOT NULL DEFAULT 'Global' COMMENT 'Global|Marine|Pirata|Revolucionario|Gobierno|Cazador|Civil',
    `max_posts`       INT             NOT NULL DEFAULT 15 COMMENT 'Posts requeridos para cumplir',
    `is_active`       TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_active` (`is_active`),
    KEY `idx_rank` (`rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Columnas detalladas:

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT AUTO_INCREMENT | Identificador único de la misión en el catálogo |
| `title` | VARCHAR(255) | Nombre público de la misión. Debe ser descriptivo y atractivo |
| `description` | TEXT | Brief narrativo. Incluye contexto, objetivos y oráculos para el master |
| `rank` | VARCHAR(10) | D, C, B, A, S. Determina la dificultad base |
| `min_level` | INT | Nivel mínimo del personaje para aceptar |
| `max_level` | INT | Nivel máximo del personaje para aceptar |
| `points_reward` | SMALLINT UNSIGNED | PD que otorga al completarse |
| `berry_reward` | INT | Berries que otorga al completarse |
| `isla` | VARCHAR(100) | Isla donde ocurre la misión. Determina el foro donde se crea el hilo |
| `categoria` | VARCHAR(64) | Tipo de misión. Ver sección 4 |
| `faction` | VARCHAR(64) | Facción que puede ver/aceptar la misión. `Global` = todas |
| `max_posts` | INT | Posts mínimos que el grupo debe redactar para cumplir |
| `is_active` | TINYINT(1) | 1 = visible en tablón, 0 = oculta/desactivada |
| `created_at` | TIMESTAMP | Fecha de creación |

#### Justificación de diseño:

- **`min_level`/`max_level`**: Permite segmentar contenido por rango de poder. Un personaje nivel 5 no debería aceptar misiones rango S ni uno nivel 80 misiones rango D. Esto protege tanto la inmersión como el balance.
- **`faction`**: Las misiones exclusivas de facción refuerzan la identidad de grupo. `Global` es el caso por defecto para misiones neutrales.
- **`is_active`**: Se usa soft-delete para preservar integridad referencial con `game_missions_active`. Una misión con `is_active=0` no se puede aceptar nueva, pero las instancias activas siguen siendo válidas.
- **`max_posts`**: Define el mínimo de posts requeridos. Esto fuerza un mínimo de interacción antes de declarar la misión completada. Previene abusos donde alguien crea un hilo y lo cierra inmediatamente.

### 2.2 Tabla `game_missions_active` — Instancias de Misión en Ejecución

Cada vez que un jugador acepta una misión, se crea un registro aquí. Representa una **ejecución específica** de una misión del catálogo.

```sql
CREATE TABLE `mybb_game_missions_active` (
    `id`                  INT             AUTO_INCREMENT PRIMARY KEY,
    `mission_id`          INT             NOT NULL COMMENT 'FK a game_misiones.id',
    `tid`                 INT             NOT NULL DEFAULT 0 COMMENT 'Thread ID en MyBB',
    `leader_character_id` INT             NOT NULL COMMENT 'Character ID del líder',
    `status`              VARCHAR(24)     NOT NULL DEFAULT 'pending' COMMENT 'pending|active|review|completed|failed',
    `post_count`          INT             NOT NULL DEFAULT 0,
    `started_at`          TIMESTAMP       NULL DEFAULT NULL,
    `completed_at`        TIMESTAMP       NULL DEFAULT NULL,
    `created_at`          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_mission` (`mission_id`),
    KEY `idx_thread` (`tid`),
    KEY `idx_leader` (`leader_character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Estados (`status`):

| Estado | Significado | Transiciones posibles |
|--------|-------------|----------------------|
| `pending` | Esperando confirmación de acompañantes | → `active` (cuando todos confirman) |
| `active` | En progreso. El hilo está abierto | → `review` (líder declara completada) |
| `review` | En revisión del staff. Hilo cerrado | → `completed` (staff aprueba) o → `active` (staff rechaza y reabre) |
| `completed` | Misión completada y recompensas entregadas | — (terminal) |
| `failed` | Misión fallida o abandonada | — (terminal) |

#### Columnas detalladas:

| Columna | Descripción |
|---------|-------------|
| `mission_id` | FK a la definición en `game_misiones`. Permite resolver el catálogo original. |
| `tid` | Thread ID del hilo creado en MyBB para rolear la misión. |
| `leader_character_id` | El personaje que lidera el grupo. Solo el líder puede declarar la misión completada. |
| `status` | Máquina de estados de la ejecución. |
| `post_count` | Número de posts en el hilo (se actualiza al completar desde `mybb_threads.replies`). |
| `started_at` | Timestamp de cuando la misión pasó a `active`. |
| `completed_at` | Timestamp de cuando el staff marcó `completed`. |

### 2.3 Tabla `game_mission_participants` — Participantes

Cada fila representa un personaje participante en una misión activa.

```sql
CREATE TABLE `mybb_game_mission_participants` (
    `id`                INT         AUTO_INCREMENT PRIMARY KEY,
    `active_mission_id` INT         NOT NULL COMMENT 'FK a game_missions_active.id',
    `character_id`      INT         NOT NULL COMMENT 'FK a game_personajes.id',
    `user_id`           INT         NOT NULL COMMENT 'FK a mybb_users.uid',
    `confirmed`         TINYINT(1)  NOT NULL DEFAULT 0 COMMENT '0=pendiente, 1=confirmado',
    `last_post_at`      TIMESTAMP   NULL DEFAULT NULL,
    `cooldown_until`    TIMESTAMP   NULL DEFAULT NULL,
    UNIQUE KEY `uq_active_char` (`active_mission_id`, `character_id`),
    KEY `idx_char_cooldown` (`character_id`, `cooldown_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Columnas detalladas:

| Columna | Descripción |
|---------|-------------|
| `active_mission_id` | FK a la instancia activa de misión. |
| `character_id` | Personaje participante. |
| `user_id` | Usuario dueño del personaje (para notificaciones). |
| `confirmed` | 0 = invitación pendiente, 1 = aceptó. El líder se crea con `confirmed=1`. |
| `last_post_at` | Timestamp del último post del personaje en el hilo. |
| `cooldown_until` | Fecha hasta la cual el personaje no puede aceptar nuevas misiones. Se asigna 14 días al completar. |

### 2.4 Tabla `game_pd_purchases` — Gastos de PD

Asociada al sistema de PD, registra compras realizadas con puntos destino (ver guía 21-PD).

```sql
CREATE TABLE `mybb_game_pd_purchases` (
    `id`            INT             AUTO_INCREMENT PRIMARY KEY,
    `character_id`  INT             NOT NULL,
    `pd_cost`       SMALLINT UNSIGNED NOT NULL,
    `item_type`     VARCHAR(64)     NOT NULL COMMENT 'card|slot|race_change|name_change|npc|boost',
    `item_slug`     VARCHAR(128)    NOT NULL,
    `item_name`     VARCHAR(255)    NOT NULL,
    `purchased_at`  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_character` (`character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.5 Schema JSON para `requirements` (futuro)

El schema actual no tiene columna `requirements` JSON — los requisitos se expresan mediante `min_level`, `max_level`, `rank`, `faction`. Si en el futuro se añade una columna JSON, se espera este formato:

```json
{
  "required_oficios": ["navegacion", "medicina"],
  "required_disciplinas": ["kenjutsu", "observacion_haki"],
  "required_items": ["eternal_pose", "log_pose"],
  "crew_min_size": 3,
  "crew_max_size": 5,
  "npc_allowed": false,
  "exclusive_faction": "Marine"
}
```

### 2.6 Schema JSON para `rewards` (futuro)

Actualmente las recompensas son planas (`points_reward`, `berry_reward`). Para soporte futuro:

```json
{
  "pd": 6,
  "berries": 8000,
  "cards_drop": [
    {"card_id": 42, "probability": 0.3},
    {"card_id": 87, "probability": 0.1}
  ],
  "reputation": {
    "isla": "Alabasta",
    "amount": 50
  },
  "faction_reputation": {
    "faction": "Marine",
    "amount": 20
  },
  "special_unlock": "acceso_a_ghost_island"
}
```

---

## 3. Tablas Auxiliares

### 3.1 `game_user_config`

Relaciona usuarios MyBB con su personaje activo. Se usa para determinar qué personaje está realizando acciones de misión.

```sql
-- Fragmento relevante
SELECT active_pj_id FROM mybb_game_user_config WHERE user_id = {$uid}
```

### 3.2 `game_personajes` (columnas relevantes)

```sql
SELECT id, name, status, user_id, faction, staff_level, puntos_destino, berries, data_json
FROM mybb_game_personajes
```

De `data_json` se extrae `nivel` para validar requisitos de misión.

### 3.3 `mybb_threads` (MyBB nativa)

```sql
SELECT tid, fid, subject, replies, closed FROM mybb_threads WHERE tid = {$tid}
```

Se usa para obtener el conteo real de posts (`replies + 1`) al completar una misión.

### 3.4 `mybb_forums`

```sql
SELECT fid, name, type FROM mybb_forums WHERE name = '{$isla}' AND type = 'f'
```

Se usa para encontrar el foro destino donde crear el hilo de la misión. Si no existe un foro con el nombre de la isla, se busca un foro con nombre "Misiones" como fallback.

---

## 4. Categorías de Misión

Cada misión pertenece a una de seis categorías. La categoría determina el **enfoque narrativo** y los **objetivos típicos**, pero no impone mecánicas específicas — un jugador puede resolver una misión de sigilo con combate si así lo decide, aunque las recompensas y la evaluación considerarán el enfoque.

### 4.1 Tabla de Categorías

| Categoría | Definición | Qué implica narrativamente | Requerimientos mecánicos típicos | Recompensas típicas | Ejemplos (genéricos) |
|-----------|-----------|---------------------------|----------------------------------|---------------------|----------------------|
| `combate` | Eliminar una amenaza, cazar a alguien | Enfrentamiento directo contra enemigos. Escenas de acción, tácticas de grupo, uso de habilidades ofensivas. | Stats de ataque/defensa altos. Habilidades de combate. | PD altos, Berries moderados, drops de cards de enemigos. | "La Infestación de Ratz" (D), "El Despertar de la Bestia Calamar" (A) |
| `exploracion` | Descubrir lugares, cartografiar, investigar | Viaje a lo desconocido. Descubrimiento de ruinas, islas, fenómenos. Énfasis en narrativa de mundo. | Stat de Percepción o Investigación. Log Pose/Eternal Pose. | PD moderados, Berries bajos, lore unlocks, mapas. | "La Búsqueda del Sextante Perdido" (D) |
| `sigilo` | Infiltración, robo, sabotaje | Evitar detección. Moverse en las sombras, recopilar información, neutralizar objetivos sin ser visto. | Stat de Sigilo. Habilidades de subterfugio. Velocidad. | PD moderados, Berries altos (botín), reputación de facción. | "Infiltración en la Mansión de Banchina" (C) |
| `escolta` | Proteger a alguien o algo | Asegurar el paso seguro de un NPC, cargamento o convoy. Gestión de recursos y defensa. | Stats defensivos. Habilidades de protección y soporte. | PD moderados, Berries altos (pago por servicio), reputación. | "Escolta en el Desierto de Alabasta" (B) |
| `supervivencia` | Sobrevivir en condiciones extremas | Resistir entornos hostiles: climas extremos, bestias, hambre, enfermedades con efecto narrativo. | Stat de Resistencia. Habilidades de supervivencia y medicina. | PD altos (por dificultad), Berries bajos, items de supervivencia. | "La Anomalía del Triángulo de Florian" (S) |
| `diplomacia` | Negociar, mediar conflictos, establecer alianzas | Diálogo y persuasión. Resolver conflictos sin violencia. Construir relaciones entre facciones. | Stat de Carisma o Persuasión. Rango/fama. Habilidades sociales. | PD moderados, Berries moderados, reputación de facción alta, aliados NPC. | *No implementada en código aún — ver nota* |

### 4.2 Nota sobre `diplomacia`

La categoría `diplomacia` está definida en el diseño conceptual (`MAESTRO_SISTEMAS_RPG.md`) pero **no aparece implementada en el selector del editor de misiones del staff** (zona_staff_misiones.php línea 184-190). Para activarla, el staff debe añadirla manualmente al array de opciones en el HTML y en las validaciones del JS. La base de datos la soporta (VARCHAR(64) sin ENUM), por lo que insertar misiones con `categoria = 'diplomacia'` funciona sin migración. El filtro del tablón también la soportará por ser una columna de texto libre.

### 4.3 Filosofía de las 6 Categorías

Las seis categorías cubren los **arquetipos narrativos fundamentales de One Piece**:

| Arquetipo One Piece | Categoría correspondiente |
|---------------------|--------------------------|
| Arco de pelea contra villano | `combate` |
| Arco de descubrimiento de isla | `exploracion` |
| Arco de infiltración (Water 7/Enies Lobby) | `sigilo` |
| Arco de protección (Alabasta/Skypiea) | `escolta` |
| Arco de resistencia extrema (Impel Down) | `supervivencia` |
| Arco de negociación (Drum Island/Wano alianzas) | `diplomacia` |

Esta taxonomía asegura que **cualquier trama canónica de One Piece tenga una categoría de misión que la represente**. Esto permite al staff diseñar misiones que se sientan temáticamente auténticas al universo.

---

## 5. Rangos de Misión

### 5.1 Tabla de Rangos

| Rango | Dificultad | PD Recomendados | Berries Recomendados | Nivel Mínimo | Nivel Máximo | Tripulación recomendada | Complejidad narrativa |
|-------|-----------|-----------------|---------------------|-------------|-------------|------------------------|----------------------|
| D | Novato | 1–2 | 200–500 | 1 | 15 | Solitario | Un objetivo simple. 10–12 posts. |
| C | Estándar | 2–4 | 500–1.500 | 5 | 25 | 1–2 personas | Objetivo con obstáculo. 12–15 posts. |
| B | Intermedio | 4–6 | 1.500–5.000 | 10 | 40 | 2–3 personas | Múltiples objetivos. 15–20 posts. |
| A | Avanzado | 6–10 | 5.000–15.000 | 20 | 60 | 3–4 personas | Objetivos en cadena, giros. 20–25 posts. |
| S | Experto | 10–20 | 15.000–50.000 | 35 | 99 | 3–5 personas | Trama compleja, múltiples actos. 25–30 posts. |
| SS | Legendario | 20+ | 50.000+ | 50 | 99 | 5+ personas | Épica, implicaciones mundiales, facciones. 30+ posts. |

### 5.2 Seeds incluidas en migración

El archivo `sql/migrate_missions_system.php` incluye seeds de ejemplo que ilustran la progresión de rangos:

```php
$seeds = [
    ['La Infestación de Ratz',            'D', 1, 500,  'Loguetown',       'combate',       15],
    ['La Búsqueda del Sextante Perdido',  'D', 1, 400,  'Ohara',           'exploracion',   12],
    ['Infiltración en la Mansión...',     'C', 2, 1200, 'Syrup',           'sigilo',        15],
    ['Escolta en el Desierto de Alabasta','B', 4, 3500, 'Alabasta',        'escolta',       20],
    ['El Despertar de la Bestia Calamar', 'A', 6, 8000, 'Water 7',         'combate',       25],
    ['La Anomalía del Triángulo...',       'S', 10,20000,'Florian Triangle','supervivencia', 30],
];
```

### 5.3 Relación Rango ↔ PD

El PD es la **moneda de mérito principal** del foro (ver guía 21). La relación rango/PD está diseñada para que:

- Una misión D da 1 PD — accesible para novatos, recompensa baja.
- Una misión S da 10–20 PD — comparable a semanas de rol activo.
- El salto D→C es pequeño (1→2 PD), pero C→B es 2→4 PD (100% incremento), reflejando que el esfuerzo requerido crece exponencialmente.

Esto evita el "grindeo" de misiones bajas: un personaje de nivel alto obtiene más PD por hora/hilo haciendo una misión A que cinco misiones D.

---

## 6. Ciclo de Vida de una Misión

```
  ┌──────────┐    ┌──────────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌─────────────┐
  │ CREACIÓN  │───▶│ PUBLICACIÓN  │───▶│ASIGNACIÓN│───▶│EJECUCIÓN │───▶│ REVISIÓN │───▶│ RECOMPENSAS │
  │ (staff)   │    │ (tablón)     │    │(jugador)  │    │(hilo)    │    │ (staff)  │    │ (automático)│
  └──────────┘    └──────────────┘    └──────────┘    └──────────┘    └──────────┘    └─────────────┘
       │                │                  │               │              │                │
       ▼                ▼                  ▼               ▼              ▼                ▼
  game_misiones    is_active=1        missions_active    thread +      status=review    puntos_destino
  .INSERT                           .INSERT              posts         .UPDATE          .UPDATE
                                                                                        berries
                                                                                        .UPDATE
```

### 6.1 Diagrama de Estados Detallado

```
                    ┌─────────────────────────────────────────────┐
                    │            game_misiones                    │
                    │  is_active = 1 (visible en tablón)         │
                    │  is_active = 0 (oculta, soft-delete)       │
                    └─────────────────────┬───────────────────────┘
                                          │
                                    accept mission
                                          │
                                          ▼
                    ┌─────────────────────────────────────────────┐
                    │        game_missions_active                 │
                    │                                           │
                    │  status = 'pending'                       │
                    │  (esperando confirmación de acompañantes)  │
                    └─────────────────────┬───────────────────────┘
                                          │
                              ┌───────────┴───────────┐
                              │                       │
                      all confirmed             companion declines
                              │                       │
                              ▼                       ▼
                    ┌──────────────┐       ┌────────────────────┐
                    │  'active'    │       │ Si era el último   │
                    │  started_at  │       │ pendiente → active  │
                    │  = NOW()     │       └────────────────────┘
                    └──────┬───────┘
                           │
                     leader clicks
                     "Declarar Completada"
                           │
                           ▼
                    ┌──────────────┐
                    │  'review'    │
                    │  Hilo cerrado│
                    │  Cooldown 14d│
                    │  AdminRequest│
                    └──────┬───────┘
                           │
                    staff evalúa
                           │
               ┌───────────┴───────────┐
               │                       │
         staff aprueba           staff rechaza
               │                       │
               ▼                       ▼
        ┌──────────────┐      ┌──────────────────┐
        │ 'completed'  │      │  'active' (reabre)│
        │ PD entregados │      │  Hilo reabierto   │
        │ Berries dados │      │  Se puede seguir  │
        └──────────────┘      │  roleando         │
                              └──────────────────┘
```

---

## 7. Flujo de Creación (Staff)

### 7.1 Endpoint: `admin_missions_action.php`

**Método:** POST · **Content-Type:** `application/json` · **Requiere CSRF:** sí

**Acción `create`:**

```php
// Validación de staff level >= 3
// Validación de campos obligatorios: title, description, isla

$db->write_query("INSERT INTO {$prefix}game_missions 
    (title, description, `rank`, min_level, max_level, points_reward, berry_reward, isla, categoria, faction, max_posts, is_active)
    VALUES 
    ('{$titleEsc}', '{$descEsc}', '{$rankEsc}', {$min_level}, {$max_level}, {$points_reward}, {$berry_reward}, '{$islaEsc}', '{$catEsc}', '{$factionEsc}', {$max_posts}, 1)");
```

**Payload de ejemplo:**

```json
{
  "action": "create",
  "title": "El Secreto del Faro Abandonado",
  "description": "Un faro en la costa norte de Loguetown ha dejado de funcionar. Los lugareños reportan luces extrañas por la noche. Investiga qué ocurre y restaura el faro si es posible.",
  "rank": "C",
  "min_level": 5,
  "max_level": 25,
  "points_reward": 3,
  "berry_reward": 1500,
  "isla": "Loguetown",
  "categoria": "exploracion",
  "faction": "Global",
  "max_posts": 15
}
```

**Acción `edit`:** Mismo payload que `create` pero incluye `id`. Hace UPDATE.

**Acción `delete`:** Soft-delete (set `is_active = 0`).

```php
// No se borra físicamente para preservar referencias en missions_active
$db->write_query("UPDATE {$prefix}game_missions SET is_active = 0 WHERE id = {$id}");
```

### 7.2 Interfaz: `zona_staff_misiones.php`

Renderiza una tabla con el catálogo actual y un modal para crear/editar. El modal incluye:

- **Título** (text)
- **Descripción** (textarea)
- **Rango** (select: D, C, B, A, S)
- **Isla** (text)
- **Facción** (select: Global, Marine, Pirata, Revolucionario, Gobierno, Cazador, Civil)
- **Nivel mínimo/máximo** (number)
- **Recompensa PD** (number)
- **Recompensa Berries** (number)
- **Categoría** (select: combate, exploracion, sigilo, escolta, supervivencia)
- **Límite de posts** (number, default 15)

**Nota:** La categoría `diplomacia` no aparece en el select por ahora. Ver sección 4.2.

### 7.3 Validaciones del Lado Servidor

En `admin_missions_action.php`:

```php
if ($title === '' || $description === '' || $isla === '') {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Título, descripción e isla son obligatorios.'], 400);
}
```

No se valida que `categoria` sea uno de los valores permitidos — confía en el cliente. Esto es un **riesgo de seguridad bajo** porque la columna es VARCHAR, pero en producción se debería validar contra un array de categorías permitidas:

```php
$allowedCategories = ['combate', 'exploracion', 'sigilo', 'escolta', 'supervivencia', 'diplomacia'];
if (!in_array($categoria, $allowedCategories)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Categoría inválida.'], 400);
}
```

### 7.4 Plantillas para Creación Rápida

El staff puede usar estas plantillas como punto de partida:

#### Plantilla: Misión de Combate (Rango C)

```json
{
  "title": "[NOMBRE DEL ENEMIGO] está causando estragos en [LUGAR]",
  "description": "[DESCRIPCIÓN DEL PROBLEMA]. Los ciudadanos están aterrorizados. Se necesita un equipo capaz de [neutralizar/expulsar/derrotar] a la amenaza.",
  "rank": "C",
  "min_level": 5,
  "max_level": 25,
  "points_reward": 3,
  "berry_reward": 1200,
  "isla": "[ISLA]",
  "categoria": "combate",
  "faction": "Global",
  "max_posts": 15
}
```

#### Plantilla: Misión de Sigilo (Rango B)

```json
{
  "title": "Infiltración en [LUGAR]",
  "description": "[FACTIÓN/NOMBRE] ha obtenido [INFORMACIÓN/OBJETO] que necesitamos. Debes infiltrarte sin ser detectado y [robarlo/copiarlo/sabotearlo]. Si te descubren, la misión se complicará.",
  "rank": "B",
  "min_level": 15,
  "max_level": 45,
  "points_reward": 5,
  "berry_reward": 4000,
  "isla": "[ISLA]",
  "categoria": "sigilo",
  "faction": "Global",
  "max_posts": 18
}
```

---

## 8. Flujo de Asignación (Jugador)

### 8.1 Verificación de Elegibilidad

`mission_helpers.php` → `game_character_can_accept_mission()`:

```php
function game_character_can_accept_mission(int $characterId, int $missionId, string &$error = ''): bool
{
    // 1. Personaje existe y está aprobado
    // 2. Nivel cumple min_level y max_level
    // 3. No tiene misión activa
    // 4. No está en cooldown
    // (Nota: no verifica facción actualmente — el filtro es solo visual en el tablón)
}
```

**Árbol de decisión:**

```
¿Personaje existe? ──NO──→ error: "Personaje no existe"
       │
      SÍ
       │
       ▼
¿Status = 'aprobada'? ──NO──→ error: "Debe estar aprobado por el staff"
       │
      SÍ
       │
       ▼
¿Nivel entre min/max? ──NO──→ error: "Nivel no cumple requisitos"
       │
      SÍ
       │
       ▼
¿Tiene misión activa? ──SÍ──→ error: "Ya participas en una misión activa"
       │
      NO
       │
       ▼
¿Está en cooldown? ──SÍ──→ error: "En cooldown hasta [fecha]"
       │
      NO
       │
       ▼
    → TRUE (puede aceptar)
```

### 8.2 Aceptación de Misión

**Frontend:** `tablon_misiones.js` → `submitAcceptMission()`

```javascript
function submitAcceptMission() {
    var missionId = parseInt(document.getElementById("accept_mission_id").value) || 0;
    var companions = [];
    document.querySelectorAll(".companion-checkbox:checked").forEach(function (cb) {
        companions.push(parseInt(cb.value));
    });
    
    gameFetchPost("mission_accept.php", {
        character_id: characterId,
        mission_id: missionId,
        companions: companions
    }).then(function (res) {
        if (res.ok) {
            alert("¡Misión aceptada! Hilo generado. Redirigiendo...");
            window.location.reload();
        } else {
            alert("Error: " + (res.error ? res.error.message : "Desconocido"));
        }
    });
}
```

**Backend:** `mission_accept.php`

```php
// 1. Validar personaje activo del usuario
// 2. Llamar a game_accept_mission() del helper
// 3. Devolver active_mission_id, thread_id, thread_url
```

### 8.3 `game_accept_mission()` — El Núcleo

`mission_helpers.php` línea 101:

```php
function game_accept_mission(int $leaderCharacterId, int $missionId, array $participantCharacterIds, string &$error = ''): ?int
```

**Secuencia de operaciones:**

1. **Validar líder** — llama a `game_character_can_accept_mission()` para el líder. Si falla, retorna null con error.
2. **Validar acompañantes** — itera sobre los IDs de acompañantes y valida cada uno con `game_character_can_accept_mission()`. Si uno falla, toda la operación se cancela (transaccional).
3. **Buscar foro destino** — intenta encontrar un foro MyBB cuyo nombre coincida con `mission.isla`. Si no existe, busca un foro que contenga "Misiones" en el nombre. Si todo falla, usa foro ID 2.
4. **Buscar usuario Narrador** — busca un usuario MyBB con username 'Narrador'. Si no existe, usa UID 2.
5. **Construir post del hilo** — genera el contenido del primer post con formato MyBB (BBCode):
   - Título: `[Misión Rango {rank}] {title}`
   - Contenido: brief, participantes, recompensas, límite de posts, objetivos.
6. **Crear hilo MyBB** — usa `PostDataHandler` con `action = 'thread'`. El hilo se crea bajo el usuario Narrador, no bajo el jugador.
7. **Registrar metadatos de tiempo** — inserta en `game_thread_meta` la fecha del mundo (Presente).
8. **Insertar registro activo** — en `game_missions_active`:
   - Status = `'active'` si el líder va solo, `'pending'` si hay acompañantes (esperar confirmación).
   - `started_at` = NOW() si no hay acompañantes, NULL si los hay.
9. **Insertar participantes** — líder con `confirmed=1`, acompañantes con `confirmed=0`.
10. **Notificar acompañantes** — envía notificación vía `NotificationService` a cada acompañante.

### 8.4 Ejemplo de Hilo Generado

```
Asunto: [Misión Rango C] Infiltración en la Mansión de Banchina

[Misión Oficial del Narrador]

Título: Infiltración en la Mansión de Banchina
Rango: C
Lugar/Isla: Syrup
Categoría: Sigilo
Recompensas: 2 PD | 1200 Berries
Límite de Posts: 15
Líder del Grupo: Monkey D. Example
Acompañantes: Roronoa Zoro, Nami

───────────────────────────────────────

Descripción del Encargo:
Un usurero local posee registros falsificados de propiedades de familias
pobres. Entra sin ser detectado y destruye los libros de contabilidad.

Objetivos principales:
1. Realizar un mínimo de posts que cumplan con la coherencia narrativa.
2. Completar o reportar el desenlace del encargo.

¡Buena suerte en vuestra aventura!
```

---

## 9. Flujo de Ejecución y Posteo

### 9.1 El Hilo de Rol

Una vez creado el hilo, los participantes rolean la misión posteando en él. No hay restricciones de orden de posteo — cualquier participante puede postear cuando quiera.

El sistema **no trackea individualmente** quién postea cuánto. Solo cuenta el total de posts en el hilo (`replies + 1` de MyBB). Esto es intencional: fomenta la colaboración sin microgestión.

### 9.2 Seguimiento de Progreso

El progreso se muestra en el tablón de misiones cuando el jugador tiene una misión activa:

```html
<div class="rpg-misiones-progress-header">
    <span>Progreso de Posts:</span>
    <strong>{post_count} / {max_posts} posts</strong>
</div>
<div class="rpg-misiones-progress-bar">
    <div class="rpg-misiones-progress-fill" data-progress="{percent}"></div>
</div>
```

La barra de progreso se renderiza con CSS dinámico desde el atributo `data-progress`. El JS en `tablon_misiones.js` lo lee y establece el ancho:

```javascript
function initProgressBars() {
    document.querySelectorAll(".rpg-misiones-progress-fill").forEach(function (fill) {
        var progress = fill.getAttribute("data-progress") || "0";
        fill.style.width = progress + "%";
    });
}
```

### 9.3 Política de Posts

- **Mínimo:** El staff define `max_posts` por misión (típicamente 12–30 según rango).
- **Sin máximo:** Los jugadores pueden postear más de `max_posts`. El extra no penaliza pero tampoco otorga recompensa adicional.
- **Calidad sobre cantidad:** El staff evalúa calidad narrativa al revisar, no solo conteo. Sin embargo, el conteo es un gate: si no se alcanza `max_posts`, la misión no puede declararse completada.

---

## 10. Flujo de Finalización y Revisión

### 10.1 Declarar Completada

El **líder del grupo** (y solo el líder) puede declarar la misión completada. Esto se hace desde el tablón de misiones o desde la gestión del personaje.

**Endpoint:** `mission_complete.php`

```php
// 1. Verificar que el personaje es el líder de la misión
// 2. Verificar que status === 'active'
// 3. Obtener post_count real desde mybb_threads.replies + 1
// 4. UPDATE status = 'review'
// 5. Cerrar el hilo en MyBB (threads.closed = 1)
// 6. Asignar cooldown 14 días a todos los participantes confirmados
// 7. Crear AdminRequest para revisión del staff
// 8. Notificar al staff
```

**Conteo de posts:**

```php
$threadQ = $db->query("SELECT replies FROM {$prefix}threads WHERE tid = {$tid} LIMIT 1");
if ($threadRow = $db->fetch_array($threadQ)) {
    $postCount = (int)$threadRow['replies'] + 1;  // +1 porque replies no incluye el primer post
}
```

**Cooldown:**

```php
$db->write_query("
    UPDATE {$prefix}game_mission_participants 
    SET cooldown_until = DATE_ADD(NOW(), INTERVAL 14 DAY) 
    WHERE active_mission_id = {$activeMissionId} AND confirmed = 1
");
```

### 10.2 Solicitud de Revisión (AdminRequest)

Se crea una solicitud en el sistema de peticiones del staff:

```php
$requestId = AdminRequestService::create(
    $uid,                    // User ID del líder
    $characterId,            // Character ID del líder
    'mision',                // Tipo: 'mision'
    'mision_review',         // Subtipo: 'mision_review'
    $title,                  // Título: "Revisión Misión: {title}"
    $description,            // Descripción con detalles
    $link,                   // Link al hilo: "showthread.php?tid={tid}"
    ['active_mission_id' => $activeMissionId]  // Meta data
);

// Notificar a todo el staff
AdminRequestService::notifyStaffPending(
    "Nueva Misión Completada: " . $activeMission['title'],
    "/game/public/zona_staff_peticiones.php"
);
```

### 10.3 Revisión del Staff

El staff revisa la solicitud desde `zona_staff_peticiones.php`. Decide:

- **Aprobar:** Se entregan las recompensas (PD, Berries). La misión pasa a `completed`.
- **Rechazar con comentarios:** La misión vuelve a `active`, el hilo se reabre, y el staff deja feedback sobre qué mejorar (más posts, mejor narrativa, etc.).

**Proceso de aprobación (código conceptual):**

```php
// staff_approve_mission_review.php (conceptual, no implementado literalmente)
$db->begin_transaction();
try {
    // 1. Marcar misión como completed
    $db->write_query("UPDATE {$prefix}game_missions_active 
        SET status = 'completed', completed_at = NOW() 
        WHERE id = {$activeMissionId}");
    
    // 2. Entregar PD al líder
    $db->write_query("UPDATE {$prefix}game_personajes 
        SET puntos_destino = puntos_destino + {$pdReward} 
        WHERE id = {$leaderId}");
    
    // 3. Entregar berries al líder (se distribuirá manualmente)
    $db->write_query("UPDATE {$prefix}game_personajes 
        SET berries = berries + {$berryReward} 
        WHERE id = {$leaderId}");
    
    // 4. Registrar transacción de PD
    $db->write_query("INSERT INTO {$prefix}game_pd_log 
        (character_id, amount, reason, source, related_id) 
        VALUES ({$leaderId}, {$pdReward}, 'Misión completada', 'mission', {$activeMissionId})");
    
    $db->commit();
} catch (\Exception $e) {
    $db->rollback();
    throw $e;
}
```

### 10.4 Distribución de Recompensas

Actualmente, las recompensas se entregan **al líder del grupo**, quien debe distribuirlas entre los participantes. Este es un diseño deliberado que:

1. **Fomenta la confianza grupal:** el líder administra las recompensas.
2. **Simplifica la lógica:** el staff entrega una vez al líder.
3. **Permite splits personalizados:** el líder decide si distribuye equitativamente o según contribución.

Para una futura automatización, se podría implementar split automático:

```php
$participants = get_confirmed_participants($activeMissionId);
$perParticipantPD = floor($pdReward / count($participants));
$perParticipantBerries = floor($berryReward / count($participants));
foreach ($participants as $p) {
    $db->write_query("UPDATE {$prefix}game_personajes 
        SET puntos_destino = puntos_destino + {$perParticipantPD},
            berries = berries + {$perParticipantBerries} 
        WHERE id = {$p['character_id']}");
}
```

---

## 11. Sistema de Recompensas

### 11.1 PD (Puntos Destino)

| Rango | PD | Uso típico |
|-------|----|------------|
| D | 1 | Un slot extra de inventario |
| C | 2–4 | Card de tienda especial |
| B | 4–6 | Card personalizada |
| A | 6–10 | Cambio de nombre/raza |
| S | 10–20 | NPC aliado, boost de rango |
| SS | 20+ | Misión privada, item legendario |

### 11.2 Berries

| Rango | Berries | Ratio PD:Berries |
|-------|---------|-----------------|
| D | 200–500 | ~1:400 |
| C | 500–1.500 | ~1:450 |
| B | 1.500–5.000 | ~1:650 |
| A | 5.000–15.000 | ~1:1,000 |
| S | 15.000–50.000 | ~1:1,500 |
| SS | 50.000+ | ~1:2,000 |

El ratio PD:Berries se incrementa con el rango: las misiones de alto rango pagan proporcionalmente más berries porque se espera que los personajes de alto nivel tengan mayores gastos.

### 11.3 Recompensas Adicionales (Futuro)

El sistema está diseñado para soportar estas recompensas adicionales:

- **Drop de cards:** Al completar la misión, ciertas cards pueden caer con probabilidad definida. La integración se haría vía `game_card_ownership` INSERT.
- **Reputación de isla:** Afecta el precio en tiendas locales, acceso a misiones exclusivas, y actitud de NPCs.
- **Reputación de facción:** Similar, pero a nivel de facción (Marina, Piratas, etc.).
- **Unlocks especiales:** Acceso a zonas restringidas, NPCs que solo aparecen tras completar ciertas misiones, etc.

### 11.4 Filosofía: PD como Recompensa Primaria

Las misiones son la **principal fuente de PD del juego**. A diferencia de los berries (que se pueden obtener por comercio, eventos, o tiradas), los PD solo se obtienen por:

1. Completar misiones (fuente principal)
2. Eventos especiales
3. Actividad rolística destacada (staff discretion)

Esto asegura que:
- Las misiones siempre sean relevantes: incluso jugadores de alto nivel necesitan PD.
- El PD mantenga su valor como moneda de mérito.
- El staff tenga control sobre la economía de PD.

---

## 12. Sistema de Invitaciones y Grupo

### 12.1 Invitación a Acompañantes

Cuando un jugador acepta una misión e invita acompañantes:

1. El líder selecciona compañeros en el modal de aceptación.
2. Cada acompañante recibe una notificación y ve una invitación pendiente en el tablón.
3. El acompañante puede **aceptar** o **rechazar** desde el tablón.

**Endpoint:** `mission_confirm.php`

**Acción `accept`:**

```php
// 1. UPDATE confirmed = 1
// 2. Si todos confirmaron → UPDATE status = 'active', started_at = NOW()
// 3. Notificar al líder que todos aceptaron
```

**Acción `decline`:**

```php
// 1. DELETE participant record
// 2. Notificar al líder del rechazo
// 3. Si era el único pendiente y misión estaba 'pending' → activar (solo el líder)
```

### 12.2 Flujo de Grupo

```
Líder acepta misión
    │
    ├── Sin acompañantes → status = 'active' (inmediato)
    │
    └── Con acompañantes → status = 'pending'
            │
            ├── Todos aceptan → status = 'active'
            │
            └── Alguien rechaza → se elimina del grupo
                    │
                    ├── Sin más pendientes → status = 'active' (líder solo)
                    │
                    └── Si quedan pendientes → sigue esperando
```

### 12.3 Notificaciones

El sistema usa `\Game\Application\Services\NotificationService` para:

| Evento | Destinatario | Mensaje |
|--------|-------------|---------|
| Invitación enviada | Acompañante | "X te ha invitado a participar en la misión Y" |
| Aceptación de invitación | Líder | "X ha aceptado unirse a la misión Y" |
| Rechazo de invitación | Líder | "X ha rechazado unirse a la misión Y" |
| Todos confirmaron | Líder | "Todos aceptaron. La misión Y ha comenzado." |
| Misión completada | Staff | "Nueva solicitud de revisión: Y" |

---

## 13. Cooldown y Restricciones

### 13.1 Cooldown por Personaje

Al completar una misión, todos los participantes (confirmados) reciben un cooldown de **14 días**:

```sql
UPDATE mybb_game_mission_participants 
SET cooldown_until = DATE_ADD(NOW(), INTERVAL 14 DAY) 
WHERE active_mission_id = {$activeMissionId} AND confirmed = 1
```

### 13.2 Verificación de Cooldown

```php
function game_character_has_cooldown(int $characterId, ?string &$cooldown_until_label = null): bool
{
    $q = $db->query("
        SELECT MAX(cooldown_until) AS max_cooldown 
        FROM {$prefix}game_mission_participants 
        WHERE character_id = {$characterId} AND cooldown_until > NOW()
    ");
    $res = $db->fetch_array($q);
    if ($res && $res['max_cooldown']) { return true; }
    return false;
}
```

### 13.3 Restricciones Acumulativas

Un personaje no puede aceptar una misión si:

1. **No existe** en `game_personajes`.
2. **No está aprobado** (`status !== 'aprobada'`).
3. **Nivel fuera de rango** (`min_level`/`max_level`).
4. **Ya tiene una misión activa** (status `pending` o `active` en `game_missions_active`).
5. **Está en cooldown** (`cooldown_until > NOW()`).

**Nota de diseño:** Actualmente no se valida facción al aceptar. La facción se usa solo como filtro visual en el tablón. Esto significa que un personaje marino podría aceptar una misión de facción pirata si esquiva el filtro. Se recomienda implementar validación del lado servidor:

```php
$pjFaction = get_standard_faction($pj['faction'] ?? 'Civil');
$missionFaction = $mission['faction'];
if ($missionFaction !== 'Global' && $missionFaction !== $pjFaction) {
    $error = "Esta misión no está disponible para tu facción.";
    return false;
}
```

---

## 14. Integración con Tablón de Misiones

### 14.1 Vista Pública: `tablon_misiones.php`

**URL:** `game/public/tablon_misiones.php`

**Funcionalidades:**
- Lista de misiones disponibles en formato mini-cards.
- Filtros por: Rango, Isla, Categoría.
- Modal de detalles de misión.
- Modal de aceptación con selección de acompañantes.
- Sección de misión activa con barra de progreso.
- Sección de invitación pendiente.

### 14.2 Filtros

```sql
WHERE is_active = 1
  AND (faction = 'Global' OR faction = '{$pjFaction}')
  [AND rank = '{$filterRank}']
  [AND isla = '{$filterIsla}']
  [AND categoria = '{$filterCat}']
ORDER BY FIELD(`rank`, 'D', 'C', 'B', 'A', 'S') ASC, title ASC
```

El `ORDER BY FIELD` asegura que las misiones aparezcan ordenadas por dificultad, no alfabéticamente por rango (que daría A antes que B).

### 14.3 Mini-Cards de Misión

Cada misión se renderiza como una tarjeta clickeable:

```html
<div class="rpg-mission-mini-card" onclick='openMissionDetailsModal({json_data})'>
    <div class="rpg-mission-mini-rank {rank_class}">{rank}</div>
    <div class="rpg-mission-mini-body">
        <h4>{title}</h4>
    </div>
    <div class="rpg-mission-mini-arrow">
        <i class="fas fa-chevron-right"></i>
    </div>
</div>
```

### 14.4 Modal de Detalles

Al hacer clic en una mini-card, se abre un modal con:

- Rango (con color codificado por clase CSS)
- Título
- Badges: Isla, Categoría, Nivel requerido, Facción
- Descripción completa
- Recompensas (PD y Berries)
- Botón "Iniciar Misión" (deshabilitado con tooltip si no cumple requisitos)

**Verificación de elegibilidad en frontend:**

```javascript
var btn = document.getElementById("btn_open_accept");
if (data.can_accept) {
    btn.disabled = false;
    btn.onclick = function() {
        closeMissionDetailsModal();
        openAcceptMissionModal(data.id, data.title);
    };
} else {
    btn.disabled = true;
    btn.title = data.error || "No puedes aceptar esta misión";
}
```

### 14.5 Modal de Aceptación

Al hacer clic en "Iniciar Misión" se abre un segundo modal:

- Título de la misión
- Lista de acompañantes disponibles (checkboxes)
- Botón "Iniciar Misión" (envía POST a `mission_accept.php`)

---

## 15. Integración con Zona Staff

### 15.1 Vista Staff: `zona_staff_misiones.php`

**URL:** `game/public/zona_staff_misiones.php`

**Acceso:** Requiere `staff_level >= 3` en el personaje activo.

**Funcionalidades:**
- Tabla del catálogo completo de misiones activas.
- Columnas: Título, Rango, Isla, Facción, Categoría, Recompensas, Límite de Posts, Acciones.
- Botón "Nueva Misión" abre modal de creación.
- Botón de edición (lápiz) abre modal precargado.
- Botón de eliminación (basura) hace soft-delete con confirmación.

### 15.2 Modal de Editor

Campos del formulario (todos con validación frontend):

| Campo | Tipo | Validación |
|-------|------|-----------|
| Título | text | Requerido |
| Descripción | textarea | Requerido |
| Rango | select (D/C/B/A/S) | Requerido |
| Isla | text | Requerido |
| Facción | select | Default: Global |
| Nivel Mínimo | number (min=1) | Default: 1 |
| Nivel Máximo | number (min=1) | Default: 99 |
| Recompensa PD | number (min=0) | Default: 1 |
| Recompensa Berries | number (min=0) | Default: 500 |
| Categoría | select (5 tipos) | Default: combate |
| Límite Posts | number (min=5) | Default: 15 |

### 15.3 Integración con Sistema de Peticiones

Cuando una misión se declara completada, se crea una `AdminRequest` de tipo `mision`/`mision_review`. El staff revisa estas solicitudes en `zona_staff_peticiones.php`.

**Estructura de la solicitud:**

```php
[
    'user_id' => $uid,
    'character_id' => $characterId,
    'type' => 'mision',
    'subtype' => 'mision_review',
    'title' => "Revisión Misión: {$activeMission['title']}",
    'description' => "El grupo liderado por {$leaderName} ha completado la misión...",
    'link' => "showthread.php?tid={$tid}",
    'meta' => ['active_mission_id' => $activeMissionId]
]
```

---

## 16. Archivos del Subsistema

### 16.1 Mapa de Archivos

```
back/forum/game/
├── ajax/
│   ├── mission_accept.php              # POST: Aceptar misión (líder)
│   ├── mission_confirm.php             # POST: Aceptar/rechazar invitación
│   ├── mission_complete.php            # POST: Declarar misión completada
│   └── admin_missions_action.php       # POST: CRUD catálogo (staff)
├── inc/
│   └── mission_helpers.php             # Lógica central de misiones
├── public/
│   ├── tablon_misiones.php             # Vista pública: tablón de misiones
│   └── zona_staff_misiones.php         # Vista staff: gestión de catálogo
├── sql/
│   ├── migrate_missions_system.php     # Creación de tablas + seeds
│   └── migrate_missions_faction.php    # ADD COLUMN faction
└── src/Application/Services/
    ├── NotificationService.php         # Notificaciones (invitaciones, etc.)
    └── AdminRequestService.php         # Solicitudes de revisión

back/forum/jscripts/game/
├── tablon_misiones.js                  # JS del tablón público
└── zona_staff_misiones.js              # JS de gestión staff

Guias/
├── MAESTRO_SISTEMAS_RPG.md             # Documento maestro (sección 16)
└── sistemas/
    └── 16-misiones.md                  # Esta guía
```

### 16.2 Dependencias

| Archivo | Depende de |
|---------|-----------|
| `mission_helpers.php` | `inc/functions_post.php` (MyBB), `PostDataHandler` |
| `mission_accept.php` | `PersonajeRepository`, `mission_helpers.php` |
| `mission_confirm.php` | `NotificationService` |
| `mission_complete.php` | `AdminRequestService` |
| `admin_missions_action.php` | N/A (SQL directo) |
| `tablon_misiones.php` | `game_render_page()` (bootstrap) |
| `zona_staff_misiones.php` | `game_render_page()` (bootstrap) |

---

## 17. Flujo de Datos Completo

### 17.1 Aceptación de Misión (Líder solo)

```
Usuario                  Navegador              AJAX                  PHP Helper              MySQL
  │                        │                     │                       │                     │
  │ Hace clic en           │                     │                       │                     │
  │ "Iniciar Misión"       │                     │                       │                     │
  │───────────────────────▶│                     │                       │                     │
  │                        │                     │                       │                     │
  │                        │ POST /ajax/         │                       │                     │
  │                        │ mission_accept.php  │                       │                     │
  │                        │────────────────────▶│                       │                     │
  │                        │                     │                       │                     │
  │                        │                     │ game_accept_mission() │                     │
  │                        │                     │──────────────────────▶│                     │
  │                        │                     │                       │                     │
  │                        │                     │                       │ SELECT personaje     │
  │                        │                     │                       │────────────────────▶│
  │                        │                     │                       │◀────────────────────│
  │                        │                     │                       │                     │
  │                        │                     │                       │ SELECT mission      │
  │                        │                     │                       │────────────────────▶│
  │                        │                     │                       │◀────────────────────│
  │                        │                     │                       │                     │
  │                        │                     │                       │ Validar nivel/      │
  │                        │                     │                       │ cooldown/activa     │
  │                        │                     │                       │                     │
  │                        │                     │                       │ INSERT thread       │
  │                        │                     │                       │ (PostDataHandler)   │
  │                        │                     │                       │────────────────────▶│
  │                        │                     │                       │                     │
  │                        │                     │                       │ INSERT missions_    │
  │                        │                     │                       │ active              │
  │                        │                     │                       │────────────────────▶│
  │                        │                     │                       │                     │
  │                        │                     │                       │ INSERT participants │
  │                        │                     │                       │────────────────────▶│
  │                        │                     │                       │                     │
  │                        │                     │◀──────────────────────│                     │
  │                        │                     │  active_mission_id    │                     │
  │                        │◀────────────────────│                       │                     │
  │                        │  JSON {ok, tid,     │                       │                     │
  │                        │  thread_url}        │                       │                     │
  │◀───────────────────────│                     │                       │                     │
  │ alert("Misión          │                     │                       │                     │
  │ aceptada") + reload    │                     │                       │                     │
```

### 17.2 Finalización de Misión (Líder → Staff)

```
Líder                    AJAX                   PHP Helper                MySQL              Staff
  │                       │                        │                      │                  │
  │ Clica "Declarar       │                        │                      │                  │
  │ Completada"           │                        │                      │                  │
  │──────────────────────▶│                        │                      │                  │
  │                       │                        │                      │                  │
  │                       │ POST /ajax/            │                      │                  │
  │                       │ mission_complete.php   │                      │                  │
  │                       │                        │                      │                  │
  │                       │ Verificar líder        │                      │                  │
  │                       │ Verificar status=active│                      │                  │
  │                       │                        │                      │                  │
  │                       │ SELECT replies         │                      │                  │
  │                       │───────────────────────────────────────────────▶│                  │
  │                       │◀───────────────────────────────────────────────│                  │
  │                       │                        │                      │                  │
  │                       │ UPDATE status=review   │                      │                  │
  │                       │───────────────────────────────────────────────▶│                  │
  │                       │                        │                      │                  │
  │                       │ UPDATE threads.closed=1│                      │                  │
  │                       │───────────────────────────────────────────────▶│                  │
  │                       │                        │                      │                  │
  │                       │ UPDATE cooldown_until  │                      │                  │
  │                       │───────────────────────────────────────────────▶│                  │
  │                       │                        │                      │                  │
  │                       │ AdminRequestService    │                      │                  │
  │                       │ ::create()             │                      │                  │
  │                       │───────────────────────────────────────────────▶│                  │
  │                       │                        │                      │                  │
  │                       │ AdminRequestService    │                      │                  │
  │                       │ ::notifyStaffPending() │                      │                  │
  │                       │───────────────────────────────────────────────▶│                  │
  │                       │                        │                      │  Notificación     │
  │                       │                        │                      │◀─────────────────│
  │                       │                        │                      │                  │
  │◀──────────────────────│                        │                      │                  │
  │ JSON {ok, status:     │                        │                      │                  │
  │ 'review'}             │                        │                      │                  │
  │                       │                        │                      │                  │
  │                       │                        │                      │  (Staff revisa    │
  │                       │                        │                      │   y aprueba)      │
  │                       │                        │                      │  ────────────────▶│
  │                       │                        │                      │                  │
  │                       │                        │                      │ UPDATE status=    │
  │                       │                        │                      │ completed         │
  │                       │                        │                      │◀─────────────────│
  │                       │                        │                      │                  │
  │                       │                        │                      │ UPDATE personajes │
  │                       │                        │                      │ puntos_destino    │
  │                       │                        │                      │ berries           │
  │                       │                        │                      │◀─────────────────│
  │                       │                        │                      │                  │
```

---

## 18. Filosofía de Diseño

### 18.1 ¿Por Qué 6 Categorías?

One Piece es un shonen de aventuras con arcos que alternan entre:
- **Pelea contra el villano** (Arlong Park, Enies Lobby, Dressrosa)
- **Exploración de una isla misteriosa** (Skypiea, Zou, Wano)
- **Infiltración y sabotaje** (Water 7, Impel Down)
- **Protección de un aliado** (Alabasta, Whole Cake Island)
- **Supervivencia en condiciones hostiles** (Little Garden, Punk Hazard)
- **Diplomacia y alianzas** (Drum Island, Alianzas de Wano)

Cada categoría cubre exactamente uno de estos arquetipos. No se añadieron más categorías para evitar solapamiento y mantener el sistema manejable para el staff.

### 18.2 ¿Por Qué Rangos D→SS?

La escala D→SS sigue la convención de ranking común en RPGs y en el universo One Piece (D→S es la escala típica de dificultad, SS es el "extra" para contenido legendario). La decisión de no usar números (1–10) es:

1. **Narrativa:** Un rango "S" suena más emocionante que "nivel 9".
2. **Agrupación:** Los rangos agrupan rangos de PD y nivel, dando al staff libertad para ajustar dentro del rango.
3. **Escalabilidad:** SS deja espacio para rangos adicionales (SSS, X) si se necesitan en el futuro.

### 18.3 ¿Por Qué PD como Recompensa Primaria?

Ver sección 11.4. En resumen: el PD necesita ser escaso para mantener su valor. Las misiones son la fuente controlada principal. Si los PD fueran fáciles de obtener (ej: por cada post), perderían su significado como "moneda de mérito".

### 18.4 ¿Por Qué el Staff Crea el Hilo?

Cuando un jugador acepta una misión, el hilo se crea automáticamente **bajo el usuario Narrador**, no bajo el jugador. Esto:

1. **Da autoridad narrativa:** El hilo empieza con un post del Narrador que establece el encargo.
2. **Separa角色 y jugador:** El hilo no pertenece a ningún personaje específico — es un thread de misión oficial.
3. **Permite cierre automático:** Al completar la misión, el sistema puede cerrar el hilo porque fue creado por el sistema.

### 18.5 ¿Por Qué Cooldown de 14 Días?

El cooldown evita que un jugador encadene misiones sin pausa, lo que:

1. **Fomenta diversidad:** El jugador debe hacer otras actividades (rol libre, eventos, desarrollo de personaje).
2. **Previene burnout:** Tanto del jugador como del staff que revisa.
3. **Da tiempo para la revisión:** El staff tiene 14 días para revisar sin presión.
4. **Simula realismo:** Los personajes necesitan descansar entre misiones importantes.

### 18.6 Decisiones Arquitectónicas (ADR)

| Decisión | Alternativa considerada | Razón |
|----------|------------------------|-------|
| Tablas separadas para catálogo e instancias | Tabla única con flag de activa | Separar permite reusar misiones (varios grupos pueden tomar la misma misión en el futuro) |
| Soft-delete en game_misiones | DELETE físico | Preserva integridad referencial con missions_active histórica |
| PHP orquesta, SQL ejecuta | Lógica en stored procedures | Mantenibilidad: el código PHP es más fácil de debuggear y versionar |
| PostDataHandler de MyBB | INSERT directo a tablas MyBB | Usar el handler oficial asegura que se disparen hooks, se generen correctamente los BBCode, y se respete la seguridad de MyBB |
| Cooldown por personaje, no por usuario | Cooldown global por cuenta | Un usuario con múltiples personajes puede rotar misiones entre ellos |

---

## 19. Consejos para Jugadores

### 19.1 Elegir Misiones para tu Build

| Tu build | Categorías recomendadas |
|----------|------------------------|
| Guerrero cuerpo a cuerpo | `combate`, `escolta` |
| Francotirador / ranged | `combate` (soporte), `sigilo` |
| Navegante / explorador | `exploracion`, `supervivencia` |
| Médico | `escolta`, `supervivencia` (crítico) |
| Diplomático / carismático | `diplomacia`, `sigilo` (persuasión) |
| Asesino sigiloso | `sigilo`, `combate` (emboscada) |

### 19.2 Composición de Grupo

| Tipo de misión | Composición ideal |
|---------------|-------------------|
| `combate` | 2 DPS + 1 soporte + 1 tanque |
| `exploracion` | 1 explorador + 1 combatiente + 1 médico |
| `sigilo` | 1 sigiloso principal + 1 apoyo táctico |
| `escolta` | 1 protector + 1 explorador + 2 combatientes |
| `supervivencia` | 1 médico + 1 combatiente + 1 explorador |
| `diplomacia` | 1 diplomático + 1 combatiente (disuasión) |

**Principio general:** Nunca vayas solo a misiones de rango ≥ B si puedes evitarlo. La dificultad está calibrada para grupos de 2–3 personas.

### 19.3 Maximizar Recompensas

- **Completa el máximo de posts:** Aunque el mínimo es `max_posts`, más posts = más material para que el staff evalúe positivamente.
- **Cubre los objetivos:** La descripción de la misión lista objetivos. Asegúrate de abordarlos todos narrativamente.
- **Interacción entre personajes:** El staff valora el roleo entre miembros del grupo, no solo la resolución del objetivo.
- **Documenta tu progreso:** Si la misión tiene múltiples fases (ej: explorar → encontrar → combatir → escapar), menciónalas explícitamente en tus posts.

### 19.4 Gestión de Cooldown

- Planifica tus misiones con 14 días de separación.
- Usa el tiempo de cooldown para: desarrollo de personaje, misiones de otros personajes, eventos del foro, rol libre.
- Si tienes múltiples personajes, puedes rotar: mientras uno está en cooldown, el otro puede aceptar misiones.

---

## 20. Consejos para Staff

### 20.1 Escribir Briefs Atractivos

Un buen brief de misión debe incluir:

1. **Contexto:** ¿Qué está pasando? ¿Por qué es urgente?
2. **Objetivo claro:** ¿Qué deben hacer los jugadores?
3. **Oráculos:** Pistas sobre lo que pueden encontrar. No reveles todo — deja espacio para la improvisación. Usa frases como "Se rumorea que...", "Los informes mencionan...".
4. **Consecuencias:** ¿Qué pasa si fallan? ¿Qué pasa si tienen éxito?
5. **NPCs involucrados:** Quién da la misión, quién puede ayudar, quién puede obstaculizar.

**Ejemplo de brief bueno vs malo:**

❌ **Malo:** "Mata a 10 bandidos en la costa."
✅ **Bueno:** "El pueblo costero de Shimotsuki está siendo aterrorizado por una banda de piratas menor que se ha atrincherado en una cueva al norte. Los ancianos del pueblo ofrecen una recompensa por su captura o eliminación. Se rumorea que la banda ha secuestrado a un niño del pueblo — verifica si sigue con vida antes de actuar."

### 20.2 Balancear Dificultad

| Síntoma de desbalance | Solución |
|----------------------|----------|
| Misiones D demasiado difíciles | Bajar min_level, reducir max_posts, bajar PD |
| Nadie toma misiones de rango X | Revisar si la recompensa es adecuada; quizás el ratio PD/esfuerzo es bajo |
| Grupos completan en 2 días | Aumentar max_posts para forzar más desarrollo narrativo |
| Misiones siempre en solitario | Crear misiones que requieran explícitamente habilidades complementarias |

### 20.3 Fair Reward Distribution

- **Entrega todo al líder** y confía en que distribuya. Si hay problemas, intervienes.
- **Para misiones con grupos grandes**, considera recompensas extra (bonus por coordinación).
- **Documenta las recompensas entregadas** en el hilo de la misión o en un registro interno.

### 20.4 Crear Cadenas de Misiones (Arcos)

Las misiones pueden encadenarse para formar arcos narrativos. Estrategias:

1. **Misión → Recompensa → Siguiente misión:** "Completa X para desbloquear Y."
2. **Múltiples rutas:** "Elige entre A, B, o C — cada una lleva a un resultado diferente."
3. **Arco en 3 actos:** Misión 1 (establece), Misión 2 (complica), Misión 3 (resuelve).
4. **Misiones paralelas:** Varias misiones que ocurren simultáneamente y afectan el resultado global.

**Ejemplo de arco:**

| Orden | Misión | Rango | Conexión |
|-------|--------|-------|----------|
| 1 | "El Mensajero Perdido" | D | Un mensajero de la Resistencia ha desaparecido en Loguetown |
| 2 | "El Código Secreto" | C | El mensajero llevaba un cifrado; hay que descifrarlo |
| 3 | "La Célula Durmiente" | B | El cifrado revela una célula de espías del Gobierno en la isla |
| 4 | "Levantamiento" | A | La célula planea un golpe de estado; hay que detenerlos |

### 20.5 Templates para Creación Rápida

Usa estos templates en el editor para mantener consistencia:

#### Template: Misión de Arco

```json
{
  "title": "[ARCO]: [NOMBRE DE MISIÓN]",
  "description": "Parte [N] del arco '[NOMBRE DEL ARCO]'. [CONTEXTO]. Objetivos: [OBJETIVOS].",
  "rank": "[RANGO]",
  "isla": "[ISLA]",
  "categoria": "[CATEGORÍA]",
  "faction": "[FACCIÓN]",
  "max_posts": [POSTS]
}
```

#### Template: Misión de Evento Estacional

```json
{
  "title": "[EVENTO]: [NOMBRE]",
  "description": "Evento especial de [TEMPORADA]. [CONTEXTO]. Participantes: máximo [N] personas.",
  "rank": "C",
  "isla": "[ISLA DEL EVENTO]", 
  "categoria": "exploracion",
  "faction": "Global",
  "max_posts": 20
}
```

---

## 21. Guía de Troubleshooting

### 21.1 Problemas Comunes

| Problema | Causa probable | Solución |
|----------|---------------|----------|
| "No puedes aceptar esta misión" sin razón visible | El personaje no está aprobado, o tiene nivel fuera de rango | Verificar status del personaje y nivel en data_json |
| El modal de detalles no se abre | Error de JSON al codificar datos de la misión (caracteres especiales sin escapar) | Revisar `json_encode` en `tablon_misiones.php` línea 302; usar `JSON_HEX_TAG | JSON_HEX_AMP` |
| La misión no aparece en el tablón | `is_active = 0` o facción no coincide | Verificar en BD: `SELECT * FROM mybb_game_missions WHERE is_active = 1` |
| "Error MyBB" al aceptar misión | El foro destino no existe o el PostDataHandler falla | Verificar que el foro con nombre de la isla existe; revisar `forumId` en `game_accept_mission()` |
| El cooldown no se aplica | La consulta UPDATE en `mission_complete.php` falló | Verificar permisos de escritura en tabla `game_mission_participants` |
| La notificación no llega al acompañante | `NotificationService` lanza excepción | El catch ignora errores — revisar logs de PHP |
| El hilo no se cierra al completar | `UPDATE threads SET closed = 1` falla | Verificar que el tid existe y hay permisos de escritura |
| La barra de progreso no se muestra | `data-progress` no se calculó correctamente | El JS lo calcula al cargar; verificar que el atributo existe en el HTML |

### 21.2 Debugging SQL

```sql
-- Ver catálogo completo
SELECT id, title, rank, is_active FROM mybb_game_misiones ORDER BY rank;

-- Ver misiones activas con estado
SELECT ma.id, m.title, ma.status, ma.post_count, ma.started_at 
FROM mybb_game_missions_active ma 
JOIN mybb_game_missions m ON ma.mission_id = m.id;

-- Ver participantes de una misión específica
SELECT mp.character_id, p.name, mp.confirmed, mp.cooldown_until
FROM mybb_game_mission_participants mp
JOIN mybb_game_personajes p ON mp.character_id = p.id
WHERE mp.active_mission_id = [ID];

-- Verificar cooldown de un personaje
SELECT cooldown_until FROM mybb_game_mission_participants 
WHERE character_id = [ID] AND cooldown_until > NOW()
ORDER BY cooldown_until DESC LIMIT 1;

-- Ver solicitudes de revisión pendientes
SELECT * FROM mybb_game_admin_requests 
WHERE type = 'mision' AND status = 'pending';
```

### 21.3 Restablecer una Misión Atascada

Si una misión se queda en estado `pending` porque un acompañante nunca responde:

```sql
-- Opción A: Forzar activación (solo el líder)
UPDATE mybb_game_mission_participants 
SET confirmed = 1 
WHERE active_mission_id = [ID];

UPDATE mybb_game_missions_active 
SET status = 'active', started_at = NOW() 
WHERE id = [ID];

-- Opción B: Eliminar al acompañante fantasma
DELETE FROM mybb_game_mission_participants 
WHERE active_mission_id = [ID] AND confirmed = 0;

-- Si era el único pendiente, la misión se activa automáticamente
-- (ver mission_confirm.php línea 160)
```

### 21.4 Logging y Monitoreo

El sistema actualmente no tiene logging explícito de misiones. Para debugging, monitorear:

- **PHP error log:** Errores de `PostDataHandler`, `NotificationService`, y consultas SQL fallidas.
- **MyBB error log:** Errores de creación de hilos.
- **JavaScript console:** Errores de fetch en `tablon_misiones.js` y `zona_staff_misiones.js`.

Recomendación para futuro: Añadir un log de auditoría de misiones:

```sql
CREATE TABLE mybb_game_mission_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    active_mission_id INT NOT NULL,
    character_id INT NOT NULL,
    action VARCHAR(64) NOT NULL COMMENT 'accepted|confirmed|declined|completed|reviewed|approved|rejected',
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mission (active_mission_id),
    KEY idx_character (character_id)
);
```

---

*Fin del documento — Guía completa del Sistema de Misiones v1.0*
*Generado desde: `Guias/sistemas/16-misiones.md`*
