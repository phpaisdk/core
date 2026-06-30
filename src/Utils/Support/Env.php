<?php

declare(strict_types=1);

namespace AiSdk\Utils\Support;

use AiSdk\Exceptions\MissingApiKeyException;

final class Env
{
    public static function loadApiKey(?string $explicit, string $envVar, string $provider): string
    {
        $key = $explicit ?? self::read($envVar);

        if ($key === null || $key === '') {
            throw MissingApiKeyException::forProvider($provider, $envVar);
        }

        return $key;
    }

    public static function loadOptionalSetting(?string $explicit, string $envVar): ?string
    {
        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        return self::read($envVar);
    }

    private static function read(string $envVar): ?string
    {
        $value = $_ENV[$envVar] ?? $_SERVER[$envVar] ?? getenv($envVar);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
