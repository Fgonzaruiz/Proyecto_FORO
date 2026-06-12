<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

// Cargar Carteles Wanted de la DB
$wanted_q = $db->query("SELECT * FROM " . TABLE_PREFIX . "game_wanted WHERE status = 'active' ORDER BY bounty DESC");
$wanteds = [];
while ($w = $db->fetch_array($wanted_q)) {
    $wanteds[] = $w;
}

ob_start();
?>
<div class="rpg-lore-app rpg-historia-final" id="wanted-app">
    <div class="rpg-lib-header rpg-lib-header-flex">
        <div class="rpg-lib-header-content">
            <h1><i class="fas fa-skull-crossbones"></i> CARTELERA WANTED</h1>
            <p>Las recompensas activas más buscadas de los mares.</p>
        </div>
        <div class="rpg-search-wrapper rpg-lib-search-box">
            <input type="text" id="wanted-search" class="textbox" placeholder="Busca por nombre, epíteto o motivo...">
            <i class="fas fa-search"></i>
        </div>
    </div>

    <div class="rpg-wanted-board rpg-wanted-board-padded">
        <div class="rpg-wanted-grid">
            <?php if (empty($wanteds)): ?>
                <div class="rpg-info-box rpg-info-box-full">No hay carteles de recompensa activos en este momento.</div>
            <?php else: ?>
                <?php foreach ($wanteds as $w): ?>
                    <div class="rpg-wanted-poster js-wanted-trigger"
                         data-title="<?= htmlspecialchars($w['name']) ?>"
                         data-subtitle="Recompensa: <?= number_format((float)$w['bounty'], 0, ',', '.') ?> Berries"
                         data-details="<h1>SE BUSCA: <?= htmlspecialchars($w['epithet'] ?: $w['name']) ?></h1><p><strong>Motivo:</strong> <?= nl2br(htmlspecialchars($w['reason'])) ?></p>">
                        <div class="rpg-wanted-poster-inner">
                            <div class="rpg-wanted-header">DEAD OR ALIVE</div>
                            <div class="rpg-wanted-image"><img src="<?= htmlspecialchars($w['image_url']) ?>" alt="Wanted Image"></div>
                            <div class="rpg-wanted-name"><?= htmlspecialchars($w['name']) ?></div>
                            <div class="rpg-wanted-bounty"><i class="fas fa-coins"></i> <?= number_format((float)$w['bounty'], 0, ',', '.') ?> -</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL GIGANTE DE LECTURA -->
<div class="rpg-lore-modal-overlay rpg-is-hidden" id="wanted-modal">
    <div class="rpg-lore-modal-box">
        <button class="rpg-lore-modal-close" id="wanted-modal-close"><i class="fas fa-times"></i></button>
        <div class="rpg-lore-modal-head">
            <span class="rpg-lore-modal-tag" id="wanted-modal-tag"></span>
            <h2 id="wanted-modal-title"></h2>
        </div>
        <div class="rpg-lore-modal-body" id="wanted-modal-body"></div>
    </div>
</div>

<?php
$content = ob_get_clean();
$content .= '<script src="' . rtrim($mybb->settings['bburl'], '/') . '/jscripts/game/wanted.js?v=' . time() . '"></script>';
game_render_page('Cartelera Wanted', $content);
