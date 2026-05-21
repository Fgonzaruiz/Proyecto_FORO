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
/* Estilos específicos para el creador RPG */
.wizard-container { max-width: 1100px; margin: 0 auto; }
.wizard-header { margin-bottom: 30px; text-align: center; }
.wizard-header h1 { font-size: 32px; font-family: var(--font-heading); color: var(--text-primary); margin-bottom: 10px; }
.wizard-header p { color: var(--text-muted); font-size: 15px; }

/* Barra de progreso */
.wizard-progress { display: flex; justify-content: space-between; position: relative; margin-bottom: 40px; padding: 0 40px; }
.wizard-progress::before {
    content: ''; position: absolute; top: 50%; left: 60px; right: 60px; height: 4px; background: var(--border-color); transform: translateY(-50%); z-index: 1; border-radius: 2px;
}
.wizard-step-marker {
    width: 40px; height: 40px; border-radius: 50%; background: var(--bg-surface); border: 4px solid var(--border-color);
    display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--text-muted); position: relative; z-index: 2; transition: all 0.3s ease;
}
.wizard-step-marker.active { border-color: var(--accent-indigo); color: var(--accent-indigo); box-shadow: 0 0 15px rgba(99,102,241,0.3); }
.wizard-step-marker.completed { border-color: #10b981; color: #10b981; background: rgba(16,185,129,0.1); }
.wizard-step-label { position: absolute; top: 50px; font-size: 12px; font-weight: 600; white-space: nowrap; color: var(--text-muted); }

.wizard-section {
    background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-xl); padding: 30px; margin-bottom: 30px; box-shadow: var(--shadow-card);
}
.wizard-section-title { font-family: var(--font-heading); font-size: 20px; color: var(--accent-indigo); border-bottom: 2px solid rgba(99,102,241,0.2); padding-bottom: 10px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }

.wizard-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.wizard-grid-full { grid-column: span 2; }
.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group label { font-family: var(--font-heading); font-weight: 600; font-size: 13px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }

/* Arquetipos / Clases */
.arq-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
.arq-box {
    background: var(--bg-main); border: 2px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s ease;
}
.arq-box:hover { border-color: rgba(99,102,241,0.5); transform: translateY(-3px); }
.arq-box.selected { border-color: var(--accent-indigo); background: rgba(99,102,241,0.05); box-shadow: 0 4px 20px rgba(99,102,241,0.2); }
.arq-icon { font-size: 32px; color: var(--text-secondary); margin-bottom: 10px; transition: color 0.2s ease; }
.arq-box.selected .arq-icon { color: var(--accent-indigo); }
.arq-name { font-weight: 700; font-size: 14px; color: var(--text-primary); }

/* Repartidor de Stats */
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

/* Árbol de Talentos Simulador */
.talent-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 15px; }
.talent-box {
    background: var(--bg-main); border: 2px solid var(--border-color); border-radius: var(--radius-lg); padding: 15px; text-align: center; cursor: pointer; transition: all 0.2s ease; position: relative; overflow: hidden;
}
.talent-box:hover { border-color: var(--accent-purple); }
.talent-box.selected { border-color: var(--accent-purple); background: rgba(168,85,247,0.05); }
.talent-box.selected::before { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; top: 10px; right: 10px; color: var(--accent-purple); }
.talent-icon { font-size: 24px; color: var(--accent-purple); margin-bottom: 8px; }
.talent-name { font-weight: 700; font-size: 13px; color: var(--text-primary); margin-bottom: 5px; }
.talent-desc { font-size: 11px; color: var(--text-muted); line-height: 1.4; }

.wizard-actions { display: flex; justify-content: space-between; gap: 15px; margin-top: 20px; }

.rpg-preview-stat-bar { background: var(--bg-card); border-radius: 10px; height: 8px; width: 100%; overflow: hidden; margin-top: 4px; }
.rpg-preview-stat-fill { height: 100%; background: linear-gradient(90deg, var(--accent-indigo), var(--accent-purple)); border-radius: 10px; }
.rpg-preview-stat-row { margin-bottom: 12px; text-align: left; }
</style>

