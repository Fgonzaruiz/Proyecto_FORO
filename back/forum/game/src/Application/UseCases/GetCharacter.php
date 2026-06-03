<?php
declare(strict_types=1);

/**
 * @deprecated No usar — D001: mecánicas en MySQL local. Endpoints ajax devuelven 501.
 * MechanicsClient no está implementado.
 */

namespace Game\Application\UseCases;

use Game\Infrastructure\Http\MechanicsClient;

final class GetCharacter
{
    public function __construct(private MechanicsClient $client)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function run(int $uid): array
    {
        // Placeholder: backend decide qué personaje corresponde al usuario.
        $resp = $this->client->postJson('/character/get', ['uid' => $uid]);
        return ['status' => $resp['status'], 'body' => $resp['body']];
    }
}

