/**
 * Wizard crear/editar personaje
 * Config: window.CREAR_PERSONAJE_CONFIG
 */
(function () {
  "use strict";
  var cfg = window.CREAR_PERSONAJE_CONFIG || {};

function applyDataBg(container) {
    var root = container && container.querySelectorAll ? container : document;
    root.querySelectorAll('[data-bg]').forEach(function (el) {
        var url = el.getAttribute('data-bg');
        if (url) el.style.backgroundImage = "url('" + String(url).replace(/'/g, '%27') + "')";
    });
}

function applyDataColors(container) {
    var root = container && container.querySelectorAll ? container : document;
    root.querySelectorAll('[data-icon-color]').forEach(function (el) {
        var c = el.getAttribute('data-icon-color');
        if (c) el.style.color = c;
    });
    root.querySelectorAll('[data-badge-color]').forEach(function (el) {
        var c = el.getAttribute('data-badge-color');
        if (c) {
            el.style.background = c + '22';
            el.style.color = c;
        }
    });
}

function applyDataPct(container) {
    var root = container && container.querySelectorAll ? container : document;
    root.querySelectorAll('[data-pct]').forEach(function (el) {
        var pct = el.getAttribute('data-pct');
        if (pct != null && pct !== '') el.style.width = pct + '%';
    });
}

var facciones = {
    'Revolucionario':'Iniciado','Marine':'Raso','Gobierno':'Agente',
    'Cazador':'Sin Estrella','Civil':'Ciudadano','Pirata':'Grumete'
};
document.getElementById('pj_faction').addEventListener('change', function(e) {
    document.getElementById('pj_rank').value = facciones[e.target.value] || '';
});
function checkHibrido() {
    var hibBox = document.getElementById('hibrido_options');
    if (document.getElementById('pj_race').value === 'Hibrido') {
        hibBox.classList.add('is-visible');
    } else {
        hibBox.classList.remove('is-visible');
        document.getElementById('pj_race_dom').value = "";
        document.getElementById('pj_race_rec').value = "";
    }
}

// ==================== PASO 2 LOGIC ====================
var arqIcons = { 'Luchador':'fa-fist-raised','Espadachin':'fa-khanda','Tirador':'fa-crosshairs','Estratega':'fa-chess' };
function selectArq(arq, el) {
    document.querySelectorAll('.arq-box').forEach(function(b){ b.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('pj_arquetipo').value = arq;
}

// --- Stats ---
var ptsMax = 20;
var stats = { fue:0, agi:0, des:0, inst:0, esp:0, int:0 };
function getPtsUsed() { return stats.fue + stats.agi + stats.des + stats.inst + stats.esp + stats.int; }
function modStat(stat, val) {
    if (val > 0 && getPtsUsed() >= ptsMax) return;
    if (val < 0 && stats[stat] <= 0) return;
    if (stats[stat] + val > 10) return;
    stats[stat] += val;
    document.getElementById('val_' + stat).textContent = stats[stat];
    document.getElementById('pts_left').textContent = (ptsMax - getPtsUsed());
}

// ==================== LINAJE PERK SYSTEM ====================
var LINAJE_DATA = cfg.catalog || {};

// State
var selectedRacial = new Set();
var selectedGeneral = new Set();
var currentRace = '';
var currentRaceDom = '';
var currentRaceRec = '';
var maxLinajePoints = 4;

function enrichPerk(p) {
    if (!p) return p;
    if (p.icon && p.iconColor) return p;
    var icon = 'fa-dna';
    var iconColor = '#C62828';
    var id = p.id || '';
    if (id.startsWith('pp_')) { p.icon = 'fa-shield-alt'; p.iconColor = '#10b981'; return p; }
    if (id.startsWith('ps_')) { p.icon = 'fa-crown'; p.iconColor = '#f59e0b'; return p; }
    if (id.startsWith('g_linaje_fuego')) { icon = 'fa-fire'; iconColor = '#ef4444'; }
    else if (id.startsWith('g_linaje_rayo')) { icon = 'fa-bolt'; iconColor = '#eab308'; }
    else if (id.startsWith('g_linaje_hielo')) { icon = 'fa-snowflake'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_linaje_viento')) { icon = 'fa-wind'; iconColor = '#4A148C'; }
    else if (id.startsWith('g_linaje_tierra')) { icon = 'fa-mountain'; iconColor = '#b45309'; }
    else if (id.startsWith('g_linaje_agua')) { icon = 'fa-water'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_piel_acero')) { icon = 'fa-shield-alt'; iconColor = '#6b7280'; }
    else if (id.startsWith('g_vitalidad')) { icon = 'fa-heartbeat'; iconColor = '#ef4444'; }
    else if (id.startsWith('g_energia')) { icon = 'fa-bolt'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_constitucion')) { icon = 'fa-dumbbell'; iconColor = '#f43f5e'; }
    else if (id.startsWith('g_metabolismo')) { icon = 'fa-utensils'; iconColor = '#10b981'; }
    else if (id.startsWith('g_resistencia')) { icon = 'fa-hand-rock'; iconColor = '#f59e0b'; }
    else if (id.startsWith('g_regeneracion')) { icon = 'fa-leaf'; iconColor = '#10b981'; }
    else if (id.startsWith('g_mente') || id.startsWith('g_intelecto') || id.startsWith('g_lucidez') || id.startsWith('g_concentracion')) { icon = 'fa-brain'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_voluntad_ferrea')) { icon = 'fa-fingerprint'; iconColor = '#C62828'; }
    else if (id.startsWith('g_instinto')) { icon = 'fa-compass'; iconColor = '#8b5cf6'; }
    else if (id.startsWith('g_paso') || id.startsWith('g_sombra')) { icon = 'fa-user-ninja'; iconColor = '#475569'; }
    else if (id.startsWith('g_agilidad')) { icon = 'fa-running'; iconColor = '#10b981'; }
    else if (id.startsWith('g_evasion')) { icon = 'fa-wind'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_parkour')) { icon = 'fa-shoe-prints'; iconColor = '#f59e0b'; }
    else if (id.startsWith('g_haki_obs')) { icon = 'fa-eye'; iconColor = '#C62828'; }
    else if (id.startsWith('g_haki_arm')) { icon = 'fa-shield-alt'; iconColor = '#6b7280'; }
    else if (id.startsWith('g_haki_conq')) { icon = 'fa-crown'; iconColor = '#db2777'; }
    else if (id.startsWith('g_suerte') || id.startsWith('g_golpe') || id.startsWith('g_fortuna')) { icon = 'fa-dice-d20'; iconColor = '#f59e0b'; }
    else if (id.startsWith('g_carisma') || id.startsWith('g_presencia') || id.startsWith('g_inspiracion') || id.startsWith('g_nombre_temido') || id.startsWith('g_voz_rey')) { icon = 'fa-comments'; iconColor = '#ec4899'; }
    else if (id.startsWith('g_manos_') || id.startsWith('g_dedos_') || id.startsWith('g_ojo_') || id.startsWith('g_genio_') || id.startsWith('g_cocinero_')) { icon = 'fa-tools'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_cuatro_brazos')) { icon = 'fa-hand-paper'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_tercer_ojo')) { icon = 'fa-eye'; iconColor = '#4A148C'; }
    else if (id.startsWith('g_sangre_fria')) { icon = 'fa-snowflake'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_linaje_marino')) { icon = 'fa-anchor'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_gula')) { icon = 'fa-cookie-bite'; iconColor = '#b45309'; }
    else if (id.startsWith('g_pelo')) { icon = 'fa-magic'; iconColor = '#db2777'; }
    else if (id.startsWith('g_piel_color')) { icon = 'fa-palette'; iconColor = '#10b981'; }
    else if (id.startsWith('g_no_dormir')) { icon = 'fa-eye-slash'; iconColor = '#64748b'; }
    else if (id.startsWith('g_sangre_de_gigante')) { icon = 'fa-expand-arrows-alt'; iconColor = '#ef4444'; }
    else if (id.startsWith('g_cuerpo_elastico')) { icon = 'fa-dumbbell'; iconColor = '#10b981'; }
    else if (id.startsWith('rh_')) { icon = 'fa-user'; iconColor = '#C62828'; }
    else if (id.startsWith('rm_')) { icon = 'fa-paw'; iconColor = '#10b981'; }
    else if (id.startsWith('rg_')) { icon = 'fa-fish'; iconColor = '#06b6d4'; }
    else if (id.startsWith('rgi_')) { icon = 'fa-expand-arrows-alt'; iconColor = '#ef4444'; }
    else if (id.startsWith('rt_')) { icon = 'fa-seedling'; iconColor = '#10b981'; }
    else if (id.startsWith('rb_')) { icon = 'fa-anchor'; iconColor = '#f59e0b'; }
    else if (id.startsWith('rl_')) { icon = 'fa-feather-alt'; iconColor = '#ec4899'; }
    else if (id.startsWith('rs_')) { icon = 'fa-cloud'; iconColor = '#06b6d4'; }
    else if (id.startsWith('ro_')) { icon = 'fa-ghost'; iconColor = '#ef4444'; }
    else if (id.startsWith('rsi_')) { icon = 'fa-tint'; iconColor = '#3b82f6'; }
    p.icon = icon;
    p.iconColor = iconColor;
    return p;
}

function findPerkById(perkId) {
    if (LINAJE_DATA.arbol_general) {
        for (var catKey in LINAJE_DATA.arbol_general) {
            var cat = LINAJE_DATA.arbol_general[catKey];
            if (cat && cat.perks) {
                var found = cat.perks.find(function(item) { return item.id === perkId; });
                if (found) return { perk: enrichPerk(found), pool: 'general' };
            }
        }
    }
    if (LINAJE_DATA.arboles_raciales) {
        for (var race in LINAJE_DATA.arboles_raciales) {
            var tree = LINAJE_DATA.arboles_raciales[race];
            if (tree && tree.perks) {
                var found = tree.perks.find(function(item) { return item.id === perkId; });
                if (found) return { perk: enrichPerk(found), pool: 'racial', race: race };
            }
        }
    }
    if (LINAJE_DATA.pasivas_primarias) {
        for (var race in LINAJE_DATA.pasivas_primarias) {
            var list = LINAJE_DATA.pasivas_primarias[race] || [];
            var found = list.find(function(item) { return item.id === perkId; });
            if (found) return { perk: enrichPerk(found), pool: 'passive', type: 'primaria', race: race };
        }
    }
    if (LINAJE_DATA.pasivas_secundarias) {
        for (var race in LINAJE_DATA.pasivas_secundarias) {
            var list = LINAJE_DATA.pasivas_secundarias[race] || [];
            var found = list.find(function(item) { return item.id === perkId; });
            if (found) return { perk: enrichPerk(found), pool: 'passive', type: 'secundaria', race: race };
        }
    }
    return null;
}

function getSpentPoints() {
    var total = 0;
    selectedRacial.forEach(function(id) {
        var found = findPerkById(id);
        if (found) total += (found.perk.cost || 1);
    });
    selectedGeneral.forEach(function(id) {
        var found = findPerkById(id);
        if (found) total += (found.perk.cost || 1);
    });
    return total;
}

function getMaxLinajePoints() {
    if (currentRace === 'Hibrido') {
        var ptsDom = LINAJE_DATA.puntos_linaje_por_raza[currentRaceDom] || 20;
        return ptsDom - 4;
    } else {
        if (LINAJE_DATA.puntos_linaje_por_raza[currentRace]) {
            return LINAJE_DATA.puntos_linaje_por_raza[currentRace];
        }
    }
    return 4; // Default fallback
}

function buildLinajeTree() {
    currentRace = document.getElementById('pj_race').value || 'Humano';
    currentRaceDom = '';
    currentRaceRec = '';

    if (currentRace === 'Hibrido') {
        currentRaceDom = document.getElementById('pj_race_dom').value || 'Humano';
        currentRaceRec = document.getElementById('pj_race_rec').value || 'Humano';
    }

    maxLinajePoints = getMaxLinajePoints();

    // Apply edit prefill
    if (window.editLinajeSelected) {
        selectedRacial  = new Set(window.editLinajeSelected.racial  || []);
        selectedGeneral = new Set(window.editLinajeSelected.general || []);
        window.editLinajeSelected = null;
    } else {
        selectedRacial.clear();
        selectedGeneral.clear();
    }

    renderPerkGrids();
    updateSlotCounters();
}

function renderPerkGrids() {
    renderPasivas();
    renderRacial();
    renderGeneral();
}

function renderPasivas() {
    var grid = document.getElementById('gridPasivas');
    var html = '';
    
    if (currentRace === 'Hibrido') {
        var primDom = LINAJE_DATA.pasivas_primarias[currentRaceDom] || [];
        primDom.forEach(function(p) {
            var enriched = enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'primaria' });
            html += buildPerkCardHTML(enriched, 'passive', currentRaceDom);
        });
        var primRec = LINAJE_DATA.pasivas_primarias[currentRaceRec] || [];
        primRec.forEach(function(p) {
            var enriched = enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'primaria' });
            html += buildPerkCardHTML(enriched, 'passive', currentRaceRec);
        });
    } else {
        var prim = LINAJE_DATA.pasivas_primarias[currentRace] || [];
        prim.forEach(function(p) {
            var enriched = enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'primaria' });
            html += buildPerkCardHTML(enriched, 'passive', currentRace);
        });
        var sec = LINAJE_DATA.pasivas_secundarias[currentRace] || [];
        sec.forEach(function(p) {
            var enriched = enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'secundaria' });
            html += buildPerkCardHTML(enriched, 'passive', currentRace);
        });
    }

    if (!html) html = '<p class="rpg-wizard-text-muted">Esta raza no tiene pasivas registradas.</p>';
    grid.innerHTML = html;
    applyDataColors(grid);
}

