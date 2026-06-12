import json
import codecs

eras = [
    {"id": 1, "start_year": 0, "end_year": 197, "name": "Era I — Los Cuatro Altares"},
    {"id": 2, "start_year": 197, "end_year": 302, "name": "Era II — Siglo Olvidado"},
    {"id": 3, "start_year": 302, "end_year": 800, "name": "Era III"},
    {"id": 4, "start_year": 800, "end_year": None, "name": "Era IV"}
]

lb_base = [
    {"id": 1, "era_id": 1, "name": "Federación", "subtype": "faccion", "desc": "Federación", "details": ""},
    {"id": 2, "era_id": 1, "name": "Cuatro Cultos", "subtype": "faccion", "desc": "Cuatro Cultos", "details": ""},
    {"id": 3, "era_id": 1, "name": "Poneglyphs Altares", "subtype": "objeto", "desc": "Poneglyphs de los Altares", "details": ""},
    {"id": 4, "era_id": 1, "name": "Familia Solmaren", "subtype": "faccion", "desc": "Familia Solmaren", "details": ""},
    {"id": 5, "era_id": 1, "name": "Consejo Itinerante", "subtype": "faccion", "desc": "Consejo Itinerante", "details": ""},
    {"id": 6, "era_id": 1, "name": "Raikōmaru", "subtype": "objeto", "desc": "Navío indestructible que navega solo. Construido por los Varek (109–112). Busca un momento, no un lugar.", "details": ""},
    {"id": 7, "era_id": 1, "name": "Familia Draven", "subtype": "faccion", "desc": "Familia Draven", "details": ""},
    {"id": 8, "era_id": 1, "name": "Tōgane", "subtype": "objeto", "desc": "Piedra que escucha todos los Poneglyphs. Coste: pierdes el recuerdo más reciente de alguien que amas.", "details": ""},
    {"id": 9, "era_id": 1, "name": "Ōkotoba", "subtype": "historia_prohibida", "desc": "Palabra en Protocomún que devuelve la memoria ancestral. Fragmentada en 3 sílabas.", "details": ""},
    {"id": 10, "era_id": 1, "name": "Vernoa — Diosa", "subtype": "personaje_historico", "desc": "Vernoa — Diosa", "details": ""},
    {"id": 11, "era_id": 1, "name": "Familia Varek", "subtype": "faccion", "desc": "Familia Varek", "details": ""},
    {"id": 12, "era_id": 1, "name": "Tsurag — Dios", "subtype": "personaje_historico", "desc": "Tsurag — Dios", "details": ""},
    {"id": 13, "era_id": 2, "name": "20 Reyes", "subtype": "faccion", "desc": "20 Reyes", "details": ""},
    {"id": 14, "era_id": 2, "name": "Familia Orvane", "subtype": "faccion", "desc": "Familia Orvane", "details": ""},
    {"id": 15, "era_id": 2, "name": "República Ohara", "subtype": "historia_prohibida", "desc": "República Ohara", "details": ""},
    {"id": 16, "era_id": 2, "name": "Voren D. Kalos", "subtype": "personaje_historico", "desc": "Voren D. Kalos", "details": ""},
    {"id": 17, "era_id": 2, "name": "Canales Seguros", "subtype": "faccion", "desc": "Canales Seguros", "details": ""},
    {"id": 18, "era_id": 2, "name": "Árbol Palabras", "subtype": "geografia", "desc": "Árbol Palabras", "details": ""},
    {"id": 19, "era_id": 2, "name": "Familia Sorell", "subtype": "faccion", "desc": "Familia Sorell", "details": ""},
    {"id": 20, "era_id": 2, "name": "Ejército Liberación", "subtype": "faccion", "desc": "Ejército Liberación", "details": ""},
    {"id": 21, "era_id": 2, "name": "Fenómeno D.", "subtype": "fenomeno_natural", "desc": "Fenómeno D.", "details": ""},
    {"id": 22, "era_id": 2, "name": "Draven Maris", "subtype": "personaje_historico", "desc": "Draven Maris", "details": ""},
    {"id": 23, "era_id": 1, "name": "Voz (Fenómeno)", "subtype": "fenomeno_natural", "desc": "Voz (Fenómeno)", "details": ""},
    {"id": 24, "era_id": 1, "name": "Frutas Era I", "subtype": "historia_prohibida", "desc": "Frutas Era I", "details": ""}
]

