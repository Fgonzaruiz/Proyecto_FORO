# Stats, Nivel, PP, Oficios y Disciplinas

> Cómo funciona la progresión del personaje en el código actual (`StatScale`, `CharacterProgression`, `grado_helpers`).  
> Complementa `01-sistema-rpg-global.md`.

---

## 1. Filosofía del sistema v7

Antes del foro existía una escala de stats 1–20 (manual antiguo). **Hoy el sistema v7 usa 7 atributos con rangos 1–6**, etiquetados D → SS. Cada rango tiene un **valor mecánico** (4, 8, 15, 26, 40, 60) que alimenta fórmulas de PV/PE y tiradas.

Lo que el jugador **entrena** (gasta PP) son los rangos en `stats_json`. Lo que **hereda** de su raza son bonos en `linaje_catalog.json`, aplicados solo al calcular valores efectivos — no se guardan duplicados en stats.

El **nivel** del personaje (1–6) no es un grind separado: es un reflejo del **rango global** derivado de la suma de stats. Para oficios y disciplinas se usa además una escala **character_level** 1–50.

---

## 2. Los siete stats

| Clave | Nombre | Rol en combate y fórmulas |
|-------|--------|---------------------------|
| `fue` | Fuerza | Daño físico, carga; parte de PV |
| `res` | Resistencia | Aguante, defensa; parte de PV |
| `agi` | Agilidad | Iniciativa, evasión; parte de PV y PE |
| `des` | Destreza | Precisión; parte de PE |
| `int` | Intelecto | Estrategia, frutas complejas; parte de PE |
| `inst` | Instinto | Percepción, reflejos |
| `esp` | Espíritu | Voluntad, **Haki**; parte de PV y PE |

### 2.1 Dónde se guardan

- **`stats_json`** en `game_personajes`: solo rangos **entrenados** (mínimo 1, máximo 6 por stat).
- Bonos raciales: runtime desde `data/linaje_catalog.json` → `StatScale::getRacialBonuses(race_name)`.
- Modificadores temporales de combate: `modifiers_json` en el post y `stat_mods_json` en el estado del hilo.

### 2.2 Rangos y etiquetas

| Rango entrenado | Etiqueta | Valor (`rangoAValor`) |
|-----------------|----------|------------------------|
| 1 | D | 4 |
| 2 | C | 8 |
| 3 | B | 15 |
| 4 | A | 26 |
| 5 | S | 40 |
| 6 | SS | 60 |

**Rango efectivo** = entrenado + bono racial (+ mod de turno si hay combate activo).

Si efectivo > 6, el valor escala linealmente (+20 por punto extra) y la etiqueta puede ser SS+, SS++ o M (`rankDisplayLabel`).

### 2.3 Cómo se calculan PV y PE

Sobre los **valores efectivos** (no los rangos crudos):

```
PV_base = (res × 4) + (fue × 3) + (esp × 2) + (agi × 1)
PE_base = (esp × 4) + (des × 3) + (int × 2) + (agi × 1)
```

Luego se multiplica por el factor del **rango global** del PJ:

| Rango global | Multiplicador PV/PE |
|--------------|---------------------|
| D | 1.00 |
| C | 1.05 |
| B | 1.08 |
| A | 1.10 |
| S | 1.12 |
| SS | 1.15 |

**En un hilo concreto**, `game_thread_pj_state` guarda `current_pv` y `current_pe` que van cambiando post a post según `pv_change` y `pe_change` en cada publicación.

Helper central: `game_build_stat_context()` en `stat_helpers.php` — devuelve trained, effective_ranks, values y display labels para la UI.

---

## 3. Bonos raciales (ejemplos)

Fuente: `game/data/linaje_catalog.json`. Se suman al rango entrenado al calcular efectivos.

| Raza | Bonos notables |
|------|----------------|
| Humano | Sin bono numérico; 4 puntos de distribución inicial en creación |
| Mink | +agi, +des, +inst |
| Gyojin | +fue, +res, −agi |
| Gigante | +fue, +res, −agi, −des |
| Buccaner | +fue, +res, +esp×2 |
| Lunarian | +fue, +res, −agi, +esp |
| Tontatta | Penalizaciones y bonos extremos; 5 puntos iniciales |

Las **pasivas** y opciones de **linaje** (`data_json.linaje`) son narrativas o reglas especiales documentadas en JSON; no todas están automatizadas en código.

---

## 4. Rango global y nivel

### 4.1 Cómo se calcula el rango global

Se suman los **7 rangos entrenados** (sin bonos raciales) y se mapea:

| Suma | Rango global |
|------|--------------|
| ≤ 10 | D |
| 11–16 | C |
| 17–22 | B |
| 23–28 | A |
| 29–36 | S |
| ≥ 37 | SS |

Se guarda en `data_json.rank`. Cada vez que el jugador sube un stat con PP, `CharacterProgression::recalculateGlobalRank` actualiza rank y nivel.

### 4.2 Nivel (`data_json.nivel`)

Derivado automáticamente:

