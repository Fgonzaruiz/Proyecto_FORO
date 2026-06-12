(function() {
    'use strict';
    var CFG = window.CREW_CONFIG || {};
    var selectedTags = new Set();
    var selectedCrewId = 0;
    var selectedCrewName = '';
    var editingEntryId = null;

    // ── TAB SWITCHING ──
    window.switchCrewTab = function(tabName, el) {
        document.querySelectorAll('.rpg-crew-immersive-page .pj-preview-tab-content')
            .forEach(function(t) { t.classList.remove('active'); });
        
        document.querySelectorAll('.rpg-crew-immersive-page .pj-preview-tab')
            .forEach(function(t) { t.classList.remove('active'); });
        
        var target = document.getElementById('crewTab_' + tabName);
        if (target) target.classList.add('active');
        if (el) el.classList.add('active');
        
        // Reinit map if switching to bio
        if (tabName === 'bio' && typeof window.reinitGameNetwork === 'function') {
            setTimeout(window.reinitGameNetwork, 100);
        }
    };

    // ── AJAX HELPER ──
    function crewAction(action, data, onSuccess) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('crew_id', CFG.crewId);
        for (var key in data) { fd.append(key, data[key]); }

        fetch(CFG.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.ok) {
                    showCrewToast(res.message, 'success');
                    if (onSuccess) onSuccess(res);
                } else {
                    showCrewToast(res.message || 'Error', 'error');
                }
            })
            .catch(function(e) { 
                console.error(e);
                showCrewToast('Error de conexión', 'error'); 
            });
    }

    // ── GESTIÓN: ACEPTAR ASPIRANTE ──
    window.crewAcceptMember = function(pjId, btnEl) {
        var cardEl = btnEl.closest('.crew-aspirant-card');
        crewAction('accept_member', { pj_id: pjId }, function() {
            if(cardEl) cardEl.remove();
            updateAspirantCount(-1);
            setTimeout(function(){ location.reload(); }, 1500);
        });
    };

    // ── GESTIÓN: RECHAZAR ASPIRANTE ──
    window.crewRejectMember = function(pjId, btnEl) {
        if (!confirm('¿Seguro que quieres rechazar esta solicitud?')) return;
        var cardEl = btnEl.closest('.crew-aspirant-card');
        crewAction('reject_member', { pj_id: pjId }, function() {
            if(cardEl) cardEl.remove();
            updateAspirantCount(-1);
        });
    };

    // ── GESTIÓN: EXPULSAR MIEMBRO ──
    window.crewKickMember = function(pjId, btnEl) {
        if (!confirm('¿Estás seguro de que quieres expulsar a este miembro de la tripulación?')) return;
        var rowEl = btnEl.closest('.crew-manage-member-row');
        crewAction('kick_member', { pj_id: pjId }, function() {
            if(rowEl) rowEl.remove();
        });
    };

    // ── GESTIÓN: ACTUALIZAR ROL CUSTOM ──
    window.crewUpdateRole = function(pjId, inputId) {
        var inputEl = document.getElementById(inputId);
        if(!inputEl) return;
        crewAction('update_role', { pj_id: pjId, role_custom: inputEl.value });
    };

    // ── GESTIÓN: GUARDAR DATOS DE CREW ──
    window.crewSaveInfo = function() {
        crewAction('update_crew', {
            name: document.getElementById('crew_edit_name').value,
            motto: document.getElementById('crew_edit_motto').value,
            factions: document.getElementById('crew_edit_factions').value,
            description: document.getElementById('crew_edit_desc').value,
            image_url: document.getElementById('crew_edit_img').value,
            relations: document.getElementById('crew_edit_relations').value,
            ost_url: document.getElementById('crew_edit_ost').value,
            ship_name: document.getElementById('crew_edit_ship_name').value,
            ship_image_url: document.getElementById('crew_edit_ship_img').value,
            ship_data: document.getElementById('crew_edit_ship_data').value
        });
    };

    // ── GESTIÓN RECUERDOS ──
    window.openAddMemoryModal = function() {
        document.getElementById('mem_add_title').value = '';
        document.getElementById('mem_add_img').value = '';
        document.getElementById('mem_add_text').value = '';
        document.getElementById('modal_add_memory').style.display = 'flex';
    };

    window.crewAddMemory = function() {
        var title = document.getElementById('mem_add_title').value.trim();
        var img = document.getElementById('mem_add_img').value.trim();
        var txt = document.getElementById('mem_add_text').value.trim();
        if (!title) {
            alert('El título es obligatorio.');
            return;
        }
        crewAction('add_memory', { title: title, image: img, text: txt }, function() {
            location.reload();
        });
    };

    window.crewDeleteMemory = function(idx, btn) {
        if (!confirm('¿Seguro que deseas borrar este recuerdo?')) return;
        crewAction('delete_memory', { index: idx }, function() {
            location.reload();
        });
    };

    // ── OST PLAYER ──
    window.toggleCrewOst = function(btn) {
        var audio = document.getElementById('crew-ost-audio');
        if (!audio) return;
        if (audio.paused) {
            audio.play();
            btn.innerHTML = '<i class="fas fa-pause"></i> Pausar';
            btn.classList.add('crew-ost-playing');
        } else {
            audio.pause();
            btn.innerHTML = '<i class="fas fa-play"></i> OST';
            btn.classList.remove('crew-ost-playing');
        }
    };

    // ── TOAST NOTIFICATION ──
    function showCrewToast(msg, type) {
        var t = document.createElement('div');
        t.className = 'rpg-toast rpg-toast--' + (type || 'info');
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(function() { t.classList.add('rpg-toast--visible'); }, 10);
        setTimeout(function() {
            t.classList.remove('rpg-toast--visible');
            setTimeout(function() { t.remove(); }, 300);
        }, 3000);
    }

    // ── HELPER: ACTUALIZAR CONTADOR ASPIRANTES ──
    function updateAspirantCount(delta) {
        var badge = document.querySelector('.crew-tab-notif');
        if (badge) {
            var n = parseInt(badge.textContent, 10) + delta;
            if (n <= 0) { badge.remove(); }
            else { badge.textContent = n; }
        }
    }

    // ── DIPLOMACY RELATIONS MAP & VIEWS ──
    window.switchCrewNetworkView = function(view) {
        var graphEl = document.getElementById('pj-view-graph');
        var listEl = document.getElementById('pj-view-list');
        var btnGraph = document.getElementById('btn-view-graph');
        var btnList = document.getElementById('btn-view-list');
        
        if (view === 'graph') {
            if(graphEl) graphEl.classList.remove('is-hidden');
            if(listEl) listEl.classList.add('is-hidden');
            if(btnGraph) btnGraph.classList.add('is-active');
            if(btnList) btnList.classList.remove('is-active');
            if (typeof window.reinitGameNetwork === 'function') {
                window.reinitGameNetwork();
            }
        } else {
            if(graphEl) graphEl.classList.add('is-hidden');
            if(listEl) listEl.classList.remove('is-hidden');
            if(btnGraph) btnGraph.classList.remove('is-active');
            if(btnList) btnList.classList.add('is-active');
        }
    };

    window.switchRelTab = function(tab, btn) {
        document.querySelectorAll('#modal_gestionar_relaciones .pj-tab-content').forEach(function(e) {
            e.classList.add('is-hidden');
        });
        document.querySelectorAll('#modal_gestionar_relaciones .pj-modal-tab-btn').forEach(function(e) {
            e.classList.remove('active');
        });
        var target = document.getElementById('tab-' + tab);
        if (target) target.classList.remove('is-hidden');
        if (btn) btn.classList.add('active');
    };

    window.toggleRelNpc = function(cb) {
        var pjBox = document.getElementById('rel_pj_box');
        var npcBox = document.getElementById('rel_npc_box');
        if (cb.checked) {
            if(pjBox) pjBox.classList.add('rpg-is-hidden');
            if(npcBox) npcBox.classList.remove('rpg-is-hidden');
        } else {
            if(pjBox) pjBox.classList.remove('rpg-is-hidden');
            if(npcBox) npcBox.classList.add('rpg-is-hidden');
        }
    };

    window.searchCrew = function(q) {
        var select = document.getElementById('rel_crew_id');
        var results = document.getElementById('rel_crew_results');
        if(!results || !select) return;
        results.innerHTML = '';
        if (!q || q.length < 1) return;
        
        var matches = [];
        for (var i = 0; i < select.options.length; i++) {
            var opt = select.options[i];
            if (!opt.value) continue;
            var name = opt.getAttribute('data-name') || opt.text;
            var img = opt.getAttribute('data-img') || '';
            if (name.toLowerCase().indexOf(q.toLowerCase()) !== -1) {
                matches.push({ id: opt.value, name: name, img: img });
                var chip = document.createElement('span');
                chip.className = 'pj-tag-option selected';
                chip.style.cssText = 'color:#3b82f6;background:#3b82f622;border-color:#3b82f6;';
                chip.textContent = name;
                chip.addEventListener('mousedown', function (e) { e.preventDefault(); });
                chip.onclick = (function(n, id, im) { 
                    return function() { selectCrew(id, n, im); }; 
                })(name, opt.value, img);
                results.appendChild(chip);
            }
        }
    };

    function selectCrew(id, name, imageUrl) {
        selectedCrewId = id;
        selectedCrewName = name;
        var searchInput = document.getElementById('rel_crew_search');
        var imgInput = document.getElementById('rel_img');
        if(searchInput) searchInput.value = name;
        if(imgInput) imgInput.value = imageUrl;
        var results = document.getElementById('rel_crew_results');
        if(results) results.innerHTML = '';
    }

    window.openCrewRelationsManager = function() {
        renderNetworkLists();
        document.getElementById('modal_gestionar_relaciones').style.display = 'flex';
    };

    window.openNewRelacion = function() {
        editingEntryId = null;
        document.getElementById('rel_modal_title').textContent = 'Añadir Relación';
        document.getElementById('rel_desc').value = '';
        document.getElementById('rel_img').value = '';
        var npcCb = document.getElementById('rel_is_npc');
        if(npcCb) {
            npcCb.checked = false;
            window.toggleRelNpc(npcCb);
        }
        var npcNameInput = document.getElementById('rel_npc_name');
        if(npcNameInput) npcNameInput.value = '';
        var searchInput = document.getElementById('rel_crew_search');
        if(searchInput) searchInput.value = '';
        
        selectedCrewId = 0; selectedCrewName = '';
        document.getElementById('rel_tags').value = '';
        selectedTags.clear();
        document.querySelectorAll('#rel_tag_picker .pj-tag').forEach(function(t) {
            t.classList.remove('active', 'selected');
            t.style.background = 'transparent';
            t.style.color = t.dataset.color;
        });
        
        var addConnCb = document.getElementById('rel_add_conn');
        if(addConnCb) {
            addConnCb.checked = false;
            document.getElementById('rel_conn_options').classList.add('rpg-is-hidden');
        }
        
        document.getElementById('modal_gestionar_relaciones').style.display = 'none';
        document.getElementById('modal_relacion').style.display = 'flex';
    };

    window.openNewGroup = function() {
        editingEntryId = null;
        document.getElementById('group_modal_title').textContent = 'Crear Grupo Diplomático';
        document.getElementById('grp_name').value = '';
        renderNetworkLists();
        document.querySelectorAll('input[name="grp_members[]"]').forEach(function(cb) { cb.checked = false; });
        document.getElementById('modal_gestionar_relaciones').style.display = 'none';
        document.getElementById('modal_group').style.display = 'flex';
    };

    window.openNewConnection = function() {
        editingEntryId = null;
        document.getElementById('conn_modal_title').textContent = 'Añadir Conexión Diplomática';
        document.getElementById('conn_label').value = '';
        renderNetworkLists();
        document.getElementById('conn_source').value = '';
        document.getElementById('conn_target').value = '';
        document.getElementById('modal_gestionar_relaciones').style.display = 'none';
        document.getElementById('modal_connection').style.display = 'flex';
    };

    window.selectConnColorRel = function(el) {
        document.querySelectorAll('.conn-color-swatch-rel').forEach(function(c) {
            c.style.transform = 'none'; c.style.borderColor = 'transparent';
        });
        el.style.transform = 'scale(1.2)';
        el.style.borderColor = '#fff';
        document.getElementById('rel_conn_color').value = el.dataset.color;
    };

    window.selectGroupColor = function(el) {
        document.querySelectorAll('.grp-color-swatch').forEach(function(c) {
            c.style.transform = 'none'; c.style.borderColor = 'transparent';
        });
        el.style.transform = 'scale(1.2)';
        el.style.borderColor = '#fff';
        document.getElementById('grp_color').value = el.dataset.color;
    };

    window.selectConnColor = function(el) {
        document.querySelectorAll('.conn-color-swatch').forEach(function(c) {
            c.style.transform = 'none'; c.style.borderColor = 'transparent';
        });
        el.style.transform = 'scale(1.2)';
        el.style.borderColor = '#fff';
        document.getElementById('conn_color').value = el.dataset.color;
    };

    // Tags picker click handlers
    document.querySelectorAll('#rel_tag_picker .pj-tag').forEach(function(el) {
        el.onclick = function() {
            var tag = el.dataset.tag;
            if (selectedTags.has(tag)) {
                selectedTags.delete(tag);
                el.classList.remove('active', 'selected');
                el.style.background = 'transparent';
                el.style.color = el.dataset.color;
            } else {
                if (selectedTags.size >= 3) {
                    alert("Elige hasta 3 etiquetas máximo.");
                    return;
                }
                selectedTags.add(tag);
                el.classList.add('active', 'selected');
                el.style.background = el.dataset.color;
                el.style.color = '#fff';
            }
            document.getElementById('rel_tags').value = Array.from(selectedTags).join(',');
        };
    });

    window.saveCrewRelationsDraft = function(type) {
        var payload = { type: type };
        if (type === 'relacion') {
            var is_npc = document.getElementById('rel_is_npc').checked;
            payload.is_npc = is_npc;
            if (is_npc) {
                payload.npc_name = document.getElementById('rel_npc_name').value;
                if (!payload.npc_name) { alert("El nombre es obligatorio."); return; }
            } else {
                payload.target_pj_id = selectedCrewId;
                payload.target_pj_name = selectedCrewName;
                if (!payload.target_pj_id) { alert("Busca y selecciona una tripulación."); return; }
            }
            payload.tags = Array.from(selectedTags);
            payload.desc = document.getElementById('rel_desc').value;
            payload.image = document.getElementById('rel_img').value;
            if (payload.tags.length === 0) { alert("Selecciona al menos una etiqueta."); return; }
        } else if (type === 'group') {
            payload.name = document.getElementById('grp_name').value;
            payload.color = document.getElementById('grp_color').value;
            var members = [];
            document.querySelectorAll('input[name="grp_members[]"]:checked').forEach(function(cb) {
                members.push(cb.value);
            });
            payload.members = members;
            if (!payload.name) { alert("El nombre del grupo es obligatorio."); return; }
            if (members.length < 2) { alert("Selecciona al menos 2 miembros."); return; }
        } else if (type === 'connection') {
            payload.source = document.getElementById('conn_source').value;
            payload.target = document.getElementById('conn_target').value;
            payload.label = document.getElementById('conn_label').value;
            payload.color = document.getElementById('conn_color').value;
            if (!payload.source || !payload.target) { alert("Selecciona Contacto A y Contacto B."); return; }
            if (payload.source === payload.target) { alert("Deben ser distintos."); return; }
            if (!payload.label) { alert("El nombre es obligatorio."); return; }
        }

        var newId = editingEntryId || ('temp_' + Math.random().toString(36).substr(2, 9));
        
        if (type === 'relacion') {
            var newRel = {
                id: newId,
                name: payload.is_npc ? payload.npc_name : payload.target_pj_name,
                is_npc: payload.is_npc,
                pj_id: payload.target_pj_id || 0,
                tags: payload.tags,
                desc: payload.desc,
                image: payload.image
            };
            var idx = window.draftNetworkData.relations.findIndex(function(r){ return r.id === newId; });
            if(idx > -1) window.draftNetworkData.relations[idx] = newRel;
            else window.draftNetworkData.relations.push(newRel);
            
            var addConnCb = document.getElementById('rel_add_conn');
            if (addConnCb && addConnCb.checked) {
                var cTarget = document.getElementById('rel_conn_target').value;
                var cLabel = document.getElementById('rel_conn_label').value;
                var cColor = document.getElementById('rel_conn_color').value;
                if (cTarget && cLabel) {
                    var targetName = '???';
                    var tgtObj = window.draftNetworkData.relations.find(function(x){ return x.id === cTarget; });
                    if(tgtObj) targetName = tgtObj.name;
                    var newConn = {
                        id: 'temp_' + Math.random().toString(36).substr(2, 9),
                        source: newId,
                        target: cTarget,
                        source_name: newRel.name,
                        target_name: targetName,
                        label: cLabel,
                        color: cColor
                    };
                    window.draftNetworkData.connections.push(newConn);
                }
            }
        } else if (type === 'group') {
            var newGrp = { id: newId, name: payload.name, color: payload.color, members: payload.members };
            var idx = window.draftNetworkData.groups.findIndex(function(g){ return g.id === newId; });
            if(idx > -1) window.draftNetworkData.groups[idx] = newGrp;
            else window.draftNetworkData.groups.push(newGrp);
        } else if (type === 'connection') {
            var sName='???', tName='???';
            var sObj = window.draftNetworkData.relations.find(function(x){ return x.id === payload.source; });
            var tObj = window.draftNetworkData.relations.find(function(x){ return x.id === payload.target; });
            if(sObj) sName = sObj.name;
            if(tObj) tName = tObj.name;
            var newConn = {
                id: newId,
                source: payload.source,
                target: payload.target,
                source_name: sName,
                target_name: tName,
                label: payload.label,
                color: payload.color
            };
            var idx = window.draftNetworkData.connections.findIndex(function(c){ return c.id === newId; });
            if(idx > -1) window.draftNetworkData.connections[idx] = newConn;
            else window.draftNetworkData.connections.push(newConn);
        }

        renderNetworkLists();
        if (typeof window.reinitGameNetwork === 'function') window.reinitGameNetwork();
        
        document.getElementById('modal_relacion').style.display = 'none';
        document.getElementById('modal_group').style.display = 'none';
        document.getElementById('modal_connection').style.display = 'none';
        document.getElementById('modal_gestionar_relaciones').style.display = 'flex';
    };

    window.editRelacionEntry = function(id, jsonStr) {
        var rel = JSON.parse(jsonStr);
        editingEntryId = id;
        document.getElementById('rel_modal_title').textContent = 'Editar Relación';
        
        var npcCb = document.getElementById('rel_is_npc');
        if (npcCb) {
            npcCb.checked = !!rel.is_npc;
            window.toggleRelNpc(npcCb);
        }
        
        var npcNameInput = document.getElementById('rel_npc_name');
        if (npcNameInput) npcNameInput.value = rel.is_npc ? (rel.name || '') : '';
        
        var searchInput = document.getElementById('rel_crew_search');
        if (searchInput) searchInput.value = rel.is_npc ? '' : (rel.name || '');
        
        selectedCrewId = rel.is_npc ? 0 : (rel.pj_id || 0);
        selectedCrewName = rel.is_npc ? '' : (rel.name || '');
        
        document.getElementById('rel_desc').value = rel.desc || '';
        document.getElementById('rel_img').value = rel.image || '';
        
        selectedTags.clear();
        (rel.tags || []).forEach(function(t) { selectedTags.add(t); });
        document.getElementById('rel_tags').value = Array.from(selectedTags).join(',');
        
        document.querySelectorAll('#rel_tag_picker .pj-tag').forEach(function(t) {
            var isSelected = selectedTags.has(t.dataset.tag);
            t.classList.toggle('active', isSelected);
            t.classList.toggle('selected', isSelected);
            if (isSelected) {
                t.style.background = t.dataset.color;
                t.style.color = '#fff';
            } else {
                t.style.background = 'transparent';
                t.style.color = t.dataset.color;
            }
        });
        
        var addConnCb = document.getElementById('rel_add_conn');
        if (addConnCb) {
            addConnCb.checked = false;
            document.getElementById('rel_conn_options').classList.add('rpg-is-hidden');
        }
        
        document.getElementById('modal_gestionar_relaciones').style.display = 'none';
        document.getElementById('modal_relacion').style.display = 'flex';
    };

    window.editGroupEntry = function(id, jsonStr) {
        var grp = JSON.parse(jsonStr);
        editingEntryId = id;
        document.getElementById('group_modal_title').textContent = 'Editar Grupo';
        document.getElementById('grp_name').value = grp.name || '';
        document.getElementById('grp_color').value = grp.color || '#C62828';
        
        renderNetworkLists();
        var members = grp.members || [];
        document.querySelectorAll('input[name="grp_members[]"]').forEach(function(cb) {
            cb.checked = members.indexOf(cb.value) !== -1;
        });
        
        document.getElementById('modal_gestionar_relaciones').style.display = 'none';
        document.getElementById('modal_group').style.display = 'flex';
    };

    window.editConnectionEntry = function(id, jsonStr) {
        var conn = JSON.parse(jsonStr);
        editingEntryId = id;
        document.getElementById('conn_modal_title').textContent = 'Editar Conexión';
        document.getElementById('conn_label').value = conn.label || '';
        
        renderNetworkLists();
        document.getElementById('conn_source').value = conn.source || '';
        document.getElementById('conn_target').value = conn.target || '';
        document.getElementById('conn_color').value = conn.color || '#ec4899';
        
        document.getElementById('modal_gestionar_relaciones').style.display = 'none';
        document.getElementById('modal_connection').style.display = 'flex';
    };

    window.deleteDraftEntry = function(type, id) {
        if (!confirm("¿Deseas quitar esta entrada de la lista de cambios?")) return;
        if (type === 'relacion') {
            window.draftNetworkData.relations = window.draftNetworkData.relations.filter(function(i) { return i.id !== id; });
        } else if (type === 'group') {
            window.draftNetworkData.groups = window.draftNetworkData.groups.filter(function(i) { return i.id !== id; });
        } else if (type === 'connection') {
            window.draftNetworkData.connections = window.draftNetworkData.connections.filter(function(i) { return i.id !== id; });
        }
        renderNetworkLists();
        if (typeof window.reinitGameNetwork === 'function') window.reinitGameNetwork();
    };

    window.saveBatchCrewRelations = function() {
        var relationsString = JSON.stringify(window.draftNetworkData);
        var hiddenInput = document.getElementById('crew_edit_relations');
        if (hiddenInput) hiddenInput.value = relationsString;
        
        crewAction('update_relations', { relations: relationsString }, function() {
            document.getElementById('modal_gestionar_relaciones').style.display = 'none';
            setTimeout(function(){ location.reload(); }, 1000);
        });
    };

    function renderNetworkLists() {
        var cList = document.getElementById('contactos-list');
        if (cList) {
            cList.innerHTML = '';
            (window.draftNetworkData.relations || []).forEach(function(r) {
                var d = document.createElement('div');
                d.className = 'pj-edit-item';
                var tagsHtml = (r.tags || []).map(function(t) { return '<span class="pj-relation-tag" data-tag-lbl="' + t + '">' + t + '</span>'; }).join('');
                d.innerHTML = 
                    '<div class="pj-edit-item-info">' +
                    '  <img src="' + (r.image || 'https://placehold.co/30x30?text=Jolly') + '" class="crew-manage-avatar-xs">' +
                    '  <div>' +
                    '    <strong>' + r.name + '</strong>' +
                    '    <div>' + tagsHtml + '</div>' +
                    '  </div>' +
                    '</div>' +
                    '<div class="pj-edit-item-actions">' +
                    '  <button class="rpg-system-tab-btn" onclick="editRelacionEntry(\'' + r.id + '\', \'' + escapeQuotes(JSON.stringify(r)) + '\')"><i class="fas fa-edit"></i></button>' +
                    '  <button class="rpg-system-tab-btn" onclick="deleteDraftEntry(\'relacion\', \'' + r.id + '\')"><i class="fas fa-trash"></i></button>' +
                    '</div>';
                
                // Color badges
                d.querySelectorAll('.pj-relation-tag').forEach(function(span) {
                    var tagVal = span.getAttribute('data-tag-lbl');
                    span.style.background = CFG.tagColors[tagVal] || '#C62828';
                    span.style.color = '#fff';
                    span.style.padding = '2px 6px';
                    span.style.fontSize = '9px';
                    span.style.borderRadius = '4px';
                    span.style.marginRight = '4px';
                    span.style.display = 'inline-block';
                });
                
                cList.appendChild(d);
            });
            if((window.draftNetworkData.relations || []).length === 0) {
                cList.innerHTML = '<p class="crew-manage-empty">No hay alianzas o relaciones registradas.</p>';
            }
        }

        var gList = document.getElementById('grupos-list');
        if (gList) {
            gList.innerHTML = '';
            (window.draftNetworkData.groups || []).forEach(function(g) {
                var d = document.createElement('div');
                d.className = 'pj-edit-item';
                d.innerHTML = 
                    '<div class="pj-edit-item-info">' +
                    '  <span class="pj-color-dot"></span>' +
                    '  <strong>' + g.name + '</strong> (' + (g.members || []).length + ' miembros)' +
                    '</div>' +
                    '<div class="pj-edit-item-actions">' +
                    '  <button class="rpg-system-tab-btn" onclick="editGroupEntry(\'' + g.id + '\', \'' + escapeQuotes(JSON.stringify(g)) + '\')"><i class="fas fa-edit"></i></button>' +
                    '  <button class="rpg-system-tab-btn" onclick="deleteDraftEntry(\'group\', \'' + g.id + '\')"><i class="fas fa-trash"></i></button>' +
                    '</div>';
                
                var dot = d.querySelector('.pj-color-dot');
                dot.style.background = g.color;
                dot.style.width = '12px';
                dot.style.height = '12px';
                dot.style.borderRadius = '50%';
                dot.style.display = 'inline-block';
                dot.style.marginRight = '10px';
                
                gList.appendChild(d);
            });
            if((window.draftNetworkData.groups || []).length === 0) {
                gList.innerHTML = '<p class="crew-manage-empty">No hay grupos creados.</p>';
            }
        }

        var connList = document.getElementById('conexiones-list');
        if (connList) {
            connList.innerHTML = '';
            (window.draftNetworkData.connections || []).forEach(function(c) {
                var d = document.createElement('div');
                d.className = 'pj-edit-item';
                d.innerHTML = 
                    '<div class="pj-edit-item-info">' +
                    '  <strong>' + c.source_name + '</strong> <i class="fas fa-long-arrow-alt-right"></i> <strong>' + c.target_name + '</strong>' +
                    '  <span class="pj-conn-label-span">[' + c.label + ']</span>' +
                    '</div>' +
                    '<div class="pj-edit-item-actions">' +
                    '  <button class="rpg-system-tab-btn" onclick="editConnectionEntry(\'' + c.id + '\', \'' + escapeQuotes(JSON.stringify(c)) + '\')"><i class="fas fa-edit"></i></button>' +
                    '  <button class="rpg-system-tab-btn" onclick="deleteDraftEntry(\'connection\', \'' + c.id + '\')"><i class="fas fa-trash"></i></button>' +
                    '</div>';
                
                var labelSpan = d.querySelector('.pj-conn-label-span');
                labelSpan.style.color = c.color;
                labelSpan.style.fontWeight = 'bold';
                labelSpan.style.fontSize = '11px';
                labelSpan.style.marginLeft = '10px';
                
                connList.appendChild(d);
            });
            if((window.draftNetworkData.connections || []).length === 0) {
                connList.innerHTML = '<p class="crew-manage-empty">No hay conexiones diplomáticas explícitas.</p>';
            }
        }

        var sourceSel = document.getElementById('conn_source');
        var targetSel = document.getElementById('conn_target');
        var relConnTargetSel = document.getElementById('rel_conn_target');
        var membersContainer = document.getElementById('grp_members_container');

        if(sourceSel) {
            sourceSel.innerHTML = '<option value="">Selecciona relación A</option>';
            targetSel.innerHTML = '<option value="">Selecciona relación B</option>';
            (window.draftNetworkData.relations || []).forEach(function(r) {
                var opt1 = new Option(r.name, r.id);
                var opt2 = new Option(r.name, r.id);
                sourceSel.add(opt1);
                targetSel.add(opt2);
            });
        }
        if (relConnTargetSel) {
            relConnTargetSel.innerHTML = '<option value="">Selecciona otra relación</option>';
            (window.draftNetworkData.relations || []).forEach(function(r) {
                relConnTargetSel.add(new Option(r.name, r.id));
            });
        }
        if (membersContainer) {
            membersContainer.innerHTML = '';
            (window.draftNetworkData.relations || []).forEach(function(r) {
                var div = document.createElement('div');
                div.className = 'pj-label-inline';
                div.innerHTML = '<input type="checkbox" name="grp_members[]" value="' + r.id + '"> ' + r.name;
                membersContainer.appendChild(div);
            });
            if((window.draftNetworkData.relations || []).length === 0) {
                membersContainer.innerHTML = '<p class="crew-manage-empty">Añade relaciones primero.</p>';
            }
        }
    }

    function escapeQuotes(str) {
        return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    window.openMemoryModal = function(mem) {
        document.getElementById('view_mem_img').src = mem.image || 'https://placehold.co/800x400/111/333?text=Recuerdo';
        document.getElementById('view_mem_title').textContent = mem.title || 'Recuerdo';
        document.getElementById('view_mem_text').innerHTML = (mem.text || '').replace(/\n/g, '<br>');
        document.getElementById('modal_ver_recuerdo').style.display = 'flex';
    };
})();
