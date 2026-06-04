# Especificación Completa del Sistema RPG — One Piece Forum

> **Propósito:** Documento fuente para que una IA genere elementos (cartas, habilidades, NPCs, misiones, eventos, objetos) con coherencia absoluta respecto al sistema.  
> **Universo:** One Piece (canon + reglas de juego propias).  
> **Plataforma:** Foro MyBB con módulo PHP `game/` + JS frontend.  
> **Base de datos:** MySQL local (MyBB), tablas prefijadas `mybb_game_*`.

---

## Índice

1. [Arquitectura General](#1-arquitectura-general)
2. [Razas Jugables](#2-razas-jugables)
3. [Sistema de Factor Linaje (PL)](#3-sistema-de-factor-linaje-pl)
4. [Atributos](#4-atributos)
5. [Puntos de Vida y Energía (PV/PE)](#5-puntos-de-vida-y-energía-pvpe)
6. [Progresión y Niveles](#6-progresión-y-niveles)
7. [Sistema de Cartas (Deck)](#7-sistema-de-cartas-deck)
8. [Sistema de Akuma no Mi](#8-sistema-de-akuma-no-mi)
9. [Haki](#9-haki)
10. [Economía: Berries y PP](#10-economía-berries-y-pp)
11. [Calendario del Juego](#11-calendario-del-juego)
12. [Diario y Red de Contactos](#12-diario-y-red-de-contactos)
13. [Sistema de Búsquedas (Tablón)](#13-sistema-de-búsquedas-tablón)
14. [Sistema de Staff / Moderación](#14-sistema-de-staff--moderación)
15. [Sistema de Notificaciones](#15-sistema-de-notificaciones)
16. [Sistema de Híbridos](#16-sistema-de-híbridos)
17. [Subespecies Humanas](#17-subespecies-humanas)
18. [Linajes Elementales](#18-linajes-elementales)
19. [Oficios y Disciplinas](#19-oficios-y-disciplinas)
20. [Barcos](#20-barcos)
21. [NPCs y Mascotas](#21-npcs-y-mascotas)
22. [Post-Character Linkage](#22-post-character-linkage)
23. [Estados y Tags de Combate](#23-estados-y-tags-de-combate)
24. [API y Contratos](#24-api-y-contratos)
25. [Estructura de Base de Datos](#25-estructura-de-base-de-datos)

---

## 1. Arquitectura General

```
┌─────────────────────────────────────────────────────┐
│                   MyBB Forum                         │
│  Autenticación · Sesiones · Foros · Posts · Themes  │
├─────────────────────────────────────────────────────┤
│                 game/ (Módulo PHP)                    │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────┐  │
│  │  AJAX     │  │  Public  │  │  Services/       │  │
│  │  (JSON)   │  │  (HTML)  │  │  UseCases/       │  │
│  └──────────┘  └──────────┘  └──────────────────┘  │
├─────────────────────────────────────────────────────┤
│           Base de Datos MySQL (mybb_game_*)          │
│  personajes · cartas · akuma · pv_pe · notifs · etc │
└─────────────────────────────────────────────────────┘
```

### Principios
- **PHP orquesta, no hace lógica pesada**: valida, renderiza, consulta BD.
- **Contratos primero**: todo endpoint tiene OpenAPI + JSON Schema + ejemplos.
- **Seguridad**: MyBB session + CSRF (`my_post_key`) + staff_level por personaje.
- **Datos en BD local**: no hay backend externo de mecánicas (decisión D001).

---

## 2. Razas Jugables

10 razas principales + 5 subespecies humanas. Cada raza tiene:

### 2.1 Tabla de Razas

| Raza | PL Base | Híbrido PL | Pasivas Primarias | Pasivas Secundarias (Puro) |
|------|---------|------------|-------------------|---------------------------|
| **Humano** | 28 | 24 | 3 | 3 |
| **Mink** | 22 | 18 | 4 | 3 |
| **Gyojin** | 20 | 16 | 4 | 3 |
| **Gigante** | 16 | 12 | 4 | 3 |
| **Tontatta** | 24 | 20 | 4 | 3 |
| **Buccaner** | 22 | 18 | 3 | 3 |
| **Lunarian** | 16 | 12 | 4 | 3 |
| **Skypean** | 26 | 22 | 3 | 3 |
| **Oni** | 18 | 14 | 4 | 3 |
| **Sirena** | 22 | 18 | 4 | 3 |

### 2.2 Pasivas Primarias (resumen mecánico)

Son **gratuitas**, activas desde creación. Aplican a puros e híbridos (raza principal).

**Humano:**
- `pp_hum_01` Adaptabilidad Fisiológica: sin penalizadores ambientales
- `pp_hum_02` Polivalencia de Aprendizaje: −10% coste PP en Disciplinas y Oficios
- `pp_hum_03` Tenacidad Humana: 1/combate, PV fijado en 1 en vez de 0

**Mink:**
- `pp_mink_01` Guerrero de Cuna: Cuerpo a Cuerpo nivel 1 gratuito
- `pp_mink_02` Electro: medidor de 5 cargas, gana 1/turno. Cartas [Tag: Electro] usan INST
- `pp_mink_03` Sentidos Predadores: +3 INST percepción, visión nocturna, olfato
- `pp_mink_04` Pelaje Resistente: −1 dado daño [Corte]/[Perforación] Rango E-D

**Gyojin:**
- `pp_gyojin_01` Físico de Leyenda: FUE +80% para daño físico, carga, rotura
- `pp_gyojin_02` Anfibio Perfecto: sin penalizadores acuáticos
- `pp_gyojin_03` Acceso a Artes Gyojin: disciplina exclusiva + técnica acuática usa INT
- `pp_gyojin_04` Piel Escamada: −2 daño físico

**Gigante:**
- `pp_gigante_01` Escala Colosal — Físico: FUE ×2 para daño/rotura. Cap FUE +30%
- `pp_gigante_02` Escala Colosal — Alcance: alcance cuerpo a cuerpo 8m, Cartas [Área] duplican radio
- `pp_gigante_03` Piel de Montaña: −4 daño físico, inmune [Knockback] D o inferior
- `pp_gigante_04` Terror Natural: adversarios deben superar ESP 12 o sufrir [Terror Leve]

**Tontatta:**
- `pp_tontatta_01` Fuerza Muscular Desproporcionada: FUE ×3 en acciones directas
- `pp_tontatta_02` Blanco Esquivo: +4 AGI defensiva vs categoría Medio+, enemigos Colosales −3 INST
- `pp_tontatta_03` Excavador Nato: movimiento subterráneo
- `pp_tontatta_04` Sentidos Subterráneos: percepción vibratoria 15m

**Buccaner:**
- `pp_buccaner_01` Sangre Primigenia: +2 cap PV/nivel, herida grave al 25%
- `pp_buccaner_02` Voluntad de Hierro: inmune control mental Rango D, resistencia ventaja C-A
- `pp_buccaner_03` Fuerza Ancestral: +3 FUE permanente

**Lunarian:**
- `pp_lunarian_01` Llama Eterna — Encendida: −50% daño todas fuentes, −2 AGI
- `pp_lunarian_02` Llama Eterna — Apagada: +4 AGI, +3 FUE siguiente ataque
- `pp_lunarian_03` Alas Negras: vuelo natural
- `pp_lunarian_04` Superviviente Absoluto: PV→1 sin límite (solo con llama encendida)

**Skypean:**
- `pp_skypean_01` Alas de Isla: planeo prolongado
- `pp_skypean_02` Observación Innata: −1 dificultad Haki Observación
- `pp_skypean_03` Manejo de Dials: −10% PP en cartas de Dials

**Oni:**
- `pp_oni_01` Fuerza Demoníaca: +4 FUE permanente
- `pp_oni_02` Cuernos del Demonio: carta innata Embestida del Oni (Rango D), inmune [Aturdimiento] D-
- `pp_oni_03` Constitución Demoníaca: −3 daño físico, cap Vitalidad +20%
- `pp_oni_04` Ira del Oni: medidor 0-5, sube 1 por impacto. Escala bonuses FUE/AGI/daño

**Sirena:**
- `pp_sirena_01` Velocidad del Abismo: AGI ×2 en agua
- `pp_sirena_02` Canto Sirenico: disciplina exclusiva +3 ESP/INT persuasión fuera combate
- `pp_sirena_03` Sangre Vitalizante: +10% PV/turno sin daño
- `pp_sirena_04` Respiración Dual: respira igual agua/aire

### 2.3 Pasivas Secundarias (solo Puros)

Ver archivo `linaje_system.json` sección `pasivas_secundarias` para la lista completa. Cada raza tiene 3 pasivas secundarias que se activan solo si el personaje es **Puro** (no híbrido). Incluyen habilidades como:
- Humano: Potencial Sin Techo (sin cap racial en disciplinas)
- Mink: Sulong (transformación lunar con multiplicadores x2)
- Gyojin: Dominio de Corrientes, Grito del Fondo
- Gigante: Legado de Elbaf, Resiliencia Épica, Temblor de Tierra
- Tontatta: Comunión Vegetal, Inmunidad Venenos Naturales
- Buccaner: Herencia del Sol, Más Allá del Límite
- Lunarian: Dominio de Llama Corporal, Ignición Total
- Skypean: Vuelo Sostenido, Resonancia con el Viento, Maestría de Dials
- Oni: Legado de Wano, Transformación Demoníaca
- Sirena: Llamada de Poseidón, Marejada Controlada

---

## 3. Sistema de Factor Linaje (PL)

### 3.1 Asignación
- Al crear personaje, recibes `PL` según tu raza (ver tabla 2.1).
- Puedes gastar PL en:
  - **Árbol Racial** (perks específicos de tu raza)
  - **Árbol General** (perks disponibles para cualquier raza)
- PL sobrante → se convierte a PP bonus: **1 PL = 3 PP bonus**
- Estos PP bonus **no cuentan para el nivel** del personaje.

### 3.2 Árbol Racial
Cada raza tiene un árbol con perks que cuestan 1-6 PL. Ejemplo Humano:

| Perk | Coste | Requisito | Solo Puro |
|------|-------|-----------|-----------|
| Tenacidad Pura | 3 | — | No |
| Estudiante Dedicado | 1 | — | No |
| Liderazgo Natural | 2 | — | No |
| Determinación Extrema | 2 | — | No |
| El Sueño del Rey | 2 | — | No |
| Adaptación Acelerada | 3 | — | Sí |
| Potencial Haki Elevado | 4 | — | Sí |
| Subespecies (Piernas Largas, Brazos Largos, etc.) | 3-5 | — | No (pero exclusivas entre sí) |

### 3.3 Árbol General
Perks que **cualquier raza** puede comprar. Organizado en categorías:

| Categoría | Ejemplo Perks | Coste |
|-----------|--------------|-------|
| **Cuerpo y Constitución** | Piel de Acero (−5 daño), Vitalidad Extra (+20 PV), Constitución Bruta (+1 FUE, +1 VIT), Regeneración Vital (3%/turno) | 2-4 |
| **Mente y Percepción** | Mente Eidética (+2 INT recuerdo), Voluntad Férrea (+3 resist mental), Instinto Peligro (nunca sorprendido) | 2-3 |
| **Agilidad y Sigilo** | Paso Silencioso, Evasión Refleja (1/combate), Acróbata Natural | 2-3 |
| **Potencial de Haki** | Sensibilidad Observación (−1 req Rango), Dureza Interior (−1 req Armamento), Aura del Conquistador (8 PL, aprueba staff) | 3-8 |
| **Linaje Elemental** | Fuego, Rayo, Hielo, Viento, Tierra, Agua (permite técnicas elementales sin Akuma) | 5-6 |
| **Supervivencia y Suerte** | Suerte del Mar (reroll 1/arco), Golpe de Suerte, Fortuna del Pirata | 2-4 |
| **Carisma y Presencia** | Carisma Natural (+2 social), Presencia Imponente, Inspiración (+1 aliados) | 2-3 |
| **Talentos de Oficio** | Manos de Herrero, Ojo Médico, Genio Científico (−2 PP oficio) | 1-3 |
| **Rasgos Exóticos** | Cuatro Brazos, Tercer Ojo, Sangre Fría, Piel Cromática, Voz del Rey, Sangre de Gigante | 2-5 |

### 3.4 Híbridos
- Restan 4 PL de su raza principal.
- Acceden a 3 árboles: `racial_principal` (completo), `racial_secundario` (solo perks con `hibrido_accesible: true`), `general` (completo).
- No pueden comprar perks marcadas como `solo_puro: true`.
- No obtienen pasivas secundarias raciales.

---

## 4. Atributos

6 atributos base. Rango típico 1-20+.

| Atributo | Abrev. | Descripción |
|----------|--------|-------------|
| **Fuerza** | FUE | Daño físico, carga, rotura, presas |
| **Agilidad** | AGI | Evasión, velocidad, iniciativa, acrobacias |
| **Destreza** | DES | Precisión, puntería, reflejos finos, artesanía |
| **Instinto** | INST | Percepción, rastreo, detección, intuición |
| **Espíritu** | ESP | Voluntad, resistencia mental, Haki, carisma mágico |
| **Intelecto** | INT | Conocimiento, estrategia, medicina, ciencia |

### 4.1 Cálculo de PV/PE desde Atributos
```php
PV = (FUE × 4) + (AGI × 2) + (ESP × 3) + (INT × 1)
PE = (ESP × 4) + (DES × 3) + (AGI × 2) + (INT × 1)
```

### 4.2 Compra de Atributos
- Cada punto de atributo cuesta **PP** (Puntos de Progreso).
- Coste base: **5 PP** por punto.
- Cada 3 niveles, el coste sube +1 PP: `cost = 5 + floor((nivel - 1) / 3)`.
- Ejemplo: Nv.1 → 5 PP, Nv.4 → 6 PP, Nv.7 → 7 PP, etc.

---

## 5. Puntos de Vida y Energía (PV/PE)

### 5.1 Concepto
- **PV**: salud del personaje en combate. 0 = inconsciente/derrotado.
- **PE**: energía para usar cartas (técnicas, Haki, etc.).
- Se gestionan **por hilo** (thread) mediante la tabla `game_thread_pj_state`.

### 5.2 Estado por Hilo
Cada personaje en cada hilo tiene:
- `current_pv`, `current_pe`: valor actual
- `max_pv`, `max_pe`: valor máximo (calculado de atributos base + modificadores)
- `stat_mods_json`: modificadores temporales (ej: `{"fue": 2, "des": -1}`)
- `last_post_id`: último post donde se actualizó

### 5.3 Modificadores por Post
La tabla `game_post_characters` almacena:
- `pv_change`, `pe_change`: delta aplicado en ese post
- `modifiers_json`: cambios de estado aplicados

---

## 6. Progresión y Niveles

### 6.1 Sistema de Nivel
- Cada **20 puntos de atributo comprados** = 1 nivel.
- `nivel = 1 + floor(stat_points_purchased / 20)`
- Máximo **1 subida de nivel por semana** (cooldown de 7 días).

### 6.2 Puntos de Progreso (PP)
- Se obtienen por: roleo, eventos, staff awards, conversión de PL sobrante.
- Se gastan en: comprar puntos de atributo, disciplinas, oficios.
- `pp_linaje`: subconjunto de PP que venían de conversión de PL (se gastan primero).

### 6.3 Tope Semanal
Si ya subiste de nivel esta semana:
- Solo puedes comprar stats hasta quedar a **1 punto del siguiente umbral**.
- Ej: Nv.3, comprados 45 → umbral Nv.4 es 60. Máximo comprable: 59 - 45 = 14 puntos.

### 6.4 Disciplinas y Oficios
- **Disciplinas**: habilidades de combate (Cuerpo a Cuerpo, Esgrima, Artes Gyojin, Canto Sirenico, etc.)
- **Oficios**: profesiones no-combate (Herrero, Músico, Médico, Científico, Cocinero, Navegante, etc.)
- Cada nivel de Disciplina/Oficio cuesta PP (coste progresivo).

---

## 7. Sistema de Cartas (Deck)

### 7.1 Concepto
Las **cartas** representan técnicas, objetos, poderes y compañeros del personaje. Cada personaje tiene un **deck** de cartas asignadas.

### 7.2 Tipos de Carta

| Tipo | Descripción | Sub-tipos |
|------|-------------|-----------|
| `tecnica` | Habilidades de combate | Golpes, patadas, estilos de lucha |
| `equipo` | Objetos y armas | Arma, Armadura, Util/Consumible |
| `akuma_no_mi` | Poderes de fruta del diablo | Paramecia, Logia, Zoan |
| `haki` | Poderes de voluntad | Busoshoku, Kenbunshoku, Haoshoku |
| `npc_menor` | Compañeros no jugadores | NPC, Mascota |
| `barco` | Embarcaciones | Navío, Carabela, Fragata, Submarino, etc. |

### 7.3 Rangos
| Rango | Descripción | Ejemplo |
|-------|-------------|---------|
| **D** | Básico / Débil | Golpe básico, pistola común |
| **C** | Intermedio | Técnica entrenada, espada de calidad |
| **B** | Avanzado | Técnica de maestro, equipo raro |
| **A** | Élite | Técnica legendaria, equipo único |
| **S** | Casi mítico | Técnica de almirante, arma suprema |
| **SS** | Mítico / Dios | Solo narrativo, Joy Boy, Im |

### 7.4 Campos de una Carta

```json
{
  "id": 1,
  "name": "Gomu Gomu no Pistol",
  "card_type": "tecnica",
  "rank": "C",
  "activation": "activa",
  "tags": ["Impacto", "Caucho", "Cuerpo a Cuerpo"],
  "description": "Estira el brazo y golpea a distancia.",
  "cost_pe": "5",
  "execution_stat": "FUE",
  "dice": "1d8+2",
  "reposo": 2,
  "duracion": 0,
  "effects": {
    "damage_type": "fisico",
    "range": "media",
    "target": "single"
  },
  "upgrade": {
    "B": { "dice": "2d6+3", "cost_pe": "7" },
    "A": { "dice": "3d6+5", "cost_pe": "10" }
  }
}
```

### 7.5 Cartas Consumibles
Subtipo especial de `equipo/util`:
- Tienen `cantidad` (stack) en `game_character_cards`.
- Ejemplo: `Botiquín de Campo` (1d4 cura, 3 usos).
- Al gastarse, la cantidad se reduce.

### 7.6 Cartas de Barco
Tienen estadísticas propias:
- `tier`, `vida`, `ataque`, `velocidad`, `resistencia`

### 7.7 Cartas NPC / Mascota
Tienen:
- `vida` (HP), `tier` (mascotas), `acciones` (array de ataques/habilidades)

### 7.8 Jugar Cartas en Posts
- Endpoint: `POST /game/ajax/cards_play.php`
- Body: `{ post_id, card_ids: [1, 2, 3] }`
- Se registra qué cartas se usaron en ese post (tabla `game_post_cards`).
- Consume PE según `cost_pe` de cada carta.

### 7.9 Peticiones de Cartas
Los jugadores pueden:
- **Proponer carta personalizada**: enviar descripción → staff discute → si conforme, se crea.
- **Solicitar borrado**: pedir que se elimine una carta de su deck.
- **Solicitar carta del catálogo**: pedir una carta preexistente.
- **Solicitar upgrade**: subir de rango una carta (C→B→A→S).

Flujo de petición:
1. Jugador envía propuesta (`cards_request_custom.php` o `cards_request_action.php`)
2. Staff y jugador conversan en hilo de discusión (`cards_request_reply.php`)
3. Jugador confirma conformidad (`cards_request_conforme.php`)
4. Staff resuelve: aprueba o deniega (`cards_resolve_request.php`)

---

## 8. Sistema de Akuma no Mi

### 8.1 Catálogo
Tabla `game_akuma_no_mi` con:
- `id`, `name_es`, `name_jp`, `type` (Paramecia/Logia/Zoan), `status`, `is_occupied`, `is_reserved`
- `power_range`: rango de poder asignado por staff

### 8.2 Tirada Aleatoria
- Endpoint: `POST /game/ajax/akuma_roll.php`
- El personaje recibe una akuma aleatoria del catálogo disponible.
- Se crea una `admin_request` con `source = 'akuma_random'`.
- Solo se puede hacer 1 tirada por personaje (pendiente o aprobada bloquea).

### 8.3 Petición Directa
- Endpoint: `POST /game/ajax/admin_requests_submit.php`
- El jugador puede solicitar una akuma específica del catálogo.
- Staff revisa y decide.

### 8.4 Cartas de Akuma
Cuando se aprueba una akuma, se crea una carta de tipo `akuma_no_mi` en el deck del personaje con efectos según el tipo y nombre de la fruta.

---

## 9. Haki

### 9.1 Tipos de Haki

| Tipo | Nombre | Función |
|------|--------|---------|
| **Busoshoku** | Armamento | Endurecimiento, dañar Logias, defensa |
| **Kenbunshoku** | Observación | Percibir emociones, predecir ataques |
| **Haoshoku** | Conquistador | Dominar voluntades débiles, rarísimo |

### 9.2 Niveles de Haki
- `basico`, `medio`, `avanzado`, `maestro`, `despertado`
- Cada nivel otorga bonuses progresivos a stats y nuevas capacidades.

### 9.3 Requisitos
- Desbloquear Haki requiere cierto nivel de personaje y/o perks de Linaje.
- Perk `g_haki_obs_potencial` (−1 req Rango) y `g_haki_arm_potencial` (−1 req Rango).
- Perk `g_haki_conq_potencial` (8 PL) habilita posibilidad de Haoshoku.

---

## 10. Economía: Berries y PP

### 10.1 Berries
Moneda del mundo One Piece. Se gestionan en el personaje (campo `berries` en `game_personajes` o similar).
- Se obtienen por: roleo, misiones, eventos, comercio.
- Se gastan en: compras en el foro, objetos narrativos, etc.

### 10.2 Puntos de Progreso (PP)
Moneda de mejora de personaje.
- Se obtienen por: roleo, eventos, staff awards, conversión PL.
- Se gastan en: stats, disciplinas, oficios.
- NO se pueden transferir entre personajes.

---

## 11. Calendario del Juego

### 11.1 Fecha Global
- Época: `2026-05-01` (fecha real de inicio).
- Progresión: **1.5 días de juego por cada día real**.
- Cada año tiene 4 estaciones de 65 días = 260 días/año.
- Estaciones: Primavera → Verano → Otoño → Invierno.

### 11.2 Formato
```
"Día 47 de Verano, Año 3"
```

### 11.3 Almacenamiento
- Tabla `game_thread_meta`: cada hilo guarda su `day`, `season`, `year`.
- Endpoint: `GET /game/ajax/get_calendar.php` devuelve fecha global actual.

---

## 12. Diario y Red de Contactos

### 12.1 Estructura JSON
Almacenado en columna `cronologia_json` de `game_personajes`:

```json
{
  "diario": [
    {
      "id": "uuid",
      "day": 1,
      "season": 0,
      "year": 1,
      "category": "Presente",
      "desc": "Descripción del evento",
      "link": "https://foro/hilo-123",
      "thread_name": "Título del Hilo",
      "participants": [
        { "pj_id": 12, "name": "Zoro" }
      ]
    }
  ],
  "relaciones": [
    {
      "id": "uuid",
      "pj_id": 12,
      "name": "Roronoa Zoro",
      "tags": ["Amigo", "Compañero"],
      "desc": "Descripción de la relación",
      "image": "https://i.imgur.com/xxx.jpg",
      "is_npc": false
    }
  ],
  "groups": [
    {
      "id": "grp_uuid",
      "name": "Los Sombrero de Paja",
      "color": "#10b981",
      "members": ["id_rel_1", "id_rel_2"]
    }
  ]
}
```

### 12.2 Categorías del Diario
`Pasado`, `Presente`, `Mision`, `Evento`, `Trama`, `Fic`, `Off_Rol`

### 12.3 Tags de Relaciones
Tags libres para describir el vínculo (Amigo, Rival, Familia, Enemigo, Mentor, etc.). Se renderizan como chips de colores.

### 12.4 Grafo SVN
Las relaciones se dibujan como **grafo de red SVG interactivo** (sin frameworks, `jscripts/game/game_network.js`).
- Nodos: personajes
- Aristas: relaciones
- Blobs (convex hulls): grupos
- Colores según tags

---

## 13. Sistema de Búsquedas (Tablón)

### 13.1 Concepto
Los jugadores publican "búsquedas" de roleplay: buscan compañeros, tripulantes, rivales, etc.

### 13.2 Tabla `game_busquedas`
- `titulo`, `descripcion`, `imagen_url`, `status` (pendiente/aprobada/rechazada)
- `user_id`, `character_id`

### 13.3 Flujo
1. Jugador envía búsqueda → queda `pendiente`
2. Staff aprueba → `aprobada`, visible en tablón público
3. Otro jugador contacta → se genera notificación
4. Dueño acepta/rechaza contacto

### 13.4 Endpoints
- `busquedas_list.php` (GET) — lista pública
- `busquedas_pending.php` (GET) — staff
- `busquedas_submit.php` (POST) — enviar
- `busquedas_action.php` (POST) — staff aprueba/deniega
- `busquedas_contact.php` (POST) — contactar
- `busquedas_resolve_contact.php` (POST) — aceptar/rechazar

---

## 14. Sistema de Staff / Moderación

### 14.1 Jerarquía
| staff_level | Rol | Permisos |
|-------------|-----|----------|
| 1 | **Narrador** | Gestiona NPCs, eventos, suple al mod |
| 2 | **Moderador** | Aprueba PJs, gestiona búsquedas, resuelve peticiones |
| 3 | **Administrador** | Todo lo anterior + anuncios + config sistema |

### 14.2 Controles por Personaje
- `is_staff` (bool): si el personaje actual tiene permisos staff.
- `staff_level` (tinyint): nivel jerárquico.
- Los controles admin/mod de MyBB se envuelven en `<span class="rpg-modonly">`.
- CSS oculta `.rpg-modonly` por defecto; JS añade `body.rpg-staff` si `activeChar.is_staff` es true.

### 14.3 Acciones de Staff
- Aprobar/rechazar personajes (`aprobar_personaje.php`)
- Gestionar cartas (crear, editar, borrar, asignar, desasignar)
- Resolver peticiones de cartas
- Gestionar búsquedas
- Gestionar anuncios
- Resolver peticiones admin (akuma, etc.)
- Gestionar NPCs (`game_npc_assignments`)

---

## 15. Sistema de Notificaciones

### 15.1 Tipos
- `admin_request_pending`: nueva petición admin
- `admin_request_resolved`: petición resuelta
- `contact_request`: alguien contactó en búsqueda
- `contact_accepted`: contacto aceptado
- `card_request_update`: cambio en petición de carta
- `staff_broadcast`: mensaje del staff

### 15.2 Tabla `game_notifications`
- `user_id`, `character_id` (nullable), `type`, `title`, `body`, `link`, `is_read`, `is_dismissed`, `created_at`

### 15.3 Endpoints
- `notifications_list.php` (GET, paginado)
- `notifications_count.php` (GET, no leídas)
- `notifications_mark_read.php` (POST, individual)
- `notifications_mark_all_read.php` (POST)
- `notifications_dismiss.php` (POST, toggle)
- `notifications_delete.php` (POST)

---

## 16. Sistema de Híbridos

Reglas:
- Restan 4 PL del total racial.
- Acceden a 3 árboles: racial_principal, racial_secundario (solo perks `hibrido_accesible: true`), general.
- No pueden comprar perks `solo_puro: true`.
- No tienen pasivas secundarias raciales.
- El híbrido declara raza principal (determina pasivas primarias que recibe).

---

## 17. Subespecies Humanas

Los humanos pueden comprar perks de subespecie (exclusivas entre sí):

| Subespecie | Coste PL | Efectos |
|------------|----------|---------|
| Piernas Largas | 4 | Velocidad +30%, alcance patadas superior, inmune derribo |
| Brazos Largos | 4 | Alcance físico superior, +3 presas |
| Cuello Largo | 4 | Visión panorámica, +3 observación distancia |
| Gigantismo Humano | 5 | Talla 4-7m, FUE+2, VIT+20%, AGI−1 |
| Enanismo Humano | 3 | Talla 30-80cm, AGI defensiva+3, FUE−1 |

Cada una tiene perks hijos (ej: Piernas Largas → Patada Devastadora, Velocista del Mar).

---

## 18. Linajes Elementales

Perks del árbol general que permiten **técnicas elementales sin Akuma no Mi**:

| Elemento | Coste PL | Efecto Base | Disciplina 3+ | Disciplina 5+ |
|----------|----------|-------------|---------------|---------------|
| Fuego | 6 | Técnicas con fuego | Rango C | Rango B |
| Rayo | 6 | Descargas eléctricas | Rango C | Rango B |
| Hielo | 6 | Congelación, inmune frío | Rango C | — |
| Viento | 6 | Cortes de viento, empujes | Rango C | — |
| Tierra | 5 | Levitar rocas, +1 reducción | Rango C | — |
| Agua | 5 | Manipular agua | Rango C | — |

---

## 19. Oficios y Disciplinas

### 19.1 Disciplinas de Combate
- Cuerpo a Cuerpo
- Esgrima
- Esgrima de Wano (Oni)
- Artes Marciales Gyojin (Gyojin)
- Canto Sirenico (Sirena)
- Combate Shandia (Skypean Shandia)
- Tiroteo / Puntería
- Estilo Libre / Improvisación

### 19.2 Oficios
- Herrero
- Carpintero Naval
- Navegante
- Cocinero
- Médico
- Científico / Inventor
- Músico
- Cartógrafo
- Joyero / Artesano
- Mercader / Comerciante

Cada nivel de Disciplina u Oficio cuesta PP. Humanos tienen −10% coste por pasiva.

---

## 20. Barcos

### 20.1 Tipo de Carta `barco`
Subtipo de carta con estadísticas propias.

| Campo | Descripción |
|-------|-------------|
| `tier` | Nivel del barco (1-5+) |
| `vida` | HP del barco |
| `ataque` | Daño de cañones/armas |
| `velocidad` | Velocidad en nudos |
| `resistencia` | Reducción de daño |

### 20.2 Tipos de Barco
Balsa, Navío, Carabela, Galera, Fragata, Bergantín, Acorazado, Submarino

---

## 21. NPCs y Mascotas

### 21.1 Tipo de Carta `npc_menor`
- Subtipo: `npc` o `mascota`
- Atributos: `vida` (HP), `tier` (solo mascotas), `acciones` (array de ataques)

### 21.2 NPC Assignments
Tabla `game_npc_assignments`: asigna qué personajes staff (narradores) controlan qué NPCs.

---

## 22. Post-Character Linkage

### 22.1 Tabla `game_post_characters`
Vincula cada post de MyBB con el personaje activo que lo escribió.

| Columna | Descripción |
|---------|-------------|
| `post_id` | ID del post (PK) |
| `thread_id` | ID del hilo (solo primera entrada) |
| `user_id` | ID del usuario MyBB |
| `character_id` | ID del personaje (`game_personajes.id`) |
| `created_at` | Timestamp |
| `pv_change` | Delta PV aplicado en el post |
| `pe_change` | Delta PE aplicado en el post |
| `modifiers_json` | Cambios de estado en JSON |

### 22.2 Captura Automática
- Hook `datahandler_post_insert_post_end` → captura post_id + character_id
- Hook `datahandler_post_insert_thread_end` → captura post_id + thread_id
- Plugin `game_postcharacter.php` hace la captura automática.

### 22.3 Consulta
- `?uid=X&post_id=Y` → busca por post_id
- `?uid=X&thread_id=Y` → busca por thread_id
- `?uid=X` (solo uid) → fallback al personaje activo actual

---

## 23. Estados y Tags de Combate

### 23.1 Tags de Efecto
| Tag | Efecto |
|-----|--------|
| `[Tag: Impacto]` | Daño físico contundente |
| `[Tag: Corte]` | Daño físico cortante |
| `[Tag: Perforación]` | Daño físico penetrante |
| `[Tag: Quemadura Leve/Grave]` | Daño de fuego progresivo |
| `[Tag: Sangrado]` | Daño periódico |
| `[Tag: Parálisis Leve]` | −2 acciones |
| `[Tag: Aturdimiento Leve]` | −2 todas las tiradas |
| `[Tag: Derribo]` | Objetivo al suelo, pierde movimiento |
| `[Tag: Empuje]` | Desplazamiento forzado |
| `[Tag: Knockback]` | Empuje violento |
| `[Tag: Terror Leve]` | −1 todas tiradas |
| `[Tag: Miedo]` | Huida o inacción |
| `[Tag: Control Mental]` | Pérdida de control |
| `[Tag: Enredo]` | Inmovilización parcial |
| `[Tag: Congelación Leve]` | −AGI, daño frío |
| `[Tag: Velocidad]` | Prioridad de turno |
| `[Tag: Área de Efecto]` | Afecta múltiples objetivos |
| `[Tag: Ejecución]` | Ignora habilidades de PV→1 |
| `[Tag: Penetración]` | Ignora reducción de daño |

### 23.2 Estados Persistentes
- `[Extenuado]`: no puede realizar acciones ofensivas hasta fin de combate
- `[Hesitación]`: −2 a iniciativa/primer turno
- `[Confusión Leve]`: 50% de que la acción falle

---

## 24. API y Contratos

### 24.1 Formato Estándar de Respuesta
Toda respuesta JSON sigue el envelope:

```json
{
  "ok": true,
  "data": { ... },
  "error": null,
  "meta": { "endpoint": "nombre" }
}
```

En caso de error:
```json
{
  "ok": false,
  "data": null,
  "error": { "code": "NOT_FOUND", "message": "Personaje no encontrado" },
  "meta": null
}
```

### 24.2 Convenciones
- **GET**: endpoints de consulta (parámetros en query string)
- **POST**: endpoints mutadores (requieren `requireLogin()` + `requirePost()` + `requireCsrf()`)
- **CSRF**: se envía como `my_post_key` en el body, obtenido de `window.GAME_CSRF`

### 24.3 Lista Completa de Endpoints

#### Principales (contrato base)
| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `ping.php` | GET | Health check |
| `my_personajes.php` | GET | Listar personajes del usuario |
| `save_personaje.php` | POST | Crear/editar personaje |
| `set_active_pj.php` | POST | Cambiar personaje activo |
| `get_active_pj_for_user.php` | GET | Obtener personaje activo por uid/post/thread |
| `get_personaje_preview.php` | GET | Vista previa de personaje |
| `aprobar_personaje.php` | POST | Staff aprueba/rechaza |
| `personajes_pendientes_list.php` | GET | Staff: lista pendientes |
| `get_calendar.php` | GET | Fecha del juego |
| `get_thread_diary_data.php` | GET | Datos de hilo para diario |
| `update_cronologia.php` | POST | Guardar/eliminar diario, relaciones, grupos |
| `cards_list.php` | GET | Catálogo de cartas |
| `cards_my_deck.php` | GET | Deck del personaje |
| `cards_create.php` | POST | Staff: crear carta |
| `cards_update.php` | POST | Staff: editar carta |
| `cards_delete.php` | POST | Staff: borrar carta |
| `cards_assign.php` | POST | Staff: asignar carta |
| `cards_unassign.php` | POST | Staff: desasignar carta |
| `cards_play.php` | POST | Jugar carta en post |
| `cards_upgrade.php` | POST | Mejorar rango de carta |
| `cards_for_post.php` | GET | Cartas usadas en un post |

#### Peticiones de Cartas
| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `cards_pending_requests.php` | GET | Staff: peticiones pendientes |
| `cards_request_list_mine.php` | GET | Peticiones del personaje |
| `cards_request_action.php` | POST | Solicitar cambio de rango |
| `cards_request_custom.php` | POST | Proponer carta personalizada |
| `cards_request_conforme.php` | POST | Confirmar propuesta staff |
| `cards_request_reply.php` | POST | Responder en hilo de discusión |
| `cards_resolve_request.php` | POST | Staff: resolver petición |
| `cards_search_characters.php` | GET | Buscar personajes para asignar |

#### Búsquedas (Tablón)
| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `busquedas_list.php` | GET | Lista pública |
| `busquedas_pending.php` | GET | Staff: pendientes |
| `busquedas_submit.php` | POST | Enviar búsqueda |
| `busquedas_action.php` | POST | Staff: aprobar/denegar |
| `busquedas_contact.php` | POST | Contactar dueño |
| `busquedas_resolve_contact.php` | POST | Aceptar/rechazar contacto |

#### Akuma no Mi
| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `akuma_catalog.php` | GET | Catálogo de frutas |
| `akuma_roll.php` | POST | Tirada aleatoria |

#### Peticiones Admin
| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `admin_requests_submit.php` | POST | Enviar petición |
| `admin_requests_pending.php` | GET | Staff: pendientes |
| `admin_requests_action.php` | POST | Staff: resolver |

#### Notificaciones
| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `notifications_list.php` | GET | Lista paginada |
| `notifications_count.php` | GET | No leídas |
| `notifications_mark_read.php` | POST | Marcar leída |
| `notifications_mark_all_read.php` | POST | Marcar todas leídas |
| `notifications_dismiss.php` | POST | Ocultar/mostrar |
| `notifications_delete.php` | POST | Eliminar |

#### Progresión
| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `claim_character_level.php` | POST | Reclamar subida de nivel |
| `purchase_attribute.php` | POST | Comprar punto de atributo |

#### Otros
| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `announcements_list.php` | GET | Anuncios |
| `announcements_save.php` | POST | Staff: gestionar anuncios |
| `latest_activity.php` | GET | Actividad reciente del foro |
| `thread_pj_state.php` | GET | PV/PE en hilo |

---

## 25. Estructura de Base de Datos

### 25.1 Tablas del Sistema

#### `game_personajes`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT PK | ID personaje |
| `user_id` | INT | Dueño MyBB |
| `name` | VARCHAR | Nombre |
| `avatar` | VARCHAR | URL avatar |
| `race_name` | VARCHAR | Raza |
| `is_hybrid` | TINYINT | 1 si híbrido |
| `occupation_name` | VARCHAR | Ocupación |
| `arquetipo` | VARCHAR | Arquetipo bélico |
| `rango` | VARCHAR | Rango narrativo |
| `status` | ENUM | pendiente/aprobada/rechazada/revision |
| `is_staff` | TINYINT | Tiene permisos staff |
| `staff_level` | TINYINT | 1=Narrador, 2=Mod, 3=Admin |
| `is_npc` | TINYINT | Es NPC |
| `is_narrator` | TINYINT | Es narrador |
| `postnum` | INT | Posts escritos con este PJ |
| `threadnum` | INT | Hilos creados con este PJ |
| `data_json` | TEXT | Datos generales (biografía, etc.) |
| `stats_json` | TEXT | Atributos FUE/AGI/DES/INST/ESP/INT |
| `cronologia_json` | TEXT | Diario + relaciones + grupos |
| `linaje_json` | TEXT | Perks de linaje comprados |
| `pp` | INT | Puntos de Progreso actuales |
| `pp_linaje` | INT | PP provenientes de PL |
| `stat_points_purchased` | INT | Stats comprados total |
| `nivel` | INT | Nivel actual |
| `berries` | BIGINT | Berries |
| `last_level_up_at` | DATETIME | Última subida de nivel |
| `created_at` | TIMESTAMP | |

#### `game_user_config`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| `user_id` | INT PK | Usuario MyBB |
| `active_pj_id` | INT | Personaje activo actual |

#### `game_post_characters`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| `post_id` | INT PK | Post MyBB |
| `thread_id` | INT | Hilo (solo primer post) |
| `user_id` | INT | Usuario |
| `character_id` | INT | Personaje |
| `created_at` | TIMESTAMP | |
| `pv_change` | INT | Delta PV |
| `pe_change` | INT | Delta PE |
| `modifiers_json` | TEXT | Modificadores |

#### `game_thread_meta`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| `thread_id` | INT PK | Hilo MyBB |
| `thread_type` | ENUM | Tipo de hilo |
| `day` | INT | Día en juego |
| `season` | INT | Estación |
| `year` | INT | Año |

#### `game_thread_pj_state`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| `thread_id` | INT | Hilo |
| `character_id` | INT | Personaje |
| `current_pv` | INT | PV actual |
| `current_pe` | INT | PE actual |
| `stat_mods_json` | TEXT | Modificadores activos |
| `last_post_id` | INT | Último post de actualización |

#### `game_cards`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT PK | |
| `name` | VARCHAR | Nombre |
| `card_type` | ENUM | tecnica/equipo/akuma_no_mi/haki/npc_menor/barco |
| `rank` | ENUM | D/C/B/A/S/SS |
| `activation` | VARCHAR | activa/pasiva |
| `tags` | TEXT | JSON array |
| `description` | TEXT | |
| `cost_pe` | VARCHAR | Coste PE (ej: "5") |
| `execution_stat` | VARCHAR | Atributo |
| `dice` | VARCHAR | Dado (ej: "1d8+2") |
| `reposo` | INT | Turnos de enfriamiento |
| `duracion` | INT | Duración en turnos |
| `effects` | TEXT | JSON de efectos |
| `upgrade` | TEXT | JSON de mejoras por rango |

#### `game_character_cards`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| `character_id` | INT | Personaje |
| `card_id` | INT | Carta |
| `rank` | ENUM | Rango actual |
| `cantidad` | INT | Stack (consumibles) |
| `assigned_at` | TIMESTAMP | |

#### `game_post_cards`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| `post_id` | INT | Post |
| `card_id` | INT | Carta |
| `character_id` | INT | Personaje |

#### `game_card_requests`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT PK | |
| `character_id` | INT | |
| `card_id` | INT | Carta existente (opcional) |
| `request_type` | ENUM | create/add_existing/delete/upgrade |
| `status` | ENUM | pendiente/en_progreso/conforme/aprobada/denegada |
| `card_details_json` | TEXT | Propuesta de carta |
| `discussion_json` | TEXT | Hilo de discusión |
| `staff_id` | INT | Staff que resuelve |
| `created_at` | TIMESTAMP | |

#### `game_akuma_no_mi`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT PK | |
| `name_es` | VARCHAR | |
| `name_jp` | VARCHAR | |
| `type` | ENUM | Paramecia/Logia/Zoan |
| `status` | ENUM | activa/inactiva |
| `is_occupied` | TINYINT | Tiene dueño |
| `is_reserved` | TINYINT | Reservada por petición |
| `power_range` | VARCHAR | |

#### `game_admin_requests`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT PK | |
| `user_id` | INT | Solicitante |
| `character_id` | INT | |
| `source` | VARCHAR | akuma_random / akuma_demanda / general |
| `request_kind` | VARCHAR | |
| `title` | VARCHAR | |
| `description` | TEXT | |
| `link` | VARCHAR | |
| `status` | ENUM | pendiente/aprobada/denegada |
| `staff_user_id` | INT | Quién resuelve |
| `staff_char_id` | INT | |
| `staff_nota` | TEXT | |
| `payload_json` | TEXT | Datos extra |
| `akuma_fruit_id` | INT | Fruta solicitada |
| `created_at` | TIMESTAMP | |

#### `game_busquedas`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT PK | |
| `user_id` | INT | |
| `character_id` | INT | |
| `titulo` | VARCHAR | |
| `descripcion` | TEXT | |
| `imagen_url` | VARCHAR | |
| `status` | ENUM | pendiente/aprobada/rechazada |
| `created_at` | TIMESTAMP | |

#### `game_notifications`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT PK | |
| `user_id` | INT | |
| `character_id` | INT | Nullable |
| `type` | VARCHAR | |
| `title` | VARCHAR | |
| `body` | TEXT | |
| `link` | VARCHAR | |
| `is_read` | TINYINT | |
| `is_dismissed` | TINYINT | |
| `created_at` | TIMESTAMP | |

#### `game_announcements`
| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | INT PK | |
| `title` | VARCHAR | |
| `content` | TEXT | |
| `created_by` | INT | Staff user_id |
| `is_active` | TINYINT | |
| `created_at` | TIMESTAMP | |

#### Tablas adicionales
- `game_npc_assignments`: asigna personajes staff a NPCs
- `game_personajes_revisiones`: historial de revisiones de PJ
- `game_schema_migrations`: control de migraciones aplicadas

---

## Apéndice A: Ejemplos de Respuestas API

### Ping
```json
{
  "ok": true,
  "data": { "uid": 123, "username": "Luffy", "ts": 1710000000 },
  "error": null,
  "meta": { "endpoint": "ping" }
}
```

### Deck de Personaje (con consumible)
```json
{
  "ok": true,
  "data": {
    "cards": [
      {
        "id": 101,
        "name": "Botiquín de Campo",
        "card_type": "equipo",
        "rank": "C",
        "quantity": 3,
        "is_consumible": true,
        "effects": {
          "equipo_type": "util",
          "subtipo": "botiquin",
          "default_cantidad": 1,
          "util_dice": "1d4"
        }
      }
    ]
  },
  "error": null
}
```

### PV/PE State en Hilo
```json
{
  "ok": true,
  "data": {
    "thread_id": 42,
    "character_id": 7,
    "current_pv": 85,
    "current_pe": 62,
    "max_pv": 120,
    "max_pe": 95,
    "stat_mods": { "fue": 2, "des": -1 }
  },
  "error": null
}
```

### Notificaciones
```json
{
  "ok": true,
  "data": {
    "items": [
      {
        "id": 1,
        "character_id": null,
        "type": "admin_request_resolved",
        "title": "Petición «Gomu Gomu no Mi»: Aprobada",
        "body": "Tu petición ha sido aprobada.",
        "link": "https://foro/private.php?action=read&pmid=5",
        "is_read": false,
        "is_dismissed": false,
        "created_at": "2026-06-01T12:00:00Z"
      }
    ],
    "total": 1,
    "page": 1,
    "per_page": 20,
    "total_pages": 1
  },
  "error": null
}
```

---

## Apéndice B: Template de Carta para Generación IA

Usa esta plantilla para generar cartas coherentes:

```json
{
  "name": "Nombre de la Carta",
  "card_type": "tecnica|equipo|akuma_no_mi|haki|npc_menor|barco",
  "rank": "D|C|B|A|S",
  "activation": "activa|pasiva",
  "tags": ["Tag1", "Tag2"],
  "description": "Descripción narrativa y mecánica.",
  "cost_pe": "5",
  "execution_stat": "FUE|AGI|DES|INST|ESP|INT",
  "dice": "1d8+2",
  "reposo": 0,
  "duracion": 0,
  "effects": {
    "damage_type": "fisico|elemental|energetico",
    "range": "corto|media|largo",
    "target": "single|area|self"
  },
  "upgrade": {
    "B": { "dice": "2d6+3", "cost_pe": "7" },
    "A": { "dice": "3d6+5", "cost_pe": "10" },
    "S": { "dice": "4d8+7", "cost_pe": "15" }
  }
}
```

### Template de Perk de Linaje (para generar nuevos)

```json
{
  "id": "g_nombre_unico",
  "name": "Nombre del Perk",
  "desc": "Descripción mecánica detallada.",
  "cost": 3,
  "requires": null,
  "solo_puro": false,
  "hibrido_accesible": true,
  "categoria": "Cuerpo y Constitución|Mente y Percepción|..."
}
```

### Template de Personaje (data_json)

```json
{
  "historia": "Backstory del personaje...",
  "personalidad": "Rasgos de personalidad...",
  "fisico": "Descripción física...",
  "edad": 25,
  "origen": "Isla Dawn",
  "ocupacion": "Pirata",
  "faccion": "Sombrero de Paja",
  "sueño": "Ser el Rey de los Piratas",
  "profesion": "Capitán",
  "armas_favoritas": ["Puños", "Gomu Gomu"],
  "tecnica_favorita": "Gomu Gomu no Pistol",
  "debilidades": ["Agua (Akuma)", "Espadas"]
}
```

---

> **Documento generado a partir del código fuente en `back/forum/game/`, `packages/contracts/`, `front/templates/`, y archivos de datos `linaje_system.json`/`linaje_catalog.json`.**  
> Versión: 1.0 — Junio 2026
