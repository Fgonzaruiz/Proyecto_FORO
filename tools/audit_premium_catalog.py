#!/usr/bin/env python3
"""Catálogo PREMIUM + design tokens + mapas de arquitectura para super guías HTML."""
from __future__ import annotations

import re
from dataclasses import dataclass
from pathlib import Path
from typing import Literal

ROOT = Path(__file__).resolve().parent.parent
CSS_PATH = ROOT / "back" / "forum" / "rpg_custom.css"

Severity = Literal["crit", "high", "med", "low"]


@dataclass(frozen=True)
class PremiumRule:
    id: str
    title: str
    body: str
    severity: Severity
    tags: tuple[str, ...]
    block_id: str


def _rule(
    rid: str,
    title: str,
    body: str,
    severity: Severity,
    block_id: str,
    *tags: str,
) -> PremiumRule:
    return PremiumRule(rid, title, body, severity, tags, block_id)


FRONTEND_RULES: tuple[PremiumRule, ...] = (
    # Oro
    _rule("F-ORO-01", "Fuente de verdad = front/templates/**",
          "Nunca editar front/Default-theme.xml a mano como autoría. Es artefacto de php front/update_theme.php.",
          "crit", "premium-oro", "templates"),
    _rule("F-ORO-02", "No sincronizar a ciegas",
          "update_theme.php sobrescribe XML con la fuente. Si la fuente está vieja, pierdes contenido de prod/XML.",
          "crit", "premium-oro", "sync"),
    _rule("F-ORO-03", "diff antes de sync",
          "php front/diff_theme_source.php → exit 0 obligatorio antes de update_theme.php. Exit 1 = alinear fuente primero.",
          "crit", "premium-oro", "sync", "ci"),
    _rule("F-ORO-04", "Commit pareado",
          "Mismo commit: .html fuente en front/templates/ + front/Default-theme.xml generado.",
          "high", "premium-oro", "git"),
    _rule("F-ORO-05", "Validar seguridad tema",
          "php front/validate_theme_security.php exit 0 — sin {$...} mal formados ni $\\s*{ en JS (MyBB rechaza import).",
          "crit", "premium-oro", "sync", "seguridad"),
    # Gates
    _rule("F-GATE-01", "0 style= en templates y game views",
          "Conteo 0 en front/templates/mybb, game/public, game/views. Usar clases en rpg_custom.css.",
          "crit", "premium-gates", "css", "ci"),
    _rule("F-GATE-02", "0 scripts inline",
          "En public/views: solo window.*_CONFIG permitido. Lógica en back/forum/jscripts/game/*.js.",
          "crit", "premium-gates", "js", "ci"),
    _rule("F-GATE-03", "--accent-indigo solo alias",
          "Una sola referencia en :root como alias de --accent-primary. Token canónico: --accent-primary.",
          "high", "premium-gates", "tokens"),
    _rule("F-GATE-04", ".rpg-form-group definición única",
          "Un solo bloque .rpg-form-group { en rpg_custom.css.",
          "med", "premium-gates", "css"),
    _rule("F-GATE-05", "Legacy global.css ≤ 200 líneas",
          "Stub mybb-minimal.css antes del marcador /* RPG Premium Modern Theme */.",
          "high", "premium-gates", "css"),
    _rule("F-GATE-06", "Contraste badges ≥ 4.5:1",
          "rpg-staff-badge, aprobar-count, pill-amber verificados en auditoría.",
          "high", "premium-gates", "a11y"),
    _rule("F-GATE-07", "Validar HTML duplicado en game PHP",
          "Sin IDs duplicados ni bloques HTML repetidos en game/public/*.php y game/views/*.php. "
          "Ejecutar auditoría tras cambios grandes del wizard para detectar duplicaciones de contenido.",
          "high", "premium-gates", "html", "ci"),
    # Legacy
    _rule("F-LEG-01", "Index: tablón premium",
          "Usar #tablon-fecha-widget → calendario.php. NO #game-calendar-bar, #modal_calendar.",
          "crit", "premium-legacy", "legacy", "index"),
    _rule("F-LEG-02", "Index: portada",
          "Usar .roleplay-hero + .rpg-tablon-container. NO solo calendario sin tablón.",
          "high", "premium-legacy", "legacy", "index"),
    _rule("F-LEG-03", "Header: cronología",
          "Usar {$mybb->settings['game_rol_header_html']} (plugin). NO duplicar fecha/calendario en index.",
          "high", "premium-legacy", "legacy", "header"),
    _rule("F-LEG-04", "Header unificado",
          "Fuente única header en front/templates — no header_notification_bell / header_hero_date separados.",
          "med", "premium-legacy", "legacy"),
    _rule("F-LEG-05", "Doble carga CSS",
          "global.css en XML = minimal + rpg_custom embebido. NO link duplicado a rpg_custom.css en headerinclude.",
          "high", "premium-legacy", "css"),
    # Templates
    _rule("F-TPL-01", "postbit showthread",
          "data-uid + data-post-id en .rpg-post-pjcard → JS pasa post_id al endpoint.",
          "high", "premium-templates", "pj", "postbit"),
    _rule("F-TPL-02", "forumdisplay_thread autor",
          "data-uid + data-thread-id en .rpg-thread-author → JS pasa thread_id.",
          "high", "premium-templates", "pj", "forumdisplay"),
    _rule("F-TPL-03", "lastposter sin thread_id",
          "Solo data-uid → fallback personaje activo (imperfecto en hilos viejos; documentado).",
          "med", "premium-templates", "pj"),
    _rule("F-TPL-04", "Controles staff: rpg-modonly",
          'Envolver {$moderationoptions}, {$adminoptions}, botones edit/delete con class="rpg-modonly". Visible solo con body.rpg-staff.',
          "crit", "premium-templates", "mod", "a11y"),
    _rule("F-TPL-05", "Import MyBB",
          "Arreglar fuente en front/templates/ y sincronizar. No parchear solo Default-theme.xml ni panel sin fuente.",
          "high", "premium-templates", "sync"),
    # Imágenes
    _rule("F-IMG-01", "Diseño foro con bburl",
          "En plantillas: {$mybb->settings['bburl']}/images/game/… para assets del servidor.",
          "high", "premium-imagenes", "imagenes"),
    _rule("F-IMG-02", "JSON ya absolutos",
          "Frontend NO debe resolver rutas relativas de avatares; la API devuelve URL completa.",
          "high", "premium-imagenes", "api", "js"),
    # Deploy
    _rule("F-DEP-01", "Sync completo",
          "python front/sync_theme_full.py → templates + global.css en XML.",
          "high", "premium-deploy", "deploy"),
    _rule("F-DEP-02", "Auditoría frontend",
          "python tools/audit_frontend_metrics.py → exit 0 · regenera docs/auditoria-frontend-foro.html.",
          "crit", "premium-deploy", "ci"),
    _rule("F-DEP-03", "Cache busting",
          "Hard refresh; incrementar ?v= en scripts tras cambios breaking (headerinclude).",
          "med", "premium-deploy", "deploy"),
    _rule("F-DEP-04", "Smoke manual",
          "Index tablón, showthread postbit, newthread tabs, personaje.php, cartas_staff, crear_personaje wizard.",
          "high", "premium-deploy", "qa"),
    # JS/CSS
    _rule("F-JS-01", "Sin frameworks en game/",
          "jscripts/game/*.js vanilla. Red: game_network.js (SVG). API: game_api.js gamePostJson.",
          "med", "premium-js-css", "js"),
    _rule("F-JS-02", "Personaje modular",
          "public/personaje.php → personaje_init.php → views/personaje/page.php + partials _tab_*, _modals, _scripts.",
          "med", "premium-js-css", "php"),
    _rule("F-JS-03", "Identidad visual",
          "Tokens pergamin/latón/mugi; Space Grotesk + Plus Jakarta Sans; no sustituir por tema dark genérico.",
          "med", "premium-js-css", "design"),
    # Design (nuevas reglas explícitas para LLM)
    _rule("F-DS-01", "CSS único del tema",
          "Estilos globales en back/forum/rpg_custom.css (embebido vía sync en global.css del XML). No crear hojas paralelas.",
          "crit", "premium-design", "css", "tokens"),
    _rule("F-DS-02", "Usar tokens, no hex sueltos",
          "En CSS nuevo preferir var(--accent-primary), var(--bg-card), var(--text-muted). Colores crew solo para chips/facciones.",
          "high", "premium-design", "tokens"),
    _rule("F-DS-03", "Botones unificados",
          "Editor/plantillas MyBB: .rpg-btn--primary | --secondary | --ghost (doble guión). "
          "Módulo game/staff: secundarios/toolbars → .rpg-system-tab-btn (± --compact); "
          "CTA primarios → .rpg-action-btn.rpg-btn-primary. Evitar estilos ad hoc.",
          "high", "premium-design", "componentes"),
    _rule("F-DS-04", "Tipografía",
          "Títulos: var(--font-heading). Cuerpo: var(--font-body). Cargadas en headerinclude (Google Fonts).",
          "med", "premium-design", "typography"),
    _rule("F-DS-05", "Espaciado grid 8px",
          "Usar --space-1 … --space-6 en layout nuevo; evitar márgenes mágicos dispersos.",
          "med", "premium-design", "layout"),
    _rule("F-DS-06", "Botones legacy prohibidos en game/",
          "No usar rpg-pj-btn, pj-btn-add, rpg-btn-approve/reject/reply, rpg-action-btn+rpg-btn-secondary/sm. "
          "Usar rpg-system-tab-btn (± --compact) y rpg-action-btn rpg-btn-primary. "
          "Gate CI: grep=0 en jscripts/game, game/public, game/views.",
          "high", "premium-design", "componentes", "ci"),
)

