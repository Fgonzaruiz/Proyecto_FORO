# PROMPTS — SISTEMAS DE AMPLIACIÓN RPG

> Prompts listos para pasar a tu IA generadora.
> Cada prompt genera el documento `.md` de reglas narrativas de ese sistema.
> Estos sistemas son gestionados por el staff de forma manual/narrativa, NO automatizados por código.
>
> **Archivos de destino:** `Guias/sistemas/`

---

## PS-01 Sistema de Recompensas (Bounty)

```
CONTEXTO DEL FORO:
Eres diseñador de reglas para un foro de rol de piratas basado temáticamente en One Piece,
con mecánicas y lore propios. Los sistemas son gestionados por el staff de forma narrativa
y escrita, sin automatización de código.

DECISIONES YA TOMADAS POR EL ADMIN:
- La recompensa (bounty) existe como campo cosmético en la ficha del personaje.
- Ahora debe tener PESO MECÁNICO y NARRATIVO real.
- El sistema es narrativo: el staff actualiza el bounty manualmente tras evaluar acciones.
- Solo aplica a piratas y revolucionarios (los marines tienen rango, no bounty).

QUÉ DEBE CUBRIR EL DOCUMENTO:
1. QUÉ ES EL BOUNTY — Definición y propósito en el mundo.
2. ESCALA DE VALORES — Tabla de rangos de bounty con nombre de categoría, rango de berries,
   y qué nivel de amenaza representa (novato, capitán notable, peligro regional, amenaza global...).
3. CÓMO SUBE — Lista de acciones que generan bounty, con el incremento aproximado en berries:
   - Derrotar a un marine (escalonado por rango del marine)
   - Destruir una instalación del Gobierno
   - Escapar de una prisión del Gobierno
   - Derrotar a otro pirata notable
   - Revelar o robar secretos del Gobierno Mundial
   - Convertirse en líder de tripulación
   - Ejecutar una acción de impacto mundial
4. EFECTOS DE GAMEPLAY SEGÚN NIVEL DE BOUNTY — Tabla que muestre qué cambia en el mundo
   cuando el bounty sube de rango:
   - Cómo te tratan los NPCs civiles, piratas y marines
   - Restricciones de acceso a islas del Gobierno o ciudades con fuerte presencia Marina
   - Beneficios entre piratas (respeto, aliados potenciales)
   - Asignación de cazarrecompensas NPCs por parte del Gobierno
5. CAZARRECOMPENSAS Y PERSECUCIÓN — Qué tipo de amenaza NPC envía el Gobierno según el nivel:
   - Bounty bajo: patrullas ordinarias
   - Bounty medio: oficiales y tenientes
   - Bounty alto: capitanes y comodoros
   - Bounty muy alto: vicealmirantes, almirantes, y/o Shichibukai equivalentes del foro
6. CARTEL DE BÚSQUEDA — Formato narrativo del cartel in-world que el staff publica al subir
   el bounty (qué datos incluye, cómo se redacta, dónde se publica en el foro).
7. ELIMINACIÓN O REDUCCIÓN DEL BOUNTY — ¿Se puede reducir? ¿Cómo? (Amnistía del Gobierno,
   convertirse en marine, Shichibukai equivalente, etc.)
8. ROL DEL STAFF — Instrucciones claras de cómo y cuándo el staff actualiza el bounty.

FORMATO DE SALIDA:
Genera el documento completo en Markdown, listo para guardar como:
  Guias/sistemas/29-bounty.md

Usa tablas donde sea útil. Sé específico con los números. El tono es de reglamento oficial
del foro, claro y sin ambigüedades.
```

---

## PS-02 Sistema de Combate PvP — Resolución de Conflictos

