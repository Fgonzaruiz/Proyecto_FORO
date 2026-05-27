<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

global $db;
$prefix = TABLE_PREFIX;

$announcements = [];
if ($db->table_exists('game_announcements')) {
    $q = $db->query("
        SELECT a.id, a.title, a.content, a.created_at, u.username as author_name
        FROM {$prefix}game_announcements a
        LEFT JOIN {$prefix}users u ON a.created_by = u.uid
        WHERE a.is_active = 1
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    while ($row = $db->fetch_array($q)) {
        $announcements[] = [
            'id' => (int)$row['id'],
            'title' => htmlspecialchars($row['title']),
            'content' => htmlspecialchars($row['content']),
            'date' => date('d/m/Y', strtotime($row['created_at'])),
            'author' => htmlspecialchars($row['author_name'] ?? 'Staff')
        ];
    }
}

echo json_encode(['ok' => true, 'data' => $announcements, 'error' => null]);
exit;
