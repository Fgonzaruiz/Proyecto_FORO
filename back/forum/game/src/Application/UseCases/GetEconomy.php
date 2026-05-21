<?php
declare(strict_types=1);

namespace Game\Application\UseCases;

use Game\Infrastructure\Http\MechanicsClient;

final class GetEconomy
{
    public function __construct(private MechanicsClient $client)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function run(int $uid): array
    {
        $resp = $this->client->postJson('/economy/get', ['uid' => $uid]);
        return ['status' => $resp['status'], 'body' => $resp['body']];
    }
}

