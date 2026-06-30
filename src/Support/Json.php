<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Exceptions\JsonException;

final class Json
{
    /**
     * @return array<string, mixed>
     */
    public static function decode(string $json, ?string $context = null): array
    {
        $decoded = self::decodeValue($json, $context);

        if (! is_array($decoded)) {
            throw new JsonException(
                message: 'Expected JSON to decode to array' . ($context !== null ? " ({$context})" : '') . '.',
                context: ['raw' => mb_substr($json, 0, 1024)],
            );
        }

        return $decoded;
    }

    public static function decodeValue(string $json, ?string $context = null): mixed
    {
        try {
            return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonException(
                message: 'Failed to decode JSON' . ($context !== null ? " ({$context})" : '') . ': ' . $e->getMessage(),
                context: ['raw' => mb_substr($json, 0, 1024)],
                previous: $e,
            );
        }
    }

    public static function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            throw new JsonException(
                message: 'Failed to encode JSON: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }
}
