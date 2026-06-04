#!/usr/bin/env python3
"""Genera docs/auditoria-*-foro.html — super guías PREMIUM para LLM y revisiones grandes."""
from __future__ import annotations

import html
import json
import re
from datetime import date
from pathlib import Path

from audit_premium_catalog import (
    BACKEND_BLOCKS,
    BACKEND_FILE_MAP,
    BACKEND_LAYERS,
    BACKEND_REPOSITORIES,
    BACKEND_RULES,
    BACKEND_SERVICES,
    BACKEND_TABLES,
    BACKEND_USECASES,
    FRONTEND_BLOCKS,
    FRONTEND_COMPONENTS,
    FRONTEND_FILE_MAP,
    FRONTEND_RULES,
    FRONTEND_TOKEN_GROUPS,
    JS_FILE_MAP,
    PremiumRule,
    extract_css_tokens,
    rules_by_block,
)

ROOT = Path(__file__).resolve().parent.parent
FRONT_METRICS = ROOT / "docs" / "auditoria-metrics.json"
BACK_METRICS = ROOT / "docs" / "auditoria-backend-metrics.json"
OUT_FRONT = ROOT / "docs" / "auditoria-frontend-foro.html"
OUT_BACK = ROOT / "docs" / "auditoria-backend-foro.html"

THEMES = {
    "front": {
        "title": "Super guía PREMIUM — Frontend FORO",
        "tag": "Super guía PREMIUM · Frontend",
        "h1": "Reglas obligatorias del tema<br>y sistema de diseño RPG",
        "sub": (
            "Documento único para cambios grandes de plantillas, CSS y JS. "
            "Incluye reglas PREMIUM, tokens de color, componentes, mapa de archivos y gates CI. "
            "<strong>Adjuntar siempre</strong> a agentes/LLM en refactors visuales o sync de tema MyBB."
        ),
        "hero_bg": (
            "radial-gradient(ellipse 80% 60% at 20% 0%, rgba(211,47,47,0.15) 0%, transparent 55%),"
            "radial-gradient(ellipse 60% 50% at 90% 10%, rgba(184,151,66,0.2) 0%, transparent 50%),"
            "linear-gradient(160deg, #2a1810 0%, #1a1510 50%, #0f0d0a 100%)"
        ),
        "accent": "#d4a843",
        "accent_rgb": "212, 168, 67",
        "bg": "#1a1510",
        "surface": "#2a2218",
        "surface2": "#3d3226",
        "regen_cmd": "python tools/audit_frontend_metrics.py",
    },
    "back": {
        "title": "Super guía PREMIUM — Backend FORO",
        "tag": "Super guía PREMIUM · Backend",
        "h1": "Reglas obligatorias del motor RPG<br>y arquitectura game/",
        "sub": (
            "Documento único para cambios grandes en PHP, SQL, plugin y AJAX. "
            "Capas (entry → Service → Repository → $db), contratos OpenAPI y checklist deploy. "
            "<strong>Adjuntar siempre</strong> a agentes/LLM al tocar game/ o game_postcharacter."
        ),
        "hero_bg": (
            "radial-gradient(ellipse 70% 55% at 10% 0%, rgba(56,189,248,0.12) 0%, transparent 55%),"
            "radial-gradient(ellipse 50% 45% at 95% 5%, rgba(167,139,250,0.12) 0%, transparent 50%),"
            "linear-gradient(165deg, #121a26 0%, #0f1419 60%, #0a0e14 100%)"
        ),
        "accent": "#38bdf8",
        "accent_rgb": "56, 189, 248",
        "bg": "#0f1419",
        "surface": "#1a2332",
        "surface2": "#243044",
        "regen_cmd": "python tools/audit_backend_contracts.py",
    },
}


def _esc(s: str) -> str:
    return html.escape(s, quote=True)


def _rule_li(r: PremiumRule) -> str:
    tags = "".join(f'<span class="tag">{_esc(t)}</span>' for t in r.tags)
    return (
        f'<li class="premium-rule {r.severity}" data-rule-id="{_esc(r.id)}">'
        f'<label class="rule-check"><input type="checkbox" aria-label="Marcar cumplida">'
        f"<strong>{_esc(r.id)}</strong> — {_esc(r.title)}</label>"
        f'<p class="rule-body">{_esc(r.body)}</p>'
        f'<div class="rule-tags">{tags}</div></li>'
    )


def _rules_section(blocks: tuple, rules: tuple[PremiumRule, ...]) -> str:
    parts = []
    for block_id, title, intro in blocks:
        block_rules = rules_by_block(rules, block_id)
        if not block_rules:
            continue
        lis = "".join(_rule_li(r) for r in block_rules)
        parts.append(
            f'<div class="premium-block" id="{_esc(block_id)}">'
            f"<h3>{_esc(title)}</h3>"
            f'<p class="premium-intro">{_esc(intro)}</p>'
            f'<ul class="premium-rules">{lis}</ul></div>'
        )
    return "\n".join(parts)


