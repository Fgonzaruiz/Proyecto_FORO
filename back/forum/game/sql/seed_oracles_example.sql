-- Seed de ejemplo: Oráculos temáticos de One Piece
-- Ejecutar con: php -f seed_oracles_example.php
-- O importar directamente en MySQL.

-- Nota: Ajusta `created_by` a un uid real de tu foro (admin).

SET @admin_uid := 1;

-- 1. yes_no: El Mar lo Decide (d20)
INSERT IGNORE INTO mybb_game_oracles
  (name, description, oracle_type, subtype, category, tags_json, results_json, variations_json, auto_invoke_json, dice_type, is_system, created_by)
VALUES
  ('El Mar lo Decide', 'Un sí/no que los marinos usan cuando el viento no sopla claro.', 'yes_no', 'navegacion', '',
   '["navegacion","basico"]',
   '[{"range":"1-10","result":"Sí","description":"El mar te concede el paso.","auto_invoke":null},{"range":"11-17","result":"No","description":"Las corrientes se oponen.","auto_invoke":null},{"range":"18-20","result":"Sí, pero...","description":"Concedido, pero con un costo inesperado.","auto_invoke":null}]',
   '{}','[]','d20',0,@admin_uid);

-- 2. action: Acciones de la Tripulación (d20)
INSERT IGNORE INTO mybb_game_oracles
  (name, description, oracle_type, subtype, category, tags_json, results_json, variations_json, auto_invoke_json, dice_type, is_system, created_by)
VALUES
  ('Acciones de la Tripulación', 'Determina qué hace un miembro de la tripulación en un momento dado.', 'action', 'tripulacion', '',
   '["tripulacion","pnj"]',
   '[{"range":"1","result":"Observa el horizonte","description":"Vigila atentamente el mar.","auto_invoke":null},{"range":"2","result":"Repara aparejos","description":"Trabaja en las cuerdas y velas.","auto_invoke":null},{"range":"3","result":"Cocina","description":"Prepara algo de comer.","auto_invoke":null},{"range":"4","result":"Entrena combate","description":"Practica con su arma o puños.","auto_invoke":null},{"range":"5","result":"Duerme","description":"Toma una siesta.","auto_invoke":null},{"range":"6","result":"Lee un libro","description":"Estudia un mapa o pergamino.","auto_invoke":null},{"range":"7","result":"Canta","description":"Entona una canción marinera.","auto_invoke":null},{"range":"8","result":"Pesca","description":"Lanza una caña al mar.","auto_invoke":null},{"range":"9","result":"Limpia cubierta","description":"Friega la cubierta del barco.","auto_invoke":null},{"range":"10","result":"Discute","description":"Tiene una discusión acalorada.","auto_invoke":null},{"range":"11","result":"Bebe","description":"Toma un trago de ron.","auto_invoke":null},{"range":"12","result":"Escribe en diario","description":"Anota algo en su bitácora.","auto_invoke":null},{"range":"13","result":"Cuenta historias","description":"Narra una leyenda del mar.","auto_invoke":null},{"range":"14","result":"Juega","description":"Juego de cartas o dados.","auto_invoke":null},{"range":"15","result":"Medita","description":"Se sienta en silencio a reflexionar.","auto_invoke":null},{"range":"16","result":"Espía","description":"Escucha conversaciones ajenas.","auto_invoke":null},{"range":"17","result":"Nada","description":"Se da un chapuzón.","auto_invoke":null},{"range":"18","result":"Inspecciona el barco","description":"Revisa daños en el casco.","auto_invoke":null},{"range":"19","result":"Discute con el capitán","description":"Cuestiona una orden.","auto_invoke":null},{"range":"20","result":"¡Ataca!","description":"Algo llamó su atención y ataca.","auto_invoke":null}]',
   '{}','[]','d20',0,@admin_uid);

-- 3. theme: Tema de Aventura (d100)
INSERT IGNORE INTO mybb_game_oracles
  (name, description, oracle_type, subtype, category, tags_json, results_json, variations_json, auto_invoke_json, dice_type, is_system, created_by)
