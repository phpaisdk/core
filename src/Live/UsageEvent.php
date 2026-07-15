<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class UsageEvent extends LiveEvent
{
    /** @param array<string, int|float> $usage */
    public function __construct(public array $usage)
    {
        parent::__construct('usage');
    }
}
