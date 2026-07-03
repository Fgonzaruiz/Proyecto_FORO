# 5. SISTEMA DE CARDS (CARTAS) — GUÍA COMPLETA

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 5
> **Propósito:** Documentar exhaustivamente el subsistema de cards: modelo de datos, tipos, rangos, activación, campos mecánicos, adquisición, integración con posts, equipamiento, validaciones, flujos de staff — y **por qué** existe cada decisión de diseño, cómo impacta en el RPG, y consejos para jugadores y staff.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [Modelo de Datos — Tabla `game_cards`](#2-modelo-de-datos)
3. [Tablas Auxiliares](#3-tablas-auxiliares)
4. [Tipos de Card (`card_type`)](#4-tipos-de-card)
5. [Rangos de Card (D → SS)](#5-rangos-de-card)
6. [Modos de Activación](#6-modos-de-activación)
7. [Campos Mecánicos Clave](#7-campos-mecánicos-clave)
8. [Proceso de Adquisición de Cards](#8-proceso-de-adquisición-de-cards)
9. [Sistema de Equipamiento e Inventario](#9-sistema-de-equipamiento)
10. [Cards en Posts — Procesamiento](#10-cards-en-posts)
11. [Integración con la Ficha de Personaje](#11-integración-con-la-ficha)
12. [AJAX Endpoints — Catálogo Completo](#12-ajax-endpoints)
13. [Validaciones y Seguridad](#13-validaciones-y-seguridad)
14. [API de Cards (OpenAPI)](#14-api-de-cards)
15. [Filosofía de Diseño](#15-filosofía-de-diseño)
16. [Consejos para Jugadores](#16-consejos-para-jugadores)
17. [Consejos para Staff](#17-consejos-para-staff)
18. [Referencia Rápida](#18-referencia-rápida)

---

## 1. Arquitectura General

### 1.1 Qué es una Card

Una **card** (carta) es la representación mecánica de una habilidad, objeto, aliado o recurso del personaje. **Toda acción con impacto mecánico real debe estar respaldada por una card.**

En el sistema RPG del foro, las cards son el puente entre la narrativa (lo que el jugador escribe en su post) y la mecánica (la tirada de dados, el coste de PE, el daño). Sin una card, una acción es puramente narrativa y no tiene efecto mecánico.

### 1.2 Capas del Subsistema

```
┌─────────────────────────────────────────────────────────────────────┐
│                       CLIENTE (Navegador)                           │
│  ┌─────────────────┐  ┌──────────────────┐  ┌───────────────────┐   │
│  │ personaje_page  │  │ personaje_inven- │  │ post_form.js      │   │
│  │ .js (Deck tab)  │  │ tory.js          │  │ (rpg_played_cards)│   │
│  └────────┬────────┘  └────────┬─────────┘  └────────┬──────────┘   │
│           │                    │                       │              │
│           ▼                    ▼                       ▼              │
│  ┌──────────────────────────────────────────────────────────────────┐│
│  │              AJAX (game/ajax/*.php)                               ││
│  │  cards_my_deck | cards_play | cards_for_post | cards_list        ││
│  │  cards_create | cards_update | cards_delete | cards_assign       ││
│  │  cards_request_custom | cards_resolve_request                    ││
│  │  inventory_get | inventory_toggle | shop_catalog_list            ││
│  └────────────────────────────┬─────────────────────────────────────┘│
└───────────────────────────────┼──────────────────────────────────────┘
                                │ HTTP POST/GET + JSON
┌───────────────────────────────┼──────────────────────────────────────┐
│  ┌────────────────────────────▼─────────────────────────────────────┐│
│  │              PHP — CAPA DE APLICACIÓN                             ││
│  │  UseCases: ProcessPostCards, ProcessPostOracles                   ││
│  │  Helpers:  inventory_helpers, grado_helpers, stat_helpers        ││
│  │  Plugin:   game_postcharacter.php (hooks MyBB)                   ││
│  └──────────────────────────────────────────────────────────────────┘│
│                              │                                       │
│                              ▼                                       │
│  ┌──────────────────────────────────────────────────────────────────┐│
│  │           MySQL (MyBB + tablas game_*)                           ││
│  │  game_cards + game_character_cards + game_character_inventory   ││
│  │  game_post_cards + game_card_requests + game_haki_progress      ││
│  └──────────────────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────────────┘
```

### 1.3 Filosofía de la Arquitectura

**¿Por qué las cards son una abstracción universal?**

En lugar de tener tablas separadas para `habilidades`, `objetos`, `aliados`, `barcos`, el sistema unifica todo bajo el concepto de **card**. Esto permite:

1. **Un solo punto de validación** — El sistema de asignación, equipamiento y uso en posts es idéntico para cualquier tipo de card. No hay que escribir código diferente para equipar un arma vs equipar un barco.
2. **Un solo catálogo** — Los jugadores y el staff trabajan con una única tabla `game_cards`. Buscar, crear, editar o eliminar cartas usa los mismos endpoints y flujos.
3. **Extensibilidad** — Si en el futuro se necesita un nuevo tipo de card (ej: `vehiculo`, `fortaleza`), solo se añade un valor al ENUM `card_type`. No se requieren nuevas tablas ni nuevos flujos de código.
4. **Inventario unificado** — La tabla `game_character_cards` es el inventario de cualquier personaje. No hay que hacer JOINs entre 5 tablas de inventario diferentes.

**¿Por qué separar la card base del inventario del personaje?**

`game_cards` es el **catálogo maestro**: la definición inamovible de la card (su tipo, dados, coste). `game_character_cards` es el **inventario por personaje**: qué cards posee y a qué rango las tiene. Esto permite:

- La misma card puede tener rangos diferentes en personajes distintos (un personaje tiene `Espada X` en rango B, otro en rango A).
- Una card en catálogo puede ser `equipo` y venderse en la tienda, pero cada personaje que la compra obtiene su propia instancia en `game_character_cards`.
- Si se actualiza el catálogo (ej: se corrige una descripción), todos los personajes ven el cambio sin migraciones.

**¿Por qué `current_rank` en `game_character_cards`?**

El rango de una card en el inventario de un personaje NO es necesariamente el rango base del catálogo. Un personaje puede tener `Espada Y` en catálogo con rango C, pero si el personaje es hábil (tiene disciplina alta), el staff puede asignarle la card con rango B o A. El `current_rank` es el rango EFECTIVO de esa card para ese personaje.

Esto permite:
- Cards que escalan con el personaje (rank up de cards vía solicitud al staff).
- Asignar versiones nerfeadas/buffed de una misma card a personajes de distinto nivel.
- Diferenciar la card base (catálogo) de su manifestación en cada personaje.

### 1.4 Impacto RPG

| Decisión arquitectónica | Lo que significa para el juego |
|------------------------|-------------------------------|
| Cards como abstracción universal | Aprende un sistema, úsalo para todo: técnicas, equipo, Haki, barcos, NPCs |
| Catálogo maestro separado | El staff crea una card una vez; puede asignarla a N personajes sin duplicar datos |
| Rango por personaje | Un veterano con una técnica rango S es más efectivo que un novato con la misma técnica en rango C |
| Sin backend externo | Las cards existen en la DB del foro; cargan instantáneamente en la ficha |

### 1.5 Principios de Diseño

1. **Card-first**: Toda acción mecánica requiere una card. No hay tiradas de stats "sueltas".
2. **Catálogo centralizado**: Las cards se crean en `game_cards` y se asignan a personajes; no hay cards "huérfanas".
3. **Rango por personaje**: Cada personaje tiene su propio rango para cada card.
4. **Validación en asignación**: Las cards con requisitos de disciplina/haki se validan al asignarse, no al usarse.
5. **Snapshot por post**: Cada post guarda qué cards estaban equipadas en ese momento, permitiendo auditoría.

---

## 2. Modelo de Datos — Tabla `game_cards`

### 2.1 Definición SQL Completa

```sql
CREATE TABLE mybb_game_cards (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(150) NOT NULL,
    card_type           ENUM('tecnica', 'equipo', 'akuma_no_mi', 'haki', 'npc_menor', 'barco') NOT NULL,
    `rank`              ENUM('D', 'C', 'B', 'A', 'S', 'SS') NOT NULL DEFAULT 'C',
    activation          ENUM('activa', 'pasiva', 'reactiva') NOT NULL DEFAULT 'activa',
    tags_json           TEXT,
    description         TEXT,
    cost_pe             VARCHAR(50) DEFAULT '—',
    execution_cost      INT NOT NULL DEFAULT 0,
    execution_stat      VARCHAR(10) DEFAULT '',
    dice                VARCHAR(150) DEFAULT '',
    effects_json        TEXT,
    notes               TEXT,
    image_url           VARCHAR(500) DEFAULT '',
    cost_berries        INT NOT NULL DEFAULT 0,
    in_shop             TINYINT(1) NOT NULL DEFAULT 0,
    shop_category       VARCHAR(50) DEFAULT 'utiles',
    peso                INT NOT NULL DEFAULT 1,
    created_by          INT NOT NULL,
    reposo              INT NOT NULL DEFAULT 0,
    duracion            INT NOT NULL DEFAULT 0,
    tier                TINYINT UNSIGNED NOT NULL DEFAULT 1,
    disciplina_slug     VARCHAR(64) NULL,
    estilo_canonico_slug VARCHAR(64) NULL,
    oficio_slug         VARCHAR(64) NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_type (card_type),
    KEY idx_rank (`rank`),
    KEY idx_shop (in_shop, card_type, cost_berries),
    KEY idx_estilo_canonico (estilo_canonico_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Campos — Descripción Detallada

#### `id` — Identificador único
- Autoincremental. Clave primaria del catálogo de cards.
- Referenciado como `card_id` en: `game_character_cards`, `game_character_inventory`, `game_post_cards`, `game_card_requests`, `game_haki_progress`, `game_navigation_voyages`.

#### `name` — Nombre de la card
- VARCHAR(150). No hay UNIQUE constraint — dos cards pueden llamarse igual si son de distinto tipo o creadas en contextos diferentes (ej: "Puño de Hierro" como técnica y como equipo).
- Se muestra en: fichas, tienda, deck, posts, resultados de tiradas.

#### `card_type` — Tipo de card
- ENUM con 6 valores: `tecnica`, `equipo`, `akuma_no_mi`, `haki`, `npc_menor`, `barco`.
- **NO se puede cambiar el tipo de una card existente** sin crear una nueva. El ENUM es restrictivo.
- Determinante para qué slots de inventario ocupa, cómo se procesa en posts, si requiere equipamiento, y cómo se muestra en la ficha.

#### `rank` — Rango base de la card
- ENUM: `D`, `C`, `B`, `A`, `S`, `SS`. Default: `C`.
- Este es el rango que la card tiene en el catálogo. Cuando se asigna a un personaje, se copia a `game_character_cards.current_rank`, pero puede ser modificado por el staff.
- El rango de la card determina: coste de PE base, tier mínimo requerido, poder de dados escalado.

#### `activation` — Modo de activación
- ENUM: `activa`, `pasiva`, `reactiva`.
- Define cuándo y cómo se declara la card en un post.

#### `tags_json` — Etiquetas de sistema
- TEXT conteniendo JSON array de strings. Ej: `["FUEGO", "CONSUMIBLE", "MUNICION", "PERFORANTE", "VENENO"]`.
- No hay catálogo fijo de tags — son libres, pero el sistema reconoce ciertas tags especiales:
  - `CONSUMIBLE`, `MUNICION`, `AMMO` — marcan la card como consumible (se decrementa al usarse)
  - `REUTILIZABLE` — puede usarse múltiples veces (default para técnicas)
  - `UNICA` — el personaje solo puede tener 1 copia aunque sea consumible
- Las tags son informativas para el frontend y para reglas de negocio específicas.

#### `description` — Descripción narrativa
- TEXT. Descripción libre de la card: qué hace, cómo se ve, cómo funciona en la narrativa.
- Visible en: tienda, deck, detalle de card, resultados de post.

#### `cost_pe` — Coste en PE para activar
- VARCHAR(50) — NO es numérico porque puede contener fórmulas o rangos.
- Valores típicos: `"—"` (sin coste), `"15"`, `"25"`, `"10-20"` (rango variable), `"5% PE total"` (porcentual).
- Se interpreta en el frontend y en la revisión de staff; el sistema no valida automáticamente el PE gastado (es declarativo por post).

#### `execution_cost` — Coste adicional de ejecución
- INT, default 0. Coste en PP/PA adicional para ejecutar la card.
- Ejemplos: `0` (sin coste extra), `2` (cuesta 2 PA adicionales), `5` (cuesta 5 PP adicionales).

#### `execution_stat` — Stat de ejecución
- VARCHAR(10). Slug del stat usado para escalar la card.
- Valores: `fue`, `res`, `agi`, `des`, `int`, `inst`, `esp`, o vacío `""`.
- Cuando se especifica, el sistema añade automáticamente `+{stat}` a la fórmula de dados al procesar la card en un post (para cards de tipo `equipo` con subtipo `arma`).
- Ejemplo: Si el arma tiene `dice = "2d8"` y `execution_stat = "fue"`, la fórmula se convierte a `2d8+fue` al jugarse.

#### `dice` — Fórmula de dados
- VARCHAR(150). Fórmula de tirada usando sintaxis del sistema de oráculos.
- Sintaxis soportada:
  - Dados: `2d20`, `1d6`, `4d8`
  - Stats: `fue`, `res`, `agi`, `des`, `int`, `inst`, `esp`
  - Multiplicadores: `2*fue`, `fue*2`, `int/2`, `3*des`
  - Modificadores planos: `+5`, `-10`
  - Tags: `[FUEGO]`, `[CORTANTE]` al final de la fórmula
  - Placeholders: `[ARMA]` se reemplaza con la fórmula del arma equipada, `[MUNICION]` con la fórmula de munición
- Ejemplos:
  - `"2d8+fue [CORTANTE]"` — 2d8 + stat FUE, tag CORTANTE
  - `"3d6+des*2 [PERFORANTE]"` — 3d6 + (DES×2), tag PERFORANTE
  - `"[ARMA]+1d6 [FUEGO]"` — dado del arma + 1d6, tag FUEGO
- Si está vacío o `"—"`, la card no produce tirada mecánica (es narrativa pura).

#### `effects_json` — Efectos mecánicos
- TEXT conteniendo JSON objeto. Estructura variable según `card_type`:

**Para `equipo`:**
```json
{
    "equipo_type": "arma",
    "subtipo": "espada",
    "manos": 1,
    "alcance": "corto",
    "durabilidad": 100,
    "bonus_stats": {"fue": 1},
    "efectos_especiales": ["sangrado"]
}
```

```json
{
    "equipo_type": "armadura",
    "parte": "torso",
    "defensa": 5,
    "penalizador_agi": -1,
    "material": "acero"
}
```

```json
{
    "equipo_type": "util",
    "usos": 1,
    "efecto": "cura 2d6 PV"
}
```

**Para `tecnica`:**
```json
{
    "tipo_tecnica": "ataque",
    "tipo_daño": "fisico",
    "efectos": ["aturdir 1 turno"],
    "alcance": "cuerpo_a_cuerpo",
    "bloqueable": true,
    "esquivable": true
}
```

**Para `haki`:**
```json
{
    "haki_type": "busoshoku",
    "haki_level": "basico",
    "efectos": ["daño_a_logias"]
}
```

**Para `akuma_no_mi`:**
```json
{
    "akuma_class": "paramecia",
    "akuma_name": "Bara Bara no Mi",
    "akuma_id": 1,
    "tier": 3,
    "despertar": false
}
```

**Para `npc_menor`:**
```json
{
    "npc_mascota_type": "npc",
    "pv": 50,
    "pe": 30,
    "stats": {"fue": 2, "res": 2, "agi": 3},
    "acciones": [
        "Garra: 1d8+fue [CORTANTE]",
        "Mordisco: 1d6+fue"
    ]
}
```

#### `notes` — Notas internas
- TEXT. Solo visible para staff. Notas de diseño, balance, origen de la card.

#### `image_url` — URL de imagen
- VARCHAR(500). URL a imagen representativa de la card.

#### `cost_berries` — Precio en tienda
- INT, default 0. Coste en Berries para comprar la card en la tienda.
- Las cards con `cost_berries > 0` y `in_shop = 1` aparecen en la tienda.

#### `in_shop` — Visible en tienda
- TINYINT(1), default 0. Si es 1, la card aparece en el catálogo de la tienda.
- Solo aplica a tipos `equipo`, `npc_menor`, `barco` (típicamente).

#### `shop_category` — Categoría de tienda
- VARCHAR(50). Agrupación visual en la tienda. Valores típicos:
  - `utiles` — Utilidades/consumibles
  - `armas` — Armas cuerpo a cuerpo
  - `armadura` — Armaduras y protecciones
  - `accesorios` — Accesorios
  - `mascotas` — NPCs menores tipo mascota
  - `naval` — Barcos y mejoras navales
  - `herramientas` — Herramientas de oficio

#### `peso` — Peso en inventario
- INT, default 1. Slots de carga que ocupa al equiparse.
- Solo relevante para cards de tipo `equipo`, `npc_menor`, `barco` (las que se equipan en slots).

#### `created_by` — Creador
- INT. FK lógica a `mybb_users.uid`. Quién creó la card en el catálogo.

#### `reposo` — Posts de recuperación
- INT, default 0. Cuántos posts debe esperar el personaje antes de poder reusar esta card.
- Se verifica en el frontend (cards_my_deck devuelve `last_played_turns`).

#### `duracion` — Duración del efecto
- INT, default 0. Cuántos posts dura el efecto de la card después de activarse (para efectos continuos).

#### `tier` — Tier mecánico
- TINYINT UNSIGNED, default 1, rango 1–5.
- Determina qué grado de disciplina u oficio se requiere para poder recibir la card.
- Relación tier ↔ rango de card:
  - Tier 1: Cards rango D–C
  - Tier 2: Cards rango C–B
  - Tier 3: Cards rango B–A
  - Tier 4: Cards rango A–S
  - Tier 5: Cards rango S–SS

#### `disciplina_slug` — Disciplina requerida
- VARCHAR(64) NULL. Si está definido, el personaje debe tener esta disciplina al menos en el grado igual al `tier` de la card.

Ejemplo: Una card con `disciplina_slug = "cuerpo_a_cuerpo"` y `tier = 2` requiere que el personaje tenga "Cuerpo a Cuerpo" grado II o superior.

#### `estilo_canonico_slug` — Estilo canónico requerido
- VARCHAR(64) NULL. Si está definido, la card pertenece a un estilo canónico específico y solo personajes que hayan aprendido ese estilo pueden solicitar/recibir la card.
- Es una FK lógica a `game_estilos_canonicos.slug`.

#### `oficio_slug` — Oficio requerido
- VARCHAR(64) NULL. Si está definido, el personaje debe tener este oficio para recibir la card.

#### `created_at` / `updated_at` — Timestamps
- Timestamps de creación y última modificación.

### 2.3 Filosofía del Modelo de Datos

**¿Por qué ENUM para `card_type` en lugar de VARCHAR?**
- **Integridad referencial lógica:** El sistema solo soporta 6 tipos de card. Si se permitiera VARCHAR libre, un error tipográfico (`"tecnica"` vs `"tecnico"`) crearía datos inconsistentes.
- **Rendimiento:** Los ENUMs en MySQL se almacenan como enteros (1 byte). La comparación es más rápida que VARCHAR.
- **Migraciones:** Añadir un nuevo tipo al ENUM es un ALTER TABLE simple (`ALTER TABLE game_cards MODIFY COLUMN card_type ENUM(...)`).

**¿Por qué effects_json y tags_json son TEXT (JSON) en lugar de tablas normalizadas?**
- **Estructura variable por tipo:** Los efectos de un arma son radicalmente distintos a los de una técnica de Haki o un NPC menor. Normalizar en tablas separadas requeriría 6+ tablas de efectos.
- **Flexibilidad de creación:** Cuando un jugador propone una card personalizada, el staff define los efectos en JSON. No hay restricciones de esquema que limiten la creatividad.
- **Lectura unificada:** Al cargar el deck de un personaje, se leen TODAS sus cards con una sola query. Los effects_json se decodifican en PHP.

**¿Por qué `rank` es ENUM y no INT?**
- Porque los rangos D→SS tienen significado narrativo directo. Un rango ENUM garantiza que no haya valores inválidos. La conversión a valor numérico se hace en PHP (StatScale).
- La consistencia con el sistema de stats (que también usa D→SS) evita confusiones.

### 2.4 Impacto RPG

| Campo | Lo que permite en el juego |
|-------|---------------------------|
| `card_type` | Determina cómo interactúa la card con el mundo (equipo, técnica, haki, etc.) |
| `rank` | El poder percibido de la card. Una card SS es temible. |
| `dice` | La tirada mecánica que resuelve la acción. |
| `cost_pe` | Cuánta energía gasta el personaje al usarla. |
| `reposo` | Evita spam de la misma card post-tras-post. |
| `tier` + `disciplina_slug` | Garantiza que solo personajes entrenados usen cards poderosas. |

---

## 3. Tablas Auxiliares

### 3.1 `game_character_cards` — Inventario de personajes

```sql
CREATE TABLE mybb_game_character_cards (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    character_id    INT NOT NULL,
    card_id         INT NOT NULL,
    current_rank    ENUM('D', 'C', 'B', 'A', 'S', 'SS') NOT NULL DEFAULT 'C',
    assigned_by     INT NOT NULL,
    cantidad        INT NOT NULL DEFAULT 1,
    assigned_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_char_card (character_id, card_id),
    KEY idx_char (character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Cards que posee cada personaje, con su rango efectivo y cantidad.

**Campos:**
- `id`: ID único de la fila.
- `character_id`: FK a `game_personajes.id`.
- `card_id`: FK a `game_cards.id`.
- `current_rank`: Rango efectivo de esta card para este personaje. Puede diferir del rango base de `game_cards`.
- `assigned_by`: FK a `mybb_users.uid` de quien asignó la card (staff).
- `cantidad`: Para consumibles (munición, útiles). Se decrementa al usarse. Default 1.
- `assigned_at`: Cuándo fue asignada.

**UNIQUE KEY `idx_char_card`:** Un personaje no puede tener la misma card más de una vez (excepto consumibles, donde `cantidad` se incrementa).

**Filosofía:**
- Separar `current_rank` del rango de catálogo permite que el staff otorgue versiones mejoradas de una card sin duplicar datos.
- La cantidad permite que las municiones y consumibles tengan stack en lugar de ocupar múltiples filas.

### 3.2 `game_character_inventory` — Equipamiento activo

```sql
CREATE TABLE mybb_game_character_inventory (
    character_id    INT NOT NULL,
    card_id         INT NOT NULL,
    slot_type       ENUM('carga', 'companero', 'barco') NOT NULL,
    equipped_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    peso            INT NOT NULL DEFAULT 0,
    PRIMARY KEY (character_id, card_id),
    INDEX idx_char_slot (character_id, slot_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Cards actualmente equipadas por el personaje. No todas las cards poseídas están equipadas; solo las equipadas pueden usarse en posts (excepto consumibles).

**Campos:**
- `character_id` + `card_id`: PK compuesta. Un personaje solo puede tener una card equipada una vez.
- `slot_type`: Tipo de slot que ocupa:
  - `carga`: Equipo, armas, herramientas (limitado por capacidad de carga).
  - `companero`: NPCs menores activos (bestias, subordinados).
  - `barco`: El barco activo del PJ (solo 1).
- `peso`: Copia del peso de la card al momento de equipar.
- `equipped_at`: Cuándo se equipó.

### 3.3 `game_post_cards` — Cards jugadas en posts

```sql
CREATE TABLE mybb_game_post_cards (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    post_id             INT NOT NULL,
    character_id        INT NOT NULL,
    card_id             INT NOT NULL,
    played_rank         ENUM('D', 'C', 'B', 'A', 'S', 'SS') NOT NULL DEFAULT 'C',
    roll_result         VARCHAR(255) DEFAULT NULL,
    hidden_action_index INT NOT NULL DEFAULT 0,
    roll_modifiers_json TEXT,
    played_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Registro de cada card jugada en cada post, con el resultado de la tirada.

**Campos:**
- `post_id`: FK a `mybb_posts.pid`.
- `character_id`: FK a `game_personajes.id`.
- `card_id`: FK a `game_cards.id`.
- `played_rank`: Rango con el que se jugó la card (copia de `current_rank` al momento de jugar).
- `roll_result`: Texto del resultado de la tirada evaluada. Ej: `"2d8 (3 + 6) + 5 = 14 [CORTANTE]"`.
- `hidden_action_index`: Si > 0, esta card se jugó como parte de una acción oculta (no visible para el rival hasta ser revelada).
- `roll_modifiers_json`: Modificadores aplicados a la tirada (dados extra, modificadores planos, formula_override).
- `played_at`: Momento de registro.

**Filosofía del snapshot de rango:** `played_rank` captura el rango de la card al momento del post, no el actual. Si el staff mejora la card después, los posts anteriores reflejan el rango original — es información histórica para revisión.

### 3.4 `game_card_requests` — Solicitudes de cards

```sql
CREATE TABLE mybb_game_card_requests (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    character_id        INT NOT NULL,
    card_id             INT NOT NULL DEFAULT 0,
    request_type        ENUM('delete', 'create', 'add_existing') NOT NULL,
    status              ENUM('pendiente', 'aprobada', 'rechazada', 'conforme') NOT NULL DEFAULT 'pendiente',
    current_rank        VARCHAR(10) NOT NULL,
    card_details_json   TEXT DEFAULT NULL,
    discussion_json     TEXT DEFAULT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_by         INT DEFAULT NULL,
    resolved_at         TIMESTAMP NULL DEFAULT NULL,
    staff_message       TEXT DEFAULT NULL,
    KEY idx_character (character_id),
    KEY idx_card (card_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Propósito:** Flujo de solicitud de cards (creación personalizada, adición desde catálogo, borrado).

**Campos:**
- `id`: ID único de la solicitud.
- `character_id`: FK a `game_personajes.id` — el personaje que solicita.
- `card_id`: ID de la card en catálogo (si `add_existing` o `delete`). Para `create`, es 0 hasta que se crea.
- `request_type`:
  - `create`: Solicitud de creación de card personalizada.
  - `add_existing`: Solicitud para añadir una card existente del catálogo al personaje.
  - `delete`: Solicitud de borrado de una card del personaje.
- `status`:
  - `pendiente`: Esperando revisión del staff.
  - `aprobada`: Aprobada (card creada/asignada/eliminada).
  - `rechazada`: Rechazada por el staff.
  - `conforme`: El jugador confirmó que la card creada cumple lo esperado (flujo de conformidad).
- `current_rank`: Rango solicitado (para `add_existing`) o rango propuesto (para `create`).
- `card_details_json`: Para `create`, contiene la especificación completa de la card propuesta.
- `discussion_json`: Historial de conversación entre jugador y staff (array de mensajes con sender, message, timestamp).
- `created_at`: Fecha de creación.
- `resolved_by`: FK a `mybb_users.uid` de quien resolvió.
- `resolved_at`: Fecha de resolución.
- `staff_message`: Mensaje final del staff.

### 3.5 `game_haki_progress` — Progreso de Haki

```sql
CREATE TABLE mybb_game_haki_progress (
    character_id    INT NOT NULL,
    haki_type       VARCHAR(20) NOT NULL,
    nivel           INT NOT NULL DEFAULT 1,
    usos_total      INT NOT NULL DEFAULT 0,
    unlocked_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (character_id, haki_type)
);
```

Aunque no es una tabla de cards directamente, se actualiza cuando un personaje juega cards de tipo `haki` en posts — cada uso incrementa `usos_total`, y el `nivel` determina qué cards de Haki puede recibir el personaje.

### 3.6 `game_post_characters` — Registro por post

```sql
CREATE TABLE mybb_game_post_characters (
    post_id               INT PRIMARY KEY,
    thread_id             INT DEFAULT NULL,
    user_id               INT NOT NULL,
    character_id          INT NOT NULL,
    pv_change             INT NOT NULL DEFAULT 0,
    pe_change             INT NOT NULL DEFAULT 0,
    pa_declared           TINYINT UNSIGNED NOT NULL DEFAULT 0,
    modifiers_json        TEXT DEFAULT NULL,
    hidden_actions_json   TEXT DEFAULT NULL,
    equipped_snapshot_json TEXT DEFAULT NULL,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_thread_id (thread_id)
);
```

El campo `equipped_snapshot_json` guarda qué cards estaban equipadas al momento del post, permitiendo verificar si una card usada estaba realmente equipada.

### 3.7 Filosofía del Diseño de Tablas

**¿Por qué 5 tablas separadas en lugar de 1 tabla de "items"?**

| Tabla | Propósito | Motivo de separación |
|-------|-----------|---------------------|
| `game_cards` | Catálogo maestro | Una card existe aunque nadie la posea |
| `game_character_cards` | Inventario | Un personaje tiene N cards, cada una con su rango |
| `game_character_inventory` | Equipamiento activo | Solo algunas cards están "equipadas" |
| `game_post_cards` | Historial de uso | Auditoría de qué se usó y cuándo |
| `game_card_requests` | Solicitudes | Flujo de aprobación separado del inventario |

**¿Por qué `equipped_snapshot_json` no es suficiente como único registro?**
El snapshot es una foto completa del equipamiento en el momento del post. Permite al staff ver "¿qué llevaba equipado?" sin depender del inventario actual. Pero los datos históricos de juego (`game_post_cards`) son necesarios para calcular cooldowns (reposo) y para el sistema de turnos.

---

## 4. Tipos de Card (`card_type`)

### 4.1 `tecnica` — Técnica de Combate

**Qué representa:** Una habilidad activa o pasiva que el personaje aprendió. Puede ser un ataque, una defensa, una maniobra, un buff, o una técnica de utilidad.

**Cómo se usa mecánicamente:**
- Se declara en el post (si activa) o está siempre activa (si pasiva).
- Generalmente consume PE (`cost_pe`) y produce una tirada (`dice`).
- Puede tener `reposo` (cooldown) y `duracion`.
- NO se equipa en slots de inventario — las técnicas están "siempre disponibles" si el personaje las posee.

**Ejemplos:**
- `"Gomu Gomu no Pistol"` — técnica de ataque cuerpo a cuerpo con tirada de dados
- `"Técnica de Batalla: Soru"` — técnica de movimiento que gasta PE
- `"Reflejos Felinos"` — pasiva que da bonus a tiradas de instinto
- `"Aliento de Todos los Cosas"` — técnica de percepción avanzada

**Subtipos en `effects_json`:**
```json
{
    "tipo_tecnica": "ataque",
    "tipo_daño": "fisico",
    "alcance": "medio",
    "efectos": ["sangrado", "aturdimiento"],
    "bloqueable": true,
    "esquivable": true,
    "requiere_postura": false
}
```

| Subtipo | Descripción |
|---------|-------------|
| `ataque` | Daña al rival. Puede ser físico, elemental, o especial. |
| `defensa` | Bloquea o reduce daño entrante. |
| `movimiento` | Desplazamiento, teletransporte, aumento de velocidad. |
| `buff` | Mejora temporal de stats o habilidades propias. |
| `debuff` | Penaliza stats o habilidades del rival. |
| `curacion` | Recupera PV/PE propios o de aliados. |
| `utilidad` | Percepción, detección, rastreo, otras habilidades no combatientes. |
| `pasiva` | Efecto permanente sin necesidad de activación. |

### 4.2 `equipo` — Objeto Equipable

**Qué representa:** Cualquier objeto físico que el personaje puede portar. Armas, armaduras, herramientas, consumibles, accesorios.

**Cómo se usa mecánicamente:**
- Debe estar **equipado** en el slot `carga` del inventario (excepto consumibles).
- Los consumibles se decrementan al usarse (`cantidad--`) y pueden desaparecer al llegar a 0.
- Las armas escalan con el stat de ejecución (`execution_stat`).
- Tiene `peso` que contribuye a la capacidad de carga.

**Subtipos en `effects_json`:**

**Armas:**
```json
{
    "equipo_type": "arma",
    "subtipo": "espada",
    "manos": 1,
    "alcance": "corto",
    "material": "acero",
    "filosofia": "katana",
    "bonus_stats": {"fue": 0, "agi": 0},
    "efectos_especiales": []
}
```

| Subtipo de arma | Ejemplo |
|-----------------|---------|
| `espada` | Katana, sable, estoque |
| `arma_asta` | Lanza, naginata, alabarda |
| `arma_contundente` | Mazo, martillo, porra |
| `arma_distancia` | Arco, cerbatana, shuriken |
| `arma_fuego` | Pistola, rifle, cañón |
| `arma_exotica` | Arma climática, dedos explosivos |
| `escudo` | Escudo de defensa |

**Armaduras:**
```json
{
    "equipo_type": "armadura",
    "parte": "torso",
    "defensa_base": 10,
    "material": "acero",
    "penalizador_agi": -1,
    "bonus_res": 1
}
```

| Parte | Descripción |
|-------|-------------|
| `torso` | Armadura de pecho/ torso |
| `cabeza` | Casco, sombrero protector |
| `brazos` | Guardabrazo, guanteletes |
| `piernas` | Grebas, rodilleras |
| `accesorio` | Joyas, amuletos, objetos especiales |

**Consumibles (útiles):**
```json
{
    "equipo_type": "util",
    "usos": 1,
    "efecto": "cura 2d6+esp PV",
    "target": "self"
}
```

| Subtipo útil | Descripción |
|--------------|-------------|
| `pocion` | Bebida curativa o buff temporal |
| `comida` | Alimento con efectos |
| `municion` | Balas, flechas, proyectiles |
| `herramienta` | Objeto de oficio (kit de médico, herramientas de carpintero) |
| `material` | Recurso para crafteo |

**Filosofía del equipamiento:** Las armas y armaduras NO se "gastan" al usarse. Permanecen equipadas hasta que el jugador las desequipa. Los consumibles sí se decrementan y eventualmente desaparecen.

### 4.3 `akuma_no_mi` — Poder de Fruta del Diablo

**Qué representa:** El poder completo de una Akuma no Mi (Paramecia, Zoan, Logia) que el personaje ha consumido.

**Cómo se usa mecánicamente:**
- Es una card **especial** — solo puede existir UNA por personaje (validado en la asignación).
- No se equipa en slots — es inherente al personaje.
- Las técnicas específicas de la fruta pueden ser cards de tipo `tecnica` con requisito de akuma.
- Al asignarse, se verifica que la fruta esté disponible (`is_occupied = 0`).

**Campos específicos en `effects_json`:**
```json
{
    "akuma_class": "paramecia",
    "akuma_name": "Bara Bara no Mi",
    "akuma_id": 1,
    "tier": 3,
    "despertar": false
}
```

**Clases:**
| Clase | Descripción |
|-------|-------------|
| `paramecia` | Poderes corporales o de entorno variados |
| `zoan` | Transformación animal; subtipo: ninguno, antiguo, mítico |
| `logia` | Control elemental + intangibilidad natural |

**Integración con el sistema de despertar:** El campo `despertar` (boolean) se actualiza mediante el flujo de `peticion_awakening.php`. Cuando se aprueba un despertar, el staff modifica la card para reflejar las nuevas capacidades.

### 4.4 `haki` — Técnica de Haki

**Qué representa:** Una técnica específica de Haki de Observación, Armamento o Conquistador.

**Cómo se usa mecánicamente:**
- Se asigna solo si el personaje tiene el nivel de Haki suficiente (validado en `cards_assign.php`).
- NO se equipa en slots — está siempre disponible si el personaje la posee.
- Cada uso de una card tipo `haki` incrementa `game_haki_progress.usos_total`.

**Campos específicos en `effects_json`:**
```json
{
    "haki_type": "busoshoku",
    "haki_level": "basico",
    "efectos": ["daño_a_logias", "endurecimiento"]
}
```

| `haki_type` | Significado |
|-------------|-------------|
| `kenbunshoku` | Haki de Observación (percepción, predicción) |
| `busoshoku` | Haki de Armamento (endurecimiento, daño a logias) |
| `haoshoku` | Haki de Conquistador (voluntad, noqueo) |

**Niveles de Haki y su mapeo a tier de card:**

| Nivel | `haki_level` | Tier de card | Ejemplo |
|-------|-------------|:------------:|---------|
| Latente | `obs_latente`, `arm_latente`, `rey_latente` | 1 | Percepción básica de presencia |
| Básico | `obs_basico`, `arm_basico`, `rey_basico` | 2 | Endurecimiento de brazos |
| Medio | `obs_medio`, `arm_medio`, `rey_medio` | 3 | Endurecimiento completo del cuerpo |
| Avanzado | `obs_avanzado`, `arm_interno`, `rey_avanzado` | 4 | Ryuo (emisión), visión del futuro parcial |
| Supremo | `obs_futuro`, `arm_supremo`, `rey_supremo` | 5 | Visión del futuro completa, Armamento Supremo, Conquistador supremo |

### 4.5 `npc_menor` — Aliado / Bestia / Subordinado

**Qué representa:** Un personaje no jugador controlado por el jugador como acompañante. Bestias, mascotas, subordinados, o NPCs aliados.

**Cómo se usa mecánicamente:**
- Se equipa en el slot `companero` del inventario.
- Cada personaje puede tener 1 compañero (o 2 si tiene el perk de linaje `g_vinculo_companero`).
- Al jugarse en un post, se selecciona una acción del NPC (o aleatoria si es bestia) y se evalúa su tirada.

**Campos específicos en `effects_json`:**
```json
{
    "npc_mascota_type": "npc",
    "pv": 50,
    "pe": 30,
    "stats": {"fue": 2, "res": 2, "agi": 3, "des": 1, "int": 1, "inst": 2, "esp": 1},
    "imagen": "https://...",
    "acciones": [
        {"name": "Garrazo", "dice": "1d8", "stat": "fue"},
        {"name": "Mordida", "dice": "1d6", "stat": "fue"},
        "Correr: 2d6+agi"
    ],
    "personalidad": "Agresivo"
}
```

| Subtipo | Comportamiento |
|---------|----------------|
| `npc` | Tiene acciones predefinidas, puede elegirse cuál usar. |
| `mascota` | Acciones seleccionables por el jugador, normalmente no combatientes. |

**Diferencias con NPCs Mayores:**
- Los NPCs **menores** son cards: el jugador los posee, los equipa, y los usa en posts.
- Los NPCs **mayores** son entidades gestionadas por staff, con ficha completa en `game_personajes` y `is_npc = 1`.

### 4.6 `barco` — Embarcación

**Qué representa:** El barco/vessel del personaje o tripulación.

**Cómo se usa mecánicamente:**
- Se equipa en el slot `barco` del inventario (solo 1 barco activo).
- No se "juega" en posts directamente, pero sus efectos se aplican en el sistema de navegación.
- El barco tiene atributos de navegación en `effects_json`.

**Campos específicos en `effects_json`:**
```json
{
    "barco_tipo": "bergantin",
    "velocidad": 3,
    "maniobrabilidad": 2,
    "resistencia": 5,
    "capacidad_carga": 50,
    "cañones": 8,
    "mejoras": ["vela_mejorada", "casco_refuerzo"]
}
```

| Atributo | Descripción |
|----------|-------------|
| `velocidad` | Modificador de velocidad en viajes |
| `maniobrabilidad` | Facilidad para esquivar obstáculos |
| `resistencia` | PV del barco en combate naval |
| `capacidad_carga` | Tonelaje máximo que puede transportar |
| `cañones` | Poder de fuego en combate naval |

---

## 5. Rangos de Card (D → SS)

### 5.1 Escala de Rangos

| Rango | Significado narrativo | Tier | Coste PE relativo | Poder de dados |
|-------|----------------------|:----:|:-----------------:|:--------------:|
| D | Básica/Novata | 1 | Muy bajo | 1d4–1d6 |
| C | Común/Entrenada | 1–2 | Bajo | 1d6–2d6 |
| B | Poderosa/Competente | 2–3 | Medio | 2d6–3d6 |
| A | Muy poderosa/Experta | 3–4 | Alto | 3d6–4d6 |
| S | Legendaria/Maestra | 4–5 | Muy alto | 4d6–6d6 |
| SS | Épica/Máxima | 5 | Extremo | 6d6+ |

### 5.2 Cómo el Rango Afecta Mecánicamente

**Coste de PE:** A mayor rango, mayor coste de PE esperado. No hay una fórmula exacta (es definido por el staff al crear la card), pero la convención es:

| Rango | Coste PE típico |
|-------|:---------------:|
| D | 0–5 |
| C | 5–15 |
| B | 15–30 |
| A | 30–50 |
| S | 50–80 |
| SS | 80+ |

**Dados:** El rango determina la magnitud de la tirada. Una card rango D rara vez tiene más de 1d6; una rango SS puede tener 6d8+fue*3.

**Tier mínimo:** El tier de la card debe corresponder a su rango:
- Rango D–C: Tier 1–2
- Rango C–B: Tier 2–3
- Rango B–A: Tier 3–4
- Rango A–S: Tier 4
- Rango S–SS: Tier 5

**Requisitos de disciplina:** Una card de rango A (tier 4) requiere una disciplina en grado IV, que es un nivel alto de maestría.

### 5.3 Rango vs Rango del Personaje

El rango de la card y el rango global del personaje son conceptos SEPARADOS:

- **Rango de card:** Qué tan poderosa es la card en sí misma (definido en `game_cards.rank` y sobrescribible en `game_character_cards.current_rank`).
- **Rango global del personaje:** Suma de stats del personaje (D→SS).

Un personaje con rango global D puede tener una card rango C, pero no debería poder tener una rango S (el tier de la card lo impide vía requisitos de disciplina).

### 5.4 Rank Up de Cards

Los personajes pueden solicitar al staff que una card existente suba de rango (ej: de C a B). Esto:
1. No modifica el catálogo (`game_cards.rank`).
2. Solo actualiza `game_character_cards.current_rank` para ese personaje.
3. Requiere justificación narrativa (el personaje entrenó la técnica/mejoró el arma).
4. El staff evalúa si el personaje cumple los nuevos requisitos de tier.

---

## 6. Modos de Activación

### 6.1 `activa` — Activación declarada

**Cuándo funciona:** El jugador debe declarar explícitamente en su post que usa la card. Sin declaración, la card no tiene efecto.

**Coste:** Siempre consume `cost_pe` y puede consumir `execution_cost`. Se considera parte del gasto de PE/PA del post.

**Ejemplo:**
```text
// En el post del jugador:
// "Luffy estira su brazo: Gomu Gomu no Pistol (card activa)"
// En el formulario RPG, selecciona la card y rellena PE gastado
```

**Táctico:** Las cards activas son predecibles (el rival sabe que las usaste). Son la forma más común de jugar.

### 6.2 `pasiva` — Siempre activa

**Cuándo funciona:** Siempre. No requiere declaración en el post, no consume PE, no tiene coste.

**Coste:** 0. No se declara ni se gasta nada.

**Ejemplos:**
- `"Cuerpo de Goma"` — pasiva de un usuario de Gomu Gomu: resistencia a balas y golpes contundentes.
- `"Visión Nocturna"` — pasiva racial: el personaje ve en la oscuridad.
- `"Afinidad con el Mar"` — pasiva de Gyojin: velocidad de nado mejorada.

**Validación:** Las pasivas no aparecen en el selector de cards del formulario de post. Se asumen siempre activas. El sistema no valida su uso porque no requiere input del jugador.

**Filosofía:** Las pasivas representan capacidades innatas o siempre activas. No deberían desbalancear el juego porque su efecto es constante y predecible.

### 6.3 `reactiva` — Respuesta a acción rival

**Cuándo funciona:** Se activa AUTOMÁTICAMENTE en respuesta a una condición específica definida en la card. El jugador no decide cuándo usarla — ocurre cuando se cumple la condición.

**Coste:** Puede tener coste (se descuenta automáticamente) o ser gratuita. La declaración la hace el sistema.

**Mecanismo de activación:**
```json
{
    "condicion": "recibir_daño_fisico",
    "efecto": "reducir_daño_en_50%",
    "coste_auto": true
}
```

| Tipo de condición | Se activa cuando... |
|-------------------|---------------------|
| `recibir_daño` | El personaje recibe cualquier daño |
| `recibir_daño_fisico` | Daño físico entrante |
| `recibir_daño_elemental` | Daño elemental/energético |
| `ser_atacado` | El personaje es objetivo de un ataque |
| `pv_bajo` | PV del personaje caen por debajo de X% |
| `estado_alterado` | El personaje sufre un estado alterado |
| `aliado_herido` | Un aliado recibe daño |

**Ejemplo:**
```text
Card: "Armadura de Haki"
Tipo: reactiva
Condición: "recibir_daño_fisico"
Efecto: "El personaje endurece su cuerpo con Haki, reduciendo el daño entrante en 1d6+fue"
Coste: 10 PE (se descuenta automáticamente)
```

**Diferencia con activa:** La reactiva no requiere decisión del jugador. Es un "seguro" mecánico que se dispara solo. Esto es importante para:
- Trampas: "Si alguien intenta envenenarte, tu cuerpo reacciona".
- Instintos: "Si te atacan por la espalda, esquivas automáticamente".
- Defensa pasiva: "Tu Haki de Armamento se activa al recibir daño".

### 6.4 Filosofía de las Activaciones

**¿Por qué tres modos y no solo una?**
- **Tensión narrativa:** Las activas requieren decisión del jugador (¿uso mi técnica ahora o la guardo?). Las reactivas crean sorpresa (el rival no sabe que tienes una defensa automática). Las pasivas son la identidad constante del personaje.
- **Coste de decisión:** Las activas tienen coste de oportunidad (gasto PE, podría necesitarlos después). Las reactivas eliminan ese coste de decisión pero requieren planificación (elegir qué reactivas tener equipadas).
- **Verificación mecánica:** Las pasivas no necesitan verificación. Las activas se verifican en el post. Las reactivas requieren que el staff valide que la condición se cumplió.

**¿Por qué no hay activación "por turno" o "por escena"?**
Porque el foro es por posts, no por turnos. Cada post es una "acción". El jugador decide cuántas cards activas usa en su post, limitado por su PA declarado.

---

## 7. Campos Mecánicos Clave

### 7.1 `cost_pe` — Coste en PE

**Qué es:** El coste en Puntos de Energía que debe pagar el personaje para activar la card.

**Tipo en DB:** `VARCHAR(50) DEFAULT '—'` — no es numérico porque puede ser un rango o fórmula.

**Uso en cálculos:**
- Se resta del PE actual del personaje en el hilo (`game_thread_pj_state.current_pe`).
- El jugador declara el PE gastado en el formulario de post (`rpg_thread_pe`).
- Si `cost_pe = "—"`, no consume PE.
- Si `cost_pe = "15"`, consume exactamente 15 PE.
- Si `cost_pe = "5-10"`, el staff determina el coste exacto según contexto.

**En PHP (`game_postcharacter.php`):**
```php
// El PE se descuenta vía el formulario del post, no automáticamente.
// El jugador escribe su PE actual y el sistema calcula pv_change/pe_change.
$current_pe = (isset($_POST['rpg_thread_pe']) && $_POST['rpg_thread_pe'] !== '')
    ? (int)$_POST['rpg_thread_pe']
    : $prev_pe;
$pe_change = $current_pe - $prev_pe;
```

**Filosofía:** El coste PE es **declarativo**, no automático. El sistema no valida "tienes suficiente PE para esta card". El jugador declara su PE actual post-a-post, y el staff revisa que sea coherente. Esto permite flexibilidad narrativa (un personaje puede gastar más PE del que tiene si el contexto lo justifica, a costa de quedar exhausto).

### 7.2 `execution_cost` — Coste de ejecución

**Qué es:** Coste adicional en PP/PA para ejecutar la card. No todos los sistemas lo usan.

**Tipo en DB:** `INT NOT NULL DEFAULT 0`.

**Uso en cálculos:**
- Se suma al PA declarado en el post (`pa_declared` en `game_post_characters`).
- Si es > 0, la card requiere un coste extra de "acción" o "recursos".

**Diferencias con `cost_pe`:**
| | cost_pe | execution_cost |
|---|---|---|
| Moneda | PE (Energía) | PA (Puntos de Aventura) o PP (Progresión) |
| Frecuencia | Por uso | Por uso |
| Recuperación | Posts de descanso | Por hilo/escena |
| Driver | `VARCHAR` (puede ser fórmula) | `INT` (siempre numérico) |

### 7.3 `execution_stat` — Stat de ejecución

**Qué es:** El stat que escala el poder de la card. Cuando una card tiene `execution_stat`, su tirada de dados se modifica añadiendo el valor de ese stat.

**Tipo en DB:** `VARCHAR(10) DEFAULT ''`.

**Uso en cálculos:**

Para cards de tipo `equipo` con subtipo `arma`, el sistema PHP modifica la fórmula de dados automáticamente:

```php
// En game_postcharacter.php, línea 436-453:
if ($card['card_type'] === 'equipo' && !empty($card['dice']) && trim($card['dice']) !== '—') {
    $card_ef = json_decode($card['effects_json'] ?? '{}', true);
    if (($card_ef['equipo_type'] ?? '') === 'arma' && !empty($card['execution_stat'])) {
        $scale_stat = strtolower(trim($card['execution_stat']));
        if ($scale_stat !== '') {
            $c_dice = trim($card['dice']);
            // Si la fórmula no incluye ya el stat, se añade
            if (stripos($c_dice, $scale_stat) === false) {
                $c_dice = $c_dice . '+' . $scale_stat;
            }
            $card['dice'] = $c_dice;
        }
    }
}
```

**Ejemplo:**
- Card: `"Katana de Acero"`, tipo `equipo`, `execution_stat = "fue"`, `dice = "2d8"`
- Cuando el personaje juega esta card en un post, la fórmula se convierte a `"2d8+fue"`
- Si el personaje tiene FUE 3 (valor 15): `2d8 + 15`

**Para cards tipo `tecnica`:** El `execution_stat` puede estar incluido directamente en la fórmula (`dice = "3d6+des*2"`) o indicarse por separado.

### 7.4 `dice` — Fórmula de dados

**Qué es:** La fórmula que se evalúa cuando la card se juega en un post.

**Tipo en DB:** `VARCHAR(150) DEFAULT ''`.

**Evaluación en PHP:**

El sistema usa `game_evaluate_dice_roll()` (definida en `game_postcharacter.php`):

```php
function game_evaluate_dice_roll(string $formula, array $stats, array $modifiers = []): string
{
    // 1. Extraer tag al final [FUEGO], [CORTANTE], etc.
    // 2. Tokenizar por + y -
    // 3. Para cada token:
    //    a. Si es notación de dados (XdY): tirar dados, sumar
    //    b. Si es número: usar como constante
    //    c. Si es stat (fue, res, etc.): obtener valor del array $stats
    //    d. Si es stat*N o N*stat o stat/N: aplicar multiplicador/divisor
    // 4. Devolver string detallado: "2d8 (3+6) + 15 = 24 [FUEGO]"
}
```

**Sintaxis completa:**

| Token | Ejemplo | Resultado |
|-------|---------|-----------|
| `XdY` | `2d6` | Tira 2 dados de 6 caras |
| `stat` | `fue` | Valor del stat (ej: 15 para FUE 3) |
| `N*stat` | `2*fue` | 2 × valor de FUE |
| `stat*N` | `int*3` | 3 × valor de INT |
| `stat/N` | `inst/2` | Valor de INST / 2 |
| `[ARMA]` | `[ARMA]+2d6` | Reemplazado por la(s) fórmula(s) de arma(s) equipada(s) |
| `[MUNICION]` | `[MUNICION]` | Reemplazado por la fórmula de munición usada |
| `+N` | `+5` | Modificador plano |
| `-N` | `-3` | Penalizador plano |
| `[TAG]` | `[FUEGO]` | Etiqueta al final, no evaluada, solo informativa |

**Ejemplos reales de fórmulas:**

```
"2d8+fue [CORTANTE]"           → 2d8 + valor de FUE + tag CORTANTE
"3d6+des*2 [PERFORANTE]"       → 3d6 + (valor de DES × 2) + tag PERFORANTE
"[ARMA]+1d6 [FUEGO]"           → dado del arma + 1d6 + tag FUEGO
"4d6+esp/2 [IMPACTO]"          → 4d6 + (ESP / 2) + tag IMPACTO
"1d20+fue [GOLPE_CRITICO]"     → 1d20 + FUE + tag
"2d10+inst+agi [EVASION]"      → 2d10 + INST + AGI + tag
```

### 7.5 `reposo` — Posts de recuperación

**Qué es:** Cuántos posts debe esperar el personaje antes de poder usar la misma card de nuevo.

**Tipo en DB:** `INT NOT NULL DEFAULT 0`.

**Cómo se calcula en frontend:**

En `cards_my_deck.php`, el sistema calcula los turnos del personaje en el hilo y cuándo usó cada card por última vez:

```php
// Por cada card jugada por este personaje en este hilo:
$last_played_turns[$card_id] = $turn; // turn = índice de post (1, 2, 3...)

// En el frontend (JS), se compara:
// if (current_turn - last_played_turns[card_id] < reposo) → card en cooldown
```

**Ejemplo:**
- `reposo = 2` significa que después de usar la card, el personaje debe hacer 2 posts ANTES de poder usarla de nuevo.
- Si se usó en el post 3, puede volver a usarse en el post 6 (post 4 y 5 son reposo).

**Valores típicos:**

| reposo | Significado |
|:------:|-------------|
| 0 | Sin cooldown. Puede usarse cada post (si hay PE). |
| 1 | Esperar 1 post entre usos. |
| 2 | Esperar 2 posts. Típico de técnicas poderosas. |
| 3+ | Técnicas ultimate o de gran impacto. |
| 5+ | Prácticamente una técnica de "una vez por combate". |

**Filosofía:** El reposo evita el spam de la misma técnica. Un jugador no puede resolver todo con "Gomu Gomu no Pistol" post-tras-post. Debe variar su repertorio.

### 7.6 `duracion` — Duración del efecto

**Qué es:** Cuántos posts dura el efecto de la card después de activarse.

**Tipo en DB:** `INT NOT NULL DEFAULT 0`.

**Interpretación:**
- `0`: Efecto instantáneo. No persiste después del post.
- `1`: Dura hasta el siguiente post del personaje.
- `2+`: Dura múltiples posts. Se aplica automáticamente sin reactivar.

**Uso táctico:**
- Cards de buff con `duracion = 3`: el personaje gana un bonus por 3 posts.
- Cards de debuff con `duracion = 2`: el rival sufre penalización por 2 posts.
- Cards de daño continuo (veneno, fuego) con `duracion = 3` y dados cada post.

**Ejemplo de card con duración:**
```json
{
    "name": "Frenesí de Batalla",
    "card_type": "tecnica",
    "activation": "activa",
    "dice": "2d6+fue",
    "cost_pe": "20",
    "reposo": 3,
    "duracion": 3,
    "effects_json": {
        "tipo_tecnica": "buff",
        "efectos": ["bonus_fue+2 por duracion"],
        "duracion_efecto": 3
    }
}
```

### 7.7 `tier` — Tier mecánico

**Qué es:** El nivel de poder/requisito de la card. Relaciona la card con el sistema de disciplinas y oficios.

**Tipo en DB:** `TINYINT UNSIGNED NOT NULL DEFAULT 1`. Rango 1–5.

**Propósito:**

El tier actúa como **gatekeeper mecánico**. Una card de tier 4 no puede ser asignada a un personaje que solo tiene disciplina grado II.

La validación ocurre en `game_card_assignment_competencia_error()`:

```php
function game_card_assignment_competencia_error(int $characterId, array $card): ?string
{
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
    // Similar para oficio_slug
    return null;
}
```

**Correspondencia tier ↔ requisitos:**

| Tier | Grado de disciplina requerido | Rango de card típico |
|:----:|:----------------------------:|:--------------------:|
| 1 | Grado I | D–C |
| 2 | Grado II | C–B |
| 3 | Grado III | B–A |
| 4 | Grado IV | A–S |
| 5 | Grado V | S–SS |

### 7.8 `peso` — Peso en inventario

**Qué es:** Cuánto espacio ocupa la card en el inventario cuando está equipada.

**Tipo en DB:** `INT NOT NULL DEFAULT 1`.

**Cálculo de capacidad de carga:**

La capacidad máxima del personaje se calcula en base a su stat FUE:

```php
// En inventory_get.php e inventory_toggle.php:
$cc_max = 5 + (int)floor($fue / 4) + ($has_carga_perk ? 3 : 0);
```

Donde `$fue` es el VALOR del stat (no el rango). Ejemplos:
- FUE 1 (valor 4): `5 + 1 + 0 = 6` slots de carga
- FUE 3 (valor 15): `5 + 3 + 0 = 8` slots
- FUE 5 (valor 40): `5 + 10 + 0 = 15` slots
- FUE 6 (valor 60): `5 + 15 + 0 = 20` slots

**Valores típicos de peso:**

| Tipo de card | Peso típico |
|-------------|:-----------:|
| Arma pequeña (cuchillo, pistola) | 1 |
| Arma mediana (espada, lanza) | 2 |
| Arma grande (gran espada, rifle) | 3 |
| Armadura ligera | 2 |
| Armadura pesada | 4 |
| Consumible | 0 (no ocupa carga si es util) |
| Herramienta | 1 |
| NPC menor | 0 (ocupa slot compañero, no carga) |
| Barco | 0 (ocupa slot barco, no carga) |

---

## 8. Proceso de Adquisición de Cards

### 8.1 Compra en Tienda (Berries)

**Vía:** `tienda.php` → `tienda_comprar.php`

**Qué se puede comprar:** Cards de tipo `equipo`, `npc_menor`, `barco` que tengan `in_shop = 1` y `cost_berries > 0`.

**Flujo completo:**

```
1. Jugador entra a tienda.php
2. GET shop_catalog_list.php → lista de cards disponibles (catalogadas por shop_category)
3. Jugador ve cards, selecciona una, ve detalle (tienda_card_detail.php)
4. POST tienda_comprar.php con {card_id, character_id}
5. Validaciones:
   a. Personaje existe y pertenece al usuario
   b. Card existe, in_shop=1, card_type en (equipo, npc_menor, barco)
   c. Personaje tiene suficientes berries (game_personajes.berries - cost_berries >= 0)
   d. Card no está ya en posesión del personaje
6. Si todo ok:
   a. INSERT en game_character_cards (character_id, card_id, current_rank=card.rank, assigned_by=uid)
   b. UPDATE game_personajes SET berries = berries - cost_berries
7. Respuesta JSON: {ok: true, data: {card_id, new_balance}}

```

**Validaciones en `tienda_comprar.php`:**
```php
// Fragmento de validación:
if (!in_array($card['card_type'], $valid_types, true)) {
    GameAjax::fail(400, 'Esta carta no está disponible para compra directa.');
}
if ($card['in_shop'] != 1) {
    GameAjax::fail(400, 'Esta carta no está en la tienda.');
}
if ((int)$pj['berries'] < $card['cost_berries']) {
    GameAjax::fail(400, 'Berries insuficientes.');
}
```

**Filosofía:** La tienda es para cards genéricas y de equipo básico. Cualquier personaje con berries puede comprar equipo estándar (armas comunes, armaduras básicas, herramientas). No requiere aprobación del staff.

**Limitaciones:**
- Solo cards de tipo `equipo`, `npc_menor`, `barco` (no técnicas, no Haki, no Akuma).
- El precio lo define el staff al crear la card (`cost_berries`).
- Si una card es consumible, se asigna con `cantidad` inicial (no mostrado aquí, pero implementado).

### 8.2 Solicitud al Staff (`game_card_requests`)

**Vía:** `cards_request_custom.php`

**Tres modalidades de solicitud:**

#### 8.2.1 Solicitud de Creación Personalizada (`type=create`)

**Cuándo usarlo:** El jugador quiere una técnica, Haki, o equipo personalizado que no existe en el catálogo.

**Flujo:**

```
1. Jugador va a pestaña "Gestión" → "Proponer Nueva Carta"
2. Llena formulario: tipo, nombre, descripción, efectos, notas
3. POST cards_request_custom.php {type: 'create', character_id, card_name, description, card_type, effects}
4. Validaciones:
   a. Personaje pertenece al usuario
   b. Nombre y descripción no vacíos
   c. card_type válido
5. Se crea solicitud en game_card_requests:
   - request_type = 'create'
   - card_id = 0 (aún no existe en catálogo)
   - card_details_json = especificación completa
   - discussion_json = mensaje inicial del jugador
   - status = 'pendiente'
6. Staff ve solicitud en zona_staff.php / cards_pending_requests.php
7. Staff puede:
   a. MODERATE: Modificar especificaciones y enviar de vuelta al jugador
   b. REPLY: Enviar mensaje al jugador pidiendo ajustes
   c. APPROVE: Crear la card en game_cards y asignarla al personaje
   d. REJECT: Rechazar la solicitud
```

**Detalle del flujo de aprobación (`cards_resolve_request.php`):**

```php
// Al aprobar una solicitud de creación:
$details = json_decode($request['card_details_json'], true);
$insert_card = [
    'name' => $details['name'],
    'card_type' => $details['card_type'],
    'rank' => $details['rank'], // Default 'C' si no se especificó
    'activation' => $details['activation'],
    'tags_json' => json_encode($details['tags'] ?? []),
    'description' => $details['description'],
    'cost_pe' => $details['cost_pe'] ?? '—',
    'execution_stat' => $details['execution_stat'] ?? '',
    'dice' => $details['dice'] ?? '',
    'effects_json' => json_encode($details['effects'] ?? []),
    'notes' => $details['notes'] ?? '',
    'image_url' => $details['image_url'] ?? '',
    'reposo' => $details['reposo'] ?? 0,
    'duracion' => $details['duracion'] ?? 0,
    'created_by' => $staff_uid
];
$db->insert_query('game_cards', $insert_card);
$new_card_id = $db->insert_id();

// Asignar al personaje:
$db->write_query("INSERT INTO game_character_cards (character_id, card_id, current_rank, assigned_by)
    VALUES ({$character_id}, {$new_card_id}, '{$rank}', {$staff_uid})");

// Actualizar solicitud:
$db->write_query("UPDATE game_card_requests SET status='aprobada', card_id={$new_card_id}
    WHERE id={$request_id}");
```

#### 8.2.2 Solicitud de Adición de Catálogo (`type=add_existing`)

**Cuándo usarlo:** El jugador quiere una card que YA existe en el catálogo (ej: un arma de la tienda que no puede pagar, o una técnica de un estilo canónico).

**Flujo:**

```
1. Jugador selecciona card del catálogo (cards_list.php)
2. POST cards_request_custom.php {type: 'add_existing', character_id, card_id, note}
3. Validaciones:
   a. Card existe
   b. Personaje no tiene ya la card
   c. No hay solicitud pendiente para esta misma combinación
4. Se crea solicitud en game_card_requests:
   - request_type = 'add_existing'
   - card_id = ID de la card del catálogo
   - current_rank = rank de la card original
5. Staff revisa y puede aprobar/rechazar
6. Si aprueba: INSERT en game_character_cards con el rango especificado
```

#### 8.2.3 Solicitud de Borrado (`type=delete`)

**Cuándo usarlo:** El jugador quiere eliminar una card de su inventario.

**Flujo:**

```
1. Jugador selecciona card de su deck
2. POST cards_request_custom.php {type: 'delete', character_id, card_id, note}
3. Se crea solicitud con request_type = 'delete'
4. Staff revisa y si aprueba: DELETE de game_character_cards
```

### 8.3 Asignación Directa por Staff

**Vía:** `cards_assign.php`

**Cuándo se usa:**
- Como recompensa directa de staff por eventos, misiones, o logros.
- Para asignar frutas del diablo (`akuma_no_mi`) tras el flujo de aprobación.
- Para otorgar cartas de Haki tras progresión.
- En general, cualquier card que no pase por tienda o solicitud.

**Flujo:**

```
1. Staff (nivel 3+) selecciona personaje y card desde panel
2. POST cards_assign.php {character_id, card_id, cantidad}
3. Validaciones:
   a. Staff nivel suficiente (≥ 3)
   b. Card existe y personaje existe
   c. Si es akuma_no_mi: game_akuma_assignment_error() verifica fruta disponible
   d. Si tiene disciplina_slug: game_card_assignment_competencia_error() verifica requisitos
   e. Si es haki: valida nivel de Haki suficiente en game_haki_progress
4. INSERT/UPDATE ON DUPLICATE KEY en game_character_cards
5. Se asigna con el rango que tenga la card en catálogo (o el especificado por staff)
```

**Validaciones de competencia:**
```php
// Verifica que el personaje cumple los requisitos de disciplina/oficio
$compErr = game_card_assignment_competencia_error($character_id, $card);
if ($compErr !== null) {
    GameAjax::fail(403, $compErr);
}

// Para Haki, verifica nivel suficiente:
if ($card['card_type'] === 'haki') {
    $hakiLevel = $efCheck['haki_level']; // ej: 'basico'
    $minLevel = $levelMap[$hakiLevel] ?? 5;
    // Consulta game_haki_progress para ver nivel actual del personaje
    if ($playerHakiLevel < $minLevel) {
        GameAjax::fail(403, 'Nivel de Haki insuficiente.');
    }
}
```

### 8.4 Drops de Misiones/Eventos

**Cuándo se usa:** Al completar una misión o evento, el staff puede asignar cards como recompensa.

**Flujo:**

```
1. Staff completa/cierra misión
2. Staff usa cards_assign.php para asignar la card de recompensa al personaje
3. Opcional: Se registra en game_notifications que el personaje recibió una card
```

No hay flujo automático — el staff asigna manualmente usando el endpoint de asignación.

### 8.5 Comparativa de Métodos de Adquisición

| Método | Velocidad | Control de staff | Tipos disponibles | Coste |
|--------|:---------:|:----------------:|:-----------------:|:-----:|
| Tienda | Inmediato | Bajo (precios predefinidos) | equipo, npc_menor, barco | Berries |
| Solicitud (create) | Días (staff revisa) | Alto (diseño + balance) | Cualquiera | 0 |
| Solicitud (add_existing) | Días (staff revisa) | Medio (solo aprobar) | Cualquiera | 0 |
| Asignación directa | Inmediato (staff) | Máximo | Cualquiera | 0 |
| Drop misión/evento | Variable | Máximo | Cualquiera | 0 |

---

## 9. Sistema de Equipamiento e Inventario

### 9.1 Slots de Inventario

| Slot | Propósito | Límite |
|------|-----------|:------:|
| `carga` | Equipo, armas, herramientas, consumibles portados | `5 + floor(FUE_valor / 4)` + perks |
| `companero` | NPCs menores activos (bestias, subordinados) | 1 (2 con perk `g_vinculo_companero`) |
| `barco` | El barco activo del PJ | 1 |

### 9.2 Endpoint de Inventario

**`inventory_get.php`** — GET - Obtiene el estado completo del inventario de un personaje.

**Respuesta JSON:**
```json
{
    "ok": true,
    "data": {
        "character": {
            "id": 1,
            "name": "Monkey D. Luffy",
            "fue": 60,
            "cc_max": 20,
            "cc_used": 8,
            "companion_max": 1,
            "companion_used": 1,
            "barco_max": 1,
            "barco_used": 1
        },
        "equipped": [
            {
                "card_id": 42,
                "slot_type": "carga",
                "peso": 2,
                "name": "Katana de Acero",
                "card_type": "equipo",
                "rank": "C",
                "description": "...",
                "image_url": "..."
            }
        ],
        "owned": [
            {
                "id": 42,
                "name": "Katana de Acero",
                "card_type": "equipo",
                "rank": "C",
                "description": "...",
                "peso": 2,
                "is_equipped": true
            }
        ]
    }
}
```

**Cálculo de capacidad:**
```php
// En inventory_get.php:
$cc_max = 5 + (int)floor($fue / 4) + ($has_carga_perk ? 3 : 0);
```

Donde `$fue` es el VALOR del stat FUE del personaje (no el rango). Con el perk `g_capacidad_carga` o `g_carga_extra`, se añaden 3 slots extra.

### 9.3 Equipar/Desequipar

**`inventory_toggle.php`** — POST - Equipa o desequipa una card.

**Flujo de equipamiento:**

```
1. Validar ownership del personaje (owner o staff)
2. Verificar que el personaje posee la card (game_character_cards)
3. Obtener card_type y peso
4. Determinar slot_type según card_type:
   - equipo → 'carga'
   - npc_menor → 'companero'
   - barco → 'barco'
5. Si YA está equipada → DESEQUIPAR (DELETE FROM game_character_inventory)
6. Si NO está equipada → EQUIPAR:
   a. Calcular límites actuales
   b. Si slot_type = 'carga': cc_used + peso <= cc_max
   c. Si slot_type = 'companero': companions_count < companion_max
   d. Si slot_type = 'barco': barcos_count < 1
   e. INSERT en game_character_inventory
7. Responder {equipped: true/false, card_id}
```

**Validación de límites:**
```php
// Fragmento de inventory_toggle.php:
if ($slot_type === 'carga') {
    if ($cc_used + $peso > $cc_max) {
        GameAjax::fail(400, "Capacidad de Carga insuficiente. Límite: {$cc_used}/{$cc_max}.");
    }
} elseif ($slot_type === 'companero') {
    if ($companions_count >= $companion_max) {
        GameAjax::fail(400, "Límite de compañeros excedido.");
    }
} elseif ($slot_type === 'barco') {
    if ($barcos_count >= 1) {
        GameAjax::fail(400, "Ya tienes un barco activo.");
    }
}
```

### 9.4 ¿Qué cards requieren equiparse?

No todas las cards requieren estar equipadas para usarse. La función `game_card_requires_equipped_slot()` define cuáles:

```php
function game_card_requires_equipped_slot(string $cardType, bool $isConsumible = false): bool
{
    if ($isConsumible) {
        return false; // Consumibles se pueden usar sin equipar
    }
    return in_array($cardType, ['equipo', 'npc_menor', 'barco'], true);
}
```

**Regla:**
- Cards `tecnica`, `haki`, `akuma_no_mi`: NO requieren equiparse. Están siempre disponibles si el personaje las posee.
- Cards `equipo` (no consumibles), `npc_menor`, `barco`: SÍ requieren equiparse.
- Cards `equipo` con subtipo `util` (consumibles): NO requieren equiparse. Se pueden usar directamente desde el inventario.

### 9.5 Snapshot en Posts

Cada vez que un personaje postea, el sistema guarda un **snapshot** de su equipamiento actual:

```php
function game_postcharacter_save_equipped_snapshot(int $pid, int $cid): array
{
    $ids = game_get_equipped_card_ids($cid);
    $json = json_encode(array_values($ids), JSON_UNESCAPED_UNICODE);
    // UPDATE game_post_characters SET equipped_snapshot_json = $json
    return $ids;
}
```

Este snapshot es CRUCIAL para la auditoría: si un jugador usó un arma en un post, el staff puede verificar que esa arma estaba equipada en ese momento. Sin snapshot, el jugador podría equipar el arma después del post y reclamar que la usó.

---

## 10. Cards en Posts — Procesamiento

### 10.1 Flujo Completo

Cuando un jugador envía un post con cards, el flujo es:

```
1. MyBB hook: datahandler_post_insert_post_end
   → game_postcharacter_save_post($dh)

2. game_postcharacter_save_post():
   a. INSERT en game_post_characters (post_id, user_id, character_id)
   b. game_postcharacter_save_equipped_snapshot() — guarda equipamiento actual
   c. game_postcharacter_process_cards($pid, $cid, $_POST)
   d. game_postcharacter_save_thread_state() — actualiza PV/PE del hilo

3. ProcessPostCards::execute():
   a. Obtener IDs de cards equipadas desde el snapshot
   b. Obtener contexto de stats (stats_json + modificadores de turno)
   c. Procesar rpg_played_cards (cards visibles)
   d. Procesar rpg_hidden_actions (acciones ocultas)
   e. Para cada card:
      - game_postcharacter_process_card_entry()

4. game_postcharacter_process_card_entry():
   a. Verificar que el personaje posee la card
   b. Verificar que la card está equipada (si requiere slot)
   c. Si es Haki: incrementar game_haki_progress.usos_total
   d. Si es arma (tipo equipo): añadir execution_stat a la fórmula
   e. Si es NPC menor: seleccionar acción (aleatoria o elegida)
   f. Evaluar tirada de dados (game_evaluate_dice_roll o formato NPC)
   g. INSERT en game_post_cards (post_id, card_id, played_rank, roll_result)
   h. Si es consumible: decrementar cantidad
```

### 10.2 Formulario de Post

El formulario de post (newreply.php, newthread.php) incluye campos ocultos para el sistema RPG:

```html
<!-- Campos enviados con el post -->
<input type="hidden" name="rpg_played_cards" value='[{"card_id":42,"selected_action":"ataque","weapons":[55],"ammo":[60]}]' />
<input type="hidden" name="rpg_hidden_actions" value='[{"index":1,"description":"Preparar emboscada","cards":[{"card_id":43}]}]' />
<input type="hidden" name="rpg_modifiers" value='{"fue":2,"agi":-1}' />
<input type="hidden" name="rpg_thread_pv" value="180" />
<input type="hidden" name="rpg_thread_pe" value="120" />
<input type="hidden" name="pa_declared" value="2" />
```

**Estructura de `rpg_played_cards`:**
```json
[
    {
        "card_id": 42,
        "selected_action": "ataque_poderoso",
        "weapons": [55],
        "ammo": [60],
        "roll_modifiers": {
            "dice_mod": ["1d4"],
            "flat_mod": 2,
            "formula_override": "3d8+fue"
        }
    }
]
```

### 10.3 Procesamiento de Armas Compuestas

Las cards pueden usar placeholders `[ARMA]` y `[MUNICION]` para construir fórmulas compuestas:

**Ejemplo:** Una técnica que dispara un proyectil elemental
- Card técnica: `"Disparo Elemental"` con `dice = "[ARMA]+[MUNICION]+1d6 [FUEGO]"`
- Arma equipada: `"Pistola de Chispa"` con `dice = "1d8+des"`
- Munición: `"Bala Explosiva"` con `dice = "2d6 [FUEGO]"`

Resultado: `1d8+des+2d6+1d6 [FUEGO]`

**Implementación (`game_postcharacter.php`):**
```php
// Reemplazar [ARMA] con las fórmulas de armas seleccionadas
while (strpos($formula, '[ARMA]') !== false) {
    $replacement = isset($weapon_formulas[$w_idx]) ? $weapon_formulas[$w_idx] : '0';
    $formula = substr_replace($formula, $replacement, $pos, strlen('[ARMA]'));
    $w_idx++;
}

// Reemplazar [MUNICION] con las fórmulas de munición seleccionadas
while (strpos($formula, '[MUNICION]') !== false) {
    $replacement = isset($ammo_formulas[$a_idx]) ? $ammo_formulas[$a_idx] : '0';
    $formula = substr_replace($formula, $replacement, $pos, strlen('[MUNICION]'));
    $a_idx++;
}
```

### 10.4 Acciones Ocultas

Las acciones ocultas permiten a un jugador preparar cartas que no son visibles para el rival hasta que se revelan.

**Flujo:**
```
1. Jugador incluye rpg_hidden_actions en el formulario del post
2. ProcessPostCards::execute():
   a. Por cada acción oculta:
      - Procesa las cards asociadas con hidden_action_index > 0
   b. Guarda en game_post_characters.hidden_actions_json:
      [{"index":1, "description":"Preparar emboscada", "is_revealed":0}]
3. Las cards jugadas en acciones ocultas se guardan en game_post_cards
   con hidden_action_index > 0
4. En la consulta cards_for_post.php:
   - Si action_index > 0 y NO revelada: las cards no se devuelven en la respuesta
   - Solo el owner del post puede ver sus propias acciones ocultas
5. El owner puede revelar la acción (no implementado automáticamente,
   se hace mediante edición manual de hidden_actions_json)
```

**Filosofía:** Las acciones ocultas permiten preparar trampas, emboscadas o defensas sin que el rival sepa. Es un sistema de información asimétrica que enriquece el combate por posts.

### 10.5 Cooldown y Reposo en Frontend

El endpoint `cards_my_deck.php` calcula los turnos del personaje en el hilo y cuándo se usó cada card:

```php
// Para cada post del personaje en este hilo:
$char_posts = [];  // array de post_ids en orden
$last_played_turns = [];

// Por cada card jugada:
$turn = array_search($pid, $char_posts); // 1-indexed
if ($turn > ($last_played_turns[$card_id] ?? 0)) {
    $last_played_turns[$card_id] = $turn;
}

// En la respuesta:
'meta' => [
    'total_posts' => 5,
    'last_played_turns' => [42 => 3, 43 => 1] // card_id => último turno
]
```

El frontend JS usa esta información para:
1. Deshabilitar cards en cooldown en el selector.
2. Mostrar tooltip: "Disponible en 2 posts".
3. Calcular si una técnica con `reposo` puede usarse.

### 10.6 Modificadores de Post

Los jugadores pueden incluir modificadores de stats en su post mediante `rpg_modifiers`:

```json
{"fue": 2, "agi": -1}
```

Estos modificadores se aplican temporalmente a los stats del personaje para la tirada:

```php
// En ProcessPostCards.php:
foreach ($raw_mods as $mod_stat => $mod_val) {
    $mod_stat = strtolower(trim((string)$mod_stat));
    $mod_val = (int)$mod_val;
    if ($mod_val !== 0 && in_array($mod_stat, $valid_stats, true)) {
        $turn_mods[$mod_stat] = ($turn_mods[$mod_stat] ?? 0) + $mod_val;
    }
}
$ctx = game_build_stat_context($stats_raw, $raceName, $turn_mods);
$stats_for_dice = $ctx['values']; // Stats con modificadores aplicados
```

---

## 11. Integración con la Ficha de Personaje

### 11.1 Vista del Deck

Archivo: `game/views/personaje/_tab_deck.php`

```html
<div id="pjTab_deck" class="pj-preview-tab-content">
    <div id="rpg-character-deck-container" data-char-id="<?= $char['id'] ?>" data-is-owner="<?= ... ?>">
        <div class="rpg-deck-empty">
            <i class="fas fa-circle-notch fa-spin rpg-deck-empty__icon"></i>
            Cargando Deck...
        </div>
    </div>
</div>
```

El tab Deck se carga dinámicamente vía AJAX (`cards_my_deck.php`). El JS (`personaje_page.js`) solicita el deck del personaje y renderiza las cards agrupadas por tipo.

### 11.2 Vista del Inventario (Gestión)

Archivo: `game/views/personaje/_tab_gestion.php` (submódulo "Gestionar Equipamiento")

Incluye:
1. **Capacidad de carga:** Muestra `cc_used / cc_max` con barra de progreso.
2. **Equipados actuales:** Lista de cards equipadas con opción de desequipar.
3. **Cards disponibles:** Grid de cards poseídas pero no equipadas, con peso y slot.
4. **Compañeros:** NPCs menores equipados en slot `companero`.
5. **Barco activo:** Barco equipado con stats de navegación.

### 11.3 Vista de Tienda

Archivo: `game/public/tienda.php`

```
page.php
├── Catálogo de cartas (cards con in_shop = 1)
│   ├── Armas (shop_category = 'armas')
│   ├── Armaduras (shop_category = 'armadura')
│   ├── Útiles (shop_category = 'utiles')
│   ├── Mascotas (shop_category = 'mascotas')
│   └── Naval (shop_category = 'naval')
├── Detalle de carta (tienda_card_detail.php)
└── Botón Comprar (POST a tienda_comprar.php)
```

Cada card en la tienda muestra:
- Nombre, tipo, rango, precio
- Imagen (image_url)
- Descripción
- Stats mecánicos (dados, cost_pe, peso)

### 11.4 JS de Cards

**`personaje_inventory.js`:**
- Carga el inventario via `inventory_get.php`
- Renderiza grids de equipados vs poseídos
- Maneja equipar/desequipar via `inventory_toggle.php`
- Actualiza barra de capacidad de carga en vivo

**`personaje_page.js` (módulo Deck):**
- Carga cards via `cards_my_deck.php`
- Renderiza por tipo (técnicas, equipo, Haki, Akuma, NPCs, barco)
- Filtra por tipo, rango, nombre
- Muestra detalles expandibles (dados, coste, descripción)

**`mis_personajes.js`:**
- Grid de personajes del usuario
- Vista rápida de cards principales de cada personaje

---

## 12. AJAX Endpoints — Catálogo Completo

### 12.1 Endpoints de Cards

| Endpoint | Método | Propósito | Autenticación |
|----------|--------|-----------|:-------------:|
| `cards_list.php` | GET | Listar catálogo completo de cards | Usuario logueado |
| `cards_my_deck.php` | GET | Obtener deck del personaje (con cooldowns) | Usuario logueado |
| `cards_for_post.php` | GET | Obtener cards jugadas en un post específico | Usuario logueado |
| `cards_play.php` | POST | Registrar cards jugadas en un post | Usuario logueado + CSRF |
| `cards_create.php` | POST | Crear card en catálogo (staff) | Staff nivel 3+ |
| `cards_update.php` | POST | Actualizar card en catálogo (staff) | Staff nivel 3+ |
| `cards_delete.php` | POST | Eliminar card del catálogo (staff) | Staff nivel 3+ |
| `cards_assign.php` | POST | Asignar card a personaje (staff) | Staff nivel 3+ |
| `cards_request_custom.php` | POST | Solicitar card personalizada | Dueño del personaje |
| `cards_resolve_request.php` | POST | Resolver solicitud de card (staff) | Staff nivel 2+ |
| `cards_request_list_mine.php` | GET | Listar solicitudes del jugador | Usuario logueado |
| `cards_pending_requests.php` | GET | Listar solicitudes pendientes (staff) | Staff nivel 2+ |
| `cards_request_action.php` | POST | Acción sobre solicitud (staff) | Staff nivel 2+ |
| `cards_request_reply.php` | POST | Responder a solicitud (staff) | Staff nivel 2+ |
| `cards_request_conforme.php` | POST | Marcar conforme (jugador) | Dueño del personaje |

### 12.2 Endpoints de Inventario/Tienda

| Endpoint | Método | Propósito | Autenticación |
|----------|--------|-----------|:-------------:|
| `inventory_get.php` | GET | Obtener inventario completo + equipados | Dueño/staff |
| `inventory_toggle.php` | POST | Equipar/desequipar card | Dueño/staff |
| `tienda_card_detail.php` | GET | Detalle de card en tienda | Usuario logueado |
| `tienda_comprar.php` | POST | Comprar card de la tienda | Dueño del personaje |
| `shop_catalog_list.php` | GET | Listar catálogo de tienda | Usuario logueado |
| `shop_catalog_update.php` | POST | Actualizar catálogo de tienda (staff) | Staff nivel 3+ |

---

## 13. Validaciones y Seguridad

### 13.1 Matriz de Permisos

| Operación | Owner PJ | Staff (1+) | Staff (2+) | Staff (3+) |
|-----------|:--------:|:----------:|:----------:|:----------:|
| Ver su deck | ✓ | — | — | — |
| Ver deck de otro (público) | — | ✓ | ✓ | ✓ |
| Ver cards en post ajeno | — | Solo reveladas | Solo reveladas | ✓ |
| Solicitar card | ✓ | — | — | — |
| Equipar/desequipar | ✓ | — | — | ✓ |
| Crear/editar card en catálogo | — | — | — | ✓ |
| Asignar card a personaje | — | — | — | ✓ |
| Eliminar card del catálogo | — | — | — | ✓ |
| Resolver solicitudes (creación) | — | — | — | ✓ |
| Resolver solicitudes (borrado) | — | — | ✓ | ✓ |
| Moderar solicitud | — | — | — | ✓ |
| Comprar en tienda | ✓ | — | — | — |

### 13.2 Validaciones por Endpoint

**`cards_play.php`:**
- Post debe pertenecer al usuario autenticado.
- Personaje activo del usuario debe ser el que juega las cards.
- Cada card debe estar en `game_character_cards` del personaje.
- La card no puede ya estar jugada en el mismo post (duck key en `game_post_cards`).

**`cards_assign.php`:**
- Staff level ≥ 3.
- Personaje y card deben existir.
- Si `card_type = 'akuma_no_mi'`: verificar que el personaje no tiene ya una akuma y que la fruta está disponible.
- Si `disciplina_slug` está definido: verificar grado suficiente.
- Si `card_type = 'haki'`: verificar nivel de Haki suficiente en `game_haki_progress`.

**`inventory_toggle.php`:**
- Personaje pertenece al usuario (o es staff).
- Card está en `game_character_cards`.
- Solo tipos `equipo`, `npc_menor`, `barco` se pueden equipar.
- Capacidad de carga suficiente (verificación en servidor).
- Límite de compañeros/barcos no excedido.

**`cards_request_custom.php`:**
- Personaje pertenece al usuario.
- Para `type = 'create'`: nombre y descripción requeridos.
- Para `type = 'add_existing'`: card debe existir en catálogo.
- No puede haber solicitud pendiente duplicada.

**`tienda_comprar.php`:**
- Card debe tener `in_shop = 1`.
- Berries suficientes.
- Card no puede estar ya en posesión del personaje.

### 13.3 Protección contra Fraude

1. **Snapshot por post:** El equipamiento se congela al momento del post. Un jugador no puede equipar un arma DESPUÉS de postear y reclamar que la usó.
2. **Verificación de posesión:** Antes de jugar una card, se verifica que está en `game_character_cards`.
3. **No rejugada en mismo post:** `game_post_cards` tiene FK contra duplicados (post_id + card_id).
4. **CSRF en todos los POST:** Todos los endpoints usan tokens CSRF.
5. **Ownership en servidor:** El frontend puede mentir (enviar character_id de otro), pero el servidor siempre verifica que el personaje pertenece al usuario.

---

## 14. API de Cards (OpenAPI)

### 14.1 Endpoints Documentados

Archivos en `packages/contracts/openapi/`:

**`GET /cards_list`**
```yaml
/cards_list:
  get:
    summary: Listar catálogo completo de cards
    responses:
      200:
        description: Array de cards del catálogo
      401:
        description: No autorizado
```

**`GET /cards_my_deck`**
```yaml
/cards_my_deck:
  get:
    summary: Obtener deck del personaje activo
    parameters:
      - name: character_id
        in: query
        schema: { type: integer }
      - name: thread_id
        in: query
        schema: { type: integer }
    responses:
      200:
        description: Array de cards del personaje con metadatos de cooldown
```

**`GET /inventory_get`**
```yaml
/inventory_get:
  get:
    summary: Obtener inventario y equipamiento
    parameters:
      - name: character_id
        in: query
        schema: { type: integer }
    responses:
      200:
        description: Equipados + poseídos + límites
```

**`POST /inventory_toggle`**
```yaml
/inventory_toggle:
  post:
    summary: Equipar o desequipar una card
    requestBody:
      required: true
      content:
        application/json:
          schema:
            type: object
            properties:
              character_id: { type: integer }
              card_id: { type: integer }
    responses:
      200:
        description: Estado de equipamiento actualizado
```

### 14.2 Ejemplos de Respuestas

Archivos en `packages/contracts/examples/`:
- `inventory_get.response.json` — respuesta completa de inventario
- `cards_my_deck.response.json` — deck del personaje con cooldowns

---

## 15. Filosofía de Diseño

### 15.1 ¿Por qué Cards como Abstracción Universal?

En el diseño original del foro, se consideraron dos alternativas:

| Alternativa | Problemas |
|-------------|-----------|
| **Tablas separadas** (game_skills, game_items, game_pets, game_ships) | 4+ sistemas de inventario, 4+ flujos de asignación, 4+ endpoints, redundancia de código |
| **Sistema genérico "Item"** con tipo y efectos en JSON | Terminó siendo cards — misma idea pero con nombre más RP-friendly |

**Cards ganó porque:**
- **Menos código:** Un solo `ProcessPostCards`, un solo `cards_assign`, una sola tabla `game_character_cards`.
- **Escalabilidad:** Si mañana se añade "vehículo volador" como tipo, es un nuevo valor ENUM.
- **Coherencia narrativa:** En One Piece, todo es "algo que tienes": una técnica, un arma, una fruta, un compañero.

### 15.2 ¿Por qué Tipos Explícitos y no un Sistema Genérico?

Se podría haber usado un solo tipo "card" con un `category` o `tags` para diferenciar. Pero los tipos explícitos (`card_type` ENUM) tienen ventajas:

1. **Validación específica por tipo:** Las `akuma_no_mi` tienen validación de fruta disponible. Las `haki` validan nivel de Haki. Las `equipo` escalan con execution_stat. No se puede hacer esto con un sistema genérico sin comerse switch-case gigantes.
2. **UI diferenciada:** Una técnica se muestra distinto a un barco. Los tipos explícitos permiten templates específicos.
3. **Reglas de equipamiento:** Solo ciertos tipos se equipan. El ENUM hace esta regla explícita en la DB.
4. **Índices y consultas:** Filtrar por tipo es rápido y claro (`WHERE card_type = 'haki'`).

### 15.3 ¿Por qué Staff Approval para Cards Personalizadas?

**Si los jugadores pudieran crear sus propias cards sin revisión:**
- "Técnica: Puño Definitivo — Daño infinito, sin coste, activación instantánea."
- "Equipo: Espada de la Verdad — Mata cualquier rival de un golpe."
- "NPC: Dios Todopoderoso — Stats infinitos."

**La revisión de staff garantiza:**
- **Balance:** Que la card no sea overpowered ni underwhelming.
- **Coherencia narrativa:** Que la técnica tenga sentido para el personaje y el mundo.
- **Calidad:** Que la descripción sea legible y complete.
- **Consistencia:** Que dos jugadores no tengan cards radicalmente distintas para el mismo efecto.

### 15.4 ¿Por qué Rangos D→SS en Cards?

Los rangos de cards siguen la misma escala que los stats del personaje (D→SS). Esto no es casual:

- **Coherencia del sistema:** Un jugador entiende "card rango B = poder medio", igual que "stat rango B = competente".
- **Gatekeeping natural:** Un personaje rango D difícilmente podría usar una card rango S sin que sea narrativamente incoherente.
- **Progresión visible:** Mejorar una card de C a B es un hito visible, igual que subir un stat.

### 15.5 Decisiones Clave y su Porqué

| Decisión | Alternativa descartada | Por qué se eligió así |
|----------|----------------------|----------------------|
| Catálogo maestro separado | Cards incrustadas en personaje (JSON column) | Una card puede ser de muchos personajes; no duplicar datos |
| Rango por personaje | Solo rango de catálogo | Permite upgrade de cards sin duplicar |
| `effects_json` como TEXT | Tabla normalizada de efectos | Flexibilidad total para distintos tipos de card |
| `dice` como VARCHAR evaluado en PHP | Tiradas precalculadas | La misma card puede dar resultados distintos según stats del personaje |
| Consumibles con cantidad | Consumibles como filas separadas | Stack de items sin saturar DB |
| Snapshot equipamiento por post | Confiar en inventario actual | Auditoría, evitar fraudes |

### 15.6 Filosofía de la Progresión de Cards

- **Las cards NO se suben de nivel automáticamente.** El personaje progresa (sube stats, mejora disciplinas) y luego solicita al staff mejores versiones de sus cards.
- **Las cards son más fáciles de adquirir que de mejorar.** Conseguir una técnica nueva es más rápido que mejorar una existente. Esto incentiva diversificar el repertorio.
- **Las cards consumibles son un recurso.** No son poder permanente. Un jugador con muchas municiones es peligroso hasta que se le acaban.

---

## 16. Consejos para Jugadores

### 16.1 Cómo Construir tu Loadout

**Principio 1: No pongas todas tus técnicas en activas.**
Una card pasiva te da un bonus constante sin gastar PE. Una reactiva te protege sin que tengas que pensar en ella. Un buen loadout tiene:

- 1–2 **pasivas** que definan tu estilo (ej: "Reflejos Felinos" +1 a tiradas de instinto)
- 1–2 **reactivas** para protección (ej: "Armadura de Haki" se activa al recibir daño)
- 3–5 **activas** de distintos tipos (ataque, defensa, utilidad, buff)

**Principio 2: Varía los costes de PE.**
No todas tus técnicas deben costar 30 PE. Ten:
- Técnicas baratas (5–10 PE) para intercambios ligeros.
- Técnicas medias (15–25 PE) para daño sostenido.
- Técnicas caras (30+ PE) para golpes definitivos con reposo largo.

**Principio 3: Sinergia entre tipos.**
- Técnica de `Fuego` + Arma con tag `[FUEGO]` = daño elemental potenciado.
- Haki de Armamento + Técnica cuerpo a cuerpo = daño a Logias.
- NPC menor + Técnica de apoyo = dos acciones por post.

### 16.2 ¿Qué Priorizar al Adquirir Cards?

**Para personajes nuevos (rango D–C):**
1. Un arma básica (compra en tienda, 500–1000 berries).
2. Una técnica de ataque barata (solicitud al staff).
3. Una técnica de defensa o movimiento (solicitud al staff).
4. Armadura ligera (tienda, si alcanzan berries).

**Para personajes intermedios (rango B–A):**
1. Mejorar armas existentes (rank up de card).
2. Adquirir técnicas con efectos especiales (veneno, fuego, aturdimiento).
3. Invertir en consumibles (munición, pociones).
4. Conseguir un NPC menor (compañero).

**Para personajes avanzados (rango A+):**
1. Técnicas ultimate con reposo largo pero daño masivo.
2. Cards de Haki (si no las tienes).
3. Mejora de equipo a rangos A/S.
4. Cards de estilo canónico.

### 16.3 Sinergias entre Tipos de Card

**Guerrero cuerpo a cuerpo:**
- `tecnica` de ataque cuerpo a cuerpo (ej: `2d8+fue`)
- `equipo` arma cuerpo a cuerpo con execution_stat = fue
- `pasiva` de resistencia (bonus a tiradas de defensa)
- `reactiva` de contraataque

**Usuario de Haki:**
- `haki` de Observación (percepción, predicción)
- `haki` de Armamento (daño a logias)
- `tecnica` que escala con `esp`
- `pasiva` de presencia (bonus a tiradas sociales)

**Tirador/Dexterity:**
- `equipo` arma a distancia (execution_stat = des)
- `tecnica` de puntería (bonus a tiradas de precisión)
- `consumible` munición especial (fuego, hielo, explosiva)
- `npc_menor` mascota que distrae

**Tanque:**
- `equipo` armadura pesada (peso 4, defensa alta)
- `tecnica` de provocación/atraer ataques
- `pasiva` de reducción de daño
- `reactiva` de absorción

### 16.4 Errores Comunes

- **Demasiadas técnicas caras:** Te quedas sin PE en 2 posts. Lleva técnicas baratas para mantener presión.
- **Ignorar las pasivas:** Una pasiva bien elegida te da ventaja constante. No subestimes su valor.
- **No tener reactivas:** Si solo tienes cards activas, cualquier ataque sorpresa te toma desprevenido.
- **Olvidar el reposo:** Si tu única técnica de ataque tiene reposo 3, en el post 1 la usas y luego no atacas por 3 posts. Ten alternativas.
- **No equipar bien:** Si tienes tu espada en el inventario pero no equipada, no puedes usarla en posts.
- **Peso excesivo:** Si llevas 4 armas grandes (peso total 12) pero tu capacidad es 10, no puedes equipar todo. Prioriza.

### 16.5 La Regla de Oro

**Tus cards definen lo que tu personaje PUEDE hacer mecánicamente.** No intentes tener una card para cada situación. Es mejor tener 5 cards que sabes usar bien que 20 cards que nunca usas. Cada card que solicitas debe responder a: *"¿Qué nueva capacidad narrativa le da esto a mi personaje?"*

---

## 17. Consejos para Staff

### 17.1 Cómo Evaluar Solicitudes de Cards Personalizadas

**Checklist de aprobación:**

| Criterio | Preguntas guía |
|----------|---------------|
| **Balance** | ¿Es el daño/coste comparable a cards existentes de mismo rango? ¿Es demasiado barata para su efecto? |
| **Coherencia narrativa** | ¿Tiene sentido que este personaje tenga esta técnica? ¿Su historia/entrenamiento lo justifica? |
| **Claridad mecánica** | ¿La fórmula de dados es correcta? ¿Los efectos están claramente descritos? |
| **Originalidad** | ¿Es una copia exacta de una card existente? (Si sí, mejor usar add_existing) |
| **Sinergia** | ¿Se combina con otras cards del personaje de forma rota? |
| **Tier adecuado** | ¿El tier corresponde al rango? ¿El personaje cumple los requisitos de disciplina? |

**Ejemplo de evaluación:**

```
Solicitud: "Golpe del Trueno" — técnica rango C, tier 1, dice "2d6+fue"
Descripción: "Un puñetazo cargado de electricidad"
Efectos: tipo_daño = "electrico", alcance = "cuerpo_a_cuerpo"

Evaluación:
✅ Balance: 2d6+fue es estándar para rango C. Comparable a "Puño de Hierro" (2d6+fue, físico).
✅ Coherencia: El personaje es usuario de una fruta eléctrica. Tiene sentido.
✅ Claridad: Fórmula correcta, efectos claros.
✅ Originalidad: No es copia exacta de otra card (tiene tipo eléctrico).
✅ Sinergia: Se combina con su fruta. OK, es intencional.
✅ Tier 1: No requiere disciplina. Personaje nuevo puede usarla.

Decisión: APROBAR
```

### 17.2 Balanceando Cards

**Reglas generales de balance:**

1. **El daño medio por PE gastado debe ser consistente.**
   - Rango C: ~1d6 por cada 5–8 PE
   - Rango B: ~2d6 por cada 10–15 PE
   - Rango A: ~3d6 por cada 20–30 PE
   - Rango S: ~4d6 por cada 40–50 PE
   - Rango SS: ~6d6 por cada 60–80 PE

2. **El reposo debe ser proporcional al impacto.**
   - Daño medio (1–2 turnos para matar a un rival normal): reposo 0–1
   - Daño alto (puede matar en 1 golpe): reposo 2–3
   - Daño masivo (casi seguro mata): reposo 4–5
   - Efecto de control (aturdir, paralizar): reposo 2+

3. **Las pasivas no deben dar más de +1 a un stat o +2 a tiradas específicas.**
   - Una pasiva que da +2 a FUE todo el tiempo es un stat point gratis. Es demasiado.
   - Una pasiva que da +1 a tiradas de FUE en situaciones específicas (ej: "en el mar") es adecuada.

4. **Las reactivas no deben ser mejores que las activas equivalentes.**
   - Una reactiva de defensa debería ser menos efectiva que una activa de defensa (porque la reactiva no cuesta decisión).

### 17.3 Revisando Card Usage en Threads

**Qué mirar cuando revisas un combate:**

1. **¿Las cards usadas estaban equipadas?**
   - Revisa `equipped_snapshot_json` del post contra las cards jugadas.
   - Si una card no estaba equipada y no es consumible, el uso es inválido.

2. **¿El personaje tiene las cards que usó?**
   - Verifica en `game_character_cards`.

3. **¿El coste de PE declarado es coherente con las cards usadas?**
   - Suma `cost_pe` de todas las cards activas usadas y compáralo con `pe_change`.
   - Si el personaje declaró PE = 100 y usó técnicas que suman 30 PE, y antes tenía 100 PE, es coherente.

4. **¿Se respetó el reposo?**
   - Revisa `last_played_turns` en el contexto del hilo.
   - Si una card con reposo 2 se usó en post 1 y post 2, es ilegal.

5. **¿Las tiradas de dados se evaluaron correctamente?**
   - Revisa `roll_result` en `game_post_cards`.
   - Si la fórmula es `2d6+fue` y el resultado muestra `2d6 (3+5) + 15 = 23`, está bien.

**Herramientas de auditoría:**

```sql
-- Ver qué cards se jugaron en un post
SELECT pc.*, c.name, c.card_type
FROM mybb_game_post_cards pc
JOIN mybb_game_cards c ON pc.card_id = c.id
WHERE pc.post_id = 12345;

-- Ver el snapshot de equipamiento del post
SELECT equipped_snapshot_json
FROM mybb_game_post_characters
WHERE post_id = 12345;

-- Ver cooldowns de cards en un hilo
SELECT pc.card_id, c.name, pc.played_at,
    COUNT(*) OVER (PARTITION BY pc.character_id, pc.card_id ORDER BY pc.played_at) as uso_numero
FROM mybb_game_post_cards pc
JOIN mybb_game_cards c ON pc.card_id = c.id
WHERE pc.character_id = 1
ORDER BY pc.played_at;
```

### 17.4 Guía para Crear Cards en el Catálogo

**Creación de cards de tienda:**

1. Usa `cards_create.php` (staff level 3).
2. Define `cost_berries` > 0 y `in_shop = 1`.
3. Asigna `shop_category` adecuada.
4. Para armas, define `execution_stat` para escalado.
5. Para consumibles, define `effects_json.equipo_type = "util"`.

**Cards de NPCs menores:**
1. Define `npc_mascota_type`: `npc` para compañero con acciones automáticas, `mascota` para acciones seleccionables.
2. Incluye `pv`, `pe`, y `stats` base.
3. Las acciones pueden ser strings legacy ("Garra: 1d8+fue") u objetos con name/dice/stat.

**Cards de Haki:**
1. Requisito: `haki_type` y `haki_level` en effects_json.
2. El nivel determina qué grado de Haki necesita el personaje.
3. Las cards de Haki no se equipan.

### 17.5 Errores Comunes al Moderar

- **"Le apruebo todo al amigo":** No todas las solicitudes son iguales. Una técnica rango S para un personaje rango D es desbalanceada.
- **"Esta card es igual a otra pero con otro nombre":** Si ya existe en catálogo, mejor usar add_existing.
- **"No reviso los dados":** Una fórmula mal escrita (`2d6+fue+fue` en lugar de `2d6+fue*2`) puede dar resultados impredecibles.
- **"Olvido el tier":** No pongas tier 5 en una card rango D. El tier debe reflejar el requisito real de disciplina.
- **"No actualizo la tienda":** Si creas cards para tienda, recuerda poner `in_shop = 1` y categoría. Las cards sin categoría no aparecen.

---

## 18. Referencia Rápida

### 18.1 Tablas Clave

| Tabla | Propósito | PK |
|-------|-----------|:---:|
| `game_cards` | Catálogo maestro de cards | `id` |
| `game_character_cards` | Cards poseídas por personaje | `id` (UK: character_id, card_id) |
| `game_character_inventory` | Cards equipadas actualmente | `character_id, card_id` |
| `game_post_cards` | Cards jugadas en posts | `id` |
| `game_card_requests` | Solicitudes de cards | `id` |

### 18.2 Archivos Clave

| Archivo | Propósito |
|---------|-----------|
| `game/ajax/cards_my_deck.php` | Obtener deck del personaje con cooldowns |
| `game/ajax/cards_play.php` | Registrar cards jugadas en post |
| `game/ajax/cards_for_post.php` | Ver cards jugadas en un post específico |
| `game/ajax/cards_create.php` | Crear card en catálogo (staff) |
| `game/ajax/cards_assign.php` | Asignar card a personaje (staff) |
| `game/ajax/cards_request_custom.php` | Solicitud de card personalizada |
| `game/ajax/cards_resolve_request.php` | Resolver solicitud (staff) |
| `game/ajax/inventory_get.php` | Obtener inventario completo |
| `game/ajax/inventory_toggle.php` | Equipar/desequipar card |
| `game/ajax/tienda_comprar.php` | Comprar card en tienda |
| `game/ajax/shop_catalog_list.php` | Listar catálogo de tienda |
| `game/inc/inventory_helpers.php` | Helpers de equipamiento (game_get_equipped_card_ids, etc.) |
| `game/inc/grado_helpers.php` | Validación de requisitos de disciplina (game_card_assignment_competencia_error) |
| `inc/plugins/game_postcharacter.php` | Plugin MyBB: procesamiento de cards en posts |
| `game/src/Application/UseCases/ProcessPostCards.php` | Use case de procesamiento de cards en posts |
| `game/views/personaje/_tab_deck.php` | Template del tab Deck en la ficha |
| `game/views/personaje/_tab_gestion.php` | Template de gestión de inventario |
| `game/public/tienda.php` | Página de tienda |
| `game/sql/install_schema_fragments.php` | Definiciones SQL de todas las tablas |
| `game/sql/migrate_cards.php` | Migración inicial del sistema de cards |

### 18.3 Fórmulas Esenciales

```
Capacidad de carga = 5 + floor(FUE_valor / 4) + (perk_carga ? 3 : 0)
Límite compañeros = 1 + (perk_vinculo ? 1 : 0)
Límite barcos = 1

Fórmula de dados:
  XdY → tirar X dados de Y caras
  stat → valor del stat
  N*stat → N × valor del stat
  [ARMA] → reemplazado por arma equipada
  [MUNICION] → reemplazado por munición usada
  +N → modificador plano
  [TAG] → etiqueta informativa

Reposo:
  current_turn - last_played_turn >= reposo → disponible
  Sino → en cooldown

Tier ↔ Rango de card:
  Tier 1: D–C
  Tier 2: C–B
  Tier 3: B–A
  Tier 4: A–S
  Tier 5: S–SS
```

---

*Fin del documento — Guía completa del Sistema de Cards v2.0*
*Generado desde: `Guias/sistemas/05-cards.md`*
*Referencia: `Guias/MAESTRO_SISTEMAS_RPG.md` — Sección 5*