| Rango global | nivel |
|--------------|-------|
| D | 1 |
| C | 2 |
| B | 3 |
| A | 4 |
| S | 5 |
| SS | 6 |

`CharacterProgression::normalize()` mantiene coherencia si falta algún campo.

### 4.3 character_level (escala 1–50)

Usado para **requisitos de oficios y disciplinas**. Si no existe en `data_json`, se infiere del `nivel`:

| nivel | character_level por defecto |
|-------|----------------------------|
| 1 | 1 |
| 2 | 10 |
| 3 | 20 |
| 4 | 30 |
| 5 | 40 |
| 6 | 50 |

Staff puede fijar `character_level` explícito para casos especiales.

---

## 5. PP — Puntos de Progresión

### 5.1 Qué son y dónde viven

**PP** = moneda para subir un stat un rango. Vive en `data_json.pp`, con sub-track `pp_linaje` para la parte otorgada por linaje al crear.

El servidor es la única fuente de verdad: `CharacterSaveService` bloquea que el cliente envíe PP en creación.

### 5.2 Cómo se obtienen PP

| Fuente | Mecanismo |
|--------|-----------|
| Linaje al crear | `linaje.bonusPP` → `syncLinajeBonusPp` al cargar ficha |
| Staff / eventos | Edición directa de `data_json` |
| Recompensas narrativas | Según implementación del staff (no hay endpoint automático de misiones en núcleo) |

### 5.3 Cuánto cuesta subir un stat

Coste **base** por salto (de rango N a N+1):

| De → A | PP base |
|--------|---------|
| 1→2 (D→C) | 50 |
| 2→3 | 130 |
| 3→4 | 350 |
| 4→5 | 800 |
| 5→6 (S→SS) | 1800 |

**Multiplicador por rango global actual** del personaje:

| RG | Mult |
|----|------|
| D | 1.00 |
| C | 1.07 |
| B | 1.15 |
| A | 1.35 |
| S | 1.60 |
| SS | 2.00 |

Coste final = `round(base × mult)`. A mayor poder global, subir stats cuesta más.

### 5.4 Flujo de compra (jugador)

1. Abre ficha propia aprobada → tab gestión
2. Elige stat a subir
3. `purchase_attribute.php` recibe `{ character_id, stat }`
4. Servidor: `validateStatUpgrade` → comprueba stat < 6, PP suficientes
5. `applyStatUpgrade`: resta PP (primero de `pp_linaje` si aplica), incrementa stat, recalcula rank/nivel
6. Persiste `data_json` + `stats_json`

Si el PJ no está `aprobada`, la compra falla.

### 5.5 UI de progresión

`CharacterSheetLoader` + `CharacterProgression::snapshot` exponen a la ficha:

- PP disponibles, rank, nivel, suma de rangos
- `next_upgrade_costs` — coste del siguiente salto por cada stat

---

## 6. Oficios

### 6.1 Qué son

Profesiones IC: navegante, médico, cocinero, herrero, científico… Representan habilidad **fuera o alrededor** del combate directo (navegación, crafteo, sanación narrativa).

### 6.2 Modelo de datos

- **`game_oficios`** — catálogo (`slug`, `name`, `description`, `category`, `icon`)
- **`game_character_oficios`** — `(character_id, oficio_id, rank)` donde rank es 1–5 = grados I–V

### 6.3 Catálogo inicial (seed)

| slug | Nombre |
|------|--------|
| navegante | Navegante |
| herrero | Herrero |
| medico | Médico |
| cocinero | Cocinero |
| cientifico | Científico |

Staff puede añadir más en `zona_staff_oficios.php`.

### 6.4 Cómo se asigna al crear PJ

El wizard envía `occupation`. `game_oficio_assign_initial_from_job` mapea texto → slug (ej. «Timonel» → `navegante`) y crea fila con **grado I** (rank=1).

### 6.5 Bono mecánico por grado

`game_oficio_rank_bonus(rank)` = `rank × 0.5` (máx 2.5 en grado V). Se expone en API; la integración en tiradas depende del contexto narrativo.

### 6.6 Subir de grado — reglas

Compartidas con disciplinas (`grado_helpers.php`):

| Grado | Nivel personaje requerido | Precio berries (orientativo) |
|-------|---------------------------|------------------------------|
| I | 1 | — |
| II | 10 | 2 500 |
| III | 20 | 7 500 |
| IV | 30 | 18 000 |
| V | 50 | 45 000 |

**Cooldown global:** 14 días entre subidas de cualquier competencia, tracked en `data_json.grado_last_upgrade_at`.

La ficha consulta `character_competencias_get.php`, que enriquece cada oficio con `upgrade.available`, razones si no puede, precio, etc.

### 6.7 Quién puede editar grados

Asignación y cambios de rank: **staff nivel 2+** vía `character_oficios_save.php`. El jugador ve sus oficios en la ficha pero no los edita directamente.

### 6.8 Efecto en navegación

Oficio **navegante** aporta `navigator_bonus` en viajes marítimos (`game_navigation_voyages`).

---

## 7. Disciplinas

### 7.1 Qué son

