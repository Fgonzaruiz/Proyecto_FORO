<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if ((int)($mybb->user['uid'] ?? 0) === 0 || (int)($mybb->usergroup['cancp'] ?? 0) !== 1) {
    error_no_permission();
}
game_require_admin_cp();

$prefix = TABLE_PREFIX;
$bburl = $mybb->settings['bburl'];

function run_sql(string $sql, string $description): void {
    global $db;
    if ($db->write_query($sql)) {
        echo "<div class='rpg-admin-ok'>[OK] {$description}</div>";
    } else {
        echo "<div class='rpg-admin-error'>[ERROR] {$description}</div>";
    }
}

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Migración de Firma y Narradores</title>
<style>
  body { font-family: sans-serif; background: #121214; color: #e1e1e6; padding: 20px; }
  .rpg-admin-log-box { background: #1a1a1e; border: 1px solid #29292e; border-radius: 6px; padding: 15px; font-family: monospace; }
  .rpg-admin-ok { color: #04d361; margin: 4px 0; }
  .rpg-admin-warn { color: #f1c40f; margin: 4px 0; }
  .rpg-admin-error { color: #e74c3c; margin: 4px 0; }
  .rpg-admin-link { color: #a855f7; text-decoration: none; font-weight: bold; }
</style>
</head><body><h1>Migración: Sistema de Firmas y Narradores por Cuenta</h1><div class='rpg-admin-log-box'>";

// 1. Agregar columna 'firma' a game_personajes
$check_firma = $db->query("SHOW COLUMNS FROM {$prefix}game_personajes LIKE 'firma'");
if (!$db->num_rows($check_firma)) {
    run_sql(
        "ALTER TABLE {$prefix}game_personajes ADD COLUMN firma TEXT DEFAULT NULL AFTER avatar",
        "Columna 'firma' añadida a game_personajes"
    );
} else {
    echo "<div class='rpg-admin-warn'>[OK] Columna 'firma' ya existe en game_personajes</div>";
}

// 2. Agregar columna 'is_narrator' a game_user_config
$check_uc_narrator = $db->query("SHOW COLUMNS FROM {$prefix}game_user_config LIKE 'is_narrator'");
if (!$db->num_rows($check_uc_narrator)) {
    run_sql(
        "ALTER TABLE {$prefix}game_user_config ADD COLUMN is_narrator TINYINT(1) NOT NULL DEFAULT 0 AFTER active_pj_id",
        "Columna 'is_narrator' añadida a game_user_config"
    );
} else {
    echo "<div class='rpg-admin-warn'>[OK] Columna 'is_narrator' ya existe en game_user_config</div>";
}

// 3. Migrar personajes narradores existentes a la configuración de usuario
echo "<div>Migrando narradores existentes...</div>";
$narr_pjs_q = $db->query("SELECT DISTINCT user_id FROM {$prefix}game_personajes WHERE is_narrator = 1 AND user_id > 0");
$migrated_count = 0;
while ($row = $db->fetch_array($narr_pjs_q)) {
    $uid = (int)$row['user_id'];
    $db->write_query("INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used, is_narrator) 
        VALUES ({$uid}, 1, 0, 1) 
        ON DUPLICATE KEY UPDATE is_narrator = 1");
    $migrated_count++;
}
echo "<div class='rpg-admin-ok'>[OK] Se migraron {$migrated_count} usuarios al rol de Narrador por cuenta.</div>";

// 4. Migrar asignaciones de NPCs (pasar narrator_id de character_id a user_id)
// Haremos esto buscando el user_id del personaje narrador actualmente guardado en narrator_id.
echo "<div>Migrando asignaciones de NPCs...</div>";
$assignments_q = $db->query("SELECT character_id, narrator_id FROM {$prefix}game_npc_assignments");
$migrated_assignments = 0;

// Temporalmente guardamos las nuevas asignaciones
$new_assignments = [];
while ($row = $db->fetch_array($assignments_q)) {
    $npc_char_id = (int)$row['character_id'];
    $narrator_char_id = (int)$row['narrator_id'];
    
    // Buscar el propietario del personaje narrador
    $owner_q = $db->query("SELECT user_id FROM {$prefix}game_personajes WHERE id = {$narrator_char_id} LIMIT 1");
    $owner = $db->fetch_array($owner_q);
    if ($owner && $owner['user_id'] > 0) {
        $new_assignments[] = [
            'character_id' => $npc_char_id,
            'narrator_uid' => (int)$owner['user_id']
        ];
    }
}

if (!empty($new_assignments)) {
    // Vaciar y reconstruir tabla de asignaciones con user_ids
    $db->write_query("TRUNCATE TABLE {$prefix}game_npc_assignments");
    foreach ($new_assignments as $na) {
        $db->write_query("INSERT INTO {$prefix}game_npc_assignments (character_id, narrator_id) 
            VALUES ({$na['character_id']}, {$na['narrator_uid']})");
        $migrated_assignments++;
    }
}
echo "<div class='rpg-admin-ok'>[OK] Se migraron {$migrated_assignments} asignaciones de NPCs a ID de cuenta.</div>";

echo "</div><br><a href='../public/mis_personajes.php' class='rpg-admin-link'>Ir a Mis Personajes</a></body></html>";
