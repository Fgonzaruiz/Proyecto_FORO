<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $templates;

$pageTitle = 'Foro RPG — Inicio';

ob_start();
?>
<div class="game-index">
  <div class="game-index-hero">
    <h1 class="game-index-title">
      <i class="fas fa-meteor"></i> Foro RPG
    </h1>
    <p class="game-index-sub">Sistema de juego de rol basado en MyBB</p>
    <div class="game-index-date">
      <i class="fas fa-calendar-alt"></i>
      <?= htmlspecialchars(game_global_rol_date()) ?>
    </div>
  </div>

  <div class="game-index-grid">
    <a href="mis_personajes.php" class="game-card game-card--personajes">
      <i class="fas fa-user-circle"></i>
      <h3>Mis Personajes</h3>
      <p>Gestiona tus fichas de personaje</p>
    </a>
    <a href="biblioteca_personajes.php" class="game-card game-card--biblioteca">
      <i class="fas fa-users"></i>
      <h3>Biblioteca</h3>
      <p>Explora personajes del foro</p>
    </a>
    <a href="tienda.php" class="game-card game-card--economy">
      <i class="fas fa-coins"></i>
      <h3>Tienda</h3>
      <p>Compra cartas con berries</p>
    </a>
    <a href="notificaciones.php" class="game-card game-card--notif">
      <i class="fas fa-bell"></i>
      <h3>Notificaciones</h3>
      <p>Consulta tus alertas</p>
    </a>
  </div>
</div>
<?php
$content = ob_get_clean();

game_render_page($pageTitle, $content);
