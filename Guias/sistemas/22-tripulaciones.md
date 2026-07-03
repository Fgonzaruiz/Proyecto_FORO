# 22. Sistema de Tripulaciones — Guía Completa

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 22
> **Propósito:** Documentar exhaustivamente el subsistema de tripulaciones: modelo de datos, AJAX endpoints, flujos de creación/unión/gestión, biblioteca pública, zona de staff, sistema diplomático, territorios, recuerdos, navíos, filosofía de diseño, y consejos para jugadores y staff.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [Modelo de Datos — Tabla `game_tripulaciones`](#2-modelo-de-datos)
3. [Tabla `game_tripulacion_miembros`](#3-tripulacion-miembros)
4. [Estructura de JSON Columns](#4-estructura-de-json-columns)
5. [Flujo de Creación](#5-flujo-de-creación)
6. [Flujo de Unión (Solicitud)](#6-flujo-de-unión)
7. [Gestión del Capitán](#7-gestión-del-capitán)
8. [Ficha de Tripulación — Vista Pública](#8-ficha-de-tripulación)
9. [Biblioteca de Tripulaciones](#9-biblioteca-de-tripulaciones)
10. [Zona de Staff](#10-zona-de-staff)
11. [Sistema de Territorios](#11-sistema-de-territorios)
12. [Sistema de Recuerdos](#12-sistema-de-recuerdos)
13. [Sistema Diplomático y Red de Alianzas](#13-sistema-diplomático)
14. [Sistema de Navío](#14-sistema-de-navío)
15. [JavaScript — Frontend Lógico](#15-javascript)
16. [Templates y Vistas PHP](#16-templates-y-vistas)
17. [AJAX Endpoints](#17-ajax-endpoints)
18. [Migraciones y Evolución del Esquema](#18-migraciones)
19. [Permisos y Seguridad](#19-permisos-y-seguridad)
20. [Filosofía de Diseño](#20-filosofía-de-diseño)
21. [Consejos para Jugadores](#21-consejos-para-jugadores)
22. [Consejos para Staff](#22-consejos-para-staff)
23. [Guía de Troubleshooting](#23-guía-de-troubleshooting)

---

## 1. Arquitectura General

### 1.1 Capas del Subsistema

```
┌───────────────────────────────────────────────────────────────────┐
│                        CLIENTE (Navegador)                         │
│  ┌──────────────────┐  ┌──────────────────┐  ┌────────────────┐   │
│  │ tripulacion_     │  │ tripulacion_     │  │ biblioteca_    │   │
│  │ crear.js         │  │ page.js          │  │ tripulaciones  │   │
│  │ (form creación)  │  │ (tabs + gestión) │  │ .js (filtros)  │   │
│  └───────┬──────────┘  └───────┬──────────┘  └───────┬────────┘   │
│          │                     │                      │            │
│          ▼                     ▼                      ▼            │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │              AJAX (game/ajax/*.php)                          │  │
│  │  crew_create.php | crew_join.php | crew_manage.php           │  │
│  └──────────────────────────┬───────────────────────────────────┘  │
└─────────────────────────────┼──────────────────────────────────────┘
                              │ HTTP POST + JSON
┌─────────────────────────────┼──────────────────────────────────────┐
│  ┌──────────────────────────▼───────────────────────────────────┐  │
│  │              PHP — VISTAS Y CAPA DE DATOS                    │  │
│  │  tripulacion.php | tripulacion_crear.php | biblioteca_       │  │
│  │  tripulaciones.php | peticion_tripulacion.php |              │  │
│  │  zona_staff_tripulaciones.php                                │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                              │                                      │
│                              ▼                                      │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │  MySQL (MyBB + tablas game_*)                                │  │
│  │  game_tripulaciones | game_tripulacion_miembros              │  │
│  │  game_forum_islands (territorios) | game_personajes (FK)     │  │
│  └──────────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────────┘
```

### 1.2 Filosofía de la Arquitectura

**Dos modalidades de creación:**
- **Rápida (AJAX + modal):** `crew_create.php` + `tripulacion_crear.php` — el capitán crea la crew directamente con estado `aprobada`. Sin revisión de staff.
- **Con petición:** `peticion_tripulacion.php` — inserta la tripulación con estado `pendiente`. El staff debe aprobarla desde `zona_staff_tripulaciones.php`.

**Separación de concerns:**
- `tripulacion.php` orquesta la carga de datos y renderiza la ficha completa.
- Las vistas parciales (`views/tripulacion/_tab_*.php`) se encargan de cada pestaña.
- `crew_manage.php` centraliza todas las acciones del capitán (aceptar, rechazar, expulsar, editar, recuerdos, relaciones).
- `game_network.js` gestiona el grafo diplomático (relaciones, grupos, conexiones).

**¿Por qué dos modalidades de creación?**
- La ruta rápida da autonomía a los jugadores para crear tripulaciones sin fricción, ideal para grupos pequeños que ya tienen acuerdo OOC.
- La ruta con petición permite al staff revisar trasfondos y evitar duplicados o nombres inapropiados. Es la vía formal y controlada.

**¿Por qué JSON column `memories` y `relations`?**
- Igual que en personajes: flexibilidad para añadir recuerdos o relaciones sin ALTER TABLE.
- `relations` guarda un objeto estructurado con `relations`, `groups` y `connections` para el grafo diplomático.
- `memories` guarda un array de objetos con `title`, `image`, `text`, `date`.

### 1.3 Integración con Personajes

Cada personaje tiene `tripulacion_id` en `game_personajes`, que es una FK lógica a `game_tripulaciones.id` (sin constraint formal para permitir eliminaciones en cascada manuales).

```sql
-- game_personajes.tripulacion_id se actualiza cuando:
-- 1. Se crea una crew (capitán)
-- 2. El capitán acepta un miembro
-- 3. El staff aprueba una crew (líder)
-- 4. Un miembro es expulsado (se pone NULL)
-- 5. Se disuelve la crew (todos a NULL)
```

---

## 2. Modelo de Datos — Tabla `game_tripulaciones`

### 2.1 Definición SQL Completa (Estado Actual)

```sql
CREATE TABLE mybb_game_tripulaciones (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    motto           VARCHAR(255) DEFAULT ''           -- v2
    image_url       VARCHAR(255) DEFAULT '',
    description     TEXT,
    relations       TEXT,                               -- extras
    ost_url         VARCHAR(500) DEFAULT '',            -- extras
    factions        VARCHAR(255) DEFAULT '',            -- v3
    ship_name       VARCHAR(150) DEFAULT '',            -- v3
    ship_image_url  VARCHAR(255) DEFAULT '',            -- v3
    ship_data       TEXT,                               -- v3
    leader_pj_id    INT DEFAULT NULL,
    status          VARCHAR(20) DEFAULT 'aprobada',
    memories        TEXT,                               -- v2 (añadido después como JSON)
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Nota:** La columna `memories` fue añadida posteriormente mediante migración directa. No aparece en `migrate_crews_v2.php` ni `v3.php`; se añadió en una migración intermedia o directamente en producción.

### 2.2 Campos — Descripción Detallada

#### `id` — Identificador único
- Autoincremental. Clave primaria.
- Referenciado por `game_tripulacion_miembros.tripulacion_id` y `game_forum_islands.controlling_id` (cuando `controlling_type = 'crew'`).
- Referenciado por `game_personajes.tripulacion_id`.

#### `name` — Nombre de la tripulación
- VARCHAR(150). Obligatorio. Único por convención (sin UNIQUE constraint formal).
- Se normaliza con `mb_substr(trim(...), 0, 150)` en todos los inputs.

#### `motto` — Lema
- VARCHAR(255). Añadido en la migración v2.
- Opcional. Se muestra entre comillas en el hero banner y la sidebar.
- Ej: "¡Hacia el Nuevo Mundo!", "La libertad no tiene precio".

#### `image_url` — Bandera / Jolly Roger
- VARCHAR(255). URL de la imagen de la bandera.
- Si está vacía, se muestra un placeholder con icono de calavera.
- Se usa como banner hero en la ficha y como thumbnail en la biblioteca.

#### `description` — Historia y filosofía
- TEXT. Descripción narrativa de la tripulación: origen, objetivos, código, etc.
- Se renderiza con `nl2br(htmlspecialchars(...))` en la pestaña de información.

#### `relations` — Relaciones diplomáticas (JSON)
- TEXT. Objeto JSON estructurado con tres claves:
  ```json
  {
    "relations": [
      {
        "id": "uuid",
        "name": "Tripulación X",
        "pj_id": 5,
        "is_npc": false,
        "tags": ["Aliado", "Compañero"],
        "desc": "Pacto comercial...",
        "image": "https://..."
      }
    ],
    "groups": [
      {
        "id": "uuid",
        "name": "Gran Flota",
        "color": "#10b981",
        "members": ["id1", "id2"]
      }
    ],
    "connections": [
      {
        "id": "uuid",
        "source": "id1",
        "target": "id2",
        "source_name": "Tripulación X",
        "target_name": "Tripulación Y",
        "label": "Tratado de paz",
        "color": "#3b82f6"
      }
    ]
  }
  ```

#### `ost_url` — URL de OST (música de fondo)
- VARCHAR(500). URL a un archivo MP3.
- Se reproduce con un reproductor de audio HTML5 en la sidebar y el hero.
- El JS `toggleCrewOst()` controla play/pausa.

#### `factions` — Facciones / Afiliaciones
- VARCHAR(255). Separadas por coma.
- Ej: `"Pirata, Supernova, Peor Generación"`.
- Se usan para filtros en la biblioteca y badges en el hero.
- Añadido en migración v3.

#### `ship_name` — Nombre del navío principal
- VARCHAR(150). Opcional.
- Se muestra en la pestaña de Navío.

#### `ship_image_url` — Imagen del navío
- VARCHAR(255). URL de la imagen del barco.
- Se muestra como imagen destacada en la pestaña de Navío.

#### `ship_data` — Datos del navío (descripción)
- TEXT. Descripción textual del barco: características, armamento, historia.
- Añadido en migración v3 junto con `ship_name` y `ship_image_url`.

#### `leader_pj_id` — Líder/Capitán
- INT. FK lógica a `game_personajes.id`.
- Se establece en la creación y se usa para mostrar el capitán en la ficha.
- El JOIN con `game_personajes` trae `name` y `avatar` del líder.

#### `status` — Estado de la tripulación
- VARCHAR(20). Valores: `'aprobada'` (default), `'pendiente'`.
- `'aprobada'`: tripulación activa visible en la biblioteca.
- `'pendiente'`: tripulación en espera de revisión del staff.
- Se muestra con badge verde (aprobada) o amarillo (pendiente).

#### `memories` — Recuerdos (JSON)
- TEXT. Array de objetos JSON:
  ```json
  [
    {
      "title": "La batalla de Marineford",
      "image": "https://...",
      "text": "Narrativa del recuerdo...",
      "date": "2025-06-12 14:30:00"
    }
  ]
  ```

#### `created_at` — Fecha de fundación
- TIMESTAMP. Default `CURRENT_TIMESTAMP`.
- Se formatea como `d/m/Y` para mostrar.

### 2.3 Historial de Migraciones

| Archivo | Añade |
|---------|-------|
| `migrate_crews.php` | Tabla base + `game_tripulacion_miembros` + `game_personajes.tripulacion_id` |
| `migrate_crews_v2.php` | `motto`, `role_custom` |
| `migrate_crews_extras.php` | `relations`, `ost_url` |
| `migrate_crews_v3.php` | `factions`, `ship_name`, `ship_image_url`, `ship_data` |
| `migrate_crews_fix.php` | Recrea tabla con esquema completo (DROP+CREATE) |
| Migración directa (no versionada) | `memories` (JSON) |

---

## 3. Tabla `game_tripulacion_miembros`

### 3.1 Definición SQL

```sql
CREATE TABLE mybb_game_tripulacion_miembros (
    pj_id           INT PRIMARY KEY,
    tripulacion_id  INT NOT NULL,
    role            VARCHAR(50) DEFAULT 'Miembro',
    role_custom     VARCHAR(80) DEFAULT '',       -- v2
    status_peticion VARCHAR(20) DEFAULT 'aprobada',
    joined_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_trip (tripulacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.2 Campos

#### `pj_id` — Personaje miembro (PK)
- INT. Clave primaria. FK lógica a `game_personajes.id`.
- Un personaje solo puede tener UNA fila en esta tabla (PK garantiza unicidad).

#### `tripulacion_id` — Tripulación a la que pertenece
- INT. FK lógica a `game_tripulaciones.id`.
- Indexado (`idx_trip`) para queries de listado de miembros.

#### `role` — Rol del sistema
- VARCHAR(50). Valores canónicos:
  - `'Capitán'`: El fundador/líder. Solo hay uno por tripulación.
  - `'Miembro'`: Miembro aprobado.
  - `'Aspirante'`: Pendiente de aprobación.
  - `'Oficial'`, `'Comandante'`, etc. (uso libre)

#### `role_custom` — Rol personalizado
- VARCHAR(80). Añadido en v2.
- Texto libre para el puesto dentro de la tripulación.
- Ej: `"Navegante"`, `"Carpintero"`, `"Médico"`, `"Timonel"`.
- Se muestra en lugar de `role` si no está vacío.

#### `status_peticion` — Estado de la membresía
- VARCHAR(20). Valores:
  - `'aprobada'`: Miembro activo.
  - `'pendiente'`: Solicitud de ingreso sin revisar.
  - `'rechazada'`: (no se usa activamente; se borra la fila).
- Cuando se acepta un aspirante, se actualiza de `'pendiente'` a `'aprobada'` y se actualiza `joined_at`.

#### `joined_at` — Fecha de ingreso
- TIMESTAMP. Default `CURRENT_TIMESTAMP`.
- Se actualiza cuando se acepta un miembro (`joined_at = NOW()`).

---

## 4. Estructura de JSON Columns

### 4.1 `game_tripulaciones.memories`

```json
[
  {
    "title": "Título del recuerdo",
    "image": "https://i.imgur.com/...",
    "text": "Descripción narrativa...",
    "date": "2025-06-12 14:30:00"
  }
]
```

**Reglas de negocio:**
- El título es obligatorio (validado en `crew_manage.php`).
- La imagen es opcional (placeholder por defecto).
- El texto es opcional.
- La fecha se genera automáticamente en el servidor (`date('Y-m-d H:i:s')`).
- Se pueden añadir N recuerdos sin límite explícito.
- El capitán añade/elimina desde la pestaña de Gestión.

### 4.2 `game_tripulaciones.relations`

```json
{
  "relations": [
    {
      "id": "temp_abc123",
      "name": "Marines G-5",
      "pj_id": 0,
      "is_npc": true,
      "tags": ["Enemigo"],
      "desc": "Base militar enemiga...",
      "image": "https://..."
    }
  ],
  "groups": [
    {
      "id": "grp_001",
      "name": "Gran Flota del Norte",
      "color": "#10b981",
      "members": ["rel_id_1", "rel_id_2"]
    }
  ],
  "connections": [
    {
      "id": "conn_001",
      "source": "rel_id_1",
      "target": "rel_id_2",
      "source_name": "Tripulación A",
      "target_name": "Tripulación B",
      "label": "Alianza militar",
      "color": "#3b82f6"
    }
  ]
}
```

**Estructura:**
- `relations`: Array de relaciones diplomáticas individuales.
- `groups`: Array de grupos/coaliciones que agrupan varias relaciones.
- `connections`: Array de vínculos explícitos entre relaciones para el grafo.

**Tags disponibles para relaciones:**
| Tag | Color |
|-----|-------|
| `Aliado` | `#10b981` (verde) |
| `Compañero` | `#3b82f6` (azul) |
| `Rival` | `#f59e0b` (ámbar) |
| `Enemigo` | `#ef4444` (rojo) |
| `Pacto de no agresión` | `#3b82f6` (azul) |
| `Bajo protección` | `#06b6d4` (cian) |
| `Tributario` | `#f97316` (naranja) |
| `Superior` | `#8b5cf6` (púrpura) |
| `Subordinado` | `#64748b` (gris) |

---

## 5. Flujo de Creación

### 5.1 Ruta Rápida (AJAX — `crew_create.php`)

**Frontend:** `tripulacion_crear.php` → `tripulacion_crear.js`

**Flujo:**
1. El usuario debe estar autenticado y tener un personaje activo (`active_pj_id`).
2. El personaje activo NO debe pertenecer ya a una tripulación (`tripulacion_id IS NULL`).
3. El usuario rellena: nombre (obligatorio), lema (opcional), URL de bandera (opcional).
4. `submitCreateCrew()` en JS envía POST a `crew_create.php`.
5. El servidor valida, escapa, inserta en `game_tripulaciones` con `status = 'aprobada'`.
6. Inserta al capitán en `game_tripulacion_miembros` con `role = 'Capitán'`.
7. Actualiza `game_personajes.tripulacion_id` del capitán.
8. Redirige a `tripulacion.php?id=<crew_id>`.

**Código clave (`crew_create.php`):**
```php
$db->query("INSERT INTO {$prefix}game_tripulaciones 
    (name, motto, image_url, leader_pj_id, created_at, status) 
    VALUES ('{$name}', '{$motto}', '{$image_url}', {$active_pj_id}, NOW(), 'aprobada')");
$crew_id = $db->insert_id();
$db->query("INSERT INTO {$prefix}game_tripulacion_miembros 
    (tripulacion_id, pj_id, role, role_custom, status_peticion, joined_at) 
    VALUES ({$crew_id}, {$active_pj_id}, 'Capitán', 'Capitán', 'aprobada', NOW())");
$db->query("UPDATE {$prefix}game_personajes SET tripulacion_id = {$crew_id} WHERE id = {$active_pj_id}");
```

### 5.2 Ruta con Petición (`peticion_tripulacion.php`)

**Flujo:**
1. El usuario autenticado accede a `peticion_tripulacion.php`.
2. Completa formulario: nombre, URL bandera, trasfondo/descripción.
3. POST con `action = 'create_crew'`.
4. Se inserta la tripulación con `status = 'pendiente'`.
5. Se inserta al capitán como miembro con `role = 'Capitán'`, `status_peticion = 'aprobada'`.
6. El usuario ve mensaje: "Petición de creación enviada al Staff."
7. El staff debe aprobar desde `zona_staff_tripulaciones.php`.
8. Al aprobar, se actualiza el `status` a `'aprobada'` y se asigna `tripulacion_id` al líder.

**Diferencia clave entre ambas rutas:**

| Aspecto | Ruta Rápida | Ruta con Petición |
|---------|-------------|-------------------|
| Status inicial | `aprobada` | `pendiente` |
| Revisión staff | No | Sí |
| Trasfondo | No requerido (solo nombre) | Requerido (descripción) |
| URL frontend | `tripulacion_crear.php` | `peticion_tripulacion.php` |
| AJAX | Sí (`crew_create.php`) | No (POST tradicional) |

### 5.3 Validaciones de Creación

**Lado servidor (`crew_create.php`):**
1. Autenticación: `$uid > 0`.
2. Personaje activo: `active_pj_id > 0`.
3. PJ existe: `SELECT id, tripulacion_id FROM game_personajes`.
4. No pertenece a otra crew: `empty($pj['tripulacion_id'])`.
5. Nombre no vacío y ≤ 150 chars.
6. Lema ≤ 255 chars, image_url ≤ 255 chars.
7. Todo escapado con `$db->escape_string()`.

**Lado servidor (`peticion_tripulacion.php`):**
- Mismas validaciones + descripción obligatoria.
- No usa AJAX; redirige con `?msg=created`.

---

## 6. Flujo de Unión (Solicitud)

### 6.1 Ruta AJAX (`crew_join.php`)

**Frontend:** Botón "Solicitar Unirse" en la ficha de tripulación → `submitJoinRequest()` en `_scripts.php`.

**Flujo:**
1. Usuario autenticado con personaje activo que NO pertenece a ninguna crew.
2. `submitJoinRequest()` envía POST a `crew_join.php` con `crew_id`.
3. El servidor verifica:
   - Autenticación.
   - Personaje activo existe y no tiene `tripulacion_id`.
   - No existe ya una solicitud previa (`SELECT status_peticion WHERE pj_id AND tripulacion_id`).
4. Inserta en `game_tripulacion_miembros` con `role = 'Aspirante'`, `status_peticion = 'pendiente'`.
5. Responde JSON `{ ok: true, message: 'Solicitud de unión enviada.' }`.

### 6.2 Ruta POST Tradicional (`peticion_tripulacion.php`)

1. Misma página que la creación con petición.
2. Lista todas las tripulaciones `aprobadas` con su capitán y miembros.
3. El usuario selecciona una y hace submit con `action = 'join_crew'`.
4. Inserta con `role = 'Aspirante'`, `status_peticion = 'pendiente'`.
5. Redirige con `?msg=joined`.

### 6.3 Estados de Solicitud

| Estado | Significado | Siguiente paso |
|--------|-------------|----------------|
| `pendiente` | El capitán no ha revisado | El capitán acepta o rechaza |
| `aprobada` | Solicitud aceptada | El miembro ya forma parte |
| (eliminada) | Solicitud rechazada | Se borra la fila |

### 6.4 Validaciones

```javascript
// Lado cliente (tripulacion_page.js)
function submitJoinRequest() {
    if (!confirm('¿Deseas solicitar unirte a esta tripulación?')) return;
    // POST a crew_join.php
}
```

**Lado servidor (`crew_join.php`):**
- Verifica que el PJ no tenga ya `tripulacion_id`.
- Verifica que no exista solicitud duplicada.
- No hay límite de solicitudes simultáneas (pero solo una por crew).

---

## 7. Gestión del Capitán

### 7.1 Endpoint Central: `crew_manage.php`

Todas las acciones de gestión pasan por un único endpoint con switch de acción:

```php
$action = $_POST['action'] ?? '';
// 'accept_member', 'reject_member', 'kick_member',
// 'update_role', 'update_crew', 'update_relations',
// 'add_memory', 'delete_memory'
```

**Verificación de identidad:**
1. Usuario autenticado.
2. Personaje activo.
3. El PJ activo debe tener `role = 'Capitán'` en `game_tripulacion_miembros` con `status_peticion = 'aprobada'`.
4. Solo operaciones sobre miembros de la misma tripulación.

### 7.2 Aceptar Miembro

**Proceso:**
1. Capitán hace clic en "Aceptar" en la tarjeta del aspirante.
2. `crewAcceptMember(pjId, btnEl)` en JS envía `action = 'accept_member'`.
3. PHP actualiza:
   ```sql
   UPDATE game_tripulacion_miembros 
   SET status_peticion = 'aprobada', role = 'Miembro', joined_at = NOW()
   WHERE pj_id = {$pj_id_target} AND tripulacion_id = {$crew_id}
   ```
4. Asigna `tripulacion_id` al personaje:
   ```sql
   UPDATE game_personajes SET tripulacion_id = {$crew_id} WHERE id = {$pj_id_target}
   ```

**Efectos secundarios:**
- El contador de notificaciones en la pestaña Gestión disminuye.
- La página recarga tras 1.5 segundos (`setTimeout(location.reload(), 1500)`).

### 7.3 Rechazar Miembro

**Proceso:**
1. Capitán hace clic en "Rechazar".
2. `crewRejectMember(pjId, btnEl)` envía `action = 'reject_member'`.
3. PHP borra la fila de `game_tripulacion_miembros`.

### 7.4 Expulsar Miembro

**Proceso:**
1. Capitán hace clic en el icono de expulsión.
2. `crewKickMember(pjId, btnEl)` envía `action = 'kick_member'`.
3. PHP borra la fila de `game_tripulacion_miembros`.
4. PHP pone `tripulacion_id = NULL` en `game_personajes`.
5. El capitán no puede expulsarse a sí mismo (validación explícita).

### 7.5 Actualizar Rol Personalizado

**Proceso:**
1. Capitán escribe el puesto en el input y hace clic en guardar.
2. `crewUpdateRole(pjId, inputId)` envía `action = 'update_role'`.
3. PHP actualiza `role_custom` en `game_tripulacion_miembros`.

### 7.6 Editar Información de la Tripulación

**Campos editables desde la pestaña Gestión:**
- `name`, `motto`, `factions`, `image_url`, `ost_url`.
- `description`, `relations` (gestionado desde el modal diplomático).
- `ship_name`, `ship_image_url`, `ship_data`.

**Proceso:**
1. Capitán modifica campos y hace clic en "Guardar Cambios".
2. `crewSaveInfo()` recolecta todos los valores y envía `action = 'update_crew'`.
3. PHP actualiza `game_tripulaciones` con todos los campos.

### 7.7 Gestión de Relaciones Diplomáticas

(Ver sección [13. Sistema Diplomático](#13-sistema-diplomático))

---

## 8. Ficha de Tripulación — Vista Pública

### 8.1 Orquestador: `tripulacion.php`

**Propósito:** Cargar y renderizar la ficha completa de una tripulación.

**Flujo de carga:**
1. Determinar `$crew_id`:
   - De `$_GET['id']` si se proporciona.
   - Si no, del personaje activo del usuario (`game_personajes.tripulacion_id`).
   - Si no hay ninguno, redirigir a `biblioteca_tripulaciones.php`.
2. Cargar datos de la tripulación (JOIN con líder).
3. Detectar rol del usuario actual:
   - `$is_captain`: el PJ activo es el capitán.
   - `$is_member`: el PJ activo es miembro aprobado.
   - `$can_join`: el PJ activo no pertenece a ninguna crew.
   - `$is_pending`: el PJ activo ya tiene solicitud pendiente.
4. Cargar miembros con datos enriquecidos (JOIN con `game_personajes`):
   - Calcula `global_rank` desde `stats_json`.
   - Separa aspirantes (`status_peticion = 'pendiente'`) de miembros.
   - Calcula recompensa total de la tripulación.
5. Cargar territorios controlados (`game_forum_islands`).
6. Cargar relaciones diplomáticas y lista de otras crews (para el modal).
7. Renderizar vistas parciales.

```php
// Estructura del query de miembros
SELECT m.pj_id, m.role, m.role_custom, m.status_peticion, m.joined_at,
       p.name, p.avatar, p.faction, p.rango, p.recompensa,
       p.stats_json, p.race_name, p.user_id
FROM game_tripulacion_miembros m
JOIN game_personajes p ON m.pj_id = p.id
WHERE m.tripulacion_id = {$crew_id}
ORDER BY CASE m.role WHEN 'Capitán' THEN 0 ELSE 1 END, m.joined_at ASC
```

### 8.2 Layout: `page_layout_1.php`

**Estructura visual:**
1. **Hero Banner:** Imagen de la bandera, nombre, lema, facciones, recompensa total, botón de unión, reproductor OST.
2. **Sidebar:** (en diseño anterior; en el diseño inmersivo el hero reemplaza parte de la sidebar).
3. **Navegación de pestañas:** Información, Tripulación, Navío, Territorios, Recuerdos, Gestión (solo capitán).
4. **Contenido de pestañas:** Cada una es un partial independiente.

### 8.3 Pestaña: Información (`_tab_bio.php`)

- **Stats rápidos:** Miembros, Territorios, Capitán, Fundación.
- **Descripción/Historia:** Renderizada con `nl2br()`.
- **Diplomacia y Red de Alianzas:**
  - Soporta dos modos de visualización:
    - **Vista grafo:** Red de relaciones dibujada por `game_network.js`.
    - **Vista lista:** Tarjetas de relaciones con tags coloreados.
  - Compatibilidad hacia atrás con relaciones legacy (texto plano).

### 8.4 Pestaña: Tripulación (`_tab_miembros.php`)

- Grid de tarjetas de miembros.
- Cada tarjeta muestra: avatar, nombre, rol, rango global, recompensa, fecha de ingreso.
- El capitán se destaca con clase `crew-member-card--captain`.
- Botón "Solicitar Ingreso" si el usuario no es miembro.

### 8.5 Pestaña: Navío (`_tab_navio.php`)

- Imagen del navío (si existe).
- Nombre del navío.
- Descripción/detalles del navío.

### 8.6 Pestaña: Territorios (`_tab_territorios.php`)

- Grid de tarjetas de islas controladas.
- Cada tarjeta: imagen, nombre del foro, descripción corta, clima, nivel de peligro.

### 8.7 Pestaña: Recuerdos (`_tab_recuerdos.php`)

- Grid de tarjetas de recuerdos.
- Cada tarjeta: imagen, título (overlay).
- Modal para ver recuerdo en grande con imagen, título y texto completo.

### 8.8 Pestaña: Gestión (`_tab_gestion.php`)

Solo visible para el capitán. Contiene:
- **Peticiones Pendientes:** Lista de aspirantes con acciones Aceptar/Rechazar.
- **Información Pública:** Formulario de edición de todos los campos de la crew.
- **Administrar Miembros:** Lista de miembros con opciones para actualizar rol y expulsar.
- **Gestionar Recuerdos:** Botón para añadir recuerdos + lista de recuerdos existentes con opción de borrar.
- **Diplomacia:** Botón "Gestionar Relaciones Diplomáticas" que abre el modal.

---

## 9. Biblioteca de Tripulaciones

### 9.1 Vista: `biblioteca_tripulaciones.php`

**Propósito:** Catálogo público de todas las tripulaciones aprobadas.

**Flujo:**
1. Query todas las tripulaciones con `status = 'aprobada'`, ordenadas por nombre.
2. Para cada crew:
   - Parsear facciones (separadas por coma).
   - Cargar miembros aprobados.
   - Cargar islas controladas.
3. Renderizar grid de tarjetas con filtros por facción.

**Filtros por facción:**
- Botones generados dinámicamente desde las facciones únicas de todas las crews.
- JS (`biblioteca_tripulaciones.js`) maneja el filtrado cliente-side:
  ```javascript
  filterBtns.forEach(btn => {
      btn.addEventListener('click', function() {
          const filter = this.getAttribute('data-filter');
          cards.forEach(card => {
              if (filter === 'all') card.style.display = 'flex';
              else {
                  const factions = card.getAttribute('data-factions').split('|');
                  card.style.display = factions.includes(filter) ? 'flex' : 'none';
              }
          });
      });
  });
  ```

**Estructura de cada tarjeta:**
- Banner con overlay (nombre + lema).
- Tags de facción con `rpg-badge--dark`.
- Lista de miembros (nombre + rol).
- Botón "Ver Detalles" → `tripulacion.php?id=N`.

---

## 10. Zona de Staff

### 10.1 Vista: `zona_staff_tripulaciones.php`

**Propósito:** Panel de administración para que el staff gestione tripulaciones.

**Acceso:**
- Requiere autenticación.
- El personaje activo debe tener `staff_level >= 2` (Game Master o Admin).

### 10.2 Acciones del Staff

#### Aprobar Tripulación
- Acción: `approve_crew`.
- Actualiza `status = 'aprobada'`.
- Asigna `tripulacion_id` al líder:
  ```php
  $crew_data = $db->fetch_array($db->query("SELECT leader_pj_id FROM ..."));
  $db->query("UPDATE game_personajes SET tripulacion_id = {$crew_id} WHERE id = {$crew_data['leader_pj_id']}");
  ```

#### Rechazar Tripulación
- Acción: `reject_crew`.
- Borra miembros y la tripulación.
- **No restaura** `tripulacion_id` del líder (porque nunca se asignó).

#### Disolver Tripulación
- Acción: `delete_crew`.
- Pone `tripulacion_id = NULL` en todos los miembros.
- Borra todos los miembros.
- Borra la tripulación.

### 10.3 Tablas de Datos

Dos secciones:
1. **Tripulaciones Pendientes:** Las que necesitan aprobación/rechazo.
2. **Tripulaciones Activas:** Las aprobadas, con opción de disolver.

Cada fila muestra: bandera, nombre, lema, capitán, acciones.

---

## 11. Sistema de Territorios

### 11.1 Integración con Islas del Foro

Las tripulaciones pueden controlar territorios (islas) del foro.

**Mecanismo:**
- Tabla `game_forum_islands` tiene campos `controlling_type` y `controlling_id`.
- Cuando `controlling_type = 'crew'`, la isla pertenece a la tripulación con `id = controlling_id`.

**Query de carga en `tripulacion.php`:**
```sql
SELECT i.*, f.name AS forum_name
FROM game_forum_islands i
JOIN forums f ON i.fid = f.fid
WHERE i.controlling_type = 'crew' AND i.controlling_id = {$crew_id}
ORDER BY f.name ASC
```

**Display en la pestaña Territorios:**
- Tarjetas con imagen, nombre del foro, descripción (truncada a 100 chars), clima, nivel de peligro (1-5 con colores).

### 11.2 Niveles de Peligro

| Nivel | Color CSS |
|-------|-----------|
| 1 | `#10b981` (verde) |
| 2 | `#84cc16` (verde claro) |
| 3 | `#f59e0b` (ámbar) |
| 4 | `#ef4444` (rojo) |
| 5 | `#dc2626` (rojo oscuro + glow) |

---

## 12. Sistema de Recuerdos

### 12.1 Propósito

Los recuerdos son una bitácora visual de momentos importantes en la historia de la tripulación. Funcionan como un álbum cronológico.

### 12.2 Modelo de Datos

Almacenado en `game_tripulaciones.memories` como JSON array.

### 12.3 CRUD

**Crear:** `crew_manage.php` con `action = 'add_memory'`.
- Título (obligatorio, ≤ 150 chars).
- Imagen URL (opcional, ≤ 255 chars).
- Texto (opcional).
- Fecha generada por el servidor.

```php
$memories = json_decode($crew_row['memories'] ?? '[]', true);
$memories[] = [
    'title' => $title,
    'image' => $img,
    'text'  => $text,
    'date'  => date('Y-m-d H:i:s')
];
$db->query("UPDATE game_tripulaciones SET memories = '" . 
    $db->escape_string(json_encode($memories, JSON_UNESCAPED_UNICODE)) . 
    "' WHERE id = {$crew_id}");
```

**Eliminar:** `crew_manage.php` con `action = 'delete_memory'`.
- Recibe `index` (posición en el array).
- Usa `array_splice()` para eliminar el elemento.

### 12.4 Visualización

**En la ficha** (`_tab_recuerdos.php`):
- Grid de tarjetas tipo álbum: imagen con overlay de título.
- Modal para vista ampliada con imagen, título y texto.

**En gestión** (`_tab_gestion.php`):
- Lista de recuerdos existentes con botón de borrar.
- Botón "Añadir Nuevo Recuerdo" abre modal con formulario.

### 12.5 UX del Modal de Vista

```javascript
window.openMemoryModal = function(mem) {
    document.getElementById('view_mem_img').src = mem.image || '...';
    document.getElementById('view_mem_title').textContent = mem.title;
    document.getElementById('view_mem_text').innerHTML = 
        (mem.text || '').replace(/\n/g, '<br>');
    document.getElementById('modal_ver_recuerdo').style.display = 'flex';
};
```

---

## 13. Sistema Diplomático y Red de Alianzas

### 13.1 Arquitectura

El sistema diplomático es un editor de red visual que permite a los capitanes:
1. **Registrar relaciones** con otras tripulaciones del foro o facciones NPC.
2. **Agrupar relaciones** en coaliciones o grupos.
3. **Dibujar conexiones** entre relaciones para representar pactos, guerras, etc.

### 13.2 Componentes

#### Backend
- `crew_manage.php` maneja `update_relations` (guarda el JSON completo) y `update_crew` (guarda el campo relations junto con el resto).
- El editor trabaja con un "borrador" en memoria (`draftNetworkData`) que se envía al servidor al guardar.

#### Frontend
- `tripulacion_page.js` contiene toda la lógica del editor diplomático.
- `game_network.js` renderiza el grafo de relaciones con `canvas` o `svg`.

### 13.3 Modal de Gestión de Relaciones

**Tres pestañas:**

1. **Relaciones (`tab-contactos`):**
   - Lista de relaciones existentes (nombre, tags coloreados, imagen).
   - Botón "Añadir Relación" que abre modal de relación.
   - Cada entrada se puede editar o eliminar.

2. **Grupos y Flotas (`tab-grupos`):**
   - Lista de grupos/coaliciones.
   - Botón "Crear Grupo" que abre modal de grupo.
   - Los grupos agrupan varias relaciones (mínimo 2 miembros).

3. **Conexiones Diplomáticas (`tab-conexiones`):**
   - Lista de conexiones/vínculos entre relaciones.
   - Botón "Añadir Vínculo" que abre modal de conexión.

### 13.4 Modal de Relación

- **Checkbox "Es facción NPC":** Cambia entre selección de tripulación del foro y nombre de facción libre.
- **Búsqueda de tripulaciones:** Autocompletado cliente-side sobre las opciones del `<select>`.
- **Selector de tags:** Hasta 3 etiquetas de las 9 disponibles (Aliado, Enemigo, Rival, etc.).
- **Descripción diplomática:** Texto libre.
- **Imagen de bandera:** URL opcional.
- **Conexión explícita:** Opción para crear un vínculo directo entre esta relación y otra en el grafo.

### 13.5 Modal de Grupo

- Nombre del grupo/coalición.
- Color del grupo (selector de 8 colores).
- Selección de miembros (checkboxes de las relaciones existentes, mínimo 2).

### 13.6 Modal de Conexión

- Relación A (select).
- Relación B (select).
- Vínculo (texto, ej: "Tratado de paz", "Guerra abierta").
- Color del vínculo.

### 13.7 Flujo de Edición

1. El capitán abre el modal de gestión.
2. Las relaciones, grupos y conexiones se cargan desde `window.__PJ_NETWORK_DATA`.
3. Las modificaciones se aplican sobre `window.draftNetworkData` (borrador local).
4. Al hacer clic en "Guardar Todo":
   - Se serializa `draftNetworkData` a JSON.
   - Se guarda en el campo oculto `crew_edit_relations`.
   - Se envía `update_relations` a `crew_manage.php`.
   - El servidor guarda el JSON en `game_tripulaciones.relations`.

### 13.8 Visualización en la Ficha

**Dos modos de visualización:**

1. **Mapa de Relaciones (grafo):**
   - Renderizado por `game_network.js`.
   - Muestra nodos (relaciones) conectados por aristas (conexiones).
   - Los grupos se representan visualmente.

2. **Vista Lista:**
   - Tarjetas de cada relación con tags coloreados.
   - Las relaciones a tripulaciones del foro son enlaces clickeables.

### 13.9 Tags y Colores

Ver tabla en [4.2 Relations JSON](#42-gametripulacionesrelations).

---

## 14. Sistema de Navío

### 14.1 Propósito

Cada tripulación puede registrar un navío principal con imagen y descripción.

### 14.2 Campos

- `ship_name`: VARCHAR(150). Nombre del barco.
- `ship_image_url`: VARCHAR(255). URL de la imagen del barco.
- `ship_data`: TEXT. Descripción: características, armamento, historia, etc.

### 14.3 Visualización (`_tab_navio.php`)

- Imagen del navío en tamaño destacado (si existe).
- Nombre del navío como título.
- Descripción con `nl2br()`.
- Mensaje placeholder "Aún no hay detalles registrados" si no hay datos.

### 14.4 Edición

Desde la pestaña Gestión, el capitán puede editar los tres campos del navío junto con el resto de la información de la tripulación.

---

## 15. JavaScript — Frontend Lógico

### 15.1 Archivos JS

| Archivo | Propósito |
|---------|-----------|
| `tripulacion_crear.js` | Formulario de creación rápida de tripulación |
| `tripulacion_page.js` | Toda la lógica de la ficha de tripulación |
| `biblioteca_tripulaciones.js` | Filtros por facción en el catálogo |
| `game_network.js` | Renderizado del grafo diplomático |

### 15.2 `tripulacion_crear.js`

**Funciones:**
- `submitCreateCrew()`: Valida, construye FormData, envía POST a `crew_create.php`, redirige a la ficha.

### 15.3 `tripulacion_page.js`

**IIFE** (Inmediatamente Invoked Function Expression) que expone funciones al ámbito global.

**Configuración (`CREW_CONFIG`):**
```javascript
window.CREW_CONFIG = {
    crewId: <?= $crew_id ?>,
    isCaptain: <?= $is_captain ? 'true' : 'false' ?>,
    myPjId: <?= $my_pj_id ?>,
    ajaxUrl: '<?= htmlspecialchars($bburl) ?>/game/ajax/crew_manage.php',
    tagColors: <?= json_encode($tag_colors) ?>
};
```

**Funciones globales expuestas:**

| Función | Acción |
|---------|--------|
| `switchCrewTab(tabName, el)` | Cambia entre pestañas (Info, Miembros, Navío, etc.) |
| `crewAction(action, data, onSuccess)` | Helper AJAX genérico para todas las operaciones |
| `crewAcceptMember(pjId, btnEl)` | Acepta un aspirante |
| `crewRejectMember(pjId, btnEl)` | Rechaza un aspirante |
| `crewKickMember(pjId, btnEl)` | Expulsa un miembro |
| `crewUpdateRole(pjId, inputId)` | Actualiza el rol personalizado |
| `crewSaveInfo()` | Guarda todos los campos editables de la crew |
| `openAddMemoryModal()` | Abre modal para añadir recuerdo |
| `crewAddMemory()` | Envía nuevo recuerdo al servidor |
| `crewDeleteMemory(idx, btn)` | Borra un recuerdo |
| `toggleCrewOst(btn)` | Play/Pausa del OST |
| `submitJoinRequest()` | Envía solicitud de unión |
| `switchCrewNetworkView(view)` | Cambia entre vista grafo/lista de relaciones |
| `switchRelTab(tab, btn)` | Cambia pestañas del modal diplomático |
| `toggleRelNpc(cb)` | Muestra/oculta campo de facción NPC |
| `searchCrew(q)` | Búsqueda de tripulaciones en el modal |
| `openCrewRelationsManager()` | Abre el modal de gestión diplomática |
| `openNewRelacion()` | Abre modal para nueva relación |
| `openNewGroup()` | Abre modal para nuevo grupo |
| `openNewConnection()` | Abre modal para nueva conexión |
| `saveCrewRelationsDraft(type)` | Guarda en borrador local (`draftNetworkData`) |
| `saveBatchCrewRelations()` | Envía todas las relaciones al servidor |
| `editRelacionEntry(id, jsonStr)` | Edita una relación existente |
| `editGroupEntry(id, jsonStr)` | Edita un grupo existente |
| `editConnectionEntry(id, jsonStr)` | Edita una conexión existente |
| `deleteDraftEntry(type, id)` | Elimina entrada del borrador |
| `selectConnColorRel(el)` | Selector de color para conexión en relación |
| `selectGroupColor(el)` | Selector de color para grupo |
| `selectConnColor(el)` | Selector de color para conexión |
| `openMemoryModal(mem)` | Abre modal de vista de recuerdo |

**Toast de notificaciones:**
```javascript
function showCrewToast(msg, type) {
    var t = document.createElement('div');
    t.className = 'rpg-toast rpg-toast--' + (type || 'info');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function() { t.classList.add('rpg-toast--visible'); }, 10);
    setTimeout(function() {
        t.classList.remove('rpg-toast--visible');
        setTimeout(function() { t.remove(); }, 300);
    }, 3000);
}
```

### 15.4 `biblioteca_tripulaciones.js`

Filtrado cliente-side por facción:
- Escucha clics en botones de filtro.
- Muestra/oculta tarjetas según el atributo `data-factions`.

### 15.5 Patrón de Diseño JS

Todos los archivos JS usan JavaScript vanilla (sin frameworks) y se cargan con script tags al final del body. Las funciones de `tripulacion_page.js` se exponen globalmente (ventanas globales) porque son llamadas desde atributos `onclick` en las plantillas PHP.

---

## 16. Templates y Vistas PHP

### 16.1 Estructura de Archivos

```
views/tripulacion/
├── page_layout_1.php       # Orquestador de partials
├── _styles.php             # Estilos específicos
├── _scripts.php            # Config JS + scripts
├── _tabs_nav.php           # Navegación de pestañas
├── _tab_bio.php            # Información + diplomacia
├── _tab_miembros.php       # Grid de miembros
├── _tab_navio.php          # Detalles del navío
├── _tab_territorios.php    # Territorios controlados
├── _tab_recuerdos.php      # Álbum de recuerdos
├── _tab_gestion.php        # Gestión del capitán
├── _modals.php             # Todos los modals (relaciones, recuerdos)
└── _sidebar.php            # Sidebar (diseño legacy)
```

### 16.2 `page_layout_1.php`

Renderiza el layout completo:
1. Hero banner con bandera, nombre, lema, facciones.
2. Contenido principal con pestañas.
3. Si el usuario es capitán, carga gestión y modals.
4. Carga scripts.

### 16.3 Convenciones de Plantillas

- Todas las variables PHP escapan con `htmlspecialchars()`.
- Los bucles usan `<?php foreach (...): ?> ... <?php endforeach; ?>`.
- Los condicionales usan `<?php if (...): ?> ... <?php endif; ?>`.
- Los atributos `onclick` llaman funciones JS globales.
- Los modals se ocultan/muestran con `style.display = 'flex'/'none'`.

---

## 17. AJAX Endpoints

### 17.1 `game/ajax/crew_create.php`

**Método:** POST
**Content-Type:** `application/json; charset=utf-8`

**Input:**
| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `name` | string | Sí | Nombre de la tripulación (≤150) |
| `motto` | string | No | Lema (≤255) |
| `image_url` | string | No | URL bandera (≤255) |

**Output:**
```json
{ "ok": true, "crew_id": 1, "message": "Tripulación creada." }
{ "ok": false, "message": "El nombre es obligatorio." }
```

**Errores posibles:**
- `No autenticado.`
- `No tienes un personaje activo.`
- `PJ inválido.`
- `Ya perteneces a una tripulación.`
- `El nombre es obligatorio.`
- `Error al crear la tripulación.`

### 17.2 `game/ajax/crew_join.php`

**Método:** POST
**Content-Type:** `application/json; charset=utf-8`

**Input:**
| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `crew_id` | int | Sí | ID de la tripulación a unirse |

**Output:**
```json
{ "ok": true, "message": "Solicitud de unión enviada." }
{ "ok": false, "message": "Ya has enviado una solicitud a esta tripulación." }
```

**Errores posibles:**
- `No autenticado.`
- `No tienes un personaje activo.`
- `Tripulación inválida.`
- `PJ inválido.`
- `Ya perteneces a una tripulación.`
- `Ya has enviado una solicitud a esta tripulación.`

### 17.3 `game/ajax/crew_manage.php`

**Método:** POST
**Content-Type:** `application/json; charset=utf-8`

**Input común:**
| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `action` | string | Sí | Una de las acciones listadas |
| `crew_id` | int | Sí | ID de la tripulación |

**Acciones:**

#### `accept_member`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `pj_id` | int | ID del personaje a aceptar |

**Output:** `{ "ok": true, "message": "Miembro aceptado." }`

#### `reject_member`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `pj_id` | int | ID del personaje a rechazar |

**Output:** `{ "ok": true, "message": "Solicitud rechazada." }`

#### `kick_member`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `pj_id` | int | ID del personaje a expulsar |

**Output:** `{ "ok": true, "message": "Miembro expulsado." }`

#### `update_role`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `pj_id` | int | ID del personaje |
| `role_custom` | string | Nuevo rol personalizado (≤80) |

**Output:** `{ "ok": true, "message": "Rol actualizado." }`

#### `update_crew`
| Campo | Tipo | Límite |
|-------|------|--------|
| `name` | string | 150 |
| `motto` | string | 255 |
| `factions` | string | 255 |
| `description` | string | TEXT |
| `image_url` | string | 255 |
| `relations` | string | TEXT |
| `ost_url` | string | 500 |
| `ship_name` | string | 150 |
| `ship_image_url` | string | 255 |
| `ship_data` | string | TEXT |

**Output:** `{ "ok": true, "message": "Tripulación actualizada con éxito." }`

#### `update_relations`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `relations` | string | JSON string del objeto relations |

**Validación:** Se decodifica el JSON. Si no es array, error.
**Output:** `{ "ok": true, "message": "Relaciones diplomáticas actualizadas." }`

#### `add_memory`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `title` | string | Título (obligatorio, ≤150) |
| `image` | string | URL imagen (≤255) |
| `text` | string | Descripción |

**Output:** `{ "ok": true, "message": "Recuerdo añadido." }`

#### `delete_memory`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `index` | int | Índice en el array de recuerdos |

**Output:** `{ "ok": true, "message": "Recuerdo eliminado." }`

---

## 18. Migraciones y Evolución del Esquema

### 18.1 Línea de Tiempo de Migraciones

```
migrate_crews.php (v1)
  ├── Crea game_tripulaciones (base: id, name, image_url, description, leader_pj_id, status, created_at)
  ├── Crea game_tripulacion_miembros (pj_id, tripulacion_id, role, status_peticion, joined_at)
  └── Añade tripulacion_id a game_personajes

migrate_crews_v2.php (v2)
  ├── Añade motto a game_tripulaciones
  └── Añade role_custom a game_tripulacion_miembros

migrate_crews_extras.php (extras)
  ├── Añade relations a game_tripulaciones
  └── Añade ost_url a game_tripulaciones

migrate_crews_v3.php (v3)
  ├── Añade factions a game_tripulaciones
  ├── Añade ship_name a game_tripulaciones
  ├── Añade ship_image_url a game_tripulaciones
  └── Añade ship_data a game_tripulaciones

migrate_crews_fix.php (fix)
  ├── DROP TABLE game_tripulaciones (si existía con esquema antiguo)
  └── CREATE TABLE con esquema completo

Migración directa (no versionada)
  └── Añade memories (JSON) a game_tripulaciones
```

### 18.2 Esquema Legacy

El fragmento en `install_schema_fragments.php` muestra una versión legacy:
```sql
CREATE TABLE game_tripulaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    imagen VARCHAR(500) NOT NULL DEFAULT '',
    descripcion TEXT
);
```
Esta versión no tenía `name` (usaba `nombre`), ni `leader_pj_id`, ni `status`, ni `members`. Fue reemplazada por el esquema actual.

---

## 19. Permisos y Seguridad

### 19.1 Matriz de Permisos

| Acción | Visitante | Miembro | Capitán | Staff |
|--------|-----------|---------|---------|-------|
| Ver ficha de tripulación | Sí | Sí | Sí | Sí |
| Ver biblioteca | Sí | Sí | Sí | Sí |
| Crear tripulación (rápida) | No | No* | No | No |
| Solicitar unión | No | No* | No | No |
| Aceptar/rechazar miembros | No | No | Sí | No |
| Editar info de la crew | No | No | Sí | No |
| Gestionar relaciones | No | No | Sí | No |
| Añadir/borrar recuerdos | No | No | Sí | No |
| Expulsar miembros | No | No | Sí | No |
| Aprobar/rechazar crews | No | No | No | Sí (≥2) |
| Disolver tripulación | No | No | No | Sí (≥2) |

*\* No si ya pertenece a una tripulación.*

### 19.2 Validaciones Clave

**Lado servidor (siempre):**
- Toda acción requiere autenticación (`$uid > 0`).
- Toda acción requiere personaje activo (`active_pj_id > 0`).
- Las acciones de capitán verifican `role = 'Capitán'` en `game_tripulacion_miembros`.
- Las acciones de staff verifican `staff_level >= 2` en `game_personajes`.
- Todos los inputs se escapan con `$db->escape_string()`.
- Todos los inputs se truncan con `mb_substr()` para respetar límites de columna.
- IDs se castean a `(int)` para prevenir inyección SQL.

**Lado cliente (UX):**
- Confirmaciones antes de acciones destructivas (rechazar, expulsar, borrar recuerdo).
- Validación de campos obligatorios antes del envío.

### 19.3 Inyección SQL

No se usan consultas preparadas; todas las consultas usan concatenación con `$db->escape_string()`. Esto es consistente con el resto del sistema MyBB. Los IDs se castean a int.

### 19.4 CSRF

El sistema no implementa tokens CSRF explícitos. La protección se basa en que el atacante necesitaría conocer la URL del endpoint y los parámetros exactos. Para un foro RPG de nicho, el riesgo es asumido.

---

## 20. Filosofía de Diseño

### 20.1 Principios Fundamentales

1. **Autonomía del jugador:** Los capitanes tienen control total sobre su tripulación sin intervención del staff (creación rápida, gestión de miembros, recuerdos, relaciones).
2. **Dos vías de creación:** Rápida (sin fricción) y con petición (con control). Cada una sirve un caso de uso diferente.
3. **JSON para datos flexibles:** `memories` y `relations` evolucionan sin migraciones de esquema.
4. **Un solo endpoint de gestión:** `crew_manage.php` centraliza todas las acciones del capitán, simplificando el mantenimiento y la auditoría.
5. **Editor diplomático visual:** Las relaciones no son texto plano; son un grafo interactivo que los capitanes pueden dibujar y modificar en tiempo real.

### 20.2 Decisiones Arquitectónicas

**¿Por qué `peticion_tripulacion.php` existe si ya tenemos `crew_create.php`?**
- `crew_create.php` es el sistema moderno (AJAX, sin revisión). Fue añadido para dar autonomía.
- `peticion_tripulacion.php` es el sistema legacy que requiere aprobación del staff. Se mantiene para flujos formales donde el staff quiere revisar el trasfondo antes de aprobar.

**¿Por qué `role_custom` separado de `role`?**
- `role` es el rol del sistema usado para lógica (Capitán, Miembro, Aspirante).
- `role_custom` es un texto libre para el puesto narrativo (Navegante, Médico, etc.).
- Separarlos permite que el sistema distinga entre roles funcionales y roles narrativos.

**¿Por qué el editor diplomático usa un borrador local?**
- Para permitir múltiples cambios (añadir/quitar relaciones, grupos, conexiones) antes de guardar todo de una vez.
- Reduce la cantidad de llamadas AJAX.
- Mejora la UX: el capitán puede ver el resultado visual antes de confirmar.

**¿Por qué `memories` se almacena como JSON y no como tabla separada?**
- Un personaje ya usa JSON para `cronologia_json`. Consistencia arquitectónica.
- Las queries de recuerdos siempre cargan el crew completo; nunca se necesita buscar recuerdos individuales.
- Evita migraciones de esquema.

### 20.3 Integración con el Ecosistema

| Componente | Integración |
|------------|-------------|
| Personajes | `game_personajes.tripulacion_id` conecta personajes a su tripulación |
| Islas/Foros | `game_forum_islands.controlling_type = 'crew'` permite propiedad territorial |
| Sistema de Rangos | `StatScale::globalRankFromSum()` se calcula desde `stats_json` de cada miembro |
| Sistema de Staff | `game_personajes.staff_level` controla acceso a `zona_staff_tripulaciones.php` |

---

## 21. Consejos para Jugadores

### 21.1 Elegir el Tipo de Tripulación

- **Tripulación Pirata:** La opción clásica. Busca libertad, aventura y recompensas.
- **Banda de Cazadores de Recompensas:** Enfócate en misiones y cazar piratas.
- **Organización de Contrabando:** Comercio ilegal y alianzas en el inframundo.
- **Flota de Exploración:** Descubrimiento de islas y misterios del Grand Line.
- **Marina / Gobierno Mundial:** Fuerza del orden (requiere facción apropiada).

### 21.2 Estrategias de Crecimiento

1. **Reclutamiento selectivo:** No aceptes a todos los aspirantes. Busca personajes con habilidades complementarias y buena actividad.
2. **Roles claros:** Asigna `role_custom` a cada miembro (Navegante, Médico, Carpintero, Vigía, Tirador). Da identidad a la tripulación.
3. **Relaciones diplomáticas:** Usa el sistema de relaciones para documentar alianzas y enemistades. Esto enriquece el lore y da pistas para tramas.
4. **Territorios:** Negocia con el staff para controlar islas. Una base de operaciones da peso narrativo.
5. **Recuerdos:** Documenta los hitos importantes (primer botín, batallas, reclutamientos). El álbum de recuerdos es el legado de la tripulación.
6. **Navío:** Personaliza el barco con descripción detallada. Es la casa de la tripulación.

### 21.3 Cómo Solicitar Unión

1. Asegúrate de no pertenecer a otra tripulación.
2. Investiga la tripulación en la biblioteca.
3. Haz clic en "Solicitar Unirse" en la ficha.
4. Espera a que el capitán acepte (o contacta OOC si pasa mucho tiempo).
5. Si no hay respuesta, puedes contactar al capitán por MP.

### 21.4 Buenas Prácticas como Capitán

- **Mantén la información actualizada:** Descripción, facciones, lema.
- **Revisa las solicitudes regularmente:** El badge de notificación te avisa.
- **Comunica las expulsiones:** Si expulsas a alguien, asegúrate de que tenga contexto IC/OOC.
- **Usa el sistema diplomático:** Documenta alianzas y rivalidades. Hace el mundo más vivo.
- **Actualiza el álbum de recuerdos:** Después de eventos importantes, añade un recuerdo.

---

## 22. Consejos para Staff

### 22.1 Moderación de Creaciones

**Para la ruta con petición:**
- Revisa que el nombre no sea inapropiado ni duplicado.
- Evalúa el trasfondo: ¿tiene coherencia con el mundo?
- Verifica que el líder tenga un personaje activo y coherente.
- Si rechazas, considera dar feedback para mejorar.

**Para la ruta rápida:**
- Monitorea crews que aparecen sin revisión.
- Si detectas nombres inapropiados, usa la opción "Disolver" desde staff.

### 22.2 Gestión de Conflictos

- **Disputas entre miembros:** Como staff, puedes intervenir si hay conflictos OOC.
- **Crews inactivas:** Considera disolver tripulaciones con líderes inactivos (> 3 meses).
- **Sucesión de liderazgo:** Si un capitán se va, puedes transferir el liderazgo editando la base de datos directamente (cambiar `leader_pj_id` y el `role` en miembros).

### 22.3 Eventos y Tramas

- **Asignación de islas:** Usa `game_forum_islands` para dar territorios como recompensa por eventos.
- **Guerras entre tripulaciones:** Coordina con los capitanes para tramas de conflicto.
- **Recompensas globales:** La recompensa total de la tripulación se calcula automáticamente desde las recompensas individuales de los miembros.

### 22.4 Operaciones desde Staff

| Operación | Cómo hacerla |
|-----------|-------------|
| Aprobar crew pendiente | `zona_staff_tripulaciones.php` → botón "Aprobar" |
| Rechazar crew pendiente | `zona_staff_tripulaciones.php` → botón "Rechazar" |
| Disolver crew activa | `zona_staff_tripulaciones.php` → botón "Disolver" |
| Editar crew directamente | No hay interfaz staff para editar; modificar en DB directamente |
| Transferir liderazgo | SQL: `UPDATE game_tripulaciones SET leader_pj_id = X` + actualizar roles en miembros |

---

## 23. Guía de Troubleshooting

### 23.1 Problemas Comunes

| Problema | Causa Posible | Solución |
|----------|---------------|----------|
| No puedo crear tripulación | Ya pertenezco a una | Sal de la tripulación actual o espera a que te expulsen |
| El botón "Solicitar Unirse" no aparece | Ya eres miembro o ya hay solicitud pendiente | Revisa tu estado en la ficha |
| La solicitud no llega al capitán | Error de AJAX | Revisa la consola del navegador (F12) |
| El modal de relaciones no se abre | `game_network.js` no cargó | Verifica que el archivo JS existe y no tiene errores |
| Los recuerdos no se guardan | JSON malformado | Revisa que el título no esté vacío |
| Al aceptar miembro no se actualiza la lista | Error de conexión | Recarga la página manualmente |
| La tripulación no aparece en la biblioteca | Status no es `aprobada` | El staff debe aprobarla |
| No puedo acceder a zona staff | `staff_level < 2` | Solicita rango de staff al administrador |

### 23.2 Depuración de AJAX

1. Abre la consola del navegador (F12 → Console/Network).
2. Busca errores de JavaScript o respuestas HTTP 500.
3. Verifica que la URL del AJAX sea correcta:
   ```javascript
   fetch('<?= $bburl ?>/game/ajax/crew_manage.php', ...)
   ```
4. Los endpoints AJAX devuelven JSON. Busca en la pestaña Network la respuesta.

### 23.3 Reseteo de Membresía

Si un personaje queda atrapado en un estado inconsistente (ej: tiene `tripulacion_id` pero no hay fila en `game_tripulacion_miembros`):

```sql
-- Verificar estado
SELECT id, tripulacion_id FROM game_personajes WHERE id = X;

-- Forzar salida de la tripulación
UPDATE game_personajes SET tripulacion_id = NULL WHERE id = X;
DELETE FROM game_tripulacion_miembros WHERE pj_id = X;
```

### 23.4 Recuperación de Tripulación Eliminada

Si se disuelve una tripulación por error:
1. Los miembros tienen `tripulacion_id = NULL`.
2. No hay copia de seguridad automática.
3. Solución: recrear la tripulación desde cero y reasignar miembros manualmente.

---

*Fin del documento — Sistema de Tripulaciones v3.0*
