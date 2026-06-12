<div id="crewTab_navio" class="pj-preview-tab-content">
    <h3 class="pj-tab-section-heading"><i class="fas fa-ship"></i> Nuestro Navío</h3>
    
    <div class="rpg-crew-ship-container">
        <?php if (!empty($crew['ship_image_url'])): ?>
            <div class="rpg-crew-ship-image-wrapper">
                <img src="<?= htmlspecialchars($crew['ship_image_url']) ?>" alt="Navío" class="rpg-crew-ship-image">
            </div>
        <?php endif; ?>
        
        <div class="rpg-crew-ship-details">
            <h2 class="rpg-crew-ship-name"><?= htmlspecialchars($crew['ship_name'] ?: 'Sin nombre registrado') ?></h2>
            
            <?php if (!empty($crew['ship_data'])): ?>
                <div class="pj-story-box">
                    <?= nl2br(htmlspecialchars($crew['ship_data'])) ?>
                </div>
            <?php else: ?>
                <p class="crew-manage-empty">Aún no hay detalles registrados sobre el barco de la tripulación.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
