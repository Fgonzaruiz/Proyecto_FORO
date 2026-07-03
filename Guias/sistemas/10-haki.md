# 10. HAKI — GUÍA COMPLETA

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 10
> **Propósito:** Documentar exhaustivamente el subsistema de Haki: los tres tipos (Kenbunshoku, Busoshoku, Haoshoku), modelo de datos (`game_haki_progress`), niveles y requisitos, flujo de solicitud de subida, sistema de aprobación/rechazo por staff, tirada de despertar del Conquistador, integración con cartas, visualización en la ficha, herramientas de staff, filosofía de diseño, y consejos para jugadores y staff.

---

## ÍNDICE

1. [Los 3 Tipos de Haki](#1-los-3-tipos-de-haki)
2. [Haki como Disciplina](#2-haki-como-disciplina)
3. [Base de Datos — `game_haki_progress`](#3-base-de-datos)
4. [Niveles de Haki](#4-niveles-de-haki)
5. [Flujo de Solicitud de Subida](#5-flujo-de-solicitud-de-subida)
6. [Tirada de Despertar del Conquistador](#6-tirada-de-despertar-del-conquistador)
7. [Resolución por Staff](#7-resolución-por-staff)
8. [Integración con Cartas](#8-integración-con-cartas)
9. [Visualización en la Ficha](#9-visualización-en-la-ficha)
10. [Herramientas de Staff](#10-herramientas-de-staff)
11. [Filosofía de Diseño](#11-filosofía-de-diseño)
12. [Consejos para Jugadores](#12-consejos-para-jugadores)
13. [Consejos para Staff](#13-consejos-para-staff)

---

## 1. Los 3 Tipos de Haki

El sistema implementa tres tipos de Haki, cada uno con mecánicas, requisitos de adquisición y progresión independientes. Comparten la misma tabla `game_haki_progress` pero se diferencian por su `haki_type` y sus reglas de negocio asociadas.

### 1.1 Kenbunshoku — Haki de Observación

**Descripción narrativa:** Permite al usuario percibir la presencia, intensidad y emociones de otros seres vivos. En niveles avanzados, otorga la capacidad de anticipar movimientos en combate y detectar amenazas a distancia, incluso a través de obstáculos. Es la expresión más refinada de la percepción espiritual.

**Comportamiento mecánico:**
- Permite al personaje detectar presencias ocultas, emboscadas y enemigos invisibles.
- En combate, justifica reacciones evasivas y contraataques basados en predicción.
- Se integra con cartas de tipo `haki` que representan técnicas específicas de observación (visión del futuro, detección de mentiras, percepción extendida).
- El stats clave que rige su efectividad es **Espíritu (ESP)**.

**Entrenamiento:** Se adquiere mediante PP — es una disciplina accesible a cualquier personaje que cumpla los requisitos de ESP y nivel. Progresa mediante uso en posts (usos acumulados) y subidas de nivel pagadas con PP.

**Niveles narrativos:**
| Nivel | Nombre Interno | Descripción |
|-------|---------------|-------------|
| 0 | No manifestado | El personaje no ha despertado este Haki |
| 1 | `obs_latente` | Percepción instintiva básica — sensación de "algo no está bien" |
| 2 | `obs_basico` | Percepción consciente — puede detectar presencias a corta distancia |
| 3 | `obs_medio` | Percepción extendida — detecta presencias a media distancia, intenciones hostiles |
| 4 | `obs_avanzado` | Visión parcial del futuro — anticipa movimientos inmediatos |
| 5 | `obs_futuro` | Visión del futuro extendida — percibe segundos adelante |

### 1.2 Busoshoku — Haki de Armamento

**Descripción narrativa:** Permite endurecer el cuerpo o imbuir objetos con una armadura espiritual invisible. Es el único medio de dañar físicamente a usuarios de Frutas Logia. En niveles superiores, permite la emisión del Haki a distancia (Ryou) para atacar sin contacto físico.

**Comportamiento mecánico:**
- Otorga capacidad de dañar a usuarios de Logia y a defensas espirituales.
- Justifica técnicas defensivas de endurecimiento y ofensivas de impacto potenciado.
- El stats clave que rige su efectividad es **Espíritu (ESP)**.
- Es requisito obligatorio para enfrentar a oponentes Logia en igualdad de condiciones.

**Entrenamiento:** Mismo esquema que Kenbunshoku: adquisición con PP, progresión por usos, subidas de nivel aprobadas por staff.

**Niveles narrativos:**
| Nivel | Nombre Interno | Descripción |
|-------|---------------|-------------|
| 0 | No manifestado | Sin despertar |
| 1 | `arm_latente` | Endurecimiento instintivo, apenas perceptible |
| 2 | `arm_basico` | Endurecimiento consciente de extremidades |
| 3 | `arm_medio` | Endurecimiento completo del cuerpo o arma |
| 4 | `arm_interno` | Emisión de Haki (Ryou) — daño interno sin contacto |
| 5 | `arm_supremo` | Armadura total, emisión a distancia dominada |

### 1.3 Haoshoku — Haki de Conquistador

**Descripción narrativa:** La cualidad de imponer la propia voluntad sobre los demás. Innata y rara — no se entrena con esfuerzo, sino que se manifiesta en aquellos destinados a ser reyes o figuras de influencia colosal. En combate, puede derribar a oponentes de voluntad débil e infundir armas con un poder aplastante.

**Comportamiento mecánico:**
- No se adquiere con PP — se despierta mediante una **tirada especial** (staff-gated).
- Una vez despertado, progresa con usos y PP como los otros tipos, pero con requisitos más exigentes.
- Permite noquear PNJs de nivel inferior sin tirada (según grado).
- En niveles avanzados, permite infundir armas con Haki de Conquistador (cartas exclusivas tier 4+).
- El stats clave es **Espíritu (ESP)**, con requisitos mínimos muy altos.

**Niveles narrativos:**
| Nivel | Nombre Interno | Descripción |
|-------|---------------|-------------|
| 0 | No manifestado | Potencial dormido |
| 1 | `rey_latente` | Manifestación involuntaria bajo estrés extremo |
| 2 | `rey_basico` | Control consciente, derriba multitudes de PNJs débiles |
| 3 | `rey_medio` | Oleada de conquista — AoE masivo vs oponentes de nivel inferior |
| 4 | `rey_avanzado` | Infundir armas con Haki de Conquistador |
| 5 | `rey_supremo` | Presencia abrumadora — debuff pasivo, forzar rendición |

---

## 2. Haki como Disciplina

### 2.1 Kenbunshoku y Busoshoku como Disciplinas

Kenbunshoku y Busoshoku se comportan como **disciplinas** en el sentido de que:
- Se "desbloquean" pagando PP y cumpliendo requisitos.
- Son accesibles a cualquier personaje (no hay restricción racial ni de clase).
- Están sujetas a un techo de nivel máximo (nivel 5).
- Sus cartas se clasifican bajo `card_type = 'haki'` con `haki_type` específico.

Sin embargo, **no se implementan como disciplinas del sistema `game_character_disciplinas`**. Históricamente, el Haki vivió dentro del sistema de disciplinas de combate (`competencias_v2_seed_data.php` contiene `haki_conquistador` como disciplina). La migración a `game_haki_progress` fue una decisión arquitectónica deliberada (ver [Sección 11 — Filosofía de Diseño](#11-filosofía-de-diseño)).

### 2.2 Haoshoku como Disciplina Exclusiva

Haoshoku **sí tiene** una entrada en el sistema de disciplinas de combate como `haki_conquistador` (ver `competencias_v2_seed_data.php:159`):

```php
'haki_conquistador' => [
    '1' => 'PNJs tier 1 caen inconscientes sin tirada en multitudes.',
    '2' => 'Afecta rivales con ESP bajo (< rango 2). Cartas tier 2.',
    '3' => 'Oleada de Conquista: AoE masivo vs enemigos de menor nivel. Cartas tier 3.',
    '4' => 'Infundir armas con Haki de Conquistador. Cartas tier 4 exclusivas.',
    '5' => 'Rey de los Mares: debuff pasivo; forzar rendición PNJ mayor (1/arco). Cartas tier 5.',
],
```

Esta entrada en competencias define los efectos narrativos que los grados 1–5 de Haoshoku otorgan. La diferencia clave: mientras las disciplinas normales se adquieren con PP y aprobación directa, Haoshoku requiere la **tirada de despertar** primero, y luego progresa mediante el sistema de `game_haki_progress`.

### 2.3 Contexto Histórico: La Migración

Originalmente todo el Haki (Kenbunshoku, Busoshoku, Haoshoku) vivía dentro del sistema de `game_disciplinas` / `game_character_disciplinas`. Cada tipo era una "disciplina" más, con grados I–V. Sin embargo, este diseño presentaba problemas:

1. **Falta de granularidad:** No se podía trackear el uso por tipo de Haki (usos_total).
2. **Estados ausentes:** No existía el concepto de "pendiente de revisión" ni "pp_reservados".
3. **Acoplamiento:** Las disciplinas tenían su propio sistema de adquisición con cooldowns y reglas que no encajaban con la naturaleza del Haki (que es paralelo al combate, no sustitutivo).
4. **Haoshoku como caso especial:** La tirada de despertar no encajaba en el modelo estándar de disciplina.

La migración a `game_haki_progress` (ver `migrate_haki_progress.php`) resolvió estos problemas:
- Tabla dedicada con `haki_type` como ENUM, no FK a `game_disciplinas`.
- Columnas específicas para el flujo de subida (`status`, `pp_reservados`).
- `usos_total` para trackear uso narrativo.
- UNIQUE KEY `(character_id, haki_type)` — un registro por tipo por personaje.

### 2.4 Diferencias con las Disciplinas de Combate Regulares

| Aspecto | Disciplina Regular | Haki (game_haki_progress) |
|---------|-------------------|--------------------------|
| Adquisición | PP directo + cooldown | PP reservados + staff approval |
| Estados | Solo adquirido/no adquirido | activo, pendiente_subida, rechazado |
| Límite | Grados I–V | Niveles 1–5 (también 5) |
| Uso | No se trackea | `usos_total` acumulado |
| Cooldown | Global entre mejoras | No tiene cooldown |
| Tabla | `game_character_disciplinas` | `game_haki_progress` |
| Haoshoku | No aplica | Tirada especial de despertar |

---

## 3. Base de Datos

### 3.1 Tabla `game_haki_progress`

Definición completa (de `migrate_haki_progress.php`):

```sql
CREATE TABLE IF NOT EXISTS {$prefix}game_haki_progress (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  character_id   INT NOT NULL,
  haki_type      ENUM('kenbunshoku','busoshoku','haoshoku') NOT NULL,
  nivel          TINYINT UNSIGNED NOT NULL DEFAULT 0 
                 COMMENT '0=no desbloqueado, 1-5=nivel actual',
  usos_total     INT UNSIGNED NOT NULL DEFAULT 0 
                 COMMENT 'Usos acumulados al jugar cartas de este tipo en posts',
  status         ENUM('activo','pendiente_subida') NOT NULL DEFAULT 'activo',
  pp_reservados  INT UNSIGNED NOT NULL DEFAULT 0 
                 COMMENT 'PP descontados pendientes de confirmación o devolución',
  unlocked_at    DATETIME NULL,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_char_haki (character_id, haki_type),
  FOREIGN KEY (character_id) REFERENCES {$prefix}game_personajes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.2 Columnas en Detalle

| Columna | Tipo | Propósito |
|---------|------|-----------|
| `id` | INT AUTO_INCREMENT | PK pura, no tiene significado de negocio |
| `character_id` | INT | FK a `game_personajes(id)`. ON DELETE CASCADE — si el PJ se elimina, su progresión de Haki también |
| `haki_type` | ENUM('kenbunshoku','busoshoku','haoshoku') | Identificador del tipo. No es FK — los tipos son fijos en el código |
| `nivel` | TINYINT UNSIGNED | 0 = no desbloqueado. 1–5 = nivel actual alcanzado. TINYINT es suficiente (nunca excederá 5) |
| `usos_total` | INT UNSIGNED | Contador acumulativo de usos narrados. Se incrementa cuando se juegan cartas de este haki_type en posts |
| `status` | ENUM('activo','pendiente_subida') | **activo**: estado normal, sin peticiones pendientes. **pendiente_subida**: hay una upgrade request en revisión |
| `pp_reservados` | INT UNSIGNED | PP descontados del saldo del personaje pero no consumidos definitivamente hasta aprobación. Se devuelven si se rechaza |
| `unlocked_at` | DATETIME NULL | Fecha de desbloqueo inicial (solo para Haoshoku y primera subida de Kenbun/Busoshoku). Se setea en la resolución cuando `nivel` pasa de 0 a 1+ |
| `updated_at` | DATETIME | Actualización automática con ON UPDATE CURRENT_TIMESTAMP. Sirve para ordenar peticiones pendientes |

### 3.3 UNIQUE KEY: `uq_char_haki`

`UNIQUE KEY uq_char_haki (character_id, haki_type)` — Garantiza que un personaje tiene exactamente UNA fila por tipo de Haki. Esto permite usar `INSERT ... ON DUPLICATE KEY UPDATE` como patrón upsert en los AJAX handlers, evitando checks previos de existencia.

### 3.4 Consideraciones de Diseño

**¿Por qué ENUM para `haki_type` y no una tabla separada?**
- Los tres tipos son fijos, definidos en el lore del sistema. En el futuro previsible no se añadirán nuevos tipos.
- ENUM permite validación a nivel de base de datos sin JOINs.
- Si en el futuro se requirieran tipos adicionales, ALTER TABLE para añadir un nuevo valor al ENUM es una operación trivial.

**¿Por qué TINYINT para `nivel`?**
- El máximo es 5. TINYINT UNSIGNED ocupa 1 byte. No hay razón para usar INT ni SMALLINT.
- El comentario documenta el rango esperado (0-5).

**¿Por qué `usos_total` está aquí y no en `game_character_cards`?**
- Porque es independiente de las cartas específicas. El contador mide la "experiencia práctica" en el tipo, no la cantidad de veces que se usó una carta concreta.
- Simplifica las queries de requisitos (SELECT usos_total FROM game_haki_progress WHERE ... en lugar de SUM sobre game_character_cards con JOINs).

---

## 4. Niveles de Haki

### 4.1 Sistema de Progresión

Cada tipo de Haki progresa en **5 niveles** (1 a 5). El nivel 0 significa "no desbloqueado". Para subir de nivel, el jugador debe cumplir requisitos acumulativos y pagar PP.

### 4.2 Requisitos por Nivel — Kenbunshoku y Busoshoku

Definidos en `_tab_haki.php:58-64` y replicados en `haki_upgrade.php:63-77`:

```php
$reqs_normal = [
    1 => ['esp' => 2, 'nivel' => 1, 'usos' => 0,  'coste' => 100],
    2 => ['esp' => 3, 'nivel' => 2, 'usos' => 5,  'coste' => 300],
    3 => ['esp' => 4, 'nivel' => 3, 'usos' => 15, 'coste' => 700],
    4 => ['esp' => 5, 'nivel' => 4, 'usos' => 35, 'coste' => 1500],
    5 => ['esp' => 6, 'nivel' => 5, 'usos' => 60, 'coste' => 3000],
];
```

| Nivel | ESP Requerido | Nivel PJ | Usos Requeridos | Coste PP |
|-------|--------------|----------|-----------------|----------|
| 1 | 2 (B) | 1 | 0 | 100 |
| 2 | 3 (B+) | 2 | 5 | 300 |
| 3 | 4 (A-) | 3 | 15 | 700 |
| 4 | 5 (A) | 4 | 35 | 1.500 |
| 5 | 6 (A+) | 5 | 60 | 3.000 |

**Observaciones clave:**
- El nivel 1 **no requiere usos previos** (usos = 0). Esto permite al personaje desbloquear el Haki inmediatamente al cumplir ESP y nivel mínimos.
- El coste acumulado para llegar a nivel 5 es **5.600 PP** (100+300+700+1.500+3.000).
- ESP requerido escala hasta rango 6 (A+), el máximo humano.
- Nivel de PJ requerido escala hasta nivel 5 (no requiere nivel máximo — nivel 6 no es necesario para Kenbunshoku/Busoshoku).

### 4.3 Requisitos por Nivel — Haoshoku (Conquistador)

Definidos en `_tab_haki.php:66-71` y `haki_upgrade.php:78-84`:

```php
$reqs_conq = [
    2 => ['esp' => 4, 'nivel' => 4, 'usos' => 10, 'coste' => 500],
    3 => ['esp' => 5, 'nivel' => 5, 'usos' => 25, 'coste' => 1200],
    4 => ['esp' => 6, 'nivel' => 5, 'usos' => 45, 'coste' => 2500],
    5 => ['esp' => 6, 'nivel' => 6, 'usos' => 70, 'coste' => 5000],
];
```

| Nivel | ESP Requerido | Nivel PJ | Usos Requeridos | Coste PP |
|-------|--------------|----------|-----------------|----------|
| 1 | (despertar vía tirada) | | | |
| 2 | 4 (A-) | 4 | 10 | 500 |
| 3 | 5 (A) | 5 | 25 | 1.200 |
| 4 | 6 (A+) | 5 | 45 | 2.500 |
| 5 | 6 (A+) | 6 | 70 | 5.000 |

**Diferencias críticas con Kenbunshoku/Busoshoku:**
- **No hay reqs para nivel 1.** El nivel 1 se obtiene exclusivamente por la tirada de despertar (o como resultado de la misma, pudiendo obtener nivel 1, 2 o 3 directamente).
- **Requisitos mucho más altos:** Nivel PJ 6 para el nivel 5, coste de 5.000 PP frente a 3.000.
- **Escalada más pronunciada:** Los usos requeridos son inferiores a Kenbunshoku en niveles bajos (10 vs 5 para nivel 2 de Kenbun) pero más exigentes en altos (70 vs 60).
- **Coste total:** 500+1.200+2.500+5.000 = **9.200 PP** desde nivel 1 a 5.

### 4.4 El Status `pendiente_subida`

Cuando el jugador solicita una subida de nivel:
1. El status de la fila cambia a `pendiente_subida`.
2. Los PP se descuentan del saldo del personaje y se registran en `pp_reservados`.
3. El personaje **no puede** solicitar otra subida del mismo tipo hasta que el staff resuelva (apruebe o rechace).
4. La validación en `haki_upgrade.php:52-54` bloquea solicitudes duplicadas:

```php
if ($currentStatus === 'pendiente_subida') {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Ya tienes una solicitud de subida pendiente para este Haki.'], 400);
}
```

### 4.5 Tabla Comparativa de Costes Totales

| Haki | N1→N5 Total PP | Nivel PJ Max | ESP Max |
|------|---------------|--------------|---------|
| Kenbunshoku | 5.600 | 5 | 6 (A+) |
| Busoshoku | 5.600 | 5 | 6 (A+) |
| Haoshoku (desde despertar) | 9.200 | 6 | 6 (A+) |

---

## 5. Flujo de Solicitud de Subida

### 5.1 Arquitectura del Endpoint

**Archivo:** `game/ajax/haki_upgrade.php`
**Método:** POST
**Content-Type:** `application/json`
**Autenticación:** Requiere login + CSRF token
**Actor:** El dueño del personaje (no staff, no terceros)

### 5.2 Flujo Completo (Backend)

```
Cliente                          Servidor                                MySQL
  │                                │                                      │
  │  POST /ajax/haki_upgrade.php   │                                      │
  │  { character_id, haki_type }   │                                      │
  │ ─────────────────────────────> │                                      │
  │                                │                                      │
  │                                │  1. Validar login + CSRF             │
  │                                │  2. Validar parámetros               │
  │                                │  3. Verificar active PJ match        │
  │                                │  4. Cargar game_haki_progress        │
  │                                │                                      │
  │                                │  ── Validaciones previas ──          │
  │                                │  5. ¿Ya tiene pendiente? → error     │
  │                                │  6. ¿Ya está en nivel máx (5)? → err │
  │                                │  7. Cargar target reqs del nivel      │
  │                                │  8. ¿Haoshoku nivel 0? → error       │
  │                                │                                      │
  │                                │  ── Validaciones de requisitos ──    │
  │                                │  9. Calcular ESP efectivo            │
  │                                │  10. Calcular nivel PJ               │
  │                                │  11. Validar ESP >= requerido         │
  │                                │  12. Validar nivel PJ >= requerido    │
  │                                │  13. Validar usos_total >= requerido  │
  │                                │  14. Validar PP >= coste              │
  │                                │                                      │
  │                                │  ── Ejecución ──                     │
  │                                │  15. Restar PP del data_json         │
  │                                │     UPDATE game_personajes           │
  │                                │     SET data_json = ...              │
  │                                │                                      │
  │                                │  16. INSERT ... ON DUPLICATE KEY     │
  │                                │     INTO game_haki_progress          │
  │                                │     SET status='pendiente_subida',   │
  │                                │     pp_reservados = coste            │
  │                                │                                      │
  │                                │  17. Crear notificación al usuario   │
  │                                │     ('haki_upgrade_pending')         │
  │                                │                                      │
  │  JSON { ok: true, data }       │                                      │
  │ <───────────────────────────── │                                      │
```

### 5.3 Validación Detallada (haki_upgrade.php)

#### 5.3.1 Validación de Personaje Activo

```php
if (game_get_active_pj_id($uid) !== $characterId) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'Debes usar tu personaje activo.'], 403);
}
```

Solo se puede solicitar subidas para el personaje activo. Esto evita que un jugador maneje múltiples PJs y gaste PP de uno en otro.

#### 5.3.2 Validación de Estado del Personaje

```php
if (($character['status'] ?? '') !== 'aprobada') {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'El personaje debe estar aprobado para progresar Haki.'], 403);
}
```

Personajes no aprobados (borradores, en revisión) no pueden gastar PP en Haki.

#### 5.3.3 Validación Haoshoku Nivel 0

```php
if ($targetReq === null) {
    if ($hakiType === 'haoshoku' && $currentLevel === 0) {
        GameAjax::json(false, null, ['code' => 403, 'message' => 'El Haki de Conquistador debe ser despertado primero por el staff.'], 403);
    }
}
```

El Haoshoku no puede solicitar nivel 1 por el canal normal. Solo la tirada de despertar puede otorgar el nivel inicial.

#### 5.3.4 Cálculo de ESP Efectivo

```php
$stats = !empty($character['stats_json']) ? json_decode($character['stats_json'], true) : [];
$statCtx = game_build_stat_context(StatScale::sanitizeRanks($stats), (string)($character['race_name'] ?? ''));
$espEffectiveRank = (int)($statCtx['effective_ranks']['esp'] ?? 1);
```

El ESP efectivo considera bonificaciones raciales (por ejemplo, razas con afinidad espiritual pueden tener ESP efectivo más alto que el rank base).

#### 5.3.5 Cálculo de Nivel y PP

```php
$data = !empty($character['data_json']) ? json_decode($character['data_json'], true) : [];
CharacterProgression::syncLinajeBonusPp($data, (string)($character['race_name'] ?? ''));
CharacterProgression::normalize($data);
$charNivel = game_get_character_nivel($data);
$ppAvailable = (int)($data['pp'] ?? 0);
```

Se sincronizan bonos de linaje antes de calcular nivel y PP disponibles.

### 5.4 La Transacción (PP Reservation)

A diferencia de las compras normales donde el PP se consume inmediatamente, aquí se sigue un modelo de **reservación**:

1. **Se resta el coste de `data_json.pp`** (el jugador ve el PP reducido).
2. **Se guarda el mismo valor en `pp_reservados`**.
3. **El status pasa a `pendiente_subida`**.

Si la aprobación llega: el `pp_reservados` se pone a 0 (PP ya consumido).
Si el rechazo llega: el `pp_reservados` se devuelve a `data_json.pp`.

### 5.5 Notificación al Usuario

```php
game_create_notification(
    $uid,
    'haki_upgrade_pending',
    'Solicitud de Haki enviada',
    "Has solicitado subir tu Haki de {$hakiLabel} al nivel {$targetLevel}. Se han reservado {$cost} PP.",
    $link,
    $characterId
);
```

Se usa el type `haki_upgrade_pending` para que el sistema de notificaciones pueda filtrar/tematizar este tipo de eventos.

### 5.6 Respuesta Exitosa

```json
{
  "ok": true,
  "data": {
    "character_id": 1,
    "haki_type": "kenbunshoku",
    "target_level": 2,
    "pp_spent": 300,
    "new_pp": 2700,
    "status": "pendiente_subida"
  },
  "error": null
}
```

---

## 6. Tirada de Despertar del Conquistador

### 6.1 Propósito

El Haoshoku no se adquiere con PP. Requiere un evento narrativo — una tirada de despertar que cualquiera (el jugador o un miembro del staff) puede iniciar cuando el personaje cumple los requisitos mínimos. Esta tirada es el **único** mecanismo para obtener nivel 1+ en Conquistador.

### 6.2 Requisitos para Tirar

Definidos en `haki_conquistador_roll.php:90-104`:

| Requisito | Mínimo | Explicación |
|-----------|--------|-------------|
| ESP efectivo | Rango 4 (A-) | Espiritualidad suficiente para canalizar la voluntad |
| Nivel PJ | 4 | Madurez narrativa y poder acumulado |
| PP | 500 | Coste fijo, se consume gane o pierda |
| No tener ya Haoshoku | nivel = 0 | No se puede despertar dos veces |

### 6.3 La Mecánica de Tirada

```php
$roll = rand(1, 100);
$bonus = ($espEffectiveRank - 4) * 5;
$total = $roll + $bonus;
```

**Fórmula:** `total = dado(1-100) + (ESP_efectivo - 4) * 5`

**Bono:** Por cada punto de ESP por encima de 4 (el mínimo), el personaje recibe +5 a la tirada. ESP 6 daría +10, ESP 7 daría +15, etc.

### 6.4 Tabla de Resultados

| Total | Nivel Obtenido | Etiqueta |
|-------|---------------|----------|
| < 41 | 0 | Fallo — no despierta |
| 41–70 | 1 | Latente (Grado I) |
| 71–90 | 2 | Básico (Grado II) |
| 91+ | 3 | Medio (Grado III) |

**Probabilidades con diferentes valores de ESP:**

| ESP | Bono | Prob. Fallo (<41) | Prob. Grado I | Prob. Grado II | Prob. Grado III |
|-----|------|-------------------|----------------|-----------------|-----------------|
| 4 | +0 | 40% | 30% | 20% | 10% |
| 5 | +5 | 35% | 30% | 20% | 15% |
| 6 | +10 | 30% | 30% | 20% | 20% |
| 7 | +15 | 25% | 30% | 20% | 25% |

### 6.5 Consumo de PP

```php
$data['pp'] = max(0, $ppAvailable - $cost);
```

Los 500 PP se **consumen independientemente del resultado**. Esto es intencional:
- Si se falla, el personaje ha invertido PP en el intento y debe acumular de nuevo.
- Si se acierta, los PP se consideran bien invertidos en el despertar.

No hay reservación aquí porque la tirada resuelve inmediatamente (no hay paso de aprobación de staff).

### 6.6 Inserción en Base de Datos

```php
$unlocked_at_sql = ($unlockedLevel > 0) ? "NOW()" : "NULL";
$db->write_query("
    INSERT INTO {$prefix}game_haki_progress (character_id, haki_type, nivel, status, pp_reservados, unlocked_at)
    VALUES ({$characterId}, 'haoshoku', {$unlockedLevel}, 'activo', 0, {$unlocked_at_sql})
    ON DUPLICATE KEY UPDATE
        nivel = {$unlockedLevel},
        status = 'activo',
        pp_reservados = 0,
        unlocked_at = {$unlocked_at_sql}
");
```

Patrón upsert: si el personaje ya tenía una fila (de un intento previo fallido con nivel 0), se actualiza. `unlocked_at` solo se setea si el nivel > 0.

### 6.7 Respuesta JSON

**Éxito (Grado III):**
```json
{
  "ok": true,
  "data": {
    "character_id": 1,
    "roll": 85,
    "bonus": 10,
    "total": 95,
    "unlocked_level": 3,
    "result_label": "Despertar Poderoso (Grado III)",
    "new_pp": 1500
  }
}
```

**Fallo:**
```json
{
  "ok": true,
  "data": {
    "character_id": 1,
    "roll": 25,
    "bonus": 5,
    "total": 30,
    "unlocked_level": 0,
    "result_label": "Fallo",
    "new_pp": 1000
  }
}
```

### 6.7 Frontend — Animación de la Tirada

El archivo `peticion_haki.js:50-134` implementa una animación con overlay visual:

1. Se crea un overlay con clase `haki-roll-overlay`.
2. Un dado animado (clase `haki-roll-animating`) con icono `fa-dice-d20`.
3. El texto "Lanzando dados..." aparece inicialmente.
4. Tras recibir respuesta (mínimo 1.5s de animación), se revela:
   - Corona `fa-crown` en éxito (cambia título a verde `success`).
   - Ojo cerrado `fa-eye-slash` en fallo (título rojo `fail`).
5. Detalles de la tirada (dado, bono, total) se muestran en un grid.
6. Botón "Aceptar" recarga la página para reflejar el nuevo estado.

---

## 7. Resolución por Staff

### 7.1 Arquitectura del Endpoint

**Archivo:** `game/ajax/haki_resolve.php`
**Método:** POST
**Actor:** Staff nivel 2+ (Moderador)
**Input:** `character_id`, `haki_type`, `action` ('aprobar'|'rechazar'), `motivo` (opcional en rechazo)

### 7.2 Validación de Staff Level

```php
$staff_level = 0;
if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level, is_staff FROM {$prefix}game_personajes WHERE id = {$active_pj_id} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj && (int)$pj['is_staff']) {
        $staff_level = (int)$pj['staff_level'];
    }
}
if ($staff_level < 2) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'Permiso denegado. Se requiere nivel de Staff 2 (Moderador) o superior.'], 403);
}
```

La validación verifica que el personaje activo del usuario tenga `is_staff = 1` y `staff_level >= 2`. No basta con tener permisos de foro — el personaje debe ser staff en el juego.

### 7.3 Flujo de Aprobación

```
Staff                              Servidor                                MySQL
  │                                  │                                      │
  │  POST /ajax/haki_resolve.php     │                                      │
  │  { character_id, haki_type,      │                                      │
  │    action: 'aprobar' }           │                                      │
  │ ──────────────────────────────>  │                                      │
  │                                  │                                      │
  │                                  │  1. Validar staff level              │
  │                                  │  2. Cargar game_haki_progress        │
  │                                  │     WHERE status='pendiente_subida'  │
  │                                  │                                      │
  │                                  │  3. UPDATE game_haki_progress        │
  │                                  │     SET nivel = nivel + 1,           │
  │                                  │         pp_reservados = 0,           │
  │                                  │         status = 'activo'            │
  │                                  │         [, unlocked_at = NOW()]      │
  │                                  │                                      │
  │                                  │  4. Notificar al usuario             │
  │                                  │     ('haki_upgrade_approved')        │
  │                                  │                                      │
  │                                  │  5. game_log_action                  │
  │                                  │     ('haki_resolve_approve')         │
  │                                  │                                      │
  │  JSON { ok: true, data }         │                                      │
  │ <────────────────────────────── │                                      │
```

### 7.4 La UPDATE SQL en Aprobación

```php
$unlocked_sql = ($haki['nivel'] == 0) ? ", unlocked_at = NOW()" : "";
$db->write_query("
    UPDATE {$prefix}game_haki_progress
    SET nivel = nivel + 1,
        pp_reservados = 0,
        status = 'activo'
        {$unlocked_sql}
    WHERE character_id = {$characterId} AND haki_type = '{$db->escape_string($hakiType)}'
");
```

Detalles clave:
- `nivel = nivel + 1` — incremento atómico. Si el nivel era 2, pasa a 3.
- `pp_reservados = 0` — los PP ya están descontados (se restaron en `haki_upgrade.php`). Al poner 0 se confirma el consumo.
- `status = 'activo'` — el personaje puede solicitar la siguiente subida.
- `unlocked_at` — solo se setea si el personaje pasó de nivel 0 a 1 (primera vez).

### 7.5 Flujo de Rechazo

```
Staff                              Servidor                                MySQL
  │                                  │                                      │
  │  POST /ajax/haki_resolve.php     │                                      │
  │  { character_id, haki_type,      │                                      │
  │    action: 'rechazar',           │                                      │
  │    motivo: 'Razon X' }           │                                      │
  │ ──────────────────────────────>  │                                      │
  │                                  │                                      │
  │                                  │  1. Validar staff level              │
  │                                  │  2. Cargar game_haki_progress        │
  │                                  │                                      │
  │                                  │  3. Devolver PP al personaje         │
  │                                  │     data_json.pp += pp_reservados    │
  │                                  │     UPDATE game_personajes           │
  │                                  │                                      │
  │                                  │  4. Resetear la fila                 │
  │                                  │     SET status='activo',             │
  │                                  │         pp_reservados = 0            │
  │                                  │                                      │
  │                                  │  5. Notificar con motivo             │
  │                                  │     ('haki_upgrade_rejected')        │
  │                                  │                                      │
  │                                  │  6. game_log_action                  │
  │                                  │     ('haki_resolve_reject')          │
  │                                  │     con motivo                       │
  │                                  │                                      │
  │  JSON { ok: true, data }         │                                      │
  │ <────────────────────────────── │                                      │
```

### 7.6 Devolución de PP en Rechazo

```php
$currentPp = (int)($data['pp'] ?? 0);
$data['pp'] = $currentPp + $reservedPp;
$dataJsonEsc = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
$db->write_query("UPDATE {$prefix}game_personajes SET data_json = '{$dataJsonEsc}' WHERE id = {$characterId}");

// Resetear fila en game_haki_progress
$db->write_query("
    UPDATE {$prefix}game_haki_progress
    SET status = 'activo',
        pp_reservados = 0
    WHERE character_id = {$characterId} AND haki_type = '{$db->escape_string($hakiType)}'
");
```

Nótese que en el rechazo **no se modifica `nivel`**. El nivel se queda donde estaba. Solo se devuelven los PP y se resetea el estado.

### 7.7 Registro de Auditoría

Ambos casos (aprobar y rechazar) llaman a `game_log_action()`:

```php
// Aprobación
game_log_action('haki_resolve_approve', [
    'staff_uid' => $uid,
    'character_id' => $characterId,
    'haki_type' => $hakiType,
    'new_level' => $targetLevel
]);

// Rechazo
game_log_action('haki_resolve_reject', [
    'staff_uid' => $uid,
    'character_id' => $characterId,
    'haki_type' => $hakiType,
    'refunded_pp' => $reservedPp,
    'reason' => $motivo
]);
```

Estos logs son vitales para auditorías y para resolver disputas sobre si una subida fue aprobada o rechazada.

---

## 8. Integración con Cartas

### 8.1 Cartas de Tipo Haki

Las cartas de Haki se crean con `card_type = 'haki'`. Su `effects_json` contiene:

```json
{
  "haki_type": "kenbunshoku",
  "haki_level": "obs_basico",
  "efecto": "Descripción del efecto narrativo de la técnica"
}
```

Campos específicos:
- `haki_type`: uno de `kenbunshoku`, `busoshoku`, `haoshoku`.
- `haki_level`: código interno del nivel requerido (ver tabla de mapeo).
- `efecto`: texto libre que describe la técnica.

### 8.2 Mapeo de Niveles (Level Map)

En `cards_assign.php:79-85`:

```php
$levelMap = [
    'obs_latente' => 1, 'arm_latente' => 1, 'rey_latente' => 1,
    'obs_basico' => 2, 'arm_basico' => 2, 'rey_basico' => 2,
    'obs_medio' => 3, 'arm_medio' => 3, 'rey_medio' => 3,
    'obs_avanzado' => 4, 'arm_interno' => 4, 'rey_avanzado' => 4,
    'obs_futuro' => 5, 'arm_supremo' => 5, 'rey_supremo' => 5,
];
```

Este mapa traduce el string narrativo del nivel (ej: `obs_basico`) a un entero (2). Luego se compara contra `game_haki_progress.nivel` del personaje.

### 8.3 Validación al Asignar Carta (`cards_assign.php:74-107`)

```php
if (($card['card_type'] ?? '') === 'haki') {
    $efCheck = json_decode($card['effects_json'] ?? '{}', true);
    $hakiType = (string)($efCheck['haki_type'] ?? 'busoshoku');
    $hakiLevel = (string)($efCheck['haki_level'] ?? 'basico');

    $levelMap = [ /* ... */ ];
    $minHakiLevel = $levelMap[$hakiLevel] ?? 5;

    $haki_q = $db->query("
        SELECT nivel FROM {$prefix}game_haki_progress 
        WHERE character_id = {$character_id} AND haki_type = '{$db->escape_string($hakiType)}' 
        LIMIT 1
    ");
    $haki_row = $db->fetch_array($haki_q);
    $playerHakiLevel = $haki_row ? (int)$haki_row['nivel'] : 0;

    if ($playerHakiLevel < $minHakiLevel) {
        $hakiName = $hakiType === 'kenbunshoku' ? 'Observación' : ($hakiType === 'busoshoku' ? 'Armamento' : 'Conquistador');
        // Error: nivel insuficiente
    }
}
```

**Regla de negocio:** Un personaje NO puede recibir una carta de Haki cuyo nivel requerido sea superior a su nivel actual en ese tipo. Por ejemplo, un personaje con Kenbunshoku nivel 2 (Básico) no puede recibir una carta `obs_medio` (nivel 3).

**Mecanismo de defensa:** Si se intenta asignar una carta de Haki de nivel superior, la asignación se rechaza con error 403 y mensaje descriptivo:

```json
{
  "ok": false,
  "error": {
    "code": 403,
    "message": "Nivel de Haki de Observación insuficiente. Requerido: Grado 3, Tienes: Grado 2."
  }
}
```

### 8.4 Implicaciones de Diseño

- **Gatekeeping narrativo:** Un personaje no puede saltarse niveles. Debe progresar de forma orgánica.
- **Incentivo a subir Haki:** Las cartas más poderosas (tier 4-5) requieren niveles altos de Haki, lo que motiva a los jugadores a invertir PP y acumular usos.
- **Haoshoku como privilegio:** Las cartas de Conquistador de nivel alto (`rey_avanzado`, `rey_supremo`) solo son accesibles para personajes que hayan despertado y progresado Haoshoku, lo que lo convierte en un marcador de estatus.

---

## 9. Visualización en la Ficha

### 9.1 Tab de Haki (`_tab_haki.php`)

El archivo `_tab_haki.php` (412 líneas) es la vista principal del progreso de Haki. Se organiza en tres secciones, una por tipo.

#### 9.1.1 Cabecera Común

```php
<!-- Progresión de Haki -->
<div class="haki-slots-bar">
    <div class="haki-slots-title">
        <i class="fas fa-bahai"></i> Progresión de Haki
    </div>
    <div class="haki-slots-pp">
        Nivel: <strong>Nivel <?= $char_nivel ?></strong> | Saldo: <strong><?= $char_pp ?> PP</strong>
    </div>
</div>
```

Muestra el nivel global del personaje y su saldo de PP (calculados con `CharacterProgression::syncLinajeBonusPp` y `game_get_character_nivel`).

#### 9.1.2 Card de Kenbunshoku

```php
<div class="haki-card haki-kenbunshoku">
    <div class="haki-card-header">
        <div class="haki-card-icon">
            <i class="fas fa-eye"></i>            <!-- Icono ojo -->
        </div>
        <div class="haki-card-title-group">
            <span class="haki-card-name">Kenbunshoku</span>
            <span class="haki-card-level">Haki de Observación</span>
        </div>
    </div>
    ...
</div>
```

Cada card de Haki incluye:
1. **Header con icono** (fas fa-eye, fa-shield-halved, fa-crown).
2. **Nivel actual** con nombre narrativo del label map.
3. **Barra de progreso de usos** (si nivel > 0).
4. **Lista de requisitos** para el siguiente nivel, con checkmarks visuales (verde `req-ok` / rojo `req-fail`).
5. **Botón de acción** (solicitar subida / pendiente / nivel máximo).

#### 9.1.3 Barra de Progreso de Usos

```php
<div class="haki-progress-section">
    <div class="haki-progress-labels">
        <span>Usos acumulados</span>
        <span><?= $obs_usos ?><?= ($obs_req) ? ' / ' . $obs_req['usos'] : '' ?></span>
    </div>
    <div class="haki-progress-bar-bg">
        <?php
        $percent = 100;
        if ($obs_req && $obs_req['usos'] > 0) {
            $percent = min(100, (int)floor(($obs_usos / $obs_req['usos']) * 100));
        }
        ?>
        <div class="haki-progress-bar-fill" data-width="<?= $percent ?>"></div>
    </div>
</div>
```

La barra se renderiza en HTML con `data-width` y el JS `peticion_haki.js:137-142` la setea dinámicamente:

```javascript
function initProgressBars() {
    document.querySelectorAll(".haki-progress-bar-fill").forEach(function (bar) {
        var w = bar.getAttribute("data-width") || "0";
        bar.style.width = w + "%";
    });
}
```

#### 9.1.4 Indicadores de Requisitos

```php
<div class="haki-req-item <?= $esp_ok ? 'req-ok' : 'req-fail' ?>">
    <span>Espíritu: <?= Game\Shared\StatScale::rankDisplayLabel($obs_req['esp']) ?></span>
    <i class="fas <?= $esp_ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
</div>
```

Cada requisito se muestra con un icono de check (cumplido) o X (no cumplido), y la clase CSS `req-ok`/`req-fail` colorea la fila.

#### 9.1.5 Estado Pendiente

Cuando el status es `pendiente_subida`:

```php
<div class="haki-pending-banner">
    <span><i class="fas fa-hourglass-half"></i> Petición en revisión</span>
    <span>Reservados: <?= (int)$haki_obs['pp_reservados'] ?> PP</span>
    <?php if ($active_char_is_staff): ?>
        <div class="haki-pending-actions">
            <button class="rpg-btn rpg-btn--primary" onclick="resolveHakiUpgrade(<?= $char_id ?>, 'kenbunshoku', 'aprobar')">Aprobar</button>
            <button class="rpg-btn" onclick="resolveHakiUpgrade(<?= $char_id ?>, 'kenbunshoku', 'rechazar')">Rechazar</button>
        </div>
    <?php endif; ?>
</div>
```

Si el usuario activo es staff, se muestran botones de aprobar/rechazar directamente en el tab de Haki del personaje. Esto permite al staff resolver rápidamente sin navegar a otra página.

### 9.2 Formulario de Solicitud en Gestión (`_tab_gestion.php`)

En el tab de gestión de la ficha, existe un formulario para solicitar cartas custom al staff, que incluye una sección para cartas de Haki:

```php
<div id="req_fields_haki" class="rpg-req-fields">
     <div class="form-group">
         <label class="rpg-form-label">Tipo de Haki</label>
         <select id="req_haki_type" class="textbox rpg-form-input">
             <option value="busoshoku">Busoshoku (Armamiento)</option>
             <option value="kenbunshoku">Kenbunshoku (Observación)</option>
             <option value="haoshoku">Haoshoku (Conquistador / Rey)</option>
         </select>
     </div>
     <div class="form-group">
         <label class="rpg-form-label">Nivel de Haki</label>
         <select id="req_haki_level" class="textbox rpg-form-input">
             <option value="despertado">Despertado</option>
             <option value="basico">Básico</option>
             <option value="medio">Medio</option>
             <option value="avanzado">Avanzado</option>
             <option value="maestro">Maestro</option>
         </select>
     </div>
     <div class="form-group">
         <label class="rpg-form-label">Efecto</label>
         <textarea id="req_haki_efecto" class="textbox rpg-form-input rpg-form-input--resize"></textarea>
     </div>
</div>
```

Este formulario permite a los jugadores proponer nuevas cartas de Haki al staff para su creación. El jugador especifica tipo, nivel narrativo, y el efecto deseado.

### 9.3 Haki en Linaje (`_tab_linaje.php`)

La sección de linaje asigna iconos específicos a bonos relacionados con Haki:

```php
elseif (strpos($id, 'g_haki_obs') === 0)  { $icon = 'fa-eye';        $iconColor = '#C62828'; }
elseif (strpos($id, 'g_haki_arm') === 0)  { $icon = 'fa-shield-alt'; $iconColor = '#6b7280'; }
elseif (strpos($id, 'g_haki_conq') === 0) { $icon = 'fa-crown';      $iconColor = '#db2777'; }
```

Los bonos de linaje que afectan Haki (ej: percepción, endurecimiento natural, voluntad innata) usan estos iconos y colores para mantener consistencia visual con el tab de Haki.

### 9.4 Iconografía Consistente

| Tipo | Icono | Color |
|------|-------|-------|
| Kenbunshoku | `fa-eye` | Rojo (#C62828) |
| Busoshoku | `fa-shield-halved` / `fa-shield-alt` | Gris (#6b7280) |
| Haoshoku | `fa-crown` | Rosa (#db2777) |

Esta consistencia se mantiene en todos los tabs: Haki, Linaje, Gestión, y herramientas de staff.

---

## 10. Herramientas de Staff

### 10.1 Lista de Peticiones Pendientes (`haki_pending_requests.php`)

Endpoint AJAX que devuelve todas las filas de `game_haki_progress` con `status = 'pendiente_subida'`:

```php
$q = $db->query("
    SELECT hp.id, hp.character_id, hp.haki_type, hp.nivel, hp.status, hp.pp_reservados, hp.updated_at,
           p.name AS character_name, p.avatar AS character_avatar
    FROM {$prefix}game_haki_progress hp
    JOIN {$prefix}game_personajes p ON hp.character_id = p.id
    WHERE hp.status = 'pendiente_subida'
    ORDER BY hp.updated_at ASC
");
```

La respuesta incluye:
- `character_id`, `character_name`, `character_avatar` — para identificar al personaje.
- `haki_type` — el tipo de Haki solicitado.
- `nivel_actual` y `nivel_siguiente` — para ver el progreso.
- `pp_reservados` — cuántos PP están en reserva.
- `date` — cuándo se hizo la solicitud (formateada como dd/mm/YYYY HH:ii).

Se ordena por `updated_at ASC` para que las más antiguas aparezcan primero (FIFO).

**Ejemplo de respuesta:**
```json
{
  "ok": true,
  "data": [
    {
      "id": 1,
      "character_id": 42,
      "character_name": "Monkey D. Luffy",
      "character_avatar": "https://...",
      "haki_type": "busoshoku",
      "nivel_actual": 2,
      "nivel_siguiente": 3,
      "pp_reservados": 700,
      "date": "15/06/2026 14:30"
    }
  ]
}
```

### 10.2 Creación de Cartas de Haki por Staff (`cartas_staff.php`)

El panel de staff para crear/editar cartas incluye una sección específica para Haki:

```php
<div id="fields-haki" class="rpg-staff-field-section">
    <div>
        <label class="rpg-form-label">Tipo de Haki</label>
        <select id="haki_type" class="textbox rpg-input-full">
            <option value="busoshoku">Busoshoku (Armamiento)</option>
            <option value="kenbunshoku">Kenbunshoku (Observación)</option>
            <option value="haoshoku">Haoshoku (Conquistador / Rey)</option>
        </select>
    </div>
    <div>
        <label class="rpg-form-label">Nivel de Haki</label>
        <select id="haki_level" class="textbox rpg-input-full">
            <option value="despertado">Despertado</option>
            <option value="basico">Básico</option>
            <option value="medio">Medio</option>
            <option value="avanzado">Avanzado</option>
            <option value="maestro">Maestro</option>
        </select>
    </div>
    <div class="rpg-grid-full">
        <label class="rpg-form-label">Efecto</label>
        <textarea id="haki_efecto" class="textbox rpg-input-full" rows="3" placeholder="Detalla el efecto de la habilidad de Haki..."></textarea>
    </div>
</div>
```

Al guardar la carta, el staff debe:
1. Seleccionar el tipo de Haki (kenbunshoku, busoshoku, haoshoku).
2. Asignar el nivel requerido (determinará qué nivel de Haki necesita el jugador para usar la carta).
3. Describir el efecto narrativo/mecánico de la técnica.

**Buena práctica:** El nivel requerido debe corresponder al poder de la técnica. Una carta "Visión del Futuro Inmediato" requeriría `obs_avanzado` (nivel 4), mientras que "Detección de Presencias" podría ser `obs_latente` (nivel 1).

### 10.3 Resolución Directa desde la Ficha

Como se vio en [9.1.5](#915-estado-pendiente), si el usuario activo es staff, los botones de aprobar/rechazar aparecen directamente en el tab de Haki del personaje que tiene una solicitud pendiente. Esto permite un flujo rápido:

1. El staff ve una solicitud en la lista de pendientes.
2. Abre la ficha del personaje.
3. En el tab de Haki, ve el banner `Petición en revisión`.
4. Hace clic en **Aprobar** o **Rechazar**.
5. Si rechaza, un prompt pide el motivo.

---

## 11. Filosofía de Diseño

### 11.1 ¿Por qué 3 Tipos con Diferentes Métodos de Adquisición?

**Kenbunshoku y Busoshoku = Accesibles a Todos:**
- Cualquier personaje puede desarrollar percepción espiritual y endurecimiento con entrenamiento. Esto refleja el lore de One Piece, donde marines, piratas y revolucionarios entrenan estos dos tipos.
- El mecanismo de PP + subidas aprobadas por staff simula el "entrenamiento con sensei" — el jugador invierte recursos y el staff valida que el progreso tenga sentido narrativo.

**Haoshoku = Exclusivo por Diseño:**
- En el lore, 1 de cada 1.000.000 de personas lo posee. Es un rasgo de nacimiento, no entrenable.
- La tirada de despertar con probabilidad < 100% asegura que no sea automático.
- Los requisitos altos (ESP A-, Nivel 4, 500 PP) garantizan que solo personajes avanzados puedan intentarlo.
- Que el jugador PUEDA ejecutar la tirada (no solo el staff) evita cuellos de botella, pero el coste de 500 PP (que se consume incluso si falla) introduce un elemento de riesgo estratégico.

### 11.2 ¿Por qué la Migración de Disciplinas a `game_haki_progress`?

El sistema anterior (`game_character_disciplinas`) no podía modelar adecuadamente el Haki:

| Problema | Solución en `game_haki_progress` |
|----------|----------------------------------|
| No había tracking de usos | Columna `usos_total` con INT |
| No había estados intermedios | ENUM('activo','pendiente_subida') |
| Los PP se consumían inmediatamente | `pp_reservados` permite devolución |
| Haoshoku no encajaba en el modelo de disciplinas | Tirada especial separada |
| No había distinción entre tipos para cards | `haki_type` en effects_json |

Además, el nuevo sistema desacopla el Haki del sistema de combate. Un personaje puede tener Haki nivel 5 y no tener disciplinas de combate — y viceversa. Son sistemas paralelos.

### 11.3 ¿Por qué el Modelo de PP Reservados (Anti-Frustración)?

El diseño de reservación de PP resuelve un problema específico: **¿qué pasa si el staff rechaza una subida?**

En un sistema donde el PP se consume inmediatamente, un rechazo significa que el jugador perdió PP y no obtuvo nada. Esto genera frustración y desincentiva el uso del sistema.

**Solución:** El PP se resta del saldo visible (para que el jugador no pueda gastarlo en otra cosa mientras espera), pero se marca como `pp_reservados`. Si el staff rechaza, el PP se devuelve íntegramente.

**Trade-off:** El jugador no puede usar ese PP para otras compras mientras la solicitud está pendiente. Esto es intencional — evita que el jugador gaste el PP en otra cosa y luego no tenga saldo si la solicitud se aprueba (lo que dejaría el sistema en estado inconsistente).

**Flujo financiero completo:**
1. Saldo: 1000 PP. Coste: 300 PP. → Saldo: 700 PP. `pp_reservados`: 300.
2. **Aprueba:** `pp_reservados` = 0. Coste real: 300 PP. Saldo: 700 PP.
3. **Rechaza:** `pp_reservados` = 0. Saldo: 700 + 300 = 1000 PP. Coste real: 0 PP.

### 11.4 ¿Por qué los Niveles de Haki Gatean el Acceso a Cartas?

Este diseño cumple múltiples objetivos:

1. **Coherencia narrativa:** Un personaje con Haki de Observación latente no debería poder usar técnicas de visión del futuro. La carta representa una técnica que requiere cierto dominio del Haki.
2. **Progresión significativa:** Cada nivel de Haki desbloquea nuevas posibilidades de cartas. Esto da a los jugadores un objetivo claro.
3. **Protección contra powergaming:** Evita que un personaje acumule cartas poderosas de Haki sin haber "pagado" el coste narrativo de desarrollarlo.
4. **Valor a las cartas:** Una carta de Haoshoku nivel 5 (`rey_supremo`) es intrínsecamente valiosa porque pocos personajes tendrán el nivel requerido para usarla. Esto las convierte en botín deseable.

---

## 12. Consejos para Jugadores

### 12.1 ¿Qué Haki Priorizar?

**Para personajes nuevos (Nivel 1-2):**
- **Prioriza Kenbunshoku o Busoshoku a nivel 1.** Cuestan solo 100 PP y ESP 2. Te dan una base sobre la que acumular usos mientras avanzas.
- **Elige según tu arquetipo:**
  - **Luchadores cuerpo a cuerpo:** Busoshoku primero. El endurecimiento es esencial para tanquear daño y enfrentar Logias.
  - **Estrategas / exploradores:** Kenbunshoku primero. Percepción y detección abren opciones narrativas y tácticas.
  - **Personajes de carisma / liderazgo:** Apunta a Haoshoku a largo plazo. Pero primero necesitas ESP A- (rango 4) y Nivel 4.

**Para personajes intermedios (Nivel 3-4):**
- **Sube Kenbunshoku y Busoshoku a nivel 3.** Es el punto dulce: coste moderado (700 PP cada uno), usos razonables (15), y desbloqueas capacidades significativas.
- **Acumula PP para Haoshoku.** Necesitarás 500 PP solo para el intento, y ESP mínimo 4.

**Para personajes avanzados (Nivel 5+):**
- **Decide si quieres Haoshoku.** Si fallas la tirada, pierdes 500 PP. No lo intentes a menos que estés dispuesto a asumir el riesgo.
- **Especialízate:** Un nivel 5 en un solo tipo de Haki es más impactante que nivel 2-3 en los tres. El coste acumulado de llevar los tres a nivel 5 es de 20.400 PP (5.600 + 5.600 + 9.200).

### 12.2 Cómo Roleplayear el Desarrollo de Haki

**Acumular usos:**
- Cada vez que uses una carta de Haki en un post, el sistema contabiliza un uso en `usos_total`.
- Para progresar rápido, involucra tu Haki en posts cotidianos — no solo en combate. Usa Kenbunshoku para detectar emociones en una conversación, o Busoshoku para romper cadenas.
- El mínimo de usos para nivel 3 es 15. Eso son ~15 posts usando Haki de forma significativa. No es una cifra absurda si usas tu Haki con regularidad.

**Solicitar subidas con narrativa:**
- El staff evalúa las solicitudes. Si tu personaje ha estado entrenando Haki activamente en sus posts, la aprobación es más probable.
- No solicites una subida inmediatamente después de cumplir los requisitos numéricos. Dale contexto narrativo al salto de poder.

**Para Haoshoku:**
- La tirada no mide "merecimiento" — mide si el personaje tiene la voluntad innata. Si fallas, el personaje simplemente no es un Conquistador. Acepta el resultado y juega con ello.
- Si obtienes Grado III directamente (91+), es porque el personaje tiene una voluntad excepcionalmente poderosa. Rolea ese momento como un despertar cataclísmico.

### 12.3 Estrategias para el Despertar de Haoshoku

1. **Maximiza tu ESP efectivo.** Cada punto extra sobre 4 da +5 a la tirada. Un personaje con razas que bonifican ESP (ej: afinidad espiritual) tiene ventaja significativa.
2. **Ahorra 500 PP.** Y no los gastes en otra cosa. Necesitas tener el saldo disponible.
3. **Decide quién tira.** Puedes tirarlo tú mismo (el dueño) o pedirle al staff que lo haga. La mecánica es idéntica.
4. **Prepárate emocionalmente para el fallo.** 40% de probabilidad de fallar con ESP 4. Incluso con ESP 7, es 25%. No es automático.
5. **Si fallas, no pierdes el progreso.** El nivel de Haoshoku se queda en 0, pero el personaje sigue existiendo. Puedes volver a intentarlo cuando tengas otros 500 PP.

### 12.4 Costes a Largo Plazo

| Estrategia | Coste PP | Usos Requeridos | Nivel PJ |
|-----------|---------|-----------------|----------|
| Un tipo a nivel 5 | 5.600 | 60 | 5 |
| Dos tipos a nivel 5 | 11.200 | 120 | 5 |
| Los 3 tipos a nivel 5 | 20.400 | 190 | 6 |
| Haoshoku intento (fallo) | 500 (perdidos) | - | 4 |
| Haoshoku + 2 tipos nivel 5 (con éxito) | 20.900 | 190 | 6 |

---

## 13. Consejos para Staff

### 13.1 Evaluación de Solicitudes de Subida

**Criterios generales:**
- **Narrativa reciente:** ¿El personaje ha usado el Haki en sus posts recientes? Revisar el historial del PJ.
- **Progresión orgánica:** ¿La subida corresponde al momento narrativo del personaje? Subir de nivel 1 a 2 tras 5 usos es razonable. Subir de nivel 3 a 4 sin usar el Haki en semanas es cuestionable.
- **Coherencia con el lore:** Un personaje Kenbunshoku nivel 4 (visión parcial del futuro) es un observador formidable. Asegúrate de que el jugador entiende el poder que eso conlleva.

**Kenbunshoku vs Busoshoku:**
- **Kenbunshoku:** Evalúa si el personaje ha demostrado perceptividad en sus posts. Un personaje que ignora pistas o cae en emboscadas constantemente no debería tener observación avanzada.
- **Busoshoku:** Evalúa si el personaje ha participado en combates donde el endurecimiento era relevante. Un personaje pacífico puede tener Busoshoku bajo, pero uno que se enfrenta a Logias necesita niveles altos.

**Haoshoku (nivel 2+):**
- Los requisitos son más altos (ESP, nivel PJ, usos, PP). Si el jugador los cumple, el desbloqueo del nivel es casi automático — el gatekeeping ya ocurrió en la tirada de despertar.
- Usa el criterio narrativo: ¿ha demostrado voluntad de "rey"? ¿Ha liderado, inspirado, o impuesto su voluntad en momentos clave?

### 13.2 Criterios para Otorgar Haoshoku

**No hay criterios subjetivos — la tirada decide:**
- El sistema de tirada es el mecanismo principal. No deberías "regalar" Haoshoku por fuera del sistema.
- Si un jugador pide Haoshoku sin hacer la tirada, dirígelo al botón "Lanzar Despertar" en su ficha.
- La única excepción sería un evento de staff narrativo de alto nivel, pero incluso entonces, usar la tirada como mecánica subyacente mantiene consistencia.

**¿Puede el staff hacer la tirada por el jugador?**
- Sí. El endpoint `haki_conquistador_roll.php` permite que staff o el dueño ejecuten la tirada (línea 51-56):
  ```php
  $isOwner = ((int)($targetCharacter['user_id'] ?? 0) === $uid);
  $isStaff = ($staff_level >= 2);
  if (!$isOwner && !$isStaff) {
      GameAjax::json(false, null, ['code' => 403, 'message' => '...'], 403);
  }
  ```
- Esto es útil para eventos de foro donde el staff quiere dramatizar el despertar.

### 13.3 Balanceo de Cartas de Haki

**Al crear cartas de Haki:**

1. **Asigna el nivel correcto:**
   - `despertado` (nivel 1): Técnicas básicas — sentir presencias, endurecer puños.
   - `basico` (nivel 2): Técnicas conscientes — escanear área, endurecer armas.
   - `medio` (nivel 3): Técnicas avanzadas — visión parcial del futuro, Ryou.
   - `avanzado` (nivel 4): Técnicas de élite — visión del futuro completa, emisión.
   - `maestro` (nivel 5): Técnicas supremas — futuro extendido, armadura total.

2. **Relaciona poder con nivel:** Una carta de nivel 5 debería ser significativamente más poderosa que una de nivel 1. Si no lo es, el nivel está mal asignado.

3. **Haoshoku requiere cuidado extra:** Las cartas de Conquistador de nivel 4+ son exclusivas y muy poderosas. No las crees en abundancia.

4. **Prueba el gatekeeping:** Antes de asignar una carta a un personaje, verifica que su nivel de Haki lo permita. El sistema `cards_assign.php` lo valida automáticamente, pero como staff deberías saber qué cartas pueden usar tus jugadores.

### 13.4 Revisión de Peticiones Pendientes

**Frecuencia recomendada:** Revisa `haki_pending_requests.php` al menos una vez por semana. Las peticiones quedan en "limbo" hasta que se resuelven.

**Flujo de revisión:**
1. Abre `zona_staff_peticiones.php` (que carga `haki_pending_requests.php` via JS).
2. Revisa cada personaje:
   - Abre su ficha.
   - Ve al tab de Haki.
   - Evalúa los requisitos.
   - Si están cumplidos y la narrativa es coherente: **Aprobar**.
   - Si falta narrativa o algo no cuadra: **Rechazar** con motivo claro.
3. Comunica el motivo de rechazo de forma constructiva. Ejemplo:
   > "Has cumplido los requisitos numéricos, pero tu personaje no ha usado Haki de Armamento en sus últimos 10 posts. Entrena su uso en combate y vuelve a solicitar."

**¿Qué hacer con solicitudes que el jugador ya no quiere?**
- El jugador no puede cancelar la solicitud por sí mismo (no hay endpoint de cancelación).
- Como staff, puedes rechazar la solicitud sin motivo para devolverle los PP.

### 13.5 Herramientas de Depuración

Si un jugador reporta errores con el sistema de Haki:

1. **Verificar la fila en `game_haki_progress`:**
   ```sql
   SELECT * FROM game_haki_progress WHERE character_id = X;
   ```

2. **Verificar los PP reservados:**
   ```sql
   SELECT pp_reservados, status FROM game_haki_progress WHERE character_id = X AND haki_type = 'kenbunshoku';
   ```

3. **Verificar el saldo de PP en data_json:**
   ```sql
   SELECT data_json FROM game_personajes WHERE id = X;
   ```
   Luego extraer `data_json.pp` y `data_json.pp_base`.

4. **Consistencia entre tablas:** Si un jugador tiene PP negativos después de un rechazo (por ejemplo, si se devolvieron PP incorrectamente), puede deberse a:
   - Una modificación manual de la base de datos.
   - Un error durante la transacción (raro, pero posible si la conexión se interrumpió entre la resta de PP y el INSERT).

---

## APÉNDICE A: Referencia Rápida de Endpoints

| Endpoint | Archivo | Método | Función |
|----------|---------|--------|---------|
| Solicitar subida de Haki | `haki_upgrade.php` | POST | Reserva PP y cambia status a pendiente |
| Resolver subida (staff) | `haki_resolve.php` | POST | Aprueba o rechaza la solicitud pendiente |
| Tirada de Conquistador | `haki_conquistador_roll.php` | POST | Ejecuta la tirada de despertar |
| Listar pendientes (staff) | `haki_pending_requests.php` | GET | Lista todas las peticiones sin resolver |
| Asignar carta (con val. Haki) | `cards_assign.php` | POST | Asigna carta, validando nivel de Haki |
| Ver progreso de Haki | `_tab_haki.php` | GET (vista) | Muestra el estado de los 3 tipos en la ficha |

---

## APÉNDICE B: Archivos Relacionados

| Ruta | Propósito |
|------|-----------|
| `back/forum/game/sql/migrate_haki_progress.php` | Migración SQL — creación de la tabla |
| `back/forum/game/ajax/haki_upgrade.php` | AJAX — solicitar subida de nivel |
| `back/forum/game/ajax/haki_resolve.php` | AJAX — staff aprueba/rechaza |
| `back/forum/game/ajax/haki_conquistador_roll.php` | AJAX — tirada de despertar |
| `back/forum/game/ajax/haki_pending_requests.php` | AJAX — lista de pendientes |
| `back/forum/game/ajax/cards_assign.php` | AJAX — asignación con validación de Haki |
| `back/forum/game/views/personaje/_tab_haki.php` | Vista — tab de Haki en la ficha |
| `back/forum/game/views/personaje/_tab_gestion.php` | Vista — formulario de solicitud de cartas custom |
| `back/forum/game/views/personaje/_tab_linaje.php` | Vista — iconos de Haki en linaje |
| `back/forum/game/public/cartas_staff.php` | Vista — creación de cartas de Haki por staff |
| `back/forum/game/sql/competencias_v2_seed_data.php` | Seed — descripción de `haki_conquistador` grados |
| `back/forum/jscripts/game/peticion_haki.js` | JS — funciones de Haki (modular) |
| `back/forum/jscripts/game/personaje_page.js` | JS — funciones de Haki (legacy) |
| `back/forum/jscripts/game/zona_staff_peticiones.js` | JS — carga pendientes en panel staff |