VALUES
  ('Tema de Aventura', 'Define el tema central de la aventura o arco narrativo.', 'theme', 'narrativa', '',
   '["narrativa","trama"]',
   '[{"range":"1-5","result":"Venganza","description":"Alguien busca ajustar cuentas del pasado.","auto_invoke":null},{"range":"6-10","result":"Protección","description":"Deben proteger a alguien o algo.","auto_invoke":null},{"range":"11-15","result":"Exploración","description":"Un territorio desconocido los llama.","auto_invoke":null},{"range":"16-20","result":"Supervivencia","description":"Luchan contra elementos hostiles.","auto_invoke":null},{"range":"21-25","result":"Misterio","description":"Algo extraño está ocurriendo.","auto_invoke":null},{"range":"26-30","result":"Competencia","description":"Una carrera o torneo.","auto_invoke":null},{"range":"31-35","result":"Traición","description":"Alguien en quien confiaban los traiciona.","auto_invoke":null},{"range":"36-40","result":"Redención","description":"Buscan limpiar su honor.","auto_invoke":null},{"range":"41-45","result":"Conquista","description":"Ambición de poder o territorio.","auto_invoke":null},{"range":"46-50","result":"Huida","description":"Escapan de algo o alguien.","auto_invoke":null},{"range":"51-55","result":"Rescate","description":"Salvar a alguien cautivo.","auto_invoke":null},{"range":"56-60","result":"Construcción","description":"Edificar o restaurar algo.","auto_invoke":null},{"range":"61-65","result":"Alianza","description":"Formar o romper una alianza.","auto_invoke":null},{"range":"66-70","result":"Ritual","description":"Una ceremonia ancestral.","auto_invoke":null},{"range":"71-75","result":"Tormenta","description":"Una tormenta se aproxima.","auto_invoke":null},{"range":"76-80","result":"Descubrimiento","description":"Revelación de un secreto.","auto_invoke":null},{"range":"81-85","result":"Guerra","description":"Conflicto a gran escala.","auto_invoke":null},{"range":"86-90","result":"Enfermedad","description":"Una plaga o maldición azota.","auto_invoke":null},{"range":"91-95","result":"Tesoro","description":"Un tesoro legendario aparece.","auto_invoke":null},{"range":"96-100","result":"Destino","description":"El futuro está escrito.","auto_invoke":null}]',
   '{}','[]','d100',0,@admin_uid);

-- 4. action_theme: Encuentro en el Mar (d100 + d100)
INSERT IGNORE INTO mybb_game_oracles
  (name, description, oracle_type, subtype, category, tags_json, results_json, variations_json, auto_invoke_json, dice_type, is_system, created_by)
VALUES
  ('Encuentro en el Mar', 'Combina una acción con un tema para generar encuentros marítimos únicos.', 'action_theme', 'encuentro', '',
   '["encuentro","navegacion"]',
   '[{"range":"1-100","result":"(Ver acción) + (Ver tema)","description":"Tira Acción y Tema por separado y combínalos.","auto_invoke":null}]',
   '{}','[]','d100',0,@admin_uid);

-- 5. place_descriptor: Descriptor de Isla (d100)
INSERT IGNORE INTO mybb_game_oracles
  (name, description, oracle_type, subtype, category, tags_json, results_json, variations_json, auto_invoke_json, dice_type, is_system, created_by)
VALUES
  ('Descriptor de Isla', 'Describe el aspecto o ambiente general de una isla.', 'place_descriptor', 'exploracion', '',
   '["exploracion","lugares"]',
   '[{"range":"1-5","result":"Frondosa","description":"Cubierta de vegetación espesa.","auto_invoke":null},{"range":"6-10","result":"Árida","description":"Tierra seca y desértica.","auto_invoke":null},{"range":"11-15","result":"Helada","description":"Cubierta de hielo y nieve.","auto_invoke":null},{"range":"16-20","result":"Vulcánica","description":"Suelo negro y volcanes humeantes.","auto_invoke":null},{"range":"21-25","result":"Flotante","description":"Isla en el cielo o sobre el agua.","auto_invoke":null},{"range":"26-30","result":"Sumergida","description":"Parcialmente bajo el agua.","auto_invoke":null},{"range":"31-35","result":"Mecánica","description":"Engranajes y metal por doquier.","auto_invoke":null},{"range":"36-40","result":"Encantada","description":"Brillo místico y flora luminiscente.","auto_invoke":null},{"range":"41-45","result":"Pantánica","description":"Ciénagas y manglares.","auto_invoke":null},{"range":"46-50","result":"Montañosa","description":"Picos escarpados y acantilados.","auto_invoke":null},{"range":"51-55","result":"Laberíntica","description":"Cavernas y túneles intrincados.","auto_invoke":null},{"range":"56-60","result":"Dorada","description":"Arena dorada y sol radiante.","auto_invoke":null},{"range":"61-65","result":"Tormentosa","description":"Cielos grises y relámpagos constantes.","auto_invoke":null},{"range":"66-70","result":"Coralina","description":"Arrecifes de coral y aguas cristalinas.","auto_invoke":null},{"range":"71-75","result":"Olvidada","description":"Ruinas de una civilización perdida.","auto_invoke":null},{"range":"76-80","result":"Festiva","description":"Luces, música y celebraciones.","auto_invoke":null},{"range":"81-85","result":"Sombría","description":"Niebla densa y árboles retorcidos.","auto_invoke":null},{"range":"86-90","result":"Celestial","description":"Maravillas astronómicas visibles.","auto_invoke":null},{"range":"91-95","result":"Infernal","description":"Ríos de lava y aire sulfúrico.","auto_invoke":null},{"range":"96-100","result":"Prístina","description":"Naturaleza virgen e inexplorada.","auto_invoke":null}]',
   '{}','[]','d100',0,@admin_uid);