BACKEND_RULES: tuple[PremiumRule, ...] = (
    _rule("B-ORO-01", "No lógica pesada en PHP",
          "PHP solo orquesta, valida ligero y renderiza. Mecánicas y datos viven en MySQL (tablas mybb_game_*).",
          "crit", "premium-oro", "php", "arquitectura"),
    _rule("B-ORO-02", "Contratos primero",
          "Todo endpoint o evento nuevo: OpenAPI/JSON Schema en packages/contracts/ + ejemplo en packages/contracts/examples/.",
          "crit", "premium-oro", "contratos", "api"),
    _rule("B-ORO-03", "Base de datos solo vía $db",
          "Operaciones internas con el cliente nativo MyBB ($db). Sin ORMs externos ni APIs de red para mecánicas (D001).",
          "crit", "premium-oro", "seguridad", "datos"),
    _rule("B-ORO-04", "Nombres neutros",
          "Módulo = game/ · plantillas game_* · AJAX en game/ajax/*. Nunca nombres temáticos en rutas públicas.",
          "high", "premium-oro", "convención"),
    _rule("B-ORO-05", "Cierre PHP en plantillas",
          "Cada if/else/foreach mezclado con HTML largo debe cerrar con endif;/endforeach;. Un endif olvidado = HTTP 500 silencioso.",
          "crit", "premium-oro", "plantillas", "php"),
    _rule("B-EP-01", "Bootstrap obligatorio",
          "HTML: back/forum/game/public/*.php · JSON: back/forum/game/ajax/*.php — siempre require_once bootstrap.php.",
          "high", "premium-entrypoints", "bootstrap"),
    _rule("B-EP-02", "Envelope estándar",
          "Éxito: { ok: true, data, error: null, meta }. Error: { ok: false, data: null, error: { code, message }, meta }.",
          "crit", "premium-entrypoints", "api", "json"),
    _rule("B-EP-03", "POST mutadores con GameAjax",
          "requireLogin, requirePost, postJson, requireCsrf en mutaciones. Cliente: gamePostJson + window.GAME_CSRF en headerinclude.",
          "crit", "premium-entrypoints", "csrf", "seguridad"),
    _rule("B-EP-04", "UseCase / Service en Application/",
          "Lógica de negocio en src/Application/UseCases/ o Services/, no acumulada en entrypoints ajax/public.",
          "high", "premium-entrypoints", "arquitectura"),
    _rule("B-EP-05", "Checklist nuevo endpoint",
          "1) entrypoint ajax|public 2) UseCase/Service 3) $db seguro 4) contrato OpenAPI + ejemplo 5) template front si aplica.",
          "high", "premium-entrypoints", "checklist"),
    _rule("B-SEC-01", "Sin GAME_DEBUG en prod",
          "No definir GAME_DEBUG ni GAME_ALLOW_MAINTENANCE en producción salvo ventana de migración controlada.",
          "crit", "premium-seguridad", "prod"),
    _rule("B-SEC-02", "display_errors Off",
          "php.ini con display_errors=Off en producción. Revisar bootstrap.php: nunca forzar errores visibles.",
          "crit", "premium-seguridad", "prod"),
    _rule("B-SEC-03", "Bloquear scripts sensibles",
          "404 en nginx/Apache para: clean_db.php, install_db.php, mock_data.php, lint.php, create_test_cards.php.",
          "crit", "premium-seguridad", "prod", "nginx"),
    _rule("B-SEC-04", "CSRF en POST",
          "POST mutador sin token → 403. Verificar con smoke test tras cada deploy.",
          "crit", "premium-seguridad", "csrf"),
    _rule("B-SEC-05", "Plugin activo",
          "Game Post Character Linker activo en Admin CP. Posts nuevos deben crear fila en mybb_game_post_characters.",
          "high", "premium-seguridad", "plugin"),
    _rule("B-DAT-01", "Orden núcleo migraciones",
          "migrate_pj_system → migrate_notifications → migrate_thread_meta → migrate_staff_levels → migrate_aprobar_pj.",
          "crit", "premium-datos", "sql"),
    _rule("B-DAT-02", "Prod una vez: thread_pj_state",
          "Ejecutar migrate_thread_pj_state.php en prod si tablón/hilos usan PV/PE por hilo.",
          "high", "premium-datos", "sql", "prod"),
    _rule("B-DAT-03", "post_modifiers antes de PV/PE en post",
          "migrate_post_modifiers.php antes de usar modificadores; el plugin no hace ALTER en runtime.",
          "high", "premium-datos", "sql"),
    _rule("B-DAT-04", "game_post_characters",
          "Posts: post_id sin thread_id. Hilos nuevos: post_id + thread_id. get_active_pj_for_user: sin fallback en post_id/thread_id.",
          "high", "premium-datos", "pj", "plugin"),
    _rule("B-DAT-05", "Conteo por personaje (D003)",
          "postnum/threadnum en game_personajes; hooks game_postcharacter actualizan contadores, no la cuenta MyBB.",
          "med", "premium-datos", "pj"),
    _rule("B-DAT-06", "cronologia_json",
          "Estructura diario + relaciones + groups en columna JSON; no duplicar en tablas ad hoc sin ADR.",
          "med", "premium-datos", "pj"),
    _rule("B-IMG-01", "Avatares PJ externos",
          "URLs absolutas https://… Nunca rutas relativas del servidor para avatares de personaje.",
          "high", "premium-imagenes", "imagenes"),
    _rule("B-IMG-02", "Assets foro con bburl",
          "Banners/fondos: rutas servidor + resolve_img() o pj_img_url() en PHP.",
          "med", "premium-imagenes", "imagenes"),
    _rule("B-CTR-01", "Cada ajax documentado",
          "Todo *.php en game/ajax/ debe tener path en packages/contracts/openapi/*.yaml (salvo LEGACY_501 documentados).",
          "crit", "premium-contratos", "contratos", "ci"),
    _rule("B-CTR-02", "Ejemplos de payload",
          "Par request/response en packages/contracts/examples/ para endpoints nuevos o cambios de schema.",
          "high", "premium-contratos", "contratos"),
    _rule("B-CTR-03", "Post-cambio backend",
          "Tras cambios PHP/SQL: python tools/audit_backend_contracts.py (exit 0) + smoke ping/notifications/CSRF.",
          "high", "premium-contratos", "ci", "deploy"),
    # Arquitectura (nuevas)
    _rule("B-ARCH-01", "Capas respetadas",
          "ajax/public → GameAjax/JsonResponder → Service|UseCase → Repository|$db. No saltar capas sin motivo documentado.",
          "high", "premium-arquitectura", "arquitectura"),
    _rule("B-ARCH-02", "Autoload game/src",
          "Clases en namespace Game\\ vía back/forum/game/src/autoload.php (cargado en bootstrap.php).",
          "med", "premium-arquitectura", "php"),
    _rule("B-ARCH-03", "Plugin vs módulo",
          "Hooks MyBB (posts, contadores, fecha header) = plugin game_postcharacter. UX páginas/AJAX = módulo game/.",
          "high", "premium-arquitectura", "plugin"),
)

