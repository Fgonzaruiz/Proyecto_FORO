# SISTEMA DE PERSONAJES (FICHA) — GUÍA COMPLETA

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 1
> **Propósito:** Documentar exhaustivamente el subsistema de personajes: modelo de datos, servicios, vistas, JS, AJAX, flujos de creación/revisión/edición, progresión, linaje, cronología, inventario, gestión, reglas de negocio — y **por qué** cada decisión de diseño se tomó así, cómo impacta en el RPG, y consejos para jugadores y staff.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [Modelo de Datos — Tabla `game_personajes`](#2-modelo-de-datos)
3. [Tablas Auxiliares](#3-tablas-auxiliares)
4. [Estructura de JSON Columns](#4-estructura-de-json-columns)
5. [Flujo Completo de Creación](#5-flujo-completo-de-creación)
6. [Wizard de Creación — Paso a Paso](#6-wizard-de-creación-paso-a-paso)
7. [Sistema de Revisión y Aprobación](#7-sistema-de-revisión-y-aprobación)
8. [Ficha de Personaje — Vista Pública](#8-ficha-de-personaje)
9. [Sistema de Gestión (PP, Stats, Deck)](#9-sistema-de-gestión)
10. [Sistema de Cronología (Diario y Relaciones)](#10-sistema-de-cronología)
11. [Sistema de Linaje (Factor Linaje)](#11-sistema-de-linaje)
12. [Servicios PHP — Capa de Aplicación](#12-servicios-php)
13. [Repositorio y Acceso a Datos](#13-repositorio)
14. [AJAX Endpoints](#14-ajax-endpoints)
15. [JavaScript — Frontend Lógico](#15-javascript)
16. [Templates y Vistas PHP](#16-templates-y-vistas)
17. [Plugin MyBB — Integración con Posts](#17-plugin-mybb)
18. [Permisos y Seguridad](#18-permisos-y-seguridad)
19. [Contratos API (OpenAPI)](#19-contratos-api)
20. [Flujo de Datos Completo](#20-flujo-de-datos-completo)
21. [Estados y Transiciones del Personaje](#21-estados-y-transiciones)
22. [Filosofía de Diseño del Sistema de Personajes](#22-filosofía-de-diseño)
23. [Consejos para Jugadores](#23-consejos-para-jugadores)
24. [Consejos para Staff](#24-consejos-para-staff)
25. [Guía de Troubleshooting](#25-guía-de-troubleshooting)

---

## 1. Arquitectura General

### 1.1 Capas del Subsistema

```
┌─────────────────────────────────────────────────────────────┐
│                    CLIENTE (Navegador)                       │
│  ┌──────────────┐  ┌──────────────────┐  ┌───────────────┐  │
│  │ crear_perso- │  │ personaje.php    │  │ mis_perso-    │  │
│  │ naje.js      │  │ personaje_page.js│  │ najes.js      │  │
│  │ (wizard)     │  │ personaje_inven- │  │ (card grid)   │  │
│  │              │  │ tory.js          │  │               │  │
│  └──────┬───────┘  └───────┬──────────┘  └───────┬───────┘  │
│         │                  │                      │          │
│         ▼                  ▼                      ▼          │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │              AJAX (game/ajax/*.php)                     │ │
│  │  save_personaje | aprobar_personaje | set_active_pj     │ │
│  │  update_cronologia | save_avatar_sig | my_personajes    │ │
│  └──────────────────────┬──────────────────────────────────┘ │
└─────────────────────────┼────────────────────────────────────┘
                          │ HTTP POST/GET + JSON
┌─────────────────────────┼────────────────────────────────────┐
│  ┌──────────────────────▼──────────────────────────────────┐ │
│  │              PHP — CAPA DE APLICACIÓN                    │ │
│  │  Services: CharacterSheetLoader, CharacterSaveService,   │ │
│  │  CharacterProgression, LinajeValidator,                  │ │
│  │  NotificationService, DirectMessageService               │ │
│  │  Infrastructure: PersonajeRepository, StatScale          │ │
│  └─────────────────────────────────────────────────────────┘ │
│                              │                                │
│                              ▼                                │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │           MySQL (MyBB + tablas game_*)                   │ │
│  │  game_personajes + 15+ tablas auxiliares                 │ │
│  └─────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### 1.2 Filosofía de la Arquitectura

**¿Por qué PHP + MySQL sin backend externo?**
- **Simplicidad operativa:** MyBB ya corre PHP + MySQL. Añadir un backend Node.js o Python para las mecánicas RPG habría duplicado la infraestructura y creado un punto de fallo adicional.
- **Latencia cero:** La DB está en el mismo servidor que el foro. Leer/escribir stats de personaje es una consulta SQL directa, no una llamada HTTP a un API externo.
- **Transaccionalidad:** Las operaciones de personaje (crear, editar, aprobar) se benefician de las transacciones de MySQL. Si algo falla a medio camino, todo se revierte.

**¿Por qué JSON columns (`stats_json`, `data_json`, `cronologia_json`) en lugar de tablas normalizadas?**
- **Flexibilidad:** El sistema de personajes evoluciona. Añadir un campo nuevo al `data_json` no requiere ALTER TABLE ni migraciones de esquema. Se añade una key al JSON y listo.
- **Rendimiento:** Un personaje se carga con UNA consulta SELECT. No hay 7 JOINs para traer stats + bio + progresión + linaje + relaciones.
- **Contrapartida:** No puedes hacer queries tipo "SELECT * FROM personajes WHERE age > 20". Pero en un foro RPG, ese tipo de consultas no existen. Siempre cargas el personaje COMPLETO.

**¿Por qué separar en 3 JSON columns en lugar de 1?**
- `stats_json`: Datos puramente mecánicos (los 7 stats). Se actualiza con frecuencia (compras de stat). Separarlo permite actualizarlo sin tocar el resto.
- `data_json`: Datos de progresión + wizard. Cambia con moderación (al crear, al aprobar, al comprar stats).
- `cronologia_json`: Datos narrativos (diario, relaciones). Puede crecer mucho. Separado para no contaminar las cargas mecánicas.

### 1.3 Impacto RPG

| Decisión arquitectónica | Lo que significa para el juego |
|------------------------|-------------------------------|
| Sin backend externo | El foro puede funcionar offline, en localhost, sin dependencias de red |
| JSON columns | El staff puede añadir campos narrativos nuevos sin tocar código |
| Plugin hooks MyBB | Los posts existentes del foro actualizan automáticamente los contadores del PJ |

### 1.4 Principios de Diseño

1. **Datos y mecánicas en MySQL** (D001): No hay backend externo ni APIs de red.
2. **Validación servidor-side**: Toda escritura se revalida en PHP. El JS solo mejora UX.
3. **JSON columns**: Datos semiestructurados normalizados en runtime.
4. **FORBIDDEN_DATA_KEYS**: Protección contra sobrescritura de campos del sistema.
5. **Plugin hooks**: `postnum`/`threadnum` sincronizados con la actividad real del foro.

---

## 2. Modelo de Datos — Tabla `game_personajes`

### 2.1 Definición SQL Completa

```sql
CREATE TABLE mybb_game_personajes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT DEFAULT NULL,
    name            VARCHAR(255) NOT NULL,
    race            VARCHAR(50) NOT NULL,
    race_name       VARCHAR(100) NOT NULL,
    occupation      VARCHAR(50) NOT NULL,
    occupation_name VARCHAR(100) NOT NULL,
    `desc`          TEXT NOT NULL,
    details         TEXT NOT NULL,
    rango           VARCHAR(100) NOT NULL,
    tripulacion     VARCHAR(255) NOT NULL,
    recompensa      VARCHAR(100) NOT NULL,
    banner          VARCHAR(255) NOT NULL,
    avatar          VARCHAR(500) NOT NULL DEFAULT '',
    firma           TEXT DEFAULT NULL,
    is_staff        TINYINT(1) NOT NULL DEFAULT 0,
    staff_level     TINYINT(1) NOT NULL DEFAULT 0,
    is_npc          TINYINT(1) NOT NULL DEFAULT 0,
    is_narrator     TINYINT(1) NOT NULL DEFAULT 0,
    status          VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    postnum         INT NOT NULL DEFAULT 0,
    threadnum       INT NOT NULL DEFAULT 0,
    data_json       LONGTEXT,
    stats_json      LONGTEXT,
    faction         VARCHAR(100) DEFAULT '',
    approved        TINYINT(1) DEFAULT 0,
    cronologia_json LONGTEXT,
    berries         INT NOT NULL DEFAULT 0,
    puntos_destino  INT NOT NULL DEFAULT 0,
    tripulacion_id  INT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2 Campos — Descripción Detallada

#### `id` — Identificador único
- Autoincremental. Clave primaria de todo el sistema RPG.
- Referenciado como `character_id` en game_character_cards, game_post_characters, game_thread_pj_state, game_npc_assignments, game_personajes_revisiones, game_busquedas, game_card_requests, game_admin_requests, etc.

#### `user_id` — Dueño del personaje
- FK lógica a `mybb_users.uid` (sin constraint formal para permitir NPCs huérfanos).
- Controlado por `game_user_config.max_slots` (default: 1 slot por usuario).

#### `name` — Nombre del personaje
- Único a nivel visual sin UNIQUE constraint formal (la convención comunitaria lo regula).
- Se muestra en toda la interfaz: fichas, posts, biblioteca, relaciones.

#### `race` / `race_name` — Raza
- `race`: slug (`humano`, `gyojin`, `mink`, `gigante`, `tontatta`, `buccaner`, `lunarian`, `skypean`, `oni`, `sirena`). Para híbridos: `hibrido`.
- `race_name`: display (`Humano`, `Híbrido (Dominante / Recesiva)`).

#### `occupation` / `occupation_name` — Oficio
- `occupation`: slug (`medico`, `cocinero`). `occupation_name`: display.
- Se sincroniza con `game_character_oficios` en creación/edición.

#### `desc` / `details` — Texto narrativo
- `desc`: apariencia física (del wizard `physique`). Default: `"Sin registrar."`.
- `details`: psicología + extras concatenados. Default: `"Sin registrar."`.

#### `rango` — Rango de facción
- Texto libre. El wizard asigna automáticamente según facción:
  - Pirata → Grumete · Marine → Raso · Revolucionario → Iniciado
  - Gobierno → Agente · Cazador → Sin Estrella · Civil → Ciudadano

#### `faction` — Facción
- Determina el color del badge del nombre en la ficha (clase CSS `pj-sidebar-name--{slug}`).
- Slugs: `pirata`, `marine`, `revolucionario`, `gobierno`, `cazador`, `civil`, `staff`.

#### `status` — Estados del personaje

| Valor | Significado | Editable | Visible en biblioteca |
|-------|-------------|----------|----------------------|
| `pendiente` | Creada, esperando revisión | Sí | No |
| `revision` | Staff revisando | Sí | No |
| `aprobada` | Listo para rolear | No (solo avatar/firma) | Sí |
| `rechazada` | Necesita correcciones (se borra automáticamente) | No | No |
| `muerto` | Fallecido (staff) | No | Sí (badge especial) |

#### `is_staff` / `staff_level`
- `is_staff = 1`: poder de moderación.
- `staff_level`: 1=mod, 2=admin, 3=superadmin.
- Determinante para permisos de edición y aprobación.

#### `is_npc` / `is_narrator`
- `is_npc = 1`: personaje controlado por staff/narradores.
- `is_narrator = 1`: personaje narrador con acceso a gestión de NPCs asignados.
- Los NPCs se crean directamente como `aprobada`, sin pasar por revisión.

#### `berries` / `puntos_destino`
- `berries`: moneda económica del foro (gastos en tienda).
- `puntos_destino`: moneda de mérito (premios especiales).

### 2.3 Filosofía del Modelo de Datos

**¿Por qué una tabla tan ancha (30+ columnas)?**
- Porque el personaje es la entidad central del foro. Todo el resto (cartas, posts, misiones, tripulaciones) gira en torno a él. Tener los datos en una sola tabla evita JOINs costosos en cada carga de ficha.

**¿Por qué `status` como VARCHAR y no ENUM?**
- Flexibilidad. Si en el futuro se necesita un estado "congelado" o "encarcelado", se añade sin ALTER TABLE.

**¿Por qué `approved` (TINYINT) como columna legacy separada de `status`?**
- Porque durante la migración del sistema antiguo al nuevo, muchas queries buscaban `approved = 1`. Mantener la columna evitó reescribir 30+ archivos. Se mantiene sincronizada: `approved = 1` cuando `status = 'aprobada'`.

**¿Por qué la columna `firma` fue añadida en migración posterior?**
- Porque el requisito de firmas personalizadas por personaje surgió después del lanzamiento inicial. La arquitectura de migraciones permite añadir columnas sin downtime.

### 2.4 Impacto RPG

| Campo | Lo que permite en el juego |
|-------|---------------------------|
| `berries` | Economía entre jugadores, tienda, recompensas |
| `puntos_destino` | Premios especiales, contenido exclusivo |
| `postnum` / `threadnum` | Medir actividad, requisitos para desbloqueos |
| `firma` | Personalización, cohesión de marca del personaje |

---

## 3. Tablas Auxiliares

### 3.1 `game_user_config` — Configuración por usuario

```sql
CREATE TABLE mybb_game_user_config (
    user_id     INT PRIMARY KEY,
    max_slots   INT NOT NULL DEFAULT 1,
    slots_used  INT NOT NULL DEFAULT 0,
    active_pj_id INT DEFAULT NULL,
    is_narrator TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Propósito:** Controlar cuántos personajes tiene cada usuario y cuál está activo.

**Filosofía:**
- **1 slot por defecto** — El foro es de rol narrativo, no de colección de personajes. El jugador debe comprometerse con UN personaje principal.
- **Slots ampliables por staff** — Como recompensa (Puntos Destino) o para jugadores veteranos.
- **Solo un PJ activo** — Evita que un jugador "alterne" entre personajes en el mismo hilo o postee con varios a la vez.

### 3.2 `game_personajes_revisiones` — Historial de revisiones

```sql
CREATE TABLE mybb_game_personajes_revisiones (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    personaje_id    INT NOT NULL,
    staff_user_id   INT NOT NULL,
    staff_char_id   INT NOT NULL,
    status_anterior VARCHAR(20) NOT NULL DEFAULT '',
    status_nuevo    VARCHAR(20) NOT NULL DEFAULT '',
    mensaje         TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_personaje (personaje_id),
    INDEX idx_staff (staff_user_id)
);
```

**Filosofía:** Cada cambio de estado queda registrado. Si un jugador reclama "me rechazaron sin motivo", el staff tiene el historial completo. También permite auditoría: "¿quién aprobó este personaje con stats tan altos?"

### 3.3 `game_npc_assignments` — Asignación de NPCs a narradores

```sql
CREATE TABLE mybb_game_npc_assignments (
    character_id INT NOT NULL,
    narrator_id  INT NOT NULL,
    PRIMARY KEY (character_id, narrator_id)
);
```

**Filosofía:** Los NPCs no son de "nadie" — son del foro. Pero los narradores necesitan poder editarlos y postear con ellos. Esta tabla asigna NPCs a narradores específicos sin darles control sobre TODOS los NPCs.

### 3.4 `game_thread_pj_state` — Estado PV/PE por hilo

```sql
CREATE TABLE mybb_game_thread_pj_state (
    thread_id     INT NOT NULL,
    character_id  INT NOT NULL,
    current_pv    INT NOT NULL,
    current_pe    INT NOT NULL,
    stat_mods_json TEXT,
    last_post_id   INT DEFAULT NULL,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (thread_id, character_id)
);
```

**Filosofía:** El PV/PE no es global por personaje, sino por hilo. Un personaje puede estar en múltiples hilos simultáneamente con distintos PV/PE en cada uno. Esto permite participar en varias tramas a la vez sin arrastrar daño de un hilo a otro.

### 3.5 `game_post_characters` — Registro por post

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

**Filosofía:**
- Cada post registra QUÉ personaje posteó, con QUÉ equipamiento y QUÉ gasto de PV/PE/PA declaró.
- `equipped_snapshot_json`: Permite al staff ver "¿qué equipo tenía cuando hizo ese ataque?" sin depender de que el inventario actual del PJ coincida.
- `hidden_actions_json`: Acciones no visibles para el rival (trampas, preparativos) que el staff puede revisar.

---

## 4. Estructura de JSON Columns

### 4.1 `stats_json` — Los 7 Atributos

```json
{
    "fue": 3,
    "res": 2,
    "agi": 5,
    "des": 1,
    "int": 4,
    "inst": 6,
    "esp": 2
}
```

- Rango 1 (mínimo) a 6 (máximo PJ). NPCs pueden tener 7+.
- Las bonificaciones raciales NO se almacenan aquí (son runtime).

### 4.2 `data_json` — Datos de Progresión y Wizard

```json
{
    "age": "19",
    "origin": "East Blue, Isla Dawn",
    "pb": "Inspired by Zoro",
    "physique": "Athletic, 180cm...",
    "psychology": "Impulsivo pero leal...",
    "extras": "Tatuaje en brazo derecho.",
    "history": "Nacido en una aldea pesquera...",
    "disciplina": "Cuerpo a Cuerpo",
    "job": "Cocinero",
    "race": "Humano",
    "faction_rank": "Grumete",
    "faction": "Pirata",
    "avatar": "https://i.imgur.com/...",
    "linaje": { "version": 2, ... },
    "pp": 10,
    "pp_linaje": 4,
    "nivel": 1,
    "rank": "D",
    "stat_points_purchased": 0,
    "last_level_up_at": null,
    "last_rank_change_at": "2025-06-12 10:30:00"
}
```

**Campos protegidos (FORBIDDEN_DATA_KEYS):**
`pp`, `pp_linaje`, `nivel`, `rank`, `stat_points_purchased`, `pp_spent_eligible`, `last_level_up_at`, `is_staff`, `staff_level`, `approved`.

**Filosofía:** El usuario puede editar su ficha libremente, pero NUNCA puede sobrescribir sus PP, rango o nivel. Esas solo las modifica el sistema (comprando stats) o el staff.

### 4.3 `cronologia_json` — Diario y Relaciones

```json
{
    "diario": [
        { "id": "uuid-abc", "title": "Llegada a Loguetown",
          "category": "Presente", "content": "...",
          "day": 15, "season": 3, "year": 1522,
          "thread_id": 42, "post_id": 1234, "created_at": "..." }
    ],
    "relaciones": [
        { "id": "uuid-def", "char_id": 5, "char_name": "Monkey D. Luffy",
          "tag": "Capitán", "color": "#3b82f6",
          "notes": "Mi capitán.", "thread_id": null }
    ],
    "groups": [],
    "connections": []
}
```

### 4.4 Filosofía de los JSON Columns

**¿Por qué no normalizar el diario en una tabla aparte?**
- Un personaje típico tiene 10-30 entradas de diario. No justifica una tabla separada con JOINs.
- El diario se carga SIEMPRE con el personaje (en la ficha), nunca se consulta independientemente.
- Si un personaje tuviera 1000+ entradas, se podría migrar a tabla aparte. Pero es un caso improbable.

**¿Por qué UUIDs manuales en lugar de AUTO_INCREMENT?**
- Las entradas del diario se crean en el frontend (JS genera un UUID) antes de enviarse al servidor.
- Esto permite añadir entradas al DOM inmediatamente (optimismo) sin esperar la respuesta del servidor.

---

## 5. Flujo Completo de Creación

### 5.1 Diagrama de Secuencia

```
Usuario → Navegador → AJAX/PHP → MySQL

1. GET crear_personaje.php
   → Cargar catálogos (razas, disciplinas, oficios, linaje_system.json)
   → Verificar slots disponibles
   → Render HTML wizard con CREAR_PERSONAJE_CONFIG

2. Usuario llena wizard (3 pasos)
   → Preview local de stats, PV/PE, linaje

3. POST save_personaje.php (JSON)
   → CharacterSaveService::buildPayloadForInsert()
     → LinajeValidator::validateAndBuild()
     → sanitizeStats()
     → calcularRangoGlobal()
   → INSERT game_personajes
   → UPDATE game_user_config (slots_used +1)
   → game_disciplina_assign_initial()
   → game_oficio_assign_initial_from_job()

4. Redirigir a personaje.php?pj=N
```

### 5.2 Precondiciones

1. Usuario autenticado.
2. `slots_used < max_slots`.
3. CSRF token válido.
4. Datos del wizard completos y válidos.

### 5.3 Postcondiciones

- Registro en `game_personajes` con `status = 'pendiente'`.
- `slots_used + 1` en `game_user_config`.
- Disciplina inicial (grado I) en `game_character_disciplinas`.
- Oficio inicial (grado I) en `game_character_oficios`.
- Si era el primer personaje, se establece como `active_pj_id`.

---

## 6. Wizard de Creación — Paso a Paso

### 6.1 Arquitectura del Wizard

Archivo: `back/forum/game/public/crear_personaje.php` (555 líneas)
JS: `back/forum/jscripts/game/crear_personaje.js` (1019 líneas)
Catálogo: `back/forum/game/data/linaje_system.json`

### 6.2 Paso 1: Identidad

Campos: nombre, avatar, facción, rango (automático según facción), raza (con opción híbrido), edad, origen, PB, apariencia física, psicología, historia, extras.

**Filosofía del paso 1:** Todo lo narrativo. El jugador define QUIÉN es su personaje antes de definir QUÉ SABE HACER. Esto fuerza una decisión: primero creas la identidad, luego la mecánica.

**Rango automático por facción:**
```javascript
var facciones = {
    'Pirata':'Grumete', 'Marine':'Raso', 'Revolucionario':'Iniciado',
    'Gobierno':'Agente', 'Cazador':'Sin Estrella', 'Civil':'Ciudadano'
};
```

**Razas disponibles:** Humano, Mink, Gyojin, Gigante, Tontatta, Buccaner, Lunarian, Skypean, Oni, Sirena.

**Híbridos:** Al seleccionar "Híbrido", aparecen selects para raza dominante + recesiva. Los puntos de linaje se calculan como `dominante - 4`.

**Consejo de diseño:** Los híbridos existen para permitir combinaciones narrativas interesantes (un Mink-Gyojin, un Lunarian-Gigante), pero tienen MENOS puntos de linaje que un personaje de raza pura. La flexibilidad narrativa tiene un coste mecánico.

### 6.3 Paso 2: Conocimientos

**Disciplina de Combate** (grid visual):
- Catálogo excluye Haki de Conquistador, Haki de Observación y Armamento (se adquieren después).
- Se asigna grado I al crear.

**Oficio** (grid visual):
- Opción "Ninguno" preseleccionada.
- Panel de detalle con descripción y desbloqueos de grado I.
- Se asigna grado I al crear.

**Distribución de Stats:**
- 7 stats, base = 1, máximo en creación = 2.
- **1 punto para distribuir.** Solo uno. Esto es INTENCIONAL: en creación, tu personaje es un novato. No puedes empezar con FUE 2 y AGI 2. Tienes que elegir: ¿qué aspecto de ti está ligeramente por encima del resto?
- Preview en vivo de valores efectivos (con bonus raciales) y PV/PE.

**Filosofía de la distribución limitada:**
- Si diéramos más puntos en creación, los personajes nuevos serían demasiado competentes.
- El punto único fuerza una decisión narrativa: "¿qué es lo primero que mi personaje sabe hacer?"
- El resto del progreso vendrá con el juego (PP posts).

### 6.4 Paso 3: Expediente (Factor Linaje)

**Sistema de Linaje v2:**
- Catálogo `linaje_system.json` con pasivas primarias, secundarias, árboles raciales, árbol general.
- Puntos de linaje según raza (Humano: 28, Mink: 22, Gyojin: 20...).
- Cada perk tiene coste 1.
- PP bonus = puntos_sobrantes × 2.
- Límite: máximo 2 perks raciales, máximo 2 perks generales.

**Filosofía del linaje:**
- El linaje es donde la RAZA realmente importa. Un Humano tiene stats planos pero MUCHOS puntos de linaje (28), pudiendo personalizarse más que nadie. Un Gigante tiene stats potentes pero menos puntos de linaje (16), porque su biología ya le da ventajas.
- La conversión sobrante→PP bonus permite sacrificar personalización racial por progresión inmediata. Es un tradeoff significativo.

**Consejo de diseño:** Cuando un jugador elige entre perks y PP bonus, está decidiendo entre identidad (perks raciales que definen a su personaje) y poder inmediato (PP para subir stats). No hay respuesta correcta.

### 6.5 Envío (Submit)

El botón "Enviar a Revisión" ejecuta `submitCharacter()`:
1. Recolecta datos del wizard en JSON.
2. POST a `save_personaje.php`.
3. Si éxito (`{ok: true, pj_id: N}`), redirige a `personaje.php?pj=N`.
4. Si falla, muestra error del servidor.

### 6.6 Edición de Personaje

- Mismo wizard con `?pj_id=N`.
- Solo editable si `status` es `pendiente` o `revision`.
- `CharacterSaveService::buildPayloadForUpdate()` preserva FORBIDDEN_DATA_KEYS.

---

## 7. Sistema de Revisión y Aprobación

### 7.1 Diagrama de Estados

```
pendiente → revision → aprobada → muerto
         ↘ rechazada → (borrado)
```

### 7.2 Endpoint de Aprobación

Archivo: `game/ajax/aprobar_personaje.php`

**Acciones:**
| Action | Nuevo status | Efecto |
|--------|-------------|--------|
| `aprobar` | `aprobada` | Recalcula data_json, actualiza stats |
| `rechazar` | `rechazada` | BORRA el personaje de DB |
| `revision` | `revision` | Marca en revisión (editable) |
| `pendiente` | `pendiente` | Vuelve a pendiente |

**Validaciones:**
1. Staff level ≥ 1 en PJ activo.
2. Personaje debe existir.
3. Al aprobar: `CharacterSaveService::recalculateOnApprove()` revalida linaje y recalcula todo.
4. Al rechazar: DELETE + recalcular slots_used.
5. Siempre se inserta en `game_personajes_revisiones`.
6. Se envía notificación y/o MP al usuario.

### 7.3 Filosofía del Sistema de Revisión

**¿Por qué revisión obligatoria?**
- **Control de calidad:** El staff puede detectar inconsistencias (stats imposibles para el lore, nombres fuera de tono, historias copiadas) antes de que el personaje empiece a rolear.
- **Igualdad de condiciones:** Nadie empieza a rolear hasta que su ficha es aprobada. No hay ventaja de "llegué primero, mi ficha no se revisó".
- **Seguridad:** Evita nombres ofensivos, enlaces a contenido inapropiado, o datos deliberadamente rotos.

**¿Por qué se BORRA al rechazar?**
- Porque un personaje rechazado es una ficha que nunca debió existir en el foro. Mantener registros de personajes rechazados solo saturaría la base de datos.
- Si el jugador quiere intentarlo de nuevo, crea uno nuevo desde cero (con el mismo slot).

**¿Por qué se envía MP con el mensaje del staff?**
- Porque las correcciones suelen ser específicas: "la historia necesita 200 palabras más", "el linaje no es válido para tu raza". Un MP llega a la bandeja de entrada del foro; una notificación del sistema puede perderse.

### 7.4 Impacto RPG

| Decisión | Efecto en la comunidad |
|----------|----------------------|
| Revisión obligatoria | La calidad media de fichas es alta. Los jugadores se esfuerzan más. |
| Rechazo = borrado | El jugador no se queda con un personaje "muerto" ocupando slot. |
| MP con motivo | El jugador sabe exactamente qué corregir. Menos fricción. |

---

## 8. Ficha de Personaje

### 8.1 Ruta y Carga

Archivo: `game/public/personaje.php`

```php
require_once 'personaje_init.php';  // CharacterSheetLoader + contexto
$content = ob_get_clean();
game_render_page('Mi Personaje', $content);
```

**personaje_init.php** carga: `$char`, `$row`, `$cfg`, `$pj_progression`, `$pp_available`, TAG_COLORS, cat_list.

### 8.2 Estructura de la Vista

```
page.php
├── _styles.php
├── _sidebar.php (avatar, badges, stats, disciplinas, oficios, PV/PE)
├── _tabs_nav.php (Bio, Historia, Linaje, Cronología, Deck, Gestión, Haki)
├── _tab_bio.php
├── _tab_historia.php
├── _tab_linaje.php
├── _tab_cronologia.php
├── _tab_deck.php (solo si can_view_private)
├── _tab_gestion.php (solo si can_view_private)
├── _modals.php
└── _scripts.php (PERSONAJE_PAGE_CONFIG)
```

### 8.3 Filosofía de la Ficha

**¿Por qué tabs y no una sola página?**
- Un personaje tiene MUCHA información. Stats, bio, historia, linaje, diario, relaciones, deck, gestión... todo en una página sería abrumador.
- Los tabs permiten que el jugador enfoque lo que necesita: "hoy voy a actualizar mi diario" → tab Cronología.

**¿Por qué Deck y Gestión son privados (solo para el dueño y staff)?**
- **Deck:** Las cartas de un personaje son su "mano". Mostrarlas públicas daría ventaja al rival en combate.
- **Gestión:** PP, PD, solicitudes al staff. Información privada entre jugador y staff.

**¿Por qué el sidebar tiene los stats SIEMPRE visibles?**
- Porque los stats son la identidad mecánica del personaje. Cualquiera que vea la ficha debería saber "este personaje es rápido (AGI alto) pero frágil (RES bajo)". Es información pública que permite rolear con conocimiento.

### 8.4 Cálculo de PV y PE en la Ficha

```php
$ctx = game_build_stat_context($char['stats'], $char['race_name']);
$vitals = game_compute_pv_pe_from_context($ctx['values'], $ctx['trained']);
$pv = $vitals['max_pv'];
$pe = $vitals['max_pe'];
```

**PV:** `(res_efectivo×4) + (fue_efectivo×3) + (esp_efectivo×2) + (agi_efectivo×1)`
**PE:** `(esp_efectivo×4) + (des_efectivo×3) + (int_efectivo×2) + (agi_efectivo×1)`

### 8.5 Render de Stats en Sidebar

Cada stat se renderiza con 6 segmentos (D→SS), llenos hasta el rango entrenado (sin bonus racial). El label muestra el rango EFECTIVO.

**Filosofía visual:** Ver las barras con tu progreso real (entrenado) pero el label con tu capacidad efectiva te da dos lecturas: "qué he invertido" vs "qué soy capaz de hacer".

---

## 9. Sistema de Gestión

### 9.1 Dashboard de Gestión

6 submódulos accesibles desde `_tab_gestion.php`:

1. **Comprar Atributos** — Gasta PP para subir stats
2. **Gestionar Deck** — Propuestas de cartas, borrados, catálogo
3. **Mis Solicitudes** — Historial de peticiones al staff
4. **Disciplinas y Oficios** — Ver y mejorar competencias
5. **Gestionar Equipamiento** — Inventario, carga, compañeros, barco
6. **Destino** — Desbloqueos con Puntos Destino

### 9.2 Compra de Atributos (PP → Stats)

**Reglas:**
1. Personaje debe estar `aprobada`.
2. Rango 1–6 por stat.
3. Coste = `RANK_UPGRADE_COST[rango] × RANK_GLOBAL_MULTIPLIERS[RG]`.
4. PP de linaje se gastan primero.

### 9.3 Gestión de Deck

Tres modos:
- **Proponer Nueva Carta:** Formulario por tipo (técnica, equipo, haki, NPC, barco). Se envía como solicitud.
- **Solicitar Borrado:** Selecciona carta del inventario + motivo.
- **Carta de Catálogo:** Selecciona del catálogo oficial.

### 9.4 Filosofía del Sistema de Gestión

**¿Por qué las cartas requieren solicitud al staff en lugar de comprarse directamente?**
- Porque las cartas son PODER. Una carta mal diseñada puede romper el balance (ej: "daño infinito", "inmunidad total"). El staff revisa cada propuesta para mantener la integridad del juego.
- Esto también permite personalización: un jugador no se limita a un catálogo cerrado; puede proponer exactamente la técnica que imagina para su personaje.

**¿Por qué los PP se gastan en stats y no en cartas?**
- Los stats son el esqueleto del personaje. Las cartas son las habilidades. Ambas progresan, pero los stats son la base: sin stats no puedes usar cartas poderosas (por coste de PE).
- Separar la moneda (PP para stats, PD para cartas especiales) evita que un jugador tenga que elegir entre "ser fuerte" y "tener habilidades interesantes".

**¿Por qué el coste de stat aumenta con el rango global?**
- Para evitar min-maxing extremo. Si solo subes FUE hasta 6, tu rango global se queda bajo y pagas el mínimo. Pero también tendrás PV/PE bajos por el multiplicador y el rango global bajo. El sistema premia el balance sin prohibir la especialización.

---

## 10. Sistema de Cronología

### 10.1 Arquitectura

La cronología se almacena en `cronologia_json` y se gestiona mediante `update_cronologia.php` + `personaje_page.js`.

### 10.2 Diario (Entradas)

Categorías: Pasado (`#8b5cf6`), Presente (`#10b981`), Mision (`#f59e0b`), Evento (`#3b82f6`), Trama (`#ef4444`), Fic (`#ec4899`), Off_Rol (`#6b7280`).

**Filosofía del diario:**
- Es la MEMORIA del personaje. Lo que vivió, lo que aprendió, lo que sintió.
- Las categorías permiten filtrar: "¿qué pasó en la misión X?" vs "¿cuál fue mi pasado?"
- `Off_Rol` existe para notas del jugador que no son in-character pero son útiles ("esta relación la conocí en el hilo Y").

### 10.3 Relaciones

Tags: Amigo, Compañero, Aliado, Rival, Enemigo, Némesis, Familiar, Maestro, Mentor, Aprendiz, Protegido, Interés Romántico, Cónyuge, Conocido, Socio, Cómplice, Subordinado, Superior, Adversario, Seguidor, Líder, Miembro.

Cada tag tiene un color asociado (TAG_COLORS) que se usa en la visualización de grafo.

**Filosofía de las relaciones:**
- Permiten mapear la red social del personaje dentro del foro.
- El color del tag da información visual inmediata: rojo (enemigo), verde (amigo), azul (aliado).
- La relación inversa (ej: "Capitán" ↔ "Tripulante") permite simetría.

### 10.4 Impacto RPG

| Característica | Para qué sirve en el juego |
|---------------|---------------------------|
| Diario por categorías | El jugador lleva un registro de su historia. Útil para recordar tramas viejas. |
| Relaciones con colores | De un vistazo sabes quién es aliado y quién enemigo. |
| Auto-detección de hilo | Al crear entrada desde un hilo, se enlaza automáticamente. |

---

## 11. Sistema de Linaje

### 11.1 Catálogo

Archivo: `game/data/linaje_system.json` (1766 líneas)

```json
{
    "puntos_linaje_por_raza": { "Humano": 28, "Mink": 22, "Gyojin": 20, ... },
    "pasivas_primarias": { "Humano": [...], "Mink": [...] },
    "pasivas_secundarias": { "Humano": [...] },
    "arboles_raciales": { "Humano": { "perks": [...] } },
    "arbol_general": { "Físico": { "perks": [...] }, "Mental": {...}, "Místico": {...} }
}
```

### 11.2 LinajeValidator

**Funciones:**
- `getMaxLinajePoints(raceName)`: Para híbridos, `dominante - 4`. Para razas puras, valor del catálogo.
- `validateAndBuild(raceName, linaje)`: Normaliza, calcula gasto, valida límites, calcula bonusPP.

**Filosofía del linaje:**
- Los puntos de linaje representan la HERENCIA biológica y cultural de tu raza.
- Gastarlos en perks es potenciar tu herencia. No gastarlos y convertirlos en PP bonus es "rechazar" tu herencia en favor de progreso individual.
- **Tradeoff central:** ¿Identidad racial o poder genérico? Es una decisión narrativa con peso mecánico.

### 11.3 Integración con CharacterProgression

`syncLinajeBonusPp()` se ejecuta en cada carga de ficha, asegurando que los PP de linaje nunca se pierdan aunque el data_json se corrompa.

**Autoreparación:** Si el servidor detecta que `data_json` está desactualizado (falta pp_linaje, pp incorrecto), lo corrige automáticamente y persiste el cambio.

---

## 12. Servicios PHP

### 12.1 CharacterSheetLoader

Archivo: `game/src/Application/Services/CharacterSheetLoader.php`

**Método principal: `load($db, $prefix, $userId, $reqPjId)`**

Retorna:
- `char`: Datos completos del personaje (stats, progresión, contexto, disciplinas, oficios)
- `pj_progression`: Snapshot de progresión
- `pp_available`: PP disponibles
- `can_edit`, `can_view_private`, `is_active_pj`, `active_char_is_staff`

**Auto-reparación de data_json:**
```php
CharacterProgression::syncLinajeBonusPp($dataForProg, $raceName);
CharacterProgression::normalize($dataForProg);
if (json_encode($dataBeforeSync) !== json_encode($dataForProg)) {
    // Persistir data_json reparado
}
```

**Filosofía:** La ficha nunca debe mostrar datos inconsistentes. Si algo está mal en la DB (por una migración incompleta o un bug), el Loader lo repara en el momento de la lectura.

### 12.2 CharacterSaveService

Archivo: `game/src/Application/Services/CharacterSaveService.php`

**Tres modos:**
- `buildPayloadForInsert()`: Crear personaje nuevo. Construye data_json desde cero.
- `buildPayloadForUpdate()`: Editar personaje pendiente. Preserva FORBIDDEN_DATA_KEYS.
- `recalculateOnApprove()`: Al aprobar. Revalida linaje, recalcula PP y rango.

**Filosofía de FORBIDDEN_DATA_KEYS:**
```php
const FORBIDDEN_DATA_KEYS = [
    'pp', 'pp_linaje', 'nivel', 'rank', 'stat_points_purchased',
    'pp_spent_eligible', 'last_level_up_at', 'is_staff', 'staff_level', 'approved'
];
```
El jugador nunca puede tocar estos campos. Ni siquiera debería saber que existen en data_json. Son gestionados exclusivamente por el sistema.

### 12.3 CharacterProgression

Archivo: `game/src/Application/Services/CharacterProgression.php`

| Método | Propósito |
|--------|-----------|
| `syncLinajeBonusPp()` | Sincroniza PP de linaje |
| `normalize()` | Asegura defaults de pp, pp_linaje, rank, nivel |
| `recalculateGlobalRank()` | Recalcula rank desde suma de stats |
| `getStatUpgradeCost()` | Coste para subir un stat |
| `validateStatUpgrade()` | Valida si se puede comprar un stat |
| `applyStatUpgrade()` | Ejecuta la compra de stat |
| `snapshot()` | Instantánea de progresión actual |

### 12.4 StatScale

Archivo: `game/src/Shared/StatScale.php`

Clase puramente matemática (sin dependencias externas). Contiene:
- Constantes de costes, multiplicadores, rangos
- Conversiones rango↔valor
- Cálculos de PV/PE
- Bonificaciones raciales
- Helper de Haki/Akuma

---

## 13. Repositorio

### 13.1 PersonajeRepository

Archivo: `game/src/Infrastructure/Persistence/PersonajeRepository.php`

**Métodos:**
- `getActiveCharacterId(userId)`: active_pj_id del usuario.
- `findByIdForUser(charId, userId)`: Busca con verificación de permisos.
- `findById(charId)`: Busca sin verificación (uso interno).

**Lógica de permisos en `findByIdForUser()`:**
1. ¿Es el dueño directo? → OK
2. ¿Es superadmin y es NPC? → OK
3. ¿Es narrador asignado? → OK
4. Sino → null (no encontrado)

### 13.2 Funciones Globales

Definidas en `game/inc/`:
- `game_disciplina_list_for_character()` / `game_oficio_list_for_character()`
- `game_disciplina_assign_initial()` / `game_oficio_assign_initial_from_job()`
- `game_build_stat_context()` / `game_compute_pv_pe_from_context()`
- `game_get_character_pd_available()` / `game_global_rol_date()`

---

## 14. AJAX Endpoints

### 14.1 `save_personaje.php`
POST. Crea o edita personaje. Verifica CSRF, login, slots, permisos.

### 14.2 `aprobar_personaje.php`
POST. Staff aprueba/rechaza/revisa personaje. Requiere staff_level ≥ 1.

### 14.3 `set_active_pj.php`
POST. Cambia PJ activo. Actualiza `game_user_config.active_pj_id`.

### 14.4 `my_personajes.php`
GET. Lista JSON de personajes del usuario.

### 14.5 `update_cronologia.php`
POST. CRUD de diario/relaciones.

### 14.6 `save_avatar_sig.php`
POST. Edita avatar/firma. Admins pueden editar cualquier personaje.

### 14.7 `get_personaje_preview.php`
GET. Staff preview del data_json completo.

### 14.8 `personajes_pendientes_list.php`
GET. Lista pendientes para staff.

---

## 15. JavaScript

### 15.1 `personaje_page.js` (2168 líneas)

Config: `window.PERSONAJE_PAGE_CONFIG`

Módulos:
- Network (Cronología): Render de relaciones, diario, grafo. CRUD completo.
- Tabs: Navegación entre secciones de la ficha.
- Gestión: Dashboard con 6 submódulos, compra de stats, solicitudes de cartas.
- Deck: Carga y render de inventario.
- Haki: Visualización de niveles de Haki.

### 15.2 `crear_personaje.js` (1019 líneas)

Config: `window.CREAR_PERSONAJE_CONFIG`

Módulos:
- 3 pasos del wizard + preview en vivo.
- Distribución de stats con límites (1→2 máximo en creación).
- Sistema de linaje con árboles raciales y general.
- Submit a `save_personaje.php`.

### 15.3 Otros JS

- `personaje_inventory.js`: Carga/equipamiento/uso de items.
- `mis_personajes.js`: Card grid + switch active character.
- `biblioteca_personajes.js`: Biblioteca pública con filtros.
- `zona_staff_personajes.js`: Panel staff (editor, berries, roles).

---

## 16. Templates y Vistas

### 16.1 Vistas PHP (`game/views/personaje/`)

| Partial | Archivo | Propósito |
|---------|---------|-----------|
| Orquestador | `page.php` | Decide qué mostrar (empty vs ficha) |
| Barra lateral | `_sidebar.php` | Avatar, badges, stats, disciplinas, oficios, PV/PE |
| Navegación | `_tabs_nav.php` | Menú de tabs |
| Biografía | `_tab_bio.php` | Datos personales |
| Historia | `_tab_historia.php` | Backstory |
| Linaje | `_tab_linaje.php` | Pasivas, perks, PP linaje |
| Cronología | `_tab_cronologia.php` | Diario + relaciones |
| Deck | `_tab_deck.php` | Placeholder contenedor |
| Gestión | `_tab_gestion.php` | Panel completo (568 líneas) |
| Modales | `_modals.php` | Modales diario, relaciones, compra stats |
| Scripts | `_scripts.php` | Bootstrap PERSONAJE_PAGE_CONFIG |
| Estilos | `_styles.php` | CSS específico ficha |

### 16.2 Filosofía de las Vistas

**¿Por qué PHP plano en lugar de Twig/Smarty?**
- MyBB no trae motor de plantillas moderno. Añadir Twig sería una dependencia más.
- PHP plano es universal: cualquier hosting con PHP lo ejecuta.
- Las vistas son partials PHP, no templates compilados. Se incluyen directamente.

**¿Por qué `_tab_gestion.php` tiene 568 líneas?**
- Porque contiene 6 submódulos completos con formularios, grids, modos de deck, etc.
- Podría dividirse en 6 archivos, pero la navegación entre subtabs es más fácil en un solo archivo con divs de display toggle.

---

## 17. Plugin MyBB

### 17.1 `game_postcharacter.php`

Archivo: `inc/plugins/game_postcharacter.php`

**Hooks:**

| Hook MyBB | Acción |
|-----------|--------|
| `datahandler_post_insert_post_end` | Post creado → incrementa postnum, otorga PP/PD |
| `datahandler_post_insert_thread_end` | Thread creado → incrementa threadnum |
| `class_moderation_delete_post_start` | Post borrado → decrementa postnum |
| `class_moderation_delete_thread_start` | Thread borrado → decrementa threadnum |
| `global_start` | Contexto global → PJ activo en navegación |
| `editpost_start` | Bloquea edición sin PJ activo |

### 17.2 Filosofía del Plugin

**¿Por qué contar posts y hilos?**
- El postnum/threadnum es la métrica de actividad del personaje. No es solo un número: puede usarse como requisito para desbloquear contenido (ej: "necesitas 100 posts para solicitar Haki de Conquistador").
- También permite al staff identificar personajes activos vs abandonados.

**¿Por qué PP por cantidad de palabras?**
- `const WORDS_PER_PP = 100;` — Cada 100 palabras de rol (no Off_Rol) = 1 PP.
- Recompensa posts de calidad (extensos, detallados) sobre posts mínimos.
- Un post de 500 palabras da ~5 PP. A 50 PP por subir un stat de 1→2, se necesitan ~10 posts para la primera mejora. Ritmo intencional.

**¿Por qué NO contar Off_Rol?**
- Porque Off_Rol son notas del jugador, no contenido del personaje. No deberían generar progresión.

---

## 18. Permisos y Seguridad

### 18.1 Matriz de Permisos

| Operación | Owner PJ | Staff (1+) | Superadmin (3) | Narrador asignado |
|-----------|:--------:|:----------:|:--------------:|:-----------------:|
| Ver ficha propia | ✓ | ✓ | ✓ | ✓ |
| Ver ficha pública ajena | Solo aprobada | ✓ | ✓ | Solo aprobada |
| Ver tabs privados | ✓ | ✓ | ✓ | - |
| Editar ficha pendiente | ✓ | - | ✓ | - |
| Editar avatar/firma | ✓ | - | ✓ | - |
| Aprobar/rechazar | - | ✓ | ✓ | - |
| Editar NPC | - | - | ✓ | ✓ |
| Borrar personaje | - | - | ✓ | - |

### 18.2 Filosofía de Permisos

**¿Por qué el owner solo puede editar su ficha si está pendiente/revision?**
- Porque una vez aprobada, la ficha es "canon". Si el jugador pudiera cambiarla a voluntad, podría alterar su historia, sus stats o su apariencia sin control.
- Si necesita cambios menores (avatar, firma), puede hacerlo. Si necesita cambios mayores, debe solicitar al staff.

**¿Por qué los narradores pueden editar NPCs?**
- Porque los NPCs evolucionan con la trama. Un narrador necesita poder actualizar la ficha de un NPC después de un arco importante.
- Pero no pueden editar NPCs que no tienen asignados. Control granular.

**¿Por qué FORBIDDEN_DATA_KEYS incluye `is_staff` y `staff_level`?**
- Para que un jugador no pueda autoasignarse poderes de staff manipulando el JSON de su petición AJAX. Aunque el servidor verifique permisos, la doble capa de seguridad (no se puede escribir + verificación de permisos) es preventiva.

---

## 19. Contratos API

### 19.1 OpenAPI

Archivos: `packages/contracts/openapi/game-api.openapi.yaml` y `game-api-extended.openapi.yaml`

Endpoints:
- `GET /my_personajes` · `POST /save_personaje` · `POST /set_active_pj`
- `GET /get_active_pj` · `POST /aprobar_personaje` · `GET /personajes_pendientes_list`
- `GET /get_personaje_preview` · `POST /claim_character_level` · `POST /purchase_stat`
- `GET /pv_pe_state` · `GET /cards_search_characters`

### 19.2 Ejemplos

Archivos en `packages/contracts/examples/`:
- `character_competencias_get.response.json`
- `character_disciplinas_get.response.json`
- `inventory_get.response.json`
- `navigation_context.response.json`

---

## 20. Flujo de Datos Completo

### 20.1 Creación

```
JS Wizard → POST /save_personaje.php
→ CharacterSaveService::buildPayloadForInsert()
→ LinajeValidator → sanitizeStats() → calcularRank()
→ INSERT game_personajes
→ UPDATE game_user_config
→ game_disciplina_assign_initial()
→ game_oficio_assign_initial_from_job()
→ Response {pj_id: N}
```

### 20.2 Carga de Ficha

```
GET /personaje.php?pj=N
→ CharacterSheetLoader::load()
→ SELECT game_personajes + game_user_config
→ Decodificar JSON columns
→ CharacterProgression::syncLinajeBonusPp()
→ CharacterProgression::normalize()
→ game_build_stat_context()
→ game_compute_pv_pe()
→ Render page.php + partials
→ Bootstrap PERSONAJE_PAGE_CONFIG
```

### 20.3 Aprobación

```
POST /aprobar_personaje (staff)
→ Verificar staff_level
→ SELECT personaje
→ CharacterSaveService::recalculateOnApprove()
→ UPDATE data_json + stats_json
→ INSERT game_personajes_revisiones
→ Notificar usuario (DM o notificación)
```

---

## 21. Estados y Transiciones

### 21.1 Diagrama

```
pendiente ←→ revision ←→ aprobada → muerto
    ↓
rechazada → (borrado)
```

### 21.2 Transiciones

| Actual → Siguiente | Quién | Efecto |
|-------------------|-------|--------|
| pendiente → revision | Staff | Personaje en revisión |
| pendiente → aprobada | Staff | Recalcular y activar |
| pendiente → rechazada | Staff | Borrar personaje |
| revision → aprobada | Staff | Recalcular y activar |
| revision → rechazada | Staff | Borrar |
| revision → pendiente | Staff | Devolver a cola |
| aprobada → muerto | Staff | Marcar fallecido |
| aprobada → pendiente | Staff | Desaprobar |
| muerto → aprobada | Staff | Revivir |

---

## 22. Filosofía de Diseño

### 22.1 Principios Rectores

1. **El personaje es la unidad fundamental del foro.** Todo gira en torno a él: posts, cartas, misiones, tripulaciones, economía.

2. **El jugador es dueño de su narrativa, pero el sistema garantiza el balance.** Puedes escribir la historia que quieras, pero tus stats y cartas pasan por revisión.

3. **La progresión es horizontal, no solo vertical.** Subir stats es importante, pero también lo es desarrollar relaciones, escribir tu diario, adquirir oficios, y construir tu red de contactos.

4. **El staff es guardián de la calidad, no enemigo del jugador.** Cada revisión, cada aprobación, es una oportunidad para mejorar la ficha y la experiencia de todos.

### 22.2 Decisiones Clave y su Porqué

| Decisión | Alternativa descartada | Por qué se eligió así |
|----------|----------------------|----------------------|
| JSON columns en lugar de tablas normalizadas | Tabla por cada submódulo (stats, bio, progresión) | Menos JOINs, más flexibilidad, migraciones más simples |
| Revisión manual por staff | Aprobación automática | Control de calidad, prevención de abusos |
| PP por palabras posteadas | PP por tiempo o por post | Recompensa calidad sobre cantidad |
| Slots limitados (1 default) | Slots ilimitados | Compromiso con un personaje principal |
| Borrado al rechazar | Mantener en DB con status rechazado | No saturar DB, slots libres para reintentar |
| 3 pasos en wizard | Formulario único de una página | No abrumar, progresión lógica: identidad → conocimientos → expediente |

### 22.3 Filosofía de la Progresión

La progresión en este sistema es LENTA intencionalmente:
- Subir un stat de 1 a 2 cuesta ~10 posts de 500 palabras.
- Subir de 5 a 6 cuesta ~360 posts (con rango global S).
- Llegar a SS requiere años de juego.

**¿Por qué?**
- Porque en un foro de rol, el valor está en la TRAMA, no en el nivel. Si los personajes subieran rápido, en 3 meses todos serían SS y no habría desafío.
- La progresión lenta da tiempo para desarrollar historias, relaciones, y arcos narrativos entre mejoras.

---

## 23. Consejos para Jugadores

### 23.1 Creando tu Personaje

**Elige una raza que te guste ROLEAR, no solo por los stats.**
- Un Mink con AGI+1 es genial, pero si no te gusta rolear un animal humanoide, te aburrirás.
- Los humanos tienen los peores stats pero más puntos de linaje. Son la opción más personalizable.

**En el paso 2, elige disciplina y oficio que CUENTEN una historia.**
- "Cuerpo a Cuerpo + Carpintero" → un constructor que se defiende a puñetazos.
- "Armas de Filo + Cocinero" → un chef que corta verduras y enemigos con la misma maestría.

**En el paso 3 (linaje), NO siempre es mejor gastar todos los puntos.**
- Si no ves perks que realmente quieras para tu personaje, conviértelos a PP. 4 puntos de linaje sobrantes = 8 PP iniciales = casi te alcanza para subir un stat de 1 a 2.

### 23.2 Durante el Juego

**No subas stats al azar.** Piensa: ¿qué está haciendo tu personaje en la trama actual? Si está navegando, quizá debería subir INT (navegación) o INST (percepción). Si está peleando, FUE o AGI.

**El diario no es obligatorio, pero es tu MEJOR herramienta.**
- Llevar un diario detallado te permite recordar tramas viejas, detalles que mencionaste, y personajes que conociste.
- Cuando el staff lea tu ficha para una misión, el diario demuestra tu consistencia.

**Las relaciones son redes. No las descuides.**
- Marcar a otro PJ como "Aliado" o "Amigo" en tu ficha hace que el staff vea tu red de contactos.
- Si necesitas ayuda en una misión, tus aliados son recursos narrativos.

### 23.3 Errores Comunes

- **"Voy a subir FUE a 6 primero y luego el resto."** Tu rango global se quedará en D/C y pagarás el multiplicador mínimo, sí. Pero tu PV y PE serán muy bajos (multiplicador D = 1.00). Un personaje con FUE 6 pero RES 1 tiene ~50 PV. Cualquier enemigo decente lo tumba de un golpe.
- **"Los PP son para stats, no gasto en cartas."** Las cartas son tu poder real. Un stat alto sin cartas es potencial sin usar.
- **"No necesito oficio, solo peleo."** Los oficios te dan ventajas narrativas y mecánicas fuera del combate. Un médico siempre es bienvenido en cualquier tripulación.

---

## 24. Consejos para Staff

### 24.1 Revisando Fichas

**Qué revisar en orden de importancia:**
1. **Coherencia narrativa:** ¿La historia tiene sentido? ¿El nombre encaja en el mundo?
2. **Stats:** ¿Son coherentes con la historia? Un personaje que "nunca ha peleado" no debería tener FUE 4.
3. **Linaje:** ¿Los perks elegidos son válidos para la raza? ¿No se excedió de puntos?
4. **Raza:** ¿Tiene sentido la elección? Un Skypean en East Blue necesita justificación.

**Señales de alerta:**
- Stats muy dispares (6/1/1/1/1/1/1): min-maxer extremo.
- Historia copiada del manga: pide originalidad.
- Avatar que no carga: pide enlace válido.
- "Sin registrar" en todos los campos narrativos: pide mínimo esfuerzo.

### 24.2 Gestionando la Progresión

- **Las compras de stats deberían tener justificación narrativa.** Si un personaje sube FUE de 3 a 4, idealmente hubo un post de entrenamiento o una situación que lo justifique.
- **Monitorea a los jugadores con muchos PP acumulados.** Si alguien tiene 2000+ PP sin gastar, pregúntale por qué. Quizá está esperando una gran compra, o quizá no sabe cómo funciona el sistema.
- **Las solicitudes de cartas son donde más se rompe el balance.** Revisa con lupa: "¿esta carta es demasiado poderosa para el rango del PJ? ¿Tiene sentido en el mundo?"

### 24.3 Manteniendo el Ecosistema

- **Los personajes muertos deberían ser eventos narrativos.** No marques a alguien como "muerto" sin una trama que lo respalde (salvo abandono del jugador).
- **Fomenta el diario y las relaciones.** Un foro con personajes que tienen diarios detallados y relaciones marcadas es un foro VIVO.
- **Los NPCs son herramientas narrativas, no suplentes de PJs.** No crees NPCs para llenar huecos que los jugadores deberían ocupar.

### 24.4 Errores Comunes del Staff

- **Aprobar sin leer la historia.** La ficha no es solo stats. La historia es lo que conecta al personaje con el mundo.
- **Dejar personajes en "pendiente" por semanas.** Una revisión rápida (incluso para rechazar) es mejor que el silencio.
- **No usar el mensaje de aprobación/rechazo.** El MP con feedback es la herramienta más valiosa para mejorar la calidad del foro.

---

## 25. Guía de Troubleshooting

### 25.1 Errores en Creación

| Error | Causa | Solución |
|-------|-------|----------|
| "Has alcanzado el límite de personajes" | `slots_used >= max_slots` | Staff aumentar `max_slots` o jugador borrar otro PJ |
| "Linaje inválido" | Perks inconsistentes con la raza | Verificar selección en paso 3 |
| "Personaje no encontrado" | pj_id inválido o permisos | Verificar URL y ownership |
| Error 500 en save | Columna faltante en DB | Ejecutar migraciones pendientes |

### 25.2 Errores en Edición

| Error | Causa | Solución |
|-------|-------|----------|
| "No puede ser editado en su estado actual" | Status no pendiente/revision | Staff cambiar status primero |
| "Permiso denegado" | No es el dueño ni staff | Verificar user_id y staff_level |

### 25.3 Errores en Aprobación

| Error | Causa | Solución |
|-------|-------|----------|
| "Permiso denegado" | staff_level < 1 | Verificar config del PJ activo |
| "Parámetros inválidos" | action inválido | Verificar payload |
| "Linaje inválido" | Perks no válidos al recalcular | Staff editar y corregir linaje |

### 25.4 Depuración

La ficha tiene modo debug que muestra errores JS en la UI:
```javascript
window.onerror = function(msg, url, lineNo, colNo, error) {
    // Muestra error en pantalla para debug
};
```

---

## APÉNDICE A: Archivos del Subsistema

```
back/forum/game/
├── ajax/ (save_personaje, aprobar_personaje, set_active_pj, my_personajes,
│          get_personaje_preview, personajes_pendientes_list, save_avatar_sig,
│          update_cronologia, cards_search_characters, get_active_pj, inventory_get)
├── public/ (personaje.php, personaje_init.php, crear_personaje.php,
│           mis_personajes.php, biblioteca_personajes.php, zona_staff_personajes.php)
├── views/personaje/ (page, _sidebar, _tabs_nav, _tab_bio, _tab_historia,
│                    _tab_linaje, _tab_cronologia, _tab_deck, _tab_gestion,
│                    _modals, _scripts, _styles)
├── src/Application/Services/ (CharacterSheetLoader, CharacterSaveService,
│                              CharacterProgression, LinajeValidator,
│                              DirectMessageService, NotificationService)
├── src/Infrastructure/Persistence/ (PersonajeRepository)
├── src/Shared/ (StatScale)
├── data/ (linaje_system.json)
└── sql/ (install_schema_fragments, migrate_*)

back/forum/jscripts/game/ (personaje_page, personaje_inventory, crear_personaje,
                          mis_personajes, biblioteca_personajes, zona_staff_personajes)

inc/plugins/ (game_postcharacter.php)

packages/contracts/openapi/ (game-api.openapi.yaml, game-api-extended.openapi.yaml)
packages/contracts/examples/ (character_*, inventory_*, navigation_*)
```

---

*Fin del documento — Guía completa del Sistema de Personajes v2.1*
*Generado desde: `Guias/sistemas/01-personaje.md`*
*Referencia: `Guias/MAESTRO_SISTEMAS_RPG.md` — Sección 1*
