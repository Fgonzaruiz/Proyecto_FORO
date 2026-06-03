<script>
console.log("=== DEBUG PERSONAJE.PHP ===");
console.log("Location:", window.location.href);
console.log("Is inside iframe:", window.self !== window.top);
console.log("Referrer:", document.referrer);
console.log("Frames count:", window.frames.length);
try {
    console.log("Top location:", window.top.location.href);
} catch(e) {
    console.log("Top location error (cross-origin blocked):", e.message);
}
try {
    console.log("Parent location:", window.parent.location.href);
} catch(e) {
    console.log("Parent location error (cross-origin blocked):", e.message);
}
console.log("===========================");

window.onerror = function(msg, url, lineNo, columnNo, error) {
    var errStr = 'Error: ' + msg + '\nURL: ' + url + '\nLine: ' + lineNo + '\nColumn: ' + columnNo + '\nError object: ' + JSON.stringify(error);
    var div = document.createElement('div');
    div.style.cssText = 'position:fixed;top:0;left:0;right:0;background:red;color:white;z-index:999999;padding:20px;white-space:pre-wrap;font-family:monospace;';
    div.innerText = errStr;
    document.body.appendChild(div);
    return false;
};
window.addEventListener("unhandledrejection", function(e) {
    var div = document.createElement('div');
    div.style.cssText = 'position:fixed;top:0;left:0;right:0;background:orange;color:white;z-index:999999;padding:20px;white-space:pre-wrap;font-family:monospace;';
    div.innerText = 'Unhandled Promise Rejection: ' + e.reason;
    document.body.appendChild(div);
});

// Helper for ES5 find compatibility
function findInArray(arr, fn) {
    if (!arr) return null;
    for (var i = 0; i < arr.length; i++) {
        if (fn(arr[i])) return arr[i];
    }
    return null;
}

