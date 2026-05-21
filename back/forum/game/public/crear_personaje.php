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

ob_start();
?>
<style>
/* Estilos específicos para el creador */
.wizard-container { max-width: 1100px; margin: 0 auto; }
.wizard-header { margin-bottom: 30px; text-align: center; }
.wizard-header h1 { font-size: 32px; font-family: var(--font-heading); color: var(--text-primary); margin-bottom: 10px; }
.wizard-header p { color: var(--text-muted); font-size: 15px; }

.wizard-section {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-xl);
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: var(--shadow-card);
}
.wizard-section-title {
    font-family: var(--font-heading);
    font-size: 20px;
    color: var(--accent-indigo);
    border-bottom: 2px solid rgba(99,102,241,0.2);
    padding-bottom: 10px;
    margin-bottom: 24px;
    display: flex; align-items: center; gap: 10px;
}

.wizard-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.wizard-grid-full { grid-column: span 2; }
.form-group { display: flex; flex-direction: column; gap: 8px; }
.form-group label { font-family: var(--font-heading); font-weight: 600; font-size: 13px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }

/* Factor Linaje Visuals */
.fl-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
.fl-box {
    background: var(--bg-main);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
}
.fl-box:hover { border-color: var(--accent-purple); box-shadow: 0 4px 15px rgba(168,85,247,0.15); transform: translateY(-2px); }
.fl-icon { font-size: 28px; color: var(--accent-indigo); margin-bottom: 10px; }
.fl-name { font-weight: 700; font-size: 14px; color: var(--text-primary); margin-bottom: 5px; }
.fl-desc { font-size: 11px; color: var(--text-muted); }

.wizard-actions { display: flex; justify-content: flex-end; gap: 15px; margin-top: 20px; }

/* Preview */
#preview-container { display: none; }
</style>

