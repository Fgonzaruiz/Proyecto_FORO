<?php
declare(strict_types=1);

require_once __DIR__ . '/competencias_v2_seed_data.php';

global $db;
$prefix = TABLE_PREFIX;
$seed = game_competencias_v2_seed_data();

if ($db->table_exists('game_cards')) {
    if (!$db->field_exists('tier', 'game_cards')) {
        $db->write_query("ALTER TABLE {$prefix}game_cards
            ADD COLUMN tier TINYINT UNSIGNED NOT NULL DEFAULT 1
                COMMENT '1-5, tier de poder para requisito de disciplina al asignar carta'");
        echo "<p class='ok'>[OK] game_cards.tier añadida.</p>";
    }
    if (!$db->field_exists('disciplina_slug', 'game_cards')) {
        $db->write_query("ALTER TABLE {$prefix}game_cards
            ADD COLUMN disciplina_slug VARCHAR(64) NULL
                COMMENT 'Disciplina requerida al grado = tier para asignar al PJ'");
        echo "<p class='ok'>[OK] game_cards.disciplina_slug añadida.</p>";
    }
    if (!$db->field_exists('oficio_slug', 'game_cards')) {
        $db->write_query("ALTER TABLE {$prefix}game_cards
            ADD COLUMN oficio_slug VARCHAR(64) NULL
                COMMENT 'Oficio requerido para asignar la carta al PJ'");
        echo "<p class='ok'>[OK] game_cards.oficio_slug añadida.</p>";
    }
}

if ($db->table_exists('game_oficios')) {
    if (!$db->field_exists('grado_unlock_json', 'game_oficios')) {
        $db->write_query("ALTER TABLE {$prefix}game_oficios
            ADD COLUMN grado_unlock_json JSON NULL
                COMMENT 'Desbloqueos por grado I-V para la UI de ficha'");
        echo "<p class='ok'>[OK] game_oficios.grado_unlock_json añadida.</p>";
    }

    foreach ($seed['oficios'] as [$slug, $name, $desc, $cat, $icon, $sort]) {
        $escSlug = $db->escape_string($slug);
        $q = $db->query("SELECT 1 FROM {$prefix}game_oficios WHERE slug = '{$escSlug}' LIMIT 1");
        if ($db->num_rows($q)) {
            continue;
        }
        $escName = $db->escape_string($name);
        $escDesc = $db->escape_string($desc);
        $escCat = $db->escape_string($cat);
        $escIcon = $db->escape_string($icon);
        $db->write_query("INSERT INTO {$prefix}game_oficios (slug, name, description, category, icon, is_active, sort_order)
            VALUES ('{$escSlug}', '{$escName}', '{$escDesc}', '{$escCat}', '{$escIcon}', 1, {$sort})");
        echo "<p class='ok'>[OK] Oficio seed: {$slug}</p>";
    }

    foreach ($seed['oficio_unlocks'] as $slug => $unlocks) {
        $json = $db->escape_string(json_encode($unlocks, JSON_UNESCAPED_UNICODE));
        $escSlug = $db->escape_string($slug);
        $db->write_query("UPDATE {$prefix}game_oficios SET grado_unlock_json = '{$json}' WHERE slug = '{$escSlug}'");
    }
    echo "<p class='ok'>[OK] grado_unlock_json de oficios actualizado.</p>";
}

if ($db->table_exists('game_disciplinas')) {
    if (!$db->field_exists('grado_unlock_json', 'game_disciplinas')) {
        $db->write_query("ALTER TABLE {$prefix}game_disciplinas
            ADD COLUMN grado_unlock_json JSON NULL
                COMMENT 'Desbloqueos por grado I-V para la UI de ficha'");
        echo "<p class='ok'>[OK] game_disciplinas.grado_unlock_json añadida.</p>";
    }
    if (!$db->field_exists('requires_esp_rank', 'game_disciplinas')) {
        $db->write_query("ALTER TABLE {$prefix}game_disciplinas
            ADD COLUMN requires_esp_rank TINYINT UNSIGNED NULL
                COMMENT 'ESP efectivo mínimo para adquirir'");
        echo "<p class='ok'>[OK] game_disciplinas.requires_esp_rank añadida.</p>";
    }
    if (!$db->field_exists('staff_grant_only', 'game_disciplinas')) {
        $db->write_query("ALTER TABLE {$prefix}game_disciplinas
            ADD COLUMN staff_grant_only TINYINT(1) NOT NULL DEFAULT 0
                COMMENT '1 = solo staff puede conceder'");
        echo "<p class='ok'>[OK] game_disciplinas.staff_grant_only añadida.</p>";
    }
    if (!$db->field_exists('fixed_pp_cost', 'game_disciplinas')) {
        $db->write_query("ALTER TABLE {$prefix}game_disciplinas
            ADD COLUMN fixed_pp_cost INT UNSIGNED NULL
                COMMENT 'Coste fijo en PP; ignora escala exponencial'");
        echo "<p class='ok'>[OK] game_disciplinas.fixed_pp_cost añadida.</p>";
    }

    foreach ($seed['disciplinas_haki'] as $row) {
        [$slug, $name, $desc, $cat, $icon, $sort, $espRank, $staffOnly, $fixedPp] = $row;
        $escSlug = $db->escape_string($slug);
        $q = $db->query("SELECT id FROM {$prefix}game_disciplinas WHERE slug = '{$escSlug}' LIMIT 1");
        $escName = $db->escape_string($name);
        $escDesc = $db->escape_string($desc);
        $escCat = $db->escape_string($cat);
        $escIcon = $db->escape_string($icon);
        $espSql = $espRank !== null ? (int)$espRank : 'NULL';
        $staffSql = (int)$staffOnly;
        $fixedSql = $fixedPp !== null ? (int)$fixedPp : 'NULL';

        if ($db->num_rows($q)) {
            $db->write_query("UPDATE {$prefix}game_disciplinas
                SET requires_esp_rank = {$espSql}, staff_grant_only = {$staffSql}, fixed_pp_cost = {$fixedSql}
                WHERE slug = '{$escSlug}'");
            continue;
        }
        $db->write_query("INSERT INTO {$prefix}game_disciplinas
            (slug, name, description, category, icon, is_active, sort_order, requires_esp_rank, staff_grant_only, fixed_pp_cost)
            VALUES ('{$escSlug}', '{$escName}', '{$escDesc}', '{$escCat}', '{$escIcon}', 1, {$sort}, {$espSql}, {$staffSql}, {$fixedSql})");
        echo "<p class='ok'>[OK] Disciplina Haki seed: {$slug}</p>";
    }

    foreach ($seed['disciplina_unlocks'] as $slug => $unlocks) {
        $json = $db->escape_string(json_encode($unlocks, JSON_UNESCAPED_UNICODE));
        $escSlug = $db->escape_string($slug);
        $db->write_query("UPDATE {$prefix}game_disciplinas SET grado_unlock_json = '{$json}' WHERE slug = '{$escSlug}'");
    }
    echo "<p class='ok'>[OK] grado_unlock_json de disciplinas actualizado.</p>";
}
