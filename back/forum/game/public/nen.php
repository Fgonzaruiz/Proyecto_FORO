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
$showMizuFlow = $nenState === null || ($nenState && !$nenState['nen_type_locked']);
$advancedTechniques = game_get_nen_advanced_techniques();

$GLOBALS['hxh_stamp'] = 'NEN · CONTROL DE AURA';
$GLOBALS['hxh_title'] = 'Sistema Nen (Hatsu)';
$GLOBALS['hxh_subtitle'] = 'Canaliza tu energía vital y entrena tus principios Nen.';
$GLOBALS['hxh_icon'] = '✕';

ob_start();
?>
<div class="rpg-peticiones rpg-nen-player-page hxh-tramites-body">
  <div class="rpg-nen-toolbar">
      <a href="<?= $peticiones_url ?>" class="rpg-nen-hero-back"><i class="fas fa-arrow-left"></i> Volver a Trámites</a>
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
    <?php elseif ($showMizuFlow): ?>
      <div class="rpg-nen-locked-state" id="nen-locked-state">
        <div class="rpg-nen-locked-icon"><i class="fas fa-mug-hot"></i></div>
        <h2><?= $nenState ? 'Prueba del Agua Pendiente' : 'Tu Aura está Dormida' ?></h2>
        <p>Coloca una hoja sobre un vaso lleno de agua y proyecta tu Ren. El agua revelará tu afinidad Nen.</p>
        <button type="button" class="rpg-nen-btn-despertar" id="btn-despertar-nen">
          <i class="fas fa-hand-sparkles"></i> <?= $nenState ? 'Continuar Prueba del Agua' : 'Iniciar Prueba del Agua' ?>
        </button>
        <div id="nen-despertar-msg" class="rpg-nen-msg rpg-is-hidden"></div>
      </div>

      <div id="nen-mizu-inline" class="nen-mizu-inline rpg-is-hidden">
        <p class="nen-mizu-instruction" id="mizu-instruction">Coloca una hoja sobre el vaso. Proyecta tu Ren y toca el agua.</p>
        <button type="button" class="nen-mizu-glass-scene" id="mizu-glass-touch" aria-label="Tocar el vaso de agua">
          <div class="nen-mizu-leaf" id="mizu-leaf"><span class="nen-mizu-leaf-vein"></span></div>
          <div class="nen-mizu-glass-vessel">
            <div class="nen-mizu-glass-rim"></div>
            <div class="nen-mizu-glass-body">
              <div class="nen-mizu-water" id="mizu-water">
                <div class="nen-mizu-water-surface"></div>
                <div class="nen-mizu-water-glow" id="mizu-water-glow"></div>
              </div>
              <div class="nen-mizu-ripple" id="mizu-ripple"></div>
            </div>
            <div class="nen-mizu-glass-base"></div>
          </div>
          <span class="nen-mizu-touch-hint" id="mizu-touch-hint"><i class="fas fa-hand-pointer"></i> Toca el vaso</span>
        </button>
        <div id="nen-mizu-reveal" class="nen-mizu-reveal rpg-is-hidden">
          <p class="nen-mizu-reveal-label">Tu afinidad Nen es</p>
          <h3 class="nen-mizu-reveal-type" id="mizu-reveal-type"></h3>
          <div class="nen-mizu-affinities" id="mizu-affinities-preview"></div>
        </div>
      </div>
    <?php else:
      $affinities = game_get_nen_type_affinities($nenState['nen_type']);
      $typeColor = game_get_nen_type_color($nenState['nen_type']);
    ?>
      <div class="rpg-nen-active-layout">
        <div class="rpg-nen-active-header">
          <div>
            <span class="rpg-nen-active-type-label" data-nen-color-text="<?= htmlspecialchars($typeColor, ENT_QUOTES) ?>">
              <?= game_get_nen_type_label($nenState['nen_type']) ?>
            </span>
            <h2 class="rpg-nen-active-type-name"><?= game_get_nen_type_label($nenState['nen_type']) ?></h2>
          </div>
          <?php if ($nenState['aura_color']): ?>
            <div class="rpg-nen-aura-chip">
              Color del Aura: <strong data-nen-aura-color="<?= htmlspecialchars($nenState['aura_color'], ENT_QUOTES) ?>"><?= htmlspecialchars($nenState['aura_color']) ?></strong>
            </div>
          <?php endif; ?>
        </div>

        <section class="rpg-nen-section">
          <h3 class="pj-tab-section-heading"><i class="fas fa-chart-pie"></i> Afinidades Nen</h3>
          <p class="rpg-nen-section-lead">Tu tipo natural comienza en Maestría V. La Especialización solo aplica si te sale en la prueba del agua.</p>
          <?= game_render_nen_hex_chart($affinities, $nenState['nen_type']) ?>
          <div class="rpg-nen-hex-legend">
            <?php foreach ($affinities as $aff):
              if (!empty($aff['unavailable'])) continue;
            ?>
              <span class="rpg-nen-hex-legend-item">
                <span class="rpg-nen-type-dot" data-nen-color="<?= htmlspecialchars($aff['color'], ENT_QUOTES) ?>"></span>
                <?= htmlspecialchars($aff['label']) ?> · <?= game_get_nen_maestria_label((int)$aff['maestria']) ?>
              </span>
            <?php endforeach; ?>
            <?php
              $specAff = null;
              foreach ($affinities as $aff) {
                  if ($aff['slug'] === 'specialization') { $specAff = $aff; break; }
              }
              if ($specAff && !empty($specAff['unavailable'])):
            ?>
              <span class="rpg-nen-hex-legend-item rpg-nen-hex-legend-item--na">
                <span class="rpg-nen-type-dot" data-nen-color="<?= htmlspecialchars($specAff['color'], ENT_QUOTES) ?>"></span>
                Especialización · Sin afinidad
              </span>
            <?php endif; ?>
          </div>
        </section>

        <div class="rpg-nen-cols">
          <div>
            <h3 class="pj-tab-section-heading"><i class="fas fa-dumbbell"></i> Principios Fundamentales</h3>
            <div class="rpg-nen-principles-grid">
              <?php foreach ($nenState['principles'] as $p => $pInfo):
                $pName = game_get_nen_principle_label($p);
                $pLevelLabel = game_get_nen_principle_level_label($pInfo['level']);
                $pct = $pInfo['level'] * 25;
                $canTrain = $pInfo['level'] < 4;
                $isHatsu = $p === 'hatsu';
              ?>
                <div class="rpg-nen-principle-card <?= $isHatsu ? 'rpg-nen-principle-card--hatsu' : '' ?>">
                  <div class="rpg-nen-principle-card-header">
                    <strong><?= $pName ?></strong>
                    <span class="rpg-nen-principle-level-tag"><?= $pLevelLabel ?></span>
                  </div>
                  <div class="rpg-nen-progress-row">
                    <div class="rpg-nen-progress-bg">
                      <div class="rpg-nen-progress-fill" data-nen-color-bg="<?= htmlspecialchars($typeColor, ENT_QUOTES) ?>" data-width="<?= $pct ?>"></div>
                    </div>
                    <?php if ($canTrain): ?>
                      <button type="button" class="rpg-btn--primary rpg-nen-btn-train" onclick="requestTrainPrinciple('<?= $p ?>', <?= $pInfo['level'] + 1 ?>)">
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
              <h3 class="pj-tab-section-heading"><i class="fas fa-hand-sparkles"></i> Hatsu (Habilidad Nen)</h3>
              <button type="button" class="rpg-btn--primary" onclick="openAbilityModal()">
                <i class="fas fa-plus"></i> Proponer / Actualizar
              </button>
            </div>
            <p class="rpg-nen-section-lead">Define tu técnica personal. Las propuestas requieren aprobación del staff.</p>
            <div class="rpg-nen-abilities-scroll">
              <?php if (empty($nenState['abilities'])): ?>
                <p class="rpg-shop-empty"><i class="fas fa-box-open"></i> Sin Hatsu propuesto aún.</p>
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

        <section class="rpg-nen-section rpg-nen-section--advanced">
          <h3 class="pj-tab-section-heading"><i class="fas fa-bolt"></i> Técnicas Avanzadas</h3>
          <p class="rpg-nen-section-lead">Aplicaciones del Nen más allá de los cuatro principios. Entrena cada una por separado.</p>
          <div class="rpg-nen-advanced-grid">
            <?php foreach ($advancedTechniques as $tech): ?>
              <div class="rpg-nen-advanced-card">
                <div class="rpg-nen-advanced-head">
                  <span class="rpg-nen-advanced-num"><?= htmlspecialchars($tech['num']) ?></span>
                  <strong><?= htmlspecialchars($tech['name']) ?></strong>
                </div>
                <p class="rpg-nen-advanced-desc"><?= htmlspecialchars($tech['desc']) ?></p>
                <div class="rpg-nen-advanced-foot">
                  <span class="rpg-nen-advanced-level">Nivel 0 · Sin entrenar</span>
                  <button type="button" class="rpg-btn--primary rpg-nen-btn-train" onclick="requestTrainAdvanced('<?= htmlspecialchars($tech['id'], ENT_QUOTES) ?>')">
                    Solicitar entrenamiento
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div id="nen-advanced-msg" class="rpg-nen-msg rpg-is-hidden"></div>
        </section>
      </div>
    <?php endif; ?>
  </div>