-- 6. place_focus: Foco de Exploración (d100)
INSERT IGNORE INTO mybb_game_oracles
  (name, description, oracle_type, subtype, category, tags_json, results_json, variations_json, auto_invoke_json, dice_type, is_system, created_by)
VALUES
  ('Foco de Exploración', 'Determina el punto de interés principal al llegar a un lugar nuevo.', 'place_focus', 'exploracion', '',
   '["exploracion","lugares"]',
   '[{"range":"1-5","result":"Una torre antigua","description":"Se alza solitaria en la distancia.","auto_invoke":null},{"range":"6-10","result":"Un mercado bullicioso","description":"Gente y mercancías de todo tipo.","auto_invoke":null},{"range":"11-15","result":"Un puerto devastado","description":"Barcos destruidos y muelle en ruinas.","auto_invoke":null},{"range":"16-20","result":"Una cueva oculta","description":"Entrada disimulada entre rocas.","auto_invoke":null},{"range":"21-25","result":"Un palacio majestuoso","description":"Residencia del gobernante local.","auto_invoke":null},{"range":"26-30","result":"Un bosque prohibido","description":"Árboles retorcidos y silencio.","auto_invoke":null},{"range":"31-35","result":"Un santuario","description":"Lugar de culto o meditación.","auto_invoke":null},{"range":"36-40","result":"Un acantilado","description":"Vista imponente del mar.","auto_invoke":null},{"range":"41-45","result":"Un cementerio de barcos","description":"Restos de naufragios apilados.","auto_invoke":null},{"range":"46-50","result":"Una fuente termal","description":"Aguas humeantes y burbujeantes.","auto_invoke":null},{"range":"51-55","result":"Un jardín colgante","description":"Vegetación que cae en cascada.","auto_invoke":null},{"range":"56-60","result":"Una mina abandonada","description":"Entrada oscura a la montaña.","auto_invoke":null},{"range":"61-65","result":"Un faro","description":"Luz giratoria en la costa.","auto_invoke":null},{"range":"66-70","result":"Una arena de combate","description":"Lugar de peleas y apuestas.","auto_invoke":null},{"range":"71-75","result":"Un laboratorio","description":"Equipo científico extraño.","auto_invoke":null},{"range":"76-80","result":"Una biblioteca","description":"Estantes repletos de conocimiento.","auto_invoke":null},{"range":"81-85","result":"Una taberna","description":"Risas, música y olor a ron.","auto_invoke":null},{"range":"86-90","result":"Una estatua gigante","description":"Monolito de una figura legendaria.","auto_invoke":null},{"range":"91-95","result":"Un campo de batalla","description":"Huellas de un conflicto reciente.","auto_invoke":null},{"range":"96-100","result":"Una puerta sellada","description":"Imposible de abrir sin la llave correcta.","auto_invoke":null}]',
   '{}','[]','d100',0,@admin_uid);

-- 7. character_role: Rol de PNJ (d20)
INSERT IGNORE INTO mybb_game_oracles
  (name, description, oracle_type, subtype, category, tags_json, results_json, variations_json, auto_invoke_json, dice_type, is_system, created_by)
