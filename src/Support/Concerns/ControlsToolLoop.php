<?php

declare(strict_types=1);

namespace AiSdk\Support\Concerns;

use AiSdk\Results\StepResult;
use Closure;

trait ControlsToolLoop
{
    protected bool $throwOnToolError = false;

    protected bool $exposeToolErrors = false;

    /** @var list<Closure(StepResult): void> */
    protected array $stepListeners = [];

    /** @var list<Closure(StepResult): bool> */
    protected array $stopConditions = [];

    /**
     * Abort with the original exception when a tool call fails instead of
     * returning an error result to the model (the default).
     */
    public function throwOnToolError(bool $throw = true): static
    {
        $this->throwOnToolError = $throw;

        return $this;
    }

    /**
     * Include the message of exceptions thrown by tool handlers in the error
     * result the model sees. Off by default: handler exceptions can hold internal details.
     */
    public function exposeToolErrors(bool $expose = true): static
    {
        $this->exposeToolErrors = $expose;

        return $this;
    }

    /**
     * Called after every model step (with that step's tool calls and results).
     *
     * @param  callable(StepResult): void  $listener
     */
    public function onStepFinish(callable $listener): static
    {
        $this->stepListeners[] = Closure::fromCallable($listener);

        return $this;
    }

    /**
     * Stop the loop after a step when any condition returns true, even if the
     * model asked for more tool calls. maxSteps() remains the hard limit.
     *
     * @param  callable(StepResult): bool  ...$conditions
     */
    public function stopWhen(callable ...$conditions): static
    {
        foreach ($conditions as $condition) {
            $this->stopConditions[] = Closure::fromCallable($condition);
        }

        return $this;
    }

    protected function stepFinished(StepResult $step): bool
    {
        foreach ($this->stepListeners as $listener) {
            $listener($step);
        }

        foreach ($this->stopConditions as $condition) {
            if ($condition($step)) {
                return true;
            }
        }

        return false;
    }
}
