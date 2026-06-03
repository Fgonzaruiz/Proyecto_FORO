  <?php if ($can_edit): ?>
  <!-- MODAL DIARIO -->
  <div id="modal_diario" class="pj-modal-overlay" onclick="if(event.target===this){this.style.display='none'; document.getElementById('modal_gestionar_diario').style.display='flex';}">
      <div class="pj-modal">
          <div class="pj-modal-title">Añadir Entrada al Diario</div>
          <div class="form-group">
              <label>Link al Tema de Rol</label>
              <div style="display:flex; gap:8px;">
                  <input type="url" id="diario_link" class="textbox" style="flex:1;" placeholder="https://foro.com/showthread.php?tid=123" onblur="autoDetectThread(this.value)">
                  <button class="pj-btn-add" style="flex-shrink:0; padding:8px 18px;" onclick="autoDetectThread(document.getElementById('diario_link').value)"><i class="fas fa-sync-alt"></i> Detectar</button>
              </div>
              <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Pega el enlace del hilo y presiona "Detectar" para auto-completar los datos.</div>
          </div>
          <div id="diario_auto_data" style="display:none; background:var(--bg-main); border:1px solid var(--border-color); border-radius:8px; padding:15px; margin-bottom:16px;">
              <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                  <i class="fas fa-check-circle" style="color:#10b981;"></i>
                  <span style="font-size:13px; font-weight:700; color:var(--text-primary);">Datos detectados del hilo</span>
              </div>
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:12px;">
                  <div><span style="color:var(--text-muted);">Título:</span> <span id="diario_detected_title" style="color:var(--text-primary); font-weight:600;"></span></div>
                  <div><span style="color:var(--text-muted);">Tipo:</span> <span id="diario_detected_cat" style="font-weight:700;"></span></div>
                  <div><span style="color:var(--text-muted);">Fecha:</span> <span id="diario_detected_date" style="color:var(--text-primary); font-weight:600;"></span></div>
                  <div><span style="color:var(--text-muted);">Participantes:</span> <span id="diario_detected_parts" style="color:var(--text-primary); font-weight:600;"></span></div>
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
              <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                  <input type="checkbox" id="rel_is_npc" onchange="toggleRelNpc(this)">
                  Es un NPC (Personaje No Jugador)
              </label>
          </div>
          <div class="form-group" id="rel_pj_box">
              <label>Personaje del Foro <span style="color:var(--text-muted);font-weight:400;text-transform:none;">— empieza a escribir para buscar</span></label>
              <input type="text" id="rel_pj_search" class="textbox" placeholder="Buscar personaje..." autocomplete="off" oninput="searchPersonaje(this.value)">
              <select id="rel_pj_id" style="display:none;">
                  <option value="">Selecciona un personaje</option>
                  <?php foreach($all_chars as $c): ?>
                  <option value="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                  <?php endforeach; ?>
              </select>
              <div id="rel_pj_results" style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;"></div>
          </div>
          <div class="form-group" id="rel_npc_box" style="display:none;">
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
                      <div class="pj-tag" data-tag="<?= htmlspecialchars($lbl) ?>" data-color="<?= $c ?>" style="border-color:<?= $c ?>; color:<?= $c ?>;"><?= htmlspecialchars($lbl) ?></div>
                  <?php endforeach; ?>
              </div>
              <input type="hidden" id="rel_tags" value="">
          </div>

          <hr style="border:0; border-top:1px solid var(--border-color); margin:20px 0;">
          <div class="form-group">
              <label style="display:flex; align-items:center; gap:8px; font-weight:700; cursor:pointer;">
                  <input type="checkbox" id="rel_add_conn" onchange="document.getElementById('rel_conn_options').style.display=this.checked?'block':'none'">
                  ¿Crear una línea de conexión explícita en la red?
              </label>
          </div>
          <div id="rel_conn_options" style="display:none; padding:15px; background:var(--bg-main); border:1px solid var(--border-color); border-radius:8px; margin-bottom:15px;">
              <p style="font-size:12px; color:var(--text-secondary); margin-top:0;">El origen ser&aacute; este contacto que est&aacute;s creando/editando.</p>
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
                  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;" id="rel_conn_colors">
                      <?php $g_colors = ['#10b981','#3b82f6','#6366f1','#8b5cf6','#ec4899','#ef4444','#f97316','#f59e0b']; foreach ($g_colors as $c): ?>
                          <div class="conn-color-swatch-rel" data-color="<?= $c ?>" style="width:28px; height:28px; border-radius:50%; background:<?= $c ?>; cursor:pointer; border:2px solid transparent; transition:transform 0.15s;" onclick="selectConnColorRel(this)"></div>
                      <?php endforeach; ?>
                  </div>
                  <input type="hidden" id="rel_conn_color" value="#ec4899">
              </div>
          </div>
          
          <div style="text-align:right; margin-top:20px;">
              <button class="pj-btn-add" style="margin-right:10px;" onclick="document.getElementById('modal_relacion').style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('relacion')"><i class="fas fa-check"></i> Confirmar</button>
          </div>
      </div>
  </div>

  <!-- MODAL GESTIONAR DIARIO -->
  <div id="modal_gestionar_diario" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
      <div class="pj-modal" style="width: 520px; max-width: 95vw;">
          <div class="pj-modal-title">Diario de Aventuras</div>
          


          <div style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
              <span style="font-size:12px; color:var(--text-muted);">Administra o añade nuevas memorias a tu cronología.</span>
              <button class="pj-btn-add" style="padding:6px 12px; font-size:12px;" onclick="openNewDiario()"><i class="fas fa-plus"></i> Añadir Entrada</button>
          </div>

          <div id="diario-list" class="pj-edit-list" style="height: 320px; overflow-y: auto;"></div>

          <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px; border-top: 1px solid var(--border-color); padding-top:20px;">
              <button class="pj-btn-add" onclick="document.getElementById('modal_gestionar_diario').style.display='none'">Cerrar</button>
              <button class="pj-btn-add" style="background:var(--accent-emerald,#10b981); color:#fff;" onclick="saveBatchCronologia()"><i class="fas fa-save"></i> Guardar Todo</button>
          </div>
      </div>
  </div>

  <!-- MODAL EDITAR RELACIONES Y GRUPOS (TABBED DASHBOARD) -->
  <div id="modal_gestionar_relaciones" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
      <div class="pj-modal" style="width: 540px; max-width: 95vw; padding: 25px;">
          <div class="pj-modal-title" style="margin-bottom:15px;">Cuaderno de Relaciones y Red</div>
          
          <!-- Tabbed navigation -->
          <div class="pj-modal-tabs" style="display:flex; border-bottom:1px solid var(--border-color); margin-bottom:20px; gap:5px; justify-content: center;">
              <button class="pj-modal-tab-btn active" onclick="switchRelTab('contactos',this)">Contactos y NPCs</button>
              <button class="pj-modal-tab-btn" onclick="switchRelTab('grupos',this)">Grupos y Facciones</button>
              <button class="pj-modal-tab-btn" onclick="switchRelTab('conexiones',this)">Conexiones de Red</button>
          </div>

          <!-- TAB CONTENT: CONTACTOS -->
          <div id="tab-contactos" class="pj-tab-content">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                  <span style="font-size:12px; color:var(--text-muted);">Administra tus relaciones directas con otros personajes del foro o NPCs.</span>
                  <button class="pj-btn-add" style="background:var(--accent-indigo); color:#fff; padding:6px 12px; font-size:12px;" onclick="openNewRelacion()"><i class="fas fa-user-plus"></i> Añadir Contacto</button>
              </div>
              <div id="contactos-list" class="pj-edit-list" style="height: 320px; overflow-y: auto;"></div>
          </div>

          <!-- TAB CONTENT: GRUPOS -->
          <div id="tab-grupos" class="pj-tab-content" style="display:none;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                  <span style="font-size:12px; color:var(--text-muted);">Organiza tus contactos en grupos (ej: tu tripulación, gremios, familia).</span>
                  <button class="pj-btn-add" style="background:var(--accent-indigo); color:#fff; padding:6px 12px; font-size:12px;" onclick="openNewGroup()"><i class="fas fa-users"></i> Crear Grupo</button>
              </div>
              <div id="grupos-list" class="pj-edit-list" style="height: 320px; overflow-y: auto;"></div>
          </div>

          <!-- TAB CONTENT: CONEXIONES -->
          <div id="tab-conexiones" class="pj-tab-content" style="display:none;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                  <span style="font-size:12px; color:var(--text-muted);">Dibuja uniones y vínculos personalizados entre contactos en el mapa de red.</span>
                  <button class="pj-btn-add" style="background:var(--accent-indigo); color:#fff; padding:6px 12px; font-size:12px;" onclick="openNewConnection()"><i class="fas fa-link"></i> Añadir Conexión</button>
              </div>
              <div id="conexiones-list" class="pj-edit-list" style="height: 320px; overflow-y: auto;"></div>
          </div>

          <!-- Footer Actions -->
          <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px; border-top: 1px solid var(--border-color); padding-top:20px;">
              <button class="pj-btn-add" onclick="document.getElementById('modal_gestionar_relaciones').style.display='none'">Cerrar</button>
              <button class="pj-btn-add" style="background:var(--accent-emerald,#10b981); color:#fff;" onclick="saveBatchCronologia()"><i class="fas fa-save"></i> Guardar Todo</button>
          </div>
      </div>
  </div>

  <!-- MODAL GRUPO -->
  <div id="modal_group" class="pj-modal-overlay" onclick="if(event.target===this){this.style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex';}">
      <div class="pj-modal" style="width: 500px;">
          <div class="pj-modal-title" id="group_modal_title">Crear Grupo</div>
          
          <div class="form-group">
              <label>Nombre del Grupo</label>
              <input type="text" id="grp_name" class="textbox" placeholder="Ej: La Tripulación, Familia Real, etc.">
          </div>
          
          <div class="form-group">
              <label>Color del Grupo</label>
              <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;" id="grp_colors">
                  <?php 
                  $g_colors = ['#10b981','#3b82f6','#6366f1','#8b5cf6','#ec4899','#ef4444','#f97316','#f59e0b'];
                  foreach ($g_colors as $c): ?>
                      <div class="grp-color-swatch" data-color="<?= $c ?>" style="width:28px; height:28px; border-radius:50%; background:<?= $c ?>; cursor:pointer; border:2px solid transparent; transition:transform 0.15s;" onclick="selectGroupColor(this)"></div>
                  <?php endforeach; ?>
              </div>
              <input type="hidden" id="grp_color" value="#6366f1">
          </div>

          <div class="form-group">
              <label>Seleccionar Miembros (Mín. 2)</label>
              <div class="pj-scroll-box" id="grp_members_container" style="height: 180px; padding:10px; background:var(--bg-main); border:1px solid var(--border-color); margin-bottom:0;">
                  <!-- Will be rendered dynamically via JS -->
              </div>
          </div>

          <div style="text-align:right; margin-top:30px;">
              <button class="pj-btn-add" style="margin-right:10px;" onclick="document.getElementById('modal_group').style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('group')"><i class="fas fa-check"></i> Confirmar</button>
          </div>
      </div>
  </div>

  <!-- MODAL CONEXION -->
  <div id="modal_connection" class="pj-modal-overlay" onclick="if(event.target===this){this.style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex';}">
      <div class="pj-modal" style="width: 500px;">
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
              <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;" id="conn_colors">
                  <?php $g_colors = ['#10b981','#3b82f6','#6366f1','#8b5cf6','#ec4899','#ef4444','#f97316','#f59e0b']; foreach ($g_colors as $c): ?>
                      <div class="conn-color-swatch" data-color="<?= $c ?>" style="width:28px; height:28px; border-radius:50%; background:<?= $c ?>; cursor:pointer; border:2px solid transparent; transition:transform 0.15s;" onclick="selectConnColor(this)"></div>
                  <?php endforeach; ?>
              </div>
              <input type="hidden" id="conn_color" value="#ec4899">
          </div>

          <div style="text-align:right; margin-top:30px;">
              <button class="pj-btn-add" style="margin-right:10px;" onclick="document.getElementById('modal_connection').style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('connection')"><i class="fas fa-check"></i> Confirmar</button>
          </div>
      </div>
  </div>

  <?php endif; ?>
