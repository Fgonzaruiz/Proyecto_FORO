<div id="crewTab_gestion" class="pj-preview-tab-content">
    <h3 class="pj-tab-section-heading"><i class="fas fa-cog"></i> Gestión del Grupo</h3>

    <!-- Peticiones Pendientes -->
    <?php if ($aspirant_count > 0): ?>
    <div class="pj-data-group crew-data-group--alert">
        <h3 class="pj-tab-section-heading crew-alert-heading">
            <i class="fas fa-bell"></i> Peticiones Pendientes (<?= $aspirant_count ?>)
        </h3>
        <div class="crew-data-group-inner">
            <?php foreach ($aspirants as $a): ?>
                <div class="crew-aspirant-card">
                    <img src="<?= htmlspecialchars($a['avatar'] ?: 'https://placehold.co/40x40') ?>" class="crew-aspirant-avatar" alt="">
                    <div class="crew-aspirant-info">
                        <a href="<?= htmlspecialchars($bburl) ?>/game/public/personaje.php?pj=<?= $a['pj_id'] ?>" class="crew-aspirant-name" target="_blank">
                            <?= htmlspecialchars($a['name']) ?>
                        </a>
                        <span class="crew-aspirant-subtitle">
                            <?= htmlspecialchars($a['race_name']) ?>
                        </span>
                    </div>
                    <div class="crew-aspirant-actions">
                        <button type="button" class="rpg-action-btn rpg-btn-primary crew-btn-action-sm" onclick="crewAcceptMember(<?= $a['pj_id'] ?>, this)">
                            <i class="fas fa-check"></i> Aceptar
                        </button>
                        <button type="button" class="rpg-action-btn crew-btn-reject-sm" onclick="crewRejectMember(<?= $a['pj_id'] ?>, this)">
                            <i class="fas fa-times"></i> Rechazar
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="pj-grid-layout crew-grid-layout-two-col">
        <!-- Editar Info -->
        <div class="pj-col">
            <h3 class="pj-tab-section-heading"><i class="fas fa-edit"></i> Información Pública</h3>
            <div class="pj-data-group crew-data-group--padded">
                <div class="rpg-form-group crew-form-group">
                    <label class="crew-form-label">Nombre del Grupo</label>
                    <input type="text" id="crew_edit_name" value="<?= htmlspecialchars($crew['name'] ?? '') ?>" class="textbox crew-form-input">
                </div>
                <div class="rpg-form-group crew-form-group">
                    <label class="crew-form-label">Lema</label>
                    <input type="text" id="crew_edit_motto" value="<?= htmlspecialchars($crew['motto'] ?? '') ?>" placeholder="Lema del grupo" class="textbox crew-form-input">
                </div>
                <div class="rpg-form-group crew-form-group">
                    <label class="crew-form-label">Facciones / Afiliaciones (separadas por coma)</label>
                    <input type="text" id="crew_edit_factions" value="<?= htmlspecialchars($crew['factions'] ?? '') ?>" placeholder="Ej: Asociación de Cazadores, Independiente" class="textbox crew-form-input">
                </div>
                <div class="rpg-form-group crew-form-group">
                    <label class="crew-form-label">URL del Emblema (Banner)</label>
                    <input type="url" id="crew_edit_img" value="<?= htmlspecialchars($crew['image_url'] ?? '') ?>" class="textbox crew-form-input">
                </div>
                <div class="rpg-form-group crew-form-group">
                    <label class="crew-form-label">URL de OST (MP3)</label>
                    <input type="url" id="crew_edit_ost" value="<?= htmlspecialchars($crew['ost_url'] ?? '') ?>" class="textbox crew-form-input">
                </div>
                <div class="rpg-form-group crew-form-group">
                    <label class="crew-form-label">Historia / Descripción</label>
                    <textarea id="crew_edit_desc" rows="6" class="textbox crew-form-input crew-form-textarea"><?= htmlspecialchars($crew['description'] ?? '') ?></textarea>
                </div>
                <div class="rpg-form-group crew-form-group">
                    <label class="crew-form-label">Relaciones Diplomáticas</label>
                    <div class="crew-relations-mgmt-box">
                        <button type="button" class="rpg-action-btn rpg-btn-primary crew-btn-submit-wide" onclick="openCrewRelationsManager()">
                            <i class="fas fa-handshake"></i> Gestionar Relaciones Diplomáticas
                        </button>
                        <span class="crew-form-hint">Administra alianzas, rivalidades, pactos de no agresión y dibuja el mapa diplomático del grupo.</span>
                    </div>
                    <input type="hidden" id="crew_edit_relations" value="<?= htmlspecialchars($crew['relations'] ?? '') ?>">
                </div>
                
                <button type="button" class="rpg-action-btn rpg-btn-primary crew-btn-submit-wide" onclick="crewSaveInfo()">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
            </div>
            
            <!-- Gestionar Recuerdos -->
            <h3 class="pj-tab-section-heading crew-tab-section-heading-spacing"><i class="fas fa-images"></i> Gestionar Recuerdos</h3>
            <div class="pj-data-group crew-data-group--padded">
                <button type="button" class="rpg-action-btn rpg-btn-primary crew-btn-submit-wide" onclick="openAddMemoryModal()">
                    <i class="fas fa-plus"></i> Añadir Nuevo Recuerdo
                </button>
                <div id="crew-memories-list" class="crew-memories-list-spacing">
                    <!-- Se listarán aquí y se podrán borrar -->
                    <?php 
                    $memories = json_decode($crew['memories'] ?? '[]', true);
                    if (!is_array($memories)) $memories = [];
                    foreach ($memories as $idx => $m): ?>
                        <div class="crew-manage-member-row">
                            <div class="crew-manage-member-info">
                                <img src="<?= htmlspecialchars($m['image'] ?: 'https://placehold.co/30x30') ?>" class="crew-manage-avatar" alt="">
                                <div>
                                    <div class="crew-manage-member-name"><?= htmlspecialchars($m['title']) ?></div>
                                </div>
                            </div>
                            <div class="crew-manage-member-actions">
                                <button type="button" class="rpg-action-btn crew-btn-kick-xs" onclick="crewDeleteMemory(<?= $idx ?>, this)" title="Borrar Recuerdo">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Gestionar Miembros -->
        <div class="pj-col">
            <h3 class="pj-tab-section-heading"><i class="fas fa-users-cog"></i> Administrar Miembros</h3>
            <div class="pj-data-group crew-data-group--padded">
                <?php foreach ($members as $m): ?>
                    <?php if ($m['role'] === 'Líder') continue; ?>
                    <div class="crew-manage-member-row">
                        <div class="crew-manage-member-info">
                            <img src="<?= htmlspecialchars($m['avatar'] ?: 'https://placehold.co/30x30') ?>" class="crew-manage-avatar" alt="">
                            <div>
                                <div class="crew-manage-member-name"><?= htmlspecialchars($m['name']) ?></div>
                                <div class="crew-manage-member-role"><?= htmlspecialchars($m['role']) ?></div>
                            </div>
                        </div>
                        <div class="crew-manage-member-actions">
                            <input type="text" id="role_custom_<?= $m['pj_id'] ?>" value="<?= htmlspecialchars($m['role_custom']) ?>" placeholder="Puesto (Ej: Rastreador)" class="textbox crew-manage-role-input">
                            <button type="button" class="rpg-action-btn rpg-btn-primary crew-btn-action-xs" onclick="crewUpdateRole(<?= $m['pj_id'] ?>, 'role_custom_<?= $m['pj_id'] ?>')" title="Actualizar Puesto">
                                <i class="fas fa-save"></i>
                            </button>
                            <button type="button" class="rpg-action-btn crew-btn-kick-xs" onclick="crewKickMember(<?= $m['pj_id'] ?>, this)" title="Expulsar">
                                <i class="fas fa-ban"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (count($members) <= 1): ?>
                    <p class="crew-manage-empty">No tienes otros miembros en el grupo para administrar.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
