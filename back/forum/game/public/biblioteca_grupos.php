<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
global $db, $mybb;

$prefix = TABLE_PREFIX;

$crews = [];
$all_factions = [];

$q = $db->query("SELECT t.*, p.name as leader_name, p.avatar as leader_avatar 
                 FROM {$prefix}game_tripulaciones t
                 LEFT JOIN {$prefix}game_personajes p ON t.leader_pj_id = p.id
                 WHERE t.status = 'aprobada' ORDER BY t.name ASC");

while ($row = $db->fetch_array($q)) {
    // Factions
    $factions = array_map('trim', explode(',', $row['factions'] ?? ''));
    $factions = array_filter($factions);
    if (empty($factions)) {
        $factions = ['Sin Afiliación'];
    }
    $row['factions_array'] = $factions;
    foreach ($factions as $f) {
        $all_factions[$f] = true;
    }
    
    // fetch members
    $mq = $db->query("SELECT m.role, m.role_custom, p.name, p.id FROM {$prefix}game_tripulacion_miembros m JOIN {$prefix}game_personajes p ON m.pj_id = p.id WHERE m.tripulacion_id = {$row['id']} AND m.status_peticion = 'aprobada' ORDER BY CASE m.role WHEN 'Líder' THEN 0 ELSE 1 END, m.joined_at ASC");
    $members = [];
    while ($m = $db->fetch_array($mq)) {
        $members[] = $m;
    }
    $row['members'] = $members;
    
    // fetch controlled islands
    $iq = $db->query("SELECT f.name FROM {$prefix}game_forum_islands i JOIN {$prefix}forums f ON i.fid = f.fid WHERE i.controlling_type = 'crew' AND i.controlling_id = {$row['id']}");
    $islands = [];
    while ($i = $db->fetch_array($iq)) {
        $islands[] = $i['name'];
    }
    $row['islands'] = $islands;
    
    $crews[] = $row;
}
ksort($all_factions);

ob_start();
?>
<div class="rpg-lib-container rpg-crews-catalog-container">
  <div class="rpg-lib-header">
    <div class="rpg-lib-header-content">
      <h1><i class="fas fa-skull"></i> Catálogo de Grupos</h1>
      <p>Las organizaciones y alianzas del Mundo Conocido.</p>
    </div>
  </div>

  <!-- Faction Filters -->
  <div class="rpg-crew-filters" id="crew-filters">
    <button type="button" class="rpg-btn-filter active" data-filter="all">Todos</button>
    <?php foreach (array_keys($all_factions) as $fac): ?>
        <button type="button" class="rpg-btn-filter" data-filter="<?= htmlspecialchars($fac) ?>"><?= htmlspecialchars($fac) ?></button>
    <?php endforeach; ?>
  </div>

  <div class="rpg-lib-body">
    <div class="rpg-crews-grid">
        <?php foreach ($crews as $crew): 
            $fac_data = implode('|', $crew['factions_array']);
        ?>
        <div class="rpg-crew-card" data-factions="<?= htmlspecialchars($fac_data) ?>">
            
            <a href="grupo.php?id=<?= $crew['id'] ?>" class="rpg-crew-banner-wrapper">
                <img src="<?= htmlspecialchars($crew['image_url'] ?: 'https://placehold.co/800x600/111111/333333?text=Sin+Bandera') ?>" alt="Bandera" class="rpg-crew-banner">
                <div class="rpg-crew-banner-overlay">
                    <h2 class="rpg-crew-title"><?= htmlspecialchars($crew['name']) ?></h2>
                    <?php if(!empty($crew['motto'])): ?>
                        <div class="rpg-crew-motto">"<?= htmlspecialchars($crew['motto']) ?>"</div>
                    <?php endif; ?>
                </div>
            </a>
            
            <div class="rpg-crew-card-body">
                <div class="rpg-crew-tags">
                    <?php foreach ($crew['factions_array'] as $f): ?>
                        <span class="rpg-badge rpg-badge--dark"><?= htmlspecialchars($f) ?></span>
                    <?php endforeach; ?>
                </div>

                <div class="rpg-crew-members-clean">
                    <h4 class="rpg-crew-section-title"><i class="fas fa-users"></i> Miembros (<?= count($crew['members']) ?>)</h4>
                    <ul class="rpg-crew-clean-list">
                        <?php foreach ($crew['members'] as $m): ?>
                            <li>
                                <span class="member-name"><?= htmlspecialchars($m['name']) ?></span>
                                <span class="member-role"><?= htmlspecialchars($m['role_custom'] ?: $m['role']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <div class="rpg-crew-card-footer">
                <a href="grupo.php?id=<?= $crew['id'] ?>" class="rpg-btn rpg-btn--block">Ver Detalles</a>
            </div>

        </div>
        <?php endforeach; ?>
        
        <?php if (empty($crews)): ?>
            <div class="rpg-empty-state">
                <i class="fas fa-wind"></i>
                <p>No hay grupos registrados todavía.</p>
            </div>
        <?php endif; ?>
    </div>
  </div>
</div>

<script src="<?= rtrim($mybb->settings['bburl'] ?? '', '/') ?>/jscripts/game/biblioteca_grupos.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Catálogo de Grupos', $content);
