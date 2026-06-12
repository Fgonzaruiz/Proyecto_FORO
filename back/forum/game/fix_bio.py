import codecs, re
path = 'c:/laragon/www/foro_local/back/forum/game/views/tripulacion/_tab_bio.php'
with codecs.open(path, 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace('style="font-size: 16px;"', 'class="rpg-text-lg"')
content = content.replace('style="color: inherit; text-decoration: none;"', 'class="rpg-link-inherit"')
content = re.sub(r'class="([^"]*)"\s+class="([^"]*)"', r'class="\1 \2"', content)

with codecs.open(path, 'w', encoding='utf-8') as f:
    f.write(content)

css = '''
.rpg-text-lg { font-size: 16px; }
.rpg-link-inherit { color: inherit; text-decoration: none; }
'''
with codecs.open('c:/laragon/www/foro_local/back/forum/rpg_custom.css', 'a', encoding='utf-8') as f:
    f.write(css)
