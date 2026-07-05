<?php
/**
 * Vista principal de ficha — orquestador de partials.
 * Contexto: personaje_init.php (vía public/personaje.php).
 */
require __DIR__ . '/_styles.php';
?>
<div class="rpg-char-page">
  <?php if (!$char): ?>
    <?php if ($req_pj_id): ?>
    <div class="rpg-char-empty">
      <i class="fas fa-user-slash"></i>
      <h2>Personaje no encontrado</h2>
      <p>El personaje solicitado no existe o no est&aacute; disponible.</p>
    </div>
    <?php elseif (!$user_id): ?>
    <div class="rpg-char-empty">
      <i class="fas fa-user-lock"></i>
      <h2>Debes iniciar sesi&oacute;n</h2>
      <p>Inicia sesi&oacute;n en el foro para ver tu ficha de personaje.</p>
    </div>
    <?php else: ?>
    <div class="rpg-char-empty">
      <i class="fas fa-user-plus"></i>
      <h2>No tienes personaje</h2>
      <p>A&uacute;n no se ha vinculado ning&uacute;n personaje a tu cuenta. ¡Ve a la gesti&oacute;n de personajes para crear uno!</p>
    </div>
    <?php endif; ?>
  <?php else: ?>
    <?php
    // Evaluate permissions based on ACTIVE CHARACTER
    $active_char_is_staff = false;
    if ($active_id && $active_id !== (int)($char['id'])) {
        $active_q = $db->query("SELECT is_staff FROM {$prefix}game_personajes WHERE id = {$active_id} LIMIT 1");
        if ($a_row = $db->fetch_array($active_q)) {
            $active_char_is_staff = (bool)$a_row['is_staff'];
        }
    } elseif ($active_id && $char && $active_id === (int)$char['id']) {
        $active_char_is_staff = (bool)$char['is_staff'];
    }
    
    $is_active_pj = ($char && $active_id === (int)$char['id']);
    $can_edit = $is_active_pj;
    $can_view_private = ($is_active_pj || $active_char_is_staff);
    ?>

    <?php
    $can_edit_this_pj = false;
    if ($user_id > 0) {
        if ((int)$char['user_id'] === $user_id) {
            $can_edit_this_pj = true;
        } elseif ((int)$char['is_npc'] === 1) {
            $staff_check_q = $db->query("SELECT COUNT(*) as cnt FROM {$prefix}game_personajes WHERE user_id = {$user_id} AND staff_level = 3");
            if ($db->fetch_field($staff_check_q, 'cnt') > 0) {
                $can_edit_this_pj = true;
            } else {
                $assign_check_q = $db->query("SELECT COUNT(*) as cnt FROM {$prefix}game_npc_assignments WHERE character_id = " . (int)$char['id'] . " AND narrator_id = {$user_id}");
                if ($db->fetch_field($assign_check_q, 'cnt') > 0) {
                    $can_edit_this_pj = true;
                }
            }
        }
    }
    ?>

    <?php require __DIR__ . '/_char_context.php'; ?>

    <div class="pj-page-shell hxh-dossier-shell">
        <div class="pj-page-content hxh-dossier-content">
            <?php require __DIR__ . '/_license_header.php'; ?>
            <?php require __DIR__ . '/_tabs_nav.php'; ?>
            <?php require __DIR__ . '/_tab_portada.php'; ?>
            <?php require __DIR__ . '/_tab_expediente.php'; ?>
            <?php require __DIR__ . '/_tab_combate.php'; ?>
            <?php require __DIR__ . '/_tab_linaje.php'; ?>
            <?php require __DIR__ . '/_tab_cronologia.php'; ?>
            <?php if (game_has_nen_despierto((int)$char['id'])): ?>
            <?php require __DIR__ . '/_tab_nen.php'; ?>
            <?php endif; ?>
            <?php if ($can_view_private): ?>
            <?php require __DIR__ . '/_tab_deck.php'; ?>
            <?php require __DIR__ . '/_tab_gestion.php'; ?>
            <?php endif; ?>
            <?php require __DIR__ . '/_hud_bar.php'; ?>
        </div>
    </div>
    <?php require __DIR__ . '/_modals.php'; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_scripts.php'; ?>