FRONTEND_BLOCKS: tuple[tuple[str, str, str], ...] = (
    ("premium-oro", "Reglas de oro frontend", "Tema MyBB + módulo visual game/. Fuente de verdad siempre en front/templates/."),
    ("premium-gates", "Gates automáticos (audit_frontend_metrics.py)", "Deben pasar en CI y antes de merge de cambios grandes de UI."),
    ("premium-legacy", "Prohibido / legacy", "No reintroducir al sincronizar ni copiar de commits viejos."),
    ("premium-templates", "Plantillas MyBB y data-attributes", "Integración personaje en foro."),
    ("premium-imagenes", "Imágenes en frontend", "El servidor resuelve; el browser no adivina rutas."),
    ("premium-deploy", "Checklist post-cambio y deploy", "docs/runbooks/frontend-deploy.md"),
    ("premium-js-css", "JS y CSS del módulo", "Sin frameworks; convenciones del repo."),
    ("premium-design", "Sistema de diseño (tokens y componentes)", "Identidad Cozy Pergamino — ver sección Design System en esta guía."),
)

BACKEND_BLOCKS: tuple[tuple[str, str, str], ...] = (
    ("premium-oro", "Reglas de oro", "Siempre. Sin excepción en cambios al módulo game/ o plugin."),
    ("premium-entrypoints", "Entry points y envelope JSON", "Convención de arranque y respuesta API."),
    ("premium-seguridad", "Seguridad y producción", "docs/runbooks/backend-deploy.md"),
    ("premium-datos", "Datos, migraciones y personaje-post", "Orden SQL y linkage post↔PJ."),
    ("premium-imagenes", "Imágenes y URLs", "Resolución en servidor; el cliente recibe URLs absolutas."),
    ("premium-contratos", "Contratos y CI", "Gate: python tools/audit_backend_contracts.py → exit 0."),
    ("premium-arquitectura", "Capas PHP del módulo game/", "Services, Repositories, UseCases — ver sección Arquitectura."),
)

