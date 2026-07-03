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
    return 'Civil';
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

use Game\Shared\StatScale;

try {
    $query = $db->query("SELECT * FROM {$prefix}game_personajes WHERE status = 'aprobada' AND is_npc = 0 AND name NOT IN ('Narrador', 'STAFF') ORDER BY id ASC");
    $chars = [];
    while ($row = $db->fetch_array($query)) {
        $stats = !empty($row['stats_json']) ? json_decode($row['stats_json'], true) : [];
        $raceName = (string)($row['race_name'] ?? '');
        $ctx = $raceName !== '' ? game_build_stat_context($stats, $raceName) : null;

        $statMetaKeys = ['fue', 'res', 'agi', 'des', 'int', 'inst', 'esp'];
        $statMetaInfo = [
            'fue' => ['Fuerza', 'fa-dumbbell'],
            'res' => ['Resistencia', 'fa-shield-alt'],
            'agi' => ['Agilidad', 'fa-running'],
            'des' => ['Destreza', 'fa-bullseye'],
            'int' => ['Intelecto', 'fa-brain'],
            'inst' => ['Instinto', 'fa-eye'],
            'esp' => ['Espíritu', 'fa-fire'],
        ];

        $statDisplay = [];
        if ($ctx) {
            foreach ($statMetaKeys as $sk) {
                $statDisplay[$sk] = [
                    'trained' => $ctx['trained'][$sk] ?? 1,
                    'eff_rank' => $ctx['effective_ranks'][$sk] ?? 1,
                    'display' => $ctx['display'][$sk] ?? 'D',
                ];
            }
        } else {
            foreach ($statMetaKeys as $sk) {
                $rank = max(1, min(6, (int)($stats[$sk] ?? 1)));
                $statDisplay[$sk] = [
                    'trained' => $rank,
                    'eff_rank' => $rank,
                    'display' => StatScale::RANK_NAMES[$rank] ?? 'D',
                ];
            }
        }

        $chars[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'race_name' => $row['race_name'],
            'job_name' => $row['occupation_name'],
            'desc' => $row['desc'],
            'details' => $row['details'],
            'rango' => $row['rango'],
            'tripulacion' => $row['tripulacion'],
            'recompensa' => $row['recompensa'],
            'avatar' => resolve_avatar($row['avatar'], $mybb->settings['bburl']),
            'faction' => get_standard_faction($row['faction']),
            'faction_display' => $row['faction'] ?: 'Civil',
            'stat_display' => $statDisplay,
            'link' => rtrim($mybb->settings['bburl'], '/') . '/game/public/personaje.php?pj=' . (int)$row['id'],
            'history' => $stats['history'] ?? '',
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
    $sdj = htmlspecialchars(json_encode($c['stat_display']), ENT_QUOTES, 'UTF-8');
    $faction_label = htmlspecialchars($c['faction_display']);
    $crew_label = $c['tripulacion'] ? htmlspecialchars($c['tripulacion']) : 'Sin Tripulación';
    $rank_label = $c['rango'] ? htmlspecialchars($c['rango']) : 'Sin Rango';
    $bounty_label = $c['recompensa'] ? htmlspecialchars($c['recompensa']) : '0 Berries';
    $history_esc = htmlspecialchars($c['details'] ?? '', ENT_QUOTES, 'UTF-8');

    $cards[] = '
    <div class="rpg-lib-card" 
         data-id="' . $c['id'] . '" 
         data-name="' . htmlspecialchars($c['name']) . '" 
         data-faction="' . $c['faction'] . '" 
         data-desc="' . htmlspecialchars($c['desc']) . '" 
         data-img="' . htmlspecialchars($c['avatar'], ENT_QUOTES) . '" 
         data-stats=\'' . $sdj . '\'
         data-rango="' . $rank_label . '" 
         data-tripulacion="' . $crew_label . '" 
         data-recompensa="' . $bounty_label . '"
         data-race-name="' . htmlspecialchars($c['race_name']) . '"
         data-job-name="' . htmlspecialchars($c['job_name']) . '"
         data-link="' . htmlspecialchars($c['link'], ENT_QUOTES) . '"
         data-history="' . $history_esc . '">
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
  <div class="rpg-lib-header">
    <div class="rpg-lib-header-content">
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

<div class="rpg-lib-modal rpg-lib-modal--xl" id="lib-modal">
  <div class="rpg-lib-modal-content rpg-lib-modal-content--xl">
    <span class="rpg-lib-modal-close" id="modal-close">&times;</span>
    <div class="rpg-lib-modal-body rpg-lib-modal-body--xl">
      <div class="rpg-lib-modal-header">
        <h2 class="rpg-lib-modal-title" id="modal-title">Nombre</h2>
        <span class="rpg-lib-modal-badge" id="modal-badge">Facci&oacute;n</span>
      </div>
      <div class="rpg-modal-grid rpg-modal-grid--biblio">
        <div class="rpg-modal-column-left">
          <div id="modal-portrait-section">
            <img id="modal-portrait" class="rpg-lib-modal-portrait--biblio" src="" alt="Retrato">
          </div>
          <div class="rpg-lib-modal-section-title"><i class="fas fa-chart-bar"></i> Estad&iacute;sticas</div>
          <div id="modal-stats-list" class="rpg-lib-stat-rows"></div>
        </div>
        <div class="rpg-modal-column-right">
          <div class="rpg-lib-modal-section-title"><i class="fas fa-history"></i> Historia</div>
          <div id="modal-history" class="rpg-lib-modal-text">Sin historia registrada.</div>
          <div class="rpg-lib-modal-divider"></div>
          <div class="rpg-lib-modal-section-title"><i class="fas fa-address-card"></i> Datos del Personaje</div>
          <div class="rpg-lib-modal-info-grid">
            <div class="rpg-lib-modal-info-item">
              <span class="rpg-lib-modal-info-icon"><i class="fas fa-users"></i></span>
              <div>
                <div class="rpg-lib-modal-info-label">Tripulaci&oacute;n</div>
                <div class="rpg-lib-modal-info-value" id="modal-stat-tripulacion">—</div>
              </div>
            </div>
            <div class="rpg-lib-modal-info-item">
              <span class="rpg-lib-modal-info-icon"><i class="fas fa-medal"></i></span>
              <div>
                <div class="rpg-lib-modal-info-label">Rango</div>
                <div class="rpg-lib-modal-info-value" id="modal-stat-rango">—</div>
              </div>
            </div>
            <div class="rpg-lib-modal-info-item">
              <span class="rpg-lib-modal-info-icon"><i class="fas fa-coins"></i></span>
              <div>
                <div class="rpg-lib-modal-info-label">Recompensa</div>
                <div class="rpg-lib-modal-info-value" id="modal-stat-recompensa">—</div>
              </div>
            </div>
            <div class="rpg-lib-modal-info-item">
              <span class="rpg-lib-modal-info-icon"><i class="fas fa-dragon"></i></span>
              <div>
                <div class="rpg-lib-modal-info-label">Raza</div>
                <div class="rpg-lib-modal-info-value" id="modal-stat-raza">—</div>
              </div>
            </div>
          </div>
          <div class="rpg-lib-modal-ficha-link">
            <a id="modal-link-ficha" href="#" target="_blank" class="rpg-btn--primary rpg-btn--sm"><i class="fas fa-external-link-alt"></i> Ver Ficha Completa</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.BIBLIOTECA_PERSONAJES_CONFIG = {};
</script>
<script src="<?= rtrim($bb_url, '/') ?>/jscripts/game/biblioteca_personajes.js?v=4"></script>
<?php
$content = ob_get_clean();
game_render_page('Biblioteca de Personajes', $content);
