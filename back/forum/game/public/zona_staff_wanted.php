<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if (!isset($mybb) || !is_object($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$uid = (int)$mybb->user['uid'];
$prefix = TABLE_PREFIX;

$pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE user_id = {$uid} AND id = (SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1) LIMIT 1");
$pj = $db->fetch_array($pj_q);

if (!$pj || (int)$pj['staff_level'] < 3) {
    echo "Acceso denegado. Se requiere nivel de Administrador.";
    exit;
}

// Procesar Formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = $db->escape_string($_POST['name'] ?? '');
        $epithet = $db->escape_string($_POST['epithet'] ?? '');
        $bounty = (int)($_POST['bounty'] ?? 0);
        $image_url = $db->escape_string($_POST['image_url'] ?? '');
        $reason = $db->escape_string($_POST['reason'] ?? '');
        $entity_id = (int)($_POST['entity_id'] ?? 0);
        
        if ($id > 0) {
            $db->query("UPDATE {$prefix}game_wanted SET name='{$name}', epithet='{$epithet}', bounty={$bounty}, image_url='{$image_url}', reason='{$reason}', entity_id={$entity_id} WHERE id={$id}");
        } else {
            $db->query("INSERT INTO {$prefix}game_wanted (name, epithet, bounty, image_url, reason, status, entity_id, type) VALUES ('{$name}', '{$epithet}', {$bounty}, '{$image_url}', '{$reason}', 'active', {$entity_id}, 'pj')");
        }
        
        // Actualizar ficha del personaje si se ha seleccionado uno
        if ($entity_id > 0) {
            $bountyFormatted = number_format((float)$bounty, 0, ',', '.') . ' Berries';
            $db->query("UPDATE {$prefix}game_personajes SET recompensa='{$bountyFormatted}' WHERE id={$entity_id}");
        }
        
        header("Location: zona_staff_wanted.php?msg=saved");
        exit;
    }
    if ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->query("DELETE FROM {$prefix}game_wanted WHERE id={$id}");
        header("Location: zona_staff_wanted.php?msg=deleted");
        exit;
    }
}

// Cargar lista de wanteds
$wanteds = [];
$q = $db->query("SELECT * FROM {$prefix}game_wanted ORDER BY bounty DESC");
while ($w = $db->fetch_array($q)) {
    $wanteds[] = $w;
}

// Cargar personajes aprobados y NPCs para asociar
$pjs = [];
$pj_query = $db->query("SELECT id, name, is_npc FROM {$prefix}game_personajes WHERE status='aprobada' OR is_npc = 1 ORDER BY name ASC");
while ($p = $db->fetch_array($pj_query)) {
    $pjs[] = $p;
}

ob_start();
?>
<div class="rpg-staff-zone">
    <div class="rpg-staff-header">
        <div class="rpg-staff-header-content">
            <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver</a>
            <h1><i class="fas fa-skull-crossbones"></i> Gestión de Recompensas (Wanted)</h1>
        </div>
    </div>

    <div class="rpg-staff-wanted-wrapper">
        <div class="rpg-staff-wanted-form-col">
            <h2>Añadir/Editar Cartel</h2>
            <form method="post" action="zona_staff_wanted.php">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="form-id" value="0">
                
                <p>
                    <label>Vincular a Personaje (Opcional):</label><br>
                    <select name="entity_id" id="form-entity_id" class="rpg-staff-wanted-input">
                        <option value="0">-- Ninguno (NPC o Personaje Genérico) --</option>
                        <?php foreach ($pjs as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) . ((int)$p['is_npc'] === 1 ? ' (NPC)' : '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p><label>Nombre:</label><br><input type="text" name="name" id="form-name" class="rpg-staff-wanted-input" required></p>
                <p><label>Epíteto (Opcional):</label><br><input type="text" name="epithet" id="form-epithet" class="rpg-staff-wanted-input"></p>
                <p><label>Recompensa (Berries):</label><br><input type="number" name="bounty" id="form-bounty" class="rpg-staff-wanted-input" required></p>
                <p><label>URL de Imagen:</label><br><input type="url" name="image_url" id="form-image_url" class="rpg-staff-wanted-input"></p>
                <p><label>Motivo de la recompensa:</label><br><textarea name="reason" id="form-reason" class="rpg-staff-wanted-input" rows="4"></textarea></p>
                
                <button type="submit" class="rpg-btn rpg-btn--primary">Guardar Cartel</button>
                <button type="button" class="rpg-btn rpg-btn--secondary" onclick="resetForm()">Nuevo Cartel</button>
            </form>
        </div>

        <div class="rpg-staff-wanted-list-col">
            <h2>Carteles Activos</h2>
            <table class="rpg-staff-wanted-table">
                <tr class="rpg-staff-wanted-th-row">
                    <th>Nombre</th>
                    <th>Recompensa</th>
                    <th>Acciones</th>
                </tr>
                <?php foreach ($wanteds as $w): ?>
                <tr class="rpg-staff-wanted-td-row">
                    <td class="rpg-staff-wanted-td"><?= htmlspecialchars($w['name']) ?></td>
                    <td class="rpg-staff-wanted-td"><?= number_format((float)$w['bounty'], 0, ',', '.') ?> B</td>
                    <td class="rpg-staff-wanted-td">
                        <button type="button" class="rpg-btn-sm" onclick="editWanted(<?= htmlspecialchars(json_encode($w)) ?>)"><i class="fas fa-edit"></i></button>
                        <form method="post" class="rpg-inline-form" onsubmit="return confirm('¿Borrar?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $w['id'] ?>">
                            <button type="submit" class="rpg-btn rpg-btn--sm rpg-btn--danger">Borrar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>
<script src="<?= rtrim($mybb->settings['bburl'], '/') ?>/jscripts/game/zona_staff_wanted.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Gestión de Recompensas', $content);
