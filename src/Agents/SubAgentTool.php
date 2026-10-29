<?php

declare(strict_types=1);

namespace AiSdk\Agents;

use AiSdk\Agent;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Schema;
use AiSdk\Support\Json;
use AiSdk\Tool;
use AiSdk\ToolExecutionContext;

/** @internal Created by Agent::asTool(). */
final class SubAgentTool extends Tool
{
    private readonly bool $defaultInput;

    public function __construct(private readonly Agent $agent, string $name, string $description, ?Schema $input, public readonly bool $canPause)
    {
        $this->defaultInput = $input === null;
        parent::__construct($name, $description, $input ?? Schema::object('input', properties: [Schema::string('prompt')->required()]), static fn(mixed $value): mixed => $value);
    }

    public function call(array $args, ?ToolExecutionContext $context = null): mixed
    {
        if ($context?->runContext === null) {
            throw new InvalidArgumentException('Sub-agent tools require an agent execution context.');
        }
        $value = parent::call($args, $context);
        $prompt = $this->defaultInput && is_array($value) && is_string($value['prompt'] ?? null) ? $value['prompt'] : Json::encode($value);
        $agent = $context->events === null ? $this->agent : $this->agent->onEvent($context->events);

        return $agent->runWithContext($prompt, $context->runContext, $context->scope);
    }

    /** @param array<string, bool> $decisions */
    public function resumeChild(string $token, array $decisions, ToolExecutionContext $context): AgentResult
    {
        $agent = $context->events === null ? $this->agent : $this->agent->onEvent($context->events);

        return $agent->resume($token, $decisions, $context->scope);
    }
}
