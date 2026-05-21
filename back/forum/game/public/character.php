<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $header, $footer;

if (!isset($mybb) || !is_object($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$content = '<div class="game-character"><h1>' . htmlspecialchars_uni('Character') . '</h1></div>';

game_render_page('Character', $content);


