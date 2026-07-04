<?php
declare(strict_types=1);

define('THIS_SCRIPT', 'nen.php');

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $headerinclude, $header, $footer, $theme, $templates;

if (empty($headerinclude) && isset($templates)) {
    eval('$headerinclude = "'.$templates->get('headerinclude').'";');
    eval('$header = "'.$templates->get('header').'";');
    eval('$footer = "'.$templates->get('footer').'";');
}

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    header('Location: ' . $mybb->settings['bburl'] . '/member.php?action=login');
    exit;
}

$prefix = TABLE_PREFIX;
$b_url = rtrim($mybb->settings['bburl'], '/');
$my_post_key = $mybb->post_code;

// Personaje activo
$char_id = game_get_active_pj_id($uid);
$character = null;
if ($char_id > 0) {
    $char_q = $db->query("SELECT id, name, avatar, status FROM {$prefix}game_personajes WHERE id = {$char_id} LIMIT 1");
    $character = $db->fetch_array($char_q);
}

$nenState = null;
if ($char_id > 0) {
    $nenState = game_get_nen_state($char_id);
}

$peticiones_url = htmlspecialchars($b_url . '/game/public/peticiones_general.php', ENT_QUOTES);
$nen_js_url = htmlspecialchars($b_url . '/jscripts/game/nen.js', ENT_QUOTES);

