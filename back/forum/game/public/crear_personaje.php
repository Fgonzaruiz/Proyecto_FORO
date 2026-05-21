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

// Check slots
$cfg_q = $db->query("SELECT * FROM {$prefix}game_user_config WHERE user_id = {$uid}");
$cfg = $db->fetch_array($cfg_q);
$max_slots = (int)($cfg['max_slots'] ?? 1);
$slots_used = (int)($cfg['slots_used'] ?? 0);

if ($slots_used >= $max_slots) {
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

/* ============ DNA LINAJE TREE ============ */
.linaje-canvas-wrapper {
    position: relative;
    width: 100%;
    min-height: 520px;
    background: radial-gradient(ellipse at center, rgba(99,102,241,0.03) 0%, transparent 70%);
    border-radius: var(--radius-lg);
    overflow: hidden;
}
.linaje-svg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1; }
.linaje-svg line {
    stroke: var(--border-color);
    stroke-width: 2;
    transition: stroke 0.4s ease, stroke-width 0.3s ease;
}
.linaje-svg line.active {
    stroke: url(#lineGrad);
    stroke-width: 3;
    filter: drop-shadow(0 0 4px rgba(99,102,241,0.4));
}

.linaje-node {
    position: absolute;
    width: 56px; height: 56px;
    border-radius: 50%;
    border: 3px solid var(--border-color);
    background: var(--bg-surface);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    z-index: 2;
    transition: all 0.3s cubic-bezier(.4,0,.2,1);
    transform: translate(-50%, -50%);
}
.linaje-node i { font-size: 20px; color: var(--text-muted); transition: all 0.3s ease; }
.linaje-node:hover {
    border-color: rgba(99,102,241,0.6);
    box-shadow: 0 0 20px rgba(99,102,241,0.15);
    transform: translate(-50%, -50%) scale(1.1);
}
.linaje-node.active {
    border-color: var(--accent-indigo);
    background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(168,85,247,0.1));
    box-shadow: 0 0 25px rgba(99,102,241,0.35), inset 0 0 12px rgba(99,102,241,0.08);
}
.linaje-node.active i { color: var(--accent-indigo); }
.linaje-node.locked {
    opacity: 0.35;
    cursor: not-allowed;
    border-style: dashed;
}
.linaje-node.locked:hover { transform: translate(-50%, -50%) scale(1); box-shadow: none; border-color: var(--border-color); }
.linaje-node.core {
    width: 72px; height: 72px;
    border-width: 4px;
    background: linear-gradient(135deg, rgba(168,85,247,0.15), rgba(236,72,153,0.1));
    border-color: var(--accent-purple);
    box-shadow: 0 0 30px rgba(168,85,247,0.2);
}
.linaje-node.core i { font-size: 26px; color: var(--accent-purple); }

.linaje-node-label {
    position: absolute;
    top: calc(100% + 6px);
    left: 50%;
    transform: translateX(-50%);
    font-size: 10px;
    font-weight: 700;
    font-family: var(--font-heading);
    color: var(--text-muted);
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    pointer-events: none;
    transition: color 0.3s ease;
}
.linaje-node.active .linaje-node-label { color: var(--accent-indigo); }

/* Tooltip */
.linaje-tooltip {
    position: absolute;
    background: var(--bg-surface);
    border: 1px solid var(--accent-indigo);
    border-radius: var(--radius-md);
    padding: 12px 16px;
    z-index: 100;
    width: 220px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.25);
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.2s ease;
}
.linaje-tooltip.visible { opacity: 1; }
.linaje-tooltip-title { font-family: var(--font-heading); font-weight: 700; font-size: 14px; color: var(--accent-indigo); margin-bottom: 5px; }
.linaje-tooltip-desc { font-size: 12px; color: var(--text-secondary); line-height: 1.4; }

