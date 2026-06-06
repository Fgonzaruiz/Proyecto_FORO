      <!-- LEFT COLUMN (Avatar & Stats) -->
      <div class="pj-sidebar">
          <?php
          $pj_avatar_url = $char['avatar'] ?: 'https://placehold.co/290x450';
          $fac_slug = 'civil';
          $fac_raw = strtolower((string)($char['faction'] ?? ''));
          if ($char['is_staff']) {
              $fac_slug = 'staff';
          } elseif (strpos($fac_raw, 'pirata') !== false) {
              $fac_slug = 'pirata';
          } elseif (strpos($fac_raw, 'marine') !== false || strpos($fac_raw, 'marina') !== false) {
              $fac_slug = 'marine';
          } elseif (strpos($fac_raw, 'cazador') !== false) {
              $fac_slug = 'cazador';
          } elseif (strpos($fac_raw, 'revolucion') !== false) {
              $fac_slug = 'revolucionario';
          } elseif (strpos($fac_raw, 'gobierno') !== false) {
              $fac_slug = 'gobierno';
          }
          ?>
          <div class="pj-sidebar-avatar">
              <img src="<?= htmlspecialchars($pj_avatar_url, ENT_QUOTES) ?>" width="290" height="450" alt="">
          </div>
          
          <div class="pj-sidebar-body">
              <h2 class="pj-sidebar-name pj-sidebar-name--<?= htmlspecialchars($fac_slug, ENT_QUOTES) ?>"><?= htmlspecialchars($char['name']) ?></h2>
              
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
                  <span class="pj-badge pj-badge--faction"><i class="fas fa-flag"></i> <?= htmlspecialchars($char['faction'] ?: 'Civil') ?></span>
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
                  <div class="pj-sidebar-actions">
                      <?php if ($char['status'] !== 'aprobada' && $char['status'] !== 'muerto'): ?>
                          <a href="<?= htmlspecialchars($bburl) ?>/game/public/crear_personaje.php?pj_id=<?= (int)$char['id'] ?>" class="rpg-pj-btn rpg-pj-btn-edit rpg-pj-btn--block rpg-pj-btn--block-center">
                              <i class="fas fa-edit"></i> Editar Ficha Completa
                          </a>
                      <?php else: ?>
                          <a href="<?= htmlspecialchars($bburl) ?>/game/public/mis_personajes.php?edit_pj=<?= (int)$char['id'] ?>" class="rpg-pj-btn rpg-pj-btn-edit rpg-pj-btn--block rpg-pj-btn--block-center">
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
              $vit = $char['stats']['vit'];

              $pv = ($fue * 4) + ($agi * 2) + ($esp * 3) + ($int * 1);
              $pe = ($esp * 4) + ($des * 3) + ($agi * 2) + ($int * 1);
              $pj_nivel = (int)($pj_progression['nivel'] ?? 1);
              $max_stat_ref = max(10, $pj_nivel * 10);
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
              <div class="rpg-post-pj-stats">
                  <div class="rpg-pj-stat-row">
                      <div class="rpg-pj-stat-label">
                          <span><i class="fas fa-dumbbell"></i> FUERZA</span>
                          <span class="rpg-pj-stat-text"><?= $fue ?> / <?= $max_stat_ref ?></span>
                      </div>
                      <div class="rpg-pj-stat-bar-bg">
                          <div class="rpg-pj-stat-bar-fill rpg-pj-stat-bar-fill--fue" data-pct="<?= min(100, ($fue / $max_stat_ref) * 100) ?>"></div>
                      </div>
                  </div>
                  <div class="rpg-pj-stat-row">
                      <div class="rpg-pj-stat-label">
                          <span><i class="fas fa-running"></i> AGILIDAD</span>
                          <span class="rpg-pj-stat-text"><?= $agi ?> / <?= $max_stat_ref ?></span>
                      </div>
                      <div class="rpg-pj-stat-bar-bg">
                          <div class="rpg-pj-stat-bar-fill rpg-pj-stat-bar-fill--agi" data-pct="<?= min(100, ($agi / $max_stat_ref) * 100) ?>"></div>
                      </div>
                  </div>
                  <div class="rpg-pj-stat-row">
                      <div class="rpg-pj-stat-label">
                          <span><i class="fas fa-bullseye"></i> DESTREZA</span>
                          <span class="rpg-pj-stat-text"><?= $des ?> / <?= $max_stat_ref ?></span>
                      </div>
                      <div class="rpg-pj-stat-bar-bg">
                          <div class="rpg-pj-stat-bar-fill rpg-pj-stat-bar-fill--des" data-pct="<?= min(100, ($des / $max_stat_ref) * 100) ?>"></div>
                      </div>
                  </div>
                  <div class="rpg-pj-stat-row">
                      <div class="rpg-pj-stat-label">
                          <span><i class="fas fa-eye"></i> INSTINTO</span>
                          <span class="rpg-pj-stat-text"><?= $inst ?> / <?= $max_stat_ref ?></span>
                      </div>
                      <div class="rpg-pj-stat-bar-bg">
                          <div class="rpg-pj-stat-bar-fill rpg-pj-stat-bar-fill--inst" data-pct="<?= min(100, ($inst / $max_stat_ref) * 100) ?>"></div>
                      </div>
                  </div>
                  <div class="rpg-pj-stat-row">
                      <div class="rpg-pj-stat-label">
                          <span><i class="fas fa-fire"></i> ESPÍRITU</span>
                          <span class="rpg-pj-stat-text"><?= $esp ?> / <?= $max_stat_ref ?></span>
                      </div>
                      <div class="rpg-pj-stat-bar-bg">
                          <div class="rpg-pj-stat-bar-fill rpg-pj-stat-bar-fill--esp" data-pct="<?= min(100, ($esp / $max_stat_ref) * 100) ?>"></div>
                      </div>
                  </div>
                  <div class="rpg-pj-stat-row">
                      <div class="rpg-pj-stat-label">
                          <span><i class="fas fa-brain"></i> INTELECTO</span>
                          <span class="rpg-pj-stat-text"><?= $int ?> / <?= $max_stat_ref ?></span>
                      </div>
                      <div class="rpg-pj-stat-bar-bg">
                          <div class="rpg-pj-stat-bar-fill rpg-pj-stat-bar-fill--int" data-pct="<?= min(100, ($int / $max_stat_ref) * 100) ?>"></div>
                      </div>
                  </div>
                  <div class="rpg-pj-stat-row">
                      <div class="rpg-pj-stat-label">
                          <span><i class="fas fa-heartbeat"></i> VITALIDAD</span>
                          <span class="rpg-pj-stat-text"><?= $vit ?> / <?= $max_stat_ref ?></span>
                      </div>
                      <div class="rpg-pj-stat-bar-bg">
                          <div class="rpg-pj-stat-bar-fill rpg-pj-stat-bar-fill--vit" data-pct="<?= min(100, ($vit / $max_stat_ref) * 100) ?>"></div>
                      </div>
                  </div>
              </div>
          </div>
      </div>
