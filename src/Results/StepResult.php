<?php

declare(strict_types=1);

namespace AiSdk\Results;

use AiSdk\FinishReason;
use AiSdk\Support\Usage;
use AiSdk\ToolCall;
use AiSdk\ToolResult;

/**
 * One model call inside a tool loop, passed to onStepFinish() and stopWhen().
 */
final readonly class StepResult
{
    /**
     * @param  array<int, ToolCall>  $toolCalls  Tool calls the model requested in this step.
     * @param  array<int, ToolResult>  $toolResults  Results produced for those calls.
     */
    public function __construct(
        public int $number,
        public string $text,
        public array $toolCalls,
        public array $toolResults,
        public FinishReason $finishReason,
        public Usage $usage,
    ) {}

    public function calledTool(string $name): bool
    {
        foreach ($this->toolCalls as $call) {
            if ($call->name === $name) {
                return true;
            }
        }

        return false;
    }
}
