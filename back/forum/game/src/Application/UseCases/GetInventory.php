<?php
declare(strict_types=1);

/** @deprecated D001 local — ver GetCharacter.php */

namespace Game\Application\UseCases;

use Game\Infrastructure\Http\MechanicsClient;

final class GetInventory
{
    public function __construct(private MechanicsClient $client)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function run(int $uid): array
    {
        $resp = $this->client->postJson('/inventory/get', ['uid' => $uid]);
        return ['status' => $resp['status'], 'body' => $resp['body']];
    }
}

