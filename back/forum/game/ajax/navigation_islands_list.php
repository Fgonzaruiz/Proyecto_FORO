<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

GameAjax::requireLogin();

$exclude = (int)($_GET['exclude'] ?? 0);
$islands = game_nav_list_islands($exclude);

GameAjax::json(true, ['islands' => $islands]);
