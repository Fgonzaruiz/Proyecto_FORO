<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer, $theme;

$prefix = TABLE_PREFIX;

try {
    $query = $db->query(
        "SELECT p.*, t.nombre AS trip_nombre, t.imagen AS trip_imagen
         FROM {$prefix}game_npc_profiles p
         LEFT JOIN {$prefix}game_tripulaciones t ON p.tripulacion_id = t.id
         ORDER BY p.id ASC"
    );
    $npcs = [];
    while ($row = $db->fetch_array($query)) {
        $npcs[] = [
            'id'             => (int)$row['id'],
            'nombre'         => $row['nombre'],
            'imagen'         => $row['imagen'],
            'tripulacion_id' => (int)$row['tripulacion_id'],
            'trip_nombre'    => $row['trip_nombre'],
            'trip_imagen'    => $row['trip_imagen'],
            'identificacion' => json_decode($row['identificacion'], true),
            'perfil_fisico'  => json_decode($row['perfil_fisico'], true),
            'psicologia'     => json_decode($row['psicologia'], true),
            'motivaciones'   => json_decode($row['motivaciones'], true),
            'perfil_estrategico' => json_decode($row['perfil_estrategico'], true),
            'cronologia'     => json_decode($row['cronologia'], true),
            'relaciones'     => json_decode($row['relaciones'], true),
            'stats'          => json_decode($row['stats'], true),
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
    $portrait = $n['imagen'] ? $abs($n['imagen']) : '';
    $faction = htmlspecialchars($id['afiliacion'] ?? 'Desconocida');
    $apodo = htmlspecialchars(implode(', ', $id['apodos'] ?? []));
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
    $cards[] = '<div class="rpg-lib-card" data-npc=\'' . $data_json . '\'><div class="rpg-lib-card-img" data-bg="' . htmlspecialchars($crew_img, ENT_QUOTES) . '"><span class="rpg-lib-card-badge">' . $faction . '</span></div><div class="rpg-lib-card-body"><h2 class="rpg-lib-card-title">' . htmlspecialchars($n['nombre']) . '</h2><div class="rpg-lib-card-stats"><span class="rpg-lib-card-stat"><i class="fas fa-user-tag"></i> ' . $apodo . '</span><span class="rpg-lib-card-stat"><i class="fas fa-briefcase"></i> ' . $ocupacion . '</span></div></div></div>';
}
$cards_html = implode("\n", $cards);

ob_start();
?>
<div class="rpg-lib-container">
  <div class="rpg-lib-banner" data-bg="<?= htmlspecialchars($mybb->settings['bburl'] . '/images/game/npc_banner.png', ENT_QUOTES) ?>">
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
        <span class="rpg-filter-label">Afiliaci&oacute;n</span>
        <label class="rpg-filter-option"><input type="checkbox" name="af" value="Marina" checked><span class="rpg-filter-checkbox"></span>Marina</label>
        <label class="rpg-filter-option"><input type="checkbox" name="af" value="Piratas" checked><span class="rpg-filter-checkbox"></span>Piratas</label>
        <label class="rpg-filter-option"><input type="checkbox" name="af" value="Revolucionarios" checked><span class="rpg-filter-checkbox"></span>Revolucionarios</label>
        <label class="rpg-filter-option"><input type="checkbox" name="af" value="Civiles" checked><span class="rpg-filter-checkbox"></span>Civiles</label>
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
      <div class="rpg-modal-npc-banner" id="modal-banner"></div>
      <div class="rpg-modal-npc-head">
        <h2 class="rpg-lib-modal-title" id="modal-title">Nombre</h2>
        <span class="rpg-lib-modal-badge" id="modal-badge">Afiliaci&oacute;n</span>
      </div>
    </div>
    <div class="rpg-modal-npc-body">
      <div class="rpg-modal-npc-top-grid">
        <div class="rpg-modal-npc-left-col">
          <div class="rpg-modal-npc-portrait-wrap" id="modal-portrait-section" class="rpg-is-hidden">
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
<script src="<?= rtrim($mybb->settings['bburl'], '/') ?>/jscripts/game/npc.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('NPCs del Mundo', $content);
