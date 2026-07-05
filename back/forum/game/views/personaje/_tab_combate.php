<?php
/**
 * Tab COMBATE — atributos detallados, stats derivados, grid de disciplinas.
 */
declare(strict_types=1);

$pillarConfig = [
    'cuerpo'   => ['Pilar Cuerpo',   'fa-dumbbell', $cuerpo_keys],
    'mente'    => ['Pilar Mente',     'fa-brain',    $mente_keys],
    'espiritu' => ['Pilar Espíritu',  'fa-bahai',    $espiritu_keys],
];
?>
<div id="pjTab_combate" class="pj-preview-tab-content">

    <!-- Disciplinas estilo Gaiden -->
    <div class="hxh-section">
        <div class="hxh-section-header">
            <span class="hxh-section-line"></span>
            <span class="hxh-section-title-text"><i class="fas fa-fist-raised"></i> Disciplinas Marciales</span>
            <span class="hxh-section-line"></span>
        </div>
        <div class="hxh-discipline-grid">
            <?php foreach ($disciplina_catalog as $catDisc):
                $slug = (string)($catDisc['slug'] ?? '');
                $owned = $ownedDiscMap[$slug] ?? null;
                $isOwned = $owned !== null;
                $cat = (string)($catDisc['category'] ?? 'default');
                $grad = $discCategoryGradients[$cat] ?? $discCategoryGradients['default'];
                $rankLabel = $isOwned ? (string)($owned['rank_label'] ?? 'I') : '—';
                $cardClass = $isOwned ? 'hxh-discipline-card--owned' : 'hxh-discipline-card--locked';
            ?>
            <article class="hxh-discipline-card <?= $cardClass ?> hxh-discipline-cat--<?= htmlspecialchars(preg_replace('/[^a-z0-9_-]/', '', strtolower($cat)) ?: 'default', ENT_QUOTES) ?>">
                <?php if (!$isOwned): ?>
                <span class="hxh-discipline-card__lock" aria-hidden="true"><i class="fas fa-lock"></i></span>
                <?php endif; ?>
                <div class="hxh-discipline-card__head">
                    <span class="hxh-discipline-card__grade"><?= htmlspecialchars($rankLabel) ?></span>
                    <i class="fas <?= htmlspecialchars((string)($catDisc['icon'] ?? 'fa-crosshairs')) ?> hxh-discipline-card__icon"></i>
                </div>
                <h4 class="hxh-discipline-card__name"><?= htmlspecialchars((string)($catDisc['name'] ?? '')) ?></h4>
                <p class="hxh-discipline-card__desc"><?= htmlspecialchars(strlen((string)($catDisc['description'] ?? '')) > 80 ? substr((string)$catDisc['description'], 0, 80) . '…' : (string)($catDisc['description'] ?? '')) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Stats derivados -->
    <div class="hxh-section">
        <div class="hxh-section-header">
            <span class="hxh-section-line"></span>
            <span class="hxh-section-title-text"><i class="fas fa-chart-bar"></i> Estadísticas de Combate</span>
            <span class="hxh-section-line"></span>
        </div>
        <div class="hxh-combat-stats-grid">
            <?php
            $half = (int)ceil(count($combatDerived) / 2);
            $colA = array_slice($combatDerived, 0, $half);
            $colB = array_slice($combatDerived, $half);
            foreach ([$colA, $colB] as $col):
            ?>
            <div class="hxh-combat-stats-col">
                <?php foreach ($col as [$label, $value]): ?>
                <div class="hxh-combat-stat-bar">
                    <span class="hxh-combat-stat-bar__label"><?= htmlspecialchars($label) ?></span>
                    <span class="hxh-combat-stat-bar__value"><?= htmlspecialchars((string)$value) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Atributos base por pilar -->
    <div class="hxh-section">
        <div class="hxh-section-header">
            <span class="hxh-section-line"></span>
            <span class="hxh-section-title-text"><i class="fas fa-sliders-h"></i> Atributos Base</span>
            <span class="hxh-section-line"></span>
        </div>
        <div class="hxh-attrs-grid">
            <?php foreach ($pillarConfig as $pillar => [$pillarName, $pillarIcon, $pillarKeys]): ?>
            <div class="hxh-pillar-block hxh-pillar-block--<?= $pillar ?>">
                <h4 class="hxh-pillar-heading">
                    <i class="fas <?= $pillarIcon ?>"></i> <?= $pillarName ?>
                </h4>
                <?php foreach ($pillarKeys as $key):
                    $meta      = $statMetaMap[$key];
                    $trained   = (int)($ctx['trained'][$key] ?? 1);
                    $effRank   = (int)($ctx['effective_ranks'][$key] ?? 1);
                    $effLabel  = (string)($ctx['display'][$key] ?? 'D');
                    $rankClass = \Game\Shared\StatScale::rankDisplayCssClass($effRank);
                ?>
                <div class="hxh-stat-row">
                    <div class="hxh-stat-icon hxh-stat-icon--<?= $pillar ?>">
                        <i class="fas <?= $meta[1] ?>"></i>
                    </div>
                    <div class="hxh-stat-body">
                        <div class="hxh-stat-top">
                            <span class="hxh-stat-name"><?= $meta[0] ?></span>
                            <span class="hxh-stat-rank-badge <?= htmlspecialchars($rankClass) ?>"><?= htmlspecialchars($effLabel) ?></span>
                        </div>
                        <div class="hxh-stat-bar-track">
                            <div class="hxh-stat-bar-fill hxh-stat-bar-fill--<?= $pillar ?> hxh-stat-bar-fill--r<?= min(6, max(1, $trained)) ?>"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Oficios en combate -->
    <?php if (!empty($sidebar_oficios)): ?>
    <div class="hxh-section">
        <div class="hxh-section-header">
            <span class="hxh-section-line"></span>
            <span class="hxh-section-title-text"><i class="fas fa-briefcase"></i> Oficios Activos</span>
            <span class="hxh-section-line"></span>
        </div>
        <div class="hxh-skills-grid">
            <?php foreach ($sidebar_oficios as $of): ?>
            <div class="hxh-skills-col">
                <div class="hxh-skill-item">
                    <span class="hxh-skill-name"><i class="fas <?= htmlspecialchars((string)($of['icon'] ?? 'fa-briefcase')) ?>"></i> <?= htmlspecialchars((string)($of['name'] ?? '')) ?></span>
                    <span class="hxh-skill-grade"><?= htmlspecialchars((string)($of['rank_label'] ?? 'I')) ?></span>
                </div>
                <?php if (!empty($of['description'])): ?>
                <p class="hxh-skills-empty"><?= htmlspecialchars((string)$of['description']) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
