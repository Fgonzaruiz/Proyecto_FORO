<?php
declare(strict_types=1);

define('THIS_SCRIPT', 'calendario.php');

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $headerinclude, $header, $footer, $theme, $templates;
$bburl = $mybb->settings['bburl'];

if (empty($headerinclude) && isset($templates)) {
    eval('$headerinclude = "'.$templates->get('headerinclude').'";');
    eval('$header = "'.$templates->get('header').'";');
    eval('$footer = "'.$templates->get('footer').'";');
}

ob_start();
?>
<div class="pj-cal-page">
    <h1 id="calendar-page-title" class="pj-cal-title">Calendario On-Rol</h1>
    
    <div class="pj-cal-layout">
        <div id="calendar-grid-container" class="pj-cal-grid-wrap">
            <div id="calendar-grid" class="pj-cal-grid">
                <div class="pj-cal-loading">Cargando calendario...</div>
            </div>
        </div>
        
        <div id="calendar-details" class="pj-cal-details">
            <div class="pj-cal-details-empty">
                <i class="fas fa-hand-pointer"></i>
                Selecciona un día para ver sus eventos
            </div>
        </div>
    </div>
</div>

<script>
window.CALENDARIO_CONFIG = { bburl: '<?= $bburl ?>' };
</script>
<script src="<?= rtrim($bburl, '/') ?>/jscripts/game/calendario.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Calendario On-Rol', $content);
