<?php

declare(strict_types=1);

namespace AiSdk\Exceptions;

use AiSdk\Live\LiveOperation;

final class UnsupportedLiveActionException extends AiSdkException
{
    public static function for(string $provider, LiveOperation $operation, string $action): self
    {
        return new self(
            message: "Provider [{$provider}] does not support Live action [{$action}] for [{$operation->value}].",
            context: [
                'provider' => $provider,
                'operation' => $operation->value,
                'action' => $action,
            ],
        );
    }
}
