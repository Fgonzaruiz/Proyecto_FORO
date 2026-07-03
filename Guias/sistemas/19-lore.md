# 19. Lore — Historia del Mundo

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 19
> **Propósito:** Documentar exhaustivamente el subsistema de Lore del mundo: arquitectura JSON, servicio PHP, catálogo de tipos, las 4 secciones del lore (Eras, Lore Basal, Eventos, Periódicos), la vista pública `historia.php`, el sistema de prompts P-06 para creación desde cero, y la filosofía de diseño de un lore vivo, coherente y generado por el admin sin contenido predefinido.

---

## ÍNDICE

1. [Arquitectura General — Lore sin Base de Datos](#1-arquitectura-general)
2. [Estructura del Archivo `lore.json`](#2-estructura-del-archivo-lorejson)
3. [LoreService — Clase y Métodos](#3-loreservice)
4. [`lore_types.json` — Catálogo de Tipos](#4-lore_typesjson)
5. [Eras — Periodos Históricos](#5-eras)
6. [Lore Basal — Entradas Enciclopédicas](#6-lore-basal)
7. [Eventos — Sucesos Históricos](#7-eventos)
8. [Periódicos — Noticias In-World](#8-periódicos)
9. [`historia.php` — Visor Público de Lore](#9-historiaphp)
10. [Sistema de Prompts P-06](#10-sistema-de-prompts-p-06)
11. [Flujo de Creación de Lore](#11-flujo-de-creación-de-lore)
12. [Consejos para el Admin](#12-consejos-para-el-admin)
13. [Filosofía de Diseño del Sistema de Lore](#13-filosofía-de-diseño)
14. [Guía de Troubleshooting](#14-guía-de-troubleshooting)

---

## 1. Arquitectura General

### 1.1 Decisión Fundamental: JSON en lugar de Base de Datos

El lore del mundo NO se almacena en MySQL. No hay tabla `game_lore_entries`, ni `game_eras`, ni `game_historical_events`. El lore vive en un único archivo JSON en el sistema de archivos:

```
back/forum/game/lore.json   ← fuente única de verdad (single source of truth)
```

**¿Por qué JSON y no DB?**

| Razón | Explicación |
|-------|-------------|
| **El admin es el único escritor** | El lore no lo escribe la comunidad — lo define el admin vía P-06. No hay concurrencia ni escritura multiusuario. Un archivo JSON es más simple que una tabla con CRUD. |
| **Versionado con git** | `lore.json` se versiona en el repo. Cada cambio de lore es un commit. Se puede revertir, diff, blame. En DB habría que implementar un historial de cambios. |
| **Atomicidad** | El lore completo se carga en un solo `file_get_contents()`. No hay JOINs, no hay consultas. Si el JSON es válido, el lore funciona. |
| **Portabilidad** | Para resetear el lore, se reemplaza el archivo. Para exportarlo, se copia. Sin migraciones SQL. |
| **Rendimiento** | `lore.json` actual ~1168 líneas (~90KB). Se carga en ~5ms. En caché de OPcache, aún menos. Una consulta SQL con JOINs entre 4 tablas sería más lenta. |

**Contrapartida:** El lore NO es consultable vía SQL. No puedes hacer `SELECT * FROM lore WHERE era_id = 2`. Pero en un foro RPG donde el lore se consulta siempre COMPLETO (vista de cronología), esto no es una limitación real.

### 1.2 Capas del Subsistema

```
┌─────────────────────────────────────────────────────────────┐
│                  lore.json (fuente de datos)                 │
│    4 secciones: eras, lore_basal, eventos, periodicos        │
└──────────────────────────┬──────────────────────────────────┘
                           │ file_get_contents()
┌──────────────────────────▼──────────────────────────────────┐
│              LoreService (PHP — Capa de Aplicación)          │
│  obtenerTipos() · obtenerCronologia() · agruparEnFilas()    │
│  Validación · Cache de tipos · Parseo de años               │
│  Ordenación · Agrupación por era · Resolución de tipos      │
└──────────────────────────┬──────────────────────────────────┘
                           │ Array asociativo
┌──────────────────────────▼──────────────────────────────────┐
│              historia.php (Vista Pública)                    │
│  Render de eras, lore_basal, eventos, periódicos            │
│  Modal de lectura · Filtros por era · Búsqueda              │
│  JS: historia.js (interactividad)                           │
└─────────────────────────────────────────────────────────────┘
```

### 1.3 Flujo de Datos

```
1. Usuario visita /game/public/historia.php
2. historia.php → LoreService::obtenerCronologia('lore.json')
3. LoreService lee lore.json + lore_types.json
4. Procesa, ordena, agrupa, resuelve tipos
5. Retorna array normalizado
6. historia.php renderiza HTML con eras, eventos, periódicos
7. JS (historia.js) maneja filtros, búsqueda, modal de lectura
```

**No hay escritura desde el frontend.** El lore es solo lectura para los usuarios. La escritura ocurre offline: el admin edita `lore.json` (directamente o vía P-06 con generación por LLM).

---

## 2. Estructura del Archivo `lore.json`

### 2.1 Esqueleto General

```json
{
    "meta": { ... },
    "eras": [ ... ],
    "lore_basal": [ ... ],
    "eventos": [ ... ],
    "periodicos": [ ... ]
}
```

### 2.2 `meta` — Metadatos del Mundo

```json
{
    "world_name": "Kairan",
    "nota": "Nombre del mundo. Explicación etimológica y contexto.",
    "era_nota": "Era IV — El Alba Rota. Descripción del período actual del foro.",
    "tipo": "parche_correcciones",
    "instruccion": "Instrucciones para el LLM al aplicar este parche.",
    "cambios_resumidos": [
        "ROTO-1: Creada LB#23 — Draven Maris",
        "ROTO-2: Voz resuelta como fenómeno cíclico"
    ],
    "correcciones_resumen": [
        "ROTO-1: Creado LB#23 con entry propio"
    ]
}
```

**Filosofía de `meta`:**
- `world_name` — nombre canónico del mundo. Se muestra en la UI.
- `nota` — explicación para el admin/LLM sobre el origen del nombre y contexto.
- `era_nota` — descripción breve de la era actual del foro (en qué año está el mundo ahora).
- `tipo` — `inicial`, `parche_correcciones`, `expansion`. Controla cómo el LLM debe procesar el JSON.
- `instruccion` — instrucciones para el LLM al aplicar cambios (ej: "REEMPLAZA por id en lore_basal y eventos, AÑADE los que tienen id nuevo").
- `cambios_resumidos` / `correcciones_resumen` — log de cambios para trazabilidad.

### 2.3 ID y Referencias Cruzadas

Cada entrada en `lore_basal`, `eventos` y `periodicos` tiene un `id` entero único. Las referencias cruzadas se hacen mediante enlaces HTML:

```html
<a href='#' class='rpg-lore-link' data-lore-id='7'>Familia Draven</a>
```

Esto permite:
- Navegación entre entradas de lore dentro del modal de lectura.
- El JS detecta clicks en `.rpg-lore-link` y abre la entrada referenciada.
- No hay URLs reales — es single-page navigation dentro del visor.

---

## 3. LoreService

### 3.1 Archivo y Namespace

```
Archivo:   game/src/Application/Services/LoreService.php
Namespace: Game\Application\Services
```

### 3.2 Métodos Públicos

#### `obtenerTipos(): array`

Lee y cachea `lore_types.json`. Retorna el catálogo completo de tipos de eventos y subtipos de lore basal.

```php
public static function obtenerTipos(): array {
    // Cache estática: self::$tiposCache
    // Lee: dirname(__DIR__, 2) . '/Config/lore_types.json'
    // Lanza Exception si no existe o JSON inválido
}
```

**Cache:** El resultado se almacena en `self::$tiposCache` para evitar releer el archivo en cada carga de página. Se destruye al finalizar la request (como toda cache estática en PHP).

#### `obtenerCronologia(string $jsonPath): array`

Método principal. Lee `lore.json`, procesa y estructura todo el lore cronológicamente.

```php
public static function obtenerCronologia(string $jsonPath): array
```

**Procesamiento interno:**

1. Lee y decodifica `lore.json`.
2. Carga `obtenerTipos()` y construye `$eventTypesIndex` (mapa `id → label`).
3. Indexa eras por `id` en un array asociativo.
4. **Lore Basal:** Asigna cada entrada a su era extrayendo el año mínimo del texto con regex `año (\d+)`.
5. **Eventos:** Asigna cada evento a su era, resuelve `type → type_name` usando el catálogo.
6. **Periódicos:** Extrae año de `date` con regex `Año (\d+)`, ordena por año.
7. **Ordenación:** Eventos y lore basal se ordenan por año dentro de cada era.
8. **Agrupación visual:** Eventos se procesan con `agruparEnFilas()` para render en columnas paralelas.
9. Retorna estructura final con `meta`, `tipos`, `eras`, `periodicos`.

**Retorno:**

```php
[
    'meta'  => $data['meta'] ?? [],       // Metadatos del mundo
    'tipos' => $tipos,                     // Catálogo completo de tipos
    'eras'  => array_values($eras),        // Eras con lore_basal, event_rows, periodicos embebidos
    'periodicos' => $periodicosFinal,      // Periódicos ordenados por año
]
```

#### `agruparEnFilas(array $eventos): array` (privado)

Agrupa eventos solapados en el tiempo en filas paralelas para render visual:

```php
private static function agruparEnFilas(array $eventos): array
```

**Algoritmo:**
1. Para cada evento, intenta añadirlo a una fila existente donde no solape con ningún evento ya en esa fila.
2. Si no hay fila compatible, crea una nueva fila.
3. Dos eventos solapan si `ev1.start ≤ ev2.end AND ev1.end ≥ ev2.start`.

**Retorno:**

```php
[
    ['events' => [EventoA, EventoB], 'is_overlap' => true],   // Fila 1: solapan entre sí
    ['events' => [EventoC],         'is_overlap' => false],  // Fila 2: evento único
]
```

### 3.3 Mapa de Tipos (TypeMap)

El array `self::$typeMap` permite mapear tipos antiguos/alternativos a tipos actuales del catálogo. Si un tipo no existe en el catálogo:
- Busca en `$typeMap` para migración.
- Si no encuentra, asigna `'otro'` y loguea warning con `error_log()`.

### 3.4 Filosofía del LoreService

**¿Por qué métodos estáticos?**
- LoreService no tiene estado interno (la cache es una optimización, no estado).
- No necesita ser instanciado con dependencias. Lee archivos directamente.
- Simplicidad: `LoreService::obtenerCronologia($path)` desde cualquier vista.

**¿Por qué el año se extrae con regex en lugar de tener campo `start_year` en lore_basal?**
- La fecha está en el texto narrativo (`details`), no en un campo separado. El admin escribe en lenguaje natural ("año 145").
- Extraer con regex evita duplicar datos (tener el año en el texto y en un campo separado que podría desincronizarse).
- Limitación: si el texto no menciona "año X", se asigna `start_year = 9999` y se va al final.

**¿Por qué `agruparEnFilas()` para eventos?**
- Para el render visual en columnas paralelas. Si dos eventos ocurren en el mismo año, se muestran en columnas separadas (como una línea de tiempo con ramas).
- El flag `is_overlap` permite al frontend decidir cómo renderizar (estilo grid vs lista).

---

## 4. `lore_types.json`

### 4.1 Archivo

```
game/src/Config/lore_types.json
```

### 4.2 Estructura

```json
{
  "event_types": [
    { "id": "guerra",          "label": "Guerra / Conflicto" },
    { "id": "legendario",      "label": "Figura Legendaria" },
    { "id": "tratado",         "label": "Tratado / Pacto" },
    { "id": "descubrimiento",  "label": "Descubrimiento" },
    { "id": "catastrofe",      "label": "Catástrofe / Desastre" },
    { "id": "poder",           "label": "Cambio de Poder" },
    { "id": "fundacion",       "label": "Fundación" },
    { "id": "traicion",        "label": "Traición / Conspiración" },
    { "id": "expedicion",      "label": "Expedición / Viaje" },
    { "id": "revolucion",      "label": "Revolución / Rebelión" },
    { "id": "profecia",        "label": "Profecía / Revelación" },
    { "id": "muerte",          "label": "Muerte Notable" },
    { "id": "artefacto",       "label": "Artefacto / Reliquia" },
    { "id": "alianza",         "label": "Alianza" },
    { "id": "invasion",        "label": "Invasión / Conquista" },
    { "id": "lore",            "label": "Lore / Conocimiento" },
    { "id": "exterminio",      "label": "Exterminio / Genocidio" },
    { "id": "politica",        "label": "Política / Gobierno" },
    { "id": "militar",         "label": "Militar / Armada" },
    { "id": "rebelion",        "label": "Rebelión" }
  ],
  "lore_subtypes": [
    { "id": "sistema_poderes",    "label": "Sistema de Poderes" },
    { "id": "faccion",            "label": "Facción / Institución" },
    { "id": "historia_prohibida", "label": "Historia Prohibida" },
    { "id": "geografia",          "label": "Geografía / Lugar" },
    { "id": "cultura",            "label": "Cultura / Tradición" },
    { "id": "economia",           "label": "Economía / Comercio" },
    { "id": "raza",               "label": "Raza / Especie" },
    { "id": "magia_ciencia",      "label": "Magia / Tecnología" },
    { "id": "religion",           "label": "Religión / Mitología" },
    { "id": "idioma",             "label": "Idioma / Escritura" },
    { "id": "bestia",             "label": "Criatura / Bestia" },
    { "id": "objeto",             "label": "Objeto / Artefacto" },
    { "id": "fenomeno_natural",   "label": "Fenómeno Natural / Anomalía" },
    { "id": "personaje_historico","label": "Personaje Histórico" },
    { "id": "organizacion_secreta","label": "Organización Secreta" },
    { "id": "artefacto_legendario","label": "Artefacto Legendario" },
    { "id": "geografia_mitica",   "label": "Geografía Mítica" }
  ]
}
```

### 4.3 Propósito y Filosofía

**`event_types`** — Clasifica los eventos históricos:
- Se usa para el badge visual en la UI ("Guerra", "Fundación", "Traición").
- Determina el color/icono en el render.
- El campo `type` en cada evento DEBE coincidir con uno de estos `id`.

**`lore_subtypes`** — Clasifica las entradas de lore basal:
- No se usa actualmente en el render (sin badge visual), pero está preparado para futura expansión.
- Permite filtrar lore por categoría.
- Facilita la creación de lore: el admin sabe qué subtipos existen y los usa como guía.

**Relación con P-06:** El prompt P-06 referencia este catálogo para que el LLM genere lore coherente con la taxonomía existente. Por ejemplo: "genera 5 entradas de lore basal de subtipo `faccion` para la Era I".

---

## 5. Eras

### 5.1 Estructura JSON

```json
{
    "id": 1,
    "numeral": "I",
    "name": "La Era de los Cuatro Altares",
    "start_year": 0,
    "end_year": 197,
    "intro_quote": "Había cuatro voces en el viento...",
    "intro_text": "Durante casi doscientos años..."
}
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | int | Único, correlativo. Usado como FK en `era_id` de lore_basal y eventos. |
| `numeral` | string | Romano para display (I, II, III, IV). |
| `name` | string | Nombre de la era, ej: "La Era de los Cuatro Altares". |
| `start_year` | int | Año de inicio. |
| `end_year` | int | Año de fin. |
| `intro_quote` | string | Cita poética que encapsula el tono de la era. |
| `intro_text` | string | Párrafo narrativo extenso que describe la era. |

### 5.2 Filosofía de las Eras

**¿Por qué `numeral` separado de `name`?**
- Porque el numeral es la referencia corta (Era I, Era II) y el name es la referencia larga (La Era de los Cuatro Altares). Ambos se muestran en la UI en distintos contextos.

**¿Por qué `intro_quote`?**
- Porque una cita potente al inicio de cada era establece el tono emocional antes de que el usuario lea el texto denso. Es diseño narrativo: el usuario primero siente, luego entiende.

**¿Por qué `intro_text` tan extenso (3-4 párrafos)?**
- Porque la era necesita contexto. No es solo "años 0-197, período de paz". Es una descripción que explica POR QUÉ esa era fue como fue, QUÉ la definió, QUÉ legado dejó.
- El texto se usa en el modal "Resumen de Era" en `historia.php`.

**Ejemplo de era bien construida:**
- Era I: Federación, libertad, cultos, pacto vivo.
- Era II: Transición, traición, fundación del Gobierno, borrado histórico.
- Era III: Control, resistencia silenciosa, guerras olvidadas, estasis.
- Era IV: Grietas visibles, preguntas incómodas, el momento antes del cambio.

Cada era tiene un ARCO narrativo propio dentro del arco global del mundo.

---

## 6. Lore Basal

### 6.1 Estructura JSON

```json
{
    "id": 1,
    "era_id": 1,
    "name": "La Federación de los Cuatro Altares",
    "subtype": "faccion",
    "desc": "La unión política y espiritual que gobernó Kairan...",
    "details": "<p>Antes de que existiera el Gobierno Mundial...</p>"
}
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | int | Único en todo el lore basal. |
| `era_id` | int | FK a la era a la que pertenece. |
| `name` | string | Título de la entrada. |
| `subtype` | string | Slug del subtipo (del catálogo `lore_subtypes`). |
| `desc` | string | Resumen de 1 frase. Se usa como snippet en listas. |
| `details` | string | HTML con el contenido completo. Párrafos narrativos extensos. |

### 6.2 Filosofía del Lore Basal

**¿Qué entra en lore basal?**
- Civilizaciones, facciones, organizaciones.
- Artefactos legendarios y objetos únicos.
- Geografía (islas, mares, ubicaciones importantes).
- Personajes históricos muertos o vivos (NPCs del mundo).
- Fenómenos naturales o sobrenaturales.
- Sistemas de poder, magia, tecnología.
- Religiones, mitologías, cultos.
- Razas y especies.
- Historia prohibida (lo que el Gobierno borró).
- Linajes familiares importantes.

**¿Qué NO entra en lore basal?**
- Eventos concretos (van en `eventos`).
- Noticias (van en `periodicos`).
- Información de personajes jugadores (va en fichas de personaje).

**Estilo narrativo:**
- Escrito en presente o pasado histórico, como una enciclopedia in-world sesgada.
- El narrador NO es omnisciente. Hay incertidumbre: "los historiadores no se ponen de acuerdo", "se cree que...", "hay tres versiones".
- Las referencias a otras entradas se enlazan con `<a href='#' class='rpg-lore-link' data-lore-id='X'>`.
- Los detalles NO son neutrales. Tienen voz, opinión, misterio.

**Ejemplo de estilo:**
> *Lo que el Gobierno Mundial no sabe — o sabe pero no puede probar — es que los Draven guardaban dos copias de todo lo que transcribían.*

Esto crea un lore que se siente VIVO. No es un dataset. Es una historia que el lector descubre.

### 6.3 Subtipos y su Uso

| Subtipo | Cuándo usarlo | Ejemplo real |
|---------|---------------|--------------|
| `faccion` | Organizaciones, grupos, familias | Familia Solmaren, Familia Draven, Familia Varek |
| `organizacion_secreta` | Grupos ocultos o marginales | Consejo Itinerante, los Gorosei |
| `artefacto_legendario` | Objetos únicos con poder | Raikōmaru, Tōgane, Ōkotoba |
| `personaje_historico` | NPCs del lore muertos o vivos | Vernoa, Ryuken D. Maren, Isolde Vael |
| `historia_prohibida` | Eventos borrados o censurados | El Siglo Vacío, la República de Ohara |
| `fenomeno_natural` | Anomalías del mundo | El Fenómeno de los Nombres (D.), Linaje de Vernoa |
| `geografia_mitica` | Lugares especiales o perdidos | La Isla Sin Viento, el Árbol de las Palabras Perdidas |

---

## 7. Eventos

### 7.1 Estructura JSON

```json
{
    "id": 1,
    "era_id": 1,
    "name": "El Primer Pacto de las Mareas",
    "type": "fundacion",
    "start_year": 1,
    "end_year": 1,
    "desc": "La firma del acuerdo que fundó la Federación...",
    "details": "<p>No hay un documento único...</p>",
    "ubicacion": "Cuatro islas simultáneas — Grand Line, South Blue, East Blue, North Blue",
    "impacto": "El Pacto es el documento fundacional..."
}
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | int | Único en toda la lista de eventos. |
| `era_id` | int | FK a la era. |
| `name` | string | Título del evento. |
| `type` | string | Slug del tipo (del catálogo `event_types`). |
| `start_year` | int | Año de inicio. |
| `end_year` | int | Año de fin (puede ser igual a start si es puntual). |
| `desc` | string | Resumen de 1-2 frases. |
| `details` | string | HTML con el contenido narrativo completo. |
| `ubicacion` | string | Dónde ocurrió. |
| `impacto` | string | Consecuencias del evento. |

### 7.2 Filosofía de los Eventos

**¿Por qué `ubicacion` e `impacto` como campos separados?**
- Porque son datos estructurados que permiten búsqueda y filtrado futuro.
- Porque separan la narrativa (details) de los metadatos (dónde, consecuencias).
- El `impacto` es especialmente útil: responde "¿y esto por qué importa?"

**¿Por qué `start_year` y `end_year`?**
- Un evento puede durar años (una guerra de 50 años, una construcción de 3 años).
- El LoreService usa ambos para ordenar y detectar solapamientos.
- Si es puntual, `start_year = end_year`.

**Tipos de eventos más comunes en el lore real:**
- `fundacion`: Creación de instituciones, firmas de tratados fundacionales.
- `guerra` / `invasion`: Conflictos armados.
- `traicion`: Conspiraciones, traiciones, cambios de bando.
- `descubrimiento`: Hallazgos importantes.
- `catastrofe`: Destrucciones, Buster Calls, desastres.
- `politica`: Decisiones gubernamentales, leyes, decretos.
- `rebelion`: Levantamientos, resistencias.

### 7.3 Agrupación Visual (Event Rows)

El LoreService agrupa eventos solapados en filas paralelas:

```
Fila 1: [Evento A (año 1)]          [Evento C (año 50)]
Fila 2:                [Evento B (año 10-30)]
```

Esto permite renderizar cronologías complejas donde múltiples eventos ocurren simultáneamente. El frontend puede mostrar cada fila como una línea de tiempo independiente.

---

## 8. Periódicos

### 8.1 Estructura JSON

```json
{
    "id": 1,
    "era_id": 1,
    "headline": "EL CONSEJO CELEBRA CIENTO NOVENTA AÑOS DE PAZ...",
    "date": "Año 190 — Edición del Centenario Doble",
    "snippet": "Los Portavoces de los Cuatro Cultos se reunirán...",
    "content": "<p>El Consejo Itinerante de la Federación...</p>",
    "image": ""  // opcional, URL de imagen
}
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | int | Único en toda la lista de periódicos. |
| `era_id` | int | FK a la era (contextual, no se usa actualmente en render). |
| `headline` | string | Titular en mayúsculas (como periódico real). |
| `date` | string | Fecha in-world. Debe contener "Año X" para que LoreService extraiga el año. |
| `snippet` | string | Resumen corto para la card preview. |
| `content` | string | HTML con el artículo completo. |
| `image` | string | URL opcional de imagen asociada. |

### 8.2 Filosofía de los Periódicos

**¿Por qué periódicos en lugar de solo lore basal y eventos?**
- Porque los periódicos ofrecen una PERSPECTIVA distinta. El lore basal es enciclopédico (tercera persona, distante). Los eventos son históricos (lineal, factual). Los periódicos son IN-WORLD: tienen sesgo, tienen voz, tienen contexto de publicación.
- Un periódico oficial del Gobierno dice una cosa. Un panfleto revolucionario dice otra. El lector decide.

**Tipos de periódicos según su fuente:**
1. **Periódico Oficial del Gobierno** — Propaganda, lenguaje burocrático, eufemismos.
2. **Panfleto del Ejército de la Liberación** — Lenguaje combativo, datos concretos, acusaciones.
3. **Publicación Independiente (La Brújula Libre)** — Tono neutral, "solo publicamos datos", pero con agenda implícita.

**Estilo por tipo:**
- **Oficial:** "El Gobierno se complace en anunciar...", "medida preventiva de rutina", "transición ordenada".
- **Revolucionario:** "Lo que el Gobierno no quiere que sepas...", "cuatrocientas treinta y siete personas", "mentira".
- **Independiente:** "Este periódico no toma partido. Publicamos datos.", preguntas sin respuesta.

### 8.3 Función Narrativa

Los periódicos cumplen 3 funciones:

1. **Sabor:** Hacen que el mundo se sienta real. Hay prensa, hay opinión pública, hay propaganda.
2. **Pistas:** Contienen información que el lore basal no da directamente. El lector tiene que inferir.
3. **Contraste:** Muestras versiones contradictorias de los mismos hechos. El Gobierno dice X, los revolucionarios dicen Y. El lector decide.

---

## 9. `historia.php`

### 9.1 Archivo y Ruta

```
game/public/historia.php
```

Página pública accesible desde el foro. Muestra todo el lore del mundo en una interfaz de tres columnas.

### 9.2 Estructura de la Vista

```
historia.php
├── Header con título + filtros
│   ├── Botón "Resumen" (modal con intro de cada era)
│   ├── Select de filtro por era
│   └── Campo de búsqueda
├── VISTA 1: CÓDICE DEL MUNDO (tres columnas)
│   ├── Columna 1: Lore Basal (lista por era)
│   ├── Columna 2: Cronología (lista de eventos por era)
│   └── Columna 3: News Coo (tarjetas de periódicos)
└── Modal de lectura (superposición fija)
    ├── Tag (tipo de entrada)
    ├── Título
    └── Cuerpo HTML (details de la entrada)
```

### 9.3 Flujo de Carga

```php
try {
    $cronologia = LoreService::obtenerCronologia(__DIR__ . '/../lore.json');
} catch (Throwable $e) { exit; }  // Si lore.json falla, no mostrar nada

$eras = $cronologia['eras'];
$periodicos = $cronologia['periodicos'] ?? [];

// Render HTML...
game_render_page('LORE: Archivos del Mundo', $content);
```

### 9.4 Componentes Interactivos (JS)

**Archivo JS:** `jscripts/game/historia.js`

| Componente | Función |
|------------|---------|
| **Filtro por era** | Select que muestra/oculta entradas de cada era |
| **Búsqueda** | Filtro en vivo por texto en títulos, descripciones, detalles |
| **Modal de lectura** | Click en cualquier entrada abre modal con contenido completo |
| **Enlaces cruzados** | Click en `.rpg-lore-link` dentro del modal carga la entrada referenciada |
| **Resumen de era** | Botón que muestra modal con `intro_text` + `intro_quote` de la era seleccionada |

### 9.5 Datos de Contexto para el JS

```php
$loreContextDataArray = [
    "all" => [
        "title" => "Archivos Históricos",
        "text"  => "",
        "quote" => ""
    ],
    "I" => [
        "title" => "Era I: La Era de los Cuatro Altares",
        "text"  => $era['intro_text'] ?? '',
        "quote" => $era['intro_quote'] ?? ''
    ],
    // ... más eras
];
$contextJson = json_encode($loreContextDataArray, JSON_UNESCAPED_UNICODE | ...);
```

Estos datos se incrustan en un atributo `data-context-info` y el JS los usa para el modal de resumen.

### 9.6 Filosofía del Visor

**¿Por qué tres columnas en lugar de una lista lineal?**
- Porque hay 3 tipos de contenido: lecturas (lore basal), fechas (eventos), noticias (periódicos). Cada uno tiene un formato visual distinto.
- Las tres columnas se leen en paralelo: el usuario puede escanear eventos, leer lore, y hojear periódicos sin cambiar de página.
- Diseño inspirado en códices y herbolarios medievales: múltiples flujos de información en una misma página.

**¿Por qué modal y no redirección?**
- El lore se lee mejor en contexto. Abrir una entrada en un modal preserva la posición del usuario en la cronología.
- Las referencias cruzadas funcionan dentro del mismo modal: carga otra entrada sin perder la anterior.

**¿Por qué `exit` silencioso si falla lore.json?**
- Si el archivo está corrupto, es mejor no mostrar nada que mostrar una página rota con errores PHP. Es una decisión de UX: silencio > ruido.

---

## 10. Sistema de Prompts P-06

### 10.1 ¿Qué es P-06?

P-06 es el prompt de la Zona de Población del foro, diseñado para generar lore desde cero usando un LLM. No existe lore predefinido — el admin lo define enviando prompts estructurados que producen JSON válido para `lore.json`.

**Principio fundamental:** El mundo se escribe desde cero. No hay lore canónico de One Piece ni de ningún otro universo. El admin decide qué existe, qué pasó y quiénes son los actores.

### 10.2 Estructura del Prompt P-06

El prompt P-06 debe contener:

1. **Contexto del mundo:** Nombre, tono, eras existentes, año actual.
2. **Instrucción específica:** Qué generar (nuevas eras, entradas de lore basal, eventos, periódicos).
3. **Catálogo de tipos:** Referencia a `event_types` y `lore_subtypes`.
4. **Formato de salida:** JSON exacto que debe producir, con campos requeridos.
5. **Ejemplos:** Entradas existentes como referencia de estilo.
6. **Restricciones:** Sin personajes canon, sin nombres de otras obras, coherencia con IDs existentes.

### 10.3 Ejemplo de Prompt (Simplificado)

```
Genera lore para el mundo de Kairan, año 800, Era IV (El Alba Rota).

INSTRUCCIÓN:
- Crea 3 nuevas entradas de lore_basal (era_id=4)
- Crea 2 eventos históricos (era_id=3)
- Crea 1 periódico (era_id=4, tipo: independiente)

CATÁLOGO DE TIPOS DISPONIBLES:
event_types: guerra, tratado, descubrimiento, catastrofe, poder,
             fundacion, traicion, expedicion, revolucion, profecia,
             muerte, artefacto, alianza, invasion, lore, exterminio,
             politica, militar, rebelion
lore_subtypes: faccion, geografia, cultura, religion, bestia,
               objeto, fenomeno_natural, personaje_historico,
               organizacion_secreta, artefacto_legendario,
               geografia_mitica, historia_prohibida, sistema_poderes

FORMATO DE SALIDA (JSON EXACTO):
Para lore_basal: {"id": NUEVO, "era_id": X, "name": "...",
                  "subtype": "...", "desc": "...", "details": "<p>...</p>"}
Para eventos: {"id": NUEVO, "era_id": X, "name": "...",
               "type": "...", "start_year": N, "end_year": N,
               "desc": "...", "details": "<p>...</p>",
               "ubicacion": "...", "impacto": "..."}
Para periodicos: {"id": NUEVO, "era_id": X, "headline": "...",
                  "date": "Año N — ...", "snippet": "...",
                  "content": "<p>...</p>", "image": ""}

RESTRICCIONES:
- No uses personajes canon de One Piece ni de ninguna otra obra.
- Todos los nombres deben ser originales.
- Las referencias a entradas existentes deben usar el formato
  <a href='#' class='rpg-lore-link' data-lore-id='ID'>.
- El estilo debe ser narrativo, con incertidumbre y misterio.
```

### 10.4 Flujo de Trabajo con P-06

```
1. Admin identifica qué falta en el lore.
   → "Necesito más lore de la Era II, facciones del Siglo Vacío"

2. Admin escribe prompt P-06 con instrucción específica.
   → Contexto + instrucción + catálogo + formato + restricciones

3. LLM genera JSON.

4. Admin revisa:
   → Coherencia con lore existente
   → IDs no duplicados
   → Estilo consistente
   → Sin nombres prohibidos

5. Admin integra en lore.json:
   → Si es nuevo → añade al array correspondiente
   → Si es corrección → reemplaza por ID

6. Admin actualiza meta.cambios_resumidos con log.

7. Commit a git.
```

### 10.5 Filosofía de P-06

**¿Por qué generar lore con LLM y no escribirlo manualmente?**
- Escalabilidad: un mundo con 4 eras, 40+ entradas de lore, 20+ eventos y 10+ periódicos requiere MILES de palabras narrativas coherentes. Un LLM bien dirigido produce eso en minutos.
- Consistencia: el LLM puede mantener el tono, el estilo narrativo y las referencias cruzadas si se le dan ejemplos.

**¿Por qué NO usar lore predefinido?**
- Porque el foro no es un fan-forum de One Piece. Es un foro ORIGINAL con mecánicas inspiradas en One Piece, pero con mundo, historia y personajes propios.
- La personalización total permite al admin crear un mundo ÚNICO.

**Riesgo de P-06 y cómo mitigarlo:**
- El LLM puede alucinar inconsistencias. La revisión manual del admin es obligatoria.
- El LLM puede generar contenido genérico. El prompt debe incluir ejemplos específicos del tono deseado.
- El LLM puede ignorar restricciones de nombres. El admin debe verificar siempre.

---

## 11. Flujo de Creación de Lore

### 11.1 Diagrama de Secuencia

```
Admin → Prompt P-06 → LLM → JSON → lore.json → historia.php

1. Admin identifica necesidad narrativa
2. Escribe prompt P-06 con contexto + instrucción
3. Recibe JSON del LLM
4. Valida: IDs, estilo, coherencia
5. Integra en lore.json
6. Actualiza meta (cambios_resumidos)
7. Commit y push
8. lore.json se despliega en producción
9. Usuarios ven nuevo lore en historia.php
```

### 11.2 Guía de Creación por Sección

#### Crear una Era Nueva

1. Asignar `id` correlativo (siguiente al último).
2. Elegir `numeral` romano.
3. Elegir nombre que encapsule el conflicto central del período.
4. Definir años de inicio y fin.
5. Escribir `intro_quote` — una línea que sintetice el tono emocional.
6. Escribir `intro_text` — 2-4 párrafos que respondan:
   - ¿Qué pasó en esta era?
   - ¿Quiénes fueron los actores principales?
   - ¿Qué cambió respecto a la era anterior?
   - ¿Qué legado dejó para la era siguiente?

#### Crear una Entrada de Lore Basal

1. Asignar `id` único en todo el archivo.
2. Elegir `era_id` correcto.
3. Elegir nombre conciso y memorable.
4. Elegir `subtype` del catálogo.
5. Escribir `desc` — una frase que resuma la entrada.
6. Escribir `details` en HTML:
   - Primer párrafo: presentación del tema.
   - Párrafos intermedios: desarrollo narrativo con detalles.
   - Último párrafo: conexión con el presente del foro (año 800).
   - Enlazar otras entradas con `data-lore-id`.

#### Crear un Evento

1. Asignar `id` único.
2. Elegir `era_id` del período en que ocurre.
3. Elegir `type` del catálogo `event_types`.
4. Definir `start_year` y `end_year`.
5. Escribir `desc` (resumen) y `details` (narrativa completa).
6. Escribir `ubicacion` (dónde).
7. Escribir `impacto` (consecuencias:
   - ¿Qué cambió en el mundo después de este evento?
   - ¿Cómo afectó a las facciones?
   - ¿Por qué importa en el año 800?

#### Crear un Periódico

1. Elegir fuente: ¿Oficial del Gobierno? ¿Revolucionario? ¿Independiente?
2. Escribir `headline` en mayúsculas, como titular real.
3. Escribir `date` con el formato "Año X — ...".
4. Escribir `snippet` (2-3 líneas para la card).
5. Escribir `content` completo:
   - El tono debe coincidir con la fuente.
   - Puede contener información que contradiga otras fuentes.
   - Debe sentirse como un artículo de periódico real, no como lore disfrazado.

### 11.3 Convenciones de Estilo

**Tono general:**
- Voz narrativa con personalidad, no académica neutral.
- Incertidumbre deliberada: "quizás", "se cree que", "nadie sabe".
- Misterio: preguntas sin respuesta, versiones contradictorias.
- Conexión con el presente: cada entrada explica por qué importa en el año 800.

**Formato de texto:**
- HTML en `details` y `content` para permitir `<p>`, `<em>`, `<strong>`, `<a>`.
- NO usar markdown dentro del JSON — el contenido es HTML.
- Los párrafos largos son aceptables (lore denso).

**Referencias cruzadas:**
```html
<a href='#' class='rpg-lore-link' data-lore-id='7'>Familia Draven</a>
```
- Siempre con `class='rpg-lore-link'` para que el JS los detecte.
- `data-lore-id` debe apuntar a un ID existente en `lore_basal`.

**Convención de IDs:**
- Los IDs son enteros secuenciales dentro de cada sección.
- NO reutilizar IDs aunque una entrada se elimine.
- El archivo `lore.json` usa IDs hasta el 45 en lore_basal, 18 en eventos, 11 en periódicos (estos números crecen con cada parche).

---

## 12. Consejos para el Admin

### 12.1 Creando Lore

**Empieza por las eras.** Define primero los períodos históricos. Sin eras, no hay contexto para el lore basal ni los eventos.

**Cada era necesita un conflicto central.**
- Era I: ¿Libertad o seguridad?
- Era II: ¿Memoria o control?
- Era III: ¿Resistencia o sumisión?
- Era IV: ¿Antes del fin?

Sin conflicto, una era es solo fechas.

**El lore basal es el esqueleto.** Invierte más tiempo aquí que en eventos o periódicos. Un lore basal rico hace que los eventos tengan contexto y los periódicos tengan peso.

**Los periódicos son el lujo.** Escribe periódicos cuando el mundo ya tiene suficiente lore basal y eventos. Un periódico sin contexto es ruido.

### 12.2 Manteniendo Coherencia

**Lleva un registro de IDs usado.** Cuando añadas lore, sabes qué ID sigue.

**Mantén un mapa mental de las familias.** En el lore de Kairan hay ~15 familias (Solmaren, Draven, Varek, Orvane, Sorell, Kross, Maren, Dross, etc.). Cada una tiene:
- Rol histórico (¿qué hicieron?).
- Rol en el año 800 (¿qué hacen ahora?).
- Relaciones con otras familias (aliados, enemigos, desconocidos).

**Las referencias cruzadas son el pegamento.** Cada entrada de lore basal debería enlazar a al menos otra entrada. Así se teje la red.

### 12.3 Usando P-06 Efectivamente

**Sé específico en el prompt.** "Genera 3 facciones para la Era II" es mejor que "genera más lore". El LLM necesita restricciones claras.

**Provee ejemplos de estilo.** Incluye 1-2 entradas existentes en el prompt para que el LLM replique el tono.

**Pide revisión humana.** Nunca integres el output del LLM sin leerlo. Los errores más comunes:
- Nombres que suenan a otro universo.
- Inconsistencias de fechas (evento en Era I pero con año de Era III).
- Estilo demasiado genérico (sin misterio, sin voz).

### 12.4 Versionado

**Cada cambio de lore es un commit.** Usa mensajes descriptivos:
```
git commit -m "lore: añadidas LB#46-48 (facciones Era III) + Evento#19 (Tratado de Zou)"
```

**El `meta.cambios_resumidos` es el changelog humano.** Actualízalo siempre:
```json
"cambios_resumidos": [
    "Añadidas 3 entradas de lore basal (Era III)",
    "Creado Evento#19 — El Tratado de Zou (año 453)",
    "Actualizada LB#12 con referencias al nuevo evento"
]
```

---

## 13. Filosofía de Diseño

### 13.1 Principios Rectores

1. **El lore se descubre, no se explica.** Cada entrada debe dejar preguntas abiertas. El misterio es más valioso que la respuesta.

2. **Ninguna versión es definitiva.** El periódico oficial miente. El panfleto revolucionario exagera. El lore basal especula. El lector construye su propia verdad.

3. **El mundo tiene memoria selectiva.** El Gobierno ha borrado historia. Los personajes han olvidado. El lore es lo que queda después de siglos de censura, no lo que realmente pasó.

4. **Conexión con el presente.** Toda entrada de lore debe responder: ¿por qué importa esto en el año 800? Si no importa, no está en el lore.

### 13.2 Decisiones Clave

| Decisión | Alternativa | Por qué se eligió así |
|----------|-------------|----------------------|
| JSON en lugar de DB | Tablas MySQL | Admin es único escritor, versionado con git, atomicidad, portabilidad |
| IDs enteros secuenciales | UUIDs | Simplicidad de lectura/edit manual del JSON |
| Sin lore predefinido | Lore basado en One Piece | Originalidad, personalización total por el admin |
| P-06 para generación | Escritura manual completa | Escalabilidad, consistencia de tono |
| HTML en details | Markdown | El contenido es rico (enlaces, énfasis, párrafos); HTML da control total |
| 3 columnas en visor | Lista única | Tres tipos de contenido con formatos distintos |
| Modal para lectura | Página separada | Preserva contexto del usuario, navegación cruzada |

### 13.3 Filosofía del Lore como Sistema

**El lore no es un libro de historia. Es una biblioteca de fuentes no fiables.**

- Las entradas de lore basal son enciclopédicas pero con sesgo.
- Los eventos son "hechos" pero seleccionados por alguien.
- Los periódicos son abiertamente propaganda o periodismo con agenda.

**El lector es arqueólogo.** Tiene que cruzar fuentes, detectar contradicciones, inferir lo que no se dice. Esto convierte la lectura de lore en una actividad activa, no pasiva.

**El año 800 es el presente del foro.** Todo el lore converge ahí. Las tramas de los personajes ocurren en este presente. El lore explica por qué el mundo es como es cuando los jugadores empiezan a rolear.

### 13.4 Impacto en el Juego

| Aspecto del lore | Lo que permite en el foro |
|------------------|--------------------------|
| Eras definidas | Los jugadores saben en qué contexto histórico están sus personajes |
| Lore basal con misterios | Los jugadores pueden investigar, buscar respuestas en sus tramas |
| Eventos históricos | Puntos de referencia para historias de personajes |
| Periódicos contradictorios | Los personajes pueden tener opiniones basadas en qué fuentes consumen |
| Referencias cruzadas | Los jugadores descubren conexiones entre tramas |
| Sin lore predefinido | El admin decide qué es canon, no hay conflictos con material fuente |

---

## 14. Guía de Troubleshooting

### 14.1 Errores Comunes

| Error | Causa | Solución |
|-------|-------|----------|
| Página en blanco en `historia.php` | `lore.json` no existe o JSON inválido | Verificar ruta y sintaxis JSON. Usar `json_decode` en CLI. |
| Periódico no aparece en orden | Falta "Año X" en el campo `date` | Añadir año en formato "Año N — ..." |
| Enlace cruzado no funciona | `data-lore-id` apunta a ID inexistente | Verificar que el ID existe en `lore_basal` |
| "Tipo desconocido" en error_log | Evento con `type` no definido en `lore_types.json` | Añadir el tipo al catálogo o corregir el slug |
| Era no muestra entradas | `era_id` incorrecto en lore_basal/eventos | Verificar que el `era_id` coincide con el `id` de la era |
| Eventos aparecen desordenados | `start_year` incorrecto o faltante | Verificar años en los eventos |
| Modal de lectura vacío | `data-details` con HTML escapado incorrectamente | Usar `htmlspecialchars()` correctamente en PHP |

### 14.2 Validación de `lore.json`

```bash
# Verificar que el JSON es válido
php -r "json_decode(file_get_contents('lore.json')); echo json_last_error_msg();"

# Verificar IDs duplicados en lore_basal
php -r "
\$d = json_decode(file_get_contents('lore.json'), true);
\$ids = array_column(\$d['lore_basal'], 'id');
\$dups = array_unique(array_diff_key(\$ids, array_unique(\$ids)));
echo \$dups ? 'Duplicados: ' . implode(', ', \$dups) : 'OK';
"
```

### 14.3 Depuración del LoreService

Activar logs de tipos desconocidos:
```php
// En obtenerCronologia(), el servicio loguea automáticamente:
error_log('[game] LoreService: tipo desconocido "' . $type . '" en evento "' . $name . '"');
```

Esto escribe en el log de errores de PHP. Útil para detectar tipos mal escritos.

### 14.4 Recuperación

Si `lore.json` se corrompe:
1. `git checkout lore.json` para recuperar la última versión válida.
2. Si no hay git, mantener un backup del archivo.
3. Si el archivo es muy grande y se corrompe parcialmente, usar `json_decode` con `JSON_INVALID_UTF8_IGNORE` para rescatar partes.

---

## APÉNDICE A: Arquitectura de Archivos

```
back/forum/game/
├── lore.json                           ← Fuente única de lore (~1168 líneas)
├── src/
│   └── Application/
│       └── Services/
│           └── LoreService.php         ← Servicio de lectura/procesamiento (185 líneas)
├── src/
│   └── Config/
│       └── lore_types.json             ← Catálogo de tipos (20 event_types + 17 lore_subtypes)
├── public/
│   └── historia.php                    ← Visor público de lore (176 líneas)
└── jscripts/
    └── game/
        └── historia.js                 ← Interactividad (filtros, modal, búsqueda)
```

---

## APÉNDICE B: Resumen de Clases y Métodos

| Clase / Archivo | Método / Propósito |
|-----------------|-------------------|
| `LoreService::obtenerTipos()` | Lee y cachea `lore_types.json` |
| `LoreService::obtenerCronologia($path)` | Procesa `lore.json`, estructura lore completo |
| `LoreService::agruparEnFilas($eventos)` | Agrupa eventos solapados para render visual |
| `historia.php` | Vista pública: 3 columnas, modal, filtros |
| `historia.js` | JS: filtros, búsqueda, modal, referencias cruzadas |

---

*Fin del documento — Guía completa del Sistema de Lore v1.0*
*Generado desde: `Guias/sistemas/19-lore.md`*
*Referencia: `Guias/MAESTRO_SISTEMAS_RPG.md` — Sección 19*
