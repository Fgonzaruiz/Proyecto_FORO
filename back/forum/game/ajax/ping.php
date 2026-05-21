<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Presentation\Api\JsonResponder;

global $mybb;

$uid = 0;
$username = null;

if (isset($mybb) && is_object($mybb) && isset($mybb->user) && is_array($mybb->user)) {
    $uid = (int)($mybb->user['uid'] ?? 0);
    $username = $mybb->user['username'] ?? null;
}

JsonResponder::ok(
    [
        'uid' => $uid,
        'username' => $username,
        'ts' => time(),
    ],
    ['endpoint' => 'ping']
);

