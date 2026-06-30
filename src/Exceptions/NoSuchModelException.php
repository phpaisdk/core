<?php

declare(strict_types=1);

namespace AiSdk\Exceptions;

final class NoSuchModelException extends AiSdkException
{
    public static function for(string $provider, string $modelId, string $modelType = 'textModel'): self
    {
        return new self(
            message: "Model [{$modelId}] does not exist on provider [{$provider}] for model type [{$modelType}].",
            context: ['provider' => $provider, 'modelId' => $modelId, 'modelType' => $modelType],
        );
    }
}