VALUES
  ('Rol de PNJ', 'Determina la ocupación o rol de un personaje no jugador.', 'character_role', 'pnj', '',
   '["pnj","personajes"]',
   '[{"range":"1","result":"Marino","description":"Soldado de la Marina.","auto_invoke":null},{"range":"2","result":"Pirata","description":"Miembro de una tripulación pirata.","auto_invoke":null},{"range":"3","result":"Cazarrecompensas","description":"Caza fugitivos por dinero.","auto_invoke":null},{"range":"4","result":"Mercader","description":"Comerciante ambulante.","auto_invoke":null},{"range":"5","result":"Carpintero naval","description":"Repara barcos.","auto_invoke":null},{"range":"6","result":"Cocinero","description":"Alimenta a la tripulación.","auto_invoke":null},{"range":"7","result":"Médico","description":"Cuida de los enfermos.","auto_invoke":null},{"range":"8","result":"Navegante","description":"Traza rutas marítimas.","auto_invoke":null},{"range":"9","result":"Pescador","description":"Vive del mar.","auto_invoke":null},{"range":"10","result":"Artista","description":"Músico, pintor o actor.","auto_invoke":null},{"range":"11","result":"Granjero","description":"Trabaja la tierra.","auto_invoke":null},{"range":"12","result":"Herrero","description":"Forja armas y herramientas.","auto_invoke":null},{"range":"13","result":"Guardián","description":"Protege un lugar o persona.","auto_invoke":null},{"range":"14","result":"Erudito","description":"Estudia historia o ciencias.","auto_invoke":null},{"range":"15","result":"Mendigo","description":"Vive de la caridad.","auto_invoke":null},{"range":"16","result":"Ladrón","description":"Sobrevive robando.","auto_invoke":null},{"range":"17","result":"Noble","description":"Miembro de la aristocracia.","auto_invoke":null},{"range":"18","result":"Místico","description":"Practica artes espirituales.","auto_invoke":null},{"range":"19","result":"Revolucionario","description":"Lucha contra el gobierno.","auto_invoke":null},{"range":"20","result":"Gorosei / Gobierno","description":"Agente del Gobierno Mundial.","auto_invoke":null}]',
   '{}','[]','d20',0,@admin_uid);

-- 8. character_trait: Rasgo de PNJ (d100)
INSERT IGNORE INTO mybb_game_oracles
  (name, description, oracle_type, subtype, category, tags_json, results_json, variations_json, auto_invoke_json, dice_type, is_system, created_by)
VALUES
  ('Rasgo de PNJ', 'Define un rasgo de personalidad o apariencia de un PNJ.', 'character_trait', 'pnj', '',
   '["pnj","personajes"]',
   '[{"range":"1-5","result":"Audaz","description":"No le teme a nada.","auto_invoke":null},{"range":"6-10","result":"Desconfiado","description":"No confía en extraños.","auto_invoke":null},{"range":"11-15","result":"Carismático","description":"Encanta a todos.","auto_invoke":null},{"range":"16-20","result":"Torpe","description":"Todo se le cae de las manos.","auto_invoke":null},{"range":"21-25","result":"Sabio","description":"Habla con experiencia.","auto_invoke":null},{"range":"26-30","result":"Cicatrizado","description":"Cubierto de heridas de batalla.","auto_invoke":null},{"range":"31-35","result":"Silencioso","description":"Habla poco y escucha mucho.","auto_invoke":null},{"range":"36-40","result":"Glotón","description":"Siempre está comiendo.","auto_invoke":null},{"range":"41-45","result":"Noble","description":"Actúa con honor y dignidad.","auto_invoke":null},{"range":"46-50","result":"Travieso","description":"Ama las bromas pesadas.","auto_invoke":null},{"range":"51-55","result":"Melancólico","description":"Nostalgia y tristeza constante.","auto_invoke":null},{"range":"56-60","result":"Fanfarrón","description":"Exagera sus hazañas.","auto_invoke":null},{"range":"61-65","result":"Leal","description":"Nunca traiciona.","auto_invoke":null},{"range":"66-70","result":"Ambicioso","description":"Busca poder y riquezas.","auto_invoke":null},{"range":"71-75","result":"Tímido","description":"Se sonroja con facilidad.","auto_invoke":null},{"range":"76-80","result":"Excéntrico","description":"Costumbres y gustos extraños.","auto_invoke":null},{"range":"81-85","result":"Violento","description":"Resuelve todo a golpes.","auto_invoke":null},{"range":"86-90","result":"Misterioso","description":"Nadie sabe su pasado.","auto_invoke":null},{"range":"91-95","result":"Protector","description":"Cuida de los débiles.","auto_invoke":null},{"range":"96-100","result":"Profético","description":"Sus palabras se cumplen.","auto_invoke":null}]',
   '{}','[]','d100',0,@admin_uid);

