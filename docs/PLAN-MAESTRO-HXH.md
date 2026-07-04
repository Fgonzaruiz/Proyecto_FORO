# Plan Maestro — Migración completa OP → Hunter × Hunter

> **Propósito:** Guía operativa para convertir el foro RPG de One Piece en una experiencia **Hunter × Hunter** coherente en backend, base de datos, frontend, contenido y manual.  
> **Filosofía:** Si no existe en HxH, **bórralo**. Si tiene equivalente real, **adáptalo**. Si ya es genérico, **consérvalo**.  
> **Documentos de apoyo:** [`hxh-eliminar-vs-adaptar.html`](./hxh-eliminar-vs-adaptar.html) · [`catastro-generalizacion.html`](./catastro-generalizacion.html) · [`arquitectura/overview.md`](./arquitectura/overview.md)

---

## Tabla de contenidos

1. [Visión y objetivo](#1-visión-y-objetivo)
2. [Principios de diseño HxH](#2-principios-de-diseño-hxh)
3. [Mapa de transformación OP → HxH](#3-mapa-de-transformación-op--hxh)
4. [Fases del proyecto](#4-fases-del-proyecto)
5. [Fase 0 — Preparación y rama de trabajo](#fase-0--preparación-y-rama-de-trabajo)
6. [Fase 1 — Configuración del universo](#fase-1--configuración-del-universo)
7. [Fase 2 — Base de datos](#fase-2--base-de-datos)
8. [Fase 3 — Eliminar sistemas OP (backend)](#fase-3--eliminar-sistemas-op-backend)
9. [Fase 4 — Crear sistemas HxH (backend)](#fase-4--crear-sistemas-hxh-backend)
10. [Fase 5 — Adaptar sistemas genéricos](#fase-5--adaptar-sistemas-genéricos)
11. [Fase 6 — Frontend y tema](#fase-6--frontend-y-tema)
12. [Fase 7 — Contenido y seeds](#fase-7--contenido-y-seeds)
13. [Fase 8 — Manual y documentación jugador](#fase-8--manual-y-documentación-jugador)
14. [Fase 9 — Lore e Historia](#fase-9--lore-e-historia)
15. [Fase 10 — QA, contratos y lanzamiento](#fase-10--qa-contratos-y-lanzamiento)
16. [Diseño detallado: Sistema Nen](#diseño-detallado-sistema-nen)
17. [Diseño detallado: Facciones y grupos](#diseño-detallado-facciones-y-grupos)
18. [Diseño detallado: Ubicaciones](#diseño-detallado-ubicaciones)
19. [Inventario de archivos](#inventario-de-archivos)
20. [Checklist pre-apertura](#checklist-pre-apertura)

---

## 1. Visión y objetivo

### Qué queremos lograr

Un foro MyBB con mecánicas RPG donde un jugador:

- Crea un **Cazador** (o personaje afiliado a otra organización HxH).
- Desarrolla **Nen** de forma única (no elige una "fruta" del catálogo).
- Juega combates por posts con **cartas de técnicas**, stats y oráculos.
- Explora **regiones/países** del Mundo Conocido (no islas marítimas con Log Pose).
- Completa **misiones** de la Asociación, mafia, Greed Island, etc.
- Usa **Jenny** como moneda, no Berries.
- Lee un **manual** y una **biblioteca de historia** 100% HxH.

### Qué NO queremos

- Reskins superficiales (`akuma` → `nen`, `berries` → `jenny` sin cambiar lógica).
- Mecánicas forzadas (barcos, navegación, wanted posters OP, razas Mink/Gyojin).
- Lore de "Kairan" con Poneglyphs, Marines, Yonkou, Frutas del Diablo.
- Referencias visuales o textuales a One Piece en UI, CSS, seeds o manual.

### Métrica de éxito

| Criterio | Cómo validarlo |
|----------|----------------|
| Cero referencias OP en código activo | `grep -ri "akuma\|haki\|berries\|pirata\|marine\|log pose\|grand line" back/ front/` → 0 en producción |
| Nen jugable end-to-end | Crear PJ → determinar tipo → entrenar principios → usar en combate |
| Ubicaciones HxH en foro | Categorías/foros mapeados a regiones reales del universo |
| Manual completo | 9+ secciones con contenido real, no stubs |
| Lore coherente | `lore.json` sin Kairan/OP; eventos HxH o originales inspirados |
| Tema sincronizado | `python front/sync_theme_full.py` sin errores |

---

## 2. Principios de diseño HxH

### Regla de oro

> **El Nen no es un objeto.** No hay catálogo de habilidades Nen predefinidas como las Frutas del Diablo. Cada personaje desarrolla su Nen único mediante entrenamiento, condiciones y restricciones (Vows).

### Los 3 tipos de acción

| Acción | Significado | Ejemplos |
|--------|-------------|----------|
| ❌ **ELIMINAR** | No existe en HxH | Barcos, navegación, Akuma, Haki, Wanted OP, razas OP, estilos Rokushiki |
| 🔄 **ADAPTAR** | Equivalente HxH con reescritura | Nen, Jenny, facciones, ubicaciones, misiones, oráculos, calendario |
| ✅ **CONSERVAR** | Ya genérico | Stats v7, cartas core, inventario, oráculos engine, misiones workflow, DMs, notificaciones, staff CRUD |

### Canon vs original

- **Evitar** personajes canon de Togashi (Gon, Killua, Hisoka…) como NPCs jugables o staff.
- **Inspirarse** en estructuras canon (Examen de Cazador, Yorknew, Greed Island, Succession War).
- **Crear** NPCs, facciones y eventos originales que respeten las reglas del universo.

---

## 3. Mapa de transformación OP → HxH

| One Piece | Hunter × Hunter | Acción |
|-----------|-----------------|--------|
| Akuma no Mi (Paramecia/Logia/Zoan) | Nen (6 tipos + 4 principios) | Eliminar + crear sistema nuevo |
| Haki (Obs/Arm/Conq) | Ten / Zetsu / Ren / Hatsu | Eliminar + crear progresión Nen |
| Awakening de fruta | Hatsu avanzado + Vows/Restricciones | Eliminar awakening; integrar en Nen |
| Berries (B) | Jenny (J) | Renombrar columna + textos |
| Pirata / Marine | Hunter Assoc. / Phantom Troupe / Zoldyck / Mafia / Kakin / Libre | Reescribir facciones |
| Recompensa + Wanted poster | (Opcional) Recompensas de Cazador — diseño distinto | Eliminar wanted; crear nuevo si se desea |
| Tripulación + barco | Grupo / alianza (sin navío) | Simplificar tablas y vistas |
| Islas + mares + Log Pose | Países / regiones terrestres | Adaptar `game_forum_islands` |
| Navegación marítima | Viaje terrestre o arco Black Whale (narrativo) | Eliminar subsistema completo |
| Razas (Mink, Gyojin…) | Humano (+ trasfondo/afiliación) | Reescribir linaje |
| Estilos canónicos (Rokushiki…) | Técnicas únicas por personaje (cartas) | Eliminar catálogo; técnicas = cartas |
| Lore Kairan | Lore Mundo Conocido HxH | Reescribir desde cero |
| Oficio Navegante (Log Pose) | Eliminado o "Guía de terreno" | Reescribir oficios |
| Oficio Arqueólogo (Poneglyph) | "Investigador Nen" / "Reliquiero" | Reescribir |
| Oráculos Grand Line / clima mar | Oráculos examen, emboscada, Nen hazard | Reescribir seeds |

---

## 4. Fases del proyecto

```
Fase 0  Preparación          ████░░░░░░  ~1 día
Fase 1  Config universo      ██████░░░░  ~2 días
Fase 2  Base de datos        ████████░░  ~3-4 días
Fase 3  Eliminar OP back     ████████░░  ~3 días
Fase 4  Crear Nen/grupos     ██████████  ~5-7 días
Fase 5  Adaptar genéricos   ██████░░░░  ~3 días
Fase 6  Frontend/tema        ████████░░  ~4 días
Fase 7  Seeds/contenido      ██████████  ~5-7 días
Fase 8  Manual               ██████░░░░  ~3 días
Fase 9  Lore/Historia        ████████░░  ~4 días
Fase 10 QA + lanzamiento     ████░░░░░░  ~2 días
                              ──────────
                              ~35-45 días (estimación)
```

> Ejecutar en orden. No poblar contenido (Fase 7) antes de tener BD y backend estables (Fases 2-4).

---

## Fase 0 — Preparación y rama de trabajo

### Objetivo
Entorno seguro para migración destructiva.

### Checklist

- [x] Crear rama git: `feature/hxh-migration` *(Trabajado directamente en main por orden del usuario)*
- [x] Backup completo de BD MySQL (`mysqldump mybb_game_*`) *(Omitido por orden del usuario)*
- [x] Backup de `back/forum/game/lore.json` y seeds actuales *(Omitido por orden del usuario)*
- [x] Documentar estado actual: ejecutar auditorías si existen
- [x] Crear entorno de staging (copia Laragon o BD de test)
- [x] Acordar nivel de canon: ¿solo Mundo Conocido o incluir Dark Continent desde día 1? *(Mundo conocido con el Continente Oscuro visible pero cerrado/bloqueado. 100% lore propio, cero personajes/sucesos canon Togashi)*

### Entregables

- Rama `feature/hxh-migration`
- Snapshot BD + archivos JSON
- Nota de decisiones de diseño (canon, facciones permitidas, etc.)

---

## Fase 1 — Configuración del universo

### Objetivo
Centralizar toda la identidad HxH en archivos de config, no hardcodeada.

### Crear: `back/forum/game/src/Config/universe.php` (o `universe.json`)

```php
// Ejemplo de estructura
return [
    'world_name'       => 'Mundo Conocido',
    'currency_name'    => 'Jenny',
    'currency_symbol'  => 'J',
    'currency_column'  => 'jenny', // tras migración BD

    'factions' => [
        'hunter_association' => ['label' => 'Asociación de Cazadores', 'color' => '#2E7D32'],
        'phantom_troupe'     => ['label' => 'Genei Ryodan',              'color' => '#212121'],
        'zoldyck'            => ['label' => 'Familia Zoldyck',           'color' => '#37474F'],
        'mafia'              => ['label' => 'Mafia / Sindicato',         'color' => '#6A1B9A'],
        'kakin'              => ['label' => 'Imperio Kakin',             'color' => '#BF360C'],
        'ngl'                => ['label' => 'NGL',                       'color' => '#558B2F'],
        'independiente'      => ['label' => 'Independiente',             'color' => '#546E7A'],
    ],

    'nen_types' => [
        'enhancement', 'transmutation', 'emission',
        'conjuration', 'manipulation', 'specialization',
    ],

    'nen_principles' => ['ten', 'zetsu', 'ren', 'hatsu'],

    'regions' => [
        'padokea', 'whale_island', 'yorknew', 'kukuroo',
        'greed_island', 'ngl', 'east_goruto', 'jappon', 'kakin', 'dark_continent',
    ],
];
```

### Checklist

- [x] Crear `universe.php` / `universe.json`
- [x] Crear helper `game_universe_config(string $key)` en `inc/universe_helpers.php`
- [x] Reemplazar **todas** las funciones `get_standard_faction()` / `getFactionSlug()` por lectura de config
- [x] Archivos a tocar primero:
  - `biblioteca_personajes.php`
  - `npc.php`
  - `tablon_misiones.php`
  - `views/personaje/_sidebar.php`
  - `views/personaje/_styles.php`
  - `jscripts/game/rpg_custom.js`
  - `jscripts/game/foro_interact.js`
- [x] Eliminar variables CSS `--color-faccion-pirata` / `--color-faccion-marine`
- [x] Añadir variables `--color-faccion-hunter`, `--color-faccion-troupe`, etc.

### Entregables

- Config universo centralizada
- Cero detección de facción por `strpos('pirata')`

---

## Fase 2 — Base de datos

### Objetivo
Esquema limpio HxH: eliminar tablas OP, añadir Nen, renombrar moneda, simplificar ubicaciones.

### 2.1 Migraciones de ELIMINACIÓN

Crear `sql/migrate_hxh_drop_op_systems.php`:

| Acción | Tabla / columna |
|--------|-----------------|
| DROP | `game_akuma_no_mi` |
| DROP | `game_haki_progress` |
| DROP | `game_navigation_routes` |
| DROP | `game_navigation_voyages` |
| DROP | `game_navigation_events` |
| DROP | `game_wanted` |
| DROP | `game_estilos_canonicos` (o vaciar y rediseñar) |
| ALTER | `game_cards.card_type` — quitar `akuma_no_mi`, `haki`, `barco` del ENUM |
| ALTER | `game_character_inventory` — quitar slot `barco` |
| ALTER | `game_tripulaciones` — DROP `ship_name`, `ship_data` |
| ALTER | `game_forum_islands` — DROP `sea_zone`, `requires_log_pose`, `requires_compass`, `controlling_type`, `controlling_id` |
| ALTER | `game_personajes` — DROP o renombrar `recompensa` (campo OP bounty) |

### 2.2 Migraciones de ADAPTACIÓN

Crear `sql/migrate_hxh_currency.php`:

```sql
ALTER TABLE mybb_game_personajes CHANGE berries jenny INT NOT NULL DEFAULT 0;
ALTER TABLE mybb_game_cards CHANGE cost_berries cost_jenny INT NOT NULL DEFAULT 0;
-- Repetir en misiones, tienda, etc.
```

Crear `sql/migrate_hxh_locations.php`:

```sql
ALTER TABLE mybb_game_forum_islands
  ADD COLUMN region_slug VARCHAR(64) DEFAULT NULL AFTER name,
  ADD COLUMN country VARCHAR(128) DEFAULT NULL,
  ADD COLUMN travel_difficulty TINYINT DEFAULT 1 COMMENT '1=fácil, 5=Dark Continent';
-- Renombrar tabla opcionalmente a game_forum_locations (fase posterior)
```

Crear `sql/migrate_hxh_factions.php`:

```sql
ALTER TABLE mybb_game_personajes
  ADD COLUMN faction_slug VARCHAR(32) DEFAULT 'independiente' AFTER race;
-- Migrar datos: pirata→independiente, marine→hunter_association, etc.
```

### 2.3 Migraciones de CREACIÓN (Nen)

Crear `sql/migrate_hxh_nen_system.php`:

```sql
CREATE TABLE mybb_game_nen (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id INT UNSIGNED NOT NULL UNIQUE,
  nen_type ENUM('enhancement','transmutation','emission','conjuration','manipulation','specialization') DEFAULT NULL,
  nen_type_locked TINYINT(1) DEFAULT 0 COMMENT 'Tras prueba de la taza',
  aura_color VARCHAR(32) DEFAULT NULL,
  vows_json JSON DEFAULT NULL COMMENT 'Restricciones y penalizaciones',
  notes TEXT,
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  KEY idx_type (nen_type)
);

CREATE TABLE mybb_game_nen_progress (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id INT UNSIGNED NOT NULL,
  principle ENUM('ten','zetsu','ren','hatsu') NOT NULL,
  level TINYINT UNSIGNED DEFAULT 0 COMMENT '0=sin entrenar, 1=básico, 2=intermedio, 3=avanzado, 4=maestría',
  experience INT UNSIGNED DEFAULT 0,
  unlocked_at INT UNSIGNED DEFAULT NULL,
  UNIQUE KEY uq_char_principle (character_id, principle)
);

CREATE TABLE mybb_game_nen_abilities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id INT UNSIGNED NOT NULL,
  name VARCHAR(128) NOT NULL,
  description TEXT,
  rank ENUM('D','C','B','A','S','SS') DEFAULT 'D',
  nen_cost INT DEFAULT 0 COMMENT 'Aura/PE cost',
  conditions_json JSON DEFAULT NULL COMMENT 'Activación, restricciones',
  card_id INT UNSIGNED DEFAULT NULL COMMENT 'Vínculo opcional a carta técnica',
  approved TINYINT(1) DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_character (character_id)
);
```

### 2.4 Migraciones de GRUPOS (ex-tripulaciones)

Crear `sql/migrate_hxh_groups.php`:

```sql
-- Opcional: renombrar tripulaciones → groups
ALTER TABLE mybb_game_tripulaciones
  ADD COLUMN group_type ENUM('libre','troupe','hunter_squad','zoldyck','mafia','kakin') DEFAULT 'libre',
  ADD COLUMN troupe_number TINYINT UNSIGNED DEFAULT NULL COMMENT 'Solo Phantom Troupe: 1-13';
-- Eliminar campos navales ya en 2.1
```

### Checklist Fase 2

- [x] Escribir las 5 migraciones anteriores *(Consolidadas en migrate_hxh_initial.php)*
- [x] Actualizar `install_schema_fragments.php` para instalaciones limpias
- [x] Ejecutar en staging: `php sql/run_pending_migrations.php`
- [x] Verificar integridad FK y datos migrados
- [x] Actualizar contratos OpenAPI si columnas cambian (`packages/contracts/`) *(Completado y auditado al 100% de cobertura)*

---

## Fase 3 — Eliminar sistemas OP (backend)

### Objetivo
Quitar código muerto. No dejar rutas huérfanas.

### 3.1 Archivos PHP a ELIMINAR

```
back/forum/game/
├── inc/
│   ├── akuma_helpers.php                    ❌
│   ├── navigation_config.php                ❌
│   ├── navigation_helpers.php               ❌
│   ├── navigation_process.php               ❌
│   └── navigation_review_helpers.php        ❌
├── ajax/
│   ├── akuma_catalog.php                    ❌
│   ├── akuma_roll.php                       ❌
│   ├── haki_upgrade.php                     ❌
│   ├── haki_resolve.php                     ❌
│   ├── haki_pending_requests.php            ❌
│   ├── haki_conquistador_roll.php           ❌
│   └── navigation_*.php (8 archivos)        ❌
├── public/
│   ├── akuma_no_mi.php                      ❌
│   ├── peticion_akuma.php                   ❌
│   ├── peticion_akuma_aleatoria.php         ❌
│   ├── peticion_akuma_demanda.php           ❌
│   ├── peticion_haki.php                    ❌
│   ├── peticion_awakening.php               ❌
│   ├── wanted.php                           ❌
│   ├── zona_staff_wanted.php                ❌
│   ├── zona_staff_navegacion.php            ❌
│   ├── zona_staff_rutas.php                 ❌
│   └── seed_imu_max.php                     ❌
└── views/
    ├── personaje/_tab_haki.php              ❌
    ├── tripulacion/_tab_navio.php           ❌
    └── tripulacion/_tab_territorios.php     ❌
```

### 3.2 Archivos JS a ELIMINAR

```
back/forum/jscripts/game/
├── akuma_no_mi.js                           ❌
├── peticion_akuma.js                        ❌
├── peticion_akuma_aleatoria.js              ❌
├── peticion_akuma_demanda.js                ❌
├── peticion_haki.js                         ❌
├── peticion_awakening.js                    ❌
├── navigation.js                            ❌
├── wanted.js                                ❌
├── zona_staff_wanted.js                     ❌
├── zona_staff_navegacion.js                 ❌
└── zona_staff_rutas.js                      ❌
```

### 3.3 Seeds SQL a ELIMINAR

```
sql/seed_east_blue_test.php                  ❌
sql/seed_oracles_example.php                 ❌ (reemplazar)
sql/seed_oracles_example.sql                 ❌ (reemplazar)
sql/estilos_canonicos_seed_data.php          ❌
sql/migrate_weather_oracles.php              ❌ (contenido OP)
sql/migrate_navigation_*.php                 ❌ (mantener solo si histórico)
```

### 3.4 JSON a ELIMINAR o REESCRIBIR

```
data/linaje_catalog.json                     ❌ → data/backgrounds.json (nuevo)
data/linaje_system.json                      ❌ → data/background_system.json (nuevo, ~90% más pequeño)
lore.json                                    ❌ → lore_hxh.json (nuevo)
lore1.json                                   ❌
lorecorreciones3.json                        ❌
```

### 3.5 Limpiar referencias en archivos que SE QUEDAN

| Archivo | Qué quitar |
|---------|------------|
| `inc/AdminRequestService.php` | Tipos `akuma`, `haki`, `awakening` |
| `public/peticiones_admin.php` | Tabs de akuma/haki/awakening |
| `public/cartas_staff.php` | Secciones akuma/haki/barco |
| `public/index.php` | Links a wanted, akuma, navegación |
| `public/zona_staff.php` | Entradas staff OP |
| `inc/plugins/game_postcharacter.php` | Modificadores haki/akuma |
| `jscripts/game/cartas_staff.js` | Funciones akuma/haki/barco |
| `jscripts/game/foro_deck_ui.js` | Tipos de carta OP |
| `inc/StatScale.php` | Métodos haki/akuma (si existen) |
| `inc/LinajeValidator.php` | Toda lógica racial OP |

### Checklist Fase 3

- [x] Eliminar archivos listados
- [x] Buscar referencias rotas: `grep -r "akuma\|haki\|navigation_\|wanted\.php" back/`
- [x] Actualizar menús (`index.php`, `zona_staff.php`, header template)
- [x] Actualizar `peticiones_admin.php` — solo peticiones HxH
- [x] Commit: `chore(hxh): remove OP systems (akuma, haki, nav, wanted)`

---

## Fase 4 — Crear sistemas HxH (backend)

### Objetivo
Nen jugable, grupos HxH, peticiones staff nuevas.

### 4.1 Sistema Nen — archivos nuevos

```
back/forum/game/
├── inc/
│   └── nen_helpers.php                      ✅ Validación, costes, prueba de tipo
├── src/Application/Services/
│   └── NenService.php                       ✅ CRUD Nen, progresión, habilidades
├── src/Infrastructure/Repositories/
│   └── NenRepository.php                    ✅
├── ajax/
│   ├── nen_status.php                       ✅ Estado Nen del PJ activo
│   ├── nen_train.php                        ✅ Entrenar principio (staff/player)
│   ├── nen_set_type.php                     ✅ Prueba de la taza (staff)
│   ├── nen_ability_save.php                 ✅ Crear/editar habilidad Nen
│   └── nen_ability_approve.php              ✅ Staff aprueba Hatsu
├── public/
│   ├── nen.php                              ✅ Página principal Nen del PJ
│   ├── peticion_nen.php                     ✅ Solicitud entrenamiento / desbloqueo
│   └── zona_staff_nen.php                   ✅ Panel staff Nen
└── views/personaje/
    └── _tab_nen.php                         ✅ Tab en ficha personaje
```

### 4.2 Flujo jugador Nen (diseño UX)

```
1. Crear personaje (sin Nen)
2. Arco narrativo / misión → desbloquear "Despertar Nen" (Ren básico)
3. Prueba de la taza (staff) → fijar nen_type (irreversible)
4. Entrenar Ten → Zetsu → Ren → Hatsu (por principio)
5. Crear habilidades Nen (propuesta → staff aprueba → genera carta técnica)
6. Usar en combate vía cartas vinculadas
```

### 4.3 Peticiones admin — nuevos tipos

Reemplazar en `game_admin_requests.request_type`:

| Antes (OP) | Después (HxH) |
|------------|---------------|
| `akuma_random` | — (eliminado) |
| `akuma_demand` | — (eliminado) |
| `haki_upgrade` | `nen_train` |
| `awakening` | `nen_hatsu_advanced` |
| — | `nen_type_test` (prueba de la taza) |
| — | `nen_ability_create` |

### 4.4 Grupos (ex-tripulaciones)

Grupos = agrupaciones de jugadores. Sin `group_type`, sin Troupe. Pueden tener **guaridas** (subforo dentro de una ubicación, mejorable a futuro).

Renombrar UI "Tripulación" → "Grupo", "Capitán" → "Líder":

- [x] `public/tripulacion.php` → adaptar textos, quitar tabs navío/territorios, quitar recompensa total
- [x] Eliminar tabs navío y territorios de vistas y controladores
- [x] Eliminar campos `ship_name`, `ship_image_url`, `ship_data` (ya migrados)
- [x] Renombrar "Capitán" → "Líder", "Tripulación" → "Grupo" en toda la UI
- [x] Guaridas: se añadirán como subforos de ubicación en Fase 7

### Checklist Fase 4

- [ ] Implementar NenService + endpoints
- [ ] Tab `_tab_nen.php` en ficha personaje
- [ ] Peticiones staff funcionando
- [ ] Tests manuales: crear PJ → asignar tipo → entrenar → aprobar habilidad
- [ ] Commit: `feat(hxh): Nen system (tables, service, UI, ajax)`

---

## Fase 5 — Adaptar sistemas genéricos

### 5.1 Cartas

- [ ] ENUM `card_type`: solo `tecnica`, `equipo`, `npc_menor`, `objeto`, `consumible`
- [ ] Eliminar validaciones akuma/haki en `cards_request.php`, `cards_equip.php`
- [ ] Cartas de técnicas Nen: tag `nen:{type}` en effects JSON
- [ ] Renombrar `cost_berries` → `cost_jenny` en toda la UI tienda

### 5.2 Misiones

- [ ] Reescribir seed en `migrate_missions_system.php` o nuevo `seed_missions_hxh.php`
- [ ] Tipos: `caceria`, `escolta`, `investigacion`, `exam_hunter`, `greed_island`, `sucesion`
- [ ] Recompensas en Jenny + PD (puntos de cazador, opcional)
- [ ] Ubicaciones: Yorknew, Padokea, NGL, etc.

### 5.3 Oráculos

- [ ] Crear `seed_oracles_hxh.php`:
  - Encuentro en examen (trampa, candidato, examinador)
  - Emboscada mafia / Troupe
  - Fenómeno Nen inestable
  - Hallazgo Greed Island
  - Clima terrestre (no marítimo)
- [ ] Eliminar oráculos Yonko, Log Pose, Calm Belt

### 5.4 Oficios y disciplinas

Reescribir `competencias_v2_seed_data.php`:

| OP (eliminar) | HxH (nuevo) |
|---------------|-------------|
| Navegante (Log Pose) | — |
| Carpintero (barcos) | Armero / Artífice |
| Arqueólogo (Poneglyph) | Investigador de reliquias |
| Cocinero (All Blue refs) | Cocinero / Chef |
| — | Evaluador Nen (staff) |
| — | Rastreador |
| — | Informante mafia |

### 5.5 Calendario

Reescribir `data/calendar.json`:

- Examen de Cazador (anual, Padokea)
- Subasta de Yorknew
- Torneo Heaven's Arena (opcional)
- Arco Greed Island (evento especial)
- Succession War (Kakin, evento staff)

### 5.6 Economía / Tienda

- [ ] Todos los textos "Berries" → "Jenny"
- [ ] Icono moneda: `fa-coins` con color dorado neutro (no `--rpg-manual-icon--berries`)
- [ ] Items tienda: eliminar frutas, log pose, barcos

### Checklist Fase 5

- [ ] Seeds HxH ejecutados
- [ ] Tienda sin items OP
- [ ] Misiones y oráculos verificados in-game
- [ ] Commit: `feat(hxh): adapt missions, oracles, jobs, calendar, shop`

---

## Fase 6 — Frontend y tema

### Objetivo
UI coherente HxH: sin wanted, sin navegación en posts, colores y tipografía acordes.

### 6.1 Templates MyBB (`front/templates/mybb/`)

| Template | Cambio |
|----------|--------|
| `global/header.html` | Quitar link CARTELERA WANTED → añadir NEN / GRUPOS / MANUAL |
| `forumdisplay/forumdisplay_island_header.html` | Renombrar a `forumdisplay_location_header.html`; "Región" en vez de "Isla" |
| `posting/_rpg_system_block.html` | **Eliminar tab Navegación** completo |
| `newthread/newthread.html` | Idem |
| `newreply/newreply.html` | Idem |
| `showthread/showthread_quickreply.html` | Idem |

### 6.2 CSS (`back/forum/rpg_custom.css`)

Eliminar bloques (~2000+ líneas estimadas):

- `.rpg-card--akuma-*` (paramecia/logia/zoan)
- `.rpg-card--haki-*` (busoshoku/kenbunshoku/haoshoku)
- `.rpg-card--barco`
- `.rpg-island-*` (adaptar a `.rpg-location-*`)
- `.rpg-nav-*` (instrumentos, spinner Log Pose)
- `--color-faccion-pirata`, `--color-faccion-marine`

Añadir bloques:

- `.rpg-card--nen-{type}` (6 tipos, colores distintivos)
- `.rpg-nen-principle` (Ten/Zetsu/Ren/Hatsu badges)
- `.rpg-faction--hunter`, `.rpg-faction--troupe`, etc.
- `.rpg-location-*` (regiones HxH)
- Paleta general: verdes oscuros, negros, acentos cyan (aura Nen)

### 6.3 JS frontend

- [ ] `foro_deck_ui.js` — tipos carta HxH
- [ ] `rpg_custom.js` — facciones desde config
- [ ] Crear `nen.js` — UI tab Nen
- [ ] `tripulacion_page.js` — quitar lógica navío

### 6.4 Sync tema

```bash
cd front
php diff_theme_source.php
python sync_theme_full.py
php validate_theme_security.php
```

### 6.5 Branding

- [ ] Logo / banner foro (HxH, no OP)
- [ ] Favicon
- [ ] Imágenes `images/game/` — reemplazar banners wanted, akuma, navegación
- [ ] Footer manual: "Foro Hunter × Hunter" (no "Foro One Piece")

### Checklist Fase 6

- [ ] Cero tabs navegación en posting
- [ ] Header sin wanted
- [ ] CSS sincronizado a Default-theme.xml
- [ ] Commit: `feat(hxh): frontend theme, templates, CSS Nen factions`

---

## Fase 7 — Contenido y seeds

### Objetivo
Mundo jugable poblado antes de abrir.

### 7.1 Ubicaciones — `seed_locations_hxh.php`

| Región | Foros ejemplo | Dificultad |
|--------|---------------|------------|
| Whale Island | Pueblo, Bosque | 1 |
| Padokea | Ciudad examen, Monte | 2 |
| Yorknew City | Centro, Subasta, Mafia | 3 |
| Monte Kukuroo | Mansión Zoldyck | 4 |
| Greed Island | Ciudad inicial, Bosques | 3 |
| NGL | Frontera, Capital | 3 |
| East Goruto | Palacio, Ruinas | 4 |
| Jappon | Dojos, Templos | 2 |
| Kakin | Capital, Puerto Black Whale | 4 |
| Dark Continent | — (bloqueado hasta arco) | 5 |

### 7.2 NPCs seed (originales, no canon)

- Presidente de la Asociación (NPC staff)
- Examinador senior
- Informante mafia Yorknew
- Guardián Zoldyck (NPC)
- — Mínimo 5 NPCs útiles para misiones

### 7.3 Cartas seed (técnicas genéricas)

- Puño Nen básico (Enhancement D)
- Paso rápido (Enhancement C)
- Bola de aura (Emission D)
- Cuchillo conjurado (Conjuration C)
- Control de cuerpo (Manipulation B)
- — 15-20 cartas base sin copiar Hatsu canon

### 7.4 Misiones seed (mínimo 10)

1. Fase 1 del Examen de Cazador
2. Escolta en Yorknew
3. Rastreo en Padokea
4. Investigación NGL
5. Recuperar objeto Greed Island
6. …

### Checklist Fase 7

- [ ] Seeds ejecutados en staging
- [ ] Cada región tiene ≥1 foro
- [ ] ≥10 misiones, ≥15 cartas, ≥5 NPCs
- [ ] Oráculos HxH activos

---

## Fase 8 — Manual y documentación jugador

### Objetivo
Manual completo, no stubs. Referencia única para jugadores.

### Reestructurar secciones (`manual.php`)

| ID | Título actual | Título HxH | Contenido clave |
|----|---------------|------------|-----------------|
| `intro` | Introducción | Introducción al Mundo | Qué es el foro, tono, Mundo Conocido |
| `creacion` | Creación de Personaje | Creación de Personaje | Stats, trasfondo, facción, aprobación |
| `linaje` | Linaje y Razas | Trasfondo y Afiliación | Humanos, facciones HxH, no razas OP |
| `combate` | Sistema de Combate | Combate y Posts | PV/PE, tiradas, turnos, modifiers |
| `nen` | *(nuevo)* | Nen y Aura | 6 tipos, 4 principios, Vows, progresión |
| `cartas` | Cartas e Inventario | Cartas e Inventario | Mazo, técnicas, equipo, ranks |
| `oficios` | *(nuevo)* | Oficios y Disciplinas | Competencias HxH |
| `misiones` | *(nuevo)* | Misiones y Recompensas | Tablón, PD, Jenny |
| `economia` | Economía y Tienda | Economía (Jenny) | Tienda, precios, PD |
| `grupos` | *(nuevo)* | Grupos y Organizaciones | Hunter squads, Troupe, etc. |
| `reglas` | Reglas de Rol | Reglas de Rol | Powergaming, metagaming, staff |
| `faq` | FAQ | Preguntas Frecuentes | Nen, examen, límites |

Eliminar secciones OP:

- ~~Estilos Canónicos~~ (Rokushiki…) → integrar en Nen/cartas
- ~~Frutas del Diablo~~ → eliminada
- ~~Haki~~ → eliminada
- ~~Navegación~~ → eliminada

### Archivos

```
public/manual_secciones/
├── intro.php          ✅
├── creacion.php       ✅
├── trasfondo.php      ✅ (ex-linaje)
├── combate.php        ✅
├── nen.php            ✅ NUEVO
├── cartas.php         ✅ adaptar
├── oficios.php        ✅ adaptar
├── misiones.php       ✅ NUEVO
├── economia.php       ✅
├── grupos.php         ✅ NUEVO
├── reglas.php         ✅
└── faq.php            ✅
```

### Checklist Fase 8

- [ ] 12 secciones con contenido real
- [ ] Footer: "Foro Hunter × Hunter"
- [ ] Enlaces cruzados entre secciones
- [ ] Commit: `docs(hxh): complete player manual`

---

## Fase 9 — Lore e Historia

### Objetivo
Biblioteca de Historia (`historia.php`) con lore HxH original o inspirado, sin Kairan.

### Estructura `lore_hxh.json`

```json
{
  "meta": {
    "world_name": "Mundo Conocido",
    "era_nota": "Era actual: post-Examen 287 · Pre-Succession War",
    "instruccion": "NPCs y eventos originales. Cero personajes canon Togashi."
  },
  "eras": [
    { "id": 1, "name": "Era de la Asociación Fundacional", "start_year": 0, "end_year": 200 },
    { "id": 2, "name": "Era de Expansión del Mundo", "start_year": 200, "end_year": 500 },
    { "id": 3, "name": "Era del Nen Moderno", "start_year": 500, "end_year": 900 },
    { "id": 4, "name": "Era Actual", "start_year": 900, "end_year": null }
  ],
  "lore_basal": [
    { "id": 1, "era_id": 1, "name": "Origen del Nen", "subtype": "sistema_poder", "desc": "..." },
    { "id": 2, "name": "La Asociación de Cazadores", "subtype": "faccion", "desc": "..." },
    { "id": 3, "name": "El Dark Continent", "subtype": "lugar_prohibido", "desc": "..." }
  ],
  "eventos": [ "..." ],
  "periodicos": [ "..." ]
}
```

### Temas lore_basal HxH

1. Origen y reglas del Nen
2. Asociación de Cazadores (12+1 estrellas)
3. Dark Continent y las 5 calamidades
4. Greed Island (como leyenda/juego)
5. Phantom Troupe (mito y terror)
6. Familia Zoldyck
7. Yorknew y la mafia
8. NGL y las Hormigas Quimera (como evento histórico)
9. Imperio Kakin y la Succession War
10. Restricciones Nen (Vows) — lore mecánico

### Biblioteca UI

- Mantener diseño "libro" actual (`historia.php`)
- Pestañas: **Historia Basal** · **Eventos** · **Lore** · **Crónicas** (periodicos)
- Ilustraciones HxH (originales o con licencia)

### Checklist Fase 9

- [ ] Eliminar lore.json OP
- [ ] Crear lore_hxh.json (mín. 10 lore_basal, 15 eventos, 3 crónicas)
- [ ] `LoreService.php` apunta al nuevo archivo
- [ ] Staff puede editar vía `zona_staff_historia.php`
- [ ] Commit: `feat(hxh): new lore bible and historia content`

---

## Fase 10 — QA, contratos y lanzamiento

### Tests funcionales

| # | Test | Esperado |
|---|------|----------|
| 1 | Crear personaje | Sin errores; facción HxH selectable |
| 2 | Asignar Nen (staff) | Tipo fijado; tab visible |
| 3 | Entrenar principio | Progreso guardado |
| 4 | Crear habilidad Nen | Petición → aprobación → carta |
| 5 | Combate en post | Cartas técnicas, PV/PE, oráculos |
| 6 | Misión | Aceptar → completar → Jenny |
| 7 | Tienda | Comprar con Jenny |
| 8 | Grupo | Crear unirse sin campos barco |
| 9 | Historia | Libro carga lore HxH |
| 10 | Manual | 12 secciones navegables |

### Auditorías automáticas

```bash
python tools/audit_backend_contracts.py
php front/validate_theme_security.php
grep -ri "akuma\|haki\|berries\|one piece\|grand line" back/ front/ --include="*.php" --include="*.js" --include="*.json"
```

### Deploy

1. Migraciones en producción (ventana mantenimiento)
2. Sync tema
3. Purgar caché MyBB
4. Anuncio en foro: "Migración HxH completada"

---

## Diseño detallado: Sistema Nen

### Tipos de Nen (canon)

| Tipo | Español | Fortaleza típica | Color UI sugerido |
|------|---------|------------------|-------------------|
| Enhancement | Reforzamiento | Potencia física | `#E53935` |
| Transmutation | Transformación | Cambiar propiedades aura | `#1E88E5` |
| Emission | Emisión | Proyectar aura | `#FDD835` |
| Conjuration | Materialización | Crear objetos | `#8E24AA` |
| Manipulation | Manipulación | Control remoto | `#43A047` |
| Specialization | Especialización | Reglas únicas | `#FF6F00` |

### Principios (progresión)

| Principio | Función en rol | Desbloqueo típico |
|-----------|----------------|-------------------|
| Ten | Concentración, ocultar aura | Primero |
| Zetsu | Suprimir presencia, ahorrar aura | Tras Ten básico |
| Ren | Output de aura, PE máximo | Tras arco entrenamiento |
| Hatsu | Habilidad personal | Tras Ren intermedio + aprobación staff |

### Reglas mecánicas foro

1. **Sin tipo Nen** → no puedes usar cartas con tag `nen:*`
2. **Tipo equivocado** → staff rechaza habilidad (Specialization es excepción por reglas canon)
3. **Vows** (opcional avanzado): bonus/malus declarados en `vows_json`, validados en combate
4. **PE (Puntos de Energía)** = aura disponible por combate; Ren sube el techo
5. **No hay "tirada de fruta aleatoria"** — ever

### Integración con cartas

- Cada Hatsu aprobado genera (o vincula) una carta `tecnica` con:
  - `effects.nen_type`
  - `effects.nen_cost`
  - `effects.conditions` (Vows activos)
- Validación en post: plugin verifica PE y condiciones

---

## Diseño detallado: Facciones y grupos

### Facciones individuales (personaje)

```json
{
  "hunter_association": {
    "label": "Asociación de Cazadores",
    "license_required": true,
    "license_tiers": ["none", "provisional", "single_star", "double_star", "triple_star"]
  },
  "phantom_troupe": {
    "label": "Genei Ryodan",
    "invite_only": true,
    "max_members": 13
  },
  "zoldyck": {
    "label": "Familia Zoldyck",
    "invite_only": true,
    "bloodline_or_contract": true
  }
}
```

### Grupos (party)

- Un personaje → un grupo
- Tipos: libre, hunter_squad, troupe, mafia_cell, kakin_faction
- Troupe: número 1-13 único en todo el foro
- **Sin** barco, **sin** territorio naval

---

## Diseño detallado: Ubicaciones

### Renombrar concepto "Isla" → "Ubicación"

La tabla `game_forum_islands` puede mantenerse internamente (coste refactor) pero la UI dice **Ubicación** o **Región**.

### Campos finales

| Campo | Uso |
|-------|-----|
| `name` | Nombre display (Yorknew City) |
| `region_slug` | `yorknew` |
| `country` | País padre |
| `travel_difficulty` | 1-5 |
| `description` | Lore corto |
| `forum_id` | FK MyBB |

### Viaje entre ubicaciones

- **Simple (recomendado):** narrativo; el hilo dice dónde estás; staff valida
- **Medio:** cooldown entre regiones vía `game_personajes.current_location`
- **Complejo (futuro):** rutas terrestres sin barcos — solo si hay demanda

---

## Inventario de archivos

### Resumen cuantitativo

| Acción | ~Archivos |
|--------|-----------|
| Eliminar | ~50 |
| Crear nuevos | ~25 |
| Modificar | ~80 |
| Seeds nuevos | ~8 |

### Archivos NUEVOS principales

```
docs/PLAN-MAESTRO-HXH.md                     ← este documento
back/forum/game/src/Config/universe.php
back/forum/game/inc/universe_helpers.php
back/forum/game/inc/nen_helpers.php
back/forum/game/src/Application/Services/NenService.php
back/forum/game/sql/migrate_hxh_*.php        (5 migraciones)
back/forum/game/sql/seed_locations_hxh.php
back/forum/game/sql/seed_oracles_hxh.php
back/forum/game/sql/seed_missions_hxh.php
back/forum/game/data/backgrounds.json
back/forum/game/data/background_system.json
back/forum/game/lore_hxh.json
back/forum/game/public/nen.php
back/forum/game/public/peticion_nen.php
back/forum/game/public/zona_staff_nen.php
back/forum/game/public/manual_secciones/nen.php
back/forum/game/public/manual_secciones/grupos.php
back/forum/game/public/manual_secciones/misiones.php
back/forum/jscripts/game/nen.js
```

---

## Checklist pre-apertura

### Infraestructura
- [ ] Migraciones ejecutadas en producción
- [ ] Backup post-migración
- [ ] Contratos OpenAPI actualizados
- [ ] Tema sincronizado

### Gameplay core
- [ ] Nen end-to-end funcional
- [ ] Combate con cartas técnicas
- [ ] Oráculos HxH
- [ ] Misiones HxH
- [ ] Tienda con Jenny
- [ ] Grupos sin barco

### Contenido
- [ ] ≥10 ubicaciones
- [ ] ≥10 misiones
- [ ] ≥15 cartas
- [ ] ≥5 NPCs
- [ ] Lore ≥10 entradas basales
- [ ] Manual 12 secciones completas
- [ ] Calendario con eventos HxH

### Limpieza OP
- [ ] grep sin referencias OP en código activo
- [ ] Sin páginas 404 de akuma/wanted/nav
- [ ] Header/footer sin One Piece
- [ ] CSS sin clases akuma/haki/barco

### Staff
- [ ] Guía staff Nen (cómo aprobar Hatsu)
- [ ] Panel zona_staff_nen operativo
- [ ] Procedimiento prueba de la taza documentado

### Comunicación
- [ ] Anuncio migración redactado
- [ ] FAQ para jugadores antiguos OP ("qué pasó con mi fruta/haki")
- [ ] Reglas transición (personajes existentes reset Nen)

---

## Apéndice A — Migración de personajes existentes

Si hay PJs OP en BD antes de abrir:

| Dato OP | Política recomendada |
|---------|---------------------|
| Fruta asignada | Eliminar; staff asigna Nen en sesión 1 a 1 |
| Progreso Haki | Mapear a Ren/Hatsu equivalente (staff manual) |
| Berries | Convertir 1:1 a Jenny |
| Recompensa bounty | Eliminar campo |
| Raza OP | → Humano + trasfondo narrativo |
| Cartas akuma/haki/barco | Archivar o convertir a técnica genérica (staff) |
| Tripulación barco | Eliminar ship_data; mantener grupo |

Comunicar con **2 semanas de antelación** y ventana de ajuste staff.

---

## Apéndice B — Orden de commits sugerido

1. `chore(hxh): add universe config and PLAN-MAESTRO-HXH`
2. `feat(hxh): database migrations drop OP + add Nen`
3. `chore(hxh): remove OP backend files`
4. `feat(hxh): Nen service, ajax, UI`
5. `feat(hxh): adapt factions, currency, locations`
6. `feat(hxh): seeds missions oracles locations`
7. `feat(hxh): frontend templates and CSS`
8. `docs(hxh): player manual complete`
9. `feat(hxh): lore_hxh.json and historia`
10. `chore(hxh): final QA grep cleanup`

---

## Apéndice C — Referencias

- [`docs/hxh-eliminar-vs-adaptar.html`](./hxh-eliminar-vs-adaptar.html) — Matriz eliminar/adaptar/conservar
- [`docs/catastro-generalizacion.html`](./catastro-generalizacion.html) — Inventario términos OP en código
- [`docs/arquitectura/overview.md`](./arquitectura/overview.md) — Arquitectura MyBB + game module
- [`back/forum/game/sql/README.md`](../back/forum/game/sql/README.md) — Cómo ejecutar migraciones

---

*Última actualización: Julio 2026 · Versión 1.0 · Mantener este documento vivo: marcar checkboxes y añadir notas por fase.*
