<?php
declare(strict_types=1);

namespace Game\Application\Services;

use Game\Infrastructure\Persistence\NenRepository;

/**
 * Servicio de negocio para la gestión del Nen, progresión y habilidades (Hatsu).
 */
final class NenService
{
    private NenRepository $repository;

    public function __construct(?NenRepository $repository = null)
    {
        $this->repository = $repository ?? new NenRepository();
    }

    /**
     * Obtiene el estado de Nen completo de un personaje.
     * Devuelve null si el personaje no tiene el Nen despierto.
     */
    public function getNenState(int $pjId): ?array
    {
        if ($pjId <= 0) {
            return null;
        }

        $nen = $this->repository->getNen($pjId);
        if (!$nen) {
            return null;
        }

        $principles = $this->repository->getPrinciples($pjId);
        $abilities = $this->repository->getAbilities($pjId);

        // Rellenar principios si faltan en DB
        $allPrinciples = ['ten', 'zetsu', 'ren', 'hatsu'];
        foreach ($allPrinciples as $p) {
            if (!isset($principles[$p])) {
                $principles[$p] = [
                    'level' => 0,
                    'experience' => 0,
                    'unlocked_at' => null
                ];
            }
        }

        $vows = json_decode($nen['vows_json'] ?? '[]', true) ?: [];

        return [
            'character_id' => $pjId,
            'nen_type' => $nen['nen_type'],
            'nen_type_locked' => (int)$nen['nen_type_locked'] === 1,
            'aura_color' => $nen['aura_color'] ?? '',
            'vows' => $vows,
            'notes' => $nen['notes'] ?? '',
            'principles' => $principles,
            'abilities' => $abilities,
        ];
    }

    /**
     * Inicializa el despertar de Nen de un personaje.
     */
    public function despertarNen(int $pjId): void
    {
        if ($pjId <= 0) {
            return;
        }

        // Crear registro principal
        $this->repository->saveNen($pjId, null, 0);

        // Inicializar principios en nivel 1 (Hatsu en 0)
        $this->repository->savePrincipleProgress($pjId, 'ten', 1);
        $this->repository->savePrincipleProgress($pjId, 'zetsu', 1);
        $this->repository->savePrincipleProgress($pjId, 'ren', 1);
        $this->repository->savePrincipleProgress($pjId, 'hatsu', 0);
    }

    /**
     * Establece de forma permanente el tipo de Nen del personaje (Prueba de la Taza).
     */
    public function setNenType(int $pjId, string $type): bool
    {
        $validTypes = [
            'enhancement', 'transmutation', 'emission',
            'conjuration', 'manipulation', 'specialization'
        ];

        if (!in_array($type, $validTypes, true)) {
            return false;
        }

        $state = $this->getNenState($pjId);
        if (!$state) {
            return false; // Debe estar despierto
        }

        $this->repository->saveNen(
            $pjId,
            $type,
            1, // locked = 1
            $state['aura_color'] ?: null,
            json_encode($state['vows']),
            $state['notes']
        );

        return true;
    }

    /**
     * Entrena o aumenta el nivel de un principio Nen.
     */
    public function trainPrinciple(int $pjId, string $principle, int $newLevel): bool
    {
        $validPrinciples = ['ten', 'zetsu', 'ren', 'hatsu'];
        if (!in_array($principle, $validPrinciples, true)) {
            return false;
        }

        if ($newLevel < 0 || $newLevel > 4) {
            return false;
        }

        $state = $this->getNenState($pjId);
        if (!$state) {
            return false; // Debe estar despierto
        }

        // No se puede entrenar Hatsu si no tiene un tipo de Nen definido
        if ($principle === 'hatsu' && !$state['nen_type_locked']) {
            return false;
        }

        $this->repository->savePrincipleProgress($pjId, $principle, $newLevel);
        return true;
    }

    /**
     * Registra una nueva propuesta de habilidad (Hatsu).
     */
    public function proponerHabilidad(int $pjId, string $name, string $desc, string $rank, int $cost, array $conditions): int
    {
        if ($pjId <= 0 || trim($name) === '' || trim($desc) === '') {
            return 0;
        }

        $validRanks = ['D', 'C', 'B', 'A', 'S', 'SS'];
        if (!in_array($rank, $validRanks, true)) {
            $rank = 'D';
        }

        $state = $this->getNenState($pjId);
        if (!$state || !$state['nen_type_locked']) {
            return 0; // Debe tener Nen y tipo definido
        }

        $conditionsJson = json_encode($conditions, JSON_UNESCAPED_UNICODE);

        return $this->repository->saveAbility(
            $pjId,
            $name,
            $desc,
            $rank,
            $cost,
            $conditionsJson,
            null, // No card associated yet
            0 // approved = 0
        );
    }

    /**
     * Aprueba una habilidad Nen y la asocia a una carta técnica generada.
     */
    public function aprobarHabilidad(int $abilityId, int $cardId): bool
    {
        if ($abilityId <= 0 || $cardId <= 0) {
            return false;
        }

        $ability = $this->repository->getAbility($abilityId);
        if (!$ability) {
            return false;
        }

        $this->repository->approveAbility($abilityId, $cardId);
        return true;
    }

    /**
     * Rechaza o elimina una propuesta de habilidad.
     */
    public function rechazarHabilidad(int $abilityId): bool
    {
        if ($abilityId <= 0) {
            return false;
        }

        $ability = $this->repository->getAbility($abilityId);
        if (!$ability) {
            return false;
        }

        $this->repository->deleteAbility($abilityId);
        return true;
    }

    /**
     * Borra todo el Nen del personaje para repetir la prueba Mizushigure.
     */
    public function resetNen(int $pjId): void
    {
        if ($pjId <= 0) {
            return;
        }
        $this->repository->resetNen($pjId);
    }
}
