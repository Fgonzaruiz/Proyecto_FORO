      <!-- LEFT COLUMN (Avatar & Stats) -->
      <div class="pj-sidebar">
          <div class="pj-sidebar-avatar" data-bg="<?= htmlspecialchars($char['avatar'] ?: 'https://placehold.co/320x450', ENT_QUOTES) ?>"></div>
          
          <div class="pj-sidebar-body">
              <h2 class="pj-sidebar-name"><?= htmlspecialchars($char['name']) ?></h2>
              
              <div class="pj-sidebar-badges">
                  <?php if ($char['status'] === 'aprobada'): ?>
                      <span class="pj-badge pj-badge--ok"><i class="fas fa-check-circle"></i> Aprobada</span>
                  <?php elseif ($char['status'] === 'revision'): ?>
                      <span class="pj-badge pj-badge--warn"><i class="fas fa-sync-alt"></i> En Revisión</span>
                  <?php elseif ($char['status'] === 'rechazada'): ?>
                      <span class="pj-badge pj-badge--err"><i class="fas fa-times-circle"></i> Rechazada</span>
                  <?php else: ?>
                      <span class="pj-badge pj-badge--err"><i class="fas fa-clock"></i> Pendiente</span>
                  <?php endif; ?>
                  <span class="pj-badge pj-badge--faction"><i class="fas fa-flag"></i> Facci&oacute;n</span>
                  <span class="pj-badge pj-badge--rank"><i class="fas fa-medal"></i> <?= htmlspecialchars($char['rango'] ?: 'Sin Rango') ?></span>
                  <?php if ($char['is_staff']): ?>
                    <span class="pj-badge pj-badge--staff"><i class="fas fa-star"></i> Staff</span>
                  <?php endif; ?>
                  <span class="pj-badge pj-badge--level"><i class="fas fa-level-up-alt"></i> Nivel <?= (int)($pj_progression['nivel'] ?? 1) ?></span>
              </div>
              
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
              if ($can_edit_this_pj):
              ?>
                  <div class="pj-sidebar-actions" style="margin: 12px 0 16px;">
                      <?php if ($char['status'] !== 'aprobada' && $char['status'] !== 'muerto'): ?>
                          <a href="<?= htmlspecialchars($bburl) ?>/game/public/crear_personaje.php?pj_id=<?= (int)$char['id'] ?>" class="rpg-pj-btn rpg-pj-btn-edit rpg-pj-btn--block" style="text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;">
                              <i class="fas fa-edit"></i> Editar Ficha Completa
                          </a>
                      <?php else: ?>
                          <a href="<?= htmlspecialchars($bburl) ?>/game/public/mis_personajes.php?edit_pj=<?= (int)$char['id'] ?>" class="rpg-pj-btn rpg-pj-btn-edit rpg-pj-btn--block" style="text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;">
                              <i class="fas fa-user-edit"></i> Editar Avatar / Firma
                          </a>
                      <?php endif; ?>
                  </div>
              <?php endif; ?>
              
              <div class="pj-sidebar-info">
                  <div class="pj-info-row pj-info-row--border">
                      <i class="fas fa-shield-alt pj-info-icon"></i>
                      <div>
                          <div class="pj-info-label">Arquetipo B&eacute;lico</div>
                          <div class="pj-info-value"><?= htmlspecialchars($char['arquetipo']) ?></div>
                      </div>
                  </div>
                  <div class="pj-info-row">
                      <i class="fas fa-anchor pj-info-icon"></i>
                      <div>
                          <div class="pj-info-label">Oficio</div>
                          <div class="pj-info-value"><?= htmlspecialchars($char['job_name'] ?: 'Ninguno') ?></div>
                      </div>
                  </div>
              </div>
              
              <?php
              $fue = $char['stats']['fue'];
              $agi = $char['stats']['agi'];
              $des = $char['stats']['des'];
              $inst = $char['stats']['inst'];
              $esp = $char['stats']['esp'];
              $int = $char['stats']['int'];

              $pv = ($fue * 4) + ($agi * 2) + ($esp * 3) + ($int * 1);
              $pe = ($esp * 4) + ($des * 3) + ($agi * 2) + ($int * 1);
              ?>
              
              <div class="pj-vitals-row">
                  <div class="pj-vital pj-vital--pv">
                      <div class="pj-vital__label">Puntos de Vida (PV)</div>
                      <div class="pj-vital__value"><?= $pv ?></div>
                  </div>
                  <div class="pj-vital pj-vital--pe">
                      <div class="pj-vital__label">Puntos de Energía (PE)</div>
                      <div class="pj-vital__value"><?= $pe ?></div>
                  </div>
              </div>

              <h3 class="pj-stats-heading">Atributos Base</h3>
              <div class="rpg-preview-stat-row">
                  <div class="rpg-preview-stat-label"><span>FUERZA (FUE)</span><span><?= $fue ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill rpg-preview-stat-fill--fue" data-pct="<?= min(100, $fue * 10) ?>"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div class="rpg-preview-stat-label"><span>AGILIDAD (AGI)</span><span><?= $agi ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill rpg-preview-stat-fill--agi" data-pct="<?= min(100, $agi * 10) ?>"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div class="rpg-preview-stat-label"><span>DESTREZA (DES)</span><span><?= $des ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill rpg-preview-stat-fill--des" data-pct="<?= min(100, $des * 10) ?>"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div class="rpg-preview-stat-label"><span>INSTINTO (INST)</span><span><?= $inst ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill rpg-preview-stat-fill--inst" data-pct="<?= min(100, $inst * 10) ?>"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div class="rpg-preview-stat-label"><span>ESPÍRITU (ESP)</span><span><?= $esp ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill rpg-preview-stat-fill--esp" data-pct="<?= min(100, $esp * 10) ?>"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div class="rpg-preview-stat-label"><span>INTELECTO (INT)</span><span><?= $int ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill rpg-preview-stat-fill--int" data-pct="<?= min(100, $int * 10) ?>"></div></div>
              </div>
          </div>
      </div>
      
