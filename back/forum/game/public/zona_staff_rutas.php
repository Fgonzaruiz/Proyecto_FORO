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
$prefix = TABLE_PREFIX;
$islands = [];
$fq = $db->query("SELECT i.fid, f.name FROM {$prefix}game_forum_islands i JOIN {$prefix}forums f ON f.fid = i.fid ORDER BY f.name");
while ($r = $db->fetch_array($fq)) {
    $islands[] = $r;
}

ob_start();
?>
<div class="rpg-staff-zone">
  <div class="rpg-staff-header rpg-staff-header--zone">
    <div class="rpg-staff-header-content">
      <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver</a>
      <h1><i class="fas fa-route"></i> Rutas de Navegación</h1>
      <p>Define distancias canónicas y waypoints entre islas.</p>
    </div>
  </div>

  <div class="rpg-staff-section">
    <h2>Nueva ruta</h2>
    <div class="rpg-form-row rpg-form-row--wrap">
      <select id="route-from" class="rpg-input">
        <option value="">Origen</option>
        <?php foreach ($islands as $i): ?>
          <option value="<?= (int)$i['fid'] ?>"><?= htmlspecialchars($i['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="route-to" class="rpg-input">
        <option value="">Destino</option>
        <?php foreach ($islands as $i): ?>
          <option value="<?= (int)$i['fid'] ?>"><?= htmlspecialchars($i['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="number" id="route-distance" class="rpg-input" placeholder="Distancia (leguas)" min="1" />
      <input type="number" id="route-danger" class="rpg-input" placeholder="Peligro override (1-5)" min="1" max="5" />
      <button type="button" class="rpg-btn--primary" id="route-save-btn"><i class="fas fa-save"></i> Guardar</button>
    </div>
  </div>

  <div class="rpg-staff-section">
    <h2>Rutas existentes</h2>
    <div id="routes-list" class="rpg-staff-table-wrap"><p>Cargando…</p></div>
  </div>
</div>
<script>
window.RUTAS_STAFF_CONFIG = { ajaxBase: '<?= rtrim($b_url, '/') ?>/game/ajax', csrf: '<?= htmlspecialchars($mybb->post_code ?? '', ENT_QUOTES) ?>' };
</script>
<script src="<?= $b_url ?>/jscripts/game/zona_staff_rutas.js"></script>
<?php
$content = ob_get_clean();
game_render_page('Rutas de Navegación', $content);
