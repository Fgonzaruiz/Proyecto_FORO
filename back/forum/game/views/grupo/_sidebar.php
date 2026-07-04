<div class="pj-sidebar">
    <div class="pj-sidebar-avatar">
        <?php if (!empty($crew['image_url'])): ?>
            <img src="<?= htmlspecialchars($crew['image_url']) ?>" class="crew-banner-img" alt="Jolly Roger">
        <?php else: ?>
            <div class="crew-banner-img crew-banner-img-placeholder">
                <i class="fas fa-skull-crossbones fa-4x"></i>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="pj-sidebar-body">
        <h1 class="crew-sidebar-name"><?= htmlspecialchars($crew['name']) ?></h1>
        <?php if (!empty($crew['motto'])): ?>
            <p class="crew-sidebar-motto">"<?= htmlspecialchars($crew['motto']) ?>"</p>
        <?php endif; ?>

        <div class="pj-sidebar-badges">
            <span class="pj-badge pj-badge--<?= $crew['status'] === 'aprobada' ? 'ok' : 'warn' ?>">
                <i class="fas <?= $crew['status'] === 'aprobada' ? 'fa-check-circle' : 'fa-clock' ?>"></i> 
                <?= htmlspecialchars(ucfirst($crew['status'])) ?>
            </span>
        </div>

        <?php if (!empty($crew['ost_url'])): ?>
            <div class="crew-ost-player">
                <audio id="crew-ost-audio" loop src="<?= htmlspecialchars($crew['ost_url']) ?>"></audio>
                <button type="button" class="crew-ost-btn" onclick="toggleCrewOst(this)">
                    <i class="fas fa-play"></i> OST
                </button>
            </div>
        <?php endif; ?>

        <?php if (!empty($crew['leader_pj_id_check'])): ?>
        <div class="pj-sidebar-info">
            <h3 class="pj-stats-heading"><i class="fas fa-crown"></i> Líder</h3>
            <div class="rpg-crew-leader-row">
                <a href="<?= htmlspecialchars($bburl) ?>/game/public/personaje.php?pj=<?= $crew['leader_pj_id_check'] ?>">
                    <img src="<?= htmlspecialchars($crew['leader_avatar'] ?: 'https://placehold.co/40x40') ?>" class="rpg-crew-avatar" alt="Líder">
                </a>
                <div>
                    <a href="<?= htmlspecialchars($bburl) ?>/game/public/personaje.php?pj=<?= $crew['leader_pj_id_check'] ?>" class="rpg-text-primary-color crew-leader-link">
                        <?= htmlspecialchars($crew['leader_name']) ?>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="crew-stat-box">
            <div class="crew-stat-item">
                <i class="fas fa-users"></i>
                <span class="crew-stat-label">Miembros</span>
                <span class="crew-stat-value"><?= $member_count ?></span>
            </div>
            <div class="crew-stat-item crew-stat-item--wide">
                <i class="fas fa-calendar-alt"></i>
                <span class="crew-stat-label">Fundación</span>
                <span class="crew-stat-value crew-stat-value--small"><?= $founded_date ?></span>
            </div>
        </div>

        <div class="crew-sidebar-actions">
            <a href="<?= htmlspecialchars($bburl) ?>/game/public/biblioteca_grupos.php" class="rpg-action-btn rpg-btn-primary crew-btn-back">
                <i class="fas fa-arrow-left"></i> Volver a Biblioteca
            </a>
        </div>
    </div>
</div>
