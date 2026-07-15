<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class ToolCallEvent extends LiveEvent
{
    /** @param array<string, mixed> $arguments */
    public function __construct(public string $callId, public string $name, public array $arguments)
    {
        parent::__construct('tool.call');
    }
}
