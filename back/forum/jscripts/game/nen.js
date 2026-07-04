/**
 * nen.js — Sistema de Nen (Hunter × Hunter RPG)
 */

(function () {
    'use strict';

    function escapeHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function applyNenColors() {
        document.querySelectorAll('[data-nen-color-text]').forEach(function (el) {
            el.style.color = el.getAttribute('data-nen-color-text');
        });
        document.querySelectorAll('[data-nen-color]').forEach(function (el) {
            if (el.classList.contains('rpg-nen-type-dot')) {
                el.style.background = el.getAttribute('data-nen-color');
            }
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

    function renderAffinitiesPreview(container, affinities) {
        if (!container || !affinities) return;
        nenHexagonChart(container, affinities);
    }

    function nenHexagonChart(container, affinities) {
        var cx = 150, cy = 150, r = 105;
        var mainType = '';
        affinities.forEach(function (a) {
            if (a.is_primary) mainType = a.slug;
        });
        var mainColor = '#6441A5';
        affinities.forEach(function (a) {
            if (a.is_primary && a.color) mainColor = a.color;
        });

        var html = '<svg viewBox="0 0 300 300" class="rpg-nen-hex-svg" role="img" aria-label="Afinidades Nen">';
        for (var ring = 1; ring <= 5; ring++) {
            var ringR = r * (ring / 5);
            var ringPts = [];
            for (var ri = 0; ri < 6; ri++) {
                var ra = (Math.PI / 3) * ri - Math.PI / 2;
                ringPts.push((cx + ringR * Math.cos(ra)).toFixed(1) + ',' + (cy + ringR * Math.sin(ra)).toFixed(1));
            }
            html += '<polygon points="' + ringPts.join(' ') + '" class="rpg-nen-hex-ring" />';
        }

        var valuePts = [];
        for (var i = 0; i < 6; i++) {
            var a = affinities[i];
            var unavailable = a && a.unavailable;
            var maestria = a ? (a.maestria || 0) : 0;
            var fillR = unavailable ? 0 : r * (maestria / 5);
            var angle = (Math.PI / 3) * i - Math.PI / 2;
            valuePts.push((cx + fillR * Math.cos(angle)).toFixed(1) + ',' + (cy + fillR * Math.sin(angle)).toFixed(1));
            var x = cx + r * Math.cos(angle);
            var y = cy + r * Math.sin(angle);
            html += '<line x1="' + cx + '" y1="' + cy + '" x2="' + x.toFixed(1) + '" y2="' + y.toFixed(1) + '" class="rpg-nen-hex-spoke" />';
        }
        html += '<polygon points="' + valuePts.join(' ') + '" class="rpg-nen-hex-value" style="--nen-hex-fill:' + escapeHtml(mainColor) + '" />';

        for (var j = 0; j < 6; j++) {
            var d = affinities[j];
            if (!d) continue;
            var ang = (Math.PI / 3) * j - Math.PI / 2;
            var lx = cx + (r + 28) * Math.cos(ang);
            var ly = cy + (r + 28) * Math.sin(ang);
            var short = d.label ? d.label.split(' ')[0] : '?';
            if (short.indexOf('(') > 0) short = short.split('(')[0].trim();
            var mText = d.unavailable ? '—' : ('M' + d.maestria);
            var fw = d.is_primary ? '700' : '600';
            html += '<text x="' + lx.toFixed(1) + '" y="' + (ly - 6).toFixed(1) + '" text-anchor="middle" class="rpg-nen-hex-label" fill="' + escapeHtml(d.color) + '" font-weight="' + fw + '">' + escapeHtml(short) + '</text>';
            html += '<text x="' + lx.toFixed(1) + '" y="' + (ly + 10).toFixed(1) + '" text-anchor="middle" class="rpg-nen-hex-maestria' + (d.unavailable ? ' rpg-nen-hex-maestria--na' : '') + '" fill="' + (d.unavailable ? '#94a3b8' : escapeHtml(d.color)) + '">' + escapeHtml(mText) + '</text>';
        }
        html += '</svg>';
        container.innerHTML = '<div class="rpg-nen-hex-wrap rpg-nen-hex-wrap--preview">' + html + '</div>';
    }

    function playInlineMizuResult(data) {
        var water = document.getElementById('mizu-water');
        var waterGlow = document.getElementById('mizu-water-glow');
        var leaf = document.getElementById('mizu-leaf');
        var ripple = document.getElementById('mizu-ripple');
        var touchHint = document.getElementById('mizu-touch-hint');
        var instruction = document.getElementById('mizu-instruction');
        var glassBtn = document.getElementById('mizu-glass-touch');
        var reveal = document.getElementById('nen-mizu-reveal');
        var revealType = document.getElementById('mizu-reveal-type');
        var affPreview = document.getElementById('mizu-affinities-preview');
        var typeColor = data.nen_type_color || '#1976D2';

        if (glassBtn) glassBtn.classList.add('nen-mizu-glass-scene--active');
        if (touchHint) touchHint.classList.add('rpg-is-hidden');
        if (instruction) instruction.textContent = 'Tu aura fluye hacia el agua...';
        if (ripple) ripple.classList.add('nen-mizu-ripple--play');

        setTimeout(function () {
            if (water) {
                water.style.setProperty('--nen-mizu-water-color', typeColor);
                water.classList.add('nen-mizu-water--colored');
            }
            if (waterGlow) {
                waterGlow.style.background = 'radial-gradient(circle, ' + typeColor + '55 0%, transparent 70%)';
                waterGlow.classList.add('nen-mizu-water-glow--on');
            }
            if (leaf) {
                leaf.style.setProperty('--nen-mizu-leaf-color', typeColor);
                leaf.classList.add('nen-mizu-leaf--colored');
            }
        }, 400);

        setTimeout(function () {
            if (reveal) reveal.classList.remove('rpg-is-hidden');
            if (revealType) {
                revealType.textContent = data.nen_type_label;
                revealType.style.color = typeColor;
            }
            renderAffinitiesPreview(affPreview, data.affinities || data.control_labels);
            if (instruction) instruction.textContent = 'Afinidad registrada. Cargando panel Nen...';
        }, 1800);

        setTimeout(function () {
            window.location.reload();
        }, 3200);
    }

    function bindDespertarBtn() {
        var btn = document.getElementById('btn-despertar-nen');
        var inline = document.getElementById('nen-mizu-inline');
        var locked = document.getElementById('nen-locked-state');
        var glassBtn = document.getElementById('mizu-glass-touch');
        if (!btn || !inline || !glassBtn) return;

        var started = false;
        var touching = false;

        btn.addEventListener('click', function () {
            if (started) return;
            started = true;
            btn.disabled = true;
            if (locked) locked.classList.add('rpg-is-hidden');
            inline.classList.remove('rpg-is-hidden');
        });

        glassBtn.addEventListener('click', function () {
            if (!started || touching) return;
            touching = true;

            var cfg = window.NEN_CONFIG;
            var msg = document.getElementById('nen-despertar-msg');
            var instruction = document.getElementById('mizu-instruction');
            if (instruction) instruction.textContent = 'Canalizando aura...';

            fetch(cfg.bburl + '/game/ajax/nen_awaken.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ my_post_key: cfg.my_post_key, character_id: cfg.character_id })
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.ok) {
                        playInlineMizuResult(res.data);
                    } else {
                        touching = false;
                        started = false;
                        inline.classList.add('rpg-is-hidden');
                        if (locked) locked.classList.remove('rpg-is-hidden');
                        if (msg) {
                            msg.textContent = '✗ ' + (res.error ? res.error.message : 'Error.');
                            msg.className = 'rpg-nen-msg rpg-nen-msg--err';
                        }
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    touching = false;
                    started = false;
                    inline.classList.add('rpg-is-hidden');
                    if (locked) locked.classList.remove('rpg-is-hidden');
                    if (msg) {
                        msg.textContent = '✗ Error de conexión.';
                        msg.className = 'rpg-nen-msg rpg-nen-msg--err';
                    }
                    btn.disabled = false;
                });
        });
    }

    window.requestTrainPrinciple = function (principle, targetLevel) {
        var cfg = window.NEN_CONFIG;
        var msg = document.getElementById('nen-train-msg');
        if (msg) msg.className = 'rpg-nen-msg rpg-is-hidden';
        fetch(cfg.bburl + '/game/ajax/nen_train.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ my_post_key: cfg.my_post_key, principle: principle, level: targetLevel })
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!msg) return;
                if (res.ok) {
                    msg.textContent = '✓ ' + (res.data.message || 'Solicitud enviada.');
                    msg.className = 'rpg-nen-msg rpg-nen-msg--ok';
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    msg.textContent = '✗ ' + (res.error ? res.error.message : 'Error.');
                    msg.className = 'rpg-nen-msg rpg-nen-msg--err';
                }
            })
            .catch(function () {
                if (msg) {
                    msg.textContent = '✗ Error de conexión.';
                    msg.className = 'rpg-nen-msg rpg-nen-msg--err';
                }
            });
    };

    window.requestTrainAdvanced = function (technique) {
        var cfg = window.NEN_CONFIG;
        var msg = document.getElementById('nen-advanced-msg');
        if (msg) msg.className = 'rpg-nen-msg rpg-is-hidden';
        fetch(cfg.bburl + '/game/ajax/nen_train_advanced.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ my_post_key: cfg.my_post_key, technique: technique })
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!msg) return;
                if (res.ok) {
                    msg.textContent = '✓ ' + (res.data.message || 'Solicitud enviada.');
                    msg.className = 'rpg-nen-msg rpg-nen-msg--ok';
                } else {
                    msg.textContent = '✗ ' + (res.error ? res.error.message : 'Error.');
                    msg.className = 'rpg-nen-msg rpg-nen-msg--err';
                }
            })
            .catch(function () {
                if (msg) {
                    msg.textContent = '✗ Error de conexión.';
                    msg.className = 'rpg-nen-msg rpg-nen-msg--err';
                }
            });
    };

    var conditionIndex = 0;

    window.addConditionInput = function (val) {
        val = val || '';
        var container = document.getElementById('conditions-list');
        if (!container) return;
        var div = document.createElement('div');
        div.id = 'cond-row-' + conditionIndex;
        div.className = 'rpg-nen-condition-row';
        var idx = conditionIndex;
        div.innerHTML = '<input type="text" class="rpg-form-input textbox hatsu-condition-input" placeholder="Ej: Solo de noche" value="' + escapeHtml(val) + '" />'
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
        var list = document.getElementById('conditions-list');
        if (list) list.innerHTML = '';
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
        var cfg = window.NEN_CONFIG;
        var msg = document.getElementById('hatsu-submit-msg');
        var conditions = [];
        document.querySelectorAll('.hatsu-condition-input').forEach(function (input) {
            var v = input.value.trim();
            if (v !== '') conditions.push(v);
        });
        if (msg) msg.className = 'rpg-nen-msg rpg-is-hidden';
        fetch(cfg.bburl + '/game/ajax/nen_ability_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                my_post_key: cfg.my_post_key,
                name: document.getElementById('hatsu-name').value.trim(),
                rank: document.getElementById('hatsu-rank').value,
                cost: parseInt(document.getElementById('hatsu-cost').value, 10) || 0,
                description: document.getElementById('hatsu-desc').value.trim(),
                conditions: conditions
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!msg) return;
                if (res.ok) {
                    msg.textContent = '✓ ' + (res.data.message || 'Enviado.');
                    msg.className = 'rpg-nen-msg rpg-nen-msg--ok';
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    msg.textContent = '✗ ' + (res.error ? res.error.message : 'Error.');
                    msg.className = 'rpg-nen-msg rpg-nen-msg--err';
                }
            })
            .catch(function () {
                if (msg) {
                    msg.textContent = '✗ Error de conexión.';
                    msg.className = 'rpg-nen-msg rpg-nen-msg--err';
                }
            });
    };

    function init() {
        applyNenColors();
        bindDespertarBtn();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
