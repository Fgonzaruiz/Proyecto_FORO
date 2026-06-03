<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\BusquedasRepository;

$repo = new BusquedasRepository();
GameAjax::json(true, $repo->listApproved(12));
