<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

GameAjax::json(false, null, ['code' => 501, 'message' => 'Tiradas: usar mecánica de cartas en post o rolls.php cuando esté implementado.'], 501);