var tagColors = <?= json_encode($tag_colors ?? [], JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
var catColors = <?= json_encode($cat_list_display ?? ['Pasado'=>'#8b5cf6','Presente'=>'#10b981','Mision'=>'#f59e0b','Evento'=>'#3b82f6','Trama'=>'#ef4444','Fic'=>'#ec4899','Off_Rol'=>'#6b7280'], JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
var seasonNames = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
window.__PJ_NETWORK_DATA = {
    relaciones: <?= json_encode(isset($char['cronologia']['relaciones']) ? $char['cronologia']['relaciones'] : [], JSON_UNESCAPED_UNICODE) ?>,
    groups: <?= json_encode(isset($char['cronologia']['groups']) ? $char['cronologia']['groups'] : [], JSON_UNESCAPED_UNICODE) ?>,
    connections: <?= json_encode(isset($char['cronologia']['connections']) ? $char['cronologia']['connections'] : [], JSON_UNESCAPED_UNICODE) ?>,
    diario: <?= json_encode(isset($char['cronologia']['diario']) ? $char['cronologia']['diario'] : [], JSON_UNESCAPED_UNICODE) ?>
};

window.draftNetworkData = {
    relaciones: [],
    groups: [],
    connections: [],
    diario: []
};
function initDraftData() {
    if (window.__PJ_NETWORK_DATA) {
        window.draftNetworkData = JSON.parse(JSON.stringify(window.__PJ_NETWORK_DATA));
        if(!window.draftNetworkData.diario) window.draftNetworkData.diario = [];
    }
}
initDraftData();

function renderNetworkLists() {
    var cList = document.getElementById('contactos-list');
    var gList = document.getElementById('grupos-list');
    var cnList = document.getElementById('conexiones-list');
    var dList = document.getElementById('diario-list');
    
    // Update options in modal_relacion and modal_connection
    var selTarget = document.getElementById('rel_conn_target');
    var selConnSource = document.getElementById('conn_source');
    var selConnTarget = document.getElementById('conn_target');
    var grpMembersContainer = document.getElementById('grp_members_container');
    
    var htmlOpts = '<option value="">Selecciona Contacto...</option>';
    var mHtml = '';
    
    if (window.draftNetworkData.relaciones && window.draftNetworkData.relaciones.length > 0) {
        window.draftNetworkData.relaciones.forEach(function(r) {
            htmlOpts += '<option value="'+r.id+'">'+escapeHtml(r.name)+'</option>';
            
            mHtml += '<label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:8px; border-radius:6px; transition:background 0.2s;" onmouseover="this.style.background=\'rgba(255,255,255,0.05)\'" onmouseout="this.style.background=\'transparent\'">';
            mHtml += '<input type="checkbox" name="grp_members[]" value="' + escapeHtml(r.id) + '" style="width:16px; height:16px;">';
            mHtml += '<img src="' + escapeHtml(r.image || 'https://placehold.co/24x24') + '" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">';
            mHtml += '<span style="font-size:13px; color:var(--text-primary); text-transform:none; letter-spacing:normal; font-weight:normal;">' + escapeHtml(r.name) + '</span>';
            mHtml += '</label>';
        });
    } else {
        mHtml = '<div style="font-size:12px; color:var(--text-muted); text-align:center; padding-top:20px;">No tienes contactos. Añade contactos primero.</div>';
    }
    
    if(selTarget) selTarget.innerHTML = htmlOpts;
    if(selConnSource) selConnSource.innerHTML = htmlOpts;
    if(selConnTarget) selConnTarget.innerHTML = htmlOpts;
    if(grpMembersContainer) grpMembersContainer.innerHTML = mHtml;
    
    // Render Diario
    if(dList) {
        if(window.draftNetworkData.diario.length === 0) {
            dList.innerHTML = '<p style="color:var(--text-muted); font-size:13px; text-align:center; padding: 20px 0;">No hay entradas en el diario.</p>';
        } else {
            var dHtml = '';
            window.draftNetworkData.diario.forEach(function(entry, index) {
                var sName = seasonNames[entry.season] || 'Desconocida';
                var fechaStr = "Día " + entry.day + " de " + sName + ", Año " + entry.year;
                var cc = catColors[entry.category] || '#6366f1';
                var shortDesc = entry.desc || '';
                if (shortDesc.length > 80) {
                    shortDesc = shortDesc.substring(0, 80) + '...';
                }
                
                dHtml += '<div class="pj-edit-item" data-category="'+entry.category+'" style="border-left: 4px solid '+cc+'; background: linear-gradient(to right, '+cc+'08, transparent); margin-bottom: 10px;">';
                dHtml += '<div class="pj-edit-item-body" style="padding-right:15px;">';
                dHtml += '<div style="display:flex; align-items:center; gap:8px; color:'+cc+';">';
                dHtml += '<span style="text-transform:uppercase; letter-spacing:1px; font-size:11px; font-weight:800;">'+escapeHtml(entry.category)+'</span>';
                dHtml += '<span style="color:var(--text-muted); font-size:12px; font-weight:600;">&bull; '+escapeHtml(fechaStr)+'</span>';
                dHtml += '</div>';
                if (entry.thread_name) {
                    dHtml += '<div style="margin-top:2px; font-size:12px; font-weight:700; color:var(--accent-indigo);">'+escapeHtml(entry.thread_name)+'</div>';
                }
                dHtml += '<div style="margin-top:6px; font-size:13px; line-height:1.4; color:var(--text-primary);">'+escapeHtml(shortDesc)+'</div>';
                if (entry.participants && entry.participants.length > 0) {
                    dHtml += '<div style="margin-top:4px; display:flex; flex-wrap:wrap; gap:3px;">';
                    entry.participants.forEach(function(p) {
                        dHtml += '<span style="font-size:10px; font-weight:600; color:var(--text-secondary); background:rgba(255,255,255,0.05); padding:1px 6px; border-radius:8px; border:1px solid var(--border-color);"><i class="fas fa-user"></i> '+escapeHtml(p.name||'?')+'</span>';
                    });
                    dHtml += '</div>';
                }
                dHtml += '</div>';
                dHtml += '<div class="pj-edit-item-actions">';
                dHtml += '<button class="pj-edit-btn pj-edit-btn-edit" title="Editar" data-index="'+index+'"><i class="fas fa-pen"></i></button>';
                dHtml += '<button class="pj-edit-btn pj-edit-btn-del" title="Eliminar" onclick="deleteDraftEntry(\'diario\', \''+entry.id+'\')"><i class="fas fa-trash"></i></button>';
                dHtml += '</div></div>';
            });
            dList.innerHTML = dHtml;
            
            // Attach event listeners for edit buttons
            var editBtns = dList.querySelectorAll('.pj-edit-btn-edit');
            editBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var idx = this.getAttribute('data-index');
                    var item = window.draftNetworkData.diario[idx];
                    editDiarioEntryDraftObj(item);
                });
            });
        }
    }

    // Render Contactos
    if(cList) {
        if(!window.draftNetworkData.relaciones || window.draftNetworkData.relaciones.length === 0) {
            cList.innerHTML = '<p style="color:var(--text-muted); font-size:13px; text-align:center; padding: 20px 0;">No hay relaciones registradas.</p>';
        } else {
            var cHtml = '';
            window.draftNetworkData.relaciones.forEach(function(rel) {
                var tagsHtml = '';
                var rtags = rel.tags || [];
                if(rtags.length === 0 && rel.relation) rtags = [rel.relation];
                rtags.forEach(function(t) {
                    if(!t) return;
                    var c = tagColors[t] || '#6366f1';
                    tagsHtml += '<span style="color:'+c+'; margin-right:10px; font-weight:600;">'+escapeHtml(t)+'</span>';
                });
                var jsonStr = JSON.stringify(rel).replace(/'/g, "&#39;").replace(/"/g, "&quot;");
                
                cHtml += '<div class="pj-edit-item" style="margin-bottom: 10px;">';
                cHtml += '<div style="display:flex; align-items:center; gap:15px; flex:1; min-width:0;">';
                cHtml += '<img src="'+escapeHtml(rel.image || 'https://placehold.co/40x40')+'" style="width:42px; height:42px; border-radius:50%; object-fit:cover; border: 2px solid rgba(255,255,255,0.1); flex-shrink:0;">';
                cHtml += '<div style="min-width:0;"><div style="font-size:14px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:8px;">'+escapeHtml(rel.name);
                if(rel.is_npc) cHtml += '<span style="font-size:9px; background:#f59e0b; color:#000; padding:1px 5px; border-radius:4px; font-weight:800; text-transform:uppercase;">NPC</span>';
                cHtml += '</div><div style="font-size:11px; margin-top:4px; display:flex; gap:6px; flex-wrap:wrap;">'+tagsHtml+'</div></div></div>';
                cHtml += '<div class="pj-edit-item-actions">';
                cHtml += '<button class="pj-edit-btn pj-edit-btn-edit" title="Editar" onclick="editRelacionEntryDraft(\''+jsonStr+'\')"><i class="fas fa-pen"></i></button>';
                cHtml += '<button class="pj-edit-btn pj-edit-btn-del" title="Eliminar" onclick="deleteDraftEntry(\'relacion\', \''+rel.id+'\')"><i class="fas fa-trash"></i></button>';
                cHtml += '</div></div>';
            });
            cList.innerHTML = cHtml;
        }
    }
    
    // Render Groups
    if(gList) {
        if(!window.draftNetworkData.groups || window.draftNetworkData.groups.length === 0) {
            gList.innerHTML = '<p style="color:var(--text-muted); font-size:13px; text-align:center; padding: 20px 0;">No hay grupos creados.</p>';
        } else {
            var gHtml = '';
            window.draftNetworkData.groups.forEach(function(grp) {
                var jsonStr = JSON.stringify(grp).replace(/'/g, "&#39;").replace(/"/g, "&quot;");
                gHtml += '<div class="pj-edit-item" style="margin-bottom: 10px; border-left: 4px solid '+grp.color+';">';
                gHtml += '<div style="display:flex; align-items:center; gap:12px; flex:1; min-width:0;">';
                gHtml += '<span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:'+grp.color+'; box-shadow: 0 0 8px '+grp.color+'; flex-shrink:0;"></span>';
                gHtml += '<div style="font-size:14px; font-weight:700; color:var(--text-primary); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">'+escapeHtml(grp.name)+'</div>';
                gHtml += '</div>';
                gHtml += '<div style="font-size:11px; color:var(--text-muted); font-weight:600; background:rgba(255,255,255,0.05); border-radius:8px; padding: 3px 8px; flex-shrink:0; margin-right:5px;">'+(grp.members?grp.members.length:0)+' miembros</div>';
                gHtml += '<div class="pj-edit-item-actions">';
                gHtml += '<button class="pj-edit-btn pj-edit-btn-edit" title="Editar" onclick="editGroupEntry(\''+grp.id+'\', \''+jsonStr+'\')"><i class="fas fa-pen"></i></button>';
                gHtml += '<button class="pj-edit-btn pj-edit-btn-del" title="Eliminar" onclick="deleteDraftEntry(\'group\', \''+grp.id+'\')"><i class="fas fa-trash"></i></button>';
                gHtml += '</div></div>';
            });
            gList.innerHTML = gHtml;
        }
    }
    
    // Render Connections
    if(cnList) {
        if(!window.draftNetworkData.connections || window.draftNetworkData.connections.length === 0) {
            cnList.innerHTML = '<p style="color:var(--text-muted); font-size:13px; text-align:center; padding: 20px 0;">No hay conexiones explícitas.</p>';
        } else {
            var cnHtml = '';
            window.draftNetworkData.connections.forEach(function(conn) {
                var jsonStr = JSON.stringify(conn).replace(/'/g, "&#39;").replace(/"/g, "&quot;");
                cnHtml += '<div class="pj-edit-item" style="margin-bottom: 10px;">';
                cnHtml += '<div style="display:flex; align-items:center; gap:12px; flex:1; min-width:0; font-size:13px;">';
                cnHtml += '<span style="font-weight:700; color:'+conn.color+'; background:'+conn.color+'15; border: 1px solid '+conn.color+'33; padding: 2px 8px; border-radius:6px; flex-shrink:0;">'+escapeHtml(conn.label)+'</span>';
                cnHtml += '<span style="color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><i class="fas fa-link" style="margin-right:6px; opacity:0.5;"></i>'+escapeHtml(conn.source_name||'ID:'+conn.source)+' <i class="fas fa-arrows-alt-h" style="margin:0 6px; opacity:0.5;"></i> '+escapeHtml(conn.target_name||'ID:'+conn.target)+'</span>';
                cnHtml += '</div>';
                cnHtml += '<div class="pj-edit-item-actions">';
                cnHtml += '<button class="pj-edit-btn pj-edit-btn-edit" title="Editar" onclick="editConnectionEntry(\''+conn.id+'\', \''+jsonStr+'\')"><i class="fas fa-pen"></i></button>';
                cnHtml += '<button class="pj-edit-btn pj-edit-btn-del" title="Eliminar" onclick="deleteDraftEntry(\'connection\', \''+conn.id+'\')"><i class="fas fa-trash"></i></button>';
                cnHtml += '</div></div>';
            });
            cnList.innerHTML = cnHtml;
        }
    }
}
document.addEventListener("DOMContentLoaded", renderNetworkLists);

function escapeHtml(text) {
    if(!text) return '';
    var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

function switchRelTab(tabName, el) {
    if (!el) el = event ? event.currentTarget : null;
    document.querySelectorAll('.pj-tab-content').forEach(function(e) {
        e.style.display = 'none';
    });
    document.getElementById('tab-' + tabName).style.display = 'block';
    document.querySelectorAll('.pj-modal-tab-btn').forEach(function(btn) {
        btn.classList.remove('active');
    });
    if (el) el.classList.add('active');
}



var selectedTags = new Set();
var selectedPjId = 0;
var selectedPjName = '';
var editingEntryId = null;

document.querySelectorAll('.pj-tag').forEach(function(el) {
    el.addEventListener('click', function() {
        var tag = this.dataset.tag;
        if (selectedTags.has(tag)) {
            selectedTags.delete(tag);
            this.classList.remove('selected');
            this.style.background = 'transparent';
            this.style.color = this.dataset.color;
        } else {
            if (selectedTags.size < 3) {
                selectedTags.add(tag);
                this.classList.add('selected');
                this.style.background = this.dataset.color;
                this.style.color = '#fff';
            }
        }
        updateTagsHidden();
    });
});

function updateTagsHidden() {
    var tagsArr = [];
    selectedTags.forEach(function(t) { tagsArr.push(t); });
    document.getElementById('rel_tags').value = JSON.stringify(tagsArr);
}

function toggleRelNpc(el) {
    document.getElementById('rel_npc_box').style.display = el.checked ? 'block' : 'none';
    document.getElementById('rel_pj_box').style.display = el.checked ? 'none' : 'block';
}

function searchPersonaje(q) {
    var select = document.getElementById('rel_pj_id');
    var results = document.getElementById('rel_pj_results');
    results.innerHTML = '';
    if (!q || q.length < 1) return;
    var found = false;
    for (var i = 0; i < select.options.length; i++) {
        var opt = select.options[i];
        if (!opt.value) continue;
        var name = opt.getAttribute('data-name') || opt.text;
        if (name.toLowerCase().indexOf(q.toLowerCase()) !== -1) {
            var chip = document.createElement('span');
            chip.className = 'pj-tag-option selected';
            chip.style.cssText = 'color:#3b82f6;background:#3b82f622;border-color:#3b82f6;';
            chip.textContent = name;
            chip.onclick = function(n, id) { return function() { selectPersonaje(id, n); }; }(name, opt.value);
            results.appendChild(chip);
            found = true;
        }
    }
}

function selectPersonaje(id, name) {
    selectedPjId = parseInt(id);
    selectedPjName = name;
    document.getElementById('rel_pj_search').value = name;
    document.getElementById('rel_pj_results').innerHTML = '';
}

function switchPjTab(tabId, tabEl) {
    document.querySelectorAll('.pj-preview-tab').forEach(function(t){ t.classList.remove('active'); });
    document.querySelectorAll('.pj-preview-tab-content').forEach(function(c){ c.classList.remove('active'); });
    tabEl.classList.add('active');
    var target = document.getElementById('pjTab_' + tabId);
    if (target) {
        target.classList.add('active');
    }
}

function editDiarioEntryDraftObj(item) {
    document.getElementById('diario_desc').value = item.desc || '';
    document.getElementById('diario_link').value = item.link || '';
    document.getElementById('diario_thread_id').value = item.thread_id || '';
    document.getElementById('diario_cat').value = item.category || 'Presente';
    document.getElementById('diario_day').value = item.day || '';
    document.getElementById('diario_season').value = item.season || 0;
    document.getElementById('diario_year').value = item.year || '';

    // Show detected data box if thread_id exists
    var detectedBox = document.getElementById('diario_auto_data');
    if (item.thread_id) {
        var seasonNames = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
        var sName = seasonNames[item.season] || 'Desconocida';
        document.getElementById('diario_detected_title').textContent = item.thread_name || 'Tema #' + item.thread_id;
        document.getElementById('diario_detected_cat').textContent = item.category === 'Off_Rol' ? 'Off Rol' : (item.category || 'Presente');
        document.getElementById('diario_detected_cat').style.color = catColors[item.category] || '#6366f1';
        document.getElementById('diario_detected_date').textContent = 'Día ' + (item.day || '?') + ' de ' + sName + ', Año ' + (item.year || '?');
        var partsHtml = '';
        if (item.participants && item.participants.length > 0) {
            partsHtml = item.participants.map(function(p) { return p.name; }).join(', ');
        } else {
            partsHtml = 'Sin datos de participantes';
        }
        document.getElementById('diario_detected_parts').textContent = partsHtml;
        detectedBox.style.display = 'block';
    } else {
        detectedBox.style.display = 'none';
    }

    editingEntryId = item.id;
    document.getElementById('modal_gestionar_diario').style.display = 'none';
    document.getElementById('modal_diario').style.display = 'flex';
}

function editDiarioEntryDraft(jsonStr) {
    var item = JSON.parse(jsonStr);
    editDiarioEntryDraftObj(item);
}

function editRelacionEntryDraft(jsonStr) {
    var item = JSON.parse(jsonStr);
    document.getElementById('rel_modal_title').textContent = 'Editar Contacto';
    // Populate dropdown options first
    renderNetworkLists();
    document.getElementById('rel_img').value = item.image || '';
    document.getElementById('rel_desc').value = item.desc || '';
    
    var isNpc = item.is_npc ? true : false;
    document.getElementById('rel_is_npc').checked = isNpc;
    toggleRelNpc(document.getElementById('rel_is_npc'));
    
    if (isNpc) {
        document.getElementById('rel_npc_name').value = item.name || '';
    } else {
        selectedPjId = item.pj_id || 0;
        selectedPjName = item.name || '';
        document.getElementById('rel_pj_search').value = item.name || '';
    }
    
    selectedTags.clear();
    document.querySelectorAll('.pj-tag').forEach(function(el) {
        el.classList.remove('selected');
        el.style.background = 'transparent';
        el.style.color = el.dataset.color;
    });
    
    var tags = item.tags || [];
    if(tags.length === 0 && item.relation) tags = [item.relation];
    
    document.querySelectorAll('.pj-tag').forEach(function(el) {
        if (tags.indexOf(el.dataset.tag) !== -1) {
            selectedTags.add(el.dataset.tag);
            el.classList.add('selected');
            el.style.background = el.dataset.color;
            el.style.color = '#fff';
        }
    });
    updateTagsHidden();
    editingEntryId = item.id;
    
    document.getElementById('rel_add_conn').checked = false;
    document.getElementById('rel_conn_options').style.display = 'none';
    
    document.getElementById('modal_gestionar_relaciones').style.display = 'none';
    document.getElementById('modal_relacion').style.display = 'flex';
}

function selectConnColorRel(el) {
    document.querySelectorAll('.conn-color-swatch-rel').forEach(function(c) {
        c.style.transform = 'none';
        c.style.borderColor = 'transparent';
    });
    el.style.transform = 'scale(1.2)';
    el.style.borderColor = '#fff';
    document.getElementById('rel_conn_color').value = el.dataset.color;
}

function selectGroupColor(el) {
    document.querySelectorAll('.grp-color-swatch').forEach(function(c) {
        c.style.transform = 'none';
        c.style.borderColor = 'transparent';
    });
    el.style.transform = 'scale(1.2)';
    el.style.borderColor = '#fff';
    document.getElementById('grp_color').value = el.dataset.color;
}

function selectConnColor(el) {
    document.querySelectorAll('.conn-color-swatch').forEach(function(c) {
        c.style.transform = 'none';
        c.style.borderColor = 'transparent';
    });
    el.style.transform = 'scale(1.2)';
    el.style.borderColor = '#fff';
    document.getElementById('conn_color').value = el.dataset.color;
}

function autoDetectThread(url) {
    var detectedBox = document.getElementById('diario_auto_data');
    if (!url) {
        detectedBox.style.display = 'none';
        return;
    }
    // Show loading state
    document.getElementById('diario_detected_title').textContent = 'Detectando...';
    detectedBox.style.display = 'block';

    fetch(AJAX_BASE + '/get_thread_diary_data.php?url=' + encodeURIComponent(url))
    .then(function(r) { return r.json(); })
    .then(function(resp) {
        if (resp.ok && resp.data) {
            var d = resp.data;
            var seasonNames = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
            var sName = seasonNames[d.season] || 'Desconocida';
            document.getElementById('diario_detected_title').textContent = d.thread_name;
            document.getElementById('diario_detected_cat').textContent = d.category === 'Off_Rol' ? 'Off Rol' : d.category;
            document.getElementById('diario_detected_cat').style.color = catColors[d.category] || '#6366f1';
            document.getElementById('diario_detected_date').textContent = 'Día ' + d.day + ' de ' + sName + ', Año ' + d.year;
            var partsHtml = '';
            if (d.participants && d.participants.length > 0) {
                partsHtml = d.participants.map(function(p) { return p.name; }).join(', ');
            } else {
                partsHtml = 'Solo tú (aún sin otros participantes)';
            }
            document.getElementById('diario_detected_parts').textContent = partsHtml;
            document.getElementById('diario_thread_id').value = d.thread_id;
            document.getElementById('diario_cat').value = d.category;
            document.getElementById('diario_day').value = d.day;
            document.getElementById('diario_season').value = d.season;
            document.getElementById('diario_year').value = d.year;
            detectedBox.style.display = 'block';
        } else {
            document.getElementById('diario_detected_title').textContent = 'No se pudo detectar el hilo.';
            document.getElementById('diario_detected_cat').textContent = '';
            document.getElementById('diario_detected_date').textContent = '';
            document.getElementById('diario_detected_parts').textContent = '';
            document.getElementById('diario_thread_id').value = '';
            document.getElementById('diario_cat').value = 'Presente';
            document.getElementById('diario_day').value = '';
            document.getElementById('diario_season').value = '';
            document.getElementById('diario_year').value = '';
        }
    })
    .catch(function() {
        document.getElementById('diario_detected_title').textContent = 'Error de conexión al detectar.';
        document.getElementById('diario_thread_id').value = '';
    });
}

function openNewDiario() {
    editingEntryId = null;
    document.getElementById('diario_desc').value = '';
    document.getElementById('diario_link').value = '';
    document.getElementById('diario_auto_data').style.display = 'none';
    document.getElementById('diario_thread_id').value = '';
    document.getElementById('diario_cat').value = 'Presente';
    document.getElementById('diario_day').value = '';
    document.getElementById('diario_season').value = '';
    document.getElementById('diario_year').value = '';
    document.getElementById('modal_gestionar_diario').style.display = 'none';
    document.getElementById('modal_diario').style.display = 'flex';
}

function openNewRelacion() {
    editingEntryId = null;
    document.getElementById('rel_modal_title').textContent = 'Añadir Contacto';
    document.getElementById('rel_desc').value = '';
    document.getElementById('rel_img').value = '';
    document.getElementById('rel_is_npc').checked = false;
    toggleRelNpc(document.getElementById('rel_is_npc'));
    document.getElementById('rel_npc_name').value = '';
    document.getElementById('rel_pj_search').value = '';
    selectedPjId = 0; selectedPjName = '';
    document.getElementById('rel_tags').value = '';
    selectedTags.clear();
    document.querySelectorAll('.pj-tag').forEach(function(t) { t.classList.remove('active', 'selected'); t.style.background='transparent'; t.style.color=t.dataset.color; });
    
    document.getElementById('rel_add_conn').checked = false;
    document.getElementById('rel_conn_options').style.display = 'none';
    
    document.getElementById('modal_gestionar_relaciones').style.display = 'none';
    document.getElementById('modal_relacion').style.display = 'flex';
}

function openNewGroup() {
    editingEntryId = null;
    document.getElementById('group_modal_title').textContent = 'Crear Grupo';
    document.getElementById('grp_name').value = '';
    // Populate checkboxes first
    renderNetworkLists();
    document.querySelectorAll('input[name="grp_members[]"]').forEach(function(cb) { cb.checked = false; });
    document.getElementById('modal_gestionar_relaciones').style.display = 'none';
    document.getElementById('modal_group').style.display = 'flex';
}

function openNewConnection() {
    editingEntryId = null;
    document.getElementById('conn_modal_title').textContent = 'Añadir Conexión Explícita';
    document.getElementById('conn_label').value = '';
    // Populate selects first
    renderNetworkLists();
    document.getElementById('conn_source').value = '';
    document.getElementById('conn_target').value = '';
    document.getElementById('modal_gestionar_relaciones').style.display = 'none';
    document.getElementById('modal_connection').style.display = 'flex';
}

function editGroupEntry(id, jsonStr) {
    try {
        var grp = JSON.parse(jsonStr);
        document.getElementById('group_modal_title').textContent = 'Editar Grupo';
        document.getElementById('grp_name').value = grp.name || '';
        
        var color = grp.color || '#6366f1';
        document.getElementById('grp_color').value = color;
        document.querySelectorAll('.grp-color-swatch').forEach(function(c) {
            if (c.dataset.color === color) {
                c.style.transform = 'scale(1.2)';
                c.style.borderColor = '#fff';
            } else {
                c.style.transform = 'none';
                c.style.borderColor = 'transparent';
            }
        });
        
        // Populate checkboxes first
        renderNetworkLists();
        var members = grp.members || [];
        document.querySelectorAll('input[name="grp_members[]"]').forEach(function(cb) {
            cb.checked = members.indexOf(cb.value) !== -1;
        });
        
        editingEntryId = id;
        document.getElementById('modal_gestionar_relaciones').style.display = 'none';
        document.getElementById('modal_group').style.display = 'flex';
    } catch (e) {
        console.error("Error parsing group JSON", e);
    }
}

function editConnectionEntry(id, jsonStr) {
    try {
        var conn = JSON.parse(jsonStr);
        document.getElementById('conn_modal_title').textContent = 'Editar Conexión';
        document.getElementById('conn_label').value = conn.label || '';
        // Populate first
        renderNetworkLists();
        document.getElementById('conn_source').value = conn.source || '';
        document.getElementById('conn_target').value = conn.target || '';
        
        var color = conn.color || '#ec4899';
        document.getElementById('conn_color').value = color;
        document.querySelectorAll('.conn-color-swatch').forEach(function(c) {
            if (c.dataset.color === color) {
                c.style.transform = 'scale(1.2)';
                c.style.borderColor = '#fff';
            } else {
                c.style.transform = 'none';
                c.style.borderColor = 'transparent';
            }
        });
        
        editingEntryId = id;
        document.getElementById('modal_gestionar_relaciones').style.display = 'none';
        document.getElementById('modal_connection').style.display = 'flex';
    } catch (e) {
        console.error("Error parsing connection JSON", e);
    }
}

function deleteDraftEntry(type, id) {
    if (type === 'relacion') {
        window.draftNetworkData.relaciones = window.draftNetworkData.relaciones.filter(function(i) { return i.id !== id; });
    } else if (type === 'group') {
        window.draftNetworkData.groups = window.draftNetworkData.groups.filter(function(i) { return i.id !== id; });
    } else if (type === 'connection') {
        window.draftNetworkData.connections = window.draftNetworkData.connections.filter(function(i) { return i.id !== id; });
    } else if (type === 'diario') {
        window.draftNetworkData.diario = window.draftNetworkData.diario.filter(function(i) { return i.id !== id; });
    }
    renderNetworkLists();
}

function deleteEntry(type, id) {
    if(type === 'relacion' || type === 'group' || type === 'connection' || type === 'diario') {
        deleteDraftEntry(type, id);
        return;
    }
    if (!confirm('¿Estás seguro de eliminar esta entrada?')) return;
    gameFetchPost('/update_cronologia.php', { pj_id: <?= (int)($char['id'] ?? 0) ?>, type: type, action: 'delete', entry_id: id })
    .then(function(data) {
        if (data.ok) { window.location.reload(); }
        else { alert('Error: ' + (data.error ? data.error.message : 'Desconocido')); }
    })
    .catch(function() { alert('Error de conexión.'); });
}

function selectDiaryCat(el) {
    document.querySelectorAll('.pj-cat-picker').forEach(function(c){ c.classList.remove('active'); });
    el.classList.add('active');
    document.getElementById('diario_cat').value = el.dataset.cat;
}

function openEditRelacion() {
    editingEntryId = null;
    document.getElementById('modal_gestionar_relaciones').style.display = 'flex';
}

function openEditDiario() {
    editingEntryId = null;
    renderNetworkLists();
    document.getElementById('modal_gestionar_diario').style.display = 'flex';
}

<?php if ($can_edit): ?>
var AJAX_BASE = '<?= rtrim($bb, '/') ?>/game/ajax';
window.__PJ_PROGRESSION = <?= json_encode($pj_progression, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function gameFetchPost(path, payload) {
    var url = (String(path).indexOf('http') === 0) ? path : (AJAX_BASE + (String(path).charAt(0) === '/' ? path : '/' + path));
    if (window.gamePostJson) {
        return window.gamePostJson(url, payload || {});
    }
    var body = payload || {};
    if (window.GAME_CSRF) {
        body.my_post_key = window.GAME_CSRF;
    }
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
        credentials: 'same-origin',
        body: JSON.stringify(body)
    }).then(function(r) { return r.json(); });
}

function updateProgressionUI(prog) {
    if (!prog) return;
    window.__PJ_PROGRESSION = prog;
    var ids = ['val_available_pp', 'val_available_pp_sub'];
    ids.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.textContent = prog.pp;
    });
    ['val_pj_nivel', 'val_pj_nivel_sub'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.textContent = prog.nivel;
    });
    document.querySelectorAll('.pj-stat-cost-label').forEach(function(el) {
        el.textContent = prog.stat_cost + ' PP';
    });
    var pendingBox = document.getElementById('pj_level_pending_box');
    var pendingVal = document.getElementById('val_pending_levels');
    if (pendingVal) pendingVal.textContent = prog.pending_levels;
    if (pendingBox) pendingBox.style.display = prog.pending_levels > 0 ? 'block' : 'none';
    var claimBtn = document.getElementById('btn_claim_level');
    if (claimBtn) claimBtn.style.display = (prog.pending_levels > 0 && prog.can_level_up_this_week) ? 'inline-flex' : 'none';
    var cooldownMsg = document.getElementById('pj_level_cooldown_msg');
    if (cooldownMsg) {
        if (prog.pending_levels > 0 && !prog.can_level_up_this_week && prog.next_level_available_iso) {
            var d = new Date(prog.next_level_available_iso);
            cooldownMsg.textContent = 'Próxima subida disponible: ' + d.toLocaleString('es-ES');
            cooldownMsg.style.display = 'block';
        } else {
            cooldownMsg.style.display = 'none';
        }
    }
}

