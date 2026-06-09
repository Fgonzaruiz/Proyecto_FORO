<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer, $theme;

$prefix = TABLE_PREFIX;

try {
    $query = $db->query("SELECT * FROM {$prefix}game_estilos ORDER BY id ASC");
    $styles = [];
    while ($row = $db->fetch_array($query)) {
        $tecs_query = $db->query("SELECT * FROM {$prefix}game_tecnicas WHERE estilo_id = " . (int)$row['id'] . " ORDER BY id ASC");
        $tecnicas = [];
        while ($tec_row = $db->fetch_array($tecs_query)) {
            $tecnicas[] = [
                'name' => $tec_row['name'],
                'desc' => $tec_row['desc'],
                'energy_cost' => $tec_row['energy_cost'],
                'damage' => $tec_row['damage'],
            ];
        }
        $styles[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'type' => $row['type'],
            'type_name' => $row['type_name'],
            'req' => $row['req'],
            'req_name' => $row['req_name'],
            'desc' => $row['desc'],
            'details' => $row['details'],
            'banner' => $row['banner'],
            'stats' => [
                'Fuerza Req.' => $row['req_fp'],
                'Destreza Req.' => $row['req_dp'],
                'Consumo Estamina' => $row['consumo_estamina'],
                'Dificultad' => $row['dificultad'],
            ],
            'tecnicas' => $tecnicas,
        ];
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Error al cargar Estilos</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>SQL: SELECT * FROM ' . htmlspecialchars($prefix) . 'game_estilos</p>';
    echo '<p>Verifica que las tablas ' . htmlspecialchars($prefix) . 'game_estilos y ' . htmlspecialchars($prefix) . 'game_tecnicas existen en phpMyAdmin.</p>';
    exit;
}

$banner_url = $mybb->settings['bburl'] . '/images/game/estilos_banner.png';

$cards_html = '';
foreach ($styles as $style) {
    $stats_json = htmlspecialchars(json_encode($style['stats']), ENT_QUOTES, 'UTF-8');
    $tecs_json = htmlspecialchars(json_encode($style['tecnicas']), ENT_QUOTES, 'UTF-8');

    $cards_html .= '
    <div class="rpg-lib-card" data-id="' . $style['id'] . '" data-name="' . htmlspecialchars($style['name']) . '" data-type="' . $style['type'] . '" data-req="' . $style['req'] . '" data-desc="' . htmlspecialchars($style['desc']) . '" data-details="' . htmlspecialchars($style['details']) . '" data-img="' . $banner_url . '" data-stats=\'' . $stats_json . '\' data-tecnicas=\'' . $tecs_json . '\'>
        <div class="rpg-lib-card-img" data-bg="' . htmlspecialchars($banner_url, ENT_QUOTES) . '">
            <span class="rpg-lib-card-badge">' . htmlspecialchars($style['type_name']) . '</span>
        </div>
        <div class="rpg-lib-card-body">
            <h2 class="rpg-lib-card-title">' . htmlspecialchars($style['name']) . '</h2>
            <p class="rpg-lib-card-desc">' . htmlspecialchars($style['desc']) . '</p>
            <div class="rpg-lib-card-stats">
                <span class="rpg-lib-card-stat"><i class="fas fa-dumbbell"></i> ' . htmlspecialchars($style['req_name']) . '</span>
            </div>
        </div>
    </div>';
}

$content = '
<div class="rpg-lib-container">
    <div class="rpg-lib-header">
        <div class="rpg-lib-header-content">
            <h1>Biblioteca: Estilos</h1>
            <p>Conoce los diferentes caminos de combate, técnicas de espada, artes marciales y requerimientos físicos necesarios para dominarlos.</p>
        </div>
    </div>

    <div class="rpg-lib-body">
        <aside class="rpg-lib-sidebar">
            <h3><i class="fas fa-filter"></i> Filtros de Combate</h3>

            <div class="rpg-filter-group">
                <span class="rpg-filter-label">Nombre del Estilo</span>
                <div class="rpg-search-wrapper">
                    <input type="text" id="lib-search" class="textbox" placeholder="Buscar estilo...">
                    <i class="fas fa-search"></i>
                </div>
            </div>

            <div class="rpg-filter-group">
                <span class="rpg-filter-label">Clase de Estilo</span>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="type" value="espadachin" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Esgrima / Espadas
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="type" value="artes-marciales" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Artes Marciales
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="type" value="tirador" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Tirador
                </label>
            </div>

            <div class="rpg-filter-group">
                <span class="rpg-filter-label">Atributo Principal</span>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="req" value="destreza" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Destreza
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="req" value="fuerza" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Fuerza
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="req" value="haki" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Haki Especial
                </label>
            </div>
        </aside>

        <main class="rpg-lib-content">
            <div class="rpg-lib-grid" id="lib-grid">
                ' . $cards_html . '
            </div>
        </main>
    </div>
</div>

<div class="rpg-lib-modal" id="lib-modal">
    <div class="rpg-lib-modal-content">
        <span class="rpg-lib-modal-close" id="modal-close">&times;</span>
        <div class="rpg-lib-modal-body">
            <div class="rpg-lib-modal-header rpg-modal-header-sticky">
                <h2 class="rpg-lib-modal-title" id="modal-title">Nombre</h2>
                <span class="rpg-lib-modal-badge" id="modal-badge">Tipo</span>
            </div>
            <div class="rpg-modal-scroll">
                <div class="rpg-estilo-section">
                    <div class="rpg-estilo-section-title"><i class="fas fa-info-circle"></i> Descripci&oacute;n</div>
                    <p class="rpg-lib-modal-desc" id="modal-details">Descripci&oacute;n del estilo...</p>
                </div>
                <div class="rpg-estilo-section">
                    <div class="rpg-estilo-section-title"><i class="fas fa-clipboard-list"></i> Requisitos</div>
                    <div class="rpg-lib-modal-stats" id="modal-stats"></div>
                </div>
                <div class="rpg-estilo-section">
                    <div class="rpg-estilo-section-title"><i class="fas fa-crosshairs"></i> Cartas del Estilo</div>
                    <div id="modal-tecnicas"></div>
                </div>
            </div>
        </div>
    </div>
</div>

';

$content .= '<script>
window.ESTILOS_CONFIG = {};
</script>
<script src="' . rtrim($mybb->settings['bburl'], '/') . '/jscripts/game/estilos.js?v=1"></script>';

game_render_page('Estilos de Combate', $content);
