# 20. Economía — Berries y Tienda

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 20
> **Propósito:** Documentar exhaustivamente el subsistema económico del foro: la moneda Berries, el sistema de tienda (compra/venta), la gestión del catálogo por staff, las reglas de negocio, decisiones de diseño, y consejos para jugadores y staff.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [Modelo de Datos — Berries en `game_personajes`](#2-modelo-de-datos)
3. [Modelo de Datos — Cartas en `game_cards`](#3-modelo-de-datos)
4. [Sistema de Tienda — Catálogo Público](#4-sistema-de-tienda)
5. [Flujo de Compra — `tienda_comprar.php`](#5-flujo-de-compra)
6. [Flujo de Venta — `tienda_vender.php`](#6-flujo-de-venta)
7. [Frontend de Tienda — `tienda.php`](#7-frontend-de-tienda)
8. [Herramientas de Staff — Gestión de Catálogo](#8-herramientas-de-staff)
9. [Flujo de Datos Completo](#9-flujo-de-datos-completo)
10. [Filosofía de Diseño](#10-filosofía-de-diseño)
11. [Consejos para Jugadores](#11-consejos-para-jugadores)
12. [Consejos para Staff](#12-consejos-para-staff)
13. [Guía de Troubleshooting](#13-guía-de-troubleshooting)

---

## 1. Arquitectura General

### 1.1 Capas del Subsistema

```
┌─────────────────────────────────────────────────────────────┐
│                    CLIENTE (Navegador)                       │
│  ┌──────────────────┐  ┌─────────────────────────────────┐  │
│  │  tienda.js       │  │  zona_staff_tienda.js           │  │
│  │  (carrito,       │  │  (catálogo, pool,              │  │
│  │   compra, venta) │  │   añadir/quitar)                │  │
│  └──────┬───────────┘  └──────────┬──────────────────────┘  │
│         │                         │                          │
└─────────┼─────────────────────────┼──────────────────────────┘
          │ HTTP POST/GET + JSON    │
┌─────────┼─────────────────────────┼──────────────────────────┐
│  ┌──────▼─────────────────────────▼──────────────────────┐  │
│  │              PHP — CAPA DE APLICACIÓN                  │  │
│  │  Públicas: tienda.php (render),                        │  │
│  │  AJAX: tienda_comprar.php, tienda_vender.php,          │  │
│  │  Staff: zona_staff_tienda.php, shop_catalog_list.php,  │  │
│  │         shop_catalog_update.php, shop_pool_list.php    │  │
│  │  Servicios: PersonajeRepository (findByIdForUser)      │  │
│  └────────────────────────┬──────────────────────────────┘  │
│                           │                                  │
│                           ▼                                  │
│  ┌────────────────────────────────────────────────────────┐  │
│  │              MySQL (MyBB + tablas game_*)               │  │
│  │  game_personajes (berries)                             │  │
│  │  game_cards (in_shop, cost_berries, shop_category)    │  │
│  │  game_character_cards (inventario, cantidad)           │  │
│  └────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────┘
```

### 1.2 Filosofía de la Arquitectura

**¿Por qué una moneda única (Berries) y no múltiples monedas?**

- **Simplicidad cognitiva:** El jugador solo gestiona un saldo. No hay "oro, plata, gemas, tickets" como en juegos F2P.
- **Una sola columna en DB:** `game_personajes.berries` es un INT simple. Cualquier query de saldo es una lectura directa, sin JOINs ni cálculos.
- **Economía lineal:** Todo tiene un precio en berries. No hay conversiones entre monedas ni tasas de cambio que explicar.

**¿Por qué la moneda está en el personaje y no en el usuario?**

- Porque la economía es diagética (ocurre dentro del mundo del juego). Los berries los gasta el PERSONAJE, no el jugador.
- Si un usuario tiene 3 personajes, cada uno tiene su propio bolsillo. No pueden transferirse berries entre sí sin rol (comercio entre PJs).
- Refuerza la identidad personaje-jugador: "yo soy mi personaje, y mi personaje tiene berries".

**¿Por qué PHP plano + MySQL sin backend externo?**

- Misma razón que el sistema de personajes (ver 01-personaje.md §1.2): MyBB ya corre PHP + MySQL. Añadir otro backend para la tienda duplicaría infraestructura.
- Las operaciones de compra/venta son transacciones atómicas de dos pasos: deducir berries + asignar carta. MySQL lo maneja en dos queries sin necesidad de transacciones distribuidas.
- Latencia cero: la tienda se carga en milisegundos porque todo está en la misma DB.

### 1.3 Impacto RPG

| Decisión arquitectónica | Lo que significa para el juego |
|------------------------|-------------------------------|
| Moneda única (Berries) | Economía simple, sin curvas de aprendizaje |
| Berries por personaje | Cada PJ gestiona su propio dinero |
| Sin backend externo | La tienda funciona offline, sin dependencias |
| MySQL para todo | Operaciones instantáneas, sin latencia de red |

### 1.4 Principios de Diseño

1. **Economía simple:** Una moneda, un saldo, sin conversiones.
2. **Validación servidor-side:** El JS solo mejora UX; toda transacción se revalida en PHP.
3. **Operaciones atómicas:** La compra y la venta son dos queries independientes pero siempre se ejecutan ambas (o ninguna si falla la primera).
4. **Sink económico:** La venta al 50% introduce un sumidero de berries para evitar inflación.
5. **Catálogo curado por staff:** No todo se puede comprar. Solo lo que el staff decide poner a la venta.

---

## 2. Modelo de Datos — Berries en `game_personajes`

### 2.1 Definición SQL

```sql
CREATE TABLE mybb_game_personajes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT DEFAULT NULL,
    name            VARCHAR(255) NOT NULL,
    -- ... otros campos del personaje ...
    berries         INT NOT NULL DEFAULT 0,
    puntos_destino  INT NOT NULL DEFAULT 0,
    -- ...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

El campo `berries` se encuentra en la línea 108 del schema de instalación, entre `cronologia_json` y el cierre de la CREATE TABLE.

### 2.2 Características del Campo

| Propiedad | Valor |
|-----------|-------|
| Tipo | `INT NOT NULL DEFAULT 0` |
| Rango | -2³¹ a 2³¹-1 (~2.1B) |
| Default | 0 |
| Sin signo | No (se usa `INT` sin `UNSIGNED` para permitir operaciones como `berries - coste` incluso si temporalmente diera negativo — aunque se valida antes) |

**Filosofía del `INT` sin signo:**
- Se validó que el saldo sea suficiente ANTES de restar. Si la validación pasa, la resta nunca dará negativo.
- Si hubiera un bug y se restara de más, el valor negativo serviría como señal de alerta (en lugar de un wrap-around con `UNSIGNED`).

### 2.3 Consultas Típicas

```sql
-- Obtener saldo
SELECT berries FROM mybb_game_personajes WHERE id = {character_id};

-- Restar berries (compra)
UPDATE mybb_game_personajes SET berries = berries - {total_cost} WHERE id = {character_id};

-- Sumar berries (venta, recompensa)
UPDATE mybb_game_personajes SET berries = berries + {total_refund} WHERE id = {character_id};

-- Staff asigna berries
UPDATE mybb_game_personajes SET berries = {amount} WHERE id = {character_id};
```

### 2.4 Impacto RPG

| Operación | Efecto en el juego |
|-----------|-------------------|
| Completar misión | + berries (recompensa narrativa) |
| Rol activo (posts) | + berries (bonus opcional por staff) |
| Eventos especiales | + berries (premio competitivo) |
| Comercio entre PJs | transferencia narrativa de berries |
| Compra en tienda | - berries (adquirir carta) |
| Venta en tienda | + berries (50% del valor) |

---

## 3. Modelo de Datos — Cartas en `game_cards`

### 3.1 Definición SQL (Campos Relevantes)

```sql
CREATE TABLE mybb_game_cards (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    card_type       VARCHAR(50) NOT NULL,
    `rank`          VARCHAR(10) NOT NULL DEFAULT 'C',
    -- ... otros campos de carta ...
    cost_berries    INT NOT NULL DEFAULT 0,
    in_shop         TINYINT(1) NOT NULL DEFAULT 0,
    shop_category   VARCHAR(50) DEFAULT 'utiles',
    peso            INT NOT NULL DEFAULT 1,
    -- ...
    KEY idx_shop (in_shop, card_type, cost_berries)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.2 Campos Relacionados con la Tienda

| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `cost_berries` | INT | 0 | Precio en berries. Solo cartas con `cost_berries > 0` son comerciables. |
| `in_shop` | TINYINT(1) | 0 | Flag: 1 = visible en catálogo público de tienda. |
| `shop_category` | VARCHAR(50) | 'utiles' | Categoría visual en la tienda (6 valores). |

### 3.3 Categorías de Tienda (`shop_category`)

| Categoría | Slug en DB | Display en tienda | Ícono |
|-----------|-----------|-------------------|-------|
| Útiles | `utiles` | Útiles | `fa-toolbox` |
| Armería | `armeria` | Armería | `fa-shield-halved` |
| Astillero | `naval` | Astillero | `fa-ship` |
| Criadero | `mascotas` | Criadero | `fa-paw` |

**Nota importante:** En la base de datos las categorías internas son `utiles`, `armeria`, `naval`, `mascotas`. Sin embargo, el maestro de sistemas (`MAESTRO_SISTEMAS_RPG.md`) menciona 6 categorías: `utiles / armas / armaduras / consumibles / barcos / npcs`. Esto refleja una versión anterior del diseño. El sistema actual (implementado) usa 4 categorías con slugs diferentes. La UI mapea `utiles` → Útiles, `armeria` → Armería, `naval` → Astillero, `mascotas` → Criadero. Los ítems consumibles (tipo `equipo` con `equipo_type = 'util'`) aparecen dentro de `utiles`.

### 3.4 Tipos de Carta Comerciables

Solo tres tipos de carta pueden venderse en la tienda:

| Tipo | Descripción | Ejemplos |
|------|-------------|----------|
| `equipo` | Objetos equipables y consumibles | Espadas, armaduras, pociones, herramientas |
| `npc_menor` | Compañeros / aliados menores | Animales domésticos, asistentes, criaturas |
| `barco` | Embarcaciones | Barcos de distintos rangos y tamaños |

**¿Por qué solo estos tres tipos?**
- `tecnica`: Las técnicas son conocimiento del personaje, no se compran y venden.
- `haki`: El Haki se entrena, no se adquiere comercialmente.
- `akuma`: Las frutas del diablo son únicas, no se venden en catálogo.
- `npc_mayor`: Personajes importantes controlados por staff, no son bienes comerciales.

### 3.5 Índice de Búsqueda

El índice `idx_shop (in_shop, card_type, cost_berries)` optimiza la query principal del catálogo:

```sql
SELECT * FROM game_cards
WHERE in_shop = 1
  AND cost_berries > 0
  AND card_type IN ('equipo', 'npc_menor', 'barco')
ORDER BY shop_category ASC, name ASC;
```

### 3.6 Impacto RPG

| Campo | Lo que permite en el juego |
|-------|---------------------------|
| `cost_berries` | Precio de compra, base para cálculo de reventa (50%) |
| `in_shop` | Control del staff sobre qué está disponible |
| `shop_category` | Organización visual para el jugador |

---

## 4. Sistema de Tienda — Catálogo Público

### 4.1 Ruta y Acceso

**Archivo:** `back/forum/game/public/tienda.php`
**URL:** `/game/public/tienda.php`
**Requisito:** Usuario autenticado (`uid > 0`).

### 4.2 Carga del Catálogo

La tienda pública (`tienda.php`) carga el catálogo con esta query:

```php
$shop_q = $db->query("
    SELECT id, name, card_type, `rank`, image_url, description, cost_berries, shop_category,
           effects_json, tags_json, dice, cost_pe, execution_cost, execution_stat,
           activation, reposo, duracion
    FROM {$prefix}game_cards
    WHERE in_shop = 1
      AND cost_berries > 0
      AND card_type IN ('equipo', 'npc_menor', 'barco')
    ORDER BY shop_category ASC, name ASC
");
```

**Filtros aplicados:**
1. `in_shop = 1`: Solo cartas marcadas por staff como disponibles.
2. `cost_berries > 0`: Excluye cartas sin precio o con precio 0 (regalo, no comprable).
3. `card_type IN ('equipo', 'npc_menor', 'barco')`: Solo tipos comerciables.

### 4.3 Detección de Consumibles

Una carta es consumible si:
- `card_type = 'equipo'`
- El campo `effects_json` contiene `{"equipo_type": "util"}`

Los consumibles pueden comprarse en múltiplos (cantidad > 1) y acumularse en el inventario. Los no-consumibles (armas, armaduras) son únicos: solo puedes tener 1.

```php
$row['is_consumable'] = (
    $row['card_type'] === 'equipo'
    && strtolower((string)($effects['equipo_type'] ?? '')) === 'util'
);
```

### 4.4 Organización por Categorías

La tienda agrupa los artículos en 4 categorías visuales usando un sistema de tabs:

```php
$categories = [
    'utiles'  => ['label' => 'Útiles',    'icon' => 'fa-toolbox',      'items' => []],
    'armeria' => ['label' => 'Armería',   'icon' => 'fa-shield-halved','items' => []],
    'naval'   => ['label' => 'Astillero',  'icon' => 'fa-ship',        'items' => []],
    'mascotas'=> ['label' => 'Criadero',   'icon' => 'fa-paw',         'items' => []],
];
```

Cada carta se asigna a su categoría según `shop_category`. Si una categoría no existe en el mapa, se asigna a `utiles` por defecto.

### 4.5 Renderizado de Cartas en Tienda

La función `render_shop_card()` genera el HTML de cada carta en el catálogo:

```php
function render_shop_card(array $c, string $b_url): string {
    // Imagen, nombre, descripción, precio formateado
    // Badge de tipo (Equipo, Compañero, Barco)
    // Botón "Añadir" que envía la carta al carrito JS
}
```

Cada carta incluye atributos `data-*`:
- `data-card-id`: ID de la carta
- `data-card-name`: Nombre para display
- `data-card-cost`: Coste en berries (int)
- `data-is-consumable`: Booleano para control de cantidad

### 4.6 Interfaz de Usuario

La tienda tiene dos modos activables con toggle:

1. **Modo Compra (default):** Muestra el catálogo completo con tabs por categoría y un carrito lateral.
2. **Modo Venta:** Muestra el inventario del personaje con opciones de venta.

Componentes visuales:
- **Cabecera:** Avatar del personaje activo, nombre y saldo de berries.
- **Tabs de categoría:** Navegación por pestañas.
- **Grid de cartas:** Cards en cuadrícula con imagen, nombre, descripción y precio.
- **Carrito lateral:** Drawer que se abre al añadir artículos, con total y botón de confirmación.
- **Vista previa de carta:** Modal que muestra la carta completa al hacer clic.
- **Panel de venta:** Lista de objetos del inventario con precio de reventa y botón de vender.

### 4.7 Integración con Carrito (JS)

La tienda usa `tienda.js` (en `jscripts/game/tienda.js`) para manejar:
- Apertura/cierre del carrito (drawer lateral)
- Añadir/remover artículos
- Cálculo de total
- Envío de compra a `tienda_comprar.php`
- Feedback de resultado (success/error)
- Actualización del saldo visible sin recargar la página

### 4.8 Vista Previa de Cartas

Al hacer clic en una carta del catálogo, se abre un modal (`shop-card-preview-modal`) que muestra la carta renderizada con `foro_deck_ui.js`, permitiendo al jugador ver todos los detalles (stats, efectos, dados, coste PE) antes de decidir comprar.

### 4.9 Config JS

La tienda inyecta un objeto `window.TIENDA_CONFIG` con:
- `bburl`: URL base del foro
- `my_post_key`: Token CSRF
- `character_id`: ID del personaje activo
- `is_approved`: Booleano (personaje aprobado)
- `current_berries`: Saldo actual
- `cardsById`: Mapa de cartas (id → preview) para render rápido sin recargar

---

## 5. Flujo de Compra — `tienda_comprar.php`

### 5.1 Endpoint

**Archivo:** `back/forum/game/ajax/tienda_comprar.php`
**Método:** POST
**Content-Type:** `application/json; charset=utf-8`
**Autenticación:** Login requerido + CSRF token

### 5.2 Payload de Entrada

```json
{
    "character_id": 42,
    "cart": [
        { "card_id": 15, "cantidad": 1 },
        { "card_id": 23, "cantidad": 3 }
    ]
}
```

### 5.3 Diagrama de Secuencia

```
JS (tienda.js) → POST /tienda_comprar.php
  → GameAjax::requireLogin()
  → GameAjax::requirePost()
  → GameAjax::postJson()
  → GameAjax::requireCsrf()
  → Validar parámetros (character_id > 0, cart no vacío)
  → PersonajeRepository::findByIdForUser(character_id, uid)
    → Verificar existencia
    → Verificar ownership (uid coincide o es staff/narrator)
  → Verificar status 'aprobada'
  → Cargar cartas del carrito desde DB con in_shop = 1
  → Por cada item:
    → Validar que existe en DB
    → Validar tipo (equipo/npc_menor/barco)
    → Validar cost_berries > 0
    → Si es único (no consumible), verificar que no lo tiene ya
    → Acumular total_cost
  → Validar saldo suficiente (berries >= total_cost)
  → UPDATE berries = berries - total_cost
  → INSERT/UPDATE game_character_cards (ON DUPLICATE KEY)
  → game_log_action('tienda_compra', ...)
  → Response {ok: true, new_berries: N, message: "..."}
```

### 5.4 Validaciones Detalladas

#### 5.4.1 Autenticación y CSRF
```php
$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);
```

#### 5.4.2 Validez del Cuerpo
```php
$character_id = (int)($input['character_id'] ?? 0);
$cart = $input['cart'] ?? [];

if ($character_id <= 0 || !is_array($cart) || empty($cart)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Parámetros inválidos.'], 400);
}
```

#### 5.4.3 Existencia y Ownership del Personaje
```php
$repo = new PersonajeRepository();
$character = $repo->findByIdForUser($character_id, $uid);

if ($character === null) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'Personaje no encontrado.'], 404);
}
```

`findByIdForUser()` verifica:
1. ¿Es el dueño directo? → OK
2. ¿Es superadmin y es NPC? → OK
3. ¿Es narrador asignado? → OK
4. Sino → null (no encontrado)

#### 5.4.4 Estado del Personaje
```php
if ($character['status'] !== 'aprobada') {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'El personaje debe estar aprobado.'], 403);
}
```

**Filosofía:** Solo personajes aprobados pueden comprar. Un personaje en creación o en revisión no debería gastar berries porque su existencia aún no es definitiva.

#### 5.4.5 Validación de Cartas en DB
```php
$card_ids_str = implode(',', $card_ids);
$cards_q = $db->query("SELECT * FROM {$prefix}game_cards WHERE id IN ({$card_ids_str}) AND in_shop = 1");
```

**Importante:** La condición `AND in_shop = 1` evita que alguien compre cartas que ya no están en el catálogo (quizá retiradas por staff entre que el jugador cargó la página y pulsó comprar).

#### 5.4.6 Validación por Item
```php
foreach ($cart as $item) {
    $card_id = (int)($item['card_id'] ?? 0);
    $qty = (int)($item['cantidad'] ?? 1);
    if ($qty <= 0) $qty = 1;

    if (!isset($cards_db[$card_id])) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Objeto no a la venta.'], 400);
    }

    $valid_types = ['equipo', 'npc_menor', 'barco'];
    if (!in_array($card['card_type'], $valid_types, true)) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Tipo no comerciable.'], 400);
    }

    $cost_berries = (int)$card['cost_berries'];
    if ($cost_berries <= 0) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Precio inválido.'], 400);
    }
    $total_cost += $cost_berries * $qty;

    // Validar duplicados para objetos únicos (no consumibles)
    if (!$is_consumable) {
        $check_owned = $db->query("SELECT 1 FROM game_character_cards
            WHERE character_id = {$character_id} AND card_id = {$card_id} LIMIT 1");
        if ($db->num_rows($check_owned) > 0) {
            GameAjax::json(false, null, ['code' => 400, 'message' => "Ya posees: {$card['name']}."], 400);
        }
        $qty = 1; // Forzar cantidad 1 para únicos
    }
}
```

#### 5.4.7 Validación de Saldo
```php
$current_berries = (int)($character['berries'] ?? 0);
if ($current_berries < $total_cost) {
    GameAjax::json(false, null, [
        'code' => 400,
        'message' => "Saldo insuficiente. Necesitas " . number_format($total_cost, 0, ',', '.')
            . " B. y posees " . number_format($current_berries, 0, ',', '.') . " B."
    ], 400);
}
```

### 5.5 Ejecución de la Transacción

```php
// Paso 1: Deducir berries
$db->write_query("UPDATE {$prefix}game_personajes
    SET berries = berries - {$total_cost}
    WHERE id = {$character_id}");

// Paso 2: Insertar/actualizar inventario
foreach ($items_to_buy as $item) {
    $card_id = (int)$item['card']['id'];
    $qty = $item['cantidad'];
    $rank = $db->escape_string($item['rank']);

    $db->write_query("
        INSERT INTO {$prefix}game_character_cards (character_id, card_id, current_rank, assigned_by, cantidad)
        VALUES ({$character_id}, {$card_id}, '{$rank}', {$uid}, {$qty})
        ON DUPLICATE KEY UPDATE cantidad = cantidad + {$qty}
    ");
}

// Paso 3: Log
game_log_action('tienda_compra', [
    'user_id' => $uid,
    'character_id' => $character_id,
    'total_cost' => $total_cost,
    'items_count' => count($items_to_buy)
]);
```

### 5.6 Atomicidad

Aunque las operaciones no están envueltas en una transacción SQL explícita, el diseño garantiza consistencia:

1. **Primero** se restan berries. Si esto falla, no se ejecuta el paso 2.
2. **Segundo** se asignan cartas. Si esto falla, los berries ya se restaron.

**Caso de fallo:** Si el paso 2 falla (rareza: caída de DB entre queries), el jugador pierde berries pero no recibe carta. Para mitigarlo:
- El paso 1 podría revertirse manualmente por staff.
- El log (`game_log_action`) registra cada compra para auditoría.
- En la práctica, MySQL InnoDB con `write_query` en el mismo hilo de ejecución hace que ambos writes se completen en milisegundos. La probabilidad de fallo entre ellos es ínfima.

### 5.7 Respuesta

```php
GameAjax::json(true, [
    'new_berries' => $new_berries,
    'message' => 'Compra realizada correctamente.'
], null);
```

### 5.8 Impacto RPG

| Paso | Lo que significa para el jugador |
|------|----------------------------------|
| Validar saldo | "No puedes comprar lo que no puedes pagar" |
| Solo aprobados | "Debes tener personaje activo y aprobado" |
| Únicos vs consumibles | "Las armas son únicas; las pociones se acumulan" |
| Log de compra | "El staff puede ver tu historial de compras" |

---

## 6. Flujo de Venta — `tienda_vender.php`

### 6.1 Endpoint

**Archivo:** `back/forum/game/ajax/tienda_vender.php`
**Método:** POST
**Content-Type:** `application/json; charset=utf-8`
**Autenticación:** Login requerido + CSRF token

### 6.2 Payload de Entrada

```json
{
    "character_id": 42,
    "card_id": 15,
    "cantidad": 1
}
```

### 6.3 Diagrama de Secuencia

```
JS (tienda.js) → POST /tienda_vender.php
  → GameAjax::requireLogin()
  → GameAjax::requirePost()
  → GameAjax::postJson()
  → GameAjax::requireCsrf()
  → Validar parámetros (character_id > 0, card_id > 0, cantidad > 0)
  → PersonajeRepository::findByIdForUser(character_id, uid)
    → Verificar existencia
    → Verificar ownership
  → Verificar status 'aprobada'
  → Cargar carta desde DB (debe existir, cost_berries > 0)
  → Validar tipo comerciable (equipo/npc_menor/barco)
  → Verificar que el personaje posee la carta
  → Validar cantidad suficiente
  → Calcular reembolso: floor(cost_berries × 0.5) × cantidad
  → DELETE o UPDATE game_character_cards (restar/decrementar)
  → UPDATE berries = berries + total_refund
  → Leer nuevo saldo
  → game_log_action('tienda_venta', ...)
  → Response {ok: true, new_berries: N, message: "..."}
```

### 6.4 Validaciones Detalladas

#### 6.4.1 Parámetros
```php
$character_id = (int)($input['character_id'] ?? 0);
$card_id      = (int)($input['card_id'] ?? 0);
$cantidad     = (int)($input['cantidad'] ?? 1);

if ($character_id <= 0 || $card_id <= 0 || $cantidad <= 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Parámetros inválidos.'], 400);
}
```

#### 6.4.2 Existencia de la Carta y Valor de Reventa
```php
$card_q = $db->query("
    SELECT * FROM {$prefix}game_cards
    WHERE id = {$card_id}
      AND cost_berries > 0
    LIMIT 1
");
$card = $db->fetch_array($card_q);

if (!$card) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'El objeto no existe o no tiene valor de reventa.'], 404);
}
```

**Filosofía:** Solo cartas con `cost_berries > 0` pueden venderse. Esto excluye cartas obtenidas por eventos, misiones, o regalos del staff que no deberían generar berries al venderse.

#### 6.4.3 Tipo Comerciable
```php
$valid_types = ['equipo', 'npc_menor', 'barco'];
if (!in_array($card['card_type'], $valid_types, true)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Tipo no comerciable.'], 400);
}
```

Misma restricción que en compra. Técnicas, Haki, Akuma y NPC mayores no se pueden vender.

#### 6.4.4 Posesión y Cantidad
```php
$owned_q = $db->query("SELECT * FROM {$prefix}game_character_cards
    WHERE character_id = {$character_id} AND card_id = {$card_id} LIMIT 1");
$owned = $db->fetch_array($owned_q);

if (!$owned) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'No posees este objeto.'], 404);
}

$owned_cantidad = (int)($owned['cantidad'] ?? 1);
if ($cantidad > $owned_cantidad) {
    GameAjax::json(false, null, [
        'code' => 400,
        'message' => "Solo posees {$owned_cantidad} unidad(es) de este objeto.",
    ], 400);
}
```

### 6.5 Cálculo de Reembolso

```php
$cost_berries = (int)$card['cost_berries'];
$refund_each  = (int)floor($cost_berries * 0.5);
$total_refund = $refund_each * $cantidad;
```

**La fórmula:** `reembolso = floor(precio_original × 0.5)`

Ejemplos:
| Precio original | 50% | floor | Reembolso por unidad |
|----------------|-----|-------|---------------------|
| 100 | 50.0 | 50 | 50 |
| 150 | 75.0 | 75 | 75 |
| 99 | 49.5 | 49 | 49 |
| 1 | 0.5 | 0 | 0 |

**Filosofía del 50% con floor:**
- **Sink económico:** El 50% perdido en cada transacción elimina berries del sistema, combatiendo la inflación.
- **Decisión con peso:** Vender un objeto siempre duele un poco. No es un "préstamo gratuito".
- **El floor asegura que el reembolso nunca supere el precio original.** Para objetos de 1 berry, el reembolso es 0 (no se pueden revender). Esto es intencional: objetos de 1 berry probablemente sean consumibles baratos o regalos simbólicos.

### 6.6 Actualización del Inventario

```php
if ($cantidad >= $owned_cantidad) {
    // Vende todas las copias → borrar registro
    $db->write_query("DELETE FROM {$prefix}game_character_cards
        WHERE character_id = {$character_id} AND card_id = {$card_id}");
} else {
    // Vende solo algunas → decrementar
    $remaining = $owned_cantidad - $cantidad;
    $db->write_query("UPDATE {$prefix}game_character_cards
        SET cantidad = {$remaining}
        WHERE character_id = {$character_id} AND card_id = {$card_id}");
}
```

**Filosofía del DELETE vs UPDATE:**
- Si vendes todas las copias, se borra el registro. No tiene sentido mantener filas con `cantidad = 0`.
- Si vendes solo algunas, se decrementa la cantidad. El registro permanece para futuras ventas o uso.

### 6.7 Reembolso de Berries

```php
$db->write_query("UPDATE {$prefix}game_personajes
    SET berries = berries + {$total_refund}
    WHERE id = {$character_id}");

$new_q = $db->query("SELECT berries FROM {$prefix}game_personajes
    WHERE id = {$character_id} LIMIT 1");
$new_berries = (int)($new_q['berries'] ?? 0);
```

Se lee el saldo actualizado después de la venta para devolverlo al frontend.

### 6.8 Log y Respuesta

```php
game_log_action('tienda_venta', [
    'user_id'      => $uid,
    'character_id' => $character_id,
    'card_id'      => $card_id,
    'cantidad'     => $cantidad,
    'total_refund' => $total_refund,
]);

GameAjax::json(true, [
    'new_berries' => $new_berries,
    'message'     => 'Venta realizada correctamente.',
], null);
```

### 6.9 Impacto RPG

| Aspecto | Lo que significa para el jugador |
|---------|----------------------------------|
| 50% de reembolso | "Vender siempre es perder dinero. Piensa bien tus compras." |
| floor() en el reembolso | "Los objetos baratos (1 B.) no se pueden revender." |
| Solo comerciables | "Tu técnica secreta no tiene precio; no puedes venderla." |
| Venta parcial | "Puedes vender 3 pociones y quedarte con 2." |

---

## 7. Frontend de Tienda — `tienda.php`

### 7.1 Estructura del Archivo

**Archivo:** `back/forum/game/public/tienda.php` (418 líneas)
**Dependencias:**
- `bootstrap.php` (entorno global)
- `rpg_modal.js` (modales de vista previa)
- `tienda.js` (lógica de carrito y compra/venta)
- `foro_deck_ui.js` (renderizado de cartas)

### 7.2 Flujo de Carga

```
GET tienda.php
  → Verificar login (redirect si no)
  → Obtener personaje activo (game_get_active_pj_id)
  → Cargar catálogo de tienda (in_shop=1, cost_berries>0, tipos comerciables)
  → Cargar inventario del personaje (solo cartas comerciables con valor)
  → Organizar por categorías
  → Renderizar tabs + panels de compra
  → Renderizar panel de venta
  → Inyectar TIENDA_CONFIG (JS)
  → game_render_page('Tienda — Gran Bazar del Mundo', $content)
```

### 7.3 Funciones de Renderizado

#### `tienda_card_to_preview(array $row): array`
Prepara los datos de una carta para el modal de vista previa. Normaliza:
- `tags_json` → array de strings
- `effects_json` → array asociativo
- `cost_pe` → string (— si vacío)
- Detecta `is_consumible` (tipo equipo + equipo_type = util)

#### `render_shop_card(array $c, string $b_url): string`
Renderiza el HTML de una carta en el grid de la tienda:
```html
<article class="rpg-shop-card rpg-shop-card--clickable"
    data-card-id="{id}"
    data-card-name="{name}"
    data-card-cost="{cost}"
    data-is-consumable="{bool}">
  <div class="rpg-shop-card-img">
    <img src="{image}" alt="{name}" loading="lazy">
    <span class="rpg-shop-card-type-badge">{type}</span>
  </div>
  <div class="rpg-shop-card-body">
    <h3 class="rpg-shop-card-title">{name}</h3>
    <p class="rpg-shop-card-desc">{description}</p>
    <div class="rpg-shop-card-footer">
      <span class="rpg-shop-card-price">{cost} B.</span>
      <button type="button" class="rpg-btn rpg-btn--laton rpg-shop-add-btn"
        data-card-id="{id}">Añadir</button>
    </div>
  </div>
</article>
```

#### `render_sell_card(array $c): string`
Renderiza el HTML de una carta en el panel de venta:
```html
<article class="rpg-shop-sell-card"
    data-card-id="{id}"
    data-card-cost="{cost}"
    data-is-consumable="{bool}"
    data-owned="{cantidad}">
  <div class="rpg-shop-sell-info">
    <span class="rpg-shop-sell-name">{name}</span>
    <span class="rpg-shop-sell-owned">{cantidad} en posesión</span>
  </div>
  <!-- Controles de cantidad (solo consumibles con owned > 1) -->
  <div class="rpg-shop-sell-action">
    <span class="rpg-shop-sell-refund">{refund} B. cada uno</span>
    <button class="rpg-btn rpg-btn--danger rpg-shop-sell-btn" data-card-id="{id}">Vender</button>
  </div>
</article>
```

Los controles de cantidad aparecen solo si la carta es consumible y la cantidad poseída > 1. El jugador puede seleccionar cuántas unidades vender.

### 7.4 Manejo de Estados

| Estado | Comportamiento |
|--------|---------------|
| Sin personaje activo | Muestra "Necesitas un personaje activo" en venta; catálogo vacío en compra |
| Personaje no aprobado | Muestra advertencia; botón de compra deshabilitado |
| Inventario vacío | Muestra "No tienes objetos vendibles" en panel de venta |
| Catálogo vacío | Muestra "No hay artículos disponibles" por categoría |
| Carrito vacío | Botón de confirmar compra deshabilitado |

### 7.5 Componentes Interactivos

1. **Toggle Comprar/Vender:** Cambia entre modo compra y modo venta.
2. **Tabs de categoría:** Filtran el catálogo por tipo.
3. **Buscador:** Filtro de texto libre sobre nombres de cartas.
4. **Carrito (drawer lateral):**
   - Se abre al pulsar "Añadir" o el FAB del carrito.
   - Muestra lista de artículos con cantidad y subtotal.
   - Muestra total acumulado.
   - Botón "Confirmar Compra" que ejecuta la transacción.
5. **Modal de vista previa:** Muestra la carta renderizada con todos sus detalles.
6. **Controles de cantidad en venta:** +/− para seleccionar cuántas unidades vender.
7. **Overlay de carga:** Indicador visual durante la transacción.

### 7.6 Manejo de Errores en el Frontend

| Error del servidor | Feedback al usuario |
|-------------------|-------------------|
| 400 (parámetros inválidos) | Mensaje descriptivo en el carrito |
| 403 (no aprobado) | "Tu personaje debe estar aprobado" |
| 404 (carta no encontrada) | "Uno de los objetos ya no está disponible" |
| 400 (saldo insuficiente) | Muestra saldo actual vs necesario |
| 400 (ya posees único) | "Ya tienes este objeto" |
| Error de red | "Error de conexión. Intenta de nuevo." |

---

## 8. Herramientas de Staff — Gestión de Catálogo

### 8.1 Panel de Gestión — `zona_staff_tienda.php`

**Archivo:** `back/forum/game/public/zona_staff_tienda.php` (118 líneas)
**Acceso:** Staff level ≥ 3 (superadmin).
**URL:** `/game/public/zona_staff_tienda.php`

El panel permite al staff:
1. **Ver el catálogo actual:** Lista todas las cartas en venta con su categoría y precio.
2. **Añadir cartas al catálogo:** Busca cartas disponibles (comerciables, con precio, no en shop) y las añade.
3. **Quitar cartas del catálogo:** Elimina cartas de la tienda.
4. **Cambiar categoría:** Asigna la categoría visual de cada carta.

### 8.2 Arquitectura del Panel

```html
<div class="rpg-peticiones rpg-shop-manage-page">
  <header>
    <h1>Gestionar Tienda</h1>
    <p>Construye el catálogo del bazar</p>
  </header>

  <div class="rpg-shop-catalog-panel">
    <div class="rpg-shop-catalog-toolbar">
      <h2>Catálogo del bazar</h2>
      <button id="shop-btn-add-card">Añadir carta</button>
    </div>

    <div class="rpg-shop-catalog-filters">
      <input type="search" placeholder="Buscar en el catálogo...">
      <select id="shop-catalog-filter-cat">
        <option value="">Todas las categorías</option>
        <option value="utiles">Útiles</option>
        <option value="armeria">Armería</option>
        <option value="naval">Astillero</option>
        <option value="mascotas">Criadero</option>
      </select>
    </div>

    <div id="shop-catalog-loading">Cargando...</div>
    <ul id="shop-catalog-list" class="rpg-shop-catalog-list"></ul>
    <p id="shop-catalog-empty" class="rpg-is-hidden">El bazar está vacío.</p>
  </div>
</div>

<!-- Modal de añadir -->
<div id="shop-add-modal" class="rpg-modal-overlay">
  <div class="rpg-modal-panel">
    <h3>Añadir carta al bazar</h3>
    <input type="search" id="shop-pool-search" placeholder="Buscar por nombre...">
    <ul id="shop-pool-list"></ul>
    <div id="shop-add-confirm" class="rpg-is-hidden">
      <h4>Confirmar</h4>
      <p id="shop-add-confirm-name"></p>
      <select id="shop-add-category">
        <option value="utiles">Útiles</option>
        <option value="armeria">Armería</option>
        <option value="naval">Astillero</option>
        <option value="mascotas">Criadero</option>
      </select>
    </div>
    <button id="shop-add-confirm-btn" class="rpg-is-hidden">Añadir al bazar</button>
  </div>
</div>
```

### 8.3 Endpoints AJAX de Staff

#### 8.3.1 `shop_catalog_list.php` — Listar catálogo o pool

**Archivo:** `back/forum/game/ajax/shop_catalog_list.php`
**Método:** GET
**Parámetros:** `scope` = `active` (en tienda) o `pool` (disponibles para añadir)

**Query para `scope=active`:**
```sql
SELECT id, name, card_type, `rank`, image_url, cost_berries, in_shop, shop_category
FROM game_cards
WHERE card_type IN ('equipo', 'npc_menor', 'barco')
  AND cost_berries > 0
  AND in_shop = 1
ORDER BY name ASC
```

**Query para `scope=pool`:**
```sql
SELECT id, name, card_type, `rank`, image_url, cost_berries, in_shop, shop_category
FROM game_cards
WHERE card_type IN ('equipo', 'npc_menor', 'barco')
  AND cost_berries > 0
  AND in_shop = 0
ORDER BY name ASC
```

**Validación de permisos:**
```php
$staff_level = /* obtener staff_level del personaje activo */;
if ($staff_level < 3) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'Permisos insuficientes.'], 403);
}
```

Solo superadmins (level 3) pueden gestionar el catálogo.

#### 8.3.2 `shop_catalog_update.php` — Añadir o quitar del catálogo

**Archivo:** `back/forum/game/ajax/shop_catalog_update.php`
**Método:** POST
**Payload:**
```json
{
    "card_id": 15,
    "in_shop": 1,
    "shop_category": "utiles"
}
```

**Validaciones:**
1. Staff level ≥ 3.
2. La carta debe existir, ser tipo comerciable y tener `cost_berries > 0`.
3. `shop_category` debe ser uno de: `utiles`, `armeria`, `naval`, `mascotas`.
4. `in_shop` debe ser 0 o 1.

```php
$card_q = $db->query("
    SELECT id, card_type, cost_berries
    FROM game_cards
    WHERE id = {$card_id}
      AND card_type IN ('equipo', 'npc_menor', 'barco')
      AND cost_berries > 0
    LIMIT 1
");
if (!$card) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'Carta no encontrada o no comerciable.'], 404);
}

$allowed_cats = ['utiles', 'armeria', 'naval', 'mascotas'];
if (!empty($input['shop_category'])) {
    $cat = (string)$input['shop_category'];
    if (!in_array($cat, $allowed_cats, true)) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Categoría inválida.'], 400);
    }
    $update['shop_category'] = $db->escape_string($cat);
}

$db->update_query('game_cards', $update, "id = {$card_id}");
```

### 8.4 Flujo de Añadir Carta al Catálogo

1. Staff abre `zona_staff_tienda.php`.
2. El catálogo actual se carga vía `shop_catalog_list.php?scope=active`.
3. Staff pulsa "Añadir carta".
4. Se abre el modal que carga el pool disponible vía `shop_catalog_list.php?scope=pool`.
5. Staff busca y selecciona una carta del pool.
6. Aparece el panel de confirmación con selector de categoría.
7. Staff confirma; se envía POST a `shop_catalog_update.php` con `in_shop=1`.
8. La carta aparece en el catálogo activo y desaparece del pool.

### 8.5 Flujo de Quitar Carta del Catálogo

1. En la lista del catálogo activo, cada carta tiene un botón "Quitar".
2. Al pulsarlo, se envía POST a `shop_catalog_update.php` con `in_shop=0`.
3. La carta desaparece del catálogo activo y vuelve al pool.

### 8.6 Gestión de Precios

El precio de una carta (`cost_berries`) no se modifica desde la tienda. Se gestiona desde el Sistema de Cartas (staff edita la carta directamente en la DB o desde el panel de edición de cartas).

**Filosofía:** El precio es un atributo intrínseco de la carta, no de su presencia en la tienda. Si el staff quiere cambiar un precio, edita la carta. Si quiere ponerla en oferta, modifica `cost_berries` temporalmente.

### 8.7 Impacto RPG

| Acción de staff | Efecto |
|----------------|--------|
| Añadir carta | Los jugadores pueden comprarla |
| Quitar carta | Los jugadores ya no pueden comprarla (las existentes no se eliminan) |
| Cambiar precio | Afecta compras futuras y reembolso al vender |
| Cambiar categoría | Reorganiza visualmente la tienda |

---

## 9. Flujo de Datos Completo

### 9.1 Compra

```
JS (carrito con items)
  → POST /game/ajax/tienda_comprar.php
    → Validar login, CSRF, parámetros
    → PersonajeRepository::findByIdForUser()
    → Validar status 'aprobada'
    → SELECT game_cards (verificar in_shop=1, cost_berries>0, tipo comerciable)
    → Validar duplicados (únicos)
    → Validar saldo
    → UPDATE game_personajes SET berries = berries - total
    → INSERT/UPDATE game_character_cards (ON DUPLICATE KEY)
    → game_log_action('tienda_compra')
    → Response { new_berries, message }
  → JS actualiza saldo en UI, vacía carrito, muestra éxito
```

### 9.2 Venta

```
JS (artículo + cantidad)
  → POST /game/ajax/tienda_vender.php
    → Validar login, CSRF, parámetros
    → PersonajeRepository::findByIdForUser()
    → Validar status 'aprobada'
    → SELECT game_cards (verificar cost_berries>0, tipo comerciable)
    → Validar posesión y cantidad suficiente
    → Calcular reembolso: floor(cost_berries × 0.5) × cantidad
    → DELETE o UPDATE game_character_cards
    → UPDATE game_personajes SET berries = berries + refund
    → SELECT nuevo saldo
    → game_log_action('tienda_venta')
    → Response { new_berries, message }
  → JS actualiza saldo, refresca lista de inventario
```

### 9.3 Staff — Añadir al Catálogo

```
Staff pulsa "Añadir carta"
  → GET shop_catalog_list.php?scope=pool
    → Mostrar cartas disponibles
  → Staff selecciona carta y categoría
  → POST shop_catalog_update.php { card_id, in_shop: 1, shop_category }
    → Validar staff_level ≥ 3
    → Validar carta comerciable
    → UPDATE game_cards SET in_shop=1, shop_category=cat
    → Response { card actualizada }
  → JS refresca lista del catálogo activo
```

### 9.4 Staff — Quitar del Catálogo

```
Staff pulsa "Quitar" en una carta
  → POST shop_catalog_update.php { card_id, in_shop: 0 }
    → Validar staff_level ≥ 3
    → UPDATE game_cards SET in_shop=0
    → Response { card actualizada }
  → JS elimina carta de la lista visual
```

---

## 10. Filosofía de Diseño

### 10.1 Principios Rectores

1. **Economía simple, decisiones significativas.** No hay múltiples monedas ni sistemas de conversión complejos. Los berries son la única moneda, pero cada compra/venta tiene peso.

2. **El staff cura el catálogo, no los jugadores.** No hay "mercadillo entre jugadores" automatizado. El staff decide qué está disponible y a qué precio. Esto permite control narrativo: "no, no puedes comprar un barco SS en la tienda".

3. **La venta es un sumidero económico.** El 50% perdido en cada venta retira berries del sistema. Sin este sumidero, los berries acumulados por años de juego generarían inflación descontrolada.

4. **Consumibles como excepción.** Los objetos consumibles (pociones, herramientas de un solo uso) son el único caso donde se permite acumular múltiples copias. Esto refleja su naturaleza fungible.

5. **La tienda no es el único método de adquisición.** Las cartas también se obtienen por misiones, eventos, propuestas al staff y comercio entre jugadores. La tienda es un complemento, no el centro del juego.

### 10.2 Decisiones Clave y su Porqué

| Decisión | Alternativa descartada | Por qué se eligió así |
|----------|----------------------|----------------------|
| Moneda única (Berries) | Múltiples monedas (oro, gemas, tickets) | Simplicidad, una columna en DB, sin conversiones |
| 50% reembolso (floor) | 100% reembolso o sin reventa | Sumidero económico, decisiones con peso |
| Solo 3 tipos comerciables | Todas las cartas vendibles | Técnicas/Haki/Akuma son conocimiento, no bienes |
| Catálogo curado por staff | Mercado libre entre jugadores | Control de balance y narrativa |
| Consumibles acumulables | Todos los items únicos | Reflejar naturaleza fungible de pociones/utiles |
| Precio en la carta, no en tienda | Precio específico de tienda | Un precio fuente de verdad |
| Staff level 3 para gestión | Staff level 1 | Solo superadmins modifican la economía |
| ON DUPLICATE KEY en compra | INSERT + verificación previa | Atómico, evita race conditions |
| DELETE vs UPDATE en venta | Siempre UPDATE con cantidad=0 | No mantener registros fantasma |

### 10.3 Filosofía del Sink Económico

**Problema:** En un RPG de foro, los berries se generan de múltiples fuentes (misiones, eventos, rol, recompensas). Sin un sumidero, la base monetaria crece indefinidamente, causando inflación: los precios dejan de significar algo.

**Solución:** El 50% de reembolso en ventas es el principal sumidero. Cada vez que un jugador vende un objeto, la mitad del valor desaparece del sistema.

**¿Por qué 50% y no otro valor?**
- 50% es intuitivo: "recupero la mitad".
- Es lo suficientemente alto para que vender duela, pero no tanto como para que nadie venda nunca.
- El `floor()` evita reembolsos fraccionarios.
- Para objetos de 1 berry, el reembolso es 0 (sink total).

**¿Hay otros sumideros?**
- Compras en tienda (obvio): el dinero se "pierde" porque el objeto no se puede revender al mismo precio.
- Upgrades narrativos: el staff puede cobrar berries por servicios narrativos (entrenamiento, información, favores).
- Impuestos o tasas: no implementados actualmente, pero posibles en el futuro.

### 10.4 Filosofía de los Tipos Comerciables

**¿Por qué `equipo` sí y `tecnica` no?**

- **Equipo** son objetos físicos: espadas, armaduras, herramientas, barcos, compañeros. Se pueden intercambiar, comprar, vender. Son tangibles.
- **Técnica** es conocimiento internalizado. No puedes "vender" una técnica que sabes. Podrías enseñarla, pero eso es un acto narrativo, no una transacción de tienda.
- **Haki** es poder innato/entrenado. No se compra ni se vende.
- **Akuma no Mi** son únicas e irrepetibles. Si alguien tiene una, no debería poder venderla en catálogo (aunque podría intercambiarla narrativamente).
- **NPC mayor** son personajes con agencia propia. No son objetos.

### 10.5 Filosofía del Inventario

El inventario de un personaje (`game_character_cards`) almacena las cartas que posee. Para la tienda, solo interesan las cartas comerciables. La query del inventario en la tienda replica los mismos filtros que el catálogo:

```sql
SELECT c.id, c.name, c.card_type, c.cost_berries, cc.cantidad
FROM game_character_cards cc
JOIN game_cards c ON cc.card_id = c.id
WHERE cc.character_id = {char_id}
  AND c.card_type IN ('equipo', 'npc_menor', 'barco')
  AND c.cost_berries > 0
ORDER BY c.name ASC
```

**Filosofía:** Solo se puede vender lo que tiene valor de reventa. Si una carta fue obtenida por misión y su `cost_berries` es 0, no aparece en el inventario de venta (no se puede revender).

### 10.6 Filosofía del Log de Acciones

Cada compra y venta se registra con `game_log_action()`:

```php
game_log_action('tienda_compra', [
    'user_id' => $uid,
    'character_id' => $character_id,
    'total_cost' => $total_cost,
    'items_count' => count($items_to_buy)
]);

game_log_action('tienda_venta', [
    'user_id' => $uid,
    'character_id' => $character_id,
    'card_id' => $card_id,
    'cantidad' => $cantidad,
    'total_refund' => $total_refund
]);
```

**Propósitos del log:**
1. **Auditoría:** El staff puede revisar el historial económico de un personaje.
2. **Detección de fraudes:** Si un jugador reporta "perdí mis berries", el log muestra qué compras hizo.
3. **Estadísticas:** Cuánto dinero circula, qué se vende más, cuál es el precio promedio.
4. **Recuperación:** Si hay un bug, el log permite reconstruir el estado anterior.

---

## 11. Consejos para Jugadores

### 11.1 Gestionando tus Berries

**No gastes todo apenas puedas.**
- Los berries son un recurso limitado. No sabes cuándo necesitarás comprar algo urgente (un barco para una misión naval, una armadura para un combate importante).
- Guarda un colchón de emergencia. Si tienes 1000 B., no gastes 950. Deja siempre algo.

**Los consumibles son tu mejor inversión inicial.**
- Pociones de curación, herramientas, utilidades. Cuestan poco, se acumulan, y siempre tienen uso.
- Una poción que cura 20 PV puede salvar a tu personaje en un combate ajustado.

**Los objetos únicos (armas, armaduras) son compromisos a largo plazo.**
- Antes de comprar un arma de 500 B., pregúntate: ¿realmente necesito esta arma? ¿O puedo conseguir algo similar por misión?
- Una vez comprada, si la vendes, pierdes 250 B. (50%). Asegúrate de que la vas a usar.

**Vender para financiar otra compra: la estrategia del reemplazo.**
- Si tienes un equipo viejo que ya no usas, véndelo y usa los berries para comprar algo mejor.
- Ejemplo: Tienes una "Espada de hierro" (100 B., reventa 50 B.). Quieres una "Espada de acero" (300 B.). Vende la de hierro, paga 250 B. netos.

### 11.2 Comprando en la Tienda

**Revisa todas las categorías.** No te quedes solo en Armería. A veces en Útiles hay herramientas que marcan la diferencia (kit de supervivencia, mapa, brújula).

**Los barcos son inversiones grandes.** Un barco abre oportunidades narrativas (navegar, explorar islas, transportar equipo). Pero cuesta caro y su reventa duele. Piensa si tu personaje realmente va a navegar.

**Los compañeros (npc_menor) son personajes secundarios.**
- No son "mascotas" descartables. Son NPCs que interactúan con el mundo.
- Un compañero bien elegido puede aportar mucho a tu narrativa. Uno mal elegido es un gasto de berries que luego no podrás recuperar.

**No compres por impulso.** La tienda no tiene ofertas flash ni tiempo limitado. Si ves algo que te gusta, tómate un día para pensarlo.

### 11.3 Vendiendo en la Tienda

**Asume que perderás la mitad.** Es la regla. Si no estás dispuesto a perder el 50%, no compres.

**Vende en bloque los consumibles que sobran.** Si tienes 20 pociones de curación básica y solo usas 1 por combate, vende 15. Te sobran 5, y recuperas algo de berries.

**Los objetos únicos no se venden a la ligera.**
- Una espada que has usado en 10 combates tiene valor narrativo, no solo económico.
- Pregúntate: "¿Mi personaje vendería su espada?" Si la respuesta es no, no la vendas solo por berries.

**Si vendes todo tu inventario, algo estás haciendo mal.**
- Los berries son un medio, no un fin. Tener objetos es mejor que tener berries.
- Un personaje con 5000 B. y 0 objetos es un personaje sin recursos. Un personaje con 500 B. y 10 objetos está preparado.

### 11.4 Errores Comunes

- **"Compré un barco y nunca navegué."** Los barcos son para tramas navales. Si tu personaje es de tierra adentro, quizá no necesites uno.
- **"Vendí mi espada inicial porque necesitaba berries."** La espada inicial probablemente no vale mucho. Pero si la vendiste, te quedas sin arma y tienes que comprar otra (perdiendo 50% otra vez).
- **"Gasté todos mis berries en pociones."** 50 pociones de curación básica son 50 usos, sí. Pero también son 50 ítems en tu inventario. ¿Los vas a usar todos?
- **"No sabía que había categorías en la tienda."** Siempre revisa las 4 pestañas. A veces el objeto que buscas está en una categoría inesperada.
- **"Compré sin ver la carta completa."** Usa la vista previa (clic en la carta) para ver stats, dados, coste PE, efectos. No compres solo por el nombre bonito.

---

## 12. Consejos para Staff

### 12.1 Gestionando el Catálogo

**Curá el catálogo con intención narrativa.**
- ¿Qué objetos existen en el mundo? ¿Qué tiene sentido que se vendan en un bazar?
- No pongas todo lo comerciable en la tienda solo porque se pueda. Un catálogo pequeño y coherente es mejor que uno gigante y sin sentido.

**Balancea por rango y rareza.**
- Un personaje rango C no debería poder comprar un barco SS, aunque tenga los berries.
- Usa el precio como barrera: objetos poderosos deberían ser muy caros.
- Si un objeto es demasiado barato para su poder, los jugadores lo comprarán masivamente y romperá el balance.

**Rotación estacional/temática.**
- Considera cambiar el catálogo cada temporada o arco narrativo: "este mes, la tienda tiene descuento en barcos" o "llegan objetos raros de la isla X".
- La rotación mantiene la economía dinámica y da razones para que los jugadores estén atentos.

**Monitorea qué se vende más.**
- Usa los logs de compra para ver qué objetos son populares.
- Si algo se vende mucho y es muy barato, quizá debería subir de precio.
- Si algo no se vende nunca, quizá el precio es demasiado alto o el objeto no es atractivo.

### 12.2 Manteniendo la Economía

**Controla la inflación.**
- Los berries entran al sistema por misiones, eventos, y recompensas. Si entran más rápido de lo que se gastan, los precios dejan de ser significativos.
- El sink del 50% en ventas ayuda, pero no es suficiente si hay demasiadas fuentes de berries.
- Ajusta las recompensas de misiones: 100 B. por misión pequeña, 500 B. por misión grande, 2000+ B. por arcos completos.

**Usa los berries como herramienta narrativa, no solo económica.**
- "El barco está averiado. Necesitas 300 B. en materiales para repararlo."
- "El informante pide 200 B. por la ubicación del tesoro."
- "El noble te ofrece 1000 B. si recuperas su reliquia."

**Precios de referencia para objetos comunes:**

| Tipo de objeto | Precio sugerido | Reventa (50%) |
|---------------|----------------|---------------|
| Consumible básico (poción) | 25-50 B. | 12-25 B. |
| Consumible avanzado | 100-200 B. | 50-100 B. |
| Arma rango C | 100-300 B. | 50-150 B. |
| Arma rango B | 300-800 B. | 150-400 B. |
| Arma rango A | 800-2000 B. | 400-1000 B. |
| Arma rango S | 2000-5000 B. | 1000-2500 B. |
| Barco pequeño | 500-1000 B. | 250-500 B. |
| Barco mediano | 1000-3000 B. | 500-1500 B. |
| Barco grande | 3000-8000 B. | 1500-4000 B. |
| Compañero común | 200-500 B. | 100-250 B. |
| Compañero raro | 500-1500 B. | 250-750 B. |

### 12.3 Atendiendo problemas de jugadores

**"Perdí mis berries por un bug."**
1. Revisa los logs (`game_log_action` con tipo `tienda_compra` o `tienda_venta`).
2. Si el log muestra la compra pero el personaje no tiene la carta, el bug fue en el paso 2.
3. Solución: Asigna la carta manualmente o reembolsa los berries vía UPDATE directo.

**"Compré algo por error, ¿puedo recuperar mis berries?"**
- Política recomendada: No. La venta al 50% es la herramienta de reversión. Si devuelves el 100%, anulas el sink económico.
- Excepción: Si fue un error técnico (compró dos veces el mismo único), puedes borrar el duplicado y reembolsar.

**"Quiero vender algo que no aparece en la lista de venta."**
- Revisa si la carta tiene `cost_berries > 0`. Si no, no tiene valor de reventa.
- Si el staff quiere que tenga valor, edita `cost_berries` en la carta.

**"El catálogo está vacío."**
- Revisa que haya cartas con `in_shop = 1`, `cost_berries > 0`, y `card_type IN ('equipo', 'npc_menor', 'barco')`.
- Usa el panel de staff para añadir cartas.

### 12.4 Seguridad

**Nunca edites `berries` directamente en DB sin registrar.**
- Siempre haz un UPDATE con comentario o añade un log manual.
- Si modificas berries sin registro y alguien reclama, no tendrás cómo demostrar qué pasó.

**Los precios de reventa se calculan en runtime.**
- No almacenes "precio de reventa" en DB. Siempre se calcula como `floor(cost_berries * 0.5)`.
- Si cambias `cost_berries`, el precio de reventa cambia automáticamente.

**Protege `zona_staff_tienda.php`.**
- Solo staff level 3. No bajes este requisito.
- Un moderador (level 1) añadiendo cartas sin control puede desbalancear la economía.

**Valida siempre `in_shop` y `card_type` en el servidor.**
- El JS del frontend filtra, pero el backend siempre revalida.
- Un atacante no puede comprar una carta que no está en la tienda manipulando el POST.

---

## 13. Guía de Troubleshooting

### 13.1 Errores en Compra

| Error | Causa | Solución |
|-------|-------|----------|
| "Parámetros inválidos" | `character_id` ≤ 0 o `cart` vacío | Verificar payload JS, personaje activo seleccionado |
| "Personaje no encontrado" | El personaje no existe o no pertenece al usuario | Verificar `character_id`, ownership |
| "Personaje debe estar aprobado" | `status !== 'aprobada'` | Staff aprobar personaje primero |
| "Uno de los objetos no está a la venta" | `in_shop` = 0 o carta no existe | Actualizar catálogo, verificar ID |
| "Tipo de carta no comerciable" | `card_type` no es equipo/npc_menor/barco | El sistema no permite comerciar ese tipo |
| "Precio inválido" | `cost_berries` ≤ 0 | Staff asignar precio a la carta |
| "Ya posees el objeto único" | No consumible ya en inventario | Solo se permite 1 copia de únicos |
| "Saldo insuficiente" | `berries < total_cost` | Conseguir más berries o reducir carrito |
| Error 500 en compra | Problema de DB | Revisar logs de errores PHP/MySQL |

### 13.2 Errores en Venta

| Error | Causa | Solución |
|-------|-------|----------|
| "Parámetros inválidos" | `card_id` ≤ 0 o `cantidad` ≤ 0 | Verificar payload |
| "Objeto no existe o sin valor de reventa" | `cost_berries` = 0 o carta borrada | Verificar carta en DB |
| "Tipo no comerciable" | `card_type` no permitido | Sistema no permite vender ese tipo |
| "No posees este objeto" | No hay registro en `game_character_cards` | Verificar inventario |
| "Solo posees N unidades" | `cantidad > owned_cantidad` | Ajustar cantidad a vender |

### 13.3 Errores de Staff

| Error | Causa | Solución |
|-------|-------|----------|
| "Permisos insuficientes" | `staff_level` < 3 | Verificar personaje activo con nivel de staff |
| "Carta no encontrada o no comerciable" | ID inválido o tipo no permitido | Verificar carta en DB |
| "Categoría inválida" | `shop_category` no está en lista blanca | Usar: utiles, armeria, naval, mascotas |
| "Nada que actualizar" | Payload sin `in_shop` ni `shop_category` | Enviar al menos un campo |

### 13.4 Problemas de Frontend

| Síntoma | Causa probable | Solución |
|---------|---------------|----------|
| El carrito no se abre | JS bloqueado por error previo | Revisar consola, recargar página |
| "Confirmar compra" no se habilita | Carrito vacío o personaje no aprobado | Añadir items o aprobar personaje |
| El saldo no se actualiza tras comprar | Error en respuesta AJAX | Recargar página, verificar DB |
| La vista previa de carta no carga | `cardsById` no tiene la carta | Verificar `TIENDA_CONFIG` |
| El panel de venta está vacío | Sin cartas comerciables en inventario | Conseguir objetos primero |
| Modal de añadir carta no abre | JS de zona_staff_tienda.js no cargó | Verificar script, ruta de archivo |
| Búsqueda en catálogo no filtra | JS filter no funciona | Verificar tienda.js, data-atributos |

### 13.5 Depuración

Para depurar problemas de tienda, revisa en orden:

1. **Consola del navegador:** Errores JS, peticiones AJAX fallidas, respuestas del servidor.
2. **Logs de PHP:** Errores 500, excepciones, consultas SQL mal formadas.
3. **Logs de MySQL:** Queries lentas, deadlocks, errores de integridad.
4. **game_log_action:** Busca entradas `tienda_compra` y `tienda_venta` para el personaje afectado.
5. **DB directa:** Verifica manualmente `game_personajes.berries` y `game_character_cards`.

```sql
-- Verificar saldo de un personaje
SELECT id, name, berries FROM mybb_game_personajes WHERE id = {character_id};

-- Verificar inventario de un personaje
SELECT c.id, c.name, c.cost_berries, cc.cantidad
FROM mybb_game_character_cards cc
JOIN mybb_game_cards c ON cc.card_id = c.id
WHERE cc.character_id = {character_id};

-- Verificar cartas en tienda
SELECT id, name, cost_berries, in_shop, shop_category
FROM mybb_game_cards
WHERE in_shop = 1
ORDER BY shop_category, name;

-- Verificar logs de compra/venta
-- (ejemplo: buscar en game_log_action si existe tabla, o en el sistema de logs del foro)
```

---

## APÉNDICE A: Archivos del Subsistema

```
back/forum/game/
├── ajax/
│   ├── tienda_comprar.php         # POST — Ejecuta compra
│   ├── tienda_vender.php          # POST — Ejecuta venta
│   ├── shop_catalog_list.php      # GET — Lista catálogo activo o pool
│   ├── shop_catalog_update.php    # POST — Añade/quita del catálogo
│   └── shop_pool_list.php         # GET — Lista cartas disponibles (pool)
├── public/
│   ├── tienda.php                 # Página principal de la tienda
│   └── zona_staff_tienda.php      # Panel de gestión para staff
├── sql/
│   └── install_schema_fragments.php  # Schema: berries (line 108), cards (lines 220-235)
└── src/Infrastructure/Persistence/
    └── PersonajeRepository.php    # findByIdForUser()

back/forum/jscripts/game/
├── tienda.js                      # Lógica de carrito, compra, venta
├── zona_staff_tienda.js           # Lógica de gestión de catálogo
└── rpg_modal.js                   # Modales de vista previa

Guias/
├── MAESTRO_SISTEMAS_RPG.md       # Sección 20: Economía
└── sistemas/
    └── 20-economia.md             # Este documento
```

---

## APÉNDICE B: Referencia Rápida de SQL

### Obtener saldo de berries del personaje activo
```php
$char_q = $db->query("SELECT id, name, berries, status
    FROM {$prefix}game_personajes WHERE id = {$char_id} LIMIT 1");
```

### Catálogo de tienda
```sql
SELECT id, name, card_type, `rank`, image_url, description,
       cost_berries, shop_category, effects_json
FROM game_cards
WHERE in_shop = 1 AND cost_berries > 0
  AND card_type IN ('equipo', 'npc_menor', 'barco')
ORDER BY shop_category ASC, name ASC;
```

### Inventario vendible del personaje
```sql
SELECT c.id, c.name, c.card_type, c.cost_berries, cc.cantidad
FROM game_character_cards cc
JOIN game_cards c ON cc.card_id = c.id
WHERE cc.character_id = {char_id}
  AND c.card_type IN ('equipo', 'npc_menor', 'barco')
  AND c.cost_berries > 0
ORDER BY c.name ASC;
```

### Deducir berries (compra)
```sql
UPDATE game_personajes SET berries = berries - {total}
WHERE id = {character_id};
```

### Sumar berries (venta)
```sql
UPDATE game_personajes SET berries = berries + {total}
WHERE id = {character_id};
```

### Insertar carta comprada
```sql
INSERT INTO game_character_cards (character_id, card_id, current_rank, assigned_by, cantidad)
VALUES ({character_id}, {card_id}, '{rank}', {uid}, {qty})
ON DUPLICATE KEY UPDATE cantidad = cantidad + {qty};
```

### Eliminar carta vendida (venta total)
```sql
DELETE FROM game_character_cards
WHERE character_id = {character_id} AND card_id = {card_id};
```

### Decrementar carta vendida (venta parcial)
```sql
UPDATE game_character_cards SET cantidad = {remaining}
WHERE character_id = {character_id} AND card_id = {card_id};
```

### Staff: añadir carta al catálogo
```sql
UPDATE game_cards SET in_shop = 1, shop_category = '{cat}'
WHERE id = {card_id};
```

### Staff: quitar carta del catálogo
```sql
UPDATE game_cards SET in_shop = 0
WHERE id = {card_id};
```

### Staff: listar pool disponible
```sql
SELECT id, name, card_type, `rank`, cost_berries
FROM game_cards
WHERE card_type IN ('equipo', 'npc_menor', 'barco')
  AND cost_berries > 0 AND in_shop = 0
ORDER BY name ASC;
```

---

*Fin del documento — Guía completa del Sistema de Economía v1.0*
*Generado desde: `Guias/sistemas/20-economia.md`*
*Referencia: `Guias/MAESTRO_SISTEMAS_RPG.md` — Sección 20*