<div class="wizard-container">
    <div class="wizard-header">
        <h1>Forja tu Leyenda</h1>
        <p>El camino de un nuevo personaje comienza aquí.</p>
    </div>

    <!-- Barra de progreso -->
    <div class="wizard-progress">
        <div class="wizard-step-marker active" id="marker-1">1<div class="wizard-step-label">Identidad</div></div>
        <div class="wizard-step-marker" id="marker-2">2<div class="wizard-step-label">Factor Linaje</div></div>
        <div class="wizard-step-marker" id="marker-3">3<div class="wizard-step-label">Expediente</div></div>
    </div>

    <!-- PASO 1: IDENTIDAD -->
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

                <!-- RAZA Y LOGICA DE HIBRIDO -->
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
            <div></div> <!-- Spacer -->
            <button type="button" class="rpg-pj-btn-primary" style="border:none; padding:12px 32px; border-radius: var(--radius-md); cursor:pointer; background: linear-gradient(135deg, var(--accent-indigo), var(--accent-purple)); color: #fff; font-family: var(--font-heading); font-weight:700;" onclick="goToStep(2)">Siguiente: Factor Linaje <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>

    <!-- PASO 2: FACTOR LINAJE -->
    <div id="step-2" class="wizard-step-content" style="display:none;">
        <div class="wizard-section">
            <h2 class="wizard-section-title"><i class="fas fa-chess-knight"></i> Arquetipo de Combate</h2>
            <p style="color:var(--text-muted); font-size:13px; margin-bottom:15px;">Selecciona tu estilo de lucha inicial. Esto definirá tus talentos iniciales.</p>
            <div class="arq-grid">
                <div class="arq-box" onclick="selectArq('Luchador', this)">
                    <div class="arq-icon"><i class="fas fa-fist-raised"></i></div>
                    <div class="arq-name">Luchador (Cuerpo a Cuerpo)</div>
                </div>
                <div class="arq-box" onclick="selectArq('Espadachin', this)">
                    <div class="arq-icon"><i class="fas fa-khanda"></i></div>
                    <div class="arq-name">Espadachín (Armas Blancas)</div>
                </div>
                <div class="arq-box" onclick="selectArq('Tirador', this)">
                    <div class="arq-icon"><i class="fas fa-crosshairs"></i></div>
                    <div class="arq-name">Tirador (Armas de Fuego)</div>
                </div>
                <div class="arq-box" onclick="selectArq('Soporte', this)">
                    <div class="arq-icon"><i class="fas fa-hand-sparkles"></i></div>
                    <div class="arq-name">Soporte (Médico/Táctico)</div>
                </div>
            </div>
            <input type="hidden" id="pj_arquetipo" value="">
        </div>

        <div class="wizard-section" id="section_talentos" style="display:none;">
            <h2 class="wizard-section-title"><i class="fas fa-project-diagram"></i> Primer Talento Pasivo</h2>
            <p style="color:var(--text-muted); font-size:13px; margin-bottom:15px;">Como <span id="arq_label" style="font-weight:bold;color:var(--accent-purple);"></span>, puedes elegir uno de estos talentos innatos.</p>
            <div class="talent-grid" id="talent_container">
                <!-- Se rellena por JS -->
            </div>
            <input type="hidden" id="pj_talento" value="">
        </div>

        <div class="wizard-grid">
            <div class="wizard-section" style="margin-bottom:0;">
                <h2 class="wizard-section-title"><i class="fas fa-sliders-h"></i> Atributos Base</h2>
                <div class="stat-distributor">
                    <div class="stat-points-left">Puntos Libres: <span id="pts_left">20</span></div>
                    
                    <div class="stat-row">
                        <div class="stat-name">Fuerza Física</div>
                        <div class="stat-controls">
                            <button type="button" class="stat-btn" onclick="modStat('str', -1)">-</button>
                            <div class="stat-value" id="val_str">0</div>
                            <button type="button" class="stat-btn" onclick="modStat('str', 1)">+</button>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-name">Agilidad</div>
                        <div class="stat-controls">
                            <button type="button" class="stat-btn" onclick="modStat('agi', -1)">-</button>
                            <div class="stat-value" id="val_agi">0</div>
                            <button type="button" class="stat-btn" onclick="modStat('agi', 1)">+</button>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-name">Resistencia</div>
                        <div class="stat-controls">
                            <button type="button" class="stat-btn" onclick="modStat('res', -1)">-</button>
                            <div class="stat-value" id="val_res">0</div>
                            <button type="button" class="stat-btn" onclick="modStat('res', 1)">+</button>
                        </div>
                    </div>
                    <div class="stat-row">
                        <div class="stat-name">Voluntad</div>
                        <div class="stat-controls">
                            <button type="button" class="stat-btn" onclick="modStat('vol', -1)">-</button>
                            <div class="stat-value" id="val_vol">0</div>
                            <button type="button" class="stat-btn" onclick="modStat('vol', 1)">+</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wizard-section" style="margin-bottom:0;">
                <h2 class="wizard-section-title"><i class="fas fa-tools"></i> Oficio de Mar</h2>
                <p style="color:var(--text-muted); font-size:13px; margin-bottom:20px;">Toda tripulación necesita especialistas. ¿Cuál es el tuyo?</p>
                <div class="form-group">
                    <select id="pj_job" class="textbox" style="height:50px; font-size:16px;">
                        <option value="Ninguno" selected>Ninguno / Aprendiz</option>
                        <option value="Médico">Médico (Medicina y curación)</option>
                        <option value="Navegante">Navegante (Cartografía y clima)</option>
                        <option value="Cocinero">Cocinero (Nutrición y energía)</option>
                        <option value="Carpintero">Carpintero (Mantenimiento de barcos)</option>
                        <option value="Erudito">Erudito (Historia y arqueología)</option>
                        <option value="Músico">Músico (Moral y buffs)</option>
                        <option value="Timonel">Timonel (Pilotaje evasivo)</option>
                    </select>
                </div>
                <div style="text-align:center; margin-top: 40px; opacity:0.5;">
                    <i class="fas fa-ship" style="font-size:64px; color:var(--text-muted);"></i>
                </div>
            </div>
        </div>

        <div class="wizard-actions">
            <button type="button" class="rpg-pj-btn-secondary" style="border:none; padding:12px 24px; border-radius: var(--radius-md); cursor:pointer; background: var(--bg-card); color: var(--text-primary); font-family: var(--font-heading); font-weight:700;" onclick="goToStep(1)"><i class="fas fa-arrow-left"></i> Volver a Identidad</button>
            <button type="button" class="rpg-pj-btn-primary" style="border:none; padding:12px 32px; border-radius: var(--radius-md); cursor:pointer; background: linear-gradient(135deg, var(--accent-indigo), var(--accent-purple)); color: #fff; font-family: var(--font-heading); font-weight:700;" onclick="goToStep(3)">Generar Expediente <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>

    <!-- PASO 3: PREVIEW -->
    <div id="step-3" class="wizard-step-content" style="display:none;">
        <div class="wizard-section" style="padding: 0; display: flex; overflow:hidden; min-height: 600px;">
            <!-- Left Side: Card + RPG Stats -->
            <div style="width: 320px; background: var(--bg-main); border-right: 1px solid var(--border-color); display:flex; flex-direction:column; position:relative; overflow-y:auto;">
                <div id="preview_avatar" style="width:100%; height:450px; min-height:450px; background-size: cover; background-position: center; background-image: url('https://placehold.co/320x450');"></div>
                
                <div style="padding: 20px;">
                    <h2 id="preview_name" style="font-family:var(--font-heading); font-size:22px; color:var(--text-primary); margin-bottom:10px; text-align:center;">Nombre</h2>
                    <div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap; margin-bottom: 15px;">
                        <span id="preview_faction" style="background:rgba(99,102,241,0.1); color:var(--accent-indigo); padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-flag"></i> Facción</span>
                        <span id="preview_rank" style="background:rgba(168,85,247,0.1); color:var(--accent-purple); padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-medal"></i> Rango</span>
                    </div>
                    
                    <!-- Ficha Rolera -->
                    <div style="background: var(--bg-card); border-radius: var(--radius-md); padding: 15px; border: 1px solid var(--border-color); margin-bottom: 15px;">
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom: 10px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">
                            <i id="preview_arq_icon" class="fas fa-fist-raised" style="color:var(--text-secondary); font-size:20px;"></i>
                            <div>
                                <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Arquetipo</div>
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
                            <i class="fas fa-star" style="color:var(--accent-purple); font-size:20px;"></i>
                            <div>
                                <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Talento Principal</div>
                                <div id="preview_talent" style="font-weight:700; color:var(--accent-purple); font-size:13px; line-height:1.2;">Ninguno</div>
                            </div>
                        </div>
                    </div>

                    <!-- Atributos Barras -->
                    <h3 style="font-size:12px; font-family:var(--font-heading); color:var(--text-muted); text-transform:uppercase; margin-bottom:10px;">Atributos Base</h3>
                    
                    <div class="rpg-preview-stat-row">
                        <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;">
                            <span>FUERZA</span><span id="pbar_str_txt">0</span>
                        </div>
                        <div class="rpg-preview-stat-bar"><div id="pbar_str" class="rpg-preview-stat-fill" style="width: 0%;"></div></div>
                    </div>
                    <div class="rpg-preview-stat-row">
                        <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;">
                            <span>AGILIDAD</span><span id="pbar_agi_txt">0</span>
                        </div>
                        <div class="rpg-preview-stat-bar"><div id="pbar_agi" class="rpg-preview-stat-fill" style="width: 0%; background: linear-gradient(90deg, #10b981, #059669);"></div></div>
                    </div>
                    <div class="rpg-preview-stat-row">
                        <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;">
                            <span>RESISTENCIA</span><span id="pbar_res_txt">0</span>
                        </div>
                        <div class="rpg-preview-stat-bar"><div id="pbar_res" class="rpg-preview-stat-fill" style="width: 0%; background: linear-gradient(90deg, #f59e0b, #d97706);"></div></div>
                    </div>
                    <div class="rpg-preview-stat-row">
                        <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;">
                            <span>VOLUNTAD</span><span id="pbar_vol_txt">0</span>
                        </div>
                        <div class="rpg-preview-stat-bar"><div id="pbar_vol" class="rpg-preview-stat-fill" style="width: 0%; background: linear-gradient(90deg, #ef4444, #dc2626);"></div></div>
                    </div>

                </div>
            </div>
            
            <!-- Right Side: Content -->
            <div style="flex:1; padding: 40px; overflow-y:auto; background:var(--bg-surface);">
                <h2 class="wizard-section-title"><i class="fas fa-file-alt"></i> Datos Biográficos</h2>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom: 25px; background:var(--bg-main); padding:15px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
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
            <button type="button" class="rpg-pj-btn-secondary" style="border:none; padding:12px 24px; border-radius: var(--radius-md); cursor:pointer; background: var(--bg-card); color: var(--text-primary); font-family: var(--font-heading); font-weight:700;" onclick="goToStep(2)"><i class="fas fa-arrow-left"></i> Volver a Factor Linaje</button>
            <button type="button" class="rpg-pj-btn-primary" style="border:none; padding:12px 32px; border-radius: var(--radius-md); cursor:pointer; background: linear-gradient(135deg, #10b981, #059669); color: #fff; font-family: var(--font-heading); font-weight:700;" onclick="guardarPersonaje()"><i class="fas fa-check"></i> Aceptar y Crear</button>
        </div>
    </div>
