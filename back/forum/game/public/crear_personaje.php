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

// Recalculate slots_used from actual non-deleted characters to prevent desync
$actual_count_q = $db->query("SELECT COUNT(*) AS cnt FROM {$prefix}game_personajes WHERE user_id = {$uid}");
$actual_count = (int)$db->fetch_field($actual_count_q, 'cnt');
if ($actual_count !== $slots_used) {
    $slots_used = $actual_count;
    $db->write_query("UPDATE {$prefix}game_user_config SET slots_used = {$actual_count} WHERE user_id = {$uid}");
}

$edit_pj_id = $mybb->get_input('pj_id', MyBB::INPUT_INT);
$edit_data = null;

if ($edit_pj_id > 0) {
    $q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$edit_pj_id} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($q);
    if (!$pj) {
        ob_start();
        ?><div class="rpg-char-empty"><i class="fas fa-exclamation-triangle"></i><h2>No encontrado</h2><p>Personaje no encontrado o no tienes permisos.</p></div><?php
        $content = ob_get_clean();
        game_render_page('Editar Personaje', $content);
        exit;
    }
    if ($pj['status'] !== 'pendiente' && $pj['status'] !== 'revision') {
        ob_start();
        ?><div class="rpg-char-empty"><i class="fas fa-lock"></i><h2>Bloqueado</h2><p>Este personaje ya no puede ser editado.</p></div><?php
        $content = ob_get_clean();
        game_render_page('Editar Personaje', $content);
        exit;
    }
    $edit_data = $pj['data_json'] ? $pj['data_json'] : 'null';
} elseif ($slots_used >= $max_slots) {
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
<style>
/* ============ WIZARD CORE ============ */
.wizard-container { max-width: 1100px; margin: 0 auto; }
.wizard-header { margin-bottom: 30px; text-align: center; }
.wizard-header h1 { font-size: 32px; font-family: var(--font-heading); color: var(--text-primary); margin-bottom: 10px; }
.wizard-header p { color: var(--text-muted); font-size: 15px; }

/* Progress bar */
.wizard-progress { display: flex; justify-content: space-between; position: relative; margin-bottom: 40px; padding: 0 40px; }
.wizard-progress::before { content: ''; position: absolute; top: 50%; left: 60px; right: 60px; height: 4px; background: var(--border-color); transform: translateY(-50%); z-index: 1; border-radius: 2px; }
.wizard-step-marker { width: 40px; height: 40px; border-radius: 50%; background: var(--bg-surface); border: 4px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--text-muted); position: relative; z-index: 2; transition: all 0.3s ease; }
.wizard-step-marker.active { border-color: var(--accent-indigo); color: var(--accent-indigo); box-shadow: 0 0 15px rgba(99,102,241,0.3); }
.wizard-step-marker.completed { border-color: #10b981; color: #10b981; background: rgba(16,185,129,0.1); }
.wizard-step-label { position: absolute; top: 50px; font-size: 12px; font-weight: 600; white-space: nowrap; color: var(--text-muted); }

.wizard-section { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-xl); padding: 30px; margin-bottom: 30px; box-shadow: var(--shadow-card); }
.wizard-section-title { font-family: var(--font-heading); font-size: 20px; color: var(--accent-indigo); border-bottom: 2px solid rgba(99,102,241,0.2); padding-bottom: 10px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
.wizard-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.wizard-grid-full { grid-column: span 2; }
.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group label { font-family: var(--font-heading); font-weight: 600; font-size: 13px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
.wizard-actions { display: flex; justify-content: space-between; gap: 15px; margin-top: 20px; }

/* ============ ARQUETIPO BELICO ============ */
.arq-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
.arq-box { background: var(--bg-main); border: 2px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s ease; }
.arq-box:hover { border-color: rgba(99,102,241,0.5); transform: translateY(-3px); }
.arq-box.selected { border-color: var(--accent-indigo); background: rgba(99,102,241,0.05); box-shadow: 0 4px 20px rgba(99,102,241,0.2); }
.arq-icon { font-size: 32px; color: var(--text-secondary); margin-bottom: 10px; transition: color 0.2s ease; }
.arq-box.selected .arq-icon { color: var(--accent-indigo); }
.arq-name { font-weight: 700; font-size: 14px; color: var(--text-primary); }

/* ============ STATS ============ */
.stat-distributor { background: var(--bg-main); border-radius: var(--radius-lg); padding: 20px; border: 1px solid var(--border-color); }
.stat-points-left { text-align: center; font-size: 24px; font-family: var(--font-heading); font-weight: 900; color: #10b981; margin-bottom: 20px; }
.stat-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed var(--border-color); }
.stat-row:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
.stat-name { font-weight: 600; font-size: 14px; color: var(--text-primary); width: 120px; }
.stat-controls { display: flex; align-items: center; gap: 15px; }
.stat-btn { width: 32px; height: 32px; border-radius: 50%; border: none; background: var(--bg-card); color: var(--text-primary); font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: all 0.1s; }
.stat-btn:hover { background: var(--border-color); }
.stat-btn:active { transform: scale(0.95); }
.stat-value { font-size: 18px; font-weight: bold; font-family: var(--font-heading); color: var(--accent-purple); width: 30px; text-align: center; }

/* ============ PERK PICKER — SISTEMA DE LINAJE ============ */

/* Slot counters */
.linaje-slots-bar {
    display: flex; align-items: center; justify-content: space-between; gap: 20px;
    padding: 14px 20px; margin-bottom: 20px;
    background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(168,85,247,0.03));
    border-radius: var(--radius-lg); border: 1px solid rgba(99,102,241,0.2);
}
.linaje-slots-group { display: flex; align-items: center; gap: 12px; }
.linaje-slots-dots { display: flex; gap: 6px; }
.linaje-slot-dot {
    width: 12px; height: 12px; border-radius: 50%;
    border: 2px solid var(--border-color);
    background: var(--bg-main);
    transition: all 0.3s ease;
}
.linaje-slot-dot.filled {
    background: var(--accent-indigo);
    border-color: var(--accent-indigo);
    box-shadow: 0 0 8px rgba(99,102,241,0.5);
}
.linaje-slots-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
.linaje-slots-count { font-family: var(--font-heading); font-weight: 900; font-size: 18px; color: var(--accent-purple); }

/* Section headings */
.linaje-section-header {
    display: flex; align-items: center; gap: 10px;
    margin: 24px 0 14px 0;
    font-family: var(--font-heading); font-size: 14px; font-weight: 800;
    text-transform: uppercase; letter-spacing: 1.5px;
}
.linaje-section-header i { font-size: 16px; }
.linaje-section-badge {
    font-size: 10px; padding: 3px 10px; border-radius: 20px;
    font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-left: auto;
}

/* Perk grid */
.perk-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

/* Individual perk card */
.perk-card {
    position: relative;
    background: var(--bg-main);
    border: 2px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 16px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
    text-align: left;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(.4,0,.2,1);
    overflow: hidden;
    user-select: none;
}
.perk-card::before {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(135deg, transparent, rgba(255,255,255,0.02));
    pointer-events: none;
}
.perk-card:hover:not(.perk-passive):not(.perk-locked) {
    border-color: rgba(99,102,241,0.6);
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(99,102,241,0.15);
}
.perk-card.perk-selected {
    border-color: var(--accent-indigo);
    background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(168,85,247,0.06));
    box-shadow: 0 0 0 3px rgba(99,102,241,0.15), 0 8px 24px rgba(99,102,241,0.2);
}
.perk-card.perk-selected::after {
    content: '\f00c';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute; top: 12px; right: 12px;
    font-size: 12px; color: var(--accent-indigo);
    animation: checkPop 0.2s ease;
}
@keyframes checkPop { 0%{ transform:scale(0); } 60%{ transform:scale(1.3); } 100%{ transform:scale(1); } }

.perk-card.perk-passive {
    cursor: default;
    border-style: solid;
    opacity: 1;
}
.perk-card.perk-passive-primary {
    border-color: rgba(16,185,129,0.4);
    background: linear-gradient(135deg, rgba(16,185,129,0.05), rgba(6,182,212,0.03));
}
.perk-card.perk-passive-secondary {
    border-color: rgba(245,158,11,0.35);
    background: linear-gradient(135deg, rgba(245,158,11,0.05), rgba(239,68,68,0.02));
}
.perk-card.perk-locked {
    cursor: not-allowed;
    opacity: 0.4;
    filter: grayscale(60%);
}

/* Perk icon */
.perk-icon {
    width: 44px; height: 44px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    transition: all 0.3s ease;
    flex-shrink: 0;
}
.perk-card:not(.perk-passive):not(.perk-locked):hover .perk-icon {
    transform: scale(1.1);
}
.perk-card.perk-selected .perk-icon {
    animation: iconGlow 2s ease-in-out infinite;
}
@keyframes iconGlow {
    0%,100% { box-shadow: 0 0 10px rgba(99,102,241,0.3); }
    50% { box-shadow: 0 0 20px rgba(99,102,241,0.6); }
}

