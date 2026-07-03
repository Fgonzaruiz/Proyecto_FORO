# 18. NPCs Menores (Cards tipo `npc_menor`)

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 18
> **Propósito:** Documentar exhaustivamente el subsistema de NPCs menores como cards: qué son, cómo se diferencian de los NPCs mayores, cómo se obtienen, cómo se equipan en el slot compañero, cómo se usan en posts, diseño de efectos_json, flujo de adquisición por oficio Domador, filosofía de diseño, y consejos para jugadores y staff.

---

## ÍNDICE

1. [¿Qué es un NPC Menor?](#1-qué-es-un-npc-menor)
2. [NPC Menor vs NPC Mayor](#2-npc-menor-vs-npc-mayor)
3. [Modelo de Datos — Card tipo `npc_menor`](#3-modelo-de-datos)
4. [Campos Específicos en `effects_json`](#4-campos-específicos-en-effects_json)
5. [Subtipo NPC vs Mascota](#5-subtipo-npc-vs-mascota)
6. [Slot Compañero (`companero`)](#6-slot-compañero)
7. [Límite de Compañeros y Perk `g_vinculo_companero`](#7-límite-de-compañeros-y-perk-g_vinculo_companero)
8. [Adquisición de NPCs Menores](#8-adquisición-de-npcs-menores)
9. [Oficio Domador](#9-oficio-domador)
10. [Activación en Posts](#10-activación-en-posts)
11. [Procesamiento PHP — NPC en Post](#11-procesamiento-php)
12. [Acciones del NPC: Formato y Evaluación](#12-acciones-del-npc-formato-y-evaluación)
13. [NPCs Menores en la Tienda](#13-npcs-menores-en-la-tienda)
14. [NPCs Menores en Misiones](#14-npcs-menores-en-misiones)
15. [Solicitud al Staff](#15-solicitud-al-staff)
16. [Muerte y Pérdida de un NPC Menor](#16-muerte-y-pérdida-de-un-npc-menor)
17. [Cards Múltiples del Mismo NPC (Rank Up)](#17-cards-múltiples-del-mismo-npc-rank-up)
18. [Inventario y UI](#18-inventario-y-ui)
19. [Integración con el Sistema de Cards](#19-integración-con-el-sistema-de-cards)
20. [Flujo de Creación por Staff](#20-flujo-de-creación-por-staff)
21. [Filosofía de Diseño](#21-filosofía-de-diseño)
22. [Consejos para Jugadores](#22-consejos-para-jugadores)
23. [Consejos para Staff](#23-consejos-para-staff)
24. [Referencia Rápida](#24-referencia-rápida)

---

## 1. ¿Qué es un NPC Menor?

Un **NPC menor** es un aliado, subordinado, bestia o mascota que un personaje jugador (PJ) posee como **card de tipo `npc_menor`**. Toda su información mecánica está contenida dentro de la card — no tiene ficha independiente en el sistema de personajes.

Los NPCs menores representan:

- **Bestias domesticadas**: lobos, aves rapaces, serpientes gigantes, criaturas exóticas.
- **Mascotas de tripulación**: animales no combatientes que aportan valor narrativo.
- **Subordinados**: secuaces que siguen órdenes del PJ (un marine bajo su mando, un sirviente).
- **Aliados vinculados**: compañeros que luchan junto al PJ (un guerrero jurado, un espíritu guardián).
- **Criaturas invocadas**: entidades temporales o permanentes que acompañan al personaje.

**Características fundamentales:**

| Propiedad | Valor |
|-----------|-------|
| `card_type` | `npc_menor` |
| ENUM en DB | Parte del ENUM `('tecnica', 'equipo', 'akuma_no_mi', 'haki', 'npc_menor', 'barco')` |
| Slot de inventario | `companero` |
| ¿Requiere equiparse? | Sí (debe estar en slot compañero para usarse en posts) |
| ¿Requiere ficha? | No — toda su info está en la card |
| `activation` | Siempre `"pasiva"` (el NPC existe, no se "activa") |
| `cost_pe` | Siempre `"—"` (el NPC no consume PE del PJ por existir) |
| `peso` en slots | `0` en slot compañero (no consume CC) |
| `in_shop` | Sí — puede venderse en tienda (categoría `mascotas`) |
| Comerciable | Sí — `cards_create.php` lo incluye en `$tradeable_types` |

### 1.1 ¿Por qué existen como cards y no como fichas?

La decisión arquitectónica clave es que los NPCs menores **no merecen una ficha entera**. Una ficha implica: stats completos, historial de posts, inventario propio, progresión independiente. Un NPC menor es un acompañante, no un segundo personaje.

Ventajas del modelo card:

1. **Ligereza administrativa**: El staff crea la card una vez y la asigna a quien corresponda. No hay que gestionar un segundo personaje.
2. **Inventario unificado**: El NPC menor se posee, equipa y usa como cualquier otra card. El jugador no necesita cambiar de ficha.
3. **Muerte y reposición**: Si el NPC muere, se elimina del inventario. No hay que "borrar personaje" ni gestionar herencia.
4. **Escalado por rango**: Un NPC menor puede tener rango C o B según la calidad del vínculo. El staff asigna el `current_rank` apropiado.

---

## 2. NPC Menor vs NPC Mayor

Esta distinción es **fundamental** para entender el sistema. No es lo mismo un NPC menor (card) que un NPC mayor (ficha completa).

### 2.1 Tabla Comparativa

| Aspecto | NPC Menor (Card) | NPC Mayor (Ficha) |
|---------|-----------------|-------------------|
| **Tipo de datos** | Fila en `game_cards` + fila en `game_character_cards` | Fila en `game_personajes` con `is_npc = 1` |
| **Ficha independiente** | No | Sí — stats completos, inventario, oficios |
| **Controlado por** | El jugador que posee la card | El staff (o un jugador designado como co-staff) |
| **Slot en inventario** | `companero` | No aplica (es entidad separada) |
| **Límite por PJ** | 1 (o 2 con perk) | Ilimitado (gestionado por staff) |
| **Uso en posts** | El jugador decide las acciones del NPC | El staff controla al NPC mayor |
| **Muerte** | Se elimina del inventario | Se marca como muerto en ficha |
| **Progresión** | No progresa independientemente | Puede progresar (sube stats, adquiere cards) |
| **Coste de creación** | Bajo (una card) | Alto (ficha completa, historia, relaciones) |
| **Peso narrativo** | Bajo/medio — acompañante | Alto — figura importante en la trama |
| **Ejemplos** | Lobo domesticado, loro mascota, marine subordinado | Shanks, Raleigh, Mihawk, un Almirante |

### 2.2 ¿Cuándo usar cada uno?

**Usa NPC Menor (card) cuando:**
- Es un acompañante que sigue al PJ (mascota, bestia, subordinado leal).
- Su función es complementaria (apoyo en combate, utilidad narrativa).
- No necesita progresión independiente ni historia propia.
- El jugador lo controla directamente.

**Usa NPC Mayor (ficha) cuando:**
- Es un personaje con agencia propia (toma decisiones, tiene objetivos).
- Requiere ficha para gestionar stats, inventario, oficios.
- Es relevante para la trama global (villano, aliado recurrente, figura histórica).
- El staff necesita controlarlo para narrativa.

### 2.3 Casos Frontera

**Subordinado con rango:** Un marine bajo el mando de un PJ puede ser card tipo `npc_menor` si su función es puramente de acompañante. Pero si el subordinado tiene su propia historia, decisiones y desarrollo, debería ser NPC mayor.

**Bestia que evoluciona:** Si una bestia puede evolucionar (ej: un Zoan despertado o una criatura mítica), el staff puede manejar esto como un **rank up** de la card (subir `current_rank`) o reemplazar la card por una versión mejorada.

---

## 3. Modelo de Datos

### 3.1 Definición en `game_cards`

La tabla `game_cards` es el catálogo maestro. Para cards de tipo `npc_menor`, estos campos tienen reglas específicas:

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

### 3.2 Campos con Reglas Especiales para `npc_menor`

| Campo | Regla para `npc_menor` | Razón |
|-------|------------------------|-------|
| `activation` | `"pasiva"` siempre | El NPC no se "activa" — existe. Su presencia es constante. |
| `cost_pe` | `"—"` siempre | El NPC no consume PE por estar presente. Las acciones pueden tener coste narrativo, pero no mecánico de PE. |
| `dice` | Vacío `""` | El NPC no tiene una tirada única. Sus acciones individuales definen los dados. |
| `execution_cost` | `0` | No aplica. |
| `execution_stat` | Vacío `""` | No aplica globalmente. Cada acción del NPC puede escalar con un stat diferente. |
| `reposo` | `0` | El NPC no tiene cooldown global. Las acciones individuales pueden tener limitaciones definidas en `notes`. |
| `duracion` | `0` | El NPC es permanente mientras esté vivo. |
| `peso` | `0` en slot compañero | El NPC ocupa slot `companero`, no `carga`. No consume Capacidad de Carga. |
| `oficio_slug` | Puede requerir `domador` | Para bestias, el oficio Domador puede ser requisito. |

### 3.3 Inventario: `game_character_cards`

Cuando un NPC menor se asigna a un personaje, se crea una fila en `game_character_cards`:

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

Para `npc_menor`, `cantidad` siempre es `1` (no son stackeables). El `current_rank` puede diferir del rango base en catálogo — un lobo rango C puede ser rango B para un domador experto.

---

## 4. Campos Específicos en `effects_json`

El campo `effects_json` para `npc_menor` tiene una estructura específica y obligatoria. Sin ella, la card no puede procesarse en posts.

### 4.1 Estructura Completa

```json
{
    "npc_mascota_type": "npc",
    "pv": 50,
    "pe": 30,
    "stats": {
        "fue": 2,
        "res": 3,
        "agi": 4,
        "des": 1,
        "int": 1,
        "inst": 3,
        "esp": 1
    },
    "imagen": "https://ejemplo.com/lobo.png",
    "personalidad": "Leal, protector, desconfía de extraños",
    "acciones": [
        {
            "name": "Garrazo",
            "dice": "1d8",
            "stat": "fue"
        },
        {
            "name": "Mordida",
            "dice": "1d6",
            "stat": "fue"
        },
        "Correr: 2d6+agi",
        "Aullido: efecto narrativo"
    ]
}
```

### 4.2 Desglose de Campos

#### `npc_mascota_type` (requerido)

Define el comportamiento del NPC en posts. Afecta cómo se seleccionan las acciones:

| Valor | Comportamiento modal |
|-------|---------------------|
| `"npc"` | Acción aleatoria. El sistema elige UNA acción al azar del array `acciones`. No requiere input del jugador. |
| `"mascota"` | Acción seleccionable por el jugador. El frontend muestra las acciones disponibles y el jugador elige cuál usar. |

Ver sección [5. Subtipo NPC vs Mascota](#5-subtipo-npc-vs-mascota) para detalles completos.

#### `pv` — Puntos de Vida

Entero positivo. Representa la vitalidad del NPC. Valor típico: 20–200 según rango.

Cuando el NPC recibe daño en combate narrativo, el staff debe trackear sus PV. Si PV llega a 0, el NPC muere (ver sección 16).

**Convención por rango:**

| Rango de card | PV típico |
|:-------------:|:---------:|
| D | 10–30 |
| C | 20–50 |
| B | 40–100 |
| A | 80–200 |
| S | 150–400 |
| SS | 300–800 |

El oficio Domador grado II otorga +10% PV base a bestias (`competencias_v2_seed_data.php:67`).

#### `pe` — Puntos de Energía

Entero positivo. Representa la energía del NPC para realizar acciones. Si el NPC usa acciones que consumen PE (definido narrativamente), se descuenta de aquí.

Valor típico: 10–100 según rango. No hay un sistema automático de descuento de PE para NPCs — es trackeo narrativo.

#### `stats` — Stats del NPC

Objeto con los 7 stats (`fue`, `res`, `agi`, `des`, `int`, `inst`, `esp`). Cada valor es un entero que representa el **valor numérico** del stat (no el rango D–SS).

Estos stats se usan para evaluar las tiradas de las acciones del NPC. Cuando una acción contiene un stat (ej: `"fue"`), el sistema lo reemplaza por el valor definido aquí.

**Ejemplo:** Si el NPC tiene `"fue": 4` (≈ rango C, valor ~15) y su acción es `"1d8+fue"`, la tirada se evalúa como `1d8 + 15`.

**Convención por rango del NPC:**

| Rango | Valor stat típico (por stat principal) |
|:-----:|:--------------------------------------:|
| D | 1–3 |
| C | 3–5 |
| B | 5–8 |
| A | 8–12 |
| S | 12–18 |
| SS | 18–25 |

Los stats secundarios (des, int) suelen ser más bajos, reflejando que el NPC es especializado.

#### `imagen` — URL de imagen (opcional)

URL a una imagen representativa del NPC. Se muestra en la card del inventario y en el detalle.

#### `personalidad` — Descripción de carácter (opcional pero recomendado)

Texto libre que describe la personalidad del NPC. Se usa para:
- Orientar al jugador sobre cómo rolear al NPC.
- Determinar cómo reacciona en situaciones de estrés.
- Definir si el NPC obedece órdenes sin cuestionar o tiene voluntad propia.

Ejemplos:
- `"Leal, protector, desconfía de extraños"`
- `"Curioso, travieso, fácil de distraer con comida"`
- `"Orgulloso, solo obedece a quien demuestra fuerza"`

#### `acciones` — Array de acciones (requerido)

Array de las acciones que el NPC puede realizar en un post. Cada acción es un objeto o un string (ver sección 12).

**Mínimo:** Al menos 1 acción. Si está vacío, el NPC solo puede realizar "Acción básica" narrativa sin tirada.

---

## 5. Subtipo NPC vs Mascota

El `npc_mascota_type` determina el **modo de selección de acciones** en el post. Es la distinción mecánica más importante entre tipos de NPC menor.

### 5.1 `npc` — Acción Aleatoria

**Comportamiento:** Cuando se juega la card en un post, el sistema elige **una acción al azar** del array `acciones`.

**Cuándo usarlo:**
- Bestias salvajes o semi-domesticadas que actúan por instinto.
- NPCs con voluntad propia que no siempre hacen lo que el jugador quiere.
- Criaturas impredecibles (ej: una serpiente que ataca al azar).
- Mascotas que hacen travesuras sin control.

**Implementación en PHP (`game_postcharacter.php:463-469`):**

```php
if ($npc_mascota_type === 'npc') {
    if (is_array($acciones) && count($acciones) > 0) {
        $picked = $acciones[array_rand($acciones)];
        $roll_result = game_postcharacter_format_npc_action($picked, $stats, $rpg_modifiers);
    } else {
        $roll_result = 'Acción básica de NPC';
    }
}
```

**Implicaciones RPG:** El jugador NO controla qué hace su bestia. Esto:
- Añade realismo (un animal no siempre obedece).
- Crea tensión narrativa (¿morderá al enemigo o huirá?).
- Equilibra bestias poderosas (no puedes "elegir" siempre su mejor ataque).

### 5.2 `mascota` — Acción Seleccionable

**Comportamiento:** Cuando se juega la card, el jugador selecciona **qué acción** usar de la lista. El frontend muestra las acciones disponibles y el jugador elige.

**Cuándo usarlo:**
- Subordinados leales que siguen órdenes.
- Mascotas entrenadas que responden a comandos.
- Aliados con los que el PJ tiene vínculo y comunicación.
- Criaturas inteligentes que entienden estrategia.

**Implementación en PHP (`game_postcharacter.php:470-488`):**

```php
} elseif ($npc_mascota_type === 'mascota') {
    if ($selected_action !== '') {
        $picked = null;
        if (is_array($acciones)) {
            foreach ($acciones as $act) {
                $act_name = is_array($act) ? ($act['name'] ?? '') : (string)$act;
                if (strcasecmp(trim($act_name), $selected_action) === 0) {
                    $picked = $act;
                    break;
                }
            }
        }
        $roll_result = $picked !== null
            ? game_postcharacter_format_npc_action($picked, $stats, $rpg_modifiers)
            : game_postcharacter_format_npc_action($selected_action, $stats, $rpg_modifiers);
    } else {
        $roll_result = 'Acción básica de Mascota';
    }
}
```

**Implicaciones RPG:** El jugador controla exactamente qué hace su compañero. Esto:
- Permite estrategia coordinada (PJ + compañero).
- Recompensa el vínculo (a mayor confianza, más control).
- Es más predecible para el staff.

### 5.3 ¿Se puede cambiar el subtipo?

Sí, pero solo el staff puede modificar `effects_json.npc_mascota_type`. Un cambio de `npc` a `mascota` puede representar:
- El vínculo entre PJ y bestia se fortaleció (tras un arco narrativo).
- La bestia fue entrenada (Domador grado III+).
- El NPC subordinado ganó lealtad.

Un cambio inverso (de `mascota` a `npc`) puede representar:
- El NPC se vuelve rebelde o inseguro.
- El vínculo se rompe por algún evento.
- La bestia entra en celo o está herida.

---

## 6. Slot Compañero (`companero`)

### 6.1 Qué es

El slot `companero` es uno de los tres slots del sistema de inventario (`carga`, `companero`, `barco`). Almacena cards de tipo `npc_menor`.

```sql
slot_type ENUM('carga', 'companero', 'barco') NOT NULL,
```

### 6.2 Reglas del Slot

| Propiedad | Valor |
|-----------|-------|
| Tipos de card que almacena | `npc_menor` |
| Límite por defecto | 1 |
| Límite con perk `g_vinculo_companero` | 2 |
| ¿Consume CC? | No (peso = 0 en slot compañero) |
| ¿Requerido para usar en posts? | Sí |
| ¿Puede desequiparse en medio de un hilo? | Sí, pero las acciones del post ya procesado no se revierten |

### 6.3 Equipar un NPC Menor

El jugador equipa un NPC menor desde el inventario usando `inventory_toggle.php`:

```json
// Request
{
    "character_id": 1,
    "card_id": 42
}
```

**Flujo específico para `npc_menor`:**

1. El sistema verifica `card_type === 'npc_menor'`.
2. Asigna `slot_type = 'companero'`.
3. Verifica que el personaje posee la card.
4. Si ya está equipada → la desequipa (DELETE).
5. Si no está equipada → valida límite de compañeros.
6. INSERT en `game_character_inventory` con `slot_type = 'companero'` y `peso = 0`.

```php
// Fragmento de inventory_toggle.php:62-65
$slot_type = 'carga';
if ($type === 'npc_menor') {
    $slot_type = 'companero';
}
```

### 6.4 Validación de Límite

```php
// inventory_toggle.php:92-120
$has_vinculo_companero = in_array('g_vinculo_companero', $general_ids)
    || in_array('g_vinculo_companero', $racial_ids)
    || in_array('rsi_vinculo_ext', $general_ids);

$companion_max = $has_vinculo_companero ? 2 : 1;

// ... recorrer equipados actuales para contar compañeros ...
} elseif ($slot_type === 'companero') {
    if ($companions_count >= $companion_max) {
        GameAjax::fail(400, "Límite de compañeros excedido ({$companions_count}/{$companion_max}). "
            . "Desequipa uno primero o amplía tu ranura por linaje.");
    }
}
```

### 6.5 Capacidad de Carga (CC)

Los NPCs menores en slot `companero` NO consumen Capacidad de Carga. El peso se ignora para este slot. Esto es intencional: el compañero no va en la mochila, camina al lado del PJ.

```php
// inventory_get.php:90-91 — solo items en 'carga' contribuyen a CC
if ($row['slot_type'] === 'carga') {
    $cc_used += $row['peso'];
}
```

---

## 7. Límite de Compañeros y Perk `g_vinculo_companero`

### 7.1 Límite Base

Todo personaje puede tener **1 NPC menor** equipado en el slot compañero. Si quiere cambiar de compañero, debe desequipar el actual y equipar el nuevo.

### 7.2 Perk `g_vinculo_companero`

El perk de linaje `g_vinculo_companero` (del árbol general o racial) amplía el límite a **2 compañeros** simultáneos.

**Detección en código:**

```php
$has_vinculo_companero = in_array('g_vinculo_companero', $general_ids)
    || in_array('g_vinculo_companero', $racial_ids)
    || in_array('rsi_vinculo_ext', $general_ids);
```

**Fuentes del perk:**

| Fuente | ID del perk | Notas |
|--------|-------------|-------|
| Linaje general | `g_vinculo_companero` | Perk genérico disponible en árbol general |
| Linaje racial | `g_vinculo_companero` | Perk racial (ej: Mink tienen afinidad animal) |
| Linaje general extendido | `rsi_vinculo_ext` | Perk alternativo (posiblemente de razas específicas) |

### 7.3 Implicaciones del Segundo Slot

Con 2 compañeros equipados:

- Ambos pueden actuar en el mismo post (sujeto a PA declarado por el jugador).
- Cada NPC se procesa independientemente, con su propia tirada.
- El jugador puede seleccionar una acción para cada mascota (si son tipo `mascota`), o ambas tiran aleatoriamente (si son tipo `npc`).

**Límite máximo:** No hay forma de obtener un tercer slot compañero. El sistema está diseñado para que 2 sea el máximo absoluto (Domador grado V permitiría hasta 3 bestias activas en combate, pero esto es un bonus de oficio, no un slot adicional; ver sección 9.1).

---

## 8. Adquisición de NPCs Menores

Un NPC menor puede obtenerse por 4 vías principales:

### 8.1 Compra en Tienda

Los NPCs menores con `in_shop = 1` y `cost_berries > 0` aparecen en la tienda dentro de la categoría `mascotas`:

```php
// cards_create.php:66-67
} elseif ($card_type === 'npc_menor') {
    $default_cat = 'mascotas';
}
```

**Validaciones en compra (`tienda_comprar.php`):**

1. La card existe y tiene `in_shop = 1`.
2. `card_type` está en `['equipo', 'npc_menor', 'barco']`.
3. El personaje no posee ya esta card (no son stackeables).
4. Tiene Berries suficientes (`cost_berries`).
5. La card no tiene requisitos de oficio/estilo que el PJ no cumpla.

**Precio típico:**

| Rango | Precio (Berries) |
|:-----:|:----------------:|
| D | 500–2.000 |
| C | 2.000–10.000 |
| B | 10.000–50.000 |
| A | 50.000–200.000 |
| S | Staff discretion |
| SS | Staff discretion |

### 8.2 Solicitud al Staff (Método Principal)

El jugador puede solicitar un NPC menor personalizado mediante el flujo de `cards_request_custom.php`:

```php
// cards_request_custom.php:49 — npc_menor es un tipo válido para solicitud
$valid_card_types = ['tecnica', 'equipo', 'akuma_no_mi', 'haki', 'npc_menor', 'barco'];
```

**Formulario de solicitud (`_tab_gestion.php:334-355`):**

```
┌─────────────────────────────────────────────┐
│ Subtipo:    [NPC ▼]                         │
│ Vida (HP):  [50    ]                        │
│ Tier:       [1     ]                        │
│ Acciones:                                   │
│   [Garrazo: 1d8+fue] [X]                    │
│   [Mordida: 1d6+fue] [X]                    │
│   [+ Añadir Acción]                         │
│ Descripción y Efecto Propuesto:             │
│ [Un lobo gigante de pelaje blanco...]       │
│ [Enviar Propuesta al Staff]                 │
└─────────────────────────────────────────────┘
```

**Flujo de aprobación:**

1. Jugador llena el formulario con nombre, descripción, subtipo, PV, acciones.
2. Se crea una solicitud en `game_card_requests` con `request_type = 'create'`.
3. Staff revisa y puede:
   - Aprobar: crea la card en `game_cards`, la asigna al personaje.
   - Rechazar: con mensaje explicativo.
   - Pedir cambios: mediante `discussion_json`.
4. Jugador confirma conformidad (status `conforme`).

### 8.3 Drop en Misiones

Las misiones pueden otorgar NPCs menores como recompensa. Esto se gestiona:

1. El staff define la card en `game_cards` (si no existe ya).
2. Al completar la misión, el sistema de recompensas ejecuta:
   ```php
   INSERT INTO game_character_cards (character_id, card_id, current_rank, assigned_by)
   VALUES ({$characterId}, {$cardId}, '{$rank}', {$staffId});
   ```
3. El jugador recibe una notificación de que ha obtenido un nuevo compañero.

**Ejemplos de drops:**
- Bestia derrotada que, impresionada por la fuerza del PJ, se une a él.
- Cría de animal encontrada en una misión de exploración.
- Subordinado rescatado que jura lealtad.

### 8.4 Oficio de Domador

El Domador es el oficio especializado en adquirir y potenciar NPCs menores de tipo bestia. Ver [sección 9](#9-oficio-domador).

---

## 9. Oficio Domador

El oficio **Domador** (`slug: domador`) es la vía más rica y estratégica para obtener y potenciar NPCs menores de tipo bestia. Está en la categoría **Utilidad**.

### 9.1 Desbloqueos por Grado

Según `competencias_v2_seed_data.php:65-71`:

| Grado | Bonus | Cómo impacta en NPCs menores |
|:-----:|-------|------------------------------|
| I | `Cartas npc_menor bestia en inventario. Bonus +1 en oráculos de bestias.` | Permite **poseer** cards `npc_menor` de tipo bestia. Sin este grado, un personaje no bestia no puede tener bestias como NPCs menores (excepto por staff grant). |
| II | `Bestias +10% PV base. Domesticación por oráculo (grado II+).` | Las bestias del domador tienen +10% de PV sobre el valor base de `effects_json.pv`. Además, puede domesticar bestias salvajes mediante tirada de oráculo. |
| III | `Vínculo: bestia actúa independiente en un post (1 vez por hilo).` | Una vez por hilo, el domador puede hacer que su bestia actúe **sin consumir PA del PJ**. La bestia realiza su acción de forma independiente. |
| IV | `Solicitar bestia única. Crafteo tier 4: equipo para bestias.` | El domador puede solicitar al staff una bestia única (no disponible en catálogo general). Además, puede crear equipo para bestias (armaduras, accesorios). |
| V | `Manada: hasta 3 bestias activas en combate. Bestias +1 rango efectivo en stats.` | El límite de compañeros bestia se amplía a 3 (solo para bestias). Además, todas las bestias del domador ganan +1 rango efectivo en sus stats (el valor numérico aumenta ~50%). Esto es el **techo del sistema de compañeros**. |

### 9.2 Requisitos del Domador para Tener Bestias

La validación ocurre en la asignación de la card. Si la card `npc_menor` tiene `oficio_slug = 'domador'`, el personaje debe tener el oficio Domador al menos en el grado especificado por `tier`:

```php
// Lógica en game_card_assignment_competencia_error()
if ($card['oficio_slug'] === 'domador') {
    $domador_rank = game_oficio_get_rank($characterId, 'domador');
    if ($domador_rank < $card['tier']) {
        return "Requiere Domador grado " . game_grado_label($card['tier']);
    }
}
```

**Mapeo tier → grado requerido:**

| Tier de card | Grado Domador requerido |
|:------------:|:----------------------:|
| 1 | I |
| 2 | II |
| 3 | III |
| 4 | IV |
| 5 | V |

### 9.3 Domador en Post

Cuando un domador juega a su NPC menor en un post:

1. Si tiene **grado III**, puede activar el vínculo independiente (1 vez por hilo). El sistema no descuenta PA del PJ para la acción de la bestia.
2. Si tiene **grado V** y la bestia es de tipo `npc`, el bonus de +1 rango efectivo se aplica a los stats en `effects_json.stats` antes de evaluar la tirada.
3. El bonus +1 en oráculos de bestias (grado I) se aplica a cualquier tirada que involucre bestias (domesticación, rastreo, comunicación).

### 9.4 Domador + Perk `g_vinculo_companero`

La combinación de Domador grado V + perk `g_vinculo_companero` permite:

- **3 bestias activas** (Domador V permite hasta 3 bestias en combate).
- **2 mascotas/subordinados no-bestia** (del slot compañero, ampliado por perk).

Esto significa que un Domador V con el perk puede tener hasta **3 bestias + 1 mascota** si el staff lo permite (aunque narrativamente sería inusual). El sistema valida `companions_count < companion_max` en `inventory_toggle.php`, y el Domador V amplía ese máximo para bestias.

---

## 10. Activación en Posts

### 10.1 Flujo General

Para usar un NPC menor en un post:

1. El NPC debe estar **equipado** en el slot `companero`.
2. En el formulario de post, el jugador selecciona la card del NPC.
3. Si es tipo `mascota`, selecciona qué acción usa.
4. Si es tipo `npc`, la acción se elige aleatoriamente.
5. El sistema procesa la card y genera el resultado de la tirada.
6. El resultado se guarda en `game_post_cards.roll_result`.
7. El snapshot de equipamiento (`equipped_snapshot_json`) registra que el NPC estaba equipado.

### 10.2 Validación en Post

El sistema verifica que la card está equipada:

```php
// game_postcharacter_card_allowed_in_post()
function game_postcharacter_card_allowed_in_post(
    string $cardType,
    int $cardId,
    array $equippedIds,
    bool $isConsumible = false
): bool {
    if (!function_exists('game_card_requires_equipped_slot')
        || !game_card_requires_equipped_slot($cardType, $isConsumible)
    ) {
        return true;  // técnicas, haki, consumibles no requieren equiparse
    }
    return in_array($cardId, $equippedIds, true);
}
```

Para `npc_menor`, `game_card_requires_equipped_slot('npc_menor')` retorna `true`, por lo que **debe estar en el snapshot del post** para ser válida.

### 10.3 Formulario de Post

El jugador no declara `cost_pe` para el NPC (es `"—"`). El formulario registra la card como usada pero sin coste de PE asociado. El NPC se procesa como una card adicional sin afectar el PE del PJ.

### 10.4 Múltiples NPCs en un Post

Si el PJ tiene 2 compañeros equipados (por perk `g_vinculo_companero`):

- Ambos pueden actuar en el mismo post.
- Cada uno procesa su acción independientemente.
- El jugador declara PA suficiente para ambas acciones (o, si es Domador III, una de ellas no consume PA).

**Ejemplo de JSON de cards jugadas en post:**

```json
{
    "cards": [
        {
            "card_id": 42,
            "selected_action": "Garrazo",
            "card_type": "npc_menor"
        },
        {
            "card_id": 43,
            "selected_action": "",
            "card_type": "npc_menor"
        }
    ]
}
```

En este ejemplo, card_id 42 es `mascota` (acción seleccionada "Garrazo"), y card_id 43 es `npc` (acción vacía → aleatoria).

---

## 11. Procesamiento PHP — NPC en Post

El procesamiento de NPCs menores en posts ocurre en `game_postcharacter.php`. Aquí está el flujo completo:

### 11.1 Entry Point

El hook `datahandler_post_insert_post_end` de MyBB dispara `game_postcharacter_save_post()`, que itera sobre las cards jugadas en el post.

```php
// game_postcharacter.php:395-420 — para cada card jugada
$own = $db->fetch_array($own_q);
// ... verifica posesión y equipamiento ...

$card = $db->fetch_array($card_q);
// ... verifica card_allowed_in_post ...
```

### 11.2 Detección de Tipo NPC

```php
// game_postcharacter.php:456-488
if ($card['card_type'] === 'npc_menor') {
    $effects = json_decode($card['effects_json'] ?? '{}', true);
    $npc_mascota_type = $effects['npc_mascota_type'] ?? 'npc';
    $acciones = $effects['acciones'] ?? [];
    
    // Si acciones es string (formato legacy), convertir a array
    if (is_string($acciones)) {
        $acciones = array_filter(array_map('trim', explode("\n", $acciones)));
    }
    
    // Procesar según subtipo
    if ($npc_mascota_type === 'npc') {
        // Acción aleatoria
        if (is_array($acciones) && count($acciones) > 0) {
            $picked = $acciones[array_rand($acciones)];
            $roll_result = game_postcharacter_format_npc_action($picked, $stats, $rpg_modifiers);
        } else {
            $roll_result = 'Acción básica de NPC';
        }
    } elseif ($npc_mascota_type === 'mascota') {
        // Acción seleccionada por jugador
        if ($selected_action !== '') {
            // Buscar la acción por nombre
            $picked = null;
            if (is_array($acciones)) {
                foreach ($acciones as $act) {
                    $act_name = is_array($act) ? ($act['name'] ?? '') : (string)$act;
                    if (strcasecmp(trim($act_name), $selected_action) === 0) {
                        $picked = $act;
                        break;
                    }
                }
            }
            $roll_result = $picked !== null
                ? game_postcharacter_format_npc_action($picked, $stats, $rpg_modifiers)
                : game_postcharacter_format_npc_action($selected_action, $stats, $rpg_modifiers);
        } else {
            $roll_result = 'Acción básica de Mascota';
        }
    }
}
```

### 11.3 Registro en `game_post_cards`

Independientemente del resultado de la tirada, se registra el uso:

```php
INSERT INTO game_post_cards (post_id, character_id, card_id, played_rank, roll_result)
VALUES ({$postId}, {$characterId}, {$cardId}, '{$currentRank}', '{$rollResult}');
```

### 11.4 Snapshot de Equipamiento

El snapshot se guarda ANTES de procesar las cards, por lo que refleja el equipamiento al momento del post:

```php
// game_postcharacter_save_equipped_snapshot()
// Obtiene IDs de cards equipadas (incluyendo NPCs en slot compañero)
$ids = game_get_equipped_card_ids($cid);
// Guarda en game_post_characters.equipped_snapshot_json
```

---

## 12. Acciones del NPC: Formato y Evaluación

### 12.1 Formatos Soportados

Las acciones pueden definirse en dos formatos dentro del array `acciones` de `effects_json`:

#### Formato Objeto (recomendado)

```json
{
    "name": "Garrazo",
    "dice": "1d8",
    "stat": "fue"
}
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `name` | string | Nombre visible de la acción |
| `dice` | string | Fórmula de dados (ej: `1d8`, `2d6`, `3d4`) |
| `stat` | string (opcional) | Slug del stat que escala la acción: `fue`, `res`, `agi`, `des`, `int`, `inst`, `esp` |

Cuando se define `stat`, el sistema construye automáticamente la fórmula `{dice}+{stat}`. El valor del stat se obtiene de `effects_json.stats`.

#### Formato String

```json
"Correr: 2d6+agi"
```

El sistema parsea el string extrayendo la fórmula de dados después de `:` o `–`. Si no encuentra fórmula, la acción es puramente narrativa.

### 12.2 Evaluación con `game_postcharacter_format_npc_action()`

```php
function game_postcharacter_format_npc_action($action, array $stats, array $rpg_modifiers = []): string
{
    if (is_array($action)) {
        $name = trim((string)($action['name'] ?? 'Acción'));
        $dice = trim((string)($action['dice'] ?? ''));
        $stat = trim((string)($action['stat'] ?? ''));
        if ($dice !== '') {
            $formula = $dice . ($stat !== '' ? '+' . $stat : '');
            try {
                $evaluated = game_evaluate_dice_roll($formula, $stats, $rpg_modifiers);
                return $name . ': ' . $evaluated;
            } catch (Throwable $t) {
                return $name;
            }
        }
        return $name;
    }
    // String format
    $text = trim((string)$action);
    if ($text === '') {
        return 'Acción básica';
    }
    if (preg_match('/\d+d\d+/i', $text)) {
        return game_evaluate_dice_in_action($text, $stats, $rpg_modifiers);
    }
    return $text;
}
```

### 12.3 Ejemplos de Evaluación

| Acción (effects_json) | Stats del NPC | Resultado |
|-----------------------|---------------|-----------|
| `{"name":"Garrazo","dice":"1d8","stat":"fue"}` | `fue: 4` (valor 15) | `"Garrazo: 1d8 (5) + 15 = 20"` |
| `{"name":"Mordida","dice":"2d6","stat":"fue"}` | `fue: 3` (valor 8) | `"Mordida: 2d6 (3+4) + 8 = 15"` |
| `"Correr: 2d6+agi"` | `agi: 5` (valor 25) | `"Correr: 2d6 (6+2) + 25 = 33"` |
| `"Aullido: efecto narrativo"` | — | `"Aullido: efecto narrativo"` (sin dados) |

### 12.4 Acciones sin Dados

Si una acción no tiene dados (es puramente narrativa), el sistema la devuelve tal cual. Esto es útil para:
- Acciones de utilidad (oler, observar, buscar).
- Interacciones sociales (ladrar, gruñir, acariciar).
- Efectos ambientales (iluminar, cavar, construir).

Estas acciones no producen tirada mecánica y no afectan al combate numérico.

### 12.5 Cantidad de Acciones Recomendada

| Subtipo | Acciones mínimas | Acciones recomendadas |
|---------|:----------------:|:---------------------:|
| `npc` | 2 | 3–6 (para que la aleatoriedad sea interesante) |
| `mascota` | 1 | 2–4 (para dar opciones sin abrumar) |

Para `npc` con pocas acciones, la aleatoriedad es predecible. Con 6+ acciones, el comportamiento es más impredecible y realista.

---

## 13. NPCs Menores en la Tienda

### 13.1 Categoría `mascotas`

Los NPCs menores en la tienda aparecen bajo la categoría `mascotas`:

```php
// cards_create.php:66-67
} elseif ($card_type === 'npc_menor') {
    $default_cat = 'mascotas';
}
```

### 13.2 Tienda Frontend

En `tienda.php:140`, la categoría se mapea a un icono:

```php
'npc_menor' => '<i class="fas fa-paw"></i> Compañero',
```

### 13.3 Cards Creables por Staff

Cuando el staff crea una card `npc_menor` en `cards_create.php`:

```php
// cards_create.php:55-59
$tradeable_types = ['equipo', 'npc_menor', 'barco'];
if (in_array($card_type, $tradeable_types, true) && $cost_berries < 1) {
    // Error: las cartas comerciables deben tener precio > 0
}
```

**Esto significa:** Toda card `npc_menor` creada por staff **debe tener** `cost_berries > 0` (aunque `in_shop` puede ser 0 si no se vende en tienda sino que se asigna directamente).

### 13.4 Shop Catalog

```php
// shop_catalog_list.php:41
WHERE card_type IN ('equipo', 'npc_menor', 'barco')
```

### 13.5 Venta

Los NPCs menores pueden venderse al 50% de su valor:

```php
// tienda_vender.php:52
$valid_types = ['equipo', 'npc_menor', 'barco'];
```

---

## 14. NPCs Menores en Misiones

### 14.1 Compañero en Misión

Cuando un personaje acepta una misión, puede llevar a su NPC menor como acompañante. El sistema de misiones maneja esto mediante:

```php
// mission_accept.php:20
$companions = is_array($input['companions'] ?? null) ? $input['companions'] : [];
```

### 14.2 Nombre del Compañero en Misiones

```php
// mission_confirm.php:66-68
$companionName = $db->fetch_field($pjNameQ, 'name') ?: 'Acompañante';
```

Si el NPC menor no tiene nombre configurado, se usa "Acompañante" por defecto.

### 14.3 Eventos de Misión con Compañero

Durante la misión, el sistema puede generar eventos que involucren al compañero:

- **Invitación a unirse (`mission_confirm.php:120`)**: El NPC acepta unirse a la misión.
- **Rechazo (`mission_confirm.php:129`)**: El NPC rechaza la invitación (el registro se elimina).

Estos eventos son puramente narrativos y no tienen efecto mecánico automático.

### 14.4 Recompensa de Misión: Drop de NPC

Como se mencionó en 8.3, las misiones pueden otorgar NPCs menores como recompensa. El staff configura esto en la definición de la misión.

---

## 15. Solicitud al Staff

### 15.1 Solicitud de Creación de NPC Menor

El jugador solicita un NPC menor personalizado mediante `cards_request_custom.php`. El tipo `npc_menor` es válido:

```php
$valid_card_types = ['tecnica', 'equipo', 'akuma_no_mi', 'haki', 'npc_menor', 'barco'];
```

**Campos del formulario de solicitud (`_tab_gestion.php`):**

| Campo | ID HTML | Descripción |
|-------|---------|-------------|
| Subtipo | `req_npc_mascota_type` | `npc` o `mascota` |
| Vida (HP) | `req_npc_vida` | PV del NPC (entero, default 50) |
| Tier | `req_npc_tier` | Tier 1–5 (oculto por defecto, visible si aplica) |
| Acciones | `req-npc-actions-container` | Array dinámico de acciones (nombre + dados) |

**JavaScript de acciones:**

```javascript
// Botón "+ Añadir Acción" en _tab_gestion.php
document.getElementById('btn-req-npc-add-action').addEventListener('click', function() {
    var container = document.getElementById('req-npc-actions-container');
    var div = document.createElement('div');
    div.className = 'rpg-npc-action-row';
    div.innerHTML = '<input type="text" placeholder="Nombre (ej: Garrazo)" class="textbox rpg-form-input" style="width:40%">'
        + '<input type="text" placeholder="Dados (ej: 1d8+fue)" class="textbox rpg-form-input" style="width:40%">'
        + '<button type="button" class="rpg-btn-remove-action">&times;</button>';
    container.appendChild(div);
});
```

### 15.2 Flujo de Aprobación

1. **Jugador envía solicitud**: Se crea `game_card_requests` con `request_type = 'create'`.
2. **Staff revisa**: Puede modificar la propuesta (balance, PV, acciones).
3. **Staff crea la card**: Usando `cards_create.php` (o `cartas_staff.php`) con los datos acordados.
4. **Staff asigna la card**: Usando `cards_assign.php` (o manualmente en DB).
5. **Staff cierra la solicitud**: Status `aprobada`.
6. **Jugador confirma**: Status `conforme`.

### 15.3 Solicitud de Adición de NPC Existente

Si el NPC menor ya existe en el catálogo (ej: un "Lobo de las Nieves" genérico), el jugador puede solicitar que se le asigne mediante `request_type = 'add_existing'`:

```json
{
    "character_id": 42,
    "card_id": 101,
    "request_type": "add_existing",
    "current_rank": "C"
}
```

El staff verifica:
- El NPC es de tipo `npc_menor`.
- El personaje cumple requisitos (oficio Domador si aplica).
- Hay justificación narrativa.

---

## 16. Muerte y Pérdida de un NPC Menor

### 16.1 Muerte en Combate

Si un NPC menor muere en combate narrativo:

1. El jugador debe reportarlo al staff (o el staff detectarlo en revisión de post).
2. El staff verifica que la muerte es narrativamente consistente.
3. El staff elimina la card del inventario del personaje:
   ```sql
   DELETE FROM game_character_cards WHERE character_id = ? AND card_id = ?;
   DELETE FROM game_character_inventory WHERE character_id = ? AND card_id = ?;
   ```
4. La card en `game_cards` permanece (puede ser adquirida por otro personaje).

**No hay resurrección mecánica.** Si el NPC muere, se pierde permanentemente (salvo eventos narrativos especiales como la Fruta Yomi Yomi, que son excepcionales).

### 16.2 Pérdida por Liberación

El jugador puede liberar voluntariamente a un NPC menor:

- **Bestia**: Liberarla en su hábitat natural.
- **Subordinado**: Dejarlo ir (fin de contrato, acuerdo mutuo).
- **Mascota**: Entregarla a otro cuidador.

El flujo es el mismo: el staff elimina las filas de inventario y posesión.

### 16.3 ¿Se puede recuperar un NPC perdido?

- **Si la card sigue en catálogo**: El personaje puede solicitar una nueva asignación (con justificación narrativa).
- **Si la card era única**: Deberá obtenerla de nuevo mediante el método original (domesticación, misión, etc.).
- **Si era un NPC único creado para ese PJ**: Se pierde para siempre (a menos que el staff decida reanimarlo narrativamente).

---

## 17. Cards Múltiples del Mismo NPC (Rank Up)

### 17.1 Rank Up de NPC

Un NPC menor puede mejorar su rango (`current_rank` en `game_character_cards`). Esto no modifica el catálogo, solo la copia del personaje:

**¿Qué significa rank up para un NPC menor?**
- Sus stats en `effects_json.stats` pueden aumentar (el staff modifica el JSON).
- Sus PV pueden aumentar.
- Puede ganar nuevas acciones.

**Ejemplo:** Un lobo rango C (FUE 4, PV 30) que sube a rango B (FUE 6, PV 50, nueva acción "Mordisco Letal").

### 17.2 ¿Cards duplicadas?

El sistema NO permite tener la misma card dos veces en `game_character_cards` (UNIQUE KEY `idx_char_card`). Para tener "dos lobos", el catálogo debe tener dos cards distintas (ej: "Lobo de las Nieves" y "Lobo de las Nieves Alpha").

### 17.3 Evolución vs Rank Up

| Concepto | Rank Up | Evolución |
|----------|---------|-----------|
| Cambia la card existente | Sí (mismo card_id) | No (nuevo card_id) |
| Sube rango | Sí | Sí (nuevo rango en nueva card) |
| Cambia nombre | No | Sí (ej: "Lobezno" → "Lobo Adulto") |
| Cambia acciones | Puede (staff edita effects_json) | Sí (nueva card, nuevas acciones) |
| Requiere nueva solicitud | Sí (solicitud de rank up) | Sí (solicitud de nueva card) |

La evolución es preferible cuando el NPC cambia cualitativamente (crece, muta, despierta). El Rank Up es para mejoras graduales.

---

## 18. Inventario y UI

### 18.1 Vista en Inventario

En el subtab "Equipamiento" de la ficha, los NPCs menores aparecen:

1. En el **slot compañero** (arriba, con contador): `"COMPAÑEROS: 1 / 1"`.
2. En el **grid de cards** filtrable por pestaña "Compañeros".

```html
<div class="rpg-inv-slot-card">
    <div class="rpg-inv-slot-icon"><i class="fas fa-paw"></i></div>
    <div class="rpg-inv-slot-desc">
        <span class="rpg-inv-slot-lbl">COMPAÑEROS</span>
        <strong id="rpg-inv-companion-display">0 / 1</strong>
    </div>
</div>
```

### 18.2 Filtros

```html
<button class="rpg-inv-filter-btn" data-filter="npc_menor">Compañeros</button>
```

### 18.3 Renderizado de Card NPC

En el grid, cada NPC menor se muestra con:

- **Icono**: `fa-paw` (predeterminado para compañeros).
- **Nombre**: El `name` de la card.
- **Rango**: Badge D–SS.
- **Subtipo**: `"Compañero"` (hardcoded en `inventory_get.php:120`).
- **Badge "Equipado"** (si está en slot compañero).
- **Botón Equipar/Desequipar**.

```php
// inventory_get.php:119-122 — subtipo fijo para npc_menor
if ($row['card_type'] === 'npc_menor') {
    $row['subtipo'] = 'Compañero';
}
```

### 18.4 Tooltip de Acciones

Cuando el jugador pasa el ratón sobre una card `npc_menor` en el inventario, debería mostrar:

- PV y PE del NPC (de `effects_json`).
- Stats principales.
- Lista de acciones disponibles.
- Personalidad.

Esto no está implementado en el frontend actual, pero es una mejora deseable (ver sección 23.7).

---

## 19. Integración con el Sistema de Cards

### 19.1 Como Card Equipable

`npc_menor` es uno de los 3 tipos que requieren equipamiento para usarse:

```php
function game_card_requires_equipped_slot(string $cardType, bool $isConsumible = false): bool
{
    if ($isConsumible) return false;
    return in_array($cardType, ['equipo', 'npc_menor', 'barco'], true);
}
```

### 19.2 Como Card Comerciable

`npc_menor` está en `$tradeable_types`:

```php
$tradeable_types = ['equipo', 'npc_menor', 'barco'];
```

Esto significa:
- Puede venderse en tienda.
- Puede comprarse.
- Debe tener `cost_berries > 0`.
- Aparece en `shop_catalog_list.php`.
- Aparece en `shop_catalog_update.php`.

### 19.3 Como Card en `game_character_inventory`

```sql
slot_type ENUM('carga', 'companero', 'barco') NOT NULL,
```

El slot `companero` es exclusivo para `npc_menor`. No hay otro tipo de card que pueda ocuparlo.

### 19.4 Snapshot en Posts

El NPC menor equipado aparece en `equipped_snapshot_json` como un `card_id` más:

```json
[42, 55, 70, 80]
// 42 = espada, 55 = escudo, 70 = barco, 80 = lobo compañero
```

### 19.5 Rango del NPC y Post

Al jugarse, se registra el `current_rank` del NPC:

```sql
INSERT INTO game_post_cards (post_id, character_id, card_id, played_rank, roll_result)
VALUES (123, 1, 80, 'C', 'Garrazo: 1d8 (5) + 15 = 20');
```

---

## 20. Flujo de Creación por Staff

### 20.1 Panel Staff (`cartas_staff.php`)

El staff puede crear NPCs menores desde el panel de gestión de cartas:

```html
<div id="fields-npc" class="rpg-staff-field-section">
    <div>
        <label>Subtipo</label>
        <select id="npc_mascota_type">
            <option value="npc">NPC</option>
            <option value="mascota">Mascota</option>
        </select>
    </div>
    <div>
        <label>Vida</label>
        <input type="number" id="npc_vida" min="0" value="50">
    </div>
    <div id="wrapper-npc-tier">
        <label>Tier de Mascota</label>
        <input type="number" id="npc_tier" min="1" value="1">
    </div>
    <div class="rpg-grid-full">
        <label>Acciones</label>
        <div id="npc-actions-container" class="rpg-npc-actions"></div>
        <button type="button" id="btn-npc-add-action">+ Añadir Acción</button>
    </div>
</div>
```

### 20.2 Campos Requeridos al Crear

| Campo | Valor para `npc_menor` |
|-------|------------------------|
| `name` | Nombre del NPC (ej: "Lobo de las Nieves") |
| `card_type` | `npc_menor` |
| `rank` | D–SS según poder |
| `activation` | `pasiva` (forzado) |
| `cost_pe` | `—` (forzado) |
| `dice` | `""` (vacío) |
| `effects_json` | Ver estructura en sección 4 |
| `description` | Presentación narrativa: especie, aspecto, carácter |
| `notes` | Notas internas: cuántas veces actúa por hilo, condiciones |
| `peso` | `0` (no consume CC) |
| `cost_berries` | > 0 si es comerciable |
| `in_shop` | 1 si está en tienda, 0 si es asignación directa |
| `shop_category` | `mascotas` (automático) |
| `tier` | 1–5 según poder |
| `oficio_slug` | `domador` si requiere Domador, NULL si no |

### 20.3 Reglas del Maestro (`MAESTRO_SISTEMAS_RPG.md`)

```
TIPO: npc_menor
Para aliados, bestias o subordinados menores.
  activation  → SIEMPRE "pasiva" (el NPC existe, no se activa)
  cost_pe     → "—"
  dice        → ""
  effects_json → Sus capacidades en un post (qué puede hacer)
  notes       → Cuántas veces puede actuar por hilo, condiciones de uso
  peso        → Slot companion (3-5) [Nota: en implementación real es 0]
  description → Presenta al NPC: especie, aspecto, carácter

REGLAS GENERALES:
1. Para npc_menor: description debe presentar al NPC con nombre y personalidad.
```

**Aclaración sobre `peso`:** La documentación del maestro indica peso 3-5 para slot companion, pero **en la implementación real** el peso no se usa para slot `companero` (el cálculo de CC solo suma items en slot `carga`). El valor puede ignorarse o usarse como referencia narrativa de tamaño.

### 20.4 Asignación a Personaje

Después de crear la card, el staff la asigna al personaje:

```php
INSERT INTO game_character_cards (character_id, card_id, current_rank, assigned_by)
VALUES (42, 101, 'C', 1);
```

O usando el endpoint `cards_assign.php`.

---

## 21. Filosofía de Diseño

### 21.1 ¿Por qué los NPCs menores son cards y no fichas?

1. **Simplicidad administrativa:** Menos tablas, menos queries, menos complejidad. Un acompañante no necesita ficha propia.
2. **Inventario unificado:** El jugador gestiona todo desde un solo lugar: su deck de cards.
3. **Muerte y ciclo de vida:** Si el NPC muere, se borra del inventario. No hay bajas en la tabla de personajes.
4. **Rango como poder:** El rango de la card determina el poder del NPC. Un lobo rango C es más débil que uno rango A.
5. **Extensibilidad:** El mismo sistema de cards soporta bestias, mascotas, subordinados, invocaciones.

### 21.2 ¿Por qué slot compañero separado de carga?

Los compañeros no son objetos. No van en una mochila ni ocupan espacio físico de la misma forma que un arma. Separar el slot refleja que:

- El compañero es un **ser vivo** que acompaña, no un objeto que se porta.
- Tener un compañero no debería competir con la capacidad de carga del PJ.
- El límite no es de peso sino de **vínculo y atención** (solo puedes coordinar con 1-2 compañeros).

### 21.3 ¿Por qué dos subtipos (npc vs mascota)?

La distinción refleja diferentes niveles de **control y predictibilidad**:

- **NPC (aleatorio):** Para bestias salvajes o criaturas con voluntad impredecible. El jugador no controla exactamente qué hacen. Esto equilibra bestias potencialmente muy poderosas (no puedes siempre elegir su mejor ataque).
- **Mascota (seleccionable):** Para aliados entrenados o vinculados. El jugador controla la acción. Esto recompensa la inversión narrativa en el vínculo.

### 21.4 ¿Por qué el Domador tiene tanto poder sobre bestias?

El Domador es el **único oficio** que impacta directamente en el sistema de compañeros. Esto es intencional:

- **Especialización:** Un personaje que invierte PP en Domador (en lugar de, ej., Médico o Herrero) está haciendo una elección estratégica. Su recompensa es tener compañeros más poderosos y numerosos.
- **Identidad de personaje:** El Domador es el "tipo de los animales". Sin un oficio dedicado, cualquier personaje podría tener bestias igual de poderosas, diluyendo la identidad del domador.
- **Escalado controlado:** Los bonuses por grado están calibrados para que Domador V sea impresionante pero no dominante (3 bestias +1 rango efectivo).

### 21.5 ¿Por qué no hay PE cost para el NPC?

El NPC menor no consume PE del personaje porque:
- Representa un ser independiente con su propia energía.
- El coste de tener un compañero no es de energía sino de oportunidad (slot ocupado, atención dividida).
- El NPC tiene su propio PE (`effects_json.pe`) para acciones que lo requieran (uso narrativo, no automático).

### 21.6 ¿Por qué el NPC no tiene ficha de stats completa?

Un NPC menor no necesita los 7 stats con rangos D–SS completos. Solo necesita valores numéricos para las acciones que realiza. Esto:

- Reduce la carga de creación (el staff no debe balancear 7 stats).
- Simplifica el uso (no hay que calcular rangos globales).
- Se enfoca en lo importante: qué puede HACER el NPC, no qué tan "completo" es.

---

## 22. Consejos para Jugadores

### 22.1 Elegir tu Compañero

- **Piensa en tu personaje:** ¿Tu PJ es un luchador solitario? ¿Un líder con seguidores? ¿Un amante de los animales? El compañero debe reflejar la identidad del PJ.
- **Sinergia mecánica:** Un NPC con acciones ofensivas complementa a un PJ tanque. Un NPC con utilidad (rastreo, curación) complementa a un PJ DPS.
- **Coste de oportunidad:** Solo tienes 1 slot (o 2 con perk). Elige sabiamente qué compañero llevas a cada hilo.
- **Personalidad importa:** Un NPC con personalidad "agresivo" puede ser útil en combate pero problemático en misiones de sigilo.

### 22.2 NPC vs Mascota

- Si quieres **control total** sobre las acciones, elige `mascota`.
- Si quieres **sorpresa y realismo** (y no te importa que a veces tu bestia haga algo inesperado), elige `npc`.
- Puedes solicitar al staff cambiar el subtipo si el vínculo con tu bestia evoluciona.

### 22.3 Solicitar un NPC Menor

- **Sé específico:** Nombre, especie, personalidad, acciones esperadas. Cuanto más detalle, mejor podrá el staff balancear la card.
- **Justifica narrativamente:** ¿Dónde encontraste a esta bestia? ¿Por qué te sigue? ¿Qué vínculo tienen?
- **Pide acciones variadas:** 3–4 acciones con diferentes propósitos (ataque, defensa, utilidad) hacen al NPC más versátil.
- **No pidas stats exagerados:** Un lobo rango D no debería tener FUE 10. Revisa las tablas de stats por rango.

### 22.4 Domador

- Si tu personaje es Domador, **invierte en el oficio**. Cada grado desbloquea mejoras significativas para tus bestias.
- Domador grado II te permite **domesticar bestias** mediante oráculos. Esto es una fuente de NPCs menores que no requiere compra ni solicitud (pero requiere éxito en la tirada).
- Domador grado V es el **techo del sistema**: 3 bestias activas + stats mejorados. Planifica tu progresión.

### 22.5 Cuidado de tu NPC

- Trackea los PV de tu NPC en combate. Si muere, se pierde (salvo eventos narrativos especiales).
- No abuses de tu NPC en todos los posts. El sistema permite usarlo, pero narrativamente puede saturar.
- Si tu NPC tiene personalidad, **roleala**. Un lobo que desconfía de extraños no debería hacerse amigo de un desconocido en 2 posts.

### 22.6 Perk `g_vinculo_companero`

- Es una inversión de linaje valiosa si planeas tener 2 compañeros.
- Combínalo con Domador para máximo potencial.
- Recuerda: 2 compañeros requieren 2 slots equipados. Si quieres cambiar de compañero, debes desequipar uno.

---

## 23. Consejos para Staff

### 23.1 Balance de NPCs Menores

- Un NPC menor **no debería ser más poderoso que un PJ del mismo rango**. Si un lobo rango C tiene stats de PJ rango C, algo está mal.
- Las acciones del NPC deberían ser **menos versátiles** que las de un PJ. Un PJ tiene 10+ técnicas; un NPC tiene 2–4 acciones.
- El **número de acciones por post** debería limitarse: 1 acción por NPC por post (excepto Domador III que permite 1 acción independiente extra).

### 23.2 Creación de Cards

- **Sigue la estructura de `effects_json`** estrictamente. Un error en el JSON rompe el procesamiento en posts.
- **Prueba la card** en un post de prueba antes de asignarla. Verifica que las acciones se evalúan correctamente.
- **Documenta en `notes`** las limitaciones: "Puede actuar 1 vez por post", "Solo en tierra firme", "No puede realizar acciones complejas".
- **Usa `oficio_slug = 'domador'`** para bestias que requieran Domador. Esto evita que un personaje sin domador tenga bestias poderosas sin justificación.

### 23.3 Revisión de Solicitudes

Al revisar una solicitud de `npc_menor`:

1. **Verifica el balance de acciones:** ¿Las tiradas son apropiadas para el rango? Un lobo rango C no debería tener "2d10+fue".
2. **Verifica el subtipo:** ¿Es adecuado `npc` (aleatorio) o `mascota` (controlado) para la personalidad del NPC?
3. **Verifica los PV:** ¿Son consistentes con el rango y la narrativa?
4. **Verifica la personalidad:** ¿Está definida? Un NPC sin personalidad es aburrido.
5. **Verifica la justificación:** ¿El PJ encontró/domesticó/recibió al NPC de forma coherente?

### 23.4 Muerte de NPCs

- La muerte de un NPC menor debería ser **significativa narrativamente**, no un mero trámite mecánico.
- Si el NPC muere en combate, verifica que el combate fue justo y el jugador tuvo oportunidad de evitar la muerte.
- Ofrece al jugador la opción de **rito narrativo** de despedida antes de eliminar la card.
- Considera si la muerte puede revertirse (evento especial, fruta del diablo, intervención divina).

### 23.5 Domador y Domesticación

- Para Domador grado II+, el jugador puede intentar domesticar bestias mediante oráculo. Establece **dificultades claras**:
  - Bestia dócil (DC 8): Fácil.
  - Bestia salvaje (DC 12): Moderado.
  - Bestia legendaria (DC 18+): Muy difícil.
  - Bestia de otro domador (DC 20+): Casi imposible.
- La domesticación por oráculo solo funciona en **contexto narrativo** (la bestia debe estar presente en el hilo).
- El resultado exitoso no es inmediato: la bestia se vuelve "amistosa" pero puede requerir varios posts para ganar su confianza.

### 23.6 Evolución de NPCs

- Considera permitir **evoluciones narrativas** de NPCs menores (una cría que crece, una bestia que despierta un poder latente).
- La evolución justifica subir el rango de la card o reemplazarla por una versión mejorada.
- No des evoluciones gratis: el jugador debe invertir narrativamente (entrenar a la bestia, superar un desafío juntos).

### 23.7 Mejoras Técnicas Futuras

Para el sistema de NPCs menores, estas mejoras serían valiosas:

| Mejora | Prioridad | Descripción |
|--------|:---------:|-------------|
| Tooltip de acciones en inventario | Alta | Mostrar PV, stats, acciones al pasar el ratón sobre la card |
| Preview de tirada en solicitud | Alta | Calcular tiradas esperadas antes de aprobar |
| Track automático de PV en post | Media | Restar daño automáticamente al NPC cuando recibe daño en post |
| Límite de acciones por post | Media | Validar que no se usen más de 1 acción por NPC por post |
| Personalidad afectando tiradas | Baja | NPC con personalidad "cobarde" podría huir en lugar de atacar si la tirada falla |
| Bestiario público | Baja | Catálogo de bestias disponibles con imágenes y stats básicos |

---

## 24. Referencia Rápida

### 24.1 Ficha Técnica

| Propiedad | Valor |
|-----------|-------|
| Tipo de card | `npc_menor` |
| Slot de inventario | `companero` |
| Límite base | 1 |
| Límite con perk `g_vinculo_companero` | 2 |
| Límite Domador V | 3 bestias |
| ¿Consume CC? | No (peso = 0 en slot) |
| `activation` | `pasiva` |
| `cost_pe` | `—` |
| `dice` | `""` (vacío) |
| Subtipos | `npc` (aleatorio), `mascota` (seleccionable) |
| Comerciable | Sí |
| Categoría tienda | `mascotas` |
| Oficio asociado | `domador` |

### 24.2 Estructura `effects_json`

```json
{
    "npc_mascota_type": "npc",
    "pv": 50,
    "pe": 30,
    "stats": {
        "fue": 2, "res": 2, "agi": 3,
        "des": 1, "int": 1, "inst": 2, "esp": 1
    },
    "imagen": "url",
    "personalidad": "texto",
    "acciones": [
        {"name": "Acción", "dice": "1d8", "stat": "fue"},
        "Acción string: 2d6+agi"
    ]
}
```

### 24.3 Desbloqueos Domador

| Grado | Bonus |
|:-----:|-------|
| I | Poseer bestias, +1 oráculos bestias |
| II | +10% PV, domesticación por oráculo |
| III | Acción independiente 1 vez/hilo |
| IV | Bestia única, equipo para bestias |
| V | Hasta 3 bestias, +1 rango efectivo stats |

### 24.4 Archivos Relevantes

| Archivo | Propósito |
|---------|-----------|
| `game/ajax/cards_create.php` | Creación de card npc_menor (línea 55–67) |
| `game/ajax/cards_update.php` | Actualización de card |
| `game/ajax/cards_request_custom.php` | Solicitud de card personalizada |
| `game/ajax/cards_assign.php` | Asignación de card a personaje |
| `game/ajax/inventory_toggle.php` | Equipar/desequipar en slot compañero (línea 58–65) |
| `game/ajax/inventory_get.php` | Obtener estado del inventario (línea 62–66, 119–122) |
| `game/ajax/tienda_comprar.php` | Compra en tienda |
| `game/ajax/tienda_vender.php` | Venta en tienda |
| `inc/plugins/game_postcharacter.php` | Procesamiento en posts (línea 456–488, 1185–1210) |
| `game/public/cartas_staff.php` | Panel staff para crear cards (línea 367–389) |
| `game/views/personaje/_tab_gestion.php` | Formulario de solicitud (línea 334–355) |
| `game/sql/competencias_v2_seed_data.php` | Datos del oficio Domador (línea 65–71) |
| `game/ajax/shop_catalog_list.php` | Catálogo de tienda |
| `game/inc/inventory_helpers.php` | Helpers de inventario |
| `Guias/MAESTRO_SISTEMAS_RPG.md` | Guía maestra (sección 18, línea 506–517) |
| `Guias/sistemas/05-cards.md` | Sistema de cards (sección 4.5) |
| `Guias/sistemas/06-inventario.md` | Inventario y slots (sección 2.3) |
| `Guias/sistemas/09-oficios.md` | Oficios (sección 2.4, 1.4, línea 181) |
