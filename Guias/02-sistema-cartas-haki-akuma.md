# Sistema de Cartas — Haki y Akuma no Mi

> Referencia para IAs. Describe **cómo funciona** el módulo de cartas en `back/forum/game/`, con foco en Haki y Akuma.  
> Complementa `01-sistema-rpg-global.md`.

---

## 1. Qué es una carta en este foro

Una carta no es un objeto suelto: es la **unidad mecánica** que un personaje puede usar al escribir un post de rol. Representa una técnica, un objeto, un poder de fruta, una habilidad de haki, un compañero menor o un barco.

El sistema separa tres momentos:

1. **Definición** — qué es la carta en abstracto (catálogo staff).
2. **Posesión** — qué cartas tiene cada personaje y con qué rango (`current_rank`).
3. **Uso** — qué cartas se jugaron en un post concreto, con qué tirada y si fueron visibles u ocultas.

Esa separación permite que el staff cree cartas una vez, las asigne a varios PJs, y cada jugada quede registrada por post sin alterar la definición original.

---

## 2. Las tres capas de datos

### 2.1 Catálogo — `game_cards`

Aquí vive la «plantilla» de cada carta. El staff (nivel 3) la crea en `cartas_staff.php`. Campos que importan:

| Campo | Función |
|-------|---------|
| `name`, `description`, `image_url` | Presentación |
| `card_type` | Comportamiento base (ver §3) |
| `rank` | Rango de la carta en catálogo: C, B, A, S, SS |
| `activation` | activa / pasiva / reactiva (sobre todo técnicas) |
| `tags_json` | Lista de etiquetas mecánicas (ver §4) |
| `cost_pe`, `execution_cost` | Coste de energía al usar |
| `execution_stat` | Stat que escala el dado (`fue`, `des`, etc.) |
| `dice` | Fórmula de tirada, ej. `2d20+fue [Daño]` |
| `effects_json` | Payload según tipo (Haki, Akuma, equipo…) |
| `reposo`, `duracion` | Cooldown y duración en turnos |
| `peso` | Peso en capacidad de carga (equipo) |
| `cost_berries`, `in_shop`, `shop_category` | Economía (equipo, npc, barco) |

**No existe `upgrade_json`.** El rango de una carta en el mazo se fija al asignarla o la cambia el staff directamente; el jugador **no** puede pedir subida de rango.

### 2.2 Mazo — `game_character_cards`

Relación personaje ↔ carta:

- `current_rank` — rango efectivo de esa copia en el mazo (puede diferir del catálogo si staff lo actualizó).
- `cantidad` — stacks para consumibles/munición.
- `assigned_by` — quién la dio.

Un personaje solo puede jugar cartas que estén aquí (salvo flujos especiales de post que el plugin valide).

### 2.3 Jugada — `game_post_cards`

Cuando el autor publica un post con deck:

- Se guarda `played_rank` (rango en el momento de jugar).
- Si la carta tiene `dice`, el plugin evalúa la fórmula con los stats del PJ y guarda `roll_result`.
- `hidden_action_index` — 0 = carta visible; >0 = ligada a una acción oculta (spoiler).

### 2.4 Equipamiento — `game_character_inventory`

Algunas cartas deben estar **equipadas** para poder jugarse en un post:

| Tipo | Slot |
|------|------|
| `equipo` (arma/armadura, no consumible) | `carga` |
| `npc_menor` | `companero` |
| `barco` | `barco` |

Los consumibles (`equipo` tipo `util` o tags CONSUMIBLE/MUNICION) no requieren equipar. El plugin comprueba esto en `game_postcharacter_card_allowed_in_post`.

---

## 3. Tipos de carta (`card_type`)

| Tipo | Qué representa | Tienda | Equipar |
|------|----------------|--------|---------|
| `tecnica` | Ataque o habilidad de combate con dados | No | No |
| `equipo` | Arma, armadura o consumible | Sí | Arma/armadura sí |
| `akuma_no_mi` | Poder de fruta del diablo en el mazo | No | No |
| `haki` | Técnica de haki | No | No |
| `npc_menor` | NPC o mascota | Sí | Sí (`companero`) |
| `barco` | Embarcación | Sí | Sí (`barco`) |