-- 9. character_goal: Meta de PNJ (d100)
INSERT IGNORE INTO mybb_game_oracles
  (name, description, oracle_type, subtype, category, tags_json, results_json, variations_json, auto_invoke_json, dice_type, is_system, created_by)
VALUES
  ('Meta de PNJ', 'Determina el objetivo o deseo de un PNJ.', 'character_goal', 'pnj', '',
   '["pnj","personajes"]',
   '[{"range":"1-5","result":"Encontrar un tesoro","description":"Busca riquezas legendarias.","auto_invoke":null},{"range":"6-10","result":"Vengar a alguien","description":"Una deuda de sangre.","auto_invoke":null},{"range":"11-15","result":"Proteger a su familia","description":"Pondría su vida por ellos.","auto_invoke":null},{"range":"16-20","result":"Obtener poder","description":"Anhela una posición.","auto_invoke":null},{"range":"21-25","result":"Escapar del pasado","description":"Quiere empezar de cero.","auto_invoke":null},{"range":"26-30","result":"Construir un imperio","description":"Ambiciona dominar.","auto_invoke":null},{"range":"31-35","result":"Curar una enfermedad","description":"Busca una cura imposible.","auto_invoke":null},{"range":"36-40","result":"Completar un mapa","description":"Necesita piezas perdidas.","auto_invoke":null},{"range":"41-45","result":"Demostrar su valía","description":"Algo que probar.","auto_invoke":null},{"range":"46-50","result":"Reunir algo","description":"Colecciona objetos raros.","auto_invoke":null},{"range":"51-55","result":"Encontrar el All Blue","description":"El sueño de todo cocinero.","auto_invoke":null},{"range":"56-60","result":"Navegar el mundo","description":"Verlo todo.","auto_invoke":null},{"range":"61-65","result":"Derrocar un régimen","description":"Tiranía que derribar.","auto_invoke":null},{"range":"66-70","result":"Revelar una verdad","description":"Un secreto que sacar a luz.","auto_invoke":null},{"range":"71-75","result":"Forjar una alianza","description":"Unir fuerzas.","auto_invoke":null},{"range":"76-80","result":"Recuperar un recuerdo","description":"Memoria perdida.","auto_invoke":null},{"range":"81-85","result":"Convertirse en leyenda","description":"Ser inmortalizado.","auto_invoke":null},{"range":"86-90","result":"Pagar una deuda","description":"Saldar cuentas.","auto_invoke":null},{"range":"91-95","result":"Encontrar el One Piece","description":"El sueño de todo pirata.","auto_invoke":null},{"range":"96-100","result":"Protegerse a sí mismo","description":"Solo quiere sobrevivir.","auto_invoke":null}]',
   '{}','[]','d100',0,@admin_uid);

-- 10. pay_the_price: Paga el Precio (d100)
INSERT IGNORE INTO mybb_game_oracles
  (name, description, oracle_type, subtype, category, tags_json, results_json, variations_json, auto_invoke_json, dice_type, is_system, created_by)
