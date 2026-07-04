/**
 * zona_staff_nen.js — Panel de Staff: Administración Nen (Hunter × Hunter RPG)
 * Confía en window.STAFF_NEN_CONFIG (inyectado desde PHP como config-only block).
 */

(function () {
    'use strict';

    /* ── Hatsu: Aprobar ─────────────────────────── */

    window.approveHatsu = function (abilityId) {
        var cardId = document.getElementById('card-bind-' + abilityId).value;
        var msg    = document.getElementById('approve-msg-' + abilityId);
        if (!cardId) { alert('Debes seleccionar una Carta Técnica del catálogo.'); return; }
        var cfg = window.STAFF_NEN_CONFIG;
        msg.className = 'rpg-nen-msg rpg-is-hidden';
        fetch(cfg.bburl + '/game/ajax/nen_ability_approve.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ my_post_key: cfg.my_post_key, ability_id: abilityId, card_id: parseInt(cardId, 10) })
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    msg.textContent = '✓ ' + (res.data.message || 'Aprobada.');
                    msg.className = 'rpg-nen-msg rpg-nen-msg--ok';
                    setTimeout(function () { var card = document.getElementById('hatsu-card-' + abilityId); if (card) card.remove(); }, 1500);
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

    /* ── Hatsu: Rechazar ────────────────────────── */

    window.rejectHatsu = function (abilityId) {
        if (!confirm('¿Rechazar y eliminar esta propuesta de Hatsu?')) return;
        var cfg = window.STAFF_NEN_CONFIG;
        var msg = document.getElementById('approve-msg-' + abilityId);
        msg.className = 'rpg-nen-msg rpg-is-hidden';
        fetch(cfg.bburl + '/game/ajax/nen_ability_approve.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ my_post_key: cfg.my_post_key, ability_id: abilityId, card_id: 0, reject: true })
        })
            .then(function () {
                msg.textContent = '✓ Habilidad rechazada.';
                msg.className = 'rpg-nen-msg rpg-nen-msg--ok';
                setTimeout(function () { var card = document.getElementById('hatsu-card-' + abilityId); if (card) card.remove(); }, 1500);
            })
            .catch(function () {
                msg.textContent = '✗ Error.';
                msg.className = 'rpg-nen-msg rpg-nen-msg--err';
            });
    };

    /* ── Taza Directo ───────────────────────────── */

    window.submitTazaDirect = function (e) {
        e.preventDefault();
        var cfg   = window.STAFF_NEN_CONFIG;
        var pjId  = document.getElementById('taza-target-pj').value;
        var type  = document.getElementById('taza-nen-type').value;
        var msg   = document.getElementById('taza-direct-msg');
        msg.className = 'rpg-nen-msg rpg-is-hidden';
        fetch(cfg.bburl + '/game/ajax/nen_set_type.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ my_post_key: cfg.my_post_key, character_id: parseInt(pjId, 10), nen_type: type })
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    msg.textContent = '✓ ' + (res.data.message || 'Tipo de Nen fijado.');
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

    /* ── Despertar Directo ──────────────────────── */

    window.submitDespertarDirect = function (e) {
        e.preventDefault();
        var cfg  = window.STAFF_NEN_CONFIG;
        var pjId = document.getElementById('despertar-pj-id').value;
        var msg  = document.getElementById('despertar-direct-msg');
        msg.className = 'rpg-nen-msg rpg-is-hidden';
        fetch(cfg.bburl + '/game/ajax/nen_despertar_directo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ my_post_key: cfg.my_post_key, character_id: parseInt(pjId, 10) })
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    msg.textContent = '✓ ' + (res.data.message || 'Aura despertada.');
                    msg.className = 'rpg-nen-msg rpg-nen-msg--ok';
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

})();