---

## 4. Catálogo completo de tags

Fuente: `jscripts/game/cartas_staff.js` → `TAG_CATEGORIES`. El staff los selecciona al crear/editar cartas; se guardan en `tags_json` como array de strings.

### Activación y temporalidad
`ACTIVA` · `PASIVA` · `REACTIVA` · `CONTINUA` · `INSTANTÁNEA` · `CARGA` · `CANAL` · `RETRASADA` · `ENCADENABLE` · `UNA VEZ` · `COOLDOWN X`

### Alcance y geometría
`CONTACTO` · `CUERPO A CUERPO` · `DISTANCIA CORTA` · `DISTANCIA MEDIA` · `DISTANCIA LARGA` · `AUTOPERSONAL` · `ALIADOS` · `ÁREA PEQUEÑA` · `ÁREA MEDIA` · `ÁREA GRANDE` · `LÍNEA` · `CONO` · `ANILLO` · `TRAYECTORIA` · `TOQUE` · `GLOBAL`

### Función de combate
`OFENSIVA` · `DEFENSIVA` · `CONTROL` · `SOPORTE` · `MOVILIDAD` · `CURACIÓN` · `UTILIDAD` · `INTERRUPCIÓN` · `PENETRACIÓN` · `DESVÍO` · `ABSORCIÓN` · `SEÑUELO` · `ESCUDO`

### Ejecución (stat que escala)
`EJECUCIÓN: FUE` · `EJECUCIÓN: AGI` · `EJECUCIÓN: DES` · `EJECUCIÓN: INST` · `EJECUCIÓN: ESP` · `EJECUCIÓN: INT`

### Tipo de daño
`DAÑO FÍSICO` · `DAÑO CORTANTE` · `DAÑO CONTUNDENTE` · `DAÑO PERFORANTE` · `DAÑO ÍGNEO` · `DAÑO CRIOGÉNICO` · `DAÑO ELÉCTRICO` · `DAÑO TÓXICO` · `DAÑO EXPLOSIVO` · `DAÑO INTERNO` · `DAÑO ESPIRITUAL` · `DAÑO ESTRUCTURAL` · `DAÑO OSCURO`

### Interacción especial
`ANTI-LOGIA` · `ANTI-HAKI` · `KAIROSEKI` · `IGNORA ARMADURA` · `DOBLE DAÑO EMPAPADO` · `VULNERABILIDAD AGUA` · `ESCALA CON DAÑO RECIBIDO` · `ESCALA CON PE RESTANTE` · `ESCALA CON ALIADOS` · `BONUS VS DERRIBADO` · `BONUS VS ESTADO` · `ENCADENADO CON` · `ROMPE CONCENTRACIÓN`

### Elemento / naturaleza
`FUEGO` · `HIELO` · `RAYO` · `VENENO` · `OSCURIDAD` · `LUZ` · `VIENTO` · `TIERRA` · `AGUA` · `HUMO` · `ARENA` · `VIBRACIÓN` · `SONIDO` · `GRAVEDAD` · `VACÍO`

### Akuma no Mi (solo tres clases)
`LOGIA` · `PARAMECIA` · `ZOAN`

### Haki
`HAKI ARMAMENTO` · `HAKI OBSERVACIÓN` · `HAKI REY` · `FLUJO AVANZADO` · `VISIÓN DE FUTURO` · `EMISIÓN DE REY`

### Equipo
`ARMA` · `ARMA SECUNDARIA` · `ARMA ARROJADIZA` · `ARMADURA` · `ARMADURA PARCIAL` · `ACCESORIO` · `CONSUMIBLE` · `NAVE` · `KAIROSEKI INTEGRADO` · `GRADO MEITO` · `MODIFICABLE`

### NPC
`PIRATA` · `MARINO` · `REVOLUCIONARIO` · `CIVIL` · `AGENTE CIPHER POL` · `BOUNTY HUNTER` · `ALIADO TEMPORAL` · `OBSTÁCULO` · `JEFE DE ESCENA`

