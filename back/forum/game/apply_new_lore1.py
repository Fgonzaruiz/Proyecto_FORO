import json
import codecs

lore_path = 'c:/laragon/www/foro_local/back/forum/game/lore.json'
new_lore_path = 'c:/laragon/www/foro_local/back/forum/game/lore1.json'

def load_json(path):
    with open(path, 'r', encoding='utf-8') as f:
        return json.load(f)

lore = load_json(lore_path)
patch = load_json(new_lore_path)

# Apply meta
if 'meta' in patch:
    if 'meta' not in lore:
        lore['meta'] = {}
    lore['meta'].update(patch['meta'])

def apply_list(key):
    if key in patch:
        for new_item in patch[key]:
            found = False
            for i, existing in enumerate(lore.get(key, [])):
                if existing['id'] == new_item['id']:
                    lore[key][i] = new_item
                    found = True
                    break
            if not found:
                if key not in lore:
                    lore[key] = []
                lore[key].append(new_item)

apply_list('eras')
apply_list('lore_basal')
apply_list('eventos')
apply_list('periodicos')

# Sort
if 'eras' in lore: lore['eras'].sort(key=lambda x: x['id'])
if 'lore_basal' in lore: lore['lore_basal'].sort(key=lambda x: x['id'])
if 'eventos' in lore: lore['eventos'].sort(key=lambda x: x['id'])
if 'periodicos' in lore: lore['periodicos'].sort(key=lambda x: x['id'])

with codecs.open(lore_path, 'w', encoding='utf-8') as f:
    json.dump(lore, f, ensure_ascii=False, indent=4)
