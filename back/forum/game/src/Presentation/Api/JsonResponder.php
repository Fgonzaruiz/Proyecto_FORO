<?php
declare(strict_types=1);

namespace Game\Presentation\Api;

use Game\Shared\Json;

final class JsonResponder
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $meta
     */
    public static function ok(array $data, array $meta = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo Json::encode([
            'ok' => true,
            'data' => $data,
            'error' => null,
            'meta' => $meta,
        ]);
    }

    /**
     * @param array{code:string,message:string} $error
     * @param array<string,mixed> $meta
     */
    public static function fail(int $httpCode, array $error, array $meta = []): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo Json::encode([
            'ok' => false,
            'data' => null,
            'error' => $error,
            'meta' => $meta,
        ]);
    }
}