# Tokens semánticos clave (documentación; :root completo se extrae del CSS)
FRONTEND_TOKEN_GROUPS: tuple[tuple[str, tuple[tuple[str, str, str], ...]], ...] = (
    ("Identidad base (pergamino)", (
        ("--color-pergamino", "#F4EBD0", "Fondo principal claro"),
        ("--color-tinta", "#2B221A", "Texto principal"),
        ("--color-laton", "#B89742", "Bordes / oro viejo"),
        ("--color-mugi", "#D32F2F", "Acento selección (→ --accent-primary)"),
    )),
    ("Superficies y texto", (
        ("--bg-main", "var(--color-pergamino)", "Fondo body"),
        ("--bg-card", "rgba(240,230,206,0.82)", "Tarjetas glass"),
        ("--text-primary", "var(--color-tinta)", "Títulos"),
        ("--text-muted", "#80715e", "Meta / hints"),
        ("--glass-bg", "rgba(244,235,208,0.92)", "Paneles flotantes"),
    )),
    ("Acentos semánticos", (
        ("--accent-primary", "var(--color-mugi)", "CTA, links activos"),
        ("--accent-purple", "var(--color-robin-purpura)", "Staff / secundario"),
        ("--accent-blue", "var(--color-franky-azul)", "Enlaces foro"),
        ("--accent-red / --accent-green", "estado error/ok", "Alertas"),
    )),
    ("Tipografía y espaciado", (
        ("--font-heading", "Space Grotesk", "h1–h3, badges"),
        ("--font-body", "Plus Jakarta Sans", "cuerpo, tablas"),
        ("--text-sm … --text-2xl", "13px–32px", "Escala modular"),
        ("--space-1 … --space-6", "8px–48px", "Grid 8px"),
        ("--radius-sm … --radius-xl", "6px–24px", "Bordes"),
    )),
    ("Botones (tokens)", (
        ("--btn-primary-bg", "gradient rojo", ".rpg-btn--primary"),
        ("--btn-staff-bg", "gradient púrpura", ".rpg-btn--staff"),
        ("--btn-secondary-bg", "var(--bg-card)", ".rpg-btn--secondary"),
    )),
)

