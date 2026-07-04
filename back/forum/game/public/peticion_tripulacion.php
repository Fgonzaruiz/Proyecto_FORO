<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
global $db, $mybb;

if (!isset($mybb) || !is_object($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$uid = (int)$mybb->user['uid'];
$prefix = TABLE_PREFIX;

$pj_id = (int)($db->fetch_field($db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1"), "active_pj_id") ?? 0);
if (!$pj_id) {
    echo "Necesitas un personaje activo para este trámite.";
    exit;
}

// Check if already in a crew
$pj_data = $db->fetch_array($db->query("SELECT tripulacion_id FROM {$prefix}game_personajes WHERE id = {$pj_id}"));
$current_crew = (int)($pj_data['tripulacion_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_crew') {
        if ($current_crew > 0) {
            die("Ya perteneces a un grupo.");
        }
        $name = $db->escape_string($_POST['name'] ?? '');
        $desc = $db->escape_string($_POST['description'] ?? '');
        $img = $db->escape_string($_POST['image_url'] ?? '');
        
        // Insert crew (pendiente)
        $db->query("INSERT INTO {$prefix}game_tripulaciones (name, description, image_url, leader_pj_id, status) VALUES ('{$name}', '{$desc}', '{$img}', {$pj_id}, 'pendiente')");
        $crew_id = $db->insert_id();
        
        // Insert leader member
        $db->query("INSERT INTO {$prefix}game_tripulacion_miembros (pj_id, tripulacion_id, role, status_peticion) VALUES ({$pj_id}, {$crew_id}, 'Líder', 'aprobada')");
        
        header("Location: peticion_tripulacion.php?msg=created");
        exit;
    }
    
    if ($_POST['action'] === 'join_crew') {
        if ($current_crew > 0) {
            die("Ya perteneces a un grupo.");
        }
        $crew_id = (int)($_POST['crew_id'] ?? 0);
        $db->query("INSERT INTO {$prefix}game_tripulacion_miembros (pj_id, tripulacion_id, role, status_peticion) VALUES ({$pj_id}, {$crew_id}, 'Aspirante', 'pendiente')");
        
        header("Location: peticion_tripulacion.php?msg=joined");
        exit;
    }
}

// Fetch available crews to join
$crews = [];
$q = $db->query("SELECT t.id, t.name, t.image_url, t.description, p.name as leader_name 
                 FROM {$prefix}game_tripulaciones t
                 LEFT JOIN {$prefix}game_personajes p ON t.leader_pj_id = p.id
                 WHERE t.status = 'aprobada' ORDER BY t.name ASC");

while ($r = $db->fetch_array($q)) {
    $mq = $db->query("SELECT p.name, m.role FROM {$prefix}game_tripulacion_miembros m JOIN {$prefix}game_personajes p ON m.pj_id = p.id WHERE m.tripulacion_id = {$r['id']} AND m.status_peticion = 'aprobada'");
    $members = [];
    while ($m = $db->fetch_array($mq)) {
        $members[] = $m;
    }
    $r['members'] = $members;
    $crews[] = $r;
}

ob_start();
?>
<div class="rpg-peticiones">
  <div class="rpg-peticiones-header">
    <div class="rpg-peticiones-header-content">
      <a href="peticiones_general.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Trámites</a>
      <h1><i class="fas fa-users"></i> Trámites de Grupo</h1>
      <p>Funda tu propio grupo o solicita unirte a una organización del Mundo Conocido.</p>
    </div>
  </div>
    
    <div class="rpg-peticiones-form-container rpg-mt-20">
    
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'created'): ?>
        <p class="rpg-text-success">Petición de creación enviada al Staff. Espera su aprobación.</p>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'joined'): ?>
        <p class="rpg-text-success">Petición enviada al Líder del grupo.</p>
    <?php endif; ?>
    
    <?php if ($current_crew > 0): ?>
        <p>Ya perteneces a un grupo. Visita <a href="mis_personajes.php" class="rpg-text-info">Mis Personajes</a> o Gestión para verlo.</p>
    <?php else: ?>
        <div class="rpg-staff-section">
            <h2>Fundar Grupo</h2>
            <form method="post" action="peticion_tripulacion.php" class="rpg-form">
                <input type="hidden" name="action" value="create_crew">
                <p><label class="rpg-form-label">Nombre del Grupo:</label><br>
                <input type="text" name="name" required class="rpg-form-input"></p>
                
                <p><label class="rpg-form-label">URL Bandera (Jolly Roger):</label><br>
                <input type="url" name="image_url" class="rpg-form-input"></p>
                
                <p><label class="rpg-form-label">Trasfondo / Descripción:</label><br>
                <textarea name="description" rows="5" required class="rpg-form-input"></textarea></p>
                
                <button type="submit" class="rpg-btn rpg-btn--primary">Enviar Petición de Creación</button>
            </form>
        </div>
        
        <div class="rpg-staff-section">
            <h2>Unirse a un Grupo</h2>
            <div class="rpg-crews-grid">
                <?php foreach ($crews as $c): ?>
                <div class="rpg-crew-card">
                    <img src="<?= htmlspecialchars($c['image_url'] ?: 'https://placehold.co/600x200') ?>" alt="Bandera" class="rpg-crew-banner">
                    <div class="rpg-crew-card-body">
                        <h3 class="rpg-crew-title"><?= htmlspecialchars($c['name']) ?></h3>
                        <p class="rpg-crew-leader"><strong>Líder:</strong> <?= htmlspecialchars($c['leader_name'] ?? 'Desconocido') ?></p>
                        <div class="rpg-crew-members">
                            <strong>Miembros:</strong><br>
                            <?php foreach ($c['members'] as $m): ?>
                                <span><?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['role']) ?>)</span>
                            <?php endforeach; ?>
                        </div>
                        <form method="post" action="peticion_tripulacion.php">
                            <input type="hidden" name="action" value="join_crew">
                            <input type="hidden" name="crew_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="rpg-btn rpg-btn--secondary">Apply / Unirse</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($crews)): ?>
                    <p>No hay tripulaciones registradas y aceptadas.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
game_render_page('Trámites de Grupo', $content);
