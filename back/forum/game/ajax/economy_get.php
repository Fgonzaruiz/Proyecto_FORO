<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\UseCases\GetEconomy;
use Game\Infrastructure\Http\MechanicsClient;
use Game\Presentation\Api\JsonResponder;

global $mybb;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    JsonResponder::fail(401, ['code' => 'unauthorized', 'message' => 'Login required'], ['endpoint' => 'economy_get']);
    exit;
}

$uc = new GetEconomy(new MechanicsClient());
$out = $uc->run($uid);

JsonResponder::ok(
    ['upstream' => $out],
    ['endpoint' => 'economy_get', 'uid' => $uid]
);