<div class="wizard-container">
    <div class="wizard-header">
        <h1>Registro de Personaje</h1>
        <p>Completa los datos para forjar el destino de tu nueva creación.</p>
    </div>

    <!-- PANTALLA 1: FORMULARIO -->
    <div id="step-1">
        <div class="wizard-section">
            <h2 class="wizard-section-title"><i class="fas fa-id-card"></i> Datos Básicos</h2>
            <div class="wizard-grid">
                <div class="form-group">
                    <label>Nombre del Personaje</label>
                    <input type="text" id="pj_name" class="textbox" placeholder="Ej. Monkey D. Luffy" required>
                </div>
                <div class="form-group">
                    <label>Enlace del Avatar (290x450 aprox)</label>
                    <input type="url" id="pj_avatar" class="textbox" placeholder="https://i.imgur.com/...">
                </div>

                <div class="form-group">
                    <label>Facción</label>
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
                    <label>Rango Inicial (Automático)</label>
                    <input type="text" id="pj_rank" class="textbox" style="background: var(--bg-main); opacity: 0.8; cursor: not-allowed;" readonly placeholder="Selecciona facción primero">
                </div>

                <div class="form-group">
                    <label>Edad</label>
                    <input type="number" id="pj_age" class="textbox" placeholder="Ej. 19">
                </div>
                <div class="form-group">
                    <label>Raza</label>
                    <input type="text" id="pj_race" class="textbox" placeholder="Ej. Humano, Mink, Gyojin...">
                </div>
                
                <div class="form-group">
                    <label>Isla de Origen</label>
                    <input type="text" id="pj_origin" class="textbox" placeholder="Ej. East Blue, Isla Dawn">
                </div>
                <div class="form-group">
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
                    <textarea id="pj_physique" class="textbox" placeholder="Describe cómo es físicamente..." style="height: 120px;"></textarea>
                </div>
                <div class="form-group wizard-grid-full">
                    <label>Descripción Psicológica</label>
                    <textarea id="pj_psychology" class="textbox" placeholder="Mentalidad, miedos, motivaciones..." style="height: 120px;"></textarea>
                </div>
                <div class="form-group wizard-grid-full">
                    <label>Otros / Extras</label>
                    <textarea id="pj_extras" class="textbox" placeholder="Cicatrices, tatuajes, objetos importantes..." style="height: 80px;"></textarea>
                </div>
            </div>
        </div>

        <div class="wizard-section">
            <h2 class="wizard-section-title"><i class="fas fa-dna"></i> Factor Linaje (Próximamente)</h2>
            <p style="color:var(--text-muted); font-size:13px; margin-bottom:20px;">Esta sección es un prototipo del sistema RPG. Elige la senda genética de tu personaje.</p>
            <div class="fl-grid">
                <div class="fl-box">
                    <div class="fl-icon"><i class="fas fa-fist-raised"></i></div>
                    <div class="fl-name">Luchador Nato</div>
                    <div class="fl-desc">Mejora el combate cuerpo a cuerpo y la resistencia.</div>
                </div>
                <div class="fl-box">
                    <div class="fl-icon"><i class="fas fa-crosshairs"></i></div>
                    <div class="fl-name">Tirador de Élite</div>
                    <div class="fl-desc">Precisión aumentada y vista de halcón.</div>
                </div>
                <div class="fl-box">
                    <div class="fl-icon"><i class="fas fa-book-dead"></i></div>
                    <div class="fl-name">Erudito Antiguo</div>
                    <div class="fl-desc">Conocimiento oculto y ventaja en habilidades mentales.</div>
                </div>
            </div>
        </div>

        <div class="wizard-actions">
            <button type="button" class="rpg-pj-btn-secondary" style="border:none; padding:12px 24px; border-radius: var(--radius-md); cursor:pointer; background: var(--bg-card); color: var(--text-primary); font-family: var(--font-heading); font-weight:700;" onclick="window.location.href='mis_personajes.php'">Cancelar</button>
            <button type="button" class="rpg-pj-btn-primary" style="border:none; padding:12px 32px; border-radius: var(--radius-md); cursor:pointer; background: linear-gradient(135deg, var(--accent-indigo), var(--accent-purple)); color: #fff; font-family: var(--font-heading); font-weight:700;" onclick="generarPreview()"><i class="fas fa-arrow-right"></i> Siguiente: Previsualizar</button>
        </div>
    </div>

    <!-- PANTALLA 2: PREVIEW -->
    <div id="step-2" style="display:none;">
        
        <div class="wizard-section" style="padding: 0; display: flex; overflow:hidden; min-height: 600px;">
            <!-- Left Side: Card -->
            <div style="width: 320px; background: var(--bg-main); border-right: 1px solid var(--border-color); display:flex; flex-direction:column; position:relative;">
                <div id="preview_avatar" style="width:100%; height:450px; background-size: cover; background-position: center; background-image: url('https://placehold.co/320x450');"></div>
                <div style="padding: 24px; text-align:center; flex:1; display:flex; flex-direction:column; justify-content:center;">
                    <h2 id="preview_name" style="font-family:var(--font-heading); font-size:24px; color:var(--text-primary); margin-bottom:10px;">Nombre</h2>
                    <div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap; margin-bottom: 15px;">
                        <span id="preview_faction" style="background:rgba(99,102,241,0.1); color:var(--accent-indigo); padding:4px 12px; border-radius:12px; font-size:12px; font-weight:700;"><i class="fas fa-flag"></i> Facción</span>
                        <span id="preview_rank" style="background:rgba(168,85,247,0.1); color:var(--accent-purple); padding:4px 12px; border-radius:12px; font-size:12px; font-weight:700;"><i class="fas fa-medal"></i> Rango</span>
                    </div>
                    <div style="font-size:13px; color:var(--text-secondary); display:flex; flex-direction:column; gap:6px;">
                        <div><strong>Edad:</strong> <span id="preview_age">--</span></div>
                        <div><strong>Raza:</strong> <span id="preview_race">--</span></div>
                        <div><strong>Origen:</strong> <span id="preview_origin">--</span></div>
                        <div><strong>PB:</strong> <span id="preview_pb">--</span></div>
                    </div>
                </div>
            </div>
            
            <!-- Right Side: Content -->
            <div style="flex:1; padding: 40px; overflow-y:auto;">
                <h2 class="wizard-section-title"><i class="fas fa-address-card"></i> Expediente</h2>
                
                <h3 style="font-family:var(--font-heading); font-size:16px; color:var(--text-primary); margin-bottom:10px; margin-top:20px;">Apariencia Física</h3>
                <div id="preview_physique" style="color:var(--text-secondary); font-size:14px; line-height:1.6; white-space:pre-wrap;"></div>

                <h3 style="font-family:var(--font-heading); font-size:16px; color:var(--text-primary); margin-bottom:10px; margin-top:30px;">Perfil Psicológico</h3>
                <div id="preview_psychology" style="color:var(--text-secondary); font-size:14px; line-height:1.6; white-space:pre-wrap;"></div>

                <h3 style="font-family:var(--font-heading); font-size:16px; color:var(--text-primary); margin-bottom:10px; margin-top:30px;">Extras y Notas</h3>
                <div id="preview_extras" style="color:var(--text-secondary); font-size:14px; line-height:1.6; white-space:pre-wrap;"></div>
            </div>
        </div>

        <div class="wizard-actions">
            <button type="button" class="rpg-pj-btn-secondary" style="border:none; padding:12px 24px; border-radius: var(--radius-md); cursor:pointer; background: var(--bg-card); color: var(--text-primary); font-family: var(--font-heading); font-weight:700;" onclick="volverAtras()"><i class="fas fa-arrow-left"></i> Modificar</button>
            <button type="button" class="rpg-pj-btn-primary" style="border:none; padding:12px 32px; border-radius: var(--radius-md); cursor:pointer; background: linear-gradient(135deg, #10b981, #059669); color: #fff; font-family: var(--font-heading); font-weight:700;" onclick="guardarPersonaje()"><i class="fas fa-check"></i> Aceptar y Crear</button>
        </div>
    </div>