/* Perk name, info, desc and type badge */
.perk-info {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.perk-name {
    font-family: var(--font-heading);
    font-size: 13px;
    font-weight: 800;
    color: var(--text-primary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    line-height: 1.2;
}
.perk-desc {
    font-size: 11px;
    color: var(--text-muted);
    line-height: 1.4;
    margin-top: 4px;
    margin-bottom: 8px;
}
.perk-type-badge {
    align-self: flex-start;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 2px 7px;
    border-radius: 10px;
    margin-top: 4px;
}

/* Tooltip (now obsolete, but disabled in style) */
.perk-tooltip {
    display: none !important;
}

/* Shimmer animation on select */
@keyframes perkShimmer {
    0% { background-position: -200% center; }
    100% { background-position: 200% center; }
}
.perk-card.perk-selected.shimmer {
    background-size: 200% auto;
    animation: perkShimmer 0.6s linear;
}

/* ============ PREVIEW TABS ============ */
.preview-tabs { display: flex; border-bottom: 2px solid var(--border-color); margin-bottom: 24px; }
.preview-tab {
    padding: 10px 20px; font-family: var(--font-heading); font-weight: 700; font-size: 14px;
    color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent;
    margin-bottom: -2px; transition: all 0.2s ease;
}
.preview-tab:hover { color: var(--text-primary); }
.preview-tab.active { color: var(--accent-indigo); border-bottom-color: var(--accent-indigo); }
.preview-tab-content { display: none; }
.preview-tab-content.active { display: block; }

/* Perk cards in preview (Step 3 + personaje.php) */
.gene-card {
    display: flex; align-items: center; gap: 15px; padding: 14px 16px;
    background: var(--bg-main); border: 1px solid var(--border-color); border-radius: var(--radius-lg);
    margin-bottom: 10px; transition: all 0.2s ease;
    position: relative; overflow: hidden;
}
.gene-card:hover { border-color: rgba(99,102,241,0.4); transform: translateX(3px); }
.gene-card.passive-primary { border-left: 3px solid #10b981; }
.gene-card.passive-secondary { border-left: 3px solid #f59e0b; }
.gene-card.perk-racial { border-left: 3px solid var(--accent-indigo); }
.gene-card.perk-general { border-left: 3px solid var(--accent-purple); }
.gene-card-icon {
    width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
}
.gene-card-info { flex: 1; }
.gene-card-name { font-weight: 800; font-size: 13px; color: var(--text-primary); margin-bottom: 2px; font-family: var(--font-heading); text-transform: uppercase; letter-spacing: 0.5px; }
.gene-card-desc { font-size: 11px; color: var(--text-muted); line-height: 1.4; }
.gene-card-badge {
    font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
    padding: 2px 8px; border-radius: 10px; flex-shrink: 0;
}
.gene-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.gene-card {
    align-items: flex-start !important;
}
.rpg-preview-stat-bar { background: var(--bg-card); border-radius: 10px; height: 8px; width: 100%; overflow: hidden; margin-top: 4px; }
.rpg-preview-stat-fill { height: 100%; background: linear-gradient(90deg, var(--accent-indigo), var(--accent-purple)); border-radius: 10px; transition: width 0.5s ease; }
.rpg-preview-stat-row { margin-bottom: 12px; text-align: left; }
</style>

<div class="wizard-container">
    <div class="wizard-header">
        <h1><?= $edit_pj_id > 0 ? 'Actualiza tu Leyenda' : 'Forja tu Leyenda' ?></h1>
        <p><?= $edit_pj_id > 0 ? 'Modifica los datos de tu personaje según las anotaciones del staff.' : 'El camino de un nuevo personaje comienza aquí.' ?></p>
    </div>

    <!-- Progress Bar -->
    <div class="wizard-progress">
        <div class="wizard-step-marker active" id="marker-1">1<div class="wizard-step-label">Identidad</div></div>
        <div class="wizard-step-marker" id="marker-2">2<div class="wizard-step-label">Factor Linaje</div></div>
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
                    <input type="text" id="pj_rank" class="textbox" style="background: var(--bg-main); opacity: 0.8; cursor: not-allowed;" readonly placeholder="Automático según facción">
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
                <div id="hibrido_options" class="wizard-grid-full" style="display:none; background:rgba(99,102,241,0.05); padding:15px; border-radius:var(--radius-md); border:1px dashed var(--accent-indigo);">
                    <div class="wizard-grid">
                        <div class="form-group">
                            <label style="color:var(--accent-indigo);">Raza Dominante *</label>
                            <select id="pj_race_dom" class="textbox">
                                <option value="" disabled selected>Selecciona la dominante</option>
                                <?php foreach($razas as $r): ?>
                                    <option value="<?= $r ?>"><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="color:var(--accent-indigo);">Raza Recesiva *</label>
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
                    <textarea id="pj_physique" class="textbox" placeholder="Describe cómo es físicamente..." style="height: 100px;"></textarea>
                </div>
                <div class="form-group wizard-grid-full">
                    <label>Descripción Psicológica</label>
                    <textarea id="pj_psychology" class="textbox" placeholder="Mentalidad, miedos, motivaciones..." style="height: 100px;"></textarea>
                </div>
                <div class="form-group wizard-grid-full">
                    <label>Otros / Extras</label>
                    <textarea id="pj_extras" class="textbox" placeholder="Cicatrices, tatuajes, objetos importantes..." style="height: 60px;"></textarea>
                </div>
            </div>
        </div>
        <div class="wizard-actions">
            <div></div>
            <button type="button" style="border:none; padding:12px 32px; border-radius: var(--radius-md); cursor:pointer; background: linear-gradient(135deg, var(--accent-indigo), var(--accent-purple)); color: #fff; font-family: var(--font-heading); font-weight:700;" onclick="goToStep(2)">Siguiente: Factor Linaje <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>

    <!-- ==================== PASO 2: FACTOR LINAJE ==================== -->
    <div id="step-2" class="wizard-step-content" style="display:none;">

        <!-- Arquetipo Belico -->
        <div class="wizard-section">
            <h2 class="wizard-section-title"><i class="fas fa-shield-alt"></i> Arquetipo Bélico</h2>
            <p style="color:var(--text-muted); font-size:13px; margin-bottom:15px;">Tu estilo de combate define tu camino. Elige sabiamente.</p>
            <div class="arq-grid">
                <div class="arq-box" onclick="selectArq('Luchador', this)">
                    <div class="arq-icon"><i class="fas fa-fist-raised"></i></div>
                    <div class="arq-name">Luchador</div>
                </div>
                <div class="arq-box" onclick="selectArq('Espadachin', this)">
                    <div class="arq-icon"><i class="fas fa-khanda"></i></div>
                    <div class="arq-name">Espadachín</div>
                </div>
                <div class="arq-box" onclick="selectArq('Tirador', this)">
                    <div class="arq-icon"><i class="fas fa-crosshairs"></i></div>
                    <div class="arq-name">Tirador</div>
                </div>
                <div class="arq-box" onclick="selectArq('Estratega', this)">
                    <div class="arq-icon"><i class="fas fa-chess"></i></div>
                    <div class="arq-name">Estratega</div>
                </div>
            </div>
            <input type="hidden" id="pj_arquetipo" value="">
        </div>

        <!-- Stats + Oficio (side by side) -->
        <div class="wizard-grid">
            <div class="wizard-section" style="margin-bottom:0;">
                <h2 class="wizard-section-title"><i class="fas fa-sliders-h"></i> Atributos Base</h2>
                <div class="stat-distributor">
                    <div class="stat-points-left">Puntos Libres: <span id="pts_left">20</span></div>
                    <div class="stat-row">
                        <div class="stat-name">Fuerza (FUE)</div>
                        <div class="stat-controls">
                            <button type="button" class="stat-btn" onclick="modStat('fue', -1)">−</button>
                            <div class="stat-value" id="val_fue">0</div>
                            <button type="button" class="stat-btn" onclick="modStat('fue', 1)">+</button>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-name">Agilidad (AGI)</div>
                        <div class="stat-controls">
                            <button type="button" class="stat-btn" onclick="modStat('agi', -1)">−</button>
                            <div class="stat-value" id="val_agi">0</div>
                            <button type="button" class="stat-btn" onclick="modStat('agi', 1)">+</button>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-name">Destreza (DES)</div>
                        <div class="stat-controls">
                            <button type="button" class="stat-btn" onclick="modStat('des', -1)">−</button>
                            <div class="stat-value" id="val_des">0</div>
                            <button type="button" class="stat-btn" onclick="modStat('des', 1)">+</button>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-name">Instinto (INST)</div>
                        <div class="stat-controls">
                            <button type="button" class="stat-btn" onclick="modStat('inst', -1)">−</button>
                            <div class="stat-value" id="val_inst">0</div>
                            <button type="button" class="stat-btn" onclick="modStat('inst', 1)">+</button>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-name">Espíritu (ESP)</div>
                        <div class="stat-controls">
                            <button type="button" class="stat-btn" onclick="modStat('esp', -1)">−</button>
                            <div class="stat-value" id="val_esp">0</div>
                            <button type="button" class="stat-btn" onclick="modStat('esp', 1)">+</button>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-name">Intelecto (INT)</div>
                        <div class="stat-controls">
                            <button type="button" class="stat-btn" onclick="modStat('int', -1)">−</button>
                            <div class="stat-value" id="val_int">0</div>
                            <button type="button" class="stat-btn" onclick="modStat('int', 1)">+</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="wizard-section" style="margin-bottom:0;">
                <h2 class="wizard-section-title"><i class="fas fa-anchor"></i> Oficio</h2>
                <p style="color:var(--text-muted); font-size:13px; margin-bottom:20px;">Tu especialidad en el mundo.</p>
                <div class="form-group">
                    <select id="pj_job" class="textbox" style="height:50px; font-size:16px;">
                        <option value="Ninguno" selected>Ninguno / Aprendiz</option>
                        <option value="Médico">Médico</option>
                        <option value="Navegante">Navegante</option>
                        <option value="Cocinero">Cocinero</option>
                        <option value="Carpintero">Carpintero</option>
                        <option value="Erudito">Erudito</option>
                        <option value="Músico">Músico</option>
                        <option value="Timonel">Timonel</option>
                        <option value="Herrero">Herrero</option>
                    </select>
                </div>
                <div style="text-align:center; margin-top: 30px; opacity:0.4;">
                    <i class="fas fa-ship" style="font-size:64px; color:var(--text-muted);"></i>
                </div>
            </div>
        </div>

        <!-- ====== LINAJE — PERK PICKER ====== -->
        <div class="wizard-section" style="margin-top: 30px;">
            <h2 class="wizard-section-title"><i class="fas fa-scroll"></i> Factor Linaje</h2>
            <p style="color:var(--text-muted); font-size:13px; margin-bottom:20px;">Tu raza determina tus habilidades pasivas innatas y los perks de linaje que puedes elegir. Los h&iacute;bridos acceden a las pasivas primarias de ambas razas.</p>

            <!-- Slot counter bar -->
            <div class="linaje-slots-bar" id="linajeSlotBar" style="display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 14px 20px; margin-bottom: 20px; background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(168,85,247,0.03)); border-radius: var(--radius-lg); border: 1px solid rgba(99,102,241,0.2);">
                <div class="linaje-slots-group" style="display: flex; align-items: center; gap: 16px;">
                    <span class="linaje-slots-label" style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);"><i class="fas fa-gem" style="color:var(--accent-indigo);"></i> Puntos de Linaje</span>
                    <div class="linaje-slots-dots" id="linajeDots" style="display: flex; gap: 8px;"></div>
                    <span class="linaje-slots-count" style="font-size: 22px;"><span id="usedPoints">0</span>/<span id="maxPoints">4</span></span>
                </div>
                <div id="linajeSobranteBonus" style="font-size: 12px; font-weight: 700; color: #10b981; text-transform: uppercase; letter-spacing: 0.5px;">
                    Puntos Sobrantes: <span id="sobrantePoints">0</span> PL = <span id="bonusPP">0</span> PP de Bonus
                </div>
            </div>

            <!-- Section 1: Pasivas Raciales -->
            <div class="linaje-section-header" style="color:#10b981;">
                <i class="fas fa-shield-alt" style="color:#10b981;"></i>
                Pasivas Raciales
                <span class="linaje-section-badge" style="background:rgba(16,185,129,0.1); color:#10b981;">Autom&aacute;ticas</span>
            </div>
            <div class="perk-grid" id="gridPasivas"></div>

            <!-- Section 2: Linaje Racial -->
            <div class="linaje-section-header" style="color:var(--accent-indigo);">
                <i class="fas fa-dna" style="color:var(--accent-indigo);"></i>
                Linaje Racial
                <span class="linaje-section-badge" style="background:rgba(99,102,241,0.1); color:var(--accent-indigo);">Elige</span>
            </div>
            <div class="perk-grid" id="gridRacial"></div>

            <!-- Section 3: Linaje General -->
            <div class="linaje-section-header" style="color:var(--accent-purple);">
                <i class="fas fa-star" style="color:var(--accent-purple);"></i>
                Linaje General
                <span class="linaje-section-badge" style="background:rgba(168,85,247,0.1); color:var(--accent-purple);">Elige</span>
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
            <button type="button" style="border:none; padding:12px 24px; border-radius: var(--radius-md); cursor:pointer; background: var(--bg-card); color: var(--text-primary); font-family: var(--font-heading); font-weight:700;" onclick="goToStep(1)"><i class="fas fa-arrow-left"></i> Volver</button>
            <button type="button" style="border:none; padding:12px 32px; border-radius: var(--radius-md); cursor:pointer; background: linear-gradient(135deg, var(--accent-indigo), var(--accent-purple)); color: #fff; font-family: var(--font-heading); font-weight:700;" onclick="goToStep(3)">Generar Expediente <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>

    <!-- ==================== PASO 3: PREVIEW ==================== -->
    <div id="step-3" class="wizard-step-content" style="display:none;">
        <div class="wizard-section" style="padding: 0; display: flex; overflow:hidden; min-height: 600px;">
            <!-- Left -->
            <div style="width: 320px; background: var(--bg-main); border-right: 1px solid var(--border-color); display:flex; flex-direction:column; overflow-y:auto;">
                <div id="preview_avatar" style="width:100%; height:450px; min-height:450px; background-size:cover; background-position:center; background-image:url('https://placehold.co/320x450');"></div>
                <div style="padding: 20px;">
                    <h2 id="preview_name" style="font-family:var(--font-heading); font-size:22px; color:var(--text-primary); margin-bottom:10px; text-align:center;">Nombre</h2>
                    <div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap; margin-bottom: 15px;">
                        <span id="preview_faction" style="background:rgba(99,102,241,0.1); color:var(--accent-indigo); padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-flag"></i> Facción</span>
                        <span id="preview_rank" style="background:rgba(168,85,247,0.1); color:var(--accent-purple); padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-medal"></i> Rango</span>
                    </div>
                    <div style="background: var(--bg-card); border-radius: var(--radius-md); padding: 15px; border: 1px solid var(--border-color); margin-bottom: 15px;">
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom: 10px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">
                            <i id="preview_arq_icon" class="fas fa-shield-alt" style="color:var(--text-secondary); font-size:20px;"></i>
                            <div>
                                <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Arquetipo Bélico</div>
                                <div id="preview_arq_name" style="font-weight:700; color:var(--text-primary); font-size:14px;">Ninguno</div>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom: 10px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">
                            <i class="fas fa-anchor" style="color:var(--text-secondary); font-size:20px;"></i>
                            <div>
                                <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Oficio</div>
                                <div id="preview_job" style="font-weight:700; color:var(--text-primary); font-size:14px;">Ninguno</div>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <i class="fas fa-dna" style="color:var(--accent-purple); font-size:20px;"></i>
                            <div>
                                <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Genes Activos</div>
                                <div id="preview_genes" style="font-weight:700; color:var(--accent-purple); font-size:13px; line-height:1.2;">Ninguno</div>
                            </div>
                        </div>
                    </div>
                    <!-- Derived Stats Preview -->
                    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <div style="flex: 1; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: var(--radius-md); padding: 8px; text-align: center;">
                            <div style="font-size: 9px; color: #f87171; text-transform: uppercase; font-weight: bold;">PV</div>
                            <div style="font-size: 16px; font-weight: 800; color: #ef4444; margin-top: 2px;" id="preview_pv">0</div>
                        </div>
                        <div style="flex: 1; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: var(--radius-md); padding: 8px; text-align: center;">
                            <div style="font-size: 9px; color: #60a5fa; text-transform: uppercase; font-weight: bold;">PE</div>
                            <div style="font-size: 16px; font-weight: 800; color: #3b82f6; margin-top: 2px;" id="preview_pe">0</div>
                        </div>
                    </div>

                    <h3 style="font-size:12px; font-family:var(--font-heading); color:var(--text-muted); text-transform:uppercase; margin-bottom:10px;">Atributos Base</h3>
                    <div class="rpg-preview-stat-row">
                        <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>FUERZA (FUE)</span><span id="pbar_fue_txt">0</span></div>
                        <div class="rpg-preview-stat-bar"><div id="pbar_fue" class="rpg-preview-stat-fill" style="width:0%; background:linear-gradient(90deg, #6366f1, #4f46e5);"></div></div>
                    </div>
                    <div class="rpg-preview-stat-row">
                        <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>AGILIDAD (AGI)</span><span id="pbar_agi_txt">0</span></div>
                        <div class="rpg-preview-stat-bar"><div id="pbar_agi" class="rpg-preview-stat-fill" style="width:0%; background:linear-gradient(90deg,#10b981,#059669);"></div></div>
                    </div>
                    <div class="rpg-preview-stat-row">
                        <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>DESTREZA (DES)</span><span id="pbar_des_txt">0</span></div>
                        <div class="rpg-preview-stat-bar"><div id="pbar_des" class="rpg-preview-stat-fill" style="width:0%; background:linear-gradient(90deg,#3b82f6,#2563eb);"></div></div>
                    </div>
                    <div class="rpg-preview-stat-row">
                        <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>INSTINTO (INST)</span><span id="pbar_inst_txt">0</span></div>
                        <div class="rpg-preview-stat-bar"><div id="pbar_inst" class="rpg-preview-stat-fill" style="width:0%; background:linear-gradient(90deg,#06b6d4,#0891b2);"></div></div>
                    </div>
                    <div class="rpg-preview-stat-row">
                        <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>ESPÍRITU (ESP)</span><span id="pbar_esp_txt">0</span></div>
                        <div class="rpg-preview-stat-bar"><div id="pbar_esp" class="rpg-preview-stat-fill" style="width:0%; background:linear-gradient(90deg,#ec4899,#db2777);"></div></div>
                    </div>
                    <div class="rpg-preview-stat-row">
                        <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>INTELECTO (INT)</span><span id="pbar_int_txt">0</span></div>
                        <div class="rpg-preview-stat-bar"><div id="pbar_int" class="rpg-preview-stat-fill" style="width:0%; background:linear-gradient(90deg,#f59e0b,#d97706);"></div></div>
                    </div>
                </div>
            </div>
            <!-- Right -->
            <div style="flex:1; padding: 40px; overflow-y:auto; background:var(--bg-surface);">
                <div class="preview-tabs">
                    <div class="preview-tab active" onclick="switchPreviewTab('bio', this)"><i class="fas fa-file-alt"></i> Biografía</div>
                    <div class="preview-tab" onclick="switchPreviewTab('linaje', this)"><i class="fas fa-dna"></i> Mapa Genético</div>
                </div>

                <div id="previewTab_bio" class="preview-tab-content active">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:25px; background:var(--bg-main); padding:15px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        <div style="font-size:13px;"><strong>Edad:</strong> <span id="preview_age"></span></div>
                        <div style="font-size:13px;"><strong>Origen:</strong> <span id="preview_origin"></span></div>
                        <div style="font-size:13px;"><strong>Raza:</strong> <span id="preview_race"></span></div>
                        <div style="font-size:13px;"><strong>PB:</strong> <span id="preview_pb"></span></div>
                    </div>
                    <h3 style="font-family:var(--font-heading); font-size:16px; color:var(--text-primary); margin-bottom:10px;">Apariencia Física</h3>
                    <div id="preview_physique" style="color:var(--text-secondary); font-size:14px; line-height:1.6; white-space:pre-wrap; margin-bottom:30px;"></div>
                    <h3 style="font-family:var(--font-heading); font-size:16px; color:var(--text-primary); margin-bottom:10px;">Perfil Psicológico</h3>
                    <div id="preview_psychology" style="color:var(--text-secondary); font-size:14px; line-height:1.6; white-space:pre-wrap; margin-bottom:30px;"></div>
                    <h3 style="font-family:var(--font-heading); font-size:16px; color:var(--text-primary); margin-bottom:10px;">Extras y Notas</h3>
                    <div id="preview_extras" style="color:var(--text-secondary); font-size:14px; line-height:1.6; white-space:pre-wrap;"></div>
                </div>

                <div id="previewTab_linaje" class="preview-tab-content">
                    <p style="color:var(--text-muted); font-size:13px; margin-bottom:20px;">Perks de Linaje del personaje — pasivas innatas y habilidades elegidas.</p>
                    <div id="preview_gene_cards">
                        <!-- Perk preview cards injected by JS -->
                    </div>
                </div>
            </div>
        </div>
        <div class="wizard-actions">
            <button type="button" style="border:none; padding:12px 24px; border-radius: var(--radius-md); cursor:pointer; background: var(--bg-card); color: var(--text-primary); font-family: var(--font-heading); font-weight:700;" onclick="goToStep(2)"><i class="fas fa-arrow-left"></i> Volver</button>
            <button type="button" style="border:none; padding:12px 32px; border-radius: var(--radius-md); cursor:pointer; background: linear-gradient(135deg, #10b981, #059669); color: #fff; font-family: var(--font-heading); font-weight:700;" onclick="guardarPersonaje()"><i class="fas fa-check"></i> <?= $edit_pj_id > 0 ? 'Guardar Correcciones' : 'Aceptar y Crear' ?></button>
        </div>
    </div>
</div>

<script>
var facciones = {
    'Revolucionario':'Iniciado','Marine':'Raso','Gobierno':'Agente',
    'Cazador':'Sin Estrella','Civil':'Ciudadano','Pirata':'Grumete'
};
document.getElementById('pj_faction').addEventListener('change', function(e) {
    document.getElementById('pj_rank').value = facciones[e.target.value] || '';
});
function checkHibrido() {
    var hibBox = document.getElementById('hibrido_options');
    if (document.getElementById('pj_race').value === 'Hibrido') {
        hibBox.style.display = 'block';
    } else {
        hibBox.style.display = 'none';
        document.getElementById('pj_race_dom').value = "";
        document.getElementById('pj_race_rec').value = "";
    }
}

// ==================== PASO 2 LOGIC ====================
var arqIcons = { 'Luchador':'fa-fist-raised','Espadachin':'fa-khanda','Tirador':'fa-crosshairs','Estratega':'fa-chess' };
function selectArq(arq, el) {
    document.querySelectorAll('.arq-box').forEach(function(b){ b.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('pj_arquetipo').value = arq;
}

// --- Stats ---
var ptsMax = 20;
var stats = { fue:0, agi:0, des:0, inst:0, esp:0, int:0 };
function getPtsUsed() { return stats.fue + stats.agi + stats.des + stats.inst + stats.esp + stats.int; }
function modStat(stat, val) {
    if (val > 0 && getPtsUsed() >= ptsMax) return;
    if (val < 0 && stats[stat] <= 0) return;
    if (stats[stat] + val > 10) return;
    stats[stat] += val;
    document.getElementById('val_' + stat).textContent = stats[stat];
    document.getElementById('pts_left').textContent = (ptsMax - getPtsUsed());
}

// ==================== LINAJE PERK SYSTEM ====================
var LINAJE_DATA = <?php echo $catalog_json; ?>;

// State
var selectedRacial = new Set();
var selectedGeneral = new Set();
var currentRace = '';
var currentRaceDom = '';
var currentRaceRec = '';
var maxLinajePoints = 4;

function enrichPerk(p) {
    if (!p) return p;
    if (p.icon && p.iconColor) return p;
    var icon = 'fa-dna';
    var iconColor = '#6366f1';
    var id = p.id || '';
    if (id.startsWith('pp_')) { p.icon = 'fa-shield-alt'; p.iconColor = '#10b981'; return p; }
    if (id.startsWith('ps_')) { p.icon = 'fa-crown'; p.iconColor = '#f59e0b'; return p; }
    if (id.startsWith('g_linaje_fuego')) { icon = 'fa-fire'; iconColor = '#ef4444'; }
    else if (id.startsWith('g_linaje_rayo')) { icon = 'fa-bolt'; iconColor = '#eab308'; }
    else if (id.startsWith('g_linaje_hielo')) { icon = 'fa-snowflake'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_linaje_viento')) { icon = 'fa-wind'; iconColor = '#a855f7'; }
    else if (id.startsWith('g_linaje_tierra')) { icon = 'fa-mountain'; iconColor = '#b45309'; }
    else if (id.startsWith('g_linaje_agua')) { icon = 'fa-water'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_piel_acero')) { icon = 'fa-shield-alt'; iconColor = '#6b7280'; }
    else if (id.startsWith('g_vitalidad')) { icon = 'fa-heartbeat'; iconColor = '#ef4444'; }
    else if (id.startsWith('g_energia')) { icon = 'fa-bolt'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_constitucion')) { icon = 'fa-dumbbell'; iconColor = '#f43f5e'; }
    else if (id.startsWith('g_metabolismo')) { icon = 'fa-utensils'; iconColor = '#10b981'; }
    else if (id.startsWith('g_resistencia')) { icon = 'fa-hand-rock'; iconColor = '#f59e0b'; }
    else if (id.startsWith('g_regeneracion')) { icon = 'fa-leaf'; iconColor = '#10b981'; }
    else if (id.startsWith('g_mente') || id.startsWith('g_intelecto') || id.startsWith('g_lucidez') || id.startsWith('g_concentracion')) { icon = 'fa-brain'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_voluntad_ferrea')) { icon = 'fa-fingerprint'; iconColor = '#6366f1'; }
    else if (id.startsWith('g_instinto')) { icon = 'fa-compass'; iconColor = '#8b5cf6'; }
    else if (id.startsWith('g_paso') || id.startsWith('g_sombra')) { icon = 'fa-user-ninja'; iconColor = '#475569'; }
    else if (id.startsWith('g_agilidad')) { icon = 'fa-running'; iconColor = '#10b981'; }
    else if (id.startsWith('g_evasion')) { icon = 'fa-wind'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_parkour')) { icon = 'fa-shoe-prints'; iconColor = '#f59e0b'; }
    else if (id.startsWith('g_haki_obs')) { icon = 'fa-eye'; iconColor = '#6366f1'; }
    else if (id.startsWith('g_haki_arm')) { icon = 'fa-shield-alt'; iconColor = '#6b7280'; }
    else if (id.startsWith('g_haki_conq')) { icon = 'fa-crown'; iconColor = '#db2777'; }
    else if (id.startsWith('g_suerte') || id.startsWith('g_golpe') || id.startsWith('g_fortuna')) { icon = 'fa-dice-d20'; iconColor = '#f59e0b'; }
    else if (id.startsWith('g_carisma') || id.startsWith('g_presencia') || id.startsWith('g_inspiracion') || id.startsWith('g_nombre_temido') || id.startsWith('g_voz_rey')) { icon = 'fa-comments'; iconColor = '#ec4899'; }
    else if (id.startsWith('g_manos_') || id.startsWith('g_dedos_') || id.startsWith('g_ojo_') || id.startsWith('g_genio_') || id.startsWith('g_cocinero_')) { icon = 'fa-tools'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_cuatro_brazos')) { icon = 'fa-hand-paper'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_tercer_ojo')) { icon = 'fa-eye'; iconColor = '#a855f7'; }
    else if (id.startsWith('g_sangre_fria')) { icon = 'fa-snowflake'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_linaje_marino')) { icon = 'fa-anchor'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_gula')) { icon = 'fa-cookie-bite'; iconColor = '#b45309'; }
    else if (id.startsWith('g_pelo')) { icon = 'fa-magic'; iconColor = '#db2777'; }
    else if (id.startsWith('g_piel_color')) { icon = 'fa-palette'; iconColor = '#10b981'; }
    else if (id.startsWith('g_no_dormir')) { icon = 'fa-eye-slash'; iconColor = '#64748b'; }
    else if (id.startsWith('g_sangre_de_gigante')) { icon = 'fa-expand-arrows-alt'; iconColor = '#ef4444'; }
    else if (id.startsWith('g_cuerpo_elastico')) { icon = 'fa-dumbbell'; iconColor = '#10b981'; }
    else if (id.startsWith('rh_')) { icon = 'fa-user'; iconColor = '#6366f1'; }
    else if (id.startsWith('rm_')) { icon = 'fa-paw'; iconColor = '#10b981'; }
    else if (id.startsWith('rg_')) { icon = 'fa-fish'; iconColor = '#06b6d4'; }
    else if (id.startsWith('rgi_')) { icon = 'fa-expand-arrows-alt'; iconColor = '#ef4444'; }
    else if (id.startsWith('rt_')) { icon = 'fa-seedling'; iconColor = '#10b981'; }
    else if (id.startsWith('rb_')) { icon = 'fa-anchor'; iconColor = '#f59e0b'; }
    else if (id.startsWith('rl_')) { icon = 'fa-feather-alt'; iconColor = '#ec4899'; }
    else if (id.startsWith('rs_')) { icon = 'fa-cloud'; iconColor = '#06b6d4'; }
    else if (id.startsWith('ro_')) { icon = 'fa-ghost'; iconColor = '#ef4444'; }
    else if (id.startsWith('rsi_')) { icon = 'fa-tint'; iconColor = '#3b82f6'; }
    p.icon = icon;
    p.iconColor = iconColor;
    return p;
}

function findPerkById(perkId) {
    if (LINAJE_DATA.arbol_general) {
        for (var catKey in LINAJE_DATA.arbol_general) {
            var cat = LINAJE_DATA.arbol_general[catKey];
            if (cat && cat.perks) {
                var found = cat.perks.find(function(item) { return item.id === perkId; });
                if (found) return { perk: enrichPerk(found), pool: 'general' };
            }
        }
    }
    if (LINAJE_DATA.arboles_raciales) {
        for (var race in LINAJE_DATA.arboles_raciales) {
            var tree = LINAJE_DATA.arboles_raciales[race];
            if (tree && tree.perks) {
                var found = tree.perks.find(function(item) { return item.id === perkId; });
                if (found) return { perk: enrichPerk(found), pool: 'racial', race: race };
            }
        }
    }
    if (LINAJE_DATA.pasivas_primarias) {
        for (var race in LINAJE_DATA.pasivas_primarias) {
            var list = LINAJE_DATA.pasivas_primarias[race] || [];
            var found = list.find(function(item) { return item.id === perkId; });
            if (found) return { perk: enrichPerk(found), pool: 'passive', type: 'primaria', race: race };
        }
    }
    if (LINAJE_DATA.pasivas_secundarias) {
        for (var race in LINAJE_DATA.pasivas_secundarias) {
            var list = LINAJE_DATA.pasivas_secundarias[race] || [];
            var found = list.find(function(item) { return item.id === perkId; });
            if (found) return { perk: enrichPerk(found), pool: 'passive', type: 'secundaria', race: race };
        }
    }
    return null;
}

function getSpentPoints() {
    var total = 0;
    selectedRacial.forEach(function(id) {
        var found = findPerkById(id);
        if (found) total += (found.perk.cost || 1);
    });
    selectedGeneral.forEach(function(id) {
        var found = findPerkById(id);
        if (found) total += (found.perk.cost || 1);
    });
    return total;
}

function getMaxLinajePoints() {
    if (currentRace === 'Hibrido') {
        var ptsDom = LINAJE_DATA.puntos_linaje_por_raza[currentRaceDom] || 20;
        return ptsDom - 4;
    } else {
        if (LINAJE_DATA.puntos_linaje_por_raza[currentRace]) {
            return LINAJE_DATA.puntos_linaje_por_raza[currentRace];
        }
    }
    return 4; // Default fallback
}

function buildLinajeTree() {
    currentRace = document.getElementById('pj_race').value || 'Humano';
    currentRaceDom = '';
    currentRaceRec = '';

    if (currentRace === 'Hibrido') {
        currentRaceDom = document.getElementById('pj_race_dom').value || 'Humano';
        currentRaceRec = document.getElementById('pj_race_rec').value || 'Humano';
    }

    maxLinajePoints = getMaxLinajePoints();

    // Apply edit prefill
    if (window.editLinajeSelected) {
        selectedRacial  = new Set(window.editLinajeSelected.racial  || []);
        selectedGeneral = new Set(window.editLinajeSelected.general || []);
        window.editLinajeSelected = null;
    } else {
        selectedRacial.clear();
        selectedGeneral.clear();
    }

    renderPerkGrids();
    updateSlotCounters();
}

function renderPerkGrids() {
    renderPasivas();
    renderRacial();
    renderGeneral();
}

function renderPasivas() {
    var grid = document.getElementById('gridPasivas');
    var html = '';
    
    if (currentRace === 'Hibrido') {
        var primDom = LINAJE_DATA.pasivas_primarias[currentRaceDom] || [];
        primDom.forEach(function(p) {
            var enriched = enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'primaria' });
            html += buildPerkCardHTML(enriched, 'passive', currentRaceDom);
        });
        var primRec = LINAJE_DATA.pasivas_primarias[currentRaceRec] || [];
        primRec.forEach(function(p) {
            var enriched = enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'primaria' });
            html += buildPerkCardHTML(enriched, 'passive', currentRaceRec);
        });
    } else {
        var prim = LINAJE_DATA.pasivas_primarias[currentRace] || [];
        prim.forEach(function(p) {
            var enriched = enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'primaria' });
            html += buildPerkCardHTML(enriched, 'passive', currentRace);
        });
        var sec = LINAJE_DATA.pasivas_secundarias[currentRace] || [];
        sec.forEach(function(p) {
            var enriched = enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'secundaria' });
            html += buildPerkCardHTML(enriched, 'passive', currentRace);
        });
    }

    if (!html) html = '<p style="color:var(--text-muted); font-size:13px;">Esta raza no tiene pasivas registradas.</p>';
    grid.innerHTML = html;
}

