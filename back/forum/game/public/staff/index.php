<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

global $mybb, $header, $footer;

if (!isset($mybb) || !is_object($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../../member.php?action=login');
    exit;
}

// Placeholder: aquí se verificará permiso staff (grupo, usergroup adicional, etc.)
$content = '<div class="game-staff"><h1>' . htmlspecialchars_uni('Staff') . '</h1></div>';

echo ($header ?? '');
echo $content;
echo ($footer ?? '');

