<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if (!isset($mybb) || !is_object($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$uid = (int)$mybb->user['uid'];
$prefix = TABLE_PREFIX;

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
$pj_name = '';

if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT name, staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        $staff_level = (int)$pj['staff_level'];
        $pj_name = $pj['name'];
    }
}

if ($staff_level < 3) {
    header('Location: ../index.php');
    exit;
}

$b_url = $mybb->settings['bburl'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'distribute_taxes') {
    $iq = $db->query("SELECT name, controlling_type, controlling_id FROM {$prefix}game_forum_islands WHERE controlling_type IN ('pj', 'crew')");
    while ($island = $db->fetch_array($iq)) {
        if ($island['controlling_type'] === 'pj') {
            $user_q = $db->query("SELECT user_id FROM {$prefix}game_personajes WHERE id = {$island['controlling_id']}");
            if ($u = $db->fetch_array($user_q)) {
                // Notificar al dueño
                $db->query("INSERT INTO {$prefix}game_notifications (user_id, type, message, is_read) VALUES ({$u['user_id']}, 'territory_tax', 'Has recibido los beneficios e impuestos (Berries y Bienes) por el control de: " . $db->escape_string($island['name']) . ". Administra los recursos en tu ficha.', 0)");
            }
        } elseif ($island['controlling_type'] === 'crew') {
            $leader_q = $db->query("SELECT p.user_id FROM {$prefix}game_tripulaciones t JOIN {$prefix}game_personajes p ON t.leader_pj_id = p.id WHERE t.id = {$island['controlling_id']}");
            if ($u = $db->fetch_array($leader_q)) {
                $db->query("INSERT INTO {$prefix}game_notifications (user_id, type, message, is_read) VALUES ({$u['user_id']}, 'territory_tax', 'Tu tripulación ha recibido los beneficios e impuestos (Berries y Bienes) por el control de: " . $db->escape_string($island['name']) . ". Administra los recursos en vuestro inventario de tripulación.', 0)");
            }
        }
    }
    header("Location: zona_staff_islas.php?msg=taxes_distributed");
    exit;
}

// Get all forums (type 'f') with their island data
$forums = [];
$fq = $db->query("SELECT f.fid, f.name FROM {$prefix}forums f WHERE f.type = 'f' ORDER BY f.name");
while ($f = $db->fetch_array($fq)) {
    $fid = (int)$f['fid'];
    $forums[$fid] = ['name' => $f['name'], 'fid' => $fid];
}

// Load island data
if ($db->table_exists('game_forum_islands')) {
    $iq = $db->query("SELECT * FROM {$prefix}game_forum_islands");
    while ($ir = $db->fetch_array($iq)) {
        $fid = (int)$ir['fid'];
        if (isset($forums[$fid])) {
            $forums[$fid]['island_image']  = $ir['island_image'];
            $forums[$fid]['leader_name']   = $ir['leader_name'];
            $forums[$fid]['description']   = $ir['description'];
            $forums[$fid]['terrain']       = $ir['terrain'];
            $forums[$fid]['climate']       = $ir['climate'];
            $forums[$fid]['climate_temp']  = $ir['climate_temp'];
            $forums[$fid]['climate_wind']  = $ir['climate_wind'];
            $forums[$fid]['climate_precip']= $ir['climate_precip'];
            $forums[$fid]['buildings']     = $ir['buildings'];
            $forums[$fid]['defenses']      = $ir['defenses'];
            $forums[$fid]['resources']     = $ir['resources'];
            $forums[$fid]['coord_x']       = $ir['coord_x'] ?? 0;
            $forums[$fid]['coord_y']       = $ir['coord_y'] ?? 0;
            $forums[$fid]['sea_zone']      = $ir['sea_zone'] ?? 'east_blue';
            $forums[$fid]['base_danger']   = $ir['base_danger'] ?? 1;
            $forums[$fid]['requires_log_pose'] = $ir['requires_log_pose'] ?? 0;
            $forums[$fid]['requires_compass']  = $ir['requires_compass'] ?? 0;
            $forums[$fid]['controlling_type']  = $ir['controlling_type'] ?? '';
            $forums[$fid]['controlling_id']    = (int)($ir['controlling_id'] ?? 0);
        }
    }
}