</div>

<div id="propose-hatsu-modal" class="rpg-nen-modal-overlay rpg-is-hidden">
  <div class="rpg-nen-modal-panel">
    <div class="rpg-nen-modal-header">
      <h3><i class="fas fa-hand-sparkles"></i> Proponer Hatsu</h3>
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
            <option value="D">Rango D</option>
            <option value="C">Rango C</option>
            <option value="B">Rango B</option>
            <option value="A">Rango A</option>
            <option value="S">Rango S</option>
            <option value="SS">Rango SS</option>
          </select>
        </div>
        <div class="rpg-form-group">
          <label class="rpg-form-label">Coste PE</label>
          <input type="number" id="hatsu-cost" class="rpg-form-input textbox" min="0" placeholder="40" required />
        </div>
      </div>
      <div class="rpg-form-group">
        <label class="rpg-form-label">Descripción y efecto</label>
        <textarea id="hatsu-desc" class="rpg-form-input textbox" required></textarea>
      </div>
      <div class="rpg-form-group">
        <label class="rpg-form-label">Restricciones (Vows)</label>
        <div id="conditions-list"></div>
        <button type="button" class="rpg-nen-add-condition-btn" onclick="addConditionInput()"><i class="fas fa-plus"></i> Añadir</button>
      </div>
      <div id="hatsu-submit-msg" class="rpg-nen-msg rpg-is-hidden"></div>
      <div class="rpg-nen-modal-footer">
        <button type="button" class="rpg-btn" onclick="closeAbilityModal()">Cancelar</button>
        <button type="submit" class="rpg-btn--primary">Enviar</button>
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
<script src="<?= $nen_js_url ?>?v=7"></script>
<?php
$content = ob_get_clean();
game_render_page('Gestión Nen — Hunter × Hunter', $content);
