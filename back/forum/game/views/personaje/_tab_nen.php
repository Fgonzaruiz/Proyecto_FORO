<?php
/**
 * Tab de Nen en la ficha de personaje.
 * Contexto: page.php (vía public/personaje.php).
 * Los colores dinámicos se aplican mediante data-attributes leídos por nen.js.
 */
declare(strict_types=1);

$nenState = game_get_nen_state((int)$char['id']);
if ($nenState):
    $typeName  = game_get_nen_type_label($nenState['nen_type']);
    $typeColor = game_get_nen_type_color($nenState['nen_type']);
?>
<div id="pjTab_nen" class="pj-preview-tab-content">
    <div class="rpg-nen-profile-header" data-nen-type-color="<?= htmlspecialchars($typeColor, ENT_QUOTES) ?>">
        <span class="rpg-nen-profile-type-label" data-nen-color-text="<?= htmlspecialchars($typeColor, ENT_QUOTES) ?>"><?= $typeName ?></span>
        <h2 class="rpg-nen-profile-type-name"><?= $typeName ?></h2>
        <?php if ($nenState['aura_color']): ?>
            <p class="rpg-nen-aura-color-note">
                Color de Aura: <strong data-nen-aura-color="<?= htmlspecialchars($nenState['aura_color'], ENT_QUOTES) ?>"><?= htmlspecialchars($nenState['aura_color']) ?></strong>
            </p>
        <?php endif; ?>
    </div>

    <h3 class="pj-tab-section-heading"><i class="fas fa-dumbbell"></i> Principios Fundamentales</h3>
    <div class="rpg-nen-principles-grid">
        <?php foreach ($nenState['principles'] as $p => $pInfo):
            $pName       = game_get_nen_principle_label($p);
            $pLevelLabel = game_get_nen_principle_level_label($pInfo['level']);
            $pct         = $pInfo['level'] * 25;
        ?>
            <div class="rpg-nen-principle-card">
                <div class="rpg-nen-principle-card-header">
                    <strong><?= $pName ?></strong>
                    <span class="rpg-nen-principle-level-tag"><?= $pLevelLabel ?></span>
                </div>
                <div class="rpg-nen-progress-bg">
                    <div class="rpg-nen-progress-fill" data-nen-color-bg="<?= htmlspecialchars($typeColor, ENT_QUOTES) ?>" data-width="<?= $pct ?>"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <h3 class="pj-tab-section-heading"><i class="fas fa-hand-sparkles"></i> Habilidades Únicas (Hatsu)</h3>
    <div class="rpg-nen-tab-abilities-list">
        <?php
        $approvedAbilities = array_filter($nenState['abilities'], static fn($a) => (int)$a['approved'] === 1);

        if (empty($approvedAbilities)):
        ?>
            <p class="rpg-shop-empty"><i class="fas fa-box-open"></i> Aún no se han registrado habilidades Hatsu aprobadas para este personaje.</p>
        <?php
        else:
            foreach ($approvedAbilities as $ability):
                $conds = json_decode($ability['conditions_json'] ?? '[]', true) ?: [];
        ?>
            <article class="rpg-nen-tab-ability-card">
                <div class="rpg-nen-tab-ability-header">
                    <h4 class="rpg-nen-profile-type-name"><?= htmlspecialchars($ability['name']) ?></h4>
                    <div class="rpg-nen-tab-ability-badges">
                        <span class="rpg-nen-tab-ability-rank"><?= htmlspecialchars($ability['rank']) ?></span>
                        <span class="rpg-nen-tab-ability-cost"><i class="fas fa-bolt"></i> <?= (int)$ability['nen_cost'] ?> PE</span>
                    </div>
                </div>
                <p class="rpg-nen-tab-ability-desc"><?= nl2br(htmlspecialchars($ability['description'])) ?></p>

                <?php if (!empty($conds)): ?>
                    <div class="rpg-nen-conditions-block">
                        <strong>Condiciones / Votos:</strong> <?= implode(', ', array_map('htmlspecialchars', $conds)) ?>
                    </div>
                <?php endif; ?>

                <?php if ($ability['card_id'] > 0): ?>
                    <div class="rpg-nen-card-link" data-nen-color-text="<?= htmlspecialchars($typeColor, ENT_QUOTES) ?>">
                        <i class="fas fa-id-card"></i> Vinculada a carta del Deck
                        <a href="#" class="rpg-shop-card--clickable" data-card-id="<?= (int)$ability['card_id'] ?>">Ver Carta</a>
                    </div>
                <?php endif; ?>
            </article>
        <?php
            endforeach;
        endif;
        ?>
    </div>
</div>
<?php endif; ?>
