<?php
/**
 * Tab EXPEDIENTE — historia, biografía, avatar integrado, acompañantes.
 */
declare(strict_types=1);
?>
<div id="pjTab_expediente" class="pj-preview-tab-content">

    <div class="hxh-section">
        <div class="hxh-section-header">
            <span class="hxh-section-line"></span>
            <span class="hxh-section-title-text"><i class="fas fa-book-open"></i> Historia</span>
            <span class="hxh-section-line"></span>
        </div>
        <div class="hxh-parchment-panel hxh-parchment-panel--tall">
            <div class="hxh-bio-scroll hxh-bio-scroll--tall">
                <?= nl2br(htmlspecialchars($char['history'] ?: 'Sin historia registrada.')) ?>
            </div>
        </div>
    </div>

    <div class="hxh-section">
        <div class="hxh-section-header">
            <span class="hxh-section-line"></span>
            <span class="hxh-section-title-text"><i class="fas fa-file-invoice"></i> Expediente Biográfico</span>
            <span class="hxh-section-line"></span>
        </div>

        <div class="hxh-bio-grid hxh-bio-grid--triple">
            <div class="hxh-parchment-panel">
                <h5 class="hxh-bio-col-title"><i class="fas fa-user"></i> Apariencia</h5>
                <div class="hxh-bio-scroll">
                    <?= nl2br(htmlspecialchars($char['physique'] ?: 'Sin registrar.')) ?>
                </div>
            </div>
            <div class="hxh-parchment-panel">
                <h5 class="hxh-bio-col-title"><i class="fas fa-brain"></i> Personalidad</h5>
                <div class="hxh-bio-scroll">
                    <?= nl2br(htmlspecialchars($char['psychology'] ?: ($char['desc'] ?: 'Sin descripción registrada.'))) ?>
                </div>
            </div>
            <div class="hxh-parchment-panel">
                <h5 class="hxh-bio-col-title"><i class="fas fa-sticky-note"></i> Extra</h5>
                <div class="hxh-bio-scroll">
                    <?= nl2br(htmlspecialchars($char['extras'] ?: ($char['details'] ?: 'Sin notas extras.'))) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="hxh-section">
        <div class="hxh-section-header">
            <span class="hxh-section-line"></span>
            <span class="hxh-section-title-text"><i class="fas fa-paw"></i> Mascotas y Acompañantes</span>
            <span class="hxh-section-line"></span>
        </div>
        <?php if (empty($companions)): ?>
            <div class="hxh-parchment-panel hxh-parchment-panel--empty">
                <p class="hxh-skills-empty">Sin acompañantes registrados en la bitácora.</p>
            </div>
        <?php else: ?>
            <div class="hxh-companion-grid">
                <?php foreach ($companions as $comp): ?>
                <article class="hxh-companion-card">
                    <img src="<?= htmlspecialchars((string)($comp['image'] ?? 'https://placehold.co/80x80/1a3d2e/e8dcc8?text=?'), ENT_QUOTES) ?>"
                         alt=""
                         class="hxh-companion-card__img">
                    <span class="hxh-companion-card__name"><?= htmlspecialchars((string)($comp['name'] ?? '?')) ?></span>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>
