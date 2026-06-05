<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\LoreService;

global $mybb, $db, $header, $footer, $theme;

try {
    // Leemos el archivo lore.json ubicado en la raíz del módulo game
    $jsonPath = __DIR__ . '/../lore.json';
    $cronologia = LoreService::obtenerCronologia($jsonPath);
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Error al cargar Historia</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

$banner_url = $mybb->settings['bburl'] . '/images/game/historia_banner.png';

// Iniciamos almacenamiento en buffer para renderizar la plantilla limpia y nativa (B-ORO-05)
ob_start();
?>
<div class="rpg-lib-container">
    <div class="rpg-lib-banner" data-bg="<?php echo htmlspecialchars($banner_url, ENT_QUOTES); ?>">
        <div class="rpg-lib-banner-content">
            <h1>Biblioteca: Historia</h1>
            <p>Explora los eventos históricos, leyendas perdidas y batallas que cambiaron el curso del mundo.</p>
        </div>
    </div>

    <!-- Barra de eras horizontales -->
    <div class="rpg-eras-bar">
        <div class="rpg-eras-horizontal" id="eras-horizontal">
            <?php foreach ($cronologia as $era): ?>
                <?php 
                $cnt = count($era['eventos']);
                $empty_cls = ($cnt === 0) ? ' rpg-era-card--empty' : '';
                ?>
                <button class="rpg-era-card<?php echo $empty_cls; ?>" data-era="<?php echo (int)$era['id']; ?>">
                    <span class="rpg-era-range">
                        <?php echo htmlspecialchars($era['numeral']); ?>: <?php echo (int)$era['start_year']; ?> &ndash; <?php echo (int)$era['end_year']; ?>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Timeline vertical de eras -->
    <div class="rpg-eras-vertical-wrap" id="eras-vertical-wrap">
        <?php foreach ($cronologia as $era): ?>
            <div class="rpg-era-section" id="era-<?php echo (int)$era['id']; ?>">
                <div class="rpg-era-section-label">
                    <?php echo htmlspecialchars($era['numeral']); ?>: <?php echo htmlspecialchars($era['name']); ?> 
                    (<?php echo (int)$era['start_year']; ?> &ndash; <?php echo (int)$era['end_year']; ?>)
                </div>

                <!-- Cabecera de descripción de la era requerida -->
                <div class="rpg-era-section-desc">
                    <?php if (!empty($era['intro_quote'])): ?>
                        <blockquote class="rpg-timeline-era__quote">
                            <p>«<?php echo htmlspecialchars($era['intro_quote']); ?>»</p>
                        </blockquote>
                    <?php endif; ?>

                    <?php if (!empty($era['intro_text'])): ?>
                        <p class="rpg-timeline-era__description">
                            <?php echo htmlspecialchars($era['intro_text']); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($era['eventos'])): ?>
                    <div class="rpg-timeline-container">
                        <?php foreach ($era['eventos'] as $event): ?>
                            <?php
                            $type_class = 'ev-' . htmlspecialchars($event['type']);
                            
                            // Evaluar años y construir el rango
                            if ((int)$event['start_year'] === (int)$event['end_year']) {
                                $year_str = "Año " . (int)$event['start_year'];
                            } else {
                                $year_str = "Años " . (int)$event['start_year'] . " - " . (int)$event['end_year'];
                            }

                            $stats_json = htmlspecialchars((string)json_encode([
                                'Época Histórica' => $year_str,
                                'Ubicación Clave' => $event['ubicacion'],
                                'Personajes Clave' => $event['personajes'],
                                'Impacto Rol' => $event['impacto'],
                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                            ?>
                            <div class="rpg-timeline-item <?php echo $type_class; ?>" 
                                 data-id="<?php echo (int)$event['id']; ?>" 
                                 data-name="<?php echo htmlspecialchars($event['name']); ?>" 
                                 data-saga="<?php echo htmlspecialchars($event['type']); ?>" 
                                 data-type="<?php echo htmlspecialchars($event['type']); ?>" 
                                 data-desc="<?php echo htmlspecialchars($event['desc']); ?>" 
                                 data-details="<?php echo htmlspecialchars($event['details']); ?>" 
                                 data-img="<?php echo htmlspecialchars($banner_url); ?>" 
                                 data-stats="<?php echo $stats_json; ?>">
                                <div class="rpg-timeline-dot"></div>
                                <div class="rpg-timeline-card">
                                    <div class="rpg-timeline-meta">
                                        <span class="rpg-timeline-date">
                                            <i class="fas fa-clock"></i> <?php echo htmlspecialchars($year_str); ?>
                                        </span>
                                        <span class="rpg-lib-card-stat rpg-lib-card-stat--muted">
                                            <?php echo htmlspecialchars($event['type_name']); ?>
                                        </span>
                                    </div>
                                    <h2 class="rpg-lib-card-title rpg-lib-card-title--lg">
                                        <?php echo htmlspecialchars($event['name']); ?>
                                    </h2>
                                    <p class="rpg-lib-card-desc rpg-lib-card-desc--clamp3">
                                        <?php echo htmlspecialchars($event['desc']); ?>
                                    </p>
                                    <div class="rpg-lib-card-stats">
                                        <span class="rpg-lib-card-stat">
                                            <i class="fas fa-globe-americas"></i> <?php echo htmlspecialchars($year_str); ?>
                                        </span>
                                        <span class="rpg-lib-card-stat">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['ubicacion']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="rpg-era-empty-state">
                        <i class="fas fa-hourglass-end"></i>
                        <p>No hay sucesos registrados en esta era.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal detalle de evento histórico -->
<div class="rpg-lib-modal" id="lib-modal">
    <div class="rpg-lib-modal-content rpg-modal-historia-content">
        <span class="rpg-lib-modal-close" id="modal-close">&times;</span>
        <div class="rpg-lib-modal-body rpg-modal-historia-body">
            <!-- Columna izquierda: Información Clave -->
            <div class="rpg-modal-historia-col-left">
                <h3 class="rpg-modal-historia-col-title">Información Clave</h3>
                <div class="rpg-lib-modal-stats" id="modal-stats"></div>
            </div>
            <!-- Columna derecha: Título y Descripción -->
            <div class="rpg-modal-historia-col-right">
                <div class="rpg-lib-modal-header rpg-modal-header-sticky">
                    <h2 class="rpg-lib-modal-title" id="modal-title">Nombre</h2>
                    <span class="rpg-lib-modal-badge" id="modal-badge">Tipo</span>
                </div>
                <div class="rpg-modal-scroll">
                    <div class="rpg-lib-modal-desc rpg-historia-modal-desc" id="modal-details">Crónica del evento...</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

$content .= '<script>
window.HISTORIA_CONFIG = {};
</script>
<script src="' . rtrim($mybb->settings['bburl'], '/') . '/jscripts/game/historia.js?v=1"></script>';

game_render_page('Historia y Línea Temporal', $content);
