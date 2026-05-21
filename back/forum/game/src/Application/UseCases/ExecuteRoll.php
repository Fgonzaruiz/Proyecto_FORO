<?php
declare(strict_types=1);

namespace Game\Application\UseCases;

use Game\Infrastructure\Http\MechanicsClient;

final class ExecuteRoll
{
    public function __construct(private MechanicsClient $client)
    {
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function run(int $uid, array $input): array
    {
        // Placeholder: delega al backend de mecánicas.
        $resp = $this->client->postJson('/rolls/execute', [
            'uid' => $uid,
            'input' => $input,
        ]);

        return [
            'status' => $resp['status'],
            'body' => $resp['body'],
        ];
    }
}

