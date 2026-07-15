<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class LiveError extends LiveEvent
{
    /** @param array<string, mixed> $details */
    public function __construct(public string $message, public ?string $code = null, public array $details = [])
    {
        parent::__construct('error');
    }
}
