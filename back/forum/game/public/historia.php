<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
use Game\Application\Services\LoreService;

global $mybb, $db, $header, $footer, $theme;

try {
    $cronologia = LoreService::obtenerCronologia(__DIR__ . '/../lore.json');
} catch (Throwable $e) { exit; }

$eras = $cronologia['eras'];
$periodicos = $cronologia['periodicos'] ?? [];

// Wanted extraído a wanted.php

ob_start();
?>
<div class="rpg-lore-app rpg-historia-final" id="historia-app">
    <div class="rpg-lib-header rpg-lib-header-flex">
        <div class="rpg-lib-header-title">
            <h2><i class="fas fa-book"></i> HISTORIA Y PERIÓDICOS</h2>
            <span class="rpg-lib-header-subtitle">Archivos, sucesos y Lore del mundo.</span>
        </div>
        <div class="rpg-historia-filters rpg-flex-between rpg-gap-2">
            <?php
            $meta = $cronologia['meta'] ?? [];
            $loreContextDataArray = [
                "all" => [
                    "title" => "Archivos Históricos",
                    "text"  => "",
                    "quote" => ""
                ]
            ];
            foreach ($eras as $era) {
                $loreContextDataArray[$era['numeral']] = [
                    "title" => "Era " . $era['numeral'] . ": " . $era['name'],
                    "text"  => $era['intro_text'] ?? '',
                    "quote" => $era['intro_quote'] ?? ''
                ];
            }
            $contextJson = json_encode($loreContextDataArray, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
            ?>
            <button id="btn-era-resumen" class="rpg-btn rpg-btn--secondary rpg-m-0" title="Leer Resumen" data-context-info='<?php echo $contextJson; ?>'>
                <i class="fas fa-book-open"></i> Resumen
            </button>
            <select id="historia-era-filter" class="rpg-form-input rpg-m-0">
                <option value="all">Todas las eras</option>
                <?php foreach ($eras as $era): ?>
                    <option value="<?php echo htmlspecialchars($era['numeral']); ?>">Era <?php echo htmlspecialchars($era['numeral']); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="rpg-search-wrapper rpg-m-0">
                <input type="text" id="historia-search" class="rpg-form-input" placeholder="Buscar en el Lore...">
                <i class="fas fa-search"></i>
            </div>
        </div>
    </div>

    <?php
    $meta = $cronologia['meta'] ?? [];
    
    $loreContextDataArray = [
        "all" => [
            "title" => "Archivos Históricos",
            "text"  => "",
            "quote" => ""
        ]
    ];
    foreach ($eras as $era) {
        $loreContextDataArray[$era['numeral']] = [
            "title" => "Era " . $era['numeral'] . ": " . $era['name'],
            "text"  => $era['intro_text'] ?? '',
            "quote" => $era['intro_quote'] ?? ''
        ];
    }
    $contextJson = json_encode($loreContextDataArray, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
    ?>
    <div id="historia-context-data" class="rpg-is-hidden" data-context-info='<?php echo $contextJson; ?>'></div>

    <!-- VISTA 1: CÓDICE DEL MUNDO -->
    <div id="view-codex" class="rpg-historia-columns js-historia-view">
        <!-- COLUMNA 1: LORE BASAL -->
        <section class="rpg-historia-col">
            <h2><i class="fas fa-book"></i> Lore Basal</h2>
            <div class="rpg-historia-list">
                <?php foreach ($eras as $era): ?>
                    <?php if (!empty($era['lore_basal'])): ?>
                        <div class="js-era-group js-era-filterable" data-era="<?php echo htmlspecialchars($era['numeral']); ?>">
                            <h3 class="rpg-historia-era-title">Era <?php echo htmlspecialchars($era['numeral']); ?></h3>
                            <?php foreach ($era['lore_basal'] as $lore): ?>
                                <div class="rpg-historia-item js-historia-trigger"
                                     data-id="lore-<?php echo htmlspecialchars((string)$lore['id']); ?>"
                                     data-title="<?php echo htmlspecialchars($lore['name']); ?>"
                                     data-subtitle="Lore Basal - Era <?php echo $era['numeral']; ?>"
                                     data-details="<?php echo htmlspecialchars($lore['details'] ?? $lore['desc']); ?>">
                                    <strong><?php echo htmlspecialchars($lore['name']); ?></strong>
                                    <span>Era <?php echo $era['numeral']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- COLUMNA 2: CRONOLOGÍA -->
        <section class="rpg-historia-col">
            <h2><i class="fas fa-stream"></i> Cronología</h2>
            <div class="rpg-historia-list">
                <?php foreach ($eras as $era): ?>
                    <div class="js-era-group js-era-filterable" data-era="<?php echo htmlspecialchars($era['numeral']); ?>">
                        <h3 class="rpg-historia-era-title">Era <?php echo htmlspecialchars($era['numeral']); ?></h3>
                        <?php 
                        $events = [];
                        foreach ($era['event_rows'] ?? [] as $row) {
                            foreach ($row['events'] as $ev) { $events[] = $ev; }
                        }
                        ?>
                        <?php foreach ($events as $event): ?>
                            <div class="rpg-historia-item js-historia-trigger"
                                 data-id="event-<?php echo htmlspecialchars((string)$event['id']); ?>"
                                 data-title="<?php echo htmlspecialchars($event['name']); ?>"
                                 data-subtitle="<?php echo htmlspecialchars($event['type_name'] ?? 'Evento'); ?> - Año <?php echo $event['start_year']; ?>"
                                 data-details="<?php echo htmlspecialchars($event['details'] ?? $event['desc']); ?>">
                                <strong>[<?php echo $event['start_year']; ?>] <?php echo htmlspecialchars($event['name']); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- COLUMNA 3: PERIÓDICOS -->
        <section class="rpg-historia-col">
            <h2><i class="far fa-newspaper"></i> News Coo</h2>
            <div class="rpg-historia-list rpg-news-list">
                <?php foreach ($periodicos as $news): ?>
                    <article class="rpg-news-card js-historia-trigger"
                             data-id="news-<?php echo htmlspecialchars((string)($news['id'] ?? '')); ?>"
                             data-title="<?php echo htmlspecialchars((string)($news['headline'] ?? '')); ?>"
                             data-subtitle="Gaceta News Coo - <?php echo htmlspecialchars((string)($news['date'] ?? '')); ?>"
                             data-details="<?php echo htmlspecialchars((string)($news['content'] ?? '')); ?>">
                        <?php if (!empty(trim((string)($news['image'] ?? '')))): ?>
                            <div class="rpg-news-img"><img src="<?php echo htmlspecialchars((string)($news['image'] ?? '')); ?>" alt="News Image"></div>
                        <?php endif; ?>
                        <div class="rpg-news-body">
                            <h4><?php echo htmlspecialchars((string)($news['headline'] ?? '')); ?></h4>
                            <span class="rpg-news-date"><?php echo htmlspecialchars((string)($news['date'] ?? '')); ?></span>
                            <p><?php echo htmlspecialchars((string)($news['snippet'] ?? '')); ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    </div>
</div>

<!-- MODAL GIGANTE DE LECTURA (FIJO CON SCROLL) -->
<div class="rpg-lore-modal-overlay rpg-is-hidden" id="historia-modal">
    <div class="rpg-lore-modal-box">
        <button class="rpg-lore-modal-close" id="historia-modal-close"><i class="fas fa-times"></i></button>
        <div class="rpg-lore-modal-head">
            <span class="rpg-lore-modal-tag" id="historia-modal-tag"></span>
            <h2 id="historia-modal-title"></h2>
        </div>
        <div class="rpg-lore-modal-body" id="historia-modal-body"></div>
    </div>
</div>

<?php
$content = ob_get_clean();
$content .= '<script src="' . rtrim($mybb->settings['bburl'], '/') . '/jscripts/game/historia.js?v=' . time() . '"></script>';
game_render_page('LORE: Archivos del Mundo', $content);
