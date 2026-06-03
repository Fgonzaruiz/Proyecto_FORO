  <?php if ($can_edit): ?>
  <!-- MODAL DIARIO -->
  <div id="modal_diario" class="pj-modal-overlay" onclick="if(event.target===this){this.style.display='none'; document.getElementById('modal_gestionar_diario').style.display='flex';}">
      <div class="pj-modal">
          <div class="pj-modal-title">Añadir Entrada al Diario</div>
          <div class="form-group">
              <label>Link al Tema de Rol</label>
              <div class="pj-modal-row">
                  <input type="url" id="diario_link" class="textbox pj-modal-input-flex" placeholder="https://foro.com/showthread.php?tid=123" onblur="autoDetectThread(this.value)">
                  <button class="pj-btn-add pj-btn-add--detect" onclick="autoDetectThread(document.getElementById('diario_link').value)"><i class="fas fa-sync-alt"></i> Detectar</button>
              </div>
              <div class="pj-form-hint">Pega el enlace del hilo y presiona "Detectar" para auto-completar los datos.</div>
          </div>
          <div id="diario_auto_data" class="pj-detect-box rpg-is-hidden">
              <div class="pj-detect-header">
                  <i class="fas fa-check-circle pj-detect-icon-ok"></i>
                  <span class="pj-detect-title">Datos detectados del hilo</span>
              </div>
              <div class="pj-detect-grid">
                  <div><span class="pj-detect-label">Título:</span> <span id="diario_detected_title" class="pj-detect-val"></span></div>
                  <div><span class="pj-detect-label">Tipo:</span> <span id="diario_detected_cat" class="pj-detect-val pj-detect-val--bold"></span></div>
                  <div><span class="pj-detect-label">Fecha:</span> <span id="diario_detected_date" class="pj-detect-val"></span></div>
                  <div><span class="pj-detect-label">Participantes:</span> <span id="diario_detected_parts" class="pj-detect-val"></span></div>
              </div>
              <input type="hidden" id="diario_thread_id" value="">
              <input type="hidden" id="diario_cat" value="">
              <input type="hidden" id="diario_day" value="">
              <input type="hidden" id="diario_season" value="">
              <input type="hidden" id="diario_year" value="">
          </div>
          <div class="form-group">
              <label>Descripción</label>
              <textarea id="diario_desc" class="textbox" rows="4" placeholder="Resumen de los hechos..."></textarea>
          </div>
          <div class="pj-modal-actions">
              <button class="pj-btn-add" onclick="document.getElementById('modal_diario').style.display='none'; document.getElementById('modal_gestionar_diario').style.display='flex'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('diario')"><i class="fas fa-check"></i> Confirmar</button>
          </div>
      </div>
  </div>



  <!-- MODAL RELACION -->
  <div id="modal_relacion" class="pj-modal-overlay" onclick="if(event.target===this){this.style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex';}">
      <div class="pj-modal">
          <div class="pj-modal-title" id="rel_modal_title">Añadir Relación</div>
          <div class="form-group">
              <label class="pj-label-inline">
                  <input type="checkbox" id="rel_is_npc" onchange="toggleRelNpc(this)">
                  Es un NPC (Personaje No Jugador)
              </label>
          </div>
          <div class="form-group" id="rel_pj_box">
              <label>Personaje del Foro <span class="pj-label-hint">— empieza a escribir para buscar</span></label>
              <input type="text" id="rel_pj_search" class="textbox" placeholder="Buscar personaje..." autocomplete="off" oninput="searchPersonaje(this.value)">
              <select id="rel_pj_id" class="rpg-is-hidden">
                  <option value="">Selecciona un personaje</option>
                  <?php foreach($all_chars as $c): ?>
                  <option value="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                  <?php endforeach; ?>
              </select>
              <div id="rel_pj_results" class="pj-pj-results"></div>
          </div>
          <div class="form-group rpg-is-hidden" id="rel_npc_box">
              <label>Nombre del NPC</label>
              <input type="text" id="rel_npc_name" class="textbox" placeholder="Ej: Alcalde de la ciudad">
          </div>
          <div class="form-group">
              <label>Descripción Corta</label>
              <input type="text" id="rel_desc" class="textbox" placeholder="Breve nota sobre la relación...">
          </div>
          <div class="form-group">
              <label>Imagen (URL 70x70 aprox)</label>
              <input type="url" id="rel_img" class="textbox" placeholder="https://i.imgur.com/...">
          </div>
          <div class="form-group">
              <label>Etiquetas (Elige hasta 3)</label>
              <div class="pj-tag-picker" id="rel_tag_picker">
                  <?php foreach ($tag_colors as $lbl => $c): ?>
                      <div class="pj-tag" data-tag="<?= htmlspecialchars($lbl) ?>" data-color="<?= $c ?>"><?= htmlspecialchars($lbl) ?></div>
                  <?php endforeach; ?>
              </div>
              <input type="hidden" id="rel_tags" value="">
          </div>

          <hr class="pj-modal-divider">
          <div class="form-group">
              <label class="pj-label-inline--bold">
                  <input type="checkbox" id="rel_add_conn" onchange="document.getElementById('rel_conn_options').classList.toggle('rpg-is-hidden', !this.checked)">
                  ¿Crear una línea de conexión explícita en la red?
              </label>
          </div>
          <div id="rel_conn_options" class="pj-conn-panel rpg-is-hidden">
              <p class="pj-conn-help">El origen ser&aacute; este contacto que est&aacute;s creando/editando.</p>
              <div class="form-group">
                  <label>Enlazar con (Destino)</label>
                  <select id="rel_conn_target" class="textbox"></select>
              </div>
              <div class="form-group">
                  <label>Nombre de la Conexión (Ej: Novios, Hermanos)</label>
                  <input type="text" id="rel_conn_label" class="textbox" placeholder="Aparecerá en la línea...">
              </div>
              <div class="form-group">
                  <label>Color de la Línea</label>
                  <div class="pj-color-swatches" id="rel_conn_colors">
                      <?php $g_colors = ['#10b981','#3b82f6','#C62828','#8b5cf6','#ec4899','#ef4444','#f97316','#f59e0b']; foreach ($g_colors as $c): ?>
                          <div class="conn-color-swatch-rel pj-color-swatch" data-color="<?= $c ?>" onclick="selectConnColorRel(this)"></div>
                      <?php endforeach; ?>
                  </div>
                  <input type="hidden" id="rel_conn_color" value="#ec4899">
              </div>
          </div>
          
          <div class="pj-modal-footer-right">
              <button class="pj-btn-add pj-btn-add--cancel" onclick="document.getElementById('modal_relacion').style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('relacion')"><i class="fas fa-check"></i> Confirmar</button>
          </div>
      </div>
  </div>

  <!-- MODAL GESTIONAR DIARIO -->
  <div id="modal_gestionar_diario" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
      <div class="pj-modal pj-modal--md">
          <div class="pj-modal-title">Diario de Aventuras</div>

          <div class="pj-modal-toolbar">
              <span class="pj-modal-toolbar-text">Administra o añade nuevas memorias a tu cronología.</span>
              <button class="pj-btn-add pj-btn-add--sm" onclick="openNewDiario()"><i class="fas fa-plus"></i> Añadir Entrada</button>
          </div>

          <div id="diario-list" class="pj-edit-list pj-edit-list--modal"></div>

          <div class="pj-modal-footer">
              <button class="pj-btn-add" onclick="document.getElementById('modal_gestionar_diario').style.display='none'">Cerrar</button>
              <button class="pj-btn-add pj-btn-add--save" onclick="saveBatchCronologia()"><i class="fas fa-save"></i> Guardar Todo</button>
          </div>
      </div>
  </div>

  <!-- MODAL EDITAR RELACIONES Y GRUPOS (TABBED DASHBOARD) -->
  <div id="modal_gestionar_relaciones" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
      <div class="pj-modal pj-modal--lg">
          <div class="pj-modal-title pj-modal-title--spaced">Cuaderno de Relaciones y Red</div>
          
          <div class="pj-modal-tabs">
              <button class="pj-modal-tab-btn active" onclick="switchRelTab('contactos',this)">Contactos y NPCs</button>
              <button class="pj-modal-tab-btn" onclick="switchRelTab('grupos',this)">Grupos y Facciones</button>
              <button class="pj-modal-tab-btn" onclick="switchRelTab('conexiones',this)">Conexiones de Red</button>
          </div>

          <div id="tab-contactos" class="pj-tab-content">
              <div class="pj-modal-toolbar">
                  <span class="pj-modal-toolbar-text">Administra tus relaciones directas con otros personajes del foro o NPCs.</span>
                  <button class="pj-btn-add pj-btn-add--sm" onclick="openNewRelacion()"><i class="fas fa-user-plus"></i> Añadir Contacto</button>
              </div>
              <div id="contactos-list" class="pj-edit-list pj-edit-list--modal"></div>
          </div>

          <div id="tab-grupos" class="pj-tab-content is-hidden">
              <div class="pj-modal-toolbar">
                  <span class="pj-modal-toolbar-text">Organiza tus contactos en grupos (ej: tu tripulación, gremios, familia).</span>
                  <button class="pj-btn-add pj-btn-add--sm" onclick="openNewGroup()"><i class="fas fa-users"></i> Crear Grupo</button>
              </div>
              <div id="grupos-list" class="pj-edit-list pj-edit-list--modal"></div>
          </div>

          <div id="tab-conexiones" class="pj-tab-content is-hidden">
              <div class="pj-modal-toolbar">
                  <span class="pj-modal-toolbar-text">Dibuja uniones y vínculos personalizados entre contactos en el mapa de red.</span>
                  <button class="pj-btn-add pj-btn-add--sm" onclick="openNewConnection()"><i class="fas fa-link"></i> Añadir Conexión</button>
              </div>
              <div id="conexiones-list" class="pj-edit-list pj-edit-list--modal"></div>
          </div>

          <div class="pj-modal-footer">
              <button class="pj-btn-add" onclick="document.getElementById('modal_gestionar_relaciones').style.display='none'">Cerrar</button>
              <button class="pj-btn-add pj-btn-add--save" onclick="saveBatchCronologia()"><i class="fas fa-save"></i> Guardar Todo</button>
          </div>
      </div>
  </div>

  <!-- MODAL GRUPO -->
  <div id="modal_group" class="pj-modal-overlay" onclick="if(event.target===this){this.style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex';}">
      <div class="pj-modal pj-modal--sm">
          <div class="pj-modal-title" id="group_modal_title">Crear Grupo</div>
          
          <div class="form-group">
              <label>Nombre del Grupo</label>
              <input type="text" id="grp_name" class="textbox" placeholder="Ej: La Tripulación, Familia Real, etc.">
          </div>
          
          <div class="form-group">
              <label>Color del Grupo</label>
              <div class="pj-color-swatches" id="grp_colors">
                  <?php 
                  $g_colors = ['#10b981','#3b82f6','#C62828','#8b5cf6','#ec4899','#ef4444','#f97316','#f59e0b'];
                  foreach ($g_colors as $c): ?>
                      <div class="grp-color-swatch pj-color-swatch" data-color="<?= $c ?>" onclick="selectGroupColor(this)"></div>
                  <?php endforeach; ?>
              </div>
              <input type="hidden" id="grp_color" value="#C62828">
          </div>

          <div class="form-group">
              <label>Seleccionar Miembros (Mín. 2)</label>
              <div class="pj-scroll-box pj-scroll-box--grp" id="grp_members_container">
                  <!-- Will be rendered dynamically via JS -->
              </div>
          </div>

          <div class="pj-modal-footer-right pj-modal-footer-right--lg">
              <button class="pj-btn-add pj-btn-add--cancel" onclick="document.getElementById('modal_group').style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('group')"><i class="fas fa-check"></i> Confirmar</button>
          </div>
      </div>
  </div>

  <!-- MODAL CONEXION -->
  <div id="modal_connection" class="pj-modal-overlay" onclick="if(event.target===this){this.style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex';}">
      <div class="pj-modal pj-modal--sm">
          <div class="pj-modal-title" id="conn_modal_title">Crear Conexión</div>
          
          <div class="form-group">
              <label>Contacto A</label>
              <select id="conn_source" class="textbox"></select>
          </div>

          <div class="form-group">
              <label>Contacto B</label>
              <select id="conn_target" class="textbox"></select>
          </div>
          
          <div class="form-group">
              <label>Nombre de la Relación (Ej: Novios, Hermanos)</label>
              <input type="text" id="conn_label" class="textbox" placeholder="Aparecerá en la línea...">
          </div>
          
          <div class="form-group">
              <label>Color de la Línea</label>
              <div class="pj-color-swatches" id="conn_colors">
                  <?php $g_colors = ['#10b981','#3b82f6','#C62828','#8b5cf6','#ec4899','#ef4444','#f97316','#f59e0b']; foreach ($g_colors as $c): ?>
                      <div class="conn-color-swatch pj-color-swatch" data-color="<?= $c ?>" onclick="selectConnColor(this)"></div>
                  <?php endforeach; ?>
              </div>
              <input type="hidden" id="conn_color" value="#ec4899">
          </div>

          <div class="pj-modal-footer-right pj-modal-footer-right--lg">
              <button class="pj-btn-add pj-btn-add--cancel" onclick="document.getElementById('modal_connection').style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('connection')"><i class="fas fa-check"></i> Confirmar</button>
          </div>
      </div>
  </div>

  <?php endif; ?>