VALUES
  ('Paga el Precio (One Piece)', 'Sufre las consecuencias de una acción arriesgada o fallo crítico.', 'pay_the_price', 'nucleo', '',
   '["nucleo","movidas"]',
   '[{"range":"1-5","result":"Pérdida de recursos","description":"Pierdes berries, comida o equipo.","auto_invoke":null},{"range":"6-10","result":"Daño físico","description":"Recibes un golpe directo.","auto_invoke":null},{"range":"11-15","result":"Llamas la atención","description":"Alguien peligroso te ha visto.","auto_invoke":null},{"range":"16-20","result":"Tu barco sufre","description":"Daños en el casco o velas.","auto_invoke":null},{"range":"21-25","result":"Alguien resulta herido","description":"Un aliado cercano cae.","auto_invoke":null},{"range":"26-30","result":"Pierdes el rumbo","description":"Te pierdes o atrasas tu viaje.","auto_invoke":null},{"range":"31-35","result":"Un aliado te traiciona","description":"Alguien en quien confiabas.","auto_invoke":null},{"range":"36-40","result":"Tormenta repentina","description":"El clima se vuelve hostil.","auto_invoke":null},{"range":"41-45","result":"Te quedas sin aliento","description":"Agotamiento extremo.","auto_invoke":null},{"range":"46-50","result":"Revelas tu posición","description":"Tu escondite queda expuesto.","auto_invoke":null},{"range":"51-55","result":"Objeto valioso destruido","description":"Un objeto importante se rompe.","auto_invoke":null},{"range":"56-60","result":"Enemigos aparecen","description":"Marines o piratas hostiles se acercan.","auto_invoke":null},{"range":"61-65","result":"Maldición o enfermedad","description":"Algo infecta tu cuerpo.","auto_invoke":null},{"range":"66-70","result":"Pérdida de reputación","description":"Tu nombre queda manchado.","auto_invoke":null},{"range":"71-75","result":"Separación del grupo","description":"Te separas de tu tripulación.","auto_invoke":null},{"range":"76-80","result":"Captura","description":"Caes prisionero.","auto_invoke":null},{"range":"81-85","result":"Fruta del Diablo","description":"Efecto secundario inesperado de una DF.","auto_invoke":null},{"range":"86-90","result":"Deuda o juramento","description":"Te ves forzado a prometer algo.","auto_invoke":null},{"range":"91-95","result":"Pérdida de memoria","description":"Olvidas algo crucial.","auto_invoke":null},{"range":"96-100","result":"El Gobernate se entera","description":"El Gobierno Mundial te pone en su lista.","auto_invoke":null}]',
   '{}','[]','d100',0,@admin_uid);

-- 11. custom: Clima en la Grand Line (d100, con variaciones por isla)
INSERT IGNORE INTO mybb_game_oracles
  (name, description, oracle_type, subtype, category, tags_json, results_json, variations_json, auto_invoke_json, dice_type, is_system, created_by)
VALUES
  ('Clima en la Grand Line', 'Condiciones meteorológicas impredecibles típicas de la Grand Line. Varía según la isla.', 'custom', 'clima', '',
   '["clima","navegacion","naturaleza"]',
   '[{"range":"1-10","result":"Tormenta eléctrica","description":"Rayos y truenos sacuden el cielo."},{"range":"11-20","result":"Calma chicha","description":"El viento desaparece por completo."},{"range":"21-30","result":"Lluvia torrencial","description":"El agua cae como cascada."},{"range":"31-40","result":"Niebla espesa","description":"Visibilidad reducida a cero."},{"range":"41-50","result":"Sol abrasador","description":"El calor es insoportable."},{"range":"51-60","result":"Vientos huracanados","description":"Ráfagas que zarandean el barco."},{"range":"61-70","result":"Granizo","description":"Bloques de hielo caen del cielo."},{"range":"71-80","result":"Arcoíris doble","description":"Un augurio de buena suerte."},{"range":"81-90","result":"Maremoto","description":"Olas gigantes se aproximan."},{"range":"91-100","result":"Calima de la Grand Line","description":"Polvo misterioso que desorienta."}]',
   '{
     "Arabasta": [
       {"range":"1-15","result":"Tormenta de arena","description":"El desierto se levanta contra ti."},
       {"range":"16-30","result":"Sol implacable","description":"El termómetro explota."},
       {"range":"31-45","result":"Noche estrellada","description":"El cielo despejado muestra constelaciones."},
       {"range":"46-60","result":"Oasis","description":"Un espejismo que es real."},
       {"range":"61-75","result":"Viento seco","description":"Arena y polvo en el aire."},
       {"range":"76-90","result":"Lluvia bendita","description":"La lluvia tan esperada llega."},
       {"range":"91-100","result":"Mirage","description":"El calor distorsiona la realidad."}
     ],
     "Drum": [
       {"range":"1-20","result":"Ventisca","description":"Nieve y viento cegador."},
       {"range":"21-40","result":"Nevada","description":"Los copos caen sin parar."},
       {"range":"41-55","result":"Hielo negro","description":"El suelo es una trampa mortal."},
       {"range":"56-70","result":"Avalancha","description":"La montaña ruge."},
       {"range":"71-85","result":"Cielo despejado","description":"Frío extremo pero sol brillante."},
       {"range":"86-95","result":"Noche polar","description":"Oscuridad que dura días."},
       {"range":"96-100","result":"Aurora boreal","description":"Luces de colores bailan en el cielo."}
     ],
     "Skypiea": [
       {"range":"1-15","result":"Cielo diáfano","description":"Visibilidad perfecta."},
       {"range":"16-30","result":"Nubes de algodón","description":"El mar de nubes es espeso."},
       {"range":"31-45","result":"Tormenta eléctrica celestial","description":"Rayos desde las nubes superiores."},
       {"range":"46-60","result":"Viento ascendedente","description":"Corriente que eleva."},
       {"range":"61-75","result":"Niebla de nubes","description":"Todo se vuelve blanco."},
       {"range":"76-90","result":"Lluvia de ángeles","description":"Agua purísima cae."},
       {"range":"91-100","result":"Vórtex","description":"Un torbellino en el cielo."}
     ],
     "Water 7": [
       {"range":"1-15","result":"Marea alta","description":"El agua sube peligrosamente."},
       {"range":"16-30","result":"Bruma matutina","description":"Niebla ligera al amanecer."},
       {"range":"31-45","result":"Acqua Laguna","description":"La gran ola de Water 7 se acerca."},
       {"range":"46-60","result":"Brisa marina","description":"Viento suave del mar."},
       {"range":"61-75","result":"Lluvia fina","description":"Llovizna que cala los huesos."},
       {"range":"76-90","result":"Cielo despejado","description":"Día perfecto para navegar."},
       {"range":"91-100","result":"Niebla tóxica","description":"Vapor de los canales."}
     ]
   }',
   '[]','d100',0,@admin_uid);

