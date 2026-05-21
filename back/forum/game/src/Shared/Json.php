<?php
declare(strict_types=1);

namespace Game\Shared;

final class Json
{
    /**
     * @param array<string,mixed> $value
     */
    public static function encode(array $value): string
    {
        return (string)json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string,mixed>
     */
    public static function decode(string $raw): array
    {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

