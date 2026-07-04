/**
 * nen.js — Sistema de Nen (Hunter × Hunter RPG)
 * Lógica de interacción para nen.php y _tab_nen.php.
 * Confía en window.NEN_CONFIG (inyectado desde PHP como config-only block).
 */

(function () {
    'use strict';

    /* ── Helpers ────────────────────────────────── */

    function applyNenColors() {
        document.querySelectorAll('[data-nen-color-text]').forEach(function (el) {
            el.style.color = el.getAttribute('data-nen-color-text');
        });
        document.querySelectorAll('[data-nen-type-color]').forEach(function (el) {
            var c = el.getAttribute('data-nen-type-color');
            if (el.classList.contains('rpg-nen-profile-type-label')) el.style.color = c;
            if (el.classList.contains('rpg-nen-profile-header'))    el.style.borderLeftColor = c;
            if (el.classList.contains('rpg-nen-card-link'))         el.style.color = c;
        });
        document.querySelectorAll('[data-nen-color]').forEach(function (el) {
            var c = el.getAttribute('data-nen-color');
            if (el.classList.contains('rpg-nen-type-dot')) el.style.background = c;
        });
        document.querySelectorAll('[data-nen-color-bg]').forEach(function (el) {
            el.style.background = el.getAttribute('data-nen-color-bg');
        });
        document.querySelectorAll('[data-nen-aura-color]').forEach(function (el) {
            el.style.color = el.getAttribute('data-nen-aura-color');
        });
        document.querySelectorAll('[data-width]').forEach(function (el) {
            el.style.width = el.getAttribute('data-width') + '%';
        });
    }

    /* ── Taza: Select Type ──────────────────────── */

    window.selectTazaType = function (type, element) {
        document.querySelectorAll('.taza-option-card').forEach(function (c) {
            c.classList.remove('is-selected');
            c.style.borderColor = '';
        });
        var color = element.getAttribute('data-nen-color');
        element.classList.add('is-selected');
        element.style.borderColor = color;
        document.getElementById('selected-nen-type').value = type;
        document.getElementById('btn-submit-taza').disabled = false;
    };

    /* ── Taza: Submit ───────────────────────────── */

    window.submitTazaRequest = function () {
        var type = document.getElementById('selected-nen-type').value;
        if (!type) return;
        var cfg  = window.NEN_CONFIG;
        var btn  = document.getElementById('btn-submit-taza');
        var msg  = document.getElementById('nen-taza-msg');
        btn.disabled  = true;
        btn.textContent = 'Enviando...';
        fetch(cfg.bburl + '/game/ajax/nen_set_type.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ my_post_key: cfg.my_post_key, character_id: cfg.character_id, nen_type: type })
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    msg.textContent = '✓ ' + (res.data.message || 'Solicitud enviada.');
                    msg.className = 'rpg-nen-msg rpg-nen-msg--ok';
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    msg.textContent = '✗ ' + (res.error ? res.error.message : 'Error.');
                    msg.className = 'rpg-nen-msg rpg-nen-msg--err';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar Elección de Aura';
                }
            })
            .catch(function () {
                msg.textContent = '✗ Error de conexión.';
                msg.className = 'rpg-nen-msg rpg-nen-msg--err';
                btn.disabled = false;
            });
    };

    /* ── Principios: Entrenar ───────────────────── */

    window.requestTrainPrinciple = function (principle, targetLevel) {
        var cfg = window.NEN_CONFIG;
        var msg = document.getElementById('nen-train-msg');
        msg.className = 'rpg-nen-msg rpg-is-hidden';
        fetch(cfg.bburl + '/game/ajax/nen_train.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ my_post_key: cfg.my_post_key, principle: principle, level: targetLevel })
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    msg.textContent = '✓ ' + (res.data.message || 'Operación realizada.');
                    msg.className = 'rpg-nen-msg rpg-nen-msg--ok';
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    msg.textContent = '✗ ' + (res.error ? res.error.message : 'Error.');
                    msg.className = 'rpg-nen-msg rpg-nen-msg--err';
                }
            })
            .catch(function () {
                msg.textContent = '✗ Error de conexión.';
                msg.className = 'rpg-nen-msg rpg-nen-msg--err';
            });
    };

    /* ── Hatsu: Modal ───────────────────────────── */

    var conditionIndex = 0;

    window.addConditionInput = function (val) {
        val = val || '';
        var container = document.getElementById('conditions-list');
        if (!container) return;
        var div = document.createElement('div');
        div.id = 'cond-row-' + conditionIndex;
        div.className = 'rpg-nen-condition-row';
        var idx = conditionIndex;
        div.innerHTML = '<input type="text" class="rpg-form-input textbox hatsu-condition-input" placeholder="Ej: Solo funciona de noche" value="' + val + '" />'
            + '<button type="button" class="rpg-nen-condition-remove" onclick="removeConditionInput(' + idx + ')">&times;</button>';
        container.appendChild(div);
        conditionIndex++;
    };

    window.removeConditionInput = function (idx) {
        var row = document.getElementById('cond-row-' + idx);
        if (row) row.remove();
    };

    window.openAbilityModal = function () {
        var modal = document.getElementById('propose-hatsu-modal');
        if (!modal) return;
        modal.classList.remove('rpg-is-hidden');
        modal.style.display = 'flex';
        document.getElementById('conditions-list').innerHTML = '';
        conditionIndex = 0;
        window.addConditionInput();
    };

    window.closeAbilityModal = function () {
        var modal = document.getElementById('propose-hatsu-modal');
        if (!modal) return;
        modal.style.display = 'none';
        modal.classList.add('rpg-is-hidden');
    };

    window.submitHatsuProposal = function (e) {
        e.preventDefault();
        var cfg  = window.NEN_CONFIG;
        var name = document.getElementById('hatsu-name').value.trim();
        var rank = document.getElementById('hatsu-rank').value;
        var cost = parseInt(document.getElementById('hatsu-cost').value, 10) || 0;
        var desc = document.getElementById('hatsu-desc').value.trim();
        var msg  = document.getElementById('hatsu-submit-msg');
        var conditions = [];
        document.querySelectorAll('.hatsu-condition-input').forEach(function (input) {
            var v = input.value.trim();
            if (v !== '') conditions.push(v);
        });
        msg.className = 'rpg-nen-msg rpg-is-hidden';
        fetch(cfg.bburl + '/game/ajax/nen_ability_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ my_post_key: cfg.my_post_key, name: name, rank: rank, cost: cost, description: desc, conditions: conditions })
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    msg.textContent = '✓ ' + (res.data.message || 'Propuesta enviada con éxito.');
                    msg.className = 'rpg-nen-msg rpg-nen-msg--ok';
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    msg.textContent = '✗ ' + (res.error ? res.error.message : 'Error.');
                    msg.className = 'rpg-nen-msg rpg-nen-msg--err';
                }
            })
            .catch(function () {
                msg.textContent = '✗ Error de conexión.';
                msg.className = 'rpg-nen-msg rpg-nen-msg--err';
            });
    };

    /* ── Despertar Automático ───────────────────── */
    /* Canvas particles */
    var awakeCanvas = null, awakeCtx = null, awakeAnimId = null;
    var awakeParticles = [];
    var AWAKENING_DURATION = 3000; // ms for phase 1 bar

    function initAwakeCanvas() {
        awakeCanvas = document.getElementById('nen-awakening-canvas');
        if (!awakeCanvas) return;
        awakeCtx = awakeCanvas.getContext('2d');
        awakeCanvas.width = window.innerWidth;
        awakeCanvas.height = window.innerHeight;
        for (var i = 0; i < 80; i++) {
            awakeParticles.push({
                x: Math.random() * awakeCanvas.width,
                y: Math.random() * awakeCanvas.height,
                r: 2 + Math.random() * 5,
                vx: (Math.random() - 0.5) * 0.8,
                vy: (Math.random() - 0.5) * 0.8,
                alpha: 0.3 + Math.random() * 0.7,
                color: '#00E5FF'
            });
        }
    }

    function drawAwakeParticles(color) {
        if (!awakeCtx) return;
        awakeCtx.clearRect(0, 0, awakeCanvas.width, awakeCanvas.height);
        for (var i = 0; i < awakeParticles.length; i++) {
            var p = awakeParticles[i];
            p.x += p.vx;
            p.y += p.vy;
            if (p.x < 0) p.x = awakeCanvas.width;
            if (p.x > awakeCanvas.width) p.x = 0;
            if (p.y < 0) p.y = awakeCanvas.height;
            if (p.y > awakeCanvas.height) p.y = 0;
            awakeCtx.beginPath();
            awakeCtx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            awakeCtx.fillStyle = color || '#00E5FF';
            awakeCtx.globalAlpha = p.alpha;
            awakeCtx.fill();
        }
        awakeCtx.globalAlpha = 1;
        awakeAnimId = requestAnimationFrame(function () { drawAwakeParticles(color); });
    }

    function nenHexagonChart(container, data, mainType) {
        var cx = 150, cy = 150, r = 130;
        var html = '<svg viewBox="0 0 300 300" class="rpg-nen-hex-svg">';
        // draw hexagon background
        for (var i = 0; i < 6; i++) {
            var pct = data[i] ? data[i].pct / 100 : 0.4;
            var fillR = r * pct;
            var angle = (Math.PI / 3) * i - Math.PI / 2;
            var nextAngle = (Math.PI / 3) * ((i + 1) % 6) - Math.PI / 2;
            var x1 = cx + fillR * Math.cos(angle);
            var y1 = cy + fillR * Math.sin(angle);
            var x2 = cx + fillR * Math.cos(nextAngle);
            var y2 = cy + fillR * Math.sin(nextAngle);
            var x3 = cx;
            var y3 = cy;
            var color = data[i] ? data[i].color : '#555';
            html += '<polygon points="' + x1 + ',' + y1 + ' ' + x2 + ',' + y2 + ' ' + x3 + ',' + y3 + '" fill="' + color + '" opacity="0.25" />';
        }
        // draw hex outlines
        for (var i = 0; i < 6; i++) {
            var angle = (Math.PI / 3) * i - Math.PI / 2;
            var x = cx + r * Math.cos(angle);
            var y = cy + r * Math.sin(angle);
            var nx = cx + r * Math.cos((Math.PI / 3) * (i + 1) - Math.PI / 2);
            var ny = cy + r * Math.sin((Math.PI / 3) * (i + 1) - Math.PI / 2);
            html += '<line x1="' + x + '" y1="' + y + '" x2="' + nx + '" y2="' + ny + '" stroke="#333" stroke-width="1" />';
            html += '<line x1="' + cx + '" y1="' + cy + '" x2="' + x + '" y2="' + y + '" stroke="#444" stroke-width="0.5" stroke-dasharray="4,4" />';
        }
        // draw labels
        for (var i = 0; i < 6; i++) {
            var angle = (Math.PI / 3) * i - Math.PI / 2;
            var labelR = r + 30;
            var x = cx + labelR * Math.cos(angle);
            var y = cy + labelR * Math.sin(angle);
            var d = data[i];
            var isMain = d && d.slug === mainType;
            var fontWeight = isMain ? 'bold' : 'normal';
            var fontSize = isMain ? '14' : '11';
            html += '<text x="' + x + '" y="' + y + '" text-anchor="middle" dominant-baseline="central" fill="' + (d ? d.color : '#999') + '" font-size="' + fontSize + '" font-weight="' + fontWeight + '">';
            var label = d ? d.label.split(' ')[0] : '?';
            html += escapeHtml(label) + ' ' + (d ? d.pct + '%' : '?%');
            html += '</text>';
        }
        // center label
        var mainLabel = mainType ? '100%' : '?';
        html += '<text x="' + cx + '" y="' + cy + '" text-anchor="middle" dominant-baseline="central" fill="#fff" font-size="28" font-weight="bold">' + mainLabel + '</text>';
        html += '</svg>';
        container.innerHTML = html;
    }

    function runAwakening(data) {
        var overlay = document.getElementById('nen-awakening-overlay');
        var locked = document.getElementById('nen-locked-state');
        var phase1 = document.getElementById('nen-awakening-phase-1');
        var phase2 = document.getElementById('nen-awakening-phase-2');
        var phase3 = document.getElementById('nen-awakening-phase-3');
        var bar = document.getElementById('nen-awakening-bar');
        var revealType = document.getElementById('nen-reveal-type-name');
        var chart = document.getElementById('nen-control-chart');
        var burst = document.getElementById('nen-aura-burst');
        var btnCont = document.getElementById('btn-nen-continuar');

        if (locked) locked.classList.add('rpg-is-hidden');
        overlay.classList.remove('rpg-is-hidden');
        initAwakeCanvas();
        drawAwakeParticles(data.nen_type_color);

        // Phase 1: Progress bar
        phase1.classList.remove('rpg-is-hidden');
        phase2.classList.add('rpg-is-hidden');
        phase3.classList.add('rpg-is-hidden');
        var startTime = Date.now();

        function animateBar() {
            var elapsed = Date.now() - startTime;
            var pct = Math.min(elapsed / AWAKENING_DURATION, 1);
            if (bar) bar.style.width = (pct * 100) + '%';
            if (pct < 1) {
                requestAnimationFrame(animateBar);
            } else {
                // Phase 2: Reveal
                phase1.classList.add('rpg-is-hidden');
                phase2.classList.remove('rpg-is-hidden');
                if (burst) {
                    burst.style.background = 'radial-gradient(circle, ' + data.aura_color + ' 0%, transparent 70%)';
                    burst.classList.add('rpg-nen-burst-anim');
                }
                if (revealType) {
                    revealType.textContent = data.nen_type_label;
                    revealType.style.color = data.nen_type_color;
                }
                setTimeout(function () {
                    // Phase 3: Chart
                    phase2.classList.add('rpg-is-hidden');
                    phase3.classList.remove('rpg-is-hidden');
                    if (chart && data.control_labels) {
                        nenHexagonChart(chart, data.control_labels, data.nen_type);
                    }
                }, 2500);
            }
        }
        animateBar();

        btnCont.addEventListener('click', function () {
            if (awakeAnimId) cancelAnimationFrame(awakeAnimId);
            overlay.classList.add('rpg-is-hidden');
            window.location.reload();
        });
    }

    function bindDespertarBtn() {
        var btn = document.getElementById('btn-despertar-nen');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var cfg = window.NEN_CONFIG;
            var msg = document.getElementById('nen-despertar-msg');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Abriendo nodos...';
            fetch(cfg.bburl + '/game/ajax/nen_awaken.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ my_post_key: cfg.my_post_key, character_id: cfg.character_id })
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.ok) {
                        runAwakening(res.data);
                    } else {
                        msg.textContent = '✗ ' + (res.error ? res.error.message : 'Error al despertar el Nen.');
                        msg.className = 'rpg-nen-msg rpg-nen-msg--err';
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-hand-sparkles"></i> Despertar Nen';
                    }
                })
                .catch(function () {
                    msg.textContent = '✗ Error de conexión.';
                    msg.className = 'rpg-nen-msg rpg-nen-msg--err';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-hand-sparkles"></i> Despertar Nen';
                });
        });
    }

    /* ── Init ───────────────────────────────────── */

    document.addEventListener('DOMContentLoaded', function () {
        applyNenColors();
        bindDespertarBtn();
    });

    // Also run right away if document is already ready
    if (document.readyState !== 'loading') {
        applyNenColors();
        bindDespertarBtn();
    }
})();
