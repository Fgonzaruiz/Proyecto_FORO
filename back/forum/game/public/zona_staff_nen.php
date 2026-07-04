<?php
declare(strict_types=1);

define('THIS_SCRIPT', 'zona_staff_nen.php');

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $headerinclude, $header, $footer, $theme, $templates;

if (empty($headerinclude) && isset($templates)) {
    eval('$headerinclude = "'.$templates->get('headerinclude').'";');
    eval('$header = "'.$templates->get('header').'";');
    eval('$footer = "'.$templates->get('footer').'";');
}

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$prefix = TABLE_PREFIX;
$b_url = rtrim($mybb->settings['bburl'], '/');
$my_post_key = $mybb->post_code;

// Verificar permisos de staff
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;

if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        $staff_level = (int)$pj['staff_level'];
    }
}

if ($staff_level < 2) {
    header('Location: ../index.php');
    exit;
}

// Propuestas de Hatsu pendientes
$abilities_q = $db->query("
    SELECT a.*, p.name AS character_name 
    FROM {$prefix}game_nen_abilities a
    JOIN {$prefix}game_personajes p ON a.character_id = p.id
    WHERE a.approved = 0 
    ORDER BY a.id ASC
");
$pending_hatsus = [];
while ($row = $db->fetch_array($abilities_q)) {
    $pending_hatsus[] = $row;
}

// Cartas técnicas para vincular
$cards_q = $db->query("SELECT id, name, `rank` FROM {$prefix}game_cards WHERE card_type = 'tecnica' ORDER BY name ASC");
$technical_cards = [];
while ($card = $db->fetch_array($cards_q)) {
    $technical_cards[] = $card;
}

// PJs con Nen despertado pero sin tipo
$pending_taza_q = $db->query("
    SELECT p.id, p.name 
    FROM {$prefix}game_personajes p
    JOIN {$prefix}game_nen n ON p.id = n.character_id
    WHERE n.nen_type_locked = 0
    ORDER BY p.name ASC
");
$pending_taza_pjs = [];
while ($pj_row = $db->fetch_array($pending_taza_q)) {
    $pending_taza_pjs[] = $pj_row;
}

$staff_nen_js_url = htmlspecialchars($b_url . '/jscripts/game/zona_staff_nen.js', ENT_QUOTES);

ob_start();
?>
<div class="rpg-staff-zone">
  <div class="rpg-nen-staff-hero">
    <a href="<?= htmlspecialchars($b_url) ?>/game/public/zona_staff.php" class="rpg-nen-staff-back"><i class="fas fa-arrow-left"></i> Volver a Zona Staff</a>
    <h1><i class="fas fa-hand-sparkles"></i> Administración de Nen</h1>
    <p>Autoriza habilidades Hatsu, vincula cartas del deck y realiza la Prueba de la Taza.</p>
  </div>

  <div class="rpg-nen-staff-cols">
    <div>
      <h2 class="pj-tab-section-heading"><i class="fas fa-clipboard-list"></i> Propuestas de Hatsu Pendientes (<?= count($pending_hatsus) ?>)</h2>

      <?php if (empty($pending_hatsus)): ?>
        <p class="rpg-shop-empty"><i class="fas fa-box-open"></i> No hay propuestas de Hatsu pendientes de revisión.</p>
      <?php else: ?>
        <div class="rpg-admin-list">
          <?php foreach ($pending_hatsus as $ab):
              $conds = json_decode($ab['conditions_json'] ?? '[]', true) ?: [];
          ?>
            <article class="rpg-nen-hatsu-card" id="hatsu-card-<?= $ab['id'] ?>">
              <div class="rpg-nen-hatsu-card-head">
                <div>
                  <h3><?= htmlspecialchars($ab['name']) ?></h3>
                  <span class="rpg-nen-hatsu-proposer">Propuesto por: <strong><?= htmlspecialchars($ab['character_name']) ?></strong></span>
                </div>
                <div class="rpg-nen-hatsu-badges">
                  <span class="rpg-nen-hatsu-rank"><?= htmlspecialchars($ab['rank']) ?></span>
                  <span class="rpg-nen-hatsu-cost"><i class="fas fa-bolt"></i> <?= (int)$ab['nen_cost'] ?> PE</span>
                </div>
              </div>

              <p class="rpg-nen-hatsu-desc"><?= nl2br(htmlspecialchars($ab['description'])) ?></p>

              <?php if (!empty($conds)): ?>
                <div class="rpg-nen-hatsu-conditions">
                  <strong>Condiciones / Votos:</strong> <?= implode(', ', array_map('htmlspecialchars', $conds)) ?>
                </div>
              <?php endif; ?>

              <div class="rpg-nen-hatsu-approve-block">
                <h4><i class="fas fa-link"></i> Vincular a Carta Técnica del Deck</h4>
                <div class="rpg-nen-hatsu-approve-row">
                  <select id="card-bind-<?= $ab['id'] ?>" class="rpg-form-input textbox">
                    <option value="">-- Seleccionar Carta Técnica --</option>
                    <?php foreach ($technical_cards as $c): ?>
                      <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= $c['rank'] ?>)</option>
                    <?php endforeach; ?>
                  </select>
                  <button type="button" class="rpg-nen-btn-approve" onclick="approveHatsu(<?= $ab['id'] ?>)"><i class="fas fa-check"></i> Aprobar</button>
                  <button type="button" class="rpg-nen-btn-reject" onclick="rejectHatsu(<?= $ab['id'] ?>)"><i class="fas fa-trash"></i> Rechazar</button>
                </div>
                <div id="approve-msg-<?= $ab['id'] ?>" class="rpg-nen-msg rpg-is-hidden"></div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="rpg-nen-staff-sidebar">
      <div class="rpg-nen-staff-panel">
        <h3><i class="fas fa-mug-hot"></i> Prueba de la Taza</h3>
        <p>Fija de forma irreversible el tipo de Nen para un personaje que ya haya despertado su aura.</p>
        <form class="rpg-nen-staff-panel-form" onsubmit="submitTazaDirect(event)">
          <div class="rpg-form-group">
            <label class="rpg-form-label">Personaje</label>
            <select id="taza-target-pj" class="rpg-form-input textbox" required>
              <option value="">-- Seleccionar Personaje --</option>
              <?php foreach ($pending_taza_pjs as $pj_row): ?>
                <option value="<?= $pj_row['id'] ?>"><?= htmlspecialchars($pj_row['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="rpg-form-group">
            <label class="rpg-form-label">Tipo de Aura</label>
            <select id="taza-nen-type" class="rpg-form-input textbox" required>
              <option value="">-- Seleccionar Tipo --</option>
              <option value="enhancement">Intensificación (Kyōka)</option>
              <option value="transmutation">Transmutación (Henka)</option>
              <option value="emission">Emisión (Hōshutsu)</option>
              <option value="conjuration">Materialización (Geshitsu)</option>
              <option value="manipulation">Manipulación (Sōsa)</option>
              <option value="specialization">Especialización (Tokushitsu)</option>
            </select>
          </div>
          <div id="taza-direct-msg" class="rpg-nen-msg rpg-is-hidden"></div>
          <button type="submit" class="rpg-nen-staff-btn-full rpg-nen-staff-btn-full--indigo"><i class="fas fa-lock"></i> Aplicar Tipo de Nen</button>
        </form>
      </div>

      <div class="rpg-nen-staff-panel">
        <h3><i class="fas fa-key"></i> Despertar Nen Directo</h3>
        <p>Abre los nodos de aura de cualquier personaje ingresando su ID de Ficha.</p>
        <form class="rpg-nen-staff-panel-form" onsubmit="submitDespertarDirect(event)">
          <div class="rpg-form-group">
            <label class="rpg-form-label">ID del Personaje</label>
            <input type="number" id="despertar-pj-id" class="rpg-form-input textbox" placeholder="Ej: 1" required />
          </div>
          <div id="despertar-direct-msg" class="rpg-nen-msg rpg-is-hidden"></div>
          <button type="submit" class="rpg-nen-staff-btn-full rpg-nen-staff-btn-full--green"><i class="fas fa-hand-sparkles"></i> Despertar Aura</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
window.STAFF_NEN_CONFIG = {
    my_post_key: <?= json_encode($my_post_key) ?>,
    bburl: <?= json_encode($b_url) ?>
};
</script>
<script src="<?= $staff_nen_js_url ?>"></script>
<?php
$content = ob_get_clean();
game_render_page('Staff: Administración de Nen', $content);