/* Race slots indicator */
.linaje-slots-bar {
    display: flex; align-items: center; justify-content: center; gap: 15px;
    padding: 12px; margin-bottom: 15px;
    background: var(--bg-main); border-radius: var(--radius-md); border: 1px solid var(--border-color);
}
.linaje-slots-count { font-family: var(--font-heading); font-weight: 900; font-size: 20px; color: var(--accent-purple); }
.linaje-slots-label { font-size: 13px; color: var(--text-muted); }

/* Pulse animation */
@keyframes nodePulse {
    0%, 100% { box-shadow: 0 0 25px rgba(99,102,241,0.35), inset 0 0 12px rgba(99,102,241,0.08); }
    50% { box-shadow: 0 0 35px rgba(99,102,241,0.5), inset 0 0 18px rgba(99,102,241,0.12); }
}
.linaje-node.active { animation: nodePulse 3s ease-in-out infinite; }
.linaje-node.core { animation: nodePulse 2.5s ease-in-out infinite; }

/* ============ PREVIEW ============ */
.rpg-preview-stat-bar { background: var(--bg-card); border-radius: 10px; height: 8px; width: 100%; overflow: hidden; margin-top: 4px; }
.rpg-preview-stat-fill { height: 100%; background: linear-gradient(90deg, var(--accent-indigo), var(--accent-purple)); border-radius: 10px; transition: width 0.5s ease; }
.rpg-preview-stat-row { margin-bottom: 12px; text-align: left; }
</style>

