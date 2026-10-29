<?php

declare(strict_types=1);

namespace AiSdk\Exceptions;

use AiSdk\Capability;
use AiSdk\CapabilitySupport;

final class CapabilityNotSupportedException extends AiSdkException
{
    /** A provider-level feature (files, webhooks, ...) the provider package does not implement. */
    public static function forProvider(string $provider, string $feature): self
    {
        return new self(
            message: "Provider [{$provider}] does not support {$feature}.",
            context: ['provider' => $provider, 'feature' => $feature],
        );
    }

    public static function for(string $provider, string $modelId, Capability $capability): self
    {
        return new self(
            message: "Model [{$modelId}] on provider [{$provider}] does not support capability [{$capability->name}].",
            context: [
                'provider' => $provider,
                'modelId' => $modelId,
                'capability' => $capability->name,
            ],
        );
    }

    public static function fromSupport(string $provider, string $modelId, CapabilitySupport $support): self
    {
        return new self(
            message: "Model [{$modelId}] on provider [{$provider}] does not support capability [{$support->capability->name}]."
                . ($support->reason !== null ? " {$support->reason}" : ''),
            context: [
                'provider' => $provider,
                'modelId' => $modelId,
                'capability' => $support->capability->name,
                'state' => $support->state->value,
                'reason' => $support->reason,
                'metadata' => $support->metadata,
            ],
        );
    }
}
