<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer, $theme;

$prefix = TABLE_PREFIX;

try {
    $query = $db->query("SELECT * FROM {$prefix}game_historia ORDER BY id ASC");
    $history = [];
    while ($row = $db->fetch_array($query)) {
        $history[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'saga' => $row['saga'],
            'saga_name' => $row['saga_name'],
            'type' => $row['type'],
            'type_name' => $row['type_name'],
            'desc' => $row['desc'],
            'details' => $row['details'],
            'epoca' => $row['epoca'],
            'ubicacion' => $row['ubicacion'],
            'personajes' => $row['personajes'],
            'impacto' => $row['impacto'],
            'banner' => $row['banner'],
            'event_date' => $row['event_date'],
        ];
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Error al cargar Historia</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>SQL: SELECT * FROM ' . htmlspecialchars($prefix) . 'game_historia</p>';
    echo '<p>Verifica que la tabla ' . htmlspecialchars($prefix) . 'game_historia existe en phpMyAdmin.</p>';
    exit;
}

$banner_url = $mybb->settings['bburl'] . '/images/game/historia_banner.png';

// Sort by numeric year from event_date (first 4 chars)
usort($history, function ($a, $b) {
    return ((int)substr($a['event_date'], 0, 4)) <=> ((int)substr($b['event_date'], 0, 4));
});

// Group into 200-year eras
$years = array_map(fn($e) => (int)substr($e['event_date'], 0, 4), $history);
$min_year = min($years);
$max_year = max($years);
$min_group = (int)(floor($min_year / 200) * 200);
$max_group = (int)(floor($max_year / 200) * 200);

$groups = [];
for ($g = $min_group; $g <= $max_group; $g += 200) {
    $groups[$g] = ['start' => $g, 'end' => $g + 199, 'events' => []];
}
foreach ($history as $event) {
    $y = (int)substr($event['event_date'], 0, 4);
    $g = (int)(floor($y / 200) * 200);
    $groups[$g]['events'][] = $event;
}

// --- Horizontal eras ---
$eras_horizontal = '';
foreach ($groups as $g => $data) {
    $cnt = count($data['events']);
    $empty_cls = $cnt === 0 ? ' rpg-era-card--empty' : '';
    $eras_horizontal .= '
    <button class="rpg-era-card' . $empty_cls . '" data-era="' . $g . '">
        <span class="rpg-era-range">' . $data['start'] . ' &ndash; ' . $data['end'] . '</span>
    </button>';
}

// --- Vertical timeline sections ---
$eras_vertical = '';
foreach ($groups as $g => $data) {
    $items_html = '';
    foreach ($data['events'] as $event) {
        $type_class = 'ev-' . $event['type'];
        $stats_json = htmlspecialchars(json_encode([
            'Época Histórica' => $event['epoca'],
            'Ubicación Clave' => $event['ubicacion'],
            'Personajes Clave' => $event['personajes'],
            'Impacto Rol' => $event['impacto'],
        ]), ENT_QUOTES, 'UTF-8');
        $items_html .= '
    <div class="rpg-timeline-item ' . $type_class . '" data-id="' . $event['id'] . '" data-name="' . htmlspecialchars($event['name']) . '" data-saga="' . $event['saga'] . '" data-type="' . $event['type'] . '" data-desc="' . htmlspecialchars($event['desc']) . '" data-details="' . htmlspecialchars($event['details']) . '" data-img="' . $banner_url . '" data-stats=\'' . $stats_json . '\'>
        <div class="rpg-timeline-dot"></div>
        <div class="rpg-timeline-card">
            <div class="rpg-timeline-meta">
                <span class="rpg-timeline-date"><i class="fas fa-clock"></i> ' . htmlspecialchars($event['event_date']) . '</span>
                <span class="rpg-lib-card-stat" style="background: rgba(255,255,255,0.05);">' . htmlspecialchars($event['type_name']) . '</span>
            </div>
            <h2 class="rpg-lib-card-title" style="font-size: 20px;">' . htmlspecialchars($event['name']) . '</h2>
            <p class="rpg-lib-card-desc" style="-webkit-line-clamp: 3;">' . htmlspecialchars($event['desc']) . '</p>
            <div class="rpg-lib-card-stats">
                <span class="rpg-lib-card-stat"><i class="fas fa-globe-americas"></i> ' . htmlspecialchars($event['epoca']) . '</span>
                <span class="rpg-lib-card-stat"><i class="fas fa-map-marker-alt"></i> ' . htmlspecialchars($event['ubicacion']) . '</span>
            </div>
        </div>
    </div>';
    }
    $empty = $items_html === '';
    $eras_vertical .= '
    <div class="rpg-era-section" id="era-' . $g . '">
        <div class="rpg-era-section-label">Era ' . $data['start'] . ' &ndash; ' . $data['end'] . '</div>' .
        ($empty
            ? '<div class="rpg-era-empty-state"><i class="fas fa-hourglass-end"></i><p>No hay sucesos registrados en esta era.</p></div>'
            : '<div class="rpg-timeline-container">' . $items_html . '</div>') . '
    </div>';
}

$content = '
<div class="rpg-lib-container">
    <div class="rpg-lib-banner" style="background-image: url(\'' . $banner_url . '\');">
        <div class="rpg-lib-banner-content">
            <h1>Biblioteca: Historia</h1>
            <p>Explora los eventos hist&oacute;ricos, leyendas perdidas y batallas que cambiaron el curso del mundo.</p>
        </div>
    </div>

    <div class="rpg-eras-bar">
        <div class="rpg-eras-horizontal" id="eras-horizontal">' . $eras_horizontal . '</div>
    </div>

    <div class="rpg-eras-vertical-wrap" id="eras-vertical-wrap">' . $eras_vertical . '</div>
</div>

<div class="rpg-lib-modal" id="lib-modal">
    <div class="rpg-lib-modal-content">
        <span class="rpg-lib-modal-close" id="modal-close">&times;</span>
        <div class="rpg-lib-modal-banner" id="modal-banner"></div>
        <div class="rpg-lib-modal-body">
            <div class="rpg-lib-modal-header rpg-modal-header-sticky" style="border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
                <h2 class="rpg-lib-modal-title" id="modal-title">Nombre</h2>
                <span class="rpg-lib-modal-badge" id="modal-badge">Tipo</span>
            </div>
            <div class="rpg-modal-scroll" style="flex:none;max-height:120px;">
                <p class="rpg-lib-modal-desc" id="modal-details" style="margin:0;min-height:60px;">Cr&oacute;nica del evento...</p>
            </div>
            <div class="rpg-modal-scroll-sm">
                <div class="rpg-lib-modal-stats" id="modal-stats"></div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    const modal = document.getElementById("lib-modal");
    const modalClose = document.getElementById("modal-close");
    const modalBanner = document.getElementById("modal-banner");
    const modalTitle = document.getElementById("modal-title");
    const modalBadge = document.getElementById("modal-badge");
    const modalDetails = document.getElementById("modal-details");
    const modalStats = document.getElementById("modal-stats");

    // Era selection
    function selectEra(era) {
        document.querySelectorAll(".rpg-era-card").forEach(function(c) { c.classList.remove("active"); });
        var card = document.querySelector(".rpg-era-card[data-era=\"" + era + "\"]");
        if (card) card.classList.add("active");

        document.querySelectorAll(".rpg-era-section").forEach(function(s) { s.classList.remove("open"); });
        var section = document.getElementById("era-" + era);
        if (section) section.classList.add("open");

        var wrap = document.getElementById("eras-vertical-wrap");
        if (wrap) {
            setTimeout(function() {
                var rect = wrap.getBoundingClientRect();
                var scrollY = window.scrollY + rect.top - 20;
                window.scrollTo({ top: scrollY, behavior: "smooth" });
            }, 100);
        }
    }

    // Era card click listeners
    document.querySelectorAll(".rpg-era-card:not(.rpg-era-card--empty)").forEach(function(card) {
        card.addEventListener("click", function() {
            selectEra(parseInt(this.getAttribute("data-era")));
        });
    });

    // Select first non-empty era
    var firstCard = document.querySelector(".rpg-era-card:not(.rpg-era-card--empty)");
    if (firstCard) {
        selectEra(parseInt(firstCard.getAttribute("data-era")));
    }

    // Timeline item click -> modal
    document.querySelectorAll(".rpg-timeline-item").forEach(function(item) {
        item.addEventListener("click", function() {
            var name = this.getAttribute("data-name");
            var type = this.querySelector(".rpg-lib-card-stat").textContent.trim();
            var details = this.getAttribute("data-details");
            var img = this.getAttribute("data-img");
            var stats = JSON.parse(this.getAttribute("data-stats"));

            modalBanner.style.backgroundImage = "url(" + img + ")";
            modalTitle.textContent = name;
            modalBadge.textContent = type;
            modalDetails.textContent = details;

            modalStats.innerHTML = "";
            for (var key in stats) {
                var box = document.createElement("div");
                box.className = "rpg-lib-modal-stat-box";
                box.innerHTML = "<div class=\"rpg-lib-modal-stat-lbl\">" + key + "</div><div class=\"rpg-lib-modal-stat-val\">" + stats[key] + "</div>";
                modalStats.appendChild(box);
            }

            modal.classList.add("open");
            document.body.classList.add("modal-open");
        });
    });

    modalClose.addEventListener("click", function() {
        modal.classList.remove("open");
        document.body.classList.remove("modal-open");
    });

    modal.addEventListener("click", function(e) {
        if (e.target === modal) {
            modal.classList.remove("open");
            document.body.classList.remove("modal-open");
        }
    });
});
</script>
';

game_render_page('Historia y Línea Temporal', $content);
