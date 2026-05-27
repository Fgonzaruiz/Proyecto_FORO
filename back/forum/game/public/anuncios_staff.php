<?php
declare(strict_types=1);

define('THIS_SCRIPT', 'anuncios_staff.php');
require_once __DIR__ . '/../bootstrap.php';

game_require_staff_character();

global $mybb, $db;
$prefix = TABLE_PREFIX;

$uid = (int)$mybb->user['uid'];
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = " . (int)$cfg['active_pj_id'] . " LIMIT 1");
$pj = $db->fetch_array($pj_q);

if (!$pj || (int)$pj['staff_level'] < 3) {
    error_no_permission();
}

$bburl = $mybb->settings['bburl'];

ob_start();
?>
<div class="pj-container" style="max-width: 1000px; margin: 40px auto; padding: 20px;">
    <h1 style="font-family:var(--font-heading); font-size:32px; color:var(--text-primary); margin-bottom:30px; text-align:center; font-weight:800;">
        <i class="fas fa-bullhorn" style="color:var(--accent-indigo);"></i> Gestión de Tablón de Anuncios
    </h1>

    <div style="background:var(--bg-surface); padding:30px; border-radius:12px; border:1px solid var(--border-color); margin-bottom:30px;">
        <h2 style="font-family:var(--font-heading); font-size:24px; color:var(--text-primary); margin-bottom:20px;">Publicar Nuevo Anuncio</h2>
        
        <div class="rpg-form-group">
            <label class="rpg-form-label"><i class="fas fa-heading"></i> Título del anuncio</label>
            <input type="text" id="ann_title" class="rpg-form-input" placeholder="Ej: Mantenimiento del servidor...">
        </div>
        
        <div class="rpg-form-group">
            <label class="rpg-form-label"><i class="fas fa-align-left"></i> Contenido (acepta HTML básico)</label>
            <textarea id="ann_content" class="rpg-editor-textarea" rows="4" placeholder="Escribe el anuncio aquí..."></textarea>
        </div>
        
        <div style="text-align:right;">
            <button onclick="saveAnnouncement()" class="rpg-action-btn rpg-btn-primary">
                <i class="fas fa-paper-plane"></i> Publicar
            </button>
        </div>
    </div>

    <div style="background:var(--bg-surface); padding:30px; border-radius:12px; border:1px solid var(--border-color);">
        <h2 style="font-family:var(--font-heading); font-size:24px; color:var(--text-primary); margin-bottom:20px;">Anuncios Actuales</h2>
        
        <div id="announcements-list" style="display:flex; flex-direction:column; gap:15px;">
            <div style="text-align:center; padding:20px; color:var(--text-muted);">Cargando anuncios...</div>
        </div>
    </div>
</div>

<script>
function loadAnnouncements() {
    fetch('<?= $bburl ?>/game/ajax/announcements_list.php')
        .then(r => r.json())
        .then(res => {
            const list = document.getElementById('announcements-list');
            if (res.ok && res.data && res.data.length > 0) {
                let html = '';
                res.data.forEach(a => {
                    html += `
                        <div style="background:var(--bg-card); padding:20px; border-radius:8px; border:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div style="font-size:12px; color:var(--text-muted); margin-bottom:5px;"><i class="far fa-clock"></i> ${a.date}</div>
                                <div style="font-size:18px; font-weight:800; color:var(--text-primary); margin-bottom:8px;">${a.title}</div>
                                <div style="font-size:14px; color:var(--text-secondary);">${a.content}</div>
                            </div>
                            <div>
                                <button onclick="deleteAnnouncement(${a.id})" class="rpg-action-btn rpg-btn-secondary" style="color:var(--accent-red); border-color:var(--accent-red);">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </div>
                        </div>
                    `;
                });
                list.innerHTML = html;
            } else {
                list.innerHTML = '<div style="text-align:center; padding:20px; color:var(--text-muted);">No hay anuncios publicados.</div>';
            }
        });
}

function saveAnnouncement() {
    const title = document.getElementById('ann_title').value;
    const content = document.getElementById('ann_content').value;
    
    if (!title || !content) {
        alert("Rellena todos los campos");
        return;
    }
    
    fetch('<?= $bburl ?>/game/ajax/announcements_save.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'create', title, content })
    })
    .then(r => r.json())
    .then(res => {
        if (res.ok) {
            document.getElementById('ann_title').value = '';
            document.getElementById('ann_content').value = '';
            loadAnnouncements();
        } else {
            alert("Error: " + (res.error ? res.error.message : "Desconocido"));
        }
    });
}

function deleteAnnouncement(id) {
    if (!confirm("¿Seguro que quieres borrar este anuncio?")) return;
    
    fetch('<?= $bburl ?>/game/ajax/announcements_save.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'delete', id: id })
    })
    .then(r => r.json())
    .then(res => {
        if (res.ok) {
            loadAnnouncements();
        } else {
            alert("Error al borrar");
        }
    });
}

document.addEventListener('DOMContentLoaded', loadAnnouncements);
</script>

<?php
$content = ob_get_clean();
game_render_page('Gestión de Anuncios', $content);
