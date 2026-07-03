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

$activePjId = game_get_active_pj_id($uid);
$activePj = null;
$pjLevel = 1;
$pjName = '';

if ($activePjId > 0) {
    $pjQ = $db->query("SELECT name, data_json FROM {$prefix}game_personajes WHERE id = {$activePjId} LIMIT 1");
    $activePj = $db->fetch_array($pjQ);
    if ($activePj) {
        $pjName = $activePj['name'];
        $pjData = !empty($activePj['data_json']) ? json_decode($activePj['data_json'], true) : [];
        $pjLevel = (int)($pjData['nivel'] ?? 1);
        $pjFaction = get_standard_faction($activePj['faction'] ?? 'Civil'); // Needs function or check db
    }
} else {
    $pjFaction = 'Civil';
}

function get_standard_faction(?string $faction): string {
    if (!$faction) return 'Civil';
    $fac = mb_strtolower(trim($faction));
    if (strpos($fac, 'marine') !== false || strpos($fac, 'marina') !== false) return 'Marine';
    if (strpos($fac, 'revolucion') !== false) return 'Revolucionario';
    if (strpos($fac, 'gobierno') !== false) return 'Gobierno';
    if (strpos($fac, 'cazador') !== false) return 'Cazador';
    if (strpos($fac, 'pirata') !== false || strpos($fac, 'paja') !== false) return 'Pirata';
    return 'Civil';
}

// 1. Fetch active mission / companion invitation
$activeMission = null;
$invitation = null;

