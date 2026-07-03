# 17. NPCs MAYORES — GUÍA COMPLETA

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 17
> **Propósito:** Documentar exhaustivamente el subsistema de NPCs Mayores: modelo de datos dual (perfiles JSON estáticos + personajes jugables con `is_npc=1`), esquemas de cada sección estructural, visor público, herramientas de staff, sistema de asignación a narradores, flujos de creación/edición/eliminación, filosofía de diseño y consejos operativos.

---

## ÍNDICE

1. [Arquitectura General del Subsistema NPC](#1-arquitectura-general)
2. [Modelo de Datos — Tabla `game_npc_profiles`](#2-modelo-de-datos-game_npc_profiles)
3. [Modelo de Datos — NPCs Mayores en `game_personajes`](#3-modelo-de-datos-npcs-mayores-en-game_personajes)
4. [Estructura de las Secciones JSON](#4-estructura-de-las-secciones-json)
5. [Visor Público — `npc.php` (Biblioteca de NPCs)](#5-visor-público-npcphp)
6. [Zona Staff — `zona_staff_npc.php`](#6-zona-staff-zona_staff_npcphp)
7. [Sistema de Asignación a Narradores](#7-sistema-de-asignación-a-narradores)
8. [Sistema de Narradores en `game_user_config`](#8-sistema-de-narradores)
9. [Permisos y Control de Acceso](#9-permisos-y-control-de-acceso)
10. [Integración con el Sidebar de Personaje](#10-integración-con-el-sidebar-de-personaje)
11. [Flujo de Creación de un NPC Mayor](#11-flujo-de-creación-de-un-npc-mayor)
12. [Flujo de Edición de un NPC Mayor](#12-flujo-de-edición-de-un-npc-mayor)
13. [Carga de Datos en el Visor](#13-carga-de-datos-en-el-visor)
14. [Normalización de Stats](#14-normalización-de-stats)
15. [Resolución de Avatares](#15-resolución-de-avatares)
16. [Clasificación de Facción](#16-clasificación-de-facción)
17. [Filosofía de Diseño](#17-filosofía-de-diseño)
18. [Decisiones Técnicas Clave](#18-decisiones-técnicas-clave)
19. [Consejos para Staff](#19-consejos-para-staff)
20. [Guía de Troubleshooting](#20-guía-de-troubleshooting)

---

## 1. Arquitectura General del Subsistema NPC

### 1.1 Dualidad del Sistema

El sistema reconoce DOS tipos de NPCs, cada uno con un modelo de datos y propósito distintos:

| Tipo | Tabla | Flag | Propósito | ¿Puede postear? |
|------|-------|------|-----------|:----------------:|
| **NPC de Biblioteca (Static)** | `game_npc_profiles` | — | Perfiles narrativos de personajes del mundo. Lore, historia, stats de referencia. Visibles en la biblioteca pública. | No |
| **NPC Mayor (Jugable)** | `game_personajes` | `is_npc = 1` | Personajes controlados por staff/narradores. Tienen ficha completa, pueden postear en hilos, aparecen en la biblioteca como "major". | Sí |

**Filosofía de la dualidad:** Los NPCs de biblioteca son el "who's who" del mundo — personajes que existen en el lore pero que nadie controla activamente. Los NPCs Mayores son personajes activos en la trama, con dueños (staff o narradores) que los manejan como si fueran PJs propios.

### 1.2 Capas del Subsistema

```
┌──────────────────────────────────────────────────────────────────┐
│                       CLIENTE (Navegador)                        │
│  ┌──────────────────┐  ┌───────────────────┐  ┌───────────────┐  │
│  │ npc.js           │  │ zona_staff_npc.js │  │ rpg_modal.js  │  │
│  │ (visor + modales)│  │ (editor NPCs)     │  │ (modal base)  │  │
│  └────────┬─────────┘  └────────┬──────────┘  └───────┬───────┘  │
│           │                     │                      │          │
└───────────┼─────────────────────┼──────────────────────┼──────────┘
            │                     │                      │
┌───────────┼─────────────────────┼──────────────────────┼──────────┐
│  ┌────────▼─────────────────────▼──────────────────────▼────────┐ │
│  │                    PHP — CAPA DE PRESENTACIÓN                 │ │
│  │  npc.php (visor) · zona_staff_npc.php (gestión staff)         │ │
│  │  personaje.php (ficha NPC Mayor)                              │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                               │                                    │
│  ┌────────────────────────────▼──────────────────────────────────┐ │
│  │              PHP — CAPA DE APLICACIÓN                         │ │
│  │  PersonajeRepository (findByIdForUser)                        │ │
│  │  StatScale (normalize_npc_stats)                              │ │
│  │  CharacterSheetLoader (carga de NPCs Mayores)                 │ │
│  │  StaffAccountService (asignación de narradores)               │ │
│  └───────────────────────────────────────────────────────────────┘ │
│                               │                                    │
│                               ▼                                    │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │                        MySQL                                  │ │
│  │  game_npc_profiles (perfiles JSON estáticos)                  │ │
│  │  game_personajes (NPCs Mayores con is_npc=1)                  │ │
│  │  game_npc_assignments (asignaciones narrador→NPC)             │ │
│  │  game_user_config (flag is_narrator)                          │ │
│  └──────────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────┘
```

### 1.3 Flujo de Datos Esquemático

```
Visor Biblioteca:
  GET /npc.php
  → SELECT game_npc_profiles (static)
  + SELECT game_personajes WHERE is_npc=1 (major)
  → Fusionar en array único
  → Renderizar grid de cards + modal detalle
  → Bootstrap NPC_CONFIG → npc.js

Gestión Staff:
  GET /zona_staff_npc.php
  → SELECT game_personajes WHERE is_npc=1
  → Verificar staff_level >= 3
  → Renderizar tabla + modal editor
  → Bootstrap → zona_staff_npc.js + rpg_modal.js

Ficha de NPC Mayor:
  GET /personaje.php?pj=N
  → CharacterSheetLoader::load()
  → Render igual que PJ normal (misma ficha, mismo sidebar)
  → Diferencia: permisos de edición vía is_npc + narrator_assignments
```

---

## 2. Modelo de Datos — Tabla `game_npc_profiles`

### 2.1 Definición SQL Completa

```sql
CREATE TABLE mybb_game_npc_profiles (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    nombre             VARCHAR(255) NOT NULL,
    imagen             VARCHAR(500) NOT NULL DEFAULT '',
    tripulacion_id     INT DEFAULT NULL,
    banner             VARCHAR(255) NOT NULL DEFAULT 'images/game/npc_banner.png',
    identificacion     JSON NOT NULL,
    perfil_fisico      JSON NOT NULL,
    psicologia         JSON NOT NULL,
    motivaciones       JSON NOT NULL,
    perfil_estrategico JSON NOT NULL,
    cronologia         JSON NOT NULL,
    relaciones         JSON NOT NULL,
    stats              JSON NOT NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_npc_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2 Descripción de Campos

#### `id` — Identificador único
- Autoincremental. NO se referencia desde otras tablas (los NPCs estáticos no participan en mecánicas).
- Usado como clave en el array de $npcs para el visor.

#### `nombre` — Nombre completo del NPC
- VARCHAR(255). Texto plano, sin markup.
- Se muestra en el título de la card y del modal.

#### `imagen` — URL del avatar/retrato
- VARCHAR(500). Puede ser:
  - Ruta relativa (ej: `images/game/npcs/kaido.png`) — se resuelve contra `$mybb->settings['bburl']`.
  - URL absoluta (ej: `https://i.imgur.com/abc.png`) — se usa directamente.
  - Vacío (`''`) — se usa `default_avatar.png`.
- **Resolución:** La función `resolve_avatar()` en `npc.php` normaliza la ruta (ver sección 15).

#### `tripulacion_id` — FK opcional a `game_tripulaciones`
- `INT DEFAULT NULL`. Relación con la tabla de tripulaciones.
- JOIN LEFT con `game_tripulaciones` para obtener `trip_nombre` y `trip_imagen`.
- Si es NULL, el NPC no pertenece a ninguna tripulación.

#### `banner` — Imagen de banner para la ficha
- VARCHAR(255). Default: `'images/game/npc_banner.png'`.
- No se usa actualmente en el visor (reservado para vista detalle futura).

#### `identificacion` — JSON de identidad básica
- NO nulo. Mínimo: `{}`.
- Estructura detallada en sección 4.

#### `perfil_fisico` — JSON de apariencia física
- NO nulo. Mínimo: `{}`.
- Estructura detallada en sección 4.

#### `psicologia` — JSON de perfil psicológico
- NO nulo. Mínimo: `{}`.
- Estructura detallada en sección 4.

#### `motivaciones` — JSON de objetivos
- NO nulo. Mínimo: `{}`.
- Estructura detallada en sección 4.

#### `perfil_estrategico` — JSON de capacidades de combate
- NO nulo. Mínimo: `{}`.
- Estructura detallada en sección 4.

#### `cronologia` — JSON de línea de tiempo
- NO nulo. Mínimo: `{}`.
- Estructura detallada en sección 4.

#### `relaciones` — JSON de conexiones con otros personajes
- NO nulo. Mínimo: `{}`.
- Estructura detallada en sección 4.

#### `stats` — JSON de atributos mecánicos
- NO nulo. Mínimo: `{}`.
- Estructura detallada en sección 4.
- Se normaliza con `normalize_npc_stats()` → `StatScale::sanitizeRanks()`.

#### `created_at` / `updated_at` — Timestamps de auditoría
- `created_at`: momento de inserción.
- `updated_at`: se actualiza automáticamente con `ON UPDATE CURRENT_TIMESTAMP`.
- No se exponen en el visor actualmente, pero están disponibles para futuras vistas de "última actualización".

### 2.3 Filosofía del Esquema

**¿Por qué 8 columnas JSON separadas en lugar de un único JSON gigante?**
- **Legibilidad en DB:** Un `SELECT *` te da las secciones como columnas nombradas. Sabes instantáneamente qué contiene cada columna sin inspeccionar keys de un JSON monolítico.
- **Actualización granular:** Si solo cambia `psicologia`, el UPDATE es semánticamente claro: `UPDATE ... SET psicologia = '...json...' WHERE ...`.
- **Paralelismo con la guía MAESTRO:** Cada sección del MAESTRO_SISTEMAS_RPG.md corresponde 1:1 con una columna de la tabla. La correspondencia es directa y obvia.
- **Contrapartida:** Cada SELECT trae ~8 columnas JSON (potencialmente pesadas). Pero los NPCs se cargan en una sola query sin JOINs pesados, y el total de NPCs rara vez supera el centenar.

**¿Por qué `tripulacion_id` como FK separada y no dentro de `identificacion`?**
- Porque la tripulación es una entidad del sistema (con tabla, imagen, miembros). Tiene sentido que sea una relación formal, no un string suelto en un JSON.
- El JOIN permite mostrar el logo/nombre de la tripulación en la card del NPC.

**¿Por qué `imagen` como columna aparte y no dentro de `identificacion`?**
- Porque la imagen del avatar se necesita en TODAS las vistas (card grid, modal, potencialmente listas). Separarla evita decodificar JSON para algo tan básico.
- Es también un campo que el staff puede querer actualizar independientemente del JSON de identidad.

---

## 3. Modelo de Datos — NPCs Mayores en `game_personajes`

### 3.1 Definición SQL (Campos Relevantes)

Los NPCs Mayores reutilizan la tabla `game_personajes` con el flag `is_npc = 1`:

```sql
CREATE TABLE mybb_game_personajes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT DEFAULT NULL,        -- NULL = NPC sin dueño directo
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
    is_npc          TINYINT(1) NOT NULL DEFAULT 0,  -- ← FLAG NPC
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
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.2 Diferencias Clave con PJs Normales

| Aspecto | PJ Normal | NPC Mayor |
|---------|-----------|-----------|
| `user_id` | ID del dueño | `NULL` (o del staff que lo creó como referencia, pero no se usa para control) |
| `is_npc` | `0` | `1` |
| `status` | `pendiente` → revisión → `aprobada` | Directamente `aprobada` (sin revisión) |
| `approved` | Se pone a 1 al aprobar | `1` desde creación |
| `staff_level` | Depende del dueño | Normalmente 0 (no es el staff quien postea, sino quien controla el NPC) |
| `data_json` | Datos del wizard + progresión | Datos narrativos (age, history, etc.) |
| `stats_json` | Stats reales del PJ | Stats de referencia del NPC |
| Edición | Solo dueño (o staff superadmin) | Superadmin (staff_level=3) + narradores asignados |

### 3.3 Datos Específicos de NPC en el Visor

Cuando `npc.php` carga NPCs Mayores, extrae del `data_json`:

```php
$dataNpc = json_decode($row['data_json'], true);
// Mapeo:
$identificacion = [
    'apodos'       => [],
    'edad'         => $dataNpc['age'] ?? 'Desconocida',
    'raza'         => $row['race_name'],
    'afiliacion'   => $row['faction'] ?: 'Civil',
    'ocupacion'    => $row['occupation_name'],
    'estado_actual'=> $row['rango'] ?: 'Activo',
];
```

A diferencia del `game_npc_profiles` que tiene 8 JSONs separados, los NPCs Mayores meten datos equivalentes en 3 campos LONGTEXT.

### 3.4 Migraciones

Dos migraciones preparan el terreno para NPCs Mayores:

**`migrate_npc_system.php`** — Añade columna `is_npc`:
```sql
ALTER TABLE mybb_game_personajes ADD COLUMN is_npc TINYINT(1) NOT NULL DEFAULT 0 AFTER staff_level;
```

**`migrate_narrador_system.php`** — Añade `is_narrator` y crea `game_npc_assignments`:
```sql
ALTER TABLE mybb_game_personajes ADD COLUMN is_narrator TINYINT(1) NOT NULL DEFAULT 0 AFTER is_npc;

CREATE TABLE mybb_game_npc_assignments (
    character_id INT NOT NULL,
    narrator_id  INT NOT NULL,
    PRIMARY KEY (character_id, narrator_id),
    INDEX idx_narrator_id (narrator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Filosofía de migraciones separadas:** Cada migración aborda UN requisito. Si un foro solo necesita NPCs pero no narradores, aplica solo la primera. Separa responsabilidades y permite despliegues parciales.

---

## 4. Estructura de las Secciones JSON

### 4.1 `identificacion` — Identidad Básica

```json
{
    "edad": "47",
    "apodo": "El Rey de las Bestias",
    "rango": "Gobernante",
    "afiliacion": "Piratas de las Bestias",
    "bounty": "4.611.000.000",
    "faccion_slug": "pirata",
    "titulo": "Gobernador General de Onigashima",
    "ocupacion": "Emperador Pirata",
    "estado": "Activo",
    "fruta": "Uo Uo no Mi, Modelo: Seiryu"
}
```

**Esquema de keys:**

| Key | Tipo | Obligatorio | Descripción |
|-----|------|:-----------:|-------------|
| `edad` | string | No | Edad en años. Puede incluir "?" si es desconocida. |
| `apodo` | string | No | Apodo o epíteto. Se muestra en la card del visor. |
| `rango` | string | No | Rango dentro de la facción (Ej: "Gobernante", "Capitán", "Almirante"). |
| `afiliacion` | string | No | Nombre de la organización/grupo al que pertenece. |
| `bounty` | string | No | Recompensa en berries. Texto formateado. |
| `faccion_slug` | string | No | Slug para clasificación (`pirata`, `marine`, `revolucionario`, `gobierno`, `cazador`, `civil`). Se usa para el filtro en el visor. |
| `titulo` | string | No | Título formal del personaje. |
| `ocupacion` | string | No | Profesión u ocupación principal. |
| `estado` | string | No | `"Activo"`, `"Fallecido"`, `"Desaparecido"`, `"Capturado"`, etc. |
| `fruta` | string | No | Nombre de la Akuma no Mi si posee una. |

**Uso en el visor:** El campo `apodo` se muestra bajo el nombre en la card. `afiliacion` determina el badge de facción (procesado por `get_standard_faction()`). `bounty` se muestra en el modal.

### 4.2 `perfil_fisico` — Apariencia Física

```json
{
    "altura": "710 cm",
    "peso": "Desconocido",
    "complexion": "Colosal, musculatura sobrehumana",
    "cabello": "Negro, largo y despeinado",
    "ojos": "Rojos, con esclerótica amarilla",
    "piel": "Blanca",
    "rasgos_distintivos": [
        "Cuernos largos y curvados hacia arriba",
        "Cicatriz en forma de X en el torso",
        "Barba larga y negra atada con una cuerda"
    ],
    "ropa_tipica": "Kimono morado con obi dorado, hakama negros",
    "descripcion": "Imponente incluso entre los gigantes. Su presencia llena la sala. Los cuernos y la mirada fiera delatan su linaje Oni.",
    "imagen_referencia": "https://i.imgur.com/kaido_full.png"
}
```

**Esquema de keys:**

| Key | Tipo | Descripción |
|-----|------|-------------|
| `altura` | string | Altura en cm o formato descriptivo. |
| `peso` | string | Peso o "Desconocido". |
| `complexion` | string | Tipo de cuerpo (atlético, robusto, delgado). |
| `cabello` | string | Color y estilo de cabello. |
| `ojos` | string | Color y características oculares. |
| `piel` | string | Color/tono de piel. |
| `rasgos_distinctivos` | array[string] | Lista de marcas, cicatrices, tatuajes. |
| `ropa_tipica` | string | Vestimenta que usa habitualmente. |
| `descripcion` | string | Párrafo narrativo de apariencia general. |
| `imagen_referencia` | string | URL a imagen de referencia adicional. |

**Filosofía:** El `perfil_fisico` es la herramienta del staff y los narradores para describir consistentemente al NPC. Cuando alguien rolea a Kaido, leer esta sección le da los detalles exactos de su apariencia. Los `rasgos_distintivos` son especialmente útiles: son checklist visuales ("¿mencioné los cuernos? ¿la cicatriz?").

### 4.3 `psicologia` — Perfil Psicológico

```json
{
    "personalidad": "Kaido es un guerrero nato que valora la fuerza por encima de todo. Depresivo, impulsivo y propenso a arrebatos de ira. Busca una muerte gloriosa que sea digna de su leyenda.",
    "miedos": [
        "No encontrar una muerte digna",
        "Ser olvidado por la historia",
        "El vacío existencial que siente cuando no está peleando"
    ],
    "motivaciones_ocultas": "Desea destruir el orden mundial establecido porque siente que el mundo no le ha dado el reconocimiento que merece. Su guerra contra el Gobierno Mundial no es política, es personal.",
    "manias": [
        "Bebe sake en exceso para ahogar su depresión",
        "Se lanza a pelear sin evaluar al oponente",
        "Colecciona objetos de guerras pasadas"
    ],
    "lema": "Si vas a pelear, hazlo como un hombre. De pie y sonriendo.",
    "fortalezas_mentales": [
        "Voluntad inquebrantable",
        "Capacidad de inspirar miedo y lealtad"
    ],
    "debilidades_mentales": [
        "Impulsividad",
        "Depresión recurrente",
        "Subestima oponentes débiles"
    ],
    "alineamiento": "Caótico Neutral",
    "filosofia": "El mundo pertenece a los fuertes. Los débiles solo existen para ser pisoteados."
}
```

**Esquema de keys:**

| Key | Tipo | Descripción |
|-----|------|-------------|
| `personalidad` | string | Descripción general de la personalidad. |
| `miedos` | array[string] | Miedos y fobias del personaje. |
| `motivaciones_ocultas` | string | Aquello que realmente impulsa al personaje, a veces ni él mismo lo sabe. |
| `manias` | array[string] | Comportamientos repetitivos o hábitos. |
| `lema` | string | Frase que define su filosofía. |
| `fortalezas_mentales` | array[string] | Virtudes psicológicas. |
| `debilidades_mentales` | array[string] | Defectos psicológicos. |
| `alineamiento` | string | Alineamiento moral (opcional, para referencia). |
| `filosofia` | string | Cosmovisión del personaje. |

**Filosofía:** La `psicologia` es el corazón del NPC. Mientras que las stats dicen qué PUEDE hacer, la psicología dice qué HARÍA. Diferenciar `miedos` de `debilidades_mentales` es intencional: un miedo es algo externo que le aterra; una debilidad mental es un patrón interno de comportamiento.

### 4.4 `motivaciones` — Objetivos y Metas

```json
{
    "corto_plazo": [
        "Mantener el control sobre Onigashima tras la incursión de los Súpernovas",
        "Encontrar al traidor dentro de sus filas"
    ],
    "medio_plazo": [
        "Reunir un ejército capaz de desafiar al Gobierno Mundial",
        "Capturar a los miembros de la Alianza Ninja-Pirata-Mink-Samurái"
    ],
    "largo_plazo": [
        "Provocar la guerra más grande que el mundo haya visto",
        "Morir en batalla contra los más fuertes del mundo",
        "Destronar a los Cinco Ancianos"
    ],
    "prioridades": {
        "1": "Su tribu y su tripulación",
        "2": "Su honor como guerrero",
        "3": "Su legado en la historia"
    },
    "estaria_dispuesto_a": "Sacrificar a sus subordinados más débiles si eso le acerca a su objetivo",
    "no_haria_jamas": "Traicionar a King, Queen y Jack. Rendirse en batalla.",
    "conflictos_internos": "Su deseo de muerte gloriosa choca con su responsabilidad como líder de los Piratas de las Bestias"
}
```

**Esquema de keys:**

| Key | Tipo | Descripción |
|-----|------|-------------|
| `corto_plazo` | array[string] | Objetivos inmediatos (días/semanas). |
| `medio_plazo` | array[string] | Objetivos a meses vista. |
| `largo_plazo` | array[string] | Objetivos vitales (años). |
| `prioridades` | object | Mapa numerado de prioridades. |
| `estaria_dispuesto_a` | string | Límites morales que cruzaría. |
| `no_haria_jamas` | string | Límites morales inquebrantables. |
| `conflictos_internos` | string | Dilemas entre sus propias metas. |

**Filosofía:** Las motivaciones son la guía del narrador para decidir las acciones del NPC. Si el staff sabe que Kaido busca una muerte gloriosa pero no traicionaría a sus subordinados, entonces sabe que no haría tratos a espaldas de King. Las motivaciones NO son fijas — evolucionan con la trama. Esta sección debe actualizarse cuando el NPC completa o abandona objetivos.

### 4.5 `perfil_estrategico` — Capacidades de Combate

```json
{
    "estilo_combate": "Combate cuerpo a cuerpo con ataques de área masiva. Usa su fruta Zoan Mítica para transformarse en un dragón colosal, arrasando todo a su alrededor.",
    "armas": [
        "Maza gigante (Shirauo)",
        "Garras en forma de dragón"
    ],
    "fruta": {
        "nombre": "Uo Uo no Mi, Modelo: Seiryu",
        "tipo": "Zoan Mítica",
        "habilidades_clave": [
            "Transformación parcial y completa en dragón",
            "Vuelo",
            "Aliento de fuego (Boro Breath)",
            "Escamas indestructibles"
        ],
        "despertar": true
    },
    "haki": {
        "observacion": {
            "nivel": "Avanzado",
            "notas": "Puede sentir la presencia y el poder de quien se le acerca"
        },
        "armamento": {
            "nivel": "Avanzado",
            "notas": "Recubre su cuerpo y armas, capaz de dañar a usuarios de Logia"
        },
        "conquistador": {
            "nivel": "Experto",
            "notas": "Puede dejar inconscientes a miles de soldados débiles"
        }
    },
    "disciplinas": [
        "Combate cuerpo a cuerpo (Maestro)",
        "Intimidación (Maestro)",
        "Resistencia (Maestro)"
    ],
    "estadisticas_combate": {
        "ofensiva": "Letal",
        "defensiva": "Casi invulnerable",
        "velocidad": "Media-alta (sorprendente para su tamaño)",
        "resistencia": "Sobrehumana",
        "letalidad": "Extrema"
    },
    "debilidades_combate": [
        "Su tamaño lo hace un blanco fácil",
        "Su orgullo le impide esquivar ataques",
        "Depende de su transformación para máximo poder"
    ],
    "tacticas_frecuentes": [
        "Abre con Boro Breath para devastar el campo de batalla",
        "Si el oponente resiste, usa forma híbrida para combate cuerpo a cuerpo",
        "Cuando está acorralado, libera su despertar"
    ],
    "notas_para_staff": "Kaido NO debe ser derrotado por personajes de rango C. Es un Emperador. Si un PJ de rango C o B lo enfrenta solo, el resultado debe ser catastrófico para el PJ. Para enfrentarlo dignamente se requiere mínimo rango A+ con preparación."
}
```

**Esquema de keys:**

| Key | Tipo | Descripción |
|-----|------|-------------|
| `estilo_combate` | string | Descripción general de cómo pelea. |
| `armas` | array[string] | Armas que utiliza. |
| `fruta` | object | Detalles de Akuma no Mi (ver subesquema). |
| `haki` | object | Niveles de Haki por tipo (subesquema). |
| `disciplinas` | array[string] | Disciplinas de combate que domina. |
| `estadisticas_combate` | object | Valoración cualitativa de capacidades. |
| `debilidades_combate` | array[string] | Puntos débiles en combate. |
| `tacticas_frecuentes` | array[string] | Patrones de combate habituales. |
| `notas_para_staff` | string | Notas internas de diseño/balance. |

**Subesquema de `fruta`:**

| Key | Tipo | Descripción |
|-----|------|-------------|
| `nombre` | string | Nombre completo de la fruta. |
| `tipo` | string | Tipo: `"Logia"`, `"Zoan"`, `"Zoan Mítica"`, `"Paramecia"`. |
| `habilidades_clave` | array[string] | Habilidades principales. |
| `despertar` | boolean | Si ha alcanzado el despertar. |

**Subesquema de `haki`:**

Cada tipo de Haki (`observacion`, `armamento`, `conquistador`) es un objeto con:

| Key | Tipo | Descripción |
|-----|------|-------------|
| `nivel` | string | `"Básico"`, `"Intermedio"`, `"Avanzado"`, `"Experto"`. |
| `notas` | string | Descripción del alcance del poder. |

**Filosofía del perfil estratégico:** Esta sección es la guía de combate para el staff. Cuando un narrador necesita decidir cómo pelea el NPC, aquí encuentra no solo las habilidades (qué puede hacer) sino las tácticas (qué haría) y las debilidades (cómo puede ser derrotado). `notas_para_staff` es el campo más importante de toda la sección: contiene advertencias de balance y contexto de diseño que solo el staff debería conocer.

### 4.6 `cronologia` — Línea de Tiempo

```json
{
    "eventos": [
        {
            "fecha": "Hace 47 años",
            "titulo": "Nacimiento",
            "descripcion": "Nace en una isla desconocida del Nuevo Mundo, de linaje Oni.",
            "importancia": "alta"
        },
        {
            "fecha": "Hace 38 años",
            "titulo": "Reclutamiento por los Rocks",
            "descripcion": "Es reclutado por Rocks D. Xebec para los Piratas Rocks. Conoce a Whitebeard, Big Mom y Shiki.",
            "importancia": "alta",
            "relacionado_con": "Piratas Rocks"
        },
        {
            "fecha": "Hace 24 años",
            "titulo": "Batalla de God Valley",
            "descripcion": "Participa en la batalla que disuelve los Piratas Rocks. Roger y Garp salen victoriosos. Kaido escapa.",
            "importancia": "critica"
        },
        {
            "fecha": "Hace 10 años",
            "titulo": "Toma de Onigashima",
            "descripcion": "Derrota a los señores locales de Wano y establece su base en Onigashima.",
            "importancia": "alta"
        },
        {
            "fecha": "Hace 6 años",
            "titulo": "Emperador",
            "descripcion": "Es reconocido como uno de los Cuatro Emperadores del Nuevo Mundo.",
            "importancia": "critica"
        }
    ],
    "era_actual": "Dos años después de la incursión en Onigashima. Kaido fue derrotado por Luffy, pero se desconoce su paradero actual.",
    "notas_cronologicas": "Se recomienda verificar fechas con la cronología oficial del foro (game_thread_meta) para sincronización."
}
```

**Esquema de keys:**

| Key | Tipo | Descripción |
|-----|------|-------------|
| `eventos` | array[object] | Lista de eventos biográficos. |
| `era_actual` | string | Contexto temporal del NPC en el presente del foro. |
| `notas_cronologicas` | string | Notas internas sobre consistencia temporal. |

**Esquema de cada `evento`:**

| Key | Tipo | Descripción |
|-----|------|-------------|
| `fecha` | string | Fecha o referencia temporal. |
| `titulo` | string | Nombre del evento. |
| `descripcion` | string | Descripción detallada. |
| `importancia` | string | `"baja"`, `"media"`, `"alta"`, `"critica"`. |
| `relacionado_con` | string | Entidad relacionada (opcional). |

**Filosofía:** La cronología en game_npc_profiles es UNIDIRECCIONAL y LINEAL (a diferencia del `cronologia_json` de personajes, que tiene diario+relaciones+grupos). Esto es intencional: los NPCs estáticos tienen una historia fija que no cambia con el juego. Los eventos con `importancia: "critica"` son los puntos de trama que el staff DEBE conocer para rolear consistentemente al NPC.

### 4.7 `relaciones` — Conexiones con Otros Personajes

```json
{
    "aliados": [
        {"nombre": "King", "tipo": "Subordinado", "lealtad": "Absoluta", "notas": "Su hombre de confianza, lleva años a su lado."},
        {"nombre": "Queen", "tipo": "Subordinado", "lealtad": "Alta", "notas": "Leal pero con agenda propia."},
        {"nombre": "Jack", "tipo": "Subordinado", "lealtad": "Alta", "notas": "Brutalmente leal, ejecuta órdenes sin cuestionar."}
    ],
    "enemigos": [
        {"nombre": "Kozuki Oden", "tipo": "Némesis", "estado": "Fallecido", "notas": "Lo ejecutó personalmente. Sigue respetando su fuerza."},
        {"nombre": "Monkey D. Luffy", "tipo": "Rival", "estado": "Activo", "notas": "Lo derrotó en Onigashima. Cuenta pendiente."}
    ],
    "neutrales": [
        {"nombre": "Charlotte Linlin", "tipo": "Aliado táctico", "estado": "Desconocido", "notas": "Alianza temporal. Ambos se respetan pero no confían."}
    ],
    "relacion_con_pjs": [
        {
            "tipo_resumen": "Considera a los personajes débiles como inferiores, pero respeta a quienes muestran determinación."
        }
    ],
    "notas_para_staff": "Las relaciones pueden cambiar drásticamente según la trama. Actualizar cuando ocurran eventos significativos."
}
```

**Esquema de keys:**

| Key | Tipo | Descripción |
|-----|------|-------------|
| `aliados` | array[object] | Personas/grupos aliados. |
| `enemigos` | array[object] | Personas/grupos hostiles. |
| `neutrales` | array[object] | Relaciones indefinidas o complejas. |
| `relacion_con_pjs` | array[object] | Pautas generales de interacción con jugadores. |
| `notas_para_staff` | string | Notas internas sobre gestión de relaciones. |

**Esquema de cada entrada de relación:**

| Key | Tipo | Descripción |
|-----|------|-------------|
| `nombre` | string | Nombre del personaje relacionado. |
| `tipo` | string | Tipo de vínculo. |
| `lealtad` | string | Nivel de lealtad (solo aliados). |
| `estado` | string | `"Activo"`, `"Fallecido"`, `"Desconocido"`. |
| `notas` | string | Contexto de la relación. |

**Filosofía:** Las relaciones en `game_npc_profiles` son REFERENCIALES, no operativas (a diferencia de las relaciones en `cronologia_json` de personajes, que son interactivas). Sirven al staff para entender la red de alianzas y conflictos del NPC, pero no tienen efecto mecánico. Un NPC puede tener relación con un PJ, pero eso no crea automáticamente una entrada en el diario del PJ.

### 4.8 `stats` — Atributos Mecánicos

```json
{
    "fue": 8,
    "res": 8,
    "agi": 6,
    "des": 4,
    "int": 5,
    "inst": 7,
    "esp": 8
}
```

**Esquema:**

| Key | Rango | Descripción |
|-----|-------|-------------|
| `fue` | 1–9+ | Fuerza física, daño cuerpo a cuerpo. |
| `res` | 1–9+ | Resistencia, aguante, PV. |
| `agi` | 1–9+ | Agilidad, velocidad, reflejos. |
| `des` | 1–9+ | Destreza, precisión, habilidades finas. |
| `int` | 1–9+ | Inteligencia, conocimiento, estrategia. |
| `inst` | 1–9+ | Instinto, percepción, intuición. |
| `esp` | 1–9+ | Espíritu, voluntad, Haki. |

**Normalización:** El visor llama a `normalize_npc_stats()` que usa `StatScale::sanitizeRanks()`:

```php
public static function sanitizeRanks(array $stats): array {
    $default = ['fue' => 1, 'res' => 1, 'agi' => 1, 'des' => 1, 'int' => 1, 'inst' => 1, 'esp' => 1];
    foreach ($default as $k => $v) {
        $default[$k] = isset($stats[$k]) ? max(1, min(9, (int)$stats[$k])) : $v;
    }
    return $default;
}
```

**Filosofía de los rangos de NPC:**

| Rango | Significado | Ejemplo |
|-------|-------------|---------|
| 1–2 | Débil | Civil, soldado raso |
| 3–4 | Competente | Guerrero entrenado, Marine veterano |
| 5–6 | Elite | Capitán pirata, Oficial de Marine |
| 7 | Sobresaliente | Comandante, Súpernova |
| 8+ | Legendario | Emperador, Almirante, Yonko |

Los PJs están limitados a rango 6 máximo. Los NPCs pueden tener 7+ para representar que son objetivamente superiores. Esto es intencional: los NPCs marcan el techo de poder que los PJs aspiran a alcanzar.

---

## 5. Visor Público — `npc.php`

### 5.1 Arquitectura del Visor

Archivo: `game/public/npc.php` (247 líneas)

Es una página pública (no requiere login) que muestra todos los NPCs del mundo en un grid con filtros y modal de detalle.

**Flujo de ejecución:**

```
1. require bootstrap.php
2. Definir helpers: get_standard_faction(), resolve_avatar(), normalize_npc_stats()
3. Query 1: SELECT game_npc_profiles + LEFT JOIN game_tripulaciones
4. Query 2: SELECT game_personajes WHERE is_npc = 1
5. Fusionar en $npcs (array único)
6. Generar HTML de cards ($cards_html)
7. Renderizar: header + filtros + grid + modal + npc.js
```

### 5.2 Carga de Datos

```php
// 1. Static NPCs (game_npc_profiles)
$query1 = $db->query(
    "SELECT p.*, t.name AS trip_nombre, t.image_url AS trip_imagen
     FROM {$prefix}game_npc_profiles p
     LEFT JOIN {$prefix}game_tripulaciones t ON p.tripulacion_id = t.id
     ORDER BY p.id ASC"
);

// 2. Major NPCs (game_personajes WHERE is_npc = 1)
$query2 = $db->query(
    "SELECT * FROM {$prefix}game_personajes WHERE is_npc = 1 ORDER BY id ASC"
);
```

**Filosofía de la carga dual:** Ambas queries se ejecutan SIEMPRE. No hay flag para mostrar solo un tipo. La biblioteca es el censo completo de personajes no jugables del foro.

### 5.3 Mapeo de Datos

**Para static NPCs (`game_npc_profiles`):**

```php
$npcs[] = [
    'id'             => (int)$row['id'],
    'nombre'         => $row['nombre'],
    'imagen'         => resolve_avatar($row['imagen'], $mybb->settings['bburl']),
    'tripulacion_id' => (int)$row['tripulacion_id'],
    'trip_nombre'    => $row['trip_nombre'],
    'trip_imagen'    => $row['trip_imagen'],
    'identificacion' => json_decode($row['identificacion'], true) ?: [],
    'stats'          => normalize_npc_stats(json_decode($row['stats'], true) ?: []),
    'history'        => $row['cronologia'] ?? '',    ← ¡OJO! mapea la columna 'cronologia' (JSON) a history
    'faction'        => get_standard_faction($id_data['afiliacion'] ?? 'Civil'),
    'type'           => 'static',
];
```

**Detalle clave:** `history` recibe el JSON CRUDO de la columna `cronologia`, no los `eventos` parseados. El modal muestra el JSON tal cual en el modal. Esto es una simplificación: el visor no renderiza la cronología estructurada, solo la muestra como texto.

**Para Major NPCs (`game_personajes`):**

```php
$stats = json_decode($row['stats_json'], true) ?: [];
$dataNpc = json_decode($row['data_json'], true) ?: [];

// Con bonus raciales si hay función disponible
if ($raceName !== '' && function_exists('game_build_stat_context')) {
    $ctx = game_build_stat_context($stats, $raceName);
    foreach (['fue','res','agi','des','int','inst','esp'] as $sk) {
        $mapped_stats[$sk] = $ctx['effective_ranks'][$sk] ?? 1;
    }
} else {
    foreach (['fue','res','agi','des','int','inst','esp'] as $sk) {
        $mapped_stats[$sk] = max(1, min(9, (int)($stats[$sk] ?? 5)));
    }
}

$npcs[] = [
    'id'             => (int)$row['id'],
    'nombre'         => $row['name'],
    'imagen'         => resolve_avatar($row['avatar'], $mybb->settings['bburl']),
    'tripulacion_id' => null,
    'trip_nombre'    => $row['tripulacion'],
    'trip_imagen'    => '',
    'identificacion' => [
        'apodos'       => [],
        'edad'         => $dataNpc['age'] ?? 'Desconocida',
        'raza'         => $row['race_name'],
        'afiliacion'   => $row['faction'] ?: 'Civil',
        'ocupacion'    => $row['occupation_name'],
        'estado_actual'=> $row['rango'] ?: 'Activo',
    ],
    'stats'          => $mapped_stats,
    'history'        => $dataNpc['history'] ?? '',
    'faction'        => get_standard_faction($row['faction']),
    'type'           => 'major',
    'real_id'        => (int)$row['id'],
];
```

### 5.4 Renderizado de Cards

Cada NPC se convierte en una card HTML con atributos `data-faction` y `data-npc` (JSON embebido):

```php
$cards[] = '
<div class="rpg-lib-card" data-faction="' . $n['faction'] . '" data-npc=\'' . $data_json . '\'>
  <div class="rpg-lib-card-img" data-bg="' . htmlspecialchars($n['imagen'], ENT_QUOTES) . '">
    <span class="rpg-lib-card-badge">' . $faction_display . '</span>
  </div>
  <div class="rpg-lib-card-body">
    <h2 class="rpg-lib-card-title">' . htmlspecialchars($n['nombre']) . '</h2>
    <div class="rpg-lib-card-stats">
      <span class="rpg-lib-card-stat"><i class="fas fa-user-tag"></i> ' . $apodo . '</span>
      <span class="rpg-lib-card-stat"><i class="fas fa-briefcase"></i> ' . $ocupacion . '</span>
    </div>
  </div>
</div>';
```

**Estructura de `data-npc` (el JSON embebido):**

```json
{
    "nombre": "Kaido",
    "portrait": "https://...",
    "apodos": ["El Rey de las Bestias"],
    "edad": "47",
    "raza": "Oni",
    "afiliacion": "Piratas de las Bestias",
    "ocupacion": "Emperador Pirata",
    "estado": "Activo",
    "stats": { "fue": 8, "res": 8, "agi": 6, "des": 4, "int": 5, "inst": 7, "esp": 8 },
    "history": "Nacido en...",
    "link": "https://.../personaje.php?pj=42"
}
```

**Filosofía del JSON embebido:** En lugar de hacer una llamada AJAX al hacer clic en una card, todos los datos del NPC están embebidos en el HTML como `data-npc`. Esto significa:
- El modal se abre instantáneamente (sin latencia de red).
- El JS solo tiene que parsear el JSON y renderizar.
- La página pesa más (todo el contenido de todos los NPCs en el HTML), pero para un número manejable de NPCs (<100) es aceptable.

Si hubiera 500+ NPCs, habría que repensar esta estrategia (carga perezosa vía AJAX al hacer clic).

### 5.5 Modal de Detalle

El modal (`#lib-modal`) muestra:
- **Columna izquierda:** Retrato + stats (barras horizontales).
- **Columna derecha:** Historia + datos del personaje (edad, raza, afiliación, ocupación, estado).

```html
<div class="rpg-lib-modal rpg-lib-modal--xl" id="lib-modal">
  <div class="rpg-lib-modal-content rpg-lib-modal-content--xl">
    <span class="rpg-lib-modal-close" id="modal-close">&times;</span>
    <div class="rpg-lib-modal-body rpg-lib-modal-body--xl">
      <!-- Header: nombre + badge facción -->
      <div class="rpg-lib-modal-header">
        <h2 class="rpg-lib-modal-title" id="modal-title">Nombre</h2>
        <span class="rpg-lib-modal-badge" id="modal-badge">Afiliación</span>
      </div>
      <!-- Grid: izquierda (retrato + stats) / derecha (historia + info) -->
      <div class="rpg-modal-grid rpg-modal-grid--biblio">
        <div class="rpg-modal-column-left">
          <img id="modal-portrait" src="" alt="Retrato">
          <div id="modal-stats-list"></div>
        </div>
        <div class="rpg-modal-column-right">
          <div id="modal-history"></div>
          <div id="modal-info-stats"></div>
          <a id="modal-link-ficha" href="#" target="_blank">Ver Ficha Completa</a>
        </div>
      </div>
    </div>
  </div>
</div>
```

Para Major NPCs, el enlace `#modal-link-ficha` apunta a `personaje.php?pj=N` (la ficha completa del personaje). Para static NPCs, el enlace es `#` (no tienen ficha).

### 5.6 Filtros

El sidebar de filtros permite filtrar por facción:

```html
<label class="rpg-filter-option">
  <input type="checkbox" name="faction" value="Pirata" checked>
  <span class="rpg-filter-checkbox"></span>Piratas
</label>
<!-- ... mismo patrón para Marine, Revolucionario, Gobierno, Cazador, Civil -->
```

El JS (`npc.js`) gestiona el filtrado client-side: al marcar/desmarcar checkboxes, las cards con `data-faction` coincidente se muestran/ocultan.

**Filosofía del filtro client-side:** Como todos los datos ya están en el DOM, no tiene sentido hacer una petición al servidor para filtrar. La lógica es trivial en JS. El filtro por nombre (`#lib-search`) también es client-side (filtra por `data-npc.nombre`).

---

## 6. Zona Staff — `zona_staff_npc.php`

### 6.1 Arquitectura

Archivo: `game/public/zona_staff_npc.php` (239 líneas)

Es una página de administración para gestionar NPCs Mayores (los de `game_personajes` con `is_npc=1`). NO gestiona los NPCs estáticos de `game_npc_profiles`.

**Requisitos de acceso:**
1. Usuario autenticado (`$uid > 0`).
2. Tener un personaje activo (`active_pj_id`).
3. Ese personaje debe tener `staff_level >= 3` (Superadmin).

### 6.2 Verificación de Staff

```php
// Obtener personaje activo
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;

// Verificar staff_level del PJ activo
if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT name, staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        $staff_level = (int)$pj['staff_level'];
    }
}

// Solo staff nivel 3
if ($staff_level < 3) {
    header('Location: ../index.php');
    exit;
}
```

**Filosofía:** La verificación de staff usa el PJ ACTIVO, no el usuario de MyBB. Un usuario puede tener un PJ staff y otro normal. El nivel de staff se asocia al PJ, no al usuario del foro.

### 6.3 Listado de NPCs

```php
$npcs_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE is_npc = 1 ORDER BY name ASC");
```

Renderiza una tabla con columnas:
- **Avatar:** Imagen miniatura del NPC.
- **NPC:** Nombre + raza + ocupación.
- **Facción:** Badge coloreado.
- **Rango:** Rango en la facción.
- **Acciones:** Botón "Editar" que abre un modal.

### 6.4 Acciones Disponibles

#### Eliminar NPC

```php
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $delete_id = (int)($_GET['id'] ?? 0);
    if ($delete_id > 0) {
        $db->write_query("DELETE FROM {$prefix}game_personajes WHERE id = {$delete_id} AND is_npc = 1");
        header('Location: zona_staff_npc.php?msg=deleted');
        exit;
    }
}
```

**Seguridad:** La cláusula `AND is_npc = 1` evita que un borrado accidental elimine un PJ real. Aunque solo staff nivel 3 tiene acceso, la doble verificación previene desastres.

#### Crear NPC Mayor

El botón "Crear NPC Mayor" enlaza a `crear_personaje.php?is_npc=1`. El wizard de creación recibe un flag que indica que es un NPC:
- No necesita revisión (se crea directamente como `aprobada`).
- `user_id` se deja NULL.
- `is_npc = 1`.

#### Editar NPC

El botón "Editar" en cada fila abre un modal con:
1. **Resumen del NPC:** Avatar, nombre, raza, ocupación, facción.
2. **Atributos:** Visualización de los 7 stats (FUE, AGI, DES, INT, ESP, INST).
3. **Acciones:**
   - "Editar Ficha y Atributos" → Enlace a `crear_personaje.php?pj_id=N`.
   - "Activar Personaje" → Switch al NPC como PJ activo (para postear con él).
   - "Eliminar NPC" → Confirmación + redirección a `?action=delete&id=N`.

### 6.5 Modal de Edición

```html
<div class="rpg-modal-overlay" id="npc-editor-modal" data-rpg-modal aria-hidden="true">
  <div class="rpg-modal-panel rpg-modal-panel--md">
    <div class="rpg-modal-header">
      <h3 class="rpg-modal-title"><i class="fas fa-user-edit"></i> Gestionar NPC</h3>
      <button type="button" class="rpg-modal-close" data-rpg-modal-close>&times;</button>
    </div>
    <div class="rpg-modal-body">
      <!-- Resumen -->
      <div class="rpg-staff-pj-summary">
        <img id="npc-summary-avatar" src="" alt="">
        <div class="rpg-staff-pj-summary-info">
          <h3 id="npc-summary-name"></h3>
          <p id="npc-summary-meta"></p>
          <p id="npc-summary-faction"></p>
        </div>
      </div>
      <!-- Stats -->
      <div class="rpg-npc-card-stats">
        <div class="rpg-npc-card-stat"><span>FUE</span><strong id="npc-stat-fue">5</strong></div>
        <div class="rpg-npc-card-stat"><span>AGI</span><strong id="npc-stat-agi">5</strong></div>
        <div class="rpg-npc-card-stat"><span>DES</span><strong id="npc-stat-des">5</strong></div>
        <div class="rpg-npc-card-stat"><span>INT</span><strong id="npc-stat-int">5</strong></div>
        <div class="rpg-npc-card-stat"><span>ESP</span><strong id="npc-stat-esp">5</strong></div>
        <div class="rpg-npc-card-stat"><span>INST</span><strong id="npc-stat-inst">5</strong></div>
      </div>
      <!-- Acciones -->
      <div class="rpg-staff-actions-grid">
        <a href="" id="btn-edit-npc-link" class="rpg-action-btn rpg-btn-primary">
          <i class="fas fa-edit"></i> Editar Ficha y Atributos
        </a>
        <a href="" id="btn-switch-npc" class="rpg-action-btn rpg-btn-primary">
          <!-- Activar/Desactivar -->
        </a>
        <a href="" id="btn-delete-npc" class="rpg-system-tab-btn rpg-staff-btn-danger"
           onclick="return confirm('¿Seguro?')">
          <i class="fas fa-trash-alt"></i> Eliminar NPC
        </a>
      </div>
    </div>
  </div>
</div>
```

### 6.6 JS de la Zona Staff

Archivo: `jscripts/game/zona_staff_npc.js`

El JS se encarga de:
1. Capturar clic en botones `.edit-npc-btn`.
2. Leer los atributos `data-*` del botón.
3. Poblar el modal (`npc-editor-modal`) con los datos.
4. Configurar enlaces:
   - `btn-edit-npc-link`: `crear_personaje.php?pj_id=N`.
   - `btn-switch-npc`: Según estado activo, muestra "Activar" o "Desactivar".
   - `btn-delete-npc`: `zona_staff_npc.php?action=delete&id=N`.
5. Inicializar el overlay modal (`rpg_modal.js`).

---

## 7. Sistema de Asignación a Narradores

### 7.1 Tabla `game_npc_assignments`

```sql
CREATE TABLE mybb_game_npc_assignments (
    character_id INT NOT NULL,   -- ID del NPC (game_personajes.id)
    narrator_id  INT NOT NULL,   -- ID del narrador (game_personajes.id)
    PRIMARY KEY (character_id, narrator_id),
    INDEX idx_narrator_id (narrator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Propósito:** Vincular NPCs Mayores con personajes narradores que pueden controlarlos.

**Claves:**
- `character_id`: El personaje NPC que se asigna.
- `narrator_id`: El personaje del narrador QUE CONTROLA al NPC.

**Filosofía de la PK compuesta:** Un NPC puede tener múltiples narradores (varios personajes pueden controlar al mismo NPC en distintas tramas). Un narrador puede controlar múltiples NPCs. La relación es M:N.

### 7.2 ¿Qué es un Narrador?

Un narrador es un personaje que tiene:
- `is_narrator = 1` en `game_personajes`.
- Su usuario asociado tiene `is_narrator = 1` en `game_user_config`.
- NO necesita tener `staff_level` alto (puede ser un jugador normal con permisos narrativos).

**¿Qué puede hacer un narrador?**
1. Editar la ficha de los NPCs asignados (via `PersonajeRepository::findByIdForUser()`).
2. Postear como ese NPC en hilos.
3. Gestionar el inventario y deck del NPC.
4. NO puede editar NPCs que no tiene asignados.

### 7.3 Flujo de Asignación

**StaffAccountService** (`game/src/Application/Services/StaffAccountService.php`) gestiona las asignaciones:

```php
// Asignar NPC a narrador
$this->db->write_query("DELETE FROM {$this->prefix}game_npc_assignments
    WHERE narrator_id = {$targetUid}");

foreach ($npcIds as $charId) {
    $this->db->write_query("INSERT INTO {$this->prefix}game_npc_assignments
        (character_id, narrator_id) VALUES ({$charId}, {$targetUid})");
}
```

**¿Dónde se gestiona?** En `zona_staff_cuentas.php` — el staff de nivel 3 puede ver qué personajes son narradores y asignarles NPCs.

### 7.4 Verificación de Permisos en PersonajeRepository

```php
public function findByIdForUser(int $characterId, int $userId): ?array
{
    // 1. ¿Es el dueño directo?
    $q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$characterId} AND user_id = {$userId} LIMIT 1");
    if ($row = $db->fetch_array($q)) return $row;

    // 2. ¿Es superadmin y es NPC?
    $npc_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$characterId} AND is_npc = 1 LIMIT 1");
    if ($npc = $db->fetch_array($npc_q)) {
        $staff_q = $db->query("SELECT COUNT(*) as cnt FROM {$prefix}game_personajes WHERE user_id = {$userId} AND staff_level = 3");
        if ((int)$db->fetch_field($staff_q, 'cnt') > 0) return $npc;

        // 3. ¿Es narrador asignado?
        $narr_q = $db->query("SELECT COUNT(*) as cnt FROM {$prefix}game_user_config uc
            INNER JOIN {$prefix}game_npc_assignments a ON uc.user_id = a.narrator_id
            WHERE uc.user_id = {$userId} AND uc.is_narrator = 1 AND a.character_id = {$characterId}");
        if ((int)$db->fetch_field($narr_q, 'cnt') > 0) return $npc;
    }

    return null;
}
```

**Orden de verificación:** Dueño → Superadmin → Narrador asignado. Esta prioridad asegura que:
- El dueño directo siempre tiene acceso (aunque sea NPC).
- El superadmin tiene acceso universal a todos los NPCs.
- El narrador solo tiene acceso a los asignados.

### 7.5 Mostrar NPCs Asignados al Narrador

En `mis_personajes.php`, los narradores ven los NPCs que tienen asignados como si fueran personajes propios:

```php
// En la query de personajes del usuario:
if ($is_narrator) {
    $query = "
        SELECT p.*
        FROM {$prefix}game_personajes p
        INNER JOIN {$prefix}game_npc_assignments a ON p.id = a.character_id
        WHERE a.narrator_id = ?
        UNION
        SELECT p.*
        FROM {$prefix}game_personajes p
        WHERE p.user_id = ?
    ";
}
```

Esto permite al narrador cambiar su PJ activo a un NPC asignado y postear con él.

---

## 8. Sistema de Narradores en `game_user_config`

### 8.1 Esquema

```sql
CREATE TABLE mybb_game_user_config (
    user_id      INT PRIMARY KEY,
    max_slots    INT NOT NULL DEFAULT 1,
    slots_used   INT NOT NULL DEFAULT 0,
    active_pj_id INT DEFAULT NULL,
    is_narrator  TINYINT(1) NOT NULL DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 8.2 El Flag `is_narrator`

- `is_narrator = 0`: Usuario normal. No puede controlar NPCs.
- `is_narrator = 1`: Usuario narrador. Puede tener NPCs asignados y postear con ellos.

**¿Quién activa este flag?** Solo staff nivel 3, desde `zona_staff_cuentas.php`. No es un permiso que los jugadores puedan autoasignarse.

### 8.3 Diferencia entre Narrador y Staff Nivel 3

| Aspecto | Staff Nivel 3 | Narrador |
|---------|:-------------:|:--------:|
| Acceso a zona staff | Completo | No |
| Gestionar todos los NPCs | Sí | No (solo asignados) |
| Crear/Eliminar NPCs | Sí | No |
| Editar ficha de NPC | Sí (todos) | Sí (asignados) |
| Activar NPC para postear | Sí (todos) | Sí (asignados) |
| Acceso a tools administrativas | Sí | No |

**Filosofía:** El narrador es un jugador de CONFIANZA pero no necesariamente staff. Puede controlar NPCs para facilitar tramas, pero no tiene poder administrativo sobre el sistema. Esto permite delegar control narrativo sin expandir el equipo staff.

---

## 9. Permisos y Control de Acceso

### 9.1 Matriz Completa de Permisos de NPC

| Operación | Superadmin (3) | Staff (1-2) | Narrador asignado | Dueño directo |
|-----------|:--------------:|:-----------:|:-----------------:|:-------------:|
| Ver NPC en biblioteca | ✓ | ✓ | ✓ | ✓ |
| Ver ficha completa del NPC | ✓ | ✓ | ✓ | ✓ (si tiene user_id) |
| Editar ficha de NPC | ✓ | - | ✓ | - |
| Editar avatar/firma de NPC | ✓ | - | ✓ | - |
| Crear NPC Mayor | ✓ | - | - | - |
| Eliminar NPC | ✓ | - | - | - |
| Activar NPC como PJ activo | ✓ | - | ✓ | - |
| Asignar narradores a NPC | ✓ | - | - | - |
| Postear como NPC | ✓ | - | ✓ | - |
| Gestionar inventario/deck de NPC | ✓ | - | ✓ | - |

### 9.2 Verificaciones Clave en Código

**En `zona_staff_npc.php`:**
```php
// Solo staff nivel 3 puede acceder a la página
if ($staff_level < 3) { header('Location: ../index.php'); exit; }
```

**En `_sidebar.php` (edición de ficha):**
```php
if ((int)$char['is_npc'] === 1) {
    // Verificar si es superadmin
    $staff_check_q = $db->query("SELECT COUNT(*) as cnt FROM {$prefix}game_personajes
        WHERE user_id = {$user_id} AND staff_level = 3");
    if ($db->fetch_field($staff_check_q, 'cnt') > 0) {
        $can_edit_this_pj = true;
    } else {
        // Verificar si es narrador asignado
        $assign_check_q = $db->query("SELECT COUNT(*) as cnt FROM {$prefix}game_npc_assignments
            WHERE character_id = " . (int)$char['id'] . " AND narrator_id = {$user_id}");
        if ($db->fetch_field($assign_check_q, 'cnt') > 0) {
            $can_edit_this_pj = true;
        }
    }
}
```

**En `PersonajeRepository::findByIdForUser()`:** Misma lógica (ver sección 7.4).

### 9.3 Seguridad en Borrado

```php
// Cláusula AND is_npc = 1 protege contra borrado de PJs reales
$db->write_query("DELETE FROM {$prefix}game_personajes
    WHERE id = {$delete_id} AND is_npc = 1");
```

Además, el JS muestra un `confirm('¿Seguro que deseas eliminar este NPC?')` preventivo antes de enviar la petición.

---

## 10. Integración con el Sidebar de Personaje

### 10.1 Flujo en `_sidebar.php`

Cuando se carga la ficha de un NPC Mayor en `personaje.php`, el sidebar determina si el usuario actual puede editar al personaje:

```
1. ¿Es el dueño? (user_id == actual) → sí
2. ¿Es NPC? → continuar
   a. ¿Staff nivel 3? → sí
   b. ¿Narrador asignado? → sí
3. Sino → no puede editar
```

### 10.2 Fragmento Relevante

En `views/personaje/_sidebar.php:59-75`:

```php
$can_edit_this_pj = false;
if ($user_id > 0) {
    if ((int)$char['user_id'] === $user_id) {
        $can_edit_this_pj = true;
    } elseif ((int)$char['is_npc'] === 1) {
        $staff_check_q = $db->query("SELECT COUNT(*) as cnt
            FROM {$prefix}game_personajes WHERE user_id = {$user_id} AND staff_level = 3");
        if ($db->fetch_field($staff_check_q, 'cnt') > 0) {
            $can_edit_this_pj = true;
        } else {
            $assign_check_q = $db->query("SELECT COUNT(*) as cnt
                FROM {$prefix}game_npc_assignments
                WHERE character_id = " . (int)$char['id'] . " AND narrator_id = {$user_id}");
            if ($db->fetch_field($assign_check_q, 'cnt') > 0) {
                $can_edit_this_pj = true;
            }
        }
    }
}
```

**Filosofía del orden:** Primero se verifica si es el dueño (el camino más rápido). Luego si es NPC. Luego si es staff nivel 3 (el permiso más amplio). Luego si es narrador asignado (el permiso más específico). Esto minimiza queries innecesarias en el caso común (PJ propio).

### 10.3 Botones que Aparecen Según Permisos

Si `$can_edit_this_pj` es true:
- Si el personaje NO está aprobado ni muerto → "Editar Ficha Completa" (enlace a wizard de edición).
- Si está aprobado o muerto → "Editar Avatar / Firma" (solo cambios superficiales).

Para NPCs, como siempre están `aprobada`, solo se muestra "Editar Avatar / Firma" desde el sidebar. La edición completa de la ficha se hace desde `zona_staff_npc.php`.

---

## 11. Flujo de Creación de un NPC Mayor

### 11.1 Diagrama de Secuencia

```
Staff (nivel 3) → Navegador → Servidor → MySQL

1. GET /zona_staff_npc.php
   → Verificar staff_level >= 3
   → Mostrar lista de NPCs existentes + botón "Crear NPC Mayor"

2. Staff hace clic en "Crear NPC Mayor"
   → GET /crear_personaje.php?is_npc=1
   → Wizard de creación con flag NPC

3. Staff llena wizard (igual que PJ normal, 3 pasos)

4. POST /save_personaje.php (JSON)
   → CharacterSaveService::buildPayloadForInsert()
     → Detectar is_npc=1 → saltar revisión
     → No asignar user_id (NULL)
     → Status directamente 'aprobada'
     → approved = 1
   → INSERT game_personajes (con is_npc=1)
   → NO actualiza game_user_config (no consume slots)
   → NO envía notificación de creación

5. Redirigir a personaje.php?pj=N
```

### 11.2 Diferencias con Creación de PJ Normal

| Paso | PJ Normal | NPC Mayor |
|------|-----------|-----------|
| Verificación de slots | Sí (slots_used < max_slots) | No (los NPCs no ocupan slots) |
| user_id | ID del creador | NULL |
| Status | `pendiente` | `aprobada` |
| Revisión staff | Obligatoria | No aplica (ya es staff quien crea) |
| Notificación | MP al usuario | No |
| game_user_config | slots_used++ | Sin cambios |

### 11.3 Precondiciones

1. Staff autenticado con PJ activo de staff_level >= 3.
2. El PJ activo del staff debe ser el que ejecuta la creación (no se puede crear NPC desde un PJ no-staff aunque el usuario tenga otro PJ staff).

### 11.4 Postcondiciones

- Nuevo registro en `game_personajes` con `is_npc = 1`, `status = 'aprobada'`.
- Datos en `data_json`, `stats_json` según el wizard.
- NO hay entrada en `game_personajes_revisiones` (no pasa por revisión).
- NO hay notificación.

---

## 12. Flujo de Edición de un NPC Mayor

### 12.1 Desde la Zona Staff

```
1. GET /zona_staff_npc.php
2. Staff hace clic en "Editar" en la fila del NPC
3. Modal muestra resumen + stats + acciones
4. Staff hace clic en "Editar Ficha y Atributos"
5. Redirige a /crear_personaje.php?pj_id=N
6. Wizard precargado con datos actuales del NPC
7. Staff modifica lo necesario
8. POST /save_personaje.php
   → CharacterSaveService::buildPayloadForUpdate()
   → Preserva FORBIDDEN_DATA_KEYS
   → UPDATE game_personajes
```

### 12.2 Edición Directa vía Ficha

Si el narrador o staff accede a `personaje.php?pj=N` y tiene permisos:
- Ve el botón "Editar Avatar / Firma" en el sidebar.
- Puede cambiar avatar, firma, y detalles menores.
- Para cambios estructurales (stats, historia, psicología), debe usar la zona staff.

### 12.3 Protección FORBIDDEN_DATA_KEYS

```php
const FORBIDDEN_DATA_KEYS = [
    'pp', 'pp_linaje', 'nivel', 'rank', 'stat_points_purchased',
    'pp_spent_eligible', 'last_level_up_at', 'is_staff', 'staff_level', 'approved'
];
```

Al editar un NPC, el staff no puede (y no necesita) modificar estas keys. Los NPCs no tienen progresión (no ganan PP, no suben de nivel). Si se quiere modificar los stats de un NPC, se edita `stats_json` directamente.

**Filosofía:** Los NPCs son ESTÁTICOS en su progresión. No ganan PP por posts, no suben de rango global, no compran stats. Si el staff quiere que un NPC sea más poderoso, edita manualmente sus stats.

---

## 13. Carga de Datos en el Visor

### 13.1 Mecanismo de `data-npc`

Cada card contiene un JSON embebido con todos los datos del NPC:

```php
$data_json = htmlspecialchars(json_encode([
    'nombre'      => $n['nombre'],
    'portrait'    => $n['imagen'],
    'apodos'      => $id['apodos'] ?? [],
    'edad'        => $id['edad'] ?? '',
    'raza'        => $id['raza'] ?? '',
    'afiliacion'  => $id['afiliacion'] ?? '',
    'ocupacion'   => $id['ocupacion'] ?? '',
    'estado'      => $id['estado_actual'] ?? '',
    'stats'       => $n['stats'],
    'history'     => $n['history'] ?? '',
    'link'        => $link,
]), ENT_QUOTES, 'UTF-8');
```

### 13.2 Procesamiento en npc.js

El JS:
1. Escucha clics en `.rpg-lib-card`.
2. Lee `data-npc` del elemento clickeado.
3. Parsea el JSON.
4. Puebla el modal:
   - `#modal-title`: nombre.
   - `#modal-badge`: afiliación.
   - `#modal-portrait`: src de la imagen.
   - `#modal-stats-list`: render de cada stat como barra.
   - `#modal-history`: texto de historia.
   - `#modal-info-stats`: edad, raza, etc.
   - `#modal-link-ficha`: href al enlace (si es major NPC).
5. Abre el modal.

### 13.3 Manejo de Errores

```php
try {
    // Queries
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Error al cargar NPCs</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}
```

**Filosofía:** Si falla la carga de NPCs, es mejor mostrar un error claro que una página rota. El catch con `Throwable` captura cualquier error (incluyendo errores fatales de PHP 7+).

---

## 14. Normalización de Stats

### 14.1 Función `normalize_npc_stats`

```php
function normalize_npc_stats(array $stats): array {
    if (!class_exists('\\Game\\Shared\\StatScale')) {
        require_once __DIR__ . '/../src/autoload.php';
    }
    return \Game\Shared\StatScale::sanitizeRanks($stats);
}
```

### 14.2 StatScale::sanitizeRanks

```php
public static function sanitizeRanks(array $stats): array {
    $default = [
        'fue'  => 1,
        'res'  => 1,
        'agi'  => 1,
        'des'  => 1,
        'int'  => 1,
        'inst' => 1,
        'esp'  => 1,
    ];
    foreach ($default as $k => $v) {
        $default[$k] = isset($stats[$k])
            ? max(1, min(9, (int)$stats[$k]))
            : $v;
    }
    return $default;
}
```

**¿Qué hace?**
1. Define defaults: todos los stats a 1 (mínimo).
2. Para cada stat presente en el input, lo convierte a entero y lo clamp entre 1 y 9.
3. Para stats faltantes, usa el default (1).

**¿Por qué rango 1-9 para NPCs cuando los PJs usan 1-6?**
- Los PJs están limitados a rango 6 (máximo alcanzable con PP). Los NPCs representan personajes que pueden estar FUERA del sistema de progresión de PJs. Un Emperador como Kaido debe tener stats de 8 para que se vea claramente superior a un PJ de rango 5-6.
- El rango 9 existe como reserva para seres divinos o ancestrales (Im-sama, Joy Boy).

### 14.3 Stats Efectivos vs Stats Base (para Major NPCs)

Para los NPCs Mayores (desde `game_personajes`), los stats pasan por un paso adicional: se les aplican bonificaciones raciales si existe la función `game_build_stat_context()`.

```php
if ($raceName !== '' && function_exists('game_build_stat_context')) {
    $ctx = game_build_stat_context($stats, $raceName);
    $mapped_stats = [];
    foreach (['fue', 'res', 'agi', 'des', 'int', 'inst', 'esp'] as $sk) {
        $mapped_stats[$sk] = $ctx['effective_ranks'][$sk] ?? 1;
    }
} else {
    // Fallback: clamp directo
    $mapped_stats = [];
    foreach (['fue', 'res', 'agi', 'des', 'int', 'inst', 'esp'] as $sk) {
        $rk = max(1, min(9, (int)($stats[$sk] ?? 5)));
        $mapped_stats[$sk] = $rk;
    }
}
```

**Filosofía:** Los NPCs estáticos (`game_npc_profiles`) ya tienen sus stats definitivos en la columna `stats`. Los NPCs Mayores (`game_personajes`) usan el mismo sistema que los PJs (stats base + bonos raciales). Esto significa que un NPC Mayor Gyojin tendrá FUE+2 automáticamente, igual que un PJ Gyojin.

---

## 15. Resolución de Avatares

### 15.1 Función `resolve_avatar`

```php
function resolve_avatar(?string $path, string $bb): string {
    if (!$path || trim($path) === '') {
        return $bb . '/images/default_avatar.png';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return rtrim($bb, '/') . '/' . ltrim($path, '/');
}
```

**Lógica:**
1. Si la ruta está vacía o es nula → default avatar.
2. Si es URL absoluta (http/https) → se usa tal cual.
3. Si es ruta relativa → se concatena con `$mybb->settings['bburl']`.

### 15.2 Función `pj_img_url` (en zona_staff)

```php
function pj_img_url(string $path, string $bb): string {
    if ($path === '') return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) return $path;
    return rtrim($bb, '/') . '/' . ltrim($path, '/');
}
```

Versión simplificada que devuelve `''` en lugar del default. Se usa en contextos donde la ausencia de imagen se maneja con un placeholder CSS.

---

## 16. Clasificación de Facción

### 16.1 Función `get_standard_faction`

```php
function get_standard_faction(?string $faction): string {
    if (!$faction) return 'Civil';
    $fac = mb_strtolower(trim($faction));
    if (strpos($fac, 'marine') !== false || strpos($fac, 'marina') !== false) return 'Marine';
    if (strpos($fac, 'revolucion') !== false) return 'Revolucionario';
    if (strpos($fac, 'gobierno') !== false) return 'Gobierno';
    if (strpos($fac, 'cazador') !== false) return 'Cazador';
    if (strpos($fac, 'civil') !== false) return 'Civil';
    if (strpos($fac, 'pirata') !== false || strpos($fac, 'paja') !== false ||
        strpos($fac, 'guild') !== false || strpos($fac, 'kuro') !== false) return 'Pirata';
    return 'Civil';
}
```

**Propósito:** Clasificar la afiliación del NPC en una de las 6 facciones estándar para el filtro de la biblioteca.

**Mecanismo:**
1. Si no hay facción → Civil (default).
2. Busca palabras clave en el texto de afiliación usando `mb_strpos` (multibyte-safe).
3. Detecta variantes: "Marina" → Marine, "Revolucionarios" → Revolucionario.

**Filosofía del matching flexible:** La afiliación en el JSON es texto libre (ej: "Piratas de las Bestias"). En lugar de forzar un slug controlado, el sistema hace matching por palabras clave. Esto permite:
- "Marina" → Marine.
- "Piratas de las Bestias" → Pirata.
- "Ejército Revolucionario" → Revolucionario.
- "CP0" → Gobierno (si no tiene "gobierno" en el texto, se clasifica como Civil).

**Limitación conocida:** "CP0" no contiene "gobierno" → se clasifica como Civil. Para estos casos, el campo `faccion_slug` en `identificacion` debería usarse, pero actualmente no se procesa. El staff debe asegurarse de que la afiliación incluya palabras clave clasificables.

### 16.2 Facción en NPCs Mayores

Para NPCs Mayores, la facción se lee directamente del campo `faction` de `game_personajes`:

```php
'faction' => get_standard_faction($row['faction']),
```

El campo `faction` en `game_personajes` es VARCHAR(100), normalmente un slug como `"pirata"`, `"marine"`, etc. `get_standard_faction()` lo procesa igual que el texto libre.

---

## 17. Filosofía de Diseño

### 17.1 Principios Rectores

1. **Los NPCs son CIUDADANOS del mundo, no PROPIDAD del staff.** Cualquiera puede ver sus fichas en la biblioteca. Su información es pública porque el lore del foro es compartido.

2. **Los NPCs marcan el techo de poder.** Los PJs pueden aspirar a ser como ellos, pero los NPCs existen primero. Si Kaido tiene FUE 8, ningún PJ nuevo puede empezar con FUE 8. Es un recordatorio visual del poder establecido del mundo.

3. **Separación narrativa vs mecánica.** Los NPCs estáticos (game_npc_profiles) son LORE. Los NPCs Mayores (game_personajes) son HERRAMIENTAS NARRATIVAS. Los primeros existen para ser leídos; los segundos, para ser usados.

4. **Control granular, no binario.** No es "staff puede todo / jugador no puede nada". Los narradores son un punto intermedio: pueden controlar NPCs específicos sin tener poder administrativo. Esto permite distribuir la carga narrativa.

5. **Los NPCs no progresan.** Un PJ gana PP, sube stats, compra cartas. Un NPC es estático. Si se vuelve más poderoso, es porque el staff lo edita manualmente, no porque "roleó". Esto refuerza que los NPCs son fuerzas de la naturaleza, no competidores de los PJs.

### 17.2 Decisiones Clave y su Porqué

| Decisión | Alternativa descartada | Por qué se eligió así |
|----------|----------------------|----------------------|
| Dos tablas (game_npc_profiles + game_personajes) | Una sola tabla con todos los NPCs | Separar estáticos (solo lore) de jugables (que postean) evita complejidad innecesaria en permisos y queries |
| JSON columns separadas en game_npc_profiles | Un único JSON gigante | Claridad semántica, updates parciales, correspondencia 1:1 con la guía MAESTRO |
| Staff_level 3 para crear NPCs | Staff_level 1 (cualquier mod) | Los NPCs Mayores pueden postear y afectar tramas. Solo los administradores deben tener ese poder |
| Narradores sin staff_level | Narradores = staff nivel 2 | Un jugador de confianza puede controlar NPCs sin tener poder de moderación. Es un permiso narrativo, no administrativo |
| Stats de NPCs hasta 9 (vs 6 de PJs) | Mismo límite que PJs | Los NPCs deben ser OBJETIVAMENTE superiores para marcar el techo del sistema |
| Borrado seguro con AND is_npc=1 | DELETE directo por ID | Prevención contra desastres: un error en el ID no borrará un PJ real |
| JSON embebido en data-npc | AJAX al hacer clic | Instantaneidad en el modal. Aceptable para <100 NPCs |

### 17.3 ¿Por qué Dos Tipos de NPC?

**Argumento a favor de unificarlos:** Ambos son personajes no jugables. ¿Por qué no poner todos los NPCs en `game_personajes` con `is_npc=1`?

**Respuesta: Porque los propósitos son distintos.**

| Aspecto | game_npc_profiles | game_personajes (is_npc=1) |
|---------|-------------------|---------------------------|
| ¿Puede postear? | No | Sí |
| ¿Tiene ficha editable? | No (solo vista pública) | Sí (ficha completa) |
| ¿Tiene user_id? | No aplica | Normalmente NULL |
| ¿Requiere staff para crear? | Sí (solo DB directa) | Sí (staff nivel 3 vía wizard) |
| ¿Cuántos puede haber? | Ilimitados (cientos) | Limitados (decenas, son activos) |
| ¿Afecta al balance del juego? | No (solo lore) | Sí (puede interactuar con PJs) |
| ¿Se actualiza con la trama? | Rara vez | Sí, frecuentemente |

**Analogía:** Los NPCs de `game_npc_profiles` son como las entradas de una enciclopedia. Los de `game_personajes` son como personajes secundarios en una obra de teatro. Ambos son "no protagonistas", pero cumplen funciones radicalmente distintas.

---

## 18. Decisiones Técnicas Clave

### 18.1 ¿Por qué `resolve_avatar()` usa `preg_match('#^https?://#i')` en lugar de `filter_var($path, FILTER_VALIDATE_URL)`?

Porque `FILTER_VALIDATE_URL` es demasiado restrictivo. URLs con caracteres Unicode, parámetros complejos o ciertos formatos de imagen pueden ser rechazados por el filtro de PHP. La regex `^https?://` es más permisiva y captura el caso de uso real: "esto es una URL absoluta".

### 18.2 ¿Por qué `htmlspecialchars($data_json, ENT_QUOTES)` antes de embeber JSON en HTML?

Porque el JSON se inserta como atributo `data-npc='...'` en el HTML. Si contiene comillas simples (`'`), romperían el HTML. `ENT_QUOTES` escapa tanto comillas dobles como simples:

```html
<div data-npc='{"nombre": "Kaido", "apodos": ["El Rey de las Bestias"]}'>
```

Sin `ENT_QUOTES`, un apodo como `"D'raven"` contendría una comilla simple y rompería el HTML.

### 18.3 ¿Por qué `$data_json` se asigna ANTES de la card y no dentro del string HTML?

Porque la variable `$data_json` pasa por `htmlspecialchars()` una vez. Si se hiciera dentro del string, habría que escaparlo en cada card. Es una micro-optimización de legibilidad y rendimiento.

### 18.4 ¿Por qué el modal NO carga datos vía AJAX?

Para NPCs estáticos de biblioteca, el contenido no cambia entre visitas. No hay razón para hacer una petición HTTP adicional cuando todos los datos ya están en el DOM. La experiencia de usuario es mejor (el modal se abre instantáneamente) y se reduce la carga del servidor.

Si en el futuro la biblioteca tuviera 500+ NPCs, se podría considerar:
- Paginación (solo mostrar 50 NPCs por página).
- Carga perezosa del modal (solicitar datos del NPC clickeado vía AJAX).
- Búsqueda server-side (en lugar de filtrar client-side).

### 18.5 ¿Por qué la tabla `game_npc_assignments` usa `narrator_id` como INT referencia a `game_personajes.id` y no a `mybb_users.uid`?

Porque el sistema de permisos de RPG se basa en PERSONAJES, no en usuarios de MyBB. Un usuario puede tener múltiples personajes, y solo algunos son narradores. La asignación de NPCs es a nivel de personaje narrador, no de cuenta de foro.

```sql
-- Así se relaciona:
game_npc_assignments.narrator_id → game_personajes.id (del narrador)
game_npc_assignments.character_id → game_personajes.id (del NPC)
```

### 18.6 ¿Por qué no hay UNIQUE constraint en `game_npc_profiles.nombre`?

Porque en el lore puede haber personajes con el mismo nombre (homónimos, o versiones alternativas). El `INDEX idx_npc_nombre` existe solo para acelerar búsquedas, no para forzar unicidad.

---

## 19. Consejos para Staff

### 19.1 Creando NPCs Estáticos (game_npc_profiles)

**Planifica las secciones antes de escribir.** Un NPC bien construido tiene:
- `identificacion`: Datos básicos. Debe ser suficiente para identificar al personaje de un vistazo.
- `perfil_fisico`: Suficiente detalle para que alguien pueda rolear su apariencia sin ver una imagen.
- `psicologia`: La sección más importante. Define cómo reacciona el NPC ante situaciones.
- `perfil_estrategico`: Solo si el NPC puede entrar en combate. Si es un civil, puede ser minimalista.
- `cronologia`: No necesita ser exhaustiva. Los eventos con `importancia: "critica"` son los únicos obligatorios.
- `stats`: Sé realista. No todos los NPCs necesitan stats de 8. Un marine raso tiene stats de 2-3.

**Regla de consistencia:** Antes de crear un NPC estático, verifica si ya existe un NPC Mayor equivalente. No dupliques personajes. Si un personaje existe en `game_personajes` como NPC Mayor, no crees una entrada estática para él en `game_npc_profiles`.

### 19.2 Creando NPCs Mayores (game_personajes, is_npc=1)

**Define el propósito del NPC antes de crearlo.** Pregunta:
- ¿Para qué trama se necesita?
- ¿Quién lo va a controlar? (¿Staff? ¿Narrador asignado?)
- ¿Qué nivel de poder debe tener en relación con los PJs actuales?

**Asigna stats proporcionados al contexto.** Un NPC que los PJs de rango C deben enfrentar no debería tener stats de 7+. Pero un Emperador contra el que nadie puede pelear aún, sí.

**No crees NPCs que solucionen tramas.** Un NPC todopoderoso que aparece a resolver problemas le quita agencia a los PJs. Los NPCs deben crear CONFLICTO, no resolverlo.

### 19.3 Gestionando Narradores

**Elige narradores de confianza.** Un narrador puede:
- Editar la ficha del NPC.
- Postear como el NPC.
- Tomar decisiones narrativas que afectan a todos los PJs involucrados.

**Asigna NPCs específicos, no acceso general.** No des acceso a todos los NPCs. Asigna solo aquellos que el narrador necesita para sus tramas.

**Revoca asignaciones cuando la trama termina.** Si un arco narrativo concluye, el narrador ya no necesita controlar ese NPC. Las asignaciones deben revisarse periódicamente.

### 19.4 Manteniendo NPCs

**Actualiza la cronología después de eventos importantes.** Si el NPC participó en una batalla crucial, añade el evento a su `cronologia`.

**Revisa las motivaciones periódicamente.** Un NPC que completa un objetivo de corto plazo necesita uno nuevo. Un NPC que fracasa estrepitosamente puede cambiar sus prioridades.

**No tengas miedo de matar NPCs.** Un NPC que muere en una trama es un NPC que ha cumplido su propósito narrativo. No lo resucites a menos que la historia lo justifique.

**Documenta cambios en `notas_para_staff`.** Cada sección que tenga este campo debe usarse para comunicación entre staff: "Este NPC fue herido en la trama X, su stats bajaron temporalmente", "Revisar después del arco de Y".

### 19.5 Buenas Prácticas de JSON

- **Usa `null` para campos desconocidos, no cadenas vacías.** Es más semántico: `"edad": null` vs `"edad": ""`.
- **Arrays vacíos para listas sin elementos:** `"miedos": []` es mejor que omitir la key.
- **No anides más de 3 niveles.** Si un JSON se vuelve demasiado profundo, considéra dividirlo en secciones separadas.
- **Valida el JSON antes de insertarlo.** Un JSON malformado rompe el visor. Usa `json_last_error()` en PHP para verificar.
- **Usa UTF-8 sin BOM.** Los acentos y caracteres especiales (ñ, ¿, ¡) son comunes en español. El charset de la tabla es `utf8mb4`.

### 19.6 Flujo de Trabajo Recomendado

```
1. Identificar necesidad narrativa
2. Elegir tipo: ¿estático (solo lore) o Mayor (interactivo)?
3. Si es Mayor:
   a. Crear via wizard (crear_personaje.php?is_npc=1)
   b. Setear stats y ficha básica
   c. Asignar narrador si aplica (zona_staff_cuentas.php)
4. Si es estático:
   a. Preparar JSON de cada sección
   b. Insertar directamente en game_npc_profiles (vía SQL o herramienta admin)
5. Verificar en visor público (npc.php)
6. Monitorear uso y actualizar según trama
```

---

## 20. Guía de Troubleshooting

### 20.1 "El NPC no aparece en la biblioteca"

**Causas posibles:**
1. Si es NPC estático: `INSERT` falló, no hay registro en `game_npc_profiles`.
2. Si es NPC Mayor: `is_npc` no está en 1, o `status` no está en `aprobada`.
3. Error de PHP en `npc.php` (catch silencioso o error antes del render).

**Verificar:**
```sql
-- NPCs estáticos
SELECT id, nombre FROM mybb_game_npc_profiles;

-- NPCs Mayores
SELECT id, name, is_npc, status, approved FROM mybb_game_personajes WHERE is_npc = 1;
```

### 20.2 "El modal no se abre al hacer clic en la card"

**Causas:**
1. `npc.js` no se cargó (revisar consola del navegador).
2. El JSON en `data-npc` está malformado (HTML inválido).
3. Error de JavaScript (tipicamente `JSON.parse` falla).

**Verificar:**
- Abrir herramientas de desarrollador → Consola.
- Si hay error de JSON, inspeccionar el atributo `data-npc` en el HTML.
- Verificar que `htmlspecialchars()` no haya escapado caracteres que rompen el JSON.

### 20.3 "No puedo editar la ficha del NPC"

**Causas:**
1. No tienes staff_level 3 en tu PJ activo.
2. No eres narrador asignado.
3. El NPC no tiene `is_npc = 1`.
4. Estás usando un PJ que no es el activo.

**Verificar:**
```sql
-- Tu personaje activo
SELECT uc.active_pj_id, p.name, p.staff_level, p.is_narrator
FROM mybb_game_user_config uc
JOIN mybb_game_personajes p ON p.id = uc.active_pj_id
WHERE uc.user_id = [TU_UID];

-- Tus asignaciones de narrador
SELECT character_id FROM mybb_game_npc_assignments
WHERE narrator_id = [TU_PERSONAJE_ID];
```

### 20.4 "El avatar del NPC no se muestra"

**Causas:**
1. Ruta incorrecta en `imagen` (para estáticos) o `avatar` (para Mayores).
2. `resolve_avatar()` devuelve default porque la ruta está vacía.
3. La URL es relativa pero el `bburl` está mal configurado.

**Verificar:**
- Inspeccionar el elemento `img` en el HTML. ¿Qué src tiene?
- Si es ruta relativa, ¿existe el archivo en el servidor?
- Si es URL absoluta, ¿es accesible públicamente?

### 20.5 "Los stats del NPC se ven raros"

**Causas:**
1. JSON de stats malformado en la DB.
2. `normalize_npc_stats()` está clampando a 1-9 pero el stat esperado era 0 o negativo.
3. Para NPCs Mayores: `game_build_stat_context()` podría estar aplicando bonos raciales incorrectos.

**Verificar:**
```php
// Debug temporal en npc.php
error_log(print_r(json_decode($row['stats'], true), true));
error_log(print_r(normalize_npc_stats(json_decode($row['stats'], true) ?: []), true));
```

### 20.6 "El filtro por facción no funciona"

**Causas:**
1. `get_standard_faction()` no reconoce la afiliación (ej: "CP0" no contiene "gobierno").
2. El JS de filtro no encuentra coincidencias.

**Solución temporal:** Añadir una palabra clave reconocible a la afiliación, o modificar `get_standard_faction()` para incluir el nuevo caso.

**Solución permanente:** Usar el campo `faccion_slug` en `identificacion` y modificar el visor para que lo lea como facción primaria, con `get_standard_faction()` como fallback.

### 20.7 Error de Migración

Si la migración de NPCs falla:

```
=== Migración: is_npc ===
[--] Columna 'is_npc' ya existe
```

No es un error real, es un mensaje informativo. La migración es idempotente: si ya se ejecutó, simplemente lo notifica.

Si la tabla `game_npc_assignments` no se crea:

```sql
CREATE TABLE mybb_game_npc_assignments (
    character_id INT NOT NULL,
    narrator_id INT NOT NULL,
    PRIMARY KEY (character_id, narrator_id),
    INDEX idx_narrator_id (narrator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Ejecutar manualmente si la migración falló.

### 20.8 "El narrador no puede postear como el NPC"

**Causas:**
1. El narrador no tiene el NPC asignado en `game_npc_assignments`.
2. El narrador no tiene `is_narrator = 1` en `game_user_config`.
3. El narrador intenta postear sin tener el NPC como PJ activo.

**Flujo correcto para postear como NPC:**
1. Asegurarse de que `game_user_config.is_narrator = 1`.
2. Asignar NPC en `game_npc_assignments`.
3. Cambiar PJ activo al NPC (desde `mis_personajes.php` o `zona_staff_npc.php`).
4. Postear normalmente — el plugin `game_postcharacter.php` usará el PJ activo.

---

> **Documentación generada a partir de:**
> - `game/public/npc.php` — Visor de biblioteca
> - `game/public/zona_staff_npc.php` — Gestión de NPCs Mayores
> - `game/sql/install_schema_fragments.php` — Esquema completo
> - `game/sql/migrate_npc_system.php` — Migración is_npc
> - `game/sql/migrate_narrador_system.php` — Migración narradores
> - `game/views/personaje/_sidebar.php` — Sidebar (permisos NPC)
> - `game/src/Infrastructure/Persistence/PersonajeRepository.php` — Repositorio
> - `game/src/Shared/StatScale.php` — Normalización de stats
> - `Guias/MAESTRO_SISTEMAS_RPG.md` — Estructura maestra
