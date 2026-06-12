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

$activePjId = game_get_active_pj_id($uid);
$pjName = '';
$pdTotal = 0;
$pdSpent = 0;
$pdAvailable = 0;

if ($activePjId > 0) {
    $pjQ = $db->query("SELECT name, puntos_destino FROM {$prefix}game_personajes WHERE id = {$activePjId} LIMIT 1");
    $pj = $db->fetch_array($pjQ);
    if ($pj) {
        $pjName = $pj['name'];
    }
    
    // Fetch PD info using helpers
    $pdTotal = game_get_character_pd_total($activePjId);
    $pdSpent = game_get_character_pd_spent($activePjId);
    $pdAvailable = game_get_character_pd_available($activePjId);
}

$b_url = $mybb->settings['bburl'];

$destinyItems = [
    [
        'type' => 'estilo_secundario',
        'cost' => 2,
        'name' => 'Estilo de Pelea Secundario',
        'icon' => 'fa-swords',
        'desc' => 'Desbloquea el acceso para entrenar y equipar un segundo estilo de combate complementario.'
    ],
    [
        'type' => 'estilo_terciario',
        'cost' => 4,
        'name' => 'Estilo de Pelea Terciario',
        'icon' => 'fa-shield-halved',
        'desc' => 'Permite al personaje dominar y utilizar un tercer estilo de combate simultáneo.'
    ],
    [
        'type' => 'tecnica_prohibida',
        'cost' => 3,
        'name' => 'Técnica Prohibida',
        'icon' => 'fa-scroll',
        'desc' => 'Habilita el aprendizaje de una técnica oculta o prohibida dentro de tu disciplina principal.'
    ],
    [
        'type' => 'habilidad_elemental',
        'cost' => 2,
        'name' => 'Habilidad Elemental / Especial',
        'icon' => 'fa-fire',
        'desc' => 'Desbloquea el uso de propiedades elementales o habilidades narrativas de combate únicas.'
    ],
    [
        'type' => 'akuma_no_mi',
        'cost' => 5,
        'name' => 'Acceso a Fruta del Diablo (Akuma no Mi)',
        'icon' => 'fa-apple-alt',
        'desc' => 'Otorga el permiso administrativo oficial para consumir una Akuma no Mi disponible.'
    ],
    [
        'type' => 'barco_narrativo',
        'cost' => 3,
        'name' => 'Mejora de Barco Narrativo',
        'icon' => 'fa-ship',
        'desc' => 'Añade slots, mejoras o refuerzos mecánicos y de diseño al barco de tu tripulación.'
    ],
    [
        'type' => 'poder_especial',
        'cost' => 4,
        'name' => 'Poder Narrativo Especial',
        'icon' => 'fa-magic',
        'desc' => 'Concede una ventaja o rasgo narrativo único en el mundo, sujeto a aprobación del staff.'
    ]
];

ob_start();
?>
<div class="rpg-peticiones">
  <div class="rpg-peticiones-header">
    <div class="rpg-peticiones-header-content">
      <a href="peticiones_general.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Trámites</a>
      <h1><i class="fas fa-star"></i> Tienda de Puntos Destino</h1>
      <p>Canjea tus Puntos Destino (PD) ganados en misiones para adquirir accesos y desbloqueos especiales para tu personaje.</p>
    </div>
  </div>

  <?php if ($activePjId <= 0): ?>
    <div class="rpg-locked-panel rpg-mt-20">
        <i class="fas fa-lock rpg-locked-icon"></i>
        Debes seleccionar un personaje activo en tu panel de control para poder usar la Tienda de Destino.
    </div>
  <?php else: ?>

    <div class="rpg-peticiones-form-container">
        <!-- Destiny Points display -->
        <div class="rpg-pp-display rpg-pp-display--wrap">
            <div class="rpg-pp-col">
                <h3>Desbloqueos y Compras con PD</h3>
                <div class="rpg-pp-desc">Adquiere slots de estilos de combate secundarios, técnicas prohibidas y otros beneficios narrativos.</div>
            </div>
            <div class="rpg-pp-stats-row">
                <div class="rpg-pp-val rpg-pp-val--pd" id="pd_available_display_page"><i class="fas fa-star"></i> <span id="pd_val_available"><?= $pdAvailable ?></span> / <?= $pdTotal ?> PD Disponibles</div>
            </div>
        </div>

        <!-- Catalog Grid -->
        <div class="rpg-shop-grid rpg-mt-20">
          <?php foreach ($destinyItems as $item): 
              $canBuy = ($pdAvailable >= $item['cost']);
          ?>
            <article class="rpg-shop-card">
              <div class="rpg-shop-card-body">
                <h3 class="rpg-shop-card-title rpg-flex-gap-10">
                  <i class="fas <?= $item['icon'] ?>"></i>
                  <span><?= htmlspecialchars($item['name']) ?></span>
                </h3>
                <p class="rpg-shop-card-desc"><?= htmlspecialchars($item['desc']) ?></p>
                <div class="rpg-shop-card-footer">
                  <span class="rpg-shop-card-price"><i class="fas fa-star"></i> <?= $item['cost'] ?> PD</span>
                  <?php if ($canBuy): ?>
                    <button type="button" class="rpg-action-btn rpg-btn-primary btn-buy-pd-item" onclick="buyPdItem('<?= $item['type'] ?>', <?= $item['cost'] ?>, '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')">
                      Adquirir
                    </button>
                  <?php else: ?>
                    <button type="button" class="rpg-system-tab-btn" disabled title="Puntos Destino insuficientes">
                      Bloqueado
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
    </div>

  <?php endif; ?>
</div>

<script>
window.TIENDA_DESTINO_CONFIG = {
  bburl: '<?= $b_url ?>',
  characterId: <?= (int)$activePjId ?>
};
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/tienda_destino.js?v=3"></script>
<?php
$content = ob_get_clean();
game_render_page('Tienda de Destino', $content);