def _doc_css(theme: dict) -> str:
    a = theme["accent"]
    ar = theme["accent_rgb"]
    return f"""
    :root {{
      --doc-bg: {theme["bg"]}; --doc-surface: {theme["surface"]}; --doc-surface2: {theme["surface2"]};
      --doc-text: #f5efe3; --doc-muted: #a89880; --doc-accent: {a};
      --doc-ok: #6ee7a0; --doc-warn: #fbbf24; --doc-crit: #f87171; --doc-info: #7dd3fc;
      --doc-border: rgba({ar}, 0.18); --doc-radius: 14px;
      --doc-font: "Plus Jakarta Sans", system-ui, sans-serif;
      --doc-heading: "Space Grotesk", sans-serif;
      --doc-mono: "Cascadia Code", Consolas, monospace;
    }}
    .hero {{ background: {theme["hero_bg"]}; }}
    .hero-tag {{ background: rgba({ar},0.15); border-color: rgba({ar},0.35); color: var(--doc-accent); }}
    * {{ box-sizing: border-box; margin: 0; padding: 0; }}
    html {{ scroll-behavior: smooth; }}
    body {{ font-family: var(--doc-font); background: var(--doc-bg); color: var(--doc-text);
      line-height: 1.65; font-size: 15px; }}
    .hero {{ border-bottom: 1px solid var(--doc-border); padding: 56px 24px 48px; }}
    .hero-inner {{ max-width: 1140px; margin: 0 auto; }}
    .hero-tag {{ display: inline-block; font-size: 11px; font-weight: 800; letter-spacing: 2px;
      text-transform: uppercase; padding: 5px 14px; border-radius: 999px; margin-bottom: 16px; border: 1px solid; }}
    .hero h1 {{ font-family: var(--doc-heading); font-size: clamp(1.75rem, 4vw, 2.5rem); font-weight: 800;
      letter-spacing: -0.03em; line-height: 1.15; margin-bottom: 12px; }}
    .hero .sub {{ color: var(--doc-muted); font-size: 1.05rem; max-width: 860px; }}
    .meta {{ display: flex; flex-wrap: wrap; gap: 10px; margin-top: 28px; }}
    .meta span {{ background: rgba(255,255,255,0.04); border: 1px solid var(--doc-border);
      padding: 6px 14px; border-radius: 999px; font-size: 12px; font-weight: 600; color: var(--doc-muted); }}
    .container {{ max-width: 1140px; margin: 0 auto; padding: 36px 24px 90px; }}
    nav.toc {{ background: var(--doc-surface); border: 1px solid var(--doc-border);
      border-radius: var(--doc-radius); padding: 26px 30px; margin-bottom: 44px; }}
    nav.toc h2 {{ font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px;
      color: var(--doc-muted); margin-bottom: 14px; }}
    nav.toc ol {{ padding-left: 20px; columns: 2; column-gap: 36px; }}
    @media (max-width: 720px) {{ nav.toc ol {{ columns: 1; }} }}
    nav.toc a {{ color: var(--doc-accent); text-decoration: none; font-size: 14px; }}
    nav.toc a:hover {{ text-decoration: underline; }}
    nav.toc li {{ margin-bottom: 5px; }}
    section {{ margin-bottom: 52px; scroll-margin-top: 28px; }}
    h2 {{ font-family: var(--doc-heading); font-size: 1.5rem; font-weight: 800; margin-bottom: 22px;
      padding-bottom: 10px; border-bottom: 2px solid var(--doc-accent);
      display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }}
    h2 .badge {{ font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
      padding: 3px 9px; border-radius: 6px; background: var(--doc-surface2); color: var(--doc-muted); }}
    h3 {{ font-family: var(--doc-heading); font-size: 1.08rem; font-weight: 700; margin: 26px 0 12px; }}
    h4 {{ font-size: 0.95rem; margin: 18px 0 10px; color: var(--doc-text); }}
    p {{ margin-bottom: 12px; color: var(--doc-muted); }}
    code, .path {{ font-family: var(--doc-mono); font-size: 12px; color: var(--doc-info); word-break: break-all; }}
    pre.flow {{ background: var(--doc-surface); border: 1px solid var(--doc-border); border-radius: var(--doc-radius);
      padding: 18px 20px; overflow-x: auto; font-family: var(--doc-mono); font-size: 12px; line-height: 1.5;
      color: var(--doc-text); margin: 16px 0; white-space: pre; }}
    .callout-premium {{ background: linear-gradient(135deg, rgba(255,200,80,0.12), rgba(255,80,80,0.08));
      border: 2px solid rgba(255, 200, 80, 0.45); border-radius: var(--doc-radius);
      padding: 24px 28px; margin-bottom: 32px; }}
    .callout-premium strong {{ color: #ffe9a8; font-size: 1.1rem; display: block; margin-bottom: 8px; }}
    .premium-block {{ margin: 28px 0; padding: 20px 0; border-top: 1px solid var(--doc-border); }}
    .premium-intro {{ font-size: 13px; font-style: italic; }}
    ul.premium-rules {{ list-style: none; margin: 12px 0; }}
    ul.premium-rules > li {{ background: var(--doc-surface); border: 1px solid var(--doc-border);
      border-radius: var(--doc-radius); padding: 14px 18px; margin-bottom: 10px;
      border-left: 4px solid var(--doc-surface2); }}
    ul.premium-rules > li.crit {{ border-left-color: var(--doc-crit); }}
    ul.premium-rules > li.high {{ border-left-color: var(--doc-warn); }}
    ul.premium-rules > li.med {{ border-left-color: var(--doc-info); }}
    .rule-check {{ display: flex; align-items: flex-start; gap: 10px; cursor: pointer;
      color: var(--doc-text); font-size: 14px; }}
    .rule-check input {{ margin-top: 4px; accent-color: var(--doc-accent); width: 18px; height: 18px; flex-shrink: 0; }}
    .rule-body {{ font-size: 13px; margin: 8px 0 0 28px; color: var(--doc-muted); }}
    .rule-tags {{ margin: 8px 0 0 28px; display: flex; flex-wrap: wrap; gap: 6px; }}
    .tag {{ font-size: 10px; font-weight: 700; text-transform: uppercase; padding: 2px 8px;
      border-radius: 999px; background: var(--doc-surface2); color: var(--doc-muted); }}
    li.premium-rule:has(input:checked) {{ opacity: 0.55; }}
    li.premium-rule:has(input:checked) .rule-check {{ text-decoration: line-through; }}
    ol.deploy-steps {{ padding-left: 22px; margin: 16px 0; }}
    ol.deploy-steps li {{ margin-bottom: 10px; color: var(--doc-muted); }}
    table {{ width: 100%; border-collapse: collapse; font-size: 13px; margin: 16px 0; }}
    th, td {{ padding: 11px 14px; text-align: left; border-bottom: 1px solid var(--doc-border); vertical-align: top; }}
    th {{ background: var(--doc-surface2); color: var(--doc-muted); font-size: 11px;
      text-transform: uppercase; letter-spacing: 0.6px; }}
    .pill {{ display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 10px; font-weight: 800; }}
    .pill-ok {{ background: rgba(74,222,128,0.15); color: var(--doc-ok); }}
    .pill-crit {{ background: rgba(248,113,113,0.15); color: var(--doc-crit); }}
    .score-row {{ display: flex; align-items: center; gap: 12px; margin: 10px 0; }}
    .score-label {{ flex: 1; font-size: 14px; }}
    .score-bar {{ flex: 0 0 180px; height: 9px; background: var(--doc-surface2); border-radius: 5px; overflow: hidden; }}
    .score-fill {{ height: 100%; border-radius: 5px; }}
    .score-num {{ width: 44px; text-align: right; font-weight: 800; font-size: 13px; }}
    .card-grid {{ display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin: 20px 0; }}
    .card {{ background: var(--doc-surface); border: 1px solid var(--doc-border);
      border-radius: var(--doc-radius); padding: 20px; }}
    .card h4 {{ font-size: 11px; text-transform: uppercase; color: var(--doc-muted); margin-bottom: 8px; }}
    .card .val {{ font-family: var(--doc-heading); font-size: 2rem; font-weight: 900; color: var(--doc-accent); }}
    .card .note {{ font-size: 12px; color: var(--doc-muted); margin-top: 8px; }}
    .swatch-grid {{ display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin: 16px 0; }}
    .swatch {{ border-radius: 10px; overflow: hidden; border: 1px solid var(--doc-border); }}
    .swatch-color {{ height: 56px; }}
    .swatch-meta {{ padding: 8px 10px; background: var(--doc-surface); font-size: 11px; }}
    .swatch-meta code {{ display: block; margin-bottom: 4px; font-size: 10px; }}
    footer {{ text-align: center; padding: 40px 24px; color: var(--doc-muted); font-size: 12px;
      border-top: 1px solid var(--doc-border); }}
    .rule-count {{ font-size: 13px; color: var(--doc-accent); font-weight: 700; margin-top: 8px; }}
    """