</div>

<script>
// --- LOGICA PASO 1 ---
const facciones = {
    'Revolucionario': 'Iniciado', 'Marine': 'Raso', 'Gobierno': 'Agente',
    'Neomarine': 'Soldado', 'Civil': 'Ciudadano', 'Pirata': 'Grumete'
};

document.getElementById('pj_faction').addEventListener('change', function(e) {
    document.getElementById('pj_rank').value = facciones[e.target.value] || '';
});

function checkHibrido() {
    const raceSelect = document.getElementById('pj_race');
    const hibBox = document.getElementById('hibrido_options');
    if (raceSelect.value === 'Hibrido') {
        hibBox.style.display = 'block';
    } else {
        hibBox.style.display = 'none';
        document.getElementById('pj_race_dom').value = "";
        document.getElementById('pj_race_rec').value = "";
    }
}

// --- LOGICA PASO 2 ---
const talentos = {
    'Luchador': [
        { id:'l1', icon:'fa-hand-rock', name:'Cuerpo de Hierro', desc:'Reduce el daño recibido en ataques directos un 5%.' },
        { id:'l2', icon:'fa-running', name:'Adrenalina Pura', desc:'Aumenta tu velocidad de movimiento en combate cerrado.' },
        { id:'l3', icon:'fa-dumbbell', name:'Fuerza Bruta', desc:'Tus golpes desarmados tienen un alto índice de rotura de armadura.' }
    ],
    'Espadachin': [
        { id:'e1', icon:'fa-bolt', name:'Corte Relámpago', desc:'Tu primer ataque en combate tiene iniciativa prioritaria.' },
        { id:'e2', icon:'fa-shield-alt', name:'Parada Perfecta', desc:'Permite desviar proyectiles básicos con la hoja.' },
        { id:'e3', icon:'fa-tint-slash', name:'Sed de Sangre', desc:'Cada enemigo caído aumenta ligeramente tu letalidad.' }
    ],
    'Tirador': [
        { id:'t1', icon:'fa-eye', name:'Ojo de Halcón', desc:'Aumenta masivamente el alcance visual en mapas abiertos.' },
        { id:'t2', icon:'fa-wind', name:'Balística de Viento', desc:'Tus disparos no se ven afectados por el clima extremo.' },
        { id:'t3', icon:'fa-shoe-prints', name:'Posicionamiento', desc:'Bonificación de evasión si disparas desde zonas elevadas.' }
    ],
    'Soporte': [
        { id:'s1', icon:'fa-heartbeat', name:'Primeros Auxilios', desc:'Los objetos curativos aplicados por ti sanan un 20% más.' },
        { id:'s2', icon:'fa-brain', name:'Táctica Pasiva', desc:'Los aliados cercanos reciben un bono pasivo a su voluntad.' },
        { id:'s3', icon:'fa-vial', name:'Alquimia Básica', desc:'Puedes improvisar pociones débiles con recursos del entorno.' }
    ]
};

