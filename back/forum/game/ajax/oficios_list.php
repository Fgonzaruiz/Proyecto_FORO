<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

GameAjax::requireLogin();

$activeOnly = !isset($_GET['all']) || $_GET['all'] !== '1';
$catalog = game_oficio_list_catalog($activeOnly);

GameAjax::json(true, ['oficios' => $catalog]);
