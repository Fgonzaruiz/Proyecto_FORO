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
<div class="rpg-akuma-container">
  <!-- Header -->
  <div class="rpg-peticiones-header rpg-akuma-roll-header">
    <div class="rpg-peticiones-header-content">
      <a href="<?= htmlspecialchars($b_url) ?>/game/public/peticion_akuma.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Modo de solicitud</a>
      <h1><i class="fas fa-dice"></i> Akuma Aleatoria</h1>
      <p id="akuma-roll-subtitle">Consulta el cat&aacute;logo de frutas registradas en el foro y tienta a la suerte con el sorteo aleatorio.</p>
    </div>
  </div>

  <div id="akuma-roll-blocked" class="rpg-akuma-roll-blocked rpg-is-hidden"></div>

  <!-- Two-Column Layout -->
  <div class="rpg-akuma-layout">
    
    <!-- Left Column: Catalog -->
    <main class="rpg-akuma-main">
      <div class="rpg-akuma-catalog-toolbar">
        <div class="rpg-akuma-search-wrap">
          <input type="text" id="akuma-search-input" class="textbox" placeholder="Buscar fruta del diablo...">
          <i class="fas fa-search"></i>
        </div>
        <div id="akuma-filter-tabs" class="rpg-akuma-filter-tabs">
          <!-- Dynamically filled: Todas, Logia, Zoan, Paramecia -->
        </div>
      </div>
      
      <div id="akuma-catalog" class="rpg-akuma-catalog-grid-wrap">
        <div class="rpg-peticiones-loading"><i class="fas fa-spinner fa-spin"></i> Cargando cat&aacute;logo de frutas...</div>
      </div>
    </main>

    <!-- Right Column: Sidebar (Stats & Roll Panel) -->
    <aside class="rpg-akuma-sidebar">
      
      <!-- Stats Panel -->
      <div class="rpg-akuma-sidebar-card rpg-akuma-stats-panel">
        <h3><i class="fas fa-chart-pie"></i> Estado General</h3>
        <div id="akuma-stats-list" class="rpg-akuma-stats-list">
          <!-- Filled dynamically: Total, Libres, Reservadas, Ocupadas -->
        </div>
      </div>

      <!-- Roll Control Deck -->
      <div class="rpg-akuma-sidebar-card rpg-akuma-roll-deck">
        <h3><i class="fas fa-dice"></i> Sorteo de Fruta</h3>
        
        <div class="rpg-akuma-roll-actions">
          <span id="akuma-available-count" class="rpg-akuma-available-badge">— disponibles</span>
          <button type="button" id="akuma-roll-btn" class="rpg-btn--primary rpg-akuma-roll-btn" style="width: 100%;" disabled>
            <i class="fas fa-dice"></i> &iexcl;Tirar aleatorio!
          </button>
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
            <span class="rpg-akuma-result-label">&iexcl;Has obtenido!</span>
            <h2 id="akuma-result-name"></h2>
            <p id="akuma-result-meta" class="rpg-akuma-result-meta"></p>
            <p id="akuma-result-desc" class="rpg-akuma-result-desc"></p>
            <p class="rpg-akuma-result-note"><i class="fas fa-info-circle"></i> Se ha registrado la petici&oacute;n. El equipo de administraci&oacute;n la revisar&aacute; a la brevedad.</p>
          </div>
        </div>
      </div>
      
    </aside>

  </div>
</div>

<!-- Library Modal (Shared layout style) -->
<div class="rpg-lib-modal" id="lib-modal">
    <div class="rpg-lib-modal-content">
        <span class="rpg-lib-modal-close" id="modal-close">&times;</span>
        <div class="rpg-lib-modal-banner" id="modal-banner"></div>
        <div class="rpg-lib-modal-body">
            <div class="rpg-lib-modal-header rpg-modal-header-sticky">
                <h2 class="rpg-lib-modal-title" id="modal-title">Nombre</h2>
                <span class="rpg-lib-modal-badge" id="modal-badge">Clase</span>
            </div>
            <div class="rpg-modal-scroll rpg-modal-scroll-sm">
                <p class="rpg-lib-modal-desc" id="modal-details">Descripci&oacute;n...</p>
            </div>
            <div class="rpg-modal-scroll-sm">
                <div class="rpg-lib-modal-stats" id="modal-stats"></div>
            </div>
        </div>
    </div>
</div>

<script>
window.PETICION_AKUMA_ALEATORIA_CONFIG = { bburl: '<?= $b_url ?>' };
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/peticion_akuma_aleatoria.js?v=5"></script>
<?php
$content = ob_get_clean();
game_render_page('Akuma Aleatoria', $content);
