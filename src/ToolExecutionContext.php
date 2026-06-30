<?php

declare(strict_types=1);

namespace AiSdk;

final class ToolExecutionContext
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, Message>  $messages
     */
    public function __construct(
        public readonly string $toolCallId,
        public readonly string $toolName,
        public readonly array $arguments,
        public readonly array $messages = [],
    ) {}
}
