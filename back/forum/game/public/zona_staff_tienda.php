<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    header('Location: ../../member.php?action=login');
    exit;
}

$prefix = TABLE_PREFIX;
global $db;
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    $staff_level = $pj ? (int)$pj['staff_level'] : 0;
}
if ($staff_level < 3) {
    header('Location: ../index.php');
    exit;
}

$b_url = rtrim($mybb->settings['bburl'], '/');

ob_start();
?>
<div class="rpg-peticiones rpg-shop-manage-page">
    <div class="rpg-peticiones-header">
        <div class="rpg-peticiones-header-content">
            <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Zona Staff</a>
            <h1><i class="fas fa-store"></i> Gestionar Tienda</h1>
            <p>Construye el catálogo del bazar: añade solo las cartas que quieras vender y quítalas cuando lo necesites.</p>
        </div>
    </div>

    <div class="rpg-peticiones-form-container rpg-shop-catalog-panel">
        <div class="rpg-shop-catalog-toolbar">
            <div class="rpg-shop-catalog-toolbar__text">
                <h2 class="rpg-shop-catalog-title"><i class="fas fa-shopping-basket"></i> Catálogo del bazar</h2>
                <p class="rpg-shop-catalog-subtitle">Solo aparecen aquí las cartas que hayas añadido. Los jugadores las verán en la tienda pública.</p>
            </div>
            <button type="button" id="shop-btn-add-card" class="rpg-btn rpg-btn--primary">
                <i class="fas fa-plus"></i> Añadir carta
            </button>
        </div>

        <div class="rpg-shop-catalog-filters">
            <input type="search" id="shop-catalog-search" class="textbox rpg-form-input" placeholder="Buscar en el catálogo..." autocomplete="off">
            <select id="shop-catalog-filter-cat" class="textbox rpg-form-select rpg-shop-filter-cat">
                <option value="">Todas las categorías</option>
                <option value="utiles">Útiles</option>
                <option value="armeria">Armería</option>
                <option value="naval">Astillero</option>
                <option value="mascotas">Criadero</option>
            </select>
        </div>

        <div id="shop-catalog-loading" class="rpg-shop-catalog-empty">
            <i class="fas fa-spinner fa-spin"></i> Cargando catálogo...
        </div>
        <ul id="shop-catalog-list" class="rpg-shop-catalog-list rpg-is-hidden" aria-label="Cartas en venta"></ul>
        <p id="shop-catalog-empty" class="rpg-shop-catalog-empty rpg-is-hidden">
            <i class="fas fa-box-open"></i>
            El bazar está vacío. Pulsa <strong>Añadir carta</strong> para elegir qué objetos estarán a la venta.
        </p>
    </div>
</div>

<div id="shop-add-modal" class="rpg-modal-overlay" data-rpg-modal aria-hidden="true">
    <div class="rpg-modal-panel rpg-modal-panel--lg">
        <div class="rpg-modal-header">
            <h3 class="rpg-modal-title"><i class="fas fa-plus-circle"></i> Añadir carta al bazar</h3>
            <button type="button" class="rpg-modal-close" data-rpg-modal-close aria-label="Cerrar">&times;</button>
        </div>
        <div class="rpg-modal-body">
            <p class="rpg-modal-intro">Elige una carta comerciable (equipo, NPC menor u objeto con precio en Jenny) que aún no esté en el catálogo.</p>
            <input type="search" id="shop-pool-search" class="textbox rpg-form-input" placeholder="Buscar por nombre..." autocomplete="off">
            <div id="shop-pool-loading" class="rpg-shop-catalog-empty rpg-is-hidden">Cargando cartas disponibles...</div>
            <ul id="shop-pool-list" class="rpg-shop-pool-list"></ul>
            <p id="shop-pool-empty" class="rpg-shop-catalog-empty rpg-is-hidden">
                No hay más cartas disponibles para añadir. Crea cartas comerciables con precio en Jenny desde Sistema de Cartas.
            </p>
            <div id="shop-add-confirm" class="rpg-shop-add-confirm rpg-is-hidden">
                <h4 class="rpg-form-section-title"><i class="fas fa-check"></i> Confirmar</h4>
                <p class="rpg-shop-add-confirm__name" id="shop-add-confirm-name"></p>
                <label class="rpg-form-label" for="shop-add-category">Categoría en la tienda</label>
                <select id="shop-add-category" class="textbox rpg-form-select">
                    <option value="utiles">Útiles</option>
                    <option value="armeria">Armería</option>
                    <option value="naval">Astillero</option>
                    <option value="mascotas">Criadero</option>
                </select>
            </div>
        </div>
        <div class="rpg-modal-footer">
            <button type="button" class="rpg-btn rpg-btn--secondary" data-rpg-modal-close>Cancelar</button>
            <button type="button" class="rpg-btn rpg-btn--primary rpg-is-hidden" id="shop-add-confirm-btn">
                <i class="fas fa-store"></i> Añadir al bazar
            </button>
        </div>
    </div>
</div>

<script>
window.ZONA_STAFF_TIENDA_CONFIG = { ajaxBase: '<?= htmlspecialchars($b_url) ?>/game/ajax' };
</script>
<script src="<?= htmlspecialchars($b_url) ?>/jscripts/game/rpg_modal.js?v=1"></script>
<script src="<?= htmlspecialchars($b_url) ?>/jscripts/game/zona_staff_tienda.js?v=3"></script>
<?php
$content = ob_get_clean();
game_render_page('Gestionar Tienda — Staff', $content);
