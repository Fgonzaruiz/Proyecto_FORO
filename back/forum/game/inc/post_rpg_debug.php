<?php
declare(strict_types=1);

if (!function_exists('game_post_rpg_debug_enabled')) {
    function game_post_rpg_debug_enabled(): bool
    {
        if (defined('GAME_DEBUG') && GAME_DEBUG) {
            return true;
        }
        if (defined('GAME_LOG_POST_RPG') && GAME_LOG_POST_RPG) {
            return true;
        }
        return (int)($_GET['debug_post_rpg'] ?? $_POST['debug_post_rpg'] ?? 0) === 1;
    }
}

if (!function_exists('game_post_rpg_modifiers_ready')) {
    function game_post_rpg_modifiers_ready(): bool
    {
        global $db;
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $ready = $db->table_exists('game_post_characters')
            && $db->field_exists('pv_change', 'game_post_characters')
            && $db->field_exists('pe_change', 'game_post_characters')
            && $db->field_exists('modifiers_json', 'game_post_characters');
        return $ready;
    }
}

if (!function_exists('game_log_post_rpg')) {
    /**
     * @param array<string, mixed> $context
     */
    function game_log_post_rpg(string $event, array $context = []): void
    {
        if (!game_post_rpg_debug_enabled()) {
            return;
        }
        $payload = array_merge(['event' => $event, 'ts' => date('c')], $context);
        $line = json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($dir . '/post_rpg_debug.log', $line, FILE_APPEND | LOCK_EX);
        error_log('[game_post_rpg] ' . trim($line));
    }
}
