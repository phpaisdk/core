<?php

declare(strict_types=1);

namespace AiSdk\Agents;

use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Support\Usage;
use AiSdk\ToolCall;

/** Server-side continuation; never send the child's token or conversations as tool output. */
final readonly class ChildCheckpoint
{
    /** @param list<ToolCall> $approvals */
    public function __construct(public ToolCall $call, public string $token, public Usage $accountedUsage, public array $approvals, public ?AgentResult $result = null) {}

    public function additionalUsage(Usage $current): Usage
    {
        $before = $this->accountedUsage;
        if ($current->inputTokens < $before->inputTokens || $current->outputTokens < $before->outputTokens || $current->totalTokens < $before->totalTokens) {
            throw new InvalidResponseException('Resumed child usage cannot decrease.');
        }

        return new Usage(
            $current->inputTokens - $before->inputTokens,
            $current->outputTokens - $before->outputTokens,
            $current->totalTokens - $before->totalTokens,
            $current->reasoningTokens === null ? null : max(0, $current->reasoningTokens - ($before->reasoningTokens ?? 0)),
            $current->cachedInputTokens === null ? null : max(0, $current->cachedInputTokens - ($before->cachedInputTokens ?? 0)),
            $current->cacheWriteInputTokens === null ? null : max(0, $current->cacheWriteInputTokens - ($before->cacheWriteInputTokens ?? 0)),
        );
    }
}
