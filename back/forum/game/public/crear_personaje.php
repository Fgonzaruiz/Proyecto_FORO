<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
$prefix = TABLE_PREFIX;

if (!$uid) {
    ob_start();
    ?><div class="rpg-char-empty"><i class="fas fa-user-lock"></i><h2>Debes iniciar sesi&oacute;n</h2><p>Inicia sesi&oacute;n para acceder a esta página.</p></div><?php
    $content = ob_get_clean();
    game_render_page('Crear Personaje', $content);
    exit;
}

// Load slot config
$cfg_q = $db->query("SELECT * FROM {$prefix}game_user_config WHERE user_id = {$uid}");
$cfg = $db->fetch_array($cfg_q);
$max_slots = (int)($cfg['max_slots'] ?? 1);
$slots_used = (int)($cfg['slots_used'] ?? 0);
$catalog_oficios = game_oficio_list_catalog(true);
$catalog_disciplinas = game_disciplina_list_catalog(true);
$catalog_disciplinas_wizard = array_values(array_filter(
    $catalog_disciplinas,
    static function (array $disc): bool {
        if (!empty($disc['staff_grant_only'])) {
            return false;
        }
        $slug = (string)($disc['slug'] ?? '');
        if (in_array($slug, ['haki_observacion', 'haki_armamento', 'haki_conquistador'], true)) {
            return false;
        }
        if (($disc['category'] ?? '') === 'haki') {
            return false;
        }
        return true;
    }
));

$oficio_category_labels = [
    'crafteo' => 'Crafteo',
    'utilidad' => 'Utilidad',
    'lore' => 'Lore',
    'sigilo' => 'Sigilo',
    'economia' => 'Economía',
];

// Recalculate slots_used from actual non-deleted characters to prevent desync
$actual_count_q = $db->query("SELECT COUNT(*) AS cnt FROM {$prefix}game_personajes WHERE user_id = {$uid} AND is_npc = 0");
$actual_count = (int)$db->fetch_field($actual_count_q, 'cnt');
if ($actual_count !== $slots_used) {
    $slots_used = $actual_count;
    $db->write_query("UPDATE {$prefix}game_user_config SET slots_used = {$actual_count} WHERE user_id = {$uid}");
}

// Check if user is admin (staff_level = 3)
$is_admin = false;
$check_admin_q = $db->query("SELECT COUNT(*) as cnt FROM {$prefix}game_personajes WHERE user_id = {$uid} AND staff_level = 3");
if ($db->fetch_field($check_admin_q, 'cnt') > 0) {
    $is_admin = true;
}

$is_npc_mode = $mybb->get_input('is_npc', MyBB::INPUT_INT) === 1;

$edit_pj_id = $mybb->get_input('pj_id', MyBB::INPUT_INT);
$edit_data = null;

if ($edit_pj_id > 0) {
    if ($is_admin) {
        $q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$edit_pj_id} LIMIT 1");
    } else {
        $q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$edit_pj_id} AND user_id = {$uid} LIMIT 1");
    }
    $pj = $db->fetch_array($q);
    if (!$pj) {
        ob_start();
        ?><div class="rpg-char-empty"><i class="fas fa-exclamation-triangle"></i><h2>No encontrado</h2><p>Personaje no encontrado o no tienes permisos.</p></div><?php
        $content = ob_get_clean();
        game_render_page('Editar Personaje', $content);
        exit;
    }
    
    // Si es NPC, forzar modo NPC
    if ((int)$pj['is_npc'] === 1) {
        $is_npc_mode = true;
    }
    
    // Si no es un NPC editado por admin, validar estado de edición
    if (!($is_admin && (int)$pj['is_npc'] === 1)) {
        if ($pj['status'] !== 'pendiente' && $pj['status'] !== 'revision') {
            ob_start();
            ?><div class="rpg-char-empty"><i class="fas fa-lock"></i><h2>Bloqueado</h2><p>Este personaje ya no puede ser editado.</p></div><?php
            $content = ob_get_clean();
            game_render_page('Editar Personaje', $content);
            exit;
        }
    }
    $edit_payload = !empty($pj['data_json']) ? json_decode($pj['data_json'], true) : [];
    if (!is_array($edit_payload)) {
        $edit_payload = [];
    }
    $edit_payload['name'] = (string)($pj['name'] ?? '');
    $edit_payload['avatar'] = (string)($pj['avatar'] ?? ($edit_payload['avatar'] ?? ''));
    $edit_payload['faction'] = (string)($pj['faction'] ?? ($edit_payload['faction'] ?? ''));
    $edit_payload['rank'] = (string)($edit_payload['faction_rank'] ?? $pj['rango'] ?? '');
    $edit_payload['job'] = (string)($pj['occupation_name'] ?? ($edit_payload['job'] ?? ''));
    $edit_payload['race'] = (string)($pj['race_name'] ?? ($edit_payload['race'] ?? ''));
    if (!empty($pj['stats_json'])) {
        $statsDecoded = json_decode($pj['stats_json'], true);
        if (is_array($statsDecoded)) {
            $edit_payload['stats'] = $statsDecoded;
        }
    }
    $disciplinasEdit = game_disciplina_list_for_character((int)$pj['id']);
    if ($disciplinasEdit !== []) {
        $edit_payload['disciplina'] = (string)$disciplinasEdit[0]['name'];
    } elseif (!empty($edit_payload['disciplina'])) {
        // keep data_json value
    } elseif (!empty($edit_payload['arquetipo']) && strcasecmp((string)$edit_payload['arquetipo'], 'Desconocido') !== 0) {
        $edit_payload['disciplina'] = (string)$edit_payload['arquetipo'];
    }
    $edit_data = json_encode($edit_payload, JSON_UNESCAPED_UNICODE) ?: 'null';
}