ob_start();
?>
<div class="rpg-peticiones rpg-nen-player-page">
  <div class="rpg-nen-hero-header">
    <div class="rpg-peticiones-header-content">
      <a href="<?= $peticiones_url ?>" class="rpg-nen-hero-back"><i class="fas fa-arrow-left"></i> Volver a Trámites</a>
      <h1><i class="fas fa-hand-sparkles"></i> Control de Aura Nen</h1>
      <p>Canaliza tu energía vital, define tu Hatsu y entrena los principios del Nen.</p>
    </div>
  </div>

  <div class="rpg-nen-container">
    <?php if ($char_id <= 0): ?>
      <div class="rpg-nen-locked-state">
        <div class="rpg-nen-locked-icon"><i class="fas fa-user-slash"></i></div>
        <h2>Sin Personaje Activo</h2>
        <p>Debes seleccionar un personaje activo en la sección de control antes de gestionar tu Nen.</p>
      </div>
    <?php elseif ($character['status'] !== 'aprobada'): ?>
      <div class="rpg-nen-locked-state">
        <div class="rpg-nen-locked-icon"><i class="fas fa-clock"></i></div>
        <h2>Personaje Pendiente de Aprobación</h2>
        <p>Tu personaje debe estar completamente aprobado por el staff para desbloquear o entrenar Nen.</p>
      </div>
    <?php elseif (!$nenState): ?>
      <div class="rpg-nen-locked-state">
        <div class="rpg-nen-locked-icon"><i class="fas fa-lock"></i></div>
        <h2>Tu Aura está Dormida</h2>
        <p>Todos los seres vivos poseen aura, pero se requiere entrenamiento o un suceso impactante para abrir los nodos de aura y poder manipularla de forma consciente. Solicita tu despertar al staff.</p>
        <button type="button" class="rpg-nen-btn-despertar" id="btn-despertar-nen">
          <i class="fas fa-key"></i> Solicitar Despertar Nen
        </button>
        <div id="nen-despertar-msg" class="rpg-nen-msg rpg-is-hidden"></div>
      </div>
    <?php elseif (!$nenState['nen_type_locked']): ?>
      <div class="rpg-nen-taza-state">
        <div class="rpg-nen-taza-intro">
          <div class="rpg-nen-taza-icon"><i class="fas fa-mug-hot"></i></div>
          <h2>La Prueba de la Taza (Mizushinger)</h2>
          <p>Has despertado tu Nen. Ahora es momento de colocar una hoja sobre un vaso lleno de agua y proyectar tu Ren para revelar tu afinidad de aura. Elige tu tipo para enviar la solicitud de afinidad irreversible al staff.</p>
        </div>

        <div class="rpg-nen-type-grid" id="taza-type-grid">
          <?php
          $types = ['enhancement', 'transmutation', 'emission', 'conjuration', 'manipulation', 'specialization'];
          $descs = [
              'enhancement'   => 'Aumenta la fuerza natural y regeneración del cuerpo y objetos.',
              'transmutation' => 'Cambia las propiedades físicas del aura para imitar elementos o sustancias.',
              'emission'      => 'Permite proyectar y separar el aura del propio cuerpo a largas distancias.',
              'conjuration'   => 'Materializa objetos y estructuras a partir del aura pura.',
              'manipulation'  => 'Controla e infunde el aura en entes biológicos o mecánicos.',
              'specialization'=> 'Poderes únicos e inclasificables que no encajan en otra categoría.',
          ];
          foreach ($types as $t):
              $tColor = game_get_nen_type_color($t);
              $tLabel = game_get_nen_type_label($t);
          ?>
            <div class="taza-option-card" data-nen-type="<?= $t ?>" data-nen-color="<?= htmlspecialchars($tColor, ENT_QUOTES) ?>" onclick="selectTazaType('<?= $t ?>', this)">
              <h3>
                <span class="rpg-nen-type-dot" data-nen-color="<?= htmlspecialchars($tColor, ENT_QUOTES) ?>"></span>
                <span data-nen-color-text="<?= htmlspecialchars($tColor, ENT_QUOTES) ?>"><?= $tLabel ?></span>
              </h3>
              <p><?= $descs[$t] ?></p>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="rpg-nen-taza-submit-wrap">
          <input type="hidden" id="selected-nen-type" value="" />
          <button type="button" class="rpg-nen-btn-taza" id="btn-submit-taza" disabled onclick="submitTazaRequest()">
            <i class="fas fa-paper-plane"></i> Enviar Elección de Aura
          </button>
          <div id="nen-taza-msg" class="rpg-nen-msg rpg-is-hidden"></div>
        </div>
      </div>
    <?php else: ?>
      <div class="rpg-nen-active-layout">
        <div class="rpg-nen-active-header">
          <div>
            <span class="rpg-nen-active-type-label" data-nen-color-text="<?= htmlspecialchars(game_get_nen_type_color($nenState['nen_type']), ENT_QUOTES) ?>"><?= game_get_nen_type_label($nenState['nen_type']) ?></span>
            <h2 class="rpg-nen-active-type-name"><?= game_get_nen_type_label($nenState['nen_type']) ?></h2>
          </div>
          <?php if ($nenState['aura_color']): ?>
            <div class="rpg-nen-aura-chip">
              Color del Aura: <strong data-nen-aura-color="<?= htmlspecialchars($nenState['aura_color'], ENT_QUOTES) ?>"><?= htmlspecialchars($nenState['aura_color']) ?></strong>
            </div>
          <?php endif; ?>
        </div>

        <div class="rpg-nen-cols">
          <div>
            <h3 class="pj-tab-section-heading"><i class="fas fa-dumbbell"></i> Progresión de Principios</h3>
            <div class="rpg-nen-principles-grid">
              <?php foreach ($nenState['principles'] as $p => $pInfo):
                $pName = game_get_nen_principle_label($p);
                $pLevelLabel = game_get_nen_principle_level_label($pInfo['level']);
                $pct = $pInfo['level'] * 25;
                $canTrain = $pInfo['level'] < 4;
              ?>
                <div class="rpg-nen-principle-card">
                  <div class="rpg-nen-principle-card-header">
                    <strong><?= $pName ?></strong>
                    <span class="rpg-nen-principle-level-tag"><?= $pLevelLabel ?></span>
                  </div>
                  <div class="rpg-nen-progress-row">
                    <div class="rpg-nen-progress-bg">
                      <div class="rpg-nen-progress-fill" data-nen-color-bg="<?= htmlspecialchars(game_get_nen_type_color($nenState['nen_type']), ENT_QUOTES) ?>" data-width="<?= $pct ?>"></div>
                    </div>
                    <?php if ($canTrain): ?>
                      <button type="button" class="rpg-btn rpg-btn-primary rpg-nen-btn-train" onclick="requestTrainPrinciple('<?= $p ?>', <?= $pInfo['level'] + 1 ?>)">
                        Niv. <?= $pInfo['level'] + 1 ?>
                      </button>
                    <?php else: ?>
                      <span class="rpg-nen-train-max"><i class="fas fa-check-circle"></i> Máximo</span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div id="nen-train-msg" class="rpg-nen-msg rpg-is-hidden"></div>
          </div>

          <div>
            <div class="rpg-nen-abilities-header">
              <h3 class="pj-tab-section-heading"><i class="fas fa-hand-sparkles"></i> Mis Hatsu</h3>
              <button type="button" class="rpg-btn rpg-btn-primary" onclick="openAbilityModal()">
                <i class="fas fa-plus"></i> Proponer
              </button>
            </div>
            <div class="rpg-nen-abilities-scroll">
              <?php if (empty($nenState['abilities'])): ?>
                <p class="rpg-shop-empty"><i class="fas fa-box-open"></i> Sin habilidades propuestas aún.</p>
              <?php else: ?>
                <?php foreach ($nenState['abilities'] as $ab):
                  $approved = (int)$ab['approved'] === 1;
                ?>
                  <div class="rpg-nen-ability-item <?= $approved ? 'rpg-nen-ability-item--approved' : 'rpg-nen-ability-item--pending' ?>">
                    <div class="rpg-nen-ability-item-header">
                      <h4><?= htmlspecialchars($ab['name']) ?></h4>
                      <span class="rpg-nen-ability-status <?= $approved ? 'rpg-nen-ability-status--approved' : 'rpg-nen-ability-status--pending' ?>">
                        <?= $approved ? 'Aprobada' : 'Pendiente' ?>
                      </span>
                    </div>
                    <p class="rpg-nen-ability-desc"><?= nl2br(htmlspecialchars($ab['description'])) ?></p>
                    <div class="rpg-nen-ability-meta">
                      <span>Rango: <strong><?= htmlspecialchars($ab['rank']) ?></strong></span>
                      <span>•</span>
                      <span>Coste: <strong><?= (int)$ab['nen_cost'] ?> PE</strong></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL: PROPONER HATSU -->
