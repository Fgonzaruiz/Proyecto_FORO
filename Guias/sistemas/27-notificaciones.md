# SISTEMA DE NOTIFICACIONES — GUÍA COMPLETA

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 27
> **Propósito:** Documentar exhaustivamente el subsistema de notificaciones: modelo de datos, servicios, helpers, AJAX endpoints, JS frontend, tipos de notificación, flujos de entrega, UI display, filosofía de diseño y consejos para jugadores y staff.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [Modelo de Datos — Tabla `game_notifications`](#2-modelo-de-datos)
3. [NotificationService — Capa de Servicio](#3-notificationservice)
4. [game_create_notification — Helper Global](#4-game_create_notification)
5. [Tipos de Notificación](#5-tipos-de-notificación)
6. [AJAX Endpoints](#6-ajax-endpoints)
7. [JavaScript — Frontend](#7-javascript)
8. [UI Display — Vista de Listado](#8-ui-display)
9. [Flujo de Entrega — Polling y Badge](#9-flujo-de-entrega)
10. [Casos de Uso por Tipo](#10-casos-de-uso-por-tipo)
11. [Integraciones con Otros Sistemas](#11-integraciones-con-otros-sistemas)
12. [Seguridad y Permisos](#12-seguridad-y-permisos)
13. [Filosofía de Diseño](#13-filosofía-de-diseño)
14. [Consejos para Jugadores](#14-consejos-para-jugadores)
15. [Consejos para Staff](#15-consejos-para-staff)
16. [Guía de Troubleshooting](#16-guía-de-troubleshooting)

---

## 1. Arquitectura General

### 1.1 Capas del Subsistema

```
┌──────────────────────────────────────────────────────────────────┐
│                        CLIENTE (Navegador)                        │
│  ┌───────────────────────────────────────────────────────────┐    │
│  │  notificaciones.js                                         │    │
│  │  ─ marcarLeida() / marcarTodasLeidas()                    │    │
│  │  ─ toggleDismiss() / deleteNotif()                        │    │
│  │  ─ actualizarBadge() (polling cada evento)                │    │
│  │  ─ resolverPropuestaTrama() (busquedas_contact)          │    │
│  └──────────────────────┬────────────────────────────────────┘    │
└─────────────────────────┼─────────────────────────────────────────┘
                          │ HTTP POST/GET + JSON
┌─────────────────────────┼─────────────────────────────────────────┐
│  ┌──────────────────────▼──────────────────────────────────────┐  │
│  │          AJAX (game/ajax/notifications_*.php)                │  │
│  │  notifications_list.php        → GET  → lista paginada      │  │
│  │  notifications_count.php       → GET  → conteo no leídas    │  │
│  │  notifications_mark_read.php   → POST → marcar una leída    │  │
│  │  notifications_mark_all_read.php→POST → marcar todas leídas │  │
│  │  notifications_dismiss.php     → POST → silenciar/reactivar │  │
│  │  notifications_delete.php      → POST → borrar definitivo   │  │
│  └──────────────────────────────┬───────────────────────────────┘  │
│                                 │                                   │
│                                 ▼                                   │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │              PHP — CAPA DE APLICACIÓN                         │  │
│  │  NotificationService (src/Application/Services/)              │  │
│  │  ─ create / createForActiveCharacter / list                   │  │
│  │  ─ unreadCount / markRead / markAllRead                       │  │
│  │  ─ toggleDismiss / delete                                     │  │
│  │                                                               │  │
│  │  Helpers (game_postcharacter.php)                             │  │
│  │  ─ game_create_notification() (función global)                │  │
│  └──────────────────────────────┬───────────────────────────────┘  │
│                                 │                                   │
│                                 ▼                                   │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │          MySQL — Tabla game_notifications                     │  │
│  │  id, user_id, character_id, type, title, body,               │  │
│  │  link, is_read, is_dismissed, created_at                     │  │
│  └──────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
```

### 1.2 Filosofía de la Arquitectura

**¿Por qué tabla dedicada en MySQL y no un sistema de colas?**

- **Simplicidad operativa:** MyBB ya corre PHP + MySQL. Añadir Redis, RabbitMQ o un bus de mensajes para las notificaciones habría añadido complejidad innecesaria para un foro RPG.
- **Persistencia inmediata:** Cada notificación se escribe directamente en `game_notifications` en la misma transacción que la acción que la genera. No hay riesgo de pérdida por colas caídas.
- **Cero dependencias externas:** El sistema entero (creación, listado, badge, marcado) vive en una tabla InnoDB, un servicio PHP y seis AJAX endpoints.

**¿Por qué dos formas de crear notificaciones (servicio y helper global)?**

- `NotificationService::create()` es el método canónico, con tipado fuerte y namespacing. Se usa dentro del módulo `game/` (src y AJAX).
- `game_create_notification()` es un helper global definido en el plugin `game_postcharacter.php`, disponible desde hooks de MyBB y scripts legacy que no tienen acceso al autoloader. Ambos hacen exactamente el mismo INSERT.

**¿Por qué separar `is_read` e `is_dismissed`?**

- `is_read = 1` significa "el usuario vio esto". No se borra, sigue visible pero sin énfasis.
- `is_dismissed = 1` significa "silencio esta notificación". No se cuenta en el badge. Es un estado intermedio entre leída y borrada.
- Esta dualidad permite al usuario silenciar notificaciones sin perder el histórico.

---

## 2. Modelo de Datos

### 2.1 Tabla `game_notifications`

```sql
CREATE TABLE mybb_game_notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    character_id INT DEFAULT NULL,
    `type`      VARCHAR(50) NOT NULL DEFAULT 'system',
    title       VARCHAR(255) NOT NULL,
    body        TEXT,
    link        VARCHAR(500) DEFAULT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    is_dismissed TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Descripción de Columnas

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT AUTO_INCREMENT | Identificador único de la notificación |
| `user_id` | INT NOT NULL | ID del usuario MyBB destinatario (NO del personaje) |
| `character_id` | INT DEFAULT NULL | ID del personaje asociado (opcional). NULL si es global del usuario |
| `type` | VARCHAR(50) | Tipo de notificación (ver sección 5) |
| `title` | VARCHAR(255) | Título corto visible |
| `body` | TEXT | Cuerpo descriptivo (opcional) |
| `link` | VARCHAR(500) | Enlace relativo/absoluto al contexto de la notificación |
| `is_read` | TINYINT(1) | 0 = no leída, 1 = leída |
| `is_dismissed` | TINYINT(1) | 0 = activa, 1 = silenciada (no cuenta en badge) |
| `created_at` | TIMESTAMP | Fecha/hora de creación |

### 2.3 Índices

- **`idx_user_read`** (user_id, is_read): Cubre la consulta más frecuente: "dame el conteo de no leídas para este usuario". Es un índice compuesto que evita full scans.
- **`idx_user`** (user_id): Cubre consultas de listado paginado por usuario.

### 2.4 Consideraciones de Diseño

- **`character_id` es nullable**: Una notificación puede ser global del usuario (e.g., "tu personaje X recibió un mensaje") o no tener personaje asociado (e.g., "bienvenida al foro"). El filtrado por personaje activo se hace con `WHERE character_id IS NULL OR character_id = ?`.
- **`link`** puede ser URL absoluta (`https://...`) o relativa (`game/public/personaje.php?pj=5`). El frontend normaliza con el `bburl`.
- **`body`** es TEXT aunque rara vez supere 200 caracteres. Se trunca a 120 chars en la vista de listado.
- **`type`** es VARCHAR(50) deliberadamente sin ENUM: añadir un tipo no requiere migración DDL.

### 2.5 Migración

El archivo `game/sql/migrate_notifications.php` crea la tabla si no existe (`CREATE TABLE IF NOT EXISTS`). Se ejecuta desde el navegador como script admin (requiere `cancp = 1`):

```
/game/sql/migrate_notifications.php
```

No hay versioning formal de schemas (B-DAT-01). La migración es idempotente.

---

## 3. NotificationService

### 3.1 Archivo

`back/forum/game/src/Application/Services/NotificationService.php`

### 3.2 API Pública

#### `create(int $userId, string $type, string $title, string $body = '', string $link = '', ?int $characterId = null): void`

Método principal de creación. Inserta una fila en `game_notifications`.

Parámetros:
- **`$userId`**: ID del usuario MyBB destinatario (obligatorio).
- **`$type`**: String con el tipo de notificación.
- **`$title`**: Título visible (máx. 255 chars).
- **`$body`**: Cuerpo descriptivo (opcional, truncado a 120 chars en UI).
- **`$link`**: Enlace al contexto (URL absoluta o relativa).
- **`$characterId`**: ID del personaje asociado (opcional, puede ser null para notificaciones globales).

```php
NotificationService::create(
    $userId,
    'competencia_acquired',
    'Nueva competencia',
    'Has adquirido la competencia: Combate con Espadas.',
    '/game/public/personaje.php?pj=5',
    $characterId
);
```

#### `createForActiveCharacter(int $userId, int $characterId, string $type, string $title, string $body = '', string $link = ''): void`

Wrapper que llama a `create()` con `$characterId` obligatorio. Existe por claridad semántica cuando sabes que la notificación va ligada al personaje activo.

#### `list(int $userId, ?int $characterId = null, int $page = 1, int $perPage = 20): array`

Lista paginada de notificaciones para un usuario.

Parámetros:
- **`$userId`**: ID del usuario.
- **`$characterId`**: Personaje activo actual (para filtrar notificaciones). Si es null, solo muestra notificaciones globales (`character_id IS NULL`).
- **`$page`**: Número de página (1-indexed).
- **`$perPage`**: Items por página (mínimo 1, default 20).

Reglas de filtrado:
- Si `$characterId` está presente: `WHERE (character_id IS NULL OR character_id = $characterId)`.
- Si no: `WHERE character_id IS NULL`.
- Si el personaje activo NO tiene `staff_level >= 2`, se excluyen las de tipo `admin_request_pending` (los jugadores normales no ven peticiones pendientes de staff).

Retorno:
```php
[
    'items' => [
        [
            'id' => 1,
            'character_id' => 5,
            'type' => 'card_request_resolved',
            'title' => 'Solicitud de Carta Aprobada',
            'body' => 'Tu solicitud para la carta "Gomu Gomu no Mi" ha sido aprobada.',
            'link' => '...',
            'is_read' => false,
            'is_dismissed' => false,
            'created_at' => '2026-06-10 15:30:00',
        ],
    ],
    'total' => 42,
    'page' => 1,
    'per_page' => 20,
    'total_pages' => 3,
]
```

#### `unreadCount(int $userId, ?int $characterId = null): int`

Retorna el número de notificaciones no leídas Y no descartadas para el usuario.

- Usa el índice compuesto `idx_user_read` (user_id, is_read) para máximo rendimiento.
- Aplica el mismo filtro de `staff_level` que `list()`.

```php
$unread = NotificationService::unreadCount($uid, $activePjId);
```

#### `markRead(int $id, int $userId): bool`

Marca una notificación como leída (`is_read = 1`). Verifica que la notificación pertenezca al usuario (seguridad).

```php
NotificationService::markRead(42, $uid); // true si existía y era del usuario
```

#### `markAllRead(int $userId, ?int $characterId = null): void`

Marca todas las notificaciones del usuario como leídas. Aplica el mismo filtro de personaje que `list()`.

#### `toggleDismiss(int $id, int $userId, bool $dismissed): bool`

Silencia o reactiva una notificación. Cuando `$dismissed = true`, la notificación:
- Deja de contar en `unreadCount()`.
- Muestra el icono de campana tachada.
- Sigue visible en el listado.

#### `delete(int $id, int $userId): bool`

Borra permanentemente una notificación. Verifica ownership.

```php
NotificationService::delete(42, $uid);
```

### 3.3 Flujo Interno de `list()`

```
list($userId, $characterId, $page, $perPage)
│
├─ 1. Obtener staff_level del personaje activo
│    └─ Si es null o 0 → filtrar admin_request_pending
│
├─ 2. Construir $charFilter:
│    └─ characterId !== null → "AND (character_id IS NULL OR character_id = X)"
│    └─ characterId === null → "AND character_id IS NULL"
│
├─ 3. COUNT(*) con filtros → total / total_pages
│
├─ 4. SELECT con filtros + ORDER BY created_at DESC + LIMIT/OFFSET
│
└─ 5. Mapear filas a array asociativo con tipado correcto
```

### 3.4 Filosofía del Servicio

- **Static methods** sin inyección de dependencias. El servicio es una fachada sobre `$db` global. Esto es deliberado: el módulo `game/` no usa DI container, y toda la lógica de negocio se orquesta via PHP procedural + clases estáticas (ver B-ORO-01).
- **No hay repositorio separado.** La capa de acceso a datos está embebida en el servicio. Para el tamaño actual del proyecto, un repositorio separado sería over-engineering.
- **No hay bound context.** Todas las consultas son SQL directo con `$db->query()`. No se usa QueryBuilder ni ORM.

---

## 4. game_create_notification

### 4.1 Archivo

`back/forum/inc/plugins/game_postcharacter.php` — línea 1037

### 4.2 Definición

```php
function game_create_notification(
    int $userId,
    string $type,
    string $title,
    string $body = '',
    string $link = '',
    ?int $characterId = null
): void {
    global $db;
    $prefix = TABLE_PREFIX;
    $cid = $characterId ? (int)$characterId : 'NULL';
    $db->write_query(
        "INSERT INTO {$prefix}game_notifications (user_id, character_id, type, title, body, link)
         VALUES ({$userId}, {$cid}, '{$db->escape_string($type)}', '{$db->escape_string($title)}', '{$db->escape_string($body)}', '{$db->escape_string($link)}')"
    );
}
```

### 4.3 ¿Por qué existe?

El plugin `game_postcharacter.php` se carga en cada página de MyBB (hooks). Define funciones globales accesibles desde cualquier script PHP, incluso desde código que no usa el autoloader de Composer. `NotificationService::create()` requiere `use Game\Application\Services\NotificationService;` y acceso al autoloader de `game/`, lo cual no está garantizado en scripts legacy o hooks de terceros.

### 4.4 Diferencia con NotificationService::create()

| Aspecto | `NotificationService::create()` | `game_create_notification()` |
|---------|--------------------------------|------------------------------|
| Namespace | Sí (Game\Application\Services) | No (función global) |
| Disponible | Solo con autoloader | Siempre (plugin MyBB) |
| Seguridad | Tipado nativo PHP | Tipado nativo PHP |
| SQL | Misma consulta | Misma consulta |
| Uso preferido | Código nuevo en `game/` | Hooks y scripts legacy |

### 4.5 Patrón de Llamada Típico

Casi todos los lugares que llaman a `game_create_notification()` lo hacen envuelto en un `if (function_exists(...))`:

```php
if (function_exists('game_create_notification')) {
    game_create_notification($uid, 'competencia_acquired', 'Nueva competencia', $body, $link, $characterId);
}
```

Esto es porque el plugin puede no estar instalado/activado. El `function_exists` evita errores fatales si el plugin se desactiva pero el código en `game/ajax/` sigue ejecutándose.

---

## 5. Tipos de Notificación

### 5.1 Catálogo Completo

| Type | Origen | Visibilidad | Qué Notifica |
|------|--------|------------|--------------|
| `admin_request_pending` | AdminRequestService::notifyStaffPending | Solo staff (level >= 2) | Nueva petición administrativa pendiente de revisión |
| `card_request_moderated` | cards_resolve_request.php | Jugador solicitante | Staff moderó (pidió cambios) una solicitud de carta |
| `card_request_reply` | cards_resolve_request.php | Jugador solicitante | Staff respondió a una solicitud de carta |
| `card_request_resolved` | cards_resolve_request.php | Jugador solicitante | Solicitud de carta aprobada o denegada definitivamente |
| `competencia_acquired` | acquire_competencia.php | Jugador | Adquisición de nueva competencia |
| `haki_upgrade_pending` | haki_upgrade.php, haki_resolve.php | Staff o jugador | Solicitud de upgrade de Haki pendiente |
| `territory_tax` | zona_staff_islas.php | Dueños de isla | Beneficios/impuestos de territorio recibidos |
| `busqueda_contact` | busquedas_contact.php | Creador de búsqueda | Alguien quiere contactar para una trama |
| `system` | Múltiples orígenes | Jugador | Notificaciones genéricas del sistema |

### 5.2 Descripción Detallada

#### `admin_request_pending`

- **Creada por:** `AdminRequestService::notifyStaffPending()`.
- **¿Quién la recibe?:** Todos los personajes con `staff_level >= 2` (staff administrativo).
- **¿Por qué se filtra?:** Los jugadores normales no deben ver peticiones pendientes de otros. El filtro está en `NotificationService::list()` y `unreadCount()`, que excluyen este type si el personaje activo no tiene `staff_level >= 2`.
- **Contenido típico:** Título: "Nueva petición: Subir de rango", link a la petición.
- **Diseño:** No se usa `character_id` porque va dirigida a todos los miembros del staff.

#### `card_request_moderated`

- **Creada por:** `cards_resolve_request.php` cuando un staff selecciona "Moderar" (pedir cambios) en una solicitud de carta.
- **Contenido típico:** "Tu solicitud para [Carta] requiere cambios: [mensaje del staff]".
- **Link:** A la solicitud de carta para ver los comentarios.

#### `card_request_reply`

- **Creada por:** `cards_resolve_request.php` cuando un staff responde (sin cambiar estado) a una solicitud.
- **Diferencia con moderated:** No cambia el estado de la solicitud, solo añade un comentario.

#### `card_request_resolved`

- **Creada por:** `cards_resolve_request.php` cuando una solicitud es aprobada o denegada definitivamente.
- **Contenido típico:** "Tu solicitud para [Carta] ha sido aprobada/denegada".
- **Link:** Al perfil del personaje o a la carta.

#### `competencia_acquired`

- **Creada por:** `acquire_competencia.php` después de que un jugador adquiere exitosamente una competencia via el sistema de PD.
- **Contenido típico:** "Has adquirido la competencia: Combate con Espadas (Rango C)".
- **Link:** A la ficha del personaje.
- **Nota:** También puede crear notificaciones `upgrade_competencia_grado.php` para subidas de rango.

#### `haki_upgrade_pending`

- **Creada por:** `haki_upgrade.php` y `haki_resolve.php`.
- **Flujo:**
  1. Jugador solicita upgrade de Haki → notificación al staff (`'haki_upgrade_pending'`).
  2. Staff resuelve → notificación al jugador (`'system'`) con el resultado.
- **Contenido típico:** "Solicitud de mejora de Haki: Kenbunshoku (Nivel 2)".
- **Filosofía:** Separar este type permite al staff filtrar solo solicitudes de Haki en el futuro.

#### `territory_tax`

- **Creada por:** `zona_staff_islas.php` cuando el staff ejecuta la distribución de impuestos de islas.
- **Flujo:**
  1. Staff hace clic en "Distribuir Impuestos" en la zona staff de islas.
  2. El script itera sobre las islas con controlador.
  3. Para cada controlador (PJ o tripulación), inserta una notificación.
- **Contenido típico:** "Has recibido los beneficios e impuestos (Berries y Bienes) por el control de: [Isla]. Administra los recursos en tu ficha."
- **Nota:** Este es uno de los pocos lugares que inserta directamente con SQL en lugar de usar el servicio o helper. Usa la columna `message` en lugar de `title`/`body` — inconsistencia histórica.

#### `busqueda_contact`

- **Creada por:** `busquedas_contact.php`.
- **Flujo:**
  1. Un jugador encuentra una búsqueda de trama en el tablón.
  2. Hace clic en "Contactar".
  3. Se crea una notificación de tipo `busqueda_contact` para el creador de la búsqueda.
  4. El creador puede "Aceptar Trama" o "Seguir buscando" desde la propia notificación.
- **Contenido típico:** Título: "[Nombre] quiere tu trama '[Título]'", body: mensaje genérico + link con formato `busqueda_contact:{busqueda_id}:{requester_pj_id}`.
- **Link especial:** No es una URL real, sino un identificador compuesto `busqueda_contact:{busqueda_id}:{requester_pj_id}` que el frontend NO convierte en enlace. En su lugar, muestra botones de acción.
- **Deduplicación:** `busquedas_contact.php` verifica que no exista ya una notificación no leída y no descartada del mismo type con el mismo link antes de crear una nueva.

#### `system`

- **Creada por:** Múltiples orígenes: `AdminRequestService` (resolución de peticiones), `haki_resolve.php`, `busquedas_submit.php`, `busquedas_action.php`, `busquedas_resolve_contact.php`, entre otros.
- **Es el tipo catch-all.** Cualquier notificación que no encaje en los tipos específicos usa este.
- **Contenido típico:** "Recompensa de Misión Aprobada — Se han otorgado 50 PD y 10000 Berries".

### 5.3 Cómo Añadir un Nuevo Tipo

1. Definir el string del type en el lugar donde se crea la notificación.
2. Si se necesita filtrado, añadir lógica en `NotificationService::list()` y `unreadCount()`.
3. Opcionalmente, añadir icono y label en `notificaciones.php` (arrays `$typeIcons` y `$typeLabels`).
4. Si aplica, añadir CSS en `rpg_custom.css` para estilos específicos.

No hay ENUM en la BD ni registro centralizado de types. La convención es usar snake_case.

---

## 6. AJAX Endpoints

### 6.1 Vista General

| Archivo | Método | Input | Output |
|---------|--------|-------|--------|
| `notifications_list.php` | GET | `page`, `per_page` | `{items, total, page, total_pages}` |
| `notifications_count.php` | GET | — | `{unread: N}` |
| `notifications_mark_read.php` | POST | `id` | `{marked_read: bool}` |
| `notifications_mark_all_read.php` | POST | — | `{marked_all_read: true}` |
| `notifications_dismiss.php` | POST | `id`, `dismissed` | `{toggled: bool, is_dismissed: bool}` |
| `notifications_delete.php` | POST | `id` | `{deleted: bool}` |

### 6.2 notifications_list.php

**Propósito:** Endpoint AJAX para carga asíncrona de notificaciones (scroll infinito o carga por lotes).

**Autenticación:** Requiere usuario logueado. Responde 401 si no.

**Parámetros GET:**
- `page` (int, default 1): Página actual.
- `per_page` (int, default 20, max 50): Items por página.

**Respuesta:**
```json
{
    "ok": true,
    "data": {
        "items": [
            {
                "id": 1,
                "character_id": 5,
                "type": "system",
                "title": "Bienvenido",
                "body": "Tu personaje ha sido creado.",
                "link": null,
                "is_read": false,
                "is_dismissed": false,
                "created_at": "2026-06-10 12:00:00"
            }
        ],
        "total": 42,
        "page": 1,
        "per_page": 20,
        "total_pages": 3
    },
    "meta": { "endpoint": "notifications_list" }
}
```

**Cache-Control:** `no-store, no-cache, must-revalidate, max-age=0` — nunca cachear.

### 6.3 notifications_count.php

**Propósito:** Obtener el conteo de notificaciones no leídas para el badge del header.

**Autenticación:** Requiere usuario logueado.

**Respuesta:**
```json
{
    "ok": true,
    "data": { "unread": 5 },
    "meta": { "endpoint": "notifications_count" }
}
```

**Frecuencia de llamado:** Cada vez que el usuario realiza una acción que podría cambiar el conteo (marcar leída, borrar, recibir nueva notificación). También se usa `? _t = Date.now()` para evitar cache.

### 6.4 notifications_mark_read.php

**Propósito:** Marcar una notificación individual como leída.

**Autenticación:** Requiere usuario logueado + POST + CSRF.

**Input:**
```json
{ "id": 42 }
```

**Validaciones:**
- `id` debe ser > 0.
- `NotificationService::markRead()` verifica que la notificación pertenezca al usuario (`WHERE id = ? AND user_id = ?`).

### 6.5 notifications_mark_all_read.php

**Propósito:** Marcar todas las notificaciones del usuario como leídas.

**Autenticación:** Requiere usuario logueado + POST + CSRF.

**Filtrado:** Aplica el filtro de personaje activo: solo marca las notificaciones del usuario que corresponden al personaje activo (o globales).

**Sin validación de staff_level:** A diferencia de `list()`, markAllRead no excluye `admin_request_pending` porque el staff puede tener notificaciones de ese tipo que también quiere marcar como leídas.

### 6.6 notifications_dismiss.php

**Propósito:** Silenciar o reactivar una notificación sin borrarla.

**Autenticación:** Requiere usuario logueado + POST + CSRF.

**Input:**
```json
{ "id": 42, "dismissed": true }
```

**Comportamiento:**
- `dismissed = true`: La notificación se descarta (no cuenta en badge, icono de campana tachada).
- `dismissed = false`: Se reactiva (vuelve a contar si no está leída).

### 6.7 notifications_delete.php

**Propósito:** Borrar permanentemente una notificación.

**Autenticación:** Requiere usuario logueado + POST + CSRF.

**Seguridad:** Verifica ownership con `WHERE id = ? AND user_id = ?`. El usuario no puede borrar notificaciones de otros.

**Confirmación:** El frontend pide confirmación con `confirm()` antes de llamar al endpoint.

### 6.8 Formato de Respuesta Común

Todos los endpoints usan `JsonResponder` para formatear respuestas:

```php
namespace Game\Presentation\Api;

class JsonResponder {
    public static function ok($data, array $meta = []): void
    public static function fail(int $httpCode, array $error, array $meta = []): void
}
```

Respuesta exitosa:
```json
{ "ok": true, "data": {...}, "meta": { "endpoint": "notifications_mark_read" } }
```

Respuesta de error:
```json
{ "ok": false, "error": { "code": "unauthorized", "message": "Login required" }, "meta": { "endpoint": "notifications_list" } }
```

---

## 7. JavaScript

### 7.1 Archivo

`back/forum/jscripts/game/notificaciones.js`

### 7.2 Funciones Exportadas

#### `notifPost(path, payload)`

Helper que envía un POST JSON al backend. Reutiliza `gamePostJson` si existe (función global de game), o usa `fetch` directamente.

```javascript
notifPost('/notifications_mark_read.php', { id: 42 })
// → POST /game/ajax/notifications_mark_read.php { "id": 42, "my_post_key": "..." }
```

#### `marcarLeida(id, el)`

Marca una notificación como leída via AJAX y actualiza el badge. Se llama desde el `onclick` del enlace de la notificación:
```html
<a href="..." onclick="return marcarLeida(42, this)">Título</a>
```

Retorna `true` para que el navegador siga el enlace después de marcar como leída.

#### `marcarTodasLeidas()`

Marca todas como leídas via AJAX. Se llama desde el botón "Leer todas" del listado.

#### `toggleDismiss(id, dismissed, btn)`

Silencia o reactiva una notificación. Actualiza el icono del botón (`fa-bell` ↔ `fa-bell-slash`) y el tooltip.

#### `deleteNotif(id, btn)`

Borra una notificación. Pide confirmación primero. Remueve la fila del DOM si el servidor confirma.

#### `actualizarBadge()`

Consulta `notifications_count.php` y actualiza el badge en el header:
- Si `unread > 0`: muestra el número y añade clase `has-unread` a la campana.
- Si `unread === 0`: oculta el badge y quita `has-unread`.

#### `resolverPropuestaTrama(notifId, action, btn)`

Maneja la aceptación o rechazo de una propuesta de trama (`busqueda_contact`). Llama a `busquedas_resolve_contact.php` con FormData. Muestra feedback visual en la notificación (mensaje de estado) y deshabilita los botones.

### 7.3 Configuración

```javascript
window.NOTIFICACIONES_CONFIG = {
    bburl: 'https://foro.ejemplo.com',
    ajaxBase: 'https://foro.ejemplo.com/game/ajax'
};
```

### 7.4 Dependencias

- `window.GAME_CSRF`: Token CSRF de MyBB (post_key).
- `window.gamePostJson()` / `window.gamePostForm()`: Helpers globales de game (opcionales, fallback a fetch).

### 7.5 Inicialización

El script se carga al final del body en `notificaciones.php`:
```php
<script>
window.NOTIFICACIONES_CONFIG = { bburl: '<?= $bb ?>', ajaxBase: '<?= $bb ?>/game/ajax' };
</script>
<script src=".../notificaciones.js?v=1"></script>
```

No hay inicialización automática del badge al cargar página — el servidor renderiza el badge inicial en el HTML del header (ver sección 9).

---

## 8. UI Display

### 8.1 Página de Notificaciones: `notificaciones.php`

**Ruta:** `/game/public/notificaciones.php`

**Template:** PHP inline en el mismo archivo (no hay archivo de template separado).

**Estructura visual:**

```
┌─────────────────────────────────────────────────────────┐
│  🔔 Notificaciones              [📚 Leer todas]        │
├────────┬────────────────────────────┬────────┬─────────┤
│        │                            │ Fecha  │ Acciones│
├────────┼────────────────────────────┼────────┼─────────┤
│  🔔    │ Título (negrita si no leída)│ 10/06  │ [🔕][🗑]│
│        │ [Sistema] — Cuerpo...      │ 15:30  │         │
├────────┼────────────────────────────┼────────┼─────────┤
│  📩    │ [Propuesta de Trama]       │ 09/06  │ [🔕][🗑]│
│        │ [✅ Aceptar] [❌ Rechazar] │ 10:15  │         │
└────────┴────────────────────────────┴────────┴─────────┘
  [1] [2] [3] ... (paginación si > 20)
```

**Elementos de cada fila:**

1. **Icono** (`notif-row-icon`): Font Awesome según el tipo. Los no leídos tienen el icono en color `--accent-primary`.
2. **Cuerpo** (`notif-row-body`):
   - Título en negrita si no leído (clase `notif-title--bold`).
   - Badge de tipo (`notif-type-badge`): texto en mayúsculas.
   - Body truncado a 120 caracteres.
   - Para `busqueda_contact`: botones "Aceptar Trama" / "Seguir buscando".
3. **Fecha** (`notif-row-date`): formato `dd/mm/YYYY HH:ii`.
4. **Acciones** (`notif-row-actions`):
   - Dismiss (campana): silencia o reactiva.
   - Delete (papelera): borra permanentemente.

**Estados visuales:**
- **No leída:** fila con fondo `--bg-card-hover`, título en bold, icono coloreado.
- **Leída:** fila normal, título en peso normal, icono gris.
- **Descarte silenciado:** icono `fa-bell-slash`.
- **Procesada** (`busqueda_contact` resuelta): fila con opacidad 0.7, mensaje de estado.

### 8.2 Campana en el Header

**Archivo:** `front/templates/mybb/global/header.html` (líneas 55-58)

```html
<a href="/game/public/notificaciones.php" class="action-btn notification-bell" title="Notificaciones" id="notification-bell">
    <i class="fas fa-bell"></i>
    <span class="notification-badge is-hidden" id="notification-badge">0</span>
</a>
```

**Comportamiento visual:**
- Badge invisible por defecto (clase `is-hidden`).
- Cuando hay no leídas: badge muestra el número, campana cambia a color `--accent-primary` y reproduce la animación `bell-ring`.
- La animación `bell-ring` es un keyframe CSS que rota la campana ±12°.

### 8.3 Tarjeta en el Index

**Archivo:** `game/public/index.php` (líneas 40-44)

```html
<a href="notificaciones.php" class="game-card game-card--notif">
    <i class="fas fa-bell"></i>
    <h3>Notificaciones</h3>
    <p>Consulta tus alertas</p>
</a>
```

### 8.4 CSS

**Archivo:** `back/forum/rpg_custom.css`

Clases principales:
- `.notification-bell`: posicionamiento relativo, color muted.
- `.notification-bell.has-unread`: color accent + animación bell-ring.
- `.notification-badge`: círculo rosa en top-right.
- `.notif-page`: contenedor principal (max-width 900px).
- `.notif-row`: grid de 4 columnas (icono, cuerpo, fecha, acciones).
- `.notif-unread`: fondo highlight.
- `.notif-btn-accept` / `.notif-btn-reject`: degradados verde y rojo para los botones de trama.

---

## 9. Flujo de Entrega

### 9.1 ¿Polling o Push?

**Polling (no Push).** El sistema no usa WebSockets, Server-Sent Events ni long-polling. La detección de nuevas notificaciones ocurre de forma reactiva:

1. **Al cargar la página:** El servidor PHP renderiza el badge inicial en el HTML del header (aunque el template actualmente no lo hace — el badge se inicializa oculto).
2. **Después de cada acción del usuario:** `actualizarBadge()` se llama desde `marcarLeida()`, `marcarTodasLeidas()`, `toggleDismiss()`, `deleteNotif()` y `resolverPropuestaTrama()`.
3. **No hay polling periódico.** Si otro usuario (staff) crea una notificación mientras el usuario está navegando, este no la verá hasta que recargue la página o realice una acción que dispare `actualizarBadge()`.

### 9.2 ¿Por qué polling reactivo y no push?

- **Infraestructura:** MyBB + Apache no soporta WebSockets nativamente. Añadir un servicio de push (Pusher, Firebase) habría sido dependencia externa.
- **Simplicidad:** Para un foro RPG, la latencia de "hasta que recargues o hagas clic" es aceptable. No es un chat en tiempo real.
- **Coste:** Cero infraestructura adicional.

### 9.3 Flujo Completo

```
1. Staff aprueba solicitud de carta
   │
   ├─ cards_resolve_request.php
   │   ├─ Actualiza estado de la solicitud
   │   ├─ game_create_notification($uid, 'card_request_resolved', ...)
   │   └─ (no hay push, no hay broadcast)
   │
   └─ [Tiempo después] Jugador navega al foro
       │
       ├─ Header renderizado con badge oculto
       │
       ├─ Jugador hace clic en "Notificaciones"
       │   └─ notificaciones.php → NotificationService::list() → muestra la nueva
       │
       ├─ Jugador hace clic en el título
       │   ├─ marcarLeida() → notifications_mark_read.php
       │   └─ actualizarBadge() → notifications_count.php → badge = 0
       │
       └─ (Fin del ciclo)
```

---

## 10. Casos de Uso por Tipo

### 10.1 Petición Administrativa Pendiente

```
Actor: Jugador
Sistema: AdminRequestService (al crear una petición)
Tipo: admin_request_pending
Destinatario: Todos los miembros del staff (staff_level >= 2)
Link: A la página de revisión de peticiones
```

El staff ve la notificación en su listado. Al hacer clic, se marca como leída y navega a la petición. Al resolverla, el sistema crea otra notificación de tipo `system` al jugador con el resultado.

### 10.2 Solicitud de Carta Moderada/Respondida/Resuelta

```
Actor: Staff
Sistema: cards_resolve_request.php
Tipo: card_request_moderated / card_request_reply / card_request_resolved
Destinatario: Jugador solicitante
Link: A la solicitud de carta
```

Tres tipos distintos para tres estados: moderación (pedir cambios), respuesta (solo comentario), resolución (aprobado/denegado). Esto permite al jugador distinguir entre "me pidieron cambios" y "ya está resuelto".

### 10.3 Adquisición de Competencia

```
Actor: Jugador (via interface)
Sistema: acquire_competencia.php
Tipo: competencia_acquired
Destinatario: El mismo jugador
Link: Ficha del personaje
```

Confirmación de que la compra fue exitosa. También se usa en `upgrade_competencia_grado.php` para subidas de rango.

### 10.4 Upgrade de Haki Pendiente

```
Actor: Jugador
Sistema: haki_upgrade.php
Tipo: haki_upgrade_pending
Destinatario: Staff
Link: A la sección de resolución de Haki
```

Similar a `admin_request_pending` pero específico para Haki. El staff resuelve desde `haki_resolve.php`, que a su vez notifica al jugador con tipo `system`.

### 10.5 Impuestos de Territorio

```
Actor: Staff (acción manual)
Sistema: zona_staff_islas.php
Tipo: territory_tax
Destinatario: Dueño de la isla (PJ o líder de tripulación)
Link: Ninguno (notificación informativa)
```

Notificación masiva: se crean N notificaciones en un bucle. Históricamente usa INSERT directo con columna `message` (no `title`/`body`), lo cual es una inconsistencia — el campo `message` no existe en el schema actual.

### 10.6 Propuesta de Trama (Búsqueda Contacto)

```
Actor: Jugador A (contacta)
Sistema: busquedas_contact.php
Tipo: busqueda_contact
Destinatario: Jugador B (creador de búsqueda)
Link: busqueda_contact:{busqueda_id}:{requester_pj_id} (no es URL real)
```

Es la notificación más compleja porque:
- El link no es navegable; en su lugar se muestran botones de acción.
- Tiene deduplicación explícita.
- Al aceptar/rechazar, se dispara `busquedas_resolve_contact.php` que actualiza el estado de la búsqueda y envía un MD (mensaje directo) al solicitante.

---

## 11. Integraciones con Otros Sistemas

### 11.1 Sistema de Personajes

- `NotificationService::list()` usa `active_pj_id` de `game_user_config` para filtrar notificaciones por personaje activo.
- `character_id` en la notificación permite asociarla a un personaje específico.
- La exclusión de `admin_request_pending` depende de `staff_level` en `game_personajes`.

### 11.2 Sistema de Cartas

- `cards_resolve_request.php` genera tres tipos de notificaciones (`moderated`, `reply`, `resolved`).
- Las notificaciones incluyen link a la solicitud de carta para seguimiento.

### 11.3 Sistema de Haki

- `haki_upgrade.php` → `haki_upgrade_pending` al staff.
- `haki_resolve.php` → `system` al jugador con resultado.
- `haki_conquistador_roll.php` → `system` al jugador sobre el resultado del rol de conquistador.

### 11.4 Sistema de Búsquedas

- `busquedas_contact.php` → `busqueda_contact` al creador.
- `busquedas_submit.php` → `system` al creador (búsqueda creada/modificada).
- `busquedas_action.php` → `system` al creador (cambios de estado).
- `busquedas_resolve_contact.php` → `system` al solicitante (aceptado/rechazado).

### 11.5 Sistema de Islas (Territorios)

- `zona_staff_islas.php` → `territory_tax` a controladores de isla.
- Se ejecuta manualmente por staff; no es automático.

### 11.6 Sistema de Misiones

- `AdminRequestService` al aprobar/denegar misiones → `system` a participantes con recompensas.

---

## 12. Seguridad y Permisos

### 12.1 Ownership

Todas las operaciones de escritura (`markRead`, `toggleDismiss`, `delete`) verifican ownership explícitamente:
```sql
UPDATE game_notifications SET is_read = 1 WHERE id = X AND user_id = Y
```

Un usuario no puede modificar notificaciones de otros, incluso si adivina el ID.

### 12.2 Filtrado de Staff

El tipo `admin_request_pending` solo es visible para personajes con `staff_level >= 2`. El filtro se aplica en:
- `NotificationService::list()` (línea 52-54)
- `NotificationService::unreadCount()` (línea 108-110)

Si un jugador normal accede al listado via AJAX o página directa, no verá las peticiones pendientes de otros.

### 12.3 CSRF

Los endpoints POST (`markRead`, `markAllRead`, `dismiss`, `delete`) requieren token CSRF de MyBB (`my_post_key`), validado por `GameAjax::requireCsrf()`.

### 12.4 Autenticación

Todos los endpoints requieren usuario logueado. `GameAjax::requireLogin()` retorna 401 si no hay sesión.

### 12.5 Validación de Inputs

- IDs se castean a `(int)` antes de usarse en SQL.
- Strings se escapan con `$db->escape_string()`.
- `$perPage` tiene límite superior (50) para evitar abusos.

---

## 13. Filosofía de Diseño

### 13.1 Principios Rectores

1. **Persistente sobre transitorio**: Cada notificación es un registro en MySQL. No hay mensajes en memoria ni colas volátiles. Si el servidor se cae después de crear una notificación pero antes de que el usuario la lea, la notificación sigue ahí.

2. **Reactivo sobre tiempo real**: No se intenta empujar notificaciones al cliente. El badge se actualiza en respuesta a acciones del usuario, no por polling periódico.

3. **Genérico sobre específico**: La tabla `game_notifications` es genérica (type como string, title/body/link opcionales). No hay columnas específicas para cada tipo de notificación. Esto permite añadir nuevos tipos sin migraciones.

4. **Filtrado en backend, no en frontend**: Los filtros de visibilidad (staff_level) se aplican en el servicio PHP, no en el JavaScript. El frontend recibe solo lo que debe ver.

5. **El usuario controla su bandeja**: Puede marcar como leída, silenciar (dismiss) o borrar cada notificación. El silencio es reversible.

### 13.2 Decisiones Técnicas (ADR-lite)

- **D-NOTIF-01: Sin colas de mensajes.** MySQL es suficiente para el volumen actual. Si el foro creciera a miles de notificaciones por minuto, se podría introducir un buffer Redis, pero no hay necesidad actual.
- **D-NOTIF-02: Sin notificaciones push.** No hay Service Workers, Firebase Cloud Messaging ni WebSockets. La entrega es bajo demanda (pull).
- **D-NOTIF-03: Tipos como string, no ENUM.** Añadir un tipo no requiere ALTER TABLE. La desventaja es que no hay validación a nivel BD, pero el código controla los tipos usados.
- **D-NOTIF-04: Dos APIs de creación (service + global helper).** El helper global existe por compatibilidad con código legacy. El service es la API canónica para código nuevo.
- **D-NOTIF-05: Dismiss soft, no hard delete.** `is_dismissed` permite al usuario silenciar notificaciones sin perder el histórico. El borrado físico es una acción explícita y permanente.

---

## 14. Consejos para Jugadores

### 14.1 Cómo Gestionar tus Notificaciones

- Revisa la campana en el header (🔔) periódicamente. El número rojo indica notificaciones sin leer.
- Puedes marcar **una** como leída haciendo clic en su título (te lleva al contexto).
- Puedes marcar **todas** como leídas con el botón "Leer todas" en la página de notificaciones.
- Si una notificación te distrae pero no quieres borrarla, usa el botón de **silenciar** (🔕). La notificación se oculta del badge pero sigue en tu listado.

### 14.2 Propuestas de Trama

- Cuando alguien contacta por tu búsqueda, verás una notificación con botones **"Aceptar Trama"** y **"Seguir buscando"**.
- Si aceptas: la búsqueda se cierra y el contacto recibe un mensaje directo tuyo.
- Si rechazas: la búsqueda sigue activa en el tablón y el contacto recibe un aviso.

### 14.3 ¿No Ves Notificaciones?

- Verifica que tienes un personaje activo seleccionado (configuración de usuario). Las notificaciones se filtran por personaje activo.
- Las notificaciones de tipo `admin_request_pending` solo son visibles para staff (no te preocupes si eres jugador normal).

---

## 15. Consejos para Staff

### 15.1 Uso de Notificaciones

- Al crear nuevas funcionalidades, usa `NotificationService::create()` (o `game_create_notification()` si es desde un hook) para informar al jugador del resultado.
- Define un type específico si el tipo de notificación necesita filtrado futuro. Si no, usa `'system'`.
- Siempre incluye un `link` que lleve al contexto de la notificación (la ficha del personaje, la solicitud, etc.).

### 15.2 Buenas Prácticas al Crear Notificaciones

```php
// ✅ Correcto
game_create_notification(
    $userId,
    'card_request_resolved',
    'Solicitud de Carta Aprobada',
    'Tu solicitud para "Gomu Gomu no Mi" ha sido aprobada.',
    rtrim($bb, '/') . '/game/public/personaje.php?pj=' . $characterId,
    $characterId
);

// ❌ Incorrecto: sin link, sin character_id, body vacío
game_create_notification($userId, 'system', 'Aprobado');
```

### 15.3 Tipo vs Sistema

- Usa types específicos (`card_request_*`, `haki_upgrade_pending`, `competencia_acquired`) cuando el sistema pueda necesitar filtrar o tematizar ese tipo en el futuro.
- Usa `'system'` para notificaciones genéricas: recompensas, bienvenidas, avisos.

### 15.4 Al Añadir un Nuevo Tipo

1. Decide el string del type (snake_case, descriptivo).
2. Si debe ser invisible para no-staff, añade lógica en `NotificationService::list()` y `unreadCount()`.
3. Añade icono y label en `notificaciones.php` si quieres un display personalizado:
   ```php
   $typeIcons['mi_tipo'] = 'fa-star';
   $typeLabels['mi_tipo'] = 'Mi Tipo';
   ```
4. Si tiene acciones especiales en UI, añade lógica en el template de `notificaciones.php`.

### 15.5 Inconsistencias Conocidas

- `zona_staff_islas.php` inserta con columna `message` que no existe en el schema. Las notificaciones `territory_tax` se crean con `title = ''` y `body = ''` porque el INSERT no especifica esas columnas. Esto es un bug: el mensaje no se muestra correctamente.
- Si trabajas en esa zona, migra a `game_create_notification()` con `title` y `body` adecuados.

---

## 16. Guía de Troubleshooting

### 16.1 El Badge de Notificaciones No se Actualiza

**Causas posibles:**
1. El script `notificaciones.js` no se cargó (error de consola, ruta incorrecta).
2. `window.GAME_CSRF` no está definido (el POST falla por CSRF).
3. `ajaxBase` está mal configurado (apunta a URL incorrecta).
4. El endpoint `notifications_count.php` devuelve error.

**Debug:**
```javascript
// En consola del navegador:
fetch(bburl + '/game/ajax/notifications_count.php?_t=' + Date.now())
  .then(r => r.json())
  .then(console.log);
```

### 16.2 Una Notificación Aparece sin Título

**Causa:** La creación usó columnas incorrectas (e.g., `message` en lugar de `title` en `zona_staff_islas.php`).

**Solución:** Corregir el código creador para usar `title` y `body`.

### 16.3 Un Usuario Ve Notificaciones de Otro Usuario

**Causa:** Bug en el filtro `WHERE user_id = ?` — posiblemente falta el filtro o se usa un ID incorrecto.

**Verificación:**
```sql
SELECT * FROM mybb_game_notifications WHERE user_id = <UID_DEL_AFECTADO>;
```

### 16.4 Las Notificaciones de Staff son Visibles para Jugadores

**Causa:** El filtro de `staff_level` no se está aplicando. Verificar:
- `NotificationService::list()` línea 52.
- `NotificationService::unreadCount()` línea 108.
- Que el personaje activo del jugador tenga `staff_level` correcto.

### 16.5 Error "Call to undefined function game_create_notification"

**Causa:** El plugin `game_postcharacter.php` no está instalado o activo en MyBB.

**Solución:** Activar el plugin desde el panel de administración de MyBB. O migrar la llamada a `NotificationService::create()` si el código está dentro del módulo `game/`.

### 16.6 Notificación Creada Pero No Aparece en el Listado

**Causa:** El filtro de personaje activo excluye notificaciones con `character_id` que no coincide.

**Verificación:**
- ¿La notificación tiene `character_id = NULL`? → Se muestra siempre.
- ¿La notificación tiene `character_id = 5`? → Solo se muestra si el personaje activo del usuario es el 5.
- ¿El usuario tiene personaje activo seleccionado? → `game_user_config.active_pj_id`.

### 16.7 Consultas Lentas

**Posible causa:** Crecimiento de la tabla sin limpieza. Considerar:
- Archivar notificaciones viejas (> 90 días).
- Añadir índice por `created_at` si se hacen consultas por fecha.
- La paginación con `LIMIT/OFFSET` se degrada con muchas páginas; considerar cursor-based pagination si el volumen crece.

---

*Fin de la guía — Sistema de Notificaciones v1.0*
