# 8. ESTILOS CANÓNICOS — GUÍA COMPLETA

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 8
> **Propósito:** Documentar exhaustivamente el subsistema de estilos canónicos: definición, diferencia con disciplinas, estructura de datos, modelo relacional, servicios PHP, integración con cards, flujo de aprendizaje, validaciones, template de creación, filosofía de diseño, y consejos para jugadores y staff.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [¿Qué es un Estilo Canónico?](#2-qué-es-un-estilo-canónico)
3. [Diferencia entre Disciplinas y Estilos Canónicos](#3-diferencia-entre-disciplinas-y-estilos-canónicos)
4. [Estructura de un Estilo Canónico](#4-estructura-de-un-estilo-canónico)
5. [Sistema de Requisitos Genérico](#5-sistema-de-requisitos-genérico)
6. [Relación Estilo-Card](#6-relación-estilo-card)
7. [Template para Crear un Nuevo Estilo](#7-template-para-crear-un-nuevo-estilo)
8. [Modelo de Datos](#8-modelo-de-datos)
9. [PHP — Servicios y Helpers](#9-php---servicios-y-helpers)
10. [Flujo de Aprendizaje de un Estilo](#10-flujo-de-aprendizaje-de-un-estilo)
11. [Flujo de Validación de Cards por Estilo](#11-flujo-de-validación-de-cards-por-estilo)
12. [Biblioteca Pública de Estilos (Frontend)](#12-biblioteca-pública-de-estilos)
13. [Consejos para Staff: Crear Estilos Balanceados](#13-consejos-para-staff-crear-estilos-balanceados)
14. [Consejos para Jugadores](#14-consejos-para-jugadores)
15. [Filosofía de Diseño](#15-filosofía-de-diseño)

---

## 1. Arquitectura General

### 1.1 Capas del Subsistema

```
┌─────────────────────────────────────────────────────────────────────┐
│                       CLIENTE (Navegador)                           │
│  ┌──────────────────┐  ┌────────────────────┐                       │
│  │ estilos.js       │  │ personaje_page.js  │                       │
│  │ (biblioteca      │  │ (deck tab →        │                       │
│  │  pública)        │  │  filtro estilo)     │                       │
│  └────────┬─────────┘  └────────┬───────────┘                       │
│           │                     │                                    │
│           ▼                     ▼                                    │
│  ┌──────────────────────────────────────────────────────────┐       │
│  │              AJAX (game/ajax/*.php)                       │       │
│  │  cards_assign.php     → validación estilo + disciplina    │       │
│  │  cards_request_*.php  → solicitud cards con estilo        │       │
│  └────────────────────────────┬─────────────────────────────┘       │
└───────────────────────────────┼──────────────────────────────────────┘
                                │ HTTP POST/GET + JSON
┌───────────────────────────────┼──────────────────────────────────────┐
│  ┌────────────────────────────▼─────────────────────────────────────┐│
│  │              PHP — CAPA DE HELPERS                                ││
│  │  estilos_canonicos_helpers.php  — CRUD + consultas de estilos    ││
│  │  grado_helpers.php             — Validación cards (disciplina)   ││
│  │  cards_helpers.php             — Validación estilo → card        ││
│  └──────────────────────────────────────────────────────────────────┘│
│                              │                                        │
│                              ▼                                        │
│  ┌──────────────────────────────────────────────────────────────────┐│
│  │              MySQL                                              ││
│  │  game_estilos_canonicos          → catálogo maestro de estilos  ││
│  │  game_character_estilos          → estilos aprendidos por PJ    ││
│  │  game_cards.estilo_canonico_slug → FK lógica a estilo           ││
│  └──────────────────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────────────┘
```

### 1.2 Filosofía de la Arquitectura

**¿Por qué los estilos son entidades separadas de las disciplinas?**

Las disciplinas representan **árboles de habilidades genéricos** abiertos a cualquier personaje que invierta PP. Un personaje puede tener "Armas de Filo grado III" sin que eso defina su escuela de combate — simplemente sabe manejar espadas.

Los estilos canónicos, en cambio, representan **escuelas de combate con identidad narrativa**. No se compran con PP: se aprenden mediante trama, entrenamiento IC, y aprobación del staff. Un estilo es un compromiso narrativo que otorga acceso a técnicas exclusivas.

Separar estilos de disciplinas permite:
1. **Desacoplar progresión mecánica de identidad temática.** Un personaje puede subir su disciplina a grado V sin atarse a ningún estilo. O puede aprender un estilo en grado II de disciplina y mantenerlo como su seña de identidad aunque nunca suba la disciplina a V.
2. **Validación granular de cards.** Las cards de un estilo requieren dos validaciones: disciplina (mecánica) y estilo (narrativa). Un personaje puede tener la disciplina requerida pero no el estilo, y viceversa.
3. **Catálogo extensible.** El staff puede añadir nuevos estilos sin modificar el sistema de disciplinas. Cada estilo es un registro en `game_estilos_canonicos` con sus propios requisitos y cards asociadas.

**¿Por qué una tabla `game_character_estilos` en lugar de un JSON array?**

- **Consultas eficientes.** El staff necesita responder "¿qué personajes tienen el estilo X?" para planificar arcos narrativos o validar tramas. Un JOIN directo evita escanear JSON.
- **Integridad referencial.** La FK lógica a `game_estilos_canonicos.slug` evita slugs huérfanos.
- **Historial.** La columna `learned_at` permite saber cuándo se aprendió cada estilo, útil para auditoría.
- **Rendimiento en listas.** Al cargar la ficha de un personaje, obtener sus estilos es un JOIN simple, no un parseo de JSON columna.

**¿Por qué `requirements_json` y `advantages_json` son TEXT (JSON) en lugar de columnas normalizadas?**

- **Estructura variable.** Cada estilo tiene requisitos diferentes en número y tipo. Algunos tienen 2 requisitos, otros 5. Algunos requisitos son condiciones simples ("disciplina grado II"), otros son compuestas ("raza Gyojin O maestro reconocido").
- **Flexibilidad de creación.** Cuando el staff crea un estilo nuevo, define los requisitos y ventajas en JSON. No hay restricciones de esquema que limiten la creatividad.
- **Lectura unificada.** Una sola query carga todos los datos del estilo, incluidos requisitos y ventajas, sin JOINs adicionales.

---

## 2. ¿Qué es un Estilo Canónico?

### 2.1 Definición

Un **estilo canónico** es una escuela de combate con identidad propia, vinculada a una disciplina base y con requisitos narrativos concretos. Representa una tradición marcial reconocible dentro del mundo del foro, con sus propias técnicas, filosofía de combate, y condiciones de acceso.

Cada estilo canónico:
- Pertenece a una **disciplina base** (ej: `cuerpo_a_cuerpo`, `armas_de_filo`).
- Tiene un **stat primario** que escala sus técnicas (ej: `fuerza`, `destreza`).
- Define **requisitos narrativos y mecánicos** que el personaje debe cumplir para aprenderlo.
- Otorga **ventajas narrativas** específicas (no mecánicas, sino contextuales).
- Agrupa **cartas de técnica exclusivas** que solo quienes dominan el estilo pueden solicitar.

### 2.2 ¿Qué NO es un Estilo Canónico?

- **No es una disciplina.** No tiene grados I–V. No se mejora con PP. No tiene cooldown de subida. No se adquiere en el wizard de creación. No aparece en el catálogo de disciplinas.
- **No es un árbol de habilidades.** No hay progresión interna dentro del estilo. Las técnicas del estilo se adquieren como cards individuales, no como ramas de un árbol.
- **No es un reemplazo de disciplina.** Un personaje no puede tener un estilo sin tener la disciplina base. El estilo ES una especialización narrativa SOBRE una disciplina. La relación es jerárquica: disciplina → estilo.
- **No es un perk de linaje.** Los estilos no se compran con puntos de linaje. Se aprenden mediante trama, entrenamiento IC, y aprobación del staff. No hay coste en PP ni en PD.

### 2.3 Analogía Conceptual

```
Disciplina = "Saber tocar el piano" (conocimiento genérico, grados I–V)
Estilo     = "Ser concertista de jazz" (escuela específica, requisitos narrativos)
Card       = "Acorde de séptima disminuida en 4/4" (técnica concreta que usas en tu interpretación)
```

Un personaje puede "saber tocar el piano" (disciplina Armas de Filo grado III) sin ser "concertista de jazz" (estilo Santōryū). Pero no puede ser concertista de jazz sin saber tocar el piano.

---

## 3. Diferencia entre Disciplinas y Estilos Canónicos

### 3.1 Tabla Comparativa

| Aspecto | Disciplina | Estilo Canónico |
|---------|-----------|-----------------|
| **Definición** | Árbol de habilidades genérico | Escuela de combate temática |
| **Adquisición** | Compra con PP (autoservicio) | Aprobación staff + trama IC |
| **Coste** | PP (escalado por cantidad poseída) | 0 PP (narrativo) |
| **Grados** | I–V (progresión vertical) | No tiene grados |
| **Requisitos** | Nivel global, PP, cooldown | Disciplina mínima, stat mínimo, condición narrativa |
| **Acceso inicial** | Gratis (1ra en creación) | Nunca en creación |
| **¿Requiere staff?** | Solo para subir de grado | Sí, siempre |
| **Cards asociadas** | Cualquier card de la disciplina | Cards exclusivas con `estilo_canonico_slug` |
| **Narrativa** | Genérica (sabes usar el arma) | Específica (sabes una escuela concreta) |
| **Cuantos puede tener un PJ** | Múltiples (con coste creciente) | Múltiples (sin límite duro, pero cada uno requiere trama) |
| **Pérdida** | No se pierde (a menos que staff lo remueva) | No se pierde (a menos que staff lo remueva) |

### 3.2 ¿Por qué existen los Estilos si ya existen las Disciplinas?

Las disciplinas resuelven la pregunta **"¿qué tan bueno eres con este tipo de combate?"** Los estilos responden **"¿qué escuela o tradición específica practicas?"**

En un foro de rol, los personajes no son solo "espadachines grado III". Son "espadachines grado III del estilo Santōryū" o "espadachines grado III del estilo Ittōryū". El estilo da identidad, flavor, y un marco narrativo para las técnicas.

Sin estilos, todos los espadachines con "Armas de Filo grado III" tendrían acceso a las mismas cards genéricas. Con estilos, cada escuela tiene su propio repertorio de técnicas exclusivas, creando diversidad temática entre personajes que comparten disciplina.

### 3.3 Mapa de Dependencias

```
Personaje
  ├── Disciplina (Armas de Filo grado III)
  │     └── Cards genéricas de la disciplina
  │           └── "Tajo básico", "Corte poderoso"
  │
  └── Estilo Canónico (Santōryū)
        └── Cards exclusivas del estilo
              └── "Oni Giri", "Yakkodori", "Tatsu Maki"
```

Las cards genéricas de disciplina son accesibles a cualquier personaje con la disciplina suficiente. Las cards de estilo son accesibles SOLO a quienes han aprendido el estilo. Un personaje con "Armas de Filo grado III" pero sin estilo Santōryū NO puede obtener "Oni Giri".

---

## 4. Estructura de un Estilo Canónico

### 4.1 Campos Definidos

Cada estilo canónico en el catálogo (`game_estilos_canonicos`) tiene los siguientes campos:

| Campo | Tipo | Descripción | Ejemplo |
|-------|------|-------------|---------|
| `slug` | string | Identificador único URL-friendly | `estilo_ejemplo` |
| `name` | string | Nombre visible del estilo | `Estilo Ejemplo` |
| `category` | string | Categoría mecánica (agrupación) | `artes_marciales` |
| `category_label` | string | Etiqueta legible para la categoría | `Artes marciales` |
| `disciplina_slug` | string | Slug de la disciplina base | `cuerpo_a_cuerpo` |
| `primary_stat` | string | Stat principal que escala las técnicas | `fuerza` |
| `short_desc` | string | Frase corta que define el estilo (1 línea) | `Estilo basado en...` |
| `description` | string | Descripción filosófica y técnica (3-5 líneas) | `Tradición de...` |
| `requirements` | array | Lista de requisitos (3-4 items) | `["Disc. X grado II+", ...]` |
| `advantages` | array | Lista de ventajas narrativas (3-4 items) | `["Ventaja en...", ...]` |
| `image_url` | string | URL de imagen representativa | `https://...png` |
| `sort_order` | int | Orden de visualización (múltiplo de 10) | `10` |
| `is_active` | bool | Si está disponible en el catálogo | `true` |

### 4.2 Categorías de Estilo

Los estilos se agrupan en categorías para facilitar la navegación y el filtrado en la biblioteca pública:

| Categoría (slug) | Categoría (label) | Ejemplos de lo que agrupa |
|------------------|-------------------|--------------------------|
| `artes_marciales` | Artes marciales | Estilos de puño, patada, cuerpo a cuerpo |
| `esgrima` | Esgrima / espadas | Estilos con una o múltiples espadas |
| `tirador` | Tirador / distancia | Estilos de combate a distancia |
| `estilo_especial` | Técnica especial | Estilos que no encajan en otras categorías |
| `ciencia_combate` | Ciencia de combate | Estilos basados en tecnología o ciencia |

### 4.3 Stat Primario

Cada estilo declara un `primary_stat` que representa la base física sobre la que se construyen sus técnicas:

| Stat | Slug | Relevancia en el estilo |
|------|------|------------------------|
| Fuerza | `fuerza` | Estilos de potencia bruta, impacto, golpes pesados |
| Resistencia | `resistencia` | Estilos de desgaste, defensa, aguante |
| Agilidad | `agilidad` | Estilos de velocidad, esquivas, golpes rápidos |
| Destreza | `destreza` | Estilos de precisión, técnica fina, control |
| Inteligencia | `inteligencia` | Estilos estratégicos, análisis, planificación |
| Instinto | `instinto` | Estilos reactivos, percepción, anticipación |
| Espíritu | `espiritu` | Estilos de voluntad, Haki, determinación |

El stat primario NO es un requisito de aprendizaje por sí mismo (los requisitos se definen en `requirements`), pero orienta al staff al crear cards del estilo: las técnicas deberían escalar preferentemente con este stat.

### 4.4 Ventajas Narrativas

Las ventajas narrativas (`advantages`) describen qué beneficios obtiene un personaje al dominar el estilo, en términos de contexto narrativo. NO son bonus mecánicos (no afectan dados, stats, ni PV/PE).

Ejemplos de ventajas narrativas:
- "Combate sin penalización narrativa bajo el agua"
- "Bonificación narrativa en esquives acrobáticos y contraataques"
- "Múltiples líneas de ataque en un mismo intercambio"
- "Técnicas que pueden imponer estados de desorientación o apertura"
- "Estilo reconocible que facilita identidad de personaje"

Las ventajas son interpretativas: el staff las tiene en cuenta al evaluar la narrativa de un combate, pero no son reglas mecánicas rígidas.

### 4.5 Formato de Almacenamiento (DB)

En la base de datos, los campos `requirements_json` y `advantages_json` almacenan arrays JSON:

```json
// requirements_json
[
    "Disciplina Cuerpo a Cuerpo grado II o superior",
    "FUE efectiva rango C+ (o narrativa de entrenamiento gyojin)",
    "Raza Gyojin, o aprendiz aceptado por un maestro del estilo"
]

// advantages_json
[
    "Combate sin penalización narrativa bajo el agua",
    "Puños y patadas reciben contexto de daño contundente reforzado en medio acuático",
    "Acceso a técnicas de barrido y rompimiento de defensa marina"
]
```

En runtime, el helper `game_estilos_canonicos_normalize_row()` decodifica ambos campos a arrays PHP:

```php
function game_estilos_canonicos_normalize_row(array $row): array
{
    $req = $row['requirements_json'] ?? '[]';
    $adv = $row['advantages_json'] ?? '[]';
    if (is_string($req)) $req = json_decode($req, true);
    if (is_string($adv)) $adv = json_decode($adv, true);

    return [
        'id' => (int)$row['id'],
        'slug' => (string)$row['slug'],
        'name' => (string)$row['name'],
        'category' => (string)$row['category'],
        'category_label' => (string)$row['category_label'],
        'disciplina_slug' => (string)($row['disciplina_slug'] ?? ''),
        'primary_stat' => (string)($row['primary_stat'] ?? ''),
        'short_desc' => (string)$row['short_desc'],
        'description' => (string)$row['description'],
        'requirements' => is_array($req) ? array_values($req) : [],
        'advantages' => is_array($adv) ? array_values($adv) : [],
        'image_url' => (string)($row['image_url'] ?? ''),
        'sort_order' => (int)($row['sort_order'] ?? 0),
        'is_active' => (int)($row['is_active'] ?? 1) === 1,
    ];
}
```

---

## 5. Sistema de Requisitos Genérico

### 5.1 Estructura de Requisitos

Cada estilo define sus requisitos como un array de strings en `requirements_json`. No hay un esquema fijo de tipos de requisito — el staff los define libremente — pero existen categorías recurrentes que todo estilo debe considerar:

### 5.2 Tipos de Requisitos

#### 5.2.1 Requisito de Disciplina Mínima

**Siempre presente.** Es el requisito fundamental: qué disciplina y qué grado mínimo se necesita.

Formato típico:
```
"Disciplina {Nombre} grado {II|III|IV|V} o superior"
```

Ejemplos:
```
"Disciplina Cuerpo a Cuerpo grado II o superior"
"Disciplina Armas de Filo grado III o superior"
```

Regla general:
- Estilos básicos → grado II (el personaje ya tiene fundamentos)
- Estilos avanzados → grado III (requiere competencia sólida)
- Estilos de maestro → grado IV o V (solo para veteranos)

#### 5.2.2 Requisito de Stat Mínimo

El stat principal del estilo debe estar a cierto rango mínimo.

Formato típico:
```
"{Stat} efectiva rango {C+|B+|A+} o superior"
```

Ejemplos:
```
"FUE efectiva rango C+ o superior"
"AGI o DES efectiva rango C+"
```

Regla general:
- Estilos de nivel de entrada → C+ (rango base decente)
- Estilos intermedios → B+
- Estilos de élite → A+

#### 5.2.3 Requisitos Narrativos (Condición IC)

Son los más importantes y variados. Definen qué debe ocurrir IC para que el personaje pueda aprender el estilo:

| Tipo de condición | Descripción | Ejemplo |
|------------------|-------------|---------|
| **Entrenamiento IC** | El personaje debe haber entrenado con alguien que sepa el estilo | "Entrenamiento IC con instructor del estilo" |
| **Raza** | Solo ciertas razas pueden aprenderlo | "Raza Gyojin, o aprendiz aceptado por un maestro del estilo" |
| **Juramento** | El personaje debe hacer un juramento o promesa | "Juramento IC de no golpear con las manos" |
| **Maestro reconocido** | Debe haber un maestro que lo enseñe | "Aprendiz aceptado por un maestro del estilo" |
| **Rango de facción** | Cierto rango dentro de una facción | "Rango de Agente del Gobierno Mundial o superior" |
| **Arma específica** | Debe poseer un tipo de arma concreta | "Tres armas de filo equipables" |
| **Evento/trama** | Haber participado en un evento específico | "Haber completado el arco de entrenamiento en X" |
| **Condición corporal** | Requisito físico o biológico | "Capacidad de estiramiento o biomecánica especial" |
| **Conocimiento previo** | Requiere otro estilo o disciplina extra | "Dominio de Cuerpo a Cuerpo grado III + Haki de Armamento básico" |

#### 5.2.4 Requisito Opcional de Nivel Global

Algunos estilos avanzados pueden requerir un nivel global mínimo:

```
"Nivel de personaje 3+"
"Nivel de personaje 5+"
```

Esto asegura que solo personajes con cierta trayectoria puedan acceder a estilos particularmente poderosos.

### 5.3 Filosofía de los Requisitos Narrativos

**¿Por qué los estilos no se compran con PP?**

Si los estilos se compraran con PP, cualquier personaje con suficientes puntos podría aprender cualquier estilo sin justificación narrativa. Un Marine raso podría tener un estilo de peces sin haber visto el mar. Un personaje sin entrenamiento IC podría tener técnicas que requieren años de práctica.

Los requisitos narrativos existen para:
1. **Preservar la coherencia del mundo.** No cualquier personaje puede tener cualquier estilo. Cada estilo tiene un origen, una cultura, una tradición.
2. **Crear historias.** El proceso de aprender un estilo es en sí mismo una trama: buscar un maestro, entrenar, demostrar valía.
3. **Dar valor al estilo.** Si cualquier personaje pudiera tener cualquier estilo sin esfuerzo, los estilos perderían su carácter distintivo.
4. **Evitar powergaming.** Los requisitos evitan que un jugador acumule estilos sin justificación.

### 5.4 Validación de Requisitos (PHP)

La validación de requisitos se realiza en el momento en que el staff evalúa la solicitud de aprendizaje. No hay una función automática que valide requisitos genéricos (porque son strings libres), pero el helper puede proporcionar una lista estructurada para que el staff evalúe manualmente:

```php
function game_estilos_canonicos_requirements_display(array $style): array
{
    $items = $style['requirements'] ?? [];
    return is_array($items) ? $items : [];
}
```

Para ciertos requisitos específicos (disciplina mínima, stat mínimo), se recomienda implementar helpers de validación predefinidos:

```php
function game_estilo_canonico_validate_requirements(int $characterId, array $style): array
{
    $errors = [];
    $requirements = $style['requirements'] ?? [];

    foreach ($requirements as $req) {
        // Detectar requisito de disciplina mínimo
        if (preg_match('/Disciplina\s+(.+?)\s+grado\s+(II|III|IV|V)/i', $req, $m)) {
            $discSlug = game_disciplina_name_to_slug(trim($m[1]));
            $minGrado = ['II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5][strtoupper($m[2])] ?? 2;
            $rank = game_disciplina_get_rank($characterId, $discSlug);
            if ($rank < $minGrado) {
                $errors[] = "Requiere {$m[1]} grado {$m[2]} o superior (actual: " . game_grado_label($rank) . ").";
            }
        }
    }
    return $errors;
}
```

Este helper es extensible: el staff puede añadir nuevas detecciones para stat mínimo, nivel, etc.

---

## 6. Relación Estilo-Card

### 6.1 El Campo `estilo_canonico_slug`

Cada card en `game_cards` puede tener opcionalmente un campo `estilo_canonico_slug` que la vincula a un estilo específico:

```sql
estilo_canonico_slug VARCHAR(64) NULL,
KEY idx_estilo_canonico (estilo_canonico_slug)
```

- Si el campo es `NULL` o cadena vacía, la card NO está asociada a ningún estilo. Puede ser solicitada por cualquier personaje que cumpla los demás requisitos (disciplina, oficio, Haki).
- Si el campo tiene un slug válido, la card SOLO puede ser solicitada/asignada a personajes que tengan ese estilo aprendido.

### 6.2 Cards Exclusivas vs Cross-Estilo

**Cards exclusivas de estilo:** Tienen `estilo_canonico_slug` definido. Solo accesibles para personajes con ese estilo.

**Cards genéricas de disciplina:** Tienen `disciplina_slug` pero NO `estilo_canonico_slug`. Accesibles para cualquier personaje con la disciplina suficiente, independientemente de su estilo.

**Cards cross-estilo:** Una card NO puede tener múltiples `estilo_canonico_slug`. Si una técnica es compartida por dos estilos, se crean dos cards separadas o la técnica se define como card genérica de disciplina.

### 6.3 Validación Dual en Asignación

Cuando el staff asigna una card a un personaje, se ejecutan dos validaciones secuenciales:

```php
// 1. Validación de disciplina/oficio (existente en grado_helpers.php)
$compErr = game_card_assignment_competencia_error($characterId, $card);

// 2. Validación de estilo (propuesta — a implementar)
$estiloErr = game_estilo_canonico_card_assignment_error($characterId, $card);

if ($compErr !== null || $estiloErr !== null) {
    // Rechazar asignación
}
```

### 6.4 Validación de Estilo en Cards

Función propuesta para validar que el personaje posea el estilo requerido por la card:

```php
function game_estilo_canonico_card_assignment_error(int $characterId, array $card): ?string
{
    $estiloSlug = trim((string)($card['estilo_canonico_slug'] ?? ''));
    if ($estiloSlug === '') {
        return null; // No requiere estilo, ok
    }
    if (!game_estilo_canonico_character_owns($characterId, $estiloSlug)) {
        $style = game_estilo_canonico_get_by_slug($estiloSlug);
        $name = $style ? $style['name'] : $estiloSlug;
        return 'Requiere el estilo canónico «' . $name . '». El personaje no lo ha aprendido.';
    }
    return null;
}
```

### 6.5 Tier y Rango en Cards de Estilo

Las cards de estilo siguen las mismas reglas de tier que las cards genéricas:

| Tier de card | Rango típico | Disciplina requerida |
|:------------:|:------------:|:--------------------:|
| 1 | D–C | Grado I |
| 2 | C–B | Grado II |
| 3 | B–A | Grado III |
| 4 | A–S | Grado IV |
| 5 | S–SS | Grado V |

Sin embargo, el estilo en sí no tiene grados. Un personaje con el estilo aprendido puede acceder a cualquier card del estilo, siempre que cumpla el requisito de disciplina (grado de la disciplina base, no del estilo).

Ejemplo:
- Card: "Oni Giri" — tier 2, rango B, disciplina `armas_de_filo`, estilo `santoryu`
- Personaje A: Armas de Filo grado II + estilo Santōryū → **puede solicitar**
- Personaje B: Armas de Filo grado III pero NO tiene el estilo → **no puede solicitar** (falta estilo)
- Personaje C: Armas de Filo grado I + estilo Santōryū → **no puede solicitar** (tier 2 requiere grado II)

### 6.6 Cards y `disciplina_slug` en Estilos

Cuando se crea una card de estilo, DEBE llevar también `disciplina_slug` (la disciplina base del estilo). La migración `migrate_estilos_canonicos.php` hace esto automáticamente:

```php
$styleRow = $db->fetch_array($db->query(
    "SELECT disciplina_slug FROM {$prefix}game_estilos_canonicos WHERE slug = '"
    . $db->escape_string($estiloSlug) . "' LIMIT 1"
));
$disciplina = $styleRow ? (string)($styleRow['disciplina_slug'] ?? '') : '';
// ...
'estilo_canonico_slug' => $estiloSlug,
'disciplina_slug' => $disciplina !== '' ? $disciplina : null,
```

Esto asegura que una card de estilo valide AMBOS requisitos: la disciplina base (grado mínimo según tier) y el estilo aprendido.

### 6.7 Cards que NO son Técnicas en Estilos

Aunque la mayoría de las cards de estilo son de tipo `tecnica`, un estilo podría tener cards de otros tipos si el staff lo considera apropiado:

- `equipo`: Armas icónicas del estilo
- `pasiva`: Técnicas pasivas del estilo

El campo `estilo_canonico_slug` funciona igual para cualquier `card_type`. La validación de estilo aplica a todas.

---

## 7. Template para Crear un Nuevo Estilo

### 7.1 JSON Schema del Estilo

Cuando el staff decide crear un nuevo estilo, debe definir la siguiente estructura:

```json
{
    "slug": "nombre_del_estilo",
    "name": "Nombre del Estilo",
    "category": "artes_marciales|esgrima|tirador|estilo_especial|ciencia_combate",
    "category_label": "Artes marciales",
    "disciplina_slug": "cuerpo_a_cuerpo|armas_de_filo|armas_a_distancia|armas_de_fuego|armas_exoticas|armas_de_asta|armas_contundentes",
    "primary_stat": "fuerza|resistencia|agilidad|destreza|inteligencia|instinto|espiritu",
    "short_desc": "Frase de una línea que define el estilo.",
    "description": "Párrafo de 3-5 líneas explicando la filosofía, origen, y técnica del estilo.",
    "requirements": [
        "Disciplina {nombre} grado {II|III|IV} o superior",
        "{Stat} efectiva rango {C+|B+|A+}",
        "Condición narrativa específica"
    ],
    "advantages": [
        "Ventaja narrativa 1",
        "Ventaja narrativa 2",
        "Ventaja narrativa 3"
    ],
    "sort_order": 10,
    "cards": [
        {
            "name": "Nombre de Técnica 1",
            "rank": "C",
            "activation": "activa",
            "description": "Cómo se ejecuta, qué se siente, imagen visual.",
            "cost_pe": "12",
            "dice": "2d20+fue",
            "execution_stat": "fue",
            "effects_json": {
                "tipo_tecnica": "ataque",
                "tipo_daño": "fisico",
                "alcance": "corto",
                "efectos": [],
                "bloqueable": true,
                "esquivable": true
            },
            "tier": 1,
            "reposo": 0,
            "duracion": 0
        }
    ]
}
```

### 7.2 Template Rellenable para Staff

Para facilitar la creación, el staff puede usar esta plantilla markdown:

```
## [Nombre del Estilo]

**Slug:** `slug_del_estilo`
**Categoría:** [artes_marciales|esgrima|tirador|estilo_especial|ciencia_combate]
**Disciplina base:** [slug de la disciplina]
**Stat primario:** [slug del stat]

### Descripción corta
[Una línea]

### Descripción extendida
[3-5 líneas sobre origen, filosofía, técnicas caracteristicas]

### Requisitos
1. [Disciplina y grado mínimo]
2. [Stat y rango mínimo]
3. [Condición narrativa 1]
4. [Condición narrativa 2 (opcional)]

### Ventajas narrativas
1. [Ventaja 1]
2. [Ventaja 2]
3. [Ventaja 3]

### Cartas del estilo

| # | Nombre | Rango | Tier | PE | Dados | Activación | Descripción |
|---|---|---|---|---|---|---|---|
| 1 | [Nom] | C | 1 | 12 | 2d20+fue | activa | ... |
| 2 | [Nom] | B | 2 | 18 | 2d20+fue | activa | ... |
| 3 | [Nom] | A | 3 | 26 | 2d20+fue | activa | ... |
```

### 7.3 Guía de Creación Paso a Paso

**Paso 1: Definir el concepto**
- ¿Qué escuela de combate es? ¿Quién la creó? ¿Dónde se enseña?
- ¿Qué filosofía la distingue de otras?
- ¿Qué tipo de personajes la usarían?

**Paso 2: Elegir disciplina base y stat primario**
- La disciplina debe ser obvia: un estilo de espadas → `armas_de_filo`
- El stat primario debe reflejar la esencia: fuerza para potencia, destreza para precisión, agilidad para velocidad

**Paso 3: Redactar requisitos (3-4)**
- Siempre incluir: disciplina mínima (grado II para básicos, III+ para avanzados)
- Siempre incluir: stat mínimo (C+ para básicos, B+ para avanzados)
- Siempre incluir: al menos una condición narrativa (raza, entrenamiento, juramento, maestro)

**Paso 4: Redactar ventajas narrativas (3-4)**
- NO son bonus mecánicos. Son contextos donde el estilo brilla.
- Ejemplos: "ventaja en X situación", "capacidad de Y", "reconocimiento como Z"

**Paso 5: Diseñar las cartas del estilo**
- Mínimo 3 cartas: 1 rango C, 1 rango B, 1 rango A. Opcional: 1 rango S.
- Las técnicas deben ser coherentes con la filosofía del estilo.
- Los efectos deben ser ejecutables sin ambigüedad por un árbitro.
- Los dados deben escalar con el stat primario.

**Paso 6: Registrar en base de datos**
- Añadir el estilo como nueva fila en `game_estilos_canonicos`.
- Crear las cards en `game_cards` con `estilo_canonico_slug` vinculado.
- O usar la migración `estilos_canonicos_seed_data.php` como referencia.

---

## 8. Modelo de Datos

### 8.1 `game_estilos_canonicos` — Catálogo Maestro de Estilos

```sql
CREATE TABLE mybb_game_estilos_canonicos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    slug            VARCHAR(64) NOT NULL,
    name            VARCHAR(150) NOT NULL,
    category        VARCHAR(32) NOT NULL DEFAULT 'artes_marciales',
    category_label  VARCHAR(100) NOT NULL,
    disciplina_slug VARCHAR(64) NULL,
    primary_stat    VARCHAR(32) NOT NULL DEFAULT '',
    short_desc      TEXT NOT NULL,
    description     TEXT NOT NULL,
    requirements_json TEXT NOT NULL,
    advantages_json TEXT NOT NULL,
    image_url       VARCHAR(500) NOT NULL DEFAULT '',
    sort_order      INT NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slug (slug),
    KEY idx_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Campos:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT AUTO_INCREMENT | Clave primaria |
| `slug` | VARCHAR(64) | Identificador URL-friendly. UNIQUE. Ej: `estilo_ejemplo` |
| `name` | VARCHAR(150) | Nombre visible del estilo |
| `category` | VARCHAR(32) | Categoría de agrupación (slug) |
| `category_label` | VARCHAR(100) | Etiqueta legible de la categoría |
| `disciplina_slug` | VARCHAR(64) NULL | FK lógica a `game_disciplinas.slug`. Disciplina base |
| `primary_stat` | VARCHAR(32) | Stat principal que escala las técnicas |
| `short_desc` | TEXT | Descripción corta (1 frase) |
| `description` | TEXT | Descripción extendida (3-5 líneas) |
| `requirements_json` | TEXT | JSON array de requisitos |
| `advantages_json` | TEXT | JSON array de ventajas narrativas |
| `image_url` | VARCHAR(500) | URL de imagen representativa |
| `sort_order` | INT | Orden de visualización (múltiplos de 10) |
| `is_active` | TINYINT(1) | Si está disponible en el catálogo público |
| `created_at` | TIMESTAMP | Fecha de creación |
| `updated_at` | TIMESTAMP | Fecha de última modificación |

### 8.2 `game_character_estilos` — Estilos Aprendidos por Personaje

```sql
CREATE TABLE mybb_game_character_estilos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    character_id    INT NOT NULL,
    estilo_slug     VARCHAR(64) NOT NULL,
    learned_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    granted_by      INT NOT NULL,
    UNIQUE KEY uq_char_estilo (character_id, estilo_slug),
    KEY idx_character (character_id),
    KEY idx_estilo (estilo_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Campos:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT AUTO_INCREMENT | Clave primaria |
| `character_id` | INT | FK lógica a `game_personajes.id` |
| `estilo_slug` | VARCHAR(64) | Slug del estilo aprendido. FK lógica a `game_estilos_canonicos.slug` |
| `learned_at` | TIMESTAMP | Cuándo se aprendió el estilo |
| `granted_by` | INT | FK lógica a `mybb_users.uid`. Staff que aprobó el aprendizaje |

**UNIQUE KEY `uq_char_estilo`:** Un personaje no puede aprender el mismo estilo dos veces. Cada estilo aparece una vez por personaje.

### 8.3 `game_cards` — Campo `estilo_canonico_slug`

La columna se añadió mediante migración:

```sql
ALTER TABLE mybb_game_cards
    ADD COLUMN estilo_canonico_slug VARCHAR(64) NULL AFTER disciplina_slug,
    ADD KEY idx_estilo_canonico (estilo_canonico_slug);
```

**Comportamiento:**
- `NULL` o `''`: La card no requiere estilo. Validación normal (disciplina/oficio/Haki).
- Slug válido: La card requiere que el personaje tenga ese estilo aprendido. Se valida en asignación.

### 8.4 Integridad Referencial

Las relaciones entre tablas son **FKs lógicas** (no hay constraints formales de FOREIGN KEY en MySQL):

```
game_estilos_canonicos.disciplina_slug
    → game_disciplinas.slug (FK lógica)

game_cards.estilo_canonico_slug
    → game_estilos_canonicos.slug (FK lógica)

game_character_estilos.estilo_slug
    → game_estilos_canonicos.slug (FK lógica)

game_character_estilos.character_id
    → game_personajes.id (FK lógica)
```

**¿Por qué FKs lógicas y no formales?**
- MyBB usa múltiples prefijos de tabla. Las FKs formales complican migraciones y backups.
- La validación de integridad se hace en PHP, no en MySQL.
- Las FKs lógicas permiten borrar y recrear tablas en migraciones sin preocuparse por dependencias circulares.

### 8.5 Seed Data

La migración `migrate_estilos_canonicos.php` inserta datos iniciales desde `estilos_canonicos_seed_data.php`. La estructura del array de seed:

```php
function game_estilos_canonicos_seed_data(): array
{
    $estilos = [
        [
            'slug' => 'estilo_ejemplo',
            'name' => 'Estilo Ejemplo',
            'category' => 'artes_marciales',
            'category_label' => 'Artes marciales',
            'disciplina_slug' => 'cuerpo_a_cuerpo',
            'primary_stat' => 'fuerza',
            'short_desc' => 'Descripción corta.',
            'description' => 'Descripción extendida de 3-5 líneas...',
            'requirements' => [
                'Disciplina X grado II o superior',
                'Stat rango C+',
                'Condición narrativa',
            ],
            'advantages' => [
                'Ventaja narrativa 1',
                'Ventaja narrativa 2',
                'Ventaja narrativa 3',
            ],
            'sort_order' => 10,
        ],
    ];

    $cartas = [
        [
            'estilo' => 'estilo_ejemplo',
            'name' => 'Técnica Ejemplo',
            'rank' => 'C',
            'dice' => '2d20+fue',
            'cost_pe' => '12',
            'description' => 'Descripción de la técnica.',
        ],
    ];

    return ['estilos' => $estilos, 'cartas' => $cartas];
}
```

---

## 9. PHP — Servicios y Helpers

### 9.1 `estilos_canonicos_helpers.php`

Archivo: `back/forum/game/inc/estilos_canonicos_helpers.php`

Es el núcleo del subsistema de estilos. Contiene las siguientes funciones:

| Función | Propósito | Devuelve |
|---------|-----------|----------|
| `game_estilos_canonicos_list(bool $activeOnly)` | Lista todos los estilos del catálogo | `list<array>` |
| `game_estilos_canonicos_normalize_row(array $row)` | Normaliza una fila de DB (decodifica JSON) | `array` |
| `game_estilos_canonicos_cards_for_slug(string $slug)` | Cards técnicas de un estilo específico | `list<array>` |
| `game_estilos_canonicos_cards_by_slug()` | Todas las cards de todos los estilos, agrupadas | `array<string, list<array>>` |
| `game_estilos_canonicos_requirements_display(array $style)` | Requisitos del estilo para mostrar | `array` |
| `game_estilos_canonicos_advantages_display(array $style)` | Ventajas del estilo para mostrar | `array` |

#### 9.1.1 `game_estilos_canonicos_list()`

```php
function game_estilos_canonicos_list(bool $activeOnly = true): array
{
    global $db;
    if (!$db->table_exists('game_estilos_canonicos')) {
        return [];
    }
    $prefix = TABLE_PREFIX;
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    $q = $db->query("SELECT * FROM {$prefix}game_estilos_canonicos {$where} ORDER BY sort_order ASC, name ASC");
    $out = [];
    while ($row = $db->fetch_array($q)) {
        $out[] = game_estilos_canonicos_normalize_row($row);
    }
    return $out;
}
```

**Uso:** Biblioteca pública de estilos (`estilos.php`), panel de personaje, AJAX de consulta.

#### 9.1.2 `game_estilos_canonicos_cards_for_slug()`

```php
function game_estilos_canonicos_cards_for_slug(string $slug): array
{
    global $db;
    if ($slug === '' || !$db->table_exists('game_cards') || !$db->field_exists('estilo_canonico_slug', 'game_cards')) {
        return [];
    }
    $prefix = TABLE_PREFIX;
    $esc = $db->escape_string($slug);
    $q = $db->query("SELECT id, name, `rank`, card_type, dice, cost_pe, description, activation
        FROM {$prefix}game_cards
        WHERE estilo_canonico_slug = '{$esc}' AND card_type = 'tecnica'
        ORDER BY FIELD(`rank`, 'D','C','B','A','S','SS'), name ASC");
    $out = [];
    while ($row = $db->fetch_array($q)) {
        $out[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'rank' => (string)$row['rank'],
            'dice' => (string)($row['dice'] ?? ''),
            'cost_pe' => (string)($row['cost_pe'] ?? '—'),
            'description' => (string)($row['description'] ?? ''),
            'activation' => (string)($row['activation'] ?? 'activa'),
        ];
    }
    return $out;
}
```

**Uso:** Detalle de un estilo en la biblioteca (muestra las cards asociadas).

#### 9.1.3 `game_estilos_canonicos_cards_by_slug()`

```php
function game_estilos_canonicos_cards_by_slug(): array
{
    global $db;
    $map = [];
    if (!$db->table_exists('game_cards') || !$db->field_exists('estilo_canonico_slug', 'game_cards')) {
        return $map;
    }
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT id, name, `rank`, dice, cost_pe, description, activation, estilo_canonico_slug
        FROM {$prefix}game_cards
        WHERE estilo_canonico_slug IS NOT NULL AND estilo_canonico_slug != '' AND card_type = 'tecnica'
        ORDER BY estilo_canonico_slug, FIELD(`rank`, 'D','C','B','A','S','SS'), name ASC");
    while ($row = $db->fetch_array($q)) {
        $slug = (string)$row['estilo_canonico_slug'];
        $map[$slug][] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'rank' => (string)$row['rank'],
            'dice' => (string)($row['dice'] ?? ''),
            'cost_pe' => (string)($row['cost_pe'] ?? '—'),
            'description' => (string)($row['description'] ?? ''),
            'activation' => (string)($row['activation'] ?? 'activa'),
        ];
    }
    return $map;
}
```

**Uso:** Render de la biblioteca completa (datos agrupados para enviar al frontend).

### 9.2 Funciones Propuestas (a Implementar)

Las siguientes funciones son necesarias para completar el subsistema pero no existen actualmente en la base de código. Se documentan aquí como especificación para implementación futura:

#### 9.2.1 `game_estilo_canonico_get_by_slug()`

```php
function game_estilo_canonico_get_by_slug(string $slug): ?array
{
    global $db;
    if ($slug === '' || !$db->table_exists('game_estilos_canonicos')) {
        return null;
    }
    $prefix = TABLE_PREFIX;
    $esc = $db->escape_string($slug);
    $q = $db->query("SELECT * FROM {$prefix}game_estilos_canonicos WHERE slug = '{$esc}' LIMIT 1");
    $row = $db->fetch_array($q);
    return $row ? game_estilos_canonicos_normalize_row($row) : null;
}
```

#### 9.2.2 `game_estilo_canonico_character_owns()`

```php
function game_estilo_canonico_character_owns(int $characterId, string $estiloSlug): bool
{
    global $db;
    if ($estiloSlug === '' || !$db->table_exists('game_character_estilos')) {
        return false;
    }
    $prefix = TABLE_PREFIX;
    $esc = $db->escape_string($estiloSlug);
    $q = $db->query("SELECT id FROM {$prefix}game_character_estilos
        WHERE character_id = {$characterId} AND estilo_slug = '{$esc}' LIMIT 1");
    return (bool)$db->fetch_array($q);
}
```

#### 9.2.3 `game_estilo_canonico_list_for_character()`

```php
function game_estilo_canonico_list_for_character(int $characterId): array
{
    global $db;
    if (!$db->table_exists('game_character_estilos')) {
        return [];
    }
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT e.*, ce.learned_at, ce.granted_by
        FROM {$prefix}game_estilos_canonicos e
        INNER JOIN {$prefix}game_character_estilos ce ON e.slug = ce.estilo_slug
        WHERE ce.character_id = {$characterId}
        ORDER BY e.sort_order ASC, e.name ASC");
    $out = [];
    while ($row = $db->fetch_array($q)) {
        $out[] = game_estilos_canonicos_normalize_row($row);
    }
    return $out;
}
```

#### 9.2.4 `game_estilo_canonico_grant()`

```php
function game_estilo_canonico_grant(int $characterId, string $estiloSlug, int $staffUid): ?string
{
    global $db;

    // Validar que el estilo existe
    $style = game_estilo_canonico_get_by_slug($estiloSlug);
    if (!$style) {
        return 'El estilo no existe.';
    }

    // Validar que el personaje no lo tiene ya
    if (game_estilo_canonico_character_owns($characterId, $estiloSlug)) {
        return 'El personaje ya ha aprendido este estilo.';
    }

    // Insertar
    $prefix = TABLE_PREFIX;
    $escSlug = $db->escape_string($estiloSlug);
    $db->insert_query('game_character_estilos', [
        'character_id' => $characterId,
        'estilo_slug' => $escSlug,
        'granted_by' => $staffUid,
    ]);

    return null; // éxito
}
```

#### 9.2.5 `game_estilo_canonico_card_assignment_error()`

```php
function game_estilo_canonico_card_assignment_error(int $characterId, array $card): ?string
{
    $estiloSlug = trim((string)($card['estilo_canonico_slug'] ?? ''));
    if ($estiloSlug === '') {
        return null;
    }
    if (!game_estilo_canonico_character_owns($characterId, $estiloSlug)) {
        $style = game_estilo_canonico_get_by_slug($estiloSlug);
        $name = $style ? $style['name'] : $estiloSlug;
        return 'Requiere el estilo canónico «' . $name . '». El personaje no lo ha aprendido.';
    }
    return null;
}
```

### 9.3 Integración con `grado_helpers.php`

La función `game_card_assignment_competencia_error()` en `grado_helpers.php` actualmente valida `disciplina_slug` y `oficio_slug`, pero NO `estilo_canonico_slug`. Se recomienda ampliarla para incluir la validación de estilo:

```php
function game_card_assignment_competencia_error(int $characterId, array $card): ?string
{
    // Validación de disciplina (existente)
    $tier = max(1, min(5, (int)($card['tier'] ?? 1)));
    $discSlug = trim((string)($card['disciplina_slug'] ?? ''));
    if ($discSlug !== '') {
        $rank = game_disciplina_get_rank($characterId, $discSlug);
        if ($rank < $tier) {
            return 'Requiere disciplina «' . $discSlug . '» grado '
                . game_grado_label($tier) . ' o superior (actual: '
                . ($rank > 0 ? game_grado_label($rank) : 'ninguno') . ').';
        }
    }

    // Validación de oficio (existente)
    $ofSlug = trim((string)($card['oficio_slug'] ?? ''));
    if ($ofSlug !== '') {
        if (game_oficio_get_rank($characterId, $ofSlug) < 1) {
            return 'Requiere oficio «' . $ofSlug . '».';
        }
    }

    // Validación de estilo canónico (propuesta)
    $estiloErr = game_estilo_canonico_card_assignment_error($characterId, $card);
    if ($estiloErr !== null) {
        return $estiloErr;
    }

    return null;
}
```

### 9.4 Bootstrap

El archivo `bootstrap.php` ya carga los helpers de estilos:

```php
require_once __DIR__ . '/inc/estilos_canonicos_helpers.php';
```

---

## 10. Flujo de Aprendizaje de un Estilo

### 10.1 Diagrama de Secuencia

```
Jugador → Staff → Sistema → DB

1. Jugador investiga estilos en la biblioteca pública (estilos.php)
2. Jugador encuentra un estilo que quiere para su personaje
3. Jugador contacta al staff (MP, ticket, o solicitud formal)
4. Staff evalúa:
   a. ¿El personaje cumple los requisitos? (disciplina, stat, narrativa)
   b. ¿Hay justificación IC? (entrenamiento, trama, maestro)
   c. ¿El estilo es apropiado para el personaje y el foro?
5. Staff aprueba → ejecuta script/panel para otorgar el estilo
6. Se inserta registro en game_character_estilos
7. El personaje ahora puede solicitar cards del estilo
8. Staff asigna las cards técnicas correspondientes
```

### 10.2 Precondiciones

1. El personaje debe estar **aprobado** (status = `aprobada`).
2. El estilo debe estar **activo** en el catálogo (`is_active = 1`).
3. El personaje NO debe tener ya el estilo aprendido.
4. El personaje debe cumplir los requisitos del estilo.

### 10.3 Validación Staff-Side

Cuando el staff evalúa una solicitud de aprendizaje, debe verificar:

```php
// Pseudocódigo del panel staff para otorgar estilo
function staff_validate_estilo_aprendizaje(int $characterId, string $estiloSlug): array
{
    $errors = [];

    // 1. Obtener estilo
    $style = game_estilo_canonico_get_by_slug($estiloSlug);
    if (!$style) {
        $errors[] = 'El estilo no existe.';
        return $errors;
    }

    // 2. Verificar que el personaje no lo tenga ya
    if (game_estilo_canonico_character_owns($characterId, $estiloSlug)) {
        $errors[] = 'El personaje ya ha aprendido este estilo.';
    }

    // 3. Verificar disciplina base
    $discSlug = $style['disciplina_slug'];
    if ($discSlug !== '') {
        // Buscar requisito de grado mínimo en el texto
        foreach ($style['requirements'] as $req) {
            if (preg_match('/grado\s+(II|III|IV|V)/i', $req, $m)) {
                $minRank = ['II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5][strtoupper($m[1])] ?? 2;
                $currentRank = game_disciplina_get_rank($characterId, $discSlug);
                if ($currentRank < $minRank) {
                    $errors[] = "Requiere {$discSlug} grado {$m[1]} (actual: " . game_grado_label($currentRank) . ").";
                }
                break;
            }
        }
    }

    // 4. Validar condiciones narrativas
    // (Esto es manual — el staff lee los requisitos y evalúa IC)

    return $errors;
}
```

### 10.4 Ejecución: Otorgar el Estilo

Si la validación pasa, el staff ejecuta la concesión:

```php
function staff_grant_estilo(int $characterId, string $estiloSlug, int $staffUid): array
{
    $error = game_estilo_canonico_grant($characterId, $estiloSlug, $staffUid);
    if ($error !== null) {
        return ['ok' => false, 'message' => $error];
    }

    // Notificar al jugador
    game_create_notification(
        $playerUid,
        'estilo_aprendido',
        'Estilo canónico aprendido',
        '¡Tu personaje ha aprendido el estilo «' . $styleName . '»! Ya puedes solicitar sus cartas técnicas.'
    );

    return ['ok' => true, 'message' => 'Estilo otorgado correctamente.'];
}
```

### 10.5 Postcondiciones

- Registro en `game_character_estilos` con `character_id`, `estilo_slug`, `granted_by`.
- El personaje aparece en listados de "personajes con estilo X".
- Las cards con `estilo_canonico_slug` = este estilo ahora son solicitables.
- El jugador recibe notificación.

### 10.6 Remover un Estilo

En casos excepcionales (cambio de personaje, retcon, abandono del estilo en trama), el staff puede remover un estilo:

```php
function game_estilo_canonico_remove_from_character(int $characterId, string $estiloSlug): void
{
    global $db;
    $prefix = TABLE_PREFIX;
    $esc = $db->escape_string($estiloSlug);
    $db->write_query("DELETE FROM {$prefix}game_character_estilos
        WHERE character_id = {$characterId} AND estilo_slug = '{$esc}'");
}
```

**Nota:** Remover un estilo NO elimina automaticamente las cards asociadas. El staff debe decidir si las cards del estilo se mantienen (el personaje conserva las técnicas aunque ya no practique el estilo) o se remueven también.

---

## 11. Flujo de Validación de Cards por Estilo

### 11.1 Proceso Completo de Asignación de una Card

Cuando el staff asigna una card a un personaje (vía `cards_assign.php`), el flujo completo de validación es:

```
1. La card existe en game_cards?

2. [Si es akuma_no_mi]: Validación de fruta disponible
   → game_akuma_assignment_error()

3. Validación de competencia (disciplina/oficio/estilo):
   → game_card_assignment_competencia_error()
     a. Si tiene disciplina_slug: validar grado mínimo (tier)
     b. Si tiene oficio_slug: validar que posee el oficio
     c. Si tiene estilo_canonico_slug: validar que posee el estilo
        → game_estilo_canonico_card_assignment_error()

4. [Si es haki]: Validación de nivel de Haki
   → Consulta game_haki_progress

5. Asignación exitosa:
   → INSERT/UPDATE en game_character_cards
```

### 11.2 Filtro en Solicitudes de Jugador

Cuando un jugador solicita una card (vía `cards_request_custom.php` o `cards_request.php`), el sistema debe filtrar qué cards del catálogo son solicitables. Las cards con `estilo_canonico_slug` deben aparecer SOLO si el personaje tiene ese estilo:

```php
function game_cards_catalog_for_character(int $characterId): array
{
    global $db;
    $prefix = TABLE_PREFIX;

    $q = $db->query("SELECT c.* FROM {$prefix}game_cards c
        WHERE c.is_active = 1
        ORDER BY c.card_type, c.rank, c.name");

    $catalog = [];
    while ($card = $db->fetch_array($q)) {
        // Filtrar cards de estilo si el PJ no tiene el estilo
        $estiloSlug = trim((string)($card['estilo_canonico_slug'] ?? ''));
        if ($estiloSlug !== '') {
            if (!game_estilo_canonico_character_owns($characterId, $estiloSlug)) {
                continue; // No mostrar esta card
            }
        }
        // También validar disciplina/oficio básico
        $compErr = game_card_assignment_competencia_error($characterId, $card);
        if ($compErr !== null) {
            continue; // No cumple requisitos
        }
        $catalog[] = $card;
    }

    return $catalog;
}
```

### 11.3 Cards Asignables por Staff

El staff puede asignar cualquier card a cualquier personaje (override de validaciones). Sin embargo, el sistema DEBE advertir si la card requiere un estilo que el personaje no tiene:

```php
function staff_card_assignment_warnings(int $characterId, array $card): array
{
    $warnings = [];

    $estiloSlug = trim((string)($card['estilo_canonico_slug'] ?? ''));
    if ($estiloSlug !== '' && !game_estilo_canonico_character_owns($characterId, $estiloSlug)) {
        $style = game_estilo_canonico_get_by_slug($estiloSlug);
        $name = $style ? $style['name'] : $estiloSlug;
        $warnings[] = "El personaje no tiene el estilo «{$name}». ¿Confirmas la asignación?";
    }

    return $warnings;
}
```

Esto permite al staff asignar cards de estilo como parte del proceso de aprendizaje (primero se otorga el estilo, luego las cards), o en casos excepcionales donde el estilo se otorga retroactivamente.

---

## 12. Biblioteca Pública de Estilos

### 12.1 Página de Biblioteca

Archivo: `back/forum/game/public/estilos.php`

Es una página pública (no requiere login) que muestra el catálogo completo de estilos canónicos. Cualquier visitante puede consultar:

- Lista de estilos con nombre, categoría, descripción corta
- Detalle de cada estilo (requisitos, ventajas, cards asociadas)
- Filtros por categoría y stat primario

### 12.2 Estructura del Frontend

```
estilos.php
├── Header (título, descripción)
├── Sidebar (filtros)
│   ├── Búsqueda por nombre
│   ├── Checkbox de categorías
│   └── Checkbox de stat primario
├── Grid de estilos (rpg-lib-grid)
│   └── Card por estilo (.rpg-lib-card)
│       ├── Imagen de fondo
│       ├── Badge de categoría
│       ├── Nombre
│       ├── Descripción corta
│       └── Stats (cantidad de cards, disciplina base)
└── Modal de detalle (.rpg-lib-modal)
    ├── Nombre + categoría
    ├── Descripción extendida
    ├── Lista de requisitos
    ├── Lista de ventajas
    └── Lista de cards del estilo
```

### 12.3 Datos Embebidos

Cada card del grid lleva todos los datos del estilo embebidos como atributos `data-*`:

```html
<div class="rpg-lib-card"
    data-slug="estilo_ejemplo"
    data-name="Estilo Ejemplo"
    data-type="artes_marciales"
    data-req="fuerza"
    data-desc="Breve descripción..."
    data-details="Descripción extendida..."
    data-img="https://..."
    data-disciplina="cuerpo_a_cuerpo"
    data-requirements='["Req 1", "Req 2"]'
    data-advantages='["Adv 1", "Adv 2"]'
    data-cartas='[{"name":"Técnica 1","rank":"C",...}]'>
```

Esto permite que `estilos.js` renderice el modal sin llamadas AJAX adicionales.

### 12.4 JavaScript: `estilos.js`

Archivo: `back/forum/jscripts/game/estilos.js`

**Funcionalidades:**
- Filtro en vivo por nombre de estilo (input search)
- Filtro por categoría (checkboxes)
- Filtro por stat primario (checkboxes)
- Modal de detalle con requisitos, ventajas y cards
- Render dinámico de lista de técnicas con rango, PE, dados y descripción

### 12.5 API Endpoints Propuestos

Para consultas desde la ficha de personaje o desde el panel de solicitud de cards:

```php
// GET /game/ajax/estilos_list.php
// Params: character_id (opcional)
// Si character_id se provee, incluir campo "aprendido": true/false
// Response:
{
    "ok": true,
    "data": [
        {
            "slug": "estilo_ejemplo",
            "name": "Estilo Ejemplo",
            "category_label": "Artes marciales",
            "disciplina_slug": "cuerpo_a_cuerpo",
            "primary_stat": "fuerza",
            "short_desc": "...",
            "aprendido": true,
            "cards_count": 3
        }
    ]
}
```

```php
// GET /game/ajax/estilo_detail.php?slug=estilo_ejemplo
// Response:
{
    "ok": true,
    "data": {
        "slug": "estilo_ejemplo",
        "name": "Estilo Ejemplo",
        "description": "...",
        "requirements": ["..."],
        "advantages": ["..."],
        "cards": [
            {"id": 1, "name": "...", "rank": "C", ...}
        ]
    }
}
```

---

## 13. Consejos para Staff: Crear Estilos Balanceados

### 13.1 Filosofía de Balance

Los estilos canónicos NO son power-ups. No deberían hacer que un personaje sea objetivamente más fuerte que otro con la misma disciplina pero sin estilo. En cambio, deberían:

1. **Dar identidad temática.** El estilo distingue visual y narrativamente a un personaje.
2. **Ofrecer ventajas contextuales.** El estilo brilla en ciertas situaciones, no en todas.
3. **Crear diversidad mecánica.** Dos personajes con la misma disciplina pero distintos estilos juegan diferente.

### 13.2 Directrices de Poder Relativo

| Nivel del estilo | Grado mínimo de disciplina | Rango de cards | Poder relativo |
|------------------|:--------------------------:|:--------------:|:--------------:|
| Básico | II | C | Comparable a cards genéricas del mismo tier |
| Intermedio | III | C–B | Ligeramente por encima de genéricas (por la especialización) |
| Avanzado | IV | B–A | Notablemente especializado |
| Maestro | V | A–S | Élite, requiere justificación narrativa importante |

**Regla de oro:** Una card de estilo de rango B no debería ser objetivamente mejor que una card genérica de rango B de la misma disciplina. Debería ser *diferente*, no *superior*.

### 13.3 Cómo Escribir Cards de Estilo Efectivas

**Cada card debe:**
1. **Reflejar la filosofía del estilo.** Una técnica de un estilo de fuerza bruta debe sentirse pesada y poderosa. Una de un estilo de precisión debe sentirse rápida y certera.
2. **Tener un uso claro.** El jugador debe saber exactamente para qué sirve la técnica: ¿ataque? ¿defensa? ¿movilidad? ¿control?
3. **Ser ejecutable sin ambigüedad.** Un árbitro de moderación debe poder determinar si la técnica se usó correctamente leyendo el post. Nada de "depende del contexto".
4. **Tener un coste proporcional.** Los dados y el coste PE deben ser coherentes con el rango de la card. Una card rango C con dados de rango A está rota.
5. **Incluir restricciones claras.** Si la técnica solo funciona en ciertas condiciones (agua, aire, corta distancia), debe estar explicitado en `description` o `effects_json`.

### 13.4 Asegurando Coherencia Temática

- **El nombre de la técnica debe evocar el estilo.** Evitar nombres genéricos si el estilo tiene una identidad marcada.
- **La descripción debe mencionar movimientos característicos del estilo.** Postura, respiración, desplazamiento.
- **Las técnicas deben encadenarse narrativamente.** Un estilo debería tener "combos" o secuencias lógicas entre sus técnicas.
- **El estilo no debe contradecir el lore del mundo.** Si el estilo existe, debe tener un origen y una razón de ser en el mundo del foro.

### 13.5 Evitando Solapamiento entre Estilos

**Dos estilos de la misma disciplina deben tener identidades distintas:**

| Mal | Bien |
|-----|------|
| Dos estilos de `cuerpo_a_cuerpo` que son "puñetazos fuertes" | Un estilo de `cuerpo_a_cuerpo` enfocado en golpes potentes y otro en patadas rápidas |
| Dos estilos de `armas_de_filo` con técnicas de corte similares | Un estilo de una espada (precisión) y otro de tres espadas (cobertura de ángulos) |
| Dos estilos con el mismo stat primario y misma filosofía | Cada estilo tiene un nicho claro que lo diferencia |

**Para evitar solapamiento:**
1. Consultar la lista de estilos existentes antes de crear uno nuevo.
2. Preguntarse: "¿qué ofrece este estilo que ningún otro ofrece?"
3. Si dos estilos son demasiado similares, considerar fusionarlos o descartar uno.
4. Usar categorías y stats primarios distintos para diferenciar.

### 13.6 Cards Mínimas por Estilo

Cada estilo debe tener un mínimo de **3 cards técnicas**:

| # | Rango | Tier | Propósito |
|---|:-----:|:----:|-----------|
| 1 | C | 1 | Técnica básica, el "golpe característico" del estilo. Bajo coste PE. |
| 2 | B | 2 | Técnica intermedia, más poderosa o con efecto adicional. |
| 3 | A | 3 | Técnica avanzada, el "ataque especial". Coste PE alto. |
| 4 (opcional) | S | 4 | Técnica suprema, difícil de dominar. Solo para estilos de élite. |

**Distribución recomendada de dados y costes:**
| Rango | Dados típicos | Coste PE típico |
|:-----:|:-------------:|:----------------:|
| C | `2d20+stat` | 8–15 |
| B | `2d20+stat` | 16–22 |
| A | `2d20+stat` o `3d20+stat` | 22–30 |
| S | `3d20+stat` o superior | 30–50 |

### 13.7 Proceso de Testing e Iteración

1. **Crear el estilo en papel** — Definir concepto, requisitos, ventajas, cards.
2. **Revisión interna del staff** — ¿Está balanceado? ¿Es coherente? ¿No solapa con otro estilo?
3. **Registro en DB** — Insertar en `game_estilos_canonicos` y crear cards.
4. **Asignación a un personaje de prueba** — Usar un NPC o personaje staff para probar.
5. **Test en combate de prueba** — Simular un combate usando las cards del estilo.
6. **Ajustes** — Modificar dados, costes, o descripciones según resultados.
7. **Publicación** — Marcar como activo para que los jugadores puedan solicitarlo.
8. **Iteración post-lanzamiento** — Recibir feedback y ajustar si es necesario.

### 13.8 Estilos Prohibidos o Restringidos

Ciertos estilos pueden requerir restricciones adicionales:

- **Estilos de facción exclusiva.** Solo accesibles para miembros de cierta facción (Gobierno Mundial, Marines, Revolucionarios).
- **Estilos de raza exclusiva.** Solo ciertas razas pueden aprenderlos.
- **Estilos de evento.** Solo disponibles durante un arco narrativo específico.
- **Estilos de staff.** Solo para personajes staff o NPCs mayores.

Estas restricciones se documentan en los requisitos del estilo y se validan manualmente por el staff.

---

## 14. Consejos para Jugadores

### 14.1 Cómo Elegir un Estilo

**Factores a considerar:**

1. **Sinergia con tu disciplina.** Si tu personaje tiene Cuerpo a Cuerpo grado III, busca estilos de esa disciplina. No tiene sentido buscar un estilo de armas de filo si tu personaje nunca ha empuñado una espada.

2. **Coherencia narrativa.** El estilo debe tener sentido con la historia de tu personaje. ¿Dónde lo aprendió? ¿Con quién entrenó? ¿Por qué eligió ese estilo y no otro?

3. **Disponibilidad de cards.** Revisa cuántas cards tiene el estilo y si las técnicas se alinean con tu visión del personaje. Un estilo con 3 técnicas puede ser limitado; uno con 6+ ofrece más versatilidad.

4. **Stat primario.** Elige un estilo cuyo stat primario sea fuerte en tu personaje. Si tu personaje tiene DES alta, busca estilos de destreza.

5. **Nicho.** ¿Tu personaje es un tanque? Busca estilos defensivos. ¿DPS? Estilos ofensivos. ¿Soporte? Estilos de control o utilidad.

### 14.2 Compromiso al Aprender un Estilo

Aprender un estilo canónico es un **compromiso narrativo**. No es como comprar una disciplina con PP. Considera:

- **Tu personaje será identificado con ese estilo.** Así como Zoro es "el espadachín de tres espadas", tu personaje será "el practicante de X estilo".
- **Requiere justificación IC.** Necesitarás posts de entrenamiento, búsqueda de maestro, o participación en tramas relacionadas.
- **No puedes "desaprenderlo" fácilmente.** Una vez aprendido, el estilo es parte de tu personaje. Solo el staff puede removerlo en circunstancias excepcionales.

### 14.3 Cómo Solicitar un Estilo

1. **Investiga.** Lee la biblioteca de estilos (`estilos.php`). Encuentra uno que encaje con tu personaje.
2. **Rolea el entrenamiento.** Haz posts IC donde tu personaje busca información, encuentra un maestro, o comienza a entrenar.
3. **Solicita al staff.** Envía un MP o ticket con:
   - El estilo que quieres y por qué.
   - Enlaces a los posts de entrenamiento.
   - Confirmación de que cumples los requisitos.
4. **Espera la revisión.** El staff evaluará si tu solicitud es coherente y si has roleado lo suficiente.
5. **Recibe el estilo.** Si el staff aprueba, el estilo se añade a tu personaje.
6. **Solicita las cards.** Ahora puedes solicitar las cards técnicas del estilo mediante el sistema de solicitud de cards.

### 14.4 Combinación de Múltiples Estilos

Un personaje puede aprender múltiples estilos, pero cada uno requiere su propia trama y justificación IC. Considera:

- **Dos estilos de la misma disciplina.** Puede ser redundante. Dos estilos de `cuerpo_a_cuerpo` compiten por el mismo "espacio" narrativo.
- **Estilos de distintas disciplinas.** Más viable, pero requiere dominar ambas disciplinas. Ej: un personaje con Cuerpo a Cuerpo y Armas de Filo puede tener un estilo marcial y otro de espada.
- **Sinergia entre estilos.** Algunos estilos pueden complementarse. Ej: un estilo de precisión + un estilo de movilidad.

### 14.5 Qué Esperar al Usar un Estilo en Combate

- **Las cards del estilo son tus herramientas.** Úsalas creativamente, pero respeta sus limitaciones.
- **Las ventajas narrativas son contextuales.** No esperes bonus mecánicos automáticos. Las ventajas son argumentos narrativos que el staff considera al evaluar tu post.
- **Un estilo no te hace invencible.** Tener un estilo avanzado no significa que puedas ignorar las reglas del combate o los stats del rival.
- **La coherencia importa.** Si tu personaje tiene un estilo de patadas, no debería usar técnicas de puños. Respeta la filosofía del estilo.

---

## 15. Filosofía de Diseño

### 15.1 Principios Rectores

1. **Los estilos son identidad, no poder.** Un estilo no debería ser "mejor" que otro en términos absolutos. Cada estilo es una forma diferente de abordar el combate, con fortalezas y debilidades situacionales.

2. **La narrativa precede a la mecánica.** El requisito más importante de un estilo es su condición narrativa. Si un jugador no puede explicar dónde y cómo su personaje aprendió el estilo, no debería tenerlo.

3. **Menos es más.** Es mejor tener 3 estilos bien diseñados con 4 cards cada uno, que 20 estilos con 1 card cada uno. La calidad sobre la cantidad.

4. **Los estilos existen para diversificar, no para segregar.** El objetivo de los estilos es que dos personajes con la misma disciplina jueguen diferente, no que un personaje sea "superior" por tener el estilo correcto.

5. **El staff es guardián de la coherencia.** Así como las fichas pasan por revisión, los estilos deben ser otorgados con criterio. No todos los personajes merecen todos los estilos.

### 15.2 Decisiones Clave y su Porqué

| Decisión | Alternativa descartada | Por qué se eligió así |
|----------|----------------------|----------------------|
| Estilos sin grados propios | Estilos con grados I–V como las disciplinas | Las disciplinas ya manejan la progresión vertical. Añadir grados a estilos sería duplicación. El estilo es cualitativo, no cuantitativo. |
| Sin coste en PP | Coste en PP para aprender estilos | El coste narrativo (trama, entrenamiento, aprobación staff) es más significativo que el coste en PP. Los PP miden esfuerzo de posting, no esfuerzo narrativo. |
| Validación de estilo separada de disciplina | Validación unificada en una función | Son conceptos diferentes. Disciplina = capacidad mecánica. Estilo = permiso narrativo. Separarlos permite validación granular y mensajes de error claros. |
| Requisitos como strings libres (JSON) | Requisitos con esquema fijo (enum de tipos) | La flexibilidad es más importante que la rigidez. El staff necesita poder definir requisitos creativos sin limitaciones de esquema. |
| Tabla separada para estilos de personaje | JSON array en data_json del personaje | JOINs eficientes para consultas staff, integridad referencial, historial de aprendizaje, rendimiento en listas. |
| Sin backend externo | API de terceros para gestionar estilos | Los estilos son parte del sistema RPG del foro. No dependen de servicios externos. La DB del foro es la única fuente de verdad. |

### 15.3 Decisiones Intencionales sobre lo que NO es un Estilo

**¿Por qué un estilo no tiene grados?**
Los grados son progresión vertical (qué tan bueno eres). Los estilos son especialización temática (qué escuela practicas). Un personaje no es "Santōryū grado III" — es "practicante de Santōryū con Armas de Filo grado III". La disciplina mide el "cuánto", el estilo mide el "qué".

**¿Por qué un estilo no otorga bonus a stats?**
Porque los bonus a stats vienen del equipamiento, las cards de tipo haki, y los perks de linaje. Un estilo no debería hacer que un personaje sea más fuerte — debería hacer que pelee de manera diferente.

**¿Por qué un estilo no se adquiere en la creación del personaje?**
Porque la creación es el punto de partida. Los estilos representan especialización que se gana con el juego, la trama y el desarrollo del personaje. Un personaje nuevo no debería comenzar con un estilo — debería ganarlo roleando.

### 15.4 Impacto RPG

| Decisión arquitectónica | Lo que significa para el juego |
|------------------------|-------------------------------|
| Estilos separados de disciplinas | Un personaje puede especializarse temáticamente sin necesidad de subir grados |
| Sin coste PP | El acceso a estilos no depende de cuánto postees, sino de cómo rolees |
| Requisitos narrativos | Aprender un estilo es una trama en sí misma |
| Cards de estilo exclusivas | Los estilos crean diversidad mecánica real entre personajes |
| Validación en asignación de cards | El sistema previene errores: no puedes tener una card de un estilo que no posees |
| Catálogo extensible | El staff puede añadir nuevos estilos sin modificar el sistema base |

---

## Referencia Rápida

| Concepto | Descripción |
|----------|-------------|
| `game_estilos_canonicos` | Tabla maestro: catálogo de estilos |
| `game_character_estilos` | Tabla: estilos aprendidos por cada personaje |
| `game_cards.estilo_canonico_slug` | Campo: vincula una card a un estilo |
| `estilos_canonicos_helpers.php` | Helpers PHP para CRUD de estilos |
| `estilos.php` | Biblioteca pública de estilos (frontend) |
| `estilos.js` | JS de filtros y modales de la biblioteca |
| `migrate_estilos_canonicos.php` | Migración que crea tablas e inserta seed data |
| `estilos_canonicos_seed_data.php` | Datos iniciales de estilos y cards |
| Disciplina mínima | Grado II+ para estilos básicos, III+ para avanzados |
| Cards mínimas por estilo | 3 (1xC, 1xB, 1xA). Opcional: 1xS |
| Validación primaria | `game_card_assignment_competencia_error()` + `game_estilo_canonico_card_assignment_error()` |
| Sin coste PP | El estilo se otorga por trama, no se compra |

---

*Fin del documento — `08-estilos-canonicos.md`*
