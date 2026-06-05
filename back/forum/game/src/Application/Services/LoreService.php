<?php
declare(strict_types=1);

namespace Game\Application\Services;

use Exception;

class LoreService {
    /**
     * Carga y procesa el archivo de lore JSON, ordenando y agrupando las eras con sus eventos.
     *
     * @param string $jsonPath Ruta absoluta al archivo lore.json.
     * @return array Estructura de eras ordenadas por start_year (ASC) con sus eventos correspondientes.
     * @throws Exception Si el archivo no existe, no se puede leer o tiene un formato JSON inválido.
     */
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

        $erasRaw = $data['eras'] ?? [];
        $eventosRaw = $data['eventos'] ?? [];

        // 1. Indexar eras por ID para optimizar el emparejamiento
        $eras = [];
        foreach ($erasRaw as $era) {
            $eraId = (int)$era['id'];
            $era['eventos'] = [];
            $eras[$eraId] = $era;
        }

        // 2. Agrupar los eventos en sus respectivas eras basadas en era_id
        foreach ($eventosRaw as $evento) {
            $eraId = (int)$evento['era_id'];
            if (isset($eras[$eraId])) {
                $eras[$eraId]['eventos'][] = $evento;
            }
        }

        // 3. Ordenar eventos de cada era por start_year en orden ascendente (ASC)
        foreach ($eras as $eraId => &$era) {
            usort($era['eventos'], function (array $a, array $b): int {
                return $a['start_year'] <=> $b['start_year'];
            });
        }
        unset($era); // Liberar referencia del ciclo

        // 4. Ordenar las eras por start_year en orden ascendente (ASC)
        usort($eras, function (array $a, array $b): int {
            return $a['start_year'] <=> $b['start_year'];
        });

        return $eras;
    }
}
