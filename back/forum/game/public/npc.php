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

$npcs = [];

try {
    // 1. Static NPCs from game_npc_profiles
    $query1 = $db->query(
        "SELECT p.*, t.nombre AS trip_nombre, t.imagen AS trip_imagen
         FROM {$prefix}game_npc_profiles p
         LEFT JOIN {$prefix}game_tripulaciones t ON p.tripulacion_id = t.id
         ORDER BY p.id ASC"
    );
    while ($row = $db->fetch_array($query1)) {
        $id_data = json_decode($row['identificacion'], true) ?: [];
        $npcs[] = [
            'id'             => (int)$row['id'],
            'nombre'         => $row['nombre'],
            'imagen'         => resolve_avatar($row['imagen'], $mybb->settings['bburl']),
            'tripulacion_id' => (int)$row['tripulacion_id'],
            'trip_nombre'    => $row['trip_nombre'],
            'trip_imagen'    => $row['trip_imagen'],
            'identificacion' => $id_data,
            'perfil_fisico'  => json_decode($row['perfil_fisico'], true) ?: [],
            'psicologia'     => json_decode($row['psicologia'], true) ?: [],
            'motivaciones'   => json_decode($row['motivaciones'], true) ?: [],
            'perfil_estrategico' => json_decode($row['perfil_estrategico'], true) ?: [],
            'cronologia'     => json_decode($row['cronologia'], true) ?: [],
            'relaciones'     => json_decode($row['relaciones'], true) ?: [],
            'stats'          => json_decode($row['stats'], true) ?: [],
            'faction'        => get_standard_faction($id_data['afiliacion'] ?? 'Civil'),
        ];
    }

    // 2. Major NPCs from game_personajes where is_npc = 1
    $query2 = $db->query(
        "SELECT * FROM {$prefix}game_personajes WHERE is_npc = 1 ORDER BY id ASC"
    );
    while ($row = $db->fetch_array($query2)) {
        $stats = !empty($row['stats_json']) ? json_decode($row['stats_json'], true) : [];
        $data = !empty($row['data_json']) ? json_decode($row['data_json'], true) : [];
        
        $mapped_stats = [
            'FP' => (int)($stats['fue'] ?? 5),
            'DP' => (int)($stats['des'] ?? 5),
            'RP' => (int)($stats['agi'] ?? 5),
            'IP' => (int)($stats['int'] ?? 5),
            'VP' => (int)($stats['inst'] ?? 5),
            'HP' => (int)($stats['esp'] ?? 0),
        ];

        $npcs[] = [
            'id'             => (int)$row['id'] + 10000,
            'nombre'         => $row['name'],
            'imagen'         => resolve_avatar($row['avatar'], $mybb->settings['bburl']),
            'tripulacion_id' => null,
            'trip_nombre'    => $row['tripulacion'],
            'trip_imagen'    => '',
            'identificacion' => [
                'apodos' => [],
                'edad' => $data['age'] ?? 'Desconocida',
                'raza' => $row['race_name'],
                'afiliacion' => $row['faction'] ?: 'Civil',
                'ocupacion' => $row['occupation_name'],
                'estado_actual' => $row['rango'] ?: 'Activo',
            ],
            'perfil_fisico'  => [],
            'psicologia'     => [
                'descripcion' => $row['desc']
            ],
            'motivaciones'   => [],
            'perfil_estrategico' => [],
            'cronologia'     => [
                'resumen' => $row['details']
            ],
            'relaciones'     => [],
            'stats'          => $mapped_stats,
            'faction'        => get_standard_faction($row['faction']),
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
    $abs = function(string $url) use ($bb): string {
        return preg_match('#^https?://#i', $url) ? $url : $bb . '/' . $url;
    };
    $crew_img = $n['trip_imagen'] ? $abs($n['trip_imagen']) : $bb . '/images/game/npc_banner.png';
    $portrait = $n['imagen'];
    
    $faction_display = htmlspecialchars($id['afiliacion'] ?? 'Desconocida');
    $apodo = htmlspecialchars(implode(', ', $id['apodos'] ?? []));
    if ($apodo === '') {
        $apodo = 'NPC Mayor';
    }
    $ocupacion = htmlspecialchars($id['ocupacion'] ?? '');
    
    $data_json = htmlspecialchars(json_encode([
        'nombre'      => $n['nombre'],
        'portrait'    => $portrait,
        'crew_banner' => $crew_img,
        'apodos'      => $id['apodos'] ?? [],
        'edad'        => $id['edad'] ?? '',
        'raza'        => $id['raza'] ?? '',
        'afiliacion'  => $id['afiliacion'] ?? '',
        'ocupacion'   => $id['ocupacion'] ?? '',
        'estado'      => $id['estado_actual'] ?? '',
        'descripcion' => $n['psicologia']['descripcion'] ?? '',
        'resumen'     => $n['cronologia']['resumen'] ?? '',
        'stats'       => $n['stats'],
    ]), ENT_QUOTES, 'UTF-8');
    
    $cards[] = '
    <div class="rpg-lib-card" data-faction="' . $n['faction'] . '" data-npc=\'' . $data_json . '\'>
      <div class="rpg-lib-card-img" data-bg="' . htmlspecialchars($portrait, ENT_QUOTES) . '">
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
  <div class="rpg-lib-banner" data-bg="<?= htmlspecialchars($bb . '/images/game/npc_banner.png', ENT_QUOTES) ?>">
    <div class="rpg-lib-banner-content">
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

<div class="rpg-lib-modal" id="lib-modal">
  <div class="rpg-lib-modal-content rpg-modal-npc">
    <span class="rpg-lib-modal-close" id="modal-close">&times;</span>
    <div class="rpg-modal-npc-top">
      <div class="rpg-modal-npc-banner" id="modal-banner" data-bg="<?= htmlspecialchars($bb . '/images/game/npc_banner.png', ENT_QUOTES) ?>"></div>
      <div class="rpg-modal-npc-head">
        <h2 class="rpg-lib-modal-title" id="modal-title">Nombre</h2>
        <span class="rpg-lib-modal-badge" id="modal-badge">Afiliaci&oacute;n</span>
      </div>
    </div>
    <div class="rpg-modal-npc-body">
      <div class="rpg-modal-npc-top-grid">
        <div class="rpg-modal-npc-left-col">
          <div class="rpg-modal-npc-portrait-wrap" id="modal-portrait-section">
            <img id="modal-portrait" class="rpg-modal-npc-portrait" src="" alt="Retrato">
          </div>
          <div class="rpg-modal-npc-radar-wrap">
            <div class="rpg-radar-container" id="modal-radar-wrapper"></div>
          </div>
        </div>
        <div class="rpg-modal-npc-right-col">
          <div class="rpg-modal-npc-right-top">
            <div class="rpg-modal-npc-section">
              <div class="rpg-modal-npc-section-title"><i class="fas fa-address-card"></i> Identificaci&oacute;n</div>
              <div class="rpg-modal-npc-info-grid" id="modal-info-grid"></div>
            </div>
            <div class="rpg-modal-npc-section">
              <div class="rpg-modal-npc-section-title"><i class="fas fa-book-open"></i> Resumen</div>
              <p class="rpg-modal-npc-text" id="modal-resumen">...</p>
            </div>
          </div>
          <div class="rpg-modal-npc-section rpg-modal-npc-bottom">
            <div class="rpg-modal-npc-section-title"><i class="fas fa-brain"></i> Psicolog&iacute;a</div>
            <p class="rpg-modal-npc-text" id="modal-descripcion">...</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.NPC_CONFIG = {};
</script>
<script src="<?= rtrim($bb, '/') ?>/jscripts/game/npc.js?v=2"></script>
<?php
$content = ob_get_clean();
game_render_page('NPCs del Mundo', $content);
