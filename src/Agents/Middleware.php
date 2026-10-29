<?php

declare(strict_types=1);

namespace AiSdk\Agents;

use Closure;

interface Middleware
{
    /** @param Closure(StepContext): StepOutcome $next */
    public function handle(StepContext $step, Closure $next): StepOutcome;
}
