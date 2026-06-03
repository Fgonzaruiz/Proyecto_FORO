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

// Obtener personaje activo y verificar staff_level
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
$pj_name = '';

if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT name, staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        $staff_level = (int)$pj['staff_level'];
        $pj_name = $pj['name'];
    }
}

// Solo staff nivel 3 (Administrador) puede crear/gestionar NPCs mayores
if ($staff_level < 3) {
    header('Location: ../index.php');
    exit;
}

$npc_id = (int)($_GET['id'] ?? 0);
$npc = null;
if ($npc_id > 0) {
    $npc_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$npc_id} AND is_npc = 1 LIMIT 1");
    $npc = $db->fetch_array($npc_q);
}

$error = '';
$factions = ['Pirata', 'Marine', 'Revolucionario', 'Gobierno Mundial', 'Cazador de Recompensas', 'Civil'];
$races = ['Humano', 'Gyojin', 'Mink', 'Kuja', 'Enano', 'Piernas Largas', 'Brazos Largos', 'Cuello Largo', 'Tres Ojos', 'Gigante', 'Lunaria', 'Skypiean'];
$occupations = ['Guerrero', 'Espadachín', 'Tirador', 'Médico', 'Carpintero', 'Cocinero', 'Arqueólogo', 'Navegante', 'Músico', 'Científico', 'Ninguna'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $avatar = trim($_POST['avatar'] ?? '');
    $faction = trim($_POST['faction'] ?? '');
    $race = trim($_POST['race'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $rango = trim($_POST['rango'] ?? '');
    $tripulacion = trim($_POST['tripulacion'] ?? '');
    $recompensa = trim($_POST['recompensa'] ?? '');
    $pb = trim($_POST['pb'] ?? '');
    $desc = trim($_POST['desc'] ?? '');
    $details = trim($_POST['details'] ?? '');
    
    // Stats
    $stats = [
        'fue' => max(1, min(20, (int)($_POST['fue'] ?? 5))),
        'agi' => max(1, min(20, (int)($_POST['agi'] ?? 5))),
        'des' => max(1, min(20, (int)($_POST['des'] ?? 5))),
        'int' => max(1, min(20, (int)($_POST['int'] ?? 5))),
        'esp' => max(1, min(20, (int)($_POST['esp'] ?? 5))),
        'inst' => max(1, min(20, (int)($_POST['inst'] ?? 5))),
    ];
    
    if ($name === '') {
        $error = 'El nombre del NPC es obligatorio.';
    } else {
        $nameEsc = $db->escape_string($name);
        $avatarEsc = $db->escape_string($avatar);
        $factionEsc = $db->escape_string($faction);
        $raceEsc = $db->escape_string($race);
        $occupationEsc = $db->escape_string($occupation);
        $rangoEsc = $db->escape_string($rango);
        $tripulacionEsc = $db->escape_string($tripulacion);
        $recompensaEsc = $db->escape_string($recompensa);
        $pbEsc = $db->escape_string($pb);
        $descEsc = $db->escape_string($desc);
        $detailsEsc = $db->escape_string($details);
        $stats_json = $db->escape_string(json_encode($stats));

        // Data JSON
        $data_json_arr = [
            'age' => 'Desconocida',
            'origin' => 'Desconocido',
            'pb' => $pb,
            'physique' => $details,
            'psychology' => '',
            'extras' => '',
            'arquetipo' => 'Desconocido',
            'job' => $occupation,
            'race' => $race,
            'rank' => $rango,
            'faction' => $faction,
            'avatar' => $avatar,
            'linaje' => ['perks' => []]
        ];
        $data_json = $db->escape_string(json_encode($data_json_arr, JSON_UNESCAPED_UNICODE));

        if ($npc) {
            // Update
            $db->write_query("UPDATE {$prefix}game_personajes SET 
                name = '{$nameEsc}',
                race_name = '{$raceEsc}',
                occupation_name = '{$occupationEsc}',
                faction = '{$factionEsc}',
                rango = '{$rangoEsc}',
                tripulacion = '{$tripulacionEsc}',
                recompensa = '{$recompensaEsc}',
                avatar = '{$avatarEsc}',
                `desc` = '{$descEsc}',
                details = '{$detailsEsc}',
                stats_json = '{$stats_json}',
                data_json = '{$data_json}'
                WHERE id = {$npc['id']}");
            header('Location: zona_staff_npc.php?msg=updated');
            exit;
        } else {
            // Insert
            $db->write_query("INSERT INTO {$prefix}game_personajes (
                user_id, name, race, race_name, occupation, occupation_name, 
                `desc`, details, rango, tripulacion, recompensa, banner, avatar, 
                is_staff, staff_level, is_npc, status, approved, stats_json, data_json, faction
            ) VALUES (
                {$uid}, '{$nameEsc}', 'npc', '{$raceEsc}', 'npc', '{$occupationEsc}',
                '{$descEsc}', '{$detailsEsc}', '{$rangoEsc}', '{$tripulacionEsc}', '{$recompensaEsc}', '', '{$avatarEsc}',
                0, 0, 1, 'aprobada', 1, '{$stats_json}', '{$data_json}', '{$factionEsc}'
            )");
            header('Location: zona_staff_npc.php?msg=created');
            exit;
        }
    }
}

// Valores por defecto para edición
$name_val = $npc ? $npc['name'] : '';
$avatar_val = $npc ? $npc['avatar'] : '';
$faction_val = $npc ? $npc['faction'] : 'Civil';
$race_val = $npc ? $npc['race_name'] : 'Humano';
$occupation_val = $npc ? $npc['occupation_name'] : 'Ninguna';
$rango_val = $npc ? $npc['rango'] : '';
$tripulacion_val = $npc ? $npc['tripulacion'] : '';
$recompensa_val = $npc ? $npc['recompensa'] : '';
$desc_val = $npc ? $npc['desc'] : '';
$details_val = $npc ? $npc['details'] : '';

$stats_val = $npc && !empty($npc['stats_json']) 
    ? json_decode($npc['stats_json'], true) 
    : ['fue'=>5,'agi'=>5,'des'=>5,'int'=>5,'esp'=>5,'inst'=>5];

$data_json_val = $npc && !empty($npc['data_json']) 
    ? json_decode($npc['data_json'], true) 
    : [];
$pb_val = $data_json_val['pb'] ?? '';

ob_start();
?>
<div class="rpg-staff-zone">
  <div class="rpg-staff-header rpg-staff-header--npc-crear">
    <div class="rpg-staff-header-content">
      <a href="zona_staff_npc.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Lista de NPCs</a>
      <h1><i class="fas fa-user-plus"></i> <?= $npc ? 'Editar NPC Mayor' : 'Crear NPC Mayor' ?></h1>
      <p>Define los datos básicos, apariencia y atributos del NPC Mayor.</p>
    </div>
  </div>

  <?php if ($error !== ''): ?>
    <div class="rpg-post-mods-container" style="border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05); color: #ef4444; margin-bottom: 20px;">
      <span class="rpg-post-mods-title" style="color: #ef4444;"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></span>
    </div>
  <?php endif; ?>

  <form method="POST" class="rpg-npc-creator-form">
    <div class="rpg-npc-form-grid">
      <!-- NOMBRE -->
      <div>
        <label class="rpg-modal-label">Nombre del NPC *</label>
        <input type="text" name="name" class="textbox" value="<?= htmlspecialchars($name_val) ?>" placeholder="Ej: Shanks" required />
      </div>

      <!-- AVATAR -->
      <div>
        <label class="rpg-modal-label">URL del Avatar (Imagen externa)</label>
        <input type="url" name="avatar" class="textbox" value="<?= htmlspecialchars($avatar_val) ?>" placeholder="https://i.imgur.com/..." />
      </div>

      <!-- FACCION -->
      <div>
        <label class="rpg-modal-label">Facción</label>
        <select name="faction" class="textbox">
          <?php foreach ($factions as $fac): ?>
            <option value="<?= $fac ?>" <?= $faction_val === $fac ? 'selected' : '' ?>><?= $fac ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- RAZA -->
      <div>
        <label class="rpg-modal-label">Raza</label>
        <select name="race" class="textbox">
          <?php foreach ($races as $ra): ?>
            <option value="<?= $ra ?>" <?= $race_val === $ra ? 'selected' : '' ?>><?= $ra ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- OCUPACION / OFICIO -->
      <div>
        <label class="rpg-modal-label">Ocupación / Oficio</label>
        <select name="occupation" class="textbox">
          <?php foreach ($occupations as $oc): ?>
            <option value="<?= $oc ?>" <?= $occupation_val === $oc ? 'selected' : '' ?>><?= $oc ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- RANGO -->
      <div>
        <label class="rpg-modal-label">Rango / Jerarquía</label>
        <input type="text" name="rango" class="textbox" value="<?= htmlspecialchars($rango_val) ?>" placeholder="Ej: Capitán, Almirante, Recluta..." />
      </div>

      <!-- TRIPULACION -->
      <div>
        <label class="rpg-modal-label">Tripulación / División / Organización</label>
        <input type="text" name="tripulacion" class="textbox" value="<?= htmlspecialchars($tripulacion_val) ?>" placeholder="Ej: Piratas del Pelirrojo, División G-5..." />
      </div>

      <!-- RECOMPENSA -->
      <div>
        <label class="rpg-modal-label">Recompensa (Bounty)</label>
        <input type="text" name="recompensa" class="textbox" value="<?= htmlspecialchars($recompensa_val) ?>" placeholder="Ej: 4,048,900,000 ฿ o 0 ฿" />
      </div>

      <!-- PB (Play-By) -->
      <div>
        <label class="rpg-modal-label">Play-By (PB) / Avatar de Rostro</label>
        <input type="text" name="pb" class="textbox" value="<?= htmlspecialchars($pb_val) ?>" placeholder="Ej: Portgas D. Ace" />
      </div>

      <!-- VACÍO / ALINEACION -->
      <div></div>

      <!-- HISTORIA / BIOGRAFÍA -->
      <div class="rpg-npc-form-full">
        <label class="rpg-modal-label">Biografía / Historia del NPC</label>
        <textarea name="desc" rows="5" class="rpg-staff-textarea" placeholder="Describe brevemente la historia u origen del personaje..."><?= htmlspecialchars($desc_val) ?></textarea>
      </div>

      <!-- DESCRIPCIÓN FÍSICA Y PERSONALIDAD -->
      <div class="rpg-npc-form-full">
        <label class="rpg-modal-label">Descripción Física y Personalidad</label>
        <textarea name="details" rows="4" class="rpg-staff-textarea" placeholder="Aspecto, cicatrices notables, personalidad y comportamiento..."><?= htmlspecialchars($details_val) ?></textarea>
      </div>

      <!-- ATRIBUTOS / STATS -->
      <div class="rpg-npc-form-full">
        <h3 class="rpg-wizard-preview-stats-title" style="margin-top: 15px; font-weight: 800; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">Atributos de Combate (Valores de 1 a 20)</h3>
        <div class="rpg-npc-form-grid" style="margin-top: 15px;">
          <div>
            <label class="rpg-modal-label">Fuerza (FUE)</label>
            <input type="number" name="fue" min="1" max="20" class="textbox" value="<?= (int)($stats_val['fue'] ?? 5) ?>" />
          </div>
          <div>
            <label class="rpg-modal-label">Agilidad (AGI)</label>
            <input type="number" name="agi" min="1" max="20" class="textbox" value="<?= (int)($stats_val['agi'] ?? 5) ?>" />
          </div>
          <div>
            <label class="rpg-modal-label">Destreza (DES)</label>
            <input type="number" name="des" min="1" max="20" class="textbox" value="<?= (int)($stats_val['des'] ?? 5) ?>" />
          </div>
          <div>
            <label class="rpg-modal-label">Inteligencia (INT)</label>
            <input type="number" name="int" min="1" max="20" class="textbox" value="<?= (int)($stats_val['int'] ?? 5) ?>" />
          </div>
          <div>
            <label class="rpg-modal-label">Espíritu (ESP)</label>
            <input type="number" name="esp" min="1" max="20" class="textbox" value="<?= (int)($stats_val['esp'] ?? 5) ?>" />
          </div>
          <div>
            <label class="rpg-modal-label">Instinto (INST)</label>
            <input type="number" name="inst" min="1" max="20" class="textbox" value="<?= (int)($stats_val['inst'] ?? 5) ?>" />
          </div>
        </div>
      </div>
    </div>

    <div style="text-align: right; padding-top: 15px; border-top: 1px solid var(--border-color);">
      <a href="zona_staff_npc.php" class="rpg-btn-reject-lg" style="text-decoration: none; padding: 10px 24px; margin-right: 10px; display: inline-block;">Cancelar</a>
      <button type="submit" class="rpg-btn-approve-lg" style="padding: 10px 32px; cursor: pointer; border: none; font-size: 13px;">
        <i class="fas fa-save"></i> <?= $npc ? 'Guardar Cambios' : 'Crear NPC' ?>
      </button>
    </div>
  </form>
</div>
<?php
$content = ob_get_clean();
game_render_page($npc ? "Editar NPC Mayor" : "Crear NPC Mayor", $content);
