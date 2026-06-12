# Guion de Auditoría de Lore — Kairan

> Instrucciones para que una IA genere el HTML `analisis-lore-kairan.html` a partir de
> los fuentes de lore y la auditoría previa (si existe).

---

## 1. Input requerido

Leer **siempre** estos archivos:

| Archivo | Ruta |
|---------|------|
| Lore maestro | `back/forum/game/lore.json` |
| Catálogo de tipos | `back/forum/game/src/Config/lore_types.json` |
| TypeMap service | `back/forum/game/src/Application/Services/LoreService.php` |
| Auditoría previa | `docs/analisis-lore-kairan.html` (si existe — extraer changelog y data histórica) |

---

## 2. Proceso paso a paso

### 2.1 Validación técnica
- Verificar que cada `type` en `lore.json.eventos[]` existe en `lore_types.json.event_types[]`
- Verificar que cada `subtype` en `lore.json.lore_basal[]` existe en `lore_types.json.lore_subtypes[]`
- Si falla: añadir el tipo faltante a `lore_types.json` y registrarlo en el changelog
- Verificar que no haya campos `icon` vacíos
- Verificar consistencia aritmética (end_year − start_year = duración numérica coherente con descripción textual)

### 2.2 Línea de tiempo
- Extraer eras con sus años
- Extraer todos los eventos con start_year, end_year, tipo
- Calcular silencios narrativos (períodos > 20 años sin eventos dentro de una era)
- Ordenar eventos cronológicamente

### 2.3 Mapeo de conexiones
- Escanear `details` de cada LB entry buscando `data-lore-id='N'`
- Registrar cada referencia como arista origen→destino
- Identificar el hub central (nodo con más aristas)
- Identificar nodos aislados (sin refs entrantes ni salientes)

### 2.4 Detección de huérfanos
- **LB sin evento asociado**: cruzar cada LB contra eventos de su era; si no hay coincidencia temática → huérfano
- **Evento sin LB asociado**: cruzar cada evento contra LB de su era; si no hay LB que lo cubra → evento huérfano
- Clasificar prioridad: ALTA (gap narrativo), MEDIA (mencionado pero no desarrollado), BAJA (misterio intencional)

### 2.5 Misterios
- Listar todos los hooks narrativos no resueltos
- Marcar estado: Abierto / Semi-abierto (parcialmente respondido) / Resuelto
- Vincular cada misterio a su LB/evento de origen

### 2.6 Hilos narrativos
- Identificar los 3 temas centrales (Memoria vs Olvido, Libertad vs Seguridad, Poder Narrativo)
- Identificar los 3 motivos recurrentes (Ciclos, Ironía Trágica, Subterráneo)
- Mapear qué LB/eventos tocan cada tema

### 2.7 Familias y linajes
- Extraer todas las familias mencionadas en LB/eventos
- Para cada familia: era origen, rol Era I, rol Era II/800, lealtad, miembros clave
- Mapear relaciones entre familias (alianzas, traiciones, complementos)
- Visualizar en tarjetas con codificación de color por lealtad

### 2.8 Personajes
- Extraer todos los personajes nombrados (excluir dioses)
- Clasificar: histórico (muerto), activo año 800 (NPC actual), indeterminado
- Marcar todos como NPC — ninguno es personaje jugable

### 2.9 Regla INALTERABLE: cero personajes del canon original
- Escanear todo `lore.json` en busca de personajes INDIVIDUALES nombrados del canon (Dragon, Nico Robin, Luffy, Shanks, etc.)
- Si aparece alguno → reemplazar por equivalente original
- SÍ se permiten términos del mundo, islas, razas, Frutas, conceptos (Grand Line, Marineford, Celestial Dragons, etc.)

### 2.10 Periódicos
- Verificar que existan 3 perspectivas: pro-Gobierno, revolucionario, neutral/independiente
- Si falta alguna perspectiva, registrarlo como observación

### 2.11 Prompt final para otra IA
- Generar sección al final del HTML con:
  - Opiniones sobre el estado actual del lore (basadas en los datos)
  - Preguntas clave no resueltas
  - Propuestas de acción priorizadas
  - Cosas rotas que necesitan respuesta
- Formato: bloque copiable que pueda pegarse a otra IA

---

## 3. Estructura del HTML de salida

```
<div class="container">
  <h1>Auditoría Definitiva de Lore — Kairan</h1>
  <div class="meta">...</div>

  <!-- 1. Resumen Ejecutivo -->
  <!-- stats cards con conteos totales -->

  <!-- 2. Validación Técnica -->
  <!-- barras de progreso + cards de errores/changelog -->

  <!-- 3. Distribución por Era -->
  <!-- tarjetas de era + línea de tiempo tabla -->
  <!-- silencios narrativos -->

  <!-- 4. Mapa de Conexiones — Red de Referencias -->
  <!-- grid de 21+ tarjetas, cada una con refs y eventos -->

  <!-- 5. Nodos Huérfanos -->
  <!-- tabla LB huérfanos + eventos huérfanos -->

  <!-- 6. Matriz de Conexiones -->
  <!-- tabla origen→destino con tipo de ref -->

  <!-- 7. Periódicos -->
  <!-- grid + verificación perspectivas -->

  <!-- 8. Misterios -->
  <!-- tabla con estado y conexiones -->

  <!-- 9. Hilos Narrativos -->
  <!-- temas + motivos -->

  <!-- 10. Familias y Linajes -->
  <!-- sección super visual con tarjetas color-coded -->

  <!-- 11. Personajes Mencionados -->
  <!-- tabla con rol, origen, tipo -->

  <!-- 12. Armas Ancestrales -->
  <!-- grid de 3 -->

  <!-- 13. Observaciones y Recomendaciones -->
  <!-- prioridad roja/amarilla/verde -->

  <!-- 14. Glosario -->

  <!-- 15. Changelog -->

  <!-- 16. PROMPT PARA OTRA IA -->
  <!-- bloque copiable al final -->
</div>
```

---

## 4. Reglas de estilo

- Fondo oscuro (`--bg: #0b0b12`), texto claro
- Tarjetas con bordes sutiles
- Codificación de color por prioridad/tipo:
  - Rojo = error, alta prioridad, exterminio
  - Naranja = media prioridad, traición
  - Verde = éxito, fundación
  - Azul = descubrimiento, facción
  - Púrpura = artefacto, política
  - Teal = organización secreta
  - Oro = eras, periódicos pro-gobierno
  - Rosa = exterminio
- Sin dependencias externas (sin CDN, sin JS frameworks)
- Responsivo (media query a 600px)

---

## 5. Changelog

Preservar el changelog de auditorías anteriores. Cada regeneración añade filas nuevas:

```html
<tr><td>v{N}</td><td>{archivo:línea}</td><td>{descripción del cambio}</td></tr>
```

Si se detectan nuevos errores, corregirlos en los fuentes ANTES de generar el HTML.

---

## 6. Prompt final

El prompt final debe ser un bloque `<pre>` o `<div>` con estilo diferenciado que contenga:

```
=== PROMPT PARA IA GENERATIVA ===

Contexto: [resumen del estado del lore]
Opiniones: [lo que funciona / no funciona]
Preguntas clave: [lista de preguntas que el lore deja abiertas]
Propuestas: [qué cambiar/añadir con prioridad]
Cosas rotas: [errores, inconsistencias, huecos]
```

Debe ser auto-contenido: cualquiera puede copiarlo y pegarlo a otra IA para obtener análisis o ejecución.
