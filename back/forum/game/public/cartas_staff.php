<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if (!$uid) {
    header('Location: ../../member.php?action=login');
    exit;
}

$prefix = TABLE_PREFIX;
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;

if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level, name FROM {$prefix}game_personajes WHERE id = {$active_pj_id} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    $staff_level = $pj ? (int)$pj['staff_level'] : 0;
    $pj_name = $pj ? $pj['name'] : '';
}

if ($staff_level < 3) {
    header('Location: ../index.php');
    exit;
}

$b_url = $mybb->settings['bburl'];

ob_start();
?>
<div class="rpg-staff-zone">
    <div class="rpg-staff-header" style="background: linear-gradient(135deg, var(--accent-amber), var(--accent-rose));">
        <div class="rpg-staff-header-content">
            <a href="zona_staff.php" style="color: #fff; text-decoration: none; font-size: 0.9em; opacity: 0.8;"><i class="fas fa-arrow-left"></i> Volver a Zona Staff</a>
            <h1 style="margin-top: 10px;"><i class="fas fa-layer-group"></i> Gestión de Cartas</h1>
            <p>Sistema de creación, edición y asignación de cartas.</p>
        </div>
    </div>

    <div class="rpg-staff-grid" style="grid-template-columns: 1fr;">
        <div class="rpg-staff-section">
            <div style="display: flex; gap: 20px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                <button class="rpg-tab-btn active" data-target="tab-catalog" style="background: transparent; border: none; color: var(--text-primary); font-family: var(--font-heading); font-size: 16px; cursor: pointer; border-bottom: 2px solid var(--accent-indigo);">Catálogo</button>
                <button class="rpg-tab-btn" data-target="tab-editor" style="background: transparent; border: none; color: var(--text-muted); font-family: var(--font-heading); font-size: 16px; cursor: pointer;">Editor de Cartas</button>
                <button class="rpg-tab-btn" data-target="tab-assign" style="background: transparent; border: none; color: var(--text-muted); font-family: var(--font-heading); font-size: 16px; cursor: pointer;">Asignación</button>
            </div>

            <!-- TAB: CATÁLOGO -->
            <div id="tab-catalog" class="rpg-tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3><i class="fas fa-list"></i> Catálogo de Cartas</h3>
                    <button id="btn-new-card" class="rpg-action-btn rpg-btn-primary" style="padding: 8px 16px; font-size: 14px;"><i class="fas fa-plus"></i> Nueva Carta</button>
                </div>
                <div id="catalog-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    <div style="text-align: center; padding: 40px; color: var(--text-muted); grid-column: 1 / -1;">Cargando catálogo...</div>
                </div>
            </div>

            <!-- TAB: EDITOR -->
            <div id="tab-editor" class="rpg-tab-content" style="display: none;">
                <h3 id="editor-title"><i class="fas fa-edit"></i> Crear Nueva Carta</h3>
                <form id="card-editor-form" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <input type="hidden" id="card_id" value="">

                    <!-- Selector de Carta Existente para Cargar -->
                    <div style="grid-column: 1 / -1; background: var(--bg-surface); padding: 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                        <label class="rpg-form-label" style="margin-bottom: 0; white-space: nowrap; font-weight: bold; color: var(--accent-indigo);">Editar Carta Existente (Opcional):</label>
                        <select id="editor-card-select" class="textbox" style="flex: 1; min-width: 250px;">
                            <option value="">-- [ Nueva Carta / Limpiar Formulario ] --</option>
                        </select>
                    </div>

                    <!-- FILA 1: Nombre + Tipo -->
                    <div>
                        <label class="rpg-form-label">Nombre</label>
                        <input type="text" id="c_name" class="textbox" required style="width: 100%;">
                    </div>
                    <div>
                        <label class="rpg-form-label">Tipo</label>
                        <select id="c_type" class="textbox" style="width: 100%;">
                            <option value="tecnica">Técnica</option>
                            <option value="equipo">Equipo</option>
                            <option value="akuma_no_mi">Akuma no Mi</option>
                            <option value="haki">Haki</option>
                            <option value="npc_menor">NPC Menor</option>
                            <option value="barco">Barco</option>
                        </select>
                    </div>

                    <!-- FILA 2: Activación + Rango -->
                    <div id="wrapper-activation">
                        <label class="rpg-form-label">Activación</label>
                        <select id="c_activation" class="textbox" style="width: 100%;">
                            <option value="activa">Activa</option>
                            <option value="pasiva">Pasiva</option>
                            <option value="reactiva">Reactiva</option>
                        </select>
                    </div>
                    <div id="wrapper-rank">
                        <label class="rpg-form-label">Rango</label>
                        <select id="c_rank" class="textbox" style="width: 100%;">
                            <option value="C">C (Común)</option>
                            <option value="B">B (Poco común)</option>
                            <option value="A">A (Raro)</option>
                            <option value="S">S (Épico)</option>
                            <option value="SS">SS (Legendario)</option>
                        </select>
                    </div>

                    <!-- FILA 3: Tags (ancho completo) -->
                    <div style="grid-column: 1 / -1;">
                        <label class="rpg-form-label">Tags</label>
                        <div id="tag-selector">
                            <div id="tag-selected" style="display: flex; flex-wrap: wrap; gap: 4px; min-height: 28px; padding: 4px 0;"></div>
                            <div id="tag-dropdown" style="display: none; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-card); max-height: 320px; overflow-y: auto; margin-top: 8px;"></div>
                            <button type="button" id="tag-toggle-btn" class="rpg-action-btn rpg-btn-secondary" style="margin-top: 6px; padding: 4px 12px; font-size: 13px;">Seleccionar Tags</button>
                            <input type="hidden" id="c_tags" value="">
                        </div>
                    </div>

                    <!-- FILA 4: Descripción (ancho completo) -->
                    <div style="grid-column: 1 / -1;">
                        <label class="rpg-form-label">Descripción</label>
                        <textarea id="c_desc" class="textbox" rows="3" style="width: 100%;"></textarea>
                    </div>

                    <!-- FILA 5: Coste PE + Ejecución -->
                    <div id="wrapper-cost">
                        <label class="rpg-form-label">Coste PE</label>
                        <input type="text" id="c_cost" class="textbox" placeholder="3 PE" style="width: 100%;">
                    </div>
                    <div id="wrapper-stat">
                        <label class="rpg-form-label">Ejecución</label>
                        <select id="c_stat" class="textbox" style="width: 100%;">
                            <option value="">—</option>
                            <option value="FUE">FUE (Fuerza)</option>
                            <option value="AGI">AGI (Agilidad)</option>
                            <option value="DES">DES (Destreza)</option>
                            <option value="INST">INST (Instinto)</option>
                            <option value="ESP">ESP (Espíritu)</option>
                            <option value="INT">INT (Inteligencia)</option>
                        </select>
                    </div>

                    <!-- FILA 6: Dados (ancho completo) -->
                    <div id="wrapper-dice" style="grid-column: 1 / -1; border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <label class="rpg-form-label">Dados / Fórmula de daño</label>
                        <div id="dice-builder">
                            <div id="dice-groups"></div>
                            <div style="display: flex; gap: 8px; margin-top: 4px;">
                                <button type="button" id="dice-add-group" class="rpg-action-btn rpg-btn-secondary" style="padding: 2px 10px; font-size: 12px;">+ Añadir dados</button>
                                <button type="button" id="dice-add-arma" class="rpg-action-btn rpg-btn-secondary" style="padding: 2px 10px; font-size: 12px;">+ Añadir [ARMA]</button>
                                <button type="button" id="dice-add-municion" class="rpg-action-btn rpg-btn-secondary" style="padding: 2px 10px; font-size: 12px;">+ Añadir [MUNICION]</button>
                            </div>

                            <div style="display: flex; gap: 12px; margin-top: 8px; flex-wrap: wrap;">
                                <div>
                                    <label style="font-size: 0.8em; color: var(--text-secondary); display: block; margin-bottom: 2px;">Bonus fijo</label>
                                    <input type="number" id="dice-fixed" min="0" value="0" class="textbox" style="width: 70px; padding: 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px;">
                                </div>
                                <div>
                                    <label style="font-size: 0.8em; color: var(--text-secondary); display: block; margin-bottom: 2px;">Stat</label>
                                    <select id="dice-stat" class="textbox" style="width: 90px; padding: 4px 20px 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px; background-position: right 6px top 50% !important; background-size: 8px auto !important;">
                                        <option value="">—</option>
                                        <option value="FUE">FUE</option>
                                        <option value="AGI">AGI</option>
                                        <option value="DES">DES</option>
                                        <option value="INST">INST</option>
                                        <option value="ESP">ESP</option>
                                        <option value="INT">INT</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size: 0.8em; color: var(--text-secondary); display: block; margin-bottom: 2px;">Mult/Div</label>
                                    <input type="text" id="dice-stat-mod" class="textbox" placeholder="Ej: 2.5* o /2" style="width: 100px; padding: 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px;">
                                </div>
                                <div>
                                    <label style="font-size: 0.8em; color: var(--text-secondary); display: block; margin-bottom: 2px;">Sufijo</label>
                                    <input type="text" id="dice-suffix" class="textbox" placeholder="[FUEGO]" style="width: 110px; padding: 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px;">
                                </div>
                                <div style="display: flex; align-items: flex-end;">
                                    <div style="padding: 0 12px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: monospace; font-size: 0.95em; height: 28px; display: flex; align-items: center; box-shadow: var(--shadow-card);">
                                        <span style="font-size: 0.8em; color: var(--text-muted); margin-right: 6px;">→</span>
                                        <span id="dice-preview" style="color: var(--text-primary); font-weight: bold;">—</span>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="c_dice" value="">
                        </div>
                    </div>


                    <!-- FILA 8: Reposo y Duración -->
                    <div id="wrapper-turns" style="grid-column: 1 / -1; border-top: 1px solid var(--border-color); padding-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label class="rpg-form-label">Turnos de Reposo</label>
                            <input type="number" id="c_reposo" min="0" value="0" class="textbox" style="width: 100%;">
                        </div>
                        <div>
                            <label class="rpg-form-label">Duración (Turnos - vacío o 0 = Turno de activación)</label>
                            <input type="number" id="c_duracion" min="0" value="0" class="textbox" style="width: 100%;">
                        </div>
                    </div>

                    <!-- SECCIÓN DINÁMICA DE CAMPOS RPG -->
                    <div id="fields-akuma" style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <div>
                            <label class="rpg-form-label">Tipo de Akuma</label>
                            <select id="akuma_type" class="textbox" style="width: 100%;">
                                <option value="paramecia">Paramecia</option>
                                <option value="logia">Logia</option>
                                <option value="zoan">Zoan</option>
                            </select>
                        </div>
                        <div></div>
                        <div style="grid-column: 1 / -1;">
                            <label class="rpg-form-label">Efectos</label>
                            <textarea id="akuma_efectos" class="textbox" rows="3" style="width: 100%;"></textarea>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label class="rpg-form-label">Limitaciones</label>
                            <textarea id="akuma_limitaciones" class="textbox" rows="3" style="width: 100%;"></textarea>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label class="rpg-form-label">Debilidades</label>
                            <textarea id="akuma_debilidades" class="textbox" rows="3" style="width: 100%;"></textarea>
                        </div>
                    </div>

                    <div id="fields-equipo" style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <div>
                            <label class="rpg-form-label">Tipo de Equipo</label>
                            <select id="equipo_type" class="textbox" style="width: 100%;">
                                <option value="arma">Arma</option>
                                <option value="util">Útil / Consumible</option>
                                <option value="armadura">Armadura</option>
                            </select>
                        </div>
                        <div>
                            <label class="rpg-form-label">Subtipo</label>
                            <select id="equipo_subtipo_select" class="textbox" style="width: 100%; margin-bottom: 8px;"></select>
                            <input type="text" id="equipo_subtipo" class="textbox" style="width: 100%; display: none;" placeholder="Especificar otro subtipo...">
                        </div>
                    </div>

                    <div id="fields-barco" style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <div>
                            <label class="rpg-form-label">Tipo de Barco</label>
                            <select id="barco_type" class="textbox" style="width: 100%;">
                                <option value="navio">Navío</option>
                                <option value="carabela">Carabela</option>
                                <option value="galera">Galera</option>
                                <option value="fragata">Fragata</option>
                                <option value="bergantin">Bergantín</option>
                                <option value="acorazado">Acorazado</option>
                                <option value="submarino">Submarino</option>
                                <option value="balsa">Balsa</option>
                            </select>
                        </div>
                        <div>
                            <label class="rpg-form-label">Tier</label>
                            <input type="number" id="barco_tier" min="1" value="1" class="textbox" style="width: 100%;">
                        </div>
                        <div>
                            <label class="rpg-form-label">Vida</label>
                            <input type="number" id="barco_vida" min="0" value="100" class="textbox" style="width: 100%;">
                        </div>
                        <div>
                            <label class="rpg-form-label">Ataque</label>
                            <input type="number" id="barco_ataque" min="0" value="0" class="textbox" style="width: 100%;">
                        </div>
                        <div>
                            <label class="rpg-form-label">Velocidad</label>
                            <input type="number" id="barco_velocidad" min="0" value="0" class="textbox" style="width: 100%;">
                        </div>
                        <div>
                            <label class="rpg-form-label">Resistencia</label>
                            <input type="number" id="barco_resistencia" min="0" value="0" class="textbox" style="width: 100%;">
                        </div>
                    </div>

                    <div id="fields-npc" style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <div>
                            <label class="rpg-form-label">Subtipo</label>
                            <select id="npc_mascota_type" class="textbox" style="width: 100%;">
                                <option value="npc">NPC</option>
                                <option value="mascota">Mascota</option>
                            </select>
                        </div>
                        <div>
                            <label class="rpg-form-label">Vida</label>
                            <input type="number" id="npc_vida" min="0" value="50" class="textbox" style="width: 100%;">
                        </div>
                        <div id="wrapper-npc-tier">
                            <label class="rpg-form-label">Tier de Mascota</label>
                            <input type="number" id="npc_tier" min="1" value="1" class="textbox" style="width: 100%;">
                        </div>
                        <div></div>
                        <div style="grid-column: 1 / -1;">
                            <label class="rpg-form-label">Acciones</label>
                            <div id="npc-actions-container" style="display:flex; flex-direction:column; gap:8px;"></div>
                            <button type="button" id="btn-npc-add-action" class="rpg-action-btn rpg-btn-secondary" style="padding: 4px 12px; font-size:12px; margin-top:8px;">+ Añadir Acción</button>
                        </div>
                    </div>

                    <div id="fields-haki" style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <div>
                            <label class="rpg-form-label">Tipo de Haki</label>
                            <select id="haki_type" class="textbox" style="width: 100%;">
                                <option value="busoshoku">Busoshoku (Armamiento)</option>
                                <option value="kenbunshoku">Kenbunshoku (Observación)</option>
                                <option value="haoshoku">Haoshoku (Conquistador / Rey)</option>
                            </select>
                        </div>
                        <div>
                            <label class="rpg-form-label">Nivel de Haki</label>
                            <select id="haki_level" class="textbox" style="width: 100%;">
                                <option value="despertado">Despertado</option>
                                <option value="basico">Básico</option>
                                <option value="medio">Medio</option>
                                <option value="avanzado">Avanzado</option>
                                <option value="maestro">Maestro</option>
                            </select>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label class="rpg-form-label">Efecto</label>
                            <textarea id="haki_efecto" class="textbox" rows="3" style="width: 100%;" placeholder="Detalla el efecto de la habilidad de Haki..."></textarea>
                        </div>
                    </div>

                    <!-- FILA 9: Notas + URL Imagen -->
                    <div>
                        <label class="rpg-form-label">Notas</label>
                        <textarea id="c_notes" class="textbox" rows="2" style="width: 100%;"></textarea>
                    </div>
                    <div>
                        <label class="rpg-form-label">URL Imagen</label>
                        <input type="text" id="c_image" class="textbox" placeholder="https://..." style="width: 100%;">
                    </div>

                    <!-- FILA 10: Botones -->
                    <div style="grid-column: 1 / -1; display: flex; gap: 10px; justify-content: flex-end; margin-top: 4px;">
                        <button type="button" id="btn-cancel-edit" class="rpg-action-btn rpg-btn-secondary">Cancelar</button>
                        <button type="submit" class="rpg-action-btn rpg-btn-primary">Guardar Carta</button>
                    </div>
                </form>
            </div>

            <!-- TAB: ASIGNACIÓN -->
            <div id="tab-assign" class="rpg-tab-content" style="display: none;">
                <h3><i class="fas fa-hand-holding-magic"></i> Asignar Carta a Personaje</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">

                    <div style="background: var(--bg-card); padding: 20px; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                        <div class="rpg-form-group" style="position: relative;">
                            <label class="rpg-form-label">Personaje</label>
                            <div class="character-search" data-target-id="assign_char_id">
                                <input type="text" class="char-search-input textbox" placeholder="Escribe el nombre del personaje..." style="width: 100%;" autocomplete="off">
                                <div class="char-search-results" style="display: none; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-card); max-height: 200px; overflow-y: auto; position: absolute; z-index: 100; width: 100%;"></div>
                                <input type="hidden" class="char-search-value" value="">
                            </div>
                        </div>
                        <div class="rpg-form-group">
                            <label class="rpg-form-label">Carta</label>
                            <select id="assign_card_id" class="textbox" style="width: 100%;">
                                <option value="">Cargando cartas...</option>
                            </select>
                        </div>
                        <div class="rpg-form-group">
                            <label class="rpg-form-label">Rango a Asignar</label>
                            <select id="assign_rank" class="textbox" style="width: 100%;">
                                <option value="C">C</option>
                                <option value="B">B</option>
                                <option value="A">A</option>
                                <option value="S">S</option>
                                <option value="SS">SS</option>
                            </select>
                        </div>
                        <button id="btn-assign" class="rpg-action-btn rpg-btn-primary" style="width: 100%;">Asignar Carta</button>
                    </div>

                    <div style="background: var(--bg-card); padding: 20px; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                        <h4 style="margin-top:0;">Deck del Personaje</h4>
                        <div class="rpg-form-group" style="position: relative; margin-bottom: 12px;">
                            <div class="character-search" data-target-id="view_deck_char_id">
                                <input type="text" class="char-search-input textbox" placeholder="Escribe el nombre del personaje..." style="width: 100%;" autocomplete="off">
                                <div class="char-search-results" style="display: none; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-card); max-height: 200px; overflow-y: auto; position: absolute; z-index: 100; width: 100%;"></div>
                                <input type="hidden" class="char-search-value" value="">
                            </div>
                        </div>
                        <button type="button" id="btn-view-deck" class="rpg-action-btn rpg-btn-secondary" style="width: 100%; margin-bottom: 12px;"><i class="fas fa-eye"></i> Ver Deck</button>
                        <ul id="deck-list" style="list-style: none; padding: 0; margin: 0; max-height: 300px; overflow-y: auto;">
                            <!-- Lista de cartas asignadas -->
                        </ul>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
