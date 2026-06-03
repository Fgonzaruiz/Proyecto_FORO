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

if ($staff_level < 1) {
    header('Location: ../index.php');
    exit;
}

$status_labels = [
    'pendiente' => ['label' => 'Sin Revisar', 'color' => '#ef4444', 'icon' => 'fa-clock'],
    'revision'  => ['label' => 'En Revisión', 'color' => '#f59e0b', 'icon' => 'fa-sync-alt'],
    'aprobada'  => ['label' => 'Aprobada', 'color' => '#10b981', 'icon' => 'fa-check-circle'],
    'rechazada' => ['label' => 'Rechazada', 'color' => '#ef4444', 'icon' => 'fa-times-circle'],
];

$b_url = $mybb->settings['bburl'];

// Load central catalog
$catalog_path = __DIR__ . '/../data/linaje_system.json';
$catalog_json = '{}';
if (file_exists($catalog_path)) {
    $catalog_json = file_get_contents($catalog_path);
}

ob_start();
?>
<style>
/* New Perk-based Linaje Styles */
.gene-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.gene-card {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 16px;
    background: var(--bg-main);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
    text-align: left;
}
.gene-card:hover { border-color: rgba(198,40,40,0.4); transform: translateX(3px); }
.gene-card.passive-primary { border-left: 3px solid #10b981; }
.gene-card.passive-secondary { border-left: 3px solid #f59e0b; }
.gene-card.perk-racial { border-left: 3px solid var(--accent-indigo); }
.gene-card.perk-general { border-left: 3px solid var(--accent-purple); }
.gene-card-icon { width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.gene-card-info { flex: 1; display: flex; flex-direction: column; }
.gene-card-name { font-weight: 800; font-size: 13px; color: var(--text-primary); margin-bottom: 2px; font-family: var(--font-heading); text-transform: uppercase; letter-spacing: 0.5px; }
.gene-card-desc { font-size: 11px; color: var(--text-muted); line-height: 1.4; margin-top: 4px; margin-bottom: 6px; }
.gene-card-badge {
    align-self: flex-start;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 2px 8px;
    border-radius: 10px;
    flex-shrink: 0;
}
</style>
<div class="rpg-staff-zone">
  <div class="rpg-staff-header rpg-staff-header--aprobar">
    <div class="rpg-staff-header-content">
      <h1><i class="fas fa-user-check"></i> Aprobar Personajes</h1>
      <p>Revisa las fichas de personaje pendientes de aprobaci&oacute;n. <strong><?= htmlspecialchars($pj_name) ?></strong></p>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="aprobar-filter-bar">
    <button class="aprobar-filter-btn active" data-filter="">Todos</button>
    <button class="aprobar-filter-btn aprobar-filter-btn--pendiente" data-filter="pendiente">Sin Revisar</button>
    <button class="aprobar-filter-btn aprobar-filter-btn--revision" data-filter="revision">En Revisión</button>
    <button class="aprobar-filter-btn aprobar-filter-btn--aprobada" data-filter="aprobada">Aprobadas</button>
    <button class="aprobar-filter-btn aprobar-filter-btn--rechazada" data-filter="rechazada">Rechazadas</button>
  </div>

  <div class="aprobar-layout">
    <!-- LEFT: Character List -->
    <div class="aprobar-list" id="aprobar-list">
      <div class="aprobar-list-header">
        <span>Personajes</span>
        <span class="aprobar-count" id="aprobar-count">0</span>
      </div>
      <div id="aprobar-list-items">
        <div class="aprobar-empty">Cargando...</div>
      </div>
    </div>

    <!-- RIGHT: Preview Panel -->
    <div class="aprobar-preview" id="aprobar-preview">
      <div class="aprobar-empty aprobar-empty--pick">
        <i class="fas fa-user-check"></i>
        Selecciona un personaje para revisar su ficha
      </div>
    </div>
  </div>
</div>



<script>
window.ZONA_STAFF_APROBAR_CONFIG = {
  bburl: <?= json_encode($b_url, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
  staffLevel: <?= (int)$staff_level ?>,
  linajeCatalog: <?= $catalog_json !== '' ? $catalog_json : '{}' ?>
};
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/zona_staff_aprobar.js?v=2"></script>
<?php
$content = ob_get_clean();
game_render_page("Aprobar Personajes", $content);
