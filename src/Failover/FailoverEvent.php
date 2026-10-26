<?php

declare(strict_types=1);

namespace AiSdk\Failover;

use Throwable;

/**
 * Describes one candidate that was skipped or failed, passed to onFailover() listeners.
 */
final readonly class FailoverEvent
{
    public function __construct(
        public string $operation,
        public string $provider,
        public string $modelId,
        public Throwable $exception,
        public int $attempt,
        public ?string $nextProvider,
        public ?string $nextModelId,
    ) {}
}
