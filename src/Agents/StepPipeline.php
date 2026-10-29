<?php

declare(strict_types=1);

namespace AiSdk\Agents;

use AiSdk\Exceptions\InvalidArgumentException;
use Closure;

/** @internal */
final class StepPipeline
{
    /**
     * @param list<Middleware> $middleware
     * @param Closure(StepContext): StepOutcome $generate
     */
    public static function run(StepContext $step, array $middleware, Closure $generate): StepOutcome
    {
        if (count($middleware) > 64) {
            throw new InvalidArgumentException('An agent supports at most 64 middleware layers.');
        }
        $next = $generate;
        foreach (array_reverse($middleware) as $layer) {
            $inner = $next;
            $next = static function (StepContext $context) use ($layer, $inner): StepOutcome {
                $called = false;

                try {
                    $outcome = $layer->handle($context, static function (StepContext $modified) use ($inner, &$called, $context): StepOutcome {
                        if ($called) {
                            throw new InvalidArgumentException('Middleware may call next only once per step.');
                        }
                        if ($modified->runContext !== $context->runContext || $modified->stepIndex !== $context->stepIndex) {
                            throw new InvalidArgumentException('Middleware cannot replace run identity or step index.');
                        }
                        $called = true;

                        return $inner($modified);
                    });
                } catch (\TypeError $error) {
                    throw new \AiSdk\Exceptions\InvalidResponseException('Middleware must return a valid StepOutcome and pass a StepContext to next.', previous: $error);
                }

                return $outcome->request === null ? $outcome->withRequest($context->request) : $outcome;
            };
        }
        $outcome = $next($step);

        return $outcome->request === null ? $outcome->withRequest($step->request) : $outcome;
    }
}
