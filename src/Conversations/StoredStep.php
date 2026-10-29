<?php

declare(strict_types=1);

namespace AiSdk\Conversations;

use AiSdk\Exceptions\ConversationException;
use AiSdk\FinishReason;
use AiSdk\Support\Usage;
use AiSdk\ToolCall;
use AiSdk\ToolResult;
use DateTimeImmutable;

final readonly class StoredStep
{
    /** @param list<ToolCall> $toolCalls
     * @param list<ToolResult> $toolResults
     * @param list<Citation> $citations
     * @param list<string> $messageIds */
    public function __construct(
        public string $id,
        public string $invocationId,
        public int $stepIndex,
        public Usage $usage = new Usage(),
        public FinishReason $finishReason = FinishReason::Unknown,
        public array $toolCalls = [],
        public array $toolResults = [],
        public ?string $reasoning = null,
        public array $citations = [],
        public array $messageIds = [],
        public ?string $parentInvocationId = null,
        public DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        RecordBounds::id($id);
        RecordBounds::id($invocationId);
        if ($parentInvocationId !== null) {
            RecordBounds::id($parentInvocationId);
        }
        if ($stepIndex < 1 || $usage->inputTokens < 0 || $usage->outputTokens < 0 || $usage->totalTokens < 0 || count($messageIds) > 4096) {
            throw new ConversationException('Stored step index, usage or message references are invalid.');
        }
        foreach ($messageIds as $messageId) {
            if (! is_string($messageId)) {
                throw new ConversationException('Stored message references must be string ids.');
            }
            RecordBounds::id($messageId);
        }
        RecordBounds::fields($toolCalls, $toolResults, $reasoning, $citations);
    }
}
