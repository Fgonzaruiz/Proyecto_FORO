<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$prefix  = TABLE_PREFIX;
$b_url   = rtrim($mybb->settings['bburl'], '/');
$my_post_key = $mybb->post_code;

// Personaje activo
$char_id = game_get_active_pj_id($uid);
$character = null;

if ($char_id > 0) {
    $char_q = $db->query("SELECT id, name, avatar, berries, status FROM {$prefix}game_personajes WHERE id = {$char_id} LIMIT 1");
    $character = $db->fetch_array($char_q);
}

// Cargar catálogo de tienda (todas las cartas in_shop=1 y tipo comerciable)
$shop_q = $db->query("
    SELECT id, name, card_type, rank, image_url, description, cost_berries, shop_category, effects_json
    FROM {$prefix}game_cards
    WHERE in_shop = 1
      AND card_type IN ('equipo', 'npc_menor', 'barco')
    ORDER BY shop_category ASC, name ASC
");
$shop_cards = [];
while ($row = $db->fetch_array($shop_q)) {
    $effects = json_decode($row['effects_json'] ?? '{}', true) ?: [];
    $row['is_consumable'] = (
        $row['card_type'] === 'equipo'
        && strtolower((string)($effects['equipo_type'] ?? '')) === 'util'
    );
    $row['cost_berries'] = (int)$row['cost_berries'];
    $shop_cards[] = $row;
}

// Cargar inventario del personaje activo (sólo cartas comerciables)
$inventory_cards = [];
if ($char_id > 0) {
    $inv_q = $db->query("
        SELECT c.id, c.name, c.card_type, c.rank, c.image_url, c.cost_berries, c.shop_category,
               c.effects_json, cc.cantidad
        FROM {$prefix}game_character_cards cc
        JOIN {$prefix}game_cards c ON cc.card_id = c.id
        WHERE cc.character_id = {$char_id}
          AND c.card_type IN ('equipo', 'npc_menor', 'barco')
          AND c.in_shop = 1
        ORDER BY c.name ASC
    ");
    while ($irow = $db->fetch_array($inv_q)) {
        $ieff = json_decode($irow['effects_json'] ?? '{}', true) ?: [];
        $irow['is_consumable'] = (
            $irow['card_type'] === 'equipo'
            && strtolower((string)($ieff['equipo_type'] ?? '')) === 'util'
        );
        $irow['cost_berries'] = (int)$irow['cost_berries'];
        $irow['cantidad']     = (int)$irow['cantidad'];
        $inventory_cards[] = $irow;
    }
}

// ---- Helpers de renderizado ----

function render_shop_card(array $c, string $b_url): string {
    $img     = htmlspecialchars($c['image_url'] ?? '', ENT_QUOTES);
    $name    = htmlspecialchars($c['name'], ENT_QUOTES);
    $desc    = htmlspecialchars($c['description'] ?? '', ENT_QUOTES);
    $cost    = number_format((int)$c['cost_berries'], 0, ',', '.');
    $cid     = (int)$c['id'];
    $is_cons = $c['is_consumable'] ? 'true' : 'false';
    $placeholder = $b_url . '/images/game/card_placeholder.png';
    $img_src = $img ?: $placeholder;

    $type_labels = [
        'equipo'    => '<i class="fas fa-shield-alt"></i> Equipo',
        'npc_menor' => '<i class="fas fa-paw"></i> Compañero',
        'barco'     => '<i class="fas fa-ship"></i> Barco',
    ];
    $type_label = $type_labels[$c['card_type']] ?? $c['card_type'];

    return "
    <article class=\"rpg-shop-card\" data-card-id=\"{$cid}\" data-card-name=\"{$name}\" data-card-cost=\"{$c['cost_berries']}\" data-is-consumable=\"{$is_cons}\">
      <div class=\"rpg-shop-card-img\">
        <img src=\"{$img_src}\" alt=\"{$name}\" loading=\"lazy\">
        <span class=\"rpg-shop-card-type-badge\">{$type_label}</span>
      </div>
      <div class=\"rpg-shop-card-body\">
        <h3 class=\"rpg-shop-card-title\">{$name}</h3>
        <p class=\"rpg-shop-card-desc\">{$desc}</p>
        <div class=\"rpg-shop-card-footer\">
          <span class=\"rpg-shop-card-price\"><i class=\"fas fa-coins\"></i> {$cost} B.</span>
          <button type=\"button\" class=\"rpg-btn rpg-btn--laton rpg-shop-add-btn\" data-card-id=\"{$cid}\">
            <i class=\"fas fa-cart-plus\"></i> Añadir
          </button>
        </div>
      </div>
    </article>";
}

function render_sell_card(array $c): string {
    $name     = htmlspecialchars($c['name'], ENT_QUOTES);
    $cid      = (int)$c['id'];
    $refund   = number_format((int)floor($c['cost_berries'] * 0.5), 0, ',', '.');
    $owned    = (int)$c['cantidad'];
    $is_cons  = $c['is_consumable'] ? 'true' : 'false';

    $qty_controls = '';
    if ($c['is_consumable'] && $owned > 1) {
        $qty_controls = "
        <div class=\"rpg-shop-sell-qty\">
          <button type=\"button\" class=\"rpg-shop-qty-btn\" data-action=\"dec\" data-card-id=\"{$cid}\">-</button>
          <span class=\"rpg-shop-qty-val\" id=\"sell-qty-{$cid}\">1</span>
          <button type=\"button\" class=\"rpg-shop-qty-btn\" data-action=\"inc\" data-card-id=\"{$cid}\" data-max=\"{$owned}\">+</button>
        </div>";
    }

    return "
    <article class=\"rpg-shop-sell-card\" data-card-id=\"{$cid}\" data-card-cost=\"{$c['cost_berries']}\" data-is-consumable=\"{$is_cons}\" data-owned=\"{$owned}\">
      <div class=\"rpg-shop-sell-info\">
        <span class=\"rpg-shop-sell-name\">{$name}</span>
        <span class=\"rpg-shop-sell-owned\">{$owned} en posesión</span>
      </div>
      {$qty_controls}
      <div class=\"rpg-shop-sell-action\">
        <span class=\"rpg-shop-sell-refund\"><i class=\"fas fa-coins\"></i> {$refund} B. cada uno</span>
        <button type=\"button\" class=\"rpg-btn rpg-btn--danger rpg-shop-sell-btn\" data-card-id=\"{$cid}\">
          <i class=\"fas fa-hand-holding-usd\"></i> Vender
        </button>
      </div>
    </article>";
}

// Agrupar cartas por categoría para la tienda
$categories = [
    'utiles'  => ['label' => 'Útiles', 'icon' => 'fa-toolbox',    'items' => []],
    'armeria' => ['label' => 'Armería','icon' => 'fa-shield-halved','items' => []],
    'naval'   => ['label' => 'Astillero','icon' => 'fa-ship',     'items' => []],
    'mascotas'=> ['label' => 'Criadero','icon' => 'fa-paw',       'items' => []],
];

foreach ($shop_cards as $sc) {
    $cat = $sc['shop_category'] ?? 'utiles';
    if (!isset($categories[$cat])) {
        $cat = 'utiles';
    }
    $categories[$cat]['items'][] = $sc;
}

// Construir tabs HTML de tienda
$tabs_nav   = '';
$tabs_panels= '';
$first      = true;
foreach ($categories as $cat_key => $cat) {
    $active_nav   = $first ? ' rpg-shop-tab--active' : '';
    $active_panel = $first ? ' rpg-shop-panel--active' : '';
    $first        = false;

    $tabs_nav .= "
      <button type=\"button\" class=\"rpg-shop-tab{$active_nav}\" data-tab=\"{$cat_key}\">
        <i class=\"fas {$cat['icon']}\"></i> {$cat['label']}
        <span class=\"rpg-shop-tab-count\">" . count($cat['items']) . "</span>
      </button>";

    $cards_html = '';
    if (empty($cat['items'])) {
        $cards_html = '<p class="rpg-shop-empty"><i class="fas fa-box-open"></i> No hay artículos disponibles en esta categoría.</p>';
    } else {
        foreach ($cat['items'] as $ci) {
            $cards_html .= render_shop_card($ci, $b_url);
        }
    }

    $tabs_panels .= "
    <div class=\"rpg-shop-panel{$active_panel}\" id=\"tab-panel-{$cat_key}\">
      <div class=\"rpg-shop-grid\">{$cards_html}</div>
    </div>";
}

// Construir panel de venta
$sell_html = '';
if ($char_id > 0 && !empty($inventory_cards)) {
    foreach ($inventory_cards as $ic) {
        $sell_html .= render_sell_card($ic);
    }
} elseif ($char_id <= 0) {
    $sell_html = '<p class="rpg-shop-empty"><i class="fas fa-user-slash"></i> Necesitas un personaje activo para vender.</p>';
} else {
    $sell_html = '<p class="rpg-shop-empty"><i class="fas fa-box-open"></i> No tienes objetos vendibles en tu inventario.</p>';
}

// Info del personaje
$char_name    = $character ? htmlspecialchars($character['name'], ENT_QUOTES) : 'Sin personaje';
$char_berries = $character ? number_format((int)($character['berries'] ?? 0), 0, ',', '.') : '0';
$char_avatar  = $character ? htmlspecialchars($character['avatar'] ?? '', ENT_QUOTES) : '';
$avatar_fallback = $b_url . '/images/game/avatar_placeholder.png';
$avatar_src   = $char_avatar ?: $avatar_fallback;
$is_approved  = ($character && $character['status'] === 'aprobada') ? 'true' : 'false';

ob_start();
?>
<div class="rpg-shop-container">

  <!-- Hero Header -->
  <div class="rpg-shop-hero">
    <div class="rpg-shop-hero-content">
      <div class="rpg-shop-hero-icon"><i class="fas fa-store-alt"></i></div>
      <div class="rpg-shop-hero-text">
        <h1>Gran Bazar del Mundo</h1>
        <p>Equípate para la aventura. Todo lo que necesitas para surcar los mares.</p>
      </div>
    </div>
    <!-- Saldo del personaje -->
    <div class="rpg-shop-balance-card">
      <img src="<?= $avatar_src ?>" alt="<?= $char_name ?>" class="rpg-shop-balance-avatar">
      <div class="rpg-shop-balance-info">
        <span class="rpg-shop-balance-name"><?= $char_name ?></span>
        <span class="rpg-shop-balance-amount" id="shop-berries-display">
          <i class="fas fa-coins"></i> <span id="shop-berries-value"><?= $char_berries ?></span> B.
        </span>
      </div>
    </div>
  </div>

  <!-- Modo: Comprar / Vender -->
  <div class="rpg-shop-mode-toggle">
    <button type="button" class="rpg-shop-mode-btn rpg-shop-mode-btn--active" id="mode-buy-btn" data-mode="buy">
      <i class="fas fa-shopping-bag"></i> Comprar
    </button>
    <button type="button" class="rpg-shop-mode-btn" id="mode-sell-btn" data-mode="sell">
      <i class="fas fa-hand-holding-usd"></i> Vender mis objetos
    </button>
  </div>

  <!-- MODO COMPRA -->
  <div id="shop-mode-buy" class="rpg-shop-mode-section">
    <!-- Tabs de categoría -->
    <nav class="rpg-shop-tabs" aria-label="Categorías de la tienda">
      <?= $tabs_nav ?>
    </nav>

    <!-- Panels de categorías -->
    <div class="rpg-shop-panels">
      <?= $tabs_panels ?>
    </div>
  </div>

  <!-- MODO VENTA -->
  <div id="shop-mode-sell" class="rpg-shop-mode-section rpg-is-hidden">
    <div class="rpg-shop-sell-header">
      <h2><i class="fas fa-hand-holding-usd"></i> Mis Objetos — Vender al 50 %</h2>
      <p>Recupera la mitad del precio de tus artículos comerciables.</p>
    </div>
    <div class="rpg-shop-sell-list" id="sell-list">
      <?= $sell_html ?>
    </div>
  </div>

</div>

<!-- DRAWER: Carrito de compra -->
<div class="rpg-cart-overlay" id="cart-overlay" aria-hidden="true"></div>
<aside class="rpg-cart-drawer" id="cart-drawer" aria-label="Carrito de compra">
  <div class="rpg-cart-header">
    <h2><i class="fas fa-shopping-cart"></i> Carrito</h2>
    <button type="button" class="rpg-cart-close" id="cart-close-btn" aria-label="Cerrar carrito">
      <i class="fas fa-times"></i>
    </button>
  </div>
  <div class="rpg-cart-body" id="cart-body">
    <p class="rpg-cart-empty" id="cart-empty-msg"><i class="fas fa-box-open"></i> El carrito está vacío.</p>
    <ul class="rpg-cart-list" id="cart-list"></ul>
  </div>
  <div class="rpg-cart-footer">
    <div class="rpg-cart-total">
      <span>Total:</span>
      <span class="rpg-cart-total-value" id="cart-total-display"><i class="fas fa-coins"></i> 0 B.</span>
    </div>
    <button type="button" class="rpg-btn rpg-btn--laton rpg-btn--full" id="cart-checkout-btn" disabled>
      <i class="fas fa-check-circle"></i> Confirmar Compra
    </button>
    <div class="rpg-cart-msg rpg-is-hidden" id="cart-msg"></div>
  </div>
</aside>

<!-- FAB: abrir carrito -->
<button type="button" class="rpg-cart-fab" id="cart-fab" title="Ver carrito">
  <i class="fas fa-shopping-cart"></i>
  <span class="rpg-cart-fab-count rpg-is-hidden" id="cart-fab-count">0</span>
</button>

<?php
$content = ob_get_clean();

// Config JS inyectada sin inline scripts en el HTML (pasa por el footer de la página)
$js_config = '<script>window.TIENDA_CONFIG=' . json_encode([
    'bburl'         => $b_url,
    'my_post_key'   => $my_post_key,
    'character_id'  => $char_id,
    'is_approved'   => ($character && $character['status'] === 'aprobada'),
    'current_berries'=> $character ? (int)($character['berries'] ?? 0) : 0,
], JSON_UNESCAPED_UNICODE) . ';</script>';

$js_src = '<script src="' . $b_url . '/jscripts/game/tienda.js?v=1"></script>';

game_render_page('Tienda — Gran Bazar del Mundo', $content . $js_config . $js_src);
