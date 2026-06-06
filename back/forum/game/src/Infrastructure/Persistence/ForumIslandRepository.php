<?php
declare(strict_types=1);

namespace Game\Infrastructure\Persistence;

class ForumIslandRepository
{
    private \DB_MySQL $db;
    private string $prefix;

    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->prefix = TABLE_PREFIX;
    }

    public function findByFid(int $fid): ?array
    {
        $q = $this->db->query("SELECT * FROM {$this->prefix}game_forum_islands WHERE fid = {$fid} LIMIT 1");
        $row = $this->db->fetch_array($q);
        return $row ?: null;
    }

    public function save(int $fid, array $data): void
    {
        $img = $this->db->escape_string($data['island_image'] ?? '');
        $leader = $this->db->escape_string($data['leader_name'] ?? '');
        $desc = $this->db->escape_string($data['description'] ?? '');
        $terrain = $this->db->escape_string($data['terrain'] ?? '');
        $climate = $this->db->escape_string($data['climate'] ?? '');
        $ctemp = $this->db->escape_string($data['climate_temp'] ?? '');
        $cwind = $this->db->escape_string($data['climate_wind'] ?? '');
        $cprecip = $this->db->escape_string($data['climate_precip'] ?? '');
        $buildings = $this->db->escape_string($data['buildings'] ?? '');
        $defenses = $this->db->escape_string($data['defenses'] ?? '');
        $resources = $this->db->escape_string($data['resources'] ?? '');

        $existing = $this->findByFid($fid);
        if ($existing) {
            $this->db->write_query("UPDATE {$this->prefix}game_forum_islands SET island_image='{$img}', leader_name='{$leader}', description='{$desc}', terrain='{$terrain}', climate='{$climate}', climate_temp='{$ctemp}', climate_wind='{$cwind}', climate_precip='{$cprecip}', buildings='{$buildings}', defenses='{$defenses}', resources='{$resources}' WHERE fid={$fid}");
        } else {
            $this->db->write_query("INSERT INTO {$this->prefix}game_forum_islands (fid, island_image, leader_name, description, terrain, climate, climate_temp, climate_wind, climate_precip, buildings, defenses, resources) VALUES ({$fid}, '{$img}', '{$leader}', '{$desc}', '{$terrain}', '{$climate}', '{$ctemp}', '{$cwind}', '{$cprecip}', '{$buildings}', '{$defenses}', '{$resources}')");
        }
    }

    /** @return list<array> */
    public function findAllWithForumData(): array
    {
        $q = $this->db->query("SELECT f.fid, f.name, f.description, f.type, i.island_image, i.leader_name, i.description as island_desc, i.terrain, i.climate, i.climate_temp, i.climate_wind, i.climate_precip, i.buildings, i.defenses, i.resources FROM {$this->prefix}forums f LEFT JOIN {$this->prefix}game_forum_islands i ON i.fid = f.fid WHERE f.type = 'f' ORDER BY f.disporder");
        $rows = [];
        while ($row = $this->db->fetch_array($q)) {
            $rows[] = $row;
        }
        return $rows;
    }
}
