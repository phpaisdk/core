<?php

declare(strict_types=1);

namespace AiSdk\Agents;

use AiSdk\Message;
use AiSdk\Results\TextResult;
use AiSdk\Support\Usage;
use AiSdk\ToolCall;

final readonly class AgentResult
{
    /**
     * @param list<Message> $messages
     * @param list<ToolCall> $approvals */
    public function __construct(
        public ?TextResult $result,
        public array $messages,
        public Usage $totalUsage,
        public array $approvals = [],
        public ?string $resumeToken = null,
        public bool $stepLimitReached = false,
    ) {}
}
