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

$bb = $mybb->settings['bburl'];
$razas = ['Humano', 'Mink', 'Gyojin', 'Gigante', 'Piernas Largas', 'Brazos Largos', 'Cuello Largo', 'Tontatta', 'Buccaner', 'Lunarian', 'Skypean'];

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
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 10px;
}

/* Individual perk card */
.perk-card {
    position: relative;
    background: var(--bg-main);
    border: 2px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 18px 12px 14px;
    text-align: center;
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
    position: absolute; top: 6px; right: 8px;
    font-size: 11px; color: var(--accent-indigo);
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
    width: 52px; height: 52px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 10px;
    font-size: 22px;
    transition: all 0.3s ease;
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

/* Perk name and type badge */
.perk-name {
    font-family: var(--font-heading);
    font-size: 11px;
    font-weight: 800;
    color: var(--text-primary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    line-height: 1.2;
    margin-bottom: 5px;
}
.perk-type-badge {
    display: inline-block;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 2px 7px;
    border-radius: 10px;
    margin-top: 4px;
}

/* Tooltip */
.perk-tooltip {
    position: fixed;
    z-index: 9999;
    pointer-events: none;
    width: 240px;
    background: linear-gradient(135deg, #1e1e2e, #16162a);
    border: 1px solid rgba(99,102,241,0.4);
    border-radius: 12px;
    padding: 14px 16px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.5), 0 0 0 1px rgba(99,102,241,0.1);
    opacity: 0;
    transition: opacity 0.15s ease;
    font-size: 12px;
}
.perk-tooltip.visible { opacity: 1; }
.perk-tooltip-title {
    font-family: var(--font-heading);
    font-weight: 800;
    font-size: 13px;
    margin-bottom: 6px;
}
.perk-tooltip-badge {
    display: inline-block;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 2px 8px;
    border-radius: 10px;
    margin-bottom: 8px;
}
.perk-tooltip-desc {
    color: rgba(255,255,255,0.7);
    line-height: 1.5;
    font-size: 11px;
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
            <div class="linaje-slots-bar" id="linajeSlotBar">
                <div class="linaje-slots-group">
                    <span class="linaje-slots-label"><i class="fas fa-gem" style="color:var(--accent-indigo);"></i> Linaje Racial</span>
                    <div class="linaje-slots-dots" id="dotsRacial"></div>
                    <span class="linaje-slots-count"><span id="usedRacial">0</span>/<span id="maxRacial">2</span></span>
                </div>
                <div style="width:1px; height:30px; background:var(--border-color);"></div>
                <div class="linaje-slots-group">
                    <span class="linaje-slots-label"><i class="fas fa-star" style="color:var(--accent-purple);"></i> Linaje General</span>
                    <div class="linaje-slots-dots" id="dotsGeneral"></div>
                    <span class="linaje-slots-count"><span id="usedGeneral">0</span>/<span id="maxGeneral">2</span></span>
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

var LINAJE_DATA = {
    // slot config: [racialSlots, generalSlots]
    slots: {
        'Humano':       [2, 2],
        'Mink':         [2, 2],
        'Gyojin':       [2, 2],
        'Gigante':      [1, 2],
        'Piernas Largas':[2, 1],
        'Brazos Largos': [2, 1],
        'Cuello Largo':  [1, 2],
        'Tontatta':     [2, 3],
        'Buccaner':     [2, 2],
        'Lunarian':     [2, 2],
        'Skypean':      [2, 2]
    },
    // Pasivas por raza
    pasivas: {
        'Humano': [
            { id:'p_hum_adapt', type:'primaria', name:'Maestro sin Maestro', icon:'fa-graduation-cap', iconColor:'#10b981', desc:'Aprende oficios y t\u00e9cnicas un 20% m\u00e1s r\u00e1pido que otras razas. Bono de adaptabilidad en entornos desconocidos.' },
            { id:'p_hum_luck',  type:'secundaria', name:'Suerte del Mar', icon:'fa-dice', iconColor:'#f59e0b', desc:'Una vez por arco, rerollea autom\u00e1ticamente un dado con desventaja. La fortuna acompa\u00f1a al audaz.' }
        ],
        'Mink': [
            { id:'p_mink_pelaje', type:'primaria', name:'Pelaje Conductor', icon:'fa-bolt', iconColor:'#10b981', desc:'Inmunidad natural al fr\u00edo extremo. +1 a tiradas de resistencia en clima adverso.' },
            { id:'p_mink_electro', type:'primaria', name:'Electro Innato', icon:'fa-charging-station', iconColor:'#06b6d4', desc:'Puede canalizar peque\u00f1as descargas el\u00e9ctricas en combate cuerpo a cuerpo.' },
            { id:'p_mink_noche', type:'secundaria', name:'Instinto Nocturno', icon:'fa-moon', iconColor:'#f59e0b', desc:'Visi\u00f3n perfecta en oscuridad total. Inmune a penalizadores de combate nocturno.' }
        ],
        'Gyojin': [
            { id:'p_gyojin_agua', type:'primaria', name:'Respiraci\u00f3n Anfibia', icon:'fa-water', iconColor:'#10b981', desc:'Respira y combate igual de bien bajo el agua. Velocidad de nado 5x superior al humano.' },
            { id:'p_gyojin_fuerza', type:'primaria', name:'Fuerza de las Profundidades', icon:'fa-dumbbell', iconColor:'#3b82f6', desc:'Fuerza f\u00edsica \u00d710 respecto a un humano medio. Ventaja autom\u00e1tica en tests de fuerza bruta.' },
            { id:'p_gyojin_karate', type:'secundaria', name:'Afinidad Karate Gyojin', icon:'fa-hand-paper', iconColor:'#f59e0b', desc:'Bono de +2 en tiradas de Karate Gyojin. El agua obedece a tu llamada.' }
        ],
        'Gigante': [
            { id:'p_gigante_talla', type:'primaria', name:'Talla Colosal', icon:'fa-expand-arrows-alt', iconColor:'#10b981', desc:'Tu tama\u00f1o f\u00edsico da ventaja en empujes y ataques de \u00e1rea. Inmune a derribo por fuerzas menores.' },
            { id:'p_gigante_pv', type:'primaria', name:'Vida Monumental', icon:'fa-heart', iconColor:'#ef4444', desc:'PV base aumentado en un 30%. Tu vitalidad atemoriza a los rivales.' },
            { id:'p_gigante_terror', type:'secundaria', name:'Presencia Aterradora', icon:'fa-skull', iconColor:'#f59e0b', desc:'Enemigos de nivel bajo deben superar una tirada de moral al enfrentarte directamente.' }
        ],
        'Piernas Largas': [
            { id:'p_ll_velocidad', type:'primaria', name:'Zancada Monumental', icon:'fa-running', iconColor:'#10b981', desc:'Velocidad de movimiento superior en tierra firme. Puedes cubrir distancias enormes en pocos pasos.' },
            { id:'p_ll_alcance', type:'primaria', name:'Alcance Extendido', icon:'fa-arrows-alt-v', iconColor:'#3b82f6', desc:'Ataques de patada tienen rango superior. Puedes golpear objetivos a distancia media sin moverse.' },
            { id:'p_ll_equilibrio', type:'secundaria', name:'Equilibrio Perfecto', icon:'fa-balance-scale', iconColor:'#f59e0b', desc:'Inmune a efectos de derribo en terreno inestable. Nunca pierde el balance en cubierta de barco.' }
        ],
        'Brazos Largos': [
            { id:'p_bl_alcance', type:'primaria', name:'Brazos de Gigante', icon:'fa-hand-rock', iconColor:'#10b981', desc:'Alcance f\u00edsico muy superior. Ventaja en ataques de rango largo y golpes a distancia.' },
            { id:'p_bl_agarre', type:'primaria', name:'Agarre F\u00e9rreo', icon:'fa-grip-strength', iconColor:'#3b82f6', desc:'Muy dif\u00edcil escapar de un agarre o lucha de control. +3 a tiradas de presa.' },
            { id:'p_bl_lanzar', type:'secundaria', name:'Proyectil Viviente', icon:'fa-baseball-ball', iconColor:'#f59e0b', desc:'Puede lanzar objetos medianos con precisi\u00f3n y potencia extremas.' }
        ],
        'Cuello Largo': [
            { id:'p_cl_vision', type:'primaria', name:'Vista Panor\u00e1mica', icon:'fa-eye', iconColor:'#10b981', desc:'Puede elevar la cabeza para ver por encima de obstáculos altos. Ventaja en reconocimiento.' },
            { id:'p_cl_mira', type:'primaria', name:'Mira Natural', icon:'fa-crosshairs', iconColor:'#3b82f6', desc:'Bono a tiradas de observaci\u00f3n y detecci\u00f3n a larga distancia.' },
            { id:'p_cl_oido', type:'secundaria', name:'O\u00eddo Amplificado', icon:'fa-assistive-listening-systems', iconColor:'#f59e0b', desc:'Oye conversaciones lejanas con una tirada de Instinto moderada.' }
        ],
        'Tontatta': [
            { id:'p_ton_mini', type:'primaria', name:'Miniaturizaci\u00f3n Extrema', icon:'fa-compress-arrows-alt', iconColor:'#10b981', desc:'Tama\u00f1o dim\u00ednuto, casi invisible para razas grandes. Ventaja en infiltraci\u00f3n y ocultamiento.' },
            { id:'p_ton_fuerza', type:'primaria', name:'Fuerza Desproporcionada', icon:'fa-fist-raised', iconColor:'#3b82f6', desc:'Fuerza f\u00edsica muy superior a su tama\u00f1o. Puede mover objetos much\u00edsimo m\u00e1s grandes.' },
            { id:'p_ton_herbo', type:'secundaria', name:'Herbolaria \u00c9lite', icon:'fa-leaf', iconColor:'#f59e0b', desc:'Conocimiento de plantas y venenos del Bosque de Tontatta. +2 a tiradas de medicina natural.' }
        ],
        'Buccaner': [
            { id:'p_buc_sangre', type:'primaria', name:'Sangre Ardiente', icon:'fa-fire', iconColor:'#10b981', desc:'El Haki fluye de forma m\u00e1s natural e intensa. Menor tiempo de entrenamiento para desarrollarlo.' },
            { id:'p_buc_aguante', type:'primaria', name:'Cuerpo Forjado', icon:'fa-shield-alt', iconColor:'#ef4444', desc:'Resistencia a lesiones graves. Ignora el primer penalizador de da\u00f1o por combate en cada escena.' },
            { id:'p_buc_leyenda', type:'secundaria', name:'Herencia Legendaria', icon:'fa-crown', iconColor:'#f59e0b', desc:'Figuras de autoridad te reconocen inconscientemente. Bono social con facciones hist\u00f3ricas.' }
        ],
        'Lunarian': [
            { id:'p_lun_fuego', type:'primaria', name:'Llama Racial', icon:'fa-fire-alt', iconColor:'#10b981', desc:'Genera llamas naturales en la espalda. Inmune al da\u00f1o por fuego normal.' },
            { id:'p_lun_vuelo', type:'primaria', name:'Alas de Ceniza', icon:'fa-feather-alt', iconColor:'#8b5cf6', desc:'Puede planar y descender controladamente. No vuelo sostenido, pero saltos enormes.' },
            { id:'p_lun_dura', type:'secundaria', name:'Cuerpo de Piedra', icon:'fa-chess-rook', iconColor:'#f59e0b', desc:'Resistencia f\u00edsica excepcional. Reduce da\u00f1o f\u00edsico recibido un 10% de forma pasiva.' }
        ],
        'Skypean': [
            { id:'p_sky_alas', type:'primaria', name:'Alas de Isla', icon:'fa-wind', iconColor:'#10b981', desc:'Puede planar largas distancias usando corrientes de aire. Control superior en alturas.' },
            { id:'p_sky_mantra', type:'primaria', name:'Observaci\u00f3n Innata', icon:'fa-broadcast-tower', iconColor:'#06b6d4', desc:'Sensibilidad natural al Mantra/Haki de Observaci\u00f3n. Menor umbral para detectarlo.' },
            { id:'p_sky_dial', type:'secundaria', name:'Dialecto del Cielo', icon:'fa-comments', iconColor:'#f59e0b', desc:'Comunicaci\u00f3n fluida con otras razas celestiales. Acceso a conocimientos del Cielo Superior.' }
        ]
    },
    // Perks raciales elegibles por raza
    racial: {
        'Humano': [
            { id:'lr_hum_tenaz', name:'Tenacidad Pura', icon:'fa-hand-fist', iconColor:'#6366f1', desc:'Una vez por evento, no caes inconsciente autom\u00e1ticamente por da\u00f1o letal.' },
            { id:'lr_hum_estudio', name:'Estudiante Dedicado', icon:'fa-book', iconColor:'#6366f1', desc:'Bono +1 en cualquier tirada de Intelecto una vez por escena.' },
            { id:'lr_hum_lider', name:'Liderazgo Natural', icon:'fa-users', iconColor:'#6366f1', desc:'Compa\u00f1eros cercanos ganan +1 en moral mientras no est\u00e9s incapacitado.' }
        ],
        'Mink': [
            { id:'lr_mink_sulong', name:'Furia Sulong', icon:'fa-moon', iconColor:'#6366f1', desc:'Bajo la luna llena, stats ofensivos aumentan dram\u00e1ticamente durante la escena.' },
            { id:'lr_mink_rastro', name:'Rastreador Experto', icon:'fa-paw', iconColor:'#6366f1', desc:'Puede seguir rastros de olfato con \u00e9xito autom\u00e1tico en condiciones normales.' },
            { id:'lr_mink_pack', name:'Mentalidad de Manada', icon:'fa-users-cog', iconColor:'#6366f1', desc:'Bono de coordinaci\u00f3n con aliados. +1 a ataques en pareja con otro personaje.' }
        ],
        'Gyojin': [
            { id:'lr_gyojin_corriente', name:'Maestro de Corrientes', icon:'fa-water', iconColor:'#6366f1', desc:'Control de corrientes marinas en un radio peque\u00f1o. \u00fatil para naufragios y emboscadas acu\u00e1ticas.' },
            { id:'lr_gyojin_peces', name:'Habla con Peces', icon:'fa-fish', iconColor:'#6366f1', desc:'Puede comunicarse con criaturas marinas. Fuente de inteligencia \u00fanica.' },
            { id:'lr_gyojin_sangre', name:'Sangre del Oc\u00e9ano', icon:'fa-tint', iconColor:'#6366f1', desc:'En entornos acu\u00e1ticos, todas las tiradas de combate tienen +1.' }
        ],
        'Gigante': [
            { id:'lr_gigante_arma', name:'Arma Gigante', icon:'fa-hammer', iconColor:'#6366f1', desc:'Puede empuñar armas de tama\u00f1o descomunal inutilizables para otras razas.' },
            { id:'lr_gigante_voz', name:'Voz del Trueno', icon:'fa-volume-up', iconColor:'#6366f1', desc:'Un grito aturde a todos en un radio cercano. Una vez por combate.' }
        ],
        'Piernas Largas': [
            { id:'lr_ll_patada', name:'Patada Devastadora', icon:'fa-shoe-prints', iconColor:'#6366f1', desc:'Una patada cargada rompe estructuras de madera o piedra blanda. +2 a tiradas de impacto.' },
            { id:'lr_ll_corrida', name:'Velocista del Mar', icon:'fa-tachometer-alt', iconColor:'#6366f1', desc:'En campo abierto, nadie puede alcanzarte si decides huir. \u00c9xito autom\u00e1tico en escapar.' }
        ],
        'Brazos Largos': [
            { id:'lr_bl_instrumento', name:'Virtuoso Instrumental', icon:'fa-music', iconColor:'#6366f1', desc:'Bono especial al tocar instrumentos de cuerda. Perfecto para oficios musicales o de precisi\u00f3n.' },
            { id:'lr_bl_trabajo', name:'Trabajador Infatigable', icon:'fa-hard-hat', iconColor:'#6366f1', desc:'Doble rendimiento en tareas manuales largas (construcci\u00f3n, reparaci\u00f3n de barcos, etc.).' }
        ],
        'Cuello Largo': [
            { id:'lr_cl_testigo', name:'Testigo Perfecto', icon:'fa-binoculars', iconColor:'#6366f1', desc:'Nunca puede ser enga\u00f1ado en una escena de negociaci\u00f3n si observa el lenguaje corporal.' },
            { id:'lr_cl_vigia', name:'Viga de Viga', icon:'fa-search', iconColor:'#6366f1', desc:'En barco, su turno de vigia nunca produce falsos negativos.' }
        ],
        'Tontatta': [
            { id:'lr_ton_veneno', name:'Alquimista Secreto', icon:'fa-flask', iconColor:'#6366f1', desc:'Puede fabricar venenos y antidotos con plantas comunes. Efecto moderado garantizado.' },
            { id:'lr_ton_construir', name:'Constructor F\u00e9rreo', icon:'fa-cogs', iconColor:'#6366f1', desc:'Puede reparar mecanismos complejos sin herramientas. Tiempo de reparaci\u00f3n \u00f73.' },
            { id:'lr_ton_red', name:'Red de T\u00faneles', icon:'fa-network-wired', iconColor:'#6366f1', desc:'Conoce o puede crear t\u00faneles subterr\u00e1neos. Movimiento oculto en lugares apropiados.' }
        ],
        'Buccaner': [
            { id:'lr_buc_haki', name:'Legado del Haki', icon:'fa-fist-raised', iconColor:'#6366f1', desc:'Desbloquea el Haki de Armadura o Observaci\u00f3n antes que la media. Entrenamiento acelerado.' },
            { id:'lr_buc_alianza', name:'Pacto de Sangre', icon:'fa-handshake', iconColor:'#6366f1', desc:'Una promesa hecha por un Buccaner es magicamente vinculante. Aliados confían un 30% m\u00e1s.' }
        ],
        'Lunarian': [
            { id:'lr_lun_llama_atk', name:'Llama Ofensiva', icon:'fa-fire', iconColor:'#6366f1', desc:'Puede lanzar bengalas o llamaradas como proyectil. Da\u00f1o de fuego moderado a distancia corta.' },
            { id:'lr_lun_invulnerable', name:'Momento de Piedra', icon:'fa-gem', iconColor:'#6366f1', desc:'Una vez por combate, activa invulnerabilidad total durante 1 acci\u00f3n. La llama en la espalda se apaga.' }
        ],
        'Skypean': [
            { id:'lr_sky_dial_arma', name:'Maestro de Dials', icon:'fa-compact-disc', iconColor:'#6366f1', desc:'Puede usar Dials con maestr\u00eda sin entrenamiento especial. +1 uso por Dial en escena.' },
            { id:'lr_sky_tormenta', name:'Hijo de la Tormenta', icon:'fa-cloud-lightning', iconColor:'#6366f1', desc:'En zonas de tormenta el\u00e9ctrica, tiene ventaja en todas las tiradas f\u00edsicas.' }
        ]
    },
    // Perks generales compartidos
    general: [
        { id:'lg_acero',     name:'Piel de Acero',     icon:'fa-shield-alt',        iconColor:'#a855f7', desc:'Reduce un 5% el da\u00f1o f\u00edsico recibido de forma pasiva.' },
        { id:'lg_voluntad',  name:'Voluntad F\u00e9rrea',  icon:'fa-brain',             iconColor:'#a855f7', desc:'+2 a tiradas de resistencia mental. Inmunidad a efectos de miedo menor.' },
        { id:'lg_sombra',    name:'Paso Silencioso',   icon:'fa-user-ninja',        iconColor:'#a855f7', desc:'Ventaja en tiradas de sigilo en exteriores nocturnos.' },
        { id:'lg_vida',      name:'Vitalidad Extra',   icon:'fa-heartbeat',         iconColor:'#a855f7', desc:'+15 a PV m\u00e1ximos. Tu cuerpo aguanta m\u00e1s de lo normal.' },
        { id:'lg_energia',   name:'Reserva de Energ\u00eda', icon:'fa-bolt',            iconColor:'#a855f7', desc:'+10 a PE m\u00e1ximos. Tu esp\u00edritu arde con fuerza adicional.' },
        { id:'lg_olfato',    name:'Sentido Agudizado', icon:'fa-search',            iconColor:'#a855f7', desc:'Detecci\u00f3n pasiva de emboscadas en un radio de 10m.' },
        { id:'lg_fortuna',   name:'Golpe de Suerte',   icon:'fa-dice-d20',          iconColor:'#a855f7', desc:'Una vez por escena, convierte un fallo en un \u00e9xito menor inesperado.' },
        { id:'lg_navegante', name:'Navegante Instintivo', icon:'fa-compass',         iconColor:'#a855f7', desc:'Bono +2 en tiradas de navegaci\u00f3n. Nunca se pierde en mar abierto.' }
    ]
};

// State
var selectedRacial = new Set();
var selectedGeneral = new Set();
var currentRace = '';
var currentRaceDom = '';
var currentRaceRec = '';
var maxRacialSlots = 2;
var maxGeneralSlots = 2;

function buildLinajeTree() {
    currentRace = document.getElementById('pj_race').value || 'Humano';
    currentRaceDom = '';
    currentRaceRec = '';

    if (currentRace === 'Hibrido') {
        currentRaceDom = document.getElementById('pj_race_dom').value || 'Humano';
        currentRaceRec = document.getElementById('pj_race_rec').value || 'Humano';
    }

    // Load slots config
    var slots = (currentRace === 'Hibrido')
        ? [1 + 1, 2]   // 1 racial from each race + 2 general
        : (LINAJE_DATA.slots[currentRace] || [2, 2]);

    maxRacialSlots  = slots[0];
    maxGeneralSlots = slots[1];

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
    var races = (currentRace === 'Hibrido')
        ? [currentRaceDom, currentRaceRec]
        : [currentRace];

    races.forEach(function(r) {
        var pasivas = LINAJE_DATA.pasivas[r] || [];
        pasivas.forEach(function(p) {
            // Hybrids only get primarias
            if (currentRace === 'Hibrido' && p.type !== 'primaria') return;
            html += buildPerkCardHTML(p, 'passive', r);
        });
    });

    // If pure race, also add secondaries
    if (currentRace !== 'Hibrido') {
        // already included above since we loop full pasivas
    }

    if (!html) html = '<p style="color:var(--text-muted); font-size:13px;">Esta raza no tiene pasivas registradas.</p>';
    grid.innerHTML = html;
    attachPerkHover(grid);
}

function renderRacial() {
    var grid = document.getElementById('gridRacial');
    var html = '';
    var races = (currentRace === 'Hibrido')
        ? [currentRaceDom, currentRaceRec]
        : [currentRace];

    races.forEach(function(r) {
        var racialList = LINAJE_DATA.racial[r] || [];
        racialList.forEach(function(p) {
            var isSelected = selectedRacial.has(p.id);
            var isFull = !isSelected && selectedRacial.size >= maxRacialSlots;
            html += buildPerkCardHTML(p, isSelected ? 'selected' : (isFull ? 'locked' : 'selectable'), null, 'racial');
        });
    });

    if (!html) html = '<p style="color:var(--text-muted); font-size:13px;">No hay perks raciales disponibles.</p>';
    grid.innerHTML = html;
    attachPerkClick(grid, 'racial');
    attachPerkHover(grid);
}

function renderGeneral() {
    var grid = document.getElementById('gridGeneral');
    var html = '';
    LINAJE_DATA.general.forEach(function(p) {
        var isSelected = selectedGeneral.has(p.id);
        var isFull = !isSelected && selectedGeneral.size >= maxGeneralSlots;
        html += buildPerkCardHTML(p, isSelected ? 'selected' : (isFull ? 'locked' : 'selectable'), null, 'general');
    });
    grid.innerHTML = html;
    attachPerkClick(grid, 'general');
    attachPerkHover(grid);
}

function buildPerkCardHTML(perk, state, raceLabel, poolType) {
    var cardClass = 'perk-card';
    var badgeHTML = '';
    var iconBg = '';

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
    } else if (state === 'selected') {
        cardClass += ' perk-selected';
        var c = poolType === 'racial' ? 'var(--accent-indigo)' : 'var(--accent-purple)';
        iconBg = 'background: rgba(99,102,241,0.2); border: 2px solid ' + c + ';';
        badgeHTML = '<div class="perk-type-badge" style="background:rgba(99,102,241,0.15); color:' + c + ';">' + (poolType === 'racial' ? 'RACIAL' : 'GENERAL') + '</div>';
    } else if (state === 'locked') {
        cardClass += ' perk-locked';
        iconBg = 'background: var(--bg-card); border: 2px solid var(--border-color);';
        badgeHTML = '';
    } else {
        // selectable
        iconBg = 'background: rgba(99,102,241,0.08); border: 2px solid rgba(99,102,241,0.2);';
        badgeHTML = '';
    }

    return '<div class="' + cardClass + '" data-perk-id="' + perk.id + '" data-perk-name="' + escHtml(perk.name) + '" data-perk-desc="' + escHtml(perk.desc) + '" data-perk-type="' + (perk.type || poolType) + '">' +
        '<div class="perk-icon" style="' + iconBg + '">' +
            '<i class="fas ' + perk.icon + '" style="color:' + perk.iconColor + ';"></i>' +
        '</div>' +
        '<div class="perk-name">' + perk.name + '</div>' +
        badgeHTML +
    '</div>';
}

function escHtml(s) {
    return (s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function attachPerkClick(grid, poolType) {
    grid.querySelectorAll('.perk-card:not(.perk-passive):not(.perk-locked)').forEach(function(card) {
        card.addEventListener('click', function() {
            var id = card.getAttribute('data-perk-id');
            var pool = (poolType === 'racial') ? selectedRacial : selectedGeneral;
            var maxSlots = (poolType === 'racial') ? maxRacialSlots : maxGeneralSlots;

            if (pool.has(id)) {
                pool.delete(id);
            } else {
                if (pool.size >= maxSlots) return;
                pool.add(id);
                // shimmer effect
                card.classList.add('shimmer');
                setTimeout(function(){ card.classList.remove('shimmer'); }, 700);
            }

            if (poolType === 'racial') renderRacial();
            else renderGeneral();
            updateSlotCounters();
        });
    });
}

function attachPerkHover(grid) {
    var tt = document.getElementById('perkTooltip');
    var ttTitle = document.getElementById('ttTitle');
    var ttBadge = document.getElementById('ttBadge');
    var ttDesc  = document.getElementById('ttDesc');

    grid.querySelectorAll('.perk-card').forEach(function(card) {
        card.addEventListener('mouseenter', function(e) {
            var pType = card.getAttribute('data-perk-type');
            var badgeColor = '#10b981';
            var badgeLabel = 'Pasiva Primaria';
            if (pType === 'secundaria') { badgeColor = '#f59e0b'; badgeLabel = 'Pasiva Secundaria'; }
            else if (pType === 'racial') { badgeColor = '#6366f1'; badgeLabel = 'Linaje Racial'; }
            else if (pType === 'general') { badgeColor = '#a855f7'; badgeLabel = 'Linaje General'; }

            ttTitle.textContent = card.getAttribute('data-perk-name');
            ttBadge.textContent = badgeLabel;
            ttBadge.style.cssText = 'background:' + badgeColor + '22; color:' + badgeColor + ';';
            ttDesc.textContent = card.getAttribute('data-perk-desc');

            var r = card.getBoundingClientRect();
            var left = r.right + 10;
            var top  = r.top;
            if (left + 250 > window.innerWidth) left = r.left - 260;
            if (top + 120 > window.innerHeight) top = window.innerHeight - 130;
            tt.style.left = left + 'px';
            tt.style.top  = top  + 'px';
            tt.classList.add('visible');
        });
        card.addEventListener('mouseleave', function() {
            tt.classList.remove('visible');
        });
    });
}

function updateSlotCounters() {
    document.getElementById('usedRacial').textContent  = selectedRacial.size;
    document.getElementById('maxRacial').textContent   = maxRacialSlots;
    document.getElementById('usedGeneral').textContent = selectedGeneral.size;
    document.getElementById('maxGeneral').textContent  = maxGeneralSlots;

    // Dot counters
    function buildDots(container, used, max) {
        container.innerHTML = '';
        for (var i = 0; i < max; i++) {
            var d = document.createElement('div');
            d.className = 'linaje-slot-dot' + (i < used ? ' filled' : '');
            container.appendChild(d);
        }
    }
    buildDots(document.getElementById('dotsRacial'),  selectedRacial.size,  maxRacialSlots);
    buildDots(document.getElementById('dotsGeneral'), selectedGeneral.size, maxGeneralSlots);
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
    races.forEach(function(r) {
        var pasivas = LINAJE_DATA.pasivas[r] || [];
        pasivas.forEach(function(p) {
            if (race === 'Hibrido' && p.type !== 'primaria') return;
            pasivasData.push(p);
        });
    });

    var racialData = [];
    races.forEach(function(r) {
        (LINAJE_DATA.racial[r] || []).forEach(function(p) {
            if (selectedRacial.has(p.id)) racialData.push(p);
        });
    });

    var generalData = [];
    LINAJE_DATA.general.forEach(function(p) {
        if (selectedGeneral.has(p.id)) generalData.push(p);
    });

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
            pasivas: Array.from(selectedRacial).concat([]).length >= 0 ? pasivasData.map(function(p){ return p.id; }) : [],
            elegidos_racial:  Array.from(selectedRacial),
            elegidos_general: Array.from(selectedGeneral),
            maxSlotsRacial:  maxRacialSlots,
            maxSlotsGeneral: maxGeneralSlots,
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
        return '<div class="gene-card ' + cssClass + '">' +
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

    var cardsHTML = '';
    if (pasivasData.length > 0) {
        cardsHTML += '<div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#10b981; margin-bottom:8px; margin-top:4px;"><i class="fas fa-shield-alt"></i> Pasivas Innatas</div>';
        pasivasData.forEach(function(p) {
            var isPrim = p.type === 'primaria';
            cardsHTML += makePerkPreviewCard(p,
                isPrim ? 'passive-primary' : 'passive-secondary',
                isPrim ? 'background:rgba(16,185,129,0.12); border:2px solid rgba(16,185,129,0.35);' : 'background:rgba(245,158,11,0.1); border:2px solid rgba(245,158,11,0.3);',
                isPrim ? 'PRIMARIA' : 'SECUNDARIA',
                isPrim ? '#10b981' : '#f59e0b'
            );
        });
    }
    if (racialData.length > 0) {
        cardsHTML += '<div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:var(--accent-indigo); margin-bottom:8px; margin-top:16px;"><i class="fas fa-dna"></i> Linaje Racial</div>';
        racialData.forEach(function(p) {
            cardsHTML += makePerkPreviewCard(p, 'perk-racial',
                'background:rgba(99,102,241,0.1); border:2px solid rgba(99,102,241,0.3);',
                'RACIAL', '#6366f1');
        });
    }
    if (generalData.length > 0) {
        cardsHTML += '<div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:var(--accent-purple); margin-bottom:8px; margin-top:16px;"><i class="fas fa-star"></i> Linaje General</div>';
        generalData.forEach(function(p) {
            cardsHTML += makePerkPreviewCard(p, 'perk-general',
                'background:rgba(168,85,247,0.1); border:2px solid rgba(168,85,247,0.3);',
                'GENERAL', '#a855f7');
        });
    }
    if (!cardsHTML) {
        cardsHTML = '<p style="color:var(--text-muted); font-style:italic;">No se han seleccionado perks adicionales.</p>';
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
