(function () {
  "use strict";

  function init() {
    const editButtons = document.querySelectorAll('.edit-npc-btn');
    const drawer = document.getElementById('npc-editor-drawer');
    const backdrop = document.getElementById('npc-editor-backdrop');
    const closeBtn = document.getElementById('npc-editor-close');

    if (!drawer) return;

    function openDrawer(btn) {
      const id = btn.getAttribute('data-id');
      const name = btn.getAttribute('data-name');
      const avatar = btn.getAttribute('data-avatar');
      const race = btn.getAttribute('data-race');
      const occupation = btn.getAttribute('data-occupation');
      const faction = btn.getAttribute('data-faction');
      const rango = btn.getAttribute('data-rango') || 'Ninguno';
      const isActive = btn.getAttribute('data-active') === '1';

      // Stats
      const fue = btn.getAttribute('data-fue');
      const agi = btn.getAttribute('data-agi');
      const des = btn.getAttribute('data-des');
      const int = btn.getAttribute('data-int');
      const esp = btn.getAttribute('data-esp');
      const inst = btn.getAttribute('data-inst');

      // Populate summary
      document.getElementById('npc-summary-avatar').src = avatar;
      document.getElementById('npc-summary-name').textContent = name;
      document.getElementById('npc-summary-meta').textContent = `${race} • ${occupation}`;
      document.getElementById('npc-summary-faction').textContent = `Facción: ${faction} • Rango: ${rango}`;

      // Populate stats
      document.getElementById('npc-stat-fue').textContent = fue;
      document.getElementById('npc-stat-agi').textContent = agi;
      document.getElementById('npc-stat-des').textContent = des;
      document.getElementById('npc-stat-int').textContent = int;
      document.getElementById('npc-stat-esp').textContent = esp;
      document.getElementById('npc-stat-inst').textContent = inst;

      // Update links
      document.getElementById('btn-edit-npc-link').href = `crear_personaje.php?pj_id=${id}`;
      
      const switchBtn = document.getElementById('btn-switch-npc');
      if (isActive) {
        switchBtn.className = 'rpg-btn-approve-lg rpg-btn-full';
        switchBtn.innerHTML = '<i class="fas fa-check-circle"></i> Personaje Activo';
        switchBtn.href = '#';
        switchBtn.onclick = function(e) { e.preventDefault(); };
      } else {
        switchBtn.className = 'rpg-btn-approve-lg rpg-btn-full';
        switchBtn.innerHTML = '<i class="fas fa-exchange-alt"></i> Activar Personaje';
        switchBtn.href = '#';
        switchBtn.onclick = function(e) {
          e.preventDefault();
          if (typeof window.switchPJNav === 'function') {
            window.switchPJNav(parseInt(id, 10));
          } else {
            // Fallback redirection
            window.location.href = `zona_staff_npc.php?action=switch&id=${id}`;
          }
        };
      }

      document.getElementById('btn-delete-npc').href = `zona_staff_npc.php?action=delete&id=${id}`;

      // Open drawer
      drawer.classList.remove('rpg-is-hidden');
      document.body.classList.add('rpg-staff-drawer-open');
    }

    function closeDrawer() {
      drawer.classList.add('rpg-is-hidden');
      document.body.classList.remove('rpg-staff-drawer-open');
    }

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