ob_start();
?>
<div class="rpg-staff-zone">
  <div class="rpg-staff-header rpg-staff-header--zone">
    <div class="rpg-staff-header-content">
      <h1><i class="fas fa-island-tree-palm"></i> Gesti&oacute;n de Islas</h1>
      <p>Configura los datos RPG de cada foro-isla del foro.</p>
    </div>
  </div>

  <div class="rpg-staff-section">
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'taxes_distributed'): ?>
        <p class="rpg-bg-success-green rpg-color-white rpg-padding-10 rpg-border-radius-4 rpg-text-center">¡Impuestos y beneficios distribuidos correctamente a todos los controladores!</p>
    <?php endif; ?>
    
    <div class="rpg-display-flex rpg-justify-between rpg-align-center rpg-margin-bottom-10">
        <div>
            <h2><i class="fas fa-globe-americas"></i> Islas del Foro</h2>
            <p class="rpg-staff-info">Selecciona un foro para editar su configuraci&oacute;n de isla.</p>
        </div>
        <form method="post" onsubmit="return confirm('¿Seguro que quieres repartir los impuestos y notificar a los dueños de las islas?');">
            <input type="hidden" name="action" value="distribute_taxes">
            <button type="submit" class="rpg-btn rpg-btn--primary rpg-bg-success-green"><i class="fas fa-coins"></i> Repartir Impuestos de Territorios</button>
        </form>
    </div>

    <div class="rpg-island-card-grid">
      <?php foreach ($forums as $fid => $forum): ?>
        <?php
          $img     = htmlspecialchars($forum['island_image'] ?? '');
          $leader  = htmlspecialchars($forum['leader_name'] ?? '');
          $desc    = htmlspecialchars($forum['description'] ?? '');
          $terrain = htmlspecialchars($forum['terrain'] ?? '');
          $climate = htmlspecialchars($forum['climate'] ?? '');
          $ctemp   = htmlspecialchars($forum['climate_temp'] ?? '');
          $cwind   = htmlspecialchars($forum['climate_wind'] ?? '');
          $cprecip = htmlspecialchars($forum['climate_precip'] ?? '');
          $build   = htmlspecialchars($forum['buildings'] ?? '');
          $def     = htmlspecialchars($forum['defenses'] ?? '');
            $res     = htmlspecialchars($forum['resources'] ?? '');
          $coordX  = (int)($forum['coord_x'] ?? 0);
          $coordY  = (int)($forum['coord_y'] ?? 0);
          $seaZone = htmlspecialchars($forum['sea_zone'] ?? 'east_blue');
          $danger  = (int)($forum['base_danger'] ?? 1);
          $logPose = (int)($forum['requires_log_pose'] ?? 0);
          $compass = (int)($forum['requires_compass'] ?? 0);
          $cType   = htmlspecialchars($forum['controlling_type'] ?? '');
          $cId     = (int)($forum['controlling_id'] ?? 0);
          $fname   = htmlspecialchars($forum['name']);
        ?>
        <div class="rpg-island-card"
             data-fid="<?= $fid ?>"
             data-name="<?= $fname ?>"
             data-island_image="<?= $img ?>"
             data-leader_name="<?= $leader ?>"
             data-description="<?= $desc ?>"
             data-terrain="<?= $terrain ?>"
             data-climate="<?= $climate ?>"
             data-climate_temp="<?= $ctemp ?>"
             data-climate_wind="<?= $cwind ?>"
             data-climate_precip="<?= $cprecip ?>"
             data-buildings="<?= $build ?>"
             data-defenses="<?= $def ?>"
             data-resources="<?= $res ?>"
             data-coord_x="<?= $coordX ?>"
             data-coord_y="<?= $coordY ?>"
             data-sea_zone="<?= $seaZone ?>"
             data-base_danger="<?= $danger ?>"
             data-requires_log_pose="<?= $logPose ?>"
             data-requires_compass="<?= $compass ?>"
             data-controlling_type="<?= $cType ?>"
             data-controlling_id="<?= $cId ?>">
          <div class="rpg-island-card-img-wrap">
            <?php if ($img): ?>
              <img src="<?= $img ?>" alt="<?= $fname ?>" class="rpg-island-card-img" />
            <?php else: ?>
              <div class="rpg-island-card-img-placeholder"><i class="fas fa-map-marked-alt"></i></div>
            <?php endif; ?>
          </div>
          <h3 class="rpg-island-card-name"><?= $fname ?></h3>
          <span class="rpg-island-card-leader"><i class="fas fa-crown"></i> <?= $leader ?: '—' ?></span>
          <button class="rpg-btn--primary rpg-island-edit-btn" type="button"><i class="fas fa-edit"></i> Editar Isla</button>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Modal -->
