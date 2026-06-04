<?php
declare(strict_types=1);

namespace Game\Application\Services;

/**
 * Moderación de cuentas de foro (nivel staff 3).
 */
final class StaffAccountService
{
    private object $db;
    private string $prefix;
    private int $actorUid;

    public function __construct(object $db, string $prefix, int $actorUid)
    {
        $this->db = $db;
        $this->prefix = $prefix;
        $this->actorUid = $actorUid;
    }

    public function findUserByQuery(string $query): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        if (ctype_digit($query)) {
            $uid = (int)$query;
            $u_q = $this->db->query("SELECT uid, username, usergroup, email, regdate, postnum, threadnum,
                suspendposting, moderateposts
                FROM {$this->prefix}users WHERE uid = {$uid} LIMIT 1");
            return $this->db->fetch_array($u_q) ?: null;
        }

        $esc = $this->db->escape_string($query);
        $u_q = $this->db->query("SELECT uid, username, usergroup, email, regdate, postnum, threadnum,
            suspendposting, moderateposts
            FROM {$this->prefix}users
            WHERE username = '{$esc}' LIMIT 1");
        $row = $this->db->fetch_array($u_q);
        if ($row) {
            return $row;
        }

        $u_q = $this->db->query("SELECT uid, username, usergroup, email, regdate, postnum, threadnum,
            suspendposting, moderateposts
            FROM {$this->prefix}users
            WHERE username LIKE '{$esc}%' ORDER BY username ASC LIMIT 1");
        return $this->db->fetch_array($u_q) ?: null;
    }

    /** @return array<string, mixed> */
    public function getAccountDetails(int $targetUid): array
    {
        $this->assertCanManage($targetUid);

        $u_q = $this->db->query("SELECT uid, username, usergroup, email, regdate, postnum, threadnum,
            suspendposting, moderateposts
            FROM {$this->prefix}users WHERE uid = {$targetUid} LIMIT 1");
        $user = $this->db->fetch_array($u_q);
        if (!$user) {
            throw new \InvalidArgumentException('Usuario no encontrado.');
        }

        $ban = $this->getBanRow($targetUid);
        $isBanned = $ban !== null;

        $cfg_q = $this->db->query("SELECT max_slots, slots_used, active_pj_id, is_narrator
            FROM {$this->prefix}game_user_config WHERE user_id = {$targetUid} LIMIT 1");
        $cfg = $this->db->fetch_array($cfg_q);
        if (!$cfg) {
            $cfg = ['max_slots' => 1, 'slots_used' => 0, 'active_pj_id' => null, 'is_narrator' => 0];
        }

        $actualSlots = (int)$this->db->fetch_field(
            $this->db->query("SELECT COUNT(*) AS cnt FROM {$this->prefix}game_personajes
                WHERE user_id = {$targetUid} AND is_npc = 0"),
            'cnt'
        );

        $chars = [];
        $c_q = $this->db->query("SELECT id, name, status, staff_level, is_staff, faction, rango
            FROM {$this->prefix}game_personajes
            WHERE user_id = {$targetUid} AND is_npc = 0
            ORDER BY name ASC");
        while ($row = $this->db->fetch_array($c_q)) {
            $chars[] = $row;
        }

        $npcs = [];
        $n_q = $this->db->query("SELECT id, name, faction FROM {$this->prefix}game_personajes
            WHERE is_npc = 1 ORDER BY name ASC");
        while ($row = $this->db->fetch_array($n_q)) {
            $npcs[] = $row;
        }

        $assigned = [];
        $a_q = $this->db->query("SELECT character_id FROM {$this->prefix}game_npc_assignments
            WHERE narrator_id = {$targetUid}");
        while ($row = $this->db->fetch_array($a_q)) {
            $assigned[] = (int)$row['character_id'];
        }

        $maxStaff = (int)$this->db->fetch_field(
            $this->db->query("SELECT MAX(staff_level) AS m FROM {$this->prefix}game_personajes
                WHERE user_id = {$targetUid} AND is_npc = 0"),
            'm'
        );

        return [
            'user' => [
                'uid' => (int)$user['uid'],
                'username' => $user['username'],
                'email' => $user['email'],
                'regdate' => (int)$user['regdate'],
                'postnum' => (int)$user['postnum'],
                'threadnum' => (int)$user['threadnum'],
                'suspendposting' => (int)$user['suspendposting'] === 1,
                'moderateposts' => (int)$user['moderateposts'] === 1,
            ],
            'ban' => $ban ? [
                'reason' => $ban['reason'] ?? '',
                'dateline' => (int)($ban['dateline'] ?? 0),
                'lifted' => (int)($ban['lifted'] ?? 0),
            ] : null,
            'is_banned' => $isBanned,
            'config' => [
                'max_slots' => (int)$cfg['max_slots'],
                'slots_used' => $actualSlots,
                'slots_used_stored' => (int)$cfg['slots_used'],
                'active_pj_id' => $cfg['active_pj_id'] ? (int)$cfg['active_pj_id'] : null,
                'is_narrator' => (int)($cfg['is_narrator'] ?? 0) === 1,
            ],
            'characters' => $chars,
            'npcs' => $npcs,
            'assigned_npc_ids' => $assigned,
            'max_staff_level' => $maxStaff,
        ];
    }

    public function banUser(int $targetUid, string $reason): void
    {
        $this->assertCanManage($targetUid);
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Indica un motivo de baneo.');
        }
        if ($this->getBanRow($targetUid) !== null) {
            throw new \InvalidArgumentException('Este usuario ya está baneado.');
        }

        $gid = $this->getBannedGroupId();
        $u_q = $this->db->query("SELECT uid, username, usergroup, additionalgroups, displaygroup
            FROM {$this->prefix}users WHERE uid = {$targetUid} LIMIT 1");
        $user = $this->db->fetch_array($u_q);
        if (!$user) {
            throw new \InvalidArgumentException('Usuario no encontrado.');
        }

        $reasonEsc = $this->db->escape_string(my_substr($reason, 0, 255));
        $this->db->write_query("INSERT INTO {$this->prefix}banned
            (uid, gid, oldgroup, oldadditionalgroups, olddisplaygroup, admin, dateline, bantime, lifted, reason)
            VALUES (
                {$targetUid},
                {$gid},
                " . (int)$user['usergroup'] . ",
                '" . $this->db->escape_string((string)$user['additionalgroups']) . "',
                " . (int)$user['displaygroup'] . ",
                {$this->actorUid},
                " . TIME_NOW . ",
                '---',
                0,
                '{$reasonEsc}'
            )");

        $this->db->write_query("UPDATE {$this->prefix}users SET
            usergroup = {$gid},
            displaygroup = 0,
            additionalgroups = ''
            WHERE uid = {$targetUid} LIMIT 1");

        game_log_action('staff_ban_user', ['actor' => $this->actorUid, 'target' => $targetUid]);
    }

    public function unbanUser(int $targetUid): void
    {
        $this->assertCanManage($targetUid);
        $ban = $this->getBanRow($targetUid);
        if ($ban === null) {
            throw new \InvalidArgumentException('Este usuario no tiene un baneo activo.');
        }

        $this->db->write_query("UPDATE {$this->prefix}users SET
            usergroup = " . (int)$ban['oldgroup'] . ",
            additionalgroups = '" . $this->db->escape_string((string)$ban['oldadditionalgroups']) . "',
            displaygroup = " . (int)$ban['olddisplaygroup'] . "
            WHERE uid = {$targetUid} LIMIT 1");
        $this->db->write_query("DELETE FROM {$this->prefix}banned WHERE uid = {$targetUid} LIMIT 1");

        game_log_action('staff_unban_user', ['actor' => $this->actorUid, 'target' => $targetUid]);
    }

    public function setNarrator(int $targetUid, bool $enabled): void
    {
        $this->assertCanManage($targetUid);
        $val = $enabled ? 1 : 0;
        $this->db->write_query("INSERT INTO {$this->prefix}game_user_config
            (user_id, max_slots, slots_used, is_narrator)
            VALUES ({$targetUid}, 1, 0, {$val})
            ON DUPLICATE KEY UPDATE is_narrator = {$val}");
        if (!$enabled) {
            $this->db->write_query("DELETE FROM {$this->prefix}game_npc_assignments
                WHERE narrator_id = {$targetUid}");
        }
        game_log_action('staff_set_narrator', ['actor' => $this->actorUid, 'target' => $targetUid, 'val' => $val]);
    }

    public function setMaxSlots(int $targetUid, int $maxSlots): void
    {
        $this->assertCanManage($targetUid);
        $maxSlots = max(1, min(20, $maxSlots));

        $used = (int)$this->db->fetch_field(
            $this->db->query("SELECT COUNT(*) AS cnt FROM {$this->prefix}game_personajes
                WHERE user_id = {$targetUid} AND is_npc = 0"),
            'cnt'
        );
        if ($maxSlots < $used) {
            throw new \InvalidArgumentException(
                "No puedes bajar de {$used} slots: el usuario ya tiene {$used} personaje(s)."
            );
        }

        $this->db->write_query("INSERT INTO {$this->prefix}game_user_config
            (user_id, max_slots, slots_used, is_narrator)
            VALUES ({$targetUid}, {$maxSlots}, {$used}, 0)
            ON DUPLICATE KEY UPDATE max_slots = {$maxSlots}, slots_used = {$used}");

        game_log_action('staff_set_max_slots', ['actor' => $this->actorUid, 'target' => $targetUid, 'max' => $maxSlots]);
    }

    /** @param list<int> $npcIds */
    public function saveNpcAssignments(int $targetUid, array $npcIds): void
    {
        $this->assertCanManage($targetUid);
        $cfg_q = $this->db->query("SELECT is_narrator FROM {$this->prefix}game_user_config
            WHERE user_id = {$targetUid} LIMIT 1");
        $cfg = $this->db->fetch_array($cfg_q);
        if (!$cfg || (int)$cfg['is_narrator'] !== 1) {
            throw new \InvalidArgumentException('La cuenta no tiene permiso de narrador.');
        }

        $this->db->write_query("DELETE FROM {$this->prefix}game_npc_assignments WHERE narrator_id = {$targetUid}");
        foreach ($npcIds as $npcId) {
            $npcId = (int)$npcId;
            if ($npcId <= 0) {
                continue;
            }
            $check = $this->db->query("SELECT id FROM {$this->prefix}game_personajes
                WHERE id = {$npcId} AND is_npc = 1 LIMIT 1");
            if ($this->db->fetch_array($check)) {
                $this->db->write_query("INSERT INTO {$this->prefix}game_npc_assignments
                    (character_id, narrator_id) VALUES ({$npcId}, {$targetUid})");
            }
        }
        game_log_action('staff_npc_assign', ['actor' => $this->actorUid, 'target' => $targetUid, 'count' => count($npcIds)]);
    }

    public function setSuspendPosting(int $targetUid, bool $enabled): void
    {
        $this->assertCanManage($targetUid);
        $val = $enabled ? 1 : 0;
        $this->db->write_query("UPDATE {$this->prefix}users SET suspendposting = {$val} WHERE uid = {$targetUid} LIMIT 1");
        game_log_action('staff_suspend_posting', ['actor' => $this->actorUid, 'target' => $targetUid, 'val' => $val]);
    }

    public function setModeratePosts(int $targetUid, bool $enabled): void
    {
        $this->assertCanManage($targetUid);
        $val = $enabled ? 1 : 0;
        $this->db->write_query("UPDATE {$this->prefix}users SET moderateposts = {$val} WHERE uid = {$targetUid} LIMIT 1");
        game_log_action('staff_moderate_posts', ['actor' => $this->actorUid, 'target' => $targetUid, 'val' => $val]);
    }

    public function clearActiveCharacter(int $targetUid): void
    {
        $this->assertCanManage($targetUid);
        $this->db->write_query("UPDATE {$this->prefix}game_user_config SET active_pj_id = NULL
            WHERE user_id = {$targetUid} LIMIT 1");
        game_log_action('staff_clear_active_pj', ['actor' => $this->actorUid, 'target' => $targetUid]);
    }

    public function syncSlotsUsed(int $targetUid): void
    {
        $this->assertCanManage($targetUid);
        $used = (int)$this->db->fetch_field(
            $this->db->query("SELECT COUNT(*) AS cnt FROM {$this->prefix}game_personajes
                WHERE user_id = {$targetUid} AND is_npc = 0"),
            'cnt'
        );
        $this->db->write_query("INSERT INTO {$this->prefix}game_user_config
            (user_id, max_slots, slots_used, is_narrator)
            VALUES ({$targetUid}, 1, {$used}, 0)
            ON DUPLICATE KEY UPDATE slots_used = {$used}");
    }

    private function assertCanManage(int $targetUid): void
    {
        if ($targetUid <= 0) {
            throw new \InvalidArgumentException('Usuario inválido.');
        }
        if ($targetUid === $this->actorUid) {
            throw new \InvalidArgumentException('No puedes aplicar esta acción sobre tu propia cuenta.');
        }

        $adminCnt = (int)$this->db->fetch_field(
            $this->db->query("SELECT COUNT(*) AS cnt FROM {$this->prefix}game_personajes
                WHERE user_id = {$targetUid} AND is_npc = 0 AND staff_level >= 3"),
            'cnt'
        );
        if ($adminCnt > 0) {
            throw new \InvalidArgumentException('No puedes moderar una cuenta con personaje administrador (nivel 3).');
        }
    }

    private function getBannedGroupId(): int
    {
        $q = $this->db->query("SELECT gid FROM {$this->prefix}usergroups WHERE isbannedgroup = 1 ORDER BY gid ASC LIMIT 1");
        $gid = (int)$this->db->fetch_field($q, 'gid');
        if ($gid <= 0) {
            throw new \RuntimeException('No hay grupo de baneados configurado en el foro.');
        }
        return $gid;
    }

    /** @return array<string, mixed>|null */
    private function getBanRow(int $uid): ?array
    {
        $q = $this->db->query("SELECT * FROM {$this->prefix}banned WHERE uid = {$uid} LIMIT 1");
        $row = $this->db->fetch_array($q);
        return $row ?: null;
    }
}