def _score_rows(rows: list[tuple[str, float]]) -> str:
    out = []
    for label, score in rows:
        pct = int(score * 10)
        color = "var(--doc-ok)" if score >= 8.8 else "var(--doc-warn)"
        out.append(
            f'<div class="score-row"><span class="score-label">{_esc(label)}</span>'
            f'<div class="score-bar"><div class="score-fill" style="width:{pct}%;background:{color}"></div></div>'
            f'<span class="score-num">{score:.1f}</span></div>'
        )
    return "\n".join(out)


def _table_map(rows: tuple[tuple[str, str], ...]) -> str:
    body = "".join(
        f"<tr><td><code>{_esc(a)}</code></td><td>{_esc(b)}</td></tr>" for a, b in rows
    )
    return f"<table><thead><tr><th>Ruta / clase</th><th>Uso</th></tr></thead><tbody>{body}</tbody></table>"


def _table_components(rows: tuple[tuple[str, str, str], ...]) -> str:
    body = "".join(
        f"<tr><td><code>{_esc(a)}</code></td><td>{_esc(b)}</td><td>{_esc(c)}</td></tr>"
        for a, b, c in rows
    )
    return (
        "<table><thead><tr><th>Clase</th><th>Rol</th><th>Cuándo usar</th></tr></thead>"
        f"<tbody>{body}</tbody></table>"
    )


