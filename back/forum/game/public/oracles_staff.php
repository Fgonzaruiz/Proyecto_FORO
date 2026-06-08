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
    <div class="rpg-staff-header rpg-staff-header--oracles">
        <div class="rpg-staff-header-content">
            <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Zona Staff</a>
            <h1><i class="fas fa-crystal-ball"></i> Sistema de Oráculos</h1>
            <p>Crea y gestiona oráculos — tablas de resultados aleatorios que se ejecutan tras postear.</p>
        </div>
    </div>

    <div class="rpg-staff-grid rpg-staff-grid--single">
        <div class="rpg-staff-section">
            <div class="rpg-staff-tabs">
                <button class="rpg-tab-btn active" data-target="tab-catalog">Catálogo</button>
                <button class="rpg-tab-btn" data-target="tab-preview">Vista Previa</button>
            </div>

            <!-- TAB: CATÁLOGO -->
            <div id="tab-catalog" class="rpg-tab-content">
                <div class="rpg-staff-catalog-toolbar">
                    <button id="btn-new-oracle" class="rpg-action-btn rpg-btn-primary"><i class="fas fa-plus"></i> Nuevo Oráculo</button>
                    <input type="search" id="catalog-search" class="textbox rpg-staff-search" placeholder="Buscar por nombre...">
                </div>
                <div id="oracle-catalog-list" class="rpg-staff-catalog-list">
                    <div class="rpg-staff-catalog-empty">Cargando oráculos...</div>
                </div>
            </div>

            <!-- TAB: PREVIEW -->
            <div id="tab-preview" class="rpg-tab-content rpg-is-hidden">
                <div class="rpg-staff-preview-toolbar">
                    <select id="preview-oracle-select" class="textbox rpg-input-full">
                        <option value="">Selecciona un oráculo...</option>
                    </select>
                    <button id="btn-roll-preview" class="rpg-action-btn rpg-btn-primary"><i class="fas fa-dice"></i> Tirar</button>
                </div>
                <div id="oracle-preview-result" class="rpg-oracle-preview-result">
                    <div class="rpg-staff-catalog-empty">Selecciona un oráculo y presiona "Tirar" para ver el resultado.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: editor de oráculos -->
<div id="oracle-editor-modal" class="rpg-modal-overlay" data-rpg-modal aria-hidden="true">
    <div class="rpg-modal-panel rpg-modal-panel--xl">
        <div class="rpg-modal-header">
            <h3 class="rpg-modal-title" id="editor-title"><i class="fas fa-plus"></i> Crear Nuevo Oráculo</h3>
            <button type="button" class="rpg-modal-close" data-rpg-modal-close aria-label="Cerrar">&times;</button>
        </div>
        <div class="rpg-modal-body">
            <form id="oracle-editor-form">
                <input type="hidden" id="oracle_id" value="">

                <section class="rpg-form-section">
                    <h4 class="rpg-form-section-title"><i class="fas fa-id-card"></i> Identidad</h4>
                    <div class="rpg-staff-editor-grid">
                        <div class="rpg-grid-full">
                            <label class="rpg-form-label">Nombre del Oráculo</label>
                            <input type="text" id="o_name" class="textbox rpg-input-full" required placeholder="Ej: Encuentro en el Mar">
                        </div>
                        <div>
                            <label class="rpg-form-label">Tipo</label>
                            <select id="o_type" class="textbox rpg-input-full">
                                <option value="custom">Custom (Personalizado)</option>
                                <option value="yes_no">Sí/No</option>
                                <option value="action">Acción</option>
                                <option value="theme">Tema</option>
                                <option value="action_theme">Acción + Tema</option>
                                <option value="place_descriptor">Descriptor de Lugar</option>
                                <option value="place_focus">Foco de Lugar</option>
                                <option value="character_role">Rol de PNJ</option>
                                <option value="character_trait">Rasgo de PNJ</option>
                                <option value="character_goal">Meta de PNJ</option>
                                <option value="pay_the_price">Paga el Precio</option>
                                <option value="delve_theme">Tema de Mazmorra</option>
                                <option value="delve_domain">Dominio de Mazmorra</option>
                            </select>
                        </div>
                        <div>
                            <label class="rpg-form-label">Subtipo</label>
                            <input type="text" id="o_subtype" class="textbox rpg-input-full" placeholder="Ej: encuentro, clima, bestia...">
                        </div>
                        <div>
                            <label class="rpg-form-label">Categoría / Isla</label>
                            <select id="o_category" class="textbox rpg-input-full">
                                <option value="">— Todas las islas —</option>
                            </select>
                        </div>
                        <div class="rpg-grid-full">
                            <label class="rpg-form-label">Descripción</label>
                            <textarea id="o_desc" class="textbox rpg-input-full" rows="3" placeholder="Describe qué representa este oráculo..."></textarea>
                        </div>
                        <div>
                            <label class="rpg-form-label">Tipo de Dado</label>
                            <select id="o_dice" class="textbox rpg-input-full">
                                <option value="d100">d100 (1-100)</option>
                                <option value="d20">d20 (1-20)</option>
                                <option value="d12">d12 (1-12)</option>
                                <option value="d10">d10 (1-10)</option>
                                <option value="d8">d8 (1-8)</option>
                                <option value="d6">d6 (1-6)</option>
                            </select>
                        </div>
                        <div>
                            <label class="rpg-form-label">URL Imagen (opcional)</label>
                            <input type="text" id="o_image" class="textbox rpg-input-full" placeholder="https://...">
                        </div>
                    </div>
                </section>

                <section class="rpg-form-section">
                    <h4 class="rpg-form-section-title"><i class="fas fa-table"></i> Resultados</h4>
                    <p class="rpg-form-hint">Define los rangos y resultados posibles. Formato: rango "1-10" o valor exacto "5".</p>
                    <div id="results-editor">
                        <div class="rpg-results-header-row">
                            <span class="rpg-results-header-label">Rango</span>
                            <span class="rpg-results-header-label">Resultado</span>
                            <span class="rpg-results-header-label">Descripción</span>
                            <span class="rpg-results-header-label">Auto-Invocar</span>
                            <span></span>
                        </div>
                        <div id="results-list"></div>
                        <button type="button" id="btn-add-result" class="rpg-action-btn rpg-btn-secondary rpg-btn-sm"><i class="fas fa-plus"></i> Añadir Resultado</button>
                    </div>
                </section>

                <section class="rpg-form-section">
                    <h4 class="rpg-form-section-title"><i class="fas fa-globe"></i> Variaciones por Categoría/Isla</h4>
                    <p class="rpg-form-hint">Si este oráculo varía según la isla, define aquí los resultados alternativos. La clave debe coincidir con el nombre de la categoría/isla.</p>
                    <div id="variations-editor">
                        <div id="variations-list"></div>
                        <button type="button" id="btn-add-variation" class="rpg-action-btn rpg-btn-secondary rpg-btn-sm"><i class="fas fa-plus"></i> Añadir Variación</button>
                    </div>
                </section>

                <div class="rpg-staff-editor-actions">
                    <button type="button" id="btn-cancel-edit" class="rpg-action-btn rpg-btn-secondary">Cancelar</button>
                    <button type="submit" class="rpg-action-btn rpg-btn-primary">Guardar Oráculo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.ORACLES_STAFF_CONFIG = { ajaxBase: '<?= rtrim($b_url, '/') ?>/game/ajax' };
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/rpg_modal.js?v=1"></script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/oracles_staff.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page("Sistema de Oráculos", $content);
