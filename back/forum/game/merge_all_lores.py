import json
import codecs

def load_json(path):
    with open(path, 'r', encoding='utf-8') as f:
        return json.load(f)

lore1 = load_json('c:/laragon/www/foro_local/back/forum/game/lore1.json')
lore2 = load_json('c:/laragon/www/foro_local/back/forum/game/lore2.json')
lore_corr = load_json('c:/laragon/www/foro_local/back/forum/game/lorecorreciones.json')

final_lore = {
    "meta": {},
    "eras": [],
    "lore_basal": [],
    "eventos": [],
    "periodicos": []
}

# 1. Merge meta
final_lore['meta'].update(lore1.get('meta', {}))
final_lore['meta'].update(lore2.get('meta', {}))
final_lore['meta'].update(lore_corr.get('meta', {}))

# 2. Helper to merge lists by ID
def merge_lists(list1, list2):
    merged = {item['id']: item for item in list1}
    for item in list2:
        merged[item['id']] = item
    return list(merged.values())

# Merge eras
final_lore['eras'] = merge_lists(lore1.get('eras', []), lore2.get('eras', []))

# Merge lore_basal
lb = merge_lists(lore1.get('lore_basal', []), lore2.get('lore_basal', []))
lb = merge_lists(lb, lore_corr.get('lore_basal_actualizar', []))
lb = merge_lists(lb, lore_corr.get('lore_basal_nuevo', []))
final_lore['lore_basal'] = lb

# Merge eventos
ev = merge_lists(lore1.get('eventos', []), lore2.get('eventos', []))
ev = merge_lists(ev, lore_corr.get('eventos_actualizar', []))
ev = merge_lists(ev, lore_corr.get('eventos_nuevo', []))
final_lore['eventos'] = ev

# Merge periodicos
per = merge_lists(lore1.get('periodicos', []), lore2.get('periodicos', []))
# note: no periodicos_actualizar in lore_corr, just periodicos_nuevo
per = merge_lists(per, lore_corr.get('periodicos_nuevo', []))
final_lore['periodicos'] = per

# Sort lists by ID just to be clean
final_lore['eras'].sort(key=lambda x: x['id'])
final_lore['lore_basal'].sort(key=lambda x: x['id'])
final_lore['eventos'].sort(key=lambda x: x['id'])
final_lore['periodicos'].sort(key=lambda x: x['id'])

with codecs.open('c:/laragon/www/foro_local/back/forum/game/lore.json', 'w', encoding='utf-8') as f:
    json.dump(final_lore, f, ensure_ascii=False, indent=4)
