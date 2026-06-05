<?php
declare(strict_types=1);

namespace Game\Application\Services;

final class DirectMessageService
{
    public static function send(
        int $fromCharacterId,
        int $toCharacterId,
        string $subject,
        string $body,
        ?int $replyToId = null,
        bool $notify = true
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
        if ($body === '') {
            throw new \InvalidArgumentException('El mensaje es obligatorio.');
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

        $threadId = 0;
        if ($replyToId !== null && $replyToId > 0) {
            $parent = self::getForCharacter($replyToId, $fromCharacterId);
            if (!$parent) {
                throw new \InvalidArgumentException('No puedes responder a este mensaje.');
            }
            $threadId = (int)($parent['thread_id'] ?? $replyToId);
            if ($threadId <= 0) {
                $threadId = $replyToId;
            }
            $baseSubject = $parent['subject'] ?? $subject;
            if ($subject === '' || mb_stripos($subject, 're:') !== 0) {
                $subject = 'Re: ' . preg_replace('/^Re:\s*/i', '', $baseSubject);
            }
            $peerId = (int)$parent['from_character_id'] === $fromCharacterId
                ? (int)$parent['to_character_id']
                : (int)$parent['from_character_id'];
            if ($toCharacterId !== $peerId) {
                $toCharacterId = $peerId;
                $toQ = $db->query("SELECT id, name, user_id FROM {$prefix}game_personajes WHERE id = {$toCharacterId} LIMIT 1");
                $to = $db->fetch_array($toQ);
                if (!$to) {
                    throw new \InvalidArgumentException('Personaje destinatario no encontrado.');
                }
            }
        }

        if ($subject === '') {
            throw new \InvalidArgumentException('El asunto es obligatorio.');
        }

        $db->write_query(
            "INSERT INTO {$prefix}game_direct_messages
                (from_character_id, to_character_id, subject, body, thread_id)
             VALUES (
                {$fromCharacterId},
                {$toCharacterId},
                '{$db->escape_string($subject)}',
                '{$db->escape_string($body)}',
                {$threadId}
             )"
        );
        $dmId = (int)$db->insert_id();

        if ($threadId <= 0) {
            $db->write_query("UPDATE {$prefix}game_direct_messages SET thread_id = {$dmId} WHERE id = {$dmId}");
            $threadId = $dmId;
        }

        if ($notify) {
            $link = 'game/public/buzon.php?thread=' . $threadId;
            NotificationService::create(
                (int)$to['user_id'],
                'dm',
                'Mensaje de ' . $from['name'],
                $subject,
                $link,
                (int)$to['id']
            );
        }

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
            $peerField = 'to_name';
        } else {
            $where = "dm.to_character_id = {$characterId} AND dm.recipient_deleted = 0";
            $peerField = 'from_name';
        }

        $countQ = $db->query("
            SELECT COUNT(DISTINCT dm.thread_id) AS cnt
            FROM {$prefix}game_direct_messages dm
            WHERE {$where}
        ");
        $total = (int)$db->fetch_field($countQ, 'cnt');

        $q = $db->query("
            SELECT dm.id, dm.thread_id, dm.from_character_id, dm.to_character_id, dm.subject, dm.body,
                   dm.is_read, dm.created_at,
                   from_pj.name AS from_name,
                   to_pj.name AS to_name
            FROM {$prefix}game_direct_messages dm
            INNER JOIN (
                SELECT thread_id, MAX(id) AS max_id
                FROM {$prefix}game_direct_messages
                WHERE " . ($folder === 'sent'
                    ? "from_character_id = {$characterId} AND sender_deleted = 0"
                    : "to_character_id = {$characterId} AND recipient_deleted = 0") . "
                GROUP BY thread_id
            ) latest ON dm.id = latest.max_id
            JOIN {$prefix}game_personajes from_pj ON from_pj.id = dm.from_character_id
            JOIN {$prefix}game_personajes to_pj ON to_pj.id = dm.to_character_id
            WHERE {$where}
            ORDER BY dm.created_at DESC
            LIMIT {$offset}, {$perPage}
        ");

        $items = self::mapListRows($q, $peerField, $characterId, $folder);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
            'folder' => $folder,
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function mapListRows($q, string $peerField, int $characterId, string $folder): array
    {
        global $db;
        $items = [];
        while ($row = $db->fetch_array($q)) {
            $threadId = (int)($row['thread_id'] ?: $row['id']);
            $unread = 0;
            if ($folder === 'inbox') {
                $uq = $db->query("
                    SELECT COUNT(*) AS cnt FROM " . TABLE_PREFIX . "game_direct_messages
                    WHERE thread_id = {$threadId}
                      AND to_character_id = {$characterId}
                      AND recipient_deleted = 0
                      AND is_read = 0
                ");
                $unread = (int)$db->fetch_field($uq, 'cnt');
            }

            $items[] = [
                'id' => (int)$row['id'],
                'thread_id' => $threadId,
                'from_character_id' => (int)$row['from_character_id'],
                'to_character_id' => (int)$row['to_character_id'],
                'from_name' => $row['from_name'],
                'to_name' => $row['to_name'],
                'peer_name' => $row[$peerField],
                'subject' => $row['subject'],
                'body_preview' => mb_substr(strip_tags($row['body']), 0, 120),
                'is_read' => $unread === 0,
                'unread_count' => $unread,
                'created_at' => $row['created_at'],
            ];
        }
        return $items;
    }

    public static function getThread(int $threadId, int $characterId): ?array
    {
        global $db;
        $prefix = TABLE_PREFIX;
        $threadId = (int)$threadId;
        $characterId = (int)$characterId;

        $checkQ = $db->query("
            SELECT id FROM {$prefix}game_direct_messages
            WHERE thread_id = {$threadId}
              AND (
                (to_character_id = {$characterId} AND recipient_deleted = 0)
                OR (from_character_id = {$characterId} AND sender_deleted = 0)
              )
            LIMIT 1
        ");
        if (!$db->fetch_array($checkQ)) {
            return null;
        }

        $q = $db->query("
            SELECT dm.*, from_pj.name AS from_name, to_pj.name AS to_name
            FROM {$prefix}game_direct_messages dm
            JOIN {$prefix}game_personajes from_pj ON from_pj.id = dm.from_character_id
            JOIN {$prefix}game_personajes to_pj ON to_pj.id = dm.to_character_id
            WHERE dm.thread_id = {$threadId}
              AND (
                (dm.to_character_id = {$characterId} AND dm.recipient_deleted = 0)
                OR (dm.from_character_id = {$characterId} AND dm.sender_deleted = 0)
              )
            ORDER BY dm.created_at ASC, dm.id ASC
        ");

        $messages = [];
        $subject = '';
        $peerName = '';
        $peerCharacterId = 0;
        while ($row = $db->fetch_array($q)) {
            if ($subject === '') {
                $subject = preg_replace('/^Re:\s*/i', '', (string)$row['subject']);
            }
            $isMine = (int)$row['from_character_id'] === $characterId;
            if (!$isMine && $peerCharacterId === 0) {
                $peerCharacterId = (int)$row['from_character_id'];
                $peerName = $row['from_name'];
            } elseif ($isMine && $peerCharacterId === 0) {
                $peerCharacterId = (int)$row['to_character_id'];
                $peerName = $row['to_name'];
            }
            if ((int)$row['to_character_id'] === $characterId && !(bool)$row['is_read']) {
                $db->write_query("UPDATE {$prefix}game_direct_messages SET is_read = 1 WHERE id = " . (int)$row['id']);
            }
            $messages[] = [
                'id' => (int)$row['id'],
                'from_character_id' => (int)$row['from_character_id'],
                'to_character_id' => (int)$row['to_character_id'],
                'from_name' => $row['from_name'],
                'body' => $row['body'],
                'created_at' => $row['created_at'],
                'is_mine' => $isMine,
            ];
        }

        if ($messages === []) {
            return null;
        }

        return [
            'thread_id' => $threadId,
            'subject' => $subject,
            'peer_name' => $peerName,
            'peer_character_id' => $peerCharacterId,
            'messages' => $messages,
            'last_message_id' => (int)$messages[count($messages) - 1]['id'],
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

        $threadId = (int)($row['thread_id'] ?: $row['id']);

        return [
            'id' => (int)$row['id'],
            'thread_id' => $threadId,
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
