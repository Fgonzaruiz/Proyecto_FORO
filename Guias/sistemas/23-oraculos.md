# 23. Sistema de Oráculos y Tiradas

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 23
> **Propósito:** Documentar exhaustivamente el subsistema de oráculos: qué son, modelo de datos, tipos, processamiento, helpers PHP, integración con navegación, AJAX endpoints, staff tools, frontend, diseño de results_json, auto-invocación, variaciones por categoría, y filosofía de diseño.

---

## ÍNDICE

1. [Definición y Propósito](#1-definición-y-propósito)
2. [Arquitectura General](#2-arquitectura-general)
3. [Modelo de Datos — `game_oracles`](#3-modelo-de-datos)
4. [Modelo de Datos — `game_post_oracles`](#4-modelo-de-datos)
5. [Oracle Types](#5-oracle-types)
6. [results_json — Estructura y Matching](#6-results_json)
7. [Dice Types](#7-dice-types)
8. [Variations JSON — Resultados por Categoría](#8-variations-json)
9. [Auto-Invocación (Chain Oracles)](#9-auto-invocación)
10. [oracle_helpers.php](#10-oracle_helpersphp)
11. [ProcessPostOracles UseCase](#11-processpostoracles-usecase)
12. [Integración con Navegación](#12-integración-con-navegación)
13. [Mitigación por Oficio de Navegante](#13-mitigación-por-oficio-de-navegante)
14. [AJAX Endpoints](#14-ajax-endpoints)
15. [Staff Tools](#15-staff-tools)
16. [Frontend JS — oracles_ui.js](#16-frontend-js)
17. [Contratos API (OpenAPI)](#17-contratos-api)
18. [Seed de Ejemplo](#18-seed-de-ejemplo)
19. [Migraciones y Schema](#19-migraciones-y-schema)
20. [Filosofía de Diseño](#20-filosofía-de-diseño)
21. [Consejos para Staff](#21-consejos-para-staff)
22. [Consejos para Jugadores](#22-consejos-para-jugadores)

---

## 1. Definición y Propósito

### 1.1 Qué es un Oráculo

Un oráculo es una **tabla de resultados aleatorios** consultada mediante tirada de dado. Sustituye al narrador omnisciente para sucesos inciertos: desde "¿hay tormenta?" hasta "¿qué hay en el horizonte?" o "¿qué hace el PNJ en este momento?".

En lugar de que el staff decida cada pequeña incertidumbre, el oráculo la resuelve con una tirada mecánica cuyo resultado se cruza contra rangos predefinidos.

### 1.2 Propósito en el Sistema

| Propósito | Descripción |
|-----------|-------------|
| Eliminar parálisis narrativa | Cuando nadie sabe qué ocurre, se tira un oráculo |
| Consistencia mecánica | Los mismos rangos producen los mismos resultados, sin sesgo humano |
| Sorpresa genuina | Ni el jugador ni el staff saben el resultado antes de tirar |
| Escalabilidad | Un oráculo bien diseñado sirve para toda la partida, no solo una escena |

### 1.3 Analogía

Un oráculo es como un **libro de tablas de encuentros aleatorios** de los RPGs de mesa clásicos. La diferencia es que aquí las tablas son configurables por el staff, con variaciones por zona geográfica (isla/categoría), y se ejecutan automáticamente al postear.

---

## 2. Arquitectura General

### 2.1 Capas del Subsistema

```
┌──────────────────────────────────────────────────────────────┐
│                    CLIENTE (Navegador)                        │
│  ┌──────────────────┐  ┌────────────────────────────────┐    │
│  │ oracles_ui.js    │  │ oracles_staff.js               │    │
│  │ (posts + editor) │  │ (CRUD staff, preview)          │    │
│  └──────┬───────────┘  └────────┬───────────────────────┘    │
│         │                       │                            │
│         ▼                       ▼                            │
│  ┌──────────────────────────────────────────────────────┐    │
│  │  AJAX (game/ajax/oracles_*.php)                      │    │
│  │  oracles_list | oracles_by_category | oracles_for_post│   │
│  │  oracles_create | oracles_update | oracles_delete    │    │
│  └───────────────────────┬──────────────────────────────┘    │
└──────────────────────────┼───────────────────────────────────┘
                           │ HTTP POST/GET + JSON
┌──────────────────────────┼───────────────────────────────────┐
│  ┌───────────────────────▼─────────────────────────────────┐ │
│  │              PHP — CAPA DE APLICACIÓN                    │ │
│  │  UseCase: ProcessPostOracles (src/Application/UseCases/) │ │
│  │  Helpers: oracle_helpers.php (roll, find, category)      │ │
│  │  Navigation: navigation_process.php (generate_events)    │ │
│  └──────────────────────────────────────────────────────────┘ │
│                              │                                │
│                              ▼                                │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │           MySQL (MyBB + tablas game_*)                    │ │
│  │  game_oracles (catálogo) + game_post_oracles (ejecución)  │ │
│  └──────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────┘
```

### 2.2 Filosofía de la Arquitectura

**¿Por qué dos tablas separadas (catálogo + ejecución)?**

- `game_oracles` es el **catálogo maestro**: define qué oráculos existen, sus resultados y configuraciones. Un registro por oráculo.
- `game_post_oracles` es el **historial de ejecuciones**: registra qué oráculo se tiró, con qué resultado, en qué post y para qué personaje.
- Separarlos permite que un mismo oráculo se tire mil veces en mil posts distintos sin duplicar su definición.

**¿Por qué la tirada se hace en servidor (PHP) y no en cliente (JS)?**

- **Integridad**: Si la tirada se hiciera en JS, un usuario malicioso podría manipular el resultado. Al hacerla en PHP, el resultado es verificable por el staff.
- **Consistencia**: El mismo algoritmo (`game_roll_oracle`) se usa desde ProcessPostOracles, navigation_process.php, y cualquier otro punto de entrada. No hay duplicación de lógica.
- La preview en el staff tool sí usa JS local, pero es solo para testing — la ejecución real siempre es server-side.

**¿Por qué results_json es un array de objetos con range/result/description en lugar de un formato más simple?**

- Porque necesitamos rangos (1-10, 11-20), no solo valores exactos.
- Porque la descripción opcional permite al staff dar contexto narrativo adicional.
- Porque el campo `auto_invoke` añade capacidades de encadenamiento (ver sección 9).

### 2.3 Impacto RPG

| Decisión arquitectónica | Lo que significa para el juego |
|------------------------|-------------------------------|
| Tirada server-side | El resultado es inalterable por el jugador |
| Dos tablas separadas | Un oráculo se puede tirar infinitas veces sin duplicar su definición |
| results_json con rangos | El staff puede diseñar probabilidades desiguales (ej: 1-10 = común, 20 = rarísimo) |
| Variaciones por categoría | Un mismo oráculo da resultados distintos según la isla donde se esté |
| Auto-invocación | Un resultado puede desencadenar automáticamente otro oráculo (ej: "aparece un Kraken" → tira "Resolución — Criatura marina") |

---

## 3. Modelo de Datos — `game_oracles`

### 3.1 Definición SQL Completa

```sql
CREATE TABLE mybb_game_oracles (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(150) NOT NULL,
    description       TEXT,
    oracle_type       VARCHAR(30) NOT NULL DEFAULT 'custom',
    subtype           VARCHAR(100) DEFAULT '',
    category          VARCHAR(100) DEFAULT '',
    tags_json         TEXT,
    results_json      TEXT NOT NULL,
    variations_json   TEXT,
    auto_invoke_json  TEXT,
    dice_type         VARCHAR(10) NOT NULL DEFAULT 'd100',
    is_system         TINYINT(1) NOT NULL DEFAULT 0,
    image_url         VARCHAR(500) DEFAULT '',
    created_by        INT NOT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_type (oracle_type),
    KEY idx_category (category),
    KEY idx_subtype (subtype)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Campos — Descripción Detallada

#### `id` — Identificador único
- Autoincremental. Clave primaria del catálogo de oráculos.
- Referenciado como `oracle_id` en `game_post_oracles` y en `auto_invoke_json`.

#### `name` — Nombre del oráculo
- Visible en la UI del selector (post editor) y en las tarjetas de resultado.
- Debe ser descriptivo: "Clima en la Grand Line", "¿Qué hay en el Horizonte?".

#### `description` — Descripción narrativa
- Texto opcional que explica cuándo y por qué usar este oráculo.
- Se muestra en el selector de oráculos al hacer hover o en la tarjeta de resultado.

#### `oracle_type` — Tipo funcional
- Define la categoría semántica del oráculo.
- Ver sección 5 para la lista completa de tipos y sus usos.

#### `subtype` — Subtipo o contexto
- Texto libre que permite agrupar oráculos por contexto funcional.
- Ejemplos: `navegacion`, `pnj`, `clima`, `encuentro`, `nav_1_2`, `nav_3`, `nav_4_5`.
- Indexado para búsquedas rápidas: `KEY idx_subtype (subtype)`.

#### `category` — Categoría / Isla
- Si se especifica, el oráculo SOLO aparece en el selector cuando el post pertenece a esa categoría/isla.
- Si está vacío, el oráculo está disponible en todas las categorías.
- Indexado: `KEY idx_category (category)`.

#### `tags_json` — Etiquetas
```json
["navegacion", "basico"]
```
- Array JSON de strings para etiquetado flexible.
- Se usa en el frontend para filtrar oráculos por tema.

#### `results_json` — Tabla de resultados
- Array JSON de objetos. Ver sección 6 para estructura detallada.
- **Campo obligatorio** — un oráculo sin resultados no tiene sentido.

#### `variations_json` — Variaciones por categoría/isla
- Objeto JSON donde cada clave es un nombre de categoría/isla y el valor es un array de resultados alternativos.
- Ver sección 8.

#### `auto_invoke_json` — Configuración global de auto-invocación
- Array JSON. Actualmente en desuso (la auto-invocación se define por resultado individual).
- Se mantiene por compatibilidad.

#### `dice_type` — Tipo de dado
- Determina el rango de la tirada: d6 (1-6), d12 (1-12), d20 (1-20), d100 (1-100).
- Ver sección 7.

#### `is_system` — Oráculo del sistema
- `1` = oráculo creado por migraciones del sistema (no editable/borrable por staff normal).
- `0` = oráculo creado por staff (totalmente gestionable).
- Los oráculos del sistema son protegidos: el endpoint `oracles_delete.php` rechaza borrarlos.

#### `image_url` — Imagen opcional
- URL a una imagen representativa del oráculo.
- Se muestra en la tarjeta de resultado si está presente.

#### `created_by` — Creador
- `user_id` del staff que creó el oráculo.
- Solo para auditoría.

### 3.3 Filosofía del Schema

**¿Por qué `tags_json` como columna separada y no dentro de `variations_json`?**
- Porque los tags son metadatos del oráculo en sí mismo, no de una variante.
- `tags_json` se usa para filtrado rápido sin parsear todo el results_json.

**¿Por qué `is_system` no es simplemente `created_by = 0`?**
- Porque un admin podría tener `uid = 1`. Separar la bandera evita confusiones.
- Además, `is_system` impide explícitamente el borrado desde el frontend.

**¿Por qué índices en `oracle_type`, `category` y `subtype`?**
- Porque las consultas más frecuentes filtran por estos campos:
  - "Dame todos los oráculos de navegación disponibles en Arabasta"
  - "Muéstrame los oráculos de tipo pay_the_price"

---

## 4. Modelo de Datos — `game_post_oracles`

### 4.1 Definición SQL Completa

```sql
CREATE TABLE mybb_game_post_oracles (
    id                        INT AUTO_INCREMENT PRIMARY KEY,
    post_id                   INT NOT NULL,
    character_id              INT NOT NULL,
    oracle_id                 INT NOT NULL,
    roll_value                VARCHAR(20) NOT NULL,
    result_range              VARCHAR(20) NOT NULL DEFAULT '',
    result_text               TEXT NOT NULL,
    result_description        TEXT,
    auto_invoked              TINYINT(1) NOT NULL DEFAULT 0,
    invoked_by_post_oracle_id INT DEFAULT NULL,
    rolled_at                 TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_post (post_id),
    KEY idx_oracle (oracle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.2 Campos — Descripción Detallada

#### `id` — Identificador único
- Autoincremental. Cada fila es una ejecución de un oráculo.

#### `post_id` — Post donde se ejecutó
- FK lógica a `mybb_posts.pid` (sin constraint formal).
- Indexado: todas las consultas de "oráculos en este post" usan este índice.

#### `character_id` — Personaje que posteó
- FK lógica a `game_personajes.id`.
- Permite saber qué personaje "recibió" el resultado del oráculo.

#### `oracle_id` — Oráculo ejecutado
- FK lógica a `game_oracles.id`.
- Permite JOIN con el catálogo para obtener nombre, tipo, dado, etc.

#### `roll_value` — Valor de la tirada
- String del número obtenido (ej: "42", "17", "5").
- Almacenado como VARCHAR por flexibilidad (aunque siempre son enteros).

#### `result_range` — Rango que coincidió
- String del rango que mapeó al resultado (ej: "41-55", "1", "18-20").
- Se muestra en la UI como "Rango 41-55".

#### `result_text` — Texto del resultado
- El resultado propiamente dicho (ej: "Tormenta eléctrica", "Sí, pero...").

#### `result_description` — Descripción del resultado
- Texto narrativo opcional que detalla qué significa el resultado.
- Puede incluir notas de mitigación (ver sección 13) con formato MyBB.

#### `auto_invoked` — ¿Fue auto-invocado?
- `0` = tirada directa (el jugador seleccionó este oráculo).
- `1` = tirada automática (encadenada desde otro oráculo o desde el sistema de navegación).
- Las auto-invocadas se ordenan después de las directas en la UI.

#### `invoked_by_post_oracle_id` — Cadena de invocación
- Si `auto_invoked = 1`, este campo apunta al `id` de `game_post_oracles` que lo desencadenó.
- Permite reconstruir la cadena: "El resultado X activó el oráculo Y".

#### `rolled_at` — Timestamp de la tirada
- Cuándo se ejecutó la tirada.

### 4.3 Filosofía del Schema

**¿Por qué `roll_value` como VARCHAR y no INT?**
- Por consistencia con `result_range` que puede contener guiones ("41-55").
- Aunque la tirada siempre es un entero, mantener el mismo tipo que `result_range` simplifica el código de renderizado.

**¿Por qué `invoked_by_post_oracle_id` en lugar de una tabla separada de cadenas?**
- Porque las cadenas son simples (1 nivel de profundidad). Un oráculo auto-invoca otro, y ese no auto-invoca más.
- Si en el futuro hubiera cadenas de 3+ niveles, se podría normalizar.

**¿Por qué NO hay FK formales a `mybb_posts`?**
- Porque MyBB no siempre usa InnoDB para todas sus tablas, y algunas migraciones crean posts antes de que existan los oráculos.
- La integridad referencial se gestiona en la capa de aplicación.

---

## 5. Oracle Types

### 5.1 Tipos Disponibles

| Tipo | Slug | Propósito | Ejemplo |
|------|------|-----------|---------|
| Custom | `custom` | Cualquier tabla personalizada | "Clima en la Grand Line", "Tesoro Escondido" |
| Sí/No | `yes_no` | Respuesta binaria con matices | "El Mar lo Decide" (Sí/No/Sí pero...) |
| Acción | `action` | Qué hace alguien/algo | "Acciones de la Tripulación" |
| Tema | `theme` | Tema narrativo de una escena/aventura | "Tema de Aventura" |
| Acción + Tema | `action_theme` | Combina acción y tema | "Encuentro en el Mar" |
| Descriptor Lugar | `place_descriptor` | Describe un lugar | "Descriptor de Isla" |
| Foco Lugar | `place_focus` | Punto de interés principal | "Foco de Exploración" |
| Rol PNJ | `character_role` | Ocupación de un PNJ | "Rol de PNJ" |
| Rasgo PNJ | `character_trait` | Personalidad/apariencia de PNJ | "Rasgo de PNJ" |
| Meta PNJ | `character_goal` | Objetivo de un PNJ | "Meta de PNJ" |
| Paga el Precio | `pay_the_price` | Consecuencias de fallo/riesgo | "Paga el Precio (One Piece)" |
| Tema Mazmorra | `delve_theme` | Ambientación de mazmorra | "Tema de Mazmorra" |
| Dominio Mazmorra | `delve_domain` | Tipo de criaturas/desafíos | "Dominio de Mazmorra" |

### 5.2 Subtipos de Navegación

Estos son subtipos (no tipos) usados por el sistema de navegación:

| Subtipo | Significado | Dado típico |
|---------|-------------|-------------|
| `nav_1` | Peligro 1 (East Blue, aguas tranquilas) | d12 |
| `nav_2` | Peligro 2 (incidentes moderados) | d20 |
| `nav_1_2` | Combinado peligro 1-2 (Blues) | d20 |
| `nav_3` | Peligro 3 (Grand Line) | d20 |
| `nav_4` | Peligro 4 (corsarios, patrullas) | d20 |
| `nav_5` | Peligro 5 (extremo, New World) | d12 |
| `nav_4_5` | Combinado peligro 4-5 (New World) | d20 |
| `nav_resolve_naval` | Resolución de encuentro naval | d6 |
| `nav_resolve_beast` | Resolución de criatura marina | d6 |

### 5.3 Filosofía de los Tipos

**¿Por qué tantos tipos si todos se almacenan igual?**
- Porque el tipo es un filtro semántico. Cuando un jugador selecciona oráculos en el editor de post, puede filtrar por "Acción" para encontrar rápidamente qué hace un PNJ.
- El tipo también permite al staff organizar el catálogo: en la herramienta staff, los oráculos se agrupan por tipo.

**¿Por qué `action_theme` existe como tipo separado si internamente es solo un placeholder?**
- Porque indica al jugador que debe tirar DOS oráculos (uno de acción y uno de tema) y combinarlos. Es un recordatorio UX.
- El resultado de `action_theme` suele ser "(Ver Acción) + (Ver Tema)".

---

## 6. results_json — Estructura y Matching

### 6.1 Estructura

```json
[
  {
    "range": "1-10",
    "result": "Sí",
    "description": "El mar te concede el paso.",
    "auto_invoke": {
      "oracle_id": 42,
      "label": "Resolución — Encuentro naval"
    }
  }
]
```

### 6.2 Campos por Resultado

| Campo | Obligatorio | Tipo | Descripción |
|-------|-------------|------|-------------|
| `range` | Sí | String | Rango numérico ("1-10") o valor exacto ("5") |
| `result` | Sí | String | Texto del resultado (corto, tipo título) |
| `description` | No | String | Descripción narrativa opcional |
| `auto_invoke` | No | Object | Config de auto-invocación (ver sección 9) |

### 6.3 Algoritmo de Matching (`game_find_oracle_result`)

```php
function game_find_oracle_result(array $results, int $roll): ?array
{
    // Paso 1: Buscar por rango numérico
    foreach ($results as $entry) {
        $range = $entry['range'] ?? '';
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $range, $m)) {
            if ($roll >= (int)$m[1] && $roll <= (int)$m[2]) {
                return [
                    'range' => $range,
                    'result' => $entry['result'] ?? '',
                    'description' => $entry['description'] ?? '',
                    'auto_invoke' => $entry['auto_invoke'] ?? null,
                ];
            }
        }
    }
    // Paso 2: Fallback a valor exacto
    foreach ($results as $entry) {
        if ((int)$entry['range'] === $roll) {
            return [
                'range' => (string)$roll,
                'result' => $entry['result'] ?? '',
                'description' => $entry['description'] ?? '',
                'auto_invoke' => $entry['auto_invoke'] ?? null,
            ];
        }
    }
    return null;
}
```

**Orden de matching:**
1. Primero busca rangos con guion ("1-10", "11-20").
2. Si no encuentra, busca valores exactos ("5", "20").
3. Si no hay match, retorna null (el oráculo devuelve "—" en ese caso).

### 6.4 Reglas de Diseño de Rangos

1. **Los rangos deben ser contiguos y cubrir todo el dado.** Si el dado es d20 y tienes rangos "1-5", "6-10" y "11-15", los valores 16-20 no tienen match y devolverán "—". Esto puede ser intencional (resultados vacíos = "nada destacable") o un error de diseño.
2. **Los rangos no deben solaparse.** "1-10" y "10-20" es ambiguo: ¿10 pertenece a cuál? El algoritmo toma el primero que encuentra.
3. **Usa rangos desiguales para controlar probabilidades.** "1-10" (50% con d20), "11-17" (35%), "18-20" (15%). Esto permite modelar "resultados comunes", "poco comunes" y "raros".
4. **Valores exactos para resultados únicos.** "20" en lugar de "20-20" es más legible.

### 6.5 Ejemplos de results_json

**d6 con resultados exactos (sin rangos):**
```json
[
  {"range": "1", "result": "Observa el horizonte"},
  {"range": "2", "result": "Repara aparejos"},
  {"range": "3", "result": "Cocina"},
  {"range": "4", "result": "Entrena combate"},
  {"range": "5", "result": "Duerme"},
  {"range": "6", "result": "Lee un libro"}
]
```

**d100 con rangos desiguales:**
```json
[
  {"range": "1-40", "result": "Común", "description": "Suceso cotidiano."},
  {"range": "41-70", "result": "Poco común", "description": "Algo fuera de lo normal."},
  {"range": "71-90", "result": "Raro", "description": "Un evento notable."},
  {"range": "91-99", "result": "Muy raro", "description": "Algo extraordinario."},
  {"range": "100", "result": "¡Único!", "description": "¡Suceso legendario!"}
]
```

**d20 con probabilidades balanceadas (5 grupos de 4):**
```json
[
  {"range": "1-4", "result": "Favorable"},
  {"range": "5-8", "result": "Moderado"},
  {"range": "9-12", "result": "Severo"},
  {"range": "13-16", "result": "Extremo"},
  {"range": "17-20", "result": "Singular"}
]
```

---

## 7. Dice Types

### 7.1 Tipos Soportados

| Dice | Rango | Uso típico |
|------|-------|------------|
| `d6` | 1-6 | Resoluciones rápidas, encuentros navales, criaturas |
| `d8` | 1-8 | Tablas pequeñas, eventos menores |
| `d10` | 1-10 | Tablas compactas, descriptors |
| `d12` | 1-12 | Navegación de bajo peligro, clima |
| `d20` | 1-20 | Propósito general, navegación, acciones |
| `d100` | 1-100 | Tablas grandes, temas, rasgos, metas |

### 7.2 Cómo se Procesa el Dice

```php
$diceType = $oracleRow['dice_type'] ?? 'd100';
$max = max(1, (int)substr($diceType, 1));
$roll = mt_rand(1, $max);
```

- `mt_rand()` genera la tirada usando Mersenne Twister.
- El máximo se extrae eliminando la 'd' del string: `substr("d20", 1)` → `"20"` → `(int)20`.

### 7.3 Filosofía de la Selección de Dados

| Dado | Ventaja | Desventaja |
|------|---------|------------|
| d6 | Simple, resultados rápidos | Poca granularidad (solo 6 resultados) |
| d20 | Buen balance, probabilidades del 5% por punto | No tan intuitivo como d100 para porcentajes |
| d100 | Granularidad máxima, porcentajes exactos | Muchos resultados que definir |

**Regla general:** Usa d100 para oráculos narrativos complejos (temas, rasgos, metas) donde quieras muchos resultados posibles y control porcentual. Usa d6/d20 para oráculos mecánicos rápidos (resoluciones, encuentros) donde necesites pocos resultados.

---

## 8. Variations JSON — Resultados por Categoría

### 8.1 Estructura

```json
{
  "Arabasta": [
    {"range": "1-15", "result": "Tormenta de arena", "description": "El desierto se levanta."},
    {"range": "16-30", "result": "Sol implacable", "description": "Calor extremo."}
  ],
  "Drum": [
    {"range": "1-20", "result": "Ventisca", "description": "Nieve y viento cegador."},
    {"range": "21-40", "result": "Nevada", "description": "Copos sin parar."}
  ]
}
```

### 8.2 Cómo se Aplican

```php
$results = json_decode($oracleRow['results_json'] ?? '[]', true);

if ($category) {
    $variations = json_decode($oracleRow['variations_json'] ?? '{}', true);
    if (is_array($variations) && isset($variations[$category]) && is_array($variations[$category])) {
        $results = $variations[$category];
    }
}
```

1. Se carga `results_json` como base.
2. Si hay una categoría (nombre de isla/foro) y existe una variación para esa categoría, se **reemplaza** completamente `results` por los de la variación.
3. No hay merging: la variación es un reemplazo total.

### 8.3 Filosofía de las Variaciones

**¿Por qué reemplazo total y no merging?**
- Porque las variaciones suelen ser tan diferentes que mezclarlas no tendría sentido. Un oráculo "Clima en la Grand Line" en Arabasta (desierto) no comparte resultados con Drum (nieve).
- Si se necesitara que ciertos resultados sean universales y otros específicos, se puede diseñar un oráculo base sin variaciones y oráculos específicos por separado.

**¿Por qué la clave del objeto es el nombre de la categoría y no el ID?**
- Porque las categorías se definen en los foros de MyBB y su nombre es más legible que un ID numérico.
- Además, el nombre de la categoría es lo que devuelve `game_get_post_category()`.

### 8.4 game_get_post_category()

```php
function game_get_post_category(int $postId): string
{
    global $db;
    $prefix = TABLE_PREFIX;
    $q = $db->query("
        SELECT t.tid, f.fid, f.name AS forum_name, f.pid,
               p.name AS category_name
        FROM {$prefix}posts pst
        JOIN {$prefix}threads t ON t.tid = pst.tid
        JOIN {$prefix}forums f ON f.fid = t.fid
        LEFT JOIN {$prefix}forums p ON p.fid = f.pid AND p.type = 'c'
        WHERE pst.pid = {$postId}
        LIMIT 1
    ");
    $row = $db->fetch_array($q);
    if (!$row) return '';
    return ($row['category_name'] ?? '') ?: ($row['forum_name'] ?? '');
}
```

Esta función determina la categoría/isla de un post navegando por la jerarquía de foros de MyBB:
1. Busca el foro donde está el thread.
2. Si ese foro tiene un padre de tipo 'c' (categoría), usa el nombre del padre.
3. Si no, usa el nombre del foro directamente.

---

## 9. Auto-Invocación (Chain Oracles)

### 9.1 Concepto

Un resultado de oráculo puede **auto-invocar** otro oráculo automáticamente. Esto crea una cadena de eventos:

1. Tiraste "Evento de Navegación — Mar Tranquilo".
2. Sacaste "Sombra bajo el agua" (rango 20).
3. Automáticamente se invoca "Resolución — Criatura marina".

### 9.2 Configuración en results_json

```json
{
  "range": "18-19",
  "result": "Emboscada leve",
  "description": "Un bote rápido intenta acercarse por la popa.",
  "auto_invoke": {
    "oracle_id": 45,
    "label": "Resolución — Encuentro naval"
  }
}
```

- `oracle_id`: ID del oráculo a invocar (debe existir en `game_oracles`).
- `label`: Texto descriptivo para mostrar en la UI preview.

### 9.3 Procesamiento

En `ProcessPostOracles::execute()`:

```php
$auto_invoke = $result['auto_invoke'] ?? null;
if ($auto_invoke && !empty($auto_invoke['oracle_id'])) {
    $invoke_id = (int)$auto_invoke['oracle_id'];
    $auto_q = $this->db->query("SELECT * FROM {$prefix}game_oracles WHERE id = {$invoke_id} LIMIT 1");
    if ($auto_row = $this->db->fetch_array($auto_q)) {
        $auto_result = game_roll_oracle($auto_row, $category);
        // Insertar con auto_invoked = 1
        $this->db->insert_query('game_post_oracles', $auto_insert);
    }
}
```

1. Se ejecuta el oráculo principal.
2. Si el resultado tiene `auto_invoke`, se busca el oráculo destino.
3. Se tira el oráculo destino.
4. Se inserta en `game_post_oracles` con `auto_invoked = 1` e `invoked_by_post_oracle_id` apuntando al registro padre.

### 9.4 Visualización en UI

Las auto-invocadas aparecen:
- Con badge "Auto-invocado" en la tarjeta de resultado.
- Ordenadas después de las invocaciones directas (`ORDER BY po.auto_invoked ASC`).
- Si forman parte de un viaje de navegación, se muestran como eventos hijos dentro del panel de navegación.

### 9.5 Filosofía de la Auto-Invocación

**¿Por qué limitar a 1 nivel de profundidad?**
- Por simplicidad. Cadenas de 3+ niveles serían difíciles de seguir en la UI.
- Si un resultado necesita invocar múltiples oráculos, se puede diseñar un oráculo "compuesto" que en su results_json tenga entradas que invoquen distintos oráculos según el rango.

**¿Por qué no permitir auto_invoke sin un oracle_id explícito?**
- Porque el sistema necesita saber QUÉ oráculo tirar. Una auto-invocación sin destino sería ruido.

---

## 10. oracle_helpers.php

**Archivo:** `back/forum/game/inc/oracle_helpers.php`

Tres funciones públicas que forman el núcleo del subsistema.

### 10.1 `game_get_post_category(int $postId): string`

```php
function game_get_post_category(int $postId): string
```

Retorna la categoría/isla de un post. Ver sección 8.4 para detalle.

**Uso:**
- Determinar qué variación aplicar a un oráculo basado en la isla donde se posteó.
- Usado tanto por `ProcessPostOracles` como por `navigation_process.php`.

### 10.2 `game_find_oracle_result(array $results, int $roll): ?array`

```php
function game_find_oracle_result(array $results, int $roll): ?array
```

Busca un resultado por rango o valor exacto. Ver sección 6.3 para el algoritmo.

**Parámetros:**
- `$results`: Array decodificado de `results_json` (o de una variación).
- `$roll`: Valor de la tirada (1..N).

**Retorno:**
```php
[
    'range' => '41-55',
    'result' => 'Tormenta',
    'description' => 'Una tormenta se aproxima.',
    'auto_invoke' => null,
]
```

### 10.3 `game_roll_oracle(array $oracleRow, ?string $category = null): array`

```php
function game_roll_oracle(array $oracleRow, ?string $category = null): array
```

Función principal: ejecuta la tirada completa de un oráculo.

**Algoritmo:**
1. Decodifica `results_json`.
2. Si hay categoría y variación, reemplaza results.
3. Determina `$max` del `dice_type` (d100 → 100, d20 → 20).
4. Genera `$roll = mt_rand(1, $max)`.
5. Busca match con `game_find_oracle_result()`.
6. Retorna payload completo.

**Retorno:**
```php
[
    'roll' => 42,
    'roll_display' => '42',
    'range' => '41-55',
    'result' => 'Tormenta',
    'description' => 'Una tormenta se aproxima.',
    'auto_invoke' => null,
]
```

**Uso en todo el sistema:**
- `ProcessPostOracles::execute()` — tirada al postear.
- `navigation_process.php` — tirada durante eventos de navegación.
- `navigation_process.php::game_navigation_maybe_invoke_chain()` — tirada de auto-invocadas.

### 10.4 Filosofía de los Helpers

**¿Por qué funciones globales en lugar de una clase?**
- Porque se usan desde contextos muy dispares (UseCases, navigation_process, migraciones). Una función global no necesita autoloading ni inyección de dependencias.
- Si el proyecto creciera, se podrían migrar a un servicio con `__invoke()`.

**¿Por qué `game_get_post_category()` separada de `game_roll_oracle()`?**
- Porque la obtención de la categoría es una consulta SQL independiente. Si ya se tiene la categoría (ej: desde navigation_process que ya la calculó), no necesitas repetir la consulta.

---

## 11. ProcessPostOracles UseCase

**Archivo:** `back/forum/game/src/Application/UseCases/ProcessPostOracles.php`

### 11.1 Propósito

Procesa los oráculos seleccionados por el jugador al crear/editar un post. Se ejecuta como parte del pipeline de posteo.

### 11.2 Flujo Completo

```
Procesamiento de Post
│
├─ ¿Hay oráculos seleccionados (oraclesJson)?
│   └─ No → retornar
│
├─ ¿Existen las tablas game_post_oracles y game_oracles?
│   └─ No → log + retornar (fallback silencioso)
│
├─ Decodificar JSON de IDs de oráculos
│
├─ Obtener categoría del post (game_get_post_category)
│
└─ Por cada oracle_id:
    ├─ Cargar row de game_oracles
    ├─ game_roll_oracle(oracle, category)
    ├─ Insertar en game_post_oracles
    └─ Si el resultado tiene auto_invoke:
        ├─ Cargar oráculo destino
        ├─ game_roll_oracle(oracle_destino, category)
        └─ Insertar con auto_invoked=1 e invoked_by
```

### 11.3 Código Completo

```php
class ProcessPostOracles
{
    private $db;
    private $prefix;

    public function __construct($db, string $prefix)
    {
        $this->db = $db;
        $this->prefix = $prefix;
    }

    public function execute(int $pid, int $cid, string $oraclesJson): void
    {
        if (empty($oraclesJson)) return;

        if (!$this->db->table_exists('game_post_oracles')
            || !$this->db->table_exists('game_oracles')) {
            // Log silencioso — las tablas pueden no existir en fresh installs
            return;
        }

        $oracle_ids = json_decode($oraclesJson, true);
        if (!is_array($oracle_ids) || $oracle_ids === []) return;

        $category = function_exists('game_get_post_category')
            ? game_get_post_category($pid) : '';

        foreach ($oracle_ids as $oid) {
            $oid = (int)$oid;
            if ($oid <= 0) continue;

            $oq = $this->db->query(
                "SELECT * FROM {$this->prefix}game_oracles WHERE id = {$oid} LIMIT 1"
            );
            $oracle = $this->db->fetch_array($oq);
            if (!$oracle) continue;

            $result = game_roll_oracle($oracle, $category);

            // Insertar registro principal
            $insert = [
                'post_id' => $pid,
                'character_id' => $cid,
                'oracle_id' => $oid,
                'roll_value' => $result['roll'],
                'result_range' => $result['range'],
                'result_text' => $result['result'],
                'result_description' => $result['description'] ?? '',
                'auto_invoked' => 0,
            ];
            $this->db->insert_query('game_post_oracles', $insert);
            $post_oracle_id = (int)$this->db->insert_id();

            // Auto-invocación
            $auto_invoke = $result['auto_invoke'] ?? null;
            if ($auto_invoke && !empty($auto_invoke['oracle_id'])) {
                $invoke_id = (int)$auto_invoke['oracle_id'];
                $auto_q = $this->db->query(
                    "SELECT * FROM {$this->prefix}game_oracles WHERE id = {$invoke_id} LIMIT 1"
                );
                if ($auto_row = $this->db->fetch_array($auto_q)) {
                    $auto_result = game_roll_oracle($auto_row, $category);
                    $auto_insert = [
                        'post_id' => $pid,
                        'character_id' => $cid,
                        'oracle_id' => $invoke_id,
                        'roll_value' => $auto_result['roll'],
                        'result_range' => $auto_result['range'],
                        'result_text' => $auto_result['result'],
                        'result_description' => $auto_result['description'] ?? '',
                        'auto_invoked' => 1,
                        'invoked_by_post_oracle_id' => $post_oracle_id,
                    ];
                    $this->db->insert_query('game_post_oracles', $auto_insert);
                }
            }
        }
    }
}
```

### 11.4 Aspectos Clave

- **Fallback silencioso:** Si las tablas no existen, el UseCase no falla — simplemente retorna. Esto permite que el sistema funcione aunque las migraciones de oráculos no se hayan ejecutado.
- **Logging:** Si existe `game_log_post_rpg()`, registra cuántos oráculos se solicitaron, cuántos se guardaron, y si algún oráculo no se encontró.
- **Integridad:** Cada tirada se persiste inmediatamente después de calcularse. Si el proceso falla a medio camino, los oráculos ya tirados quedan registrados.

---

## 12. Integración con Navegación

### 12.1 Visión General

El sistema de navegación (`navigation_process.php`) es el mayor consumidor de oráculos. Cada viaje genera eventos de navegación que se resuelven mediante oráculos.

### 12.2 Flujo de Navegación con Oráculos

```
Post con navegación habilitada
│
├─ game_navigation_process_post()
│  ├─ Validar destino, barco, instrumento
│  ├─ Calcular distancia, peligro, duración
│  ├─ Calcular num_events (eventos durante el viaje)
│  ├─ INSERT en game_navigation_voyages
│  └─ game_navigation_generate_events()
│
└─ game_navigation_generate_events()
   ├─ Obtener oráculos disponibles según peligro
   ├─ Por cada evento:
   │  ├─ Seleccionar oráculo aleatorio
   │  ├─ game_roll_oracle() con mitigación de Navegante
   │  ├─ game_navigation_insert_post_oracle()
   │  ├─ INSERT en game_navigation_events
   │  └─ game_navigation_maybe_invoke_chain()
   └─ Retornar
```

### 12.3 Selección de Oráculos por Nivel de Peligro

```php
function game_nav_get_oracles_for_danger(int $danger): array
```

Esta función selecciona los oráculos cuyo subtipo coincide con el nivel de peligro:

| Peligro | Subtipos de oráculos usados |
|---------|---------------------------|
| 1 | `nav_1`, `nav_1_2` |
| 2 | `nav_2`, `nav_1_2` |
| 3 | `nav_3` |
| 4 | `nav_4`, `nav_4_5` |
| 5 | `nav_5`, `nav_4_5` |

### 12.4 Eventos de Navegación

Cada evento de navegación se registra en `game_navigation_events`:

```sql
CREATE TABLE mybb_game_navigation_events (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    voyage_id         INT NOT NULL,
    post_oracle_id    INT NOT NULL,
    event_order       TINYINT UNSIGNED NOT NULL,
    danger_tier       TINYINT UNSIGNED NOT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_voyage (voyage_id)
);
```

- `voyage_id`: Viaje al que pertenece.
- `post_oracle_id`: ID en `game_post_oracles` con el resultado de la tirada.
- `event_order`: Orden del evento dentro del viaje (1, 2, 3...).
- `danger_tier`: Nivel de peligro en el momento del evento.

### 12.5 game_navigation_insert_post_oracle()

Helper que encapsula la inserción en `game_post_oracles` desde el sistema de navegación:

```php
function game_navigation_insert_post_oracle(
    int $postId,
    int $characterId,
    array $oracle,       // row completa de game_oracles
    array $rollResult,   // resultado de game_roll_oracle()
    int $autoInvoked = 1,
    ?int $invokedByPostOracleId = null
): int
```

Todas las tiradas de navegación se marcan como `auto_invoked = 1` porque no las seleccionó el jugador directamente sino el sistema.

### 12.6 game_navigation_maybe_invoke_chain()

Procesa auto-invocaciones durante eventos de navegación:

```php
function game_navigation_maybe_invoke_chain(
    int $postId,
    int $characterId,
    array $rollResult,
    string $category,
    int $parentPostOracleId
): void
```

Misma lógica que ProcessPostOracles, pero usando los helpers de navegación.

### 12.7 game_navigation_voyage_for_post()

Carga el viaje completo asociado a un post, incluyendo todos los eventos y sus oráculos encadenados:

```php
function game_navigation_voyage_for_post(int $postId): ?array
```

Retorna un array con:
- Datos del viaje (islas, barco, distancia, peligro, duración)
- Lista de eventos con sus oráculos
- Oráculos hijos (auto-invocados) anidados dentro de cada evento
- `navigation_post_oracle_ids`: IDs de game_post_oracles que pertenecen a la navegación (se excluyen de la lista general de oráculos en cards_for_post.php)

### 12.8 Integración con cards_for_post.php

En `cards_for_post.php`, los oráculos de navegación se separan de los oráculos normales para evitar duplicación en la UI:

```php
if ($voyage && !empty($voyage['navigation_post_oracle_ids'])) {
    $navIds = array_flip($voyage['navigation_post_oracle_ids']);
    $oracles = array_values(array_filter($oracles, static function ($o) use ($navIds) {
        return !isset($navIds[(int)($o['id'] ?? 0)]);
    }));
}
```

Los oráculos de navegación se muestran dentro del panel de viaje, no en la lista general de oráculos.

---

## 13. Mitigación por Oficio de Navegante

### 13.1 Concepto

El grado de Navegante (oficio) del personaje puede **mitigar** los resultados de eventos de navegación. Un navegante experto evita o reduce la gravedad de los eventos adversos.

### 13.2 Lógica de Mitigación

En `game_navigation_generate_events()`:

```php
// Grado 3+: Tira dos veces y se queda con la mejor (la más baja)
if ($navigatorRank >= 3) {
    $rollResult2 = game_roll_oracle($oracle, $category);
    if ($rollResult2['roll'] < $rollResult['roll']) {
        $rollResult = $rollResult2;
    }
}

// Mitigación por rangos según grado
if ($navigatorRank >= 5) {
    // Extremo (16-19) → Moderado (6)
    // Severo (11-15) → Moderado o Evitado
    // Moderado (6-10) → Favorable (1)
} elseif ($navigatorRank == 4) {
    // Extremo → Severo
    // Severo → Favorable (evasión única) o Moderado
    // Moderado → Favorable
} elseif ($navigatorRank >= 2) {
    // Extremo → Severo
    // Severo → Moderado
    // Moderado → Favorable
}
```

### 13.3 Tabla de Mitigación

| Grado Navegante | Extremo (16-19) | Severo (11-15) | Moderado (6-10) |
|----------------|-----------------|----------------|-----------------|
| 1 | — | — | — |
| 2 | → Severo | → Moderado | → Favorable |
| 3 | → Severo (tira 2, mejor) | → Moderado (tira 2) | → Favorable (tira 2) |
| 4 | → Severo | → Favorable (1 evasión) o Moderado | → Favorable |
| 5 | → Moderado | → Moderado (1 evasión) o Favorable | → Favorable |

### 13.4 Filosofía de la Mitigación

**¿Por qué mitigar con tiradas y no con modificadores directos?**
- Porque queremos que el Navegante se sienta ÚTIL sin eliminar la incertidumbre. No es "nunca te pasa nada malo", es "tus habilidades reducen la probabilidad y gravedad de lo malo".

**¿Por qué Grado 2 es el mínimo para mitigar?**
- Porque Grado 1 es un navegante novato que aún no sabe leer corrientes ni clima. No debería tener capacidad de mitigación.

**¿Por qué la evasión única en Grado 4?**
- Para darle un momento de poder al Navegante: "una vez por viaje, puedes evitar completamente un evento Severo". Es un recurso táctico.

---

## 14. AJAX Endpoints

### 14.1 `oracles_list.php` — GET

**Ruta:** `game/ajax/oracles_list.php`

**Parámetros:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `type` | string | Filtrar por oracle_type |
| `category` | string | Filtrar por categoría (incluye oráculos sin categoría) |
| `subtype` | string | Filtrar por subtipo |

**Respuesta:**
```json
{
  "ok": true,
  "data": [
    {
      "id": 1,
      "name": "Pay the Price",
      "oracle_type": "pay_the_price",
      "subtype": "core",
      "dice_type": "d100",
      "tags": ["core", "moves"],
      "results": [...],
      "variations": {},
      "auto_invoke": [],
      "is_system": 0
    }
  ]
}
```

**Uso:** Catálogo completo para staff tool y selector de oráculos en editor de post.

### 14.2 `oracles_by_category.php` — GET

**Ruta:** `game/ajax/oracles_by_category.php`

**Parámetros:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `category` | string | (Obligatorio) Categoría para filtrar |
| `type` | string | Filtrar por oracle_type |

**Diferencia con `oracles_list`:** Este endpoint filtra por `(o.category = '' OR o.category = '{$category}')`, retornando solo oráculos que aplican a una categoría específica (incluyendo los que no tienen categoría, que son "globales").

**Uso:** Cuando un jugador postea en una isla específica, solo ve los oráculos relevantes para esa isla + los globales.

### 14.3 `oracles_for_post.php` — GET

**Ruta:** `game/ajax/oracles_for_post.php`

**Parámetros:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `post_id` | int | (Obligatorio) ID del post |

**Respuesta:**
```json
{
  "ok": true,
  "data": [
    {
      "id": 10,
      "oracle_id": 1,
      "name": "Pay the Price",
      "dice_type": "d100",
      "roll_value": "42",
      "result_range": "41-55",
      "result_text": "A resource is taken",
      "auto_invoked": 0
    }
  ]
}
```

**Uso:** Frontend JS (`oracles_ui.js`) carga los oráculos de un post para mostrarlos en la zona de resultados.

### 14.4 `oracles_create.php` — POST (Staff Nivel 3)

**Ruta:** `game/ajax/oracles_create.php`

**Parámetros (JSON body):**
| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `name` | string | Sí | Nombre del oráculo |
| `description` | string | No | Descripción |
| `oracle_type` | string | No | Default: `custom` |
| `subtype` | string | No | Subtipo |
| `category` | string | No | Categoría/isla |
| `tags` | array | No | Array de strings |
| `results` | array | Sí | Array de objetos {range, result, description, auto_invoke} |
| `variations` | object | No | Mapa clave→array de resultados |
| `dice_type` | string | No | Default: `d100` |
| `image_url` | string | No | URL de imagen |

**Validaciones:**
- Staff level ≥ 3.
- CSRF token válido.
- `name` y `results` obligatorios.
- `results` debe tener al menos un elemento.

### 14.5 `oracles_update.php` — POST (Staff Nivel 3)

**Ruta:** `game/ajax/oracles_update.php`

**Parámetros:** Mismos que `oracles_create` + `id` (int, obligatorio).

**Validaciones adicionales:**
- El oráculo debe existir.
- Se permite actualizar cualquier campo excepto `created_by` y `is_system`.

### 14.6 `oracles_delete.php` — POST (Staff Nivel 3)

**Ruta:** `game/ajax/oracles_delete.php`

**Parámetros:**
| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `id` | int | Sí | ID del oráculo a eliminar |

**Validaciones:**
- No se puede eliminar un oráculo con `is_system = 1`.
- Si el oráculo tiene ejecuciones en `game_post_oracles`, esas filas NO se eliminan (el registro histórico se conserva aunque el oráculo ya no exista en el catálogo).

### 14.7 Filosofía de Seguridad

**¿Por qué Staff Nivel 3 (superadmin) para CRUD?**
- Porque los oráculos afectan a TODOS los personajes. Un oráculo mal diseñado puede romper el balance del juego.
- Los niveles 1-2 (mods) pueden ver y usar oráculos, pero no crear/editarlos.

**¿Por qué CSRF en todas las escrituras?**
- Porque un CSRF exitoso podría crear un oráculo malicioso o eliminar uno existente.

---

## 15. Staff Tools

### 15.1 Página Principal

**Archivo:** `game/public/oracles_staff.php`

**Ruta:** `/game/public/oracles_staff.php`

**Acceso:** Solo staff level ≥ 3 (superadmin).

**Layout:**
```
┌─────────────────────────────────────────────┐
│  Zona Staff → Sistema de Oráculos           │
│  [Catálogo] [Vista Previa]                   │
├─────────────────────────────────────────────┤
│  ┌──────────────────────────────────────┐   │
│  │ [Nuevo Oráculo]  [Buscar...]         │   │
│  ├──────────────────────────────────────┤   │
│  │ Custom (3)                           │   │
│  │ ┌───────────────────────────────┐    │   │
│  │ │ Clima en la Grand Line        │    │   │
│  │ │ d100 · 10 resultados · var.   │    │   │
│  │ │ [Editar] [Eliminar]           │    │   │
│  │ └───────────────────────────────┘    │   │
│  │ ┌───────────────────────────────┐    │   │
│  │ │ ¿Qué hay en el Horizonte?     │    │   │
│  │ │ d20 · 20 resultados          │    │   │
│  │ │ [Editar] [Eliminar]           │    │   │
│  │ └───────────────────────────────┘    │   │
│  └──────────────────────────────────────┘   │
└─────────────────────────────────────────────┘
```

### 15.2 Editor de Oráculos (Modal)

El modal de editor (`oracle-editor-modal`) tiene tres secciones:

**Sección 1: Identidad**
- Nombre (texto, obligatorio)
- Tipo (select: custom, yes_no, action...)
- Subtipo (texto, opcional)
- Categoría/Isla (select poblado desde los foros de MyBB con optgroups)
- Descripción (textarea)
- Tipo de Dado (select: d6, d8, d10, d12, d20, d100)
- URL Imagen (texto, opcional)

**Sección 2: Resultados**
- Grid de filas: Rango | Resultado | Descripción | Auto-Invocar | [Eliminar]
- Botón "Añadir Resultado"
- Auto-Invocar es un select poblado con todos los oráculos existentes (para seleccionar el destino)

**Sección 3: Variaciones por Categoría/Isla**
- Grupos de variaciones, cada uno con:
  - Nombre de categoría/isla (clave)
  - Grid de resultados propio
  - Botón "Añadir Resultado"
- Botón "Añadir Variación"

**Sección 4: Acciones**
- Cancelar (cierra modal)
- Guardar Oráculo (submit del formulario)

### 15.3 Vista Previa (Tab)

El tab "Vista Previa" permite al staff:
1. Seleccionar un oráculo del catálogo.
2. Presionar "Tirar" para simular una tirada (cliente-side con Math.random).
3. Ver el resultado renderizado como aparecería en un post.

**Uso:** El staff puede probar oráculos antes de usarlos en juego real, verificando que los rangos, resultados y descripciones se vean correctamente.

### 15.4 JS de Staff (`oracles_staff.js`)

**Archivo:** `back/forum/jscripts/game/oracles_staff.js` (569 líneas)

**Módulos:**
- `loadCatalog()`: Carga y renderiza el catálogo agrupado por tipo.
- `openEditor(oracleData)`: Abre el modal de editor en modo creación (null) o edición (datos).
- `saveOracle()`: Gather datos del formulario y envía a create/update.
- `deleteOracle(id)`: Solicita confirmación y envía DELETE.
- `loadForums()`: Carga la lista de foros para poblar el select de categorías.
- `rollPreview()`: Simula tirada en cliente y renderiza resultado.
- `gatherFormData()`: Recolecta todos los campos del formulario, incluyendo resultados, variaciones y auto-invocaciones.

---

## 16. Frontend JS — oracles_ui.js

**Archivo:** `back/forum/jscripts/game/oracles_ui.js` (274 líneas)

### 16.1 Renderizado en Posts

Cada post que tiene una zona de cartas (`div.rpg-post-cards-zone`) puede contener oráculos. `oracles_ui.js`:

1. Busca todas las zonas de cartas en la página.
2. Para cada zona, llama a `oracles_for_post.php?post_id=N`.
3. Si hay resultados, añade un bloque plegable "Oráculos (N)" con tarjetas de resultado.
4. Las tarjetas muestran: nombre, subtipo, dado, valor de tirada, rango, resultado y descripción.

### 16.2 Selector en Editor de Post

El selector de oráculos en el editor permite al jugador elegir qué oráculos ejecutar al postear:

1. Carga el catálogo desde `oracles_list.php`.
2. Filtra por categoría si el post está en una isla específica.
3. Renderiza los oráculos agrupados por tipo, cada uno como tarjeta seleccionable.
4. Al seleccionar, los IDs se acumulan en un campo oculto `rpg_oracles` como JSON array.
5. El contador muestra cuántos oráculos están seleccionados.

### 16.3 Integración con la Carga de Posts

```javascript
// En cards_for_post.php, el JS de RpgCards llama a:
RpgOracles.loadPostOracles();

// Que itera sobre las zonas y las rellena:
RpgOracles.renderOraclesForZone(zone);
```

### 16.4 Estructura de Tarjeta de Resultado

```html
<div class="rpg-oracle-card">
  <div class="rpg-oracle-card-header">
    <div class="rpg-oracle-card-title">
      Nombre
      <span class="rpg-oracle-subtype">subtipo</span>
    </div>
    <div class="rpg-oracle-card-dice">
      <span class="rpg-oracle-auto-badge">Auto-invocado</span>
      <span class="rpg-oracle-roll-badge">d100 → 42</span>
    </div>
  </div>
  <div class="rpg-oracle-card-desc">Descripción del oráculo</div>
  <div class="rpg-oracle-card-result">
    <div class="rpg-oracle-result-range">Rango 41-55</div>
    <div class="rpg-oracle-result-text">Tormenta</div>
    <div class="rpg-oracle-result-desc">Una tormenta se aproxima.</div>
  </div>
</div>
```

---

## 17. Contratos API (OpenAPI)

### 17.1 Endpoints Documentados

En `packages/contracts/openapi/game-api.openapi.yaml`:

| Endpoint | Método | Línea |
|----------|--------|-------|
| `/game/ajax/oracles_list.php` | GET | 517 |
| `/game/ajax/oracles_by_category.php` | GET | 544 |
| `/game/ajax/oracles_for_post.php` | GET | 569 |
| `/game/ajax/oracles_create.php` | POST | 591 |
| `/game/ajax/oracles_update.php` | POST | 614 |
| `/game/ajax/oracles_delete.php` | POST | 642 |

### 17.2 Ejemplos de Respuesta

En `packages/contracts/examples/`:

| Archivo | Endpoint |
|---------|----------|
| `oracles_list.response.json` | Lista el catálogo completo |
| `oracles_by_category.response.json` | Filtrado por categoría con variaciones |
| `oracles_for_post.response.json` | Resultados de oráculos en un post |
| `oracles_create.response.json` | Creación exitosa |
| `oracles_update.response.json` | Actualización exitosa |
| `oracles_delete.response.json` | Eliminación exitosa |

---

## 18. Seed de Ejemplo

**Archivo:** `back/forum/game/sql/seed_oracles_example.php`

### 18.1 Oráculos Sembrados

| # | Nombre | Tipo | Dado | Subtipo |
|---|--------|------|------|---------|
| 1 | El Mar lo Decide | yes_no | d20 | navegacion |
| 2 | Acciones de la Tripulación | action | d20 | tripulacion |
| 3 | Tema de Aventura | theme | d100 | narrativa |
| 4 | Encuentro en el Mar | action_theme | d100 | encuentro |
| 5 | Descriptor de Isla | place_descriptor | d100 | exploracion |
| 6 | Foco de Exploración | place_focus | d100 | exploracion |
| 7 | Rol de PNJ | character_role | d20 | pnj |
| 8 | Rasgo de PNJ | character_trait | d100 | pnj |
| 9 | Meta de PNJ | character_goal | d100 | pnj |
| 10 | Paga el Precio (One Piece) | pay_the_price | d100 | nucleo |
| 11 | Clima en la Grand Line | custom | d100 | clima |
| 12 | ¿Qué hay en el Horizonte? | custom | d20 | avistamiento |
| 13 | Tesoro Escondido | custom | d100 | tesoro |
| 14 | Tema de Mazmorra | delve_theme | d20 | mazmorra |
| 15 | Dominio de Mazmorra | delve_domain | d20 | mazmorra |

### 18.2 Ejecución

```bash
php back/forum/game/sql/seed_oracles_example.php
```

El script:
1. Busca un admin real (usergroup 4).
2. Inserta cada oráculo si no existe ya (verifica por nombre).
3. Muestra resumen de insertados/saltados.

---

## 19. Migraciones y Schema

### 19.1 Migración Principal

**Archivo:** `back/forum/game/sql/migrate_oracles.php`

Crea las dos tablas base si no existen:
- `game_oracles` (catálogo)
- `game_post_oracles` (ejecuciones)

### 19.2 Migraciones de Navegación

- `migrate_navigation_system.php`: Crea tabla `game_navigation_voyages` y `game_navigation_events`. Inserta 3 oráculos de navegación base (nav_1_2, nav_3, nav_4_5).
- `migrate_navigation_oracles_expand.php`: Inserta oráculos por nivel de peligro (nav_1, nav_2, nav_4, nav_5) y oráculos de resolución (nav_resolve_naval, nav_resolve_beast). Añade auto-invocaciones a los oráculos base.
- `migrate_weather_oracles.php`: Actualiza `results_json` de los oráculos nav_1_2, nav_3, nav_4_5 con datos de clima definitivos.

### 19.3 Fragmento de Instalación

En `install_schema_fragments.php`, las dos tablas de oráculos están definidas como fragmentos para instalaciones fresh.

### 19.4 Orden de Migraciones Recomendado

```
1. migrate_oracles.php
2. migrate_navigation_system.php
3. migrate_navigation_oracles_expand.php
4. migrate_weather_oracles.php
5. seed_oracles_example.php (opcional, solo para datos de ejemplo)
```

---

## 20. Filosofía de Diseño

### 20.1 Principios Rectores

1. **El oráculo no es un sustituto del staff, es una herramienta.** Los oráculos resuelven incertidumbres menores para que el staff se concentre en la trama principal. El staff siempre puede overridear un resultado de oráculo si la narrativa lo requiere.

2. **Los rangos son probabilidades disfrazadas.** Al diseñar un oráculo, el staff controla las probabilidades. "1-40" es 40% de probabilidad con d100. "100" es 1%. Esto permite modelar rareza.

3. **Las variaciones por isla dan sabor local.** Un mismo oráculo "Clima" se siente diferente en Arabasta que en Drum. Esto refuerza la identidad de cada isla sin multiplicar el número de oráculos.

4. **La auto-invocación crea narrativa emergente.** Una tirada puede desencadenar otra, que desencadena otra — y de repente tienes una mini-trama que nadie planeó.

5. **La mitigación por Navegante hace que los oficios importen.** Sin mitigación, un Navegante Grado 5 y uno Grado 1 tirarían los mismos oráculos. Con mitigación, el Grado 5 enfrenta menos eventos graves.

### 20.2 Decisiones Clave y su Porqué

| Decisión | Alternativa descartada | Por qué se eligió así |
|----------|----------------------|----------------------|
| results_json con array de objetos | Columnas separadas (range_1, result_1, range_2...) | Flexibilidad: número variable de resultados, auto-invoke por resultado |
| Variaciones por categoría en JSON column | Tabla separada game_oracle_variations | Menos JOINs, carga atómica del oráculo completo |
| Auto-invocación como campo de resultado | Sistema de triggers/eventos separado | Simple, explícito, visible en el editor |
| Mitigación en PHP (no en SQL) | Stored procedures | Transparente, fácil de debuggear y modificar |
| Staff level 3 para CRUD | Staff level 1 | Protección contra cambios maliciosos o accidentales |
| Tirada con mt_rand() | random_int() o API externa | mt_rand() es suficiente para juegos, más rápido |

### 20.3 Tradeoffs

| Decisión | Ventaja | Desventaja |
|----------|---------|------------|
| results_json como TEXT sin validación de schema | Flexibilidad total, el staff define lo que quiera | Posible corrupción si el JSON es inválido (se mitiga con decodificación con fallback) |
| Falta de FK formales | Sin errores de integridad referencial en migraciones | Posibles huérfanos en game_post_oracles si se borra un oráculo |
| Auto-invocación a 1 nivel | Simple, fácil de depurar | No permite cadenas complejas (ej: A→B→C) |
| Sin soft-delete en oráculos | Borrado simple, sin datos muertos | game_post_oracles puede referenciar oráculos ya borrados |

---

## 21. Consejos para Staff

### 21.1 Diseñando Oráculos

**Define el propósito antes que los resultados.** Pregúntate: "¿Para qué sirve este oráculo?" antes de escribir la tabla. Un oráculo de "Clima" tiene sentido si los personajes navegan. Un oráculo de "Rasgo de PNJ" tiene sentido si el staff crea PNJs sobre la marcha.

**Usa el tipo de dado para controlar la granularidad.**
- d6: 6 resultados posibles. Para cosas rápidas (resoluciones).
- d20: 20 resultados. Para tablas medianas (encuentros, acciones).
- d100: 100 resultados. Para tablas grandes (temas, descripciones).

**Los rangos desiguales crean interés.** Un d20 donde cada resultado del 1 al 20 es único y equitativo es aburrido. Un d20 donde:
- 1-10 = resultado común (50%)
- 11-17 = resultado interesante (35%)
- 18-19 = resultado raro (10%)
- 20 = resultado muy raro (5%)
Esto crea emoción: "¡saqué 20 en el oráculo de clima!"

**No pongas 100 resultados si 20 son suficientes.** Un oráculo d100 con 100 entradas es difícil de diseñar bien. Muchas veces 20 resultados bien pensados son más útiles que 100 resultados genéricos.

### 21.2 Manteniendo el Catálogo

**Los oráculos del sistema (is_system=1) no deben modificarse a mano.**
- Se actualizan mediante migraciones SQL (ver migrate_weather_oracles.php).
- Si necesitas cambiar un oráculo del sistema, crea una migración o contacta al desarrollador.

**Los oráculos huérfanos no son un problema.** Si borras un oráculo, las ejecuciones pasadas en `game_post_oracles` se conservan. El post no se rompe — simplemente el nombre del oráculo ya no aparece en el catálogo.

**Revisa periódicamente los oráculos más usados.**
- Usa `SELECT o.name, COUNT(po.id) AS usages` para ver qué oráculos se tiran más.
- Si un oráculo nunca se usa, quizá está mal diseñado o es irrelevante.
- Si un oráculo se usa demasiado, quizá está resolviendo algo que debería ser decisión del staff.

### 21.3 ProTip: Variaciones Estratégicas

Usa variaciones por isla para dar identidad regional sin crear oráculos duplicados. Un mismo oráculo "Clima" puede tener:
- `results_json` base = clima de Grand Line genérico.
- `variations_json.Arabasta` = clima desértico.
- `variations_json.Drum` = clima ártico.
- `variations_json.Water7` = clima acuático.

Esto reduce el catálogo (un oráculo en lugar de 4) y facilita el mantenimiento (si cambias la lógica del oráculo, cambia en todas las islas a la vez).

### 21.4 ProTip: Auto-Invocación para Eventos Compuestos

Diseña oráculos de "resolución" que se auto-invocan desde oráculos de "encuentro":

```
"¿Qué hay en el Horizonte?" (d20)
  → Rango 6: "Barco de la Marina" → auto-invoca "Resolución — Encuentro naval"
  → Rango 8: "Rey del Mar" → auto-invoca "Resolución — Criatura marina"
```

Esto separa el "qué" del "cómo se resuelve", permitiendo reutilizar las resoluciones en múltiples oráculos de encuentro.

---

## 22. Consejos para Jugadores

### 22.1 Usando Oráculos en tus Posts

**Los oráculos son herramientas narrativas, no mecánicas.** No los uses para "ganar" ventaja — úsalos para descubrir qué pasa cuando no sabes qué escribir.

**Selecciona oráculos relevantes a tu escena.** Si estás navegando, selecciona oráculos de navegación. Si estás explorando una isla, selecciona oráculos de exploración. Un oráculo de "Acciones de la Tripulación" en medio de un combate no tiene sentido.

**No abuses de los oráculos.** Un post con 10 oráculos es abrumador de leer. 1-3 oráculos por post es suficiente. El sistema de navegación ya genera automáticamente los eventos de viaje — no necesitas añadir más.

### 22.2 Interpretando Resultados

**El resultado del oráculo es verdad dentro del juego.** Si el oráculo dice "Tormenta eléctrica", hay tormenta eléctrica. Escríbelo en tu post. No ignores el resultado porque no te conviene.

**Pero puedes interpretarlo creativamente.** "Tormenta eléctrica" no significa que tu personaje tenga que caer fulminado. Puede significar que busca refugio, que aprovecha el ruido para algo, o que usa la tormenta como cobertura narrativa.

**Los auto-invocados son sorpresas.** Si un resultado dice "Auto-invoca: Resolución — Criatura marina" y sacas "Ataque directo", ahora tienes una escena de combate que no planeaste. ¡Escríbela!

### 22.3 Errores Comunes

- **"Voy a seleccionar todos los oráculos disponibles."** El selector permite múltiples selecciones, pero no todas son relevantes. Elegir oráculos al azar sin contexto narrativo produce posts incoherentes.

- **"El oráculo no me gusta, lo ignoro."** El oráculo es vinculante. Si no quieres resultados aleatorios, no selecciones oráculos. Pero si los seleccionas, debes incorporar el resultado en tu post.

- **"Uso oráculos para evitar escribir."** Los oráculos son un REEMPLAZO de la incertidumbre, no un reemplazo de la narrativa. El resultado del oráculo debe inspirarte a escribir, no a poner "pasa esto" y ya.

---

## APÉNDICE A: Archivos del Subsistema

```
back/forum/game/
├── ajax/
│   ├── oracles_list.php              # GET — lista catálogo
│   ├── oracles_by_category.php       # GET — filtrado por categoría
│   ├── oracles_for_post.php          # GET — oráculos de un post
│   ├── oracles_create.php            # POST — crear oráculo
│   ├── oracles_update.php            # POST — actualizar oráculo
│   ├── oracles_delete.php            # POST — eliminar oráculo
│   └── cards_for_post.php            # GET — incluye oráculos en carga de post
├── inc/
│   ├── oracle_helpers.php            # Funciones de tirada y matching
│   └── navigation_process.php        # Eventos de navegación con oráculos
├── public/
│   └── oracles_staff.php             # Página staff de gestión
├── sql/
│   ├── migrate_oracles.php           # Migración principal
│   ├── migrate_navigation_system.php  # Oráculos de navegación base
│   ├── migrate_navigation_oracles_expand.php  # Oráculos expandidos
│   ├── migrate_weather_oracles.php   # Actualización de clima
│   ├── seed_oracles_example.php      # Seed de ejemplo (One Piece)
│   └── install_schema_fragments.php  # Fragmentos de instalación
├── src/Application/UseCases/
│   └── ProcessPostOracles.php        # Procesamiento al postear
└── jscripts/game/
    ├── oracles_ui.js                 # Frontend: renderizado y selector
    └── oracles_staff.js              # Staff tool CRUD

packages/contracts/
├── openapi/game-api.openapi.yaml     # Documentación OpenAPI
└── examples/
    ├── oracles_list.response.json
    ├── oracles_by_category.response.json
    ├── oracles_for_post.response.json
    ├── oracles_create.response.json
    ├── oracles_update.response.json
    └── oracles_delete.response.json

Guias/
├── MAESTRO_SISTEMAS_RPG.md           # Sección 23
└── sistemas/23-oraculos.md           # Este archivo
```

---

*Fin del documento — Guía completa del Sistema de Oráculos y Tiradas*
*Referencia: `Guias/MAESTRO_SISTEMAS_RPG.md` — Sección 23*
