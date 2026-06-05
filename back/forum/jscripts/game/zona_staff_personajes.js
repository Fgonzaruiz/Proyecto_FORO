(function () {
  "use strict";

  function init() {
    const editButtons = document.querySelectorAll('.edit-pj-btn');
    const drawer = document.getElementById('pj-editor-drawer');
    const backdrop = document.getElementById('pj-editor-backdrop');
    const closeBtn = document.getElementById('pj-editor-close');

    if (!drawer) return;

    function openDrawer(btn) {
      const id = btn.getAttribute('data-id');
      const name = btn.getAttribute('data-name');
      const avatar = btn.getAttribute('data-avatar');
      const race = btn.getAttribute('data-race');
      const occupation = btn.getAttribute('data-occupation');
      const faction = btn.getAttribute('data-faction');
      const username = btn.getAttribute('data-username');
      const uid = btn.getAttribute('data-uid');
      const berries = btn.getAttribute('data-berries');
      const status = btn.getAttribute('data-status');
      const staffLevel = btn.getAttribute('data-staff-level');

      // Populate summary
      document.getElementById('pj-summary-avatar').src = avatar;
      document.getElementById('pj-summary-name').textContent = name;
      document.getElementById('pj-summary-meta').textContent = `${race} • ${occupation} • ${faction}`;
      document.getElementById('pj-summary-owner').innerHTML = username 
        ? `<i class="fas fa-user"></i> Propietario: <strong>${username}</strong> (UID: ${uid})`
        : `<span class="rpg-staff-cell-muted">Sin Cuenta</span>`;

      // Populate forms
      document.getElementById('edit-berries-id').value = id;
      document.getElementById('edit-berries-input').value = berries;

      document.getElementById('edit-role-id').value = id;
      document.getElementById('edit-role-select').value = staffLevel;

      // Update links
      document.getElementById('btn-view-ficha').href = `personaje.php?pj=${id}`;

      const toggleLifeBtn = document.getElementById('btn-toggle-life');
      if (status === 'muerto') {
        toggleLifeBtn.className = 'rpg-btn-approve-lg rpg-btn-full';
        toggleLifeBtn.innerHTML = '<i class="fas fa-heart"></i> Revivir Personaje';
        toggleLifeBtn.href = `zona_staff_personajes.php?action=set_status&id=${id}&status=aprobada`;
      } else {
        toggleLifeBtn.className = 'rpg-btn-reject-lg rpg-btn-full';
        toggleLifeBtn.innerHTML = '<i class="fas fa-skull"></i> Matar Personaje';
        toggleLifeBtn.href = `zona_staff_personajes.php?action=set_status&id=${id}&status=muerto`;
        
        // Add confirmation to Matar
        toggleLifeBtn.onclick = function (e) {
          if (!confirm('¿Seguro que deseas matar a este personaje?')) {
            e.preventDefault();
          }
        };
      }

      document.getElementById('btn-delete-pj').href = `zona_staff_personajes.php?action=delete&id=${id}`;

      // Open visual drawer
      drawer.classList.remove('rpg-is-hidden');
      document.body.classList.add('rpg-staff-drawer-open');
    }

    function closeDrawer() {
      drawer.classList.add('rpg-is-hidden');
      document.body.classList.remove('rpg-staff-drawer-open');
    }

    // Event listeners for edit buttons
    editButtons.forEach(btn => {
      btn.addEventListener('click', () => openDrawer(btn));
    });

    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (backdrop) backdrop.addEventListener('click', closeDrawer);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
