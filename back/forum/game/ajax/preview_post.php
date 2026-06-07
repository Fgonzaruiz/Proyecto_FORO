<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

header('Content-Type: application/json; charset=utf-8');

global $mybb, $db, $cache;

$input = json_decode(file_get_contents('php://input'), true);
$content = $input['content'] ?? '';

if (empty(trim($content))) {
    GameAjax::json(true, ['html' => '<p class="rpg-preview-empty">El editor está vacío.</p>']);
}

require_once MYBB_ROOT . 'inc/class_parser.php';
$parser = new postParser();

$parser_options = [
    'allow_html' => 0,
    'allow_mycode' => 1,
    'allow_smilies' => 1,
    'allow_imgcode' => 1,
    'allow_videocode' => 1,
    'filter_badwords' => 1,
    'me_username' => $mybb->user['username'] ?? '',
    'nl2br' => 1,
];

$html = $parser->parse_message($content, $parser_options);

$html = preg_replace_callback(
    '#\[spoiler(?:=([^\]]*))?\](.*?)\[/spoiler\]#si',
    function ($m) use ($parser, $parser_options) {
        $title = !empty($m[1]) ? htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') : '';
        $label = $title !== '' ? 'Spoiler: ' . $title : 'Spoiler';
        $body = $parser->parse_message($m[2], $parser_options);
        return '<details class="rpg-spoiler"><summary class="rpg-spoiler__title">' . $label . '</summary><div class="rpg-spoiler__body">' . $body . '</div></details>';
    },
    $html
);

// Fallback si el parser dejó spoilers sin transformar en HTML
if (strpos($html, '[spoiler') !== false && function_exists('game_postcharacter_parse_spoiler_bbcode')) {
    require_once MYBB_ROOT . 'inc/plugins/game_postcharacter.php';
    game_postcharacter_parse_spoiler_bbcode($html);
}

GameAjax::json(true, ['html' => $html]);
