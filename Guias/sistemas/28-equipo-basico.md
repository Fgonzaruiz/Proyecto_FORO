# 28. Cartas de Equipo Básico (Tienda)

> **Archivo maestro:** `Guias/MAESTRO_SISTEMAS_RPG.md` · Sección 28
> **Propósito:** Documentar exhaustivamente el subsistema de cartas de equipo básico disponibles en la tienda: criterios de selección, categorías, creación en el sistema de cartas, precios, disponibilidad, filosofía de onboarding y consejos para staff.

---

## ÍNDICE

1. [Criterios de Equipo Básico](#1-criterios-de-equipo-básico)
2. [Categorías y Ejemplos](#2-categorías-y-ejemplos)
3. [Creación en el Sistema de Cartas](#3-creación-en-el-sistema-de-cartas)
4. [Guía de Precios](#4-guía-de-precios)
5. [Disponibilidad en Tienda](#5-disponibilidad-en-tienda)
6. [Filosofía de Diseño](#6-filosofía-de-diseño)
7. [Consejos para Staff](#7-consejos-para-staff)
8. [Consejos para Jugadores](#8-consejos-para-jugadores)

---

## 1. Criterios de Equipo Básico

### 1.1 Definición

El **equipo básico** es el conjunto de cartas de tipo `equipo` que forman el catálogo de entrada para personajes nuevos. Son objetos funcionales, sin pretensiones de poder, que permiten al jugador equipar a su PJ desde el primer día sin necesidad de aprobación especial ni justificación narrativa extensa.

### 1.2 Filtros de Elegibilidad

Para que una carta sea considerada "equipo básico" debe cumplir **todos** estos criterios:

| # | Criterio | Explicación |
|---|----------|-------------|
| 1 | Rango **D o C** | El poder de la carta debe estar en el escalón más bajo de la escala. Una carta rango B ya es un salto significativo y debe tratarse como equipo avanzado. |
| 2 | Sin requisitos de raza | No puede exigir una raza específica (Mink, Gyojin, Skypieano, etc.). Cualquier personaje de cualquier origen debe poder usarlo. |
| 3 | Sin requisitos de estilo | No puede requerir un estilo canónico (Rokushiki, Haki, etc.). El equipo básico es universal. |
| 4 | Sin requisitos de disciplina | No puede exigir `disciplina_slug` ni `oficio_slug`. Un personaje sin disciplinas asignadas debe poder equiparlo. |
| 5 | Sin efectos que distorsionen el balance | No puede incluir efectos que alteren drásticamente las reglas del juego (ej. duplicar stats, conceder turnos extra, inmunidad a tipos de daño). |
| 6 | Precio razonable para PJ nuevo | El coste en Berries debe estar al alcance de un personaje recién aprobado (ver sección 4). |
| 7 | Sin efectos de despertar o haki | No debe requerir ni otorgar acceso a mecánicas avanzadas (Haki, Akuma no Mi, Despertar). |
| 8 | Tier 1 obligatorio | El campo `tier` debe ser 1. Esto garantiza que no haya barrera de progresión para obtenerlo. |

### 1.3 Lo Que NO es Equipo Básico

| Tipo | Ejemplo | Por qué no es básico |
|------|---------|---------------------|
| Arma legendaria | *Yubashiri* (rango A) | Rango superior a C, requiere justificación narrativa |
| Armadura con Haki | *Armadura de Haki Endurecido* | Requiere Haki de Armamento nivel básico |
| Herramienta de oficio avanzado | *Kit de Carpintería Naval de Maestro* | Requiere oficio `carpintero_naval` |
| Objeto racial | *Electro Staff Elborrado* | Solo usable por Mink (raza específica) |
| Consumible con efecto permanente | *Elixir de la Juventud* | Efecto que distorsiona el balance (stats permanentes) |
| Arma con efectos especiales complejos | *Espada que Absorbe Almas* | Efecto que requiere reglas especiales de staff |

### 1.4 Impacto RPG

| Criterio | Lo que significa para el juego |
|----------|-------------------------------|
| Rango D-C | El personaje empieza con equipo modesto y puede progresar a mejor |
| Sin requisitos | Cualquier personaje, sin importar su origen, puede comprar equipo básico |
| Sin efectos de balance | Las partidas no se rompen porque alguien compró en tienda |
| Precio razonable | El primer personaje no sufre "pared de berries" para equiparse |

---

## 2. Categorías y Ejemplos

### 2.1 Armas Básicas

Armas de rango D-C, sin requisitos de estilo, con estadísticas modestas y efectos simples o nulos.

| Nombre genérico | Rango | Subtipo | stat | Dados | Precio (B.) | Descripción |
|-----------------|:-----:|---------|:----:|:-----:|:-----------:|-------------|
| Espada Estándar | D | espada | fue | 1d8 | 150 | Espada recta de acero común. Filo básico, sin lujos. |
| Cuchillo de Combate | D | espada | fue | 1d6 | 80 | Hoja corta y ligera. Fácil de ocultar. |
| Arco Corto | D | arma_distancia | des | 1d8 | 150 | Arco de madera flexible. Alcance medio. |
| Pistola de un Cañón | D | arma_fuego | des | 1d10 | 200 | Pistola de chispa básica. Un disparo por recarga. |
| Garrote de Madera | D | arma_contundente | fue | 1d6 | 50 | Palo robusto. No corta, pero aturde. |
| Hacha de Mano | C | arma_contundente | fue | 2d6 | 300 | Hacha pequeña de una mano. Útil también como herramienta. |
| Daga Asegurada | C | espada | agi | 2d4 | 220 | Daga con guarda para los dedos. Precisión mejorada. |
| Honda Reforzada | C | arma_distancia | des | 1d8+2 | 180 | Honda con mecanismo de palanca para mayor potencia. |
| Tonfa de Hierro | C | arma_contundente | fue | 2d4 | 250 | Tonfa metálico. Permite bloquear ataques ligeros. |

**Características comunes de las armas básicas:**

- Sin efectos especiales (`efectos_especiales: []`)
- Sin bonus a stats (`bonus_stats: {"fue": 0, "agi": 0, ...}`)
- Sin tags de material especial (no son "acero superior", "madera bendita", etc.)
- Sin capacidad de [ARMA] en fórmulas compuestas — son armas autónomas
- Durabilidad estándar sin tratamiento especial

### 2.2 Armaduras Básicas

Protecciones ligeras que ofrecen defensa modesta sin penalizadores significativos.

| Nombre genérico | Rango | Parte | Defensa Base | Penalizador | Precio (B.) | Descripción |
|-----------------|:-----:|-------|:------------:|:-----------:|:-----------:|-------------|
| Capa Reforzada | D | torso | 3 | 0 | 100 | Capa de viaje con costuras reforzadas. Protege del viento y cortes superficiales. |
| Chaleco de Cuero | D | torso | 4 | 0 | 120 | Chaleco de cuero curtido. Protección ligera y flexible. |
| Venda Protectora | D | brazos | 1 | 0 | 40 | Vendas enrolladas en antebrazos. Protección mínima. |
| Sombrero de Algodón | D | cabeza | 1 | 0 | 30 | Gorro simple. Protege del sol más que de golpes. |
| Grebas de Madera | D | piernas | 2 | 0 | 60 | Tablillas atadas a las espinillas. Protección básica. |
| Camisa de Malla Ligera | C | torso | 6 | -1 agi | 250 | Malla de anillos pequeños. Ofrece buena protección pero resta movilidad. |
| Brazales de Cuero | C | brazos | 3 | 0 | 100 | Protectores de antebrazo acolchados. |
| Cinturón de Utilidad | C | accesorio | 0 | 0 | 80 | Cinturón con bolsas y ganchos. Sin defensa, pero útil para portar herramientas. |

**Características comunes de las armaduras básicas:**

- Penalizador máximo de -1 (si existe)
- Sin `bonus_res` ni `bonus_stats` defensivos
- Materiales comunes: cuero, madera, tela reforzada, hierro ligero
- Sin efectos especiales (no son ignífugas, no son impermeables de forma mágica)
- Sin requisito de oficio para equipar

### 2.3 Herramientas

Objetos de utilidad que no otorgan poder de combate directo pero expanden las opciones narrativas y mecánicas del personaje.

| Nombre genérico | Rango | Tipo en DB | Precio (B.) | Descripción |
|-----------------|:-----:|------------|:-----------:|-------------|
| Brújula Estándar | D | util | 50 | Brújula magnética básica. Señala el norte. |
| Catalejo Plegable | D | util | 80 | Telescopio de bolsillo. Permite ver a larga distancia. |
| Kit de Escalada | D | util | 100 | Cuerda de 10 m, mosquetones y arnés básico. |
| Cantimplora | D | util | 30 | Recipiente para agua. Capacidad 1 L. |
| Mapa de las Islas | D | util | 60 | Mapa general del archipiélago. Puntos clave marcados. |
| Linterna de Aceite | D | util | 40 | Luz portable. Combustible: aceite (se vende por separado). |
| Grappling Hook | C | util | 150 | Gancho de agarre con cuerda de 15 m. Requiere agilidad para usar. |
| Kit de Supervivencia | C | util | 200 | Incluye yesca, navaja pequeña, hilo de pescar y sales. |
| Red Plegable | C | util | 120 | Red ligera de 3 m de radio. Útil para pesca, captura o camuflaje. |
| Silbato de Señales | D | util | 20 | Silbato de metal de alto volumen. Se oye a 500 m. |

**Características comunes de las herramientas básicas:**

- Tipo `equipo` con `equipo_type = "util"` y `usos = 999` (reutilizables) o `usos = 1` (desechables)
- Sin `dice` (no generan tiradas de daño)
- Sin `execution_stat` (no escalan con stats del personaje)
- Efecto puramente narrativo o de utilidad mecánica menor
- Sin coste PE (`cost_pe = "—"`)
- El staff puede decidir si una herramienta específica debe ser consumible o reutilizable según su naturaleza

### 2.4 Consumibles

Objetos de un solo uso (o usos limitados) que el personaje consume para obtener un efecto inmediato.

| Nombre genérico | Rango | Efecto | Precio (B.) | Precio Unitario |
|-----------------|:-----:|--------|:-----------:|:---------------:|
| Venda Medicinal | D | Cura 1d4 PV | 30 | 30 |
| Antídoto Genérico | D | Cura venenos comunes | 50 | 50 |
| Bengala de Señales | D | Ilumina el cielo 1 post | 40 | 40 |
| Poción de Curación Menor | D | Cura 1d6+2 PV | 60 | 60 |
| Venda Antibacteriana | D | Cura 1d4 PV + previene infección | 45 | 45 |
| Tónico de Energía | D | Recupera 1d6 PE | 70 | 70 |
| Poción de Curación Estándar | C | Cura 2d6+2 PV | 120 | 120 |
| Antídoto Reforzado | C | Cura venenos potentes + 1d4 PV | 100 | 100 |
| Granada de Humo | C | Oscurece un área 1 post | 80 | 80 |
| Kit de Vendaje Completo | C | Cura 3d4 PV (3 usos) | 250 | 83 |
| Fuego Artificial | C | Señal luminosa de colores, visible 1 km | 60 | 60 |

**Características comunes de los consumibles básicos:**

- `tags_json` contiene `"CONSUMIBLE"` para que el sistema los decremente al usarse
- `card_type = "equipo"` con `equipo_type = "util"` para detección como consumible en tienda
- Efectos cuantificables pero modestos (dados pequeños, sin multiplicadores)
- Sin efectos de estado alterado complejos (no aturden, no envenenan, solo curan)
- Sin duración prolongada (el efecto se resuelve en el mismo post)
- Pueden comprarse en múltiplos (la tienda permite cantidad > 1)

### 2.5 Tabla Resumen de Categorías

| Categoría | Tipos de carta | Rango | Precio típico | Consumible | Cantidad máx. |
|-----------|---------------|:-----:|:-------------:|:----------:|:-------------:|
| Armas | `equipo` (subtipo arma) | D–C | 50–300 B. | No | 1 |
| Armaduras | `equipo` (subtipo armadura) | D–C | 30–250 B. | No | 1 por parte |
| Herramientas | `equipo` (subtipo util) | D–C | 20–200 B. | Depende | 1–5 |
| Consumibles | `equipo` (subtipo util + CONSUMIBLE) | D–C | 30–250 B. | Sí | Ilimitado |

### 2.6 Impacto RPG

| Categoría | Lo que significa para el juego |
|-----------|-------------------------------|
| Armas básicas | El personaje puede defenderse desde el día 1 sin ser overpowered |
| Armaduras básicas | El personaje sobrevive a sus primeros combates sin morir en un golpe |
| Herramientas | El personaje puede interactuar con el mundo más allá del combate |
| Consumibles | El personaje aprende a gestionar recursos desde el principio |

---

## 3. Creación en el Sistema de Cartas

### 3.1 Campos Obligatorios en `game_cards`

Para insertar una carta de equipo básico en la base de datos, el staff debe completar los siguientes campos:

```sql
INSERT INTO mybb_game_cards (
    name, card_type, `rank`, activation, tags_json, description,
    cost_pe, execution_cost, execution_stat, dice, effects_json,
    cost_berries, in_shop, shop_category, peso, created_by,
    reposo, duracion, tier, disciplina_slug, estilo_canonico_slug, oficio_slug
) VALUES (
    'Espada Estándar',        -- name
    'equipo',                 -- card_type
    'D',                      -- rank
    'activa',                 -- activation
    '[]',                     -- tags_json (sin tags especiales)
    'Espada recta de acero común. Filof básico, sin lujos.', -- description
    '—',                      -- cost_pe (sin coste de PE)
    0,                        -- execution_cost
    'fue',                    -- execution_stat (escala con FUE)
    '1d8+fue',                -- dice
    '{"equipo_type":"arma","subtipo":"espada","manos":1,"alcance":"corto","material":"acero","filosofia":"estandar","bonus_stats":{},"efectos_especiales":[]}',   -- effects_json
    150,                      -- cost_berries
    1,                        -- in_shop
    'armeria',                -- shop_category
    2,                        -- peso
    1,                        -- created_by (staff ID)
    0,                        -- reposo
    0,                        -- duracion
    1,                        -- tier
    NULL,                     -- disciplina_slug (sin requisito)
    NULL,                     -- estilo_canonico_slug
    NULL                      -- oficio_slug
);
```

### 3.2 Reglas de Creación por Tipo

#### Armas

```json
{
    "equipo_type": "arma",
    "subtipo": "espada",
    "manos": 1,
    "alcance": "corto",
    "material": "acero",
    "filosofia": "estandar",
    "bonus_stats": {},
    "efectos_especiales": []
}
```

| Campo | Regla para básico |
|-------|------------------|
| `manos` | 1 (una mano) o 2 (dos manos). Sin armas que ocupen slot especial. |
| `alcance` | `corto` o `medio`. Sin alcance `largo` en básico. |
| `material` | Materiales comunes: `acero`, `madera`, `cuero`, `hierro`. |
| `filosofia` | `estandar` para armas sin estilo específico. |
| `bonus_stats` | Vacío u objeto vacío. Sin bonus a stats. |
| `efectos_especiales` | Vacío o array vacío. Sin efectos de estado. |

**Dados máximos por rango:**

| Rango | Dados máx. | Ejemplo válido | Ejemplo inválido |
|:-----:|:----------:|----------------|------------------|
| D | 1d10 | `1d8+fue`, `1d10+des` | `2d8+fue` (supera el tope) |
| C | 2d6 | `2d6+fue`, `1d12+des` | `3d6+fue` (supera el tope) |

#### Armaduras

```json
{
    "equipo_type": "armadura",
    "parte": "torso",
    "defensa_base": 4,
    "material": "cuero",
    "penalizador_agi": 0,
    "bonus_res": 0
}
```

| Campo | Regla para básico |
|-------|------------------|
| `defensa_base` | Máx. 4 para rango D, máx. 6 para rango C. |
| `penalizador_agi` | 0 para D, -1 máximo para C. |
| `bonus_res` | 0. Sin bonus a stats defensivos. |
| `material` | Materiales comunes: `cuero`, `tela`, `madera`, `hierro_ligero`. |

#### Herramientas

```json
{
    "equipo_type": "util",
    "usos": 999,
    "efecto": "Permite escalar superficies verticales hasta 15 m."
}
```

| Campo | Regla para básico |
|-------|------------------|
| `usos` | 999 (reutilizable) o 1 (desechable). Si es desechable, añadir tag `CONSUMIBLE`. |
| `efecto` | Descripción textual del efecto. Sin números de dados ni fórmulas. |

#### Consumibles

```json
{
    "equipo_type": "util",
    "usos": 1,
    "efecto": "cura 1d6+2 PV",
    "target": "self"
}
```

| Campo | Regla para básico |
|-------|------------------|
| `usos` | Siempre 1 (salvo packs multi-usos como el Kit de Vendaje Completo). |
| `efecto` | Fórmula de curación o efecto. Máx. 2d6+2 para rango C. |
| `target` | `self` (autoaplicado). Sin consumibles que afecten a terceros en básico. |

**Tags necesarios en `tags_json`:**

```json
["CONSUMIBLE"]
```

### 3.3 Mapeo a Categoría de Tienda

| Tipo de equipo | `shop_category` en DB |
|----------------|----------------------|
| Armas | `armeria` |
| Armaduras | `armeria` |
| Herramientas | `utiles` |
| Consumibles | `utiles` |

**Importante:** Actualmente la DB usa 4 categorías: `utiles`, `armeria`, `naval`, `mascotas`. Las armas y armaduras comparten `armeria`. Los consumibles y herramientas comparten `utiles`. El frontend las distingue por el `card_type` y `effects_json.equipo_type`.

### 3.4 Validación en Creación

Antes de insertar una carta como equipo básico, el staff debe verificar:

```php
$validaciones = [
    'rango_valido'       => in_array($rank, ['D', 'C']),
    'tier_valido'        => $tier === 1,
    'sin_disciplina'     => empty($disciplina_slug),
    'sin_oficio'         => empty($oficio_slug),
    'sin_estilo'         => empty($estilo_canonico_slug),
    'precio_razonable'   => $cost_berries > 0 && $cost_berries <= 300,
    'sin_efectos_rojos'  => !tiene_efectos_prohibidos($effects_json),
    'tipo_valido'        => $card_type === 'equipo',
];
```

### 3.5 Efectos Prohibidos en Equipo Básico

| Efecto | Motivo |
|--------|--------|
| Duplicar stats | Rompe el balance de progresión |
| Inmunidad a daño | Hace invulnerable al personaje |
| Turnos extra | Distorsiona el sistema de combate |
| Revivir | Mecánica de alto impacto narrativo |
| Modificar dados de otros | Afecta el combate de terceros |
| Robo de objetos | Mecánica compleja de gestionar |
| Invocación permanente | Requiere seguimiento de staff |
| Alteración de gravedad/tiempo | Efectos de alto nivel narrativo |

### 3.6 Impacto RPG

| Aspecto | Lo que significa para el juego |
|---------|-------------------------------|
| Inserción directa en DB | El staff puede crear equipo básico sin pasar por flujo de solicitudes |
| Sin tags especiales | El sistema no aplica lógica extra a estas cartas |
| shop_category fijo | El jugador las encuentra en las categorías esperadas de la tienda |
| Sin efectos prohibidos | No hay sorpresas de balance para el staff |

---

## 4. Guía de Precios

### 4.1 Principios de Precio

1. **Accesible para PJ nuevo:** Un personaje recién aprobado recibe entre 200 y 500 Berries iniciales (según configuración del foro). El equipo básico debe ser comprable con ese presupuesto.

2. **Escalado por rango:** Las cartas rango D cuestan menos que las rango C. La diferencia de precio debe reflejar la diferencia de poder.

3. **Escalado por utilidad:** Los consumibles (que se gastan) cuestan menos que los objetos permanentes. Las herramientas cuestan menos que las armas.

4. **Sink económico:** Al vender, el jugador recupera el 50% (floor). El precio base debe ser suficientemente bajo para que la pérdida no sea frustrante.

### 4.2 Tabla de Precios Recomendados

| Tipo | Rango D | Rango C |
|------|:-------:|:-------:|
| Arma una mano | 80–150 B. | 200–300 B. |
| Arma dos manos | 100–200 B. | 250–350 B. |
| Arma distancia | 100–200 B. | 180–280 B. |
| Armadura torso | 100–150 B. | 200–300 B. |
| Armadura extremidad | 30–80 B. | 80–150 B. |
| Armadura cabeza | 30–60 B. | 60–120 B. |
| Accesorio | 20–80 B. | 80–150 B. |
| Herramienta (reutilizable) | 30–100 B. | 100–200 B. |
| Herramienta (consumible) | 20–60 B. | 60–150 B. |
| Consumible curativo | 30–70 B. | 100–150 B. |
| Consumible antídoto | 40–60 B. | 80–120 B. |
| Consumible señal/iluminación | 30–50 B. | 50–100 B. |

### 4.3 Fórmula de Precio Sugerida

Para armas y armaduras, el staff puede usar esta fórmula orientativa:

```
precio_base = (dados_promedio × 20) + (defensa_base × 15) + (bonus × 50)
```

**Ejemplos:**
- Espada Estándar (1d8, rango D): `(4.5 × 20) + (0 × 15) + (0 × 50) = 90` → se redondea a 150 por ser arma principal.
- Chaleco de Cuero (defensa 4, rango D): `(0 × 20) + (4 × 15) + (0 × 50) = 60` → se redondea a 120.
- Daga Asegurada (2d4, rango C): `(5 × 20) + (0 × 15) + (0 × 50) = 100` → se redondea a 220.

**Nota:** La fórmula es orientativa. El staff debe ajustar según el contexto del foro, la inflación actual y la dificultad de obtener berries.

### 4.4 Precio vs Reembolso

| Precio | Reembolso (50% floor) | Sensación del jugador |
|:------:|:---------------------:|-----------------------|
| 50 | 25 | "Pues vale, perdí 25." |
| 100 | 50 | "Me duele un poco." |
| 200 | 100 | "Lo pensaré dos veces." |
| 300 | 150 | "Solo si realmente lo necesito." |
| 500+ | 250+ | "¿Estoy seguro de esta compra?" |

**Regla:** El reembolso de equipo básico nunca debería superar los 150 B. Si un objeto tiene reembolso mayor, probablemente no es "básico".

### 4.5 Paquetes y Lotes

Opcionalmente, el staff puede crear "paquetes de inicio" que agrupen varios objetos básicos a un precio reducido:

| Paquete | Contenido | Precio | Ahorro |
|---------|-----------|:------:|:------:|
| Pack Novato (combate) | Espada Estándar + Chaleco de Cuero | 250 B. | 20 B. |
| Pack Novato (explorador) | Arco Corto + Brújula + Cantimplora | 220 B. | 40 B. |
| Pack Novato (supervivencia) | Kit de Supervivencia + Venda Medicinal ×3 | 250 B. | 40 B. |
| Pack Médico | Poción Curación Menor ×3 + Antídoto Genérico | 200 B. | 30 B. |
| Pack Herramientas | Kit de Escalada + Linterna + Silbato | 150 B. | 20 B. |

Los paquetes se crean como cartas individuales con `effects_json` que lista las cartas incluidas, y al comprarse el sistema asigna todas las cartas del paquete al inventario del jugador.

### 4.6 Impacto RPG

| Decisión de precio | Lo que significa para el juego |
|--------------------|-------------------------------|
| Precios accesibles | Los PJ nuevos no se quedan sin equipo |
| Diferencia D vs C | El jugador percibe la progresión de poder |
| 50% reembolso | El jugador aprende a valorar sus compras |
| Paquetes de inicio | El onboarding es más suave y generoso |

---

## 5. Disponibilidad en Tienda

### 5.1 Marcado en DB

Para que una carta aparezca en la tienda, el staff debe asegurarse de:

```sql
UPDATE mybb_game_cards
SET in_shop = 1,
    shop_category = 'armeria',  -- según corresponda
    cost_berries = 150
WHERE id = {card_id};
```

### 5.2 Query de Catálogo

La tienda carga las cartas con esta query (ver `20-economia.md §4.2`):

```sql
SELECT id, name, card_type, `rank`, image_url, description, cost_berries, shop_category,
       effects_json, tags_json, dice, cost_pe, execution_cost, execution_stat,
       activation, reposo, duracion
FROM game_cards
WHERE in_shop = 1
  AND cost_berries > 0
  AND card_type IN ('equipo', 'npc_menor', 'barco')
ORDER BY shop_category ASC, name ASC;
```

El equipo básico se filtra automáticamente porque todas las cartas de equipo tienen `card_type = 'equipo'`.

### 5.3 Detección de Consumibles en Tienda

```php
$row['is_consumable'] = (
    $row['card_type'] === 'equipo'
    && strtolower((string)($effects['equipo_type'] ?? '')) === 'util'
);
```

Los consumibles básicos deben tener `equipo_type = "util"` para que el sistema permita comprar múltiples unidades.

### 5.4 Categorías Visuales en Tienda

| Categoría en DB | Display en tienda | Equipo básico que aparece aquí |
|-----------------|-------------------|-------------------------------|
| `armeria` | Armería | Armas y armaduras básicas |
| `utiles` | Útiles | Herramientas y consumibles básicos |

### 5.5 Gestión desde Panel de Staff

El staff puede añadir o quitar cartas del catálogo desde `zona_staff_tienda.php`:

1. Buscar la carta en el pool disponible (`shop_catalog_list.php?scope=pool`)
2. Seleccionar la categoría (`armeria` o `utiles`)
3. Confirmar la inclusión

Para retirar equipo básico desactualizado o desbalanceado, el staff usa el mismo panel y marca `in_shop = 0`. Las cartas ya compradas por jugadores no se eliminan de sus inventarios.

### 5.6 Rotación del Catálogo Básico

**Recomendación:** El catálogo de equipo básico no debería rotar con frecuencia. La estabilidad permite a los jugadores nuevos planificar sus compras. Cambios sugeridos:

| Cuándo | Acción |
|--------|--------|
| Nueva temporada narrativa | Añadir 1–2 armas nuevas temáticas |
| Detección de desbalance | Ajustar precio o retirar carta problemática |
| Solicitud de jugadores | Evaluar inclusión de nuevas herramientas |
| Inflación detectada | Subir precios ligeramente (10–20%) |

### 5.7 Impacto RPG

| Aspecto | Lo que significa para el juego |
|---------|-------------------------------|
| Siempre disponible | El jugador sabe que puede comprar equipo básico en cualquier momento |
| Categorías fijas | La navegación en tienda es predecible |
| Sin rotación agresiva | No hay estrés por "comprar antes de que desaparezca" |
| Ajustes por staff | El equilibrio se mantiene sin reinventar el catálogo |

---

## 6. Filosofía de Diseño

### 6.1 Propósito del Equipo Básico

El equipo básico existe para resolver tres problemas fundamentales del onboarding en un RPG de foro:

1. **Problema de la página en blanco:** Un personaje nuevo no tiene nada. Sin equipo básico en tienda, el jugador tendría que solicitar cada objeto al staff mediante el sistema de solicitudes de cartas (`game_card_requests`), lo cual ralentiza la puesta en marcha.

2. **Problema de la curva de aprendizaje:** Si la tienda solo ofreciera equipo avanzado (rango B+), el jugador nuevo no sabría qué es valioso ni cómo progresar. El equipo básico enseña la estructura de precios, las categorías y el flujo de compra sin riesgo de error costoso.

3. **Problema de la desigualdad inicial:** Sin equipo básico, los jugadores con más experiencia o contactos conseguirían equipo más rápido que los nuevos. La tienda con equipo básico nivelada el campo de juego.

### 6.2 Principios Rectores

1. **El equipo básico es un trampolín, no un destino.** Los objetos básicos están diseñados para ser reemplazados. El jugador debería sentir que "supera" su espada estándar y quiere algo mejor.

2. **El onboarding económico es generoso pero no gratuito.** El personaje nuevo recibe berries iniciales justos para comprar 2–3 objetos básicos, no para comprar toda la tienda. Debe elegir qué priorizar.

3. **El error de compra no es catastrófico.** Si un jugador nuevo compra el arma equivocada, la vende al 50% y pierde unos berries. La pérdida es una lección, no un castigo.

4. **El equipo básico es genérico por diseño.** No intentes ser creativo con el equipo básico. La creatividad viene después, con las cartas personalizadas. El básico es funcional, predecible y aburrido a propósito.

### 6.3 Decisiones Clave y su Porqué

| Decisión | Alternativa descartada | Por qué se eligió así |
|----------|----------------------|----------------------|
| Rango D-C máximo | Rango B permitido | El rango B ya es un salto de poder; debe ser aspiracional |
| Sin requisitos de disciplina | Exigir disciplina mínima | El PJ nuevo no tiene disciplinas; no podría comprar nada |
| Efectos simples o nulos | Efectos complejos desde inicio | El jugador debe aprender mecánicas básicas antes de las complejas |
| Precios fijos en tienda | Precios variables por oferta/demanda | Simplicidad cognitiva para el jugador nuevo |
| Paquetes de inicio opcionales | Solo venta individual | Los paquetes facilitan la decisión del jugador indeciso |

### 6.4 El Equipo Básico como Herramienta de Onboarding

El flujo ideal de un jugador nuevo es:

```
1. Crea personaje → recibe berries iniciales (ej. 300 B.)
2. Visita tienda → ve equipo básico con precios claros
3. Compra arma básica (150 B.) + armadura básica (120 B.) + consumible (30 B.)
   → Gasta 300 B. exactos, le queda 0
4. Participa en su primer hilo → usa su equipo → aprende a jugar cartas
5. Gana berries en el hilo → vuelve a tienda → compra mejora
6. Eventualmente reemplaza su equipo básico por equipo rango B o superior
```

Este flujo **no requiere interacción con el staff**. El jugador aprende el sistema solo, experimenta con sus compras y se integra al foro sin fricción.

### 6.5 El Coste de Oportunidad del Equipo Básico

Cuando un jugador compra equipo básico, está decidiendo NO comprar otra cosa. Esta decisión debe ser significativa pero no paralizante:

| Escenario | Dilema | Lección que aprende |
|-----------|--------|---------------------|
| ¿Arma o armadura primero? | No tengo berries para ambos | Priorizar según estilo de juego |
| ¿Herramienta o consumible? | La herramienta es útil pero cara; el consumible se gasta | Reconocer valor a corto vs largo plazo |
| ¿Espada D o espada C? | La C es mejor pero cuesta el doble | Ahorrar para objetivos mayores |
| ¿Comprar ahora o esperar? | Si compro ahora, no ahorro para el barco | Planificación financiera básica |

### 6.6 Impacto RPG

| Principio | Lo que significa para el juego |
|-----------|-------------------------------|
| Trampolín, no destino | El jugador siempre tiene algo que mejorar |
| Onboarding generoso | El nuevo jugador se siente bienvenido |
| Error no catastrófico | El jugador experimenta sin miedo |
| Genérico por diseño | El foco está en la narrativa, no en el equipo inicial |

---

## 7. Consejos para Staff

### 7.1 Creando Nuevo Equipo Básico

**Antes de crear, pregúntate:**
- ¿Cubre este objeto un hueco real en el catálogo? ¿O es un duplicado de algo que ya existe?
- ¿Entendería un jugador nuevo para qué sirve solo con leer el nombre?
- ¿El efecto es lo suficientemente simple como para explicarse en una frase?

**Durante la creación:**
- Usa nombres descriptivos y genéricos. *"Espada Estándar"* es mejor que *"Filo del Amanecer Incierto"*.
- La descripción debe indicar claramente qué hace el objeto y en qué contexto es útil.
- No añadas efectos "secretos" o "sorpresa" al equipo básico. Todo debe ser transparente.
- Respeta los topes de dados de la sección 3.2. Si una carta básica tiene más dados de los permitidos, no es básica.

**Después de crear:**
- Verifica que la carta aparezca correctamente en la tienda con la categoría adecuada.
- Compra la carta con un personaje de prueba para confirmar que el flujo funciona.
- Revisa que el reembolso (50%) sea adecuado para el precio.

### 7.2 Manteniendo el Catálogo

- **Audita el catálogo cada 3–6 meses.** Revisa si algún equipo básico está desbalanceado respecto al meta actual del foro.
- **Retira objetos que nadie compra.** Si un objeto lleva 6 meses sin venderse, quizá su precio es muy alto o su utilidad no está clara. Ajústalo o retíralo.
- **No satures el catálogo.** 10–15 armas básicas, 8–10 armaduras, 10–15 herramientas y 10–15 consumibles es más que suficiente. Demasiada oferta abruma al jugador nuevo.
- **Mantén coherencia temática.** Si el foro está en una era de la navegación, el equipo básico debería reflejar ese setting (espadas, mosquetes, catalejos, brújulas).

### 7.3 Detectando Problemas

| Síntoma | Posible causa | Solución |
|---------|---------------|----------|
| Los PJ nuevos compran siempre lo mismo | El resto del catálogo no es atractivo o está mal priced | Revisar precios y utilidad de otros objetos |
| Los jugadores se saltan el equipo básico | El equipo avanzado es demasiado accesible | Subir precio o requisitos del equipo avanzado |
| Quejas de que "el equipo básico es muy caro" | Inflación o berries iniciales insuficientes | Ajustar berries iniciales o bajar precios 10–20% |
| Objeto básico aparece en combates de alto nivel | El objeto es demasiado bueno para su rango | Nerfear el objeto o subirlo a rango C con precio ajustado |
| Jugadores compran consumibles y nunca los usan | Los consumibles son muy caros o los combates muy fáciles | Bajar precio de consumibles o revisar dificultad de combates |

### 7.4 Política de Reembolsos y Cambios

- **Si el staff modifica un equipo básico (ej. baja su poder):** No se realiza reembolso automático. El cambio aplica a compras futuras. Los objetos ya comprados mantienen sus stats originales.
- **Si el staff retira un equipo básico del catálogo:** Los jugadores que ya lo poseen no lo pierden. Solo deja de estar disponible para nuevas compras.
- **Si un jugador compró por error:** El staff puede hacer una excepción y reembolsar el 100% si se reporta dentro de las 24 horas. No es política oficial, pero es una cortesía que mejora la experiencia.

### 7.5 Integración con Eventos y Temporadas

El equipo básico puede ampliarse temporalmente durante eventos:

- **Evento de temporada:** Añadir 2–3 objetos temáticos (ej. *"Antorcha de Invierno"* durante evento de nieve).
- **Campaña narrativa:** Añadir equipo básico relacionado con la trama actual (ej. *"Brújula del Archipiélago Misterioso"*).
- **Festividad del foro:** Paquetes de inicio con descuento por aniversario.

Estos objetos temporales se retiran del catálogo al finalizar el evento, pero los jugadores que los compraron los conservan.

### 7.6 Impacto RPG

| Consejo | Lo que significa para el juego |
|---------|-------------------------------|
| Catálogo auditado | El equilibrio se mantiene en el tiempo |
| Coherencia temática | El mundo se siente vivo y consistente |
| Objetos temporales | Los eventos tienen recompensas tangibles |
| Política clara de cambios | Los jugadores confían en que su inversión está segura |

---

## 8. Consejos para Jugadores

### 8.1 Tu Primera Compra

Cuando creas tu personaje y recibes tus berries iniciales:

1. **Compra un arma primero.** Sin arma, no puedes participar en combates. Prioriza un arma de rango D (barata) o C (si te alcanza).
2. **Compra una armadura después.** Incluso una capa reforzada (100 B.) te da 3 de defensa que pueden marcar la diferencia.
3. **Lleva al menos 1 consumible curativo.** Una venda medicinal (30 B.) o una poción menor (60 B.) puede salvarte la vida.
4. **Si te sobran berries, compra una herramienta.** Un catalejo o una brújula abren opciones narrativas.

### 8.2 Prioriza según tu Estilo

| Estilo de juego | Prioridad de compra |
|----------------|---------------------|
| Combate cuerpo a cuerpo | Arma cuerpo a cuerpo → Armadura torso → Consumible curativo |
| Combate a distancia | Arma a distancia → Armadura ligera → Herramienta de posicionamiento |
| Explorador | Herramientas (brújula, kit escalada) → Arma defensiva → Consumibles |
| Rol puro | Herramientas narrativas → Armadura → Consumible |
| Médico/Support | Consumibles curativos → Armadura → Herramienta |

### 8.3 No Tengas Miedo de Vender

Si compraste algo que no te gusta:
- Vénde lo en la tienda (recuperas el 50%).
- Usa los berries para comprar algo mejor.
- La pérdida es pequeña: perder 50–75 berries en una mala compra no arruina tu personaje.

### 8.4 El Equipo Básico es Temporal

No te enamores de tu espada estándar. El equipo básico está diseñado para ser reemplazado. A medida que tu personaje gane berries y experiencia, busca equipo de rango B, A o superior mediante:

- Solicitudes de cartas personalizadas al staff.
- Misiones y eventos especiales.
- Comercio narrativo con otros jugadores.
- Recompensas de staff por participación.

### 8.5 Los Consumibles se Acumulan

Los consumibles básicos (vendas, pociones, antídotos) son la excepción a la regla de "objeto único". Puedes tener múltiples copias en tu inventario. Aprovecha esto para:

- Llevar siempre 3–5 consumibles curativos en tus hilos.
- Comprar en bulk cuando tengas berries extra.
- Vender los que sobran si necesitas efectivo rápido.

### 8.6 Errores Comunes

| Error | Consecuencia | Cómo evitarlo |
|-------|-------------|---------------|
| Gastar todos los berries en un solo objeto | Te quedas sin presupuesto para armadura/consumibles | Reparte tu presupuesto: 50% arma, 30% armadura, 20% consumibles |
| Comprar equipo de rango C sin tener berries para consumibles | El arma es buena, pero no tienes curación | Prioriza consumibles antes que la mejora de arma |
| No comprar armadura | Recibes daño completo en cada combate | La armadura es tan importante como el arma |
| Comprar herramientas que nunca usas | Berries desperdiciados | Piensa si tu personaje realmente escalaría, navegaría o exploraría |
| Vender equipo básico para comprar equipo avanzado demasiado pronto | Te quedas sin equipo funcional | Asegúrate de tener al menos un arma y armadura antes de vender lo viejo |

### 8.7 Impacto RPG

| Consejo | Lo que significa para el juego |
|---------|-------------------------------|
| Compra arma primero | Puedes participar en combates desde el día 1 |
| Prioriza por estilo | Tu personaje se siente único desde el inicio |
| Vender sin miedo | Experimentar con builds no tiene castigo severo |
| Equipo es temporal | Siempre hay algo mejor que conseguir |

---

*Fin del documento — Guía completa de Cartas de Equipo Básico v1.0*
*Generado desde: `Guias/sistemas/28-equipo-basico.md`*
