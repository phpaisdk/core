<?php

declare(strict_types=1);

namespace AiSdk\Agents\Middleware;

use AiSdk\Agents\Middleware;
use AiSdk\Agents\StepContext;
use AiSdk\Agents\StepOutcome;
use AiSdk\Exceptions\InvalidArgumentException;
use Closure;

/** Additional generation-step cap; the agent's own maximum remains the hard ceiling. */
final readonly class StepBudget implements Middleware
{
    public function __construct(public int $steps)
    {
        if ($steps < 1) {
            throw new InvalidArgumentException('Step budget must be positive.');
        }
    }

    public function handle(StepContext $step, Closure $next): StepOutcome
    {
        $outcome = $next($step);

        return $step->stepIndex >= $this->steps ? $outcome->budgetReached() : $outcome;
    }
}
