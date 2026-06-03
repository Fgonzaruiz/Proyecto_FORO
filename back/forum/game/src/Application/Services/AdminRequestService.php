<?php
declare(strict_types=1);

namespace Game\Application\Services;

/**
 * Peticiones administrativas (Akuma aleatoria/demanda, formulario general).
 */
final class AdminRequestService
{
    /**
     * @return array{can_roll: bool, reason: string, request_id: int|null, status: string|null}
     */
    public static function characterAkumaRandomRollState(int $characterId): array
    {
        global $db;
        if (!$db->table_exists('game_admin_requests')) {
            return ['can_roll' => true, 'reason' => '', 'request_id' => null, 'status' => null];
        }
        $prefix = TABLE_PREFIX;
        $cid = (int)$characterId;
        $q = $db->query("
            SELECT id, status, title
            FROM {$prefix}game_admin_requests
            WHERE character_id = {$cid}
              AND source = 'akuma_random'
              AND status IN ('pendiente', 'aprobada')
            ORDER BY id DESC
            LIMIT 1
        ");
        $row = $db->fetch_array($q);
        if (!$row) {
            return ['can_roll' => true, 'reason' => '', 'request_id' => null, 'status' => null];
        }
        $status = (string)$row['status'];
        $reason = $status === 'pendiente'
            ? 'Ya realizaste una tirada aleatoria. Tu petición está pendiente de revisión del staff.'
            : 'Tu personaje ya obtuvo una Akuma no Mi por tirada aleatoria (aprobada).';
        return [
            'can_roll' => false,
            'reason' => $reason,
            'request_id' => (int)$row['id'],
            'status' => $status,
        ];
    }

    public static function requireActiveCharacter(int $userId): int
    {
        global $db;
        $prefix = TABLE_PREFIX;
        $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$userId} LIMIT 1");
        $cfg = $db->fetch_array($cfg_q);
        $cid = $cfg ? (int)$cfg['active_pj_id'] : 0;
        if ($cid <= 0) {
            throw new \RuntimeException('Debes tener un personaje activo.');
        }
        return $cid;
    }