eventos_base = [
    {"id": 1, "era_id": 1, "name": "Primer Pacto de las Mareas", "type": "fundacion", "start_year": 1, "end_year": 1, "desc": "Primer Pacto de las Mareas", "details": "", "ubicacion": "", "impacto": ""},
    {"id": 2, "era_id": 1, "name": "Gran Cartografía de Zazah", "type": "descubrimiento", "start_year": 44, "end_year": 67, "desc": "Gran Cartografía de Zazah", "details": "", "ubicacion": "", "impacto": ""},
    {"id": 3, "era_id": 1, "name": "Controversia de las Frutas", "type": "politica", "start_year": 88, "end_year": 103, "desc": "Controversia de las Frutas", "details": "", "ubicacion": "", "impacto": ""},
    {"id": 4, "era_id": 1, "name": "Construcción de Raikōmaru", "type": "descubrimiento", "start_year": 109, "end_year": 112, "desc": "Construcción de Raikōmaru", "details": "", "ubicacion": "", "impacto": ""},
    {"id": 5, "era_id": 1, "name": "Silencio de Tsurag", "type": "traicion", "start_year": 191, "end_year": 197, "desc": "Silencio de Tsurag", "details": "", "ubicacion": "", "impacto": ""},
    {"id": 6, "era_id": 1, "name": "Dispersión de Ōkotoba", "type": "traicion", "start_year": 196, "end_year": 197, "desc": "Dispersión de Ōkotoba", "details": "", "ubicacion": "", "impacto": ""},
    {"id": 7, "era_id": 1, "name": "Fin del Pacto", "type": "catastrofe", "start_year": 197, "end_year": 197, "desc": "Fin del Pacto", "details": "", "ubicacion": "", "impacto": ""},
    {"id": 8, "era_id": 2, "name": "Declaración Mares Seguros", "type": "fundacion", "start_year": 200, "end_year": 200, "desc": "Declaración Mares Seguros", "details": "", "ubicacion": "", "impacto": ""},
    {"id": 9, "era_id": 2, "name": "Gran Quema de Archivos", "type": "exterminio", "start_year": 203, "end_year": 218, "desc": "Gran Quema de Archivos", "details": "", "ubicacion": "", "impacto": ""},
    {"id": 10, "era_id": 2, "name": "Fundación República Ohara", "type": "fundacion", "start_year": 210, "end_year": 210, "desc": "Fundación República Ohara", "details": "", "ubicacion": "", "impacto": ""},
    {"id": 11, "era_id": 2, "name": "1er Buster Call — Ohara", "type": "exterminio", "start_year": 282, "end_year": 282, "desc": "1er Buster Call — Ohara", "details": "", "ubicacion": "", "impacto": ""},
    {"id": 12, "era_id": 2, "name": "Traición de Voren D. Kalos", "type": "traicion", "start_year": 205, "end_year": 208, "desc": "Traición de Voren D. Kalos", "details": "", "ubicacion": "", "impacto": ""}
]

periodicos_base = [
    {"id": 1, "era_id": 1, "headline": "Era I, Año 190", "date": "Año 190", "snippet": "PRO-FEDERACIÓN", "content": "\"El Consejo celebra 190 años...\" — Voz institucional de la Federación. Cubre el tono pre-caída."},
    {"id": 2, "era_id": 2, "headline": "Era II, Año 203", "date": "Año 203", "snippet": "PRO-GOBIERNO", "content": "\"Pacificación completa...\" — Órgano oficial. Eufemismos: \"transición\", \"integración pacífica\". Menciona Ohara como \"autonomía interna\"."},
    {"id": 3, "era_id": 2, "headline": "Era II, Año 286", "date": "Año 286", "snippet": "REVOLUCIONARIO", "content": "\"Desde las cenizas...\" — Panfleto del Ejército de la Liberación. Cifras, nombres, tono combativo. Primera mención de \"Kael\" (personaje)."},
    {"id": 4, "era_id": 3, "headline": "Era II, Año 305", "date": "Año 305", "snippet": "INDEPENDIENTE", "content": "\"El Navegante Libre\" — Análisis factual. Compara texto oficial con documentos alternos. Descubre discrepancias del Siglo Vacío."}
]

def load_lore2():
    with open("c:/laragon/www/foro_local/back/forum/game/lore2.json", "r", encoding="utf-8") as f:
        content = f.read()
    return json.loads("{" + content + "}")

lore2 = load_lore2()

# Apply corrections
def apply_corrections(text):
    if not isinstance(text, str):
        return text
    # LB#7: Draven Maris ref data-lore-id='23'→'22'
    # Actually wait, let's just do global replace if it's safe.
    text = text.replace("data-lore-id='23'>Draven Maris", "data-lore-id='22'>Draven Maris")
    text = text.replace("data-lore-id=\"23\">Draven Maris", "data-lore-id=\"22\">Draven Maris")
    # LB#20: Voz ref data-lore-id='24'→'23'
    text = text.replace("data-lore-id='24'>El Fenómeno de la Voz", "data-lore-id='23'>El Fenómeno de la Voz")
    text = text.replace("data-lore-id=\"24\">El Fenómeno de la Voz", "data-lore-id=\"23\">El Fenómeno de la Voz")
    return text

for list_name in ["lore_basal_actualizar", "lore_basal_nuevo", "eventos_actualizar", "eventos_nuevo", "periodicos_nuevo"]:
    if list_name in lore2:
        for item in lore2[list_name]:
            for k, v in item.items():
                if isinstance(v, str):
                    item[k] = apply_corrections(v)

# Merge
lore_basal = {x["id"]: x for x in lb_base}
for x in lore2.get("lore_basal_actualizar", []) + lore2.get("lore_basal_nuevo", []):
    lore_basal[x["id"]] = x

eventos = {x["id"]: x for x in eventos_base}
for x in lore2.get("eventos_actualizar", []) + lore2.get("eventos_nuevo", []):
    eventos[x["id"]] = x

periodicos = {x["id"]: x for x in periodicos_base}
for x in lore2.get("periodicos_nuevo", []):
    periodicos[x["id"]] = x

# Solmaren Jirou: eliminar ref (no existe LB entry)
# I will do a regex to remove Solmaren Jirou links if any exist in the resulting json
import re

out_data = {
    "eras": eras,
    "lore_basal": list(lore_basal.values()),
    "eventos": list(eventos.values()),
    "periodicos": list(periodicos.values())
}

json_str = json.dumps(out_data, ensure_ascii=False, indent=4)
# Solmaren Jirou correction
json_str = re.sub(r"<a[^>]+data-lore-id=['\"]\d+['\"][^>]*>Solmaren Jirou</a>", "Solmaren Jirou", json_str)

with codecs.open("c:/laragon/www/foro_local/back/forum/game/lore_temp.json", "w", encoding="utf-8") as f:
    f.write(json_str)
