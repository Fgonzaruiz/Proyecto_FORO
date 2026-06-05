<?php
declare(strict_types=1);

namespace Game\Application\Services;

final class DirectMessageService
{
    public static function send(
        int $fromCharacterId,
        int $toCharacterId,
        string $subject,
        string $body
    ): int {
        global $db;
        $prefix = TABLE_PREFIX;

        if ($fromCharacterId <= 0 || $toCharacterId <= 0) {
            throw new \InvalidArgumentException('Personajes inválidos.');
        }
        if ($fromCharacterId === $toCharacterId) {
            throw new \InvalidArgumentException('No puedes enviarte un mensaje a ti mismo.');
        }

        $subject = trim($subject);
        $body = trim($body);
        if ($subject === '' || $body === '') {
            throw new \InvalidArgumentException('Asunto y mensaje son obligatorios.');
        }

        $fromQ = $db->query("SELECT id, name, user_id FROM {$prefix}game_personajes WHERE id = {$fromCharacterId} LIMIT 1");
        $from = $db->fetch_array($fromQ);
        if (!$from) {
            throw new \InvalidArgumentException('Personaje remitente no encontrado.');
        }

        $toQ = $db->query("SELECT id, name, user_id FROM {$prefix}game_personajes WHERE id = {$toCharacterId} LIMIT 1");
        $to = $db->fetch_array($toQ);
        if (!$to) {
            throw new \InvalidArgumentException('Personaje destinatario no encontrado.');
        }

        $db->write_query(
            "INSERT INTO {$prefix}game_direct_messages
                (from_character_id, to_character_id, subject, body)
             VALUES (
                {$fromCharacterId},
                {$toCharacterId},
                '{$db->escape_string($subject)}',
                '{$db->escape_string($body)}'
             )"
        );
        $dmId = (int)$db->insert_id();

        $link = 'game/public/buzon.php?read=' . $dmId;
        $notifTitle = 'Mensaje de ' . $from['name'];
        $notifBody = $subject;
        NotificationService::create(
            (int)$to['user_id'],
            'dm',
            $notifTitle,
            $notifBody,
            $link,
            (int)$to['id']
        );

        return $dmId;
    }

    public static function listInbox(int $characterId, int $page = 1, int $perPage = 20): array
    {
        return self::listFolder($characterId, 'inbox', $page, $perPage);
    }

    public static function listSent(int $characterId, int $page = 1, int $perPage = 20): array
    {
        return self::listFolder($characterId, 'sent', $page, $perPage);
    }

