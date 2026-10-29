<?php

declare(strict_types=1);

namespace AiSdk\Utils\Errors;

use AiSdk\Exceptions\AiSdkException;
use AiSdk\Exceptions\AuthenticationException;
use AiSdk\Exceptions\InsufficientCreditsException;
use AiSdk\Exceptions\InternalServerException;
use AiSdk\Exceptions\InvalidRequestException;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Exceptions\NotFoundException;
use AiSdk\Exceptions\OverloadedException;
use AiSdk\Exceptions\PermissionDeniedException;
use AiSdk\Exceptions\ProviderException;
use AiSdk\Exceptions\RateLimitException;

/**
 * Maps a provider HTTP error to a normalized SDK exception. Intentionally
 * small: status and explicit billing or authentication signals decide the class.
 */
final class HttpErrorNormalizer
{
    /** Provider error bodies can be whole HTML pages; keep exception messages readable. */
    private const int MAX_MESSAGE_BYTES = 1000;

    private const int MAX_BODY_BYTES = 8192;

    /**
     * @param  array<int|string, mixed>|string|null  $body
     */
    public static function normalize(
        string $provider,
        int $status,
        array|string|null $body,
        ?string $requestId = null,
        ?string $modelId = null,
        ?\Throwable $previous = null,
        ?int $retryAfter = null,
    ): AiSdkException {
        // Some APIs (Google) wrap their error object in a one-element list.
        if (is_array($body) && array_is_list($body) && count($body) === 1 && is_array($body[0])) {
            $body = $body[0];
        }

        $message = self::clean(self::extractMessage($body) ?? "{$provider} request failed with status {$status}.", self::MAX_MESSAGE_BYTES);

        $insufficientCredits = $status === 402 || self::isInsufficientCredits($body, $message);

        $context = [
            'provider' => $provider,
            'status' => $status,
            'requestId' => $requestId,
            'modelId' => $modelId,
            'body' => self::boundedBody($body),
            'retryAfter' => $retryAfter,
            'retryable' => ! $insufficientCredits && in_array($status, [408, 409, 429, 500, 502, 503, 504, 529], true),
        ];

        return match (true) {
            $insufficientCredits => new InsufficientCreditsException($message, $context, $previous),
            $status === 401, self::isAuthenticationFailure($status, $body, $message) => new AuthenticationException($message, $context, $previous),
            $status === 403 => new PermissionDeniedException($message, $context, $previous),
            $status === 404 => new NotFoundException($message, $context, $previous),
            $status === 429 => new RateLimitException($message, $context, $previous),
            $status === 529 => new OverloadedException($message, $context, $previous),
            $status >= 500 => new InternalServerException($message, $context, $previous),
            $status >= 400 => new InvalidRequestException($message, $context, $previous),
            default => new ProviderException($message, $context, $previous),
        };
    }

    /**
     * Some providers answer a bad or missing API key with 400 instead of 401. Recognize those by what the body says.
     *
     * @param  array<int|string, mixed>|string|null  $body
     */
    private static function isAuthenticationFailure(int $status, array|string|null $body, string $message): bool
    {
        if ($status !== 400 && $status !== 403 && $status !== 404) {
            return false;
        }

        if (is_array($body)) {
            $error = is_array($body['error'] ?? null) ? $body['error'] : [];
            $details = is_array($error['details'] ?? null) ? $error['details'] : [];

            foreach ($details as $detail) {
                if (is_array($detail) && in_array($detail['reason'] ?? null, ['API_KEY_INVALID', 'API_KEY_EXPIRED', 'API_KEY_NOT_FOUND'], true)) {
                    return true;
                }
            }

            foreach ([$error['code'] ?? null, $error['type'] ?? null, $body['code'] ?? null] as $code) {
                if (is_string($code) && in_array(strtolower($code), ['invalid_api_key', 'incorrect_api_key', 'authentication_error', 'unauthenticated'], true)) {
                    return true;
                }
            }
        }

        return $status === 400 && preg_match('/\b(?:incorrect|invalid|wrong|missing|bad|expired)\b[^.]{0,30}\bapi[ _-]?key\b|\bapi[ _-]?key\b[^.]{0,30}\b(?:not valid|invalid|incorrect|expired)\b/i', $message) === 1;
    }

