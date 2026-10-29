<?php

declare(strict_types=1);

namespace AiSdk\Observability\Events;

use AiSdk\Observability\RunContext;
use AiSdk\Observability\RunEvent;

final readonly class StepFinished implements RunEvent
{
    /** @param list<\AiSdk\ToolCall> $toolCalls Arguments are omitted from diagnostic events. */
    public function __construct(
        public RunContext $run,
        public int $stepIndex,
        public \AiSdk\Support\Usage $usage,
        public \AiSdk\FinishReason $finishReason,
        public array $toolCalls,
    ) {}

    public function context(): RunContext
    {
        return $this->run;
    }
}
