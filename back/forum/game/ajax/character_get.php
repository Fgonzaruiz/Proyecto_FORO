<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\UseCases\GetCharacter;
use Game\Infrastructure\Http\MechanicsClient;
use Game\Presentation\Api\JsonResponder;

global $mybb;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    JsonResponder::fail(401, ['code' => 'unauthorized', 'message' => 'Login required'], ['endpoint' => 'character_get']);
    exit;
}

$uc = new GetCharacter(new MechanicsClient());
$out = $uc->run($uid);

JsonResponder::ok(
    ['upstream' => $out],
    ['endpoint' => 'character_get', 'uid' => $uid]
);

