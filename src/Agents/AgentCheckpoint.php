<?php

declare(strict_types=1);

namespace AiSdk\Agents;

use AiSdk\Message;
use AiSdk\Support\Usage;
use AiSdk\ToolCall;

final readonly class AgentCheckpoint
{
    /**
     * @param list<Message> $messages
     * @param list<ToolCall> $calls
     * @param list<string> $approvalIds
     */
    public function __construct(
        public string $scope,
        public string $agent,
        public int $expiresAt,
        public array $messages,
        public array $calls,
        public array $approvalIds,
        public int $remainingSteps,
        public Usage $usage,
    ) {}
}