-- 12. custom: ¿Qué hay en el Horizonte? (d20)
INSERT IGNORE INTO mybb_game_oracles
  (name, description, oracle_type, subtype, category, tags_json, results_json, variations_json, auto_invoke_json, dice_type, is_system, created_by)
VALUES
  ('¿Qué hay en el Horizonte?', 'Algo aparece en la línea del mar. ¿Qué será?', 'custom', 'avistamiento', '',
   '["avistamiento","navegacion","encuentro"]',
   '[{"range":"1","result":"Un barco mercante","description":"Bandera amiga. Comercio pacífico.","auto_invoke":null},{"range":"2","result":"Una isla desconocida","description":"No aparece en ningún mapa.","auto_invoke":{"oracle_id":null,"label":"Descriptor de Isla"}},{"range":"3","result":"Restos de un naufragio","description":"Maderos y barriles flotando."},{"range":"4","result":"Un Rey del Mar","description":"¡Una criatura colosal emerge!"},{"range":"5","result":"Una tormenta","description":"Nubes negras en el horizonte."},{"range":"6","result":"Barco de la Marina","description":"Una goleta con la bandera del Gobierno."},{"range":"7","result":"Una ballena","description":"Un cetáceo gigante salta."},{"range":"8","result":"Una balsa a la deriva","description":"Alguien pide ayuda."},{"range":"9","result":"Un grupo de aves","description":"Pájaros que migran al sur."},{"range":"10","result":"Columna de humo","description":"Señal de algún lugar."},{"range":"11","result":"Un barco pirata","description":"Bandera negra en el mástil."},{"range":"12","result":"Un iceberg","description":"Masa de hielo a la deriva."},{"range":"13","result":"Lluvia de peces","description":"Animales marinos caen del cielo."},{"range":"14","result":"Una sirena","description":"Una figura misteriosa en las rocas."},{"range":"15","result":"Un tonel flotante","description":"Contiene algo en su interior."},{"range":"16","result":"Niebla mágica","description":"Brillante y sobrenatural."},{"range":"17","result":"Barco fantasma","description":"Un navío abandonado navega sin rumbo."},{"range":"18","result":"Una cascada en el mar","description":"El agua cae hacia el vacío."},{"range":"19","result":"Flores en el agua","description":"Pétalos cubren la superficie."},{"range":"20","result":"El mismísimo Gol D. Roger","description":"¿Una visión del Rey de los Piratas?!"}]',
   '{}','[]','d20',0,@admin_uid);

-- 13. custom: Tesoro Escondido (d100, con auto-invoke a Rol + Rasgo + Meta PNJ)
INSERT IGNORE INTO mybb_game_oracles
  (name, description, oracle_type, subtype, category, tags_json, results_json, variations_json, auto_invoke_json, dice_type, is_system, created_by)
