# 6. Inventario y Equipamiento

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 6
> **Propósito:** Documentar exhaustivamente el subsistema de inventario y equipamiento: slots, peso, snapshots en posts, equipamiento de cards, consumibles, gestión de ítems, modelo de datos, implementación PHP, flujos de compra/venta, diseño de UX — y **por qué** cada decisión existe, cómo impacta en el RPG, y consejos para jugadores y staff.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [Slots de Inventario](#2-slots-de-inventario)
3. [Peso y Capacidad de Carga](#3-peso-y-capacidad-de-carga)
4. [Snapshot en Posts (`equipped_snapshot_json`)](#4-snapshot-en-posts)
5. [Equipamiento de Cards](#5-equipamiento-de-cards)
6. [Inventario: Vista y Gestión](#6-inventario-vista-y-gestión)
7. [Consumibles](#7-consumibles)
8. [Compra y Venta en Tienda](#8-compra-y-venta-en-tienda)
9. [Database Schema](#9-database-schema)
10. [Implementación PHP](#10-implementación-php)
11. [Implementación JavaScript](#11-implementación-javascript)
12. [AJAX Endpoints — Catálogo](#12-ajax-endpoints)
13. [Validaciones y Seguridad](#13-validaciones-y-seguridad)
14. [Filosofía de Diseño](#14-filosofía-de-diseño)
15. [Consejos para Jugadores](#15-consejos-para-jugadores)
16. [Consejos para Staff](#16-consejos-para-staff)

---

## 1. Arquitectura General

### 1.1 Qué es el Inventario

El inventario es el sistema que gestiona **qué objetos físicos porta un personaje**. No todas las cards que un personaje posee están "disponibles" en todo momento — solo las que tiene equipadas en sus slots de inventario pueden usarse en combate (con excepción de consumibles).

El inventario es la capa intermedia entre:
- **Posesión** (`game_character_cards`): todas las cards que el personaje ha adquirido.
- **Uso en posts** (`game_post_cards`): las cards que efectivamente se juegan.

Sin estar equipada, una card de tipo `equipo`, `npc_menor` o `barco` no puede usarse en posts.

### 1.2 Capas del Subsistema

```
┌──────────────────────────────────────────────────────────────────────┐
│                         CLIENTE (Navegador)                           │
│  ┌──────────────────────┐  ┌────────────────────────────────────┐    │
│  │ personaje_inventory  │  │ tienda.js / shop_catalog_list.js   │    │
│  │ .js (equip/unequip)  │  │ (compra/venta)                     │    │
│  └──────────┬───────────┘  └────────────────┬───────────────────┘    │
│             │                                │                        │
│             ▼                                ▼                        │
│  ┌───────────────────────────────────────────────────────────────────┐│
│  │              AJAX (game/ajax/*.php)                               ││
│  │  inventory_get.php | inventory_toggle.php | tienda_comprar.php   ││
│  │  tienda_vender.php | tienda_card_detail.php                      ││
│  └──────────────────────────────┬────────────────────────────────────┘│
└─────────────────────────────────┼────────────────────────────────────┘
                                  │ HTTP POST/GET + JSON
┌─────────────────────────────────┼────────────────────────────────────┐
│  ┌──────────────────────────────▼───────────────────────────────────┐│
│  │              PHP — CAPA DE APLICACIÓN                             ││
│  │  inventory_helpers.php (game_get_equipped_card_ids, etc.)         ││
│  │  game_postcharacter.php (snapshot, decremento consumibles)       ││
│  │  stat_helpers.php (build_stat_context para calcular CC)          ││
│  └──────────────────────────────────────────────────────────────────┘│
│                                  │                                    │
│                                  ▼                                    │
│  ┌──────────────────────────────────────────────────────────────────┐│
│  │           MySQL (MyBB + tablas game_*)                           ││
│  │  game_character_inventory | game_character_cards | game_cards   ││
│  │  game_post_characters (equipped_snapshot_json)                   ││
│  └──────────────────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────────────┘
```

### 1.3 Filosofía de la Arquitectura

**¿Por qué separar posesión (`game_character_cards`) de equipamiento (`game_character_inventory`)?**

```
game_character_cards (posesión)         game_character_inventory (equipado)
┌─────────────────────────────┐         ┌─────────────────────────────┐
│ character_id: 1             │         │ character_id: 1             │
│ card_id: 42 (Katana)        │         │ card_id: 42 (Katana)        │
│ card_id: 55 (Escudo)        │  →equip │ slot_type: carga            │
│ card_id: 60 (Poción)        │         │ ─────────────────────────── │
│ card_id: 70 (Nuevo Mundo)   │         │ card_id: 70 (Nuevo Mundo)   │
│ card_id: 80 (Lobo Comp.)    │         │ slot_type: barco            │
└─────────────────────────────┘         │ card_id: 80 (Lobo Comp.)    │
                                         │ slot_type: companero        │
                                         └─────────────────────────────┘
```

1. **Un personaje puede poseer muchas cards pero solo equipar unas pocas.** El límite de carga fuerza decisiones estratégicas.
2. **El equipamiento cambia según el contexto.** Un personaje puede tener 5 armas en posesión pero solo equipar 2 en un hilo.
3. **Snapshot en posts captura el equipamiento en un momento dado.** Si el equipamiento cambiara en la misma tabla que la posesión, el snapshot sería irrelevante.
4. **La posesión persiste aunque se desequipe.** Si vendes un arma, se borra de `game_character_cards`. Si solo la desequipas, sigue en posesión.

**¿Por qué 3 tipos de slot y no más?**

Porque los slots representan las tres categorías fundamentales de "cosas que un personaje lleva consigo":
- `carga`: objetos físicos que porta (armas, armaduras, herramientas).
- `companero`: seres vivos que le acompañan (NPCs menores, bestias).
- `barco`: el vehículo/vessel que posee.

Cada slot tiene reglas de límite diferentes porque la naturaleza de lo que almacena es diferente. No hay slot "mochila" o "bolsillo" porque eso sería redundante con `carga`.

### 1.4 Principios de Diseño

1. **Equipamiento obligatorio para uso:** Cards de tipo `equipo`, `npc_menor`, `barco` deben estar equipadas para usarse en posts (excepto consumibles).
2. **Límites por slot:** No puedes equipar infinitos objetos. La capacidad de carga (CC) es el principal限制了.
3. **Snapshot por post:** Cada post congela el estado del equipamiento para auditoría.
4. **Peso como recurso:** El peso (`peso`) de cada card es un coste de oportunidad: equipar un arma pesada te da poder pero consume CC que podrías usar para otras cosas.
5. **Consumibles son excepción:** No requieren equiparse, se usan directamente desde inventario, y se decrementan al usarse.

---

## 2. Slots de Inventario

### 2.1 Los 3 Slots

| Slot | Propósito | Tipos de card que almacena | Límite |
|------|-----------|---------------------------|--------|
| `carga` | Equipo, armas, herramientas, consumibles portados | `equipo` | `5 + floor(FUE_valor / 4)` + perks |
| `companero` | NPCs menores activos (bestias, subordinados) | `npc_menor` | 1 (2 con perk `g_vinculo_companero`) |
| `barco` | El barco activo del PJ | `barco` | 1 |

### 2.2 Slot `carga` — Carga Portada

**Qué almacena:** Todas las cards de tipo `equipo`: armas, armaduras, accesorios, herramientas, consumibles.

**Límite:** La Capacidad de Carga (CC) es un número que se calcula dinámicamente. No es un conteo de items sino una **suma de pesos**: cada card equipada en `carga` consume `peso` CC. La CC total disponible es:

```php
$cc_max = 5 + (int)floor($fue / 4) + ($has_carga_perk ? 3 : 0);
```

**Mecánica de peso:** El peso no es "número de items" sino "unidades de carga". Un arma pequeña (cuchillo, pistola) pesa 1 CC. Un arma grande (gran espada, rifle) pesa 3 CC. Una armadura pesada pesa 4 CC. Esto permite tener 1 item pesado o varios ligeros.

**Conteo vs Peso:**
- Un personaje con CC=12 puede tener: 1 armadura pesada (4) + 1 espada (2) + 1 escudo (2) + 1 pistola (1) + 1 herramienta (1) + pociones (0) = 6 items, 10 CC.
- Pero NO puede tener: 4 armaduras pesadas (4×4=16 → excede CC=12).

**Perks de linaje que afectan CC:**
- `g_capacidad_carga` (general): +3 CC.
- `g_carga_extra` (general): +3 CC.
- `g_vinculo_companero` (general): NO afecta CC, afecta slot compañero.

La detección en código:
```php
$has_carga_perk = in_array('g_capacidad_carga', $general_ids)
    || in_array('g_capacidad_carga', $racial_ids)
    || in_array('g_carga_extra', $general_ids);
```

### 2.3 Slot `companero` — Compañero Activo

**Qué almacena:** Cards de tipo `npc_menor`. Representa bestias, mascotas, subordinados o aliados que acompañan al personaje.

**Límite:** 1 compañero por defecto. El perk de linaje `g_vinculo_companero` (del árbol general o racial) amplía a 2.

**Mecánica:**
- Solo el compañero equipado en este slot puede usarse en posts.
- Si el personaje tiene múltiples NPCs menores en posesión, solo uno (o dos) pueden estar activos.
- El compañero se juega en posts seleccionando una de sus acciones predefinidas (ver `05-cards.md` sección 4.5).
- Si el compañero muere en combate narrativo, el staff puede desequiparlo y retirarlo del inventario.

**Casos borde:**
- Un personaje con 2 slots de compañero equipa 2 NPCs. Ambos pueden actuar en el mismo post (sujeto a PA declarado).
- Los NPCs menores en compañero NO ocupan carga (peso = 0 en slot compañero). El peso solo aplica a `carga`.

### 2.4 Slot `barco` — Barco Activo

**Qué almacena:** Una card de tipo `barco`. Representa la embarcación principal del personaje o tripulación.

**Límite:** Siempre 1. No hay perks que lo amplíen.

**Mecánica:**
- El barco no se "juega" en posts de combate cuerpo a cuerpo, pero sí se usa en el sistema de navegación (`navigation_process.php`).
- Para usar un barco en navegación, debe estar equipado en este slot.
- Los atributos del barco (velocidad, maniobrabilidad, resistencia) se leen desde `game_cards.effects_json` para cálculos de navegación.

**Validación en navegación:**
```php
// En navigation_process.php, línea 71-72:
$equipped = $db->query("SELECT 1 FROM {$prefix}game_character_inventory
    WHERE character_id = {$characterId} AND card_id = {$shipCardId} AND slot_type = 'barco' LIMIT 1");
```

### 2.5 ¿Qué cards NO requieren slot?

| Tipo de card | ¿Requiere equiparse? | Razón |
|-------------|:-------------------:|-------|
| `tecnica` | No | Son habilidades aprendidas, siempre disponibles |
| `haki` | No | Es poder interno, siempre disponible |
| `akuma_no_mi` | No | Es inherente al personaje, siempre activa |
| `equipo` (no consumible) | Sí | Debe portarse físicamente |
| `equipo` (consumible) | No | Puede usarse directamente desde inventario |
| `npc_menor` | Sí | Debe estar activo para acompañar |
| `barco` | Sí | Debe ser el barco activo |

La función que determina esto:

```php
function game_card_requires_equipped_slot(string $cardType, bool $isConsumible = false): bool
{
    if ($isConsumible) {
        return false;
    }
    return in_array($cardType, ['equipo', 'npc_menor', 'barco'], true);
}
```

---

## 3. Peso y Capacidad de Carga

### 3.1 El campo `peso` en `game_cards`

Cada card en el catálogo tiene un campo `peso` (INT, default 1) que define cuántas unidades de carga consume al equiparse en el slot `carga`.

```sql
peso INT NOT NULL DEFAULT 1,
```

**Valores típicos:**

| Tipo de card | Peso típico | Ejemplo |
|-------------|:-----------:|---------|
| Arma pequeña (cuchillo, daga, pistola) | 1 | "Daga de acero" |
| Arma mediana (espada, lanza, hacha) | 2 | "Katana de acero" |
| Arma grande (gran espada, rifle, cañón portátil) | 3 | "Espada de dos manos" |
| Arma exótica / especial | 2-4 | "Clima Tact" |
| Armadura ligera (cuero, tela) | 2 | "Chaleco de cuero" |
| Armadura pesada (acero, placas) | 4 | "Armadura de placas" |
| Escudo pequeño | 1 | "Escudo de buckler" |
| Escudo grande | 3 | "Escudo de torre" |
| Herramienta (kit médico, herramientas) | 1 | "Kit de primeros auxilios" |
| Consumible (poción, comida, material) | 0 | "Poción curativa" |
| Accesorio (joya, amuleto) | 0-1 | "Amuleto protector" |
| NPC menor | 0 (usa slot compañero) | "Lobo domesticado" |
| Barco | 0 (usa slot barco) | "Bergantín" |

**¿Por qué los consumibles pesan 0?** Porque de otro modo, un personaje con 10 pociones no podría equipar nada más. Los consumibles están pensados para llevarse en cantidad sin penalizar el equipo de combate. El límite real de consumibles es económico (cuestan Berries) y narrativo (solo tienes los que compraste/creaste).

### 3.2 Cálculo de Capacidad de Carga (CC)

La CC máxima se calcula exclusivamente del stat FUE (valor numérico, no rango):

```php
$cc_max = 5 + (int)floor($fue / 4) + ($has_carga_perk ? 3 : 0);
```

Donde `$fue` es el **valor** del stat FUE (obtenido de `game_build_stat_context()`) — NO el rango D-SS. La conversión de rango a valor usa `StatScale`:

| Rango FUE | Valor FUE | CC base | CC con perk |
|:---------:|:---------:|:-------:|:-----------:|
| 1 (D) | 4 | 6 | 9 |
| 2 (C) | 8 | 7 | 10 |
| 3 (B) | 15 | 8 | 11 |
| 4 (A) | 25 | 11 | 14 |
| 5 (S) | 40 | 15 | 18 |
| 6 (SS) | 60 | 20 | 23 |

**Desglose de la fórmula:**
- **5 base:** Todo personaje puede cargar al menos 5 unidades de peso sin importar su fuerza. Esto asegura que personajes con FUE 1 pueden llevar un arma + armadura ligera + algunos consumibles.
- **floor(FUE / 4):** El stat FUE escala cuadráticamente (el valor sube más que el rango). Un personaje con FUE 6 (SS, valor 60) gana 15 CC extra. Esto hace que la fuerza sea muy relevante para equipamiento.
- **Perk +3:** Los perks de carga son significativos (+3 CC equivale a ~12 puntos de FUE en valor). Son una inversión de linaje valiosa.

**Cálculo en inventory_get.php:**
```php
$fue = game_build_stat_context(is_array($stats) ? $stats : [], $raceNameInv)['values']['fue'] ?? 4;
$has_carga_perk = in_array('g_capacidad_carga', $general_ids)
    || in_array('g_capacidad_carga', $racial_ids)
    || in_array('g_carga_extra', $general_ids);
$cc_max = 5 + (int)floor($fue / 4) + ($has_carga_perk ? 3 : 0);
```

**Cálculo de CC usado:**
```php
$cc_used = 0;
while ($r = $db->fetch_array($current_q)) {
    if ($r['slot_type'] === 'carga') {
        $cc_used += (int)$r['peso'];
    }
}
```

Solo los items en slot `carga` contribuyen al CC usado. Los compañeros y barcos tienen sus propios contadores.

### 3.3 ¿Qué pasa cuando se supera el límite?

El sistema **impide equipar** si la operación excede el límite:

```php
if ($cc_used + $peso > $cc_max) {
    GameAjax::fail(400, "Capacidad de Carga insuficiente. Consumo: {$peso} CC (Límite: {$cc_used}/{$cc_max} CC).");
}
```

**NO hay penalización por exceso de carga pasivo** — el sistema no revisa CC en cada post ni aplica debuffs automáticos por sobrecarga. La validación ocurre solo al equipar. Esto es intencional: si un personaje ya tiene items equipados y luego pierde fuerza (ej: por un debuff que baja FUE temporal), no se le desequipan automáticamente — eso sería disruptivo. El staff puede intervenir si considera abusivo.

### 3.4 Filosofía del Peso

**¿Por qué limitar el peso del inventario?**

1. **Decisión estratégica:** El jugador debe elegir qué llevar. ¿Una armadura pesada (4 CC) + espada (2 CC) + escudo (2 CC) = 8 CC? O ¿dos armas ligeras (2 CC) + herramientas (2 CC) + pociones (0 CC) = 4 CC? Cada elección tiene consecuencias en combate.

2. **Realismo narrativo:** Un personaje no puede llevar 10 espadas, 5 armaduras y 20 pociones a la vez. Tiene sentido que haya un límite físico.

3. **Valor del stat FUE:** Si no hubiera límite de carga, FUE sería un stat mucho menos relevante. Al vincular CC con FUE, damos peso (nunca mejor dicho) a la fuerza física.

4. **Prevención de abusos:** Sin límite, un personaje podría equipar 30 armas y usar la más conveniente en cada post sin coste de preparación.

**¿Por qué el peso se copia a `game_character_inventory` en lugar de leerse siempre de `game_cards`?**

```sql
peso INT NOT NULL DEFAULT 0,  -- en game_character_inventory
```

Si el staff cambia el peso de una card en el catálogo (ej: "Esta espada pesaba 2 pero debería pesar 3"), los personajes que ya la tienen equipada no se ven afectados hasta que la desequipen y re-equipen. Esto evita cambios disruptivos en medio de un hilo.

---

## 4. Snapshot en Posts (`equipped_snapshot_json`)

### 4.1 Qué es el Snapshot

Cuando un personaje crea un post, el sistema guarda una **instantánea** (snapshot) de los IDs de todas las cards equipadas en ese momento. Este snapshot se almacena en `game_post_characters.equipped_snapshot_json`.

**Estructura:**
```json
[42, 55, 70, 80]
```

Es simplemente un array JSON de `card_id`s. No incluye metadata adicional — solo los IDs.

### 4.2 Por qué existen los Snapshots

**Problema que resuelve:** Sin snapshot, si un jugador usaba un arma en un post y luego la desequipaba, el staff no podría verificar que efectivamente la tenía equipada cuando hizo el post. El jugador podría:
1. Hacer un post declarando "Ataco con mi Espada Legendaria".
2. Después del post, equipar la Espada Legendaria.
3. Reclamar que siempre la tuvo.

El snapshot congela el estado del equipamiento en el momento exacto del post, proporcionando una prueba inmutable.

### 4.3 Cómo se Guarda

El snapshot se guarda en el hook `datahandler_post_insert_post_end` del plugin `game_postcharacter.php`:

```php
function game_postcharacter_save_equipped_snapshot(int $pid, int $cid): array
{
    game_postcharacter_ensure_inventory_helpers();
    global $db;

    // Obtener IDs de cards equipadas
    $ids = function_exists('game_get_equipped_card_ids')
        ? game_get_equipped_card_ids($cid)
        : [];

    if (!game_postcharacter_equipped_snapshot_ready()) {
        return $ids;
    }

    $prefix = TABLE_PREFIX;
    $json = json_encode(array_values($ids), JSON_UNESCAPED_UNICODE);
    $esc = $db->escape_string($json);

    // UPDATE directo sobre el registro game_post_characters ya insertado
    $db->write_query(
        "UPDATE {$prefix}game_post_characters
         SET equipped_snapshot_json = '{$esc}'
         WHERE post_id = {$pid} AND character_id = {$cid}"
    );

    return $ids;
}
```

**Flujo de llamada:**
```
1. MyBB hook: datahandler_post_insert_post_end
2. game_postcharacter_save_post()
   a. INSERT INTO game_post_characters (post_id, character_id, ...)
   b. game_postcharacter_save_equipped_snapshot()
      → game_get_equipped_card_ids()
      → UPDATE game_post_characters SET equipped_snapshot_json = '[...]'
   c. game_postcharacter_process_cards() — verifica cards contra snapshot
```

### 4.4 Cómo se Lee

Para verificar si una card estaba equipada en un post dado:

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

    // Fallback: si no hay snapshot, usar equipamiento actual
    return function_exists('game_get_equipped_card_ids')
        ? game_get_equipped_card_ids($cid)
        : [];
}
```

### 4.5 Validación en Procesamiento de Cards

Cuando se procesan las cards jugadas en un post (`game_postcharacter_process_card_entry`), se verifica contra los IDs del snapshot:

```php
function game_postcharacter_card_allowed_in_post(
    string $cardType,
    int $cardId,
    array $equippedIds,
    bool $isConsumible = false
): bool {
    game_postcharacter_ensure_inventory_helpers();

    // Consumibles no requieren equiparse
    if (!function_exists('game_card_requires_equipped_slot')
        || !game_card_requires_equipped_slot($cardType, $isConsumible)
    ) {
        return true;
    }

    // Verificar que la card está en la lista de equipadas
    $allowed = in_array($cardId, $equippedIds, true);

    if (!$allowed && function_exists('game_log_equipped_debug')) {
        game_log_equipped_debug('card_rejected', [
            'card_id' => $cardId,
            'card_type' => $cardType,
            'equipped_ids' => $equippedIds,
            'is_consumible' => $isConsumible,
        ]);
    }

    return $allowed;
}
```

### 4.6 Migración: Añadir la columna

La columna `equipped_snapshot_json` se añadió mediante una migración dedicada:

```php
// migrate_post_equipped_snapshot.php
if (!$db->field_exists('equipped_snapshot_json', 'game_post_characters')) {
    $db->write_query("ALTER TABLE {$table} ADD equipped_snapshot_json TEXT DEFAULT NULL AFTER hidden_actions_json");
}
```

### 4.7 Filosofía del Snapshot

**¿Por qué guardar solo IDs y no el objeto completo?**

- **Tamaño:** Un array de enteros ocupa muy poco. Si guardáramos el objeto completo (nombre, tipo, peso, etc.), cada post añadiría KB innecesarios.
- **Consistencia:** Los detalles de la card (nombre, tipo) no cambian en el catálogo. Si necesitas saber qué card es el ID 42, haces JOIN con `game_cards`. No necesitas duplicar esa información.
- **Suficiencia:** Para la auditoría, solo necesitas saber "¿estaba equipada la card X cuando se hizo el post Y?". Los IDs son suficientes.

**¿Por qué no es el snapshot la única fuente de verdad?**
El snapshot es una foto del equipamiento en el momento del post. Pero el historial de cards jugadas (`game_post_cards`) registra qué se usó realmente y con qué resultado. Ambos se complementan:
- Snapshot → "¿qué podía usar?"
- `game_post_cards` → "¿qué usó realmente?"

**¿Los snapshots son inmutables?**
Sí, una vez escritos no se modifican. El UPDATE solo ocurre en la creación del post. Si alguien edita el post después, el snapshot no se actualiza. Esto es intencional: la foto es del momento del post, no del momento de la edición.

---

## 5. Equipamiento de Cards

### 5.1 Endpoint: `inventory_toggle.php`

Este endpoint único maneja tanto equipar como desequipar. Es un toggle: si la card ya está equipada, la desequipa; si no, la equipa (sujeto a validaciones).

**Request:**
```json
{
    "character_id": 1,
    "card_id": 42
}
```

**Response (equipar):**
```json
{
    "ok": true,
    "data": { "equipped": true, "card_id": 42 },
    "error": null
}
```

**Response (desequipar):**
```json
{
    "ok": true,
    "data": { "equipped": false, "card_id": 42 },
    "error": null
}
```

### 5.2 Flujo Completo de Equipamiento

```
1. Validar autenticación (requireLogin + CSRF)
2. Obtener character_id y card_id del POST JSON
3. Validar que el personaje existe y pertenece al usuario (o es staff)
4. Validar que el personaje posee la card (game_character_cards)
5. Obtener card_type de la card (game_cards)
6. Solo tipos 'equipo', 'npc_menor', 'barco' son equipables
7. Determinar slot_type según card_type:
   - equipo → 'carga'
   - npc_menor → 'companero'
   - barco → 'barco'
8. Obtener peso de la card
9. Verificar si YA está equipada:
   - SELECT 1 FROM game_character_inventory WHERE character_id=? AND card_id=?
   - Si existe → DELETE (desequipar) y responder
   - Si no existe → continuar a validaciones de equipamiento
10. Validar límites del slot:
    - carga: cc_used + peso <= cc_max
    - companero: companions_count < companion_max
    - barco: barcos_count < 1
11. INSERT en game_character_inventory
12. Responder {equipped: true, card_id}
```

### 5.3 Validaciones Detalladas

**Validación de tipo equipable:**
```php
$type = $card['card_type'];
if (!in_array($type, ['equipo', 'npc_menor', 'barco'], true)) {
    GameAjax::fail(400, 'Este tipo de carta no se puede equipar.');
}
```

**Validación de peso y capacidad de carga:**
```php
// Obtener peso de la card
$peso = (int)($card['peso'] ?? 1);

// Calcular CC máxima
$fue = game_build_stat_context($stats, $raceNameToggle)['values']['fue'] ?? 4;
$cc_max = 5 + (int)floor($fue / 4) + ($has_carga_perk ? 3 : 0);

// Calcular CC usado actual
$current_q = $db->query("SELECT slot_type, peso FROM {$prefix}game_character_inventory WHERE character_id = {$char_id}");
$cc_used = 0;
while ($r = $db->fetch_array($current_q)) {
    if ($r['slot_type'] === 'carga') {
        $cc_used += (int)$r['peso'];
    }
}

// Validar
if ($cc_used + $peso > $cc_max) {
    GameAjax::fail(400, "Capacidad de Carga insuficiente. Consumo: {$peso} CC (Límite: {$cc_used}/{$cc_max} CC).");
}
```

**Validación de límite de compañeros:**
```php
$companion_max = $has_vinculo_companero ? 2 : 1;
if ($companions_count >= $companion_max) {
    GameAjax::fail(400, "Límite de compañeros excedido ({$companions_count}/{$companion_max}). Desequipa uno primero o amplía tu ranura por linaje.");
}
```

**Validación de límite de barco:**
```php
if ($barcos_count >= 1) {
    GameAjax::fail(400, "Ya tienes un barco activo. Desactiva el barco actual primero para equipar uno nuevo.");
}
```

### 5.4 Reglas de Slot por Tipo de Card

| `card_type` | `slot_type` | ¿Equipable? |
|-------------|-------------|:-----------:|
| `equipo` | `carga` | Sí |
| `npc_menor` | `companero` | Sí |
| `barco` | `barco` | Sí |
| `tecnica` | — | No |
| `haki` | — | No |
| `akuma_no_mi` | — | No |

### 5.5 Concurrencia y Atomicidad

El toggle NO usa transacciones explícitas (no hay `BEGIN/COMMIT`). Sin embargo, cada operación es una sola query:
- DELETE es atómico.
- INSERT con validación previa es atómico (la validación lee antes, pero la escritura es un solo INSERT).

El riesgo de condición de carrera (dos clicks simultáneos) es bajo porque:
1. El inventario es por personaje, y un jugador rara vez hace clic simultáneo.
2. La PK compuesta `(character_id, card_id)` evita duplicados en INSERT.
3. El DELETE es idempotente.

---

## 6. Inventario: Vista y Gestión

### 6.1 Vista General en la Ficha

El inventario se muestra en el subtab **"Gestionar Equipamiento"** dentro del tab **Gestión** de la ficha de personaje.

**Layout HTML:**
```html
<div class="rpg-inv-dashboard-box">
    <!-- CC Bar -->
    <div class="rpg-inv-cc-card">
        <div class="rpg-inv-cc-header">
            <span class="rpg-inv-cc-lbl">CAPACIDAD DE CARGA (CC)</span>
            <strong id="rpg-inv-cc-display">0 / 0 CC</strong>
        </div>
        <div class="rpg-inv-cc-bar-container">
            <div id="rpg-inv-cc-bar-fill" class="rpg-inv-cc-bar-fill"></div>
        </div>
        <div class="rpg-inv-cc-info">
            CC = 5 + floor(FUE / 4) + Linaje.
        </div>
    </div>

    <!-- Slots Grid -->
    <div class="rpg-inv-slots-grid">
        <div class="rpg-inv-slot-card">
            <div class="rpg-inv-slot-icon"><i class="fas fa-paw"></i></div>
            <div class="rpg-inv-slot-desc">
                <span class="rpg-inv-slot-lbl">COMPAÑEROS</span>
                <strong id="rpg-inv-companion-display">0 / 1</strong>
            </div>
        </div>
        <div class="rpg-inv-slot-card">
            <div class="rpg-inv-slot-icon"><i class="fas fa-ship"></i></div>
            <div class="rpg-inv-slot-desc">
                <span class="rpg-inv-slot-lbl">BARCO ACTIVO</span>
                <strong id="rpg-inv-barco-display">0 / 1</strong>
            </div>
        </div>
    </div>
</div>

<!-- Deck Grid -->
<div class="rpg-inv-deck-section">
    <h4>Tu Deck (Equipables / Disponibles)</h4>
    <div class="rpg-inv-deck-filters">
        <button class="rpg-inv-filter-btn active" data-filter="all">Todos</button>
        <button class="rpg-inv-filter-btn" data-filter="equipo">Carga</button>
        <button class="rpg-inv-filter-btn" data-filter="npc_menor">Compañeros</button>
        <button class="rpg-inv-filter-btn" data-filter="barco">Barcos</button>
    </div>
    <div id="rpg-inv-deck-list" class="rpg-inv-grid"></div>
</div>
```

### 6.2 Card en el Grid — Render

Cada card en el inventario se renderiza con:
- **Nombre** y **tipo** (badge: Equipo/Compañero/Barco)
- **Descripción** (truncada)
- **Rango** (D-SS)
- **Peso** (solo para tipo `equipo`)
- **Badge "Equipado"** si está equipada (verde)
- **Botón Equipar/Desequipar**

**Visual states:**
| Estado | Apariencia |
|--------|-----------|
| No equipada | Borde gris, botón azul "Equipar" |
| Equipada | Borde verde, badge "Equipado", botón rojo "Desequipar" |
| Sin permisos | Sin botón de toggle (solo vista) |
| CC insuficiente | Botón deshabilitado con tooltip (validación servidor) |

### 6.3 Filtros

El inventario tiene 4 filtros:
- **Todos:** Muestra todas las cards equipables del personaje.
- **Carga:** Solo cards tipo `equipo`.
- **Compañeros:** Solo cards tipo `npc_menor`.
- **Barcos:** Solo cards tipo `barco`.

Los filtros se aplican en cliente (JS) sobre los datos ya cargados:

```javascript
if (activeFilter !== "all") {
    filtered = owned.filter(function (card) {
        return card.card_type === activeFilter;
    });
}
```

### 6.4 Barra de CC

La barra de Capacidad de Carga tiene 3 estados visuales:
- **Normal** (verde/azul): CC < 80%
- **Advertencia** (amarillo): CC >= 80%
- **Peligro** (rojo): CC >= 100%

```javascript
var ccPct = char.cc_max > 0 ? (char.cc_used / char.cc_max) * 100 : 0;
if (ccPct >= 100) {
    ccBar.classList.add("danger");
} else if (ccPct >= 80) {
    ccBar.classList.add("warning");
}
```

### 6.5 Carga Inicial

El inventario se carga al cambiar al subtab "equipamiento":

```javascript
function init() {
    var originalSwitch = window.switchGestionSubtab;
    window.switchGestionSubtab = function (subtabId) {
        if (originalSwitch) originalSwitch(subtabId);
        if (subtabId === "equipamiento") {
            loadInventory();
        }
    };
}
```

**`loadInventory()`** hace GET a `inventory_get.php?character_id=N` y renderiza:
- CC bar + display
- Compañeros count
- Barco count
- Grid de cards (equipadas + no equipadas)

---

## 7. Consumibles

### 7.1 Qué es un Consumible

Un consumible es una card de tipo `equipo` con subtipo `util` (en `effects_json.equipo_type = "util"`) o con tags especiales (`CONSUMIBLE`, `MUNICION`, `AMMO`).

```php
function game_postcharacter_is_consumible_card(array $card): bool
{
    $ef = json_decode($card['effects_json'] ?? '{}', true);
    if (($ef['equipo_type'] ?? '') === 'util') {
        return true;
    }
    $tags = json_decode($card['tags_json'] ?? '[]', true);
    if (!is_array($tags)) {
        return false;
    }
    foreach ($tags as $t) {
        $u = strtoupper((string)$t);
        if (in_array($u, ['CONSUMIBLE', 'MUNICION', 'AMMO'], true)) {
            return true;
        }
    }
    return false;
}
```

### 7.2 Tipos de Consumible

| Subtipo | Ejemplo | Comportamiento |
|---------|---------|---------------|
| `pocion` | Poción curativa | Cura PV, se consume al usarse |
| `comida` | Bento de batalla | Buff temporal, se consume |
| `municion` | Bala explosiva | Se consume al usarse como `[MUNICION]` |
| `herramienta` | Kit de médico | Se consume al usarse (o tiene usos limitados) |
| `material` | Madera, metal | Se consume al craftear |

### 7.3 Almacenamiento

Los consumibles se almacenan en `game_character_cards` con el campo `cantidad`:

```sql
cantidad INT NOT NULL DEFAULT 1,
```

La misma card puede tener multiples unidades en una sola fila (stack):
- `character_id = 1, card_id = 60, cantidad = 5` → 5 pociones curativas.
- Al comprar más: `ON DUPLICATE KEY UPDATE cantidad = cantidad + N`.
- Al usar una: `UPDATE ... SET cantidad = GREATEST(0, cantidad - 1)`.
- Al llegar a 0: `DELETE ... WHERE cantidad <= 0`.

### 7.4 Uso en Posts

Los consumibles NO requieren estar equipados para usarse. Pueden usarse de dos formas:

**Como card principal:**
```json
{
    "card_id": 60,
    "selected_action": "beber"
}
```

El sistema procesa la card normalmente y luego decrementa:

```php
if (game_postcharacter_is_consumible_card($card)) {
    game_postcharacter_decrement_consumible($cid, $c);
}
```

**Como munición adjunta (`[MUNICION]`):**
```json
{
    "card_id": 42,
    "weapons": [55],
    "ammo": [60]
}
```

Cuando una técnica o arma usa `[MUNICION]` en su fórmula, la munición se consume al usarse:

```php
$ammo_used = array_unique(array_filter(array_map('intval', $selected_ammo)));
foreach ($ammo_used as $a_id) {
    if (game_postcharacter_is_consumible_card($a_card)) {
        game_postcharacter_decrement_consumible($cid, $a_id);
    }
}
```

### 7.5 Decremento Atómico

```php
function game_postcharacter_decrement_consumible(int $cid, int $card_id): void
{
    global $db;
    $prefix = TABLE_PREFIX;
    if (!$db->field_exists('cantidad', 'game_character_cards')) {
        return;
    }

    // Decrementar (mínimo 0)
    $db->write_query(
        "UPDATE {$prefix}game_character_cards SET cantidad = GREATEST(0, cantidad - 1)
         WHERE character_id = {$cid} AND card_id = {$card_id}",
        1
    );

    // Limpiar filas con cantidad 0
    $db->write_query(
        "DELETE FROM {$prefix}game_character_cards
         WHERE character_id = {$cid} AND card_id = {$card_id} AND cantidad <= 0",
        1
    );
}
```

Dos queries separadas (no transacción): la primera garantiza que cantidad nunca baje de 0 (`GREATEST(0, ...)`), la segunda limpia filas vacías. Si la segunda falla, la fila queda con `cantidad = 0` pero no afecta al juego (el frontend puede ocultarla).

### 7.6 Stack vs Único

| Propiedad | Stackable (consumible) | Único (no consumible) |
|-----------|----------------------|----------------------|
| `cantidad` en DB | > 1 (stack) | Siempre 1 |
| Compra repetida | Incrementa cantidad | Rechazada ("Ya posees este objeto") |
| Venta | Vende N unidades | Vende el objeto completo |
| Equipamiento | No requiere equiparse | Requiere slot `carga` |

**Detección en compra:**
```php
$is_consumable = ($card['card_type'] === 'equipo'
    && ($effects['equipo_type'] ?? '') === 'util');

if (!$is_consumable) {
    // Verificar que no lo tenga ya
    $check_owned = $db->query("SELECT 1 FROM {$prefix}game_character_cards
        WHERE character_id = {$character_id} AND card_id = {$card_id} LIMIT 1");
    if ($db->num_rows($check_owned) > 0) {
        GameAjax::fail(400, "Ya posees el objeto único: {$card['name']}.");
    }
    $qty = 1;  // Forzar cantidad 1 para únicos
}
```

### 7.7 Filosofía de Consumibles

**¿Por qué los consumibles no requieren equiparse?**
- **Fluidez narrativa:** Si un personaje necesita beber una poción en medio del combate, no debería tener que haberla "equipado" antes del hilo. Las pociones están en su mochila, accesibles.
- **Diferenciación mecánica:** Los consumibles son recursos de un solo uso. El coste no es de equipamiento sino de adquisición (Berries) y de acción en el post.

**¿Por qué los consumibles no pesan?**
Porque si pesaran, un personaje con 20 pociones no podría equipar armadura. El peso cero permite llevar muchos consumibles sin sacrificar equipo de combate.

---

## 8. Compra y Venta en Tienda

### 8.1 Compra (`tienda_comprar.php`)

Endpoint POST que procesa un carrito de compras. Puede comprar múltiples items en una sola operación atómica.

**Request:**
```json
{
    "character_id": 1,
    "cart": [
        {"card_id": 101, "cantidad": 1},
        {"card_id": 102, "cantidad": 5}
    ]
}
```

**Validaciones:**
1. Personaje existe y pertenece al usuario.
2. Personaje está `aprobada`.
3. Cards existen y tienen `in_shop = 1`.
4. Cards son de tipo `equipo`, `npc_menor` o `barco`.
5. Precio total calculado ≤ Berries del personaje.
6. Si no es consumible: personaje no posee ya esa card (único).
7. Si es consumible: se permite duplicado (incrementa cantidad).

**Procesamiento:**
```php
// Descontar berries
$db->write_query("UPDATE {$prefix}game_personajes SET berries = berries - {$total_cost} WHERE id = {$character_id}");

// Insertar/actualizar cards
foreach ($items_to_buy as $item) {
    $db->write_query("
        INSERT INTO {$prefix}game_character_cards (character_id, card_id, current_rank, assigned_by, cantidad)
        VALUES ({$character_id}, {$card_id}, '{$rank}', {$uid}, {$qty})
        ON DUPLICATE KEY UPDATE cantidad = cantidad + {$qty}
    ");
}
```

**Respuesta:**
```json
{
    "ok": true,
    "data": {
        "new_berries": 9500,
        "message": "Compra realizada correctamente."
    }
}
```

### 8.2 Venta (`tienda_vender.php`)

Endpoint POST para vender objetos al 50% de su valor de compra.

**Request:**
```json
{
    "character_id": 1,
    "card_id": 101,
    "cantidad": 1
}
```

**Validaciones:**
1. Card existe, tiene `cost_berries > 0`.
2. Card es de tipo comerciable (`equipo`, `npc_menor`, `barco`).
3. Personaje posee la card y tiene cantidad suficiente.

**Cálculo de reembolso:**
```php
$refund_each = (int)floor($cost_berries * 0.5);
$total_refund = $refund_each * $cantidad;
```

**Procesamiento:**
```php
if ($cantidad >= $owned_cantidad) {
    // Vender todo: borrar fila
    $db->write_query("DELETE FROM {$prefix}game_character_cards WHERE character_id = {$character_id} AND card_id = {$card_id}");
} else {
    // Vender parte: decrementar
    $db->write_query("UPDATE {$prefix}game_character_cards SET cantidad = {$remaining} WHERE character_id = {$character_id} AND card_id = {$card_id}");
}

// Sumar berries
$db->write_query("UPDATE {$prefix}game_personajes SET berries = berries + {$total_refund} WHERE id = {$character_id}");
```

**Filosofía de reventa al 50%:** La pérdida del 50% es intencional para:
- Prevenir "arbitraje" (comprar y vender repetidamente para ganar dinero).
- Hacer que la compra sea una decisión con coste de oportunidad.
- Dar un valor residual a los objetos (no se pierde todo al vender).

---

## 9. Database Schema

### 9.1 `game_character_inventory` — Equipamiento Activo

```sql
CREATE TABLE mybb_game_character_inventory (
    character_id INT NOT NULL,
    card_id INT NOT NULL,
    slot_type ENUM('carga', 'companero', 'barco') NOT NULL,
    equipped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    peso INT NOT NULL DEFAULT 0,
    PRIMARY KEY (character_id, card_id),
    INDEX idx_char_slot (character_id, slot_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `character_id` | INT | FK a `game_personajes.id` |
| `card_id` | INT | FK a `game_cards.id` |
| `slot_type` | ENUM('carga','companero','barco') | Qué slot ocupa |
| `equipped_at` | TIMESTAMP | Cuándo se equipó |
| `peso` | INT | Copia del peso al equipar (0 para no-carga) |

**PK compuesta** `(character_id, card_id)`: Un personaje no puede equipar la misma card dos veces.

**Índice** `idx_char_slot`: Para consultas rápidas por personaje y slot.

**Filosofía del `peso` copiado:**
Si el staff cambia el peso de una card en `game_cards`, los items ya equipados mantienen su peso original hasta que se desequipan. Esto evita cambios disruptivos en medio de un hilo.

### 9.2 `game_character_cards` — Posesión de Cards (con cantidad)

```sql
CREATE TABLE mybb_game_character_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    character_id INT NOT NULL,
    card_id INT NOT NULL,
    current_rank ENUM('D', 'C', 'B', 'A', 'S', 'SS') NOT NULL DEFAULT 'C',
    assigned_by INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_char_card (character_id, card_id),
    KEY idx_char (character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

| Campo | Descripción |
|-------|-------------|
| `cantidad` | Para consumibles: cuántas unidades tiene. Default 1. Para no consumibles: siempre 1. |

### 9.3 `game_cards` — Catálogo (campo `peso`)

```sql
CREATE TABLE mybb_game_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- ... otros campos ...
    peso INT NOT NULL DEFAULT 1,
    -- ...
);
```

### 9.4 `game_post_characters` — Snapshot por Post

```sql
CREATE TABLE mybb_game_post_characters (
    post_id INT PRIMARY KEY,
    thread_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    character_id INT NOT NULL,
    pv_change INT NOT NULL DEFAULT 0,
    pe_change INT NOT NULL DEFAULT 0,
    pa_declared TINYINT UNSIGNED NOT NULL DEFAULT 0,
    modifiers_json TEXT DEFAULT NULL,
    hidden_actions_json TEXT DEFAULT NULL,
    equipped_snapshot_json TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_thread_id (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

| Campo | Descripción |
|-------|-------------|
| `equipped_snapshot_json` | JSON array de `card_id`s equipados al momento del post. Ej: `[42, 55, 70]` |

### 9.5 Migraciones y Orden de Creación

Las tablas relacionadas con inventario tienen un orden de migración:

```
1. migrate_cards.php          → game_cards (catálogo)
2. migrate_character_cards.php → game_character_cards (posesión)
3. migrate_inventory.php       → game_character_inventory + columna peso en game_cards
4. migrate_post_equipped_snapshot.php → columna equipped_snapshot_json
5. migrate_character_cards_quantity.php → columna cantidad (consumibles)
```

`migrate_inventory.php` es un script ejecutable desde consola:

```php
// 1. Crear tabla game_character_inventory
$db->write_query("CREATE TABLE {$table} (
    character_id INT NOT NULL,
    card_id INT NOT NULL,
    slot_type ENUM('carga', 'companero', 'barco') NOT NULL,
    equipped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    peso INT NOT NULL DEFAULT 0,
    PRIMARY KEY (character_id, card_id),
    INDEX idx_char_slot (character_id, slot_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

// 2. Agregar columna peso a game_cards
if (!$db->field_exists('peso', 'game_cards')) {
    $db->write_query("ALTER TABLE {$table_cards} ADD peso INT NOT NULL DEFAULT 1;");
}
```

---

## 10. Implementación PHP

### 10.1 `inventory_helpers.php` — Funciones Compartidas

Archivo: `game/inc/inventory_helpers.php`

Seguro para incluir desde plugins MyBB (no recarga `global.php`). Define funciones _guard_ con `function_exists()` para evitar redeclaración.

```php
if (!function_exists('game_get_equipped_card_ids')) {
    function game_get_equipped_card_ids(int $characterId): array
    {
        global $db;
        if ($characterId <= 0 || !$db->table_exists('game_character_inventory')) {
            return [];
        }
        $prefix = TABLE_PREFIX;
        $q = $db->query("SELECT card_id FROM {$prefix}game_character_inventory WHERE character_id = {$characterId}");
        $ids = [];
        while ($row = $db->fetch_array($q)) {
            $ids[] = (int)$row['card_id'];
        }
        return $ids;
    }
}

if (!function_exists('game_inventory_system_active')) {
    function game_inventory_system_active(): bool
    {
        global $db;
        return $db->table_exists('game_character_inventory');
    }
}

if (!function_exists('game_card_requires_equipped_slot')) {
    function game_card_requires_equipped_slot(string $cardType, bool $isConsumible = false): bool
    {
        if ($isConsumible) return false;
        return in_array($cardType, ['equipo', 'npc_menor', 'barco'], true);
    }
}
```

### 10.2 `inventory_get.php` — Obtener Estado del Inventario

Archivo: `game/ajax/inventory_get.php`

**Propósito:** Devuelve el estado completo del inventario: slots, CC, cards equipadas y poseídas.

**Flujo:**
1. Validar autenticación y permisos (owner o staff).
2. Obtener stats del personaje (FUE, linaje).
3. Calcular límites:
   - `cc_max = 5 + floor(FUE/4) + (perk ? 3 : 0)`
   - `companion_max = 1` (2 con perk `g_vinculo_companero`)
   - `barco_max = 1`
4. Consultar `game_character_inventory` JOIN `game_cards` → items equipados.
5. Calcular `cc_used`, `companions_count`, `barcos_count`.
6. Consultar `game_character_cards` JOIN `game_cards` → cards poseídas (solo equipables: `equipo`, `npc_menor`, `barco`).
7. Marcar `is_equipped` en cada card poseída.
8. Responder JSON.

**Response completa:**
```json
{
    "ok": true,
    "data": {
        "character": {
            "id": 42,
            "name": "Kazan",
            "fue": 30,
            "cc_max": 12,
            "cc_used": 3,
            "companion_max": 1,
            "companion_used": 0,
            "barco_max": 1,
            "barco_used": 0
        },
        "equipped": [
            {
                "card_id": 101,
                "slot_type": "carga",
                "peso": 3,
                "name": "Espada ligera",
                "card_type": "equipo",
                "rank": "C",
                "description": "Arma básica.",
                "image_url": ""
            }
        ],
        "owned": [
            {
                "id": 101,
                "name": "Espada ligera",
                "card_type": "equipo",
                "rank": "C",
                "description": "...",
                "peso": 3,
                "cantidad": 1,
                "is_equipped": true
            }
        ]
    },
    "error": null
}
```

### 10.3 `inventory_toggle.php` — Equipar/Desequipar

Archivo: `game/ajax/inventory_toggle.php`

**Propósito:** Toggle de equipamiento. Si la card está equipada, la desequipa; si no, la equipa (validando límites).

**Métodos clave del flujo:**

```php
// 1. Validar permisos y existencia
$pj_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$char_id} LIMIT 1");
$pj = $db->fetch_array($pj_q);
$is_owner = ($uid > 0 && (int)$pj['user_id'] === $uid);

// 2. Verificar posesión de la card
$owns_q = $db->query("SELECT 1 FROM {$prefix}game_character_cards WHERE character_id = {$char_id} AND card_id = {$card_id} LIMIT 1");

// 3. Determinar slot_type y peso según card_type
$slot_type = 'carga';
if ($type === 'npc_menor') $slot_type = 'companero';
elseif ($type === 'barco') $slot_type = 'barco';
$peso = (int)($card['peso'] ?? 1);

// 4. Si ya equipada → DELETE (desequipar)
$eq_q = $db->query("SELECT 1 FROM {$prefix}game_character_inventory WHERE character_id = {$char_id} AND card_id = {$card_id} LIMIT 1");
$is_equipped = ($db->num_rows($eq_q) > 0);

if ($is_equipped) {
    $db->write_query("DELETE FROM {$prefix}game_character_inventory WHERE character_id = {$char_id} AND card_id = {$card_id}");
    GameAjax::json(true, ['equipped' => false, 'card_id' => $card_id]);
}

// 5. Si no equipada → validar y equipar
// (cálculo de cc_max, cc_used, validaciones, INSERT)
```

### 10.4 `game_postcharacter.php` — Plugin (Snapshot y Consumibles)

Archivo: `inc/plugins/game_postcharacter.php`

**Funciones de inventario:**

| Función | Propósito |
|---------|-----------|
| `game_postcharacter_save_equipped_snapshot()` | Guarda snapshot al crear post |
| `game_postcharacter_get_post_equipped_ids()` | Lee snapshot de un post |
| `game_postcharacter_card_allowed_in_post()` | Verifica si una card puede usarse según equipamiento |
| `game_postcharacter_is_consumible_card()` | Determina si una card es consumible |
| `game_postcharacter_decrement_consumible()` | Decrementa cantidad al usar consumible |
| `game_postcharacter_ensure_inventory_helpers()` | Incluye helpers de inventario |

### 10.5 Cálculo de CC en StatHelpers

No hay una función dedicada "calculateCC". El cálculo se hace inline en `inventory_get.php` e `inventory_toggle.php`:

```php
$fue = game_build_stat_context($stats, $raceName)['values']['fue'] ?? 4;
$linaje = $data['linaje'] ?? [];
$general_ids = $linaje['elegidos_general'] ?? [];
$racial_ids = $linaje['elegidos_racial'] ?? [];

$has_carga_perk = in_array('g_capacidad_carga', $general_ids)
    || in_array('g_capacidad_carga', $racial_ids)
    || in_array('g_carga_extra', $general_ids);

$cc_max = 5 + (int)floor($fue / 4) + ($has_carga_perk ? 3 : 0);
```

Si en el futuro se centraliza, debería estar en `inventory_helpers.php` como `game_calculate_carry_capacity(int $characterId): int`.

---

## 11. Implementación JavaScript

### 11.1 `personaje_inventory.js` — Módulo de Inventario

Archivo: `jscripts/game/personaje_inventory.js` (210 líneas)

**Arquitectura:**
- IIFE (Immediately Invoked Function Expression) para evitar contaminar el scope global.
- Config desde `window.PERSONAJE_PAGE_CONFIG` (definido en `_scripts.php`).
- Comunicación AJAX con `fetch()` y `credentials: "same-origin"`.

**Funciones:**

| Función | Línea | Propósito |
|---------|:-----:|-----------|
| `gameFetchPost()` | 11 | Helper para POST JSON con CSRF |
| `escapeHtml()` | 30 | Escape de HTML (XSS prevention) |
| `loadInventory()` | 40 | GET a `inventory_get.php` y render |
| `renderUI()` | 68 | Actualiza CC bar, contadores de slots |
| `renderDeckList()` | 96 | Renderiza grid de cards con filtros |
| `toggleEquip()` | 153 | POST a `inventory_toggle.php` |
| `init()` | 170 | Binding de eventos, load on subtab switch |

**Carga inicial:**
```javascript
function loadInventory() {
    fetch(AJAX_BASE + "/inventory_get.php?character_id=" + cfg.characterId)
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.ok && res.data) {
                currentInventoryData = res.data;
                renderUI();
            } else {
                showError(deckContainer, res.error.message);
            }
        });
}
```

**Toggle de equipamiento:**
```javascript
function toggleEquip(cardId) {
    gameFetchPost("/inventory_toggle.php", {
        character_id: cfg.characterId,
        card_id: cardId
    }).then(function (res) {
        if (res.ok) {
            loadInventory();  // Recargar todo
        } else {
            alert("Error: " + res.error.message);
        }
    });
}
```

**Delegación de eventos:**
```javascript
var deckEl = document.getElementById("rpg-inv-deck-list");
if (deckEl) {
    deckEl.addEventListener("click", function (e) {
        var btn = e.target.closest(".rpg-inv-toggle-btn");
        if (btn) {
            var cardId = parseInt(btn.getAttribute("data-card-id"), 10);
            if (cardId) toggleEquip(cardId);
        }
    });
}
```

### 11.2 Filtros

```javascript
var filterBtns = document.querySelectorAll(".rpg-inv-filter-btn");
filterBtns.forEach(function (btn) {
    btn.addEventListener("click", function () {
        filterBtns.forEach(function (b) { b.classList.remove("active"); });
        this.classList.add("active");
        activeFilter = this.getAttribute("data-filter");
        renderDeckList();  // Re-render con filtro
    });
});
```

---

## 12. AJAX Endpoints — Catálogo

### 12.1 `inventory_get.php`
**Método:** GET
**Propósito:** Obtener estado completo del inventario.
**Parámetros:** `character_id` (opcional, usa active_pj_id si omite)
**Autenticación:** Owner del personaje o staff.
**Response:** JSON con `character`, `equipped`, `owned`.

### 12.2 `inventory_toggle.php`
**Método:** POST
**Propósito:** Equipar o desequipar una card.
**Body:** `{character_id, card_id}`
**Autenticación:** Owner del personaje o staff + CSRF.
**Validaciones:** Peso, límites de slot, tipo de card.
**Response:** `{equipped: bool, card_id: int}`

### 12.3 `tienda_comprar.php`
**Método:** POST
**Propósito:** Comprar cards de la tienda.
**Body:** `{character_id, cart: [{card_id, cantidad}]}`
**Autenticación:** Owner del personaje + CSRF.
**Validaciones:** Berries suficientes, tipo vendible, unicidad, existencia en catálogo.
**Response:** `{new_berries, message}`

### 12.4 `tienda_vender.php`
**Método:** POST
**Propósito:** Vender cards al 50% del valor.
**Body:** `{character_id, card_id, cantidad}`
**Autenticación:** Owner del personaje + CSRF.
**Validaciones:** Card tiene valor de reventa, personaje posee suficientes.
**Response:** `{new_berries, message}`

### 12.5 Endpoints Relacionados (en otros subsistemas)

| Endpoint | Propósito | Inventario relacionado |
|----------|-----------|----------------------|
| `cards_my_deck.php` | Listar cards del personaje | Filtra equipadas, marca `is_consumible` |
| `cards_assign.php` | Asignar card por staff | Inserta en `game_character_cards` |
| `cards_request_custom.php` | Solicitar card | Crea solicitud en `game_card_requests` |
| `cards_my_deck.php` | Deck para post | Filtra cards jugables según equipamiento |
| `save_personaje.php` | Crear/editar personaje | No toca inventario directamente |

---

## 13. Validaciones y Seguridad

### 13.1 Matriz de Permisos

| Operación | Owner PJ | Staff (1+) | Superadmin (3) |
|-----------|:--------:|:----------:|:--------------:|
| Ver inventario propio | ✓ | ✓ | ✓ |
| Ver inventario ajeno | - | ✓ | ✓ |
| Equipar/desequipar | ✓ | ✓ | ✓ |
| Comprar en tienda | ✓ | - | ✓ |
| Vender en tienda | ✓ | - | ✓ |
| Asignar card directamente | - | - | ✓ |

### 13.2 Validaciones de Seguridad

**CSRF:** Toda operación de escritura requiere `my_post_key` (CSRF token de MyBB):
```php
GameAjax::requireCsrf($input);
```

**Server-side validation:** Ninguna validación de inventario ocurre en cliente. El JS solo mejora UX. El servidor siempre revalida:
- Posesión de la card.
- Límites de CC, compañeros, barco.
- Tipo de card equipable.
- Permisos del usuario.

**Inyección SQL:** Todas las queries usan escapado (`$db->escape_string()`) o casteo a int:
```php
$char_id = (int)$input['character_id'];
$slot_type = $db->escape_string($slot_type);
```

**Protección contra equipamiento inválido:**
```php
if (!in_array($type, ['equipo', 'npc_menor', 'barco'], true)) {
    GameAjax::fail(400, 'Este tipo de carta no se puede equipar.');
}
```

### 13.3 Casos Borde

**¿Qué pasa si un personaje es eliminado?**
Si se borra un personaje (`game_personajes`), las filas huérfanas en `game_character_inventory` y `game_character_cards` deben limpiarse. Actualmente no hay CASCADE automático — el staff debe ejecutar una limpieza manual o migración.

**¿Qué pasa si una card es eliminada del catálogo?**
`game_character_cards` y `game_character_inventory` referencian `card_id` sin FK formal (no hay `ON DELETE CASCADE`). Si se elimina una card del catálogo con personajes que la poseen, quedan referencias huérfanas. El sistema actualmente:
- `inventory_get.php` hace JOIN y las filas sin match se ignoran (LEFT JOIN implícito).
- `inventory_toggle.php` falla si la card no existe.

**¿Qué pasa si dos personajes comparten el mismo slot?**
Cada personaje tiene su propio inventario. No hay conflicto.

---

## 14. Filosofía de Diseño

### 14.1 ¿Por qué 3 Slots (carga, companero, barco)?

La división en 3 slots refleja tres categorías fundamentalmente diferentes de "cosas que un personaje lleva":

1. **Carga:** Objetos inanimados que ocupan espacio físico y tienen peso. Compiten entre sí por recursos limitados (CC).
2. **Compañero:** Seres vivos con voluntad propia. No ocupan "carga" porque no los llevas en la mochila — te acompañan. Tienen su propio límite porque no puedes tener 10 bestias siguiéndote.
3. **Barco:** Un vehículo. No es algo que "portes" sino que "posees". Solo tienes un barco activo porque no puedes navegar dos barcos a la vez.

**Alternativa descartada:** Un solo slot "inventario" con todos los items mezclados. Se descartó porque:
- No habría diferenciación mecánica entre "llevar un arma" y "tener un compañero".
- Un barco no debería competir por el mismo espacio que una poción.
- La UX sería confusa (¿por qué mi barco aparece en la misma lista que mis armas?).

### 14.2 ¿Por qué Límites de Peso?

El peso introduce **coste de oportunidad** en el equipamiento. Sin peso:
- Los personajes equiparían todo lo que poseen.
- No habría decisión estratégica.
- Las armaduras pesadas no tendrían desventaja frente a las ligeras (ambas se equipan igual).
- El stat FUE perdería relevancia.

El peso como recurso limitado convierte el equipamiento en un juego de gestión: "¿Qué es más importante ahora, llevar el escudo o las herramientas?"

### 14.3 ¿Por qué Snapshots en Posts?

El snapshot resuelve el problema de **auditoría de equipamiento**. Sin snapshot:
- Un jugador podría usar un arma en un post y equiparla después.
- El staff no tendría forma de verificar que el arma estaba disponible.
- El combate perdería integridad: ¿usó realmente esa técnica o la está añadiendo después?

El snapshot es una **prueba inmutable** de qué tenía equipado el personaje en el momento exacto de escribir el post.

### 14.4 ¿Por qué Separar Inventario de Equipamiento?

Tres capas distintas para tres conceptos diferentes:

| Concepto | Tabla | Significado |
|----------|-------|-------------|
| **Catálogo** | `game_cards` | "Esto existe en el mundo" |
| **Posesión** | `game_character_cards` | "Este personaje tiene esto" |
| **Equipamiento** | `game_character_inventory` | "Este personaje lleva esto puesto ahora" |

Separarlas permite:
- Un personaje puede tener 50 cards pero solo equipar 5.
- Se puede cambiar de equipo entre hilos sin perder posesión.
- El snapshot captura solo lo equipado, no todo lo poseído.

### 14.5 ¿Por qué los Consumibles son Excepción?

Los consumibles son la única categoría que puede usarse sin estar equipada. Esto es intencional porque:
- **Narrativa:** Beber una poción no requiere tenerla "equipada" — está en tu cinturón/mochila.
- **Mecánica:** El coste de un consumible es económico (Berries) y de acción (usa tu turno en el post), no de equipamiento.
- **Jugabilidad:** Si las pociones ocuparan CC, los personajes con poca fuerza no podrían llevar ni armadura ni pociones. Sería un castigo doble.

### 14.6 Decisiones Clave y su Porqué

| Decisión | Alternativa descartada | Por qué se eligió así |
|----------|----------------------|----------------------|
| ENUM para slot_type | VARCHAR | Integridad de datos: solo 3 valores válidos |
| PK compuesta (character_id, card_id) | AUTO_INCREMENT solo | Evita duplicados de equipamiento |
| Peso copiado al equipar | Leer peso siempre de game_cards | Evita cambios disruptivos por edición de catálogo |
| Toggle equip/unequip en 1 endpoint | Endpoints separados | Simplicidad, menos código, toggle natural |
| Consumibles usan cantidad | Filas separadas por unidad | Eficiencia (1 fila = N unidades vs N filas) |
| Validación en servidor (no cliente) | Validación dual | Seguridad: el cliente puede ser manipulado |
| Snapshot solo IDs | Snapshot con datos completos | Tamaño mínimo, JOIN resuelve detalles |
| CC calculado con FUE valor (no rango) | CC con rango | Mayor granularidad, FUE alto escala mejor |

---

## 15. Consejos para Jugadores

### 15.1 Gestión de Peso

**Conoce tu CC:** Antes de comprar equipo, calcula tu CC máxima. Si tienes FUE baja, prioriza armas ligeras (peso 1) sobre armaduras pesadas.

**La regla del 80%:** Intenta mantener tu CC usado por debajo del 80%. Si superas el 80%, no podrás equipar items nuevos sin desequipar algo. Deja margen para botín de misiones.

**Perks de carga:** Si tu personaje es un guerrero que necesita mucho equipo, considera el perk `g_capacidad_carga` o `g_carga_extra` (+3 CC cada uno). Es una inversión de 1 punto de linaje que puede duplicar tu capacidad efectiva.

**Desequipa antes de vender:** `game_character_inventory` guarda el peso al equipar. Si cambias de opinión, desequipa primero, luego vende.

### 15.2 Qué Llevar vs Qué Guardar

**Siempre equipado:**
- 1 arma principal (la que mejor se te dé).
- 1 armadura (si tu personaje la usa).
- Opcional: 1 herramienta de oficio si el hilo lo requiere.

**En posesión (no equipado):**
- Armas secundarias (para situaciones específicas).
- Consumibles (no ocupan CC, pero cuestan Berries).
- NPCs menores que no estén activos.
- Barcos alternativos.

**En el hilo, cámbiate según el contexto.** ¿Sabes que vas a un combate naval? Equipa tu barco y armas a distancia. ¿Sabes que vas a negociar? Lleva herramientas y pociones.

### 15.3 Estrategias de Loadout

**Loadout balanceado (CC ~8):**
- 1 arma mediana (2 CC)
- 1 armadura ligera (2 CC)
- 1 herramienta (1 CC)
- 2-3 consumibles (0 CC)
- Total: ~5 CC (deja margen)

**Loadout ofensivo (CC ~12):**
- 2 armas (una mediana 2 + una grande 3)
- 0 armadura (confías en agilidad)
- 0 herramientas
- 5 consumibles (0 CC)
- Total: ~5 CC (mucho margen para botín)

**Loadout defensivo (CC ~15):**
- 1 arma mediana (2 CC)
- 1 armadura pesada (4 CC)
- 1 escudo grande (3 CC)
- 1 herramienta (1 CC)
- 3 consumibles (0 CC)
- Total: ~10 CC

### 15.4 Compañeros

**Elige bien tu compañero:** Solo puedes tener 1 (o 2 con perk). Un compañero de combate (lobo, guerrero) te da acciones extra en combate. Un compañero de utilidad (ave mensajera, bestia de carga) te da versatilidad narrativa.

**El perk `g_vinculo_companero`** es excelente si tu personaje es un domador, invocador, o simplemente quieres un acompañante adicional.

### 15.5 Barcos

**Solo 1 barco activo.** Si tienes múltiples barcos (un velero rápido y un galeón de guerra), elige según la misión. No puedes cambiarlo en medio del mar.

### 15.6 Consumibles

**Compra en cantidad:** Los consumibles son stackables. Compra 10 pociones de una vez (ahorras viajes a tienda).

**Munición:** Si tu arma usa `[MUNICION]`, asegúrate de tener siempre munición en inventario. Sin ella, el placeholder vale 0 en la fórmula.

---

## 16. Consejos para Staff

### 16.1 Verificar Equipamiento en Posts

Cuando un jugador usa una card de tipo `equipo`, `npc_menor` o `barco` en un post, verifica:

1. **¿El snapshot del post la incluye?** Consulta `game_post_characters.equipped_snapshot_json` para el post.
2. **Si no hay snapshot** (posts anteriores a la migración), consulta `game_character_inventory` para la fecha del post.
3. **Si no está equipada ni en snapshot**, el jugador no debería haberla usado. Es una infracción.

**Query de verificación:**
```sql
SELECT equipped_snapshot_json
FROM mybb_game_post_characters
WHERE post_id = {pid} AND character_id = {cid};
```

Decodifica el JSON: si no contiene el `card_id`, la card no estaba equipada.

### 16.2 Casos Especiales

**Items únicos de trama:**
- Si creas una card especial para una trama, considera marcarla como `UNICA` en tags para que el personaje no pueda tener copias.
- Si el item debe ser irremplazable, no lo pongas en la tienda (`in_shop = 0`).

**Items de misión que desaparecen:**
- Si un item es temporal (ej: "Llave del cofre del tesoro"), asígnalo y retíralo manualmente al completar la misión.
- No hay mecanismo de expiración automática. Debes borrar la card de `game_character_cards` y desequiparla de `game_character_inventory`.

**Objetos no equipables pero importantes:**
- Algunos items pueden ser narrativos sin efectos mecánicos. Crealos como cards tipo `equipo` con `dice = ""` y `peso = 0`.
- No necesitan equiparse si son "narrativos puros", pero el sistema de todos modos permite equiparlos en `carga`.

**Personajes con FUE muy alta:**
- Un personaje con FUE 6 (SS, valor 60) tiene CC=20 base + hasta 23 con perks.
- Puede equipar prácticamente cualquier cosa. Monitorea si esto crea desbalance.
- Considera que el peso es solo UNO de los límites: el tipo de slot y el número de compañeros/barcos también limitan.

### 16.3 Auditoría de Equipamiento

**Log de cambios:** No hay un log específico de cambios de inventario. Si necesitas auditar quién equipó/desequipó qué y cuándo:

1. `game_character_inventory.equipped_at` te dice cuándo se equipó un item.
2. No hay columna `updated_at` ni historial de cambios.
3. Para auditoría retroactiva, usa los snapshots de posts.

**Si sospechas de equipamiento fraudulento:**
```sql
-- Card X fue usada post Y. ¿Estaba equipada?
SELECT equipped_snapshot_json
FROM mybb_game_post_characters
WHERE post_id = Y;

-- ¿Cambió el equipamiento después del post?
SELECT * FROM mybb_game_character_inventory
WHERE character_id = C AND card_id = X
AND equipped_at > (SELECT created_at FROM mybb_game_post_characters WHERE post_id = Y);
```

### 16.4 Mantenimiento de Tablas

**Limpieza de datos huérfanos:**
- Cuando borres un personaje, ejecuta:
  ```sql
  DELETE FROM game_character_inventory WHERE character_id = {id};
  DELETE FROM game_character_cards WHERE character_id = {id};
  ```
- Cuando borres una card del catálogo, verifica que ningún personaje la posea:
  ```sql
  SELECT character_id FROM game_character_cards WHERE card_id = {id};
  SELECT character_id FROM game_character_inventory WHERE card_id = {id};
  ```

**Migraciones:**
- `migrate_inventory.php`: Crea tabla y columna peso.
- `migrate_post_equipped_snapshot.php`: Añade columna snapshot.
- `migrate_character_cards_quantity.php`: Añade columna cantidad para consumibles.
