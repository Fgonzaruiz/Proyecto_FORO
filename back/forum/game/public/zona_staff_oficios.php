<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if (!$uid || game_get_active_staff_level($uid) < 3) {
    header('Location: ' . ($uid ? '../index.php' : '../../member.php?action=login'));
    exit;
}

$b_url = $mybb->settings['bburl'];
$oficios = game_oficio_list_catalog(false);

ob_start();
?>
<div class="rpg-staff-zone">
  <div class="rpg-staff-header rpg-staff-header--zone">
    <div class="rpg-staff-header-content">
      <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver</a>
      <h1><i class="fas fa-briefcase"></i> Catálogo de Oficios</h1>
      <p>Gestiona oficios (grados I–V) del sistema.</p>
    </div>
  </div>

  <div class="rpg-staff-section">
    <button type="button" class="rpg-btn--primary" id="btn-new-oficio"><i class="fas fa-plus"></i> Nuevo Oficio</button>
    <div class="rpg-staff-table-wrap rpg-staff-table-wrap--spaced">
      <table class="rpg-staff-table">
        <thead>
          <tr><th>Slug</th><th>Nombre</th><th>Categoría</th><th>Activo</th><th></th></tr>
        </thead>
        <tbody id="oficios-tbody">
          <?php foreach ($oficios as $o): ?>
          <tr data-id="<?= (int)$o['id'] ?>"
              data-slug="<?= htmlspecialchars($o['slug']) ?>"
              data-name="<?= htmlspecialchars($o['name']) ?>"
              data-description="<?= htmlspecialchars($o['description'] ?? '') ?>"
              data-category="<?= htmlspecialchars($o['category'] ?? '') ?>"
              data-icon="<?= htmlspecialchars($o['icon'] ?? 'fa-briefcase') ?>"
              data-active="<?= (int)$o['is_active'] ?>"
              data-sort="<?= (int)$o['sort_order'] ?>">
            <td><code><?= htmlspecialchars($o['slug']) ?></code></td>
            <td><i class="fas <?= htmlspecialchars($o['icon'] ?? 'fa-briefcase') ?>"></i> <?= htmlspecialchars($o['name']) ?></td>
            <td><?= htmlspecialchars($o['category']) ?></td>
            <td><?= (int)$o['is_active'] ? 'Sí' : 'No' ?></td>
            <td><button type="button" class="rpg-btn--secondary rpg-btn--sm btn-edit-oficio">Editar</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="rpg-staff-section">
    <h2><i class="fas fa-user-tag"></i> Asignar oficio a personaje</h2>
    <div class="rpg-form-row">
      <input type="number" id="assign-char-id" class="rpg-input" placeholder="ID personaje" min="1" />
      <select id="assign-oficio-id" class="rpg-input">
        <?php foreach ($oficios as $o): ?>
          <option value="<?= (int)$o['id'] ?>"><?= htmlspecialchars($o['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="assign-rank" class="rpg-input">
        <option value="1">Grado I</option>
        <option value="2">Grado II</option>
        <option value="3">Grado III</option>
        <option value="4">Grado IV</option>
        <option value="5">Grado V</option>
      </select>
      <button type="button" class="rpg-btn--primary" id="btn-assign-oficio">Asignar</button>
    </div>
  </div>
</div>

<div id="oficio-modal" class="rpg-modal is-hidden">
  <div class="rpg-modal-backdrop"></div>
  <div class="rpg-modal-panel">
    <div class="rpg-modal-head">
      <h2 id="oficio-modal-title">Oficio</h2>
      <button type="button" class="rpg-modal-close-btn" id="oficio-modal-close">&times;</button>
    </div>
    <div class="rpg-modal-body">
      <input type="hidden" id="oficio-id" value="0" />
      <div class="rpg-form-group"><label>Slug</label><input type="text" id="oficio-slug" class="rpg-input" /></div>
      <div class="rpg-form-group"><label>Nombre</label><input type="text" id="oficio-name" class="rpg-input" /></div>
      <div class="rpg-form-group"><label>Descripción</label><textarea id="oficio-desc" class="rpg-input" rows="3"></textarea></div>
      <div class="rpg-form-group"><label>Categoría</label><input type="text" id="oficio-category" class="rpg-input" /></div>
      <div class="rpg-form-group"><label>Icono FA</label><input type="text" id="oficio-icon" class="rpg-input" placeholder="fa-compass" /></div>
      <div class="rpg-form-group"><label><input type="checkbox" id="oficio-active" checked /> Activo</label></div>
      <button type="button" class="rpg-btn--primary" id="oficio-save-btn"><i class="fas fa-save"></i> Guardar</button>
    </div>
  </div>
</div>

<script src="<?= $b_url ?>/jscripts/game/zona_staff_oficios.js"></script>
<?php
$content = ob_get_clean();
game_render_page('Gestión de Oficios', $content);