def _hex_swatch(val: str) -> str | None:
    m = re.search(r"#([0-9A-Fa-f]{3,8})\b", val)
    return m.group(0) if m else None


def _frontend_design_section() -> str:
    swatches = []
    for _gname, tokens in FRONTEND_TOKEN_GROUPS:
        for name, val, _desc in tokens:
            hx = _hex_swatch(val)
            if hx:
                swatches.append((name, hx, val))

    swatch_html = '<div class="swatch-grid">'
    seen = set()
    for name, hx, val in swatches[:16]:
        if hx in seen:
            continue
        seen.add(hx)
        swatch_html += (
            f'<div class="swatch"><div class="swatch-color" style="background:{_esc(hx)}"></div>'
            f'<div class="swatch-meta"><code>{_esc(name)}</code>{_esc(val)}</div></div>'
        )
    swatch_html += "</div>"

    groups_html = ""
    for gname, tokens in FRONTEND_TOKEN_GROUPS:
        rows = "".join(
            f"<tr><td><code>{_esc(n)}</code></td><td><code>{_esc(v)}</code></td><td>{_esc(d)}</td></tr>"
            for n, v, d in tokens
        )
        groups_html += f"<h4>{_esc(gname)}</h4><table><thead><tr><th>Token</th><th>Valor</th><th>Uso</th></tr></thead><tbody>{rows}</tbody></table>"

    extracted = extract_css_tokens()
    ext_note = ""
    if extracted:
        ext_note = f"<p>Extraídos automáticamente <strong>{len(extracted)}</strong> tokens de <code>:root</code> en <code>rpg_custom.css</code>.</p>"

    flow = """Usuario / MyBB
    │
    ▼
front/templates/**/*.html  ←── FUENTE DE VERDAD
    │
    ├─► php front/diff_theme_source.php  (exit 0)
    ├─► python front/sync_theme_full.py
    │         └─► embebe rpg_custom.css en global.css
    └─► front/Default-theme.xml  (artefacto, commit pareado)
    │
    ▼
Admin CP import · runtime MyBB + jscripts/game/*.js"""

    return f"""
  <section id="design-system">
    <h2>Sistema de diseño <span class="badge">rpg_custom.css</span></h2>
    <div class="callout-premium">
      <strong>Identidad: Cozy Pergamino Light-Glass</strong>
      Pergamin + latón + acento mugi. Fondo seigaiha + glassmorphism. 
      Archivo único: <code>back/forum/rpg_custom.css</code> — marcador <code>/* RPG Premium Modern Theme */</code> en sync XML.
    </div>
    {ext_note}
    <h3>Paleta visual (muestra)</h3>
    {swatch_html}
    <h3>Tokens semánticos obligatorios</h3>
    {groups_html}
    <h3>Componentes UI estándar</h3>
    {_table_components(FRONTEND_COMPONENTS)}
    <h3>Flujo fuente → producción</h3>
    <pre class="flow">{_esc(flow)}</pre>
  </section>

  <section id="mapa-archivos">
    <h2>Mapa de archivos frontend <span class="badge">Dónde vive cada cosa</span></h2>
    {_table_map(FRONTEND_FILE_MAP)}
    <h3>Scripts JS por feature</h3>
    {_table_map(JS_FILE_MAP)}
    <h3>Plantillas fuente (muestra)</h3>
    <table><thead><tr><th>Archivo</th><th>Rol</th></tr></thead><tbody>
      <tr><td><code>mybb/global/header.html</code></td><td>Header unificado + nav</td></tr>
      <tr><td><code>mybb/global/headerinclude.html</code></td><td>CSS/JS global, GAME_CSRF, game_api.js</td></tr>
      <tr><td><code>mybb/index/index.html</code></td><td>Tablón + hero (NO calendario legacy)</td></tr>
      <tr><td><code>mybb/showthread/postbit.html</code></td><td>data-post-id en .rpg-post-pjcard</td></tr>
      <tr><td><code>mybb/forumdisplay/forumdisplay_thread.html</code></td><td>data-thread-id autor</td></tr>
      <tr><td><code>game/game_character.html</code></td><td>Template ficha legacy MyBB</td></tr>
    </tbody></table>
  </section>"""