Estilos de **combate**: cuerpo a cuerpo, espadas, armas de fuego, escudo, etc. Misma escala de grados I–V que oficios pero orientada a pelear.

### 7.2 Modelo de datos

- **`game_disciplinas`** — catálogo
- **`game_character_disciplinas`** — grado por personaje

### 7.3 Catálogo inicial (seed)

| slug | Nombre |
|------|--------|
| cuerpo_a_cuerpo | Cuerpo a Cuerpo |
| armas_de_filo | Armas de Filo |
| armas_de_asta | Armas de Asta |
| armas_contundentes | Armas Contundentes |
| armas_a_distancia | Armas a Distancia |
| armas_de_fuego | Armas de Fuego |
| armas_exoticas | Armas Exóticas |
| escudo | Escudo |

### 7.4 Asignación al crear PJ

Campo `disciplina` o `arquetipo` del wizard → `game_disciplina_assign_initial` busca por nombre en catálogo → grado I.

### 7.5 Relación con cartas

Las cartas `tecnica` no tienen FK a disciplina. La relación es **narrativa y por tags**: una carta «Corte de espada» llevará tags de filo; el grado en `armas_de_filo` es lo que el staff/jugador usa como referencia de maestría.

### 7.6 Gestión

Staff: `zona_staff_disciplinas.php`, `character_disciplinas_save.php`. Mismas reglas de nivel, berries y cooldown que oficios.

---

## 8. Grados I–V — sistema compartido

| rank en BD | Etiqueta | Bono ×0.5 |
|------------|----------|-----------|
| 1 | I | 0.5 |
| 2 | II | 1.0 |
| 3 | III | 1.5 |
| 4 | IV | 2.0 |
| 5 | V | 2.5 |

Funciones en `grado_helpers.php`:

- `game_grado_label`, `game_grado_bonus`
- `game_grado_nivel_required`, `game_grado_upgrade_price`
- `game_grado_cooldown_ok`, `game_grado_cooldown_remaining_days`
- `game_grado_enrich_row` — añade bloque `upgrade` a cada fila para la UI (esto es subida de **oficio/disciplina**, no de cartas)

---

## 9. Linaje e interacción con stats/PP

### 9.1 Estructura típica `data_json.linaje`

```json
{
  "version": 2,
  "bonusPP": 50,
  "pasivas": ["p_hum_adapt"],
  "racial": ["lr_hum_tenaz"]
}
```

### 9.2 Efectos

- **bonusPP**: puntos gratis al inicio; tracked en `pp_linaje`
- **stat_bonuses** de raza: modifican efectivos, no el gasto de PP en entrenamiento
- **Pasivas/opciones raciales**: reglas narrativas en JSON; algunas referencian Haki u oficios

`LinajeValidator` valida en creación. `CharacterProgression::syncLinajeBonusPp` corrige desincronizaciones al abrir la ficha.

---

## 10. Migración desde stats legacy

`migrate_stats_v7.php` convierte valores 1–20 a rangos 1–6. Mapeo aproximado:

| Valor antiguo | Rango nuevo |
|---------------|-------------|
| 1–3 | 1 |
| 4–6 | 2 |
| 7–10 | 3 |
| 11–14 | 4 |
| 15–18 | 5 |
| 19–20 | 6 |

El manual en `manual_secciones/creacion.php` aún describe 6 stats y 300 puntos — **obsoleto** respecto al código v7.

---

## 11. Flujo mental de progresión

```
Crear PJ → stats iniciales + linaje (bonus PP)
    ↓
Aprobación → puede gastar PP en stats (purchase_attribute)
    ↓
Cada subida de stat → puede cambiar rango global y nivel
    ↓
nivel / character_level desbloquean grados II–V en oficios/disciplinas
    ↓
Subir grado (staff + berries + cooldown) → mejor bono de competencia
    ↓
En paralelo: staff asigna cartas; el jugador las usa en posts
```

Stats y competencias **no** suben automáticamente por postear; PP y grados son sistemas explícitos.

---

## 12. Archivos clave

| Archivo | Rol |
|---------|-----|
| `src/Shared/StatScale.php` | Constantes y fórmulas |
| `src/Application/Services/CharacterProgression.php` | PP y subidas de stat |
| `inc/stat_helpers.php` | Contexto runtime de stats |
| `inc/grado_helpers.php` | Reglas I–V compartidas |
| `inc/oficios_helpers.php` | CRUD oficios |
| `inc/disciplinas_helpers.php` | CRUD disciplinas |
| `ajax/purchase_attribute.php` | Compra de stat |
| `ajax/character_competencias_get.php` | Vista unificada oficios+disciplinas |
| `data/linaje_catalog.json` | Razas |

---

## 13. Para IAs

1. Leer stats con `StatScale::sanitizeRanks`.
2. Rango global = suma de entrenados, no efectivos.
3. Haki y cartas usan ESP **efectivo** — ver guía de cartas.
4. `upgrade` en `game_grado_enrich_row` = subida de oficio/disciplina, **no** cartas.
5. No confundir `nivel` (1–6) con `character_level` (1–50).