const arqIcons = {
    'Luchador': 'fa-fist-raised', 'Espadachin': 'fa-khanda',
    'Tirador': 'fa-crosshairs', 'Soporte': 'fa-hand-sparkles'
};

function selectArq(arq, element) {
    document.querySelectorAll('.arq-box').forEach(b => b.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('pj_arquetipo').value = arq;
    document.getElementById('pj_talento').value = ""; // reset talento
    
    // Rellenar talentos
    const tBox = document.getElementById('section_talentos');
    const tContainer = document.getElementById('talent_container');
    document.getElementById('arq_label').textContent = arq;
    
    tContainer.innerHTML = '';
    talentos[arq].forEach(t => {
        tContainer.innerHTML += `
            <div class="talent-box" onclick="selectTalent('${t.name}', this)">
                <div class="talent-icon"><i class="fas ${t.icon}"></i></div>
                <div class="talent-name">${t.name}</div>
                <div class="talent-desc">${t.desc}</div>
            </div>
        `;
    });
    tBox.style.display = 'block';
}

function selectTalent(talentName, element) {
    document.querySelectorAll('.talent-box').forEach(b => b.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('pj_talento').value = talentName;
}

// Stats distributor
let ptsMax = 20;
let stats = { str:0, agi:0, res:0, vol:0 };

function getPtsUsed() { return stats.str + stats.agi + stats.res + stats.vol; }

function modStat(stat, val) {
    if (val > 0 && getPtsUsed() >= ptsMax) return; // Limite maximo global
    if (val < 0 && stats[stat] <= 0) return; // Limite minimo individual
    if (stats[stat] + val > 10) return; // Maximo 10 por stat inicialmente
    
    stats[stat] += val;
    document.getElementById('val_' + stat).textContent = stats[stat];
    document.getElementById('pts_left').textContent = (ptsMax - getPtsUsed());
}


// --- NAVEGACION ---
let pjData = {};

function goToStep(step) {
    // Validaciones de salida
    if (step === 2) {
        if (!document.getElementById('pj_name').value.trim() || !document.getElementById('pj_faction').value || !document.getElementById('pj_race').value) {
            alert("Nombre, Facción y Raza son campos obligatorios."); return;
        }
        if (document.getElementById('pj_race').value === 'Hibrido') {
            if (!document.getElementById('pj_race_dom').value || !document.getElementById('pj_race_rec').value) {
                alert("Si eres híbrido debes seleccionar raza dominante y recesiva."); return;
            }
        }
    }
    if (step === 3) {
        if (!document.getElementById('pj_arquetipo').value) {
            alert("Debes seleccionar un Arquetipo de Combate."); return;
        }
        if (!document.getElementById('pj_talento').value) {
            alert("Debes seleccionar un Talento Inicial."); return;
        }
        if (getPtsUsed() < ptsMax) {
            if (!confirm(`Aún tienes ${ptsMax - getPtsUsed()} puntos libres sin gastar. ¿Seguro que quieres continuar?`)) return;
        }
        generarPreviewJSON();
    }

    // Cambiar DOM
    document.querySelectorAll('.wizard-step-content').forEach(el => el.style.display = 'none');
    document.getElementById('step-' + step).style.display = 'block';
    
    document.querySelectorAll('.wizard-step-marker').forEach((el, index) => {
        el.classList.remove('active', 'completed');
        if (index + 1 < step) el.classList.add('completed');
        if (index + 1 === step) el.classList.add('active');
    });
    
    window.scrollTo(0, 0);
}

function generarPreviewJSON() {
    let raceFinal = document.getElementById('pj_race').value;
    if (raceFinal === 'Hibrido') {
        raceFinal = `Híbrido (${document.getElementById('pj_race_dom').value} / ${document.getElementById('pj_race_rec').value})`;
    }

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
        talento: document.getElementById('pj_talento').value,
        job: document.getElementById('pj_job').value,
        stats: stats
    };

    // DOM INJECT PREVIEW
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

    // RPG Stats Inject
    document.getElementById('preview_arq_name').textContent = pjData.arquetipo;
    document.getElementById('preview_arq_icon').className = "fas " + arqIcons[pjData.arquetipo];
    document.getElementById('preview_job').textContent = pjData.job;
    document.getElementById('preview_talent').textContent = pjData.talento;

    // Bar multipliers (max 10 points for initial) -> 1 point = 10%
    document.getElementById('pbar_str_txt').textContent = stats.str;
    document.getElementById('pbar_str').style.width = (stats.str * 10) + "%";
    
    document.getElementById('pbar_agi_txt').textContent = stats.agi;
    document.getElementById('pbar_agi').style.width = (stats.agi * 10) + "%";
    
    document.getElementById('pbar_res_txt').textContent = stats.res;
    document.getElementById('pbar_res').style.width = (stats.res * 10) + "%";
    
    document.getElementById('pbar_vol_txt').textContent = stats.vol;
    document.getElementById('pbar_vol').style.width = (stats.vol * 10) + "%";
}

function guardarPersonaje() {
    console.log("JSON FINAL DEL PERSONAJE:", JSON.stringify(pjData));
    alert("¡Personaje configurado y listo!\n(Modo Simulación: Abre la consola F12 para ver todo el JSON recolectado con atributos RPG incluidos).\n\n" + JSON.stringify(pjData, null, 2));
}
</script>

<?php
$content = ob_get_clean();
game_render_page('Crear Personaje', $content);