```
CONTEXTO DEL FORO:
Eres diseñador de reglas para un foro de rol de piratas. El sistema de posts usa:
- 7 stats (fue, res, agi, des, int, inst, esp) con rangos 1-6
- Cards con activaciones (activa, pasiva, reactiva), coste PE, dados y efectos
- PA (Puntos de Aventura) declarados por post
- PV y PE por personaje
- Disciplinas (grados 1-5) y Estilos Canónicos

El combate es ESCRITO/NARRATIVO: los jugadores escriben sus posts de combate y el
sistema da herramientas para resolver ambigüedades. No hay automatización.

DECISIONES YA TOMADAS POR EL ADMIN:
- El combate PvP es narrativo en primera instancia: los jugadores narran y el staff arbitra.
- Las tiradas (dados) existen para resolver acciones disputadas, no para toda acción.
- Solo puede haber UN HILO de presente por personaje a la vez.

QUÉ DEBE CUBRIR EL DOCUMENTO:
1. FILOSOFÍA DEL COMBATE — Cómo se entiende el PvP en este foro (narrativa colaborativa
   con arbitraje de staff, no sistema de turnos estricto).
2. INICIO DE UN COMBATE — Cómo se declara oficialmente un combate PvP en un hilo:
   - Quién puede iniciar uno
   - Etiquetas o marcadores que debe tener el hilo
   - Aviso al staff (cómo y cuándo)
3. ORDEN DE POSTS (INICIATIVA) — Cómo se determina quién postea primero:
   - Fórmula basada en stats (agi + inst como sugerencia, o libre)
   - Qué pasa si hay empate
   - Tiempos límite para responder (días reales)
4. QUÉ SE PUEDE HACER EN UN POST DE COMBATE — Definición clara de:
   - Cuántas acciones se pueden declarar por post (vinculado a PA)
   - Cómo se declaran las cards que se usan
   - Cómo se escriben las acciones vs los efectos (lo que INTENTAS vs lo que LOGRAS)
   - Acciones ocultas (hidden_actions_json): qué son y cuándo se revelan
5. CUÁNDO SE TIRA DADO — Lista de situaciones que requieren tirada:
   - Acción vs acción de igual nivel
   - Superar una defensa
   - Usar una card activa bajo presión
   - El staff puede solicitar tirada en cualquier momento
6. CÓMO SE DETERMINA EL IMPACTO — Guía para el staff:
   - Diferencia de stats: si A supera en X rangos a B en el stat relevante...
   - Resultado de la tirada: rangos de éxito/fallo
   - Cartas con ventaja o desventaja contextual
7. CONDUCTAS PROHIBIDAS EN PvP — Lista explícita de lo que está prohibido:
   - Godmodding (controlar al otro PJ)
   - Powerplay (declarar que tu ataque impacta sin que el rival pueda reaccionar)
   - Metagaming (usar información OOC en IC)
   - Esquivar todo sin justificación narrativa
8. ROL DEL STAFF COMO ÁRBITRO — Cuándo interviene, cómo solicitar arbitraje, tiempos de respuesta.
9. CONSECUENCIAS DE NO RESPONDER — Si un jugador no postea en el tiempo límite, qué pasa.
10. COMBATES GRUPALES (MULTI-PJ) — Reglas especiales si hay más de 2 participantes.

FORMATO DE SALIDA:
Genera el documento completo en Markdown, listo para guardar como:
  Guias/sistemas/30-combate-pvp.md

Sé preciso y sin ambigüedades. Este documento es la referencia ante disputas.
```

---

## PS-03 Sistema de Derrota e Incapacitación