function renderRacial() {
    var grid = document.getElementById('gridRacial');
    var html = '';
    var remaining = maxLinajePoints - getSpentPoints();

    if (currentRace === 'Hibrido') {
        var domTree = LINAJE_DATA.arboles_raciales[currentRaceDom];
        if (domTree && domTree.perks) {
            domTree.perks.forEach(function(p) {
                if (p.solo_puro === true) return;
                
                var isSelected = selectedRacial.has(p.id);
                var cost = p.cost || 1;
                var hasPrereq = true;
                if (p.requires) {
                    hasPrereq = selectedRacial.has(p.requires) || selectedGeneral.has(p.requires);
                }
                var isLocked = !isSelected && (cost > remaining || !hasPrereq);
                var state = isSelected ? 'selected' : (isLocked ? 'locked' : 'selectable');
                
                var enriched = enrichPerk(Object.assign({}, p));
                html += buildPerkCardHTML(enriched, state, currentRaceDom + ' (Dominante)', 'racial');
            });
        }
        var recTree = LINAJE_DATA.arboles_raciales[currentRaceRec];
        if (recTree && recTree.perks) {
            recTree.perks.forEach(function(p) {
                if (p.hibrido_accesible !== true || p.solo_puro === true) return;
                
                var isSelected = selectedRacial.has(p.id);
                var cost = p.cost || 1;
                var hasPrereq = true;
                if (p.requires) {
                    hasPrereq = selectedRacial.has(p.requires) || selectedGeneral.has(p.requires);
                }
                var isLocked = !isSelected && (cost > remaining || !hasPrereq);
                var state = isSelected ? 'selected' : (isLocked ? 'locked' : 'selectable');
                
                var enriched = enrichPerk(Object.assign({}, p));
                html += buildPerkCardHTML(enriched, state, currentRaceRec + ' (Recesiva)', 'racial');
            });
        }
    } else {
        var tree = LINAJE_DATA.arboles_raciales[currentRace];
        if (tree && tree.perks) {
            tree.perks.forEach(function(p) {
                var isSelected = selectedRacial.has(p.id);
                var cost = p.cost || 1;
                var hasPrereq = true;
                if (p.requires) {
                    hasPrereq = selectedRacial.has(p.requires) || selectedGeneral.has(p.requires);
                }
                var isLocked = !isSelected && (cost > remaining || !hasPrereq);
                var state = isSelected ? 'selected' : (isLocked ? 'locked' : 'selectable');
                
                var enriched = enrichPerk(Object.assign({}, p));
                html += buildPerkCardHTML(enriched, state, null, 'racial');
            });
        }
    }

    if (!html) html = '<p style="color:var(--text-muted); font-size:13px;">No hay perks raciales disponibles.</p>';
    grid.innerHTML = html;
    attachPerkClick(grid, 'racial');
}

