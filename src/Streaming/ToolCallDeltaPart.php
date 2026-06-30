<?php

declare(strict_types=1);

namespace AiSdk\Streaming;

/**
 * A fragment of a streamed tool call's argument JSON. The `index` ties the
 * fragment to a tool slot so partial JSON accumulates correctly (the previous
 * implementation decoded each fragment independently, producing broken calls).
 */
final class ToolCallDeltaPart extends StreamPart
{
    public function __construct(
        public readonly int $index,
        public readonly string $argsJson,
        public readonly ?string $id = null,
        public readonly ?string $name = null,
    ) {}
}
