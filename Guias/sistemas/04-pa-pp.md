# 4. Puntos de Aventura (PA) y Puntos de Progresión (PP)

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 4
> **Propósito:** Documentar exhaustivamente el subsistema de Puntos de Aventura (PA) — el recurso táctico gastado post a post — y su relación con los Puntos de Progresión (PP, cubiertos en profundidad en `03-rangos.md`). PA y PP son las dos caras de la moneda económica del RPG: PA para acciones inmediatas, PP para mejoras permanentes.
> **Dependencias:** `03-rangos.md` (progresión detallada), `05-cards.md` (gasto de PA en cartas), `10-haki.md` (costes de PA en Haki avanzado).

---

## ÍNDICE

1. [PA — Puntos de Aventura (FULL depth)](#1-pa--puntos-de-aventura)
    - 1.1 [Definición y Filosofía](#11-definición-y-filosofía)
    - 1.2 [Cálculo del PA Máximo](#12-cálculo-del-pa-máximo)
    - 1.3 [Declaración en Cada Post (`pa_declared`)](#13-declaración-en-cada-post-pa_declared)
    - 1.4 [Validación por Staff (NO automática)](#14-validación-por-staff-no-automática)
    - 1.5 [¿Qué Afecta el PA?](#15-qué-afecta-el-pa)
    - 1.6 [Regeneración y Persistencia del PA](#16-regeneración-y-persistencia-del-pa)
    - 1.7 [Relación con PE (Puntos de Energía)](#17-relación-con-pe-puntos-de-energía)
    - 1.8 [Database Schema — Tabla `game_post_characters`](#18-database-schema)
    - 1.9 [PHP Implementation — `thread_pj_state.php`](#19-php-implementation-threadpjstate)
    - 1.10 [PHP Implementation — `pa_declared` en Post Creation](#110-php-implementation-pa_declared-en-post-creation)
    - 1.11 [Staff Review Workflow para PA](#111-staff-review-workflow-para-pa)
    - 1.12 [Modificadores de PA por Raza y Linaje](#112-modificadores-de-pa-por-raza-y-linaje)
2. [PP — Puntos de Progresión (Resumen + Cross-Reference)](#2-pp--puntos-de-progresión)
    - 2.1 [Definición Breve](#21-definición-breve)
    - 2.2 [¿Qué Compra el PP?](#22-qué-compra-el-pp)
    - 2.3 [Estructura en `data_json`](#23-estructura-en-data_json)
    - 2.4 [Cross-Reference a 03-rangos.md](#24-cross-reference-a-03-rangosmd)
    - 2.5 [Plugin de Ganancia por Posts](#25-plugin-de-ganancia-por-posts)
3. [Interacción PA + PP](#3-interacción-pa--pp)
    - 3.1 [Cómo Funcionan Juntos](#31-cómo-funcionan-juntos)
    - 3.2 [Tabla Comparativa PA vs PP](#32-tabla-comparativa-pa-vs-pp)
    - 3.3 [La Frontera Entre lo Táctico y lo Permanente](#33-la-frontera-entre-lo-táctico-y-lo-permanente)
4. [Filosofía de Diseño](#4-filosofía-de-diseño)
    - 4.1 [¿Por Qué PA como Recurso Táctico?](#41-por-qué-pa-como-recurso-táctico)
    - 4.2 [¿Por Qué PP es Permanente (Sunk Cost)?](#42-por-qué-pp-es-permanente-sunk-cost)
    - 4.3 [¿Por Qué el Staff Valida PA (No Automatizado)?](#43-por-qué-el-staff-valida-pa-no-automatizado)
    - 4.4 [¿Por Qué un Sistema de Doble Moneda?](#44-por-qué-un-sistema-de-doble-moneda)
5. [Consejos para Jugadores](#5-consejos-para-jugadores)
    - 5.1 [Cómo Presupuestar PA por Post](#51-cómo-presupuestar-pa-por-post)
    - 5.2 [Qué Priorizar con PP](#52-qué-priorizar-con-pp)
    - 5.3 [Errores Comunes](#53-errores-comunes)
6. [Consejos para Staff](#6-consejos-para-staff)
    - 6.1 [Cómo Revisar Declaraciones de PA](#61-cómo-revisar-declaraciones-de-pa)
    - 6.2 [Balanceando Costes de PA para Encuentros](#62-balanceando-costes-de-pa-para-encuentros)
    - 6.3 [Aprobando Gastos de PP](#63-aprobando-gastos-de-pp)
7. [Referencia Rápida](#7-referencia-rápida)

---

## 1. PA — Puntos de Aventura

### 1.1 Definición y Filosofía

Los **Puntos de Aventura (PA)** son la "energía táctica" del personaje. Representan la capacidad de un personaje para realizar acciones significativas dentro de un post: jugar cartas, ejecutar movimientos especiales, desplegar Haki avanzado o coordinar acciones de equipo.

**Filosofía rectora:** PA es un recurso post-a-post que fuerza al jugador a priorizar. No puedes hacer todo en un solo post; debes elegir qué acciones son más importantes. Esto crea tensión táctica, incentiva el trabajo en equipo, y evita que un solo post resuelva un combate completo.

**Características fundamentales:**
- **No se acumulan entre hilos:** Son por-hilo y por-post. Cada hilo tiene su propio estado de PA (aunque hoy no se persista el PA actual en DB — solo el máximo y lo declarado).
- **Se declaran, no se descuentan automáticamente:** El jugador escribe en su post cuántos PA gasta. El staff verifica durante la revisión del hilo que el gasto sea legal.
- **Tope variable:** Depende del AGI del personaje, más modificadores raciales y de linaje.
- **Se regeneran por post:** Al menos conceptualmente, el PA se "refresca" cada post (el personaje recupera su capacidad de acción al escribir un nuevo post). No hay un pool persistente de PA que se arrastre — el jugador declara cuánto gasta de su máximo en cada post.

### 1.2 Cálculo del PA Máximo

La fórmula del PA máximo está implementada en `game/ajax/thread_pj_state.php`:

```php
$mod_raza = (int)($data['modificadores_pa_raza'] ?? 0);
$mod_linaje = (int)($data['linaje']['modificadores_pa'] ?? 0);
$max_pa = 10 + intdiv($values['agi'], 2) + $mod_raza + $mod_linaje;
```

**Componentes:**

| Componente | Fuente | Descripción |
|------------|--------|-------------|
| **10** | Base fija | Todo personaje tiene al menos 10 PA. Es el mínimo absoluto. |
| **`intdiv(AGI, 2)`** | Stat AGI (valor numérico) | El valor de AGI se divide entre 2 (división entera). AGI 4 (rango D) → +2. AGI 60 (rango SS) → +30. |
| **`modificadores_pa_raza`** | `data_json.modificadores_pa_raza` | Bono/malus racial persistente. Ej: razas con afinidad táctica natural. |
| **`modificadores_pa`** | `data_json.linaje.modificadores_pa` | Bono/malus del linaje (pasiva `pa_extra`). Ej: +1 PA por pasiva racial. |

**Ejemplos de PA máximo:**

| Perfil | AGI valor | PA base | +AGI/2 | Mod racial | Mod linaje | **PA total** |
|--------|:---------:|:-------:|:------:|:----------:|:----------:|:------------:|
| Civil (stats 1, Humano) | 4 | 10 | +2 | 0 | 0 | **12** |
| Novato (AGI rango C) | 8 | 10 | +4 | 0 | 0 | **14** |
| Luchador ágil (AGI rango B) | 15 | 10 | +7 | 0 | 0 | **17** |
| Especialista (AGI rango A) | 26 | 10 | +13 | 0 | 0 | **23** |
| Maestro (AGI rango S) | 40 | 10 | +20 | 0 | +1 (linaje) | **31** |
| Leyenda (AGI rango SS) | 60 | 10 | +30 | +2 (raza) | +1 (linaje) | **43** |

**Impacto RPG:** La AGI no solo determina iniciativa y evasión, sino también la capacidad de acción táctica. Un personaje con AGI alta puede hacer más cosas por post que uno lento. Esto da un rol táctico claro a los ágiles: son los que ejecutan múltiples acciones, mientras que los tanques (RES alta, AGI baja) tienen menos PA pero más PV para aguantar.

### 1.3 Declaración en Cada Post (`pa_declared`)

Cada vez que un jugador crea un post en un hilo de rol, el plugin `game_postcharacter` registra el post en `game_post_characters`. El campo `pa_declared` almacena cuántos PA el jugador **declara** haber gastado en ese post.

**¿Cómo se declara?** El jugador incluye en su post (usando el editor o un campo oculto en el formulario de post rápido) la cantidad de PA que consume. Esto puede ser:

- **Implícito:** El sistema de cartas ya descuenta PA por cada carta jugada (el JS calcula el coste total y lo envía como `pa_declared`).
- **Explícito:** El jugador escribe manualmente "Gasto X PA en esto" y el staff lo verifica después.

**No hay descuento automático.** El sistema registra lo que el jugador declara, pero no valida en tiempo real si el jugador tenía suficientes PA o si el gasto es legal. Eso es responsabilidad del staff en la revisión de hilo.

**Estructura en la tabla `game_post_characters`:**

```sql
pa_declared TINYINT UNSIGNED NOT NULL DEFAULT 0
COMMENT 'PA declarado gastado por el jugador en este post (referencia para staff, no validación automática)'
```

**Flujo de datos:**

```
1. Jugador escribe post con N cartas + acciones especiales
2. JS calcula coste total en PA (suma de costes de cada carta/acción)
3. JS envía POST con pa_declared = coste_total
4. Plugin game_postcharacter registra pa_declared en game_post_characters
5. Staff revisa hilo completo, verifica que pa_declared <= max_pa del personaje
   y que las acciones realizadas correspondan al PA declarado
6. Si hay discrepancia, el staff corrige o pide ajuste al jugador
```

### 1.4 Validación por Staff (NO automática)

Ésta es una de las decisiones de diseño más importantes del sistema de PA:

**El sistema NO valida automáticamente los PA gastados.**

Razones:

1. **Confianza sobre control:** El foro opera bajo un modelo de confianza. El jugador declara su gasto de PA, y el staff verifica durante la revisión del hilo. No hay un sistema automático que impida al jugador gastar más PA de los que tiene.

2. **Revisión holística:** El staff revisa el hilo completo (no post por post) para evaluar la coherencia narrativa, el gasto de recursos y el resultado. Validar PA en tiempo real requeriría un sistema de combate automatizado que no es el objetivo del foro.

3. **Flexibilidad narrativa:** Un jugador puede necesitar gastar más PA en un post climático que en uno de transición. La validación automática no entendería contexto narrativo. El staff sí.

4. **Carga técnica mínima:** No implementar un validador en tiempo real simplifica el backend: no hay necesidad de trackear estado de PA entre posts, manejar concurrencia, o lidiar con casos borde de regeneración.

**¿Qué verifica el staff?**
- Que `pa_declared` no exceda el `max_pa` del personaje en ese hilo.
- Que las cartas jugadas tengan sentido con el PA declarado (una carta de coste 5 no puede jugarse si solo declaraste 3 PA).
- Que no haya abuso (declarar PA bajos para acciones que claramente requieren más).
- Que el jugador no haya "olvidado" declarar PA en posts donde claramente gastó (múltiples cartas, Haki avanzado, etc.).

### 1.5 ¿Qué Afecta el PA?

El PA es el combustible de las acciones tácticas en cada post. Afecta directamente a:

**a) Número de cartas jugables por post:**
Cada carta tiene un coste en PA (definido por el staff en `game_catalogo_cartas.coste_pa`). La suma de costes de todas las cartas jugadas en un post no puede exceder el PA declarado.

```php
// Pseudocódigo de validación de cartas vs PA
$coste_total_cartas = array_sum(array_column($cartas_jugadas, 'coste_pa'));
if ($coste_total_cartas > $pa_declarado) {
    // El staff marcará esto en revisión
    $discrepancias[] = "Coste de cartas ({$coste_total_cartas}) excede PA declarado ({$pa_declarado})";
}
```

**b) Acciones especiales:**
Ciertas acciones (movimientos combinados, técnicas de equipo, acciones ambientales mayores) pueden tener un coste en PA definido por el staff o por la carta misma.

**c) Haki avanzado:**
El uso de Haki avanzado (Kenbunshoku para predicción, Busoshoku avanzado para emisión, Haoshoku para dominio) consume PA. Los costes están definidos en el sistema de Haki (`10-haki.md`).

**d) Límite de acciones narrativas:**
Aunque no hay una regla dura, el staff puede usar el PA como referencia: un personaje con 12 PA no debería realizar 5 acciones significativas en un post. El PA es una guía narrativa de "cuánto puede hacer este personaje en un post".

### 1.6 Regeneración y Persistencia del PA

**Regla fundamental: el PA se refresca por post.**

A diferencia del PV/PE, que son persistentes por hilo (se almacenan en `game_thread_pj_state` y se modifican con cada post), el PA **no tiene estado persistente entre posts**.

**¿Qué significa esto realmente?**

- **Conceptualmente:** Cada post nuevo, el personaje recupera su PA máximo. Puede gastar hasta ese máximo en el post actual.
- **Técnicamente:** No hay una columna `current_pa` en `game_thread_pj_state`. Solo existe `max_pa` (calculado en runtime desde stats + modificadores) y `pa_declared` por post (lo que el jugador declaró gastar en ese post específico).
- **Para el jugador:** Puedes asumir que al escribir un nuevo post, tu PA se "resetca" a tu máximo. No arrastras gasto de posts anteriores.
- **Para el staff:** La revisión es post por post. Evalúas si el PA declarado en el post N es válido para las acciones de ese post, sin considerar posts previos.

**Excepción narrativa:** El staff puede decidir que cierto combate o escena tiene fatiga acumulativa, y que el PA máximo se reduce posts subsiguientes. Esto es decisión del staff, no una regla del sistema. Ejemplo: "Estás en tu tercer combate consecutivo sin descanso. Tu PA máximo se reduce en 5 para este hilo."

**¿Por qué no persistir el PA actual como se hace con PV/PE?**
- **Simplicidad:** PV/PE son recursos de daño/acumulación que sí necesitan persistencia. PA es un recurso de acción que se "gasta y olvida" por post.
- **Ritmo de juego:** Si el PA se arrastrara, un jugador que gastó mucho en un post tendría menos en el siguiente, penalizando la participación activa. El diseño actual incentiva a los jugadores a usar sus recursos cada post.
- **Diferenciación de PE:** PE (Puntos de Energía) se gasta en técnicas y se regenera lentamente (persistente por hilo). PA es más volátil: se gasta y recupera por post. Esta diferenciación es intencional.

### 1.7 Relación con PE (Puntos de Energía)

PE y PA son dos recursos distintos pero complementarios:

| Recurso | Naturaleza | Persistencia | Se gasta en | Se regenera |
|---------|-----------|-------------|-------------|-------------|
| **PA** | Acción táctica | Por post (no persiste) | Cartas, Haki avanzado, acciones especiales | Cada post nuevo |
| **PE** | Energía de técnicas | Por hilo (persiste en `game_thread_pj_state`) | Técnicas de Akuma no Mi, habilidades de Estilo, ciertas cartas | Lentamente por post (o por descanso) |

**Regla práctica:** PA es "lo que puedes hacer". PE es "cuánto puedes gastar haciendo lo que haces".

- Un personaje puede tener mucho PA (muchas acciones) pero poco PE (no puede mantener técnicas caras). Sus posts serán muchas acciones pequeñas.
- Un personaje con mucho PE pero poco PA puede hacer una o dos acciones muy poderosas por post.
- Ambos recursos se gestionan juntos: una carta puede requerir PA para activarse y PE para mantener su efecto.

### 1.8 Database Schema

**Tabla principal: `game_post_characters`**

```sql
CREATE TABLE mybb_game_post_characters (
    post_id               INT PRIMARY KEY,
    thread_id             INT DEFAULT NULL,
    user_id               INT NOT NULL,
    character_id          INT NOT NULL,
    pv_change             INT NOT NULL DEFAULT 0,
    pe_change             INT NOT NULL DEFAULT 0,
    pa_declared           TINYINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'PA declarado gastado por el jugador en este post (referencia para staff, no validación automática)',
    modifiers_json        TEXT DEFAULT NULL
        COMMENT 'Modificadores de stats activos en este post',
    hidden_actions_json   TEXT DEFAULT NULL
        COMMENT 'Acciones ocultas no visibles al rival (trampas, preparativos)',
    equipped_snapshot_json TEXT DEFAULT NULL
        COMMENT 'Snapshot del equipamiento del PJ al momento del post',
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_thread_id (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Campos clave para PA:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `post_id` | INT (PK) | ID del post de MyBB. Un post por personaje (si hay múltiples PJs en un post, cada uno tiene su fila). |
| `pa_declared` | TINYINT UNSIGNED | PA que el jugador declara gastar. Rango 0–255 (suficiente para cualquier personaje, máximo teórico ~43). |
| `hidden_actions_json` | TEXT | Acciones que el jugador declara como ocultas (trampas, emboscadas). Cada acción puede tener coste en PA. |

**Tabla auxiliar: `game_thread_pj_state`**

```sql
CREATE TABLE mybb_game_thread_pj_state (
    thread_id       INT NOT NULL,
    character_id    INT NOT NULL,
    current_pv      INT NOT NULL,
    current_pe      INT NOT NULL,
    stat_mods_json  TEXT DEFAULT NULL
        COMMENT 'Modificadores de stats activos en este hilo',
    last_post_id    INT DEFAULT NULL,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (thread_id, character_id)
);
```

**¿Por qué PA no está aquí?** Porque PA no se persiste entre posts. `pa_declared` en `game_post_characters` es suficiente: cada post tiene su propia declaración de PA. No hay un "PA actual" que arrastrar entre posts.

**Migración histórica** (`game/sql/migrate_post_pa_declared.php`):

```php
<?php
declare(strict_types=1);

/**
 * Migración: Añadir columna pa_declared a game_post_characters.
 */
global $db;
$prefix = TABLE_PREFIX;

if ($db->table_exists('game_post_characters')) {
    if (!$db->field_exists('pa_declared', 'game_post_characters')) {
        $db->write_query("ALTER TABLE {$prefix}game_post_characters 
            ADD COLUMN pa_declared TINYINT UNSIGNED NOT NULL DEFAULT 0 
            COMMENT 'PA declarado gastado por el jugador en este post (referencia para staff, no validación automática)'
            AFTER pe_change");
        echo "<p class='ok'>[OK] Columna 'pa_declared' añadida a 'game_post_characters'.</p>";
    } else {
        echo "<p class='skip'>[--] Columna 'pa_declared' ya existe en 'game_post_characters'.</p>";
    }
} else {
    echo "<p class='error'>[ERROR] La tabla 'game_post_characters' no existe.</p>";
}
```

### 1.9 PHP Implementation — `thread_pj_state.php`

El endpoint `game/ajax/thread_pj_state.php` calcula el PA máximo en runtime y lo expone al frontend:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if (!$uid) {
    echo json_encode(['ok' => false, 'error' => ['code' => 401, 'message' => 'No autorizado.']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['ok' => false, 'error' => ['code' => 405, 'message' => 'Method not allowed']]);
    exit;
}

$thread_id = $mybb->get_input('thread_id', MyBB::INPUT_INT);
$char_id = $mybb->get_input('character_id', MyBB::INPUT_INT);
$prefix = TABLE_PREFIX;

// Si no se especifica character_id, usar el personaje activo
if ($char_id <= 0) {
    $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
    $cfg = $db->fetch_array($cfg_q);
    $char_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
}

if ($char_id <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Sin personaje activo.']]);
    exit;
}

// Cargar stats y data del personaje
$pj_q = $db->query("SELECT stats_json, data_json, race_name FROM {$prefix}game_personajes WHERE id = {$char_id} LIMIT 1");
$pj = $db->fetch_array($pj_q);

$stats = json_decode($pj['stats_json'] ?? '{}', true);
$data = json_decode($pj['data_json'] ?? '{}', true);

$raceName = (string)($pj['race_name'] ?? '');
$ctx = game_build_stat_context($stats, $raceName);
$values = $ctx['values'];

// Calcular PV y PE máximos
$vitals = game_compute_pv_pe_from_context($values, $ctx['trained']);
$max_pv = $vitals['max_pv'];
$max_pe = $vitals['max_pe'];

// CÁLCULO DEL PA MÁXIMO
$mod_raza = (int)($data['modificadores_pa_raza'] ?? 0);
$mod_linaje = (int)($data['linaje']['modificadores_pa'] ?? 0);
$max_pa = 10 + intdiv($values['agi'], 2) + $mod_raza + $mod_linaje;

// Cargar PV/PE actuales desde el estado del hilo (si existe)
$current_pv = $max_pv;
$current_pe = $max_pe;
$stat_mods = [];

if ($thread_id > 0 && $db->table_exists('game_thread_pj_state')) {
    $state_q = $db->query("
        SELECT current_pv, current_pe, stat_mods_json
        FROM {$prefix}game_thread_pj_state
        WHERE thread_id = {$thread_id} AND character_id = {$char_id}
        LIMIT 1
    ");
    $state = $db->fetch_array($state_q);
    if ($state) {
        $current_pv = (int)$state['current_pv'];
        $current_pe = (int)$state['current_pe'];
        $decoded = json_decode($state['stat_mods_json'] ?? '{}', true);
        if (is_array($decoded)) {
            $stat_mods = $decoded;
        }
    }
}

echo json_encode([
    'ok' => true,
    'data' => [
        'thread_id' => $thread_id,
        'character_id' => $char_id,
        'current_pv' => $current_pv,
        'current_pe' => $current_pe,
        'max_pv' => $max_pv,
        'max_pe' => $max_pe,
        'max_pa' => $max_pa,               // ← PA máximo disponible
        'stat_mods' => $stat_mods,
        'stats_ranks' => $ctx['trained'],
        'stats_display' => $ctx['display'],
    ],
    'error' => null,
]);
```

**Detalles técnicos importantes:**

1. **`max_pa` se calcula con valores efectivos** (incluyendo bonos raciales). Si un Mink tiene AGI entrenada 3 (+1 racial → AGI efectiva 4 → valor 26), el PA se calcula con valor 26. Esto hace que la raza influya en la capacidad táctica.

2. **`modificadores_pa_raza`** está en `data_json` como un entero. Se asigna manualmente por staff para ciertas razas o situaciones especiales. No todas las razas lo tienen.

3. **`modificadores_pa`** está dentro de `data_json.linaje` y proviene de pasivas de linaje como `pa_extra` que otorgan +1 PA.

4. **No se persiste `current_pa`.** El PA máximo se recalcula cada vez que se carga el estado del hilo. No hay un PA actual que se modifique con cada acción.

### 1.10 PHP Implementation — `pa_declared` en Post Creation

Cuando un jugador crea un post, el plugin `game_postcharacter` (`inc/plugins/game_postcharacter.php`) se dispara y registra la información del post en `game_post_characters`. El campo `pa_declared` se llena desde los datos enviados por el frontend.

**Flujo en el plugin (conceptual):**

```php
// Dentro del hook datahandler_post_insert_post_end
function game_postcharacter_insert_post($post)
{
    global $db, $mybb;
    $prefix = TABLE_PREFIX;
    $pid = (int)$post['pid'];
    $tid = (int)$post['thread'];
    $uid = (int)$mybb->user['uid'];

    // Obtener el personaje activo del usuario
    $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
    $cfg = $db->fetch_array($cfg_q);
    $cid = $cfg ? (int)$cfg['active_pj_id'] : 0;
    if ($cid <= 0) return;

    // Procesar cartas jugadas en el post
    $postData = $_POST; // Datos del formulario del post
    $pa_declared = (int)($postData['pa_declared'] ?? 0);

    // Procesar cartas (que también consumen PA)
    $cardsProcessor = new ProcessPostCards($db, $prefix);
    $cardsProcessor->execute($pid, $cid, $postData);

    // También calcular el coste de PA desde las cartas jugadas
    $pa_from_cards = 0;
    if (!empty($postData['rpg_played_cards'])) {
        $card_ids = json_decode($postData['rpg_played_cards'], true);
        if (is_array($card_ids)) {
            foreach ($card_ids as $entry) {
                if (is_array($entry)) {
                    $pa_from_cards += (int)($entry['coste_pa'] ?? 0);
                }
            }
        }
    }

    // Si el jugador no declaró PA explícitamente, usar el coste de cartas
    if ($pa_declared === 0 && $pa_from_cards > 0) {
        $pa_declared = $pa_from_cards;
    }

    // Registrar o actualizar el post en game_post_characters
    $pa_declared_esc = $db->escape_string($pa_declared);
    $pv_change = (int)($postData['pv_change'] ?? 0);
    $pe_change = (int)($postData['pe_change'] ?? 0);
    $modifiers = $db->escape_string($postData['rpg_modifiers'] ?? '{}');
    $equipped = $db->escape_string($postData['rpg_equipped_snapshot'] ?? '{}');

    $db->write_query("
        INSERT INTO {$prefix}game_post_characters
            (post_id, thread_id, user_id, character_id, pv_change, pe_change, pa_declared, modifiers_json, equipped_snapshot_json)
        VALUES
            ({$pid}, {$tid}, {$uid}, {$cid}, {$pv_change}, {$pe_change}, {$pa_declared_esc}, '{$modifiers}', '{$equipped}')
        ON DUPLICATE KEY UPDATE
            pv_change = VALUES(pv_change),
            pe_change = VALUES(pe_change),
            pa_declared = VALUES(pa_declared),
            modifiers_json = VALUES(modifiers_json),
            equipped_snapshot_json = VALUES(equipped_snapshot_json)
    ");
}
```

**Procesamiento de cartas (`ProcessPostCards`):**

```php
<?php
namespace Game\Application\UseCases;

class ProcessPostCards
{
    private $db;
    private $prefix;

    public function __construct($db, string $prefix)
    {
        $this->db = $db;
        $this->prefix = $prefix;
    }

    public function execute(int $pid, int $cid, array $postData): void
    {
        $has_cards = !empty($postData['rpg_played_cards']);
        $has_hidden = !empty($postData['rpg_hidden_actions']);
        if (!$has_cards && !$has_hidden) {
            return;
        }

        // El PA ya fue registrado por el plugin; aquí solo se procesan
        // las cartas (tiradas de dados, efectos, etc.)
        
        $equipped_ids = game_postcharacter_get_post_equipped_ids($pid, $cid);
        game_postcharacter_ensure_stat_helpers();

        $stats = [];
        $stats_for_dice = [];
        $pj_q = $this->db->query("SELECT name, stats_json, race_name FROM {$this->prefix}game_personajes WHERE id = {$cid} LIMIT 1");
        $pj = $this->db->fetch_array($pj_q);
        if ($pj) {
            $stats_decoded = json_decode($pj['stats_json'] ?? '{}', true);
            $stats_raw = is_array($stats_decoded) ? $stats_decoded : [];
            $turn_mods = [];
            if (!empty($postData['rpg_modifiers'])) {
                $raw_mods = json_decode($postData['rpg_modifiers'], true);
                if (is_array($raw_mods)) {
                    foreach ($raw_mods as $mod_stat => $mod_val) {
                        $mod_stat = strtolower(trim((string)$mod_stat));
                        $mod_val = (int)$mod_val;
                        if ($mod_val !== 0 && in_array($mod_stat, ['fue','res','agi','des','int','esp','inst'], true)) {
                            $turn_mods[$mod_stat] = ($turn_mods[$mod_stat] ?? 0) + $mod_val;
                        }
                    }
                }
            }
            $ctx = game_build_stat_context($stats_raw, (string)($pj['race_name'] ?? ''), $turn_mods);
            $stats = $ctx['trained'];
            $stats_for_dice = $ctx['values'];
        }

        // Procesar cartas visibles
        if (!empty($postData['rpg_played_cards'])) {
            $card_ids = json_decode($postData['rpg_played_cards'], true);
            if (is_array($card_ids)) {
                foreach ($card_ids as $c_entry) {
                    game_postcharacter_process_card_entry($pid, $cid, $c_entry, $stats_for_dice, [], 0, $equipped_ids);
                }
            }
        }

        // Procesar acciones ocultas
        if (!empty($postData['rpg_hidden_actions'])) {
            $hidden_actions = json_decode($postData['rpg_hidden_actions'], true);
            if (is_array($hidden_actions)) {
                $saved_actions = [];
                foreach ($hidden_actions as $action) {
                    $action_idx = (int)($action['index'] ?? 0);
                    if ($action_idx <= 0) continue;
                    
                    $description = isset($action['description']) ? trim((string)$action['description']) : '';
                    $action_cards = isset($action['cards']) && is_array($action['cards']) ? $action['cards'] : [];
                    
                    foreach ($action_cards as $c_entry) {
                        game_postcharacter_process_card_entry($pid, $cid, $c_entry, $stats_for_dice, [], $action_idx, $equipped_ids);
                    }
                    
                    $saved_actions[] = [
                        'index' => $action_idx,
                        'description' => $description,
                        'is_revealed' => 0
                    ];
                }
                
                if (!empty($saved_actions) && $this->db->field_exists('hidden_actions_json', 'game_post_characters')) {
                    $json_str = json_encode($saved_actions, JSON_UNESCAPED_UNICODE);
                    $esc_json = "'" . $this->db->escape_string($json_str) . "'";
                    $this->db->write_query("UPDATE {$this->prefix}game_post_characters SET hidden_actions_json = {$esc_json} WHERE post_id = {$pid} AND character_id = {$cid}");
                }
            }
        }
    }
}
```

**Nota:** `ProcessPostCards` no valida PA. Solo procesa las cartas. La validación de PA es responsabilidad del staff en la revisión de hilo.

### 1.11 Staff Review Workflow para PA

La revisión de PA no es un paso aislado — es parte de la revisión completa del hilo que el staff realiza periódicamente.

**Checklist de revisión de PA:**

```
□ 1. Por cada post, leer pa_declared en game_post_characters
□ 2. Verificar pa_declared <= max_pa del personaje en ese hilo
□ 3. Verificar que las cartas jugadas suman coste <= pa_declared
□ 4. Verificar que acciones especiales (Haki, combinadas) se reflejan en PA
□ 5. Verificar que acciones ocultas (hidden_actions_json) tienen PA asignado
□ 6. Si hay discrepancia, notificar al jugador para ajuste
□ 7. Si el jugador abusa (declara PA menor al necesario), aplicar corrección
```

**Herramienta de staff — Vista de PA por hilo:**

```sql
-- Consulta para staff: Ver PA declarado y cartas por post en un hilo
SELECT
    gpc.post_id,
    gpc.character_id,
    gp.name AS personaje,
    gpc.pa_declared,
    gpc.modifiers_json,
    gpc.hidden_actions_json
FROM mybb_game_post_characters gpc
JOIN mybb_game_personajes gp ON gp.id = gpc.character_id
WHERE gpc.thread_id = {$thread_id}
ORDER BY gpc.post_id ASC;
```

**¿Qué hace el staff si detecta una discrepancia?**

| Situación | Acción del staff |
|-----------|-----------------|
| PA declarado > cartas jugadas | OK (el jugador pudo haber gastado PA en acciones narrativas sin carta) |
| PA declarado < cartas jugadas | Notificar al jugador. Si es error honesto, corregir. Si es recurrente, advertir. |
| PA declarado = 0 pero hay cartas | Asumir que el jugador olvidó declarar. Corregir a `pa_declared = coste_cartas`. |
| PA declarado > max_pa posible | El jugador no puede tener más PA que su máximo. Corregir a max_pa y advertir. |
| Acción oculta sin PA asignado | Verificar si la acción debería costar PA. Si es relevante, pedir ajuste. |

### 1.12 Modificadores de PA por Raza y Linaje

**Racial (`data_json.modificadores_pa_raza`):**
Algunas razas pueden tener un bono o penalización innata al PA máximo. Este valor es un entero que se suma directamente. Ejemplo:

```json
{
    "modificadores_pa_raza": 2
}
```

Este modificador se asigna manualmente por staff cuando una raza tiene afinidad táctica. No hay bonos raciales automáticos de PA en `linaje_catalog.json` — el catálogo solo define bonos a stats, no a PA directamente.

**Linaje (`data_json.linaje.modificadores_pa`):**
Proviene de pasivas de linaje como `pa_extra`. Cuando un jugador elige una pasiva que otorga +1 PA, se almacena aquí:

```json
{
    "linaje": {
        "modificadores_pa": 1
    }
}
```

**Ejemplo completo en `data_json`:**

```json
{
    "pp": 120,
    "rank": "B",
    "nivel": 3,
    "faction_rank": "Capitán",
    "last_rank_change_at": "2025-06-12 10:30:00",
    "modificadores_pa_raza": 2,
    "linaje": {
        "modificadores_pa": 1,
        "bonusPP": 4,
        "passives": ["pa_extra"]
    }
}
```

---

## 2. PP — Puntos de Progresión

### 2.1 Definición Breve

Los **Puntos de Progresión (PP)** son la moneda de mejora permanente del personaje. Se ganan principalmente mediante actividad rolística (posts) y se gastan en mejoras que nunca se pierden.

**Diferencia clave con PA:** PP es permanente (sunk cost). Una vez gastado, no se recupera. PA es táctico (se refresca por post).

### 2.2 ¿Qué Compra el PP?

- **Subir rangos de Stats** (~90% del gasto típico). Cada incremento de un stat (ej: FUE de 2 a 3) cuesta PP según una fórmula que depende del rango actual del stat y del rango global del personaje.
- **Desbloquear grados de Disciplinas** (PP por grado). Las disciplinas de combate (Temple, Ferocidad, etc.) requieren PP para subir de grado.
- **Desbloquear grados de Oficios** (PP por grado). Similar a disciplinas, pero para oficios (Carpintero, Cocinero, etc.).
- **Aprender Estilos Canónicos** (coste fijo en PP). Los estilos de lucha requieren una inversión única de PP.
- **Obtener ciertas Cartas** (las que tienen coste en PP). Cartas especiales del catálogo pueden requerir PP además de otros requisitos.

### 2.3 Estructura en `data_json`

```json
{
    "pp": 120,
    "pp_linaje": 4,
    "rank": "B",
    "nivel": 3,
    "faction_rank": "Capitán",
    "last_rank_change_at": "2025-06-12 10:30:00"
}
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `pp` | int | PP totales disponibles. Nunca negativo (normalizado a ≥0). |
| `pp_linaje` | int | PP provenientes del sistema de linaje (bonificación racial). Se gastan primero. |
| `rank` | string | Rango global (D, C, B, A, S, SS). Calculado desde suma de stats. |
| `nivel` | int | Equivalencia numérica del rango (1–6). |
| `faction_rank` | string | Rango dentro de la facción (ej: "Capitán", "Oficial"). No afecta mecánicas. |
| `last_rank_change_at` | datetime | Última vez que cambió el rango global. Para tracking. |

### 2.4 Cross-Reference a `03-rangos.md`

Para la mecánica COMPLETA de PP, consultar `03-rangos.md`:

| Tema | Sección en 03-rangos.md |
|------|------------------------|
| Costes de stats (fórmula completa) | [§5. Costes de Progresión](/Guias/sistemas/03-rangos.md#5-costes-de-progresión) |
| Tabla de costes base + multiplicadores | [§5.4 Tabla Completa de Costes](/Guias/sistemas/03-rangos.md#54-tabla-completa-de-costes) |
| `CharacterProgression` service (validate, apply, recalculate) | [§6. CharacterProgression](/Guias/sistemas/03-rangos.md#6-characterprogression) |
| `purchase_attribute.php` endpoint | [§7. purchase_attribute.php](/Guias/sistemas/03-rangos.md#7-purchaseattributephp) |
| Plugin de ganancia de PP por posts | [§8. Plugin MyBB](/Guias/sistemas/03-rangos.md#8-plugin-mybb) |
| Normalización y auto-reparación de PP | [§4.5 Normalización](/Guias/sistemas/03-rangos.md#45-normalización-y-auto-reparación) |
| PP de linaje (gasto prioritario) | [§4.4 PP de Linaje](/Guias/sistemas/03-rangos.md#44-pp-de-linaje) |
| Filosofía de la progresión lenta | [§13. Filosofía de Diseño](/Guias/sistemas/03-rangos.md#13-filosofía-de-diseño) |
| Consejos para jugadores sobre gasto de PP | [§14. Consejos para Jugadores](/Guias/sistemas/03-rangos.md#14-consejos-para-jugadores) |
| Consejos para staff (monitoreo) | [§15. Consejos para Staff](/Guias/sistemas/03-rangos.md#15-consejos-para-staff) |

### 2.5 Plugin de Ganancia por Posts

Cada 100 palabras de rol (excluyendo contenido Off_Rol) otorgan **1 PP**:

```php
const WORDS_PER_PP = 100;
PP_ganados = floor(palabras_de_rol / 100);
```

- Un post de 300–500 palabras → 3–5 PP por post.
- A 2 posts/semana → ~6–10 PP semanales.
- Para subir un stat de 1 a 2 (50 PP con RG D) → ~5–8 posts → ~2–4 semanas.

Los PP se otorgan al crear el post y NO se descuentan si el post se elimina (intencional: no se puede perder progreso por edición administrativa).

---

## 3. Interacción PA + PP

### 3.1 Cómo Funcionan Juntos

PA y PP son dos monedas que operan en planos distintos pero se complementan:

```
┌─────────────────────────────────────────────────────────────────┐
│                      CICLO RPG COMPLETO                        │
│                                                                 │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐      │
│  │  ESCRIBES     │    │  DECLARAS    │    │  USAS PA     │      │
│  │  UN POST      │───▶│  GASTO DE    │───▶│  PARA        │      │
│  │               │    │  PA + CARTAS │    │  ACCIONES    │      │
│  └──────────────┘    └──────────────┘    └──────────────┘      │
│         │                                                       │
│         ▼                                                       │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐      │
│  │  GANAS PP     │    │  ACUMULAS    │    │  GASTAS PP   │      │
│  │  (PALABRAS)   │───▶│  PP          │───▶│  EN MEJORAS  │      │
│  │               │    │  (data_json) │    │  (stats,etc) │      │
│  └──────────────┘    └──────────────┘    └──────────────┘      │
│                                                                 │
│  PP ↑ → stats ↑ → AGI ↑ → max_pa ↑ → más acciones por post     │
│  PP ↑ → stats ↑ → ESP ↑ → mejores cartas de Haki               │
│  PA bien usado → ventaja táctica → sobrevives → más posts → PP  │
└─────────────────────────────────────────────────────────────────┘
```

**Ciclo virtuoso:**
1. Haces posts de rol → gastas PA en acciones → ganas PP por palabras.
2. Gastas PP en subir stats (AGI, ESP, etc.) → tu PA máximo aumenta.
3. Con más PA, puedes hacer más acciones por post → posts más interesantes.
4. Más posts → más PP → sigues mejorando.

**Tensión estratégica:**
- Si gastas mucho PA en un post (muchas cartas, acciones complejas), tu post es más impactante pero no ganas PP extra por ello (el PP depende de palabras, no de PA).
- Si escribes posts largos (muchas palabras) pero con poco PA, avanzas más en progresión pero participas menos tácticamente.
- El balance ideal: posts con contenido sustancial (muchas palabras → PP) y uso táctico de PA (acciones significativas).

### 3.2 Tabla Comparativa PA vs PP

| Dimensión | PA | PP |
|-----------|:--:|:--:|
| **Naturaleza** | Táctico (por post) | Permanente (sunk cost) |
| **Se gana** | No se gana (es un tope) | Posts (100 palabras = 1 PP) |
| **Se gasta en** | Cartas, Haki, acciones especiales | Stats, disciplinas, oficios, estilos |
| **Se recupera** | Cada post nuevo | Nunca (es permanente) |
| **Persistencia** | No persiste entre posts | Persiste en `data_json.pp` |
| **Validación** | Staff (revisión de hilo) | Automática (servicio `CharacterProgression`) |
| **Tope** | `max_pa` (calculado desde AGI) | Sin tope (puedes acumular) |
| **Afectado por** | AGI, raza, linaje | Todas las decisiones de progresión |
| **Fallo posible** | Declarar más PA del máximo | No tener suficientes PP |
| **Consecuencia de fallo** | Staff corrige en revisión | Compra rechazada |

### 3.3 La Frontera Entre lo Táctico y lo Permanente

**¿Qué hace que algo cueste PA vs PP?**

| Criterio | PA (táctico) | PP (permanente) |
|----------|:------------:|:---------------:|
| **Temporalidad** | Efecto inmediato, dura un post | Efecto permanente, dura para siempre |
| **Ejemplo 1** | Jugar carta de ataque en este post | Comprar la carta para tu colección |
| **Ejemplo 2** | Usar Haki Kenbunshoku para predecir | Desbloquear el grado de Haki que permite usarlo |
| **Ejemplo 3** | Activar técnica especial de Estilo | Aprender el Estilo Canónico |
| **Ejemplo 4** | Coordinar ataque combinado en equipo | Subir AGI para tener más PA en el futuro |

**Regla de oro:** Si algo te da una ventaja en este post específico → PA. Si algo mejora a tu personaje para siempre → PP.

**Zona gris — Haki avanzado:**
- Desbloquear Haki (requisito de ESP + PP): PP permanente.
- Usar Haki en un post (activar Kenbunshoku, recubrir de Busoshoku): PA por post.
- El desbloqueo te permite USAR la habilidad; el PA es el coste de activarla.

---

## 4. Filosofía de Diseño

### 4.1 ¿Por Qué PA como Recurso Táctico?

**Problema que resuelve:** En muchos RPGs por foro, un jugador puede hacer cualquier cosa en un post sin límite de recursos. Esto lleva a:
- Posts donde un personaje hace 10 acciones diferentes, rompiendo la credibilidad.
- Combates donde un jugador resuelve todo en un post, dejando sin participación a los demás.
- Falta de tensión táctica: no hay decisión difícil, solo "hago todo lo que puedo".

**PA fuerza decisiones difíciles:**
- "¿Juego esta carta de ataque poderoso (5 PA) o juego dos cartas de apoyo (2+2 PA)?"
- "¿Guardo PA para una posible reacción, o gasto todo en mi ofensiva?"
- "¿Coordinamos un ataque combinado (cada uno gasta 3 PA) o cada uno va por su cuenta?"

**PA también resuelve el problema de la asincronía:**
En un foro asíncrono, los jugadores postean en diferentes momentos. El PA limita cuánto puede hacer un jugador en su post, dando oportunidad a los demás de reaccionar. Sin PA, el primer jugador en postear podría resolver el combate antes de que los otros tengan turno.

### 4.2 ¿Por Qué PP es Permanente (Sunk Cost)?

**PP es la moneda del logro.** Cada PP gastado es un paso que no se deshace. Esto:

1. **Da peso a cada decisión:** Gastar 1800 PP en subir FUE de 5 a 6 es una decisión que tomas después de meses de juego. No hay vuelta atrás. Cada mejora se siente significativa.

2. **Elimina el miedo a "perder" progreso:** Si los PP se pudieran perder o reembolsar, los jugadores no sentirían que su progreso es real. PP permanente = tu personaje MEJORA de verdad.

3. **Crea historias de crecimiento:** El rango global, los stats, las disciplinas — todo refleja la historia de juego del personaje. "Recuerdo cuando gasté mis primeros PP en subir FUE" es un hito que el jugador recuerda.

4. **Evita el "respec" infinito:** Si los PP fueran refundables, los jugadores optimizarían sus builds constantemente, perdiendo la identidad del personaje. Un personaje que invirtió en ESP para Haki no debería poder "resetear" y poner todo en FUE porque ahora quiere ser luchador.

**Contraste con PA:** PA es volátil, se gasta y olvida. PP es permanente, se guarda y recuerda. Juntos cubren ambas necesidades: la emoción del momento (PA) y la satisfacción del progreso (PP).

### 4.3 ¿Por Qué el Staff Valida PA (No Automatizado)?

**Decisión consciente:** Podríamos haber implementado un sistema automático que verifique PA en tiempo real (como hacen los videojuegos). Elegimos no hacerlo.

**Razones:**

1. **El combate por foro es narrativo, no matemático.** En un videojuego, validar PA automáticamente es trivial porque hay un estado global sincronizado. En un foro, los posts se escriben en distintos momentos y la validación automática requeriría trackear estado en tiempo real.

2. **El staff es parte del juego.** La revisión de hilo no es solo para validar PA. El staff también evalúa la calidad narrativa, la coherencia, y ajusta resultados según el contexto. Automatizar PA sería quitarle una herramienta de evaluación al staff.

3. **Flexibilidad sobre rigidez.** Un jugador puede tener una razón narrativa para gastar más PA en un post climático. El staff puede aprobarlo aunque exceda ligeramente el máximo, si la escena lo merece. Un sistema automático no haría esa excepción.

4. **Coste de desarrollo.** Implementar un sistema de validación automática de PA requeriría:
   - Persistencia de PA actual entre posts.
   - Lógica de descuento automático en el plugin de post.
   - Manejo de concurrencia (¿qué pasa si dos jugadores postean al mismo tiempo?).
   - Interfaz de corrección para el staff cuando el sistema falla.
   Todo esto para validar un recurso que se "gasta y olvida" — no vale la pena.

**Confianza como valor:** El sistema asume buena fe del jugador. Si un jugador abusa declarando PA incorrectos, el staff lo detecta en revisión y aplica consecuencias. La mayoría de los jugadores declararán honestamente.

### 4.4 ¿Por Qué un Sistema de Doble Moneda?

**PA + PP crean un ecosistema económico RPG completo:**

```
┌──────────────────────────────────────────────────────────────┐
│                    ECONOMÍA RPG COMPLETA                     │
│                                                              │
│  Moneda táctica (PA)                                         │
│  ├── Se gasta en: acciones inmediatas                        │
│  ├── Se regenera: cada post                                  │
│  ├── Validación: staff (revisión)                            │
│  └── Propósito: crear tensión táctica post a post            │
│                                                              │
│  Moneda de progresión (PP)                                   │
│  ├── Se gasta en: mejoras permanentes                        │
│  ├── Se gana: posteando (100 palabras = 1 PP)               │
│  ├── Validación: automática (servicio CharacterProgression)  │
│  └── Propósito: recompensar la actividad a largo plazo       │
│                                                              │
│  Moneda de energía (PE) — ver sistema de stats               │
│  ├── Se gasta en: técnicas, habilidades especiales           │
│  ├── Se regenera: lentamente por hilo (descanso)             │
│  ├── Validación: semi-automática (staff con ayuda de UI)     │
│  └── Propósito: limitar el uso de técnicas poderosas         │
└──────────────────────────────────────────────────────────────┘
```

**Las tres monedas operan en escalas temporales distintas:**
- **PA:** Segundo a segundo (post a post). "¿Qué hago AHORA?"
- **PE:** Minuto a minuto (dentro de un combate/hilo). "¿Cuánto puedo gastar antes de agotarme?"
- **PP:** Día a día (a lo largo de semanas/meses). "¿En qué quiero mejorar a mi personaje?"

Esta triada cubre todas las necesidades de un RPG:
- **Emergencia táctica (PA):** La emoción del momento. Decisiones rápidas, impacto inmediato.
- **Gestión de recursos (PE):** La estrategia de mediano plazo. Administrar energía para no quedarse sin combustible.
- **Progresión a largo plazo (PP):** La satisfacción del crecimiento. Ver a tu personaje volverse más fuerte con el tiempo.

---

## 5. Consejos para Jugadores

### 5.1 Cómo Presupuestar PA por Post

**Conoce tu `max_pa`:** Antes de postear, revisa tu PA máximo (disponible en el endpoint `thread_pj_state.php` o en la UI del hilo). Sepas cuánto puedes gastar.

**Estrategia de gasto:**

| Estilo de juego | Gasto de PA recomendado | Cuándo usarlo |
|----------------|:-----------------------:|---------------|
| **Explorador / diálogo** | 0–5 PA | Posts de interacción social, investigación, exploración. No necesitas gastar PA si no hay combate o acción significativa. |
| **Combate ligero** | 5–10 PA | Escaramuzas, peleas menores, entrenamiento. 1–2 cartas de ataque o apoyo. |
| **Combate serio** | 10–20 PA | Jefes, combates PvP importantes. 3–5 cartas, uso de Haki, acciones combinadas. |
| **Todo o nada** | max_pa | Momento climático del arco. Gastas todo tu PA en un post espectacular. Te quedas sin PA para reaccionar, pero el impacto narrativo vale la pena. |

**No gastes todo siempre.** Guardar algo de PA "por si acaso" no es necesario mecánicamente (PA se refresca por post), pero narrativamente puede serlo: si tu personaje gasta todo su PA en atacar, no podrá defenderse narrativamente de un contraataque. El staff puede considerar que un personaje sin PA restante es más vulnerable.

**Acciones ocultas y PA:** Si usas `hidden_actions_json` (trampas, preparativos), asegúrate de declarar el PA correspondiente. Una acción oculta sigue siendo una acción y consume PA.

### 5.2 Qué Priorizar con PP

**Prioridad de gasto recomendada:**

1. **Stats a 2 general (350 PP):** Sube los 7 stats a 2 primero. Es la inversión más eficiente: te da RG C y desbloquea el primer multiplicador de PV/PE (×1.05).

2. **Stat principal a 3–4 (180–530 PP):** Elige tu stat principal (FUE para luchador, ESP para usuario de Haki, etc.) y llévalo a 3 o 4. Esto te da competencia real en tu área.

3. **AGI para más PA (variable):** AGI no solo da iniciativa y evasión, sino que aumenta tu `max_pa`. Cada punto de valor de AGI (que sube al subir el rango) te da +0.5 PA (redondeado hacia abajo). Subir AGI de rango 1 a 2 (valor 4→8) te da +2 PA.

4. **ESP si planeas Haki (variable):** El Haki requiere ESP mínimo y cuesta PP. Si es tu camino, empieza a invertir pronto.

5. **Disciplinas y Estilos (PP fijo):** Una vez que tus stats base están sólidos, invierte en disciplinas y estilos canónicos que definan tu build.

**Lo que NO deberías hacer:**
- Gastar todos tus PP en un solo stat. Un personaje con FUE 6 y RES 1 tiene ~50 PV. Cualquier enemigo decente lo tumba.
- Acumular PP sin gastar. Si tienes 2000+ PP guardados y stats en 1, estás perdiendo la oportunidad de ser más efectivo ahora.
- Gastar PP en cartas que no usas. Las cartas con coste de PP son una inversión — si no las vas a usar en tus posts, no las compres.

### 5.3 Errores Comunes

**Error #1: Confundir PA con PE.**
- PA es para acciones por post. PE es para energía de técnicas. No gastes PE en acciones que deberían costar PA, y viceversa.
- Si no estás seguro: las cartas suelen tener coste de PA (para jugarlas) y a veces también coste de PE (para mantener sus efectos).

**Error #2: Declarar PA de más sin tener las cartas o acciones para justificarlo.**
- Declarar 20 PA pero solo jugar 2 cartas de 3 PA cada una (total 6 PA) es sospechoso. El staff preguntará "¿en qué gastaste los otros 14 PA?". Sé honesto: declara solo lo que gastas.

**Error #3: Olvidar declarar PA.**
- Si juegas cartas pero pones `pa_declared = 0`, el staff asumirá que olvidaste declarar y corregirá. Pero si pasa seguido, puede parecer que intentas evitar el límite de PA.

**Error #4: No considerar el PA en acciones de equipo.**
- Si coordinas un ataque combinado, cada participante gasta PA. No asumas que "el líder paga por todos". Cada personaje gasta su propio PA.

**Error #5: Ignorar AGI por ser "solo para rápidos".**
- AGI afecta directamente tu `max_pa`. Si tienes AGI baja, tendrás pocas acciones por post. Incluso un tanque necesita algo de AGI para no quedarse sin PA.

**Error #6: Gastar PP de linaje en cosas superfluas.**
- Los PP de linaje se gastan PRIMERO. Si los gastas en una carta decorativa, perderás la oportunidad de usarlos para subir stats. Los PP de linaje son oro: úsalos sabiamente.

---

## 6. Consejos para Staff

### 6.1 Cómo Revisar Declaraciones de PA

**El PA se revisa como parte de la revisión de hilo. No es un paso separado.**

**Checklist rápido:**

```
□ ¿pa_declared <= max_pa del personaje?
   → max_pa = 10 + floor(AGI_valor / 2) + mod_raza + mod_linaje

□ ¿Las cartas jugadas suman coste_pa <= pa_declared?
   → SELECT coste_pa FROM game_catalogo_cartas WHERE id IN (...)

□ ¿Las acciones ocultas tienen PA asignado?
   → Revisar hidden_actions_json

□ ¿El jugador usó Haki avanzado sin declarar PA?
   → Verificar tab_haki para costes de activación

□ ¿Hay acciones narrativas que claramente requieren PA pero no se declararon?
   → Ej: "esquivó una docena de balas, contraatacó, y salvó a un compañero" sin PA declarado

□ Si hay discrepancia: ¿fue error honesto o patrón de abuso?
   → Error honesto: corregir y notificar. Patrón: advertir formalmente.
```

**Herramienta SQL para staff:**

```sql
-- Vista unificada de PA por hilo
SELECT
    p.subject AS post_subject,
    p.dateline,
    gp.name AS personaje,
    gpc.pa_declared,
    COALESCE(gpc.hidden_actions_json, '[]') AS hidden_actions,
    -- Calcular el max_pa del personaje en ese momento (aproximado)
    -- Nota: los stats pueden haber cambiado desde el post
    (10 +
        FLOOR(
            COALESCE(JSON_EXTRACT(gp.stats_json, '$.agi'), 1) / 2
        )
    ) AS max_pa_aproximado
FROM mybb_posts p
JOIN mybb_game_post_characters gpc ON gpc.post_id = p.pid
JOIN mybb_game_personajes gp ON gp.id = gpc.character_id
WHERE gpc.thread_id = {$thread_id}
ORDER BY p.dateline ASC;
```

**¿Qué hago si hay abuso sistemático de PA?**

| Nivel de abuso | Acción |
|----------------|--------|
| Leve (1-2 errores honestos) | Corrección silenciosa + notificación privada al jugador. |
| Moderado (3+ posts con discrepancia) | Conversación con el jugador. Explicar el sistema de PA. Ofrecer ayuda. |
| Grave (declara PA falsos para ganar ventaja) | Advertencia formal. Si reincide, penalización temporal de PA (reducir max_pa para ese personaje por X posts). |
| Extremo (abuso reiterado + mala fe) | Elevar a administración. Posible sanción disciplinaria. |

### 6.2 Balanceando Costes de PA para Encuentros

Como staff, puedes definir costes de PA para acciones especiales en tus eventos:

| Tipo de encuentro | PA típico por post | Notas |
|-------------------|:------------------:|-------|
| Encuentro social (negociación, fiesta) | 0–3 PA | Las acciones sociales rara vez requieren PA. Solo si hay confrontación o habilidad especial. |
| Encuentro de exploración (investigar, rastrear) | 2–8 PA | Depende de la complejidad: buscar pistas, interactuar con el entorno, evitar trampas. |
| Combate menor (bandidos, marines raso) | 5–15 PA | Suficiente para 1-3 cartas. No abrumar. |
| Combate contra jefe (vicealmirante, capitán pirata) | 10–25 PA | Permitir despliegue táctico completo. |
| Evento de arco (batalla masiva, asalto a base) | 15–max_pa | El máximo de cada personaje. Que cada jugador decida cuánto gastar. |

**Regla de oro:** No diseñes encuentros donde el jugador necesite gastar más PA del que razonablemente tiene. Un personaje nuevo (AGI valor 4) tiene solo 12 PA. Pedirle 15 PA para una acción necesaria lo excluye.

**Si quieres aumentar la dificultad:**
- No subas el coste en PA (eso excluye a personajes con AGI baja).
- En lugar de eso, añade más enemigos o condiciones de victoria adicionales que requieran coordinar PA entre varios personajes.

**Costes de PA para Haki (referencia rápida):**

| Habilidad | Coste PA (por post) |
|-----------|:-------------------:|
| Kenbunshoku — Predicción básica | 3 PA |
| Kenbunshoku — Predicción avanzada | 6 PA |
| Busoshoku — Recubrimiento básico | 2 PA |
| Busoshoku — Emisión (Ryou) | 5 PA |
| Haoshoku — Imposición de voluntad | 8 PA |
| Haoshoku — Dominio avanzado | 15 PA |

*Costes orientativos. El staff puede ajustarlos según el contexto del hilo.*

### 6.3 Aprobando Gastos de PP

A diferencia del PA, el PP se valida automáticamente a través de `CharacterProgression::validateStatUpgrade()`. Pero el staff tiene roles importantes:

**¿Qué puede hacer el staff con PP?**

1. **Otorgar PP manualmente:** Editando `data_json.pp` en DB para recompensas de eventos, misiones completadas, o PD convertidos. Útil para acelerar la progresión de un jugador que contribuyó significativamente a la trama.

2. **Limitar compras por razones narrativas:** No hay bloqueo técnico, pero puedes acordar con el jugador "no subas FUE hasta que completes esta misión de entrenamiento". Esto fomenta la coherencia.

3. **Detectar y corregir anomalías:**
   - PP negativos: `normalize()` los corrige a 0 automáticamente, pero deberías investigar cómo ocurrió.
   - PP desproporcionados: Un jugador con 5000 PP acumulados y stats en 1 no es un error técnico, pero puede indicar que el jugador no entiende el sistema o está acumulando para un dump masivo.

4. **Aprobar compras grandes manualmente:** Aunque `purchase_attribute.php` no requiere aprobación del staff, puedes pedir a los jugadores que te consulten antes de compras significativas (ej: subir un stat de 5 a 6, desbloquear un Estilo Canónico). No es obligatorio, pero buenísimo para la coherencia narrativa.

**Señales de alerta con PP:**

| Señal | Posible problema |
|-------|-----------------|
| Stats muy dispares (6/1/1/1/1/1/1) | Min-maxer extremo. Muy frágil. Verificar que el jugador entiende las consecuencias. |
| PP acumulados sin gastar (>2000) | El jugador puede estar acumulando para un dump masivo. Preguntar si necesita ayuda o está esperando algo. |
| Progresión muy rápida (>500 PP/semana) | Posible abuso de posts cortos o multicuentas. Revisar actividad reciente. |
| Progresión nula (0 cambios en 3 meses) | Personaje abandonado o jugador inactivo. Contactar. |

**Flujo de aprobación para compras de PP:**

```
┌──────────────────────────────────────────────────────────────┐
│  JUGADOR                                                     │
│  Decide gastar PP en stat/estilo/carta                       │
│  └── Si es compra pequeña (<200 PP): ¡adelante!              │
│  └── Si es compra grande (>500 PP): consultar al staff       │
│      (buena práctica, no obligatorio)                        │
└──────────┬───────────────────────────────────────────────────┘
           │ POST /purchase_attribute.php
           ▼
┌──────────────────────────────────────────────────────────────┐
│  SISTEMA (CharacterProgression)                              │
│  1. validateStatUpgrade() → ¿stat válido? ¿PP suficientes?   │
│  2. applyStatUpgrade() → descuenta PP, sube stat,            │
│     recalcula rango global                                   │
│  3. UPDATE game_personajes → persiste cambios                │
└──────────┬───────────────────────────────────────────────────┘
           │ automático
           ▼
┌──────────────────────────────────────────────────────────────┐
│  STAFF (revisión posterior)                                  │
│  En la siguiente auditoría de personaje:                     │
│  □ ¿La compra tiene sentido narrativo?                       │
│  □ ¿El jugador no acumuló PP de forma anómala?               │
│  □ ¿El nuevo rango global es correcto?                       │
│  Si algo está mal: corregir y hablar con el jugador.         │
└──────────────────────────────────────────────────────────────┘
```

---

## 7. Referencia Rápida

### Fórmulas Esenciales

```
PA máximo por post = 10 + floor(AGI_valor / 2) + mod_raza + mod_linaje
  AGI_valor: rango AGI → valor numérico (4, 8, 15, 26, 40, 60)
  Ej: AGI rango C (valor 8) → +4 PA
  Ej: AGI rango SS (valor 60) → +30 PA

PA declarado (pa_declared) = suma costes de cartas + acciones especiales
  NO validado automáticamente → revisión de staff
  Se registra en game_post_characters.pa_declared

PP ganados por post = floor(palabras_de_rol / 100)
  PP por palabra: 100 palabras = 1 PP
  Off_Rol no cuenta
  Límite: ninguno (acumulación sin tope)

PP gastados = coste_base × multiplicador_RG
  Costes base: 50 → 130 → 350 → 800 → 1800
  Multiplicadores: D=1.00, C=1.07, B=1.15, A=1.35, S=1.60, SS=2.00
  Se gasta PP de linaje primero
```

### Archivos Clave

| Archivo | Propósito |
|---------|-----------|
| `back/forum/game/ajax/thread_pj_state.php` | Endpoint GET que calcula `max_pa`, PV, PE en runtime |
| `back/forum/game/sql/install_schema_fragments.php` | DDL de `game_post_characters` (incluye `pa_declared`) |
| `back/forum/game/sql/migrate_post_pa_declared.php` | Migración para añadir `pa_declared` |
| `inc/plugins/game_postcharacter.php` | Plugin que registra `pa_declared` al crear post |
| `back/forum/game/src/Application/UseCases/ProcessPostCards.php` | Procesamiento de cartas en posts |
| `back/forum/game/src/Application/Services/CharacterProgression.php` | Servicio de PP (validación, compra, normalización) |
| `back/forum/game/ajax/purchase_attribute.php` | Endpoint de compra de stats con PP |
| `Guias/sistemas/03-rangos.md` | Guía completa del sistema de rangos y PP |
| `Guias/sistemas/05-cards.md` | Guía del sistema de cartas (costes de PA por carta) |
| `Guias/sistemas/10-haki.md` | Guía del sistema de Haki (costes de PA por habilidad) |

### Tabla de PA Máximo por Build

| Build de ejemplo | AGI rango | AGI valor | PA base | +AGI/2 | Total |
|------------------|:---------:|:---------:|:-------:|:------:|:-----:|
| Tanque puro (RES 6, AGI 1) | D | 4 | 10 | +2 | **12** |
| Guerrero equilibrado (AGI 3) | B | 15 | 10 | +7 | **17** |
| Pícaro / esquivador (AGI 4) | A | 26 | 10 | +13 | **23** |
| Especialista en velocidad (AGI 5) | S | 40 | 10 | +20 | **30** |
| Velocidad máxima (AGI 6) | SS | 60 | 10 | +30 | **40** |
| Ídem + bonos raza/linaje | SS | 60 | 10 | +30 | **43** (máximo teórico) |

### Glosario Rápido

| Término | Definición |
|---------|------------|
| **PA** | Puntos de Aventura. Recurso táctico por post. Límite de acciones. |
| **PP** | Puntos de Progresión. Moneda permanente de mejora. |
| **PE** | Puntos de Energía. Recurso de técnicas, persistente por hilo. |
| **`pa_declared`** | Campo en `game_post_characters` donde el jugador declara PA gastados. |
| **`max_pa`** | PA máximo calculado en runtime desde AGI + modificadores. |
| **`modificadores_pa_raza`** | Bono/malus racial al PA, almacenado en `data_json`. |
| **`modificadores_pa`** | Bono de linaje al PA, dentro de `data_json.linaje`. |
| **Coste PA de carta** | Atributo `coste_pa` en `game_catalogo_cartas`. Cuánto PA cuesta jugar esa carta. |
| **PP de linaje** | PP bonus provenientes del sistema de linaje. Se gastan primero. |
| **Sunk cost** | Principio de que el PP gastado no se recupera. Mejora permanente. |

---

*Fin del documento — Guía completa del Sistema de PA y PP v1.0*
*Generado desde: `Guias/sistemas/04-pa-pp.md`*
*Referencia: `Guias/MAESTRO_SISTEMAS_RPG.md` — Sección 4*
*Ver también: `03-rangos.md` (progresión detallada), `05-cards.md` (costes de PA), `10-haki.md` (costes de PA en Haki)*
