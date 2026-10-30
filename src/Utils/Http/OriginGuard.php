<?php

declare(strict_types=1);

namespace AiSdk\Utils\Http;

use AiSdk\Exceptions\InvalidResponseException;

/** Validates provider follow-up origins before credentials are attached. */
final class OriginGuard
{
    /**
     * Siblings are allowed only when the configured base matches an explicitly
     * listed official base origin. Paths, queries and fragments do not affect
     * origins; scheme, case-insensitive host and effective port must match.
     *
     * @param  list<string>  $allowedOrigins  Explicit sibling origins (no wildcards).
     * @param  list<string>  $officialBaseOrigins  Official configurations enabling siblings.
     *
     * @throws InvalidResponseException
     */
    public static function assertSameOrigin(
        string $url,
        string $baseUrl,
        array $allowedOrigins = [],
        array $officialBaseOrigins = [],
    ): void {
        $actual = self::origin($url);
        $expected = self::origin($baseUrl);
        if ($actual !== null && $expected !== null) {
            if ($actual === $expected) {
                return;
            }
            if (in_array($expected, array_map(self::origin(...), $officialBaseOrigins), true)
                && in_array($actual, array_map(self::origin(...), $allowedOrigins), true)) {
                return;
            }
        }

        throw new InvalidResponseException('Provider follow-up URL has an untrusted origin.');
    }

    private static function origin(string $value): ?string
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $value) === 1) {
            return null;
        }
        $parts = parse_url($value);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true) || ($parts['port'] ?? 1) < 1) {
            return null;
        }

        return $scheme . '://' . strtolower($parts['host']) . ':' . ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    }
}
