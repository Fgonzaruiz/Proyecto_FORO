<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\LoreService;

global $mybb, $db, $header, $footer, $theme;

try {
    $jsonPath = __DIR__ . '/../lore.json';
    $cronologia = LoreService::obtenerCronologia($jsonPath);
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Error al cargar Historia</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

$banner_url = $mybb->settings['bburl'] . '/images/game/historia_banner.png';
$tipos = $cronologia['tipos'];
$eras  = $cronologia['eras'];

ob_start();
?>
<div class="rpg-lib-container rpg-historia-layout" id="historia-app">
    <div class="rpg-lib-banner" data-bg="<?php echo htmlspecialchars($banner_url, ENT_QUOTES); ?>">
        <div class="rpg-lib-banner-content">
            <h1>Biblioteca: Historia</h1>
            <p>Explora los eventos históricos, leyendas perdidas y batallas que cambiaron el curso del mundo.</p>
        </div>
    </div>

    <div class="rpg-historia-filter-bar" id="filter-bar">
        <div class="rpg-filter-pills" id="filter-pills"></div>
    </div>

    <div class="rpg-historia-layout-body">
        <aside class="rpg-lib-sidebar" id="sidebar-nav">
            <nav class="rpg-sidebar-nav">
                <?php foreach ($eras as $era): ?>
                    <?php
                    $totalEvents = 0;
                    $totalLore = count($era['lore_basal'] ?? []);
                    foreach (($era['event_rows'] ?? []) as $row) {
                        $totalEvents += count($row['events']);
                    }
                    ?>
                    <a class="rpg-sidebar-item" href="#era-<?php echo (int)$era['id']; ?>" data-era="<?php echo (int)$era['id']; ?>">
                        <span class="rpg-sidebar-item-numeral"><?php echo htmlspecialchars($era['numeral']); ?></span>
                        <span class="rpg-sidebar-item-name"><?php echo htmlspecialchars($era['name']); ?></span>
                        <span class="rpg-sidebar-item-years"><?php echo (int)$era['start_year']; ?>&ndash;<?php echo (int)$era['end_year']; ?></span>
                        <span class="rpg-sidebar-item-counts">
                            <?php if ($totalLore > 0): ?><span class="rpg-count-badge rpg-count-lore" title="Lore basal"><?php echo $totalLore; ?></span><?php endif; ?>
                            <?php if ($totalEvents > 0): ?><span class="rpg-count-badge rpg-count-event" title="Eventos"><?php echo $totalEvents; ?></span><?php endif; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <main class="rpg-lib-main" id="eras-vertical-wrap">
            <?php foreach ($eras as $era): ?>
                <section class="rpg-era-section" id="era-<?php echo (int)$era['id']; ?>">
                    <header class="rpg-era-section-header">
                        <span class="rpg-era-section-badge"><?php echo (int)$era['start_year']; ?> &ndash; <?php echo (int)$era['end_year']; ?></span>
                        <h2 class="rpg-era-section-title"><?php echo htmlspecialchars($era['numeral']); ?>: <?php echo htmlspecialchars($era['name']); ?></h2>
                    </header>

                    <div class="rpg-era-section-desc">
                        <?php if (!empty($era['intro_quote'])): ?>
                            <blockquote class="rpg-timeline-era__quote">
                                <p>&laquo;<?php echo htmlspecialchars($era['intro_quote']); ?>&raquo;</p>
                            </blockquote>
                        <?php endif; ?>
                        <?php if (!empty($era['intro_text'])): ?>
                            <p class="rpg-timeline-era__description"><?php echo htmlspecialchars($era['intro_text']); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php $loreEntries = $era['lore_basal'] ?? []; ?>
                    <?php if (!empty($loreEntries)): ?>
                        <div class="rpg-lore-basal-block">
                            <h3 class="rpg-block-heading">Conocimiento Ancestral</h3>
                            <div class="rpg-lore-basal-grid">
                                <?php foreach ($loreEntries as $lore): ?>
                                    <?php
                                    $subtype = $lore['subtype'] ?? 'historia_prohibida';
                                    $subtypeLabel = '';
                                    foreach ($tipos['lore_subtypes'] as $ls) {
                                        if ($ls['id'] === $subtype) { $subtypeLabel = $ls['label']; break; }
                                    }
                                    ?>
                                    <div class="rpg-lore-basal-card"
                                         data-modal-type="lore"
                                         data-id="<?php echo (int)$lore['id']; ?>"
                                         data-name="<?php echo htmlspecialchars($lore['name']); ?>"
                                         data-subtype="<?php echo htmlspecialchars($subtype); ?>"
                                         data-subtype-label="<?php echo htmlspecialchars($subtypeLabel); ?>"
                                         data-desc="<?php echo htmlspecialchars($lore['desc']); ?>"
                                         data-details="<?php echo htmlspecialchars($lore['details']); ?>"
                                         data-ubicacion="<?php echo htmlspecialchars($lore['ubicacion'] ?? ''); ?>"
                                         data-img="<?php echo htmlspecialchars($banner_url); ?>">
                                        <div class="rpg-lore-basal-card-body">
                                            <span class="rpg-lore-basal-card-subtype"><?php echo htmlspecialchars($subtypeLabel); ?></span>
                                            <h4 class="rpg-lore-basal-card-title"><?php echo htmlspecialchars($lore['name']); ?></h4>
                                            <p class="rpg-lore-basal-card-desc"><?php echo htmlspecialchars($lore['desc']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php $eventRows = $era['event_rows'] ?? []; ?>
                    <?php if (!empty($eventRows)): ?>
                        <div class="rpg-eventos-block">
                            <h3 class="rpg-block-heading">Eventos Históricos</h3>
                            <div class="rpg-timeline-container">
                                <?php foreach ($eventRows as $rowIdx => $row): ?>
                                    <?php
                                    $rowEvents = $row['events'];
                                    $isOverlap = $row['is_overlap'];
                                    $rowExtraCls = $isOverlap ? ' rpg-timeline-row--overlap' : '';
                                    ?>
                                    <div class="rpg-timeline-row<?php echo $rowExtraCls; ?>" data-row="<?php echo $rowIdx; ?>">
                                        <?php if ($isOverlap): ?>
                                            <div class="rpg-timeline-row-header">
                                                <span class="rpg-timeline-row-badge"><?php echo count($rowEvents); ?> eventos simultáneos</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php foreach ($rowEvents as $event): ?>
                                            <?php
                                            $evType = htmlspecialchars($event['type']);
                                            if ((int)$event['start_year'] === (int)$event['end_year']) {
                                                $yearStr = "Año " . (int)$event['start_year'];
                                            } else {
                                                $yearStr = "Años " . (int)$event['start_year'] . " - " . (int)$event['end_year'];
                                            }
                                            $statsJson = htmlspecialchars((string)json_encode([
                                                'Época Histórica' => $yearStr,
                                                'Ubicación Clave' => $event['ubicacion'],
                                                'Personajes Clave' => $event['personajes'] ?? '',
                                                'Impacto Rol' => $event['impacto'] ?? '',
                                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                            $eventLink = trim((string)($event['link'] ?? ''));
                                            ?>
                                            <div class="rpg-timeline-item rpg-ev-<?php echo $evType; ?>"
                                                 data-modal-type="event"
                                                 data-id="<?php echo (int)$event['id']; ?>"
                                                 data-name="<?php echo htmlspecialchars($event['name']); ?>"
                                                 data-type="<?php echo $evType; ?>"
                                                 data-type-name="<?php echo htmlspecialchars($event['type_name'] ?? $evType); ?>"
                                                 data-desc="<?php echo htmlspecialchars($event['desc']); ?>"
                                                 data-details="<?php echo htmlspecialchars($event['details']); ?>"
                                                 data-link="<?php echo htmlspecialchars($eventLink, ENT_QUOTES); ?>"
                                                 data-img="<?php echo htmlspecialchars($banner_url); ?>"
                                                 data-stats="<?php echo $statsJson; ?>">
                                                <div class="rpg-timeline-dot"></div>
                                                <div class="rpg-timeline-card">
                                                    <?php if ($isOverlap): ?>
                                                        <span class="rpg-timeline-card-overlap-badge">Simultáneo</span>
                                                    <?php endif; ?>
                                                    <div class="rpg-timeline-meta">
                                                        <span class="rpg-timeline-date"><i class="fas fa-clock"></i> <?php echo htmlspecialchars($yearStr); ?></span>
                                                        <span class="rpg-ev-badge"><?php echo htmlspecialchars($event['type_name'] ?? $evType); ?></span>
                                                    </div>
                                                    <h2 class="rpg-lib-card-title rpg-lib-card-title--lg">
                                                        <?php echo htmlspecialchars($event['name']); ?>
                                                        <?php if ($eventLink !== ''): ?>
                                                            <i class="fas fa-link rpg-timeline-forum-icon" title="Suceso real del foro"></i>
                                                        <?php endif; ?>
                                                    </h2>
                                                    <p class="rpg-lib-card-desc rpg-lib-card-desc--clamp3"><?php echo htmlspecialchars($event['desc']); ?></p>
                                                    <div class="rpg-lib-card-stats">
                                                        <span class="rpg-lib-card-stat"><i class="fas fa-globe-americas"></i> <?php echo htmlspecialchars($yearStr); ?></span>
                                                        <span class="rpg-lib-card-stat"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['ubicacion']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="rpg-era-empty-state">
                            <i class="fas fa-hourglass-end"></i>
                            <p>No hay sucesos registrados en esta era.</p>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </main>
    </div>
</div>

<div class="rpg-lib-modal" id="lib-modal">
    <div class="rpg-lib-modal-content rpg-modal-historia-content">
        <span class="rpg-lib-modal-close" id="modal-close">&times;</span>
        <div class="rpg-lib-modal-body rpg-modal-historia-body">
            <div class="rpg-modal-historia-col-left">
                <h3 class="rpg-modal-historia-col-title">Información Clave</h3>
                <div class="rpg-lib-modal-stats" id="modal-stats"></div>
            </div>
            <div class="rpg-modal-historia-col-right">
                <div class="rpg-lib-modal-header rpg-modal-header-sticky">
                    <h2 class="rpg-lib-modal-title" id="modal-title">Nombre</h2>
                    <span class="rpg-lib-modal-badge" id="modal-badge">Tipo</span>
                    <div id="modal-forum-link-wrap" class="rpg-historia-forum-link" hidden>
                        <a id="modal-forum-link" href="#" target="_blank" rel="noopener" class="rpg-historia-forum-link__btn">
                            <i class="fas fa-external-link-alt"></i> Ver suceso en el foro
                        </a>
                    </div>
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

$tiposJson = (string)json_encode($tipos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

$content .= '<script>
window.HISTORIA_CONFIG = { tipos: ' . $tiposJson . ' };
</script>
<script src="' . rtrim($mybb->settings['bburl'], '/') . '/jscripts/game/historia.js?v=2"></script>';

game_render_page('Historia y Línea Temporal', $content);
