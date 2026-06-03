          <div id="pjTab_cronologia" class="pj-preview-tab-content">
              <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:10px; margin-bottom:20px;">
                  <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin:0;">Diario de Aventuras</h3>
                  <?php if ($can_edit): ?>
                      <div style="display:flex; gap:8px;">
                          <button class="pj-btn-add" onclick="openNewDiario()"><i class="fas fa-plus"></i> Añadir</button>
                          <button class="pj-btn-add" onclick="openEditDiario()"><i class="fas fa-list"></i> Editar</button>
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
                  <span class="pj-cat-chip" style="color:<?= $cc ?>;background:<?= $cc ?>22;">
                      <span class="num"><?= $cat_counts[$cn] ?></span> <?= $cat_names[$cn] ?? $cn ?>
                  </span>
                  <?php endforeach; ?>
              </div>

              <?php if (empty($char['cronologia']['diario'])): ?>
                  <p style="color:var(--text-muted); font-size:14px; text-align:center; margin-bottom:40px;">No hay registros en el diario.</p>
              <?php else: ?>
                  <div class="pj-scroll-box" style="height: 350px;">
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
                          <div class="pj-timeline-item-wrapper" style="margin-bottom:15px; border: 1px dashed var(--border-color); padding: 15px; border-radius: 6px; background: var(--bg-surface);">
                              <div class="pj-timeline-item" style="border-left: 4px solid <?= $cat_color ?>; padding-left: 15px;">
                                  <div class="pj-timeline-date" style="display:flex;align-items:center;gap:10px; color: <?= $cat_color ?>;">
                                      <span style="text-transform:uppercase; letter-spacing:1px; font-size:11px; font-weight:800;"><?= htmlspecialchars($cat_names[$entry_cat] ?? $entry_cat) ?></span>
                                      <span style="font-size:12px; font-weight:600; opacity:0.7;">&bull; <?= mb_strtoupper(htmlspecialchars($fecha_str)) ?></span>
                                  </div>
                                  <?php if ($thread_name): ?>
                                      <div style="margin-top:10px; font-size:14px; font-weight:700; color:var(--accent-indigo);"><?= htmlspecialchars($thread_name) ?></div>
                                  <?php endif; ?>
                                  <div class="pj-timeline-desc" style="margin-top:8px; font-size:14px; line-height:1.6; color:var(--text-primary); font-style:italic; white-space:pre-wrap;"><?= htmlspecialchars($entry['desc'] ?? '') ?></div>
                                  <?php if (!empty($participants)): ?>
                                      <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:6px;">
                                          <?php foreach ($participants as $pj): ?>
                                              <span style="font-size:11px; font-weight:600; color:var(--text-primary); background:var(--bg-main); padding:4px 10px; border-radius:12px; border:1px solid var(--border-color); display:inline-flex; align-items:center; gap:5px;"><i class="fas fa-user" style="color:var(--text-muted);"></i> <?= htmlspecialchars($pj['name'] ?? '?') ?></span>
                                          <?php endforeach; ?>
                                      </div>
                                  <?php endif; ?>
                                  <?php if (!empty($entry['link'])): ?>
                                      <a href="<?= htmlspecialchars((string)($entry['link'] ?? '')) ?>" class="pj-timeline-link" target="_blank" style="margin-top:15px; display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--accent-indigo); font-weight:800; text-decoration:none; background:#f4f1e1; padding:8px 16px; border-radius:20px; border:1px solid #e5dfc5; text-transform:uppercase; letter-spacing:0.5px;"><i class="fas fa-book-open"></i> Leer Tema</a>
                                  <?php endif; ?>
                              </div>
                          </div>
                      <?php endforeach; ?>
                      </div>
                  </div>
              <?php endif; ?>

              <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:10px; margin-top:40px; margin-bottom:20px;">
                  <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin:0;">Red de Contactos</h3>
                  <?php if ($can_edit): ?>
                      <div style="display:flex; gap:8px;">
                          <button class="pj-btn-add" onclick="openNewRelacion()"><i class="fas fa-plus"></i> Añadir Contacto</button>
                          <button class="pj-btn-add" onclick="openEditRelacion()"><i class="fas fa-cog"></i> Editar</button>
                          <button class="pj-btn-add" onclick="openNewGroup()"><i class="fas fa-users"></i> Crear Grupo</button>
                      </div>
                  <?php endif; ?>
              </div>

              <!-- tags predefined globally -->
              <?php if (empty($char['cronologia']['relaciones'])): ?>
                  <p style="color:var(--text-muted); font-size:14px; text-align:center;">No hay relaciones registradas.</p>
              <?php else: ?>
                  <div style="position:relative;">
                      <!-- Controles integrados flotantes en la esquina superior derecha -->
                      <div style="position:absolute; top:15px; right:15px; z-index:10; display:flex; gap:15px;">
                          <button id="btn-view-graph" style="background:none; border:none; color:var(--text-primary); font-size:22px; cursor:pointer; opacity:1; transition:opacity 0.2s;" onclick="document.getElementById('pj-view-graph').style.display='block'; document.getElementById('pj-view-list').style.display='none'; this.style.opacity=1; document.getElementById('btn-view-list').style.opacity=0.4;" title="Mapa de Relaciones"><i class="fas fa-project-diagram"></i></button>
                          <button id="btn-view-list" style="background:none; border:none; color:var(--text-primary); font-size:22px; cursor:pointer; opacity:0.4; transition:opacity 0.2s;" onclick="document.getElementById('pj-view-graph').style.display='none'; document.getElementById('pj-view-list').style.display='block'; this.style.opacity=1; document.getElementById('btn-view-graph').style.opacity=0.4;" title="Vista Lista"><i class="fas fa-th-large"></i></button>
                      </div>
                      
                      <div id="pj-view-graph">
                          <div id="pj-network-container" style="width: 100%; height: 500px; background: radial-gradient(circle, var(--bg-surface) 0%, var(--bg-main) 100%); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; position: relative;"></div>
                          <script src="../../jscripts/game/game_network.js?v=<?= time() ?>"></script>
                      </div>
                      
                      <div id="pj-view-list" style="display:none; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-md); padding-top:40px;">
                          <div class="pj-scroll-box" style="height: 460px; border:none; background:transparent;">
                              <div class="pj-relations-grid">
                              <?php foreach ($char['cronologia']['relaciones'] as $rel):
                                  $tags = $rel['tags'] ?? [];
                                  if (empty($tags) && !empty($rel['relation'])) $tags = [$rel['relation']];
                                  if (!is_array($tags)) $tags = [$tags];
                              ?>
                                  <?php if (!empty($rel['pj_id'])): ?>
                                      <a href="personaje.php?pj=<?= htmlspecialchars((string)$rel['pj_id']) ?>" target="_blank" style="text-decoration:none; color:inherit;">
                                  <?php endif; ?>
                                  <div class="pj-relation-card" style="position:relative;">
                                      <?php if (!empty($rel['is_npc'])): ?>
                                          <div style="position:absolute; top:-5px; right:-5px; background:#f59e0b; color:#000; font-size:9px; font-weight:800; padding:2px 6px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.5); z-index:2;">NPC</div>
                                      <?php endif; ?>
                                      <img src="<?= htmlspecialchars($rel['image'] ?: 'https://placehold.co/70x70') ?>" class="pj-relation-img">
                                      <div class="pj-relation-name"><?= htmlspecialchars($rel['name']) ?></div>
                                      <div class="pj-relation-tag-wrap">
                                          <?php foreach ($tags as $t): $t = trim($t); if (!$t) continue; $c = $tag_colors[$t] ?? '#C62828'; ?>
                                          <span class="pj-relation-tag" style="color:<?= $c ?>; background:<?= $c ?>22;"><?= htmlspecialchars($t) ?></span>
                                          <?php endforeach; ?>
                                      </div>
                                      <?php if (!empty($rel['desc'])): ?>
                                          <div style="font-size:11px; color:var(--text-muted); margin-top:8px; line-height:1.4;"><?= htmlspecialchars($rel['desc']) ?></div>
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
