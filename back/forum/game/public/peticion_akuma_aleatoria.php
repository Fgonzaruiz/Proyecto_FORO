<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb;

if (!isset($mybb) || !is_object($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$b_url = $mybb->settings['bburl'];

ob_start();
?>
<div class="rpg-peticiones rpg-akuma-roll-page">
  <div class="rpg-peticiones-header rpg-akuma-roll-header">
    <div class="rpg-peticiones-header-content">
      <a href="<?= htmlspecialchars($b_url) ?>/game/public/peticion_akuma.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Modo de petici&oacute;n</a>
      <h1><i class="fas fa-dice"></i> Akuma Aleatoria</h1>
      <p id="akuma-roll-subtitle">Explora el cat&aacute;logo y lanza la ruleta. Solo participan frutas no ocupadas.</p>
    </div>
    <div class="rpg-akuma-roll-actions">
      <span id="akuma-available-count" class="rpg-akuma-available-badge">— disponibles</span>
      <button type="button" id="akuma-roll-btn" class="rpg-btn-primary rpg-akuma-roll-btn" disabled>
        <i class="fas fa-dice"></i> ¡Tirar aleatorio!
      </button>
    </div>
  </div>

  <div id="akuma-roll-stage" class="rpg-akuma-roll-stage rpg-is-hidden" aria-live="polite">
    <div class="rpg-akuma-roll-wheel">
      <div id="akuma-roll-spinner" class="rpg-akuma-roll-spinner"></div>
      <div class="rpg-akuma-roll-pointer"><i class="fas fa-caret-down"></i></div>
    </div>
    <p id="akuma-roll-status" class="rpg-akuma-roll-status">Girando...</p>
  </div>

  <div id="akuma-roll-result" class="rpg-akuma-roll-result rpg-is-hidden">
    <div class="rpg-akuma-result-card">
      <span class="rpg-akuma-result-label">¡Has obtenido!</span>
      <h2 id="akuma-result-name"></h2>
      <p id="akuma-result-meta" class="rpg-akuma-result-meta"></p>
      <p id="akuma-result-desc" class="rpg-akuma-result-desc"></p>
      <p class="rpg-akuma-result-note"><i class="fas fa-info-circle"></i> Se ha generado una petici&oacute;n administrativa. El staff la revisar&aacute; y te responder&aacute; por mensaje directo.</p>
    </div>
  </div>

  <div id="akuma-catalog" class="rpg-akuma-catalog">
    <div class="rpg-peticiones-loading"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Cargando cat&aacute;logo...</div>
  </div>
</div>

<script>
window.PETICION_AKUMA_ALEATORIA_CONFIG = { bburl: '<?= $b_url ?>' };
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/peticion_akuma_aleatoria.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Akuma Aleatoria', $content);