```
CONTEXTO DEL FORO:
Eres diseñador de reglas para un foro de rol de piratas. El sistema usa PV (Puntos de Vida)
que disminuyen en combate. Necesitamos definir qué ocurre cuando los PV llegan a 0 o el
jugador decide rendirse.

DECISIONES YA TOMADAS POR EL ADMIN:
- El sistema es narrativo: el staff arbitra y aplica las consecuencias.
- La muerte permanente de un PJ solo puede ocurrir con consentimiento del jugador.
- Solo puede haber UN HILO de presente por personaje.

QUÉ DEBE CUBRIR EL DOCUMENTO:
1. ESTADOS DE INCAPACITACIÓN — Definir claramente cada estado posible:
   - INCONSCIENTE: PV en 0, el PJ cae pero no muere. Estado por defecto.
   - DERROTADO CRÍTICO: PV en 0 con daño masivo en un solo hit. Más consecuencias.
   - RENDICIÓN: El jugador elige rendirse antes de llegar a 0.
   - RETIRADA: El PJ consigue huir del combate.
   - MUERTE: Solo con consentimiento expreso del jugador propietario.
2. QUÉ PUEDE HACER EL VENCEDOR — Lista de opciones narrativas:
   - Capturar al derrotado
   - Dejarle inconsciente y marcharse
   - Robar o confiscar cards/equipo (reglas de qué se puede tomar y qué no)
   - Entregar al Gobierno (marines o cazarrecompensas)
   - Matarle (solo con consentimiento del otro jugador)
3. QUÉ LE PASA AL DERROTADO — Consecuencias mecánicas concretas:
   - Cooldown de recuperación (días de foro que no puede participar en combate)
   - Recuperación de PV/PE (cuánto recupera solo vs con un Médico)
   - Pérdida de Berries (si aplica y en qué porcentaje)
   - Pérdida o embargo de cards (reglas claras sobre qué se puede perder)
   - Registro del hilo como "derrota" en la cronología del PJ
4. CAPTURA Y PRISIÓN — Si el derrotado es capturado:
   - Cómo funciona la prisión narrativamente (hilo de prisión, tiempo)
   - Cómo puede escapar o ser rescatado
   - Qué pasa con las cards mientras está preso
5. RECUPERACIÓN — Cómo se recupera un PJ derrotado:
   - Solo con descanso (tiempo real de cooldown)
   - Con ayuda de un Médico (reduce el cooldown)
   - Cicatrices narrativas permanentes (opcionales pero recomendadas para drama)
6. DERROTA EN MISIONES — Reglas específicas si la derrota ocurre en una misión:
   - ¿La misión falla automáticamente?
   - ¿Pueden continuar los compañeros?
   - ¿Hay penalización de PD o Berries?
7. DEATH NOTE — Proceso formal para cuando un jugador consiente la muerte de su PJ:
   - Cómo se solicita y se aprueba
   - Cómo se registra en el foro
   - Si puede crear un nuevo PJ y con qué condiciones
8. ROL DEL STAFF — Quién registra y aplica las consecuencias, en qué plazo.

FORMATO DE SALIDA:
Genera el documento completo en Markdown, listo para guardar como:
  Guias/sistemas/31-derrota-incapacitacion.md
```

---

## PS-04 Sistema de Crafteo

```
CONTEXTO DEL FORO:
Eres diseñador de reglas para un foro de rol de piratas. El foro tiene oficios que permiten
crear objetos: Herrero (armas/armaduras), Cocinero (consumibles/buffs), Carpintero (barcos),
Científico (gadgets/trampas/invenciones). El crafteo es narrativo y gestionado por el staff.

DECISIONES YA TOMADAS POR EL ADMIN:
- Las cards crafteadas se insertan en el sistema exactamente igual que las compradas en tienda.
- La diferencia es el PROCESO de obtención, no el objeto final.
- El sistema es narrativo: el jugador escribe el proceso de creación en posts, el staff lo valida.
- Los materiales son narrativos (no hay sistema de inventario de materias primas separado).

QUÉ DEBE CUBRIR EL DOCUMENTO:
1. QUÉ ES EL CRAFTEO — Definición: el proceso por el que un PJ con el oficio correcto
   crea una card en lugar de comprarla.
2. VENTAJAS DEL CRAFTEO vs COMPRA — Por qué alguien craftearía en vez de comprar:
   - Menor coste en Berries
   - Posibilidad de personalizar efectos o nombre
   - Rango potencialmente mayor al disponible en tienda
   - Acceso a objetos no disponibles en tienda
3. REQUISITOS GENERALES — Qué necesita cualquier crafteo:
   - Oficio correspondiente en el grado mínimo requerido (tabla por rango de card)
   - Hilo de crafteo con mínimo de posts narrando el proceso
   - Materiales narrativos descritos en el hilo (no necesitan ser comprados, basta narrarlos)
   - Solicitud al staff con el hilo como evidencia
4. OFICIOS Y LO QUE PUEDEN CREAR — Tabla detallada por oficio:
   - HERRERO: armas, armaduras, refuerzos de barco
   - COCINERO: consumibles (buffs de stats, recuperación de PV/PE, mejoras temporales)
   - CARPINTERO: barcos, reparaciones mayores, mejoras de barco
   - CIENTÍFICO: gadgets, trampas, invenciones únicas, prótesis
   Cada entrada debe incluir: rango máximo crafteable por grado de oficio, tiempo mínimo (posts)
5. RANGO DE LO CRAFTEABLE — Tabla que relaciona grado del oficio con rango máximo:
   - Grado 1: máximo rank C
   - Grado 2: máximo rank B
   - Grado 3: máximo rank A
   - Grado 4: máximo rank S
   - Grado 5: máximo rank SS (solo con aprobación especial del staff)
6. EL HILO DE CRAFTEO — Cómo debe ser el hilo donde el jugador narra la creación:
   - Mínimo de posts requerido según rango (D: 3, C: 4, B: 6, A: 8, S: 12)
   - Qué debe narrar el jugador (proceso, materiales obtenidos, dificultades)
   - Un solo jugador o puede haber colaboración (maestro + aprendiz del oficio)
7. COSTE EN BERRIES DEL CRAFTEO — Reducción respecto al precio de tienda según grado:
   - Grado 1: 70% del precio de tienda
   - Grado 2: 50%
   - Grado 3: 30%
   - Grado 4: 15%
   - Grado 5: coste simbólico (materiales narrativos únicamente)
8. CRAFTEO ESPECIAL — Objetos únicos que no existen en tienda:
   - El jugador propone los efectos + el staff los valida y balancea
   - La card resultante tiene el tag "crafteada" y el nombre del creador en notas
9. SOLICITUD Y APROBACIÓN — Proceso formal:
   - Dónde se solicita
   - Qué información incluye la solicitud
   - Tiempo de respuesta del staff
   - Qué pasa si la solicitud es rechazada (qué se puede cambiar)

FORMATO DE SALIDA:
Genera el documento completo en Markdown, listo para guardar como:
  Guias/sistemas/32-crafteo.md
```