FRONTEND_COMPONENTS: tuple[tuple[str, str, str], ...] = (
    (".rpg-btn--primary", "CTA editor/plantillas", "Guardar post, enviar formulario MyBB"),
    (".rpg-btn--secondary", "Secundario editor", "Cancelar, volver en plantillas"),
    (".rpg-btn--ghost", "Terciaria editor", "Toolbar BBCODE, filtros"),
    (".rpg-system-tab-btn", "Acción secundaria game/staff", "Cancelar, quitar, filtros, tabs"),
    (".rpg-system-tab-btn--compact", "Tab btn compacto", "Listas, filas de deck, toolbars densas"),
    (".rpg-action-btn.rpg-btn-primary", "CTA primario game/staff", "Guardar, Nueva Carta, confirmar"),
    (".rpg-staff-btn-danger", "Destructivo tab-btn", "Eliminar en contexto staff"),
    (".rpg-form-group", "Campo formulario", "Única definición en CSS — gate CI"),
    (".rpg-modonly", "Controles mod", "Oculto salvo body.rpg-staff (rpg_custom.js)"),
    (".rpg-post-pjcard", "Tarjeta PJ en post", "data-uid + data-post-id"),
    (".rpg-thread-author", "Autor en listado", "data-uid + data-thread-id"),
    (".roleplay-hero", "Hero index", "Portada con identidad RPG"),
    (".rpg-tablon-container", "Tablón index", "Anuncios / fecha on-rol"),
    (".rpg-staff-badge", "Badge staff", "Contraste gate ≥4.5:1"),
)

