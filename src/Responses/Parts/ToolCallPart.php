<?php

declare(strict_types=1);

namespace AiSdk\Responses\Parts;

use AiSdk\ToolCall;

final class ToolCallPart extends ResponsePart
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments,
    ) {}

    public function toolCall(): ToolCall
    {
        return new ToolCall($this->id, $this->name, $this->arguments);
    }
}
