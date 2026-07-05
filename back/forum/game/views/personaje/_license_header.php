<?php
/**
 * Cabecera integrada — sello según tipo de personaje (no todos son cazadores).
 */
declare(strict_types=1);
?>
<div class="hxh-license-header">
    <div class="hxh-license-header__brand">
        <span class="hxh-license-header__xx" aria-hidden="true"><?= $has_hunter_license ? '✕✕' : '◆' ?></span>
        <div class="hxh-license-header__titles">
            <span class="hxh-license-stamp"><?= htmlspecialchars($doc_stamp_label) ?></span>
            <h1 class="hxh-license-name"><?= htmlspecialchars($char['name']) ?></h1>
            <?php if ($char_epithet !== ''): ?>
            <p class="hxh-license-epithet"><?= htmlspecialchars($char_epithet) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="hxh-license-header__meta">
        <span class="hxh-license-id-badge"><?= htmlspecialchars($doc_id_badge) ?></span>
        <?php if (!empty($can_edit_this_pj)): ?>
            <?php if ($char['status'] !== 'aprobada' && $char['status'] !== 'muerto'): ?>
                <a href="<?= htmlspecialchars($bburl) ?>/game/public/crear_personaje.php?pj_id=<?= (int)$char['id'] ?>" class="rpg-btn--secondary rpg-btn--sm">
                    <i class="fas fa-edit"></i> Editar Ficha
                </a>
            <?php else: ?>
                <a href="<?= htmlspecialchars($bburl) ?>/game/public/mis_personajes.php?edit_pj=<?= (int)$char['id'] ?>" class="rpg-btn--secondary rpg-btn--sm">
                    <i class="fas fa-user-edit"></i> Editar Retratos
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
