# Sistema de Akuma no Mi — Estructura de Carta, Tier y Guía de Creación Equilibrada v1

> Documento de diseño para IAs. Complementa `02-sistema-cartas-haki-akuma.md`.  
> Define la estructura ampliada de la carta Akuma, el sistema de tier por fruta y la guía completa  
> para que una IA cree cartas de Akuma equilibradas, coherentes y listas para insertar.

---

## 1. Filosofía

Una Akuma no Mi es una **carta única, hiperdesarrollada** que define lo que el personaje ES, no solo lo que hace. No da técnicas: da una identidad de poder, sus capacidades pasivas, sus transformaciones, sus reglas especiales y sus debilidades. Las técnicas concretas son cartas separadas que el jugador solicita al staff usando la carta base como fundamento.

**Principio de equilibrio central:** el poder de la fruta debe ser proporcional a su tier canónico. Una fruta tier 1 tiene poderes narrativos modestos y muchas limitaciones. Una fruta tier 5 es capaz de alterar el mundo y tiene restricciones severas de ESP y nivel. El tier no es negociable: lo asigna el staff al crear la fruta en `game_akuma_no_mi` y se hereda en la carta.

---

## 2. Tier de las Akuma no Mi

### 2.1 Escala de tier por clase canónica

No todas las clases llegan a todos los tiers. La escala canónica de One Piece se refleja así:

| Tier | Paramecia | Logia | Zoan |
|------|-----------|-------|------|
| 1 | ✓ (poderes de utilidad, efectos menores) | ✗ | ✓ (animales comunes, sin ventaja real) |
| 2 | ✓ (combate modesto, un efecto sólido) | ✓ (elemento menor o limitado) | ✓ (animales con ventaja física) |
| 3 | ✓ (poder temático fuerte, versatilidad media) | ✓ (elemento con presencia ofensiva real) | ✓ (depredadores, animales míticos menores) |
| 4 | ✓ (poderes extraordinarios, alteran el entorno) | ✓ (elemento destructivo, inmunidad sólida) | ✓ (bestias legendarias, formas múltiples) |
| 5 | ✓ (solo las más poderosas canónicamente) | ✓ (solo las más destructivas) | ✓ (solo míticas supremas) |

### 2.2 Requisitos del PJ para obtener cada tier

El tier de la fruta impone requisitos mínimos en el PJ que la porta. Si un PJ no cumple los requisitos al obtenerla, la tiene pero no puede usar sus capacidades activas hasta alcanzarlos.

| Tier | ESP mínimo (rango efectivo) | Nivel mínimo del PJ |
|------|-----------------------------|---------------------|
| 1 | 1 (D) | 1 |
| 2 | 2 (C) | 2 |
| 3 | 3 (B) | 3 |
| 4 | 4 (A) | 4 |
| 5 | 5 (S) | 5 |

**Implementación:** `cards_assign.php` valida ESP y nivel vía `StatScale::minEspRankForAkumaTier()` y `game_get_character_nivel()` antes de asignar la carta al mazo.

### 2.3 Ejemplos canónicos de referencia para el staff

| Fruta | Clase | Tier sugerido | Justificación |
|-------|-------|---------------|---------------|
| Gomu Gomu (Nika) | Paramecia / Mítica | 5 | Libertad absoluta, despertar universal |
| Gura Gura | Paramecia | 5 | Destrucción a escala mundial |
| Mera Mera | Logia | 4 | Fuego ofensivo potente, inmunidad sólida |
| Hie Hie | Logia | 4 | Hielo, congelación, alcance masivo |
| Suna Suna | Logia | 4 | Desierto, deshidratación, control de zona |
| Yami Yami | Logia especial | 5 | Absorbe poderes, neutraliza Haki |
| Pika Pika | Logia | 5 | Velocidad de luz, inmunidad total |
| Ope Ope | Paramecia | 4 | ROOM, cirugía, trasplante de inmortalidad |
| Hana Hana | Paramecia | 3 | Versatilidad, reconocimiento, utilidad |
| Bara Bara | Paramecia | 2 | Inmunidad cortante, limitaciones físicas |
| Suke Suke | Paramecia | 2 | Invisibilidad, utilidad táctica |
| Tori Tori (Fénix) | Zoan Mítica | 5 | Regeneración, llama azul curativa |
| Inu Inu (Kyubi) | Zoan Mítica | 5 | Ilusiones, poder mítico supremo |
| Ryu Ryu (Pteranodonte) | Zoan Antiguo | 3 | Vuelo, fuerza aumentada, sin poderes especiales |
| Neko Neko (Sable) | Zoan Antiguo | 3 | Fuerza bruta, instinto de caza |
| Ushi Ushi (Jirafa) | Zoan | 2 | Fuerza, cuello extensible, sin poderes especiales |
| Hito Hito (Daibutsu) | Zoan Mítica | 4 | Tamaño colosal, onda de impacto, presión |

---

## 3. Estructura ampliada de la carta Akuma no Mi

La carta base de una Akuma usa el tipo `akuma_no_mi` existente pero con un `effects_json` significativamente más rico que el legacy. El editor staff (`cartas_staff.php`) expone tier, subtipo, identidad y un textarea JSON para la estructura ampliada.

### 3.1 Campos SQL en `game_cards`

| Campo | Valor para Akuma |
|-------|-----------------|
| `card_type` | `akuma_no_mi` |
| `rank` | Igual al tier (D=tier1, C=tier2, B=tier3, A=tier4, S=tier5) |
| `activation` | `pasiva` |
| `dice` | Normalmente vacío |
| `cost_pe` | `0` |
| `tier` | Valor numérico 1–5 |

### 3.2 Estructura completa de `effects_json`

Ver ejemplo Kage Kage en §4. Campos clave: `akuma_type`, `subtipo`, `tier`, `nombre_fruta`, `identidad`, `pasivas`, `transformaciones`, `capacidades_base`, `inmunidades`, `debilidades`, `reglas_especiales`, `potencial_despertar`, `referencia_tecnicas`.

---

## 4. Ejemplo completo — Kage Kage no Mi (Tier 4, Paramecia)

Ver plantilla en el editor staff («Cargar plantilla vacía») y el ejemplo del documento de diseño original. El staff puede pegar el JSON ampliado en el campo «Estructura ampliada».

---

## 5. Biblioteca `game_akuma_no_mi`

Columnas añadidas por `migrate_akuma_tier.php`:

- `tier` TINYINT 1–5
- `subtipo` ENUM(`ninguno`,`antiguo`,`mitico`)

Al crear fruta y carta, ambos `tier` deben ser consistentes. `akuma_catalog.php` expone tier y subtipo en el catálogo AJAX.

---

## 6. Archivos de implementación

| Archivo | Rol |
|---------|-----|
| `inc/akuma_helpers.php` | Normalización effects_json, rank por tier, validación asignación |
| `sql/migrate_akuma_tier.php` | Columnas biblioteca + rango D en cartas |
| `ajax/cards_assign.php` | Requisitos ESP/nivel al asignar |
| `ajax/cards_create.php` / `cards_update.php` | Defaults pasiva, tier, rank |
| `public/cartas_staff.php` + `jscripts/game/cartas_staff.js` | Editor ampliado |
| `src/Shared/StatScale.php` | `minEspRankForAkumaTier`, `minNivelForAkumaTier` |
