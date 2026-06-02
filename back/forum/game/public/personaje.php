<?php
declare(strict_types=1);

/**
 * Ficha de personaje — controlador delgado.
 * Vista: game/views/personaje/page.php
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/personaje_init.php';

ob_start();
require __DIR__ . '/../views/personaje/page.php';
$content = ob_get_clean();

game_render_page('Mi Personaje', $content);