---

## PS-05 Sistema de Rangos de Facción

```
CONTEXTO DEL FORO:
Eres diseñador de reglas para un foro de rol de piratas. Existen al menos tres facciones
principales: Marina (Gobierno Mundial), Piratas y Revolucionarios. Cada una tiene su propia
jerarquía interna. El sistema es narrativo y gestionado por el staff.

DECISIONES YA TOMADAS POR EL ADMIN:
- Los piratas tienen bounty como métrica de peligro; los marines tienen rango como métrica.
- Los rangos se suben por actividad, logros y aprobación del staff.
- La jerarquía debe tener EFECTOS MECÁNICOS concretos, no ser solo cosmética.
- El admin definirá los nombres de las facciones equivalentes a Shichibukai, Yonko, etc.
  Usa nombres genéricos en el documento que el admin puede renombrar.

QUÉ DEBE CUBRIR EL DOCUMENTO:
1. PROPÓSITO DE LOS RANGOS — Por qué existen y qué representan mecánicamente.

2. FACCIÓN: MARINA / GOBIERNO
   - Escala de rangos completa (adaptar de One Piece: soldado, sargento, teniente, capitán,
     comodoro, vicealmirante, almirante, almirante en jefe / Fleet Admiral).
   - Por cada rango: nombre, requisitos para alcanzarlo (stat mínimo, rango de PJ, acciones),
     beneficios que otorga (acceso a islas, recursos, autoridad sobre NPCs).
   - CUERPOS ESPECIALES: unidades de élite equivalentes (SWORD equivalente, Cipher Pol equivalente).
   - Cómo se asciende: proceso formal de solicitud, quién aprueba.
   - Cómo se desciende: deshonra, traición, derrota.

3. FACCIÓN: PIRATAS
   - Rango dentro de la TRIPULACIÓN: roles internos (capitán, primer oficial, médico, navegante...).
     Estos son roles narrativos y se asignan por acuerdo entre jugadores.
   - Rango en el MUNDO: escala de reconocimiento global basada en bounty + logros:
     Pirata desconocido → Pirata notable → Supernova / Rookies peligrosos → Capitán reconocido
     → Pirata fuerte del Grand Line → [Nombre del rango equivalente a Yonko]
   - POSICIÓN ESPECIAL: equivalente a Shichibukai (pirata que pacta con el Gobierno):
     Beneficios, obligaciones, cómo se accede, cómo se pierde.
   - Cómo afecta el rango pirata a la interacción con otras facciones.

4. FACCIÓN: REVOLUCIONARIOS
   - Escala de rangos interna (comandante, jefe de ejército, líder supremo, etc.).
   - Cómo operan en relación con piratas y la Marina (no son simples piratas).
   - Acceso a recursos y misiones exclusivas de la facción.
   - Cómo se asciende y qué acciones son valoradas por la facción.

5. CAMBIO DE FACCIÓN — ¿Se puede cambiar? ¿Cuándo y cómo?
   - Un pirata que se vuelve marine (condiciones, consecuencias con la facción antigua).
   - Un marine que deserta (consecuencias, bounty asignado).
   - Agentes dobles (si el foro los permite).

6. TABLA RESUMEN — Tabla comparativa de las tres facciones con sus rangos alineados
   por nivel de poder equivalente.

FORMATO DE SALIDA:
Genera el documento completo en Markdown, listo para guardar como:
  Guias/sistemas/33-rangos-faccion.md

Usa tablas. Incluye los nombres genéricos de rangos pero añade una nota de "el admin
puede renombrar esto a [X]" donde sea relevante.
```