FRONTEND_FILE_MAP: tuple[tuple[str, str], ...] = (
    ("back/forum/rpg_custom.css", "CSS único del tema (~9k líneas). Fuente visual; se embebe en global.css vía sync."),
    ("front/templates/mybb/", "Plantillas MyBB (header, index, postbit, forumdisplay, …)."),
    ("front/templates/game/", "Plantillas módulo game_* (ficha, inventario, staff)."),
    ("front/Default-theme.xml", "ARTEFACTO generado — no editar a mano."),
    ("front/sync_theme_full.py", "Sync templates + CSS → XML."),
    ("back/forum/jscripts/game_api.js", "gamePostJson, CSRF, envelope cliente."),
    ("back/forum/rpg_custom.js", "PJ activo, postbit, staff body class, foro."),
    ("back/forum/jscripts/game/*.js", "Un archivo por página/feature (personaje, cartas, calendario, …)."),
    ("back/forum/game/public/", "Entry HTML PHP (personaje, crear_personaje, calendario, …)."),
    ("back/forum/game/views/", "Partials PHP (personaje/_tab_*.php, …)."),
)

BACKEND_LAYERS: tuple[tuple[str, str, str], ...] = (
    ("1 · Cliente", "jscripts/game_api.js", "gamePostJson(url, body) + my_post_key / GAME_CSRF"),
    ("2 · Entry", "game/ajax/*.php · game/public/*.php", "bootstrap.php → guards → delega"),
    ("3 · HTTP", "src/Http/GameAjax.php", "requireLogin, requirePost, requireCsrf, json envelope"),
    ("4 · API", "src/Presentation/Api/JsonResponder.php", "ok() / fail() envelope estándar"),
    ("5 · Aplicación", "src/Application/Services/* · UseCases/*", "Reglas de negocio, validación, orquestación"),
    ("6 · Persistencia", "src/Infrastructure/Persistence/*Repository.php", "SQL vía global $db + TABLE_PREFIX"),
    ("7 · MySQL", "mybb_game_*", "Datos RPG; hooks plugin para posts/hilos"),
    ("Plugin", "inc/plugins/game_postcharacter.php", "Hooks MyBB: post_characters, contadores, header fecha"),
)

