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
    if (strpos($fac, 'pirata') !== false || strpos($fac, 'paja') !== false || strpos($fac, 'guild') !== false || strpos($fac, 'kuro') !== false) {
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

function normalize_npc_stats(array $stats): array {
    if (!class_exists('\\Game\\Shared\\StatScale')) {
        require_once __DIR__ . '/../src/autoload.php';
    }
    return \Game\Shared\StatScale::sanitizeRanks($stats);
}

$npcs = [];

try {
    // 1. Static NPCs from game_npc_profiles
    $query1 = $db->query(
        "SELECT p.*, t.name AS trip_nombre, t.image_url AS trip_imagen
         FROM {$prefix}game_npc_profiles p
         LEFT JOIN {$prefix}game_tripulaciones t ON p.tripulacion_id = t.id
         ORDER BY p.id ASC"
    );
    while ($row = $db->fetch_array($query1)) {
        $id_data = json_decode($row['identificacion'], true) ?: [];
        $rawStats = json_decode($row['stats'], true) ?: [];
        $npcs[] = [
            'id'             => (int)$row['id'],
            'nombre'         => $row['nombre'],
            'imagen'         => resolve_avatar($row['imagen'], $mybb->settings['bburl']),
            'tripulacion_id' => (int)$row['tripulacion_id'],
            'trip_nombre'    => $row['trip_nombre'],
            'trip_imagen'    => $row['trip_imagen'],
            'identificacion' => $id_data,
            'stats'          => normalize_npc_stats($rawStats),
            'history'        => $row['cronologia'] ?? '',
            'faction'        => get_standard_faction($id_data['afiliacion'] ?? 'Civil'),
            'type'           => 'static',
        ];
    }

    // 2. Major NPCs from game_personajes where is_npc = 1
    $query2 = $db->query(
        "SELECT * FROM {$prefix}game_personajes WHERE is_npc = 1 ORDER BY id ASC"
    );
    while ($row = $db->fetch_array($query2)) {
        $stats = !empty($row['stats_json']) ? json_decode($row['stats_json'], true) : [];
        $dataNpc = !empty($row['data_json']) ? json_decode($row['data_json'], true) : [];

        $raceName = (string)($row['race_name'] ?? '');
        if ($raceName !== '' && function_exists('game_build_stat_context')) {
            $ctx = game_build_stat_context($stats, $raceName);
            $mapped_stats = [];
            foreach (['fue', 'res', 'agi', 'des', 'int', 'inst', 'esp'] as $sk) {
                $mapped_stats[$sk] = $ctx['effective_ranks'][$sk] ?? 1;
            }
        } else {
            $mapped_stats = [];
            foreach (['fue', 'res', 'agi', 'des', 'int', 'inst', 'esp'] as $sk) {
                $rk = max(1, min(9, (int)($stats[$sk] ?? 5)));
                $mapped_stats[$sk] = $rk;
            }
        }

        $npcs[] = [
            'id'             => (int)$row['id'],
            'nombre'         => $row['name'],
            'imagen'         => resolve_avatar($row['avatar'], $mybb->settings['bburl']),
            'tripulacion_id' => null,
            'trip_nombre'    => $row['tripulacion'],
            'trip_imagen'    => '',
            'identificacion' => [
                'apodos' => [],
                'edad' => $dataNpc['age'] ?? 'Desconocida',
                'raza' => $row['race_name'],
                'afiliacion' => $row['faction'] ?: 'Civil',
                'ocupacion' => $row['occupation_name'],
                'estado_actual' => $row['rango'] ?: 'Activo',
            ],
            'stats'          => $mapped_stats,
            'history'        => $dataNpc['history'] ?? '',
            'faction'        => get_standard_faction($row['faction']),
            'type'           => 'major',
            'real_id'        => (int)$row['id']
        ];
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Error al cargar NPCs</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

$bb = $mybb->settings['bburl'];
$cards = [];
foreach ($npcs as $n) {
    $id = $n['identificacion'];

    $faction_display = htmlspecialchars($id['afiliacion'] ?? 'Desconocida');
    $apodo = htmlspecialchars(implode(', ', $id['apodos'] ?? []));
    if ($apodo === '') {
        $apodo = 'NPC Mayor';
    }
    $ocupacion = htmlspecialchars($id['ocupacion'] ?? '');

    if ($n['type'] === 'major' && !empty($n['real_id'])) {
        $link = $bb . '/game/public/personaje.php?pj=' . $n['real_id'];
    } else {
        $link = '#';
    }

    $data_json = htmlspecialchars(json_encode([
        'nombre'      => $n['nombre'],
        'portrait'    => $n['imagen'],
        'apodos'      => $id['apodos'] ?? [],
        'edad'        => $id['edad'] ?? '',
        'raza'        => $id['raza'] ?? '',
        'afiliacion'  => $id['afiliacion'] ?? '',
        'ocupacion'   => $id['ocupacion'] ?? '',
        'estado'      => $id['estado_actual'] ?? '',
        'stats'       => $n['stats'],
        'history'     => $n['history'] ?? '',
        'link'        => $link,
    ]), ENT_QUOTES, 'UTF-8');

    $cards[] = '
    <div class="rpg-lib-card" data-faction="' . $n['faction'] . '" data-npc=\'' . $data_json . '\'>
      <div class="rpg-lib-card-img" data-bg="' . htmlspecialchars($n['imagen'], ENT_QUOTES) . '">
        <span class="rpg-lib-card-badge">' . $faction_display . '</span>
      </div>
      <div class="rpg-lib-card-body">
        <h2 class="rpg-lib-card-title">' . htmlspecialchars($n['nombre']) . '</h2>
        <div class="rpg-lib-card-stats">
          <span class="rpg-lib-card-stat"><i class="fas fa-user-tag"></i> ' . $apodo . '</span>
          <span class="rpg-lib-card-stat"><i class="fas fa-briefcase"></i> ' . $ocupacion . '</span>
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
      <h1>Biblioteca: NPC</h1>
      <p>Explora las fichas completas de los personajes del mundo: estad&iacute;sticas, psicolog&iacute;a, historia y relaciones.</p>
    </div>
  </div>
  <div class="rpg-lib-body">
    <aside class="rpg-lib-sidebar">
      <h3><i class="fas fa-filter"></i> Filtros</h3>
      <div class="rpg-filter-group">
        <span class="rpg-filter-label">Buscar por Nombre</span>
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
        <span class="rpg-lib-modal-badge" id="modal-badge">Afiliaci&oacute;n</span>
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
          <div id="modal-history" class="rpg-lib-modal-text">Historia del personaje...</div>
          <div class="rpg-lib-modal-divider"></div>
          <div class="rpg-lib-modal-section-title"><i class="fas fa-address-card"></i> Datos del Personaje</div>
          <div class="rpg-lib-modal-info-grid" id="modal-info-stats"></div>
          <div class="rpg-lib-modal-ficha-link">
            <a id="modal-link-ficha" href="#" target="_blank" class="rpg-btn--primary rpg-btn--sm"><i class="fas fa-external-link-alt"></i> Ver Ficha Completa</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.NPC_CONFIG = {};
</script>
<script src="<?= rtrim($bb, '/') ?>/jscripts/game/npc.js?v=3"></script>
<?php
$content = ob_get_clean();
game_render_page('NPCs del Mundo', $content);
