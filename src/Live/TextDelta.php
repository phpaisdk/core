<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class TextDelta extends LiveEvent
{
    public function __construct(public string $delta)
    {
        parent::__construct('text.delta');
    }
}
