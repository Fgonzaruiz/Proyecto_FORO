<?php
declare(strict_types=1);

namespace Game\Application\Services;

final class NotificationService
{
    public static function create(
        int $userId,
        string $type,
        string $title,
        string $body = '',
        string $link = '',
        ?int $characterId = null
    ): void {
        global $db;
        $prefix = TABLE_PREFIX;
        $db->write_query(
            "INSERT INTO {$prefix}game_notifications (user_id, character_id, type, title, body, link)
             VALUES ({$userId}, " . ($characterId ?: 'NULL') . ", '{$db->escape_string($type)}', '{$db->escape_string($title)}', '{$db->escape_string($body)}', '{$db->escape_string($link)}')"
        );
    }

    public static function createForActiveCharacter(
        int $userId,
        int $characterId,
        string $type,
        string $title,
        string $body = '',
        string $link = ''
    ): void {
        self::create($userId, $type, $title, $body, $link, $characterId);
    }

    public static function list(int $userId, ?int $characterId = null, int $page = 1, int $perPage = 20): array
    {
        global $db;
        $prefix = TABLE_PREFIX;
        $offset = ($page - 1) * $perPage;

        $charFilter = $characterId !== null ? "AND (character_id IS NULL OR character_id = {$characterId})" : "AND character_id IS NULL";

        $countQ = $db->query("SELECT COUNT(*) AS cnt FROM {$prefix}game_notifications WHERE user_id = {$userId} {$charFilter}");
        $total = (int)$db->fetch_field($countQ, 'cnt');

        $q = $db->query(
            "SELECT id, user_id, character_id, type, title, body, link, is_read, is_dismissed, created_at
             FROM {$prefix}game_notifications
             WHERE user_id = {$userId} {$charFilter}
             ORDER BY created_at DESC
             LIMIT {$offset}, {$perPage}"
        );

        $items = [];
        while ($row = $db->fetch_array($q)) {
            $items[] = [
                'id' => (int)$row['id'],
                'character_id' => $row['character_id'] ? (int)$row['character_id'] : null,
                'type' => $row['type'],
                'title' => $row['title'],
                'body' => $row['body'],
                'link' => $row['link'],
                'is_read' => (bool)$row['is_read'],
                'is_dismissed' => (bool)$row['is_dismissed'],
                'created_at' => $row['created_at'],
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public static function unreadCount(int $userId, ?int $characterId = null): int
    {
        global $db;
        $prefix = TABLE_PREFIX;
        
        $charFilter = $characterId !== null ? "AND (character_id IS NULL OR character_id = {$characterId})" : "AND character_id IS NULL";
        
        $q = $db->query("SELECT COUNT(*) AS cnt FROM {$prefix}game_notifications WHERE user_id = {$userId} {$charFilter} AND is_read = 0 AND is_dismissed = 0");
        return (int)$db->fetch_field($q, 'cnt');
    }

    public static function markRead(int $id, int $userId): bool
    {
        global $db;
        $prefix = TABLE_PREFIX;
        $db->write_query("UPDATE {$prefix}game_notifications SET is_read = 1 WHERE id = {$id} AND user_id = {$userId}");
        return $db->affected_rows() > 0;
    }

    public static function markAllRead(int $userId, ?int $characterId = null): void
    {
        global $db;
        $prefix = TABLE_PREFIX;
        
        $charFilter = $characterId !== null ? "AND (character_id IS NULL OR character_id = {$characterId})" : "AND character_id IS NULL";
        
        $db->write_query("UPDATE {$prefix}game_notifications SET is_read = 1 WHERE user_id = {$userId} {$charFilter} AND is_read = 0");
    }

    public static function toggleDismiss(int $id, int $userId, bool $dismissed): bool
    {
        global $db;
        $prefix = TABLE_PREFIX;
        $val = $dismissed ? 1 : 0;
        $db->write_query("UPDATE {$prefix}game_notifications SET is_dismissed = {$val} WHERE id = {$id} AND user_id = {$userId}");
        return $db->affected_rows() > 0;
    }
}
