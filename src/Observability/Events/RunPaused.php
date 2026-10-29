<?php

declare(strict_types=1);

namespace AiSdk\Observability\Events;

use AiSdk\Observability\RunContext;
use AiSdk\Observability\RunEvent;

final readonly class RunPaused implements RunEvent
{
    public function __construct(
        public RunContext $run,
        public \AiSdk\Support\Usage $usage,
    ) {}

    public function context(): RunContext
    {
        return $this->run;
    }
}
