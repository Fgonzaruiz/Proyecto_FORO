<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $templates;

$pageTitle = 'Foro RPG — Inicio';

ob_start();
?>
<div class="game-index" style="max-width:1200px;margin:0 auto;padding:40px 20px;">
  <div style="text-align:center;margin-bottom:60px;">
    <h1 style="font-family:var(--font-heading);font-size:36px;color:var(--text-primary);margin-bottom:10px;">
      <i class="fas fa-meteor" style="color:var(--accent-indigo);"></i> Foro RPG
    </h1>
    <p style="color:var(--text-muted);font-size:16px;">Sistema de juego de rol basado en MyBB</p>
    <div style="margin-top:20px;font-size:14px;color:var(--text-secondary);background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:12px 24px;display:inline-flex;align-items:center;gap:10px;">
      <i class="fas fa-calendar-alt" style="color:var(--accent-indigo);"></i>
      <?= htmlspecialchars(game_global_rol_date()) ?>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;">
    <a href="mis_personajes.php" class="game-card" style="display:flex;flex-direction:column;align-items:center;padding:30px;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-lg);text-decoration:none;color:inherit;transition:all 0.2s;">
      <i class="fas fa-user-circle" style="font-size:40px;color:var(--accent-indigo);margin-bottom:15px;"></i>
      <h3 style="font-family:var(--font-heading);font-size:18px;color:var(--text-primary);margin-bottom:5px;">Mis Personajes</h3>
      <p style="font-size:13px;color:var(--text-muted);text-align:center;">Gestiona tus fichas de personaje</p>
    </a>
    <a href="biblioteca_personajes.php" class="game-card" style="display:flex;flex-direction:column;align-items:center;padding:30px;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-lg);text-decoration:none;color:inherit;transition:all 0.2s;">
      <i class="fas fa-users" style="font-size:40px;color:var(--accent-purple);margin-bottom:15px;"></i>
      <h3 style="font-family:var(--font-heading);font-size:18px;color:var(--text-primary);margin-bottom:5px;">Biblioteca</h3>
      <p style="font-size:13px;color:var(--text-muted);text-align:center;">Explora personajes del foro</p>
    </a>
    <a href="economy.php" class="game-card" style="display:flex;flex-direction:column;align-items:center;padding:30px;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-lg);text-decoration:none;color:inherit;transition:all 0.2s;">
      <i class="fas fa-coins" style="font-size:40px;color:var(--accent-amber);margin-bottom:15px;"></i>
      <h3 style="font-family:var(--font-heading);font-size:18px;color:var(--text-primary);margin-bottom:5px;">Econom&iacute;a</h3>
      <p style="font-size:13px;color:var(--text-muted);text-align:center;">Tu bolsillo y transacciones</p>
    </a>
    <a href="inventory.php" class="game-card" style="display:flex;flex-direction:column;align-items:center;padding:30px;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-lg);text-decoration:none;color:inherit;transition:all 0.2s;">
      <i class="fas fa-box" style="font-size:40px;color:var(--accent-teal);margin-bottom:15px;"></i>
      <h3 style="font-family:var(--font-heading);font-size:18px;color:var(--text-primary);margin-bottom:5px;">Inventario</h3>
      <p style="font-size:13px;color:var(--text-muted);text-align:center;">Objetos y equipo</p>
    </a>
    <a href="rolls.php" class="game-card" style="display:flex;flex-direction:column;align-items:center;padding:30px;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-lg);text-decoration:none;color:inherit;transition:all 0.2s;">
      <i class="fas fa-dice-d20" style="font-size:40px;color:var(--accent-pink);margin-bottom:15px;"></i>
      <h3 style="font-family:var(--font-heading);font-size:18px;color:var(--text-primary);margin-bottom:5px;">Tiradas</h3>
      <p style="font-size:13px;color:var(--text-muted);text-align:center;">Lanza los dados</p>
    </a>
    <a href="notificaciones.php" class="game-card" style="display:flex;flex-direction:column;align-items:center;padding:30px;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-lg);text-decoration:none;color:inherit;transition:all 0.2s;">
      <i class="fas fa-bell" style="font-size:40px;color:var(--accent-orange);margin-bottom:15px;"></i>
      <h3 style="font-family:var(--font-heading);font-size:18px;color:var(--text-primary);margin-bottom:5px;">Notificaciones</h3>
      <p style="font-size:13px;color:var(--text-muted);text-align:center;">Consulta tus alertas</p>
    </a>
  </div>
</div>
<?php
$content = ob_get_clean();

game_render_page($pageTitle, $content);


