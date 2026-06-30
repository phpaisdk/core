<?php

declare(strict_types=1);

namespace AiSdk;

final class ToolResult
{
    public function __construct(
        public readonly string $toolCallId,
        public readonly string $toolName,
        public readonly mixed $output,
    ) {}
}
