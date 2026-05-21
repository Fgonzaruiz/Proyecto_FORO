<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Presentation\Api\JsonResponder;

global $mybb;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    JsonResponder::fail(401, ['code' => 'unauthorized', 'message' => 'Login required'], ['endpoint' => 'staff_award_xp']);
    exit;
}

// Placeholder: aquí se verificará permiso staff y se delegará al backend de mecánicas.
JsonResponder::fail(501, ['code' => 'not_implemented', 'message' => 'Not implemented yet'], ['endpoint' => 'staff_award_xp', 'uid' => $uid]);

