<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

GameAjax::json(false, null, ['code' => 501, 'message' => 'Inventario: usar game/public/inventory.php cuando esté implementado.'], 501);
