<?php

declare(strict_types=1);

namespace AiSdk\Utils\Errors;

/**
 * Removes credentials from strings that end up in exception messages, error
 * context and logs.
 */
final class Redactor
{
    public const string MASK = '[redacted]';

    private const string SENSITIVE_PARAMS = 'key|api[_-]?key|apikey|token|access[_-]?token|auth|authorization|secret|signature|sig|password|x-amz-signature|x-amz-credential|x-amz-security-token';

    private function __construct() {}

    /**
     * Mask the values of credential-looking query parameters and any userinfo
     * in a URL.
     */
    public static function url(string $url): string
    {
        $url = preg_replace('#(://)[^/@\s]+@#', '$1' . self::MASK . '@', $url) ?? $url;

        return preg_replace(
            '/([?&](?:' . self::SENSITIVE_PARAMS . ')=)[^&#\s]*/i',
            '$1' . self::MASK,
            $url,
        ) ?? $url;
    }

    /**
     * Redact URLs, bearer tokens and common API key shapes inside free text.
     */
    public static function text(string $text): string
    {
        $text = preg_replace_callback('#https?://[^\s"\'<>]+|wss?://[^\s"\'<>]+#i', static fn(array $m): string => self::url($m[0]), $text) ?? $text;
        $text = preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]{8,}/i', '$1 ' . self::MASK, $text) ?? $text;

        return preg_replace('/\b(sk|pk|rk|xai|gsk|AIza|hf|r8|fal|nvapi)[-_A-Za-z0-9]{16,}\b/', self::MASK, $text) ?? $text;
    }
}
