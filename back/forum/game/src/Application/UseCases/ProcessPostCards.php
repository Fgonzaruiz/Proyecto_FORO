<?php
namespace Game\Application\UseCases;

class ProcessPostCards
{
    private $db;
    private $prefix;

    public function __construct($db, string $prefix)
    {
        $this->db = $db;
        $this->prefix = $prefix;
    }

    public function execute(int $pid, int $cid, array $postData): void
    {
        $has_cards = !empty($postData['rpg_played_cards']);
        $has_hidden = !empty($postData['rpg_hidden_actions']);
        if (!$has_cards && !$has_hidden) {
            return;
        }

        $equipped_ids = game_postcharacter_get_post_equipped_ids($pid, $cid);
        if (function_exists('game_log_equipped_debug')) {
            game_log_equipped_debug('process_cards', [
                'post_id' => $pid,
                'character_id' => $cid,
                'equipped_ids' => $equipped_ids,
                'has_played_cards' => $has_cards,
                'has_hidden_actions' => $has_hidden,
            ]);
        }

        game_postcharacter_ensure_stat_helpers();

        $stats = [];
        $stats_for_dice = [];
        $pj_q = $this->db->query("SELECT name, stats_json, race_name FROM {$this->prefix}game_personajes WHERE id = {$cid} LIMIT 1");
        $pj = $this->db->fetch_array($pj_q);
        if ($pj) {
            $stats_decoded = json_decode($pj['stats_json'] ?? '{}', true);
            $stats_raw = is_array($stats_decoded) ? $stats_decoded : [];
            $turn_mods = [];
            if (!empty($postData['rpg_modifiers'])) {
                $raw_mods = json_decode($postData['rpg_modifiers'], true);
                if (is_array($raw_mods)) {
                    $valid_stats = ['fue', 'res', 'agi', 'des', 'int', 'esp', 'inst'];
                    foreach ($raw_mods as $mod_stat => $mod_val) {
                        $mod_stat = strtolower(trim((string)$mod_stat));
                        $mod_val = (int)$mod_val;
                        if ($mod_val !== 0 && in_array($mod_stat, $valid_stats, true)) {
                            $turn_mods[$mod_stat] = ($turn_mods[$mod_stat] ?? 0) + $mod_val;
                        }
                    }
                }
            }
            $ctx = game_build_stat_context($stats_raw, (string)($pj['race_name'] ?? ''), $turn_mods);
            $stats = $ctx['trained'];
            $stats_for_dice = $ctx['values'];
        }

        if (!empty($postData['rpg_played_cards'])) {
            $card_ids = json_decode($postData['rpg_played_cards'], true);
            if (is_array($card_ids)) {
                foreach ($card_ids as $c_entry) {
                    game_postcharacter_process_card_entry($pid, $cid, $c_entry, $stats_for_dice, [], 0, $equipped_ids);
                }
            }
        }

        if (!empty($postData['rpg_hidden_actions'])) {
            $hidden_actions = json_decode($postData['rpg_hidden_actions'], true);
            if (is_array($hidden_actions)) {
                $saved_actions = [];
                foreach ($hidden_actions as $action) {
                    $action_idx = (int)($action['index'] ?? 0);
                    if ($action_idx <= 0) continue;
                    
                    $description = isset($action['description']) ? trim((string)$action['description']) : '';
                    $action_cards = isset($action['cards']) && is_array($action['cards']) ? $action['cards'] : [];
                    
                    foreach ($action_cards as $c_entry) {
                        game_postcharacter_process_card_entry($pid, $cid, $c_entry, $stats_for_dice, [], $action_idx, $equipped_ids);
                    }
                    
                    $saved_actions[] = [
                        'index' => $action_idx,
                        'description' => $description,
                        'is_revealed' => 0
                    ];
                }
                
                if (!empty($saved_actions) && $this->db->field_exists('hidden_actions_json', 'game_post_characters')) {
                    $json_str = json_encode($saved_actions, JSON_UNESCAPED_UNICODE);
                    $esc_json = "'" . $this->db->escape_string($json_str) . "'";
                    $this->db->write_query("UPDATE {$this->prefix}game_post_characters SET hidden_actions_json = {$esc_json} WHERE post_id = {$pid} AND character_id = {$cid}");
                }
            }
        }
    }
}
