import codecs, re
path = 'c:/laragon/www/foro_local/back/forum/game/public/tripulacion_crear.php'
with codecs.open(path, 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace('style="max-width: 600px; margin: 0 auto;"', 'class="pj-page-shell rpg-crew-form-shell"')
content = content.replace('class="pj-page-shell rpg-crew-form-shell"', 'class="rpg-crew-form-shell"')
content = content.replace('style="margin-bottom: 24px; text-align: center;"', 'class="rpg-crew-form-heading"')
content = content.replace('style="padding: 24px;"', 'class="rpg-crew-form-group"')
content = content.replace('style="color: var(--text-muted); margin-bottom: 24px; text-align: center;"', 'class="rpg-crew-form-desc"')
content = content.replace('style="width: 100%; box-sizing: border-box; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-primary);"', 'class="textbox rpg-crew-form-input"')
content = content.replace('style="margin-top: 16px;"', 'class="rpg-crew-form-margin"')
content = content.replace('style="margin-top: 30px; text-align: center;"', 'class="rpg-crew-form-submit-wrap"')
content = content.replace('style="width: 100%; padding: 12px; font-size: 16px;"', 'class="rpg-action-btn rpg-btn-primary rpg-crew-form-submit-btn"')

content = re.sub(r'class="([^"]*)"\s+class="([^"]*)"', r'class="\1 \2"', content)

with codecs.open(path, 'w', encoding='utf-8') as f:
    f.write(content)

css = '''
/* =========================================================
   TRIPULACION CREAR STYLES
   ========================================================= */
.rpg-crew-form-shell { max-width: 600px; margin: 0 auto; }
h1.rpg-crew-form-heading { margin-bottom: 24px; text-align: center; }
div.rpg-crew-form-group { padding: 24px; }
p.rpg-crew-form-desc { color: var(--text-muted); margin-bottom: 24px; text-align: center; }
input.rpg-crew-form-input { width: 100%; box-sizing: border-box; padding: 10px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text-primary); }
div.rpg-crew-form-margin { margin-top: 16px; }
div.rpg-crew-form-submit-wrap { margin-top: 30px; text-align: center; }
button.rpg-crew-form-submit-btn { width: 100%; padding: 12px; font-size: 16px; }
'''
with codecs.open('c:/laragon/www/foro_local/back/forum/rpg_custom.css', 'a', encoding='utf-8') as f:
    f.write(css)
