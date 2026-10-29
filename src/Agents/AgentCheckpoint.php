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
     * @param list<string>|null $toolNames
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
        public ?\AiSdk\Observability\RunContext $runContext = null,
        public int $completedSteps = 0,
        public ?array $toolNames = null,
    ) {}
}
