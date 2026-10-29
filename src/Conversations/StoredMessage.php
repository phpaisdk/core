<?php

declare(strict_types=1);

namespace AiSdk\Conversations;

use AiSdk\Message;
use AiSdk\ToolCall;
use AiSdk\ToolResult;
use DateTimeImmutable;

final readonly class StoredMessage
{
    /** @var array<int, ToolCall> */
    public array $toolCalls;

    /** @param list<Citation> $citations
     * @param list<ToolResult> $toolResults */
    public function __construct(
        public string $id,
        public Message $message,
        public ?string $reasoning = null,
        public array $citations = [],
        public array $toolResults = [],
        public DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        RecordBounds::id($id);
        $this->toolCalls = $message->toolCalls;
        RecordBounds::fields($this->toolCalls, $toolResults, $reasoning, $citations);
    }
}
