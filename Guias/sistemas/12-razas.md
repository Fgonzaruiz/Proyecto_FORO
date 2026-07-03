# 12. Razas — Pasivas y Linaje — GUÍA COMPLETA

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 12
> **Propósito:** Documentar exhaustivamente el subsistema de razas: qué son, cómo se definen, sus 5 componentes mecánicos (pasivas primarias, pasivas secundarias, puntos de linaje, cards raciales, bonificaciones de stat), cómo se almacenan en DB, cómo se cargan en runtime, cómo se validan, la interfaz de linaje, la integración con la creación de personaje, y la filosofía completa de diseño — y **por qué** cada decisión se tomó así.
> **Prioridad:** Esta es la guía más detallada del sistema por solicitud expresa del administrador del foro.

---

## ÍNDICE

1. [Arquitectura del Sistema de Razas](#1-arquitectura-del-sistema-de-razas)
2. [¿Qué es una Raza?](#2-qué-es-una-raza)
3. [Estructura General de una Raza (5 Componentes)](#3-estructura-general-de-una-raza)
4. [Pasivas Primarias](#4-pasivas-primarias)
5. [Pasivas Secundarias](#5-pasivas-secundarias)
6. [Puntos de Linaje](#6-puntos-de-linaje)
7. [Cards Raciales](#7-cards-raciales)
8. [Bonificaciones y Penalizaciones de Stat](#8-bonificaciones-y-penalizaciones-de-stat)
9. [Database Schema](#9-database-schema)
10. [PHP Implementation](#10-php-implementation)
11. [Integration con Character Creation](#11-integration-con-character-creation)
12. [Lineage Tab UI (`_tab_linaje.php`)](#12-lineage-tab-ui)
13. [Staff Tools — Gestión de Razas](#13-staff-tools)
14. [Filosofía de Diseño](#14-filosofía-de-diseño)
15. [Consejos para Jugadores](#15-consejos-para-jugadores)
16. [Consejos para Staff](#16-consejos-para-staff)
17. [Catálogo Completo de Razas](#17-catálogo-completo-de-razas)

---

## 1. Arquitectura del Sistema de Razas

### 1.1 Capas del Subsistema

```
┌─────────────────────────────────────────────────────────────────┐
│                     CLIENTE (Navegador)                          │
│  ┌──────────────────┐  ┌──────────────────┐  ┌───────────────┐  │
│  │ crear_personaje  │  │ personaje.php    │  │ _tab_linaje   │  │
│  │ .js (wizard)     │  │ (ficha)          │  │ .php (vista)  │  │
│  │ - selección raza │  │ - sidebar stats  │  │ - pasivas     │  │
│  │ - árbol linaje   │  │ - tab linaje     │  │ - perks       │  │
│  │ - preview stats  │  │                  │  │ - PP bonus    │  │
│  └────────┬─────────┘  └────────┬─────────┘  └───────┬───────┘  │
│           │                     │                     │          │
└───────────┼─────────────────────┼─────────────────────┼──────────┘
            │                     │                     │
            ▼                     ▼                     ▼
┌─────────────────────────────────────────────────────────────────┐
│               AJAX + PHP — CAPA DE APLICACIÓN                     │
│                                                                   │
│  ┌─────────────────────┐  ┌──────────────────────────────────┐   │
│  │ StatScale.php       │  │ LinajeValidator.php              │   │
│  │ - getRacialBonuses  │  │ - getMaxLinajePoints()           │   │
│  │ - effectiveRanks    │  │ - validateAndBuild()             │   │
│  │ - effectiveValues   │  │ - findPerk()                     │   │
│  └─────────┬───────────┘  └──────────────┬───────────────────┘   │
│            │                              │                       │
│            ▼                              ▼                       │
│  ┌─────────────────────┐  ┌──────────────────────────────────┐   │
│  │ CharacterSheetLoader│  │ CharacterProgression             │   │
│  │ - mapRowToChar()    │  │ - syncLinajeBonusPp()            │   │
│  │ - stat_context      │  │ - normalize()                    │   │
│  │ (build con race)    │  │ - allocatePpSpend()              │   │
│  └─────────────────────┘  └──────────────────────────────────┘   │
│                              │                                    │
│                              ▼                                    │
│  ┌──────────────────────────────────────────────────────────┐    │
│  │ stat_helpers.php                                         │    │
│  │ - game_build_stat_context(ranks, raceName)                │    │
│  │ - game_compute_pv_pe_from_context(values, ranks)          │    │
│  └──────────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│          DATOS — MySQL + JSON Files                              │
│                                                                   │
│  game_personajes          game/data/                  game/data/ │
│  ┌──────────────┐        linaje_catalog.json      linaje_system  │
│  │ race (slug)  │        ┌──────────────┐          .json         │
│  │ race_name    │        │ stat_bonuses │        ┌──────────┐   │
│  │ stats_json   │◄───────│ por raza     │        │ pasivas  │   │
│  │ data_json    │        └──────────────┘        │ primarias│   │
│  │  └─ linaje   │                                │ secun-   │   │
│  └──────────────┘                                │ darias   │   │
│                                                   │ árboles  │   │
│                                                   │ raciales │   │
│                                                   │ general  │   │
│                                                   │ puntos   │   │
│                                                   └──────────┘   │
└──────────────────────────────────────────────────────────────────┘
```

### 1.2 Filosofía de la Arquitectura

**¿Por qué dos archivos JSON separados (`linaje_catalog.json` y `linaje_system.json`)?**

- `linaje_catalog.json` es el archivo **ligero** que solo contiene `stat_bonuses`. Lo usa `StatScale` que se carga en cada ficha, en cada preview de stats, en cada cálculo de PV/PE. Es pequeño (~2KB), se cachea en memoria estática (`$catalogCache`).
- `linaje_system.json` es el archivo **pesado** (~1766 líneas) que contiene TODO el sistema de linaje: pasivas, árboles raciales, árbol general, puntos por raza, metadatos. Se carga solo cuando es necesario: al crear personaje (wizard JS), al renderizar `_tab_linaje.php`, y durante la validación servidor (`LinajeValidator`).

**¿Por qué la raza se guarda en dos columnas (`race` y `race_name`)?**

- `race` (VARCHAR 50): slug normalizado (`humano`, `gyojin`, `hibrido`). Se usa para lógica de negocio, validaciones, joins conceptuales.
- `race_name` (VARCHAR 100): texto de display (`Humano`, `Gyojin`, `Híbrido (Mink / Gyojin)`). Se muestra al usuario. Para híbridos, contiene los paréntesis con las razas dominante/recesiva.
- Separar slug de display permite que el nombre narrativo sea rico (ej: `Híbrido (Mink / Gyojin)`) mientras el slug sigue siendo un valor procesable.

**¿Por qué los bonos raciales NO se guardan en `stats_json`?**

- Porque `stats_json` almacena ÚNICAMENTE rangos entrenados (comprados con PP).
- Los bonos raciales se calculan en runtime sumando el bono del catálogo al rango entrenado.
- Esto permite: (a) cambio de raza sin perder progreso, (b) modificadores temporales que se apilan sobre el efectivo, (c) transparencia para el jugador (sabe cuánto ha invertido vs cuánto le da su raza).

**¿Por qué el linaje se guarda en `data_json` y no en tabla aparte?**

- El linaje se define una vez (creación) y rara vez cambia. No justifica una tabla separada con JOINs.
- Se carga siempre con el personaje. No hay consultas tipo "SELECT * FROM linaje WHERE perk = X".
- La estructura es semiestructurada y puede evolucionar (v1 -> v2) sin migraciones de esquema.

### 1.3 Principios de Diseño

1. **La raza tiene peso mecánico real.** No es solo estética. Cada raza modifica stats, otorga pasivas, y define un árbol de linaje único.
2. **Asimetría balanceada.** Todas las razas tienen ventajas y desventajas. La suma de bonos de stat es ~0. Las pasivas se compensan con limitaciones.
3. **Runtime sobre persistencia.** Los bonos raciales nunca se escriben en `stats_json`. Se calculan en cada carga.
4. **El linaje es un tradeoff.** Gastar puntos en perks raciales/generales vs convertirlos a PP bonus. Elección significativa.
5. **Híbridos existen pero con coste.** -4 PL respecto a la raza dominante. Acceso limitado a perks.

---

## 2. ¿Qué es una Raza?

### 2.1 Definición

Una **raza** en el sistema es la **especie** del personaje dentro del mundo de One Piece. Define:

- **La biología del personaje:** qué puede hacer físicamente, qué limitaciones tiene.
- **Modificadores de stat iniciales:** algunas razas son más fuertes (+FUE), otras más rápidas (+AGI).
- **Habilidades pasivas innatas:** capacidades que vienen con la especie (respirar bajo el agua, generar electricidad, volar).
- **Un árbol de linaje exclusivo:** perks raciales que el jugador puede desbloquear con Puntos de Linaje.
- **Acceso a cards raciales:** técnicas y habilidades que solo esa raza puede usar.
- **Restricciones narrativas:** tamaño, esperanza de vida, relaciones con otras especies.

### 2.2 Razas Disponibles (10 + Híbrido)

| Raza | Slug | Esencia narrativa |
|------|------|-------------------|
| Humano | `humano` | Versátil, adaptable, sin bonos pero con más PL |
| Mink | `mink` | Animal humanoide, Electro, sentidos agudos |
| Gyojin | `gyojin` | Pez humanoide, dominio acuático, fuerza ×10 |
| Gigante | `gigante` | Talla colosal, fuerza bruta, tanque nato |
| Tontatta | `tontatta` | Miniatura, velocidad, fuerza desproporcionada |
| Buccaner | `buccaner` | Sangre de gigante antigua, Haki natural |
| Lunarian | `lunarian` | Llama eterna, vuelo, casi extintos |
| Skypean | `skypean` | Habitante del cielo, Dials, Mantra innato |
| Oni | `oni` | Cuernos, ira, constitución demoníaca |
| Sirena | `sirena` | Mitad pez, canto, velocidad acuática |
| Híbrido | `hibrido` | Combinación de dos razas, -4 PL, acceso limitado |

### 2.3 Design Philosophy: Why Races Have Mechanical Weight

**¿Por qué no son solo cosméticas como en otros foros?**

- **One Piece es un mundo de BIODIVERSIDAD EXTREMA.** Un Gigante NO es un humano grande. Un Mink NO es un humano con pelo. Son especies fundamentalmente diferentes con capacidades innatas distintas.
- **La elección de raza es la PRIMERA decisión mecánica del jugador.** Define arquetipo: ¿serás un tanque (Gigante), un DPS ágil (Mink), un versátil (Humano)?
- **Crea identidad de personaje.** Un personaje no es "un pirata fuerte"; es "un Gyojin pirata fuerte". Su raza informa su historia, sus relaciones, sus capacidades.
- **Las restricciones generan creatividad.** Un Tontatta NO puede ser un tanque frontal. Un Gigante NO puede ser sigiloso. Pero la gracia está en jugar CONTRA el arquetipo: un Gigante observador, un Tontatta tanque con FUE entrenada.

---

## 3. Estructura General de una Raza

Cada raza tiene **5 componentes mecánicos** definidos en el maestro (`MAESTRO_SISTEMAS_RPG.md` sección 12):

```
RAZA
├── 1. Pasivas Primarias     → Innatas, siempre activas (sin coste)
├── 2. Pasivas Secundarias   → Innatas, solo para personajes PUROS
├── 3. Puntos de Linaje      → Moneda racial para perks (PL)
│   ├── Árbol Racial         → Perks exclusivos de la raza
│   ├── Árbol General        → Perks disponibles para todos
│   └── Conversión a PP      → 1 PL sobrante = 2 PP bonus
├── 4. Cards Raciales        → Técnicas exclusivas de la raza
└── 5. Bonos/Penal. Stats    → Modificadores a stats iniciales
```

### 3.1 Dónde se Define Cada Componente

| Componente | Almacenamiento | Archivo/DB | Se aplica en |
|-----------|---------------|------------|--------------|
| Pasivas Primarias | `linaje_system.json` → `pasivas_primarias` | `game/data/linaje_system.json` | Runtime (display en tab linaje) |
| Pasivas Secundarias | `linaje_system.json` → `pasivas_secundarias` | `game/data/linaje_system.json` | Runtime (solo personajes puros) |
| Puntos de Linaje | `game_personajes.data_json.linaje` | DB + `linaje_system.json` para catálogo | Persistido, se valida en creación |
| Cards Raciales | `game_cards.notes` (con restricción de raza) | DB | Validación al asignar carta |
| Bonos/Penal. Stats | `linaje_catalog.json` → `stat_bonuses` | `game/data/linaje_catalog.json` | Runtime en `StatScale::getRacialBonuses()` |

### 3.2 Flujo Completo de Aplicación de una Raza

```
1. Creación de personaje
   ├── Jugador selecciona raza en wizard (paso 1)
   ├── Wizard carga linaje_system.json para:
   │   ├── Mostrar puntos de linaje disponibles
   │   ├── Mostrar pasivas primarias (automáticas)
   │   ├── Mostrar pasivas secundarias (si es puro)
   │   └── Mostrar árbol racial y general
   ├── Jugador selecciona perks (máx 2 raciales, 2 generales)
   ├── LinajeValidator::validateAndBuild() en servidor
   │   ├── Calcula puntos máximos (según raza)
   │   ├── Valida que no exceda límites
   │   ├── Calcula sobrante → bonus PP
   │   └── Devuelve struct linaje normalizado
   └── Se guarda en data_json.linaje

2. Carga de ficha (cada vez que se ve)
   ├── CharacterSheetLoader::load()
   │   ├── Lee stats_json (rangos entrenados)
   │   ├── Lee race_name de game_personajes
   │   ├── game_build_stat_context():
   │   │   ├── StatScale::getRacialBonuses(raceName)
   │   │   ├── Suma entrenados + raciales = efectivos
   │   │   └── Calcula valores numéricos y labels
   │   ├── CharacterProgression::syncLinajeBonusPp():
   │   │   ├── Recalcula bonus PP desde linaje
   │   │   └── Auto-repara si data_json está corrupto
   │   └── Render:
   │       ├── _sidebar.php: stats con/sin bono racial
   │       └── _tab_linaje.php: pasivas + perks

3. Runtime (combate, posts)
   ├── game_build_stat_context() con turnModifiers
   ├── PV/PE calculados con valores efectivos
   └── Pasivas raciales aplican según narración
```

---

## 4. Pasivas Primarias

### 4.1 Qué Son

Las **Pasivas Primarias** son habilidades innatas que el personaje tiene **siempre activas** desde el momento de su creación. No cuestan puntos de linaje, no requieren activación, no se pueden desactivar.

Son la expresión mecánica de la biología básica de la raza:
- Un Gyojin **respira bajo el agua** (Anfibio Perfecto)
- Un Mink **genera electricidad** (Electro)
- Un Gigante **tiene alcance colosal** (Escala Colosal)

### 4.2 Cómo se Almacenan

En `game/data/linaje_system.json` → `pasivas_primarias`:

```json
{
  "pasivas_primarias": {
    "_nota": "Activas desde creación, sin coste.",
    "Humano": [
      {
        "id": "pp_hum_01",
        "name": "Adaptabilidad Fisiológica",
        "desc": "Sin penalizadores de entorno en tiradas de Atributo por condiciones ambientales ordinarias (frío, calor, altitud, humedad)."
      },
      {
        "id": "pp_hum_02",
        "name": "Polivalencia de Aprendizaje",
        "desc": "Descuento permanente del 10% sobre el coste en PP de todos los niveles de Disciplina y Oficio."
      },
      {
        "id": "pp_hum_03",
        "name": "Tenacidad Humana",
        "desc": "Una vez por combate, cuando el personaje recibiría daño que lo llevaría a 0 PV, su Vitalidad queda fijada en 1 en su lugar."
      }
    ],
    "Mink": [
      {
        "id": "pp_mink_01",
        "name": "Guerrero de Cuna",
        "desc": "Obtiene la Disciplina Cuerpo a Cuerpo nivel 1 de forma gratuita al crear el personaje."
      },
      {
        "id": "pp_mink_02",
        "name": "Electro",
        "desc": "Medidor de Electro (capacidad 5, inicio en 2). Gana 1 Carga por turno activo. Cartas con [Tag: Electro] usan Instinto en lugar de Fuerza. Descarga de Emergencia 1/combate."
      },
      {
        "id": "pp_mink_03",
        "name": "Sentidos Predadores",
        "desc": "+3 permanente a tiradas de Instinto de percepción, rastreo y detección. Visión nocturna completa, olfato rastreador, audición amplificada."
      },
      {
        "id": "pp_mink_04",
        "name": "Pelaje Resistente",
        "desc": "Reducción de daño físico [Tag: Corte] o [Tag: Perforación] de Rango E y D en 1 punto de dados."
      }
    ]
  }
}
```

Cada pasiva primaria tiene:
- `id`: Identificador único (prefijo `pp_` para "pasiva primaria" + abreviatura raza + número)
- `name`: Nombre legible
- `desc`: Descripción mecánica completa

### 4.3 Cómo se Cargan y Aplican en Runtime

**En la ficha (`_tab_linaje.php`):**

```php
// _tab_linaje.php líneas 154-189
$pasiva_ids = $char['linaje']['pasivas'] ?? [];
$displayed_pasivas = [];
foreach ($pasiva_ids as $pid) {
    $found = find_perk_in_new_catalog($pid, $linaje_catalog);
    if ($found) $displayed_pasivas[] = $found;
}

// Si no hay pasivas guardadas (nuevo sistema v2),
// se renderizan las del catálogo según la raza
if (empty($displayed_pasivas)) {
    $races[] = $char_race;
    foreach ($races as $r) {
        $prim = $linaje_catalog['pasivas_primarias'][$r] ?? [];
        foreach ($prim as $p) {
            $p['type'] = 'primaria';
            $displayed_pasivas[] = enrich_perk_in_php($p);
        }
    }
}
```

**En la validación de servidor (`LinajeValidator`):**

```php
// LinajeValidator.php — las pasivas primarias se toman del
// catálogo por raza, no se "gastan" puntos en ellas
$pasivas = $this->normalizeIdList($linaje['pasivas'] ?? []);
// Se validan contra el catálogo pero no consumen PL
```

### 4.4 Ejemplos de lo que Pueden Hacer

| Tipo de pasiva | Ejemplo | Raza |
|---------------|---------|------|
| Stat bonus pasivo | +3 a Instinto de percepción | Mink |
| Resistencia | Reducción de daño físico 4 pts | Gigante |
| Sentido especial | Visión nocturna, olfato rastreador | Mink |
| Movilidad | Vuelo natural | Lunarian |
| Ambiental | Sin penalizadores acuáticos | Gyojin |
| Económica | Descuento 10% PP disciplinas | Humano |
| Combate | FUE ×2 para daño | Gigante |
| Medidor especial | Electro (cargas) | Mink |
| Innata gratuita | Carta Embestida del Oni | Oni |
| Supervivencia | Fijar PV en 1 al recibir golpe letal | Humano, Lunarian |

### 4.5 Design Philosophy: Why Always Active (No Toggle)

**¿Por qué las pasivas primarias no se pueden desactivar?**

- **Son biología, no habilidades.** Un Mink no "decide" tener sentidos agudos. Un Gyojin no "decide" poder respirar bajo el agua. Son parte de su fisiología.
- **Simplifica el combate.** No hay decisión de "activo o no activo mi pasiva racial". Está siempre ahí.
- **Las desventajas también son pasivas.** Un Gigante no puede "apagar" su tamaño para caber en espacios pequeños. Un Tontatta no puede "crecer" para alcanzar estantes altos.
- **El toggle existe en las pasivas secundarias** (ej: Lunarian puede apagar/encender su llama), que son más complejas y tienen coste.

---

## 5. Pasivas Secundarias

### 5.1 Qué Son

Las **Pasivas Secundarias** son habilidades raciales adicionales, de mayor poder o complejidad, que **solo los personajes de raza PURA** tienen acceso. No cuestan PL, pero requieren que el personaje NO sea híbrido.

Son más poderosas que las primarias y a menudo tienen:
- **Condiciones de activación** (luna llena, entorno acuático, umbral de PV)
- **Costes narrativos** (recibir daño, consumir un recurso)
- **Límites de uso** (1/combate, 1/día)
- **Tiradas de control** (Espíritu para dominar la habilidad)

### 5.2 Diferencia de Primarias

| Aspecto | Pasiva Primaria | Pasiva Secundaria |
|---------|----------------|-------------------|
| Acceso | Todas las razas | Solo personajes PUROS |
| Activación | Siempre activa | Puede requerir condición |
| Poder | Bajo a moderado | Moderado a alto |
| Complejidad | Simple (efecto pasivo) | Puede tener coste/tirada |
| Límite | Sin límite de uso | 1/combate, 1/día |
| Ejemplo | "Respiras bajo el agua" | "Transformación Sulong bajo luna llena" |

### 5.3 Almacenamiento

En `game/data/linaje_system.json` → `pasivas_secundarias`:

```json
{
  "pasivas_secundarias": {
    "_nota": "Solo para Personajes Puros. Activas desde creación sin coste.",
    "Mink": [
      {
        "id": "ps_mink_01",
        "name": "Sulong — La Transformación Lunar",
        "desc": "Bajo luz directa de luna llena declarada: Fuerza/Agilidad/Instinto ×2, Medidor Electro a 8 (recarga 2/turno). Coste: 8% PV máx. por turno. Requiere tirada de Espíritu dif.15 para control."
      },
      {
        "id": "ps_mink_02",
        "name": "Curación Acelerada del Linaje",
        "desc": "Tasa de curación fuera de combate duplicada. En combate, si no realiza acción ofensiva recupera 5% PV máx. al final de su turno."
      },
      {
        "id": "ps_mink_03",
        "name": "Resonancia del Pelaje",
        "desc": "Consume 2 Cargas de Electro: aplica [Tag: Parálisis Leve] automático a todos en contacto físico directo. Sin tirada de resistencia. 1/combate."
      }
    ]
  }
}
```

### 5.4 Cómo se Validan en la UI

En `_tab_linaje.php`, las pasivas secundarias solo se muestran si el personaje es de raza pura:

```php
// _tab_linaje.php líneas 175-189
foreach ($races as $r) {
    $prim = $linaje_catalog['pasivas_primarias'][$r] ?? [];
    foreach ($prim as $p) {
        $p['type'] = 'primaria';
        $displayed_pasivas[] = enrich_perk_in_php($p);
    }
    if (count($races) === 1) { // SOLO si es una raza (puro)
        $sec = $linaje_catalog['pasivas_secundarias'][$r] ?? [];
        foreach ($sec as $p) {
            $p['type'] = 'secundaria';
            $displayed_pasivas[] = enrich_perk_in_php($p);
        }
    }
}
```

### 5.5 Ejemplos por Raza

| Raza | Pasiva Secundaria | Condición |
|------|------------------|-----------|
| Mink | Sulong | Luna llena, tirada Espíritu 15, 8% PV/turno |
| Gyojin | Ventaja Acuática Absoluta | Combate subacuático |
| Gigante | Temblor de Tierra | 1/combate, acción libre |
| Lunarian | Ignición Total | 1/combate, turno completo |
| Oni | Transformación Demoníaca | Requiere 5 Puntos de Ira |
| Sirena | Marejada Controlada | 1/combate, requiere agua |
| Buccaner | Más Allá del Límite | <25% PV, 1/combate |
| Skypean | Vuelo Sostenido | Solo puro, sin coste |

---

## 6. Puntos de Linaje

### 6.1 Qué Son

Los **Puntos de Linaje (PL)** son una moneda especial de cada raza que el jugador gasta **durante la creación del personaje** para adquirir perks del árbol racial y del árbol general. Los puntos no gastados se convierten automáticamente en PP bonus.

**Regla fundamental:** 1 PL sobrante = 2 PP bonus (NUNCA modificable por multiplicadores de rango global).

### 6.2 Cómo se Obtienen

Los PL se asignan únicamente en la creación del personaje según su raza:

| Raza | PL | Lógica de diseño |
|------|:--:|------------------|
| Humano | 28 | Sin bonos de stat → más flexibilidad de linaje |
| Skypean | 26 | Buenos bonos + muchos PL = raza equilibrada |
| Tontatta | 24 | Buenos bonos pero desventaja de tamaño |
| Mink | 22 | Buenos bonos + electro = paquete completo |
| Gyojin | 20 | Buenos bonos físicos |
| Buccaner | 22 | Buenos bonos + Haki natural |
| Sirena | 22 | Buenos bonos + canto |
| Oni | 18 | Buenos bonos físicos + ira |
| Gigante | 16 | Grandes bonos físicos |
| Lunarian | 16 | Grandes bonos + llama + vuelo |

**Híbridos:** PL = `PL_raza_dominante - 4`. Un híbrido Mink/Gyojin tendría 22 - 4 = 18 PL.

### 6.3 Límites en Creación

```
Máximo 2 perks del árbol racial
Máximo 2 perks del árbol general
Los sobrantes se convierten a PP
```

### 6.4 Árbol Racial (Perks Exclusivos de Raza)

Cada raza tiene su propio árbol racial (`linaje_system.json` → `arboles_raciales`). Ejemplo del árbol Mink:

```json
{
  "Mink": {
    "_nota": "El árbol Mink gira en torno al Electro, el instinto animal, el Sulong...",
    "perks": [
      {
        "id": "rm_electro_maestro",
        "name": "Electro Maestro",
        "desc": "Capacidad máxima del Medidor de Electro aumenta a 7 (en vez de 5).",
        "cost": 3,
        "requires": null,
        "solo_puro": false,
        "hibrido_accesible": true
      },
      {
        "id": "rm_electro_distancia",
        "name": "Electro a Distancia",
        "desc": "Cartas [Tag: Electro] pueden descargarse a distancia corta (hasta 5m).",
        "cost": 2,
        "requires": "rm_electro_maestro",
        "solo_puro": false,
        "hibrido_accesible": true
      },
      {
        "id": "rm_sulong_control",
        "name": "Control Sulong Inicial",
        "desc": "La tirada de Espíritu para controlar el Sulong reduce su dificultad de 15 a 12.",
        "cost": 3,
        "requires": null,
        "solo_puro": true,
        "hibrido_accesible": false
      }
    ]
  }
}
```

Campos de cada perk:
- `id`: Identificador único (prefijo según raza: `rh_` Humano, `rm_` Mink, `rg_` Gyojin, `rgi_` Gigante, `rt_` Tontatta, `rb_` Buccaner, `rl_` Lunarian, `rs_` Skypean, `ro_` Oni, `rsi_` Sirena)
- `name`: Nombre del perk
- `desc`: Descripción mecánica completa
- `cost`: Coste en PL (1-6)
- `requires`: ID del perk requerido (null si es base)
- `solo_puro`: true = solo personajes de raza pura
- `hibrido_accesible`: true = disponible para híbridos en su árbol racial_secundario

### 6.5 Árbol General (Perks para Todas las Razas)

Disponible para cualquier raza. Organizado en categorías:

```json
{
  "arbol_general": {
    "categoria_cuerpo": {
      "nombre": "Cuerpo y Constitución",
      "perks": [
        { "id": "g_piel_acero", "name": "Piel de Acero", "cost": 3,
          "desc": "Reducción de daño físico recibido de 5 puntos." },
        { "id": "g_vitalidad_extra", "name": "Vitalidad Extra", "cost": 3,
          "desc": "+20 PV máximos permanentes." },
        { "id": "g_vitalidad_II", "name": "Vitalidad Extra II", "cost": 4,
          "requires": "g_vitalidad_extra" }
      ]
    },
    "categoria_mente": {
      "nombre": "Mente y Percepción",
      "perks": [
        { "id": "g_voluntad_ferrea", "name": "Voluntad Férrea", "cost": 3,
          "desc": "+3 resistencia mental. Inmune a miedo menor." }
      ]
    },
    "categoria_sigilo": { "nombre": "Agilidad y Sigilo", "perks": [...] },
    "categoria_haki_potencial": { "nombre": "Potencial de Haki", "perks": [...] },
    "categoria_elemental": { "nombre": "Linaje Elemental", "perks": [...] },
    "categoria_supervivencia": { "nombre": "Supervivencia y Suerte", "perks": [...] },
    "categoria_social": { "nombre": "Carisma y Presencia", "perks": [...] },
    "categoria_oficio": { "nombre": "Talentos de Oficio Innatos", "perks": [...] },
    "categoria_exoticos": { "nombre": "Rasgos Exóticos", "perks": [...] }
  }
}
```

### 6.6 Conversión a PP Bonus

El tradeoff central del sistema de linaje:

```
PP Bonus = sobrante × 2

Ejemplo:
- Humano: 28 PL total
- Gasta 4 PL en perks (2 raciales + 2 generales)
- Sobrante: 24 PL
- PP Bonus: 24 × 2 = 48 PP
```

**¿Por qué 1→2?**
- Para que valga la pena NO gastar puntos. 48 PP de bonus es suficiente para subir un stat de 1 a 2 (50 PP con RG D) casi inmediatamente.
- Pero 4 PL bien gastados en perks pueden ser más valiosos a largo plazo que 8 PP.

### 6.7 Almacenamiento en `data_json`

```json
{
  "linaje": {
    "version": 2,
    "pasivas": ["pp_hum_01", "pp_hum_02", "pp_hum_03"],
    "elegidos_racial": ["rh_tenaz"],
    "elegidos_general": ["g_vitalidad_extra"],
    "maxPoints": 28,
    "usedPoints": 4,
    "sobrantePoints": 24,
    "bonusPP": 48,
    "maxSlotsRacial": 2,
    "maxSlotsGeneral": 2
  }
}
```

### 6.8 LinajeValidator (Validación Servidor)

`LinajeValidator.php` es el guardián del sistema. Valida que el linaje enviado por el cliente sea legal:

```php
class LinajeValidator {
    private array $catalog;

    public function __construct(?array $catalog = null) {
        if ($catalog !== null) {
            $this->catalog = $catalog;
            return;
        }
        $path = dirname(__DIR__, 3) . '/data/linaje_system.json';
        $this->catalog = is_file($path) ? json_decode(file_get_contents($path), true) : [];
    }

    public function getMaxLinajePoints(string $raceName): int {
        $ptsPorRaza = $this->catalog['puntos_linaje_por_raza'] ?? [];
        $base = 4;
        if (preg_match('/Híbrid[o|a]\s*\(([^\\/]+)\s*\\/\s*([^)]+)\)/iu', $raceName, $m)) {
            $dom = trim($m[1]);
            $ptsDom = (int)($ptsPorRaza[$dom] ?? 20);
            return max(0, $ptsDom - 4);
        }
        return (int)($ptsPorRaza[$raceName] ?? $base);
    }

    public function validateAndBuild(string $raceName, array $linaje): array {
        $pasivas = $this->normalizeIdList($linaje['pasivas'] ?? []);
        $racial = $this->normalizeIdList($linaje['elegidos_racial'] ?? []);
        $general = $this->normalizeIdList($linaje['elegidos_general'] ?? []);

        $maxPoints = $this->getMaxLinajePoints($raceName);
        $spent = 0;
        foreach (array_merge($racial, $general) as $id) {
            $perk = $this->findPerk($id);
            $spent += (int)($perk['cost'] ?? 1);
        }

        if ($spent > $maxPoints) {
            return ['ok' => false,
                'message' => "Puntos de linaje inválidos: gastados {$spent}, máximo {$maxPoints}."];
        }

        $sobrante = $maxPoints - $spent;
        $bonusPp = $sobrante * 2;

        return [
            'ok' => true,
            'linaje' => [
                'version' => 2,
                'pasivas' => $pasivas,
                'elegidos_racial' => $racial,
                'elegidos_general' => $general,
                'maxPoints' => $maxPoints,
                'usedPoints' => $spent,
                'sobrantePoints' => $sobrante,
                'bonusPP' => $bonusPp,
                'maxSlotsRacial' => 2,
                'maxSlotsGeneral' => 2,
            ],
        ];
    }
}
```

### 6.9 Design Philosophy: Why a Separate Currency

**¿Por qué Puntos de Linaje y no usar PP o Berries?**

- **Los PP miden esfuerzo del jugador** (posts escritos). Los PL miden **identidad racial** (herencia biológica). Mezclarlos diluiría ambos conceptos.
- **Los PL son una decisión única.** Se gastan en creación y no se recuperan. Esto fuerza al jugador a pensar: "¿qué perks definen a mi personaje?" vs "¿qué stats subo primero?".
- **El tradeoff PL→PP bonus** es intencional. Un jugador puede sacrificar personalización racial por progresión inmediata. Es una decisión narrativa con peso mecánico.
- **Los Berries son dinero.** Comprar perks raciales con dinero rompería la lógica: "comprar" tu herencia biológica no tiene sentido.

---

## 7. Cards Raciales

### 7.1 Qué Son

Las **Cards Raciales** son cartas (tipo `tecnica` o del tipo que corresponda) que solo pueden ser obtenidas, equipadas o solicitadas por personajes de una raza específica.

No hay un campo `race_restriction` dedicado en la tabla `game_cards`. En su lugar, la restricción se indica en el campo `notes` con texto como `"Solo disponible para [RAZA]"`. La validación es responsabilidad del staff durante la revisión de solicitudes de carta.

### 7.2 Cómo se Gates

Actualmente, el sistema NO tiene validación automática de raza en la tabla `game_cards`. La validación se realiza en dos niveles:

1. **Solicitud al staff:** Cuando un jugador propone una carta técnica, el staff revisa si la raza del personaje es compatible con la carta.
2. **Asignación directa:** Cuando el staff asigna una carta, verifica manualmente que el personaje cumpla los requisitos raciales.

La estructura de `game_cards` no tiene campo de raza:

```sql
CREATE TABLE game_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    card_type ENUM('tecnica', 'equipo', 'akuma_no_mi', 'haki', 'npc_menor', 'barco') NOT NULL,
    `rank` ENUM('D', 'C', 'B', 'A', 'S', 'SS') NOT NULL DEFAULT 'C',
    activation ENUM('activa', 'pasiva', 'reactiva') NOT NULL DEFAULT 'activa',
    tags_json TEXT,
    description TEXT,
    cost_pe VARCHAR(50) DEFAULT '—',
    execution_cost INT NOT NULL DEFAULT 0,
    execution_stat VARCHAR(10) DEFAULT '',
    dice VARCHAR(150) DEFAULT '',
    effects_json TEXT,
    notes TEXT,            -- Aquí se indica "Solo disponible para Mink"
    ...
);
```

### 7.3 Ejemplos de Cards Raciales

| Card | Raza | Rango | Tipo | Efecto |
|------|------|:-----:|------|--------|
| Electro Shock | Mink | C | técnica | Descarga eléctrica cuerpo a cuerpo |
| Karate Gyojin: Muro de Agua | Gyojin | B | técnica | Barrera de agua defensiva |
| Embistida del Oni | Oni | D | técnica (innata) | Carga con cuernos, derribo |
| Canto Sirenico: Melodía de Paz | Sirena | C | técnica | Confusión emocional |
| Proyección de Llama | Lunarian | D | técnica (innata) | Proyectil de fuego |

### 7.4 Cómo se Obtienen

1. **Innatas:** Algunas razas reciben cartas innatas gratuitas (ej: Oni recibe "Embistida del Oni" como pasiva primaria).
2. **Solicitud al staff:** El jugador propone una carta técnica, el staff verifica que sea apropiada para la raza.
3. **Asignación directa:** El staff puede asignar cartas raciales como recompensa de eventos o misiones.
4. **Tienda:** Las cartas raciales pueden estar en la tienda con `notes` que indique la restricción de raza.

### 7.5 Design Philosophy

**¿Por qué no hay validación automática de raza en la tabla `game_cards`?**

- **Flexibilidad:** El staff puede decidir caso por caso si una carta es apropiada para un personaje. Una validación automática sería demasiado rígida.
- **Evolución narrativa:** Un personaje podría desarrollar una técnica que emule un efecto racial (ej: un humano aprendiendo a generar electricidad mediante entrenamiento).
- **Simplicidad:** Añadir un campo `race_restriction` requeriría migraciones y lógica extra. El sistema actual de `notes` + revisión manual funciona para el volumen del foro.

---

## 8. Bonificaciones y Penalizaciones de Stat

### 8.1 Catálogo Completo

Archivo: `game/data/linaje_catalog.json`

```json
{
  "races": {
    "Humano":    { "stat_bonuses": { "fue": 0,  "res": 0,  "agi": 0,  "des": 0,  "int": 0,  "inst": 0,  "esp": 0 } },
    "Mink":      { "stat_bonuses": { "fue": 0,  "res": 0,  "agi": 1,  "des": 1,  "int": 0,  "inst": 1,  "esp": 0 } },
    "Gyojin":    { "stat_bonuses": { "fue": 1,  "res": 1,  "agi": -1, "des": 0,  "int": 0,  "inst": 0,  "esp": 0 } },
    "Gigante":   { "stat_bonuses": { "fue": 2,  "res": 2,  "agi": -1, "des": -1, "int": 0,  "inst": 0,  "esp": 0 } },
    "Tontatta":  { "stat_bonuses": { "fue": -2, "res": -1, "agi": 2,  "des": 2,  "int": 1,  "inst": 0,  "esp": 0 } },
    "Buccaner":  { "stat_bonuses": { "fue": 1,  "res": 1,  "agi": 0,  "des": 0,  "int": 0,  "inst": 0,  "esp": 2 } },
    "Lunarian":  { "stat_bonuses": { "fue": 1,  "res": 2,  "agi": -1, "des": 0,  "int": 0,  "inst": 0,  "esp": 1 } },
    "Skypean":   { "stat_bonuses": { "fue": 0,  "res": 0,  "agi": 1,  "des": 0,  "int": 1,  "inst": 1,  "esp": 1 } },
    "Oni":       { "stat_bonuses": { "fue": 2,  "res": 1,  "agi": 0,  "des": -1, "int": -1, "inst": 1,  "esp": 0 } },
    "Sirena":    { "stat_bonuses": { "fue": -1, "res": 0,  "agi": 1,  "des": 1,  "int": 1,  "inst": 0,  "esp": 2 } }
  }
}
```

### 8.2 Tabla Visual

| Raza | FUE | RES | AGI | DES | INT | INST | ESP | Suma neta |
|------|:---:|:---:|:---:|:---:|:---:|:----:|:---:|:---------:|
| Humano | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| Mink | 0 | 0 | +1 | +1 | 0 | +1 | 0 | +3 |
| Gyojin | +1 | +1 | -1 | 0 | 0 | 0 | 0 | +1 |
| Gigante | +2 | +2 | -1 | -1 | 0 | 0 | 0 | +2 |
| Tontatta | -2 | -1 | +2 | +2 | +1 | 0 | 0 | +2 |
| Buccaner | +1 | +1 | 0 | 0 | 0 | 0 | +2 | +4 |
| Lunarian | +1 | +2 | -1 | 0 | 0 | 0 | +1 | +3 |
| Skypean | 0 | 0 | +1 | 0 | +1 | +1 | +1 | +4 |
| Oni | +2 | +1 | 0 | -1 | -1 | +1 | 0 | +2 |
| Sirena | -1 | 0 | +1 | +1 | +1 | 0 | +2 | +4 |

### 8.3 Cómo se Calculan en Runtime

`StatScale::getRacialBonuses()`:

```php
public static function getRacialBonuses(string $raceName): array
{
    $catalog = self::loadCatalog();  // linaje_catalog.json
    $race = $catalog['races'][$raceName] ?? null;
    $bonuses = is_array($race) ? ($race['stat_bonuses'] ?? []) : [];
    $out = [];
    foreach (self::STAT_KEYS as $key) {
        $out[$key] = (int)($bonuses[$key] ?? 0);
    }
    return $out;
}
```

Luego `game_build_stat_context()` suma entrenado + racial:

```php
function game_build_stat_context(array $statsRaw, string $raceName, array $turnModifiers = []): array
{
    $trained = StatScale::sanitizeRanks($statsRaw);
    $racial = StatScale::getRacialBonuses($raceName);
    $effectiveRanks = [];
    $values = [];
    $display = [];

    foreach (StatScale::STAT_KEYS as $key) {
        $mod = (int)($turnModifiers[$key] ?? 0);
        $effRank = (int)$trained[$key] + (int)($racial[$key] ?? 0) + $mod;
        $effectiveRanks[$key] = $effRank;
        $values[$key] = StatScale::rangoEfectivoAValor(
            (int)$trained[$key],
            (int)($racial[$key] ?? 0) + $mod
        );
        $display[$key] = StatScale::rankDisplayLabel($effRank);
    }

    return [
        'trained' => $trained,
        'effective_ranks' => $effectiveRanks,
        'values' => $values,
        'display' => $display,
    ];
}
```

### 8.4 Efecto en PV y PE

Los bonos raciales afectan PV/PE porque los cálculos usan valores efectivos (con bono):

```php
// _sidebar.php línea 146-149
$ctx = game_build_stat_context($char['stats'], (string)($char['race_name'] ?? ''));
$vitals = game_compute_pv_pe_from_context($ctx['values'], $ctx['trained']);
$pv = $vitals['max_pv'];
$pe = $vitals['max_pe'];
```

### 8.5 Visualización en Sidebar

En `_sidebar.php`, la fila de stat tiene clase `--racial` si hay bono:

```php
$hasRacial = (int)(StatScale::getRacialBonuses((string)($char['race_name'] ?? ''))[$key] ?? 0) !== 0;
```

```html
<div class="rpg-pj-stat-row rpg-pj-stat-row--rank<?= $hasRacial ? ' rpg-pj-stat-row--racial' : '' ?>">
    <div class="rpg-pj-stat-label">
        <span><i class="fas fa-dumbbell"></i> FUERZA</span>
        <span class="rpg-stat-rank rpg-stat-rank--a">A (efectivo)</span>
    </div>
    <div class="rpg-stat-rank-track">
        <!-- 6 segmentos llenos hasta rango ENTRENADO (sin bono) -->
        <span class="rpg-stat-rank-segment rpg-stat-rank-segment--filled"></span>
        ...
    </div>
</div>
```

**Filosofía visual:**
- Las barras muestran el rango ENTRENADO (tu progreso real)
- El label muestra el rango EFECTIVO (tu capacidad real)
- La fila tiene un color especial si hay bono racial activo

### 8.6 Design Philosophy: Why Penalties Exist

**¿Por qué algunas razas tienen penalizadores negativos?**

- **Balance a través de tradeoffs.** Si todas las razas tuvieran solo bonos positivos, todos elegirían la misma. Las penalizaciones FUERZAN decisiones significativas.
- **Narrativa.** Un Gigante es lento (AGI -1) y torpe (DES -1) porque su tamaño colosal lo hace menos ágil. Un Tontatta es débil (FUE -2) porque es diminuto.
- **Las penalizaciones se pueden compensar con PP.** Un Tontatta puede entrenar FUE para tener FUE entrenada 3, resultando en FUE efectiva 1 (3-2=1) — igual que un Humano sin entrenar. No es una condena eterna.
- **Las razas con penalizadores grandes tienen más PL.** Tontatta tiene FUE -2 pero 24 PL (muchos puntos para perks). Lunarian tiene 16 PL pero grandes bonos (+1 FUE, +2 RES, +1 ESP) + pasivas muy poderosas (llama, vuelo, reducción daño 50%).

---

## 9. Database Schema

### 9.1 `game_personajes` — Columnas de Raza

```sql
CREATE TABLE mybb_game_personajes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT DEFAULT NULL,
    name            VARCHAR(255) NOT NULL,
    race            VARCHAR(50) NOT NULL,      -- slug: humano, gyojin, hibrido
    race_name       VARCHAR(100) NOT NULL,     -- display: Humano, Híbrido (Mink / Gyojin)
    ...
    data_json       LONGTEXT,                  -- incluye linaje { version, pasivas, elegidos_*, bonusPP }
    stats_json      LONGTEXT,                  -- rangos entrenados { fue: 3, res: 2, ... }
    ...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 9.2 `game_cards` — Catálogo de Cartas

```sql
CREATE TABLE mybb_game_cards (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    card_type       ENUM('tecnica','equipo','akuma_no_mi','haki','npc_menor','barco') NOT NULL,
    `rank`          ENUM('D','C','B','A','S','SS') NOT NULL DEFAULT 'C',
    activation      ENUM('activa','pasiva','reactiva') NOT NULL DEFAULT 'activa',
    ...
    notes           TEXT,              -- Aquí se ponen restricciones raciales: "Solo disponible para Mink"
    ...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 9.3 `game_character_cards` — Cartas del Personaje

```sql
CREATE TABLE mybb_game_character_cards (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    character_id  INT NOT NULL,
    card_id       INT NOT NULL,
    is_equipped   TINYINT(1) NOT NULL DEFAULT 0,
    ...
);
```

### 9.4 `game_card_requests` — Solicitudes de Cartas

```sql
CREATE TABLE mybb_game_card_requests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    character_id    INT NOT NULL,
    card_id         INT NOT NULL DEFAULT 0,
    request_type    ENUM('delete', 'create', 'add_existing') NOT NULL,
    status          ENUM('pendiente', 'aprobada', 'rechazada', 'conforme') NOT NULL DEFAULT 'pendiente',
    card_details_json TEXT,
    ...
);
```

### 9.5 Archivos JSON (No SQL — Datos de Sistema)

**`game/data/linaje_catalog.json`** — Estructura:

```json
{
  "races": {
    "Humano":    { "puntos_iniciales": 4, "stat_bonuses": { "fue": 0, ... } },
    "Mink":      { "puntos_iniciales": 4, "stat_bonuses": { "fue": 0, ... } },
    ...
  }
}
```

**`game/data/linaje_system.json`** — Estructura (1766 líneas):

```json
{
  "_meta": { "version": "1.0", ... },
  "puntos_linaje_por_raza": { "Humano": 28, "Mink": 22, ... },
  "conversion_sobrante": { "ratio": "1 PL sobrante = 2 PP bonus" },
  "hibrido": { "modificador_puntos": -4, ... },
  "pasivas_primarias": { "Humano": [...], "Mink": [...], ... },
  "pasivas_secundarias": { "Humano": [...], "Mink": [...], ... },
  "arboles_raciales": { "Humano": { "perks": [...] }, "Mink": { ... } },
  "arbol_general": { "categoria_cuerpo": {...}, "categoria_mente": {...}, ... }
}
```

### 9.6 `data_json.linaje` — Estructura Persistida

```json
{
  "version": 2,
  "pasivas": ["pp_hum_01", "pp_hum_02", "pp_hum_03"],
  "elegidos_racial": ["rh_tenaz", "rh_lider"],
  "elegidos_general": ["g_vitalidad_extra"],
  "maxPoints": 28,
  "usedPoints": 5,
  "sobrantePoints": 23,
  "bonusPP": 46,
  "maxSlotsRacial": 2,
  "maxSlotsGeneral": 2
}
```

### 9.7 Datos de Raza por Personaje (Flujo Completo)

```
game_personajes.race          = "hibrido"
game_personajes.race_name     = "Híbrido (Mink / Gyojin)"
game_personajes.stats_json    = {"fue":3, "res":2, "agi":4, "des":2, "int":1, "inst":3, "esp":2}
game_personajes.data_json     = { ..., "linaje": { ... } }

En runtime:
  StatScale::getRacialBonuses("Híbrido (Mink / Gyojin)")
    → busca en linaje_catalog.json por nombre exacto
    → no encuentra "Híbrido..." → retorna [0,0,0,0,0,0,0]
  
  NOTA: Los híbridos NO tienen bonos de stat en el sistema actual.
  Su ventaja es la combinación narrativa y acceso a árboles mixtos.
```

---

## 10. PHP Implementation

### 10.1 StatScale::getRacialBonuses() — Carga de Bonos

```php
// Archivo: game/src/Shared/StatScale.php

private static ?array $catalogCache = null;

private static function loadCatalog(): array
{
    if (self::$catalogCache !== null) {
        return self::$catalogCache;
    }
    $path = dirname(__DIR__, 2) . '/data/linaje_catalog.json';
    if (!is_file($path)) {
        self::$catalogCache = ['races' => []];
        return self::$catalogCache;
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    self::$catalogCache = is_array($decoded) ? $decoded : ['races' => []];
    return self::$catalogCache;
}
```

**Cache estática:** `$catalogCache` evita leer el archivo JSON más de una vez por request. Se mantiene en memoria durante toda la ejecución del script PHP.

### 10.2 CharacterSheetLoader::mapRowToChar() — Carga de Personaje

```php
// Archivo: game/src/Application/Services/CharacterSheetLoader.php
private function mapRowToChar(array $row): array
{
    $data = !empty($row['data_json']) ? json_decode($row['data_json'], true) : [];
    $stats = !empty($row['stats_json']) ? json_decode($row['stats_json'], true) : [];

    $char = [
        'id' => (int)$row['id'],
        'user_id' => (int)$row['user_id'],
        'name' => $row['name'],
        'race_name' => !empty($row['race_name'])
            ? $row['race_name']
            : ($data['race'] ?? 'Desconocida'),
        'stats' => StatScale::sanitizeRanks($stats),
        'linaje' => $data['linaje'] ?? [],
        'stat_context' => game_build_stat_context(
            $stats,
            !empty($row['race_name'])
                ? (string)$row['race_name']
                : (string)($data['race'] ?? '')
        ),
        ...
    ];
    return $char;
}
```

**Importante:** `stat_context` se calcula en CADA carga de ficha. Nunca se cachea ni persiste. Es siempre runtime.

### 10.3 CharacterProgression::syncLinajeBonusPp() — Auto-reparación

```php
// Archivo: game/src/Application/Services/CharacterProgression.php
public static function syncLinajeBonusPp(array &$data, string $raceName): void
{
    $linaje = is_array($data['linaje'] ?? null) ? $data['linaje'] : [];
    if ((int)($linaje['version'] ?? 0) < 2 || trim($raceName) === '') {
        return;  // Sistema antiguo v1 o sin raza — no hace nada
    }

    $built = (new LinajeValidator())->validateAndBuild($raceName, $linaje);
    if (!($built['ok'] ?? false)) {
        return;  // Linaje inválido — no toca nada
    }

    $data['linaje'] = $built['linaje'];
    $bonus = (int)($built['linaje']['bonusPP'] ?? 0);
    if ($bonus <= 0) {
        return;
    }

    $pp = (int)($data['pp'] ?? 0);
    $ppLinaje = (int)($data['pp_linaje'] ?? 0);

    if ($pp < $bonus) {
        // Personaje nuevo o con PP insuficientes — asigna el bonus como PP
        $data['pp'] = $bonus;
        $data['pp_linaje'] = $bonus;
        return;
    }

    if ($ppLinaje === 0 && $pp > 0) {
        // Ya tenía PP pero no pp_linaje — marca el bono
        $data['pp_linaje'] = min($pp, $bonus);
    }
}
```

### 10.4 CharacterProgression::allocatePpSpend() — Gasto de PP Linaje

```php
// Cuando se compra un stat, los PP de linaje se gastan PRIMERO
public static function allocatePpSpend(int $cost, int $pp, int $ppLinaje): array
{
    $fromLinaje = min($cost, max(0, $ppLinaje));
    return [
        'from_linaje' => $fromLinaje,
        'new_pp' => $pp - $cost,
        'new_pp_linaje' => $ppLinaje - $fromLinaje,
    ];
}
```

### 10.5 LinajeValidator — Validación Completa

```php
// Archivo: game/src/Application/Services/LinajeValidator.php

class LinajeValidator
{
    public function getMaxLinajePoints(string $raceName): int
    {
        $ptsPorRaza = $this->catalog['puntos_linaje_por_raza'] ?? [];
        $base = 4;

        // Detectar híbrido: "Híbrido (Mink / Gyojin)"
        if (preg_match('/Híbrid[o|a]\s*\(([^\\/]+)\s*\\/\s*([^)]+)\)/iu', $raceName, $m)) {
            $dom = trim($m[1]);
            $ptsDom = (int)($ptsPorRaza[$dom] ?? 20);
            return max(0, $ptsDom - 4);
        }

        return (int)($ptsPorRaza[$raceName] ?? $base);
    }

    public function validateAndBuild(string $raceName, array $linaje): array
    {
        $pasivas  = $this->normalizeIdList($linaje['pasivas'] ?? []);
        $racial   = $this->normalizeIdList($linaje['elegidos_racial'] ?? []);
        $general  = $this->normalizeIdList($linaje['elegidos_general'] ?? []);

        $maxPoints = $this->getMaxLinajePoints($raceName);
        $spent = 0;

        foreach (array_merge($racial, $general) as $id) {
            $perk = $this->findPerk($id);
            $spent += (int)($perk['cost'] ?? 1);
        }

        if ($spent > $maxPoints) {
            return ['ok' => false, 'message' => "Puntos excedidos."];
        }

        $sobrante = $maxPoints - $spent;
        $bonusPp = $sobrante * 2;

        return [
            'ok' => true,
            'linaje' => [
                'version' => 2,
                'pasivas' => $pasivas,
                'elegidos_racial' => $racial,
                'elegidos_general' => $general,
                'maxPoints' => $maxPoints,
                'usedPoints' => $spent,
                'sobrantePoints' => $sobrante,
                'bonusPP' => $bonusPp,
                'maxSlotsRacial' => 2,
                'maxSlotsGeneral' => 2,
            ],
        ];
    }
}
```

### 10.6 Flujo de Carga en Render de Ficha

```
personaje.php?pj=N
  │
  ├── CharacterSheetLoader::load()
  │     ├── SELECT * FROM game_personajes WHERE id = N
  │     ├── json_decode(data_json) → $dataForProg
  │     ├── CharacterProgression::syncLinajeBonusPp($dataForProg, $raceName)
  │     │     └── LinajeValidator::validateAndBuild() → recalcula bonusPP
  │     ├── CharacterProgression::normalize($dataForProg)
  │     ├── sanitizeRanks(stats_json) → $stats
  │     ├── CharacterProgression::snapshot($dataForProg, $stats)
  │     └── mapRowToChar() → $char
  │           ├── race_name
  │           ├── stats (trained)
  │           ├── linaje (from data_json)
  │           └── stat_context → game_build_stat_context(stats, raceName)
  │
  ├── page.php
  │     ├── _sidebar.php
  │     │     ├── game_build_stat_context() → ctx
  │     │     ├── game_compute_pv_pe_from_context() → PV/PE
  │     │     └── render stats con barras (entrenado) + label (efectivo)
  │     └── _tab_linaje.php
  │           ├── cargar linaje_system.json
  │           ├── buscar pasivas display
  │           ├── buscar perks raciales/generales
  │           └── render cards + dots de PL
  └── Response HTML
```

---

## 11. Integration con Character Creation

### 11.1 Paso 1: Identidad — Selección de Raza

Archivo: `game/public/crear_personaje.php`

```php
// Línea 149: Lista de razas disponibles
$razas = ['Humano', 'Mink', 'Gyojin', 'Gigante', 'Tontatta',
          'Buccaner', 'Lunarian', 'Skypean', 'Oni', 'Sirena'];
```

HTML del selector:

```html
<select id="pj_race" class="textbox" required onchange="checkHibrido()">
    <option value="" disabled selected>Selecciona tu raza</option>
    <option value="Humano">Humano</option>
    <option value="Mink">Mink</option>
    <option value="Gyojin">Gyojin</option>
    <option value="Gigante">Gigante</option>
    <option value="Tontatta">Tontatta</option>
    <option value="Buccaner">Buccaner</option>
    <option value="Lunarian">Lunarian</option>
    <option value="Skypean">Skypean</option>
    <option value="Oni">Oni</option>
    <option value="Sirena">Sirena</option>
    <option value="Híbrido">Híbrido</option>
</select>
```

**Híbridos:** Al seleccionar "Híbrido", la función JS `checkHibrido()` muestra selects para raza dominante + recesiva.

### 11.2 Paso 2: Conocimientos — Preview de Stats

El wizard muestra preview en vivo de stats con bonos raciales:

```javascript
// crear_personaje.js
// Cuando cambia la raza, se actualiza la preview de stats:
// 1. Se cargan los bonos de raza desde el catálogo
// 2. Se suman a los stats base (distribución 1 punto)
// 3. Se actualizan los labels de rango efectivo
// 4. Se recalcula PV/PE
```

**Punto de distribución:** En creación, el jugador tiene solo 1 punto para repartir entre 7 stats (todos base 1, máximo 2 en creación). Esto es intencional: eres un novato.

**Ejemplo de cálculo en wizard:**

```
Raza: Mink (bonos: AGI+1, DES+1, INST+1)
Stats base: todos 1
Punto asignado a: AGI → AGI entrenado = 2

Stats entrenados: { fue:1, res:1, agi:2, des:1, int:1, inst:1, esp:1 }
Stats efectivos:  { fue:1, res:1, agi:3, des:2, int:1, inst:2, esp:1 }
PV ≈ (res_val×4)+(fue_val×3)+(esp_val×2)+(agi_val×1) = 24+12+8+15 = 59
PE ≈ (esp_val×4)+(des_val×3)+(int_val×2)+(agi_val×1) = 8+24+8+15 = 55
```

### 11.3 Paso 3: Expediente — Sistema de Linaje

El paso 3 carga TODO el `linaje_system.json` y renderiza:

1. **Puntos de Linaje disponibles** según raza
2. **Pasivas Primarias** (automáticas, no gastan PL)
3. **Pasivas Secundarias** (solo si es puro)
4. **Árbol Racial** (perks exclusivos, máx 2)
5. **Árbol General** (perks para todos, máx 2)
6. **PP Bonus** (dinámico según sobrante)

```javascript
// crear_personaje.js
// Config: window.CREAR_PERSONAJE_CONFIG contiene linaje_system.json completo
// Cuando el jugador selecciona/deselecciona perks:
//   1. Calcula PL gastados
//   2. Calcula sobrante → PP Bonus
//   3. Actualiza UI: "Puntos Sobrantes: X PL = Y PP de Bonus"
```

### 11.4 Validación Servidor (`save_personaje.php`)

Al enviar, `CharacterSaveService::buildPayloadForInsert()`:

```php
public function buildPayloadForInsert(array $input, string $raceName): array
{
    // 1. LinajeValidator::validateAndBuild() → valida y normaliza
    $linajeResult = (new LinajeValidator())->validateAndBuild($raceName, $input['linaje']);
    if (!$linajeResult['ok']) {
        throw new \InvalidArgumentException($linajeResult['message']);
    }

    // 2. Sanitizar stats
    $stats = StatScale::sanitizeRanks($input['stats']);

    // 3. Calcular rango global
    $data = [
        'linaje' => $linajeResult['linaje'],
        'pp' => $linajeResult['linaje']['bonusPP'],
        'pp_linaje' => $linajeResult['linaje']['bonusPP'],
        'rank' => StatScale::globalRankFromSum(StatScale::sumRanks($stats)),
        'nivel' => 1,
        // ...
    ];

    return [
        'data_json' => $data,
        'stats_json' => $stats,
        'race_name' => $raceName,  // se persiste en game_personajes
        'race' => $this->slugFromRaceName($raceName),  // slug
    ];
}
```

### 11.5 Asignación de `race` (slug) y `race_name`

```php
private function slugFromRaceName(string $raceName): string
{
    // Simplificado: detecta híbrido
    if (stripos($raceName, 'Híbrido') === 0 || stripos($raceName, 'Hibrido') === 0) {
        return 'hibrido';
    }
    return strtolower(trim($raceName));
}
```

---

## 12. Lineage Tab UI

### 12.1 Estructura General

Archivo: `game/views/personaje/_tab_linaje.php` (324 líneas)

```html
<div id="pjTab_linaje" class="pj-preview-tab-content">
    <!-- 1. Linaje Slots Bar (Puntos de Linaje) -->
    <div class="linaje-slots-bar">
        <div class="linaje-slots-group">
            <span class="linaje-slots-label">
                <i class="fas fa-gem"></i> Puntos de Linaje:
            </span>
            <div class="linaje-slots-dots">
                <!-- Dots llenos/vacíos según PL gastados -->
            </div>
            <span class="linaje-slots-count">5/28</span>
        </div>
        <div id="linajeSobranteBonus">
            Puntos Sobrantes: 23 PL = 46 PP de Bonus
        </div>
    </div>

    <!-- 2. Pasivas Innatas (Primarias + Secundarias) -->
    <div class="pj-linaje-section-title pj-linaje-section-title--green">
        <i class="fas fa-shield-alt"></i> Pasivas Innatas
    </div>
    <div class="gene-cards-grid">
        <!-- render_perk_card() para cada pasiva -->
    </div>

    <!-- 3. Linaje Racial (perks del árbol racial) -->
    <div class="pj-linaje-section-title pj-linaje-section-title--indigo">
        <i class="fas fa-dna"></i> Linaje Racial
    </div>
    <div class="gene-cards-grid">
        <!-- render_perk_card() para cada perk racial -->
    </div>

    <!-- 4. Linaje General (perks del árbol general) -->
    <div class="pj-linaje-section-title pj-linaje-section-title--purple">
        <i class="fas fa-star"></i> Linaje General
    </div>
    <div class="gene-cards-grid">
        <!-- render_perk_card() para cada perk general -->
    </div>
</div>
```

### 12.2 Helper Functions

```php
// Busca un perk por ID en TODO el catálogo (pasivas, racial, general)
function find_perk_in_new_catalog(string $id, array $catalog): ?array {
    // Busca en arbol_general → todas las categorías
    // Busca en arboles_raciales → todas las razas
    // Busca en pasivas_primarias → todas las razas
    // Busca en pasivas_secundarias → todas las razas
}

// Enriquece un perk con icono y color según su ID
function enrich_perk_in_php(array $p): array {
    // Según el prefijo del ID:
    // pp_ → primaria (fa-shield-alt, #10b981)
    // ps_ → secundaria (fa-crown, #f59e0b)
    // rh_ → humano racial (fa-user, #C62828)
    // rm_ → mink racial (fa-paw, #10b981)
    // g_ → general (fa-dna, #C62828)
    // etc.
}

// Renderiza una card de perk en HTML
function render_perk_card(array $p, string $type_class,
    string $icon_modifier, string $badge_label, string $badge_color): string {
    // gene-card con icono, nombre, descripción, coste, badge
}
```

### 12.3 Sistema de Dots (Visualización de PL)

```php
<?php if ($max_points <= 10): ?>
    <div class="linaje-slots-dots">
        <?php for ($i = 0; $i < $max_points; $i++): ?>
            <div class="linaje-slot-dot <?= ($i < $spent_points) ? 'filled' : '' ?>"></div>
        <?php endfor; ?>
    </div>
<?php endif; ?>
```

Para razas con muchos PL (Humano: 28), los dots se omiten y solo se muestra el contador numérico.

### 12.4 Legacy v1 Support

```php
<?php if ($has_perks_v2): ?>
    <!-- Sistema v2: pasivas, racial, general -->
<?php else: ?>
    <!-- Legacy v1: banner + old gene names -->
    <div class="pj-linaje-legacy-notice">
        <i class="fas fa-info-circle"></i>
        <div>
            <div class="pj-linaje-legacy-notice__title">Ficha en formato antiguo</div>
            <div class="pj-linaje-legacy-notice__text">El sistema de Linaje será actualizado en la próxima revisión.</div>
        </div>
    </div>
    <?php foreach ($char['linaje']['geneNames'] as $geneName): ?>
        <!-- Mostrar geneNames del sistema antiguo -->
    <?php endforeach; ?>
<?php endif; ?>
```

### 12.5 Cálculo de Puntos Máximos (en UI)

```php
$char_race = $char['race_name'] ?? '';
$max_points = 4;

if (strpos($char_race, 'Híbrido') === 0 || strpos($char_race, 'Hibrido') === 0) {
    // Híbrido: PL = raza_dominante - 4
    if (preg_match('/Híbrid[o|a]\s*\(([^\\/]+)\s*\\/\s*([^)]+)\)/i', $char_race, $matches)) {
        $race_dom = trim($matches[1]);
        $pts_dom = $linaje_catalog['puntos_linaje_por_raza'][$race_dom] ?? 20;
        $max_points = $pts_dom - 4;
    }
} else {
    // Raza pura
    $max_points = $linaje_catalog['puntos_linaje_por_raza'][$char_race] ?? 4;
}

$spent_points = 0;
foreach ($racial_display as $p) { $spent_points += ($p['cost'] ?? 1); }
foreach ($general_display as $p) { $spent_points += ($p['cost'] ?? 1); }

$sobrante = $max_points - $spent_points;
$bonus_pp = $sobrante * 2;
```

---

## 13. Staff Tools

### 13.1 Crear Nuevas Razas

Para añadir una nueva raza al sistema se necesitan tocar 4 archivos:

1. **`game/data/linaje_catalog.json`** — Añadir entrada en `races`:
```json
"NuevaRaza": { "puntos_iniciales": 4, "stat_bonuses": { "fue": 0, "res": 0, "agi": 0, "des": 0, "int": 0, "inst": 0, "esp": 0 } }
```

2. **`game/data/linaje_system.json`** — Añadir en:
   - `puntos_linaje_por_raza`: `"NuevaRaza": 20`
   - `pasivas_primarias`: Array de pasivas primarias
   - `pasivas_secundarias`: Array de pasivas secundarias
   - `arboles_raciales`: Objeto con `perks` array

3. **`game/public/crear_personaje.php`** — Añadir a `$razas` array (línea 149)

4. **`game/views/personaje/_tab_linaje.php`** — Los iconos se asignan automáticamente según prefijo del ID. Para una raza nueva, añadir su prefijo a `enrich_perk_in_php()`.

### 13.2 Añadir Cards Raciales

1. Crear la card en `game_cards` con `notes = "Solo disponible para [Raza]"`
2. Si es una carta innata (regalo racial), incluirla como pasiva primaria o secundaria con el campo adecuado
3. Si es técnica adquirible, el jugador la solicita mediante el sistema de solicitudes

### 13.3 Otorgar Puntos de Linaje Adicionales

El sistema actual NO tiene mecánica para otorgar PL después de la creación. Los PL son exclusivos de la creación del personaje.

**Para staff:** Si se quiere otorgar PL extra (como recompensa de evento), el staff puede:
1. Editar manualmente `data_json.linaje.maxPoints` en DB
2. Recalcular `sobrantePoints` y `bonusPP`
3. Ajustar `pp` y `pp_linaje` en consecuencia

**IMPORTANTE:** Esta operación debe hacerse con cuidado. El sistema de auto-reparación (`syncLinajeBonusPp`) puede sobrescribir cambios manuales si detecta inconsistencias.

### 13.4 Cambio de Raza

El cambio de raza (ej: mediante Puntos Destino) requiere:

1. Actualizar `race` y `race_name` en `game_personajes`
2. Recalcular linaje: el nuevo `data_json.linaje` se adapta a la nueva raza
3. Los bonos de stat cambian automáticamente (son runtime)
4. Las pasivas cambian según la nueva raza
5. Si la raza anterior tenía perks incompatibles, el staff debe decidir si se mantienen, reembolsan o reasignan

**Filosofía del cambio de raza:**
- `stats_json` NO cambia (rangos entrenados). Solo los bonos efectivos cambian.
- Los perks de linaje se pierden (la nueva raza tiene su propio árbol).
- Se recomienda que el cambio de raza sea un evento narrativo importante, no una decisión cosmética.

---

## 14. Filosofía de Diseño

### 14.1 ¿Por qué las razas tienen PROFUNDIDAD mecánica (no solo cosmética)?

**Counterargumento común en foros RPG:** "Si las razas tienen mecánicas distintas, todos eligen la más fuerte."

**Respuesta del sistema:**
- Las razas NO son "más fuertes" unas que otras. Son DIFERENTES. Un Gigante es imbatible en fuerza bruta pero incapaz de sigilo. Un Tontatta es escurridizo pero frágil.
- Los PL compensan: las razas con malos bonos (Humano) tienen muchos PL para personalizarse. Las razas con grandes bonos (Lunarian) tienen pocos PL.
- El juego no es competitivo. Es un foro de rol cooperativo. No importa si tu personaje es "menos óptimo" — importa la historia que cuentas.
- **Las penalizaciones crean historias.** Un Gigante que aprende sigilo es más interesante que un Mink que nació siendo sigiloso.

### 14.2 ¿Por qué las pasivas son runtime-calculadas (no persisten)?

- **Flexibilidad de cambio:** Si un personaje cambia de raza, sus pasivas cambian automáticamente sin migraciones.
- **Un solo origen de verdad:** El catálogo (`linaje_system.json`) es la fuente única. Si se actualiza una pasiva, todos los personajes de esa raza reciben el cambio automáticamente.
- **Sin desincronización:** No hay riesgo de que un personaje tenga una pasiva desactualizada porque su `data_json` no se migró.
- **Contrapartida:** Las pasivas no se pueden personalizar por personaje. Si un humano tuviera una pasiva extra por una razón narrativa, habría que hardcodearlo.

### 14.3 ¿Por qué el linaje tiene su propia moneda (PL)?

- **Los PL son una decisión de IDENTIDAD, no de progresión.** Se gastan UNA VEZ y definen quién eres. Los PP se gastan CONTINUAMENTE y definen tu crecimiento.
- **Tradeoff significativo:** Gastar PL en perks vs convertirlos a PP bonus es una decisión real con consecuencias a largo plazo.
- **Equilibrio racial:** Sin los PL, las razas con malos bonos (Humano) serían directamente inferiores. Los PL extra compensan dándoles más flexibilidad.

### 14.4 ¿Por qué bonos Y penalizaciones? (Balance through trade-offs)

- **Principio de asimetría balanceada:** Si todo fuera positivo, todas las razas serían igual de buenas en todo. Las penalizaciones crean nichos.
- **La suma neta ~0 (o cercana) no es el objetivo real.** El objetivo es que CADA RAZA tenga un arquetipo claro:
  - Gigante: tanque lento
  - Tontatta: DPS ágil y frágil
  - Mink: versátil perceptivo
  - Humano: comodín personalizable
- **Las penalizaciones se compensan con entrenamiento.** Un Tontatta con FUE entrenada 4 tiene FUE efectiva 2 (4-2=2), que es como un Humano sin entrenar. Con esfuerzo, cualquier penalización se supera.

### 14.5 Cómo interactúa el sistema de razas con otros sistemas

| Sistema | Interacción con Razas |
|---------|----------------------|
| **Stats** | Bonos raciales modifican stats efectivos (runtime) |
| **Cards** | Cards raciales solo accesibles por raza (vía `notes`) |
| **Progresión (PP)** | PL sobrantes → PP bonus; PP de linaje se gastan primero |
| **Disciplinas** | Algunas disciplinas son exclusivas de raza (Karate Gyojin, Canto Sirenico) |
| **Haki** | Ciertas razas tienen afinidad (Buccaner, Skypean) |
| **Akuma no Mi** | Sin restricción racial directa (cualquier raza puede tener fruta) |
| **Navegación** | Razas acuáticas (Gyojin, Sirena) tienen ventajas en navegación |
| **Misiones** | Algunas misiones pueden requerir o beneficiar a razas específicas |
| **Oficios** | Perks de linaje pueden reducir coste de oficios |

---

## 15. Consejos para Jugadores

### 15.1 Cómo Elegir una Raza para tu Build

**Si quieres ser un tanque (absorber daño):**
- Mejores opciones: **Gigante** (+2 FUE, +2 RES), **Lunarian** (+1 FUE, +2 RES, reducción 50% daño)
- Buenos: **Buccaner** (+1 FUE, +1 RES), **Oni** (+2 FUE, +1 RES)
- A evitar: **Tontatta** (−2 FUE, −1 RES), **Sirena** (−1 FUE)

**Si quieres ser un DPS ágil (esquivar y golpear):**
- Mejores opciones: **Mink** (+1 AGI, +1 DES, Electro), **Tontatta** (+2 AGI, +2 DES)
- Buenos: **Skypean** (+1 AGI), **Sirena** (+1 AGI)
- A evitar: **Gigante** (−1 AGI), **Lunarian** (−1 AGI en modo llama)

**Si quieres ser un usuario de Haki:**
- Mejores opciones: **Buccaner** (+2 ESP, +1 FUE, Haki natural), **Skypean** (+1 ESP, Mantra innato)
- Buenos: **Humano** (perk Potencial de Haki Elevado), **Lunarian** (+1 ESP)
- A evitar: **Gigante** (0 ESP), **Gyojin** (0 ESP)

**Si quieres ser un versátil (jack of all trades):**
- Mejores opciones: **Humano** (28 PL para personalizar), **Skypean** (bonos repartidos + 26 PL)
- Buenos: **Mink** (+3 bonos total), **Sirena** (+4 bonos total)

### 15.2 Qué Priorizar con los Puntos de Linaje

**Estrategia "Especialista" (gastar todo en perks):**
- Recomendado para: jugadores que quieren una identidad racial muy marcada.
- Ventaja: Perks potentes que definen tu estilo de juego.
- Desventaja: Pocos PP iniciales (progresión más lenta).
- Ejemplo: Mink que gasta 6 PL en `rm_electro_maestro` + `rm_electro_distancia` → maestro del Electro desde el día 1.

**Estrategia "Generalista" (gastar la mitad, convertir el resto):**
- Recomendado para: jugadores que quieren perks útiles pero también progresión rápida.
- Equilibrio: 2 perks raciales (2-4 PL) + 1 perk general (1-3 PL) = ~5 PL gastados.
- PP Bonus: ~23 PL sobrantes × 2 = 46 PP = casi suficiente para subir un stat de 1→2.

**Estrategia "Progresión" (convertir casi todo a PP):**
- Recomendado para: jugadores que quieren subir stats rápido.
- PP Bonus masivo: 24 PL sobrantes × 2 = 48 PP (Humano).
- Puedes subir 2 stats de 1→2 en la primera semana.
- Desventaja: tu personaje no tiene perks raciales definidos.

### 15.3 Sinergias con Disciplinas

| Raza | Disciplina que sinergiza | Por qué |
|------|-------------------------|---------|
| Mink | Cuerpo a Cuerpo | Electro + Cuerpo a Cuerpo = descargas eléctricas en golpes |
| Gyojin | Armas de Filo (Karate Gyojin) | Karate Gyojin usa INT para daño en agua |
| Gigante | Armas Contundentes | Armas colosales + FUE ×2 = daño masivo |
| Tontatta | Armas a Distancia | Tamaño diminuto + puntería = francotirador perfecto |
| Buccaner | Cualquier disciplina de Haki | Afinidad natural con el Haki |
| Lunarian | Cuerpo a Cuerpo | Combate aéreo + llama = DPS aéreo |

### 15.4 Sinergias con Oficios

| Raza | Oficio que sinergiza | Por qué |
|------|---------------------|---------|
| Humano | Cualquiera | Adaptabilidad + descuento 10% PP = oficios más baratos |
| Gyojin | Navegante | Conocimiento del mar + corrientes |
| Mink | Domador | Instinto animal + afinidad con bestias |
| Tontatta | Médico/Cocinero | Herbolaria élite + conocimiento de plantas |
| Skypean | Científico | Domina Dials (tecnología) + afinidad celestial |
| Gigante | Herrero | Fuerza colosal para forjar armas enormes |

### 15.5 Lo que NO te dice el sistema

- **Humano no es "la raza aburrida".** Es la raza con MÁS flexibilidad (28 PL). Puedes construir un humano que imite cualquier arquetipo con los perks adecuados.
- **Los híbridos son narrativamente interesantes pero mecánicamente más débiles.** Pierden pasivas secundarias y tienen menos PL. Si quieres poder racial puro, elige una raza pura.
- **Los bonos negativos no te condenan.** Un Tontatta con FUE -2 necesita FUE entrenada 3 para tener FUE efectiva 1. Con esfuerzo (PP), cualquier penalización se compensa.
- **Las pasivas secundarias solo para puros son MUY poderosas.** El Sulong Mink, la Transformación Demoníaca Oni, la Resiliencia Épica Gigante — habilidades que justifican no ser híbrido.

---

## 16. Consejos para Staff

### 16.1 Creando Nuevas Razas Balanceadas

**Checklist de balance:**

1. **Suma de bonos de stat:** Apunta a neto entre +1 y +4. Si es muy alto (+6+), añade penalizaciones equivalentes.
2. **PL inicial:** Inversamente proporcional a la potencia de los bonos. Más bonos = menos PL.
3. **Pasivas primarias:** Cada raza debe tener 2-4 pasivas que cubran: (a) una ventaja única, (b) una resistencia, (c) una capacidad de movimiento o sentido.
4. **Pasivas secundarias:** Poderosas pero condicionales. 3 por raza.
5. **Árbol racial:** 6-12 perks. Deben escalar en poder (los más caros, los más potentes). Algunos deben requerir otros (requisitos en cadena).
6. **Cards raciales:** 3-6 cartas sugeridas. Pueden ser innatas (regalo) o adquiribles.

**Proporción ideal de perks raciales:**
- 2-3 perks básicos (coste 1-2) — accesibles para todos
- 2-3 perks intermedios (coste 2-3) — requieren especialización
- 2-3 perks avanzados (coste 3-4) — requieren perks anteriores
- 1-2 perks élite (coste 4-6) — solo para puros, muy poderosos

### 16.2 Evaluando Solicitudes de Perks de Linaje

Cuando un jugador solicita un perk durante la creación, el staff debe verificar:

1. **¿Es legal para su raza?** `solo_puro`, `hibrido_accesible`, prerrequisitos (`requires`).
2. **¿Caben en los límites?** Máx 2 raciales, máx 2 generales.
3. **¿Tiene suficientes PL?** Coste total ≤ PL máximo.
4. **¿El perk tiene sentido narrativo?** Un humano tomando "Linaje Elemental: Fuego" necesita una historia que lo justifique.

### 16.3 Evaluando Cards Raciales

Cuando un jugador solicita una carta técnica con temática racial:

1. **¿La raza del personaje es compatible con el efecto?** Un Gyojin puede tener cartas de agua. Un Mink puede tener cartas de electricidad. Un Humano... necesita justificación.
2. **¿La carta replica una pasiva existente?** No debe duplicar efectos de pasivas gratuitas (eso sería pagar por algo que ya tienes).
3. **¿El poder es apropiado para el rango de la carta?** Una carta racial de rango B debe ser más poderosa que la pasiva racial base.

### 16.4 Manejando Solicitudes de Cambio de Raza

Protocolo sugerido:

1. **Verificar que el cambio tiene justificación narrativa.** Un personaje no cambia de raza porque sí. Debe haber un evento IC que lo justifique (ej: una transformación, un ritual, un artefacto).
2. **Preservar `stats_json` (rangos entrenados).** El esfuerzo del jugador no debe perderse.
3. **Resetear linaje.** La nueva raza tiene su propio árbol. Los perks anteriores no aplican. El jugador elige nuevos perks según los PL de la nueva raza.
4. **Reasignar o reembolsar PP.** Si la nueva raza tiene menos PL, los PP bonus se recalculan. Si el jugador perdería PP por el cambio, el staff puede compensar.
5. **Notificar en el hilo de cambios.** Registrar el cambio para transparencia.

### 16.5 Errores Comunes

- **"Esta raza tiene más bonos, es OP."** No necesariamente. Mira sus PL: Lunarian tiene +3 bonos pero solo 16 PL. Un Humano con 28 PL puede compensar con perks.
- **"Los híbridos son mejores porque tienen dos razas."** Pierden pasivas secundarias (poderosas) y tienen -4 PL. La flexibilidad narrativa tiene un coste mecánico.
- **"Los bonos negativos hacen la raza injugable."** FALSO. Un Tontatta con FUE -2 puede entrenar FUE. Con FUE entrenada 5 y bono -2, tiene FUE efectiva 3 (B). No es óptimo pero es viable.
- **"Se puede cambiar de raza sin coste."** NO. El sistema no tiene mecánica automática de cambio de raza. Requiere intervención del staff y justificación narrativa.

---

## 17. Catálogo Completo de Razas

### 17.1 Humano

| Propiedad | Valor |
|-----------|-------|
| Bonos | FUE 0, RES 0, AGI 0, DES 0, INT 0, INST 0, ESP 0 |
| PL | 28 (el más alto del sistema) |
| Esencia | Versatilidad absoluta. Sin ventajas naturales, pero con máxima personalización |

**Pasivas Primarias:**
- `pp_hum_01` **Adaptabilidad Fisiológica:** Sin penalizadores de entorno.
- `pp_hum_02` **Polivalencia de Aprendizaje:** −10% PP en disciplinas y oficios.
- `pp_hum_03` **Tenacidad Humana:** 1/combate, fija PV en 1 en vez de caer.

**Pasivas Secundarias (puro):**
- `ps_hum_01` **Potencial Sin Techo:** Sin caps raciales en disciplinas.
- `ps_hum_02` **Herencia Adaptativa del Linaje:** Nodo central en tablero de linaje.
- `ps_hum_03` **Voluntad Inquebrantable:** +3 resistencia mental.

**Árbol Racial:** 18 perks incluyendo subespecies (Piernas Largas, Brazos Largos, Cuello Largo, Gigantismo, Enanismo), tenacidad, liderazgo, potencial de Haki.

### 17.2 Mink

| Propiedad | Valor |
|-----------|-------|
| Bonos | AGI +1, DES +1, INST +1 |
| PL | 22 |
| Esencia | Velocidad, sentidos, Electro |

**Pasivas Primarias:**
- `pp_mink_01` **Guerrero de Cuna:** Disciplina Cuerpo a Cuerpo gratis.
- `pp_mink_02` **Electro:** Medidor (cap 5). Cartas con [Tag: Electro] usan INST.
- `pp_mink_03` **Sentidos Predadores:** +3 INST percepción. Visión nocturna, olfato.
- `pp_mink_04` **Pelaje Resistente:** −1 dado a daño de corte/perforación D.

**Pasivas Secundarias (puro):**
- `ps_mink_01` **Sulong:** Transformación lunar. Stats ×2. Coste 8% PV/turno.
- `ps_mink_02` **Curación Acelerada:** Curación ×2 fuera de combate.
- `ps_mink_03` **Resonancia del Pelaje:** Parálisis leve automática 1/combate.

### 17.3 Gyojin

| Propiedad | Valor |
|-----------|-------|
| Bonos | FUE +1, RES +1, AGI −1 |
| PL | 20 |
| Esencia | Dominio acuático, fuerza bruta |

**Pasivas Primarias:**
- `pp_gyojin_01` **Físico de Leyenda:** FUE ×1,8 para daño, carga, rotura.
- `pp_gyojin_02` **Anfibio Perfecto:** Sin penalizadores acuáticos.
- `pp_gyojin_03` **Acceso Artes Gyojin:** Disciplina exclusiva.
- `pp_gyojin_04` **Piel Escamada:** −2 daño físico base.

**Pasivas Secundarias (puro):**
- `ps_gyojin_01` **Dominio de las Corrientes:** Manipulación de agua incluso sin agua visible.
- `ps_gyojin_02` **Grito del Fondo:** Aturdimiento leve 1/encuentro.
- `ps_gyojin_03` **Ventaja Acuática Absoluta:** +2 stats en combate subacuático.

### 17.4 Gigante

| Propiedad | Valor |
|-----------|-------|
| Bonos | FUE +2, RES +2, AGI −1, DES −1 |
| PL | 16 |
| Esencia | Tanque colosal, fuerza bruta |

**Pasivas Primarias:**
- `pp_gigante_01` **Escala Colosal — Físico:** FUE ×2. Cap de FUE +30%.
- `pp_gigante_02` **Escala Colosal — Alcance:** Alcance 8m cuerpo a cuerpo. Área ×2.
- `pp_gigante_03` **Piel de Montaña:** −4 daño físico. Inmune a knockback bajo.
- `pp_gigante_04` **Terror Natural:** Tirada de Espíritu dif.12 o Terror Leve.

**Pasivas Secundarias (puro):**
- `ps_gigante_01` **Legado de Elbaf:** Disciplina exclusiva + corredor de linaje.
- `ps_gigante_02` **Resiliencia Épica:** 1/día, "No Cae": PV en 1.
- `ps_gigante_03` **Temblor de Tierra:** Derribo en radio 15m 1/combate.

### 17.5 Tontatta

| Propiedad | Valor |
|-----------|-------|
| Bonos | FUE −2, RES −1, AGI +2, DES +2, INT +1 |
| PL | 24 |
| Esencia | Miniatura, velocidad, fuerza desproporcionada |

**Pasivas Primarias:**
- `pp_tontatta_01` **Fuerza Muscular Desproporcionada:** FUE ×3 en acciones directas.
- `pp_tontatta_02` **Blanco Esquivo:** +4 AGI defensiva contra enemigos grandes.
- `pp_tontatta_03` **Excavador Nato:** Excava a velocidad = AGI.
- `pp_tontatta_04` **Sentidos Subterráneos:** Detecta seres vivos en 15m en tierra.

**Pasivas Secundarias (puro):**
- `ps_tontatta_01` **Comunión Vegetal:** Información de plantas. Inmovilización 1/combate.
- `ps_tontatta_02` **Inmunidad a Venenos Naturales:** Inmune a venenos naturales.
- `ps_tontatta_03` **Lazo de Sangre del Reino:** +2 AGI/FUE con otro Tontatta.

### 17.6 Buccaner

| Propiedad | Valor |
|-----------|-------|
| Bonos | FUE +1, RES +1, ESP +2 |
| PL | 22 |
| Esencia | Sangre de gigante, Haki natural, voluntad |

**Pasivas Primarias:**
- `pp_buccaner_01` **Sangre Primigenia:** +2 cap de Vitalidad. Herida grave al 25%.
- `pp_buccaner_02` **Voluntad de Hierro:** Inmune a control mental bajo.
- `pp_buccaner_03` **Fuerza Ancestral:** +3 a FUE base.

**Pasivas Secundarias (puro):**
- `ps_buccaner_01` **Herencia del Sol:** Aura +1 ESP a aliados en 5m.
- `ps_buccaner_02` **Más Allá del Límite:** Al <25% PV, dados +1 categoría 3 turnos.
- `ps_buccaner_03` **Maldición del Linaje:** Inmune a grilletes de Seastone.

### 17.7 Lunarian

| Propiedad | Valor |
|-----------|-------|
| Bonos | FUE +1, RES +2, AGI −1, ESP +1 |
| PL | 16 |
| Esencia | Llama eterna, vuelo, casi extinto, tanque mágico |

**Pasivas Primarias:**
- `pp_lunarian_01` **Llama Eterna — Encendida:** −50% daño todas fuentes. Inmune fuego. AGI −2.
- `pp_lunarian_02` **Llama Eterna — Apagada:** Acción libre. Pierde reducción. AGI +4. FUE +3 siguiente ataque.
- `pp_lunarian_03` **Alas Negras:** Vuelo natural. Ignora terreno dificultoso.
- `pp_lunarian_04` **Superviviente Absoluto:** Fijar PV en 1 sin límite de usos (con llama encendida).

**Pasivas Secundarias (puro):**
- `ps_lunarian_01` **Dominio de la Llama Corporal:** Proyectil de fuego 1/turno.
- `ps_lunarian_02` **Herencia de los Cielos:** Corredor exclusivo de linaje.
- `ps_lunarian_03` **Ignición Total:** Explosión de fuego radio 8m 1/combate.

### 17.8 Skypean

| Propiedad | Valor |
|-----------|-------|
| Bonos | AGI +1, INT +1, INST +1, ESP +1 |
| PL | 26 |
| Esencia | Habitante del cielo, Dials, Mantra |

**Pasivas Primarias:**
- `pp_skypean_01` **Alas de Isla:** Planeo prolongado.
- `pp_skypean_02` **Observación Innata:** −1 dificultad Haki de Observación.
- `pp_skypean_03` **Manejo de Dials:** −10% PP en cartas de Dials.

**Pasivas Secundarias (puro):**
- `ps_skypean_01` **Vuelo Sostenido:** Vuelo activo controlado ilimitado.
- `ps_skypean_02` **Resonancia con el Viento:** Anticipa proyectiles con INST.
- `ps_skypean_03` **Maestría de Dials:** +1 uso por Dial por escena.

### 17.9 Oni

| Propiedad | Valor |
|-----------|-------|
| Bonos | FUE +2, RES +1, DES −1, INT −1, INST +1 |
| PL | 18 |
| Esencia | Fuerza demoníaca, ira, cuernos, legado de Wano |

**Pasivas Primarias:**
- `pp_oni_01` **Fuerza Demoníaca:** +4 a FUE base.
- `pp_oni_02` **Cuernos del Demonio:** Carta Embestida del Oni (Rango D). Inmune aturdimiento bajo.
- `pp_oni_03` **Constitución Demoníaca:** −3 daño físico. Cap vitalidad +20%.
- `pp_oni_04` **Ira del Oni:** Medidor 0-5. Según ira: +FUE/AGI y daño.

**Pasivas Secundarias (puro):**
- `ps_oni_01` **Legado de Wano:** Disciplina de Esgrima de Wano.
- `ps_oni_02` **Transformación Demoníaca:** Con 5 ira: FUE ×1,5 por 3 turnos.
- `ps_oni_03` **Voluntad Indomable:** Con 5 ira: inmune a control mental.

### 17.10 Sirena

| Propiedad | Valor |
|-----------|-------|
| Bonos | FUE −1, AGI +1, DES +1, INT +1, ESP +2 |
| PL | 22 |
| Esencia | Velocidad acuática, canto, curación, limitación terrestre |

**Pasivas Primarias:**
- `pp_sirena_01` **Velocidad del Abismo:** AGI ×2 en agua.
- `pp_sirena_02` **Canto Sirenico:** Disciplina exclusiva. +3 persuasión fuera de combate.
- `pp_sirena_03` **Sangre Vitalizante:** +10% PV recuperación por turno. Puede curar aliados.
- `pp_sirena_04` **Respiración Dual:** Respira en agua y aire.

**Pasivas Secundarias (puro):**
- `ps_sirena_01` **Llamada de Poseidón:** Órdenes a criaturas marinas 1/tema.
- `ps_sirena_02` **Marejada Controlada:** Ola 20m×5m, derribo+empuje 1/combate.
- `ps_sirena_03` **Vínculo Oceánico:** Percibe emociones del mar.

---

*Fin del documento — Guía completa del Sistema de Razas v2.0*
*Generado desde: `Guias/sistemas/12-razas.md`*
*Referencia: `Guias/MAESTRO_SISTEMAS_RPG.md` — Sección 12*
*Archivos clave: `game/data/linaje_catalog.json`, `game/data/linaje_system.json`, `game/src/Shared/StatScale.php`, `game/src/Application/Services/LinajeValidator.php`*