    public static function notifyStaffPending(string $title, string $linkPath): void
    {
        global $db, $mybb;
        if (!function_exists('game_create_notification')) {
            return;
        }
        $prefix = TABLE_PREFIX;
        $bb = rtrim((string)($mybb->settings['bburl'] ?? ''), '/');
        $link = $bb . $linkPath;
        $staff_q = $db->query("SELECT DISTINCT user_id FROM {$prefix}game_personajes WHERE staff_level >= 2");
        while ($row = $db->fetch_array($staff_q)) {
            $staff_uid = (int)$row['user_id'];
            if ($staff_uid > 0) {
                game_create_notification($staff_uid, 'admin_request_pending', $title, '', $link);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function create(
        int $userId,
        int $characterId,
        string $source,
        string $requestKind,
        string $title,
        string $description,
        ?string $link = null,
        ?array $payload = null,
        ?int $akumaFruitId = null
    ): int {
        global $db;
        $prefix = TABLE_PREFIX;
        $titleEsc = $db->escape_string($title);
        $descEsc = $db->escape_string($description);
        $sourceEsc = $db->escape_string($source);
        $kindEsc = $db->escape_string($requestKind);
        $linkSql = $link !== null && $link !== '' ? "'" . $db->escape_string($link) . "'" : 'NULL';
        $payloadEsc = $payload !== null
            ? "'" . $db->escape_string(json_encode($payload, JSON_UNESCAPED_UNICODE)) . "'"
            : 'NULL';
        $akumaSql = $akumaFruitId !== null && $akumaFruitId > 0 ? (int)$akumaFruitId : 'NULL';

        $db->write_query("
            INSERT INTO {$prefix}game_admin_requests
            (user_id, character_id, source, request_kind, title, description, link, payload_json, akuma_fruit_id, status)
            VALUES ({$userId}, {$characterId}, '{$sourceEsc}', '{$kindEsc}', '{$titleEsc}', '{$descEsc}', {$linkSql}, {$payloadEsc}, {$akumaSql}, 'pendiente')
        ");

        return (int)$db->insert_id();
    }

    public static function reserveAkumaFruit(int $fruitId): void
    {
        global $db;
        if (!$db->table_exists('game_akuma_no_mi')) {
            throw new \RuntimeException('Catálogo de Akuma no disponible');
        }
        $prefix = TABLE_PREFIX;
        $fid = (int)$fruitId;
        $q = $db->query("SELECT id, is_occupied, is_reserved FROM {$prefix}game_akuma_no_mi WHERE id = {$fid} LIMIT 1");
        $row = $db->fetch_array($q);
        if (!$row) {
            throw new \RuntimeException('Fruta no encontrada');
        }
        $occupied = $db->field_exists('is_occupied', 'game_akuma_no_mi')
            ? (int)($row['is_occupied'] ?? 0) === 1
            : false;
        $reserved = $db->field_exists('is_reserved', 'game_akuma_no_mi')
            ? (int)($row['is_reserved'] ?? 0) === 1
            : false;
        if ($occupied) {
            throw new \RuntimeException('Esa Akuma no Mi ya está ocupada');
        }
        if ($reserved) {
            throw new \RuntimeException('Esa Akuma no Mi está reservada por otra petición');
        }
        if ($db->field_exists('is_reserved', 'game_akuma_no_mi')) {
            $db->write_query("UPDATE {$prefix}game_akuma_no_mi SET is_reserved = 1 WHERE id = {$fid}");
        }
    }

    public static function releaseAkumaReservation(int $fruitId): void
    {
        global $db;
        if (!$db->table_exists('game_akuma_no_mi') || !$db->field_exists('is_reserved', 'game_akuma_no_mi')) {
            return;
        }
        $prefix = TABLE_PREFIX;
        $fid = (int)$fruitId;
        $db->write_query("UPDATE {$prefix}game_akuma_no_mi SET is_reserved = 0 WHERE id = {$fid} AND is_occupied = 0");
    }

    public static function occupyAkumaFruit(int $fruitId): void
    {
        global $db;
        if (!$db->table_exists('game_akuma_no_mi')) {
            return;
        }
        $prefix = TABLE_PREFIX;
        $fid = (int)$fruitId;
        $sets = ["status = 'activa'"];
        if ($db->field_exists('is_occupied', 'game_akuma_no_mi')) {
            $sets[] = 'is_occupied = 1';
        }
        if ($db->field_exists('is_reserved', 'game_akuma_no_mi')) {
            $sets[] = 'is_reserved = 0';
        }
        $db->write_query('UPDATE ' . $prefix . 'game_akuma_no_mi SET ' . implode(', ', $sets) . " WHERE id = {$fid}");
    }

    public static function resolve(
        int $requestId,
        int $staffUserId,
        int $staffCharId,
        string $action,
        string $staffNota = ''
    ): array {
        global $db, $mybb;
        $prefix = TABLE_PREFIX;

        if (!in_array($action, ['aprobar', 'denegar'], true)) {
            throw new \InvalidArgumentException('Acción inválida');
        }

        $req_q = $db->query("
            SELECT r.*, p.name AS character_name, p.user_id AS player_uid
            FROM {$prefix}game_admin_requests r
            JOIN {$prefix}game_personajes p ON r.character_id = p.id
            WHERE r.id = {$requestId} LIMIT 1
        ");
        $req = $db->fetch_array($req_q);
        if (!$req) {
            throw new \RuntimeException('Petición no encontrada');
        }
        if ($req['status'] !== 'pendiente') {
            throw new \RuntimeException('La petición ya fue resuelta');
        }

        $newStatus = $action === 'aprobar' ? 'aprobada' : 'denegada';
        $notaEsc = $db->escape_string($staffNota);
        $db->write_query("
            UPDATE {$prefix}game_admin_requests
            SET status = '{$newStatus}',
                staff_nota = '{$notaEsc}',
                staff_user_id = {$staffUserId},
                staff_char_id = {$staffCharId}
            WHERE id = {$requestId}
        ");

        if (!empty($req['akuma_fruit_id'])) {
            $fid = (int)$req['akuma_fruit_id'];
            if ($action === 'aprobar') {
                self::occupyAkumaFruit($fid);
            } else {
                self::releaseAkumaReservation($fid);
            }
        }

        $playerUid = (int)$req['player_uid'];
        $characterId = (int)$req['character_id'];
        $label = $action === 'aprobar' ? 'Aprobada' : 'Denegada';
        $notifTitle = "Petición «{$req['title']}»: {$label}";
        $notifBody = $action === 'aprobar'
            ? "Tu petición administrativa ha sido aprobada por el staff."
            : "Tu petición administrativa ha sido denegada.";
        if ($staffNota !== '') {
            $notifBody .= " Nota: {$staffNota}";
        }
        $notifLink = rtrim((string)($mybb->settings['bburl'] ?? ''), '/') . '/game/public/peticiones_general.php';

        if ($staffNota !== '' && $playerUid > 0) {
            require_once MYBB_ROOT . 'inc/datahandlers/pm.php';
            $pm = [
                'subject' => "Petición administrativa: {$req['title']}",
                'message' => "Tu petición ha sido **{$label}**.\n\n**{$req['title']}**\n\nRespuesta del Staff:\n{$staffNota}",
                'touid' => $playerUid,
                'receivepms' => 1,
            ];
            if (send_pm($pm, $staffUserId, true)) {
                $pm_q = $db->query("SELECT pmid FROM {$prefix}privatemessages WHERE fromid = {$staffUserId} AND toid = {$playerUid} ORDER BY pmid DESC LIMIT 1");
                $pmid = $db->fetch_field($pm_q, 'pmid');
                if ($pmid) {
                    $notifLink = rtrim((string)($mybb->settings['bburl'] ?? ''), '/') . "/private.php?action=read&pmid={$pmid}";
                }
            }
        }

        if (function_exists('game_create_notification')) {
            game_create_notification($playerUid, 'admin_request_resolved', $notifTitle, $notifBody, $notifLink, $characterId);
        }

        return ['status' => $newStatus, 'request_id' => $requestId];
    }
}
