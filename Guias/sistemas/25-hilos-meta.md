# 25. Sistema de Hilos — Metadatos

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 25
> **Propósito:** Documentar exhaustivamente el subsistema de metadatos de hilos: tabla `game_thread_meta`, tipos de hilo (`thread_type`), fecha in-world (día/estación/año), cómo se establecen durante la creación del hilo, cómo se consumen en navegación, misiones, diario, actividad reciente y PP, junto con la filosofía de diseño detrás de cada decisión.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [Modelo de Datos — Tabla `game_thread_meta`](#2-modelo-de-datos)
3. [Tipos de Hilo (`thread_type`)](#3-tipos-de-hilo)
4. [Calendario In-World — Día, Estación, Año](#4-calendario-in-world)
5. [Flujo de Establecimiento](#5-flujo-de-establecimiento)
6. [Flujo en Respuesta a un Hilo](#6-flujo-en-respuesta)
7. [Uso en Navegación](#7-uso-en-navegación)
8. [Uso en Sistema de Misiones](#8-uso-en-sistema-de-misiones)
9. [Integración con Diario / Log](#9-integración-con-diario--log)
10. [Integración con Actividad Reciente](#10-integración-con-actividad-reciente)
11. [Integración con PP (Off_Rol)](#11-integración-con-pp)
12. [Migración y Mantenimiento](#12-migración-y-mantenimiento)
13. [Filosofía de Diseño](#13-filosofía-de-diseño)
14. [Consejos para Jugadores](#14-consejos-para-jugadores)
15. [Consejos para Staff](#15-consejos-para-staff)
16. [Guía de Troubleshooting](#16-guía-de-troubleshooting)

---

## 1. Arquitectura General

### 1.1 Propósito del Subsistema

Cada hilo en el foro RPG tiene dos dimensiones temporales:

- **Tiempo real (MyBB):** `threads.dateline`, `threads.lastpost`. Es el tiempo del servidor.
- **Tiempo in-world (RPG):** día, estación, año del mundo ficcional. Además, un **tipo narrativo** que clasifica la naturaleza del hilo (Presente, Flashback, Sueño, Misión, etc.).

`game_thread_meta` es la tabla que une ambas dimensiones. Sin ella, el foro sabría *cuándo* se creó un hilo en el mundo real, pero no *cuándo* ocurre en la historia ni *qué tipo* de escena es.

### 1.2 Capas del Subsistema

```
┌─────────────────────────────────────────────────────────────┐
│                    CLIENTE (Navegador)                       │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  newthread.html (select #game_thread_type, inputs    │   │
│  │  game_day/game_season/game_year + JS sync/validation)│   │
│  └──────────────────────┬───────────────────────────────┘   │
│                         │ POST (form submission)             │
└─────────────────────────┼───────────────────────────────────┘
                          │
┌─────────────────────────┼───────────────────────────────────┐
│  ┌──────────────────────▼────────────────────────────────┐  │
│  │            PHP — PLUGIN MyBB                           │  │
│  │  game_postcharacter.php:                               │  │
│  │    game_postcharacter_save_thread() — INSERT/UPDATE    │  │
│  │    game_postcharacter_global_setup() — lee meta para   │  │
│  │      pasar $game_thread_type a las templates           │  │
│  │    game_postcharacter_award_pp() — bloquea PP si       │  │
│  │      thread_type === 'Off_Rol'                         │  │
│  └──────────────────────┬────────────────────────────────┘  │
│                         │                                    │
│  ┌──────────────────────▼────────────────────────────────┐  │
│  │            PHP — Módulo game/                          │  │
│  │  mission_helpers.php → INSERT meta al crear misión     │  │
│  │  navigation_process.php → bloquea si ≠ Presente        │  │
│  │  get_thread_diary_data.php → expone meta vía AJAX      │  │
│  │  latest_activity.php → LEFT JOIN para etiquetas        │  │
│  └──────────────────────┬────────────────────────────────┘  │
│                         │                                    │
│                         ▼                                    │
│  ┌──────────────────────────────────────────────────────┐   │
│  │     MySQL — game_thread_meta + mybb_threads           │   │
│  │     PK: thread_id → threads.tid                       │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### 1.3 Principios de Diseño

1. **Desacoplamiento narrativo-mecánico:** El tipo de hilo y la fecha in-world se almacenan en una tabla separada (`game_thread_meta`), no como columnas de `mybb_threads`. Esto evita contaminar el esquema core de MyBB y permite agregar campos sin migraciones complejas.
2. **ON DUPLICATE KEY UPDATE:** La escritura es idempotente. Si el hilo ya tenía metadatos (por ejemplo, por ser una misión creada automáticamente), se actualizan sin borrar.
3. **Defaults sensatos:** `Presente` / día 1 / season 0 / año 1. Un hilo sin metadatos explícitos se trata como "Presente, Día 1 de Primavera, Año 1".
4. **Validación servidor-side:** El servidor revalida el `thread_type` contra una whitelist y los rangos de día/season/year. El frontend JS solo mejora UX.
5. **Cálculo automático vs. manual:** Si el tipo es `Presente`, la fecha se calcula automáticamente desde el calendario global. Si es cualquier otro tipo, el usuario la introduce manualmente.

---

## 2. Modelo de Datos — Tabla `game_thread_meta`

### 2.1 Definición SQL

```sql
CREATE TABLE mybb_game_thread_meta (
    thread_id   INT PRIMARY KEY,
    thread_type VARCHAR(20) NOT NULL DEFAULT 'Presente',
    day         INT NOT NULL DEFAULT 1,
    season      INT NOT NULL DEFAULT 0,
    year        INT NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2 Descripción de Columnas

| Columna       | Tipo         | Default       | Descripción |
|---------------|--------------|---------------|-------------|
| `thread_id`   | INT (PK)     | —             | ID del hilo MyBB (`threads.tid`). No es AUTO_INCREMENT porque la PK es el tid foráneo. |
| `thread_type` | VARCHAR(20)  | `'Presente'`  | Tipo narrativo del hilo. Ver sección 3 para valores válidos. |
| `day`         | INT          | `1`           | Día in-world dentro de la estación (1–65). |
| `season`      | INT          | `0`           | Índice de estación: 0=Primavera, 1=Verano, 2=Otoño, 3=Invierno. |
| `year`        | INT          | `1`           | Año in-world (1+). |
| `created_at`  | TIMESTAMP    | CURRENT_TIMESTAMP | Cuándo se insertó por primera vez el meta. |
| `updated_at`  | TIMESTAMP    | CURRENT_TIMESTAMP ON UPDATE | Cuándo se modificó por última vez. |

### 2.3 Notas sobre el Esquema

- **thread_id como PK:** No hay FK explícita por diseño — MyBB podría borrar un hilo y no queremos que el DELETE falle por una restricción. La limpieza se maneja en `game_postcharacter_delete_thread`.
- **VARCHAR(20) para thread_type:** No se usa ENUM porque los tipos podrían extenderse en el futuro sin ALTER TABLE. La validación se hace en PHP contra un array.
- **day 1–65, season 0–3, year 1+:** Estaciones de 65 días, 4 estaciones por año = 260 días por año. El calendario se define en `rol_calendar_helpers.php`.

### 2.4 Archivo de Instalación

`back/forum/game/sql/install_schema_fragments.php:177-185`:

```php
'Metadatos de hilos' => "CREATE TABLE {$prefix}game_thread_meta (
    thread_id INT PRIMARY KEY,
    thread_type VARCHAR(20) NOT NULL DEFAULT 'Presente',
    day INT NOT NULL DEFAULT 1,
    season INT NOT NULL DEFAULT 0,
    year INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
```

Este fragmento se ejecuta como parte de la instalación completa del schema. Para foros ya existentes, se usa el script de migración dedicado.

---

## 3. Tipos de Hilo (`thread_type`)

### 3.1 Valores Válidos

Definidos en `game_postcharacter.php:834`:

```php
$allowed_types = ['Pasado','Presente','Mision','Evento','Trama','Fic','Off_Rol'];
```

| Valor       | Significado | Fecha in-world | Navegación | PP |
|-------------|-------------|----------------|------------|----|
| `Presente`  | La escena ocurre en el momento actual del calendario RPG. | Automática (cálculo global) | ✅ Permitida | ✅ Otorga |
| `Pasado`    | Flashback / escena que ocurrió antes del momento actual. | Manual (jugador elige fecha) | ❌ Bloqueada | ✅ Otorga |
| `Mision`    | Hilo oficial de misión creado por el sistema. | Automática (Presente) | ❌ Bloqueada | ✅ Otorga |
| `Evento`    | Hilo de evento global o estacional. | Manual | ❌ Bloqueada | ✅ Otorga |
| `Trama`     | Hilo de trama narrativa importante. | Manual | ❌ Bloqueada | ✅ Otorga |
| `Fic`       | Hilo fuera de continuidad / what-if / no canónico. | Manual | ❌ Bloqueada | ✅ Otorga |
| `Off_Rol`   | Hilo fuera de personaje (OOC / off-topic). | Manual | ❌ Bloqueada | ❌ No otorga |

### 3.2 Semántica de Cada Tipo

**Presente:**
- Es el tipo por defecto. Representa la línea temporal activa del foro.
- La fecha in-world se calcula automáticamente con la función `game_rol_days_at()` (ver sección 4).
- Es el **único** tipo que permite navegación marítima. Esto evita que los jugadores se desplacen en flashbacks o tramas no canónicas.
- El campo de fecha aparece deshabilitado en la UI cuando se selecciona "Presente", y se rellena automáticamente con la fecha global vía JS.

**Pasado:**
- Flashback narrativo. El jugador elige manualmente el día, estación y año.
- No afecta la línea temporal actual. Los eventos en un hilo "Pasado" no deberían tener consecuencias en el presente (aunque el staff puede decidir lo contrario).
- Las cartas y oráculos siguen funcionando, pero la navegación está deshabilitada.
- Típicamente usado para desarrollar el background de un personaje.

**Mision:**
- Hilo creado automáticamente por el sistema de misiones (`mission_helpers.php:239-243`).
- Siempre usa `Presente` + fecha automática.
- No se ofrece esta opción en el formulario de creación de hilo normal; solo el sistema la usa.
- Los hilos de misión tienen su propio contador de posts (`game_missions_active.post_count`).

**Evento:**
- Hilo para eventos globales, festivales, incursiones, etc.
- La fecha puede ser la actual o una futura/pasada, según el evento.
- El staff puede crear eventos y fijar una fecha in-world específica.

**Trama:**
- Hilo que forma parte de una trama argumental importante.
- Similar a "Evento" pero más enfocado a historia que a mecánica.
- Podría usarse para arcos narrativos largos.

**Fic:**
- Hilo "fan fiction" o fuera de continuidad.
- No canónico. No afecta el estado del mundo ni del personaje.
- El jugador puede experimentar libremente sin preocuparse por consecuencias.
- Los PP se otorgan igual (incentiva la escritura), pero las cartas y oráculos se procesan como en cualquier hilo no-Presente.

**Off_Rol:**
- Hilo fuera de personaje (Out Of Character).
- El sistema **bloquea la entrega de PP**. Esto es crítico para evitar farming de puntos en hilos OOC.
- La navegación está obviamente deshabilitada.
- Las cartas y oráculos no deberían procesarse (aunque el sistema no las bloquea explícitamente).

### 3.3 Template — Selector en la UI

En `front/templates/mybb/newthread/newthread.html:30-38`:

```html
<select name="game_thread_type" id="game_thread_type" class="rpg-form-input">
    <option value="Presente">Presente</option>
    <option value="Pasado">Pasado</option>
    <option value="Mision">Misión</option>
    <option value="Evento">Evento</option>
    <option value="Trama">Trama</option>
    <option value="Fic">Fic</option>
    <option value="Off_Rol">Off Rol</option>
</select>
```

Nota: `Mision` aparece en el selector pero su uso está pensado para el sistema automático, no para selección manual del usuario. El staff puede usarlo para crear hilos de misión manualmente.

### 3.4 Data Attribute en Templates

El `thread_type` se expone como `data-thread-type` en el contenedor `.rpg-system-container` de todas las páginas de posteo (newthread, newreply, showthread/quickreply):

```html
<div class="rpg-system-container" data-active-char-id="{$game_active_char_id}"
     data-forum-fid="{$game_post_forum_fid}"
     data-thread-type="{$game_thread_type}">
```

Esto permite que el JS del frontend (por ejemplo, el RPG System container) tome decisiones basadas en el tipo de hilo sin hacer una llamada AJAX extra.

### 3.5 Sobrescritura en Respuesta

Cuando un usuario responde en un hilo existente (`newreply.php`, `showthread.php`), el `$game_thread_type` se carga desde la base de datos (`game_postcharacter.php:982-984`):

```php
$metaQ = $db->query("SELECT thread_type FROM {$prefix}game_thread_meta WHERE thread_id = {$tid} LIMIT 1");
if ($metaRow = $db->fetch_array($metaQ)) {
    $game_thread_type = $metaRow['thread_type'];
}
```

Esto asegura que el sistema RPG sepa el tipo de hilo incluso en respuestas, donde no hay selector de tipo visible (se hereda del hilo padre).

---

## 4. Calendario In-World — Día, Estación, Año

### 4.1 El Calendario del Foro

El calendario RPG se define en `rol_calendar_helpers.php` y se replica en múltiples lugares (plugin, mission_helpers, etc.). Sus constantes:

| Constante        | Valor | Significado |
|------------------|-------|-------------|
| Días por estación | 65    | Primavera, Verano, Otoño, Invierno |
| Estaciones por año | 4    | 0=Primavera, 1=Verano, 2=Otoño, 3=Invierno |
| Días por año      | 260   | 65 × 4 |
| Época (epoch)     | `2026-05-01` | Fecha real en que comenzó el calendario RPG |
| Factor de tiempo  | 1.5   | 1 día real = 1.5 días in-world |

### 4.2 Cálculo Automático (Modo Presente)

**Función base — `game_rol_days_at()`:**

```php
function game_rol_days_at(?int $timestamp = null): int
{
    $epoch = strtotime('2026-05-01');
    $now = $timestamp ?? time();
    $diffSeconds = max(0, $now - $epoch);
    $diffDaysFloat = $diffSeconds / 86400;
    return (int)floor($diffDaysFloat * 1.5) + 1;
}
```

**Desglose:**
1. Se calculan los segundos transcurridos desde el epoch (`2026-05-01 00:00:00`).
2. Se convierten a días reales (`/ 86400`).
3. Se multiplica por el factor 1.5 para obtener días in-world.
4. Se suma 1 (el día 1 es el primer día del calendario).

**Descomposición a día/estación/año:**

```php
$days_per_season = 65;
$days_per_year = $days_per_season * 4;  // 260

$year = floor(($rol_days - 1) / $days_per_year) + 1;
$day_of_year = (($rol_days - 1) % $days_per_year) + 1;
$season = floor(($day_of_year - 1) / $days_per_season);          // 0-3
$day = (($day_of_year - 1) % $days_per_season) + 1;             // 1-65
```

**Ejemplo:** Si `rol_days = 100`:
- `year = floor(99 / 260) + 1 = 1`
- `day_of_year = 99 % 260 + 1 = 100`
- `season = floor(99 / 65) = 1` (Verano)
- `day = 99 % 65 + 1 = 35`
- Resultado: "Día 35 de Verano, Año 1"

### 4.3 Etiqueta Legible

`game_rol_date_label()` produce una cadena humanamente legible:

```php
function game_rol_date_label(int $rolDays): string
{
    // ... mismo cálculo ...
    $seasons = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
    $season = $seasons[$seasonIdx] ?? 'Desconocida';
    return "Día {$rolDay} de {$season}, Año {$rolYear}";
}
```

### 4.4 Fecha Manual (Modo No-Presente)

Cuando `thread_type` no es `Presente`, el jugador introduce manualmente los valores:

```php
$day = max(1, min(100, (int)($_POST['game_day'] ?? 1)));
$season = max(0, min(3, (int)($_POST['game_season'] ?? 0)));
$year = max(1, (int)($_POST['game_year'] ?? 1));
```

- `day` se permite hasta 100 (aunque el máximo canónico es 65). Esto da flexibilidad para estaciones extendidas si el staff lo decide.
- `season` se fuerza al rango 0-3.
- `year` se fuerza a ≥ 1.

### 4.5 Visualización en el Header Global

La fecha global se muestra en el header del índice del foro (`game_postcharacter_global_date()`):

```php
$date_full = "Día {$rol_day} de {$current_season}, Año {$rol_year}";
// Renderizado en game-hero-date
```

Esto da a todos los jugadores una referencia visual constante del momento actual en el mundo.

### 4.6 Discrepancias entre Implementaciones

Existen **dos versiones** del cálculo de fecha global:

1. **`game_postcharacter_global_date()` (plugin):**
   ```php
   $diff_days = max(0, floor(($now - $epoch) / 86400));
   $rol_days = ($diff_days * 2) + 1;     // factor 2.0, no 1.5
   $rol_year = floor(($rol_days - 1) / 400) + 1;  // 400 días/año
   $season_idx = floor(($day_of_year - 1) / 100);
   ```
   Usa factor 2.0 y 400 días/año. Es la versión legacy y solo se usa para el display del header.

2. **`rol_calendar_helpers.php` (módulo game/):**
   ```php
   $rol_days = (int)floor($diffDaysFloat * 1.5) + 1;  // factor 1.5
   $daysPerYear = 65 * 4;  // 260
   ```
   Usa factor 1.5 y 260 días/año. Es la versión actual usada en todos los subsistemas.

**Nota:** La discrepancia es conocida pero no crítica, porque el header global es meramente decorativo. Las misiones, navegación y diario usan la versión correcta (1.5 / 260).

---

## 5. Flujo de Establecimiento

### 5.1 Creación de Hilo por un Jugador

Cuando un usuario crea un nuevo hilo (`newthread.php`):

1. **Hook `newthread_do_newthread_end`** dispara `game_postcharacter_save_thread()`.
2. El plugin detecta `$_POST['game_thread_type']`.
3. Valida el tipo contra la whitelist.
4. Según el tipo, calcula o recoge la fecha:
   - Si `Presente`: cálculo automático.
   - Si otro: valores de `$_POST['game_day']`, `$_POST['game_season']`, `$_POST['game_year']`.
5. Ejecuta INSERT ... ON DUPLICATE KEY UPDATE.

**Código completo del flujo** (`game_postcharacter.php:832-859`):

```php
if (isset($_POST['game_thread_type'])) {
    $allowed_types = ['Pasado','Presente','Mision','Evento','Trama','Fic','Off_Rol'];
    $type = in_array($_POST['game_thread_type'], $allowed_types)
        ? $_POST['game_thread_type'] : 'Presente';

    if ($type === 'Presente') {
        // Cálculo automático de fecha global
        $epoch = strtotime('2026-05-01');
        $now = time();
        $diff_seconds = max(0, $now - $epoch);
        $diff_days_float = $diff_seconds / 86400;
        $rol_days = floor($diff_days_float * 1.5) + 1;
        // ... descomposición a day/season/year ...
    } else {
        // Valores manuales del formulario
        $day = max(1, min(100, (int)($_POST['game_day'] ?? 1)));
        $season = max(0, min(3, (int)($_POST['game_season'] ?? 0)));
        $year = max(1, (int)($_POST['game_year'] ?? 1));
    }

    $db->write_query("INSERT INTO {$prefix}game_thread_meta
        (thread_id, thread_type, day, season, year)
        VALUES ({$tid}, '{$db->escape_string($type)}', {$day}, {$season}, {$year})
        ON DUPLICATE KEY UPDATE
        thread_type='{$db->escape_string($type)}', day={$day},
        season={$season}, year={$year}");
}
```

### 5.2 Manejo del Lado Cliente (JS)

En `newthread.html:340-429`, un script inline maneja la interacción del selector:

```javascript
function onTypeChange() {
    if (sel.value === 'Presente') {
        fillDate(getGlobalDate());   // Rellena con fecha global vía AJAX
        setInputsDisabled(true);     // Deshabilita inputs
    } else {
        clearDate();                 // Limpia inputs para entrada manual
        setInputsDisabled(false);    // Habilita inputs
    }
    syncHiddenInputs();              // Sincroniza hidden fields
    // Deshabilita navegación si no es Presente
    var navBtn = document.getElementById('rpg-tab-btn-navegacion');
    if (navBtn) {
        if (sel.value === 'Presente') {
            navBtn.classList.remove('is-disabled');
        } else {
            navBtn.classList.add('is-disabled');
            navBtn.title = 'Navegación solo disponible en hilos de tipo Presente.';
            navBtn.onclick = function(e) { e.preventDefault(); };
        }
    }
}
```

**Mecanismo de hidden fields:**

El formulario tiene inputs visibles (`game_day`, `game_season`, `game_year`) más hidden fields que se sincronizan. Cuando el tipo es `Presente`, los inputs visibles se deshabilitan y sus valores se copian a los hidden fields. Cuando no es Presente, los inputs visibles recuperan su nombre y envían los datos directamente.

Esto evita que los inputs deshabilitados envíen datos vacíos al servidor.

### 5.3 Creación de Hilo por el Sistema de Misiones

Cuando el sistema acepta una misión y crea el hilo automáticamente (`mission_helpers.php:224-243`):

1. No hay formulario ni POST — todo es server-side.
2. Siempre se usa `Presente` con fecha automática.
3. Se ejecuta el mismo INSERT ... ON DUPLICATE KEY UPDATE.

```php
$rol_days = (int)floor($diff_days_float * 1.5) + 1;
// ... descomposición ...
$db->write_query("
    INSERT INTO {$prefix}game_thread_meta (thread_id, thread_type, day, season, year)
    VALUES ({$tid}, 'Presente', {$day}, {$season}, {$year})
    ON DUPLICATE KEY UPDATE thread_type='Presente', day={$day}, season={$season}, year={$year}
");
```

Esto garantiza que los hilos de misión siempre estén anclados al presente del calendario global.

### 5.4 Respuesta en Hilo Existente

En respuestas (`newreply.php` y `showthread.php` quick reply), NO se reescribe el `thread_meta`. El meta se estableció durante la creación del hilo y permanece inmutable. La respuesta solo **lee** el meta para:

1. Pasar `$game_thread_type` a la template (para `data-thread-type`).
2. Determinar si se otorgan PP (bloqueado para Off_Rol).
3. Decidir si la navegación está disponible.

---

## 6. Flujo en Respuesta

### 6.1 Lectura del Meta en Global Setup

`game_postcharacter_global_setup()` (`game_postcharacter.php:958-1005`) se ejecuta en cada página de posteo (newreply, newthread, showthread):

1. Detecta el script actual.
2. Si es `newreply.php` o `showthread.php`, obtiene el `tid` y consulta:
   ```php
   $metaQ = $db->query("SELECT thread_type FROM {$prefix}game_thread_meta WHERE thread_id = {$tid} LIMIT 1");
   ```
3. Si existe meta, asigna `$game_thread_type = $metaRow['thread_type']`.
4. Si no existe (hilo legacy), `$game_thread_type` queda vacío.
5. Si es `newthread.php`, no hay meta previa, se deja vacío y el JS del formulario se encarga.

### 6.2 Variable Global $game_thread_type

Definida como global en el plugin y pasada al sistema de templates de MyBB. Está disponible en todas las templates de posteo como `{$game_thread_type}`.

Se usa en:
- `data-thread-type` en `.rpg-system-container`.
- Condicionales en templates para mostrar/ocultar elementos.
- Evaluación del JS en el frontend (por ejemplo, `data-thread-type` leído por `rpg-system.js`).

### 6.3 Persistencia Cruzada

Cuando un usuario escribe un post en un hilo que NO es el primero (no es creación), el `game_thread_type` de ese post se asocia correctamente porque el meta se lee del hilo padre. El post individual no tiene su propio `thread_type` — hereda el del hilo.

---

## 7. Uso en Navegación

### 7.1 Guardia en PHP

`navigation_process.php:19-26` contiene una guardia crítica:

```php
// Navigation only allowed in Presente threads
$metaQ = $db->query("SELECT thread_type FROM {$prefix}game_thread_meta
    WHERE thread_id = " . (int)$threadId . " LIMIT 1");
if ($metaRow = $db->fetch_array($metaQ)) {
    if ($metaRow['thread_type'] !== 'Presente') {
        return null;  // Bloquea la navegación silenciosamente
    }
}
```

Si el hilo no es `Presente`, la función `game_navigation_process_post()` retorna `null` sin insertar el viaje ni generar eventos. Esto significa que:

- Los jugadores **no pueden** moverse entre islas en flashbacks.
- Los jugadores **no pueden** moverse en hilos OOC.
- Los jugadores **no pueden** moverse en misiones (que también se crean como `Presente`, así que SÍ pueden — esto es intencional: las misiones ocurren en el presente y los personajes pueden desplazarse durante ellas).

### 7.2 Feedback Visual en Frontend

El JS del formulario de nuevo hilo deshabilita el botón de navegación si el tipo seleccionado no es `Presente`:

```javascript
var navBtn = document.getElementById('rpg-tab-btn-navegacion');
if (sel.value === 'Presente') {
    navBtn.classList.remove('is-disabled');
    navBtn.title = '';
    navBtn.onclick = null;
} else {
    navBtn.classList.add('is-disabled');
    navBtn.title = 'Navegación solo disponible en hilos de tipo Presente.';
    navBtn.onclick = function(e) { e.preventDefault(); };
}
```

Esto proporciona feedback inmediato al usuario sin necesidad de recargar la página.

### 7.3 Lógica de Negocio

**¿Por qué solo Presente permite navegación?**
- La navegación marítima tiene consecuencias mecánicas (cambia la ubicación del personaje, genera eventos, afecta la posición en el mundo).
- Permitir navegación en flashbacks o hilos no canónicos rompería la consistencia del mundo. Un personaje no puede cambiar el pasado.
- Las misiones son Presente, así que los PJs pueden navegar durante misiones, lo cual tiene sentido narrativo.

---

## 8. Uso en Sistema de Misiones

### 8.1 Establecimiento del Meta

Al aceptar una misión, `mission_helpers.php:239-243` inserta el meta automáticamente. El flujo completo:

1. Se crea el hilo MyBB con `PostDataHandler`.
2. Se obtiene el `tid` del hilo recién insertado.
3. Se calcula el día/estación/año actual (Presente).
4. Se inserta en `game_thread_meta`.

### 8.2 Relación con game_missions_active

La tabla `game_missions_active` tiene una columna `tid` que referencia al hilo de la misión. Aunque no hay FK explícita, la integridad referencial se mantiene a nivel de aplicación:

- Al crear misión → se crea hilo + se inserta meta + se inserta active.
- Al completar misión → se actualiza el estado de active, el meta del hilo no se modifica.
- Al borrar hilo → `game_postcharacter_delete_thread` limpia `game_post_characters` y personajes, pero NO borra `game_thread_meta` ni `game_missions_active` (limpieza manual del staff).

### 8.3 Consultas Cruzadas

`latest_activity.php:53-61` hace LEFT JOIN entre `threads`, `game_thread_meta` y `threadprefixes` para mostrar información de hilos activos (incluyendo misiones):

```sql
SELECT t.tid, t.subject, tm.thread_type, tp.prefix as mybb_prefix
FROM {$prefix}threads t
LEFT JOIN {$prefix}game_thread_meta tm ON t.tid = tm.thread_id
LEFT JOIN {$prefix}threadprefixes tp ON t.prefix = tp.pid
WHERE t.visible = 1 AND t.closed != 1
ORDER BY t.lastpost DESC
LIMIT 10
```

Esto permite al frontend mostrar el tipo de hilo (por ejemplo, "Misión Rango C" o "Evento Especial") en la lista de actividad reciente.

---

## 9. Integración con Diario / Log

### 9.1 Endpoint AJAX `get_thread_diary_data.php`

Este endpoint expone los metadatos del hilo junto con participantes para ser consumido por el sistema de diario/cronología.

**Código clave (`get_thread_diary_data.php:43-72`):**

```php
// Fetch thread meta (type + date)
$mq = $db->query("SELECT * FROM {$prefix}game_thread_meta WHERE thread_id = {$tid} LIMIT 1");
$meta = $db->fetch_array($mq);

// Fetch participants (characters involved in this thread)
$participants = [];
$pq = $db->query("
    SELECT DISTINCT gpc.character_id, p.name
    FROM {$prefix}game_post_characters gpc
    JOIN {$prefix}game_personajes p ON gpc.character_id = p.id
    WHERE gpc.thread_id = {$tid}
    ORDER BY gpc.character_id ASC
");

$data = [
    'thread_id'    => $tid,
    'thread_name'  => $thread['subject'],
    'thread_uid'   => (int)$thread['uid'],
    'category'     => $meta ? $meta['thread_type'] : 'Presente',
    'day'          => $meta ? (int)$meta['day'] : 1,
    'season'       => $meta ? (int)$meta['season'] : 0,
    'year'         => $meta ? (int)$meta['year'] : 1,
    'participants' => $participants
];
```

**Respuesta JSON típica:**

```json
{
    "ok": true,
    "data": {
        "thread_id": 42,
        "thread_name": "El misterio del mapa antiguo",
        "thread_uid": 5,
        "category": "Pasado",
        "day": 17,
        "season": 2,
        "year": 3,
        "participants": [
            {"pj_id": 1, "name": "Monkey D. Luffy"},
            {"pj_id": 7, "name": "Roronoa Zoro"}
        ]
    },
    "error": null,
    "meta": null
}
```

### 9.2 Uso en la Cronología del Personaje

El sistema de diario/cronología (`cronologia_json` en `game_personajes`) puede referenciar hilos por su `thread_id`. Cuando se renderiza la línea de tiempo del personaje, el frontend puede:

1. Llamar a `get_thread_diary_data.php?thread_id=N`.
2. Obtener el tipo de hilo, fecha in-world y participantes.
3. Mostrar la entrada en orden cronológico world-wise (no real-time).

Esto permite que la cronología del personaje refleje el tiempo in-world, no el tiempo real del servidor.

### 9.3 Defaults cuando no Hay Meta

Si un hilo no tiene registro en `game_thread_meta` (hilos anteriores a la migración), los defaults son:

- `category`: `'Presente'`
- `day`: `1`
- `season`: `0`
- `year`: `1`

Esto garantiza que ningún hilo rompa la UI del diario aunque sea legacy.

---

## 10. Integración con Actividad Reciente

### 10.1 Endpoint AJAX `latest_activity.php`

El endpoint de actividad reciente (`latest_activity.php:53-71`) usa `game_thread_meta` para enriquecer la lista de hilos activos:

```sql
SELECT t.tid, t.subject, tm.thread_type, tp.prefix as mybb_prefix
FROM {$prefix}threads t
LEFT JOIN {$prefix}game_thread_meta tm ON t.tid = tm.thread_id
LEFT JOIN {$prefix}threadprefixes tp ON t.prefix = tp.pid
WHERE t.visible = 1 AND t.closed != 1
ORDER BY t.lastpost DESC
LIMIT 10
```

**Propósito:** Mostrar en la página de inicio (o en widgets de sidebar) los hilos más recientes con su tipo y prefijo. Ejemplo de salida:

```json
{
    "ok": true,
    "data": {
        "latest_posts": [...],
        "active_missions": [
            {
                "tid": 45,
                "subject": "Cazar al Kraken",
                "type": "tp: Mision | tm: Presente",
                "link": "http://.../showthread.php?tid=45"
            }
        ],
        "staff": [...]
    }
}
```

La cadena `type` muestra tanto el prefijo MyBB (`tp: ...`) como el tipo de meta (`tm: ...`), permitiendo identificar hilos que son misiones pero no tienen prefijo MyBB, o viceversa.

---

## 11. Integración con PP

### 11.1 Bloqueo de PP para Off_Rol

En `game_postcharacter_award_pp()` (`game_postcharacter.php:1253-1280`), se consulta `thread_type` para decidir si se entregan PP:

```php
$is_off_rol = false;
if ($tid > 0) {
    $meta_q = $db->simple_select('game_thread_meta', 'thread_type', "thread_id = {$tid}", ['limit' => 1]);
    if ($meta = $db->fetch_array($meta_q)) {
        if ($meta['thread_type'] === 'Off_Rol') {
            $is_off_rol = true;
        }
    } else {
        // Fallback: si no hay meta, comprobar el POST directamente
        if (isset($_POST['game_thread_type']) && $_POST['game_thread_type'] === 'Off_Rol') {
            $is_off_rol = true;
        }
    }
}

if ($is_off_rol) {
    return;  // No otorga PP
}
```

**Lógica:**
1. Busca el meta en la BD.
2. Si existe y es `Off_Rol`, bloquea PP.
3. Si no existe (posible race condition en hilo recién creado donde el meta no se ha persistido aún), comprueba `$_POST['game_thread_type']`.
4. Si es Off_Rol, retorna sin otorgar.

### 11.2 ¿Por qué solo Off_Rol bloquea PP?

Los hilos de tipo `Fic`, `Pasado`, `Evento`, etc. también otorgan PP. La razón es:

- **Fic:** Aunque no es canónico, el jugador está escribiendo. Queremos incentivar la escritura.
- **Pasado:** Es narrativa válida y contribuye al lore del personaje.
- **Evento/Trama:** Son contenido canónico que merece recompensa.
- **Off_Rol:** No es rol. No hay personaje involucrado. No hay narrativa que recompensar.

### 11.3 Race Condition y Doble Guardia

El fallback a `$_POST` existe porque:

1. El hook `newthread_do_newthread_end` primero ejecuta `game_postcharacter_save_thread()` (que escribe el meta).
2. Luego ejecuta `game_postcharacter_award_pp()`.
3. En teoría, el meta ya está escrito. Pero en caso de error de BD, el meta podría no persistirse. El POST es la fuente original de la verdad.

Esta doble guardia asegura que incluso en condiciones de fallo, no se otorguen PP indebidamente.

---

## 12. Migración y Mantenimiento

### 12.1 Script de Migración

`back/forum/game/sql/migrate_thread_meta.php` — Script ejecutable vía navegador (requiere permisos de admin + PJ staff).

**Estructura:**
1. Verifica autenticación y permisos de staff.
2. Crea la tabla si no existe (`CREATE TABLE IF NOT EXISTS`).
3. Muestra mensaje de éxito o error.

```php
$db->write_query("CREATE TABLE IF NOT EXISTS {$prefix}game_thread_meta (
    thread_id INT PRIMARY KEY,
    thread_type VARCHAR(20) NOT NULL DEFAULT 'Presente',
    day INT NOT NULL DEFAULT 1,
    season INT NOT NULL DEFAULT 0,
    year INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
```

**Nota:** Este script NO migra datos existentes. Solo crea la tabla. Los hilos nuevos reciben meta automáticamente al crearse. Los hilos legacy no tienen meta y se les asignan defaults en las consultas (Presente, día 1, season 0, año 1).

### 12.2 Fragmento de Instalación

En `install_schema_fragments.php`, la tabla se define junto con todo el schema RPG. El orden de creación es:

1. `game_personajes`
2. `game_user_config`
3. `game_forum_islands`
4. ... (otras tablas) ...
5. **`game_thread_meta`** (décima tabla aprox.)
6. `game_card_requests`
7. ... etc.

### 12.3 Limpieza al Borrar Hilo

`game_postcharacter_delete_thread()` NO borra el registro de `game_thread_meta`. Esto es intencional por dos razones:

1. **Preservación de datos:** Si un hilo se borra por error, el meta aún existe y puede recuperarse.
2. **Auditoría:** El staff puede saber qué tipo de hilo fue, incluso después de borrado.

Si se desea una limpieza completa, debe hacerse manualmente o con un script de mantenimiento.

---

## 13. Filosofía de Diseño

### 13.1 ¿Por qué una tabla separada?

Podríamos haber añadido `thread_type`, `day`, `season`, `year` como columnas en `mybb_threads`. Se eligió una tabla separada por:

- **No contaminar el core de MyBB.** MyBB actualiza su esquema en cada versión. Tener columnas adicionales en `threads` aumenta el riesgo de conflictos en upgrades.
- **Separación de concerns.** `mybb_threads` gestiona el hilo como objeto del foro (visibilidad, estado, fechas reales). `game_thread_meta` gestiona el hilo como elemento narrativo (tipo, fecha in-world).
- **Escalabilidad.** Si en el futuro se añaden más metadatos narrativos (por ejemplo, `location_id`, `arc_id`, `mood`), se añaden a esta tabla sin tocar el esquema de MyBB.

### 13.2 ¿Por qué VARCHAR(20) y no ENUM?

- **Flexibilidad.** Un ENUM requiere ALTER TABLE para añadir nuevos tipos. Con VARCHAR + validación en PHP, añadir un nuevo tipo es solo añadirlo a un array.
- **Portabilidad.** VARCHAR funciona igual en MySQL, MariaDB, PostgreSQL. ENUM es específico de MySQL.
- **Depuración.** En logs y queries, ves el string directamente sin tener que recordar qué número corresponde a qué tipo.

### 13.3 ¿Por qué la navegación solo en Presente?

La navegación es una mecánica con consecuencias duraderas en el mundo del juego:
- Cambia la ubicación del personaje en el mapamundi.
- Genera eventos que pueden afectar al viaje.
- Consume recursos (barco, provisiones).

Permitir navegación en un flashback o en un hilo Fic crearía inconsistencias: ¿el personaje se desplazó en el pasado o en el presente? ¿Cómo reconcilian los eventos del flashback con la línea temporal actual?

Restringir navegación a hilos `Presente` es una decisión de diseño que mantiene la integridad del mundo.

### 13.4 ¿Por qué las misiones son siempre Presente?

- **Simplicidad:** No necesitamos un selector de fecha para misiones automáticas. Siempre ocurren en el "ahora".
- **Mecánica:** Las misiones tienen plazos y recompensas que dependen del estado actual del mundo.
- **Narrativa:** Una misión es un encargo del Narrador que ocurre en el presente del juego.

### 13.5 ¿Por qué PP en hilos no-canónicos (Fic)?

La escritura es el core del RPG. Incluso en hilos Fic (fan fiction, what-if), el jugador está escribiendo, desarrollando su prosa, explorando personajes. Negar PP en Fic desincentivaría la escritura.

La distinción clave:
- **Fic:** El jugador escribe con su personaje en una situación hipotética. Hay esfuerzo narrativo. → PP sí.
- **Off_Rol:** El jugador habla como usuario, no como personaje. No hay narrativa. → PP no.

### 13.6 Decisión de Diseño sobre la Fecha Manual

Cuando el tipo no es Presente, la fecha es **completamente manual**. No hay validación de coherencia temporal (por ejemplo, "no puedes poner un flashback en el futuro"). Esto es intencional:

- **Confianza en el jugador:** Asumimos que los jugadores son responsables con la cronología.
- **Flexibilidad narrativa:** Un "recuerdo profético" o una "visión del futuro" sería un Pasado o Fic con fecha futura. No tenemos por qué prohibirlo.
- **Simplicidad técnica:** Validar coherencia temporal requeriría conocer toda la línea de tiempo del personaje, lo cual es complejo y costoso.

---

## 14. Consejos para Jugadores

### 14.1 Eligiendo el Tipo de Hilo

| Quieres... | Usa... |
|------------|--------|
| Una escena que avanza la historia principal | `Presente` |
| Un recuerdo del pasado de tu personaje | `Pasado` |
| Una historia alternativa / what-if | `Fic` |
| Hablar con otros jugadores fuera de personaje | `Off_Rol` |
| Un hilo temático de evento del foro | `Evento` o `Trama` |
| Responder a una misión automática | El sistema lo pone como `Mision` |

### 14.2 Fecha en Flashbacks

Cuando escribas un `Pasado`:
- Piensa en la cronología de tu personaje. ¿Cuándo ocurrió esto realmente?
- Consulta el calendario global para saber en qué año/estación estás ahora y así situar el flashback correctamente.
- No te preocupes por ser exacto al día. El staff rara vez valida la precisión del día en flashbacks. El año y la estación son más importantes.

### 14.3 Off_Rol y PP

Si creas un hilo `Off_Rol`, **no ganarás PP** por los posts que escribas allí. Usa `Off_Rol` solo para:
- Anuncios entre jugadores.
- Coordinación fuera de personaje.
- Charla OOC.

Si quieres escribir sin consecuencias pero ganando PP, usa `Fic`.

### 14.4 Navegación y Tipo de Hilo

Recuerda: la navegación solo funciona en hilos `Presente`. Si estás en medio de un viaje y quieres hacer un flashback, no podrás navegar en ese flashback. Planifica tus viajes en consecuencia:

1. Crea el hilo `Presente` para el viaje.
2. Durante el viaje, si necesitas un flashback, crea un hilo separado `Pasado`.
3. El personaje sigue en el viaje (Presente) mientras el flashback ocurre en otro hilo.

---

## 15. Consejos para Staff

### 15.1 Revisión de Hilos

Cuando revises un hilo nuevo:
- **Verifica que el tipo sea coherente.** Si es un flashback pero el jugador lo marcó como `Presente`, pídele que lo corrija.
- **Verifica la fecha en flashbacks.** No necesita ser exacta, pero sí coherente con la biografía del personaje.
- **Off_Rol debe ser obvio.** Si un hilo marcado como `Off_Rol` tiene contenido narrativo, considera cambiarlo a `Fic` para que el jugador gane PP.

### 15.2 Gestión de Misiones

- Los hilos de misión se crean automáticamente como `Mision`/`Presente`. Si por alguna razón el meta no se insertó (error de BD), puedes insertarlo manualmente:
  ```sql
  INSERT INTO mybb_game_thread_meta (thread_id, thread_type, day, season, year)
  VALUES (N, 'Mision', 1, 0, 1)
  ON DUPLICATE KEY UPDATE thread_type='Mision';
  ```
- Las misiones completadas mantienen su meta. No es necesario modificarlo.

### 15.3 Hilos Legacy

Los hilos creados antes de la migración no tienen meta. El sistema asigna defaults (`Presente`, día 1, season 0, año 1). Si un hilo legacy debería tener un tipo específico, puedes insertarlo manualmente:

```sql
INSERT IGNORE INTO mybb_game_thread_meta (thread_id, thread_type, day, season, year)
VALUES (N, 'Pasado', 15, 2, 2);
```

### 15.4 Mantenimiento

- **Monitorea `game_thread_meta`** periódicamente para detectar hilos huérfanos (thread_id que ya no existe en `mybb_threads`).
- **Limpieza opcional:**
  ```sql
  DELETE tm FROM mybb_game_thread_meta tm
  LEFT JOIN mybb_threads t ON tm.thread_id = t.tid
  WHERE t.tid IS NULL;
  ```
- **No es obligatorio:** Los registros huérfanos son inofensivos (ocupan pocos KB), pero es buena práctica limpiarlos.

### 15.5 Depuración de Problemas

| Problema | Causa probable | Solución |
|----------|---------------|----------|
| El meta no se guarda al crear hilo | `$_POST['game_thread_type']` no se envía | Verificar template newthread.html; el campo puede no estar presente en ciertos themes |
| La navegación no aparece en hilo Presente | `thread_type` no es exactamente `Presente` | Verificar el valor en BD: `SELECT thread_type FROM mybb_game_thread_meta WHERE thread_id = N` |
| PP se otorgan en hilo Off_Rol | El meta no se guardó antes de award_pp | Race condition rara; verificar orden de hooks |
| Fecha automática incorrecta | Discrepancia entre implementaciones de calendario | Asegurar que `rol_calendar_helpers.php` es la fuente de verdad |

---

## 16. Guía de Troubleshooting

### 16.1 El meta del hilo no aparece en la BD

**Síntomas:** El hilo existe, pero `SELECT * FROM game_thread_meta WHERE thread_id = N` no devuelve filas.

**Causas:**
1. Hilo creado antes de la migración (`migrate_thread_meta.php` nunca se ejecutó).
2. Error de PHP durante `game_postcharacter_save_thread()` (por ejemplo, excepción no capturada).
3. El hook `newthread_do_newthread_end` no se disparó (plugin desactivado o error de hook).

**Solución:**
1. Insertar manualmente el meta (ver sección 15.3).
2. Revisar los logs de error de PHP.
3. Verificar que el plugin `game_postcharacter` está activo en el ACP de MyBB.

### 16.2 La navegación no funciona aunque el hilo es Presente

**Síntomas:** El botón de navegación aparece pero al enviar el post no se crea el viaje.

**Causas:**
1. `thread_type` no es exactamente `Presente` (puede tener espacios extra o mayúsculas incorrectas).
2. La consulta en `navigation_process.php:21` no encuentra el meta porque la tabla no existe o el thread_id no está.

**Solución:**
1. Verificar el valor exacto en BD.
2. Ejecutar el script de migración si la tabla no existe.
3. Comprobar logs de MySQL para errores de sintaxis en la query.

### 16.3 PP otorgados en hilo Off_Rol

**Síntomas:** Jugadores ganan PP en hilos marcados como Off_Rol.

**Causas:**
1. Race condition: `award_pp()` se ejecuta antes de que `save_thread()` persista el meta, y el fallback a `$_POST['game_thread_type']` falla porque el campo no se envió correctamente.
2. El meta se actualizó después de award_pp (por ejemplo, un script externo modificó el thread_type).

**Solución:**
1. Verificar el orden de hooks en `game_postcharacter.php`.
2. Añadir logging en award_pp para depurar.
3. Si el daño ya está hecho, se pueden descontar PP manualmente.

### 16.4 Fecha automática no coincide con el calendario global

**Síntomas:** La fecha en el header del foro muestra un valor diferente a la fecha calculada en el meta del hilo.

**Causa:** Discrepancia entre `game_postcharacter_global_date()` (factor 2.0, 400 días/año) y `rol_calendar_helpers.php` (factor 1.5, 260 días/año).

**Solución:**
- Para el header global: es decorativo, la discrepancia es aceptable.
- Para cálculos mecánicos (misiones, navegación): asegurarse de que todos los subsistemas usan `rol_calendar_helpers.php`.
- Si se desea consistencia total, actualizar `global_date()` para usar el mismo factor.

### 16.5 Error al ejecutar migrate_thread_meta.php

**Síntomas:** "Acceso denegado" o error 403.

**Causa:** El usuario no tiene permisos de admin (`cancp`) o no tiene un PJ staff activo.

**Solución:**
1. Iniciar sesión con una cuenta que tenga `cancp = 1`.
2. Asegurarse de tener un personaje con `is_staff = 1` y `staff_level >= 1`.
3. Si es necesario, ejecutar la migración desde la línea de comandos con phpMyAdmin o similar.

---

## APÉNDICE A: Archivos del Subsistema

```
back/forum/
├── inc/plugins/game_postcharacter.php          # Plugin: save_thread, global_setup, award_pp
├── game/
│   ├── sql/
│   │   ├── install_schema_fragments.php         # Schema: CREATE TABLE game_thread_meta
│   │   └── migrate_thread_meta.php              # Migración: CREATE TABLE IF NOT EXISTS
│   ├── inc/
│   │   ├── navigation_process.php               # Guardia: solo Presente permite navegación
│   │   ├── mission_helpers.php                  # Inserción automática al crear misión
│   │   └── rol_calendar_helpers.php             # Cálculo de fecha in-world
│   └── ajax/
│       ├── get_thread_diary_data.php            # Expone meta vía JSON para diario
│       └── latest_activity.php                  # LEFT JOIN con meta para actividad reciente
├── front/templates/mybb/
│   ├── newthread/newthread.html                 # Selector de tipo + inputs de fecha + JS
│   ├── newreply/newreply.html                   # data-thread-type desde meta leído
│   ├── showthread/showthread_quickreply.html    # data-thread-type desde meta leído
│   └── posting/_rpg_system_block.html           # Contenedor con data-thread-type
└── packages/contracts/
    └── templates/newthread_with_type.xml        # Template de referencia para nuevos foros
```

---

## APÉNDICE B: Contratos API (OpenAPI)

### `GET /game/ajax/get_thread_diary_data.php?thread_id=N`

**Request:**
```http
GET /game/ajax/get_thread_diary_data.php?thread_id=42 HTTP/1.1
```

**Response (200):**
```json
{
    "ok": true,
    "data": {
        "thread_id": 42,
        "thread_name": "Título del hilo",
        "thread_uid": 5,
        "category": "Presente",
        "day": 27,
        "season": 1,
        "year": 3,
        "participants": [
            {"pj_id": 1, "name": "Personaje A"},
            {"pj_id": 2, "name": "Personaje B"}
        ]
    },
    "error": null,
    "meta": null
}
```

**Errores:**
- `400`: thread_id o url inválido.
- `404`: Hilo no encontrado.

### `GET /game/ajax/latest_activity.php`

**Request:**
```http
GET /game/ajax/latest_activity.php HTTP/1.1
```

**Response (200):**
```json
{
    "ok": true,
    "data": {
        "active_missions": [
            {
                "tid": 45,
                "subject": "Misión de prueba",
                "type": "tp: null | tm: Presente",
                "link": "http://.../showthread.php?tid=45"
            }
        ]
    },
    "error": null
}
```

---

*Fin del documento — Guía completa del Sistema de Hilos — Metadatos v1.0*
*Generado desde: `Guias/sistemas/25-hilos-meta.md`*
*Referencia: `Guias/MAESTRO_SISTEMAS_RPG.md` — Sección 25*
