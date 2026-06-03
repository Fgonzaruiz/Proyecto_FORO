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
<div class="rpg-gestion-panel">
                  <!-- DASHBOARD LANDING VIEW -->
                  <div id="gestion_dashboard">
                      <div class="rpg-pp-display rpg-pp-display--wrap">
                          <div class="rpg-pp-col">
                              <h3>Panel de Gestión del Personaje</h3>
                              <div class="rpg-pp-desc">Nivel <?= (int)$pj_progression['nivel'] ?> &bull; Cada 20 puntos de atributo comprados suben 1 nivel (máx. 1/semana). Si ya subiste esta semana, solo puedes comprar hasta quedar a 1 del siguiente umbral.</div>
                          </div>
                          <div class="rpg-pp-stats-row">
                              <div class="rpg-pp-val rpg-pp-val--sm"><i class="fas fa-level-up-alt"></i> Nv. <span id="val_pj_nivel"><?= (int)$pj_progression['nivel'] ?></span></div>
                              <div class="rpg-pp-val"><i class="fas fa-gem"></i> <span id="val_available_pp"><?= $pp_available ?></span> PP</div>
                          </div>
                      </div>

                      <div class="rpg-gestion-dashboard-grid">
                          <!-- CARD 1: ATRIBUTOS -->
                          <div class="rpg-gestion-card" onclick="switchGestionSubtab('atributos')">
                              <div class="rpg-gestion-card-icon rpg-gestion-card-icon--attr">
                                  <i class="fas fa-chart-line"></i>
                              </div>
                              <div class="rpg-gestion-card-body">
                                  <h3>Comprar Atributos</h3>
                                  <p>Mejora tus estadísticas base (Fuerza, Agilidad, Espíritu, etc.) canjeando tus PP acumulados.</p>
                              </div>
                              <div class="rpg-gestion-card-footer">
                                  <span class="rpg-gestion-card-tag"><?= (int)$pj_progression['stat_cost'] ?> PP / Punto</span>
                                  <i class="fas fa-chevron-right rpg-gestion-chevron"></i>
                              </div>
                          </div>

                                <!-- CARD 2: GESTIONAR DECK -->
                           <div class="rpg-gestion-card" onclick="switchGestionSubtab('crear_carta')">
                               <div class="rpg-gestion-card-icon rpg-gestion-card-icon--deck">
                                   <i class="fas fa-wand-magic-sparkles"></i>
                               </div>
                               <div class="rpg-gestion-card-body">
                                   <h3>Gestionar Deck</h3>
                                   <p>Propón una carta personalizada (técnica, equipo, etc.) o solicita el borrado de alguna de tu deck.</p>
                               </div>
                               <div class="rpg-gestion-card-footer">
                                   <span class="rpg-gestion-card-tag">Propuestas y borrados</span>
                                   <i class="fas fa-chevron-right rpg-gestion-chevron"></i>
                               </div>
                           </div>

                          <!-- CARD 3: CARTA CATÁLOGO -->
                          <div class="rpg-gestion-card" onclick="switchGestionSubtab('solicitar_catalogo')">
                              <div class="rpg-gestion-card-icon rpg-gestion-card-icon--catalog">
                                  <i class="fas fa-clone"></i>
                              </div>
                              <div class="rpg-gestion-card-body">
                                  <h3>Carta de Catálogo</h3>
                                  <p>Solicita que se te añada una carta oficial existente en el catálogo del foro (misiones, eventos, etc.).</p>
                              </div>
                              <div class="rpg-gestion-card-footer">
                                  <span class="rpg-gestion-card-tag">Catálogo oficial</span>
                                  <i class="fas fa-chevron-right rpg-gestion-chevron"></i>
                              </div>
                          </div>

                          <!-- CARD 4: HISTORIAL Y CONVERSACIONES -->
                          <div class="rpg-gestion-card" onclick="switchGestionSubtab('historial')">
                              <div class="rpg-gestion-card-icon rpg-gestion-card-icon--requests">
                                  <i class="fas fa-clipboard-list"></i>
                              </div>
                              <div class="rpg-gestion-card-body">
                                  <h3>Mis Solicitudes</h3>
                                  <p>Revisa tus solicitudes activas, responde en el chat de discusión y confirma tu conformidad.</p>
                              </div>
                              <div class="rpg-gestion-card-footer">
                                  <span class="rpg-gestion-card-tag">Mensajes e historial</span>
                                  <span id="dashboard-requests-badge" class="rpg-gestion-card-badge rpg-is-hidden">0 activa(s)</span>
                              </div>
                          </div>
                      </div>
                  </div>

                  <!-- SUBTAB: ATRIBUTOS -->
                  <div id="gestion_subtab_atributos" class="gestion-subtab-content">
                      <button class="rpg-back-btn" onclick="showGestionDashboard()">
                          <i class="fas fa-arrow-left"></i> Volver a Gestión
                      </button>

                      <div class="rpg-pp-display rpg-pp-display--wrap">
                          <div class="rpg-pp-col rpg-pp-col--wide">
                              <h3>Progresión y atributos</h3>
                              <div class="rpg-pp-desc rpg-pp-desc--spaced">
                                  <strong id="val_pj_nivel_sub">Nivel <?= (int)$pj_progression['nivel'] ?></strong>
                                  &bull; Precio actual: <strong><?= (int)$pj_progression['stat_cost'] ?> PP</strong> por punto
                                  <br>
                                  Progreso hacia nivel <?= (int)$pj_progression['nivel'] + 1 ?>: <strong><?= (int)$pj_progression['progress_in_tier'] ?>/<?= (int)$pj_progression['stat_points_per_level'] ?></strong> puntos de atributo comprados en esta franja
                                  (<?= (int)$pj_progression['stat_points_purchased'] ?> comprados en total)
                                  <?php if (!$pj_progression['can_level_up_this_week'] && $pj_progression['max_stat_points_buyable'] !== null): ?>
                                  <br><span class="rpg-warning-text">Tope semanal activo: puedes comprar como máximo <?= (int)$pj_progression['max_stat_points_buyable'] ?> punto(s) más hasta el <?= !empty($pj_progression['next_level_available_iso']) ? htmlspecialchars(date('d/m/Y', strtotime($pj_progression['next_level_available_iso']))) : 'próximo desbloqueo' ?>.</span>
                                  <?php endif; ?>
                                  <?php if ((int)$pj_progression['pp_linaje'] > 0): ?>
                                  <br><span class="rpg-muted-soft">Tienes <?= (int)$pj_progression['pp_linaje'] ?> PP de sobrante de linaje (se gastan primero al comprar).</span>
                                  <?php endif; ?>
                              </div>
                              <div id="pj_level_pending_box" class="rpg-level-pending-box<?= ((int)$pj_progression['pending_levels'] > 0) ? '' : ' rpg-is-hidden' ?>">
                                  <div class="rpg-level-pending-msg">
                                      <i class="fas fa-arrow-up"></i> <span id="val_pending_levels"><?= (int)$pj_progression['pending_levels'] ?></span> subida(s) de nivel pendiente(s)
                                  </div>
                                  <button type="button" id="btn_claim_level" class="rpg-attr-buy-btn rpg-attr-claim-btn<?= ($pj_progression['pending_levels'] > 0 && $pj_progression['can_level_up_this_week']) ? '' : ' rpg-is-hidden' ?>" onclick="claimPendingLevel()">
                                      <i class="fas fa-level-up-alt"></i> Aplicar subida de nivel
                                  </button>
                                  <div id="pj_level_cooldown_msg" class="rpg-level-cooldown<?= ($pj_progression['pending_levels'] > 0 && !$pj_progression['can_level_up_this_week']) ? '' : ' rpg-is-hidden' ?>">
                                      <?php if (!empty($pj_progression['next_level_available_iso'])): ?>
                                      Próxima subida disponible: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($pj_progression['next_level_available_iso']))) ?>
                                      <?php endif; ?>
                                  </div>
                              </div>
                          </div>
                          <div class="rpg-pp-val"><i class="fas fa-gem"></i> <span id="val_available_pp_sub"><?= $pp_available ?></span> PP</div>
                      </div>

                      <?php if ($char['status'] !== 'aprobada'): ?>
                          <div class="rpg-locked-panel">
                              <i class="fas fa-lock rpg-locked-icon"></i>
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
                                          <div class="rpg-attr-buy-icon" style="--icon-bg: <?= $lbl[2] ?>; --icon-color: <?= $lbl[3] ?>;">
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

                  <!-- SUBTAB: CREACIÓN DE CARTA (GESTIONAR DECK) -->
                  <div id="gestion_subtab_crear_carta" class="gestion-subtab-content">
                      <button class="rpg-back-btn" onclick="showGestionDashboard()">
                          <i class="fas fa-arrow-left"></i> Volver a Gestión
                      </button>

                      <div class="rpg-form-panel">
                          <h3 class="rpg-form-heading">
                              <i class="fas fa-sliders-h rpg-form-heading-icon--purple"></i> Gestionar Deck
                          </h3>
                          
                          <!-- Selector de Modo -->
                          <div class="rpg-gestion-deck-modes">
                              <button type="button" id="btn_mode_propose" class="rpg-back-btn rpg-back-btn--flat active" onclick="switchGestionDeckMode('propose')">
                                  <i class="fas fa-plus"></i> Proponer Nueva Carta
                              </button>
                              <button type="button" id="btn_mode_delete" class="rpg-back-btn rpg-back-btn--flat" onclick="switchGestionDeckMode('delete')">
                                  <i class="fas fa-trash-alt"></i> Solicitar Borrado
                              </button>
                          </div>

                          <!-- MODO: PROPONER CARTA -->
                          <div id="deck_mode_propose_section">
                              <div class="rpg-form-stack">
                                  <p class="rpg-form-help">
                                      Propón una técnica, equipo, Akuma no Mi o NPC menor adaptado a tu personaje. Tras enviarla, podrás conversar con los moderadores en el chat interactivo para ajustar sus efectos.
                                  </p>
                                  
                                  <div class="form-group">
                                      <label class="rpg-form-label">Nombre de la Carta</label>
                                      <input type="text" id="req_new_name" class="textbox rpg-form-input">
                                  </div>
                                    
                                  <div class="form-group">
                                       <label class="rpg-form-label">Tipo de Carta</label>
                                       <select id="req_new_type" class="textbox rpg-form-input">
                                           <option value="tecnica">Técnica</option>
                                           <option value="equipo">Equipo</option>
                                           <option value="akuma_no_mi">Akuma no Mi</option>
                                           <option value="haki">Haki</option>
                                           <option value="npc_menor">NPC Menor</option>
                                           <option value="barco">Barco</option>
                                       </select>
                                  </div>

                                  <!-- CAMPOS DINÁMICOS PROPUESTA JUGADOR -->
                                  <div id="req_fields_akuma" class="rpg-req-fields">
                                      <div class="form-group">
                                          <label class="rpg-form-label">Tipo de Akuma</label>
                                          <select id="req_akuma_type" class="textbox rpg-form-input">
                                              <option value="paramecia">Paramecia</option>
                                              <option value="logia">Logia</option>
                                              <option value="zoan">Zoan</option>
                                          </select>
                                      </div>
                                      <div class="form-group">
                                          <label class="rpg-form-label">Efectos</label>
                                          <textarea id="req_akuma_efectos" class="textbox rpg-form-input rpg-form-input--resize"></textarea>
                                      </div>
                                      <div class="form-group">
                                          <label class="rpg-form-label">Limitaciones</label>
                                          <textarea id="req_akuma_limitaciones" class="textbox rpg-form-input rpg-form-input--resize"></textarea>
                                      </div>
                                      <div class="form-group">
                                          <label class="rpg-form-label">Debilidades</label>
                                          <textarea id="req_akuma_debilidades" class="textbox rpg-form-input rpg-form-input--resize"></textarea>
                                      </div>
                                  </div>

                                  <div id="req_fields_equipo" class="rpg-req-fields">
                                      <div class="form-group">
                                          <label class="rpg-form-label">Tipo de Equipo</label>
                                          <select id="req_equipo_type" class="textbox rpg-form-input">
                                              <option value="arma">Arma</option>
                                              <option value="util">Útil / Consumible</option>
                                              <option value="armadura">Armadura</option>
                                          </select>
                                      </div>
                                      <div class="form-group">
                                          <label class="rpg-form-label">Subtipo (ej: Espada, Arco, Botiquín...)</label>
                                          <select id="req_equipo_subtipo_select" class="textbox rpg-form-input rpg-form-input--spaced"></select>
                                          <input type="text" id="req_equipo_subtipo" class="textbox rpg-form-input rpg-is-hidden">
                                      </div>
                                      <div id="wrapper_req_equipo_damage" class="rpg-form-row-flex rpg-is-hidden">
                                           <div class="form-group rpg-form-group--flex1">
                                               <label class="rpg-form-label">Dado de Daño</label>
                                               <select id="req_equipo_damage_dice_select" class="textbox rpg-form-input rpg-form-input--spaced">
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
                                               <input type="text" id="req_equipo_damage_dice" class="textbox rpg-form-input rpg-is-hidden">
                                           </div>
                                           <div class="form-group rpg-form-group--flex1">
                                               <label class="rpg-form-label">Atributo</label>
                                               <select id="req_equipo_damage_stat" class="textbox rpg-form-input">
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

                                  <div id="req_fields_barco" class="rpg-req-fields">
                                      <div class="form-group">
                                          <label class="rpg-form-label">Tipo de Barco</label>
                                          <select id="req_barco_type" class="textbox rpg-form-input">
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
                                      <div class="rpg-form-grid-2">
                                          <div class="form-group">
                                              <label class="rpg-form-label">Tier</label>
                                              <input type="number" id="req_barco_tier" min="1" value="1" class="textbox rpg-form-input">
                                          </div>
                                          <div class="form-group">
                                              <label class="rpg-form-label">Vida</label>
                                              <input type="number" id="req_barco_vida" min="0" value="100" class="textbox rpg-form-input">
                                          </div>
                                          <div class="form-group">
                                              <label class="rpg-form-label">Ataque</label>
                                              <input type="number" id="req_barco_ataque" min="0" value="0" class="textbox rpg-form-input">
                                          </div>
                                          <div class="form-group">
                                              <label class="rpg-form-label">Velocidad</label>
                                              <input type="number" id="req_barco_velocidad" min="0" value="0" class="textbox rpg-form-input">
                                          </div>
                                          <div class="form-group rpg-form-group--span2">
                                              <label class="rpg-form-label">Resistencia</label>
                                              <input type="number" id="req_barco_resistencia" min="0" value="0" class="textbox rpg-form-input">
                                          </div>
                                      </div>
                                  </div>

                                  <div id="req_fields_npc" class="rpg-req-fields">
                                      <div class="form-group">
                                          <label class="rpg-form-label">Subtipo</label>
                                          <select id="req_npc_mascota_type" class="textbox rpg-form-input">
                                              <option value="npc">NPC</option>
                                              <option value="mascota">Mascota</option>
                                          </select>
                                      </div>
                                      <div class="form-group">
                                          <label class="rpg-form-label">Vida (HP)</label>
                                          <input type="number" id="req_npc_vida" min="0" value="50" class="textbox rpg-form-input">
                                      </div>
                                      <div class="form-group rpg-is-hidden" id="wrapper_req_npc_tier">
                                          <label class="rpg-form-label">Tier de Mascota</label>
                                          <input type="number" id="req_npc_tier" min="1" value="1" class="textbox rpg-form-input">
                                      </div>
                                      <div class="form-group">
                                          <label class="rpg-form-label">Acciones</label>
                                          <div id="req-npc-actions-container" class="rpg-npc-actions"></div>
                                          <button type="button" id="btn-req-npc-add-action" class="textbox rpg-btn-add-dashed">+ Añadir Acción</button>
                                      </div>
                                  </div>

                                  <div id="req_fields_haki" class="rpg-req-fields">
                                       <div class="form-group">
                                           <label class="rpg-form-label">Tipo de Haki</label>
                                           <select id="req_haki_type" class="textbox rpg-form-input">
                                               <option value="busoshoku">Busoshoku (Armamiento)</option>
                                               <option value="kenbunshoku">Kenbunshoku (Observación)</option>
                                               <option value="haoshoku">Haoshoku (Conquistador / Rey)</option>
                                           </select>
                                       </div>
                                       <div class="form-group">
                                           <label class="rpg-form-label">Nivel de Haki</label>
                                           <select id="req_haki_level" class="textbox rpg-form-input">
                                               <option value="despertado">Despertado</option>
                                               <option value="basico">Básico</option>
                                               <option value="medio">Medio</option>
                                               <option value="avanzado">Avanzado</option>
                                               <option value="maestro">Maestro</option>
                                           </select>
                                       </div>
                                       <div class="form-group">
                                           <label class="rpg-form-label">Efecto</label>
                                           <textarea id="req_haki_efecto" class="textbox rpg-form-input rpg-form-input--resize"></textarea>
                                       </div>
                                  </div>

                                  <div class="form-group rpg-form-section-spaced">
                                      <label class="rpg-form-label">Descripción y Efecto Propuesto</label>
                                      <textarea id="req_new_desc" class="textbox rpg-form-input rpg-form-input--resize"></textarea>
                                  </div>
                                  <button class="pj-btn-add pj-btn-add--full" onclick="submitCustomCardRequest()"><i class="fas fa-paper-plane"></i> Enviar Propuesta al Staff</button>
                              </div>
                          </div>

                          <!-- MODO: SOLICITAR BORRADO -->
                          <div id="deck_mode_delete_section" class="rpg-is-hidden">
                              <div class="rpg-form-stack rpg-form-stack--wide">
                                  <p class="rpg-form-help">
                                      Selecciona la carta que deseas eliminar de tu inventario y detalla el motivo de la solicitud. El staff revisará la petición en tu historial.
                                  </p>
                                  <div class="form-group">
                                      <label class="rpg-form-label">Seleccionar Carta a Borrar</label>
                                      <select id="req_delete_card_id" class="textbox rpg-form-input">
                                          <option value="">Cargando tus cartas...</option>
                                      </select>
                                  </div>
                                  <div class="form-group">
                                      <label class="rpg-form-label">Motivo del Borrado</label>
                                      <textarea id="req_delete_reason" class="textbox rpg-form-input rpg-form-input--resize"></textarea>
                                  </div>
                                  <button class="pj-btn-add pj-btn-add--full pj-btn-add--danger" onclick="submitCardDeleteRequest()"><i class="fas fa-trash-alt"></i> Enviar Solicitud de Borrado</button>
                              </div>
                          </div>
                      </div>
                  </div>

                  <!-- SUBTAB: CARTA CATÁLOGO -->
                  <div id="gestion_subtab_solicitar_catalogo" class="gestion-subtab-content">
                      <button class="rpg-back-btn" onclick="showGestionDashboard()">
                          <i class="fas fa-arrow-left"></i> Volver a Gestión
                      </button>

                      <div class="rpg-form-panel">
                          <h3 class="rpg-form-heading">
                              <i class="fas fa-clone rpg-form-heading-icon--indigo"></i> Solicitar Carta del Catálogo
                          </h3>
                          <p class="rpg-form-help">
                              Solicita que se te asigne una de las cartas preexistentes del catálogo oficial del juego.
                          </p>
                          
                          <div class="form-group">
                              <label class="rpg-form-label">Seleccionar Carta</label>
                              <select id="req_existing_id" class="textbox rpg-form-input">
                                  <option value="">Selecciona una carta...</option>
                                  <?php foreach ($catalog_cards as $cc): ?>
                                      <option value="<?= $cc['id'] ?>">[<?= $cc['rank'] ?>] <?= htmlspecialchars($cc['name']) ?> (<?= ucfirst($cc['card_type']) ?>)</option>
                                  <?php endforeach; ?>
                              </select>
                          </div>
                          <div class="form-group">
                              <label class="rpg-form-label">Nota / Justificación (Opcional)</label>
                              <textarea id="req_existing_note" class="textbox rpg-form-input rpg-form-input--resize"></textarea>
                          </div>
                          <button class="pj-btn-add rpg-btn--primary pj-btn-add--full-sm" onclick="submitCatalogCardRequest()"><i class="fas fa-paper-plane"></i> Solicitar Adición</button>
                      </div>
                  </div>

                  <!-- SUBTAB: HISTORIAL -->
                  <div id="gestion_subtab_historial" class="gestion-subtab-content">
                      <button class="rpg-back-btn" onclick="showGestionDashboard()">
                          <i class="fas fa-arrow-left"></i> Volver a Gestión
                      </button>

                      <div class="rpg-req-split">
                          <!-- LEFT: Requests List -->
                          <div class="rpg-req-list" id="my-requests-list-items">
                              <div class="rpg-req-loading">Cargando solicitudes...</div>
                          </div>

                          <!-- RIGHT: Request Details -->
                          <div class="rpg-req-detail" id="my-request-detail-panel">
                              <div class="rpg-req-detail-empty">
                                  <i class="fas fa-envelope-open-text"></i>
                                  Selecciona una solicitud de la lista para ver su conversación y estado.
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