<div class="wizard-container">
    <div class="wizard-header">
        <h1>Forja tu Leyenda</h1>
        <p>El camino de un nuevo personaje comienza aquí.</p>
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
                        <option value="Neomarine">Neomarine</option>
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
                        <div class="stat-name">Fuerza</div>
                        <div class="stat-controls">
                            <button type="button" class="stat-btn" onclick="modStat('str', -1)">−</button>
                            <div class="stat-value" id="val_str">0</div>
                            <button type="button" class="stat-btn" onclick="modStat('str', 1)">+</button>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-name">Agilidad</div>
                        <div class="stat-controls">
                            <button type="button" class="stat-btn" onclick="modStat('agi', -1)">−</button>
                            <div class="stat-value" id="val_agi">0</div>
                            <button type="button" class="stat-btn" onclick="modStat('agi', 1)">+</button>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-name">Resistencia</div>
                        <div class="stat-controls">
                            <button type="button" class="stat-btn" onclick="modStat('res', -1)">−</button>
                            <div class="stat-value" id="val_res">0</div>
                            <button type="button" class="stat-btn" onclick="modStat('res', 1)">+</button>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-name">Voluntad</div>
                        <div class="stat-controls">
                            <button type="button" class="stat-btn" onclick="modStat('vol', -1)">−</button>
                            <div class="stat-value" id="val_vol">0</div>
                            <button type="button" class="stat-btn" onclick="modStat('vol', 1)">+</button>
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

        <!-- ====== LINAJE DNA TREE ====== -->
        <div class="wizard-section" style="margin-top: 30px;">
            <h2 class="wizard-section-title"><i class="fas fa-dna"></i> Mapa Genético — Linaje</h2>
            <p style="color:var(--text-muted); font-size:13px; margin-bottom:10px;">Activa los genes heredados de tu linaje. Cada raza tiene un número distinto de nodos disponibles. Las esferas conectadas se desbloquean al activar su nodo padre.</p>
            
            <div class="linaje-slots-bar">
                <div><span class="linaje-slots-label">Nodos Activables:</span></div>
                <div class="linaje-slots-count"><span id="linaje_used">0</span> / <span id="linaje_max">5</span></div>
            </div>

            <div class="linaje-canvas-wrapper" id="linajeCanvas">
                <!-- SVG connections -->
                <svg class="linaje-svg" id="linajeSVG">
                    <defs>
                        <linearGradient id="lineGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" style="stop-color:rgb(99,102,241);stop-opacity:0.8" />
                            <stop offset="100%" style="stop-color:rgb(168,85,247);stop-opacity:0.8" />
                        </linearGradient>
                    </defs>
                </svg>
                <!-- Nodes get injected by JS -->
                <div class="linaje-tooltip" id="linajeTooltip">
                    <div class="linaje-tooltip-title" id="ttTitle"></div>
                    <div class="linaje-tooltip-desc" id="ttDesc"></div>
                </div>
            </div>
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
                    <h3 style="font-size:12px; font-family:var(--font-heading); color:var(--text-muted); text-transform:uppercase; margin-bottom:10px;">Atributos Base</h3>
                    <div class="rpg-preview-stat-row">
                        <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>FUERZA</span><span id="pbar_str_txt">0</span></div>
                        <div class="rpg-preview-stat-bar"><div id="pbar_str" class="rpg-preview-stat-fill" style="width:0%;"></div></div>
                    </div>
                    <div class="rpg-preview-stat-row">
                        <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>AGILIDAD</span><span id="pbar_agi_txt">0</span></div>
                        <div class="rpg-preview-stat-bar"><div id="pbar_agi" class="rpg-preview-stat-fill" style="width:0%; background:linear-gradient(90deg,#10b981,#059669);"></div></div>
                    </div>
                    <div class="rpg-preview-stat-row">
                        <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>RESISTENCIA</span><span id="pbar_res_txt">0</span></div>
                        <div class="rpg-preview-stat-bar"><div id="pbar_res" class="rpg-preview-stat-fill" style="width:0%; background:linear-gradient(90deg,#f59e0b,#d97706);"></div></div>
                    </div>
                    <div class="rpg-preview-stat-row">
                        <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>VOLUNTAD</span><span id="pbar_vol_txt">0</span></div>
                        <div class="rpg-preview-stat-bar"><div id="pbar_vol" class="rpg-preview-stat-fill" style="width:0%; background:linear-gradient(90deg,#ef4444,#dc2626);"></div></div>
                    </div>
                </div>
            </div>
            <!-- Right -->
            <div style="flex:1; padding: 40px; overflow-y:auto; background:var(--bg-surface);">
                <h2 class="wizard-section-title"><i class="fas fa-file-alt"></i> Datos Biográficos</h2>
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
        </div>
        <div class="wizard-actions">
            <button type="button" style="border:none; padding:12px 24px; border-radius: var(--radius-md); cursor:pointer; background: var(--bg-card); color: var(--text-primary); font-family: var(--font-heading); font-weight:700;" onclick="goToStep(2)"><i class="fas fa-arrow-left"></i> Volver</button>
            <button type="button" style="border:none; padding:12px 32px; border-radius: var(--radius-md); cursor:pointer; background: linear-gradient(135deg, #10b981, #059669); color: #fff; font-family: var(--font-heading); font-weight:700;" onclick="guardarPersonaje()"><i class="fas fa-check"></i> Aceptar y Crear</button>
        </div>
    </div>
</div>

