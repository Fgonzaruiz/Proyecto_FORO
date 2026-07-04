<div id="crewTab_miembros" class="pj-preview-tab-content">
    <div class="pj-tab-section-header">
        <h3 class="pj-tab-section-title">Miembros del Grupo</h3>
        <?php if ($uid > 0 && !$is_member && !$is_leader): ?>
            <div class="pj-tab-section-actions">
                <?php
                // Comprobar si ya envió petición
                $has_request = false;
                if ($my_pj_id > 0) {
                    $has_request = (bool)$db->fetch_field(
                        $db->query("SELECT 1 FROM {$prefix}game_tripulacion_miembros WHERE pj_id = {$my_pj_id} AND tripulacion_id = {$crew_id}"),
                        "1"
                    );
                }
                ?>
                <?php if ($my_pj_id > 0): ?>
                    <?php if ($has_request): ?>
                        <button class="rpg-action-btn crew-btn-disabled" disabled>
                            <i class="fas fa-clock"></i> Petición Enviada
                        </button>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($bburl) ?>/game/public/peticion_grupo.php?id=<?= $crew_id ?>" class="rpg-action-btn rpg-btn-primary">
                            <i class="fas fa-hand-paper"></i> Solicitar Ingreso
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="crew-member-grid">
        <?php foreach ($members as $m): ?>
            <div class="crew-member-card <?= $m['role'] === 'Líder' ? 'crew-member-card--captain' : '' ?>">
                <a href="<?= htmlspecialchars($bburl) ?>/game/public/personaje.php?pj=<?= $m['pj_id'] ?>" class="crew-member-avatar-link">
                    <img src="<?= htmlspecialchars($m['avatar'] ?: 'https://placehold.co/65x65') ?>" class="crew-member-avatar" alt="<?= htmlspecialchars($m['name']) ?>">
                </a>
                <div class="crew-member-info">
                    <a href="<?= htmlspecialchars($bburl) ?>/game/public/personaje.php?pj=<?= $m['pj_id'] ?>" class="crew-member-name">
                        <?= htmlspecialchars($m['name']) ?>
                    </a>
                    <div class="crew-member-badges">
                        <span class="crew-member-role-badge">
                            <?= htmlspecialchars($m['role_custom'] ?: $m['role']) ?>
                        </span>
                        <span class="crew-member-role-badge <?= $m['global_rank_class'] ?>">
                            <?= htmlspecialchars($m['global_rank']) ?>
                        </span>
                    </div>
                    <div class="crew-member-joined">
                        Unido: <?= date('d/m/Y', strtotime($m['joined_at'])) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($members)): ?>
            <p class="crew-grid-empty">No hay miembros registrados.</p>
        <?php endif; ?>
    </div>
</div>