---

## PS-06 Sistema de Control de Territorios

```
CONTEXTO DEL FORO:
Eres diseñador de reglas para un foro de rol de piratas. Las tripulaciones pueden controlar
islas del foro, lo cual les da beneficios y objetivos a largo plazo. El sistema es narrativo
y gestionado por el staff.

DECISIONES YA TOMADAS POR EL ADMIN:
- Una tripulación controla una isla cuando ha establecido dominancia narrativa en ella.
- El control se pierde si otra tripulación / la Marina lo disputa y gana.
- No hay automatización: el staff registra qué tripulación controla qué isla.

QUÉ DEBE CUBRIR EL DOCUMENTO:
1. QUÉ SIGNIFICA CONTROLAR UNA ISLA — Definición narrativa y mecánica:
   - No es "ser dueño": es tener influencia dominante reconocida in-world.
   - Cómo lo saben los otros jugadores (aviso del staff, descripción de la isla actualizada).

2. CÓMO SE ESTABLECE EL CONTROL — Proceso formal:
   - Requisitos mínimos para reclamar una isla (tripulación con capitán reconocido, bounty/rango
     mínimo del capitán, número mínimo de miembros activos).
   - Acciones necesarias para reclamar: misión de establecimiento de control (hilo dedicado),
     derrota del líder NPC actual o de la tripulación que la controla.
   - Solicitud al staff + registro oficial.

3. BENEFICIOS DEL CONTROL — Tabla de beneficios:
   - Bonus de Berries pasivos (renta de la isla, escalonada por nivel de peligro de la isla)
   - Acceso a recursos exclusivos de esa isla
   - Spawn de misiones locales de mayor recompensa
   - Puntos Destino adicionales por actividad en la isla controlada
   - Autoridad narrativa sobre los NPCs locales
   - Base de operaciones: bonus de recuperación de PV/PE en la isla

4. TIPOS DE ISLA Y VALOR DE CONTROL — No todas las islas valen igual:
   - Tabla que relaciona `base_danger` y `sea_zone` con el valor del control
     (las islas del New World valen más pero son más difíciles de mantener)

5. DISPUTA Y PÉRDIDA DEL CONTROL — Cómo se pierde el control:
   - Otra tripulación declara disputa (proceso formal)
   - La Marina decide "limpiar" la isla (evento de staff)
   - La tripulación controlante pierde actividad mínima (inactividad durante X semanas)
   - Derrota en un hilo de disputa declarado oficialmente

6. DISPUTAS PvP DE TERRITORIO — Reglas específicas para hilos de disputa:
   - Cómo se declara una disputa
   - Qué tipo de hilo se abre (combate, negociación, asedio)
   - Cómo se determina el ganador
   - Reglas para que la tripulación defensora tenga ventaja narrativa (es su isla)

7. CONTROL DE LA MARINA — La Marina también puede controlar islas:
   - Islas con presencia fuerte de la Marina son más difíciles de controlar por piratas.
   - Si los piratas toman una isla de la Marina, consecuencias especiales (bounty extra,
     respuesta de la Marina más severa).

8. MAPA DE CONTROL — Cómo el staff registra y publica el estado de control de islas
   (hilo de noticias del mundo, actualización de la descripción del foro-isla, etc.)

FORMATO DE SALIDA:
Genera el documento completo en Markdown, listo para guardar como:
  Guias/sistemas/34-control-territorios.md
```