function claimPendingLevel() {
    gameFetchPost('/claim_character_level.php', { character_id: <?= (int)$char['id'] ?> })
    .then(function(res) {
        if (res.ok) {
            var msg = '¡Has subido al nivel ' + res.data.nivel + '!';
            if (res.data.pending_levels > 0) {
                msg += ' Aún tienes ' + res.data.pending_levels + ' subida(s) pendiente(s).';
            }
            alert(msg);
            updateProgressionUI(res.data);
            window.location.reload();
        } else {
            alert('Error: ' + (res.error && res.error.message ? res.error.message : 'No se pudo subir de nivel.'));
        }
    })
    .catch(function() { alert('Error de conexión.'); });
}

function saveCronologia(type) {
    var payload = { pj_id: <?= (int)($char['id'] ?? 0) ?>, type: type };
    if (type === 'diario') {
        payload.link = document.getElementById('diario_link').value;
        payload.desc = document.getElementById('diario_desc').value;
        var tid = document.getElementById('diario_thread_id').value;
        if (tid) {
            payload.thread_id = parseInt(tid);
            payload.day = parseInt(document.getElementById('diario_day').value) || 1;
            payload.season = parseInt(document.getElementById('diario_season').value) || 0;
            payload.year = parseInt(document.getElementById('diario_year').value) || 1;
            payload.category = document.getElementById('diario_cat').value;
        } else {
            payload.day = 1;
            payload.season = 0;
            payload.year = 1;
            payload.category = 'Presente';
        }
        if(!payload.desc) { alert("La descripción es obligatoria."); return; }
    } else if (type === 'relacion') {
        var is_npc = document.getElementById('rel_is_npc').checked;
        payload.is_npc = is_npc;
        if (is_npc) {
            payload.npc_name = document.getElementById('rel_npc_name').value;
            if (!payload.npc_name) { alert("El nombre del NPC es obligatorio."); return; }
        } else {
            payload.target_pj_id = selectedPjId;
            payload.target_pj_name = selectedPjName;
            if (!payload.target_pj_id) { alert("Busca y selecciona un personaje de los resultados."); return; }
        }
        var tagsArr = [];
        selectedTags.forEach(function(t) { tagsArr.push(t); });
        payload.tags = tagsArr;
        payload.desc = document.getElementById('rel_desc').value;
        payload.image = document.getElementById('rel_img').value;
        if (payload.tags.length === 0) { alert("Selecciona al menos una etiqueta de relación."); return; }
    } else if (type === 'group') {
        payload.name = document.getElementById('grp_name').value;
        payload.color = document.getElementById('grp_color').value;
        var members = [];
        document.querySelectorAll('input[name="grp_members[]"]:checked').forEach(function(cb) {
            members.push(cb.value);
        });
        payload.members = members;
        if (!payload.name) { alert("El nombre del grupo es obligatorio."); return; }
        if (members.length < 2) { alert("Selecciona al menos 2 miembros para el grupo."); return; }
    } else if (type === 'connection') {
        payload.source = document.getElementById('conn_source').value;
        payload.target = document.getElementById('conn_target').value;
        payload.label = document.getElementById('conn_label').value;
        payload.color = document.getElementById('conn_color').value;
        
        if (!payload.source || !payload.target) { alert("Selecciona Contacto A y Contacto B."); return; }
        if (payload.source === payload.target) { alert("El Contacto A y el Contacto B no pueden ser el mismo."); return; }
        if (!payload.label) { alert("El nombre de la conexión es obligatorio."); return; }
    }

    if (editingEntryId) { payload.entry_id = editingEntryId; }
    
    // --- BATCH SAVE LOGIC FOR ALL NETWORK & DIARIO ARRAYS ---
    if (type === 'relacion' || type === 'group' || type === 'connection' || type === 'diario') {
        var newId = payload.entry_id || ('temp_' + Math.random().toString(36).substr(2, 9));
        
        if (type === 'diario') {
            var newDiario = {
                id: newId,
                day: payload.day,
                season: payload.season,
                year: payload.year,
                category: payload.category,
                desc: payload.desc,
                link: payload.link
            };
            if (payload.thread_id) {
                newDiario.thread_id = payload.thread_id;
                var tid = payload.thread_id;
                // If we have participants from auto-detect, copy them
                var detectedTitle = document.getElementById('diario_detected_title');
                if (detectedTitle && detectedTitle.textContent && detectedTitle.textContent.indexOf('No se pudo') === -1) {
                    newDiario.thread_name = detectedTitle.textContent;
                }
                var partsEl = document.getElementById('diario_detected_parts');
                if (partsEl && partsEl.textContent) {
                    var names = partsEl.textContent.split(', ').filter(function(n) { return n && n !== 'Solo tú (aún sin otros participantes)' && n !== 'Sin datos de participantes'; });
                    if (names.length > 0) {
                        newDiario.participants = names.map(function(n) { return { name: n }; });
                    }
                }
            }
            var idx = window.draftNetworkData.diario.findIndex(function(d){ return d.id === newId; });
            if(idx > -1) window.draftNetworkData.diario[idx] = newDiario;
            else window.draftNetworkData.diario.push(newDiario);
            
        } else if (type === 'relacion') {
            var newRel = {
                id: newId,
                name: payload.is_npc ? payload.npc_name : payload.target_pj_name,
                is_npc: payload.is_npc,
                pj_id: payload.target_pj_id || 0,
                tags: payload.tags,
                desc: payload.desc,
                image: payload.image
            };
            var idx = window.draftNetworkData.relaciones.findIndex(function(r){ return r.id === newId; });
            if(idx > -1) window.draftNetworkData.relaciones[idx] = newRel;
            else window.draftNetworkData.relaciones.push(newRel);
            
            // Check if we also want to add a connection
            if (document.getElementById('rel_add_conn') && document.getElementById('rel_add_conn').checked) {
                var cTarget = document.getElementById('rel_conn_target').value;
                var cLabel = document.getElementById('rel_conn_label').value;
                var cColor = document.getElementById('rel_conn_color').value;
                if (cTarget && cLabel) {
                    var targetName = '???';
                    var tgtObj = findInArray(window.draftNetworkData.relaciones, function(x){ return x.id === cTarget; });
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
            var sObj = findInArray(window.draftNetworkData.relaciones, function(x){ return x.id === payload.source; });
            var tObj = findInArray(window.draftNetworkData.relaciones, function(x){ return x.id === payload.target; });
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
        
        editingEntryId = null;
        if (type === 'diario') {
            document.getElementById('diario_desc').value = '';
            document.getElementById('diario_link').value = '';
            document.getElementById('modal_diario').style.display = 'none';
            document.getElementById('modal_gestionar_diario').style.display = 'flex';
        } else if (type === 'relacion') {
            document.getElementById('rel_desc').value = '';
            document.getElementById('rel_img').value = '';
            document.getElementById('rel_is_npc').checked = false;
            toggleRelNpc(document.getElementById('rel_is_npc'));
            document.getElementById('rel_npc_name').value = '';
            document.getElementById('rel_pj_search').value = '';
            document.getElementById('rel_tags').value = '';
            document.querySelectorAll('.pj-tag').forEach(function(t) { t.classList.remove('active'); });
            document.getElementById('modal_relacion').style.display = 'none';
            document.getElementById('modal_gestionar_relaciones').style.display = 'flex';
        } else if (type === 'group') {
            document.getElementById('grp_name').value = '';
            document.querySelectorAll('input[name="grp_members[]"]').forEach(function(cb) { cb.checked = false; });
            document.getElementById('modal_group').style.display = 'none';
            document.getElementById('modal_gestionar_relaciones').style.display = 'flex';
        } else if (type === 'connection') {
            document.getElementById('conn_label').value = '';
            document.getElementById('modal_connection').style.display = 'none';
            document.getElementById('modal_gestionar_relaciones').style.display = 'flex';
        }
        return;
    }
    // ---------------------------------------------
    
    gameFetchPost('/update_cronologia.php', payload)
    .then(function(data) {
        if (data.ok) { window.location.reload(); }
        else { alert('Error al guardar: ' + (data.error ? data.error.message : 'Desconocido')); }
    })
    .catch(function() { alert('Error de conexión.'); });
}

function saveBatchCronologia() {
    gameFetchPost('/update_cronologia.php', {
        pj_id: <?= (int)($char['id'] ?? 0) ?>,
        type: 'network_batch',
        data: window.draftNetworkData
    })
    .then(function(data) {
        if (data.ok) { window.location.reload(); }
        else { alert('Error al guardar: ' + (data.error ? data.error.message : 'Desconocido')); }
    })
    .catch(function() { alert('Error de conexión.'); });
}

// === RPG GESTION JAVASCRIPT ===
function switchGestionSubtab(subtabId) {
    // Hide dashboard panel
    var dbPanel = document.getElementById('gestion_dashboard');
    if (dbPanel) dbPanel.style.display = 'none';
    
    // Hide all subtab contents
    document.querySelectorAll('.gestion-subtab-content').forEach(function(e) {
        e.style.display = 'none';
    });
    
    // Show selected subtab content
    var target = document.getElementById('gestion_subtab_' + subtabId);
    if(target) target.style.display = 'block';
    
    if (subtabId === 'historial') {
        loadMyRequests();
    }
}

function showGestionDashboard() {
    // Hide all subtab contents
    document.querySelectorAll('.gestion-subtab-content').forEach(function(e) {
        e.style.display = 'none';
    });
    
    // Show dashboard panel
    var dbPanel = document.getElementById('gestion_dashboard');
    if (dbPanel) dbPanel.style.display = 'block';
    
    loadMyRequests();
}

function buyStatPoint(stat) {
    var prog = window.__PJ_PROGRESSION || {};
    var cost = prog.stat_cost || 5;
    if (!confirm('¿Comprar +1 en este atributo por ' + cost + ' PP?')) return;
    
    gameFetchPost('/purchase_attribute.php', { character_id: <?= (int)$char['id'] ?>, stat: stat, amount: 1 })
    .then(function(res) {
        if (res.ok) {
            var msg = '¡Atributo comprado!';
            if (res.data.levels_applied > 0) {
                msg += ' Has subido al nivel ' + res.data.nivel + '.';
            } else if (res.data.pending_levels > 0 && !res.data.can_level_up_this_week) {
                msg += ' Tienes ' + res.data.pending_levels + ' subida(s) de nivel pendiente(s) (máx. 1 por semana).';
            } else if (res.data.pending_levels > 0) {
                msg += ' Puedes aplicar una subida de nivel pendiente.';
            }
            alert(msg);
            window.location.reload();
        } else {
            alert('Error: ' + res.error.message);
        }
    })
    .catch(function() { alert('Error de conexión al comprar atributo.'); });
}

function submitCustomCardRequest() {
    var name = document.getElementById('req_new_name').value.trim();
    var type = document.getElementById('req_new_type').value;
    var desc = document.getElementById('req_new_desc').value.trim();
    
    if (name === '' || desc === '') {
        alert('Por favor, ingresa el nombre y la descripción para tu propuesta.');
        return;
    }
    
    gameFetchPost('/cards_request_custom.php', { character_id: <?= (int)$char['id'] ?>, type: 'create', card_name: name, card_type: type, description: desc })
    .then(function(res) {
        if (res.ok) {
            alert('Propuesta de carta enviada correctamente al staff.');
            document.getElementById('req_new_name').value = '';
            document.getElementById('req_new_desc').value = '';
            
            // Switch tab to historial
            switchGestionSubtab('historial');
        } else {
            alert('Error: ' + res.error.message);
        }
    })
    .catch(function() { alert('Error de conexión.'); });
}

function submitCatalogCardRequest() {
    var cardId = document.getElementById('req_existing_id').value;
    var note = document.getElementById('req_existing_note').value.trim();
    
    if (!cardId) {
        alert('Por favor, selecciona una carta del catálogo.');
        return;
    }
    
    gameFetchPost('/cards_request_custom.php', { character_id: <?= (int)$char['id'] ?>, type: 'add_existing', card_id: cardId, note: note })
    .then(function(res) {
        if (res.ok) {
            alert('Solicitud de adición de carta enviada correctamente.');
            document.getElementById('req_existing_id').value = '';
            document.getElementById('req_existing_note').value = '';
            
            // Switch tab to historial
            switchGestionSubtab('historial');
        } else {
            alert('Error: ' + res.error.message);
        }
    })
    .catch(function() { alert('Error de conexión.'); });
}

var currentRequestsList = [];
var activeReqId = null;

function loadMyRequests() {
    fetch(AJAX_BASE + '/cards_request_list_mine.php?character_id=<?= (int)$char['id'] ?>')
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) {
            currentRequestsList = res.data;
            renderMyRequestsList(res.data);
            
            var count = res.data.filter(function(r) { return r.status === 'pendiente' || r.status === 'conforme'; }).length;
            
            // Update badge count in history subtab header if present
            var badge = document.getElementById('requests-badge-count');
            if(badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
            
            // Update dashboard card badge count
            var dBadge = document.getElementById('dashboard-requests-badge');
            if(dBadge) {
                if (count > 0) {
                    dBadge.textContent = count + ' activa' + (count > 1 ? 's' : '');
                    dBadge.style.display = 'inline-block';
                } else {
                    dBadge.style.display = 'none';
                }
            }
        }
    });
}

function renderMyRequestsList(list) {
    var container = document.getElementById('my-requests-list-items');
    if(!container) return;
    if (list.length === 0) {
        container.innerHTML = '<div style="padding:40px 20px; color:var(--text-muted); text-align:center;"><i class="fas fa-check-circle" style="font-size:24px; color:var(--accent-emerald); display:block; margin-bottom:8px; opacity:0.6;"></i>No tienes solicitudes activas.</div>';
        return;
    }
    
    var html = '';
    list.forEach(function(req) {
        var statusLabel = req.status.toUpperCase();
        var statusColor = 'var(--text-muted)';
        if (req.status === 'aprobada') statusColor = '#10b981';
        else if (req.status === 'rechazada') statusColor = '#ef4444';
        else if (req.status === 'pendiente') statusColor = '#f59e0b';
        else if (req.status === 'conforme') statusColor = '#6366f1';
        
        var typeLabel = 'MEJORA';
        if (req.request_type === 'delete') typeLabel = 'BORRADO';
        else if (req.request_type === 'create') typeLabel = 'CREACI├ôN';
        else if (req.request_type === 'add_existing') typeLabel = 'ADICI├ôN';
        
        var isActive = (parseInt(req.id) === activeReqId) ? 'active' : '';
        
        html += '<div class="rpg-req-item ' + isActive + '" onclick="selectMyRequest(' + req.id + ')">';
        html += '  <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">';
        html += '    <strong style="font-size:12px; color:var(--text-primary); text-overflow:ellipsis; overflow:hidden; white-space:nowrap; max-width:140px;">' + escapeHtml(req.resolved_card_name) + '</strong>';
        html += '    <span style="font-size:9px; font-weight:800; color:' + statusColor + '; flex-shrink:0;">' + statusLabel + '</span>';
        html += '  </div>';
        html += '  <div style="font-size:10px; color:var(--text-muted); margin-top:4px;">Tipo: ' + typeLabel + ' &bull; ' + req.created_at.split(' ')[0] + '</div>';
        html += '</div>';
    });
    container.innerHTML = html;
}

function selectMyRequest(reqId) {
    activeReqId = parseInt(reqId);
    renderMyRequestsList(currentRequestsList);
    
    var req = findInArray(currentRequestsList, function(r) { return parseInt(r.id) === reqId; });
    var panel = document.getElementById('my-request-detail-panel');
    if (!req || !panel) return;
    
    var isPending = (req.status === 'pendiente');
    var isConforme = (req.status === 'conforme');
    
    var typeLabel = 'Mejora de Carta';
    if (req.request_type === 'delete') typeLabel = 'Borrado de Carta';
    else if (req.request_type === 'create') typeLabel = 'Creación de Carta';
    else if (req.request_type === 'add_existing') typeLabel = 'Adición de Carta';
    
    var html = '';
    html += '<div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:10px; margin-bottom:15px;">';
    html += '  <h3 style="margin:0; font-size:15px; color:var(--text-primary); font-family:var(--font-heading); font-weight:800;">' + typeLabel + ': ' + escapeHtml(req.resolved_card_name) + '</h3>';
    html += '  <span style="font-size:10px; font-weight:800; background:rgba(255,255,255,0.05); padding:3px 10px; border-radius:12px; color:var(--text-muted);">' + req.status.toUpperCase() + '</span>';
    html += '</div>';
    
    html += '<div style="display:flex; gap:15px; flex-wrap:wrap; flex:1; min-height:0;">';
    
    // Discussion Thread
    html += '  <div style="flex:1; display:flex; flex-direction:column; gap:10px; min-width:250px;">';
    html += '    <div class="rpg-chat-container">';
    html += '      <div class="rpg-chat-messages" id="rpg-chat-messages-container">';
    
    if (req.discussion && req.discussion.length > 0) {
        req.discussion.forEach(function(msg) {
            var bubbleClass = (msg.sender === 'player') ? 'player' : 'staff';
            var senderLabel = (msg.sender === 'player') ? 'T├Ü' : 'STAFF';
            var senderColor = (msg.sender === 'player') ? 'var(--accent-indigo)' : 'var(--accent-purple)';
            var msgTime = msg.timestamp ? msg.timestamp.split(' ')[1] : '';
            
            html += '        <div class="rpg-chat-bubble ' + bubbleClass + '">';
            html += '          <div class="rpg-chat-bubble-meta">';
            html += '            <span style="color:' + senderColor + '; font-weight:700;">' + escapeHtml(msg.sender_name) + ' (' + senderLabel + ')</span>';
            html += '            <span style="margin-left:10px;">' + escapeHtml(msgTime) + '</span>';
            html += '          </div>';
            html += '          <div style="white-space:pre-wrap;">' + escapeHtml(msg.message) + '</div>';
            html += '        </div>';
        });
    } else {
        html += '        <div style="padding:20px; color:var(--text-muted); text-align:center;">No hay mensajes en esta conversación.</div>';
    }
    
    html += '      </div>';
    
    // If pending, allow reply
    if (isPending) {
        html += '      <div class="rpg-chat-input-bar">';
        html += '        <input type="text" id="rpg-chat-reply-input" class="rpg-chat-input" placeholder="Escribe un mensaje para el staff...">';
        html += '        <button class="rpg-chat-send" onclick="replyToMyRequest(' + req.id + ')"><i class="fas fa-paper-plane"></i></button>';
        html += '      </div>';
    }
    
    html += '    </div>';
    
    // Actions panel
    if (isPending && req.request_type === 'create') {
        html += '    <div style="margin-top:10px; display:flex; gap:10px;">';
        html += '      <button class="pj-btn-add" style="flex:1; justify-content:center; background:linear-gradient(135deg, #10b981, #059669) !important; box-shadow: 0 4px 15px rgba(16,185,129,0.3) !important;" onclick="conformeMyRequest(' + req.id + ')"><i class="fas fa-check-double"></i> Estoy Conforme con la Carta</button>';
        html += '    </div>';
    }
    
    html += '  </div>';
    
    // Dynamic Moderated Card Preview (For custom card creations only)
    if (req.request_type === 'create' && req.card_details) {
        var card = req.card_details;
        var tagsHtml = '';
        if (card.tags && Array.isArray(card.tags)) {
            card.tags.forEach(function(t) {
                tagsHtml += '<span style="display:inline-block; font-size:8px; font-weight:700; padding:1px 6px; border:1px solid var(--border-color); border-radius:8px; color:var(--text-muted); text-transform:uppercase;">' + escapeHtml(t) + '</span>';
            });
        }
        
        var statRow = '';
        if ((card.cost_pe && card.cost_pe !== '—') || card.execution_stat || card.dice) {
            statRow = '<div style="display:flex; gap:8px; margin:10px 0; background:var(--bg-main); padding:6px 10px; border-radius:6px; border:1px solid var(--border-color); font-size:10px;">';
            if (card.cost_pe && card.cost_pe !== '—') statRow += '<div><span style="display:block; font-size:8px; color:var(--text-muted); font-weight:700;">PE</span><strong style="color:var(--text-primary);">' + escapeHtml(card.cost_pe) + '</strong></div>';
            if (card.execution_stat) statRow += '<div><span style="display:block; font-size:8px; color:var(--text-muted); font-weight:700;">STAT</span><strong style="color:var(--text-primary);">' + escapeHtml(card.execution_stat) + '</strong></div>';
            if (card.dice) statRow += '<div><span style="display:block; font-size:8px; color:var(--text-muted); font-weight:700;">DADOS</span><strong style="color:var(--text-primary);">' + escapeHtml(card.dice) + '</strong></div>';
            statRow += '</div>';
        }
        
        var cardImg = card.image_url ? '<div style="width:100%; height:90px; background-image:url(\'' + escapeHtml(card.image_url) + '\'); background-size:cover; background-position:center; border-radius:4px; margin-bottom:8px;"></div>' : '';
        
        html += '  <div style="display:flex; flex-direction:column; align-items:center; gap:8px; flex-shrink:0;">';
        html += '    <div style="font-size:10px; font-weight:800; color:var(--accent-indigo); text-transform:uppercase; letter-spacing:0.5px;">Carta Propuesta</div>';
        html += '    <div class="rpg-card-preview-mini">';
        html += '      <div style="padding:8px 12px; background:var(--bg-surface); border-bottom:1px solid var(--border-color); font-family:var(--font-heading);">';
        html += '        <div style="font-weight:900; color:var(--text-primary); font-size:12px;">' + escapeHtml(card.name) + '</div>';
        html += '        <div style="font-size:9px; color:var(--text-muted); text-transform:uppercase; margin-top:2px;">[' + escapeHtml(card.rank) + '] ' + escapeHtml(card.card_type.toUpperCase()) + '</div>';
        html += '      </div>';
        html += '      ' + cardImg;
        html += '      <div style="padding:10px;">';
        html += '        <div style="display:flex; gap:3px; flex-wrap:wrap; margin-bottom:8px;">' + tagsHtml + '</div>';
        html += '        ' + statRow;
        html += '        <div style="font-size:10px; color:var(--text-secondary); line-height:1.4; height:100px; overflow-y:auto; padding-right:3px; white-space:pre-wrap;">' + escapeHtml(card.description) + '</div>';
        html += '      </div>';
        html += '    </div>';
        html += '  </div>';
    }
    
    html += '</div>';
    
    panel.innerHTML = html;
    
    // Scroll chat to bottom
    setTimeout(function() {
        var chatBox = document.getElementById('rpg-chat-messages-container');
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    }, 50);
}

function replyToMyRequest(reqId) {
    var input = document.getElementById('rpg-chat-reply-input');
    var msg = input.value.trim();
    if (msg === '') return;
    
    gameFetchPost('/cards_request_reply.php', { request_id: reqId, message: msg })
    .then(function(res) {
        if (res.ok) {
            input.value = '';
            loadMyRequests();
            setTimeout(function() { selectMyRequest(reqId); }, 300);
        } else {
            alert('Error: ' + res.error.message);
        }
    })
    .catch(function() { alert('Error de conexión.'); });
}

function conformeMyRequest(reqId) {
    if (!confirm('¿Estás seguro de marcar esta propuesta como CONFORME? Una vez lo hagas, no podrás seguir enviando mensajes y quedará pendiente de que el staff la cree oficialmente.')) return;
    
    gameFetchPost('/cards_request_conforme.php', { request_id: reqId })
    .then(function(res) {
        if (res.ok) {
            alert('¡Has expresado tu conformidad con ├®xito! El staff procederá a la creación de la carta.');
            loadMyRequests();
            setTimeout(function() { selectMyRequest(reqId); }, 300);
        } else {
            alert('Error: ' + res.error.message);
        }
    })
    .catch(function() { alert('Error de conexión.'); });
}

// Auto-run list loading on DOM ready
document.addEventListener("DOMContentLoaded", function() {
    loadMyRequests();
});
// ==============================

<?php endif; ?>
</script>
