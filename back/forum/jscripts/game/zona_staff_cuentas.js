(function () {
  "use strict";

  function init() {
    const editButtons = document.querySelectorAll('.edit-account-btn');
    const modalId = 'account-editor-modal';

    if (!document.getElementById(modalId)) return;
    if (window.RpgModal) RpgModal.bind(modalId);

    function openDrawer(btn) {
      const uid = btn.getAttribute('data-uid');
      const username = btn.getAttribute('data-username');
      const email = btn.getAttribute('data-email');
      const avatar = btn.getAttribute('data-avatar');
      const isBanned = btn.getAttribute('data-is-banned') === '1';
      const isNarrator = btn.getAttribute('data-is-narrator') === '1';
      const maxSlots = btn.getAttribute('data-max-slots');
      const actualSlots = btn.getAttribute('data-actual-slots');
      const activePjId = btn.getAttribute('data-active-pj-id');
      const activePjName = btn.getAttribute('data-active-pj-name');
      const suspendPosting = btn.getAttribute('data-suspend-posting') === '1';
      const moderatePosts = btn.getAttribute('data-moderate-posts') === '1';

      // Summary
      document.getElementById('account-summary-avatar').src = avatar;
      document.getElementById('account-summary-name').textContent = username;
      document.getElementById('account-summary-email').textContent = email;
      document.getElementById('account-summary-uid').textContent = `UID: ${uid}`;

      // Slots
      document.getElementById('account-slots-used').textContent = `Ranuras en uso: ${actualSlots}`;
      const slotsSelect = document.getElementById('account-slots-max-select');
      slotsSelect.value = maxSlots;
      
      const saveSlotsBtn = document.getElementById('btn-save-slots');
      saveSlotsBtn.onclick = function() {
        window.location.href = `zona_staff_cuentas.php?action=set_max_slots&uid=${uid}&slots=${slotsSelect.value}`;
      };

      document.getElementById('btn-sync-slots').href = `zona_staff_cuentas.php?action=sync_slots&uid=${uid}`;

      // Narrator
      const toggleNarratorBtn = document.getElementById('btn-toggle-narrator');
      const manageNpcsBtn = document.getElementById('btn-manage-npcs');
      if (isNarrator) {
        toggleNarratorBtn.className = 'rpg-btn-reject-lg rpg-btn-full';
        toggleNarratorBtn.innerHTML = '<i class="fas fa-times"></i> Quitar Narrador';
        toggleNarratorBtn.href = `zona_staff_cuentas.php?action=set_narrator&uid=${uid}&enabled=0`;
        
        manageNpcsBtn.classList.remove('rpg-is-hidden');
        manageNpcsBtn.href = `zona_staff_cuentas.php?manage_npcs=${uid}`;
      } else {
        toggleNarratorBtn.className = 'rpg-btn-approve-lg rpg-btn-full';
        toggleNarratorBtn.innerHTML = '<i class="fas fa-user-ninja"></i> Hacer Narrador';
        toggleNarratorBtn.href = `zona_staff_cuentas.php?action=set_narrator&uid=${uid}&enabled=1`;
        
        manageNpcsBtn.classList.add('rpg-is-hidden');
      }

      // Moderation
      const toggleSuspendBtn = document.getElementById('btn-toggle-suspend');
      if (suspendPosting) {
        toggleSuspendBtn.className = 'rpg-btn-approve-lg rpg-btn-full';
        toggleSuspendBtn.innerHTML = '<i class="fas fa-check"></i> Habilitar Publicación';
        toggleSuspendBtn.href = `zona_staff_cuentas.php?action=set_posting&uid=${uid}&field=suspendposting&enabled=0`;
      } else {
        toggleSuspendBtn.className = 'rpg-btn-reject-lg rpg-btn-full';
        toggleSuspendBtn.innerHTML = '<i class="fas fa-comment-slash"></i> Suspender Publicación';
        toggleSuspendBtn.href = `zona_staff_cuentas.php?action=set_posting&uid=${uid}&field=suspendposting&enabled=1`;
      }

      const toggleModerateBtn = document.getElementById('btn-toggle-moderate');
      if (moderatePosts) {
        toggleModerateBtn.className = 'rpg-btn-approve-lg rpg-btn-full';
        toggleModerateBtn.innerHTML = '<i class="fas fa-check-circle"></i> Quitar Moderación de Posts';
        toggleModerateBtn.href = `zona_staff_cuentas.php?action=set_posting&uid=${uid}&field=moderateposts&enabled=0`;
      } else {
        toggleModerateBtn.className = 'rpg-btn-reject-lg rpg-btn-full';
        toggleModerateBtn.innerHTML = '<i class="fas fa-shield-alt"></i> Moderar Posts';
        toggleModerateBtn.href = `zona_staff_cuentas.php?action=set_posting&uid=${uid}&field=moderateposts&enabled=1`;
      }

      // Active PJ
      const activePjInfo = document.getElementById('account-active-pj-info');
      const clearActivePjBtn = document.getElementById('btn-clear-active-pj');
      if (activePjId && parseInt(activePjId, 10) > 0) {
        activePjInfo.innerHTML = `Personaje activo: <strong>${activePjName}</strong> (ID: ${activePjId})`;
        clearActivePjBtn.classList.remove('rpg-is-hidden');
        clearActivePjBtn.href = `zona_staff_cuentas.php?action=clear_active_pj&uid=${uid}`;
        clearActivePjBtn.onclick = function(e) {
          if (!confirm('¿Limpiar personaje activo de esta cuenta?')) {
            e.preventDefault();
          }
        };
      } else {
        activePjInfo.textContent = 'Ninguno';
        clearActivePjBtn.classList.add('rpg-is-hidden');
      }

      // Ban
      const toggleBanBtn = document.getElementById('btn-toggle-ban');
      if (isBanned) {
        toggleBanBtn.className = 'rpg-btn-approve-lg rpg-btn-full';
        toggleBanBtn.innerHTML = '<i class="fas fa-unlock"></i> Desbanear Cuenta';
        toggleBanBtn.href = `zona_staff_cuentas.php?action=unban&uid=${uid}`;
        toggleBanBtn.onclick = function(e) {
          if (!confirm(`¿Quitar baneo a la cuenta de ${username}?`)) {
            e.preventDefault();
          }
        };
      } else {
        toggleBanBtn.className = 'rpg-btn-reject-lg rpg-btn-full';
        toggleBanBtn.innerHTML = '<i class="fas fa-ban"></i> Banear Cuenta';
        toggleBanBtn.href = '#';
        toggleBanBtn.onclick = function(e) {
          e.preventDefault();
          const reason = prompt(`Escribe el motivo del baneo para la cuenta ${username}:`);
          if (reason === null) return;
          const trimmed = reason.trim();
          if (!trimmed) {
            alert('Debes especificar un motivo para el baneo.');
            return;
          }
          window.location.href = `zona_staff_cuentas.php?action=ban&uid=${uid}&reason=${encodeURIComponent(trimmed)}`;
        };
      }

      if (window.RpgModal) RpgModal.open(modalId);
    }

    editButtons.forEach(btn => {
      btn.addEventListener('click', () => openDrawer(btn));
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
