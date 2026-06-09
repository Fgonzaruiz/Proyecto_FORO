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
$disciplinas = game_disciplina_list_catalog(false);

ob_start();
?>
<div class="rpg-staff-zone">
  <div class="rpg-staff-header rpg-staff-header--zone">
    <div class="rpg-staff-header-content">
      <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver</a>
      <h1><i class="fas fa-crosshairs"></i> Catálogo de Disciplinas</h1>
      <p>Gestiona disciplinas de combate (grados I–V) del sistema.</p>
    </div>
  </div>

  <div class="rpg-staff-section">
    <button type="button" class="rpg-btn--primary" id="btn-new-disciplina"><i class="fas fa-plus"></i> Nueva Disciplina</button>
    <div class="rpg-staff-table-wrap rpg-staff-table-wrap--spaced">
      <table class="rpg-staff-table">
        <thead>
          <tr><th>Slug</th><th>Nombre</th><th>Categoría</th><th>Activo</th><th></th></tr>
        </thead>
        <tbody id="disciplinas-tbody">
          <?php foreach ($disciplinas as $d): ?>
          <tr data-id="<?= (int)$d['id'] ?>"
              data-slug="<?= htmlspecialchars($d['slug']) ?>"
              data-name="<?= htmlspecialchars($d['name']) ?>"
              data-description="<?= htmlspecialchars($d['description'] ?? '') ?>"
              data-category="<?= htmlspecialchars($d['category'] ?? '') ?>"
              data-icon="<?= htmlspecialchars($d['icon'] ?? 'fa-crosshairs') ?>"
              data-active="<?= (int)$d['is_active'] ?>"
              data-sort="<?= (int)$d['sort_order'] ?>">
            <td><code><?= htmlspecialchars($d['slug']) ?></code></td>
            <td><i class="fas <?= htmlspecialchars($d['icon'] ?? 'fa-crosshairs') ?>"></i> <?= htmlspecialchars($d['name']) ?></td>
            <td><?= htmlspecialchars($d['category']) ?></td>
            <td><?= (int)$d['is_active'] ? 'Sí' : 'No' ?></td>
            <td><button type="button" class="rpg-btn--secondary rpg-btn--sm btn-edit-disciplina">Editar</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="rpg-staff-section">
    <h2><i class="fas fa-user-tag"></i> Asignar disciplina a personaje</h2>
    <div class="rpg-form-row">
      <input type="number" id="assign-char-id" class="rpg-input" placeholder="ID personaje" min="1" />
      <select id="assign-disciplina-id" class="rpg-input">
        <?php foreach ($disciplinas as $d): ?>
          <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="assign-rank" class="rpg-input">
        <option value="1">Grado I</option>
        <option value="2">Grado II</option>
        <option value="3">Grado III</option>
        <option value="4">Grado IV</option>
        <option value="5">Grado V</option>
      </select>
      <button type="button" class="rpg-btn--primary" id="btn-assign-disciplina">Asignar</button>
    </div>
  </div>
</div>

<div id="disciplina-modal" class="rpg-modal is-hidden">
  <div class="rpg-modal-backdrop"></div>
  <div class="rpg-modal-panel">
    <div class="rpg-modal-head">
      <h2 id="disciplina-modal-title">Disciplina</h2>
      <button type="button" class="rpg-modal-close-btn" id="disciplina-modal-close">&times;</button>
    </div>
    <div class="rpg-modal-body">
      <input type="hidden" id="disciplina-id" value="0" />
      <div class="rpg-form-group"><label>Slug</label><input type="text" id="disciplina-slug" class="rpg-input" /></div>
      <div class="rpg-form-group"><label>Nombre</label><input type="text" id="disciplina-name" class="rpg-input" /></div>
      <div class="rpg-form-group"><label>Descripción</label><textarea id="disciplina-desc" class="rpg-input" rows="3"></textarea></div>
      <div class="rpg-form-group"><label>Categoría</label><input type="text" id="disciplina-category" class="rpg-input" /></div>
      <div class="rpg-form-group"><label>Icono FA</label><input type="text" id="disciplina-icon" class="rpg-input" placeholder="fa-crosshairs" /></div>
      <div class="rpg-form-group"><label><input type="checkbox" id="disciplina-active" checked /> Activo</label></div>
      <button type="button" class="rpg-btn--primary" id="disciplina-save-btn"><i class="fas fa-save"></i> Guardar</button>
    </div>
  </div>
</div>

<script src="<?= $b_url ?>/jscripts/game/zona_staff_disciplinas.js"></script>
<?php
$content = ob_get_clean();
game_render_page('Gestión de Disciplinas', $content);
