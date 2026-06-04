<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer, $theme;

$prefix = TABLE_PREFIX;

function get_standard_faction(?string $faction): string {
    if (!$faction) return 'Civil';
    $fac = mb_strtolower(trim($faction));
    if (strpos($fac, 'marine') !== false || strpos($fac, 'marina') !== false) {
        return 'Marine';
    }
    if (strpos($fac, 'revolucion') !== false) {
        return 'Revolucionario';
    }
    if (strpos($fac, 'gobierno') !== false) {
        return 'Gobierno';
    }
    if (strpos($fac, 'cazador') !== false) {
        return 'Cazador';
    }
    if (strpos($fac, 'civil') !== false) {
        return 'Civil';
    }
    if (strpos($fac, 'pirata') !== false || strpos($fac, 'paja') !== false) {
        return 'Pirata';
    }
    return 'Civil'; // default fallback
}

function resolve_avatar(?string $path, string $bb): string {
    if (!$path || trim($path) === '') {
        return $bb . '/images/default_avatar.png';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return rtrim($bb, '/') . '/' . ltrim($path, '/');
}

try {
    $query = $db->query("SELECT * FROM {$prefix}game_personajes WHERE status = 'aprobada' AND is_npc = 0 ORDER BY id ASC");
    $chars = [];
    while ($row = $db->fetch_array($query)) {
        $stats = !empty($row['stats_json']) ? json_decode($row['stats_json'], true) : [];
        $chars[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'race' => $row['race'],
            'race_name' => $row['race_name'],
            'job' => $row['occupation'],
            'job_name' => $row['occupation_name'],
            'desc' => $row['desc'],
            'details' => $row['details'],
            'rango' => $row['rango'],
            'tripulacion' => $row['tripulacion'],
            'recompensa' => $row['recompensa'],
            'avatar' => resolve_avatar($row['avatar'], $mybb->settings['bburl']),
            'faction' => get_standard_faction($row['faction']),
            'faction_display' => $row['faction'] ?: 'Civil',
            'stats' => [
                'FUE' => (int)($stats['fue'] ?? $stats['str'] ?? $row['stat_fp'] ?? 5),
                'AGI' => (int)($stats['agi'] ?? $row['stat_dp'] ?? 5),
                'DES' => (int)($stats['des'] ?? $stats['res'] ?? $row['stat_rp'] ?? 5),
                'INST' => (int)($stats['inst'] ?? $stats['vol'] ?? $row['stat_vp'] ?? 5),
                'ESP' => (int)($stats['esp'] ?? $stats['vol'] ?? $row['stat_vp'] ?? 5),
                'INT' => (int)($stats['int'] ?? $row['stat_ip'] ?? 5),
            ]
        ];
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Error al cargar Personajes</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

$bb_url = $mybb->settings['bburl'];

$cards = [];
foreach ($chars as $c) {
    $sj = htmlspecialchars(json_encode($c['stats']), ENT_QUOTES, 'UTF-8');
    $faction_label = htmlspecialchars($c['faction_display']);
    $crew_label = $c['tripulacion'] ? htmlspecialchars($c['tripulacion']) : 'Sin Tripulación';
    $rank_label = $c['rango'] ? htmlspecialchars($c['rango']) : 'Sin Rango';
    $bounty_label = $c['recompensa'] ? htmlspecialchars($c['recompensa']) : '0 Berries';
    
    $cards[] = '
    <div class="rpg-lib-card" 
         data-id="' . $c['id'] . '" 
         data-name="' . htmlspecialchars($c['name']) . '" 
         data-faction="' . $c['faction'] . '" 
         data-desc="' . htmlspecialchars($c['desc']) . '" 
         data-details="' . htmlspecialchars($c['details']) . '" 
         data-img="' . htmlspecialchars($c['avatar'], ENT_QUOTES) . '" 
         data-stats=\'' . $sj . '\' 
         data-rango="' . $rank_label . '" 
         data-tripulacion="' . $crew_label . '" 
         data-recompensa="' . $bounty_label . '"
         data-race-name="' . htmlspecialchars($c['race_name']) . '"
         data-job-name="' . htmlspecialchars($c['job_name']) . '">
      <div class="rpg-lib-card-img" data-bg="' . htmlspecialchars($c['avatar'], ENT_QUOTES) . '">
        <span class="rpg-lib-card-badge">' . $faction_label . '</span>
      </div>
      <div class="rpg-lib-card-body">
        <h2 class="rpg-lib-card-title">' . htmlspecialchars($c['name']) . '</h2>
        <p class="rpg-lib-card-desc">' . htmlspecialchars($c['desc']) . '</p>
        <div class="rpg-lib-card-stats">
          <span class="rpg-lib-card-stat"><i class="fas fa-users"></i> ' . $crew_label . '</span>
          <span class="rpg-lib-card-stat"><i class="fas fa-briefcase"></i> ' . htmlspecialchars($c['job_name']) . '</span>
          <span class="rpg-lib-card-stat"><i class="fas fa-medal"></i> ' . $rank_label . '</span>
          <span class="rpg-lib-card-stat rpg-lib-card-stat--muted"><i class="fas fa-coins"></i> ' . $bounty_label . '</span>
        </div>
      </div>
    </div>';
}
$cards_html = implode("\n", $cards);

ob_start();
?>
<div class="rpg-lib-container">
  <div class="rpg-lib-banner" data-bg="<?= htmlspecialchars($bb_url . '/images/game/personaje_banner.png', ENT_QUOTES) ?>">
    <div class="rpg-lib-banner-content">
      <h1>Biblioteca: Personajes</h1>
      <p>Explora todos los personajes del foro de rol, sus facciones, especialidades y estad&iacute;sticas.</p>
    </div>
  </div>
  <div class="rpg-lib-body">
    <aside class="rpg-lib-sidebar">
      <h3><i class="fas fa-filter"></i> Filtros</h3>
      <div class="rpg-filter-group">
        <span class="rpg-filter-label">Nombre del Personaje</span>
        <div class="rpg-search-wrapper"><input type="text" id="lib-search" class="textbox" placeholder="Buscar personaje..."><i class="fas fa-search"></i></div>
      </div>
      <div class="rpg-filter-group">
        <span class="rpg-filter-label">Facci&oacute;n</span>
        <label class="rpg-filter-option"><input type="checkbox" name="faction" value="Pirata" checked><span class="rpg-filter-checkbox"></span>Piratas</label>
        <label class="rpg-filter-option"><input type="checkbox" name="faction" value="Marine" checked><span class="rpg-filter-checkbox"></span>Marina</label>
        <label class="rpg-filter-option"><input type="checkbox" name="faction" value="Revolucionario" checked><span class="rpg-filter-checkbox"></span>Revolucionarios</label>
        <label class="rpg-filter-option"><input type="checkbox" name="faction" value="Gobierno" checked><span class="rpg-filter-checkbox"></span>Gobierno Mundial</label>
        <label class="rpg-filter-option"><input type="checkbox" name="faction" value="Cazador" checked><span class="rpg-filter-checkbox"></span>Cazadores</label>
        <label class="rpg-filter-option"><input type="checkbox" name="faction" value="Civil" checked><span class="rpg-filter-checkbox"></span>Civiles</label>
      </div>
    </aside>
    <main class="rpg-lib-content">
      <div class="rpg-lib-grid" id="lib-grid"><?= $cards_html ?></div>
    </main>
  </div>
</div>

<div class="rpg-lib-modal" id="lib-modal">
  <div class="rpg-lib-modal-content">
    <span class="rpg-lib-modal-close" id="modal-close">&times;</span>
    <div class="rpg-lib-modal-banner" id="modal-banner" data-bg="<?= htmlspecialchars($bb_url . '/images/game/personaje_banner.png', ENT_QUOTES) ?>"></div>
    <div class="rpg-lib-modal-body">
      <div class="rpg-lib-modal-header rpg-modal-header-sticky">
        <h2 class="rpg-lib-modal-title" id="modal-title">Nombre</h2>
        <span class="rpg-lib-modal-badge" id="modal-badge">Facci&oacute;n</span>
      </div>
      <div class="rpg-modal-grid">
        <div class="rpg-modal-column-left">
          <div class="rpg-modal-npc-portrait-wrap" id="modal-portrait-section">
            <img id="modal-portrait" class="rpg-modal-npc-portrait" src="" alt="Retrato">
          </div>
          <div class="rpg-modal-npc-section-title"><i class="fas fa-chart-pie"></i> Distribuci&oacute;n de Stats</div>
          <div class="rpg-radar-container" id="modal-radar-wrapper"></div>
        </div>
        <div class="rpg-modal-column-right">
          <div class="rpg-modal-npc-section-title"><i class="fas fa-history"></i> Biograf&iacute;a y Habilidades</div>
          <p class="rpg-lib-modal-desc rpg-historia-modal-desc" id="modal-details">Biograf&iacute;a del personaje...</p>
          <div class="rpg-modal-npc-section-title rpg-modal-npc-section-title--spaced"><i class="fas fa-address-card"></i> Datos del Personaje</div>
          <div class="rpg-lib-modal-stats">
            <div class="rpg-lib-modal-stat-box"><div class="rpg-lib-modal-stat-lbl">Tripulaci&oacute;n</div><div class="rpg-lib-modal-stat-val" id="modal-stat-tripulacion">Sombreros de Paja</div></div>
            <div class="rpg-lib-modal-stat-box"><div class="rpg-lib-modal-stat-lbl">Rango / Rol</div><div class="rpg-lib-modal-stat-val" id="modal-stat-rango">Oficial</div></div>
            <div class="rpg-lib-modal-stat-box"><div class="rpg-lib-modal-stat-lbl">Recompensa</div><div class="rpg-lib-modal-stat-val" id="modal-stat-recompensa">0 Berries</div></div>
            <div class="rpg-lib-modal-stat-box"><div class="rpg-lib-modal-stat-lbl">Raza</div><div class="rpg-lib-modal-stat-val" id="modal-stat-raza">Humano</div></div>
            <div class="rpg-lib-modal-stat-box"><div class="rpg-lib-modal-stat-lbl">Ocupaci&oacute;n</div><div class="rpg-lib-modal-stat-val" id="modal-stat-ocupacion">Cocinero</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.BIBLIOTECA_PERSONAJES_CONFIG = {};
</script>
<script src="<?= rtrim($bb_url, '/') ?>/jscripts/game/biblioteca_personajes.js?v=2"></script>
<?php
$content = ob_get_clean();
game_render_page('Biblioteca de Personajes', $content);
