<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/migration_helpers.php';

use Game\Shared\StatScale;

game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;
$migrationName = 'migrate_stats_v7.php';

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Stats v7 (7 atributos, rangos 1-6) ===\n\n";

if (game_migration_applied($migrationName)) {
    echo "[--] Ya aplicada.\n</pre>";
    exit;
}

$conversion = static function (int $v): int {
    $v = max(1, min(20, $v));
    return match (true) {
        $v <= 3 => 1,
        $v <= 6 => 2,
        $v <= 10 => 3,
        $v <= 14 => 4,
        $v <= 18 => 5,
        default => 6,
    };
};

$q = $db->query("SELECT id, name, stats_json, data_json, rango FROM {$prefix}game_personajes");
$count = 0;
$ambiguous = [];

while ($row = $db->fetch_array($q)) {
    $id = (int)$row['id'];
    $old = json_decode($row['stats_json'] ?? '{}', true);
    if (!is_array($old)) {
        $old = [];
    }

    $oldDes = (int)($old['des'] ?? $old['res'] ?? 5);
    $mappedDes = $conversion($oldDes);

    $statsNew = [
        'fue' => $conversion((int)($old['fue'] ?? $old['str'] ?? 5)),
        'res' => $mappedDes,
        'agi' => $conversion((int)($old['agi'] ?? 5)),
        'des' => $mappedDes,
        'int' => $conversion((int)($old['int'] ?? 5)),
        'inst' => $conversion((int)($old['inst'] ?? $old['vol'] ?? 5)),
        'esp' => $conversion((int)($old['esp'] ?? $old['vol'] ?? 5)),
    ];
    $statsNew = StatScale::sanitizeRanks($statsNew);

    if ($mappedDes >= 3) {
        $ambiguous[] = "#{$id} {$row['name']}: des viejo alto → revisar split res/des";
    }

    $data = json_decode($row['data_json'] ?? '{}', true);
    if (!is_array($data)) {
        $data = [];
    }

    $ppSpentNew = StatScale::ppSpentOnRanks($statsNew);
    $oldPurchased = (int)($data['stat_points_purchased'] ?? 0);
    $oldNivel = max(1, (int)($data['nivel'] ?? 1));
    $ppSpentOldApprox = max(0, ($oldNivel - 1) * 10 + $oldPurchased) * 4;
    $refund = max(0, $ppSpentOldApprox - $ppSpentNew);
    if ($refund > 0) {
        $data['pp'] = (int)($data['pp'] ?? 0) + $refund;
    }

    unset($data['stat_points_purchased'], $data['last_level_up_at']);
    if (empty($data['faction_rank']) && !empty($row['rango'])) {
        $data['faction_rank'] = (string)$row['rango'];
    }
    $globalRank = StatScale::globalRankFromSum(StatScale::sumRanks($statsNew));
    $data['rank'] = $globalRank;
    $data['nivel'] = StatScale::globalNivelFromRank($globalRank);
    $data['last_rank_change_at'] = date('Y-m-d H:i:s');

    $statsEsc = $db->escape_string(json_encode($statsNew, JSON_UNESCAPED_UNICODE));
    $dataEsc = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));

    $db->write_query("
        UPDATE {$prefix}game_personajes
        SET stats_json = '{$statsEsc}', data_json = '{$dataEsc}'
        WHERE id = {$id}
    ");
    $count++;
}

game_migration_mark_applied($migrationName);

echo "[OK] Personajes migrados: {$count}\n";
if ($ambiguous) {
    echo "\n[WARN] Revisión manual sugerida (des→res/des):\n";
    foreach (array_slice($ambiguous, 0, 50) as $line) {
        echo "  - {$line}\n";
    }
    if (count($ambiguous) > 50) {
        echo "  ... y " . (count($ambiguous) - 50) . " más\n";
    }
}
echo "\n=== Fin ===\n</pre>";