if ($is_npc_mode && !$is_admin) {
    header('Location: ../index.php');
    exit;
}

if (!$is_npc_mode && $edit_pj_id <= 0 && $slots_used >= $max_slots) {
    ob_start();
    ?><div class="rpg-char-empty"><i class="fas fa-ban"></i><h2>Sin slots disponibles</h2><p>No tienes ranuras libres para crear más personajes.</p></div><?php
    $content = ob_get_clean();
    game_render_page('Crear Personaje', $content);
    exit;
}

// Load central catalog
$catalog_path = __DIR__ . '/../data/linaje_system.json';
$catalog_json = '{}';
if (file_exists($catalog_path)) {
    $catalog_json = file_get_contents($catalog_path);
}

$bb = $mybb->settings['bburl'];
$razas = ['Humano', 'Mink', 'Gyojin', 'Gigante', 'Tontatta', 'Buccaner', 'Lunarian', 'Skypean', 'Oni', 'Sirena'];

ob_start();
?>


<div class="wizard-container">
    <div class="wizard-header">
        <h1><?= $edit_pj_id > 0 ? 'Actualiza tu Leyenda' : 'Forja tu Leyenda' ?></h1>
        <p><?= $edit_pj_id > 0 ? 'Modifica los datos de tu personaje según las anotaciones del staff.' : 'El camino de un nuevo personaje comienza aquí.' ?></p>
    </div>

    <!-- Progress Bar -->
    <div class="wizard-progress">
        <div class="wizard-step-marker active" id="marker-1">1<div class="wizard-step-label">Identidad</div></div>
        <div class="wizard-step-marker" id="marker-2">2<div class="wizard-step-label">Conocimientos</div></div>
        <div class="wizard-step-marker" id="marker-3">3<div class="wizard-step-label">Expediente</div></div>
    </div>

    <!-- ==================== PASO 1: IDENTIDAD ==================== -->
    <div id="step-1" class="wizard-step-content">
        <div class="wizard-section">
            <h2 class="wizard-section-title"><i class="fas fa-id-card"></i> Datos Básicos</h2>
            <div class="wizard-grid">
                <div class="form-group">
                    <label>Nombre del Personaje *</label>
                    <input type="text" id="pj_name" class="textbox" placeholder="Ej. Monkey D. Luffy" required>
                </div>
                <div class="form-group">
                    <label>Enlace del Avatar (290x450 aprox)</label>
                    <input type="url" id="pj_avatar" class="textbox" placeholder="https://i.imgur.com/...">
                </div>
                <div class="form-group">
                    <label>Facción *</label>
                    <select id="pj_faction" class="textbox" required>
                        <option value="" disabled selected>Selecciona una facción</option>
                        <option value="Revolucionario">Revolucionario</option>
                        <option value="Marine">Marine</option>
                        <option value="Gobierno">Gobierno</option>
                        <option value="Cazador">Cazador</option>
                        <option value="Civil">Civil</option>
                        <option value="Pirata">Pirata</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rango Inicial</label>
                    <input type="text" id="pj_rank" class="textbox rpg-wizard-input-readonly" readonly placeholder="Automático según facción">
                </div>
                <div class="form-group wizard-grid-full">
                    <label>Raza *</label>
                    <select id="pj_race" class="textbox" required onchange="checkHibrido()">
                        <option value="" disabled selected>Selecciona tu raza</option>
                        <?php foreach($razas as $r): ?>
                            <option value="<?= $r ?>"><?= $r ?></option>
                        <?php endforeach; ?>
                        <option value="Hibrido">Híbrido (Mezcla de dos razas)</option>
                    </select>
                </div>
                <div id="hibrido_options" class="wizard-grid-full rpg-wizard-hibrido-panel">
                    <div class="wizard-grid">
                        <div class="form-group">
                            <label class="rpg-wizard-label-accent">Raza Dominante *</label>
                            <select id="pj_race_dom" class="textbox">
                                <option value="" disabled selected>Selecciona la dominante</option>
                                <?php foreach($razas as $r): ?>
                                    <option value="<?= $r ?>"><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="rpg-wizard-label-accent">Raza Recesiva *</label>
                            <select id="pj_race_rec" class="textbox">
                                <option value="" disabled selected>Selecciona la recesiva</option>
                                <?php foreach($razas as $r): ?>
                                    <option value="<?= $r ?>"><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Edad</label>
                    <input type="number" id="pj_age" class="textbox" placeholder="Ej. 19">
                </div>
                <div class="form-group">
                    <label>Isla de Origen</label>
                    <input type="text" id="pj_origin" class="textbox" placeholder="Ej. East Blue, Isla Dawn">
                </div>
                <div class="form-group wizard-grid-full">
                    <label>PB (Play-By / Físico Base)</label>
                    <input type="text" id="pj_pb" class="textbox" placeholder="¿En qué personaje te basas?">
                </div>
            </div>
        </div>
        <div class="wizard-section">
            <h2 class="wizard-section-title"><i class="fas fa-book-open"></i> Descripciones</h2>
            <div class="wizard-grid">
                <div class="form-group wizard-grid-full">
                    <label>Apariencia Física</label>
                    <textarea id="pj_physique" class="textbox rpg-wizard-textarea-md" placeholder="Describe cómo es físicamente..."></textarea>
                </div>
                <div class="form-group wizard-grid-full">
                    <label>Descripción Psicológica</label>
                    <textarea id="pj_psychology" class="textbox rpg-wizard-textarea-md" placeholder="Mentalidad, miedos, motivaciones..."></textarea>
                </div>
                <div class="form-group wizard-grid-full">
                    <label>Historia</label>
                    <textarea id="pj_history" class="textbox rpg-wizard-textarea-md" placeholder="Narra la historia de tu personaje..."></textarea>
                </div>
                <div class="form-group wizard-grid-full">
                    <label>Otros / Extras</label>
                    <textarea id="pj_extras" class="textbox rpg-wizard-textarea-sm" placeholder="Cicatrices, tatuajes, objetos importantes..."></textarea>
                </div>
            </div>
        </div>
        <div class="wizard-actions">
            <div></div>
            <button type="button" class="rpg-wizard-btn-next" onclick="goToStep(2)">Siguiente: Conocimientos <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>

    <!-- ==================== PASO 2: CONOCIMIENTOS ==================== -->
    <div id="step-2" class="wizard-step-content rpg-wizard-hidden">

        <!-- Disciplinas -->
        <div class="wizard-section">
            <h2 class="wizard-section-title"><i class="fas fa-crosshairs"></i> Disciplina de Combate</h2>
            <p class="rpg-wizard-text-muted">Tu especializaci&oacute;n marcial define tu estilo de lucha. Elige una disciplina inicial (grado I al crear).</p>
            <div class="disc-grid" id="discGrid">
                <?php foreach ($catalog_disciplinas_wizard as $disc): ?>
                <div class="disc-box" data-disc="<?= htmlspecialchars($disc['name']) ?>" onclick="selectDisc('<?= htmlspecialchars($disc['name'], ENT_QUOTES) ?>', this)">
                    <div class="disc-icon"><i class="fas <?= htmlspecialchars($disc['icon'] ?? 'fa-crosshairs') ?>"></i></div>
                    <div class="disc-name"><?= htmlspecialchars($disc['name']) ?></div>
                    <div class="disc-desc"><?= htmlspecialchars($disc['description'] ?? '') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="pj_disciplina" value="">
        </div>

        <!-- Oficio -->
        <div class="wizard-section">
            <h2 class="wizard-section-title"><i class="fas fa-anchor"></i> Oficio</h2>
            <p class="rpg-wizard-text-muted">Tu especialidad en el mundo. Elige un oficio inicial (grado I al crear).</p>
            <div class="oficio-grid" id="oficioGrid">
                <div class="oficio-box selected"
                     data-job="Ninguno"
                     data-desc="Sin especialización formal. Puedes adquirir un oficio más adelante con PP."
                     data-unlock=""
                     data-category=""
                     data-category-label=""
                     data-icon="fa-user"
                     onclick="selectOficio('Ninguno', this)">
                    <div class="oficio-icon"><i class="fas fa-user"></i></div>
                    <div class="oficio-name">Aprendiz</div>
                    <div class="oficio-desc">Sin especialización formal.</div>
                </div>
                <?php foreach ($catalog_oficios as $of):
                    $ofUnlocks = !empty($of['grado_unlock_json']) ? json_decode((string)$of['grado_unlock_json'], true) : [];
                    $ofUnlock1 = is_array($ofUnlocks) ? (string)($ofUnlocks['1'] ?? $ofUnlocks[1] ?? '') : '';
                    $ofCat = (string)($of['category'] ?? '');
                    $ofCatLabel = $oficio_category_labels[$ofCat] ?? ucfirst($ofCat);
                    $ofIcon = (string)($of['icon'] ?? 'fa-anchor');
                    if ($ofIcon !== '' && !str_starts_with($ofIcon, 'fa-')) {
                        $ofIcon = 'fa-' . $ofIcon;
                    }
                ?>
                <div class="oficio-box"
                     data-job="<?= htmlspecialchars($of['name']) ?>"
                     data-desc="<?= htmlspecialchars($of['description'] ?? '') ?>"
                     data-unlock="<?= htmlspecialchars($ofUnlock1) ?>"
                     data-category="<?= htmlspecialchars($ofCat) ?>"
                     data-category-label="<?= htmlspecialchars($ofCatLabel) ?>"
                     data-icon="<?= htmlspecialchars($ofIcon) ?>"
                     onclick="selectOficio('<?= htmlspecialchars($of['name'], ENT_QUOTES) ?>', this)">
                    <div class="oficio-icon"><i class="fas <?= htmlspecialchars($ofIcon) ?>"></i></div>
                    <div class="oficio-name"><?= htmlspecialchars($of['name']) ?></div>
                    <div class="oficio-desc"><?= htmlspecialchars($of['description'] ?? '') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="oficioDetailPanel" class="oficio-detail-panel" aria-live="polite"></div>
            <input type="hidden" id="pj_job" value="Ninguno">
        </div>

        <!-- Atributos -->
        <div class="wizard-section">
            <h2 class="wizard-section-title"><i class="fas fa-sliders-h"></i> Atributos Base</h2>
            <div class="stat-distributor rpg-wizard-stat-distributor">
                    <div class="stat-points-left">Punto libre de creación: <span id="pts_left">1</span></div>

                    <div class="rpg-wizard-pillar-section">
                        <div class="rpg-wizard-pillar-label rpg-wizard-pillar-label--cuerpo"><i class="fas fa-dumbbell"></i> Pilar Cuerpo</div>
                        <div class="stat-row">
                            <div class="stat-name">Fuerza</div>
                            <div class="stat-controls">
                                <button type="button" class="stat-btn" onclick="modStat('fuerza', -1)">−</button>
                                <div class="stat-value" id="val_fuerza">D</div>
                                <button type="button" class="stat-btn" onclick="modStat('fuerza', 1)">+</button>
                            </div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-name">Destreza</div>
                            <div class="stat-controls">
                                <button type="button" class="stat-btn" onclick="modStat('destreza', -1)">−</button>
                                <div class="stat-value" id="val_destreza">D</div>
                                <button type="button" class="stat-btn" onclick="modStat('destreza', 1)">+</button>
                            </div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-name">Vigor</div>
                            <div class="stat-controls">
                                <button type="button" class="stat-btn" onclick="modStat('vigor', -1)">−</button>
                                <div class="stat-value" id="val_vigor">D</div>
                                <button type="button" class="stat-btn" onclick="modStat('vigor', 1)">+</button>
                            </div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-name">Agilidad</div>
                            <div class="stat-controls">
                                <button type="button" class="stat-btn" onclick="modStat('agilidad', -1)">−</button>
                                <div class="stat-value" id="val_agilidad">D</div>
                                <button type="button" class="stat-btn" onclick="modStat('agilidad', 1)">+</button>
                            </div>
                        </div>
                    </div>

                    <div class="rpg-wizard-pillar-section">
                        <div class="rpg-wizard-pillar-label rpg-wizard-pillar-label--mente"><i class="fas fa-brain"></i> Pilar Mente</div>
                        <div class="stat-row">
                            <div class="stat-name">Intelecto</div>
                            <div class="stat-controls">
                                <button type="button" class="stat-btn" onclick="modStat('intelecto', -1)">−</button>
                                <div class="stat-value" id="val_intelecto">D</div>
                                <button type="button" class="stat-btn" onclick="modStat('intelecto', 1)">+</button>
                            </div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-name">Ingenio</div>
                            <div class="stat-controls">
                                <button type="button" class="stat-btn" onclick="modStat('ingenio', -1)">−</button>
                                <div class="stat-value" id="val_ingenio">D</div>
                                <button type="button" class="stat-btn" onclick="modStat('ingenio', 1)">+</button>
                            </div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-name">Concentración</div>
                            <div class="stat-controls">
                                <button type="button" class="stat-btn" onclick="modStat('concentracion', -1)">−</button>
                                <div class="stat-value" id="val_concentracion">D</div>
                                <button type="button" class="stat-btn" onclick="modStat('concentracion', 1)">+</button>
                            </div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-name">Percepción</div>
                            <div class="stat-controls">
                                <button type="button" class="stat-btn" onclick="modStat('percepcion', -1)">−</button>
                                <div class="stat-value" id="val_percepcion">D</div>
                                <button type="button" class="stat-btn" onclick="modStat('percepcion', 1)">+</button>
                            </div>
                        </div>
                    </div>

                    <div class="rpg-wizard-pillar-section">
                        <div class="rpg-wizard-pillar-label rpg-wizard-pillar-label--espiritu"><i class="fas fa-bahai"></i> Pilar Espíritu (Nen)</div>
                        <div class="stat-row">
                            <div class="stat-name">Caudal de Aura</div>
                            <div class="stat-controls">
                                <button type="button" class="stat-btn" onclick="modStat('caudal', -1)">−</button>
                                <div class="stat-value" id="val_caudal">D</div>
                                <button type="button" class="stat-btn" onclick="modStat('caudal', 1)">+</button>
                            </div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-name">Control</div>
                            <div class="stat-controls">
                                <button type="button" class="stat-btn" onclick="modStat('control', -1)">−</button>
                                <div class="stat-value" id="val_control">D</div>
                                <button type="button" class="stat-btn" onclick="modStat('control', 1)">+</button>
                            </div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-name">Voluntad</div>
                            <div class="stat-controls">
                                <button type="button" class="stat-btn" onclick="modStat('voluntad', -1)">−</button>
                                <div class="stat-value" id="val_voluntad">D</div>
                                <button type="button" class="stat-btn" onclick="modStat('voluntad', 1)">+</button>
                            </div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-name">Sensibilidad Aúrica</div>
                            <div class="stat-controls">
                                <button type="button" class="stat-btn" onclick="modStat('sensibilidad', -1)">−</button>
                                <div class="stat-value" id="val_sensibilidad">D</div>
                                <button type="button" class="stat-btn" onclick="modStat('sensibilidad', 1)">+</button>
                            </div>
                        </div>
                    </div>
                </div>
        </div>

        <!-- ====== LINAJE — PERK PICKER ====== -->
        <div class="wizard-section rpg-wizard-section--spaced">
            <h2 class="wizard-section-title"><i class="fas fa-scroll"></i> Factor Linaje (Conocimiento Racial)</h2>
            <p class="rpg-wizard-text-muted--lg">Tu raza determina tus habilidades pasivas innatas y los perks de linaje que puedes elegir. Los h&iacute;bridos acceden a las pasivas primarias de ambas razas.</p>

            <!-- Slot counter bar -->
            <div class="linaje-slots-bar rpg-wizard-linaje-bar" id="linajeSlotBar">
                <div class="linaje-slots-group rpg-wizard-linaje-row">
                    <span class="linaje-slots-label rpg-wizard-linaje-label"><i class="fas fa-gem rpg-wizard-icon-accent"></i> Puntos de Linaje</span>
                    <div class="linaje-slots-dots rpg-wizard-linaje-dots" id="linajeDots"></div>
                    <span class="linaje-slots-count rpg-wizard-linaje-count"><span id="usedPoints">0</span>/<span id="maxPoints">4</span></span>
                </div>
                <div id="linajeSobranteBonus" class="rpg-wizard-linaje-bonus">
                    Puntos Sobrantes: <span id="sobrantePoints">0</span> PL = <span id="bonusPP">0</span> PP de Bonus
                </div>
            </div>

            <!-- Section 1: Pasivas Raciales -->
            <div class="linaje-section-header rpg-wizard-linaje-header--green">
                <i class="fas fa-shield-alt"></i>
                Pasivas Raciales
                <span class="linaje-section-badge rpg-wizard-linaje-badge rpg-wizard-linaje-badge--green">Autom&aacute;ticas</span>
            </div>
            <div class="perk-grid" id="gridPasivas"></div>

            <!-- Section 2: Linaje Racial -->
            <div class="linaje-section-header rpg-wizard-linaje-header--primary">
                <i class="fas fa-dna"></i>
                Linaje Racial
                <span class="linaje-section-badge rpg-wizard-linaje-badge rpg-wizard-linaje-badge--primary">Elige</span>
            </div>
            <div class="perk-grid" id="gridRacial"></div>

            <!-- Section 3: Linaje General -->
            <div class="linaje-section-header rpg-wizard-linaje-header--purple">
                <i class="fas fa-star"></i>
                Linaje General
                <span class="linaje-section-badge rpg-wizard-linaje-badge rpg-wizard-linaje-badge--purple">Elige</span>
            </div>
            <div class="perk-grid" id="gridGeneral"></div>
        </div>

        <!-- Global tooltip -->
        <div class="perk-tooltip" id="perkTooltip">
            <div class="perk-tooltip-title" id="ttTitle"></div>
            <div class="perk-tooltip-badge" id="ttBadge"></div>
            <div class="perk-tooltip-desc" id="ttDesc"></div>
        </div>

        <div class="wizard-actions">
            <button type="button" class="rpg-wizard-btn-back" onclick="goToStep(1)"><i class="fas fa-arrow-left"></i> Volver</button>
            <button type="button" class="rpg-wizard-btn-next" onclick="goToStep(3)">Generar Expediente <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>

    <!-- ==================== PASO 3: PREVIEW ==================== -->
    <div id="step-3" class="wizard-step-content rpg-wizard-hidden">
        <div class="wizard-section rpg-wizard-step-3-layout">
            <!-- Left -->
            <div class="rpg-wizard-preview-sidebar">
                <div id="preview_avatar" class="rpg-wizard-preview-avatar" data-bg="https://placehold.co/320x450"></div>
                <div class="rpg-wizard-preview-body">
                    <h2 id="preview_name" class="rpg-wizard-preview-name">Nombre</h2>
                    <div class="rpg-wizard-preview-badges">
                        <span id="preview_faction" class="rpg-wizard-preview-badge--faction"><i class="fas fa-flag"></i> Facción</span>
                        <span id="preview_rank" class="rpg-wizard-preview-badge--rank"><i class="fas fa-medal"></i> Rango</span>
                    </div>
                    <div class="rpg-wizard-preview-card">
                        <div class="rpg-wizard-preview-row">
                            <i class="fas fa-crosshairs rpg-wizard-preview-icon"></i>
                            <div>
                                <div class="rpg-wizard-preview-label">Disciplina</div>
                                <div id="preview_disciplina" class="rpg-wizard-preview-value">Ninguna</div>
                            </div>
                        </div>
                        <div class="rpg-wizard-preview-row">
                            <i class="fas fa-anchor rpg-wizard-preview-icon"></i>
                            <div>
                                <div class="rpg-wizard-preview-label">Oficio</div>
                                <div id="preview_job" class="rpg-wizard-preview-value">Ninguno</div>
                            </div>
                        </div>
                        <div class="rpg-wizard-preview-row rpg-wizard-preview-row--last">
                            <i class="fas fa-dna rpg-wizard-preview-icon--purple"></i>
                            <div>
                                <div class="rpg-wizard-preview-label">Genes Activos</div>
                                <div id="preview_genes" class="rpg-wizard-preview-value--genes">Ninguno</div>
                            </div>
                        </div>
                    </div>
                    <div class="rpg-wizard-preview-derived">
                        <div class="rpg-wizard-preview-pv">
                            <div class="rpg-wizard-preview-pv-label">PV</div>
                            <div class="rpg-wizard-preview-pv-value" id="preview_pv">0</div>
                        </div>
                        <div class="rpg-wizard-preview-pe">
                            <div class="rpg-wizard-preview-pe-label">PE</div>
                            <div class="rpg-wizard-preview-pe-value" id="preview_pe">0</div>
                        </div>
                    </div>

                    <h3 class="rpg-wizard-preview-stats-title">Atributos Base</h3>
                    <div id="wizard-preview-stats" class="rpg-wizard-preview-stats"></div>
                </div>
            </div>
            <!-- Right -->
            <div class="rpg-wizard-preview-main">
                <div class="preview-tabs">
                    <div class="preview-tab active" onclick="switchPreviewTab('bio', this)"><i class="fas fa-file-alt"></i> Biografía</div>
                    <div class="preview-tab" onclick="switchPreviewTab('linaje', this)"><i class="fas fa-dna"></i> Mapa Genético</div>
                </div>

                <div id="previewTab_bio" class="preview-tab-content active">
                    <div class="rpg-wizard-preview-bio-grid">
                        <div class="rpg-wizard-preview-bio-cell"><strong>Edad:</strong> <span id="preview_age"></span></div>
                        <div class="rpg-wizard-preview-bio-cell"><strong>Origen:</strong> <span id="preview_origin"></span></div>
                        <div class="rpg-wizard-preview-bio-cell"><strong>Raza:</strong> <span id="preview_race"></span></div>
                        <div class="rpg-wizard-preview-bio-cell"><strong>PB:</strong> <span id="preview_pb"></span></div>
                    </div>
                    <h3 class="rpg-wizard-preview-section-title">Apariencia Física</h3>
                    <div id="preview_physique" class="rpg-wizard-preview-text rpg-wizard-preview-text--spaced"></div>
                    <h3 class="rpg-wizard-preview-section-title">Historia</h3>
                    <div id="preview_history" class="rpg-wizard-preview-text rpg-wizard-preview-text--spaced"></div>
                    <h3 class="rpg-wizard-preview-section-title">Perfil Psicológico</h3>
                    <div id="preview_psychology" class="rpg-wizard-preview-text rpg-wizard-preview-text--spaced"></div>
                    <h3 class="rpg-wizard-preview-section-title">Extras y Notas</h3>
                    <div id="preview_extras" class="rpg-wizard-preview-text"></div>
                </div>

                <div id="previewTab_linaje" class="preview-tab-content">
                    <p class="rpg-wizard-text-muted--lg">Perks de Linaje del personaje — pasivas innatas y habilidades elegidas.</p>
                    <div id="preview_gene_cards">
                        <!-- Perk preview cards injected by JS -->
                    </div>
                </div>
            </div>
        </div>
        <div class="wizard-actions">
            <button type="button" class="rpg-wizard-btn-back" onclick="goToStep(2)"><i class="fas fa-arrow-left"></i> Volver</button>
            <button type="button" class="rpg-wizard-btn-save" onclick="guardarPersonaje()"><i class="fas fa-check"></i> <?= $edit_pj_id > 0 ? 'Guardar Correcciones' : 'Aceptar y Crear' ?></button>
        </div>
    </div>
</div>

<script>
window.CREAR_PERSONAJE_CONFIG = <?= json_encode([
  'bburl' => rtrim($bb, '/'),
  'editPjId' => (int)($edit_pj_id ?? 0),
  'isNpcMode' => (bool)$is_npc_mode,
  'editData' => $edit_data ? json_decode($edit_data, true) : null,
  'catalog' => json_decode($catalog_json ?: '{}', true),
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= rtrim($bb, '/') ?>/jscripts/game/crear_personaje.js?v=7"></script>

<?php
$content = ob_get_clean();
game_render_page('Crear Personaje', $content);
