<?php

declare(strict_types=1);

namespace AiSdk\Observability\Events;

use AiSdk\Observability\RunContext;
use AiSdk\Observability\RunEvent;

final readonly class ToolFinished implements RunEvent
{
    public function __construct(
        public RunContext $run,
        public string $toolName,
        public string $callId,
        public int $durationMicroseconds,
    ) {}

    public function context(): RunContext
    {
        return $this->run;
    }
}