    /**
     * Normalize an error delivered inside a successful event stream (HTTP 200), where no status code exists.
     * The provider's own error type or code decides the class so retry and failover logic can act on it.
     */
    public static function fromStreamError(string $provider, mixed $error): AiSdkException
    {
        $body = is_array($error) ? $error : ['message' => is_string($error) ? $error : null];
        if (is_array($body['error'] ?? null)) {
            $body = $body['error'];
        }
        $message = self::clean(self::extractMessage(['error' => $body]) ?? "Provider [{$provider}] returned an error in its event stream.", self::MAX_MESSAGE_BYTES);
        $kind = strtolower(implode(' ', array_filter([
            is_string($body['type'] ?? null) ? $body['type'] : null,
            is_string($body['code'] ?? null) ? $body['code'] : null,
            is_string($body['status'] ?? null) ? $body['status'] : null,
        ])));
        $context = ['provider' => $provider, 'body' => self::boundedBody($body), 'errorType' => is_string($body['type'] ?? null) ? $body['type'] : null, 'errorCode' => is_string($body['code'] ?? null) ? $body['code'] : null];

        return match (true) {
            ($body['status'] ?? null) === 402, ($body['status'] ?? null) === '402', self::isInsufficientCredits($body, $message) => new InsufficientCreditsException($message, $context + ['retryable' => false]),
            str_contains($kind, 'rate_limit'), str_contains($kind, 'resource_exhausted') => new RateLimitException($message, $context + ['retryable' => true]),
            str_contains($kind, 'overloaded'), str_contains($kind, 'unavailable') => new OverloadedException($message, $context + ['retryable' => true]),
            str_contains($kind, 'authentication'), str_contains($kind, 'invalid_api_key'), str_contains($kind, 'unauthenticated') => new AuthenticationException($message, $context),
            str_contains($kind, 'permission') => new PermissionDeniedException($message, $context),
            str_contains($kind, 'not_found') => new NotFoundException($message, $context),
            str_contains($kind, 'invalid_request'), str_contains($kind, 'bad_request'), str_contains($kind, 'context_length'), str_contains($kind, 'invalid_argument') => new InvalidRequestException($message, $context),
            str_contains($kind, 'server_error'), str_contains($kind, 'api_error'), str_contains($kind, 'internal') => new InternalServerException($message, $context + ['retryable' => true]),
            default => new InvalidResponseException($message, $context),
        };
    }

    /**
     * @param  array<int|string, mixed>|string|null  $body
     */
    private static function isInsufficientCredits(array|string|null $body, string $message): bool
    {
        if (is_array($body)) {
            $error = is_array($body['error'] ?? null) ? $body['error'] : [];
            foreach ([$error['code'] ?? null, $error['type'] ?? null, $body['code'] ?? null, $body['type'] ?? null] as $code) {
                if (is_string($code) && in_array(strtolower($code), ['insufficient_quota', 'insufficient_credits', 'billing_hard_limit_reached'], true)) {
                    return true;
                }
            }
        }

        return preg_match('/\b(?:credit balance is too low|exceeded your current quota|out of credits|payment required|insufficient credits|(?:credits?|quota) (?:exhausted|depleted)|exhausted (?:your )?(?:credits?|quota))\b/i', $message) === 1;
    }

    /**
     * @param  array<int|string, mixed>|string|null  $body
     */
    private static function extractMessage(array|string|null $body): ?string
    {
        if (is_string($body)) {
            return trim($body) !== '' ? $body : null;
        }

        if (! is_array($body)) {
            return null;
        }

        $error = $body['error'] ?? null;

        return match (true) {
            is_array($error) && array_key_exists('message', $error) => self::text($error['message']),
            is_string($error) && $error !== '' => $error,
            is_array($error) && $error !== [] => self::text($error),
            default => self::text($body['message'] ?? null),
        };
    }

    /** Only scalar or structured values become text; never rely on implicit array-to-string conversion. */
    private static function text(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => $value !== '' ? $value : null,
            is_int($value), is_float($value) => (string) $value,
            is_array($value) && $value !== [] => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: null,
            default => null,
        };
    }

    /** Remove credentials the provider may echo back, strip markup noise and cap the length. */
    private static function clean(string $message, int $limit): string
    {
        $message = Redactor::text(trim(preg_replace('/\s+/', ' ', strip_tags($message)) ?? $message));

        return strlen($message) > $limit ? mb_strcut($message, 0, $limit) . '…' : $message;
    }

    /**
     * @param  array<int|string, mixed>|string|null  $body
     * @return array<int|string, mixed>|string|null
     */
    private static function boundedBody(array|string|null $body): array|string|null
    {
        if ($body === null) {
            return null;
        }

        $text = is_string($body) ? $body : (json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '');
        $redacted = Redactor::text($text);

        if (strlen($redacted) <= self::MAX_BODY_BYTES) {
            return is_string($body) ? $redacted : (json_decode($redacted, true) ?? $redacted);
        }

        return mb_strcut($redacted, 0, self::MAX_BODY_BYTES) . '… [truncated]';
    }
}
