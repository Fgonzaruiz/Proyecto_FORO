# MAESTRO DE SISTEMAS RPG — GUÍA COMPLETA DEL FORO

> **Documento raíz.** Cada sección aquí referenciada tendrá su propio `.md` en `Guias/sistemas/`.
> La segunda parte del documento (`## ZONA DE POBLACIÓN`) contiene prompts listos para que una IA genere contenido de calidad para el foro.

---

## ÍNDICE GENERAL

1. [Sistema de Personajes (Ficha)](#1-sistema-de-personajes-ficha)
2. [Stats — Los 7 Atributos](#2-stats--los-7-atributos)
3. [Rangos y Progresión](#3-rangos-y-progresión)
4. [Puntos de Aventura (PA) y Puntos de Progresión (PP)](#4-puntos-de-aventura-pa-y-puntos-de-progresión-pp)
5. [Sistema de Cards (Cartas)](#5-sistema-de-cards-cartas)
6. [Inventario y Equipamiento](#6-inventario-y-equipamiento)
7. [Disciplinas de Combate](#7-disciplinas-de-combate)
8. [Estilos Canónicos](#8-estilos-canónicos)
9. [Oficios](#9-oficios)
10. [Haki](#10-haki)
11. [Akuma no Mi (Frutas del Diablo)](#11-akuma-no-mi-frutas-del-diablo)
12. [Razas — Pasivas y Linaje](#12-razas--pasivas-y-linaje)
13. [Barcos — Cards y Navegación](#13-barcos--cards-y-navegación)
14. [Sistema de Navegación — Rutas e Islas](#14-sistema-de-navegación--rutas-e-islas)
15. [Islas-Foros (Cada Foro como Isla)](#15-islas-foros)
16. [Sistema de Misiones](#16-sistema-de-misiones)
17. [NPCs Mayores](#17-npcs-mayores)
18. [NPCs Menores (Cards tipo npc_menor)](#18-npcs-menores-cards-tipo-npc_menor)
19. [Lore — Historia del Mundo](#19-lore--historia-del-mundo)
20. [Economía — Berries y Tienda](#20-economía--berries-y-tienda)
21. [Puntos Destino (PD) — Premios](#21-puntos-destino-pd--premios)
22. [Sistema de Tripulaciones](#22-sistema-de-tripulaciones)
23. [Sistema de Oráculos y Tiradas](#23-sistema-de-oráculos-y-tiradas)
24. [Sistema de Posts — PV/PE y Modificadores](#24-sistema-de-posts--pvpe-y-modificadores)
25. [Sistema de Hilos — Metadatos](#25-sistema-de-hilos--metadatos)
26. [Búsquedas de Rol](#26-búsquedas-de-rol)
27. [Sistema de Notificaciones](#27-sistema-de-notificaciones)
28. [Cartas de Equipo Básico (Tienda)](#28-cartas-de-equipo-básico-tienda)
29. [Sistema de Despertar (Awakening)](#29-sistema-de-despertar-awakening)
30. [Clima y Fenómenos — Ampliación de Navegación](#30-clima-y-fenómenos--ampliación-de-navegación)

---

## ZONA DE POBLACIÓN

- [PROMPTS DE POBLACIÓN IA](#zona-de-población--prompts-de-ia)
  - [P-01 Cards de Equipo Básico (Tienda)](#p-01-cards-de-equipo-básico-tienda)
  - [P-02 Estilos Canónicos + sus Cards de Técnicas](#p-02-estilos-canónicos--sus-cards-de-técnicas)
  - [P-03 Islas-Foros](#p-03-islas-foros)
  - [P-04 Misiones](#p-04-misiones)
  - [P-05 NPCs Mayores](#p-05-npcs-mayores)
  - [P-06 Lore — Creación desde Cero](#p-06-lore--creación-desde-cero)
  - [P-07 Premios con Puntos Destino](#p-07-premios-con-puntos-destino)
  - [P-08 Razas — Pasivas, Stats y Puntos de Linaje](#p-08-razas--pasivas-stats-y-puntos-de-linaje)
  - [P-09 Rutas de Navegación](#p-09-rutas-de-navegación)
  - [P-10 Cards de Técnicas / NPC Menores / Barcos / Haki / Akuma](#p-10-cards-de-técnicas--npc-menores--barcos--haki--akuma)

---

---

# PARTE 1 — SISTEMAS RPG DETALLADOS

---

## 1. Sistema de Personajes (Ficha)

**Archivo de guía:** `Guias/sistemas/01-personaje.md`

### Qué es
Cada jugador crea uno o más **personajes (PJ)** que representan su alter ego en el mundo. La ficha contiene toda la información mecánica y narrativa del personaje.

### Estructura de la Ficha
| Campo | Tipo | Descripción |
|---|---|---|
| `name` | texto | Nombre del personaje |
| `race` / `race_name` | slug / texto | Raza (humano, gyojin, mink, etc.) |
| `occupation` / `occupation_name` | slug / texto | Ocupación o rol narrativo |
| `rango` | texto | Rango dentro de su facción |
| `tripulacion` | texto | Tripulación o afiliación |
| `recompensa` | texto | Recompensa en Berries (cosmética) |
| `desc` | texto corto | Descripción pública breve |
| `details` | texto largo | Trasfondo y detalles extendidos |
| `stats_json` | JSON | Los 7 atributos (rangos 1–6) |
| `data_json` | JSON | Datos de progresión (PP, PA, rank global, nivel) |
| `berries` | entero | Moneda económica |
| `puntos_destino` | entero | Moneda de premios |
| `postnum` / `threadnum` | entero | Actividad rolística registrada |
| `cronologia_json` | JSON | Línea de tiempo biográfica del PJ |
| `banner` / `avatar` | URL | Imágenes del personaje |
| `firma` | HTML | Firma en posts |
| `faction` | texto | Facción (pirata, marine, revolucionario…) |

### Slots de Personaje
- Cada usuario tiene por defecto **1 slot** de personaje.
- El staff puede ampliar slots (`max_slots`).
- Solo un personaje puede estar **activo** (`active_pj_id`).

### Estados del Personaje
| Estado | Significado |
|---|---|
| `pendiente` | Ficha creada, sin revisar |
| `en_revision` | Staff la está revisando |
| `aprobado` | Personaje listo para rolear |
| `rechazado` | Necesita correcciones |

### Personajes Staff / Narradores
- `is_staff = 1`: personaje con poderes de gestión.
- `is_narrator = 1`: narrador con acceso a gestión de NPCs.
- `staff_level`: nivel de permisos del staff (1–3).

### Revisiones
La tabla `game_personajes_revisiones` guarda el historial de revisiones con mensaje del staff, fecha y cambio de estado.

---

## 2. Stats — Los 7 Atributos

**Archivo de guía:** `Guias/sistemas/02-stats.md`

### Los 7 Atributos
| Slug | Nombre | Descripción |
|---|---|---|
| `fue` | **Fuerza** | Potencia física bruta, daño cuerpo a cuerpo, carga |
| `res` | **Resistencia** | Aguante físico, PV máximos, resistencia al daño |
| `agi` | **Agilidad** | Velocidad de movimiento, reflejos, evasión |
| `des` | **Destreza** | Precisión, control fino, habilidades técnicas |
| `int` | **Inteligencia** | Razonamiento, táctica, conocimiento, magia |
| `inst` | **Instinto** | Percepción, voluntad, resistencia mental |
| `esp` | **Espíritu** | Caudal de PE, Haki latente, presencia |

### Sistema de Rangos (1–6)
Cada stat va de **1 (mínimo)** a **6 (máximo absoluto de PJ ordinario)**. Los valores de 7+ son exclusivos de NPCs/jefes narrativos.

| Rango | Equivalencia narrativa |
|---|---|
| 1 | Principiante / civil sin entrenamiento |
| 2 | Entrenado / marinero o pirata novato |
| 3 | Competente / soldado o pirata de la Grand Line |
| 4 | Experto / oficial de alto rango o capitán fuerte |
| 5 | Maestro / vicealmirante o capitán de los Blues |
| 6 | Leyenda / nivel Almirante u equivalente |

### PV y PE — Cómo se Calculan
- **PV (Puntos de Vida):** `base + (res × multiplicador de rango)`
- **PE (Puntos de Energía):** `base + (esp × multiplicador de rango)`

### Rango Global del Personaje
La suma de los 7 stats determina el **rango global** del PJ (D → SS).

---

## 3. Rangos y Progresión

**Archivo de guía:** `Guias/sistemas/03-rangos.md`

### Tabla de Rangos Globales
| Rango | Nivel | Requisito de suma de stats |
|---|---|---|
| D | 1 | Suma baja (~7–14) |
| C | 2 | Suma media-baja (~15–22) |
| B | 3 | Suma media (~23–30) |
| A | 4 | Suma alta (~31–36) |
| S | 5 | Suma muy alta (~37–40) |
| SS | 6 | Suma máxima (~41–42) — Staff only |

### Cómo se Sube de Rango
1. El jugador acumula **PP** (Puntos de Progresión) mediante actividad.
2. Invierte PP en subir stats individuales.
3. Al alcanzar la suma mínima del siguiente rango, el sistema o el staff actualiza el rango global.

---

## 4. Puntos de Aventura (PA) y Puntos de Progresión (PP)

**Archivo de guía:** `Guias/sistemas/04-pa-pp.md`

### PA — Puntos de Aventura
- Son la **"energía táctica"** gastada post a post.
- El jugador **declara** los PA gastados en cada post (`pa_declared`).
- No son validados automáticamente; el staff los revisa en revisión de hilo.
- **PA afecta:** número de cartas que puedes jugar en un post, acciones especiales, uso de Haki avanzado.

### PP — Puntos de Progresión
- Son la **moneda de mejora permanente** del personaje.
- Se invierten en: subir rangos de Stats, desbloquear grados de Disciplinas y Oficios, aprender Estilos Canónicos, obtener ciertas Cartas.

### Campo `data_json` — Todo lo que guarda
```json
{
  "pp": 120,
  "rank": "B",
  "nivel": 3,
  "faction_rank": "Capitán",
  "last_rank_change_at": "2025-01-15 10:30:00"
}
```

---

## 5. Sistema de Cards (Cartas)

**Archivo de guía:** `Guias/sistemas/05-cards.md`

### Qué es una Card
Una **card** es la representación mecánica de una habilidad, objeto, aliado o recurso del personaje. Toda acción con impacto mecánico real debe estar respaldada por una card.

### Tipos de Card (`card_type`)
| Tipo | Descripción |
|---|---|
| `tecnica` | Técnica de combate o habilidad activa/pasiva |
| `equipo` | Objeto equipable (arma, armadura, herramienta) |
| `akuma_no_mi` | Poder de una Fruta del Diablo |
| `haki` | Técnica de Haki |
| `npc_menor` | Aliado menor, bestia o subordinado |
| `barco` | Embarcación del PJ |

### Rangos de Card (D → SS)
A mayor rango, mayor poder y mayor coste.

### Activación
| Activación | Cuándo funciona |
|---|---|
| `activa` | El jugador la declara en su post |
| `pasiva` | Siempre activa, sin coste de declaración |
| `reactiva` | Se activa en respuesta a una acción rival |

### Campos Mecánicos Clave
| Campo | Descripción |
|---|---|
| `cost_pe` | Coste en PE para activar |
| `execution_cost` | Coste adicional en PP/PA para ejecutar |
| `execution_stat` | Stat que se usa para la tirada |
| `dice` | Fórmula de dados (ej: `2d20+fue`) |
| `reposo` | Posts de recuperación antes de poder reusarla |
| `duracion` | Posts que dura el efecto |
| `tier` | Tier mecánico (1–6, igual que rangos) |
| `peso` | Peso en el inventario (slots) |

### Proceso de Obtención de Cards
1. **Compra en tienda** (Berries) para cartas de equipo básico.
2. **Solicitud al staff** (`game_card_requests`) para cartas de técnica personalizadas.
3. **Asignación directa** por staff.
4. **Drop en misiones / eventos** (staff asigna tras resolver).

---

## 6. Inventario y Equipamiento

**Archivo de guía:** `Guias/sistemas/06-inventario.md`

### Slots de Inventario
| Slot | Para qué |
|---|---|
| `carga` | Equipo, armas, consumibles portados |
| `companero` | NPCs menores activos (bestias, subordinados) |
| `barco` | El barco activo del PJ |

### Peso
Cada card tiene un **peso** (`peso`). La capacidad de carga es limitada. Superar el peso impide equipar más ítems.

### Snapshot en Posts
Cuando un jugador postea, el sistema guarda un **snapshot** de su equipamiento en ese momento (`equipped_snapshot_json`).

---

## 7. Disciplinas de Combate

**Archivo de guía:** `Guias/sistemas/07-disciplinas.md`

### Qué es una Disciplina
Una disciplina es una **familia de combat skills** que el personaje domina progresivamente (grados 1–5). Cada grado desbloquea capacidades narrativas y acceso a cards de mayor tier.

### Disciplinas Disponibles
| Slug | Nombre | Categoría |
|---|---|---|
| `cuerpo_a_cuerpo` | Cuerpo a Cuerpo | Combate |
| `armas_de_filo` | Armas de Filo | Combate |
| `armas_de_asta` | Armas de Asta | Combate |
| `armas_contundentes` | Armas Contundentes | Combate |
| `armas_a_distancia` | Armas a Distancia | Combate |
| `armas_de_fuego` | Armas de Fuego | Combate |
| `armas_exoticas` | Armas Exóticas | Combate |
| `escudo` | Escudo | Combate |
| `haki_conquistador` | Haki de Conquistador | Especial |

---

## 8. Estilos Canónicos

**Archivo de guía:** `Guias/sistemas/08-estilos-canonicos.md`

### Qué es un Estilo Canónico
Un estilo canónico es una **escuela de combate** con identidad propia, vinculada a una disciplina y con requisitos narrativos concretos. Otorga ventajas narrativas específicas y acceso a sus cartas de técnica propias.

### Diferencia con Disciplinas
- Las **disciplinas** son la base genérica (árbol de habilidades abierto a todos).
- Los **estilos canónicos** son especializaciones temáticas con requisitos narrativos.

### Requisitos Típicos
- Disciplina base en grado II o superior.
- Stat principal a rango C+ o superior.
- Condición narrativa (entrenamiento IC, raza, juramento, maestro reconocido).

### Cards de Estilo
Cada estilo tiene sus propias cartas de técnica con `estilo_canonico_slug` vinculado. Solo pueden ser solicitadas por personajes que hayan aprendido ese estilo.

---

## 9. Oficios

**Archivo de guía:** `Guias/sistemas/09-oficios.md`

### Qué es un Oficio
El oficio es la **especialización no combativa** del personaje. Define su rol en la tripulación y sus capacidades de utilidad.

### Oficios Disponibles
| Slug | Nombre | Categoría |
|---|---|---|
| `navegante` | Navegante | Utilidad |
| `medico` | Médico | Utilidad |
| `cocinero` | Cocinero | Utilidad |
| `herrero` | Herrero | Crafteo |
| `carpintero` | Carpintero | Crafteo |
| `cientifico` | Científico | Crafteo |
| `domador` | Domador | Utilidad |
| `arqueologo` | Arqueólogo | Lore |
| `musico` | Músico/Artista | Utilidad |
| `espia` | Espía/Infiltrador | Sigilo |
| `mercader` | Mercader | Economía |

---

## 10. Haki

**Archivo de guía:** `Guias/sistemas/10-haki.md`

### Tipos de Haki
| Tipo | Descripción |
|---|---|
| **Haki de Observación (Kenbunshoku)** | Percepción avanzada, predicción de movimientos, detección de presencia |
| **Haki de Armamento (Busoshoku)** | Endurecimiento del cuerpo/armas, único medio de dañar usuarios de Logia |
| **Haki de Conquistador (Haoshoku)** | Aura de voluntad superior, noquea a rivales débiles, se infunde en armas (avanzado) |

### Haki como Disciplina
- Haki de Observación y Armamento son **disciplinas** que cualquier PJ puede desbloquear con PP.
- Haki de Conquistador es **exclusivo**: otorgado por staff. Usa la disciplina `haki_conquistador`.

### Haki y Cards
El Haki interactúa con las cards de tipo `haki`. Estas cards representan técnicas específicas de cada tipo.

---

## 11. Akuma no Mi (Frutas del Diablo)

**Archivo de guía:** `Guias/sistemas/11-akuma.md`

### Clasificación
| Clase | Descripción |
|---|---|
| `paramecia` | Poderes corporales o de entorno variados |
| `zoan` | Transformación animal; subtipo: ninguno, antiguo, mítico |
| `logia` | Control elemental + intangibilidad natural |

### Sistema de Solicitud
1. El jugador solicita la fruta mediante `game_admin_requests`.
2. El staff comprueba disponibilidad (`is_occupied = 0`, `is_reserved = 0`).
3. Si se aprueba, se marca `is_occupied = 1` y se asigna la card `akuma_no_mi` al PJ.

### Desventajas
- El usuario **no puede nadar**.
- El usuario **pierde sus poderes** sumergido o con grillete Kairoseki.

---

## 12. Razas — Pasivas y Linaje

**Archivo de guía:** `Guias/sistemas/12-razas.md`

### Estructura General de una Raza
Cada raza tiene:
1. **Pasivas Primarias** — Habilidades innatas siempre activas.
2. **Pasivas Secundarias** — Habilidades adicionales menores.
3. **Puntos de Linaje** — Moneda especial de la raza para desbloquear habilidades raciales avanzadas.
4. **Cards Raciales** — Cards exclusivas de esa raza.
5. **Bonificaciones / Penalizaciones de Stat** — Modificadores al distribuir stats iniciales.

---

## 13. Barcos — Cards y Navegación

**Archivo de guía:** `Guias/sistemas/13-barcos.md`

### Barcos como Cards
Los barcos son cards del tipo `barco`. Se equipan en el slot `barco` del inventario.

### Categorías Narrativas de Barcos
- **Barca / Balsa:** rango D, para Blues iniciales.
- **Bergantín:** rango C, válido para Grand Line.
- **Galeón:** rango B, resistente y espacioso.
- **Navío de Guerra:** rango A, ideal para combate naval.
- **Barco Legendario:** rango S/SS, requiere misión o evento especial.

---

## 14. Sistema de Navegación — Rutas e Islas

**Archivo de guía:** `Guias/sistemas/14-navegacion.md`

### Tablas Involucradas
| Tabla | Qué guarda |
|---|---|
| `game_navigation_routes` | Rutas entre islas (distancia, peligro) |
| `game_navigation_voyages` | Viajes activos de cada PJ |
| `game_navigation_events` | Eventos ocurridos durante el viaje |

### Zonas del Mundo
| Zona | Nivel | Descripción |
|---|---|---|
| `east_blue` | 1–2 | El Blue más seguro |
| `west_blue` | 1–2 | Similar al East |
| `north_blue` | 2–3 | Aguas más complejas |
| `south_blue` | 2–3 | Similar al North |
| `grand_line` | 3 | El Grand Line — impredecible |
| `new_world` | 4–5 | La segunda mitad — extremo |

---

## 15. Islas-Foros

**Archivo de guía:** `Guias/sistemas/15-islas.md`

### Datos de Cada Isla
| Campo | Descripción |
|---|---|
| `fid` | ID del foro MyBB (clave primaria) |
| `island_image` | Imagen representativa |
| `leader_name` | Líder actual de la isla |
| `description` | Historia y descripción general |
| `terrain` | Tipo de terreno |
| `climate` | Clima general |
| `buildings` | Edificios y puntos de interés |
| `defenses` | Defensas militares o naturales |
| `resources` | Recursos naturales disponibles |
| `sea_zone` | Zona del mundo |
| `base_danger` | Peligro base (1–5) |
| `requires_log_pose` | ¿Necesita Log Pose para llegar? |

---

## 16. Sistema de Misiones

**Archivo de guía:** `Guias/sistemas/16-misiones.md`

### Tipos de Misión por Categoría
| Categoría | Descripción |
|---|---|
| `combate` | Eliminar una amenaza, cazar a alguien |
| `exploracion` | Descubrir lugares, cartografiar |
| `sigilo` | Infiltración, robo, sabotaje |
| `escolta` | Proteger a alguien o algo |
| `supervivencia` | Sobrevivir en condiciones extremas |
| `diplomacia` | Negociar, mediar conflictos |

### Rangos de Misión
| Rango | Dificultad | Recompensa PD aprox. |
|---|---|---|
| D | Novato | 1–2 PD |
| C | Estándar | 2–4 PD |
| B | Intermedio | 4–6 PD |
| A | Avanzado | 6–10 PD |
| S | Experto | 10–20 PD |
| SS | Legendario | 20+ PD |

---

## 17. NPCs Mayores

**Archivo de guía:** `Guias/sistemas/17-npcs-mayores.md`

### Estructura de un NPC Mayor
| Sección | Contenido |
|---|---|
| `nombre` | Nombre completo |
| `imagen` | Avatar del NPC |
| `identificacion` (JSON) | Edad, apodo, rango, afiliación, bounty |
| `perfil_fisico` (JSON) | Altura, peso, rasgos físicos, ropa típica |
| `psicologia` (JSON) | Personalidad, miedos, motivaciones ocultas |
| `motivaciones` (JSON) | Objetivos a corto, medio y largo plazo |
| `perfil_estrategico` (JSON) | Cómo combate, frutas, Haki, disciplinas |
| `cronologia` (JSON) | Línea de tiempo biográfica |
| `relaciones` (JSON) | Aliados, enemigos, relaciones con PJs |
| `stats` (JSON) | Los 7 stats (rangos 1–8+) |

---

## 18. NPCs Menores (Cards tipo npc_menor)

**Archivo de guía:** `Guias/sistemas/18-npcs-menores.md`

### Qué es
Un NPC menor es un **aliado, subordinado o bestia** que un PJ puede poseer como card. Toda su información está en la card, no tiene ficha independiente.

### Cómo se Obtienen
- Compra en tienda (si están disponibles).
- Solicitud al staff.
- Drop en misiones.
- Oficio de Domador (grado 1+) para bestias.

---

## 19. Lore — Historia del Mundo

**Archivo de guía:** `Guias/sistemas/19-lore.md`

### Estructura del Lore
El lore se organiza en:
1. **Eras:** periodos históricos con nombre, años y descripción.
2. **Lore Basal:** entradas enciclopédicas (civilizaciones, artefactos, geografía).
3. **Eventos:** sucesos históricos concretos (guerras, fundaciones, catástrofes).
4. **Periódicos:** noticias in-world ficticias que dan sabor al lore.

> El lore completo del foro se creará desde cero usando el prompt **P-06** de la zona de población. No existe lore predefinido; el admin lo define.

---

## 20. Economía — Berries y Tienda

**Archivo de guía:** `Guias/sistemas/20-economia.md`

### Berries
- Moneda principal del mundo (`berries` en `game_personajes`).
- Se ganan completando misiones, rol activo, eventos, comercio entre PJs.
- Se gastan en la tienda y para upgrades narrativos.

### Tienda
Las cards con `in_shop = 1` aparecen en la tienda pública, organizadas por `shop_category`: utiles / armas / armaduras / consumibles / barcos / npcs.

---

## 21. Puntos Destino (PD) — Premios

**Archivo de guía:** `Guias/sistemas/21-puntos-destino.md`

### Qué son los PD
Los Puntos Destino son la **moneda de mérito** del foro. Se ganan por completar misiones, eventos especiales y actividad rolística destacada.

### Categorías de Premios
- **Extras de personaje:** slot adicional, cambio de raza, cambio de nombre.
- **Cards especiales:** cartas únicas no disponibles en tienda.
- **Boosters de progresión:** PP extra, subida de rango acelerada.
- **Contenido narrativo:** misión privada, NPC aliado, ítem legendario.
- **Cosméticos:** banners, firmas especiales, insignias de foro.

---

## 22. Sistema de Tripulaciones

**Archivo de guía:** `Guias/sistemas/22-tripulaciones.md`

### Campos
| Campo | Descripción |
|---|---|
| `nombre` | Nombre de la tripulación |
| `imagen` | Bandera o emblema |
| `descripcion` | Historia y filosofía |

Los NPCs mayores tienen un campo `tripulacion_id` que los vincula a su tripulación.

---

## 23. Sistema de Oráculos y Tiradas

**Archivo de guía:** `Guias/sistemas/23-oraculos.md`

### Qué es un Oráculo
Un oráculo es una **tabla de resultados aleatorios** consultada mediante tirada de dado. Sustituye al narrador omnisciente para sucesos inciertos.

### Mecánica
1. El jugador o sistema lanza el dado (`dice_type`: d6, d20, d100…).
2. El resultado se cruza con `results_json`.
3. Se registra en `game_post_oracles`.

---

## 24. Sistema de Posts — PV/PE y Modificadores

**Archivo de guía:** `Guias/sistemas/24-sistema-posts.md`

### Registro por Post (`game_post_characters`)
- `pv_change`: cambio de PV en ese post.
- `pe_change`: cambio de PE.
- `pa_declared`: PA que el jugador declara gastar.
- `modifiers_json`: modificadores activos al postar.
- `hidden_actions_json`: acciones no visibles al rival.
- `equipped_snapshot_json`: snapshot del inventario en ese momento.

### Estado por Hilo (`game_thread_pj_state`)
Mantiene `current_pv` y `current_pe` en tiempo real para cada hilo y personaje.

---

## 25. Sistema de Hilos — Metadatos

**Archivo de guía:** `Guias/sistemas/25-hilos-meta.md`

### Metadatos de Hilo (`game_thread_meta`)
| Campo | Descripción |
|---|---|
| `thread_type` | `Presente`, `Flashback`, `Sueño`, etc. |
| `day` | Día in-world del hilo |
| `season` | Estación in-world |
| `year` | Año in-world |

---

## 26. Búsquedas de Rol

**Archivo de guía:** `Guias/sistemas/26-busquedas.md`

Tablón donde los jugadores publican anuncios para encontrar compañeros de rol. Campos: `titulo`, `descripcion`, `imagen_url`, `status` (pendiente/aprobada/denegada), `staff_nota`.

---

## 27. Sistema de Notificaciones

**Archivo de guía:** `Guias/sistemas/27-notificaciones.md`

El sistema notifica revisiones de ficha, cards asignadas, misiones completadas, mensajes directos, eventos de navegación y anuncios del staff. Se marcan como leídas (`is_read = 1`) o descartadas.

---

## 28. Cartas de Equipo Básico (Tienda)

**Archivo de guía:** `Guias/sistemas/28-equipo-basico.md`

### Criterios de "Básico"
- Rango D o C.
- Sin requisitos especiales de raza, estilo o disciplina.
- Sin efectos que distorsionen el balance del foro.
- Precio en Berries razonable para un PJ nuevo.

### Categorías Principales
- **Armas básicas:** espada estándar, pistola de un cañón, arco corto.
- **Armaduras básicas:** capa reforzada, chaleco de cuero.
- **Herramientas:** grappling hook, catalejos, brújula estándar.
- **Consumibles:** vendas medicinales, antídoto genérico, bengala de señales.

---

## 29. Sistema de Despertar (Awakening)

**Archivo de guía:** `Guias/sistemas/37-awakening-frutas.md`

### Qué es el Awakening
El hito de poder máximo de un usuario de Fruta del Diablo. Se gestiona como una card especial ligada a la card de la fruta original del PJ, añadiéndose al inventario sin reemplazar la primera.

### Requisitos de Despertar
Cada fruta tiene condiciones específicas dictadas por el staff en su campo `notes` durante la aprobación original:
- **Uso Mínimo:** Número de veces que la fruta debe haber sido usada en posts registrados (ej. 30 usos para Tier 1-2, 100 usos para Tier 5).
- **Rango Mínimo:** Rango global del personaje requerido (ej. rango B para Tier 1-2, rango S para Tier 4).
- **Condición Narrativa:** Situación específica obligatoria (ej. proteger a alguien, sobrevivir a una situación límite, enfrentarse al mismo elemento).

### La Card de Awakening
- Rango **SS**, independientemente del tier de la fruta original.
- Incluye poderes base drásticamente amplificados y 1-2 habilidades completamente nuevas que reflejan el Despertar.
- Posibilidad de un **Despertar Incompleto** (mitad de usos requeridos) que otorga el poder pero incluye consecuencias negativas (drawbacks) graves al utilizarse.

### Diferencias por Tipo de Fruta
| Clase | Manifestación Narrativa del Awakening |
|---|---|
| `paramecia` | El poder trasciende al cuerpo y se extiende al entorno, pudiendo modificar objetos y escenarios cercanos. |
| `zoan` | Transformación estabilizada, una nueva forma definitiva, con control absoluto, fuerza colosal y mayor stamina. |
| `logia` | Control total y puro del elemento, capacidad de transmitir o imbuir el elemento a seres ajenos permanentemente. |

---

## 30. Clima y Fenómenos — Ampliación de Navegación

**Archivo de guía:** `Guias/sistemas/38-clima-navegacion.md`

### Integración con la Navegación
El clima no es un sistema aislado; funciona como una capa adicional de los **Oráculos de Navegación** (`game_navigation_events`). No requiere tiradas extra, el clima surge directamente como un resultado posible del viaje.

### Eventos Climáticos por Zona
| Zona | Nivel de Peligro | Tipo de Clima |
|---|---|---|
| **Blues** | 1–2 | **Predecible y natural.** (Lluvia moderada, viento favorable, tormentas menores). |
| **Grand Line** | 3 | **Impredecible y caótico.** (Nieve en pleno verano, lluvia de meteoritos pequeños, tornados súbitos). |
| **New World** | 4–5 | **Activo y hostil.** (Islas de fuego flotantes, lluvia de lava, tormentas permanentes, ballenas de tormenta). |

### Efectos Mecánicos
- **En Navegación:** Retraso en la duración del viaje, aumento drástico del peligro base, daño estructural al barco, y desorientación del Log Pose temporalmente.
- **En Combate Naval:** Penalizaciones a los stats de Agilidad/Destreza por el movimiento brusco del navío, inhabilitación de ciertas cards (ej. uso de fuego bajo tormenta severa), ventajas narrativas para Logias afines.

### El Oficio de Navegante ante el Clima
El grado de la disciplina `navegante` otorga la capacidad de mitigar el clima hostil:
- **Grado 1-2:** Puede anticipar el clima y reducir el impacto de un evento climático en 1 nivel de severidad.
- **Grado 3-4:** Permite realizar dos tiradas en el oráculo climático o evadir un clima severo por viaje.
- **Grado 5:** Inmunidad a los eventos climáticos moderados y resiliencia suprema que degrada los climas más mortales a efectos menores.

---

---

# ZONA DE POBLACIÓN — PROMPTS DE IA

> **Cómo usar estos prompts:**
> 1. Copia el bloque completo de la sección que necesites.
> 2. Rellena los campos marcados con [CORCHETES].
> 3. Pega en tu IA preferida.
> 4. El staff revisa el output antes de insertarlo.

---

## P-01 Cards de Equipo Básico (Tienda)

```
CONTEXTO DEL FORO:
Eres diseñador de contenido para un foro de rol de piratas. El sistema de cartas (cards)
tiene estos campos relevantes para equipo básico:

  name          → Nombre de la card
  card_type     → SIEMPRE "equipo"
  rank          → "D" o "C" (equipo básico)
  activation    → "activa" | "pasiva" | "reactiva"
  description   → Descripción narrativa (2-4 frases evocadoras)
  cost_pe       → Coste en PE si es activa (número) o "—" si es pasiva
  dice          → Fórmula de dados (ej: "2d20+fue") o "" si no tiene
  execution_stat → Stat de la tirada (fue/res/agi/des/int/inst/esp) o "" si pasiva
  effects_json  → Array de efectos (strings breves)
  notes         → Restricciones o condiciones (puede ser "")
  cost_berries  → Precio (rango D: 500-2000, rango C: 2000-8000)
  peso          → Armas: 2-3 | Armaduras: 3-4 | Herramientas: 1-2 | Consumibles: 1
  reposo        → Posts de espera antes de reusar (0 si no aplica)
  duracion      → Posts que dura el efecto (0 si instantáneo)
  shop_category → "armas" | "armaduras" | "consumibles" | "utiles"
  tier          → 1 para rango D, 2 para rango C

REGLAS:
1. NO requieren disciplinas o estilos específicos.
2. Nada que dé ventaja masiva en combate; son herramientas útiles.
3. Nombres con sabor a piratas/marines/aventureros.
4. Descripción evocadora e inmersiva, no una ficha técnica.
5. Efectos claros y ejecutables sin ambigüedad.
6. Conecta el objeto con el mundo: puede tener procedencia (East Blue, Wano, Water 7, etc.).

TAREA:
Genera [NÚMERO] cards de equipo básico de la categoría [CATEGORÍA].

Devuelve un array JSON con todos los campos rellenos. Sin explicaciones fuera del JSON.

EJEMPLO:
[
  {
    "name": "Sable de Abordaje East Blue",
    "card_type": "equipo",
    "rank": "C",
    "activation": "activa",
    "description": "Sable de hoja curva forjado en los astilleros de Loguetown. Equilibrado para velocidad.",
    "cost_pe": "10",
    "dice": "2d20+des",
    "execution_stat": "des",
    "effects_json": ["Ataque de filo a corta distancia.", "Ventaja narrativa si el rival está desprevenido."],
    "notes": "",
    "cost_berries": 3500,
    "peso": 2,
    "reposo": 0,
    "duracion": 0,
    "shop_category": "armas",
    "tier": 2
  }
]
```

---

## P-02 Estilos Canónicos + sus Cards de Técnicas

```
CONTEXTO DEL FORO:
Eres diseñador de escuelas de combate para un foro de rol de piratas.

CAMPOS DEL ESTILO CANÓNICO:
  slug            → identificador snake_case (ej: "santoryu")
  name            → Nombre del estilo
  category        → "artes_marciales" | "esgrima" | "tirador" | "estilo_especial" | "ciencia_combate"
  category_label  → Etiqueta legible (ej: "Artes marciales")
  disciplina_slug → cuerpo_a_cuerpo / armas_de_filo / armas_a_distancia / armas_de_fuego / armas_exoticas / armas_de_asta / armas_contundentes
  primary_stat    → fue / res / agi / des / int / inst / esp
  short_desc      → 1 frase que defina el estilo
  description     → 3-5 frases filosóficas y técnicas
  requirements    → Array de 3-4 requisitos (disciplina mínima, stat mínimo, condición narrativa)
  advantages      → Array de 3-4 ventajas mecánicas narrativas
  sort_order      → Número (múltiplo de 10)

CAMPOS DE UNA CARD DE TÉCNICA DEL ESTILO:
  name                  → Nombre de la técnica (evocador, puede ser en otro idioma)
  card_type             → SIEMPRE "tecnica"
  rank                  → "C" | "B" | "A" | "S"
  activation            → "activa" | "pasiva" | "reactiva"
  estilo_canonico_slug  → El slug del estilo
  description           → Cómo se ejecuta, sensación, imagen visual
  cost_pe               → C: 8-15 | B: 16-22 | A: 22-30 | S: 30-50
  dice                  → Fórmula de dados
  execution_stat        → Stat de la tirada
  effects_json          → Array de efectos mecánicos
  notes                 → Restricciones (ej: "Requiere grado III de disciplina")
  reposo                → 0 para técnicas rápidas, 1-3 para poderosas
  duracion              → 0 si instantánea, 1-3 si tiene efecto continuado
  tier                  → 1(C) / 2(B) / 3(A) / 4(S)
  peso                  → SIEMPRE 0 para técnicas

REGLAS:
1. Mínimo 3 cards por estilo: 1 rango C, 1 rango B, 1 rango A. Opcional: 1 rango S.
2. Las técnicas deben ser coherentes con la filosofía del estilo.
3. NO copies técnicas exactas del manga; inspírate y crea versiones propias.
4. Los efectos deben ser ejecutables sin ambigüedad por un árbitro de moderación.

TAREA:
Genera el estilo canónico "[NOMBRE DEL ESTILO]".
Concepto del estilo: [DESCRIPCIÓN QUE DA EL ADMIN]

Devuelve:
1. JSON del estilo canónico.
2. Array JSON de sus cards de técnica (mínimo 3, máximo 6).
```

---

## P-03 Islas-Foros

```
CONTEXTO DEL FORO:
Eres worldbuilder para un foro de rol de piratas. Cada foro del tablón es una isla con identidad propia.

CAMPOS DE UNA ISLA (game_forum_islands):
  leader_name   → Nombre del gobernante o figura de poder actual
  description   → Descripción rica (mínimo 5 frases: historia, cultura, atmósfera, relevancia)
  terrain       → Tipos de terreno (ej: "Selva tropical, acantilados volcánicos")
  climate       → Clima general
  climate_temp  → Rango de temperatura (ej: "24-38 grados C")
  climate_wind  → Vientos predominantes
  climate_precip → Precipitaciones
  buildings     → Lista de edificios y puntos de interés con nombres propios (mínimo 5)
  defenses      → Defensas militares y naturales (específico)
  resources     → Recursos naturales y económicos
  sea_zone      → "east_blue" | "west_blue" | "north_blue" | "south_blue" | "grand_line" | "new_world"
  base_danger   → 1 (pacífica) a 5 (extremadamente peligrosa)
  requires_log_pose → true/false
  coord_x / coord_y → Coordenadas en el mapa (inventadas si no hay mapa)

REGLAS DE WORLDBUILDING:
1. Cada isla debe sentirse ÚNICA y HABITABLE, no solo un mapa.
2. Los puntos de interés deben tener nombres propios creativos.
3. El líder debe tener un nombre sugerente, no solo "El Alcalde".
4. La descripción debe incluir: por qué los piratas van ahí, por qué la Marina la vigila,
   qué leyenda o conflicto la hace especial.
5. Las defensas deben ser creíbles según el nivel de peligro de la zona.
6. Conecta la isla con el lore del mundo cuando sea posible.

TAREA:
Genera la ficha completa de la isla para el foro "[NOMBRE DEL FORO]".
Zona del mundo: [ZONA]
Concepto temático de la isla: [DESCRIPCIÓN BREVE DEL CONCEPTO]

Devuelve un JSON completo con todos los campos.
Después añade "GANCHOS DE TRAMA": 3 ideas de misiones o eventos para esta isla.
```

---

## P-04 Misiones

```
CONTEXTO DEL FORO:
Eres diseñador de misiones para un foro de rol de piratas.

CAMPOS DE UNA MISIÓN (game_missions):
  title         → Título evocador y memorable
  description   → Mínimo 4 frases: contexto, problema, objetivo, consecuencias de fallar
  rank          → "D" | "C" | "B" | "A" | "S" | "SS"
  min_level / max_level → D: 1-10 | C: 5-20 | B: 15-35 | A: 30-60 | S: 50-99
  points_reward → PD: D:1-2 | C:2-4 | B:4-6 | A:6-10 | S:10-20 | SS:20+
  berry_reward  → D:300-800 | C:800-2500 | B:2500-8000 | A:8000-25000 | S:25000+
  isla          → Isla donde ocurre
  categoria     → "combate" | "exploracion" | "sigilo" | "escolta" | "supervivencia" | "diplomacia"
  max_posts     → D:10-15 | C:12-18 | B:18-25 | A:22-30 | S:28-40

REGLAS:
1. El título debe ser memorable y no genérico.
2. La descripción debe tener GANCHOS: un antagonista con nombre, una consecuencia tangible.
3. Añade siempre una "complicación inesperada": algo que puede salir mal incluso bien jugado.
4. Las misiones S/SS deben tener implicaciones mundiales o afectar facciones mayores.

TAREA:
Genera [NÚMERO] misiones de rango [RANGO] para la isla de [ISLA].
Categoría preferida: [CATEGORÍA o "variada"]

Devuelve un array JSON de misiones, cada una con todos los campos más:
  "antagonista": { nombre, descripcion, motivacion }
  "complicacion": descripción del giro inesperado
```

---

## P-05 NPCs Mayores

```
CONTEXTO DEL FORO:
Eres creador de personajes importantes para un foro de rol de piratas.

SISTEMA DE STATS (mismo que los PJs, pero NPCs jefes pueden llegar a 8-9):
  fue, res, agi, des, int, inst, esp → valores numéricos 1-9

ESTRUCTURA DE UN NPC MAYOR:
  nombre → Nombre completo
  identificacion:
    edad, apodo, rango, faccion (pirata/marine/revolucionario/gobierno/civil/otro),
    afiliacion, bounty
  perfil_fisico:
    altura, complexion, rasgos, atuendo, presencia (cómo impacta visualmente)
  psicologia:
    personalidad (3-5 frases), valores (array), miedos (array),
    contradiccion (conflicto interno), habitos (tics y frases características)
  motivaciones:
    corto_plazo, medio_plazo, largo_plazo
  perfil_estrategico:
    estilo_combate, frutas (Akuma no Mi si tiene o null),
    haki (array de tipos que posee), disciplinas (array de slug+grado),
    puntos_fuertes (array), puntos_debiles (array), tacticas_habituales
  cronologia:
    array de { año, evento } — mínimo 3 entradas
  relaciones:
    aliados: [{ nombre, relacion }]
    enemigos: [{ nombre, relacion }]
    neutrales: [{ nombre, relacion }]
  stats:
    { fue, res, agi, des, int, inst, esp }

REGLAS:
1. El NPC debe tener una CONTRADICCIÓN INTERNA que lo haga interesante.
2. Sus stats deben reflejar su rol narrativo.
3. Su historia debe conectar con el lore del foro.
4. Las relaciones deben incluir al menos 1 PJ conocido del foro.
5. El bounty debe ser coherente con sus stats y rango.

TAREA:
Genera el NPC Mayor "[NOMBRE O CONCEPTO]".
Facción: [FACCIÓN]
Rol narrativo: [descripción del admin]
Nivel de poder: [rango de PJ equivalente D/C/B/A/S o descripción]
```

---

## P-06 Lore — Creación desde Cero

~~~
CONTEXTO DEL FORO:
Eres el historiador oficial y cronista del mundo de un foro de rol de piratas. Tu trabajo es
CREAR el lore desde cero. No hay nada preexistente; tú defines la historia, las eras, los
eventos y los periódicos que le darán profundidad al mundo.

El mundo tiene ambientación de piratas en un mar de fantasía: cuatro Mares (Blues), una ruta
central legendaria (Grand Line) y una segunda mitad aún más peligrosa (New World). Existe un
Gobierno Mundial autoritario, piratas que buscan libertad, y revolucionarios que luchan contra
el orden establecido.

Más allá de eso: NADA está escrito. Tú defines los nombres, eventos, misterios e historia.

━━━━━━━━━━━━━━━━━━━━━━━━━
ARQUITECTURA DEL LORE
━━━━━━━━━━━━━━━━━━━━━━━━━

El lore se guarda como un JSON con 4 arrays:
  eras         → Los grandes períodos históricos del mundo
  lore_basal   → Entradas enciclopédicas: civilizaciones, artefactos, lugares míticos, facciones
  eventos      → Sucesos concretos dentro de cada era (guerras, fundaciones, tragedias)
  periodicos   → Noticias in-world con sesgo y drama (presente del foro)

━━━━━━━━━━━━━━━━━━━━━━━━━
BLOQUE 1: ERAS
━━━━━━━━━━━━━━━━━━━━━━━━━

Campos de cada objeto de era:
  numeral     → "I", "II", "III"...
  name        → Nombre evocador y poético (NO literal)
  start_year  → Año de inicio
  end_year    → Año de fin (o null si es la era actual)
  intro_quote → Frase breve y poética que capture la esencia de la era (máx 20 palabras)
  intro_text  → 3-5 frases: qué ocurrió y por qué importa hoy

GUÍA PARA ERAS:
- Cada era tiene un CONFLICTO CENTRAL que la define.
- Se conectan causalmente: la Era I explica por qué existe la Era II.
- Mínimo 2 eras, máximo 5.
- La era actual es la ÚLTIMA, con end_year null o reciente.
- Nombres poéticos. Ej: NO "La Era del Gobierno" → SÍ "El Reinado de los Cuatro Cielos".

━━━━━━━━━━━━━━━━━━━━━━━━━
BLOQUE 2: LORE BASAL (enciclopedia)
━━━━━━━━━━━━━━━━━━━━━━━━━

Campos de cada objeto de lore_basal:
  era_id   → ID numérico de la era (1, 2, 3...)
  name     → Nombre del elemento
  subtype  → historia_prohibida / artefacto_legendario / geografia_mitica /
             faccion / personaje_historico / fenomeno_natural / organizacion_secreta
  desc     → UNA sola frase descriptiva (máx 20 palabras, para índices y tooltips)
  details  → Descripción extendida en HTML (mínimo 200 palabras).
             Estructura recomendada:
               Párrafo 1 — Qué es y por qué importa.
               Párrafo 2 — Historia o contexto detallado.
               Párrafo 3 — Estado actual / cómo afecta a los PJs.
             Perspectiva SIEMPRE in-world (como un libro de historia del mundo).
             Para referenciar otro elemento usa: <a href='#' class='rpg-lore-link' data-lore-id='ID'>nombre</a>

GUÍA PARA LORE BASAL:
- Deja preguntas sin responder: el misterio invita a explorar.
- Los artefactos legendarios tienen CONSECUENCIAS si alguien los encuentra.
- Las facciones tienen motivaciones comprensibles aunque sean villanas.
- Añade CONTRADICCIONES entre fuentes: distintas facciones tienen versiones distintas del mismo hecho.

━━━━━━━━━━━━━━━━━━━━━━━━━
BLOQUE 3: EVENTOS HISTÓRICOS
━━━━━━━━━━━━━━━━━━━━━━━━━

Campos de cada objeto de eventos:
  era_id     → ID numérico de la era
  name       → Nombre breve y memorable
  type       → guerra / fundacion / descubrimiento / politica / catastrofe / traicion / rebelion / exterminio
  start_year → Año de inicio
  end_year   → Año de fin (igual que start_year si fue puntual)
  desc       → Una frase para índices y tooltips
  details    → Descripción extendida (mínimo 150 palabras):
               antecedentes, qué ocurrió, protagonistas, cómo terminó.
               Tono épico o trágico según el evento.
  ubicacion  → Dónde ocurrió (isla, mar, o "Global")
  impacto    → Consecuencia para el presente: qué ley nació, qué odio persiste, qué secreto se guarda.

GUÍA PARA EVENTOS:
- Consecuencias VIVAS en el presente, no solo historia muerta.
- Al menos 1 evento por era con PIRATAS, 1 con GOBIERNO, 1 con REVOLUCIONARIOS.
- Las traiciones son las más poderosas narrativamente.
- Las catástrofes dejan cicatrices que los jugadores pueden encontrar.

━━━━━━━━━━━━━━━━━━━━━━━━━
BLOQUE 4: PERIÓDICOS IN-WORLD
━━━━━━━━━━━━━━━━━━━━━━━━━

Campos de cada objeto de periodicos:
  headline → TITULAR EN MAYÚSCULAS, impactante y corto
  date     → "Año X — Edición [nombre evocador]"
  snippet  → 1-2 frases de vista previa (sin spoilers del artículo)
  content  → Artículo completo en HTML (mínimo 3 párrafos).
             Tono según facción del periódico:
               PRO-GOBIERNO     → minimiza pérdidas, glorifica Marina, demoniza piratas
               PIRATA/UNDERGROUND → exagera logros piratas, acusa al Gobierno de mentir
               NEUTRAL          → datos escuetos, interpretación libre al lector
             Para enlazar lore: <a href='#' class='rpg-lore-link' data-lore-id='ID'>texto</a>

GUÍA PARA PERIÓDICOS:
- El sesgo debe ser OBVIO pero nunca declarado: muéstralo, no lo digas.
- Incluye un detalle falso deliberado que los jugadores puedan detectar.
- Cada periódico menciona al menos 1 elemento de lore con enlace.

━━━━━━━━━━━━━━━━━━━━━━━━━
PRINCIPIOS DE ESCRITURA
━━━━━━━━━━━━━━━━━━━━━━━━━

1. PERSPECTIVA IN-WORLD     → El narrador nunca rompe la cuarta pared.
2. MISTERIO CONTROLADO      → Cada respuesta genera 2 nuevas preguntas.
3. COHERENCIA INTERNA       → Nombres, fechas y causas consistentes en todo el lore.
4. ÉPICA + TRAGEDIA         → Entre grandiosidad épica y tragedia humana concreta.
5. TONO NUNCA GENÉRICO      → Nombres propios, números exactos, consecuencias concretas.
   EVITA: "un gran conflicto", "muchos murieron", "las cosas cambiaron".
6. CONSECUENCIAS JUGABLES   → Todo debe poder ser un gancho de misión o secreto a descubrir.

━━━━━━━━━━━━━━━━━━━━━━━━━
TAREA — RELLENA ESTOS CAMPOS Y ENVÍA
━━━━━━━━━━━━━━━━━━━━━━━━━

  NOMBRE DEL MUNDO O FORO  → [NOMBRE]
  NÚMERO DE ERAS           → [NÚMERO, mínimo 2]
  TONO GENERAL             → [oscuro y trágico / épico y esperanzador / conspirativo y misterioso / otro]
  AÑO ACTUAL DEL FORO      → [AÑO]
  FACCIONES PRINCIPALES    → [lista separada por comas]
  RESTRICCIONES DE LORE    → [cosas que NO deben existir o nombrarse, o "ninguna"]
  SEMILLA DE HISTORIA      → [concepto central — descripción libre]

CON ESA INFORMACIÓN, GENERA EN ORDEN:
  1. Las [NÚMERO] ERAS completas.
  2. Mínimo 3 entradas de LORE BASAL por era (varía los subtipos).
  3. Mínimo 4 EVENTOS por era (al menos 1 guerra, 1 fundación, 1 traición o catástrofe).
  4. 3 PERIÓDICOS del año actual del foro (1 pro-gobierno, 1 pirata/underground, 1 neutral).

FORMATO DE SALIDA — un único JSON con esta estructura raíz:
  {
    "eras": [ ... ],
    "lore_basal": [ ... ],
    "eventos": [ ... ],
    "periodicos": [ ... ]
  }

Después del JSON añade "SEMILLAS DE TRAMA": 5 ideas de misiones o arcos narrativos que
nazcan directamente del lore que acabas de crear.
~~~

---

## P-07 Premios con Puntos Destino

```
CONTEXTO DEL FORO:
Eres diseñador del catálogo de recompensas de un foro de rol de piratas. Los Puntos Destino
(PD) son una moneda de mérito que los jugadores acumulan y gastan en premios especiales.

CAMPOS DE CADA PREMIO:
  item_type   → Categoría del premio
  item_slug   → Identificador único en snake_case
  item_name   → Nombre legible
  pd_cost     → Coste en PD
  descripcion → Qué otorga exactamente (3-5 frases claras)
  restricciones → Condiciones de uso o limitaciones
  proceso     → Cómo se aplica (automático / solicitud staff / ambos)

CATEGORÍAS:
  "cosmetico"         → Elementos visuales y de personalización
  "extra_personaje"   → Beneficios meta del personaje
  "booster_progresion" → Aceleradores de crecimiento
  "card_especial"     → Cards únicas no disponibles en tienda
  "contenido_narrativo" → Experiencias de rol exclusivas

REGLAS:
1. Los premios son TENTADORES pero no pay-to-win.
2. Cosméticos: 2-10 PD. Poderosos: 20-50+ PD.
3. Los boosters aceleran, no omiten el sistema de rangos.
4. Las cards especiales de PD son únicas (1 copia o personalizables).
5. El contenido narrativo siempre requiere aprobación del staff.

TAREA:
Genera el catálogo completo de premios PD. Mínimo:
  - 4 premios de "cosmetico" (2-10 PD)
  - 3 premios de "extra_personaje" (5-30 PD)
  - 3 premios de "booster_progresion" (10-40 PD)
  - 3 premios de "card_especial" (15-50 PD)
  - 2 premios de "contenido_narrativo" (20-60 PD)

Contexto del foro: [DESCRIPCIÓN BREVE DEL TONO Y REGLAS DEL FORO]
```

---

## P-08 Razas — Pasivas, Stats y Puntos de Linaje

```
CONTEXTO DEL FORO:
Eres diseñador de razas jugables para un foro de rol de piratas. Los stats son 7 atributos
con rangos 1-6 (fue, res, agi, des, int, inst, esp).

ESTRUCTURA DE UNA RAZA:
  slug               → snake_case identificador único
  nombre             → Nombre de la Raza
  descripcion_corta  → 1 frase que define su esencia
  descripcion        → 4-6 frases: origen, cultura, dónde viven, relación con el mundo pirata
  stat_modifiers     → { fue: X, res: X, agi: X, des: X, int: X, inst: X, esp: X }
                       Solo modificadores relevantes (el resto son 0). Deben equilibrarse.
  stat_caps          → Solo si algún stat tiene techo diferente al estándar (rango 6)
  pasivas_primarias  → Array de { nombre, descripcion, mecanica }
  pasivas_secundarias → Array de { nombre, descripcion, mecanica }
  puntos_linaje:
    descripcion      → Qué representan narrativamente
    como_se_ganan    → En qué situaciones de rol o mecánicas se otorgan
    catalogo_linaje  → Array de { nombre, costo_pl, descripcion, requisito }
  cards_raciales     → Array de cards tipo "tecnica" con notes "Solo disponible para [RAZA]"
                       Campos: name, rank, activation, description, cost_pe, dice,
                               execution_stat, effects_json, notes
  restricciones      → Array de limitaciones de la raza
  compatibilidad_akuma → true / false
  nota_staff         → Instrucciones especiales al aprobar la raza

REGLAS:
1. Los modificadores deben equilibrarse: +2 en algo implica -1/-2 en otro.
2. Las pasivas no replican exactamente lo que hace una card (son pasivas, no activas).
3. Los puntos de linaje conectan con la narrativa de la raza.
4. Las cards raciales son ÚNICAS, imposibles de obtener sin ser de esa raza.
5. Las restricciones tienen lógica narrativa, no son arbitrarias.

TAREA:
Genera la raza jugable "[NOMBRE DE LA RAZA]".
Concepto: [DESCRIPCIÓN DEL ADMIN]
Nivel de poder relativo: [normal / fuerte / especial]

Devuelve el JSON completo de la raza.
Después añade "GUÍA DE ROL": 3 consejos para jugar esta raza de forma memorable.
```

---

## P-09 Rutas de Navegación

```
CONTEXTO DEL FORO:
Eres cartógrafo de un foro de rol de piratas. El sistema de navegación registra rutas entre
islas con distancias y niveles de peligro.

ZONAS Y PELIGRO BASE:
  east/west/north/south blue → peligro 1-2
  grand_line                 → peligro 3
  new_world                  → peligro 4-5

CAMPOS DE UNA RUTA:
  island_from       → Nombre de la isla origen
  island_to         → Nombre de la isla destino
  distance          → 1-50 (unidades del foro; afecta días de viaje)
                      Vecinas mismo Blue: 3-8 | Distantes mismo Blue: 8-15
                      Cruzar a Grand Line: 15-25 | Grand Line interno: 10-30
                      New World: 15-40
  danger_override   → null (normal para la zona) o 1-5 (si hay algo especial)
  waypoints         → Array de islas menores que se cruzan (sin ser parada)
  descripcion_ruta  → Descripción narrativa del viaje (corrientes, peligros, fenómenos)
  log_pose_notes    → Comportamiento especial del Log Pose en esta ruta (si aplica)

CUÁNDO USAR danger_override:
  +1 → corriente traicionera, niebla permanente, zona de depredadores
  +2 → fenómeno climático permanente, zona volcánica, triángulo de anomalías
  -1 → ruta muy conocida y bien patrullada por la Marina

REGLAS:
1. Cada ruta tiene algo ESPECIAL: no existen rutas aburridas en el Grand Line.
2. Los waypoints son islas menores sin foro propio, útiles para escalas narrativas.
3. La descripción da ganchos de aventura potenciales.

TAREA:
Genera las rutas de navegación para estas conexiones:
[ADMIN proporciona: lista de pares ISLA-A → ISLA-B con zona]

Para cada par devuelve el JSON de la ruta más la descripción narrativa del viaje.
Agrupa las rutas por zona del mundo.
Después del JSON añade un "MAPA CONCEPTUAL" en texto ASCII mostrando las conexiones.
```

---

## P-10 Cards de Técnicas / NPC Menores / Barcos / Haki / Akuma

```
CONTEXTO DEL FORO:
Eres diseñador de cards avanzadas para un foro de rol de piratas.

━━━━━━━━━━━━━━━━━━━━━━━━━
TIPO: tecnica (sin estilo canónico asignado)
━━━━━━━━━━━━━━━━━━━━━━━━━
Para técnicas personalizadas de un PJ o técnicas genéricas.
  estilo_canonico_slug → null
  disciplina_slug      → disciplina base
  Todos los rangos posibles (C a S)

━━━━━━━━━━━━━━━━━━━━━━━━━
TIPO: haki
━━━━━━━━━━━━━━━━━━━━━━━━━
Para técnicas de Haki. El tipo se indica en tags_json:
  "haki_armamento" | "haki_observacion" | "haki_conquistador"
  Básico (C/B): cost_pe 10-20
  Avanzado (A/S): cost_pe 20-35
  Conquistador infundido (A/S): cost_pe 30-50, solo con disciplina haki_conquistador grado 3+
  Efectos describen aspecto visual + mecánico.

━━━━━━━━━━━━━━━━━━━━━━━━━
TIPO: npc_menor
━━━━━━━━━━━━━━━━━━━━━━━━━
Para aliados, bestias o subordinados menores.
  activation  → SIEMPRE "pasiva" (el NPC existe, no se activa)
  cost_pe     → "—"
  dice        → ""
  effects_json → Sus capacidades en un post (qué puede hacer)
  notes       → Cuántas veces puede actuar por hilo, condiciones de uso
  peso        → Slot companion (3-5)
  description → Presenta al NPC: especie, aspecto, carácter

━━━━━━━━━━━━━━━━━━━━━━━━━
TIPO: barco
━━━━━━━━━━━━━━━━━━━━━━━━━
Para embarcaciones de los PJs.
  activation  → "pasiva"
  cost_pe     → "—"
  effects_json → velocidad, capacidad de tripulación, bonus navegación, resistencia, especiales
  peso        → Tamaño: barca=1, bergantín=2, galeón=3, navío=4, legendario=5
  Rango y zonas accesibles:
    D → solo Blues
    C → Grand Line
    B/A → cualquier zona
    S/SS → cualquier zona con bonus

━━━━━━━━━━━━━━━━━━━━━━━━━
TIPO: akuma_no_mi
━━━━━━━━━━━━━━━━━━━━━━━━━
Card base que representa el poder de la fruta (1 por fruta).
  activation  → "pasiva" (la fruta siempre está activa una vez comida)
  cost_pe     → "—"
  effects_json → poder principal, transformaciones si aplica, inmunidades
  notes       → "El usuario no puede nadar. Inútil sumergido o con Kairoseki."
  Las técnicas activas de la fruta son cartas separadas de tipo "tecnica" con el tag de la fruta.

REGLAS GENERALES:
1. Para npc_menor: description debe presentar al NPC con nombre y personalidad.
2. Para barcos: el nombre del barco es el "name" de la card.
3. Para Haki: cada grado de disciplina debería tener al menos 1 carta accesible.
4. Las cartas de Akuma no Mi base son pasivas; las técnicas activas son cartas separadas.

TAREA:
Genera [NÚMERO] cards del tipo "[TIPO]".
[ADMIN añade: para qué PJ, nivel de poder, temática específica]

Devuelve un array JSON con todas las cards, cada una con TODOS los campos del esquema.
```

---

---

## NOTAS FINALES PARA EL STAFF

### Orden de Creación de Contenido Recomendado
1. **Lore** (P-06) — el mundo debe existir antes que nada.
2. **Razas** (P-08) — define la base del PJ.
3. **Equipo básico** (P-01) — para que los nuevos puedan comprarse algo de inicio.
4. **Estilos Canónicos** (P-02) — define qué escuelas de combate existen.
5. **Islas-Foros** (P-03) — rellena el mundo antes de abrir el foro.
6. **NPCs Mayores** (P-05) — antagonistas y aliados del primer arco.
7. **Misiones** (P-04) — el tablón de misiones del primer arco.
8. **Rutas** (P-09) — conexiones entre islas abiertas.
9. **Premios PD** (P-07) — el catálogo de la tienda de PD.
10. **Cards especiales** (P-10) — Haki, Akuma, Barcos, NPCs menores.

### Checklist Antes de Publicar Contenido Generado
- [ ] El contenido no contradice el lore existente.
- [ ] Los stats / rangos son coherentes con el balance actual.
- [ ] Los nombres no son calcos exactos del manga/anime.
- [ ] Se han revisado las restricciones y condiciones especiales.
- [ ] Si es una card → insertada en `game_cards` con `created_by = [uid staff]`.
- [ ] Si es un NPC mayor → creado en `game_npc_profiles`.
- [ ] Si es una isla → rellenado `game_forum_islands` para el foro correspondiente.
- [ ] Si es lore → actualizado `lore.json` con las nuevas entradas.

---

*Documento v2.0 — Junio 2026*
