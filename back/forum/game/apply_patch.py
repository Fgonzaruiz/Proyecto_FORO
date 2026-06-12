import json
import codecs

lore_path = 'c:/laragon/www/foro_local/back/forum/game/lore.json'
patch_path = 'c:/laragon/www/foro_local/back/forum/game/lorecorreciones2.json'

def load_json(path):
    with open(path, 'r', encoding='utf-8') as f:
        return json.load(f)

lore = load_json(lore_path)
patch = load_json(patch_path)

def clean_item(item):
    if '_accion' in item:
        del item['_accion']
    return item

# Apply meta
if 'meta' in patch:
    if 'meta' not in lore:
        lore['meta'] = {}
    lore['meta'].update(patch['meta'])

# Apply lore_basal
if 'lore_basal_reemplazar_o_añadir' in patch:
    items_to_apply = [clean_item(x) for x in patch['lore_basal_reemplazar_o_añadir']]
    for new_item in items_to_apply:
        found = False
        for i, existing in enumerate(lore['lore_basal']):
            if existing['id'] == new_item['id']:
                lore['lore_basal'][i] = new_item
                found = True
                break
        if not found:
            lore['lore_basal'].append(new_item)

# Apply eventos
if 'eventos_reemplazar_o_añadir' in patch:
    items_to_apply = [clean_item(x) for x in patch['eventos_reemplazar_o_añadir']]
    for new_item in items_to_apply:
        found = False
        for i, existing in enumerate(lore['eventos']):
            if existing['id'] == new_item['id']:
                lore['eventos'][i] = new_item
                found = True
                break
        if not found:
            lore['eventos'].append(new_item)

# Apply periodicos
if 'periodicos_añadir' in patch:
    items_to_apply = [clean_item(x) for x in patch['periodicos_añadir']]
    for new_item in items_to_apply:
        found = False
        for i, existing in enumerate(lore['periodicos']):
            if existing['id'] == new_item['id']:
                lore['periodicos'][i] = new_item
                found = True
                break
        if not found:
            lore['periodicos'].append(new_item)

# Sort
lore['lore_basal'].sort(key=lambda x: x['id'])
lore['eventos'].sort(key=lambda x: x['id'])
lore['periodicos'].sort(key=lambda x: x['id'])

with codecs.open(lore_path, 'w', encoding='utf-8') as f:
    json.dump(lore, f, ensure_ascii=False, indent=4)