function renderGeneral() {
    var grid = document.getElementById('gridGeneral');
    var html = '';
    var remaining = maxLinajePoints - getSpentPoints();

    if (LINAJE_DATA.arbol_general) {
        for (var catKey in LINAJE_DATA.arbol_general) {
            var cat = LINAJE_DATA.arbol_general[catKey];
            if (cat && cat.perks) {
                cat.perks.forEach(function(p) {
                    if (currentRace === 'Hibrido' && p.solo_puro === true) return;
                    
                    var isSelected = selectedGeneral.has(p.id);
                    var cost = p.cost || 1;
                    var hasPrereq = true;
                    if (p.requires) {
                        hasPrereq = selectedRacial.has(p.requires) || selectedGeneral.has(p.requires);
                    }
                    var isLocked = !isSelected && (cost > remaining || !hasPrereq);
                    var state = isSelected ? 'selected' : (isLocked ? 'locked' : 'selectable');
                    
                    var enriched = enrichPerk(Object.assign({}, p));
                    html += buildPerkCardHTML(enriched, state, cat.nombre, 'general');
                });
            }
        }
    }
    grid.innerHTML = html;
    attachPerkClick(grid, 'general');
}

function buildPerkCardHTML(perk, state, raceLabel, poolType) {
    var cardClass = 'perk-card';
    var badgeHTML = '';
    var iconBg = '';
    var costBadge = '';

    if (state === 'passive') {
        var isPrimaria = perk.type === 'primaria';
        cardClass += isPrimaria ? ' perk-passive perk-passive-primary' : ' perk-passive perk-passive-secondary';
        iconBg = isPrimaria
            ? 'background: rgba(16,185,129,0.15); border: 2px solid rgba(16,185,129,0.4);'
            : 'background: rgba(245,158,11,0.12); border: 2px solid rgba(245,158,11,0.3);';
        var badgeColor = isPrimaria ? '#10b981' : '#f59e0b';
        var badgeLabel = isPrimaria ? 'PRIMARIA' : 'SECUNDARIA';
        if (raceLabel) badgeLabel = raceLabel.toUpperCase() + ' • ' + badgeLabel;
        badgeHTML = '<div class="perk-type-badge" style="background:' + badgeColor + '22; color:' + badgeColor + ';">' + badgeLabel + '</div>';
    } else {
        var cost = perk.cost || 1;
        if (state === 'selected') {
            cardClass += ' perk-selected';
            var c = poolType === 'racial' ? 'var(--accent-indigo)' : 'var(--accent-purple)';
            iconBg = 'background: rgba(99,102,241,0.2); border: 2px solid ' + c + ';';
            badgeHTML = '<div class="perk-type-badge" style="background:rgba(99,102,241,0.15); color:' + c + ';">' + (poolType === 'racial' ? 'RACIAL' : 'GENERAL') + '</div>';
            costBadge = '<div style="position: absolute; top: 12px; right: 30px; font-family: var(--font-heading); font-size: 10px; font-weight: 800; background: rgba(99, 102, 241, 0.1); color: var(--accent-indigo); padding: 2px 6px; border-radius: 4px;">' + cost + ' PTS</div>';
        } else if (state === 'locked') {
            cardClass += ' perk-locked';
            iconBg = 'background: var(--bg-card); border: 2px solid var(--border-color);';
            var c = poolType === 'racial' ? 'var(--accent-indigo)' : 'var(--accent-purple)';
            badgeHTML = '<div class="perk-type-badge" style="background:var(--border-color); color:var(--text-muted);">' + (poolType === 'racial' ? 'RACIAL' : 'GENERAL') + '</div>';
            costBadge = '<div style="position: absolute; top: 12px; right: 12px; font-family: var(--font-heading); font-size: 10px; font-weight: 800; background: rgba(0, 0, 0, 0.05); color: var(--text-muted); padding: 2px 6px; border-radius: 4px;">' + cost + ' PTS</div>';
        } else {
            // selectable
            var c = poolType === 'racial' ? 'var(--accent-indigo)' : 'var(--accent-purple)';
            iconBg = 'background: rgba(99,102,241,0.08); border: 2px solid rgba(99,102,241,0.2);';
            badgeHTML = '<div class="perk-type-badge" style="background:rgba(99,102,241,0.08); color:' + c + ';">' + (poolType === 'racial' ? 'RACIAL' : 'GENERAL') + '</div>';
            costBadge = '<div style="position: absolute; top: 12px; right: 12px; font-family: var(--font-heading); font-size: 10px; font-weight: 800; background: rgba(99, 102, 241, 0.1); color: var(--accent-indigo); padding: 2px 6px; border-radius: 4px;">' + cost + ' PTS</div>';
        }
    }

    return '<div class="' + cardClass + '" data-perk-id="' + perk.id + '" data-perk-name="' + escHtml(perk.name) + '" data-perk-desc="' + escHtml(perk.desc) + '" data-perk-type="' + (perk.type || poolType) + '">' +
        costBadge +
        '<div class="perk-icon" style="' + iconBg + '">' +
            '<i class="fas ' + perk.icon + '" style="color:' + perk.iconColor + ';"></i>' +
        '</div>' +
        '<div class="perk-info">' +
            '<div class="perk-name">' + perk.name + '</div>' +
            '<div class="perk-desc">' + perk.desc + '</div>' +
            badgeHTML +
        '</div>' +
    '</div>';
}

