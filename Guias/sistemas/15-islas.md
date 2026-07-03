# 15. Islas-Foros

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 15
> **Propósito:** Documentar exhaustivamente el subsistema de islas-foro: modelo de datos, relación 1:1 con foros MyBB, campos de isla, sistema de control territorial, gestión de staff, integración con navegación y tripulaciones, filosofía de diseño y consejos para jugadores y staff.

---

## ÍNDICE

1. [Arquitectura General — Islas como Foros](#1-arquitectura-general)
2. [Modelo de Datos — Tabla `game_forum_islands`](#2-modelo-de-datos)
3. [Catálogo Completo de Campos](#3-catálogo-completo-de-campos)
4. [Ciclo de Vida de una Isla](#4-ciclo-de-vida-de-una-isla)
5. [Sistema de Control Territorial](#5-sistema-de-control-territorial)
6. [Gestión de Staff — Interfaz y AJAX](#6-gestión-de-staff)
7. [Integración con Navegación](#7-integración-con-navegación)
8. [Integración con Tripulaciones](#8-integración-con-tripulaciones)
9. [Integración con Personajes](#9-integración-con-personajes)
10. [Filosofía de Diseño](#10-filosofía-de-diseño)
11. [Consejos para Staff](#11-consejos-para-staff)
12. [Consejos para Jugadores](#12-consejos-para-jugadores)
13. [Guía de Troubleshooting](#13-guía-de-troubleshooting)

---

## 1. Arquitectura General

### 1.1 Concepto Fundamental: Cada Foro es una Isla

El sistema de islas-foro se basa en un mapeo **1:1** entre los foros (subforos) de MyBB y las ubicaciones del mundo de rol. Cada subforo de tipo `'f'` (forum) en la tabla `mybb_forums` puede tener un registro correspondiente en `mybb_game_forum_islands`.

**¿Por qué 1:1?**

- **URLs significativas:** `foro/viewforum.php?fid=42` equivale a navegar a la isla 42. El fid del foro ES el fid de la isla. No hay tablas puente, no hay IDs separados.
- **Permisos de MyBB:** Los permisos de lectura/escritura del foro controlan quién puede postear en la isla. Un jugador no puede postear en una isla a la que no ha llegado porque el staff le otorga acceso al foro tras completar el viaje de navegación.
- **Jerarquía padre-hijo:** Las islas pueden organizarse en categorías (por ejemplo, "East Blue" como categoría padre, con islas hijas). La función `game_nav_get_island_from_forum()` resuelve recursivamente hacia arriba en la jerarquía de foros para determinar la isla actual de un post.
- **Creación simple:** Crear un foro nuevo = crear una isla potencial. El staff solo necesita añadir el registro en `game_forum_islands` para activar los datos RPG.

### 1.2 Capas del Subsistema

```
┌──────────────────────────────────────────────────────────────────────┐
│                        CLIENTE (Navegador)                           │
│  ┌────────────────────────────┐  ┌───────────────────────────────┐   │
│  │ zona_staff_islas.php       │  │ zona_staff_islas.js           │   │
│  │ (panel de gestión staff)   │  │ (modal editor + AJAX save)    │   │
│  │                            │  │                               │   │
│  │ Muestra:                   │  │ Funciones:                    │   │
│  │  - Island cards con datos  │  │  populateModal(card)          │   │
│  │  - Modal editor completo   │  │  updateCard(fid, data)        │   │
│  │  - Botón repartir impuestos│  │  gamePostJson() → AJAX        │   │
│  └────────────────────────────┘  └───────────────┬───────────────┘   │
│                                                   │                   │
│                                                   ▼                   │
│  ┌──────────────────────────────────────────────────────────────────┐│
│  │             AJAX (game/ajax/save_forum_island.php)                ││
│  │  Recibe POST JSON con todos los campos de la isla                ││
│  │  Valida: staff_level >= 3, CSRF token, forum exists              ││
│  │  Crea tabla si no existe, hace UPSERT (INSERT/UPDATE)            ││
│  │  Retorna {ok: true, fid: N}                                      ││
│  └──────────────────────────┬───────────────────────────────────────┘│
└─────────────────────────────┼────────────────────────────────────────┘
                              │
┌─────────────────────────────┼────────────────────────────────────────┐
│  ┌──────────────────────────▼──────────────────────────────────────┐│
│  │              PHP — HELPERS Y CONFIG                               ││
│  │  navigation_helpers.php  → game_nav_get_island_from_forum()      ││
│  │  navigation_helpers.php  → game_nav_calculate_distance()         ││
│  │  navigation_helpers.php  → game_nav_calculate_danger()           ││
│  │  navigation_helpers.php  → game_nav_list_islands()               ││
│  │  navigation_helpers.php  → game_nav_compute_voyage()             ││
│  │  navigation_config.php   → game_nav_sea_zone_labels()            ││
│  │  navigation_process.php  → Procesa posts con navegación          ││
│  └──────────────────────────────────────────────────────────────────┘│
│                              │                                        │
│                              ▼                                        │
│  ┌──────────────────────────────────────────────────────────────────┐│
│  │  MySQL — game_forum_islands                                       ││
│  │  PK: fid (INT UNSIGNED) → mybb_forums.fid                        ││
│  │  18 columnas + updated_at                                         ││
│  │  Sin FK explícita (MyBB no usa InnoDB FK), pero FK lógica         ││
│  └──────────────────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────────────┘
```

### 1.3 Flujo de Datos General

```
Staff abre zona_staff_islas.php
    → PHP carga todos los foros tipo 'f' desde mybb_forums
    → JOIN (o left join manual) con game_forum_islands
    → Renderiza island cards con data-* attributes
    → JS populateModal() carga datos en el editor
    → Staff edita campos en el modal
    → JS saveBtn click: gamePostJson('save_forum_island.php', data)
        → PHP valida staff_level >= 3, CSRF, forum exists
        → PHP sanitiza cada campo (escape_string, int, enum)
        → PHP hace UPSERT en game_forum_islands
        → Retorna {ok: true, fid: N}
    → JS updateCard() actualiza data-* en la card
```

---

## 2. Modelo de Datos

### 2.1 `CREATE TABLE` Completo

La tabla `game_forum_islands` se crea en `install_schema_fragments.php` con la siguiente definición:

```sql
CREATE TABLE mybb_game_forum_islands (
    fid                INT UNSIGNED NOT NULL PRIMARY KEY,
    island_image       VARCHAR(500) NOT NULL DEFAULT '',
    leader_name        VARCHAR(200) NOT NULL DEFAULT '',
    description        TEXT NOT NULL,
    terrain            VARCHAR(200) NOT NULL DEFAULT '',
    climate            VARCHAR(300) NOT NULL DEFAULT '',
    climate_temp       VARCHAR(100) NOT NULL DEFAULT '',
    climate_wind       VARCHAR(100) NOT NULL DEFAULT '',
    climate_precip     VARCHAR(100) NOT NULL DEFAULT '',
    buildings          TEXT NOT NULL,
    defenses           TEXT NOT NULL,
    resources          VARCHAR(300) NOT NULL DEFAULT '',
    coord_x            INT NOT NULL DEFAULT 0,
    coord_y            INT NOT NULL DEFAULT 0,
    sea_zone           VARCHAR(50) NOT NULL DEFAULT 'east_blue',
    base_danger        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    requires_log_pose  TINYINT(1) NOT NULL DEFAULT 0,
    requires_compass   TINYINT(1) NOT NULL DEFAULT 0,
    controlling_type   VARCHAR(20) DEFAULT NULL,
    controlling_id     INT DEFAULT NULL,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2 Columnas Añadidas por Migración

La tabla original (creada por `migrate_forum_islands.php`) solo tenía las columnas básicas:

```sql
CREATE TABLE mybb_game_forum_islands (
    fid           INT UNSIGNED NOT NULL PRIMARY KEY,
    island_image  VARCHAR(500) NOT NULL DEFAULT '',
    leader_name   VARCHAR(200) NOT NULL DEFAULT '',
    buildings     TEXT NOT NULL,
    climate       VARCHAR(200) NOT NULL DEFAULT '',
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Las columnas de navegación (`coord_x`, `coord_y`, `sea_zone`, `base_danger`, `requires_log_pose`, `requires_compass`) se añadieron mediante migraciones posteriores:

```php
game_nav_migration_add_column('game_forum_islands', 'coord_x', 'INT NOT NULL DEFAULT 0');
game_nav_migration_add_column('game_forum_islands', 'coord_y', 'INT NOT NULL DEFAULT 0');
game_nav_migration_add_column('game_forum_islands', 'sea_zone', "VARCHAR(50) NOT NULL DEFAULT 'east_blue'");
game_nav_migration_add_column('game_forum_islands', 'base_danger', 'TINYINT UNSIGNED NOT NULL DEFAULT 1');
game_nav_migration_add_column('game_forum_islands', 'requires_log_pose', 'TINYINT(1) NOT NULL DEFAULT 0');
game_nav_migration_add_column('game_forum_islands', 'requires_compass', 'TINYINT(1) NOT NULL DEFAULT 0');
```

Las columnas de control territorial (`controlling_type`, `controlling_id`) se añadieron mediante `migrate_territories.php`:

```sql
ALTER TABLE mybb_game_forum_islands
    ADD COLUMN controlling_type VARCHAR(20) DEFAULT NULL AFTER requires_log_pose,
    ADD COLUMN controlling_id INT DEFAULT NULL AFTER controlling_type;
```

### 2.3 Relación con `mybb_forums`

No existe una clave foránea explícita (`FOREIGN KEY`) porque MyBB utiliza tablas InnoDB pero no define FK entre sus tablas internas. La relación es **lógica**:

- `game_forum_islands.fid` → `mybb_forums.fid`
- En `save_forum_island.php` se verifica explícitamente que el foro existe antes de insertar/actualizar:

```php
$fq = $db->query("SELECT fid, name FROM {$prefix}forums WHERE fid = {$fid} LIMIT 1");
$forum = $db->fetch_array($fq);
if (!$forum) {
    GameAjax::fail(404, 'El foro no existe');
}
```

### 2.4 Comportamiento UPSERT

El endpoint `save_forum_island.php` implementa un patrón UPSERT:

1. Consulta si existe un registro con ese `fid`
2. Si existe → `UPDATE`
3. Si no existe → `INSERT`

```php
$existing = $db->query("SELECT 1 FROM {$prefix}game_forum_islands WHERE fid = {$fid} LIMIT 1");
if ($db->num_rows($existing)) {
    $db->write_query("UPDATE {$prefix}game_forum_islands SET ... WHERE fid={$fid}");
} else {
    $db->write_query("INSERT INTO {$prefix}game_forum_islands (...) VALUES (...)");
}
```

El `fid` es la clave primaria, por lo que no puede haber duplicados.

### 2.5 Diferencia entre foro con y sin isla

| Estado | `mybb_forums` | `game_forum_islands` | Efecto en juego |
|--------|---------------|----------------------|-----------------|
| Foro normal (sin isla) | Existe `fid` | Sin registro | `game_nav_get_island_from_forum()` sube al padre recursivamente |
| Foro isla | Existe `fid` | Con registro | Se usa como ubicación navegable |
| Foro borrado | Eliminado | Registro huérfano (no se limpia automáticamente) | Staff debe limpiar manualmente |

---

## 3. Catálogo Completo de Campos

### 3.1 `fid` — ID del foro (Clave Primaria)

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `INT UNSIGNED NOT NULL PRIMARY KEY` |
| **PHP** | `(int)$input['fid']` |
| **MyBB** | `mybb_forums.fid` |
| **Default** | Sin default (obligatorio) |
| **Editable** | No (se define al crear el registro) |

**Uso en el sistema:**
- Es el vínculo entre el foro de MyBB y los datos RPG de la isla.
- Se usa como clave en `game_navigation_routes` (`island_from_fid`, `island_to_fid`).
- Se usa como clave en `game_navigation_voyages` para origen y destino.
- `game_nav_get_island_from_forum(int $fid)` consulta por este campo.

**Display:** Se muestra como identificador numérico en el panel staff.

---

### 3.2 `island_image` — Imagen Representativa

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `VARCHAR(500) NOT NULL DEFAULT ''` |
| **PHP** | `$db->escape_string($input['island_image'] ?? '')` |
| **Default** | `''` (cadena vacía) |
| **Editable** | Sí, campo de texto URL |

**Uso en el sistema:**
- URL absoluta a una imagen representativa de la isla.
- Puede ser una imagen externa o subida al servidor.

**Display:**
- En `zona_staff_islas.php`: se muestra como `<img>` dentro de la `.rpg-island-card-img-wrap`. Si no hay imagen, se muestra un placeholder con ícono `fa-map-marked-alt`.
- En la ficha de tripulación: se muestra como imagen de territorio controlado.
- En el modal editor: vista previa de la imagen y actualización dinámica vía JS.

**Consejo:** Usar imágenes de 400×250px aproximadamente para consistencia visual.

---

### 3.3 `leader_name` — Líder Actual de la Isla

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `VARCHAR(200) NOT NULL DEFAULT ''` |
| **PHP** | `$db->escape_string($input['leader_name'] ?? '')` |
| **Default** | `''` |
| **Editable** | Sí, campo de texto |

**Uso en el sistema:**
- Este campo es **narrativo** (no mecánico). Describe quién gobierna la isla en la historia actual.
- Se actualiza manualmente por el staff cuando cambia el liderazgo.
- No está vinculado a `game_personajes` ni a `game_tripulaciones` — es texto libre.

**Display:**
- En la island card: `<span class="rpg-island-card-leader"><i class="fas fa-crown"></i> {leader_name}</span>`
- Si está vacío, se muestra un em dash (`—`).

**Consejo:** Incluir el nombre del personaje gobernante y, opcionalmente, su título (ej: "Alvida — Capitana Pirata").

---

### 3.4 `description` — Historia y Descripción General

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `TEXT NOT NULL` |
| **PHP** | `$db->escape_string($input['description'] ?? '')` |
| **Default** | Cadena vacía (`TEXT NOT NULL` sin default explícito) |
| **Editable** | Sí, textarea |

**Uso en el sistema:**
- Contiene la historia, el lore y la descripción general de la isla.
- Es el campo más importante para la narrativa del mundo.
- No tiene límite de tamaño (TEXT = 65,535 bytes).

**Display:**
- En el modal editor: `<textarea rows="3">`.
- En la card actual: solo se almacena en `data-description` para el modal. No se muestra directamente en la card.

**Consejo:** Escribir 2-4 párrafos que cubran: historia, geografía, habitantes, cultura, situación política actual.

---

### 3.5 `terrain` — Tipo de Terreno

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `VARCHAR(200) NOT NULL DEFAULT ''` |
| **PHP** | `$db->escape_string($input['terrain'] ?? '')` |
| **Default** | `''` |
| **Editable** | Sí, campo de texto |

**Uso en el sistema:**
- Describe el terreno predominante: selva tropical, desierto ártico, archipiélago volcánico, ciudad metropolitana, etc.
- Es puramente narrativo, pero puede ser referenciado por eventos y oráculos.

**Display:** Campo de texto en el editor, almacenado como atributo `data-terrain`.

---

### 3.6 `climate`, `climate_temp`, `climate_wind`, `climate_precip` — Clima

| Propiedad | Valores |
|-----------|---------|
| **Tipo SQL** | `VARCHAR(300)`, `VARCHAR(100)`, `VARCHAR(100)`, `VARCHAR(100)`, todos `NOT NULL DEFAULT ''` |
| **PHP** | `$db->escape_string($input['climate'] ?? '')`, etc. |
| **Default** | `''` |
| **Editable** | Sí, campos de texto |

**Estructura de sub-campos:**

| Campo | Propósito | Ejemplo |
|-------|-----------|---------|
| `climate` | Clima general | "Tropical húmedo" |
| `climate_temp` | Temperatura | "28-35°C" |
| `climate_wind` | Vientos | "Brisas suaves del este" |
| `climate_precip` | Precipitación | "1200mm anuales" |

**Uso en el sistema:**
- Actualmente son **puramente narrativos**. No afectan mecánicas directamente.
- Están diseñados para futura integración con el sistema de oráculos de navegación (ej: si una isla tiene `climate = "Tormentas eléctricas"`, los oráculos de clima podrían consultar este campo para personalizar eventos).
- La separación en 4 sub-campos permite representación estructurada en lugar de un solo bloque de texto.

**Display:** Cuatro campos de texto separados en el modal editor, agrupados bajo "Clima".

---

### 3.7 `buildings` — Edificios y Puntos de Interés

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `TEXT NOT NULL` |
| **PHP** | `$db->escape_string($input['buildings'] ?? '')` |
| **Default** | Cadena vacía |
| **Editable** | Sí, textarea |

**Uso en el sistema:**
- Lista de ubicaciones, edificios, o distritos en la isla.
- Sirve como referencia para jugadores: "Aquí puedo ir al mercado", "El cuartel de la Marina está aquí".
- Texto libre: puede ser una lista con viñetas o párrafos descriptivos.

**Consejo:** Organizar por categorías: Comercio, Gobierno, Vivienda, Ocio, Lugares Emblemáticos.

---

### 3.8 `defenses` — Defensas Militares o Naturales

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `TEXT NOT NULL` |
| **PHP** | `$db->escape_string($input['defenses'] ?? '')` |
| **Default** | Cadena vacía |
| **Editable** | Sí, textarea |

**Uso en el sistema:**
- Describe las capacidades defensivas de la isla: murallas, fortificaciones, guarnición marina, defensas naturales (arrecifes, acantilados), sistemas de alarma.
- Es relevante para el control territorial: una isla con defensas fuertes es más difícil de conquistar.
- Actualmente es narrativo, pero puede integrarse con mecánicas de asedio/control.

**Display:** Textarea en el editor, almacenado como `data-defenses`.

---

### 3.9 `resources` — Recursos Naturales

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `VARCHAR(300) NOT NULL DEFAULT ''` |
| **PHP** | `$db->escape_string($input['resources'] ?? '')` |
| **Default** | `''` |
| **Editable** | Sí, campo de texto |

**Uso en el sistema:**
- Lista de recursos disponibles: madera, minerales, pesca, frutas, metales preciosos, etc.
- Es el campo clave para el valor estratégico de una isla.
- Las tripulaciones que controlan una isla obtienen acceso narrativo a estos recursos.
- La distribución de impuestos (`distribute_taxes` en `zona_staff_islas.php`) notifica a los controladores sobre los recursos disponibles.

**Display:** Campo de texto en el editor.

---

### 3.10 `sea_zone` — Zona del Mundo

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `VARCHAR(50) NOT NULL DEFAULT 'east_blue'` |
| **PHP** | `$db->escape_string(preg_replace('/[^a-z_]/', '', $input['sea_zone']))` |
| **Default** | `'east_blue'` |
| **Editable** | Sí, select con opciones predefinidas |

**Valores permitidos (enum lógico):**

| Valor | Etiqueta | Peligro típico |
|-------|----------|----------------|
| `east_blue` | East Blue | 1-2 |
| `west_blue` | West Blue | 1-2 |
| `north_blue` | North Blue | 1-2 |
| `south_blue` | South Blue | 1-2 |
| `grand_line` | Grand Line | 3-4 |
| `new_world` | New World | 4-5 |
| `calm_belt` | Calm Belt | 3-5 |
| `florian_triangle` | Triángulo de Florian | 4-5 |

**Uso en el sistema:**

Es uno de los campos más importantes para mecánicas:

1. **Determina la zona de navegación:** Cuando se calcula un viaje:
   ```php
   $seaZone = $danger >= 3 ? ($islandTo['sea_zone'] ?? 'grand_line') : ($islandFrom['sea_zone'] ?? 'east_blue');
   ```
   Si el peligro calculado es >= 3, se usa la `sea_zone` de la isla de destino; si es menor, se usa la de origen. Esto refleja que viajes peligrosos cruzan a mares hostiles.

2. **Afecta velocidad efectiva:** `game_nav_effective_speed()` aplica modificadores por zona específicos del barco:
   ```php
   $zoneKey = 'nav_bonus_' . preg_replace('/[^a-z_]/', '', $seaZone);
   $zoneMod = (float)($shipEffects[$zoneKey] ?? 0);
   ```

3. **Filtro de oráculos:** Los oráculos de navegación se seleccionan según la zona.

4. **Determina instrumentos requeridos:** `grand_line` y `new_world` generalmente requieren Log Pose; los Blues requieren brújula.

**Sanitización en PHP:**
```php
$seaZone = $db->escape_string(
    preg_replace('/[^a-z_]/', '', (string)($input['sea_zone'] ?? 'east_blue'))
) ?: 'east_blue';
```
Se elimina cualquier caracter que no sea letra minúscula o guion bajo, y se fuerza un default seguro.

---

### 3.11 `base_danger` — Peligro Base

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `TINYINT UNSIGNED NOT NULL DEFAULT 1` |
| **PHP** | `max(1, min(5, (int)($input['base_danger'] ?? 1)))` |
| **Default** | `1` |
| **Rango** | 1 (Tranquilo) – 5 (EXTREMO) |
| **Editable** | Sí, input number min=1 max=5 |

**Uso en el sistema:**

El `base_danger` es fundamental para el cálculo de peligro de rutas:

```php
function game_nav_calculate_danger(array $islandFrom, array $islandTo, array $waypointFids, ?int $dangerOverride): int
{
    if ($dangerOverride !== null) {
        return max(1, min(5, $dangerOverride));
    }

    $dangers = [(int)($islandFrom['base_danger'] ?? 1), (int)($islandTo['base_danger'] ?? 1)];

    if (!empty($waypointFids)) {
        global $db;
        $prefix = TABLE_PREFIX;
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

**Regla de interpolación:** El peligro final es 40% del máximo + 60% del promedio. Esto evita que una sola isla de peligro 5 dispare toda la ruta, pero sí la afecta significativamente.

**Etiquetas:**
```php
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

El peligro también determina:
- **Número de eventos** durante el viaje (`game_nav_calculate_events()`):
  - Danger 1: 0 eventos base
  - Danger 2: 1 evento base
  - Danger 3: 2 eventos base
  - Danger 4: 3 eventos base
  - Danger 5: 4 eventos base
  - +1 si duración >= 5 días
  - +1 si duración >= 10 días
  - +0–2 aleatorio

---

### 3.12 `requires_log_pose` — Requiere Log Pose

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `TINYINT(1) NOT NULL DEFAULT 0` |
| **PHP** | `!empty($input['requires_log_pose']) ? 1 : 0` |
| **Default** | `0` (false) |
| **Editable** | Sí, checkbox |

**Uso en el sistema:**
- Si es `1`, el personaje necesita un Log Pose (o Eternal Pose) en su inventario para navegar hacia esta isla.
- Se valida en el proceso de navegación:
  ```php
  $instrument = $input['nav_instrument'] ?? '';
  // Si la isla destino requiere Log Pose, el instrumento debe ser 'log_pose' o 'eternal_pose'
  ```
- Las islas en Grand Line y New World típicamente requieren Log Pose.

**Display:** Checkbox en el modal editor:
```html
<label><input type="checkbox" class="island-field-check" data-field="requires_log_pose" /> Requiere Log Pose</label>
```

---

### 3.13 `requires_compass` — Requiere Brújula

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `TINYINT(1) NOT NULL DEFAULT 0` |
| **PHP** | `!empty($input['requires_compass']) ? 1 : 0` |
| **Default** | `0` |
| **Editable** | Sí, checkbox |

**Uso en el sistema:**
- Si es `1`, el personaje necesita una brújula en su inventario para navegar hacia esta isla.
- Típicamente usado en islas de los Blues (East, West, North, South Blue) donde el Log Pose no funciona.
- Una isla no debería requerir ambos (`requires_log_pose` y `requires_compass`), pero el sistema lo permite.

**Display:** Checkbox en el modal editor:
```html
<label><input type="checkbox" class="island-field-check" data-field="requires_compass" /> Requiere brújula (Blues)</label>
```

---

### 3.14 `coord_x`, `coord_y` — Coordenadas en el Mapa

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `INT NOT NULL DEFAULT 0` |
| **PHP** | `(int)($input['coord_x'] ?? 0)` |
| **Rango** | 0–1000 (definido por `GAME_NAV_MAP_WIDTH` / `GAME_NAV_MAP_HEIGHT`) |
| **Editable** | Sí, input number min=0 max=1000 |

**Uso en el sistema:**

Las coordenadas son la base para el cálculo de distancia entre islas:

```php
function game_nav_calculate_distance(int $islandFromFid, int $islandToFid): array
{
    // Si existe ruta precalculada en game_navigation_routes, la usa
    // Si no, calcula distancia Euclidiana:
    $fromRow = $db->fetch_array($db->query("SELECT coord_x, coord_y FROM ... WHERE fid = {$from}"));
    $toRow = $db->fetch_array($db->query("SELECT coord_x, coord_y FROM ... WHERE fid = {$to}"));

    $dx = (int)$fromRow['coord_x'] - (int)$toRow['coord_x'];
    $dy = (int)$fromRow['coord_y'] - (int)$toRow['coord_y'];
    $dist = (int)round(sqrt($dx * $dx + $dy * $dy));

    return ['distance' => max(1, $dist), ...];
}
```

**Filosofía de coordenadas:**
- El mapa es un plano cartesiano de 1000×1000 unidades.
- No hay unidades físicas reales; es un sistema abstracto para calcular distancias relativas.
- Dos islas en el mismo Blue deberían tener coordenadas cercanas (distancia 3–15).
- Islas en diferentes mares deberían estar más distantes (distancia 15–40+).

**Display:** Inputs number en el modal editor, agrupados bajo "Navegación".

---

### 3.15 `controlling_type` — Tipo de Controlador

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `VARCHAR(20) DEFAULT NULL` |
| **PHP** | `$db->escape_string($input['controlling_type'] ?? '')` |
| **Valores** | `'pj'`, `'crew'`, `''` (NULL) |
| **Default** | `NULL` |
| **Editable** | Sí, select |

**Uso en el sistema:**

Define quién controla la isla mecánicamente:

- `'pj'`: Controlada por un personaje individual. `controlling_id` apunta a `game_personajes.id`.
- `'crew'`: Controlada por una tripulación. `controlling_id` apunta a `game_tripulaciones.id`.
- `''` o `NULL`: No controlada (tierra de nadie).

**Validación en PHP:**
```php
$controllingType = $db->escape_string($input['controlling_type'] ?? '');
$controllingId = (int)($input['controlling_id'] ?? 0);
if (!in_array($controllingType, ['pj', 'crew'])) {
    $controllingType = '';
    $controllingId = 0;
}
```

---

### 3.16 `controlling_id` — ID del Controlador

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `INT DEFAULT NULL` |
| **PHP** | `(int)($input['controlling_id'] ?? 0)` |
| **Editable** | Sí, input number |

**Uso en el sistema:**
- Si `controlling_type = 'pj'`: contiene `game_personajes.id`.
- Si `controlling_type = 'crew'`: contiene `game_tripulaciones.id`.
- Si `controlling_type` está vacío: debe ser `0` o `NULL`.

**No hay FK explícita.** La integridad referencial se mantiene a nivel de aplicación. Si un personaje o tripulación se elimina, el staff debe limpiar manualmente el control.

---

### 3.17 `updated_at` — Timestamp de Última Modificación

| Propiedad | Valor |
|-----------|-------|
| **Tipo SQL** | `TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |
| **PHP** | No se envía (auto-gestionado por MySQL) |
| **Default** | CURRENT_TIMESTAMP |
| **Editable** | No |

Se actualiza automáticamente cuando cualquier campo de la fila cambia, gracias a `ON UPDATE CURRENT_TIMESTAMP`.

---

## 4. Ciclo de Vida de una Isla

### 4.1 Creación de una Isla

1. **Staff crea un foro nuevo en MyBB** (ACP → Foros → Crear Foro). Se asigna un `fid`.
2. **Staff abre `zona_staff_islas.php`** y localiza el nuevo foro en la lista.
3. **Staff hace clic en "Editar Isla"** y rellena los campos.
4. **Staff guarda** → `save_forum_island.php` crea el registro en `game_forum_islands`.
5. **Opcional:** Staff configura rutas de navegación desde/hacia la nueva isla en `zona_staff_rutas.php`.

### 4.2 Edición de una Isla

- Cualquier campo puede editarse en cualquier momento desde el modal editor.
- Los cambios se reflejan inmediatamente en navegación, fichas de tripulación, etc.
- No hay versionado ni historial de cambios.

### 4.3 Eliminación de una Isla

**No hay interfaz de eliminación desde el panel staff.** Para "eliminar" una isla:
1. Borrar el foro desde el ACP de MyBB.
2. Opcional: eliminar manualmente el registro de `game_forum_islands` vía SQL.
3. Las rutas de navegación huérfanas deben limpiarse de `game_navigation_routes`.

**Advertencia:** Si se borra un foro pero no el registro de `game_forum_islands`, quedan rutas huérfanas que pueden causar errores al listar islas.

### 4.4 Foros sin isla (no-configurados)

Cuando `game_nav_get_island_from_forum()` encuentra un foro sin registro en `game_forum_islands`, **sube al padre** recursivamente:

```php
function game_nav_get_island_from_forum(int $fid): ?array
{
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

**Esto permite:**
- Tener foros dentro de islas (ej: "Shells Town → Puerto" y "Shells Town → Cuartel") que heredan la isla del padre.
- Crear sub-foros dentro de una isla sin necesidad de configurar cada uno como isla separada.

---

## 5. Sistema de Control Territorial

### 5.1 Filosofía

El control territorial permite que personajes y tripulaciones **posean** islas mecánicamente. Esto no es simplemente decorativo: tiene implicaciones en la economía del juego, la narrativa y el estatus.

**¿Por qué control territorial?**
- Recompensa la actividad y el rolplay político/militar.
- Crea conflictos naturales por recursos y territorio.
- Da a las tripulaciones un objetivo a largo plazo (conquistar y defender islas).
- Permite la distribución de recursos económicos (Berries, bienes).

### 5.2 Modelo de Datos

El control se almacena directamente en `game_forum_islands`:
- `controlling_type`: `'pj'`, `'crew'`, o `NULL`
- `controlling_id`: ID del personaje o tripulación

**No hay tabla separada** para control territorial. Esto simplifica las consultas:

```sql
-- Obtener todas las islas controladas por una tripulación
SELECT i.*, f.name AS forum_name
FROM game_forum_islands i
JOIN forums f ON i.fid = f.fid
WHERE i.controlling_type = 'crew' AND i.controlling_id = {$crew_id};
```

### 5.3 Beneficios del Control

Actualmente implementados:

1. **Display en perfil de tripulación:** Las islas controladas aparecen en `tripulacion.php`:
   ```php
   $tq = $db->query("SELECT i.*, f.name AS forum_name
       FROM {$prefix}game_forum_islands i
       JOIN {$prefix}forums f ON i.fid = f.fid
       WHERE i.controlling_type = 'crew' AND i.controlling_id = {$crew_id}
       ORDER BY f.name ASC");
   ```

2. **Display en biblioteca de tripulaciones:** Las islas controladas aparecen en `biblioteca_tripulaciones.php`:
   ```php
   $iq = $db->query("SELECT f.name FROM {$prefix}game_forum_islands i
       JOIN {$prefix}forums f ON i.fid = f.fid
       WHERE i.controlling_type = 'crew' AND i.controlling_id = {$row['id']}");
   ```

3. **Distribución de impuestos:** Staff puede disparar la distribución de beneficios:

### 5.4 Sistema de Distribución de Impuestos

En `zona_staff_islas.php`, el botón **"Repartir Impuestos de Territorios"** ejecuta:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'distribute_taxes') {
    $iq = $db->query("SELECT name, controlling_type, controlling_id FROM {$prefix}game_forum_islands WHERE controlling_type IN ('pj', 'crew')");
    while ($island = $db->fetch_array($iq)) {
        if ($island['controlling_type'] === 'pj') {
            $user_q = $db->query("SELECT user_id FROM {$prefix}game_personajes WHERE id = {$island['controlling_id']}");
            if ($u = $db->fetch_array($user_q)) {
                $db->query("INSERT INTO {$prefix}game_notifications (user_id, type, message, is_read)
                    VALUES ({$u['user_id']}, 'territory_tax',
                    'Has recibido los beneficios e impuestos (Berries y Bienes) por el control de: " . $db->escape_string($island['name']) . ". Administra los recursos en tu ficha.', 0)");
            }
        } elseif ($island['controlling_type'] === 'crew') {
            $leader_q = $db->query("SELECT p.user_id FROM {$prefix}game_tripulaciones t
                JOIN {$prefix}game_personajes p ON t.leader_pj_id = p.id
                WHERE t.id = {$island['controlling_id']}");
            if ($u = $db->fetch_array($leader_q)) {
                $db->query("INSERT INTO {$prefix}game_notifications (user_id, type, message, is_read)
                    VALUES ({$u['user_id']}, 'territory_tax',
                    'Tu tripulación ha recibido los beneficios e impuestos (Berries y Bienes) por el control de: " . $db->escape_string($island['name']) . ". Administra los recursos en vuestro inventario de tripulación.', 0)");
            }
        }
    }
}
```

**Flujo:**
1. Staff hace clic en "Repartir Impuestos".
2. Se consultan todas las islas con `controlling_type IN ('pj', 'crew')`.
3. Para cada isla controlada por PJ: se busca el `user_id` del personaje y se le notifica.
4. Para cada isla controlada por tripulación: se busca el líder de la tripulación y se le notifica.
5. Las notificaciones son de tipo `'territory_tax'`.
6. Staff es redirigido con `?msg=taxes_distributed`.

**Limitación actual:** La distribución no transfiere Berries/Bienes automáticamente. Solo notifica al jugador/líder para que administre manualmente. Los montos exactos se deciden por narrativa.

### 5.5 Cambio de Control

El staff actualiza manualmente el control desde el modal editor:
1. Seleccionar tipo (`pj`, `crew`, o vacío).
2. Ingresar el ID correspondiente.
3. Guardar.

**No hay sistema de conquista automatizado.** La transferencia de control debe ser mediada por el staff basándose en la narrativa del juego.

---

## 6. Gestión de Staff — Interfaz y AJAX

### 6.1 `zona_staff_islas.php` — Vista General

**Ruta:** `back/forum/game/public/zona_staff_islas.php`
**Permisos:** `staff_level >= 3`

**Flujo de la página:**

1. **Verificar autenticación y staff level:**
   ```php
   $uid = (int)$mybb->user['uid'];
   // ... carga active_pj_id, staff_level ...
   if ($staff_level < 3) {
       header('Location: ../index.php');
       exit;
   }
   ```

2. **Carga de foros e islas:**
   ```php
   // Todos los foros tipo 'f'
   $fq = $db->query("SELECT f.fid, f.name FROM {$prefix}forums f WHERE f.type = 'f' ORDER BY f.name");
   while ($f = $db->fetch_array($fq)) {
       $forums[$fid] = ['name' => $f['name'], 'fid' => $fid];
   }

   // Datos de islas (si la tabla existe)
   if ($db->table_exists('game_forum_islands')) {
       $iq = $db->query("SELECT * FROM {$prefix}game_forum_islands");
       while ($ir = $db->fetch_array($iq)) {
           $forums[$fid] = array_merge($forums[$fid], $ir);
       }
   }
   ```

3. **Renderizado de island cards:**
   ```html
   <div class="rpg-island-card"
        data-fid="<?= $fid ?>"
        data-name="<?= $fname ?>"
        data-island_image="<?= $img ?>"
        data-leader_name="<?= $leader ?>"
        data-description="<?= $desc ?>"
        ...>
     <div class="rpg-island-card-img-wrap">
       <?php if ($img): ?>
         <img src="<?= $img ?>" alt="<?= $fname ?>" class="rpg-island-card-img" />
       <?php else: ?>
         <div class="rpg-island-card-img-placeholder"><i class="fas fa-map-marked-alt"></i></div>
       <?php endif; ?>
     </div>
     <h3 class="rpg-island-card-name"><?= $fname ?></h3>
     <span class="rpg-island-card-leader"><i class="fas fa-crown"></i> <?= $leader ?: '—' ?></span>
     <button class="rpg-btn--primary rpg-island-edit-btn" type="button"><i class="fas fa-edit"></i> Editar Isla</button>
   </div>
   ```

### 6.2 Modal Editor

El modal contiene todos los campos organizados en secciones:

```
┌─────────────────────────────────────────┐
│  ✕  Editar Isla: [Nombre]              │
├─────────────────────────────────────────┤
│  Imagen de la Isla (URL)               │
│  [_________________________________]    │
│  [Preview image]                        │
│                                         │
│  Líder Actual (Narrativo)              │
│  [_________________________________]    │
│                                         │
│  ── Control Territorial (Mecánico) ──  │
│  Tipo: [select: Ninguno/PJ/Tripulación]│
│  ID:   [number input]                   │
│                                         │
│  ── Datos Generales ──                 │
│  Descripción: [textarea]                │
│  Terreno: [input]                       │
│  Clima - General: [input]               │
│  Clima - Temperatura: [input]           │
│  Clima - Viento: [input]                │
│  Clima - Precipitación: [input]         │
│  Zonas / Edificios: [textarea]          │
│  Defensas: [textarea]                    │
│  Recursos: [input]                      │
│                                         │
│  ── Navegación ──                      │
│  Coordenada X (0-1000): [number]        │
│  Coordenada Y (0-1000): [number]        │
│  Zona del mar: [select: 8 zonas]        │
│  Peligro base (1-5): [number]           │
│  ☐ Requiere Log Pose                    │
│  ☐ Requiere brújula (Blues)            │
│                                         │
│  [✓ Guardado]  [💾 Guardar]           │
└─────────────────────────────────────────┘
```

### 6.3 `zona_staff_islas.js` — Lógica del Editor

**Ruta:** `back/forum/jscripts/game/zona_staff_islas.js`

**Funciones principales:**

1. **`populateModal(card)`:**
   - Lee los atributos `data-*` de la card y llena el formulario.
   - Maneja checkboxes (`requires_log_pose`, `requires_compass`) usando `data-field-check`.
   - Muestra preview de la imagen si existe.

2. **`updateCard(fid, data)`:**
   - Actualiza los atributos `data-*` de la card después de guardar.
   - Actualiza la imagen y el líder en la card visualmente.
   - Actualiza la preview en el modal.

3. **Evento Save:**
   ```javascript
   saveBtn.addEventListener('click', function() {
       var data = { fid: currentFid };
       fields.forEach(function(f) { data[f.dataset.field] = f.value; });
       document.querySelectorAll('.island-field-check').forEach(function(cb) {
           data[cb.dataset.field] = cb.checked ? 1 : 0;
       });
       gamePostJson('/game/ajax/save_forum_island.php', data).then(function(r) {
           if (r.ok) {
               savedMsg.classList.remove('is-hidden');
               updateCard(currentFid, data);
           }
       });
   });
   ```

### 6.4 `save_forum_island.php` — Endpoint AJAX

**Ruta:** `back/forum/game/ajax/save_forum_island.php`
**Método:** POST
**Content-Type:** application/json
**Autenticación:** Staff level >= 3
**CSRF:** Requerido

**Input completo (JSON):**

```json
{
  "fid": 42,
  "island_image": "https://ejemplo.com/isla.png",
  "leader_name": "Monkey D. Luffy",
  "description": "Una isla tropical en East Blue...",
  "terrain": "Selva tropical",
  "climate": "Tropical húmedo",
  "climate_temp": "28-35°C",
  "climate_wind": "Brisas suaves del este",
  "climate_precip": "1200mm anuales",
  "buildings": "Puerto, Mercado, Cuartel de Marina",
  "defenses": "Muralla costera, guarnición de 50 marines",
  "resources": "Madera, frutas tropicales, pesca",
  "coord_x": 150,
  "coord_y": 300,
  "sea_zone": "east_blue",
  "base_danger": 2,
  "requires_log_pose": 0,
  "requires_compass": 1,
  "controlling_type": "crew",
  "controlling_id": 7
}
```

**Output (JSON):**

```json
{
  "ok": true,
  "fid": 42
}
```

**Errores posibles:**

| Código | Mensaje | Causa |
|--------|---------|-------|
| 403 | No tienes permiso para editar islas | Staff level < 3 |
| 400 | ID de foro inválido | `fid` <= 0 o no enviado |
| 404 | El foro no existe | `fid` no encontrado en `mybb_forums` |

**Seguridad:**
- `staff_level >= 3` verificado contra `game_personajes` activo.
- CSRF token validado.
- SQL injection mitigado vía `$db->escape_string()` y typecasting.
- `sea_zone` sanitizado con regex `[^a-z_]`.
- `base_danger` clamp: `max(1, min(5, ...))`.
- `controlling_type` validado contra whitelist `['pj', 'crew']`.

### 6.5 Campos Navegación + Control (Backwards Compatibility)

Como las columnas de navegación y control se añadieron en migraciones posteriores, el código detecta si existen:

```php
// Columnas de navegación
$navCols = '';
if ($db->field_exists('coord_x', 'game_forum_islands')) {
    $navCols = ", coord_x={$coordX}, coord_y={$coordY}, sea_zone='{$seaZone}', base_danger={$baseDanger}, requires_log_pose={$requiresLogPose}, requires_compass={$requiresCompass}";
}

// Columnas de control
$controlCols = '';
if ($db->field_exists('controlling_type', 'game_forum_islands')) {
    $controlCols = ", controlling_type=" . ($controllingType ? "'{$controllingType}'" : "NULL") . ", controlling_id=" . ($controllingId ?: "NULL");
}
```

Esto permite que el código funcione incluso si las migraciones no se han ejecutado completamente.

---

## 7. Integración con Navegación

### 7.1 Islas como Destinos de Viaje

Cada viaje de navegación tiene un origen y un destino, ambos referenciados por `fid` de `game_forum_islands`:

```sql
CREATE TABLE mybb_game_navigation_voyages (
    ...
    island_from_fid INT UNSIGNED NOT NULL,
    island_to_fid   INT UNSIGNED NOT NULL,
    ...
);
```

### 7.2 `game_nav_get_island_from_forum()` — Resolución de Isla Actual

**Propósito:** Dado un `fid` de foro, determinar si es una isla o subir al padre.

```php
function game_nav_get_island_from_forum(int $fid): ?array
```

**Retorna:** Array con todos los campos de `game_forum_islands` + `forum_name` de `mybb_forums`.

**Uso típico:**
```php
// Al procesar un post, determinar la isla actual del personaje
$islandFrom = game_nav_get_island_from_forum((int)$postRow['fid']);
```

### 7.3 `game_nav_list_islands()` — Listado de Islas

```php
function game_nav_list_islands(int $excludeFid = 0): array
```

**Retorna:** Array de islas con campos:
- `fid`, `name`, `sea_zone`, `base_danger`
- `requires_log_pose`, `requires_compass`
- `coord_x`, `coord_y`, `image_url`

**Uso:** Poblar selects de destino en formularios de navegación y en el AJAX `navigation_islands_list.php`.

### 7.4 `game_nav_compute_voyage()` — Cálculo Completo de Viaje

```php
function game_nav_compute_voyage(
    int $fromFid,
    int $toFid,
    array $shipEffects,
    int $navigatorRank,
    string $instrument
): array
```

**Flujo interno:**
1. Carga `game_forum_islands` para origen y destino.
2. Calcula distancia (`game_nav_calculate_distance`).
3. Calcula peligro (`game_nav_calculate_danger`).
4. Determina zona del mar basada en peligro.
5. Calcula velocidad efectiva.
6. Calcula duración.
7. Calcula rango de eventos.

**Retorna:**
```json
{
  "ok": true,
  "distance": 28,
  "danger_level": 3,
  "danger_label": "Peligroso",
  "effective_speed": 7.5,
  "duration_days": 4,
  "events_min": 2,
  "events_max": 4,
  "sea_zone": "grand_line",
  "route": { "distance": 28, "waypoints": [], "is_precalculated": false, "danger_override": null }
}
```

### 7.5 Tabla `game_navigation_routes` — Rutas entre Islas

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

**FK lógica:** `island_from_fid` y `island_to_fid` referencian `game_forum_islands.fid` (no hay FK explícita).

**Ejemplo de datos:**
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

**Staff management:** `zona_staff_rutas.php` permite crear/editar/eliminar rutas.

### 7.6 Cómo los Campos de Isla Afectan la Navegación

| Campo de isla | Efecto en navegación |
|---------------|----------------------|
| `coord_x`, `coord_y` | Distancia Euclidiana = `round(sqrt(dx² + dy²))` |
| `base_danger` | Peligro del viaje = 40% max + 60% avg de todas las islas en la ruta |
| `sea_zone` | Zona del viaje (origen si danger < 3, destino si danger >= 3). Afecta velocidad del barco, oráculos |
| `requires_log_pose` | El jugador debe tener Log Pose o Eternal Pose en su inventario |
| `requires_compass` | El jugador debe tener brújula en los Blues |

### 7.7 Ejemplo de Cálculo

```
Origen: Shells Town (fid=42, coord=(100,200), sea_zone=east_blue, danger=1)
Destino: Orange Town (fid=57, coord=(120,180), sea_zone=east_blue, danger=2)

Distancia: sqrt((100-120)² + (200-180)²) = sqrt(400+400) = sqrt(800) ≈ 28
Peligro: max(1,2)=2, avg(1,2)=1.5, interpolado = 2*0.4 + 1.5*0.6 = 0.8+0.9 = 1.7 ≈ 2
Zona: danger=2 < 3 → usa sea_zone de origen = east_blue
```

---

## 8. Integración con Tripulaciones

### 8.1 Territorios Controlados en Perfil de Tripulación

En `tripulacion.php`, se cargan las islas controladas por la tripulación:

```php
$tq = $db->query("
    SELECT i.*, f.name AS forum_name
    FROM {$prefix}game_forum_islands i
    JOIN {$prefix}forums f ON i.fid = f.fid
    WHERE i.controlling_type = 'crew' AND i.controlling_id = {$crew_id}
    ORDER BY f.name ASC
");
```

Estas islas se muestran en el perfil público de la tripulación como una sección de "Territorios Controlados", con su imagen, nombre en el foro, descripción y recursos.

### 8.2 Territorios en Biblioteca de Tripulaciones

En `biblioteca_tripulaciones.php`, cada tripulación en el listado muestra las islas que controla:

```php
$iq = $db->query("SELECT f.name FROM {$prefix}game_forum_islands i
    JOIN {$prefix}forums f ON i.fid = f.fid
    WHERE i.controlling_type = 'crew' AND i.controlling_id = {$row['id']}");
$row['islands'] = array_column($iq, 'name');
```

### 8.3 Beneficios para Tripulaciones

Las tripulaciones que controlan islas obtienen:
1. **Recursos narrativos:** Acceso a los recursos definidos en el campo `resources`.
2. **Impuestos:** Notificaciones periódicas vía el sistema de distribución de impuestos.
3. **Prestigio:** Display público en la biblioteca de tripulaciones.
4. **Base de operaciones:** La isla puede servir como puerto base para la tripulación.

---

## 9. Integración con Personajes

### 9.1 Personajes como Controladores

Un personaje individual puede controlar una isla (`controlling_type = 'pj'`, `controlling_id = game_personajes.id`).

**Uso:**
- Gobernadores de islas.
- Señores piratas que han conquistado territorio.
- Marines que comandan una base.

### 9.2 Staff Level para Gestión

Solo personajes con `staff_level >= 3` pueden editar islas desde el panel de staff:
```php
$staffLevel = game_get_active_staff_level($uid);
if ($staffLevel < 3) {
    GameAjax::fail(403, 'No tienes permiso para editar islas');
}
```

---

## 10. Filosofía de Diseño

### 10.1 ¿Por qué 1:1 entre Foros e Islas?

**Decisión de arquitectura (D001/D002):**

1. **URL canónica:** `viewforum.php?fid=X` siempre muestra la isla X. No hay ambigüedad.
2. **Permisos nativos:** MyBB ya tiene un sistema de permisos por foro. No necesitamos reinventar quién puede postear dónde.
3. **Jerarquía natural:** Las categorías de foros pueden reflejar mares/islas. Un foro "East Blue" con hijos "Shells Town", "Orange Town" etc. La herencia de isla vía `pid` funciona orgánicamente.
4. **Simplicidad:** No crear una tabla separada con IDs de isla que luego hay que mapear a foros. El `fid` es todo lo que necesitas.

### 10.2 ¿Por qué Tanta Metadata por Isla?

Cada isla tiene 18 columnas de datos. Esto está deliberadamente sobredimensionado:

- **Narrativa:** Los campos `description`, `terrain`, `climate*`, `buildings`, `defenses`, `resources`, `leader_name` permiten describir la isla completamente sin recurrir a hilos de lore separados.
- **Mecánica:** `coord_*`, `sea_zone`, `base_danger`, `requires_*` alimentan directamente los cálculos de navegación.
- **Económica:** `resources`, `controlling_*` soportan el sistema económico y de control territorial.

**Alternativa rechazada:** Tener una tabla de islas minimalista (solo fid y nombre) y dejar toda la metadata en hilos de foro. Se rechazó porque:
- Los datos no serían consultables por SQL.
- No se podrían usar en cálculos mecánicos.
- Sería imposible integrar con navegación y control territorial.

### 10.3 ¿Por qué Existe el Control Territorial?

El control territorial no es solo decorativo. Responde a necesidades de diseño:

1. **Economía:** Las islas tienen recursos. Controlar una isla = controlar sus recursos.
2. **Conflicto:** Las islas son puntos de conflicto natural entre tripulaciones, Marines y piratas.
3. **Progresión:** Conquistar y defender islas da a las tripulaciones objetivos a largo plazo más allá del combate individual.
4. **Mundo vivo:** El mapa cambia. Las islas cambian de manos. El mundo se siente dinámico.

### 10.4 ¿Por qué Datos Narrativos y Mecánicos en la Misma Tabla?

Mezclar campos narrativos (`description`, `leader_name`) con mecánicos (`coord_x`, `base_danger`) en la misma tabla es intencional:

- **Una consulta para todo:** `SELECT * FROM game_forum_islands WHERE fid = X` te da toda la información de la isla en una sola fila.
- **Edición unificada:** El staff edita todo desde el mismo modal.
- **Coherencia:** Siempre que tienes una isla, tienes todos sus datos. No hay joins para obtener la descripción o las coordenadas.

---

## 11. Consejos para Staff

### 11.1 Configuración Inicial de una Isla Nueva

1. **Crear el foro** en ACP de MyBB con tipo "Foro" (no "Categoría").
   - Elegir un nombre que sea el nombre de la isla en el mundo.
   - Asignar un padre adecuado (el mar/zona al que pertenece).
   - Configurar permisos básicos.
2. **Abrir `zona_staff_islas.php`** y encontrar el foro en la lista.
3. **Rellenar campos** en este orden de prioridad:
   - `coord_x`, `coord_y` (fundamental para navegación)
   - `sea_zone` (define en qué mar está)
   - `base_danger` (1-2 para Blues, 3+ para Grand Line/New World)
   - `description` (el lore de la isla)
   - `resources`, `buildings`, `defenses` (qué hay en la isla)
   - `leader_name`, `island_image` (identidad visual)
   - `requires_log_pose` / `requires_compass` (requisitos de entrada)
4. **Configurar rutas** desde/hacia la isla en `zona_staff_rutas.php`.
5. **Verificar** que `game_nav_list_islands()` incluya la nueva isla.

### 11.2 Escribir Buenas Descripciones de Isla

Una buena descripción de isla en `description` debería incluir:

```
# [Nombre de la Isla]

## Historia
[2-3 párrafos sobre el origen, eventos importantes, situación actual]

## Geografía
[Tipo de terreno, tamaño aproximado, características geográficas notables]

## Habitantes
[Población aproximada, especies/razas predominantes, cultura]

## Gobierno
[Quién gobierna, tipo de gobierno, estabilidad política]

## Economía
[Actividades económicas principales, recursos exportados/importados]

## Lugares de Interés
[Lista de ubicaciones importantes con breve descripción]
```

**Ejemplo realista:**
```
# Isla Shells (Shells Town)

## Historia
Una pequeña isla en East Blue conocida por su base naval. Originalmente un puesto de comercio, fue fortificada hace 20 años cuando la Marina estableció una base para combatir la piratería en la región. Recientemente, la base fue derrotada por el pirata "Mano de Hacha", dejando la isla en un estado de anarquía controlada.

## Geografía
Isla de tamaño pequeño a mediano, mayormente plana con una colina central donde se asienta la base marina. Costa este con puerto natural, costa oeste acantilada e inaccesible.

## Habitantes
Aproximadamente 3000 civiles. Mayormente humanos. El ambiente es tenso desde la caída de la base.

## Gobierno
Ninguno formal. La ley la impone quien tenga más fuerza en el momento. El líder nominal es cualquiera que ocupe la mansión del gobernador.

## Economía
Pesca, pequeña agricultura, y un mercado negro de suministros navales saqueados.

## Lugares de Interés
- Puerto de Shells: Puerto principal, controlado por piratas locales
- Base Marina 77: Abandonada, pero con suministros y armamento
- Barrio Comercial: Tiendas, tabernas y el mercado negro
- Mansión del Gobernador: Actualmente ocupada por piratas
```

### 11.3 Balancear Peligro y Recursos

**Regla general:**

| `base_danger` | Recursos típicos | Ejemplo |
|---------------|------------------|---------|
| 1 (Tranquilo) | Básicos (pesca, madera) | Isla pequeña de East Blue |
| 2 (Moderado) | Moderados (minerales, agricultura) | Isla comercial estable |
| 3 (Peligroso) | Buenos (metales, frutas del diablo menores) | Isla de Grand Line |
| 4 (Muy peligroso) | Valiosos (metales raros, tecnología) | Isla de New World |
| 5 (EXTREMO) | Legendarios (tesoros, armas ancestrales) | Isla prohibida |

**Balance:** Una isla con `base_danger = 1` pero `resources = "Gold, Diamonds, Ancient Weapons"` rompe la lógica del juego. El peligro debe reflejar qué tan codiciados (y defendidos) están los recursos.

### 11.4 Gestionar el Control Territorial

- **No asignar control sin narrativa:** Un personaje o tripulación debería tener que rolear la conquista/gobierno antes de recibir control mecánico.
- **Actualizar `leader_name`** cuando cambie el control, para reflejar quién gobierna ahora.
- **Usar la distribución de impuestos** periódicamente (ej: una vez al mes) para mantener el beneficio del control.
- **No acumular demasiadas islas** bajo un mismo controlador. Si una tripulación controla 10 islas, considerar si es realista o si debería haber facciones compitiendo.

### 11.5 Buenas Prácticas de Coordenadas

| Zona | Rango X típico | Rango Y típico |
|------|----------------|----------------|
| East Blue | 0-200 | 700-1000 |
| West Blue | 800-1000 | 700-1000 |
| North Blue | 0-200 | 0-300 |
| South Blue | 800-1000 | 0-300 |
| Grand Line | 300-700 | 300-700 |
| Calm Belt | 100-300 | 300-500 (franja) |
| New World | 400-600 | 400-600 (centro) |
| Florian Triangle | 450-550 | 450-550 (zona específica) |

Estos rangos son orientativos. Lo importante es la **distancia relativa**: islas cercanas deben tener coordenadas cercanas.

---

## 12. Consejos para Jugadores

### 12.1 Usar Datos de Isla para Roleplay

Los datos de `game_forum_islands` no son solo para el staff. Como jugador, puedes usar:

- **`terrain`** para describir el entorno en tus posts: "Camino entre la densa selva tropical, esquivando lianas y animales exóticos..."
- **`climate_temp`** para añadir realismo: "El sol del mediodía en Shells Town es abrasador, debo buscar sombra..."
- **`buildings`** para saber a dónde ir: "Me dirijo al mercado en el Barrio Comercial..."
- **`defenses`** para planear estrategias: "La muralla costera está descuidada, podría ser un punto débil..."
- **`resources`** para comerciar o saquear: "Necesito madera para reparar el barco, y esta isla tiene..."
- **`leader_name`** para interacciones políticas: "Quiero audiencia con el líder de la isla..."
- **`sea_zone`** para ambientación de viaje: "Estamos en East Blue, el mar más tranquilo de todos..."

### 12.2 Valor Estratégico de las Islas

**Para piratas:**
- **Islas con `defenses` débiles** → Fáciles de atacar, pero quizá también pobres en recursos.
- **Islas con `resources` valiosos** → Objetivos prioritarios, pero suelen tener `base_danger` alto.
- **Islas sin controlador** → Oportunidad de establecer base sin conflicto inmediato.
- **Islas en Grand Line/New World** → Más peligrosas pero con mayor prestigio y mejores recompensas.

**Para marines:**
- **Islas con `leader_name` pirata** → Objetivos de misión.
- **Islas con `defenses` de la Marina** → Posibles bases de operaciones.
- **Islas en `sea_zone = calm_belt`** → Ideales para rutas secretas.

**Para comerciantes:**
- **Islas con `resources` complementarios** → Rutas comerciales rentables.
- **Islas con `buildings` de mercado/puerto** → Buenas para establecer negocios.
- **Islas con `base_danger` bajo** → Rutas seguras para mercancías.

### 12.3 Navegar entre Islas

Antes de iniciar un viaje, verifica:
1. **`requires_log_pose` / `requires_compass`:** ¿Tienes el instrumento necesario en tu inventario?
2. **`base_danger`:** ¿Tu barco y tu tripulación pueden manejar el peligro esperado?
3. **`sea_zone`:** ¿Tu barco tiene buena velocidad en esa zona? (ej: algunos barcos tienen bonus específicos para Grand Line o Calm Belt).
4. **Distancia:** Calcula la duración del viaje para planificar tu narrativa.

---

## 13. Troubleshooting

### 13.1 Error: "Isla no encontrada" en Navegación

**Causa:** `game_nav_get_island_from_forum()` retorna `null`.

**Diagnóstico:**
1. Verificar que el foro donde posteaste existe en `mybb_forums`.
2. Verificar que el foro o algún padre tiene registro en `game_forum_islands`.
3. Verificar que la tabla `game_forum_islands` existe.

```sql
-- Verificar islas
SELECT * FROM mybb_game_forum_islands;

-- Verificar si el foro está registrado
SELECT * FROM mybb_game_forum_islands WHERE fid = {tu_fid};

-- Verificar el foro y su padre
SELECT fid, pid, name FROM mybb_forums WHERE fid = {tu_fid};
```

### 13.2 Error: "No tienes permiso para editar islas"

**Causa:** El personaje activo no tiene `staff_level >= 3`.

**Diagnóstico:**
```sql
SELECT id, name, staff_level FROM mybb_game_personajes
WHERE user_id = {tu_uid} AND id = {tu_active_pj_id};
```

### 13.3 Isla no aparece en el panel staff

**Causa:** El foro no es de tipo `'f'` (forum).

**Solución:** Verificar en MyBB ACP que el foro sea tipo "Foro" y no "Categoría" o "Link".

```sql
SELECT fid, name, type FROM mybb_forums WHERE fid = {fid};
-- type debe ser 'f'
```

### 13.4 Columnas faltantes en `game_forum_islands`

Si ves errores como "Unknown column 'coord_x' in..." o "Unknown column 'controlling_type' in...":

**Solución:** Ejecutar las migraciones:
```bash
php back/forum/game/sql/migrate_territories.php
```

O verificar la estructura actual:
```sql
DESCRIBE mybb_game_forum_islands;
```

### 13.5 Datos no se guardan

**Causa posible:** El token CSRF ha expirado o la tabla `game_forum_islands` no existe.

**Diagnóstico:**
1. Verificar la consola del navegador para errores de red.
2. Verificar los logs de PHP/MyBB.
3. Verificar que `save_forum_island.php` retorna un JSON válido.

### 13.6 Rutas huérfanas después de borrar una isla

Si se elimina un foro isla pero las rutas en `game_navigation_routes` no se limpian:

```sql
-- Encontrar rutas huérfanas
SELECT r.* FROM mybb_game_navigation_routes r
LEFT JOIN mybb_game_forum_islands i ON r.island_from_fid = i.fid
WHERE i.fid IS NULL;

-- Eliminar rutas huérfanas
DELETE r FROM mybb_game_navigation_routes r
LEFT JOIN mybb_game_forum_islands i ON r.island_from_fid = i.fid
WHERE i.fid IS NULL;
```

---

## Apéndice A: Mapa de Archivos

| Archivo | Propósito |
|---------|-----------|
| `back/forum/game/sql/install_schema_fragments.php` | CREATE TABLE original de `game_forum_islands` (línea 421) |
| `back/forum/game/sql/migrate_forum_islands.php` | Migración de la tabla (versión legacy con columnas básicas) |
| `back/forum/game/sql/migrate_territories.php` | Añade `controlling_type` y `controlling_id` |
| `back/forum/game/ajax/save_forum_island.php` | Endpoint AJAX para guardar datos de isla |
| `back/forum/game/public/zona_staff_islas.php` | Panel de gestión de islas para staff |
| `back/forum/game/public/zona_staff_rutas.php` | Panel de gestión de rutas entre islas |
| `back/forum/game/inc/navigation_helpers.php` | Helpers: `game_nav_get_island_from_forum()`, `game_nav_list_islands()`, `game_nav_compute_voyage()` |
| `back/forum/game/inc/navigation_config.php` | Constantes y `game_nav_sea_zone_labels()` |
| `back/forum/game/inc/navigation_process.php` | Procesamiento de posts con navegación |
| `back/forum/game/public/tripulacion.php` | Perfil de tripulación (muestra territorios, línea 118) |
| `back/forum/game/public/biblioteca_tripulaciones.php` | Biblioteca de tripulaciones (muestra islas controladas, línea 37) |
| `back/forum/jscripts/game/zona_staff_islas.js` | JS del modal editor de islas |

## Apéndice B: Constantes de Configuración

Definidas en `navigation_config.php`:

```php
define('GAME_NAV_SPEED_FACTOR', 10);        // divisor para convertir distancia en días
define('GAME_NAV_MAP_WIDTH', 1000);          // ancho máximo del mapa
define('GAME_NAV_MAP_HEIGHT', 1000);         // alto máximo del mapa
define('GAME_NAV_EVENTS_MIN', 0);            // mínimo de eventos por viaje
define('GAME_NAV_EVENTS_MAX', 8);            // máximo de eventos por viaje
define('GAME_NAV_NO_INSTRUMENT_SPEED_PENALTY', 1.0); // penalización sin instrumento
```

## Apéndice C: Referencia Rápida de Funciones

| Función | Archivo | Propósito | Depende de `game_forum_islands` |
|---------|---------|-----------|----------------------------------|
| `game_nav_get_island_from_forum(int $fid)` | `navigation_helpers.php:10` | Resuelve foro → isla (sube al padre) | Sí |
| `game_nav_calculate_distance(int $from, int $to)` | `navigation_helpers.php:32` | Distancia entre islas | `coord_x`, `coord_y` |
| `game_nav_calculate_danger(array $from, array $to, ...)` | `navigation_helpers.php:73` | Peligro del viaje | `base_danger` |
| `game_nav_compute_voyage(...)` | `navigation_helpers.php:359` | Cálculo completo de viaje | Todos los campos de navegación |
| `game_nav_list_islands(int $exclude)` | `navigation_helpers.php:209` | Listar islas disponibles | Todas las columnas |
| `game_nav_sea_zone_labels()` | `navigation_config.php:24` | Etiquetas de zonas | — (no consulta DB) |
