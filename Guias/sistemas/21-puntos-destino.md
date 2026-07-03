# 21. Puntos Destino (PD) — Premios

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 21
> **Propósito:** Documentar exhaustivamente el subsistema de Puntos Destino: moneda de mérito, modelo de datos, endpoints de compra, catálogo de premios, filosofía de diseño, y consejos para jugadores y staff.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [Modelo de Datos](#2-modelo-de-datos)
3. [Tabla `game_pd_purchases`](#3-tabla-game_pd_purchases)
4. [Helpers PHP — Capa de Acceso a Datos](#4-helpers-php)
5. [Cómo se Ganan los PD](#5-cómo-se-ganan-los-pd)
6. [Catálogo de Premios](#6-catálogo-de-premios)
7. [JSON Schema de Premios](#7-json-schema-de-premios)
8. [Flujo de Compra — `pd_purchase.php`](#8-flujo-de-compra)
9. [Frontend — Tienda de Destino](#9-frontend)
10. [Historial de Compras — `pd_history.php`](#10-historial-de-compras)
11. [Concesión de PD por Staff](#11-concesión-de-pd-por-staff)
12. [Flujo de Datos Completo](#12-flujo-de-datos-completo)
13. [Filosofía de Diseño](#13-filosofía-de-diseño)
14. [Impacto RPG](#14-impacto-rpg)
15. [Consejos para Jugadores](#15-consejos-para-jugadores)
16. [Consejos para Staff](#16-consejos-para-staff)
17. [Guía de Troubleshooting](#17-guía-de-troubleshooting)

---

## 1. Arquitectura General

### 1.1 Capas del Subsistema

```
┌─────────────────────────────────────────────────────────────┐
│                    CLIENTE (Navegador)                       │
│  ┌──────────────────────┐  ┌──────────────────────────────┐ │
│  │ tienda_destino.js    │  │ personaje_page.js (gestion)  │ │
│  │ (compra PD)          │  │ (dashboard PD + histórico)   │ │
│  └──────────┬───────────┘  └──────────────┬───────────────┘ │
└─────────────┼──────────────────────────────┼─────────────────┘
              │ POST (JSON + CSRF)           │ GET
┌─────────────┼──────────────────────────────┼─────────────────┐
│  ┌──────────▼──────────────────────────────▼───────────────┐ │
│  │              PHP — CAPA DE APLICACIÓN                    │ │
│  │  pd_purchase.php    (compra y registro)                  │ │
│  │  pd_history.php     (historial JSON)                     │ │
│  │  AdminRequestService (concesión por staff)                │ │
│  │  pd_helpers.php     (funciones de acceso)                │ │
│  │  game_postcharacter.php (plugin: PD por posts)           │ │
│  └─────────────────────────────────────────────────────────┘ │
│                              │                                │
│                              ▼                                │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │           MySQL (MyBB + tablas game_*)                   │ │
│  │  game_personajes.puntos_destino  (saldo total)           │ │
│  │  game_pd_purchases              (registro de gastos)     │ │
│  │  game_missions / game_mission_participants (concesión)   │ │
│  └─────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### 1.2 Filosofía de la Arquitectura

**¿Por qué saldo total en `game_personajes` + gastos en tabla separada?**
- El saldo total (`puntos_destino` en `game_personajes`) se incrementa SOLO por el staff o por recompensas de misiones. Es un contador acumulativo que nunca decrece directamente.
- Los gastos se registran en `game_pd_purchases` con cada compra. El PD disponible se calcula en runtime como `total - SUM(pd_cost)`.
- Esto da trazabilidad completa: en cualquier momento se puede ver cuánto PD ganó un personaje, cuánto gastó, y en qué lo gastó.
- Si hubiera que revertir una compra, basta con borrar o anular el registro en `game_pd_purchases` — no hay que modificar el saldo total.

**¿Por qué `pd_helpers.php` como funciones globales y no como clase?**
- Porque se usan desde AJAX endpoints (`pd_purchase.php`, `pd_history.php`) y desde vistas PHP (`_tab_gestion.php`, `tienda_destino.php`). Las funciones globales están disponibles sin necesidad de instanciar servicios o usar autoload en contextos de vista.
- No hay lógica de negocio compleja aquí: son operaciones de lectura/escritura directas contra la DB.

**¿Por qué el PD disponible se calcula como `total - spent` en lugar de tener un campo `pd_available`?**
- Para evitar inconsistencias. Si hubiera un campo `pd_available`, cada compra y cada concesión tendría que mantenerlo sincronizado. Con la fórmula en runtime, el saldo disponible siempre es exacto.
- Coste insignificante: una compra requiere 2 queries (SELECT saldo, SELECT SUM gastos) o en la práctica se resuelve con `game_get_character_pd_available()` que hace ambas.

### 1.3 Diagrama de Secuencia (Compra PD)

```
Usuario            JS (tienda_destino.js)      PHP (pd_purchase.php)         MySQL
   │                        │                          │                      │
   │  Clic "Adquirir"        │                          │                      │
   │────────────────────────>│                          │                      │
   │                        │  confirm(cost)            │                      │
   │                        │  POST pd_purchase.php     │                      │
   │                        │──────────────────────────>│                      │
   │                        │                          │  requireLogin()       │
   │                        │                          │  requirePost()        │
   │                        │                          │  requireCsrf()        │
   │                        │                          │  Validar parámetros   │
   │                        │                          │  Validar PJ activo    │
   │                        │                          │  PersonajeRepository  │
   │                        │                          │──────────────────────>│
   │                        │                          │<──────────────────────│
   │                        │                          │  Validar status=aprob │
   │                        │                          │  Verificar cost mapping│
   │                        │                          │  game_get_character_   │
   │                        │                          │  pd_available()        │
   │                        │                          │──────────────────────>│
   │                        │                          │<──────────────────────│
   │                        │                          │  if cost <= available  │
   │                        │                          │  game_register_pd_     │
   │                        │                          │  purchase()            │
   │                        │                          │  INSERT game_pd_       │
   │                        │                          │  purchases             │
   │                        │                          │──────────────────────>│
   │                        │                          │<──────────────────────│
   │                        │                          │  NotificationService  │
   │                        │  {ok, new_pd_available}   │                      │
   │                        │<──────────────────────────│                      │
   │                        │  alert("éxito")           │                      │
   │                        │  window.location.reload() │                      │
   │<────────────────────────│                          │                      │
```

### 1.4 Principios de Diseño

1. **PD es moneda de mérito, no económica:** No se compra con dinero real, no se transfiere entre personajes, no se obtiene por grind mecánico.
2. **Escasez deliberada:** Los PD son el recurso más escaso del foro. Su obtención está controlada por staff.
3. **Trazabilidad total:** Cada PD ganado y cada PD gastado queda registrado con timestamp.
4. **Sin deuda:** `pd_available` nunca es negativo. Si `total < spent`, se trunca a 0 (por seguridad).
5. **Gastos irrevocables por sistema:** Una compra de PD no se puede deshacer automáticamente. Solo staff puede revertirla (borrando el registro en `game_pd_purchases`).

---

## 2. Modelo de Datos

### 2.1 Columna en `game_personajes`

```sql
CREATE TABLE mybb_game_personajes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    -- ... otros campos ...
    berries         INT NOT NULL DEFAULT 0,
    puntos_destino  INT NOT NULL DEFAULT 0,
    -- ...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2 Características del Campo

| Propiedad | Valor |
|-----------|-------|
| Tipo | `INT NOT NULL DEFAULT 0` |
| Rango | -2³¹ a 2³¹-1 (~2.1B) |
| Default | 0 |
| Sin signo | No (INT normal) |
| Posición | Inmediatamente después de `berries` |

**Filosofía del `INT` sin signo:**
- El saldo total NUNCA se decrementa directamente. Siempre se suma cuando se otorgan PD.
- El gasto se registra en `game_pd_purchases`, no se resta del campo.
- Si hubiera un bug que intentara restar, el valor negativo serviría como alarma.

### 2.3 Relación con Otras Tablas

| Tabla | Relación | Propósito |
|-------|----------|-----------|
| `game_personajes` | `puntos_destino` = saldo total acumulado | Contador maestro de PD ganados |
| `game_pd_purchases` | `character_id` → `game_personajes.id` | Registro de gastos |
| `game_missions` | PD en `points_reward` | Definición de recompensa |
| `game_mission_participants` | `character_id` → entrega de PD | Registro de participantes que recibieron PD |

### 2.4 Impacto RPG

| Campo | Lo que permite en el juego |
|-------|---------------------------|
| `puntos_destino` | Premios especiales, contenido exclusivo, desbloqueos narrativos |
| `game_pd_purchases` | Historial de compras, auditoría, reversibilidad |

---

## 3. Tabla `game_pd_purchases`

### 3.1 Definición SQL

```sql
CREATE TABLE mybb_game_pd_purchases (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    character_id    INT NOT NULL,
    pd_cost         SMALLINT UNSIGNED NOT NULL,
    item_type       VARCHAR(64) NOT NULL,
    item_slug       VARCHAR(128) NOT NULL,
    item_name       VARCHAR(255) NOT NULL,
    purchased_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_character (character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Campos — Descripción Detallada

#### `id` — Identificador único
- Autoincremental. Clave primaria de la tabla.

#### `character_id` — Personaje que compra
- FK lógica a `game_personajes.id` (sin constraint formal).
- Todos los queries de compras filtran por este campo.

#### `pd_cost` — Coste en PD
- `SMALLINT UNSIGNED` — rango 0 a 65535. Suficiente para cualquier item del catálogo.
- Los costes actuales van de 2 a 5 PD.

#### `item_type` — Tipo de artículo
- Slug del tipo de premio. Valores actuales:
  - `estilo_secundario`, `estilo_terciario`, `tecnica_prohibida`, `habilidad_elemental`, `akuma_no_mi`, `barco_narrativo`, `poder_especial`

#### `item_slug` — Slug único del artículo
- Se genera en `pd_purchase.php` como: `strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $itemType . '_' . $itemName))`
- Sirve como identificador único para evitar duplicados conceptuales.

#### `item_name` — Nombre legible
- El nombre tal cual lo ve el usuario. Se guarda para mostrarlo en el historial.

#### `purchased_at` — Fecha de compra
- `TIMESTAMP DEFAULT CURRENT_TIMESTAMP`.

### 3.3 Filosofía de la Tabla

**¿Por qué una tabla separada en lugar de un JSON column en `game_personajes`?**
- Las compras de PD son un histórico que crece indefinidamente. Un JSON column sería inmanejable con 50+ compras.
- Las compras se consultan independientemente del personaje (historial, auditoría, reversiones).
- Permitiría en el futuro queries como "¿cuántos usuarios han comprado akuma_no_mi?" sin cargar todos los personajes.
- No hay necesidad de transaccionalidad entre la compra y el personaje: el registro de compra es un append-only log.

**¿Por qué `SMALLINT` para `pd_cost`?**
- Los costes individuales de items nunca superarán 65535 PD (el item más caro hoy cuesta 5 PD).
- Ahorra espacio frente a `INT` (2 bytes vs 4 bytes por fila).

**¿Por qué `item_slug` como campo separado de `item_type`?**
- `item_type` identifica la CATEGORÍA del premio (qué sistema desbloquea).
- `item_slug` identifica el artículo CONCRETO (para evitar compras duplicadas del mismo item).
- Ejemplo: `item_type = 'estilo_secundario'`, `item_slug = 'estilo_secundario_fishman_karate'`.

### 3.4 Consultas Típicas

```sql
-- PD total gastado por personaje
SELECT SUM(pd_cost) AS total_spent
FROM mybb_game_pd_purchases
WHERE character_id = {character_id};

-- Historial de compras (más recientes primero)
SELECT id, pd_cost, item_type, item_slug, item_name, purchased_at
FROM mybb_game_pd_purchases
WHERE character_id = {character_id}
ORDER BY purchased_at DESC;

-- Verificar si un personaje ya compró cierto tipo de item
SELECT COUNT(*) AS already_bought
FROM mybb_game_pd_purchases
WHERE character_id = {character_id}
  AND item_type = 'akuma_no_mi';

-- Todas las compras de un tipo en el foro (auditoría staff)
SELECT p.name, pp.item_name, pp.purchased_at
FROM mybb_game_pd_purchases pp
JOIN mybb_game_personajes p ON pp.character_id = p.id
WHERE pp.item_type = 'akuma_no_mi'
ORDER BY pp.purchased_at DESC;
```

---

## 4. Helpers PHP — Capa de Acceso a Datos

### 4.1 Archivo `game/inc/pd_helpers.php`

```php
function game_get_character_pd_total(int $characterId): int
{
    global $db;
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT puntos_destino
                     FROM {$prefix}game_personajes
                     WHERE id = {$characterId} LIMIT 1");
    $pj = $db->fetch_array($q);
    return $pj ? (int)$pj['puntos_destino'] : 0;
}

function game_get_character_pd_spent(int $characterId): int
{
    global $db;
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT SUM(pd_cost) AS total_spent
                     FROM {$prefix}game_pd_purchases
                     WHERE character_id = {$characterId}");
    $res = $db->fetch_array($q);
    return $res ? (int)$res['total_spent'] : 0;
}

function game_get_character_pd_available(int $characterId): int
{
    $total = game_get_character_pd_total($characterId);
    $spent = game_get_character_pd_spent($characterId);
    return max(0, $total - $spent);
}

function game_get_character_purchases(int $characterId): array
{
    global $db;
    $prefix = TABLE_PREFIX;
    $purchases = [];
    $q = $db->query("
        SELECT id, pd_cost, item_type, item_slug, item_name, purchased_at
        FROM {$prefix}game_pd_purchases
        WHERE character_id = {$characterId}
        ORDER BY purchased_at DESC
    ");
    while ($p = $db->fetch_array($q)) {
        $purchases[] = $p;
    }
    return $purchases;
}

function game_register_pd_purchase(
    int $characterId,
    int $cost,
    string $itemType,
    string $itemSlug,
    string $itemName
): bool
{
    global $db;
    $prefix = TABLE_PREFIX;
    $escType = $db->escape_string($itemType);
    $escSlug = $db->escape_string($itemSlug);
    $escName = $db->escape_string($itemName);

    return (bool)$db->write_query("
        INSERT INTO {$prefix}game_pd_purchases
            (character_id, pd_cost, item_type, item_slug, item_name, purchased_at)
        VALUES ({$characterId}, {$cost}, '{$escType}',
                '{$escSlug}', '{$escName}', NOW())
    ");
}
```

### 4.2 Filosofía de los Helpers

**Separación de responsabilidades:**
- `game_get_character_pd_total()`: Fuente de verdad del saldo acumulado (solo staff escribe aquí).
- `game_get_character_pd_spent()`: Gasto agregado desde la tabla de compras.
- `game_get_character_pd_available()`: Diferencia. Función compuesta que usan vistas y AJAX.
- `game_get_character_purchases()`: Historial completo para mostrar en UI.
- `game_register_pd_purchase()`: Único punto de escritura de gastos. INSERT-only.

**¿Por qué `max(0, $total - $spent)` en `pd_available`?**
- Protección contra inconsistencias. Si por un bug `spent > total`, el personaje no debería tener PD negativo disponible. Se trunca a 0.
- Esto permite que el staff pueda seguir viendo el problema sin que el personaje quede "en deuda".

---

## 5. Cómo se Ganan los PD

### 5.1 Fuentes de Obtención

| Fuente | Descripción | Quién otorga | Frecuencia |
|--------|-------------|-------------|------------|
| Misiones | Al completar una misión, se entregan PD según rango (D: 1, C: 2, B: 4, A: 7, S: 10–20) | Staff (al aprobar la misión) | Semanal/quincenal |
| Eventos especiales | Torneos, tramas globales, arcos narrativos con recompensas | Staff de eventos | Mensual/esporádico |
| Rol destacado | Posts de calidad excepcional, arcos narrativos sobresalientes | Staff (nominación) | Ad-hoc |
| Staff discrecional | Premios por veteranía, contribución a la comunidad, ayuda en el foro | Staff | Ad-hoc |
| Concursos | Mejor post del mes, mejor historia, diseño de personaje | Staff | Mensual |

### 5.2 PD por Misiones

Las misiones son la **fuente principal y más predecible** de PD. El sistema de misiones (guía 16) define:

```sql
-- En game_missions:
points_reward SMALLINT UNSIGNED NOT NULL DEFAULT 1  -- PD que otorga
```

| Rango de Misión | PD Recomendados | Esfuerzo Estimado |
|:---------------:|:---------------:|:-----------------:|
| D | 1 | 5–10 posts |
| C | 2 | 10–20 posts |
| B | 4 | 20–40 posts |
| A | 7 | 40–80 posts |
| S | 10–20 | 80+ posts |

La entrega ocurre en `AdminRequestService` cuando el staff aprueba una misión:

```php
// Por cada participante confirmado:
$db->write_query("
    UPDATE {$prefix}game_personajes
    SET puntos_destino = puntos_destino + {$points},
        berries = berries + {$berries}
    WHERE id = {$cId}
");
```

### 5.3 PD por Eventos Especiales

Los eventos globales (torneos, tramas de temporada, arcos de isla) pueden otorgar PD adicionales. A diferencia de las misiones, estos PD no pasan por el sistema de misiones — se asignan directamente por staff mediante el panel de administración.

**Filosofía:** Los eventos son la válvula de ajuste de la economía de PD. Si el staff detecta que los jugadores tienen pocos PD para la actividad del foro, puede inyectar PD mediante eventos sin necesidad de modificar las recompensas de misión.

### 5.4 PD por Rol Destacado (Outstanding Roleplay)

El staff puede nominar y premiar posts excepcionales con PD. Estos son los criterios:

- **Calidad narrativa:** Un post que eleva la trama, desarrolla profundamente al personaje, o introduce giros argumentales memorables.
- **Colaboración:** Un jugador que facilita el rol de otros, crea oportunidades narrativas para sus compañeros.
- **Consistencia:** Un personaje roleado consistentemente durante meses, con evolución y coherencia.
- **Dificultad:** Un personaje que rolea situaciones difíciles (pérdida, conflicto interno, decisiones moralmente complejas) con madurez.

**Filosofía del rol destacado:** El PD por rol destacado es deliberadamente subjetivo. No hay una métrica automática. Esto permite al staff premiar lo que el sistema no puede medir: calidad sobre cantidad.

### 5.5 Procedimiento de Concesión

Independientemente de la fuente, la concesión de PD sigue siempre el mismo patrón SQL:

```sql
UPDATE mybb_game_personajes
SET puntos_destino = puntos_destino + {amount}
WHERE id = {character_id};
```

**No hay método automático de concesión desde el frontend.** Solo el staff puede incrementar el saldo de PD, ya sea:
1. Aprobando una misión (automatizado en `AdminRequestService`)
2. Desde el panel staff (concesión directa)

Esto garantiza que ningún bug en el frontend pueda generar PD fraudulentamente.

---

## 6. Catálogo de Premios

### 6.1 Categorías desde el Maestro

El `MAESTRO_SISTEMAS_RPG.md` define 5 categorías de premios:

1. **Extras de personaje:** Slot adicional, cambio de raza, cambio de nombre.
2. **Cards especiales:** Cartas únicas no disponibles en tienda.
3. **Boosters de progresión:** PP extra, subida de rango acelerada.
4. **Contenido narrativo:** Misión privada, NPC aliado, ítem legendario.
5. **Cosméticos:** Banners, firmas especiales, insignias de foro.

### 6.2 Catálogo Implementado (Tienda de Destino)

Los items actualmente disponibles en `tienda_destino.php` son:

| Tipo | Nombre | Coste | Icono | Descripción |
|------|--------|:-----:|:-----:|-------------|
| `estilo_secundario` | Estilo de Pelea Secundario | **2 PD** | `fa-swords` | Desbloquea el acceso para entrenar y equipar un segundo estilo de combate complementario. |
| `estilo_terciario` | Estilo de Pelea Terciario | **4 PD** | `fa-shield-halved` | Permite al personaje dominar y utilizar un tercer estilo de combate simultáneo. |
| `tecnica_prohibida` | Técnica Prohibida | **3 PD** | `fa-scroll` | Habilita el aprendizaje de una técnica oculta o prohibida dentro de tu disciplina principal. |
| `habilidad_elemental` | Habilidad Elemental / Especial | **2 PD** | `fa-fire` | Desbloquea el uso de propiedades elementales o habilidades narrativas de combate únicas. |
| `akuma_no_mi` | Acceso a Fruta del Diablo | **5 PD** | `fa-apple-alt` | Otorga el permiso administrativo oficial para consumir una Akuma no Mi disponible. |
| `barco_narrativo` | Mejora de Barco Narrativo | **3 PD** | `fa-ship` | Añade slots, mejoras o refuerzos mecánicos y de diseño al barco de tu tripulación. |
| `poder_especial` | Poder Narrativo Especial | **4 PD** | `fa-magic` | Concede una ventaja o rasgo narrativo único en el mundo, sujeto a aprobación del staff. |

### 6.3 Mapa de Costes (Backend)

En `pd_purchase.php`, línea 42-50:

```php
$costs = [
    'estilo_secundario'  => 2,
    'estilo_terciario'   => 4,
    'tecnica_prohibida'  => 3,
    'habilidad_elemental'=> 2,
    'akuma_no_mi'        => 5,
    'barco_narrativo'    => 3,
    'poder_especial'     => 4,
];
```

### 6.4 Filosofía de los Costes

**¿Por qué 2–5 PD y no valores más altos?**
- Porque los PD son escasos. Una misión D da 1 PD. Un personaje activo puede ganar 3–6 PD al mes.
- Un item de 5 PD (Akuma no Mi) representa ~1 mes de juego activo. Suficientemente caro para ser significativo, suficientemente alcanzable para no frustrar.
- Los costes bajos (2 PD) permiten que los jugadores novatos puedan comprar algo tras su primera misión.

**¿Por qué estilos secundarios y terciarios tienen costes diferentes (2 vs 4)?**
- El primer estilo extra (secundario) es barato porque el jugador ya demostró compromiso con su personaje.
- El tercer estilo es el doble de caro porque tener 3 estilos de combate es un lujo mecánico y narrativo. No todos los personajes deberían tener acceso a 3 estilos.
- Escalabilidad: 1→2 cuesta 2 PD, 2→3 cuesta 4 PD. Si en el futuro se añadiera un cuarto estilo, costaría 8 PD (progresión geométrica).

**¿Por qué Akuma no Mi es el item más caro (5 PD)?**
- Una Akuma no Mi es el desbloqueo más impactante del juego. Cambia permanentemente al personaje, le da poderes únicos, y tiene implicaciones narrativas enormes.
- El coste alto también desincentiva compras impulsivas: "¿realmente quiero este poder para mi personaje?".
- Además del coste en PD, consumir una Akuma no Mi requiere pasar por el sistema de solicitudes al staff (aprobación del fruto específico).

### 6.5 Premios No Implementados en Tienda

Del maestro de sistemas, estas categorías existen conceptualmente pero no tienen implementación directa en la tienda:

| Categoría | Ejemplos | Implementación |
|-----------|----------|---------------|
| **Extras de personaje** | Slot adicional (+1 personaje slot), cambio de raza, cambio de nombre | Debe pasar por solicitud staff + modificación directa de DB |
| **Cards especiales** | Cartas custom fuera de catálogo | Sistema de solicitudes de cartas (`game_card_requests`) |
| **Boosters de progresión** | PP extra, subida de rango acelerada | Staff asigna PP manualmente |
| **Cosméticos** | Banners, firmas especiales, insignias | Aún no implementado |

**Filosofía de los premios no implementados:** La tienda de destino actual cubre items mecánicos concretos. Los premios narrativos (misión privada, NPC aliado, ítem legendario) se gestionan mediante solicitud directa al staff porque requieren contexto narrativo que una tienda automatizada no puede evaluar.

---

## 7. JSON Schema de Premios

### 7.1 Schema Genérico para un Item de PD

```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "title": "PD Prize Item",
    "description": "Schema para un artículo canjeable con Puntos Destino",
    "type": "object",
    "required": ["type", "cost", "name", "icon", "desc", "category"],
    "properties": {
        "type": {
            "type": "string",
            "description": "Identificador único del tipo de premio (slug)",
            "pattern": "^[a-z_]+$",
            "examples": ["estilo_secundario", "akuma_no_mi"]
        },
        "cost": {
            "type": "integer",
            "description": "Coste en PD",
            "minimum": 1,
            "maximum": 65535,
            "examples": [2, 3, 4, 5]
        },
        "name": {
            "type": "string",
            "description": "Nombre visible del premio",
            "maxLength": 255,
            "examples": ["Estilo de Pelea Secundario", "Acceso a Fruta del Diablo"]
        },
        "icon": {
            "type": "string",
            "description": "Clase CSS de FontAwesome para el icono",
            "pattern": "^fa-[a-z-]+$",
            "examples": ["fa-swords", "fa-apple-alt"]
        },
        "desc": {
            "type": "string",
            "description": "Descripción del premio (qué desbloquea)",
            "maxLength": 500
        },
        "category": {
            "type": "string",
            "enum": ["extras_pj", "cards_especiales", "boosters", "narrativo", "cosmeticos"],
            "description": "Categoría del premio según clasificación del maestro"
        },
        "requires_approval": {
            "type": "boolean",
            "description": "Si el premio requiere aprobación staff adicional tras la compra",
            "default": true
        },
        "max_per_character": {
            "type": "integer",
            "description": "Máximo de veces que se puede comprar este item por personaje",
            "minimum": 1,
            "default": 1
        },
        "flags": {
            "type": "array",
            "description": "Flags especiales del item",
            "items": {
                "type": "string",
                "enum": ["irreversible", "staff_grant_only", "hidden", "limited_time"]
            }
        }
    }
}
```

### 7.2 Ejemplo: Estilo Secundario

```json
{
    "type": "estilo_secundario",
    "cost": 2,
    "name": "Estilo de Pelea Secundario",
    "icon": "fa-swords",
    "desc": "Desbloquea el acceso para entrenar y equipar un segundo estilo de combate complementario.",
    "category": "extras_pj",
    "requires_approval": false,
    "max_per_character": 1,
    "flags": []
}
```

### 7.3 Ejemplo: Akuma no Mi

```json
{
    "type": "akuma_no_mi",
    "cost": 5,
    "name": "Acceso a Fruta del Diablo (Akuma no Mi)",
    "icon": "fa-apple-alt",
    "desc": "Otorga el permiso administrativo oficial para consumir una Akuma no Mi disponible.",
    "category": "extras_pj",
    "requires_approval": true,
    "max_per_character": 1,
    "flags": ["irreversible"]
}
```

### 7.4 Ejemplo: Poder Narrativo Especial

```json
{
    "type": "poder_especial",
    "cost": 4,
    "name": "Poder Narrativo Especial",
    "icon": "fa-magic",
    "desc": "Concede una ventaja o rasgo narrativo único en el mundo, sujeto a aprobación del staff.",
    "category": "narrativo",
    "requires_approval": true,
    "max_per_character": 3,
    "flags": ["staff_grant_only"]
}
```

### 7.5 Schema de Respuesta de Compra

```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "title": "PD Purchase Response",
    "description": "Respuesta del endpoint pd_purchase.php",
    "type": "object",
    "required": ["ok"],
    "properties": {
        "ok": {
            "type": "boolean",
            "description": "Resultado de la operación"
        },
        "data": {
            "type": "object",
            "description": "Datos de la compra (solo si ok=true)",
            "properties": {
                "character_id": { "type": "integer" },
                "item_type": { "type": "string" },
                "item_name": { "type": "string" },
                "pd_spent": { "type": "integer" },
                "new_pd_available": { "type": "integer" }
            }
        },
        "error": {
            "type": "object",
            "description": "Detalle del error (solo si ok=false)",
            "properties": {
                "code": { "type": "integer" },
                "message": { "type": "string" }
            }
        }
    }
}
```

### 7.6 Ejemplo de Respuesta Exitosa

```json
{
    "ok": true,
    "data": {
        "character_id": 42,
        "item_type": "estilo_secundario",
        "item_name": "Estilo de Pelea Secundario",
        "pd_spent": 2,
        "new_pd_available": 3
    }
}
```

### 7.7 Ejemplo de Respuesta Fallida

```json
{
    "ok": false,
    "error": {
        "code": 400,
        "message": "Puntos Destino (PD) insuficientes (tienes 1 PD, necesitas 5 PD)."
    }
}
```

---

## 8. Flujo de Compra

### 8.1 Endpoint `pd_purchase.php`

Archivo: `back/forum/game/ajax/pd_purchase.php` (93 líneas)

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\PersonajeRepository;

header('Content-Type: application/json; charset=utf-8');

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$characterId = (int)($input['character_id'] ?? 0);
$itemType = trim((string)($input['item_type'] ?? ''));
$itemName = trim((string)($input['item_name'] ?? ''));

if ($characterId <= 0 || $itemType === '' || $itemName === '') {
    GameAjax::json(false, null,
        ['code' => 400, 'message' => 'Parámetros inválidos.'], 400);
}

if (game_get_active_pj_id($uid) !== $characterId) {
    GameAjax::json(false, null,
        ['code' => 403, 'message' => 'Debes usar tu personaje activo.'], 403);
}

$personajes = new PersonajeRepository();
$character = $personajes->findByIdForUser($characterId, $uid);

if ($character === null) {
    GameAjax::json(false, null,
        ['code' => 404, 'message' => 'Personaje no encontrado.'], 404);
}

if (($character['status'] ?? '') !== 'aprobada') {
    GameAjax::json(false, null,
        ['code' => 403, 'message' => 'El personaje debe estar aprobado para realizar compras.'], 403);
}

// Cost mapping
$costs = [
    'estilo_secundario'  => 2,
    'estilo_terciario'   => 4,
    'tecnica_prohibida'  => 3,
    'habilidad_elemental'=> 2,
    'akuma_no_mi'        => 5,
    'barco_narrativo'    => 3,
    'poder_especial'     => 4,
];

if (!array_key_exists($itemType, $costs)) {
    GameAjax::json(false, null,
        ['code' => 400, 'message' => 'Tipo de artículo no válido.'], 400);
}

$cost = $costs[$itemType];
$availablePd = game_get_character_pd_available($characterId);

if ($availablePd < $cost) {
    GameAjax::json(false, null,
        ['code' => 400,
         'message' => 'Puntos Destino (PD) insuficientes (tienes '
                      . $availablePd . ' PD, necesitas ' . $cost . ' PD).'], 400);
}

$itemSlug = strtolower(
    preg_replace('/[^a-zA-Z0-9]+/', '_', $itemType . '_' . $itemName)
);

// Register purchase
if (game_register_pd_purchase(
    $characterId, $cost, $itemType, $itemSlug, $itemName
)) {
    $newAvailable = game_get_character_pd_available($characterId);

    // Send system notification
    try {
        $notifService = new \Game\Application\Services\NotificationService();
        $notifService->create(
            $uid,
            'system',
            "Compra PD Confirmada",
            "Has desbloqueado '{$itemName}' gastando {$cost} PD.",
            "game/public/personaje.php?pj={$characterId}",
            $characterId
        );
    } catch (\Throwable $e) {
        // Ignore
    }

    GameAjax::json(true, [
        'character_id' => $characterId,
        'item_type' => $itemType,
        'item_name' => $itemName,
        'pd_spent' => $cost,
        'new_pd_available' => $newAvailable,
    ], null);
} else {
    GameAjax::json(false, null,
        ['code' => 500,
         'message' => 'Error al guardar la compra en la base de datos.'], 500);
}
```

### 8.2 Validaciones — Paso a Paso

| # | Validación | Código | ¿Por qué? |
|---|-----------|--------|-----------|
| 1 | Usuario autenticado | `requireLogin()` | Solo usuarios logueados pueden comprar |
| 2 | Método POST | `requirePost()` | Las compras son mutaciones, no idempotentes |
| 3 | JSON válido + CSRF | `postJson()` + `requireCsrf()` | Protección contra CSRF y manipulación |
| 4 | Parámetros completos | `character_id > 0 && itemType !== '' && itemName !== ''` | Evita compras malformadas |
| 5 | PJ activo | `game_get_active_pj_id($uid) !== $characterId` | Solo puedes comprar para tu PJ activo |
| 6 | Personaje existe | `findByIdForUser()` | El PJ debe pertenecerte |
| 7 | Personaje aprobado | `status === 'aprobada'` | No puedes gastar PD en un PJ pendiente/rechazado |
| 8 | Tipo de item válido | `array_key_exists($itemType, $costs)` | El item debe estar en el catálogo |
| 9 | PD suficientes | `$availablePd >= $cost` | No puedes gastar lo que no tienes |
| 10 | INSERT exitoso | `game_register_pd_purchase()` | La DB debe aceptar el registro |

### 8.3 Generación del `itemSlug`

```php
$itemSlug = strtolower(
    preg_replace('/[^a-zA-Z0-9]+/', '_', $itemType . '_' . $itemName)
);
```

**Ejemplos:**
- `estilo_secundario_Estilo de Pelea Secundario` → `estilo_secundario_estilo_de_pelea_secundario`
- `akuma_no_mi_Acceso a Fruta del Diablo` → `akuma_no_mi_acceso_a_fruta_del_diablo`

**Propósito:** El slug permite identificar unívocamente cada compra. Sirve para:
- Evitar duplicados: si el personaje ya compró exactamente ese item, el slug es el mismo.
- Auditoría: el staff puede buscar por slug en la DB.
- Futuras features: "reclamar" un item comprado mediante su slug.

### 8.4 Notificación Post-Compra

Tras una compra exitosa, se envía una notificación de sistema:

```php
$notifService = new \Game\Application\Services\NotificationService();
$notifService->create(
    $uid,
    'system',
    "Compra PD Confirmada",
    "Has desbloqueado '{$itemName}' gastando {$cost} PD.",
    "game/public/personaje.php?pj={$characterId}",
    $characterId
);
```

La notificación incluye:
- Tipo: `system` (notificación automática del sistema)
- Título: "Compra PD Confirmada"
- Cuerpo: "Has desbloqueado 'X' gastando Y PD."
- Link: Ficha del personaje (para que el jugador vea su nuevo desbloqueo)
- `character_id`: Asociada al personaje que compró

Si la notificación falla (ej: servicio de notificaciones caído), la compra NO se revierte. Es un error silencioso (try/catch vacío).

**Filosofía de la notificación post-compra:** El jugador recibe confirmación inmediata de que su compra fue procesada. La notificación es el "ticket" de compra en el sistema. Si en el futuro hay disputas ("compré X pero no lo recibí"), la notificación sirve como evidencia.

### 8.5 Seguridad

| Aspecto | Cómo se protege |
|---------|----------------|
| Autenticación | `requireLogin()` — solo usuarios registrados |
| Autorización | `findByIdForUser()` — el PJ debe pertenecer al usuario |
| CSRF | `requireCsrf()` — token de MyBB verificado |
| Método HTTP | `requirePost()` — evita CSRF vía GET |
| Inyección SQL | `$db->escape_string()` en item_type, item_slug, item_name |
| Precio hardcodeado | `$costs` array — el coste está en servidor, no en el JS |
| Validación del tipo | `array_key_exists()` — solo tipos conocidos |
| PJ activo | `game_get_active_pj_id()` — solo el PJ activo puede comprar |

---

## 9. Frontend

### 9.1 Tienda de Destino (`tienda_destino.php`)

Archivo: `back/forum/game/public/tienda_destino.php` (161 líneas)

**Ruta:** `game/public/tienda_destino.php`
**Entrada:** Panel de trámites → Tienda de Destino

**Estructura de la página:**
1. Header con navegación "Volver a Trámites"
2. Título + descripción del sistema de PD
3. Verificación de PJ activo (si no hay, muestra panel bloqueado)
4. Panel de saldo de PD: "X / Y PD Disponibles"
5. Grid de items del catálogo (tarjetas con icono, nombre, descripción, precio, botón)

**Carga de datos:**
```php
$pdTotal = game_get_character_pd_total($activePjId);
$pdSpent = game_get_character_pd_spent($activePjId);
$pdAvailable = game_get_character_pd_available($activePjId);
```

**Grid de items:**
```php
$destinyItems = [
    ['type' => 'estilo_secundario', 'cost' => 2,  'name' => 'Estilo de Pelea Secundario', ...],
    ['type' => 'estilo_terciario',  'cost' => 4,  'name' => 'Estilo de Pelea Terciario', ...],
    // ... 7 items
];
```

Cada item se renderiza como una tarjeta (`.rpg-shop-card`) con:
- Icono FontAwesome
- Nombre del item
- Descripción
- Precio en PD
- Botón "Adquirir" (si hay PD suficientes) o "Bloqueado" (si no)

### 9.2 JavaScript (`tienda_destino.js`)

Archivo: `back/forum/jscripts/game/tienda_destino.js` (60 líneas)

**Config:**
```javascript
window.TIENDA_DESTINO_CONFIG = {
    bburl: '...',
    characterId: 42
};
```

**Función principal: `buyPdItem(itemType, cost, itemName)`:**
1. Confirmación con `confirm()`: "¿Confirmas que deseas gastar X PD en 'Y'?"
2. Deshabilitar todos los botones de compra (prevenir doble clic)
3. POST a `pd_purchase.php` con `{character_id, item_type, item_name}`
4. Si éxito (`res.ok === true`): alert y recargar página
5. Si error: mostrar mensaje y re-habilitar botones

```javascript
function buyPdItem(itemType, cost, itemName) {
    if (!itemType || !itemName) return;
    if (!confirm("¿Confirmas que deseas gastar " + cost + " PD en: '" + itemName + "'?")) return;

    var buttons = document.querySelectorAll(".btn-buy-pd-item");
    buttons.forEach(function(b) { b.disabled = true; });

    gameFetchPost("pd_purchase.php", {
        character_id: characterId,
        item_type: itemType,
        item_name: itemName
    }).then(function (res) {
        if (res.ok) {
            alert("¡Compra realizada con éxito!");
            window.location.reload();
        } else {
            alert("Error: " + (res.error ? res.error.message : "Desconocido"));
            buttons.forEach(function(b) { b.disabled = false; });
        }
    }).catch(function () {
        alert("Error de conexión al procesar la compra.");
        buttons.forEach(function(b) { b.disabled = false; });
    });
}
```

**Filosofía del frontend:**
- **Recarga completa tras compra:** El saldo de PD se actualiza en el servidor. Recargar la página garantiza que el UI refleje el nuevo saldo sin lógica de estado compartida entre JS y PHP.
- **Confirmación previa:** Evita compras accidentales (especialmente importante con items de 5 PD que representan semanas de juego).
- **Deshabilitar botones durante la compra:** Previene que el usuario haga clic múltiples veces y genere múltiples registros de compra antes de que la primera respuesta llegue.
- **Sin animación ni feedback visual avanzado:** Es una tienda funcional. Lo importante es que la transacción sea correcta, no que tenga micro-interacciones elaboradas.

### 9.3 Dashboard de Gestión (PD en Ficha de Personaje)

En el panel de gestión del personaje (`_tab_gestion.php`), el PD disponible se muestra junto a los PP:

```html
<div class="rpg-pp-val rpg-pp-val--pd">
    <i class="fas fa-star"></i>
    <span id="val_available_pd">
        <?= game_get_character_pd_available($char['id']) ?>
    </span> PD
</div>
```

**Filosofía de la doble ubicación (ficha + tienda):**
- En la ficha, el PD es un número más en el dashboard de gestión: "tienes X PD".
- En la tienda, el PD tiene contexto: "tienes X PD, y estos son los items que puedes comprar".
- La ficha no tiene botón de compra directo porque la tienda es el lugar centralizado para todas las compras. Centralizar evita dispersión de UI y lógica duplicada.

---

## 10. Historial de Compras

### 10.1 Endpoint `pd_history.php`

Archivo: `back/forum/game/ajax/pd_history.php` (39 líneas)

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\PersonajeRepository;

header('Content-Type: application/json; charset=utf-8');

global $db;

$uid = GameAjax::requireLogin();
$charId = (int)($_GET['character_id'] ?? 0);

if ($charId <= 0) {
    GameAjax::fail(400, 'character_id inválido');
}

$personajes = new PersonajeRepository();
$character = $personajes->findByIdForUser($charId, $uid);

// Or if they are staff
if ($character === null && game_get_active_staff_level($uid) < 2) {
    GameAjax::fail(403, 'Sin permiso');
}

$totalPd = game_get_character_pd_total($charId);
$spentPd = game_get_character_pd_spent($charId);
$availablePd = game_get_character_pd_available($charId);
$purchases = game_get_character_purchases($charId);

GameAjax::json(true, [
    'character_id' => $charId,
    'total_pd' => $totalPd,
    'spent_pd' => $spentPd,
    'available_pd' => $availablePd,
    'purchases' => $purchases,
]);
```

### 10.2 Respuesta JSON

```json
{
    "ok": true,
    "data": {
        "character_id": 42,
        "total_pd": 10,
        "spent_pd": 4,
        "available_pd": 6,
        "purchases": [
            {
                "id": 1,
                "pd_cost": 2,
                "item_type": "estilo_secundario",
                "item_slug": "estilo_secundario_estilo_de_pelea_secundario",
                "item_name": "Estilo de Pelea Secundario",
                "purchased_at": "2026-05-15 14:30:00"
            },
            {
                "id": 2,
                "pd_cost": 2,
                "item_type": "habilidad_elemental",
                "item_slug": "habilidad_elemental_maniabilidad_de_hielo",
                "item_name": "Maniabilidad de Hielo",
                "purchased_at": "2026-06-01 09:15:00"
            }
        ]
    }
}
```

### 10.3 Permisos del Historial

| Usuario | ¿Puede ver historial? |
|---------|----------------------|
| Dueño del personaje | Sí (siempre) |
| Staff nivel 2+ | Sí (cualquier personaje) |
| Otros usuarios | No (403) |

**Filosofía:** El historial de gastos es información sensible. Muestra qué poderes o desbloqueos tiene un personaje. Solo el dueño y el staff deberían verlo. No es información pública como los stats base.

---

## 11. Concesión de PD por Staff

### 11.1 Desde el Panel de Administración

El staff puede conceder PD mediante `AdminRequestService` cuando:
1. Aprueba una misión (automático, con reparto entre participantes).
2. Asigna PD directamente (concesión manual).

### 11.2 Concesión por Misión (Automático)

Cuando el staff aprueba una misión `completed`:

```php
// Por cada participante confirmado:
$db->write_query("
    UPDATE {$prefix}game_personajes
    SET puntos_destino = puntos_destino + {$points},
        berries = berries + {$berries}
    WHERE id = {$cId}
");
```

**Para líder vs acompañantes:**
- El líder de misión recibe el PD completo definido en la misión.
- Los acompañantes reciben `floor($pdReward / count($participants))`.
- Esto incentiva liderar misiones (más PD) pero también permite a jugadores menos activos ganar PD como acompañantes.

```php
$perParticipantPD = floor($pdReward / count($participants));
```

### 11.3 Concesión Manual (Directa)

Para eventos, rol destacado, o compensaciones, el staff puede ejecutar directamente:

```sql
UPDATE mybb_game_personajes
SET puntos_destino = puntos_destino + {amount}
WHERE id = {character_id};
```

**Filosofía de la concesión manual:** No hay un endpoint AJAX para que el staff conceda PD desde el frontend. Esto es intencional: la concesión de PD debe ser una acción deliberada, con intención clara, y preferiblemente documentada (en un post de staff, en el hilo de la misión, o en el panel de administración).

---

## 12. Flujo de Datos Completo

### 12.1 Compra de PD

```
JS (tienda_destino.js)
  → POST /game/ajax/pd_purchase.php
    → GameAjax::requireLogin()
    → GameAjax::requirePost()
    → GameAjax::postJson()
    → GameAjax::requireCsrf($input)
    → Validar character_id, item_type, item_name
    → game_get_active_pj_id($uid) === $characterId
    → PersonajeRepository::findByIdForUser()
    → Verificar status === 'aprobada'
    → Validar itemType in $costs mapping
    → game_get_character_pd_available($characterId) >= $cost
    → Generar itemSlug
    → game_register_pd_purchase() → INSERT game_pd_purchases
    → NotificationService::create() (sistema)
    → Response {ok: true, data: {character_id, item_type, item_name, pd_spent, new_pd_available}}
```

### 12.2 Concesión por Misión

```
Staff apruea misión en panel de administración
  → AdminRequestService::processMissionApproval()
    → Obtener mission_id y pdReward
    → Consultar participantes confirmados
    → Para cada participante:
      → UPDATE game_personajes SET puntos_destino = puntos_destino + pdReward
      → (Para acompañantes: pdReward = floor(pdTotal / count))
    → Enviar notificaciones individuales
    → UPDATE game_missions_active.status = 'completed'
```

### 12.3 Concesión Manual (Staff)

```
Staff ejecuta SQL directo o comando admin:
  → UPDATE game_personajes SET puntos_destino = puntos_destino + {amount}
  → Opcional: registrar en log de auditoría (game_pd_purchases con pd_cost negativo)
```

---

## 13. Filosofía de Diseño

### 13.1 Principios Rectores

1. **PD es moneda de mérito, no de poder:** Representa el reconocimiento de la comunidad a la calidad del rol. No se puede comprar con berries ni con dinero real.

2. **Escasez deliberada y controlada:** Los PD son el recurso más escaso porque son los que más impacto tienen en el personaje. Si fueran abundantes, ningún desbloqueo sería especial.

3. **El staff controla la emisión:** Ningún mecanismo automático genera PD. Solo el staff, aprobando misiones o reconociendo rol destacado, puede incrementar el saldo.

4. **Los premios son desbloqueos, no consumibles:** Comprar un item con PD no te da un objeto que se gasta. Te da un PERMISO. El estilo secundario, la Akuma no Mi, el poder especial... son desbloqueos permanentes.

5. **Trazabilidad y auditabilidad:** Cada PD ganado y cada PD gastado se registra. El sistema permite reconstruir el historial completo de cualquier personaje.

### 13.2 Decisiones Clave y su Porqué

| Decisión | Alternativa descartada | Por qué se eligió así |
|----------|----------------------|----------------------|
| `puntos_destino` como columna separada en `game_personajes` | Tabla separada de transacciones con saldo calculado | Rendimiento: el saldo se consulta constantemente en vistas. Una query directa es más rápida que un JOIN+SUM. |
| `game_pd_purchases` como registro de gastos | Restar directamente del saldo | Trazabilidad: puedes ver todas las compras pasadas sin depender de logs. Reversibilidad: borrar un registro de compra es más seguro que sumar de vuelta al saldo. |
| Costes hardcodeados en PHP | Costes en DB (tabla configurable) | Los costes cambian raramente. Hardcodearlos evita una query extra y posibles inconsistencias entre DB y UI. |
| Confirmación JS antes de comprar | Compra directa sin confirmación | Prevención de accidentes. Un clic equivocado puede costar 5 PD (semanas de juego). |
| Sin API de concesión de PD para staff | Endpoint AJAX para staff | La concesión de PD debe ser deliberada y documentada. Un endpoint facilitaría la concesión impulsiva. |

### 13.3 Filosofía de la Economía de PD

**¿Por qué los PD no se transfieren entre personajes?**
- Los PD representan el MÉRITO de un personaje específico. No puedes transferir el mérito de un personaje a otro.
- Si los PD fueran transferibles, los jugadores podrían acumular PD en un personaje "grinder" y transferirlos a su personaje principal, rompiendo la economía.
- Cada personaje construye su propio historial de méritos.

**¿Por qué los items de PD son mayoritariamente desbloqueos narrativos/mecánicos y no cosméticos?**
- La tienda actual prioriza items que CAMBIAN cómo se juega (nuevos estilos, poderes) sobre items que CAMBIAN cómo se ve (cosméticos).
- Los cosméticos están planificados (banners, insignias, firmas) pero no implementados. Se añadirán cuando la economía de PD esté madura.
- Los desbloqueos narrativos son más valiosos para el juego que los cosméticos.

**¿Qué pasa si un jugador acumula PD sin gastarlos?**
- No hay límite de PD acumulables. Un jugador puede ahorrar durante meses para comprar el item más caro.
- El PD no caduca. No hay inflación ni devaluación forzada.
- Esto permite estrategias: "¿compro algo pequeño ahora o ahorro para una Akuma no Mi?"

### 13.4 Comparativa: PD vs Berries vs PP

| Característica | PD (Puntos Destino) | Berries | PP (Puntos de Progreso) |
|----------------|:-------------------:|:-------:|:-----------------------:|
| Naturaleza | Mérito | Económica | Progresión |
| Cómo se obtiene | Misiones, eventos, rol destacado | Misiones, comercio, staff | Posts (palabras escritas) |
| Quién controla | Staff | Staff + jugadores (comercio) | Automático (por post) |
| Escasez | Muy escaso | Moderado | Abundante |
| Transferible | No | Sí (entre jugadores) | No |
| Caducidad | No | No | No |
| Uso principal | Desbloqueos especiales | Compra de cartas | Subir stats |
| Impacto en juego | Alto (permisos) | Medio (items) | Bajo (incremental) |

---

## 14. Impacto RPG

### 14.1 En la Experiencia de Juego

| Aspecto | Efecto en la comunidad |
|---------|----------------------|
| PD como moneda de mérito | Los jugadores valoran la calidad del rol sobre la cantidad de posts |
| Misiones como fuente principal | Las misiones son relevantes incluso para personajes de alto nivel |
| Items de desbloqueo | Los personajes evolucionan con el tiempo, adquiriendo nuevas capacidades narrativas |
| Escasez controlada | Cada compra es significativa. No hay "grindeo" de PD |

### 14.2 En la Economía del Foro

| Decisión | Impacto RPG |
|----------|-------------|
| PD no transferibles | Cada personaje es independiente. No puedes "comprar" un personaje poderoso |
| Items = permisos | Comprar un item no te da el poder automáticamente; te da el DERECHO a adquirirlo mediante juego |
| Costes fijos | La economía de PD es predecible. Sabes exactamente cuánto necesitas ahorrar |

### 14.3 En el Comportamiento del Jugador

| Estímulo | Reacción esperada |
|----------|------------------|
| PD por misiones completadas | Los jugadores se inscriben y completan misiones |
| PD por rol destacado | Los jugadores se esfuerzan en la calidad narrativa |
| Items caros (Akuma no Mi = 5 PD) | Los jugadores planifican sus compras a largo plazo |
| Items baratos (estilo secundario = 2 PD) | Los jugadores nuevos pueden comprar algo rápidamente |

---

## 15. Consejos para Jugadores

### 15.1 Gestionando tus PD

**Los PD son el recurso más valioso de tu personaje.** No los gastes impulsivamente.

- **Prioriza items que cambien tu forma de rolear.** Un estilo secundario abre nuevas posibilidades narrativas. Un cosmético solo cambia tu apariencia.
- **Calcula tu tasa de ganancia.** Si en promedio ganas 3 PD al mes (1 misión D + 1 evento pequeño), ahorrar 5 PD para una Akuma no Mi te tomará ~2 meses. ¿Estás dispuesto a esperar?
- **No acumules PD sin propósito.** Tener PD guardados no te da ningún beneficio. Si no ves un item que quieras ahora, espera a que añadan nuevos.
- **El Poder Narrativo Especial (4 PD) es el más flexible.** Si no estás seguro de qué comprar, el poder especial te permite proponer algo único para tu personaje.

### 15.2 Planificación de Gastos

| Escenario | Qué comprar | Por qué |
|-----------|-------------|---------|
| Personaje nuevo, recién aprobado | Estilo Secundario (2 PD) | Lo más barato y útil. Te da un estilo extra desde el principio. |
| Personaje de combate | Técnica Prohibida (3 PD) | Una técnica única dentro de tu disciplina te diferencia. |
| Personaje con ambiciones de poder | Akuma no Mi (5 PD) | El desbloqueo más impactante. Planea con el staff qué fruta quieres. |
| Personaje de tripulación | Mejora de Barco (3 PD) | Beneficia a toda tu tripulación, no solo a ti. |
| Personaje veterano | Poder Especial (4 PD) | Cuando ya tienes lo básico, crea algo realmente único. |

### 15.3 Maximizando la Ganancia de PD

- **Completa misiones en lugar de rolear suelto.** Las misiones tienen recompensa garantizada de PD. El rol libre no.
- **Forma tripulación para misiones de grupo.** Las misiones de rango alto (A, S) requieren grupo y dan más PD per cápita.
- **Sé líder de misión.** El líder recibe el PD completo; los acompañantes reciben una fracción.
- **Participa en eventos globales.** Los eventos suelen tener recompensas extra de PD.
- **Rolea con calidad, no solo con cantidad.** El staff puede premiar posts excepcionales con PD adicionales.
- **No tengas miedo de nominarte.** Si crees que hiciste un post excepcional, puedes mencionarlo al staff. No es grosero; es autogestión.

---

## 16. Consejos para Staff

### 16.1 Gestión de la Economía de PD

- **Monitorea la tasa de concesión.** Si los jugadores acumulan PD más rápido de lo que hay items interesantes, la economía se estanca. Ajusta las recompensas de misión o añade nuevos items.
- **Sé consistente con las recompensas.** Una misión D siempre debería dar ~1 PD. Una misión A siempre ~7 PD. Si empiezas a dar 5 PD por misiones D, los jugadores perderán interés en las misiones altas.
- **Premia el rol destacado con moderación.** 1–3 PD por un post excepcional es suficiente. Si das PD por cada post bueno, dejarán de ser especiales.
- **Documenta las concesiones manuales.** Si das PD por evento, deja constancia en el hilo del evento o en el panel de administración.

### 16.2 Aprobación de Compras

Aunque la tienda de destino es automática, algunos items requieren seguimiento staff:

- **Akuma no Mi (5 PD):** El jugador pagó por el DERECHO a tener una fruta. El staff debe coordinar qué fruta específica recibe y cómo se desarrolla narrativamente.
- **Poder Narrativo Especial (4 PD):** El jugador pagó por un desbloqueo narrativo único. El staff debe trabajar con el jugador para definir qué es exactamente ese poder.
- **Barco Narrativo (3 PD):** La mejora de barco debe reflejarse en la ficha de la tripulación. El staff debe asegurarse de que se aplica correctamente.

**Para estos items, la compra en la tienda es solo el primer paso.** El staff debe contactar al jugador tras la compra para concretar los detalles.

### 16.3 Reversión de Compras

Si es necesario revertir una compra (error del sistema, reclamo del jugador):

```sql
-- 1. Identificar la compra
SELECT * FROM mybb_game_pd_purchases
WHERE character_id = {character_id}
ORDER BY purchased_at DESC;

-- 2. Eliminar el registro (solo staff)
DELETE FROM mybb_game_pd_purchases
WHERE id = {purchase_id}
  AND character_id = {character_id};

-- 3. Verificar que el saldo disponible se actualizó
SELECT game_get_character_pd_available({character_id});
```

**Importante:** No modifiques el campo `puntos_destino` directamente para revertir compras. Siempre trabaja sobre `game_pd_purchases`. El saldo total (`puntos_destino`) solo debe incrementarse.

### 16.4 Añadir Nuevos Items a la Tienda

Para añadir un nuevo item al catálogo de PD:

1. **Añadir al array `$destinyItems` en `tienda_destino.php`:**
```php
[
    'type' => 'nuevo_tipo',
    'cost' => 3,
    'name' => 'Nombre del Nuevo Item',
    'icon' => 'fa-icono',
    'desc' => 'Descripción del nuevo desbloqueo.'
]
```

2. **Añadir al `$costs` mapping en `pd_purchase.php`:**
```php
$costs = [
    // ... existentes ...
    'nuevo_tipo' => 3,
];
```

3. **Documentar el item** en esta guía y en el maestro de sistemas.

---

## 17. Guía de Troubleshooting

### 17.1 Problemas Comunes

| Síntoma | Causa | Solución |
|---------|-------|----------|
| El botón "Adquirir" no aparece | PJ no activo o no aprobado | Selecciona PJ activo en Mis Personajes |
| "PD insuficientes" | Saldo menor al coste | Completa más misiones o espera eventos |
| "Tipo de artículo no válido" | `itemType` no reconocido | Verificar que el item está en el catálogo |
| Error 403 "Debes usar tu personaje activo" | `character_id` no coincide con `active_pj_id` | Cambiar PJ activo en el panel |
| Error 500 "Error al guardar la compra" | Falla INSERT en `game_pd_purchases` | Verificar que la tabla existe y hay permisos de escritura |
| La compra se registró pero no aparece el desbloqueo | El item requiere aprobación staff adicional | Contactar al staff para concretar el desbloqueo |
| PD disponibles no coinciden con lo esperado | Inconsistencia entre `puntos_destino` y `game_pd_purchases` | Recalcular: `game_get_character_pd_total() - game_get_character_pd_spent()` |

### 17.2 Consultas de Diagnóstico

```sql
-- Verificar saldo total
SELECT id, name, puntos_destino
FROM mybb_game_personajes
WHERE id = {character_id};

-- Verificar gastos totales
SELECT SUM(pd_cost) AS total_spent
FROM mybb_game_pd_purchases
WHERE character_id = {character_id};

-- Verificar cada compra individual
SELECT id, pd_cost, item_type, item_name, purchased_at
FROM mybb_game_pd_purchases
WHERE character_id = {character_id}
ORDER BY purchased_at DESC;

-- Buscar compras duplicadas del mismo item
SELECT item_type, item_slug, COUNT(*) AS veces
FROM mybb_game_pd_purchases
WHERE character_id = {character_id}
GROUP BY item_type, item_slug
HAVING COUNT(*) > 1;
```

### 17.3 Recalcular Saldo Disponible

Si el saldo disponible parece incorrecto:

```sql
-- Calcular manualmente
SELECT
    p.id,
    p.name,
    p.puntos_destino AS total_pd,
    COALESCE(SUM(pp.pd_cost), 0) AS spent_pd,
    (p.puntos_destino - COALESCE(SUM(pp.pd_cost), 0)) AS calculated_available
FROM mybb_game_personajes p
LEFT JOIN mybb_game_pd_purchases pp ON p.id = pp.character_id
WHERE p.id = {character_id}
GROUP BY p.id;
```

### 17.4 La Función `game_get_character_pd_available()` No Funciona

Verificar que:
1. El archivo `pd_helpers.php` está incluido (vía `bootstrap.php`).
2. La función `game_get_character_pd_total()` funciona (query a `game_personajes.puntos_destino`).
3. La función `game_get_character_pd_spent()` funciona (query a `game_pd_purchases`).
4. La tabla `game_pd_purchases` existe y tiene datos.

### 17.5 Migración: Columna `puntos_destino` No Existe

Si la columna falta (ej: instalación antigua sin migrar):

```sql
ALTER TABLE mybb_game_personajes
ADD COLUMN puntos_destino INT NOT NULL DEFAULT 0
AFTER berries;
```

Esto está cubierto por el script `migrate_missions_system.php` que la añade automáticamente si no existe.

---

## 18. Referencias

### 18.1 Archivos del Sistema

| Archivo | Propósito |
|---------|-----------|
| `back/forum/game/inc/pd_helpers.php` | Funciones helper de PD (62 líneas) |
| `back/forum/game/ajax/pd_purchase.php` | Endpoint de compra (93 líneas) |
| `back/forum/game/ajax/pd_history.php` | Endpoint de historial (39 líneas) |
| `back/forum/game/public/tienda_destino.php` | Página de tienda (161 líneas) |
| `back/forum/jscripts/game/tienda_destino.js` | JS de tienda (60 líneas) |
| `back/forum/game/views/personaje/_tab_gestion.php` | Dashboard de gestión (línea 24: display PD) |
| `back/forum/game/src/Application/Services/AdminRequestService.php` | Concesión de PD por misión (línea 252) |
| `back/forum/game/sql/migrate_missions_system.php` | Migración: columna + tabla (líneas 12-98) |
| `back/forum/game/inc/plugins/game_postcharacter.php` | Plugin: otorga PD por posts (hook post insert) |
| `Guias/MAESTRO_SISTEMAS_RPG.md` | Sección 21: definición conceptual |
| `Guias/sistemas/16-misiones.md` | Sistema de misiones (fuente principal de PD) |
| `Guias/sistemas/20-economia.md` | Sistema económico (berries, relación con PD) |
| `Guias/sistemas/01-personaje.md` | Ficha de personaje (columna puntos_destino) |

### 18.2 Contratos API

Los endpoints de PD siguen el patrón `GameAjax::json()`:

| Endpoint | Método | Input | Output |
|----------|--------|-------|--------|
| `pd_purchase.php` | POST | `{character_id, item_type, item_name}` | `{ok, data: {character_id, item_type, item_name, pd_spent, new_pd_available}}` |
| `pd_history.php` | GET | `?character_id=N` | `{ok, data: {character_id, total_pd, spent_pd, available_pd, purchases[]}}` |

### 18.3 Funciones Globales

| Función | Archivo | Propósito |
|---------|---------|-----------|
| `game_get_character_pd_total()` | `pd_helpers.php:8` | Obtiene saldo total desde `game_personajes.puntos_destino` |
| `game_get_character_pd_spent()` | `pd_helpers.php:17` | Calcula gasto total desde `game_pd_purchases` |
| `game_get_character_pd_available()` | `pd_helpers.php:26` | Saldo disponible (`total - spent`, truncado a >= 0) |
| `game_get_character_purchases()` | `pd_helpers.php:33` | Lista completa de compras de un personaje |
| `game_register_pd_purchase()` | `pd_helpers.php:50` | Registra una compra en `game_pd_purchases` |
| `game_get_active_pj_id()` | Inc global | Obtiene el PJ activo del usuario |
| `game_get_active_staff_level()` | Inc global | Obtiene nivel de staff del usuario |

---

*Fin de la guía — 21. Puntos Destino (PD)*
