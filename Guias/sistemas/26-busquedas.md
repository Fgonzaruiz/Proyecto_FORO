# 26. Búsquedas de Rol — GUÍA COMPLETA

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 26
> **Propósito:** Documentar exhaustivamente el subsistema de búsquedas de rol: modelo de datos, flujo de envío, moderación por staff, sistema de contacto entre jugadores, filosofía de diseño, y consejos tanto para jugadores como para el equipo de staff.

---

## ÍNDICE

1. [Visión General](#1-visión-general)
2. [Modelo de Datos — Tabla `game_busquedas`](#2-modelo-de-datos)
3. [Flujo de Envío (Submit)](#3-flujo-de-envío)
4. [Flujo de Aprobación (Staff)](#4-flujo-de-aprobación)
5. [Sistema de Contacto entre Jugadores](#5-sistema-de-contacto)
6. [Repositorio y Acceso a Datos](#6-repositorio)
7. [AJAX Endpoints](#7-ajax-endpoints)
8. [Vistas y Templates (Staff)](#8-vistas-y-templates)
9. [JavaScript](#9-javascript)
10. [Filosofía de Diseño](#10-filosofía-de-diseño)
11. [Consejos para Jugadores](#11-consejos-para-jugadores)
12. [Consejos para Staff](#12-consejos-para-staff)
13. [Guía de Troubleshooting](#13-guía-de-troubleshooting)

---

## 1. Visión General

### 1.1 Qué es el Sistema de Búsquedas

Las **Búsquedas de Rol** son un tablón público donde los jugadores publican anuncios buscando compañeros para tramas, misiones o relaciones narrativas. Funciona como un "mercado de conexiones": un jugador explica qué tipo de rol busca, y otros jugadores (o el staff) pueden contactarlo para coordinar.

El flujo completo es:

```
Jugador → Publica búsqueda → Staff revisa y aprueba → Tablón público → Otros jugadores contactan → Se coordina la trama
```

### 1.2 ¿Por Qué un Sistema de Búsquedas?

En un foro de rol con decenas de jugadores, encontrar compañeros para una trama específica puede ser difícil. El tablón de búsquedas resuelve tres problemas:

1. **Visibilidad:** Un jugador con una idea para una trama puede anunciarla a toda la comunidad, no solo a sus contactos directos.
2. **Descubrimiento:** Un jugador que busca rol activo puede navegar el tablón y encontrar tramas que le interesen.
3. **Curaduría:** El staff revisa cada búsqueda antes de publicarla, garantizando calidad y coherencia con el lore del foro.

### 1.3 Arquitectura del Subsistema

```
┌──────────────────────────────────────────────────────────────┐
│                      CLIENTE (Navegador)                      │
│  ┌───────────────────────┐  ┌──────────────────────────────┐  │
│  │ busquedas_submit.js   │  │ zona_staff_busquedas.js      │  │
│  │ (formulario envío)    │  │ (panel staff + modal revisión) │  │
│  └───────────┬───────────┘  └──────────────┬───────────────┘  │
│              │                              │                  │
│              ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              AJAX (game/ajax/*.php)                      │  │
│  │ busquedas_submit.php | busquedas_pending.php             │  │
│  │ busquedas_contact.php | busquedas_resolve_contact.php    │  │
│  └───────────────────────┬──────────────────────────────────┘  │
└──────────────────────────┼─────────────────────────────────────┘
                           │ HTTP POST/GET + JSON
┌──────────────────────────┼─────────────────────────────────────┐
│  ┌───────────────────────▼──────────────────────────────────┐   │
│  │              PHP — CAPA DE APLICACIÓN                     │   │
│  │  BusquedasRepository (listApproved, updateStatus,         │   │
│  │  findOwnerMeta)                                           │   │
│  │  Servicios: NotificationService, DirectMessageService     │   │
│  │  (reutilizados del ecosistema)                            │   │
│  └──────────────────────────────────────────────────────────┘   │
│                           │                                    │
│                           ▼                                    │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │           MySQL (MyBB + tabla game_busquedas)             │   │
│  │  Columnas: id, user_id, character_id, titulo,             │   │
│  │  descripcion, imagen_url, status, staff_nota,             │   │
│  │  created_at, updated_at                                   │   │
│  └──────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────┘
```

---

## 2. Modelo de Datos

### 2.1 Definición SQL Completa

```sql
CREATE TABLE mybb_game_busquedas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    character_id INT NOT NULL,
    titulo      VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    imagen_url  VARCHAR(500) DEFAULT NULL,
    `status`    ENUM('pendiente','aprobada','denegada') DEFAULT 'pendiente',
    staff_nota  TEXT DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Campos — Descripción Detallada

#### `id` — Identificador único
- Autoincremental. Clave primaria del sistema de búsquedas.
- Referenciado en enlaces de notificación (`busqueda_contact:{id}:{requester_pj_id}`) para el sistema de contacto.

#### `user_id` — Dueño de la búsqueda
- FK lógica a `mybb_users.uid` (sin constraint formal).
- Se usa para enviar notificaciones al creador cuando alguien contacta.
- No se expone en el tablón público por privacidad.

#### `character_id` — Personaje que publica
- FK lógica a `mybb_game_personajes.id`.
- Determina qué personaje aparece como autor de la búsqueda en el tablón.
- El sistema de contacto usa este ID para enviar DMs entre personajes.

#### `titulo` — Título del anuncio
- `VARCHAR(255)`. Mínimo 3 caracteres (validado en servidor).
- Debe ser descriptivo y atractivo: "Busco tripulante para navegar el Grand Line", "Misión de rescate en el Nuevo Mundo".
- Se escapa con `htmlspecialchars` en las respuestas AJAX.

#### `descripcion` — Cuerpo del anuncio
- `TEXT`. Mínimo 10 caracteres (validado en servidor).
- Contiene los detalles de lo que el jugador busca: tipo de trama, estilo de rol, disponibilidad, requisitos.
- Se escapa con `htmlspecialchars` en las respuestas AJAX.

#### `imagen_url` — Imagen ilustrativa
- `VARCHAR(500)`. Opcional.
- Puede ser una URL de imagen (escena, personaje, ambientación) que acompañe al anuncio.
- No se valida como URL real (el servidor solo la almacena).

#### `status` — Estado de la búsqueda

| Valor | Significado | Visible en tablón | Acciones permitidas |
|-------|-------------|:-----------------:|---------------------|
| `pendiente` | Esperando revisión del staff | No | Solo el staff puede verla |
| `aprobada` | Publicada y visible | Sí | Cualquier jugador puede contactar |
| `denegada` | Rechazada por staff | No | El creador ve la nota del staff |

**Filosofía del ENUM vs VARCHAR:**
A diferencia de `game_personajes.status` que es VARCHAR por flexibilidad, aquí se usa ENUM porque:
- Los estados son fijos y no se espera que cambien (pendiente → aprobada/denegada).
- ENUM es más eficiente en almacenamiento y velocidad que VARCHAR para un conjunto cerrado de valores.

#### `staff_nota` — Nota del staff
- `TEXT`. Opcional. Solo visible para el creador y el staff.
- Cuando se deniega: contiene el motivo del rechazo (ej: "La descripción es demasiado genérica, especifica qué tipo de trama buscas").
- Cuando se aprueba: puede contener sugerencias opcionales.

#### `created_at` / `updated_at` — Marcas de tiempo
- `created_at`: Fecha de creación de la búsqueda.
- `updated_at`: Se actualiza automáticamente con `ON UPDATE CURRENT_TIMESTAMP` cuando el staff cambia el status.

### 2.3 Filosofía del Modelo de Datos

**¿Por qué separar `user_id` y `character_id` si podríamos obtener el user_id del personaje con un JOIN?**
- Porque en el momento de crear la búsqueda ya tenemos ambos datos. Almacenarlos directamente evita un JOIN en cada consulta de listado.
- Además, `user_id` es necesario para enviar notificaciones (el sistema de notificaciones requiere user_id, no character_id).

**¿Por qué `imagen_url` es VARCHAR(500) y no TEXT?**
- Porque una URL de imagen no debería superar los 500 caracteres. TEXT sería sobredimensionado.
- Si se necesitara almacenar imágenes en el servidor (no solo URLs externas), se cambiaría el diseño.

**¿Por qué no hay índice en `status`?**
- Porque el volumen de datos es bajo (decenas, no miles de búsquedas). Un índice no aportaría beneficio significativo.
- Si el foro creciera a cientos de búsquedas simultáneas, se añadiría `INDEX idx_status (status)`.

### 2.4 Impacto RPG

| Campo | Lo que permite en el juego |
|-------|---------------------------|
| `titulo` | Atraer jugadores con ideas específicas |
| `descripcion` | Definir expectativas, tono, estilo de rol |
| `imagen_url` | Ambientar visualmente la propuesta |
| `character_id` | Asociar la búsqueda a un personaje concreto (su ficha, su historial) |

---

## 3. Flujo de Envío

### 3.1 Diagrama de Secuencia

```
Jugador autenticado
    │
    ▼
Completa formulario: título + descripción + imagen (opcional)
    │
    ▼
POST /ajax/busquedas_submit.php
    │
    ├── ¿Login válido? ─── No → 401
    ├── ¿Personaje activo? ─── No → 400 "Debes tener personaje activo"
    ├── ¿Método POST? ─── No → 405
    ├── ¿CSRF válido? ─── No → 403
    ├── ¿Título ≥ 3 caracteres? ─── No → 400
    └── ¿Descripción ≥ 10 caracteres? ─── No → 400
         │
         ▼
    INSERT INTO game_busquedas (status = 'pendiente')
         │
         ▼
    Notificar a todo el staff (staff_level ≥ 2)
         │
         ▼
    Response {ok: true}
```

### 3.2 Archivo: `ajax/busquedas_submit.php` (61 líneas)

**Precondiciones:**
1. Usuario autenticado (via `GameAjax::requireLogin()`).
2. Personaje activo seleccionado (via `game_user_config.active_pj_id`).
3. CSRF token válido en `$_POST`.

**Validaciones:**
| Campo | Regla | Mensaje de error |
|-------|-------|------------------|
| `titulo` | Mínimo 3 caracteres tras trim | `"El título es demasiado corto (mínimo 3 caracteres)"` |
| `descripcion` | Mínimo 10 caracteres tras trim | `"La descripción es demasiado corta (mínimo 10 caracteres)"` |

**Inserción:**
```php
$db->write_query("
    INSERT INTO {$prefix}game_busquedas (user_id, character_id, titulo, descripcion, imagen_url, status)
    VALUES ({$uid}, {$cid}, '{$titulo_esc}', '{$desc_esc}', '{$img_esc}', 'pendiente')
");
```

**Notificación al staff:**
Tras insertar, se recorre `game_personajes` con `staff_level >= 2` y se crea una notificación por cada miembro del staff (excepto el propio autor):

```php
game_create_notification(
    $staff_uid,
    'busqueda_pendiente',
    "Nueva búsqueda de rol: «{$titulo}» por {$pj_name}",
    '',
    $mybb->settings['bburl'] . '/game/public/zona_staff_busquedas.php'
);
```

### 3.3 Filosofía del Flujo de Envío

**¿Por qué revisión obligatoria en lugar de publicación inmediata?**
- **Control de calidad:** El staff filtra búsquedas que sean demasiado genéricas ("busco rol") o que violen las normas del foro (contenido inapropiado, solicitudes fuera de lugar).
- **Visibilidad:** Si todas las búsquedas se publicaran automáticamente, el tablón se saturaría de anuncios de baja calidad, y las búsquedas interesantes se perderían.
- **Seguridad:** Evita que se publiquen enlaces maliciosos en `imagen_url` o contenido ofensivo en la descripción.

**¿Por qué notificar a TODO el staff y no solo a un grupo?**
- Porque cualquier miembro del staff con nivel ≥ 2 puede revisar y aprobar. No hay un "staff de búsquedas" dedicado.
- La notificación permite una respuesta rápida: si un staffer ve la notificación, puede atenderla inmediatamente.

**¿Por qué el personaje activo y no un selector?**
- Porque simplifica el formulario. El jugador no tiene que elegir "con qué personaje publico" si solo puede tener uno activo.
- Si un jugador tiene múltiples personajes, debe cambiar su personaje activo antes de publicar. Esto fuerza una decisión consciente.

### 3.4 Impacto RPG

| Decisión | Efecto en la comunidad |
|----------|----------------------|
| Revisión obligatoria | El tablón muestra solo búsquedas de calidad |
| Notificación a todo el staff | Respuesta rápida (el primero que ve, revisa) |
| Personaje activo obligatorio | Cada búsqueda está asociada a un personaje específico |

---

## 4. Flujo de Aprobación

### 4.1 Diagrama de Estados

```
         ┌──────────┐
         │ Pendiente │
         └─────┬────┘
               │
      ┌────────┼────────┐
      ▼        ▼        ▼
  Aprobada  Denegada  (sigue pendiente)
  (pública)  (oculta)
```

### 4.2 Acceso Staff

La página staff `zona_staff_busquedas.php` verifica:
1. Usuario autenticado en MyBB.
2. Personaje activo con `staff_level >= 2`.

```php
$pj_q = $db->query("SELECT name, staff_level FROM {$prefix}game_personajes WHERE id = {$cid} AND user_id = {$uid} LIMIT 1");
$pj = $db->fetch_array($pj_q);
if ((int)($pj['staff_level'] ?? 0) < 2) {
    header('Location: ../index.php');
    exit;
}
```

**Filosofía de staff_level ≥ 2:**
- Nivel 1 (moderadores) tiene permisos limitados para evitar decisiones apresuradas.
- Nivel 2+ (administradores) tiene el criterio y la experiencia para evaluar búsquedas.

### 4.3 Listado de Pendientes

`ajax/busquedas_pending.php` — GET, requiere staff_level ≥ 2 en el personaje activo.

```sql
SELECT b.id, b.titulo, b.descripcion, b.imagen_url, b.status, b.staff_nota, b.created_at,
       pj.name as pj_name, pj.avatar as pj_avatar, pj.id as pj_id
FROM {$prefix}game_busquedas b
LEFT JOIN {$prefix}game_personajes pj ON b.character_id = pj.id
WHERE b.status = 'pendiente'
ORDER BY b.created_at ASC
```

**Orden ASC (más antiguas primero):** Las búsquedas que llevan más tiempo esperando se revisan primero. FIFO natural.

**Datos expuestos al staff:**
- `titulo`, `descripcion`, `imagen_url`: Contenido de la búsqueda.
- `pj_name`, `pj_avatar`, `pj_link`: Datos del personaje (con avatar resuelto a URL absoluta).
- `date`: Fecha formateada (`d/m/Y H:i`).

### 4.4 Revisión y Decisión

El staff usa un modal (ver sección 8) para:

1. **Ver** el título, descripción, imagen y personaje autor de la búsqueda.
2. **Escribir** una nota opcional para el jugador.
3. **Decidir:**
   - **Aprobar:** Cambia `status` a `'aprobada'`. La búsqueda aparece en el tablón público.
   - **Denegar:** Cambia `status` a `'denegada'`. La búsqueda queda oculta, el jugador ve la nota.

**Actualización (vía `BusquedasRepository::updateStatus()`):**

```php
$db->write_query("
    UPDATE {$prefix}game_busquedas
    SET status = '{$status_esc}', staff_nota = '{$nota_esc}', updated_at = NOW()
    WHERE id = {$id}
");
```

**Validaciones en el repositorio:**
```php
if ($id <= 0 || !in_array($status, ['aprobada', 'denegada', 'pendiente'], true)) {
    return; // Silently ignore invalid input
}
```

### 4.5 Filosofía del Flujo de Aprobación

**¿Por qué orden ASC en lugar de DESC?**
- Porque las búsquedas más antiguas son las que más tiempo llevan esperando. Revisarlas primero es justo.
- Si se usara DESC, las búsquedas nuevas tendrían prioridad y las viejas podrían quedar olvidadas.

**¿Por qué el staff puede escribir nota incluso al aprobar?**
- Porque a veces una búsqueda es aprobable pero tiene detalles mejorables. El staff puede dejar sugerencias constructivas.
- La nota al aprobar no es obligatoria, pero está disponible.

**¿Por qué no hay edición de búsqueda por parte del staff?**
- Por diseño: si una búsqueda necesita cambios, se deniega con nota explicativa y el jugador la reenvía corregida.
- Evita que el staff "reescriba" la intención del jugador.

### 4.6 Impacto RPG

| Decisión | Efecto en la comunidad |
|----------|----------------------|
| Revisión manual | Todas las búsquedas visibles tienen un mínimo de calidad |
| Nota del staff | Feedback directo al jugador, mejora continua |
| Orden FIFO | Justicia en los tiempos de espera |

---

## 5. Sistema de Contacto

### 5.1 Flujo Completo

```
Jugador A (busca rol) ─── Publica búsqueda ─── Staff aprueba ─── Tablón
                                                                     │
Jugador B (quiere rolear) ─── Ve búsqueda ─── Click "Contactar"
                                                                     │
                                                                     ▼
                                                    POST /ajax/busquedas_contact.php
                                                                     │
                                          ┌──────────────────────────┼──────────────────────────┐
                                          ▼                          ▼                          ▼
                                   Buscar personaje           Validar duplicado          Validar que no sea
                                   activo de B               (notificación activa        el propio autor
                                                              existente)
                                                                     │
                                                                     ▼
                                          Crear NOTIFICACIÓN a Jugador A:
                                          "Personaje B quiere tu trama 'Título'"
                                                                     │
                                                                     ▼
                                          Enviar DM de Personaje B a Personaje A:
                                          "Interés en tu trama: Título"
                                                                     │
                                                                     ▼
                                          Response {ok: true}
```

### 5.2 Archivo: `ajax/busquedas_contact.php` (93 líneas)

**Precondiciones:**
1. Usuario autenticado.
2. Personaje activo (el que contacta).
3. POST con CSRF válido.
4. `busqueda_id` > 0.

**Validaciones:**

| Condición | Código | Mensaje |
|-----------|:------:|---------|
| `busqueda_id` inválido | 400 | `"ID de búsqueda inválido."` |
| Sin personaje activo | 400 | `"Debes tener un personaje activo seleccionado para contactar por una trama."` |
| Personaje activo no pertenece al usuario | 403 | `"Personaje activo no encontrado o no te pertenece."` |
| Búsqueda no existe | 404 | `"La búsqueda seleccionada no existe o ya no está disponible."` |
| Contactarse a sí mismo | 400 | `"No puedes contactarte a ti mismo por tu propia búsqueda."` |
| Contacto duplicado pendiente | 400 | `"Ya has enviado una solicitud de contacto para esta búsqueda y está pendiente de respuesta."` |

**Detección de duplicados:**
```sql
SELECT COUNT(*) as cnt
FROM {$prefix}game_notifications
WHERE user_id = {$creator_user_id}
  AND character_id = {$creator_pj_id}
  AND type = 'busqueda_contact'
  AND link = 'busqueda_contact:{$busqueda_id}:{$requester_pj_id}'
  AND is_read = 0
  AND is_dismissed = 0
```

**Creación de notificación:**
```php
$title = "{$requester_name} quiere tu trama '{$titulo_trama}'";
$body = "¡Hola! Me gustaría coordinar esta trama contigo. ¿Aceptas mi propuesta? (Al aceptar, la búsqueda se marcará como resuelta y se quitará del tablón)";
$link = "busqueda_contact:{$busqueda_id}:{$requester_pj_id}";

NotificationService::create(
    $creator_user_id,
    'busqueda_contact',
    $title,
    $body,
    $link,
    $creator_pj_id
);
```

**Envío de DM (fallback seguro):**
```php
try {
    DirectMessageService::send(
        $requester_pj_id,   // De: Personaje que contacta
        $creator_pj_id,     // Para: Dueño de la búsqueda
        "Interés en tu trama: {$titulo_trama}",
        $body,
        null,
        false
    );
} catch (\Throwable $e) {
    // El contacto por notificación sigue disponible aunque falle el buzón.
}
```

**Filosofía del fallback:** La notificación es el canal principal. El DM es adicional. Si el buzón falla (por cualquier razón), la notificación sigue ahí para que el creador pueda responder.

### 5.3 Resolución de Contacto

`ajax/busquedas_resolve_contact.php` (114 líneas)

**Acciones:**

| `action` | Efecto |
|----------|--------|
| `aceptar` | Elimina la búsqueda de DB + notifica al solicitante |
| `rechazar` | Mantiene la búsqueda activa + notifica al solicitante |

**Validaciones:**
1. `notification_id` > 0 y `action` es `aceptar` o `rechazar`.
2. La notificación existe, pertenece al usuario actual y está asociada al personaje activo.
3. El `link` de la notificación comienza con `busqueda_contact:`.
4. Se parsea el link para extraer `busqueda_id` y `requester_pj_id`.

**Al aceptar:**
```php
// Eliminar la búsqueda (ya no está disponible para otros)
$db->write_query("DELETE FROM {$prefix}game_busquedas WHERE id = {$busqueda_id}");

// Enviar DM de confirmación
$body_notif = "¡Felicidades! {$creator_name} ha aceptado tu propuesta de trama para '{$titulo_trama}'. Ya podéis coordinaros por el buzón.";
DirectMessageService::send($active_pj_id, $requester_pj_id, "Trama aceptada: {$titulo_trama}", $body_notif);

// Marcar notificación como leída
$db->write_query("UPDATE {$prefix}game_notifications SET is_read = 1, is_dismissed = 1 WHERE id = {$notification_id}");
```

**Al rechazar:**
```php
$body_notif = "{$creator_name} ha decidido seguir buscando compañeros para la trama '{$titulo_trama}'.";
DirectMessageService::send($active_pj_id, $requester_pj_id, "Trama declinada: {$titulo_trama}", $body_notif);

// Marcar notificación como leída
$db->write_query("UPDATE {$prefix}game_notifications SET is_read = 1, is_dismissed = 1 WHERE id = {$notification_id}");
```

**Filosofía de la eliminación al aceptar:**
- Cuando una búsqueda se resuelve (alguien contactó y fue aceptado), se elimina de la DB. No se marca como "resuelta" ni se oculta.
- Esto evita que otros jugadores intenten contactar por una trama que ya tiene dueño.
- Si el creador quiere buscar más compañeros, debe publicar una nueva búsqueda.

**Filosofía del DM como canal principal de coordinación:**
- Una vez que el contacto es aceptado, la coordinación se traslada al buzón del foro (DMs entre personajes).
- El sistema de búsquedas deja de intervenir. Es solo un "emparejador" inicial.

### 5.4 Impacto RPG

| Decisión | Efecto en la comunidad |
|----------|----------------------|
| Notificación + DM dual | El creador recibe el contacto aunque tenga notificaciones silenciadas |
| Eliminación al aceptar | Evita contactos duplicados sobre la misma trama |
| DM como canal post-contacto | La conversación continúa en privado, fuera del tablón |

---

## 6. Repositorio

### 6.1 BusquedasRepository

Archivo: `game/src/Infrastructure/Persistence/BusquedasRepository.php` (94 líneas)

**Métodos:**

| Método | Descripción | Parámetros | Retorno |
|--------|-------------|------------|---------|
| `listApproved()` | Lista búsquedas aprobadas para el tablón público | `$limit` (default 12, max 50) | `list<array>` |
| `updateStatus()` | Cambia status y nota de una búsqueda | `$id`, `$status`, `$staffNota` | `void` |
| `findOwnerMeta()` | Obtiene datos del dueño de una búsqueda | `$id` | `?array` |

#### `listApproved()` — Consulta principal del tablón

```sql
SELECT b.id, b.titulo, b.descripcion, b.imagen_url, b.created_at,
       pj.name as pj_name, pj.avatar as pj_avatar, pj.id as pj_id
FROM {$prefix}game_busquedas b
LEFT JOIN {$prefix}game_personajes pj ON b.character_id = pj.id
WHERE b.status = 'aprobada'
ORDER BY b.updated_at DESC
LIMIT {$limit}
```

**Procesamiento de avatar:**
```php
$avatar = $row['pj_avatar'];
if ($avatar && strpos($avatar, 'http') !== 0) {
    $avatar = rtrim($bburl, '/') . '/' . ltrim($avatar, '/');
}
if (!$avatar) {
    $avatar = $bburl . '/images/default_avatar.png';
}
```

**Transformación del resultado:**
```php
$list[] = [
    'id'          => (int)$row['id'],
    'titulo'      => htmlspecialchars($row['titulo']),
    'descripcion' => htmlspecialchars($row['descripcion']),
    'imagen_url'  => htmlspecialchars($row['imagen_url'] ?? ''),
    'pj_name'     => htmlspecialchars($row['pj_name'] ?? 'Desconocido'),
    'pj_avatar'   => $avatar,
    'pj_link'     => $bburl . '/game/public/personaje.php?id=' . (int)$row['pj_id'],
    'pj_id'       => (int)$row['pj_id'],
    'date'        => date('d/m/Y', strtotime($row['created_at'])),
];
```

#### `updateStatus()` — Cambio de estado

```php
public function updateStatus(int $id, string $status, string $staffNota = ''): void
{
    if ($id <= 0 || !in_array($status, ['aprobada', 'denegada', 'pendiente'], true)) {
        return;
    }
    // UPDATE ...
}
```

**Filosofía de validación silenciosa:** Si los parámetros son inválidos, el método retorna sin hacer nada en lugar de lanzar excepción. Esto es intencional: quien llama al método ya debería haber validado. Si no lo hizo, el error es silencioso pero no rompe la página.

#### `findOwnerMeta()` — Metadatos del dueño

```php
$q = $db->query("SELECT user_id, character_id, titulo FROM {$prefix}game_busquedas WHERE id = {$id} LIMIT 1");
```

Usado por el sistema de contacto para obtener:
- `user_id`: Para enviar notificaciones.
- `character_id`: Para verificar que el contactante no es el propio autor.
- `titulo`: Para personalizar los mensajes.

### 6.2 Filosofía del Repositorio

**¿Por qué `listApproved()` tiene un límite máximo de 50?**
- Porque el tablón no debe mostrar cientos de búsquedas simultáneamente. 12 por defecto, máximo 50.
- Si hay más de 50 búsquedas activas, significa que muchas están desatendidas (nadie contacta). Es mejor que el foro tenga menos búsquedas pero más activas.

**¿Por qué no hay métodos de borrado en el repositorio?**
- Porque el borrado ocurre en `busquedas_resolve_contact.php` con una consulta directa (`DELETE FROM ... WHERE id = {$busqueda_id}`).
- No se encapsuló en el repositorio porque la funcionalidad inicial no lo requería. Es deuda técnica aceptable para un sistema pequeño.
- Si se añadiera un panel de "mis búsquedas" para jugadores, se agregaría un método `delete()` al repositorio.

---

## 7. AJAX Endpoints

### 7.1 `busquedas_submit.php`
| Propiedad | Valor |
|-----------|-------|
| Método | POST |
| Auth | Login + Personaje activo |
| CSRF | Obligatorio |
| Archivo | `game/ajax/busquedas_submit.php` |
| Parámetros | `titulo`, `descripcion`, `imagen_url` |
| Respuesta éxito | `{ok: true}` |
| Respuesta error | `{ok: false, error: "mensaje"}` |
| Efectos | INSERT en `game_busquedas` + notificaciones al staff |

### 7.2 `busquedas_pending.php`
| Propiedad | Valor |
|-----------|-------|
| Método | GET (implícito) |
| Auth | Staff level ≥ 2 |
| Archivo | `game/ajax/busquedas_pending.php` |
| Respuesta éxito | `{ok: true, data: [lista de búsquedas pendientes]}` |
| Respuesta error | `{ok: false, error: "mensaje"}` |

### 7.3 `busquedas_contact.php`
| Propiedad | Valor |
|-----------|-------|
| Método | POST |
| Auth | Login + Personaje activo |
| CSRF | Obligatorio |
| Archivo | `game/ajax/busquedas_contact.php` |
| Parámetros | `busqueda_id` |
| Respuesta éxito | `{ok: true}` |
| Respuesta error | `{ok: false, error: "mensaje"}` |
| Efectos | Notificación + DM al dueño de la búsqueda |

### 7.4 `busquedas_resolve_contact.php`
| Propiedad | Valor |
|-----------|-------|
| Método | POST |
| Auth | Login + Personaje activo |
| CSRF | Obligatorio |
| Archivo | `game/ajax/busquedas_resolve_contact.php` |
| Parámetros | `notification_id`, `action` (`aceptar` \| `rechazar`) |
| Respuesta éxito | `{ok: true}` |
| Respuesta error | `{ok: false, error: "mensaje"}` |
| Efectos | DELETE de búsqueda (si acepta) + DM al solicitante |

### 7.5 Filosofía de los Endpoints

**¿Por qué `busquedas_pending.php` es GET sin parámetros?**
- Porque solo hay un recurso: "todas las búsquedas pendientes". No hay filtros ni paginación necesaria para el volumen actual.
- GET es apropiado porque es una operación de lectura. No modifica estado.

**¿Por qué `busquedas_contact.php` y `busquedas_resolve_contact.php` son POST separados en lugar de un solo endpoint con verbos?**
- Porque cada uno tiene responsabilidades distintas y validaciones distintas.
- Unificarlos habría creado un endpoint con demasiadas ramas condicionales.

---

## 8. Vistas y Templates

### 8.1 Página Staff: `zona_staff_busquedas.php`

Archivo: `game/public/zona_staff_busquedas.php` (98 líneas)

**Estructura:**

```
zona_staff_busquedas.php
├── Verificación de login y staff_level ≥ 2
├── Header con título "Búsquedas de Rol Pendientes"
├── Back link → zona_staff.php
├── <div id="busquedas-staff-list"> (cargado por JS)
└── Modal de revisión (#busqueda-review-modal)
    ├── Título (#modal-review-titulo)
    ├── Imagen (#modal-review-img) — oculta si no hay imagen
    ├── Autor: avatar + nombre + fecha
    ├── Descripción (#modal-review-desc)
    ├── Campo: Nota para el jugador (#modal-review-nota)
    ├── Botón: Aprobar y publicar
    └── Botón: Denegar
```

**Modal de revisión:**

```html
<div id="busqueda-review-modal" class="rpg-modal-overlay">
    <div class="rpg-modal-panel">
        <div class="rpg-modal-header">
            <h3 id="modal-review-titulo" class="rpg-modal-title"></h3>
            <button onclick="closeBusquedaReview()" class="rpg-modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="rpg-modal-body">
            <img id="modal-review-img" src="" class="rpg-modal-img rpg-is-hidden" alt="" />
            <div class="rpg-modal-author">
                <img id="modal-review-avatar" src="" class="rpg-modal-avatar" alt="" />
                <div>
                    <div id="modal-review-pj" class="rpg-modal-pj"></div>
                    <div id="modal-review-date" class="rpg-modal-date"></div>
                </div>
            </div>
            <div id="modal-review-desc" class="rpg-modal-desc"></div>

            <input type="hidden" id="modal-review-id" value="" />
            <label class="rpg-modal-label">Nota para el jugador (opcional):</label>
            <textarea id="modal-review-nota" rows="3" class="rpg-staff-textarea" placeholder="Añade una nota que recibirá el jugador..."></textarea>

            <div class="rpg-modal-actions">
                <button type="button" onclick="accionBusqueda('aprobar')" class="rpg-action-btn rpg-btn-primary">
                    <i class="fas fa-check"></i> Aprobar y publicar
                </button>
                <button type="button" onclick="accionBusqueda('denegar')" class="rpg-system-tab-btn rpg-staff-btn-danger">
                    <i class="fas fa-times"></i> Denegar
                </button>
            </div>
        </div>
    </div>
</div>
```

**Config JavaScript:**
```php
<script>
window.ZONA_STAFF_BUSQUEDAS_CONFIG = { bburl: '<?= $b_url ?>' };
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/zona_staff_busquedas.js?v=1"></script>
```

### 8.2 Filosofía de las Vistas

**¿Por qué un modal en lugar de una página separada para cada búsqueda?**
- Porque la revisión es rápida: el staff lee título, descripción y decide en segundos.
- El modal permite revisar sin perder el contexto de la lista completa.
- Si se necesitara una revisión más detallada, se podría abrir la búsqueda en una página aparte, pero para el 90% de los casos el modal es suficiente.

**¿Por qué la imagen se oculta si no hay?**
- Porque `imagen_url` es opcional. Mostrar un placeholder roto o un `<img>` vacío es mala UX.
- La clase `rpg-is-hidden` se maneja por JS: si `imagen_url` está vacío, el elemento no se muestra.

**¿Por qué el título del modal es el título de la búsqueda?**
- Para que el staff identifique rápidamente de qué búsqueda se trata, incluso si llegó al modal desde otra interacción.

---

## 9. JavaScript

### 9.1 Archivo: `zona_staff_busquedas.js`

El JS asociado al panel staff realiza:

1. **Carga inicial:** `GET /ajax/busquedas_pending.php` → renderiza lista de tarjetas.
2. **Abrir modal:** Al clickear una tarjeta, llena el modal con los datos de la búsqueda.
3. **Acción (aprobar/denegar):** POST a un endpoint (probablemente `BusquedasRepository::updateStatus()` vía un AJAX dedicado) con `id`, `action` y `staff_nota`.
4. **Feedback visual:** Elimina la tarjeta de la lista al aprobar/denegar, muestra toast de confirmación.

**Config esperada:**
```javascript
window.ZONA_STAFF_BUSQUEDAS_CONFIG = {
    bburl: 'https://foro.ejemplo.com'
};
```

**Funciones esperadas:**
| Función | Propósito |
|---------|-----------|
| `cargarPendientes()` | GET a `busquedas_pending.php`, renderiza lista |
| `abrirReview(id)` | Busca búsqueda por ID, llena modal |
| `accionBusqueda(tipo)` | POST aprobar/denegar (tipo = 'aprobar' \| 'denegar') |
| `closeBusquedaReview()` | Cierra modal |

### 9.2 Filosofía del JS

**¿Por qué un archivo JS separado y no inline?**
- Porque sigue la regla F-GATE-02 del foro: 0 scripts inline en templates/vistas.
- El JS se carga al final, no bloquea el renderizado del HTML.
- Separa lógica de presentación: el PHP genera HTML, el JS maneja interacción.

**¿Por qué la config se pasa como variable global?**
- Porque el JS necesita conocer la URL base del foro (`bburl`) para construir URLs absolutas.
- Usar una variable global (`ZONA_STAFF_BUSQUEDAS_CONFIG`) es el patrón establecido en el ecosistema (ver `PERSONAJE_PAGE_CONFIG`, `CREAR_PERSONAJE_CONFIG`).

---

## 10. Filosofía de Diseño

### 10.1 Principios Rectores

1. **Las búsquedas son un puente, no un fin.** El sistema está diseñado para conectar jugadores, no para reemplazar la coordinación directa. Una vez que dos jugadores se conectan, el sistema desaparece.

2. **Calidad sobre cantidad.** Cada búsqueda pasa por revisión del staff. Es mejor tener 3 búsquedas interesantes en el tablón que 30 genéricas.

3. **El creador tiene el control.** Decide quién contacta (su búsqueda está pública) pero también decide quién acepta. Nadie puede unirse a su trama sin su consentimiento.

4. **Simplicidad deliberada.** El sistema tiene 4 endpoints AJAX y 1 tabla. No hay subastas, ni puntuaciones, ni gamification. Es un tablón de anuncios, no un mercado.

### 10.2 Decisiones Clave y su Porqué

| Decisión | Alternativa descartada | Por qué se eligió así |
|----------|----------------------|----------------------|
| Revisión obligatoria | Publicación inmediata | Calidad, seguridad, curaduría |
| Eliminación al aceptar | Status "resuelta" | Evita contactos duplicados, DB limpia |
| Contacto vía notificación + DM | Solo DM o solo notificación | Redundancia segura: si un canal falla, el otro funciona |
| Una sola búsqueda por personaje activo | Múltiples búsquedas simultáneas | Un personaje, una trama a la vez |
| Staff level ≥ 2 para revisar | Staff level ≥ 1 | Los moderadores (nivel 1) no tienen permiso para decisiones de contenido |

### 10.3 Comparación con Sistemas Similares

| Aspecto | Búsquedas (este sistema) | Personajes | Admin Requests |
|---------|-------------------------|------------|----------------|
| Tabla | `game_busquedas` | `game_personajes` | `game_admin_requests` |
| Estado inicial | `pendiente` | `pendiente` | `pending` |
| Revisión | Staff level ≥ 2 | Staff level ≥ 1 | Staff level ≥ 2 |
| Resolución | Aprobada / Denegada | Aprobada / Rechazada / Muerto | Aprobada / Rechazada |
| Post-resolución | Eliminación si se contacta | La ficha permanece | La solicitud permanece como histórico |

### 10.4 Lo Que el Sistema NO Hace (Intencionalmente)

- **No tiene gamification:** No hay reputación, puntos por búsquedas aceptadas, ni rankings de "mejor buscador de rol".
- **No tiene chat interno:** La coordinación post-contacto se hace por DM del foro.
- **No tiene categorías:** Todas las búsquedas están en un solo tablón, ordenadas por fecha de actualización.
- **No tiene búsqueda/filtro:** El tablón muestra todas las búsquedas aprobadas. Si el volumen crece, se añadiría filtrado.

**Filosofía de estas omisiones:**
- El sistema es minimalista a propósito. Cada funcionalidad añadida es una superficie de bugs y una decisión de diseño más.
- La premisa es: "conecta jugadores, luego aléjate". No necesitas un Tinder para rol; necesitas un tablón de corcho digital.

---

## 11. Consejos para Jugadores

### 11.1 Cómo Publicar una Búsqueda Efectiva

**Escribe un título que enganche:**
- ✗ "Busco rol" (demasiado genérico)
- ✓ "Marine novato busca oficial para patrullar el Grand Line"
- ✓ "Médico tripulante busca capitán para aventuras en el Nuevo Mundo"

**Sé específico en la descripción:**
- Menciona el tipo de trama que buscas: ¿combate? ¿exploración? ¿intriga política?
- Indica tu disponibilidad: ¿puedes postear diario? ¿semanal?
- Describe el tono: ¿rol serio, casual, cómico?
- Si prefieres rolear con personajes de cierto rango o facción, dilo.

**Incluye una imagen de ambientación:**
- Una imagen del escenario que imaginas para la trama.
- O una imagen de tu personaje en acción.
- Las imágenes atraen la mirada y dan personalidad al anuncio.

### 11.2 Cómo Contactar a Otro Jugador

**Antes de contactar:**
1. Lee la descripción completa. ¿Cumples con lo que pide?
2. Revisa la ficha del personaje (enlace en el tablón). ¿Tu personaje encaja narrativamente?
3. Prepárate para explicar por qué quieres unirte.

**Después de contactar:**
- Si el creador acepta, responde al DM rápidamente para coordinar detalles.
- Si el creador rechaza, no insistas. Busca otra búsqueda o publica la tuya.

### 11.3 Buenas Prácticas

- **No publiques la misma búsqueda múltiples veces.** Si no recibes contactos, revisa tu descripción: ¿es demasiado específica? ¿demasiado genérica? Ajusta y vuelve a publicar.
- **Mantén tu búsqueda actualizada.** Si encuentras trama por otro medio, puedes solicitar al staff que la elimine.
- **Responde a los contactos.** Si alguien se toma el tiempo de contactarte, responde aunque sea para rechazar. La comunidad se construye con respeto mutuo.

### 11.4 Errores Comunes

| Error | Por qué evitar |
|-------|----------------|
| Título de 3 caracteres | Pasa la validación pero no dice nada |
| Descripción minimalista | "Busco rol xd" no atrae a nadie |
| Contactar sin leer la descripción | El creador notará que no leíste y rechazará |
| Publicar con personaje inactivo | No puedes enviar la búsqueda si no tienes personaje activo |

---

## 12. Consejos para Staff

### 12.1 Cómo Revisar Búsquedas

**Criterios de aprobación:**
- ¿El título es descriptivo y atractivo?
- ¿La descripción especifica qué tipo de trama busca?
- ¿La imagen (si la hay) es apropiada y funciona?
- ¿El personaje que publica está activo y es coherente con la búsqueda?

**Criterios de denegación:**
- Contenido ofensivo, inapropiado o fuera del lore.
- Descripción demasiado genérica ("busco rol", "lo que sea").
- Título engañoso (promete algo que la descripción no cumple).
- Imagen rota o con contenido inapropiado.

**Cómo escribir notas útiles:**
- Al denegar: explica POR QUÉ se deniega. "La descripción es muy corta. Especifica qué tipo de trama buscas y con qué estilo de rol te sientes cómodo."
- Al aprobar (opcional): "¡Buena búsqueda! Considera añadir tu disponibilidad horaria para que los interesados sepan cuándo puedes postear."

### 12.2 Gestión del Tablón

- **Rapidez:** Intenta revisar las búsquedas en menos de 24 horas. Una búsqueda pendiente es un jugador esperando.
- **Orden:** Revisa por orden de llegada (FIFO). Las búsquedas más antiguas primero.
- **Comunicación:** Si ves una búsqueda que podría mejorarse pero no es para denegar, aprueba con una nota constructiva.

### 12.3 Resolución de Conflictos

- **Spam:** Si un jugador publica la misma búsqueda repetidamente, deniega con nota explicativa. Si persiste, escalar a superadmin.
- **Búsquedas inactivas:** Si una búsqueda aprobada lleva semanas sin contacto y el jugador ya no está activo, puedes contactarlo para preguntar si desea mantenerla.
- **Contactos no respondidos:** Si un jugador se queja de que contactó a alguien y no recibió respuesta, puedes enviar un recordatorio al creador de la búsqueda.

---

## 13. Guía de Troubleshooting

### 13.1 Problemas Comunes

| Problema | Causa probable | Solución |
|----------|---------------|----------|
| "Debes tener un personaje activo" | `active_pj_id` es NULL o 0 en `game_user_config` | Seleccionar personaje activo en Mis Personajes |
| "El título es demasiado corto" | Título < 3 caracteres | Escribir al menos 3 caracteres |
| "Sin permisos" al ver pendientes | Staff level del personaje activo < 2 | Usar personaje con staff_level ≥ 2 |
| "Ya has enviado una solicitud" | Contacto duplicado no resuelto | Esperar a que el creador responda |
| "Enlace de notificación dañado" | Link mal formado en DB | Revisar integridad de la notificación |
| "Esta notificación no corresponde a un contacto" | Tipo de notificación incorrecto | Solo notificaciones `busqueda_contact` son válidas |

### 13.2 Debugging

**Para staff: verificar que una búsqueda existe:**
```sql
SELECT * FROM mybb_game_busquedas WHERE id = {ID};
```

**Para staff: listar búsquedas por usuario:**
```sql
SELECT b.*, pj.name as pj_name
FROM mybb_game_busquedas b
LEFT JOIN mybb_game_personajes pj ON b.character_id = pj.id
WHERE b.user_id = {USER_ID};
```

**Para staff: notificaciones de contacto pendientes:**
```sql
SELECT * FROM mybb_game_notifications
WHERE type = 'busqueda_contact' AND is_read = 0 AND is_dismissed = 0;
```

### 13.3 Casos Borde

**¿Qué pasa si el personaje que publicó la búsqueda se borra?**
- La búsqueda permanece en la tabla con `character_id` huérfano.
- En el tablón, `pj_name` mostrará "Desconocido".
- El sistema de contacto fallará al buscar el personaje (la búsqueda devuelve 404).

**¿Qué pasa si el usuario que contactó borra su personaje antes de recibir respuesta?**
- La notificación del creador tendrá un `requester_pj_id` inválido.
- Al intentar resolver, `busquedas_resolve_contact.php` fallará con "El personaje que solicitó la trama ya no existe".

**¿Qué pasa si se intenta aprobar/denegar una búsqueda que ya fue eliminada (por contacto aceptado)?**
- `updateStatus()` ejecutará un UPDATE que no afecta ninguna fila (0 rows affected).
- No hay error, pero tampoco hay efecto. El staff debería verificar que la búsqueda aún existe.

---

## 14. Migración y Actualizaciones

### 14.1 Script de Migración

Archivo: `game/sql/migrate_busquedas.php` (34 líneas)

```php
if (!$db->table_exists('game_busquedas')) {
    $sql = "CREATE TABLE {$prefix}game_busquedas (...);";
    $db->write_query($sql);
    echo "<p class='rpg-admin-ok'>[OK] Tabla game_busquedas creada correctamente.</p>";
} else {
    echo "<p class='rpg-admin-warn'>[INFO] La tabla game_busquedas ya existe.</p>";
}
```

**Filosofía de la migración:**
- **Idempotente:** Si la tabla ya existe, no falla, solo informa.
- **Sin versioning:** A diferencia de sistemas con migraciones numeradas, este script es único. Si se necesitan cambios futuros, se crea un nuevo script.

### 14.2 Posibles Mejoras Futuras

| Mejora | Complejidad | Impacto |
|--------|:-----------:|:-------:|
| Categorías de búsqueda (combate, exploración, social) | Baja | Alta |
| Edición de búsqueda por el jugador | Media | Alta |
| Historial de búsquedas del jugador | Baja | Media |
| Notificación por email al recibir contacto | Media | Baja |
| Límite de búsquedas activas por jugador | Baja | Media |
| Buscador/filtro en el tablón público | Media | Alta |
| Botón "Reportar búsqueda" | Baja | Media |

**Filosofía de las mejoras futuras:**
Cada mejora debe evaluarse contra el principio de simplicidad. Antes de añadir una funcionalidad, preguntar: "¿Esto ayuda a conectar jugadores?" Si la respuesta es "no directamente", no se añade.

---

## 15. Referencias

| Recurso | Ruta |
|---------|------|
| Migración SQL | `game/sql/migrate_busquedas.php` |
| Schema fragment | `game/sql/install_schema_fragments.php` (línea 306) |
| Repositorio | `game/src/Infrastructure/Persistence/BusquedasRepository.php` |
| Submit AJAX | `game/ajax/busquedas_submit.php` |
| Pending list AJAX | `game/ajax/busquedas_pending.php` |
| Contact AJAX | `game/ajax/busquedas_contact.php` |
| Resolve contact AJAX | `game/ajax/busquedas_resolve_contact.php` |
| Staff page | `game/public/zona_staff_busquedas.php` |
| Staff JS | `jscripts/game/zona_staff_busquedas.js` |
| Sección maestro | `Guias/MAESTRO_SISTEMAS_RPG.md` (línea 634) |
