          <?php
          $catalog_cards = [];
          if ($char) {
              $cat_q = $db->query("
                  SELECT id, name, card_type, rank 
                  FROM {$prefix}game_cards 
                  WHERE id NOT IN (
                      SELECT card_id FROM {$prefix}game_character_cards WHERE character_id = {$char['id']}
                  )
                  ORDER BY name ASC
              ");
              while ($c = $db->fetch_array($cat_q)) {
                  $catalog_cards[] = $c;
              }
          }
          ?>
          <div id="pjTab_gestion" class="pj-preview-tab-content">
              <style>
                  .rpg-pp-display { background: linear-gradient(135deg, rgba(198,40,40,0.1), rgba(74,20,140,0.06)); border: 1px solid rgba(198,40,40,0.2); border-radius: 10px; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
                  .rpg-pp-display h3 { margin: 0; font-size: 14px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
                  .rpg-pp-val { font-size: 24px; font-weight: 900; color: var(--accent-indigo); text-shadow: 0 0 10px rgba(198,40,40,0.3); font-family: var(--font-heading); }
                  
                  .rpg-attr-buy-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
                  .rpg-attr-buy-card { background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 10px; padding: 15px 18px; display: flex; flex-direction: column; gap: 12px; transition: border-color 0.2s; position: relative; }
                  .rpg-attr-buy-card:hover { border-color: rgba(198,40,40,0.3); }
                  .rpg-attr-buy-header { display: flex; align-items: center; gap: 10px; }
                  .rpg-attr-buy-icon { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
                  .rpg-attr-buy-name { font-weight: 800; font-size: 12px; color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.5px; font-family: var(--font-heading); }
                  .rpg-attr-buy-value { font-size: 15px; font-weight: 900; color: var(--text-primary); margin-left: auto; }
                  .rpg-attr-buy-actions { display: flex; align-items: center; justify-content: space-between; gap: 10px; border-top: 1px solid var(--border-color); padding-top: 10px; }
                  .rpg-attr-buy-cost { font-size: 11px; color: var(--text-muted); font-weight: 700; }
                  .rpg-attr-buy-cost span { color: var(--accent-indigo); }
                  .rpg-attr-buy-btn { background: linear-gradient(135deg, var(--accent-indigo), var(--accent-purple)); border: none; border-radius: 6px; color: #fff; padding: 8px 15px; font-weight: 800; font-size: 11px; text-transform: uppercase; cursor: pointer; transition: opacity 0.2s; display: inline-flex; align-items: center; gap: 6px; }
                  .rpg-attr-buy-btn:hover { opacity: 0.9; }

                  .rpg-chat-container { display: flex; flex-direction: column; height: 350px; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; }
                  .rpg-chat-messages { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 10px; }
                  .rpg-chat-bubble { padding: 10px 14px; border-radius: 8px; max-width: 85%; font-size: 13px; line-height: 1.5; word-break: break-word; position: relative; }
                  .rpg-chat-bubble.player { background: rgba(198,40,40,0.08); border: 1px solid rgba(198,40,40,0.15); align-self: flex-end; color: var(--text-primary); }
                  .rpg-chat-bubble.staff { background: rgba(74,20,140,0.08); border: 1px solid rgba(74,20,140,0.15); align-self: flex-start; color: var(--text-primary); }
                  .rpg-chat-bubble-meta { font-size: 9px; color: var(--text-muted); margin-bottom: 4px; display: flex; justify-content: space-between; font-weight: 700; }
                  .rpg-chat-input-bar { display: flex; border-top: 1px solid var(--border-color); background: var(--bg-surface); }
                  .rpg-chat-input { flex: 1; border: none; background: transparent; color: var(--text-primary); padding: 12px 15px; font-size: 13px; outline: none; }
                  .rpg-chat-send { background: var(--accent-indigo); color: #fff; border: none; padding: 0 20px; font-weight: 800; font-size: 13px; cursor: pointer; }

                  .rpg-req-split { display: flex; gap: 20px; min-height: 480px; }
                  .rpg-req-list { width: 260px; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; overflow-y: auto; max-height: 480px; flex-shrink: 0; }
                  .rpg-req-item { padding: 12px 15px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: background 0.2s; }
                  .rpg-req-item:hover { background: rgba(255,255,255,0.02); }
                  .rpg-req-item.active { background: rgba(198,40,40,0.08); border-left: 3px solid var(--accent-indigo); }
                  .rpg-req-detail { flex: 1; display: flex; flex-direction: column; gap: 15px; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; }

                  .rpg-card-preview-mini { width: 220px; background: var(--bg-card); border: 2px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: var(--shadow-card); font-size: 12px; flex-shrink: 0; }

                  /* Premium Dashboard Grid & Card Styles */
                  .rpg-gestion-panel { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 25px; }
                  .rpg-gestion-dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-top: 15px; }
                  .rpg-gestion-card {
                      background: var(--bg-main);
                      border: 1px solid var(--border-color);
                      border-radius: 12px;
                      padding: 24px;
                      display: flex;
                      flex-direction: column;
                      gap: 15px;
                      cursor: pointer;
                      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                      position: relative;
                      overflow: hidden;
                      box-shadow: var(--shadow-card);
                      text-decoration: none !important;
                  }
                  .rpg-gestion-card::before {
                      content: '';
                      position: absolute;
                      top: 0; left: 0; width: 100%; height: 100%;
                      background: linear-gradient(135deg, rgba(198,40,40,0.03), rgba(74,20,140,0.03));
                      opacity: 0;
                      transition: opacity 0.3s;
                  }
                  .rpg-gestion-card:hover {
                      transform: translateY(-4px);
                      border-color: var(--accent-indigo);
                      box-shadow: 0 8px 25px rgba(198,40,40,0.12);
                  }
                  .rpg-gestion-card:hover::before { opacity: 1; }
                  .rpg-gestion-card-icon {
                      width: 46px;
                      height: 46px;
                      border-radius: 12px;
                      display: flex;
                      align-items: center;
                      justify-content: center;
                      font-size: 18px;
                      color: #fff;
                      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                      transition: transform 0.3s;
                  }
                  .rpg-gestion-card:hover .rpg-gestion-card-icon { transform: scale(1.1); }
                  .rpg-gestion-card-body { display: flex; flex-direction: column; gap: 6px; }
                  .rpg-gestion-card-body h3 { margin: 0; font-size: 15px; font-weight: 800; color: var(--text-primary); font-family: var(--font-heading); letter-spacing: 0.5px; }
                  .rpg-gestion-card-body p { margin: 0; font-size: 12px; color: var(--text-muted); line-height: 1.5; }
                  .rpg-gestion-card-footer {
                      margin-top: auto;
                      display: flex;
                      justify-content: space-between;
                      align-items: center;
                      font-size: 10px;
                      font-weight: 800;
                      text-transform: uppercase;
                      letter-spacing: 0.5px;
                      border-top: 1px solid var(--border-color);
                      padding-top: 12px;
                  }
                  .rpg-gestion-card-tag { color: var(--accent-indigo); }
                  .rpg-gestion-card-badge { background: var(--accent-rose); color: #fff; padding: 2px 8px; border-radius: 20px; font-size: 9px; font-weight: 800; }

                  /* Back Button Styles */
                  .rpg-back-btn {
                      background: rgba(255, 255, 255, 0.02);
                      border: 1px solid var(--border-color);
                      color: var(--text-secondary);
                      padding: 8px 16px;
                      border-radius: 8px;
                      font-family: var(--font-heading);
                      font-weight: 800;
                      font-size: 11px;
                      text-transform: uppercase;
                      letter-spacing: 1px;
                      cursor: pointer;
                      display: inline-flex;
                      align-items: center;
                      gap: 8px;
                      transition: all 0.2s;
                      margin-bottom: 20px;
                  }
                  .rpg-back-btn:hover {
                      background: rgba(198, 40, 40, 0.06);
                      border-color: var(--accent-indigo);
                      color: var(--text-primary);
                  }
              </style>

              <div class="rpg-gestion-panel">
                  <!-- DASHBOARD LANDING VIEW -->
                  <div id="gestion_dashboard" style="display:block;">
                      <div class="rpg-pp-display" style="flex-wrap:wrap; gap:16px;">
                          <div style="flex:1; min-width:200px;">
                              <h3>Panel de Gestión del Personaje</h3>
                              <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">Nivel <?= (int)$pj_progression['nivel'] ?> &bull; Cada 20 puntos de atributo comprados suben 1 nivel (máx. 1/semana). Si ya subiste esta semana, solo puedes comprar hasta quedar a 1 del siguiente umbral.</div>
                          </div>
                          <div style="display:flex; flex-wrap:wrap; gap:20px; align-items:center;">
                              <div class="rpg-pp-val" style="font-size:18px;"><i class="fas fa-level-up-alt"></i> Nv. <span id="val_pj_nivel"><?= (int)$pj_progression['nivel'] ?></span></div>
                              <div class="rpg-pp-val"><i class="fas fa-gem"></i> <span id="val_available_pp"><?= $pp_available ?></span> PP</div>
                          </div>
                      </div>

                      <div class="rpg-gestion-dashboard-grid">
                          <!-- CARD 1: ATRIBUTOS -->
                          <div class="rpg-gestion-card" onclick="switchGestionSubtab('atributos')">
                              <div class="rpg-gestion-card-icon" style="background: linear-gradient(135deg, var(--accent-indigo), var(--accent-blue));">
                                  <i class="fas fa-chart-line"></i>
                              </div>
                              <div class="rpg-gestion-card-body">
                                  <h3>Comprar Atributos</h3>
                                  <p>Mejora tus estadísticas base (Fuerza, Agilidad, Espíritu, etc.) canjeando tus PP acumulados.</p>
                              </div>
                              <div class="rpg-gestion-card-footer">
                                  <span class="rpg-gestion-card-tag"><?= (int)$pj_progression['stat_cost'] ?> PP / Punto</span>
                                  <i class="fas fa-chevron-right" style="color: var(--text-muted);"></i>
                              </div>
                          </div>

                          <!-- CARD 2: CREACI├ôN DE CARTA -->
                          <div class="rpg-gestion-card" onclick="switchGestionSubtab('crear_carta')">
                              <div class="rpg-gestion-card-icon" style="background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));">
                                  <i class="fas fa-wand-magic-sparkles"></i>
                              </div>
                              <div class="rpg-gestion-card-body">
                                  <h3>Proponer Carta</h3>
                                  <p>Envía una propuesta de carta personalizada (t├®cnica, equipo, etc.) para moderar y equilibrar junto al staff.</p>
                              </div>
                              <div class="rpg-gestion-card-footer">
                                  <span class="rpg-gestion-card-tag">Bajo revisión</span>
                                  <i class="fas fa-chevron-right" style="color: var(--text-muted);"></i>
                              </div>
                          </div>

                          <!-- CARD 3: CARTA CATÁLOGO -->
                          <div class="rpg-gestion-card" onclick="switchGestionSubtab('solicitar_catalogo')">
                              <div class="rpg-gestion-card-icon" style="background: linear-gradient(135deg, var(--accent-blue), var(--accent-teal));">
                                  <i class="fas fa-clone"></i>
                              </div>
                              <div class="rpg-gestion-card-body">
                                  <h3>Carta de Catálogo</h3>
                                  <p>Solicita que se te añada una carta oficial existente en el catálogo del foro (misiones, eventos, etc.).</p>
                              </div>
                              <div class="rpg-gestion-card-footer">
                                  <span class="rpg-gestion-card-tag">Catálogo oficial</span>
                                  <i class="fas fa-chevron-right" style="color: var(--text-muted);"></i>
                              </div>
                          </div>

                          <!-- CARD 4: HISTORIAL Y CONVERSACIONES -->
                          <div class="rpg-gestion-card" onclick="switchGestionSubtab('historial')">
                              <div class="rpg-gestion-card-icon" style="background: linear-gradient(135deg, var(--accent-rose), var(--accent-orange));">
                                  <i class="fas fa-clipboard-list"></i>
                              </div>
                              <div class="rpg-gestion-card-body">
                                  <h3>Mis Solicitudes</h3>
                                  <p>Revisa tus solicitudes activas, responde en el chat de discusión y confirma tu conformidad.</p>
                              </div>
                              <div class="rpg-gestion-card-footer">
                                  <span class="rpg-gestion-card-tag">Mensajes e historial</span>
                                  <span id="dashboard-requests-badge" class="rpg-gestion-card-badge" style="display:none;">0 activa(s)</span>
                              </div>
                          </div>
                      </div>
                  </div>

                  <!-- SUBTAB: ATRIBUTOS -->
                  <div id="gestion_subtab_atributos" class="gestion-subtab-content" style="display:none;">
                      <button class="rpg-back-btn" onclick="showGestionDashboard()">
                          <i class="fas fa-arrow-left"></i> Volver a Gestión
                      </button>

                      <div class="rpg-pp-display" style="flex-wrap:wrap; gap:16px;">
                          <div style="flex:1; min-width:220px;">
                              <h3>Progresión y atributos</h3>
                              <div style="font-size:12px; color:var(--text-muted); margin-top:4px; line-height:1.5;">
                                  <strong id="val_pj_nivel_sub">Nivel <?= (int)$pj_progression['nivel'] ?></strong>
                                  &bull; Precio actual: <strong><?= (int)$pj_progression['stat_cost'] ?> PP</strong> por punto
                                  <br>
                                  Progreso hacia nivel <?= (int)$pj_progression['nivel'] + 1 ?>: <strong><?= (int)$pj_progression['progress_in_tier'] ?>/<?= (int)$pj_progression['stat_points_per_level'] ?></strong> puntos de atributo comprados en esta franja
                                  (<?= (int)$pj_progression['stat_points_purchased'] ?> comprados en total)
                                  <?php if (!$pj_progression['can_level_up_this_week'] && $pj_progression['max_stat_points_buyable'] !== null): ?>
                                  <br><span style="color:#f59e0b; font-weight:700;">Tope semanal activo: puedes comprar como máximo <?= (int)$pj_progression['max_stat_points_buyable'] ?> punto(s) más hasta el <?= !empty($pj_progression['next_level_available_iso']) ? htmlspecialchars(date('d/m/Y', strtotime($pj_progression['next_level_available_iso']))) : 'próximo desbloqueo' ?>.</span>
                                  <?php endif; ?>
                                  <?php if ((int)$pj_progression['pp_linaje'] > 0): ?>
                                  <br><span style="opacity:0.85;">Tienes <?= (int)$pj_progression['pp_linaje'] ?> PP de sobrante de linaje (se gastan primero al comprar).</span>
                                  <?php endif; ?>
                              </div>
                              <div id="pj_level_pending_box" style="margin-top:12px; <?= ((int)$pj_progression['pending_levels'] > 0) ? '' : 'display:none;' ?>">
                                  <div style="font-size:12px; color:var(--accent-amber, #f59e0b); font-weight:700;">
                                      <i class="fas fa-arrow-up"></i> <span id="val_pending_levels"><?= (int)$pj_progression['pending_levels'] ?></span> subida(s) de nivel pendiente(s)
                                  </div>
                                  <button type="button" id="btn_claim_level" class="rpg-attr-buy-btn" style="margin-top:8px; <?= ($pj_progression['pending_levels'] > 0 && $pj_progression['can_level_up_this_week']) ? '' : 'display:none;' ?>" onclick="claimPendingLevel()">
                                      <i class="fas fa-level-up-alt"></i> Aplicar subida de nivel
                                  </button>
                                  <div id="pj_level_cooldown_msg" style="font-size:11px; color:var(--text-muted); margin-top:6px; <?= ($pj_progression['pending_levels'] > 0 && !$pj_progression['can_level_up_this_week']) ? '' : 'display:none;' ?>">
                                      <?php if (!empty($pj_progression['next_level_available_iso'])): ?>
                                      Próxima subida disponible: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($pj_progression['next_level_available_iso']))) ?>
                                      <?php endif; ?>
                                  </div>
                              </div>
                          </div>
                          <div class="rpg-pp-val"><i class="fas fa-gem"></i> <span id="val_available_pp_sub"><?= $pp_available ?></span> PP</div>
                      </div>

                      <?php if ($char['status'] !== 'aprobada'): ?>
                          <div style="padding:40px; text-align:center; color:var(--text-muted); background:var(--bg-main); border:1px solid var(--border-color); border-radius:8px;">
                              <i class="fas fa-lock" style="font-size:28px; color:var(--accent-amber); margin-bottom:12px; display:block;"></i>
                              Tu personaje debe estar **Aprobado** por el staff para poder comprar puntos de atributos.
                          </div>
                      <?php else: ?>
                          <div class="rpg-attr-buy-grid">
                              <?php
                              $stats_labels = [
                                  'fue' => ['Fuerza', 'fa-dumbbell', 'linear-gradient(135deg, rgba(198,40,40,0.15), rgba(198,40,40,0.05))', '#C62828'],
                                  'agi' => ['Agilidad', 'fa-running', 'linear-gradient(135deg, rgba(16,185,129,0.15), rgba(16,185,129,0.05))', '#10b981'],
                                  'des' => ['Destreza', 'fa-crosshairs', 'linear-gradient(135deg, rgba(59,130,246,0.15), rgba(59,130,246,0.05))', '#3b82f6'],
                                  'inst' => ['Instinto', 'fa-compass', 'linear-gradient(135deg, rgba(6,182,212,0.15), rgba(6,182,212,0.05))', '#06b6d4'],
                                  'esp' => ['Espíritu', 'fa-fire', 'linear-gradient(135deg, rgba(236,72,153,0.15), rgba(236,72,153,0.05))', '#ec4899'],
                                  'int' => ['Intelecto', 'fa-brain', 'linear-gradient(135deg, rgba(245,158,11,0.15), rgba(245,158,11,0.05))', '#f59e0b'],
                              ];
                              foreach ($stats_labels as $key => $lbl):
                                  $curr_val = $char['stats'][$key];
                              ?>
                                  <div class="rpg-attr-buy-card">
                                      <div class="rpg-attr-buy-header">
                                          <div class="rpg-attr-buy-icon" style="background: <?= $lbl[2] ?>; color: <?= $lbl[3] ?>;">
                                              <i class="fas <?= $lbl[1] ?>"></i>
                                          </div>
                                          <div class="rpg-attr-buy-name"><?= $lbl[0] ?></div>
                                          <div class="rpg-attr-buy-value" id="val_stat_<?= $key ?>"><?= $curr_val ?></div>
                                      </div>
                                      <div class="rpg-attr-buy-actions">
                                          <div class="rpg-attr-buy-cost">Precio: <span class="pj-stat-cost-label"><?= (int)$pj_progression['stat_cost'] ?> PP</span></div>
                                          <button class="rpg-attr-buy-btn" onclick="buyStatPoint('<?= $key ?>')">
                                              <i class="fas fa-plus-circle"></i> Comprar +1
                                          </button>
                                      </div>
                                  </div>
                              <?php endforeach; ?>
                          </div>
                      <?php endif; ?>
                  </div>

                  <!-- SUBTAB: CREACI├ôN DE CARTA -->
                  <div id="gestion_subtab_crear_carta" class="gestion-subtab-content" style="display:none;">
                      <button class="rpg-back-btn" onclick="showGestionDashboard()">
                          <i class="fas fa-arrow-left"></i> Volver a Gestión
                      </button>

                      <div style="max-width:650px; margin:0 auto; background:var(--bg-main); border:1px solid var(--border-color); border-radius:12px; padding:30px; display:flex; flex-direction:column; gap:20px; box-shadow:var(--shadow-card);">
                          <h3 style="margin:0; font-size:16px; color:var(--text-primary); border-bottom:1px solid var(--border-color); padding-bottom:12px; display:flex; align-items:center; gap:10px; font-family:var(--font-heading); font-weight:800;">
                              <i class="fas fa-wand-magic-sparkles" style="color:var(--accent-purple); font-size:18px;"></i> Proponer Nueva Carta Personalizada
                          </h3>
                          <p style="font-size:12px; color:var(--text-muted); margin:0; line-height:1.6;">
                              Propón una t├®cnica, equipo, Akuma no Mi o NPC menor adaptado a tu personaje. Tras enviarla, podrás conversar con los moderadores en el chat interactivo para ajustar sus efectos.
                          </p>
                          
                          <div class="form-group">
                              <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Nombre de la Carta</label>
                              <input type="text" id="req_new_name" class="textbox" placeholder="Ej: Puñetazo Explosivo" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                            </div>
                            
                           <div class="form-group">
                               <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Tipo de Carta</label>
                               <select id="req_new_type" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                   <option value="tecnica">T├®cnica</option>
                                   <option value="equipo">Equipo</option>
                                   <option value="akuma_no_mi">Akuma no Mi</option>
                                   <option value="haki">Haki</option>
                                   <option value="npc_menor">NPC Menor</option>
                                   <option value="barco">Barco</option>
                               </select>
                           </div>

                           <!-- CAMPOS DINÁMICOS PROPUESTA JUGADOR -->
                           <div id="req_fields_akuma" style="display: none; flex-direction: column; gap: 12px; margin-top: 5px;">
                               <div class="form-group">
                                   <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Tipo de Akuma</label>
                                   <select id="req_akuma_type" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                       <option value="paramecia">Paramecia</option>
                                       <option value="logia">Logia</option>
                                       <option value="zoan">Zoan</option>
                                   </select>
                               </div>
                               <div class="form-group">
                                   <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Efectos</label>
                                   <textarea id="req_akuma_efectos" class="textbox" rows="3" placeholder="Detalla los efectos de la fruta..." style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); resize:vertical;"></textarea>
                               </div>
                               <div class="form-group">
                                   <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Limitaciones</label>
                                   <textarea id="req_akuma_limitaciones" class="textbox" rows="3" placeholder="Detalla las limitaciones..." style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); resize:vertical;"></textarea>
                               </div>
                               <div class="form-group">
                                   <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Debilidades</label>
                                   <textarea id="req_akuma_debilidades" class="textbox" rows="3" placeholder="Detalla las debilidades..." style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); resize:vertical;"></textarea>
                               </div>
                           </div>

                           <div id="req_fields_equipo" style="display: none; flex-direction: column; gap: 12px; margin-top: 5px;">
                               <div class="form-group">
                                   <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Tipo de Equipo</label>
                                   <select id="req_equipo_type" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                       <option value="arma">Arma</option>
                                       <option value="util">Útil / Consumible</option>
                                       <option value="armadura">Armadura</option>
                                   </select>
                                <div class="form-group">
                                    <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Subtipo (ej: Espada, Arco, Botiquín...)</label>
                                    <select id="req_equipo_subtipo_select" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); margin-bottom: 8px;"></select>
                                    <input type="text" id="req_equipo_subtipo" class="textbox" placeholder="Espada, botiquín, peto..." style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); display: none;">
                                </div>
                                <div id="wrapper_req_equipo_damage" style="display: flex; gap: 12px;">
                                     <div class="form-group" style="flex:1;">
                                         <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Dado de Daño</label>
                                         <select id="req_equipo_damage_dice_select" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); margin-bottom: 8px;">
                                             <option value="1d4">1d4</option>
                                             <option value="1d6">1d6</option>
                                             <option value="1d8">1d8</option>
                                             <option value="1d10">1d10</option>
                                             <option value="1d12">1d12</option>
                                             <option value="2d4">2d4</option>
                                             <option value="2d6">2d6</option>
                                             <option value="2d8">2d8</option>
                                             <option value="2d10">2d10</option>
                                             <option value="3d6">3d6</option>
                                             <option value="4d6">4d6</option>
                                             <option value="otros">Otros (Especificar)</option>
                                         </select>
                                         <input type="text" id="req_equipo_damage_dice" class="textbox" placeholder="Ej: 1d10 o 2d6..." style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); display: none;">
                                     </div>
                                     <div class="form-group" style="flex:1;">
                                         <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Atributo</label>
                                         <select id="req_equipo_damage_stat" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                             <option value="">Ninguno</option>
                                             <option value="FUE">FUE</option>
                                             <option value="AGI">AGI</option>
                                             <option value="DES">DES</option>
                                             <option value="INST">INST</option>
                                             <option value="ESP">ESP</option>
                                             <option value="INT">INT</option>
                                         </select>
                                     </div>
                                 </div>
                            </div>

                            <div id="req_fields_barco" style="display: none; flex-direction: column; gap: 12px; margin-top: 5px;">
                                <div class="form-group">
                                    <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Tipo de Barco</label>
                                    <select id="req_barco_type" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                        <option value="navio">Navío</option>
                                        <option value="carabela">Carabela</option>
                                        <option value="galera">Galera</option>
                                        <option value="fragata">Fragata</option>
                                        <option value="bergantin">Bergantín</option>
                                        <option value="acorazado">Acorazado</option>
                                        <option value="submarino">Submarino</option>
                                        <option value="balsa">Balsa</option>
                                    </select>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <div class="form-group">
                                        <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Tier</label>
                                        <input type="number" id="req_barco_tier" min="1" value="1" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                    </div>
                                    <div class="form-group">
                                        <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Vida</label>
                                        <input type="number" id="req_barco_vida" min="0" value="100" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                    </div>
                                    <div class="form-group">
                                        <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Ataque</label>
                                        <input type="number" id="req_barco_ataque" min="0" value="0" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                    </div>
                                    <div class="form-group">
                                        <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Velocidad</label>
                                        <input type="number" id="req_barco_velocidad" min="0" value="0" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                    </div>
                                    <div class="form-group" style="grid-column: span 2;">
                                        <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Resistencia</label>
                                        <input type="number" id="req_barco_resistencia" min="0" value="0" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                    </div>
                                </div>
                            </div>

                            <div id="req_fields_npc" style="display: none; flex-direction: column; gap: 12px; margin-top: 5px;">
                                <div class="form-group">
                                    <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Subtipo</label>
                                    <select id="req_npc_mascota_type" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                        <option value="npc">NPC</option>
                                        <option value="mascota">Mascota</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Vida (HP)</label>
                                    <input type="number" id="req_npc_vida" min="0" value="50" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                </div>
                                <div class="form-group" id="wrapper_req_npc_tier" style="display:none;">
                                    <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Tier de Mascota</label>
                                    <input type="number" id="req_npc_tier" min="1" value="1" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                </div>
                                <div class="form-group">
                                    <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Acciones</label>
                                    <div id="req-npc-actions-container" style="display:flex; flex-direction:column; gap:8px;"></div>
                                    <button type="button" id="btn-req-npc-add-action" class="textbox" style="width:100%; margin-top:8px; background:var(--bg-surface); border:1px dashed var(--border-color); border-radius:6px; color:var(--text-secondary); padding:8px; cursor:pointer; font-weight:700;">+ Añadir Acción</button>
                                </div>
                            </div>

                            <div id="req_fields_haki" style="display: none; flex-direction: column; gap: 12px; margin-top: 5px;">
                                 <div class="form-group">
                                     <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Tipo de Haki</label>
                                     <select id="req_haki_type" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                         <option value="busoshoku">Busoshoku (Armamiento)</option>
                                         <option value="kenbunshoku">Kenbunshoku (Observación)</option>
                                         <option value="haoshoku">Haoshoku (Conquistador / Rey)</option>
                                     </select>
                                 </div>
                                 <div class="form-group">
                                     <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Nivel de Haki</label>
                                     <select id="req_haki_level" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                         <option value="despertado">Despertado</option>
                                         <option value="basico">Básico</option>
                                         <option value="medio">Medio</option>
                                         <option value="avanzado">Avanzado</option>
                                         <option value="maestro">Maestro</option>
                                     </select>
                                 </div>
                                 <div class="form-group">
                                     <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Efecto</label>
                                     <textarea id="req_haki_efecto" class="textbox" rows="3" placeholder="Detalla el efecto de la habilidad de Haki..." style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); resize:vertical;"></textarea>
                                 </div>
                             </div>           </div>
                            
                          <div class="form-group">
                              <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Descripción y Efecto Propuesto</label>
                              <textarea id="req_new_desc" class="textbox" rows="5" placeholder="Describe el efecto de la carta, coste aproximado de PE, etc..." style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); resize:vertical;"></textarea>
                          </div>
                          <button class="pj-btn-add" style="margin-top:5px; width:100%; justify-content:center; padding:12px; font-weight:800;" onclick="submitCustomCardRequest()"><i class="fas fa-paper-plane"></i> Enviar Propuesta al Staff</button>
                      </div>
                  </div>

                  <!-- SUBTAB: CARTA CATÁLOGO -->
                  <div id="gestion_subtab_solicitar_catalogo" class="gestion-subtab-content" style="display:none;">
                      <button class="rpg-back-btn" onclick="showGestionDashboard()">
                          <i class="fas fa-arrow-left"></i> Volver a Gestión
                      </button>

                      <div style="max-width:650px; margin:0 auto; background:var(--bg-main); border:1px solid var(--border-color); border-radius:12px; padding:30px; display:flex; flex-direction:column; gap:20px; box-shadow:var(--shadow-card);">
                          <h3 style="margin:0; font-size:16px; color:var(--text-primary); border-bottom:1px solid var(--border-color); padding-bottom:12px; display:flex; align-items:center; gap:10px; font-family:var(--font-heading); font-weight:800;">
                              <i class="fas fa-clone" style="color:var(--accent-indigo); font-size:18px;"></i> Solicitar Carta del Catálogo
                          </h3>
                          <p style="font-size:12px; color:var(--text-muted); margin:0; line-height:1.6;">
                              Solicita que se te asigne una de las cartas preexistentes del catálogo oficial del juego.
                          </p>
                          
                          <div class="form-group">
                              <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Seleccionar Carta</label>
                              <select id="req_existing_id" class="textbox" style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary);">
                                  <option value="">Selecciona una carta...</option>
                                  <?php foreach ($catalog_cards as $cc): ?>
                                      <option value="<?= $cc['id'] ?>">[<?= $cc['rank'] ?>] <?= htmlspecialchars($cc['name']) ?> (<?= ucfirst($cc['card_type']) ?>)</option>
                                  <?php endforeach; ?>
                              </select>
                          </div>
                          <div class="form-group">
                              <label style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px; display:block;">Nota / Justificación (Opcional)</label>
                              <textarea id="req_existing_note" class="textbox" rows="5" placeholder="Indica dónde obtuviste esta carta (ej: link a post de entrenamiento, premio de misión o compra de tienda)..." style="width:100%; box-sizing:border-box; padding:10px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); resize:vertical;"></textarea>
                          </div>
                          <button class="pj-btn-add rpg-btn--primary" style="margin-top:5px; width:100%; justify-content:center; padding:12px;" onclick="submitCatalogCardRequest()"><i class="fas fa-paper-plane"></i> Solicitar Adición</button>
                      </div>
                  </div>

                  <!-- SUBTAB: HISTORIAL -->
                  <div id="gestion_subtab_historial" class="gestion-subtab-content" style="display:none;">
                      <button class="rpg-back-btn" onclick="showGestionDashboard()">
                          <i class="fas fa-arrow-left"></i> Volver a Gestión
                      </button>

                      <div class="rpg-req-split">
                          <!-- LEFT: Requests List -->
                          <div class="rpg-req-list" id="my-requests-list-items">
                              <div style="padding:20px; text-align:center; color:var(--text-muted);">Cargando solicitudes...</div>
                          </div>

                          <!-- RIGHT: Request Details -->
                          <div class="rpg-req-detail" id="my-request-detail-panel">
                              <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-muted); text-align:center;">
                                  <i class="fas fa-envelope-open-text" style="font-size:40px; color:var(--text-muted); opacity:0.3; margin-bottom:15px;"></i>
                                  Selecciona una solicitud de la lista para ver su conversación y estado.
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
