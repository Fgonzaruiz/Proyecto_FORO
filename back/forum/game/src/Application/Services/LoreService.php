<?php
declare(strict_types=1);

namespace Game\Application\Services;

use Exception;

class LoreService {

    private static ?array $tiposCache = null;

    private static array $typeMap = [
        'epoca_antigua'      => 'descubrimiento',
        'reliquia'           => 'artefacto',
        'guerra'             => 'guerra',
        'fundacion'          => 'fundacion',
        'fundacion_militar'  => 'fundacion',
        'exploracion'        => 'expedicion',
        'conflicto_racial'   => 'guerra',
        'descubrimiento'     => 'descubrimiento',
        'institucion'        => 'fundacion',
        'pacto_politico'     => 'tratado',
        'pirata_legendario'  => 'legendario',
        'disidencia_celestial' => 'revolucion',
        'movimiento_social'  => 'revolucion',
        'periodo'            => 'poder',
        'alianza'            => 'alianza',
        'organizacion'       => 'fundacion',
        'figura'             => 'legendario',
        'ley'                => 'poder',
        'misterio'           => 'profecia',
        'crisis'             => 'poder',
        'logro'              => 'poder',
        'investigacion'      => 'descubrimiento',
        'caceria'            => 'expedicion',
        'estado'             => 'poder',
        'captura'            => 'traicion',
        'poder'              => 'poder',
    ];

    public static function obtenerTipos(): array {
        if (self::$tiposCache !== null) {
            return self::$tiposCache;
        }
        $path = dirname(__DIR__, 2) . '/Config/lore_types.json';
        if (!file_exists($path)) {
            throw new Exception("El archivo de catálogo de tipos no existe: " . $path);
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new Exception("No se pudo leer el archivo de catálogo de tipos.");
        }
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error al decodificar lore_types.json: " . json_last_error_msg());
        }
        self::$tiposCache = $data;
        return $data;
    }

    public static function obtenerCronologia(string $jsonPath): array {
        if (!file_exists($jsonPath)) {
            throw new Exception("El archivo de lore no existe en la ruta: " . $jsonPath);
        }
        $jsonContent = file_get_contents($jsonPath);
        if ($jsonContent === false) {
            throw new Exception("No se pudo leer el contenido del archivo de lore.");
        }
        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error al decodificar el archivo JSON: " . json_last_error_msg());
        }

        $tipos = self::obtenerTipos();
        $eventTypesIndex = [];
        foreach ($tipos['event_types'] as $et) {
            $eventTypesIndex[$et['id']] = $et;
        }

        $erasRaw = $data['eras'] ?? [];
        $eventosRaw = $data['eventos'] ?? [];
        $loreBasalRaw = $data['lore_basal'] ?? [];

        $eras = [];
        foreach ($erasRaw as $era) {
            $eraId = (int)$era['id'];
            $era['lore_basal'] = [];
            $era['eventos'] = [];
            $eras[$eraId] = $era;
        }

        foreach ($loreBasalRaw as $entry) {
            $eraId = (int)$entry['era_id'];
            if (isset($eras[$eraId])) {
                $eras[$eraId]['lore_basal'][] = $entry;
            }
        }

        foreach ($eventosRaw as $evento) {
            $eraId = (int)$evento['era_id'];
            if (!isset($eras[$eraId])) {
                continue;
            }
            $oldType = $evento['type'] ?? 'otro';
            if (isset($eventTypesIndex[$oldType])) {
                $evento['type'] = $oldType;
            } elseif (isset(self::$typeMap[$oldType])) {
                $newType = self::$typeMap[$oldType];
                $evento['type'] = $newType;
                error_log('[game] LoreService: tipo "' . $oldType . '" mapeado a "' . $newType . '" para evento "' . $evento['name'] . '"');
            } else {
                $evento['type'] = 'otro';
                error_log('[game] LoreService: tipo desconocido "' . $oldType . '" en evento "' . $evento['name'] . '" — asignado "otro"');
            }
            $eras[$eraId]['eventos'][] = $evento;
        }

        foreach ($eras as $eraId => &$era) {
            usort($era['eventos'], function (array $a, array $b): int {
                $cmp = $a['start_year'] <=> $b['start_year'];
                if ($cmp !== 0) return $cmp;
                return ($a['end_year'] - $a['start_year']) <=> ($b['end_year'] - $b['start_year']);
            });

            $era['event_rows'] = self::agruparEnFilas($era['eventos']);
            unset($era['eventos']);
        }
        unset($era);

        usort($eras, function (array $a, array $b): int {
            return $a['start_year'] <=> $b['start_year'];
        });

        return [
            'tipos' => $tipos,
            'eras'  => array_values($eras),
        ];
    }

    private static function agruparEnFilas(array $eventos): array {
        $rows = [];
        foreach ($eventos as $ev) {
            $evStart = (int)$ev['start_year'];
            $evEnd   = (int)$ev['end_year'];
            $placed = false;
            foreach ($rows as &$row) {
                $canJoin = true;
                foreach ($row as $existing) {
                    $exStart = (int)$existing['start_year'];
                    $exEnd   = (int)$existing['end_year'];
                    if ($evStart <= $exEnd && $exStart <= $evEnd) {
                        continue;
                    }
                    $canJoin = false;
                    break;
                }
                if ($canJoin) {
                    $row[] = $ev;
                    $placed = true;
                    break;
                }
            }
            unset($row);
            if (!$placed) {
                $rows[] = [$ev];
            }
        }
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'events'     => $row,
                'is_overlap' => count($row) > 1,
            ];
        }
        return $result;
    }
}
