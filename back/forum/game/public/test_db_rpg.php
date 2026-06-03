<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Only allow admin or staff to prevent public exposure (MyBB check)
global $mybb, $db;
if (empty($mybb->user['uid'])) {
    die("No autorizado. Inicia sesión en el foro.");
}

// Check if user is admin/staff
$prefix = TABLE_PREFIX;
$uid = (int)$mybb->user['uid'];
$is_admin = false;
if (isset($mybb->usergroup['cancp']) && $mybb->usergroup['cancp'] == 1) {
    $is_admin = true;
} else {
    // Check game_personajes is_staff
    $q = $db->simple_select('game_personajes', 'is_staff', "user_id = {$uid}", ['limit' => 1]);
    if ($row = $db->fetch_array($q)) {
        if ($row['is_staff'] == 1) {
            $is_admin = true;
        }
    }
}

if (!$is_admin) {
    die("No tienes permisos para ver esta página.");
}

echo "<html><head><title>RPG Database Diagnostics</title>";
echo "<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f7f9fa; color: #333; padding: 20px; }
    h1, h2 { color: #2b3e50; }
    pre { background-color: #2b3e50; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 13px; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px; overflow: hidden; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
    th { background-color: #4caf50; color: white; }
    tr:hover { background-color: #f5f5f5; }
    .ok { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
</style></head><body>";

echo "<h1>RPG System Diagnostics</h1>";

// 1. Table schema
echo "<h2>1. Schema of game_post_characters</h2>";
if ($db->table_exists('game_post_characters')) {
    echo "<span class='ok'>[OK] Tabla 'game_post_characters' existe.</span>";
    
    // Get fields
    $fields = $db->show_fields_from('game_post_characters');
    echo "<table><tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($fields as $f) {
        echo "<tr>";
        echo "<td><b>{$f['Field']}</b></td>";
        echo "<td>{$f['Type']}</td>";
        echo "<td>{$f['Null']}</td>";
        echo "<td>{$f['Key']}</td>";
        echo "<td>" . ($f['Default'] === null ? 'NULL' : htmlspecialchars((string)$f['Default'])) . "</td>";
        echo "<td>{$f['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<span class='error'>[ERROR] La tabla 'game_post_characters' NO existe.</span>";
}

// 2. Query last 10 posts in game_post_characters
echo "<h2>2. Últimos 10 registros en game_post_characters</h2>";
if ($db->table_exists('game_post_characters')) {
    $q = $db->query("
        SELECT pc.*, p.name as char_name, u.username 
        FROM {$prefix}game_post_characters pc
        LEFT JOIN {$prefix}game_personajes p ON pc.character_id = p.id
        LEFT JOIN {$prefix}users u ON pc.user_id = u.uid
        ORDER BY pc.post_id DESC 
        LIMIT 10
    ");
    
    if ($db->num_rows($q) > 0) {
        echo "<table><tr><th>Post ID</th><th>Thread ID</th><th>User</th><th>Personaje</th><th>PV Change</th><th>PE Change</th><th>Modifiers JSON</th></tr>";
        while ($row = $db->fetch_array($q)) {
            echo "<tr>";
            echo "<td>{$row['post_id']}</td>";
            echo "<td>{$row['thread_id']}</td>";
            echo "<td>{$row['username']} (UID: {$row['user_id']})</td>";
            echo "<td>{$row['char_name']} (CID: {$row['character_id']})</td>";
            echo "<td>{$row['pv_change']}</td>";
            echo "<td>{$row['pe_change']}</td>";
            echo "<td><pre style='margin:0; padding:5px; font-size:11px;'>" . htmlspecialchars($row['modifiers_json'] ?? 'NULL') . "</pre></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No hay registros en la tabla.";
    }
}

// 3. Read post_debug.log
echo "<h2>3. Logs de peticiones POST (últimos 1500 caracteres)</h2>";
$logPath = __DIR__ . '/../../post_debug.log';
if (file_exists($logPath)) {
    $size = filesize($logPath);
    $fp = fopen($logPath, 'r');
    if ($size > 1500) {
        fseek($fp, -1500, SEEK_END);
    }
    $data = fread($fp, 1500);
    fclose($fp);
    echo "<pre>" . htmlspecialchars($data) . "</pre>";
} else {
    echo "El archivo post_debug.log aún no se ha creado. Realiza un post para generar logs.";
}

echo "</body></html>";
