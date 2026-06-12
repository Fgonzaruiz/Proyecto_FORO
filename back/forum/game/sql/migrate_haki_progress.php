<?php
declare(strict_types=1);

/**
 * Migración: Crear tabla game_haki_progress.
 */
global $db;
$prefix = TABLE_PREFIX;

$db->write_query("CREATE TABLE IF NOT EXISTS {$prefix}game_haki_progress (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  character_id   INT NOT NULL,
  haki_type      ENUM('kenbunshoku','busoshoku','haoshoku') NOT NULL,
  nivel          TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=no desbloqueado, 1-5=nivel actual',
  usos_total     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Usos acumulados al jugar cartas de este tipo en posts',
  status         ENUM('activo','pendiente_subida') NOT NULL DEFAULT 'activo',
  pp_reservados  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'PP descontados pendientes de confirmación o devolución',
  unlocked_at    DATETIME NULL,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_char_haki (character_id, haki_type),
  FOREIGN KEY (character_id) REFERENCES {$prefix}game_personajes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "<p class='ok'>[OK] Tabla game_haki_progress creada o verificada.</p>";
