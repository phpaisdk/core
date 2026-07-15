<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class LiveClosed extends LiveEvent
{
    public function __construct(public ?int $code = null, public ?string $reason = null)
    {
        parent::__construct('closed');
    }
}
