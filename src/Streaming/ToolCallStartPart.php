<?php

declare(strict_types=1);

namespace AiSdk\Streaming;

/**
 * Emitted once when a streamed tool call begins. Carries the slot index plus
 * the tool id/name; argument JSON arrives separately as {@see ToolCallDeltaPart}.
 */
final class ToolCallStartPart extends StreamPart
{
    public function __construct(
        public readonly int $index,
        public readonly string $id,
        public readonly string $name,
    ) {}
}
