<?php
/**
 * Tab PORTADA — dashboard integrado estilo Licencia de Cazador.
 */
declare(strict_types=1);
?>
<div id="pjTab_portada" class="pj-preview-tab-content active">

    <div class="hxh-portada">
        <span class="hxh-watermark-xx" aria-hidden="true"><?= $has_hunter_license ? '✕✕' : '◆' ?></span>
        <?php if ($has_hunter_license): ?>
        <span class="hxh-watermark-kanji" aria-hidden="true">猟</span>
        <?php endif; ?>

        <div class="hxh-portada-grid">

            <!-- Columna identidad -->
            <aside class="hxh-col hxh-col--identity">
                <div class="hxh-id-stat">
                    <span class="hxh-id-stat__kanji" aria-hidden="true">族</span>
                    <div class="hxh-id-stat__body">
                        <span class="hxh-id-stat__label">Raza</span>
                        <span class="hxh-id-stat__value"><?= htmlspecialchars((string)($char['race_name'] ?? 'Desconocida')) ?></span>
                    </div>
                </div>
                <div class="hxh-id-stat">
                    <span class="hxh-id-stat__kanji" aria-hidden="true">歳</span>
                    <div class="hxh-id-stat__body">
                        <span class="hxh-id-stat__label">Edad</span>
                        <span class="hxh-id-stat__value"><?= htmlspecialchars((string)($char['age'] ?? '?')) ?> años</span>
                    </div>
                </div>
                <div class="hxh-id-stat">
                    <span class="hxh-id-stat__kanji" aria-hidden="true">性</span>
                    <div class="hxh-id-stat__body">
                        <span class="hxh-id-stat__label">Género</span>
                        <span class="hxh-id-stat__value"><?= htmlspecialchars($char_gender) ?></span>
                    </div>
                </div>
                <div class="hxh-id-stat">
                    <span class="hxh-id-stat__kanji" aria-hidden="true">地</span>
                    <div class="hxh-id-stat__body">
                        <span class="hxh-id-stat__label">Origen</span>
                        <span class="hxh-id-stat__value"><?= htmlspecialchars((string)($char['origin'] ?? '?')) ?></span>
                    </div>
                </div>
                <div class="hxh-id-stat">
                    <span class="hxh-id-stat__kanji" aria-hidden="true">旗</span>
                    <div class="hxh-id-stat__body">
                        <span class="hxh-id-stat__label">Facción</span>
                        <span class="hxh-id-stat__value"><?= htmlspecialchars($char['faction'] ?: 'Civil') ?></span>
                    </div>
                </div>

                <div class="hxh-status-panel">
                    <div class="hxh-badges-row">
                        <span class="hxh-badge <?= $statusInfo[1] ?>">
                            <i class="fas fa-circle"></i> <?= $statusInfo[0] ?>
                        </span>
                        <?php if ($char['is_staff']): ?>
                        <span class="hxh-badge hxh-badge--staff"><i class="fas fa-star"></i> Staff</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($char['pb']) && $char['pb'] !== 'Desconocido'): ?>
                    <div class="hxh-pb-chip">
                        <i class="fas fa-theater-masks"></i> PB: <?= htmlspecialchars((string)$char['pb']) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($portada_rasgos)): ?>
                <div class="hxh-rasgos-panel">
                    <h3 class="hxh-panel-title"><i class="fas fa-star"></i> Rasgos</h3>
                    <ul class="hxh-rasgos-list">
                        <?php foreach ($portada_rasgos as $rasgo): ?>
                        <li><?= htmlspecialchars($rasgo) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </aside>

            <!-- Columna central: radar + avatar -->
            <main class="hxh-col hxh-col--core">
                <div class="hxh-radar-hub">
                    <svg id="hunterRadarChart"
                         data-cuerpo="<?= $cuerpo_sum ?>"
                         data-mente="<?= $mente_sum ?>"
                         data-espiritu="<?= $espiritu_sum ?>"
                         viewBox="0 0 320 320"
                         class="hxh-radar-svg"
                         aria-hidden="true">
                        <defs>
                            <filter id="hxhGlow" x="-30%" y="-30%" width="160%" height="160%">
                                <feGaussianBlur stdDeviation="4" result="coloredBlur"/>
                                <feMerge>
                                    <feMergeNode in="coloredBlur"/>
                                    <feMergeNode in="SourceGraphic"/>
                                </feMerge>
                            </filter>
                            <linearGradient id="hxhRingPv" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#dc2626"/>
                                <stop offset="100%" stop-color="#991b1b"/>
                            </linearGradient>
                            <linearGradient id="hxhRingPe" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#06b6d4"/>
                                <stop offset="100%" stop-color="#0891b2"/>
                            </linearGradient>
                        </defs>
                        <!-- Anillo exterior segmentado -->
                        <circle cx="160" cy="160" r="148" fill="none" stroke="url(#hxhRingPv)" stroke-width="6"
                                stroke-dasharray="<?= round($pv / max(1, $pv + $pe) * 880) ?> 880"
                                transform="rotate(-90 160 160)" opacity="0.85"/>
                        <circle cx="160" cy="160" r="148" fill="none" stroke="url(#hxhRingPe)" stroke-width="6"
                                stroke-dasharray="<?= round($pe / max(1, $pv + $pe) * 880) ?> 880"
                                stroke-dashoffset="<?= round(-$pv / max(1, $pv + $pe) * 880) ?>"
                                transform="rotate(-90 160 160)" opacity="0.85"/>
                        <!-- Rejilla triangular -->
                        <polygon points="160,35 55,210 265,210" class="hxh-radar-grid hxh-radar-grid--outer"/>
                        <polygon points="160,78 98,185 222,185" class="hxh-radar-grid hxh-radar-grid--mid"/>
                        <polygon points="160,121 129,152 191,152" class="hxh-radar-grid hxh-radar-grid--inner"/>
                        <line x1="160" y1="160" x2="160" y2="35"  class="hxh-radar-axis"/>
                        <line x1="160" y1="160" x2="55"  y2="210" class="hxh-radar-axis"/>
                        <line x1="160" y1="160" x2="265" y2="210" class="hxh-radar-axis"/>
                        <polygon id="hunterRadarValPoly" points="160,160 160,160 160,160"
                                 class="hxh-radar-poly" filter="url(#hxhGlow)"/>
                        <circle id="hunterRadarNodeEsp" cx="160" cy="35"  r="5" class="hxh-radar-node hxh-radar-node--espiritu"/>
                        <circle id="hunterRadarNodeCue" cx="55"  cy="210" r="5" class="hxh-radar-node hxh-radar-node--cuerpo"/>
                        <circle id="hunterRadarNodeMen" cx="265" cy="210" r="5" class="hxh-radar-node hxh-radar-node--mente"/>
                        <text x="160" y="22"  text-anchor="middle" class="hxh-radar-lbl hxh-radar-lbl--espiritu">ESPÍRITU</text>
                        <text x="38"  y="228" text-anchor="middle" class="hxh-radar-lbl hxh-radar-lbl--cuerpo">CUERPO</text>
                        <text x="282" y="228" text-anchor="middle" class="hxh-radar-lbl hxh-radar-lbl--mente">MENTE</text>
                    </svg>

                    <div class="hxh-radar-avatar">
                        <img src="<?= htmlspecialchars($pj_radar_url, ENT_QUOTES) ?>"
                             alt="Nexus Nen — <?= htmlspecialchars($char['name']) ?>"
                             class="hxh-radar-avatar__img"
                             title="Retrato Nexus (editable en Mis Personajes)">
                        <span class="hxh-radar-avatar__rank <?= htmlspecialchars($globalRankClass) ?>"><?= htmlspecialchars($globalRank) ?></span>
                    </div>
                </div>

                <div class="hxh-pillar-scores hxh-pillar-scores--center">
                    <div class="hxh-pillar-score hxh-pillar-score--espiritu">
                        <span><?= $espiritu_sum ?></span><small>Espíritu</small>
                    </div>
                    <div class="hxh-pillar-score hxh-pillar-score--cuerpo">
                        <span><?= $cuerpo_sum ?></span><small>Cuerpo</small>
                    </div>
                    <div class="hxh-pillar-score hxh-pillar-score--mente">
                        <span><?= $mente_sum ?></span><small>Mente</small>
                    </div>
                    <div class="hxh-pillar-score hxh-pillar-score--total">
                        <span><?= $total_sum ?></span><small>/ 72</small>
                    </div>
                </div>

                <div class="hxh-vitals-row hxh-vitals-row--portada">
                    <div class="hxh-vital hxh-vital--pv">
                        <i class="fas fa-heartbeat"></i>
                        <div class="hxh-vital-info">
                            <span class="hxh-vital-label">Vida (PV)</span>
                            <span class="hxh-vital-num"><?= $pv ?></span>
                        </div>
                    </div>
                    <div class="hxh-vital hxh-vital--pe">
                        <i class="fas fa-fire"></i>
                        <div class="hxh-vital-info">
                            <span class="hxh-vital-label">Energía (PE)</span>
                            <span class="hxh-vital-num"><?= $pe ?></span>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Columna retrato principal -->
            <aside class="hxh-col hxh-col--portrait">
                <div class="hxh-portrait-frame">
                    <img src="<?= htmlspecialchars($pj_portrait_url, ENT_QUOTES) ?>"
                         alt="<?= htmlspecialchars($char['name']) ?>"
                         class="hxh-portrait-frame__img">
                    <div class="hxh-portrait-frame__fade"></div>
                </div>
            </aside>

        </div>
    </div>

</div>
