<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb;

if (!isset($mybb) || !is_object($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$b_url = $mybb->settings['bburl'];

ob_start();
?>
<div class="rpg-peticiones">
  <div class="rpg-peticiones-header">
    <div class="rpg-peticiones-header-content">
      <h1><i class="fas fa-envelope"></i> Peticiones Generales</h1>
      <p>Selecciona el tipo de petici&oacute;n que deseas realizar.</p>
    </div>
  </div>

  <div class="rpg-peticiones-grid">
    <a class="rpg-peticion-card" href="#">
      <div class="rpg-peticion-card-icon" style="background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));">
        <i class="fas fa-apple-alt"></i>
      </div>
      <div class="rpg-peticion-card-body">
        <h3>Akuma no Mi</h3>
        <p>Solicita una fruta del diablo, consulta poderes disponibles o reporta una fruta en juego.</p>
      </div>
      <div class="rpg-peticion-card-arrow">
        <i class="fas fa-arrow-right"></i>
      </div>
    </a>

    <a class="rpg-peticion-card" href="#">
      <div class="rpg-peticion-card-icon" style="background: linear-gradient(135deg, var(--accent-amber), var(--accent-orange));">
        <i class="fas fa-hand-fist"></i>
      </div>
      <div class="rpg-peticion-card-body">
        <h3>Haki</h3>
        <p>Gestiona el despertar o entrenamiento de tu Haki: Armadura, Observaci&oacute;n y Rey.</p>
      </div>
      <div class="rpg-peticion-card-arrow">
        <i class="fas fa-arrow-right"></i>
      </div>
    </a>

    <a class="rpg-peticion-card" href="#">
      <div class="rpg-peticion-card-icon" style="background: linear-gradient(135deg, var(--accent-teal), var(--accent-emerald));">
        <i class="fas fa-store"></i>
      </div>
      <div class="rpg-peticion-card-body">
        <h3>Tienda</h3>
        <p>Compra y venta de objetos, equipamiento y recursos del juego.</p>
      </div>
      <div class="rpg-peticion-card-arrow">
        <i class="fas fa-arrow-right"></i>
      </div>
    </a>

    <!-- BÚSQUEDA DE ROL -->
    <a class="rpg-peticion-card" href="#" onclick="openBusquedaModal(event)">
      <div class="rpg-peticion-card-icon" style="background: linear-gradient(135deg, var(--accent-rose), var(--accent-purple));">
        <i class="fas fa-search-heart"></i>
      </div>
      <div class="rpg-peticion-card-body">
        <h3>B&uacute;squeda de Rol</h3>
        <p>Publica una b&uacute;squeda de trama o compa&ntilde;ero de rol que aparecer&aacute; en el tabl&oacute;n del foro.</p>
      </div>
      <div class="rpg-peticion-card-arrow">
        <i class="fas fa-arrow-right"></i>
      </div>
    </a>
  </div>
</div>

<!-- MODAL: B&Uacute;SQUEDA DE ROL -->
<div id="busqueda-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
  <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; width: 90%; max-width: 580px; box-shadow: var(--shadow-main);">
    <div style="padding: 25px 30px; border-bottom: 1px solid var(--border-color); display:flex; align-items:center; justify-content:space-between;">
      <h3 style="margin:0; font-size:20px; color:var(--text-primary); display:flex; align-items:center; gap:10px;">
        <i class="fas fa-search-heart" style="color:var(--accent-rose);"></i> Nueva B&uacute;squeda de Rol
      </h3>
      <button onclick="closeBusquedaModal()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:20px;"><i class="fas fa-times"></i></button>
    </div>
    <div style="padding: 25px 30px;">
      <p style="font-size:13px; color:var(--text-muted); margin:0 0 20px 0;">Tu b&uacute;squeda pasar&aacute; por revisi&oacute;n del staff antes de publicarse en el tabl&oacute;n.</p>

      <label style="font-size:13px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;">T&iacute;tulo <span style="color:var(--accent-rose);">*</span></label>
      <input type="text" id="busqueda-titulo" placeholder="Ej: Busco compa&ntilde;ero para trama pirata..." maxlength="120" style="width:100%; background:var(--bg-main); border:1px solid var(--border-color); border-radius:6px; padding:10px 14px; color:var(--text-primary); font-size:14px; box-sizing:border-box; margin-bottom:16px; transition:border-color 0.2s;" onfocus="this.style.borderColor='var(--accent-rose)'" onblur="this.style.borderColor='var(--border-color)'">

      <label style="font-size:13px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;">Imagen (URL) &mdash; <span style="font-weight:400; color:var(--text-muted);">opcional, pero recomendada</span></label>
      <input type="url" id="busqueda-imagen" placeholder="https://i.imgur.com/...jpg" style="width:100%; background:var(--bg-main); border:1px solid var(--border-color); border-radius:6px; padding:10px 14px; color:var(--text-primary); font-size:14px; box-sizing:border-box; margin-bottom:16px; transition:border-color 0.2s;" onfocus="this.style.borderColor='var(--accent-rose)'" onblur="this.style.borderColor='var(--border-color)'">

      <label style="font-size:13px; font-weight:700; color:var(--text-primary); margin-bottom:6px; display:block;">Descripci&oacute;n <span style="color:var(--accent-rose);">*</span></label>
      <textarea id="busqueda-desc" rows="5" placeholder="Describe qu&eacute; buscas, qu&eacute; tipo de historia, personajes ideales, disponibilidad..." style="width:100%; background:var(--bg-main); border:1px solid var(--border-color); border-radius:6px; padding:10px 14px; color:var(--text-primary); font-size:14px; box-sizing:border-box; resize:vertical; transition:border-color 0.2s;" onfocus="this.style.borderColor='var(--accent-rose)'" onblur="this.style.borderColor='var(--border-color)'"></textarea>

      <div id="busqueda-msg" style="margin-top:12px; font-size:13px; display:none;"></div>

      <button onclick="submitBusqueda()" id="busqueda-btn" style="margin-top:16px; width:100%; background: linear-gradient(135deg, var(--accent-rose), var(--accent-purple)); color:#fff; border:none; border-radius:8px; padding:14px; font-weight:800; font-size:15px; cursor:pointer; transition:opacity 0.2s; letter-spacing:1px;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
        <i class="fas fa-paper-plane"></i> Enviar al Staff
      </button>
    </div>
  </div>
</div>

<script>
var bburl_pet = '<?= $b_url ?>';

function openBusquedaModal(e) {
    e.preventDefault();
    document.getElementById('busqueda-modal').style.display = 'flex';
    document.getElementById('busqueda-titulo').value = '';
    document.getElementById('busqueda-imagen').value = '';
    document.getElementById('busqueda-desc').value = '';
    document.getElementById('busqueda-msg').style.display = 'none';
}
function closeBusquedaModal() {
    document.getElementById('busqueda-modal').style.display = 'none';
}
function submitBusqueda() {
    var titulo = document.getElementById('busqueda-titulo').value.trim();
    var imagen = document.getElementById('busqueda-imagen').value.trim();
    var desc = document.getElementById('busqueda-desc').value.trim();
    var msg = document.getElementById('busqueda-msg');
    var btn = document.getElementById('busqueda-btn');

    if (titulo.length < 3) {
        msg.innerHTML = '<span style="color:var(--accent-rose);"><i class="fas fa-exclamation-circle"></i> El título es demasiado corto.</span>';
        msg.style.display = 'block';
        return;
    }
    if (desc.length < 10) {
        msg.innerHTML = '<span style="color:var(--accent-rose);"><i class="fas fa-exclamation-circle"></i> La descripción es demasiado corta.</span>';
        msg.style.display = 'block';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

    var fd = new FormData();
    fd.append('titulo', titulo);
    fd.append('imagen_url', imagen);
    fd.append('descripcion', desc);

    fetch(bburl_pet + '/game/ajax/busquedas_submit.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            if (res.ok) {
                btn.innerHTML = '<i class="fas fa-check"></i> ¡Enviado!';
                btn.style.background = 'var(--accent-emerald)';
                msg.innerHTML = '<span style="color:var(--accent-emerald);"><i class="fas fa-check-circle"></i> Tu búsqueda ha sido enviada al staff para revisión.</span>';
                msg.style.display = 'block';
                setTimeout(closeBusquedaModal, 2000);
            } else {
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar al Staff';
                msg.innerHTML = '<span style="color:var(--accent-rose);"><i class="fas fa-exclamation-circle"></i> ' + res.error + '</span>';
                msg.style.display = 'block';
            }
        });
}
document.getElementById('busqueda-modal').addEventListener('click', function(e) {
    if (e.target === this) closeBusquedaModal();
});
</script>
<?php
$content = ob_get_clean();
game_render_page('Peticiones Generales', $content);
