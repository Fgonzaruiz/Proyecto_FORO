# 14. SISTEMA DE NAVEGACIÓN — RUTAS E ISLAS

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 14
> **Propósito:** Documentar exhaustivamente el subsistema de navegación: modelo de datos, flujo post-by-post, oráculos de clima, integración con oficio de navegante, instrumentos de navegación, ciclo de vida de viajes, revisión de staff, AJAX endpoints, filosofía de diseño, y consejos para jugadores y staff.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [Modelo de Datos — Tablas del Sistema](#2-modelo-de-datos)
3. [Zonas del Mundo](#3-zonas-del-mundo)
4. [Oráculos de Navegación y Clima](#4-oráculos-de-navegación-y-clima)
5. [Procesamiento de Navegación (navigation_process.php)](#5-procesamiento-de-navegación)
6. [Helpers de Navegación (navigation_helpers.php)](#6-helpers-de-navegación)
7. [Helpers de Revisión (navigation_review_helpers.php)](#7-helpers-de-revisión)
8. [Oficio de Navegante](#8-oficio-de-navegante)
9. [Instrumentos de Navegación](#9-instrumentos-de-navegación)
10. [Ciclo de Vida de un Viaje](#10-ciclo-de-vida-de-un-viaje)
11. [Revisión de Staff](#11-revisión-de-staff)
12. [Herramientas de Staff — AJAX](#12-herramientas-de-staff)
13. [AJAX Endpoints — Referencia Completa](#13-ajax-endpoints)
14. [Flujo de Datos Completo](#14-flujo-de-datos-completo)
15. [Filosofía de Diseño](#15-filosofía-de-diseño)
16. [Consejos para Jugadores](#16-consejos-para-jugadores)
17. [Consejos para Staff](#17-consejos-para-staff)
18. [Guía de Troubleshooting](#18-guía-de-troubleshooting)

---

## 1. Arquitectura General

### 1.1 Capas del Subsistema

```
┌─────────────────────────────────────────────────────────────────────┐
│                    CLIENTE (Navegador)                               │
│  ┌──────────────────────────┐  ┌────────────────────────────────┐   │
│  │ new_thread.js / new_post │  │ navigation_context.js           │   │
│  │ (formulario de post)     │  │ (panel de navegación en post)  │   │
│  │                          │  │                                │   │
│  │ Campos:                  │  │ Muestra:                       │   │
│  │  rpg_nav_enabled         │  │  Isla origen                   │   │
│  │  nav_destination_island  │  │  Barcos disponibles            │   │
│  │  nav_ship_card_id        │  │  Instrumentos                  │   │
│  │  nav_instrument          │  │  Grado navegante + bonus       │   │
│  └──────────┬───────────────┘  └───────────────┬────────────────┘   │
│             │                                   │                     │
│             ▼                                   ▼                     │
│  ┌──────────────────────────────────────────────────────────────────┐│
│  │              AJAX (game/ajax/*.php)                              ││
│  │  navigation_context | navigation_ships | navigation_preview      ││
│  │  navigation_voyage_status | navigation_voyage_review             ││
│  │  navigation_voyages_list | navigation_routes_list                ││
│  │  navigation_routes_save | navigation_islands_list                ││
│  └─────────────────────────┬────────────────────────────────────────┘│
└────────────────────────────┼─────────────────────────────────────────┘
                             │ HTTP POST/GET + JSON
┌────────────────────────────┼─────────────────────────────────────────┐
│  ┌─────────────────────────▼────────────────────────────────────────┐│
│  │              PHP — CAPA DE HELPERS                                ││
│  │  navigation_config.php       — Constantes y zonas                ││
│  │  navigation_helpers.php      — Cálculos de ruta, distancia,      ││
│  │                                peligro, velocidad, duración,     ││
│  │                                eventos, oráculos, barcos,        ││
│  │                                instrumentos, cómputo de viaje    ││
│  │  navigation_process.php      — Procesamiento post → voyage      ││
│  │  navigation_review_helpers.php — Revisión staff, posts auto     ││
│  │  oficios_helpers.php         — game_oficio_rank_bonus()          ││
│  │  rol_calendar_helpers.php    — game_rol_days_at()                ││
│  │  oracle_helpers.php          — game_roll_oracle()                ││
│  └──────────────────────────────────────────────────────────────────┘│
│                              │                                        │
│                              ▼                                        │
│  ┌──────────────────────────────────────────────────────────────────┐│
│  │  MySQL — Tablas del sistema                                      ││
│  │  game_forum_islands       → Islas con coordenadas, zona, peligro ││
│  │  game_navigation_routes   → Rutas precalculadas entre islas      ││
│  │  game_navigation_voyages  → Viajes activos/completados           ││
│  │  game_navigation_events   → Eventos generados durante viaje      ││
│  │  game_oracles             → Oráculos de clima y navegación       ││
│  │  game_post_oracles        → Tiradas de oráculo por post          ││
│  │  game_cards               → Barcos e instrumentos (card_type)    ││
│  │  game_character_inventory → Inventario: slot_type='barco'/'equipo'││
│  │  game_character_oficios   → Grados de navegante                  ││
│  └──────────────────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────────────┘
```

### 1.2 Filosofía de la Arquitectura

**¿Por qué post-by-post navigation en lugar de insta-travel?**

La navegación no es un simple teletransporte entre foros. Cada viaje es una oportunidad narrativa. Al ligar la navegación al sistema de posts:
- El jugador declara su intención de viajar en el contenido de su post.
- El sistema procesa la solicitud y genera eventos de navegación (clima, encuentros, descubrimientos).
- El staff revisa el viaje para asegurar calidad narrativa y justicia mecánica.
- El viaje tiene una duración real en el calendario de rol (`rol_days`), dando sensación de distancia y escala.

**¿Por qué separar la lógica en helpers funcionales en lugar de clases Service?**

Misma razón que el sistema de personajes: el código debe ser legible para cualquier desarrollador PHP. Los helpers son funciones planas con namespacing de prefijo `game_nav_*`. La carga cognitiva es baja: cualquier archivo que necesite navegación hace `require_once __DIR__ . '/navigation_helpers.php'` y obtiene acceso a todas las funciones.

**¿Por qué rutas precalculadas vs. cálculo en vivo?**

Ambos modos existen. Si existe una ruta en `game_navigation_routes`, se usa su distancia y waypoints. Si no, se calcula por coordenadas Euclidianas. Las rutas precalculadas permiten al staff definir distancias personalizadas, waypoints narrativos y `danger_override` para rutas específicas.

**¿Por qué oráculos para el clima en lugar de tiradas de stats?**

El sistema de oráculos (`game_oracles`) proporciona resultados narrativos ricos con rangos, descripciones y auto-invocaciones. En lugar de "tirada de navegación: 15 → éxito", el oráculo produce "Corriente inversa favorable: las aguas te empujan hacia tu destino, acortando el viaje". Esto fomenta el roleplay.

### 1.3 Principios de Diseño

1. **Datos y mecánicas en MySQL** (D001): Toda la persistencia está en tablas MyBB.
2. **Validación servidor-side**: Toda escritura se revalida en PHP. El JS solo mejora UX.
3. **Dualidad ruta precalculada/cálculo en vivo**: El sistema tolera rutas definidas y no definidas.
4. **Staff review obligatorio**: Todo viaje debe ser aprobado por staff para completarse.
5. **Tiempo de rol (`rol_days`)**: La duración del viaje se mide en días de rol, no en tiempo real.
6. **Oficio de navegante como multiplicador**: No es obligatorio tenerlo, pero mejora significativamente la velocidad y mitiga eventos.

### 1.4 Integración con el Formulario de Post

La navegación se inicia desde el formulario de nuevo post o edición de post. Los campos HTML se procesan en `navigation_process.php`:

```
┌───────────────────────────────────────────────────────┐
│  Formulario de Post                                   │
│                                                       │
│  [x] Habilitar navegación (rpg_nav_enabled = 1)       │
│                                                       │
│  Destino: [select con islas] (nav_destination_island) │
│                                                       │
│  Barco: [select con barcos del PJ] (nav_ship_card_id) │
│                                                       │
│  Instrumento: [select: brújula/log pose/eternal pose] │
│               (nav_instrument)                        │
│                                                       │
│  [Vista previa del viaje] → AJAX navigation_preview   │
│                                                       │
│  ┌──────────────────────────────────────────────────┐ │
│  │  Resumen del viaje:                              │ │
│  │  Origen: Shells Town → Destino: Orange Town      │ │
│  │  Distancia: 28 u · Duración: 3 días rol          │ │
│  │  Peligro: Moderado (2) · Eventos: 1-3            │ │
│  │  Velocidad efectiva: 6.5 nudos                   │ │
│  │  Barco: Goleta Merry · Navegante: Grado III      │ │
│  └──────────────────────────────────────────────────┘ │
└───────────────────────────────────────────────────────┘
```

---

## 2. Modelo de Datos

### 2.1 Tabla `game_forum_islands` — Islas del Foro

Cada foro (subforo de MyBB) puede ser una isla. La tabla `game_forum_islands` extiende los foros con datos de navegación.

```sql
CREATE TABLE mybb_game_forum_islands (
    fid              INT UNSIGNED NOT NULL PRIMARY KEY,
    island_image     VARCHAR(500) NOT NULL DEFAULT '',
    leader_name      VARCHAR(200) NOT NULL DEFAULT '',
    description      TEXT NOT NULL,
    terrain          VARCHAR(200) NOT NULL DEFAULT '',
    climate          VARCHAR(300) NOT NULL DEFAULT '',
    climate_temp     VARCHAR(100) NOT NULL DEFAULT '',
    climate_wind     VARCHAR(100) NOT NULL DEFAULT '',
    climate_precip   VARCHAR(100) NOT NULL DEFAULT '',
    buildings        TEXT NOT NULL,
    defenses         TEXT NOT NULL,
    resources        VARCHAR(300) NOT NULL DEFAULT '',
    coord_x          INT NOT NULL DEFAULT 0,
    coord_y          INT NOT NULL DEFAULT 0,
    sea_zone         VARCHAR(50) NOT NULL DEFAULT 'east_blue',
    base_danger      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    requires_log_pose TINYINT(1) NOT NULL DEFAULT 0,
    requires_compass TINYINT(1) NOT NULL DEFAULT 0,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `fid` | INT UNSIGNED | FK al foro de MyBB (`mybb_forums.fid`). Clave primaria. |
| `coord_x`, `coord_y` | INT | Coordenadas cartesianas para cálculo de distancia Euclidiana. |
| `sea_zone` | VARCHAR(50) | Zona del mar. Determina qué oráculos se usan. Valores: `east_blue`, `west_blue`, `north_blue`, `south_blue`, `grand_line`, `new_world`, `calm_belt`, `florian_triangle`. |
| `base_danger` | TINYINT UNSIGNED | Peligro base de la isla (1–5). Afecta el cálculo de peligro de rutas desde/hacia esta isla. |
| `requires_log_pose` | TINYINT(1) | Si es 1, el personaje necesita un Log Pose para llegar aquí. |
| `requires_compass` | TINYINT(1) | Si es 1, el personaje necesita una brújula para llegar aquí. |

**Filosofía:** La tabla `game_forum_islands` es la base de todo el sistema. Sin una isla de origen y una de destino, no hay navegación. La función `game_nav_get_island_from_forum()` resuelve recursivamente: si un foro no tiene registro en `game_forum_islands`, sube al foro padre (pid) hasta encontrar uno.

### 2.2 Tabla `game_navigation_routes` — Rutas Precalculadas

```sql
CREATE TABLE mybb_game_navigation_routes (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    island_from_fid  INT UNSIGNED NOT NULL,
    island_to_fid    INT UNSIGNED NOT NULL,
    distance         INT NOT NULL,
    waypoint_fids    TEXT DEFAULT NULL,
    danger_override  TINYINT UNSIGNED DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_route (island_from_fid, island_to_fid),
    KEY idx_from (island_from_fid),
    KEY idx_to (island_to_fid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT AUTO_INCREMENT | Clave primaria. |
| `island_from_fid` | INT UNSIGNED | Isla de origen. FK lógica a `game_forum_islands.fid`. |
| `island_to_fid` | INT UNSIGNED | Isla de destino. FK lógica a `game_forum_islands.fid`. |
| `distance` | INT | Distancia en unidades del foro. Determina la duración del viaje. |
| `waypoint_fids` | TEXT | JSON array de fids de islas intermedias (waypoints sin parada formal). |
| `danger_override` | TINYINT UNSIGNED | Si se define (1–5), sobreescribe el cálculo automático de peligro para esta ruta específica. |
| `created_at` | TIMESTAMP | Fecha de creación. |

**UNIQUE KEY uq_route:** Usa `(island_from_fid, island_to_fid)` como clave única. Esto significa que no puede haber dos rutas en la misma dirección entre el mismo par de islas. La búsqueda en `game_nav_calculate_distance()` también invierte el orden (busca A→B y B→A) para que una ruta funcione en ambos sentidos.

**Ejemplo de fila:**

```json
{
  "id": 1,
  "island_from_fid": 42,
  "island_to_fid": 57,
  "distance": 28,
  "waypoint_fids": "[45, 51]",
  "danger_override": null
}
```

**Filosofía de distance:**
| Contexto | Distancia típica |
|----------|-----------------|
| Islas vecinas mismo Blue | 3–8 |
| Islas distantes mismo Blue | 8–15 |
| Cruzar a Grand Line | 15–25 |
| Grand Line interno | 10–30 |
| New World | 15–40 |

### 2.3 Tabla `game_navigation_voyages` — Viajes

```sql
CREATE TABLE mybb_game_navigation_voyages (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    post_id                INT NOT NULL,
    thread_id              INT NOT NULL,
    character_id           INT NOT NULL,
    ship_card_id           INT NOT NULL,
    island_from_fid        INT UNSIGNED NOT NULL,
    island_to_fid          INT UNSIGNED NOT NULL,
    distance               INT NOT NULL,
    danger_level           TINYINT UNSIGNED NOT NULL,
    duration_days          INT NOT NULL,
    num_events             INT NOT NULL,
    navigator_bonus        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    instrument_used        VARCHAR(100) DEFAULT NULL,
    instrument_bonus       TINYINT NOT NULL DEFAULT 0,
    raw_calculation_json   TEXT DEFAULT NULL,
    status                 ENUM('active','arrived','cancelled') NOT NULL DEFAULT 'active',
    staff_review           ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    start_rol_days         INT UNSIGNED NOT NULL DEFAULT 0,
    expected_end_rol_days  INT UNSIGNED NOT NULL DEFAULT 0,
    reviewed_at            INT UNSIGNED DEFAULT NULL,
    reviewed_by_uid        INT UNSIGNED DEFAULT NULL,
    staff_notice_post_id   INT UNSIGNED DEFAULT NULL,
    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_post (post_id),
    KEY idx_char (character_id),
    KEY idx_thread (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT AUTO_INCREMENT | Clave primaria. |
| `post_id` | INT | Post que inició la navegación. Permite recuperar el contexto narrativo. |
| `thread_id` | INT | Hilo donde se posteó. El staff publica la respuesta automática aquí. |
| `character_id` | INT | Personaje que navega. FK lógica a `game_personajes.id`. |
| `ship_card_id` | INT | Card del barco usado. FK lógica a `game_cards.id`. |
| `island_from_fid` | INT UNSIGNED | Isla de origen. |
| `island_to_fid` | INT UNSIGNED | Isla de destino. |
| `distance` | INT | Distancia calculada. |
| `danger_level` | TINYINT UNSIGNED | Nivel de peligro (1–5). |
| `duration_days` | INT | Duración en días de rol. |
| `num_events` | INT | Número de eventos generados (basado en peligro + duración + aleatoriedad). |
| `navigator_bonus` | TINYINT UNSIGNED | Grado del navegante en el momento del cálculo. |
| `instrument_used` | VARCHAR(100) | Instrumento usado: `compass`, `log_pose`, `eternal_pose`, `none`. |
| `instrument_bonus` | TINYINT | Bonus del instrumento (puede ser negativo si no tiene). |
| `raw_calculation_json` | TEXT | Snapshot completo del cálculo: islas, ruta, peligro, efectos del barco, zona, velocidad. |
| `status` | ENUM | `active` → en progreso · `arrived` → llegó · `cancelled` → cancelado. |
| `staff_review` | ENUM | `pending` → esperando revisión · `approved` → aprobado · `denied` → denegado. |
| `start_rol_days` | INT UNSIGNED | Día de rol en que comenzó el viaje (`game_rol_days_at()`). |
| `expected_end_rol_days` | INT UNSIGNED | Día de rol estimado de llegada (`start_rol_days + duration_days`). |
| `reviewed_at` | INT UNSIGNED | Timestamp Unix de cuando el staff revisó. |
| `reviewed_by_uid` | INT UNSIGNED | UID del staff que revisó. |
| `staff_notice_post_id` | INT UNSIGNED | Post automático publicado en el hilo notificando la revisión. |

**Filosofía de `raw_calculation_json`:** Esta columna es una auditoría completa del cálculo. Permite al staff y al sistema entender exactamente cómo se determinó cada valor. Ejemplo de contenido:

```json
{
  "island_from": {"fid": 42, "coord_x": 100, "coord_y": 200, "sea_zone": "east_blue", "base_danger": 1},
  "island_to": {"fid": 57, "coord_x": 120, "coord_y": 180, "sea_zone": "east_blue", "base_danger": 2},
  "route": {"distance": 28, "waypoints": [45, 51], "is_precalculated": true, "danger_override": null},
  "danger": 2,
  "ship_effects": {"velocidad_base": 5, "nav_bonus_east_blue": 1},
  "sea_zone": "east_blue",
  "effective_speed": 6.5,
  "navigator_rank": 2
}
```

### 2.4 Tabla `game_navigation_events` — Eventos del Viaje

```sql
CREATE TABLE mybb_game_navigation_events (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    voyage_id       INT NOT NULL,
    post_oracle_id  INT NOT NULL,
    event_order     TINYINT UNSIGNED NOT NULL,
    danger_tier     TINYINT UNSIGNED NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_voyage (voyage_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT AUTO_INCREMENT | Clave primaria. |
| `voyage_id` | INT | FK a `game_navigation_voyages.id`. |
| `post_oracle_id` | INT | FK a `game_post_oracles.id`. La tirada del oráculo que generó este evento. |
| `event_order` | TINYINT UNSIGNED | Orden del evento dentro del viaje (1, 2, 3...). |
| `danger_tier` | TINYINT UNSIGNED | Nivel de peligro en el momento del evento. |
| `created_at` | TIMESTAMP | Fecha de creación. |

**Filosofía:** Los eventos no guardan el texto directamente. En su lugar, `post_oracle_id` apunta a `game_post_oracles`, que contiene la tirada real, el rango, el resultado y la descripción. Esto permite reutilizar oráculos y mantener una auditoría completa de todas las tiradas de navegación.

**Relación de tablas:**

```
game_navigation_voyages
  └── game_navigation_events (1:N)
        └── game_post_oracles (N:1)
              └── game_oracles (N:1)
```

### 2.5 Tabla `game_forum_islands` — Columnas de Navegación (Migración)

Las columnas específicas de navegación se añadieron mediante migración (`migrate_navigation_system.php`):

```php
// migrate_navigation_system.php:20-25
game_nav_migration_add_column('game_forum_islands', 'coord_x', 'INT NOT NULL DEFAULT 0');
game_nav_migration_add_column('game_forum_islands', 'coord_y', 'INT NOT NULL DEFAULT 0');
game_nav_migration_add_column('game_forum_islands', 'sea_zone', "VARCHAR(50) NOT NULL DEFAULT 'east_blue'");
game_nav_migration_add_column('game_forum_islands', 'base_danger', 'TINYINT UNSIGNED NOT NULL DEFAULT 1');
game_nav_migration_add_column('game_forum_islands', 'requires_log_pose', 'TINYINT(1) NOT NULL DEFAULT 0');
game_nav_migration_add_column('game_forum_islands', 'requires_compass', 'TINYINT(1) NOT NULL DEFAULT 0');
```

La migración de revisión de viaje (`migrate_navigation_voyage_review.php`) añadió las columnas de staff review:

```php
// migrate_navigation_voyage_review.php:21-26
$add('staff_review', "ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending'");
$add('start_rol_days', 'INT UNSIGNED NOT NULL DEFAULT 0');
$add('expected_end_rol_days', 'INT UNSIGNED NOT NULL DEFAULT 0');
$add('reviewed_at', 'INT UNSIGNED DEFAULT NULL');
$add('reviewed_by_uid', 'INT UNSIGNED DEFAULT NULL');
$add('staff_notice_post_id', 'INT UNSIGNED DEFAULT NULL');
```

---

## 3. Zonas del Mundo

### 3.1 Definición de Zonas

```php
// navigation_config.php:24-35
function game_nav_sea_zone_labels(): array
{
    return [
        'east_blue' => 'East Blue',
        'west_blue' => 'West Blue',
        'north_blue' => 'North Blue',
        'south_blue' => 'South Blue',
        'grand_line' => 'Grand Line',
        'new_world' => 'New World',
        'calm_belt' => 'Calm Belt',
        'florian_triangle' => 'Triángulo de Florian',
    ];
}
```

### 3.2 Tabla de Zonas

| Zona | Nivel | Peligro Base | Descripción Narrativa | Clima Predominante |
|------|-------|-------------|----------------------|-------------------|
| `east_blue` | 1–2 | 1–2 | El mar más seguro de los cuatro Blues. Aguas tranquilas, clima predecible, rutas comerciales establecidas. Ideal para personajes de nivel bajo. | Brisas alisias, tormentas menores, clima tropical templado. |
| `west_blue` | 1–2 | 1–2 | Similar al East Blue. Aguas conocidas, navegación tradicional con brújula. Cultura y gobierno local estable. | Similar al East Blue. Temporada de lluvias definida. |
| `north_blue` | 2–3 | 2–3 | Aguas más complejas que los Blues menores. Corrientes cambiantes, clima más frío, algunas zonas volcánicas. Mayor presencia de la Marina. | Frío moderado, tormentas de nieve en invierno, corrientes traicioneras. |
| `south_blue` | 2–3 | 2–3 | Similar al North. Aguas con mayor biodiversidad marina. Tormentas tropicales frecuentes. | Tropical extremo, huracanes estacionales, mareas impredecibles. |
| `grand_line` | 3 | 3 | El mar legendario. El clima es impredecible: puede nevar en verano y hacer calor en invierno. El Log Pose es obligatorio. Las aguas están llenas de bestias y fenómenos extraños. | Caótico. Nieve en verano, lluvia de meteoritos, tornados súbitos. Sin patrón climático predecible. |
| `new_world` | 4–5 | 4–5 | La segunda mitad de la Grand Line. El clima no es solo impredecible, es hostil y activo. Islas de fuego flotantes, tormentas permanentes, mares que hierven. Solo los más fuertes sobreviven. | Extremo. Mar de lava, lluvia de fuego, tornados de hielo, ballenas de tormenta. El clima es un enemigo en sí mismo. |

### 3.3 Cómo la Zona Afecta el Viaje

La zona del viaje se determina en `navigation_process.php`:

```php
// navigation_process.php:87
$seaZone = $danger >= 3 ? ($islandTo['sea_zone'] ?? 'grand_line') : ($islandFrom['sea_zone'] ?? 'east_blue');
```

**Regla:** Si el peligro es >= 3 (Grand Line o superior), se usa la zona de la isla de destino. Si es menor, se usa la zona de origen. Esto refleja que los viajes peligrosos cruzan a mares más hostiles, mientras que los viajes seguros permanecen en aguas conocidas.

La zona luego se usa para:

1. **Determinar el oráculo de clima:** `nav_1_2` para Blues (peligro 1–2), `nav_3` para Grand Line (peligro 3), `nav_4_5` para New World (peligro 4–5).
2. **Calcular la velocidad:** Los barcos pueden tener bonus de velocidad específicos por zona (`nav_bonus_east_blue`, `nav_bonus_grand_line`, etc.).
3. **Establecer la severidad de eventos:** Un evento "Moderado" en East Blue es una lluvia ligera; en New World es niebla desorientadora que deja al barco a la deriva.

### 3.4 Zonas Especiales

| Zona | Descripción |
|------|-------------|
| `calm_belt` | Zona de calma total. Sin viento. Hogar de los Reyes del Mar. Peligro extremo para quien se aventure sin medios especiales. |
| `florian_triangle` | El Triángulo de Florian. Niebla perpetua, barcos fantasma, visibilidad cero. Peligro 5 por desorientación y terror psicológico. |

---

## 4. Oráculos de Navegación y Clima

### 4.1 Arquitectura de Oráculos

Los oráculos de navegación son un caso especial del sistema general de `game_oracles`. Se definen con `subtype` específico (`nav_1`, `nav_1_2`, `nav_2`, `nav_3`, `nav_4`, `nav_4_5`, `nav_5`) y se etiquetan con `"navegacion"` en `tags_json`.

**Función de selección de oráculos:**

```php
// navigation_helpers.php:170-206
function game_nav_get_oracles_for_danger(int $danger): array
{
    global $db;
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT * FROM {$prefix}game_oracles
        WHERE (tags_json LIKE '%navegacion%' AND (subtype LIKE 'nav_%' OR subtype = 'navegacion'))
        ORDER BY id ASC");

    $all = [];
    while ($row = $db->fetch_array($q)) {
        $all[] = $row;
    }

    $matching = [];
    foreach ($all as $oracle) {
        $subtype = (string)($oracle['subtype'] ?? '');
        if ($subtype === 'navegacion') {
            $matching[] = $oracle;
            continue;
        }
        if (!str_starts_with($subtype, 'nav_')) {
            continue;
        }
        $parts = explode('_', str_replace('nav_', '', $subtype));
        foreach ($parts as $st) {
            if (is_numeric($st) && (int)$st <= $danger) {
                $matching[] = $oracle;
                break;
            }
        }
    }

    return !empty($matching) ? $matching : $all;
}
```

**Lógica de matching:** Un oráculo con `subtype = 'nav_3'` coincide con peligro >= 3. Un oráculo con `subtype = 'nav_1_2'` coincide con peligro 1 o 2. El sistema divide el subtype por `_` y compara cada parte numérica contra el nivel de peligro.

### 4.2 Catálogo de Oráculos de Navegación

#### 4.2.1 `nav_1_2` — Blues (peligro 1–2)

```
Nombre: Evento de Navegación — Mar Tranquilo
Dado: d20
Resultados:
   1-5:  Viento a favor / Mar calmado (Favorable)
   6-10: Lluvia moderada / Neblina (Moderado)
  11-15: Tormenta menor / Mar picado (Severo)
  16-19: Mar encalmado total (Extremo)
  20:    Corriente desfavorable fuerte (Singular)
```

#### 4.2.2 `nav_3` — Grand Line (peligro 3)

```
Nombre: Evento de Navegación — Grand Line
Dado: d20
Resultados:
   1-5:  Corriente inversa favorable (Favorable)
   6-10: Nieve en verano / Lluvia cálida (Moderado)
  11-15: Rayos sin nubes / Calor extremo (Severo)
  16-19: Tornado súbito / Mar de nubes (Extremo)
  20:    Lluvia de meteoritos / Erupción submarina (Singular)
```

#### 4.2.3 `nav_4_5` — New World (peligro 4–5)

```
Nombre: Evento de Navegación — New World
Dado: d20
Resultados:
   1-5:  Ojo del huracán (Favorable)
   6-10: Niebla desorientadora / Lluvia constante (Moderado)
  11-15: Mar de lava / Lluvia de fuego (Severo)
  16-19: Tornado de hielo / Tormenta eléctrica rastreadora (Extremo)
  20:    Isla de fuego flotante / Ballena de tormenta / Vórtice gigante (Singular)
```

### 4.3 Oráculos Expandidos (nav_1, nav_2, nav_4, nav_5)

La migración `migrate_navigation_oracles_expand.php` añade oráculos más específicos por nivel de peligro:

#### `nav_1` — East Blue (peligro 1, d12)

```
1-3:   Mar en calma
4-6:   Gaviotas de ruta
7-9:   Corriente suave
10-11: Pesca casual
12:    Viento cambiante
```

#### `nav_2` — Incidente en ruta (peligro 2, d20)

```
1-4:   Lluvia persistente
5-8:   Arrecife oculto
9-12:  Barco pesquero
13-15: Viento en contra
16-17: Humo en el horizonte
18-19: Emboscada leve → auto_invoke: nav_resolve_naval
20:    Sombra bajo el agua → auto_invoke: nav_resolve_beast
```

#### `nav_4` — Corsarios y patrullas (peligro 4, d20)

```
1-4:   Señal de humo
5-8:   Patrulla lejana
9-12:  Mina flotante
13-15: Flota corsaria → auto_invoke: nav_resolve_naval
16-18: Caza marina
19-20: Kraken menor → auto_invoke: nav_resolve_beast
```

#### `nav_5` — Abismo extremo (peligro 5, d12)

```
1-2:   Anomalía temporal
3-4:   Lluvia de meteoritos
5-6:   Muro de tormenta
7-8:   Territorio Yonko
9-10:  Kraken adulto → auto_invoke: nav_resolve_beast
11-12: Colisión inevitable → auto_invoke: nav_resolve_naval
```

### 4.4 Oráculos de Resolución (Auto-Invoke)

Los oráculos de navegación pueden encadenar auto-invocaciones. Cuando el resultado de un oráculo contiene `auto_invoke`, el sistema automáticamente ejecuta otro oráculo:

```php
// navigation_process.php:169-192
function game_navigation_maybe_invoke_chain(
    int $postId,
    int $characterId,
    array $rollResult,
    string $category,
    int $parentPostOracleId
): void {
    global $db;

    $autoInvoke = $rollResult['auto_invoke'] ?? null;
    if (!$autoInvoke || empty($autoInvoke['oracle_id'])) {
        return;
    }

    $invokeId = (int)$autoInvoke['oracle_id'];
    $prefix = TABLE_PREFIX;
    $autoQ = $db->query("SELECT * FROM {$prefix}game_oracles WHERE id = {$invokeId} LIMIT 1");
    if (!$autoRow = $db->fetch_array($autoQ)) {
        return;
    }

    $autoResult = game_roll_oracle($autoRow, $category);
    game_navigation_insert_post_oracle($postId, $characterId, $autoRow, $autoResult, 1, $parentPostOracleId);
}
```

**Oráculos de resolución disponibles:**

#### `nav_resolve_naval` — Encuentro naval (d6)

```
1-2: Huida limpia
3-4: Intercambio tenso
5:   Escaramuza menor
6:   Abordaje
```

#### `nav_resolve_beast` — Criatura marina (d6)

```
1-2: Solo avistamiento
3-4: Golpe al casco
5-6: Ataque directo
```

### 4.5 Cómo los Oráculos se Integran en el Viaje

```
Viaje creado
    │
    ▼
game_navigation_generate_events()
    │
    ├── Determinar número de eventos (basado en peligro + duración)
    │
    └── Para cada evento:
         ├── Seleccionar oráculo aleatorio de game_nav_get_oracles_for_danger()
         ├── game_roll_oracle() → resultado con rango y descripción
         ├── Mitigación por navegante (si aplica)
         ├── Registrar en game_post_oracles
         ├── Registrar en game_navigation_events
         └── game_navigation_maybe_invoke_chain() si auto_invoke presente
```

### 4.6 Eventos Climáticos por Zona (Ampliación)

| Zona | Nivel de Peligro | Tipo de Clima |
|------|-----------------|---------------|
| **Blues** | 1–2 | **Predecible y natural.** Lluvia moderada, viento favorable, tormentas menores. |
| **Grand Line** | 3 | **Impredecible y caótico.** Nieve en pleno verano, lluvia de meteoritos pequeños, tornados súbitos. |
| **New World** | 4–5 | **Activo y hostil.** Islas de fuego flotantes, lluvia de lava, tormentas permanentes, ballenas de tormenta. |

---

## 5. Procesamiento de Navegación

### 5.1 Entry Point: `game_navigation_process_post()`

Este es el corazón del sistema. Se llama desde el pipeline de posteo (probablemente desde `game_postcharacter` plugin de MyBB) cuando un usuario envía un post con navegación habilitada.

```php
// navigation_process.php:11-139
function game_navigation_process_post(int $postId, int $threadId, int $characterId, array $input): ?int
{
    global $db;

    // Paso 1: ¿Está habilitada la navegación?
    if (empty($input['rpg_nav_enabled']) || (string)$input['rpg_nav_enabled'] !== '1') {
        return null;
    }

    // Paso 2: Solo en hilos de tipo "Presente"
    $prefix = TABLE_PREFIX;
    $metaQ = $db->query("SELECT thread_type FROM {$prefix}game_thread_meta WHERE thread_id = " . (int)$threadId . " LIMIT 1");
    if ($metaRow = $db->fetch_array($metaQ)) {
        if ($metaRow['thread_type'] !== 'Presente') {
            return null;
        }
    }

    // Paso 3: Extraer y validar campos de navegación
    $islandToFid = (int)($input['rpg_nav_destination'] ?? $input['nav_destination_island_id'] ?? 0);
    $shipCardId = (int)($input['rpg_nav_ship'] ?? $input['nav_ship_card_id'] ?? 0);
    $instrument = preg_replace('/[^a-z_]/', '', (string)($input['rpg_nav_instrument'] ?? $input['nav_instrument'] ?? 'none'));

    // Paso 4: Validar que el instrumento pertenece al personaje
    if ($instrument !== 'none' && function_exists('game_nav_instruments_for_character')) {
        $allowed = array_column(game_nav_instruments_for_character($characterId), 'instrument_key');
        if (!in_array($instrument, $allowed, true)) {
            $instrument = 'none';
        }
    }

    // Paso 5: Validaciones básicas
    if ($islandToFid <= 0 || $shipCardId <= 0) {
        return null;
    }

    // Paso 6: Obtener isla de origen desde el foro del post
    $postRow = $db->fetch_array($db->query("SELECT fid FROM {$prefix}posts WHERE pid = " . (int)$postId . " LIMIT 1"));
    $islandFrom = game_nav_get_island_from_forum((int)$postRow['fid']);
    if (!$islandFrom) {
        return null;
    }

    // Paso 7: Validar que origen ≠ destino
    $fromFid = (int)$islandFrom['fid'];
    if ($fromFid === $islandToFid) {
        return null;
    }

    // Paso 8: Validar que el destino existe como isla
    $islandTo = $db->fetch_array($db->query("SELECT * FROM {$prefix}game_forum_islands WHERE fid = " . (int)$islandToFid . " LIMIT 1"));
    if (!$islandTo) {
        return null;
    }

    // Paso 9: Validar que el barco existe y está equipado
    $shipCard = $db->fetch_array($db->query("SELECT * FROM {$prefix}game_cards WHERE id = " . (int)$shipCardId . " AND card_type = 'barco' LIMIT 1"));
    $equipped = $db->query("SELECT 1 FROM {$prefix}game_character_inventory
        WHERE character_id = " . (int)$characterId . " AND card_id = " . (int)$shipCardId . " AND slot_type = 'barco' LIMIT 1");
    if (!$shipCard || !$db->num_rows($equipped)) {
        return null;
    }

    // Paso 10: Cálculo completo del viaje
    $shipEffects = json_decode($shipCard['effects_json'] ?? '{}', true);
    $navigatorRank = game_oficio_get_rank($characterId, 'navegante');

    $route = game_nav_calculate_distance($fromFid, $islandToFid);
    $distance = (int)$route['distance'];
    $danger = game_nav_calculate_danger($islandFrom, $islandTo, $route['waypoints'], $route['danger_override']);
    $seaZone = $danger >= 3 ? ($islandTo['sea_zone'] ?? 'grand_line') : ($islandFrom['sea_zone'] ?? 'east_blue');
    $effSpeed = game_nav_effective_speed($shipEffects, (string)$seaZone, $navigatorRank, $instrument);
    $duration = game_nav_calculate_duration($distance, $effSpeed);
    $numEvents = game_nav_calculate_events($danger, $duration, true);

    // Paso 11: Separar bonus del instrumento (auditoría)
    $baseSpeed = (float)($shipEffects['velocidad_base'] ?? $shipEffects['velocidad'] ?? 5);
    $instrumentBonus = (int)round($effSpeed - $baseSpeed - game_oficio_rank_bonus($navigatorRank));

    // Paso 12: Snapshot para auditoría
    $raw = json_encode([
        'island_from' => $islandFrom,
        'island_to' => $islandTo,
        'route' => $route,
        'danger' => $danger,
        'ship_effects' => $shipEffects,
        'sea_zone' => $seaZone,
        'effective_speed' => $effSpeed,
        'navigator_rank' => $navigatorRank,
    ], JSON_UNESCAPED_UNICODE);

    // Paso 13: Calcular días de rol
    $startRolDays = game_rol_days_at();
    $expectedEndRolDays = $startRolDays + max(1, $duration);

    // Paso 14: Insertar voyage
    $insert = [
        'post_id' => $postId,
        'thread_id' => $threadId,
        'character_id' => $characterId,
        'ship_card_id' => $shipCardId,
        'island_from_fid' => $fromFid,
        'island_to_fid' => $islandToFid,
        'distance' => $distance,
        'danger_level' => $danger,
        'duration_days' => $duration,
        'num_events' => $numEvents,
        'navigator_bonus' => $navigatorRank,
        'instrument_used' => $instrument,
        'instrument_bonus' => $instrumentBonus,
        'raw_calculation_json' => $raw,
        'status' => 'active',
    ];
    if ($db->field_exists('staff_review', 'game_navigation_voyages')) {
        $insert['staff_review'] = 'pending';
        $insert['start_rol_days'] = $startRolDays;
        $insert['expected_end_rol_days'] = $expectedEndRolDays;
    }
    $db->insert_query('game_navigation_voyages', $insert);

    // Paso 15: Generar eventos si aplica
    $voyageId = (int)$db->insert_id();
    if ($voyageId > 0 && $numEvents > 0) {
        game_navigation_generate_events($voyageId, $postId, $characterId, $numEvents, $danger, $navigatorRank);
    }

    return $voyageId > 0 ? $voyageId : null;
}
```

### 5.2 Validaciones en Cadena

El flujo de validación es estricto y silencioso (return null sin errores visibles al usuario en muchos casos):

| Paso | Validación | Consecuencia |
|------|-----------|-------------|
| 1 | `rpg_nav_enabled !== '1'` | No se crea viaje (retorno null) |
| 2 | Thread type !== 'Presente' | No se crea viaje |
| 3 | Campos malformados | Se usan defaults (0, 'none') |
| 4 | Instrumento no pertenece al PJ | Degrada a 'none' |
| 5 | `islandToFid <= 0` o `shipCardId <= 0` | No se crea viaje |
| 6 | Post no tiene isla de origen | No se crea viaje |
| 7 | Origen === Destino | No se crea viaje |
| 8 | Destino no existe en `game_forum_islands` | No se crea viaje |
| 9 | Barco no existe o no está equipado | No se crea viaje |

### 5.3 Generación de Eventos

```php
// navigation_process.php:194-303
function game_navigation_generate_events(
    int $voyageId,
    int $postId,
    int $characterId,
    int $numEvents,
    int $danger,
    int $navigatorRank = 0
): void {
    global $db;

    $category = game_get_post_category($postId);
    $available = game_nav_get_oracles_for_danger($danger);
    if (empty($available)) {
        return;
    }

    $avoidedSevero = false;

    for ($i = 1; $i <= $numEvents; $i++) {
        $oracle = $available[array_rand($available)];
        $rollResult = game_roll_oracle($oracle, $category);

        // Grado 3+: Tirar dos veces, quedarse con el mejor
        if ($navigatorRank >= 3) {
            $rollResult2 = game_roll_oracle($oracle, $category);
            if ($rollResult2['roll'] < $rollResult['roll']) {
                $rollResult = $rollResult2;
            }
        }

        // Mitigación mecánica según grado de navegante
        $rollVal = (int)$rollResult['roll'];
        $newRollVal = $rollVal;
        $mitigationNote = '';

        // ... (lógica de mitigación por grado)

        if ($newRollVal !== $rollVal) {
            // Re-buscar resultado para el nuevo roll value
            $mitigatedResult = game_find_oracle_result($resultsData, $newRollVal);
            if ($mitigatedResult) {
                $rollResult['range'] = $mitigatedResult['range'];
                $rollResult['result'] = $mitigatedResult['result'];
                $rollResult['description'] = $mitigatedResult['description'] . $mitigationNote;
                $rollResult['roll'] = $newRollVal;
            }
        }

        $postOracleId = game_navigation_insert_post_oracle(...);
        if ($postOracleId > 0) {
            $db->insert_query('game_navigation_events', [
                'voyage_id' => $voyageId,
                'post_oracle_id' => $postOracleId,
                'event_order' => $i,
                'danger_tier' => $danger,
            ]);
            game_navigation_maybe_invoke_chain($postId, $characterId, $rollResult, $category, $postOracleId);
        }
    }
}
```

### 5.4 Campos de Input Soportados

El sistema acepta dos convenciones de nombres para campos de formulario (backward compatibility):

| Campo nuevo | Campo legacy | Propósito |
|-------------|-------------|-----------|
| `rpg_nav_enabled` | — | Checkbox: habilitar navegación |
| `rpg_nav_destination` | `nav_destination_island_id` | ID de la isla destino |
| `rpg_nav_ship` | `nav_ship_card_id` | ID de la card del barco |
| `rpg_nav_instrument` | `nav_instrument` | Instrumento a usar |

---

## 6. Helpers de Navegación

### 6.1 `game_nav_get_island_from_forum(int $fid): ?array`

Resuelve recursivamente el foro a isla. Si el foro no está en `game_forum_islands`, sube al padre (`pid`).

```php
// navigation_helpers.php:10-29
function game_nav_get_island_from_forum(int $fid): ?array
{
    global $db;
    if ($fid <= 0 || !$db->table_exists('game_forum_islands')) {
        return null;
    }
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT i.*, f.name AS forum_name FROM {$prefix}game_forum_islands i
        JOIN {$prefix}forums f ON f.fid = i.fid
        WHERE i.fid = " . (int)$fid . " LIMIT 1");
    if ($row = $db->fetch_array($q)) {
        return $row;
    }

    $forum = get_forum($fid);
    if ($forum && (int)($forum['pid'] ?? 0) > 0) {
        return game_nav_get_island_from_forum((int)$forum['pid']);
    }
    return null;
}
```

### 6.2 `game_nav_calculate_distance(int $islandFromFid, int $islandToFid): array`

Busca primero en `game_navigation_routes`. Si no encuentra ruta, calcula distancia Euclidiana por coordenadas.

```php
// navigation_helpers.php:32-71
function game_nav_calculate_distance(int $islandFromFid, int $islandToFid): array
{
    global $db;
    $prefix = TABLE_PREFIX;

    // Buscar ruta precalculada (en ambos sentidos)
    if ($db->table_exists('game_navigation_routes')) {
        $q = $db->query("SELECT * FROM {$prefix}game_navigation_routes
            WHERE (island_from_fid = {$from} AND island_to_fid = {$to})
               OR (island_from_fid = {$to} AND island_to_fid = {$from})
            LIMIT 1");
        if ($route = $db->fetch_array($q)) {
            // Retornar ruta precalculada
        }
    }

    // Fallback: cálculo Euclidiano
    $dx = (int)$fromRow['coord_x'] - (int)$toRow['coord_x'];
    $dy = (int)$fromRow['coord_y'] - (int)$toRow['coord_y'];
    $dist = (int)round(sqrt($dx * $dx + $dy * $dy));

    return ['distance' => max(1, $dist), ...];
}
```

**Retorno:**
```json
{
  "distance": 28,
  "waypoints": [45, 51],
  "is_precalculated": true,
  "danger_override": null
}
```

### 6.3 `game_nav_calculate_danger(array $islandFrom, array $islandTo, array $waypointFids, ?int $dangerOverride): int`

Calcula el nivel de peligro interpolando entre el peligro de las islas origen, destino y waypoints.

```php
// navigation_helpers.php:73-96
function game_nav_calculate_danger(array $islandFrom, array $islandTo, array $waypointFids, ?int $dangerOverride): int
{
    if ($dangerOverride !== null) {
        return max(1, min(5, $dangerOverride));
    }

    $dangers = [(int)($islandFrom['base_danger'] ?? 1), (int)($islandTo['base_danger'] ?? 1)];

    if (!empty($waypointFids)) {
        // Añadir peligro de waypoints
        $ids = implode(',', array_map('intval', $waypointFids));
        $q = $db->query("SELECT base_danger FROM {$prefix}game_forum_islands WHERE fid IN ({$ids})");
        while ($wp = $db->fetch_array($q)) {
            $dangers[] = (int)$wp['base_danger'];
        }
    }

    $max = max($dangers);
    $avg = array_sum($dangers) / count($dangers);
    $interpolated = ($max * 0.4) + ($avg * 0.6);

    return max(1, min(5, (int)round($interpolated)));
}
```

**Fórmula:** `danger = (max * 0.4) + (avg * 0.6)`, clamped a [1, 5].
- El peligro máximo tiene 40% de peso.
- El promedio tiene 60% de peso.
- Esto evita que una sola isla de peligro 5 dispare toda la ruta, pero sí la afecta significativamente.

### 6.4 `game_nav_effective_speed(array $shipEffects, string $seaZone, int $navigatorRank, string $instrument): float`

Calcula la velocidad efectiva del barco para el viaje.

```php
// navigation_helpers.php:98-117
function game_nav_effective_speed(array $shipEffects, string $seaZone, int $navigatorRank, string $instrument): float
{
    $base = (float)($shipEffects['velocidad_base'] ?? $shipEffects['velocidad'] ?? 5);
    if ($base <= 0) {
        $base = 5.0;
    }

    $zoneKey = 'nav_bonus_' . preg_replace('/[^a-z_]/', '', $seaZone);
    $zoneMod = (float)($shipEffects[$zoneKey] ?? 0);
    $navMod = game_oficio_rank_bonus($navigatorRank);

    $instrumentBonus = match ($instrument) {
        'compass' => 0.0,
        'log_pose' => 0.5,
        'eternal_pose' => 1.0,
        default => -GAME_NAV_NO_INSTRUMENT_SPEED_PENALTY,
    };

    return max(1.0, $base + $zoneMod + $navMod + $instrumentBonus);
}
```

**Fórmula de velocidad efectiva:**
```
velocidad_efectiva = MAX(1.0, velocidad_base + bonus_zona + bonus_navegante + bonus_instrumento)
```

| Componente | Rango típico | Fuente |
|-----------|-------------|--------|
| `velocidad_base` | 1–15 | Efectos de la card del barco (`velocidad_base` o `velocidad`) |
| `bonus_zona` | 0–5 | `nav_bonus_{zona}` en efectos del barco |
| `bonus_navegante` | 0.0–2.5 | Grado del oficio navegante (I→0.5, V→2.5) |
| `bonus_instrumento` | -1.0 a 1.0 | Brújula 0, Log Pose +0.5, Eternal Pose +1.0, sin instr: -1.0 |

### 6.5 `game_nav_calculate_duration(int $distance, float $effectiveSpeed): int`

```php
// navigation_helpers.php:119-123
function game_nav_calculate_duration(int $distance, float $effectiveSpeed): int
{
    $factor = defined('GAME_NAV_SPEED_FACTOR') ? GAME_NAV_SPEED_FACTOR : 10;
    return max(1, (int)ceil($distance / ($effectiveSpeed * $factor)));
}
```

**Fórmula:** `días = CEILING(distancia / (velocidad_efectiva * FACTOR))`

Donde `GAME_NAV_SPEED_FACTOR = 10` (configurable en `navigation_config.php`).

Ejemplo: distancia 28, velocidad 6.5 → `CEIL(28 / (6.5 * 10)) = CEIL(28 / 65) = 1` día.

### 6.6 `game_nav_calculate_events(int $danger, int $duration, bool $withRandom = true): int`

```php
// navigation_helpers.php:125-148
function game_nav_calculate_events(int $danger, int $duration, bool $withRandom = true): int
{
    $base = match ($danger) {
        1 => 0,
        2 => 1,
        3 => 2,
        4 => 3,
        5 => 4,
        default => 0,
    };

    if ($duration >= 5) {
        $base++;
    }
    if ($duration >= 10) {
        $base++;
    }

    if ($withRandom) {
        $base += mt_rand(0, 2);
    }

    return max(GAME_NAV_EVENTS_MIN, min(GAME_NAV_EVENTS_MAX, $base));
}
```

| Peligro | Eventos base | + duración ≥5 | + duración ≥10 | + aleatorio | Total máx |
|---------|:-----------:|:-------------:|:--------------:|:-----------:|:---------:|
| 1 | 0 | +1 | +1 | +0–2 | 4 |
| 2 | 1 | +1 | +1 | +0–2 | 5 |
| 3 | 2 | +1 | +1 | +0–2 | 6 |
| 4 | 3 | +1 | +1 | +0–2 | 7 |
| 5 | 4 | +1 | +1 | +0–2 | 8 (MAX) |

### 6.7 `game_nav_compute_voyage(...)`: Cómputo Completo (Usado por AJAX preview)

Esta función consolida todos los cálculos anteriores en una sola llamada. Es utilizada por `navigation_preview.php` para mostrar al jugador el resumen del viaje antes de confirmar.

```php
// navigation_helpers.php:359-394
function game_nav_compute_voyage(
    int $fromFid,
    int $toFid,
    array $shipEffects,
    int $navigatorRank,
    string $instrument
): array {
    global $db;

    $islandFrom = $db->fetch_array($db->query("SELECT * FROM {$prefix}game_forum_islands WHERE fid = " . (int)$fromFid . " LIMIT 1"));
    $islandTo = $db->fetch_array($db->query("SELECT * FROM {$prefix}game_forum_islands WHERE fid = " . (int)$toFid . " LIMIT 1"));
    if (!$islandFrom || !$islandTo) {
        return ['ok' => false, 'error' => 'Isla no encontrada'];
    }

    $route = game_nav_calculate_distance($fromFid, $toFid);
    $danger = game_nav_calculate_danger($islandFrom, $islandTo, $route['waypoints'], $route['danger_override']);
    $seaZone = $danger >= 3 ? ($islandTo['sea_zone'] ?? 'grand_line') : ($islandFrom['sea_zone'] ?? 'east_blue');
    $effSpeed = game_nav_effective_speed($shipEffects, (string)$seaZone, $navigatorRank, $instrument);
    $duration = game_nav_calculate_duration((int)$route['distance'], $effSpeed);
    $eventsRange = game_nav_events_range($danger, $duration);

    return [
        'ok' => true,
        'distance' => (int)$route['distance'],
        'danger_level' => $danger,
        'danger_label' => game_nav_danger_label($danger),
        'effective_speed' => round($effSpeed, 2),
        'duration_days' => $duration,
        'events_min' => $eventsRange['min'],
        'events_max' => $eventsRange['max'],
        'sea_zone' => $seaZone,
        'route' => $route,
    ];
}
```

### 6.8 Funciones Auxiliares

#### Etiquetas de Peligro

```php
// navigation_helpers.php:157-167
function game_nav_danger_label(int $level): string
{
    return match ($level) {
        1 => 'Tranquilo',
        2 => 'Moderado',
        3 => 'Peligroso',
        4 => 'Muy peligroso',
        5 => 'EXTREMO',
        default => '—',
    };
}
```

#### Listado de Islas

```php
// navigation_helpers.php:209-236
function game_nav_list_islands(int $excludeFid = 0): array
{
    // JOIN con mybb_forums para obtener nombre
    // Retorna: fid, name, sea_zone, base_danger, requires_log_pose, requires_compass, coord_x, coord_y, image_url
}
```

#### Barcos del Personaje

```php
// navigation_helpers.php:239-270
function game_nav_ships_for_character(int $characterId): array
{
    // JOIN game_character_inventory + game_cards
    // WHERE slot_type = 'barco' AND card_type = 'barco'
    // Retorna: card_id, name, image_url, velocidad, effects
}
```

#### Instrumentos del Personaje

```php
// navigation_helpers.php:319-357
function game_nav_instruments_for_character(int $characterId): array
{
    // Busca en inventario (slot_type = 'equipo')
    // Detecta instrumento por game_nav_detect_instrument_from_card()
    // Retorna: card_id, instrument_key, name, image_url, label, subtitle, icon
}
```

#### Meta de Instrumentos

```php
// navigation_helpers.php:278-287
function game_nav_instrument_meta(string $key): array
{
    $catalog = [
        'compass' => ['label' => 'Brújula', 'subtitle' => 'Blues', 'icon' => 'fa-compass'],
        'log_pose' => ['label' => 'Log Pose', 'subtitle' => 'Grand Line', 'icon' => 'fa-map-marked-alt'],
        'eternal_pose' => ['label' => 'Eternal Pose', 'subtitle' => 'Isla fija', 'icon' => 'fa-map-pin'],
    ];
    return $catalog[$key] ?? ['label' => $key, 'subtitle' => '', 'icon' => 'fa-location-arrow'];
}
```

### 6.9 Constantes del Sistema

Definidas en `navigation_config.php`:

| Constante | Valor | Propósito |
|-----------|-------|-----------|
| `GAME_NAV_SPEED_FACTOR` | 10 | Divisor para convertir distancia a días |
| `GAME_NAV_MAP_WIDTH` | 1000 | Ancho máximo del mapa de coordenadas |
| `GAME_NAV_MAP_HEIGHT` | 1000 | Alto máximo del mapa de coordenadas |
| `GAME_NAV_EVENTS_MIN` | 0 | Mínimo de eventos por viaje |
| `GAME_NAV_EVENTS_MAX` | 8 | Máximo de eventos por viaje |
| `GAME_NAV_NO_INSTRUMENT_SPEED_PENALTY` | 1.0 | Penalización de velocidad si no hay instrumento |

---

## 7. Helpers de Revisión

### 7.1 `game_navigation_review_voyage(int $voyageId, int $staffUid, string $staffUsername, string $decision): array`

Procesa la revisión de un viaje por parte del staff.

```php
// navigation_review_helpers.php:58-121
function game_navigation_review_voyage(int $voyageId, int $staffUid, string $staffUsername, string $decision): array
{
    global $db;

    // Validar decisión
    $decision = strtolower(trim($decision));
    if (!in_array($decision, ['approve', 'deny'], true)) {
        return ['ok' => false, 'message' => 'Decisión inválida.'];
    }

    // Verificar que el viaje existe y está pendiente
    $voyage = $db->fetch_array($db->query("SELECT * FROM {$prefix}game_navigation_voyages WHERE id = " . (int)$voyageId . " LIMIT 1"));
    if (!$voyage) {
        return ['ok' => false, 'message' => 'Viaje no encontrado.'];
    }

    $currentReview = (string)($voyage['staff_review'] ?? 'pending');
    if ($currentReview !== 'pending') {
        return ['ok' => false, 'message' => 'Este viaje ya fue revisado.'];
    }

    // Construir mensaje automático
    if ($decision === 'approve') {
        $staffReview = 'approved';
        $status = 'arrived';
        $body = '[b][Navegación — Staff][/b] La travesía de [b]' . $fromName . '[/b] a [b]' . $toName . '[/b] se ha completado con éxito.';
    } else {
        $staffReview = 'denied';
        $status = 'cancelled';
        $body = '[b][Navegación — Staff][/b] La travesía de [b]' . $fromName . '[/b] a [b]' . $toName . '[/b] no pudo completarse.';
    }

    // Publicar respuesta automática en el hilo
    $postId = game_navigation_post_thread_reply((int)$voyage['thread_id'], $staffUid, $staffUsername, $body);

    // Actualizar viaje
    $db->write_query("UPDATE {$prefix}game_navigation_voyages SET
        staff_review = '{$escReview}',
        status = '{$escStatus}',
        reviewed_at = {$now},
        reviewed_by_uid = " . (int)$staffUid . ",
        staff_notice_post_id = {$postId}
        WHERE id = " . (int)$voyageId);

    return ['ok' => true, 'post_id' => $postId];
}
```

### 7.2 `game_navigation_post_thread_reply(int $threadId, int $userId, string $username, string $message): ?int`

Publica un mensaje automático en el hilo del viaje usando el PostDataHandler de MyBB.

```php
// navigation_review_helpers.php:7-53
function game_navigation_post_thread_reply(int $threadId, int $userId, string $username, string $message): ?int
{
    global $db;

    $thread = $db->fetch_array($db->query("SELECT tid, fid, subject FROM {$prefix}threads WHERE tid = " . (int)$threadId . " LIMIT 1"));
    if (!$thread) {
        return null;
    }

    require_once MYBB_ROOT . 'inc/datahandlers/post.php';

    // Prefijar RE: si no existe
    $subject = (string)$thread['subject'];
    if (stripos($subject, 'RE:') !== 0) {
        $subject = 'RE: ' . $subject;
    }

    $posthandler = new PostDataHandler('insert');
    $post = [
        'tid' => $threadId,
        'fid' => (int)$thread['fid'],
        'subject' => $subject,
        'uid' => $userId,
        'username' => $username,
        'message' => $message,
        'ipaddress' => my_inet_pton(get_ip()),
        'options' => [
            'signature' => 0,
            'emailnotify' => 0,
            'disablesmilies' => 0,
        ],
    ];
    $posthandler->set_data($post);
    if (!$posthandler->validate_post()) {
        return null;
    }
    $info = $posthandler->insert_post();

    return isset($info['pid']) ? (int)$info['pid'] : null;
}
```

### 7.3 `game_navigation_voyage_enrich_row(array $row): array`

 Enriquece una fila de viaje con datos calculados para mostrar en listados.

```php
// navigation_review_helpers.php:124-146
function game_navigation_voyage_enrich_row(array $row): array
{
    global $mybb;

    $startRol = (int)($row['start_rol_days'] ?? 0);
    $endRol = (int)($row['expected_end_rol_days'] ?? 0);
    if ($endRol <= 0 && $startRol > 0) {
        $endRol = $startRol + (int)($row['duration_days'] ?? 0);
    }

    $row['expected_end_rol_label'] = $endRol > 0 ? game_rol_date_label($endRol) : '';
    $row['start_rol_label'] = $startRol > 0 ? game_rol_date_label($startRol) : '';
    $row['staff_review'] = (string)($row['staff_review'] ?? 'pending');

    // Construir URL del post
    $bb = rtrim((string)($mybb->settings['bburl'] ?? ''), '/');
    $tid = (int)($row['thread_id'] ?? 0);
    $pid = (int)($row['post_id'] ?? 0);
    $row['post_url'] = ($bb && $tid > 0 && $pid > 0)
        ? "{$bb}/showthread.php?tid={$tid}&pid={$pid}#pid{$pid}"
        : '';

    return $row;
}
```

---

## 8. Oficio de Navegante

### 8.1 El Oficio en el Sistema

El oficio `navegante` (slug: `navegante`) es el único oficio con un efecto mecánico directo y cuantificable en el sistema. Su grado afecta directamente la velocidad del barco y la mitigación de eventos.

### 8.2 Bonus de Velocidad por Grado

```php
// grado_helpers.php:14
function game_grado_bonus(int $rank): float
{
    if ($rank <= 0) {
        return 0.0;
    }
    return (float)max(1, min(5, $rank)) * 0.5;
}

// oficios_helpers.php:15
function game_oficio_rank_bonus(int $rank): float
{
    return game_grado_bonus($rank);
}
```

| Grado | Valor | Bonus velocidad | Significado narrativo |
|:-----:|:-----:|:---------------:|----------------------|
| Sin | 0 | +0.0 | No sabe navegar |
| I | 1 | +0.5 | Novato: lee mapas básicos |
| II | 2 | +1.0 | Aprendiz: navega con confianza en Blues |
| III | 3 | +1.5 | Competente: fiable en Grand Line |
| IV | 4 | +2.0 | Experto: predice el clima |
| V | 5 | +2.5 | Maestro: navega cualquier mar |

### 8.3 Mitigación de Eventos

La mitigación ocurre en `game_navigation_generate_events()` y depende del grado del navegante:

#### Grado 2 (II): Mitigación básica

```
Roll 16-19 (Extremo) → Mitiga a Severo (roll 11)
Roll 11-15 (Severo)  → Mitiga a Moderado (roll 6)
Roll 6-10 (Moderado) → Mitiga a Favorable (roll 1)
```

#### Grado 3 (III): Doble tirada

El navegante tira dos veces y se queda con el resultado más bajo (mejor).

```php
// navigation_process.php:210-216
if ($navigatorRank >= 3) {
    $rollResult2 = game_roll_oracle($oracle, $category);
    if ($rollResult2['roll'] < $rollResult['roll']) {
        $rollResult = $rollResult2;
    }
}
```

#### Grado 4 (IV): Evasión táctica

```
Roll 11-15 (Severo) → Primera ocurrencia: Evita completamente → Favorable (roll 1)
                        Segunda ocurrencia: Mitiga a Moderado (roll 6)
Roll 16-19 (Extremo) → Mitiga a Severo (roll 11)
Roll 6-10 (Moderado) → Mitiga a Favorable (roll 1)
```

#### Grado 5 (V): Maestro navegante

```
Roll 16-19 (Extremo) → Mitiga a Moderado (roll 6)
Roll 11-15 (Severo)  → Primera: Evita → Favorable; Segunda: Mitiga a Moderado
Roll 6-10 (Moderado) → Inmunidad total → Favorable (roll 1)
```

### 8.4 Contexto de Navegación (Frontend)

El endpoint `navigation_context.php` expone el bonus del navegante al frontend:

```php
// navigation_context.php:33-46
GameAjax::json(true, [
    'island_fid' => $island ? (int)$island['fid'] : 0,
    'island_name' => $island ? ($island['forum_name'] ?? '') : '',
    'has_island' => $island !== null,
    'has_ship' => count($ships) > 0,
    'ships_count' => count($ships),
    'ships' => $ships,
    'instruments' => $instruments,
    'character_id' => $charId,
    'navegante_rank' => $naveganteRank,
    'navegante_label' => $naveganteRank > 0 ? game_oficio_rank_label($naveganteRank) : null,
    'navegante_bonus' => game_oficio_rank_bonus($naveganteRank),
    'can_navigate' => $island !== null && count($ships) > 0,
]);
```

### 8.5 Implicaciones de Diseño

- **No es obligatorio tener navegante** para navegar. Un personaje sin oficio puede navegar con instrumentos, pero será más lento y sufrirá más eventos.
- **El navegante Grado V reduce drásticamente** la duración de viajes largos y esencialmente ignora el clima moderado.
- **Incentivo mecánico real:** A diferencia de otros oficios puramente narrativos, el navegante tiene un impacto cuantificable en el juego.

---

## 9. Instrumentos de Navegación

### 9.1 Catálogo de Instrumentos

| Clave | Nombre | Bonus velocidad | Zona habilitada | Ícono FA |
|-------|--------|:--------------:|-----------------|----------|
| `compass` | Brújula | +0.0 | Blues | `fa-compass` |
| `log_pose` | Log Pose | +0.5 | Grand Line | `fa-map-marked-alt` |
| `eternal_pose` | Eternal Pose | +1.0 | Isla específica | `fa-map-pin` |
| `none` | Sin instrumento | -1.0 | — | `fa-location-arrow` |

### 9.2 Detección de Instrumentos

Los instrumentos se detectan automáticamente desde las cards del inventario:

```php
// navigation_helpers.php:289-316
function game_nav_detect_instrument_from_card(array $cardRow): ?string
{
    $effects = json_decode($cardRow['effects_json'] ?? '{}', true);
    if (is_array($effects) && !empty($effects['nav_instrument'])) {
        $key = preg_replace('/[^a-z_]/', '', strtolower((string)$effects['nav_instrument']));
        if (in_array($key, game_nav_instrument_keys(), true)) {
            return $key;
        }
    }

    // Fallback: buscar en tags y nombre
    $tags = json_decode($cardRow['tags_json'] ?? '[]', true);
    $haystack = mb_strtoupper(implode(' ', array_merge($tags, [(string)($cardRow['name'] ?? '')])));

    if (str_contains($haystack, 'ETERNAL POSE') || str_contains($haystack, 'ETERNAL_POSE')) {
        return 'eternal_pose';
    }
    if (str_contains($haystack, 'LOG POSE') || str_contains($haystack, 'LOG_POSE')) {
        return 'log_pose';
    }
    if (str_contains($haystack, 'BRÚJULA') || str_contains($haystack, 'BRUJULA') || str_contains($haystack, 'COMPASS')) {
        return 'compass';
    }

    return null;
}
```

**Dos modos de detección:**
1. **Explícito:** El campo `effects_json.nav_instrument` contiene la clave del instrumento.
2. **Por heurística:** El sistema busca palabras clave en `tags_json` y `name` de la card.

### 9.3 Cómo se Adquieren

Los instrumentos son cards de tipo `equipo` que se asignan al inventario del personaje (`game_character_inventory.slot_type = 'equipo'`). Se obtienen mediante:
- **Tienda de cards:** Comprando un Log Pose, Brújula o Eternal Pose.
- **Eventos narrativos:** El staff puede asignar un Eternal Pose como recompensa.
- **Creación de personaje:** Algunas ocupaciones iniciales pueden incluir brújula.

### 9.4 Efecto de No Tener Instrumento

Si el personaje navega sin instrumento (`instrument = 'none'`), recibe una penalización de `-GAME_NAV_NO_INSTRUMENT_SPEED_PENALTY` (valor por defecto: -1.0). La función `game_nav_effective_speed()` asegura que la velocidad nunca baje de 1.0:

```php
return max(1.0, $base + $zoneMod + $navMod + $instrumentBonus);
```

---

## 10. Ciclo de Vida de un Viaje

### 10.1 Diagrama de Estados

```
                    ┌──────────────┐
                    │  Post creado │
                    │  (sin nav)   │
                    └──────┬───────┘
                           │ rpg_nav_enabled = 1
                           ▼
                    ┌──────────────┐
                    │  NOT_STARTED │
                    │  (validación) │
                    └──────┬───────┘
                           │ validación OK
                           ▼
                    ┌──────────────┐
                    │  IN_PROGRESS │
                    │  (status:    │
                    │   'active')  │
                    │              │
                    │  Genera      │
                    │  eventos     │
                    └──────┬───────┘
                           │ tiempo de rol transcurre
                           ▼
                    ┌──────────────┐
                    │  ARRIVED     │
                    │  (esperando  │
                    │   staff      │
                    │   review)    │
                    └──────┬───────┘
                           │ staff decide
                    ┌──────┴──────┐
                    ▼             ▼
            ┌────────────┐ ┌────────────┐
            │  APPROVED  │ │  DENIED    │
            │  (arrived) │ │ (cancelled)│
            │  Viaje     │ │ Viaje      │
            │  completo  │ │ cancelado  │
            └────────────┘ └────────────┘
```

### 10.2 Paso a Paso

#### Fase 1: Iniciación

1. El jugador escribe un post en un hilo de tipo **Presente**.
2. Marca `rpg_nav_enabled = 1` en el formulario.
3. Selecciona destino, barco e instrumento.
4. Envía el post.
5. `game_navigation_process_post()` valida, calcula y crea el viaje (`status = 'active'`).
6. Si `num_events > 0`, se generan eventos de navegación (`game_navigation_generate_events()`).
7. Los eventos se registran en `game_navigation_events` + `game_post_oracles`.

#### Fase 2: En Progreso

1. El viaje está activo. `status = 'active', staff_review = 'pending'`.
2. El personaje se considera "en viaje" hasta que:
   - `start_rol_days + duration_days <= current_rol_days` (tiempo transcurrido).
   - El staff revisa y aprueba/deniega.
3. Durante esta fase, el personaje no puede iniciar otro viaje (una sola navegación activa).

#### Fase 3: Llegada

1. El tiempo de rol ha transcurrido (`expected_end_rol_days <= game_rol_days_at()`).
2. El viaje está listo para revisión.
3. El staff puede ver el viaje en el panel de revisión.

#### Fase 4: Revisión

1. El staff revisa el viaje usando `navigation_voyage_review.php`.
2. Decide: `approve` o `deny`.
3. El sistema publica un mensaje automático en el hilo.
4. El viaje se marca como `approved`/`denied` y `arrived`/`cancelled`.

#### Fase 5: Completado

1. Viaje aprobado: El personaje ha llegado a destino. Puede postear en la isla de destino.
2. Viaje denegado: El personaje no llegó. El staff puede establecer causas narrativas.

### 10.3 Recuperación de Viaje para un Post

```php
// navigation_process.php:306-392
function game_navigation_voyage_for_post(int $postId): ?array
{
    // Busca viaje por post_id
    // Carga eventos con sus oráculos
    // Carga islas origen/destino
    // Carga barco
    // Enrriquece con fechas de rol
    // Retorna payload completo para cards_for_post
}
```

**Payload de retorno:**
```json
{
  "id": 1,
  "island_from": {"fid": 42, "name": "Shells Town", "sea_zone": "east_blue"},
  "island_to": {"fid": 57, "name": "Orange Town", "sea_zone": "east_blue"},
  "ship": {"id": 12, "name": "Goleta Merry"},
  "distance": 28,
  "danger_level": 2,
  "duration_days": 3,
  "num_events": 2,
  "navigator_bonus": 2,
  "instrument": "compass",
  "events": [
    {
      "event_order": 1,
      "danger_tier": 2,
      "roll_value": 7,
      "result_range": "6-10",
      "result_text": "Lluvia moderada / Neblina",
      "result_description": "Reduce levemente la visibilidad...",
      "oracle_name": "Evento de Navegación — Mar Tranquilo"
    }
  ],
  "status": "active",
  "staff_review": "pending",
  "start_rol_days": 150,
  "expected_end_rol_days": 153,
  "start_rol_label": "Día 150 de la Era",
  "expected_end_rol_label": "Día 153 de la Era"
}
```

---

## 11. Revisión de Staff

### 11.1 Flujo de Revisión

```
Staff abre panel de revisión
    │
    ▼
Lista de viajes pendientes (staff_review = 'pending')
    │
    ├── Ver detalle del viaje:
    │    ├── Post original (enlace)
    │    ├── Ruta (origen → destino)
    │    ├── Distancia, peligro, duración
    │    ├── Eventos generados (con resultados de oráculo)
    │    └── Tiempo de rol transcurrido
    │
    ├── Decisión: Approve
    │    └── Sistema publica post automático: "viaje completado"
    │    └── Viaje marcado como 'approved' / 'arrived'
    │
    └── Decisión: Deny
         └── Sistema publica post automático: "viaje denegado"
         └── Viaje marcado como 'denied' / 'cancelled'
```

### 11.2 Qué Revisa el Staff

| Aspecto | Qué verificar |
|---------|--------------|
| **Validez de ruta** | ¿La ruta existe? ¿La distancia es razonable? |
| **Tiempo transcurrido** | ¿Han pasado suficientes días de rol desde `start_rol_days`? |
| **Eventos** | ¿Los eventos generados tienen sentido narrativo? ¿Se rolearon? |
| **Equipamiento** | ¿El personaje tiene el barco e instrumento seleccionados? |
| **Calidad narrativa** | ¿El post de salida tiene contenido coherente con el viaje? |

### 11.3 Endpoint de Revisión

```php
// navigation_voyage_review.php
$uid = GameAjax::requireLogin();
if (game_get_active_staff_level($uid) < 2) {
    GameAjax::fail(403, 'Sin permiso');
}

GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$id = (int)($input['id'] ?? 0);
$decision = (string)($input['decision'] ?? '');

$result = game_navigation_review_voyage($id, $uid, (string)($mybb->user['username'] ?? 'Staff'), $decision);

GameAjax::json(true, [
    'id' => $id,
    'decision' => $decision,
    'post_id' => $result['post_id'] ?? null,
]);
```

### 11.4 Post Automático

Cuando el staff aprueba o deniega, el sistema publica un mensaje en el hilo del viaje:

**Aprobado:**
```
[Navegación — Staff] La travesía de Shells Town a Orange Town
se ha completado con éxito. El viaje concluyó según lo previsto.
```

**Denegado:**
```
[Navegación — Staff] La travesía de Shells Town a Orange Town
no pudo completarse. El staff ha denegado la navegación.
```

---

## 12. Herramientas de Staff

### 12.1 Gestión de Rutas (`navigation_routes_list.php` + `navigation_routes_save.php`)

**Listar rutas:**
```php
// navigation_routes_list.php
$q = $db->query("SELECT r.*, f1.name AS from_name, f2.name AS to_name
    FROM {$prefix}game_navigation_routes r
    JOIN {$prefix}forums f1 ON f1.fid = r.island_from_fid
    JOIN {$prefix}forums f2 ON f2.fid = r.island_to_fid
    ORDER BY r.id DESC");
```

**Guardar/editar ruta:**
```php
// navigation_routes_save.php
// Input: island_from_fid, island_to_fid, distance, waypoint_fids[], danger_override, id, delete
// INSERT ON DUPLICATE KEY UPDATE o DELETE según acción
```

El endpoint permite:
- Crear nueva ruta.
- Actualizar ruta existente (por par origen-destino único).
- Eliminar ruta (si `delete = true` y `id > 0`).

### 12.2 Listado de Islas (`navigation_islands_list.php`)

```php
// navigation_islands_list.php
$exclude = (int)($_GET['exclude'] ?? 0);
$islands = game_nav_list_islands($exclude);
GameAjax::json(true, ['islands' => $islands]);
```

Usado por el selector de destino en el formulario de post y por la herramienta de rutas.

### 12.3 Listado de Viajes (`navigation_voyages_list.php`)

```php
// navigation_voyages_list.php
// Filtros: thread_id, character_id, staff_review (pending/approved/denied)
// Solo muestra viajes donde expected_end_rol_days <= currentRolDays
// Retorna: voyages[], pending_count
```

**Filosofía del filtro temporal:** Solo se muestran viajes cuyo tiempo de rol ha expirado. Esto evita que el staff revise viajes que aún están "en curso" desde la perspectiva del calendario de rol.

### 12.4 Cambio de Estado (`navigation_voyage_status.php`)

Endpoint para que el staff cambie manualmente el estado de un viaje:

```php
// navigation_voyage_status.php
$id = (int)($input['id'] ?? 0);
$status = (string)($input['status'] ?? '');
$allowed = ['active', 'arrived', 'cancelled'];
$db->write_query("UPDATE {$prefix}game_navigation_voyages SET status = '{$esc}' WHERE id = {$id}");
```

---

## 13. AJAX Endpoints

### 13.1 `navigation_context.php` — Contexto de Navegación

**Método:** GET
**Parámetros:** `fid`, `character_id`, `tid`
**Autenticación:** Requiere login
**Propósito:** Obtener el contexto de navegación para un post: isla actual, barcos, instrumentos, grado de navegante.
**Respuesta:**
```json
{
  "ok": true,
  "island_fid": 42,
  "island_name": "Shells Town",
  "has_island": true,
  "has_ship": true,
  "ships_count": 2,
  "ships": [{"card_id": 12, "name": "Goleta Merry", "velocidad": 5}],
  "instruments": [{"card_id": 8, "instrument_key": "compass", "name": "Brújula de latón"}],
  "character_id": 1,
  "navegante_rank": 2,
  "navegante_label": "II",
  "navegante_bonus": 1.0,
  "can_navigate": true
}
```

### 13.2 `navigation_ships.php` — Listar Barcos

**Método:** GET
**Parámetros:** `character_id` (opcional, usa PJ activo si no se especifica)
**Autenticación:** Requiere login + pertenencia del personaje (o staff level ≥ 2)
**Propósito:** Obtener los barcos que posee un personaje.
**Respuesta:** `{"ok": true, "ships": [...]}`

### 13.3 `navigation_preview.php` — Vista Previa del Viaje

**Método:** GET
**Parámetros:** `island_from`, `island_to`, `ship_card_id`, `character_id`, `instrument`
**Autenticación:** Requiere login
**Propósito:** Mostrar al jugador el resumen del viaje antes de confirmar.
**Respuesta:**
```json
{
  "ok": true,
  "distance": 28,
  "danger_level": 2,
  "danger_label": "Moderado",
  "effective_speed": 5.5,
  "duration_days": 3,
  "events_min": 1,
  "events_max": 3,
  "sea_zone": "east_blue"
}
```

### 13.4 `navigation_islands_list.php` — Listar Islas

**Método:** GET
**Parámetros:** `exclude` (fid a excluir, opcional)
**Autenticación:** Requiere login
**Propósito:** Obtener todas las islas disponibles para el selector de destino.
**Respuesta:** `{"ok": true, "islands": [{"fid": 42, "name": "Shells Town", "sea_zone": "east_blue", ...}]}`

### 13.5 `navigation_routes_list.php` — Listar Rutas

**Método:** GET
**Autenticación:** Staff level ≥ 3
**Propósito:** Obtener todas las rutas de navegación para gestión de staff.
**Respuesta:** `{"ok": true, "routes": [{"id": 1, "island_from_fid": 42, "island_to_fid": 57, "distance": 28, ...}]}`

### 13.6 `navigation_routes_save.php` — Guardar/Editar Ruta

**Método:** POST (JSON)
**Autenticación:** Staff level ≥ 3 + CSRF
**Parámetros:** `island_from_fid`, `island_to_fid`, `distance`, `waypoint_fids[]`, `danger_override`, `id`, `delete`
**Propósito:** CRUD de rutas de navegación.

### 13.7 `navigation_voyages_list.php` — Listar Viajes

**Método:** GET
**Parámetros:** `thread_id`, `character_id`, `staff_review`
**Autenticación:** Staff level ≥ 2
**Propósito:** Obtener lista de viajes para revisión.

### 13.8 `navigation_voyage_status.php` — Cambiar Estado

**Método:** POST (JSON)
**Parámetros:** `id`, `status` (active/arrived/cancelled)
**Autenticación:** Staff level ≥ 2 + CSRF
**Propósito:** Cambiar manualmente el estado de un viaje.

### 13.9 `navigation_voyage_review.php` — Revisar Viaje

**Método:** POST (JSON)
**Parámetros:** `id`, `decision` (approve/deny)
**Autenticación:** Staff level ≥ 2 + CSRF
**Propósito:** Aprobar o denegar un viaje pendiente.
**Respuesta:**
```json
{"ok": true, "id": 1, "decision": "approve", "post_id": 1234}
```

### 13.10 Mapa de Endpoints

| Endpoint | Método | Auth | Staff Level | Propósito |
|----------|--------|------|:-----------:|-----------|
| `navigation_context.php` | GET | Login | — | Contexto para post |
| `navigation_ships.php` | GET | Login + Propiedad | — | Barcos del PJ |
| `navigation_preview.php` | GET | Login | — | Vista previa |
| `navigation_islands_list.php` | GET | Login | — | Selector de islas |
| `navigation_routes_list.php` | GET | Staff | ≥3 | Listar rutas |
| `navigation_routes_save.php` | POST | Staff + CSRF | ≥3 | CRUD rutas |
| `navigation_voyages_list.php` | GET | Staff | ≥2 | Listar viajes |
| `navigation_voyage_status.php` | POST | Staff + CSRF | ≥2 | Cambiar estado |
| `navigation_voyage_review.php` | POST | Staff + CSRF | ≥2 | Revisar viaje |

---

## 14. Flujo de Datos Completo

### 14.1 Creación de Viaje

```
Usuario escribe post
    │
    ▼
Input: rpg_nav_enabled=1, nav_destination=57, nav_ship=12, nav_instrument=compass
    │
    ▼
navigation_process.php:game_navigation_process_post()
    │
    ├── Validar thread_type = 'Presente'
    ├── game_nav_get_island_from_forum(fid_del_post) → isla origen
    ├── Validar destino en game_forum_islands
    ├── Validar barco en inventario (slot_type='barco')
    │
    ├── game_nav_calculate_distance(42, 57) → {distance:28, waypoints:[], ...}
    ├── game_nav_calculate_danger(...) → 2
    ├── game_nav_effective_speed(...) → 6.5
    ├── game_nav_calculate_duration(28, 6.5) → 1
    ├── game_nav_calculate_events(2, 1) → 2
    │
    ├── INSERT INTO game_navigation_voyages (...)
    │
    └── game_navigation_generate_events(voyageId, postId, charId, 2, 2, rank)
         ├── game_nav_get_oracles_for_danger(2) → [oráculos nav_1_2, nav_2]
         ├── Para evento 1: roll oráculo → guardar en game_post_oracles + game_navigation_events
         ├── Para evento 2: roll oráculo → guardar
         └── auto_invoke si aplica
```

### 14.2 Revisión de Viaje

```
Staff abre panel
    │
    ▼
navigation_voyages_list.php (GET) → viajes pendientes
    │
    ▼
Staff selecciona viaje ID=1, decisión='approve'
    │
    ▼
navigation_voyage_review.php (POST)
    │
    ▼
game_navigation_review_voyage(1, staffUid, username, 'approve')
    │
    ├── Publicar post automático en hilo
    ├── UPDATE staff_review='approved', status='arrived'
    └── Retornar {ok:true, post_id:1234}
```

### 14.3 Consulta de Viaje para Post

```
Post se renderiza (cards_for_post)
    │
    ▼
game_navigation_voyage_for_post(postId)
    │
    ├── SELECT * FROM game_navigation_voyages WHERE post_id = X
    ├── SELECT eventos con JOIN a game_post_oracles y game_oracles
    ├── SELECT islas origen/destino
    ├── SELECT barco
    └── Retornar array completo
```

---

## 15. Filosofía de Diseño

### 15.1 ¿Por qué post-by-post navigation (no insta-travel)?

La navegación por posts convierte cada viaje en una **oportunidad narrativa**. En lugar de un simple "teletransportarse a otra isla", el personaje:
1. Escribe un post anunciando su partida.
2. El sistema genera eventos climáticos y encuentros.
3. El jugador puede rolear esos eventos en posts posteriores.
4. El staff revisa la coherencia del viaje.
5. La llegada es un evento significativo.

**Beneficios:**
- Los viajes largos se sienten largos (días de rol, no segundos reales).
- Cada viaje genera contenido para el foro (posts de navegación).
- El clima y los eventos crean drama impredecible.
- El staff puede intervenir si algo no cuadra.

### 15.2 ¿Por qué zonas con dificultad creciente?

El mundo de One Piece tiene una progresión geográfica natural. Las zonas reflejan esto:
- **Blues (1-2):** Seguros, predecibles. Para personajes de nivel bajo.
- **Grand Line (3):** El desafío real. Clima impredecible, Log Pose necesario.
- **New World (4-5):** Extremo. Solo para personajes poderosos o muy preparados.

Esto crea una **barrera natural de progresión**: un personaje de nivel D no debería poder navegar el New World sin preparación. El sistema de peligro y eventos lo hace explícito: un viaje de peligro 5 genera 4+ eventos con posibles oráculos de resolución de combate.

### 15.3 ¿Por qué oráculos de clima (no tiradas de stats)?

Las tiradas de stats (ej: "tira INT para navegar") son mecánicamente aburridas. Los oráculos producen resultados narrativos:
- En lugar de "éxito/fallo", producen "Viento a favor", "Tormenta menor", "Kraken avistado".
- Cada resultado tiene una descripción que el jugador puede usar para rolear.
- Los auto-invoke encadenan eventos: un resultado "Emboscada leve" invoca automáticamente un oráculo de resolución naval.

**El oráculo no solo dice qué pasa, sino que da al jugador material narrativo para escribir.**

### 15.4 ¿Por qué el navegante es importante (pero no obligatorio)?

- **No obligatorio:** Cualquier personaje puede navegar con instrumentos básicos. No se penaliza al que no eligió navegante como oficio.
- **Importante:** Un navegante Grado V viaja 2.5 unidades más rápido, evita eventos climáticos y mitiga los que no puede evitar. Esto hace que tener un navegante en la tripulación sea valioso sin ser necesario.

**Tradeoff estratégico:** El jugador puede invertir PP en navegante (barato: 50/90/130/190 PP) o en disciplinas de combate (caras: 80/140/180/250 PP). Es una decisión con peso real.

### 15.5 ¿Por qué staff review?

La revisión de staff asegura:
1. **Justicia:** Que el viaje no se haya completado en tiempo irreal.
2. **Calidad narrativa:** Que el post de salida tenga sentido para el viaje.
3. **Consistencia:** Que el personaje no esté en dos lugares a la vez.
4. **Oportunidades:** El staff puede añadir eventos narrativos adicionales.

Sin staff review, el sistema sería puramente mecánico y perdería la capa de curaduría narrativa que define a un RPG de foro.

### 15.6 ¿Por qué `raw_calculation_json`?

Esta columna es una auditoría completa de cómo se calculó cada viaje. Permite:
- Al staff: Verificar que el cálculo fue correcto.
- Al desarrollador: Debuggear si algo sale mal.
- Al sistema: Recalcular si cambian las reglas.
- Al jugador: Entender por qué su viaje dura X días.

---

## 16. Consejos para Jugadores

### 16.1 Planificación de Rutas

- **Conoce las distancias:** Una ruta vecina en East Blue (3-8 unidades) toma 1 día. Una ruta larga (30+ unidades) puede tomar 3-5 días.
- **Usa la vista previa:** Antes de postear, usa el botón de vista previa para ver la duración estimada.
- **Encadena viajes:** Si necesitas ir muy lejos, planea escalas. Un viaje directo muy largo genera más eventos y es más riesgoso.
- **Evita el Calm Belt:** No hay islas registradas allí, pero si tu ruta pasa por él (por coordenadas), el peligro será máximo.

### 16.2 Preparación del Barco

- **Velocidad base importa:** Un barco con velocidad base 8 viaja casi el doble que uno con velocidad 4.
- **Bonus de zona:** Algunos barcos tienen `nav_bonus_grand_line` que los hace más rápidos en la Grand Line.
- **Mantenimiento:** Un barco dañado (si el sistema de daños se implementa) podría tener velocidad reducida.

### 16.3 Instrumentos

- **Siempre lleva un instrumento.** Navegar sin instrumento te da -1.0 de velocidad. Es mejor que nada, pero duele.
- **Brújula para Blues:** En los cuatro Blues, la brújula es suficiente.
- **Log Pose para Grand Line:** Obligatorio. Sin Log Pose en Grand Line, te pierdes.
- **Eternal Pose para destinos fijos:** Si viajas frecuentemente entre dos islas, consigue un Eternal Pose (+1.0 de velocidad constante).

### 16.4 El Oficio de Navegante

- **Grado I-II:** Suficiente para Blues. Notarás la diferencia en velocidad.
- **Grado III:** Doble tirada en eventos. Reduce significativamente los eventos malos.
- **Grado IV-V:** Prácticamente inmune al clima en Blues. En Grand Line, sobrevives mucho mejor.
- **Inversión recomendada:** Si tu personaje viaja mucho, vale la pena invertir en navegante hasta grado III al menos. Son 140 PP totales (50+90).

### 16.5 Roleplay Durante Viajes

- **Lee tus eventos:** Cuando el sistema genere eventos, úsalos como inspiración para tus posts.
- **Escribe sobre el viaje:** No te limites a "llego a la isla". Describe el mar, el viento, las sensaciones.
- **Interactúa con la tripulación:** Si viajas con otros, crea escenas a bordo.
- **Usa los oráculos de resolución:** Si te sale "Abordaje", rolea el combate o la negociación.

### 16.6 Checklist Pre-Viaje

```
☐ ¿Estoy en un hilo de tipo "Presente"?
☐ ¿Tengo un barco equipado (slot_type = 'barco')?
☐ ¿Tengo un instrumento de navegación?
☐ ¿La isla destino existe y es diferente de la actual?
☐ ¿Tengo suficiente tiempo de rol para el viaje?
☐ ¿Mi personaje tiene sentido que haga este viaje ahora?
```

---

## 17. Consejos para Staff

### 17.1 Configuración de Islas

- **Toda isla necesita coordenadas.** Sin coordenadas, la distancia se calcula como 100 (máximo).
- **Asigna `base_danger` según la zona:** East Blue 1-2, Grand Line 3, New World 4-5.
- **Usa `requires_log_pose` y `requires_compass`** para islas especiales que requieren instrumentos específicos.
- **Coordina con el lore:** Las islas de la Grand Line deberían ser más peligrosas no solo numéricamente, sino también en descripción.

### 17.2 Configuración de Rutas

- **Define rutas para pares comunes:** Evita que el sistema calcule distancias Euclidianas (que pueden no tener sentido narrativo).
- **Usa `danger_override` con moderación:** Solo para rutas específicas que sean más o menos peligrosas que la media de la zona.
- **Waypoints:** Úsalos para rutas largas donde haya islas menores que no son parada pero sí cruce.
- **Rutas bidireccionales:** La ruta se define una vez y funciona en ambos sentidos gracias a la lógica OR en `game_nav_calculate_distance()`.

### 17.3 Revisión de Viajes

- **Verifica el tiempo de rol:** No apruebes un viaje si `expected_end_rol_days > current_rol_days`. El personaje aún está viajando.
- **Lee el post original:** Asegúrate de que el jugador realmente escribió una salida coherente.
- **Considera los eventos:** Si el viaje generó eventos severos, el jugador debería haberlos roleado al menos mínimamente.
- **Sé justo:** Si el jugador hizo todo correctamente (post, equipo, tiempo), aprueba. Si falta algo, deniega con explicación.

### 17.4 Creación de Eventos Interesantes

- **Usa los oráculos de resolución:** Si un evento se sale de control, el sistema invocará automáticamente `nav_resolve_naval` o `nav_resolve_beast`.
- **Añade eventos manuales:** Si ves oportunidad narrativa, puedes añadir eventos adicionales editando directamente la BD.
- **Variedad es clave:** No todos los eventos tienen que ser climáticos. Usa encuentros con barcos, criaturas, islas fantasma, etc.

### 17.5 Balanceo de Zonas

- **Monitorea las rutas más usadas:** Si todos viajan entre las mismas dos islas, considera añadir más peligro o eventos a esa ruta.
- **Ajusta distancias:** Si un viaje "debería" tomar más tiempo del que calcula el sistema, sube la distancia de la ruta.
- **Niveles de peligro:** Revisa periódicamente que el `base_danger` de las islas siga siendo coherente con el desarrollo del foro.

### 17.6 Herramientas de Staff

- **Panel de rutas:** Usa `navigation_routes_list.php` + `navigation_routes_save.php` para mantener las rutas.
- **Panel de viajes:** Usa `navigation_voyages_list.php` para ver el estado de todos los viajes.
- **Cambio manual:** Si algo sale mal (viaje huérfano, error de cálculo), usa `navigation_voyage_status.php` para corregirlo.

---

## 18. Guía de Troubleshooting

### 18.1 Problemas Comunes

| Problema | Causa probable | Solución |
|----------|---------------|----------|
| El viaje no se crea | `rpg_nav_enabled !== '1'` o thread no es "Presente" | Verificar formulario y tipo de hilo |
| Error "Isla no encontrada" | El foro actual no está en `game_forum_islands` | Registrar el foro como isla |
| Error "Barco no encontrado" | `ship_card_id` no existe o no es tipo 'barco' | Verificar card type |
| Error "Barco no equipado" | El barco no está en inventario del PJ (slot_type ≠ 'barco') | Asignar barco al PJ |
| Distancia incorrecta | Faltan coordenadas o ruta precalculada | Definir ruta en `game_navigation_routes` |
| Demasiados eventos | Peligro alto + duración larga + aleatoriedad | Es normal. Si es excesivo, ajustar `base_danger` |
| Velocidad muy baja | Sin instrumento, sin navegante, barco lento | Conseguir instrumento o mejorar barco |
| El staff review no aparece | Columna `staff_review` no existe en la tabla | Ejecutar `migrate_navigation_voyage_review.php` |

### 18.2 Debugging

Para debuggear un viaje específico, revisa:

1. **`raw_calculation_json`** en `game_navigation_voyages`: Muestra el snapshot completo del cálculo.
2. **`game_navigation_events`** + **`game_post_oracles`**: Los eventos generados y las tiradas de oráculo.
3. **`game_oficio_get_rank(characterId, 'navegante')`**: Verificar que el grado de navegante es correcto.
4. **`game_nav_compute_voyage(...)`**: Ejecutar manualmente desde una shell de prueba.

### 18.3 Migraciones

Si estás añadiendo navegación a un foro existente, ejecuta en orden:
1. `migrate_forum_islands.php` — Crear tabla base de islas.
2. `migrate_navigation_system.php` — Crear tablas de navegación + columnas en `game_forum_islands`.
3. `migrate_navigation_voyage_review.php` — Añadir columnas de staff review.
4. `migrate_navigation_oracles_expand.php` — Añadir oráculos expandidos por nivel de peligro.
5. `seed_forum_islands.php` — Poblar islas existentes.

---

## Apéndice A: Ejemplos de Uso

### A.1 Ejemplo de Post con Navegación

```html
<form method="post">
  <input type="hidden" name="rpg_nav_enabled" value="1">
  <select name="nav_destination_island_id">
    <option value="57">Orange Town</option>
    <option value="63">Syrup Village</option>
  </select>
  <select name="nav_ship_card_id">
    <option value="12">Goleta Merry</option>
  </select>
  <select name="nav_instrument">
    <option value="compass">Brújula</option>
  </select>
  <textarea name="message">
    Izamos velas al amanecer. El viento sopla del este,
    favorable. Dejamos atrás Shells Town con la esperanza
    de alcanzar Orange Town antes del anochecer.
  </textarea>
  <input type="submit" value="Navegar">
</form>
```

### A.2 Ejemplo de Viaje Completado

```json
{
  "id": 1,
  "post_id": 1234,
  "thread_id": 56,
  "character_id": 1,
  "ship_card_id": 12,
  "island_from_fid": 42,
  "island_to_fid": 57,
  "distance": 28,
  "danger_level": 2,
  "duration_days": 3,
  "num_events": 2,
  "navigator_bonus": 2,
  "instrument_used": "compass",
  "instrument_bonus": 0,
  "status": "arrived",
  "staff_review": "approved",
  "start_rol_days": 150,
  "expected_end_rol_days": 153,
  "reviewed_at": 1718000000,
  "reviewed_by_uid": 2,
  "staff_notice_post_id": 1240,
  "events": [
    {
      "event_order": 1,
      "roll_value": 7,
      "result_text": "Lluvia moderada"
    },
    {
      "event_order": 2,
      "roll_value": 14,
      "result_text": "Tormenta menor"
    }
  ]
}
```

### A.3 Ejemplo de Ruta Precalculada

```json
{
  "id": 5,
  "island_from_fid": 42,
  "island_to_fid": 63,
  "distance": 48,
  "waypoint_fids": [45, 51],
  "danger_override": null,
  "from_name": "Shells Town",
  "to_name": "Syrup Village"
}
```

---

## Apéndice B: Referencia Rápida de Funciones

| Función | Archivo | Propósito |
|---------|---------|-----------|
| `game_nav_sea_zone_labels()` | `navigation_config.php` | Etiquetas de zonas |
| `game_nav_get_island_from_forum($fid)` | `navigation_helpers.php` | Resolver foro a isla |
| `game_nav_calculate_distance($from, $to)` | `navigation_helpers.php` | Calcular distancia |
| `game_nav_calculate_danger($from, $to, $wps, $override)` | `navigation_helpers.php` | Calcular peligro |
| `game_nav_effective_speed($effects, $zone, $rank, $inst)` | `navigation_helpers.php` | Velocidad efectiva |
| `game_nav_calculate_duration($dist, $speed)` | `navigation_helpers.php` | Duración en días |
| `game_nav_calculate_events($danger, $duration, $random)` | `navigation_helpers.php` | Número de eventos |
| `game_nav_events_range($danger, $duration)` | `navigation_helpers.php` | Rango de eventos |
| `game_nav_danger_label($level)` | `navigation_helpers.php` | Etiqueta de peligro |
| `game_nav_get_oracles_for_danger($danger)` | `navigation_helpers.php` | Oráculos por peligro |
| `game_nav_list_islands($exclude)` | `navigation_helpers.php` | Listar islas |
| `game_nav_ships_for_character($charId)` | `navigation_helpers.php` | Barcos del PJ |
| `game_nav_instruments_for_character($charId)` | `navigation_helpers.php` | Instrumentos del PJ |
| `game_nav_compute_voyage($from, $to, $effects, $rank, $inst)` | `navigation_helpers.php` | Cómputo completo |
| `game_navigation_process_post($postId, $threadId, $charId, $input)` | `navigation_process.php` | Procesar post → viaje |
| `game_navigation_generate_events($voyageId, $postId, $charId, $num, $danger, $rank)` | `navigation_process.php` | Generar eventos |
| `game_navigation_voyage_for_post($postId)` | `navigation_process.php` | Viaje de un post |
| `game_navigation_review_voyage($id, $uid, $user, $decision)` | `navigation_review_helpers.php` | Revisar viaje |
| `game_navigation_post_thread_reply($tid, $uid, $user, $msg)` | `navigation_review_helpers.php` | Post automático |
| `game_navigation_voyage_enrich_row($row)` | `navigation_review_helpers.php` | Enriquecer fila |
