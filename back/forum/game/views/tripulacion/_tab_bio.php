<div id="crewTab_bio" class="pj-preview-tab-content active">
    <div class="crew-stat-box rpg-mb-24">
        <div class="crew-stat-item">
            <i class="fas fa-users"></i>
            <span class="crew-stat-label">Miembros</span>
            <span class="crew-stat-value"><?= $member_count ?></span>
        </div>
        <div class="crew-stat-item">
            <i class="fas fa-map-marked-alt"></i>
            <span class="crew-stat-label">Territorios</span>
            <span class="crew-stat-value"><?= $territory_count ?></span>
        </div>
        <div class="crew-stat-item crew-stat-item--wide">
            <i class="fas fa-crown"></i>
            <span class="crew-stat-label">Capitán</span>
            <span class="crew-stat-value rpg-text-lg">
                <?php if (!empty($crew['leader_pj_id_check'])): ?>
                    <a href="<?= htmlspecialchars($bburl) ?>/game/public/personaje.php?pj=<?= $crew['leader_pj_id_check'] ?>" class="rpg-link-inherit">
                        <?= htmlspecialchars($crew['leader_name']) ?>
                    </a>
                <?php else: ?>
                    Sin capitán
                <?php endif; ?>
            </span>
        </div>
        <div class="crew-stat-item crew-stat-item--wide">
            <i class="fas fa-calendar-alt"></i>
            <span class="crew-stat-label">Fundación</span>
            <span class="crew-stat-value crew-stat-value--small"><?= $founded_date ?></span>
        </div>
    </div>

    <h3 class="pj-tab-section-heading"><i class="fas fa-scroll"></i> Descripción / Historia</h3>
    <div class="pj-scroll-box pj-scroll-box--bio">
        <?php if (!empty($crew['description'])): ?>
            <?= nl2br(htmlspecialchars($crew['description'])) ?>
        <?php else: ?>
            <p class="crew-bio-empty">No hay información disponible sobre esta tripulación todavía.</p>
        <?php endif; ?>
    </div>

    <h3 class="pj-tab-section-heading"><i class="fas fa-handshake"></i> Diplomacia y Red de Alianzas</h3>
    <?php 
    $rels_data = json_decode($crew['relations'] ?? '', true);
    $has_structured_relations = is_array($rels_data) && (isset($rels_data['relaciones']) || isset($rels_data['relations']));
    if ($has_structured_relations):
        $relaciones = $rels_data['relaciones'] ?? $rels_data['relations'] ?? [];
    ?>
        <?php if (empty($relaciones)): ?>
            <div class="pj-scroll-box pj-scroll-box--bio">
                <p class="crew-bio-empty">No hay relaciones diplomáticas registradas.</p>
            </div>
        <?php else: ?>
            <div class="pj-network-wrap">
                <div class="pj-view-toggles">
                    <button id="btn-view-graph" type="button" class="pj-view-toggle is-active" onclick="switchCrewNetworkView('graph')" title="Mapa de Relaciones"><i class="fas fa-project-diagram"></i></button>
                    <button id="btn-view-list" type="button" class="pj-view-toggle" onclick="switchCrewNetworkView('list')" title="Vista Lista"><i class="fas fa-th-large"></i></button>
                </div>
                
                <div id="pj-view-graph" class="pj-view-graph">
                    <div id="pj-network-container" class="pj-network-container"></div>
                </div>
                
                <div id="pj-view-list" class="pj-view-list is-hidden">
                    <div class="pj-scroll-box pj-scroll-box--network">
                        <div class="pj-relations-grid">
                        <?php foreach ($relaciones as $rel):
                            $tags = $rel['tags'] ?? [];
                            if (empty($tags) && !empty($rel['relation'])) $tags = [$rel['relation']];
                            if (!is_array($tags)) $tags = [$tags];
                        ?>
                            <?php if (!empty($rel['pj_id']) && empty($rel['is_faction'])): ?>
                                <a href="tripulacion.php?id=<?= htmlspecialchars((string)$rel['pj_id']) ?>" class="pj-relation-card-link">
                            <?php endif; ?>
                            <div class="pj-relation-card">
                                <?php if (!empty($rel['is_faction'])): ?>
                                    <div class="pj-relation-npc-badge crew-faction-badge">FACCIÓN</div>
                                <?php endif; ?>
                                <img src="<?= htmlspecialchars($rel['image'] ?: 'https://placehold.co/70x70?text=Jolly') ?>" class="pj-relation-img" alt="">
                                <div class="pj-relation-name"><?= htmlspecialchars($rel['name']) ?></div>
                                <div class="pj-relation-tag-wrap">
                                    <?php foreach ($tags as $t): $t = trim($t); if (!$t) continue; 
                                        $c = $tag_colors[$t] ?? '#C62828'; ?>
                                        <span class="pj-relation-tag" data-color="<?= $c ?>"><?= htmlspecialchars($t) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (!empty($rel['desc'])): ?>
                                    <div class="pj-relation-desc"><?= htmlspecialchars($rel['desc']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($rel['pj_id']) && empty($rel['is_faction'])): ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="pj-scroll-box pj-scroll-box--bio">
            <?php if (!empty($crew['relations'])): ?>
                <div class="crew-relations-text-legacy">
                    <?= nl2br(htmlspecialchars($crew['relations'])) ?>
                </div>
            <?php else: ?>
                <p class="crew-bio-empty">No hay relaciones diplomáticas registradas.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
