<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\UseCases\GetInventory;
use Game\Infrastructure\Http\MechanicsClient;
use Game\Presentation\Api\JsonResponder;

global $mybb;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    JsonResponder::fail(401, ['code' => 'unauthorized', 'message' => 'Login required'], ['endpoint' => 'inventory_get']);
    exit;
}

$uc = new GetInventory(new MechanicsClient());
$out = $uc->run($uid);

JsonResponder::ok(
    ['upstream' => $out],
    ['endpoint' => 'inventory_get', 'uid' => $uid]
);

