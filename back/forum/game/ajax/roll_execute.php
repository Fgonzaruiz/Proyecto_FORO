<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\UseCases\ExecuteRoll;
use Game\Infrastructure\Http\MechanicsClient;
use Game\Presentation\Api\JsonResponder;
use Game\Shared\Json;

global $mybb;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    JsonResponder::fail(401, ['code' => 'unauthorized', 'message' => 'Login required'], ['endpoint' => 'roll_execute']);
    exit;
}

$raw = (string)file_get_contents('php://input');
$input = $raw !== '' ? Json::decode($raw) : [];

$uc = new ExecuteRoll(new MechanicsClient());
$out = $uc->run($uid, $input);

JsonResponder::ok(
    ['upstream' => $out],
    ['endpoint' => 'roll_execute', 'uid' => $uid]
);