function escHtml(s) {
    return (s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function checkAndRemoveDependencies() {
    var changed = true;
    while (changed) {
        changed = false;
        selectedRacial.forEach(function(id) {
            var found = findPerkById(id);
            if (found && found.perk.requires) {
                if (!selectedRacial.has(found.perk.requires) && !selectedGeneral.has(found.perk.requires)) {
                    selectedRacial.delete(id);
                    changed = true;
                }
            }
        });
        selectedGeneral.forEach(function(id) {
            var found = findPerkById(id);
            if (found && found.perk.requires) {
                if (!selectedRacial.has(found.perk.requires) && !selectedGeneral.has(found.perk.requires)) {
                    selectedGeneral.delete(id);
                    changed = true;
                }
            }
        });
    }
}

function attachPerkClick(grid, poolType) {
    grid.querySelectorAll('.perk-card:not(.perk-passive):not(.perk-locked)').forEach(function(card) {
        card.addEventListener('click', function() {
            var id = card.getAttribute('data-perk-id');
            var pool = (poolType === 'racial') ? selectedRacial : selectedGeneral;
            var found = findPerkById(id);
            if (!found) return;
            var cost = found.perk.cost || 1;

            if (pool.has(id)) {
                pool.delete(id);
                checkAndRemoveDependencies();
            } else {
                var spent = getSpentPoints();
                if (spent + cost > maxLinajePoints) {
                    return;
                }
                pool.add(id);
                // shimmer effect
                card.classList.add('shimmer');
                setTimeout(function(){ card.classList.remove('shimmer'); }, 700);
            }

            renderRacial();
            renderGeneral();
            updateSlotCounters();
        });
    });
}

function attachPerkHover(grid) {
    // No-op, descriptions are now embedded directly and permanently visible in the cards!
}

function updateSlotCounters() {
    var spent = getSpentPoints();
    var max = maxLinajePoints;
    var usedPointsEl = document.getElementById('usedPoints');
    var maxPointsEl = document.getElementById('maxPoints');
    if (usedPointsEl) usedPointsEl.textContent = spent;
    if (maxPointsEl) maxPointsEl.textContent = max;

    // Dot counters
    var container = document.getElementById('linajeDots');
    if (container) {
        container.innerHTML = '';
        for (var i = 0; i < max; i++) {
            var d = document.createElement('div');
            d.className = 'linaje-slot-dot' + (i < spent ? ' filled' : '');
            container.appendChild(d);
        }
    }

    // Sobrante & PP Bonus
    var sobrante = max - spent;
    var bonusPP = sobrante * 3;
    var sobranteEl = document.getElementById('sobrantePoints');
    var bonusPPEl = document.getElementById('bonusPP');
    if (sobranteEl) sobranteEl.textContent = sobrante;
    if (bonusPPEl) bonusPPEl.textContent = bonusPP;
}

// ==================== PREVIEW TABS ====================
function switchPreviewTab(tabId, tabEl) {
    document.querySelectorAll('.preview-tab').forEach(function(t){ t.classList.remove('active'); });
    document.querySelectorAll('.preview-tab-content').forEach(function(c){ c.classList.remove('active'); });
    tabEl.classList.add('active');
    document.getElementById('previewTab_' + tabId).classList.add('active');
}

// ==================== NAVIGATION ====================
var pjData = {};

function goToStep(step) {
    if (step === 2) {
        if (!document.getElementById('pj_name').value.trim() || !document.getElementById('pj_faction').value || !document.getElementById('pj_race').value) {
            alert("Nombre, Facción y Raza son campos obligatorios."); return;
        }
        if (document.getElementById('pj_race').value === 'Hibrido') {
            if (!document.getElementById('pj_race_dom').value || !document.getElementById('pj_race_rec').value) {
                alert("Si eres híbrido debes seleccionar raza dominante y recesiva."); return;
            }
        }
        buildLinajeTree();
    }
    if (step === 3) {
        if (!document.getElementById('pj_arquetipo').value) {
            alert("Debes seleccionar un Arquetipo Bélico."); return;
        }
        if (getPtsUsed() < ptsMax) {
            if (!confirm('Aún tienes ' + (ptsMax - getPtsUsed()) + ' puntos libres sin gastar. ¿Continuar?')) return;
        }
        generarPreviewJSON();
    }
    document.querySelectorAll('.wizard-step-content').forEach(function(el){ el.style.display = 'none'; });
    document.getElementById('step-' + step).style.display = 'block';
    document.querySelectorAll('.wizard-step-marker').forEach(function(el, i) {
        el.classList.remove('active', 'completed');
        if (i + 1 < step) el.classList.add('completed');
        if (i + 1 === step) el.classList.add('active');
    });
    window.scrollTo(0, 0);
}

function generarPreviewJSON() {
    var raceFinal = document.getElementById('pj_race').value;
    if (raceFinal === 'Hibrido') {
        raceFinal = 'Híbrido (' + document.getElementById('pj_race_dom').value + ' / ' + document.getElementById('pj_race_rec').value + ')';
    }

    var race = document.getElementById('pj_race').value;
    var races = (race === 'Hibrido')
        ? [document.getElementById('pj_race_dom').value, document.getElementById('pj_race_rec').value]
        : [race];

    var pasivasData = [];
    if (race === 'Hibrido') {
        var domPrim = LINAJE_DATA.pasivas_primarias[document.getElementById('pj_race_dom').value] || [];
        domPrim.forEach(function(p) {
            pasivasData.push(enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'primaria' }));
        });
        var recPrim = LINAJE_DATA.pasivas_primarias[document.getElementById('pj_race_rec').value] || [];
        recPrim.forEach(function(p) {
            pasivasData.push(enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'primaria' }));
        });
    } else {
        var prim = LINAJE_DATA.pasivas_primarias[race] || [];
        prim.forEach(function(p) {
            pasivasData.push(enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'primaria' }));
        });
        var sec = LINAJE_DATA.pasivas_secundarias[race] || [];
        sec.forEach(function(p) {
            pasivasData.push(enrichPerk({ id: p.id, name: p.name, desc: p.desc, type: 'secundaria' }));
        });
    }

    var racialData = [];
    if (race === 'Hibrido') {
        var rDom = document.getElementById('pj_race_dom').value;
        var rRec = document.getElementById('pj_race_rec').value;
        var domTree = LINAJE_DATA.arboles_raciales[rDom];
        if (domTree && domTree.perks) {
            domTree.perks.forEach(function(p) {
                if (selectedRacial.has(p.id)) racialData.push(enrichPerk(Object.assign({}, p)));
            });
        }
        var recTree = LINAJE_DATA.arboles_raciales[rRec];
        if (recTree && recTree.perks) {
            recTree.perks.forEach(function(p) {
                if (selectedRacial.has(p.id)) racialData.push(enrichPerk(Object.assign({}, p)));
            });
        }
    } else {
        var tree = LINAJE_DATA.arboles_raciales[race];
        if (tree && tree.perks) {
            tree.perks.forEach(function(p) {
                if (selectedRacial.has(p.id)) racialData.push(enrichPerk(Object.assign({}, p)));
            });
        }
    }

    var generalData = [];
    if (LINAJE_DATA.arbol_general) {
        for (var catKey in LINAJE_DATA.arbol_general) {
            var cat = LINAJE_DATA.arbol_general[catKey];
            if (cat && cat.perks) {
                cat.perks.forEach(function(p) {
                    if (selectedGeneral.has(p.id)) generalData.push(enrichPerk(Object.assign({}, p)));
                });
            }
        }
    }

    var allNames = pasivasData.map(function(p){ return p.name; })
        .concat(racialData.map(function(p){ return p.name; }))
        .concat(generalData.map(function(p){ return p.name; }));

    pjData = {
        pj_id: <?= (int)$edit_pj_id ?>,
        name: document.getElementById('pj_name').value.trim(),
        avatar: document.getElementById('pj_avatar').value.trim() || 'https://placehold.co/320x450',
        faction: document.getElementById('pj_faction').value,
        rank: document.getElementById('pj_rank').value,
        race: raceFinal,
        age: document.getElementById('pj_age').value.trim() || 'Desconocida',
        origin: document.getElementById('pj_origin').value.trim() || 'Desconocido',
        pb: document.getElementById('pj_pb').value.trim() || 'Ninguno',
        physique: document.getElementById('pj_physique').value.trim() || 'Sin registrar.',
        psychology: document.getElementById('pj_psychology').value.trim() || 'Sin registrar.',
        extras: document.getElementById('pj_extras').value.trim() || 'Sin notas.',
        arquetipo: document.getElementById('pj_arquetipo').value,
        job: document.getElementById('pj_job').value,
        stats: JSON.parse(JSON.stringify(stats)),
        linaje: {
            pasivas: pasivasData.map(function(p){ return p.id; }),
            elegidos_racial:  Array.from(selectedRacial),
            elegidos_general: Array.from(selectedGeneral),
            maxPoints: maxLinajePoints,
            usedPoints: getSpentPoints(),
            sobrantePoints: maxLinajePoints - getSpentPoints(),
            bonusPP: (maxLinajePoints - getSpentPoints()) * 3,
            maxSlotsRacial:  2,
            maxSlotsGeneral: 2,
            geneNames: allNames,
            version: 2
        }
    };

    document.getElementById('preview_name').textContent = pjData.name;
    document.getElementById('preview_avatar').style.backgroundImage = "url('" + pjData.avatar + "')";
    document.getElementById('preview_faction').innerHTML = '<i class="fas fa-flag"></i> ' + pjData.faction;
    document.getElementById('preview_rank').innerHTML = '<i class="fas fa-medal"></i> ' + pjData.rank;
    document.getElementById('preview_age').textContent = pjData.age;
    document.getElementById('preview_origin').textContent = pjData.origin;
    document.getElementById('preview_race').textContent = pjData.race;
    document.getElementById('preview_pb').textContent = pjData.pb;
    document.getElementById('preview_physique').textContent = pjData.physique;
    document.getElementById('preview_psychology').textContent = pjData.psychology;
    document.getElementById('preview_extras').textContent = pjData.extras;
    document.getElementById('preview_arq_name').textContent = pjData.arquetipo;
    document.getElementById('preview_arq_icon').className = "fas " + (arqIcons[pjData.arquetipo] || 'fa-shield-alt');
    document.getElementById('preview_job').textContent = pjData.job;
    document.getElementById('preview_genes').textContent = allNames.length ? allNames.join(', ') : 'Ninguno';

    var f = stats.fue || 0;
    var a = stats.agi || 0;
    var d = stats.des || 0;
    var inst = stats.inst || 0;
    var e = stats.esp || 0;
    var it = stats.int || 0;

    var pv = (f * 4) + (a * 2) + (e * 3) + (it * 1);
    var pe = (e * 4) + (d * 3) + (a * 2) + (it * 1);

    document.getElementById('preview_pv').textContent = pv;
    document.getElementById('preview_pe').textContent = pe;

    ['fue','agi','des','inst','esp','int'].forEach(function(s) {
        document.getElementById('pbar_' + s + '_txt').textContent = stats[s];
        document.getElementById('pbar_' + s).style.width = (stats[s] * 10) + '%';
    });

    function makePerkPreviewCard(p, cssClass, iconBg, badgeLabel, badgeColor) {
        var costBadge = p.cost ? '<div style="position: absolute; top: 12px; right: 80px; font-family: var(--font-heading); font-size: 10px; font-weight: 800; background: rgba(99, 102, 241, 0.1); color: var(--accent-indigo); padding: 2px 6px; border-radius: 4px;">' + p.cost + ' PTS</div>' : '';
        return '<div class="gene-card ' + cssClass + '" style="position: relative;">' +
            costBadge +
            '<div class="gene-card-icon" style="' + iconBg + '">' +
                '<i class="fas ' + p.icon + '" style="color:' + p.iconColor + ';"></i>' +
            '</div>' +
            '<div class="gene-card-info">' +
                '<div class="gene-card-name">' + p.name + '</div>' +
                '<div class="gene-card-desc">' + p.desc + '</div>' +
            '</div>' +
            '<div class="gene-card-badge" style="background:' + badgeColor + '22; color:' + badgeColor + ';">' + badgeLabel + '</div>' +
        '</div>';
    }

    var spent = getSpentPoints();
    var max = maxLinajePoints;
    var sobrante = max - spent;
    var bonusPP = sobrante * 3;

    var cardsHTML = '';
    cardsHTML += '<div class="linaje-slots-bar" style="display: flex; align-items: center; justify-content: center; gap: 20px; padding: 14px 20px; margin-bottom: 20px; background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(168,85,247,0.03)); border-radius: var(--radius-lg); border: 1px solid rgba(99,102,241,0.2);">';
    cardsHTML += '    <div class="linaje-slots-group" style="display: flex; align-items: center; gap: 12px;">';
    cardsHTML += '        <span class="linaje-slots-label" style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);"><i class="fas fa-gem" style="color:var(--accent-indigo);"></i> Puntos:</span>';
    cardsHTML += '        <span class="linaje-slots-count" style="font-family: var(--font-heading); font-weight: 900; font-size: 22px; color: var(--accent-purple);">' + spent + '/' + max + '</span>';
    cardsHTML += '        <span style="font-size:12px; font-weight:700; color:#10b981; text-transform: uppercase; margin-left:10px;">(' + sobrante + ' Sobrantes = +' + bonusPP + ' PP Bonus)</span>';
    cardsHTML += '    </div>';
    cardsHTML += '</div>';

    if (pasivasData.length > 0) {
        cardsHTML += '<div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#10b981; margin-bottom:8px; margin-top:4px;"><i class="fas fa-shield-alt"></i> Pasivas Innatas</div>';
        cardsHTML += '<div class="gene-cards-grid">';
        pasivasData.forEach(function(p) {
            var isPrim = p.type === 'primaria';
            cardsHTML += makePerkPreviewCard(p,
                isPrim ? 'passive-primary' : 'passive-secondary',
                isPrim ? 'background:rgba(16,185,129,0.12); border:2px solid rgba(16,185,129,0.35);' : 'background:rgba(245,158,11,0.1); border:2px solid rgba(245,158,11,0.3);',
                isPrim ? 'PRIMARIA' : 'SECUNDARIA',
                isPrim ? '#10b981' : '#f59e0b'
            );
        });
        cardsHTML += '</div>';
    }
    if (racialData.length > 0) {
        cardsHTML += '<div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:var(--accent-indigo); margin-bottom:8px; margin-top:16px;"><i class="fas fa-dna"></i> Linaje Racial</div>';
        cardsHTML += '<div class="gene-cards-grid">';
        racialData.forEach(function(p) {
            cardsHTML += makePerkPreviewCard(p, 'perk-racial',
                'background:rgba(99,102,241,0.1); border:2px solid rgba(99,102,241,0.3);',
                'RACIAL', '#6366f1');
        });
        cardsHTML += '</div>';
    }
    if (generalData.length > 0) {
        cardsHTML += '<div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:var(--accent-purple); margin-bottom:8px; margin-top:16px;"><i class="fas fa-star"></i> Linaje General</div>';
        cardsHTML += '<div class="gene-cards-grid">';
        generalData.forEach(function(p) {
            cardsHTML += makePerkPreviewCard(p, 'perk-general',
                'background:rgba(168,85,247,0.1); border:2px solid rgba(168,85,247,0.3);',
                'GENERAL', '#a855f7');
        });
        cardsHTML += '</div>';
    }
    if (pasivasData.length === 0 && racialData.length === 0 && generalData.length === 0) {
        cardsHTML += '<p style="color:var(--text-muted); font-style:italic;">No se han seleccionado perks adicionales.</p>';
    }
    document.getElementById('preview_gene_cards').innerHTML = cardsHTML;
}