BACKEND_SERVICES: tuple[tuple[str, str], ...] = (
    ("CharacterSaveService", "Validar y armar payload guardado de ficha (save_personaje)."),
    ("CharacterSheetLoader", "Cargar ficha completa para vistas/API."),
    ("CharacterProgression", "XP, niveles, atributos comprables."),
    ("LinajeValidator", "Reglas de linaje/raza en creación/edición."),
    ("NotificationService", "CRUD notificaciones in-app."),
    ("AdminRequestService", "Peticiones admin genéricas (zona staff)."),
)

BACKEND_REPOSITORIES: tuple[tuple[str, str], ...] = (
    ("PersonajeRepository", "Consultas game_personajes, activo, listados."),
    ("BusquedasRepository", "Misiones/búsquedas staff y jugadores."),
)

BACKEND_USECASES: tuple[tuple[str, str], ...] = (
    ("CharacterSheetLoader", "Ficha personaje — personaje.php + save_personaje."),
    ("inventory_get / inventory_toggle", "Equipamiento en ficha — carga, compañero, barco."),
    ("cards_play / oracles_for_post", "Mecánicas en post — cartas y oráculos."),
)

BACKEND_TABLES: tuple[tuple[str, str], ...] = (
    ("game_personajes", "Ficha PJ: stats, cronologia_json, postnum, is_staff, …"),
    ("game_post_characters", "Vínculo post_id/thread_id → character_id"),
    ("game_notifications", "Campana header — notificaciones por usuario"),
    ("game_cards*", "Sistema cartas / peticiones / mazo"),
    ("game_busquedas*", "Misiones on-rol (staff + jugadores)"),
)

BACKEND_FILE_MAP: tuple[tuple[str, str], ...] = (
    ("back/forum/game/bootstrap.php", "IN_MYBB, global.php, autoload, helpers resolve_img."),
    ("back/forum/game/ajax/", "80 endpoints JSON — un archivo = una ruta."),
    ("back/forum/game/public/", "Páginas HTML renderizadas con templates game_*."),
    ("back/forum/game/src/", "Capas Application / Infrastructure / Http / Presentation."),
    ("back/forum/game/sql/", "Migraciones PHP (sin versioning formal)."),
    ("packages/contracts/openapi/", "OpenAPI por dominio (cards, personajes, …)."),
    ("packages/contracts/examples/", "Payloads ejemplo para validación y LLM."),
    ("inc/plugins/game_postcharacter.php", "Plugin hooks MyBB (producción obligatorio)."),
)

JS_FILE_MAP: tuple[tuple[str, str], ...] = (
    ("game_api.js", "Cliente API global (raíz jscripts/)."),
    ("rpg_custom.js", "Foro: PJ en posts, staff class, utilidades globales."),
    ("personaje_page.js", "Ficha personaje — tabs, modales, cronología."),
    ("game_network.js", "Grafo SVG relaciones (cronologia_json)."),
    ("crear_personaje.js", "Wizard creación PJ."),
    ("foro_deck_ui.js / cartas_staff.js", "Sistema cartas y mazo en posts."),
    ("calendario.js", "Calendario on-rol (página dedicada)."),
    ("notificaciones.js", "Campana y lista notificaciones."),
)


def extract_css_tokens(css_path: Path = CSS_PATH) -> list[tuple[str, str, str]]:
    """Extrae --var: valor del bloque :root en rpg_custom.css."""
    if not css_path.is_file():
        return []
    text = css_path.read_text(encoding="utf-8", errors="replace")
    m = re.search(r":root\s*\{([^}]+)\}", text, re.S)
    if not m:
        return []
    block = m.group(1)
    rows: list[tuple[str, str, str]] = []
    for line in block.splitlines():
        line = line.strip()
        if not line.startswith("--") or ":" not in line:
            continue
        name, _, rest = line.partition(":")
        val = rest.split("/*")[0].strip().rstrip(";").strip()
        comment = ""
        if "/*" in rest:
            cm = re.search(r"/\*\s*(.+?)\s*\*/", rest)
            if cm:
                comment = cm.group(1)
        rows.append((name.strip(), val, comment))
    return rows


def rules_by_block(rules: tuple[PremiumRule, ...], block_id: str) -> list[PremiumRule]:
    return [r for r in rules if r.block_id == block_id]
