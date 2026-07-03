# STATS — LOS 7 ATRIBUTOS — GUÍA COMPLETA

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 2
> **Propósito:** Documentar exhaustivamente el sistema de atributos: modelo matemático, escalas, bonos raciales, cálculo de PV/PE, costes de progresión — y **por qué** cada decisión de diseño se tomó así, cómo impacta en la experiencia RPG, y consejos para aprovecharlo.

---

## ÍNDICE

1. [Los 7 Atributos — Definición y Filosofía](#1-los-7-atributos)
2. [Sistema de Rangos (1–6)](#2-sistema-de-rangos)
3. [Valor Numérico de los Stats](#3-valor-numérico-de-los-stats)
4. [Bonificaciones Raciales](#4-bonificaciones-raciales)
5. [Cálculo de PV y PE](#5-cálculo-de-pv-y-pe)
6. [Rango Global del Personaje](#6-rango-global-del-personaje)
7. [Costes de Progresión](#7-costes-de-progresión)
8. [StatScale — Clase Completa](#8-statscale)
9. [game_build_stat_context — Contexto de Stats](#9-gamebuildstatcontext)
10. [Visualización y CSS](#10-visualización-y-css)
11. [Integración con el Sistema de Personaje](#11-integración-con-el-sistema-de-personaje)
12. [Límites y Casos Especiales](#12-límites-y-casos-especiales)
13. [Ejemplos Prácticos](#13-ejemplos-prácticos)
14. [Migraciones Históricas](#14-migraciones-históricas)
15. [Filosofía de Diseño del Sistema de Stats](#15-filosofía-de-diseño)
16. [Consejos para Jugadores](#16-consejos-para-jugadores)
17. [Consejos para Staff](#17-consejos-para-staff)
18. [Referencia Rápida](#18-referencia-rápida)

---

## 1. Los 7 Atributos

### 1.1 Los Stats

El sistema define **7 atributos (stats)** que representan las capacidades fundamentales de todo personaje.

| Slug | Nombre | Descripción narrativa | Mecánica principal |
|------|--------|----------------------|-------------------|
| `fue` | **Fuerza** | Potencia física bruta, capacidad de carga, daño cuerpo a cuerpo | Daño físico, capacidad de carga, tiradas de fuerza bruta |
| `res` | **Resistencia** | Aguante físico, tolerancia al dolor, capacidad cardiovascular | PV máximos, resistencia a daño, aguante en condiciones extremas |
| `agi` | **Agilidad** | Velocidad de movimiento, reflejos, coordinación, evasión | Iniciativa, esquiva, velocidad de desplazamiento, precisión en movimiento |
| `des` | **Destreza** | Precisión manual, control fino, habilidad técnica, puntería | Precisión con armas, PE máximos, habilidades de oficio |
| `int` | **Intelecto** | Razonamiento, conocimiento, táctica, inteligencia | PE máximos, habilidad con estrategia, conocimiento del mundo |
| `inst` | **Instinto** | Percepción, intuición, voluntad, resistencia mental | Detección, resistencia a engaños, voluntad contra efectos mentales |
| `esp` | **Espíritu** | Energía vital, presencia, carisma, Haki latente | PE máximos, poder de Haki, presencia imponente, carisma |

### 1.2 Filosofía de Diseño: ¿Por qué estos 7 y no otros?

**¿Por qué 7 stats y no 6 como D&D?**
- One Piece tiene un componente espiritual muy fuerte (Haki, voluntad, presencia). Separar `int` (intelecto analítico) de `inst` (instinto perceptivo) y `esp` (espíritu/presencia) permite que un personaje sea sabio pero no inteligente, o carismático pero distraído.
- La tríada `fue`/`res`/`agi` cubre el físico; `des`/`int` cubren lo técnico/mental; `inst`/`esp` cubren lo perceptivo/espiritual. Es un sistema 3-2-2 que balancea cuerpo-mente-espíritu.

**¿Por qué orden `fue`/`res`/`agi`/`des`/`int`/`inst`/`esp`?**
- No es alfabético ni aleatorio: va de lo más físico a lo más espiritual. FUE y RES son cuerpo puro; AGI y DES son control del cuerpo; INT es mente; INST es intuición; ESP es alma. El orden refleja el viaje de lo tangible a lo intangible.

**¿Por qué `esp` como stat separado?**
- En One Piece, el Espíritu (o voluntad) es fundamental: el Haki funciona con él, la presencia de un personaje impacta escenas, y la resistencia mental depende de él. Meterlo dentro de "carisma" o "sabiduría" habría perdido matiz.

**¿Y por qué NO hay un stat de "Carisma"?**
- En One Piece, el carisma se manifiesta como voluntad (ESP), no como habilidad social separada. Un personaje carismático en One Piece es alguien con presencia arrolladora, no alguien con labia. Por eso `esp` cumple ese rol.

### 1.3 Impacto RPG

| Esta decisión... | Hace que en el juego... |
|-----------------|------------------------|
| 7 stats con roles claros | Cada personaje tenga un perfil único. Dos "guerreros" pueden ser completamente distintos (uno FUE alto, otro AGI alto). |
| Sin stat de carisma explícito | La personalidad del jugador importe más que un número. Un personaje con ESP bajo igual puede ser líder si el jugador es buen roleador. |
| Espíritu como stat separado | Los usuarios de Haki tengan que invertir PP específicamente, creando un arquetipo diferenciado. |

### 1.4 Orden Canónico

```php
const STAT_KEYS = ['fue', 'res', 'agi', 'des', 'int', 'inst', 'esp'];
```

Este orden se usa en todos los lugares del sistema (StatScale, stats_json, build_stat_context, renders, wizard JS, purchase_attribute).

---

## 2. Sistema de Rangos (1–6)

### 2.1 Escala de Rangos

Cada stat individual va de **1 (mínimo)** a **6 (máximo absoluto de PJ ordinario)**.

| Rango | Nombre | Equivalencia narrativa |
|-------|--------|----------------------|
| 1 | **D** | Principiante / civil sin entrenamiento |
| 2 | **C** | Entrenado / marinero o pirata novato |
| 3 | **B** | Competente / soldado o pirata de la Grand Line |
| 4 | **A** | Experto / oficial de alto rango o capitán fuerte |
| 5 | **S** | Maestro / vicealmirante o capitán de los Blues |
| 6 | **SS** | Leyenda / nivel Almirante u equivalente |

**NPCs mayores** pueden tener rangos de 7+ (SS+, SS++, M), inalcanzables para PJs.

### 2.2 Filosofía de Diseño: ¿Por qué 1–6 y no 1–10 o 1–100?

**Rango comprimido (1–6) en lugar de escala amplia (1–100):**
- **Cada punto importa.** En un sistema 1–100, subir de 50 a 51 se siente intrascendente. En 1–6, subir de 3 a 4 (B→A) es un hito narrativo: "ahora eres EXPERTO". Cada incremento merece celebración.
- **Menos math, más rol.** Los jugadores recuerdan "tengo FUE A" mejor que "tengo FUE 73". La escala D→SS es intuitiva y temática con One Piece.
- **Gatekeeping claro.** Para requisitos (Haki, técnicas), "necesitas INST 4+" es mucho más claro que "necesitas INST 80".

**La nomenclatura D→SS en lugar de números:**
- Es directa de One Piece (cazarrecompensas, rangos). Un personaje rank S suena inmediatamente poderoso.
- La escala tiene solo 6 escalones, pero cada uno se siente como un logro real.

**¿Por qué 6 y no 7 como los stats?**
- 6 rangos permiten que cada stat tenga exactamente 6 niveles, dando un máximo de 42 puntos totales (7×6). La simetría es intencional: el máximo perfecto es un número icónico (42).
- El rango 7+ existe para NPCs, manteniendo el misterio de "¿qué hay más allá del SS?" para los jugadores.

### 2.3 Impacto RPG

| Esta decisión... | Hace que en el juego... |
|-----------------|------------------------|
| Solo 6 rangos | Cada subida de stat sea un evento. Un jugador no sube stats todos los días, sino cada varias semanas. |
| D→SS temático | Los jugadores hablen "en personaje" de rangos ("¡Mi FUE es B ya!") con naturalidad. |
| Rango 7+ solo NPC | Los jefes finales tengan un aura de misterio: "¿qué rango tiene?" — los jugadores no pueden saberlo con exactitud. |

### 2.4 Rangos Efectivos vs Rangos Entrenados

Es IMPORTANTÍSIMO entender la diferencia:

- **Rango Entrenado (trained):** El valor base comprado con PP. Se almacena en `stats_json` y se sanitiza entre 1 y 6.
- **Bono Racial:** Modificador racial, definido en `linaje_catalog.json`.
- **Rango Efectivo:** `rango_entrenado + bono_racial + modificadores_de_turno`.

```
Ejemplo: Gyojin con FUE entrenada = 3, bono racial +1
→ Rango efectivo de FUE = 4
```

**El rango entrenado NUNCA se modifica por bonos raciales.** Los bonos son siempre en runtime.

### 2.5 Filosofía de Diseño: Separación entrenado/efectivo

¿Por qué no guardar directamente el rango efectivo en la DB?
- **Portabilidad:** Si un personaje cambia de raza (evento narrativo), sus stats entrenados no cambian, solo los bonos.
- **Transparencia para el jugador:** Sabes que tu FUE "real" es 3 (B), aunque con tu raza se vea como 4 (A). Si pierdes tu bono racial, no pierdes progreso real.
- **Modificadores temporales:** Los bonuses de comida, entorno, o cartas se apilan sobre el efectivo sin tocar lo entrenado.

### 2.6 Sanitización

Toda entrada de stats pasa por `StatScale::sanitizeRanks()`:

```php
public static function sanitizeRanks(array $raw): array
{
    $clamp = static fn($v): int => max(1, min(6, (int)$v));
    $out = [];
    foreach (self::STAT_KEYS as $key) {
        $out[$key] = $clamp($raw[$key] ?? 1);
    }
    return $out;
}
```

---

## 3. Valor Numérico de los Stats

### 3.1 Conversión Rango → Valor

```php
public static function rangoAValor(int $rango): int
{
    return match ($rango) {
        1 => 4,
        2 => 8,
        3 => 15,
        4 => 26,
        5 => 40,
        6 => 60,
        default => 4,
    };
}
```

| Rango | Valor |
|-------|-------|
| 1 (D) | 4 |
| 2 (C) | 8 |
| 3 (B) | 15 |
| 4 (A) | 26 |
| 5 (S) | 40 |
| 6 (SS) | 60 |

### 3.2 Filosofía de Diseño: ¿Por qué esta curva exponencial?

Los valores NO son lineales (no 4→8→12→16→20→24). Son **exponenciales**: 4→8→15→26→40→60.

**¿Por qué?**
- **Diferencia real entre rangos bajos:** Pasar de D a C (4→8) duplica tu valor. Es un salto enorme. Un entrenado novato es el DOBLE de efectivo que un civil. Narrativamente correcto.
- **Diferencia entre rangos altos:** Pasar de S a SS (40→60) es una mejora del 50%. No es duplicar, porque en los rangos altos la diferencia está en técnica, no en bruto. Pero sigue siendo significativa.
- **La fórmula secreta:** Los incrementos siguen `+4, +7, +11, +14, +20`. Cada incremento crece ~50% respecto al anterior:
  - D→C: +4
  - C→B: +7 (75% más que +4)
  - B→A: +11 (57% más que +7)
  - A→S: +14 (27% más que +11)
  - S→SS: +20 (43% más que +14)

**¿Qué consigue esta curva?**
- Los rangos bajos son accesibles y dan mucha recompensa por poco PP.
- Los rangos altos requieren mucha inversión para mejoras porcentualmente menores.
- Un rango D no es inútil: tiene valor 4, que sigue aportando a PV/PE. Nadie es completamente irrelevante.

### 3.3 Rangos Efectivos > 6 (NPCs)

```php
$valor = ($rangoEfectivo <= 6)
    ? self::rangoAValor($rangoEfectivo)
    : self::rangoAValor(6) + (($rangoEfectivo - 6) * 20);
```

| Rango efectivo | Valor |
|----------------|-------|
| 6 (SS) | 60 |
| 7 (SS+) | 80 |
| 8 (SS++) | 100 |
| 9 (M) | 120 |

**Diseño:** A partir de 7, cada punto extra da +20 lineal. Es intencionalmente mucho más generoso que la escala de PJs (donde de 5 a 6 daba +20). Esto permite que NPCs jefes tengan PV/PE descomunales sin necesidad de rangos 20 imposibles.

### 3.4 Label de Display

```php
public static function rankDisplayLabel(int $rangoEfectivo): string
{
    if ($rangoEfectivo <= 0) return '—';
    if ($rangoEfectivo <= 6) return self::RANK_NAMES[$rangoEfectivo] ?? 'D';
    if ($rangoEfectivo === 7) return 'SS+';
    if ($rangoEfectivo === 8) return 'SS++';
    return 'M';
}
```

---

## 4. Bonificaciones Raciales

### 4.1 Catálogo de Bonos

Archivo: `game/data/linaje_catalog.json`

| Raza | FUE | RES | AGI | DES | INT | INST | ESP |
|------|:---:|:---:|:---:|:---:|:---:|:----:|:---:|
| Humano | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| Mink | 0 | 0 | +1 | +1 | 0 | +1 | 0 |
| Gyojin | +1 | +1 | -1 | 0 | 0 | 0 | 0 |
| Gigante | +2 | +2 | -1 | -1 | 0 | 0 | 0 |
| Tontatta | -2 | -1 | +2 | +2 | +1 | 0 | 0 |
| Buccaner | +1 | +1 | 0 | 0 | 0 | 0 | +2 |
| Lunarian | +1 | +2 | -1 | 0 | 0 | 0 | +1 |
| Skypean | 0 | 0 | +1 | 0 | +1 | +1 | +1 |
| Oni | +2 | +1 | 0 | -1 | -1 | +1 | 0 |
| Sirena | -1 | 0 | +1 | +1 | +1 | 0 | +2 |

### 4.2 Filosofía de Diseño: ¿Por qué estos bonos exactos?

**Principios de balance racial:**
1. **Suma de bonos ≈ 0 en todas las razas.** Si sumas todos los bonos de una raza, el resultado es 0 o muy cercano. Gigante tiene +2+2-1-1+0+0+0 = +2 neto (pero su tamaño colosal también trae desventajas narrativas que no se reflejan en stats). Tontatta tiene -2-1+2+2+1 = +2 neto (pero es diminuto).
2. **Cada raza tiene un "precio" que paga.** Gigante paga AGI -1 y DES -1 por su FUE+2 y RES+2. Gyojin paga AGI -1. Tontatta paga FUE -2 y RES -1.
3. **Humanos tienen todo a 0.** No por aburridos, sino porque la ventaja humana está en el sistema de linaje (más puntos de linaje que nadie: 28), no en stats.

**¿Por qué los bonos son asimétricos?**
- Porque así se crean arquetipos naturales. Un Gigante va a ser lento pero tanque. Un Mink va a ser escurridizo y perceptivo. Un jugador elige raza no solo por lore, sino porque quiere jugar ese arquetipo.
- Si todas las razas fueran balanceadas perfectamente (misma suma, mismas opciones), la elección sería cosmética. La asimetría genera decisiones significativas.

### 4.3 Impacto RPG

| Bono racial | Lo que significa en juego |
|-------------|--------------------------|
| Gigante +2 FUE, +2 RES | Inmenso, resistente, pero lento. Ideal para tanques. |
| Tontatta +2 AGI, +2 DES | Pequeño, rápido, preciso. Ideal para pícaros/tiradores. |
| Mink +1 AGI, +1 DES, +1 INST | Versátil, equilibrado. Buenos en casi todo sin sobresalir en nada concreto. |
| Humano todo 0 | "Normal" pero con más flexibilidad de linaje. |

### 4.4 Carga de Bonos en Runtime

`StatScale::getRacialBonuses()`:

```php
public static function getRacialBonuses(string $raceName): array
{
    $catalog = self::loadCatalog();
    $race = $catalog['races'][$raceName] ?? null;
    $bonuses = is_array($race) ? ($race['stat_bonuses'] ?? []) : [];
    $out = [];
    foreach (self::STAT_KEYS as $key) {
        $out[$key] = (int)($bonuses[$key] ?? 0);
    }
    return $out;
}
```

**Para híbridos:** El catálogo NO tiene entrada de híbrido. `getRacialBonuses()` retorna `[0,0,0,0,0,0,0]`. El balance de híbridos viene del sistema de linaje.

### 4.5 Cálculo de Rangos Efectivos

```php
public static function effectiveRanks(array $ranks, string $raceName): array
{
    $bonuses = self::getRacialBonuses($raceName);
    $out = [];
    foreach (self::STAT_KEYS as $key) {
        $out[$key] = (int)($ranks[$key] ?? 1) + (int)($bonuses[$key] ?? 0);
    }
    return $out;
}
```

---

## 5. Cálculo de PV y PE

### 5.1 Fórmulas Base

**PV (Puntos de Vida):**

```php
PV_base = (res_valor × 4) + (fue_valor × 3) + (esp_valor × 2) + (agi_valor × 1)
```

**PE (Puntos de Energía):**

```php
PE_base = (esp_valor × 4) + (des_valor × 3) + (int_valor × 2) + (agi_valor × 1)
```

### 5.2 Filosofía de Diseño: ¿Por qué estas fórmulas y pesos?

**PV — ¿Por qué RES pesa 4 y FUE 3?**
- **RES (×4)** es el stat de aguante. Es lógico que sea el que más PV dé. Si quieres ser un tanque, inviertes en RES.
- **FUE (×3)** también aporta porque un cuerpo fuerte tiene más masa y resistencia estructural.
- **ESP (×2)** aporta porque la voluntad también mantiene vivo a un personaje (One Piece: personajes que no se rinden).
- **AGI (×1)** aporta mínimo porque un cuerpo ágil esquiva, no absorbe.

**PE — ¿Por qué ESP pesa 4 y DES 3?**
- **ESP (×4)** es el stat de energía espiritual. Es el PE por excelencia. Sin ESP alto, tienes poca reserva de energía para técnicas.
- **DES (×3)** aporta porque la precisión también requiere energía concentrada.
- **INT (×2)** porque el intelecto requiere energía mental.
- **AGI (×1)** porque moverse rápido gasta energía, pero es el menor contribuyente.

**¿Por qué PV y PE no son simétricos?**
- Porque un personaje puede ser tanque (RES alto → mucho PV) pero tener poca energía (ESP bajo → poco PE). Esto crea tradeoffs: un guerrero resistente pero que se cansa rápido, o un usuario de Haki con mucha energía pero frágil.

### 5.3 Impacto RPG

| Perfil de stats | Resultado |
|----------------|-----------|
| RES alta, ESP baja | Mucho PV, poco PE. Tanque que se cansa. Ideal para combatientes cuerpo a cuerpo básicos. |
| ESP alta, RES baja | Poco PV, mucho PE. Cristal. Mucha energía para técnicas pero frágil. Ideal para usuarios de Haki o Akuma. |
| RES y ESP altas | Mucho PV y mucho PE. Carísimo de alcanzar (invertir en dos stats). Personaje de élite. |
| AGI y DES altas | PV y PE medios, pero buena velocidad y precisión. Estilo esquivador/técnico. |

### 5.4 Multiplicador de Rango Global

```php
const PV_PE_MULTIPLIERS = [
    'D' => 1.00, 'C' => 1.05, 'B' => 1.10,
    'A' => 1.20, 'S' => 1.35, 'SS' => 1.50,
];
```

**Filosofía del multiplicador:**
- Recompensa tener un rango global alto (muchos stats subidos).
- Un personaje rango A tiene 20% más PV/PE que uno rango D con los mismos stats. Esto incentiva la progresión balanceada (subir todos los stats) en lugar de min-maxear uno solo.
- El multiplicador SS (1.50) hace que los personajes tope de rango sean sustancialmente más duraderos.

### 5.5 Función Completa

```php
function game_compute_pv_pe_from_context(array $values, ?array $trainedRanks = null): array
{
    $pv_base = StatScale::computeMaxPv($values);
    $pe_base = StatScale::computeMaxPe($values);

    if ($trainedRanks !== null) {
        $rango_global = StatScale::globalRankFromSum(StatScale::sumRanks($trainedRanks));
        $mult = StatScale::getMultiplicadorPvPe($rango_global);
        return [
            'max_pv' => (int) round($pv_base * $mult),
            'max_pe' => (int) round($pe_base * $mult),
        ];
    }

    return ['max_pv' => $pv_base, 'max_pe' => $pe_base];
}
```

### 5.6 Ejemplos de Cálculo

**Personaje rango C (todos los stats en 2, Humano):**

```
Valores: todos 8
PV_base = (8×4)+(8×3)+(8×2)+(8×1) = 32+24+16+8 = 80
PE_base = (8×4)+(8×3)+(8×2)+(8×1) = 32+24+16+8 = 80
Suma=14 → RG C (1.05)
PV=84, PE=84
```

**Personaje rango B (Gyojin, stats variados):**
| Stat | Entrenado | Bono | Valor |
|------|:---------:|:----:|:-----:|
| FUE | 4 | +1 | 40 |
| RES | 3 | +1 | 26 |
| AGI | 3 | -1 | 8 |
| DES | 2 | 0 | 8 |
| INT | 3 | 0 | 15 |
| INST | 2 | 0 | 8 |
| ESP | 3 | 0 | 15 |

```
PV_base = (26×4)+(40×3)+(15×2)+(8×1) = 104+120+30+8 = 262
PE_base = (15×4)+(8×3)+(15×2)+(8×1) = 60+24+30+8 = 122
Suma entrenados = 20 → RG B (1.10)
PV = 288, PE = 134
```

> **Consejo:** Fíjate que aunque AGI efectivo es bajo (2) por el bono -1, eso apenas afecta a PV/PE porque AGI solo pesa ×1. El Gyojin sacrifica AGI pero gana mucha FUE y RES, que pesan más en PV.

---

## 6. Rango Global del Personaje

### 6.1 Cálculo desde Suma de Stats

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

| Rango Global | Suma mínima | Suma máxima | Nivel |
|:-----------:|:-----------:|:-----------:|:-----:|
| D | 7 | 10 | 1 |
| C | 11 | 16 | 2 |
| B | 17 | 22 | 3 |
| A | 23 | 28 | 4 |
| S | 29 | 36 | 5 |
| SS | 37 | 42 | 6 |

### 6.2 Filosofía de Diseño: ¿Por qué estos umbrales?

Los umbrales están calibrados para que:
1. **Rango D** sea alcanzable con stats mínimos (7-10, apenas por encima de la base). Cualquier personaje recién creado empieza aquí.
2. **Rango C** requiera ~14 (media de 2 por stat). Alcanzable con unas pocas mejoras.
3. **Rango B** (~20, media ~3) marca el punto donde el personaje es competente en todo. Es el "nivel de entrada a Grand Line".
4. **Rango A** (~25, media ~3.5) ya requiere especialización. No puedes tener ningún stat realmente bajo.
5. **Rango S** (~32, media ~4.5) es para personajes avanzados con 2-3 stats en 5+.
6. **Rango SS** (~39, media ~5.5) es prácticamente la perfección: todos los stats muy altos.

**La curva es más indulgente al inicio:** Pasar de D a C requiere solo +4 puntos totales (menos de 1 stat completo). Pasar de S a SS requiere +6 puntos (casi un stat entero de 5 a 6). Esto hace que los rangos bajos se suban rápido (motivación) y los altos sean una carrera larga.

**¿Por qué se usan rangos ENTRENADOS (sin bonos raciales)?**
- Para que el rango global refleje el esfuerzo real del jugador, no su elección racial.
- Un Mink con +1 en 3 stats no empieza con rango más alto que un Humano con la misma inversión de PP.

### 6.3 Impacto RPG

| Rango | Lo que significa para el jugador |
|-------|----------------------------------|
| D→C | "Ya no soy un civil. Soy alguien." (1-2 semanas de juego) |
| C→B | "Puedo enfrentarme a amenazas serias." (1-2 meses) |
| B→A | "Soy alguien importante en este mar." (3-6 meses) |
| A→S | "Mi nombre empieza a ser conocido." (6-12 meses) |
| S→SS | "Soy una leyenda viviente." (1-2 años de juego constante) |

### 6.4 Nivel desde Rango

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

### 6.5 PP Gastados por Rango

```php
const RANK_CUMULATIVE_PP = [
    1 => 0,      // D
    2 => 50,     // D→C
    3 => 180,    // C→B
    4 => 530,    // B→A
    5 => 1330,   // A→S
    6 => 3130,   // S→SS
];
```

`ppSpentOnRanks()` calcula el PP total gastado para alcanzar los rangos actuales:

```php
public static function ppSpentOnRanks(array $ranks): int
{
    $total = 0;
    foreach (self::STAT_KEYS as $key) {
        $r = max(1, min(6, (int)($ranks[$key] ?? 1)));
        $total += self::RANK_CUMULATIVE_PP[$r] ?? 0;
    }
    return $total;
}
```

---

## 7. Costes de Progresión

### 7.1 Coste Base por Rango

```php
const RANK_UPGRADE_COST = [
    1 => 50,    // D → C
    2 => 130,   // C → B
    3 => 350,   // B → A
    4 => 800,   // A → S
    5 => 1800,  // S → SS
];
```

### 7.2 Multiplicador por Rango Global

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

### 7.3 Fórmula de Coste Final

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

### 7.4 Tabla de Costes

| Rango actual del stat | Coste base | RG D | RG C | RG B | RG A | RG S | RG SS |
|:---------------------:|:----------:|:----:|:----:|:----:|:----:|:----:|:-----:|
| 1→2 (D→C) | 50 | **50** | 54 | 58 | 68 | 80 | 100 |
| 2→3 (C→B) | 130 | **130** | 139 | 150 | 176 | 208 | 260 |
| 3→4 (B→A) | 350 | **350** | 375 | 403 | 473 | 560 | 700 |
| 4→5 (A→S) | 800 | **800** | 856 | 920 | 1080 | 1280 | 1600 |
| 5→6 (S→SS) | 1800 | **1800** | 1926 | 2070 | 2430 | 2880 | 3600 |

### 7.5 Filosofía de Diseño: ¿Por qué costes exponenciales?

**Los costes base crecen así: 50 → 130 (×2.6) → 350 (×2.7) → 800 (×2.3) → 1800 (×2.25).**

**¿Por qué?**
1. **Progresión natural:** Los primeros niveles son baratos para que el jugador sienta progreso rápido. Subir un stat de 1 a 2 cuesta solo 50 PP (una sesión o dos de juego).
2. **Muro natural en rangos altos:** Subir de 5 a 6 cuesta 1800 PP base. Con rango global S, son 2880 PP. Eso son semanas o meses de juego. Evita que los jugadores lleguen a SS en pocos meses.
3. **El rango global multiplier evita el min-maxing extremo.** Si solo subes FUE, tu rango global se queda bajo, así que pagas poco. Pero para ser SS necesitas subir TODO. El multiplier castiga a los especialistas extremos.

**¿Por qué el multiplier sube tan agresivamente en S/SS?**
- Para que llegar a SS sea un logro que requiera balance. Si pudieras tener FUE 6 con rango D pagando solo 50+130+350+800+1800 = 3130 PP (sin multiplier), sería demasiado fácil tener un stat maxeado. El multiplier de SS (×2.0) duplica el coste, haciendo que subir ese último nivel cueste 3600 PP.

### 7.6 Impacto RPG

| Esta decisión... | Hace que en el juego... |
|-----------------|------------------------|
| Costes exponenciales | Los PJs nuevos progresen rápido (enganche) y los veteranos tengan metas a largo plazo. |
| Multiplicador por rango global | Un jugador que sube solo FUE tenga un techo. Para ser fuerte de verdad, debe ser completo. |
| Sin límite de rango global por nivel | Cualquier personaje, por nuevo que sea, puede subir cualquier stat si tiene PP. |

### 7.7 Sistema de Gasto de PP

Cuando un jugador compra un stat, el sistema primero gasta `pp_linaje` (PP provenientes de linaje), y luego PP normales:

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

**Consejo de diseño:** Los PP de linaje se gastan primero porque son un regalo racial. Una vez gastados, no vuelven. Esto hace que la elección de linaje tenga peso: si eliges muchos perks (gastas puntos de linaje), recibes menos PP bonus, y viceversa.

### 7.8 Validación de Compra

`CharacterProgression::validateStatUpgrade()`:

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

---

## 8. StatScale

### 8.1 Definición Completa de la Clase

Archivo: `game/src/Shared/StatScale.php` (363 líneas)

```php
namespace Game\Shared;

final class StatScale
{
    public const WORDS_PER_PP = 100;
    public const STAT_KEYS = ['fue', 'res', 'agi', 'des', 'int', 'inst', 'esp'];
    public const RANK_NAMES = [1 => 'D', 2 => 'C', 3 => 'B', 4 => 'A', 5 => 'S', 6 => 'SS'];
    public const RANK_CUMULATIVE_PP = [1 => 0, 2 => 50, 3 => 180, 4 => 530, 5 => 1330, 6 => 3130];
    public const RANK_UPGRADE_COST = [1 => 50, 2 => 130, 3 => 350, 4 => 800, 5 => 1800];

    public const RANK_GLOBAL_MULTIPLIERS = [
        'D' => 1.00, 'C' => 1.07, 'B' => 1.15,
        'A' => 1.35, 'S' => 1.60, 'SS' => 2.00,
    ];
    public const PV_PE_MULTIPLIERS = [
        'D' => 1.00, 'C' => 1.05, 'B' => 1.10,
        'A' => 1.20, 'S' => 1.35, 'SS' => 1.50,
    ];

    // Conversión rango↔valor
    public static function rangoAValor(int $rango): int
    public static function rangoEfectivoAValor(int $rangoEntrenado, int $bonoRacial): int
    public static function rankDisplayLabel(int $rangoEfectivo): string
    public static function rankDisplayCssClass(int $rangoEfectivo): string

    // Rango global
    public static function globalRankFromSum(int $sumaRangos): string
    public static function globalNivelFromRank(string $rank): int
    public static function globalRankCssClass(string $rank): string

    // Costes
    public static function getStatUpgradeCost(int $rangoActual, string $rangoGlobal): int
    public static function getMultiplicadorPvPe(string $rangoGlobal): float

    // Manipulación de stats
    public static function defaultRanks(): array
    public static function sanitizeRanks(array $raw): array
    public static function getRacialBonuses(string $raceName): array
    public static function effectiveValues(array $ranks, string $raceName): array
    public static function effectiveRanks(array $ranks, string $raceName): array
    public static function computeMaxPv(array $values): int
    public static function computeMaxPe(array $values): int
    public static function sumRanks(array $ranks): int
    public static function ppSpentOnRanks(array $ranks): int

    // Haki / Akuma
    public static function minEspRankForHaki(string $hakiType, string $hakiLevel): int
    public static function minEspRankForAkumaTier(int $tier): int
    public static function minNivelForAkumaTier(int $tier): int
}
```

---

## 9. game_build_stat_context

### 9.1 Definición

Archivo: `game/inc/stat_helpers.php`

```php
function game_build_stat_context(
    array $statsRaw,
    string $raceName,
    array $turnModifiers = []
): array {
    $trained = StatScale::sanitizeRanks($statsRaw);
    $racial = StatScale::getRacialBonuses($raceName);
    $effectiveRanks = [];
    $values = [];
    $display = [];

    foreach (StatScale::STAT_KEYS as $key) {
        $mod = (int)($turnModifiers[$key] ?? 0);
        $effRank = (int)$trained[$key] + (int)($racial[$key] ?? 0) + $mod;
        $effectiveRanks[$key] = $effRank;
        $values[$key] = StatScale::rangoEfectivoAValor(
            (int)$trained[$key],
            (int)($racial[$key] ?? 0) + $mod
        );
        $display[$key] = StatScale::rankDisplayLabel($effRank);
    }

    return [
        'trained' => $trained,
        'effective_ranks' => $effectiveRanks,
        'values' => $values,
        'display' => $display,
    ];
}
```

### 9.2 Usos en el Sistema

| Archivo | Propósito |
|---------|-----------|
| `CharacterSheetLoader::mapRowToChar()` | Cargar contexto para la ficha |
| `_sidebar.php` | Renderizar stats y PV/PE |
| `_tab_gestion.php` | Mostrar stats en panel de gestión |
| `_tab_haki.php` | Calcular requisitos de Haki |
| `get_active_pj_for_user.php` | Contexto para el PJ activo (API) |
| `get_personaje_preview.php` | Preview del staff |
| `thread_pj_state.php` | Estado PV/PE en hilos |
| `cards_play.php` | Tiradas de cartas en posts |
| `inventory_get.php` | Calcular capacidad de carga |
| `character_competencias_get.php` | Requisitos de competencias |
| `acquire_competencia.php` | Validar adquisición de competencias |
| `ProcessPostCards.php` | Procesar cartas jugadas en posts |

---

## 10. Visualización y CSS

### 10.1 Clases CSS por Rango

```php
public static function rankDisplayCssClass(int $rangoEfectivo): string
{
    if ($rangoEfectivo <= 0)               return 'rpg-stat-rank--none';
    if ($rangoEfectivo <= 6)
        return 'rpg-stat-rank--' . strtolower(self::RANK_NAMES[$rangoEfectivo] ?? 'd');
    if ($rangoEfectivo === 7)              return 'rpg-stat-rank--ss-plus';
    if ($rangoEfectivo === 8)              return 'rpg-stat-rank--ss-plus-plus';
    return 'rpg-stat-rank--ss-beyond';
}
```

### 10.2 Render de Barras de Stats

Cada stat se renderiza en `_sidebar.php` con 6 segmentos (D→SS), llenos hasta el rango entrenado. El label muestra el rango EFECTIVO:

```html
<div class="rpg-pj-stat-row rpg-pj-stat-row--rank<?= $hasRacial ? ' rpg-pj-stat-row--racial' : '' ?>">
    <div class="rpg-pj-stat-label">
        <span><i class="fas fa-dumbbell"></i> FUERZA</span>
        <span class="rpg-stat-rank rpg-stat-rank--a">A</span>
    </div>
    <div class="rpg-stat-rank-track">
        <span class="rpg-stat-rank-segment rpg-stat-rank-segment--filled rpg-stat-rank-segment--fue"></span>
        <span class="rpg-stat-rank-segment rpg-stat-rank-segment--filled rpg-stat-rank-segment--fue"></span>
        ...
    </div>
</div>
```

**Filosofía visual:**
- Los segmentos se llenan según el rango ENTRENADO, no el efectivo. Así ves tu progreso real.
- La fila tiene clase `--racial` si hay bono racial en ese stat, tiñéndola de un color especial.
- El label grande muestra el rango EFECTIVO. Así ves ambas cosas: tu progreso (barras) y tu capacidad real (label).

---

## 11. Integración con el Sistema de Personaje

### 11.1 Flujo de stats en creación de PJ

```
Wizard JS → sanitizeStats() → stats_json → data_json.rank
```

1. El wizard asigna 1 punto a distribuir entre los 7 stats (todos base 1, máximo 2 en creación).
2. El JS calcula preview con `RANK_VALUES` y bonos raciales locales.
3. Al enviar, `CharacterSaveService::buildPayloadForInsert()` sanitiza y calcula rango global.
4. Los stats se guardan en `stats_json` y el rango global en `data_json.rank`.

### 11.2 Flujo de compra de stat

```
purchase_attribute.php → CharacterProgression::applyStatUpgrade() → UPDATE stats_json
```

1. El jugador hace clic en "Subir rango" en el panel de gestión.
2. `purchase_attribute.php` verifica ownership, status, PP suficientes.
3. `applyStatUpgrade()` incrementa el stat, descuenta PP (linaje primero), recalcula rango global.
4. Se persiste `data_json` y `stats_json`.

### 11.3 Stats en Combate (Modificadores de Turno)

Los `$turnModifiers` permiten buffs/debuffs temporales:
- Una carta que da "+1 AGI por 3 turnos"
- Un entorno que penaliza "-1 AGI" (tormenta)
- Un buff de cocinero que da "+2 FUE por escena"

Estos modificadores NO persisten en la ficha, solo en `game_thread_pj_state.stat_mods_json`.

---

## 12. Límites y Casos Especiales

### 12.1 Límite de NPC (rango 7+)

```php
if ($efectivo <= 6) {
    return self::rangoAValor($efectivo);
}
return self::rangoAValor(6) + (($efectivo - 6) * 20);
```

**Consejo para staff:** Un NPC con stats 7+ debe ser un jefe narrativo importante. No abuses de rangos 7+ en NPCs menores. Cada punto extra de rango efectivo añade +20 al valor del stat, duplicando enormemente PV/PE.

### 12.2 Mínimo de Stat

El mínimo absoluto es 1 (D). El rango efectivo puede ser 0 o negativo si el bono racial es negativo y el stat entrenado es 1.

**Ejemplo:** Tontatta con FUE entrenada = 1, bono racial -2 → rango efectivo = -1 → label '—' → valor 0.

**Consejo para jugadores:** Si eliges una raza con penalizadores (Tontatta: FUE -2), necesitarás invertir PP extra en ese stat para compensar. No es inviable, pero tu personaje será muy débil en ese aspecto hasta que entrenes.

---

## 13. Ejemplos Prácticos

### 13.1 Personaje Nuevo (Pirata principiante)

```json
// stats_json
{ "fue": 2, "res": 1, "agi": 2, "des": 1, "int": 1, "inst": 1, "esp": 1 }
// Suma=9 → RG D. Como Humano, sin bonos.
// PV=56, PE=44
// Coste subir cualquier stat 1→2: 50 PP (RG D, sin multiplier)
```

> **Lectura:** Este personaje es rápido y fuerte para ser novato (FUE 2, AGI 2) pero frágil (RES 1). Ideal para un pirata que golpea primero y espera no recibir golpes.

### 13.2 Personaje Intermedio (Marine oficial Gyojin)

```json
{ "fue": 3, "res": 3, "agi": 3, "des": 2, "int": 2, "inst": 2, "esp": 2 }
// Suma=17 → RG B. Bonos: FUE+1, RES+1, AGI-1
// Efectivos: FUE=4(A), RES=4(A), AGI=2(C)...
// PV=227, PE=88
// Coste subir FUE de 3→4: 350 × 1.15 = 403 PP
// Total PP gastados: 740
```

> **Lectura:** Un marine sólido. La penalización AGI no le duele porque usa armadura. Su PV alto (227) lo hace durable en primera línea. Su PE bajo (88) significa que no puede mantener técnicas caras por mucho tiempo.

### 13.3 Personaje Avanzado (Capitán Mink)

```json
{ "fue": 4, "res": 3, "agi": 4, "des": 3, "int": 3, "inst": 4, "esp": 4 }
// Suma=25 → RG A. Bonos: AGI+1, DES+1, INST+1
// Efectivos: FUE=4(A), AGI=5(S), INST=5(S)...
// PV=276, PE=302
// Total PP gastados: 2660
```

> **Lectura:** Un capitán equilibrado y peligroso. Su PE alto (302) le permite usar técnicas de Haki y cartas avanzadas. Su PV (276) es respetable. Es un personaje que puede liderar y pelear.

### 13.4 Límite de PJ (SS, todos stats 6)

```json
{ "fue": 6, "res": 6, "agi": 6, "des": 6, "int": 6, "inst": 6, "esp": 6 }
// Suma=42 → RG SS
// PV=900, PE=900
// Total PP: 21,910
```

> **Consejo:** Nadie llega aquí antes de 1-2 años de juego consistente. Este personaje es un monstruo. Como staff, si alguien se acerca a SS, ya deberías estar planeando su "retiro" narrativo o su transformación en NPC legendario.

---

## 14. Migraciones Históricas

### 14.1 migrate_stats_v7.php

**Contexto:** El sistema anterior tenía stats con valores 1-20 sin normalizar. La v7 comprimió todo a 1-6 con la función de conversión:

```php
$conversion = static function (int $v): int {
    $v = max(1, min(20, $v));
    return match (true) {
        $v <= 3  => 1,   // D
        $v <= 6  => 2,   // C
        $v <= 10 => 3,   // B
        $v <= 14 => 4,   // A
        $v <= 18 => 5,   // S
        default  => 6,   // SS
    };
};
```

**Qué cambió:**
- `str` (strength) → `fue`
- `vol` (voluntad) → se dividió en `inst` y `esp`
- `res` y `des` estaban unificados en legacy → se separaron (con advertencia si el valor legacy era ambiguo)
- Se otorgó refund de PP si el nuevo sistema era más barato que el anterior.

**Filosofía de la migración:** No dejar a nadie atrás. Todos los personajes existentes recibieron una conversión justa y PP de compensación si perdían poder.

---

## 15. Filosofía de Diseño

### 15.1 Principios Rectores del Sistema de Stats

1. **Cada punto importa.** Con solo 6 rangos, cada subida de stat es un hito. No hay ruido de números.

2. **La especialización tiene coste.** Un personque solo sube FUE tendrá un rango global bajo y pagará menos por subir FUE... pero nunca será SS porque necesita todos los stats. El sistema premia el balance.

3. **La raza importa pero no determina.** Un personaje puede compensar sus debilidades raciales invirtiendo PP. Un Tontatta puede tener FUE 4 si entrena suficiente — pero le costará más que a un Gigante.

4. **PV y PE son asimétricos intencionalmente.** No hay personajes "tanque-mago" universales. Cada build tiene fortalezas y debilidades claras.

5. **El rango global es un resumen, no una cárcel.** Dos personajes rango A pueden ser completamente distintos: uno tanque (FUE/RES altos) y otro técnico (DES/INT altos).

### 15.2 ¿Qué problemas resuelve este diseño?

| Problema común en RPGs | Cómo lo resuelve este sistema |
|------------------------|-------------------------------|
| "Los stats no se sienten diferentes" | 7 stats con roles muy diferenciados y fórmulas asimétricas |
| "Subir de nivel no se siente especial" | Solo 6 rangos, cada subida es un evento |
| "Min-maxing extremo" | Rango global multiplier encarece la especialización |
| "Las razas son solo estética" | Bonos raciales asimétricos generan arquetipos distintos |
| "Los números grandes pierden significado" | Escala comprimida 1-6, cada número importa |

### 15.3 Filosofía de los Costes de PP

```
50 → 130 → 350 → 800 → 1800
```

Esta secuencia sigue una progresión intencional:
- **×2.6, ×2.7, ×2.3, ×2.25** — los factores decrecen ligeramente, haciendo que los rangos más altos no sean tan prohibitivos como parecería con una curva puramente exponencial.
- El coste acumulado para tener un stat en 6 es 3130 PP. Para tener los 7 en 6: 21,910 PP.
- A 100 palabras de rol por PP (100 palabras = 1 PP), un post de 500 palabras da ~5 PP. Para llegar a 21,910 PP se necesitan ~4,382 posts de 500 palabras. A 2 posts por semana, son ~42 años de juego. **Nadie llega al máximo.** El sistema está diseñado para que siempre haya una meta lejana.

---

## 16. Consejos para Jugadores

### 16.1 ¿Qué stat me conviene según mi estilo?

| Quiero ser... | Stat principal | Stat secundario | Stat a ignorar |
|--------------|:-------------:|:---------------:|:--------------:|
| Tanque / Guerrero | RES | FUE | INT |
| DPS cuerpo a cuerpo | FUE | AGI | INT |
| Espadachín | DES | FUE | ESP |
| Tirador / Francotirador | DES | INST | FUE |
| Usuario de Haki | ESP | INST | FUE |
| Médico | INT | DES | FUE |
| Capitán / Líder | ESP | FUE | — (necesitas balance) |
| Navegante | INT | INST | FUE |
| Científico | INT | DES | AGI |

### 16.2 Estrategias de Distribución

**Estrategia "Generalista" (recomendada para nuevos):**
Sube todos los stats a 2 primero. Cuesta solo 7×50 = 350 PP (con RG D) y te da rango C automáticamente. Luego especialízate.

**Estrategia "Especialista" (para builds concretos):**
Lleva tu stat principal a 3 o 4 antes de tocar los demás. Serás muy bueno en algo pero frágil y con rango global bajo.

**Estrategia "RG Rush" (para veteranos):**
Sube los stats más baratos (los que tengas más bajos) para aumentar tu rango global. Aunque los stats bajos no te den mucho poder, subir el RG desbloquea multiplicadores de PV/PE y mejoras narrativas.

### 16.3 Lo que NO te dice el sistema

- **AGI es infravalorada por nuevos.** Aunque su peso en PV/PE es bajo, AGI determina iniciativa y evasión en combate. Un personaje con AGI alta evita daño que RES nunca podría absorber.
- **ESP es la inversión a largo plazo.** Sin ESP, no puedes usar Haki avanzado ni técnicas caras. Si planeas ser usuario de Haki, empieza a subir ESP desde el principio.
- **INT no es inútil para guerreros.** Un guerrero con INT decente puede desarrollar tácticas, identificar debilidades enemigas y usar su conocimiento del mundo para ventaja narrativa.
- **Los bonos raciales negativos se pueden compensar.** Si tu raza penaliza FUE, no estás condenado. Sube FUE a 3 y tendrás FUE efectiva 1 (con Tontatta: 3-2=1), que es como un humano sin entrenar.

### 16.4 La Regla de Oro

**No subas stats porque "toca".** Sube stats porque tu personaje, en su historia, estaría entrenando eso. El sistema de PP recompensa el roleo, no el grinding. Si tu personaje es un cocinero que nunca pelea, tiene sentido tener FUE 1.

---

## 17. Consejos para Staff

### 17.1 Balanceando encuentros con stats

| Rango Global del PJ | Dificultad recomendada de encuentro |
|:-------------------:|-------------------------------------|
| D | Civil, bandido, animal pequeño. PV < 50. |
| C | Marino novato, pirata menor. PV 50-100. |
| B | Soldado experimentado, oficial bajo. PV 100-200. |
| A | Vicealmirante, capitán pirata fuerte. PV 200-400. |
| S | Miembro de Almirante/Yonko. PV 400-700. |
| SS | Jefe de arco legendario. PV 700+. |

### 17.2 Qué vigilar

- **Min-maxers extremos:** Un personaje con FUE 6 y todo lo demás en 1. Técnicamente posible, pero narrativamente extraño y mecánicamente roto (mucho daño, 0 PV). Si ves un stats_json muy disparejo (ej: 6/1/1/1/1/1/1), revisa si tiene sentido narrativo.
- **Acumulación de PP sin gastar:** Un jugador con 3000+ PP guardados y stats en 1. No hay regla que lo prohíba, pero es señal de que el jugador está acumulando para un "dump" masivo. Puedes sugerirle que gaste o limitar compras masivas.
- **Subidas de stat sin justificación narrativa:** El sistema no bloquea subir FUE de 5 a 6 después de un post de cocina. Como staff, puedes pedir que haya coherencia: "¿cómo entrenó esto?"

### 17.3 Errores Comunes al Interpretar Stats

- **"Rango S es el segundo mejor, así que un PJ rank S debería poder con casi todo."** No exactamente. Rango S significa que la SUMA de sus stats está entre 29 y 36. Puede tener FUE 6 y RES 1. Es fuerte pero frágil.
- **"Un NPC con FUE 7 es solo un poco más fuerte que un PJ con FUE 6."** FALSO. FUE 7 = valor 80, FUE 6 = valor 60. Es 33% más fuerte. La diferencia entre rangos altos es enorme.
- **"Los bonos raciales se acumulan en el stats_json."** NO. Los bonos raciales son runtime, no persisten. stats_json siempre guarda rangos entrenados (1-6).

### 17.4 Cómo usar el sistema narrativamente

- Un personaje sube de rango global → es buen momento para un evento narrativo (ceremonia marina, reconocimiento pirata).
- Un personaje sube FUE de 3 a 4 → puede partir rocas con golpes. Menciona su nueva fuerza en el siguiente combate.
- Un personaje con ESP 6 tiene presencia arrolladora. La gente lo nota al entrar en una habitación.

---

## 18. Referencia Rápida

### 18.1 Fórmulas Esenciales

```
Valor numérico: 1→4, 2→8, 3→15, 4→26, 5→40, 6→60
  >6 → 60 + (efectivo - 6) × 20

Rango efectivo = entrenado + racial + modificadores

PV = ((res_val×4)+(fue_val×3)+(esp_val×2)+(agi_val×1)) × mult_RG
PE = ((esp_val×4)+(des_val×3)+(int_val×2)+(agi_val×1)) × mult_RG

RG desde suma entrenados:
  ≤10 D, ≤16 C, ≤22 B, ≤28 A, ≤36 S, >36 SS

Coste = RANK_UPGRADE_COST[rango] × RANK_GLOBAL_MULTIPLIERS[RG]
  Costes base: 50→130→350→800→1800
  Mult RG: D=1.00, C=1.07, B=1.15, A=1.35, S=1.60, SS=2.00

PV/PE Mult: D=1.00, C=1.05, B=1.10, A=1.20, S=1.35, SS=1.50
```

### 18.2 Archivos Clave

| Archivo | Propósito |
|---------|-----------|
| `game/src/Shared/StatScale.php` | Constantes, conversiones, cálculos |
| `game/inc/stat_helpers.php` | `game_build_stat_context()`, `game_compute_pv_pe_from_context()` |
| `game/data/linaje_catalog.json` | Bonificaciones raciales por raza |
| `game/ajax/purchase_attribute.php` | Endpoint de compra de stats |
| `game/src/Application/Services/CharacterProgression.php` | Validación y aplicación de compras |
| `game/sql/migrate_stats_v7.php` | Migración histórica a stats v7 |

---

*Fin del documento — Guía completa del Sistema de Stats v2.0*
*Generado desde: `Guias/sistemas/02-stats.md`*
*Referencia: `Guias/MAESTRO_SISTEMAS_RPG.md` — Sección 2*