if ($activePjId > 0) {
    // Check if participant has confirmed active/pending mission
    $amQ = $db->query("
        SELECT ma.*, m.title, m.description, m.rank, m.points_reward, m.berry_reward, m.isla, m.categoria, m.max_posts,
               mp.confirmed
        FROM {$prefix}game_missions_active ma
        JOIN {$prefix}game_missions m ON ma.mission_id = m.id
        JOIN {$prefix}game_mission_participants mp ON mp.active_mission_id = ma.id
        WHERE mp.character_id = {$activePjId} AND ma.status IN ('pending', 'active', 'review')
        LIMIT 1
    ");
    $amRow = $db->fetch_array($amQ);
    if ($amRow) {
        if ((int)$amRow['confirmed'] === 1) {
            $activeMission = $amRow;
        } else if ((int)$amRow['confirmed'] === 0) {
            // Fetch leader name
            $lQ = $db->query("SELECT name FROM {$prefix}game_personajes WHERE id = " . (int)$amRow['leader_character_id'] . " LIMIT 1");
            $lName = $db->fetch_field($lQ, 'name') ?: 'Líder';
            $invitation = [
                'active_mission_id' => (int)$amRow['id'],
                'title' => $amRow['title'],
                'leader_name' => $lName,
                'rank' => $amRow['rank'],
            ];
        }
    }
}

// 2. Fetch filters
$filterRank = isset($_GET['rank']) ? trim((string)$_GET['rank']) : '';
$filterIsla = isset($_GET['isla']) ? trim((string)$_GET['isla']) : '';
$filterCat = isset($_GET['categoria']) ? trim((string)$_GET['categoria']) : '';

// 3. Build missions query
$where = ["is_active = 1"];
$factionEsc = $db->escape_string($pjFaction);
$where[] = "(faction = 'Global' OR faction = '{$factionEsc}')";

if ($filterRank !== '') {
    $where[] = "`rank` = '" . $db->escape_string($filterRank) . "'";
}
if ($filterIsla !== '') {
    $where[] = "isla = '" . $db->escape_string($filterIsla) . "'";
}
if ($filterCat !== '') {
    $where[] = "categoria = '" . $db->escape_string($filterCat) . "'";
}
$whereSql = implode(' AND ', $where);

$mQuery = $db->query("
    SELECT * FROM {$prefix}game_missions 
    WHERE {$whereSql} 
    ORDER BY FIELD(`rank`, 'D', 'C', 'B', 'A', 'S') ASC, title ASC
");
$missions = [];
while ($m = $db->fetch_array($mQuery)) {
    $missions[] = $m;
}

// 4. Fetch list of potential companions (all other approved characters)
$companions = [];
if ($activePjId > 0) {
    $compQ = $db->query("
        SELECT id, name FROM {$prefix}game_personajes 
        WHERE id != {$activePjId} AND status = 'aprobada' AND is_npc = 0 AND name NOT IN ('Narrador', 'STAFF') 
        ORDER BY name ASC
    ");
    while ($c = $db->fetch_array($compQ)) {
        $companions[] = $c;
    }
}

// Fetch list of unique islands and categories for filter options
$islands = [];
$iq = $db->query("SELECT DISTINCT isla FROM {$prefix}game_missions WHERE is_active = 1 ORDER BY isla ASC");
while ($iRow = $db->fetch_array($iq)) {
    $islands[] = $iRow['isla'];
}

$categories = [];
$cq = $db->query("SELECT DISTINCT categoria FROM {$prefix}game_missions WHERE is_active = 1 ORDER BY categoria ASC");
while ($cRow = $db->fetch_array($cq)) {
    $categories[] = $cRow['categoria'];
}

$b_url = $mybb->settings['bburl'];

ob_start();
?>
<div class="rpg-misiones-board">
  <div class="rpg-misiones-header">
    <div class="rpg-misiones-header-content">
      <h1><i class="fas fa-compass"></i> Tablón de Misiones Oficiales</h1>
      <p>Misiones disponibles para la facción: <strong><?= htmlspecialchars($pjFaction) ?></strong> y Globales.</p>
    </div>
  </div>

  <?php if ($activePjId <= 0): ?>
    <div class="rpg-locked-panel rpg-mt-20">
        <i class="fas fa-lock rpg-locked-icon"></i>
        Debes seleccionar un personaje activo en tu panel de control para poder ver y aceptar misiones.
    </div>
  <?php else: ?>

    <!-- ─── SECCIÓN: INVITACIÓN PENDIENTE ─── -->
    <?php if ($invitation): ?>
      <div class="rpg-pd-container rpg-mt-20">
        <div class="rpg-form-panel rpg-form-panel--warning">
          <h3 class="rpg-form-heading">
            <i class="fas fa-envelope-open-text"></i> Invitación a Misión Pendiente
          </h3>
          <p class="rpg-form-help">
            <strong><?= htmlspecialchars($invitation['leader_name']) ?></strong> te ha invitado a participar como acompañante en la misión: 
            <strong>«<?= htmlspecialchars($invitation['title']) ?>»</strong> (Rango <?= htmlspecialchars($invitation['rank']) ?>).
          </p>
          <div class="rpg-misiones-invitation-actions">
            <button class="rpg-action-btn rpg-btn-primary" onclick="respondInvitation(<?= $invitation['active_mission_id'] ?>, 'accept')">
              <i class="fas fa-check"></i> Aceptar Invitación
            </button>
            <button class="rpg-system-tab-btn rpg-staff-btn-danger" onclick="respondInvitation(<?= $invitation['active_mission_id'] ?>, 'decline')">
              <i class="fas fa-times"></i> Rechazar
            </button>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- ─── SECCIÓN: MISIÓN ACTIVA ─── -->
    <?php if ($activeMission): ?>
      <div class="rpg-pd-container rpg-my-20">
        <div class="rpg-form-panel rpg-form-panel--success">
          <h3 class="rpg-form-heading">
            <i class="fas fa-map-marked-alt"></i> Misión en Curso: <?= htmlspecialchars($activeMission['title']) ?>
          </h3>
          <div class="rpg-misiones-active-layout">
            <div>
              <p class="rpg-mb-8"><strong>Rango:</strong> <?= htmlspecialchars($activeMission['rank']) ?> | <strong>Isla:</strong> <?= htmlspecialchars($activeMission['isla']) ?> | <strong>Categoría:</strong> <?= ucfirst(htmlspecialchars($activeMission['categoria'])) ?></p>
              <p class="rpg-mb-12 rpg-text-muted"><?= htmlspecialchars($activeMission['description']) ?></p>
              
              <!-- Progress post count -->
              <div class="rpg-mb-15">
                <div class="rpg-misiones-progress-header">
                  <span>Progreso de Posts:</span>
                  <strong><?= $activeMission['post_count'] ?> / <?= $activeMission['max_posts'] ?> posts</strong>
                </div>
                <div class="rpg-misiones-progress-bar">
                  <div class="rpg-misiones-progress-fill" data-progress="<?= min(100, (int)(($activeMission['post_count'] / $activeMission['max_posts']) * 100)) ?>"></div>
                </div>
              </div>

              <!-- Companions list -->
              <?php
              $partQ = $db->query("
                  SELECT mp.confirmed, p.name 
                  FROM {$prefix}game_mission_participants mp
                  JOIN {$prefix}game_personajes p ON mp.character_id = p.id
                  WHERE mp.active_mission_id = " . (int)$activeMission['id'] . " AND mp.character_id != " . (int)$activeMission['leader_character_id']
              );
              $comps = [];
              while ($cp = $db->fetch_array($partQ)) {
                  $comps[] = htmlspecialchars($cp['name']) . ' (' . ((int)$cp['confirmed'] === 1 ? 'Confirmado' : 'Pendiente') . ')';
              }
              if (!empty($comps)):
              ?>
                <p class="rpg-mb-12"><strong>Grupo de combate:</strong> <?= implode(', ', $comps) ?></p>
              <?php endif; ?>

              <!-- Thread link -->
              <a href="<?= htmlspecialchars($b_url) ?>/showthread.php?tid=<?= $activeMission['tid'] ?>" target="_blank" class="rpg-action-btn rpg-btn-primary rpg-display-inline-block">
                <i class="fas fa-external-link-alt"></i> Ir al Hilo de Rol
              </a>
            </div>

            <!-- Resolution actions for leader -->
            <div class="rpg-misiones-active-actions">
              <div class="rpg-misiones-rewards-summary">
                <div class="rpg-misiones-rewards-title">Recompensas al aprobar</div>
                <div class="rpg-misiones-reward-pd"><i class="fas fa-star"></i> <?= $activeMission['points_reward'] ?> PD</div>
                <div class="rpg-misiones-reward-berries"><i class="fas fa-coins"></i> <?= number_format((int)$activeMission['berry_reward']) ?> B</div>
              </div>
              
              <?php if ((int)$activeMission['leader_character_id'] === $activePjId): ?>
                <?php if ($activeMission['status'] === 'review'): ?>
                  <button class="rpg-system-tab-btn rpg-w-100 rpg-text-center rpg-flex-gap-10" disabled>
                    <i class="fas fa-clock"></i> Pendiente de Revisión
                  </button>
                <?php else: ?>
                  <button class="rpg-action-btn rpg-btn-primary btn-complete-mission rpg-w-100" onclick="completeMission(<?= $activeMission['id'] ?>)">
                    <i class="fas fa-flag-checkered"></i> Declarar Completada
                  </button>
                <?php endif; ?>
              <?php else: ?>
                <div class="rpg-text-center rpg-text-muted">Solo el líder del grupo puede dar por completada la misión.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- ─── FILTROS DEL TABLÓN ─── -->
    <form method="GET" action="tablon_misiones.php" class="rpg-form-panel rpg-misiones-filter-bar">
      <div class="rpg-misiones-filter-group">
        <label class="rpg-form-label rpg-misiones-filter-label">Filtrar por Rango</label>
        <select name="rank" class="textbox rpg-misiones-filter-select">
          <option value="">Todos los Rangos</option>
          <option value="D" <?= $filterRank === 'D' ? 'selected' : '' ?>>Rango D (Principiante)</option>
          <option value="C" <?= $filterRank === 'C' ? 'selected' : '' ?>>Rango C (Intermedio)</option>
          <option value="B" <?= $filterRank === 'B' ? 'selected' : '' ?>>Rango B (Experimentado)</option>
          <option value="A" <?= $filterRank === 'A' ? 'selected' : '' ?>>Rango A (Élite)</option>
          <option value="S" <?= $filterRank === 'S' ? 'selected' : '' ?>>Rango S (Legendario)</option>
        </select>
      </div>

      <div class="rpg-misiones-filter-group">
        <label class="rpg-form-label rpg-misiones-filter-label">Filtrar por Isla</label>
        <select name="isla" class="textbox rpg-misiones-filter-select">
          <option value="">Todas las Islas</option>
          <?php foreach ($islands as $isl): ?>
            <option value="<?= htmlspecialchars($isl) ?>" <?= $filterIsla === $isl ? 'selected' : '' ?>><?= htmlspecialchars($isl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="rpg-misiones-filter-group">
        <label class="rpg-form-label rpg-misiones-filter-label">Filtrar por Categoría</label>
        <select name="categoria" class="textbox rpg-misiones-filter-select">
          <option value="">Todas las Categorías</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>" <?= $filterCat === $cat ? 'selected' : '' ?>><?= ucfirst(htmlspecialchars($cat)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="rpg-misiones-filter-btn-wrap">
        <button type="submit" class="rpg-action-btn rpg-btn-primary"><i class="fas fa-filter"></i> Aplicar Filtros</button>
        <a href="tablon_misiones.php" class="rpg-system-tab-btn rpg-display-inline-block"><i class="fas fa-undo"></i> Limpiar</a>
      </div>
    </form>

    <!-- ─── GRID DE MISIONES DISPONIBLES (MINI CARDS) ─── -->
    <div class="rpg-misiones-mini-grid">
      <?php if (empty($missions)): ?>
        <div class="rpg-text-center rpg-text-muted rpg-w-100 rpg-mt-20">
          <i class="fas fa-compass rpg-display-block rpg-mb-10"></i>
          No hay misiones disponibles con los filtros aplicados para tu facción.
        </div>
      <?php else: ?>
        <?php foreach ($missions as $m): 
          $err = '';
          $canAccept = game_character_can_accept_mission($activePjId, (int)$m['id'], $err);
          $rankClass = 'rpg-stat-rank--' . strtolower($m['rank']);
          $json = htmlspecialchars(json_encode([
              'id' => $m['id'],
              'title' => $m['title'],
              'description' => $m['description'],
              'rank' => $m['rank'],
              'isla' => $m['isla'],
              'categoria' => $m['categoria'],
              'points_reward' => $m['points_reward'],
              'berry_reward' => $m['berry_reward'],
              'min_level' => $m['min_level'],
              'max_level' => $m['max_level'],
              'faction' => $m['faction'],
              'can_accept' => $canAccept,
              'error' => $err
          ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
        ?>
          <div class="rpg-mission-mini-card" onclick='openMissionDetailsModal(<?= $json ?>)'>
            <div class="rpg-mission-mini-rank <?= htmlspecialchars($rankClass) ?>">
              <?= htmlspecialchars($m['rank']) ?>
            </div>
            <div class="rpg-mission-mini-body">
              <h4><?= htmlspecialchars($m['title']) ?></h4>
            </div>
            <div class="rpg-mission-mini-arrow">
              <i class="fas fa-chevron-right"></i>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  <?php endif; ?>
</div>

<!-- MODAL: DETALLES DE MISIÓN -->
<div id="mission-details-modal" class="rpg-modal-overlay" data-modal>
  <div class="rpg-modal-panel rpg-modal-panel--lg">
    <div class="rpg-modal-header">
      <h3 class="rpg-modal-title"><i class="fas fa-info-circle"></i> Detalles de la Misión</h3>
      <button type="button" onclick="closeMissionDetailsModal()" class="rpg-modal-close"><i class="fas fa-times"></i></button>
    </div>
    <div class="rpg-modal-body">
      <div class="rpg-mission-details-header">
        <div id="md-rank" class="rpg-mission-details-rank">A</div>
        <div class="rpg-mission-details-title-box">
          <h2 id="md-title">Título</h2>
          <div class="rpg-mission-details-badges">
            <span class="rpg-pd-cost-badge" id="md-isla"><i class="fas fa-map-marker-alt"></i> Isla</span>
            <span class="rpg-pd-cost-badge" id="md-cat"><i class="fas fa-tag"></i> Cat</span>
            <span class="rpg-pd-cost-badge" id="md-niv"><i class="fas fa-user"></i> Niv</span>
          </div>
        </div>
      </div>
      
      <div class="rpg-mission-details-desc" id="md-desc">Descripción aquí...</div>
      
      <div class="rpg-misiones-rewards-summary rpg-mt-20">
        <div class="rpg-misiones-rewards-title">Recompensas Estimadas</div>
        <div class="rpg-misiones-reward-pd" id="md-pd"><i class="fas fa-star"></i> 0 PD</div>
        <div class="rpg-misiones-reward-berries" id="md-berry"><i class="fas fa-coins"></i> 0 B</div>
      </div>

      <div class="rpg-misiones-modal-footer rpg-mt-20">
        <button type="button" onclick="closeMissionDetailsModal()" class="rpg-system-tab-btn">Cerrar</button>
        <button type="button" id="btn_open_accept" class="rpg-action-btn rpg-btn-primary">
          <i class="fas fa-play"></i> Iniciar Misión
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: ACEPTAR MISIÓN (SELECCIÓN DE COMPAÑEROS) -->
<div id="accept-mission-modal" class="rpg-modal-overlay" data-modal>
  <div class="rpg-modal-panel rpg-modal-panel--md">
    <div class="rpg-modal-header">
      <h3 class="rpg-modal-title"><i class="fas fa-compass"></i> Configurar Grupo</h3>
      <button type="button" onclick="closeAcceptMissionModal()" class="rpg-modal-close"><i class="fas fa-times"></i></button>
    </div>
    <div class="rpg-modal-body">
      <input type="hidden" id="accept_mission_id" value="">
      <p class="rpg-modal-intro">Estás a punto de iniciar la misión: <strong id="accept_mission_title_text"></strong>.</p>
      
      <p class="rpg-modal-intro rpg-modal-intro--small">
        Puedes realizar esta misión en solitario o invitar a compañeros (máximo 2 recomendados). Cada compañero recibirá una notificación para confirmar su asistencia.
      </p>

      <div class="rpg-mt-20">
        <label class="rpg-modal-label">Invitar Acompañantes (Opcional)</label>
        <?php if (empty($companions)): ?>
          <p class="rpg-muted-soft">No hay otros personajes aprobados disponibles en el foro.</p>
        <?php else: ?>
          <div class="rpg-modal-body-companions">
            <?php foreach ($companions as $c): ?>
              <label class="rpg-modal-companion-row">
                <input type="checkbox" class="companion-checkbox" value="<?= $c['id'] ?>">
                <?= htmlspecialchars($c['name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="rpg-misiones-modal-footer">
        <button type="button" onclick="closeAcceptMissionModal()" class="rpg-system-tab-btn">Volver</button>
        <button type="button" onclick="submitAcceptMission()" id="btn_submit_accept_mission" class="rpg-action-btn rpg-btn-primary">
          <i class="fas fa-compass"></i> Iniciar Misión
        </button>
      </div>
    </div>
  </div>
</div>

<script>
window.TABLON_MISIONES_CONFIG = {
  bburl: '<?= $b_url ?>',
  characterId: <?= (int)$activePjId ?>
};
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/tablon_misiones.js?v=2"></script>
<?php
$content = ob_get_clean();
game_render_page('Tablón de Misiones', $content);

