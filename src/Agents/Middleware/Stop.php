<?php

declare(strict_types=1);

namespace AiSdk\Agents\Middleware;

use AiSdk\Agents\Middleware;
use AiSdk\Agents\StepContext;
use AiSdk\Agents\StepOutcome;
use AiSdk\Results\StepResult;
use Closure;

/** Stop after generation, before tools are executed. Accepts existing StopWhen predicates. */
final readonly class Stop implements Middleware
{
    /** @var Closure(StepResult): bool */
    private Closure $predicate;

    /** @param callable(StepResult): bool $predicate */
    public function __construct(callable $predicate)
    {
        $this->predicate = Closure::fromCallable($predicate);
    }

    public function handle(StepContext $step, Closure $next): StepOutcome
    {
        $outcome = $next($step);
        $response = $outcome->response;
        $result = new StepResult($step->stepIndex, $response->text(), $response->toolCalls(), [], $response->finishReason, $response->usage);

        return ($this->predicate)($result) ? $outcome->stopped() : $outcome;
    }
}
