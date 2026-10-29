<?php

declare(strict_types=1);

namespace AiSdk\Observability\Events;

use AiSdk\Observability\RunContext;
use AiSdk\Observability\RunEvent;

final readonly class StepStarted implements RunEvent
{
    public function __construct(
        public RunContext $run,
        public int $stepIndex,
    ) {}

    public function context(): RunContext
    {
        return $this->run;
    }
}
