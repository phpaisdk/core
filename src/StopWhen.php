<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Results\StepResult;
use Closure;

/**
 * Ready-made conditions for PendingTextRequest::stopWhen().
 *
 *   Generate::text($prompt)->tools(...)->maxSteps(20)->stopWhen(StopWhen::toolCalled('submit_answer'))
 */
final class StopWhen
{
    private function __construct() {}

    /**
     * @return Closure(StepResult): bool
     */
    public static function toolCalled(string $name): Closure
    {
        return static fn(StepResult $step): bool => $step->calledTool($name);
    }

    /**
     * Stop once a step has used at least this many input tokens, a guard against runaway context growth.
     *
     * @return Closure(StepResult): bool
     */
    public static function inputTokensOver(int $tokens): Closure
    {
        return static fn(StepResult $step): bool => $step->usage->inputTokens > $tokens;
    }

    /**
     * @return Closure(StepResult): bool
     */
    public static function textContains(string $needle): Closure
    {
        return static fn(StepResult $step): bool => str_contains($step->text, $needle);
    }
}
