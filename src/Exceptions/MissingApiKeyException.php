<?php

declare(strict_types=1);

namespace AiSdk\Exceptions;

final class MissingApiKeyException extends AiSdkException
{
    public static function forProvider(string $provider, string $envVar): self
    {
        return new self(
            message: "Missing API key for provider [{$provider}]. Set the {$envVar} environment variable or pass apiKey when constructing the provider.",
            context: ['provider' => $provider, 'envVar' => $envVar],
        );
    }
}