<div id="propose-hatsu-modal" class="rpg-nen-modal-overlay rpg-is-hidden">
  <div class="rpg-nen-modal-panel">
    <div class="rpg-nen-modal-header">
      <h3><i class="fas fa-hand-sparkles"></i> Proponer Nueva Habilidad Hatsu</h3>
      <button type="button" class="rpg-nen-modal-close" onclick="closeAbilityModal()">&times;</button>
    </div>
    <form id="propose-hatsu-form" class="rpg-nen-modal-form" onsubmit="submitHatsuProposal(event)">
      <div class="rpg-form-group">
        <label class="rpg-form-label">Nombre del Hatsu</label>
        <input type="text" id="hatsu-name" class="rpg-form-input textbox" placeholder="Ej: Jajanken" required />
      </div>
      <div class="rpg-nen-modal-two-col">
        <div class="rpg-form-group">
          <label class="rpg-form-label">Rango Sugerido</label>
          <select id="hatsu-rank" class="rpg-form-input textbox" required>
            <option value="D">Rango D (Básico)</option>
            <option value="C">Rango C (Intermedio)</option>
            <option value="B">Rango B (Avanzado)</option>
            <option value="A">Rango A (Élite)</option>
            <option value="S">Rango S (Maestro)</option>
            <option value="SS">Rango SS (Legendario)</option>
          </select>
        </div>
        <div class="rpg-form-group">
          <label class="rpg-form-label">Coste de Aura Sugerido (PE)</label>
          <input type="number" id="hatsu-cost" class="rpg-form-input textbox" min="0" placeholder="Ej: 40" required />
        </div>
      </div>
      <div class="rpg-form-group">
        <label class="rpg-form-label">Descripción Completa y Efecto</label>
        <textarea id="hatsu-desc" class="rpg-form-input textbox" placeholder="Describe detalladamente el funcionamiento de tu técnica..." required></textarea>
      </div>
      <div class="rpg-form-group">
        <label class="rpg-form-label">Votos y Condiciones (Restricciones)</label>
        <div id="conditions-list"></div>
        <button type="button" class="rpg-nen-add-condition-btn" onclick="addConditionInput()">
          <i class="fas fa-plus"></i> Añadir Restricción
        </button>
      </div>
      <div id="hatsu-submit-msg" class="rpg-nen-msg rpg-is-hidden"></div>
      <div class="rpg-nen-modal-footer">
        <button type="button" class="rpg-btn" onclick="closeAbilityModal()">Cancelar</button>
        <button type="submit" class="rpg-btn rpg-btn-primary">Enviar Propuesta</button>
      </div>
    </form>
  </div>
</div>

<script>
window.NEN_CONFIG = {
    my_post_key: <?= json_encode($my_post_key) ?>,
    character_id: <?= json_encode($char_id) ?>,
    bburl: <?= json_encode($b_url) ?>
};
</script>
<script src="<?= $nen_js_url ?>"></script>
<?php
$content = ob_get_clean();
game_render_page('Gestión Nen — Hunter × Hunter', $content);