function renderRacial() {
    var grid = document.getElementById('gridRacial');
    var html = '';
    var remaining = maxLinajePoints - getSpentPoints();

    if (currentRace === 'Hibrido') {
        var domTree = LINAJE_DATA.arboles_raciales[currentRaceDom];
        if (domTree && domTree.perks) {
            domTree.perks.forEach(function(p) {
                if (p.solo_puro === true) return;
                
                var isSelected = selectedRacial.has(p.id);
                var cost = p.cost || 1;
                var hasPrereq = true;
                if (p.requires) {
                    hasPrereq = selectedRacial.has(p.requires) || selectedGeneral.has(p.requires);
                }
                var isLocked = !isSelected && (cost > remaining || !hasPrereq);
                var state = isSelected ? 'selected' : (isLocked ? 'locked' : 'selectable');
                
                var enriched = enrichPerk(Object.assign({}, p));
                html += buildPerkCardHTML(enriched, state, currentRaceDom + ' (Dominante)', 'racial');
            });
        }
        var recTree = LINAJE_DATA.arboles_raciales[currentRaceRec];
        if (recTree && recTree.perks) {
            recTree.perks.forEach(function(p) {
                if (p.hibrido_accesible !== true || p.solo_puro === true) return;
                
                var isSelected = selectedRacial.has(p.id);
                var cost = p.cost || 1;
                var hasPrereq = true;
                if (p.requires) {
                    hasPrereq = selectedRacial.has(p.requires) || selectedGeneral.has(p.requires);
                }
                var isLocked = !isSelected && (cost > remaining || !hasPrereq);
                var state = isSelected ? 'selected' : (isLocked ? 'locked' : 'selectable');
                
                var enriched = enrichPerk(Object.assign({}, p));
                html += buildPerkCardHTML(enriched, state, currentRaceRec + ' (Recesiva)', 'racial');
            });
        }
    } else {
        var tree = LINAJE_DATA.arboles_raciales[currentRace];
        if (tree && tree.perks) {
            tree.perks.forEach(function(p) {
                var isSelected = selectedRacial.has(p.id);
                var cost = p.cost || 1;
                var hasPrereq = true;
                if (p.requires) {
                    hasPrereq = selectedRacial.has(p.requires) || selectedGeneral.has(p.requires);
                }
                var isLocked = !isSelected && (cost > remaining || !hasPrereq);
                var state = isSelected ? 'selected' : (isLocked ? 'locked' : 'selectable');
                
                var enriched = enrichPerk(Object.assign({}, p));
                html += buildPerkCardHTML(enriched, state, null, 'racial');
            });
        }
    }

    if (!html) html = '<p class="rpg-wizard-text-muted">No hay perks raciales disponibles.</p>';
    grid.innerHTML = html;
    applyDataColors(grid);
    attachPerkClick(grid, 'racial');
}

