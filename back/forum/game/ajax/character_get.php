<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

GameAjax::json(false, null, [
    'code' => 501,
    'message' => 'Usar la ficha en game/public/personaje.php. Este endpoint legacy está deshabilitado.',
], 501);
