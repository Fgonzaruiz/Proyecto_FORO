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
                  <?php
                  $factionRank = (string)($char['faction_rank'] ?? $char['rango'] ?? 'Sin Rango');
                  $globalRank = (string)($pj_progression['rank'] ?? 'D');
                  $globalRankClass = \Game\Shared\StatScale::globalRankCssClass($globalRank);
                  ?>
                  <span class="pj-badge pj-badge--rank"><i class="fas fa-medal"></i> <?= htmlspecialchars($factionRank) ?></span>
                  <span class="pj-badge pj-badge--global-rank <?= htmlspecialchars($globalRankClass) ?>" title="Rango global (suma de rangos de atributos)">
                      <i class="fas fa-layer-group"></i> <?= htmlspecialchars($globalRank) ?>
                  </span>
                  <?php if ($char['is_staff']): ?>
                    <span class="pj-badge pj-badge--staff"><i class="fas fa-star"></i> Staff</span>
                  <?php endif; ?>
              </div>

              <?php if (!empty($char['recompensa']) && $char['recompensa'] !== '0' && $char['recompensa'] !== '0 Berries'): ?>
                  <div class="pj-badge-wanted">
                      <i class="fas fa-skull-crossbones"></i> WANTED: <?= htmlspecialchars($char['recompensa']) ?>
                  </div>
              <?php endif; ?>
              
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
                          <a href="<?= htmlspecialchars($bburl) ?>/game/public/crear_personaje.php?pj_id=<?= (int)$char['id'] ?>" class="rpg-system-tab-btn rpg-staff-btn-full">
                              <i class="fas fa-edit"></i> Editar Ficha Completa
                          </a>
                      <?php else: ?>
                          <a href="<?= htmlspecialchars($bburl) ?>/game/public/mis_personajes.php?edit_pj=<?= (int)$char['id'] ?>" class="rpg-system-tab-btn rpg-staff-btn-full">
                              <i class="fas fa-user-edit"></i> Editar Avatar / Firma
                          </a>
                      <?php endif; ?>
                  </div>
              <?php endif; ?>
              
              <?php
              $sidebar_disciplinas = $char['disciplinas'] ?? game_disciplina_list_for_character((int)$char['id']);
              $sidebar_oficios = $char['oficios'] ?? game_oficio_list_for_character((int)$char['id']);
              if ($sidebar_disciplinas === []) {
                  $legacyDisc = trim((string)($char['disciplina'] ?? $char['arquetipo'] ?? ''));
                  if ($legacyDisc !== '' && strcasecmp($legacyDisc, 'Desconocido') !== 0 && strcasecmp($legacyDisc, 'Ninguna') !== 0) {
                      $sidebar_disciplinas = [[
                          'name' => $legacyDisc,
                          'rank_label' => 'I',
                          'icon' => 'fa-crosshairs',
                      ]];
                  }
              }
              if ($sidebar_oficios === [] && !empty($char['job_name']) && $char['job_name'] !== 'Ninguno') {
                  $sidebar_oficios = [[
                      'name' => $char['job_name'],
                      'rank_label' => 'I',
                      'icon' => 'fa-briefcase',
                  ]];
              }
              ?>
              <div class="pj-sidebar-info pj-sidebar-info--skills">
                  <div class="pj-sidebar-info-section">
                      <div class="pj-sidebar-info-heading"><i class="fas fa-crosshairs"></i> Disciplinas</div>
                      <div class="pj-sidebar-skills-list">
                          <?php if ($sidebar_disciplinas === []): ?>
                              <p class="pj-sidebar-skills-empty">Sin disciplinas</p>
                          <?php else: ?>
                              <?php foreach ($sidebar_disciplinas as $disc): ?>
                              <div class="pj-skill-row pj-skill-row--disc">
                                  <span class="pj-skill-row__name"><?= htmlspecialchars((string)($disc['name'] ?? '')) ?></span>
                                  <span class="pj-skill-row__grade"><?= htmlspecialchars((string)($disc['rank_label'] ?? 'I')) ?></span>
                              </div>
                              <?php endforeach; ?>
                          <?php endif; ?>
                      </div>
                  </div>
                  <div class="pj-sidebar-info-section pj-sidebar-info-section--split">
                      <div class="pj-sidebar-info-heading"><i class="fas fa-anchor"></i> Oficios</div>
                      <div class="pj-sidebar-skills-list">
                          <?php if ($sidebar_oficios === []): ?>
                              <p class="pj-sidebar-skills-empty">Ninguno</p>
                          <?php else: ?>
                              <?php foreach ($sidebar_oficios as $of): ?>
                              <div class="pj-skill-row pj-skill-row--oficio">
                                  <span class="pj-skill-row__name"><?= htmlspecialchars((string)($of['name'] ?? '')) ?></span>
                                  <span class="pj-skill-row__grade"><?= htmlspecialchars((string)($of['rank_label'] ?? 'I')) ?></span>
                              </div>
                              <?php endforeach; ?>
                          <?php endif; ?>
                      </div>
                  </div>
              </div>
              
              <?php
              $ctx = $char['stat_context'] ?? game_build_stat_context($char['stats'], (string)($char['race_name'] ?? ''));
              $vitals = game_compute_pv_pe_from_context($ctx['values'], $ctx['trained']);
              $pv = $vitals['max_pv'];
              $pe = $vitals['max_pe'];
              $statMeta = [
                  'fue' => ['FUERZA', 'fa-dumbbell'],
                  'res' => ['RESISTENCIA', 'fa-shield-alt'],
                  'agi' => ['AGILIDAD', 'fa-running'],
                  'des' => ['DESTREZA', 'fa-bullseye'],
                  'int' => ['INTELECTO', 'fa-brain'],
                  'inst' => ['INSTINTO', 'fa-eye'],
                  'esp' => ['ESPÍRITU', 'fa-fire'],
              ];
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

              <h3 class="pj-stats-heading">Atributos</h3>
              <div class="rpg-post-pj-stats">
                  <?php foreach ($statMeta as $key => $meta):
                      $trained = (int)($ctx['trained'][$key] ?? 1);
                      $effLabel = (string)($ctx['display'][$key] ?? 'D');
                      $effRank = (int)($ctx['effective_ranks'][$key] ?? 1);
                      $rankClass = \Game\Shared\StatScale::rankDisplayCssClass($effRank);
                      $hasRacial = (int)(\Game\Shared\StatScale::getRacialBonuses((string)($char['race_name'] ?? ''))[$key] ?? 0) !== 0;
                  ?>
                  <div class="rpg-pj-stat-row rpg-pj-stat-row--rank<?= $hasRacial ? ' rpg-pj-stat-row--racial' : '' ?>">
                      <div class="rpg-pj-stat-label">
                          <span><i class="fas <?= $meta[1] ?>"></i> <?= $meta[0] ?></span>
                          <span class="rpg-stat-rank <?= htmlspecialchars($rankClass) ?>"><?= htmlspecialchars($effLabel) ?></span>
                      </div>
                      <div class="rpg-stat-rank-track">
                          <?php for ($seg = 1; $seg <= 6; $seg++): ?>
                          <span class="rpg-stat-rank-segment<?= $seg <= $trained ? ' rpg-stat-rank-segment--filled rpg-stat-rank-segment--' . $key : '' ?>"></span>
                          <?php endfor; ?>
                      </div>
                  </div>
                  <?php endforeach; ?>
              </div>
          </div>
      </div>
