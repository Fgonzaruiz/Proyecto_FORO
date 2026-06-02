<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if (!isset($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$uid = (int)$mybb->user['uid'];
$prefix = TABLE_PREFIX;

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$cid = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
$pj_name = '';

if ($cid > 0) {
    $pj_q = $db->query("SELECT name, staff_level FROM {$prefix}game_personajes WHERE id = {$cid} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        $staff_level = (int)$pj['staff_level'];
        $pj_name = $pj['name'];
    }
}

if ($staff_level < 2) {
    header('Location: ../index.php');
    exit;
}

$b_url = $mybb->settings['bburl'];

ob_start();
?>
<div class="rpg-staff-zone" style="max-width: 1100px; margin: 40px auto; padding: 0 20px;">
    <div class="rpg-staff-header" style="background: linear-gradient(135deg, rgba(244,63,94,0.15), rgba(139,92,246,0.1)); border-radius: 10px; padding: 30px; margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h1 style="font-family: var(--font-heading); font-size: 28px; color: var(--text-primary); margin: 0 0 5px 0; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-search" style="color: var(--accent-rose);"></i> Búsquedas de Rol Pendientes
            </h1>
            <p style="color: var(--text-secondary); margin: 0;">Revisa y responde las búsquedas enviadas por los jugadores.</p>
        </div>
        <a href="<?= $b_url ?>/game/public/zona_staff.php" style="color: var(--text-muted); text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 6px; transition: color 0.2s;" onmouseover="this.style.color='var(--text-primary)'" onmouseout="this.style.color='var(--text-muted)'">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
    </div>

    <div id="busquedas-staff-list">
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Cargando búsquedas...
        </div>
    </div>

    <!-- Modal de revisión -->
    <div id="busqueda-review-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
        <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; width: 90%; max-width: 650px; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-main);">
            <div style="padding: 25px 30px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                <h3 id="modal-review-titulo" style="margin:0; font-size: 20px; color: var(--text-primary);"></h3>
                <button onclick="closeBusquedaReview()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:20px;"><i class="fas fa-times"></i></button>
            </div>
            <div style="padding: 25px 30px;">
                <img id="modal-review-img" src="" style="width:100%; height:220px; object-fit:cover; border-radius:8px; margin-bottom:20px; display:none;" />
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
                    <img id="modal-review-avatar" src="" style="width:50px; height:50px; border-radius:50%; object-fit:cover;" />
                    <div>
                        <div id="modal-review-pj" style="font-weight: 700; color: var(--text-primary);"></div>
                        <div id="modal-review-date" style="font-size: 12px; color: var(--text-muted);"></div>
                    </div>
                </div>
                <div id="modal-review-desc" style="font-size: 14px; color: var(--text-secondary); line-height: 1.7; margin-bottom: 20px; background: var(--bg-main); padding: 15px; border-radius: 8px;"></div>

                <input type="hidden" id="modal-review-id" value="" />
                <label style="font-size: 13px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px; display: block;">Nota para el jugador (opcional):</label>
                <textarea id="modal-review-nota" rows="3" style="width:100%; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; color: var(--text-primary); font-size: 13px; resize: vertical; box-sizing: border-box;" placeholder="Añade una nota que recibirá el jugador..."></textarea>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button onclick="accionBusqueda('aprobar')" style="flex:1; background: var(--accent-emerald); color: #fff; border: none; border-radius: 6px; padding: 12px; font-weight: 700; cursor: pointer; font-size: 15px; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                        <i class="fas fa-check"></i> Aprobar y publicar
                    </button>
                    <button onclick="accionBusqueda('denegar')" style="flex:1; background: var(--accent-rose); color: #fff; border: none; border-radius: 6px; padding: 12px; font-weight: 700; cursor: pointer; font-size: 15px; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                        <i class="fas fa-times"></i> Denegar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var busquedas_list = [];
var bburl = '<?= $b_url ?>';

function loadBusquedasStaff() {
    fetch(bburl + '/game/ajax/busquedas_pending.php')
        .then(r => r.json())
        .then(res => {
            var container = document.getElementById('busquedas-staff-list');
            if (!res.ok) {
                container.innerHTML = '<div style="text-align:center; color:var(--accent-rose); padding:30px;">' + res.error + '</div>';
                return;
            }
            busquedas_list = res.data;
            if (!res.data || res.data.length === 0) {
                container.innerHTML = '<div style="text-align:center; padding:60px; color:var(--text-muted);"><i class="fas fa-check-circle fa-3x" style="color:var(--accent-emerald); margin-bottom:15px;"></i><br><strong>¡Todo al día!</strong><br>No hay búsquedas pendientes de revisión.</div>';
                return;
            }
            var html = '<div style="display:flex; flex-direction:column; gap:15px;">';
            res.data.forEach(function(b) {
                html += '<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 10px; padding: 20px; display:flex; gap:20px; align-items:center; transition: border-color 0.2s;" onmouseover="this.style.borderColor=\'var(--accent-rose)\'" onmouseout="this.style.borderColor=\'var(--border-color)\'">' +
                    (b.imagen_url ? '<img src="' + b.imagen_url + '" style="width:80px; height:80px; object-fit:cover; border-radius:8px; flex-shrink:0;">' : '<div style="width:80px; height:80px; background:var(--bg-main); border-radius:8px; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:var(--text-muted);"><i class="fas fa-image fa-2x"></i></div>') +
                    '<div style="flex:1;">' +
                        '<div style="font-weight:800; font-size:17px; color:var(--text-primary); margin-bottom:5px;">' + b.titulo + '</div>' +
                        '<div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;"><img src="' + b.pj_avatar + '" style="width:24px; height:24px; border-radius:50%; object-fit:cover;"><span style="font-size:13px; color:var(--text-secondary);">' + b.pj_name + ' · ' + b.date + '</span></div>' +
                        '<div style="font-size:13px; color:var(--text-secondary);">' + b.descripcion.substring(0,120) + (b.descripcion.length > 120 ? '...' : '') + '</div>' +
                    '</div>' +
                    '<button onclick="openBusquedaReview(' + b.id + ')" style="background: var(--accent-rose); color:#fff; border:none; border-radius:6px; padding:10px 18px; font-weight:700; cursor:pointer; flex-shrink:0; transition: opacity 0.2s;" onmouseover="this.style.opacity=\'0.85\'" onmouseout="this.style.opacity=\'1\'">Revisar</button>' +
                '</div>';
            });
            html += '</div>';
            container.innerHTML = html;
        });
}

function openBusquedaReview(id) {
    var b = busquedas_list.find(function(x) { return x.id === id; });
    if (!b) return;
    document.getElementById('modal-review-id').value = b.id;
    document.getElementById('modal-review-titulo').textContent = b.titulo;
    document.getElementById('modal-review-desc').textContent = b.descripcion;
    document.getElementById('modal-review-pj').textContent = b.pj_name;
    document.getElementById('modal-review-date').textContent = b.date;
    document.getElementById('modal-review-avatar').src = b.pj_avatar;
    document.getElementById('modal-review-nota').value = '';
    var img = document.getElementById('modal-review-img');
    if (b.imagen_url) {
        img.src = b.imagen_url;
        img.style.display = 'block';
    } else {
        img.style.display = 'none';
    }
    var modal = document.getElementById('busqueda-review-modal');
    modal.style.display = 'flex';
}

function closeBusquedaReview() {
    document.getElementById('busqueda-review-modal').style.display = 'none';
}

function accionBusqueda(accion) {
    var id = document.getElementById('modal-review-id').value;
    var nota = document.getElementById('modal-review-nota').value;
    var fd = new FormData();
    fd.append('id', id);
    fd.append('accion', accion);
    fd.append('nota', nota);

    (window.gamePostForm
      ? window.gamePostForm(bburl + '/game/ajax/busquedas_action.php', fd)
      : fetch(bburl + '/game/ajax/busquedas_action.php', {
          method: 'POST',
          headers: { 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
          credentials: 'same-origin',
          body: (function () {
            if (window.GAME_CSRF) { fd.append('my_post_key', window.GAME_CSRF); }
            return fd;
          })()
        }).then(function (r) { return r.json(); })
    ).then(function (res) {
            if (res.ok) {
                closeBusquedaReview();
                loadBusquedasStaff();
            } else {
                alert('Error: ' + res.error);
            }
        });
}

loadBusquedasStaff();
</script>
<?php
$content = ob_get_clean();
game_render_page('Búsquedas de Rol — Staff', $content);
