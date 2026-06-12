<div id="crewTab_territorios" class="pj-preview-tab-content">
    <h3 class="pj-tab-section-heading">Territorios Controlados</h3>

    <div class="crew-territory-grid">
        <?php foreach ($territories as $t): ?>
            <div class="crew-territory-card">
                <div class="crew-territory-img-container">
                    <img src="<?= htmlspecialchars($t['image_url'] ?: 'https://placehold.co/400x200?text=Territorio') ?>" class="crew-territory-img-fallback" alt="">
                </div>
                <div class="crew-territory-body">
                    <h3 class="crew-territory-name"><?= htmlspecialchars($t['forum_name']) ?></h3>
                    <p class="crew-territory-desc">
                        <?= htmlspecialchars(mb_substr($t['description'], 0, 100)) ?><?= strlen($t['description']) > 100 ? '...' : '' ?>
                    </p>
                    <div class="crew-territory-meta">
                        <?php if ($t['climate']): ?>
                            <span class="crew-territory-tag"><i class="fas fa-cloud-sun"></i> <?= htmlspecialchars($t['climate']) ?></span>
                        <?php endif; ?>
                        <?php if ($t['danger_level']): ?>
                            <span class="crew-territory-danger crew-territory-danger--<?= $t['danger_level'] ?>">
                                Peligro: Nv. <?= $t['danger_level'] ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($territories)): ?>
            <p class="crew-grid-empty">Esta tripulación no controla ningún territorio actualmente.</p>
        <?php endif; ?>
    </div>
</div>
