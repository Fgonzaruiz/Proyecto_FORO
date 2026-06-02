<?php
/**
 * Vista principal de ficha — orquestador de partials.
 * Contexto: personaje_init.php (vía public/personaje.php).
 */
require __DIR__ . '/_styles.php';
require __DIR__ . '/_shell_open.php';
?>
  <div style="display: flex; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: var(--radius-lg); overflow: hidden; min-height: 700px; margin-top: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
      <?php require __DIR__ . '/_sidebar.php'; ?>
      <div style="flex:1; padding: 40px; overflow-y:auto;">
          <?php require __DIR__ . '/_tabs_nav.php'; ?>
          <?php require __DIR__ . '/_tab_bio.php'; ?>
          <?php require __DIR__ . '/_tab_linaje.php'; ?>
          <?php require __DIR__ . '/_tab_cronologia.php'; ?>
          <?php if ($can_view_private): ?>
          <?php require __DIR__ . '/_tab_deck.php'; ?>
          <?php require __DIR__ . '/_tab_gestion.php'; ?>
          <?php endif; ?>
      </div>
  </div>
  <?php require __DIR__ . '/_modals.php'; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_scripts.php'; ?>
