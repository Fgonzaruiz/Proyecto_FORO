<div class="pj-tabs-nav-container">
    <div class="pj-preview-tabs pj-tabs-nav">
        <div class="pj-preview-tab active" onclick="switchCrewTab('bio', this)">
            <i class="fas fa-book-open"></i> Información
        </div>
        <div class="pj-preview-tab" onclick="switchCrewTab('miembros', this)">
            <i class="fas fa-users"></i> Miembros
        </div>
        <div class="pj-preview-tab" onclick="switchCrewTab('recuerdos', this)">
            <i class="fas fa-images"></i> Recuerdos
        </div>
        
        <?php if ($is_leader): ?>
        <div class="pj-preview-tab crew-tab-btn-gestion" onclick="switchCrewTab('gestion', this)">
            <i class="fas fa-cog"></i> Gestión
            <?php if ($aspirant_count > 0): ?>
                <span class="crew-tab-notif"><?= $aspirant_count ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
