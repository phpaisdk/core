<?php

declare(strict_types=1);

namespace AiSdk\Live;

/** Unrecognized provider events are deliberately preserved. */
final readonly class ProviderEvent extends LiveEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(public string $providerType, public array $payload)
    {
        parent::__construct('provider');
    }
}
