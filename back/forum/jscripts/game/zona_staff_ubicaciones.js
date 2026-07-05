(function() {
    'use strict';

    var modal = document.getElementById('rpg-island-modal');
    var modalTitle = document.getElementById('rpg-modal-title');
    var modalClose = document.getElementById('rpg-modal-close');
    var backdrop = modal.querySelector('.rpg-modal-backdrop');
    var editor = modal.querySelector('.rpg-forum-island-editor');
    var saveBtn = modal.querySelector('.rpg-island-save-btn');
    var savedMsg = modal.querySelector('.rpg-island-saved-msg');
    var fields = modal.querySelectorAll('.island-field');
    var currentFid = null;

    function openModal(fid, name) {
        currentFid = fid;
        modalTitle.textContent = name;
        saveBtn.dataset.saveUrl = '';
        savedMsg.classList.add('is-hidden');
        modal.classList.remove('is-hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.add('is-hidden');
        document.body.style.overflow = '';
        currentFid = null;
    }

    // Populate modal from card data attributes
    function populateModal(card) {
        var fieldMap = {
            island_image: 1, leader_name: 1, description: 1, terrain: 1,
            climate: 1, climate_temp: 1, climate_wind: 1, climate_precip: 1,
            buildings: 1, defenses: 1, resources: 1,
            coord_x: 1, coord_y: 1, region_slug: 1, base_danger: 1,
            country: 1, travel_difficulty: 1,
            controlling_type: 1, controlling_id: 1
        };
        fields.forEach(function(f) {
            var key = f.dataset.field;
            if (fieldMap[key]) {
                f.value = card.getAttribute('data-' + key) || (key === 'base_danger' ? '1' : (key === 'controlling_id' ? '0' : ''));
            }
        });
        // Preview
        var preview = modal.querySelector('.rpg-island-preview');
        var imgUrl = card.getAttribute('data-island_image') || '';
        if (imgUrl) {
            preview.innerHTML = '<img src="' + imgUrl.replace(/&/g, '&amp;') + '" alt="" />';
            preview.classList.remove('is-hidden');
        } else {
            preview.innerHTML = '';
            preview.classList.add('is-hidden');
        }
    }

    // Update card after save
    function updateCard(fid, data) {
        var card = document.querySelector('.rpg-island-card[data-fid="' + fid + '"]');
        if (!card) return;
        var attrMap = {
            island_image: 'data-island_image',
            leader_name: 'data-leader_name',
            description: 'data-description',
            terrain: 'data-terrain',
            climate: 'data-climate',
            climate_temp: 'data-climate_temp',
            climate_wind: 'data-climate_wind',
            climate_precip: 'data-climate_precip',
            buildings: 'data-buildings',
            defenses: 'data-defenses',
            resources: 'data-resources',
            coord_x: 'data-coord_x',
            coord_y: 'data-coord_y',
            region_slug: 'data-region_slug',
            base_danger: 'data-base_danger',
            country: 'data-country',
            travel_difficulty: 'data-travel_difficulty',
            controlling_type: 'data-controlling_type',
            controlling_id: 'data-controlling_id'
        };
        Object.keys(attrMap).forEach(function(key) {
            if (data[key] !== undefined) {
                card.setAttribute(attrMap[key], data[key]);
            }
        });
        // Update leader text in card
        if (data.leader_name !== undefined) {
            var leaderEl = card.querySelector('.rpg-island-card-leader');
            if (leaderEl) leaderEl.innerHTML = '<i class="fas fa-crown"></i> ' + (data.leader_name || '—');
        }
        // Update image
        var imgWrap = card.querySelector('.rpg-island-card-img-wrap');
        if (data.island_image !== undefined) {
            if (data.island_image) {
                imgWrap.innerHTML = '<img src="' + data.island_image.replace(/&/g, '&amp;') + '" alt="" class="rpg-island-card-img" />';
            } else {
                imgWrap.innerHTML = '<div class="rpg-island-card-img-placeholder"><i class="fas fa-map-marked-alt"></i></div>';
            }
        }
        // Update preview in modal
        var preview = modal.querySelector('.rpg-island-preview');
        if (data.island_image) {
            preview.innerHTML = '<img src="' + data.island_image.replace(/&/g, '&amp;') + '" alt="" />';
            preview.classList.remove('is-hidden');
        } else {
            preview.innerHTML = '';
            preview.classList.add('is-hidden');
        }
    }

    // Edit buttons
    document.querySelectorAll('.rpg-island-edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var card = this.closest('.rpg-island-card');
            var fid = card.getAttribute('data-fid');
            var name = card.getAttribute('data-name') || 'Editar Isla';
            populateModal(card);
            openModal(fid, name);
        });
    });

    // Close
    modalClose.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);

    // ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('is-hidden')) {
            closeModal();
        }
    });

    // Save
    saveBtn.addEventListener('click', function() {
        if (!currentFid) return;
        var bUrl = saveBtn.dataset.saveUrl;
        var data = { fid: currentFid };
        fields.forEach(function(f) {
            data[f.dataset.field] = f.value;
        });

        savedMsg.classList.add('is-hidden');
        gamePostJson(bUrl || '/game/ajax/save_forum_location.php', data).then(function(r) {
            if (r.ok) {
                savedMsg.classList.remove('is-hidden');
                updateCard(currentFid, data);
                setTimeout(function() { savedMsg.classList.add('is-hidden'); }, 3000);
            } else {
                alert('Error: ' + (r.error ? r.error.message : 'desconocido'));
            }
        }).catch(function(e) {
            alert('Error de red: ' + e.message);
        });
    });
})();