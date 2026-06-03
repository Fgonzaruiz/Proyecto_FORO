<?php
declare(strict_types=1);

namespace Game\Infrastructure\Persistence;

/**
 * Acceso a datos de búsquedas de rol (MyBB $db).
 */
final class BusquedasRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listApproved(int $limit = 12): array
    {
        global $db, $mybb;
        $prefix = TABLE_PREFIX;
        $limit = max(1, min(50, $limit));
        $q = $db->query("
            SELECT b.id, b.titulo, b.descripcion, b.imagen_url, b.created_at,
                   pj.name as pj_name, pj.avatar as pj_avatar, pj.id as pj_id
            FROM {$prefix}game_busquedas b
            LEFT JOIN {$prefix}game_personajes pj ON b.character_id = pj.id
            WHERE b.status = 'aprobada'
            ORDER BY b.updated_at DESC
            LIMIT {$limit}
        ");

        $list = [];
        $bburl = $mybb->settings['bburl'];

        while ($row = $db->fetch_array($q)) {
            $avatar = $row['pj_avatar'];
            if ($avatar && strpos($avatar, 'http') !== 0) {
                $avatar = rtrim($bburl, '/') . '/' . ltrim($avatar, '/');
            }
            if (!$avatar) {
                $avatar = $bburl . '/images/default_avatar.png';
            }

            $list[] = [
                'id'          => (int)$row['id'],
                'titulo'      => htmlspecialchars($row['titulo']),
                'descripcion' => htmlspecialchars($row['descripcion']),
                'imagen_url'  => htmlspecialchars($row['imagen_url'] ?? ''),
                'pj_name'     => htmlspecialchars($row['pj_name'] ?? 'Desconocido'),
                'pj_avatar'   => $avatar,
                'pj_link'     => $bburl . '/game/public/personaje.php?id=' . (int)$row['pj_id'],
                'pj_id'       => (int)$row['pj_id'],
                'date'        => date('d/m/Y', strtotime($row['created_at'])),
            ];
        }

        return $list;
    }

    public function updateStatus(int $id, string $status, string $staffNota = ''): void
    {
        global $db;
        if ($id <= 0 || !in_array($status, ['aprobada', 'denegada', 'pendiente'], true)) {
            return;
        }
        $prefix = TABLE_PREFIX;
        $nota_esc = $db->escape_string($staffNota);
        $status_esc = $db->escape_string($status);
        $db->write_query("
            UPDATE {$prefix}game_busquedas
            SET status = '{$status_esc}', staff_nota = '{$nota_esc}', updated_at = NOW()
            WHERE id = {$id}
        ");
    }

    /**
     * @return array{user_id: int, titulo: string}|null
     */
    public function findOwnerMeta(int $id): ?array
    {
        global $db;
        if ($id <= 0) {
            return null;
        }
        $prefix = TABLE_PREFIX;
        $q = $db->query("SELECT user_id, titulo FROM {$prefix}game_busquedas WHERE id = {$id} LIMIT 1");
        $row = $db->fetch_array($q);
        if (!$row) {
            return null;
        }
        return [
            'user_id' => (int)$row['user_id'],
            'titulo'  => (string)$row['titulo'],
        ];
    }
}
