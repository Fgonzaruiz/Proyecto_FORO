# 9. OFICIOS — GUÍA COMPLETA

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 9
> **Propósito:** Documentar exhaustivamente el subsistema de oficios: definición, categorías, grados I–V, modelo de datos, helpers PHP, flujos de adquisición y mejora, integración con cards y navegación, herramientas de staff, filosofía de diseño, y consejos para jugadores y staff.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [¿Qué es un Oficio?](#2-qué-es-un-oficio)
3. [Categorías de Oficios](#3-categorías-de-oficios)
4. [Grados (I–V)](#4-grados-i-v)
5. [Modelo de Datos](#5-modelo-de-datos)
6. [Servicios PHP — Capa de Helpers](#6-servicios-php)
7. [Flujo de Adquisición de Oficio](#7-flujo-de-adquisición-de-oficio)
8. [Flujo de Mejora de Grado](#8-flujo-de-mejora-de-grado)
9. [Integración con Cards](#9-integración-con-cards)
10. [Integración con Navegación](#10-integración-con-navegación)
11. [Herramientas de Staff](#11-herramientas-de-staff)
12. [Costes de Progresión Detallados](#12-costes-de-progresión-detallados)
13. [Filosofía de Diseño](#13-filosofía-de-diseño)
14. [Consejos para Jugadores](#14-consejos-para-jugadores)
15. [Consejos para Staff](#15-consejos-para-staff)
16. [Referencia Rápida](#16-referencia-rápida)

---

## 1. Arquitectura General

### 1.1 Capas del Subsistema

```
┌──────────────────────────────────────────────────────────────────────┐
│                      FRONTEND (Navegador)                             │
│  ┌──────────────────┐  ┌────────────────────┐  ┌──────────────────┐   │
│  │ personaje_page.js│  │ character_competen-│  │ crear_personaje  │   │
│  │ (Gestión →       │  │ cias_get.js        │  │ .js              │   │
│  │  Oficios)        │  │ (AJAX load)        │  │ (wizard step 2)  │   │
│  └────────┬─────────┘  └────────┬───────────┘  └────────┬─────────┘   │
│           │                     │                        │             │
│           ▼                     ▼                        ▼             │
│  ┌──────────────────────────────────────────────────────────────────┐ │
│  │              AJAX (game/ajax/*.php)                               │ │
│  │  oficios_list | acquire_competencia | upgrade_competencia_grado  │ │
│  │  character_competencias_get | oficios_save | navigation_context  │ │
│  └────────────────────────────┬─────────────────────────────────────┘ │
└───────────────────────────────┼───────────────────────────────────────┘
                                │ HTTP POST/GET + JSON
┌───────────────────────────────┼───────────────────────────────────────┐
│  ┌────────────────────────────▼──────────────────────────────────────┐│
│  │              PHP — CAPA DE HELPERS                                 ││
│  │  oficios_helpers.php       — CRUD de oficios (este documento)     ││
│  │  grado_helpers.php          — Grados I–V (compartido disciplinas) ││
│  │  navigation_helpers.php    — game_oficio_rank_bonus() en velocidad ││
│  └───────────────────────────────────────────────────────────────────┘│
│                              │                                         │
│                              ▼                                         │
│  ┌───────────────────────────────────────────────────────────────────┐│
│  │  MySQL — game_oficios + game_character_oficios                    ││
│  │  game_cards.oficio_slug (FK lógica)                               ││
│  │  game_personajes.data_json (cooldown + PP)                        ││
│  └───────────────────────────────────────────────────────────────────┘│
└───────────────────────────────────────────────────────────────────────┘
```

### 1.2 Relación Jerárquica

```
Mundo (One Piece)
  └── Personaje
        ├── Disciplinas de Combate (Sección 7)
        │     └── Combate, grados I–V, cards de técnica
        ├── Estilos Canónicos (Sección 8)
        │     └── Escuelas narrativas atadas a disciplina base
        ├── Oficios (este documento)
        │     └── Especializaciones no combativas, grados I–V
        ├── Haki (Sección 10)
        │     └── Poderes espirituales
        └── Akuma no Mi (Sección 11)
              └── Frutas del diablo
```

### 1.3 Filosofía de la Arquitectura

**¿Por qué helpers funcionales en lugar de clases Service?**

Los oficios y disciplinas comparten `grado_helpers.php` para el sistema de grados I–V. La diferencia clave es que `oficios_helpers.php` es más simple que `disciplinas_helpers.php`: los oficios no tienen reglas de adquisición especiales (no hay `requires_esp_rank`, `staff_grant_only` ni `fixed_pp_cost`), no se vinculan a estilos canónicos, y sus costes de grado son menores porque representan habilidades no combativas.

**¿Por qué tabla separada `game_character_oficios` en lugar de JSON en `data_json`?**

Las mismas razones que para disciplinas:
- **Consultas del staff:** "¿Qué personajes tienen Médico grado III?" responde con un JOIN directo sin escanear JSON.
- **FK lógica:** `oficio_id` → `game_oficios.id` evita slugs huérfanos.
- **Rendimiento:** JOIN simple en lugar de parsear JSON columnas al cargar la biblioteca de personajes.

**¿Por qué la ocupación inicial se guarda como `occupation`/`occupation_name` EN `game_personajes` y también como oficio en `game_character_oficios`?**

La columna `occupation` en `game_personajes` es el campo legacy del wizard de creación. Durante la migración al sistema de oficios v2, se añadió `game_character_oficios` como tabla normalizada, pero se mantuvo `occupation` para compatibilidad con vistas antiguas. Al cargar la ficha, si `game_character_oficios` está vacío, se usa `occupation_name` como fallback:

```php
// _sidebar.php:104
$sidebar_oficios = $char['oficios'] ?? game_oficio_list_for_character((int)$char['id']);
if ($sidebar_oficios === [] && !empty($char['job_name']) && $char['job_name'] !== 'Ninguno') {
    $sidebar_oficios = [[
        'name' => $char['job_name'],
        'rank_label' => 'I',
        'icon' => 'fa-briefcase',
    ]];
}
```

### 1.4 Impacto RPG

| Decisión arquitectónica | Lo que significa para el juego |
|------------------------|-------------------------------|
| Tabla separada `game_character_oficios` | Staff consulta "todos los médicos grado III+" en una query |
| Costes de oficio más baratos que disciplina | Ser un buen cocinero cuesta menos PP que ser un buen espadachín — refleja que el oficio es complementario |
| Cooldown global compartido con disciplinas | Subir tu oficio compite con subir tu disciplina. Hay tradeoff estratégico |
| `game_oficio_rank_bonus()` como bonus numérico | El grado del oficio tiene efectos mecánicos medibles (navegante → velocidad de barco) |

---

## 2. ¿Qué es un Oficio?

### 2.1 Definición

Un **oficio** es una especialización no combativa del personaje. Define su rol en la tripulación y sus capacidades de utilidad fuera del combate. Mientras que las disciplinas determinan cómo lucha un personaje, los oficios determinan qué puede hacer fuera del combate: cocinar, navegar, curar, construir, comerciar, espiar, etc.

### 2.2 Lo que un Oficio NO es

- **No es una clase:** Cualquier personaje puede tener cualquier oficio independientemente de su disciplina de combate. Un espadachín puede ser médico; un luchador cuerpo a cuerpo puede ser carpintero.
- **No es un reemplazo de stats:** Tener "Médico grado V" no te da INT alta. El oficio te permite acceder a cards y habilidades, pero las tiradas dependen de tus stats.
- **No es obligatorio:** Un personaje puede no tener ningún oficio (opción "Ninguno" en creación). Esto es válido para personajes que son puramente combatientes.

### 2.3 Diseño de la Progresión

Cada oficio progresa independientemente en grados I→V. Subir de grado:

1. **Cuesta PP** (menos que disciplinas: 50/90/130/190 vs 80/140/180/250).
2. **Requiere nivel global** (mismo sistema que disciplinas).
3. **Tiene cooldown real** (compartido con disciplinas — mismo reloj global).
4. **Requiere solicitud al staff** (no automático, como disciplinas).

**¿Por qué son más baratos que las disciplinas?** Porque los oficios son habilidades de utilidad, no de combate directo. Un personaje con un oficio alto no gana poder marcial directamente. El sistema reconoce esto con costes reducidos (~62% del coste de disciplina equivalente).

### 2.4 Oficios Disponibles (Catálogo Actual)

| Slug | Nombre | Categoría | Icono FA |
|------|--------|-----------|----------|
| `navegante` | Navegante | Utilidad | `fa-compass` |
| `medico` | Médico | Utilidad | `fa-heartbeat` |
| `cocinero` | Cocinero | Utilidad | `fa-utensils` |
| `domador` | Domador | Utilidad | `fa-paw` |
| `musico` | Músico/Artista | Utilidad | `fa-music` |
| `herrero` | Herrero | Crafteo | `fa-hammer` |
| `carpintero` | Carpintero | Crafteo | `fa-tools` |
| `cientifico` | Científico | Crafteo | `fa-flask` |
| `arqueologo` | Arqueólogo | Lore | `fa-scroll` |
| `espia` | Espía/Infiltrador | Sigilo | `fa-mask` |
| `mercader` | Mercader | Economía | `fa-coins` |

**Nota:** Este catálogo puede cambiar. El sistema permite crear, editar y desactivar oficios dinámicamente mediante el panel de staff (`oficios_save.php`). La guía se enfoca en la estructura y no en el contenido específico.

---

## 3. Categorías de Oficios

### 3.1 Utilidad

Oficios que proporcionan servicios esenciales a la tripulación: supervivencia, navegación, sanación, moral.

| Oficio | Slug | Qué hace |
|--------|------|----------|
| Navegante | `navegante` | Traza rutas, predice clima, navega entre islas. **Tiene efecto mecánico directo** en el sistema de navegación (aumenta velocidad del barco). |
| Médico | `medico` | Cura heridas, trata enfermedades, realiza cirugías, conoce anatomía. |
| Cocinero | `cocinero` | Prepara alimentos, gestiona provisiones, moraleja de tripulación, identifica ingredientes. |
| Domador | `domador` | Entrena y controla animales, doma bestias, comunicación con fauna. |
| Músico/Artista | `musico` | Entretenimiento, moral, actuaciones, arte, artesanía artística. |

**Bonificaciones típicas:** Los oficios de utilidad suelen proporcionar acceso a cards de tipo `equipo` o `npc_menor` (ej: un animal domesticado para domador, instrumentos musicales para músico). El navegante es el único con bonus mecánico directo y cuantificable en este grupo.

### 3.2 Crafteo

Oficios que permiten crear, reparar y modificar objetos.

| Oficio | Slug | Qué hace |
|--------|------|----------|
| Herrero | `herrero` | Forja y repara armas, armaduras, herramientas metálicas. |
| Carpintero | `carpintero` | Construye y repara barcos, muebles, estructuras de madera. |
| Científico | `cientifico` | Investiga, analiza muestras, crea pociones/medicinas, conocimientos académicos. |

**Bonificaciones típicas:** Los oficios de crafteo permiten acceder a cards de tipo `equipo` avanzado (armas forjadas por el propio herrero, barcos mejorados por el carpintero). El científico tiene potencial para crear objetos especiales (medicinas, explosivos).

### 3.3 Lore

| Oficio | Slug | Qué hace |
|--------|------|----------|
| Arqueólogo | `arqueologo` | Descifra textos antiguos, identifica artefactos, conoce historia del mundo, analiza ruinas. |

**Bonificaciones típicas:** Acceso a información vetada para otros personajes. Capacidad de identificar objetos misteriosos (incluyendo Akuma no Mi no identificadas). Ponepe glifos, historia del Siglo Vacío, secretos del mundo.

### 3.4 Sigilo

| Oficio | Slug | Qué hace |
|--------|------|----------|
| Espía/Infiltrador | `espia` | Reúne información, se infiltra en organizaciones, contrabando, seguimiento, disfraces. |

**Bonificaciones típicas:** Capacidad de obtener información que otros no pueden. Acceso a áreas restringidas en tramas. Puede tener cards de equipo específicas (herramientas de espionaje, disfraces).

### 3.5 Economía

| Oficio | Slug | Qué hace |
|--------|------|----------|
| Mercader | `mercader` | Compra y vende bienes, negocia precios, identifica valor de objetos, redes comerciales. |

**Bonificaciones típicas:** Descuentos en compras, mejores precios de venta, acceso a mercados exclusivos, identificación de objetos valiosos. Potencialmente vinculado al sistema de economía y `berries`.

---

## 4. Grados (I–V)

### 4.1 Escala de Grados

Los oficios usan el **mismo sistema de grados I–V que las disciplinas**, definido en `grado_helpers.php`. Los grados romanos miden maestría en el oficio, no poder bruto.

| Grado | Valor numérico | Etiqueta | Significado narrativo |
|:-----:|:--------------:|:--------:|----------------------|
| I | 1 | `I` | Novato. Conoces los fundamentos del oficio. |
| II | 2 | `II` | Aprendiz. Puedes realizar tareas básicas sin supervisión. |
| III | 3 | `III` | Competente. Eres fiable en tu oficio. La tripulación confía en ti. |
| IV | 4 | `IV` | Experto. Eres reconocido como un profesional destacado. |
| V | 5 | `V` | Maestro. Pocos en el mundo te superan en tu oficio. |

```php
// game_grado_label() en grado_helpers.php:8
function game_grado_label(int $rank): string
{
    $labels = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V'];
    return $labels[max(1, min(5, $rank))] ?? 'I';
}
```

Los wrappers específicos:

```php
// oficios_helpers.php:10
function game_oficio_rank_label(int $rank): string
{
    return game_grado_label($rank);
}
```

### 4.2 Bonus Numérico por Grado

Cada grado otorga un bonus numérico calculado por `game_grado_bonus()`:

```php
// grado_helpers.php:14
function game_grado_bonus(int $rank): float
{
    if ($rank <= 0) {
        return 0.0;
    }
    return (float)max(1, min(5, $rank)) * 0.5;
}
```

**Tabla de bonus:**

| Grado | Bonus |
|:-----:|:-----:|
| I | 0.5 |
| II | 1.0 |
| III | 1.5 |
| IV | 2.0 |
| V | 2.5 |

Este bonus se usa principalmente en el sistema de navegación (`game_oficio_rank_bonus()` como modificador de velocidad del barco), pero está disponible para cualquier uso mecánico futuro. El wrapper:

```php
// oficios_helpers.php:15
function game_oficio_rank_bonus(int $rank): float
{
    return game_grado_bonus($rank);
}
```

### 4.3 Requisitos por Grado

Los oficios comparten los mismos requisitos de nivel global que las disciplinas, pero con costes de PP reducidos:

| Grado | Nivel global requerido | PP cost (oficio) | Cooldown tras obtener |
|:-----:|:---------------------:|:-----------------:|:---------------------:|
| I | 1 (cualquier rango) | — (adquirir) | — |
| II | 2 (rango C) | 50 PP | 7 días |
| III | 3 (rango B) | 90 PP | 14 días |
| IV | 4 (rango A) | 130 PP | 21 días |
| V | 5 (rango S) | 190 PP | 30 días |

```php
// game_grado_nivel_required() en grado_helpers.php:23
function game_grado_nivel_required(int $targetRank): int
{
    $map = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5];
    return $map[max(1, min(5, $targetRank))] ?? 1;
}
```

```php
// game_grado_upgrade_price() en grado_helpers.php:33 — rama 'oficio'
function game_grado_upgrade_price(int $targetRank, string $competenciaType = 'disciplina'): int
{
    $disciplina = [2 => 80, 3 => 140, 4 => 180, 5 => 250];
    $oficio = [2 => 50, 3 => 90, 4 => 130, 5 => 190];
    $table = $competenciaType === 'oficio' ? $oficio : $disciplina;
    return $table[max(2, min(5, $targetRank))] ?? 0;
}
```

### 4.4 Mecánica del Cooldown

El cooldown es **global y compartido** con las disciplinas. Se almacena en `data_json`:

```json
{
    "pp": 320,
    "grado_last_upgrade_at": "2026-05-15 14:30:00",
    "grado_last_upgrade_rank": 3
}
```

**Reglas del cooldown (idénticas a disciplinas):**
1. Es global para disciplinas Y oficios. Subir Cuerpo a Cuerpo a II pone en cooldown TODAS las mejoras, incluidos oficios.
2. El contador se inicia cuando el staff aprueba la mejora.
3. Si el personaje tenía cooldown de una mejora a rango II (7 días) y luego sube a III, el nuevo cooldown es de 14 días desde esa fecha.

```php
// game_grado_cooldown_days_for_rank() en grado_helpers.php:42
function game_grado_cooldown_days_for_rank(int $targetRank): int
{
    $map = [2 => 7, 3 => 14, 4 => 21, 5 => 30];
    return $map[max(2, min(5, $targetRank))] ?? 7;
}
```

### 4.5 ¿Qué desbloquea cada grado?

| Grado | Tier de cards accesible | Narrativa |
|:-----:|:----------------------:|-----------|
| I | Tier 1 | Puedes realizar tareas básicas del oficio |
| II | Tier 2 | Eres fiable. La tripulación confía en ti para tareas rutinarias |
| III | Tier 3 | Eres competente. Puedes enseñar principios básicos a otros |
| IV | Tier 4 | Eres experto. Puedes innovar y crear tus propios métodos |
| V | Tier 5 | Eres un maestro del oficio. Referente mundial |

---

## 5. Modelo de Datos

### 5.1 `game_oficios` — Catálogo Maestro de Oficios

```sql
CREATE TABLE mybb_game_oficios (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    slug            VARCHAR(64) NOT NULL,
    name            VARCHAR(120) NOT NULL,
    description     TEXT,
    category        VARCHAR(64) NOT NULL DEFAULT 'oficio',
    icon            VARCHAR(64) NOT NULL DEFAULT 'fa-briefcase',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    sort_order      INT NOT NULL DEFAULT 0,
    grado_unlock_json JSON NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Campos:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT AUTO_INCREMENT | Clave primaria |
| `slug` | VARCHAR(64) | Identificador URL-friendly. UNIQUE. Ej: `medico`, `navegante` |
| `name` | VARCHAR(120) | Nombre visible. Ej: `Médico`, `Navegante` |
| `description` | TEXT | Descripción narrativa del oficio |
| `category` | VARCHAR(64) | Categoría: `Utilidad`, `Crafteo`, `Lore`, `Sigilo`, `Economía` |
| `icon` | VARCHAR(64) | Clase FontAwesome. Ej: `fa-heartbeat`, `fa-compass` |
| `is_active` | TINYINT(1) | 1 = visible en catálogo de adquisición, 0 = oculto |
| `sort_order` | INT | Orden de visualización en UI (ASC) |
| `grado_unlock_json` | JSON NULL | Descripción de desbloqueos por grado (texto informativo) |
| `created_at` | TIMESTAMP | Fecha de creación del registro |

**Diferencias clave con `game_disciplinas`:**
- No tiene `requires_esp_rank` — los oficios no requieren percepción especial.
- No tiene `staff_grant_only` — ningún oficio es exclusivo de staff.
- No tiene `fixed_pp_cost` — todos usan coste escalado por cantidad poseída.
- Tiene `category` más diversa (`Utilidad`, `Crafteo`, `Lore`, `Sigilo`, `Economía`) vs disciplinas (`combate`, `especial`).

### 5.2 `game_character_oficios` — Oficios del Personaje

```sql
CREATE TABLE mybb_game_character_oficios (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    character_id    INT NOT NULL,
    oficio_id       INT NOT NULL,
    `rank`          TINYINT UNSIGNED NOT NULL DEFAULT 1,
    learned_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_char_oficio (character_id, oficio_id),
    KEY idx_character (character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Campos:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT AUTO_INCREMENT | Clave primaria |
| `character_id` | INT | FK lógica a `game_personajes.id` |
| `oficio_id` | INT | FK lógica a `game_oficios.id` |
| `rank` | TINYINT UNSIGNED | Grado actual (1=I, 2=II, 3=III, 4=IV, 5=V). Default 1 |
| `learned_at` | TIMESTAMP | Cuándo se adquirió el oficio |

**UNIQUE KEY `uq_char_oficio`:** Un personaje no puede tener el mismo oficio dos veces. Cada oficio aparece una vez con su grado actual.

### 5.3 Datos de Progresión en `data_json`

Compartido con disciplinas. El cooldown es global:

```json
{
    "pp": 320,
    "pp_linaje": 0,
    "nivel": 3,
    "rank": "B",
    "grado_last_upgrade_at": "2026-05-15 14:30:00",
    "grado_last_upgrade_rank": 3
}
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `grado_last_upgrade_at` | string (datetime) | Última mejora de grado (disciplina u oficio) |
| `grado_last_upgrade_rank` | int | Grado alcanzado en esa última mejora (2–5) |

### 5.4 Datos de `grado_unlock_json`

El campo `grado_unlock_json` en `game_oficios` es informativo. Ejemplo:

```json
{
    "1": "Conocimientos básicos de primeros auxilios.",
    "2": "Puedes tratar heridas moderadas y enfermedades comunes.",
    "3": "Capacidad para realizar cirugías de campo.",
    "4": "Puedes crear medicamentos y antídotos.",
    "5": "Maestría médica: reconocido como un sanador excepcional."
}
```

Se parsea con `game_parse_grado_unlock_json()`:

```php
// grado_helpers.php:98
function game_parse_grado_unlock_json(mixed $unlocks): array
{
    if (is_string($unlocks) && $unlocks !== '') {
        $decoded = json_decode($unlocks, true);
        return is_array($decoded) ? $decoded : [];
    }
    return is_array($unlocks) ? $unlocks : [];
}
```

---

## 6. Servicios PHP — Capa de Helpers

### 6.1 `grado_helpers.php` — Sistema Compartido de Grados

Archivo: `back/forum/game/inc/grado_helpers.php`

Todas las funciones documentadas en la guía de disciplinas (Sección 7) aplican también a oficios. Las funciones clave con rama de oficio:

| Función | Relevancia para oficios |
|---------|------------------------|
| `game_grado_label(int $rank)` | Etiqueta romana I–V |
| `game_grado_bonus(int $rank)` | Bonus numérico: rank × 0.5 |
| `game_grado_nivel_required(int $targetRank)` | Nivel mínimo = targetRank (1:1) |
| `game_grado_upgrade_price(int $targetRank, 'oficio')` | 50/90/130/190 para grados 2–5 |
| `game_grado_cooldown_days_for_rank(int $targetRank)` | 7/14/21/30 días |
| `game_grado_cooldown_ok(?string $lastAt, ?int $lastRank)` | Verifica cooldown global |
| `game_grado_enrich_row(...)` | Enriquece fila con datos de upgrade |
| `game_grado_staff_apply_rank_change(...)` | Staff aplica cambio de grado |
| `game_card_assignment_competencia_error(...)` | Validación de oficio para cards |
| `game_get_acquisition_cost(int $alreadyOwned, 'oficio')` | Coste de adquirir nuevo oficio |

### 6.2 `oficios_helpers.php` — Helpers Específicos

Archivo: `back/forum/game/inc/oficios_helpers.php` (239 líneas)

Requiere: `grado_helpers.php`

#### `game_oficio_rank_label(int $rank): string`

```php
function game_oficio_rank_label(int $rank): string
{
    return game_grado_label($rank);
}
```

**Propósito:** Obtener la etiqueta romana (I–V) para un grado numérico.
**Parámetros:**
- `$rank`: int entre 1–5.
**Retorno:** string `'I'` a `'V'`.
**Edge cases:** Valores fuera de rango se clamp a 1–5.

---

#### `game_oficio_rank_bonus(int $rank): float`

```php
function game_oficio_rank_bonus(int $rank): float
{
    return game_grado_bonus($rank);
}
```

**Propósito:** Obtener el bonus numérico del grado (rank × 0.5).
**Parámetros:**
- `$rank`: int entre 1–5.
**Retorno:** float (0.5, 1.0, 1.5, 2.0, 2.5).
**Uso:** Sistema de navegación para calcular velocidad del barco.

---

#### `game_oficio_get_by_slug(string $slug): ?array`

```php
function game_oficio_get_by_slug(string $slug): ?array
{
    global $db;
    if (!$db->table_exists('game_oficios')) {
        return null;
    }
    $prefix = TABLE_PREFIX;
    $esc = $db->escape_string($slug);
    $q = $db->query("SELECT * FROM {$prefix}game_oficios WHERE slug = '{$esc}' AND is_active = 1 LIMIT 1");
    $row = $db->fetch_array($q);
    return $row ?: null;
}
```

**Propósito:** Buscar un oficio activo por slug.
**Parámetros:**
- `$slug`: string, slug del oficio (ej: `'medico'`).
**Retorno:** array asociativo con todos los campos de `game_oficios`, o `null` si no existe o está inactivo.
**Edge cases:**
- Si la tabla `game_oficios` no existe, retorna `null` (graceful degradation).
- Si el slug no se encuentra, retorna `null`.
- Solo retorna oficios con `is_active = 1`.
**Uso:** Asignación inicial desde job (`game_oficio_assign_initial_from_job`).

---

#### `game_oficio_get_rank(int $characterId, string $slug): int`

```php
function game_oficio_get_rank(int $characterId, string $slug): int
{
    global $db;
    if ($characterId <= 0 || !$db->table_exists('game_character_oficios') || !$db->table_exists('game_oficios')) {
        return 0;
    }
    $prefix = TABLE_PREFIX;
    $esc = $db->escape_string($slug);
    $q = $db->query("
        SELECT co.`rank`
        FROM {$prefix}game_character_oficios co
        JOIN {$prefix}game_oficios o ON o.id = co.oficio_id
        WHERE co.character_id = " . (int)$characterId . " AND o.slug = '{$esc}' AND o.is_active = 1
        LIMIT 1
    ");
    $row = $db->fetch_array($q);
    return $row ? (int)$row['rank'] : 0;
}
```

**Propósito:** Obtener el grado de un oficio específico para un personaje.
**Parámetros:**
- `$characterId`: int, ID del personaje.
- `$slug`: string, slug del oficio.
**Retorno:** int 0–5. 0 = no posee el oficio o inactivo.
**Edge cases:**
- Si `$characterId <= 0`, retorna 0.
- Si las tablas no existen, retorna 0.
- Si el personaje no tiene el oficio, retorna 0.
- Solo considera oficios activos (`is_active = 1`).
**Uso:** Validación de cards (`game_card_assignment_competencia_error`), contexto de navegación.

---

#### `game_oficio_list_for_character(int $characterId): array`

```php
function game_oficio_list_for_character(int $characterId): array
{
    global $db;
    if ($characterId <= 0 || !$db->table_exists('game_character_oficios')) {
        return [];
    }
    $prefix = TABLE_PREFIX;
    $q = $db->query("
        SELECT co.`rank`, co.learned_at, o.id, o.slug, o.name, o.description, o.category, o.icon, o.grado_unlock_json
        FROM {$prefix}game_character_oficios co
        JOIN {$prefix}game_oficios o ON o.id = co.oficio_id
        WHERE co.character_id = " . (int)$characterId . "
        ORDER BY o.sort_order ASC, o.name ASC
    ");
    $out = [];
    while ($row = $db->fetch_array($q)) {
        $rank = (int)$row['rank'];
        $row['rank_label'] = game_oficio_rank_label($rank);
        $row['rank_bonus'] = game_oficio_rank_bonus($rank);
        $row['grado_unlock'] = game_parse_grado_unlock_json($row['grado_unlock_json'] ?? null);
        unset($row['grado_unlock_json']);
        $out[] = $row;
    }
    return $out;
}
```

**Propósito:** Listar todos los oficios que posee un personaje, con metadatos enriquecidos.
**Parámetros:**
- `$characterId`: int, ID del personaje.
**Retorno:** `list<array>` — cada elemento incluye rank, rank_label, rank_bonus, id, slug, name, description, category, icon, learned_at, grado_unlock (array parseado).
**Edge cases:**
- Si `$characterId <= 0`, retorna `[]`.
- Si la tabla no existe, retorna `[]`.
- Incluye oficios inactivos (`is_active = 0`) que el personaje ya posee.
- Ordena por `sort_order` ASC, luego `name` ASC.
**Uso:** Sidebar de la ficha, catálogo de adquisición (para excluir poseídos), panel de gestión.

---

#### `game_oficio_list_catalog(bool $activeOnly = true): array`

```php
function game_oficio_list_catalog(bool $activeOnly = true): array
{
    global $db;
    if (!$db->table_exists('game_oficios')) {
        return [];
    }
    $prefix = TABLE_PREFIX;
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    $q = $db->query("SELECT * FROM {$prefix}game_oficios {$where} ORDER BY sort_order ASC, name ASC");
    $out = [];
    while ($row = $db->fetch_array($q)) {
        $out[] = $row;
    }
    return $out;
}
```

**Propósito:** Listar todo el catálogo de oficios.
**Parámetros:**
- `$activeOnly`: bool (default true). Si true, solo retorna oficios activos.
**Retorno:** `list<array>` — todos los campos de `game_oficios`.
**Edge cases:**
- Si la tabla no existe, retorna `[]`.
- Con `$activeOnly = false`, retorna también oficios desactivados (útil para staff).
**Uso:** Catálogo de adquisición para jugadores, panel staff (`zona_staff_oficios.php`).

---

#### `game_oficio_set_character_rank(int $characterId, int $oficioId, int $rank): bool`

```php
function game_oficio_set_character_rank(int $characterId, int $oficioId, int $rank): bool
{
    global $db;
    if ($characterId <= 0 || $oficioId <= 0 || !$db->table_exists('game_character_oficios')) {
        return false;
    }
    $prefix = TABLE_PREFIX;
    $rank = max(1, min(5, $rank));
    $cid = (int)$characterId;
    $oid = (int)$oficioId;

    $existing = $db->query("SELECT id FROM {$prefix}game_character_oficios WHERE character_id = {$cid} AND oficio_id = {$oid} LIMIT 1");
    if ($db->num_rows($existing)) {
        $db->write_query("UPDATE {$prefix}game_character_oficios SET `rank` = {$rank} WHERE character_id = {$cid} AND oficio_id = {$oid}");
    } else {
        $db->write_query("INSERT INTO {$prefix}game_character_oficios (character_id, oficio_id, `rank`) VALUES ({$cid}, {$oid}, {$rank})");
    }
    return true;
}
```

**Propósito:** Insertar o actualizar el grado de un oficio para un personaje.
**Parámetros:**
- `$characterId`: int, ID del personaje.
- `$oficioId`: int, ID del oficio.
- `$rank`: int, grado a establecer (1–5, se clamp automáticamente).
**Retorno:** bool (true si la operación se completó, false si parámetros inválidos o tabla no existe).
**Comportamiento:** UPSERT: si el personaje ya tiene el oficio, actualiza el rank; si no, inserta nuevo registro.
**Edge cases:**
- `$rank` se clamp a [1, 5] con `max(1, min(5, $rank))`.
- Si `$characterId <= 0` o `$oficioId <= 0`, retorna false (no hace nada).
**Uso:** Adquisición de oficio (rank 1), mejora de grado por staff, asignación inicial.

---

#### `game_oficio_remove_from_character(int $characterId, int $oficioId): bool`

```php
function game_oficio_remove_from_character(int $characterId, int $oficioId): bool
{
    global $db;
    if ($characterId <= 0 || $oficioId <= 0) {
        return false;
    }
    $prefix = TABLE_PREFIX;
    $db->write_query("DELETE FROM {$prefix}game_character_oficios WHERE character_id = " . (int)$characterId . " AND oficio_id = " . (int)$oficioId);
    return true;
}
```

**Propósito:** Eliminar un oficio de un personaje.
**Parámetros:**
- `$characterId`: int, ID del personaje.
- `$oficioId`: int, ID del oficio a remover.
**Retorno:** bool (true si la operación se completó, false si parámetros inválidos).
**Edge cases:** No verifica si el personaje realmente posee el oficio — el DELETE simplemente no afecta filas si no existe.
**Uso:** Solo staff (corrección de errores, reasignación).

---

#### `game_oficio_job_to_slug(string $job): ?string`

```php
function game_oficio_job_to_slug(string $job): ?string
{
    $job = trim($job);
    if ($job === '' || strcasecmp($job, 'Ninguno') === 0 || strcasecmp($job, 'Ninguno / Aprendiz') === 0) {
        return null;
    }
    $map = [
        'navegante' => 'navegante',
        'medico' => 'medico',
        'médico' => 'medico',
        'cocinero' => 'cocinero',
        'herrero' => 'herrero',
        'cientifico' => 'cientifico',
        'científico' => 'cientifico',
        'erudito' => 'cientifico',
        'timonel' => 'navegante',
    ];
    $key = strtolower(preg_replace('/[^a-záéíóúñ]/iu', '', $job) ?? '');
    if (isset($map[$key])) {
        return $map[$key];
    }
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $job) ?? '');
    $slug = trim($slug, '_');
    return $slug !== '' ? $slug : null;
}
```

**Propósito:** Convertir el nombre de un oficio (del wizard de creación, campo `occupation`) al slug del oficio correspondiente.
**Parámetros:**
- `$job`: string, nombre del oficio tal como viene del wizard.
**Retorno:** string (slug) o null si el job es "Ninguno" o vacío.
**Mapeo manual conocido:**
- `navegante` → `navegante`
- `medico` / `médico` → `medico`
- `cocinero` → `cocinero`
- `herrero` → `herrero`
- `cientifico` / `científico` / `erudito` → `cientifico`
- `timonel` → `navegante`
**Fallback:** Si no está en el mapa, intenta convertir el job a slug genérico (ej: `"Músico/Artista"` → `"musico_artista"`).
**Edge cases:**
- Entrada vacía → null.
- "Ninguno" o "Ninguno / Aprendiz" → null (personaje sin oficio).
- Acentuación: normaliza acentos para matching.
**Uso:** Asignación inicial en creación de personaje.

---

#### `game_oficio_assign_initial_from_job(int $characterId, string $job, int $rank = 1): void`

```php
function game_oficio_assign_initial_from_job(int $characterId, string $job, int $rank = 1): void
{
    $slug = game_oficio_job_to_slug($job);
    if ($slug === null) {
        return;
    }
    $oficio = game_oficio_get_by_slug($slug);
    if (!$oficio) {
        return;
    }
    game_oficio_set_character_rank($characterId, (int)$oficio['id'], $rank);
}
```

**Propósito:** Asignar el oficio inicial a un personaje durante la creación.
**Parámetros:**
- `$characterId`: int, ID del personaje recién creado.
- `$job`: string, nombre del oficio del wizard.
- `$rank`: int, grado inicial (default 1).
**Retorno:** void (silent fail si no hay slug o no se encuentra el oficio).
**Flujo:**
1. Convierte job → slug.
2. Busca el oficio activo por slug.
3. Si existe, asigna rank 1 al personaje.
**Edge cases:**
- Si el job es "Ninguno", no hace nada (personaje sin oficio inicial).
- Si el slug no corresponde a ningún oficio activo, no hace nada (log silencioso).
**Uso:** `save_personaje.php` después de crear el registro del personaje.

---

#### `game_oficio_count_for_character(int $characterId): int`

```php
function game_oficio_count_for_character(int $characterId): int
{
    global $db;
    if ($characterId <= 0 || !$db->table_exists('game_character_oficios')) {
        return 0;
    }
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT COUNT(*) AS c FROM {$prefix}game_character_oficios WHERE character_id = " . (int)$characterId);
    $row = $db->fetch_array($q);
    return $row ? (int)$row['c'] : 0;
}
```

**Propósito:** Contar cuántos oficios posee un personaje.
**Parámetros:**
- `$characterId`: int, ID del personaje.
**Retorno:** int, cantidad de oficios (0 si personaje inválido o tabla no existe).
**Uso:** Calcular `$alreadyOwned` para coste de adquisición escalado.

---

#### `game_oficio_character_owns(int $characterId, int $oficioId): bool`

```php
function game_oficio_character_owns(int $characterId, int $oficioId): bool
{
    global $db;
    if ($characterId <= 0 || $oficioId <= 0 || !$db->table_exists('game_character_oficios')) {
        return false;
    }
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT 1 FROM {$prefix}game_character_oficios
        WHERE character_id = " . (int)$characterId . " AND oficio_id = " . (int)$oficioId . " LIMIT 1");
    return (bool)$db->fetch_array($q);
}
```

**Propósito:** Verificar si un personaje posee un oficio específico.
**Parámetros:**
- `$characterId`: int, ID del personaje.
- `$oficioId`: int, ID del oficio.
**Retorno:** bool (true si posee el oficio en cualquier grado).
**Edge cases:**
- Si `$characterId <= 0` o `$oficioId <= 0`, retorna false.
- Si la tabla no existe, retorna false.
- No verifica `is_active` del oficio — si el personaje lo posee aunque esté desactivado, retorna true.
**Uso:** Prevenir adquisición duplicada en `acquire_competencia.php`.

---

#### `game_oficio_enrich_acquire_option(array $catalogRow, int $alreadyOwned, int $charNivel, int $ppAvailable): array`

```php
function game_oficio_enrich_acquire_option(array $catalogRow, int $alreadyOwned, int $charNivel, int $ppAvailable): array
{
    $cost = game_get_acquisition_cost($alreadyOwned, 'oficio');
    $nivelReq = game_get_acquisition_level_required($alreadyOwned);
    $reasons = [];
    if ($charNivel < $nivelReq) {
        $reasons[] = 'Requiere nivel ' . $nivelReq;
    }
    if ($ppAvailable < $cost) {
        $reasons[] = 'PP insuficientes (' . $cost . ')';
    }
    $unlocks = game_parse_grado_unlock_json($catalogRow['grado_unlock_json'] ?? null);

    return [
        'id' => (int)$catalogRow['id'],
        'slug' => (string)$catalogRow['slug'],
        'name' => (string)$catalogRow['name'],
        'description' => (string)($catalogRow['description'] ?? ''),
        'category' => (string)($catalogRow['category'] ?? ''),
        'icon' => (string)($catalogRow['icon'] ?? 'fa-briefcase'),
        'pp_cost' => $cost,
        'nivel_required' => $nivelReq,
        'can_acquire' => $reasons === [],
        'blocked_reason' => $reasons !== [] ? implode(' · ', $reasons) : '',
        'grado_unlock' => $unlocks,
        'unlock_preview' => (string)($unlocks['1'] ?? ''),
    ];
}
```

**Propósito:** Enriquece una fila del catálogo con datos de adquisición para mostrar en la UI.
**Parámetros:**
- `$catalogRow`: array, fila de `game_oficios`.
- `$alreadyOwned`: int, número de oficios que ya posee el personaje.
- `$charNivel`: int, nivel global del personaje (1–6).
- `$ppAvailable`: int, PP disponibles.
**Retorno:** array con id, slug, name, description, category, icon, pp_cost, nivel_required, can_acquire (bool), blocked_reason, grado_unlock, unlock_preview.
**Lógica:**
1. Calcula coste de adquisición según `$alreadyOwned` y tipo `'oficio'`.
2. Calcula nivel mínimo requerido.
3. Genera lista de razones por las que NO puede adquirir (si aplica).
4. Parsea `grado_unlock_json` para preview.
**Uso:** Catálogo de adquisición en UI de gestión.

---

#### `game_oficio_acquire_catalog_for_character(int $characterId, int $charNivel, int $ppAvailable): array`

```php
function game_oficio_acquire_catalog_for_character(int $characterId, int $charNivel, int $ppAvailable): array
{
    $ownedIds = [];
    foreach (game_oficio_list_for_character($characterId) as $row) {
        $ownedIds[(int)$row['id']] = true;
    }
    $alreadyOwned = count($ownedIds);
    $out = [];
    foreach (game_oficio_list_catalog(true) as $row) {
        if (isset($ownedIds[(int)$row['id']])) {
            continue;
        }
        $out[] = game_oficio_enrich_acquire_option($row, $alreadyOwned, $charNivel, $ppAvailable);
    }
    return $out;
}
```

**Propósito:** Generar el catálogo de oficios que un personaje PUEDE adquirir (excluyendo los que ya posee).
**Parámetros:**
- `$characterId`: int, ID del personaje.
- `$charNivel`: int, nivel global (1–6).
- `$ppAvailable`: int, PP disponibles.
**Retorno:** `list<array>` — lista de opciones enriquecidas (ver `game_oficio_enrich_acquire_option`).
**Flujo:**
1. Obtiene IDs de oficios ya poseídos.
2. Cuenta cuántos posee (para coste escalado).
3. Itera catálogo de oficios activos.
4. Excluye los que ya posee.
5. Enriquece cada opción con coste, nivel requerido y razones de bloqueo.
**Uso:** Endpoint `character_competencias_get.php` para llenar la UI "Adquirir Nuevo Oficio".

---

## 7. Flujo de Adquisición de Oficio

### 7.1 Actor: Jugador (Autoservicio)

El jugador adquiere un nuevo oficio desde el panel de gestión (Gestión > Disciplinas y Oficios > Adquirir Nuevas > pestaña "Oficio").

**Endpoint:** `POST /game/ajax/acquire_competencia.php`

**Payload:**
```json
{
    "character_id": 42,
    "type": "oficio",
    "catalog_id": 5,
    "_csrf": "token"
}
```

**Validaciones en orden (ramo oficio):**

```php
// acquire_competencia.php:65 — rama oficio
if ($type === 'oficio') {
    $catalog = $db->fetch_array($db->query(
        "SELECT * FROM {$prefix}game_oficios WHERE id = {$catalogId} AND is_active = 1 LIMIT 1"
    ));
    if (!$catalog) {
        GameAjax::json(false, null, ['code' => 404, 'message' => 'Oficio no encontrado en el catálogo.'], 404);
    }
    if (game_oficio_character_owns($characterId, $catalogId)) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Ya tienes este oficio.'], 400);
    }
    $alreadyOwned = game_oficio_count_for_character($characterId);
    $cost = game_get_acquisition_cost($alreadyOwned, 'oficio');
    $nivelReq = game_get_acquisition_level_required($alreadyOwned);
    if ($charNivel < $nivelReq) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Nivel insuficiente (requiere nivel ' . $nivelReq . ').'], 400);
    }
    if ($ppAvailable < $cost) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'PP insuficientes (requiere ' . $cost . ' PP).'], 400);
    }
    $name = (string)$catalog['name'];
    game_oficio_set_character_rank($characterId, $catalogId, 1);
}
```

**Validaciones completas:**
1. Login + CSRF + POST method.
2. PJ activo del usuario.
3. PJ existe y pertenece al usuario.
4. PJ está aprobado (`status === 'aprobada'`).
5. `type` es `'oficio'`.
6. `catalog_id` existe en `game_oficios` y está activo.
7. El PJ no posee ya este oficio.
8. Nivel global suficiente para la cantidad de oficios poseídos.
9. PP suficientes (coste escalado por cantidad poseída).

**Ejecución:**
1. Se descuentan los PP de `data_json`.
2. Se inserta registro en `game_character_oficios` con `rank = 1` (vía `game_oficio_set_character_rank`).
3. Se envía notificación al jugador.
4. Se devuelve respuesta JSON.

```php
// acquire_competencia.php:113
$data['pp'] = max(0, $ppAvailable - $cost);
// Persiste data_json
game_oficio_set_character_rank($characterId, $catalogId, 1);
```

**Respuesta JSON exitosa:**
```json
{
    "ok": true,
    "data": {
        "character_id": 42,
        "type": "oficio",
        "catalog_id": 5,
        "name": "Médico",
        "rank": 1,
        "rank_label": "I",
        "pp_spent": 100,
        "new_pp": 220,
        "nivel": 2
    }
}
```

### 7.2 Costes de Adquisición por Cantidad Poseída

El coste de adquirir un nuevo oficio sigue la misma curva escalada que las disciplinas, pero con valores reducidos (~62%):

```php
// grado_helpers.php:70
function game_get_acquisition_cost(int $alreadyOwned, string $competenciaType = 'disciplina'): int
{
    $disciplina = [0, 0, 150, 350, 750, 1400, 2500, 4000];
    $oficio = [0, 0, 100, 250, 550, 1000, 1800, 3000];
    $costs = $competenciaType === 'oficio' ? $oficio : $disciplina;
    $index = $alreadyOwned + 1;
    if ($index < count($costs)) {
        return $costs[$index];
    }
    $cap = $competenciaType === 'oficio' ? 3500 : 4500;
    $base = $competenciaType === 'oficio' ? 3000 : 4000;
    $step = $competenciaType === 'oficio' ? 400 : 500;
    return min($cap, $base + ($alreadyOwned - 6) * $step);
}
```

| Oficios ya poseídos | Coste del siguiente | Nivel mínimo requerido |
|:-------------------:|:-------------------:|:----------------------:|
| 0 | 0 PP (gratis, primero) | 1 |
| 1 | 100 PP | 2 |
| 2 | 250 PP | 3 |
| 3 | 550 PP | 4 |
| 4 | 1.000 PP | 5 |
| 5 | 1.800 PP | 6 |
| 6 | 3.000 PP | 6 |
| 7+ | 3.500 PP (cap) | 6 |

### 7.3 Comparativa: Oficios vs Disciplinas (Costes de Adquisición)

| Cantidad poseída | Coste oficio | Coste disciplina | Diferencia |
|:----------------:|:------------:|:----------------:|:----------:|
| 0 → 1 (gratis) | 0 PP | 0 PP | 0 |
| 1 → 2 | 100 PP | 150 PP | −33% |
| 2 → 3 | 250 PP | 350 PP | −29% |
| 3 → 4 | 550 PP | 750 PP | −27% |
| 4 → 5 | 1.000 PP | 1.400 PP | −29% |
| 5 → 6 | 1.800 PP | 2.500 PP | −28% |
| Cap máximo | 3.500 PP | 4.500 PP | −22% |

### 7.4 Actor: Staff (Asignación Directa)

El staff puede asignar oficios directamente desde `zona_staff_oficios.php` mediante el panel de asignación rápida:

```html
<!-- zona_staff_oficios.php:58 -->
<div class="rpg-staff-section">
    <h2><i class="fas fa-user-tag"></i> Asignar oficio a personaje</h2>
    <div class="rpg-form-row">
        <input type="number" id="assign-char-id" placeholder="ID personaje" />
        <select id="assign-oficio-id">
            <option value="1">Navegante</option>
            <!-- ... -->
        </select>
        <select id="assign-rank">
            <option value="1">Grado I</option>
            <!-- ... -->
        </select>
        <button type="button" id="btn-assign-oficio">Asignar</button>
    </div>
</div>
```

**Casos de uso staff:**
- Asignar un oficio como recompensa de trama (sin coste de PP).
- Corregir un grado si hubo error.
- Otorgar un oficio a un NPC.
- Remover un oficio (en casos excepcionales).

### 7.5 Flujo: Primer Oficio (Creación de Personaje)

Al crear un personaje, se asigna el oficio inicial automáticamente desde el campo `job` del wizard:

```php
// En save_personaje.php (a través de CharacterSaveService)
game_oficio_assign_initial_from_job($characterId, $job, 1);
```

```php
// oficios_helpers.php:153
function game_oficio_assign_initial_from_job(int $characterId, string $job, int $rank = 1): void
{
    $slug = game_oficio_job_to_slug($job);
    if ($slug === null) {
        return;
    }
    $oficio = game_oficio_get_by_slug($slug);
    if (!$oficio) {
        return;
    }
    game_oficio_set_character_rank($characterId, (int)$oficio['id'], $rank);
}
```

El primer oficio es **gratis** (0 PP) y se asigna en **grado I**.

**Mapeo job → slug (de `game_oficio_job_to_slug`):**

| Job en wizard (occupation) | Slug asignado |
|---------------------------|---------------|
| Navegante, Timonel | `navegante` |
| Médico | `medico` |
| Cocinero | `cocinero` |
| Herrero | `herrero` |
| Científico, Erudito | `cientifico` |
| Carpintero | `carpintero` |
| Domador | `domador` |
| Arqueólogo | `arqueologo` |
| Músico/Artista | `musico` |
| Espía | `espia` |
| Mercader | `mercader` |
| Ninguno / Aprendiz | (sin oficio) |

---

## 8. Flujo de Mejora de Grado

### 8.1 Solicitud del Jugador

Idéntico al flujo de disciplinas: el jugador solicita la subida, el staff revisa y aprueba.

**Endpoint:** `POST /game/ajax/upgrade_competencia_grado.php`

**Payload:**
```json
{
    "character_id": 42,
    "type": "oficio",
    "catalog_id": 5,
    "_csrf": "token"
}
```

**Validaciones (rama oficio):**

```php
// upgrade_competencia_grado.php:62 — rama oficio
if ($type === 'oficio') {
    $row = $db->fetch_array($db->query("
        SELECT co.`rank`, o.name
        FROM {$prefix}game_character_oficios co
        JOIN {$prefix}game_oficios o ON o.id = co.oficio_id
        WHERE co.character_id = {$characterId} AND co.oficio_id = {$catalogId}
        LIMIT 1
    "));
    if (!$row) {
        GameAjax::json(false, null, ['code' => 404, 'message' => 'No tienes este oficio.'], 404);
    }
    $currentRank = (int)$row['rank'];
    $name = (string)$row['name'];
}

if ($currentRank >= 5) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Grado máximo (V) alcanzado.'], 400);
}

$nextRank = $currentRank + 1;
$reqNivel = game_grado_nivel_required($nextRank);
$price = game_grado_upgrade_price($nextRank, $type); // 'oficio' → tabla barata

// Validaciones: nivel, PP, cooldown
```

**Validaciones completas:**
1. Login, CSRF, POST, ownership, PJ aprobado.
2. El oficio existe y el PJ lo posee en `game_character_oficios`.
3. El grado actual no es el máximo (V).
4. Nivel global suficiente para el siguiente grado.
5. PP suficientes (coste de oficio: 50/90/130/190).
6. Cooldown global ha expirado.

**No se descuentan PP ni se actualiza el grado en este punto.** La solicitud se envía al staff para revisión:

```php
// upgrade_competencia_grado.php:117
// Se notifica a todo el staff (staff_level >= 2)
$staffLink = $bburl . '/game/public/zona_staff_oficios.php';
```

### 8.2 Revisión y Aprobación del Staff

El staff usa `game_grado_staff_apply_rank_change()` (de `grado_helpers.php`) para validar y ejecutar:

```php
// grado_helpers.php:258
function game_grado_staff_apply_rank_change(
    int $characterId, int $oldRank, int $newRank, string $competenciaType = 'disciplina'
): ?string
{
    // 1. Valida nivel global para cada grado intermedio
    for ($r = $oldRank + 1; $r <= $newRank; $r++) {
        if ($charNivel < game_grado_nivel_required($r)) {
            return 'Nivel insuficiente...';
        }
    }
    // 2. Calcula coste total (puede ser multi-grado)
    $cost = game_grado_upgrade_total_price($oldRank, $newRank, $competenciaType);
    if ($pp < $cost) {
        return 'PP insuficientes...';
    }
    // 3. Verifica cooldown
    if (!game_grado_cooldown_ok($lastUpgrade, $lastUpgradeRank)) {
        return 'Cooldown activo...';
    }
    // 4. Descuenta PP y registra cooldown
    $data['pp'] = max(0, $pp - $cost);
    $data['grado_last_upgrade_at'] = date('Y-m-d H:i:s');
    $data['grado_last_upgrade_rank'] = $newRank;
    // Persiste
}
```

Luego actualiza el rank en `game_character_oficios`:

```php
game_oficio_set_character_rank($characterId, $oficioId, $newRank);
```

### 8.3 Costes de Grado: Oficios vs Disciplinas

| Grado | PP (oficio) | PP (disciplina) | Diferencia |
|:-----:|:-----------:|:---------------:|:----------:|
| I → II | 50 | 80 | −37.5% |
| II → III | 90 | 140 | −35.7% |
| III → IV | 130 | 180 | −27.8% |
| IV → V | 190 | 250 | −24.0% |
| **Total I→V** | **460 PP** | **650 PP** | **−29.2%** |

### 8.4 Cooldown (Compartido con Disciplinas)

El cooldown es **global**: si el personaje subió una disciplina a grado III ayer, no puede solicitar mejora de oficio hasta que pasen los días correspondientes.

| Nuevo grado | Cooldown |
|:-----------:|:--------:|
| II | 7 días |
| III | 14 días |
| IV | 21 días |
| V | 30 días |

### 8.5 Diferencia Clave con Disciplinas

A diferencia de las disciplinas, los oficios **no tienen reglas especiales de adquisición**:
- No hay `staff_grant_only` — ningún oficio es exclusivo.
- No hay `requires_esp_rank` — no requieren percepción especial.
- No hay `fixed_pp_cost` — siempre usan coste escalado por cantidad poseída.

Esto hace que el flujo de oficios sea más simple que el de disciplinas.

---

## 9. Integración con Cards

### 9.1 Oficio como Requisito de Card

Toda card en el catálogo (`game_cards`) puede tener un requisito de oficio mediante el campo `oficio_slug`:

```sql
CREATE TABLE mybb_game_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- ... otros campos ...
    oficio_slug VARCHAR(64) NULL,
    -- ...
);
```

**Regla:** Si `oficio_slug` está definido, el personaje debe tener ese oficio en grado **I o superior**. A diferencia de las disciplinas (que requieren un `tier` específico), los oficios solo requieren poseer el oficio — no importa el grado (rank ≥ 1).

```sql
-- Card que requiere oficio de médico
INSERT INTO mybb_game_cards (name, card_type, rank, tier, oficio_slug, dice)
VALUES ('Botiquín de Campo', 'equipo', 'D', 1, 'medico', '1d4+int [CURACION]');

-- Card que requiere oficio de navegante
INSERT INTO mybb_game_cards (name, card_type, rank, tier, oficio_slug, dice)
VALUES ('Carta Náutica Detallada', 'equipo', 'C', 1, 'navegante', NULL);
```

### 9.2 Validación en Asignación de Cards

Cuando el staff asigna una card a un personaje (`cards_assign.php`), se ejecuta `game_card_assignment_competencia_error()`:

```php
// grado_helpers.php:314
function game_card_assignment_competencia_error(int $characterId, array $card): ?string
{
    $tier = max(1, min(5, (int)($card['tier'] ?? 1)));
    $discSlug = trim((string)($card['disciplina_slug'] ?? ''));
    if ($discSlug !== '') {
        // Validación de disciplina (ver guía 07-disciplinas)
    }

    $ofSlug = trim((string)($card['oficio_slug'] ?? ''));
    if ($ofSlug !== '') {
        if (game_oficio_get_rank($characterId, $ofSlug) < 1) {
            return 'Requiere oficio «' . $ofSlug . '».';
        }
    }

    return null;
}
```

**Diferencia clave con disciplina:**
- **Disciplina:** Requiere `rank >= tier` (el grado debe ser igual o superior al tier de la card).
- **Oficio:** Solo requiere `rank >= 1` (poseer el oficio, independientemente del grado).

Esto es intencional: un médico grado I ya sabe primeros auxilios (puede usar un botiquín). No necesita grado V para usar una card de curación básica.

### 9.3 Cards que Requieren Oficio + Disciplina

Una card puede requerir AMBOS:

```sql
-- Card que requiere oficio de médico + disciplina cuerpo_a_cuerpo
INSERT INTO mybb_game_cards (name, card_type, rank, tier, disciplina_slug, oficio_slug, dice)
VALUES ('Acupuntura Marcial', 'tecnica', 'C', 2, 'cuerpo_a_cuerpo', 'medico', '1d8+des [PERFORANTE]');
```

La validación se ejecuta secuencialmente: primero disciplina (grado ≥ tier), luego oficio (rank ≥ 1).

### 9.4 Cards Sin Oficio

La mayoría de las cards no requieren oficio. Solo aquellas que representan habilidades específicas de un oficio llevan `oficio_slug`. Por ejemplo:
- Un "Botiquín de Campo" requiere `medico`.
- Un "Cuchillo de Cocina" requiere `cocinero`.
- Una "Espada Básica" NO requiere oficio (es un arma, no una herramienta de oficio).

---

## 10. Integración con Navegación

### 10.1 Navegante como Oficio Mecánico

El oficio `navegante` es el único oficio con un efecto mecánico directo y cuantificable: modifica la velocidad efectiva del barco en el sistema de navegación.

```php
// navigation_helpers.php:98
function game_nav_effective_speed(array $shipEffects, string $seaZone, int $navigatorRank, string $instrument): float
{
    $base = (float)($shipEffects['velocidad_base'] ?? $shipEffects['velocidad'] ?? 5);
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

**Efecto del grado de navegante en velocidad:**

| Grado navegante | Bonus velocidad |
|:---------------:|:---------------:|
| No tiene (0) | +0.0 |
| I | +0.5 |
| II | +1.0 |
| III | +1.5 |
| IV | +2.0 |
| V | +2.5 |

### 10.2 Contexto de Navegación

El endpoint `navigation_context.php` expone el bonus del navegante al frontend:

```php
// navigation_context.php:33
GameAjax::json(true, [
    // ...
    'navegante_rank' => $naveganteRank,
    'navegante_label' => $naveganteRank > 0 ? game_oficio_rank_label($naveganteRank) : null,
    'navegante_bonus' => game_oficio_rank_bonus($naveganteRank),
    // ...
]);
```

### 10.3 Cálculo de Duración de Viaje

```php
// navigation_process.php:93
$instrumentBonus = (int)round($effSpeed - $baseSpeed - game_oficio_rank_bonus($navigatorRank));
```

El sistema de navegación usa `game_oficio_rank_bonus()` para separar el bonus del navegante del bonus del instrumento, permitiendo auditoría granular.

### 10.4 Implicaciones

- Un personaje **sin** oficio de navegante puede navegar (usando instrumentos), pero su velocidad base será menor.
- Un navegante grado V (+2.5 de velocidad) puede reducir significativamente la duración de los viajes.
- Esto crea un incentivo mecánico real para tener el oficio de navegante, más allá del narrativo.

---

## 11. Herramientas de Staff

### 11.1 `oficios_list.php` — Listar Catálogo

**Endpoint:** `GET /game/ajax/oficios_list.php`

```php
// oficios_list.php
$activeOnly = !isset($_GET['all']) || $_GET['all'] !== '1';
$catalog = game_oficio_list_catalog($activeOnly);
GameAjax::json(true, ['oficios' => $catalog]);
```

**Parámetros GET:**
- `all=1`: incluye oficios inactivos (solo staff).

**Uso:** Cargar el catálogo para el panel staff y para la UI de adquisición.

### 11.2 `oficios_save.php` — Crear/Editar Oficio

**Endpoint:** `POST /game/ajax/oficios_save.php`

**Requisito:** Staff level ≥ 3 (superadmin).

**Payload:**
```json
{
    "id": 0,
    "slug": "nuevo_oficio",
    "name": "Nuevo Oficio",
    "description": "Descripción del nuevo oficio.",
    "category": "Utilidad",
    "icon": "fa-star",
    "is_active": 1,
    "sort_order": 10
}
```

**Comportamiento:**
- Si `id > 0`: actualiza el oficio existente.
- Si `id = 0`: inserta nuevo oficio (valida slug único).

```php
// oficios_save.php:40
if ($id > 0) {
    $db->write_query("UPDATE {$prefix}game_oficios SET ... WHERE id = {$id}");
} else {
    // Valida slug único
    $dup = $db->query("SELECT 1 FROM {$prefix}game_oficios WHERE slug = '{$escSlug}' LIMIT 1");
    if ($db->num_rows($dup)) {
        GameAjax::fail(409, 'Ya existe un oficio con ese slug');
    }
    $db->insert_query('game_oficios', [...]);
}
```

**Validaciones:**
- Staff level ≥ 3.
- `slug`: solo caracteres `[a-z0-9_]`.
- `name`: obligatorio.
- `slug`: obligatorio y único.

### 11.3 `zona_staff_oficios.php` — Panel de Gestión

**Archivo:** `back/forum/game/public/zona_staff_oficios.php`

**Acceso:** Staff level ≥ 3.

**Funcionalidades:**
1. **Listar oficios** — Tabla con slug, nombre, categoría, activo, botón editar.
2. **Crear/Editar oficio** — Modal con campos: slug, name, description, category, icon, is_active.
3. **Asignar oficio a personaje** — Formulario rápido: ID personaje + oficio + grado.

**Campos editables desde el modal:**
- `slug`: Identificador único (solo [a-z0-9_]).
- `name`: Nombre visible.
- `description`: Descripción narrativa.
- `category`: Categoría (texto libre, se recomiendmay: Utilidad, Crafteo, Lore, Sigilo, Economía).
- `icon`: Clase FontAwesome (ej: `fa-compass`).
- `is_active`: Checkbox para activar/desactivar.

**Campos NO editables desde el modal:**
- `grado_unlock_json`: Debe editarse directamente en DB (no hay UI para esto aún).
- `sort_order`: Se asigna automáticamente o se edita en DB.

### 11.4 Creación de Nuevos Oficios

Para crear un nuevo oficio:

1. **INSERT en `game_oficios`** (vía modal staff o SQL directo):
   ```sql
   INSERT INTO mybb_game_oficios (slug, name, description, category, icon, is_active, sort_order)
   VALUES ('minero', 'Minero', 'Especialista en excavación y minería.', 'Crafteo', 'fa-pick', 1, 20);
   ```

2. **Opcional: Añadir `grado_unlock_json`:**
   ```json
   {
       "1": "Puedes identificar minerales comunes.",
       "2": "Capacidad para excavar en terrenos duros.",
       "3": "Puedes encontrar vetas de minerales raros.",
       "4": "Maestría en minería de materiales preciosos.",
       "5": "Reconocido experto en geología y minería."
   }
   ```

3. **Opcional: Crear cards asociadas** (con `oficio_slug = 'minero'`).

4. **Notificar a jugadores** sobre el nuevo oficio disponible.

### 11.5 Asignación Directa a Personajes

El staff puede asignar oficios directamente desde el panel:

```javascript
// zona_staff_oficios.js
// POST a character_oficios_save.php (o endpoint similar)
// Payload: { character_id, oficio_id, rank }
```

**Casos de uso:**
- Recompensa de evento/trama (sin coste PP).
- Corrección de errores en asignación.
- NPCs que necesitan oficios específicos.

---

## 12. Costes de Progresión Detallados

### 12.1 Resumen de Costes (Oficios)

| Concepto | Coste en PP | Nivel mínimo | Cooldown | ¿Requiere staff? |
|----------|:-----------:|:------------:|:--------:|:----------------:|
| Adquirir 1er oficio | 0 (gratis) | 1 | — | No |
| Adquirir 2do oficio | 100 | 2 | — | No |
| Adquirir 3er oficio | 250 | 3 | — | No |
| Adquirir 4to oficio | 550 | 4 | — | No |
| Adquirir 5to oficio | 1.000 | 5 | — | No |
| Subir grado I→II | 50 | 2 | 7 días | Sí |
| Subir grado II→III | 90 | 3 | 14 días | Sí |
| Subir grado III→IV | 130 | 4 | 21 días | Sí |
| Subir grado IV→V | 190 | 5 | 30 días | Sí |

### 12.2 Coste Acumulado: Oficio Individual I→V

| Salto | Coste por salto | Coste acumulado |
|:-----:|:---------------:|:---------------:|
| I → II | 50 PP | 50 PP |
| II → III | 90 PP | 140 PP |
| III → IV | 130 PP | 270 PP |
| IV → V | 190 PP | 460 PP |

**Total: 460 PP para dominar un oficio por completo.** En perspectiva, dominar una disciplina cuesta 650 PP (41% más caro).

### 12.3 Coste Comparativo: Oficios vs Disciplinas

| Concepto | Oficio | Disciplina | Diferencia |
|----------|:------:|:----------:|:----------:|
| Adquirir 2da | 100 PP | 150 PP | −33% |
| Adquirir 3ra | 250 PP | 350 PP | −29% |
| Grado I→II | 50 PP | 80 PP | −37% |
| Grado II→III | 90 PP | 140 PP | −36% |
| Grado III→IV | 130 PP | 180 PP | −28% |
| Grado IV→V | 190 PP | 250 PP | −24% |
| **Masterizar (I→V)** | **460 PP** | **650 PP** | **−29%** |

### 12.4 Escenario: Personaje con 2 Oficios en Grado III

| Concepto | Coste |
|----------|:-----:|
| 1er oficio (gratis, creación) | 0 PP |
| 2do oficio (adquirir) | 100 PP |
| 1er oficio I→II + II→III | 50 + 90 = 140 PP |
| 2do oficio I→II + II→III | 50 + 90 = 140 PP |
| **Total** | **380 PP** |

Equivalente a ~76 posts de 500 palabras (~2–3 meses). Es accesible para un jugador activo que quiera un personaje con oficio sólido además de su entrenamiento de combate.

---

## 13. Filosofía de Diseño

### 13.1 ¿Por qué separar Oficios de Disciplinas?

La separación entre oficios (no combativos) y disciplinas (combativas) es una decisión de diseño fundamental:

1. **Claridad conceptual:** Un personaje sabe luchar (disciplina) y sabe hacer algo útil fuera del combate (oficio). Son dos ejes ortogonales de progresión.
2. **Justicia mecánica:** Un médico no debería pagar los mismos PP que un espadachín por mejorar su habilidad. Curar heridas no es tan poderoso como cortar enemigos.
3. **Especialización narrativa:** Los oficios definen el rol del personaje en la tripulación. Un cocinero no es "menos importante" que un combatiente — son roles diferentes.

### 13.2 ¿Por qué oficios más baratos que disciplinas?

**Premisa:** Los oficios son habilidades de utilidad, no de combate directo.

- Un médico grado V no gana poder marcial. Puede curar mejor, pero sigue siendo un blanco fácil en combate cuerpo a cuerpo.
- Un herrero grado V puede forjar armas, pero no necesariamente sabe usarlas.
- El coste reducido refleja que el beneficio mecánico de un oficio es situacional o indirecto, no universal como una disciplina de combate.

**Excepción:** El navegante tiene un efecto mecánico medible (velocidad de barco), pero sigue siendo un bonus de utilidad, no de combate.

### 13.3 ¿Por qué grados I–V y no otra escala?

Los oficios usan el **mismo sistema de grados que las disciplinas** por consistencia:
- Unificar el sistema de cooldown global (compartido).
- Reutilizar `grado_helpers.php` sin duplicación.
- El jugador ve "grado III" y sabe exactamente qué significa, ya sea en disciplina u oficio.

La diferencia está en los **costes**, no en la escala.

### 13.4 ¿Por qué el oficio inicial viene del wizard de creación?

En la creación del personaje, el campo `occupation` (job) selecciona el oficio inicial. Esto vincula la identidad del personaje con su rol desde el primer momento:

- "Soy médico" → oficio `medico`.
- "Soy cocinero" → oficio `cocinero`.
- "No tengo oficio" → `Ninguno` (sin oficio inicial, puede adquirir después).

Esta decisión fuerza al jugador a pensar en QUÉ hace su personaje cuando no está peleando, no solo en cómo pelea.

### 13.5 ¿Por qué algunos oficios tienen efectos mecánicos y otros no?

Actualmente, solo el oficio `navegante` tiene un efecto mecánico directo y cuantificable (modificador de velocidad). Los demás oficios son puramente narrativos (acceso a cards, información, tramas).

**Razón:**
1. **Complejidad incremental:** Implementar efectos mecánicos para cada oficio requeriría sistemas adicionales (economía para mercader, bestiario para domador, crafteo para herrero, etc.).
2. **Flexibilidad narrativa:** Dejar los oficios sin efectos mecánicos estrictos permite al staff y jugadores interpretarlos libremente en la narrativa.
3. **Futuro:** El sistema está diseñado para añadir efectos mecánicos cuando se implementen los subsistemas correspondientes (ej: sistema de economía → bonus para mercader; sistema de crafting → bonus para herrero).

### 13.6 Principios Rectores

1. **Utilidad > Poder:** Los oficios mejoran lo que el personaje puede HACER, no lo FUERTE que es.
2. **Coste justo:** Los oficios cuestan menos PP que las disciplinas porque su impacto en el balance de combate es menor.
3. **Complementariedad:** Un personaje con disciplina alta y oficio alto es más completo que uno con solo disciplina alta.
4. **Incentivo a la variedad:** El coste escalado por cantidad poseída permite tener 2–3 oficios a precio razonable, pero tener 5+ se vuelve prohibitivo.
5. **Sin requisitos especiales:** A diferencia de las disciplinas (que pueden requerir ESP, staff_grant, coste fijo), los oficios son accessibles a cualquier personaje.

---

## 14. Consejos para Jugadores

### 14.1 Elegir tu Oficio Inicial

**El oficio inicial es gratis y en grado I.** Aprovéchalo para definir el rol de tu personaje:

| Perfil de personaje | Oficio recomendado | Por qué |
|--------------------|--------------------|---------|
| Tripulante de soporte | Médico o Cocinero | Siempre útil en cualquier tripulación |
| Explorador solitario | Navegante | Te permite moverte entre islas con bonus de velocidad |
| Artesano/inventor | Herrero o Carpintero | Si tu personaje construye cosas |
| Intelectual | Científico o Arqueólogo | Si tu personaje investiga o descifra misterios |
| Pícaro/infiltratrado | Espía | Si tu personaje opera en las sombras |
| Comerciante | Mercader | Si tu personaje negocia y comercia |
| Combatiente puro | Ninguno | Válido para personajes que solo saben pelear |

### 14.2 ¿Cuántos Oficios Tener?

| Perfil | Oficios recomendados | Estrategia |
|--------|:--------------------:|------------|
| Nuevo jugador | 1 (el inicial) | Domínalo a grado II–III antes de pensar en otro |
| Jugador activo (3+ meses) | 1–2 | Principal (grado III–IV) + secundario (grado I–II) |
| Veterano (1+ año) | 2–3 | Especialización + versatilidad |
| Rol de tripulación | 1 | Un solo oficio bien desarrollado define tu rol |

**Regla de oro:** Un oficio en grado III es más valioso que tres oficios en grado I. La profundidad pesa más que la amplitud.

### 14.3 Sinergias Oficio + Disciplina

| Combinación | Arquetipo | Cómo funciona |
|-------------|-----------|---------------|
| `medico` + `cuerpo_a_cuerpo` | Médico de combate | Cura en medio de la batalla, conoce puntos de presión |
| `navegante` + `armas_a_distancia` | Vigía/tirador | Navega y dispara desde la cofa |
| `cocinero` + `armas_de_filo` | Chef espadachín | Corta verduras y enemigos con igual maestría |
| `herrero` + `armas_contundentes` | Forjador de martillos | Forja sus propias armas contundentes |
| `cientifico` + `armas_exoticas` | Inventor loco | Crea artefactos y armas experimentales |
| `espia` + `cuerpo_a_cuerpo` | Agente encubierto | Sigilo + combate desarmado silencioso |
| `mercader` + `armas_de_fuego` | Mercader armado | Negocia y, si es necesario, dispara |

### 14.4 Estrategia de Progresión

**Fase 1 (1–2 meses): Enfócate en tu oficio inicial.**
- Lleva tu oficio a grado II (50 PP) — es barato y te abre cards de tier 2.
- No adquieras un segundo oficio hasta que el primero esté al menos en grado II.

**Fase 2 (2–4 meses): Profundiza o expande.**
- Opción A: Lleva tu oficio a grado III (+90 PP = 140 PP total).
- Opción B: Adquiere un segundo oficio (100 PP) y llévalo a II (50 PP).

**Fase 3 (4+ meses): Especialización.**
- Oficio principal en grado IV (+130 PP) o V (+190 PP).
- Oficios secundarios en grado II–III.

### 14.5 Lo que NO te dice el sistema

- **El oficio no reemplaza las tiradas de stats.** Tener `medico V` no te garantiza curar una herida grave si tu INT es 1. Las cards de oficio usan stats para las tiradas.
- **El cooldown es compartido con disciplinas.** Si subes tu oficio a grado III, no puedes subir tu disciplina a grado III hasta que pase el cooldown. Planifica tus mejoras.
- **El staff puede rechazar tu solicitud** si no has roleado el oficio IC. Un médico que nunca ha curado a nadie en sus posts no debería subir a grado III.
- **Los oficios sin mecánica no son inútiles.** Tener `arqueologo V` te convierte en la autoridad del foro sobre historia antigua. Eso tiene valor narrativo y de trama.

### 14.6 Ejemplo de Plan de Progresión (Médico)

1. **Creación:** Eliges `medico` como oficio inicial (gratis, grado I).
2. **Posts 1–20 (1 mes):** Roleas curaciones básicas en tu tripulación. Acumulas ~200 PP.
3. **Subes a grado II (50 PP):** Solicitud al staff. Ahora puedes usar cards de tier 2 (cirugías básicas).
4. **Decides adquirir un oficio secundario:** `cientifico` (100 PP, grado I) — sinergiza con medicina para crear medicamentos.
5. **Posts 21–40 (2 meses):** Alternas curaciones e investigación. Acumulas ~200 PP más.
6. **Subes `medico` a grado III (90 PP):** Total gastado en oficios: 50 + 100 + 90 = 240 PP.
7. **Resultado:** Médico grado III + Científico grado I. Puedes curar heridas graves y crear medicamentos básicos.

---

## 15. Consejos para Staff

### 15.1 Evaluación de Solicitudes de Grado de Oficio

Cada solicitud de subida de grado de oficio debe evaluarse contra tres criterios:

**1. Mecánico (objetivo):**
- ¿El personaje tiene el nivel global requerido?
- ¿Tiene los PP necesarios (50/90/130/190)?
- ¿El cooldown ha expirado?
- Validaciones automáticas del sistema, pero confirmar.

**2. Narrativo (subjetivo):**
- ¿El personaje ha ejercido su oficio IC desde que obtuvo el grado actual?
- ¿Hay posts recientes que muestren práctica del oficio?
- Ej: un médico debe haber curado heridas; un carpintero debe haber reparado algo.
- ¿El nuevo grado tiene sentido en la historia del personaje?

**3. Progresión (estratégico):**
- ¿El personaje está subiendo demasiado rápido? (ej: oficio I→III en una semana).
- ¿El jugador está descuidando sus disciplinas de combate en favor de oficios?
- ¿Hay coherencia entre la actividad del personaje y su progresión de oficio?

### 15.2 Criterios de Rechazo Comunes (Oficios)

| Motivo | Ejemplo | Acción sugerida |
|--------|---------|-----------------|
| Sin práctica IC | "Pido cocinero III pero mi personaje ha estado en combates 3 meses" | Rechazar y sugerir posts de cocina |
| Salto irreal | "Pido médico V cuando empecé como grumete hace 2 semanas" | Rechazar (el nivel mínimo lo impediría automáticamente) |
| Incoherencia | "Mi personaje ermitaño pide mercader IV" | Pedir justificación narrativa |
| Multisolicitud | "Pide subida de oficio y disciplina el mismo día" | Recordar cooldown global compartido |

### 15.3 Creación de Nuevos Oficios

Al crear un nuevo oficio, considera:

1. **¿Cubre un nicho no representado?** Ej: actualmente no hay oficio de "Pescador" ni "Granjero" — si el foro necesita uno, créalo.
2. **¿Tendrá efecto mecánico o será puramente narrativo?** Decide desde el principio para planificar cards y sistemas.
3. **Categorización:** Asigna una categoría existente o crea una nueva. Las categorías actuales son: Utilidad, Crafteo, Lore, Sigilo, Economía.
4. **Cards asociadas:** Crea al menos 1–2 cards por tier que usen el oficio como requisito.
5. **Mapeo job→slug:** Si el nuevo oficio puede elegirse en creación, añade el mapeo en `game_oficio_job_to_slug()`.

**Template para nuevo oficio:**

```sql
INSERT INTO mybb_game_oficios (slug, name, description, category, icon, is_active, sort_order, grado_unlock_json)
VALUES (
    'pescador',
    'Pescador',
    'Experto en pesca, conocimiento de criaturas marinas y navegación costera.',
    'Utilidad',
    'fa-fish',
    1,
    30,
    '{"1":"Pesca básica con caña.","2":"Conocimiento de especies marinas comunes.","3":"Pesca en aguas peligrosas.","4":"Maestría en técnicas de pesca avanzadas.","5":"Rey de los pescadores."}'
);
```

### 15.4 Balance de Oficios

**¿Son todos los oficios igualmente valiosos?**

No, y está bien. `navegante` es el único con efecto mecánico directo. `medico` y `cocinero` son los más versátiles narrativamente. `arqueologo` es nicho pero muy poderoso en tramas de lore.

**El balance está en las cards, no en el oficio.** Si un oficio tiene pocas cards asociadas, los jugadores no lo elegirán. El staff puede equilibrar creando cards interesantes para oficios subrepresentados.

**Cómo detectar desbalance:**
- Revisa `game_character_oficios` periódicamente: ¿qué oficios son los más comunes?
- Si >50% de los personajes tienen `medico` o `navegante`, tal vez necesitas incentivar otros oficios.
- Si un oficio no lo tiene NADIE, pregúntate: ¿es poco atractivo? ¿Faltan cards? ¿Nadie sabe que existe?

### 15.5 Cuándo Otorgar un Oficio por Trama

Los oficios pueden otorgarse como recompensa de trama sin coste de PP:

- Un personaje que completa un arco de entrenamiento con un herrero legendario podría obtener `herrero` gratis.
- Un personaje que descifra un glifo antiguo podría obtener `arqueologo`.
- Un personaje que sobrevive una tormenta navegando solo podría obtener `navegante`.

**Regla:** Si el oficio se otorga por trama, el staff asigna grado I directamente, sin coste de PP para el jugador. Las mejoras posteriores sí requieren PP y solicitud normal.

### 15.6 Migraciones y Ajustes Masivos

- **Añadir oficio:** INSERT en `game_oficios` + crear cards asociadas. Los personajes no se ven afectados.
- **Desactivar:** UPDATE `is_active = 0`. Los oficios existentes se conservan, pero no aparecen en catálogo de adquisición.
- **Renombrar:** UPDATE `slug` y `name`. Actualizar `oficio_slug` en cards.
- **Eliminar:** No recomendado. Mejor desactivar. Si es inevitable, DELETE en cascada (oficio → character_oficios → actualizar cards con oficio_slug a NULL).

---

## 16. Referencia Rápida

### 16.1 Tabla de Oficios (Catálogo Actual)

| Slug | Nombre | Categoría | Icono |
|------|--------|-----------|-------|
| `navegante` | Navegante | Utilidad | `fa-compass` |
| `medico` | Médico | Utilidad | `fa-heartbeat` |
| `cocinero` | Cocinero | Utilidad | `fa-utensils` |
| `domador` | Domador | Utilidad | `fa-paw` |
| `musico` | Músico/Artista | Utilidad | `fa-music` |
| `herrero` | Herrero | Crafteo | `fa-hammer` |
| `carpintero` | Carpintero | Crafteo | `fa-tools` |
| `cientifico` | Científico | Crafteo | `fa-flask` |
| `arqueologo` | Arqueólogo | Lore | `fa-scroll` |
| `espia` | Espía/Infiltrador | Sigilo | `fa-mask` |
| `mercader` | Mercader | Economía | `fa-coins` |

### 16.2 Costes de Grado

| Grado | Nivel req. | PP (oficio) | PP (disciplina) | Cooldown | Tier de cards |
|:-----:|:----------:|:-----------:|:---------------:|:--------:|:-------------:|
| I | 1 | — (adquirir) | — | — | 1 |
| II | 2 | 50 | 80 | 7 días | 2 |
| III | 3 | 90 | 140 | 14 días | 3 |
| IV | 4 | 130 | 180 | 21 días | 4 |
| V | 5 | 190 | 250 | 30 días | 5 |

### 16.3 Costes de Adquisición

| N oficios | Coste | Nivel req. |
|:---------:|:-----:|:----------:|
| 1ro (inicial) | 0 PP | 1 |
| 2do | 100 PP | 2 |
| 3ro | 250 PP | 3 |
| 4to | 550 PP | 4 |
| 5to | 1.000 PP | 5 |
| 6to | 1.800 PP | 6 |
| 7mo | 3.000 PP | 6 |
| Cap | 3.500 PP | 6 |

### 16.4 Bonus de Grado

| Grado | `game_oficio_rank_bonus()` |
|:-----:|:--------------------------:|
| I | 0.5 |
| II | 1.0 |
| III | 1.5 |
| IV | 2.0 |
| V | 2.5 |

### 16.5 Archivos Relevantes

| Archivo | Propósito |
|---------|-----------|
| `Guias/MAESTRO_SISTEMAS_RPG.md` | Sección 9 — Definición general |
| `Guias/sistemas/09-oficios.md` | **Este archivo** |
| `back/forum/game/inc/oficios_helpers.php` | Helpers de oficios (239 líneas) |
| `back/forum/game/inc/grado_helpers.php` | Helpers de grados (compartido disciplinas) |
| `back/forum/game/ajax/acquire_competencia.php` | Adquirir oficio |
| `back/forum/game/ajax/upgrade_competencia_grado.php` | Solicitar mejora de grado |
| `back/forum/game/ajax/oficios_list.php` | Listar catálogo (AJAX) |
| `back/forum/game/ajax/oficios_save.php` | Crear/editar oficio (staff) |
| `back/forum/game/public/zona_staff_oficios.php` | Panel staff de gestión |
| `back/forum/game/sql/install_schema_fragments.php` | Schema SQL (`game_oficios` + `game_character_oficios`) |
| `back/forum/game/inc/navigation_helpers.php` | Uso de `game_oficio_rank_bonus()` en navegación |
| `back/forum/game/ajax/navigation_context.php` | Contexto de navegación (incluye bonus navegante) |
| `Guias/sistemas/07-disciplinas.md` | Guía de disciplinas (mismo sistema de grados) |

### 16.6 Diagrama de Relaciones

```
game_oficios (catálogo)
    │
    ├── game_character_oficios (por personaje)
    │       └── rank (I–V)
    │
    ├── game_cards.oficio_slug (FK lógica)
    │       └── Requisito: rank ≥ 1 para usar la card
    │
    └── game_personajes.occupation (legacy)
            └── Sincronizado con game_character_oficios al crear

game_personajes.data_json
    ├── pp (se descuenta al adquirir/mejorar)
    ├── grado_last_upgrade_at (cooldown tracker — GLOBAL con disciplinas)
    └── grado_last_upgrade_rank (último grado alcanzado)

game_nav_effective_speed()
    └── game_oficio_rank_bonus(navigatorRank) → modificador de velocidad
```
