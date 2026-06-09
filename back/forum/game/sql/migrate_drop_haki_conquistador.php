<?php
declare(strict_types=1);

/**
 * Elimina Haki de Conquistador del catálogo de disciplinas (staff-only, no creación PJ).
 */

global $db;
$prefix = TABLE_PREFIX;
$slug = 'haki_conquistador';

if (!$db->table_exists('game_disciplinas')) {
    echo "<p class='skip'>[SKIP] game_disciplinas no existe.</p>";
    return;
}

$escSlug = $db->escape_string($slug);
$q = $db->query("SELECT id FROM {$prefix}game_disciplinas WHERE slug = '{$escSlug}' LIMIT 1");
$row = $db->fetch_array($q);
if (!$row) {
    echo "<p class='skip'>[SKIP] Disciplina {$slug} no encontrada.</p>";
    return;
}

$did = (int)$row['id'];

if ($db->table_exists('game_character_disciplinas')) {
    $db->write_query("DELETE FROM {$prefix}game_character_disciplinas WHERE disciplina_id = {$did}");
    echo "<p class='ok'>[OK] Vínculos PJ eliminados para {$slug}.</p>";
}

$db->write_query("DELETE FROM {$prefix}game_disciplinas WHERE id = {$did}");
echo "<p class='ok'>[OK] Disciplina eliminada: {$slug}.</p>";
