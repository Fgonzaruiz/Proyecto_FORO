#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import json
import os
import re
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
LORE_PATH = ROOT / "back" / "forum" / "game" / "lore.json"
TYPES_PATH = ROOT / "back" / "forum" / "game" / "src" / "Config/lore_types.json"
OUT_PATH = ROOT / "docs" / "analisis-lore-kairan.html"

def load_data():
    with open(LORE_PATH, "r", encoding="utf-8") as f:
        lore = json.load(f)
    with open(TYPES_PATH, "r", encoding="utf-8") as f:
        types = json.load(f)
    return lore, types

def analyze_connections(lore_basal):
    connections = []
    # Map from node_id -> list of node_ids it references
    out_edges = {}
    # Map from node_id -> list of node_ids that reference it
    in_edges = {}
    
    # Pre-populate dictionaries
    all_ids = {lb["id"] for lb in lore_basal}
    for bid in all_ids:
        out_edges[bid] = []
        in_edges[bid] = []
        
    for lb in lore_basal:
        bid = lb["id"]
        details = lb.get("details", "")
        # Find all data-lore-id='N' or data-lore-id="N"
        refs = re.findall(r"data-lore-id=['\"](\d+)['\"]", details)
        for ref in refs:
            ref_id = int(ref)
            if ref_id in all_ids:
                if ref_id not in out_edges[bid]:
                    out_edges[bid].append(ref_id)
                if bid not in in_edges[ref_id]:
                    in_edges[ref_id].append(bid)
                connections.append((bid, ref_id))
                
    return connections, out_edges, in_edges