var GAME_AJAX_BASE = '<?= rtrim($b_url, '/') ?>/game/ajax';
function staffPost(endpoint, data) {
    var url = GAME_AJAX_BASE + '/' + String(endpoint).replace(/^\//, '');
    if (window.gamePostJson) {
        return window.gamePostJson(url, data || {});
    }
    var body = data || {};
    if (window.GAME_CSRF) {
        body.my_post_key = window.GAME_CSRF;
    }
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
        credentials: 'same-origin',
        body: JSON.stringify(body)
    }).then(function (r) { return r.json(); });
}

document.addEventListener('DOMContentLoaded', () => {
    // Tab switching
    const tabs = document.querySelectorAll('.rpg-tab-btn');
    const contents = document.querySelectorAll('.rpg-tab-content');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => { t.classList.remove('active'); t.style.borderBottom = 'none'; t.style.color = 'var(--text-muted)'; });
            contents.forEach(c => c.style.display = 'none');
            tab.classList.add('active');
            tab.style.borderBottom = '2px solid var(--accent-indigo)';
            tab.style.color = 'var(--text-primary)';
            document.getElementById(tab.dataset.target).style.display = 'block';
        });
    });

    let allCards = [];

    // ======= LOAD CATALOG =======
    function loadCatalog() {
        fetch('../ajax/cards_list.php')
            .then(r => r.json())
            .then(d => {
                if(d.ok) {
                    allCards = d.data;
                    renderCatalog(d.data);
                    populateCardSelect(d.data);
                    populateEditorCardSelect(d.data);
                }
            });
    }

    function renderCatalog(cards) {
        const list = document.getElementById('catalog-list');
        list.innerHTML = '';
        if(cards.length === 0) {
            list.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--text-muted);">No hay cartas creadas.</div>';
            return;
        }
        cards.forEach(c => {
            const el = document.createElement('div');
            el.style = 'background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 15px; display: flex; flex-direction: column; gap: 10px;';
            const isEquipo = c.card_type === 'equipo';
            const rankLabel = isEquipo ? 'Rareza' : 'Rango';
            const durText = c.duracion > 0 ? ` • Duración: ${c.duracion}t` : '';
            const repText = c.reposo > 0 ? ` • Reposo: ${c.reposo}t` : '';
            el.innerHTML = `
                <div style="display:flex; justify-content: space-between; align-items:flex-start;">
                    <strong style="color: var(--accent-indigo); font-size: 1.1em;">${c.name}</strong>
                    <span style="background: var(--bg-main); padding: 2px 6px; border-radius: 4px; font-size: 0.8em; font-weight: bold;">${rankLabel} ${c.rank}</span>
                </div>
                <div style="font-size: 0.85em; color: var(--text-secondary);">${c.card_type.toUpperCase()}${durText}${repText}</div>
                <div style="font-size: 0.9em; color: var(--text-primary); flex: 1;">${c.description}</div>
                <div style="display: flex; gap: 5px; margin-top: auto; border-top: 1px solid var(--border-color); padding-top: 10px;">
                    <button class="rpg-action-btn rpg-btn-secondary edit-card" data-id="${c.id}" style="padding: 5px 10px; font-size: 12px; flex:1;">Editar</button>
                    <button class="rpg-action-btn rpg-btn-secondary del-card" data-id="${c.id}" style="padding: 5px 10px; font-size: 12px; flex:1; background: rgba(239,68,68,0.1); color: var(--accent-rose); border-color: transparent;">Eliminar</button>
                </div>
            `;
            list.appendChild(el);
        });
        document.querySelectorAll('.edit-card').forEach(btn => btn.addEventListener('click', e => editCard(e.target.dataset.id)));
        document.querySelectorAll('.del-card').forEach(btn => btn.addEventListener('click', e => deleteCard(e.target.dataset.id)));
    }

    function populateCardSelect(cards) {
        const sel = document.getElementById('assign_card_id');
        sel.innerHTML = '<option value="">Selecciona una carta...</option>';
        cards.forEach(c => {
            sel.innerHTML += `<option value="${c.id}">${c.name} (${c.card_type})</option>`;
        });
    }

    function populateEditorCardSelect(cards) {
        const sel = document.getElementById('editor-card-select');
        if (!sel) return;
        sel.innerHTML = '<option value="">-- [ Nueva Carta / Limpiar Formulario ] --</option>';
        cards.forEach(c => {
            sel.innerHTML += `<option value="${c.id}">${c.name} (${c.card_type.toUpperCase()})</option>`;
        });
    }

    // ======= TAG SELECTOR =======
    const TAG_CATEGORIES = [
        { name: 'Activación y temporalidad', tags: ['ACTIVA','PASIVA','REACTIVA','CONTINUA','INSTANTÁNEA','CARGA','CANAL','RETRASADA','ENCADENABLE','UNA VEZ','COOLDOWN X'] },
        { name: 'Alcance y geometría', tags: ['CONTACTO','CUERPO A CUERPO','DISTANCIA CORTA','DISTANCIA MEDIA','DISTANCIA LARGA','AUTOPERSONAL','ALIADOS','ÁREA PEQUEÑA','ÁREA MEDIA','ÁREA GRANDE','LÍNEA','CONO','ANILLO','TRAYECTORIA','TOQUE','GLOBAL'] },
        { name: 'Función de combate', tags: ['OFENSIVA','DEFENSIVA','CONTROL','SOPORTE','MOVILIDAD','CURACIÓN','UTILIDAD','INTERRUPCIÓN','PENETRACIÓN','DESVÍO','ABSORCIÓN','SEÑUELO','ESCUDO'] },
        { name: 'Ejecución', tags: ['EJECUCIÓN: FUE','EJECUCIÓN: AGI','EJECUCIÓN: DES','EJECUCIÓN: INST','EJECUCIÓN: ESP','EJECUCIÓN: INT'] },
        { name: 'Tipo de daño', tags: ['DAÑO FÍSICO','DAÑO CORTANTE','DAÑO CONTUNDENTE','DAÑO PERFORANTE','DAÑO ÍGNEO','DAÑO CRIOGÉNICO','DAÑO ELÉCTRICO','DAÑO TÓXICO','DAÑO EXPLOSIVO','DAÑO INTERNO','DAÑO ESPIRITUAL','DAÑO ESTRUCTURAL','DAÑO OSCURO'] },
        { name: 'Interacción especial', tags: ['ANTI-LOGIA','ANTI-HAKI','KAIROSEKI','IGNORA ARMADURA','DOBLE DAÑO EMPAPADO','VULNERABILIDAD AGUA','ESCALA CON DAÑO RECIBIDO','ESCALA CON PE RESTANTE','ESCALA CON ALIADOS','BONUS VS DERRIBADO','BONUS VS ESTADO','ENCADENADO CON','ROMPE CONCENTRACIÓN'] },
        { name: 'Elemento / naturaleza', tags: ['FUEGO','HIELO','RAYO','VENENO','OSCURIDAD','LUZ','VIENTO','TIERRA','AGUA','HUMO','ARENA','VIBRACIÓN','SONIDO','GRAVEDAD','VACÍO'] },
        { name: 'Akuma no Mi', tags: ['LOGIA','PARAMECIA-PRODUCTOR','PARAMECIA-TRANSFORMADOR','PARAMECIA-MANIPULADOR','ZOAN','ZOAN MÍTICO','ZOAN ANTIGUO','DESPERTAR'] },
        { name: 'Haki', tags: ['HAKI ARMAMENTO','HAKI OBSERVACIÓN','HAKI REY','FLUJO AVANZADO','VISIÓN DE FUTURO','EMISIÓN DE REY'] },
        { name: 'Equipo', tags: ['ARMA','ARMA SECUNDARIA','ARMA ARROJADIZA','ARMADURA','ARMADURA PARCIAL','ACCESORIO','CONSUMIBLE','NAVE','KAIROSEKI INTEGRADO','GRADO MEITO','MODIFICABLE'] },
        { name: 'NPC', tags: ['PIRATA','MARINO','REVOLUCIONARIO','CIVIL','AGENTE CIPHER POL','BOUNTY HUNTER','ALIADO TEMPORAL','OBSTÁCULO','JEFE DE ESCENA'] },
        { name: 'Condición y restricción', tags: ['REQUIERE ARMA','REQUIERE AKUMA NO MI','REQUIERE HAKI','REQUIERE ESTADO PROPIO','REQUIERE ESTADO OBJETIVO','SOLO EN AGUA','SOLO EN TIERRA','SOLO FORMA HÍBRIDA','SOLO FORMA BESTIAL','CONSUMO DOBLE EMPAPADO','AUTO-DAÑO'] }
    ];

    const selectedTags = new Set();
    const tagDropdown = document.getElementById('tag-dropdown');
    const tagSelected = document.getElementById('tag-selected');
    const tagToggleBtn = document.getElementById('tag-toggle-btn');
    const cTagsInput = document.getElementById('c_tags');

    TAG_CATEGORIES.forEach(cat => {
        const group = document.createElement('div');
        group.style = 'border-bottom: 1px solid var(--border-color);';
        const header = document.createElement('div');
        header.textContent = cat.name;
        header.style = 'padding: 8px 12px; font-weight: bold; font-size: 0.85em; background: var(--bg-main); cursor: pointer; user-select: none; display: flex; align-items: center; gap: 6px;';
        header.innerHTML = '<span style="font-size: 0.7em; opacity: 0.5;">▸</span> ' + cat.name;
        header.addEventListener('click', () => {
            const body = header.nextElementSibling;
            const arrow = header.querySelector('span');
            body.style.display = body.style.display === 'none' ? 'flex' : 'none';
            arrow.textContent = body.style.display === 'none' ? '▸' : '▾';
        });
        group.appendChild(header);
        const body = document.createElement('div');
        body.style = 'display: none; flex-wrap: wrap; gap: 3px; padding: 6px 12px 10px;';
        cat.tags.forEach(tag => {
            const label = document.createElement('label');
            label.style = 'display: flex; align-items: center; gap: 3px; padding: 2px 7px; font-size: 0.8em; cursor: pointer; border-radius: 4px; background: var(--bg-card); border: 1px solid var(--border-color);';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.value = tag;
            cb.addEventListener('change', () => {
                if (cb.checked) selectedTags.add(tag);
                else selectedTags.delete(tag);
                updateTagDisplay();
            });
            label.appendChild(cb);
            label.appendChild(document.createTextNode(tag));
            body.appendChild(label);
        });
        group.appendChild(body);
        tagDropdown.appendChild(group);
    });

    tagToggleBtn.addEventListener('click', () => {
        tagDropdown.style.display = tagDropdown.style.display === 'none' ? 'block' : 'none';
    });

    function updateTagDisplay() {
        tagSelected.innerHTML = '';
        selectedTags.forEach(tag => {
            const pill = document.createElement('span');
            pill.style = 'display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; background: var(--accent-indigo); color: #fff;';
            pill.textContent = tag;
            const remove = document.createElement('span');
            remove.textContent = '×';
            remove.style = 'cursor: pointer; margin-left: 2px; font-weight: bold; font-size: 1.1em;';
            remove.addEventListener('click', (e) => {
                e.stopPropagation();
                selectedTags.delete(tag);
                const cbs = tagDropdown.querySelectorAll('input[type="checkbox"]');
                cbs.forEach(cb => { if (cb.value === tag) cb.checked = false; });
                updateTagDisplay();
            });
            pill.appendChild(remove);
            tagSelected.appendChild(pill);
        });
        cTagsInput.value = Array.from(selectedTags).join(', ');
    }

    function setTags(tagsArray) {
        selectedTags.clear();
        const cbs = tagDropdown.querySelectorAll('input[type="checkbox"]');
        cbs.forEach(cb => { cb.checked = false; });
        (tagsArray || []).forEach(tag => {
            selectedTags.add(tag);
            cbs.forEach(cb => { if (cb.value === tag) cb.checked = true; });
        });
        updateTagDisplay();
    }

    function resetTags() {
        setTags([]);
    }

    // ======= DICE BUILDER =======
    function buildDiceFormula() {
        const groups = document.querySelectorAll('#dice-groups > div');
        let parts = [];
        groups.forEach(g => {
            if (g.classList.contains('dice-group')) {
                const qty = parseInt(g.querySelector('.dice-qty').value) || 1;
                const type = g.querySelector('.dice-type').value;
                if (qty > 0) parts.push(qty + type);
            } else if (g.classList.contains('dice-placeholder')) {
                const type = g.querySelector('.placeholder-type').value;
                parts.push(type);
            }
        });
        const fixed = parseInt(document.getElementById('dice-fixed').value) || 0;
        const stat = document.getElementById('dice-stat').value;
        const statMod = document.getElementById('dice-stat-mod').value.trim();
        const suffix = document.getElementById('dice-suffix').value.trim();

        let formula = parts.join('+');
        if (fixed > 0) formula += (formula ? '+' : '') + fixed;
        if (stat) {
            let statPart = stat;
            if (statMod) {
                if (statMod.includes('/')) {
                    const divisor = statMod.replace('/', '').trim();
                    statPart = stat + '/' + divisor;
                } else if (statMod.includes('*')) {
                    const mult = statMod.replace('*', '').trim();
                    statPart = mult + '*' + stat;
                } else {
                    if (!isNaN(parseFloat(statMod))) {
                        statPart = statMod + '*' + stat;
                    } else {
                        statPart = statMod + stat;
                    }
                }
            }
            formula += (formula ? '+' : '') + statPart;
        }
        if (suffix) formula += (formula ? ' ' : '') + suffix;

        document.getElementById('dice-preview').textContent = formula || '—';
        document.getElementById('c_dice').value = formula;
    }

    function addDiceGroup(qty, type) {
        const container = document.getElementById('dice-groups');
        const group = document.createElement('div');
        group.className = 'dice-group';
        group.style = 'display: inline-flex; align-items: center; gap: 6px; margin: 4px 8px 4px 0; padding: 6px 10px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md);';

        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.className = 'dice-qty';
        qtyInput.min = 1;
        qtyInput.max = 100;
        qtyInput.value = qty || 2;
        qtyInput.style = 'width: 60px; padding: 4px 6px !important; height: 28px; font-size: 12px; border-radius: 4px; line-height: 20px; box-shadow: none !important;';
        qtyInput.addEventListener('input', buildDiceFormula);

        const typeSelect = document.createElement('select');
        typeSelect.className = 'dice-type';
        typeSelect.style = 'width: 80px; padding: 4px 20px 4px 8px !important; height: 28px; font-size: 12px; border-radius: 4px; background-position: right 6px top 50% !important; background-size: 8px auto !important; box-shadow: none !important;';
        ['d4', 'd6', 'd8', 'd10', 'd12', 'd20', 'd100'].forEach(d => {
            const opt = document.createElement('option');
            opt.value = d;
            opt.textContent = d;
            if (d === (type || 'd20')) opt.selected = true;
            typeSelect.appendChild(opt);
        });
        typeSelect.addEventListener('change', buildDiceFormula);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = '×';
        removeBtn.title = 'Quitar grupo';
        removeBtn.style = 'background: none; border: none; color: var(--accent-rose); cursor: pointer; font-size: 16px; padding: 0 2px; line-height: 1;';
        removeBtn.addEventListener('click', () => {
            container.removeChild(group);
            buildDiceFormula();
        });

        group.appendChild(qtyInput);
        group.appendChild(typeSelect);
        group.appendChild(removeBtn);
        container.appendChild(group);
        buildDiceFormula();
    }

    function addPlaceholderGroup(type) {
        const container = document.getElementById('dice-groups');
        const group = document.createElement('div');
        group.className = 'dice-placeholder';
        group.style = 'display: inline-flex; align-items: center; gap: 6px; margin: 4px 8px 4px 0; padding: 6px 10px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); font-weight: bold; color: var(--accent-indigo);';

        const textSpan = document.createElement('span');
        textSpan.textContent = type;

        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.className = 'placeholder-type';
        typeInput.value = type;

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = '×';
        removeBtn.title = 'Quitar';
        removeBtn.style = 'background: none; border: none; color: var(--accent-rose); cursor: pointer; font-size: 16px; padding: 0 2px; line-height: 1;';
        removeBtn.addEventListener('click', () => {
            container.removeChild(group);
            buildDiceFormula();
        });

        group.appendChild(textSpan);
        group.appendChild(typeInput);
        group.appendChild(removeBtn);
        container.appendChild(group);
        buildDiceFormula();
    }

    function parseDiceFormula(formula) {
        const container = document.getElementById('dice-groups');
        container.innerHTML = '';
        document.getElementById('dice-fixed').value = '0';
        document.getElementById('dice-stat').value = '';
        document.getElementById('dice-stat-mod').value = '';
        document.getElementById('dice-suffix').value = '';

        if (!formula || formula === '—' || !formula.trim()) {
            addDiceGroup(2, 'd20');
            return;
        }

        // Extract bracketed suffix at the end (e.g. [FUEGO], [AGUA], etc.)
        let suffix = '';
        let formulaNoSuffix = formula.trim();
        const suffixMatch = formula.match(/\[([^\]]+)\]$/);
        if (suffixMatch) {
            suffix = suffixMatch[0]; // e.g. "[FUEGO]"
            formulaNoSuffix = formula.substring(0, formula.length - suffix.length).trim();
        }

        const parts = formulaNoSuffix.split('+');
        let suffixParts = [];

        parts.forEach(part => {
            part = part.trim();
            if (!part) return;
            const diceMatch = part.match(/^(\d+)(d\d+)$/i);
            if (diceMatch) {
                addDiceGroup(parseInt(diceMatch[1]), diceMatch[2]);
                return;
            }
            if (part === '[ARMA]' || part === '[MUNICION]') {
                addPlaceholderGroup(part);
                return;
            }

            // Stat with multiplier or divisor
            const multMatch = part.match(/^([\d.]+)\*(FUE|AGI|DES|INST|ESP|INT)$/i);
            if (multMatch) {
                document.getElementById('dice-stat').value = multMatch[2].toUpperCase();
                document.getElementById('dice-stat-mod').value = multMatch[1] + '*';
                return;
            }
            const divMatch = part.match(/^(FUE|AGI|DES|INST|ESP|INT)\/([\d.]+)$/i);
            if (divMatch) {
                document.getElementById('dice-stat').value = divMatch[1].toUpperCase();
                document.getElementById('dice-stat-mod').value = '/' + divMatch[2];
                return;
            }
            const reverseMultMatch = part.match(/^(FUE|AGI|DES|INST|ESP|INT)\*([\d.]+)$/i);
            if (reverseMultMatch) {
                document.getElementById('dice-stat').value = reverseMultMatch[1].toUpperCase();
                document.getElementById('dice-stat-mod').value = reverseMultMatch[2] + '*';
                return;
            }

            if (['FUE', 'AGI', 'DES', 'INST', 'ESP', 'INT'].includes(part.toUpperCase())) {
                document.getElementById('dice-stat').value = part.toUpperCase();
                return;
            }
            if (/^\d+$/.test(part)) {
                document.getElementById('dice-fixed').value = part;
                return;
            }
            suffixParts.push(part);
        });

        // Add back the extracted suffix tag to suffixParts
        if (suffix) {
            suffixParts.push(suffix);
        }

        if (suffixParts.length > 0) {
            document.getElementById('dice-suffix').value = suffixParts.join(' ');
        }
        buildDiceFormula();
    }

    function resetDiceBuilder() {
        document.getElementById('dice-groups').innerHTML = '';
        addDiceGroup(2, 'd20');
        document.getElementById('dice-fixed').value = '0';
        document.getElementById('dice-stat').value = '';
        document.getElementById('dice-stat-mod').value = '';
        document.getElementById('dice-suffix').value = '';
        buildDiceFormula();
    }

    document.getElementById('dice-add-group').addEventListener('click', () => addDiceGroup(1, 'd6'));
    document.getElementById('dice-add-arma').addEventListener('click', () => addPlaceholderGroup('[ARMA]'));
    document.getElementById('dice-add-municion').addEventListener('click', () => addPlaceholderGroup('[MUNICION]'));
    document.getElementById('dice-fixed').addEventListener('input', buildDiceFormula);
    document.getElementById('dice-stat').addEventListener('change', buildDiceFormula);
    document.getElementById('dice-stat-mod').addEventListener('input', buildDiceFormula);
    document.getElementById('dice-suffix').addEventListener('input', buildDiceFormula);

    // Default dice group
    addDiceGroup(2, 'd20');

    // ======= NPC ACTIONS DYNAMIC LIST =======
    const npcActionsContainer = document.getElementById('npc-actions-container');
    
    function addNpcActionRow(val = '') {
        if (!npcActionsContainer) return;
        const div = document.createElement('div');
        div.className = 'npc-action-row';
        div.style = 'display:flex; gap:8px; align-items:center; margin-bottom: 4px;';
        div.innerHTML = `
            <input type="text" class="textbox npc-action-input" style="flex:1;" value="${val.replace(/"/g, '&quot;')}" placeholder="Ej: Zarpazo (1d8)">
            <button type="button" class="remove-npc-action" style="padding:4px 8px; font-size:11px; background:rgba(239,68,68,0.1); color:var(--accent-rose); border:1px solid transparent; border-radius:4px; cursor:pointer;">Eliminar</button>
        `;
        div.querySelector('.remove-npc-action').addEventListener('click', () => {
            div.remove();
            if (npcActionsContainer.children.length === 0) {
                addNpcActionRow('');
            }
        });
        npcActionsContainer.appendChild(div);
    }

    document.getElementById('btn-npc-add-action').addEventListener('click', () => addNpcActionRow(''));

    function getNpcActions() {
        const inputs = document.querySelectorAll('#npc-actions-container .npc-action-input');
        return Array.from(inputs).map(inp => inp.value.trim()).filter(Boolean);
    }

    function setNpcActions(actions) {
        if (!npcActionsContainer) return;
        npcActionsContainer.innerHTML = '';
        const list = Array.isArray(actions) ? actions : (typeof actions === 'string' ? actions.split('\n') : []);
        const filtered = list.map(a => a.trim()).filter(Boolean);
        if (filtered.length === 0) {
            addNpcActionRow('');
        } else {
            filtered.forEach(act => addNpcActionRow(act));
        }
    }

    // ======= DYNAMIC SUBTIPO OPTIONS =======
    const subOptions = {
        arma: ['Espada', 'Lanza', 'Arco', 'Ballesta', 'Pistola', 'Rifle', 'Hacha', 'Maza', 'Otros'],
        util: ['Botiquín', 'Comida', 'Brújula', 'Munición', 'Kairooseki', 'Herramienta', 'Otros'],
        armadura: ['Peto', 'Escudo', 'Casco', 'Grebas', 'Guanteletes', 'Otros']
    };

    function updateSubtipoOptions(currentVal = '') {
        const eqType = document.getElementById('equipo_type').value;
        const sel = document.getElementById('equipo_subtipo_select');
        const input = document.getElementById('equipo_subtipo');
        if (!sel || !input) return;
        
        const list = subOptions[eqType] || ['Otros'];
        
        sel.innerHTML = '';
        list.forEach(opt => {
            sel.innerHTML += `<option value="${opt.toLowerCase()}">${opt}</option>`;
        });
        
        const lowerList = list.map(x => x.toLowerCase());
        const searchVal = (currentVal || input.value || '').trim().toLowerCase();
        
        if (searchVal && lowerList.includes(searchVal)) {
            sel.value = searchVal;
            input.value = searchVal;
            input.style.display = 'none';
        } else if (searchVal) {
            sel.value = 'otros';
            input.value = currentVal || input.value;
            input.style.display = 'block';
        } else {
            sel.value = lowerList[0];
            input.value = lowerList[0];
            input.style.display = 'none';
        }
    }

    document.getElementById('equipo_subtipo_select').addEventListener('change', (e) => {
        const input = document.getElementById('equipo_subtipo');
        if (e.target.value === 'otros') {
            input.style.display = 'block';
            input.value = '';
            input.focus();
        } else {
            input.style.display = 'none';
            input.value = e.target.value;
        }
    });

    // ======= EDITOR CARD SELECTOR =======
    document.getElementById('editor-card-select').addEventListener('change', (e) => {
        const id = e.target.value;
        if (id) {
            editCard(id);
        } else {
            document.getElementById('card-editor-form').reset();
            document.getElementById('card_id').value = '';
            document.getElementById('editor-title').innerHTML = '<i class="fas fa-plus"></i> Crear Nueva Carta';
            resetTags();
            resetDiceBuilder();
            setNpcActions([]);
            updateSubtipoOptions('');
            updateFieldVisibility();
        }
    });

    // ======= NEW / RESET CARD =======
    document.getElementById('btn-new-card').addEventListener('click', () => {
        document.getElementById('card-editor-form').reset();
        document.getElementById('card_id').value = '';
        document.getElementById('editor-card-select').value = '';
        document.getElementById('editor-title').innerHTML = '<i class="fas fa-plus"></i> Crear Nueva Carta';
        resetTags();
        resetDiceBuilder();
        
        // Reset dynamic fields
        document.getElementById('akuma_efectos').value = '';
        document.getElementById('akuma_limitaciones').value = '';
        document.getElementById('akuma_debilidades').value = '';
        document.getElementById('equipo_subtipo').value = '';
        updateSubtipoOptions('');
        document.getElementById('barco_tier').value = 1;
        document.getElementById('barco_vida').value = 100;
        document.getElementById('barco_ataque').value = 0;
        document.getElementById('barco_velocidad').value = 0;
        document.getElementById('barco_resistencia').value = 0;
        document.getElementById('npc_vida').value = 50;
        document.getElementById('npc_tier').value = 1;
        setNpcActions([]);
        document.getElementById('haki_efecto').value = '';
        
        updateFieldVisibility();
        tabs[1].click();
    });

    document.getElementById('btn-cancel-edit').addEventListener('click', () => {
        tabs[0].click();
    });

    // Visibilidad dinámica de campos RPG
    const typeSelect = document.getElementById('c_type');
    const eqTypeSelect = document.getElementById('equipo_type');
    const npcTypeSelect = document.getElementById('npc_mascota_type');

    function updateFieldVisibility() {
        const type = typeSelect.value;
        
        // Default wrappers
        const wActivation = document.getElementById('wrapper-activation');
        const wRank = document.getElementById('wrapper-rank');
        const wCost = document.getElementById('wrapper-cost');
        const wStat = document.getElementById('wrapper-stat');
        const wDice = document.getElementById('wrapper-dice');
        const wTurns = document.getElementById('wrapper-turns');
        
        // Custom wrappers
        const fAkuma = document.getElementById('fields-akuma');
        const fEquipo = document.getElementById('fields-equipo');
        const fBarco = document.getElementById('fields-barco');
        const fNpc = document.getElementById('fields-npc');
        const fHaki = document.getElementById('fields-haki');
        
        // Reset defaults
        wActivation.style.display = 'block';
        wRank.style.display = (type === 'tecnica' || type === 'equipo' || type === 'barco') ? 'block' : 'none';
        wCost.style.display = 'block';
        wStat.style.display = 'block';
        wDice.style.display = 'block';
        wTurns.style.display = 'grid';
        
        // Hide all custom
        fAkuma.style.display = 'none';
        fEquipo.style.display = 'none';
        fBarco.style.display = 'none';
        fNpc.style.display = 'none';
        fHaki.style.display = 'none';
        
        if (type === 'akuma_no_mi') {
            wActivation.style.display = 'none';
            wCost.style.display = 'none';
            wStat.style.display = 'none';
            wDice.style.display = 'none';
            wTurns.style.display = 'none';
            
            fAkuma.style.display = 'grid';
        } else if (type === 'equipo') {
            wActivation.style.display = 'none';
            wCost.style.display = 'none';
            wTurns.style.display = 'none';
            
            fEquipo.style.display = 'grid';
            
            // Subtype damage fields for weapons
            const eqType = eqTypeSelect.value;
            updateSubtipoOptions();
            if (eqType === 'arma') {
                wDice.style.display = 'block';
                wStat.style.display = 'block';
            } else {
                wDice.style.display = 'none';
                wStat.style.display = 'none';
            }
        } else if (type === 'barco') {
            wActivation.style.display = 'none';
            wCost.style.display = 'none';
            wStat.style.display = 'none';
            wDice.style.display = 'none';
            wTurns.style.display = 'none';
            
            fBarco.style.display = 'grid';
        } else if (type === 'npc_menor') {
            wActivation.style.display = 'none';
            wCost.style.display = 'none';
            wStat.style.display = 'none';
            wDice.style.display = 'none';
            wTurns.style.display = 'none';
            
            fNpc.style.display = 'grid';
            
            // Mascot tier
            const npcType = npcTypeSelect.value;
            const wNpcTier = document.getElementById('wrapper-npc-tier');
            if (npcType === 'mascota') {
                wNpcTier.style.display = 'block';
            } else {
                wNpcTier.style.display = 'none';
            }
        } else if (type === 'haki') {
            wActivation.style.display = 'none';
            wCost.style.display = 'none';
            wStat.style.display = 'none';
            wDice.style.display = 'none';
            wTurns.style.display = 'none';
            
            fHaki.style.display = 'grid';
        }
    }

    typeSelect.addEventListener('change', updateFieldVisibility);
    eqTypeSelect.addEventListener('change', () => {
        updateSubtipoOptions();
        updateFieldVisibility();
    });
    npcTypeSelect.addEventListener('change', updateFieldVisibility);
    
    // Init state
    updateFieldVisibility();

    // ======= EDIT CARD =======
    function editCard(id) {
        const card = allCards.find(c => c.id == id);
        if(!card) return;

        document.getElementById('card_id').value = card.id;
        const selectEl = document.getElementById('editor-card-select');
        if (selectEl) selectEl.value = card.id;
        
        document.getElementById('c_name').value = card.name;
        document.getElementById('c_type').value = card.card_type;
        document.getElementById('c_rank').value = card.rank;
        document.getElementById('c_activation').value = card.activation;
        setTags(card.tags || []);
        document.getElementById('c_desc').value = card.description;
        document.getElementById('c_cost').value = card.cost_pe;
        document.getElementById('c_stat').value = card.execution_stat || '';
        
        // Migrate legacy weapon fields dynamically into the dice formula
        const effects = card.effects || {};
        let diceFormula = card.dice || '';
        if (card.card_type === 'equipo' && !diceFormula && effects.damage_dice) {
            diceFormula = effects.damage_dice + (effects.damage_stat ? `+${effects.damage_stat}` : '');
        }
        parseDiceFormula(diceFormula);

        document.getElementById('c_reposo').value = card.reposo || 0;
        document.getElementById('c_duracion').value = card.duracion || 0;
        document.getElementById('c_notes').value = card.notes;
        document.getElementById('c_image').value = card.image_url;

        // Cargar efectos estructurados dinámicos
        document.getElementById('akuma_type').value = effects.akuma_type || 'paramecia';
        document.getElementById('akuma_efectos').value = effects.efectos || '';
        document.getElementById('akuma_limitaciones').value = effects.limitaciones || '';
        document.getElementById('akuma_debilidades').value = effects.debilidades || '';

        document.getElementById('equipo_type').value = effects.equipo_type || 'util';
        updateSubtipoOptions(effects.subtipo || '');

        document.getElementById('barco_type').value = effects.barco_type || 'navio';
        document.getElementById('barco_tier').value = effects.tier || 1;
        document.getElementById('barco_vida').value = effects.vida || 100;
        document.getElementById('barco_ataque').value = effects.ataque || 0;
        document.getElementById('barco_velocidad').value = effects.velocidad || 0;
        document.getElementById('barco_resistencia').value = effects.resistencia || 0;

        document.getElementById('npc_mascota_type').value = effects.npc_mascota_type || 'npc';
        document.getElementById('npc_vida').value = effects.vida || 50;
        document.getElementById('npc_tier').value = effects.tier || 1;
        setNpcActions(effects.acciones || []);

        let hType = effects.haki_type || 'busoshoku';
        if (hType === 'busshoku') hType = 'busoshoku';
        if (hType === 'kenboshuko') hType = 'kenbunshoku';
        document.getElementById('haki_type').value = hType;
        document.getElementById('haki_level').value = effects.haki_level || 'basico';
        document.getElementById('haki_efecto').value = effects.efecto || '';

        updateFieldVisibility();

        document.getElementById('editor-title').innerHTML = '<i class="fas fa-edit"></i> Editar Carta';
        tabs[1].click();
    }

    // ======= DELETE CARD =======
    function deleteCard(id) {
        if(!confirm('¿Seguro que quieres eliminar esta carta? Se quitará de todos los personajes.')) return;
        staffPost('cards_delete.php', { card_id: parseInt(id, 10) }).then(d => {
            if(d.ok) loadCatalog();
            else alert('Error: ' + ((d.error && d.error.message) ? d.error.message : 'Desconocido'));
        });
    }

    // ======= SUBMIT =======
    document.getElementById('card-editor-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const id = document.getElementById('card_id').value;
        const payload = {
            name: document.getElementById('c_name').value,
            card_type: document.getElementById('c_type').value,
            rank: document.getElementById('c_rank').value,
            activation: document.getElementById('c_activation').value,
            tags: document.getElementById('c_tags').value.split(',').map(t=>t.trim()).filter(t=>t),
            description: document.getElementById('c_desc').value,
            cost_pe: document.getElementById('c_cost').value,
            execution_stat: document.getElementById('c_stat').value,
            dice: document.getElementById('c_dice').value,
            notes: document.getElementById('c_notes').value,
            image_url: document.getElementById('c_image').value,
        };
        
        const type = document.getElementById('c_type').value;
        payload.effects = {};
        
        if (type === 'akuma_no_mi') {
            payload.effects = {
                akuma_type: document.getElementById('akuma_type').value,
                efectos: document.getElementById('akuma_efectos').value,
                limitaciones: document.getElementById('akuma_limitaciones').value,
                debilidades: document.getElementById('akuma_debilidades').value
            };
        } else if (type === 'equipo') {
            const eqType = document.getElementById('equipo_type').value;
            payload.effects = {
                equipo_type: eqType,
                subtipo: document.getElementById('equipo_subtipo').value,
                damage_dice: '',
                damage_stat: ''
            };
        } else if (type === 'barco') {
            payload.effects = {
                barco_type: document.getElementById('barco_type').value,
                tier: parseInt(document.getElementById('barco_tier').value) || 1,
                vida: parseInt(document.getElementById('barco_vida').value) || 0,
                ataque: parseInt(document.getElementById('barco_ataque').value) || 0,
                velocidad: parseInt(document.getElementById('barco_velocidad').value) || 0,
                resistencia: parseInt(document.getElementById('barco_resistencia').value) || 0
            };
        } else if (type === 'npc_menor') {
            const subType = document.getElementById('npc_mascota_type').value;
            const actionsList = getNpcActions();
            payload.effects = {
                npc_mascota_type: subType,
                vida: parseInt(document.getElementById('npc_vida').value) || 0,
                tier: subType === 'mascota' ? (parseInt(document.getElementById('npc_tier').value) || 1) : 1,
                acciones: actionsList
            };
        } else if (type === 'haki') {
            payload.effects = {
                haki_type: document.getElementById('haki_type').value,
                haki_level: document.getElementById('haki_level').value,
                efecto: document.getElementById('haki_efecto').value
            };
        }

        payload.reposo = parseInt(document.getElementById('c_reposo').value) || 0;
        payload.duracion = parseInt(document.getElementById('c_duracion').value) || 0;
        payload.upgrade = {};

        if (id) {
            payload.card_id = parseInt(id);
            staffPost('cards_update.php', payload)
            .then(d => {
                if (!d || d.ok === false) throw new Error((d && d.error && d.error.message) ? d.error.message : 'Error');
                return d;
            })
            .then(d => {
                if (d.ok) {
                    loadCatalog();
                    tabs[0].click();
                } else {
                    alert('Error al actualizar la carta: ' + ((d.error && d.error.message) ? d.error.message : 'Error desconocido'));
                }
            })
            .catch(err => {
                alert('Error de conexión o de servidor: ' + err.message + '\n(Asegúrate de haber corrido las migraciones de base de datos)');
            });
        } else {
            staffPost('cards_create.php', payload)
            .then(d => {
                if (!d || d.ok === false) throw new Error((d && d.error && d.error.message) ? d.error.message : 'Error');
                return d;
            })
            .then(d => {
                if (d.ok) {
                    loadCatalog();
                    tabs[0].click();
                } else {
                    alert('Error al crear la carta: ' + ((d.error && d.error.message) ? d.error.message : 'Error desconocido'));
                }
            })
            .catch(err => {
                alert('Error de conexión o de servidor: ' + err.message + '\n(Asegúrate de haber corrido las migraciones de base de datos)');
            });
        }
    });

    // ======= CHARACTER SEARCH AUTOCOMPLETE =======
    function initCharSearch(container) {
        const input = container.querySelector('.char-search-input');
        const results = container.querySelector('.char-search-results');
        const hidden = container.querySelector('.char-search-value');
        let fetchTimeout = null;

        input.addEventListener('input', () => {
            clearTimeout(fetchTimeout);
            const q = input.value.trim();
            if (q.length < 1) {
                results.style.display = 'none';
                hidden.value = '';
                return;
            }
            fetchTimeout = setTimeout(() => {
                fetch('../ajax/cards_search_characters.php?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(d => {
                        results.innerHTML = '';
                        if (d.ok && d.data.length > 0) {
                            results.style.display = 'block';
                            d.data.forEach(ch => {
                                const item = document.createElement('div');
                                item.textContent = ch.name;
                                item.dataset.id = ch.id;
                                item.style = 'padding: 8px 12px; cursor: pointer; font-size: 0.9em; border-bottom: 1px solid var(--border-color);';
                                item.addEventListener('mouseenter', () => { item.style.background = 'var(--bg-main)'; });
                                item.addEventListener('mouseleave', () => { item.style.background = ''; });
                                item.addEventListener('click', () => {
                                    input.value = ch.name;
                                    hidden.value = ch.id;
                                    results.style.display = 'none';
                                    if (container.dataset.targetId === 'view_deck_char_id') {
                                        loadDeck(ch.id);
                                    }
                                });
                                results.appendChild(item);
                            });
                        } else {
                            results.style.display = 'none';
                        }
                    });
            }, 250);
        });

        input.addEventListener('blur', () => {
            setTimeout(() => { results.style.display = 'none'; }, 200);
        });
        input.addEventListener('focus', () => {
            if (results.children.length > 0) results.style.display = 'block';
        });
    }

    document.querySelectorAll('.character-search').forEach(initCharSearch);

    // ======= ASSIGN =======
    document.getElementById('btn-assign').addEventListener('click', () => {
        const charId = document.querySelector('#tab-assign .character-search[data-target-id="assign_char_id"] .char-search-value').value;
        const cardId = document.getElementById('assign_card_id').value;
        const rank = document.getElementById('assign_rank').value;
        if(!charId || !cardId) return alert('Selecciona un personaje y una carta.');
        staffPost('cards_assign.php', {
            character_id: parseInt(charId, 10),
            card_id: parseInt(cardId, 10),
            rank: rank
        }).then(d => {
            if(d.ok) {
                alert('Carta asignada correctamente.');
                const viewInput = document.querySelector('#tab-assign .character-search[data-target-id="view_deck_char_id"] .char-search-input');
                const viewHidden = document.querySelector('#tab-assign .character-search[data-target-id="view_deck_char_id"] .char-search-value');
                viewInput.value = document.querySelector('#tab-assign .character-search[data-target-id="assign_char_id"] .char-search-input').value;
                viewHidden.value = charId;
                loadDeck(parseInt(charId));
            }
        });
    });

    document.getElementById('btn-view-deck').addEventListener('click', () => {
        const charId = document.querySelector('#tab-assign .character-search[data-target-id="view_deck_char_id"] .char-search-value').value;
        if(charId) loadDeck(parseInt(charId));
    });

    function loadDeck(charId) {
        fetch('../ajax/cards_my_deck.php?character_id=' + charId)
            .then(r=>r.json()).then(d=>{
                const list = document.getElementById('deck-list');
                list.innerHTML = '';
                if(d.ok && d.data.length > 0) {
                    d.data.forEach(c => {
                        const li = document.createElement('li');
                        li.style = 'padding: 10px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;';
                        li.innerHTML = `
                            <div>
                                <strong>${c.name}</strong> <span style="font-size: 0.8em; color: var(--accent-indigo);">[${c.rank}]</span>
                            </div>
                            <button class="rpg-action-btn rpg-btn-secondary unassign-btn" data-cid="${c.id}" style="padding: 4px 8px; font-size: 11px;">Quitar</button>
                        `;
                        list.appendChild(li);
                    });
                    document.querySelectorAll('.unassign-btn').forEach(btn => {
                        btn.addEventListener('click', (e) => {
                            const cardId = e.target.dataset.cid;
                            staffPost('cards_unassign.php', {
                                character_id: parseInt(charId, 10),
                                card_id: parseInt(cardId, 10)
                            }).then(res => {
                                if(res.ok) loadDeck(charId);
                            });
                        });
                    });
                } else {
                    list.innerHTML = '<li style="padding: 10px; color: var(--text-muted);">Sin cartas.</li>';
                }
            });
    }

    loadCatalog();
});
</script>
<?php
$content = ob_get_clean();
game_render_page("Gestión de Cartas", $content);