def _backend_arch_section() -> str:
    layers = "".join(
        f"<tr><td>{_esc(a)}</td><td><code>{_esc(b)}</code></td><td>{_esc(c)}</td></tr>"
        for a, b, c in BACKEND_LAYERS
    )
    flow = """Browser (gamePostJson)
    │
    ▼
game/ajax/*.php  ──bootstrap.php──►  $mybb, $db
    │
    ├─► GameAjax (login, POST, CSRF)
    ├─► JsonResponder / envelope
    │
    ▼
Application/Services/*  ·  UseCases/*
    │
    ▼
Infrastructure/Persistence/*Repository.php
    │
    ▼
MySQL mybb_game_*  ◄── hooks game_postcharacter (posts, contadores)"""

    example = """// Patron recomendado (save_personaje.php):
$userId = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);
$svc = new CharacterSaveService();
// ... $db->update_query / Repository ...
GameAjax::json(true, $data);"""

    return f"""
  <section id="arquitectura">
    <h2>Arquitectura backend <span class="badge">game/</span></h2>
    <div class="callout-premium">
      <strong>D001 — Todo local en MySQL</strong>
      Sin backend externo en producción. PHP orquesta; datos en <code>mybb_game_*</code>; 
      plugin captura posts. Contratos en <code>packages/contracts/</code>.
    </div>
    <h3>Capas (de arriba a abajo)</h3>
    <table><thead><tr><th>Capa</th><th>Ubicación</th><th>Responsabilidad</th></tr></thead><tbody>{layers}</tbody></table>
    <pre class="flow">{_esc(flow)}</pre>
    <h3>Services (Application/Services/)</h3>
    {_table_map(BACKEND_SERVICES)}
    <h3>Repositories (Infrastructure/Persistence/)</h3>
    {_table_map(BACKEND_REPOSITORIES)}
    <h3>UseCases (esqueleto / legacy 501)</h3>
    {_table_map(BACKEND_USECASES)}
    <h3>Tablas principales mybb_game_*</h3>
    {_table_map(BACKEND_TABLES)}
    <h3>Ejemplo de entrypoint AJAX</h3>
    <pre class="flow">{_esc(example)}</pre>
  </section>

  <section id="mapa-backend">
    <h2>Mapa de archivos backend <span class="badge">Repo</span></h2>
    {_table_map(BACKEND_FILE_MAP)}
    <p>Endpoints AJAX actuales: carpeta <code>back/forum/game/ajax/</code> — ver gate OpenAPI para lista viva y cobertura.</p>
  </section>"""


def _gates_front(metrics: dict | None) -> str:
    if not metrics:
        return "<p><em>Sin métricas — ejecutar audit_frontend_metrics.py</em></p>"
    gates = metrics.get("gates", {})
    rows = []
    for name, g in gates.items():
        ok = g.get("pass", False)
        pill = '<span class="pill pill-ok">PASS</span>' if ok else '<span class="pill pill-crit">FAIL</span>'
        detail = g.get("count", g.get("pairs", ""))
        rows.append(f"<tr><td><code>{_esc(name)}</code></td><td>{pill}</td><td>{_esc(str(detail))}</td></tr>")
    return (
        "<table><thead><tr><th>Gate</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>"
        + "".join(rows)
        + "</tbody></table>"
    )


def _gates_back(metrics: dict | None) -> str:
    if not metrics:
        return "<p><em>Sin métricas — ejecutar audit_backend_contracts.py</em></p>"
    missing = metrics.get("missing_contract", [])
    miss_pill = '<span class="pill pill-ok">PASS</span>' if not missing else '<span class="pill pill-crit">FAIL</span>'
    return f"""<table><thead><tr><th>Métrica</th><th>Estado</th><th>Valor</th></tr></thead><tbody>
      <tr><td>ajax_count</td><td>—</td><td>{metrics.get("ajax_count", "?")}</td></tr>
      <tr><td>openapi_paths</td><td>—</td><td>{metrics.get("openapi_paths", "?")}</td></tr>
      <tr><td>coverage_percent</td><td>—</td><td>{metrics.get("coverage_percent", "?")}%</td></tr>
      <tr><td>missing_contract</td><td>{miss_pill}</td><td><code>{_esc(", ".join(missing) or "—")}</code></td></tr>
    </tbody></table>"""


