# 29. Sistema de Despertar (Awakening)

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 29
> **Archivo de referencia cruzada:** `Guias/sistemas/11-akuma.md` · Sección 11
> **Propósito:** Documentar exhaustivamente el subsistema de Despertar de Akuma no Mi: qué es, cómo se solicita, requisitos, diferencias por clase de fruta, la card de awakening, despertar incompleto vs completo, evaluación de staff, filosofía de diseño, y todos los archivos involucrados.

---

## ÍNDICE

1. [¿Qué es el Awakening?](#1-qué-es-el-awakening)
2. [Arquitectura General](#2-arquitectura-general)
3. [La Card de Awakening](#3-la-card-de-awakening)
4. [Requisitos de Despertar](#4-requisitos-de-despertar)
5. [Sistema de Seguimiento de Usos](#5-sistema-de-seguimiento-de-usos)
6. [Hub UI — `peticion_akuma.php`](#6-hub-ui)
7. [Formulario de Solicitud — `peticion_awakening.php`](#7-formulario-de-solicitud)
8. [JavaScript de Awakening](#8-javascript-de-awakening)
9. [Awakening Completo (Full)](#9-awakening-completo)
10. [Awakening Incompleto (Pre-Awakening)](#10-awakening-incompleto)
11. [Diferencias por Clase de Fruta](#11-diferencias-por-clase-de-fruta)
12. [Evaluación del Staff](#12-evaluación-del-staff)
13. [Flujo Completo: Solicitud → Resolución](#13-flujo-completo)
14. [Filosofía de Diseño](#14-filosofía-de-diseño)
15. [Consejos para Jugadores](#15-consejos-para-jugadores)
16. [Consejos para Staff](#16-consejos-para-staff)
17. [Referencia Rápida de Archivos](#17-referencia-rápida-de-archivos)

---

## 1. ¿Qué es el Awakening?

### 1.1 Definición

El **Awakening (Despertar)** es el hito de poder máximo de un usuario de Fruta del Diablo. Representa el momento en que el usuario trasciende los límites normales de su akuma y desbloquea su potencial latente. En el sistema del foro, se gestiona como una **card especial** ligada a la card de la fruta original del personaje, añadiéndose al inventario **sin reemplazar** la primera.

```
┌──────────────────────────────────────────────────────┐
│            INVENTARIO DEL PERSONAJE                   │
│                                                        │
│  ┌────────────────────┐   ┌────────────────────────┐  │
│  │ Card Akuma Original │   │ Card Awakening (SS)    │  │
│  │ Rank: D / C / B    │   │ Rank: SS               │  │
│  │ / A / S            │   │ Poderes amplificados   │  │
│  │ Poderes base       │   │ + 1-2 habilidades      │  │
│  │                    │   │ nuevas                 │  │
│  └────────────────────┘   └────────────────────────┘  │
│           No se reemplaza        Se añade              │
│           Siguen existiendo      como carta extra      │
│           los poderes base                             │
└──────────────────────────────────────────────────────┘
```

### 1.2 Principios Fundamentales

| Principio | Explicación |
|-----------|-------------|
| **No reemplaza** | La carta akuma original permanece intacta. El awakening es una carta adicional. |
| **Se gana, no se compra** | El despertar requiere uso demostrado de la fruta, no es adquirible con berries ni PD. |
| **Basado en uso real** | El sistema cuenta automáticamente los usos registrados en `game_post_cards`. |
| **Dos niveles** | Incompleto (pre-awakening, con drawbacks) y Completo (full awakening, sin penalización). |
| **Requiere staff** | Toda solicitud de awakening pasa por revisión y aprobación del staff. |
| **Único por fruta** | Solo se puede despertar la akuma que se posee. No hay "doble despertar". |

### 1.3 Qué NO es el Awakening

- No es una técnica que se aprende (como Haki).
- No es un objeto que se equipa.
- No es un reemplazo de la fruta original — los poderes base siguen existiendo.
- No es automático al alcanzar X usos — siempre requiere solicitud y aprobación.
- No es un evento que ocurre "de repente" — es un proceso narrativo y mecánico.

---

## 2. Arquitectura General

### 2.1 Capas del Subsistema

```
┌─────────────────────────────────────────────────────────────┐
│                    CLIENTE (Navegador)                       │
│  ┌───────────────────────────────────────────────────────┐  │
│  │ peticion_akuma.php (Hub)                              │  │
│  │  ├── Panel de progreso (barra, números, estado)       │  │
│  │  ├── Botón "Solicitar Despertar Incompleto"           │  │
│  │  └── Botón "Solicitar Awakening Completo"             │  │
│  │                                                        │  │
│  │ peticion_awakening.php (Formulario)                    │  │
│  │  ├── Link a condición narrativa                       │  │
│  │  └── Propuesta de poderes/efectos                     │  │
│  │                                                        │  │
│  │ peticion_awakening.js (Envío AJAX)                     │  │
│  └───────────────────────────────────────────────────────┘  │
└──────────────────────────┬──────────────────────────────────┘
                           │ HTTP
┌──────────────────────────▼──────────────────────────────────┐
│                    PHP — BACKEND                             │
│  ┌───────────────────────────────────────────────────────┐  │
│  │ peticion_akuma.php          → Hub + cálculo progreso  │  │
│  │ peticion_awakening.php      → Formulario por tipo     │  │
│  │ admin_requests_submit.php   → AJAX submit             │  │
│  │ AdminRequestService::create → Crea petición           │  │
│  │ AdminRequestService::resolve→ Staff aprueba/deniega   │  │
│  └───────────────────────────────────────────────────────┘  │
└──────────────────────────┬──────────────────────────────────┘
                           │ MySQL
┌──────────────────────────▼──────────────────────────────────┐
│                    BASE DE DATOS                             │
│  ┌───────────────────────────────────────────────────────┐  │
│  │ game_cards              → Card akuma + awakening      │  │
│  │ game_character_cards    → Inventario del personaje    │  │
│  │ game_post_cards         → Registro de usos            │  │
│  │ game_admin_requests     → Peticiones de awakening     │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

### 2.2 Flujo de Datos Simplificado

```
Uso en posts → game_post_cards (acumula contador)
       ↓
Hub en peticion_akuma.php (lee usos_totales)
       ↓
Jugador alcanza umbral → Botón habilitado
       ↓
Jugador completa formulario en peticion_awakening.php
       ↓
admin_requests_submit.php → Crea petición en game_admin_requests
       ↓
Staff recibe notificación → Evalúa → Aprueba/Deniega
       ↓
Si aprueba: Crea/Modifica carta awakening → Personaje obtiene poder
```

---

## 3. La Card de Awakening

### 3.1 Rango SS

Independientemente del tier de la fruta original (1-5), la carta de awakening tiene rango **SS**. Esto refleja que el despertar trasciende la escala normal de poder:

```php
// La card awakening siempre se crea con rank SS
$input['rank'] = 'SS';
$input['activation'] = 'pasiva'; // O 'activa' según el diseño del staff
$input['tier'] = 5; // Efectivamente tier 5 por ser despertar
```

**Justificación:** El despertar es, por definición, el techo de poder de una akuma. Incluso una fruta tier 1 (rango D) despierta a rango SS porque su poder despertado trasciende su clasificación original. Un personaje con fruta tier 1 despertada sigue siendo menos poderoso en términos base que uno con fruta tier 5 sin despertar, pero el despertar le da herramientas únicas que equiparan la balanza.

### 3.2 Contenido de la Card

La card de awakening incluye:

1. **Poderes base drásticamente amplificados:** Todas las capacidades existentes de la fruta se potencian (mayor alcance, más daño, efecto más duradero).
2. **1-2 habilidades completamente nuevas:** Poderes que no existían en la fruta original y que reflejan la naturaleza del despertar.
3. **Manifestación única:** Una descripción narrativa de cómo se manifiesta el despertar en el personaje.

**Ejemplo de estructura en `effects_json`:**

```json
{
    "card_type": "awakening",
    "akuma_original_id": 42,
    "nombre_despertar": "Despertar de la Gomu Gomu no Mi",
    "manifestacion": "El entorno se vuelve gomoso, el usuario controla la elasticidad de todo lo que le rodea.",
    "poderes_amplificados": [
        "Gomu Gomu no Rocket: alcance triplicado, puede impulsar objetos del entorno.",
        "Gomu Gomu no Elephant Gun: ahora puede estirar el entorno para crear múltiples impactos."
    ],
    "habilidades_nuevas": [
        {
            "nombre": "Gomu Gomu no Kaen",
            "descripcion": "Combina elasticidad extrema con fricción para generar llamas.",
            "tipo": "ofensivo",
            "coste_pe": 40
        }
    ],
    "drawbacks": []  // Solo para Incompleto
}
```

### 3.3 Relación con la Card Original

| Aspecto | Card Akuma Original | Card Awakening |
|---------|-------------------|----------------|
| Rango | D-S (según tier) | SS (fijo) |
| Propósito | Poderes base de la fruta | Poderes despertados |
| Se elimina al obtener awakening | No | No (ambas coexisten) |
| Se puede usar sin awakening | Sí | No |
| Usos contabilizados | Sí | No (el awakening no requiere usos adicionales) |
| Modificable por staff | Sí | Sí |

### 3.4 Visualización en el Inventario

En el deck del personaje, ambas cartas aparecen:

```
┌─────────────────────────────────────────────┐
│  Gomu Gomu no Mi          [Rango B] [Akuma] │
│  Poderes base de elasticidad                │
├─────────────────────────────────────────────┤
│  ✦ Despertar: Gomu Gomu    [Rango SS] [Desp]│
│  Poderes amplificados + nuevas habilidades  │
└─────────────────────────────────────────────┘
```

---

## 4. Requisitos de Despertar

### 4.1 Visión General

Cada fruta tiene condiciones específicas dictadas por el staff en su campo `notes` durante la aprobación original. Los requisitos se dividen en tres categorías:

```
┌──────────────────────────────────────────────────────────────┐
│                    REQUISITOS DE AWAKENING                    │
│                                                              │
│  1. Uso Mínimo ─── Cantidad de usos registrados de la carta │
│                     (30/50/75/100 según tier)                 │
│                                                              │
│  2. Rango Mínimo ─ Rango global del personaje requerido      │
│                     (B para tier 1-2, S para tier 4)         │
│                                                              │
│  3. Condición ──── Situación narrativa específica            │
│     Narrativa      (proteger a alguien, sobrevivir, etc.)    │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### 4.2 Uso Mínimo (Usage Threshold)

Cantidad de veces que la fruta debe haber sido usada en posts registrados (`game_post_cards`). El número base depende del tier:

| Tier | `usos_base` | Notas |
|:----:|:-----------:|-------|
| 1 | 30 | Fruta menor, despertar alcanzable relativamente rápido |
| 2 | 30 | Fruta común, mismo umbral que tier 1 |
| 3 | 50 | Fruta notable, requiere más dedicación |
| 4 | 75 | Fruta poderosa, compromiso significativo |
| 5 | 100 | Fruta legendaria, solo los más dedicados |

```php
$usos_base = 30;
if ($tier == 3) $usos_base = 50;
if ($tier == 4) $usos_base = 75;
if ($tier >= 5) $usos_base = 100;
```

**Filosofía:** El uso mínimo garantiza que el jugador ha roleado activamente con su fruta antes de poder despertarla. No se puede "guardar" la fruta y despertarla sin haberla usado. Cada uso es un post donde la carta akuma fue efectivamente utilizada en una situación de juego.

### 4.3 Rango Mínimo (Global Rank)

El personaje debe haber alcanzado un rango global mínimo para poder acceder al despertar:

| Tier de fruta | Rango mínimo requerido |
|:-------------:|:----------------------:|
| 1 | B |
| 2 | B |
| 3 | A |
| 4 | S |
| 5 | S |

**Filosofía:** El rango global es un indicador del desarrollo general del personaje (stats, nivel, experiencia). Un personaje de rango B ha demostrado suficiente crecimiento como para comenzar a explorar el despertar. Para frutas de tier alto (4-5), se requiere rango S porque el poder que otorgan es tan masivo que solo personajes de élite deberían acceder a él.

### 4.4 Condición Narrativa

Cada fruta puede tener una condición narrativa específica definida por el staff durante la aprobación original de la fruta. Esta condición se almacena en `effects_json.potencial_despertar`:

```json
{
    "potencial_despertar": {
        "disponible": true,
        "descripcion": "El usuario debe sobrevivir a una situación donde su cuerpo sea llevado al límite extremo, enfrentando su debilidad principal.",
        "requisito_minimo": "Nivel 6 + ESP SS + aprobación staff"
    }
}
```

**Ejemplos de condiciones narrativas:**

| Tipo de fruta | Ejemplo de condición |
|---------------|---------------------|
| Paramecia | "El usuario debe proteger a alguien importante usando su fruta en una situación de vida o muerte." |
| Zoan | "El usuario debe enfrentarse a un depredador natural de su forma animal y sobrevivir." |
| Logia | "El usuario debe ser envuelto por el elemento opuesto a su Logia y sobrevivir para demostrar control total." |
| Paramecia especial | "El usuario debe demostrar dominio absoluto de su poder en un entorno hostil sin ayuda externa." |

**Nota importante:** La condición narrativa NO es opcional. El staff puede denegar una solicitud de awakening si el enlace proporcionado no demuestra el cumplimiento de la condición.

---

## 5. Sistema de Seguimiento de Usos

### 5.1 Cómo se Registran los Usos

Cada vez que un personaje usa su carta akuma en un post, se registra en `game_post_cards`:

```sql
-- La query que cuenta los usos en el hub:
SELECT COUNT(*) as usos_totales
FROM {$prefix}game_post_cards pc
WHERE pc.character_id = {$char_id} AND pc.card_id = {$akuma_card_id}
```

El sistema cuenta de forma automática cada post donde la carta akuma fue declarada como usada. No hay intervención manual ni del jugador ni del staff para este conteo.

### 5.2 Variables del Sistema

En el hub de awakening, se calculan cuatro variables clave:

```
usos_totales  →  Contador real de usos registrados en game_post_cards
usos_base     →  Umbral base según tier (30/50/75/100)
usos_pre      →  = ceil(usos_base / 2) — Umbral para Incompleto
usos_final    →  = usos_base (o usos_base * 1.33 si ya tiene Incompleto)
```

```php
$usos_totales = (int)$akuma_card['usos_totales']; // De la DB

$usos_base = 30;
if ($tier == 3) $usos_base = 50;
if ($tier == 4) $usos_base = 75;
if ($tier >= 5) $usos_base = 100;

$usos_pre = (int)ceil($usos_base / 2);  // Mitad para Incompleto

if ($has_pre) {
    $usos_final = (int)ceil($usos_base * 1.33); // Penalización 33%
} else {
    $usos_final = $usos_base;
}
```

### 5.3 Tabla de Umbrales Completa

| Tier | `usos_base` | `usos_pre` (Incompleto) | `usos_final` (Completo) | `usos_final` con penalización |
|:----:|:-----------:|:-----------------------:|:-----------------------:|:----------------------------:|
| 1 | 30 | 15 | 30 | 40 |
| 2 | 30 | 15 | 30 | 40 |
| 3 | 50 | 25 | 50 | 67 |
| 4 | 75 | 38 | 75 | 100 |
| 5 | 100 | 50 | 100 | 133 |

### 5.4 Visualización del Progreso

En el hub, el progreso se muestra con:

```
Usos: [████████████░░░░░░░░░░] 45/100
```

Implementación técnica:

```html
<progress class="rpg-awakening-hub-progress-bar"
          value="<?= $usos_totales ?>"
          max="<?= max(1, $usos_final) ?>">
</progress>

<span>
  <?= $usos_totales ?> / <?= $usos_final ?> usos necesarios
</span>
```

### 5.5 Estados del Personaje Según Usos

```
usos_totales < usos_pre
  → Ningún botón disponible
  → Mensaje: "Necesitas X usos para solicitar Despertar Incompleto"

usos_pre <= usos_totales < usos_final  (sin Incompleto)
  → Botón "Solicitar Despertar Incompleto" habilitado
  → Botón "Solicitar Awakening Completo" bloqueado

usos_pre <= usos_totales < usos_final  (con Incompleto)
  → Botón Incompleto oculto (ya adquirido)
  → Botón Completo bloqueado (faltan usos)
  → Mensaje de penalización activa

usos_totales >= usos_final
  → Botón "Solicitar Awakening Completo" habilitado
  → Si no tiene Incompleto: también botón Incompleto disponible
```

---

## 6. Hub UI — `peticion_akuma.php`

### 6.1 Propósito

`peticion_akuma.php` funciona como **hub central** de todo el sistema de akuma. Cuando el personaje ya posee una fruta, la página detecta automáticamente la presencia de la carta akuma y muestra el **panel de awakening** sobre las opciones regulares.

### 6.2 Detección de Carta Akuma

```php
// Buscar la carta akuma del personaje
$q = $db->query("
    SELECT c.id, c.name, c.tier, cc.current_rank,
           (SELECT COUNT(*) FROM {$prefix}game_post_cards pc
            WHERE pc.character_id = {$char_id} AND pc.card_id = c.id) as usos_totales
    FROM {$prefix}game_character_cards cc
    JOIN {$prefix}game_cards c ON cc.card_id = c.id
    WHERE cc.character_id = {$char_id} AND c.card_type = 'akuma_no_mi'
    LIMIT 1
");
$akuma_card = $db->fetch_array($q);
```

### 6.3 Detección de Pre-Awakening

```php
// Verificar si ya tiene carta de Despertar Incompleto
$q2 = $db->query("
    SELECT c.id
    FROM {$prefix}game_character_cards cc
    JOIN {$prefix}game_cards c ON cc.card_id = c.id
    WHERE cc.character_id = {$char_id}
      AND (c.name LIKE '%Pre-Awakening%' OR c.name LIKE '%Despertar Incompleto%')
    LIMIT 1
");
$pre_awakening_card = ($db->num_rows($q2) > 0);
```

**¿Por qué detección por nombre y no por flag?** Porque:
- Es más simple y no requiere migración de datos.
- Los nombres de carta son controlados por el staff y predecibles.
- La carta de Pre-Awakening es independiente (con su propio `effects_json`) en lugar de modificar la carta akuma original.

### 6.4 Layout del Hub

Cuando `$is_awakening_hub = true`, se renderiza el panel de awakening:

```
┌──────────────────────────────────────────────────────────────┐
│  Solicitud Akuma no Mi                                       │
│  Elige cómo deseas solicitar tu fruta o gestiona tu Awakening│
├──────────────────────────────────────────────────────────────┤
│  ┌────────────────────────────────────────────────────────┐  │
│  │  ✦ Awakening: Gomu Gomu no Mi                         │  │
│  │  Gestiona el progreso hacia el Despertar de tu Fruta  │  │
│  │                                                        │  │
│  │  ┌─────────────────────┐  ┌─────────────────────────┐  │  │
│  │  │ Progreso de Usos    │  │ Estado del Despertar    │  │  │
│  │  │ 45 / 100 usos       │  │ ❌ Ningún Awakening     │  │  │
│  │  │ [██████████░░░░░░░] │  │ Puedes solicitar        │  │  │
│  │  │                     │  │ Incompleto a 50 usos    │  │  │
│  │  └─────────────────────┘  └─────────────────────────┘  │  │
│  │                                                        │  │
│  │  [🔒 Solicitar Despertar Incompleto]  (bloqueado)     │  │
│  │  [🔒 Solicitar Awakening Completo]    (bloqueado)     │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                              │
│  ─── Opciones Regulares (Ya posees una fruta) ───            │
│                                                              │
│  [🎲 Aleatoria]  [👆 Bajo demanda]                          │
│  (atenuadas al 70% de opacidad)                              │
└──────────────────────────────────────────────────────────────┘
```

### 6.5 Variables Expuestas al Template

| Variable | Tipo | Descripción |
|----------|------|-------------|
| `$is_awakening_hub` | bool | True si el personaje tiene carta akuma |
| `$akuma_card` | array | Datos de la carta akuma (id, name, tier, usos_totales) |
| `$usos_base` | int | Umbral base según tier (30/50/75/100) |
| `$usos_totales` | int | Usos registrados actuales |
| `$usos_pre` | int | Umbral para Incompleto (usos_base / 2) |
| `$usos_final` | int | Umbral para Completo (base o base * 1.33) |
| `$has_pre` | bool | True si ya tiene carta de Pre-Awakening |

### 6.6 Lógica de Mostrar/Ocultar Botones

```php
<?php if (!$has_pre): ?>
    <?php if ($usos_totales >= $usos_pre): ?>
        <a href="...?type=pre" class="rpg-btn rpg-btn--primary">
            Solicitar Despertar Incompleto
        </a>
    <?php else: ?>
        <button class="rpg-btn rpg-btn--disabled" disabled
                title="Necesitas <?= $usos_pre ?> usos">
            Solicitar Despertar Incompleto
        </button>
    <?php endif; ?>
<?php endif; ?>

<?php if ($usos_totales >= $usos_final): ?>
    <a href="...?type=full" class="rpg-btn rpg-btn--primary rpg-btn--awakening-full">
        Solicitar Awakening Completo
    </a>
<?php else: ?>
    <button class="rpg-btn rpg-btn--disabled" disabled
            title="Necesitas <?= $usos_final ?> usos">
        Solicitar Awakening Completo
    </button>
<?php endif; ?>
```

### 6.7 Opacidad de Opciones Regulares

Cuando el personaje ya tiene akuma, las opciones "Aleatoria" y "Bajo demanda" se atenúan al 70% de opacidad:

```html
<div class="rpg-akuma-mode-grid <?= $is_awakening_hub ? 'rpg-opacity-70' : '' ?>">
```

Esto comunica visualmente que esas opciones siguen disponibles pero ya no son la acción principal (el awakening es la prioridad).

---

## 7. Formulario de Solicitud — `peticion_awakening.php`

### 7.1 Propósito

Renderiza el formulario de solicitud de despertar, diferenciando entre Incompleto y Completo mediante el parámetro `$_GET['type']`.

### 7.2 Determinación del Tipo

```php
$type = $_GET['type'] ?? 'full';
$is_pre = ($type === 'pre');

$title_label = $is_pre
    ? 'Despertar Incompleto (Pre-Awakening)'
    : 'Awakening Completo';
$icon = $is_pre ? 'fa-bolt' : 'fa-sun';
```

### 7.3 Estructura del Formulario

```
┌──────────────────────────────────────────────────────────────┐
│  ← Volver al Hub                                            │
│  Solicitud: [Despertar Incompleto / Awakening Completo]      │
│  Rellena los datos para que el staff revise tu petición     │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  Link a la Condición Narrativa  [________________________]  │
│  Enlace directo al hilo/post donde se cumple la condición   │
│  estipulada en tu carta.                                     │
│                                                              │
│  Propuesta de Poderes / Efectos                              │
│  [______________________________________________________]   │
│  [______________________________________________________]   │
│  [______________________________________________________]   │
│  Describe cómo se manifiesta el despertar y qué mecánicas   │
│  nuevas sugieres (1-2 habilidades).                         │
│                                                              │
│  ⚠️ Al ser un Despertar Incompleto, el staff añadirá        │
│  'drawbacks' (consecuencias negativas) a la carta.          │
│  (solo visible para Incompleto)                              │
│                                                              │
│  [✉️ Enviar solicitud al Staff]                              │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### 7.4 Campos del Formulario

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `awakening_type` | hidden | Sí | `pre_awakening` o `full_awakening` |
| `link` | url | Sí | Enlace al post/hilo donde se cumple la condición narrativa |
| `propuesta_poderes` | textarea | Sí | Descripción de la manifestación y 1-2 habilidades nuevas |

### 7.5 Aviso de Drawbacks (Incompleto)

Para el Despertar Incompleto, se muestra un aviso especial:

```php
<?php if ($is_pre): ?>
    <span class="rpg-form-hint rpg-text-warning">
        <i class="fas fa-exclamation-triangle"></i>
        Al ser un Despertar Incompleto, el staff añadirá 'drawbacks'
        (consecuencias negativas) a la carta.
    </span>
<?php endif; ?>
```

### 7.6 Config JS Embebida

```php
<script>
window.PETICION_AWAKENING_CONFIG = {
    bburl: '<?= $b_url ?>',
    typeLabel: '<?= $title_label ?>'
};
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/peticion_awakening.js?v=1"></script>
```

---

## 8. JavaScript de Awakening

### 8.1 Archivo: `peticion_awakening.js`

**Propósito:** Manejar el envío AJAX del formulario de solicitud de despertar.

### 8.2 Flujo del JS

```
1. Obtener configuración (bburl, typeLabel)
2. Escuchar submit del formulario
3. Prevenir envío normal del formulario
4. Construir FormData:
   - source = 'tramites_awakening'
   - request_kind = 'awakening'
   - title = "Solicitud de [Despertar Incompleto/Awakening Completo]"
   - motivo = tipo de awakening (pre_awakening / full_awakening)
   - justificacion = propuesta de poderes
   - link = URL de condición narrativa
   - description = tipo + propuesta
5. Deshabilitar botón de envío
6. POST a admin_requests_submit.php
7. Si éxito: mostrar mensaje verde, resetear formulario
8. Si error: mostrar mensaje de error
9. Si error de conexión: alert
```

### 8.3 Código Relevante

```javascript
(function () {
  'use strict';

  var cfg = window.PETICION_AWAKENING_CONFIG || {};
  var bburl = (cfg.bburl || window.GAME_BBURL || '').replace(/\/$/, '');
  var form = document.getElementById('awakening-form');
  var msgEl = document.getElementById('awakening-msg');

  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('awakening-submit');
    var fd = new FormData();

    var reqType = document.getElementById('awakening_type').value;
    var title = 'Solicitud de ' + cfg.typeLabel;

    fd.append('source', 'tramites_awakening');
    fd.append('request_kind', 'awakening');
    fd.append('title', title);
    fd.append('motivo', reqType);

    var propuesta = document.getElementById('propuesta_poderes').value.trim();
    fd.append('justificacion', propuesta);
    fd.append('link', document.getElementById('link_condicion').value.trim());

    var desc = "Tipo: " + cfg.typeLabel + "\n\nPropuesta de Poderes/Efectos:\n" + propuesta;
    fd.append('description', desc);

    btn.disabled = true;
    msgEl.classList.add('rpg-is-hidden');

    // Envío AJAX a admin_requests_submit.php
    // ...
  });
})();
```

### 8.4 Manejo de Respuestas

```javascript
post.then(function (res) {
  btn.disabled = false;
  if (res.ok) {
    msgEl.innerHTML = '<span class="rpg-text-success">' +
      '<i class="fas fa-check-circle"></i> Petición enviada. ' +
      'El staff revisará tu solicitud de Awakening.</span>';
    msgEl.classList.remove('rpg-is-hidden');
    form.reset();
  } else {
    msgEl.innerHTML = '<span class="rpg-modal-title-icon">' +
      '<i class="fas fa-exclamation-circle"></i> ' +
      (res.error ? res.error.message : 'Error al enviar') + '</span>';
    msgEl.classList.remove('rpg-is-hidden');
  }
}).catch(function () {
  btn.disabled = false;
  alert('Error de conexión');
});
```

---

## 9. Awakening Completo (Full)

### 9.1 Definición

El **Awakening Completo** es la forma máxima de despertar de una akuma. El personaje obtiene el poder total de su fruta despertada sin restricciones ni drawbacks.

### 9.2 Requisitos

| Requisito | Detalle |
|-----------|---------|
| Usos mínimos | `usos_totales >= usos_final` (100% del base, o 133% si tiene Incompleto) |
| Rango global | Según tier de fruta (B para tier 1-2, S para tier 4-5) |
| Condición narrativa | Demostrada mediante enlace en el formulario |
| Sin Incompleto (opcional) | Si se salta el Incompleto, no hay penalización. Si tiene Incompleto, el umbral sube 33%. |

### 9.3 Proceso de Solicitud

```
1. Jugador alcanza usos_finales
2. Clica "Solicitar Awakening Completo"
3. Es redirigido a peticion_awakening.php?type=full
4. Rellena: link a condición + propuesta de poderes
5. Envía → Se crea petición en game_admin_requests
6. Staff evalúa y decide
7. Si aprueba:
   a. Staff modifica la carta akuma original o crea versión mejorada
   b. Actualiza effects_json.potencial_despertar.disponible = true
   c. Añade nuevos poderes al effects_json
   d. Notifica al jugador
```

### 9.4 Resultado Final

Tras la aprobación:

- El personaje tiene una carta de awakening (rango SS) en su inventario.
- La carta akuma original sigue existiendo con sus poderes base.
- Los poderes despertados están disponibles para su uso.
- No hay drawbacks ni restricciones mecánicas.
- El hub de awakening muestra el estado como completado (oculta botones de solicitud).

---

## 10. Awakening Incompleto (Pre-Awakening)

### 10.1 Definición

El **Despertar Incompleto** (Pre-Awakening) es una versión adelantada del despertar que otorga poder parcial pero con **consecuencias negativas** (drawbacks). Permite al jugador acceder a una parte del poder despertado antes de alcanzar el umbral completo.

### 10.2 Requisitos

| Requisito | Detalle |
|-----------|---------|
| Usos mínimos | `usos_totales >= usos_pre` (50% del base) |
| Rango global | Mismo que para Completo |
| Condición narrativa | Demostrada mediante enlace |

### 10.3 La Penalización del 33%

Si el jugador obtiene el Despertar Incompleto, el umbral para el Completo aumenta un 33%:

```php
if ($has_pre) {
    $usos_final = (int)ceil($usos_base * 1.33); // 33% penalty
}
```

**Ejemplo:**
- Tier 3, `usos_base = 50`
- `usos_pre = 25` (alcanza Incompleto)
- `usos_final` sin Incompleto: 50
- `usos_final` con Incompleto: `ceil(50 * 1.33) = 67`

**Filosofía de la penalización:**

| Aspecto | Explicación |
|---------|-------------|
| Coste de oportunidad | Poder ahora = más esfuerzo después |
| Decisión significativa | El jugador elige entre poder inmediato con precio vs paciencia |
| Tiempo para el staff | El staff puede evaluar cómo el Incompleto afecta el balance antes de que llegue el Completo |
| Progresión justa | Quien skip el Incompleto no es penalizado; quien lo toma paga el precio |

### 10.4 Drawbacks Obligatorios

El staff **debe** añadir consecuencias negativas mecánicas a la carta de Pre-Awakening. Algunos ejemplos:

| Drawback | Descripción |
|----------|-------------|
| Pérdida de control | El poder se activa involuntariamente en momentos de estrés |
| Daño colateral | El entorno o personas cercanas sufren daño al usar el poder |
| Agotamiento extremo | Tras usar el poder despertado, el personaje queda incapaz de moverse por X turnos |
| Transformación incompleta | La forma despertada deja expuesta una debilidad (ej: un punto débil visible) |
| Retroceso de stat | Mientras dura el efecto, un stat se reduce temporalmente |
| Coste de PE duplicado | Usar los poderes despertados cuesta el doble de PE |
| Autodaño | El poder daña al usuario además del objetivo |

```json
{
    "card_type": "pre_awakening",
    "akuma_original_id": 42,
    "nombre": "Pre-Awakening: Gomu Gomu no Mi",
    "poderes_parciales": [
        "Gomu Gomu no Kaen: versión reducida, 50% del poder completo."
    ],
    "drawbacks": [
        {
            "nombre": "Agotamiento extremo",
            "descripcion": "Tras usar Gomu Gomu no Kaen, el personaje queda con 0 PE por 2 turnos.",
            "severidad": "alta"
        },
        {
            "nombre": "Daño colateral",
            "descripcion": "El calor generado por la fricción daña objetos cercanos (aliados incluidos).",
            "severidad": "media"
        }
    ]
}
```

### 10.5 Carta Separada

El Pre-Awakening se crea como una **carta independiente** (no modifica la akuma original):

```sql
INSERT INTO game_cards (name, card_type, rank, effects_json, ...)
VALUES ('Pre-Awakening: Gomu Gomu no Mi', 'awakening', 'SS', '{...}', ...);
```

Esto permite:
- Que la carta de Pre-Awakening tenga su propio `effects_json`.
- Que se pueda eliminar o modificar sin afectar la carta akuma original.
- Que el sistema de detección funcione por nombre (LIKE '%Pre-Awakening%').

### 10.6 Comparativa: Incompleto vs Completo

| Aspecto | Incompleto (Pre-Awakening) | Completo (Full Awakening) |
|---------|---------------------------|--------------------------|
| Umbral de usos | 50% del base (`usos_pre`) | 100% del base (`usos_final`) |
| Penalización | +33% en umbral completo | Sin penalización |
| Drawbacks | **Obligatorios** | Sin drawbacks |
| Carta resultante | Carta separada "Pre-Awakening" | Carta mejorada o nueva "Awakening" |
| Beneficios | Poder parcial desbloqueado | Poder completo desbloqueado |
| Riesgo para el staff | Debe diseñar drawbacks | Solo debe evaluar la propuesta |
| Filosofía | "Poder a medias, con precio" | "Poder total, sin restricciones" |

---

## 11. Diferencias por Clase de Fruta

### 11.1 Manifestación Narrativa del Awakening

Cada clase de akuma manifiesta el despertar de forma distinta, tanto narrativa como mecánicamente:

| Clase | Manifestación Narrativa del Awakening |
|-------|--------------------------------------|
| `paramecia` | El poder trasciende al cuerpo y se extiende al entorno, pudiendo modificar objetos y escenarios cercanos. |
| `zoan` | Transformación estabilizada, una nueva forma definitiva, con control absoluto, fuerza colosal y mayor stamina. |
| `logia` | Control total y puro del elemento, capacidad de transmitir o imbuir el elemento a seres ajenos permanentemente. |

### 11.2 Paramecia — Entorno Despertado

**Característica distintiva:** El poder del usuario deja de estar confinado a su cuerpo y se extiende al entorno.

**Ejemplos de manifestación:**
- Una paramecia de elasticidad (Gomu): el entorno se vuelve gomoso, el usuario puede rebotar contra edificios, estirar el suelo, etc.
- Una paramecia de multiplicación: los objetos cercanos se multiplican, no solo las partes del cuerpo.
- Una paramecia de sustancias: el usuario puede generar su sustancia a partir del entorno, no solo de su cuerpo.

**Implicaciones mecánicas:**
- Alcance de habilidades aumentado (de personal/medio a largo/área).
- Capacidad de modificar el campo de batalla (terreno, objetos, estructuras).
- El poder puede persistir en el entorno incluso después de que el usuario deje de concentrarse.

### 11.3 Zoan — Forma Definitiva

**Característica distintiva:** El usuario alcanza una forma definitiva estable, más poderosa que la forma híbrida o animal completa.

**Ejemplos de manifestación:**
- Una Zoan de lobo: forma "Lobo Demoníaco", más grande, más rápido, con resistencia sobrehumana.
- Una Zoan de dragón (mítico): forma "Dragón Emperador", tamaño colosal, control elemental absoluto.
- Una Zoan antiguo (tigre dientes de sable): forma "Tigre Primordial", mandíbula capaz de destrozar acero.

**Implicaciones mecánicas:**
- Bonus de stats significativamente mayores que las formas previas.
- La transformación es estable (no se desactiva por daño moderado).
- Mayor stamina en forma despertada (menor coste de PE por turno).
- Posibilidad de ataques exclusivos de la forma despertada.

### 11.4 Logia — Control Elemental Total

**Característica distintiva:** El usuario logra control absoluto de su elemento, pudiendo transmitirlo a otros seres u objetos permanentemente.

**Ejemplos de manifestación:**
- Una Logia de fuego: puede imbuir llamas permanentes en objetos, crear seres de fuego autónomos, controlar el fuego de otros.
- Una Logia de hielo: puede congelar el océano permanentemente, crear golems de hielo que actúan independientemente.
- Una Logia de luz: puede almacenar luz en objetos, crear campos de luz sólida, transmitir propiedades lumínicas a otros.

**Implicaciones mecánicas:**
- El usuario puede imbuir su elemento en objetos/aliados (permanente o temporal).
- Creación de seres elementales autónomos.
- Control del elemento incluso cuando no está en contacto directo.
- Mayor resistencia a la debilidad elemental (aunque no inmunidad total).

### 11.5 Tabla Comparativa de Awakening por Clase

| Dimensión | Paramecia | Zoan | Logia |
|-----------|-----------|------|-------|
| ¿Qué se despierta? | El entorno | El cuerpo | El elemento |
| Alcance del poder | Área (entorno) | Personal (transformación) | Área + imbuir |
| Nuevas capacidades | Modificar escenario, persistencia | Forma definitiva, stats máximos | Creación elemental, imbuir |
| Drawback típico (Incompleto) | El entorno modificado daña aliados | Transformación inestable, dolor | Pérdida parcial de intangibilidad |
| Estilo de juego | Control de zona | Tanque/DPS cuerpo a cuerpo | Control elemental/AP |

### 11.6 Ejemplos de Effects JSON por Clase

**Paramecia Awakening:**
```json
{
    "tipo_manifestacion": "entorno",
    "efectos_entorno": [
        "El área de 20 metros alrededor del usuario adquiere las propiedades de la fruta.",
        "El usuario puede manipular objetos dentro del área como extensiones de su cuerpo."
    ],
    "nuevas_habilidades": [
        "Crear barreras con el material del entorno.",
        "Proyectiles que rebotan en superficies despertadas."
    ]
}
```

**Zoan Awakening:**
```json
{
    "tipo_manifestacion": "transformacion",
    "forma_definitiva": {
        "nombre": "Forma Despertada",
        "bonus_stats": {"fue": 4, "res": 4, "agi": 2},
        "coste_pe_por_turno": 5,
        "requisito": "Voluntad férrea + condición narrativa"
    },
    "habilidades_exclusivas": [
        "Ataque colosal (daño x3, alcance largo)",
        "Regeneración acelerada en forma despertada"
    ]
}
```

**Logia Awakening:**
```json
{
    "tipo_manifestacion": "elemental",
    "control_total": true,
    "imbuir": {
        "objetos": true,
        "seres": true,
        "permanencia": "depende de la voluntad del usuario"
    },
    "habilidades_exclusivas": [
        "Crear elemental autónomo (1 por turno, 30 PE)",
        "Campo elemental (el entorno se convierte en el elemento)"
    ]
}
```

---

## 12. Evaluación del Staff

### 12.1 Criterios de Evaluación

Cuando un staff recibe una petición de awakening (source: `tramites_awakening`, request_kind: `awakening`), debe evaluar:

#### 12.1.1 Verificar el Cumplimiento de Usos

1. Abrir el perfil del personaje → verificar `usos_totales` en `game_post_cards`.
2. Confirmar que el jugador ha alcanzado el umbral requerido.
3. Si es Incompleto: `usos_totales >= usos_pre`.
4. Si es Completo: `usos_totales >= usos_final` (considerando penalización si aplica).

#### 12.1.2 Verificar la Condición Narrativa

1. Revisar el enlace proporcionado.
2. Confirmar que el post/hilo demuestra el cumplimiento de la condición.
3. La condición debe haber sido roleada, no solo mencionada.
4. Si la condición no se cumple claramente → denegar con nota explicativa.

#### 12.1.3 Evaluar la Propuesta de Poderes

**Lista de verificación:**
- [ ] ¿La propuesta es coherente con la fruta original?
- [ ] ¿Los poderes propuestos son balanceados para el contexto del foro?
- [ ] ¿Las 1-2 habilidades nuevas son originales (no copiadas de otro personaje)?
- [ ] ¿El alcance y daño propuestos son razonables?
- [ ] ¿La manifestación narrativa es interesante y bien descrita?

**Criterios de balance:**
| Factor | Bien | Mal |
|--------|------|-----|
| Daño | Comparable a técnicas de tier similar | "Daño infinito", "muerte instantánea" |
| Alcance | Acorde al tipo de fruta | "Alcance global" sin justificación |
| Duración | Limitada (turnos) o con coste | "Permanente" sin coste |
| Utilidad | Situacional o con preparación | "Resuelve cualquier problema" |

#### 12.1.4 Para Incompleto: Diseñar Drawbacks

El staff debe crear drawbacks que:
- Sean significativos (no meramente cosméticos).
- Equilibren el poder adelantado.
- Sean divertidos de rolear (no solo molestos).
- Tengan sentido con la fruta y el personaje.

**Drawbacks recomendados por severidad:**

| Severidad | Ejemplo | Cuándo usarlo |
|-----------|---------|---------------|
| Baja | Coste de PE +50% | El poder no es muy superior a lo normal |
| Media | Daño colateral a aliados | El poder es significativamente superior |
| Alta | El usuario queda inconsciente tras usarlo | El poder es extremadamente superior |
| Crítica | Pérdida temporal de la fruta | El poder roza lo SS sin serlo |

### 12.2 Resolución de la Petición

**Si se APRUEBA:**

1. **Para Incompleto:**
   - Crear carta "Pre-Awakening / Despertar Incompleto" con:
     - Beneficios parciales aprobados.
     - Drawbacks diseñados por el staff.
   - La penalización de +33% se aplica automáticamente en el hub (no requiere acción del staff).

2. **Para Completo:**
   - Modificar la carta akuma original o crear versión mejorada.
   - Actualizar `effects_json.potencial_despertar.disponible = true`.
   - Añadir los nuevos poderes/efectos al `effects_json`.

**Si se DENIEGA:**

1. Enviar DM al jugador explicando el motivo:
   - "No se cumple la condición narrativa — el enlace no muestra la situación requerida."
   - "La propuesta de poderes no es balanceada — [explicación]."
   - "Faltan usos registrados — actualmente tienes X, necesitas Y."

### 12.3 Nota del Staff

```php
// El DM se envía automáticamente si staffNota no está vacío
if ($staffNota !== '' && $playerUid > 0 && $staffCharId > 0 && $characterId > 0) {
    $dmId = DirectMessageService::send(
        $staffCharId,
        $characterId,
        "Petición de Awakening: {$req['title']}",
        "Tu solicitud ha sido {$label}.\n\n{$req['title']}\n\nRespuesta del Staff:\n{$staffNota}"
    );
}
```

---

## 13. Flujo Completo: Solicitud → Resolución

### 13.1 Diagrama de Secuencia Detallado

```
JUGADOR                              SISTEMA                            STAFF
────────────────────────────────────────────────────────────────────────────
1. Rolea con su akuma
   (usa la carta en posts)
                                       2. game_post_cards registra
                                          cada uso automáticamente
                                       ... (el tiempo pasa) ...

3. Abre peticion_akuma.php
                                       4. Detecta carta akuma_no_mi
                                       5. Calcula: usos_totales=45,
                                          usos_base=50, usos_pre=25,
                                          usos_final=50
                                       6. Muestra hub con:
                                          - Barra de progreso (45/50)
                                          - "Ningún Awakening activo"
                                          - "Puedes solicitar Incompleto
                                             a 25 usos" (✅ cumplido)
                                          - Botón Incompleto: HABILITADO
                                          - Botón Completo: BLOQUEADO
7. Clica "Solicitar Despertar Incompleto"
                                       8. Redirige a:
                                          peticion_awakening.php?type=pre
9. Rellena formulario:
   - Link a condición narrativa
   - Propuesta de poderes
   - Lee aviso de drawbacks
10. Clica "Enviar solicitud al Staff"
                                       11. JS construye FormData
                                       12. POST a admin_requests_submit.php
                                       13. AdminRequestService::create()
                                           - source: 'tramites_awakening'
                                           - request_kind: 'awakening'
                                           - title: "Solicitud de Despertar
                                             Incompleto (Pre-Awakening)"
                                           - payload: {tipo, propuesta, link}
                                       14. notifyStaffPending()
                                       15. Muestra mensaje de éxito
                                                                           16. Staff recibe notificación
                                                                           17. Abre panel de peticiones
                                                                           18. Evalúa:
                                                                               - ¿Usos suficientes? (45 ≥ 25 ✅)
                                                                               - ¿Condición cumplida? (revisa link)
                                                                               - ¿Propuesta balanceada?
                                                                           19. Decide: APROBAR
                                                                           20. Crea carta "Pre-Awakening..."
                                                                               con drawbacks
                                                                           21. AdminRequestService::resolve()
                                                                               - status = 'aprobada'
                                                                               - Envía DM al jugador
                                       22. El jugador recibe DM
                                       23. La próxima vez que abre
                                           peticion_akuma.php:
                                           - has_pre = true
                                           - usos_final = ceil(50*1.33)=67
                                           - "Despertar Incompleto (Adquirido)"
                                           - Penalización activa
                                           - Barra: 45/67
                                           - Botón Incompleto: OCULTO
                                           - Botón Completo: BLOQUEADO
                                       ... (más usos) ...
24. Alcanza 67 usos
25. Abre peticion_akuma.php
                                           - usos_totales = 67
                                           - usos_final = 67
                                           - Botón Completo: HABILITADO
26. Clica "Solicitar Awakening Completo"
                                       27. peticion_awakening.php?type=full
28. Rellena formulario
29. Envía
                                                                           30. Staff evalúa
                                                                           31. Aprueba
                                                                           32. Crea/Actualiza carta
                                                                               awakening completo
33. Jugador obtiene Awakening Completo
```

### 13.2 Petición en DB

Cuando se crea una petición de awakening, la entrada en `game_admin_requests` se ve así:

```json
{
    "id": 99,
    "user_id": 1,
    "character_id": 5,
    "source": "tramites_awakening",
    "request_kind": "awakening",
    "title": "Solicitud de Despertar Incompleto (Pre-Awakening)",
    "description": "Tipo: Despertar Incompleto (Pre-Awakening)\n\nPropuesta de Poderes/Efectos:\n...",
    "link": "https://foro/hilo/42/post-1234",
    "payload_json": "{\"tipo\":\"pre_awakening\",\"propuesta\":\"...\",\"link\":\"...\"}",
    "akuma_fruit_id": 1,
    "status": "pendiente",
    "created_at": "2026-06-12 10:30:00"
}
```

---

## 14. Filosofía de Diseño

### 14.1 ¿Por qué el Awakening es una carta separada?

| Razón | Explicación |
|-------|-------------|
| Modularidad | La carta original y la despertada pueden modificarse independientemente |
| Historial | El personaje conserva su fruta original incluso si el awakening se pierde |
| Claridad visual | En el inventario se ve claramente qué está despertado y qué no |
| Flexibilidad de staff | Pueden crear la carta de awakening sin modificar la original |

### 14.2 ¿Por qué la distinción Incompleto/Completo?

| Razón | Explicación |
|-------|-------------|
| Premio progresivo | El Incompleto da una recompensa a mitad de camino, manteniendo la motivación |
| Decisión estratégica | El jugador elige entre poder ahora (con precio) o poder después (sin precio) |
| Curva de aprendizaje | El staff puede ver cómo funciona el Incompleto antes de aprobar el Completo |
| Narrativa | El despertar es un proceso, no un evento binario |

### 14.3 ¿Por qué el sistema está basado en usos?

| Razón | Explicación |
|-------|-------------|
| Objetividad | Los usos son un registro automático e inmutable en la DB |
| Compromiso | Solo personajes que han roleado extensamente con su akuma pueden despertar |
| Incentivo | Para llegar al despertar, el jugador debe participar activamente |
| No manipulable | El staff no puede "dar" usos; el jugador no puede "comprarlos" |

### 14.4 ¿Por qué la penalización del 33%?

| Razón | Explicación |
|-------|-------------|
| Coste real | El jugador paga un precio tangible por obtener poder antes de tiempo |
| No es castigo | Es un tradeoff: poder inmediato a cambio de más esfuerzo después |
| Proporcional | 33% es significativo sin ser prohibitivo (ej: 50 → 67, no 50 → 100) |
| Fomenta la decisión | El jugador siente el peso de su elección |

### 14.5 ¿Por qué SS es el rango fijo?

| Razón | Explicación |
|-------|-------------|
| El despertar trasciende el tier | Incluso una fruta tier 1 despierta a SS porque su poder despertado es cualitativamente superior |
| Identidad visual | SS comunica inmediatamente "esto es especial" |
| Balance | El rango determina costes de PE y otros factores mecánicos; unificar en SS simplifica |

### 14.6 Principios de Diseño del Sistema

1. **El despertar se gana, no se compra.** No hay atajos: los usos son el único camino.
2. **La carta original se conserva.** El awakening es un añadido, no un reemplazo.
3. **El staff es guardián del balance.** Aunque el sistema automatiza el conteo, la decisión final es del staff.
4. **Los drawbacks son obligatorios en Incompleto.** Sin ellos, no hay razón para no tomar el Incompleto.
5. **La penalización es automática.** El jugador no puede evitar la penalización una vez obtenido el Incompleto.
6. **El despertar es narrativo y mecánico.** No basta con cumplir los números; la condición narrativa es requisito indispensable.

---

## 15. Consejos para Jugadores

### 15.1 Acumular Usos Eficientemente

- **Usa tu akuma en cada post posible.** Cada post donde se registre la carta cuenta como un uso. No la guardes "para momentos especiales".
- **Participa en combates.** Los combates suelen requerir múltiples usos de cartas por post, acelerando tu progreso.
- **Documenta usos fuera de combate.** Si usas tu akuma para algo narrativo (abrir una puerta, cruzar un precipicio, construir algo), asegúrate de declararlo en el post.
- **No dependas solo de técnicas.** La carta akuma base también cuenta usos.

### 15.2 Decidir entre Incompleto y Completo

| Si tú... | Elige... |
|----------|----------|
| Quieres poder ahora y te da igual el esfuerzo extra | **Incompleto** |
| Prefieres esperar y no tener drawbacks | **Completo directo** |
| Estás en un arco importante y necesitas el poder YA | **Incompleto** |
| Tu personaje tiene paciencia y disciplina | **Completo** |
| Ya tienes 80 usos de 100, mejor espera | **Completo** (faltan 20 usos) |
| Tienes 30 usos de 100, quieres probar el poder | **Incompleto** (solo necesitas 15 usos más) |

**Recomendación:** Si tu personaje es impulsivo o está en una situación desesperada, el Incompleto tiene sentido narrativo. Si tu personaje es metódico o paciente, esperar al Completo refuerza su personalidad.

### 15.3 Preparar tu Propuesta de Despertar

Mientras acumulas usos, piensa en tu propuesta:

1. **Investiga cómo se manifiesta el despertar de tu clase de fruta** (paramecia → entorno, zoan → forma, logia → elemento).
2. **Define 1-2 habilidades nuevas** que tengan sentido con tu fruta y tu estilo de juego.
3. **Piensa en la manifestación narrativa:** ¿cómo se ve? ¿cómo se siente? ¿qué cambios físicos ocurren?
4. **Para Incompleto: ¿qué drawback aceptarías?** Anticípate y propón uno tú mismo — el staff apreciará la autoconciencia.
5. **Escribe la propuesta con claridad y detalle.** Una propuesta bien redactada tiene más probabilidades de ser aprobada.

### 15.4 Errores Comunes

| Error | Consecuencia |
|-------|-------------|
| No usar la akuma en posts por miedo a "gastarla" | Nunca alcanzarás el despertar |
| Tomar Incompleto sin pensar en la penalización | 33% extra de usos puede ser frustrante |
| Propuesta vaga ("quiero ser más fuerte") | El staff denegará por falta de concreción |
| Ignorar la condición narrativa | La solicitud será denegada aunque tengas los usos |
| No enlazar el post correcto | El staff no puede verificar la condición |

---

## 16. Consejos para Staff

### 16.1 Evaluar Solicitudes de Awakening

**Lo primero: verifica los usos reales.**

```sql
SELECT COUNT(*) as usos
FROM mybb_game_post_cards pc
JOIN mybb_game_character_cards cc ON pc.character_id = cc.character_id AND pc.card_id = cc.card_id
JOIN mybb_game_cards c ON cc.card_id = c.id
WHERE cc.character_id = {$charId} AND c.card_type = 'akuma_no_mi';
```

No confíes solo en lo que dice el jugador. Verifica en la DB.

**Lo segundo: lee el hilo/post de la condición narrativa.**

- ¿Realmente ocurrió la situación?
- ¿El personaje estaba en peligro real?
- ¿La condición se cumplió de forma creíble?

**Lo tercero: evalúa la propuesta con ojos críticos.**

- ¿Es balanceada? Si un poder puede ganar cualquier combate por sí solo, es demasiado fuerte.
- ¿Es original? Si es una copia exacta de otro personaje, no es un despertar único.
- ¿Tiene limitaciones? Todo poder debe tener un coste, una condición o una debilidad.

### 16.2 Diseñar Drawbacks para Incompleto

**Principios de diseño de drawbacks:**

1. **Deben ser proporcionales al poder otorgado.** Un drawback pequeño para un poder pequeño; un drawback severo para un poder masivo.
2. **Deben ser roleables.** "El personaje pierde 10 PV" es mecánico. "El personaje siente un dolor insoportable que lo deja sin aliento por un turno" es roleable.
3. **Deben tener sentido con la fruta.** Una Zoan no debería tener un drawback de "pérdida de control del entorno".
4. **No deben hacer el poder inusable.** Si el drawback es tan malo que el jugador nunca usa el poder, el Incompleto no tiene propósito.

**Drawbacks recomendados:**

| Clase de fruta | Drawbacks coherentes |
|----------------|---------------------|
| Paramecia | Daño colateral al entorno, pérdida de control sobre qué objetos se modifican |
| Zoan | Transformación dolorosa, pérdida de consciencia parcial, forma inestable que se desactiva con daño |
| Logia | El elemento imbuido daña al usuario también, pérdida temporal de intangibilidad al usar el poder |

### 16.3 Balancear Awakenings por Tier de Fruta Original

| Tier original | El awakening debería... |
|:-------------:|------------------------|
| 1 | Ser transformador: el personaje pasa de tener un poder menor a algo realmente útil |
| 2 | Ser potenciador: duplica o triplica la efectividad de la fruta |
| 3 | Ser amplificador: extiende alcance, duración y daño significativamente |
| 4 | Ser refinador: el poder ya es grande; el awakening lo hace más versátil y controlable |
| 5 | Ser culminante: el poder ya es masivo; el awakening elimina limitaciones y añade 1-2 habilidades de élite |

### 16.4 Cuándo Denegar

| Motivo | Explicación |
|--------|-------------|
| Usos insuficientes | El jugador no ha alcanzado el umbral (verificar en DB) |
| Condición no cumplida | El enlace no demuestra la condición o no es suficientemente clara |
| Propuesta desbalanceada | Los poderes sugeridos rompen el equilibrio del foro |
| Propuesta vaga | "Quiero ser más fuerte" sin detalle de cómo |
| Mala fe | El jugador ha inflado usos artificialmente (posteo vacío solo para contar) |
| Tiempo insuficiente | El personaje lleva muy poco tiempo con la fruta (evaluar caso por caso) |

### 16.5 Fomentar Despertar Significativos

- **No apresures los despertares.** 100 usos son ~50 posts de combate. Es un compromiso significativo. Celebra que el jugador haya llegado.
- **Recompensa la paciencia.** Un jugador que espera al Completo sin tomar el Incompleto debería sentir que su paciencia valió la pena (poder sin drawbacks, sin penalización).
- **Sé creativo con los poderes.** Si el jugador propone algo aburrido ("más daño"), sugiere alternativas más interesantes.
- **Involucra al jugador.** Pregúntale "¿qué te gustaría que pasara narrativamente cuando despiertes?" El despertar es de él, no tuyo.

---

## 17. Referencia Rápida de Archivos

### 17.1 PHP — Páginas Públicas

| Archivo | Propósito | Líneas clave |
|---------|-----------|-------------|
| `game/public/peticion_akuma.php` | Hub de akuma + panel de awakening | Líneas 56-81 (cálculo progreso), 94-139 (Hub HTML) |
| `game/public/peticion_awakening.php` | Formulario de solicitud de despertar | Todo el archivo (69 líneas) |

### 17.2 PHP — AJAX

| Archivo | Propósito |
|---------|-----------|
| `game/ajax/admin_requests_submit.php` | Endpoint que recibe el POST del formulario de awakening |

### 17.3 JavaScript

| Archivo | Propósito |
|---------|-----------|
| `jscripts/game/peticion_awakening.js` | Envío AJAX del formulario de despertar (70 líneas) |

### 17.4 Servicios

| Archivo | Propósito |
|---------|-----------|
| `game/src/Application/Services/AdminRequestService.php` | Creación y resolución de peticiones de awakening |

### 17.5 Base de Datos

| Tabla | Propósito en Awakening |
|-------|----------------------|
| `game_cards` | Almacena las cartas de awakening (`card_type = 'awakening'` o `card_type = 'pre_awakening'`) |
| `game_character_cards` | Asocia la carta de awakening al personaje |
| `game_post_cards` | Registra usos de la carta akuma (base para el conteo) |
| `game_admin_requests` | Peticiones de awakening (source: `tramites_awakening`) |

### 17.6 SQL — Consultas Útiles

**Verificar usos de un personaje:**
```sql
SELECT COUNT(*) as usos_totales
FROM mybb_game_post_cards pc
JOIN mybb_game_character_cards cc ON pc.character_id = cc.character_id AND pc.card_id = cc.card_id
WHERE cc.character_id = {charId} AND cc.card_id = {akumaCardId};
```

**Verificar si tiene Pre-Awakening:**
```sql
SELECT c.id
FROM mybb_game_character_cards cc
JOIN mybb_game_cards c ON cc.card_id = c.id
WHERE cc.character_id = {charId}
  AND (c.name LIKE '%Pre-Awakening%' OR c.name LIKE '%Despertar Incompleto%');
```

**Listar peticiones de awakening pendientes:**
```sql
SELECT r.*, p.name as character_name
FROM mybb_game_admin_requests r
JOIN mybb_game_personajes p ON r.character_id = p.id
WHERE r.source = 'tramites_awakening'
  AND r.request_kind = 'awakening'
  AND r.status = 'pendiente'
ORDER BY r.created_at DESC;
```

---

> **Fin de la guía 37 — Sistema de Despertar (Awakening)**
>
> Esta guía documenta exhaustivamente el subsistema de Awakening de Akuma no Mi: qué es, cómo se solicita, requisitos, diferencias por clase de fruta, la card de awakening, despertar incompleto vs completo, evaluación de staff y filosofía de diseño. Cada archivo, función y flujo está explicado con su propósito, implementación y lógica subyacente.
>
> **Referencia cruzada:** `Guias/sistemas/11-akuma.md` · Sección 11 (arquitectura general de akuma)
> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 29
