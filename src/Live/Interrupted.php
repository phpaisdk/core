<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class Interrupted extends LiveEvent
{
    public function __construct(public ?string $responseId = null)
    {
        parent::__construct('interrupted');
    }
}
