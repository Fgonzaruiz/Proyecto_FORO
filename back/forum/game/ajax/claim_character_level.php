<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

header('Content-Type: application/json; charset=utf-8');

GameAjax::requireLogin();
GameAjax::requirePost();

GameAjax::json(false, null, [
    'code' => 410,
    'message' => 'El sistema de subida semanal de nivel fue reemplazado por rangos de atributos. Compra rangos en Gestión > Atributos.',
], 410);
