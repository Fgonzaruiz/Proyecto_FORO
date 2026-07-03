# RANGOS Y PROGRESIÓN — GUÍA COMPLETA

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 3
> **Propósito:** Documentar exhaustivamente el subsistema de rangos y progresión: rango global, nivel, PP (Puntos de Progresión), costes de mejora de stats, servicio `CharacterProgression`, endpoint de compra, plugin de ganancia de PP por posts — y **por qué** cada decisión de diseño se tomó así, cómo impacta en la experiencia RPG, y consejos para jugadores y staff.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [Rango Global — Definición y Cálculo](#2-rango-global)
3. [Nivel — Equivalencia Numérica](#3-nivel)
4. [PP — Puntos de Progresión](#4-pp-puntos-de-progresión)
5. [Costes de Progresión de Stats](#5-costes-de-progresión)
6. [CharacterProgression — Servicio Completo](#6-characterprogression)
7. [purchase_attribute.php — Endpoint de Compra](#7-purchaseattributephp)
8. [Plugin MyBB — Ganancia de PP por Posts](#8-plugin-mybb)
9. [Tabla Completa de Rangos](#9-tabla-completa-de-rangos)
10. [Integración con la Ficha y Frontend](#10-integración-con-la-ficha)
11. [Límites y Casos Especiales](#11-límites-y-casos-especiales)
12. [Migraciones Históricas](#12-migraciones-históricas)
13. [Filosofía de Diseño del Sistema de Rangos](#13-filosofía-de-diseño)
14. [Consejos para Jugadores](#14-consejos-para-jugadores)
15. [Consejos para Staff](#15-consejos-para-staff)
16. [Referencia Rápida](#16-referencia-rápida)

---

## 1. Arquitectura General

### 1.1 Capas del Subsistema de Progresión

```
┌─────────────────────────────────────────────────────────────────────┐
│                       FRONTEND (Navegador)                          │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │ personaje_page.js                    personaje_inventory.js    │  │
│  │  └ Gestión → Comprar Atributos       └ uso de items/PP        │  │
│  └───────────────────┬───────────────────────────────────────────┘  │
│                      │ POST /purchase_attribute.php                 │
└──────────────────────┼─────────────────────────────────────────────┘
                       │
┌──────────────────────┼─────────────────────────────────────────────┐
│  ┌───────────────────▼───────────────────────────────────────────┐ │
│  │              PHP — CAPA DE APLICACIÓN                          │ │
│  │  CharacterProgression (validate, apply, recalculate)           │ │
│  │  StatScale (constantes, conversiones, multiplicadores)         │ │
│  │  CharacterSaveService (cálculo de rank en creación/aprobación) │ │
│  │  PersonajeRepository (persistencia de stats y data_json)       │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│                              ▼                                      │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │           MySQL — game_personajes (stats_json, data_json)      │ │
│  │           game_post_characters (PP por posts)                  │ │
│  └───────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────┘
```

### 1.2 Filosofía de la Arquitectura

**¿Por qué la progresión se maneja como un servicio separado (`CharacterProgression`) y no dentro del controlador o la vista?**

- **Separación de responsabilidades:** El controlador (`purchase_attribute.php`) solo orquesta la petición HTTP, verifica permisos y devuelve la respuesta. La lógica de negocio de progresión está encapsulada en `CharacterProgression`, que puede ser testeada y reutilizada desde cualquier punto de entrada (AJAX, script de consola, migración).
- **Consistencia transaccional:** Aunque la implementación actual usa dos UPDATEs separados (data_json y stats_json), el diseño permite migrar a una transacción atómica si hiciera falta. Toda actualización de stats pasa por `applyStatUpgrade()`, garantizando que el recalculo de rango global siempre acompañe al cambio de stats.
- **Un solo punto de validación:** `validateStatUpgrade()` es la única puerta de entrada legítima para comprar un stat. Cualquier intento de manipular `stats_json` directamente en DB sería detectado al recargar la ficha (el `CharacterSheetLoader` recalcula el rank esperado contra el rank guardado, aunque hoy no haya una verificación explícita — el diseño lo permite).

**¿Por qué el rango global se recalcula desde la suma de stats entrenados en cada operación y no se cachea?**

- Porque es barato: sumar 7 enteros y comparar contra 6 umbrales es O(1). No merece la pena cachear algo tan simple.
- Porque garantiza consistencia: si un bug modificara `stats_json` sin tocar `data_json.rank`, en la siguiente compra de stat el rank se recalcula automáticamente. El `applyStatUpgrade()` llama a `recalculateGlobalRank()` siempre.

**¿Por qué los PP de linaje se gastan primero?**

- Porque son un "regalo" racial que desaparece al gastarse. Si el jugador gasta primero sus PP normales y guarda los de linaje, estaría acumulando un colchón extra. La regla "linaje primero" asegura que el beneficio racial se consume y no se acumula indefinidamente.

### 1.3 Impacto RPG

| Decisión arquitectónica | Lo que significa para el juego |
|------------------------|-------------------------------|
| Servicio de progresión dedicado | El staff puede auditar compras, simular progresiones, o escribir herramientas de administración sin tocar la UI |
| Recalculo siempre desde stats_json | Si alguien manipula data_json.rank manualmente, en la siguiente compra se corrige solo |
| PP de linaje primero | Los jugadores sienten que su herencia racial "se gasta" rápido, incentivando la reflexión antes de comprar |

---

## 2. Rango Global

### 2.1 Definición

El **Rango Global (RG)** de un personaje representa su nivel de poder general. Va de **D** (novato) a **SS** (leyenda). Se calcula exclusivamente a partir de la **suma de los 7 stats entrenados** (sin incluir bonos raciales ni modificadores de turno).

```php
public static function globalRankFromSum(int $sumaRangos): string
{
    if ($sumaRangos <= 10)  return 'D';
    if ($sumaRangos <= 16)  return 'C';
    if ($sumaRangos <= 22)  return 'B';
    if ($sumaRangos <= 28)  return 'A';
    if ($sumaRangos <= 36)  return 'S';
    return 'SS';
}
```

| Rango | Suma mínima | Suma máxima | Media por stat |
|:-----:|:-----------:|:-----------:|:--------------:|
| D | 7 | 10 | ~1.0–1.4 |
| C | 11 | 16 | ~1.6–2.3 |
| B | 17 | 22 | ~2.4–3.1 |
| A | 23 | 28 | ~3.3–4.0 |
| S | 29 | 36 | ~4.1–5.1 |
| SS | 37 | 42 | ~5.3–6.0 |

### 2.2 Filosofía de Diseño: ¿Por qué la suma de stats entrenados y no los efectivos?

**Decisión consciente: el RG se calcula con stats entrenados, SIN bonos raciales.**

Imaginemos dos personajes:
- **Personaje A:** Humano (sin bonos), stats `[2,2,2,2,2,2,2]` → suma 14 → RG C
- **Personaje B:** Mink (AGI+1, DES+1, INST+1), stats entrenados `[2,2,2,2,2,2,2]` → misma suma 14 → RG C

Si contáramos los efectivos, el Mink tendría suma 17 → RG B, a pesar de haber invertido EXACTAMENTE los mismos PP que el Humano. Eso sería injusto: el jugador Mink obtendría un RG más alto sin haber pagado su coste en PP.

**Consecuencia narrativa:** Dos personajes con el mismo RG tienen niveles de poder real (con raciales) posiblemente distintos. Un Mink RG C es efectivamente más fuerte que un Humano RG C, porque sus bonos raciales le dan stats efectivos más altos. Pero ambos han "entrenado" lo mismo.

### 2.3 Impacto RPG

| Esta decisión... | Hace que en el juego... |
|-----------------|------------------------|
| RG calculado con stats entrenados | Elegir raza no te da "rango gratis". Un Humano y un Mink con el mismo esfuerzo tienen el mismo RG. |
| Suma de 7 stats, no el stat más alto | La especialización extrema (un solo stat alto) no sube el RG. Para subir RG necesitas balance. |
| Umbrales con progresión no lineal | Los rangos bajos se alcanzan rápido (motivación), los altos son una maratón. |

### 2.4 El data_json.rank

El rango global se persiste en `data_json.rank` como un campo derivado. No es la fuente de verdad — la fuente de verdad es `stats_json`. Pero se guarda para:

1. **Evitar recalcular en cada carga de ficha:** Aunque el recalculo es barato, tener el rank disponible evita incluso esa mínima operación en renders masivos (biblioteca, listas).
2. **Tener un campo indexable:** En el futuro, se podría añadir un índice sobre `data_json` (MySQL 8+ permite índices en JSON) para filtrar personajes por rango sin recorrer todos los stats.
3. **Auto-reparación:** `CharacterProgression::recalculateGlobalRank()` se llama en cada compra de stat, manteniendo `data_json.rank` sincronizado con `stats_json`. Si alguien modifica stats_json manualmente, en la siguiente compra se corrige.

```json
{
    "rank": "B",
    "nivel": 3,
    "last_rank_change_at": "2025-06-12 10:30:00"
}
```

### 2.5 ¿Por qué D→SS y no números?

La escala D→SS es directamente temática de One Piece (cazarrecompensas, rangos marinos, niveles de amenaza). Además:

- **Diferencia psicológica:** Decir "soy rango B" suena más significativo que "soy nivel 3".
- **Memorabilidad:** Los jugadores recuerdan su rango y el de sus rivales. "Ese tipo es rango A" tiene peso narrativo.
- **CSS y visualización:** Las clases CSS (`pj-global-rank-badge--s`) permiten estilos visuales únicos por rango.

---

## 3. Nivel

### 3.1 Definición

El **nivel** es la representación numérica (1–6) del rango global. Existe puramente por conveniencia matemática:

```php
public static function globalNivelFromRank(string $rank): int
{
    return match (strtoupper($rank)) {
        'D' => 1, 'C' => 2, 'B' => 3,
        'A' => 4, 'S' => 5, 'SS' => 6,
        default => 1,
    };
}
```

| Rango | Nivel |
|:-----:|:-----:|
| D | 1 |
| C | 2 |
| B | 3 |
| A | 4 |
| S | 5 |
| SS | 6 |

### 3.2 ¿Por qué existe el nivel si ya tenemos el rango?

- **Operaciones matemáticas:** El nivel se usa en cálculos que requieren un valor numérico (ej: `minNivelForAkumaTier`, comparaciones de requisitos). Comparar `nivel >= 4` es más limpio que un switch de rangos.
- **Requisitos de contenido:** Algunas mecánicas (Akuma no Mi, Haki avanzado, Awakening) usan el nivel como gatekeeper. Es más fácil almacenar "requiere nivel 3" que "requiere rango B".
- **Legado:** En versiones anteriores del sistema (pre-v7), existía solo el nivel numérico. Se mantuvo por compatibilidad con migraciones y código legacy.

### 3.3 Sincronización

`data_json.nivel` se actualiza siempre junto con `data_json.rank` en `recalculateGlobalRank()`. Nunca puede haber un nivel sin rango o viceversa.

```php
public static function recalculateGlobalRank(array $stats, array &$data): string
{
    $sum = StatScale::sumRanks($stats);
    $rank = StatScale::globalRankFromSum($sum);
    $data['rank'] = $rank;
    $data['nivel'] = StatScale::globalNivelFromRank($rank);
    $data['last_rank_change_at'] = date('Y-m-d H:i:s');
    return $rank;
}
```

---

## 4. PP — Puntos de Progresión

### 4.1 Definición

Los **Puntos de Progresión (PP)** son la moneda de mejora permanente del personaje. Se ganan mediante actividad rolística (posts) y se gastan en:

- **Subir rangos de Stats** (principal uso, ~90% del gasto típico)
- **Desbloquear grados de Disciplinas** (PP por grado)
- **Desbloquear grados de Oficios** (PP por grado)
- **Aprender Estilos Canónicos** (coste fijo en PP)
- **Obtener ciertas Cartas** (las que tienen coste en PP)

### 4.2 PP en data_json

```json
{
    "pp": 120,
    "pp_linaje": 4
}
```

- `pp`: PP totales disponibles. Nunca negativo.
- `pp_linaje`: PP provenientes del sistema de linaje (bonificación racial). Se gastan primero.

### 4.3 Filosofía de Diseño: ¿Por qué PP en lugar de XP/EXP?

- **Diferenciación de PE:** En el foro, PE (Puntos de Energía) son un recurso de combate que se gasta y recupera por post. PP son permanentes. Usar "XP" se habría confundido con "PE".
- **Temático:** "Progresión" suena a avance sostenido, no a "experiencia" genérica. PP es la moneda que inviertes para MEJORAR tu personaje.
- **Paralelismo con PA (Puntos de Aventura):** PP y PA comparten la "P" de Puntos, creando una familia semántica: PP para progresión permanente, PA para acciones tácticas por post.

### 4.4 PP de Linaje

Los PP de linaje son un subconjunto especial de PP que se obtienen al crear el personaje. Cuando un jugador no gasta todos sus puntos de linaje en perks raciales, el sobrante se convierte en PP bonus:

```
PP_linaje_bonus = puntos_de_linaje_sobrantes × 2
```

**Ejemplo:** Un Humano (28 puntos de linaje) gasta 24 en perks. Le sobran 4 → 8 PP bonus guardados como `pp_linaje`.

**Regla de gasto:** `pp_linaje` se consume primero al comprar stats.

```php
public static function allocatePpSpend(int $cost, int $pp, int $ppLinaje): array
{
    $fromLinaje = min($cost, max(0, $ppLinaje));
    return [
        'from_linaje' => $fromLinaje,
        'new_pp' => $pp - $cost,
        'new_pp_linaje' => $ppLinaje - $fromLinaje,
    ];
}
```

### 4.5 Normalización y Auto-Reparación

`CharacterProgression::normalize()` asegura que data_json siempre tenga valores coherentes de PP:

```php
public static function normalize(array &$data): void
{
    $bonusLinaje = (int)($data['linaje']['bonusPP'] ?? 0);

    if (!isset($data['pp']) && $bonusLinaje > 0) {
        $data['pp'] = $bonusLinaje;
    }

    $data['pp'] = max(0, (int)($data['pp'] ?? 0));
    $ppLinaje = isset($data['pp_linaje']) ? (int)$data['pp_linaje'] : null;

    if ($ppLinaje === null && $bonusLinaje > 0) {
        $ppLinaje = min((int)$data['pp'], $bonusLinaje);
    } elseif ($ppLinaje === null) {
        $ppLinaje = 0;
    }

    $data['pp_linaje'] = min(max(0, $ppLinaje), (int)$data['pp']);

    $rank = trim((string)($data['rank'] ?? ''));
    if ($rank === '') {
        $data['rank'] = 'D';
    }
    $data['nivel'] = StatScale::globalNivelFromRank((string)$data['rank']);
}
```

**Casos que maneja:**
1. `pp` ausente pero hay bonus de linaje → asigna el bonus como PP iniciales.
2. `pp_linaje` ausente → lo deduce del bonus de linaje o pone 0.
3. `pp_linaje` mayor que `pp` → lo limita (no puedes tener más PP de linaje que PP totales).
4. `rank` vacío → asume D (nuevo personaje sin calcular).
5. `nivel` ausente o inconsistente → recalcula desde rank.

---

## 5. Costes de Progresión

### 5.1 Coste Base por Rango

Cada vez que subes un stat individual de un rango al siguiente, el coste base depende del rango ACTUAL del stat (no del objetivo):

```php
const RANK_UPGRADE_COST = [
    1 => 50,    // D → C
    2 => 130,   // C → B
    3 => 350,   // B → A
    4 => 800,   // A → S
    5 => 1800,  // S → SS
];
```

| Salto | Coste base |
|:-----:|:----------:|
| D → C (1→2) | 50 PP |
| C → B (2→3) | 130 PP |
| B → A (3→4) | 350 PP |
| A → S (4→5) | 800 PP |
| S → SS (5→6) | 1800 PP |

### 5.2 Multiplicador por Rango Global

El coste base se multiplica por un factor que depende del **Rango Global actual del personaje**:

```php
const RANK_GLOBAL_MULTIPLIERS = [
    'D' => 1.00,
    'C' => 1.07,
    'B' => 1.15,
    'A' => 1.35,
    'S' => 1.60,
    'SS' => 2.00,
];
```

### 5.3 Fórmula Final

```php
public static function getStatUpgradeCost(int $rangoActual, string $rangoGlobal = 'D'): int
{
    if ($rangoActual < 1 || $rangoActual >= 6) {
        return PHP_INT_MAX;
    }
    $base = self::RANK_UPGRADE_COST[$rangoActual] ?? PHP_INT_MAX;
    $mult = self::RANK_GLOBAL_MULTIPLIERS[$rangoGlobal] ?? 1.0;
    return (int) round($base * $mult);
}
```

### 5.4 Tabla Completa de Costes

| Rango actual | Coste base | RG D | RG C | RG B | RG A | RG S | RG SS |
|:------------:|:----------:|:----:|:----:|:----:|:----:|:----:|:-----:|
| 1→2 (D→C) | 50 | 50 | 54 | 58 | 68 | 80 | 100 |
| 2→3 (C→B) | 130 | 130 | 139 | 150 | 176 | 208 | 260 |
| 3→4 (B→A) | 350 | 350 | 375 | 403 | 473 | 560 | 700 |
| 4→5 (A→S) | 800 | 800 | 856 | 920 | 1080 | 1280 | 1600 |
| 5→6 (S→SS) | 1800 | 1800 | 1926 | 2070 | 2430 | 2880 | 3600 |

### 5.5 PP Acumulados por Rango

El PP total gastado para alcanzar cierto rango en un stat (acumulado desde 1):

```php
const RANK_CUMULATIVE_PP = [
    1 => 0,      // D — sin coste, es el inicial
    2 => 50,     // D→C
    3 => 180,    // C→B  (50+130)
    4 => 530,    // B→A  (50+130+350)
    5 => 1330,   // A→S  (50+130+350+800)
    6 => 3130,   // S→SS (50+130+350+800+1800)
];
```

Estos son costes BASE sin multiplicador. Con RG SS, el coste real para llegar a 6 en un stat es:

```
50×2.0 + 130×2.0 + 350×2.0 + 800×2.0 + 1800×2.0 = 100+260+700+1600+3600 = 6260 PP
```

### 5.6 Coste Total para RG SS

Para tener todos los stats en 6 (SS global):

| Concepto | Coste |
|----------|:-----:|
| 7 stats en rango 6 (base) | 7 × 3130 = 21,910 PP |
| Con RG SS (×2.0 en cada compra) | ~43,820 PP |
| A 100 palabras/PP, posts de 500 palabras | ~8,764 posts |
| A 2 posts/semana | ~84 años de juego |

**Conclusión deliberada:** Es imposible alcanzar el máximo. El sistema siempre tiene una meta lejana.

### 5.7 Filosofía de los Multiplicadores

¿Por qué el rango global afecta al coste de subir stats individuales?

- **Anti-min-maxing:** Si no hubiera multiplicador, un personaje podría subir un solo stat a 6 pagando 3130 PP totales (base), manteniendo los otros 6 stats en 1. Su RG sería D (suma 12), pero tendría un stat al máximo. Con el multiplicador, ese personaje paga el coste base sin penalización (RG D = ×1.0). Bien. Pero para SUBIR de RG, necesita balance.

- **Incentivo al balance:** El multiplicador hace que sea progresivamente más caro mejorar stats cuando ya eres fuerte. Un personaje RG A paga ×1.35, un RG SS paga ×2.0. Esto incentiva mantener los stats parejos en lugar de crear "picos".

- **El multiplicador no se aplica retroactivamente:** Si subiste FUE de 1 a 3 cuando tenías RG D (pagando 50+130 = 180 PP), y luego tu RG sube a B, las compras FUTURAS (3→4) se calcularán con multiplicador B (×1.15). Lo ya pagado no se recalcula.

### 5.8 Impacto RPG

| Esta decisión... | Hace que en el juego... |
|-----------------|------------------------|
| Costes exponenciales | Los primeros 2-3 meses de juego tengan progresión rápida y emocionante. |
| Multiplicador por RG | Los especialistas extremos tengan un techo natural. Llegar a SS exige ser completo. |
| Acumulativo de 21,910 PP totales | Nadie llega al máximo. Siempre hay algo por mejorar, incluso después de años. |
| PP de linaje primero | La decisión racial del personaje tiene peso económico hasta que el bonus se gasta. |

---

## 6. CharacterProgression

### 6.1 Definición Completa de la Clase

Archivo: `back/forum/game/src/Application/Services/CharacterProgression.php` (176 líneas)

```php
namespace Game\Application\Services;

use Game\Shared\StatScale;

final class CharacterProgression
{
    // Sincronización de PP de linaje desde el sistema de linaje
    public static function syncLinajeBonusPp(array &$data, string $raceName): void

    // Normalización de valores de progresión
    public static function normalize(array &$data): void

    // Recalcular rango global desde la suma de stats
    public static function recalculateGlobalRank(array $stats, array &$data): string

    // Obtener coste para subir un stat
    public static function getStatUpgradeCost(int $rangoActual, string $rangoGlobal = 'D'): int

    // Validar si una compra de stat es permitida
    public static function validateStatUpgrade(array $data, array $stats, string $stat): array

    // Asignar el gasto entre pp_linaje y pp normal
    public static function allocatePpSpend(int $cost, int $pp, int $ppLinaje): array

    // Ejecutar la compra (valida, descuenta PP, sube stat, recalcula rank)
    public static function applyStatUpgrade(array &$data, array &$stats, string $stat): array

    // Instantánea de la progresión actual para la UI
    public static function snapshot(array $data, array $stats = []): array
}
```

### 6.2 syncLinajeBonusPp — Sincronización de PP de Linaje

Se ejecuta en cada carga de ficha (desde `CharacterSheetLoader`) para asegurar que los PP de linaje nunca se pierdan aunque el data_json se corrompa.

**Flujo:**
1. Lee el linaje del personaje.
2. Si la versión del linaje ≥ 2 y hay raza definida, ejecuta `LinajeValidator::validateAndBuild()`.
3. Si hay bonusPP calculado y el personaje tiene menos PP que ese bonus, asigna el bonus.
4. Si el personaje tiene PP pero `pp_linaje` es 0, asigna `pp_linaje = min(pp_actuales, bonus)`.

**Auto-reparación:** Si un bug durante la creación dejó al personaje sin PP de linaje, esta función lo corrige en el momento de cargar la ficha, sin intervención del staff.

### 6.3 validateStatUpgrade — Validación de Compra

```php
public static function validateStatUpgrade(array $data, array $stats, string $stat): array
{
    self::normalize($data);
    if (!in_array($stat, StatScale::STAT_KEYS, true))
        return ['ok' => false, 'error' => 'Atributo inválido.'];

    $rangoActual = (int)($stats[$stat] ?? 1);
    if ($rangoActual >= 6)
        return ['ok' => false, 'error' => 'Este atributo ya está en rango máximo (SS).'];

    $rangoGlobal = StatScale::globalRankFromSum(StatScale::sumRanks($stats));
    $coste = self::getStatUpgradeCost($rangoActual, $rangoGlobal);
    $ppDisponibles = (int)($data['pp'] ?? 0);
    if ($ppDisponibles < $coste)
        return ['ok' => false, 'error' => "Necesitas {$coste} PP. Tienes {$ppDisponibles}."];

    return ['ok' => true, 'coste' => $coste];
}
```

**Validaciones en orden:**
1. **Stat existe:** Solo `fue`, `res`, `agi`, `des`, `int`, `inst`, `esp`.
2. **No está en máximo:** No se puede subir un stat que ya está en 6 (SS).
3. **PP suficientes:** El jugador debe tener PP ≥ coste calculado.
4. **Coste calculado:** Usa el rango actual del stat + el rango global actual.

### 6.4 applyStatUpgrade — Ejecución de la Compra

```php
public static function applyStatUpgrade(array &$data, array &$stats, string $stat): array
{
    self::normalize($data);
    $validation = self::validateStatUpgrade($data, $stats, $stat);
    if (!($validation['ok'] ?? false)) {
        throw new \InvalidArgumentException($validation['error'] ?? 'Compra no permitida.');
    }

    $coste = (int)$validation['coste'];
    $alloc = self::allocatePpSpend($coste, (int)$data['pp'], (int)$data['pp_linaje']);
    $data['pp'] = $alloc['new_pp'];
    $data['pp_linaje'] = $alloc['new_pp_linaje'];
    $stats[$stat] = (int)($stats[$stat] ?? 1) + 1;

    $newRank = self::recalculateGlobalRank($stats, $data);

    return [
        'new_pp' => (int)$data['pp'],
        'new_pp_linaje' => (int)$data['pp_linaje'],
        'new_rank' => $newRank,
        'upgrade_cost' => $coste,
    ];
}
```

**Efectos colaterales:**
- `data_json` modificado in-situ (nuevos pp, pp_linaje, rank, nivel, last_rank_change_at).
- `stats_json` modificado in-situ (stat incrementado en 1).
- Lanza `InvalidArgumentException` si la validación falla (no se ejecuta la compra).
- Retorna snapshot de los nuevos valores para que el frontend actualice la UI inmediatamente.

### 6.5 snapshot — Instantánea para UI

```php
public static function snapshot(array $data, array $stats = []): array
{
    self::normalize($data);
    $rangoGlobal = StatScale::globalRankFromSum(StatScale::sumRanks($stats));
    $nextCosts = [];
    foreach (StatScale::STAT_KEYS as $key) {
        $r = (int)($stats[$key] ?? 1);
        $nextCosts[$key] = $r >= 6 ? null : self::getStatUpgradeCost($r, $rangoGlobal);
    }

    return [
        'nivel' => (int)($data['nivel'] ?? 1),
        'rank' => (string)($data['rank'] ?? 'D'),
        'pp' => (int)($data['pp'] ?? 0),
        'pp_linaje' => (int)($data['pp_linaje'] ?? 0),
        'sum_ranks' => StatScale::sumRanks($stats),
        'next_upgrade_costs' => $nextCosts,
    ];
}
```

**`next_upgrade_costs`** es un array clave para la UI: por cada stat, el frontend muestra "Coste para subir: X PP". Si el stat está en 6, el valor es `null` (no se puede subir más).

---

## 7. purchase_attribute.php

### 7.1 Endpoint Completo

Archivo: `back/forum/game/ajax/purchase_attribute.php` (82 líneas)

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\CharacterProgression;
use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\PersonajeRepository;
use Game\Shared\StatScale;

header('Content-Type: application/json; charset=utf-8');

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$character_id = (int)($input['character_id'] ?? 0);
$stat = trim((string)($input['stat'] ?? ''));

if ($character_id <= 0 || !in_array($stat, StatScale::STAT_KEYS, true)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Parámetros inválidos.'], 400);
}

$personajes = new PersonajeRepository();
$character = $personajes->findByIdForUser($character_id, $uid);

if ($character === null) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'Personaje no encontrado.'], 404);
}

if ($character['status'] !== 'aprobada') {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'El personaje debe estar aprobado para realizar compras.'], 403);
}

$data = !empty($character['data_json']) ? json_decode($character['data_json'], true) : [];
if (!is_array($data)) { $data = []; }

$stats = !empty($character['stats_json']) ? json_decode($character['stats_json'], true) : [];
if (!is_array($stats)) { $stats = []; }
$stats = StatScale::sanitizeRanks($stats);

CharacterProgression::syncLinajeBonusPp($data, (string)($character['race_name'] ?? ''));
CharacterProgression::normalize($data);

$validation = CharacterProgression::validateStatUpgrade($data, $stats, $stat);
if (!($validation['ok'] ?? false)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => $validation['error'] ?? 'Compra no permitida.'], 400);
}

try {
    $result = CharacterProgression::applyStatUpgrade($data, $stats, $stat);
} catch (\InvalidArgumentException $e) {
    GameAjax::json(false, null, ['code' => 400, 'message' => $e->getMessage()], 400);
}

$prefix = TABLE_PREFIX;
$data_json_esc = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
$stats_json_esc = $db->escape_string(json_encode($stats, JSON_UNESCAPED_UNICODE));

$db->write_query("
    UPDATE {$prefix}game_personajes
    SET data_json = '{$data_json_esc}',
        stats_json = '{$stats_json_esc}'
    WHERE id = {$character_id}
");

$snapshot = CharacterProgression::snapshot($data, $stats);

GameAjax::json(true, array_merge($snapshot, [
    'new_pp' => $result['new_pp'],
    'new_pp_linaje' => $result['new_pp_linaje'],
    'new_stats' => $stats,
    'upgrade_cost' => $result['upgrade_cost'],
    'stat_upgraded' => $stat,
]), null);
```

### 7.2 Flujo Completo de la Petición

```
1. Cliente JS → POST /purchase_attribute.php
   Payload: { character_id: N, stat: "fue", _csrf: "token" }

2. GameAjax::requireLogin()          → Verifica que el usuario tiene sesión activa
3. GameAjax::requirePost()           → Rechaza si no es POST
4. GameAjax::postJson()              → Parsea JSON del body
5. GameAjax::requireCsrf()           → Valida token CSRF

6. Validar parámetros → character_id > 0, stat ∈ STAT_KEYS

7. PersonajeRepository::findByIdForUser() → Verifica que:
   a. El personaje existe
   b. El usuario es el dueño (o staff)

8. Verificar status == "aprobada"    → No se pueden comprar stats si el PJ no está aprobado

9. syncLinajeBonusPp()               → Asegura PP de linaje correctos
10. normalize()                       → Asegura defaults
11. validateStatUpgrade()             → Valida que la compra sea legal

12. applyStatUpgrade()                → Ejecuta la compra (modifica data y stats en memoria)

13. UPDATE game_personajes            → Persiste data_json y stats_json en DB

14. snapshot()                        → Prepara respuesta para el frontend

15. JSON response                     → Devuelve nuevos PP, nuevo rank, nuevos stats
```

### 7.3 Seguridad

- **CSRF:** Toda petición POST requiere un token CSRF válido. Sin él, la petición es rechazada.
- **Ownership:** `findByIdForUser()` asegura que el personaje pertenece al usuario que hace la petición (o es staff).
- **Status gate:** No se pueden comprar stats si el personaje no está aprobado. Un personaje pendiente no puede progresar.
- **Server-side validation:** Toda la lógica se revalida en PHP. El JS del frontend solo mejora la experiencia; no se confía en él.
- **Sanitización:** `StatScale::sanitizeRanks()` asegura que los stats nunca salgan del rango 1–6, incluso si alguien enviara valores corruptos desde el cliente.

### 7.4 Respuesta JSON

```json
{
    "ok": true,
    "data": {
        "nivel": 3,
        "rank": "B",
        "pp": 92,
        "pp_linaje": 0,
        "sum_ranks": 17,
        "next_upgrade_costs": {
            "fue": 150,
            "res": 58,
            "agi": 150,
            "des": 58,
            "int": 58,
            "inst": null,
            "esp": 58
        },
        "new_pp": 92,
        "new_pp_linaje": 0,
        "new_stats": {
            "fue": 3,
            "res": 3,
            "agi": 2,
            "des": 2,
            "int": 2,
            "inst": 3,
            "esp": 2
        },
        "upgrade_cost": 58,
        "stat_upgraded": "res"
    }
}
```

---

## 8. Plugin MyBB — Ganancia de PP por Posts

### 8.1 game_postcharacter.php

Archivo: `inc/plugins/game_postcharacter.php`

El plugin escucha hooks de MyBB para otorgar PP cuando un jugador postea.

| Hook | Cuándo se dispara | Acción |
|------|-------------------|--------|
| `datahandler_post_insert_post_end` | Después de crear un post | Incrementa `postnum`, otorga PP según palabras |

### 8.2 Cálculo de PP por Post

```php
const WORDS_PER_PP = 100;
```

Cada 100 palabras de rol (excluyendo contenido Off_Rol) otorgan **1 PP**.

**Fórmula:**
```
PP_ganados = floor(palabras_de_rol / 100)
```

**Ejemplos:**
| Palabras de rol | PP ganados |
|:---------------:|:----------:|
| 50 | 0 |
| 100 | 1 |
| 250 | 2 |
| 500 | 5 |
| 1200 | 12 |

### 8.3 ¿Por qué 100 palabras por PP?

- **Ritmo de progresión:** Un post medio de rol tiene 300–500 palabras → 3–5 PP por post. A 2 posts por semana, son ~6–10 PP semanales. Para subir un stat de 1 a 2 (50 PP con RG D) se necesitan ~5–8 posts. Es decir, ~2–4 semanas para la primera mejora. Suficientemente rápido para enganchar, suficientemente lento para que cada mejora se sienta importante.
- **Calidad sobre cantidad:** Si el ratio fuera más generoso (ej: 50 palabras = 1 PP), los jugadores harían posts cortos y frecuentes para maximizar PP. Con 100 palabras/PP, se incentivan posts sustanciales.
- **Equivalencia con el mundo real:** 100 palabras es aproximadamente un párrafo de rol bien desarrollado. No es una cantidad excesiva.

### 8.4 ¿Por qué NO se cuentan posts Off_Rol?

- Porque Off_Rol son notas del jugador, conversaciones out-of-character o aclaraciones. No son contenido del personaje y no deberían generar progresión.
- La distinción la hace el jugador al escribir (usando tags o identificadores en el post). Si se detecta contenido Off_Rol, se resta del conteo de palabras.

### 8.5 PP como Recompensa de Post vs PP como Recurso

Es importante distinguir:

- **PP ganados por posts:** Son la entrada principal de PP al sistema. Fluyen del plugin al data_json.pp.
- **PP de linaje:** Son un bonus único de creación. No se regeneran.
- **PP gastados:** Se eliminan permanentemente al comprar stats. No hay "reembolso" ni "reset".

### 8.6 ¿Qué pasa si un post se edita o elimina?

- **Post eliminado:** `class_moderation_delete_post_start` decrementa `postnum`. Sin embargo, el PP ya otorgado NO se descuenta. Esto es intencional: no se puede "perder" progreso por una edición o eliminación administrativa.
- **Post editado:** No se recalcula el PP. Una vez otorgado, el PP es permanente.
- **Abuso:** Si un jugador crea y elimina posts repetidamente para ganar PP, el staff puede detectarlo por el `postnum` anómalo (muchos posts creados y eliminados). Es un caso extremo y poco práctico.

---

## 9. Tabla Completa de Rangos

### 9.1 Rangos Individuales de Stats (D→SS)

| Rango | Nombre | Valor numérico | Equivalencia narrativa |
|:-----:|:------:|:--------------:|----------------------|
| 1 | D | 4 | Principiante / civil sin entrenamiento |
| 2 | C | 8 | Entrenado / marinero o pirata novato |
| 3 | B | 15 | Competente / soldado o pirata de la Grand Line |
| 4 | A | 26 | Experto / oficial de alto rango o capitán fuerte |
| 5 | S | 40 | Maestro / vicealmirante o capitán de los Blues |
| 6 | SS | 60 | Leyenda / nivel Almirante u equivalente |

### 9.2 Rangos Globales (D→SS) y Progresión

| RG | Nivel | Suma stats | PP total gastado (base) | PP total gastado (con ×2.0) | Posts de 500 palabras |
|:--:|:-----:|:----------:|:-----------------------:|:---------------------------:|:---------------------:|
| D | 1 | 7–10 | 0 | 0 | 0 |
| C | 2 | 11–16 | ~50–350 | ~50–374 | ~10–75 |
| B | 3 | 17–22 | ~400–1260 | ~428–1420 | ~80–284 |
| A | 4 | 23–28 | ~1330–2730 | ~1500–3160 | ~300–632 |
| S | 5 | 29–36 | ~2800–7000 | ~3580–9320 | ~716–1864 |
| SS | 6 | 37–42 | ~7350–21,910 | ~10,000–43,820 | ~2000–8764 |

### 9.3 Multiplicadores por Rango Global

| RG | × Coste de stat | × PV/PE |
|:--:|:---------------:|:-------:|
| D | 1.00 | 1.00 |
| C | 1.07 | 1.05 |
| B | 1.15 | 1.10 |
| A | 1.35 | 1.20 |
| S | 1.60 | 1.35 |
| SS | 2.00 | 1.50 |

### 9.4 Progresión Temporal Estimada

| RG | Tiempo estimado (actividad normal) | Hito narrativo |
|:--:|:----------------------------------:|----------------|
| D→C | 1–2 semanas | "Dejé de ser un civil" |
| C→B | 1–2 meses | "Puedo enfrentarme a amenazas serias" |
| B→A | 3–6 meses | "Soy alguien importante en este mar" |
| A→S | 6–12 meses | "Mi nombre empieza a ser conocido" |
| S→SS | 1–2+ años | "Soy una leyenda viviente" |

**Nota:** Estos tiempos son estimaciones basadas en 2 posts/semana de ~500 palabras cada uno (~10 PP/semana). Jugadores más activos progresarán más rápido.

---

## 10. Integración con la Ficha

### 10.1 Sidebar — Visualización del Rango

En `_sidebar.php`, el rango global se muestra como un badge:

```php
$globalRank = (string)($pj_progression['rank'] ?? 'D');
$globalRankClass = \Game\Shared\StatScale::globalRankCssClass($globalRank);
```

```html
<span class="pj-badge pj-badge--global-rank <?= htmlspecialchars($globalRankClass) ?>"
      title="Rango global (suma de rangos de atributos)">
    <i class="fas fa-layer-group"></i> <?= htmlspecialchars($globalRank) ?>
</span>
```

### 10.2 Badge de Rango — CSS

```php
public static function globalRankCssClass(string $rank): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9+]/i', '', $rank) ?: 'd');
    return 'pj-global-rank-badge--' . $slug;
}
```

Clases generadas: `pj-global-rank-badge--d`, `pj-global-rank-badge--c`, `pj-global-rank-badge--b`, `pj-global-rank-badge--a`, `pj-global-rank-badge--s`, `pj-global-rank-badge--ss`.

### 10.3 Panel de Gestión — Compra de Stats

Desde `_tab_gestion.php`, el submódulo "Comprar Atributos" permite al jugador:

1. Ver sus stats actuales con barras de progreso.
2. Ver el coste de la siguiente mejora (`next_upgrade_costs`).
3. Hacer clic en "Subir rango" para un stat específico.
4. Confirmar la compra (o cancelar si el coste es muy alto).

**Flujo JS:**

```javascript
// personaje_page.js — Gestión > Comprar Atributos
function purchaseStat(stat) {
    const cost = nextUpgradeCosts[stat];
    if (cost === null) return; // ya en máximo
    if (!confirm(`¿Gastar ${cost} PP para subir ${stat.toUpperCase()}?`)) return;

    fetch('game/ajax/purchase_attribute.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            character_id: pjId,
            stat: stat,
            _csrf: csrfToken
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.ok) {
            // Actualizar UI con nuevos valores
            updateStatsDisplay(res.data.new_stats);
            updateProgressionDisplay(res.data);
        } else {
            alert(res.message);
        }
    });
}
```

### 10.4 Snapshot en CharacterSheetLoader

Cada vez que se carga la ficha de un personaje (`personaje_init.php`), `CharacterSheetLoader` ejecuta:

```php
$pjProgression = CharacterProgression::snapshot($dataForProg, $statsForProg);
```

Esto inyecta en la UI los valores de `pp`, `pp_linaje`, `rank`, `nivel`, `sum_ranks`, y `next_upgrade_costs`. El frontend los usa para:
- Renderizar el badge de rango global.
- Mostrar PP disponibles en el panel de gestión.
- Mostrar los costes de mejora en cada stat.
- Bloquear visualmente los stats que ya están en rango 6.

### 10.5 CharacterSaveService — Cálculo de Rank al Crear/Aprobar

Cuando se crea un personaje o se aprueba, `CharacterSaveService` calcula el rango global inicial:

```php
// En buildPayloadForInsert() y recalculateOnApprove()
$globalRank = StatScale::globalRankFromSum(StatScale::sumRanks($sanitizedStats));
$data['rank'] = $globalRank;
$data['nivel'] = StatScale::globalNivelFromRank($globalRank);
```

Esto asegura que desde el momento de la creación, el personaje tenga un rank calculado. Un personaje nuevo con todos los stats en 1 (suma=7) tendrá RG D. Si gastó su punto de creación en un stat (+1), suma=8 → sigue siendo D.

**Transición D→C:** Ocurre cuando el jugador junta PP y sube stats hasta alcanzar suma ≥ 11.

---

## 11. Límites y Casos Especiales

### 11.1 Límite de Stat Individual

Máximo **6 (SS)** para personajes de jugador. No se puede subir más.

```php
if ($rangoActual >= 6) {
    return ['ok' => false, 'error' => 'Este atributo ya está en rango máximo (SS).'];
}
```

```php
if ($rangoActual >= 6) {
    $nextCosts[$key] = null; // Sin coste, no se puede mejorar
}
```

### 11.2 NPCs con Rangos 7+

Los NPCs mayores pueden tener stats de rango 7+ (SS+, SS++, M). Estos valores son inalcanzables para PJs y se asignan directamente por staff en DB, no mediante el sistema de compra.

**¿Por qué permitir 7+ solo en NPCs?**
- **Misterio:** Los jugadores no pueden saber exactamente el rango de un NPC poderoso. Ven "SS+" o "M" y saben que es superior.
- **Jefes narrativos:** Los antagonistas principales deben ser más fuertes que cualquier PJ para generar tensión.
- **Límite de PJ:** Si los PJs pudieran llegar a 7+, no habría techo y la progresión se diluiría.

### 11.3 Mínimo de Stat

El mínimo absoluto es 1 (D). Ningún personaje puede tener un stat por debajo de 1 entrenado:

```php
$clamp = static fn($v): int => max(1, min(6, (int)$v));
```

Sin embargo, el rango **efectivo** puede ser 0 o negativo si el bono racial es negativo:

- Tontatta con FUE entrenada = 1, bono racial -2 → rango efectivo = -1 → label '—' → valor 0.

### 11.4 PP Negativos

No existen. `normalize()` fuerza `pp = max(0, pp)`.

Si un bug causara PP negativos, la siguiente carga de ficha los corregiría a 0.

### 11.5 Personajes "Muertos"

Los personajes con `status = 'muerto'` no pueden gastar PP ni progresar. Su data_json y stats_json se conservan pero están congelados.

---

## 12. Migraciones Históricas

### 12.1 migrate_stats_v7.php

**Contexto:** El sistema anterior (v6 y anteriores) tenía stats con valores 1–20 sin normalizar. No existía el concepto de "rango global" — cada stat era un número independiente. Tampoco existían los multiplicadores por rango global.

**Qué cambió:**
1. Compresión de 1–20 a 1–6 usando una función de mapeo.
2. Introducción del rango global (D→SS) calculado desde la suma.
3. Introducción de multiplicadores de coste por RG.
4. Introducción de PV/PE multipliers.
5. Creación de `CharacterProgression` como servicio.
6. Migración de datos existentes con refund de PP si el nuevo sistema era más barato.

**Filosofía de la migración:** No dejar a nadie atrás. Todos los personajes existentes recibieron:
- Stats convertidos a la nueva escala (1–6).
- Rango global calculado desde sus stats convertidos.
- Refund de PP si el coste total de sus stats en el nuevo sistema era menor que lo que habían pagado en el viejo.

### 12.2 Migraciones Menores

- **Introducción de `pp_linaje`:** Los jugadores que ya tenían personajes creados antes del sistema de linaje v2 recibieron `pp_linaje = 0` y un linaje básico asignado por staff.
- **Añadido `last_rank_change_at`:** Columna temporal (en data_json) añadida para tracking de progresión. Sin migración retroactiva — solo los cambios futuros registran la fecha.
- **Separación de `data_json.rank` como campo derivado:** Antes se calculaba siempre en runtime; ahora se persiste para rendimiento en listas.

---

## 13. Filosofía de Diseño

### 13.1 Principios Rectores del Sistema de Rangos

1. **La progresión es lenta intencionalmente.** Subir de D a C es rápido para enganchar. Subir de S a SS es una maratón para mantener el interés a largo plazo.

2. **El balance es más importante que el poder absoluto.** Un personaje con todos los stats en 3 (RG B) es más versátil que uno con FUE 6 y todo lo demás en 1 (RG D). El sistema recompensa la versatilidad.

3. **Cada punto cuenta.** Con solo 6 rangos, cada subida de stat se siente como un logro. No hay niveles de relleno.

4. **El PP es la conexión entre el roleo y la mecánica.** Haces posts de rol → ganas PP → gastas PP en mejorar → tu personaje se vuelve más fuerte. El ciclo es claro y motivante.

5. **El rango global es un resumen, no una sentencia.** Dos personajes RG A pueden ser completamente distintos: un tanque (FUE/RES altos) vs un técnico (DES/INT altos). El RG solo dice "este personaje tiene un nivel general X", no define su estilo.

### 13.2 ¿Qué problemas resuelve este diseño?

| Problema común en RPGs por foro | Cómo lo resuelve este sistema |
|--------------------------------|-------------------------------|
| "Los jugadores suben demasiado rápido" | Costes exponenciales + multiplicador por RG frenan la progresión en rangos altos |
| "El min-maxing rompe el balance" | El RG multiplicador encarece la especialización; el PV/PE multiplier premia el balance |
| "No sé cuánto he progresado" | Barras visuales de stats, badge de RG, costes visibles de siguiente mejora |
| "Los posts cortos dan las mismas recompensas que los largos" | PP por palabras (100 palabras = 1 PP) recompensa posts sustanciales |
| "No hay metas a largo plazo" | El rango SS requiere años; siempre hay algo que mejorar |
| "Perder progreso da miedo" | PP no se descuentan al eliminar posts; no hay pérdida de progreso |

### 13.3 Filosofía de la Curva de Costes

```
50 → 130 → 350 → 800 → 1800
×2.6  ×2.7  ×2.3  ×2.25
```

Los factores de crecimiento no son constantes. Decrecen ligeramente en los rangos más altos (×2.3, ×2.25) para que el salto de 5→6 no sea tan prohibitivo como una curva puramente exponencial. Aun así, el coste acumulado para un stat en 6 es 3130 PP (base), y con multiplicador SS (×2.0) son 6260 PP.

**¿Por qué no una línea recta (ej: 100→200→300→400→500)?**
- Porque los primeros niveles serían demasiado caros (100 PP para D→C desmotivaría) y los últimos demasiado baratos (500 PP para S→SS sería alcanzable en semanas). La curva exponencial invertida (más pendiente al inicio, más plana al final, pero con valores absolutos grandes) da la sensación de "progreso rápido al principio, logro lento al final".

### 13.4 Filosofía del Rango Global como "Impuesto"

El multiplicador de coste por rango global funciona como un **impuesto progresivo**:

- **RG D (×1.0):** Sin impuesto. El personaje novato paga el coste base.
- **RG C (×1.07):** Impuesto del 7%. Apenas perceptible.
- **RG B (×1.15):** Impuesto del 15%. Empieza a notarse.
- **RG A (×1.35):** Impuesto del 35%. Significativo.
- **RG S (×1.60):** Impuesto del 60%. Duele.
- **RG SS (×2.00):** Impuesto del 100%. Pagas el doble.

Este impuesto evita que un personaje ya fuerte mejore sus stats más rápido que uno débil. Sin él, un SS con todos los stats en 6 no tendría incentivo para mejorar — pero tampoco podría, porque están al máximo. El impuesto afecta más a quienes están en rangos intermedios (B→A) que quieren seguir especializándose.

**Efecto colateral positivo:** El impuesto hace que los jugadores consideren SUBIR PRIMERO los stats bajos (que son más baratos y suben el RG) antes que los altos (que son caros y además tienen más impuesto). Esto fomenta el balance orgánicamente, sin reglas prohibitivas.

---

## 14. Consejos para Jugadores

### 14.1 Estrategias de Gasto de PP

**Estrategia "Generalista" (recomendada para nuevos):**
Sube todos los stats a 2 primero. Cuesta solo:
```
7 stats × 50 PP (RG D) = 350 PP
```
Te da RG C (suma ≥ 14) automáticamente. Luego, con RG C, los costes de mejora siguen siendo bajos (×1.07). Es la ruta más eficiente en PP para subir RG rápido.

```
PP total: 350
RG resultante: C (o B si subes alguno más)
PV/PE multiplier: 1.05
```

**Estrategia "Especialista" (para builds concretos):**
Lleva tu stat principal a 3 o 4 antes de tocar los demás. Ejemplo para un luchador FUE:
```
FUE 1→2: 50 PP (RG D)
FUE 2→3: 130 PP (RG D) → total 180 PP solo en FUE
```
Serás muy bueno en FUE (rango 3 = B), pero tu RG será D (suma = 7-1+3 = 9). Tus PV/PE serán bajos (×1.00). Ventaja: eres fuerte en combate cuerpo a cuerpo desde el principio. Desventaja: cualquier daño que recibas te dolerá más porque tienes poco PV.

**Estrategia "RG Rush" (para veteranos):**
Sube los stats más bajos primero para aumentar el rango global. Ejemplo: stats `[4,3,2,2,2,2,2]` → suma 17, RG B. Si subes los 2 a 3:
```
5 stats × 130 PP (RG B, ×1.15) = 5 × 150 = 750 PP
Nueva suma: 22 → RG A (¡subes de rango!)
```
Aunque los stats sigan siendo dispares, el nuevo RG A te da mejor multiplier de PV/PE (×1.20 vs ×1.10).

### 14.2 ¿Cuánto PP Necesito para...?

| Objetivo | PP necesarios (aprox.) |
|----------|:----------------------:|
| Subir un stat de 1 a 2 | 50 PP |
| Subir un stat de 1 a 3 | 180 PP |
| Subir un stat de 1 a 4 | 530 PP |
| Subir un stat de 1 a 5 | 1330 PP |
| Subir un stat de 1 a 6 | 3130 PP |
| Alcanzar RG C (suma 14) | ~350 PP |
| Alcanzar RG B (suma 21) | ~1260 PP |
| Alcanzar RG A (suma 28) | ~2730 PP |
| Alcanzar RG S (suma 35) | ~7000 PP |
| Alcanzar RG SS (suma 42) | ~21,910 PP |
| Desbloquear Haki Básico | ~500–800 PP (en ESP + requisitos) |
| Desbloquear Grado II de Disciplina | ~300 PP |
| Desbloquear Grado III de Disciplina | ~800 PP |

### 14.3 Lo que NO te dice el sistema

- **No gastes todos los PP en un solo stat.** Un personaje con FUE 6 y RES 1 tiene ~50 PV de base (sin multiplier). Cualquier enemigo decente lo tumba de un golpe. La supervivencia requiere RES.
- **El RG bajo no es malo.** Es temporal. Cada personaje empieza en D. Lo importante es tener una dirección clara de mejora.
- **Los PP de linaje son un bonus, no un derecho.** Si los gastas rápido, luego tendrás que ganar PP posteando. Si los ahorras, puedes hacer una compra grande más adelante.
- **No compares tu progresión con la de otros.** Un jugador que postea 3 veces por semana va a progresar más rápido que uno que postea 1 vez al mes. El sistema recompensa la actividad constante, no la intensidad esporádica.

### 14.4 La Regla de Oro

**Cada vez que subes un stat, pregúntate: ¿mi personaje ha hecho algo en la trama que justifique esta mejora?**

Si tu personaje pasó las últimas 10 sesiones entrenando con un maestro espadachín, tiene sentido subir DES. Si tu personaje ha estado cocinando en un barco, subir FUE no tiene mucho sentido narrativo.

El sistema no te obliga a justificar — pero el staff valora la coherencia. Y tú, como jugador, disfrutarás más sabiendo que tus mejoras cuentan una historia.

---

## 15. Consejos para Staff

### 15.1 Monitoreo de Progresión

**Qué vigilar:**

| Señal | Posible problema | Acción sugerida |
|-------|-----------------|-----------------|
| Stats muy dispares (6/1/1/1/1/1/1) | Min-maxer extremo. PV/PE muy bajo. | Verificar que el jugador entiende las consecuencias. Sugerir balance. |
| PP acumulados sin gastar (>2000) | El jugador puede estar acumulando para un dump masivo. | Preguntar si necesita ayuda para gastarlos o si está esperando algo concreto. |
| Progresión muy rápida (>500 PP/semana) | Posible abuso de posts cortos o multicuentas. | Revisar actividad reciente del jugador. |
| Progresión nula (0 cambios en 3 meses) | Personaje abandonado o jugador inactivo. | Contactar al jugador. Si no responde, considerar NPCización. |

### 15.2 Ajuste de Progresión

El sistema no tiene palancas directas de ajuste (no hay "x2 PP" temporal), pero el staff puede:

- **Otorgar PP manualmente** editando `data_json.pp` en DB (para recompensas de eventos, misiones, o PD gastados en progresión).
- **Limitar compras de stats** por razones narrativas (ej: "no puedes subir FUE hasta que completes esta misión de entrenamiento"). No hay bloqueo técnico, es un acuerdo con el jugador.
- **Crear eventos de "entrenamiento"** donde los participantes ganen PP extra por posts temáticos.

### 15.3 Rangos Globales y Dificultad de Encuentros

Usa el RG del personaje como guía para diseñar encuentros:

| RG del PJ | Dificultad recomendada de NPC enemigo | PV del NPC sugerido |
|:---------:|:-------------------------------------:|:-------------------:|
| D | Civil, bandido, animal pequeño | < 50 |
| C | Marino novato, pirata menor | 50–100 |
| B | Soldado experimentado, oficial bajo | 100–200 |
| A | Vicealmirante, capitán pirata fuerte | 200–400 |
| S | Miembro de Almirante/Yonko | 400–700 |
| SS | Jefe de arco legendario | 700+ |

### 15.4 Errores Comunes al Interpretar Rangos

- **"RG S significa que el PJ es extremadamente poderoso en todo."** No necesariamente. RG S significa suma de stats entre 29 y 36. Puede tener FUE 4, RES 4, AGI 4, DES 4, INT 4, INST 4, ESP 5 (suma 29 — justo en el límite inferior de S). Sigue siendo muy bueno, pero no es invencible.

- **"Un NPC con stat 7 es solo un poco mejor que un PJ con stat 6."** Incorrecto. Stat 6 = valor 60, stat 7 = valor 80 (60 + 20). Es un 33% más de valor. En PV/PE, esa diferencia se amplifica: un NPC con FUE 7 y RES 7 tiene valores 80 y 80, dando PV_base = (80×4)+(80×3) = 320+240 = 560 solo de esas dos stats. Un PJ con FUE 6 y RES 6 tiene valores 60 y 60 → PV_base = (60×4)+(60×3) = 240+180 = 420. El NPC tiene 33% más de PV solo por tener +1 en dos stats.

- **"Si el personaje tiene muchos PP, debe haber estado roleando mucho."** Puede ser, pero también puede haber recibido PP de eventos, misiones, o PD. Verifica el origen si hay dudas.

### 15.5 Cómo Usar el Sistema de Rangos Narrativamente

- **Cambio de rango global = evento.** Cuando un PJ sube de C a B, el staff puede crear un hilo narrativo: "El cuartel marino te asciende", "Los piratas rivales empiezan a respetarte", "Tu recompensa aumenta".
- **Subida de stat = entrenamiento visible.** Si un PJ sube FUE de 3 a 4, menciónalo en combates: "Tus golpes, antes potentes, ahora parten rocas."
- **Rango S o SS = estatus de leyenda.** Un PJ que alcanza RG S debería ser reconocido in-world. Un RG SS es un NPC en potencia: el jugador ha llevado a su personaje al techo del sistema.

---

## 16. Referencia Rápida

### 16.1 Fórmulas Esenciales

```
Rango Global desde suma de stats entrenados:
  ≤10 → D    11–16 → C    17–22 → B    23–28 → A    29–36 → S    ≥37 → SS

Nivel desde Rango: D=1, C=2, B=3, A=4, S=5, SS=6

Coste de mejora de stat individual:
  CosteBase[rango_actual] × Multiplicador[RG_actual]
  
CosteBase: 1→50, 2→130, 3→350, 4→800, 5→1800

Multiplicadores por RG:
  D=1.00, C=1.07, B=1.15, A=1.35, S=1.60, SS=2.00

PP por palabras de rol: floor(palabras / 100)

PP acumulados para rango N en un stat (base, sin ×RG):
  1→0, 2→50, 3→180, 4→530, 5→1330, 6→3130

PV/PE Multiplicador por RG:
  D=1.00, C=1.05, B=1.10, A=1.20, S=1.35, SS=1.50
```

### 16.2 Archivos Clave

| Archivo | Propósito |
|---------|-----------|
| `game/src/Shared/StatScale.php` | Constantes, conversiones rangos↔valores, multiplicadores, cálculo de rank global |
| `game/src/Application/Services/CharacterProgression.php` | Validación, aplicación y snapshot de compras de stats |
| `game/ajax/purchase_attribute.php` | Endpoint AJAX para comprar stats |
| `game/src/Application/Services/CharacterSaveService.php` | Cálculo de rank inicial en creación/aprobación |
| `game/src/Infrastructure/Persistence/PersonajeRepository.php` | Acceso a datos de personaje |
| `inc/plugins/game_postcharacter.php` | Plugin que otorga PP por posts |
| `game/inc/stat_helpers.php` | `game_build_stat_context()`, `game_compute_pv_pe_from_context()` |
| `game/views/personaje/_sidebar.php` | Badge de rango global en ficha |
| `game/views/personaje/_tab_gestion.php` | Panel de compra de stats (submódulo) |
| `game/sql/migrate_stats_v7.php` | Migración histórica a sistema de stats v7 |

### 16.3 Flujo de Datos de una Compra de Stat

```
JS → POST /purchase_attribute.php
  → Verificar login, POST, CSRF, ownership, status aprobada
  → syncLinajeBonusPp()    (asegurar PP de linaje)
  → normalize()            (asegurar defaults)
  → validateStatUpgrade()  (validar compra)
  → applyStatUpgrade()     (descontar PP, subir stat, recalcular RG)
  → UPDATE game_personajes (persistir data_json + stats_json)
  → JSON response (nuevos PP, stats, rank)
```

### 16.4 Flujo de Datos de Ganancia de PP por Post

```
Usuario postea en un hilo
  → Hook datahandler_post_insert_post_end
  → Plugin game_postcharacter.php
  → Contar palabras del post (excluyendo Off_Rol)
  → PP_ganados = floor(palabras / 100)
  → UPDATE game_personajes (data_json.pp += PP_ganados)
  → Incrementar postnum
```

---

## APÉNDICE A: Tabla de PP Acumulados por Combinación de Stats

### A.1 PP Totales para Alcanzar un Perfil de Stats Concreto

| Perfil | Distribución | Suma | RG | PP total (base) |
|--------|:------------:|:----:|:--:|:---------------:|
| Novato equilibrado | `[2,2,2,2,2,2,2]` | 14 | C | 350 |
| Especialista FUE | `[4,1,1,1,1,1,1]` | 10 | D | 530 |
| Luchador versátil | `[3,3,3,2,2,2,2]` | 17 | B | 870 |
| Tanque | `[3,4,2,2,2,2,2]` | 17 | B | 1020 |
| Usuario de Haki | `[2,2,2,2,3,3,4]` | 18 | B | 1150 |
| Capitán competente | `[4,3,3,3,3,3,3]` | 22 | B | 2280 |
| Oficial de élite | `[4,4,4,3,3,3,3]` | 24 | A | 3360 |
| Veterano de Grand Line | `[4,4,4,4,4,4,4]` | 28 | A | 7420 |
| Maestro reconocido | `[5,5,5,5,5,5,5]` | 35 | S | 19,180 |
| Leyenda (SS teórico) | `[6,6,6,6,6,6,6]` | 42 | SS | 21,910 |

### A.2 Aplicando Multiplicadores de RG (Coste Real Aprox.)

| Perfil | PP base | Multiplicador medio aplicado | PP real aprox. |
|--------|:-------:|:---------------------------:|:--------------:|
| Novato equilibrado | 350 | ×1.00 (todo en D) | 350 |
| Especialista FUE | 530 | ×1.00–1.07 | ~550 |
| Tanque | 1020 | ×1.00–1.15 | ~1150 |
| Capitán competente | 2280 | ×1.00–1.35 | ~2900 |
| Veterano de GL | 7420 | ×1.00–1.60 | ~10,500 |
| Maestro reconocido | 19,180 | ×1.00–2.00 | ~28,000 |
| Leyenda | 21,910 | ×2.00 (últimas compras) | ~43,820 |

---

*Fin del documento — Guía completa del Sistema de Rangos y Progresión v2.0*
*Generado desde: `Guias/sistemas/03-rangos.md`*
*Referencia: `Guias/MAESTRO_SISTEMAS_RPG.md` — Sección 3*
