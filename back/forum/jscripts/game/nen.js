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

    /* ── Despertar ──────────────────────────────── */

    function bindDespertarBtn() {
        var btn = document.getElementById('btn-despertar-nen');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var cfg = window.NEN_CONFIG;
            var msg = document.getElementById('nen-despertar-msg');
            btn.disabled = true;
            btn.textContent = 'Enviando...';
            fetch(cfg.bburl + '/game/public/peticiones_general.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=crear_peticion&my_post_key=' + encodeURIComponent(cfg.my_post_key)
                    + '&request_kind=nen_despertar&title=' + encodeURIComponent('Solicitud Despertar Nen')
                    + '&description=' + encodeURIComponent('El personaje solicita al staff abrir sus nodos de aura.')
            })
                .then(function () {
                    msg.textContent = '✓ Solicitud de Despertar Nen enviada correctamente al staff.';
                    msg.className = 'rpg-nen-msg rpg-nen-msg--ok';
                    setTimeout(function () { window.location.reload(); }, 1800);
                })
                .catch(function () {
                    msg.textContent = '✗ Error al enviar la solicitud.';
                    msg.className = 'rpg-nen-msg rpg-nen-msg--err';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-key"></i> Solicitar Despertar Nen';
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