### Condición y restricción
`REQUIERE ARMA` · `REQUIERE AKUMA NO MI` · `REQUIERE HAKI` · `REQUIERE ESTADO PROPIO` · `REQUIERE ESTADO OBJETIVO` · `SOLO EN AGUA` · `SOLO EN TIERRA` · `SOLO FORMA HÍBRIDA` · `SOLO FORMA BESTIAL` · `CONSUMO DOBLE EMPAPADO` · `AUTO-DAÑO`

Los tags son **metadatos declarativos**: el motor no ejecuta automáticamente todos; sirven para filtrar, documentar y guiar al narrador/jugador. Los dados y `effects_json` son lo que el código evalúa en runtime.

---

## 5. Cómo se juega una carta en un post (flujo completo)

```
Jugador escribe post en MyBB
    ↓
Editor de deck (foro_deck_ui / plantillas) envía IDs de cartas + opciones
    ↓
Plugin game_postcharacter_save_post (hook insert post)
    ↓
Para cada carta:
  1. ¿Está en game_character_cards del PJ activo?
  2. ¿Cumple regla de equipamiento?
  3. ¿Tipo equipo arma? → puede inyectar execution_stat en la fórmula del dado
  4. ¿Tiene dice? → game_evaluate_dice_roll(stats del PJ, modificadores del post)
  5. INSERT en game_post_cards
  6. ¿Consumible? → cantidad-- en game_character_cards
    ↓
Lectores abren el hilo → cards_for_post.php devuelve cartas + PV/PE + oráculos
```

**PJ activo**: `game_user_config.active_pj_id` del autor del post. Sin PJ activo, el plugin no vincula mecánicas.

**Modificadores de post**: `pv_change`, `pe_change`, `modifiers_json` en `game_post_characters` actualizan `game_thread_pj_state` para ese hilo.

**Acciones ocultas**: el jugador puede agrupar cartas bajo un índice oculto; solo el dueño del PJ ve el contenido hasta revelar (`reveal_hidden_action.php`) o usar spoiler BBCode.

---

## 6. Gestión staff del catálogo

### Crear / editar

- Página: `game/public/cartas_staff.php`
- JS: `cartas_staff.js` monta `effects_json` según el tipo y envía a `cards_create.php` / `cards_update.php`
- Requiere `staff_level >= 3` en el PJ activo del usuario staff

Al cambiar tipo en el editor, se muestran u ocultan campos (Akuma y Haki ocultan dado, PE, activación por defecto).

### Asignar a personaje

`cards_assign.php`:

- Copia el `rank` del catálogo a `current_rank`
- Para **Haki**: valida ESP efectivo antes de asignar (§7)
- Consumibles: acepta `cantidad` para el stack

El staff puede cambiar `current_rank` manualmente en BD o reasignando; no hay petición de jugador para subir rango.

---

## 7. Cartas Haki — funcionamiento detallado

### 7.1 Qué modela

Una carta `haki` representa **una técnica concreta** de uno de los tres tipos canónicos:

| `haki_type` en effects | Nombre |
|------------------------|--------|
| `busoshoku` | Armamento |
| `kenbunshoku` | Observación |
| `haoshoku` | Conquistador / Rey |

### 7.2 Estructura `effects_json`

```json
{
  "haki_type": "busoshoku",
  "haki_level": "basico",
  "efecto": "Texto libre con la mecánica narrativa o numérica"
}
```

El campo `efecto` es donde el staff describe bonos, alcance, etc. El manual del foro (`manual_secciones/haki.php`) tiene tablas narrativas (defensa +10, evasión +30…); **eso no se calcula solo en PHP** — vive en la carta y en el rol.

### 7.3 Requisito de ESP al asignar

`cards_assign.php` calcula el **rango ESP efectivo** (entrenado + bono racial) y lo compara con `StatScale::minEspRankForHaki`:

| Tipo | Nivel (`haki_level`) | ESP mínimo (rango) |
|------|----------------------|---------------------|
| kenbunshoku | basico | C (2) |
| kenbunshoku | avanzado | A (4) |
| busoshoku | basico | B (3) |
| busoshoku | interno | A (4) |
| busoshoku | supremo | S (5) |
| busoshoku | fusion | SS (6) |
| haoshoku | pasivo | S (5) |
| haoshoku | ofensivo | SS (6) |

