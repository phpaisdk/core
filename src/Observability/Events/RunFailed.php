<?php

declare(strict_types=1);

namespace AiSdk\Observability\Events;

use AiSdk\Observability\RunContext;
use AiSdk\Observability\RunEvent;

final readonly class RunFailed implements RunEvent
{
    public function __construct(
        public RunContext $run,
        public string $exceptionClass,
        public string $message,
    ) {}

    public function context(): RunContext
    {
        return $this->run;
    }
}
