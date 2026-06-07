<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;
$table = "{$prefix}mycodes";

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: MyCode [spoiler] ===\n\n";

$tableExists = $db->table_exists('mycodes');
if (!$tableExists) {
    $probe = $db->query("SHOW TABLES LIKE '{$table}'");
    $tableExists = $probe && $db->num_rows($probe) > 0;
}
if (!$tableExists) {
    echo "[INFO] Tabla {$table} no encontrada en esta base de datos.\n";
    echo "       Los spoilers funcionan vía hook parse_message (game_postcharacter).\n";
    echo "       Si usas MyBB completo, verifica TABLE_PREFIX en config y que exista mybb_mycodes.\n";
    require_once __DIR__ . '/migration_helpers.php';
    game_migration_mark_applied('migrate_mycode_spoiler.php');
    echo "\n=== Fin (sin MyCode en BD; hook activo) ===\n</pre>";
    exit;
}

$title = $db->escape_string('Spoiler RPG');
$regex = $db->escape_string('\[spoiler(?:=([^\]]*))?\](.*?)\[/spoiler\]');
$replacement = $db->escape_string('<details class="rpg-spoiler"><summary class="rpg-spoiler__title">Spoiler$1</summary><div class="rpg-spoiler__body">$2</div></details>');

$exists = $db->fetch_array($db->query("SELECT cid FROM {$table} WHERE title = '{$title}' LIMIT 1"));
if ($exists) {
    $db->write_query("
        UPDATE {$table}
        SET regex = '{$regex}', replacement = '{$replacement}', active = 1
        WHERE cid = " . (int)$exists['cid']
    );
    echo "[OK] MyCode spoiler actualizado (cid {$exists['cid']}).\n";
    require_once MYBB_ROOT . 'inc/class_cache.php';
    $cache = new cachehandler();
    $cache->update('mycode');
} else {
    $db->write_query("
        INSERT INTO {$table} (title, description, regex, replacement, active, parseorder)
        VALUES ('{$title}', 'Spoiler colapsable RPG', '{$regex}', '{$replacement}', 1, 80)
    ");
    echo "[OK] MyCode [spoiler] registrado.\n";
    require_once MYBB_ROOT . 'inc/class_cache.php';
    $cache = new cachehandler();
    $cache->update('mycode');
}

echo "\n=== Fin ===\n</pre>";