    private static function listFolder(int $characterId, string $folder, int $page, int $perPage): array
    {
        global $db;
        $prefix = TABLE_PREFIX;
        $characterId = (int)$characterId;
        $perPage = max(1, min(50, $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        if ($folder === 'sent') {
            $where = "dm.from_character_id = {$characterId} AND dm.sender_deleted = 0";
            $joinChar = 'to_pj';
            $joinId = 'dm.to_character_id';
            $peerField = 'to_name';
        } else {
            $where = "dm.to_character_id = {$characterId} AND dm.recipient_deleted = 0";
            $joinChar = 'from_pj';
            $joinId = 'dm.from_character_id';
            $peerField = 'from_name';
        }

        $countQ = $db->query("SELECT COUNT(*) AS cnt FROM {$prefix}game_direct_messages dm WHERE {$where}");
        $total = (int)$db->fetch_field($countQ, 'cnt');

        $q = $db->query("
            SELECT dm.id, dm.from_character_id, dm.to_character_id, dm.subject, dm.body,
                   dm.is_read, dm.created_at,
                   from_pj.name AS from_name,
                   to_pj.name AS to_name
            FROM {$prefix}game_direct_messages dm
            JOIN {$prefix}game_personajes from_pj ON from_pj.id = dm.from_character_id
            JOIN {$prefix}game_personajes to_pj ON to_pj.id = dm.to_character_id
            WHERE {$where}
            ORDER BY dm.created_at DESC
            LIMIT {$offset}, {$perPage}
        ");

        $items = [];
        while ($row = $db->fetch_array($q)) {
            $items[] = [
                'id' => (int)$row['id'],
                'from_character_id' => (int)$row['from_character_id'],
                'to_character_id' => (int)$row['to_character_id'],
                'from_name' => $row['from_name'],
                'to_name' => $row['to_name'],
                'peer_name' => $row[$peerField],
                'subject' => $row['subject'],
                'body_preview' => mb_substr(strip_tags($row['body']), 0, 120),
                'is_read' => (bool)$row['is_read'],
                'created_at' => $row['created_at'],
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
            'folder' => $folder,
        ];
    }

    public static function getForCharacter(int $messageId, int $characterId): ?array
    {
        global $db;
        $prefix = TABLE_PREFIX;
        $messageId = (int)$messageId;
        $characterId = (int)$characterId;

        $q = $db->query("
            SELECT dm.*, from_pj.name AS from_name, to_pj.name AS to_name
            FROM {$prefix}game_direct_messages dm
            JOIN {$prefix}game_personajes from_pj ON from_pj.id = dm.from_character_id
            JOIN {$prefix}game_personajes to_pj ON to_pj.id = dm.to_character_id
            WHERE dm.id = {$messageId}
              AND (
                (dm.to_character_id = {$characterId} AND dm.recipient_deleted = 0)
                OR (dm.from_character_id = {$characterId} AND dm.sender_deleted = 0)
              )
            LIMIT 1
        ");
        $row = $db->fetch_array($q);
        if (!$row) {
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'from_character_id' => (int)$row['from_character_id'],
            'to_character_id' => (int)$row['to_character_id'],
            'from_name' => $row['from_name'],
            'to_name' => $row['to_name'],
            'subject' => $row['subject'],
            'body' => $row['body'],
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at'],
            'is_inbox' => (int)$row['to_character_id'] === $characterId,
        ];
    }

    public static function markRead(int $messageId, int $characterId): bool
    {
        global $db;
        $prefix = TABLE_PREFIX;
        $db->write_query(
            "UPDATE {$prefix}game_direct_messages
             SET is_read = 1
             WHERE id = " . (int)$messageId . "
               AND to_character_id = " . (int)$characterId . "
               AND recipient_deleted = 0"
        );
        return $db->affected_rows() > 0;
    }

    public static function unreadCount(int $characterId): int
    {
        global $db;
        $prefix = TABLE_PREFIX;
        $q = $db->query(
            "SELECT COUNT(*) AS cnt FROM {$prefix}game_direct_messages
             WHERE to_character_id = " . (int)$characterId . "
               AND recipient_deleted = 0
               AND is_read = 0"
        );
        return (int)$db->fetch_field($q, 'cnt');
    }

    public static function delete(int $messageId, int $characterId): bool
    {
        global $db;
        $prefix = TABLE_PREFIX;
        $messageId = (int)$messageId;
        $characterId = (int)$characterId;

        $q = $db->query("SELECT from_character_id, to_character_id FROM {$prefix}game_direct_messages WHERE id = {$messageId} LIMIT 1");
        $row = $db->fetch_array($q);
        if (!$row) {
            return false;
        }

        if ((int)$row['to_character_id'] === $characterId) {
            $db->write_query("UPDATE {$prefix}game_direct_messages SET recipient_deleted = 1 WHERE id = {$messageId}");
            return $db->affected_rows() > 0;
        }
        if ((int)$row['from_character_id'] === $characterId) {
            $db->write_query("UPDATE {$prefix}game_direct_messages SET sender_deleted = 1 WHERE id = {$messageId}");
            return $db->affected_rows() > 0;
        }
        return false;
    }

    /** @return list<array{id:int,name:string}> */
    public static function searchCharacters(string $query, int $excludeCharacterId = 0, int $limit = 20): array
    {
        global $db;
        $prefix = TABLE_PREFIX;
        $limit = max(1, min(30, $limit));
        $exclude = $excludeCharacterId > 0 ? " AND id != {$excludeCharacterId}" : '';

        if (trim($query) === '') {
            $sql = "SELECT id, name FROM {$prefix}game_personajes WHERE 1=1{$exclude} ORDER BY name ASC LIMIT {$limit}";
        } else {
            $esc = $db->escape_string(trim($query));
            $sql = "SELECT id, name FROM {$prefix}game_personajes WHERE name LIKE '%{$esc}%'{$exclude} ORDER BY name ASC LIMIT {$limit}";
        }

        $q = $db->query($sql);
        $chars = [];
        while ($row = $db->fetch_array($q)) {
            $chars[] = ['id' => (int)$row['id'], 'name' => $row['name']];
        }
        return $chars;
    }
}
