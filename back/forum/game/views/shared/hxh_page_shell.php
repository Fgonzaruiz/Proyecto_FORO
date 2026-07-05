<?php
/**
 * Shell expediente HXH para páginas game públicas.
 * Variables: $hxh_stamp, $hxh_title, $hxh_subtitle, $hxh_badge, $hxh_icon (optional)
 */
declare(strict_types=1);
$hxh_stamp = $hxh_stamp ?? 'EXPEDIENTE';
$hxh_title = $hxh_title ?? '';
$hxh_subtitle = $hxh_subtitle ?? '';
$hxh_badge = $hxh_badge ?? '';
$hxh_icon = $hxh_icon ?? '◆';
?>
<div class="hxh-foro-shell hxh-page-shell">
    <div class="hxh-license-header">
        <div class="hxh-license-header__brand">
            <span class="hxh-license-header__xx" aria-hidden="true"><?= htmlspecialchars($hxh_icon) ?></span>
            <div class="hxh-license-header__titles">
                <span class="hxh-license-stamp"><?= htmlspecialchars($hxh_stamp) ?></span>
                <?php if ($hxh_title !== ''): ?>
                <h1 class="hxh-license-name"><?= htmlspecialchars($hxh_title) ?></h1>
                <?php endif; ?>
                <?php if ($hxh_subtitle !== ''): ?>
                <p class="hxh-license-epithet"><?= htmlspecialchars($hxh_subtitle) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($hxh_badge !== ''): ?>
        <div class="hxh-license-header__meta">
            <span class="hxh-license-id-badge"><?= htmlspecialchars($hxh_badge) ?></span>
        </div>
        <?php endif; ?>
    </div>
    <div class="hxh-page-shell__content pj-preview-tab-content">
