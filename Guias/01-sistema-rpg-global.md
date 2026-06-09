# Sistema RPG del Foro — Visión global

> Documento maestro para IAs. Explica **cómo encaja todo** el RPG en `back/forum/game/` (junio 2026).  
> Prefijo MySQL: `mybb_` + `game_*`.

---

## 1. Idea general

Este foro es **MyBB con un módulo de juego embebido**. MyBB sigue haciendo lo que siempre hace: cuentas, sesiones, permisos, hilos y posts. Encima, el módulo `game/` añade personajes, stats, cartas jugables en posts, oráculos, navegación, economía y herramientas staff.

**Principio de diseño (D001):** toda mecánica vive en MySQL local. No hay API externa que calcule combate o progresión. PHP valida, persiste y devuelve JSON; el plugin MyBB engancha el momento en que alguien publica un post.

Para una IA que vaya a implementar algo nuevo: primero entender **qué tabla toca**, **qué hook o AJAX la escribe**, y **qué validación hace el servidor** (nunca confiar en el cliente para PP, berries o rangos).

---

## 2. Arquitectura en capas

```
┌─────────────────────────────────────────────────────────┐
│  Navegador — plantillas MyBB + jscripts/game/*.js       │
└──────────────────────────┬──────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────┐
│  MyBB — global.php, posts, permisos, $db                │
│  Plugin game_postcharacter.php (hooks)                  │
└──────────────────────────┬──────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────┐
│  game/bootstrap.php — helpers + autoload                │
│  game/public/* — páginas HTML                           │
│  game/ajax/* — JSON                                     │
│  game/src/Application — servicios (PP, guardado PJ…)     │
└──────────────────────────┬──────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────┐
│  MySQL mybb_game_* + tablas MyBB (posts, users, forums) │
└─────────────────────────────────────────────────────────┘
```

### 2.1 Bootstrap

Cada script en `game/public/` o `game/ajax/` incluye `game/bootstrap.php`, que carga MyBB y luego:

- `src/autoload.php` — clases `Game\*`
- Helpers: stats, oficios, disciplinas, inventario, oráculos, navegación

### 2.2 Plugin — el pegamento con los posts

`inc/plugins/game_postcharacter.php` es crítico: sin él, publicar un post no guardaría cartas ni PV/PE. Hooks principales:

| Hook | Qué hace |
|------|----------|
| `datahandler_post_insert_post_end` | Al publicar: vincula PJ, cartas, oráculos, modificadores |
| `datahandler_post_insert_thread_end` | Crea meta de calendario on-rol del hilo |
| `class_moderation_delete_post/thread` | Limpia datos RPG al borrar |
| `global_start` | Fecha on-rol en plantillas; vars del deck |
| `editpost_*` | Bloquea editar posts que ya tienen tiradas |
| `parse_message` | BBCode `[spoiler]` para acciones ocultas |

Funciones compartidas usadas por AJAX y plugin: `game_evaluate_dice_roll`, `game_create_notification`.

---

## 3. El personaje como centro del sistema

### 3.1 Tabla `game_personajes`

Una fila = una ficha. Mezcla columnas SQL y JSON:

| Almacenamiento | Contenido |
|----------------|-----------|
| Columnas | `name`, `race`, `occupation`, `faction`, `avatar`, `berries`, `status`, `postnum`, `threadnum`, flags staff/npc |
| `stats_json` | 7 stats en rangos 1–6 (v7): fue, res, agi, des, int, inst, esp |
| `data_json` | PP, nivel, rank global, linaje, bio extendida, cooldowns de grados |
| `cronologia_json` | Diario IC, relaciones, grupos |

### 3.2 Usuario ↔ personajes

`game_user_config` (1 fila por usuario MyBB):

- `max_slots` / `slots_used` — cuántos PJs puede tener
- `active_pj_id` — **el PJ con el que escribe en el foro** (crucial para el plugin)

Sin `active_pj_id`, el post es «normal» sin mecánicas.

### 3.3 Ciclo de vida

