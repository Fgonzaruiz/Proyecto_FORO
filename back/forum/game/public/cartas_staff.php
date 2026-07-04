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
                                <button type="button" class="rpg-type-picker-btn" data-card-type="npc_menor"><i class="fas fa-paw"></i> NPC Menor</button>
                            </div>
                        </div>

                <form id="card-editor-form" class="rpg-staff-editor-form rpg-staff-editor-form--stacked rpg-is-hidden">
                    <input type="hidden" id="card_id" value="">

                    <section class="rpg-form-section" id="section-identidad">
                        <h4 class="rpg-form-section-title"><i class="fas fa-id-card"></i> Identidad <button type="button" id="btn-tecnica-guide" class="rpg-system-tab-btn rpg-btn--staff rpg-tecnica-guide-btn rpg-is-hidden"><i class="fas fa-book-open"></i> Guía de Técnicas</button></h4>
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
                                <option value="npc_menor">NPC Menor</option>
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
            </div>

            <!-- Modal: Guía de Creación de Técnicas -->
            <div id="tecnica-guide-modal" class="rpg-modal-overlay" data-rpg-modal aria-hidden="true">
                <div class="rpg-modal-panel rpg-modal-panel--xl">
                    <div class="rpg-modal-header">
                        <h3 class="rpg-modal-title"><i class="fas fa-book"></i> Guía de Creación de Técnicas Equilibradas</h3>
                        <button type="button" class="rpg-modal-close" data-rpg-modal-close aria-label="Cerrar">&times;</button>
                    </div>
                    <div class="rpg-modal-body">
                        <div class="tecnica-guide-container">
                            <h3>1. Puntos de Acción (PA)</h3>
                            <p>Los PA representan la capacidad de acción de un personaje por turno de combate. Cada post de combate, el PJ dispone de una reserva máxima de PA que gasta en todo lo que hace (desplazarse, usar técnicas, realizar acciones físicas con impacto mecánico).</p>
                            
                            <h4>Fórmula Base</h4>
                            <code>PA_max = 4 + (rango_AGI_efectivo × 2) + bonos_raza + bonos_linaje</code>
                            <p><strong>Tope absoluto:</strong> 20 PA. <strong>Mínimo garantizado:</strong> 2 PA.</p>

                            <table>
                                <thead>
                                    <tr>
                                        <th>Rango AGI efectivo</th>
                                        <th>PA base (sin bonos)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>D (1)</td><td>6 PA</td></tr>
                                    <tr><td>C (2)</td><td>8 PA</td></tr>
                                    <tr><td>B (3)</td><td>10 PA</td></tr>
                                    <tr><td>A (4)</td><td>12 PA</td></tr>
                                    <tr><td>S (5)</td><td>14 PA</td></tr>
                                    <tr><td>SS (6)</td><td>16 PA</td></tr>
                                </tbody>
                            </table>

                            <h4>Bonos a PA</h4>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Fuente</th>
                                        <th>Bonus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>Raza Mink / Raza Tontatta</td><td>+2 PA</td></tr>
                                    <tr><td>Raza Lunarian (penalización)</td><td>−1 PA</td></tr>
                                    <tr><td>Linaje pasiva <code>pa_extra</code></td><td>+1 PA</td></tr>
                                    <tr><td>Carta de soporte con tag <code>BONUS PA</code> activa</td><td>+2 PA ese post</td></tr>
                                    <tr><td>Estado <code>RALENTIZADO</code></td><td>−4 PA ese post</td></tr>
                                    <tr><td>Estado <code>PARALIZADO</code></td><td>−PA total (no puede actuar)</td></tr>
                                    <tr><td>Estado <code>EXHAUSTO</code></td><td>−3 PA ese post</td></tr>
                                </tbody>
                            </table>

                            <h4>Desplazamiento Narrativo (Metros por PA)</h4>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Rango AGI efectivo</th>
                                        <th>Metros por PA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>D</td><td>3 m / PA</td></tr>
                                    <tr><td>C</td><td>5 m / PA</td></tr>
                                    <tr><td>B</td><td>8 m / PA</td></tr>
                                    <tr><td>A</td><td>12 m / PA</td></tr>
                                    <tr><td>S</td><td>18 m / PA</td></tr>
                                    <tr><td>SS</td><td>25 m / PA</td></tr>
                                </tbody>
                            </table>

                            <h4>Acciones Físicas y de Entorno</h4>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Acción</th>
                                        <th>Coste PA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>Golpe físico sin técnica / Agarrar o inmovilizar</td><td>2 PA</td></tr>
                                    <tr><td>Soltar / lanzar un objeto o persona</td><td>1 PA</td></tr>
                                    <tr><td>Desenfundar o cambiar de arma</td><td>1 PA</td></tr>
                                    <tr><td>Levantarse del estado DERRIBADO</td><td>2 PA</td></tr>
                                    <tr><td>Interactuar con el entorno / Proteger a un aliado</td><td>2 PA</td></tr>
                                    <tr><td>Apuntar antes de disparar (+1 dado de ataque)</td><td>1 PA</td></tr>
                                    <tr><td>Esquiva física sin técnica reactiva</td><td>2 PA</td></tr>
                                    <tr><td>Preparar técnica con tag <code>CARGA</code></td><td>2 PA</td></tr>
                                </tbody>
                            </table>

                            <h3>2. Rangos de Técnica y Dados Base</h3>
                            <p>Las técnicas usan el sistema de rangos de cartas. El dado es fijo por rango; el stat de ejecución añade un modificador plano calculado como: <code>Modificador_stat = floor(valor_efectivo_stat / 10)</code>.</p>
                            
                            <table>
                                <thead>
                                    <tr>
                                        <th>Rango</th>
                                        <th>Dado base</th>
                                        <th>Coste PA</th>
                                        <th>Coste PE base</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>D</td><td>1d6</td><td>2 PA</td><td>5 PE</td></tr>
                                    <tr><td>C</td><td>1d10</td><td>2 PA</td><td>10 PE</td></tr>
                                    <tr><td>B</td><td>2d8</td><td>3 PA</td><td>20 PE</td></tr>
                                    <tr><td>A</td><td>2d12</td><td>4 PA</td><td>35 PE</td></tr>
                                    <tr><td>S</td><td>3d10</td><td>5 PA</td><td>55 PE</td></tr>
                                    <tr><td>SS</td><td>3d12</td><td>7 PA</td><td>80 PE</td></tr>
                                </tbody>
                            </table>

                            <h3>3. Costes de PE por Función y Efectos</h3>
                            <p>El coste final en PE se calcula aplicando el multiplicador de función al coste base del rango, redondeando al múltiplo de 5 más cercano, y sumando sobrecostes por efectos adicionales.</p>
                            
                            <h4>Multiplicador por Función Principal</h4>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Función</th>
                                        <th>PE relativo al rango</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>Ataque directo</td><td>100% del base</td></tr>
                                    <tr><td>Ataque con control (ralentizar, derribar)</td><td>115% del base</td></tr>
                                    <tr><td>Ataque en área (AoE)</td><td>125% del base</td></tr>
                                    <tr><td>Defensa / esquiva</td><td>80% del base</td></tr>
                                    <tr><td>Barrera / escudo</td><td>90% del base</td></tr>
                                    <tr><td>Soporte (buff a aliado)</td><td>70% del base</td></tr>
                                    <tr><td>Control puro (sin daño)</td><td>85% del base</td></tr>
                                    <tr><td>Técnica de movimiento</td><td>60% del base</td></tr>
                                </tbody>
                            </table>

                            <h4>Sobrecostes de PE por Efecto Adicional</h4>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Efecto adicional</th>
                                        <th>Sobrecoste PE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>Aplica un estado (aturdido, quemado, etc.)</td><td>+10 PE</td></tr>
                                    <tr><td>El estado dura más de 1 post</td><td>+10 PE por post extra</td></tr>
                                    <tr><td>Afecta a múltiples objetivos (AoE)</td><td>+15 PE</td></tr>
                                    <tr><td>AoE grande (más de 3 objetivos)</td><td>+25 PE</td></tr>
                                    <tr><td>Ignora una defensa / Ignora armadura</td><td>+20 PE</td></tr>
                                    <tr><td>Tiene alcance largo o superior al esperado</td><td>+10 PE</td></tr>
                                    <tr><td>Recuperación / Cura al usuario</td><td>+15 PE</td></tr>
                                    <tr><td>Buff a aliado</td><td>+10 PE</td></tr>
                                    <tr><td>Encadenamiento sin coste de PA adicional</td><td>+15 PE</td></tr>
                                    <tr><td>El efecto no tiene tirada de resistencia</td><td>+10 PE</td></tr>
                                </tbody>
                            </table>

                            <h3>4. Catálogo de Tags con Efecto Mecánico</h3>
                            
                            <h4>Activación y Temporalidad</h4>
                            <p>
                                <span class="badge-tag">ACTIVA</span>
                                <span class="badge-tag">PASIVA</span>
                                <span class="badge-tag">REACTIVA</span>
                                <span class="badge-tag">CONTINUA</span>
                                <span class="badge-tag">INSTANTÁNEA</span>
                                <span class="badge-tag">CARGA</span>
                                <span class="badge-tag">CANAL</span>
                                <span class="badge-tag">RETRASADA</span>
                                <span class="badge-tag">ENCADENABLE</span>
                                <span class="badge-tag">UNA VEZ</span>
                            </p>

                            <h4>Alcance y Geometría</h4>
                            <p>
                                <span class="badge-tag">CONTACTO</span>
                                <span class="badge-tag">CUERPO A CUERPO</span>
                                <span class="badge-tag">DISTANCIA CORTA</span>
                                <span class="badge-tag">DISTANCIA MEDIA</span>
                                <span class="badge-tag">DISTANCIA LARGA</span>
                                <span class="badge-tag">AUTOPERSONAL</span>
                                <span class="badge-tag">ALIADOS</span>
                                <span class="badge-tag">ÁREA PEQUEÑA</span>
                                <span class="badge-tag">ÁREA MEDIA</span>
                                <span class="badge-tag">ÁREA GRANDE</span>
                                <span class="badge-tag">LÍNEA</span>
                                <span class="badge-tag">CONO</span>
                                <span class="badge-tag">ANILLO</span>
                                <span class="badge-tag">TRAYECTORIA</span>
                                <span class="badge-tag">GLOBAL</span>
                            </p>

                            <h4>Función de Combate</h4>
                            <p>
                                <span class="badge-tag">OFENSIVA</span>
                                <span class="badge-tag">DEFENSIVA</span>
                                <span class="badge-tag">CONTROL</span>
                                <span class="badge-tag">SOPORTE</span>
                                <span class="badge-tag">MOVILIDAD</span>
                                <span class="badge-tag">CURACIÓN</span>
                                <span class="badge-tag">UTILIDAD</span>
                                <span class="badge-tag">INTERRUPCIÓN</span>
                                <span class="badge-tag">PENETRACIÓN</span>
                                <span class="badge-tag">DESVÍO</span>
                                <span class="badge-tag">ABSORCIÓN</span>
                                <span class="badge-tag">SEÑUELO</span>
                                <span class="badge-tag">ESCUDO</span>
                                <span class="badge-tag">BONUS PA</span>
                            </p>

                            <h4>Ejecución (Stat)</h4>
                            <p>
                                <span class="badge-tag">EJECUCIÓN: FUE</span>
                                <span class="badge-tag">EJECUCIÓN: AGI</span>
                                <span class="badge-tag">EJECUCIÓN: DES</span>
                                <span class="badge-tag">EJECUCIÓN: INST</span>
                                <span class="badge-tag">EJECUCIÓN: ESP</span>
                                <span class="badge-tag">EJECUCIÓN: INT</span>
                            </p>

                            <h4>Tipos de Daño</h4>
                            <p>
                                <span class="badge-tag">DAÑO FÍSICO</span>
                                <span class="badge-tag">DAÑO CORTANTE</span>
                                <span class="badge-tag">DAÑO CONTUNDENTE</span>
                                <span class="badge-tag">DAÑO PERFORANTE</span>
                                <span class="badge-tag">DAÑO ÍGNEO</span>
                                <span class="badge-tag">DAÑO CRIOGÉNICO</span>
                                <span class="badge-tag">DAÑO ELÉCTRICO</span>
                                <span class="badge-tag">DAÑO TÓXICO</span>
                                <span class="badge-tag">DAÑO EXPLOSIVO</span>
                                <span class="badge-tag">DAÑO INTERNO</span>
                                <span class="badge-tag">DAÑO ESPIRITUAL</span>
                                <span class="badge-tag">DAÑO ESTRUCTURAL</span>
                                <span class="badge-tag">DAÑO OSCURO</span>
                            </p>

                            <h3>5. Biblioteca de Estados Alterados</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Estado</th>
                                        <th>Efecto Mecánico</th>
                                        <th>Resistencia</th>
                                        <th>Duración Base</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>DERRIBADO</td><td>+1 dado de daño recibido. Levantarse cuesta 2 PA.</td><td>FUE / AGI</td><td>Hasta gastar 2 PA</td></tr>
                                    <tr><td>RALENTIZADO</td><td>Pierde 4 PA (mínimo 2). Velocidad despl. a la mitad.</td><td>AGI</td><td>1 post</td></tr>
                                    <tr><td>INMOVILIZADO</td><td>No puede desplazarse ni esquivar físicamente.</td><td>FUE / AGI</td><td>1-2 posts</td></tr>
                                    <tr><td>ATRAPADO</td><td>Como INMOVILIZADO, pero sin técnicas de MOVILIDAD.</td><td>FUE</td><td>Por agresor</td></tr>
                                    <tr><td>EXHAUSTO</td><td>Pierde 3 PA en cada post. Coste PE aumentado 20%.</td><td>RES</td><td>Hasta descanso</td></tr>
                                    <tr><td>PESADO</td><td>El coste en PA de desplazamiento se duplica.</td><td>FUE</td><td>2 posts</td></tr>
                                    <tr><td>QUEMADO</td><td>Al inicio de cada post pierde 5% PV máximo. Ignora armadura.</td><td>RES</td><td>2 posts</td></tr>
                                    <tr><td>ENVENENADO</td><td>Al inicio de cada post pierde 3% PV máximo.</td><td>RES</td><td>3 posts</td></tr>
                                    <tr><td>SANGRADO</td><td>Al final de cada post pierde 5% PV máximo.</td><td>RES</td><td>2 posts</td></tr>
                                    <tr><td>CORROSIÓN</td><td>Al inicio de cada post pierde 4% PV y la armadura pierde 10%.</td><td>RES</td><td>2 posts</td></tr>
                                    <tr><td>ATURDIDO</td><td>No puede usar técnicas activas en su siguiente post.</td><td>INT / ESP</td><td>1 post</td></tr>
                                    <tr><td>PARALIZADO</td><td>No puede gastar PA en ninguna acción activa.</td><td>RES + INST</td><td>1 post</td></tr>
                                    <tr><td>CEGADO</td><td>Pierde bonus de INST en tiradas. Rango medio/largo baja 1 dado.</td><td>INST</td><td>1-2 posts</td></tr>
                                    <tr><td>CONFUNDIDO</td><td>Siguiente técnica activa tiene 25% de probabilidad de golpear aliado.</td><td>INT</td><td>1 post</td></tr>
                                    <tr><td>ASUSTADO</td><td>Pierde 1 PA y no puede acercarse al origen del miedo.</td><td>ESP</td><td>1 post</td></tr>
                                    <tr><td>DOMINADO</td><td>Actúa bajo las órdenes de quien aplicó el estado (Sin SS).</td><td>ESP + INT</td><td>1 post</td></tr>
                                    <tr><td>MALDECIDO</td><td>Sin regeneración de PE natural. Costes PE +15%.</td><td>ESP</td><td>2 posts</td></tr>
                                    <tr><td>EMPAPADO</td><td>Doble daño eléctrico. Fruta de fuego pierde 50% inmunidad.</td><td>—</td><td>Secarse (1 PA)</td></tr>
                                    <tr><td>DEBILITADO</td><td>Anula Akuma/Haki. Stats físicos reducidos -1 rango.</td><td>—</td><td>Contacto</td></tr>
                                    <tr><td>DESENMASCARADO</td><td>Logias corpóreos: reciben 50% de daño físico sin Haki.</td><td>ESP</td><td>1 post</td></tr>
                                </tbody>
                            </table>
                        </div>
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
