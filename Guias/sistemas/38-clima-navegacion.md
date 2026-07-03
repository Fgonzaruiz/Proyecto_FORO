# 30. Clima y Fenómenos — Ampliación de Navegación

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 30
> **Propósito:** Documentar exhaustivamente el subsistema de clima como capa narrativa y mecánica sobre los Oráculos de Navegación. Sin tiradas extra, el clima emerge de los eventos de viaje. Incluye efectos por zona, mitigación del navegante, penalizaciones en combate naval, tablas de oráculos climáticos expandidas, integración con migraciones SQL, filosofía de diseño y consejos para jugadores y staff.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [El Clima como Capa sobre los Oráculos](#2-el-clima-como-capa-sobre-los-oráculos)
3. [Zonas Climáticas del Mundo](#3-zonas-climáticas-del-mundo)
4. [Oráculos Climáticos — Tablas Completas](#4-oráculos-climáticos)
5. [Efectos Mecánicos en Navegación](#5-efectos-mecánicos-en-navegación)
6. [Efectos Mecánicos en Combate Naval](#6-efectos-mecánicos-en-combate-naval)
7. [Mitigación del Navegante](#7-mitigación-del-navegante)
8. [Integración con Instrumentos de Navegación](#8-integración-con-instrumentos)
9. [Fenómenos Especiales por Zona](#9-fenómenos-especiales-por-zona)
10. [Migraciones SQL y Modelo de Datos](#10-migraciones-sql)
11. [Flujo de Datos — Clima en el Viaje](#11-flujo-de-datos)
12. [Filosofía de Diseño](#12-filosofía-de-diseño)
13. [Consejos para Jugadores](#13-consejos-para-jugadores)
14. [Consejos para Staff](#14-consejos-para-staff)
15. [Guía de Troubleshooting](#15-guía-de-troubleshooting)

---

## 1. Arquitectura General

### 1.1 Capas del Subsistema Climático

```
┌──────────────────────────────────────────────────────────────────────────┐
│                     SISTEMA DE NAVEGACIÓN (base)                         │
│  game_navigation_voyages · game_navigation_events · game_navigation_routes│
└────────────────────────────────┬─────────────────────────────────────────┘
                                 │ El clima emerge desde aquí
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                     CAPA CLIMÁTICA (este documento)                       │
│                                                                          │
│  No hay tablas nuevas. El clima se resuelve mediante:                    │
│    ┌──────────────────────────────────────────────────────────┐          │
│    │  game_oracles (subtype = nav_1_2, nav_3, nav_4_5)        │          │
│    │  + variations_json por categoría/isla                     │          │
│    │  + auto_invoke para fenómenos encadenados                 │          │
│    └──────────────────────────────────────────────────────────┘          │
│                                                                          │
│  La severidad del clima se determina por:                                │
│    danger_level del viaje + sea_zone + mitigación del navegante          │
└──────────────────────────────────────────────────────────────────────────┘
```

### 1.2 Principio Fundamental

**El clima no es un sistema aparte.** No hay tiradas de "clima" y tiradas de "eventos". El clima **es** el resultado del oráculo de navegación. Cuando un oráculo devuelve "Tormenta menor / Mar picado (Severo)", ese es el clima del evento. No se tira nada adicional.

Esto se refleja en el diseño de los oráculos:

- `nav_1_2` (Blues): Resultados predominantemente climáticos (viento, lluvia, tormenta, calma).
- `nav_3` (Grand Line): Clima caótico mezclado con fenómenos extraordinarios.
- `nav_4_5` (New World): Clima extremo indistinguible de catástrofes sobrenaturales.

### 1.3 ¿Por qué el clima no requiere tiradas separadas?

| Razón | Explicación |
|-------|-------------|
| **Carga cognitiva** | Si cada evento requiriera "tirar clima" + "tirar evento", el sistema duplicaría la complejidad |
| **Fricción narrativa** | El jugador recibiría "Tormenta: sí" y luego "Evento: ataque de Kraken" como dos cosas separadas cuando deberían ser una sola experiencia |
| **Principio de economía de dados** | Cada tirada diluye el impacto de las anteriores. Un solo oráculo por evento produce resultados más memorables |
| **Coherencia diegética** | En el mundo real, el clima ES el evento cuando navegas. Separarlo sería antinatural |

### 1.4 Impacto de esta decisión en el diseño de oráculos

Los oráculos de navegación (`nav_*`) no son tablas de "encuentros" con clima añadido. Son **tablas climáticas** que **pueden** derivar en encuentros mediante auto-invocación:

```
Resultado del oráculo (clima)
    │
    ├── Clima puro: "Viento a favor" → efecto directo en navegación
    │
    └── Clima + encuentro: "Sombra bajo el agua"
         └── auto_invoke → nav_resolve_beast (criatura marina)
```

Esto asegura que el clima es siempre el **primer filtro** y los encuentros son **consecuencia** del clima o las condiciones, no al revés.

---

## 2. El Clima como Capa sobre los Oráculos

### 2.1 Mapeo Zona → Subtipo de Oráculo

| Zona | Peligro | Subtipo de oráculo | Dado | Resultados climáticos puros |
|------|---------|-------------------|:----:|:--------------------------:|
| **East Blue** | 1 | `nav_1` | d12 | 100% |
| **West/North/South Blue** | 1–2 | `nav_1_2` | d20 | 80% |
| **Cualquier Blue** | 2 | `nav_2` | d20 | 60% (resto: incidentes) |
| **Grand Line** | 3 | `nav_3` | d20 | 100% (clima caótico) |
| **New World (alto)** | 4 | `nav_4` | d20 | 50% (resto: amenazas) |
| **New World (extremo)** | 5 | `nav_5` | d12 | 50% (resto: letales) |
| **New World (general)** | 4–5 | `nav_4_5` | d20 | 100% (clima hostil) |

### 2.2 Cómo el Sistema Selecciona el Oráculo Climático

En `navigation_process.php`, la función `game_nav_get_oracles_for_danger()` selecciona los oráculos cuyo `subtype` coincide con el nivel de peligro:

```php
// navigation_helpers.php:170-206 (simplificado)
function game_nav_get_oracles_for_danger(int $danger): array
{
    // Busca oráculos con tags_json LIKE '%navegacion%'
    // y subtype LIKE 'nav_%'
    //
    // Matching por peligro:
    //   danger=1 → nav_1, nav_1_2
    //   danger=2 → nav_2, nav_1_2
    //   danger=3 → nav_3
    //   danger=4 → nav_4, nav_4_5
    //   danger=5 → nav_5, nav_4_5
}
```

### 2.3 Variaciones Climáticas por Isla (variations_json)

Un mismo oráculo climático puede producir resultados diferentes según la isla/categoría del post. Por ejemplo, el oráculo `nav_1_2` (Blues) puede tener variaciones para islas específicas:

```json
{
  "Arabasta": [
    {"range": "1-5", "result": "Viento del desierto", "description": "Arena fina arrastrada desde tierra firme."},
    {"range": "6-10", "result": "Calima", "description": "El horizonte se difumina."}
  ],
  "Drum": [
    {"range": "1-5", "result": "Ventisca ligera", "description": "Nieve fina, mar en calma."},
    {"range": "6-10", "result": "Banquisa", "description": "Bloques de hielo flotante."}
  ]
}
```

Esto se procesa en `game_roll_oracle()`: si hay una variación para la categoría del post, reemplaza completamente los resultados base.

### 2.4 Clima y Auto-Invocación

Cuando un resultado climático alcanza un umbral de severidad, puede auto-invocar un oráculo de resolución:

| Resultado climático | Severidad | Auto-invoca |
|--------------------|:---------:|-------------|
| "Sombra bajo el agua" (nav_2, rango 20) | Alta | `nav_resolve_beast` — ¿criatura ataca? |
| "Emboscada leve" (nav_2, rango 18-19) | Media | `nav_resolve_naval` — ¿encuentro hostil? |
| "Flota corsaria" (nav_4, rango 13-15) | Alta | `nav_resolve_naval` — combate naval |
| "Kraken menor" (nav_4, rango 19-20) | Alta | `nav_resolve_beast` — ataque de bestia |
| "Tormenta brutal" (nav_1_2, rango alto) | Alta | `nav_resolve_beast` — criatura atraída |

El sistema de auto-invocación está implementado en `navigation_process.php:169-192`:

```php
function game_navigation_maybe_invoke_chain(
    int $postId,
    int $characterId,
    array $rollResult,
    string $category,
    int $parentPostOracleId
): void {
    $autoInvoke = $rollResult['auto_invoke'] ?? null;
    if (!$autoInvoke || empty($autoInvoke['oracle_id'])) {
        return;
    }
    // Carga el oráculo destino, lo tira y registra el resultado
}
```

---

## 3. Zonas Climáticas del Mundo

### 3.1 Tabla de Zonas y Clima

| Zona | Slug | Peligro | Clima Predominante | Patrón | Impredictibilidad |
|------|------|:-------:|-------------------|--------|:-----------------:|
| East Blue | `east_blue` | 1–2 | Templado, brisas alisias, lluvias estacionales | Estacional predecible | Baja |
| West Blue | `west_blue` | 1–2 | Oceánico, tormentas menores, niebla matinal | Estacional predecible | Baja |
| North Blue | `north_blue` | 2–3 | Frío, nevadas invernales, corrientes gélidas | Estacional con variaciones | Media |
| South Blue | `south_blue` | 2–3 | Tropical extremo, huracanes, monzones | Estacional extremo | Media |
| Grand Line | `grand_line` | 3 | Caótico absoluto: nieve en verano, sol en invierno | Sin patrón | Muy alta |
| New World | `new_world` | 4–5 | Hostil activo: mares de lava, tormentas permanentes | Hostil constante | Extrema |
| Calm Belt | `calm_belt` | 5 | Calma total, sin viento ni oleaje | Invariable | N/A (siempre igual) |
| Triángulo de Florian | `florian_triangle` | 5 | Niebla perpetua, visibilidad cero, fenómenos sobrenaturales | Niebla constante | Alta (contenido) |

### 3.2 Determinación de la Zona Climática del Viaje

La zona climática se determina en `navigation_process.php` según el peligro:

```php
$seaZone = $danger >= 3
    ? ($islandTo['sea_zone'] ?? 'grand_line')
    : ($islandFrom['sea_zone'] ?? 'east_blue');
```

**Regla:**
- Viajes de **peligro 1–2**: usan la zona de la isla de **origen** (el viaje permanece en aguas conocidas).
- Viajes de **peligro 3+**: usan la zona de la isla de **destino** (el viaje cruza a mares más hostiles).

### 3.3 Perfiles Climáticos Detallados por Zona

#### 3.3.1 Blues (East, West, North, South) — Peligro 1–2

```
┌────────────────────────────────────────────────────────────┐
│  BLUES — Clima Predecible y Natural                        │
│                                                            │
│  Temperatura: 15°C–35°C (según latitud y estación)        │
│  Viento:      Brisas de 5–20 nudos, direcciones estables   │
│  Precipitación: Estacional, ciclos predecibles             │
│  Visibilidad: Generalmente buena, niebla ocasional         │
│                                                            │
│  Fenómenos típicos:                                        │
│    • Viento a favor / Mar calmado                          │
│    • Lluvia moderada                                       │
│    • Neblina matinal                                       │
│    • Tormenta menor                                        │
│    • Mar encalmado total (poco común)                      │
└────────────────────────────────────────────────────────────┘
```

**East Blue:** El más seguro. Clima mediterráneo/tropical. Las tormentas son predecibles y rara vez mortales. Un barco bien mantenido con un mínimo de preparación puede navegar sin problemas.

**West Blue:** Similar al East. Ligeramente más húmedo. Neblina frecuente en ciertas rutas.

**North Blue:** Frío en invierno (hasta –10°C en latitudes altas). Tormentas de nieve ocasionales. Corrientes frías que pueden llevar hielo flotante.

**South Blue:** Tropical extremo. Temporada de huracanes definida (3–4 meses al año). Marejadas ciclónicas. Lluvias torrenciales.

#### 3.3.2 Grand Line — Peligro 3

```
┌────────────────────────────────────────────────────────────┐
│  GRAND LINE — Clima Caótico e Impredecible                 │
│                                                            │
│  Temperatura:  –5°C a 50°C (cambia en minutos)            │
│  Viento:      0–80 nudos, dirección aleatoria cambiante    │
│  Precipitación: Sin patrón, cualquier cosa en cualquier    │
│                momento                                      │
│  Visibilidad: Variable extrema (0–20 km en minutos)        │
│                                                            │
│  Fenómenos típicos:                                        │
│    • Nieve en verano                                       │
│    • Lluvia cálida (agua a 40°C)                           │
│    • Rayos sin nubes                                       │
│    • Calor extremo súbito                                  │
│    • Tornado súbito                                        │
│    • Mar de nubes (nubes a nivel del mar)                  │
│    • Lluvia de meteoritos pequeños                         │
│    • Erupción submarina                                    │
└────────────────────────────────────────────────────────────┘
```

La Grand Line no sigue las leyes de la meteorología convencional. Las masas de aire de diferentes climas chocan sin mezclarse, creando fronteras climáticas abruptas. Un barco puede pasar de un calor abrasador a una ventisca en cuestión de metros.

**El Log Pose es obligatorio** no solo por la orientación magnética, sino porque el clima cambia tan rápido que ningún otro instrumento puede seguir el ritmo.

#### 3.3.3 New World — Peligro 4–5

```
┌────────────────────────────────────────────────────────────┐
│  NEW WORLD — Clima Hostil y Activo                         │
│                                                            │
│  Temperatura:  –20°C a 80°C (extremos frecuentes)         │
│  Viento:      0–120 nudos, con ráfagas destructivas        │
│  Precipitación: Agua, fuego, lava, hielo, ácido...        │
│  Visibilidad: Frecuentemente cero (tormenta, ceniza,       │
│               niebla mágica)                                │
│                                                            │
│  Fenómenos típicos:                                        │
│    • Mar de lava (agua hirviendo por actividad volcánica)  │
│    • Lluvia de fuego / ceniza ardiente                     │
│    • Tornado de hielo                                      │
│    • Tormenta eléctrica rastreadora                        │
│    • Isla de fuego flotante                                │
│    • Ballena de tormenta                                   │
│    • Vórtice gigante                                       │
│    • Niebla desorientadora magnética                       │
└────────────────────────────────────────────────────────────┘
```

En el New World, el clima es un **enemigo activo**. No es simplemente mal tiempo: es un fenómeno con intencionalidad aparente. Las tormentas "persiguen" barcos. Los tornados de hielo aparecen exactamente donde estás. La lluvia de lava cae solo sobre tu cubierta.

**Sobrevivir al clima del New World requiere:** barco resistente, navegante experto (grado III+), instrumentos adecuados, y una buena dosis de suerte.

### 3.4 Zonas Especiales

#### 3.4.1 Calm Belt — La Calma Mortal

El Calm Belt es único: **nunca hay viento**. Los barcos de vela quedan completamente inmóviles. Además, es el hábitat principal de los Reyes del Mar.

**Efectos climáticos en Calm Belt:**
- Viento: **0 nudos** siempre. Sin vela posible.
- Oleaje: Casi nulo. Mar como un espejo.
- Temperatura: Cálida y constante (zona ecuatorial).
- Visibilidad: Buena (sin nubes, sin niebla).
- **Peligro:** 5 (no por el clima, sino por los Reyes del Mar).

**Navegar el Calm Belt requiere:** Un sistema de propulsión alternativo (remos, paddle wheels, fruta del diablo, Marines con sus barcos con remos gigantes).

#### 3.4.2 Triángulo de Florian — Niebla Eterna

El Triángulo de Florian está envuelto en una niebla perpetuamente densa que nunca se disipa.

**Efectos climáticos en el Triángulo de Florian:**
- Visibilidad: **0–5 metros** permanente.
- Viento: Leve y errático.
- Temperatura: Fría y húmeda constante.
- Fenómeno especial: Barcos fantasma, alucinaciones colectivas, sonidos sin fuente.
- **Peligro:** 5 (desorientación, terror psicológico, embarcaciones fantasma).

**Navegar el Triángulo de Florian requiere:** Una fuente de luz excepcional, un navegante de grado IV+, o un Eternal Pose de salida.

---

## 4. Oráculos Climáticos

### 4.1 Estructura de un Oráculo Climático

Cada oráculo climático sigue el formato estándar de `game_oracles` con `results_json`:

```json
[
  {
    "range": "1-5",
    "result": "Viento a favor / Mar calmado (Favorable)",
    "description": "Condiciones óptimas para navegar. Otorga un bonus narrativo de navegación.",
    "auto_invoke": null
  }
]
```

La severidad se clasifica internamente por el rango:

| Categoría | Rango típico (d20) | Efecto narrativo |
|-----------|:------------------:|------------------|
| **Favorable** | 1–5 | Ayuda al viajero. Bonus de velocidad o ventaja. |
| **Moderado** | 6–10 | Molestia leve. Penalizaciones menores. |
| **Severo** | 11–15 | Problema serio. Dificultad para acciones. |
| **Extremo** | 16–19 | Crisis. Daño potencial al barco o la tripulación. |
| **Singular** | 20 | Único y catastrófico. Evento memorable. |

### 4.2 `nav_1_2` — Blues (Peligro 1–2, d20)

**Nombre en sistema:** `Evento de Navegación — Mar Tranquilo`
**Tags:** `["navegacion","basico"]`
**Dado:** d20

| Rango | Resultado | Descripción | Efecto mecánico |
|:-----:|-----------|-------------|:---------------:|
| 1–5 | Viento a favor / Mar calmado (Favorable) | Condiciones óptimas para navegar. Otorga un bonus narrativo de navegación (+ velocidad o ventaja). | Velocidad +10% |
| 6–10 | Lluvia moderada / Neblina (Moderado) | Reduce levemente la visibilidad o hace resbaladiza la cubierta. Ligeras penalizaciones a tiradas. | −1 a tiradas AGI/DES |
| 11–15 | Tormenta menor / Mar picado (Severo) | Vientos fuertes y olas que sacuden la nave. Dificultad para moverse en cubierta. | −2 AGI/DES · +1 día posible |
| 16–19 | Mar encalmado total (Extremo) | Cero viento; el barco no avanza si depende de velas. Aumenta considerablemente la duración del viaje. | +2–3 días duración |
| 20 | Corriente desfavorable fuerte (Singular) | Una corriente empuja el barco en dirección contraria. Atraso significativo o desvío de ruta. | +2 días · desvío de rumbo |

**Frecuencia de resultados en los Blues:**
- **Favorable:** 25% (días de navegación ideales)
- **Moderado:** 25% (condiciones normales)
- **Severo:** 25% (mal tiempo, pero manejable)
- **Extremo:** 20% (raro en Blues)
- **Singular:** 5% (muy raro)

### 4.3 `nav_3` — Grand Line (Peligro 3, d20)

**Nombre en sistema:** `Evento de Navegación — Grand Line`
**Tags:** `["navegacion","grand_line"]`
**Dado:** d20

| Rango | Resultado | Descripción | Efecto mecánico |
|:-----:|-----------|-------------|:---------------:|
| 1–5 | Corriente inversa favorable (Favorable) | Corrientes salvajes que milagrosamente empujan al destino. Acorta el tiempo del viaje. | Velocidad +20% · −1 día |
| 6–10 | Nieve en verano / Lluvia cálida (Moderado) | Alteración extrema de la temperatura en minutos. Confusión, necesidad de adaptar vestimenta. | −1 AGI/DES · penalización térmica |
| 11–15 | Rayos sin nubes / Calor extremo (Severo) | Descargas eléctricas o soles abrasadores súbitos. Riesgo leve; penalización a acciones físicas prolongadas. | −2 AGI/DES · daño leve al casco (1) |
| 16–19 | Tornado súbito / Mar de nubes (Extremo) | Fenómenos altamente destructivos que aparecen de la nada. Posible daño directo al barco si no se elude. | −3 AGI/DES · daño al casco (1d3) · +1 día |
| 20 | Lluvia de meteoritos / Erupción submarina (Singular) | Catástrofe natural súbita de gran escala. Daño casi seguro a la integridad del barco. | Daño al casco (1d6) · fuego a bordo posible · +2 días |

**Frecuencia de resultados en Grand Line:**
- **Favorable:** 25% (raras ventanas de buen clima)
- **Moderado:** 25% (lo "normal" en Grand Line)
- **Severo:** 25% (común)
- **Extremo:** 20% (frecuente)
- **Singular:** 5% (inesperado pero posible)

### 4.4 `nav_4_5` — New World (Peligro 4–5, d20)

**Nombre en sistema:** `Evento de Navegación — New World`
**Tags:** `["navegacion","new_world","extremo"]`
**Dado:** d20

| Rango | Resultado | Descripción | Efecto mecánico |
|:-----:|-----------|-------------|:---------------:|
| 1–5 | Ojo del huracán (Favorable) | Una perturbadora e inusual calma total en medio del caos. Respiro vital antes de que todo vuelva a enloquecer. | Velocidad +15% · recuperación |
| 6–10 | Niebla desorientadora / Lluvia constante (Moderado) | Niebla espesa magnética. El Log Pose gira erráticamente por unas horas. | Log Pose desorientado 1d4 horas · −1 AGI/DES |
| 11–15 | Mar de lava / Lluvia de fuego (Severo) | Ascuas del cielo o agua hirviendo. Casco dañado si no está recubierto; imposible pelear en cubierta. | −3 AGI/DES · daño al casco (1d4) · cards de rango bajo inhabilitadas |
| 16–19 | Tornado de hielo / Tormenta eléctrica rastreadora (Extremo) | Escarcha instantánea o rayos apuntan al barco. Inutilización de artillería, daño estructural grave al barco. | −4 AGI/DES · daño al casco (1d6+1) · artillería inhabilitada |
| 20 | Isla de fuego flotante / Ballena de tormenta / Vórtice gigante (Singular) | Eventos épicos y catastróficos. Amenaza de destrucción inminente. Resetea la aguja del Log Pose. | Log Pose reseteado · daño al casco (2d6) · +3 días · peligro +1 temporal |

**Frecuencia de resultados en New World:**
- **Favorable:** 25% (la calma antes de la tormenta... que ya está aquí)
- **Moderado:** 25% (condiciones "tranquilas" para NW)
- **Severo:** 25% (día normal en NW)
- **Extremo:** 20% (muy peligroso)
- **Singular:** 5% (evento de nivel legendario)

### 4.5 Oráculos Expandidos por Nivel de Peligro

La migración `migrate_navigation_oracles_expand.php` añade granularidad:

#### `nav_1` — East Blue (Peligro 1, d12)

| Rango | Resultado | Descripción |
|:-----:|-----------|-------------|
| 1–3 | Mar en calma | El horizonte está despejado. |
| 4–6 | Gaviotas de ruta | Aves marinas indican tierra cercana. |
| 7–9 | Corriente suave | Una corriente ayuda sin desviar el rumbo. |
| 10–11 | Pesca casual | La tripulación puede reponer provisiones. |
| 12 | Viento cambiante | El viento rota; el navegante debe ajustar velas. |

#### `nav_2` — Incidente en Ruta (Peligro 2, d20)

| Rango | Resultado | Descripción | Auto-invoke |
|:-----:|-----------|-------------|:-----------:|
| 1–4 | Lluvia persistente | Cubierta resbaladiza y visibilidad reducida. | — |
| 5–8 | Arrecife oculto | Hay que frenar y rodear con cuidado. | — |
| 9–12 | Barco pesquero | Pescadores comparten rumores del destino. | — |
| 13–15 | Viento en contra | La travesía se alarga un día. | — |
| 16–17 | Humo en el horizonte | Algo arde a lo lejos. ¿Piratas? ¿Incendio? | — |
| 18–19 | Emboscada leve | Un bote rápido intenta acercarse por la popa. | `nav_resolve_naval` |
| 20 | Sombra bajo el agua | Algo grande nada paralelo al barco. | `nav_resolve_beast` |

#### `nav_4` — Corsarios y Patrullas (Peligro 4, d20)

| Rango | Resultado | Descripción | Auto-invoke |
|:-----:|-----------|-------------|:-----------:|
| 1–4 | Señal de humo | Otra embarcación pide auxilio. Puede ser trampa. | — |
| 5–8 | Patrulla lejana | Una fragata avistada no cambia de rumbo… aún. | — |
| 9–12 | Mina flotante | Restos de guerra flotan en la ruta. | — |
| 13–15 | Flota corsaria | Dos bergantines bloquean parcialmente el paso. | `nav_resolve_naval` |
| 16–18 | Caza marina | La Marina intercepta y exige identificación. | — |
| 19–20 | Kraken menor | Tentáculos rozan el casco. | `nav_resolve_beast` |

#### `nav_5` — Abismo Extremo (Peligro 5, d12)

| Rango | Resultado | Descripción | Auto-invoke |
|:-----:|-----------|-------------|:-----------:|
| 1–2 | Anomalía temporal | El reloj de a bordo pierde horas sin explicación. | — |
| 3–4 | Lluvia de meteoritos | Impactos en cubierta; daños estructurales posibles. | — |
| 5–6 | Muro de tormenta | Un frente negro obliga a atravesar o rodear. | — |
| 7–8 | Territorio Yonko | Banderas hostiles en islas cercanas. | — |
| 9–10 | Kraken adulto | La bestia ataca sin vacilar. | `nav_resolve_beast` |
| 11–12 | Colisión inevitable | Otro coloso aparece de la niebla. | `nav_resolve_naval` |

### 4.6 Oráculos de Resolución Climática

Cuando un fenómeno climático deriva en un encuentro, se usan estos oráculos de resolución:

#### `nav_resolve_beast` — Criatura Marina (d6)

| Rango | Resultado | Descripción |
|:-----:|-----------|-------------|
| 1–2 | Solo avistamiento | La sombra se hunde sin atacar. |
| 3–4 | Golpe al casco | Sacudida fuerte. Revisar averías. |
| 5–6 | Ataque directo | La criatura ataca el barco con intención clara. |

#### `nav_resolve_naval` — Encuentro Naval (d6)

| Rango | Resultado | Descripción |
|:-----:|-----------|-------------|
| 1–2 | Huida limpia | El otro barco no alcanza a interceptaros. |
| 3–4 | Intercambio tenso | Palabras cruzadas a distancia. Nadie abre fuego… por ahora. |
| 5 | Escaramuza menor | Disparos de advertencia y maniobras bruscas. Daños leves posibles. |
| 6 | Abordaje | El combate cuerpo a cuerpo en cubierta es inevitable. |

### 4.7 Migración Clima (migrate_weather_oracles.php)

La migración `migrate_weather_oracles.php` actualiza los tres oráculos climáticos base con resultados mejorados:

```php
// migrate_weather_oracles.php
// UPDATE game_oracles SET results_json = ... WHERE subtype = 'nav_1_2'
// UPDATE game_oracles SET results_json = ... WHERE subtype = 'nav_3'
// UPDATE game_oracles SET results_json = ... WHERE subtype = 'nav_4_5'
```

Cada UPDATE reemplaza el `results_json` con descripciones más ricas y efectos mecánicos explícitos. La migración es **idempotente**: se puede ejecutar múltiples veces sin duplicar datos porque los oráculos se identifican por `subtype`.

**Orden de ejecución sugerido:**
1. `migrate_navigation_system.php` — Crea tablas base y siembra oráculos iniciales.
2. `migrate_navigation_oracles_expand.php` — Añade nav_1, nav_2, nav_4, nav_5 y resoluciones.
3. `migrate_weather_oracles.php` — Enriquece nav_1_2, nav_3, nav_4_5 con datos climáticos detallados.

---

## 5. Efectos Mecánicos en Navegación

### 5.1 Tabla de Efectos por Severidad Climática

| Severidad | Retraso Viaje | Incremento Peligro | Daño al Casco | Log Pose |
|-----------|:------------:|:------------------:|:--------------|:---------|
| **Favorable** | −1 día | −1 (mín 1) | — | — |
| **Moderado** | +0 días | +0 | — | — |
| **Severo** | +1 día | +1 | 1 punto | — |
| **Extremo** | +2–3 días | +1 | 1d3 puntos | Desorientación 1d4 horas |
| **Singular** | +2–4 días | +2 (máx 5) | 1d6 puntos | Reseteo completo |

### 5.2 Retraso en la Duración del Viaje

Cuando un evento climático de severidad **Severo o superior** ocurre, la duración base del viaje puede incrementarse. El sistema no modifica `duration_days` directamente (ese es el valor calculado original), sino que la información de retraso se almacena en el evento:

```php
// Lógica conceptual en game_navigation_generate_events()
function game_nav_apply_climate_delay(array $rollResult, int $currentDuration): int
{
    $severity = $rollResult['severity'] ?? 'moderate';
    return match ($severity) {
        'favorable' => max(1, $currentDuration - 1),
        'moderate'  => $currentDuration,
        'severe'    => $currentDuration + 1,
        'extreme'   => $currentDuration + rand(2, 3),
        'singular'  => $currentDuration + rand(2, 4),
        default     => $currentDuration,
    };
}
```

**En la práctica:** El staff, al revisar el viaje, ve una nota en el evento: "Tormenta severa: retraso estimado de +1 día". Si el personaje llegó al `expected_end_rol_days` pero hubo eventos severos, el staff puede considerar el retraso narrativo al aprobar.

### 5.3 Incremento del Peligro Base

Ciertos fenómenos climáticos incrementan temporalmente el nivel de peligro del viaje:

| Situación | Incremento |
|-----------|:----------:|
| Tormenta severa durante el viaje | +1 a peligro para eventos futuros |
| Clima extremo continuado | +1 acumulativo por evento extremo |
| Fenómeno singular | +2 directo (cap en 5) |
| Log Pose desorientado | +1 mientras dure la desorientación |

Esto crea un **ciclo de retroalimentación negativa**: un clima malo aumenta el peligro, y un peligro más alto genera eventos más severos:

```
Evento Severo → Peligro +1 → Siguiente evento más severo → ...
```

El navegante puede romper este ciclo (ver sección 7).

### 5.4 Daño Estructural al Barco

El clima extremo puede dañar el barco. El daño se registra conceptualmente como puntos de daño al casco:

| Evento | Daño | Notas |
|--------|:----:|-------|
| Tormenta severa (Blues) | 0–1 | Menor, apenas rasguños |
| Rayos sin nubes (GL) | 1 | Posible fuego en mástil |
| Tornado súbito (GL) | 1d3 | Daño estructural moderado |
| Mar de lava (NW) | 1d4 | Daño por calor, necesita reparación |
| Tornado de hielo (NW) | 1d6+1 | Daño grave, artillería afectada |
| Lluvia de meteoritos (NW) | 1d6 | Múltiples impactos |
| Isla de fuego flotante (NW) | 2d6 | Potencial destrucción total |

**Impacto del daño en el juego:**
- Daño acumulado: Penalización a la velocidad del barco (−1 por cada 3 puntos de daño).
- Daño ≥ 50% de la integridad del barco: El barco necesita reparaciones mayores antes del próximo viaje.
- Daño ≥ 75%: El barco se considera "averiado" y no puede navegar.

### 5.5 Desorientación del Log Pose

Ciertos fenómenos climáticos afectan al Log Pose:

| Fenómeno | Efecto en Log Pose |
|----------|-------------------|
| Niebla desorientadora (NW) | Gira erráticamente 1d4 horas |
| Tormenta eléctrica rastreadora (NW) | La aguja se sobrecarga magnéticamente |
| Singular: Vórtice gigante (NW) | Reseteo completo de la aguja |
| Anomalía temporal (nav_5) | El Log Pose muestra coordenadas incorrectas |

**Mientras el Log Pose está desorientado:**
- El barco no puede avanzar hacia el destino correcto (pérdida de tiempo).
- Penalización de −2 a velocidad efectiva (navegación a ciegas).
- Si se viaja sin Log Pose en Grand Line/New World y se desorienta, el viaje se considera "a la deriva" y debe ser revisado por staff.

### 5.6 Ejemplo de Viaje con Clima

**Ruta:** Shells Town (East Blue) → Orange Town (East Blue)
**Distancia:** 28 · **Peligro:** 2 · **Duración base:** 3 días
**Navegante:** Grado II · **Barco:** Goleta Merry (vel. base 5)
**Instrumento:** Brújula

**Eventos generados (2 eventos):**

```
Evento 1 — Oráculo nav_1_2, d20 → Rollo 14 → "Tormenta menor / Mar picado (Severo)"
  → Efecto: −2 AGI/DES, +1 día de retraso
  → Sin auto-invoke

Evento 2 — Oráculo nav_1_2, d20 → Rollo 3 → "Viento a favor / Mar calmado (Favorable)"
  → Efecto: Velocidad +10% (compensa parcialmente el retraso)
  → Sin auto-invoke
```

**Resultado final:**
- Duración real: ~3 días (2 base + 1 retraso − 0.5 por viento favorable, redondeado).
- El personaje experimentó: una tormenta menor que retardó el viaje, seguida de un viento favorable.
- Narrativa: "Salimos de Shells Town con buen tiempo, pero al segundo día nos azotó una tormenta que nos desvió. Por suerte, al amanecer un viento favorable nos puso de vuelta en rumbo."

---

## 6. Efectos Mecánicos en Combate Naval

### 6.1 Penalizaciones a Agilidad/Destreza

El movimiento brusco del barco por el clima afecta las capacidades de combate:

| Severidad climática | Penalización AGI/DES | Justificación narrativa |
|---------------------|:-------------------:|------------------------|
| **Favorable** | +1 (bonus) | Mar calmado, movimientos precisos |
| **Moderado** | 0 | Movimiento normal |
| **Severo** | −2 | Cubierta se balancea, difícil mantener el equilibrio |
| **Extremo** | −3 | Aferrarse para no caer por la borda |
| **Singular** | −4 | El barco es sacudido violentamente |

Estas penalizaciones aplican a:
- Tiradas de ataque cuerpo a cuerpo.
- Tiradas de puntería con armas a distancia.
- Esquivas y movimientos acrobáticos.
- Uso de técnicas que requieran equilibrio (patadas, posturas).

**No aplican a:**
- Tiradas de resistencia física (CON).
- Habilidades mentales o de percepción (INT, WIS).
- Poderes de Akuma no Mi que no requieran movimiento (ej: Logia de gas que se dispersa).

### 6.2 Inhabilitación de Cards por Clima

Ciertas cards no pueden usarse bajo condiciones climáticas específicas:

| Card / Tipo | Clima que la inhabilita | Razón |
|-------------|------------------------|-------|
| Cards de fuego | Tormenta severa, lluvia intensa | La pólvora/municiones se mojan |
| Cards de papel (origami) | Lluvia, niebla densa | El papel se empapa y pierde forma |
| Cards de humo/gas | Viento fuerte, tornado | El viento dispersa el gas |
| Cards de electricidad | Mar de lava, lluvia de fuego | El calor extremo interfiere |
| Cards de hielo/nieve | Mar de lava, calor extremo | Se derriten antes de impactar |
| Artillería pesada | Tornado, tormenta extrema | Imposible apuntar con precisión |
| Armamento cuerpo a cuerpo | Cubierta inclinada extrema | Sin equilibrio para el golpe |

**Detección:** El sistema no bloquea cards automáticamente (no hay un mapeo card→clima en la BD). Depende del staff y los jugadores respetar estas restricciones narrativas. El evento climático incluye en su `description` una nota: "Imposible usar fuego bajo esta tormenta."

### 6.3 Ventajas de Logia ante el Clima

Los usuarios de Akuma no Mi de tipo Logia tienen ventajas narrativas significativas ante el clima extremo:

| Logia | Ventaja climática |
|-------|------------------|
| Mera Mera (fuego) | Inmune a lluvia de fuego, mar de lava. Puede calentar la cubierta en tormentas de hielo. |
| Hie Hie (hielo) | Inmune a tornados de hielo, tormentas de nieve. Puede congelar olas peligrosas. |
| Goro Goro (rayo) | Inmune a tormentas eléctricas. Puede absorber rayos para recargar energía. |
| Suna Suna (arena) | Puede crear barreras contra viento. Inmune a tormentas de arena. |
| Yami Yami (oscuridad) | Puede absorber fenómenos climáticos (canónicamente, aniquila todo). |
| Gas Gas | Inmune a niebla tóxica, puede moverse en corrientes de aire. |

**Regla:** Las ventajas Logia son **narrativas**, no mecánicas. El jugador puede describir cómo su Logia interactúa con el clima, pero no hay un bonus de estadística automático. El staff puede conceder ventaja en tiradas si la interacción es creativa y coherente.

### 6.4 Cards y Habilidades Especiales contra el Clima

| Card / Habilidad | Efecto |
|-----------------|--------|
| **Revestimiento de brea** | Protege el casco contra mar de lava y lluvia de fuego (−2 daño) |
| **Velas reforzadas** | Resisten vientos extremos (ignora retraso por viento severo una vez) |
| **Estabilizadores giroscópicos** | Reduce penalización AGI/DES en 1 punto |
| **Sistema de drenaje** | Evita acumulación de agua en cubierta por lluvia intensa |
| **Pararrayos** | Canaliza rayos lejos del barco (evita daño eléctrico) |
| **Generador de niebla** | Crea cortina de niebla para evadir tormentas rastreadoras |

Estas cards son de tipo `equipo` o mejoras de barco (`card_type = 'barco'` con effects_json relevante).

---

## 7. Mitigación del Navegante

### 7.1 Visión General

El grado de Navegante (`oficio = 'navegante'`) mitiga los efectos del clima. La mitigación ocurre en `game_navigation_generate_events()` y depende del grado:

| Grado | Capacidad principal |
|:-----:|---------------------|
| I | Sin mitigación. Aprendiz. |
| II | Mitigación básica: reduce severidad un nivel. |
| III | Doble tirada + mitigación básica. |
| IV | Evasión táctica (evitar un evento severo por viaje) + mitigación mejorada. |
| V | Inmunidad moderados + mitigación suprema. |

### 7.2 Grado I — Sin Mitigación

El navegante novato reconoce los patrones básicos del clima pero no puede alterar el rumbo a tiempo.

```
Grado I (Novato):
  • Bonus de velocidad: +0.5
  • NO mitiga eventos climáticos
  • NO tiene doble tirada
```

**Narrativa:** "El cielo se oscurece y sabes que se acerca una tormenta, pero no estás seguro de si virar a babor o estribor. Decides seguir recto."

### 7.3 Grado II — Mitigación Básica

El navegante puede anticipar el clima y ajustar el rumbo para reducir el impacto.

```
Grado II (Aprendiz):
  • Bonus de velocidad: +1.0
  • Mitigación de severidad:
      Extremo (16-19) → Severo (11)
      Severo (11-15)  → Moderado (6)
      Moderado (6-10) → Favorable (1)
  • Singular (20): NO se mitiga (sigue siendo Singular)
```

```php
// Lógica en navigation_process.php (simplificada)
if ($navigatorRank >= 2) {
    $rollVal = (int)$rollResult['roll'];
    if ($rollVal >= 16 && $rollVal <= 19) {       // Extremo → Severo
        $newRoll = 11;
    } elseif ($rollVal >= 11 && $rollVal <= 15) {  // Severo → Moderado
        $newRoll = 6;
    } elseif ($rollVal >= 6 && $rollVal <= 10) {   // Moderado → Favorable
        $newRoll = 1;
    }
    // Re-buscar resultado para el nuevo roll
}
```

### 7.4 Grado III — Doble Tirada + Mitigación

El navegante competente lee el clima con tal precisión que puede elegir la mejor ruta entre dos opciones.

```
Grado III (Competente):
  • Bonus de velocidad: +1.5
  • Doble tirada: Tira dos veces, se queda con la mejor (roll más bajo)
  • Misma mitigación que Grado II
```

```php
if ($navigatorRank >= 3) {
    // Doble tirada
    $rollResult2 = game_roll_oracle($oracle, $category);
    if ($rollResult2['roll'] < $rollResult['roll']) {
        $rollResult = $rollResult2;
    }
}

// Luego aplica mitigación de Grado II
if ($navigatorRank >= 2) {
    // ... misma lógica de mitigación
}
```

**Estadísticamente:** La doble tirada reduce la probabilidad de resultados malos:
- Probabilidad de Extremo o Singular (16-20) con 1 tirada: 25%
- Probabilidad con 2 tiradas (tomando la mejor): ~6.25%
- Probabilidad de Favorable (1-5) con 1 tirada: 25%
- Probabilidad con 2 tiradas: ~43.75%

### 7.5 Grado IV — Evasión Táctica

El navegante experto puede, una vez por viaje, evitar completamente un evento climático severo.

```
Grado IV (Experto):
  • Bonus de velocidad: +2.0
  • Evasión única: Primer evento Severo (11-15) se evita completamente → Favorable (1)
  • Segundos eventos Severo: Mitigan a Moderado (6)
  • Extremo (16-19) → Severo (11)
  • Moderado (6-10) → Favorable (1)
```

```php
if ($navigatorRank >= 4) {
    static $avoidedSevero = false;

    if (!$avoidedSevero && $rollVal >= 11 && $rollVal <= 15) {
        // Evasión única: saltar directamente a Favorable
        $newRoll = 1;
        $avoidedSevero = true;
    } elseif ($rollVal >= 16 && $rollVal <= 19) {
        $newRoll = 11; // Extremo → Severo
    } elseif ($rollVal >= 11 && $rollVal <= 15) {
        $newRoll = 6;  // Severo → Moderado (si ya usó evasión)
    } elseif ($rollVal >= 6 && $rollVal <= 10) {
        $newRoll = 1;  // Moderado → Favorable
    }
}
```

### 7.6 Grado V — Maestro Navegante

El navegante de grado V es esencialmente inmune al clima moderado y reduce drásticamente los efectos extremos.

```
Grado V (Maestro):
  • Bonus de velocidad: +2.5
  • Inmunidad a Moderado: Todo Moderado (6-10) es automáticamente Favorable (1)
  • Extremo (16-19) → Moderado (6) (mejor que la mitigación de grados inferiores)
  • Severo (11-15): Primero evita (Favorable), segundos → Moderado
  • Singular (20): NO se mitiga directamente, pero el bonus de velocidad puede
    compensar los retrasos
```

```php
if ($navigatorRank >= 5) {
    static $avoidedSevero = false;

    if ($rollVal >= 6 && $rollVal <= 10) {
        $newRoll = 1; // Inmunidad total a Moderado
    } elseif (!$avoidedSevero && $rollVal >= 11 && $rollVal <= 15) {
        $newRoll = 1; // Evasión única de Severo
        $avoidedSevero = true;
    } elseif ($rollVal >= 16 && $rollVal <= 19) {
        $newRoll = 6; // Extremo → Moderado (NO a Severo como en II-IV)
    } elseif ($rollVal >= 11 && $rollVal <= 15) {
        $newRoll = 6; // Severo residual → Moderado
    }
}
```

### 7.7 Tabla Comparativa de Mitigación

| Grado | Favorable (1-5) | Moderado (6-10) | Severo (11-15) | Extremo (16-19) | Singular (20) |
|:-----:|:---------------:|:---------------:|:--------------:|:----------------:|:-------------:|
| I | — | — | — | — | — |
| II | — | → Favorable | → Moderado | → Severo | — |
| III | (doble tiro) | (doble tiro) | (doble tiro) | (doble tiro) | (doble tiro) |
| IV | — | → Favorable | 1º evasión, 2º→Mod | → Severo | — |
| V | — | **Inmune** → Fav | 1º evasión, 2º→Mod | → **Moderado** | — |

### 7.8 Bonus de Velocidad Acumulado

El navegante no solo mitiga eventos, sino que acelera el viaje:

| Grado | Bonus velocidad | Días ahorrados en viaje de 10 días |
|:-----:|:--------------:|:----------------------------------:|
| 0 | +0.0 | 0 |
| I | +0.5 | ~0.5 |
| II | +1.0 | ~1 |
| III | +1.5 | ~1.5 |
| IV | +2.0 | ~2 |
| V | +2.5 | ~2.5 |

**Cálculo:** `días_ahorrados = duración_sin_navegante - duración_con_navegante`

Para un viaje de distancia 50 con barco de velocidad 5:
- Sin navegante: `CEIL(50 / (5 * 10)) = 1 día`
- Con Grado V: `CEIL(50 / ((5 + 2.5) * 10)) = CEIL(50 / 75) = 1 día` → en viajes cortos no hay diferencia
- Sin navegante, distancia 100: `CEIL(100 / 50) = 2 días`
- Con Grado V, distancia 100: `CEIL(100 / 75) = 2 días` → igual
- Sin navegante, distancia 200: `CEIL(200 / 50) = 4 días`
- Con Grado V, distancia 200: `CEIL(200 / 75) = 3 días` → −1 día

El bonus es más notable en viajes largos (distancia > 150).

---

## 8. Integración con Instrumentos

### 8.1 Interacción Clima ↔ Instrumento

| Instrumento | Protege contra | Vulnerable a |
|-------------|----------------|--------------|
| **Brújula** | N/A (solo útil en Blues) | Desorientación magnética |
| **Log Pose** | Desorientación menor | Tormentas eléctricas intensas, campos magnéticos anómalos |
| **Eternal Pose** | Desorientación completa | Solo si el destino es destruido |
| **Sin instrumento** | Nada | Todo |

### 8.2 Bonus de Velocidad por Instrumento en Clima

| Instrumento | Bonus base | Bonus en clima favorable | Penalización en clima adverso |
|-------------|:----------:|:------------------------:|:----------------------------:|
| Brújula | +0.0 | +0.5 | −1.0 |
| Log Pose | +0.5 | +0.5 | −0.5 (desorientación) |
| Eternal Pose | +1.0 | +1.0 | +0.0 (siempre estable) |
| Ninguno | −1.0 | −0.5 | −2.0 |

### 8.3 Instrumentos Especiales contra el Clima

El staff puede crear cards de equipo especiales que proporcionen protección climática:

| Instrumento | Efecto |
|-------------|--------|
| **Log Pose Reforzado** | Resistente a tormentas eléctricas (no se desorienta) |
| **Eternal Pose de Ruta** | Fijado a una ruta específica, no a una isla |
| **Barómetro de Precisión** | +1 a mitigación de navegante para tormentas |
| **Veleta de New World** | Predice cambios climáticos con 1 hora de antelación |

---

## 9. Fenómenos Especiales por Zona

### 9.1 Blues — Fenómenos Especiales

```
┌───────────────────────────────────────────────────────────────┐
│  Blues — Fenómenos Raros (ocasionales)                        │
│                                                               │
│  • Lluvia de peces voladores: Peces saltan a cubierta.       │
│    Efecto: Provisiones gratis, pero riesgo de golpes.         │
│                                                               │
│  • Marea roja (bioluminiscencia): El mar brilla de noche.     │
│    Efecto: Visualmente espectacular. Sin peligro.             │
│                                                               │
│  • Niebla de coral: Niebla con aroma dulce y alucinógeno.     │
│    Efecto: −1 WIS por 1d4 horas si se respira.               │
│                                                               │
│  • Torbellino de viento: Pequeño vórtice que sube agua.       │
│    Efecto: Daño 1 al casco si impacta. Fácil de esquivar.    │
└───────────────────────────────────────────────────────────────┘
```

### 9.2 Grand Line — Fenómenos Especiales

```
┌───────────────────────────────────────────────────────────────┐
│  Grand Line — Fenómenos Extraordinarios (comunes)             │
│                                                               │
│  • Nieve de verano: Copos de nieve caen con el sol radiante.  │
│    Efecto: −1 AGI (frío repentino). Dura 1d6 horas.          │
│                                                               │
│  • Mar de nubes: Nubes densas a nivel del mar.                │
│    Efecto: Visibilidad 0. El barco navega "entre nubes".     │
│    Dura 1d3 horas. Posible desorientación.                    │
│                                                               │
│  • Rayo sin nubes: Descarga eléctrica de cielo despejado.     │
│    Efecto: Daño 1d4 aleatorio (barco o tripulante).          │
│                                                               │
│  • Calor extremo súbito: Temperatura sube 20°C en minutos.    │
│    Efecto: −2 a tiradas físicas. Dura 1d4 horas.             │
│                                                               │
│  • Erupción submarina: Volcán bajo el agua.                   │
│    Efecto: Olas anómalas, agua hirviendo cerca. Daño 1d6     │
│    al casco si se está en radio de 1km.                      │
│                                                               │
│  • Lluvia de meteoritos pequeños: Rocas del espacio caen.     │
│    Efecto: Daño 1d3 al casco. Posible fuego.                 │
└───────────────────────────────────────────────────────────────┘
```

### 9.3 New World — Fenómenos Especiales

```
┌───────────────────────────────────────────────────────────────┐
│  New World — Fenómenos Catastróficos (frecuentes)             │
│                                                               │
│  • Isla de fuego flotante: Masa de tierra volcánica errante.  │
│    Efecto: Colisión = daño 2d6 al casco. Calor extremo       │
│    a 500m. Logia de fuego puede interactuar.                  │
│                                                               │
│  • Ballena de tormenta: Criatura que genera clima extremo.    │
│    Efecto: Atrae tormentas. Si se le enfada, ataque directo. │
│    Si se le calma, guía a aguas seguras.                     │
│                                                               │
│  • Vórtice gigante: Remolino de km de diámetro.              │
│    Efecto: Atrapa el barco. 1d6 daño por hora. Escapar       │
│    requiere tirada de navegación CD 15+ o fuerza bruta.      │
│                                                               │
│  • Tornado de hielo: Columna de aire congelante.              │
│    Efecto: Congela velas y cubierta. Daño 1d6+1.             │
│    Artillería inutilizada hasta descongelar.                  │
│                                                               │
│  • Lluvia de fuego: Ceniza ardiente y lava líquida del cielo. │
│    Efecto: Daño 1d4 por ronda en cubierta. Refugio           │
│    obligatorio. Sin protección, daño continuo.                │
│                                                               │
│  • Mar de lava: Agua que hierve por actividad volcánica.      │
│    Efecto: Daño 1d4 al casco por hora. Barcos no            │
│    recubiertos sufren daño estructural progresivo.            │
│                                                               │
│  • Tormenta eléctrica rastreadora: Nube que persigue al barco.│
│    Efecto: Rayos continuos. Daño 1d6 por impacto.            │
│    No se puede escapar hasta que se disipa (1d6 horas).      │
└───────────────────────────────────────────────────────────────┘
```

### 9.4 Fenómenos de Zonas Especiales

```
┌───────────────────────────────────────────────────────────────┐
│  Calm Belt                                                    │
│  • Calma absoluta: Sin viento. Barcos de vela inmóviles.     │
│  • Reyes del Mar: Múltiples especies gigantes.                │
│  • Superficie espejo: El mar refleja el cielo perfectamente.  │
│                                                               │
│  Triángulo de Florian                                         │
│  • Niebla perpetua: Visibilidad < 5m.                        │
│  • Barcos fantasma: Embarcaciones que aparecen y desaparecen. │
│  • Ecos sin fuente: Voces, risas, llantos.                   │
│  • Fallo de brújula: Todas las agujas apuntan al centro.     │
└───────────────────────────────────────────────────────────────┘
```

---

## 10. Migraciones SQL

### 10.1 Las migraciones climáticas no crean tablas nuevas

El sistema de clima **reutiliza** las tablas existentes de navegación y oráculos. No hay `game_climate_events` ni `game_weather_patterns`. Todo el clima se almacena en:

| Tabla | Rol climático |
|-------|---------------|
| `game_oracles` | Define los oráculos climáticos (`subtype = nav_*`, `tags_json` con `"navegacion"`) |
| `game_post_oracles` | Registra cada tirada climática durante el viaje |
| `game_navigation_events` | Asocia la tirada climática al viaje |
| `game_navigation_voyages` | Almacena el `sea_zone` y `danger_level` del viaje |

### 10.2 Migraciones Relevantes

#### `migrate_navigation_system.php`

Crea las tablas base del sistema de navegación y siembra los oráculos climáticos iniciales:

```php
// Seeds: nav_1_2, nav_3, nav_4_5
// Estos son los tres oráculos climáticos principales
```

#### `migrate_navigation_oracles_expand.php`

Añade oráculos de navegación específicos por nivel de peligro + resoluciones auto-invocadas:

```php
// Añade: nav_1 (d12), nav_2 (d20), nav_4 (d20), nav_5 (d12)
// Añade: nav_resolve_naval (d6), nav_resolve_beast (d6)
// Enriquece nav_1_2 con auto_invoke en resultados "Encuentro pirata" y "Tormenta menor"
```

**Oráculos climáticos añadidos por esta migración:**

| Nombre | Subtipo | Dado | Resultados |
|--------|---------|:----:|------------|
| Navegación — Brisa del East Blue | `nav_1` | d12 | 5 resultados (mar en calma, gaviotas, corriente suave, pesca, viento cambiante) |
| Navegación — Incidente en ruta | `nav_2` | d20 | 8 resultados (lluvia, arrecife, barco pesquero, viento en contra, humo, emboscada→auto, sombra→auto) |
| Navegación — Corsarios y patrullas | `nav_4` | d20 | 6 resultados (señal de humo, patrulla, mina, flota→auto, caza marina, kraken→auto) |
| Navegación — Abismo extremo | `nav_5` | d12 | 6 resultados (anomalía, meteoritos, muro tormenta, territorio yonko, kraken→auto, colisión→auto) |

#### `migrate_weather_oracles.php`

Actualiza los tres oráculos climáticos base con descripciones enriquecidas y efectos mecánicos explícitos:

```php
// migrate_weather_oracles.php
$db->write_query("UPDATE game_oracles SET results_json = ... WHERE subtype = 'nav_1_2'");
$db->write_query("UPDATE game_oracles SET results_json = ... WHERE subtype = 'nav_3'");
$db->write_query("UPDATE game_oracles SET results_json = ... WHERE subtype = 'nav_4_5'");
```

### 10.3 Si Necesitas Añadir Clima a una Isla Específica

No necesitas migración. Usa `variations_json` en el oráculo existente:

```sql
UPDATE mybb_game_oracles
SET variations_json = '{
  "Arabasta": [
    {"range":"1-5","result":"Viento del desierto","description":"...","auto_invoke":null},
    {"range":"6-10","result":"Calima","description":"..."}
  ]
}'
WHERE subtype = 'nav_1_2';
```

Esto hace que el oráculo `nav_1_2` tenga resultados diferentes para posts en la categoría "Arabasta".

---

## 11. Flujo de Datos

### 11.1 El Clima en el Ciclo de Vida del Viaje

```
Post con navegación habilitada
    │
    ▼
game_navigation_process_post()
    │
    ├── Calcular distancia, peligro, duración, zona
    │   (el peligro determina qué oráculo climático se usará)
    │
    ├── INSERT en game_navigation_voyages
    │   (se guarda danger_level, sea_zone)
    │
    └── game_navigation_generate_events()
        │
        ├── game_nav_get_oracles_for_danger(danger)
        │   (obtiene oráculos climáticos para este nivel de peligro)
        │
        └── Por cada evento:
            │
            ├── Seleccionar oráculo aleatorio de los disponibles
            ├── Mitigar según grado de navegante
            │   (doble tirada, reducción de severidad, inmunidad)
            │
            ├── game_roll_oracle(oracle, category)
            │   (aplica variations_json si la categoría tiene variación)
            │
            ├── Determinar efectos climáticos del resultado
            │   (retraso, daño, desorientación, penalización)
            │
            ├── INSERT en game_post_oracles
            │
            ├── INSERT en game_navigation_events
            │
            └── game_navigation_maybe_invoke_chain()
                (si el resultado climático tiene auto_invoke)
```

### 11.2 El Clima NO afecta a:

| Aspecto | Explicación |
|---------|-------------|
| **Cálculo inicial del viaje** | El clima se genera durante el viaje, no antes. La vista previa no muestra clima. |
| **Disponibilidad de rutas** | El clima no bloquea rutas (aunque narrativamente podría). |
| **Staff review** | El clima no acelera ni retrasa la revisión. |
| **Duración real del viaje** | El `duration_days` es fijo. El retraso climático es narrativo. |

### 11.3 Ejemplo de Traza Completa

**Viaje:** Loguetown → Arabasta (Grand Line)
**Peligro:** 3 · **Zona:** `grand_line` · **Eventos:** 3

```
EVENTO 1:
  game_nav_get_oracles_for_danger(3) → [nav_3]
  game_roll_oracle(nav_3, 'Arabasta')
    → variations_json NO tiene variación para nav_3 en Arabasta
    → se usa results_json base
  → Roll: 14 → "Rayos sin nubes / Calor extremo (Severo)"
  → Sin auto_invoke
  → Efecto: penalización AGI/DES, daño leve
  → Mitigación (Grado III): doble tirada → roll original 14 vs 2ª tirada 8 → se queda con 8
  → Resultado mitigado: "Nieve en verano / Lluvia cálida (Moderado)"
  → Efecto mitigado: −1 AGI/DES

EVENTO 2:
  game_roll_oracle(nav_3, 'Arabasta')
  → Roll: 20 → "Lluvia de meteoritos / Erupción submarina (Singular)"
  → Auto-invoke: nav_resolve_beast (criatura atraída por la erupción)
  → Mitigación: Grado III no mitiga Singular
  → Efecto: daño 1d6 al casco, +2 días, penalización −4 AGI/DES
  → nav_resolve_beast: roll 4 → "Golpe al casco" (daño adicional)

EVENTO 3:
  game_roll_oracle(nav_3, 'Arabasta')
  → Roll: 5 → "Corriente inversa favorable (Favorable)"
  → Mitigación: no necesita (ya es favorable)
  → Efecto: velocidad +20%, −1 día (compensa en parte el retraso del evento 2)
```

---

## 12. Filosofía de Diseño

### 12.1 ¿Por qué el clima emerge de los oráculos (no es un sistema aparte)?

**Respuesta corta:** Porque el clima ES el evento cuando navegas.

En la mayoría de los RPGs, el clima es una capa separada: tiras clima, luego tiras encuentro, luego combinas. Esto funciona en juegos de mesa donde hay un DJ que orquesta. En un sistema automatizado post-by-post, cada capa adicional multiplica la complejidad.

Al hacer que el clima **sea** el contenido del oráculo:
- No hay estado global del clima que mantener.
- No hay cálculos de "clima actual + evento = resultado combinado".
- Cada evento es autónomo y autocontenido.
- El jugador recibe una experiencia narrativa completa en un solo resultado.

**Analogía:** En lugar de tener un termómetro, un barómetro y un pluviómetro que se leen por separado, el oráculo climático es un "medidor de clima percibido" que te dice directamente cómo se siente el tiempo ahora.

### 12.2 ¿Por qué los efectos climáticos severos se concentran en el New World?

El New World en One Piece no es solo "más difícil" — es cualitativamente diferente. El clima no es impredecible (Grand Line), es **hostil activo**. Las tormentas persiguen barcos, los mares hierven, las islas explotan. Reflejar esto en el juego requiere:

1. **Daño estructural frecuente:** El barco no puede cruzar el New World sin recibir daño.
2. **Penalizaciones severas:** −4 AGI/DES hace que el combate en cubierta sea casi imposible.
3. **Log Pose bajo amenaza:** Si el instrumento se desorienta o resetea, el viaje se alarga drásticamente.
4. **Singular como amenaza existencial:** Un 20 en `nav_4_5` no es solo "malo" — redefine el viaje.

Esto asegura que el New World se sienta como un **logro alcanzar**, no como un simple escalón de dificultad.

### 12.3 ¿Por qué el navegante mitiga el clima (en lugar de evitarlo)?

Si el navegante simplemente ignorara el clima, el sistema climático sería irrelevante para quien invierta en el oficio. Al **mitigar** en lugar de **eliminar**:

- Grado II: El clima sigue siendo un factor, pero menos severo.
- Grado III: La doble tirada hace que el clima sea más predecible.
- Grado IV: La evasión única es un recurso táctico.
- Grado V: Casi inmune, pero los Singulars siguen siendo amenazas.

**Principio:** El clima debe ser siempre una consideración, incluso para los mejores navegantes. Un Grado V no debería aburrirse en el mar — debería sentir que domina un entorno que doblega a otros.

### 12.4 ¿Por qué las penalizaciones de combate son por evento (no persistentes)?

El clima en combate naval es una **condición de escena**, no un modificador permanente. Si la tormenta termina, los penalizadores desaparecen. Esto:

- Evita que un personaje esté permanentemente debilitado por un evento que ocurrió hace 3 posts.
- Da al jugador agency: "Puedo esperar a que pase la tormenta para luchar".
- Recompensa la planificación: "Usemos el cañón antes de que la tormenta inhabilite la artillería".

### 12.5 ¿Por qué Logia tiene ventajas narrativas (no mecánicas)?

Las Logia son intrínsecamente poderosas. Darles ventajas mecánicas automáticas contra el clima las haría indispensables y reduciría la diversidad de tripulaciones. Al mantener las ventajas como **narrativas**:

- El jugador de Logia se siente especial ("Mira, puedo caminar sobre el mar de lava").
- El jugador no Logia no se siente castigado ("No tengo frío porque llevo ropa adecuada").
- El staff puede premiar la creatividad sin romper el balance.

### 12.6 ¿Por qué no hay tablas de clima separadas por isla?

En lugar de crear 50 oráculos climáticos (uno por isla), usamos `variations_json` para personalizar los resultados por categoría. Esto:

- Mantiene el catálogo manejable (3 oráculos base + 4 expandidos).
- Permite personalización sin duplicación.
- Facilita la creación de nuevas islas (solo añades una variación).

---

## 13. Consejos para Jugadores

### 13.1 Preparación Climática

```
☐ ¿Conozco el clima típico de mi ruta? (Blues = predecible, GL = caótico, NW = hostil)
☐ ¿Mi barco tiene resistencia al clima de la zona? (revestimiento, velas reforzadas)
☐ ¿Llevo instrumentos adecuados? (Log Pose para GL/NW, Eternal Pose para rutas fijas)
☐ ¿Mi tripulación tiene un navegante? (si no, el viaje será más lento y peligroso)
☐ ¿Tengo cards o equipo que mitiguen el clima? (pararrayos, estabilizadores)
```

### 13.2 Cómo Leer los Eventos Climáticos

Cuando el sistema genere un evento climático en tu viaje:

1. **Lee el resultado:** "Tormenta menor / Mar picado (Severo)".
2. **Identifica la severidad:** Severo → penalizaciones.
3. **Rolea la experiencia:** Describe cómo tu personaje enfrenta la tormenta.
4. **Considera el impacto:** ¿Afecta esto a tus planes? ¿Necesitas esperar?

**Ejemplo de roleo:** "El viento arrecia y la cubierta se inclina 30 grados. Me aferro al timón mientras una ola barrería a cualquiera que no esté asegurado. —Sujetad el foque, que se nos lleva el mástil— grito sobre el rugido del viento."

### 13.3 Inversión Recomendada en Navegante

| Si navegas principalmente en... | Grado mínimo recomendado |
|--------------------------------|:------------------------:|
| **Blues** (East, West, North, South) | I–II |
| **Grand Line** | III |
| **New World** | IV–V |
| **Viajes mixtos** | III (cubre todos los mares competentemente) |

**Coste total para llegar a cada grado:**
- Grado I: Gratis (primer oficio)
- Grado II: 50 PP (acumulado: 50)
- Grado III: 90 PP (acumulado: 140)
- Grado IV: 130 PP (acumulado: 270)
- Grado V: 190 PP (acumulado: 460)

**Comparación:** 460 PP para un navegante maestro vs 650 PP para una disciplina de combate maestra. El navegante es una inversión rentable para personajes viajeros.

### 13.4 Tripulación sin Navegante

Si tu personaje viaja sin navegante:
- **Acepta que los viajes serán más lentos** (−1.0 de velocidad respecto a un navegante Grado III).
- **Compensa con instrumentos:** Eternal Pose da +1.0, Log Pose da +0.5.
- **Compensa con barco:** Un barco con velocidad base 8 compensa la falta de navegante.
- **Acepta los eventos:** Sin mitigación, recibirás el clima en su máxima expresión.

### 13.5 Interacciones Logia y Clima

Si tienes una Logia, úsala creativamente:

| Logia | Qué puedes hacer narrativamente |
|-------|--------------------------------|
| Mera Mera (fuego) | Derretir hielo en cubierta, calentar mástiles congelados, crear cortina de calor contra tornados de hielo |
| Hie Hie (hielo) | Congelar olas peligrosas, crear camino sobre mar de lava brevemente, proteger el barco del calor |
| Goro Goro (rayo) | Absorber rayos de tormentas, recargar energía, desviar descargas del barco |
| Suna Suna (arena) | Crear barrera contra viento, llenar vórtices, desviar lluvia de fuego con muro de arena |
| Yami Yami (oscuridad) | Absorber cualquier fenómeno (canónicamente) |

Recuerda: son ventajas **narrativas**. No esperes bonuses automáticos. Sorprende al staff con tu creatividad.

---

## 14. Consejos para Staff

### 14.1 Diseño de Clima para Islas Nuevas

Al crear una nueva isla:

1. Define su `sea_zone` según la ubicación.
2. Establece un `base_danger` coherente (1–2 para Blues, 3 para GL, 4–5 para NW).
3. Si la isla tiene un clima distintivo, añade una variación en el oráculo correspondiente:

```sql
-- Ejemplo: Isla "Invernalia" en North Blue con clima extremo
UPDATE mybb_game_oracles
SET variations_json = JSON_MERGE_PATCH(
    COALESCE(variations_json, '{}'),
    '{"Invernalia": [
        {"range":"1-5","result":"Ventisca ártica","description":"...","auto_invoke":null},
        {"range":"6-10","result":"Hielo marino","description":"..."},
        {"range":"11-15","result":"Tormenta de nieve","description":"..."},
        {"range":"16-19","result":"Noche polar","description":"..."},
        {"range":"20","result":"Avalancha submarina","description":"..."}
    ]}'
)
WHERE subtype = 'nav_1_2';
```

### 14.2 Balanceo de la Frecuencia Climática

| Síntoma | Posible causa | Solución |
|---------|---------------|----------|
| Demasiados eventos Favorables | `base_danger` muy bajo | Subir el peligro base de la ruta |
| Demasiados eventos Extremos/Singular | `base_danger` muy alto | Bajar el peligro o añadir más rangos Favorables |
| Viajes en Blues son siempre aburridos | Poca variedad en `nav_1_2` | Añadir variaciones por isla en Blues |
| Viajes en NW son siempre mortales | Es el diseño intencional | Asegurar que los jugadores sepan que NW requiere preparación |
| Navegantes Grado V nunca tienen problemas | Es el diseño intencional | Recordar que Singular (20) no se mitiga |

### 14.3 Uso de Fenómenos Especiales

Los fenómenos especiales (sección 9) son **narrativos**. No están en los oráculos del sistema. El staff puede:

1. **Añadirlos manualmente** a los eventos de un viaje editando la BD.
2. **Usarlos como inspiración** para posts de respuesta automática.
3. **Crear oráculos personalizados** para islas específicas.

**Ejemplo:** Si un viaje en el Triángulo de Florian genera un evento Moderado, el staff puede enriquecer la descripción: "La niebla se espesa y escuchas un violín a lo lejos. No hay nadie más en cubierta."

### 14.4 Revisión de Eventos Climáticos

Al revisar un viaje, el staff debe verificar:

| Aspecto | Qué comprobar |
|---------|---------------|
| **Coherencia** | ¿El evento climático tiene sentido en la zona del viaje? |
| **Roleo** | ¿El jugador mencionó el clima en su post? |
| **Acumulación** | ¿Hubo múltiples eventos severos consecutivos? El peligro debería haber subido. |
| **Daño al barco** | Si hubo daño, ¿se refleja en el estado del barco? |
| **Auto-invocaciones** | ¿Se generaron encuentros que deberían haberse roleado? |

### 14.5 Personalización de la Dificultad

Si el foro tiene pocos jugadores o prefieres viajes más suaves:

- **Reduce el peso de `danger_override`** en `game_nav_calculate_danger()`.
- **Añade más rangos Favorables** en los oráculos climáticos (ej: 1-8 en lugar de 1-5).
- **Aumenta el umbral de eventos:** Cambia `GAME_NAV_EVENTS_MIN/MAX` en `navigation_config.php`.

Si el foro tiene jugadores experimentados que buscan desafío:

- **Aumenta el peligro base** de las islas.
- **Reduce rangos Favorables** en los oráculos de New World.
- **Añade auto-invocaciones** a más resultados climáticos.

---

## 15. Guía de Troubleshooting

### 15.1 Problemas Comunes

| Problema | Causa probable | Solución |
|----------|---------------|----------|
| El evento no tiene descripción climática | El oráculo no se encontró o no tiene `results_json` | Verificar `game_oracles` para el `subtype` correcto |
| El clima es siempre "Favorable" | `danger_level` demasiado bajo o navegante muy alto | Revisar `base_danger` de las islas y grado del navegante |
| El clima es siempre "Singular" | El oráculo seleccionado es `nav_5` y tiene pocos resultados | Usar `nav_4_5` en lugar de `nav_5` para más variedad |
| La mitigación del navegante no se aplica | El grado del navegante no se está detectando | Verificar `game_oficio_get_rank(characterId, 'navegante')` |
| El Log Pose debería desorientarse pero no pasa | La desorientación es narrativa, no automática | El staff debe indicarlo en la descripción del evento |
| El daño al casco no se registra | No hay sistema de integridad de barco implementado | El daño es narrativo hasta que se implemente el sistema de reparaciones |
| Las variaciones climáticas por isla no se aplican | La categoría del post no coincide con la clave en `variations_json` | Verificar `game_get_post_category()` para el post |

### 15.2 Debugging de Clima

Para debuggear un evento climático específico:

1. **Revisa el `raw_calculation_json`** en `game_navigation_voyages`: Contiene la zona, peligro y efectos del barco.
2. **Consulta `game_post_oracles`** para el viaje: Muestra qué oráculo se tiró y qué resultado dio.
3. **Verifica el grado del navegante:** `SELECT * FROM game_character_oficios WHERE character_id = X AND oficio_id = (SELECT id FROM game_oficios WHERE slug = 'navegante')`.
4. **Simula una tirada:** Usa el staff tool de preview de oráculos para ver qué resultados produce `nav_3` o `nav_4_5`.

### 15.3 Preguntas Frecuentes

**P: ¿Puedo crear mi propio oráculo climático para una isla específica?**
R: Sí. Usa el staff tool de oráculos (`oracles_staff.php`) para crear un oráculo con `subtype = 'nav_1_2'` y `category = 'NombreIsla'`. El sistema lo seleccionará automáticamente para esa categoría.

**P: ¿El clima puede cancelar un viaje?**
R: No automáticamente. El sistema genera eventos pero no cancela viajes. Si el clima es tan extremo que el viaje no tiene sentido, el staff puede denegar la revisión o el jugador puede rolear que da media vuelta.

**P: ¿Los Reyes del Mar cuentan como clima?**
R: No. Los Reyes del Mar son criaturas, no clima. Aparecen mediante auto-invocación `nav_resolve_beast` cuando un resultado climático los atrae.

**P: ¿Puede un evento climático ser tan severo que mate a un personaje?**
R: El sistema no mata personajes automáticamente. El clima puede causar daño, retrasos y penalizaciones, pero la muerte de un personaje requiere decisión del staff y contexto narrativo.

**P: ¿Cómo sabe mi personaje qué tiempo va a hacer?**
R: No lo sabe. Los oráculos se tiran en el momento del evento. El personaje experimenta el clima en tiempo real, como en la vida real. Los navegantes de alto grado pueden "anticipar" (de ahí la mitigación), pero no predecir con certeza.

---

## Apéndice A: Referencia Rápida de Efectos Climáticos

| Severidad | Retraso | Daño casco | Penalización AGI/DES | Log Pose | Peligro + |
|-----------|:-------:|:----------:|:--------------------:|:--------:|:---------:|
| Favorable | −1 día | 0 | +1 | — | −1 |
| Moderado | 0 | 0 | 0 | — | 0 |
| Severo | +1 día | 1 | −2 | — | +1 |
| Extremo | +2–3 días | 1d3 | −3 | Desorientación 1d4h | +1 |
| Singular | +2–4 días | 1d6 | −4 | Reseteo | +2 |

## Apéndice B: Mapa de Oráculos Climáticos

| Zona | Peligro | Oráculo primario | Oráculo expandido |
|------|:-------:|:----------------:|:-----------------:|
| East Blue | 1 | `nav_1_2` (d20) | `nav_1` (d12) |
| West Blue | 1–2 | `nav_1_2` (d20) | `nav_2` (d20) |
| North Blue | 2–3 | `nav_1_2` / `nav_3` | `nav_2` (d20) |
| South Blue | 2–3 | `nav_1_2` / `nav_3` | `nav_2` (d20) |
| Grand Line | 3 | `nav_3` (d20) | — |
| New World | 4–5 | `nav_4_5` (d20) | `nav_4` (d20) / `nav_5` (d12) |
| Calm Belt | 5 | `nav_4_5` (d20) | `nav_5` (d12) |
| Triángulo de Florian | 5 | `nav_4_5` (d20) | `nav_5` (d12) |

## Apéndice C: Integración con Combat System

| Efecto climático | Impacto en combate |
|-----------------|-------------------|
| Lluvia moderada | −1 a ataques a distancia. Armamento mojado: sin efecto. |
| Tormenta severa | −2 a todos los ataques. Posibles caídas. |
| Tormenta eléctrica | −3 a ataques. Armas metálicas conducen electricidad. |
| Mar de lava | −4 a ataques cuerpo a cuerpo. Daño por calor cada ronda. |
| Niebla densa | −2 a ataques a distancia (visibilidad). Ventaja para sigilo. |
| Viento fuerte | −3 a ataques a distancia. Proyectiles se desvían. |
| Tornado | −4 a todos los ataques. Riesgo de ser succionado. |
| Calma total | Sin penalización ofensiva. Sin movimiento de barco. |
