<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb;

$bbUrl = rtrim((string)$mybb->settings['bburl'], '/');
$bannerUrl = $bbUrl . '/images/game/estilos_banner.png';

$cardsBySlug = game_estilos_canonicos_cards_by_slug();
$styles = game_estilos_canonicos_list(true);

$categories = [];
foreach ($styles as $style) {
    $categories[$style['category']] = $style['category_label'];
}

$cardsHtml = '';
foreach ($styles as $style) {
    $slug = $style['slug'];
    $linkedCards = $cardsBySlug[$slug] ?? [];
    $reqJson = htmlspecialchars(json_encode($style['requirements'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    $advJson = htmlspecialchars(json_encode($style['advantages'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    $cardsJson = htmlspecialchars(json_encode($linkedCards, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    $img = $style['image_url'] !== '' ? $style['image_url'] : $bannerUrl;

    $cardsHtml .= '
    <div class="rpg-lib-card" role="button" tabindex="0"
        data-slug="' . htmlspecialchars($slug, ENT_QUOTES) . '"
        data-name="' . htmlspecialchars($style['name'], ENT_QUOTES) . '"
        data-type="' . htmlspecialchars($style['category'], ENT_QUOTES) . '"
        data-req="' . htmlspecialchars($style['primary_stat'], ENT_QUOTES) . '"
        data-desc="' . htmlspecialchars($style['short_desc'], ENT_QUOTES) . '"
        data-details="' . htmlspecialchars($style['description'], ENT_QUOTES) . '"
        data-img="' . htmlspecialchars($img, ENT_QUOTES) . '"
        data-disciplina="' . htmlspecialchars($style['disciplina_slug'], ENT_QUOTES) . '"
        data-requirements=\'' . $reqJson . '\'
        data-advantages=\'' . $advJson . '\'
        data-cartas=\'' . $cardsJson . '\'>
        <div class="rpg-lib-card-img" data-bg="' . htmlspecialchars($img, ENT_QUOTES) . '">
            <span class="rpg-lib-card-badge">' . htmlspecialchars($style['category_label']) . '</span>
        </div>
        <div class="rpg-lib-card-body">
            <h2 class="rpg-lib-card-title">' . htmlspecialchars($style['name']) . '</h2>
            <p class="rpg-lib-card-desc">' . htmlspecialchars($style['short_desc']) . '</p>
            <div class="rpg-lib-card-stats">
                <span class="rpg-lib-card-stat"><i class="fas fa-scroll"></i> ' . count($linkedCards) . ' carta(s)</span>
                <span class="rpg-lib-card-stat"><i class="fas fa-crosshairs"></i> ' . htmlspecialchars($style['disciplina_slug'] ?: '—') . '</span>
            </div>
        </div>
    </div>';
}

$filterTypes = '';
foreach ($categories as $catSlug => $catLabel) {
    $filterTypes .= '
                <label class="rpg-filter-option">
                    <input type="checkbox" name="type" value="' . htmlspecialchars($catSlug, ENT_QUOTES) . '" checked>
                    <span class="rpg-filter-checkbox"></span>
                    ' . htmlspecialchars($catLabel) . '
                </label>';
}

$content = '
<div class="rpg-lib-container">
    <div class="rpg-lib-header">
        <div class="rpg-lib-header-content">
            <h1>Biblioteca: Estilos canónicos</h1>
            <p>Escuelas y tradiciones de combate del mundo (Karate Gyojin, Okama Kenpō, Rokushiki…). Son <strong>flavor IC</strong> y agrupan cartas técnicas; la progresión mecánica sigue en <strong>disciplinas</strong> (grados I–V).</p>
        </div>
    </div>

    <div class="rpg-lib-body">
        <aside class="rpg-lib-sidebar">
            <h3><i class="fas fa-filter"></i> Filtros</h3>

            <div class="rpg-filter-group">
                <span class="rpg-filter-label">Nombre del estilo</span>
                <div class="rpg-search-wrapper">
                    <input type="text" id="lib-search" class="textbox" placeholder="Buscar estilo...">
                    <i class="fas fa-search"></i>
                </div>
            </div>

            <div class="rpg-filter-group">
                <span class="rpg-filter-label">Categoría</span>
                ' . $filterTypes . '
            </div>

            <div class="rpg-filter-group">
                <span class="rpg-filter-label">Atributo principal</span>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="req" value="fuerza" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Fuerza
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="req" value="destreza" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Destreza
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="req" value="agilidad" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Agilidad
                </label>
            </div>
        </aside>

        <main class="rpg-lib-content">
            <div class="rpg-lib-grid" id="lib-grid">' . ($cardsHtml !== '' ? $cardsHtml : '<p class="rpg-estilo-empty">No hay estilos en el catálogo. Ejecuta la migración <code>migrate_estilos_canonicos.php</code>.</p>') . '</div>
        </main>
    </div>
</div>

<div class="rpg-lib-modal rpg-lib-modal--xl" id="lib-modal">
    <div class="rpg-lib-modal-content rpg-lib-modal-content--xl">
        <span class="rpg-lib-modal-close" id="modal-close">&times;</span>
        <div class="rpg-lib-modal-body rpg-lib-modal-body--xl">
            <div class="rpg-lib-modal-header">
                <h2 class="rpg-lib-modal-title" id="modal-title">Estilo</h2>
                <span class="rpg-lib-modal-badge" id="modal-badge">Categoría</span>
            </div>
            <div class="rpg-modal-scroll rpg-modal-scroll-sm">
                <p class="rpg-lib-modal-desc" id="modal-details"></p>
            </div>
            <div class="rpg-estilo-section">
                <div class="rpg-estilo-section-title"><i class="fas fa-clipboard-check"></i> Requisitos</div>
                <ul class="rpg-estilo-list" id="modal-requirements"></ul>
            </div>
            <div class="rpg-estilo-section">
                <div class="rpg-estilo-section-title"><i class="fas fa-star"></i> Ventajas</div>
                <ul class="rpg-estilo-list rpg-estilo-list--advantages" id="modal-advantages"></ul>
            </div>
            <div class="rpg-estilo-section">
                <div class="rpg-estilo-section-title"><i class="fas fa-layer-group"></i> Cartas del estilo</div>
                <div id="modal-tecnicas"></div>
            </div>
        </div>
    </div>
</div>

<script>window.ESTILOS_CONFIG = {};</script>
<script src="' . $bbUrl . '/jscripts/game/estilos.js?v=2"></script>';

game_render_page('Biblioteca: Estilos canónicos', $content);
