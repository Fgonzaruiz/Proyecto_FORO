# 11. Akuma no Mi (Frutas del Diablo)

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 11
> **Propósito:** Documentar exhaustivamente el subsistema de Akuma no Mi: clasificación, modelo de datos, sistema de solicitudes (aleatoria y demanda), asignación de cartas, desventajas, despertar, herramientas de staff, y filosofía de diseño. Esta guía NO lista frutas específicas — se centra en la arquitectura del sistema.

---

## ÍNDICE

1. [Clasificación de Akuma no Mi](#1-clasificación-de-akuma-no-mi)
2. [Database Schema — `game_akuma_no_mi`](#2-database-schema)
3. [Tabla `game_admin_requests`](#3-game_admin_requests)
4. [Sistema de Solicitud — Aleatoria](#4-sistema-de-solicitud-aleatoria)
5. [Sistema de Solicitud — Bajo Demanda](#5-sistema-de-solicitud-bajo-demanda)
6. [AdminRequestService — Capa de Servicio](#6-adminrequestservice)
7. [Asignación de Carta Akuma](#7-asignación-de-carta-akuma)
8. [Validación de Requisitos al Asignar](#8-validación-de-requisitos-al-asignar)
9. [Estructura de effects_json para Akuma](#9-estructura-de-effectsjson-para-akuma)
10. [Desventajas Universales](#10-desventajas-universales)
11. [Sistema de Despertar (Awakening)](#11-sistema-de-despertar)
12. [Herramientas de Staff](#12-herramientas-de-staff)
13. [Flujo Completo: Solicitud → Asignación](#13-flujo-completo)
14. [Filosofía de Diseño](#14-filosofía-de-diseño)
15. [Consejos para Jugadores](#15-consejos-para-jugadores)
16. [Consejos para Staff](#16-consejos-para-staff)
17. [Referencia Rápida de Archivos](#17-referencia-rápida-de-archivos)

---

## 1. Clasificación de Akuma no Mi

El sistema reconoce tres clases fundamentales de Akuma no Mi, definidas en el ENUM del campo `class` de la tabla `game_akuma_no_mi` y validadas en `akuma_helpers.php:78`:

```php
$akumaType = strtolower((string)($effects['akuma_type'] ?? 'paramecia'));
if (!in_array($akumaType, ['paramecia', 'logia', 'zoan'], true)) {
    $akumaType = 'paramecia';
}
```

### 1.1 Paramecia

**Definición:** Frutas que otorgan poderes corporales o de entorno variados, sin encajar en Zoan o Logia. Es la categoría más amplia y diversa.

**Mecánicas:**
- El cuerpo del usuario se modifica o adquiere propiedades sobrehumanas (elasticidad, multiplicación de partes, generación de sustancias).
- El usuario puede generar o manipular sustancias, campos de fuerza, o alterar su entorno inmediato.
- No otorga intangibilidad natural (excepto casos muy específicos determinados por el staff).
- Es la clase por defecto si no se especifica otra.

**Implicaciones mecánicas:**
- `tier` típico: 1–4 (la mayoría son tier 1–3, las paramecias extraordinarias pueden ser tier 4–5).
- `subtipo` es siempre `ninguno`.
- La carta akuma define pasivas, capacidades base, transformaciones y debilidades en `effects_json`.
- Las técnicas derivadas se crean como cartas tipo `tecnica` con tag de clase.

**Filosofía de diseño:** Las Paramecias son el cajón versátil del sistema. Su poder puede ir desde lo mundano (tier 1: cuerpo que gira) hasta lo divino (tier 5: manipulación de la realidad). El staff debe evaluar cada Paramecia por sus méritos individuales, ya que no hay un patrón único de comportamiento mecánico.

### 1.2 Zoan

**Definición:** Frutas que permiten al usuario transformarse en un animal y en una forma híbrida (humano-animal). El subtipo define la naturaleza del animal base.

**Mecánicas:**
- El usuario obtiene tres formas: humana (sin poderes), híbrida (stats mixtos), y animal completa (stats especializados).
- La transformación otorga mejoras físicas: FUE, RES, AGI incrementados en forma híbrida; FUE y RES máximos en forma animal.
- La resistencia pasiva del usuario aumenta proporcionalmente al tier de la fruta.
- Puede tener `subtipo` específico.

**Subtipos Zoan:**

| Subtipo | `subtipo` en DB | Descripción mecánica |
|---------|-----------------|----------------------|
| Estándar | `ninguno` | Animal existente en el mundo real. Stats acordes al animal. Tier 1–3. |
| Antiguo | `antiguo` | Animal prehistórico/extinto. Stats superiores al estándar. Tier 2–4. |
| Mítico | `mitico` | Criatura legendaria/mitológica. Puede incluir habilidades elementales o especiales además de la transformación. Tier 3–5. |

**Normalización en PHP (`akuma_helpers.php:81-87`):**
```php
$subtipo = strtolower((string)($effects['subtipo'] ?? 'ninguno'));
if (!in_array($subtipo, ['ninguno', 'antiguo', 'mitico'], true)) {
    $subtipo = 'ninguno';
}
if ($akumaType !== 'zoan') {
    $subtipo = 'ninguno';
}
```

**Implicaciones mecánicas:**
- Solo las Zoan pueden tener `subtipo` distinto de `ninguno`. Si una carta es Paramecia o Logia y tiene `subtipo` definido, el sistema lo fuerza a `ninguno` automáticamente.
- Las Zoan `mitico` pueden incluir efectos elementales o especiales en su `effects_json.transformaciones`.
- La forma híbrida es la más versátil: combina movilidad humana con fuerza animal.

**Filosofía de diseño:** Las Zoan están pensadas para jugadores que quieren un estilo de combate cuerpo a cuerpo con énfasis en transformación y resistencia. Los subtipos antiguo y mítico permiten escalar el poder sin romper la temática animal. Un Zoan mítico de tier 5 (ej: modelo dragón) es equiparable a una Logia de alto tier en poder destructivo, pero limitado por requerir transformación activa.

### 1.3 Logia

**Definición:** Frutas que permiten al usuario crear, controlar y transformarse en un elemento natural. La característica definitoria es la **intangibilidad natural**: los ataques físicos convencionales atraviesan al usuario sin dañarlo.

**Mecánicas:**
- **Intangibilidad pasiva:** Mientras el usuario está consciente y en estado "elemental", los ataques físicos sin Haki de Armamento (o sin la debilidad específica de la Logia) no producen daño.
- **Creación elemental:** El usuario puede generar cantidades ilimitadas de su elemento.
- **Control elemental:** El usuario manipula el elemento existente en el entorno.
- **Debilidad elemental:** Cada Logia tiene un elemento que la contrarresta (ej: agua moja a una Logia de arena).

**Implicaciones mecánicas:**
- `tier` mínimo sugerido: 3. Una Logia tier 1 o 2 solo sería concebible con nerfeos severos aprobados por staff.
- La intangibilidad se representa como una pasiva en `effects_json.pasivas`:
  ```json
  {
      "nombre": "Intangibilidad Logia",
      "descripcion": "Ataques físicos sin Haki de Armamento o [debilidad_elemento] no producen daño.",
      "tier_necesario_para_usar": 0
  }
  ```
- Las Logias requieren que el enemigo tenga Haki de Armamento (carta tipo `haki` con `haki_level >= basico`) para ser dañado por ataques físicos.

**Filosofía de diseño:** Las Logia son las frutas más poderosas en términos defensivos. La intangibilidad cambia fundamentalmente cómo se combate contra ellas. Por diseño:
- Son raras en el sistema (menos frutas Logia disponibles que Paramecia o Zoan).
- Tienen un requisito narrativo alto para su obtención.
- El staff debe asegurarse de que existan personajes con Haki de Armamento en el foro para mantener el balance.

### 1.4 Mapeo Tier ↔ Rango de Carta

El tier de una Akuma no Mi determina el rango de la carta asignada, según `akuma_helpers.php`:

```php
function game_akuma_tier_rank_map(): array
{
    return [1 => 'D', 2 => 'C', 3 => 'B', 4 => 'A', 5 => 'S'];
}

function game_akuma_rank_for_tier(int $tier): string
{
    $map = game_akuma_tier_rank_map();
    return $map[max(1, min(5, $tier))] ?? 'D';
}
```

| Tier | Rango de carta | Perfil de poder |
|:----:|:--------------:|-----------------|
| 1 | D | Fruta menor, poder limitado, efectos situacionales |
| 2 | C | Fruta común, poder moderado, utilidad en combate |
| 3 | B | Fruta notable, poder significativo, ventaja táctica clara |
| 4 | A | Fruta poderosa, gran impacto en combate, difícil de contrarrestar |
| 5 | S | Fruta legendaria, poder masivo, capacidad de cambiar el rumbo de una batalla |

---

## 2. Database Schema

### 2.1 `game_akuma_no_mi` — Catálogo de Frutas

```sql
CREATE TABLE mybb_game_akuma_no_mi (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    class           VARCHAR(50) NOT NULL,
    class_name      VARCHAR(100) NOT NULL,
    status          VARCHAR(50) NOT NULL,
    status_name     VARCHAR(100) NOT NULL,
    `desc`          TEXT NOT NULL,
    details         TEXT NOT NULL,
    tipo_fruta      VARCHAR(100) NOT NULL,
    usuario_actual  VARCHAR(255) NOT NULL,
    habilidad_clave VARCHAR(255) NOT NULL,
    precio          VARCHAR(100) NOT NULL,
    banner          VARCHAR(255) NOT NULL,
    is_occupied     TINYINT(1) NOT NULL DEFAULT 0,
    power_range     VARCHAR(32) NOT NULL DEFAULT 'Sin asignar',
    is_reserved     TINYINT(1) NOT NULL DEFAULT 0,
    tier            TINYINT UNSIGNED NOT NULL DEFAULT 1
        COMMENT '1-5, tier de poder de la fruta según escala canónica',
    subtipo         ENUM('ninguno','antiguo','mitico') NOT NULL DEFAULT 'ninguno'
        COMMENT 'Subtipo Zoan; Paramecia/Logia usan ninguno'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2 Descripción de Columnas

#### `id` — Identificador único
- INT AUTO_INCREMENT. Clave primaria.
- Referenciado como `akuma_fruit_id` en `game_admin_requests`.
- Usado en las operaciones de reserva y ocupación (`AdminRequestService`).

#### `name` — Nombre de la fruta
- VARCHAR(255). Nombre canónico en español/japonés. Ej: `"Gomu Gomu no Mi"`.
- Se muestra en el catálogo, en notificaciones, y en el título de las peticiones.

#### `class` — Clase mecánica
- VARCHAR(50). Almacena el valor en inglés: `"paramecia"`, `"zoan"`, `"logia"`.
- Se usa en las queries de filtrado del catálogo (`akuma_catalog.php:46-54`):
  ```php
  $category = 'paramecia';
  if (strpos($class, 'logia') === 0) {
      $category = 'logia';
  } elseif (strpos($class, 'zoan') === 0) {
      $category = 'zoan';
  }
  ```

#### `class_name` — Nombre legible de la clase
- VARCHAR(100). Versión capitalizada/localizada: `"Paramecia"`, `"Zoan"`, `"Logia"`.
- Se muestra en la UI.

#### `status` — Estado textual
- VARCHAR(50). Originalmente contenía valores como `"disponible"`, `"activa"`.
- Con la migración a `is_occupied`/`is_reserved`, se mantiene por compatibilidad: al ocupar se establece `"activa"`, al liberar se actualiza.

#### `status_name` — Nombre legible del estado
- VARCHAR(100). Ej: `"Disponible (Libre)"`, `"Activa (Ocupada)"`.

#### `desc` — Descripción corta
- TEXT. Descripción breve visible en el catálogo y en los resultados de tiradas.
- Se incluye en la respuesta JSON de `akuma_roll.php` y `akuma_catalog.php`.

#### `details` — Descripción detallada
- TEXT. Información ampliada, historia, notas sobre el poder.

#### `tipo_fruta` — Clasificación adicional
- VARCHAR(100). Información extra sobre el tipo. Ej: `"Logia elemental"`, `"Zoan clásica"`, `"Paramecia"`.

#### `usuario_actual` — Último poseedor conocido
- VARCHAR(255). Nombre del personaje que la posee actualmente o la poseyó. Informativo, no vinculante mecánicamente.

#### `habilidad_clave` — Habilidad representativa
- VARCHAR(255). Una frase que resume el poder principal. Ej: `"Elasticidad extrema"`, `"Control del fuego"`.

#### `precio` — Precio de referencia
- VARCHAR(100). Valor en Berries de referencia (si aplica para compraventa entre PJs).

#### `banner` — URL de imagen
- VARCHAR(255). Ruta a la imagen representativa de la fruta. Ej: `"images/game/akuma_banner.png"`.

#### `is_occupied` — Ocupada (TINYINT 0/1)
- **0:** Fruta disponible para ser solicitada.
- **1:** Fruta ya asignada a un personaje. No puede ser solicitada por nadie más.
- Se establece a 1 cuando el staff APRUEBA una petición que incluye esta fruta (`AdminRequestService::occupyAkumaFruit()`).
- Es la columna principal de control de disponibilidad.

#### `power_range` — Rango de poder sugerido
- VARCHAR(32). Valor textual como `"Rango B"`, `"Rango S"`. Informativo, el tier determina el rango real de la carta.

#### `is_reserved` — Reservada (TINYINT 0/1)
- **0:** No hay una petición activa sobre esta fruta.
- **1:** Una petición PENDIENTE ha reservado esta fruta. Mientras esté en este estado, otras peticiones no pueden seleccionarla.
- Se establece a 1 **antes** de crear la petición (`AdminRequestService::reserveAkumaFruit()`).
- Se libera a 0 cuando la petición es denegada o cuando se ocupa la fruta.
- Previene condiciones de carrera: dos jugadores solicitando la misma fruta simultáneamente.

#### `tier` — Tier de poder
- TINYINT UNSIGNED (1–5). El nivel de poder de la fruta.
- Determina:
  - Los requisitos mínimos de ESP y nivel del personaje para recibir la carta (`StatScale::minEspRankForAkumaTier()`, `StatScale::minNivelForAkumaTier()`).
  - El rango de la carta asignada (`D` para tier 1 → `S` para tier 5).
  - La cantidad de usos base para despertar (30/50/75/100 según tier).

#### `subtipo` — Subtipo Zoan
- ENUM: `'ninguno'`, `'antiguo'`, `'mitico'`.
- Solo significativo para clase `zoan`. Para `paramecia` y `logia` siempre es `'ninguno'`.

### 2.3 Cómo Funciona la Disponibilidad

El sistema tiene tres estados de disponibilidad:

```
                    ┌──────────────────┐
                    │  is_occupied=0   │
                    │  is_reserved=0   │
                    │   → DISPONIBLE   │
                    └────────┬─────────┘
                             │
              reserveAkumaFruit()
                             │
                             ▼
                    ┌──────────────────┐
                    │  is_occupied=0   │
                    │  is_reserved=1   │
                    │  → RESERVADA     │
                    └────────┬─────────┘
                            / \
                           /   \
             deny request /     \ approve request
                         /       \
                        /         \
                       ▼           ▼
            ┌──────────────────┐ ┌──────────────────┐
            │  is_occupied=0   │ │  is_occupied=1   │
            │  is_reserved=0   │ │  is_reserved=0   │
            │  → DISPONIBLE    │ │  → OCUPADA       │
            └──────────────────┘ └──────────────────┘
```

**Transiciones:**
1. **Disponible → Reservada:** Cuando se inicia una petición (aleatoria o demanda), se llama a `reserveAkumaFruit()` que verifica que `is_occupied = 0` y `is_reserved = 0`, y establece `is_reserved = 1`.
2. **Reservada → Ocupada:** Cuando el staff aprueba la petición, `occupyAkumaFruit()` establece `is_occupied = 1` y `is_reserved = 0`. También cambia `status = 'activa'`.
3. **Reservada → Disponible:** Cuando el staff deniega la petición, `releaseAkumaReservation()` establece `is_reserved = 0` (solo si `is_occupied = 0`).

**Condiciones de carrera prevenidas:**
- `reserveAkumaFruit()` lanza excepción si la fruta ya está ocupada o reservada.
- `occupyAkumaFruit()` solo se ejecuta si la petición está en estado `pendiente` y pasa a `aprobada`.
- `releaseAkumaReservation()` verifica que no se libere una fruta ocupada accidentalmente.

---

## 3. `game_admin_requests`

### 3.1 Esquema SQL

```sql
CREATE TABLE mybb_game_admin_requests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    character_id    INT NOT NULL,
    source          VARCHAR(32) NOT NULL,
    request_kind    VARCHAR(64) NOT NULL,
    title           VARCHAR(255) NOT NULL,
    description     TEXT NOT NULL,
    link            VARCHAR(500) DEFAULT NULL,
    payload_json    TEXT DEFAULT NULL,
    akuma_fruit_id  INT DEFAULT NULL,
    status          ENUM('pendiente','aprobada','denegada') NOT NULL DEFAULT 'pendiente',
    staff_nota      TEXT DEFAULT NULL,
    staff_user_id   INT DEFAULT NULL,
    staff_char_id   INT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_character (character_id),
    INDEX idx_akuma (akuma_fruit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Columnas Específicas para Akuma

#### `source` — Origen de la petición
- VARCHAR(32). Para akuma, los valores son:
  - `'akuma_random'` — Tirada aleatoria.
  - `'akuma_demand'` — Solicitud bajo demanda.
  - Otros valores: `'mision'`, `'form'` (para otros tipos de peticiones administrativas).

#### `request_kind` — Tipo de solicitud
- VARCHAR(64). Para akuma: `'fruta_diablo'`.
- La combinación `source + request_kind` permite filtrar peticiones de akuma específicamente.

#### `akuma_fruit_id` — Enlace a la fruta
- INT, FK lógica a `game_akuma_no_mi.id`. Puede ser NULL para peticiones no-akuma.
- Es el campo crítico que vincula la petición administrativa con la fruta del diablo.
- Se usa en `AdminRequestService::resolve()` para ocupar o liberar la fruta según la acción.

#### `payload_json` — Datos adicionales
- TEXT JSON. Para peticiones akuma aleatorias contiene:
  ```json
  {
      "mode": "random",
      "fruit_id": 1,
      "fruit_name": "Gomu Gomu no Mi",
      "class": "paramecia",
      "class_name": "Paramecia"
  }
  ```
- Para peticiones bajo demanda contiene el motivo y la justificación del jugador.

#### `status` — Estado de la petición
- ENUM: `'pendiente'` → `'aprobada'` | `'denegada'`.
- Solo las peticiones en estado `pendiente` pueden ser resueltas.
- Cambiar el estado activa la ocupación/liberación de la fruta.

### 3.3 Índices Relevantes

```sql
INDEX idx_akuma (akuma_fruit_id)
```
Este índice es crítico para consultar rápidamente si una fruta tiene peticiones pendientes asociadas.

---

## 4. Sistema de Solicitud — Aleatoria

### 4.1 Vista: `peticion_akuma_aleatoria.php`

**Archivo:** `back/forum/game/public/peticion_akuma_aleatoria.php`

Renderiza la interfaz de tirada aleatoria con:
- Catálogo visual de frutas (filtrable por clase y búsqueda).
- Panel lateral con estadísticas (total, libres, reservadas, ocupadas).
- Botón de tirada con estado de disponibilidad.
- Modal de detalle para cada fruta del catálogo.
- Zona de resultado con animación de ruleta.

**Layout:**
```
┌──────────────────────────────────────────────────────────┐
│ [Volver]  Akuma Aleatoria                                │
│ Consulta el catálogo...                                  │
├────────────────────────────┬─────────────────────────────┤
│ ┌────────────────────────┐│ ┌───────────────────────────┐│
│ │ [Buscar fruta...]      ││ │ Estado General            ││
│ │ [Todas] [Logia] [Zoan] ││ │ Total: 12  Libres: 8     ││
│ │ [Paramecia]            ││ │ Reservadas: 1  Ocup: 3   ││
│ │                        ││ ├───────────────────────────┤│
│ │ ┌───┐ ┌───┐ ┌───┐     ││ │ Sorteo de Fruta           ││
│ │ │Gomu│ │Yuki│ │Baku│   ││ │ 8 disponibles             ││
│ │ │Par │ │Log │ │Par │   ││ │ [¡Tirar aleatorio!]       ││
│ │ └───┘ └───┘ └───┘     ││ │                           ││
│ │                         ││ │ Resultado:                ││
│ └────────────────────────┘│ │ "Has obtenido..."          ││
│                           │ └───────────────────────────┘│
└────────────────────────────┴─────────────────────────────┘
```

### 4.2 AJAX: `akuma_catalog.php`

**Endpoint:** `GET` (con login requerido)

Carga el catálogo completo con estado de disponibilidad.

**Lógica:**
```php
// Detecta columnas disponibles (normalización por versión DB)
$hasOccupied = $db->field_exists('is_occupied', 'game_akuma_no_mi');
$hasReserved = $db->field_exists('is_reserved', 'game_akuma_no_mi');

// Construye columnas dinámicamente
$cols = 'id, name, class, class_name, `desc`, details, tipo_fruta, status, status_name';
if ($hasOccupied) $cols .= ', is_occupied';
if ($hasReserved) $cols .= ', is_reserved';
if ($hasRange)    $cols .= ', power_range';
if ($hasTier)     $cols .= ', tier';
if ($hasSubtipo)  $cols .= ', subtipo';

// Query completa
$q = $db->query("SELECT {$cols} FROM {$prefix}game_akuma_no_mi ORDER BY class_name ASC, name ASC");
```

**Respuesta JSON:**
```json
{
    "fruits": [
        {
            "id": 1,
            "name": "Gomu Gomu no Mi",
            "class": "paramecia",
            "class_name": "Paramecia",
            "category": "paramecia",
            "desc": "...",
            "is_occupied": false,
            "is_reserved": false,
            "power_range": "Rango B",
            "tier": 3,
            "subtipo": "ninguno"
        }
    ],
    "available_count": 8,
    "stats": {
        "total": 12,
        "libre": 8,
        "reservada": 1,
        "ocupada": 3
    },
    "roll": {
        "can_roll": true,
        "reason": "",
        "request_id": null,
        "status": null
    },
    "categories": [
        {"key": "logia", "label": "Logia"},
        {"key": "zoan", "label": "Zoan"},
        {"key": "paramecia", "label": "Paramecia"}
    ]
}
```

**El objeto `roll` determina si el jugador puede tirar:**
- Si `can_roll = true`, se habilita el botón de tirada.
- Si `can_roll = false`, se muestra el motivo y se bloquea la UI.

### 4.3 AJAX: `akuma_roll.php`

**Endpoint:** `POST` (con login + CSRF)

Ejecuta la tirada aleatoria.

**Flujo completo:**

```
1. requireLogin()                          → Verifica sesión
2. requirePost() + requireCsrf()           → Verifica método y token
3. requireActiveCharacter($uid)            → Obtiene character_id activo
4. characterAkumaRandomRollState($cid)     → Verifica si puede tirar
   ↓
   ¿can_roll? → No → 409 "Ya no puedes realizar otra tirada"
   ↓
   Sí
   ↓
5. SELECT id, name, class... FROM game_akuma_no_mi
   WHERE is_occupied = 0 AND is_reserved = 0
   → Pool de frutas disponibles
   ↓
   ¿pool vacío? → 409 "No hay Akuma disponibles"
   ↓
   Con frutas
   ↓
6. $pick = $pool[random_int(0, count($pool)-1)]
   → Selección criptográficamente segura
   ↓
7. reserveAkumaFruit($fruitId)
   → Marca is_reserved = 1
   ↓
   ¿Excepción? → 409 "Esa Akuma no Mi ya está ocupada/reservada"
   ↓
   OK
   ↓
8. AdminRequestService::create(
       uid, cid, 'akuma_random', 'fruta_diablo',
       "Akuma aleatoria: Gomu Gomu no Mi",
       "El personaje X ha obtenido...",
       null,
       {mode:'random', fruit_id:1, ...},
       fruitId
   )
   → Crea petición en game_admin_requests
   ↓
9. notifyStaffPending(...)
   → Notifica a todo staff_level >= 2
   ↓
10. Devolver JSON con request_id y datos de la fruta
```

**Selección criptográfica segura:**
```php
$pick = $pool[random_int(0, count($pool) - 1)];
```
Usa `random_int()` en lugar de `rand()` o `array_rand()` para garantizar distribución uniforme criptográficamente segura.

**Payload de petición:**
```php
$payload = [
    'mode' => 'random',
    'fruit_id' => $fruitId,
    'fruit_name' => $fruitName,
    'class' => $pick['class'] ?? '',
    'class_name' => $pick['class_name'] ?? '',
];
```

**Respuesta exitosa:**
```json
{
    "success": true,
    "request_id": 42,
    "fruit": {
        "id": 1,
        "name": "Gomu Gomu no Mi",
        "class": "paramecia",
        "class_name": "Paramecia",
        "desc": "...",
        "power_range": "Rango B"
    }
}
```

### 4.4 Límite de Tiradas

Cada personaje solo puede realizar **una** tirada aleatoria en total, controlado por:

```php
public static function characterAkumaRandomRollState(int $characterId): array
{
    $q = $db->query("
        SELECT id, status, title
        FROM {$prefix}game_admin_requests
        WHERE character_id = {$cid}
          AND source = 'akuma_random'
          AND status IN ('pendiente', 'aprobada')
        ORDER BY id DESC
        LIMIT 1
    ");
    if (!$row) {
        return ['can_roll' => true, 'reason' => ''];
    }
    // Ya existe una petición aleatoria pendiente o aprobada
    return ['can_roll' => false, 'reason' => 'Ya realizaste una tirada aleatoria...'];
}
```

**Filosofía:** La tirada aleatoria es un evento único por personaje. Una vez que tiras, obtienes lo que salga (si se aprueba). No hay reselección. Esto:
- Previene abuso (tirar hasta obtener una fruta deseada).
- Mantiene la emoción: el jugador se compromete con el resultado.
- Fomenta que los jugadores que quieren una fruta específica usen el método de solicitud bajo demanda.

### 4.5 JS: `peticion_akuma_aleatoria.js`

El JavaScript asociado (referenciado en `peticion_akuma_aleatoria.php:118`) maneja:
- Carga del catálogo vía `akuma_catalog.php`.
- Filtrado por clase y búsqueda textual.
- Botón de tirada con verificación de disponibilidad.
- Animación de ruleta mientras se procesa la tirada.
- Visualización del resultado.
- Desactivación del botón tras tirada exitosa.

---

## 5. Sistema de Solicitud — Bajo Demanda

### 5.1 Vista: `peticion_akuma_demanda.php`

**Archivo:** `back/forum/game/public/peticion_akuma_demanda.php`

Renderiza un formulario donde el jugador selecciona una fruta específica del catálogo y justifica su solicitud.

**Campos del formulario:**
1. **Fruta solicitada** (`akuma_fruit_id`): Select desplegable con las frutas disponibles (cargado vía JS desde `akuma_catalog.php`).
2. **Motivo** (`motivo`): Texto corto (max 200 chars). Ej: "Encaja con la trama de mi bando pirata".
3. **Justificación narrativa** (`justificacion`): Textarea donde el jugador argumenta IC por qué su personaje merece esta fruta.
4. **Enlace de apoyo** (`link`): URL opcional a un hilo/post que respalde la solicitud.

### 5.2 Flujo de Solicitud bajo Demanda

El envío del formulario se maneja vía JavaScript (`peticion_akuma_demanda.js`) que realiza una petición AJAX a un endpoint no mostrado en los archivos leídos, pero que lógicamente sigue este flujo:

```
1. Validar que el jugador tiene personaje activo
2. Validar que la fruta seleccionada existe y está disponible
3. reserveAkumaFruit($fruitId)          → Marca is_reserved = 1
4. AdminRequestService::create(
       uid, cid, 'akuma_demand', 'fruta_diablo',
       "Solicitud: Gomu Gomu no Mi",
       "Justificación: ...",
       link,
       {mode:'demand', fruit_id:1, motivo:'...'},
       fruitId
   )
5. notifyStaffPending(...)              → Notifica al staff
6. Devolver JSON con request_id
```

### 5.3 Diferencias Clave: Aleatoria vs Demanda

| Aspecto | Aleatoria | Bajo Demanda |
|---------|-----------|--------------|
| Selección | Sistema elige al azar | Jugador elige |
| Límite | 1 vez por personaje | Sin límite explícito (evaluado por staff) |
| Probabilidad | 100% de obtener alguna fruta disponible | Depende de aprobación del staff |
| Pool | Solo frutas disponibles | Cualquier fruta del catálogo (si está disponible) |
| Justificación | No requiere (es suerte) | Requiere motivo + justificación narrativa |
| Tiempo de resolución | Staff revisa y asigna | Staff revisa, evalúa mérito, y decide |
| Payload | `{mode:'random', fruit_id, fruit_name, class}` | `{mode:'demand', fruit_id, fruit_name, motivo}` |
| source en DB | `akuma_random` | `akuma_demand` |

---

## 6. AdminRequestService

**Archivo:** `back/forum/game/src/Application/Services/AdminRequestService.php`
**Namespace:** `Game\Application\Services`

Es la capa de servicio que orquesta todas las operaciones de peticiones de akuma.

### 6.1 Métodos

#### `characterAkumaRandomRollState(int $characterId): array`

Verifica si un personaje puede realizar una tirada aleatoria.

**Lógica:**
- Si no existe la tabla `game_admin_requests`, retorna `can_roll = true` (sistema sin migrar).
- Busca en `game_admin_requests` donde `character_id = X`, `source = 'akuma_random'`, y `status IN ('pendiente', 'aprobada')`.
- Si encuentra un registro, retorna `can_roll = false` con el motivo.

**Retorno:**
```php
[
    'can_roll' => bool,
    'reason' => string,       // Motivo si no puede
    'request_id' => ?int,     // ID de la petición existente
    'status' => ?string,      // 'pendiente' | 'aprobada' | null
]
```

#### `requireActiveCharacter(int $userId): int`

Obtiene el `active_pj_id` del usuario. Lanza `RuntimeException` si no tiene personaje activo.

```php
public static function requireActiveCharacter(int $userId): int
{
    global $db;
    $prefix = TABLE_PREFIX;
    $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$userId} LIMIT 1");
    $cfg = $db->fetch_array($cfg_q);
    $cid = $cfg ? (int)$cfg['active_pj_id'] : 0;
    if ($cid <= 0) {
        throw new \RuntimeException('Debes tener un personaje activo.');
    }
    return $cid;
}
```

#### `notifyStaffPending(string $title, string $linkPath): void`

Notifica a todos los miembros del staff (staff_level >= 2) sobre una nueva petición.

```php
public static function notifyStaffPending(string $title, string $linkPath): void
{
    $staff_q = $db->query("SELECT DISTINCT user_id FROM {$prefix}game_personajes WHERE staff_level >= 2");
    while ($row = $db->fetch_array($staff_q)) {
        $staff_uid = (int)$row['user_id'];
        if ($staff_uid > 0) {
            game_create_notification($staff_uid, 'admin_request_pending', $title, '', $link);
        }
    }
}
```

#### `create(...): int`

Crea un registro en `game_admin_requests`. Es el método central de creación de peticiones.

**Firma:**
```php
public static function create(
    int $userId,
    int $characterId,
    string $source,          // 'akuma_random' | 'akuma_demand' | 'mision' | 'form'
    string $requestKind,     // 'fruta_diablo' | etc
    string $title,
    string $description,
    ?string $link = null,
    ?array $payload = null,  // Array asociativo → JSON
    ?int $akumaFruitId = null
): int
```

**SQL generado:**
```php
$db->write_query("
    INSERT INTO {$prefix}game_admin_requests
    (user_id, character_id, source, request_kind, title, description, link, payload_json, akuma_fruit_id, status)
    VALUES ({$userId}, {$characterId}, '{$sourceEsc}', '{$kindEsc}', '{$titleEsc}', '{$descEsc}', {$linkSql}, {$payloadEsc}, {$akumaSql}, 'pendiente')
");
```

#### `reserveAkumaFruit(int $fruitId): void`

Marca una fruta como reservada. Previene condiciones de carrera.

**Validaciones:**
1. La tabla `game_akuma_no_mi` debe existir.
2. La fruta con `$fruitId` debe existir.
3. `is_occupied` debe ser 0.
4. `is_reserved` debe ser 0.

Si pasa todas, establece `is_reserved = 1`.

```php
public static function reserveAkumaFruit(int $fruitId): void
{
    // ... verifica existencia ...
    if ($occupied) throw new \RuntimeException('Esa Akuma no Mi ya está ocupada');
    if ($reserved) throw new \RuntimeException('Esa Akuma no Mi está reservada por otra petición');
    if ($db->field_exists('is_reserved', 'game_akuma_no_mi')) {
        $db->write_query("UPDATE {$prefix}game_akuma_no_mi SET is_reserved = 1 WHERE id = {$fid}");
    }
}
```

#### `releaseAkumaReservation(int $fruitId): void`

Libera la reserva de una fruta. Se llama cuando una petición es denegada.

```php
public static function releaseAkumaReservation(int $fruitId): void
{
    $db->write_query("UPDATE {$prefix}game_akuma_no_mi SET is_reserved = 0 WHERE id = {$fid} AND is_occupied = 0");
}
```

La cláusula `AND is_occupied = 0` es una salvaguarda: si por algún motivo la fruta ya fue ocupada (transición atómica fallida), no se revierte la ocupación.

#### `occupyAkumaFruit(int $fruitId): void`

Marca la fruta como ocupada. Se llama cuando una petición es aprobada.

```php
public static function occupyAkumaFruit(int $fruitId): void
{
    $sets = ["status = 'activa'"];
    if ($db->field_exists('is_occupied', 'game_akuma_no_mi')) {
        $sets[] = 'is_occupied = 1';
    }
    if ($db->field_exists('is_reserved', 'game_akuma_no_mi')) {
        $sets[] = 'is_reserved = 0';
    }
    $db->write_query('UPDATE ' . $prefix . 'game_akuma_no_mi SET ' . implode(', ', $sets) . " WHERE id = {$fid}");
}
```

Actualiza tres cosas simultáneamente:
1. `status = 'activa'` — Estado textual.
2. `is_occupied = 1` — Ocupado, no disponible.
3. `is_reserved = 0` — Ya no está reservado.

#### `resolve(int $requestId, int $staffUserId, int $staffCharId, string $action, string $staffNota = ''): array`

Resuelve una petición (aprueba o deniega). Es el método más complejo.

**Flujo:**
```
1. Obtener la petición con JOIN a game_personajes (para character_name y player_uid)
2. Validar que la petición esté en estado 'pendiente'
3. Actualizar estado a 'aprobada' o 'denegada'
4. Si tiene akuma_fruit_id:
   - Si aprueba → occupyAkumaFruit()
   - Si deniega → releaseAkumaReservation()
5. Si es source='mision' → manejar recompensas de misión
6. Enviar DM al jugador con la nota del staff
7. Crear notificación de sistema
8. Retornar ['status' => 'aprobada'|'denegada', 'request_id' => $requestId]
```

**Log de notas DM:**
Si `$staffNota` no está vacío, el staff puede enviar un mensaje directo al jugador junto con la resolución:
```php
if ($staffNota !== '' && $playerUid > 0 && $staffCharId > 0 && $characterId > 0) {
    $dmId = DirectMessageService::send(
        $staffCharId,
        $characterId,
        "Petición administrativa: {$req['title']}",
        "Tu petición ha sido {$label}.\n\n{$req['title']}\n\nRespuesta del Staff:\n{$staffNota}"
    );
}
```

---

## 7. Asignación de Carta Akuma

### 7.1 akuma_no_mi como card_type

Cuando el staff aprueba una petición de akuma, se crea una carta en `game_cards` con `card_type = 'akuma_no_mi'`.

**Campos específicos que establece `game_cards_apply_akuma_payload()`:**

```php
function game_cards_apply_akuma_payload(array &$input): void
{
    if (($input['card_type'] ?? '') !== 'akuma_no_mi') {
        return;
    }

    $effects = is_array($input['effects'] ?? null) ? $input['effects'] : [];
    $tier = max(1, min(5, (int)($input['tier'] ?? $effects['tier'] ?? 1)));
    $name = (string)($input['name'] ?? '');

    $input['effects'] = game_akuma_normalize_effects($effects, $name, $tier);
    $input['tier'] = $tier;
    $input['rank'] = game_akuma_rank_for_tier($tier);
    $input['activation'] = 'pasiva';
    $input['cost_pe'] = '0';
    $input['dice'] = '';
    $input['execution_stat'] = '';
    $input['execution_cost'] = 0;
    $input['reposo'] = 0;
    $input['duracion'] = 0;
}
```

**¿Por qué `activation = 'pasiva'`?**
La akuma no es una habilidad que se "activa" como una técnica. Es inherente al personaje. Sus poderes están siempre presentes (aunque ciertos sub-poderes puedan requerir activación, esos son cartas tipo `tecnica` separadas).

**¿Por qué `cost_pe = '0'`?**
La posesión de la fruta no cuesta PE. Las técnicas derivadas de la fruta (cartas tipo `tecnica`) son las que tienen coste de PE.

### 7.2 Efecto en Inventario

La carta akuma se asigna al personaje mediante:
```sql
INSERT INTO game_character_cards (character_id, card_id, current_rank, assigned_by)
VALUES ($characterId, $cardId, '$rank', $staffUserId);
```

- `current_rank` se establece según el tier (D para tier 1 → S para tier 5).
- La carta akuma NO se equipa en slots de inventario — es inherente.

### 7.3 Unicidad

El sistema garantiza que solo haya **una** carta akuma por personaje mediante:
- La query de verificación en `peticion_akuma.php:33-39`:
  ```sql
  SELECT c.id, c.name, c.tier, cc.current_rank, ...
  FROM game_character_cards cc
  JOIN game_cards c ON cc.card_id = c.id
  WHERE cc.character_id = {$char_id} AND c.card_type = 'akuma_no_mi'
  LIMIT 1
  ```
- El `UNIQUE KEY` en `game_character_cards` y la validación en `cards_assign.php`.

### 7.4 Actualización de effects_json Post-Aprobación

Cuando el staff crea la carta akuma, el sistema aplica `game_akuma_normalize_effects()` que:

1. Normaliza `akuma_type` y `subtipo`.
2. Toma la estructura defaults:
   ```php
   [
       'pasivas' => [],
       'transformaciones' => [],
       'capacidades_base' => [],
       'inmunidades' => [],
       'debilidades' => [
           'universal_agua_mar' => true,
           'universal_kairoseki' => true,
           'universal_haki_armamento' => true,
           'especificas' => [],
       ],
       'reglas_especiales' => [],
       'potencial_despertar' => [
           'disponible' => false,
           'descripcion' => '',
           'requisito_minimo' => 'Nivel 6 + ESP SS + aprobación staff',
       ],
       'referencia_tecnicas' => 'Las técnicas de esta fruta son cartas separadas de tipo tecnica...',
   ]
   ```
3. Mergea los datos del formulario del staff sobre los defaults.
4. Migra datos legacy (si `effects.efectos` existe pero `capacidades_base` está vacío).
5. Añade el tier, nombre de fruta, identidad.

---

## 8. Validación de Requisitos al Asignar

### 8.1 `game_akuma_assignment_error()`

**Archivo:** `akuma_helpers.php:138-178`

Verifica que el personaje cumple los requisitos para recibir la carta akuma:

```php
function game_akuma_assignment_error(int $characterId, array $cardRow): ?string
{
    // Solo valida para cartas tipo akuma_no_mi
    if (($cardRow['card_type'] ?? '') !== 'akuma_no_mi') {
        return null;
    }

    // Obtiene stats y data del personaje
    $pj = $db->query("SELECT stats_json, race_name, data_json FROM ... WHERE id = {$characterId}");
    $tier = game_akuma_tier_from_card($cardRow);
    
    // Requisitos según el tier
    $minEsp = \Game\Shared\StatScale::minEspRankForAkumaTier($tier);
    $minNivel = \Game\Shared\StatScale::minNivelForAkumaTier($tier);

    // Calcula ESP efectivo del personaje
    $espEff = (int)($ctx['effective_ranks']['esp'] ?? 1);
    $charNivel = game_get_character_nivel($data);

    // Validaciones
    if ($espEff < $minEsp) { return 'ESP efectivo insuficiente...'; }
    if ($charNivel < $minNivel) { return 'Nivel insuficiente...'; }

    return null; // OK
}
```

### 8.2 `game_akuma_tier_from_card()`

Extrae el tier efectivo de una carta, buscando en múltiples ubicaciones:

```php
function game_akuma_tier_from_card(array $card): int
{
    // 1. game_cards.tier
    if (isset($card['tier']) && (int)$card['tier'] > 0) return max(1, min(5, (int)$card['tier']));
    // 2. effects_json.tier (estructura ampliada)
    if (is_array($ef) && isset($ef['tier'])) return max(1, min(5, (int)$ef['tier']));
    // 3. game_cards.rank (legacy)
    if (isset($card['rank'])) return game_akuma_tier_from_rank((string)$card['rank']);
    // 4. Default
    return 1;
}
```

### 8.3 StatScale (Referencia)

Las constantes de requisitos se definen en `StatScale`:

| Tier | ESP mínimo requerido | Nivel mínimo requerido |
|:----:|:--------------------:|:----------------------:|
| 1 | D | 1 |
| 2 | C | 2 |
| 3 | B | 3 |
| 4 | A | 4 |
| 5 | S | 5 |

---

## 9. Estructura de effects_json para Akuma

### 9.1 Estructura Completa

Cuando el staff crea una carta akuma, `effects_json` contiene el siguiente esquema ampliado:

```json
{
    "akuma_type": "paramecia",
    "subtipo": "ninguno",
    "tier": 1,
    "nombre_fruta": "Nombre de la Fruta",
    "identidad": "Frase que define al usuario",

    "pasivas": [
        {
            "nombre": "Nombre de pasiva",
            "descripcion": "Descripción del efecto pasivo",
            "alcance": "personal",
            "tier_necesario_para_usar": 0
        }
    ],

    "transformaciones": [
        {
            "nombre": "Forma Híbrida / Forma Completa",
            "descripcion": "Descripción de la transformación",
            "bonus_stats": {"fue": 2, "res": 2, "agi": 1},
            "requisito": "Voluntad del usuario",
            "tier_necesario_para_usar": 1
        }
    ],

    "capacidades_base": [
        {
            "nombre": "Nombre de capacidad",
            "descripcion": "Descripción detallada",
            "alcance": "medio",
            "tipo_daño": "fisico",
            "tier_necesario_para_usar": 1
        }
    ],

    "inmunidades": [
        "Descripción de inmunidad"
    ],

    "debilidades": {
        "universal_agua_mar": true,
        "universal_kairoseki": true,
        "universal_haki_armamento": true,
        "especificas": [
            "Debilidad específica de la fruta"
        ]
    },

    "reglas_especiales": [
        "Regla especial 1",
        "Regla especial 2"
    ],

    "potencial_despertar": {
        "disponible": false,
        "descripcion": "Descripción del despertar potencial",
        "requisito_minimo": "Nivel 6 + ESP SS + aprobación staff"
    },

    "referencia_tecnicas": "Las técnicas de esta fruta son cartas separadas de tipo tecnica..."
}
```

### 9.2 Migración Legacy

El sistema soporta migración desde el formato legacy (donde `effects` era texto plano):

```php
// Si effects.efectos tiene texto pero capacities_base está vacío
if (!empty($effects['efectos']) && empty($structured['capacidades_base'])) {
    $structured['capacidades_base'][] = [
        'nombre' => 'Poderes (legacy)',
        'descripcion' => (string)$effects['efectos'],
        'alcance' => 'medio',
        'tier_necesario_para_usar' => $tier,
    ];
}
```

Esto permite que cartas akuma creadas antes de la migración al formato estructurado sigan siendo funcionales, mostrando su descripción legacy como una capacidad base.

---

## 10. Desventajas Universales

### 10.1 No Puede Nadar

**Mecánica:** Cualquier personaje con una carta `akuma_no_mi` activa NO puede realizar acciones de natación.

**Representación en DB:**
En `effects_json.debilidades`:
```json
{
    "universal_agua_mar": true
}
```

**Implicaciones mecánicas:**
- El personaje no puede cruzar cuerpos de agua nadando.
- Caer al agua durante un combate significa quedar indefenso (no puede moverse ni usar poderes).
- Los personajes con akuma evitan barcos pequeños, tormentas en alta mar, o combates cerca del agua.
- El personaje se hunde inmediatamente al sumergirse más allá de la cintura.

**Implementación técnica:**
- La validación se hace a nivel narrativo (no hay un flag mecánico de "ahogándose"). El jugador es responsable de rolear la debilidad.
- El staff puede intervenir si un jugador no respeta esta desventaja.
- En combate, si un personaje con akuma es enviado al agua, se considera fuera de combate a menos que un aliado lo rescate.

### 10.2 Pérdida de Poderes en Agua/Kairoseki

**Mecánica:** Cuando el personaje está sumergido en agua de mar o en contacto con Kairoseki (piedra del mar), pierde todos sus poderes activos y pasivos de la akuma.

**Representación en DB:**
```json
{
    "universal_kairoseki": true,
    "universal_haki_armamento": true
}
```

**Implicaciones mecánicas:**
- **Agua de mar:** Sumergirse (más de la cintura) anula los poderes. Salpicaduras o lluvia no afectan.
- **Kairoseki:** El contacto directo con grilletes, armas o barras de Kairoseki anula los poderes instantáneamente. El personaje queda débil (sin fuerza).
- **Haki de Armamento:** Un ataque con Haki de Armamento puede dañar a usuarios de Logia (anulando su intangibilidad). Esto está representado como debilidad universal, no específica de Logia — es un pilar del sistema de combate.

**Diseño de las debilidades universales:**
A diferencia de debilidades específicas (que varían por fruta), las tres debilidades universales SIEMPRE están presentes en TODAS las akuma:

| Debilidad | `efectos_json.debilidades` | Afecta a |
|-----------|---------------------------|----------|
| Agua de mar | `universal_agua_mar: true` | Todas las akuma |
| Kairoseki | `universal_kairoseki: true` | Todas las akuma |
| Haki de Armamento | `universal_haki_armamento: true` | Especialmente Logia |

### 10.3 Filosofía de las Desventajas

**¿Por qué estas desventajas existen?**
1. **Balance:** Las akuma otorgan poder masivo. Sin desventajas, no haber razón para no tener una. El agua y Kairoseki son los "costes" de tener superpoderes.
2. **Narrativa canónica:** One Piece tiene estas reglas universales. El sistema las respeta.
3. **Oportunidades de rol:** La debilidad al agua genera tramas (miedo a cruzar océanos, dependencia de compañeros que naden, búsqueda de métodos para contrarrestar).
4. **Profundidad táctica:** En combate, intentar lanzar al rival al agua o usar Kairoseki son estrategias viables.

**¿Por qué no hay una validación automática en el sistema?**
- El agua y Kairoseki son contextuales (dependen del escenario, no de un flag binario).
- Sería excesivamente complejo rastrear "¿está el personaje mojado?" en cada post.
- Se confía en el roleo y en la supervisión del staff para garantizar que se respeten.

---

## 11. Sistema de Despertar (Awakening)

### 11.1 Arquitectura General

El despertar es un sistema de progresión que permite a los usuarios de akuma desbloquear el potencial máximo de su fruta. Está diseñado como un **sistema de uso** (usage-based) con dos niveles: Incompleto y Completo.

La interfaz central es `peticion_akuma.php`, que funciona como **hub de despertar** cuando el personaje ya posee una akuma.

### 11.2 Hub UI: `peticion_akuma.php`

**Archivo:** `back/forum/game/public/peticion_akuma.php`

Cuando el personaje tiene una carta `akuma_no_mi`, la página detecta la presencia y muestra el panel de awakening:

**Detección:**
```php
$q = $db->query("
    SELECT c.id, c.name, c.tier, cc.current_rank,
           (SELECT COUNT(*) FROM {$prefix}game_post_cards pc 
            WHERE pc.character_id = {$char_id} AND pc.card_id = c.id) as usos_totales
    FROM {$prefix}game_character_cards cc
    JOIN {$prefix}game_cards c ON cc.card_id = c.id
    WHERE cc.character_id = {$char_id} AND c.card_type = 'akuma_no_mi'
    LIMIT 1
");
```

**Cálculo de progreso:**

```php
$usos_base = 30;  // Default para tier 1-2
if ($tier == 3) $usos_base = 50;
if ($tier == 4) $usos_base = 75;
if ($tier >= 5) $usos_base = 100;

$usos_pre = (int)ceil($usos_base / 2);  // Umbral para Incompleto
$has_pre = $pre_awakening_card ? true : false;

if ($has_pre) {
    // Penalización del 33% si ya tiene Incompleto
    $usos_final = (int)ceil($usos_base * 1.33);
} else {
    $usos_final = $usos_base;
}
```

**Tabla de umbrales por tier:**

| Tier | `usos_base` | `usos_pre` (Incompleto) | `usos_final` (Completo) | `usos_final` con penalización |
|:----:|:-----------:|:-----------------------:|:-----------------------:|:----------------------------:|
| 1 | 30 | 15 | 30 | 40 |
| 2 | 30 | 15 | 30 | 40 |
| 3 | 50 | 25 | 50 | 67 |
| 4 | 75 | 38 | 75 | 100 |
| 5 | 100 | 50 | 100 | 133 |

**Progreso visual:**
```html
<progress class="rpg-awakening-hub-progress-bar" value="<?= $usos_totales ?>" max="<?= max(1, $usos_final) ?>"></progress>
```

### 11.3 Estados del Despertar

#### Sin Despertar
- `$usos_totales < $usos_pre`: Botones bloqueados, mensaje "Ningún Awakening activo".
- `$usos_totales >= $usos_pre`: Botón "Solicitar Despertar Incompleto" habilitado.

#### Despertar Incompleto (Pre-Awakening)
- Se ha adquirido la carta de Pre-Awakening (detectada por nombre LIKE '%Pre-Awakening%' OR LIKE '%Despertar Incompleto%').
- `$has_pre = true`, se activa penalización del 33%.
- Se muestra mensaje: "Despertar Incompleto (Adquirido) — Penalización activa".
- Botón de Despertar Incompleto se oculta.
- Botón de Despertar Completo se habilita cuando `$usos_totales >= $usos_final`.

#### Despertar Completo (Full Awakening)
- El personaje ha alcanzado el despertar completo.
- Ya no se muestran opciones de solicitud de awakening.
- La carta akuma debe ser actualizada por el staff para reflejar las nuevas capacidades.

### 11.4 Detección de Pre-Awakening

```php
$q2 = $db->query("
    SELECT c.id 
    FROM {$prefix}game_character_cards cc
    JOIN {$prefix}game_cards c ON cc.card_id = c.id
    WHERE cc.character_id = {$char_id} 
      AND (c.name LIKE '%Pre-Awakening%' OR c.name LIKE '%Despertar Incompleto%')
    LIMIT 1
");
```

La detección se hace por nombre de carta (no por flag en effects_json) porque:
- Es más simple y no requiere migración de datos.
- Los nombres de carta son controlados por el staff y predecibles.
- Permite que la carta de Pre-Awakening sea una carta independiente (con su propio effects_json) en lugar de modificar la carta akuma original.

### 11.5 Formulario de Solicitud: `peticion_awakening.php`

**Archivo:** `back/forum/game/public/peticion_awakening.php`

Renderiza el formulario de solicitud de despertar, diferenciando entre Incompleto y Completo mediante `$_GET['type']`.

**Tipo de solicitud:**
```php
$type = $_GET['type'] ?? 'full';
$is_pre = ($type === 'pre');
```

**Campos del formulario:**
1. **Link a condición narrativa** (`link`): URL al hilo/post donde se cumple la condición estipulada en la carta (requerido).
2. **Propuesta de poderes/efectos** (`propuesta_poderes`): Textarea describiendo cómo se manifiesta el despertar y qué mecánicas nuevas sugiere el jugador.
3. **Aviso de drawbacks** (solo para Incompleto): "Al ser un Despertar Incompleto, el staff añadirá 'drawbacks' (consecuencias negativas) a la carta."

**Hidden field:**
```html
<input type="hidden" id="awakening_type" value="pre_awakening">
<!-- o -->
<input type="hidden" id="awakening_type" value="full_awakening">
```

### 11.6 Despertar Incompleto vs Completo

| Aspecto | Incompleto (Pre-Awakening) | Completo (Full Awakening) |
|---------|---------------------------|--------------------------|
| Umbral de usos | 50% del base (`usos_pre`) | 100% del base (`usos_final`) |
| Penalización | Si se obtiene, el umbral completo sube 33% | No aplica |
| Drawbacks | **Obligatorios:** el staff debe añadir consecuencias negativas | Sin drawbacks obligatorios |
| Carta resultante | Se crea carta separada "Pre-Awakening/Despertar Incompleto" | Se modifica la carta akuma original o se crea versión mejorada |
| Beneficios | Poder parcial desbloqueado, pero con limitaciones | Poder completo desbloqueado |
| Filosofía | "Despertar a medias, con precio" | "Despertar total, sin restricciones" |

**Filosofía de la penalización del 33%:**
El Incompleto otorga poder ANTES de tiempo. La penalización de +33% en el umbral final garantiza que:
- Obtener el Incompleto temprano retrasa el Completo.
- El jugador siente el peso de su decisión: "poder ahora" vs "poder completo después".
- El staff tiene tiempo para evaluar cómo el Incompleto afecta el balance antes de que llegue el Completo.

### 11.7 JS de Awakening

El archivo `jscripts/game/peticion_awakening.js` (referenciado en `peticion_awakening.php:66`) maneja:
- Envío AJAX del formulario.
- Validación de campos.
- Manejo de errores y mensajes de respuesta.

### 11.8 Progresión Visual en el Hub

El hub de awakening muestra tres indicadores clave:

**Barra de progreso:**
```
[████████████░░░░░░░░░░] 45/100 usos
```

**Estado del despertar:**
- Sin despertar: ❌ Ningún Awakening activo
- Incompleto: ✅ Despertar Incompleto (Adquirido) — Penalización activa
- Completo: ✅ Awakening Completo (depende de resolución staff)

**Botones de acción:**
- Bloqueados (grises, con candado) si no se cumple el umbral.
- Habilitados (con color, clickeables) si se cumple.
- Ocultos si ya se tiene el siguiente nivel.

---

## 12. Herramientas de Staff

### 12.1 Revisión de Peticiones

Las peticiones de akuma se revisan en la zona de staff (`zona_staff_peticiones.php`). El staff ve:

**Para peticiones aleatorias:**
- `source = 'akuma_random'`
- `request_kind = 'fruta_diablo'`
- Título: "Akuma aleatoria: [nombre de fruta]"
- Payload: `{mode: 'random', fruit_id, fruit_name, class, class_name}`
- Acción: Aprobar (crea la carta akuma y asigna) o Denegar (libera la fruta).

**Para peticiones bajo demanda:**
- `source = 'akuma_demand'`
- `request_kind = 'fruta_diablo'`
- Título: "Solicitud: [nombre de fruta]"
- Payload: `{mode: 'demand', fruit_id, fruit_name, motivo, justificacion, link?}`
- Acción: Aprobar o Denegar, con nota opcional al jugador.

**Para peticiones de awakening:**
- Se revisan en el mismo panel.
- El staff debe evaluar:
  - Link a condición narrativa (¿realmente cumplió la condición?).
  - Propuesta de poderes (¿es balanceada?).
  - Historial de usos de la carta akuma.
  - Para Incompleto: ¿qué drawbacks añadir?

### 12.2 Creación de Cartas Akuma: `cartas_staff.php`

**Archivo:** `back/forum/game/public/cartas_staff.php` (líneas 257-289)

El formulario de creación de cartas en el panel de staff incluye una sección especial para akuma:

```html
<div id="fields-akuma" class="rpg-staff-field-section">
    <div>
        <label>Tipo de Akuma</label>
        <select id="akuma_type">
            <option value="paramecia">Paramecia</option>
            <option value="logia">Logia</option>
            <option value="zoan">Zoan</option>
        </select>
    </div>
    <div id="wrapper-akuma-subtipo">
        <label>Subtipo Zoan</label>
        <select id="akuma_subtipo">
            <option value="ninguno">Ninguno</option>
            <option value="antiguo">Antiguo</option>
            <option value="mitico">Mítico</option>
        </select>
    </div>
    <div>
        <label>Tier de poder (1–5)</label>
        <input type="number" id="akuma_tier" min="1" max="5" value="1">
        <p class="hint">Determina rango de carta y requisitos ESP/nivel al asignar.</p>
    </div>
    <div>
        <label>Identidad del poder</label>
        <textarea id="akuma_identidad" rows="2" placeholder="Una frase que define qué ES el usuario con esta fruta."></textarea>
    </div>
    <div>
        <label>Estructura ampliada (JSON)</label>
        <textarea id="akuma_structured" rows="14" spellcheck="false"></textarea>
        <button type="button" id="akuma_structured_reset">Cargar plantilla vacía</button>
    </div>
</div>
```

**Campos:**
1. `akuma_type`: Select con las 3 clases.
2. `akuma_subtipo`: Select visible solo cuando `akuma_type = 'zoan'` (controlado por JS).
3. `akuma_tier`: Número 1-5.
4. `akuma_identidad`: Texto corto que identifica el poder.
5. `akuma_structured`: Editor JSON para la estructura ampliada (pasivas, transformaciones, etc.).

**JS asociado (`cartas_staff.js`):**
- Muestra/oculta el campo de subtipo según el tipo seleccionado.
- Carga plantilla vacía desde `game_akuma_structured_defaults()`.
- Valida el JSON antes de enviar.

### 12.3 Gestión de Frutas en Catálogo

El staff puede:
- **Añadir nuevas frutas** al catálogo `game_akuma_no_mi` con todos sus datos (clase, tier, subtipo, descripción, etc.).
- **Editar frutas existentes** (cambiar tier, clase, disponibilidad manual).
- **Marcar frutas como ocupadas/reservadas manualmente** (para casos excepcionales).
- **Visualizar estadísticas** (cuántas frutas hay, cuántas están libres/ocupadas/reservadas).

### 12.4 Resolución de Awakening

Cuando un staff resuelve una petición de awakening:

1. **Evaluar el cumplimiento** de la condición narrativa (link proporcionado).
2. **Evaluar la propuesta** de nuevos poderes/efectos.
3. **Para Despertar Incompleto:**
   - Crear carta "Pre-Awakening / Despertar Incompleto" con:
     - Los beneficios parciales aprobados.
     - Drawbacks obligatorios (consecuencias negativas mecánicas).
     - La penalización de +33% en el umbral completo se aplica automáticamente en el hub.
4. **Para Despertar Completo:**
   - Modificar la carta akuma original o crear versión mejorada.
   - Actualizar `effects_json.potencial_despertar.disponible = true`.
   - Añadir los nuevos poderes/efectos al `effects_json`.
5. **Notificar al jugador** con el resultado y la nota.

### 12.5 Notificaciones Automáticas

El sistema notifica automáticamente al staff cuando:
- Se crea una petición aleatoria.
- Se crea una petición bajo demanda.
- Se crea una petición de awakening.

Y notifica al jugador cuando:
- Su petición es aprobada o denegada.
- El staff añade una nota.

---

## 13. Flujo Completo: Solicitud → Asignación

### 13.1 Flujo Aleatorio

```
JUGADOR                              SISTEMA                            STAFF
────────────────────────────────────────────────────────────────────────────
1. Abre peticion_akuma.php
                                      2. Detecta personaje activo
                                      3. Muestra hub + opciones
4. Clica "Aleatoria"
                                      5. peticion_akuma_aleatoria.php
                                      6. Carga catálogo (akuma_catalog.php)
                                      7. Muestra frutas disponibles
8. Clica "¡Tirar aleatorio!"
                                      9. akuma_roll.php (POST)
                                      10. Valida: login, CSRF, personaje
                                      11. Verifica: puede tirar (can_roll)
                                      12. Pool: SELECT disponibles
                                      13. random_int() → fruta seleccionada
                                      14. reserveAkumaFruit(fruitId)
                                      15. create(akuma_random, ...)
                                      16. notifyStaffPending(...)
                                      17. Muestra resultado al jugador
                                                                          18. Staff recibe notificación
                                                                          19. Abre panel de peticiones
                                                                          20. Evalúa la petición
                                                                          21. Aprueba o Deniega
                                      22. AdminRequestService::resolve()
                                      23. Si aprueba:
                                          - occupyAkumaFruit()
                                          - Crea carta akuma_no_mi
                                          - Asigna al personaje
                                      24. Si deniega:
                                          - releaseAkumaReservation()
                                      25. Notifica al jugador
26. Jugador recibe notificación
27. Si aprobada: ve la carta en su deck
```

### 13.2 Flujo Bajo Demanda

```
JUGADOR                              SISTEMA                            STAFF
────────────────────────────────────────────────────────────────────────────
1. Abre peticion_akuma.php
2. Clica "Bajo demanda"
                                      3. peticion_akuma_demanda.php
                                      4. Carga catálogo (solo disponibles)
5. Selecciona fruta
6. Escribe motivo + justificación
7. Envía formulario
                                      8. Valida disponibilidad
                                      9. reserveAkumaFruit(fruitId)
                                      10. create(akuma_demand, ...)
                                      11. notifyStaffPending(...)
                                                                          12. Staff recibe notificación
                                                                          13. Evalúa: ¿Merece la fruta?
                                                                          14. ¿Encaja con el personaje?
                                                                          15. ¿Hay frutas mejores para otros?
                                                                          16. Aprueba o Deniega (+ nota)
                                      17. AdminRequestService::resolve()
                                      18. Si aprueba:
                                          - occupyAkumaFruit()
                                          - Staff crea carta akuma manualmente
                                          - Asigna carta al personaje
                                      19. Si deniega:
                                          - releaseAkumaReservation()
                                          - Envía DM con nota
20. Jugador recibe notificación
```

### 13.3 Flujo de Awakening

```
JUGADOR                              SISTEMA                            STAFF
────────────────────────────────────────────────────────────────────────────
1. Tiene akuma, usa la carta en posts
                                      2. game_post_cards registra usos
                                      ... (repite hasta alcanzar umbral)
3. Abre peticion_akuma.php
                                      4. Hub muestra progreso
                                      5. usos_totales >= usos_pre
                                      6. Botón "Solicitar Despertar Incompleto"
7. Clica el botón
                                      8. peticion_awakening.php?type=pre
9. Rellena formulario (link + propuesta)
10. Envía
                                      11. Crea petición de awakening
                                                                          12. Staff revisa
                                                                          13. Evalúa propuesta
                                                                          14. Para Incompleto: diseña drawbacks
                                                                          15. Aprueba/Deniega
                                      16. Si aprueba Incompleto:
                                          - Crea carta Pre-Awakening
                                          - Hub detecta y aplica penalización
                                      17. Si aprueba Completo:
                                          - Actualiza carta akuma
                                          - Marca despertar como completo
```

---

## 14. Filosofía de Diseño

### 14.1 ¿Por qué existe la tirada aleatoria?

**Emoción y descubrimiento:** La aleatoriedad captura la esencia de One Piece — nunca sabes qué fruta vas a encontrar. Un jugador puede terminar con una fruta que nunca habría elegido, creando tramas únicas.

**Balance y disponibilidad:** Evita que los jugadores siempre escojan las frutas más poderosas (Logia tier 5). El azar distribuye las frutas entre los jugadores de forma más equitativa.

**Simplicidad:** Para jugadores indecisos o nuevos, la opción aleatoria elimina la parálisis de elección. "El sistema decidió por mí."

**Límite único:** Al ser solo una vez por personaje, la tirada aleatoria es un momento especial. No es un recurso farmeable.

### 14.2 ¿Por qué existe la solicitud bajo demanda?

**Agencia del jugador:** Un jugador con una historia específica en mente puede buscar la fruta que encaje con su concepto. No todo debe dejarse al azar.

**Tramas dirigidas:** El staff puede aprobar frutas que generen historias interesantes. Un jugador que planea un arco narrativo alrededor de una fruta específica tiene un camino para obtenerla.

**Flexibilidad:** Permite que el staff evalúe cada caso individualmente, en lugar de aplicar una regla rígida para todos.

### 14.3 ¿Por qué el despertar está basado en usos?

**Compromiso con el personaje:** El despertar no se compra ni se regala. Se gana usando la fruta en situaciones reales de juego. Esto garantiza que solo personajes que han roleado extensamente con su akuma puedan despertarla.

**Progresión natural:** Los usos de la carta akuma en posts (`game_post_cards`) son un registro automático y objetivo. No requiere intervención del staff para contar.

**Incentivo a rolear:** Para llegar al despertar, el jugador debe participar activamente en el foro, usando su fruta en combates y situaciones narrativas. Esto fomenta la actividad.

### 14.4 ¿Por qué la distinción Incompleto/Completo?

**Premio progresivo:** El Incompleto da un premio a mitad de camino, manteniendo la motivación del jugador. No tener nada hasta llegar a 100 usos sería frustrante.

**Coste de oportunidad:** El jugador elige entre poder ahora (Incompleto, con drawbacks y penalización) o poder después (Completo, sin penalización). Es una decisión estratégica.

**Diseño de drawbacks:** El Incompleto obliga al staff a pensar "¿cuál es el precio de este poder adelantado?" Esto genera creatividad en el diseño de desventajas.

### 14.5 ¿Por qué el sistema de ocupado/reservado?

**Integridad transaccional:** `is_reserved` previene que dos jugadores soliciten la misma fruta simultáneamente. Sin este mecanismo, ambos podrían recibir una notificación de "disponible" y enviar sus solicitudes antes de que el sistema actualice `is_occupied`.

**Transparencia:** El jugador ve el estado de cada fruta en el catálogo. Si está reservada, sabe que alguien más la está solicitando.

**Rastro de auditoría:** Combinando `game_admin_requests.akuma_fruit_id` con los flags de `game_akuma_no_mi`, el staff puede rastrear qué frutas han sido solicitadas y por quién.

### 14.6 Principios de Diseño del Sistema

1. **La akuma es inherente:** No es un objeto que se equipe o desequipe. Es parte del personaje.
2. **Las técnicas son cartas separadas:** La akuma es la fuente de poder; las técnicas que usa son cartas tipo `tecnica` que requieren la akuma como prerequisito narrativo.
3. **Disponibilidad controlada:** No hay frutas infinitas. Cada fruta en el catálogo es única y solo un personaje puede poseerla a la vez.
4. **El despertar se gana:** No hay atajos para el despertar completo. Los usos son el único camino.
5. **El staff es guardián del balance:** Aunque el sistema automatiza la tirada y el conteo de usos, la decisión final de asignar una fruta o aprobar un despertar es siempre del staff.

---

## 15. Consejos para Jugadores

### 15.1 Elegir entre Aleatoria y Demanda

| Si tú... | Elige... |
|----------|----------|
| Quieres cualquier fruta, te gusta la sorpresa | **Aleatoria** |
| Tienes una historia específica en mente | **Demanda** |
| No estás seguro de qué fruta quieres | **Aleatoria** (el sistema decide) |
| Quieres una fruta de tier alto (Logia/Zoan mítico) | **Demanda** (con buena justificación) |
| Quieres empezar a jugar rápido | **Aleatoria** (es más simple) |
| Eres veterano y quieres un poder concreto | **Demanda** |

**Recomendación:** Si es tu primer personaje, usa la tirada aleatoria. Es más rápida, te sorprenderá, y evitarás la parálisis de análisis.

### 15.2 Construir hacia el Despertar

- **Usa tu akuma en cada oportunidad.** Cada post donde uses tu carta akuma cuenta como un uso. No la guardes.
- **Participa en combates.** Los combates suelen requerir múltiples usos de cartas, acelerando tu progreso.
- **Documenta usos fuera de combate.** Si usas tu akuma para algo narrativo (abrir una puerta, cruzar un precipicio), asegúrate de que quede registrado en `game_post_cards`.
- **Planifica tu propuesta de despertar.** Mientras acumulas usos, piensa en qué poderes te gustaría desbloquear. Una propuesta bien pensada tiene más probabilidades de ser aprobada por el staff.

### 15.3 Trabajar alrededor de las Debilidades

- **Nunca estés solo cerca del agua.** Siempre ten un compañero que pueda rescatarte.
- **Invierte en Haki de Armamento.** Es la principal defensa contra Logias y otros usuarios de akuma.
- **Rolea la debilidad.** No ignores que no puedes nadar. Si caes al agua y milagrosamente sobrevives, la experiencia será más memorable.
- **Kairoseki es raro.** No tengas miedo constante, pero sé consciente de que existe.

### 15.4 Roll vs Request: Estrategia

- Si tiras aleatorio y obtienes una fruta que no te gusta, puedes rechazar la petición (no aprobarla) y quedarte sin akuma. Pero entonces habrás gastado tu única tirada aleatoria. **Elige sabiamente.**
- Si haces una solicitud bajo demanda, invierte tiempo en la justificación. Un "porque quiero ser poderoso" no convencerá al staff. Enlaza tu solicitud a la historia de tu personaje.
- Una akuma no define a tu personaje. Incluso una fruta "débil" (tier 1-2) puede ser increíblemente divertida de rolear. La creatividad vale más que el tier.

---

## 16. Consejos para Staff

### 16.1 Evaluar Solicitudes de Fruta

**Criterios para aprobar una demanda:**
1. **¿La fruta encaja con el personaje?** (historia, personalidad, estilo de combate)
2. **¿El jugador tiene un plan narrativo?** (¿qué historias planea contar con esta fruta?)
3. **¿Hay personajes más adecuados para esta fruta?** (considera el reparto general)
4. **¿Cuánto tiempo lleva el jugador en el foro?** (los jugadores nuevos deberían empezar con frutas de tier bajo)
5. **¿Es una fruta de tier alto?** (Logia tier 5 solo para jugadores veteranos con historias excepcionales)

**Criterios para denegar:**
- El jugador ya tiene otra akuma (no es posible tener dos).
- La fruta está ocupada (error del sistema — no debería mostrarse como disponible).
- La justificación es insuficiente ("porque mola" no es una justificación).
- El tier de la fruta excede el nivel/ESP del personaje (el sistema lo validará al asignar, pero mejor denegar antes).

### 16.2 Balancear el Poder de las Frutas

- **Tier 1-2:** Frutas con efectos situacionales o limitados. Sin grandes ventajas de combate.
- **Tier 3:** Frutas con ventaja táctica clara. Pueden cambiar el rumbo de un combate con creatividad.
- **Tier 4:** Frutas con poder ofensivo/defensivo significativo. Requieren contramedidas (Haki, estrategia).
- **Tier 5:** Frutas con poder masivo. Solo para personajes de alto nivel. Requieren coordinación narrativa.

**Regla de oro:** Una fruta no debería poder resolver cualquier situación por sí sola. Siempre debe haber una debilidad, un contraataque, o un coste.

### 16.3 Gestionar la Progresión de Awakening

- **No apresures los despertar.** 100 usos de carta akuma a ~2 usos por post = ~50 posts de combate. Es un compromiso significativo.
- **Evalúa la propuesta de poderes.** El jugador propone; el staff ajusta. No aceptes la propuesta tal cual sin revisarla.
- **Para Incompleto: sé creativo con los drawbacks.** Algunas ideas:
  - Pérdida de control temporal (el poder se activa sin querer).
  - Daño colateral (amigos o entorno afectados).
  - Agotamiento extremo tras usar el poder.
  - Transformación incompleta que deja expuesto al usuario.
- **Para Completo: asegúrate de que el premio vale la pena.** El jugador invirtió meses en llegar. El despertar debería ser memorable.

### 16.4 Crear Nuevas Cartas Akuma

Al crear una carta akuma en `cartas_staff.php`:

1. **Define bien el tipo y subtipo.** Una Paramecia no puede tener subtipo Zoan. Una Zoan no puede tener subtipo si no es antiguo o mítico.
2. **Establece el tier según el poder real.** No infles el tier porque "suena cool".
3. **Rellena la estructura JSON.** No dejes campos vacíos. Si no hay transformaciones, pon `"transformaciones": []`. Si no hay inmunidades, pon `"inmunidades": []`.
4. **La identidad es importante.** "Una frase que define qué ES el usuario con esta fruta" — es el norte narrativo de la fruta.
5. **Debilidades específicas.** Si la fruta tiene una debilidad única (ej: una Logia de arena es débil al agua), documéntala en `debilidades.especificas`.
6. **Potencial de despertar.** Define si la fruta puede despertar. No todas las frutas deberían poder hacerlo (o al menos, no igual de fácil).

---

## 17. Referencia Rápida de Archivos

### PHP — Páginas Públicas

| Archivo | Propósito |
|---------|-----------|
| `game/public/peticion_akuma.php` | Hub principal: muestra opciones de solicitud + panel de awakening |
| `game/public/peticion_akuma_aleatoria.php` | Interfaz de tirada aleatoria con catálogo |
| `game/public/peticion_akuma_demanda.php` | Formulario de solicitud bajo demanda |
| `game/public/peticion_awakening.php` | Formulario de solicitud de despertar (incompleto/completo) |
| `game/public/cartas_staff.php` | Creación/edición de cartas (sección akuma) |

### PHP — AJAX

| Archivo | Propósito |
|---------|-----------|
| `game/ajax/akuma_roll.php` | Ejecuta tirada aleatoria + crea petición |
| `game/ajax/akuma_catalog.php` | Devuelve catálogo completo con estados |

### PHP — Helpers y Servicios

| Archivo | Propósito |
|---------|-----------|
| `game/inc/akuma_helpers.php` | Tier map, normalize effects, assignment validation |
| `game/src/Application/Services/AdminRequestService.php` | Create, reserve, release, occupy, resolve |

### SQL — Migraciones

| Archivo | Propósito |
|---------|-----------|
| `game/sql/migrate_akuma_peticiones.php` | Añade is_occupied, is_reserved, power_range + crea game_admin_requests |
| `game/sql/migrate_akuma_tier.php` | Añade tier y subtipo a game_akuma_no_mi |

### JavaScript

| Archivo | Propósito |
|---------|-----------|
| `jscripts/game/akuma_no_mi.js` | Interactividad del catálogo (filtros, modal) |
| `jscripts/game/peticion_akuma_aleatoria.js` | Tirada aleatoria (carga, ruleta, resultado) |
| `jscripts/game/peticion_akuma_demanda.js` | Envío de solicitud bajo demanda |
| `jscripts/game/peticion_awakening.js` | Envío de solicitud de despertar |

### Base de Datos

| Tabla | Propósito |
|-------|-----------|
| `game_akuma_no_mi` | Catálogo maestro de frutas del diablo |
| `game_admin_requests` | Peticiones administrativas (akuma + otros) |
| `game_cards` | Catálogo de cartas (las akuma son `card_type = 'akuma_no_mi'`) |
| `game_character_cards` | Inventario de cartas por personaje |
| `game_post_cards` | Registro de usos de cartas en posts |
| `game_personajes` | Personajes (para validación de stats/nivel) |

---

> **Fin de la guía 11 — Akuma no Mi**
>
> Esta guía está diseñada para que un AI pueda operar el sistema de Akuma no Mi de forma autónoma. Cada función, tabla y flujo está documentado con su propósito, implementación y filosofía subyacente. Las decisiones de diseño están explicadas para que el AI pueda tomar decisiones consistentes con la arquitectura del sistema.