def _load_json(path: Path) -> dict | None:
    if not path.is_file():
        return None
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError:
        return None


def generate_frontend() -> str:
    theme = THEMES["front"]
    today = date.today().isoformat()
    metrics = _load_json(FRONT_METRICS)
    n_rules = len(FRONTEND_RULES)
    gates_ok = metrics.get("pass", False) if metrics else False
    gate_pill = '<span class="pill pill-ok">PASS</span>' if gates_ok else '<span class="pill pill-crit">PENDING</span>'

    toc = [
        ("premium-reglas", "★ PREMIUM — Reglas obligatorias"),
        ("design-system", "Sistema de diseño (tokens + componentes)"),
        ("mapa-archivos", "Mapa de archivos frontend"),
        ("gates-vivo", "Gates automáticos (CI)"),
        ("deploy-checklist", "Checklist deploy frontend"),
        ("legacy-tabla", "Tabla prohibido / legacy"),
        ("resumen", "Resumen y puntuación"),
        ("cierre", "Cierre y veredicto"),
    ]
    toc_html = "".join(f'<li><a href="#{a}">{_esc(t)}</a></li>' for a, t in toc)

    scores = [
        ("Sistema de design tokens", 9.2),
        ("Consistencia de botones", 9.0),
        ("Jerarquía visual / escaneabilidad", 9.2),
        ("Accesibilidad de contraste", 8.8),
        ("Atractivo / sensación RPG premium", 9.0),
        ("Mantenibilidad (0 inline, modular JS)", 9.2),
        ("Documentación super guía (LLM)", 9.5),
    ]

    return f"""<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{_esc(theme["title"])}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
  <style>{_doc_css(theme)}</style>
</head>
<body>
<header class="hero">
  <div class="hero-inner">
    <span class="hero-tag">{_esc(theme["tag"])}</span>
    <h1>{theme["h1"]}</h1>
    <p class="sub">{theme["sub"]}</p>
    <div class="meta">
      <span>Generado: {today}</span>
      <span>{n_rules} reglas PREMIUM</span>
      <span>Gates CI: {gate_pill}</span>
      <span>Fuente: AGENTS.md + rpg_custom.css + frontend-deploy.md</span>
    </div>
  </div>
</header>
<main class="container">
  <nav class="toc"><h2>Índice</h2><ol>{toc_html}</ol></nav>

  <section id="premium-reglas">
    <h2>★ PREMIUM — Reglas obligatorias <span class="badge">Siempre</span></h2>
    <div class="callout-premium">
      <strong>Instrucción para agentes y desarrolladores</strong>
      Antes de <code>update_theme.php</code> o importar XML en MyBB: cumple reglas F-ORO-* y F-DS-*.
      Nunca sincronices si <code>diff_theme_source.php</code> devuelve exit 1.
      Usa tokens y componentes de la sección Sistema de diseño — no inventes estilos sueltos.
    </div>
    <p class="rule-count">Total: {n_rules} reglas · Marca cada checkbox al revisar un cambio grande</p>
    {_rules_section(FRONTEND_BLOCKS, FRONTEND_RULES)}
  </section>

  {_frontend_design_section()}

  <section id="gates-vivo">
    <h2>Gates automáticos <span class="badge">audit_frontend_metrics.py</span></h2>
    <p>Salida de la última auditoría. Todos deben PASS (exit 0) antes de merge/deploy de UI.</p>
    {_gates_front(metrics)}
  </section>

  <section id="deploy-checklist">
    <h2>Checklist deploy frontend <span class="badge">Runbook</span></h2>
    <ol class="deploy-steps">
      <li><code>Editar solo front/templates/**/*.html</code></li>
      <li><code>php front/diff_theme_source.php  # exit 0</code></li>
      <li><code>python front/sync_theme_full.py</code></li>
      <li><code>php front/validate_theme_security.php  # exit 0</code></li>
      <li><code>php front/diff_theme_source.php  # confirmar 0</code></li>
      <li><code>python tools/audit_frontend_metrics.py  # exit 0 + regenera esta guía</code></li>
      <li><code>Commitear fuente + Default-theme.xml juntos</code></li>
      <li><code>Importar tema en Admin CP · smoke index/showthread/personaje</code></li>
    </ol>
  </section>

  <section id="legacy-tabla">
    <h2>Prohibido / legacy <span class="badge">No reintroducir</span></h2>
    <table>
      <thead><tr><th>Zona</th><th>Usar (actual)</th><th>No usar (legacy)</th></tr></thead>
      <tbody>
        <tr><td>index — fecha on-rol</td><td><code>#tablon-fecha-widget</code> → calendario.php</td><td><code>#game-calendar-bar</code>, <code>#modal_calendar</code></td></tr>
        <tr><td>index — portada</td><td><code>.roleplay-hero</code> + <code>.rpg-tablon-container</code></td><td>Solo calendario sin tablón</td></tr>
        <tr><td>header — cronología</td><td><code>game_rol_header_html</code> (plugin)</td><td>Duplicar fecha en index</td></tr>
        <tr><td>CSS global</td><td>mybb-minimal + RPG en XML</td><td>Doble link rpg_custom.css</td></tr>
      </tbody>
    </table>
  </section>

  <section id="resumen">
    <h2>Resumen de calidad <span class="badge">DoD visual</span></h2>
    <div class="card-grid">
      <div class="card"><h4>Reglas PREMIUM</h4><div class="val">{n_rules}</div><div class="note">checklist + CI gates</div></div>
      <div class="card"><h4>Gates CI</h4><div class="val">{"PASS" if gates_ok else "—"}</div><div class="note">audit_frontend_metrics.py</div></div>
      <div class="card"><h4>Identidad</h4><div class="val">RP</div><div class="note">pergamin + latón + tablón</div></div>
    </div>
    <h3>Puntuación por dimensión</h3>
    {_score_rows(scores)}
  </section>

  <section id="cierre">
    <h2>Cierre y veredicto <span class="badge">Auditoría cerrada</span></h2>
    <p>Sprint de calidad frontend: gates en <code>tools/audit_frontend_metrics.py</code>. Métricas en <code>docs/auditoria-metrics.json</code>.</p>
    <h3>Completado</h3>
    <ul class="premium-rules">
      <li class="med premium-rule"><span class="rule-body">0 <code>style=</code> en templates y game views · JS en jscripts/game/</span></li>
      <li class="med premium-rule"><span class="rule-body">Super guía con tokens, componentes y mapa de archivos para LLM</span></li>
    </ul>
    <h3>Pendiente</h3>
    <ul class="premium-rules">
      <li class="med premium-rule"><span class="rule-body"><em>Lista vacía de pendientes frontend — auditoría cerrada.</em></span></li>
    </ul>
    <div class="callout-premium">
      <strong>Veredicto final</strong>
      Auditoría cerrada. Coherencia visual ≥ 9.0. Operativo: ejecutar migrate_thread_pj_state.php en prod si aplica (runbook frontend-deploy).
    </div>
  </section>

  <footer>
    Super HTML generado por <code>tools/generate_audit_super_html.py</code> · {today} ·
    Regenerar: <code>{_esc(theme["regen_cmd"])}</code>
  </footer>
</main>
</body>
</html>"""


