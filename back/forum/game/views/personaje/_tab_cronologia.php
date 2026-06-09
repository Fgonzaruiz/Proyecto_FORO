          <div id="pjTab_cronologia" class="pj-preview-tab-content">
              <div class="pj-tab-section-header">
                  <h3 class="pj-tab-section-title">Diario de Aventuras</h3>
                  <?php if ($can_edit): ?>
                      <div class="pj-tab-section-actions">
                          <button class="rpg-system-tab-btn rpg-system-tab-btn--compact" onclick="openNewDiario()"><i class="fas fa-plus"></i> Añadir</button>
                          <button class="rpg-system-tab-btn rpg-system-tab-btn--compact" onclick="openEditDiario()"><i class="fas fa-list"></i> Editar</button>
                      </div>
                  <?php endif; ?>
              </div>
              
              <?php
               $cat_list = ['Pasado'=>'#8b5cf6','Presente'=>'#10b981','Mision'=>'#f59e0b','Evento'=>'#3b82f6','Trama'=>'#ef4444','Fic'=>'#ec4899','Off_Rol'=>'#6b7280'];
               $cat_names = ['Pasado'=>'Pasado','Presente'=>'Presente','Mision'=>'Misión','Evento'=>'Evento','Trama'=>'Trama','Fic'=>'Fic','Off_Rol'=>'Off Rol'];
               $cat_counts = [];
              foreach ($cat_list as $cn => $cc) $cat_counts[$cn] = 0;
              foreach ($char['cronologia']['diario'] as $entry) {
                  $ec = $entry['category'] ?? 'Presente';
                  if (isset($cat_counts[$ec])) $cat_counts[$ec]++;
              }
              ?>
              <div class="pj-cat-counter">
                  <?php foreach ($cat_list as $cn => $cc): ?>
                  <span class="pj-cat-chip" data-color="<?= $cc ?>">
                      <span class="num"><?= $cat_counts[$cn] ?></span> <?= $cat_names[$cn] ?? $cn ?>
                  </span>
                  <?php endforeach; ?>
              </div>

              <?php if (empty($char['cronologia']['diario'])): ?>
                  <p class="pj-empty-msg">No hay registros en el diario.</p>
              <?php else: ?>
                  <div class="pj-scroll-box pj-scroll-box--tall">
                      <div class="pj-timeline">
                      <?php 
                      $s_names = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
                      foreach ($char['cronologia']['diario'] as $entry): 
                          $d = $entry['day'] ?? '?';
                          $s_id = $entry['season'] ?? 0;
                          $y = $entry['year'] ?? '?';
                          $s_name = $s_names[$s_id] ?? 'Desconocida';
                          $fecha_str = "Día {$d} de {$s_name}, Año {$y}";
                          $entry_cat = $entry['category'] ?? 'Presente';
                          $cat_color = $cat_list[$entry_cat] ?? '#C62828';
                          $thread_name = $entry['thread_name'] ?? '';
                          $participants = $entry['participants'] ?? [];
                      ?>
                          <div class="pj-timeline-item-wrapper">
                              <div class="pj-timeline-item pj-timeline-item--cat" data-color="<?= $cat_color ?>">
                                  <div class="pj-timeline-date-row">
                                      <span class="pj-timeline-cat-label"><?= htmlspecialchars($cat_names[$entry_cat] ?? $entry_cat) ?></span>
                                      <span class="pj-timeline-date-sub">&bull; <?= mb_strtoupper(htmlspecialchars($fecha_str)) ?></span>
                                  </div>
                                  <?php if ($thread_name): ?>
                                      <div class="pj-timeline-thread-name"><?= htmlspecialchars($thread_name) ?></div>
                                  <?php endif; ?>
                                  <div class="pj-timeline-desc pj-timeline-desc--view"><?= htmlspecialchars($entry['desc'] ?? '') ?></div>
                                  <?php if (!empty($participants)): ?>
                                      <div class="pj-timeline-participants">
                                          <?php foreach ($participants as $pj): ?>
                                              <span class="pj-participant-chip"><i class="fas fa-user"></i> <?= htmlspecialchars($pj['name'] ?? '?') ?></span>
                                          <?php endforeach; ?>
                                      </div>
                                  <?php endif; ?>
                                  <?php if (!empty($entry['link'])): ?>
                                      <a href="<?= htmlspecialchars((string)($entry['link'] ?? '')) ?>" class="pj-timeline-link" target="_blank"><i class="fas fa-book-open"></i> Leer Tema</a>
                                  <?php endif; ?>
                              </div>
                          </div>
                      <?php endforeach; ?>
                      </div>
                  </div>
              <?php endif; ?>

              <div class="pj-tab-section-header pj-tab-section-header--spaced">
                  <h3 class="pj-tab-section-title">Red de Contactos</h3>
                  <?php if ($can_edit): ?>
                      <div class="pj-tab-section-actions">
                          <button class="rpg-system-tab-btn rpg-system-tab-btn--compact" onclick="openNewRelacion()"><i class="fas fa-plus"></i> Añadir Contacto</button>
                          <button class="rpg-system-tab-btn rpg-system-tab-btn--compact" onclick="openEditRelacion()"><i class="fas fa-cog"></i> Editar</button>
                          <button class="rpg-system-tab-btn rpg-system-tab-btn--compact" onclick="openNewGroup()"><i class="fas fa-users"></i> Crear Grupo</button>
                      </div>
                  <?php endif; ?>
              </div>

              <?php if (empty($char['cronologia']['relaciones'])): ?>
                  <p class="pj-empty-msg">No hay relaciones registradas.</p>
              <?php else: ?>
                  <div class="pj-network-wrap">
                      <div class="pj-view-toggles">
                          <button id="btn-view-graph" type="button" class="pj-view-toggle is-active" onclick="pjShowNetworkView('graph')" title="Mapa de Relaciones"><i class="fas fa-project-diagram"></i></button>
                          <button id="btn-view-list" type="button" class="pj-view-toggle" onclick="pjShowNetworkView('list')" title="Vista Lista"><i class="fas fa-th-large"></i></button>
                      </div>
                      
                      <div id="pj-view-graph" class="pj-view-graph">
                          <div id="pj-network-container" class="pj-network-container"></div>
                          <script src="../../jscripts/game/game_network.js?v=<?= time() ?>"></script>
                      </div>
                      
                      <div id="pj-view-list" class="pj-view-list">
                          <div class="pj-scroll-box pj-scroll-box--network">
                              <div class="pj-relations-grid">
                              <?php foreach ($char['cronologia']['relaciones'] as $rel):
                                  $tags = $rel['tags'] ?? [];
                                  if (empty($tags) && !empty($rel['relation'])) $tags = [$rel['relation']];
                                  if (!is_array($tags)) $tags = [$tags];
                              ?>
                                  <?php if (!empty($rel['pj_id'])): ?>
                                      <a href="personaje.php?pj=<?= htmlspecialchars((string)$rel['pj_id']) ?>" target="_blank" class="pj-relation-card-link">
                                  <?php endif; ?>
                                  <div class="pj-relation-card">
                                      <?php if (!empty($rel['is_npc'])): ?>
                                          <div class="pj-relation-npc-badge">NPC</div>
                                      <?php endif; ?>
                                      <img src="<?= htmlspecialchars($rel['image'] ?: 'https://placehold.co/70x70') ?>" class="pj-relation-img" alt="">
                                      <div class="pj-relation-name"><?= htmlspecialchars($rel['name']) ?></div>
                                      <div class="pj-relation-tag-wrap">
                                          <?php foreach ($tags as $t): $t = trim($t); if (!$t) continue; $c = $tag_colors[$t] ?? '#C62828'; ?>
                                          <span class="pj-relation-tag" data-color="<?= $c ?>"><?= htmlspecialchars($t) ?></span>
                                          <?php endforeach; ?>
                                      </div>
                                      <?php if (!empty($rel['desc'])): ?>
                                          <div class="pj-relation-desc"><?= htmlspecialchars($rel['desc']) ?></div>
                                      <?php endif; ?>
                                  </div>
                                  <?php if (!empty($rel['pj_id'])): ?>
                                      </a>
                                  <?php endif; ?>
                              <?php endforeach; ?>
                              </div>
                          </div>
                      </div>
                  </div>
              <?php endif; ?>
          </div>
