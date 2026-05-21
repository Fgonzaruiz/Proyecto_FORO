<?php
declare(strict_types=1);

namespace Game\Shared;

final class Env
{
    public static function getString(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        $value = trim((string)$value);
        return $value === '' ? $default : $value;
    }

    public static function getInt(string $key, int $default): int
    {
        $value = self::getString($key);
        if ($value === null) {
            return $default;
        }
        return (int)$value;
    }
}