Si el PJ no alcanza el umbral, la asignación falla con 403.

**Importante**: el selector del editor staff lista niveles genéricos (`despertado`, `basico`, `medio`…). Para que la validación funcione, el staff debe usar los valores del mapa anterior en `haki_level`, o la asignación exigirá ESP 99 (valor por defecto para niveles desconocidos).

### 7.4 En combate (post)

- No requiere equipar
- Puede llevar `dice` y `cost_pe` si el staff los configura
- Tags `HAKI ARMAMENTO`, `HAKI OBSERVACIÓN`, `HAKI REY` ayudan a clasificar
- El stat relevante del personaje es **ESP** (`esp`) en la escala v7

### 7.5 Linaje y Haki

Raza **Buccaner** tiene pasiva narrativa de afinidad con haki. Opciones de linaje en ficha (`g_haki_obs`, `g_haki_arm`, `g_haki_conq`) son elecciones de creación, no cartas automáticas.

---

## 8. Cartas Akuma no Mi — funcionamiento detallado

### 8.1 Dos sistemas relacionados

| Sistema | Tabla | Rol |
|---------|-------|-----|
| **Biblioteca de frutas** | `game_akuma_no_mi` | Inventario del mundo: qué frutas existen, quién las tiene, si están libres |
| **Carta en mazo** | `game_cards` + `game_character_cards` | Cómo se **juega** el poder en posts |

Un PJ puede tener carta Akuma sin que la fila de biblioteca esté perfectamente sincronizada, pero en la práctica el flujo staff suele: aprobar petición → ocupar fruta en biblioteca → crear/asignar carta `akuma_no_mi`.

### 8.2 Clases, subtipo y tier

En el editor de cartas y en `effects_json`:

| `akuma_type` | Clase |
|--------------|-------|
| `paramecia` | Paramecia |
| `logia` | Logia |
| `zoan` | Zoan |

| `subtipo` | Uso |
|-----------|-----|
| `ninguno` | Paramecia, Logia y Zoan estándar |
| `antiguo` | Zoan antiguo |
| `mitico` | Zoan mítica |

**Tier 1–5** en columna `game_cards.tier` y en `game_akuma_no_mi.tier`. Escala, requisitos ESP/nivel y guía de creación: **`04-sistema-akuma-estructura-tier.md`**.

Tags de carta para Akuma: **LOGIA**, **PARAMECIA**, **ZOAN** (+ tags mecánicos según §4).

### 8.3 Estructura `effects_json` (v2 ampliada)

Estructura completa documentada en `04-sistema-akuma-estructura-tier.md`. Resumen:

- `identidad`, `pasivas[]`, `transformaciones[]` (Zoan), `capacidades_base[]`
- `inmunidades[]`, `debilidades` (universales + `especificas[]`)
- `reglas_especiales[]`, `potencial_despertar`, `referencia_tecnicas`

El editor staff usa un textarea JSON para la estructura ampliada; al guardar, `akuma_helpers.php` normaliza y migra cartas legacy (`efectos`/`limitaciones`/`debilidades` en texto).

En el editor, Akuma **oculta** dado/PE/activación; `activation` = `pasiva`, `cost_pe` = 0. `cards_assign.php` exige ESP y nivel mínimos según tier (`StatScale::minEspRankForAkumaTier`).

### 8.4 Debilidades universales (diseño)

- No nadar; debilidad en agua de mar
- Kairoseki anula poderes
- Haki de armadura golpea cuerpos logia
- Tags: `VULNERABILIDAD AGUA`, `ANTI-LOGIA`, `KAIROSEKI`

### 8.5 Obtener una fruta (peticiones, no carta directa)

Flujo **biblioteca** vía `game_admin_requests`:

1. **Aleatoria** (`peticion_akuma_aleatoria.php` → `akuma_roll.php`): elige fruta libre de `game_akuma_no_mi`, la reserva (`is_reserved`), crea petición `source=akuma_random`. Una sola tirada por personaje.
2. **Demanda** (`peticion_akuma_demanda.php`): el jugador pide una fruta concreta.
3. Staff revisa en `peticiones_admin.php` → al aprobar, ocupa fruta y normalmente asigna carta al mazo.

