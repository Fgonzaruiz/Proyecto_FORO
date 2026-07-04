<?php
$memories = json_decode($crew['memories'] ?? '[]', true);
if (!is_array($memories)) $memories = [];
?>
<div id="crewTab_recuerdos" class="pj-preview-tab-content">
    <h3 class="pj-tab-section-heading"><i class="fas fa-images"></i> Recuerdos del Grupo</h3>
    
    <div class="rpg-crew-memories-grid">
        <?php if (empty($memories)): ?>
            <p class="crew-manage-empty">Aún no hay recuerdos registrados en la bitácora del grupo.</p>
        <?php else: ?>
            <?php foreach ($memories as $index => $mem): ?>
                <div class="rpg-crew-memory-card" onclick="openMemoryModal(<?= htmlspecialchars(json_encode($mem)) ?>)">
                    <img src="<?= htmlspecialchars($mem['image'] ?: 'https://placehold.co/400x300/111/333?text=Recuerdo') ?>" class="rpg-crew-memory-img" alt="">
                    <div class="rpg-crew-memory-overlay">
                        <h4 class="rpg-crew-memory-title"><?= htmlspecialchars($mem['title']) ?></h4>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para ver un recuerdo en grande -->
<div id="modal_ver_recuerdo" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
    <div class="pj-modal pj-modal--lg crew-memory-modal">
        <div class="crew-memory-modal-hero">
            <img id="view_mem_img" src="" alt="" class="crew-memory-modal-img">
            <div class="crew-memory-modal-overlay-info">
                <h2 id="view_mem_title" class="crew-memory-modal-title"></h2>
            </div>
            <button onclick="document.getElementById('modal_ver_recuerdo').style.display='none'" class="crew-memory-modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="crew-memory-modal-body">
            <div id="view_mem_text"></div>
        </div>
    </div>
</div>


