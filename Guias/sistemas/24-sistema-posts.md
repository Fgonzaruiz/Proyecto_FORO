# 24. Sistema de Posts — PV/PE y Modificadores

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 24
> **Propósito:** Documentar exhaustivamente el subsistema de registro por post: modelo de datos `game_post_characters`, estado por hilo `game_thread_pj_state`, tracking de PV/PE, declaración de PA, modificadores por post, acciones ocultas, snapshot de equipamiento, debug, integración con navegación — y **por qué** cada pieza existe, cómo procesa PHP cada columna, y consejos para jugadores y staff.
> **Dependencias:** `01-personaje.md` (ficha), `04-pa-pp.md` (PA/PE), `05-cards.md` (cartas jugadas), `06-inventario.md` (equipped snapshot), `14-navegacion.md` (viajes), `02-stats.md` (cálculo de PV/PE).

---

## ÍNDICE

1. [Arquitectura General del Sistema de Posts](#1-arquitectura-general)
2. [game_post_characters — Todas las Columnas](#2-game_post_characters)
    - 2.1 [post_id (PK)](#21-post_id-pk)
    - 2.2 [thread_id](#22-thread_id)
    - 2.3 [user_id](#23-user_id)
    - 2.4 [character_id](#24-character_id)
    - 2.5 [pv_change](#25-pv_change)
    - 2.6 [pe_change](#26-pe_change)
    - 2.7 [pa_declared](#27-pa_declared)
    - 2.8 [modifiers_json](#28-modifiers_json)
    - 2.9 [hidden_actions_json](#29-hidden_actions_json)
    - 2.10 [equipped_snapshot_json](#210-equipped_snapshot_json)
    - 2.11 [created_at](#211-created_at)
    - 2.12 [Índices](#212-índices)
3. [game_thread_pj_state — Estado por Hilo](#3-game_thread_pj_state)
    - 3.1 [Estructura de la Tabla](#31-estructura)
    - 3.2 [current_pv y current_pe](#32-current_pv-y-current_pe)
    - 3.3 [stat_mods_json](#33-stat_mods_json)
    - 3.4 [last_post_id](#34-last_post_id)
    - 3.5 [Flujo de Actualización](#35-flujo-de-actualización)
4. [PV/PE Change Tracking](#4-pvpe-change-tracking)
    - 4.1 [Cómo se Calcula pv_change y pe_change](#41-cálculo)
    - 4.2 [Entrada del Usuario](#42-entrada-del-usuario)
    - 4.3 [game_postcharacter_compute_post_modifiers](#43-función-de-cómputo)
    - 4.4 [Inicialización de PV/PE en Primer Post](#44-inicialización-en-primer-post)
5. [PA Declaration](#5-pa-declaration)
    - 5.1 [Qué es pa_declared](#51-definición)
    - 5.2 [Flujo Frontend → Backend](#52-flujo-frontend-a-backend)
    - 5.3 [Relación con Cartas](#53-relación-con-cartas)
    - 5.4 [Validación por Staff (NO automática)](#54-validación-por-staff)
6. [Sistema de Modificadores (modifiers_json)](#6-sistema-de-modificadores)
    - 6.1 [Estructura de modifiers_json](#61-estructura)
    - 6.2 [Cómo se Envían desde el Frontend](#62-cómo-se-envían)
    - 6.3 [Procesamiento en game_postcharacter.php](#63-procesamiento-en-plugin)
    - 6.4 [Consumo en ProcessPostCards](#64-consumo-en-processpostcards)
    - 6.5 [Migraciones](#65-migraciones)
7. [Hidden Actions (hidden_actions_json)](#7-hidden-actions)
    - 7.1 [Qué son las Acciones Ocultas](#71-definición)
    - 7.2 [Estructura de hidden_actions_json](#72-estructura)
    - 7.3 [Procesamiento en ProcessPostCards](#73-procesamiento-en-processpostcards)
    - 7.4 [Visibilidad en cards_for_post.php](#74-visibilidad-en-cards_for_post)
    - 7.5 [Revelación de Acciones](#75-revelación-de-acciones)
8. [Equipment Snapshot (equipped_snapshot_json)](#8-equipment-snapshot)
    - 8.1 [Qué es el Snapshot](#81-definición)
    - 8.2 [Cómo se Toma](#82-cómo-se-toma)
    - 8.3 [Cómo se Consulta](#83-cómo-se-consulta)
    - 8.4 [Validación de Cartas vs Snapshot](#84-validación-de-cartas-vs-snapshot)
9. [Post RPG Debug](#9-post-rpg-debug)
    - 9.1 [game_post_rpg_debug_enabled](#91-game_post_rpg_debug_enabled)
    - 9.2 [game_post_rpg_modifiers_ready](#92-game_post_rpg_modifiers_ready)
    - 9.3 [game_log_post_rpg](#93-game_log_post_rpg)
    - 9.4 [Debug en cards_for_post.php](#94-debug-en-cards_for_post)
10. [Integración con Navigation Processing](#10-integración-con-navegación)
    - 10.1 [game_navigation_process_post](#101-game_navigation_process_post)
    - 10.2 [game_navigation_voyage_for_post](#102-game_navigation_voyage_for_post)
    - 10.3 [Filtrado de Oráculos de Navegación](#103-filtrado-de-oráculos)
11. [Plugin MyBB game_postcharacter.php](#11-plugin-mybb)
    - 11.1 [Hooks del Plugin](#111-hooks)
    - 11.2 [game_postcharacter_save_post](#112-game_postcharacter_save_post)
    - 11.3 [game_postcharacter_save_thread](#113-game_postcharacter_save_thread)
    - 11.4 [game_postcharacter_save_thread_state](#114-game_postcharacter_save_thread_state)
    - 11.5 [game_postcharacter_process_cards](#115-game_postcharacter_process_cards)
    - 11.6 [game_postcharacter_process_oracles](#116-game_postcharacter_process_oracles)
    - 11.7 [game_postcharacter_has_rolls y Bloqueo de Edición](#117-bloqueo-de-edición)
    - 11.8 [game_postcharacter_delete_post y delete_thread](#118-eliminación-de-posts)
    - 11.9 [game_postcharacter_award_pp](#119-asignación-de-pp)
    - 11.10 [game_postcharacter_block_edit](#1110-bloqueo-de-edición-por-tiradas)
12. [AJAX Endpoints](#12-ajax-endpoints)
    - 12.1 [cards_for_post.php](#121-cards_for_post)
    - 12.2 [thread_pj_state.php](#122-thread_pj_state)
13. [Migraciones SQL](#13-migraciones-sql)
    - 13.1 [migrate_pj_system.php](#131-migrate_pj_system)
    - 13.2 [migrate_post_modifiers.php](#132-migrate_post_modifiers)
    - 13.3 [migrate_post_pa_declared.php](#133-migrate_post_pa_declared)
    - 13.4 [migrate_post_equipped_snapshot.php](#134-migrate_post_equipped_snapshot)
    - 13.5 [migrate_roll_modifiers.php](#135-migrate_roll_modifiers)
14. [Flujo Completo de un Post](#14-flujo-completo-de-un-post)
    - 14.1 [Post en Hilo Existente](#141-post-en-hilo-existente)
    - 14.2 [Post como Nuevo Hilo](#142-post-como-nuevo-hilo)
15. [Filosofía de Diseño](#15-filosofía-de-diseño)
    - 15.1 [¿Por Qué game_post_characters es Plana?](#151-por-qué-es-plana)
    - 15.2 [¿Por Qué PV/PE se Persisten y PA No?](#152-por-qué-pvpe-se-persisten-y-pa-no)
    - 15.3 [¿Por Qué el Staff Valida PA?](#153-por-qué-el-staff-valida-pa)
    - 15.4 [¿Por Qué Snapshot de Equipamiento?](#154-por-qué-snapshot)
    - 15.5 [¿Por Qué Acciones Ocultas?](#155-por-qué-acciones-ocultas)
    - 15.6 [¿Por Qué No se Puede Editar un Post con Tiradas?](#156-por-qué-no-se-puede-editar)
16. [Consejos para Jugadores](#16-consejos-para-jugadores)
17. [Consejos para Staff](#17-consejos-para-staff)
18. [Troubleshooting](#18-troubleshooting)
19. [Apéndice: Archivos del Subsistema](#19-apéndice-archivos)

---

## 1. Arquitectura General

El sistema de posts conecta el contenido narrativo del foro (los posts de MyBB) con el estado mecánico del RPG (PV, PE, PA, modificadores, equipamiento). Cada post de un personaje dispara una cadena de procesos que registran qué pasó mecánicamente en ese post.

```
┌─────────────────────────────────────────────────────────────────┐
│                     FLUJO DE UN POST RPG                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Usuario escribe post en newreply.php o newthread.php         │
│     ├── Incluye datos ocultos: rpg_played_cards, rpg_modifiers,  │
│     │   rpg_thread_pv, rpg_thread_pe, rpg_hidden_actions,        │
│     │   rpg_nav_enabled, rpg_oracles                             │
│     └── Envía formulario → MyBB datahandler                      │
│                                                                  │
│  2. MyBB inserta post en `mybb_posts`                            │
│     └── Dispara hook datahandler_post_insert_post_end            │
│                                                                  │
│  3. Plugin game_postcharacter.php                                │
│     ├── 3a. INSERT en game_post_characters (post_id, user_id,    │
│     │       character_id, thread_id)                             │
│     ├── 3b. game_postcharacter_save_equipped_snapshot            │
│     ├── 3c. game_postcharacter_process_cards → ProcessPostCards  │
│     │   ├── Juega cartas → INSERT en game_post_cards             │
│     │   ├── Guarda hidden_actions_json                           │
│     │   └── Aplica roll_modifiers_json por carta                 │
│     ├── 3d. game_postcharacter_process_oracles → ProcessPostOracles│
│     ├── 3e. game_navigation_process_post (si aplica)             │
│     └── 3f. game_postcharacter_save_thread_state                 │
│         ├── Calcula pv_change, pe_change, stat_mods              │
│         ├── UPDATE de game_post_characters                        │
│         └── UPSERT en game_thread_pj_state                       │
│                                                                  │
│  4. Post visible → AJAX cards_for_post.php                       │
│     Devuelve: cards, modifications, hidden_actions, oracles,     │
│               voyage al frontend                                  │
│                                                                  │
│  5. Staff revisa hilo completo                                   │
│     Verifica pa_declared, pv_change, pe_change, cartas           │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 1.1 Capas del Subsistema

```
game/ajax/
├── cards_for_post.php        → Lectura de datos RPG del post
├── thread_pj_state.php       → Estado actual PV/PE/PA por hilo+PJ

game/inc/
├── post_rpg_debug.php        → Funciones de depuración
├── navigation_process.php    → Procesamiento de viajes por post
├── navigation_helpers.php    → Helper de islas/rutas
└── stat_helpers.php          → Cálculo de PV/PE/contexto de stats

game/src/Application/UseCases/
├── ProcessPostCards.php      → Procesa cartas jugadas y hidden actions
└── ProcessPostOracles.php    → Procesa oráculos

game/sql/
├── install_schema_fragments.php  → Definición de tablas
├── migrate_pj_system.php         → Creación de game_post_characters
├── migrate_post_modifiers.php    → Añade pv_change, pe_change, modifiers_json
├── migrate_post_pa_declared.php  → Añade pa_declared
├── migrate_post_equipped_snapshot.php → Añade equipped_snapshot_json
└── migrate_roll_modifiers.php    → Añade roll_modifiers_json a game_post_cards

inc/plugins/
└── game_postcharacter.php    → Plugin MyBB (hooks, procesamiento principal)
```

### 1.2 Tablas Involucradas

| Tabla | Propósito | PK |
|-------|-----------|----|
| `game_post_characters` | Registro por post: qué PJ escribió, cambios de PV/PE, PA declarado, modificadores, hidden actions, snapshot | `post_id` |
| `game_thread_pj_state` | Estado persistente de PV/PE por hilo + personaje | `(thread_id, character_id)` |
| `game_post_cards` | Cartas jugadas en cada post | `id` auto_increment |
| `game_post_oracles` | Oráculos ejecutados en cada post | `id` auto_increment |
| `game_navigation_voyages` | Viajes iniciados desde un post | `id` auto_increment |

---

## 2. game_post_characters — Todas las Columnas

Esta es la tabla central del sistema de posts. Cada fila representa un post escrito por un personaje. Contiene tanto metadatos de identificación (quién, dónde) como datos mecánicos (qué cambió, qué se usó).

```sql
CREATE TABLE mybb_game_post_characters (
    post_id               INT PRIMARY KEY,
    thread_id             INT DEFAULT NULL,
    user_id               INT NOT NULL,
    character_id          INT NOT NULL,
    pv_change             INT NOT NULL DEFAULT 0,
    pe_change             INT NOT NULL DEFAULT 0,
    pa_declared           TINYINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'PA declarado gastado por el jugador en este post (referencia para staff, no validación automática)',
    modifiers_json        TEXT DEFAULT NULL
        COMMENT 'Modificadores de stats activos en este post',
    hidden_actions_json   TEXT DEFAULT NULL
        COMMENT 'Acciones ocultas no visibles al rival (trampas, preparativos)',
    equipped_snapshot_json TEXT DEFAULT NULL
        COMMENT 'Snapshot del equipamiento del PJ al momento del post',
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_thread_id (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.1 post_id (PK)

**Tipo:** `INT PRIMARY KEY`

**Propósito:** ID del post en MyBB (`mybb_posts.pid`). Es la clave primaria porque cada post de MyBB puede tener exactamente un personaje asociado (el personaje activo del usuario que escribe).

**Regla de negocio:** Un post puede tener múltiples personajes involucrados narrativamente, pero mecánicamente solo el personaje activo del autor queda registrado. Si dos usuarios escriben en el mismo post (coautoría), cada uno tiene su propia fila en `game_post_characters` — pero esto no ocurre en el sistema actual porque MyBB asigna un solo autor por post.

**PHP processing:**
```php
// En game_postcharacter_save_post:
$pid = (int)$dh->pid;
$db->write_query("INSERT IGNORE INTO {$prefix}game_post_characters 
    (post_id, user_id, character_id) VALUES ({$pid}, {$uid}, {$cid})");
```

El `INSERT IGNORE` previene duplicados si el hook se dispara múltiples veces.

### 2.2 thread_id

**Tipo:** `INT DEFAULT NULL`

**Propósito:** ID del hilo de MyBB (`mybb_threads.tid`) al que pertenece el post.

**Comportamiento:**
- En `game_postcharacter_save_post` (respuesta a hilo existente): el `thread_id` se actualiza después del INSERT inicial mediante `game_postcharacter_save_thread_state`, que recibe el `$tid` desde `$dh->data['tid']`.
- En `game_postcharacter_save_thread` (creación de nuevo hilo): se incluye desde el principio: `INSERT IGNORE INTO ... (post_id, thread_id, user_id, character_id)`.
- Es `NULL` solo en el breve instante entre el INSERT inicial y la actualización por `save_thread_state`, o en posts huérfanos por migraciones incompletas.

**Índice:** `INDEX idx_thread_id (thread_id)` — optimiza consultas como "todos los posts de este hilo" y el recálculo de `threadnum` en `game_personajes`.

**Uso en recálculo de contadores:**
```php
// En migrate_pj_system.php:
$thread_counts = $db->query("SELECT character_id, COUNT(*) as c 
    FROM {$prefix}game_post_characters 
    WHERE thread_id IS NOT NULL 
    GROUP BY character_id");
while ($tc = $db->fetch_array($thread_counts)) {
    $cid = (int)$tc['character_id'];
    $c = (int)$tc['c'];
    $db->write_query("UPDATE {$prefix}game_personajes SET threadnum = {$c} WHERE id = {$cid}");
}
```

### 2.3 user_id

**Tipo:** `INT NOT NULL`

**Propósito:** ID del usuario de MyBB (`mybb_users.uid`) que escribió el post.

**Por qué existe:** Aunque `character_id` identifica al personaje, `user_id` es necesario para:
- Consultas de permisos: "¿este usuario es dueño de este post?"
- Notificaciones: al responder a un hilo, se notifica al `uid` del autor del hilo.
- Staff review: el staff necesita saber qué usuario controla al personaje que posteó.

**No hay FK explícita** porque `game_*` no tiene FK formales a tablas de MyBB (por diseño, para mantener el módulo desacoplado).

### 2.4 character_id

**Tipo:** `INT NOT NULL`

**Propósito:** ID del personaje (`game_personajes.id`) que escribió el post.

**Flujo de obtención:**
```php
// game_postcharacter_save_post:
$cfg = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$row = $db->fetch_array($cfg);
$cid = (int)$row['active_pj_id'];
```

El personaje activo del usuario en el momento de escribir el post es el que queda registrado. Si el usuario cambia de personaje activo después, los posts anteriores no se ven afectados.

**Relación con `game_personajes.postnum` y `threadnum`:** Cada INSERT en `game_post_characters` incrementa `postnum` del personaje. Si es un nuevo hilo, también incrementa `threadnum`. Al borrar, se decrementan.

### 2.5 pv_change

**Tipo:** `INT NOT NULL DEFAULT 0`

**Propósito:** Cambio neto de Puntos de Vida (PV) en este post. Puede ser negativo (daño recibido), positivo (curación), o cero (sin cambio).

**Cómo se calcula:**
```php
// game_postcharacter_compute_post_modifiers (plugin):
$prev_pv = obtener_PV_previo_del_thread_state_o_maximo();
$current_pv = (int)$_POST['rpg_thread_pv'];  // o $prev_pv si no se envió
$pv_change = $current_pv - $prev_pv;
```

**Regla de negocio:** `pv_change` es siempre la diferencia entre el PV actual del hilo (antes de este post) y el nuevo PV que el jugador declara. No es un valor absoluto, sino un delta.

**Ejemplos:**
- Personaje con 80/100 PV actuales. Jugador indica 65 PV → `pv_change = -15` (recibió 15 de daño).
- Personaje con 80/100 PV. Jugador indica 85 PV → `pv_change = +5` (curó 5).
- Personaje con 80/100 PV. Jugador no envía valor → `pv_change = 0` (no hay cambio).

**Uso en frontend:** `cards_for_post.php` devuelve `modifications.pv_change` para que la UI muestre el cambio de vida en el post.

**Migración:** Añadida en `migrate_post_modifiers.php`:
```php
if (!$db->field_exists('pv_change', 'game_post_characters')) {
    $db->write_query("ALTER TABLE {$table} ADD pv_change INT NOT NULL DEFAULT 0");
}
```

### 2.6 pe_change

**Tipo:** `INT NOT NULL DEFAULT 0`

**Propósito:** Cambio neto de Puntos de Energía (PE) en este post. Funciona idéntico a `pv_change` pero para PE.

**Cálculo:**
```php
$prev_pe = obtener_PE_previo_del_thread_state_o_maximo();
$current_pe = (int)$_POST['rpg_thread_pe'];  // o $prev_pe si no se envió
$pe_change = $current_pe - $prev_pe;
```

**Ejemplos:**
- Personaje con 50/60 PE. Jugador indica 35 PE → `pe_change = -15` (gastó 15 PE en técnicas).
- Personaje con 50/60 PE. Jugador indica 55 PE → `pe_change = +5` (regeneró 5 PE por descanso).

**Diferencia con PA:** PE es persistente por hilo (se acumula/gasta entre posts), mientras que PA se refresca por post. `pe_change` refleja el delta real de energía entre posts consecutivos.

### 2.7 pa_declared

**Tipo:** `TINYINT UNSIGNED NOT NULL DEFAULT 0`

**Propósito:** PA (Puntos de Aventura) que el jugador declara haber gastado en este post. Es un valor de referencia para el staff, no una validación automática.

**Rango:** 0–255. Suficiente para cualquier personaje (máximo teórico ~43 PA).

**Comentario en SQL:**
```sql
COMMENT 'PA declarado gastado por el jugador en este post (referencia para staff, no validación automática)'
```

**Cómo se llena:**
```php
// El frontend JS calcula el coste total de PA de las cartas jugadas
// y lo envía como campo de formulario:
// <input type="hidden" name="pa_declared" value="12">

// El plugin lo recibe y (potencialmente) actualiza la fila:
// (Nota: en la versión actual, pa_declared se inserta en el INSERT 
// inicial o se deja en 0 si no se envía desde el frontend)
```

**Relación con cartas:** Cuando un jugador juega cartas, el JS suma los costes de PA de cada carta y envía el total como `pa_declared`. El staff luego verifica que:
1. `pa_declared <= max_pa` del personaje.
2. La suma de costes de las cartas jugadas coincide con `pa_declared`.
3. Las acciones narrativas justifican el gasto declarado.

**Migración:** `migrate_post_pa_declared.php` añade la columna:
```php
if (!$db->field_exists('pa_declared', 'game_post_characters')) {
    $db->write_query("ALTER TABLE {$prefix}game_post_characters 
        ADD COLUMN pa_declared TINYINT UNSIGNED NOT NULL DEFAULT 0 
        COMMENT 'PA declarado gastado por el jugador en este post (...)'
        AFTER pe_change");
}
```

### 2.8 modifiers_json

**Tipo:** `TEXT DEFAULT NULL`

**Propósito:** Almacena los modificadores de stats que el jugador declara activos en este post. Son bonificaciones/maluses temporales a los 7 stats base (fue, res, agi, des, int, esp, inst) que afectan las tiradas de este post específicamente.

**Estructura JSON:**
```json
{
    "fue": 5,
    "agi": -2,
    "res": 3
}
```

Cada clave es un stat en minúsculas, cada valor es un entero (positivo = bono, negativo = malus). Solo se incluyen los stats que tienen modificador; los stats sin cambio no aparecen.

**Cómo se envía desde el frontend:**
```javascript
// Desde el JS del editor de posts:
const modifiers = { fue: 5, agi: -2, res: 3 };
// Se envía como campo oculto:
// <input type="hidden" name="rpg_modifiers" value='{"fue":5,"agi":-2,"res":3}'>
```

**Procesamiento en el plugin:**
```php
// game_postcharacter_has_post_modifier_input() verifica si hay modificadores:
if (!empty($_POST['rpg_modifiers'])) {
    $raw = json_decode((string)$_POST['rpg_modifiers'], true);
    if (is_array($raw)) {
        foreach ($raw as $val) {
            if ((int)$val !== 0) { return true; }
        }
    }
}

// game_postcharacter_compute_post_modifiers() lo serializa:
$stat_mods_arr = [];
if (!empty($_POST['rpg_modifiers'])) {
    $raw = json_decode((string)$_POST['rpg_modifiers'], true);
    if (is_array($raw)) {
        $stat_mods_arr = $raw;
    }
}
$stat_mods_json = json_encode($stat_mods_arr, JSON_UNESCAPED_UNICODE);
```

**Consumo en ProcessPostCards:**
```php
// Los modificadores se aplican al contexto de stats para las tiradas:
$turn_mods = [];
if (!empty($postData['rpg_modifiers'])) {
    $raw_mods = json_decode($postData['rpg_modifiers'], true);
    if (is_array($raw_mods)) {
        $valid_stats = ['fue', 'res', 'agi', 'des', 'int', 'esp', 'inst'];
        foreach ($raw_mods as $mod_stat => $mod_val) {
            $mod_stat = strtolower(trim((string)$mod_stat));
            $mod_val = (int)$mod_val;
            if ($mod_val !== 0 && in_array($mod_stat, $valid_stats, true)) {
                $turn_mods[$mod_stat] = ($turn_mods[$mod_stat] ?? 0) + $mod_val;
            }
        }
    }
}
$ctx = game_build_stat_context($stats_raw, $raceName, $turn_mods);
```

**Visualización en frontend:** `cards_for_post.php` devuelve `modifications.stat_mods` como array asociativo para que la UI muestre qué modificadores estaban activos en ese post.

**Migración:** `migrate_post_modifiers.php`:
```php
if (!$db->field_exists('modifiers_json', 'game_post_characters')) {
    $db->write_query("ALTER TABLE {$table} ADD modifiers_json TEXT DEFAULT NULL");
}
```

### 2.9 hidden_actions_json

**Tipo:** `TEXT DEFAULT NULL`

**Propósito:** Almacena acciones ocultas que el jugador prepara en su post pero que no son visibles para el rival. Ejemplos: trampas, emboscadas, preparativos, movimientos sigilosos.

**Estructura JSON esperada:**
```json
[
    {
        "index": 1,
        "description": "Preparar una red trampa en la entrada",
        "is_revealed": 0
    },
    {
        "index": 2,
        "description": "Esconderse tras las barriles",
        "is_revealed": 0
    }
]
```

**Campos de cada acción:**
- `index` (int): Identificador único dentro del post. Se correlaciona con `hidden_action_index` en `game_post_cards`.
- `description` (string): Descripción narrativa de la acción oculta. Solo visible para el dueño del post (o si ha sido revelada).
- `is_revealed` (bool): 0 = oculta, 1 = revelada (visible para todos).

**Procesamiento en ProcessPostCards:**
```php
if (!empty($postData['rpg_hidden_actions'])) {
    $hidden_actions = json_decode($postData['rpg_hidden_actions'], true);
    if (is_array($hidden_actions)) {
        $saved_actions = [];
        foreach ($hidden_actions as $action) {
            $action_idx = (int)($action['index'] ?? 0);
            if ($action_idx <= 0) continue;
            
            $description = isset($action['description']) ? trim((string)$action['description']) : '';
            $action_cards = isset($action['cards']) && is_array($action['cards']) ? $action['cards'] : [];
            
            // Procesar cartas asociadas a esta acción oculta
            foreach ($action_cards as $c_entry) {
                game_postcharacter_process_card_entry($pid, $cid, $c_entry, 
                    $stats_for_dice, [], $action_idx, $equipped_ids);
            }
            
            $saved_actions[] = [
                'index' => $action_idx,
                'description' => $description,
                'is_revealed' => 0  // Siempre empieza oculta
            ];
        }
        
        if (!empty($saved_actions) && $db->field_exists('hidden_actions_json', 'game_post_characters')) {
            $json_str = json_encode($saved_actions, JSON_UNESCAPED_UNICODE);
            $esc_json = "'" . $db->escape_string($json_str) . "'";
            $db->write_query("UPDATE {$prefix}game_post_characters 
                SET hidden_actions_json = {$esc_json} 
                WHERE post_id = {$pid} AND character_id = {$cid}");
        }
    }
}
```

**Visibilidad en cards_for_post.php:**
```php
// Solo el dueño del post (o si la acción fue revelada) puede ver la descripción:
foreach ($hidden_actions as $act) {
    $idx = (int)($act['index'] ?? 0);
    if ($idx <= 0) continue;
    $revealed = (bool)($act['is_revealed'] ?? false);
    $can_see = ($revealed || $is_post_owner_character);
    
    if ($can_see) {
        $processed_hidden_actions[] = [
            'index' => $idx,
            'description' => $act['description'] ?? '',
            'is_revealed' => $revealed,
            'can_reveal' => ($is_post_owner_character && !$revealed),
            'cards' => [],
        ];
    }
}
```

**Correlación con game_post_cards:** Las cartas jugadas dentro de una acción oculta tienen `hidden_action_index` = el índice de la acción. Desde `cards_for_post.php`, se agrupan:
```php
$h_idx = (int)$row['hidden_action_index'];
if ($h_idx === 0) {
    $normal_cards[] = $row;  // Cartas visibles normales
} elseif (!empty($visible_hidden_indexes[$h_idx])) {
    $hidden_cards_by_action[$h_idx][] = $row;  // Cartas de acción oculta
}
```

### 2.10 equipped_snapshot_json

**Tipo:** `TEXT DEFAULT NULL`

**Propósito:** Una "foto" del equipamiento que el personaje tenía en el momento exacto de escribir el post. Es una lista de IDs de cartas que el personaje tenía equipadas en sus slots.

**Estructura JSON:**
```json
[45, 102, 78, 203]
```

Simplemente un array de `card_id`s.

**Cómo se toma (game_postcharacter_save_equipped_snapshot):**
```php
function game_postcharacter_save_equipped_snapshot(int $pid, int $cid): array {
    game_postcharacter_ensure_inventory_helpers();
    global $db;
    $ids = function_exists('game_get_equipped_card_ids')
        ? game_get_equipped_card_ids($cid)
        : [];
    if (!game_postcharacter_equipped_snapshot_ready()) {
        return $ids;
    }
    $prefix = TABLE_PREFIX;
    $json = json_encode(array_values($ids), JSON_UNESCAPED_UNICODE);
    $esc = $db->escape_string($json);
    $db->write_query(
        "UPDATE {$prefix}game_post_characters
         SET equipped_snapshot_json = '{$esc}'
         WHERE post_id = {$pid} AND character_id = {$cid}"
    );
    return $ids;
}
```

**Por qué es importante:**
- **Congela el estado del inventario** al momento del post. Si el personaje cambia de equipamiento después (vende un arma, cambia de armadura), el snapshot preserva qué tenía cuando escribió.
- **Valida cartas jugadas:** `game_postcharacter_card_allowed_in_post` verifica que una carta que requiere slot equipado esté en el snapshot:
```php
function game_postcharacter_card_allowed_in_post(string $cardType, int $cardId, array $equippedIds, bool $isConsumible = false): bool {
    game_postcharacter_ensure_inventory_helpers();
    if (!function_exists('game_card_requires_equipped_slot') || !game_card_requires_equipped_slot($cardType, $isConsumible)) {
        return true;
    }
    $allowed = in_array($cardId, $equippedIds, true);
    return $allowed;
}
```
- **Recuperación para procesos posteriores:** `game_postcharacter_get_post_equipped_ids` lee el snapshot del post, y si no existe, cae al equipamiento actual:
```php
function game_postcharacter_get_post_equipped_ids(int $pid, int $cid): array {
    game_postcharacter_ensure_inventory_helpers();
    global $db;
    $prefix = TABLE_PREFIX;
    if (game_postcharacter_equipped_snapshot_ready()) {
        $q = $db->query(
            "SELECT equipped_snapshot_json FROM {$prefix}game_post_characters
             WHERE post_id = {$pid} AND character_id = {$cid} LIMIT 1"
        );
        $row = $db->fetch_array($q);
        if ($row && ($row['equipped_snapshot_json'] ?? '') !== '') {
            $decoded = json_decode($row['equipped_snapshot_json'], true);
            if (is_array($decoded)) {
                return array_values(array_unique(array_map('intval', $decoded)));
            }
        }
    }
    return function_exists('game_get_equipped_card_ids') ? game_get_equipped_card_ids($cid) : [];
}
```

**Migración:** `migrate_post_equipped_snapshot.php`:
```php
if (!$db->field_exists('equipped_snapshot_json', 'game_post_characters')) {
    if ($db->field_exists('hidden_actions_json', 'game_post_characters')) {
        $db->write_query("ALTER TABLE {$table} ADD equipped_snapshot_json TEXT DEFAULT NULL AFTER hidden_actions_json");
    } else {
        $db->write_query("ALTER TABLE {$table} ADD equipped_snapshot_json TEXT DEFAULT NULL");
    }
}
```

### 2.11 created_at

**Tipo:** `TIMESTAMP DEFAULT CURRENT_TIMESTAMP`

**Propósito:** Marca de tiempo de cuándo se registró la fila. No necesariamente coincide con el timestamp del post de MyBB (que puede ser diferente por segundos). Útil para auditoría y depuración.

### 2.12 Índices

| Índice | Columnas | Propósito |
|--------|----------|-----------|
| `PRIMARY` | `post_id` | Búsqueda por post, JOIN con `mybb_posts.pid` |
| `idx_thread_id` | `thread_id` | Consultas por hilo (lista de PJs en un hilo, recálculo de threadnum) |

No hay FK explícitas por diseño (D001: sin backend externo en prod, módulo neutro).

---

## 3. game_thread_pj_state — Estado por Hilo

### 3.1 Estructura

```sql
CREATE TABLE mybb_game_thread_pj_state (
    thread_id       INT NOT NULL,
    character_id    INT NOT NULL,
    current_pv      INT NOT NULL,
    current_pe      INT NOT NULL,
    stat_mods_json  TEXT DEFAULT NULL
        COMMENT 'Modificadores de stats activos en este hilo',
    last_post_id    INT DEFAULT NULL,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (thread_id, character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**PK compuesta `(thread_id, character_id)`:** Cada personaje tiene exactamente un estado por hilo. No puede haber dos filas para el mismo personaje en el mismo hilo.

### 3.2 current_pv y current_pe

**Propósito:** Almacenan los PV y PE actuales del personaje dentro del contexto de un hilo específico.

**Reglas de persistencia:**
- Se crean en el primer post del personaje en el hilo (con valores iniciales = máximos).
- Se actualizan en cada post subsiguiente donde el jugador declare cambios de PV/PE.
- No hay un "pool de PA" aquí porque PA no persiste entre posts.

**Inicialización (cuando no hay fila previa en `game_thread_pj_state`):**
```php
// En game_postcharacter_compute_post_modifiers:
if ($prev_pv === 0 && $prev_pe === 0) {
    $pj_q = $db->query("SELECT stats_json, race_name FROM {$prefix}game_personajes WHERE id = {$cid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        game_postcharacter_ensure_stat_helpers();
        $stats = json_decode($pj['stats_json'] ?? '{}', true);
        $ctx = game_build_stat_context($stats, (string)($pj['race_name'] ?? ''));
        $vitals = game_compute_pv_pe_from_context($ctx['values'], $ctx['trained']);
        $prev_pv = $vitals['max_pv'];
        $prev_pe = $vitals['max_pe'];
    }
}
```

Es decir, si no hay estado previo, se usan los PV/PE máximos calculados desde los stats del personaje.

### 3.3 stat_mods_json

**Tipo:** `TEXT DEFAULT NULL`

**Propósito:** Almacena los modificadores de stats activos en el hilo para este personaje. A diferencia de `modifiers_json` en `game_post_characters` (que son modificadores de un post específico), `stat_mods_json` aquí representa el estado acumulado.

**Estructura:** Mismo formato que `modifiers_json` en `game_post_characters`:
```json
{
    "fue": 5,
    "agi": -2
}
```

**Actualización:** Se sobrescribe completamente en cada post que tenga modificadores:
```php
// En game_postcharacter_save_thread_state:
$computed = game_postcharacter_compute_post_modifiers($tid, $cid);
$mods_esc = $db->escape_string($computed['stat_mods_json']);
$db->write_query("
    INSERT INTO {$prefix}game_thread_pj_state (thread_id, character_id, current_pv, current_pe, stat_mods_json, last_post_id)
    VALUES ({$tid}, {$cid}, {$current_pv}, {$current_pe}, '{$mods_esc}', {$pid})
    ON DUPLICATE KEY UPDATE
        current_pv = {$current_pv},
        current_pe = {$current_pe},
        stat_mods_json = '{$mods_esc}',
        last_post_id = {$pid}
");
```

### 3.4 last_post_id

**Tipo:** `INT DEFAULT NULL`

**Propósito:** ID del último post que actualizó el estado. Útil para:
- Auditoría: saber desde qué post viene el estado actual.
- Depuración: si hay discrepancia entre PV/PE esperados y reales, se puede rastrear el último post que los modificó.
- Procesamiento de rollback: si se elimina un post, se sabe cuál era el estado antes.

### 3.5 Flujo de Actualización

```
Post N escrito por personaje X en hilo Y:
  │
  ├─ ¿Existe fila en game_thread_pj_state para (Y, X)?
  │   ├─ NO  → current_pv = max_pv, current_pe = max_pe
  │   └─ SÍ  → current_pv = fila.current_pv, current_pe = fila.current_pe
  │
  ├─ ¿Jugador envió rpg_thread_pv o rpg_thread_pe?
  │   ├─ NO  → pv_change = 0, pe_change = 0 (no hay cambio)
  │   └─ SÍ  → pv_change = nuevo_pv - prev_pv
  │             pe_change = nuevo_pe - prev_pe
  │
  ├─ UPSERT en game_thread_pj_state:
  │     current_pv = nuevo_pv (o prev_pv si no cambió)
  │     current_pe = nuevo_pe (o prev_pe si no cambió)
  │     stat_mods_json = modificadores_enviados
  │     last_post_id = pid
  │
  └─ UPDATE en game_post_characters (para esta fila de post):
        pv_change = calculado
        pe_change = calculado
        modifiers_json = stat_mods_json
```

**Importante:** `pv_change` y `pe_change` se almacenan en `game_post_characters` (por post), mientras que `current_pv` y `current_pe` se almacenan en `game_thread_pj_state` (por hilo). Son dos vistas del mismo dato: el delta (por post) y el acumulado (por hilo).

---

## 4. PV/PE Change Tracking

### 4.1 Cálculo

El cálculo de `pv_change` y `pe_change` ocurre en `game_postcharacter_compute_post_modifiers()` en `game_postcharacter.php`:

```php
function game_postcharacter_compute_post_modifiers(int $tid, int $cid): array
{
    global $db;
    $prefix = TABLE_PREFIX;

    // 1. Procesar modificadores de stats (rpg_modifiers)
    $stat_mods_arr = [];
    if (!empty($_POST['rpg_modifiers'])) {
        $raw = json_decode((string)$_POST['rpg_modifiers'], true);
        if (is_array($raw)) {
            $stat_mods_arr = $raw;
        }
    }
    $stat_mods_json = json_encode($stat_mods_arr, JSON_UNESCAPED_UNICODE);

    // 2. Obtener PV/PE previos (desde game_thread_pj_state o máximos)
    $prev_pv = 0;
    $prev_pe = 0;
    if ($tid > 0 && $db->table_exists('game_thread_pj_state')) {
        $prev_q = $db->query("SELECT current_pv, current_pe 
            FROM {$prefix}game_thread_pj_state 
            WHERE thread_id = {$tid} AND character_id = {$cid} LIMIT 1");
        if ($prev_row = $db->fetch_array($prev_q)) {
            $prev_pv = (int)$prev_row['current_pv'];
            $prev_pe = (int)$prev_row['current_pe'];
        }
    }

    // 3. Si no hay estado previo, calcular máximos desde stats
    if ($prev_pv === 0 && $prev_pe === 0) {
        $pj_q = $db->query("SELECT stats_json, race_name FROM {$prefix}game_personajes WHERE id = {$cid} LIMIT 1");
        $pj = $db->fetch_array($pj_q);
        if ($pj) {
            game_postcharacter_ensure_stat_helpers();
            $stats = json_decode($pj['stats_json'] ?? '{}', true);
            if (!is_array($stats)) $stats = [];
            $ctx = game_build_stat_context($stats, (string)($pj['race_name'] ?? ''));
            $vitals = game_compute_pv_pe_from_context($ctx['values'], $ctx['trained']);
            $prev_pv = $vitals['max_pv'];
            $prev_pe = $vitals['max_pe'];
        }
    }

    // 4. Determinar nuevos valores (desde POST o mantener anteriores)
    $current_pv = (isset($_POST['rpg_thread_pv']) && $_POST['rpg_thread_pv'] !== '') 
        ? (int)$_POST['rpg_thread_pv'] : $prev_pv;
    $current_pe = (isset($_POST['rpg_thread_pe']) && $_POST['rpg_thread_pe'] !== '') 
        ? (int)$_POST['rpg_thread_pe'] : $prev_pe;

    // 5. Calcular cambios
    $pv_change = 0;
    $pe_change = 0;
    if (isset($_POST['rpg_thread_pv']) && $_POST['rpg_thread_pv'] !== '') {
        $pv_change = $current_pv - $prev_pv;
    }
    if (isset($_POST['rpg_thread_pe']) && $_POST['rpg_thread_pe'] !== '') {
        $pe_change = $current_pe - $prev_pe;
    }

    return [
        'pv_change' => $pv_change,
        'pe_change' => $pe_change,
        'current_pv' => $current_pv,
        'current_pe' => $current_pe,
        'stat_mods_json' => $stat_mods_json,
    ];
}
```

### 4.2 Entrada del Usuario

El frontend envía los valores de PV/PE mediante campos ocultos en el formulario del post:

```html
<!-- En el editor de posts (newreply.php / newthread.php): -->
<input type="hidden" name="rpg_thread_pv" id="rpg_thread_pv" value="75">
<input type="hidden" name="rpg_thread_pe" id="rpg_thread_pe" value="42">
```

Estos campos son actualizados por JS cuando el jugador interactúa con el sistema de cartas o modifica manualmente sus PV/PE.

**Si el campo no se envía o está vacío:** Se asume que no hay cambio y se mantiene el valor anterior. Esto permite que posts puramente narrativos (sin combate ni gasto de recursos) no generen cambios accidentales.

### 4.3 game_postcharacter_save_post_modifiers

Esta función guarda los modificadores calculados en `game_post_characters`:

```php
function game_postcharacter_save_post_modifiers(int $tid, int $cid, int $pid): void
{
    global $db;
    if ($pid <= 0 || $cid <= 0) return;
    
    // Si no hay input de modificadores, salir
    if (!game_postcharacter_has_post_modifier_input()) return;
    
    // Si las columnas no existen (migración no ejecutada), salir
    if (!game_postcharacter_post_modifiers_ready()) return;

    $prefix = TABLE_PREFIX;
    $computed = game_postcharacter_compute_post_modifiers($tid, $cid);
    $mods_esc = $db->escape_string($computed['stat_mods_json']);
    $pv_change = (int)$computed['pv_change'];
    $pe_change = (int)$computed['pe_change'];

    $db->write_query("
        UPDATE {$prefix}game_post_characters
        SET pv_change = {$pv_change},
            pe_change = {$pe_change},
            modifiers_json = '{$mods_esc}'
        WHERE post_id = {$pid} AND character_id = {$cid}
    ");

    if (function_exists('game_log_post_rpg')) {
        game_log_post_rpg('modifiers_saved', [
            'post_id' => $pid,
            'character_id' => $cid,
            'pv_change' => $pv_change,
            'pe_change' => $pe_change,
            'stat_mods' => $computed['stat_mods_json'],
        ]);
    }
}
```

### 4.4 Inicialización en Primer Post

Cuando un personaje escribe su primer post en un hilo, no hay fila en `game_thread_pj_state`. El sistema asume que el personaje empieza con sus PV y PE máximos:

```php
// game_postcharacter_compute_post_modifiers maneja el caso prev_pv === 0:
if ($prev_pv === 0 && $prev_pe === 0) {
    // Cargar stats del personaje
    $pj_q = $db->query("SELECT stats_json, race_name FROM {$prefix}game_personajes WHERE id = {$cid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        game_postcharacter_ensure_stat_helpers();
        $stats = json_decode($pj['stats_json'] ?? '{}', true);
        $ctx = game_build_stat_context($stats, (string)($pj['race_name'] ?? ''));
        $vitals = game_compute_pv_pe_from_context($ctx['values'], $ctx['trained']);
        $prev_pv = $vitals['max_pv'];  // Ej: 100
        $prev_pe = $vitals['max_pe'];  // Ej: 60
    }
}
```

Si el jugador no envía cambios en el primer post, `current_pv = prev_pv = max_pv` y `current_pe = prev_pe = max_pe`, y `pv_change = pe_change = 0`.

---

## 5. PA Declaration

### 5.1 Definición

`pa_declared` es el campo en `game_post_characters` donde el jugador declara cuántos Puntos de Aventura (PA) gasta en ese post. Es un **valor referencial**: no se valida automáticamente, sino que el staff lo revisa después.

**Tipo:** `TINYINT UNSIGNED NOT NULL DEFAULT 0`

**Rango efectivo:** 0–255. El máximo teórico de PA de cualquier personaje es ~43, así que sobra espacio.

### 5.2 Flujo Frontend a Backend

```
1. Jugador selecciona cartas en el editor de posts
2. JS calcula coste total de PA:
       coste_total = Σ coste_pa de cada carta jugada
3. JS actualiza campo oculto:
       document.getElementById('rpg_pa_input').value = coste_total;
4. Jugador envía el formulario del post
5. PHP recibe $_POST['pa_declared'] (si se implementó en el formulario)
6. Plugin registra el post con ese valor (o 0 por defecto)
```

**Nota importante:** En la implementación actual (según el código revisado), `pa_declared` no se establece explícitamente desde el plugin. El INSERT inicial en `game_post_characters` usa `INSERT IGNORE ... (post_id, user_id, character_id)` sin incluir `pa_declared`, por lo que queda en 0 por defecto. La columna existe y está lista para usarse cuando el frontend envíe el valor.

### 5.3 Relación con Cartas

Cada carta en `game_catalogo_cartas` (o equivalente) tiene un `coste_pa`. La suma de costes de todas las cartas jugadas en un post debería coincidir con `pa_declared`.

**Validación en el frontend (JS):**
```javascript
// Pseudocódigo JS en el editor de posts:
let totalPA = 0;
selectedCards.forEach(card => {
    totalPA += card.coste_pa;
});
document.getElementById('rpg_pa_declared').value = totalPA;
```

**Validación por staff (manual):** El staff revisa:
1. `pa_declared` en `game_post_characters`.
2. Las cartas jugadas en `game_post_cards` para ese `post_id`.
3. Suma los costes de PA de cada carta.
4. Verifica que la suma <= `max_pa` del personaje.
5. Verifica que la suma coincida con `pa_declared` (o que la diferencia sea justificable narrativamente).

### 5.4 Validación por Staff (NO automática)

Esta es una decisión de diseño fundamental: el sistema no valida PA automáticamente. Razones detalladas en la sección [15. Filosofía de Diseño](#15-filosofía-de-diseño).

**¿Qué verifica el staff exactamente?**

| Verificación | Descripción |
|---|---|
| `pa_declared <= max_pa` | Que el jugador no declare más PA de los que tiene disponibles. |
| Coste de cartas <= pa_declared | Que las cartas jugadas no excedan el PA declarado. |
| Coherencia narrativa | Que el PA declarado tenga sentido con las acciones descritas. |
| Consistencia entre posts | Que no haya un post con 0 PA y 5 cartas jugadas. |

---

## 6. Sistema de Modificadores (modifiers_json)

### 6.1 Estructura

`modifiers_json` es un objeto JSON donde cada clave es un stat y cada valor es un entero (modificador):

```json
{
    "fue": 5,
    "res": -3,
    "agi": 2,
    "des": 0,
    "int": 0,
    "esp": 1,
    "inst": -1
}
```

**Reglas:**
- Solo los 7 stats base son válidos: `fue`, `res`, `agi`, `des`, `int`, `esp`, `inst`.
- Stats con valor 0 pueden omitirse (no afectan el cálculo).
- Los modificadores se **suman** a los stats base del personaje para el contexto de tirada de ese post.
- No hay límite superior/inferior técnico, pero el sentido común aplica (no tendría sentido un +100 a FUE).

### 6.2 Cómo se Envían desde el Frontend

El JS del editor de posts construye el objeto de modificadores y lo envía como un campo oculto:

```javascript
// JS en showthread.php / newreply.php
const modifiers = {};
// Si el jugador activó un buff temporal:
modifiers.fue = 5;  // +5 FUE por algún motivo narrativo
modifiers.agi = -2; // -2 AGI por terreno difícil

// Se serializa a JSON:
const rpgModifiers = JSON.stringify(modifiers);
// Se asigna al campo oculto:
document.querySelector('[name="rpg_modifiers"]').value = rpgModifiers;
```

HTML:
```html
<input type="hidden" name="rpg_modifiers" value='{"fue":5,"agi":-2}'>
```

### 6.3 Procesamiento en game_postcharacter.php

**Paso 1: Detectar si hay modificadores (`game_postcharacter_has_post_modifier_input`)**
```php
function game_postcharacter_has_post_modifier_input(): bool
{
    if (isset($_POST['rpg_thread_pv']) && (string)$_POST['rpg_thread_pv'] !== '') {
        return true;
    }
    if (isset($_POST['rpg_thread_pe']) && (string)$_POST['rpg_thread_pe'] !== '') {
        return true;
    }
    if (!empty($_POST['rpg_modifiers'])) {
        $raw = json_decode((string)$_POST['rpg_modifiers'], true);
        if (is_array($raw)) {
            foreach ($raw as $val) {
                if ((int)$val !== 0) { return true; }
            }
        }
    }
    return false;
}
```

**Paso 2: Guardar en game_post_characters (`game_postcharacter_save_post_modifiers`):**
```php
// Se llama desde game_postcharacter_save_thread_state
// Actualiza pv_change, pe_change, modifiers_json en la fila del post
```

**Paso 3: Incluir en el contexto de stats para tiradas de cartas:**
Los modificadores se inyectan en `game_build_stat_context` para que las evaluaciones de dados los tengan en cuenta.

### 6.4 Consumo en ProcessPostCards

En `ProcessPostCards.php`, los modificadores del post se convierten en `$turn_mods` y se pasan a `game_build_stat_context`:

```php
$turn_mods = [];
if (!empty($postData['rpg_modifiers'])) {
    $raw_mods = json_decode($postData['rpg_modifiers'], true);
    if (is_array($raw_mods)) {
        $valid_stats = ['fue', 'res', 'agi', 'des', 'int', 'esp', 'inst'];
        foreach ($raw_mods as $mod_stat => $mod_val) {
            $mod_stat = strtolower(trim((string)$mod_stat));
            $mod_val = (int)$mod_val;
            if ($mod_val !== 0 && in_array($mod_stat, $valid_stats, true)) {
                $turn_mods[$mod_stat] = ($turn_mods[$mod_stat] ?? 0) + $mod_val;
            }
        }
    }
}
$ctx = game_build_stat_context($stats_raw, (string)($pj['race_name'] ?? ''), $turn_mods);
```

Esto afecta directamente las tiradas de dados: si una carta usa `fue` en su fórmula y el post tiene `+5 FUE`, la tirada se resuelve con el valor modificado.

### 6.5 Migraciones

El sistema de modificadores requiere 3 migraciones para funcionar completamente:

1. **`migrate_post_modifiers.php`** — Añade `pv_change`, `pe_change`, `modifiers_json` a `game_post_characters`.
2. **`migrate_post_pa_declared.php`** — Añade `pa_declared` (si no existe ya).
3. **`migrate_roll_modifiers.php`** — Añade `roll_modifiers_json` a `game_post_cards` (modificadores de tirada por carta individual).

---

## 7. Hidden Actions (hidden_actions_json)

### 7.1 Definición

Las acciones ocultas permiten a un jugador preparar acciones que no son visibles para otros jugadores (especialmente rivales) hasta que se revelan. Ejemplos:

- Preparar una trampa.
- Esconderse en una posición táctica.
- Activar un dispositivo sigilosamente.
- Preparar un ataque sorpresa.

Mecánicamente, cada acción oculta puede tener cartas asociadas que se juegan "en secreto" y solo se revelan cuando la acción se descubre o ejecuta.

### 7.2 Estructura de hidden_actions_json

```json
[
    {
        "index": 1,
        "description": "Preparar red trampa en la entrada de la cueva",
        "is_revealed": 0
    },
    {
        "index": 2,
        "description": "Ocultarse tras las rocas con fusil preparado",
        "is_revealed": 0
    }
]
```

**Campos:**
- `index` (int): Identificador único dentro del post (1, 2, 3...). Se usa para correlacionar con `hidden_action_index` en `game_post_cards`.
- `description` (string): Texto narrativo que describe la acción. Solo visible para el dueño hasta que se revela.
- `is_revealed` (int): 0 = oculta, 1 = revelada (visible para todos los lectores del post).

### 7.3 Procesamiento en ProcessPostCards

```php
// En ProcessPostCards::execute():
if (!empty($postData['rpg_hidden_actions'])) {
    $hidden_actions = json_decode($postData['rpg_hidden_actions'], true);
    if (is_array($hidden_actions)) {
        $saved_actions = [];
        foreach ($hidden_actions as $action) {
            $action_idx = (int)($action['index'] ?? 0);
            if ($action_idx <= 0) continue;
            
            $description = isset($action['description']) ? trim((string)$action['description']) : '';
            $action_cards = isset($action['cards']) && is_array($action['cards']) ? $action['cards'] : [];
            
            // Procesar cada carta asociada con hidden_action_index
            foreach ($action_cards as $c_entry) {
                game_postcharacter_process_card_entry(
                    $pid, $cid, $c_entry, $stats_for_dice, [], $action_idx, $equipped_ids
                );
            }
            
            $saved_actions[] = [
                'index' => $action_idx,
                'description' => $description,
                'is_revealed' => 0  // Siempre inicia oculta
            ];
        }
        
        // Guardar en la base de datos
        if (!empty($saved_actions) && $this->db->field_exists('hidden_actions_json', 'game_post_characters')) {
            $json_str = json_encode($saved_actions, JSON_UNESCAPED_UNICODE);
            $esc_json = "'" . $this->db->escape_string($json_str) . "'";
            $this->db->write_query("UPDATE {$this->prefix}game_post_characters 
                SET hidden_actions_json = {$esc_json} 
                WHERE post_id = {$pid} AND character_id = {$cid}");
        }
    }
}
```

**Flujo detallado:**

```
1. Frontend envía rpg_hidden_actions = JSON con array de acciones
2. Cada acción tiene index, description, y cards (array de card_ids o entradas)
3. ProcessPostCards::execute() itera cada acción:
   a. Procesa cada carta con game_postcharacter_process_card_entry(... $action_idx)
   b. Las cartas se insertan en game_post_cards con hidden_action_index = action_idx
   c. Guarda el array de acciones en hidden_actions_json
4. Las cartas con hidden_action_index > 0 no aparecen en la lista normal de cartas del post
   hasta que la acción es revelada
```

### 7.4 Visibilidad en cards_for_post.php

El endpoint `cards_for_post.php` controla quién puede ver cada acción oculta:

```php
// Cargar hidden_actions_json desde game_post_characters
$char_q = $db->query("SELECT character_id, hidden_actions_json 
    FROM {$prefix}game_post_characters WHERE post_id = {$post_id} LIMIT 1");
$char_row = $db->fetch_array($char_q);
$decoded = json_decode($char_row['hidden_actions_json'] ?? '[]', true);
$hidden_actions = is_array($decoded) ? $decoded : [];

// Determinar si el viewer es el dueño del post
$is_post_owner_character = ($viewer_char_id > 0 && $viewer_char_id === $post_character_id);

// Filtrar acciones visibles
foreach ($hidden_actions as $act) {
    $idx = (int)($act['index'] ?? 0);
    if ($idx <= 0) continue;
    $revealed = (bool)($act['is_revealed'] ?? false);
    $can_see = ($revealed || $is_post_owner_character);
    
    if ($can_see) {
        $processed_hidden_actions[] = [
            'index' => $idx,
            'description' => $act['description'] ?? '',
            'is_revealed' => $revealed,
            'can_reveal' => ($is_post_owner_character && !$revealed),
            'cards' => [],  // Se llena después con las cartas asociadas
        ];
        $visible_hidden_indexes[$idx] = true;
    }
}
```

**Reglas de visibilidad:**
- **Dueño del post:** Ve todas sus acciones ocultas (reveladas y no reveladas). Puede revelarlas.
- **Otros jugadores:** Solo ven acciones que ya fueron reveladas (`is_revealed = 1`).
- **Staff:** Puede ver todo (porque puede acceder directamente a la BD).

### 7.5 Revelación de Acciones

Una acción oculta se revela cuando el dueño (o el staff) cambia `is_revealed` a 1. Esto se hace mediante un endpoint AJAX (a implementar) que actualiza `hidden_actions_json` en la base de datos.

**Mecanismo de revelación propuesto:**
```php
// Endpoint: game/ajax/reveal_hidden_action.php
$post_id = (int)$_POST['post_id'];
$action_index = (int)$_POST['action_index'];

// Cargar hidden_actions_json actual
$q = $db->query("SELECT hidden_actions_json FROM {$prefix}game_post_characters WHERE post_id = {$post_id} LIMIT 1");
$row = $db->fetch_array($q);
$actions = json_decode($row['hidden_actions_json'] ?? '[]', true);

// Marcar la acción como revelada
foreach ($actions as &$act) {
    if ((int)$act['index'] === $action_index) {
        $act['is_revealed'] = 1;
        break;
    }
}

// Guardar
$json_str = json_encode($actions, JSON_UNESCAPED_UNICODE);
$esc_json = $db->escape_string($json_str);
$db->write_query("UPDATE {$prefix}game_post_characters SET hidden_actions_json = '{$esc_json}' WHERE post_id = {$post_id}");
```

---

## 8. Equipment Snapshot (equipped_snapshot_json)

### 8.1 Definición

El snapshot de equipamiento es una "foto" del inventario equipado del personaje en el momento exacto en que escribe el post. Permite saber, incluso meses después, qué equipo tenía el personaje cuando realizó las acciones del post.

### 8.2 Cómo se Toma

`game_postcharacter_save_equipped_snapshot()` se llama inmediatamente después del INSERT inicial en `game_post_characters`:

```php
// En game_postcharacter_save_post:
$db->write_query("INSERT IGNORE INTO {$prefix}game_post_characters 
    (post_id, user_id, character_id) VALUES ({$pid}, {$uid}, {$cid})");
game_postcharacter_save_equipped_snapshot($pid, $cid);
// ... después se procesan cartas, oráculos, navegación, estado
```

La función de snapshot:
```php
function game_postcharacter_save_equipped_snapshot(int $pid, int $cid): array
{
    game_postcharacter_ensure_inventory_helpers();
    global $db;
    $ids = function_exists('game_get_equipped_card_ids')
        ? game_get_equipped_card_ids($cid)
        : [];
    if (!game_postcharacter_equipped_snapshot_ready()) {
        return $ids;
    }
    $prefix = TABLE_PREFIX;
    $json = json_encode(array_values($ids), JSON_UNESCAPED_UNICODE);
    $esc = $db->escape_string($json);
    $db->write_query(
        "UPDATE {$prefix}game_post_characters
         SET equipped_snapshot_json = '{$esc}'
         WHERE post_id = {$pid} AND character_id = {$cid}"
    );
    return $ids;
}
```

**¿Qué incluye el snapshot?** Cualquier carta que esté equipada en un slot del personaje: armas, armaduras, accesorios, barcos, herramientas. El criterio es `game_get_equipped_card_ids()` que devuelve los IDs de cartas en slots equipados.

### 8.3 Cómo se Consulta

`game_postcharacter_get_post_equipped_ids()` recupera el snapshot o cae al equipamiento actual:

```php
function game_postcharacter_get_post_equipped_ids(int $pid, int $cid): array
{
    game_postcharacter_ensure_inventory_helpers();
    global $db;
    $prefix = TABLE_PREFIX;
    if (game_postcharacter_equipped_snapshot_ready()) {
        $q = $db->query(
            "SELECT equipped_snapshot_json FROM {$prefix}game_post_characters
             WHERE post_id = {$pid} AND character_id = {$cid} LIMIT 1"
        );
        $row = $db->fetch_array($q);
        if ($row && ($row['equipped_snapshot_json'] ?? '') !== '') {
            $decoded = json_decode($row['equipped_snapshot_json'], true);
            if (is_array($decoded)) {
                return array_values(array_unique(array_map('intval', $decoded)));
            }
        }
    }
    // Fallback: equipamiento actual del personaje
    return function_exists('game_get_equipped_card_ids') 
        ? game_get_equipped_card_ids($cid) 
        : [];
}
```

### 8.4 Validación de Cartas vs Snapshot

Antes de procesar una carta jugada, se verifica que esté permitida según el snapshot:

```php
function game_postcharacter_card_allowed_in_post(
    string $cardType, 
    int $cardId, 
    array $equippedIds, 
    bool $isConsumible = false
): bool {
    game_postcharacter_ensure_inventory_helpers();
    // Si la carta no requiere slot equipado, siempre permitida
    if (!function_exists('game_card_requires_equipped_slot') || 
        !game_card_requires_equipped_slot($cardType, $isConsumible)) {
        return true;
    }
    // Verificar que la carta esté en el snapshot (o equipamiento actual)
    $allowed = in_array($cardId, $equippedIds, true);
    if (!$allowed && function_exists('game_log_equipped_debug')) {
        game_log_equipped_debug('card_rejected', [
            'card_id' => $cardId,
            'card_type' => $cardType,
            'equipped_ids' => $equippedIds,
        ]);
    }
    return $allowed;
}
```

**¿Qué cartas requieren slot equipado?**
- Armas (tipo `equipo` con `equipo_type = arma`)
- Armaduras
- Barcos (tipo `barco`)
- No aplica a: consumibles, habilidades, técnicas, NPCs menores.

**Debug:** Si una carta es rechazada por no estar equipada, se registra en el log de debug (`game_log_equipped_debug`).

---

## 9. Post RPG Debug

El sistema incluye un módulo de depuración para rastrear el procesamiento de posts RPG. Está en `game/inc/post_rpg_debug.php`.

### 9.1 game_post_rpg_debug_enabled()

```php
function game_post_rpg_debug_enabled(): bool
{
    if (defined('GAME_DEBUG') && GAME_DEBUG) return true;
    if (defined('GAME_LOG_POST_RPG') && GAME_LOG_POST_RPG) return true;
    return (int)($_GET['debug_post_rpg'] ?? $_POST['debug_post_rpg'] ?? 0) === 1;
}
```

**Modos de activación:**
1. Constante global `GAME_DEBUG = true` — debug completo.
2. Constante global `GAME_LOG_POST_RPG = true` — solo log RPG.
3. Parámetro `?debug_post_rpg=1` en URL — debug por request.
4. Parámetro `debug_post_rpg=1` en POST — debug en formularios.

### 9.2 game_post_rpg_modifiers_ready()

```php
function game_post_rpg_modifiers_ready(): bool
{
    global $db;
    static $ready = null;
    if ($ready !== null) return $ready;
    $ready = $db->table_exists('game_post_characters')
        && $db->field_exists('pv_change', 'game_post_characters')
        && $db->field_exists('pe_change', 'game_post_characters')
        && $db->field_exists('modifiers_json', 'game_post_characters');
    return $ready;
}
```

Esta función verifica que las columnas necesarias para el sistema de modificadores existan. Si la migración no se ha ejecutado, el sistema funciona sin modificadores (graceful degradation).

### 9.3 game_log_post_rpg()

```php
function game_log_post_rpg(string $event, array $context = []): void
{
    if (!game_post_rpg_debug_enabled()) return;
    
    $payload = array_merge(['event' => $event, 'ts' => date('c')], $context);
    $line = json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir . '/post_rpg_debug.log', $line, FILE_APPEND | LOCK_EX);
    error_log('[game_post_rpg] ' . trim($line));
}
```

**Eventos registrados:**

| Evento | Dónde se dispara | Contexto |
|--------|-----------------|----------|
| `modifiers_saved` | `game_postcharacter_save_post_modifiers` | post_id, character_id, pv_change, pe_change, stat_mods |
| `modifiers_skip_columns` | `game_postcharacter_save_post_modifiers` | post_id (cuando faltan columnas) |
| `save_post_done` | `game_postcharacter_save_post` | post_id, character_id, had_cards, had_hidden, had_oracles, had_modifiers, modifiers_ready, hidden_col |
| `save_thread_done` | `game_postcharacter_save_thread` | post_id, thread_id, character_id, had_cards, had_hidden, had_oracles, had_modifiers |
| `cards_for_post` | `cards_for_post.php` | post_id, tables, columns, post_character_id, viewer_char_id, is_owner, counts |
| `cards_for_post_error` | `cards_for_post.php` (catch) | post_id, message, file, line |
| `process_cards` | `ProcessPostCards::execute` | post_id, character_id, equipped_ids, has_played_cards, has_hidden_actions |
| `card_rejected` | `game_postcharacter_card_allowed_in_post` | card_id, card_type, equipped_ids, is_consumible |

### 9.4 Debug en cards_for_post.php

Cuando el debug está activo, `cards_for_post.php` incluye un campo `_debug` en la respuesta JSON:

```json
{
    "ok": true,
    "data": [...],
    "_debug": {
        "post_id": 12345,
        "tables": {
            "game_post_characters": true,
            "game_post_cards": true,
            "game_post_oracles": true,
            "game_oracles": true
        },
        "columns": {
            "hidden_actions_json": true,
            "hidden_action_index": true,
            "modifiers_json": true,
            "roll_modifiers_json": true
        },
        "post_character_id": 42,
        "viewer_char_id": 42,
        "is_owner": true,
        "counts": {
            "cards": 3,
            "hidden_actions": 1,
            "oracles": 0,
            "pv_change": -15,
            "pe_change": -5,
            "stat_mods": 2
        }
    }
}
```

---

## 10. Integración con Navigation Processing

### 10.1 game_navigation_process_post

Cuando un post se crea en un hilo de tipo `Presente`, el jugador puede iniciar un viaje de navegación. La función `game_navigation_process_post()` en `navigation_process.php` se encarga de esto.

**Disparo desde el plugin:**
```php
// En game_postcharacter_save_post:
if (function_exists('game_navigation_process_post') && isset($dh->data['tid'])) {
    game_navigation_process_post($pid, (int)$dh->data['tid'], $cid, $_POST);
}
```

**Condiciones para que se inicie un viaje:**
1. `$_POST['rpg_nav_enabled'] === '1'`.
2. El hilo es de tipo `Presente` (verificado en `game_thread_meta`).
3. Se especificó `rpg_nav_destination` (isla destino) y `rpg_nav_ship` (barco).
4. La isla destino existe en `game_forum_islands`.
5. El barco está equipado en el slot `barco` del personaje.
6. La isla origen (determinada por el `fid` del foro donde está el hilo) es diferente de la destino.

**Resultado:** Se inserta una fila en `game_navigation_voyages` y se generan eventos de navegación (oráculos).

### 10.2 game_navigation_voyage_for_post

Esta función recupera los datos del viaje asociado a un post para incluirlos en la respuesta de `cards_for_post.php`:

```php
function game_navigation_voyage_for_post(int $postId): ?array
{
    global $db;
    if (!$db->table_exists('game_navigation_voyages') || $postId <= 0) return null;
    
    $prefix = TABLE_PREFIX;
    $voyage = $db->fetch_array($db->query(
        "SELECT * FROM {$prefix}game_navigation_voyages WHERE post_id = " . (int)$postId . " LIMIT 1"
    ));
    if (!$voyage) return null;
    
    // ... carga eventos, islas, barco ...
    
    return [
        'id' => (int)$voyage['id'],
        'island_from' => ...,
        'island_to' => ...,
        'ship' => ...,
        'distance' => ...,
        'danger_level' => ...,
        'duration_days' => ...,
        'num_events' => ...,
        'events' => [...],
        'navigation_post_oracle_ids' => $navPostOracleIds,
    ];
}
```

### 10.3 Filtrado de Oráculos de Navegación

En `cards_for_post.php`, los oráculos de navegación se filtran de la lista de oráculos normales para evitar duplicados:

```php
if ($voyage && !empty($voyage['navigation_post_oracle_ids'])) {
    $navIds = array_flip($voyage['navigation_post_oracle_ids']);
    $oracles = array_values(array_filter($oracles, static function ($o) use ($navIds) {
        return !isset($navIds[(int)($o['id'] ?? 0)]);
    }));
}
```

Esto asegura que los oráculos generados por la navegación no aparezcan dos veces: una vez como parte del viaje y otra como oráculos independientes del post.

---

## 11. Plugin MyBB game_postcharacter.php

### 11.1 Hooks

```php
$plugins->add_hook('datahandler_post_insert_post_end', 'game_postcharacter_save_post');
$plugins->add_hook('datahandler_post_insert_thread_end', 'game_postcharacter_save_thread');
$plugins->add_hook('class_moderation_delete_post_start', 'game_postcharacter_delete_post');
$plugins->add_hook('class_moderation_delete_thread_start', 'game_postcharacter_delete_thread');
$plugins->add_hook('global_start', 'game_postcharacter_global_date');
$plugins->add_hook('global_start', 'game_postcharacter_set_template_vars');
$plugins->add_hook('editpost_start', 'game_postcharacter_block_edit');
$plugins->add_hook('xmlhttp_edit_post_start', 'game_postcharacter_block_ajax_edit');
$plugins->add_hook('parse_message', 'game_postcharacter_parse_spoiler_bbcode');
```

| Hook | Función | Disparo |
|------|---------|---------|
| `datahandler_post_insert_post_end` | `game_postcharacter_save_post` | Después de insertar un post en hilo existente |
| `datahandler_post_insert_thread_end` | `game_postcharacter_save_thread` | Después de insertar un nuevo hilo |
| `class_moderation_delete_post_start` | `game_postcharacter_delete_post` | Antes de borrar un post |
| `class_moderation_delete_thread_start` | `game_postcharacter_delete_thread` | Antes de borrar un hilo |
| `global_start` | `game_postcharacter_global_date` | En cada página (fecha del mundo RPG) |
| `global_start` | `game_postcharacter_set_template_vars` | Variables de template para el editor de posts |
| `editpost_start` | `game_postcharacter_block_edit` | Bloquea edición de posts con tiradas |
| `xmlhttp_edit_post_start` | `game_postcharacter_block_ajax_edit` | Bloquea edición AJAX |
| `parse_message` | `game_postcharacter_parse_spoiler_bbcode` | Parsea BBCode [spoiler] |

### 11.2 game_postcharacter_save_post

Función principal que procesa un post en un hilo existente. Flujo completo:

```php
function game_postcharacter_save_post($dh) {
    // 1. Validar que exista pid, uid
    if (!isset($dh->pid) || !isset($dh->data['uid'])) return;
    $pid = (int)$dh->pid;
    $uid = (int)$dh->data['uid'];
    if ($uid <= 0) return;
    
    // 2. Obtener personaje activo del usuario
    $cfg = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
    $row = $db->fetch_array($cfg);
    if (!$row || !$row['active_pj_id']) return;
    $cid = (int)$row['active_pj_id'];
    
    // 3. Insertar en game_post_characters
    $db->write_query("INSERT IGNORE INTO {$prefix}game_post_characters (post_id, user_id, character_id) VALUES ({$pid}, {$uid}, {$cid})");
    
    // 4. Tomar snapshot de equipamiento
    game_postcharacter_save_equipped_snapshot($pid, $cid);
    
    // 5. Incrementar contador de posts del personaje
    $db->write_query("UPDATE {$prefix}game_personajes SET postnum = postnum + 1 WHERE id = {$cid}");
    
    // 6. Incrementar contador de misiones si aplica
    if (isset($dh->data['tid']) && (int)$dh->data['tid'] > 0) {
        $db->write_query("UPDATE {$prefix}game_missions_active SET post_count = post_count + 1 WHERE tid = " . (int)$dh->data['tid'] . " AND status = 'active'");
    }
    
    // 7. Notificar al autor del hilo
    if (isset($dh->data['tid']) && (int)$dh->data['tid'] > 0) {
        $tid = (int)$dh->data['tid'];
        // ... notificación si el autor del hilo no es quien responde
    }
    
    // 8. Procesar cartas jugadas
    game_postcharacter_process_cards($pid, $cid);
    
    // 9. Procesar oráculos
    game_postcharacter_process_oracles($pid, $cid);
    
    // 10. Procesar navegación (si aplica)
    if (function_exists('game_navigation_process_post') && isset($dh->data['tid'])) {
        game_navigation_process_post($pid, (int)$dh->data['tid'], $cid, $_POST);
    }
    
    // 11. Guardar estado del hilo (PV/PE + modificadores)
    if (isset($dh->data['tid']) && (int)$dh->data['tid'] > 0) {
        game_postcharacter_save_thread_state((int)$dh->data['tid'], $cid, $pid);
    }
    
    // 12. Asignar PP por palabras
    game_postcharacter_award_pp($pid, $cid, $dh->data['message'] ?? '', (int)($dh->data['tid'] ?? 0));
}
```

### 11.3 game_postcharacter_save_thread

Similar a `save_post` pero para nuevos hilos. Diferencias clave:
- Incluye `thread_id` en el INSERT inicial.
- Incrementa tanto `postnum` como `threadnum`.
- Guarda metadatos del hilo (tipo, fecha in-game) en `game_thread_meta`.

### 11.4 game_postcharacter_save_thread_state

Esta función orquesta el guardado del estado del hilo:

```php
function game_postcharacter_save_thread_state(int $tid, int $cid, int $pid): void
{
    global $db;
    if ($tid <= 0 || $cid <= 0 || $pid <= 0) return;
    
    // 1. Guardar modificadores del post (pv_change, pe_change, modifiers_json)
    game_postcharacter_save_post_modifiers($tid, $cid, $pid);
    
    // 2. Si no hay input de modificadores, no actualizar estado de hilo
    if (!game_postcharacter_has_post_modifier_input()) return;
    if (!$db->table_exists('game_thread_pj_state')) return;
    if (!game_postcharacter_post_modifiers_ready()) return;
    
    // 3. Calcular y guardar estado actual en game_thread_pj_state
    $prefix = TABLE_PREFIX;
    $computed = game_postcharacter_compute_post_modifiers($tid, $cid);
    $mods_esc = $db->escape_string($computed['stat_mods_json']);
    $current_pv = (int)$computed['current_pv'];
    $current_pe = (int)$computed['current_pe'];
    
    $db->write_query("
        INSERT INTO {$prefix}game_thread_pj_state (thread_id, character_id, current_pv, current_pe, stat_mods_json, last_post_id)
        VALUES ({$tid}, {$cid}, {$current_pv}, {$current_pe}, '{$mods_esc}', {$pid})
        ON DUPLICATE KEY UPDATE
            current_pv = {$current_pv},
            current_pe = {$current_pe},
            stat_mods_json = '{$mods_esc}',
            last_post_id = {$pid}
    ");
}
```

### 11.5 game_postcharacter_process_cards

Delega en `ProcessPostCards` para procesar las cartas jugadas y las acciones ocultas:

```php
function game_postcharacter_process_cards($pid, $cid) {
    global $db;
    require_once __DIR__ . '/../../game/src/Application/UseCases/ProcessPostCards.php';
    $useCase = new \Game\Application\UseCases\ProcessPostCards($db, TABLE_PREFIX);
    $useCase->execute((int)$pid, (int)$cid, $_POST);
}
```

### 11.6 game_postcharacter_process_oracles

Delega en `ProcessPostOracles`:

```php
function game_postcharacter_process_oracles(int $pid, int $cid): void
{
    if (empty($_POST['rpg_oracles'])) return;
    global $db;
    require_once __DIR__ . '/../../game/src/Application/UseCases/ProcessPostOracles.php';
    $useCase = new \Game\Application\UseCases\ProcessPostOracles($db, TABLE_PREFIX);
    $useCase->execute($pid, $cid, (string)$_POST['rpg_oracles']);
}
```

### 11.7 Bloqueo de Edición

Una vez que un post tiene tiradas de dados, cartas jugadas, oráculos o viajes de navegación, no puede editarse:

```php
function game_postcharacter_has_rolls(int $pid): bool {
    global $db;
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT id FROM {$prefix}game_post_cards WHERE post_id = {$pid} AND roll_result != '' LIMIT 1");
    if ($db->num_rows($q) > 0) return true;
    if ($db->table_exists('game_post_oracles')) {
        $oq = $db->query("SELECT id FROM {$prefix}game_post_oracles WHERE post_id = {$pid} LIMIT 1");
        if ($db->num_rows($oq) > 0) return true;
    }
    if ($db->table_exists('game_navigation_voyages')) {
        $vq = $db->query("SELECT id FROM {$prefix}game_navigation_voyages WHERE post_id = {$pid} LIMIT 1");
        if ($db->num_rows($vq) > 0) return true;
    }
    return false;
}
```

### 11.8 Eliminación de Posts

Al eliminar un post, se decrementa `postnum` del personaje:

```php
function game_postcharacter_delete_post($pid) {
    global $db;
    $prefix = TABLE_PREFIX;
    $pid = (int)$pid;
    if ($pid <= 0) return $pid;
    
    $query = $db->query("SELECT character_id FROM {$prefix}game_post_characters WHERE post_id = {$pid} LIMIT 1");
    $row = $db->fetch_array($query);
    if ($row && $row['character_id']) {
        $cid = (int)$row['character_id'];
        $db->write_query("UPDATE {$prefix}game_personajes SET postnum = GREATEST(0, postnum - 1) WHERE id = {$cid}");
    }
    return $pid;
}
```

Al eliminar un hilo, se decrementan `postnum` de todos los personajes que participaron y `threadnum` del autor:

```php
function game_postcharacter_delete_thread($tid) {
    global $db;
    $prefix = TABLE_PREFIX;
    $tid = (int)$tid;
    if ($tid <= 0) return $tid;
    
    // Decrementar threadnum del autor
    $q_thread = $db->query("SELECT character_id FROM {$prefix}game_post_characters WHERE thread_id = {$tid} LIMIT 1");
    $author = $db->fetch_array($q_thread);
    if ($author && $author['character_id']) {
        $cid = (int)$author['character_id'];
        $db->write_query("UPDATE {$prefix}game_personajes SET threadnum = GREATEST(0, threadnum - 1) WHERE id = {$cid}");
    }
    
    // Decrementar postnum de todos los participantes
    $q_posts = $db->query("
        SELECT gpc.character_id, COUNT(*) as post_count
        FROM {$prefix}posts p
        JOIN {$prefix}game_post_characters gpc ON p.pid = gpc.post_id
        WHERE p.tid = {$tid}
        GROUP BY gpc.character_id
    ");
    while ($r = $db->fetch_array($q_posts)) {
        $cid = (int)$r['character_id'];
        $count = (int)$r['post_count'];
        $db->write_query("UPDATE {$prefix}game_personajes SET postnum = GREATEST(0, postnum - {$count}) WHERE id = {$cid}");
    }
    
    return $tid;
}
```

### 11.9 Asignación de PP

Cada post otorga PP (Puntos de Progresión) basados en el conteo de palabras:

```php
function game_postcharacter_award_pp(int $pid, int $cid, string $message, int $tid): void
{
    global $db;
    $prefix = TABLE_PREFIX;
    
    static $awarded_pids = [];
    if (isset($awarded_pids[$pid])) return;  // Evitar duplicados
    $awarded_pids[$pid] = true;
    
    // No otorgar PP en hilos Off_Rol
    $is_off_rol = false;
    if ($tid > 0) {
        $meta_q = $db->simple_select('game_thread_meta', 'thread_type', "thread_id = {$tid}", ['limit' => 1]);
        if ($meta = $db->fetch_array($meta_q)) {
            if ($meta['thread_type'] === 'Off_Rol') $is_off_rol = true;
        }
    }
    if ($is_off_rol) return;
    
    $word_count = game_postcharacter_count_words($message);
    if ($word_count <= 0) return;
    
    $pp_earned = intdiv($word_count, \Game\Shared\StatScale::WORDS_PER_PP);
    if ($pp_earned <= 0) return;
    
    // Actualizar data_json.pp del personaje
    $pj_q = $db->simple_select('game_personajes', 'data_json', "id = {$cid}", ['limit' => 1]);
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        $data = json_decode($pj['data_json'] ?? '{}', true);
        $data['pp'] = (int)($data['pp'] ?? 0) + $pp_earned;
        \Game\Application\Services\CharacterProgression::normalize($data);
        $data_json_esc = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
        $db->write_query("UPDATE {$prefix}game_personajes SET data_json = '{$data_json_esc}' WHERE id = {$cid}");
    }
}
```

**WORDS_PER_PP:** Constante definida en `StatScale` que determina cuántas palabras se necesitan para 1 PP.

### 11.10 Bloqueo de Edición por Tiradas

Si un post tiene tiradas (cartas, oráculos, viajes), se bloquea la edición tanto en la interfaz normal como en AJAX:

```php
function game_postcharacter_block_edit() {
    global $mybb;
    $pid = (int)($mybb->get_input('pid', MyBB::INPUT_INT));
    if ($pid > 0 && game_postcharacter_has_rolls($pid)) {
        error("Este mensaje contiene tiradas de dados u oráculos y no puede ser editado.");
    }
}

function game_postcharacter_block_ajax_edit() {
    global $mybb;
    $pid = (int)($mybb->get_input('pid', MyBB::INPUT_INT));
    if ($pid > 0 && game_postcharacter_has_rolls($pid)) {
        xmlhttp_error("Este mensaje contiene tiradas de dados u oráculos y no puede ser editado.");
    }
}
```

---

## 12. AJAX Endpoints

### 12.1 cards_for_post.php

**Ruta:** `game/ajax/cards_for_post.php`
**Método:** GET
**Parámetros:** `post_id` (int)
**Respuesta:** JSON con datos RPG del post.

```json
{
    "ok": true,
    "data": [
        {
            "id": 1,
            "card_id": 42,
            "name": "Golpe de Espada",
            "rank": "B",
            "card_type": "tecnica",
            "dice": "3d6+fue",
            "roll_result": "3d6 (4 + 2 + 6) + 8 = 20",
            "tags": ["ataque", "cuerpo_a_cuerpo"],
            "effects": {"dano": "fisico"},
            "execution_cost": 3,
            "is_modified": false
        }
    ],
    "modifications": {
        "pv_change": -15,
        "pe_change": -5,
        "stat_mods": {"fue": 5, "agi": -2}
    },
    "hidden_actions": [
        {
            "index": 1,
            "description": "Trampa preparada",
            "is_revealed": false,
            "can_reveal": true,
            "cards": []
        }
    ],
    "oracles": [],
    "voyage": null,
    "_debug": {} // Solo si debug_post_rpg=1
}
```

**Flujo interno:**
1. Valida `post_id > 0`.
2. Carga `game_post_characters` para obtener `character_id` y `hidden_actions_json`.
3. Determina si el viewer es el dueño del post.
4. Filtra acciones ocultas según visibilidad.
5. Carga cartas de `game_post_cards` con JOIN a `game_cards`.
6. Separa cartas normales vs cartas de acciones ocultas.
7. Carga modificadores (pv_change, pe_change, stat_mods desde `modifiers_json`).
8. Carga oráculos desde `game_post_oracles`.
9. Carga viaje de navegación (si existe).
10. Filtra oráculos de navegación para evitar duplicados.
11. Devuelve respuesta JSON.

### 12.2 thread_pj_state.php

**Ruta:** `game/ajax/thread_pj_state.php`
**Método:** GET
**Parámetros:** `thread_id` (int), `character_id` (int, opcional — usa personaje activo por defecto)
**Respuesta:** JSON con el estado actual del personaje en el hilo.

```json
{
    "ok": true,
    "data": {
        "thread_id": 123,
        "character_id": 42,
        "current_pv": 75,
        "current_pe": 42,
        "max_pv": 100,
        "max_pe": 60,
        "max_pa": 23,
        "stat_mods": {},
        "stats_ranks": {"fue": 3, "res": 2, "agi": 4},
        "stats_display": {"fue": 15, "res": 8, "agi": 26}
    }
}
```

**Detalles del cálculo:**
- `max_pv` y `max_pe`: calculados desde stats + raza mediante `game_compute_pv_pe_from_context()`.
- `max_pa`: `10 + intdiv(agi_valor, 2) + modificadores_pa_raza + modificadores_pa_linaje`.
- `current_pv`, `current_pe`: desde `game_thread_pj_state` si existe; si no, igual a máximos.
- `stat_mods`: desde `game_thread_pj_state.stat_mods_json` (modificadores acumulados del hilo).

---

## 13. Migraciones SQL

### 13.1 migrate_pj_system.php

**Propósito:** Migración inicial del sistema de personajes. Crea tablas base y datos de ejemplo.

**Acciones:**
1. Añade `user_id` a `game_personajes`.
2. Añade `avatar` a `game_personajes`.
3. Crea `game_user_config`.
4. Añade `is_staff` a `game_personajes`.
5. Crea personajes admin (Imu, Kazan).
6. **Crea `game_post_characters`** (versión inicial sin columnas de modificadores).
7. Añade `thread_id` a `game_post_characters` si no existe.
8. Añade `postnum` y `threadnum` a `game_personajes`.
9. Recalcula contadores de posts y temas.

**Esquema inicial de `game_post_characters` en esta migración:**
```sql
CREATE TABLE IF NOT EXISTS mybb_game_post_characters (
    post_id INT PRIMARY KEY,
    thread_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    character_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_thread_id (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Las columnas de modificadores se añaden en migraciones posteriores.

### 13.2 migrate_post_modifiers.php

**Propósito:** Añadir las columnas de modificadores por post.

```php
if (!$db->field_exists('pv_change', 'game_post_characters'))
    → ALTER TABLE ADD pv_change INT NOT NULL DEFAULT 0

if (!$db->field_exists('pe_change', 'game_post_characters'))
    → ALTER TABLE ADD pe_change INT NOT NULL DEFAULT 0

if (!$db->field_exists('modifiers_json', 'game_post_characters'))
    → ALTER TABLE ADD modifiers_json TEXT DEFAULT NULL
```

**Orden de ejecución recomendado:** Después de `migrate_pj_system.php`.

### 13.3 migrate_post_pa_declared.php

**Propósito:** Añadir la columna `pa_declared`.

```php
ALTER TABLE mybb_game_post_characters 
ADD COLUMN pa_declared TINYINT UNSIGNED NOT NULL DEFAULT 0 
COMMENT 'PA declarado gastado por el jugador en este post (referencia para staff, no validación automática)'
AFTER pe_change
```

**Nota:** Se posiciona `AFTER pe_change` para mantener un orden lógico de columnas: primero PV/PE, luego PA.

### 13.4 migrate_post_equipped_snapshot.php

**Propósito:** Añadir la columna `equipped_snapshot_json`.

```php
if (!$db->field_exists('equipped_snapshot_json', 'game_post_characters')) {
    if ($db->field_exists('hidden_actions_json', 'game_post_characters')) {
        → ALTER TABLE ADD equipped_snapshot_json TEXT DEFAULT NULL AFTER hidden_actions_json
    } else {
        → ALTER TABLE ADD equipped_snapshot_json TEXT DEFAULT NULL
    }
}
```

Se posiciona `AFTER hidden_actions_json` si esa columna existe.

### 13.5 migrate_roll_modifiers.php

**Propósito:** Añadir `roll_modifiers_json` a `game_post_cards` (no a `game_post_characters`).

```php
if (!$db->field_exists('roll_modifiers_json', 'game_post_cards')) {
    $db->write_query("ALTER TABLE {$table} ADD roll_modifiers_json TEXT DEFAULT NULL");
}
```

---

## 14. Flujo Completo de un Post

### 14.1 Post en Hilo Existente

```
1. Jugador hace clic en "Responder" en showthread.php
2. Se carga el editor con el personaje activo seleccionado
3. JS del editor:
   a. Carga thread_pj_state.php → obtiene current_pv, current_pe, max_pa
   b. Muestra controles de PV/PE (inputs ocultos)
   c. Muestra selector de cartas con costes de PA
   d. Muestra opciones de acciones ocultas (si aplica)
   e. Muestra opciones de navegación (si hilo Presente)
   f. Muestra opciones de oráculos (si aplica)
4. Jugador escribe narrativa, selecciona cartas, ajusta PV/PE
5. JS calcula:
   - coste_total_PA = suma(coste_pa de cartas seleccionadas)
   - rpg_modifiers = {fue: X, agi: Y, ...} (si aplica)
   - rpg_hidden_actions = [...] (si aplica)
   - rpg_thread_pv = valor actual de PV
   - rpg_thread_pe = valor actual de PE
6. Jugador envía el formulario
7. MyBB:
   a. Inserta post en mybb_posts
   b. Dispara datahandler_post_insert_post_end
8. Plugin game_postcharacter.php:
   a. game_postcharacter_save_post:
      1. INSERT IGNORE en game_post_characters
      2. game_postcharacter_save_equipped_snapshot
      3. UPDATE game_personajes SET postnum = postnum + 1
      4. Notificación al autor del hilo
      5. game_postcharacter_process_cards
         → ProcessPostCards::execute
           - Procesa cartas normales → INSERT game_post_cards
           - Procesa hidden actions → INSERT game_post_cards + UPDATE hidden_actions_json
      6. game_postcharacter_process_oracles
         → ProcessPostOracles::execute
      7. game_navigation_process_post (si aplica)
      8. game_postcharacter_save_thread_state
         → game_postcharacter_save_post_modifiers
           - Calcula pv_change, pe_change, stat_mods
           - UPDATE game_post_characters SET pv_change, pe_change, modifiers_json
         → UPSERT game_thread_pj_state
      9. game_postcharacter_award_pp
9. Post visible → JS en frontend carga cards_for_post.php
   para mostrar tiradas, modificadores, hidden actions
```

### 14.2 Post como Nuevo Hilo

```
1. Jugador hace clic en "Nuevo Tema"
2. Selecciona el tipo de hilo (Pasado, Presente, Mision, etc.)
3. Si Presente: la fecha in-game se calcula automáticamente
4. Jugador completa el post (similar a 14.1 pasos 3-6)
5. MyBB:
   a. Inserta hilo en mybb_threads
   b. Inserta post en mybb_posts
   c. Dispara datahandler_post_insert_thread_end
6. Plugin game_postcharacter.php:
   a. game_postcharacter_save_thread:
      1. INSERT con thread_id en game_post_characters
      2. Snapshot, incremento de postnum + threadnum
      3. Guarda game_thread_meta (tipo, fecha)
      4. ProcessPostCards, ProcessPostOracles
      5. Navegación (solo si Presente y aplica)
      6. game_postcharacter_save_thread_state
      7. Asignación de PP
```

---

## 15. Filosofía de Diseño

### 15.1 ¿Por Qué game_post_characters es Plana?

La tabla `game_post_characters` tiene todas las columnas en una sola tabla, sin normalizar en tablas separadas (pv_changes aparte, modifiers aparte, etc.).

**Razones:**
- **Un solo query para cargar todo:** Al leer un post, se necesita character_id, pv_change, pe_change, modifiers, hidden actions, y snapshot. Una sola fila = un solo query.
- **Integridad transaccional:** Todos los datos del post se insertan/actualizan juntos. No hay riesgo de que una tabla secundaria quede desactualizada.
- **Simplicidad de migraciones:** Añadir una columna es más simple que crear una nueva tabla.
- **Cada post es una unidad atómica:** No hay relaciones 1:N desde `game_post_characters` excepto hacia `game_post_cards` (que es la tabla de cartas, que sí es 1:N).

**Trade-off:** Columnas TEXT (JSON) no son consultables fácilmente con SQL. Pero no se necesita consultar "todos los posts con fue > 5" — para eso están `game_post_cards` y otras tablas.

### 15.2 ¿Por Qué PV/PE se Persisten y PA No?

| Recurso | Persistencia | Razón |
|---------|-------------|-------|
| **PV** | Por hilo (`game_thread_pj_state.current_pv`) | El daño se acumula entre posts. Un personaje herido sigue herido hasta que cure. |
| **PE** | Por hilo (`game_thread_pj_state.current_pe`) | La energía gastada en técnicas no se recupera instantáneamente. |
| **PA** | Por post (`game_post_characters.pa_declared`) | El PA es un recurso de acción que se "refresca" cada post. No arrastras gasto de posts anteriores. |

**¿Por qué no persistir PA como PV/PE?**
- **Ritmo de juego:** Cada post nuevo, el personaje recupera su capacidad de acción. Esto incentiva participación activa.
- **Simplicidad:** No hay que trackear regeneración de PA entre posts.
- **Diferenciación de PE:** PE es persistente y se regenera lentamente. PA es volátil. Son dos recursos con roles distintos.

### 15.3 ¿Por Qué el Staff Valida PA?

El PA declarado no se valida automáticamente. Esta es una decisión fundamental.

**Razones:**
1. **Confianza sobre control:** El foro opera bajo un modelo de confianza. El jugador declara su gasto, el staff verifica después.
2. **Revisión holística:** El staff revisa el hilo completo, no post por post. Un validador automático no entendería contexto narrativo.
3. **Flexibilidad:** Un post climático puede justificar más PA que uno de transición. El staff puede aprobar excepciones.
4. **Carga técnica mínima:** No implementar un validador en tiempo real simplifica el backend radicalmente.

### 15.4 ¿Por Qué Snapshot de Equipamiento?

El snapshot resuelve un problema específico: **¿qué equipamiento tenía el personaje cuando escribió este post?**

Sin snapshot, si un personaje vendía su espada después de un post de combate, el registro histórico perdía esa información. Con el snapshot:
- Se preserva el contexto histórico del post.
- Se validan cartas jugadas contra el equipamiento de ese momento.
- Se permite auditoría: "¿este personaje tenía el requisito para usar esa carta?"

### 15.5 ¿Por Qué Acciones Ocultas?

Las acciones ocultas permiten estrategia y sorpresa en el combate narrativo. Sin ellas, todos los movimientos serían visibles para todos los jugadores, eliminando la posibilidad de:
- Trampas y emboscadas.
- Preparativos sigilosos.
- Coordinación secreta entre aliados.
- Engaños tácticos.

El sistema está diseñado para que el dueño del post pueda revelar la acción en el momento oportuno, y hasta entonces solo él (y el staff) conocen los detalles.

### 15.6 ¿Por Qué No se Puede Editar un Post con Tiradas?

Una vez que un post tiene cartas jugadas, oráculos ejecutados, o viajes iniciados, no puede editarse.

**Razones:**
- **Integridad de las tiradas:** Si un jugador editara un post, podría cambiar el resultado de una tirada (modificando la narrativa).
- **Fairness para el rival:** El rival ya vio las cartas jugadas y tomó decisiones basadas en eso.
- **Auditoría:** El staff necesita poder confiar en que el post no cambió después de ser revisado.
- **Consumibles:** Las cartas consumibles ya fueron decrementadas del inventario. Una edición no podría revertir eso limpiamente.

**Excepción:** Un post sin tiradas (puramente narrativo) puede editarse normalmente.

---

## 16. Consejos para Jugadores

### 16.1 Gestionando tu PA

- **Calcula tu PA máximo:** Conoce tu AGI y modificadores. Usa `thread_pj_state.php` para ver tu `max_pa` en cada hilo.
- **Presupuesta antes de postear:** Si tu PA máximo es 23, no selecciones cartas que sumen 30.
- **Declara PA incluso si no usas cartas:** Si haces una acción narrativa que consume esfuerzo, declarar 1-2 PA le da contexto al staff.
- **No gastes todo en un solo post:** A menos que sea un momento climático. Guarda PA para reaccionar a lo que hagan otros.

### 16.2 Usando Modificadores

- Los modificadores representan condiciones del entorno o buffs temporales. Si estás en terreno pantanoso, espera un malus a AGI.
- Si un aliado te da un buff narrativo (ej: "te cubro la retaguardia"), puedes reflejarlo como modificador.
- No abuses: un personaje no tiene +5 a todos los stats todo el tiempo.

### 16.3 Acciones Ocultas

- Úsalas para preparar tácticas sorpresa. Una trampa bien preparada puede cambiar el rumbo de un combate.
- Describe la acción con suficiente detalle para que el staff pueda evaluarla después.
- No abuses: no puedes tener 10 acciones ocultas en un solo post.
- Coordina con aliados: si dos personas preparan una emboscada combinada, pueden coordinarse fuera del hilo y ejecutarla en el momento adecuado.

### 16.4 Equipamiento

- Asegúrate de tener tu equipo correcto antes de postear. El snapshot toma el equipamiento actual.
- Si cambias de equipo (ej: armadura diferente para un combate específico), hazlo antes de escribir el post.
- Verifica que las cartas que juegas estén equipadas. Si tu espada no está en el slot de arma, la carta será rechazada.

### 16.5 PV y PE

- Lleva un registro mental de tu PV/PE actual en el hilo.
- Si recibiste daño en el post anterior, asegúrate de reflejarlo en el siguiente.
- Si curaste o regeneraste PE, indica el nuevo valor.
- Si no estás seguro de tu PV/PE actual, usa `thread_pj_state.php` para consultarlo.
- El staff usará `pv_change` y `pe_change` para verificar coherencia entre posts consecutivos.

### 16.6 Evita Editar Posts con Tiradas

- Revisa tu post antes de enviarlo. Una vez con tiradas, no podrás editarlo.
- Si cometiste un error narrativo (no mecánico), puedes pedir al staff que agregue una nota al post.
- Los errores mecánicos (carta incorrecta, PA mal calculado) se resuelven en la revisión de staff, no editando el post.

---

## 17. Consejos para Staff

### 17.1 Revisando PV/PE por Hilo

- Verifica que `current_pv` y `current_pe` en `game_thread_pj_state` sean coherentes con la suma de `pv_change` y `pe_change` de todos los posts del personaje en el hilo.
- Si hay discrepancia, revisa post por post: ¿faltó un cambio de PV/PE en algún post? ¿El jugador omitió actualizar?
- Un personaje no puede tener PV < 0 (estaría derrotado/muerto). Si `current_pv` es negativo, es un error o requiere intervención narrativa.
- PE no debería exceder el máximo. Si `current_pe > max_pe`, el jugador declaró más PE del que debería tener.

### 17.2 Revisando Declaraciones de PA

Para cada post, verifica:
1. **`pa_declared <= max_pa`** del personaje. Si declaró 25 PA pero su máximo es 23, hay un problema.
2. **Las cartas jugadas en el post suman <= pa_declared.** Si declaró 15 PA pero las cartas suman 20, pidió prestado PA que no tiene.
3. **Las acciones narrativas justifican el gasto.** Un post donde solo camina y habla no debería tener 20 PA gastados.
4. **Hidden actions también consumen PA.** Cada carta en una acción oculta tiene coste de PA.

**Herramienta:** Query para obtener datos de un hilo:
```sql
SELECT gc.post_id, gc.pa_declared, gc.pv_change, gc.pe_change,
       gc.character_id, gp.name as character_name
FROM mybb_game_post_characters gc
JOIN mybb_game_personajes gp ON gc.character_id = gp.id
WHERE gc.thread_id = {TID}
ORDER BY gc.post_id ASC;
```

### 17.3 Aprobando Modificadores

- Los modificadores deben tener justificación narrativa o mecánica.
- Un buff de +5 FUE por "esfuerzo sobrehumano" es aceptable en un momento climático. Un buff permanente todas las semanas no.
- Los modificadores no deberían exceder el rango de stats del personaje. Si un personaje rango C (FUE 8) tiene +10 FUE por modificador, pregúntate si eso tiene sentido.
- Los modificadores negativos son igual de importantes: si el personaje está envenenado o herido, debería tener malus.

### 17.4 Gestionando Acciones Ocultas

- Monitorea las acciones ocultas para evitar abusos. Un jugador no debería tener 10 acciones ocultas acumuladas.
- Las acciones ocultas deberían revelarse en el momento apropiado. Si pasan 10 posts y el jugador nunca reveló su "trampa", el staff puede pedir que la revele o la descarte.
- Verifica que las cartas en acciones ocultas sean válidas (mismas reglas que cartas normales).

### 17.5 Snapshot de Equipamiento

- Si un jugador reclama haber usado una carta que requiere equipamiento, verifica que esté en `equipped_snapshot_json` del post.
- Si el snapshot está vacío (migración no ejecutada), el sistema cae al equipamiento actual. Esto puede ser engañoso si el jugador cambió equipo después del post.
- Para auditoría forense: compara el snapshot del post con el equipamiento actual. Si difieren, pregúntate cuándo cambió.

### 17.6 Depuración

- Activa `?debug_post_rpg=1` en la URL de `cards_for_post.php` para ver datos técnicos.
- Revisa `game/logs/post_rpg_debug.log` si el log está activo.
- Si las columnas de modificadores no funcionan, ejecuta `migrate_post_modifiers.php`.
- Si `hidden_actions_json` no guarda, ejecuta la migración que añade esa columna.
- Los contadores de posts (`postnum`, `threadnum`) se recalculan con `migrate_pj_system.php`.

### 17.7 Migraciones Pendientes

Orden recomendado para ejecutar migraciones:

```
1. migrate_pj_system.php        ← Crea tabla base y contadores
2. migrate_post_modifiers.php   ← PV/PE/Modifiers
3. migrate_post_pa_declared.php ← PA declaration
4. migrate_post_equipped_snapshot.php ← Equipment snapshot
5. migrate_roll_modifiers.php   ← Roll modifiers en game_post_cards
```

Cada migración es idempotente: verifica si la columna existe antes de crearla.

---

## 18. Troubleshooting

### 18.1 Las Columnas de Modificadores No Funcionan

| Síntoma | Causa | Solución |
|---------|-------|----------|
| `pv_change` siempre 0 | Columna no existe en DB | Ejecutar `migrate_post_modifiers.php` |
| `modifiers_json` siempre NULL | Columna no existe o no se envía desde frontend | Verificar migración y JS |
| `game_post_rpg_modifiers_ready()` devuelve false | Faltan columnas | Verificar migración |

### 18.2 Hidden Actions No se Guardan

| Síntoma | Causa | Solución |
|---------|-------|----------|
| `hidden_actions_json` siempre NULL | Columna no existe | Ejecutar migración que crea la columna |
| Acciones no visibles para el dueño | `post_character_id != viewer_char_id` | Verificar personaje activo |
| Cartas de acción oculta visibles para todos | `hidden_action_index = 0` | Verificar frontend envía index correcto |

### 18.3 Snapshot de Equipamiento Vacío

| Síntoma | Causa | Solución |
|---------|-------|----------|
| `equipped_snapshot_json` NULL | Columna no existe o no se tomó snapshot | Ejecutar `migrate_post_equipped_snapshot.php` |
| Snapshot vacío en posts antiguos | Migración ejecutada después de esos posts | No se puede corregir retroactivamente |
| Carta rechazada no está en snapshot | La carta no estaba equipada al postear | Verificar inventario al momento del post |

### 18.4 Edición Bloqueada Incorrectamente

| Síntoma | Causa | Solución |
|---------|-------|----------|
| Post sin tiradas no se puede editar | `game_postcharacter_has_rolls()` detecta falsos positivos | Revisar `game_post_cards` para ese post_id |
| Error "contiene tiradas" en post narrativo | Oráculo o viaje asociado al post | Verificar `game_post_oracles` y `game_navigation_voyages` |

### 18.5 Contadores de Posts Incorrectos

| Síntoma | Causa | Solución |
|---------|-------|----------|
| `postnum` en ficha no coincide con posts reales | Posts borrados sin decrementar contador | Recalcular con `migrate_pj_system.php` |
| `threadnum` incorrecto | Similar | Recalcular |
| Personaje tiene `postnum = 0` pero tiene posts | Delete sin UPDATE de contadores | Recalcular |

### 18.6 El Plugin No se Dispara

| Síntoma | Causa | Solución |
|---------|-------|----------|
| No se crea fila en `game_post_characters` al postear | Plugin desactivado | Activar plugin en Admin CP |
| Solo pasan algunos hooks | Error en `game_postcharacter_save_post` | Revisar `post_debug.log` |
| Error 500 al postear | Excepción no capturada en el plugin | Revisar logs de PHP/MyBB |

---

## 19. Apéndice: Archivos del Subsistema

```
back/forum/
├── game/
│   ├── ajax/
│   │   ├── cards_for_post.php          ← Lectura de datos RPG del post
│   │   └── thread_pj_state.php         ← Estado PV/PE/PA por hilo+PJ
│   ├── inc/
│   │   ├── post_rpg_debug.php          ← Debug logging
│   │   ├── navigation_process.php      ← Viajes desde posts
│   │   ├── navigation_helpers.php      ← Helper de islas/rutas
│   │   └── stat_helpers.php            ← Cálculo de PV/PE/contexto
│   ├── src/Application/UseCases/
│   │   ├── ProcessPostCards.php        ← Procesa cartas y hidden actions
│   │   └── ProcessPostOracles.php      ← Procesa oráculos
│   └── sql/
│       ├── install_schema_fragments.php    ← Definición completa de tablas
│       ├── migrate_pj_system.php           ← Migración base del sistema
│       ├── migrate_post_modifiers.php      ← PV/PE/modifiers_json
│       ├── migrate_post_pa_declared.php    ← pa_declared
│       ├── migrate_post_equipped_snapshot.php ← equipped_snapshot_json
│       └── migrate_roll_modifiers.php      ← roll_modifiers_json en game_post_cards
├── inc/plugins/
│   └── game_postcharacter.php          ← Plugin MyBB (hooks + procesamiento)
└── jscripts/game/
    └── post_editor.js                  ← JS del editor de posts (referencia)

Guias/
├── MAESTRO_SISTEMAS_RPG.md             ← Documento maestro (sección 24)
├── sistemas/
│   ├── 01-personaje.md                 ← Ficha de personaje (contadores)
│   ├── 02-stats.md                     ← Stats (PV/PE/PA calculation)
│   ├── 04-pa-pp.md                     ← PA y PP (declaración, cálculo)
│   ├── 05-cards.md                     ← Cartas (procesamiento en posts)
│   ├── 06-inventario.md                ← Inventario (equipped snapshot)
│   ├── 14-navegacion.md                ← Navegación (viajes desde posts)
│   └── 24-sistema-posts.md             ← ESTE DOCUMENTO
```

---

*Fin del documento — Guía completa del Sistema de Posts v1.0*
*Generado desde: `Guias/sistemas/24-sistema-posts.md`*
*Referencia: `Guias/MAESTRO_SISTEMAS_RPG.md` — Sección 24*
