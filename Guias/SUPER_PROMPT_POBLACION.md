# SUPER PROMPT — POBLACIÓN COMPLETA DEL FORO RPG

> **Uso:** Pasa este prompt completo a cualquier IA generadora (ChatGPT, Claude, Gemini, etc.) junto con los archivos `Guias/MAESTRO_SISTEMAS_RPG.md` y `Guias/PROMPTS_SISTEMAS_AMPLIACION.md` como contexto.
>
> La IA recibirá las reglas del sistema, los esquemas de cada tabla y los prompts individuales para generar TODO el contenido que necesita el foro.

---

## INSTRUCCIONES GENERALES PARA LA IA

Eres un equipo de diseñadores de contenido para un foro de rol de piratas con ambientación One Piece, sistema de cards, stats (fue/res/agi/des/int/inst/esp rangos 1-6), Haki, Akuma no Mi, oficios, disciplinas, y lore propio.

Tu tarea es generar TODO el contenido de población del foro siguiendo los prompts P-01 a P-10 que aparecen en `MAESTRO_SISTEMAS_RPG.md` y los PS-01 a PS-08 de `PROMPTS_SISTEMAS_AMPLIACION.md`.

### REGLAS GENERALES (APLICAN A TODO)
1. **Nombres originales:** NO copies nombres exactos del manga/anime. Crea nombres propios con sabor a One Piece pero originales.
2. **Coherencia interna:** Todos los elementos generados deben ser coherentes entre sí y con el lore del foro.
3. **Formato JSON:** Donde el prompt lo indique, devuelve JSON válido y completo, listo para insertar en base de datos.
4. **Output:** Sin explicaciones fuera del formato solicitado. La respuesta debe ser el JSON o markdown directamente.
5. **Staff-review:** El staff revisará antes de insertar. Incluye notas donde sea necesario advertir sobre balance.

### DATOS DEL FORO (RELLENAR POR EL ADMIN)
```
NOMBRE DEL MUNDO: [Kairan]
AÑO ACTUAL: [801]
TONO: [Conspirativo y misterioso con épica trágica]
FACCIONES PRINCIPALES: [Marina/Gobierno Mundial, Piratas, Ejército Revolucionario, Cazadores]
RESTRICCIONES: [Ninguna]
SEMILLA DE HISTORIA: [El mundo vivió una Era de los Cuatro Altares borrada por el Gobierno. La palabra Ōkotoba, el navío Raikōmaru y las familias ancestrales (Solmaren, Draven, Varek) guardan secretos que están despertando.]
```

---

## CONTENIDO A GENERAR (ORDEN RECOMENDADO)

Sigue estrictamente el orden. Cada bloque usa su prompt correspondiente del MAESTRO.

### 1. LORE COMPLETO → P-06
**Archivo:** `MAESTRO_SISTEMAS_RPG.md` — sección P-06
**Qué genera:** Eras históricas, lore basal (enciclopedia), eventos históricos y periódicos in-world.
**Cantidad:** 3-4 eras, mínimo 3 lore basal por era, mínimo 4 eventos por era, 3 periódicos del año actual.
**Formato:** JSON único con arrays `eras`, `lore_basal`, `eventos`, `periodicos`. Después: "SEMILLAS DE TRAMA" (5 ideas).

### 2. RAZAS JUGABLES → P-08
**Archivo:** `MAESTRO_SISTEMAS_RPG.md` — sección P-08
**Qué genera:** Fichas completas de razas con stats, pasivas primarias/secundarias, puntos de linaje, cards raciales.
**Cantidad:** 4-6 razas (Humano, Gyojin, Mink, Skypiea/Shandia, Lunaria, etc. — versiones originales).
**Formato:** JSON completo por raza. Después: "GUÍA DE ROL" (3 consejos por raza).

### 3. EQUIPO BÁSICO (TIENDA) → P-01
**Archivo:** `MAESTRO_SISTEMAS_RPG.md` — sección P-01
**Qué genera:** Cards de equipo básico rango D y C para la tienda del foro.
**Cantidad:** 5 armas, 3 armaduras, 3 consumibles, 3 herramientas/útiles.
**Formato:** Array JSON de cards con todos los campos.

### 4. ESTILOS CANÓNICOS → P-02
**Archivo:** `MAESTRO_SISTEMAS_RPG.md` — sección P-02
**Qué genera:** Escuelas de combate con sus técnicas asociadas.
**Cantidad:** 3-4 estilos canónicos. Mínimo 3 técnicas por estilo (1 C, 1 B, 1 A). Opcional: 1 S.
**Formato:** JSON del estilo + array JSON de técnicas.