<div id="rpg-island-modal" class="rpg-modal is-hidden">
  <div class="rpg-modal-backdrop"></div>
  <div class="rpg-modal-panel">
    <div class="rpg-modal-head">
      <h2><i class="fas fa-map-marked-alt"></i> <span id="rpg-modal-title">Editar Isla</span></h2>
      <button type="button" class="rpg-modal-close-btn" id="rpg-modal-close">&times;</button>
    </div>
    <div class="rpg-modal-body">
      <div class="rpg-forum-island-editor">
        <div class="rpg-forum-island-editor-fields">
          <div class="rpg-form-group">
            <label>Imagen de la Isla (URL)</label>
            <input type="text" class="rpg-input island-field" data-field="island_image" placeholder="https://ejemplo.com/isla.png" />
            <div class="rpg-island-preview is-hidden"></div>
          </div>
          <div class="rpg-form-group">
            <label>Líder Actual (Narrativo)</label>
            <input type="text" class="rpg-input island-field" data-field="leader_name" placeholder="Ej: Monkey D. Luffy" />
          </div>
          <h3 class="rpg-form-section-title"><i class="fas fa-flag"></i> Control Territorial (Mecánico)</h3>
          <div class="rpg-display-flex rpg-gap-10">
            <div class="rpg-form-group rpg-flex-1">
              <label>Tipo de Controlador</label>
              <select class="rpg-input island-field" data-field="controlling_type">
                <option value="">-- Ninguno --</option>
                <option value="pj">Personaje</option>
                <option value="crew">Grupo</option>
              </select>
            </div>
            <div class="rpg-form-group rpg-flex-1">
              <label>ID del Controlador</label>
              <input type="number" class="rpg-input island-field" data-field="controlling_id" value="0" />
            </div>
          </div>
          <h3 class="rpg-form-section-title"><i class="fas fa-info-circle"></i> Datos Generales</h3>
          <div class="rpg-form-group">
            <label>Descripción General</label>
            <textarea class="rpg-input island-field" data-field="description" rows="3" placeholder="Historia y descripción de la isla..."></textarea>
          </div>
          <div class="rpg-form-group">
            <label>Terreno</label>
            <input type="text" class="rpg-input island-field" data-field="terrain" placeholder="Ej: Selva tropical" />
          </div>
          <div class="rpg-form-group">
            <label>Clima - General</label>
            <input type="text" class="rpg-input island-field" data-field="climate" placeholder="Ej: Tropical húmedo" />
          </div>
          <div class="rpg-form-group">
            <label>Clima - Temperatura</label>
            <input type="text" class="rpg-input island-field" data-field="climate_temp" placeholder="Ej: 28-35°C" />
          </div>
          <div class="rpg-form-group">
            <label>Clima - Viento</label>
            <input type="text" class="rpg-input island-field" data-field="climate_wind" placeholder="Ej: Brisas suaves del este" />
          </div>
          <div class="rpg-form-group">
            <label>Clima - Precipitación</label>
            <input type="text" class="rpg-input island-field" data-field="climate_precip" placeholder="Ej: 1200mm anuales" />
          </div>
          <div class="rpg-form-group">
            <label>Zonas / Edificios</label>
            <textarea class="rpg-input island-field" data-field="buildings" rows="2" placeholder="Lugares emblemáticos..."></textarea>
          </div>
          <div class="rpg-form-group">
            <label>Defensas</label>
            <textarea class="rpg-input island-field" data-field="defenses" rows="2" placeholder="Murallas, fortificaciones..."></textarea>
          </div>
          <div class="rpg-form-group">
            <label>Recursos Naturales</label>
            <input type="text" class="rpg-input island-field" data-field="resources" placeholder="Ej: Madera, minerales, pesca" />
          </div>
          <h3 class="rpg-form-section-title"><i class="fas fa-compass"></i> Navegación</h3>
          <div class="rpg-form-group">
            <label>Coordenada X (0–1000)</label>
            <input type="number" class="rpg-input island-field" data-field="coord_x" min="0" max="1000" value="0" />
          </div>
          <div class="rpg-form-group">
            <label>Coordenada Y (0–1000)</label>
            <input type="number" class="rpg-input island-field" data-field="coord_y" min="0" max="1000" value="0" />
          </div>
          <div class="rpg-form-group">
            <label>Zona del mar</label>
            <select class="rpg-input island-field" data-field="sea_zone">
              <option value="east_blue">East Blue</option>
              <option value="west_blue">West Blue</option>
              <option value="north_blue">North Blue</option>
              <option value="south_blue">South Blue</option>
              <option value="grand_line">Grand Line</option>
              <option value="new_world">New World</option>
              <option value="calm_belt">Calm Belt</option>
              <option value="florian_triangle">Triángulo de Florian</option>
            </select>
          </div>
          <div class="rpg-form-group">
            <label>Peligro base (1–5)</label>
            <input type="number" class="rpg-input island-field" data-field="base_danger" min="1" max="5" value="1" />
          </div>
          <div class="rpg-form-group">
            <label><input type="checkbox" class="island-field-check" data-field="requires_log_pose" /> Requiere Log Pose</label>
          </div>
          <div class="rpg-form-group">
            <label><input type="checkbox" class="island-field-check" data-field="requires_compass" /> Requiere brújula (Blues)</label>
          </div>
          <span class="rpg-island-saved-msg is-hidden"><i class="fas fa-check"></i> Guardado</span>
          <button class="rpg-btn--primary rpg-island-save-btn" type="button"><i class="fas fa-save"></i> Guardar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?= $b_url ?>/jscripts/game/zona_staff_islas.js"></script>
<?php
$content = ob_get_clean();
game_render_page("Gestión de Islas", $content);