<?php
$catalog_cards = [];
if ($char) {
    $cat_q = $db->query("
        SELECT id, name, card_type, `rank`
        FROM {$prefix}game_cards 
        WHERE id NOT IN (
            SELECT card_id FROM {$prefix}game_character_cards WHERE character_id = {$char['id']}
        )
        ORDER BY name ASC
    ");
    while ($c = $db->fetch_array($cat_q)) {
        $catalog_cards[] = $c;
    }
}
?>
<div id="pjTab_gestion" class="pj-preview-tab-content">
    <div class="rpg-gestion-panel">
        <!-- DASHBOARD LANDING VIEW -->
        <div id="gestion_dashboard">
            <div class="rpg-pp-display rpg-pp-display--wrap">
                <div class="rpg-pp-stats-row">
                    <div class="rpg-pp-val"><i class="fas fa-gem"></i> <span id="val_available_pp"><?= $pp_available ?></span> PP</div>
                    <div class="rpg-pp-val rpg-pp-val--pd"><i class="fas fa-star"></i> <span id="val_available_pd"><?= game_get_character_pd_available($char['id']) ?></span> PD</div>
                </div>
            </div>

            <div class="rpg-gestion-dashboard-grid">
                <!-- CARD 1: ATRIBUTOS -->
                <div class="rpg-gestion-card" onclick="switchGestionSubtab('atributos')">
                    <div class="rpg-gestion-card-icon rpg-gestion-card-icon--attr">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="rpg-gestion-card-body">
                        <h3>Comprar Atributos</h3>
                        <p>Mejora tus estadísticas base (Fuerza, Agilidad, Espíritu, etc.) canjeando tus PP acumulados.</p>
                    </div>
                    <div class="rpg-gestion-card-footer">
                        <span class="rpg-gestion-card-tag">Coste por rango</span>
                        <i class="fas fa-chevron-right rpg-gestion-chevron"></i>
                    </div>
                </div>

                <!-- CARD 2: GESTIONAR DECK -->
                <div class="rpg-gestion-card" onclick="switchGestionSubtab('crear_carta')">
                    <div class="rpg-gestion-card-icon rpg-gestion-card-icon--deck">
                        <i class="fas fa-wand-magic-sparkles"></i>
                    </div>
                    <div class="rpg-gestion-card-body">
                        <h3>Gestionar Deck</h3>
                        <p>Propón una carta personalizada, solicita borrado o pide una carta del catálogo oficial.</p>
                    </div>
                    <div class="rpg-gestion-card-footer">
                        <span class="rpg-gestion-card-tag">Propuestas, borrados y catálogo</span>
                        <i class="fas fa-chevron-right rpg-gestion-chevron"></i>
                    </div>
                </div>

                <!-- CARD 3: HISTORIAL Y CONVERSACIONES -->
                <div class="rpg-gestion-card" onclick="switchGestionSubtab('historial')">
                    <div class="rpg-gestion-card-icon rpg-gestion-card-icon--requests">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="rpg-gestion-card-body">
                        <h3>Mis Solicitudes</h3>
                        <p>Revisa tus solicitudes activas, responde en el chat de discusión y confirma tu conformidad.</p>
                    </div>
                    <div class="rpg-gestion-card-footer">
                        <span class="rpg-gestion-card-tag">Mensajes e historial</span>
                        <span id="dashboard-requests-badge" class="rpg-gestion-card-badge rpg-is-hidden">0 activa(s)</span>
                    </div>
                </div>

                <!-- CARD: DISCIPLINAS Y OFICIOS -->
                <div class="rpg-gestion-card" onclick="switchGestionSubtab('competencias')">
                    <div class="rpg-gestion-card-icon rpg-gestion-card-icon--red">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="rpg-gestion-card-body">
                        <h3>Disciplinas y Oficios</h3>
                        <p>Consulta grados (I–V), requisitos de nivel y mejora una competencia cada dos semanas.</p>
                    </div>
                    <div class="rpg-gestion-card-footer">
                        <span class="rpg-gestion-card-tag">Combate · Profesiones</span>
                        <i class="fas fa-chevron-right rpg-gestion-chevron"></i>
                    </div>
                </div>

                <!-- CARD 4: GESTIONAR EQUIPAMIENTO -->
                <div class="rpg-gestion-card" onclick="switchGestionSubtab('equipamiento')">
                    <div class="rpg-gestion-card-icon rpg-gestion-card-icon--equip">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <div class="rpg-gestion-card-body">
                        <h3>Gestionar Equipamiento</h3>
                        <p>Equipa tus armas, armaduras, compañeros (mascotas/NPCs) y barco activo respetando tu límite de carga.</p>
                    </div>
                    <div class="rpg-gestion-card-footer">
                        <span class="rpg-gestion-card-tag">Inventario y Carga</span>
                        <i class="fas fa-chevron-right rpg-gestion-chevron"></i>
                    </div>
                </div>

                <!-- CARD 5: DESTINO (DESBLOQUEOS PD) -->
                <div class="rpg-gestion-card" onclick="switchGestionSubtab('desbloqueos_pd')">
                    <div class="rpg-gestion-card-icon rpg-gestion-card-icon--pd">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="rpg-gestion-card-body">
                        <h3>Destino</h3>
                        <p>Consulta tus desbloqueos, estilos de combate secundarios adquiridos y poderes especiales mediante Puntos Destino (PD).</p>
                    </div>
                    <div class="rpg-gestion-card-footer">
                        <span class="rpg-gestion-card-tag">Registro de desbloqueos</span>
                        <i class="fas fa-chevron-right rpg-gestion-chevron"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- SUBTAB: ATRIBUTOS -->
        <div id="gestion_subtab_atributos" class="gestion-subtab-content">
            <button class="rpg-back-btn" onclick="showGestionDashboard()">
                <i class="fas fa-arrow-left"></i> Volver a Gestión
            </button>

            <div class="rpg-pp-display rpg-pp-display--wrap">
                <div class="rpg-pp-col rpg-pp-col--wide">
                    <h3>Progresión y atributos</h3>
                    <div class="rpg-pp-desc rpg-pp-desc--spaced">
                        <strong id="val_pj_nivel_sub">Rango <?= htmlspecialchars((string)($pj_progression['rank'] ?? 'D')) ?></strong>
                        &bull; Suma de rangos: <strong><?= (int)($pj_progression['sum_ranks'] ?? 0) ?></strong> / 42
                        <?php if ((int)$pj_progression['pp_linaje'] > 0): ?>
                        <br><span class="rpg-muted-soft">Tienes <?= (int)$pj_progression['pp_linaje'] ?> PP de linaje (se gastan primero al subir rangos).</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rpg-pp-val"><i class="fas fa-gem"></i> <span id="val_available_pp_sub"><?= $pp_available ?></span> PP</div>
            </div>

            <?php if ($char['status'] !== 'aprobada'): ?>
                <div class="rpg-locked-panel">
                    <i class="fas fa-lock rpg-locked-icon"></i>
                    Tu personaje debe estar **Aprobado** por el staff para poder comprar puntos de atributos.
                </div>
            <?php else: ?>
                <div class="rpg-attr-pillars-editor">
                    <?php
                    $stats_labels = [
                        'fuerza'        => ['Fuerza', 'fa-dumbbell', 'cuerpo', '#C62828', 'linear-gradient(135deg, rgba(198,40,40,0.15), rgba(198,40,40,0.05))'],
                        'destreza'      => ['Destreza', 'fa-bullseye', 'cuerpo', '#C62828', 'linear-gradient(135deg, rgba(198,40,40,0.15), rgba(198,40,40,0.05))'],
                        'vigor'         => ['Vigor', 'fa-heartbeat', 'cuerpo', '#C62828', 'linear-gradient(135deg, rgba(198,40,40,0.15), rgba(198,40,40,0.05))'],
                        'agilidad'      => ['Agilidad', 'fa-running', 'cuerpo', '#C62828', 'linear-gradient(135deg, rgba(198,40,40,0.15), rgba(198,40,40,0.05))'],
                        
                        'intelecto'     => ['Intelecto', 'fa-brain', 'mente', '#3b82f6', 'linear-gradient(135deg, rgba(59,130,246,0.15), rgba(59,130,246,0.05))'],
                        'ingenio'       => ['Ingenio', 'fa-lightbulb', 'mente', '#3b82f6', 'linear-gradient(135deg, rgba(59,130,246,0.15), rgba(59,130,246,0.05))'],
                        'concentracion' => ['Concentración', 'fa-crosshairs', 'mente', '#3b82f6', 'linear-gradient(135deg, rgba(59,130,246,0.15), rgba(59,130,246,0.05))'],
                        'percepcion'    => ['Percepción', 'fa-eye', 'mente', '#3b82f6', 'linear-gradient(135deg, rgba(59,130,246,0.15), rgba(59,130,246,0.05))'],
                        
                        'caudal'        => ['Caudal Aura', 'fa-fire', 'espiritu', '#a855f7', 'linear-gradient(135deg, rgba(168,85,247,0.15), rgba(168,85,247,0.05))'],
                        'control'       => ['Control Aura', 'fa-hand-sparkles', 'espiritu', '#a855f7', 'linear-gradient(135deg, rgba(168,85,247,0.15), rgba(168,85,247,0.05))'],
                        'voluntad'      => ['Voluntad', 'fa-fingerprint', 'espiritu', '#a855f7', 'linear-gradient(135deg, rgba(168,85,247,0.15), rgba(168,85,247,0.05))'],
                        'sensibilidad'  => ['Sensibilidad', 'fa-compass', 'espiritu', '#a855f7', 'linear-gradient(135deg, rgba(168,85,247,0.15), rgba(168,85,247,0.05))'],
                    ];
                    $pillars = [
                        'cuerpo' => ['Pilar Cuerpo', 'fa-dumbbell'],
                        'mente' => ['Pilar Mente', 'fa-brain'],
                        'espiritu' => ['Pilar Espíritu', 'fa-bahai'],
                    ];
                    $ctxGestion = $char['stat_context'] ?? [];
                    foreach ($pillars as $pKey => $pMeta):
                    ?>
                        <h4 class="hunter-pillar-editor-title hunter-pillar-editor-title--<?= $pKey ?>"><i class="fas <?= $pMeta[1] ?>"></i> <?= $pMeta[0] ?></h4>
                        <div class="rpg-attr-buy-grid">
                            <?php foreach ($stats_labels as $key => $lbl):
                                if ($lbl[2] !== $pKey) continue;
                                $curr_val = (int)($char['stats'][$key] ?? 1);
                                $displayRank = (string)($ctxGestion['display'][$key] ?? \Game\Shared\StatScale::rankDisplayLabel($curr_val));
                                $nextCost = $pj_progression['next_upgrade_costs'][$key] ?? null;
                            ?>
                                <div class="rpg-attr-buy-card">
                                    <div class="rpg-attr-buy-header">
                                        <div class="rpg-attr-buy-icon rpg-attr-buy-icon--<?= $key ?>" data-icon-bg="<?= htmlspecialchars($lbl[4], ENT_QUOTES) ?>" data-icon-color="<?= htmlspecialchars($lbl[3], ENT_QUOTES) ?>">
                                            <i class="fas <?= $lbl[1] ?>"></i>
                                        </div>
                                        <div class="rpg-attr-buy-name"><?= $lbl[0] ?></div>
                                        <div class="rpg-attr-buy-value rpg-stat-rank <?= htmlspecialchars(\Game\Shared\StatScale::rankDisplayCssClass((int)($ctxGestion['effective_ranks'][$key] ?? $curr_val))) ?>" id="val_stat_<?= $key ?>"><?= htmlspecialchars($displayRank) ?></div>
                                    </div>
                                    <div class="rpg-attr-buy-actions">
                                        <div class="rpg-attr-buy-cost">Siguiente: <span class="pj-stat-cost-label"><?= $nextCost !== null ? (int)$nextCost . ' PP' : 'Máximo (SS)' ?></span></div>
                                        <button class="rpg-attr-buy-btn" onclick="buyStatPoint('<?= $key ?>')"<?= $nextCost === null ? ' disabled' : '' ?>>
                                            <i class="fas fa-arrow-up"></i> Subir rango
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- SUBTAB: CREACIÓN DE CARTA (GESTIONAR DECK) -->
        <div id="gestion_subtab_crear_carta" class="gestion-subtab-content">
            <button class="rpg-back-btn" onclick="showGestionDashboard()">
                <i class="fas fa-arrow-left"></i> Volver a Gestión
            </button>

            <div class="rpg-form-panel">
                <h3 class="rpg-form-heading">
                    <i class="fas fa-sliders-h rpg-form-heading-icon--purple"></i> Gestionar Deck
                </h3>
                
                <!-- Selector de Modo -->
                <div class="rpg-gestion-deck-modes">
                    <button type="button" id="btn_mode_propose" class="rpg-back-btn rpg-back-btn--flat active" onclick="switchGestionDeckMode('propose')">
                        <i class="fas fa-plus"></i> Proponer Nueva Carta
                    </button>
                    <button type="button" id="btn_mode_delete" class="rpg-back-btn rpg-back-btn--flat" onclick="switchGestionDeckMode('delete')">
                        <i class="fas fa-trash-alt"></i> Solicitar Borrado
                    </button>
                    <button type="button" id="btn_mode_catalog" class="rpg-back-btn rpg-back-btn--flat" onclick="switchGestionDeckMode('catalog')">
                        <i class="fas fa-clone"></i> Carta de Catálogo
                    </button>
                </div>

                <!-- MODO: PROPONER CARTA -->
                <div id="deck_mode_propose_section">
                    <div class="rpg-form-stack">
                        <p class="rpg-form-help">
                            Propón una técnica, equipo, Akuma no Mi o NPC menor adaptado a tu personaje. Tras enviarla, podrás conversar con los moderadores en el chat interactivo para ajustar sus efectos.
                        </p>
                        
                        <div class="form-group">
                            <label class="rpg-form-label">Nombre de la Carta</label>
                            <input type="text" id="req_new_name" class="textbox rpg-form-input">
                        </div>
                          
                        <div class="form-group">
                             <label class="rpg-form-label">Tipo de Carta</label>
                             <select id="req_new_type" class="textbox rpg-form-input">
                                  <option value="tecnica">Técnica</option>
                                  <option value="equipo">Equipo</option>
                                  <option value="npc_menor">NPC Menor</option>
                                  <option value="objeto">Objeto</option>
                                  <option value="consumible">Consumible</option>
                              </select>
                         </div>

                         <!-- CAMPOS DINÁMICOS PROPUESTA JUGADOR -->
                         <div id="req_fields_equipo" class="rpg-req-fields">
                            <div class="form-group">
                                <label class="rpg-form-label">Tipo de Equipo</label>
                                <select id="req_equipo_type" class="textbox rpg-form-input">
                                    <option value="arma">Arma</option>
                                    <option value="util">Útil / Consumible</option>
                                    <option value="armadura">Armadura</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="rpg-form-label">Subtipo (ej: Espada, Arco, Botiquín...)</label>
                                <select id="req_equipo_subtipo_select" class="textbox rpg-form-input rpg-form-input--spaced"></select>
                                <input type="text" id="req_equipo_subtipo" class="textbox rpg-form-input rpg-is-hidden">
                            </div>
                            <div id="wrapper_req_equipo_damage" class="rpg-form-row-flex rpg-is-hidden">
                                 <div class="form-group rpg-form-group--flex1">
                                     <label class="rpg-form-label">Dado de Daño</label>
                                     <select id="req_equipo_damage_dice_select" class="textbox rpg-form-input rpg-form-input--spaced">
                                         <option value="1d4">1d4</option>
                                         <option value="1d6">1d6</option>
                                         <option value="1d8">1d8</option>
                                         <option value="1d10">1d10</option>
                                         <option value="1d12">1d12</option>
                                         <option value="2d4">2d4</option>
                                         <option value="2d6">2d6</option>
                                         <option value="2d8">2d8</option>
                                         <option value="2d10">2d10</option>
                                         <option value="3d6">3d6</option>
                                         <option value="4d6">4d6</option>
                                         <option value="otros">Otros (Especificar)</option>
                                     </select>
                                     <input type="text" id="req_equipo_damage_dice" class="textbox rpg-form-input rpg-is-hidden">
                                 </div>
                                 <div class="form-group rpg-form-group--flex1">
                                     <label class="rpg-form-label">Atributo</label>
                                     <select id="req_equipo_damage_stat" class="textbox rpg-form-input">
                                         <option value="">Ninguno</option>
                                         <option value="FUE">FUE</option>
                                         <option value="AGI">AGI</option>
                                         <option value="DES">DES</option>
                                         <option value="INST">INST</option>
                                         <option value="ESP">ESP</option>
                                         <option value="INT">INT</option>
                                     </select>
                                 </div>
                            </div>
                            <div id="wrapper_req_equipo_util" class="rpg-form-row-flex rpg-is-hidden">
                                 <div class="form-group rpg-form-group--flex1">
                                     <label class="rpg-form-label">Dado (munición/consumible)</label>
                                     <select id="req_equipo_util_dice_select" class="textbox rpg-form-input">
                                         <option value="1d4">1d4</option>
                                         <option value="1d6">1d6</option>
                                         <option value="1d8">1d8</option>
                                         <option value="1d10">1d10</option>
                                         <option value="2d6">2d6</option>
                                     </select>
                                 </div>
                                 <div class="form-group rpg-form-group--flex1">
                                     <label class="rpg-form-label">Cantidad inicial</label>
                                     <input type="number" id="req_equipo_stack_qty" min="1" value="1" class="textbox rpg-form-input">
                                 </div>
                            </div>
                         </div>

                         <div id="req_fields_barco" class="rpg-req-fields">
                             <div class="form-group">
                                 <label class="rpg-form-label">Tipo de Barco</label>
                                 <select id="req_barco_type" class="textbox rpg-form-input">
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
                             <div class="rpg-form-grid-2">
                                 <div class="form-group">
                                     <label class="rpg-form-label">Tier</label>
                                     <input type="number" id="req_barco_tier" min="1" value="1" class="textbox rpg-form-input">
                                 </div>
                                 <div class="form-group">
                                     <label class="rpg-form-label">Vida</label>
                                     <input type="number" id="req_barco_vida" min="0" value="100" class="textbox rpg-form-input">
                                 </div>
                                 <div class="form-group">
                                     <label class="rpg-form-label">Ataque</label>
                                     <input type="number" id="req_barco_ataque" min="0" value="0" class="textbox rpg-form-input">
                                 </div>
                                 <div class="form-group">
                                     <label class="rpg-form-label">Velocidad</label>
                                     <input type="number" id="req_barco_velocidad" min="0" value="0" class="textbox rpg-form-input">
                                 </div>
                                 <div class="form-group rpg-form-group--span2">
                                     <label class="rpg-form-label">Resistencia</label>
                                     <input type="number" id="req_barco_resistencia" min="0" value="0" class="textbox rpg-form-input">
                                 </div>
                             </div>
                         </div>

                         <div id="req_fields_npc" class="rpg-req-fields">
                             <div class="form-group">
                                 <label class="rpg-form-label">Subtipo</label>
                                 <select id="req_npc_mascota_type" class="textbox rpg-form-input">
                                     <option value="npc">NPC</option>
                                     <option value="mascota">Mascota</option>
                                 </select>
                             </div>
                             <div class="form-group">
                                 <label class="rpg-form-label">Vida (HP)</label>
                                 <input type="number" id="req_npc_vida" min="0" value="50" class="textbox rpg-form-input">
                             </div>
                             <div class="form-group rpg-is-hidden" id="wrapper_req_npc_tier">
                                 <label class="rpg-form-label">Tier de Mascota</label>
                                 <input type="number" id="req_npc_tier" min="1" value="1" class="textbox rpg-form-input">
                             </div>
                             <div class="form-group">
                                 <label class="rpg-form-label">Acciones</label>
                                 <div id="req-npc-actions-container" class="rpg-npc-actions"></div>
                                 <button type="button" id="btn-req-npc-add-action" class="textbox rpg-btn-add-dashed">+ Añadir Acción</button>
                             </div>
                         </div>

                         <div class="form-group rpg-form-section-spaced">
                             <label class="rpg-form-label">Descripción y Efecto Propuesto</label>
                             <textarea id="req_new_desc" class="textbox rpg-form-input rpg-form-input--resize"></textarea>
                         </div>
                         <button class="rpg-action-btn rpg-btn-primary rpg-staff-btn-full" onclick="submitCustomCardRequest()"><i class="fas fa-paper-plane"></i> Enviar Propuesta al Staff</button>
                     </div>
                 </div>

                 <!-- MODO: SOLICITAR BORRADO -->
                 <div id="deck_mode_delete_section" class="rpg-is-hidden">
                     <div class="rpg-form-stack rpg-form-stack--wide">
                         <p class="rpg-form-help">
                             Selecciona la carta que deseas eliminar de tu inventario y detalla el motivo de la solicitud. El staff revisará la petición en tu historial.
                         </p>
                         <div class="form-group">
                             <label class="rpg-form-label">Seleccionar Carta a Borrar</label>
                             <select id="req_delete_card_id" class="textbox rpg-form-input">
                                 <option value="">Cargando tus cartas...</option>
                             </select>
                         </div>
                         <div class="form-group">
                             <label class="rpg-form-label">Motivo del Borrado</label>
                             <textarea id="req_delete_reason" class="textbox rpg-form-input rpg-form-input--resize"></textarea>
                         </div>
                         <button class="rpg-system-tab-btn rpg-staff-btn-danger rpg-staff-btn-full" onclick="submitCardDeleteRequest()"><i class="fas fa-trash-alt"></i> Enviar Solicitud de Borrado</button>
                     </div>
                 </div>

                 <!-- MODO: CARTA DE CATÁLOGO -->
                 <div id="deck_mode_catalog_section" class="rpg-is-hidden">
                     <div class="rpg-form-stack">
                         <p class="rpg-form-help">
                             Solicita que se te asigne una de las cartas preexistentes del catálogo oficial del juego (misiones, eventos, etc.).
                         </p>
                         <div class="form-group">
                             <label class="rpg-form-label">Seleccionar Carta</label>
                             <select id="req_existing_id" class="textbox rpg-form-input">
                                 <option value="">Selecciona una carta...</option>
                                 <?php foreach ($catalog_cards as $cc): ?>
                                     <option value="<?= $cc['id'] ?>">[<?= $cc['rank'] ?>] <?= htmlspecialchars($cc['name']) ?> (<?= ucfirst($cc['card_type']) ?>)</option>
                                 <?php endforeach; ?>
                             </select>
                         </div>
                         <div class="form-group">
                             <label class="rpg-form-label">Nota / Justificación (Opcional)</label>
                             <textarea id="req_existing_note" class="textbox rpg-form-input rpg-form-input--resize"></textarea>
                         </div>
                         <button class="rpg-action-btn rpg-btn-primary rpg-staff-btn-full" onclick="submitCatalogCardRequest()"><i class="fas fa-paper-plane"></i> Solicitar Adición</button>
                     </div>
                 </div>
             </div>
         </div>

         <!-- SUBTAB: HISTORIAL -->
         <div id="gestion_subtab_historial" class="gestion-subtab-content">
             <button class="rpg-back-btn" onclick="showGestionDashboard()">
                 <i class="fas fa-arrow-left"></i> Volver a Gestión
             </button>

             <div class="rpg-req-split">
                 <!-- LEFT: Requests List -->
                 <div class="rpg-req-list" id="my-requests-list-items">
                     <div class="rpg-req-loading">Cargando solicitudes...</div>
                 </div>

                 <!-- RIGHT: Request Details -->
                 <div class="rpg-req-detail" id="my-request-detail-panel">
                     <div class="rpg-req-detail-empty">
                         <i class="fas fa-envelope-open-text"></i>
                         Selecciona una solicitud de la lista para ver su conversación y estado.
                     </div>
                 </div>
             </div>
         </div>

         <!-- SUBTAB: DISCIPLINAS Y OFICIOS -->
         <div id="gestion_subtab_competencias" class="gestion-subtab-content">
             <button type="button" class="rpg-btn--secondary rpg-btn--sm" onclick="showGestionDashboard()"><i class="fas fa-arrow-left"></i> Volver</button>
             <h3 class="rpg-gestion-subtitle"><i class="fas fa-book-open"></i> Disciplinas y Oficios</h3>
             <div id="rpg-competencias-meta" class="rpg-competencias-meta"></div>
             <div id="rpg-competencias-acquire" class="rpg-competencias-acquire" hidden>
                 <h4 class="rpg-comp-acquire-heading"><i class="fas fa-plus-circle"></i> Adquirir nueva competencia</h4>
                 <div class="rpg-comp-acquire-tabs">
                     <button type="button" class="rpg-comp-acquire-tab active" data-acquire-type="oficio">Oficio</button>
                     <button type="button" class="rpg-comp-acquire-tab" data-acquire-type="disciplina">Disciplina</button>
                 </div>
                 <p id="rpg-comp-acquire-summary" class="rpg-inv-deck-hint"></p>
                 <div id="rpg-comp-acquire-list" class="rpg-comp-acquire-list"></div>
             </div>
             <div class="rpg-competencias-filters">
                 <button type="button" class="rpg-competencias-filter active" data-filter="all">Todos</button>
                 <button type="button" class="rpg-competencias-filter" data-filter="disciplina">Disciplinas</button>
                 <button type="button" class="rpg-competencias-filter" data-filter="oficio">Oficios</button>
             </div>
             <div id="rpg-competencias-list" class="rpg-competencias-list">
                 <div class="rpg-inv-loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Cargando…</div>
             </div>
         </div>

         <div id="gestion_subtab_equipamiento" class="gestion-subtab-content">
             <button class="rpg-back-btn" onclick="showGestionDashboard()">
                 <i class="fas fa-arrow-left"></i> Volver a Gestión
             </button>

             <div class="rpg-inv-panel">
                 <h3 class="rpg-inv-heading">
                     <i class="fas fa-briefcase rpg-form-heading-icon--teal"></i> Gestión de Equipamiento
                 </h3>
                 
                 <!-- Inventory stats dashboard -->
                 <div class="rpg-inv-dashboard-box">
                     <div class="rpg-inv-cc-card">
                         <div class="rpg-inv-cc-header">
                             <span class="rpg-inv-cc-lbl"><i class="fas fa-weight-hanging"></i> CAPACIDAD DE CARGA (CC)</span>
                             <strong id="rpg-inv-cc-display" class="rpg-inv-cc-number">0 / 0 CC</strong>
                         </div>
                         <div class="rpg-inv-cc-bar-container">
                             <div id="rpg-inv-cc-bar-fill" class="rpg-inv-cc-bar-fill"></div>
                         </div>
                         <div class="rpg-inv-cc-info">
                             Determina el peso máximo en armas, armaduras y útiles equipados. CC = 5 + floor(FUE / 4) + Linaje.
                         </div>
                     </div>
                     <div class="rpg-inv-slots-grid">
                         <div class="rpg-inv-slot-card">
                             <div class="rpg-inv-slot-icon"><i class="fas fa-paw"></i></div>
                             <div class="rpg-inv-slot-desc">
                                 <span class="rpg-inv-slot-lbl">COMPAÑEROS</span>
                                 <strong id="rpg-inv-companion-display" class="rpg-inv-slot-qty">0 / 1</strong>
                             </div>
                         </div>
                         <div class="rpg-inv-slot-card">
                             <div class="rpg-inv-slot-icon"><i class="fas fa-ship"></i></div>
                             <div class="rpg-inv-slot-desc">
                                 <span class="rpg-inv-slot-lbl">BARCO ACTIVO</span>
                                 <strong id="rpg-inv-barco-display" class="rpg-inv-slot-qty">0 / 1</strong>
                             </div>
                         </div>
                     </div>
                 </div>

                 <div class="rpg-inv-deck-section">
                     <h4 class="rpg-inv-section-title"><i class="fas fa-layer-group"></i> Tu Deck (Equipables / Disponibles)</h4>
                     <p class="rpg-inv-deck-hint">La capacidad de carga, compañeros y barco activo se muestran arriba. Las cartas equipadas aparecen resaltadas en verde.</p>
                     <div class="rpg-inv-deck-filters">
                         <button type="button" class="rpg-inv-filter-btn active" data-filter="all">Todos</button>
                         <button type="button" class="rpg-inv-filter-btn" data-filter="equipo">Carga</button>
                         <button type="button" class="rpg-inv-filter-btn" data-filter="npc_menor">Compañeros</button>
                         <button type="button" class="rpg-inv-filter-btn" data-filter="barco">Barcos</button>
                     </div>
                     <div id="rpg-inv-deck-list" class="rpg-inv-grid rpg-inv-grid--full">
                         <div class="rpg-inv-loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Cargando deck...</div>
                     </div>
                 </div>
             </div>
         </div>

         <!-- SUBTAB: DESTINO -->
         <div id="gestion_subtab_desbloqueos_pd" class="gestion-subtab-content">
             <button class="rpg-back-btn" onclick="showGestionDashboard()">
                 <i class="fas fa-arrow-left"></i> Volver a Gestión
             </button>

             <div class="rpg-pd-container">
                 <!-- Destiny Points display -->
                 <div class="rpg-pp-display rpg-pp-display--wrap">
                     <div class="rpg-pp-col">
                         <h3>Destino</h3>
                         <div class="rpg-pp-desc">Consulta los desbloqueos, estilos de combate secundarios y poderes especiales que has adquirido.</div>
                     </div>
                     <div class="rpg-pp-stats-row">
                         <div class="rpg-pp-val" id="pd_available_display"><i class="fas fa-star"></i> <span>0</span> PD Disponibles</div>
                     </div>
                 </div>

                 <div class="rpg-form-panel">
                     <h3 class="rpg-form-heading">
                         <i class="fas fa-history rpg-form-heading-icon--purple"></i> Desbloqueos y Compras Realizadas
                     </h3>
                     <div class="rpg-pd-history-list" id="pd_history_items">
                         <p class="rpg-muted-soft">Cargando historial de compras...</p>
                     </div>
                 </div>
             </div>
         </div>
    </div>
</div>
