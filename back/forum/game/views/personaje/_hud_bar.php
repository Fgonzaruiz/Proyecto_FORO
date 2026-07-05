<?php
/**
 * Barra HUD inferior — recursos del cazador.
 */
declare(strict_types=1);
?>
<div class="hxh-hud-bar" role="contentinfo" aria-label="Recursos del personaje">
    <div class="hxh-hud-item hxh-hud-item--nivel">
        <i class="fas fa-layer-group" aria-hidden="true"></i>
        <span class="hxh-hud-label">Nivel</span>
        <span class="hxh-hud-value"><?= $pj_nivel ?></span>
    </div>
    <div class="hxh-hud-item hxh-hud-item--pp">
        <i class="fas fa-bolt" aria-hidden="true"></i>
        <span class="hxh-hud-label">PP</span>
        <span class="hxh-hud-value" id="val_available_pp"><?= number_format($pj_pp, 0, ',', '.') ?></span>
    </div>
    <div class="hxh-hud-item hxh-hud-item--rank">
        <i class="fas fa-medal" aria-hidden="true"></i>
        <span class="hxh-hud-label">Rango</span>
        <span class="hxh-hud-value <?= htmlspecialchars($globalRankClass) ?>"><?= htmlspecialchars($globalRank) ?></span>
    </div>
    <div class="hxh-hud-item hxh-hud-item--jenny">
        <i class="fas fa-coins" aria-hidden="true"></i>
        <span class="hxh-hud-label">Jenny</span>
        <span class="hxh-hud-value"><?= number_format($pj_jenny, 0, ',', '.') ?></span>
    </div>
    <div class="hxh-hud-item hxh-hud-item--pv">
        <i class="fas fa-heartbeat" aria-hidden="true"></i>
        <span class="hxh-hud-label">PV</span>
        <span class="hxh-hud-value"><?= $pv ?></span>
    </div>
    <div class="hxh-hud-item hxh-hud-item--pe">
        <i class="fas fa-fire" aria-hidden="true"></i>
        <span class="hxh-hud-label">PE</span>
        <span class="hxh-hud-value"><?= $pe ?></span>
    </div>
</div>