---

## PS-07 Sistema de Guerras y Eventos Mundiales

```
CONTEXTO DEL FORO:
Eres diseñador de reglas para un foro de rol de piratas. Los Eventos Mundiales son arcos
narrativos grandes donde todos los jugadores pueden participar. Son gestionados íntegramente
por el staff. Son el equivalente a los grandes arcos del manga (Marineford, Dressrosa, etc.)

DECISIONES YA TOMADAS POR EL ADMIN:
- Los eventos mundiales son iniciados y controlados exclusivamente por el staff.
- Pueden ser guerras, expediciones, catástrofes, o revoluciones políticas.
- Los jugadores pueden participar o no; no es obligatorio.
- Los resultados tienen consecuencias permanentes para el mundo del foro.

QUÉ DEBE CUBRIR EL DOCUMENTO:
1. QUÉ ES UN EVENTO MUNDIAL — Definición y cómo se diferencia de una misión o un arco personal.

2. TIPOS DE EVENTO MUNDIAL — Con ejemplos de cada uno:
   - GUERRA: conflicto armado entre dos o más facciones a escala de varios foros.
   - ASEDIO: ataque concentrado a una isla o instalación.
   - CATÁSTROFE: evento natural o provocado (erupción, tsunami, arma prohibida...).
   - CRISIS POLÍTICA: cambio de poder, revelación de secretos, traición pública.
   - EXPEDICIÓN: exploración de territorio nuevo/peligroso.
   - TORNEO: competición oficial organizada por una facción o institución del mundo.

3. ESTRUCTURA DE UN EVENTO MUNDIAL — Fases:
   - FASE 1 — PRÓLOGO: anuncios in-world (periódico, rumores), preparación narrativa.
   - FASE 2 — DECLARACIÓN OFICIAL: el staff abre el evento, publica las reglas específicas.
   - FASE 3 — DESARROLLO: hilos paralelos activos. Reglas de participación.
   - FASE 4 — RESOLUCIÓN: el staff determina el resultado según la participación y las acciones.
   - FASE 5 — CONSECUENCIAS: cambios permanentes al mundo (islas destruidas, líderes caídos,
     nuevas rutas abiertas, lore nuevo escrito).

4. PARTICIPACIÓN DE JUGADORES — Cómo un jugador se une a un evento:
   - Tipos de participación: combate, espionaje, apoyo logístico, rol de civil afectado.
   - Requisitos mínimos para participar en combate (rango de PJ, facción).
   - Hilos asignados por facción o por bando.
   - Cómo se registra la participación (el staff lleva control).

5. RECOMPENSAS DE EVENTO — Tabla de recompensas por participación:
   - Participante: recompensa base (PD + Berries).
   - Participante destacado (staff lo selecciona): recompensa mejorada.
   - Héroe del evento (el más impactante): recompensa especial única (card, rango, lore personal).
   - Bando ganador vs perdedor: diferencia de recompensas.

6. BAJAS Y CONSECUENCIAS PERMANENTES — Qué puede pasar durante un evento:
   - NPCs mayores pueden morir permanentemente.
   - Islas pueden ser destruidas, cambiadas de control o modificadas.
   - PJs pueden tener consecuencias narrativas permanentes (cicatrices, rangos, bounty masivo).

7. AFTER EVENT — Cómo el staff publica el resultado:
   - Formato del "After Event" (resumen narrativo del resultado).
   - Actualización del lore del mundo.
   - Periódico in-world con la cobertura del evento.

8. GUERRAS ENTRE TRIPULACIONES — Cuando el conflicto es entre jugadores, no de staff:
   - Cómo se declara una guerra oficial entre tripulaciones.
   - El staff actúa de árbitro, no de narrador.
   - Condiciones de victoria y rendición.
   - Consecuencias de la guerra (territorio, berries, lore).

FORMATO DE SALIDA:
Genera el documento completo en Markdown, listo para guardar como:
  Guias/sistemas/35-guerras-eventos-mundiales.md
```