1. **Crear** — `crear_personaje.php` + `save_personaje.php` → `status=pendiente`
2. **Aprobar** — staff → `aprobada`, `approved=1`
3. **Activar** — `set_active_pj.php` pone `active_pj_id`
4. **Jugar** — posts con deck; contadores `postnum`/`threadnum` suben vía plugin
5. **Progresar** — PP en ficha, oficios/disciplinas, cartas asignadas por staff

Al crear, el sistema asigna automáticamente un **oficio** (según occupation del wizard) y una **disciplina** inicial en sus tablas puente.

### 3.4 Qué puede editar el jugador vs el servidor

El cliente envía bio, stats iniciales en creación, avatar. **No** puede fijar: `pp`, `nivel`, `rank`, `approved`. `CharacterSaveService` filtra claves prohibidas. Las compras de stats pasan por `purchase_attribute.php` con validación de PP en servidor.

---

## 4. Publicar un post con mecánicas (flujo detallado)

Este es el flujo más importante del RPG:

```
1. Usuario logueado con active_pj_id = N
2. Escribe post en un hilo; el editor adjunta:
   - IDs de cartas del mazo
   - Opcional: cambios PV/PE, mods de stat
   - Opcional: oráculos a invocar
   - Opcional: acciones ocultas (índice + cartas)
3. MyBB inserta fila en mybb_posts
4. Hook game_postcharacter_save_post:
   a. INSERT/UPDATE game_post_characters (post_id → character_id)
   b. Procesa cada carta → game_post_cards (+ tirada si hay dice)
   c. Procesa oráculos → game_post_oracles
   d. Actualiza game_thread_pj_state (PV/PE del PJ en ese hilo)
   e. Si hay viaje naval → game_navigation_voyages
   f. Decrementa consumibles en mazo
5. Otros usuarios leen el hilo
6. JS llama cards_for_post.php → renderiza cartas, mods, oráculos
```

**Estado por hilo:** `game_thread_pj_state` guarda PV/PE actuales y modificadores de stat acumulados. Cada post puede sumar `pv_change`/`pe_change` en `game_post_characters`.

**Equipamiento:** armas, barcos y compañeros deben estar en `game_character_inventory` para poder jugarse (salvo consumibles).

---

## 5. Mapa de tablas por dominio

### 5.1 Personajes y usuarios
`game_personajes` · `game_user_config` · `game_personajes_revisiones` · `game_npc_profiles` · `game_npc_assignments` · `game_tripulaciones`

### 5.2 Posts y combate
`game_post_characters` · `game_post_cards` · `game_post_oracles` · `game_thread_pj_state` · `game_thread_meta`

### 5.3 Cartas
`game_cards` · `game_character_cards` · `game_character_inventory` · `game_card_requests`

Peticiones de carta permitidas: `delete`, `create`, `add_existing`. **Sin upgrade.**

### 5.4 Progresión y competencias
`game_oficios` · `game_character_oficios` · `game_disciplinas` · `game_character_disciplinas`

### 5.5 Akuma y peticiones admin
`game_akuma_no_mi` · `game_admin_requests`

### 5.6 Mundo y navegación
`game_forum_islands` (metadatos por foro=isla) · `game_navigation_routes` · `game_navigation_voyages` · `game_navigation_events`

### 5.7 Comunidad
`game_notifications` · `game_direct_messages` · `game_busquedas` · `game_announcements`

### 5.8 Legacy / catálogos
`game_estilos` · `game_tecnicas` · `game_objetos`

### 5.9 Control
`game_schema_migrations`

Esquema completo en `game/sql/install_schema_fragments.php`.

---

## 6. Módulos funcionales — cómo funcionan

### 6.1 Stats y PP (v7)

Siete stats con rangos 1–6 (D a SS). El **rango global** del PJ es la suma de rangos entrenados → D/C/B/A/S/SS → `nivel` 1–6. Los **PP** compran subidas de stat; el coste sube con el rango global del personaje.

Detalle completo: `03-stats-nivel-pp-oficios-disciplinas.md`.

### 6.2 Cartas

Catálogo staff → mazo por PJ → jugadas por post. Tipos: técnica, equipo, akuma_no_mi, haki, npc_menor, barco.

Detalle: `02-sistema-cartas-haki-akuma.md`.

### 6.3 Oráculos

