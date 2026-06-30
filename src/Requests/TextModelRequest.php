<?php

declare(strict_types=1);

namespace AiSdk\Requests;

use AiSdk\Message;
use AiSdk\Outputs\Output;
use AiSdk\Reasoning;
use AiSdk\Tool;
use AiSdk\ToolChoice;

/**
 * Immutable, provider-agnostic text request. withMessages() returns a copy
 * (used by the tool-execution loop).
 */
final class TextModelRequest
{
    /**
     * @param  array<int, Message>  $messages
     * @param  array<int, Tool>  $tools
     * @param  array<string, array<string, mixed>>  $providerOptions
     */
    public function __construct(
        public readonly array $messages,
        public readonly ?string $system = null,
        public readonly ?Output $output = null,
        public readonly array $tools = [],
        public readonly ?ToolChoice $toolChoice = null,
        public readonly int $maxTokens = 1024,
        public readonly float $temperature = 1.0,
        public readonly ?float $topP = null,
        public readonly int $maxSteps = 1,
        public readonly ?Reasoning $reasoning = null,
        public readonly array $providerOptions = [],
    ) {}

    /**
     * @param  array<int, Message>  $messages
     */
    public function withMessages(array $messages): self
    {
        return new self(
            messages: $messages,
            system: $this->system,
            output: $this->output,
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            maxTokens: $this->maxTokens,
            temperature: $this->temperature,
            topP: $this->topP,
            maxSteps: $this->maxSteps,
            reasoning: $this->reasoning,
            providerOptions: $this->providerOptions,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptionsFor(string $provider): array
    {
        $value = $this->providerOptions[$provider] ?? [];

        return is_array($value) ? $value : [];
    }
}
