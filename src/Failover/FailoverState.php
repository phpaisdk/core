<?php

declare(strict_types=1);

namespace AiSdk\Failover;

/**
 * Per-Failover mutable state shared by the runners it creates.
 *
 * @internal
 */
final class FailoverState
{
    /** @var array<string, float> candidate key => monotonic seconds until which it is skipped */
    public array $coolingUntil = [];
}
