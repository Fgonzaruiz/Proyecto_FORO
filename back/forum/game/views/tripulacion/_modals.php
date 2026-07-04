<?php if ($is_captain): ?>
<!-- MODAL RELACION -->
<div id="modal_relacion" class="pj-modal-overlay" onclick="if(event.target===this){this.style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex';}">
    <div class="pj-modal">
        <div class="pj-modal-title" id="rel_modal_title">Añadir Relación Diplomática</div>
        <div class="form-group">
            <label class="pj-label-inline">
                <input type="checkbox" id="rel_is_npc" onchange="toggleRelNpc(this)">
                Es una Facción / Organización NPC
            </label>
        </div>
        <div class="form-group" id="rel_pj_box">
            <label>Grupo del Foro <span class="pj-label-hint">— empieza a escribir para buscar</span></label>
            <input type="text" id="rel_crew_search" class="textbox crew-form-input" placeholder="Buscar grupo..." autocomplete="off" oninput="searchCrew(this.value)">
            <select id="rel_crew_id" class="rpg-is-hidden">
                <option value="">Selecciona un grupo</option>
                <?php foreach($all_crews as $c): ?>
                <option value="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>" data-img="<?= htmlspecialchars($c['image_url'] ?: '') ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div id="rel_crew_results" class="pj-pj-results"></div>
        </div>
        <div class="form-group rpg-is-hidden" id="rel_npc_box">
            <label>Nombre de la Facción / Organización NPC</label>
            <input type="text" id="rel_npc_name" class="textbox crew-form-input" placeholder="Ej: Base de la Marina G-5">
        </div>
        <div class="form-group">
            <label>Descripción Diplomática</label>
            <input type="text" id="rel_desc" class="textbox crew-form-input" placeholder="Ej: Pacto comercial y alianza militar temporal...">
        </div>
        <div class="form-group">
            <label>Imagen de Jolly Roger / Bandera (URL)</label>
            <input type="url" id="rel_img" class="textbox crew-form-input" placeholder="https://i.imgur.com/...">
        </div>
        <div class="form-group">
            <label>Relación Diplomática (Elige hasta 3)</label>
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
                ¿Crear una línea de conexión explícita en la red diplomática?
            </label>
        </div>
        <div id="rel_conn_options" class="pj-conn-panel rpg-is-hidden">
            <p class="pj-conn-help">El origen será esta relación que estás creando/editando.</p>
            <div class="form-group">
                <label>Enlazar con (Destino)</label>
                <select id="rel_conn_target" class="textbox crew-form-input"></select>
            </div>
            <div class="form-group">
                <label>Vínculo (Ej: Tratado, Guerra)</label>
                <input type="text" id="rel_conn_label" class="textbox crew-form-input" placeholder="Aparecerá en la línea...">
            </div>
            <div class="form-group">
                <label>Color del Vínculo</label>
                <div class="pj-color-swatches" id="rel_conn_colors">
                    <?php $g_colors = ['#10b981','#3b82f6','#C62828','#8b5cf6','#ec4899','#ef4444','#f97316','#f59e0b']; foreach ($g_colors as $c): ?>
                        <div class="conn-color-swatch-rel pj-color-swatch" data-color="<?= $c ?>" onclick="selectConnColorRel(this)"></div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="rel_conn_color" value="#ec4899">
            </div>
        </div>
        
        <div class="pj-modal-footer-right">
            <button class="rpg-system-tab-btn" onclick="document.getElementById('modal_relacion').style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex'">Cancelar</button>
            <button class="rpg-action-btn rpg-btn-primary" onclick="saveCrewRelationsDraft('relacion')"><i class="fas fa-check"></i> Confirmar</button>
        </div>
    </div>
</div>

<!-- MODAL GESTIONAR RELACIONES Y GRUPOS (TABBED DASHBOARD) -->
<div id="modal_gestionar_relaciones" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
    <div class="pj-modal pj-modal--lg">
        <div class="pj-modal-title pj-modal-title--spaced">Diplomacia y Red de Alianzas</div>
        
        <div class="pj-modal-tabs">
            <button class="pj-modal-tab-btn active" onclick="switchRelTab('contactos',this)">Relaciones</button>
            <button class="pj-modal-tab-btn" onclick="switchRelTab('grupos',this)">Grupos y Flotas</button>
            <button class="pj-modal-tab-btn" onclick="switchRelTab('conexiones',this)">Conexiones Diplomáticas</button>
        </div>

        <div id="tab-contactos" class="pj-tab-content">
            <div class="pj-modal-toolbar">
                            <span class="pj-modal-toolbar-text">Administra tus alianzas y relaciones con otros grupos del foro o facciones.</span>
                <button class="rpg-system-tab-btn rpg-system-tab-btn--compact" onclick="openNewRelacion()"><i class="fas fa-handshake"></i> Añadir Relación</button>
            </div>
            <div id="contactos-list" class="pj-edit-list pj-edit-list--modal"></div>
        </div>

        <div id="tab-grupos" class="pj-tab-content is-hidden">
            <div class="pj-modal-toolbar">
                <span class="pj-modal-toolbar-text">Organiza a tus aliados en grupos (ej: Gran Flota, Enemigos del Nuevo Mundo).</span>
                <button class="rpg-system-tab-btn rpg-system-tab-btn--compact" onclick="openNewGroup()"><i class="fas fa-users"></i> Crear Grupo</button>
            </div>
            <div id="grupos-list" class="pj-edit-list pj-edit-list--modal"></div>
        </div>

        <div id="tab-conexiones" class="pj-tab-content is-hidden">
            <div class="pj-modal-toolbar">
                <span class="pj-modal-toolbar-text">Dibuja conexiones y pactos cruzados entre tus aliados en el mapa de red.</span>
                <button class="rpg-system-tab-btn rpg-system-tab-btn--compact" onclick="openNewConnection()"><i class="fas fa-link"></i> Añadir Vínculo</button>
            </div>
            <div id="conexiones-list" class="pj-edit-list pj-edit-list--modal"></div>
        </div>

        <div class="pj-modal-footer">
            <button class="rpg-system-tab-btn" onclick="document.getElementById('modal_gestionar_relaciones').style.display='none'">Cerrar</button>
            <button class="rpg-action-btn rpg-btn-primary" onclick="saveBatchCrewRelations()"><i class="fas fa-save"></i> Guardar Todo</button>
        </div>
    </div>
