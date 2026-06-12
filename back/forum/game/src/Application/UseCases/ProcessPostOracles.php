<?php
namespace Game\Application\UseCases;

class ProcessPostOracles
{
    private $db;
    private $prefix;

    public function __construct($db, string $prefix)
    {
        $this->db = $db;
        $this->prefix = $prefix;
    }

    public function execute(int $pid, int $cid, string $oraclesJson): void
    {
        if (empty($oraclesJson)) return;

        if (!$this->db->table_exists('game_post_oracles') || !$this->db->table_exists('game_oracles')) {
            if (function_exists('game_log_post_rpg')) {
                game_log_post_rpg('oracles_skip_tables_missing', ['post_id' => $pid]);
            }
            return;
        }

        $oracle_ids = json_decode($oraclesJson, true);
        if (!is_array($oracle_ids) || $oracle_ids === []) {
            return;
        }

        $category = function_exists('game_get_post_category') ? game_get_post_category($pid) : '';
        $saved = 0;

        foreach ($oracle_ids as $oid) {
            $oid = (int)$oid;
            if ($oid <= 0) continue;

            $oq = $this->db->query("SELECT * FROM {$this->prefix}game_oracles WHERE id = {$oid} LIMIT 1");
            $oracle = $this->db->fetch_array($oq);
            if (!$oracle) {
                if (function_exists('game_log_post_rpg')) {
                    game_log_post_rpg('oracle_not_found', ['post_id' => $pid, 'oracle_id' => $oid]);
                }
                continue;
            }

            $result = game_roll_oracle($oracle, $category);

            $insert = [
                'post_id' => $pid,
                'character_id' => $cid,
                'oracle_id' => $oid,
                'roll_value' => $this->db->escape_string((string)$result['roll']),
                'result_range' => $this->db->escape_string($result['range']),
                'result_text' => $this->db->escape_string($result['result']),
                'result_description' => $this->db->escape_string($result['description'] ?? ''),
                'auto_invoked' => 0,
            ];

            $this->db->insert_query('game_post_oracles', $insert);
            $post_oracle_id = (int)$this->db->insert_id();
            $saved++;

            $auto_invoke = $result['auto_invoke'] ?? null;
            if ($auto_invoke && !empty($auto_invoke['oracle_id'])) {
                $invoke_id = (int)$auto_invoke['oracle_id'];
                $auto_q = $this->db->query("SELECT * FROM {$this->prefix}game_oracles WHERE id = {$invoke_id} LIMIT 1");
                if ($auto_row = $this->db->fetch_array($auto_q)) {
                    $auto_result = game_roll_oracle($auto_row, $category);
                    $auto_insert = [
                        'post_id' => $pid,
                        'character_id' => $cid,
                        'oracle_id' => $invoke_id,
                        'roll_value' => $this->db->escape_string((string)$auto_result['roll']),
                        'result_range' => $this->db->escape_string($auto_result['range']),
                        'result_text' => $this->db->escape_string($auto_result['result']),
                        'result_description' => $this->db->escape_string($auto_result['description'] ?? ''),
                        'auto_invoked' => 1,
                        'invoked_by_post_oracle_id' => $post_oracle_id,
                    ];
                    $this->db->insert_query('game_post_oracles', $auto_insert);
                    $saved++;
                }
            }
        }

        if (function_exists('game_log_post_rpg')) {
            game_log_post_rpg('oracles_saved', [
                'post_id' => $pid,
                'character_id' => $cid,
                'requested' => count($oracle_ids),
                'saved' => $saved,
            ]);
        }
    }
}
