<div class="pj-tabs-nav-container">
    <div class="pj-tabs-nav">
        <button class="pj-preview-tab active" onclick="switchCrewTab('bio', this)">
            <i class="fas fa-book-open"></i> Información
        </button>
        <button class="pj-preview-tab" onclick="switchCrewTab('miembros', this)">
            <i class="fas fa-users"></i> Tripulación
        </button>
        <button class="pj-preview-tab" onclick="switchCrewTab('navio', this)">
            <i class="fas fa-ship"></i> Navío
        </button>
        <button class="pj-preview-tab" onclick="switchCrewTab('territorios', this)">
            <i class="fas fa-map-marked-alt"></i> Territorios
        </button>
        <button class="pj-preview-tab" onclick="switchCrewTab('recuerdos', this)">
            <i class="fas fa-images"></i> Recuerdos
        </button>
        
        <?php if ($is_captain): ?>
        <button class="pj-preview-tab crew-tab-btn-gestion" onclick="switchCrewTab('gestion', this)">
            <i class="fas fa-cog"></i> Gestión
            <?php if ($aspirant_count > 0): ?>
                <span class="crew-tab-notif"><?= $aspirant_count ?></span>
            <?php endif; ?>
        </button>
        <?php endif; ?>
    </div>
</div>
