import json
import os

lore_path = r'c:\laragon\www\foro_local\back\forum\game\lore.json'
lore2_path = r'c:\laragon\www\foro_local\back\forum\game\lore2.txt'

with open(lore_path, 'r', encoding='utf-8') as f:
    lore = json.load(f)

with open(lore2_path, 'r', encoding='utf-8') as f:
    lore2 = json.load(f)

for key in ['eras', 'lore_basal', 'eventos']:
    if key in lore2:
        if key not in lore:
            lore[key] = []
        existing_ids = {item['id'] for item in lore.get(key, []) if 'id' in item}
        for item in lore2[key]:
            if 'id' in item and item['id'] in existing_ids:
                for i, ex_item in enumerate(lore[key]):
                    if ex_item.get('id') == item['id']:
                        lore[key][i] = item
            else:
                lore[key].append(item)

with open(lore_path, 'w', encoding='utf-8') as f:
    json.dump(lore, f, indent=4, ensure_ascii=False)

os.remove(lore2_path)
print('Merge complete and lore2.txt deleted.')
