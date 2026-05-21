<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Página mínima de entrada para /game/
// Renderiza usando el sistema de templates de MyBB si existe, o salida simple como fallback.

global $templates, $header, $headerinclude, $footer, $lang;

$pageTitle = 'Game';

if (isset($templates) && is_object($templates)) {
    // Si más adelante defines una plantilla `game_index`, aquí se usará.
    // Mientras tanto, fallback a HTML mínimo.
    $content = '<div class="game-index"><h1>' . htmlspecialchars_uni($pageTitle) . '</h1></div>';
} else {
    $content = '<h1>' . $pageTitle . '</h1>';
}

game_render_page($pageTitle, $content);