function renderGeneral() {
    var grid = document.getElementById('gridGeneral');
    var html = '';
    var remaining = maxLinajePoints - getSpentPoints();

    if (LINAJE_DATA.arbol_general) {
        for (var catKey in LINAJE_DATA.arbol_general) {
            var cat = LINAJE_DATA.arbol_general[catKey];
            if (cat && cat.perks) {
                cat.perks.forEach(function(p) {
                    if (currentRace === 'Hibrido' && p.solo_puro === true) return;
                    
                    var isSelected = selectedGeneral.has(p.id);
                    var cost = p.cost || 1;
                    var hasPrereq = true;
                    if (p.requires) {
                        hasPrereq = selectedRacial.has(p.requires) || selectedGeneral.has(p.requires);
                    }
                    var isLocked = !isSelected && (cost > remaining || !hasPrereq);
                    var state = isSelected ? 'selected' : (isLocked ? 'locked' : 'selectable');
                    
                    var enriched = enrichPerk(Object.assign({}, p));
                    html += buildPerkCardHTML(enriched, state, cat.nombre, 'general');
                });
            }
        }
    }
    grid.innerHTML = html;
    applyDataColors(grid);
    attachPerkClick(grid, 'general');
}

function buildPerkCardHTML(perk, state, raceLabel, poolType) {
    var cardClass = 'perk-card';
    var badgeHTML = '';
    var iconClass = 'perk-icon';
    var costBadge = '';

    if (state === 'passive') {
        var isPrimaria = perk.type === 'primaria';
        cardClass += isPrimaria ? ' perk-passive perk-passive-primary' : ' perk-passive perk-passive-secondary';
        iconClass += isPrimaria ? ' perk-icon--primary' : ' perk-icon--secondary';
        var badgeClass = isPrimaria ? 'perk-type-badge--primary' : 'perk-type-badge--secondary';
        var badgeLabel = isPrimaria ? 'PRIMARIA' : 'SECUNDARIA';
        if (raceLabel) badgeLabel = raceLabel.toUpperCase() + ' • ' + badgeLabel;
        badgeHTML = '<div class="perk-type-badge ' + badgeClass + '">' + badgeLabel + '</div>';
    } else {
        var cost = perk.cost || 1;
        if (state === 'selected') {
            cardClass += ' perk-selected';
            iconClass += poolType === 'racial' ? ' perk-icon--selected-racial' : ' perk-icon--selected-general';
            var badgeSel = poolType === 'racial' ? 'perk-type-badge--racial-selected' : 'perk-type-badge--general-selected';
            badgeHTML = '<div class="perk-type-badge ' + badgeSel + '">' + (poolType === 'racial' ? 'RACIAL' : 'GENERAL') + '</div>';
            costBadge = '<div class="perk-cost-badge perk-cost-badge--selected">' + cost + ' PTS</div>';
        } else if (state === 'locked') {
            cardClass += ' perk-locked';
            iconClass += ' perk-icon--locked';
            badgeHTML = '<div class="perk-type-badge perk-type-badge--locked">' + (poolType === 'racial' ? 'RACIAL' : 'GENERAL') + '</div>';
            costBadge = '<div class="perk-cost-badge perk-cost-badge--locked">' + cost + ' PTS</div>';
        } else {
            iconClass += ' perk-icon--selectable';
            var badgePool = poolType === 'racial' ? 'perk-type-badge--racial' : 'perk-type-badge--general';
            badgeHTML = '<div class="perk-type-badge ' + badgePool + '">' + (poolType === 'racial' ? 'RACIAL' : 'GENERAL') + '</div>';
            costBadge = '<div class="perk-cost-badge">' + cost + ' PTS</div>';
        }
    }

    return '<div class="' + cardClass + '" data-perk-id="' + perk.id + '" data-perk-name="' + escHtml(perk.name) + '" data-perk-desc="' + escHtml(perk.desc) + '" data-perk-type="' + (perk.type || poolType) + '">' +
        costBadge +
        '<div class="' + iconClass + '">' +
            '<i class="fas ' + perk.icon + '" data-icon-color="' + escHtml(perk.iconColor || '') + '"></i>' +
        '</div>' +
        '<div class="perk-info">' +
            '<div class="perk-name">' + perk.name + '</div>' +
            '<div class="perk-desc">' + perk.desc + '</div>' +
            badgeHTML +
        '</div>' +
    '</div>';
}

