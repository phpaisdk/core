<?php

declare(strict_types=1);

namespace AiSdk\Live;

abstract readonly class LiveEvent
{
    public function __construct(public string $type) {}
}
