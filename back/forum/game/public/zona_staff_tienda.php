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
<div class="rpg-staff-zone">
    <div class="rpg-staff-header rpg-staff-header--tienda">
        <div class="rpg-staff-header-content">
            <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Zona Staff</a>
            <h1><i class="fas fa-store"></i> Gestionar Tienda</h1>
            <p>Activa o retira del catálogo las cartas comerciables (equipo, NPC menor y barco) con precio en berries.</p>
        </div>
    </div>

    <div class="rpg-staff-section">
        <div class="rpg-shop-manage-toolbar">
            <input type="search" id="shop-manage-search" class="textbox rpg-staff-search" placeholder="Buscar carta por nombre...">
            <select id="shop-manage-filter-cat" class="textbox rpg-shop-manage-cat-select">
                <option value="">Todas las categorías</option>
                <option value="utiles">Útiles</option>
                <option value="armeria">Armería</option>
                <option value="naval">Astillero</option>
                <option value="mascotas">Criadero</option>
            </select>
            <select id="shop-manage-filter-status" class="textbox rpg-shop-manage-cat-select">
                <option value="">En venta y no listadas</option>
                <option value="1">Solo en venta</option>
                <option value="0">Solo fuera del catálogo</option>
            </select>
        </div>

        <div id="shop-manage-loading" class="rpg-staff-catalog-empty">Cargando catálogo comerciable...</div>
        <div class="rpg-shop-manage-table-wrap rpg-is-hidden" id="shop-manage-wrap">
            <table class="rpg-shop-manage-table" aria-label="Catálogo de tienda">
                <thead>
                    <tr>
                        <th>Carta</th>
                        <th>Tipo</th>
                        <th>Precio (B.)</th>
                        <th>Categoría</th>
                        <th>En venta</th>
                    </tr>
                </thead>
                <tbody id="shop-manage-tbody"></tbody>
            </table>
        </div>
        <p id="shop-manage-empty" class="rpg-staff-catalog-empty rpg-is-hidden">No hay cartas comerciables con precio definido.</p>
    </div>
</div>

<script>
window.ZONA_STAFF_TIENDA_CONFIG = { ajaxBase: '<?= htmlspecialchars($b_url) ?>/game/ajax' };
</script>
<script src="<?= htmlspecialchars($b_url) ?>/jscripts/game/rpg_modal.js?v=1"></script>
<script src="<?= htmlspecialchars($b_url) ?>/jscripts/game/zona_staff_tienda.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Gestionar Tienda — Staff', $content);