function escHtml(s) {
    return (s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function checkAndRemoveDependencies() {
    var changed = true;
    while (changed) {
        changed = false;
        selectedRacial.forEach(function(id) {
            var found = findPerkById(id);
            if (found && found.perk.requires) {
                if (!selectedRacial.has(found.perk.requires) && !selectedGeneral.has(found.perk.requires)) {
                    selectedRacial.delete(id);
                    changed = true;
                }
            }
        });
        selectedGeneral.forEach(function(id) {
            var found = findPerkById(id);
            if (found && found.perk.requires) {
                if (!selectedRacial.has(found.perk.requires) && !selectedGeneral.has(found.perk.requires)) {
                    selectedGeneral.delete(id);
                    changed = true;
                }
            }
        });
    }
}

function attachPerkClick(grid, poolType) {
    grid.querySelectorAll('.perk-card:not(.perk-passive):not(.perk-locked)').forEach(function(card) {
        card.addEventListener('click', function() {
            var id = card.getAttribute('data-perk-id');
            var pool = (poolType === 'racial') ? selectedRacial : selectedGeneral;
            var found = findPerkById(id);
            if (!found) return;
            var cost = found.perk.cost || 1;

            if (pool.has(id)) {
                pool.delete(id);
                checkAndRemoveDependencies();
            } else {
                var spent = getSpentPoints();
                if (spent + cost > maxLinajePoints) {
                    return;
                }
                pool.add(id);
                // shimmer effect
                card.classList.add('shimmer');
                setTimeout(function(){ card.classList.remove('shimmer'); }, 700);
            }

            renderRacial();
            renderGeneral();
            updateSlotCounters();
        });
    });
}

function attachPerkHover(grid) {
    // No-op, descriptions are now embedded directly and permanently visible in the cards!
}

function updateSlotCounters() {
    var spent = getSpentPoints();
    var max = maxLinajePoints;
    var usedPointsEl = document.getElementById('usedPoints');
    var maxPointsEl = document.getElementById('maxPoints');
    if (usedPointsEl) usedPointsEl.textContent = spent;
    if (maxPointsEl) maxPointsEl.textContent = max;

    // Dot counters
    var container = document.getElementById('linajeDots');
    if (container) {
        container.innerHTML = '';
        for (var i = 0; i < max; i++) {
            var d = document.createElement('div');
            d.className = 'linaje-slot-dot' + (i < spent ? ' filled' : '');
            container.appendChild(d);
        }
    }

    // Sobrante & PP Bonus
    var sobrante = max - spent;
    var bonusPP = sobrante * 3;
    var sobranteEl = document.getElementById('sobrantePoints');
    var bonusPPEl = document.getElementById('bonusPP');
    if (sobranteEl) sobranteEl.textContent = sobrante;
    if (bonusPPEl) bonusPPEl.textContent = bonusPP;
}

// ==================== PREVIEW TABS ====================
function switchPreviewTab(tabId, tabEl) {
    document.querySelectorAll('.preview-tab').forEach(function(t){ t.classList.remove('active'); });
    document.querySelectorAll('.preview-tab-content').forEach(function(c){ c.classList.remove('active'); });
    tabEl.classList.add('active');
    document.getElementById('previewTab_' + tabId).classList.add('active');
}

// ==================== NAVIGATION ====================
var pjData = {};

function goToStep(step) {
    if (step === 2) {
        if (!document.getElementById('pj_name').value.trim() || !document.getElementById('pj_faction').value || !document.getElementById('pj_race').value) {
            alert("Nombre, Facción y Raza son campos obligatorios."); return;
        }
        if (document.getElementById('pj_race').value === 'Hibrido') {
            if (!document.getElementById('pj_race_dom').value || !document.getElementById('pj_race_rec').value) {
                alert("Si eres híbrido debes seleccionar raza dominante y recesiva."); return;
            }
        }
        buildLinajeTree();
    }
    if (step === 3) {
        if (!document.getElementById('pj_arquetipo').value) {
            alert("Debes seleccionar un Arquetipo Bélico."); return;
        }
        if (getPtsUsed() < ptsMax) {
            if (!confirm('Aún tienes ' + (ptsMax - getPtsUsed()) + ' puntos libres sin gastar. ¿Continuar?')) return;
        }
        generarPreviewJSON();
    }
    document.querySelectorAll('.wizard-step-content').forEach(function(el){ el.style.display = 'none'; });
    document.getElementById('step-' + step).style.display = 'block';
    document.querySelectorAll('.wizard-step-marker').forEach(function(el, i) {
        el.classList.remove('active', 'completed');
        if (i + 1 < step) el.classList.add('completed');
        if (i + 1 === step) el.classList.add('active');
    });
    window.scrollTo(0, 0);
}

function generarPreviewJSON() {
    var raceFinal = document.getElementById('pj_race').value;
    if (raceFinal === 'Hibrido') {
        raceFinal = 'Híbrido (' + document.getElementById('pj_race_dom').value + ' / ' + document.getElementById('pj_race_rec').value + ')';
    }

    var race = document.getElementById('pj_race').value;
    var races = (race === 'Hibrido')
        ? [document.getElementById('pj_race_dom').value, document.getElementById('pj_race_rec').value]
        : [race];

    var pasivasData = [];
    if (race === 'Hibrido') {
        var domPrim = LINAJE_DATA.pasivas_primarias[document.getElementById('pj_race_dom').value] || [];
        domPrim.forEach(function(p) {
            pasivasData.push(enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'primaria' }));
        });
        var recPrim = LINAJE_DATA.pasivas_primarias[document.getElementById('pj_race_rec').value] || [];
        recPrim.forEach(function(p) {
            pasivasData.push(enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'primaria' }));
        });
    } else {
        var prim = LINAJE_DATA.pasivas_primarias[race] || [];
        prim.forEach(function(p) {
            pasivasData.push(enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'primaria' }));
        });
        var sec = LINAJE_DATA.pasivas_secundarias[race] || [];
        sec.forEach(function(p) {
            pasivasData.push(enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'secundaria' }));
        });
    }

    var racialData = [];
    if (race === 'Hibrido') {
        var rDom = document.getElementById('pj_race_dom').value;
        var rRec = document.getElementById('pj_race_rec').value;
        var domTree = LINAJE_DATA.arboles_raciales[rDom];
        if (domTree && domTree.perks) {
            domTree.perks.forEach(function(p) {
                if (selectedRacial.has(p.id)) racialData.push(enrichPerk(Object.assign({}, p)));
            });
        }
        var recTree = LINAJE_DATA.arboles_raciales[rRec];
        if (recTree && recTree.perks) {
            recTree.perks.forEach(function(p) {
                if (selectedRacial.has(p.id)) racialData.push(enrichPerk(Object.assign({}, p)));
            });
        }
    } else {
        var tree = LINAJE_DATA.arboles_raciales[race];
        if (tree && tree.perks) {
            tree.perks.forEach(function(p) {
                if (selectedRacial.has(p.id)) racialData.push(enrichPerk(Object.assign({}, p)));
            });
        }
    }

    var generalData = [];
    if (LINAJE_DATA.arbol_general) {
        for (var catKey in LINAJE_DATA.arbol_general) {
            var cat = LINAJE_DATA.arbol_general[catKey];
            if (cat && cat.perks) {
                cat.perks.forEach(function(p) {
                    if (selectedGeneral.has(p.id)) generalData.push(enrichPerk(Object.assign({}, p)));
                });
            }
        }
    }

    var allNames = pasivasData.map(function(p){ return p.name; })
        .concat(racialData.map(function(p){ return p.name; }))
        .concat(generalData.map(function(p){ return p.name; }));

    pjData = {
        pj_id: cfg.editPjId || 0,
        name: document.getElementById('pj_name').value.trim(),
        avatar: document.getElementById('pj_avatar').value.trim() || 'https://placehold.co/320x450',
        faction: document.getElementById('pj_faction').value,
        rank: document.getElementById('pj_rank').value,
        race: raceFinal,
        age: document.getElementById('pj_age').value.trim() || 'Desconocida',
        origin: document.getElementById('pj_origin').value.trim() || 'Desconocido',
        pb: document.getElementById('pj_pb').value.trim() || 'Ninguno',
        physique: document.getElementById('pj_physique').value.trim() || 'Sin registrar.',
        psychology: document.getElementById('pj_psychology').value.trim() || 'Sin registrar.',
        extras: document.getElementById('pj_extras').value.trim() || 'Sin notas.',
        arquetipo: document.getElementById('pj_arquetipo').value,
        job: document.getElementById('pj_job').value,
        stats: JSON.parse(JSON.stringify(stats)),
        linaje: {
            pasivas: pasivasData.map(function(p){ return p.id; }),
            elegidos_racial:  Array.from(selectedRacial),
            elegidos_general: Array.from(selectedGeneral),
            maxPoints: maxLinajePoints,
            usedPoints: getSpentPoints(),
            sobrantePoints: maxLinajePoints - getSpentPoints(),
            bonusPP: (maxLinajePoints - getSpentPoints()) * 3,
            maxSlotsRacial:  2,
            maxSlotsGeneral: 2,
            geneNames: allNames,
            version: 2
        }
    };

    document.getElementById('preview_name').textContent = pjData.name;
    var avatarEl = document.getElementById('preview_avatar');
    avatarEl.setAttribute('data-bg', pjData.avatar);
    applyDataBg(avatarEl);
    document.getElementById('preview_faction').innerHTML = '<i class="fas fa-flag"></i> ' + pjData.faction;
    document.getElementById('preview_rank').innerHTML = '<i class="fas fa-medal"></i> ' + pjData.rank;
    document.getElementById('preview_age').textContent = pjData.age;
    document.getElementById('preview_origin').textContent = pjData.origin;
    document.getElementById('preview_race').textContent = pjData.race;
    document.getElementById('preview_pb').textContent = pjData.pb;
    document.getElementById('preview_physique').textContent = pjData.physique;
    document.getElementById('preview_psychology').textContent = pjData.psychology;
    document.getElementById('preview_extras').textContent = pjData.extras;
    document.getElementById('preview_arq_name').textContent = pjData.arquetipo;
    document.getElementById('preview_arq_icon').className = "fas " + (arqIcons[pjData.arquetipo] || 'fa-shield-alt');
    document.getElementById('preview_job').textContent = pjData.job;
    document.getElementById('preview_genes').textContent = allNames.length ? allNames.join(', ') : 'Ninguno';

    var f = stats.fue || 0;
    var a = stats.agi || 0;
    var d = stats.des || 0;
    var inst = stats.inst || 0;
    var e = stats.esp || 0;
    var it = stats.int || 0;

    var pv = (f * 4) + (a * 2) + (e * 3) + (it * 1);
    var pe = (e * 4) + (d * 3) + (a * 2) + (it * 1);

    document.getElementById('preview_pv').textContent = pv;
    document.getElementById('preview_pe').textContent = pe;

    ['fue','agi','des','inst','esp','int'].forEach(function(s) {
        document.getElementById('pbar_' + s + '_txt').textContent = stats[s];
        document.getElementById('pbar_' + s).setAttribute('data-pct', stats[s] * 10);
    });
    applyDataPct(document.getElementById('step-3'));

    function makePerkPreviewCard(p, cssClass, iconClass, badgeClass, badgeLabel) {
        var costBadge = p.cost ? '<div class="gene-card-cost-badge">' + p.cost + ' PTS</div>' : '';
        return '<div class="gene-card gene-card--relative ' + cssClass + '">' +
            costBadge +
            '<div class="gene-card-icon ' + iconClass + '">' +
                '<i class="fas ' + p.icon + '" data-icon-color="' + escHtml(p.iconColor || '') + '"></i>' +
            '</div>' +
            '<div class="gene-card-info">' +
                '<div class="gene-card-name">' + escHtml(p.name) + '</div>' +
                '<div class="gene-card-desc">' + escHtml(p.desc) + '</div>' +
            '</div>' +
            '<div class="gene-card-badge ' + badgeClass + '">' + badgeLabel + '</div>' +
        '</div>';
    }

    var spent = getSpentPoints();
    var max = maxLinajePoints;
    var sobrante = max - spent;
    var bonusPP = sobrante * 3;

    var cardsHTML = '';
    cardsHTML += '<div class="linaje-slots-bar linaje-slots-bar--column">';
    cardsHTML += '    <div class="linaje-slots-group">';
    cardsHTML += '        <span class="linaje-slots-label rpg-wizard-linaje-label"><i class="fas fa-gem rpg-wizard-icon-accent"></i> Puntos:</span>';
    cardsHTML += '        <span class="linaje-slots-count">' + spent + '/' + max + '</span>';
    cardsHTML += '        <span class="rpg-wizard-linaje-sobrante">(' + sobrante + ' Sobrantes = +' + bonusPP + ' PP Bonus)</span>';
    cardsHTML += '    </div>';
    cardsHTML += '</div>';

    if (pasivasData.length > 0) {
        cardsHTML += '<div class="linaje-section-title linaje-section-title--pasivas"><i class="fas fa-shield-alt"></i> Pasivas Innatas</div>';
        cardsHTML += '<div class="gene-cards-grid">';
        pasivasData.forEach(function(p) {
            var isPrim = p.type === 'primaria';
            cardsHTML += makePerkPreviewCard(p,
                isPrim ? 'passive-primary' : 'passive-secondary',
                isPrim ? 'gene-card-icon--primary' : 'gene-card-icon--secondary',
                isPrim ? 'gene-card-badge--primary' : 'gene-card-badge--secondary',
                isPrim ? 'PRIMARIA' : 'SECUNDARIA'
            );
        });
        cardsHTML += '</div>';
    }
    if (racialData.length > 0) {
        cardsHTML += '<div class="linaje-section-title linaje-section-title--racial"><i class="fas fa-dna"></i> Linaje Racial</div>';
        cardsHTML += '<div class="gene-cards-grid">';
        racialData.forEach(function(p) {
            cardsHTML += makePerkPreviewCard(p, 'perk-racial',
                'gene-card-icon--racial', 'gene-card-badge--racial', 'RACIAL');
        });
        cardsHTML += '</div>';
    }
    if (generalData.length > 0) {
        cardsHTML += '<div class="linaje-section-title linaje-section-title--general"><i class="fas fa-star"></i> Linaje General</div>';
        cardsHTML += '<div class="gene-cards-grid">';
        generalData.forEach(function(p) {
            cardsHTML += makePerkPreviewCard(p, 'perk-general',
                'gene-card-icon--general', 'gene-card-badge--general', 'GENERAL');
        });
        cardsHTML += '</div>';
    }
    if (pasivasData.length === 0 && racialData.length === 0 && generalData.length === 0) {
        cardsHTML += '<p class="rpg-wizard-empty-italic">No se han seleccionado perks adicionales.</p>';
    }
    var geneCardsEl = document.getElementById('preview_gene_cards');
    geneCardsEl.innerHTML = cardsHTML;
    applyDataColors(geneCardsEl);
}

