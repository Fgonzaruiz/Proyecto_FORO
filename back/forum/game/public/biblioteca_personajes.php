<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer, $theme;

$prefix = TABLE_PREFIX;

try {
    $query = $db->query("SELECT * FROM {$prefix}game_personajes ORDER BY id ASC");
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
            'banner' => $row['banner'],
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

$b_url = $mybb->settings['bburl'] . '/images/game/personaje_banner.png';

$cards = [];
foreach ($chars as $c) {
    $sj = htmlspecialchars(json_encode($c['stats']), ENT_QUOTES, 'UTF-8');
    $cards[] = '<div class="rpg-lib-card" data-id="' . $c['id'] . '" data-name="' . htmlspecialchars($c['name']) . '" data-race="' . $c['race'] . '" data-job="' . $c['job'] . '" data-desc="' . htmlspecialchars($c['desc']) . '" data-details="' . htmlspecialchars($c['details']) . '" data-img="' . $b_url . '" data-stats=\'' . $sj . '\' data-rango="' . htmlspecialchars($c['rango']) . '" data-tripulacion="' . htmlspecialchars($c['tripulacion']) . '" data-recompensa="' . htmlspecialchars($c['recompensa']) . '"><div class="rpg-lib-card-img" data-bg="' . htmlspecialchars($b_url, ENT_QUOTES) . '"><span class="rpg-lib-card-badge">' . htmlspecialchars($c['race_name']) . '</span></div><div class="rpg-lib-card-body"><h2 class="rpg-lib-card-title">' . htmlspecialchars($c['name']) . '</h2><p class="rpg-lib-card-desc">' . htmlspecialchars($c['desc']) . '</p><div class="rpg-lib-card-stats"><span class="rpg-lib-card-stat"><i class="fas fa-briefcase"></i> ' . htmlspecialchars($c['job_name']) . '</span></div></div></div>';
}
$cards_html = implode("\n", $cards);

ob_start();
?>
<div class="rpg-lib-container">
  <div class="rpg-lib-banner" data-bg="<?= htmlspecialchars($b_url, ENT_QUOTES) ?>">
    <div class="rpg-lib-banner-content">
      <h1>Biblioteca: Personajes</h1>
      <p>Explora todos los personajes del foro de rol, sus razas, ocupaciones y estad&iacute;sticas.</p>
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
        <span class="rpg-filter-label">Raza</span>
        <label class="rpg-filter-option"><input type="checkbox" name="race" value="humano" checked><span class="rpg-filter-checkbox"></span>Humano</label>
        <label class="rpg-filter-option"><input type="checkbox" name="race" value="gyojin" checked><span class="rpg-filter-checkbox"></span>Gyojin</label>
        <label class="rpg-filter-option"><input type="checkbox" name="race" value="mink" checked><span class="rpg-filter-checkbox"></span>Reno / Mink</label>
      </div>
      <div class="rpg-filter-group">
        <span class="rpg-filter-label">Ocupaci&oacute;n / Rol</span>
        <label class="rpg-filter-option"><input type="checkbox" name="job" value="capitan" checked><span class="rpg-filter-checkbox"></span>Capit&aacute;n</label>
        <label class="rpg-filter-option"><input type="checkbox" name="job" value="combatiente" checked><span class="rpg-filter-checkbox"></span>Combatiente</label>
        <label class="rpg-filter-option"><input type="checkbox" name="job" value="navegante" checked><span class="rpg-filter-checkbox"></span>Navegante</label>
        <label class="rpg-filter-option"><input type="checkbox" name="job" value="medico" checked><span class="rpg-filter-checkbox"></span>M&eacute;dico</label>
        <label class="rpg-filter-option"><input type="checkbox" name="job" value="arqueologo" checked><span class="rpg-filter-checkbox"></span>Arque&oacute;logo</label>
        <label class="rpg-filter-option"><input type="checkbox" name="job" value="carpintero" checked><span class="rpg-filter-checkbox"></span>Carpintero</label>
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
    <div class="rpg-lib-modal-banner" id="modal-banner"></div>
    <div class="rpg-lib-modal-body">
      <div class="rpg-lib-modal-header rpg-modal-header-sticky">
        <h2 class="rpg-lib-modal-title" id="modal-title">Nombre</h2>
        <span class="rpg-lib-modal-badge" id="modal-badge">Raza</span>
      </div>
      <div class="rpg-modal-grid">
        <div class="rpg-modal-column-left">
          <div class="rpg-modal-npc-section-title"><i class="fas fa-chart-pie"></i> Distribuci&oacute;n de Stats</div>
          <div class="rpg-radar-container" id="modal-radar-wrapper"></div>
        </div>
        <div class="rpg-modal-column-right">
          <div class="rpg-modal-npc-section-title"><i class="fas fa-history"></i> Biograf&iacute;a y Habilidades</div>
          <p class="rpg-lib-modal-desc rpg-historia-modal-desc" id="modal-details">Biograf&iacute;a del personaje...</p>
          <div class="rpg-modal-npc-section-title rpg-modal-npc-section-title--spaced"><i class="fas fa-address-card"></i> Datos del Cap&iacute;tulo</div>
          <div class="rpg-lib-modal-stats">
            <div class="rpg-lib-modal-stat-box"><div class="rpg-lib-modal-stat-lbl">Tripulaci&oacute;n</div><div class="rpg-lib-modal-stat-val" id="modal-stat-tripulacion">Sombreros de Paja</div></div>
            <div class="rpg-lib-modal-stat-box"><div class="rpg-lib-modal-stat-lbl">Rango / Rol</div><div class="rpg-lib-modal-stat-val" id="modal-stat-rango">Oficial</div></div>
            <div class="rpg-lib-modal-stat-box"><div class="rpg-lib-modal-stat-lbl">Recompensa</div><div class="rpg-lib-modal-stat-val" id="modal-stat-recompensa">0 Berries</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.BIBLIOTECA_PERSONAJES_CONFIG = {};
</script>
<script src="<?= rtrim($mybb->settings['bburl'], '/') ?>/jscripts/game/biblioteca_personajes.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Biblioteca de Personajes', $content);
