<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer, $theme;

$prefix = TABLE_PREFIX;

try {
    $query = $db->query("SELECT * FROM {$prefix}game_akuma_no_mi ORDER BY id ASC");
    $fruits = [];
    while ($row = $db->fetch_array($query)) {
        $fruits[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'class' => $row['class'],
            'class_name' => $row['class_name'],
            'status' => $row['status'],
            'status_name' => $row['status_name'],
            'desc' => $row['desc'],
            'details' => $row['details'],
            'banner' => $row['banner'],
            'stats' => [
                'Tipo de Fruta' => $row['tipo_fruta'],
                'Usuario Actual' => $row['usuario_actual'],
                'Habilidad Clave' => $row['habilidad_clave'],
                'Precio Estimado' => $row['precio'],
            ],
        ];
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Error al cargar Akuma no Mi</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>SQL: SELECT * FROM ' . htmlspecialchars($prefix) . 'game_akuma_no_mi</p>';
    echo '<p>Verifica que la tabla ' . htmlspecialchars($prefix) . 'game_akuma_no_mi existe en phpMyAdmin.</p>';
    exit;
}

$banner_url = $mybb->settings['bburl'] . '/images/game/akuma_banner.png';

$cards_html = '';
foreach ($fruits as $fruit) {
    $stats_json = htmlspecialchars(json_encode($fruit['stats']), ENT_QUOTES, 'UTF-8');

    $cards_html .= '
    <div class="rpg-lib-card" data-id="' . $fruit['id'] . '" data-name="' . htmlspecialchars($fruit['name']) . '" data-class="' . $fruit['class'] . '" data-status="' . $fruit['status'] . '" data-desc="' . htmlspecialchars($fruit['desc']) . '" data-details="' . htmlspecialchars($fruit['details']) . '" data-img="' . $banner_url . '" data-stats=\'' . $stats_json . '\'>
        <div class="rpg-lib-card-img" style="background-image: url(\'' . $banner_url . '\');">
            <span class="rpg-lib-card-badge">' . htmlspecialchars($fruit['class_name']) . '</span>
        </div>
        <div class="rpg-lib-card-body">
            <h2 class="rpg-lib-card-title">' . htmlspecialchars($fruit['name']) . '</h2>
            <p class="rpg-lib-card-desc">' . htmlspecialchars($fruit['desc']) . '</p>
            <div class="rpg-lib-card-stats">
                <span class="rpg-lib-card-stat"><i class="fas fa-toggle-on"></i> ' . htmlspecialchars($fruit['status_name']) . '</span>
            </div>
        </div>
    </div>';
}

$content = '
<div class="rpg-lib-container">
    <div class="rpg-lib-banner" style="background-image: url(\'' . $banner_url . '\');">
        <div class="rpg-lib-banner-content">
            <h1>Biblioteca: Akuma no Mi</h1>
            <p>Descubre los misteriosos frutos del mar, sus clasificaciones en Logia, Paramecia y Zoan, y conoce qué poderes están activos o disponibles para el rol.</p>
        </div>
    </div>

    <div class="rpg-lib-body">
        <aside class="rpg-lib-sidebar">
            <h3><i class="fas fa-filter"></i> Filtros de Frutas</h3>

            <div class="rpg-filter-group">
                <span class="rpg-filter-label">Nombre de la Fruta</span>
                <div class="rpg-search-wrapper">
                    <input type="text" id="lib-search" class="textbox" placeholder="Buscar fruta...">
                    <i class="fas fa-search"></i>
                </div>
            </div>

            <div class="rpg-filter-group">
                <span class="rpg-filter-label">Tipo de Fruta</span>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="class" value="paramecia" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Paramecia
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="class" value="logia" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Logia
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="class" value="zoan" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Zoan
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="class" value="zoan-mitologica" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Zoan Mitológica
                </label>
            </div>

            <div class="rpg-filter-group">
                <span class="rpg-filter-label">Estado de Obtención</span>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="status" value="activa" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Activa (En Uso)
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="status" value="disponible" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Disponible (Libre)
                </label>
            </div>
        </aside>

        <main class="rpg-lib-content">
            <div class="rpg-lib-grid" id="lib-grid">
                ' . $cards_html . '
            </div>
        </main>
    </div>
</div>

<div class="rpg-lib-modal" id="lib-modal">
    <div class="rpg-lib-modal-content">
        <span class="rpg-lib-modal-close" id="modal-close">&times;</span>
        <div class="rpg-lib-modal-banner" id="modal-banner"></div>
        <div class="rpg-lib-modal-body">
            <div class="rpg-lib-modal-header rpg-modal-header-sticky">
                <h2 class="rpg-lib-modal-title" id="modal-title">Nombre</h2>
                <span class="rpg-lib-modal-badge" id="modal-badge">Clase</span>
            </div>
            <div class="rpg-modal-scroll" style="flex:none;max-height:120px;">
                <p class="rpg-lib-modal-desc" id="modal-details">Descripci&oacute;n detallada del fruto...</p>
            </div>
            <div class="rpg-modal-scroll-sm">
                <div class="rpg-lib-modal-stats" id="modal-stats"></div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("lib-search");
    const classCheckboxes = document.querySelectorAll("input[name=\'class\']");
    const statusCheckboxes = document.querySelectorAll("input[name=\'status\']");
    const cards = document.querySelectorAll(".rpg-lib-card");

    const modal = document.getElementById("lib-modal");
    const modalClose = document.getElementById("modal-close");
    const modalBanner = document.getElementById("modal-banner");
    const modalTitle = document.getElementById("modal-title");
    const modalBadge = document.getElementById("modal-badge");
    const modalDetails = document.getElementById("modal-details");
    const modalStats = document.getElementById("modal-stats");

    function filterCards() {
        const searchText = searchInput.value.toLowerCase().trim();

        const activeClasses = Array.from(classCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        const activeStatuses = Array.from(statusCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        cards.forEach(card => {
            const name = card.getAttribute("data-name").toLowerCase();
            const clazz = card.getAttribute("data-class");
            const status = card.getAttribute("data-status");

            const matchesSearch = name.includes(searchText);
            const matchesClass = activeClasses.includes(clazz);
            const matchesStatus = activeStatuses.includes(status);

            if (matchesSearch && matchesClass && matchesStatus) {
                card.style.display = "flex";
            } else {
                card.style.display = "none";
            }
        });
    }

    searchInput.addEventListener("input", filterCards);
    classCheckboxes.forEach(cb => cb.addEventListener("change", filterCards));
    statusCheckboxes.forEach(cb => cb.addEventListener("change", filterCards));

    cards.forEach(card => {
        card.addEventListener("click", function() {
            const name = this.getAttribute("data-name");
            const clazz = this.querySelector(".rpg-lib-card-badge").textContent;
            const details = this.getAttribute("data-details");
            const img = this.getAttribute("data-img");
            const stats = JSON.parse(this.getAttribute("data-stats"));

            modalBanner.style.backgroundImage = `url(\'${img}\')`;
            modalTitle.textContent = name;
            modalBadge.textContent = clazz;
            modalDetails.textContent = details;

            modalStats.innerHTML = "";
            for (const [key, value] of Object.entries(stats)) {
                const statBox = document.createElement("div");
                statBox.className = "rpg-lib-modal-stat-box";
                statBox.innerHTML = `<div class="rpg-lib-modal-stat-lbl">${key}</div><div class="rpg-lib-modal-stat-val">${value}</div>`;
                modalStats.appendChild(statBox);
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

game_render_page('Frutas del Diablo', $content);
