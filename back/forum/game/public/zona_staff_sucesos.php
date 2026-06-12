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

if (!$pj || (int)$pj['staff_level'] < 2) { // Moderadores o superior pueden leer
    echo "Acceso denegado.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $id = (int)($_POST['id'] ?? 0);
    $suceso_q = $db->query("SELECT * FROM {$prefix}game_sucesos WHERE id = {$id} LIMIT 1");
    $suceso = $db->fetch_array($suceso_q);
    
    if ($suceso && $suceso['status'] === 'pendiente') {
        $db->query("UPDATE {$prefix}game_sucesos SET status = 'leido' WHERE id = {$id}");
        
        // Enviar MD de agradecimiento a través de MyBB PM handler
        require_once MYBB_ROOT . "inc/datahandlers/pm.php";
        $pmhandler = new PMDataHandler();
        
        $pm = [
            "subject" => "Gracias por tu contribución: " . $suceso['title'],
            "message" => "Estimado jugador,\n\nEl equipo de Narradores ha recibido y registrado el suceso que enviaste: [url={$suceso['thread_url']}]{$suceso['title']}[/url].\n\nGracias a tu aportación, el mundo de rol se mantiene vivo y dinámico. La redacción de News Coo podría publicar algo al respecto muy pronto.\n\nAtentamente,\nEl Staff",
            "icon" => 0,
            "toid" => [$suceso['user_id']],
            "fromid" => $uid, // Enviado por el mod actual
            "do_html" => 0,
            "do_mycode" => 1,
            "do_smilies" => 1,
            "do_badwords" => 1,
            "options" => [
                "signature" => 0,
                "disablesmilies" => 0,
                "savecopy" => 0,
                "readreceipt" => 0
            ],
            "saveasdraft" => 0
        ];
        
        $pmhandler->admin_override = true;
        $pmhandler->set_data($pm);
        
        if(!$pmhandler->validate_pm()) {
            $pmhandler->is_validated = true;
            $pmhandler->errors = [];
        }
        $pmhandler->insert_pm();
        
        header("Location: zona_staff_sucesos.php?msg=marked");
        exit;
    }
}

$sucesos = [];
$q = $db->query("SELECT s.*, p.name as pj_name FROM {$prefix}game_sucesos s LEFT JOIN {$prefix}game_personajes p ON s.pj_id = p.id WHERE s.status = 'pendiente' ORDER BY s.created_at ASC");
while ($s = $db->fetch_array($q)) {
    $sucesos[] = $s;
}

ob_start();
?>
<div class="rpg-staff-zone">
    <div class="rpg-staff-header">
        <div class="rpg-staff-header-content">
            <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver</a>
            <h1><i class="fas fa-envelope-open-text"></i> Buzón de Sucesos</h1>
            <p>Sucesos mundiales reportados por los jugadores. Al marcar como leído, se enviará un MP automático de agradecimiento.</p>
        </div>
    </div>

    <div class="rpg-staff-sucesos-wrapper">
        <?php if (empty($sucesos)): ?>
            <div class="rpg-info-box rpg-text-center">No hay sucesos nuevos pendientes de leer. ¡Todo al día!</div>
        <?php else: ?>
            <div class="rpg-grid-gap-20">
                <?php foreach ($sucesos as $s): ?>
                <div class="rpg-staff-suceso-card">
                    <h3 class="rpg-staff-suceso-title"><?= htmlspecialchars($s['title']) ?></h3>
                    <p><strong>Enviado por:</strong> <?= htmlspecialchars($s['pj_name'] ?? 'Desconocido') ?> el <?= date('d/m/Y H:i', (int)$s['created_at']) ?></p>
                    <p><strong>URL del Tema:</strong> <a href="<?= htmlspecialchars($s['thread_url']) ?>" target="_blank"><?= htmlspecialchars($s['thread_url']) ?></a></p>
                    <div class="rpg-staff-suceso-desc"><?= htmlspecialchars($s['description']) ?></div>
                    
                    <form method="post">
                        <input type="hidden" name="action" value="mark_read">
                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                        <button type="submit" class="rpg-btn rpg-btn--primary"><i class="fas fa-check"></i> Marcar como Leído (y agradecer)</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
game_render_page('Buzón de Sucesos', $content);