Catálogo `game_oracles` con tablas de resultados (`results_json`). Al publicar post, se tiran y guardan en `game_post_oracles`. Pueden encadenarse (auto-invoke). Usados también en eventos de navegación.

### 6.4 Navegación

Cada foro MyBB puede tener ficha de isla (`game_forum_islands`: coordenadas, peligro, requisitos). Rutas conectan islas. Un viaje se inicia en un post con barco equipado; genera eventos y puede requerir revisión staff.

### 6.5 Economía

`berries` en `game_personajes`. Tienda compra cartas comerciables. Subida de grados de oficio/disciplina tiene precios en berries (reglas en `grado_helpers.php`).

### 6.6 Linaje

`data/linaje_catalog.json` — razas, bonos a stats, pasivas, opciones raciales con coste PP. Se guarda en `data_json.linaje`. Bonos raciales se aplican al calcular stats efectivos, no se duplican en `stats_json`.

### 6.7 Calendario on-rol

`game_thread_meta` por hilo (día, estación, año). Fecha global en `game/data/calendar.json`. Se muestra en index y plantillas vía `game_global_rol_date()`.

### 6.8 Staff

Nivel en `game_personajes.staff_level` del PJ activo:

| Nivel | Capacidades típicas |
|-------|---------------------|
| 0 | Jugador |
| 2 | Moderar peticiones, asignar oficios/disciplinas |
| 3 | Catálogo cartas, crear cartas, aprobar creación de cartas |

### 6.9 Notificaciones y mensajería

`game_notifications` — alertas in-app (peticiones resueltas, admin, etc.).  
`game_direct_messages` — MD entre personajes (sustituto de PM temático).

### 6.10 Búsquedas de rol

`game_busquedas` — tablón IC: jugador publica búsqueda, staff aprueba.

---

## 7. Páginas públicas principales

| Ruta | Función |
|------|---------|
| `game/public/index.php` | Hub |
| `mis_personajes.php` | Lista y activar PJ |
| `crear_personaje.php` | Wizard creación |
| `personaje.php` | Ficha completa (tabs: gestión, cronología, inventario…) |
| `biblioteca_personajes.php` | Directorio público |
| `tienda.php` | Comprar cartas |
| `cartas_staff.php` | Admin cartas |
| `oracles_staff.php` | Admin oráculos |
| `zona_staff*.php` | Panel staff |
| `akuma_no_mi.php` / `peticion_akuma*.php` | Frutas |
| `manual.php` | Reglas jugador |

---

## 8. Servicios PHP importantes

| Clase | Responsabilidad |
|-------|-----------------|
| `StatScale` | Fórmulas stats v7, PV/PE, costes PP, requisitos Haki |
| `CharacterProgression` | Gastar PP, recalcular rank/nivel |
| `CharacterSaveService` | Crear/editar ficha de forma segura |
| `CharacterSheetLoader` | Cargar ficha para personaje.php |
| `LinajeValidator` | Validar árbol de linaje |
| `AdminRequestService` | Peticiones Akuma y admin |

---

## 9. Datos estáticos

| Archivo | Uso |
|---------|-----|
| `data/linaje_catalog.json` | Razas y pasivas |
| `data/linaje_system.json` | Reglas árbol linaje |
| `data/calendar.json` | Calendario global |
| `lore.json` | Eras y eventos de historia |

---

## 10. Migraciones

Scripts en `game/sql/`, orden en `migration_helpers.php`. Runner: `run_pending_migrations.php` (admin CP).

Reciente: `migrate_cards_drop_upgrade.php` elimina `upgrade_json` y peticiones `upgrade`.

---

## 11. Convenciones para implementar features

1. Nuevo comportamiento → tabla + helper/servicio + AJAX si hace falta UI.
2. Si afecta posts → plugin o función llamada desde plugin.
3. Validar en servidor PP, berries, ownership, staff_level.
4. CSS solo en `rpg_custom.css`; JS en `jscripts/game/`.
5. Reglas B-* en `docs/auditoria-backend-foro.html`.

---

## 12. Documentos relacionados

- `02-sistema-cartas-haki-akuma.md` — Cartas, tags, Haki, Akuma
- `03-stats-nivel-pp-oficios-disciplinas.md` — Stats, PP, nivel, oficios, disciplinas
