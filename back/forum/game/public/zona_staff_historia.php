<?php
declare(strict_types=1);

define('THIS_SCRIPT', 'zona_staff_historia.php');
require_once __DIR__ . '/../bootstrap.php';

game_require_staff_character();

global $mybb, $db;
$prefix = TABLE_PREFIX;

$uid = (int)$mybb->user['uid'];
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = " . (int)$cfg['active_pj_id'] . " LIMIT 1");
$pj = $db->fetch_array($pj_q);

if (!$pj || (int)$pj['staff_level'] < 3) {
    error_no_permission();
}

// Cargar el JSON de lore actual
$jsonPath = __DIR__ . '/../lore.json';
$jsonContent = file_exists($jsonPath) ? file_get_contents($jsonPath) : '{"eras":[],"eventos":[]}';
$loreData = json_decode($jsonContent, true);

$bburl = $mybb->settings['bburl'];

$b_url = $bburl;

ob_start();
?>
<div class="rpg-staff-zone rpg-staff-zone--wide">
    <div class="rpg-staff-header rpg-staff-header--historia">
        <div class="rpg-staff-header-content">
            <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Zona Staff</a>
            <h1><i class="fas fa-history"></i> Gestión de la Línea de Tiempo (Lore)</h1>
            <p>Edita eras y eventos históricos. Los eventos pueden enlazar a hilos reales del foro mediante el campo <strong>Link</strong> (opcional).</p>
        </div>
    </div>

<div class="rpg-staff-historia-container">
    <div class="rpg-staff-historia-header">
        <button type="button" id="btn-save-lore" class="rpg-btn--primary">
            <i class="fas fa-save"></i> Guardar Cambios en Lore.json
        </button>
    </div>

    <div class="rpg-staff-historia-layout">
        <!-- Panel Izquierdo: Lista/Árbol de Eras y Eventos -->
        <div class="rpg-staff-historia-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <h2>Estructura de Eras</h2>
                <button type="button" id="btn-add-era" class="rpg-tree-btn" title="Agregar Nueva Era">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            
            <div id="historia-tree" class="rpg-historia-tree">
                <!-- Rellenado dinámicamente con JS -->
                <div class="rpg-staff-historia-loading">Cargando árbol de historia...</div>
            </div>
        </div>

        <!-- Panel Derecho: Formulario Dinámico de Edición -->
        <div class="rpg-staff-historia-panel" id="editor-panel">
            <h2>Editor de Elemento</h2>
            <div id="editor-form-container">
                <div class="rpg-historia-editor-empty">
                    <i class="fas fa-mouse-pointer" style="font-size: 32px; display: block; margin-bottom: 12px; opacity: 0.5;"></i>
                    Selecciona una Era o un Evento en el panel izquierdo para editar, o crea uno nuevo.
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
window.LORE_CONFIG = {
    bburl: '<?= $bburl ?>',
    csrfToken: '<?= $mybb->post_code ?>',
    data: <?= json_encode($loreData, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= rtrim($bburl, '/ ') ?>/jscripts/game/zona_staff_historia.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Gestión de Línea de Tiempo', $content);