VALUES
  ('Tesoro Escondido', '¿Qué tipo de tesoro han descubierto? Desde berries hasta objetos legendarios.', 'custom', 'tesoro', '',
   '["tesoro","objetos","recompensa"]',
   '[{"range":"1-10","result":"Bolsa de berries","description":"Una cantidad modesta de dinero."},{"range":"11-20","result":"Joyas","description":"Piedras preciosas de valor."},{"range":"21-30","result":"Arma antigua","description":"Una espada o pistola de otra época."},{"range":"31-40","result":"Mapa del tesoro","description":"Lleva a otro tesoro mayor."},{"range":"41-50","result":"Fruta del Diablo","description":"Una fruta misteriosa con poder."},{"range":"51-60","result":"Libro antiguo","description":"Contiene conocimiento prohibido."},{"range":"61-70","result":"Pergamino del Gobierno","description":"Secretos del Gobierno Mundial."},{"range":"71-80","result":"Poneglyph","description":"Un trozo de piedra con inscripciones."},{"range":"81-90","result":"Arma ancestral","description":"Reliquia de una civilización perdida."},{"range":"91-95","result":"El Arma Definitiva","description":"Un arma de destrucción masiva."},{"range":"96-100","result":"One Piece","description":"¿El tesoro más grande del mundo?"}]',
   '{}','[]','d100',0,@admin_uid);

-- 14. delve_theme: Tema de Mazmorra (d20)
INSERT IGNORE INTO mybb_game_oracles
  (name, description, oracle_type, subtype, category, tags_json, results_json, variations_json, auto_invoke_json, dice_type, is_system, created_by)
VALUES
  ('Tema de Mazmorra', 'Define el tema o ambientación de una mazmorra o mazmorra.', 'delve_theme', 'mazmorra', '',
   '["mazmorra","exploracion"]',
   '[{"range":"1-2","result":"Mazmorra acuática","description":"Pasajes inundados y criaturas marinas."},{"range":"3-4","result":"Fortaleza militar","description":"Baluarte de la Marina."},{"range":"5-6","result":"Templo antiguo","description":"Ruinas de una civilización olvidada."},{"range":"7-8","result":"Bosque encantado","description":"Árboles que cobran vida."},{"range":"9-10","result":"Cueva de hielo","description":"Galerías glaciares y frío mortal."},{"range":"11-12","result":"Volcán activo","description":"Ríos de lava y ceniza."},{"range":"13-14","result":"Laberinto subterráneo","description":"Túneles que cambian solos."},{"range":"15-16","result":"Nave abandonada","description":"Un barco fantasma gigante."},{"range":"17-18","result":"Ciudad flotante","description":"Arquitectura en las nubes."},{"range":"19-20","result":"Sótano del Gobierno","description":"Instalación secreta bajo Enies Lobby."}]',
   '{}','[]','d20',0,@admin_uid);

-- 15. delve_domain: Dominio de Mazmorra (d20)
INSERT IGNORE INTO mybb_game_oracles
  (name, description, oracle_type, subtype, category, tags_json, results_json, variations_json, auto_invoke_json, dice_type, is_system, created_by)
VALUES
  ('Dominio de Mazmorra', 'Determina el tipo de criaturas o desafíos en la mazmorra.', 'delve_domain', 'mazmorra', '',
   '["mazmorra","exploracion"]',
   '[{"range":"1-2","result":"Hombres-pez","description":"Guerreros acuáticos."},{"range":"3-4","result":"Marines corruptos","description":"Soldados que abusan de su poder."},{"range":"5-6","result":"Bestias salvajes","description":"Animales gigantes y feroces."},{"range":"7-8","result":"Espíritus antiguos","description":"Entidades de otro tiempo."},{"range":"9-10","result":"Piratas rivales","description":"Otra tripulación en la zona."},{"range":"11-12","result":"Robots / Pacifistas","description":"Creaciones de Vegapunk."},{"range":"13-14","result":"Cazarrecompensas","description":"Cazan cabezas por dinero."},{"range":"15-16","result":"Fruteros","description":"Usuarios de Frutas del Diablo."},{"range":"17-18","result":"Agentes del CP9","description":"Espías del Gobierno Mundial."},{"range":"19-20","result":"Ancestrales","description":"Guardianes de épocas pasadas."}]',
   '{}','[]','d20',0,@admin_uid);
