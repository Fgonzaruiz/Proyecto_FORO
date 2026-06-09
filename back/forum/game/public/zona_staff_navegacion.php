<?php

declare(strict_types=1);



require_once __DIR__ . '/../bootstrap.php';



global $mybb, $db;



$uid = (int)($mybb->user['uid'] ?? 0);

if (!$uid || game_get_active_staff_level($uid) < 2) {

    header('Location: ' . ($uid ? '../index.php' : '../../member.php?action=login'));

    exit;

}



$b_url = $mybb->settings['bburl'];

$pendingCount = 0;

if ($db->table_exists('game_navigation_voyages') && $db->field_exists('staff_review', 'game_navigation_voyages')) {

    $prefix = TABLE_PREFIX;

    $pendingCount = (int)$db->fetch_field(

        $db->query("SELECT COUNT(*) AS c FROM {$prefix}game_navigation_voyages WHERE staff_review = 'pending'"),

        'c'

    );

}



ob_start();

?>

<div class="rpg-staff-zone">

  <div class="rpg-staff-header rpg-staff-header--zone">

    <div class="rpg-staff-header-content">

      <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver</a>

      <h1><i class="fas fa-ship"></i> Revisión de Viajes</h1>

      <p>Cada travesía iniciada en un post queda pendiente de revisión. Aprueba o deniega; se publicará un mensaje automático en el hilo.</p>

      <p id="nav-pending-badge" class="rpg-staff-badge level-2"><?= $pendingCount ?> pendiente(s)</p>

    </div>

  </div>



  <div class="rpg-staff-section">

    <div class="rpg-form-row">

      <select id="filter-review" class="rpg-input">

        <option value="">Todas las revisiones</option>

        <option value="pending" selected>Pendientes</option>

        <option value="approved">Aprobadas</option>

        <option value="denied">Denegadas</option>

      </select>

      <input type="number" id="filter-thread" class="rpg-input" placeholder="Thread ID (opcional)" />

      <input type="number" id="filter-char" class="rpg-input" placeholder="Personaje ID (opcional)" />

      <button type="button" class="rpg-btn--secondary" id="btn-filter-voyages">Filtrar</button>

    </div>

    <div id="voyages-list" class="rpg-staff-table-wrap rpg-staff-table-wrap--spaced"><p>Cargando…</p></div>

  </div>

</div>

<script>
window.NAV_STAFF_CONFIG = { ajaxBase: '<?= rtrim($b_url, '/') ?>/game/ajax' };
</script>
<script src="<?= $b_url ?>/jscripts/game/zona_staff_navegacion.js?v=2"></script>

<?php

$content = ob_get_clean();

game_render_page('Revisión de Viajes', $content);