Esto es independiente de las **peticiones de cartas** (`game_card_requests`), que solo permiten: **borrar**, **crear propuesta**, **añadir existente** — nunca subir rango.

### 8.6 Biblioteca `game_akuma_no_mi`

Campos útiles: `name`, `class`/`class_name`, `desc`, `usuario_actual`, `is_occupied`, `is_reserved`, `power_range`, `status`, **`tier`**, **`subtipo`**.

Página pública: `akuma_no_mi.php`. Catálogo AJAX: `akuma_catalog.php`.

---

## 9. Peticiones de cartas (jugador → staff)

Tabla `game_card_requests`. Tipos **válidos**:

| `request_type` | Qué pide el jugador | Quién resuelve |
|----------------|---------------------|----------------|
| `delete` | Quitar carta de su mazo | Mod (2+) aprueba → borra fila en `game_character_cards` |
| `create` | Proponer carta nueva (nombre + descripción + tipo) | Admin (3+) modera, crea en catálogo y asigna |
| `add_existing` | Pedir carta ya existente en catálogo | Admin (3+) asigna al mazo |

**No existe `upgrade`.** El jugador no puede solicitar subir el rango de una carta.

Flujo típico `create`:

1. Jugador envía desde ficha (`cards_request_custom.php`) con borrador en `card_details_json`
2. Hilo de discusión en `discussion_json` (mensajes jugador/staff)
3. Staff usa `moderate` / `approve` / `reject` en `cards_resolve_request.php`
4. Notificación al jugador vía `game_create_notification`

Borrado: `cards_request_action.php` solo acepta `action=delete`.

---

## 10. Tienda y economía de cartas

- Solo `equipo`, `npc_menor`, `barco` con `cost_berries > 0`
- `tienda_comprar.php` descuenta `berries` del personaje e inserta en `game_character_cards`
- `in_shop` y `shop_category` controlan visibilidad (`utiles`, `naval`, `mascotas`)

---

## 11. Otros tipos (resumen operativo)

### `tecnica`
Dado + stat + activación + reposo. Corazón del combate con tiradas automáticas.

### `equipo`
- `arma`: escala daño con `execution_stat`; puede requerir equipar en slot `carga`
- `armadura`: equipar; peso en CC
- `util`: consumible con `cantidad`; se gasta al jugar

### `npc_menor`
Acciones en `effects.acciones`; mascotas eligen acción al jugar. Tier solo para mascotas.

### `barco`
Stats náuticos + bonuses por zona (`nav_bonus_grand_line`, etc.). Necesario para viajes (`game_navigation_voyages`).

---

## 12. Archivos clave

| Archivo | Rol |
|---------|-----|
| `public/cartas_staff.php` | UI catálogo |
| `jscripts/game/cartas_staff.js` | Editor, tags, effects_json |
| `ajax/cards_*.php` | API cartas |
| `inc/inventory_helpers.php` | Equipamiento |
| `inc/plugins/game_postcharacter.php` | Persistencia en post, dados |
| `src/Shared/StatScale.php` | Requisitos Haki y Akuma tier |
| `inc/akuma_helpers.php` | effects_json ampliado, rank/tier, validación asignación |
| `sql/migrate_akuma_tier.php` | tier/subtipo biblioteca + rango D |
| `sql/migrate_cards_drop_upgrade.php` | Limpieza upgrade obsoleto |

---

## 13. Checklist para IAs

- No implementar ni documentar `upgrade_json` ni peticiones `upgrade`.
- Akuma: `akuma_type` paramecia/logia/zoan; `subtipo` para Zoan; tier 1–5; effects_json ampliado según `04-sistema-akuma-estructura-tier.md`.
- Haki: validar `haki_level` contra `StatScale::minEspRankForHaki` al asignar.
- Tags: usar solo los listados en §4.
- Al cambiar flujo de post, probar consumibles, equipamiento y `cards_for_post.php`.