function guardarPersonaje() {
    var btn = document.querySelector('button[onclick="guardarPersonaje()"]');
    var oldText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    btn.disabled = true;

    var saveUrl = (cfg.bburl || '') + '/game/ajax/save_personaje.php';
    var savePromise = window.gamePostJson
        ? window.gamePostJson(saveUrl, pjData)
        : fetch(saveUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(pjData) }).then(function(r) { return r.json(); });
    savePromise.then(function(data) {
        if (data.ok) {
            window.location.href = 'personaje.php?pj=' + data.data.pj_id;
        } else {
            alert('Error al guardar: ' + (data.error ? data.error.message : 'Desconocido'));
            btn.innerHTML = oldText;
            btn.disabled = false;
        }
    })
    .catch(err => {
        alert('Error de conexión.');
        btn.innerHTML = oldText;
        btn.disabled = false;
    });
}

// Prefill script
(function(){
    var editData = cfg.editData || null;
    if (editData) {
        document.getElementById('pj_name').value = editData.name || '';
        document.getElementById('pj_avatar').value = editData.avatar || '';
        document.getElementById('pj_faction').value = editData.faction || '';
        document.getElementById('pj_rank').value = editData.rank || '';
        
        if (editData.race && editData.race.indexOf('Híbrido') === 0) {
            document.getElementById('pj_race').value = 'Hibrido';
            checkHibrido();
            var match = editData.race.match(/Híbrido \((.*) \/ (.*)\)/);
            if (match) {
                document.getElementById('pj_race_dom').value = match[1];
                document.getElementById('pj_race_rec').value = match[2];
            }
        } else {
            document.getElementById('pj_race').value = editData.race || '';
            checkHibrido();
        }
        
        document.getElementById('pj_age').value = editData.age || '';
        document.getElementById('pj_origin').value = editData.origin || '';
        document.getElementById('pj_pb').value = editData.pb || '';
        document.getElementById('pj_physique').value = editData.physique || '';
        document.getElementById('pj_psychology').value = editData.psychology || '';
        document.getElementById('pj_extras').value = editData.extras || '';
        
        if (editData.arquetipo) {
            var box = document.querySelector('.arq-box[onclick="selectArq(\''+editData.arquetipo+'\', this)"]');
            if (box) selectArq(editData.arquetipo, box);
        }
        
        if (editData.stats) {
            stats.fue = editData.stats.fue !== undefined ? editData.stats.fue : (editData.stats.str !== undefined ? editData.stats.str : 0);
            stats.agi = editData.stats.agi !== undefined ? editData.stats.agi : 0;
            stats.des = editData.stats.des !== undefined ? editData.stats.des : (editData.stats.res !== undefined ? editData.stats.res : 0);
            stats.inst = editData.stats.inst !== undefined ? editData.stats.inst : (editData.stats.vol !== undefined ? editData.stats.vol : 0);
            stats.esp = editData.stats.esp !== undefined ? editData.stats.esp : (editData.stats.vol !== undefined ? editData.stats.vol : 0);
            stats.int = editData.stats.int !== undefined ? editData.stats.int : 0;
            
            ['fue','agi','des','inst','esp','int'].forEach(function(s) {
                var el = document.getElementById('val_' + s);
                if(el) el.textContent = stats[s];
            });
            var ptsEl = document.getElementById('pts_left');
            if(ptsEl) ptsEl.textContent = (ptsMax - getPtsUsed());
        }
        
        document.getElementById('pj_job').value = editData.job || 'Ninguno';
        
        if (editData.linaje) {
            // Support both v2 (new perk system) and v1 (legacy DNA tree)
            if (editData.linaje.version === 2) {
                window.editLinajeSelected = {
                    racial:  editData.linaje.elegidos_racial  || [],
                    general: editData.linaje.elegidos_general || []
                };
            }
            // v1 data is silently ignored — user picks fresh on edit
        }
    }
})();
applyDataBg(document.getElementById('preview_avatar'));
applyDataPct(document.getElementById('step-3'));
})();
