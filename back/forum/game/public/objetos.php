<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer, $theme;

$prefix = TABLE_PREFIX;

try {
    $query = $db->query("SELECT * FROM {$prefix}game_objetos ORDER BY id ASC");
    $objects = [];
    while ($row = $db->fetch_array($query)) {
        $objects[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'category' => $row['category'],
            'category_name' => $row['category_name'],
            'rarity' => $row['rarity'],
            'rarity_name' => $row['rarity_name'],
            'desc' => $row['desc'],
            'details' => $row['details'],
            'banner' => $row['banner'],
            'stats' => [
                'Tipo de Objeto' => $row['tipo_objeto'],
                'Bono' => $row['bono'],
                'Req. Uso' => $row['req_uso'],
                'Precio Tienda' => $row['precio'],
            ],
        ];
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Error al cargar Objetos</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>SQL: SELECT * FROM ' . htmlspecialchars($prefix) . 'game_objetos</p>';
    echo '<p>Verifica que la tabla ' . htmlspecialchars($prefix) . 'game_objetos existe en phpMyAdmin.</p>';
    exit;
}

$banner_url = $mybb->settings['bburl'] . '/images/game/objetos_banner.png';

$cards_html = '';
foreach ($objects as $obj) {
    $stats_json = htmlspecialchars(json_encode($obj['stats']), ENT_QUOTES, 'UTF-8');

    $cards_html .= '
    <div class="rpg-lib-card" data-id="' . $obj['id'] . '" data-name="' . htmlspecialchars($obj['name']) . '" data-category="' . $obj['category'] . '" data-rarity="' . $obj['rarity'] . '" data-desc="' . htmlspecialchars($obj['desc']) . '" data-details="' . htmlspecialchars($obj['details']) . '" data-img="' . $banner_url . '" data-stats=\'' . $stats_json . '\'>
        <div class="rpg-lib-card-img" data-bg="' . htmlspecialchars($banner_url, ENT_QUOTES) . '">
            <span class="rpg-lib-card-badge">' . htmlspecialchars($obj['category_name']) . '</span>
        </div>
        <div class="rpg-lib-card-body">
            <h2 class="rpg-lib-card-title">' . htmlspecialchars($obj['name']) . '</h2>
            <p class="rpg-lib-card-desc">' . htmlspecialchars($obj['desc']) . '</p>
            <div class="rpg-lib-card-stats">
                <span class="rpg-lib-card-stat"><i class="fas fa-gem"></i> ' . htmlspecialchars($obj['rarity_name']) . '</span>
            </div>
        </div>
    </div>';
}

$content = '
<div class="rpg-lib-container">
    <div class="rpg-lib-banner" data-bg="' . htmlspecialchars($banner_url, ENT_QUOTES) . '">
        <div class="rpg-lib-banner-content">
            <h1>Biblioteca: Objetos</h1>
            <p>Consulta el catálogo de armas de filo, armaduras, consumibles revitalizantes y metales raros necesarios para fabricar tu propio equipamiento.</p>
        </div>
    </div>

    <div class="rpg-lib-body">
        <aside class="rpg-lib-sidebar">
            <h3><i class="fas fa-filter"></i> Filtros de Objetos</h3>

            <div class="rpg-filter-group">
                <span class="rpg-filter-label">Nombre del Objeto</span>
                <div class="rpg-search-wrapper">
                    <input type="text" id="lib-search" class="textbox" placeholder="Buscar objeto...">
                    <i class="fas fa-search"></i>
                </div>
            </div>

            <div class="rpg-filter-group">
                <span class="rpg-filter-label">Categoría</span>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="category" value="armas" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Armas
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="category" value="consumibles" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Consumibles
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="category" value="materiales" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Materiales de Forja
                </label>
            </div>

            <div class="rpg-filter-group">
                <span class="rpg-filter-label">Rareza del Objeto</span>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="rarity" value="comun" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Común
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="rarity" value="raro" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Raro
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="rarity" value="epico" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Épico
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="rarity" value="legendario" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Legendario
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
        <div class="rpg-lib-modal-banner" id="modal-banner"></div>
        <div class="rpg-lib-modal-body">
            <div class="rpg-lib-modal-header rpg-modal-header-sticky">
                <h2 class="rpg-lib-modal-title" id="modal-title">Nombre</h2>
                <span class="rpg-lib-modal-badge" id="modal-badge">Categor&iacute;a</span>
            </div>
            <div class="rpg-modal-scroll rpg-modal-scroll-sm">
                <p class="rpg-lib-modal-desc" id="modal-details">Detalles t&eacute;cnicos del objeto...</p>
            </div>
            <div class="rpg-modal-scroll-sm">
                <div class="rpg-lib-modal-stats" id="modal-stats"></div>
            </div>
        </div>
    </div>
</div>

';

$content .= '<script>
window.OBJETOS_CONFIG = {};
</script>
<script src="' . rtrim($mybb->settings['bburl'], '/') . '/jscripts/game/objetos.js?v=1"></script>';

game_render_page('Objetos, Equipamiento y Forja', $content);