function guardarPersonaje() {
    var btn = document.querySelector('button[onclick="guardarPersonaje()"]');
    var oldText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    btn.disabled = true;

    fetch('<?= rtrim($bb, '/') ?>/game/ajax/save_personaje.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(pjData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            window.location.href = 'personaje.php?pj=' + data.data.pj_id;
        } else {
            alert('Error al guardar: ' + (data.error ? data.error.message : 'Desconocido'));
            btn.innerHTML = oldText;
            btn.disabled = false;
        }
    })
    .catch(err => {
        alert('Error de conexión.');
        btn.innerHTML = oldText;
        btn.disabled = false;
    });
}

// Prefill script
(function(){
    var editData = <?= $edit_data ?: 'null' ?>;
    if (editData) {
        document.getElementById('pj_name').value = editData.name || '';
        document.getElementById('pj_avatar').value = editData.avatar || '';
        document.getElementById('pj_faction').value = editData.faction || '';
        document.getElementById('pj_rank').value = editData.rank || '';
        
        if (editData.race && editData.race.indexOf('Híbrido') === 0) {
            document.getElementById('pj_race').value = 'Hibrido';
            checkHibrido();
            var match = editData.race.match(/Híbrido \((.*) \/ (.*)\)/);
            if (match) {
                document.getElementById('pj_race_dom').value = match[1];
                document.getElementById('pj_race_rec').value = match[2];
            }
        } else {
            document.getElementById('pj_race').value = editData.race || '';
            checkHibrido();
        }
        
        document.getElementById('pj_age').value = editData.age || '';
        document.getElementById('pj_origin').value = editData.origin || '';
        document.getElementById('pj_pb').value = editData.pb || '';
        document.getElementById('pj_physique').value = editData.physique || '';
        document.getElementById('pj_psychology').value = editData.psychology || '';
        document.getElementById('pj_extras').value = editData.extras || '';
        
        if (editData.arquetipo) {
            var box = document.querySelector('.arq-box[onclick="selectArq(\''+editData.arquetipo+'\', this)"]');
            if (box) selectArq(editData.arquetipo, box);
        }
        
        if (editData.stats) {
            stats.fue = editData.stats.fue !== undefined ? editData.stats.fue : (editData.stats.str !== undefined ? editData.stats.str : 0);
            stats.agi = editData.stats.agi !== undefined ? editData.stats.agi : 0;
            stats.des = editData.stats.des !== undefined ? editData.stats.des : (editData.stats.res !== undefined ? editData.stats.res : 0);
            stats.inst = editData.stats.inst !== undefined ? editData.stats.inst : (editData.stats.vol !== undefined ? editData.stats.vol : 0);
            stats.esp = editData.stats.esp !== undefined ? editData.stats.esp : (editData.stats.vol !== undefined ? editData.stats.vol : 0);
            stats.int = editData.stats.int !== undefined ? editData.stats.int : 0;
            
            ['fue','agi','des','inst','esp','int'].forEach(function(s) {
                var el = document.getElementById('val_' + s);
                if(el) el.textContent = stats[s];
            });
            var ptsEl = document.getElementById('pts_left');
            if(ptsEl) ptsEl.textContent = (ptsMax - getPtsUsed());
        }
        
        document.getElementById('pj_job').value = editData.job || 'Ninguno';
        
        if (editData.linaje) {
            // Support both v2 (new perk system) and v1 (legacy DNA tree)
            if (editData.linaje.version === 2) {
                window.editLinajeSelected = {
                    racial:  editData.linaje.elegidos_racial  || [],
                    general: editData.linaje.elegidos_general || []
                };
            }
            // v1 data is silently ignored — user picks fresh on edit
        }
    }
})();
</script>

<?php
$content = ob_get_clean();
game_render_page('Crear Personaje', $content);
