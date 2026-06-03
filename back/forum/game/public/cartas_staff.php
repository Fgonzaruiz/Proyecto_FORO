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
    <div class="rpg-staff-header rpg-staff-header--cartas">
        <div class="rpg-staff-header-content">
            <a href="zona_staff.php" class="rpg-staff-header__back"><i class="fas fa-arrow-left"></i> Volver a Zona Staff</a>
            <h1 class="rpg-staff-header__title"><i class="fas fa-layer-group"></i> Gestión de Cartas</h1>
            <p>Sistema de creación, edición y asignación de cartas.</p>
        </div>
    </div>

    <div class="rpg-staff-grid rpg-staff-grid--single">
        <div class="rpg-staff-section">
            <div class="rpg-staff-tabs">
                <button class="rpg-tab-btn active" data-target="tab-catalog">Catálogo</button>
                <button class="rpg-tab-btn" data-target="tab-assign">Asignación</button>
            </div>

            <!-- TAB: CATÁLOGO -->
            <div id="tab-catalog" class="rpg-tab-content">
                <div class="rpg-staff-catalog-toolbar">
                    <button id="btn-new-card" class="rpg-action-btn rpg-btn-primary"><i class="fas fa-plus"></i> Nueva Carta</button>
                    <input type="search" id="catalog-search" class="textbox rpg-staff-search" placeholder="Buscar por nombre...">
                </div>
                <div id="catalog-list" class="rpg-staff-catalog-list">
                    <div class="rpg-staff-catalog-empty">Cargando catálogo...</div>
                </div>
            </div>

            <!-- DRAWER: EDITOR (modal) -->
            <div id="card-editor-drawer" class="rpg-staff-drawer rpg-is-hidden">
                <div class="rpg-staff-drawer__backdrop" id="card-editor-backdrop"></div>
                <div class="rpg-staff-drawer__panel">
            <div id="tab-editor">
                <h3 id="editor-title"><i class="fas fa-edit"></i> Crear Nueva Carta</h3>
                <form id="card-editor-form" class="rpg-staff-editor-form">
                    <input type="hidden" id="card_id" value="">

                    <!-- FILA 1: Nombre + Tipo -->
                    <div>
                        <label class="rpg-form-label">Nombre</label>
                        <input type="text" id="c_name" class="textbox rpg-input-full" required>
                    </div>
                    <div>
                        <label class="rpg-form-label">Tipo</label>
                        <select id="c_type" class="textbox rpg-input-full">
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
                        <select id="c_activation" class="textbox rpg-input-full">
                            <option value="activa">Activa</option>
                            <option value="pasiva">Pasiva</option>
                            <option value="reactiva">Reactiva</option>
                        </select>
                    </div>
                    <div id="wrapper-rank">
                        <label class="rpg-form-label">Rango</label>
                        <select id="c_rank" class="textbox rpg-input-full">
                            <option value="C">C (Común)</option>
                            <option value="B">B (Poco común)</option>
                            <option value="A">A (Raro)</option>
                            <option value="S">S (Épico)</option>
                            <option value="SS">SS (Legendario)</option>
                        </select>
                    </div>

                    <!-- FILA 3: Tags (ancho completo) -->
                    <div class="rpg-grid-full">
                        <label class="rpg-form-label">Tags</label>
                        <div id="tag-selector">
                            <div id="tag-selected" class="rpg-staff-tag-selected"></div>
                            <div id="tag-dropdown" class="rpg-staff-tag-dropdown"></div>
                            <button type="button" id="tag-toggle-btn" class="rpg-action-btn rpg-btn-secondary rpg-staff-tag-toggle">Seleccionar Tags</button>
                            <input type="hidden" id="c_tags" value="">
                        </div>
                    </div>

                    <!-- FILA 4: Descripción (ancho completo) -->
                    <div class="rpg-grid-full">
                        <label class="rpg-form-label">Descripción</label>
                        <textarea id="c_desc" class="textbox rpg-input-full" rows="3"></textarea>
                    </div>

                    <!-- FILA 5: Coste PE + Ejecución -->
                    <div id="wrapper-cost">
                        <label class="rpg-form-label">Coste PE</label>
                        <input type="text" id="c_cost" class="textbox rpg-input-full" placeholder="3 PE">
                    </div>
                    <div id="wrapper-stat">
                        <label class="rpg-form-label">Ejecución</label>
                        <select id="c_stat" class="textbox rpg-input-full">
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
                    <div id="wrapper-dice" class="rpg-grid-full rpg-section-divider">
                        <label class="rpg-form-label">Dados / Fórmula de daño</label>
                        <div id="dice-builder">
                            <div id="dice-groups"></div>
                            <div class="rpg-dice-toolbar">
                                <button type="button" id="dice-add-group" class="rpg-action-btn rpg-btn-secondary rpg-btn-sm">+ Añadir dados</button>
                                <button type="button" id="dice-add-arma" class="rpg-action-btn rpg-btn-secondary rpg-btn-sm">+ Añadir [ARMA]</button>
                                <button type="button" id="dice-add-municion" class="rpg-action-btn rpg-btn-secondary rpg-btn-sm">+ Añadir [MUNICION]</button>
                            </div>

                            <div class="rpg-dice-meta-row">
                                <div>
                                    <label class="rpg-dice-label-sm">Bonus fijo</label>
                                    <input type="number" id="dice-fixed" min="0" value="0" class="textbox rpg-dice-input-sm">
                                </div>
                                <div>
                                    <label class="rpg-dice-label-sm">Stat</label>
                                    <select id="dice-stat" class="textbox rpg-dice-select-sm">
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
                                    <label class="rpg-dice-label-sm">Mult/Div</label>
                                    <input type="text" id="dice-stat-mod" class="textbox rpg-dice-input-md" placeholder="Ej: 2.5* o /2">
                                </div>
                                <div>
                                    <label class="rpg-dice-label-sm">Sufijo</label>
                                    <input type="text" id="dice-suffix" class="textbox rpg-dice-input-lg" placeholder="[FUEGO]">
                                </div>
                                <div class="rpg-dice-meta-row">
                                    <div class="rpg-dice-preview-box">
                                        <span class="rpg-dice-preview-arrow">→</span>
                                        <span id="dice-preview" class="rpg-dice-preview-value">—</span>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="c_dice" value="">
                        </div>
                    </div>


                    <!-- FILA 8: Reposo y Duración -->
                    <div id="wrapper-turns" class="rpg-grid-full rpg-section-divider rpg-grid-2">
                        <div>
                            <label class="rpg-form-label">Turnos de Reposo</label>
                            <input type="number" id="c_reposo" min="0" value="0" class="textbox rpg-input-full">
                        </div>
                        <div>
                            <label class="rpg-form-label">Duración (Turnos - vacío o 0 = Turno de activación)</label>
                            <input type="number" id="c_duracion" min="0" value="0" class="textbox rpg-input-full">
                        </div>
                    </div>

                    <!-- SECCIÓN DINÁMICA DE CAMPOS RPG -->
                    <div id="fields-akuma" class="rpg-staff-field-section">
                        <div>
                            <label class="rpg-form-label">Tipo de Akuma</label>
                            <select id="akuma_type" class="textbox rpg-input-full">
                                <option value="paramecia">Paramecia</option>
                                <option value="logia">Logia</option>
                                <option value="zoan">Zoan</option>
                            </select>
                        </div>
                        <div></div>
                        <div class="rpg-grid-full">
                            <label class="rpg-form-label">Efectos</label>
                            <textarea id="akuma_efectos" class="textbox rpg-input-full" rows="3"></textarea>
                        </div>
                        <div class="rpg-grid-full">
                            <label class="rpg-form-label">Limitaciones</label>
                            <textarea id="akuma_limitaciones" class="textbox rpg-input-full" rows="3"></textarea>
                        </div>
                        <div class="rpg-grid-full">
                            <label class="rpg-form-label">Debilidades</label>
                            <textarea id="akuma_debilidades" class="textbox rpg-input-full" rows="3"></textarea>
                        </div>
                    </div>

                    <div id="fields-equipo" class="rpg-staff-field-section">
                        <div>
                            <label class="rpg-form-label">Tipo de Equipo</label>
                            <select id="equipo_type" class="textbox rpg-input-full">
                                <option value="arma">Arma</option>
                                <option value="util">Útil / Consumible</option>
                                <option value="armadura">Armadura</option>
                            </select>
                        </div>
                        <div>
                            <label class="rpg-form-label">Subtipo</label>
                            <select id="equipo_subtipo_select" class="textbox rpg-input-full rpg-subtipo-select"></select>
                            <input type="text" id="equipo_subtipo" class="textbox rpg-input-full rpg-subtipo-other" placeholder="Especificar otro subtipo...">
                        </div>
                        <div id="wrapper-equipo-stack" class="rpg-wizard-hidden">
                            <label class="rpg-form-label">Cantidad por stack (consumible)</label>
                            <input type="number" id="equipo_stack_qty" min="1" value="1" class="textbox rpg-input-full">
                        </div>
                    </div>

                    <div id="fields-barco" class="rpg-staff-field-section">
                        <div>
                            <label class="rpg-form-label">Tipo de Barco</label>
                            <select id="barco_type" class="textbox rpg-input-full">
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
                            <input type="number" id="barco_tier" min="1" value="1" class="textbox rpg-input-full">
                        </div>
                        <div>
                            <label class="rpg-form-label">Vida</label>
                            <input type="number" id="barco_vida" min="0" value="100" class="textbox rpg-input-full">
                        </div>
                        <div>
                            <label class="rpg-form-label">Ataque</label>
                            <input type="number" id="barco_ataque" min="0" value="0" class="textbox rpg-input-full">
                        </div>
                        <div>
                            <label class="rpg-form-label">Velocidad</label>
                            <input type="number" id="barco_velocidad" min="0" value="0" class="textbox rpg-input-full">
                        </div>
                        <div>
                            <label class="rpg-form-label">Resistencia</label>
                            <input type="number" id="barco_resistencia" min="0" value="0" class="textbox rpg-input-full">
                        </div>
                    </div>

                    <div id="fields-npc" class="rpg-staff-field-section">
                        <div>
                            <label class="rpg-form-label">Subtipo</label>
                            <select id="npc_mascota_type" class="textbox rpg-input-full">
                                <option value="npc">NPC</option>
                                <option value="mascota">Mascota</option>
                            </select>
                        </div>
                        <div>
                            <label class="rpg-form-label">Vida</label>
                            <input type="number" id="npc_vida" min="0" value="50" class="textbox rpg-input-full">
                        </div>
                        <div id="wrapper-npc-tier">
                            <label class="rpg-form-label">Tier de Mascota</label>
                            <input type="number" id="npc_tier" min="1" value="1" class="textbox rpg-input-full">
                        </div>
                        <div></div>
                        <div class="rpg-grid-full">
                            <label class="rpg-form-label">Acciones</label>
                            <div id="npc-actions-container" class="rpg-npc-actions"></div>
                            <button type="button" id="btn-npc-add-action" class="rpg-action-btn rpg-btn-secondary rpg-staff-tag-toggle">+ Añadir Acción</button>
                        </div>
                    </div>

                    <div id="fields-haki" class="rpg-staff-field-section">
                        <div>
                            <label class="rpg-form-label">Tipo de Haki</label>
                            <select id="haki_type" class="textbox rpg-input-full">
                                <option value="busoshoku">Busoshoku (Armamiento)</option>
                                <option value="kenbunshoku">Kenbunshoku (Observación)</option>
                                <option value="haoshoku">Haoshoku (Conquistador / Rey)</option>
                            </select>
                        </div>
                        <div>
                            <label class="rpg-form-label">Nivel de Haki</label>
                            <select id="haki_level" class="textbox rpg-input-full">
                                <option value="despertado">Despertado</option>
                                <option value="basico">Básico</option>
                                <option value="medio">Medio</option>
                                <option value="avanzado">Avanzado</option>
                                <option value="maestro">Maestro</option>
                            </select>
                        </div>
                        <div class="rpg-grid-full">
                            <label class="rpg-form-label">Efecto</label>
                            <textarea id="haki_efecto" class="textbox rpg-input-full" rows="3" placeholder="Detalla el efecto de la habilidad de Haki..."></textarea>
                        </div>
                    </div>

                    <!-- FILA 9: Notas + URL Imagen -->
                    <div>
                        <label class="rpg-form-label">Notas</label>
                        <textarea id="c_notes" class="textbox rpg-input-full" rows="2"></textarea>
                    </div>
                    <div>
                        <label class="rpg-form-label">URL Imagen</label>
                        <input type="text" id="c_image" class="textbox" placeholder="https://..." class="textbox rpg-input-full">
                    </div>

                    <!-- FILA 10: Botones -->
                    <div class="rpg-staff-editor-actions">
                        <button type="button" id="btn-cancel-edit" class="rpg-action-btn rpg-btn-secondary">Cancelar</button>
                        <button type="submit" class="rpg-action-btn rpg-btn-primary">Guardar Carta</button>
                    </div>
                </form>
            </div>
                </div>
            </div>

            <!-- TAB: ASIGNACIÓN -->
            <div id="tab-assign" class="rpg-tab-content rpg-is-hidden">
                <h3><i class="fas fa-hand-holding-magic"></i> Asignar Carta a Personaje</h3>
                <div class="rpg-staff-assign-grid">

                    <div class="rpg-staff-panel-card">
                        <div class="rpg-form-group rpg-char-search">
                            <label class="rpg-form-label">Personaje</label>
                            <div class="character-search" data-target-id="assign_char_id">
                                <input type="text" class="char-search-input textbox rpg-input-full" placeholder="Escribe el nombre del personaje..." autocomplete="off">
                                <div class="char-search-results rpg-char-search-results"></div>
                                <input type="hidden" class="char-search-value" value="">
                            </div>
                        </div>
                        <div class="rpg-form-group">
                            <label class="rpg-form-label">Carta</label>
                            <select id="assign_card_id" class="textbox rpg-input-full">
                                <option value="">Cargando cartas...</option>
                            </select>
                        </div>
                        <div class="rpg-form-group rpg-wizard-hidden">
                            <label class="rpg-form-label">Rango a Asignar</label>
                            <select id="assign_rank" class="textbox rpg-input-full">
                                <option value="C">C</option>
                            </select>
                        </div>
                        <div class="rpg-form-group rpg-is-hidden" id="assign-qty-group">
                            <label class="rpg-form-label">Cantidad (consumibles)</label>
                            <input type="number" id="assign_cantidad" min="1" value="1" class="textbox">
                        </div>
                        <button id="btn-assign" class="rpg-action-btn rpg-btn-primary rpg-staff-btn-full">Asignar Carta</button>
                    </div>

                    <div class="rpg-staff-panel-card">
                        <h4 class="rpg-staff-panel-title">Deck del Personaje</h4>
                        <div class="rpg-form-group rpg-char-search rpg-char-search--mb">
                            <div class="character-search" data-target-id="view_deck_char_id">
                                <input type="text" class="char-search-input textbox rpg-input-full" placeholder="Escribe el nombre del personaje..." autocomplete="off">
                                <div class="char-search-results rpg-char-search-results"></div>
                                <input type="hidden" class="char-search-value" value="">
                            </div>
                        </div>
                        <button type="button" id="btn-view-deck" class="rpg-action-btn rpg-btn-secondary rpg-staff-btn-block"><i class="fas fa-eye"></i> Ver Deck</button>
                        <ul id="deck-list" class="rpg-staff-deck-list">
                            <!-- Lista de cartas asignadas -->
                        </ul>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
window.CARTAS_STAFF_CONFIG = { ajaxBase: '<?= rtrim($b_url, '/') ?>/game/ajax' };
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/cartas_staff.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page("Gestión de Cartas", $content);