</div>

<!-- MODAL GRUPO -->
<div id="modal_group" class="pj-modal-overlay" onclick="if(event.target===this){this.style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex';}">
    <div class="pj-modal pj-modal--sm">
        <div class="pj-modal-title" id="group_modal_title">Crear Grupo Diplomático</div>
        
        <div class="form-group">
            <label>Nombre del Grupo / Coalición</label>
            <input type="text" id="grp_name" class="textbox crew-form-input" placeholder="Ej: Gran Flota de la Alianza, Rivales del Este, etc.">
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
            <button class="rpg-system-tab-btn" onclick="document.getElementById('modal_group').style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex'">Cancelar</button>
            <button class="rpg-action-btn rpg-btn-primary" onclick="saveCrewRelationsDraft('group')"><i class="fas fa-check"></i> Confirmar</button>
        </div>
    </div>
</div>

<!-- MODAL CONEXION -->
<div id="modal_connection" class="pj-modal-overlay" onclick="if(event.target===this){this.style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex';}">
    <div class="pj-modal pj-modal--sm">
        <div class="pj-modal-title" id="conn_modal_title">Crear Vínculo entre Relaciones</div>
        
        <div class="form-group">
            <label>Relación A</label>
            <select id="conn_source" class="textbox crew-form-input"></select>
        </div>

        <div class="form-group">
            <label>Relación B</label>
            <select id="conn_target" class="textbox crew-form-input"></select>
        </div>
        
        <div class="form-group">
            <label>Vínculo (Ej: Tratado, Guerra)</label>
            <input type="text" id="conn_label" class="textbox crew-form-input" placeholder="Aparecerá en la línea...">
        </div>
        
        <div class="form-group">
            <label>Color del Vínculo</label>
            <div class="pj-color-swatches" id="conn_colors">
                <?php $g_colors = ['#10b981','#3b82f6','#C62828','#8b5cf6','#ec4899','#ef4444','#f97316','#f59e0b']; foreach ($g_colors as $c): ?>
                    <div class="conn-color-swatch pj-color-swatch" data-color="<?= $c ?>" onclick="selectConnColor(this)"></div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="conn_color" value="#ec4899">
        </div>

        <div class="pj-modal-footer-right pj-modal-footer-right--lg">
            <button class="rpg-system-tab-btn" onclick="document.getElementById('modal_connection').style.display='none'; document.getElementById('modal_gestionar_relaciones').style.display='flex'">Cancelar</button>
            <button class="rpg-action-btn rpg-btn-primary" onclick="saveCrewRelationsDraft('connection')"><i class="fas fa-check"></i> Confirmar</button>
        </div>
    </div>
</div>

<!-- MODAL AÑADIR RECUERDO -->
<div id="modal_add_memory" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
    <div class="pj-modal pj-modal--sm">
        <div class="pj-modal-title">Añadir Recuerdo</div>
        <div class="form-group">
            <label>Título del Recuerdo</label>
            <input type="text" id="mem_add_title" class="textbox crew-form-input" placeholder="Ej: La misión en Yorknew">
        </div>
        <div class="form-group">
            <label>Imagen (URL)</label>
            <input type="url" id="mem_add_img" class="textbox crew-form-input" placeholder="https://i.imgur.com/...">
        </div>
        <div class="form-group">
            <label>Descripción / Texto</label>
            <textarea id="mem_add_text" class="textbox crew-form-input crew-form-textarea" rows="4" placeholder="Narra el recuerdo..."></textarea>
        </div>
        <div class="pj-modal-footer-right pj-modal-footer-right--lg">
            <button class="rpg-system-tab-btn" onclick="document.getElementById('modal_add_memory').style.display='none'">Cancelar</button>
            <button class="rpg-action-btn rpg-btn-primary" onclick="crewAddMemory()"><i class="fas fa-save"></i> Guardar</button>
        </div>
    </div>
</div>

<?php endif; ?>
