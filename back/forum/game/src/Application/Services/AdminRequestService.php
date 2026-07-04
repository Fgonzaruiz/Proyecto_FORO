<?php
declare(strict_types=1);

namespace Game\Application\Services;

use Game\Application\Services\NenService;

/**
 * Peticiones administrativas (Akuma aleatoria/demanda, formulario general).
 */
final class AdminRequestService
{
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
        ?array $payload = null
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

        $db->write_query("
            INSERT INTO {$prefix}game_admin_requests
            (user_id, character_id, source, request_kind, title, description, link, payload_json, status)
            VALUES ({$userId}, {$characterId}, '{$sourceEsc}', '{$kindEsc}', '{$titleEsc}', '{$descEsc}', {$linkSql}, {$payloadEsc}, 'pendiente')
        ");

        return (int)$db->insert_id();
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

        // Hook for mission review
        if ($req['source'] === 'mision' || $req['request_kind'] === 'mision_review') {
            $payload = !empty($req['payload_json']) ? json_decode($req['payload_json'], true) : [];
            $activeMissionId = (int)($payload['active_mission_id'] ?? 0);
            if ($activeMissionId > 0) {
                if ($action === 'aprobar') {
                    // Update active mission to completed
                    $db->write_query("UPDATE {$prefix}game_missions_active SET status = 'completed', completed_at = NOW() WHERE id = {$activeMissionId}");
                    
                    // Fetch rewards
                    $mQ = $db->query("
                        SELECT ma.id, m.points_reward, m.berry_reward, m.title
                        FROM {$prefix}game_missions_active ma
                        JOIN {$prefix}game_missions m ON ma.mission_id = m.id
                        WHERE ma.id = {$activeMissionId} LIMIT 1
                    ");
                    if ($missionInfo = $db->fetch_array($mQ)) {
                        $points = (int)$missionInfo['points_reward'];
                        $berries = (int)$missionInfo['berry_reward'];
                        
                        // Fetch confirmed participants
                        $partQ = $db->query("
                            SELECT character_id, user_id FROM {$prefix}game_mission_participants
                            WHERE active_mission_id = {$activeMissionId} AND confirmed = 1
                        ");
                        while ($pRow = $db->fetch_array($partQ)) {
                            $cId = (int)$pRow['character_id'];
                            $pUid = (int)$pRow['user_id'];
                            
                            // Award PD and Jenny
                            $db->write_query("
                                UPDATE {$prefix}game_personajes
                                SET puntos_destino = puntos_destino + {$points},
                                    jenny = jenny + {$berries}
                                WHERE id = {$cId}
                            ");

                            // Send individual notification to companions (since leader gets the request status notification)
                            if ($cId !== (int)$req['character_id']) {
                                try {
                                    if (function_exists('game_create_notification')) {
                                        $bb = rtrim((string)($mybb->settings['bburl'] ?? ''), '/');
                                        game_create_notification(
                                            $pUid,
                                            'system',
                                            "Recompensa de Misión Aprobada",
                                            "Se han otorgado {$points} PD y {$berries} Jenny por la misión '{$missionInfo['title']}'.",
                                            $bb . "/game/public/personaje.php?pj={$cId}",
                                            $cId
                                        );
                                    }
                                } catch (\Throwable $e) {}
                            }
                        }
                    }
                } else {
                    // Revert active mission status to failed / cancelled
                    $db->write_query("UPDATE {$prefix}game_missions_active SET status = 'failed' WHERE id = {$activeMissionId}");
                }
            }
        }

        // Hook para peticiones de Nen
        $requestKind = $req['request_kind'];
        if (in_array($requestKind, ['nen_despertar', 'nen_taza', 'nen_entrenamiento', 'nen_hatsu'], true)) {
            $payload = !empty($req['payload_json']) ? json_decode($req['payload_json'], true) : [];
            $nenService = new NenService();
            if ($action === 'aprobar') {
                if ($requestKind === 'nen_despertar') {
                    $nenService->despertarNen($characterId);
                } elseif ($requestKind === 'nen_taza') {
                    $nenType = (string)($payload['nen_type'] ?? '');
                    $nenService->setNenType($characterId, $nenType);
                } elseif ($requestKind === 'nen_entrenamiento') {
                    $principle = (string)($payload['principle'] ?? '');
                    $level = (int)($payload['level'] ?? 1);
                    $nenService->trainPrinciple($characterId, $principle, $level);
                } elseif ($requestKind === 'nen_hatsu') {
                    $abilityId = (int)($payload['ability_id'] ?? 0);
                    $cardId = (int)($payload['card_id'] ?? 0);
                    $nenService->aprobarHabilidad($abilityId, $cardId);
                }
            } else {
                // Denegación
                if ($requestKind === 'nen_hatsu') {
                    $abilityId = (int)($payload['ability_id'] ?? 0);
                    $nenService->rechazarHabilidad($abilityId);
                }
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

        if ($staffNota !== '' && $playerUid > 0 && $staffCharId > 0 && $characterId > 0) {
            try {
                $dmId = DirectMessageService::send(
                    $staffCharId,
                    $characterId,
                    "Petición administrativa: {$req['title']}",
                    "Tu petición ha sido {$label}.\n\n{$req['title']}\n\nRespuesta del Staff:\n{$staffNota}"
                );
                $notifLink = rtrim((string)($mybb->settings['bburl'] ?? ''), '/') . '/game/public/buzon.php?read=' . $dmId;
            } catch (\Throwable $e) {
                $notifLink = rtrim((string)($mybb->settings['bburl'] ?? ''), '/') . '/game/public/buzon.php';
            }
        }

        if ($staffNota === '' && function_exists('game_create_notification')) {
            game_create_notification($playerUid, 'system', $notifTitle, $notifBody, $notifLink, $characterId);
        }

        return ['status' => $newStatus, 'request_id' => $requestId];
    }
}