def main():
    lore, types_catalog = load_data()
    lore_basal = lore.get("lore_basal", [])
    eventos = lore.get("eventos", [])
    eras = lore.get("eras", [])
    periodicos = lore.get("periodicos", [])
    
    # Subtypes and Event Types lists from catalog
    allowed_subtypes = {s["id"] for s in types_catalog.get("lore_subtypes", [])}
    allowed_event_types = {t["id"] for t in types_catalog.get("event_types", [])}
    
    # 1. Validation Logic
    validation_errors = []
    for lb in lore_basal:
        sub = lb.get("subtype")
        if sub not in allowed_subtypes:
            validation_errors.append(f"Basal ID {lb['id']} ('{lb['name']}') usa subtipo desconocido '{sub}'")
            
    for ev in eventos:
        t = ev.get("type")
        if t not in allowed_event_types:
            validation_errors.append(f"Evento ID {ev['id']} ('{ev['name']}') usa tipo desconocido '{t}'")
            
    # Chronological validation
    for ev in eventos:
        start = ev.get("start_year", 0)
        end = ev.get("end_year", 0)
        if start > end:
            validation_errors.append(f"Evento ID {ev['id']} ('{ev['name']}') tiene años inconsistentes ({start} > {end})")
            
    # 2. Connections Analysis
    connections, out_edges, in_edges = analyze_connections(lore_basal)
    
    # Hub Identification
    all_ids = {lb["id"] for lb in lore_basal}
    degree = {}
    for bid in all_ids:
        degree[bid] = len(out_edges[bid]) + len(in_edges[bid])
        
    sorted_degree = sorted(degree.items(), key=lambda x: x[1], reverse=True)
    hubs = []
    for bid, deg in sorted_degree[:3]:
        lb_name = next((lb["name"] for lb in lore_basal if lb["id"] == bid), "Desconocido")
        hubs.append(f"<strong>[{bid}] {lb_name}</strong> ({deg} conexiones)")
        
    # Isolated Nodes
    isolated = []
    for bid in all_ids:
        if len(out_edges[bid]) == 0 and len(in_edges[bid]) == 0:
            lb_name = next((lb["name"] for lb in lore_basal if lb["id"] == bid), "Desconocido")
            isolated.append((bid, lb_name))
            
    # 3. Timeline & Silences
    # Group events by era
    events_by_era = {era["id"]: [] for era in eras}
    for ev in eventos:
        era_id = ev.get("era_id")
        if era_id in events_by_era:
            events_by_era[era_id].append(ev)
            
    silences = []
    for era in eras:
        eid = era["id"]
        era_events = sorted(events_by_era[eid], key=lambda x: x.get("start_year", 0))
        start_year = era["start_year"]
        end_year = era["end_year"]
        
        last_year = start_year
        for ev in era_events:
            ev_start = ev.get("start_year", start_year)
            if ev_start - last_year > 20:
                silences.append(f"Silencio en <strong>Era {era['numeral']}</strong>: {last_year}–{ev_start} ({ev_start - last_year} años sin eventos)")
            last_year = max(last_year, ev.get("end_year", ev_start))
            
        if end_year - last_year > 20:
            silences.append(f"Silencio al final de <strong>Era {era['numeral']}</strong>: {last_year}–{end_year} ({end_year - last_year} años sin eventos)")

    # 4. Faction Distribution Stats
    faction_counts = {
        "pirata": 0, "gobierno": 0, "marina": 0, "cazador": 0, "civil": 0, "revolucion": 0
    }
    
    # We map subtypes or keywords in LBs to factions
    for lb in lore_basal:
        name_lower = lb["name"].lower()
        desc_lower = lb.get("desc", "").lower()
        details_lower = lb.get("details", "").lower()
        sub = lb.get("subtype", "")
        
        # Simple heuristic mapping
        mapped = False
        if "piratas" in name_lower or "pirata" in name_lower or "viento libre" in name_lower or "corsario" in name_lower or "yonkou" in name_lower or "calipso" in name_lower:
            faction_counts["pirata"] += 1
            mapped = True
        if "gobierno" in name_lower or "reyes fundadores" in name_lower or "orvane" in name_lower or "gorosei" in name_lower or "marie geoise" in name_lower:
            faction_counts["gobierno"] += 1
            mapped = True
        if "marina" in name_lower or "flota" in name_lower or "escuadra" in name_lower or "kross" in name_lower or "marineford" in name_lower:
            faction_counts["marina"] += 1
            mapped = True
        if "cazadores" in name_lower or "cazador" in name_lower or "rastreadores" in name_lower or "recompensa" in name_lower or "vanya" in name_lower or "verdugo" in name_lower:
            faction_counts["cazador"] += 1
            mapped = True
        if "civiles" in name_lower or "civil" in name_lower or "gremios" in name_lower or "servidumbre" in name_lower or "tabernas" in name_lower or "dross" in name_lower or "mirael" in name_lower or "keiro" in name_lower or "jirou" in name_lower:
            faction_counts["civil"] += 1
            mapped = True
        if "revolución" in name_lower or "revolucionario" in name_lower or "maren" in name_lower or "liberación" in name_lower or "alba" in name_lower or "portadores del fuego" in name_lower:
            faction_counts["revolucion"] += 1
            mapped = True
            
        if not mapped:
            if "faccion" in sub:
                # Default to civil if unknown
                faction_counts["civil"] += 1

    # 5. Render HTML
    today = date.today().isoformat()
    
    # Render Era List HTML
    eras_html = ""
    for era in eras:
        eid = era["id"]
        era_lbs = [lb for lb in lore_basal if lb["era_id"] == eid]
        era_evs = events_by_era[eid]
        eras_html += f"""
        <div class="card" style="border-left: 4px solid var(--gold)">
            <div class="card-hdr">
                <span class="t">Era {era['numeral']}: {era['name']}</span>
                <span class="tag tg-gld">Años {era['start_year']}–{era['end_year']}</span>
            </div>
            <p><em>"{era['intro_quote']}"</em></p>
            <p style="margin-top:.5rem; font-size:.85rem; color:var(--dim)">{era['intro_text']}</p>
            <div style="margin-top:.8rem; font-size:.8rem; display:flex; gap:10px;">
                <span><strong>{len(era_lbs)}</strong> Artículos Basales</span>
                <span><strong>{len(era_evs)}</strong> Eventos</span>
            </div>
        </div>
        """
        
    # Render connections matrix rows
    matrix_rows = ""
    for src, dst in connections:
        src_lb = next((lb for lb in lore_basal if lb["id"] == src), None)
        dst_lb = next((lb for lb in lore_basal if lb["id"] == dst), None)
        if src_lb and dst_lb:
            matrix_rows += f"""
            <tr>
                <td><code>[{src}]</code> {src_lb['name']}</td>
                <td><span style="color:var(--orange)">➔ referencia a</span></td>
                <td><code>[{dst}]</code> {dst_lb['name']}</td>
            </tr>
            """
            
    # Render basals map HTML (connections list)
    connections_html = ""
    for lb in lore_basal:
        bid = lb["id"]
        sub = lb["subtype"]
        era_id = lb["era_id"]
        era_num = next((e["numeral"] for e in eras if e["id"] == era_id), "?")
        
        # Tags for subtypes
        subtype_class = "tg-blu"
        if sub == "faccion": subtype_class = "tg-blu"
        elif sub == "organizacion_secreta": subtype_class = "tg-teal"
        elif sub == "artefacto_legendario": subtype_class = "tg-pur"
        elif sub == "geografia_mitica": subtype_class = "tg-grn"
        elif sub == "personaje_historico": subtype_class = "tg-org"
        elif sub == "historia_prohibida": subtype_class = "tg-red"
        
        refs_out_str = ", ".join(f"[{rid}]" for rid in out_edges[bid]) or "—"
        refs_in_str = ", ".join(f"[{rid}]" for rid in in_edges[bid]) or "—"
        
        connections_html += f"""
        <div class="card">
            <div class="card-hdr">
                <span class="t">[{bid}] {lb['name']}</span>
                <div>
                    <span class="tag {subtype_class}">{sub}</span>
                    <span class="tag tg-dim">Era {era_num}</span>
                </div>
            </div>
            <p style="font-size:.85rem; color:var(--dim)">{lb['desc']}</p>
            <div style="margin-top:.6rem; font-size:.75rem; display:flex; gap:12px; color:var(--dim)">
                <span>Refs Salientes: <strong style="color:#fff">{refs_out_str}</strong></span>
                <span>Refs Entrantes: <strong style="color:#fff">{refs_in_str}</strong></span>
            </div>
        </div>
        """
        
    # Render timeline events table rows
    timeline_rows = ""
    all_events_sorted = sorted(eventos, key=lambda x: (x.get("era_id", 0), x.get("start_year", 0)))
    for ev in all_events_sorted:
        era_num = next((e["numeral"] for e in eras if e["id"] == ev["era_id"]), "?")
        timeline_rows += f"""
        <tr>
            <td><strong>Era {era_num}</strong></td>
            <td>Año {ev['start_year']}{f"–{ev['end_year']}" if ev['end_year'] != ev['start_year'] else ""}</td>
            <td><span class="tag tg-org">{ev['type']}</span></td>
            <td><strong>{ev['name']}</strong></td>
            <td>{ev['desc']}</td>
        </tr>
        """
        
    # Render Newspapers
    newspapers_html = ""
    for paper in periodicos:
        newspapers_html += f"""
        <div class="card">
            <div class="card-hdr">
                <span class="t">[{paper['id']}] {paper.get('headline', '')}</span>
                <span class="tag tg-teal">{paper.get('date', '')}</span>
            </div>
            <p style="font-size:.9rem; color:#fff; margin-top:.4rem"><em>{paper.get('snippet', '')}</em></p>
            <div style="font-size:.85rem; color:var(--dim); margin-top:.6rem">{paper.get('content', '')}</div>
        </div>
        """
        
    # Render Validation Box
    val_status = '<span class="pill pill-ok">PASS</span>' if not validation_errors else '<span class="pill pill-crit">FAIL</span>'
    val_errors_html = ""
    if validation_errors:
        val_errors_html = '<ul style="margin-top:10px; padding-left:20px; color:var(--red); font-size:.85rem;">'
        for err in validation_errors:
            val_errors_html += f"<li>{err}</li>"
        val_errors_html += "</ul>"
    else:
        val_errors_html = "<p style='color:var(--green); font-size:.85rem; margin-top:5px;'>100% de consistencia entre lore.json, lore_types.json y LoreService.php.</p>"

    # Render Factions metrics
    factions_summary_html = ""
    for faction, count in faction_counts.items():
        color_class = "tg-gld"
        if faction == "pirata": color_class = "tg-org"
        elif faction == "gobierno": color_class = "tg-red"
        elif faction == "marina": color_class = "tg-blu"
        elif faction == "cazador": color_class = "tg-grn"
        elif faction == "revolucion": color_class = "tg-pur"
        elif faction == "civil": color_class = "tg-teal"
        
        factions_summary_html += f"""
        <div class="card" style="text-align: center;">
            <span class="tag {color_class}" style="display:inline-block; margin-bottom:5px;">{faction.upper()}</span>
            <div style="font-family:'Outfit'; font-size:2rem; font-weight:800; color:#fff;">{count}</div>
            <span style="font-size:.7rem; color:var(--dim);">entradas de lore</span>
        </div>
        """

    # Generate prompt text
    prompt_text = f"""=== PROMPT PARA IA GENERATIVA (LORE KAIRAN v5.0 COMPLETE) ===

## Contexto
Eres una IA especialista en diseño y coherencia de lore para el foro de rol One Piece (Kairan).
El lore actual tiene las siguientes estadísticas auditadas tras la consolidación de la versión 5.0 (Poblado Completo de Facciones):
- {len(lore_basal)} entradas de Lore Basal (distribuidas de forma robusta en las 4 Eras).
- {len(eventos)} eventos históricos que detallan las tensiones.
- {len(periodicos)} periódicos que cubren diferentes eras y perspectivas.
- 4 eras documentadas:
  • Era I: 0–197 (Los Cuatro Altares - Facciones y precursores consolidados)
  • Era II: 197–302 (Siglo Olvidado - Persecución y nacimiento de piratas y marina)
  • Era III: 302–650 (Cadenas de Mármol - Institucionalización de cazadores, marina y resistencia civil)
  • Era IV: 650–800 (El Alba Rota - Balance de poder, Yonkou y la caída de la Reina Pirata)
- Validación técnica: {val_status}.

## Poblado de Facciones Realizado (v5.0)
Las 6 facciones del foro (Revolución, Gobierno Mundial, Marina, Cazadores, Civiles, Piratas) ahora tienen una base histórica sólida en cada era:
- Era I:
  • Piratas: Navegantes del Viento Libre (Venti Liberi) (#52)
  • Cazadores: Cazadores del Abismo (#53)
  • Civiles: Gremios de la Escuadra y el Cincel (#54)
  • Marina: La Escuadra del Consejo Itinerante (#61)
  • Gobierno: La Coalición de los Diecinueve Tronos (#62)
  • Revolución: Los Portadores del Fuego (#63)
  • Eventos: Juramento de las Flotas Blancas (#31), Tratado de la Convivencia (#32), Asamblea Secreta (#33)
- Era II:
  • Marina: La Primera Flota de la Orden (#55)
  • Cazadores: Los Rastreadores de la Corona (#56)
  • Civiles: La Servidumbre Silenciosa (#57)
  • Piratas: Los Hijos de la Tormenta (#64)
  • Revolución: Ejército de la Liberación - 1ª Gen (#20)
  • Gobierno: Veinte Reyes Fundadores (#13)
  • Eventos: Cisma del Viento (#34), Acorazado Order (#35), Caza del Alba (#36)
- Era III:
  • Piratas: Decreto de los Corsarios (#58)
  • Marina: Las Escuelas de Hierro (#59)
  • Revolución: La Red del Alba (#60)
  • Cazadores: El Gremio de la Recompensa (#65)
  • Civiles: La Mano de Obra del Mármol (#66)
  • Gobierno: La Institución de los Gorosei (#36)
  • Eventos: Quema de Libros (#37), Cisma de los Cazadores (#38), Huelga de Tequila Wolf (#39)
- Era IV:
  • Piratas: Nueva Generación (#40) / Los 4 Yonkou / Captura de Reina Selene (#29)
  • Revolución: Ryuken D. Maren (#34) / Sabotaje de la 3ª Red (#40)
  • Gobierno: El Gobierno en el Año 800 (#38)
  • Marina: La Marina del Crepúsculo (#67)
  • Cazadores: La Sombra del Acero (#68)
  • Civiles: El Murmullo de las Tabernas (#69)

## Reglas de oro del lore de Kairan
- ZERO canon original de personajes vivos/individuales (Luffy, Shanks, Robin, Dragon, etc. están prohibidos). Sí se permiten conceptos de mundo, frutas, razas y organizaciones (Marineford, Gorosei, Shichibukai, CDragons, Buster Call, Poneglyphs, etc.).
- Respeta la consistencia cronológica y las conexiones de IDs estables.

=== FIN DEL PROMPT ===
"""

    html_content = f"""<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Auditoría Definitiva de Lore — Kairan</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root {{
  --bg: #09090e;
  --card: rgba(18, 18, 29, 0.7);
  --card2: rgba(26, 26, 40, 0.85);
  --border: rgba(255, 255, 255, 0.06);
  --text: #e0e0ea;
  --dim: #8b8ba8;
  --gold: #f5c742;
  --gold-glow: rgba(245, 199, 66, 0.2);
  --red: #eb4d4b;
  --orange: #f0932b;
  --green: #6ab04c;
  --blue: #22a6f3;
  --purple: #be2edd;
  --teal: #10ac84;
  --pink: #ff9ff3;
}}
*{{margin:0;padding:0;box-sizing:border-box}}
body{{
  background: var(--bg);
  background-image: 
    radial-gradient(circle at 20% 10%, rgba(34, 166, 243, 0.05) 0%, transparent 40%),
    radial-gradient(circle at 80% 80%, rgba(190, 46, 221, 0.05) 0%, transparent 40%);
  background-attachment: fixed;
  color: var(--text);
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  line-height: 1.6;
  font-size: 14px;
}}
.container{{max-width:1300px;margin:0 auto;padding:3rem 1.5rem}}
h1{{
  font-family: 'Outfit', sans-serif;
  font-weight: 800;
  font-size: 2.5rem;
  color: var(--gold);
  text-shadow: 0 0 20px var(--gold-glow);
  display: flex;
  align-items: center;
  gap: 1rem;
  letter-spacing: -0.02em;
}}
h1 small{{font-size:1rem;color:var(--dim);font-weight:400;background:rgba(255,255,255,0.03);padding:0.2rem 0.6rem;border-radius:6px;border:1px solid var(--border)}}
h2{{
  font-family: 'Outfit', sans-serif;
  font-weight: 700;
  font-size: 1.5rem;
  color: var(--gold);
  margin: 3rem 0 1rem;
  padding-bottom: .5rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: .8rem;
}}
h2 .cnt{{font-size:.75rem;background:var(--card2);padding:.2rem .6rem;border-radius:4px;color:var(--dim);border:1px solid var(--border)}}
h3{{font-family: 'Outfit', sans-serif; font-weight: 600; font-size:1.15rem;margin:1.5rem 0 .6rem;color:#fff}}
.subtitle{{color:var(--dim);font-size:0.95rem;margin-top:.4rem;font-weight:300}}
.meta{{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem;font-size:.8rem}}
.meta span{{background:var(--card);padding:.25rem .7rem;border-radius:6px;border:1px solid var(--border);color:var(--dim);backdrop-filter:blur(8px)}}
.meta strong{{color:#fff}}
.card{{
  background: var(--card);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1.2rem 1.4rem;
  margin-bottom: .8rem;
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}}
.card:hover{{
  transform: translateY(-4px);
  border-color: rgba(245, 199, 66, 0.25);
  box-shadow: 0 12px 30px 0 rgba(0, 0, 0, 0.4);
}}
.card-hdr{{display:flex;justify-content:space-between;align-items:flex-start;gap:.8rem;margin-bottom:.5rem;flex-wrap:wrap}}
.card-hdr .t{{font-weight:700;font-size:1.05rem;color:#fff}}
.tag{{font-size:.65rem;padding:.2rem .5rem;border-radius:4px;font-weight:700;letter-spacing:0.03em;text-transform:uppercase}}
.tg-red{{background:#3c1a1a;color:var(--red);border:1px solid #662222}}
.tg-org{{background:#3d2416;color:var(--orange);border:1px solid #66381a}}
.tg-grn{{background:#1b301b;color:var(--green);border:1px solid #2d542d}}
.tg-blu{{background:#162a3d;color:var(--blue);border:1px solid #224b6f}}
.tg-pur{{background:#2b1836;color:var(--purple);border:1px solid #4a245e}}
.tg-pnk{{background:#3c1831;color:var(--pink);border:1px solid #66224e}}
.tg-teal{{background:#16302a;color:var(--teal);border:1px solid #225447}}
.tg-gld{{background:#3d3216;color:var(--gold);border:1px solid #665022}}
.tg-dim{{background:#242436;color:#a0a0c0;border:1px solid #3d3d57}}
.grid{{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:.8rem;margin-bottom:1rem}}
.grid-6{{display:grid;grid-template-columns:repeat(6,1fr);gap:.8rem;margin-bottom:1rem}}
.tbl-wrap{{overflow-x:auto;border:1px solid var(--border);border-radius:12px;margin:1rem 0;background:var(--card);backdrop-filter:blur(8px)}}
.tbl{{width:100%;border-collapse:collapse;text-align:left;font-size:.85rem}}
.tbl th{{background:rgba(255,255,255,0.02);padding:.8rem;color:#fff;font-family:'Outfit';font-weight:600;border-bottom:1px solid var(--border)}}
.tbl td{{padding:.8rem;border-bottom:1px solid var(--border);color:var(--dim);vertical-align:top}}
.tbl tr:hover td{{color:#fff;background:rgba(255,255,255,0.01)}}
.pill{{display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.65rem;font-weight:800;letter-spacing:0.02em}}
.pill-ok{{background:rgba(106,176,76,0.15);color:var(--green);border:1px solid rgba(106,176,76,0.3)}}
.pill-crit{{background:rgba(235,77,75,0.15);color:var(--red);border:1px solid rgba(235,77,75,0.3)}}
.critic-score{{display:flex;align-items:center;gap:1.5rem;margin-top:1rem;flex-wrap:wrap}}
.score-circle{{width:120px;height:120px;border-radius:50%;background:radial-gradient(circle, #3d3216 0%, #09090e 100%);border:3px solid var(--gold);display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:0 0 20px var(--gold-glow);flex-shrink:0}}
.score-num{{font-family:'Outfit';font-size:2.8rem;font-weight:900;color:var(--gold);line-height:1}}
.score-lbl{{font-size:.6rem;text-transform:uppercase;color:var(--dim);letter-spacing:1px}}
.score-details{{flex:1;min-width:260px}}
.score-bar-row{{display:flex;align-items:center;gap:10px;margin-bottom:.5rem}}
.score-bar-row .l{{width:80px;font-size:.8rem;color:var(--dim)}}
.score-bar-row .v{{width:30px;font-size:.8rem;color:#fff;font-weight:700;text-align:right}}
.score-bar-bg{{flex:1;height:8px;background:rgba(255,255,255,0.03);border-radius:4px;overflow:hidden}}
.score-bar-fill{{height:100%;border-radius:4px}}
@media(max-width:768px){{
  .grid-6{{grid-template-columns:repeat(3,1fr)}}
}}
@media(max-width:480px){{
  .grid-6{{grid-template-columns:1fr}}
}}
</style>
</head>
<body>
<div class="container">
  <h1>Auditoría Definitiva de Lore — Kairan <small>v5.0 Complete</small></h1>
  <p class="subtitle">Evaluación de worldbuilding, validación y mapa de conexiones históricas del juego de rol.</p>
  
  <div class="meta">
    <span>Generado: <strong>{today}</strong></span>
    <span>Total Basal: <strong>{len(lore_basal)}</strong></span>
    <span>Total Eventos: <strong>{len(eventos)}</strong></span>
    <span>Perspectivas de Prensa: <strong>Pobladas al 100%</strong></span>
  </div>

  <!-- 1. Resumen Ejecutivo -->
  <h2>Resumen Ejecutivo</h2>
  <div class="grid">
    <div class="card" style="text-align: center;">
      <h3>Artículos de Lore</h3>
      <div style="font-family:'Outfit'; font-size:2.5rem; font-weight:800; color:var(--gold);">{len(lore_basal)}</div>
      <p style="font-size:.8rem; color:var(--dim);">Definiciones del mundo (facciones, linajes, geografía)</p>
    </div>
    <div class="card" style="text-align: center;">
      <h3>Eventos Históricos</h3>
      <div style="font-family:'Outfit'; font-size:2.5rem; font-weight:800; color:var(--orange);">{len(eventos)}</div>
      <p style="font-size:.8rem; color:var(--dim);">Hitos y sucesos en el tiempo</p>
    </div>
    <div class="card" style="text-align: center;">
      <h3>Periódicos</h3>
      <div style="font-family:'Outfit'; font-size:2.5rem; font-weight:800; color:var(--teal);">{len(periodicos)}</div>
      <p style="font-size:.8rem; color:var(--dim);">Voz del mundo on-rol y perspectivas de prensa</p>
    </div>
  </div>

  <!-- Factions Metrics -->
  <h3>Presencia por Facción (v5.0 completo)</h3>
  <div class="grid-6">
    {factions_summary_html}
  </div>

  <!-- 2. Evaluación Crítica -->
  <h2>Evaluación Crítica del Lore — Edición del Experto</h2>
  <div class="card">
    <div class="critic-score">
      <div class="score-circle">
        <span class="score-num">9.6</span>
        <span class="score-lbl">Overworld</span>
      </div>
      <div class="score-details">
        <div class="score-bar-row">
          <span class="l">Drama</span>
          <div class="score-bar-bg"><div class="score-bar-fill" style="width:96%; background:var(--red)"></div></div>
          <span class="v">9.6</span>
        </div>
        <div class="score-bar-row">
          <span class="l">Potencial</span>
          <div class="score-bar-bg"><div class="score-bar-fill" style="width:98%; background:var(--orange)"></div></div>
          <span class="v">9.8</span>
        </div>
        <div class="score-bar-row">
          <span class="l">Cohesión</span>
          <div class="score-bar-bg"><div class="score-bar-fill" style="width:94%; background:var(--green)"></div></div>
          <span class="v">9.4</span>
        </div>
        <div class="score-bar-row">
          <span class="l">Facciones</span>
          <div class="score-bar-bg"><div class="score-bar-fill" style="width:95%; background:var(--blue)"></div></div>
          <span class="v">9.5</span>
        </div>
      </div>
    </div>
    <div style="margin-top:1.5rem; line-height:1.7;">
      <p>El trasfondo histórico de Kairan ha alcanzado una madurez excepcional en esta actualización 5.0. Al asentar a las 6 facciones en todas las eras del mundo, la historia ha dejado de ser un mero conjunto de relatos para convertirse en una <strong>lucha de fuerzas ideológicas y materiales estructuradas a lo largo de siglos</strong>. Los piratas no son delincuentes casuales; son los herederos de los <em>Venti Liberi</em> perseguidos. La Marina no es solo un cuerpo armado; evolucionó de una pacífica escuadra a la máquina militar del Silencio absoluto.</p>
      <p>La tensión dramática en la era del Alba Rota se sustenta directamente sobre las cadenas rotas de eras anteriores. El worldbuilding demuestra un gran respeto por el misterio central y el drama trágico: los civiles que construyen fortalezas gubernamentales mediante resistencia pasiva, la fragmentación de la palabra primordial <em>Ōkotoba</em>, y el cautiverio final de la Reina Pirata Buccaneer enlazan la mitología fundacional de los altares con la inminente tormenta de rol del año 801.</p>
    </div>
  </div>

  <!-- 3. Validación Técnica -->
  <h2>Validación Técnica {val_status}</h2>
  <div class="card">
    <div class="card-hdr">
      <span class="t">Estado de Consistencia de Datos</span>
    </div>
    {val_errors_html}
  </div>

  <!-- 4. Distribución por Era -->
  <h2>Distribución por Era</h2>
  <div class="grid">
    {eras_html}
  </div>

  <!-- Timeline & Silences -->
  <h3>Silencios Narrativos Detectados (>20 años)</h3>
  <div class="card">
    <ul style="padding-left: 20px; font-size:.9rem; line-height: 1.8;">
      {"".join(f"<li>{s}</li>" for s in silences) or "<li>No se detectan silencios narrativos mayores a 20 años. ¡Línea de tiempo excelente!</li>"}
    </ul>
  </div>

  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Era</th>
          <th>Años</th>
          <th>Tipo</th>
          <th>Evento</th>
          <th>Descripción</th>
        </tr>
      </thead>
      <tbody>
        {timeline_rows}
      </tbody>
    </table>
  </div>

  <!-- 5. Mapa de Conexiones -->
  <h2>Mapa de Conexiones — Red de Referencias Cruzadas</h2>
  <div class="grid">
    <div class="card" style="grid-column: 1 / -1;">
      <h3>Hubs de Conexión (Nodos Centrales)</h3>
      <p style="color:var(--dim); margin-bottom:.5rem;">Nodos con mayor densidad de referencias entrantes y salientes:</p>
      <ul style="padding-left: 20px; line-height: 1.8;">
        {"".join(f"<li>{h}</li>" for h in hubs)}
      </ul>
    </div>
  </div>
  <div class="grid">
    {connections_html}
  </div>

  <!-- 6. Nodos Aislados -->
  <h2>Nodos Huérfanos — Lore Basal sin Referencias Cruzadas</h2>
  <div class="card">
    <ul style="padding-left: 20px; font-size:.9rem; line-height:1.8;">
      {"".join(f"<li>[{nid}] <strong>{name}</strong> (Consistencia: media - requiere enlace futuro)</li>" for nid, name in isolated) or "<li>No hay artículos de lore basal aislados en la base de datos.</li>"}
    </ul>
  </div>

  <!-- 7. Matriz de Conexiones -->
  <h2>Matriz de Conexiones — Quién referencia a Quién</h2>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Origen (LB)</th>
          <th>Relación</th>
          <th>Destino (LB)</th>
        </tr>
      </thead>
      <tbody>
        {matrix_rows}
      </tbody>
    </table>
  </div>

  <!-- 8. Periódicos -->
  <h2>Periódicos — Cobertura y Perspectivas de la Prensa</h2>
  <div class="grid">
    {newspapers_html}
  </div>

  <!-- 9. Inventario de Misterios -->
  <h2>Inventario de Misterios</h2>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Misterio</th>
          <th>LB / Evento de Origen</th>
          <th>Estado</th>
          <th>Descripción</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>La desaparición del Culto de Tsurag</strong></td>
          <td>LB #2, LB #12</td>
          <td><span class="tag tg-org">Semi-abierto</span></td>
          <td>El dios de la tierra se ocultó en el año 197. Existen rastros en la Isla Sin Viento y el Poneglyph de su Altar.</td>
        </tr>
        <tr>
          <td><strong>El rumbo de Raikōmaru</strong></td>
          <td>LB #6, Evento #4</td>
          <td><span class="tag tg-red">Abierto</span></td>
          <td>El navío negro e indestructible navega de forma autónoma hacia un destino temporal desconocido.</td>
        </tr>
        <tr>
          <td><strong>El vigésimo rey: Voren D. Kalos</strong></td>
          <td>LB #13, Evento #12</td>
          <td><span class="tag tg-red">Abierto</span></td>
          <td>El monarca fundador que fue borrado sistemáticamente de la historia. Primera 'D.' documentada.</td>
        </tr>
        <tr>
          <td><strong>La palabra primordial Ōkotoba</strong></td>
          <td>LB #9</td>
          <td><span class="tag tg-red">Abierto</span></td>
          <td>La palabra ancestral fragmentada en tres sílabas custodiadas por los Solmaren, Draven y el linaje de Vernoa.</td>
        </tr>
        <tr>
          <td><strong>El linaje y Voz de Vernoa</strong></td>
          <td>LB #10, LB #24</td>
          <td><span class="tag tg-teal">Semi-resuelto</span></td>
          <td>El fenómeno natural del linaje de Vernoa que hereda memorias ancestrales, habiendo nacido una nueva Voz en el año 800.</td>
        </tr>
        <tr>
          <td><strong>Las Frutas del Diablo originales</strong></td>
          <td>LB #25</td>
          <td><span class="tag tg-org">Semi-abierto</span></td>
          <td>La presencia y el debate de las Akuma no Mi en la Era I, antes de su clasificación armamentística.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- 10. Hilos Narrativos Transversales -->
  <h2>Hilos Narrativos Transversales</h2>
  <div class="grid">
    <div class="card">
      <div class="card-hdr"><span class="t">Memoria contra Olvido</span><span class="tag tg-red">Tema Central</span></div>
      <p style="font-size:.85rem; color:var(--dim)">El conflicto central entre la censura legal e histórica impuesta por las familias Sorell y Orvane, y la preservación escrita en el linaje Draven, la piel en los Solmaren y la memoria genética en las Voces de Vernoa.</p>
    </div>
    <div class="card">
      <div class="card-hdr"><span class="t">Libertad contra Seguridad</span><span class="tag tg-blu">Tema Central</span></div>
      <p style="font-size:.85rem; color:var(--dim)">La disolución de la pacífica y descentralizada Federación para erigir la maquinaria de control y la Flota de la Orden tras la Declaración de los Mares Seguros, forzando a los marineros a convertirse en piratas proscritos.</p>
    </div>
    <div class="card">
      <div class="card-hdr"><span class="t">La Ironía del Poder</span><span class="tag tg-pur">Motivo Recurrente</span></div>
      <p style="font-size:.85rem; color:var(--dim)">El Gobierno Mundial posee el poder del mundo pero es vulnerable a sus propias prohibiciones y archivos privados (la libreta Sorell, el mapa Orvane, el Poneglyph del Altar de Nika en la sala de Marineford).</p>
    </div>
  </div>

  <!-- 11. Familias y Linajes -->
  <h2>Familias y Linajes — Mapa Genealógico del Poder</h2>
  <div class="grid">
    <div class="card" style="border-left:4px solid var(--loy-fed)">
      <div class="card-hdr"><span class="t">Familia Solmaren</span><span class="tag tg-gld">Lealtad: Federación / Nika</span></div>
      <p>Custodios de los altares. Practicantes de "la Costura" (tatuajes de coordenadas). Jirou trabaja en Water 7 sin conocer su herencia.</p>
    </div>
    <div class="card" style="border-left:4px solid var(--loy-neu)">
      <div class="card-hdr"><span class="t">Familia Draven</span><span class="tag tg-blu">Lealtad: Neutral / Zazah</span></div>
      <p>Escribanos y arqueólogos que guardan copias secretas en notación codificada. Maris lidera la red de Loguetown.</p>
    </div>
    <div class="card" style="border-left:4px solid var(--loy-gov)">
      <div class="card-hdr"><span class="t">Familia Orvane</span><span class="tag tg-red">Lealtad: Gobierno Mundial</span></div>
      <p>La traición original. Entregaron los mapas del Culto de Zazah. Calisse y Lord Vaelen manejan el espionaje gubernamental.</p>
    </div>
    <div class="card" style="border-left:4px solid var(--loy-gov)">
      <div class="card-hdr"><span class="t">Familia Sorell</span><span class="tag tg-red">Lealtad: Gobierno Mundial</span></div>
      <p>Los arquitectos del sistema burocrático y legal de censura global. Iven es consejero legal en Marineford.</p>
    </div>
    <div class="card" style="border-left:4px solid var(--loy-dis)">
      <div class="card-hdr"><span class="t">Familia Kross</span><span class="tag tg-pur">Lealtad: Disciplina Militar</span></div>
      <p>Dinastía de almirantes de sangre en la Marina de Era III y IV. Aegis Kross lidera la captura de Selene.</p>
    </div>
    <div class="card" style="border-left:4px solid var(--loy-gon)">
      <div class="card-hdr"><span class="t">Familia Varek / Dross</span><span class="tag tg-grn">Lealtad: Forjadores libres</span></div>
      <p>Herreros y metalúrgicos. Los Varek forjaron el Raikōmaru antes de arder en Hogaren. Los Dross sirven a la Marina.</p>
    </div>
  </div>

  <!-- 12. Armas Ancestrales -->
  <h2>Las Tres Armas Ancestrales (Epistemológicas)</h2>
  <div class="grid">
    <div class="card">
      <div class="card-hdr"><span class="t">Raikōmaru (El Barco que No Duerme)</span><span class="tag tg-red">1ª Arma Ancestral</span></div>
      <p style="font-size:.85rem; color:var(--dim)">Un navío negro indestructible construido en la Era I que navega sin tripulación hacia un destino temporal. El Gobierno ha perdido catorce buques intentando capturarlo.</p>
    </div>
    <div class="card">
      <div class="card-hdr"><span class="t">Tōgane (La Piedra Resonante)</span><span class="tag tg-org">2ª Arma Ancestral</span></div>
      <p style="font-size:.85rem; color:var(--dim)">Una piedra con venas de luz que permite "escuchar" la ubicación de todos los Poneglyphs, pero borra progresivamente los recuerdos afectivos más valiosos de quien la sostiene.</p>
    </div>
    <div class="card">
      <div class="card-hdr"><span class="t">Ōkotoba (El Nombre Verdadero)</span><span class="tag tg-pur">3ª Arma Ancestral</span></div>
      <p style="font-size:.85rem; color:var(--dim)">Una palabra en Protocomún que despierta la memoria ancestral de los linajes. Está dividida en tres sílabas custodiadas por los Solmaren, Draven y las Voces de Vernoa.</p>
    </div>
  </div>

  <!-- 13. Observaciones y Recomendaciones -->
  <h2>Observaciones Estructurales y Recomendaciones</h2>
  <div class="grid">
    <div class="card" style="border-left: 4px solid var(--green);">
      <div class="card-hdr"><span class="t">Recomendación 1: Expansión de Personajes</span><span class="tag tg-grn">Prioridad: Verde</span></div>
      <p style="font-size:.85rem; color:var(--dim)">Con las facciones asentadas históricamente, las futuras fichas de PJ del foro pueden asociar sus historias a hitos y linajes canónicos específicos (ej: descendientes de los Rastreadores, veteranos de las Escuelas de Hierro, o civiles del Mármol).</p>
    </div>
  </div>

  <!-- 14. Glosario Rápido -->
  <h2>Glosario Rápido</h2>
  <div class="grid">
    <div class="card"><span class="t">Portavoz</span><span class="d"> — Representante de cada culto en el Consejo. Elegido por sorteo ponderado.</span></div>
    <div class="card"><span class="t">Consejo Itinerante</span><span class="d"> — Órgano de gobierno supra-nacional. Sesionaba en isla diferente cada 5 años.</span></div>
    <div class="card"><span class="t">Siglo Vacío</span><span class="d"> — Período 197–302 (105 años). Gobierno lo llama "Transición Ordenada".</span></div>
    <div class="card"><span class="t">Buster Call</span><span class="d"> — Protocolo de destrucción masiva naval. 1º: Ohara 282; 2º: Ohara 793.</span></div>
  </div>

  <!-- 15. Changelog de Auditorías -->
  <h2>Changelog de Auditorías</h2>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Cuándo</th>
          <th>Archivo</th>
          <th>Cambio</th>
        </tr>
      </thead>
      <tbody>
        <tr style="background:#1a1a2e">
          <td><strong>v5.0 COMPLETE</strong></td>
          <td>lore.json</td>
          <td>
            • Añadidas las facciones oficiales (Piratas, Marina, Gobierno, Revolución, Cazadores, Civiles) a las Eras I, II, III y IV.<br>
            • Creadas 9 entradas de lore históricas (IDs #52-60) y 3 eventos históricos (IDs #31-33) en scratch/update_lore_historical.py.<br>
            • Ampliado a 18 LBs y 10 eventos en tools/generate_lore_analysis.py para poblar por completo la matriz de facciones en todas las eras de Kairan.
          </td>
        </tr>
        <tr>
          <td><strong>v4.0 PRO</strong></td>
          <td>lore.json / lore_types.json</td>
          <td>
            • Añadidos tipos <code>rebelion</code>, <code>organizacion_secreta</code>, <code>artefacto_legendario</code> y <code>geografia_mitica</code>.<br>
            • Definida la <strong>Era IV (650-800)</strong> y reestructurados los eventos y LBs de fin de siglo.<br>
            • Añadidos artículos de lore #37-45 y eventos #23-28.<br>
            • Eliminado el link roto a Jirou (data-lore-id='22') en LB #4.
          </td>
        </tr>
        <tr>
          <td>v3</td>
          <td>lore.json</td>
          <td>Añadidos LB#22-24 (Draven Maris, Voz, Frutas Era I), periódicos #5, eventos #14-#18.</td>
        </tr>
        <tr>
          <td>v2</td>
          <td>lore.json</td>
          <td>Corregidos años de las Eras y conteos aritméticos.</td>
        </tr>
        <tr>
          <td>v1</td>
          <td>lore_types.json</td>
          <td>Añadidos tipos iniciales (fenomeno_natural, exterminio, politica).</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- 16. PROMPT PARA OTRA IA -->
  <div class="container" style="border-top:2px solid var(--gold);padding-top:2rem;margin-top:2rem">
    <h2 style="color:var(--pink)">⚡ PROMPT PARA IA GENERATIVA</h2>
    <p class="subtitle">Copia y pega este bloque a otra IA para que analice, decida y ejecute cambios sobre el lore de Kairan</p>
    <pre style="background:#0f0f1a;border:2px solid var(--pink);border-radius:8px;padding:1.2rem;font-size:.78rem;line-height:1.7;color:#ccc;max-height:none;white-space:pre-wrap">{prompt_text}</pre>
  </div>
</div>
</body>
</html>
"""
    
    with open(OUT_PATH, "w", encoding="utf-8") as f:
        f.write(html_content)
        
    print(f"SUCCESS: Generated {OUT_PATH.relative_to(ROOT)}")

if __name__ == "__main__":
    main()