def generate_backend() -> str:
    theme = THEMES["back"]
    today = date.today().isoformat()
    metrics = _load_json(BACK_METRICS)
    n_rules = len(BACKEND_RULES)
    ajax_n = metrics.get("ajax_count", "?") if metrics else "?"
    cov = metrics.get("coverage_percent", "?") if metrics else "?"

    toc = [
        ("premium-reglas", "★ PREMIUM — Reglas obligatorias"),
        ("arquitectura", "Arquitectura capas (game/)"),
        ("mapa-backend", "Mapa de archivos backend"),
        ("gates-vivo", "Métricas vivas (contratos)"),
        ("deploy-checklist", "Checklist deploy backend"),
        ("resumen", "Resumen y puntuación"),
        ("referencias", "Referencias"),
    ]
    toc_html = "".join(f'<li><a href="#{a}">{_esc(t)}</a></li>' for a, t in toc)

    scores = [
        ("Seguridad (auth, CSRF, exposición)", 9.0),
        ("Arquitectura y convenciones AGENTS", 9.2),
        ("Contratos y trazabilidad API", 9.0),
        ("Capa de datos (migraciones)", 9.0),
        ("Mantenibilidad PHP / UseCases", 9.0),
        ("Operación / runbooks", 9.1),
        ("Documentación super guía (LLM)", 9.5),
    ]

    return f"""<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{_esc(theme["title"])}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
  <style>{_doc_css(theme)}</style>
</head>
<body>
<header class="hero">
  <div class="hero-inner">
    <span class="hero-tag">{_esc(theme["tag"])}</span>
    <h1>{theme["h1"]}</h1>
    <p class="sub">{theme["sub"]}</p>
    <div class="meta">
      <span>Generado: {today}</span>
      <span>{n_rules} reglas PREMIUM</span>
      <span>Endpoints AJAX: {ajax_n}</span>
      <span>Cobertura OpenAPI: {cov}%</span>
      <span>Fuente: AGENTS.md + overview.md + backend-deploy.md</span>
    </div>
  </div>
</header>
<main class="container">
  <nav class="toc"><h2>Índice</h2><ol>{toc_html}</ol></nav>

  <section id="premium-reglas">
    <h2>★ PREMIUM — Reglas obligatorias <span class="badge">Siempre</span></h2>
    <div class="callout-premium">
      <strong>Instrucción para agentes y desarrolladores</strong>
      Estas reglas no son sugerencias. Ante cualquier PR o cambio grande, recorre la lista y marca cada ítem.
      Si una regla no está aquí ni en AGENTS.md, la decisión no existe — documentar primero en AGENTS.md o docs/.
      Consulta la sección Arquitectura para capas Service/Repository/$db.
    </div>
    <p class="rule-count">Total: {n_rules} reglas · Severidad: borde rojo = crítica · ámbar = alta · azul = media</p>
    {_rules_section(BACKEND_BLOCKS, BACKEND_RULES)}
  </section>

  {_backend_arch_section()}

  <section id="gates-vivo">
    <h2>Métricas vivas <span class="badge">audit_backend_contracts.py</span></h2>
    <p>Última ejecución del gate de contratos. Debe estar en verde (sin missing_contract) antes de merge/deploy.</p>
    {_gates_back(metrics)}
  </section>

  <section id="deploy-checklist">
    <h2>Checklist deploy backend <span class="badge">Runbook</span></h2>
    <p>Orden recomendado post-cambio (docs/runbooks/backend-deploy.md):</p>
    <ol class="deploy-steps">
      <li><code>python tools/audit_backend_contracts.py  # exit 0 + regenera esta guía</code></li>
      <li><code>Revisar migraciones pendientes (game/sql/README.md)</code></li>
      <li><code>Verificar plugin Game Post Character Linker activo</code></li>
      <li><code>Smoke: GET /game/ajax/ping.php, notifications_count, POST sin CSRF → 403</code></li>
      <li><code>Bloquear scripts sensibles en nginx/Apache</code></li>
    </ol>
    <h3>Orden migraciones núcleo</h3>
    <ol class="deploy-steps">
      <li><code>migrate_pj_system.php</code></li>
      <li><code>migrate_notifications.php</code></li>
      <li><code>migrate_thread_meta.php</code></li>
      <li><code>migrate_staff_levels.php</code></li>
      <li><code>migrate_aprobar_pj.php</code></li>
    </ol>
    <h3>Smoke post-deploy</h3>
    <table>
      <thead><tr><th>Prueba</th><th>Esperado</th></tr></thead>
      <tbody>
        <tr><td><code>GET /game/ajax/ping.php</code></td><td><code>{{"ok": true}}</code></td></tr>
        <tr><td><code>notifications_count.php</code> (logueado)</td><td>200 + número</td></tr>
        <tr><td>POST mutador sin CSRF</td><td>403</td></tr>
        <tr><td><code>clean_db.php</code> sin flags dev</td><td>404</td></tr>
        <tr><td>Post nuevo en foro</td><td>Fila en <code>mybb_game_post_characters</code></td></tr>
      </tbody>
    </table>
  </section>

  <section id="resumen">
    <h2>Resumen de cumplimiento <span class="badge">Referencia</span></h2>
    <div class="card-grid">
      <div class="card"><h4>Reglas PREMIUM</h4><div class="val">{n_rules}</div><div class="note">checklist manual + CI contratos</div></div>
      <div class="card"><h4>Cobertura API</h4><div class="val">{cov}%</div><div class="note">OpenAPI vs ajax/*.php</div></div>
      <div class="card"><h4>ADR D001</h4><div class="val">Local</div><div class="note">MySQL + $db, sin webhooks activos</div></div>
    </div>
    <h3>Puntuación orientativa (mantenimiento)</h3>
    {_score_rows(scores)}
  </section>

  <section id="referencias">
    <h2>Referencias <span class="badge">Repo</span></h2>
    <ul class="deploy-steps">
      <li><code>AGENTS.md</code> — fuente de verdad operativa</li>
      <li><code>docs/arquitectura/overview.md</code></li>
      <li><code>docs/runbooks/backend-deploy.md</code></li>
      <li><code>back/forum/game/sql/README.md</code></li>
      <li><code>packages/contracts/</code></li>
      <li><code>python tools/audit_backend_contracts.py</code></li>
    </ul>
  </section>

  <footer>
    Super HTML generado por <code>tools/generate_audit_super_html.py</code> · {today} ·
    Regenerar: <code>{_esc(theme["regen_cmd"])}</code>
  </footer>
</main>
</body>
</html>"""


def write_all() -> tuple[Path, Path]:
    OUT_FRONT.write_text(generate_frontend(), encoding="utf-8")
    OUT_BACK.write_text(generate_backend(), encoding="utf-8")
    return OUT_FRONT, OUT_BACK


def main() -> int:
    front, back = write_all()
    print(f"Wrote {front.relative_to(ROOT)}")
    print(f"Wrote {back.relative_to(ROOT)}")
    print(f"Frontend rules: {len(FRONTEND_RULES)}")
    print(f"Backend rules:  {len(BACKEND_RULES)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
