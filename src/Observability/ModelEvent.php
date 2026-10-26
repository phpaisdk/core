<?php

declare(strict_types=1);

namespace AiSdk\Observability;

use AiSdk\Support\Usage;

/** Contains no prompt, output, tool arguments, credentials, or exception messages. */
final readonly class ModelEvent
{
    public function __construct(
        public string $phase,
        public string $provider,
        public string $model,
        public bool $streaming,
        public float $durationMs = 0,
        public ?Usage $usage = null,
        public ?string $errorType = null,
    ) {}
}
