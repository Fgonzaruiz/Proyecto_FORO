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
            <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Zona Staff</a>
            <h1><i class="fas fa-layer-group"></i> Gestión de Cartas</h1>
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

            <!-- Modal: editor de cartas -->
            <div id="card-editor-modal" class="rpg-modal-overlay" data-rpg-modal aria-hidden="true">
                <div class="rpg-modal-panel rpg-modal-panel--xl">
                    <div class="rpg-modal-header">
                        <h3 class="rpg-modal-title" id="editor-title"><i class="fas fa-plus"></i> Crear Nueva Carta</h3>
                        <button type="button" class="rpg-modal-close" data-rpg-modal-close aria-label="Cerrar">&times;</button>
                    </div>
                    <div class="rpg-modal-body">
                        <div id="card-editor-step-type">
                            <p class="rpg-modal-intro">Elige el tipo de carta. El formulario se adaptará a esa categoría.</p>
                            <div class="rpg-type-picker-grid">
                                <button type="button" class="rpg-type-picker-btn" data-card-type="tecnica"><i class="fas fa-fist-raised"></i> Técnica</button>
                                <button type="button" class="rpg-type-picker-btn" data-card-type="equipo"><i class="fas fa-shield-alt"></i> Equipo</button>
                                <button type="button" class="rpg-type-picker-btn" data-card-type="akuma_no_mi"><i class="fas fa-apple-alt"></i> Akuma no Mi</button>
                                <button type="button" class="rpg-type-picker-btn" data-card-type="haki"><i class="fas fa-hand-sparkles"></i> Haki</button>
                                <button type="button" class="rpg-type-picker-btn" data-card-type="npc_menor"><i class="fas fa-paw"></i> NPC Menor</button>
                                <button type="button" class="rpg-type-picker-btn" data-card-type="barco"><i class="fas fa-ship"></i> Barco</button>
                            </div>
                        </div>

                <form id="card-editor-form" class="rpg-staff-editor-form rpg-staff-editor-form--stacked rpg-is-hidden">
                    <input type="hidden" id="card_id" value="">

                    <section class="rpg-form-section" id="section-identidad">
                        <h4 class="rpg-form-section-title"><i class="fas fa-id-card"></i> Identidad</h4>
                        <div class="rpg-staff-editor-grid">
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
                            <label class="rpg-form-label">Rango / Rareza</label>
                            <select id="c_rank" class="textbox rpg-input-full">
                                <option value="D">D (Tier 1 Akuma)</option>
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
                                <button type="button" id="tag-toggle-btn" class="rpg-system-tab-btn rpg-staff-tag-toggle">Seleccionar Tags</button>
                                <input type="hidden" id="c_tags" value="">
                            </div>
                        </div>

                        <!-- FILA 4: Descripción (ancho completo) -->
                        <div class="rpg-grid-full">
                            <label class="rpg-form-label">Descripción</label>
                            <textarea id="c_desc" class="textbox rpg-input-full" rows="3"></textarea>
                        </div>

                        <!-- FILA 9: Notas + URL Imagen -->
                        <div>
                            <label class="rpg-form-label">Notas</label>
                            <textarea id="c_notes" class="textbox rpg-input-full" rows="2"></textarea>
                        </div>
                        <div>
                            <label class="rpg-form-label">URL Imagen</label>
                            <input type="text" id="c_image" class="textbox rpg-input-full" placeholder="https://...">
                        </div>
                        </div>
                    </section>

                    <section class="rpg-form-section rpg-is-hidden" id="section-economia">
                        <h4 class="rpg-form-section-title"><i class="fas fa-coins"></i> Economía</h4>
                        <div class="rpg-staff-editor-grid">
                            <div class="rpg-grid-full">
                                <label class="rpg-form-label">Valor en Berries (B.)</label>
                                <p class="rpg-form-hint">Precio de compra en tienda y base para reventa (50 %). Gestiona el catálogo en <a href="zona_staff_tienda.php">Gestionar Tienda</a>.</p>
                                <input type="number" id="c_cost_berries" min="1" value="1" class="textbox rpg-input-full" required>
                            </div>
                        </div>
                    </section>

                    <section class="rpg-form-section" id="section-combate">
                        <h4 class="rpg-form-section-title"><i class="fas fa-dice-d20"></i> Combate y costes</h4>
                        <div class="rpg-staff-editor-grid">
                        <!-- FILA 6: Dados (ancho completo) -->
                        <div id="wrapper-dice" class="rpg-grid-full">
                            <label class="rpg-form-label">Dados / Fórmula de daño</label>
                            <div id="dice-builder">
                                <div id="dice-groups"></div>
                                <div class="rpg-dice-toolbar">
                                    <button type="button" id="dice-add-group" class="rpg-system-tab-btn rpg-system-tab-btn--compact">+ Añadir dados</button>
                                    <button type="button" id="dice-add-arma" class="rpg-system-tab-btn rpg-system-tab-btn--compact">+ Añadir [ARMA]</button>
                                    <button type="button" id="dice-add-municion" class="rpg-system-tab-btn rpg-system-tab-btn--compact">+ Añadir [MUNICION]</button>
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

                        <!-- FILA 5: Coste PE + PA + Ejecución -->
                        <div id="wrapper-cost">
                            <label class="rpg-form-label">Coste PE</label>
                            <input type="text" id="c_cost" class="textbox rpg-input-full" placeholder="3 PE">
                        </div>
                        <div id="wrapper-pa">
                            <label class="rpg-form-label">Coste PA</label>
                            <input type="number" id="c_execution_cost" class="textbox rpg-input-full" min="0" value="0">
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

                        <!-- FILA 8: Reposo y Duración -->
                        <div id="wrapper-turns" class="rpg-grid-full rpg-grid-2">
                            <div>
                                <label class="rpg-form-label">Turnos de Reposo</label>
                                <input type="number" id="c_reposo" min="0" value="0" class="textbox rpg-input-full">
                            </div>
                            <div>
                                <label class="rpg-form-label">Duración (Turnos)</label>
                                <input type="number" id="c_duracion" min="0" value="0" class="textbox rpg-input-full">
                            </div>
                        </div>
                        </div>
                    </section>

                    <section class="rpg-form-section" id="section-tipo">
                        <h4 class="rpg-form-section-title"><i class="fas fa-sliders-h"></i> Propiedades del tipo</h4>
                        <div class="rpg-staff-editor-grid">
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
                            <div id="wrapper-akuma-subtipo">
                                <label class="rpg-form-label">Subtipo Zoan</label>
                                <select id="akuma_subtipo" class="textbox rpg-input-full">
                                    <option value="ninguno">Ninguno</option>
                                    <option value="antiguo">Antiguo</option>
                                    <option value="mitico">Mítico</option>
                                </select>
                            </div>
                            <div>
                                <label class="rpg-form-label">Tier de poder (1–5)</label>
                                <input type="number" id="akuma_tier" min="1" max="5" value="1" class="textbox rpg-input-full">
                                <p class="rpg-form-hint">Determina rango de carta y requisitos ESP/nivel al asignar. Ver guía Akuma tier.</p>
                            </div>
                            <div class="rpg-grid-full">
                                <label class="rpg-form-label">Identidad del poder</label>
                                <textarea id="akuma_identidad" class="textbox rpg-input-full" rows="2" placeholder="Una frase que define qué ES el usuario con esta fruta."></textarea>
                            </div>
                            <div class="rpg-grid-full">
                                <label class="rpg-form-label">Estructura ampliada (JSON)</label>
                                <p class="rpg-form-hint">Pasivas, transformaciones, capacidades base, inmunidades, debilidades, reglas y despertar. Plantilla en <code>Guias/04-sistema-akuma-estructura-tier.md</code>.</p>
                                <textarea id="akuma_structured" class="textbox rpg-input-full rpg-akuma-json-editor" rows="14" spellcheck="false"></textarea>
                                <button type="button" id="akuma_structured_reset" class="rpg-system-tab-btn rpg-system-tab-btn--compact">Cargar plantilla vacía</button>
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
                            <div>
                                <label class="rpg-form-label">Peso (Capacidad de Carga - CC)</label>
                                <input type="number" id="equipo_peso" min="1" max="20" value="1" class="textbox rpg-input-full">
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
                            <div>
                                <label class="rpg-form-label">Velocidad base (navegación)</label>
                                <input type="number" id="barco_velocidad_base" min="1" value="5" class="textbox rpg-input-full">
                            </div>
                            <div>
                                <label class="rpg-form-label">Bonus Grand Line</label>
                                <input type="number" id="barco_nav_grand_line" value="0" class="textbox rpg-input-full">
                            </div>
                            <div>
                                <label class="rpg-form-label">Bonus New World</label>
                                <input type="number" id="barco_nav_new_world" value="0" class="textbox rpg-input-full">
                            </div>
                            <div>
                                <label class="rpg-form-label">Bonus Calm Belt</label>
                                <input type="number" id="barco_nav_calm_belt" value="0" class="textbox rpg-input-full">
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
                                <button type="button" id="btn-npc-add-action" class="rpg-system-tab-btn rpg-staff-tag-toggle">+ Añadir Acción</button>
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
                        </div>
                    </section>

                    <div class="rpg-staff-editor-actions">
                        <button type="button" id="btn-cancel-edit" class="rpg-system-tab-btn">Cancelar</button>
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
                        <button type="button" id="btn-view-deck" class="rpg-system-tab-btn rpg-staff-btn-block"><i class="fas fa-eye"></i> Ver Deck</button>
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
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/rpg_modal.js?v=1"></script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/game_char_search.js?v=1"></script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/cartas_staff.js?v=5"></script>
<?php
$content = ob_get_clean();
game_render_page("Gestión de Cartas", $content);
