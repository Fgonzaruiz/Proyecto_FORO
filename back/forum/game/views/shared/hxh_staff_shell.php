<?php
/**
 * Shell expediente staff HXH.
 * Variables: $staff_label, $pj_name, $staff_level, $back_url (optional)
 */
declare(strict_types=1);
$staff_label = $staff_label ?? 'Staff';
$pj_name = $pj_name ?? '';
$staff_level = (int)($staff_level ?? 0);
$back_url = $back_url ?? '';
?>
<div class="hxh-staff-shell">
    <div class="hxh-license-header">
        <div class="hxh-license-header__brand">
            <span class="hxh-license-header__xx" aria-hidden="true">✕</span>
            <div class="hxh-license-header__titles">
                <span class="hxh-license-stamp">ZONA STAFF · NIVEL <?= (int)$staff_level ?></span>
                <h1 class="hxh-license-name">Panel <?= htmlspecialchars($staff_label) ?></h1>
                <?php if ($pj_name !== ''): ?>
                <p class="hxh-license-epithet">Operando como <strong><?= htmlspecialchars($pj_name) ?></strong></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="hxh-license-header__meta">
            <?php if ($back_url !== ''): ?>
            <a href="<?= htmlspecialchars($back_url) ?>" class="rpg-btn--secondary rpg-btn--sm">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <?php endif; ?>
            <span class="hxh-license-id-badge">STF–<?= str_pad((string)$staff_level, 2, '0', STR_PAD_LEFT) ?></span>
        </div>
    </div>
    <div class="hxh-page-shell__content pj-preview-tab-content">
