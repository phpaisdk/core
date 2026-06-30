<?php

declare(strict_types=1);

namespace AiSdk\Utils\Errors;

use AiSdk\Exceptions\AiSdkException;
use AiSdk\Exceptions\AuthenticationException;
use AiSdk\Exceptions\InternalServerException;
use AiSdk\Exceptions\InvalidRequestException;
use AiSdk\Exceptions\NotFoundException;
use AiSdk\Exceptions\OverloadedException;
use AiSdk\Exceptions\PermissionDeniedException;
use AiSdk\Exceptions\ProviderException;
use AiSdk\Exceptions\RateLimitException;

/**
 * Maps a provider HTTP error to a normalized SDK exception. Intentionally
 * small: status code drives the class, body provides message/context.
 */
final class HttpErrorNormalizer
{
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
        $message = self::extractMessage($body) ?? "{$provider} request failed with status {$status}.";

        $context = [
            'provider' => $provider,
            'status' => $status,
            'requestId' => $requestId,
            'modelId' => $modelId,
            'body' => $body,
            'retryAfter' => $retryAfter,
            'retryable' => in_array($status, [408, 409, 429, 500, 502, 503, 504, 529], true),
        ];

        return match (true) {
            $status === 401 => new AuthenticationException($message, $context, $previous),
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
     * @param  array<int|string, mixed>|string|null  $body
     */
    private static function extractMessage(array|string|null $body): ?string
    {
        return match (true) {
            is_string($body) => $body !== '' ? $body : null,
            is_array($body) && isset($body['error']) && is_array($body['error']) && isset($body['error']['message']) => (string) $body['error']['message'],
            is_array($body) && isset($body['error']) && is_string($body['error']) => $body['error'],
            is_array($body) && isset($body['message']) && is_string($body['message']) => $body['message'],
            default => null,
        };
    }
}