</div>

<script>
// Diccionario de Facción -> Rango
const facciones = {
    'Revolucionario': 'Iniciado',
    'Marine': 'Raso',
    'Gobierno': 'Agente',
    'Neomarine': 'Soldado',
    'Civil': 'Ciudadano',
    'Pirata': 'Grumete'
};

// Evento para actualizar el rango
document.getElementById('pj_faction').addEventListener('change', function(e) {
    const val = e.target.value;
    const rankInput = document.getElementById('pj_rank');
    if (facciones[val]) {
        rankInput.value = facciones[val];
    } else {
        rankInput.value = '';
    }
});

let pjData = {};

function generarPreview() {
    // Recolectar a JSON
    pjData = {
        name: document.getElementById('pj_name').value.trim(),
        avatar: document.getElementById('pj_avatar').value.trim() || 'https://placehold.co/320x450',
        faction: document.getElementById('pj_faction').value,
        rank: document.getElementById('pj_rank').value,
        age: document.getElementById('pj_age').value.trim() || 'Desconocida',
        race: document.getElementById('pj_race').value.trim() || 'Desconocida',
        origin: document.getElementById('pj_origin').value.trim() || 'Desconocido',
        pb: document.getElementById('pj_pb').value.trim() || 'Ninguno',
        physique: document.getElementById('pj_physique').value.trim() || 'Sin registrar.',
        psychology: document.getElementById('pj_psychology').value.trim() || 'Sin registrar.',
        extras: document.getElementById('pj_extras').value.trim() || 'Sin notas.'
    };

    if (!pjData.name) {
        alert("El nombre es obligatorio.");
        document.getElementById('pj_name').focus();
        return;
    }
    if (!pjData.faction) {
        alert("Debes elegir una facción.");
        document.getElementById('pj_faction').focus();
        return;
    }

    // Inyectar en preview
    document.getElementById('preview_name').textContent = pjData.name;
    document.getElementById('preview_avatar').style.backgroundImage = "url('" + pjData.avatar + "')";
    document.getElementById('preview_faction').innerHTML = '<i class="fas fa-flag"></i> ' + pjData.faction;
    document.getElementById('preview_rank').innerHTML = '<i class="fas fa-medal"></i> ' + pjData.rank;
    document.getElementById('preview_age').textContent = pjData.age;
    document.getElementById('preview_race').textContent = pjData.race;
    document.getElementById('preview_origin').textContent = pjData.origin;
    document.getElementById('preview_pb').textContent = pjData.pb;
    document.getElementById('preview_physique').textContent = pjData.physique;
    document.getElementById('preview_psychology').textContent = pjData.psychology;
    document.getElementById('preview_extras').textContent = pjData.extras;

    // Cambiar vista
    document.getElementById('step-1').style.display = 'none';
    document.getElementById('step-2').style.display = 'block';
    window.scrollTo(0, 0);
}

function volverAtras() {
    document.getElementById('step-2').style.display = 'none';
    document.getElementById('step-1').style.display = 'block';
    window.scrollTo(0, 0);
}

function guardarPersonaje() {
    // Por ahora, simulamos el guardado como pidió el usuario.
    console.log("JSON del Personaje a enviar a la DB:", JSON.stringify(pjData));
    alert("Funcionalidad simulada con éxito.\n¡Próximamente tu personaje será guardado en la base de datos!\n\nDatos recogidos:\n" + JSON.stringify(pjData, null, 2));
}
</script>

<?php
$content = ob_get_clean();
game_render_page('Crear Personaje', $content);
