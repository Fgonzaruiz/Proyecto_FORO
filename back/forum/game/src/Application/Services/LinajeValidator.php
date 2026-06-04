<?php
declare(strict_types=1);

namespace Game\Application\Services;

/**
 * Valida y recalcula Factor Linaje v2 en servidor (catálogo linaje_system.json).
 */
final class LinajeValidator
{
    private array $catalog;

    public function __construct(?array $catalog = null)
    {
        if ($catalog !== null) {
            $this->catalog = $catalog;
            return;
        }
        $path = dirname(__DIR__, 3) . '/data/linaje_system.json';
        $this->catalog = [];
        if (is_file($path)) {
            $decoded = json_decode((string)file_get_contents($path), true);
            if (is_array($decoded)) {
                $this->catalog = $decoded;
            }
        }
    }

    public function getMaxLinajePoints(string $raceName): int
    {
        $ptsPorRaza = $this->catalog['puntos_linaje_por_raza'] ?? [];
        $base = 4;
        if (preg_match('/Híbrid[o|a]\s*\(([^\\/]+)\s*\\/\s*([^)]+)\)/iu', $raceName, $m)) {
            $dom = trim($m[1]);
            $ptsDom = (int)($ptsPorRaza[$dom] ?? 20);
            return max(0, $ptsDom - 4);
        }
        return (int)($ptsPorRaza[$raceName] ?? $base);
    }

    /**
     * @param array<string, mixed> $linaje Input del cliente (pasivas, elegidos_*)
     * @return array{ok:bool, message?:string, linaje?:array}
     */
    public function validateAndBuild(string $raceName, array $linaje): array
    {
        $pasivas = $this->normalizeIdList($linaje['pasivas'] ?? []);
        $racial = $this->normalizeIdList($linaje['elegidos_racial'] ?? []);
        $general = $this->normalizeIdList($linaje['elegidos_general'] ?? []);

        $maxPoints = $this->getMaxLinajePoints($raceName);
        $spent = 0;
        foreach (array_merge($racial, $general) as $id) {
            $perk = $this->findPerk($id);
            $spent += (int)($perk['cost'] ?? 1);
        }

        if ($spent > $maxPoints) {
            return [
                'ok' => false,
                'message' => "Puntos de linaje inválidos: gastados {$spent}, máximo {$maxPoints}.",
            ];
        }

        $sobrante = $maxPoints - $spent;
        $bonusPp = (int)floor($sobrante / 2.0);

        return [
            'ok' => true,
            'linaje' => [
                'version' => 2,
                'pasivas' => $pasivas,
                'elegidos_racial' => $racial,
                'elegidos_general' => $general,
                'maxPoints' => $maxPoints,
                'usedPoints' => $spent,
                'sobrantePoints' => $sobrante,
                'bonusPP' => $bonusPp,
                'maxSlotsRacial' => 2,
                'maxSlotsGeneral' => 2,
            ],
        ];
    }

    /** @return list<string> */
    private function normalizeIdList($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $id) {
            $id = trim((string)$id);
            if ($id !== '') {
                $out[] = $id;
            }
        }
        return array_values(array_unique($out));
    }

    /** @return array{cost?:int} */
    private function findPerk(string $id): array
    {
        foreach ($this->catalog['pasivas_primarias'] ?? [] as $list) {
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $p) {
                if (($p['id'] ?? '') === $id) {
                    return $p;
                }
            }
        }
        foreach ($this->catalog['pasivas_secundarias'] ?? [] as $list) {
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $p) {
                if (($p['id'] ?? '') === $id) {
                    return $p;
                }
            }
        }
        foreach ($this->catalog['arboles_raciales'] ?? [] as $tree) {
            foreach ($tree['perks'] ?? [] as $p) {
                if (($p['id'] ?? '') === $id) {
                    return $p;
                }
            }
        }
        foreach ($this->catalog['arbol_general'] ?? [] as $cat) {
            foreach ($cat['perks'] ?? [] as $p) {
                if (($p['id'] ?? '') === $id) {
                    return $p;
                }
            }
        }
        return ['cost' => 1];
    }
}