### 5. ISLAS-FOROS → P-03
**Archivo:** `MAESTRO_SISTEMAS_RPG.md` — sección P-03
**Qué genera:** Fichas completas de islas del mundo (cada foro del tablón es una isla).
**Cantidad:** 5-8 islas distribuidas por los 4 Blues, Grand Line y New World.
**Formato:** JSON completo por isla + "GANCHOS DE TRAMA" (3 ideas por isla).

### 6. NPCs MAYORES → P-05
**Archivo:** `MAESTRO_SISTEMAS_RPG.md` — sección P-05 + `Guias/sistemas/17-npcs-mayores.md`
**Qué genera:** Perfiles completos de NPCs importantes del mundo.
**Cantidad:** 4-6 NPCs mayores (líderes de facciones, figuras históricas, antagonistas principales).
**Formato:** Estructura completa con identificacion, perfil_fisico, psicologia, motivaciones, perfil_estrategico, cronologia, relaciones, stats.

### 7. MISIONES → P-04
**Archivo:** `MAESTRO_SISTEMAS_RPG.md` — sección P-04
**Qué genera:** Misiones de diversos rangos para el tablón.
**Cantidad:** 3 misiones rango D, 3 rango C, 2 rango B, 1 rango A.
**Formato:** Array JSON de misiones con antagonista y complicación.

### 8. RUTAS DE NAVEGACIÓN → P-09
**Archivo:** `MAESTRO_SISTEMAS_RPG.md` — sección P-09
**Qué genera:** Conexiones entre islas con distancias y peligros.
**Cantidad:** Conexiones entre todas las islas generadas en el paso 5.
**Formato:** JSON por ruta + descripción narrativa + mapa conceptual ASCII.

### 9. PREMIOS PD (Puntos Destino) → P-07
**Archivo:** `MAESTRO_SISTEMAS_RPG.md` — sección P-07
**Qué genera:** Catálogo de recompensas canjeables con Puntos Destino.
**Cantidad:** 4 cosméticos, 3 extras personaje, 3 boosters, 3 cards especiales, 2 contenido narrativo.
**Formato:** JSON completo del catálogo.

### 10. CARDS ESPECIALES → P-10
**Archivo:** `MAESTRO_SISTEMAS_RPG.md` — sección P-10
**Qué genera:** Cards de Haki, Akuma no Mi, Barcos y NPCs menores.
**Cantidad:** 4 técnicas de Haki (1 por tipo + 1 avanzada), 2 Akuma no Mi base + 2 técnicas activas cada una, 2 barcos, 3 NPCs menores.
**Formato:** Array JSON de cards con todos los campos.

---

## SISTEMAS DE AMPLIACIÓN (PROMPTS ADICIONALES)

Usa `PROMPTS_SISTEMAS_AMPLIACION.md` para generar documentos markdown de sistemas narrativos:

| Prompt | Sistema a generar |
|--------|------------------|
| PS-01 | Sistema de Recompensas (Bounty) → `29-bounty.md` |
| PS-02 | Combate PvP → `30-combate-pvp.md` |
| PS-03 | Derrota e Incapacitación → `31-derrota-incapacitacion.md` |
| PS-04 | Crafteo → `32-crafteo.md` |
| PS-05 | Rangos de Facción → `33-rangos-faccion.md` |
| PS-06 | Control de Territorios → `34-control-territorios.md` |
| PS-07 | Guerras y Eventos Mundiales → `35-guerras-eventos-mundiales.md` |
| PS-08 | Tiempo y Calendario In-World → `36-tiempo-calendario.md` |

---

## CHECKLIST POST-GENERACIÓN

Para cada elemento generado, verifica antes de darlo por bueno:
- [ ] Coherencia con el lore existente (nombres, fechas, facciones)
- [ ] Stats y rangos balanceados dentro del sistema (1-6 PJs, 1-9 NPCs)
- [ ] Nombres originales (no calcos del manga)
- [ ] JSON válido (sin comas colgantes, sin caracteres inválidos)
- [ ] Efectos ejecutables sin ambigüedad por un árbitro
- [ ] Conexiones entre elementos (una isla generada debe aparecer en rutas y misiones)

---

*Super Prompt v1.0 — Generado desde `MAESTRO_SISTEMAS_RPG.md` (ZONA DE POBLACIÓN) + `PROMPTS_SISTEMAS_AMPLIACION.md`*
