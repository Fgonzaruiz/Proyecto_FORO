# SISTEMA RPG DEL FORO — DOCUMENTACIÓN EXTENSA

> **Versión del sistema:** MyBB 1.8 + Módulo Game RPG  
> **Última actualización:** Junio 2026  
> **Propósito:** Documentar cada atributo, mecánica, interacción entre componentes y flujo de datos del sistema de rol.

---

## ÍNDICE

1. [ARQUITECTURA GENERAL](#1-arquitectura-general)
2. [ESTRUCTURA DE BASE DE DATOS](#2-estructura-de-base-de-datos)
3. [ATRIBUTOS DEL PERSONAJE (STATS)](#3-atributos-del-personaje-stats)
4. [PUNTOS DE PODER (PP)](#4-puntos-de-poder-pp)
5. [SISTEMA DE NIVELES](#5-sistema-de-niveles)
6. [PV Y PE — PUNTOS DE VIDA Y ENERGÍA](#6-pv-y-pe--puntos-de-vida-y-energía)
7. [SISTEMA DE LINAJE (RAZAS)](#7-sistema-de-linaje-razas)
8. [SISTEMA DE CARTAS](#8-sistema-de-cartas)
9. [SISTEMA DE DADOS](#9-sistema-de-dados)
10. [INVENTARIO Y EQUIPAMIENTO](#10-inventario-y-equipamiento)
11. [FLUJO DE POSTEO](#11-flujo-de-posteos)
12. [INTERACCIÓN CON EL FORO (MyBB)](#12-interacción-con-el-foro-mybb)
13. [SISTEMA DE STAFF](#13-sistema-de-staff)
14. [FECHA IN-GAME (CRONOLOGÍA)](#14-fecha-in-game-cronología)
15. [NOTIFICACIONES Y MENSAJES](#15-notificaciones-y-mensajes)
16. [ECONOMÍA (BERRIES)](#16-economía-berries)
17. [DIAGRAMA DE FLUJO COMPLETO](#17-diagrama-de-flujo-completo)
18. [APÉNDICE: CÓDIGO FUENTE RELEVANTE](#18-apéndice-código-fuente-relevante)

---

## 1. ARQUITECTURA GENERAL

### 1.1 Stack Tecnológico

| Componente | Tecnología |
|---|---|
| Foro | MyBB 1.8 (PHP 7.4+) |
| Base de datos | MySQL / MariaDB (tablas InnoDB) |
| Frontend JS | Vanilla JavaScript (ES5 compatible) |
| CSS | Tema RPG personalizado (`rpg_custom.css`) |
| Editor | BBCode personalizado + toolbar JS |
| Servidor | Apache / Nginx |

### 1.2 Diagrama de Capas

```
┌──────────────────────────────────────────────────────────┐
│                    NAVEGADOR (CLIENTE)                    │
│  rpg_custom.js · templates MyBB · CSS personalizado      │
└────────────────────────┬─────────────────────────────────┘
                         │ HTTP / AJAX
┌────────────────────────▼─────────────────────────────────┐
│                    MyBB CORE                              │
│  global.php · inc/init.php · class_core.php               │
│  session.php · plugins.php · class_parser.php             │
└───────┬────────────────────────────────┬──────────────────┘
        │                                │
┌───────▼──────────────┐   ┌────────────▼──────────────────┐
│  Plugin RPG           │   │  Módulo Game/                 │
│  game_postcharacter   │   │  game/bootstrap.php           │
│  (11 hooks MyBB)      │   │  game/src/ (Services/Domain)  │
└───────┬──────────────┘   │  game/ajax/ (78 endpoints)    │
        │                  │  game/public/ (46 páginas)    │
        │                  └────────────┬──────────────────┘
        │                               │
┌───────▼───────────────────────────────▼──────────────────┐
│              BASE DE DATOS MySQL                          │
│  mybb_users · mybb_posts · mybb_threads                  │
│  mybb_game_personajes · mybb_game_post_characters         │
│  mybb_game_cards · mybb_game_character_cards              │
│  mybb_game_thread_meta · mybb_game_thread_pj_state        │
│  +20 tablas game_*                                        │
└──────────────────────────────────────────────────────────┘
```

### 1.3 Flujo de una petición típica

```
1. Usuario escribe un post en el foro
2. MyBB ejecuta datahandler_post_insert_post
3. Plugin game_postcharacter.php captura el hook
4. Recupera el personaje activo del usuario (game_user_config.active_pj_id)
5. Vincula el post al personaje (INSERT en game_post_characters)
6. Incrementa postnum del personaje (game_personajes.postnum++)
7. Toma snapshot del equipamiento (equipped_snapshot_json)
8. Procesa cartas jugadas (rpg_played_cards / rpg_hidden_actions)
9. Guarda estado PV/PE del hilo (game_thread_pj_state)
10. Otorga PP según el conteo de palabras
11. Crea notificaciones si es reply a otro usuario
12. Renderiza el post con la card del personaje (JS en frontend)
```

---

## 2. ESTRUCTURA DE BASE DE DATOS

### 2.1 Tabla `mybb_game_personajes` — El personaje

Esta es la tabla **central** del sistema. Cada fila = un personaje.

```sql
CREATE TABLE mybb_game_personajes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT DEFAULT NULL,          -- FK a mybb_users.uid
    name            VARCHAR(255) NOT NULL,      -- Nombre del personaje
    race            VARCHAR(50) NOT NULL,       -- race key (Humano, Mink, etc.)
    race_name       VARCHAR(100) NOT NULL,      -- Nombre legible
    occupation      VARCHAR(50) NOT NULL,       -- occupation key
    occupation_name VARCHAR(100) NOT NULL,      -- Nombre legible
    `desc`          TEXT NOT NULL,              -- Descripción narrativa
    details         TEXT NOT NULL,              -- Detalles extendidos
    rango           VARCHAR(100) NOT NULL,      -- Rango (D, C, B, A, S, SS)
    tripulacion     VARCHAR(255) NOT NULL,      -- Tripulación / banda
    recompensa      VARCHAR(100) NOT NULL,      -- Recompensa (berries)
    banner          VARCHAR(255) NOT NULL,      -- URL del banner
    avatar          VARCHAR(500) NOT NULL DEFAULT '',
    firma           TEXT DEFAULT NULL,          -- Firma HTML del personaje
    is_staff        TINYINT(1) NOT NULL DEFAULT 0,   -- ¿Es staff?
    staff_level     TINYINT(1) NOT NULL DEFAULT 0,   -- 1=Narrador 2=Mod 3=Admin
    is_npc          TINYINT(1) NOT NULL DEFAULT 0,
    is_narrator     TINYINT(1) NOT NULL DEFAULT 0,
    status          VARCHAR(20) NOT NULL DEFAULT 'pendiente',  -- pendiente/aprobado/rechazado
    postnum         INT NOT NULL DEFAULT 0,     -- Posts hechos con este PJ
    threadnum       INT NOT NULL DEFAULT 0,     -- Hilos creados con este PJ
    data_json       LONGTEXT,                   -- JSON: nivel, pp, linaje, etc.
    stats_json      LONGTEXT,                   -- JSON: fue, agi, des, int, inst, esp
    faction         VARCHAR(100) DEFAULT '',
    approved        TINYINT(1) DEFAULT 0,
    cronologia_json LONGTEXT,
    tecnicas_json   LONGTEXT,
    gestion_json    LONGTEXT,
    berries         INT NOT NULL DEFAULT 0,     -- Moneda del juego
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Campos clave explicados:**

| Campo | Tipo | Contenido |
|---|---|---|
| `data_json` | LONGTEXT JSON | `{nivel, pp, pp_linaje, stat_points_purchased, last_level_up_at, age, origin, race, job, faction, pb, physique, psychology, extras, arquetipo, linaje, rank}` |
| `stats_json` | LONGTEXT JSON | `{fue, agi, des, inst, esp, int}` — los 6 atributos core |
| `postnum` | INT | Contador de posts. Incrementa al postear, decrementa al borrar |
| `threadnum` | INT | Contador de hilos creados |
| `berries` | INT | Moneda del juego |

### 2.2 `data_json` — Estructura completa

```json
{
  "nivel": 5,
  "pp": 42,
  "pp_linaje": 8,
  "stat_points_purchased": 47,
  "last_level_up_at": "2026-05-20 14:30:00",
  "age": "28",
  "origin": "Isla Gyojin",
  "race": "Gyojin",
  "job": "Luchador",
  "faction": "Piratas del Sol",
  "pb": "Kaito",
  "physique": "2.80m, complexión musculosa, piel escamada azul",
  "psychology": "Orgulloso, impulsivo, leal a su tripulación",
  "extras": "Cicatriz en el ojo izquierdo",
  "arquetipo": "Guerrero",
  "rank": "C",
  "linaje": {
    "version": 2,
    "bonusPP": 24,
    "pasivas_primarias": ["pp_gyojin_01", "pp_gyojin_02", "pp_gyojin_03", "pp_gyojin_04"],
    "pasivas_secundarias": ["ps_gyojin_01", "ps_gyojin_02", "ps_gyojin_03"],
    "perks_raciales": ["rg_karate_agua", "rg_corriente_maestro", "rg_habla_peces"],
    "perks_generales": ["lg_voluntad", "lg_vida"]
  }
}
```

### 2.3 `stats_json` — Estructura

```json
{
  "fue": 12,
  "agi": 8,
  "des": 10,
  "inst": 7,
  "esp": 9,
  "int": 6
}
```

**Rango de cada stat:** 1 a 20 (clamp aplicado en `CharacterSaveService.sanitizeStats()`)

### 2.4 Tabla `mybb_game_post_characters` — Puente Post ↔ Personaje

```sql
CREATE TABLE mybb_game_post_characters (
    post_id               INT PRIMARY KEY,         -- FK a mybb_posts.pid
    thread_id             INT DEFAULT NULL,        -- FK a mybb_threads.tid
    user_id               INT NOT NULL,            -- FK a mybb_users.uid
    character_id          INT NOT NULL,            -- FK a game_personajes.id
    pv_change             INT NOT NULL DEFAULT 0,  -- Cambio de PV en este post
    pe_change             INT NOT NULL DEFAULT 0,  -- Cambio de PE en este post
    modifiers_json        TEXT DEFAULT NULL,       -- Modificadores de stats este turno
    hidden_actions_json   TEXT DEFAULT NULL,       -- Acciones ocultas (postData)
    equipped_snapshot_json TEXT DEFAULT NULL,      -- Snapshot de equipo al postear
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 2.5 Tabla `mybb_game_thread_meta` — Metadatos del hilo

```sql
CREATE TABLE mybb_game_thread_meta (
    thread_id   INT PRIMARY KEY,
    thread_type VARCHAR(20) NOT NULL DEFAULT 'Presente',
    day         INT NOT NULL DEFAULT 1,
    season      INT NOT NULL DEFAULT 0,
    year        INT NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Tipos de hilo (`thread_type`):** `Pasado`, `Presente`, `Mision`, `Evento`, `Trama`, `Fic`, `Off_Rol`

### 2.6 Tabla `mybb_game_thread_pj_state` — Estado PV/PE por hilo

```sql
CREATE TABLE mybb_game_thread_pj_state (
    thread_id     INT NOT NULL,
    character_id  INT NOT NULL,
    current_pv    INT NOT NULL,              -- PV actual en este hilo
    current_pe    INT NOT NULL,              -- PE actual en este hilo
    stat_mods_json TEXT DEFAULT NULL,        -- Modificadores activos
    last_post_id  INT DEFAULT NULL,          -- Último post que actualizó
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (thread_id, character_id)
);
```

### 2.7 Tabla `mybb_game_user_config` — Config usuario ↔ personaje

```sql
CREATE TABLE mybb_game_user_config (
    user_id     INT PRIMARY KEY,
    max_slots   INT NOT NULL DEFAULT 1,    -- Máx personajes permitidos
    slots_used  INT NOT NULL DEFAULT 0,    -- Personajes creados
    active_pj_id INT DEFAULT NULL,          -- Personaje activo actual
    is_narrator TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 2.8 Tablas del sistema de Cartas

**`mybb_game_cards`** — Catálogo de cartas (habilidades, equipo, frutas, etc.):
```sql
CREATE TABLE mybb_game_cards (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    card_type       ENUM('tecnica','equipo','akuma_no_mi','haki','npc_menor','barco') NOT NULL,
    `rank`          ENUM('C','B','A','S','SS') NOT NULL DEFAULT 'C',
    activation      ENUM('activa','pasiva','reactiva') NOT NULL DEFAULT 'activa',
    tags_json       TEXT,                    -- Tags como ["FUEGO", "IMPACTO", "ARMA"]
    description     TEXT,
    cost_pe         VARCHAR(50) DEFAULT '—',
    execution_cost  INT NOT NULL DEFAULT 0,
    execution_stat  VARCHAR(10) DEFAULT '',   -- Stat de escalado: fue, agi, int...
    dice            VARCHAR(150) DEFAULT '',  -- Fórmula de dados: "2d8 + fue"
    effects_json    TEXT,                     -- Efectos especiales
    upgrade_json    TEXT,                     -- Mejoras disponibles
    notes           TEXT,
    image_url       VARCHAR(500) DEFAULT '',
    cost_berries    INT NOT NULL DEFAULT 0,
    in_shop         TINYINT(1) NOT NULL DEFAULT 0,
    shop_category   VARCHAR(50) DEFAULT 'utiles',
    peso            INT NOT NULL DEFAULT 1,   -- Peso en inventario
    created_by      INT NOT NULL,
    reposo          INT NOT NULL DEFAULT 0,   -- Turnos de reposo requeridos
    duracion        INT NOT NULL DEFAULT 0,   -- Duración en turnos
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**`mybb_game_character_cards`** — Cartas que posee cada personaje:
```sql
CREATE TABLE mybb_game_character_cards (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    character_id INT NOT NULL,
    card_id      INT NOT NULL,
    current_rank ENUM('C','B','A','S','SS') NOT NULL DEFAULT 'C',
    assigned_by  INT NOT NULL,
    cantidad     INT NOT NULL DEFAULT 1,      -- Para consumibles
    assigned_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_char_card (character_id, card_id)
);
```

**`mybb_game_post_cards`** — Cartas jugadas en posts:
```sql
CREATE TABLE mybb_game_post_cards (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    post_id            INT NOT NULL,
    character_id       INT NOT NULL,
    card_id            INT NOT NULL,
    played_rank        ENUM('C','B','A','S','SS') NOT NULL DEFAULT 'C',
    roll_result        VARCHAR(255) DEFAULT NULL,  -- Resultado de la tirada
    hidden_action_index INT NOT NULL DEFAULT 0,    -- 0 = carta normal, >0 = acción oculta
    played_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**`mybb_game_character_inventory`** — Slots equipados:
```sql
CREATE TABLE mybb_game_character_inventory (
    character_id INT NOT NULL,
    card_id      INT NOT NULL,
    slot_type    ENUM('carga','companero','barco') NOT NULL,
    equipped_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    peso         INT NOT NULL DEFAULT 0,
    PRIMARY KEY (character_id, card_id)
);
```

---

## 3. ATRIBUTOS DEL PERSONAJE (STATS)

### 3.1 Los 6 Atributos Fundamentales

Cada personaje tiene exactamente **6 estadísticas base**, almacenadas en `stats_json`:

| Clave | Nombre | Descripción | Valor Inicial | Rango |
|---|---|---|---|---|
| `fue` | **Fuerza** | Poder físico bruto, daño cuerpo a cuerpo, capacidad de carga | 5 | 1–20 |
| `agi` | **Agilidad** | Velocidad, reflejos, evasión, precisión | 5 | 1–20 |
| `des` | **Destreza** | Coordinación manual, puntería, precisión fina | 5 | 1–20 |
| `int` | **Intelecto** | Conocimiento, estrategia, uso de técnicas complejas | 5 | 1–20 |
| `inst` | **Instinto** | Percepción, intuición, detección de peligros, rastreo | 5 | 1–20 |
| `esp` | **Espíritu** | Voluntad, resistencia mental, poder de Haki, energía | 5 | 1–20 |

**Cálculo de inicialización** (desde `CharacterSaveService.sanitizeStats()`):
```php
$clamp = fn($v) => max(1, min(20, (int)$v));
// Si no se provee un stat, default = 5
// Mapeo legacy: str → fue, res → des, vol → esp/inst
```

### 3.2 Vitalidad (vit) — Stat Derivado

Aunque se muestra en la interfaz (`vit` = Vitalidad), **no está en `stats_json`**. Es un valor calculado o legacy. En el frontend (`rpg_custom.js`) se itera sobre 7 stats incluyendo `vit`:
```javascript
var attributes = ['fue', 'agi', 'des', 'int', 'esp', 'inst', 'vit'];
```

### 3.3 Fórmulas de PV y PE

Los **PV (Puntos de Vida)** y **PE (Puntos de Energía)** se derivan de los 6 stats:

```php
// game_postcharacter.php línea 225-228
$prev_pv = ($fue * 4) + ($agi * 2) + ($esp * 3) + ($int * 1);
$prev_pe = ($esp * 4) + ($des * 3) + ($agi * 2) + ($int * 1);
```

**Fórmulas completas:**

```
PV Máximo = (FUERZA × 4) + (AGILIDAD × 2) + (ESPÍRITU × 3) + (INTELECTO × 1)
PE Máximo = (ESPÍRITU × 4) + (DESTREZA × 3) + (AGILIDAD × 2) + (INTELECTO × 1)
```

**Ejemplo con stats `{fue:12, agi:8, des:10, inst:7, esp:9, int:6}`:**

```
PV = (12×4) + (8×2) + (9×3) + (6×1) = 48 + 16 + 27 + 6 = 97
PE = (9×4) + (10×3) + (8×2) + (6×1) = 36 + 30 + 16 + 6 = 88
```

### 3.4 Interacción de Atributos con el Sistema

| Atributo | Afecta | Usado en |
|---|---|---|
| `fue` (Fuerza) | PV, daño físico, cartas cuerpo a cuerpo | Fórmulas de dados: `fue`, `fue*2`, `fue/2` |
| `agi` (Agilidad) | PV, PE, evasión, velocidad | Fórmulas de dados: `agi`, `agi*2` |
| `des` (Destreza) | PE, puntería, precisión | Fórmulas de dados: `des`, `des*3` |
| `int` (Intelecto) | PV, PE, técnicas complejas | Fórmulas de dados: `int`, `int/2` |
| `inst` (Instinto) | Percepción, detección | Fórmulas de dados: `inst`, `inst+5` |
| `esp` (Espíritu) | PV, PE, Haki, resistencia mental | Fórmulas de dados: `esp`, `esp*4` |

### 3.5 Coste de Subir Stats

Cada punto de atributo cuesta PP según esta fórmula:

```php
getStatCost($nivel) = 3 + floor(($nivel - 1) / 5)
```

| Nivel | Coste por punto de stat |
|---|---|
| 1–5 | 3 PP |
| 6–10 | 4 PP |
| 11–15 | 5 PP |
| 16–20 | 6 PP |
| ... | ... |

**Ejemplo:** Un personaje en nivel 7 quiere subir FUE de 12 a 13.  
Coste = `3 + floor((7-1)/5)` = `3 + 1` = **4 PP**

**Prioridad de gasto de PP** (desde `allocatePpSpend`):
1. Primero gasta `pp_linaje` (PP de linaje)
2. Luego gasta `pp` normal

```php
$fromLinaje = min($cost, max(0, $ppLinaje));
$new_pp = $pp - $cost;
$new_pp_linaje = $ppLinaje - $fromLinaje;
```

---

## 4. PUNTOS DE PODER (PP)

### 4.1 ¿Qué son los PP?

Los **Puntos de Poder (PP)** son la moneda de progresión del personaje. Se obtienen **posteando** y se gastan en **comprar puntos de atributo**.

### 4.2 Cómo se Obtienen

**Regla:** 1 PP por cada 150 palabras escritas en posts de rol.

Desde `game_postcharacter_award_pp()`:

```php
$word_count = game_postcharacter_count_words($message); // Sin HTML ni BBCode
$pp_earned = intdiv($word_count, 150);
```

**Validaciones:**
- ❌ **No se otorgan PP en hilos `Off_Rol`** (chat fuera de personaje)
- ❌ **No se otorgan PP si el word_count es 0**
- ✅ **Solo una vez por post** (protegido por `$awarded_pids[$pid]`)

**Tabla de referencia:**

| Palabras | PP obtenidos |
|---|---|
| 0–149 | 0 |
| 150–299 | 1 |
| 300–449 | 2 |
| 450–599 | 3 |
| 600–749 | 4 |
| 750–899 | 5 |
| 900+ | 6+ |

### 4.3 Dónde se Almacenan

Dentro de `game_personajes.data_json`:

```json
{
  "pp": 42,           // PP totales disponibles
  "pp_linaje": 8,     // PP provenientes del linaje (raza)
  "stat_points_purchased": 47,  // Puntos de atributo comprados acumulados
}
```

### 4.4 PP de Linaje (`pp_linaje`)

Los personajes reciben **PP bonus** al crearse según su raza:

| Raza | PL totales | PP bonus (sobrante) |
|---|---|---|
| Humano | 28 PL | Variable |
| Mink | 22 PL | Variable |
| Gyojin | 20 PL | Variable |
| Gigante | 16 PL | Variable |
| Tontatta | 24 PL | Variable |
| Buccaner | 22 PL | Variable |
| Lunarian | 16 PL | Variable |
| Skypean | 26 PL | Variable |
| Oni | 18 PL | Variable |
| Sirena | 22 PL | Variable |

**Regla de conversión:** 1 PL sobrante → 3 PP bonus  
Los PP bonus **NO cuentan para el cálculo de nivel**.

### 4.5 Cómo se Gastan los PP

1. **Comprar puntos de atributo** (principal uso)
   - Cada punto cuesta `3 + floor((nivel-1)/5)` PP
   - Subir 10 puntos de atributo = 1 nivel
   - Limitado a 1 nivel por semana

2. **Fórmulas de gasto** (desde `recordStatPurchase`):
   ```php
   $data['pp'] -= $cost;
   $data['pp_linaje'] = max(0, $data['pp_linaje'] - min($cost, $data['pp_linaje']));
   $data['stat_points_purchased'] += $statPointsAmount;
   ```

---

## 5. SISTEMA DE NIVELES

### 5.1 Fórmula de Nivel

```php
$nivel = 1 + intdiv($stat_points_purchased, 10);
// STAT_POINTS_PER_LEVEL = 10
```

| Puntos de atributo comprados | Nivel |
|---|---|
| 0–9 | 1 |
| 10–19 | 2 |
| 20–29 | 3 |
| 30–39 | 4 |
| 40–49 | 5 |
| 50–59 | 6 |
| ... | ... |

### 5.2 Progresión dentro del nivel

```php
getProgressInCurrentTier($purchased) = $purchased % 10;
```

Ejemplo: Si has comprado 27 puntos → progreso = 27 % 10 = **7/10** hacia nivel 4.

### 5.3 Subida de Nivel (Level Up)

```php
tryApplyPendingLevels(&$data): int
```

1. Calcula niveles pendientes: `getTargetNivel(purchased) - nivelActual`
2. Verifica restricción semanal: `canLevelUpThisWeek()`
3. Si puede subir: `nivel += 1`, `last_level_up_at = fecha_actual`

### 5.4 Restricción Semanal

```php
canLevelUpThisWeek($data) {
    $last = $data['last_level_up_at'];
    if (!$last) return true; // Nunca ha subido
    return (time() - strtotime($last)) >= 604800; // 7 días en segundos
}
```

- ✅ **Puede subir** si han pasado 7+ días desde el último level up
- ❌ **No puede subir** si han pasado menos de 7 días

### 5.5 Tope Semanal de Compra

Si ya subiste de nivel esta semana, no puedes comprar más puntos de atributo de los que te dejen a **1 punto del siguiente umbral**:

```php
// Ejemplo: nivel 5, purchased = 40, next threshold = 5*10 = 50
// Máximo permitido = 50 - 1 - 40 = 9 puntos más esta semana
$maxTotalBeforeNextLevel = $nivel * 10 - 1;
$maxBuyable = max(0, $maxTotalBeforeNextLevel - $purchased);
```

### 5.6 Snapshot del Estado de Progresión

La función `snapshot()` devuelve una vista completa:

```json
{
  "nivel": 5,
  "pp": 42,
  "pp_linaje": 8,
  "stat_points_purchased": 47,
  "stat_cost": 3,
  "progress_in_tier": 7,
  "stat_points_per_level": 10,
  "next_level_stat_threshold": 50,
  "max_stat_points_buyable": null,
  "pending_levels": 0,
  "can_level_up_this_week": true,
  "next_level_available_at": null,
  "next_level_available_iso": null
}
```

---

## 6. PV Y PE — PUNTOS DE VIDA Y ENERGÍA

### 6.1 Definición

| Recurso | Significado | Afected by |
|---|---|---|
| **PV** (Puntos de Vida) | Salud del personaje | FUE, AGI, ESP, INT |
| **PE** (Puntos de Energía) | Energía para usar técnicas | ESP, DES, AGI, INT |

### 6.2 Almacenamiento por Hilo

El estado PV/PE se guarda **por personaje y por hilo** en `game_thread_pj_state`:

```json
{
  "thread_id": 42,
  "character_id": 7,
  "current_pv": 85,
  "current_pe": 60,
  "stat_mods_json": "{\"fue\": 2, \"agi\": -1}"
}
```

### 6.3 Flujo de Actualización en Cada Post

1. El usuario envía `rpg_thread_pv` y `rpg_thread_pe` en el formulario del post
2. El plugin recupera el estado anterior o calcula máximos
3. Calcula la diferencia: `pv_change = current_pv - prev_pv`
4. Guarda el nuevo estado en `game_thread_pj_state`
5. Guarda `pv_change` y `pe_change` en `game_post_characters`

```php
// Si no hay estado previo, se calcula desde stats_json
$prev_pv = ($fue * 4) + ($agi * 2) + ($esp * 3) + ($int * 1);
$prev_pe = ($esp * 4) + ($des * 3) + ($agi * 2) + ($int * 1);
```

### 6.4 Modificadores por Post

Desde el panel JS, el usuario puede enviar **modificadores de stats** para ese turno:

```php
// Aplica buffs/debuffs a los stats antes de procesar cartas
$valid_stats = ['fue', 'agi', 'des', 'int', 'esp', 'inst'];
foreach ($raw_mods as $mod_stat => $mod_val) {
    $stats[$mod_stat] = ($stats[$mod_stat] ?? 0) + (int)$mod_val;
}
```

Ejemplo: `rpg_modifiers = {"fue": 2, "agi": -1}` → +2 FUE, -1 AGI para este post.

---

## 7. SISTEMA DE LINAJE (RAZAS)

### 7.1 Razas Disponibles

| Raza | PL Iniciales | PP Bonus Potencial |
|---|---|---|
| **Humano** | 28 PL | Variable (adaptable) |
| **Mink** | 22 PL | Electro, Sentidos |
| **Gyojin** | 20 PL | Fuerza acuática |
| **Gigante** | 16 PL | Tamaño colosal |
| **Piernas Largas** | 24 PL (sub-Humano) | Velocidad |
| **Brazos Largos** | 24 PL (sub-Humano) | Alcance |
| **Cuello Largo** | 24 PL (sub-Humano) | Visión |
| **Tontatta** | 24 PL | Tamaño diminuto |
| **Buccaner** | 22 PL | Haki innato |
| **Lunarian** | 16 PL | Llama racial |
| **Skypean** | 26 PL | Dials, vuelo |
| **Oni** | 18 PL | Fuerza demoníaca |
| **Sirena** | 22 PL | Canto, velocidad acuática |

### 7.2 Estructura del Linaje en `data_json`

```json
{
  "linaje": {
    "version": 2,
    "bonusPP": 24,
    "pasivas_primarias": ["id1", "id2", ...],
    "pasivas_secundarias": ["id3", ...],
    "perks_raciales": ["id4", "id5", ...],
    "perks_generales": ["id6", ...]
  }
}
```

### 7.3 Tipos de Pasivas

**Pasivas Primarias** (todas las razas):
- Se obtienen **automáticamente** al crear el personaje
- Sin coste de PL
- Definen la esencia racial

**Pasivas Secundarias** (solo Personajes **Puros**):
- Se obtienen automáticamente
- Solo disponibles si el personaje es de raza pura (no híbrido)
- Algunas requieren condiciones de activación

**Ejemplo — Humano:**

| ID | Nombre | Tipo | Efecto |
|---|---|---|---|
| `pp_hum_01` | Adaptabilidad Fisiológica | Primaria | Sin penalizadores por entorno |
| `pp_hum_02` | Polivalencia de Aprendizaje | Primaria | −10% coste PP en disciplinas |
| `pp_hum_03` | Tenacidad Humana | Primaria | 1/combate: PV no baja de 1 |
| `ps_hum_01` | Potencial Sin Techo | Secundaria | Sin límite racial de rango |
| `ps_hum_02` | Herencia Adaptativa del Linaje | Secundaria | Nodo central en tablero linaje |
| `ps_hum_03` | Voluntad Inquebrantable | Secundaria | +3 a resistencia de Espíritu |

### 7.4 Árboles Raciales (Perks Comprables)

Cada raza tiene un árbol de habilidades exclusivo que se compra con PL:

**Ejemplo — Humano:**

| Perk | Coste | Requiere | Efecto |
|---|---|---|---|
| Tenacidad Pura | 3 PL | — | 1/evento no caes por daño letal |
| Estudiante Dedicado | 1 PL | — | +1 Intelecto 1/escena |
| Liderazgo Natural | 2 PL | — | Aliados +1 moral/Espíritu |
| Subespecie: Piernas Largas | 4 PL | — | +30% velocidad, alcance patadas |
| Subespecie: Brazos Largos | 4 PL | — | Alcance físico superior |
| Subespecie: Cuello Largo | 4 PL | — | Visión panorámica |
| Gigantismo Humano | 5 PL | — | Talla 4-7m, FUE+2 |
| Enanismo Humano | 3 PL | — | Talla 30-80cm, AGI+3 |
| Potencial de Haki Elevado | 4 PL | Solo puro | −1 rango mínimo para Haki |

### 7.5 Árbol General (Todas las Razas)

| Perk | Coste | Efecto |
|---|---|---|
| Piel de Acero | 2 PL | −5% daño físico |
| Voluntad Férrea | 1 PL | +2 resistencia mental |
| Paso Silencioso | 1 PL | Ventaja sigilo nocturno |
| Vitalidad Extra | 2 PL | +15 PV máximos |
| Reserva de Energía | 1 PL | +10 PE máximos |
| Sentido Agudizado | 1 PL | Detección pasiva 10m |
| Golpe de Suerte | 1 PL | 1/escena: fallo → éxito menor |
| Navegante Instintivo | 1 PL | +2 navegación |

### 7.6 Sistema Híbrido

- **Coste:** −4 PL sobre la raza principal
- **Árboles:** 3 árboles (principal, secundario limitado, general)
- **Restricciones:** No puede adquirir perks `solo_puro`, no tiene pasivas secundarias

---

## 8. SISTEMA DE CARTAS

### 8.1 Tipos de Carta

| Tipo | Descripción | Ejemplo |
|---|---|---|
| `tecnica` | Habilidades activas, golpes especiales | "Meteoro de Fuego", "Garra de Tigre" |
| `equipo` | Armas, armaduras, objetos equipables | "Espada Maldita", "Chaleco Anti-balas" |
| `akuma_no_mi` | Frutas del Diablo (poderes) | "Gomu Gomu no Mi", "Mera Mera no Mi" |
| `haki` | Habilidades de Haki | "Kenbunshoku", "Busoshoku" |
| `npc_menor` | Compañeros NPC o mascotas | "Mapache Guardián", "Loro Mensajero" |
| `barco` | Embarcaciones | " Thousand Sunny", "Barco Pirata" |

### 8.2 Rango de Cartas

`C → B → A → S → SS`

El rango determina la potencia base. Las cartas pueden tener `current_rank` diferente al rank base (mejorable).

### 8.3 Activación

| Tipo | Significado |
|---|---|
| `activa` | Se declara su uso en un post |
| `pasiva` | Efecto permanente sin declaración |
| `reactiva` | Se activa como respuesta a una condición |

### 8.4 Tags de Cartas

Las cartas tienen `tags_json` que define sus propiedades:

```json
["FUEGO", "IMPACTO", "AREA", "CUERPO_A_CUERPO"]
```

Tags comunes: `FUEGO`, `AGUA`, `HIELO`, `ELECTRO`, `IMPACTO`, `CORTE`, `PERFORACION`, `VENENO`, `AREA`, `DERRIBO`, `Sangrado`, `PARALISIS`, `QUEMADURA`, `VELOCIDAD`

### 8.5 Fórmula de Dados en Cartas

Ejemplos de `dice`:

| Carta | Fórmula | Significado |
|---|---|---|
| Puño de Fuego | `2d8 + fue` | 2 dados de 8 caras + stat FUE |
| Patada Cyclón | `1d20 + agi*2` | 1d20 + AGI × 2 |
| Explosión | `3d6 + int [FUEGO]` | 3d6 + INT con tag FUEGO |
| Espadazo | `1d12 + des + fue*0.5` | 1d12 + DES + FUE/2 |
| Combinación | `[ARMA] + [MUNICION] + agi` | Usa arma equipada + munición |

**Sustitución de placeholders:**
- `[ARMA]` → Reemplazado por la fórmula del arma equipada seleccionada
- `[MUNICION]` → Reemplazado por la fórmula de la munición seleccionada

### 8.6 Coste de PE

Cada carta tiene un `cost_pe` que indica cuánta energía consume usarla.
Cartas con coste `—` son pasivas o gratuitas.

### 8.7 Consumibles

Las cartas de tipo `equipo` con `effects_json.equipo_type === 'util'` o con tags `CONSUMIBLE` / `MUNICION` / `AMMO` se descuentan del inventario al usarse:

```php
game_postcharacter_decrement_consumible($cid, $card_id);
// UPDATE cantidad = GREATEST(0, cantidad - 1)
// DELETE WHERE cantidad <= 0
```

### 8.8 Cartas en Post

Cuando un usuario postea, puede incluir cartas en el formulario:

```html
<input type="hidden" name="rpg_played_cards" value='[{"card_id":5,"weapons":[12],"ammo":[8]}]'>
<input type="hidden" name="rpg_hidden_actions" value='[{"index":2,"description":"Emboscada","cards":[{"card_id":15}]}]'>
```

**Validaciones:**
- La carta debe pertenecer al personaje (`game_character_cards`)
- La carta debe estar equipada (si requiere slot)
- Para consumibles, se verifica cantidad > 0
- Las cartas que requieren slots equipados se validan contra `equipped_snapshot_json`

### 8.9 Acciones Ocultas (Hidden Actions)

Las acciones ocultas permiten a los personajes preparar acciones que se revelan después:

```json
{
  "index": 2,
  "description": "Salto hacia atrás y contraataque",
  "cards": [{"card_id": 15}],
  "is_revealed": 0
}
```

Almacenadas en `game_post_characters.hidden_actions_json`.

---

## 9. SISTEMA DE DADOS

### 9.1 Motor de Dados

El motor `game_evaluate_dice_roll()` procesa fórmulas como:

```
[notación de dados] [operador] [stat] [operador] [constante] [TAG]
```

### 9.2 Sintaxis Soportada

| Componente | Sintaxis | Ejemplo | Resultado |
|---|---|---|---|
| Dados | `NdM` | `2d8` | 2 dados de 8 caras → "3 + 7 = 10" |
| Stats | `fue`, `agi`, `des`, `int`, `inst`, `esp` | `fue` | Valor del stat (ej. 12) |
| Stats legacy | `str`, `res`, `vol` | `str` | Mapeado a `fue`, `des`, `esp` |
| Multiplicador | `stat*N` o `N*stat` | `fue*2` | Stat × 2 |
| Divisor | `stat/N` | `des/2` | Stat ÷ 2 (floor) |
| Constante | número | `+5` | 5 |
| Tag elemental | `[TAG]` al final | `[FUEGO]` | Añadido al resultado |

### 9.3 Límites de Seguridad

```php
if ($num > 100) $num = 100;    // Máximo 100 dados
if ($faces > 1000) $faces = 1000; // Máximo 1000 caras
```

### 9.4 Ejemplos de Evaluación

**Entrada:** `"2d8 + 3 + fue"`
```
→ stats: fue=12
→ "2d8 (3 + 7) + 3 + 5 (FUERZA) = 18"
```

**Entrada:** `"1d20 + agi*2"`
```
→ stats: agi=8
→ "1d20 (12) + 12 (AGI*2) = 24"
```

**Entrada:** `"fue + des + agi [FUEGO]"`
```
→ stats: fue=12, des=10, agi=8
→ "12 (FUERZA) + 8 (DESTREZA) + 6 (AGILIDAD) = 26 [FUEGO]"
```

### 9.5 Acciones de NPC/Mascota

Para cartas `npc_menor`, el motor puede:

1. Elegir una acción aleatoria de la lista (tipo `npc`)
2. Usar una acción seleccionada por el jugador (tipo `mascota`)
3. Evaluar dados dentro del texto de la acción

```php
// Si el texto contiene "1d6 + fue", evalúa y appendea el resultado
" Mordida rápida: 1d6 + fue\n→ Mordida rápida: 1d6 (4) + 12 (FUERZA) = 16 "
```

### 9.6 Protección Anti-Edit

Una vez que un post contiene cartas con tiradas de dados, **NO puede ser editado**:

```php
// game_postcharacter_block_edit()
$q = $db->query("SELECT id FROM game_post_cards WHERE post_id = {$pid} AND roll_result != ''");
if ($db->num_rows($q) > 0) {
    error("Este mensaje contiene tiradas de dados y no puede ser editado.");
}
```

Esto aplica tanto a edición normal como a AJAX (Quick Edit).

---

## 10. INVENTARIO Y EQUIPAMIENTO

### 10.1 Slots de Inventario

| Slot | Propósito |
|---|---|
| `carga` | Objetos que el personaje lleva consigo |
| `companero` | NPCs/Mascotas acompañantes |
| `barco` | Embarcaciones |

### 10.2 Sistema de Peso

Cada carta tiene un `peso`. Al equipar:

```sql
INSERT INTO game_character_inventory (character_id, card_id, slot_type, peso)
VALUES (?, ?, 'carga', ?)
```

### 10.3 Snapshot de Equipo al Postear

Cuando se hace un post, se toma una **foto del equipamiento actual**:

```php
game_postcharacter_save_equipped_snapshot($pid, $cid);
// Guarda en game_post_characters.equipped_snapshot_json
// Ejemplo: [12, 15, 8, 3] — IDs de cartas equipadas
```

Esto asegura que las cartas validadas en el post correspondan al equipo que el personaje tenía en ese momento.

### 10.4 Validación de Cartas Equipadas

```php
function game_postcharacter_card_allowed_in_post($cardType, $cardId, $equippedIds, $isConsumible): bool {
    if (card requiere slot_equipado && !is_consumible) {
        return in_array($cardId, $equippedIds);
    }
    return true; // Consumibles y técnicas no requieren equipo
}
```

---

## 11. FLUJO DE POSTEO

### 11.1 Creación de un Post (Respuesta)

```
1. Usuario hace clic en "Responder"
2. MyBB carga newreply.php
3. Plugin global_start: establece $game_active_char_id
4. Usuario escribe su mensaje, selecciona cartas, ajusta PV/PE
5. Envía el formulario
6. MyBB ejecuta datahandler_post
7. Hook datahandler_post_insert_post_end → game_postcharacter_save_post()
   a. Obtiene active_pj_id del usuario
   b. INSERT en game_post_characters (post_id, user_id, character_id)
   c. Snapshot de equipo (equipped_snapshot_json)
   d. Incrementa game_personajes.postnum++
   e. Si es reply, notifica al autor del hilo
   f. Procesa cartas jugadas (rpg_played_cards)
   g. Procesa acciones ocultas (rpg_hidden_actions)
   h. Guarda estado PV/PE (game_thread_pj_state)
   i. Otorga PP según word count
8. Post renderizado en showthread.php
9. JavaScript reemplaza el postbit con:
   - Card del personaje (avatar, nombre, nivel, rango)
   - Barras de PV/PE
   - Barras de stats (fue, agi, des, int, esp, inst, vit)
   - Firma del personaje
```

### 11.2 Creación de un Hilo (Nuevo Tema)

```
1-5. Similar a post, pero en newthread.php
6. Hook datahandler_post_insert_thread_end → game_postcharacter_save_thread()
   a. Mismo flujo que save_post +:
   b. Incrementa game_personajes.threadnum++
   c. Guarda metadatos del hilo (game_thread_meta):
      - thread_type: Pasado/Presente/Mision/Evento/Trama/Fic/Off_Rol
      - Fecha in-game (día, season, año)
   d. Si es tipo "Presente", calcula fecha automática
   e. Si es otro tipo, usa valores del formulario
```

### 11.3 Borrado de Post

```php
game_postcharacter_delete_post($pid) {
    // Decrementa postnum del personaje vinculado
    UPDATE game_personajes SET postnum = GREATEST(0, postnum - 1) WHERE id = $cid
}
```

### 11.4 Borrado de Hilo

```php
game_postcharacter_delete_thread($tid) {
    // Decrementa threadnum del autor
    // Decrementa postnum de TODOS los que postearon en el hilo
    // (JOIN entre mybb_posts y game_post_characters)
}
```

### 11.5 Conteo de Palabras para PP

```php
function game_postcharacter_count_words(string $text): int {
    $text = strip_tags($text);           // Quita HTML
    $text = preg_replace('/\[[^\]]*\]/', ' ', $text); // Quita BBCode
    return preg_match_all('/\p{L}+/u', $text);  // Cuenta palabras Unicode
}
```

### 11.6 Campos del Formulario de Post

| Campo | Tipo | Propósito |
|---|---|---|
| `rpg_played_cards` | JSON hidden | Cartas jugadas en este post |
| `rpg_hidden_actions` | JSON hidden | Acciones ocultas preparadas |
| `rpg_thread_pv` | number | PV actual del personaje en este hilo |
| `rpg_thread_pe` | number | PE actual del personaje en este hilo |
| `rpg_modifiers` | JSON hidden | Buffs/debuffs temporales `{"fue":2,"agi":-1}` |
| `game_thread_type` | string | Tipo de hilo (solo en creación) |
| `game_day` | number | Día in-game (solo hilos no-Presente) |
| `game_season` | number | Estación in-game (solo no-Presente) |
| `game_year` | number | Año in-game (solo no-Presente) |

---

## 12. INTERACCIÓN CON EL FORO (MyBB)

### 12.1 Hooks del Plugin

| Hook MyBB | Función del Plugin | Disparador |
|---|---|---|
| `datahandler_post_insert_post_end` | `game_postcharacter_save_post()` | Al guardar un post |
| `datahandler_post_insert_thread_end` | `game_postcharacter_save_thread()` | Al crear un hilo |
| `class_moderation_delete_post_start` | `game_postcharacter_delete_post()` | Al borrar un post |
| `class_moderation_delete_thread_start` | `game_postcharacter_delete_thread()` | Al borrar un hilo |
| `global_start` | `game_postcharacter_global_date()` | En cada página (fecha in-game) |
| `global_start` | `game_postcharacter_set_template_vars()` | Variable `$game_active_char_id` |
| `editpost_start` | `game_postcharacter_block_edit()` | Bloquea edición de posts con dados |
| `xmlhttp_edit_post_start` | `game_postcharacter_block_ajax_edit()` | Bloquea AJAX edit de posts con dados |

### 12.2 Template Variables

| Variable | Valor | Dónde se usa |
|---|---|---|
| `{$game_active_char_id}` | ID del personaje activo | Plantillas para mostrar selector PJ |
| `{$mybb->settings['game_rol_header_html']}` | HTML con fecha in-game | Header del foro (index) |

### 12.3 JavaScript Postbit PJ Card

Cada post con clase `.rpg-post-pjcard` se reemplaza dinámicamente:

```javascript
fetch('/game/ajax/get_active_pj_for_user.php?uid=' + uid + '&post_id=' + postId)
```

**Datos que devuelve el endpoint:**

```json
{
  "ok": true,
  "data": {
    "id": 7,
    "name": "Kaito",
    "nivel": 5,
    "rango": "C",
    "avatar": "url Avatar 290x450",
    "faction": "Piratas del Sol",
    "max_pv": 97,
    "max_pe": 88,
    "current_pv": 85,
    "current_pe": 60,
    "stats": {"fue":12, "agi":8, "des":10, "int":6, "esp":9, "inst":7, "vit":5},
    "firma_html": "<p>Firma del personaje</p>",
    "is_staff": false,
    "staff_level": 0
  }
}
```

**Elementos renderizados:**
- Avatar del personaje (290×450)
- Nombre con link a `personaje.php?pj=ID`
- Nivel y Rango
- Barra de PV (current/max, color rojo)
- Barra de PE (current/max, color azul)
- 7 barras de stats (fue, agi, des, int, esp, inst, vit)
- Color de facción (Pirata=rojo, Marine=azul, etc.)
- Firma del personaje (si existe)

### 12.4 Reemplazo de Nombres en Lista de Hilos

En `forumdisplay.php`, los nombres de usuario se reemplazan por nombres de personaje:

```javascript
// Autor del hilo
fetch('/game/ajax/get_active_pj_for_user.php?uid=' + uid + '&thread_id=' + threadId)
// Último post
fetch('/game/ajax/get_active_pj_for_user.php?uid=' + uid + '&last_post_for_thread_id=' + threadId)
```

### 12.5 Selector de Personaje (Navbar)

El menú de usuario en el navbar se reemplaza dinámicamente:

1. Fetch a `/game/ajax/my_personajes.php`
2. Muestra lista de personajes del usuario
3. El personaje activo se marca
4. Al hacer clic en otro, `switchPJNav(pjId)` → POST a `set_active_pj.php`
5. Recarga la página

### 12.6 Badges de Tipo de Hilo

En la lista de hilos, se agrega un badge con el tipo y fecha in-game:

```javascript
fetch('/game/ajax/get_thread_diary_data.php?thread_id=' + tid)
// Devuelve: {category: "Presente", day: 47, season: 1, year: 3}
```

**Colores por tipo:**

| Tipo | Color |
|---|---|
| Pasado | `#8b5cf6` (púrpura) |
| Presente | `#10b981` (verde) |
| Misión | `#f59e0b` (ámbar) |
| Evento | `#3b82f6` (azul) |
| Trama | `#ef4444` (rojo) |
| Fic | `#ec4899` (rosa) |
| Off Rol | `#6b7280` (gris) |

### 12.7 Iconos Dinámicos de Foros

En el índice, cada foro recibe un icono según su nombre:

```javascript
const themeConfig = {
    'reglamento':     { icon: 'fa-bullhorn',       color: '#8b5cf6' },
    'anuncios':       { icon: 'fa-bell',           color: '#C62828' },
    'presentaciones': { icon: 'fa-user-astronaut', color: '#10b981' },
    'off-topic':      { icon: 'fa-smile',          color: '#8b5cf6' },
    'default':        { icon: 'fa-compass',        color: '#3b82f6' }
};
```

### 12.8 Hero Banner Rotatorio

En el índice, el banner principal cambia aleatoriamente entre imágenes predefinidas.

### 12.9 Polling de Notificaciones

```javascript
setInterval(function() {
    fetch('/game/ajax/notifications_count.php')
    // Actualiza badge de campana cada 30 segundos
}, 30000);
```

---

## 13. SISTEMA DE STAFF

### 13.1 Niveles de Staff

| staff_level | Rol | Permisos |
|---|---|---|
| 1 | **Narrador** | Gestionar NPCs, crear eventos, moderar roleo |
| 2 | **Moderador** | Todo lo anterior + moderar usuarios, gestionar fichas |
| 3 | **Administrador** | Control total del sistema |

### 13.2 Verificación

```php
// Desde bootstrap.php
function game_get_active_staff_level(int $userId): int {
    // Verifica que el personaje activo tenga is_staff=1
    // Devuelve staff_level (0 si no es staff)
}

function game_require_staff_character(): void {
    // 403 si el personaje activo no es staff
}

function game_require_staff_level(int $minLevel): void {
    // 403 si staff_level < minLevel
}
```

### 13.3 UI de Staff

El navbar muestra enlaces de staff según el nivel:
- Nivel 1: "Zona Colaborador"
- Nivel 2: "Zona Moderador"
- Nivel 3: "Zona Administrador"

El `document.body` recibe la clase `rpg-staff` si el personaje activo es staff.

---

## 14. FECHA IN-GAME (CRONOLOGÍA)

### 14.1 Cálculo Automático

```php
function game_global_rol_date(): string {
    $epoch = strtotime('2026-05-01');   // Época del mundo
    $diff_seconds = time() - $epoch;
    $diff_days_float = $diff_seconds / 86400;
    $rol_days = floor($diff_days_float * 1.5) + 1;  // 1.5× tiempo real
    // ...
    // 260 días por año (65 días × 4 estaciones)
    // Estaciones: Primavera, Verano, Otoño, Invierno
    return "Día 47 de Verano, Año 3";
}
```

**Escala temporal:** 1 día real = 1.5 días in-game.

### 14.2 Hilos "Presente"

Los hilos con tipo `Presente` usan la fecha automática del sistema.

### 14.3 Hilos "Pasado", "Misión", "Evento", "Trama", "Fic", "Off_Rol"

Usan **fechas manuales** definidas por el creador del hilo.

### 14.4 Almacenamiento

```sql
game_thread_meta (thread_id, type, day, season, year)
```

---

## 15. NOTIFICACIONES Y MENSAJES

### 15.1 Sistema de Notificaciones

```sql
game_notifications (id, user_id, character_id, type, title, body, link, is_read, is_dismissed, created_at)
```

**Tipos de notificación:**
- `role_reply` — Alguien respondió a tu hilo
- `system` — Mensajes del sistema
- `staff` — Acciones del staff
- `dm` — Mensaje directo recibido

**Creación:**
```php
game_create_notification($userId, $type, $title, $body, $link);
```

### 15.2 Mensajes Directos Entre Personajes

```sql
game_direct_messages (id, from_character_id, to_character_id, subject, body, is_read, ...)
```

### 15.3 Polling de No Leídas

El frontend consulta cada 30s:

```javascript
fetch('/game/ajax/notifications_count.php')
// Muestra badge con número de no leídas
```

---

## 16. ECONOMÍA (BERRIES)

### 16.1 Moneda

Los **Berries** son la moneda del juego, almacenados en `game_personajes.berries`.

### 16.2 Tienda

Las cartas marcadas con `in_shop=1` aparecen en la tienda pública.
Tienen `cost_berries` y `shop_category`.

### 16.3 Categorías de Tienda

```
utiles, armas, armaduras, consumibles, especiales, barcos, companeros
```

---

## 17. DIAGRAMA DE FLUJO COMPLETO

### 17.1 Ciclo de Vida de un Personaje

```
CREACIÓN:
  Wizard de creación → CharacterSaveService.buildPayloadForInsert()
    → Valida linaje (LinajeValidator)
    → Sanitiza stats (1..20)
    → Asigna PP bonus de raza
    → Crea registro en game_personajes
    → Estado: "pendiente"

APROBACIÓN (staff):
  → CharacterSaveService.recalculateOnApprove()
    → Recalcula linaje
    → Normaliza PP
    → Normaliza nivel
    → Estado: "aprobado"

PROGRESIÓN:
  Postear en hilos de rol
    → Obtiene PP (1 PP / 150 palabras)
    → PP se acumulan en data_json.pp

  Comprar puntos de atributo (gastar PP)
    → purchase_attribute.php
    → CharacterProgression.validateStatPointPurchase()
    → CharacterProgression.recordStatPurchase()
    → Sube stats individuales (fue, agi, etc.)
    → Si acumula 10 puntos, sube 1 nivel

  Subir de nivel
    → tryApplyPendingLevels()
    → Max 1 level-up por semana
    → Actualiza nivel y last_level_up_at
```

### 17.2 Ciclo de un Post con Interacción RPG

```
┌──────────────────────────────────────────────┐
│ USUARIO ESCRIBE POST                          │
│ - Mensaje (BBCode)                            │
│ - rpg_played_cards: [{card_id:5, weapons:[3]}]│
│ - rpg_thread_pv: 85                           │
│ - rpg_thread_pe: 60                           │
│ - rpg_modifiers: {fue:2, agi:-1}              │
└──────────────────┬───────────────────────────┘
                   ▼
┌──────────────────────────────────────────────┐
│ MyBB: datahandler_post_insert_post            │
└──────────────────┬───────────────────────────┘
                   ▼
┌──────────────────────────────────────────────┐
│ PLUGIN: game_postcharacter_save_post()        │
│                                               │
│ 1. Obtener personaje activo                   │
│    SELECT active_pj_id FROM game_user_config   │
│                                               │
│ 2. Vincular post ↔ personaje                  │
│    INSERT INTO game_post_characters           │
│                                               │
│ 3. Snapshot de equipo                         │
│    game_postcharacter_save_equipped_snapshot() │
│                                               │
│ 4. Incrementar contador de posts              │
│    UPDATE game_personajes SET postnum++       │
│                                               │
│ 5. Notificar al autor del hilo (si aplica)    │
│    game_create_notification()                 │
│                                               │
│ 6. Procesar cartas jugadas                    │
│    game_postcharacter_process_cards()         │
│    ├── Valida propiedad de carta              │
│    ├── Valida equipamiento                     │
│    ├── Aplica buffs/debuffs (rpg_modifiers)   │
│    ├── Evalúa dados (game_evaluate_dice_roll) │
│    ├── Guarda en game_post_cards              │
│    └── Decrementa consumibles                 │
│                                               │
│ 7. Guardar estado PV/PE del hilo              │
│    game_postcharacter_save_thread_state()     │
│    ├── INSERT/UPDATE game_thread_pj_state     │
│    └── UPDATE game_post_characters PV/PE      │
│                                               │
│ 8. Otorgar PP                                 │
│    game_postcharacter_award_pp()              │
│    ├── Contar palabras (sin HTML/BBCode)      │
│    ├── PP = floor(palabras / 150)             │
│    └── UPDATE game_personajes.data_json.pp    │
└──────────────────┬───────────────────────────┘
                   ▼
┌──────────────────────────────────────────────┐
│ RENDERIZADO DEL POST (showthread.php)         │
│                                               │
│ 1. HTML del post generado por MyBB            │
│ 2. .rpg-post-pjcard presente en el template   │
│ 3. JS ejecuta:                                │
│    fetch(/game/ajax/get_active_pj_for_user)   │
│    → Reemplaza avatar, nombre, stats, PV/PE   │
│    → Aplica color de facción                  │
│    → Muestra firma del personaje              │
│ 4. Badge de tipo de hilo renderizado          │
└──────────────────────────────────────────────┘
```

### 17.3 Mapa de Interacciones entre Componentes

```
game_personajes
  ├── data_json.nivel ← → CharacterProgression.getTargetNivel()
  ├── data_json.pp    ← → game_postcharacter_award_pp()
  ├── data_json.pp    →   purchase_attribute.php → recordStatPurchase()
  ├── stats_json.fue  →   Cálculo de PV/PE
  ├── stats_json.*    →   game_evaluate_dice_roll()
  ├── stats_json.*    →   game_postcharacter_process_cards()
  ├── postnum         ← → game_postcharacter_save_post/delete_post()
  └── threadnum       ← → game_postcharacter_save_thread/delete_thread()

game_post_characters
  ├── post_id         →   game_post_cards.post_id
  ├── character_id    →   game_personajes.id
  ├── pv_change       ←   game_postcharacter_save_thread_state()
  ├── pe_change       ←   game_postcharacter_save_thread_state()
  ├── equipped_snapshot → game_postcharacter_card_allowed_in_post()
  └── hidden_actions  →   UI de revelación

game_thread_pj_state
  ├── current_pv      ← → game_postcharacter_save_thread_state()
  └── current_pe      ← → game_postcharacter_save_thread_state()

game_thread_meta
  ├── thread_type     →   Badge en lista de hilos
  ├── day/season/year →   Fecha in-game
  └── thread_type     →   game_postcharacter_award_pp() (Off_Rol blocked)

game_cards
  ├── dice            →   game_evaluate_dice_roll()
  ├── effects_json    →   Efectos especiales
  ├── tags_json       →   Tags elementales
  └── cost_pe         →   Consumo de PE

game_character_cards
  └── current_rank    →   played_rank en game_post_cards

game_user_config
  └── active_pj_id    →   game_postcharacter_save_post/thread()
```

---

## 18. APÉNDICE: CÓDIGO FUENTE RELEVANTE

### 18.1 Archivos Clave

| Archivo | Propósito | Líneas |
|---|---|---|
| `inc/plugins/game_postcharacter.php` | Plugin que conecta MyBB con RPG | 1153 |
| `game/src/Application/Services/CharacterProgression.php` | Motor de niveles, PP, costes | 274 |
| `game/src/Application/Services/CharacterSaveService.php` | Creación/edición segura de fichas | 176 |
| `game/src/Application/Services/LinajeValidator.php` | Validación de árboles raciales | — |
| `game/bootstrap.php` | Bootstrap del módulo game | 195 |
| `game/data/linaje_catalog.json` | Catálogo de razas, pasivas, perks | 131 |
| `game/data/linaje_system.json` | Sistema completo de linaje | 1200+ |
| `rpg_custom.js` | Frontend JS (postbit, editor, navbar) | 1073 |
| `rpg_custom.css` | Estilos visuales del tema RPG | — |

### 18.2 Endpoints AJAX Principales

| Endpoint | Método | Propósito |
|---|---|---|
| `game/ajax/get_active_pj_for_user.php` | GET | Obtener datos del PJ para postbit |
| `game/ajax/my_personajes.php` | GET | Lista de personajes del usuario |
| `game/ajax/set_active_pj.php` | POST | Cambiar personaje activo |
| `game/ajax/purchase_attribute.php` | POST | Comprar puntos de atributo |
| `game/ajax/claim_character_level.php` | POST | Reclamar level-up manual |
| `game/ajax/roll_execute.php` | POST | Ejecutar tirada de dados |
| `game/ajax/notifications_count.php` | GET | Contar notificaciones no leídas |
| `game/ajax/dm_count.php` | GET | Contar mensajes no leídos |
| `game/ajax/get_thread_diary_data.php` | GET | Obtener metadatos del hilo |
| `game/ajax/save_personaje.php` | POST | Guardar ficha de personaje |
| `game/ajax/cards_*.php` | * | Gestión de cartas (comprar, equipar) |
| `game/ajax/shop_*.php` | * | Compra en tienda |
| `game/ajax/staff_*.php` | * | Acciones de staff |
| `game/ajax/economy_*.php` | * | Gestión económica |

### 18.3 Páginas Públicas

| Página | Propósito |
|---|---|
| `game/public/personaje.php?pj=ID` | Ficha completa del personaje |
| `game/public/mis_personajes.php` | Gestión de personajes del usuario |
| `game/public/crear_personaje.php` | Wizard de creación |
| `game/public/inventory.php` | Inventario y equipamiento |
| `game/public/rolls.php` | Historial de tiradas |
| `game/public/economy.php` | Cartera y transacciones |
| `game/public/tienda.php` | Tienda del juego |
| `game/public/notificaciones.php` | Centro de notificaciones |
| `game/public/buzon.php` | Mensajes directos |
| `game/public/historia.php` | Lore e historia |
| `game/public/calendario.php` | Calendario in-game |
| `game/public/manual.php` | Manual del juego |

---

> **Fin de la documentación.**  
> Este documento cubre todos los atributos, sistemas e interacciones del módulo RPG.  
> Para cambios o extensiones, referirse a `AGENTS.md` y las guías de auditoría en `docs/`.
