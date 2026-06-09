<?php
declare(strict_types=1);

/**
 * Elimina Haki de Observación, Armamento y Conquistador del catálogo de disciplinas.
 * El Haki sigue gestionándose vía cartas (card_type haki), no como disciplina.
 */

global $db;
$prefix = TABLE_PREFIX;

$slugs = ['haki_observacion', 'haki_armamento', 'haki_conquistador'];

if (!$db->table_exists('game_disciplinas')) {
    echo "<p class='skip'>[SKIP] game_disciplinas no existe.</p>";
    return;
}

foreach ($slugs as $slug) {
    $escSlug = $db->escape_string($slug);
    $q = $db->query("SELECT id FROM {$prefix}game_disciplinas WHERE slug = '{$escSlug}' LIMIT 1");
    $row = $db->fetch_array($q);
    if (!$row) {
        echo "<p class='skip'>[SKIP] Disciplina {$slug} no encontrada.</p>";
        continue;
    }
    $did = (int)$row['id'];

    if ($db->table_exists('game_character_disciplinas')) {
        $db->write_query("DELETE FROM {$prefix}game_character_disciplinas WHERE disciplina_id = {$did}");
        echo "<p class='ok'>[OK] Vínculos PJ eliminados para {$slug}.</p>";
    }

    $db->write_query("DELETE FROM {$prefix}game_disciplinas WHERE id = {$did}");
    echo "<p class='ok'>[OK] Disciplina eliminada: {$slug}.</p>";
}
