# 7. DISCIPLINAS DE COMBATE — GUÍA COMPLETA

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 7
> **Propósito:** Documentar exhaustivamente el subsistema de disciplinas de combate: definición, catálogo completo, grados I–V, modelo de datos, servicios PHP, integración con cards, costes de progresión, flujos de adquisición y mejora, validaciones, filosofía de diseño, y consejos para jugadores y staff.

---

## ÍNDICE

1. [Arquitectura General](#1-arquitectura-general)
2. [¿Qué es una Disciplina?](#2-qué-es-una-disciplina)
3. [Catálogo de Disciplinas](#3-catálogo-de-disciplinas)
4. [Grados (I–V)](#4-grados-i-v)
5. [Modelo de Datos](#5-modelo-de-datos)
6. [Servicios PHP — Capa de Helpers](#6-servicios-php)
7. [Flujo de Adquisición de Disciplina](#7-flujo-de-adquisición-de-disciplina)
8. [Flujo de Mejora de Grado](#8-flujo-de-mejora-de-grado)
9. [Integración con Cards](#9-integración-con-cards)
10. [Integración con Estilos Canónicos](#10-integración-con-estilos-canónicos)
11. [Costes de Progresión Detallados](#11-costes-de-progresión-detallados)
12. [Filosofía de Diseño](#12-filosofía-de-diseño)
13. [Consejos para Jugadores](#13-consejos-para-jugadores)
14. [Consejos para Staff](#14-consejos-para-staff)
15. [Referencia Rápida](#15-referencia-rápida)

---

## 1. Arquitectura General

### 1.1 Capas del Subsistema

```
┌─────────────────────────────────────────────────────────────────────┐
│                       FRONTEND (Navegador)                           │
│  ┌──────────────────┐  ┌────────────────────┐  ┌─────────────────┐   │
│  │ personaje_page.js│  │ character_competen-│  │ crear_personaje │   │
│  │ (Gestión →       │  │ cias_get.js        │  │ .js             │   │
│  │  Disciplinas)    │  │ (AJAX load)        │  │ (wizard step 2) │   │
│  └────────┬─────────┘  └────────┬───────────┘  └────────┬────────┘   │
│           │                     │                        │            │
│           ▼                     ▼                        ▼            │
│  ┌──────────────────────────────────────────────────────────────────┐│
│  │              AJAX (game/ajax/*.php)                               ││
│  │  disciplines_list | acquire_competencia | upgrade_competencia     ││
│  │  character_competencias_get | character_disciplinas_save         ││
│  └────────────────────────────┬─────────────────────────────────────┘│
└───────────────────────────────┼──────────────────────────────────────┘
                                │ HTTP POST/GET + JSON
┌───────────────────────────────┼──────────────────────────────────────┐
│  ┌────────────────────────────▼─────────────────────────────────────┐│
│  │              PHP — CAPA DE HELPERS                                ││
│  │  disciplinas_helpers.php     — CRUD de disciplinas                ││
│  │  grado_helpers.php           — Grados I–V (compartido oficios)   ││
│  │  oficios_helpers.php         — Reutiliza grado_helpers            ││
│  │  estilos_canonicos_helpers.php — Vinculación disciplina→estilo    ││
│  └──────────────────────────────────────────────────────────────────┘│
│                              │                                        │
│                              ▼                                        │
│  ┌──────────────────────────────────────────────────────────────────┐│
│  │              MySQL — game_disciplinas + game_character_disciplinas││
│  │              game_cards.disciplina_slug (FK lógica)               ││
│  │              game_estilos_canonicos.disciplina_slug (FK lógica)   ││
│  └──────────────────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────────────┘
```

### 1.2 Filosofía de la Arquitectura

**¿Por qué helpers funcionales en lugar de una clase Service?**

Las disciplinas y oficios comparten el mismo sistema de grados (I–V) con variaciones de coste. En lugar de duplicar lógica en dos servicios, `grado_helpers.php` contiene las funciones compartidas (`game_grado_upgrade_price`, `game_grado_nivel_required`, `game_grado_cooldown_ok`) y cada subsistema específico (`disciplinas_helpers.php`, `oficios_helpers.php`) las invoca con el parámetro `competenciaType`.

**¿Por qué `game_character_disciplinas` como tabla separada en lugar de un JSON array en data_json?**

- **Consultas eficientes:** El staff necesita responder "¿qué personajes tienen disciplina X en grado III+?" para crear arcos narrativos. Una tabla relacional permite JOINs directos sin escanear JSON.
- **Integridad referencial:** La FK lógica a `game_disciplinas.id` evita slugs huérfanos.
- **Rendimiento en listas:** Al cargar la biblioteca de personajes, obtener las disciplinas de cada uno es un JOIN simple, no un parseo de JSON columna.
- **Historial:** La columna `learned_at` permite saber cuándo se adquirió cada disciplina, útil para auditoría y cooldowns.

**¿Por qué el cooldown entre mejoras de grado es global y no por disciplina?**

El dato `grado_last_upgrade_at` y `grado_last_upgrade_rank` se almacenan en `data_json` del personaje, **no** por disciplina/oficio individual. Esto significa que si un personaje sube su disciplina Cuerpo a Cuerpo a grado II, el cooldown aplica para TODAS las competencias (disciplinas y oficios). La decisión es intencional:

1. **Evita grinding secuencial:** Si el cooldown fuera por disciplina, un jugador podría subir Cuerpo a Cuerpo un día, Armas de Filo al siguiente, y Escudo al tercero. Con cooldown global, cada mejora requiere una pausa reflexiva.
2. **Simplifica la UI:** Un solo reloj de cooldown visible en la interfaz, no N relojes.
3. **Ritmo de progresión:** Una mejora cada 7–30 días mantiene el ritmo de progresión alineado con la actividad de posting.

### 1.3 Impacto RPG

| Decisión arquitectónica | Lo que significa para el juego |
|------------------------|-------------------------------|
| Tabla separada `game_character_disciplinas` | El staff puede consultar "todos los espadachines grado III+" en una query |
| Cooldown global entre mejoras | Subir una disciplina requiere pausa estratégica: ¿subo mi arma principal o desbloqueo una nueva? |
| PP cost escalado por cantidad poseída | La primera disciplina extra es barata; la cuarta es carísima. El sistema incentiva profundizar antes que abarcar. |
| Haki Conquistador como `staff_grant_only` | Nadie puede comprarlo. Es un regalo del staff ligado a trama. |

---

## 2. ¿Qué es una Disciplina?

### 2.1 Definición

Una **disciplina de combate** es una familia de habilidades marciales que el personaje domina progresivamente a través de **grados** (I a V). Cada disciplina agrupa técnicas, armas y estilos de lucha bajo un mismo paraguas temático y mecánico.

Las disciplinas son el equivalente a "clases" o "árboles de habilidades" en otros sistemas RPG, pero con un enfoque temático de One Piece: no hay clases rígidas (un personaje puede aprender múltiples disciplinas), pero cada una requiere inversión dedicada de PP y tiempo narrativo.

### 2.2 Relación Jerárquica

```
Mundo (One Piece)
  └── Personaje
        ├── Disciplinas de Combate (este documento)
        │     ├── grado I  → cards tier 1
        │     ├── grado II → cards tier 2, estilos canónicos básicos
        │     ├── grado III → cards tier 3, estilos avanzados
        │     ├── grado IV → cards tier 4, estilos maestros
        │     └── grado V  → cards tier 5, técnicas legendarias
        ├── Estilos Canónicos (Sección 8)
        │     └── Especializaciones narrativas atadas a una disciplina base
        ├── Oficios (Sección 9)
        │     └── Competencias no combativas (usan mismo sistema de grados)
        └── Haki (Sección 10)
              └── Poderes espirituales (Observación, Armamento, Conquistador)
```

### 2.3 Lo que una Disciplina NO es

- **No es una clase exclusiva:** Un personaje puede tener 2, 3 o más disciplinas (con coste creciente).
- **No es un estilo canónico:** Los estilos son escuelas narrativas (Rokushiki, Karate Gyojin) que se apoyan sobre una disciplina base. Un personaje puede tener "Armas de Filo grado III" sin tener "Estilo Santōryū", pero no al revés.
- **No es un stat:** Los stats (FUE, RES, AGI, etc.) son la base física del personaje. Las disciplinas son el entrenamiento específico que canaliza esa base física en técnicas concretas.

### 2.4 Diseño de la Progresión

Cada disciplina progresa independientemente en grados I→V. Subir de grado:

1. **Cuesta PP** (mayor que subir stats, pero menor que un rango de stat completo).
2. **Requiere nivel global** (no puedes tener grado IV si tu rango global es D).
3. **Tiene cooldown real** (7–30 días entre mejoras).
4. **Requiere solicitud al staff** (no es automático como comprar stats).

**¿Por qué requiere staff?** A diferencia de los stats (que son numéricos y auto-validables), subir una disciplina tiene implicaciones narrativas: el personaje debe haber entrenado, encontrado un maestro, o completado un arco de trama que justifique el nuevo grado. El staff evalúa si la solicitud tiene respaldo IC.

### 2.5 ¿Qué NO pueden hacer las disciplinas?

- Una disciplina por sí sola no otorga técnicas. Las técnicas son **cards** que se adquieren por separado (vía solicitud al staff). La disciplina es el **requisito de acceso**.
- Una disciplina no da bonus directos a stats. Los bonus vienen del equipamiento, las cards de tipo `haki`, o los perks de linaje.
- Una disciplina no sustituye a la narrativa. Tener "Armas de Filo grado V" sin haber entrenado IC es incoherente y el staff puede rechazar la solicitud.

---

## 3. Catálogo de Disciplinas

### 3.1 Lista Completa

| # | Slug | Nombre | Categoría | Ícono | `staff_grant_only` | `requires_esp_rank` |
|:-:|------|--------|-----------|-------|:------------------:|:-------------------:|
| 1 | `cuerpo_a_cuerpo` | Cuerpo a Cuerpo | Combate | `fa-fist-raised` | 0 | null |
| 2 | `armas_de_filo` | Armas de Filo | Combate | `fa-sword` | 0 | null |
| 3 | `armas_de_asta` | Armas de Asta | Combate | `fa-axe-battle` | 0 | null |
| 4 | `armas_contundentes` | Armas Contundentes | Combate | `fa-hammer` | 0 | null |
| 5 | `armas_a_distancia` | Armas a Distancia | Combate | `fa-bow-arrow` | 0 | null |
| 6 | `armas_de_fuego` | Armas de Fuego | Combate | `fa-gun` | 0 | null |
| 7 | `armas_exoticas` | Armas Exóticas | Combate | `fa-ring` | 0 | null |
| 8 | `escudo` | Escudo | Combate | `fa-shield-alt` | 0 | null |
| 9 | `haki_conquistador` | Haki de Conquistador | Especial | `fa-crown` | 1 | null |

### 3.2 Disciplina 1: `cuerpo_a_cuerpo`

**Nombre:** Cuerpo a Cuerpo
**Slug:** `cuerpo_a_cuerpo`
**Categoría:** Combate
**Requiere staff:** No
**Requiere ESP:** No

**Qué representa:** El combate sin armas. Puños, patadas, cabezazos, artes marciales, técnicas de presión, lucha libre, y cualquier forma de combate que use el cuerpo como arma principal. Incluye estilos como Karate Gyojin, Rokushiki (Soru, Geppo, Tekkai), Fishman Karate, y técnicas de puño genéricas.

**Armas/estilos que caen aquí:**
- Puños y patadas (cualquier estilo marcial)
- Técnicas de presión (Fisherman Karate — agua a presión)
- Rokushiki (Soru, Geppo, Tekkai, Rankyaku — aunque Rankyaku usa piernas, es técnica de cuerpo)
- Dials de combate corporal (Impact Dial, Reject Dial)
- Técnicas de brazo/extremidad sin arma

**Ejemplos de cards asociadas:**
| Card | Tier | Rango típico | Descripción |
|------|:----:|:------------:|-------------|
| Gomu Gomu no Pistol | 1 | D–C | Estiramiento de brazo para golpe lejano |
| Tekkai (Hierro) | 2 | C–B | Endurecimiento corporal defensivo |
| Gyojin Karate: Samegare | 3 | B–A | Ola de presión de agua |
| Rankyaku (Tempestad) | 4 | A–S | Patada cortante a distancia |
| King Kong Gun | 5 | S–SS | Puño gigante recubierto de Haki |

**Tips para jugadores:**
- Es la disciplina más versátil: no requiere equipamiento, nunca te quedas sin arma.
- Sin embargo, carece del alcance de las armas de filo o distancia. Necesitas acercarte.
- Sinergiza naturalmente con Haki de Armamento (endurecimiento de puños).
- Si tu personaje es un peleador callejero, un luchador, o un artista marcial, esta es tu disciplina principal.

### 3.3 Disciplina 2: `armas_de_filo`

**Nombre:** Armas de Filo
**Slug:** `armas_de_filo`
**Categoría:** Combate

**Qué representa:** Toda arma cuyo daño principal proviene de un filo cortante. Espadas, katanas, sables, cuchillos, dagas, hachas de filo, guadañas, y cualquier arma que corte. Es la disciplina más icónica del mundo de One Piece (Zoro, Mihawk, Vista, Roger).

**Armas/estilos que caen aquí:**
- Katanas y espadas de una o dos manos
- Sables, estoques, cimitarras
- Cuchillos de combate, dagas, kunais de filo
- Guadañas, hoces de combate
- Estilos: Santōryū (tres espadas), Nittōryū (dos), Ittōryū (una), Happō (ocho)

**Ejemplos de cards asociadas:**
| Card | Tier | Rango típico | Descripción |
|------|:----:|:------------:|-------------|
| Corte Básico | 1 | D | Tajo simple con arma blanca |
| Oni Giri (Corte de Ogro) | 2 | C | Corte diagonal rápido |
| Tatsu Maki (Torbellino) | 3 | B | Corte giratorio con alcance amplio |
| Yakkodori (Golpe de Fénix) | 4 | A | Corte con cambio de dirección impredecible |
| Isshin — Daishinkan | 5 | S | Corte supremo de estilo Isshin |

**Tips para jugadores:**
- La disciplina con más contenido temático y cards disponibles.
- Sinergiza con DES (destreza) como stat principal de ataque.
- Un espadachín sin Haki de Armamento es incompleto: necesitas Busoshoku para dañar Logias y endurecer tu espada.
- Considera combinarla con `cuerpo_a_cuerpo` si tu personaje pelea con espada + puños.

### 3.4 Disciplina 3: `armas_de_asta`

**Nombre:** Armas de Asta
**Slug:** `armas_de_asta`
**Categoría:** Combate

**Qué representa:** Armas de mango largo con cabeza ofensiva. Lanzas, naginatas, alabardas, tridentes, picas, bidentes, y cualquier arma donde el filo/punta esté al final de un asta. En One Piece es el arma típica de guardias, soldados, y algunos piratas (Whitebeard con su bisento, Kizaru con su espada de luz —aunque él es más filo—, los guardias de Impel Down).

**Armas/estilos que caen aquí:**
- Lanzas de una o dos manos
- Naginata (lanza japonesa con filo curvo)
- Tridente, bidente
- Alabarda, guja
- Pica larga de caballería
- Bastones largos con punta (bo staff con punta metálica)

**Ejemplos de cards asociadas:**
| Card | Tier | Rango típico | Descripción |
|------|:----:|:------------:|-------------|
| Estocada de Lanza | 1 | D | Ataque básico de penetración |
| Barrido de Naginata | 2 | C | Corte amplio con asta |
| Torbellino de Asta | 3 | B | Giro continuo para defensa y ataque |
| Tridente Certero | 4 | A | Ataque preciso que ignora armadura |
| Terremoto de Bisento | 5 | S | Golpe con asta que rompe el terreno |

**Tips para jugadores:**
- Las armas de asta ofrecen el mejor alcance cuerpo a cuerpo (superan a espadas y puños).
- Son versátiles: pueden cortar (naginata), perforar (lanza), o golpear (asta contundente).
- Combinan bien con escudo (lanza + escudo es un arquetipo clásico).
- El alcance extra permite ataques de "zona de control" en combates grupales.

### 3.5 Disciplina 4: `armas_contundentes`

**Nombre:** Armas Contundentes
**Slug:** `armas_contundentes`
**Categoría:** Combate

**Qué representa:** Armas que dañan por impacto, aplastamiento o presión, sin filo. Manguales, martillos, mazas, porras, bates, bastones pesados, nudilleras. También incluye escudos usados ofensivamente (embestir con escudo) y el propio cuerpo si se usa en golpes contundentes (aunque eso es primariamente cuerpo_a_cuerpo, la línea es difusa).

**Armas/estilos que caen aquí:**
- Martillos de guerra, mazas
- Manguales, cadenas con peso
- Porras, bastones pesados (metal, roble macizo)
- Bate de béisbol/palo de cricket
- Nudilleras de metal
- Puños con armadura pesada (guanteletes con peso)

**Ejemplos de cards asociadas:**
| Card | Tier | Rango típico | Descripción |
|------|:----:|:------------:|-------------|
| Martillazo | 1 | D | Golpe descendente básico |
| Impacto Contundente | 2 | C | Golpe que empuja al rival hacia atrás |
| Rompehuesos | 3 | B | Ataque que aturde (penaliza AGI del rival) |
| Demolición | 4 | A | Golpe que daña armaduras y estructuras |
| Terremoto de Martillo | 5 | S | Impacto con onda expansiva |

**Tips para jugadores:**
- Las armas contundentes son efectivas contra armaduras (el impacto se transfiere aunque el filo no penetre).
- Sinergizan con FUE (fuerza): a más fuerza, más daño de impacto.
- Son menos letales que las de filo (narrativamente: dejas rivales inconscientes, no muertos). Ideal para piratas que no quieren matar.
- Combinan con Escudo para un tanque pesado.

### 3.6 Disciplina 5: `armas_a_distancia`

**Nombre:** Armas a Distancia
**Slug:** `armas_a_distancia`
**Categoría:** Combate

**Qué representa:** Armas arrojadizas o de proyectil no explosivo. Arcos, flechas, shurikens, cuchillos arrojadizos, kunai, cerbatanas, bumeranes, hondas, redes. Todo lo que se lanza con fuerza física (no pólvora).

**Armas/estilos que caen aquí:**
- Arcos largos, arcos compuestos, arcos recurvos
- Shurikens, kunai, cuchillos de lanzar
- Cerbatanas con dardos
- Bumeranes, hachas arrojadizas
- Redes de combate (teppō), cadenas con ancla
- Balas de honda

**Ejemplos de cards asociadas:**
| Card | Tier | Rango típico | Descripción |
|------|:----:|:------------:|-------------|
| Tiro Básico | 1 | D | Proyectil simple |
| Lluvia de Agujas | 2 | C | Ráfaga de múltiples proyectiles |
| Flecha Perforante | 3 | B | Proyectil que atraviesa armaduras ligeras |
| Tiro Cegador | 4 | A | Ataque que apunta a los ojos |
| Red de Captura | 1–3 | C–B | Inmoviliza al rival |

**Tips para jugadores:**
- La reina del combate a distancia sin pólvora. Te permite dañar sin exponerte.
- Sinergiza con DES (destreza) para precisión.
- Limitación: munición finita (a diferencia de cuerpo_a_cuerpo). Necesitas gestionar flechas/shurikens.
- Excelente combinación con Venenos (dardos envenenados) y Haki de Observación (para tiro de precisión).

### 3.7 Disciplina 6: `armas_de_fuego`

**Nombre:** Armas de Fuego
**Slug:** `armas_de_fuego`
**Categoría:** Combate

**Qué representa:** Armas que usan pólvora o explosión para propulsar proyectiles. Pistolas, rifles, mosquetes, cañones de mano, escopetas. En el mundo de One Piece, las armas de fuego coexisten con armas cuerpo a cuerpo (Ben Beckman, Yasopp, Van Augur, Lucky Roux —aunque usa pistola, también es cocinero—).

**Armas/estilos que caen aquí:**
- Pistolas de chispa, revólvers
- Rifles de avancarga, mosquetes
- Escopetas de dos cañones
- Cañones de mano (portátiles)
- Armas de fuego híbridas (ej: un rifle con bayoneta)

**Ejemplos de cards asociadas:**
| Card | Tier | Rango típico | Descripción |
|------|:----:|:------------:|-------------|
| Disparo Simple | 1 | D | Bala básica |
| Doble Impacto | 2 | C | Dos disparos simultáneos |
| Bala de Precisión | 3 | B | Disparo certero a larga distancia |
| Bala de Haki | 4 | A | Proyectil imbuido de Busoshoku |
| Ráfaga Suprema | 5 | S | Ráfaga de proyectiles que cubre área |

**Tips para jugadores:**
- Las armas de fuego tienen poder de parada inmediato (narrativa y mecánicamente).
- Dependen de munición (pólvora, balas). Sin recarga, eres inútil a distancia.
- Sinergizan con Haki de Armamento para balas imbuidas (daño a Logias).
- Combinan bien con `armas_a_distancia` si tu personaje usa arco y pistola.

### 3.8 Disciplina 7: `armas_exoticas`

**Nombre:** Armas Exóticas
**Slug:** `armas_exoticas`
**Categoría:** Combate

**Qué representa:** Armas que no encajan en las categorías anteriores por su naturaleza única, mecánica compleja, o rareza. Brazos mecánicos, dedos explosivos, armas climáticas, látigos, cadenas, armas con propiedades elementales innatas (no Haki), instrumentos musicales de combate, armas vivientes, etc.

**Armas/estilos que caen aquí:**
- Weatheria/Clima-Tact (arma climática de Nami)
- Brazos mecánicos (Franky — Strong Hammer, Franky Shogun)
- Dedos explosivos (Mr. 5, Globitos)
- Látigos, cadenas, kusarigama (hoz con cadena)
- Instrumentos musicales letales (Scratchmen Apoo — su música)
- Armas vivientes (Spandam — Funkfreed, espada elefante)
- Armas con mecanismos complejos (trampas portátiles, armas plegables)

**Ejemplos de cards asociadas:**
| Card | Tier | Rango típico | Descripción |
|------|:----:|:------------:|-------------|
| Golpe Sorpresa | 2 | C | Ataque con mecanismo oculto en el arma |
| Clima-Tact: Breeze Tempo | 2 | C | Ráfaga de viento cálido |
| Franky Shogun: Radical Beam | 4 | A | Rayo láser desde el robot |
| Música Explosiva | 3 | B | Onda sónora dañina |
| Dedos Explosivos | 2 | C | Proyectiles de punta de dedo |

**Tips para jugadores:**
- La disciplina para personajes con armas únicas y creativas.
- No hay un "estándar": cada arma exótica es diferente y requiere cards personalizadas.
- El staff evalúa cada card caso por caso. Sé creativo pero razonable.
- Ventaja: tus ataques son impredecibles (nadie sabe qué hace tu Clima-Tact).
- Desventaja: si tu arma exótica se rompe o te la roban, no tienes respaldo.

### 3.9 Disciplina 8: `escudo`

**Nombre:** Escudo
**Slug:** `escudo`
**Categoría:** Combate

**Qué representa:** El combate defensivo usando escudos. No es solo "tener un escudo" — es la disciplina de usar el escudo como arma y herramienta defensiva: bloqueo, desvío, contraataque, protección de aliados. Incluye desde escudos de mano hasta escudos cuerpo completo.

**Armas/estilos que caen aquí:**
- Escudos de mano (broquel, rodela)
- Escudos medianos (escudo de hoplita, escudo oblongo)
- Escudos grandes (pavés, escudo de torre)
- Escudos improvisados (tapas de alcantarilla, puertas)
- Escudos con filo (escudo de carga con borde cortante)
- Técnicas de bloqueo y parry sin escudo (estilo defensivo)

**Ejemplos de cards asociadas:**
| Card | Tier | Rango típico | Descripción |
|------|:----:|:------------:|-------------|
| Bloqueo Básico | 1 | D | Reduce daño entrante |
| Postura Defensiva | 2 | C | Bonus a RES mientras te cubres |
| Contra Impacto | 2 | C | Bloquea y contraataca |
| Muro de Escudos | 3 | B | Protege a un aliado cercano |
| Escudo Total | 4 | A | Bloqueo completo durante un post |
| Reflejo de Haki | 5 | S | Escudo imbuido de Busoshoku que rebota ataques |

**Tips para jugadores:**
- Es la única disciplina puramente defensiva. Tu objetivo no es dañar, es proteger y sobrevivir.
- Sinergiza perfectamente con RES (resistencia) y tanques.
- Un personaje con escudo puede mantener la línea mientras los dps hacen su trabajo.
- Muy infravalorada: un buen tanque con escudo controla el ritmo del combate.
- Combinada con `armas_de_asta` o `armas_contundentes` formas el arquetipo clásico de "guerrero con escudo + arma".

### 3.10 Disciplina 9: `haki_conquistador` (Especial)

**Nombre:** Haki de Conquistador
**Slug:** `haki_conquistador`
**Categoría:** Especial
**Requiere staff:** Sí (`staff_grant_only = 1`)
**Requiere ESP:** No (pero tener ESP alto ayuda narrativamente)

**Qué representa:** La forma más rara de Haki. La capacidad de imponer la propia voluntad sobre los demás. Solo 1 de cada millón de personas nace con ella. En el sistema del foro, es una disciplina **especial** que no se compra con PP, no se adquiere en el wizard de creación, y solo el staff puede otorgarla mediante trama.

**Diferencias clave con las otras disciplinas:**
- No tiene `fixed_pp_cost` — no se adquiere con PP.
- `staff_grant_only = 1` — solo asignable por staff vía `character_disciplinas_save.php`.
- No aparece en `game_disciplina_acquire_catalog_for_character()`.
- Su progresión de grados sigue las mismas reglas (I–V) pero narrativamente cada grado representa un dominio mayor del Haoshoku.
- No requiere nivel mínimo para adquirirla (la trama es el único requisito), pero subir de grado sí requiere nivel según `game_grado_nivel_required`.

**Mapeo narrativo de grados:**
| Grado | Narrativa | Efectos típicos |
|:-----:|-----------|-----------------|
| I | Despertar latente | El personaje noquea involuntariamente a débiles cercanos en momentos de estrés extremo |
| II | Control básico | Puede dirigir el Haoshoku conscientemente, noqueando a voluntad a oponentes débiles |
| III | Control consciente | Noquea a oponentes medianos, puede seleccionar objetivos (evita aliados) |
| IV | Infusión en armas | Recubre su arma/ataque con Haoshoku, dañando incluso sin contacto físico (como Kaido y Big Mom) |
| V | Supremacía | Haoshoku de nivel Divino: afecta a oponentes fuertes, puede paralizar sin noquear |

**Tips para jugadores:**
- El Conquistador no se pide — se merece. El staff lo otorga cuando la trama lo justifica.
- No bases tu personaje en tener Conquistador. Es un bonus narrativo, no un requisito.
- Si lo obtienes, úsalo con moderación. Noquear npcs déiles cada 2 posts aburre.
- Sinergiza con Haki de Armamento y Observación: un personaje con los tres tipos es un monstruo.

---

## 4. Grados (I–V)

### 4.1 Escala de Grados

Los grados representan el nivel de maestría en una disciplina. Van de **I** (novato) a **V** (maestro absoluto).

| Grado | Valor numérico | Nombre | Etiqueta | Significado narrativo |
|:-----:|:--------------:|--------|:--------:|----------------------|
| I | 1 | Iniciado | `I` | Has empezado a entrenar esta disciplina. Conoces lo básico. |
| II | 2 | Aprendiz | `II` | Tienes algo de experiencia. Puedes usar técnicas de nivel medio. |
| III | 3 | Competente | `III` | Eres un luchador competente. Tus técnicas son fiables. |
| IV | 4 | Experto | `IV` | Tu maestría es notable. Eres respetado en este campo. |
| V | 5 | Maestro | `V` | Has alcanzado la cúspide. Pocos en el mundo te superan. |

```php
// game_grado_label() en grado_helpers.php:8
function game_grado_label(int $rank): string
{
    $labels = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V'];
    return $labels[max(1, min(5, $rank))] ?? 'I';
}
```

### 4.2 Requisitos por Grado

Cada grado requiere que el personaje cumpla ciertas condiciones **antes de solicitar la mejora**:

| Grado | Nivel global requerido | PP cost (disciplina) | Cooldown tras obtener |
|:-----:|:---------------------:|:--------------------:|:---------------------:|
| I | 1 (cualquier rango) | — (se adquiere al crear o comprar) | — |
| II | 2 (rango C) | 80 PP | 7 días |
| III | 3 (rango B) | 140 PP | 14 días |
| IV | 4 (rango A) | 180 PP | 21 días |
| V | 5 (rango S) | 250 PP | 30 días |

```php
// game_grado_nivel_required() en grado_helpers.php:23
function game_grado_nivel_required(int $targetRank): int
{
    $map = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5];
    return $map[max(1, min(5, $targetRank))] ?? 1;
}
```

```php
// game_grado_upgrade_price() en grado_helpers.php:33
function game_grado_upgrade_price(int $targetRank, string $competenciaType = 'disciplina'): int
{
    $disciplina = [2 => 80, 3 => 140, 4 => 180, 5 => 250];
    $oficio = [2 => 50, 3 => 90, 4 => 130, 5 => 190];
    $table = $competenciaType === 'oficio' ? $oficio : $disciplina;
    return $table[max(2, min(5, $targetRank))] ?? 0;
}
```

```php
// game_grado_cooldown_days_for_rank() en grado_helpers.php:42
function game_grado_cooldown_days_for_rank(int $targetRank): int
{
    $map = [2 => 7, 3 => 14, 4 => 21, 5 => 30];
    return $map[max(2, min(5, $targetRank))] ?? 7;
}
```

### 4.3 Mecánica del Cooldown

El cooldown se mide en **días reales** desde la última mejora de grado exitosa (de cualquier competencia). Se almacena en `data_json`:

```json
{
    "grado_last_upgrade_at": "2026-05-15 14:30:00",
    "grado_last_upgrade_rank": 3
}
```

**Reglas del cooldown:**
1. Es **global** para disciplinas y oficios (subir Cuerpo a Cuerpo a II pone en cooldown TODAS las mejoras de competencias).
2. El contador se inicia cuando el staff **aprueba** la mejora, no cuando el jugador la solicita.
3. Si el personaje tenía cooldown de una mejora a rango II (7 días) y luego sube a III, el nuevo cooldown es de 14 días desde esa fecha.
4. Los cooldowns no se acumulan en paralelo — siempre es el último rango alcanzado el que determina la espera.

```php
// game_grado_cooldown_ok() en grado_helpers.php:133
function game_grado_cooldown_ok(?string $lastUpgradeAt, ?int $lastReachedRank = null): bool
{
    if ($lastUpgradeAt === null || $lastUpgradeAt === '') {
        return true;
    }
    $last = strtotime($lastUpgradeAt);
    if ($last === false) {
        return true;
    }
    $days = $lastReachedRank !== null
        ? game_grado_cooldown_days_for_rank($lastReachedRank)
        : 14;
    return (time() - $last) >= ($days * 86400);
}
```

### 4.4 ¿Qué desbloquea cada grado?

Cada grado abre acceso a contenido de mayor nivel:

| Grado | Tier de cards accesible | Rango de cards típico | Estilos canónicos | Narrativa |
|:-----:|:----------------------:|:---------------------:|:-----------------:|-----------|
| I | Tier 1 | D–C | No | Puedes usar técnicas básicas de la disciplina |
| II | Tier 2 | C–B | Sí (requisito mínimo) | Puedes aprender estilos canónicos básicos |
| III | Tier 3 | B–A | Sí (avanzados) | Eres reconocido como competente |
| IV | Tier 4 | A–S | Sí (maestros) | Puedes enseñar la disciplina a otros |
| V | Tier 5 | S–SS | Sí (supremos) | Eres una autoridad mundial |

**Regla de validación (`game_card_assignment_competencia_error`):**

```php
// grado_helpers.php:314
function game_card_assignment_competencia_error(int $characterId, array $card): ?string
{
    $tier = max(1, min(5, (int)($card['tier'] ?? 1)));
    $discSlug = trim((string)($card['disciplina_slug'] ?? ''));
    if ($discSlug !== '') {
        $rank = game_disciplina_get_rank($characterId, $discSlug);
        if ($rank < $tier) {
            return 'Requiere disciplina «' . $discSlug . '» grado '
                . game_grado_label($tier) . ' o superior (actual: '
                . ($rank > 0 ? game_grado_label($rank) : 'ninguno') . ').';
        }
    }
    // ... también valida oficio_slug
}
```

### 4.5 Grado vs Tier de Card

El `tier` de una card (1–5) se corresponde 1:1 con el grado requerido de la disciplina:

```
tier 1 → grado I
tier 2 → grado II
tier 3 → grado III
tier 4 → grado IV
tier 5 → grado V
```

No se puede tener una card de tier superior al grado que se posee. Esto es una validación **en el momento de asignar la card** (no al usarla). Si un personaje sube su disciplina a grado III después de obtener una card tier 3, la card sigue siendo válida (no se "pierde").

---

## 5. Modelo de Datos

### 5.1 `game_disciplinas` — Catálogo Maestro

```sql
CREATE TABLE mybb_game_disciplinas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    slug            VARCHAR(64) NOT NULL,
    name            VARCHAR(120) NOT NULL,
    description     TEXT,
    category        VARCHAR(64) NOT NULL DEFAULT 'combate',
    icon            VARCHAR(64) NOT NULL DEFAULT 'fa-crosshairs',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    sort_order      INT NOT NULL DEFAULT 0,
    grado_unlock_json JSON NULL,
    requires_esp_rank TINYINT UNSIGNED NULL,
    staff_grant_only TINYINT(1) NOT NULL DEFAULT 0,
    fixed_pp_cost   INT UNSIGNED NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Campos:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT AUTO_INCREMENT | Clave primaria |
| `slug` | VARCHAR(64) | Identificador URL-friendly. UNIQUE. Ej: `cuerpo_a_cuerpo` |
| `name` | VARCHAR(120) | Nombre visible. Ej: `Cuerpo a Cuerpo` |
| `description` | TEXT | Descripción narrativa de la disciplina |
| `category` | VARCHAR(64) | Agrupación: `combate` o `especial` |
| `icon` | VARCHAR(64) | Clase FontAwesome para el ícono visual |
| `is_active` | TINYINT(1) | Si está disponible en el catálogo (1 = activo) |
| `sort_order` | INT | Orden de visualización en la UI |
| `grado_unlock_json` | JSON NULL | Qué se desbloquea en cada grado (texto libre, informativo) |
| `requires_esp_rank` | TINYINT UNSIGNED NULL | Rango de ESP efectivo mínimo requerido para adquirir (ej: 3 para disciplinas que requieren percepción) |
| `staff_grant_only` | TINYINT(1) | Si solo puede ser asignada por staff (1 = sí, para Haki Conquistador) |
| `fixed_pp_cost` | INT UNSIGNED NULL | Coste fijo en PP para adquirir (si null, usa coste escalado por cantidad poseída) |
| `created_at` | TIMESTAMP | Fecha de creación del registro |

### 5.2 `game_character_disciplinas` — Disciplinas del Personaje

```sql
CREATE TABLE mybb_game_character_disciplinas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    character_id    INT NOT NULL,
    disciplina_id   INT NOT NULL,
    `rank`          TINYINT UNSIGNED NOT NULL DEFAULT 1,
    learned_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_char_disciplina (character_id, disciplina_id),
    KEY idx_character (character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Campos:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT AUTO_INCREMENT | Clave primaria |
| `character_id` | INT | FK lógica a `game_personajes.id` |
| `disciplina_id` | INT | FK lógica a `game_disciplinas.id` |
| `rank` | TINYINT UNSIGNED | Grado actual (1=I, 2=II, 3=III, 4=IV, 5=V). Default 1 |
| `learned_at` | TIMESTAMP | Cuándo se adquirió la disciplina |

**UNIQUE KEY `uq_char_disciplina`:** Un personaje no puede tener la misma disciplina dos veces. Cada disciplina aparece una vez con su grado actual.

### 5.3 Datos de Progresión en `data_json`

El cooldown y tracking de mejoras se almacena en `game_personajes.data_json` (compartido con el sistema de progresión general):

```json
{
    "pp": 320,
    "pp_linaje": 0,
    "nivel": 3,
    "rank": "B",
    "grado_last_upgrade_at": "2026-05-15 14:30:00",
    "grado_last_upgrade_rank": 3
}
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `grado_last_upgrade_at` | string (datetime) | Última vez que se subió de grado (cualquier competencia) |
| `grado_last_upgrade_rank` | int | Grado alcanzado en esa última mejora (2–5) |

### 5.4 Datos de `grado_unlock_json`

El campo `grado_unlock_json` en `game_disciplinas` es un JSON que describe qué desbloquea cada grado a nivel informativo. Ejemplo:

```json
{
    "1": "Técnicas básicas cuerpo a cuerpo (tier 1).",
    "2": "Estilo canónico básico. Cards tier 2.",
    "3": "Técnicas intermedias. Puedes enseñar principios básicos.",
    "4": "Maestría. Puedes crear tu propio estilo derivado.",
    "5": "Máxima expresión del combate cuerpo a cuerpo."
}
```

Este JSON se parsea con `game_parse_grado_unlock_json()` para mostrarse en la UI como preview de desbloqueos.

---

## 6. Servicios PHP — Capa de Helpers

### 6.1 `grado_helpers.php` — Sistema Compartido de Grados

Archivo: `back/forum/game/inc/grado_helpers.php`

Es el núcleo compartido entre disciplinas y oficios. Contiene:

| Función | Propósito | Devuelve |
|---------|-----------|----------|
| `game_grado_label(int $rank)` | Etiqueta romana I–V | string |
| `game_grado_bonus(int $rank)` | Bonus numérico por grado | float |
| `game_grado_nivel_required(int $targetRank)` | Nivel global mínimo requerido | int |
| `game_grado_upgrade_price(int $targetRank, string $type)` | Coste PP para subir a ese grado | int |
| `game_grado_cooldown_days_for_rank(int $targetRank)` | Días de cooldown tras alcanzar el grado | int |
| `game_grado_cooldown_ok(?string $lastAt, ?int $lastRank)` | Verifica si el cooldown ha expirado | bool |
| `game_grado_cooldown_remaining_days(...)` | Días restantes de cooldown | int |
| `game_get_character_nivel(array $data)` | Nivel del PJ desde data_json | int |
| `game_get_acquisition_cost(int $alreadyOwned, string $type)` | Coste PP de adquirir una nueva competencia | int |
| `game_get_acquisition_level_required(int $alreadyOwned)` | Nivel mínimo para adquirir otra competencia | int |
| `game_parse_grado_unlock_json(mixed $unlocks)` | Parsea el JSON de desbloqueos | array |
| `game_grado_last_upgrade_at(array $data)` | Timestamp de última mejora | ?string |
| `game_grado_last_upgrade_rank(array $data)` | Último grado alcanzado | ?int |
| `game_grado_enrich_row(...)` | Enriquece fila de competencia con datos de upgrade | array |
| `game_grado_upgrade_total_price(int $old, int $new, string $type)` | Coste total de old→new | int |
| `game_grado_staff_apply_rank_change(...)` | Staff aplica cambio de grado | ?string (error) |
| `game_card_assignment_competencia_error(...)` | Valida disciplina/oficio para asignar card | ?string |

### 6.2 `disciplinas_helpers.php` — Helpers Específicos

Archivo: `back/forum/game/inc/disciplinas_helpers.php`

| Función | Propósito |
|---------|-----------|
| `game_disciplina_rank_label(int $rank)` | Wrapper de `game_grado_label` |
| `game_disciplina_get_by_slug(string $slug)` | Busca disciplina por slug |
| `game_disciplina_get_by_name(string $name)` | Busca disciplina por nombre |
| `game_disciplina_name_to_slug(string $name)` | Convierte nombre a slug |
| `game_disciplina_get_rank(int $charId, string $slug)` | Obtiene grado de disciplina de un PJ |
| `game_disciplina_list_for_character(int $charId)` | Lista disciplinas de un personaje |
| `game_disciplina_list_catalog(bool $activeOnly)` | Lista catálogo completo |
| `game_disciplina_set_character_rank(int $charId, int $discId, int $rank)` | Inserta o actualiza grado |
| `game_disciplina_remove_from_character(int $charId, int $discId)` | Elimina disciplina del PJ |
| `game_disciplina_assign_initial(int $charId, string $input, int $rank)` | Asigna disciplina inicial (creación) |
| `game_disciplina_count_for_character(int $charId)` | Cuenta disciplinas del PJ |
| `game_disciplina_character_owns(int $charId, int $discId)` | Verifica si posee la disciplina |
| `game_disciplina_validate_acquire_rules(array $row, int $espRank)` | Valida reglas de adquisición |
| `game_disciplina_enrich_acquire_option(...)` | Prepara opción para el catálogo de compra |
| `game_disciplina_acquire_catalog_for_character(...)` | Catálogo de disciplinas adquiribles |
| `game_disciplina_acquire_pp_cost(array $row, int $alreadyOwned)` | Coste de adquisición |

### 6.3 Flujo de Datos: Carga de Disciplinas en la Ficha

Cuando se carga la ficha de un personaje (`personaje_init.php`), `CharacterSheetLoader` obtiene las disciplinas:

```php
// CharacterSheetLoader.php:181
'disciplinas' => function_exists('game_disciplina_list_for_character')
    ? game_disciplina_list_for_character((int)$row['id'])
```

```php
// En _sidebar.php — renderizado de disciplinas
$sidebar_disciplinas = $char['disciplinas'] ?? game_disciplina_list_for_character((int)$char['id']);
```

El endpoint AJAX `character_competencias_get.php` devuelve datos enriquecidos para el panel de gestión:

```php
// character_competencias_get.php — fragmento relevante
$disciplinas = [];
foreach (game_disciplina_list_for_character($charId) as $row) {
    $disciplinas[] = game_grado_enrich_row($row, 'disciplina', $charNivel,
        $lastUpgrade, $lastUpgradeRank, $ppAvailable);
}

// Respuesta incluye:
'req_nivel_global' => 'Nivel 2+ para grado II',
'disciplinas_owned' => game_disciplina_count_for_character($charId),
'next_disciplina_cost' => ...,
'catalog_disciplinas' => game_disciplina_acquire_catalog_for_character(...),
```

---

## 7. Flujo de Adquisición de Disciplina

### 7.1 Actor: Jugador (Autoservicio)

El jugador adquiere una nueva disciplina desde el panel de gestión (Gestión > Disciplinas y Oficios > Adquirir Nuevas).

**Endpoint:** `POST /game/ajax/acquire_competencia.php`

**Payload:**
```json
{
    "character_id": 42,
    "type": "disciplina",
    "catalog_id": 2,
    "_csrf": "token"
}
```

**Validaciones en orden:**
1. Login + CSRF + POST method
2. PJ activo del usuario
3. PJ existe y pertenece al usuario
4. PJ está aprobado
5. `type` es `disciplina` o `oficio`
6. `catalog_id` existe y está activo
7. El PJ no posee ya esta disciplina
8. Reglas específicas de disciplina (`game_disciplina_validate_acquire_rules`):
   - `staff_grant_only` = false (no es Haki Conquistador)
   - `requires_esp_rank` se cumple (si aplica)
9. Límite de disciplinas por nivel (`game_get_acquisition_level_required`):
   - 0 poseídas → nivel 1+
   - 1 poseída → nivel 2+
   - 2 poseídas → nivel 3+
   - etc.
10. PP suficientes (coste escalado por cantidad poseída)

```php
// acquire_competencia.php — validación de disciplina
$ruleCheck = game_disciplina_validate_acquire_rules($catalog, $espEffectiveRank);
if (!$ruleCheck['ok']) {
    GameAjax::json(false, null, ['code' => 403, 'message' => $ruleCheck['reason']], 403);
}
$alreadyOwned = game_disciplina_count_for_character($characterId);
$cost = game_disciplina_acquire_pp_cost($catalog, $alreadyOwned);
$nivelReq = game_get_acquisition_level_required($alreadyOwned);
if ($charNivel < $nivelReq) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Nivel insuficiente...'], 400);
}
if ($ppAvailable < $cost) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'PP insuficientes...'], 400);
}
```

**Ejecución:**
1. Se descuentan los PP de `data_json`.
2. Se inserta registro en `game_character_disciplinas` con `rank = 1`.
3. Se envía notificación al jugador.
4. Se devuelve respuesta JSON con datos de la nueva disciplina.

```php
data['pp'] = max(0, $ppAvailable - $cost);
// Se persiste en DB
game_disciplina_set_character_rank($characterId, $catalogId, 1);
```

### 7.2 Costes de Adquisición por Cantidad Poseída

El coste de adquirir una N-ésima disciplina sigue una curva progresiva:

| Disciplinas ya poseídas | Coste de la siguiente | Nivel mínimo requerido |
|:----------------------:|:---------------------:|:----------------------:|
| 0 | 0 PP (gratis, primera) | 1 |
| 1 | 150 PP | 2 |
| 2 | 350 PP | 3 |
| 3 | 750 PP | 4 |
| 4 | 1.400 PP | 5 |
| 5 | 2.500 PP | 6 |
| 6 | 4.000 PP | 6 |
| 7+ | 4.500 PP (cap) | 6 |

```php
// grado_helpers.php:70
function game_get_acquisition_cost(int $alreadyOwned, string $competenciaType = 'disciplina'): int
{
    $disciplina = [0, 0, 150, 350, 750, 1400, 2500, 4000];
    // index 0: 1ra disciplina (gratis)
    // index 1: 2da disciplina (150pp)
    // ...
    $index = $alreadyOwned + 1;
    if ($index < count($costs)) {
        return $costs[$index];
    }
    // Cap para más de 7
    return min(4500, 4000 + ($alreadyOwned - 6) * 500);
}
```

### 7.3 Actor: Staff (Asignación Directa)

El staff puede asignar o modificar disciplinas mediante el panel staff (`character_disciplinas_save.php`):

**Endpoint:** `POST /game/ajax/character_disciplinas_save.php`

**Casos de uso:**
- Asignar Haki de Conquistador a un personaje (`staff_grant_only`).
- Corregir un grado (si hubo error en una solicitud).
- Otorgar una disciplina como recompensa de evento/trama gratis.
- Remover una disciplina (en casos excepcionales).

```php
// character_disciplinas_save.php — fragmento
$oldQ = $db->query("SELECT `rank` FROM {$prefix}game_character_disciplinas
    WHERE character_id = {$charId} AND disciplina_id = {$disciplinaId} LIMIT 1");
// Si existe, actualiza rank. Si no, inserta.
// También ejecuta game_grado_staff_apply_rank_change() para validar y cobrar PP
```

### 7.4 Flujo: Primera Disciplina (Creación de Personaje)

Al crear un personaje, se asigna la disciplina inicial automáticamente:

```php
// En save_personaje.php (a través de CharacterSaveService)
game_disciplina_assign_initial($characterId, $disciplinaInput, 1);
```

```php
// disciplinas_helpers.php:147
function game_disciplina_assign_initial(int $characterId, string $disciplinaInput, int $rank = 1): void
{
    $disciplinaInput = trim($disciplinaInput);
    if ($disciplinaInput === '' || strcasecmp($disciplinaInput, 'Ninguna') === 0) {
        return;
    }
    $disciplina = game_disciplina_get_by_name($disciplinaInput);
    if (!$disciplina) {
        $slug = game_disciplina_name_to_slug($disciplinaInput);
        if ($slug !== null) {
            $disciplina = game_disciplina_get_by_slug($slug);
        }
    }
    if (!$disciplina) {
        return;
    }
    game_disciplina_set_character_rank($characterId, (int)$disciplina['id'], $rank);
}
```

La primera disciplina es **gratis** (0 PP) y se asigna en **grado I**.

---

## 8. Flujo de Mejora de Grado

### 8.1 Solicitud del Jugador

A diferencia de la compra de stats (automática si tienes PP), **subir de grado requiere solicitud y aprobación del staff**.

**Endpoint:** `POST /game/ajax/upgrade_competencia_grado.php`

**Payload:**
```json
{
    "character_id": 42,
    "type": "disciplina",
    "catalog_id": 2,
    "_csrf": "token"
}
```

**Validaciones en el endpoint:**
1. Login, CSRF, POST, ownership, PJ aprobado.
2. La disciplina existe y el PJ la posee.
3. El grado actual no es el máximo (V).
4. El jugador no ha hecho otra solicidud de grado recientemente (cooldown).
5. Nivel global suficiente para el siguiente grado.
6. PP suficientes para pagar el coste del siguiente grado.

```php
// upgrade_competencia_grado.php — validaciones clave
$nextRank = $currentRank + 1;
$reqNivel = game_grado_nivel_required($nextRank);
$price = game_grado_upgrade_price($nextRank, $type);

if (!game_grado_cooldown_ok($lastUpgrade, $lastUpgradeRank)) {
    // Responder con cooldown activo
}
if ($charNivel < $reqNivel) {
    // Responder con nivel insuficiente
}
if ($ppAvailable < $price) {
    // Responder con PP insuficientes
}
```

**No se descuentan PP ni se actualiza el grado en este punto.** La solicitud se envía al panel de staff para revisión:

```php
// Se crea solicitud en game_admin_requests
game_create_notification(
    $staffUid,
    'competencia_grado_request',
    'Solicitud de subida de grado',
    "{$pjName} solicita subir «{$name}» ({$typeLabel}) a grado {$nextLabel}..."
);
```

### 8.2 Revisión del Staff

El staff recibe la solicitud en el panel de administración y evalúa:

1. **¿El jugador ha roleado el entrenamiento?** — El personaje debe haber hecho posts IC entrenando, buscando un maestro, o participando en combates que justifiquen la mejora.
2. **¿Los PP están correctos?** — Verificar que el jugador tiene los PP necesarios.
3. **¿El cooldown es correcto?** — Confirmar que han pasado los días necesarios desde la última mejora.
4. **¿Hay coherencia narrativa?** — Un personaje que nunca ha empuñado una espada no puede subir Armas de Filo a grado III de golpe. Las mejoras deben ser graduales y reflejarse en la historia.

### 8.3 Aprobación del Staff

Cuando el staff aprueba, usa `character_disciplinas_save.php` o `character_oficios_save.php`:

```php
// character_disciplinas_save.php — fragmento de actualización
$ecoErr = game_grado_staff_apply_rank_change($charId, $oldRank, $newRank, 'disciplina');
if ($ecoErr !== null) {
    // Devolver error (nivel insuficiente, PP insuficientes, cooldown activo)
}
game_disciplina_set_character_rank($charId, $disciplinaId, $newRank);
```

`game_grado_staff_apply_rank_change()` realiza las validaciones finales del lado del staff:

```php
// grado_helpers.php:258
function game_grado_staff_apply_rank_change(
    int $characterId, int $oldRank, int $newRank, string $competenciaType = 'disciplina'
): ?string
{
    // 1. Valida nivel global para cada grado intermedio
    for ($r = $oldRank + 1; $r <= $newRank; $r++) {
        if ($charNivel < game_grado_nivel_required($r)) {
            return 'Nivel insuficiente para grado ' . game_grado_label($r) . '...';
        }
    }
    // 2. Calcula coste total (puede ser multi-grado si staff sube de II a IV de una vez)
    $cost = game_grado_upgrade_total_price($oldRank, $newRank, $competenciaType);
    if ($pp < $cost) {
        return 'PP insuficientes...';
    }
    // 3. Verifica cooldown
    if (!game_grado_cooldown_ok($lastUpgrade, $lastUpgradeRank)) {
        return 'Cooldown activo...';
    }
    // 4. Descuenta PP y registra cooldown
    $data['pp'] = max(0, $pp - $cost);
    $data['grado_last_upgrade_at'] = date('Y-m-d H:i:s');
    $data['grado_last_upgrade_rank'] = $newRank;
    // Persiste
}
```

### 8.4 Cooldown de Mejora

| Nuevo grado | Cooldown tras aprobación |
|:-----------:|:------------------------:|
| II | 7 días |
| III | 14 días |
| IV | 21 días |
| V | 30 días |

El cooldown se mide desde la fecha de aprobación de la solicitud anterior. Si un personaje tiene `grado_last_upgrade_at = 2026-05-15` con `grado_last_upgrade_rank = 3` (III), no puede solicitar otra mejora hasta 14 días después (2026-05-29).

### 8.5 Mejora Directa por Staff (Multi-grado)

El staff puede subir múltiples grados de una sola vez (ej: de II a IV) si la trama lo justifica (un salto de poder narrativo). En ese caso:

- El coste es la suma de los incrementos: `game_grado_upgrade_total_price(2, 4, 'disciplina')` = 140 + 180 = 320 PP.
- El cooldown se registra para el grado más alto alcanzado (IV = 21 días).
- Se cobran los PP de una sola vez.

---

## 9. Integración con Cards

### 9.1 Disciplina como Requisito de Card

Toda card en el catálogo (`game_cards`) puede tener un requisito de disciplina mediante dos campos:

- `disciplina_slug` (VARCHAR 64 NULL): Slug de la disciplina requerida.
- `tier` (TINYINT UNSIGNED): El grado mínimo requerido (1–5).

**Regla:** Si `disciplina_slug` está definido, el personaje debe tener esa disciplina en un grado **igual o superior** al `tier` de la card.

```
disciplina_slug = "armas_de_filo", tier = 3
→ Requiere Armas de Filo grado III o superior
```

**Ejemplos de cards con requisitos de disciplina:**

```sql
-- Card de técnica (Santōryū)
INSERT INTO mybb_game_cards (name, card_type, rank, tier, disciplina_slug, dice)
VALUES ('Oni Giri', 'tecnica', 'C', 2, 'armas_de_filo', '2d8+des [CORTANTE]');

-- Card de técnica (Fishman Karate)
INSERT INTO mybb_game_cards (name, card_type, rank, tier, disciplina_slug, dice)
VALUES ('Samegare', 'tecnica', 'B', 3, 'cuerpo_a_cuerpo', '3d6+fue [AGUA]');

-- Card de equipo (Escudo de batalla)
INSERT INTO mybb_game_cards (name, card_type, rank, tier, disciplina_slug, effects_json)
VALUES ('Escudo de Acero Reforzado', 'equipo', 'C', 1, 'escudo',
        '{"equipo_type":"arma","subtipo":"escudo","defensa":8}');
```

### 9.2 Validación en Asignación de Cards

Cuando el staff asigna una card a un personaje (`cards_assign.php`), se ejecuta `game_card_assignment_competencia_error()`:

```php
// grado_helpers.php:314
function game_card_assignment_competencia_error(int $characterId, array $card): ?string
{
    $tier = max(1, min(5, (int)($card['tier'] ?? 1)));
    $discSlug = trim((string)($card['disciplina_slug'] ?? ''));
    if ($discSlug !== '') {
        $rank = game_disciplina_get_rank($characterId, $discSlug);
        if ($rank < $tier) {
            return 'Requiere disciplina «' . $discSlug . '» grado '
                . game_grado_label($tier) . ' o superior (actual: '
                . ($rank > 0 ? game_grado_label($rank) : 'ninguno') . ').';
        }
    }
    // También valida oficio_slug si está presente
    return null; // Sin error
}
```

**Flujo de asignación con validación:**

```
1. Staff selecciona card del catálogo + personaje destino
2. cards_assign.php recibe la petición
3. Se lee la card (incluyendo disciplina_slug y tier)
4. Se llama a game_card_assignment_competencia_error($characterId, $card)
5. Si devuelve null → se asigna la card
6. Si devuelve string → se muestra el error al staff
```

### 9.3 Cards Sin Disciplina

No todas las cards requieren disciplina. Cards sin `disciplina_slug` pueden ser asignadas a cualquier personaje (sujeto a otras validaciones como oficio, nivel de Haki, etc.). Ejemplos:

- Cards de tipo `akuma_no_mi` (no requieren disciplina, requieren tener la fruta).
- Cards de tipo `haki` (requieren nivel del tipo de Haki, no disciplina).
- Cards de equipo genérico (armaduras, herramientas, consumibles).

### 9.4 Cards y Estilo Canónico

Las cards también pueden tener `estilo_canonico_slug`, que es independiente de `disciplina_slug`. Una card puede requerir AMBOS:

```sql
-- Card del estilo Santōryū (requiere disciplina + estilo)
disciplina_slug = "armas_de_filo"  (grado II+, según tier)
estilo_canonico_slug = "santoryu"  (requiere el estilo aprendido)
```

La validación de `estilo_canonico_slug` se hace en un paso posterior a la validación de disciplina.

### 9.5 Relación Tier ↔ Rango de Card

| Tier | Rango de card típico | Disciplina requerida | Ejemplo de card |
|:----:|:--------------------:|:--------------------:|-----------------|
| 1 | D–C | Grado I | Tajo básico |
| 2 | C–B | Grado II | Oni Giri |
| 3 | B–A | Grado III | Tatsu Maki |
| 4 | A–S | Grado IV | Yakkodori |
| 5 | S–SS | Grado V | Daishinkan |

### 9.6 Caso Especial: Cards que NO Requieren la Disciplina Relacionada

Una card puede tener `disciplina_slug = null` aunque narrativamente esté asociada a un arma. Por ejemplo:

- Una "Granada de Humo" (equipo) no requiere `armas_de_fuego` porque es un objeto arrojadizo, no un arma de pólvora.
- Un "Botiquín de Primeros Auxilios" no requiere `escudo` aunque sea defensivo.

La regla es: **si la card requiere entrenamiento específico en una disciplina para usarse efectivamente, lleva `disciplina_slug`.** Si es un objeto genérico que cualquiera puede usar, no lo lleva.

---

## 10. Integración con Estilos Canónicos

### 10.1 Vinculación Disciplina → Estilo

Cada estilo canónico tiene un campo `disciplina_slug` que lo vincula a su disciplina base:

```sql
-- Datos de estilos_canonicos_seed_data.php
INSERT INTO mybb_game_estilos_canonicos (slug, name, disciplina_slug, ...) VALUES
('rokushiki', 'Rokushiki', 'cuerpo_a_cuerpo', ...),
('gyojin_karate', 'Gyojin Karate', 'cuerpo_a_cuerpo', ...),
('santoryu', 'Santōryū', 'armas_de_filo', ...),
('ittoryu', 'Ittōryū', 'armas_de_filo', ...);
```

### 10.2 Requisitos de Estilo

Para aprender un estilo canónico, el personaje debe cumplir:
1. Disciplina base en grado II o superior (según diseño en MAESTRO §8).
2. Stat principal a rango C+ o superior.
3. Condición narrativa (entrenamiento IC, raza, juramento).

### 10.3 Diferencia en Validación de Cards

- `disciplina_slug` en card: validación automática en `game_card_assignment_competencia_error()`.
- `estilo_canonico_slug` en card: validación separada que verifica que el personaje tenga el estilo aprendido (`game_estilo_canonico_character_owns()`).

Ambas validaciones se ejecutan secuencialmente en el flujo de asignación de cards.

---

## 11. Costes de Progresión Detallados

### 11.1 Resumen de Costes

| Concepto | Coste en PP | Nivel mínimo | Cooldown | ¿Requiere staff? |
|----------|:-----------:|:------------:|:--------:|:----------------:|
| Adquirir 1ra disciplina | 0 (gratis) | 1 | — | No |
| Adquirir 2da disciplina | 150 | 2 | — | No |
| Adquirir 3ra disciplina | 350 | 3 | — | No |
| Adquirir 4ta disciplina | 750 | 4 | — | No |
| Adquirir 5ta disciplina | 1.400 | 5 | — | No |
| Adquirir 6ta disciplina | 2.500 | 6 | — | No |
| Subir grado I→II | 80 | 2 | 7 días | Sí |
| Subir grado II→III | 140 | 3 | 14 días | Sí |
| Subir grado III→IV | 180 | 4 | 21 días | Sí |
| Subir grado IV→V | 250 | 5 | 30 días | Sí |

### 11.2 Coste Acumulado por Disciplina (hasta grado V)

Para llevar UNA disciplina de grado I a V:

| Salto | Coste por salto | Coste acumulado |
|:-----:|:---------------:|:---------------:|
| I → II | 80 PP | 80 PP |
| II → III | 140 PP | 220 PP |
| III → IV | 180 PP | 400 PP |
| IV → V | 250 PP | 650 PP |

**Total: 650 PP para dominar una disciplina por completo.**

### 11.3 Coste vs Stats

Para poner en perspectiva: subir un stat de rango 1 a 2 cuesta 50 PP (RG D). Subir una disciplina de I a II cuesta 80 PP. Es más caro que el primer nivel de stat, pero más barato que subir de 2 a 3 (130 PP).

**Relación:** Una disciplina en grado V cuesta 650 PP totales, equivalente a subir un stat de 1 a 4 (530 PP) más un pequeño extra. Es una inversión significativa pero accesible para un jugador activo (~130 posts de 500 palabras).

### 11.4 Múltiples Disciplinas: Coste Total

Escenario: un personaje con 3 disciplinas, todas en grado III.

| Concepto | Coste |
|----------|:-----:|
| 1ra disciplina (gratis) | 0 PP |
| 2da disciplina (adquirir) | 150 PP |
| 3ra disciplina (adquirir) | 350 PP |
| 1ra disc. I→II + II→III | 80 + 140 = 220 PP |
| 2da disc. I→II + II→III | 80 + 140 = 220 PP |
| 3ra disc. I→II + II→III | 80 + 140 = 220 PP |
| **Total** | **1.160 PP** |

Eso equivale a ~232 posts de 500 palabras (~4–6 meses a 2 posts/semana). Es una inversión considerable que refleja a un personaje con formación marcial sólida.

### 11.5 Costes de Disciplinas en data_json

```json
{
    "pp": 320,
    "pp_linaje": 0,
    "grado_last_upgrade_at": "2026-05-15 14:30:00",
    "grado_last_upgrade_rank": 3
}
```

No se almacenan los PP gastados en disciplinas (no hay un contador de "pp_gastado_disciplinas") — el coste se deduce de `data_json.pp` directamente. La única traza persistente es el cooldown.

---

## 12. Filosofía de Diseño

### 12.1 ¿Por qué 9 disciplinas específicas?

**Las 8 disciplinas de combate + 1 especial (Haki Conquistador) cubren TODO el espectro de armas y estilos de lucha en One Piece.**

| Si tu personaje usa... | Disciplina |
|------------------------|------------|
| Puños, patadas, artes marciales | `cuerpo_a_cuerpo` |
| Espadas, cuchillos, sables | `armas_de_filo` |
| Lanzas, naginatas, tridentes | `armas_de_asta` |
| Martillos, mazas, porras | `armas_contundentes` |
| Arcos, shurikens, honda | `armas_a_distancia` |
| Pistolas, rifles, cañones | `armas_de_fuego` |
| Arma única/exótica | `armas_exoticas` |
| Escudo defensivo | `escudo` |
| Haki de Conquistador | `haki_conquistador` (especial) |

**¿Por qué no separar "espadas" de "cuchillos"?** Porque ambas son armas de filo con la misma mecánica base (cortar). La diferencia entre una katana y un cuchillo es narrativa y se refleja en las cards, no en la disciplina.

**¿Por qué "Armas Contundentes" incluye puños?** Porque compartieron la categorización antigua. La línea entre `cuerpo_a_cuerpo` y `armas_contundentes` cuando usas nudilleras o guanteletes es fina. La regla práctica: si es una extensión física del cuerpo (puño con armadura ligera), es cuerpo_a_cuerpo. Si es un arma separada (martillo, maza), es contundente.

### 12.2 ¿Por qué grados I–V y no D→SS?

Las disciplinas usan grados romanos (I–V) mientras que los stats usan D→SS. ¿Por qué?

1. **Diferenciación semántica:** Los rangos D→SS miden poder/potencia bruta. Los grados I–V miden maestría/entrenamiento. Son escalas conceptualmente distintas.
2. **Menos confusión:** Si las disciplinas usaran D→SS, un jugador podría confundir "mi stat FUE es B" con "mi disciplina Armas de Filo es B". Con I–V está claro.
3. **Temática:** Los grados romanos evocan cinturones de artes marciales, niveles de maestro, rangos de habilidad en escuelas tradicionales. Es más apropiado para "disciplinas de combate".
4. **Escala corta:** 5 grados (vs 6 rangos D→SS) porque no necesitas granularidad extrema. La diferencia entre grado I y II es significativa y clara.

### 12.3 ¿Por qué las disciplinas no tienen "niveles" dentro del grado?

Cada grado es un peldaño discreto. No hay "grado II, 75% hacia III". La progresión es: estás en II, solicitas subir, si el staff aprueba, saltas a III. Esto reduce la granularidad y hace que cada mejora sea un evento significativo, no un incremento incremental.

### 12.4 ¿Por qué el coste de adquirir disciplinas escala?

**Primera disciplina: gratis.** Todo personaje de combate debe tener al menos una. Es parte de la identidad del personaje.

**Segunda disciplina: 150 PP.** Accesible para un jugador activo después de ~30 posts. Permite tener un backup o un estilo secundario.

**Tercera disciplina: 350 PP.** Empieza a doler. El jugador debe pensar: "¿realmente necesito una tercera disciplina o puedo profundizar en las que tengo?"

**Cuarta disciplina+: prohibitivo para la mayoría.** El sistema dice implícitamente: "elige 2–3 disciplinas y domínalas; no intentes abarcar 6."

**Filosofía:** El sistema premia la profundidad sobre la amplitud. Es mejor tener 2 disciplinas en grado IV que 5 en grado I. Un personaje con 2 disciplinas dominadas es más efectivo mecánica y narrativamente que uno con 5 disciplinas superficiales.

### 12.5 ¿Por qué Conquistador es una disciplina separada?

El Haki de Conquistador es fundamentalmente diferente de los otros tipos de Haki:

1. **Es innato, no se aprende.** No puedes entrenar para tener Conquistador — naces con él o no. En el sistema, no se adquiere con PP, se otorga por trama.
2. **No es un "tipo" de Haki como los otros.** Observación y Armamento son disciplinas de Haki (en el sistema de Haki, sección 10) con niveles progresivos. Conquistador es un talento especial que merece su propia categoría.
3. **Mecánicamente es simple:** No tiene árbol de técnicas como Observación (predicción) o Armamento (endurecimiento). Conquistador es básicamente "noquear débiles" y (avanzado) "infundir en ataques". Los grados I–V son suficientes para modelarlo.

### 12.6 ¿Por qué las disciplinas no dan bonus directos a stats?

En algunos sistemas RPG, tener "Espadas nivel 5" te da +X a FUE. Aquí no:

- Los stats son la **base física** del personaje. Un personaje débil (FUE baja) que entrena mucho con espadas (grado V en Armas de Filo) debería tener técnica pero no necesariamente fuerza bruta.
- El beneficio de tener grado V en una disciplina es **acceso a cards de tier 5** (las más poderosas). Ese es el incentivo mecánico.
- Narrativamente, tener una disciplina alta significa que el personaje ha entrenado dedicadamente. Eso **debería** reflejarse en la historia, no en un +1 automático a DES.

### 12.7 Principios Rectores

1. **Profundidad > Amplitud:** Es mejor dominar 2 disciplinas que tener 5 en grado I.
2. **PP como recurso compartido:** Gastas PP en stats O en disciplinas. Cada PP gastado en una disciplina es un PP que no gastas en stats. Hay tradeoff.
3. **Staff gate para grados altos:** Subir de grado no es automático como comprar stats. Requiere justificación narrativa. El staff es el guardián.
4. **Cooldown como pausa reflexiva:** 7–30 días entre mejoras obliga al jugador a considerar su siguiente movimiento.
5. **9 disciplinas es suficiente:** No todo necesita su propia disciplina. Un personaje con un "tridente" usa `armas_de_asta`. Un personaje con "cuchillo de cocina" usa `armas_de_filo`. No necesitamos "armas_de_cocina".

---

## 13. Consejos para Jugadores

### 13.1 ¿Cuántas disciplinas debería tener?

| Perfil | Disciplinas recomendadas | Estrategia |
|--------|:------------------------:|------------|
| Nuevo jugador | 1 (la inicial) | Domínala a grado III antes de pensar en otra |
| Jugador activo (3+ meses) | 2 | Principal (grado III–IV) + Secundaria (grado I–II) |
| Veterano (1+ año) | 2–3 | Principal (grado V) + 1–2 de soporte |
| Min-maxer extremo | 1 | Una sola disciplina al máximo (ahorra PP) |

**Regla de oro:** No adquieras una disciplina nueva hasta que tu disciplina principal esté al menos en grado III. Un personaje con `armas_de_filo IV` + `escudo II` es más efectivo que uno con 3 disciplinas en grado I.

### 13.2 ¿Qué disciplinas sinergizan?

| Combinación | Arquetipo | Cómo funciona |
|-------------|-----------|---------------|
| `cuerpo_a_cuerpo` + `escudo` | Tanque marcial | Peleas con puños y escudo, defensa + contraataque |
| `armas_de_filo` + `armas_a_distancia` | Versátil | Espada para cerca, arco/shurikens para lejos |
| `armas_de_asta` + `escudo` | Hoplita | Lanza + escudo, formación defensiva |
| `armas_contundentes` + `escudo` | Paladín | Martillo + escudo, daño de impacto + defensa |
| `armas_de_fuego` + `armas_a_distancia` | Tirador | Pistola + arco, versatilidad de munición |
| `cuerpo_a_cuerpo` + `armas_de_filo` | Monje espadachín | Puños + espada, ideal para estilos como Zoro (técnicas sin espada) |

### 13.3 Estrategia de Progresión

**Fase 1 (1–3 meses): Enfócate en una disciplina.**
- Lleva tu disciplina principal a grado III (220 PP totales).
- No adquieras una segunda hasta que la primera esté en III.
- Usa cards de tier 1–2 para aprender el sistema.

**Fase 2 (3–6 meses): Expande o profundiza.**
- Opción A: Lleva tu principal a grado V (650 PP total).
- Opción B: Adquiere una segunda disciplina (150 PP) y llévala a II (80 PP).
- Evalúa cuál opción se adapta más a tu personaje.

**Fase 3 (6+ meses): Especialización.**
- Con principal en V y secundaria en III, eres un luchador completo.
- Considera invertir en Haki o en una tercera disciplina exótica.

### 13.4 Lo que NO te dice el sistema

- **Las disciplinas no son armas.** Tener `armas_de_filo V` no te da una espada mágica. Necesitas una card de equipo (espada) para usar tus técnicas de filo con una buena tirada de dados.
- **Grado alto sin stats no sirve.** Un personaje con `armas_de_filo V` pero FUE 1 y DES 1 no puede usar bien sus técnicas porque las tiradas escalan con stats.
- **El cooldown es global.** Si subes `cuerpo_a_cuerpo a III` y luego quieres subir `armas_de_filo a II`, el cooldown de la primera mejora bloquea la segunda.
- **El staff puede rechazar tu solicitud de grado** si no has roleado el entrenamiento. No asumas que tener los PP suficientes garantiza la subida.

### 13.5 Ejemplo de Plan de Progresión (Personaje Espadachín)

1. **Creación:** Eliges `armas_de_filo` como disciplina inicial (gratis, grado I).
2. **Posts 1–30 (1–2 meses):** Roleas combates y entrenamiento básico con tu espada. Acumulas ~300 PP.
3. **Subes a grado II (80 PP):** Solicitud al staff, aprueban porque has roleado.
4. **Posts 31–60 (2–3 meses):** Combates más serios. Obtienes cards de tier 2. Acumulas ~300 PP más.
5. **Subes a grado III (140 PP):** Total gastado: 220 PP.
6. **Evaluación:** ¿Sigo con espada o adquiero otra disciplina? Decides adquirir `escudo` (150 PP, grado I).
7. **Posts 60–100:** Alternas espada y escudo. Subes `armas_de_filo` a IV (180 PP) y `escudo` a II (80 PP).
8. **Total gastado:** 220 + 150 + 180 + 80 = 630 PP. Tu personaje es un espadachín competente con capacidad defensiva.

---

## 14. Consejos para Staff

### 14.1 Evaluación de Solicitudes de Grado

Cada solicitud de subida de grado debe evaluarse contra tres criterios:

**1. Mecánico (objetivo):**
- ¿El personaje tiene el nivel global requerido?
- ¿Tiene los PP necesarios?
- ¿El cooldown ha expirado?
- Estas validaciones las hace el sistema automáticamente, pero el staff debe confirmar.

**2. Narrativo (subjetivo):**
- ¿El personaje ha entrenado esta disciplina IC desde que obtuvo el grado actual?
- ¿Hay posts recientes que muestren práctica, combates o estudio relevantes?
- ¿Obtener este grado tendría sentido en la historia del personaje?

**3. Progresión (estratégico):**
- ¿El personaje está subiendo demasiado rápido? (ej: grado I→III en un mes).
- ¿Hay otras disciplinas descuidadas que deberían subir primero?
- ¿El jugador está distribuyendo sus mejoras de forma equilibrada?

### 14.2 Criterios de Rechazo Comunes

| Motivo | Ejemplo | Acción sugerida |
|--------|---------|-----------------|
| Sin entrenamiento IC | "Pido espadas V pero mi personaje ha estado en una isla sin combate por 3 meses" | Rechazar y sugerir un arco de entrenamiento |
| Demasiado rápido | "Pido grado III después de solo 5 posts desde grado II" | Rechazar (el cooldown lo impediría automáticamente, pero verificar) |
| Incoherencia de personaje | "Mi personaje médico pacifista pide armas_contundentes IV" | Pedir justificación narrativa |
| Multisolicitud | "Pide grado al mismo tiempo que otro personaje suyo" | Verificar que no está alternando personajes para evadir cooldown |

### 14.3 Creación de Nuevas Cards Específicas de Disciplina

Cuando crees cards para el catálogo, sigue estas pautas:

- **Tier 1 (Grado I):** Efectos simples, dados 1d4–1d6, coste PE bajo (0–10). Ej: "Tajo Básico".
- **Tier 2 (Grado II):** Efectos moderados, dados 1d6–2d6, coste PE medio (10–20). Ej: "Oni Giri".
- **Tier 3 (Grado III):** Efectos notables, dados 2d6–3d6, coste PE medio-alto (15–30). Ej: "Tatsu Maki".
- **Tier 4 (Grado IV):** Efectos poderosos, dados 3d6–4d6, coste PE alto (30–50). Ej: "Yakkodori".
- **Tier 5 (Grado V):** Efectos devastadores, dados 4d6–6d6, coste PE muy alto (50–80). Ej: "Daishinkan".

```sql
-- Ejemplo de card bien balanceada
INSERT INTO mybb_game_cards (name, card_type, rank, tier, disciplina_slug,
    dice, cost_pe, effects_json)
VALUES ('Corte de Fénix', 'tecnica', 'B', 3, 'armas_de_filo',
    '2d8+des [CORTANTE]', '20',
    '{"tipo_tecnica":"ataque","tipo_daño":"fisico","efectos":["quemadura_superficial"],"alcance":"corto","bloqueable":true,"esquivable":true}');
```

### 14.4 Balance de Disciplinas

**¿Son todas las disciplinas igual de fuertes?**

No, y está bien. `cuerpo_a_cuerpo` y `armas_de_filo` son las más versátiles y con más cards en el catálogo. `escudo` es más nicho pero esencial para tanques. `haki_conquistador` es rarísima.

**El balance no está en la disciplina, está en las cards.** El staff puede equilibrar creando cards poderosas para disciplinas subrepresentadas. Si notas que nadie usa `armas_de_asta`, crea cards interesantes para lanzas y naginatas.

**Cómo detectar desbalance:**
- Revisa `game_character_disciplinas` periódicamente: ¿qué disciplinas son las más comunes?
- Si >60% de los personajes tienen `cuerpo_a_cuerpo` o `armas_de_filo`, tal vez necesitas incentivar otras.
- Si una disciplina no la tiene NADIE, pregúntate por qué: ¿es poco atractiva narrativamente? ¿sus cards son débiles?

### 14.5 Concesión de Haki de Conquistador

**Nunca** otorgues Conquistador a un personaje nuevo o sin una trama significativa. Criterios:

1. **Trayectoria:** El personaje debe tener una historia de voluntad fuerte demostrada IC.
2. **Momento narrativo:** Debe ser un clímax emocional o un punto de inflexión en la historia.
3. **Raza/relevancia:** No hay restricción por raza, pero el Conquistador es raro. No des uno por mes.
4. **Aprobación de staff:** Idealmente, que al menos 2 miembros del staff acuerden la concesión.

### 14.6 Migraciones y Ajustes Masivos

Si en el futuro necesitas añadir, modificar o eliminar disciplinas:

- **Añadir:** INSERT en `game_disciplinas` + crear cards asociadas. Los personajes existentes no se ven afectados.
- **Desactivar:** UPDATE `is_active = 0`. Las disciplinas existentes se conservan (los personajes no pierden grados), pero no aparecen en el catálogo de adquisición.
- **Renombrar:** UPDATE `slug` y `name`. Actualizar `disciplina_slug` en cards y estilos canónicos.
- **Eliminar:** No recomendado. Mejor desactivar. Si es inevitable, DELETE en cascada (disciplina → character_disciplinas → actualizar cards con disciplina_slug a NULL).

---

## 15. Referencia Rápida

### 15.1 Tabla de Disciplinas

| Slug | Nombre | Categoría | `staff_grant_only` | `fixed_pp_cost` |
|------|--------|-----------|:------------------:|:----------------:|
| `cuerpo_a_cuerpo` | Cuerpo a Cuerpo | Combate | 0 | null |
| `armas_de_filo` | Armas de Filo | Combate | 0 | null |
| `armas_de_asta` | Armas de Asta | Combate | 0 | null |
| `armas_contundentes` | Armas Contundentes | Combate | 0 | null |
| `armas_a_distancia` | Armas a Distancia | Combate | 0 | null |
| `armas_de_fuego` | Armas de Fuego | Combate | 0 | null |
| `armas_exoticas` | Armas Exóticas | Combate | 0 | null |
| `escudo` | Escudo | Combate | 0 | null |
| `haki_conquistador` | Haki de Conquistador | Especial | 1 | null |

### 15.2 Costes de Grado

| Grado | Nivel req. | PP (disciplina) | Cooldown | Tier de cards |
|:-----:|:----------:|:---------------:|:--------:|:-------------:|
| I | 1 | — | — | 1 |
| II | 2 | 80 | 7 días | 2 |
| III | 3 | 140 | 14 días | 3 |
| IV | 4 | 180 | 21 días | 4 |
| V | 5 | 250 | 30 días | 5 |

### 15.3 Costes de Adquisición

| N discipline | Coste | Nivel req. |
|:------------:|:-----:|:----------:|
| 1ra | 0 PP | 1 |
| 2da | 150 PP | 2 |
| 3ra | 350 PP | 3 |
| 4ta | 750 PP | 4 |
| 5ta | 1.400 PP | 5 |
| 6ta | 2.500 PP | 6 |
| 7ma | 4.000 PP | 6 |

### 15.4 Archivos Relevantes

| Archivo | Propósito |
|---------|-----------|
| `Guias/MAESTRO_SISTEMAS_RPG.md` | Sección 7 — Definición general |
| `Guias/sistemas/07-disciplinas.md` | **Este archivo** |
| `back/forum/game/inc/disciplinas_helpers.php` | Helpers de disciplinas |
| `back/forum/game/inc/grado_helpers.php` | Helpers de grados (compartido oficios) |
| `back/forum/game/ajax/acquire_competencia.php` | Adquirir disciplina |
| `back/forum/game/ajax/upgrade_competencia_grado.php` | Solicitar mejora de grado |
| `back/forum/game/ajax/character_competencias_get.php` | Obtener competencias del PJ |
| `back/forum/game/ajax/character_disciplinas_save.php` | Staff: asignar/editar disciplina |
| `back/forum/game/ajax/disciplinas_list.php` | Listar catálogo |
| `back/forum/game/sql/install_schema_fragments.php` | Schema SQL (disciplinas + character_disciplinas) |
| `back/forum/game/sql/estilos_canonicos_seed_data.php` | Vinculación disciplina→estilo |
| `Guias/sistemas/05-cards.md` | Integración cards + disciplinas |
| `Guias/sistemas/03-rangos.md` | Sistema de PP y costes de stats |
| `Guias/sistemas/08-estilos-canonicos.md` | Estilos canónicos (siguiente sección) |

### 15.5 Diagrama de Relaciones

```
game_disciplinas (catálogo)
    │
    ├── game_character_disciplinas (por personaje)
    │       └── rank (I–V)
    │
    ├── game_cards.disciplina_slug (FK lógica)
    │       └── Requisito: tier ≤ rank de disciplina
    │
    └── game_estilos_canonicos.disciplina_slug (FK lógica)
            └── Requisito: rank ≥ 2 para aprender el estilo

game_personajes.data_json
    ├── pp (se descuenta al adquirir/mejorar)
    ├── grado_last_upgrade_at (cooldown tracker)
    └── grado_last_upgrade_rank (último grado alcanzado)
```
