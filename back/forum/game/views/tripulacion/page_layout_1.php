<?php
/**
 * Vista inmersiva de ficha de grupo — orquestador de partials.
 */
require __DIR__ . '/../personaje/_styles.php';
require __DIR__ . '/_styles.php';
?>
<div class="rpg-crew-immersive-page">
    <!-- HERO BANNER -->
    <div class="rpg-crew-hero">
        <img src="<?= htmlspecialchars($crew['image_url'] ?: 'https://placehold.co/1200x400/111111/333333?text=Sin+Bandera') ?>" alt="Bandera" class="rpg-crew-hero-img">
        <div class="rpg-crew-hero-overlay">
            <h1 class="rpg-crew-hero-title"><?= htmlspecialchars($crew['name']) ?></h1>
            <?php if (!empty($crew['motto'])): ?>
                <p class="rpg-crew-hero-motto">"<?= htmlspecialchars($crew['motto']) ?>"</p>
            <?php endif; ?>
            <div class="rpg-crew-hero-factions">
                <?php 
                $factions = array_filter(array_map('trim', explode(',', $crew['factions'] ?? '')));
                if(empty($factions)) $factions = ['Sin Afiliación'];
                foreach ($factions as $f): ?>
                    <span class="rpg-badge rpg-badge--dark"><?= htmlspecialchars($f) ?></span>
                <?php endforeach; ?>
                
                <?php if ($can_join): ?>
                    <button type="button" class="rpg-action-btn rpg-btn-primary rpg-ml-auto" onclick="submitJoinRequest()">
                        <i class="fas fa-user-plus"></i> Solicitar Unirse
                    </button>
                <?php elseif ($is_pending): ?>
                    <span class="rpg-badge rpg-badge--dark rpg-ml-auto"><i class="fas fa-clock"></i> Petición Pendiente</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($crew['ost_url'])): ?>
                <div class="rpg-crew-hero-ost">
                    <audio id="crew-ost-audio" loop src="<?= htmlspecialchars($crew['ost_url']) ?>"></audio>
                    <button type="button" class="rpg-btn-sm rpg-btn--dark" onclick="toggleCrewOst(this)">
                        <i class="fas fa-play"></i> OST
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="rpg-crew-immersive-content">
        <?php require __DIR__ . '/_tabs_nav.php'; ?>
        
        <div class="pj-page-content rpg-crew-tabs-container">
            <?php require __DIR__ . '/_tab_bio.php'; ?>
            <?php require __DIR__ . '/_tab_miembros.php'; ?>
            <?php require __DIR__ . '/_tab_recuerdos.php'; ?>
            <?php if ($is_leader): ?>
                <?php require __DIR__ . '/_tab_gestion.php'; ?>
                <?php require __DIR__ . '/_modals.php'; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/_scripts.php'; ?>