<script>
// ==================== PASO 1 LOGIC ====================
const facciones = {
    'Revolucionario':'Iniciado','Marine':'Raso','Gobierno':'Agente',
    'Neomarine':'Soldado','Civil':'Ciudadano','Pirata':'Grumete'
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

// --- Arquetipo ---
const arqIcons = { 'Luchador':'fa-fist-raised','Espadachin':'fa-khanda','Tirador':'fa-crosshairs','Estratega':'fa-chess' };
function selectArq(arq, el) {
    document.querySelectorAll('.arq-box').forEach(function(b){ b.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('pj_arquetipo').value = arq;
}

// --- Stats ---
var ptsMax = 20;
var stats = { str:0, agi:0, res:0, vol:0 };
function getPtsUsed() { return stats.str + stats.agi + stats.res + stats.vol; }
function modStat(stat, val) {
    if (val > 0 && getPtsUsed() >= ptsMax) return;
    if (val < 0 && stats[stat] <= 0) return;
    if (stats[stat] + val > 10) return;
    stats[stat] += val;
    document.getElementById('val_' + stat).textContent = stats[stat];
    document.getElementById('pts_left').textContent = (ptsMax - getPtsUsed());
}

// ==================== LINAJE DNA TREE ====================
// How many nodes each race can activate (example data — you'll customize later)
var raceSlots = {
    'Humano':5, 'Mink':6, 'Gyojin':6, 'Gigante':4, 'Piernas Largas':5,
    'Brazos Largos':5, 'Cuello Largo':5, 'Tontatta':7, 'Buccaner':5,
    'Lunarian':6, 'Skypean':6, 'Hibrido':4
};

// Node definitions: id, x%, y%, icon, name, desc, requires (parent node id or null)
var linajeNodes = [
    // CORE (center top)
    { id:'core', x:50, y:8, icon:'fa-dna', name:'Núcleo Genético', desc:'El origen de tu linaje. Tu sangre corre con la fuerza de tus ancestros.', requires:null, core:true, preselected:true },

    // Tier 1 (branches from core)
    { id:'vit',   x:20, y:22, icon:'fa-heart',        name:'Vitalidad Ancestral', desc:'+10% a la salud máxima base. Tu cuerpo resiste más de lo normal.', requires:'core' },
    { id:'inst',  x:50, y:25, icon:'fa-eye',           name:'Instinto Primario',   desc:'Mejora la percepción en situaciones de peligro inminente.', requires:'core' },
    { id:'adapt', x:80, y:22, icon:'fa-sync-alt',      name:'Adaptabilidad',       desc:'Reduces el penalizador al cambiar de entorno o clima.', requires:'core' },

    // Tier 2 (left branch — physical)
    { id:'iron',  x:10, y:40, icon:'fa-shield-alt',    name:'Piel de Hierro',      desc:'Reduces un 5% el daño físico recibido de forma pasiva.', requires:'vit' },
    { id:'regen', x:30, y:42, icon:'fa-first-aid',     name:'Regeneración Menor',  desc:'Recuperas un pequeño porcentaje de salud entre combates.', requires:'vit' },

    // Tier 2 (center branch — mental)
    { id:'will',  x:40, y:44, icon:'fa-brain',         name:'Mente Blindada',      desc:'Resistencia a efectos de miedo, confusión y control mental.', requires:'inst' },
    { id:'sixth', x:60, y:44, icon:'fa-bolt',          name:'Sexto Sentido',       desc:'Posibilidad de esquivar ataques a traición automáticamente.', requires:'inst' },

    // Tier 2 (right branch — utility)
    { id:'camo',  x:70, y:40, icon:'fa-mask',          name:'Camuflaje Natural',   desc:'Eres más difícil de detectar en entornos naturales.', requires:'adapt' },
    { id:'swim',  x:90, y:40, icon:'fa-water',         name:'Afinidad Acuática',   desc:'Nadas más rápido y aguantas más la respiración bajo el agua.', requires:'adapt' },

    // Tier 3 (deep nodes)
    { id:'berserk', x:10, y:60, icon:'fa-fire-alt',    name:'Furia Berserker',     desc:'Cuando tu salud baja del 20%, tu daño aumenta un 15%.', requires:'iron' },
    { id:'undying', x:30, y:62, icon:'fa-skull',       name:'Difícil de Matar',    desc:'Una vez por evento, sobrevives un golpe letal con 1HP.', requires:'regen' },
    { id:'clarity', x:40, y:65, icon:'fa-moon',        name:'Claridad Absoluta',   desc:'Inmunidad total a aturdimiento en el primer turno de combate.', requires:'will' },
    { id:'reflex',  x:60, y:65, icon:'fa-running',     name:'Reflejos de Rayo',    desc:'+15% velocidad de reacción en los primeros segundos de combate.', requires:'sixth' },
    { id:'shadow',  x:70, y:60, icon:'fa-user-ninja',  name:'Paso de Sombra',      desc:'Puedes moverte sin ser detectado durante 1 turno por evento.', requires:'camo' },
    { id:'tide',    x:90, y:60, icon:'fa-fish',        name:'Hijo de la Marea',    desc:'Bajo el agua, tus stats no se reducen como a otros personajes.', requires:'swim' },

    // Tier 4 (final deep legendary)
    { id:'titan',   x:20, y:82, icon:'fa-mountain',    name:'Voluntad de Titán',   desc:'Una vez por arco, puedes ignorar completamente el daño de un ataque.', requires:'berserk' },
    { id:'oracle',  x:50, y:85, icon:'fa-hat-wizard',  name:'Visión del Oráculo',  desc:'Puedes predecir la intención del enemigo en el próximo turno.', requires:'clarity' },
    { id:'wraith',  x:80, y:82, icon:'fa-ghost',       name:'Forma Espectral',     desc:'Una vez por evento, te vuelves intangible durante 1 acción.', requires:'shadow' }
];

// Pre-selected nodes by race (core is always selected)
var racePreselected = {
    'Humano':     ['core','inst'],
    'Mink':       ['core','inst','adapt'],
    'Gyojin':     ['core','adapt','swim'],
    'Gigante':    ['core','vit','iron'],
    'Piernas Largas':['core','inst','sixth'],
    'Brazos Largos': ['core','vit','regen'],
    'Cuello Largo':  ['core','adapt'],
    'Tontatta':   ['core','inst','adapt','camo'],
    'Buccaner':   ['core','vit','iron'],
    'Lunarian':   ['core','vit','adapt'],
    'Skypean':    ['core','adapt','camo'],
    'Hibrido':    ['core']
};

var activeNodes = new Set();
var maxLinajeSlots = 5;
var linajeBuilt = false;

function buildLinajeTree() {
    var canvas = document.getElementById('linajeCanvas');
    var svg = document.getElementById('linajeSVG');

    // Remove old nodes (not SVG, not tooltip)
    canvas.querySelectorAll('.linaje-node').forEach(function(n){ n.remove(); });
    // Clear SVG lines
    while (svg.childNodes.length > 1) { svg.removeChild(svg.lastChild); }

    // Determine race
    var race = document.getElementById('pj_race').value || 'Humano';
    if (race === 'Hibrido') {
        var dom = document.getElementById('pj_race_dom').value || 'Humano';
        maxLinajeSlots = Math.max(raceSlots[dom] || 4, 4);
    } else {
        maxLinajeSlots = raceSlots[race] || 5;
    }
    document.getElementById('linaje_max').textContent = maxLinajeSlots;

    // Set preselected
    activeNodes = new Set(racePreselected[race] || ['core']);

    // Draw connections first
    linajeNodes.forEach(function(node) {
        if (!node.requires) return;
        var parent = linajeNodes.find(function(n){ return n.id === node.requires; });
        if (!parent) return;
        var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', parent.x + '%');
        line.setAttribute('y1', parent.y + '%');
        line.setAttribute('x2', node.x + '%');
        line.setAttribute('y2', node.y + '%');
        line.setAttribute('data-from', parent.id);
        line.setAttribute('data-to', node.id);
        svg.appendChild(line);
    });

    // Draw nodes
    linajeNodes.forEach(function(node) {
        var el = document.createElement('div');
        el.className = 'linaje-node' + (node.core ? ' core' : '');
        el.setAttribute('data-id', node.id);
        el.style.left = node.x + '%';
        el.style.top = node.y + '%';
        el.innerHTML = '<i class="fas ' + node.icon + '"></i><div class="linaje-node-label">' + node.name + '</div>';

        el.addEventListener('click', function(){ toggleNode(node.id); });
        el.addEventListener('mouseenter', function(e){ showTooltip(node, e); });
        el.addEventListener('mouseleave', hideTooltip);

        canvas.appendChild(el);
    });

    updateLinajeVisuals();
    linajeBuilt = true;
}

function toggleNode(nodeId) {
    var nodeDef = linajeNodes.find(function(n){ return n.id === nodeId; });
    if (!nodeDef) return;

    // Core can never be deactivated
    if (nodeDef.core) return;

    if (activeNodes.has(nodeId)) {
        // Check if any child depends on this and is active
        var hasActiveChild = linajeNodes.some(function(n) {
            return n.requires === nodeId && activeNodes.has(n.id);
        });
        if (hasActiveChild) return; // can't deactivate a node with active children
        activeNodes.delete(nodeId);
    } else {
        // Check requirements
        if (nodeDef.requires && !activeNodes.has(nodeDef.requires)) return; // parent not active
        if (activeNodes.size >= maxLinajeSlots) return; // no slots left
        activeNodes.add(nodeId);
    }
    updateLinajeVisuals();
}

function updateLinajeVisuals() {
    document.getElementById('linaje_used').textContent = activeNodes.size;

    // Update node visuals
    document.querySelectorAll('.linaje-node').forEach(function(el) {
        var id = el.getAttribute('data-id');
        var nodeDef = linajeNodes.find(function(n){ return n.id === id; });
        el.classList.remove('active', 'locked');
        if (activeNodes.has(id)) {
            el.classList.add('active');
        } else if (nodeDef && nodeDef.requires && !activeNodes.has(nodeDef.requires)) {
            el.classList.add('locked');
        } else if (activeNodes.size >= maxLinajeSlots) {
            el.classList.add('locked');
        }
    });

    // Update line visuals
    document.querySelectorAll('.linaje-svg line').forEach(function(line) {
        var from = line.getAttribute('data-from');
        var to = line.getAttribute('data-to');
        if (activeNodes.has(from) && activeNodes.has(to)) {
            line.classList.add('active');
        } else {
            line.classList.remove('active');
        }
    });
}

function showTooltip(node, e) {
    var tt = document.getElementById('linajeTooltip');
    document.getElementById('ttTitle').textContent = node.name;
    document.getElementById('ttDesc').textContent = node.desc;
    var canvas = document.getElementById('linajeCanvas');
    var rect = canvas.getBoundingClientRect();
    var x = (node.x / 100) * rect.width;
    var y = (node.y / 100) * rect.height;
    // Position tooltip
    var ttLeft = x + 40;
    if (ttLeft + 220 > rect.width) ttLeft = x - 260;
    tt.style.left = ttLeft + 'px';
    tt.style.top = (y - 20) + 'px';
    tt.classList.add('visible');
}
function hideTooltip() {
    document.getElementById('linajeTooltip').classList.remove('visible');
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
        // Build/rebuild linaje tree when entering step 2
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

    // Collect active gene names
    var geneNames = [];
    activeNodes.forEach(function(id) {
        var n = linajeNodes.find(function(nd){ return nd.id === id; });
        if (n && !n.core) geneNames.push(n.name);
    });

    pjData = {
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
            activeNodeIds: Array.from(activeNodes),
            geneNames: geneNames,
            maxSlots: maxLinajeSlots
        }
    };

    // Inject into preview DOM
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
    document.getElementById('preview_genes').textContent = geneNames.length ? geneNames.join(', ') : 'Ninguno';

    ['str','agi','res','vol'].forEach(function(s) {
        document.getElementById('pbar_' + s + '_txt').textContent = stats[s];
        document.getElementById('pbar_' + s).style.width = (stats[s] * 10) + '%';
    });
}

function guardarPersonaje() {
    console.log("JSON FINAL:", JSON.stringify(pjData, null, 2));
    alert("¡Personaje configurado!\n(Modo simulación — abre la consola F12 para ver el JSON completo incluyendo el mapa genético).\n\n" + JSON.stringify(pjData, null, 2));
}
</script>

<?php
$content = ob_get_clean();
game_render_page('Crear Personaje', $content);