---

## PS-08 Sistema de Tiempo y Calendario In-World

```
CONTEXTO DEL FORO:
Eres diseñador de reglas para un foro de rol de piratas. El foro tiene una línea de tiempo
interna que debe ser coherente entre todos los jugadores y el lore.

DECISIONES YA TOMADAS POR EL ADMIN:
- Un personaje solo puede tener UN HILO de presente activo a la vez.
- El resto de hilos activos de ese personaje son pasado (flashbacks, memorias, etc.).
- El avance del tiempo in-world lo decide el lore y lo anuncia el staff.
- Los hilos pasados pueden transcurrir en cualquier punto de la línea de tiempo anterior al presente.
- No hay sistema de código para esto: es un contrato social entre jugadores con reglas escritas.

QUÉ DEBE CUBRIR EL DOCUMENTO:
1. EL AÑO ACTUAL DEL FORO — Cómo se define y dónde se publica (el admin lo rellena).
   Formato sugerido para el campo en blanco que el admin completará.

2. EL HILO DE PRESENTE — Reglas claras:
   - Definición: el hilo donde el PJ existe "ahora mismo" en la línea de tiempo.
   - Solo puede haber UNO por personaje activo.
   - Cómo se etiqueta (prefijo o tag en el título del hilo).
   - Qué pasa si un jugador quiere abrir un nuevo presente (debe cerrar o pausar el anterior).

3. HILOS DE PASADO — Reglas:
   - Flashbacks: hilos de un momento anterior al presente del PJ.
   - Memorias: hilos cortos de escenas específicas del pasado.
   - Cómo se etiquetan.
   - Pueden estar abiertos simultáneamente con el presente.
   - No pueden "alterar" eventos que ya ocurrieron en el presente.
   - Los objetos/skills ganados en flashbacks no se aplican hasta el presente lógicamente.

4. HILOS DE SUEÑO / VISIÓN — Tipo especial:
   - Escenas oníricas o visiones proféticas.
   - Sin consecuencias mecánicas directas (salvo que el staff lo apruebe).

5. SINCRONÍA ENTRE PERSONAJES — Cuándo dos PJs pueden compartir hilo:
   - Solo si están en el mismo punto de la línea de tiempo (mismo "presente" aproximado).
   - Cómo se gestiona si uno avanza su presente más rápido que el otro.

6. AVANCE DEL TIEMPO DEL FORO — Cómo el staff avanza el año in-world:
   - Con qué periodicidad (o en qué circunstancias narrativas).
   - Cómo se anuncia.
   - Qué pasa con los hilos de presente abiertos al avanzar el año (se considera que se cierran
     en el año anterior si no se especifica).

7. TABLA DE ETIQUETAS RECOMENDADAS — Para los títulos de hilo:
   [PRESENTE] | [FLASHBACK — Año X] | [SUEÑO] | [MEMORIA] | [CERRADO]

FORMATO DE SALIDA:
Genera el documento completo en Markdown, listo para guardar como:
  Guias/sistemas/36-tiempo-calendario.md
```



---

---

## ÍNDICE DE ARCHIVOS A GENERAR

| Prompt | Archivo destino |
|---|---|
| PS-01 Bounty | `Guias/sistemas/29-bounty.md` |
| PS-02 Combate PvP | `Guias/sistemas/30-combate-pvp.md` |
| PS-03 Derrota e Incapacitación | `Guias/sistemas/31-derrota-incapacitacion.md` |
| PS-04 Crafteo | `Guias/sistemas/32-crafteo.md` |
| PS-05 Rangos de Facción | `Guias/sistemas/33-rangos-faccion.md` |
| PS-06 Control de Territorios | `Guias/sistemas/34-control-territorios.md` |
| PS-07 Guerras y Eventos Mundiales | `Guias/sistemas/35-guerras-eventos-mundiales.md` |
| PS-08 Tiempo y Calendario | `Guias/sistemas/36-tiempo-calendario.md` |

---

*Prompts v1.0 — Junio 2026*
