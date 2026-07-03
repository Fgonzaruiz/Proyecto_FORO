# 13. Barcos — Cards y Navegación

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 13
> **Propósito:** Documentar exhaustivamente el subsistema de barcos como cards de tipo `barco` en el sistema RPG del foro: modelo de datos, adquisición, equipamiento, integración con navegación, estadísticas de barco, durabilidad, reparación, filosofía de diseño, y consejos para jugadores y staff.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [Barcos como Cards](#2-barcos-como-cards)
3. [Categorías Narrativas (Rangos D → SS)](#3-categorías-narrativas)
4. [Campos de Barco — Effects JSON](#4-campos-de-barco)
5. [Equipamiento en Slot Barco](#5-equipamiento-en-slot-barco)
6. [Adquisición de Barcos](#6-adquisición-de-barcos)
7. [Integración con el Sistema de Navegación](#7-integración-con-el-sistema-de-navegación)
8. [Database Schema](#8-database-schema)
9. [Implementación PHP — Navegación](#9-implementación-php)
10. [Durabilidad y Reparación](#10-durabilidad-y-reparación)
11. [Mejoras de Barco (Upgrades)](#11-mejoras-de-barco)
12. [Barcos y Tripulaciones](#12-barcos-y-tripulaciones)
13. [AJAX Endpoints — Catálogo](#13-ajax-endpoints)
14. [Filosofía de Diseño](#14-filosofía-de-diseño)
15. [Consejos para Jugadores](#15-consejos-para-jugadores)
16. [Consejos para Staff](#16-consejos-para-staff)
17. [Referencia Rápida](#17-referencia-rápida)

---

## 1. Arquitectura General

### 1.1 Qué es un Barco en el Sistema

Un **barco** es una card de tipo `barco` que representa la embarcación que posee y opera un personaje o tripulación. No es una entidad separada con tabla propia — es una card como cualquier otra, con las mismas reglas de adquisición, equipamiento, y almacenamiento.

El barco es el vehículo principal para el sistema de navegación (Sección 14). Sin un barco equipado, un personaje no puede iniciar viajes entre islas.

### 1.2 Capas del Subsistema

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          CLIENTE (Navegador)                             │
│  ┌───────────────────┐  ┌─────────────────────┐  ┌───────────────────┐  │
│  │ personaje_inven-  │  │ formulario de post  │  │ zona_staff_nave-  │  │
│  │ tory.js           │  │ (cards_for_post)    │  │ gacion.js         │  │
│  │ (slot barco)      │  │ (selector de barco) │  │ (revisión viajes) │  │
│  └─────────┬─────────┘  └──────────┬──────────┘  └─────────┬─────────┘  │
│            │                       │                        │            │
│            ▼                       ▼                        ▼            │
│  ┌─────────────────────────────────────────────────────────────────────┐ │
│  │              AJAX (game/ajax/*.php)                                  │ │
│  │  inventory_get | inventory_toggle | navigation_ships                │ │
│  │  navigation_voyage_start | navigation_voyages_list                  │ │
│  │  navigation_voyage_review | tienda_comprar                          │ │
│  └──────────────────────────────┬──────────────────────────────────────┘ │
└─────────────────────────────────┼────────────────────────────────────────┘
                                  │ HTTP POST/GET + JSON
┌─────────────────────────────────┼────────────────────────────────────────┐
│  ┌──────────────────────────────▼──────────────────────────────────────┐ │
│  │              PHP — CAPA DE APLICACIÓN                                │ │
│  │  navigation_process.php (game_navigation_process_post)              │ │
│  │  navigation_helpers.php (game_nav_ships_for_character, etc.)        │ │
│  │  navigation_config.php (constantes de velocidad, penalizaciones)    │ │
│  │  navigation_review_helpers.php (revisión staff de viajes)           │ │
│  │  inventory_helpers.php (equipamiento slot barco)                     │ │
│  └──────────────────────────────────────────────────────────────────────┘ │
│                                    │                                      │
│                                    ▼                                      │
│  ┌──────────────────────────────────────────────────────────────────────┐ │
│  │           MySQL (MyBB + tablas game_*)                               │ │
│  │  game_cards (card_type='barco')                                     │ │
│  │  game_character_cards (posesión de barco)                           │ │
│  │  game_character_inventory (slot_type='barco')                       │ │
│  │  game_navigation_voyages (ship_card_id, raw_calculation_json)       │ │
│  │  game_navigation_events (eventos del viaje)                          │ │
│  └──────────────────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────────┘
```

### 1.3 Filosofía de la Arquitectura

**¿Por qué los barcos son cards y no una tabla separada `game_barcos`?**

1. **Consistencia del sistema:** El staff aprende UN sistema de cards y lo aplica a todo. No necesitan aprender un módulo de barcos separado con sus propias reglas, formularios y endpoints.
2. **Reutilización de infraestructura:** El sistema de adquisición (tienda, solicitudes, asignación staff), equipamiento (inventario toggle), snapshot en posts, y almacenamiento (posesión con cantidad) funciona IDÉNTICO para barcos que para armas, NPCs menores, o consumibles.
3. **Extensibilidad:** Si en el futuro se necesita un tipo `vehiculo` o `fortaleza`, se añade al ENUM de `card_type`. No requiere nueva tabla, nueva migración de esquema, ni nuevo flujo de código.
4. **Catálogo unificado:** Buscar "barco" en la tienda usa el mismo endpoint `shop_catalog_list.php` que busca armas. Filtrar por tipo es un parámetro `card_type=barco`.
5. **Snapshots en posts:** El barco equipado aparece en `equipped_snapshot_json` automáticamente, sin código adicional.

**¿Por qué solo 1 barco equipado a la vez?**

Porque un personaje solo puede navegar UNA embarcación a la vez. A diferencia de las armas (puedes llevar varias en carga), el barco es tu vehículo principal. Si quieres cambiar de barco, debes desequipar el actual y equipar el nuevo. Esto fuerza decisiones estratégicas: "¿uso mi rápido bergantín para este viaje corto, o mi resistente galeón por si hay tormentas?"

**¿Por qué los barcos son pasivos (activation = 'pasiva')?**

Los barcos no se "activan" en un post como una técnica o un ataque. Su efecto es constante mientras estén equipados: determinan la velocidad de navegación, la capacidad de carga de la tripulación, y la resistencia en combate naval. No requieren coste de PE ni declaración de uso. Son, por definición, cards pasivas.

---

## 2. Barcos como Cards

### 2.1 Card Type `barco`

El tipo `barco` se añadió al ENUM de `game_cards.card_type` mediante la migración `migrate_cards_barco.php`:

```php
// migrate_cards_barco.php
$db->write_query("ALTER TABLE {$table} MODIFY COLUMN card_type
    ENUM('tecnica', 'equipo', 'akuma_no_mi', 'haki', 'npc_menor', 'barco') NOT NULL;");
```

**Migración:** La columna `card_type` se modificó para incluir `'barco'` al final del ENUM existente. Esto es un ALTER TABLE no disruptivo: las cards existentes de otros tipos conservan su valor.

### 2.2 Diferencias con otros Tipos de Card

| Aspecto | `barco` | `equipo` | `tecnica` | `npc_menor` |
|---------|---------|----------|-----------|-------------|
| Slot de inventario | `barco` (único) | `carga` (múltiple) | Ninguno | `companero` |
| Activación | `pasiva` | Activa/pasiva | Activa/reactiva | Depende |
| ¿Se juega en posts? | No directamente* | Sí | Sí | Sí |
| Afecta navegación | Sí (velocidad, resistencia) | No | No | No |
| Peso en CC | 0 (usa slot propio) | 1-4 | N/A | 0 |
| Límite por personaje | 1 | Según CC | Ilimitadas | 1-2 |
| Coste PE | `—` | Variable | Variable | Variable |

*\*El barco no se "juega" como card activa en posts, pero sus efectos se aplican automáticamente en el sistema de navegación. Su resistencia puede ser relevante en combate naval narrativo.*

### 2.3 Cómo se Comporta un Barco como Card

Un barco en el sistema:

1. **Se posee** como cualquier otra card: `INSERT INTO game_character_cards (character_id, card_id, current_rank, assigned_by, cantidad) VALUES (...)`.
2. **Se equipa** en el slot `barco` del inventario. Solo 1 a la vez.
3. **No se juega** en formularios de post (no aparece en el selector de cards activas).
4. **No consume PE** — es una card pasiva.
5. **Está siempre activa** mientras esté equipada.
6. **Sus estadísticas** se leen desde `effects_json` para cálculos de navegación.
7. **Aparece en snapshots** de posts (`equipped_snapshot_json`) automáticamente.

```json
// Ejemplo de barco en la respuesta de inventory_get.php
{
    "card_id": 101,
    "name": "Bergantín Veloz",
    "card_type": "barco",
    "rank": "C",
    "description": "Un bergantín ligero y rápido, ideal para la Grand Line.",
    "image_url": "https://i.imgur.com/bergantin.png",
    "peso": 0,
    "cantidad": 1,
    "is_equipped": true
}
```

### 2.4 Validación en Equipamiento

El sistema valida que la card sea de tipo `barco` antes de permitir equiparla en el slot `barco`:

```php
// En inventory_toggle.php
$type = $card['card_type'];
if (!in_array($type, ['equipo', 'npc_menor', 'barco'], true)) {
    GameAjax::fail(400, 'Este tipo de carta no se puede equipar.');
}

// Determinar slot_type
$slot_type = 'carga';
if ($type === 'npc_menor') $slot_type = 'companero';
elseif ($type === 'barco') $slot_type = 'barco';

// Validar límite de barco (máximo 1)
if ($barcos_count >= 1) {
    GameAjax::fail(400, 'Ya tienes un barco activo. Desactiva el barco actual primero para equipar uno nuevo.');
}
```

### 2.5 ¿Por qué `peso = 0` para barcos?

El peso del barco es 0 en `game_cards.peso` porque no ocupa espacio en la Capacidad de Carga (slot `carga`). El barco tiene su propio slot dedicado (`barco`), separado del equipamiento personal. Esto evita que el barco compita con armas y armaduras por el limitado CC del personaje.

---

## 3. Categorías Narrativas (Rangos D → SS)

### 3.1 Mapeo de Categorías a Rangos

Las categorías narrativas de barcos en el universo One Piece se mapean directamente a los rangos de card D → SS. Cada rango implica no solo poderío del barco, sino también **dónde puede navegar** y **cómo se adquiere**.

| Rango | Categoría Narrativa | Zonas Accesibles | Ejemplo Canónico | Tier |
|:-----:|---------------------|------------------|-----------------|:----:|
| D | Barca / Balsa | Solo Blues | Balsa de Shanks (inicio) | 1 |
| C | Bergantín / Carabela | Blues + Grand Line | Going Merry | 1-2 |
| B | Galeón / Fragata | Cualquier zona | Thousand Sunny | 2-3 |
| A | Navío de Guerra / Acorazado | Cualquier zona | Moby Dick, Oro Jackson | 3-4 |
| S | Barco Legendario | Cualquier zona + bonus | Shirohige, Navío de Roger | 4-5 |
| SS | Barco Épico/Mítico | Cualquier zona + bonus máx | Pluton (armas ancestrales) | 5 |

### 3.2 Rango D — Barca / Balsa

**Descripción narrativa:** Embarcaciones pequeñas, frágiles, sin tecnología avanzada. Una balsa de madera atada con cuerdas, un bote de remos, una canoa. Son lo que un personaje construye con sus propias manos al empezar su viaje.

**Mecánicas:**
- `velocidad_base`: 1-3 (muy lento)
- `resistencia` (hull): 20-50
- `capacidad_carga`: 5-10 toneladas
- `tripulación_max`: 1-3 personas
- `cañones`: 0 (sin armamento)
- `nav_bonus`: 0 en todas las zonas

**Zonas accesibles:** Solo Blues (East Blue, West Blue, North Blue, South Blue). No puede ingresar a Grand Line porque el casco no resiste las corrientes ni los cambios climáticos extremos.

**Adquisición:** Compra en tienda por 500-2000 Berries, o construcción inicial mediante oficio Carpintero (`oficio_slug = 'carpintero'`) en grado I.

**Filosofía de diseño:** El rango D es el "tutorial" de barcos. Todo personaje empieza con uno (o puede comprarlo barato). Es funcional pero extremadamente limitado. Fuerza al jugador a buscar una mejora si quiere explorar más allá de su mar de origen.

### 3.3 Rango C — Bergantín / Carabela

**Descripción narrativa:** Velero mediano de dos mástiles, maniobrable, con capacidad oceánica básica. Puede almacenar provisiones para viajes medios y tiene espacio para una pequeña tripulación. Es el barco típico de un pirata novato que deja su Blues natal.

**Mecánicas:**
- `velocidad_base`: 4-6
- `resistencia` (hull): 80-150
- `capacidad_carga`: 20-40 toneladas
- `tripulación_max`: 5-15 personas
- `cañones`: 2-6 (batería ligera)
- `nav_bonus`: Grand Line +1, New World -2, Calm Belt -3

**Zonas accesibles:** Blues + Grand Line (con Log Pose). Puede sobrevivir en Grand Line si tiene un navegante competente, pero sufre penalizaciones en New World y Calm Belt.

**Adquisición:**
- Tienda: 5,000-20,000 Berries
- Solicitud al staff con justificación narrativa
- Recompensa de misión menor

**Ejemplo canónico:** Going Merry (el primer barco de los Sombrero de Paja).

### 3.4 Rango B — Galeón / Fragata

**Descripción narrativa:** Navío grande de 3 mástiles, robusto y espacioso. Diseñado para travesías largas y climas adversos. Tiene bodega amplia, camarotes para la tripulación, y espacio para taller de oficios.

**Mecánicas:**
- `velocidad_base`: 5-7
- `resistencia` (hull): 200-350
- `capacidad_carga`: 60-100 toneladas
- `tripulación_max`: 20-50 personas
- `cañones`: 8-16 (batería media)
- `nav_bonus`: Grand Line +2, New World +0, Calm Belt -1

**Zonas accesibles:** Cualquier zona, pero con penalización en Calm Belt si no tiene remos o sistema de propulsión alternativo.

**Adquisición:**
- Tienda (categoría `naval`): 50,000-200,000 Berries
- Asignación por staff como recompensa de arco narrativo
- Construcción por oficio Carpintero grado III+

**Ejemplo canónico:** Thousand Sunny (el barco actual de los Sombrero de Paja), Victoria Punk (barco de Buggy).

### 3.5 Rango A — Navío de Guerra / Acorazado

**Descripción narrativa:** Embarcación de guerra masiva, con blindaje reforzado, múltiples baterías de cañones, y capacidad para tropas. Diseñada por Marines, piratas poderosos, o gobiernos. Es un barco de combate, no de exploración.

**Mecánicas:**
- `velocidad_base`: 6-8
- `resistencia` (hull): 400-700
- `capacidad_carga`: 100-200 toneladas
- `tripulación_max`: 50-200 personas
- `cañones`: 20-50 (batería pesada)
- `nav_bonus`: Grand Line +3, New World +1, Calm Belt +0

**Zonas accesibles:** Cualquier zona. Su blindaje le permite incluso incursiones limitadas en Calm Belt si tiene un medio de propulsión (remo, paddle).

**Adquisición:**
- NO disponible en tienda pública
- Solo por asignación directa del staff (misiones de alto nivel, arcos narrativos importantes)
- Requiere justificación narrativa extensa
- Comúnmente asociado a rangos altos (Marine: Vicealmirante+, Pirata: Capitán+)

**Ejemplo canónico:** Moby Dick (Barbablanca), Red Force (Shanks), navíos de Almirantes.

### 3.6 Rango S — Barco Legendario

**Descripción narrativa:** Embarcaciones únicas en el mundo, con historias que trascienden generaciones. Construidas con materiales exóticos (Madera del Árbol Adam, piedra lunar), con capacidades sobrenaturales o tecnología imposible. Son barcos de personajes verdaderamente legendarios.

**Mecánicas:**
- `velocidad_base`: 8-12
- `resistencia` (hull): 800-1500
- `capacidad_carga`: 200-500 toneladas
- `tripulación_max`: 100-500 personas
- `cañones`: 40-100+
- `nav_bonus`: Grand Line +4, New World +3, Calm Belt +2
- Puede tener `special_abilities` (ej: navegar bajo tormentas, invisibilidad, vuelo limitado)

**Zonas accesibles:** Cualquier zona sin restricciones. El barco legendario desafía las leyes naturales.

**Adquisición:**
- Exclusivamente por evento especial, misión de staff, o arco argumental
- Nunca está en tienda
- Puede requerir una cadena de misiones (quest) o un evento en vivo
- Solo 1-2 por foro activo (rareza controlada)

**Ejemplo canónico:** Oro Jackson (barco de Gol D. Roger), Shirohige (antes de la guerra de Marineford).

### 3.7 Rango SS — Barco Épico / Mítico / Arma Ancestral

**Descripción narrativa:** No son barcos: son armas vivientes, artefactos de destrucción masiva o navíos construidos con tecnología perdida. Pluton, Poseidon, Uranus si fueran barcos, o equivalentes foro-creados con poder descomunal. Su sola presencia altera el equilibrio de poder.

**Mecánicas:**
- `velocidad_base`: 10-15+
- `resistencia` (hull): 2000-5000
- `capacidad_carga`: 500-2000 toneladas
- `tripulación_max`: 500+
- `cañones`: 100+ (o armamento especial: rayos, explosiones, cañones de energía)
- `nav_bonus`: Grand Line +5, New World +5, Calm Belt +5
- `special_abilities`: 2-4 habilidades únicas

**Zonas accesibles:** Cualquier zona. El barco SS es temido incluso por losmares más peligrosos.

**Adquisición:**
- Evento global (toda la comunidad)
- Arco argumental central de la temporada
- Decisión unánime del staff
- Prácticamente irrepetible (1 en la historia del foro)

---

## 4. Campos de Barco — Effects JSON

### 4.1 Estructura Completa de `effects_json` para `barco`

```json
{
    "barco_tipo": "bergantin",
    "velocidad_base": 6,
    "resistencia": 150,
    "capacidad_carga": 30,
    "tripulacion_max": 12,
    "cañones": 4,
    "maniobrabilidad": 3,
    "nav_bonus_east_blue": 0,
    "nav_bonus_west_blue": 0,
    "nav_bonus_north_blue": 0,
    "nav_bonus_south_blue": 0,
    "nav_bonus_grand_line": 1,
    "nav_bonus_new_world": -2,
    "nav_bonus_calm_belt": -3,
    "nav_bonus_florian_triangle": -1,
    "special_abilities": [
        "vela_mejorada: +1 velocidad con viento fuerte",
        "casco_reforzado: +50 resistencia en Grand Line"
    ],
    "mejoras": ["vela_mejorada"],
    "durabilidad_actual": 150,
    "durabilidad_max": 150
}
```

### 4.2 Descripción de Campos

#### `barco_tipo` — Tipo narrativo del barco
- VARCHAR. Subtipo visual/narrativo.
- Valores permitidos: `balsa`, `carabela`, `bergantin`, `galera`, `fragata`, `galeon`, `navio`, `acorazado`, `submarino`.
- Se usa en el frontend para mostrar el icono/badge correspondiente.
- No tiene impacto mecánico directo, pero el staff debe elegir uno coherente con el rango.
- Almacenado en la lista de opciones del formulario de propuesta de barco:
```html
<select id="req_barco_type" class="textbox rpg-form-input">
    <option value="navio">Navío</option>
    <option value="carabela">Carabela</option>
    <option value="galera">Galera</option>
    <option value="fragata">Fragata</option>
    <option value="bergantin">Bergantín</option>
    <option value="acorazado">Acorazado</option>
    <option value="submarino">Submarino</option>
    <option value="balsa">Balsa</option>
</select>
```

#### `velocidad_base` — Velocidad base del barco
- FLOAT/INT, default 5 en staff, 1-3 para D, 4-6 para C, 5-7 para B, 6-8 para A, 8-12 para S, 10-15 para SS.
- Es la velocidad CRUDA del barco sin modificadores de navegante ni instrumentos.
- Se usa como valor base en `game_nav_effective_speed()`:
```php
$base = (float)($shipEffects['velocidad_base'] ?? $shipEffects['velocidad'] ?? 5);
```
- Compatibilidad retro: si el campo se llamaba `velocidad` (legacy), el sistema lo lee como fallback.

#### `resistencia` — Hull / PV estructural del barco
- INT. Puntos de vida del barco. Representa la integridad estructural.
- Rangos típicos: D: 20-50, C: 80-150, B: 200-350, A: 400-700, S: 800-1500, SS: 2000-5000.
- Se usa en combate naval narrativo y en eventos de navegación que dañan el casco.
- El campo `durabilidad_actual` puede diferir de `resistencia` si el barco ha sufrido daño.

#### `capacidad_carga` — Tonelaje máximo
- INT. Toneladas que puede transportar el barco (carga, provisiones, tripulación con equipo).
- No es lo mismo que la Capacidad de Carga (CC) del personaje — esta es carga del BARCO, no del personaje.
- Relevante para:
  - Determinar si el barco puede transportar ciertos objetos grandes (cañones extra, materiales de construcción).
  - Viajes largos: un barco con poca capacidad necesita repostar más seguido.
  - Tripulaciones: la carga incluye provisiones para la tripulación.

#### `tripulacion_max` — Máximo de tripulantes
- INT. Número máximo de personas que el barco puede alojar cómodamente (con camarotes, provisiones, espacio).
- Relevante para tripulaciones: una tripulación con más miembros que `tripulacion_max` sufre penalizaciones narrativas (hacinamiento, falta de provisiones).
- Un barco D solo lleva 1-3 personas. Un SS puede llevar ejércitos.

#### `cañones` — Poder de fuego
- INT. Número de cañones/armamento montado.
- No es un stat de daño directo, sino un indicador de capacidad ofensiva naval.
- Se usa en:
  - Combate naval narrativo (más cañones = ventaja al describir bombardeos).
  - Eventos de navegación (ej: "Un Kraken ataca" — tener cañones permite defenderte).
  - Disuasión narrativa (un barco con 50 cañones inspira respeto).

#### `maniobrabilidad` — Facilidad de maniobra
- INT, 1-5. Qué tan ágil es el barco.
- 1: Torpe (acorazado, barcos muy grandes).
- 3: Media (galeón, fragata).
- 5: Muy ágil (balsa pequeña, bergantín ligero).
- Se usa en:
  - Esquivar obstáculos en navegación.
  - Combate naval (maniobras evasivas).
  - Eventos que requieren reflejos (tormenta, remolino).

#### `nav_bonus_*` — Bonificaciones por zona marítima
- INT (puede ser negativo). Modificador de velocidad por zona marítima.
- El sistema calcula la velocidad efectiva así:
```php
function game_nav_effective_speed(array $shipEffects, string $seaZone, int $navigatorRank, string $instrument): float
{
    $base = (float)($shipEffects['velocidad_base'] ?? $shipEffects['velocidad'] ?? 5);
    if ($base <= 0) $base = 5.0;
    $zoneKey = 'nav_bonus_' . preg_replace('/[^a-z_]/', '', $seaZone);
    $zoneMod = (float)($shipEffects[$zoneKey] ?? 0);
    $navMod = game_oficio_rank_bonus($navigatorRank);
    $instrumentBonus = match ($instrument) {
        'compass' => 0.0,
        'log_pose' => 0.5,
        'eternal_pose' => 1.0,
        default => -GAME_NAV_NO_INSTRUMENT_SPEED_PENALTY, // -1.0
    };
    return max(1.0, $base + $zoneMod + $navMod + $instrumentBonus);
}
```
- Zonas disponibles (de `game_nav_sea_zone_labels()`):
  - `east_blue`, `west_blue`, `north_blue`, `south_blue`
  - `grand_line`, `new_world`, `calm_belt`, `florian_triangle`

#### `special_abilities` — Array de habilidades especiales
- Array de strings. Cada entrada describe una capacidad única del barco.
- Formato: `"nombre: descripción mecánica"`.
- No hay un catálogo cerrado — el staff define habilidades especiales caso por caso.
- Ejemplos comunes:
  - `"vela_mejorada: +1 velocidad con viento favorable"`
  - `"casco_reforzado: +50 resistencia en Grand Line"`
  - `"galga_submarina: puede sumergirse por 10 minutos"`
  - `"cañón_de_rayo: ataque especial 1 vez por viaje"`
  - `"taller_de_armas: permite reparar armas durante el viaje"`
- Las habilidades se listan en el frontend pero no se procesan automáticamente — son narrativas con impacto mecánico definido por el staff.

#### `mejoras` — Array de slugs de mejoras instaladas
- Array de strings. Slugs de mejoras activas en el barco.
- Las mejoras se añaden mediante el sistema de Puntos Destino (PD) o eventos.
- Ejemplos: `["vela_mejorada", "casco_acero", "caniones_extra"]`.
- Ver sección [11. Mejoras de Barco](#11-mejoras-de-barco).

#### `durabilidad_actual` y `durabilidad_max`
- INT. Estado actual del barco. `durabilidad_actual` se reduce con eventos de navegación adversos y combate naval.
- `durabilidad_max` es el límite superior (normalmente igual a `resistencia` inicial).
- Se actualiza mediante comandos de staff o sistemas de reparación.
- Cuando `durabilidad_actual <= 0`, el barco está Hundido/Inutilizable.

### 4.3 Formulario de Staff para Crear Barcos

El staff crea barcos desde `cartas_staff.php` con campos específicos (líneas 315-364):

```html
<div id="fields-barco" class="rpg-staff-field-section">
    <div>
        <label>Tipo de Barco</label>
        <select id="barco_type">
            <option value="navio">Navío</option>
            <option value="carabela">Carabela</option>
            <option value="galera">Galera</option>
            <option value="fragata">Fragata</option>
            <option value="bergantin">Bergantín</option>
            <option value="acorazado">Acorazado</option>
            <option value="submarino">Submarino</option>
            <option value="balsa">Balsa</option>
        </select>
    </div>
    <div>
        <label>Tier</label>
        <input type="number" id="barco_tier" min="1" value="1">
    </div>
    <div>
        <label>Vida</label>
        <input type="number" id="barco_vida" min="0" value="100">
    </div>
    <div>
        <label>Ataque</label>
        <input type="number" id="barco_ataque" min="0" value="0">
    </div>
    <div>
        <label>Velocidad</label>
        <input type="number" id="barco_velocidad" min="0" value="0">
    </div>
    <div>
        <label>Resistencia</label>
        <input type="number" id="barco_resistencia" min="0" value="0">
    </div>
    <div>
        <label>Velocidad base (navegación)</label>
        <input type="number" id="barco_velocidad_base" min="1" value="5">
    </div>
    <div>
        <label>Bonus Grand Line</label>
        <input type="number" id="barco_nav_grand_line" value="0">
    </div>
    <div>
        <label>Bonus New World</label>
        <input type="number" id="barco_nav_new_world" value="0">
    </div>
    <div>
        <label>Bonus Calm Belt</label>
        <input type="number" id="barco_nav_calm_belt" value="0">
    </div>
</div>
```

Estos campos se serializan a `effects_json` al guardar la card.

---

## 5. Equipamiento en Slot Barco

### 5.1 El Slot `barco`

La tabla `game_character_inventory` tiene 3 slot types: `carga`, `companero`, `barco`.

```sql
slot_type ENUM('carga', 'companero', 'barco') NOT NULL,
```

El slot `barco` tiene estas características:
- **Límite:** Siempre 1. No hay perks que lo amplíen.
- **Peso:** Siempre 0 (no contribuye a CC).
- **Cards permitidas:** Solo tipo `barco`.
- **Toggle:** Usa el mismo endpoint `inventory_toggle.php` que los otros slots.

### 5.2 Flujo de Equipamiento

```
1. Jugador hace clic en "Equipar" en una card de tipo barco
2. inventory_toggle.php recibe character_id + card_id
3. Validaciones:
   a. Personaje existe y pertenece al usuario
   b. Card existe y es tipo 'barco'
   c. Personaje posee la card (en game_character_cards)
   d. Si ya equipada → DELETE (desequipar)
   e. Si no equipada:
      - Contar barcos equipados: SELECT COUNT(*) FROM game_character_inventory
        WHERE character_id = X AND slot_type = 'barco'
      - Si count >= 1 → error "Ya tienes un barco activo"
      - Si count < 1 → INSERT
4. Respuesta: { equipped: true/false, card_id: N }
```

### 5.3 Validación en Navegación

Cuando un personaje inicia un viaje, el sistema verifica que el barco esté equipado:

```php
// navigation_process.php, línea 71-75
$equipped = $db->query("SELECT 1 FROM {$prefix}game_character_inventory
    WHERE character_id = " . (int)$characterId . "
      AND card_id = " . (int)$shipCardId . "
      AND slot_type = 'barco' LIMIT 1");
if (!$db->num_rows($equipped)) {
    return null; // El barco no está equipado, no se puede navegar
}
```

### 5.4 Vista en el Inventario

En el inventario del personaje (`personaje_inventory.js`), el slot barco se muestra con icono de barco:

```html
<div class="rpg-inv-slot-card">
    <div class="rpg-inv-slot-icon"><i class="fas fa-ship"></i></div>
    <div class="rpg-inv-slot-desc">
        <span class="rpg-inv-slot-lbl">BARCO ACTIVO</span>
        <strong id="rpg-inv-barco-display">0 / 1</strong>
    </div>
</div>
```

Los barcos del inventario se filtran con el botón "Barcos":

```html
<button type="button" class="rpg-inv-filter-btn" data-filter="barco">Barcos</button>
```

---

## 6. Adquisición de Barcos

### 6.1 Compra en Tienda

Los barcos de rango D, C y ocasionalmente B están disponibles en la tienda del foro.

**Requisitos en `game_cards`:**
```sql
in_shop = 1,
shop_category = 'naval',
cost_berries = <precio>,
card_type = 'barco'
```

**Validación en compra (`tienda_comprar.php`):**
```php
// Los barcos son objetos únicos (no consumibles), así que:
if (!$is_consumable) {
    $check_owned = $db->query("SELECT 1 FROM {$prefix}game_character_cards
        WHERE character_id = {$character_id} AND card_id = {$card_id} LIMIT 1");
    if ($db->num_rows($check_owned) > 0) {
        GameAjax::fail(400, "Ya posees el barco: {$card['name']}.");
    }
    $qty = 1;
}
```

**Precios orientativos:**
| Rango | Precio (Berries) |
|:-----:|:----------------:|
| D | 500 - 2,000 |
| C | 5,000 - 20,000 |
| B | 50,000 - 200,000 |
| A+ | No disponible en tienda |

### 6.2 Asignación por Staff

Los barcos de rango A, S y SS se asignan directamente por el staff mediante:

1. **Solicitud de jugador** (`game_card_requests` con `request_type = 'add_existing'` o `'create'`):
   - El jugador selecciona "Barco" en el tipo de card a solicitar.
   - Rellena los campos: tipo, tier, vida, ataque, velocidad, resistencia.
   - El staff revisa la solicitud y, si aprueba, asigna la card.

2. **Asignación directa por staff** (desde panel de staff):
   - El staff crea la card en `game_cards` con `card_type = 'barco'`.
   - Asigna al personaje mediante `cards_assign.php`.

### 6.3 Recompensa de Misión/Evento

- **Misiones:** Un arco argumental puede recompensar con un barco único.
- **Eventos comunitarios:** Torneos, juegos, eventos en vivo.
- **Misiones de oficio:** Un Carpintero grado III+ puede construir barcos como parte de una misión gremial.

### 6.4 Construcción (Oficio Carpintero)

El oficio `carpintero` (slug: `carpintero`) permite construir y reparar barcos.

**Requisitos mecánicos para construir:**
- Grado I: Balsas y botes (rango D)
- Grado II: Carabelas y bergantines pequeños (rango C-)
- Grado III: Bergantines completos, fragatas ligeras (rango C/B)
- Grado IV+: Galeones, navíos completos (rango B/A)

**Flujo de construcción (narrativo + staff):**
1. Personaje con oficio Carpintero reúne materiales (vía tienda o roleo).
2. El jugador describe la construcción en posts.
3. Al completar, solicita al staff la creación de la card `barco`.
4. El staff crea la card con stats determinados por la calidad narrativa de la construcción.

### 6.5 Tabla Comparativa de Métodos de Adquisición

| Método | Rangos | Tiempo | Coste | Staff Required |
|--------|:------:|:------:|:-----:|:--------------:|
| Tienda | D, C, B (ocasional) | Instantáneo | Berries | No |
| Solicitud jugador | C, B, A | 1-7 días | 0 Berries | Sí |
| Asignación staff | A, S, SS | Variable | 0 Berries | Sí |
| Misión/evento | C, B, A | Semanas | Narrativo | Sí |
| Construcción (carpintero) | D, C, B | Semanas | Materiales | Sí (crear card) |

---

## 7. Integración con el Sistema de Navegación

### 7.1 Visión General

El barco es el componente central del sistema de navegación. Sin barco equipado, no se puede viajar. El sistema de navegación (Sección 14) usa los atributos del barco para calcular:

1. **Velocidad efectiva** — qué tan rápido se mueve el barco.
2. **Duración del viaje** — días rol que toma llegar.
3. **Eventos de navegación** — qué probabilidad hay de encontrar fenómenos.
4. **Nivel de peligro** — qué zonas puede atravesar el barco.

### 7.2 Flujo Completo de un Viaje

```
1. Jugador crea un post en un foro-isla
2. En el formulario RPG, selecciona:
   - Barco (de los equipados)
   - Destino (isla)
   - Instrumento de navegación (brújula, Log Pose, Eternal Pose)
3. PHP procesa (navigation_process.php):
   a. Valida que el post esté en un hilo "Presente"
   b. Valida que el barco existe y está equipado
   c. Obtiene isla origen (forum_id del post)
   d. Obtiene isla destino
   e. Calcula distancia (ruta precalculada o coordenadas)
   f. Calcula peligro (base_danger de islas + waypoints)
   g. Determina sea_zone
   h. Calcula velocidad efectiva (velocidad_base + nav_bonus + navegante + instrumento)
   i. Calcula duración (distancia / (velocidad_efectiva * GAME_NAV_SPEED_FACTOR))
   j. Calcula número de eventos (según peligro y duración)
   k. INSERT en game_navigation_voyages
   l. Genera eventos (INSERT en game_navigation_events + game_post_oracles)
4. El viaje queda en estado 'active' (pendiente de revisión staff)
5. Staff revisa y aprueba/deniega el viaje
6. Si aprobado → personaje llega a destino
```

### 7.3 Cálculo de Velocidad Efectiva

La velocidad efectiva es la combinación de 4 factores:

```
Velocidad Efectiva = velocidad_base + nav_bonus_zona + bonus_navegante + bonus_instrumento
```

**Factor 1: `velocidad_base` del barco**
- Rango D: 1-3, Rango C: 4-6, Rango B: 5-7, Rango A: 6-8, Rango S: 8-12, Rango SS: 10-15
- Es la línea base. Barcos más rápidos viajan más lejos por día rol.

**Factor 2: `nav_bonus_*` por zona marítima**
- Un barco de rango C tiene `nav_bonus_grand_line = 1` pero `nav_bonus_new_world = -2`.
- Esto significa que en New World su velocidad se reduce en 2 puntos (el barco no está diseñado para ese mar).
- Un barco de rango A tiene `nav_bonus_new_world = 1`, funcionando bien en cualquier zona.

**Factor 3: Grado del oficio Navegante**
```php
$navigatorRank = game_oficio_get_rank($characterId, 'navegante');
$navMod = game_ofcio_rank_bonus($navigatorRank);
```

El bonus por rango de navegante:
| Grado | Bonus | Descripción |
|:-----:|:-----:|-------------|
| 0 (sin) | 0 | Sin navegante, velocidad base sin bonus |
| I | 1 | Conocimientos básicos de navegación |
| II | 2 | Puede leer corrientes y vientos |
| III | 3 | Navegación competente en Grand Line |
| IV | 4 | Navegación experta, mitiga eventos |
| V | 5 | Navegante de élite, evita casi todo |

**Factor 4: Instrumento de navegación**
```php
$instrumentBonus = match ($instrument) {
    'compass' => 0.0,
    'log_pose' => 0.5,
    'eternal_pose' => 1.0,
    default => -GAME_NAV_NO_INSTRUMENT_SPEED_PENALTY, // -1.0
};
```

| Instrumento | Bonus | Dónde funciona |
|-------------|:-----:|---------------|
| Ninguno | -1.0 | En ningún mar (penalización) |
| Brújula | 0.0 | Blues solamente |
| Log Pose | +0.5 | Grand Line (esencial) |
| Eternal Pose | +1.0 | Cualquier zona (apunta a isla fija) |

**Ejemplo completo:**
```
Barco: Bergantín Veloz (rango C)
  velocidad_base = 6
  nav_bonus_grand_line = 1
Navegante: Grado III (bonus = 3)
Instrumento: Log Pose (bonus = 0.5)

Velocidad Efectiva = 6 + 1 + 3 + 0.5 = 10.5

Distancia al destino: 200 unidades (coordenadas)
Duración = ceil(200 / (10.5 × 10)) = ceil(200/105) = 2 días rol
```

### 7.4 Cálculo de Peligro

El barco determina qué zonas puede atravesar a través del `base_danger` de las islas:

| Danger | Zona típica | Barco mínimo requerido |
|:------:|-------------|:----------------------:|
| 1 | Blues | D |
| 2 | Grand Line baja | C |
| 3 | Grand Line alta | C/B |
| 4 | New World / Calm Belt | B/A |
| 5 | New World profundo / Florian Triangle | A/S |

El cálculo de peligro usa una interpolación entre el peligro máximo y promedio de las islas en la ruta:

```php
$max = max($dangers);
$avg = array_sum($dangers) / count($dangers);
$interpolated = ($max * 0.4) + ($avg * 0.6);
return max(1, min(5, (int)round($interpolated)));
```

### 7.5 Eventos de Navegación

El número de eventos que ocurren durante un viaje depende del nivel de peligro y la duración:

```php
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
    if ($duration >= 5) $base++;
    if ($duration >= 10) $base++;
    if ($withRandom) $base += mt_rand(0, 2);
    return max(GAME_NAV_EVENTS_MIN, min(GAME_NAV_EVENTS_MAX, $base));
}
```

Los eventos se resuelven con oráculos de navegación específicos por zona:
- `nav_1_2`: eventos para Blues (danger 1-2)
- `nav_3`: eventos para Grand Line (danger 3)
- `nav_4_5`: eventos para New World (danger 4-5)

Cada evento tiene 5 posibles resultados: Favorable, Moderado, Severo, Extremo, Singular. El rango de navegante puede mitigar eventos:

```php
// Grado 3+: Tirar dos veces y quedarse con el mejor (menor)
if ($navigatorRank >= 3) {
    $rollResult2 = game_roll_oracle($oracle, $category);
    if ($rollResult2['roll'] < $rollResult['roll']) {
        $rollResult = $rollResult2;
    }
}

// Grado 5: Inmunidad a eventos Moderados → Favorables
// Grado 4: Mitiga Extremo → Severo, Severo → Moderado
// Grado 2: Mitiga Extremo → Severo, Severo → Moderado, Moderado → Favorable
```

### 7.6 La Tabla `game_navigation_voyages`

```sql
CREATE TABLE mybb_game_navigation_voyages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    thread_id INT NOT NULL,
    character_id INT NOT NULL,
    ship_card_id INT NOT NULL,        -- FK a game_cards.id del barco usado
    island_from_fid INT UNSIGNED NOT NULL,
    island_to_fid INT UNSIGNED NOT NULL,
    distance INT NOT NULL,             -- Distancia calculada
    danger_level TINYINT UNSIGNED NOT NULL,  -- 1-5
    duration_days INT NOT NULL,        -- Duración en días rol
    num_events INT NOT NULL,           -- Eventos generados
    navigator_bonus TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- Grado de navegante
    instrument_used VARCHAR(100) DEFAULT NULL,  -- Instrumento seleccionado
    instrument_bonus TINYINT NOT NULL DEFAULT 0,   -- Bonus del instrumento
    raw_calculation_json TEXT DEFAULT NULL,   -- Cálculos completos (debug/auditoría)
    status ENUM('active','arrived','cancelled') NOT NULL DEFAULT 'active',
    staff_review ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    start_rol_days INT UNSIGNED NOT NULL DEFAULT 0,
    expected_end_rol_days INT UNSIGNED NOT NULL DEFAULT 0,
    reviewed_at INT UNSIGNED DEFAULT NULL,
    reviewed_by_uid INT UNSIGNED DEFAULT NULL,
    staff_notice_post_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_post (post_id),
    KEY idx_char (character_id),
    KEY idx_thread (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

El campo `ship_card_id` es la clave que conecta el viaje con el barco específico que se usó. El `raw_calculation_json` guarda una instantánea de los cálculos (incluyendo `ship_effects`) para auditoría del staff.

---

## 8. Database Schema

### 8.1 `game_cards` — Catálogo (para tipo `barco`)

```sql
-- Es la misma tabla que para todas las cards
-- Los campos específicos de barco van en effects_json

SELECT * FROM game_cards WHERE card_type = 'barco' \G

-- Ejemplo de fila:
id: 101
name: "Bergantín Veloz"
card_type: "barco"
rank: "C"
activation: "pasiva"
tags_json: '["navegacion","velero"]'
description: "Un bergantín ligero con velas mejoradas, ideal para la Grand Line."
cost_pe: "—"
execution_cost: 0
execution_stat: ""
dice: ""
effects_json: '{"barco_tipo":"bergantin","velocidad_base":6,"resistencia":150,"capacidad_carga":30,"tripulacion_max":12,"cañones":4,"maniobrabilidad":3,"nav_bonus_grand_line":1,"nav_bonus_new_world":-2,"nav_bonus_calm_belt":-3,"special_abilities":["vela_mejorada: +1 velocidad con viento fuerte"],"mejoras":[],"durabilidad_actual":150,"durabilidad_max":150}'
notes: "Barco inicial para personajes que salen de East Blue."
image_url: "https://i.imgur.com/bergantin.png"
cost_berries: 15000
in_shop: 1
shop_category: "naval"
peso: 0
tier: 1
created_by: 1
reposo: 0
duracion: 0
disciplina_slug: NULL
estilo_canonico_slug: NULL
oficio_slug: NULL
```

### 8.2 `game_character_inventory` — Slot Barco

```sql
-- Un barco equipado se ve así:
character_id: 42
card_id: 101
slot_type: "barco"
equipped_at: "2026-06-12 10:30:00"
peso: 0
```

### 8.3 `game_character_cards` — Posesión

```sql
-- Un barco en posesión:
character_id: 42
card_id: 101
current_rank: "C"  -- Puede diferir del rank de catálogo (ej: staff lo mejoró)
assigned_by: 1
cantidad: 1  -- Siempre 1 para barcos (no son consumibles)
assigned_at: "2026-06-10 14:00:00"
```

### 8.4 `game_navigation_voyages` — Viaje

```sql
-- Viaje usando el barco 101:
ship_card_id: 101
raw_calculation_json: '{"island_from":{...},"island_to":{...},"route":{...},"danger":3,"ship_effects":{"barco_tipo":"bergantin","velocidad_base":6,...},"sea_zone":"grand_line","effective_speed":10.5,"navigator_rank":3}'
```

---

## 9. Implementación PHP — Navegación

### 9.1 `game_nav_ships_for_character()` — Obtener barcos del personaje

Función definida en `navigation_helpers.php` que devuelve los barcos equipados por un personaje:

```php
function game_nav_ships_for_character(int $characterId): array
{
    global $db;
    if ($characterId <= 0 || !$db->table_exists('game_character_inventory')) {
        return [];
    }
    $prefix = TABLE_PREFIX;
    $q = $db->query("
        SELECT c.id AS card_id, c.name, c.effects_json, c.image_url
        FROM {$prefix}game_character_inventory i
        JOIN {$prefix}game_cards c ON c.id = i.card_id
        WHERE i.character_id = " . (int)$characterId . "
          AND i.slot_type = 'barco'
          AND c.card_type = 'barco'
        ORDER BY i.equipped_at ASC
    ");
    $ships = [];
    while ($row = $db->fetch_array($q)) {
        $effects = json_decode($row['effects_json'] ?? '{}', true);
        if (!is_array($effects)) $effects = [];
        $ships[] = [
            'card_id' => (int)$row['card_id'],
            'name' => $row['name'],
            'image_url' => $row['image_url'] ?? '',
            'velocidad' => (int)($effects['velocidad_base'] ?? $effects['velocidad'] ?? 5),
            'effects' => $effects,
        ];
    }
    return $ships;
}
```

### 9.2 Endpoint AJAX: `navigation_ships.php`

```php
// game/ajax/navigation_ships.php
// GET ?character_id=N → devuelve los barcos equipados del personaje

GameAjax::json(true, ['ships' => game_nav_ships_for_character($charId)]);
```

**Respuesta:**
```json
{
    "ok": true,
    "data": {
        "ships": [
            {
                "card_id": 101,
                "name": "Bergantín Veloz",
                "image_url": "https://...",
                "velocidad": 6,
                "effects": {
                    "barco_tipo": "bergantin",
                    "velocidad_base": 6,
                    "nav_bonus_grand_line": 1,
                    ...
                }
            }
        ]
    }
}
```

### 9.3 `game_nav_effective_speed()` — Cálculo de Velocidad

```php
function game_nav_effective_speed(
    array $shipEffects,
    string $seaZone,
    int $navigatorRank,
    string $instrument
): float {
    $base = (float)($shipEffects['velocidad_base'] ?? $shipEffects['velocidad'] ?? 5);
    if ($base <= 0) $base = 5.0;

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

### 9.4 `game_nav_compute_voyage()` — Previsualización de Viaje

Función usada para mostrar al jugador una previsualización del viaje antes de confirmar:

```php
function game_nav_compute_voyage(
    int $fromFid, int $toFid,
    array $shipEffects,
    int $navigatorRank,
    string $instrument
): array {
    // Calcula distancia, peligro, velocidad efectiva, duración, rango de eventos
    // Devuelve: { ok, distance, danger_level, danger_label, effective_speed,
    //             duration_days, events_min, events_max, sea_zone, route }
}
```

### 9.5 `game_navigation_process_post()` — Procesamiento de Viaje

Función principal que se ejecuta cuando un personaje crea un post con navegación habilitada:

```php
function game_navigation_process_post(int $postId, int $threadId, int $characterId, array $input): ?int
{
    // 1. Validar que el post sea en hilo "Presente"
    // 2. Obtener shipCardId del input
    // 3. Validar que la isla destino existe y es distinta de la origen
    // 4. Validar que el barco existe (card_type='barco') y está equipado
    // 5. Leer shipEffects del barco
    // 6. Calcular ruta, distancia, peligro, zona marítima
    // 7. Calcular velocidad efectiva y duración
    // 8. Generar eventos de navegación
    // 9. INSERT en game_navigation_voyages
    // 10. Generar eventos si num_events > 0
    // 11. Devolver voyageId (o null si falló)
}
```

### 9.6 Validación de Equipamiento en Navegación

El sistema verifica TRES condiciones para permitir un viaje:

```php
// 1. El barco existe y es tipo 'barco'
$shipCard = $db->fetch_array($db->query(
    "SELECT * FROM {$prefix}game_cards WHERE id = " . (int)$shipCardId . " AND card_type = 'barco' LIMIT 1"
));

// 2. El barco está equipado en el slot barco del personaje
$equipped = $db->query(
    "SELECT 1 FROM {$prefix}game_character_inventory
     WHERE character_id = " . (int)$characterId . "
       AND card_id = " . (int)$shipCardId . "
       AND slot_type = 'barco' LIMIT 1"
);

// 3. El personaje posee el barco (opcional pero recomendado)
// (Por diseño: si está equipado, ya debería poseerlo)
```

---

## 10. Durabilidad y Reparación

### 10.1 Concepto de Durabilidad

Cada barco tiene dos campos en `effects_json`:

```json
{
    "durabilidad_actual": 150,
    "durabilidad_max": 150
}
```

- **`durabilidad_max`**: Valor máximo de durabilidad. Normalmente igual a `resistencia`.
- **`durabilidad_actual`**: Valor actual de durabilidad. Se reduce cuando el barco sufre daño.

### 10.2 Cuándo se Reduce la Durabilidad

1. **Eventos de navegación severos:** En eventos de navegación con resultado "Extremo" o "Singular", el barco puede recibir daño. El staff determina la cantidad.
2. **Combate naval:** Daño de cañones enemigos, ataques de criaturas marinas, etc.
3. **Condiciones climáticas extremas:** Tormentas en New World, hielo en zonas frías, calor extremo.
4. **Accidentes narrativos:** Choques contra arrecifes, ataques de monstruos marinos, tormentas eléctricas.

### 10.3 Efectos de Durabilidad Reducida

| Durabilidad % | Efecto |
|:-------------:|--------|
| 100%-75% | Normal. Sin penalizaciones. |
| 75%-50% | Velocidad reducida en -1. El barco navega más lento por daño estructural. |
| 50%-25% | Velocidad -1 adicional. Los eventos de navegación tienen +1 en tirada (mayor probabilidad de resultados adversos). |
| 25%-1% | Velocidad -2 adicional, eventos +2. El barco necesita reparación urgente o corre riesgo de hundirse. |
| 0% | Barco Hundido. No puede navegar hasta ser reparado (o se pierde permanentemente). |

### 10.4 Reparación de Barcos

**Método 1: Reparación por oficio Carpintero**
- El personaje debe tener oficio `carpintero` y rolear las reparaciones.
- Grado I: Repara 10-20 durabilidad por post de reparación.
- Grado III: Repara 30-50 durabilidad por post.
- Grado V: Repara 80-100 durabilidad o restaura completa si el daño es menor al 25%.

**Método 2: Puertos y astilleros**
- En islas con astillero (Water 7, islas con base_danger ≤ 2), se puede pagar por reparaciones.
- Coste: 10-50 Berries por punto de durabilidad recuperada.
- Tiempo: 1-3 días rol, dependiendo del daño total.

**Método 3: Staff (reparación narrativa)**
- Para barcos legendarios o daños en eventos de staff, el staff modifica manualmente el `effects_json`.

**Método 4: Puntos Destino (PD)**
- El jugador puede gastar PD (ver `tienda_destino.php`, categoría `barco_narrativo`) para mejoras o reparaciones especiales:
```php
// En pd_purchase.php:
'barco_narrativo' => 3,  // Coste: 3 PD por mejora/reparación narrativa
```

### 10.5 Flujo de Staff para Modificar Durabilidad

El staff actualiza `effects_json` directamente desde el panel de edición de cards:

```sql
UPDATE game_cards
SET effects_json = JSON_SET(effects_json,
    '$.durabilidad_actual', <nuevo_valor>,
    '$.durabilidad_max', <max>)
WHERE id = <card_id>;
```

O mediante un endpoint de staff:

```php
// Pseudo-código de endpoint staff para modificar durabilidad
$shipEffects = json_decode($card['effects_json'], true);
$shipEffects['durabilidad_actual'] = max(0, min(
    $shipEffects['durabilidad_max'],
    $shipEffects['durabilidad_actual'] - $damage
));
$db->write_query("UPDATE ... SET effects_json = '" . $db->escape_string(json_encode($shipEffects)) . "' ...");
```

### 10.6 Hundimiento y Pérdida del Barco

Cuando `durabilidad_actual <= 0`:

1. **El barco no puede equiparse** para navegar (validación en el sistema).
2. **El personaje debe repararlo o adquirir uno nuevo.**
3. Si el contexto narrativo lo justifica (explosión, naufragio total), el staff puede:
   - Marcar la card como perdida (DELETE de `game_character_cards` y `game_character_inventory`).
   - Exigir que el personaje adquiera un barco nuevo por medios narrativos.
4. Los barcos legendarios (S/SS) rara vez se pierden permanentemente — son demasiado importantes para la trama.

---

## 11. Mejoras de Barco (Upgrades)

### 11.1 Sistema de Mejoras

Las mejoras de barco se representan mediante el campo `mejoras` (array de strings) en `effects_json`:

```json
{
    "mejoras": ["vela_mejorada", "casco_acero"]
}
```

### 11.2 Catálogo de Mejoras

| Slug | Nombre | Efecto | Coste |
|------|--------|--------|:-----:|
| `vela_mejorada` | Velas Mejoradas | +1 velocidad_base | 2 PD o 10,000 Berries |
| `casco_acero` | Casco de Acero | +100 resistencia | 3 PD o 50,000 Berries |
| `casco_metal_Adam` | Casco de Metal Adam | +300 resistencia, inmune a daño menor | 5 PD |
| `caniones_extra` | Batería Extra | +4 cañones | 2 PD o 15,000 Berries |
| `canion_rayo` | Cañón de Rayo | Ataque especial 1x/viaje | 5 PD |
| `bodega_extra` | Bodega Ampliada | +20 capacidad_carga | 1 PD o 5,000 Berries |
| `submarino_emergencia` | Campana de Buceo | Puede sumergirse 10 min | 4 PD |
| `vela_grand_line` | Velas de Grand Line | +1 nav_bonus_grand_line | 3 PD |
| `timon_mejorado` | Timón de Precisión | +1 maniobrabilidad | 2 PD |
| `recubrimiento` | Recubrimiento de Resina | Permite navegar bajo el agua (como en Sabao) | 4 PD + 20,000 Berries |

### 11.3 Aplicación de Mejoras

1. **Compra con PD:** Desde `tienda_destino.php`, categoría `barco_narrativo`.
   ```php
   // pd_purchase.php línea 48:
   'barco_narrativo' => 3,  // coste base en PD
   ```
2. **Solicitud al staff:** El jugador solicita la mejora, el staff actualiza `effects_json`.
3. **Evento narrativo:** Mejora como recompensa de un arco argumental.

### 11.4 Límite de Mejoras

No hay un límite técnico al número de mejoras, pero el staff debería aplicar sentido común:
- Un barco rango D no debería tener más de 1 mejora.
- Un barco rango C: máximo 2-3 mejoras.
- Un barco rango B: máximo 4-5 mejoras.
- Un barco rango A: máximo 6-7 mejoras.
- Un barco rango S/SS: hasta 10 mejoras (son legendarios).

---

## 12. Barcos y Tripulaciones

### 12.1 Barco de Tripulación vs Barco Personal

Cada personaje puede tener su propio barco equipado. Pero en una tripulación, el barco principal es el de la tripulación.

**Barco de tripulación** (en `mybb_game_crews`):
```sql
ship_name VARCHAR(150) DEFAULT '',
ship_image_url VARCHAR(255) DEFAULT '',
ship_data TEXT,
```

La tripulación almacena su barco como datos descriptivos (no como card). Esto es porque el barco de tripulación es propiedad de la tripulación, no de un personaje individual.

**Diferencia clave:**
- El barco del personaje es una card equipada (`game_character_inventory`).
- El barco de la tripulación es metadata de la tripulación (`game_crews`).

Cuando un personaje viaja, usa SU barco equipado (su card). Pero narrativamente, suele viajar en el barco de la tripulación. La solución: el capitán (o quien tenga el barco) equipa la card del barco de la tripulación como su barco personal.

### 12.2 Vista del Barco en la Tripulación

En `_tab_navio.php` de la tripulación:

```php
<div class="rpg-crew-ship-container">
    <?php if (!empty($crew['ship_image_url'])): ?>
        <div class="rpg-crew-ship-image-wrapper">
            <img src="<?= htmlspecialchars($crew['ship_image_url']) ?>" alt="Navío" class="rpg-crew-ship-image">
        </div>
    <?php endif; ?>
    <div class="rpg-crew-ship-details">
        <h2 class="rpg-crew-ship-name">
            <?= htmlspecialchars($crew['ship_name'] ?: 'Sin nombre registrado') ?>
        </h2>
        <?php if (!empty($crew['ship_data'])): ?>
            <div class="rpg-crew-ship-description">
                <?= nl2br(htmlspecialchars($crew['ship_data'])) ?>
            </div>
        <?php else: ?>
            <p>Aún no hay detalles registrados sobre el barco de la tripulación.</p>
        <?php endif; ?>
    </div>
</div>
```

---

## 13. AJAX Endpoints — Catálogo

### 13.1 Endpoints Relacionados con Barcos

| Endpoint | Método | Propósito | Parámetros |
|----------|--------|-----------|------------|
| `navigation_ships.php` | GET | Barcos equipados del personaje | `character_id` (opcional, usa active_pj) |
| `inventory_get.php` | GET | Estado del inventario (incluye barcos) | `character_id` |
| `inventory_toggle.php` | POST | Equipar/desequipar barco | `character_id`, `card_id` |
| `tienda_comprar.php` | POST | Comprar barco | `character_id`, `cart[{card_id, cantidad}]` |
| `tienda_vender.php` | POST | Vender barco (50% precio) | `character_id`, `card_id`, `cantidad` |
| `cards_create.php` | POST | Crear card (staff) | Datos de la card |
| `cards_assign.php` | POST | Asignar card a personaje | `character_id`, `card_id` |
| `navigation_voyage_start.php` | POST | Iniciar viaje | `post_id`, `ship_card_id`, `destination`, `instrument` |
| `navigation_voyages_list.php` | GET | Listar viajes | Filtros varios |

### 13.2 Respuesta de `navigation_ships.php`

```json
{
    "ok": true,
    "data": {
        "ships": [
            {
                "card_id": 101,
                "name": "Bergantín Veloz",
                "image_url": "https://...",
                "velocidad": 6,
                "effects": {
                    "barco_tipo": "bergantin",
                    "velocidad_base": 6,
                    "resistencia": 150,
                    "nav_bonus_grand_line": 1,
                    "nav_bonus_new_world": -2
                }
            }
        ]
    }
}
```

### 13.3 Integración con Formulario de Post

Cuando un personaje crea un post con navegación, el frontend:
1. Llama a `navigation_ships.php` para obtener los barcos equipados.
2. Muestra un selector de barco en el formulario.
3. Muestra los atributos del barco seleccionado (velocidad, bonus, etc.).
4. Llama a `game_nav_compute_voyage()` para previsualizar la duración y eventos.
5. Al enviar, incluye `rpg_nav_ship` con el `card_id` del barco seleccionado.

---

## 14. Filosofía de Diseño

### 14.1 ¿Por qué los Barcos son Cards (no Tabla Separada)?

Este es el principio más importante del sistema de barcos. Las razones:

1. **Consistencia cognitiva:** Un jugador aprende a usar el sistema de cards para técnicas, equipo, Haki, compañeros, y barcos. No necesita aprender un sistema diferente para cada cosa. El concepto de "card" es el denominador común de TODO el poder mecánico del juego.

2. **Mantenibilidad del código:** Con barcos como cards, no hay que mantener:
   - Una tabla `game_barcos` separada con su propio CRUD.
   - Endpoints AJAX dedicados para barcos (usan los mismos de cards/inventario).
   - Validaciones duplicadas (equipamiento, peso, asignación).
   - Formularios de staff separados (usan `cartas_staff.php` con un campo `card_type = 'barco'`).

3. **Extensibilidad:** Si mañana se necesita un tipo `vehiculo` (coches, carruajes, etc.), se añade al ENUM de `card_type` y automáticamente tiene todo el pipeline: tienda, inventario, equipamiento, snapshot.

4. **Snapshot automático:** El barco equipado se incluye automáticamente en `equipped_snapshot_json` al crear posts. Si fuera una tabla separada, habría que escribir código adicional para capturar esa información.

5. **Búsqueda unificada:** Un staff que busca "¿tiene este personaje un barco?" puede consultar `game_character_cards JOIN game_cards WHERE card_type='barco'`. La misma query que para cualquier otro tipo de card.

### 14.2 ¿Por qué las Categorías Narrativas se Mapean a Rangos D→SS?

Las categorías narrativas de One Piece (Barca, Bergantín, Galeón, Navío de Guerra, Legendario) se mapean naturalmente a D→SS porque:

1. **Progresión clara:** D→SS es la escala de poder del sistema. Un barco D es débil, un barco SS es temible. No hay ambigüedad.
2. **Consistencia con el resto del sistema:** Las técnicas, equipos, Haki, y NPCs también usan D→SS. Un jugador ve "rango C" y sabe que es "común, nivel medio".
3. **Filtros y búsquedas:** Se puede filtrar barcos por rango en la tienda, en el inventario, en el catálogo.
4. **Balance:** Un barco rango D cuesta 500 Berries; un barco rango C cuesta 15,000. La progresión económica es natural.

### 14.3 ¿Por qué solo 1 Barco Equipado a la Vez?

- **Lógica narrativa:** Un personaje no puede navegar dos barcos simultáneamente. Es como tener dos cuerpos — no tiene sentido.
- **Coste de decisión:** Elegir qué barco equipar es una decisión estratégica. ¿Llevo el rápido pero frágil? ¿El lento pero resistente? ¿El que tiene mejor bonus para esta zona?
- **Simplicidad técnica:** No hay que gestionar "cambiar de barco en medio de un viaje". El barco equipado es tu barco activo hasta que decidas cambiarlo (en puerto, fuera de viaje).
- **Coherencia con el slot `companero`:** También es 1 (o 2 con perk). Los slots dedicados tienen límite 1 porque representan algo que no se puede tener duplicado.

### 14.4 ¿Cómo los Barcos Habilitan/Limitan la Navegación?

El barco es el GATEKEEPER de la exploración:
- **Sin barco:** No puedes navegar. Estás atrapado en tu isla actual.
- **Barco D:** Solo Blues. No puedes cruzar a Grand Line. Esto fuerza al jugador a progresar para conseguir un mejor barco.
- **Barco C:** Grand Line accesible, pero con penalizaciones en New World. El barco "se la juega" en aguas peligrosas.
- **Barco B/+:** Acceso completo a cualquier zona. El barco ya no es una limitación — es una herramienta.

Este diseño crea una progresión natural: el jugador empieza en un Blues, consigue un barco mejor, explora Grand Line, y eventualmente navega el New World. Cada mejora de barco abre nuevas áreas del mapa, manteniendo la exploración como incentivo.

### 14.5 ¿Por qué los Barcos NO se Juegan en Posts?

A diferencia de las técnicas o equipos, los barcos no se "activan" en un post. Su función es puramente de navegación. Las razones:

1. **Rol del barco:** El barco es el VEHÍCULO, no el combatiente. Los combates navales se resuelven con cards de técnica/equipo, no con el barco directamente.
2. **Pasividad:** El barco está "siempre ahí". No consume PE, no tiene cooldown, no se selecciona en el formulario de post.
3. **Efecto constante:** Sus beneficios (velocidad, resistencia, bonus de zona) están activos mientras el barco esté equipado. No hay "modo encendido/apagado".
4. **Foco mecánico:** Separar la navegación (barco) del combate (cards jugadas) evita que el jugador tenga que gestionar demasiadas cosas en un solo post.

---

## 15. Consejos para Jugadores

### 15.1 Eligiendo el Barco Adecuado

**Para empezar:** No gastes todos tus Berries en un barco rango C si apenas empiezas en East Blue. Una balsa (D) es suficiente para tus primeros arcos. Mejora cuando sientas que el barco te limita.

**Considera tu zona de juego:**
- Si roleas principalmente en los Blues, un barco C te sobra. No necesitas más.
- Si ya estás en Grand Line, prioriza `nav_bonus_grand_line` positivo.
- Si planeas ir al New World, necesitas mínimo rango B con `nav_bonus_new_world >= 0`.

**Sinergia con tu tripulación:**
- ¿Tienes un navegante en tu tripulación? Un barco más rápido pero frágil puede ser viable porque el navegante evitará eventos dañinos.
- ¿No tienes navegante? Prioriza `resistencia` alta y `maniobrabilidad` para sobrevivir a eventos adversos.

**Considera tu oficio:**
- Carpintero → puedes reparar y mejorar tu barco tú mismo. Un barco más dañable pero mejorable te da más contenido.
- No carpintero → necesitarás pagar reparaciones. Un barco resistente te ahorrará Berries a largo plazo.

### 15.2 Mantenimiento del Barco

- **Revisa la durabilidad regularmente.** Si tu barco está al 30% y entras en una tormenta, puedes perderlo.
- **Repara en puerto siempre que puedas.** No esperes a que el barco esté a punto de hundirse.
- **Invierte en mejoras útiles.** `casco_acero` es la mejora más rentable para viajeros frecuentes.
- **Las mejoras de vela** (`vela_mejorada`, `vela_grand_line`) son excelentes si haces viajes largos.

### 15.3 Cuándo Mejorar de Barco

Señales de que NECESITAS un barco mejor:
1. **No puedes llegar a zonas que quieres explorar.** Tu barco D no puede cruzar a Grand Line.
2. **Los eventos de navegación siempre son adversos.** Tu barco C sufre -2 en New World y cada viaje es una pesadilla.
3. **Tu tripulación creció y no caben.** El barco D tiene capacidad para 3 personas y ya son 5.
4. **El barco se daña constantemente.** Los eventos severos reducen tu durabilidad a 0 en cada viaje.
5. **Narrativamente tiene sentido.** Si tu personaje pasó de ser un novato a un pirata reconocido, un bergantín ya no es acorde a su estatus.

### 15.4 Estrategias Avanzadas

- **Ten 2 barcos:** Uno rápido para viajes cortos y otro resistente para travesías largas. Cambia según necesites.
- **El barco no consume CC:** No tengas miedo de tener múltiples barcos en posesión (aunque solo uno equipado).
- **Vende barcos viejos:** Al 50% del precio. Recuperas algo de inversión.
- **Coordina con tu tripulación:** Si 3 miembros tienen barco, cada uno puede equipar uno diferente y viajan juntos en el mejor.

---

## 16. Consejos para Staff

### 16.1 Creando Barcos Balanceados

**Reglas de oro para stats:**

| Rango | velocidad_base | resistencia | cañones | tripulación | nav_bonus_new_world |
|:-----:|:--------------:|:-----------:|:-------:|:-----------:|:-------------------:|
| D | 1-3 | 20-50 | 0 | 1-3 | -5 a -3 |
| C | 4-6 | 80-150 | 2-6 | 5-15 | -3 a -1 |
| B | 5-7 | 200-350 | 8-16 | 20-50 | -1 a 0 |
| A | 6-8 | 400-700 | 20-50 | 50-200 | 0 a +1 |
| S | 8-12 | 800-1500 | 40-100 | 100-500 | +2 a +3 |
| SS | 10-15 | 2000-5000 | 100+ | 500+ | +4 a +5 |

**Principio de especialización:** Todo barco debería tener al menos un bonus positivo y uno negativo. Un barco rápido debería ser frágil. Un barco resistente debería ser lento. Esto fuerza decisiones estratégicas.

**Principio de zona:** Un barco C con `nav_bonus_grand_line = 2` y `nav_bonus_new_world = -2` es interesante: es ideal para Grand Line pero pésimo para New World. Esto da personalidad al barco.

### 16.2 Asignando Barcos Legendarios

Los barcos S/SS son los más impactantes del juego. Directrices:

1. **Nunca asignar sin contexto narrativo.** Un barco S debe ganarse con un arco argumental importante.
2. **Solo 1-2 barcos S activos por foro.** La rareza es parte de su valor.
3. **Los barcos SS son para eventos globales.** Una vez cada 1-2 años.
4. **Documenta la decisión.** Usa el campo `notes` de la card para registrar quién, cuándo y por qué se asignó.
5. **Considera el impacto futuro.** Un barco SS puede desbalancear la navegación. Prepárate para ajustar rutas o peligros.

### 16.3 Manejando Daño/Pérdida de Barco

**Daño moderado:** El staff puede descontar durabilidad manualmente editando `effects_json`. Notificar al jugador en el post de evento.

**Daño severo:** Si el barco queda con durabilidad < 25%, el jugador debería buscar reparación activamente. El staff puede enviar un MP recordatorio.

**Pérdida total:** Solo en circunstancias extremas (evento global, combate naval masivo, decisión del jugador). Considerar:
- ¿El jugador acepta la pérdida? (consultar antes)
- ¿Hay alternativa narrativa? (ej: el barco queda varado en una isla, no destruido)
- ¿El barco es único/legendario? Si es S/SS, quizás merece una "casi pérdida" como lección, no destrucción total.

**Reemplazo:** Si un barco se pierde, el personaje no debería obtener uno igual automáticamente. Debe buscarlo, construirlo, o comprar uno nuevo.

### 16.4 Revisión de Viajes (Staff Review)

Cada viaje tiene estado `staff_review = 'pending'`. El staff debe:

1. **Verificar coherencia:** ¿El personaje está realmente en la isla de origen? ¿El destino existe? ¿La duración es razonable?
2. **Verificar equipamiento:** Confirmar que el barco usado estaba equipado (se puede ver en `raw_calculation_json.ship_effects`).
3. **Aprobar o denegar:**
   - Approve: El viaje se completa, el personaje llega a destino.
   - Deny: El viaje se cancela (ej: el barco no podía sobrevivir la ruta, el jugador se equivocó de destino).
4. **Publicar respuesta automática:** `game_navigation_post_thread_reply()` inserta un post en el hilo del viaje notificando la decisión.

### 16.5 Depuración de Problemas

Si un viaje no se procesa correctamente, revisar:

1. ¿El barco existe en `game_cards` con `card_type = 'barco'`?
2. ¿El barco está en `game_character_inventory` con `slot_type = 'barco'`?
3. ¿El `ship_card_id` en el POST coincide con el `card_id` del barco?
4. ¿La isla destino tiene una fila en `game_forum_islands`?
5. ¿El foro actual (fid) está mapeado a una isla?
6. Revisar `raw_calculation_json` del viaje para ver los cálculos exactos.

---

## 17. Referencia Rápida

### 17.1 Tabla Resumen de Barcos por Rango

| Rango | Categoría | velocidad_base | resistencia | cañones | trip_max | Bonus GL | Bonus NW | Precio (tienda) |
|:-----:|-----------|:--------------:|:-----------:|:-------:|:--------:|:--------:|:--------:|:---------------:|
| D | Barca/Balsa | 1-3 | 20-50 | 0 | 1-3 | -2 | -5 | 500-2,000 |
| C | Bergantín/Carabela | 4-6 | 80-150 | 2-6 | 5-15 | +1 | -2 | 5,000-20,000 |
| B | Galeón/Fragata | 5-7 | 200-350 | 8-16 | 20-50 | +2 | 0 | 50,000-200,000 |
| A | Navío/Acorazado | 6-8 | 400-700 | 20-50 | 50-200 | +3 | +1 | Staff only |
| S | Legendario | 8-12 | 800-1500 | 40-100 | 100-500 | +4 | +3 | Evento |
| SS | Épico/Mítico | 10-15 | 2000-5000 | 100+ | 500+ | +5 | +5 | Global |

### 17.2 Archivos Clave

| Archivo | Propósito |
|---------|-----------|
| `back/forum/game/inc/navigation_process.php` | Procesamiento de viajes (post → voyage) |
| `back/forum/game/inc/navigation_helpers.php` | Funciones de navegación (velocidad, distancia, eventos) |
| `back/forum/game/inc/navigation_config.php` | Constantes (GAME_NAV_SPEED_FACTOR, penalizaciones) |
| `back/forum/game/inc/navigation_review_helpers.php` | Revisión de viajes por staff |
| `back/forum/game/inc/inventory_helpers.php` | Equipamiento (slot barco) |
| `back/forum/game/ajax/navigation_ships.php` | Endpoint: barcos equipados del personaje |
| `back/forum/game/sql/migrate_cards_barco.php` | Migración: añadir barco al ENUM card_type |
| `back/forum/game/sql/migrate_navigation_system.php` | Migración: tablas de navegación |
| `back/forum/game/public/cartas_staff.php` | Formulario de creación de cards (staff) |
| `back/forum/game/views/personaje/_tab_gestion.php` | Formulario de propuesta de barco (jugador) |
| `back/forum/jscripts/game/personaje_inventory.js` | Inventario frontend (slot barco) |

### 17.3 Valores ENUM y Constantes

```php
// game_cards.card_type
ENUM('tecnica', 'equipo', 'akuma_no_mi', 'haki', 'npc_menor', 'barco')

// game_character_inventory.slot_type
ENUM('carga', 'companero', 'barco')

// game_navigation_voyages.status
ENUM('active', 'arrived', 'cancelled')

// game_navigation_voyages.staff_review
ENUM('pending', 'approved', 'denied')

// Constantes de navegación (navigation_config.php)
GAME_NAV_SPEED_FACTOR = 10
GAME_NAV_EVENTS_MIN = 0
GAME_NAV_EVENTS_MAX = 8
GAME_NAV_NO_INSTRUMENT_SPEED_PENALTY = 1.0
```

### 17.4 Típico effects_json de Barco

```json
{
    "barco_tipo": "bergantin",
    "velocidad_base": 6,
    "resistencia": 150,
    "capacidad_carga": 30,
    "tripulacion_max": 12,
    "cañones": 4,
    "maniobrabilidad": 3,
    "nav_bonus_east_blue": 0,
    "nav_bonus_west_blue": 0,
    "nav_bonus_north_blue": 0,
    "nav_bonus_south_blue": 0,
    "nav_bonus_grand_line": 1,
    "nav_bonus_new_world": -2,
    "nav_bonus_calm_belt": -3,
    "nav_bonus_florian_triangle": -1,
    "special_abilities": [
        "vela_mejorada: +1 velocidad con viento fuerte"
    ],
    "mejoras": ["vela_mejorada"],
    "durabilidad_actual": 150,
    "durabilidad_max": 150
}
```
