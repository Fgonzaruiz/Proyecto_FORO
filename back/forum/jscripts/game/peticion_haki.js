/**
 * Petición Haki — request, resolve, roll
 * Config: window.PERSONAJE_PAGE_CONFIG
 */
(function () {
    "use strict";

    function requestHakiUpgrade(characterId, hakiType) {
        if (!confirm('¿Estás seguro de que deseas solicitar la subida de este Haki? Se descontarán los PP correspondientes.')) {
            return;
        }
        var url = (window.PERSONAJE_PAGE_CONFIG.bburl || '') + '/game/ajax/haki_upgrade.php';
        window.gamePostJson(url, { character_id: characterId, haki_type: hakiType })
        .then(function(res) {
            if (res.ok) {
                alert('Solicitud enviada con éxito. Los PP han sido reservados.');
                window.location.reload();
            } else {
                alert('Error: ' + window.gameFormatError(res));
            }
        })
        .catch(function() {
            alert('Error de conexión.');
        });
    }

    function resolveHakiUpgrade(characterId, hakiType, action) {
        var motivo = '';
        if (action === 'rechazar') {
            motivo = prompt('Introduce el motivo del rechazo (opcional):');
            if (motivo === null) return; // Cancelled
        } else {
            if (!confirm('¿Estás seguro de aprobar esta subida de Haki?')) return;
        }
        var url = (window.PERSONAJE_PAGE_CONFIG.bburl || '') + '/game/ajax/haki_resolve.php';
        window.gamePostJson(url, { character_id: characterId, haki_type: hakiType, action: action, motivo: motivo })
        .then(function(res) {
            if (res.ok) {
                alert('Solicitud ' + (action === 'aprobar' ? 'aprobada' : 'rechazada') + ' con éxito.');
                window.location.reload();
            } else {
                alert('Error: ' + window.gameFormatError(res));
            }
        })
        .catch(function() {
            alert('Error de conexión.');
        });
    }

    function rollHaoshokuAwakening(characterId) {
        if (!confirm('¿Estás seguro de lanzar la tirada de despertar de Haki del Conquistador para este personaje? Esto consumirá 500 PP de su saldo.')) {
            return;
        }

        // Create overlay
        var overlay = document.createElement('div');
        overlay.className = 'haki-roll-overlay';
        overlay.id = 'haki-roll-overlay';
        overlay.innerHTML = 
            '<div class="haki-roll-box haki-roll-animating" id="haki-roll-box">' +
                '<div class="haki-roll-dice">' +
                    '<i class="fas fa-dice-d20"></i>' +
                '</div>' +
                '<div class="haki-roll-title" id="haki-roll-title">Lanzando dados...</div>' +
                '<div class="haki-roll-details" id="haki-roll-details" style="display:none">' +
                    '<div class="haki-roll-row">' +
                        '<span>Dado (1-100)</span>' +
                        '<span id="haki-roll-val">0</span>' +
                    '</div>' +
                    '<div class="haki-roll-row">' +
                        '<span>Bono Espíritu</span>' +
                        '<span id="haki-roll-bonus">0</span>' +
                    '</div>' +
                    '<div class="haki-roll-row">' +
                        '<span>Total</span>' +
                        '<span id="haki-roll-total">0</span>' +
                    '</div>' +
                '</div>' +
                '<div id="haki-roll-actions" style="display:none; margin-top:20px;">' +
                    '<button class="rpg-btn rpg-btn--primary" onclick="window.location.reload()">Aceptar</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(overlay);

        // Fade in
        setTimeout(function() {
            overlay.classList.add('is-visible');
        }, 50);

        var startTime = Date.now();
        var url = (window.PERSONAJE_PAGE_CONFIG.bburl || '') + '/game/ajax/haki_conquistador_roll.php';

        window.gamePostJson(url, { character_id: characterId })
        .then(function(res) {
            var elapsed = Date.now() - startTime;
            var delay = Math.max(0, 1500 - elapsed);

            setTimeout(function() {
                if (res.ok) {
                    var data = res.data;
                    var box = document.getElementById('haki-roll-box');
                    if (box) box.classList.remove('haki-roll-animating');

                    var titleEl = document.getElementById('haki-roll-title');
                    var diceEl = box ? box.querySelector('.haki-roll-dice') : null;

                    if (data.unlocked_level > 0) {
                        titleEl.className = 'haki-roll-title success';
                        titleEl.textContent = '¡Despertado! ' + data.result_label;
                        if (diceEl) diceEl.innerHTML = '<i class="fas fa-crown"></i>';
                    } else {
                        titleEl.className = 'haki-roll-title fail';
                        titleEl.textContent = 'Despertar fallido';
                        if (diceEl) diceEl.innerHTML = '<i class="fas fa-eye-slash"></i>';
                    }

                    document.getElementById('haki-roll-val').textContent = data.roll;
                    document.getElementById('haki-roll-bonus').textContent = '+' + data.bonus;
                    document.getElementById('haki-roll-total').textContent = data.total;

                    document.getElementById('haki-roll-details').style.display = 'block';
                    document.getElementById('haki-roll-actions').style.display = 'block';
                } else {
                    overlay.remove();
                    alert('Error: ' + window.gameFormatError(res));
                }
            }, delay);
        })
        .catch(function(err) {
            overlay.remove();
            alert('Error de conexión.');
        });
    }

    // Set progress bar widths dynamically
    function initProgressBars() {
        document.querySelectorAll(".haki-progress-bar-fill").forEach(function (bar) {
            var w = bar.getAttribute("data-width") || "0";
            bar.style.width = w + "%";
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener("DOMContentLoaded", initProgressBars);
    } else {
        initProgressBars();
    }

    window.requestHakiUpgrade = requestHakiUpgrade;
    window.resolveHakiUpgrade = resolveHakiUpgrade;
    window.rollHaoshokuAwakening = rollHaoshokuAwakening;
})();
