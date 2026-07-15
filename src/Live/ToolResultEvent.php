<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class ToolResultEvent extends LiveEvent
{
    public function __construct(
        public string $callId,
        public string $name,
        public mixed $result,
        public bool $automatic = false,
    ) {
        parent::__construct('tool.result');
    }
}
