<?php

declare(strict_types=1);

namespace AiSdk\Exceptions;

final class InvalidResponseException extends ProviderException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function forProvider(string $provider, string $message, array $context = []): self
    {
        return new self($message, array_replace(['provider' => $provider], $context));
    }
}
