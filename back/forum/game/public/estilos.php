<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer, $theme;

$prefix = TABLE_PREFIX;

try {
    $query = $db->query("SELECT * FROM {$prefix}game_estilos ORDER BY id ASC");
    $styles = [];
    while ($row = $db->fetch_array($query)) {
        $tecs_query = $db->query("SELECT * FROM {$prefix}game_tecnicas WHERE estilo_id = " . (int)$row['id'] . " ORDER BY id ASC");
        $tecnicas = [];
        while ($tec_row = $db->fetch_array($tecs_query)) {
            $tecnicas[] = [
                'name' => $tec_row['name'],
                'desc' => $tec_row['desc'],
                'energy_cost' => $tec_row['energy_cost'],
                'damage' => $tec_row['damage'],
            ];
        }
        $styles[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'type' => $row['type'],
            'type_name' => $row['type_name'],
            'req' => $row['req'],
            'req_name' => $row['req_name'],
            'desc' => $row['desc'],
            'details' => $row['details'],
            'banner' => $row['banner'],
            'stats' => [
                'Fuerza Req.' => $row['req_fp'],
                'Destreza Req.' => $row['req_dp'],
                'Consumo Estamina' => $row['consumo_estamina'],
                'Dificultad' => $row['dificultad'],
            ],
            'tecnicas' => $tecnicas,
        ];
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Error al cargar Estilos</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>SQL: SELECT * FROM ' . htmlspecialchars($prefix) . 'game_estilos</p>';
    echo '<p>Verifica que las tablas ' . htmlspecialchars($prefix) . 'game_estilos y ' . htmlspecialchars($prefix) . 'game_tecnicas existen en phpMyAdmin.</p>';
    exit;
}

$banner_url = $mybb->settings['bburl'] . '/images/game/estilos_banner.png';

$cards_html = '';
foreach ($styles as $style) {
    $stats_json = htmlspecialchars(json_encode($style['stats']), ENT_QUOTES, 'UTF-8');
    $tecs_json = htmlspecialchars(json_encode($style['tecnicas']), ENT_QUOTES, 'UTF-8');

    $cards_html .= '
    <div class="rpg-lib-card" data-id="' . $style['id'] . '" data-name="' . htmlspecialchars($style['name']) . '" data-type="' . $style['type'] . '" data-req="' . $style['req'] . '" data-desc="' . htmlspecialchars($style['desc']) . '" data-details="' . htmlspecialchars($style['details']) . '" data-img="' . $banner_url . '" data-stats=\'' . $stats_json . '\' data-tecnicas=\'' . $tecs_json . '\'>
        <div class="rpg-lib-card-img" style="background-image: url(\'' . $banner_url . '\');">
            <span class="rpg-lib-card-badge">' . htmlspecialchars($style['type_name']) . '</span>
        </div>
        <div class="rpg-lib-card-body">
            <h2 class="rpg-lib-card-title">' . htmlspecialchars($style['name']) . '</h2>
            <p class="rpg-lib-card-desc">' . htmlspecialchars($style['desc']) . '</p>
            <div class="rpg-lib-card-stats">
                <span class="rpg-lib-card-stat"><i class="fas fa-dumbbell"></i> ' . htmlspecialchars($style['req_name']) . '</span>
            </div>
        </div>
    </div>';
}

$content = '
<div class="rpg-lib-container">
    <div class="rpg-lib-banner" style="background-image: url(\'' . $banner_url . '\');">
        <div class="rpg-lib-banner-content">
            <h1>Biblioteca: Estilos</h1>
            <p>Conoce los diferentes caminos de combate, técnicas de espada, artes marciales y requerimientos físicos necesarios para dominarlos.</p>
        </div>
    </div>

    <div class="rpg-lib-body">
        <aside class="rpg-lib-sidebar">
            <h3><i class="fas fa-filter"></i> Filtros de Combate</h3>

            <div class="rpg-filter-group">
                <span class="rpg-filter-label">Nombre del Estilo</span>
                <div class="rpg-search-wrapper">
                    <input type="text" id="lib-search" class="textbox" placeholder="Buscar estilo...">
                    <i class="fas fa-search"></i>
                </div>
            </div>

            <div class="rpg-filter-group">
                <span class="rpg-filter-label">Clase de Estilo</span>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="type" value="espadachin" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Esgrima / Espadas
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="type" value="artes-marciales" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Artes Marciales
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="type" value="tirador" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Tirador
                </label>
            </div>

            <div class="rpg-filter-group">
                <span class="rpg-filter-label">Atributo Principal</span>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="req" value="destreza" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Destreza
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="req" value="fuerza" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Fuerza
                </label>
                <label class="rpg-filter-option">
                    <input type="checkbox" name="req" value="haki" checked>
                    <span class="rpg-filter-checkbox"></span>
                    Haki Especial
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
                <span class="rpg-lib-modal-badge" id="modal-badge">Tipo</span>
            </div>
            <div class="rpg-modal-scroll" style="flex:none;max-height:100px;">
                <p class="rpg-lib-modal-desc" id="modal-details">Detalles t&eacute;cnicos del estilo...</p>
            </div>
            <div class="rpg-modal-scroll-sm">
                <div class="rpg-lib-modal-stats" id="modal-stats"></div>
            </div>
            <div id="modal-tecnicas" class="rpg-modal-scroll" style="flex:1;"></div>
        </div>
    </div>
</div>

<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("lib-search");
    const typeCheckboxes = document.querySelectorAll("input[name=\'type\']");
    const reqCheckboxes = document.querySelectorAll("input[name=\'req\']");
    const cards = document.querySelectorAll(".rpg-lib-card");

    const modal = document.getElementById("lib-modal");
    const modalClose = document.getElementById("modal-close");
    const modalBanner = document.getElementById("modal-banner");
    const modalTitle = document.getElementById("modal-title");
    const modalBadge = document.getElementById("modal-badge");
    const modalDetails = document.getElementById("modal-details");
    const modalStats = document.getElementById("modal-stats");
    const modalTecnicas = document.getElementById("modal-tecnicas");

    function filterCards() {
        const searchText = searchInput.value.toLowerCase().trim();

        const activeTypes = Array.from(typeCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        const activeReqs = Array.from(reqCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        cards.forEach(card => {
            const name = card.getAttribute("data-name").toLowerCase();
            const type = card.getAttribute("data-type");
            const req = card.getAttribute("data-req");

            const matchesSearch = name.includes(searchText);
            const matchesType = activeTypes.includes(type);
            const matchesReq = activeReqs.includes(req);

            if (matchesSearch && matchesType && matchesReq) {
                card.style.display = "flex";
            } else {
                card.style.display = "none";
            }
        });
    }

    searchInput.addEventListener("input", filterCards);
    typeCheckboxes.forEach(cb => cb.addEventListener("change", filterCards));
    reqCheckboxes.forEach(cb => cb.addEventListener("change", filterCards));

    cards.forEach(card => {
        card.addEventListener("click", function() {
            const name = this.getAttribute("data-name");
            const type = this.querySelector(".rpg-lib-card-badge").textContent;
            const details = this.getAttribute("data-details");
            const img = this.getAttribute("data-img");
            const stats = JSON.parse(this.getAttribute("data-stats"));
            const tecnicas = JSON.parse(this.getAttribute("data-tecnicas") || "[]");

            modalBanner.style.backgroundImage = `url(\'${img}\')`;
            modalTitle.textContent = name;
            modalBadge.textContent = type;
            modalDetails.textContent = details;

            modalStats.innerHTML = "";
            for (const [key, value] of Object.entries(stats)) {
                const statBox = document.createElement("div");
                statBox.className = "rpg-lib-modal-stat-box";
                statBox.innerHTML = `<div class="rpg-lib-modal-stat-lbl">${key}</div><div class="rpg-lib-modal-stat-val">${value}</div>`;
                modalStats.appendChild(statBox);
            }

            modalTecnicas.innerHTML = "";
            if (tecnicas.length > 0) {
                let techHtml = `<div class="rpg-tech-title"><i class="fas fa-crosshairs"></i> Técnicas disponibles</div><div class="rpg-tech-list">`;
                tecnicas.forEach(t => {
                    techHtml += `
                        <div class="rpg-tech-card">
                            <div class="rpg-tech-header">
                                <span class="rpg-tech-name">${t.name}</span>
                                <span class="rpg-tech-cost">${t.energy_cost}</span>
                            </div>
                            <div class="rpg-tech-desc">${t.desc}</div>
                            <div class="rpg-tech-dmg">Daño: ${t.damage}</div>
                        </div>`;
                });
                techHtml += `</div>`;
                modalTecnicas.innerHTML = techHtml;
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

game_render_page('Estilos de Combate', $content);
