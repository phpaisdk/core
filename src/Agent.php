<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Agents\AgentCheckpoint;
use AiSdk\Agents\AgentResult;
use AiSdk\Agents\CheckpointStore;
use AiSdk\Contracts\Model;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Exceptions\ApprovalException;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Observability\Events;
use AiSdk\Observability\ObservedTextModel;
use AiSdk\Observability\RunContext;
use AiSdk\Observability\RunEvent;
use AiSdk\Observability\RunTrace;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Results\TextResult;
use AiSdk\Support\Json;
use AiSdk\Support\ModelResolver;
use AiSdk\Support\ToolExecutor;
use AiSdk\Support\Usage;
use Closure;
use Throwable;

/** Reusable, bounded agent. Conversation and durable storage belong to the application. */
final readonly class Agent
{
    /**
     * @param list<Tool> $tools
     * @param list<Events|Closure> $listeners */
    public function __construct(
        private Model $model,
        private string $instructions = '',
        private array $tools = [],
        private int $maxSteps = 10,
        private ?CheckpointStore $store = null,
        private ?string $signingKey = null,
        private string $id = 'default',
        private int $approvalTtl = 900,
        private array $listeners = [],
    ) {
        if ($maxSteps < 1 || $approvalTtl < 1 || $id === '') {
            throw new InvalidArgumentException('Agent requires a positive step limit, positive approval TTL and nonempty id.');
        }
        $names = [];
        foreach ($tools as $tool) {
            if ($tool->name() === '' || isset($names[$tool->name()])) {
                throw new InvalidArgumentException('Agent tool names must be unique and nonempty.');
            }
            $names[$tool->name()] = true;
            if ($tool->requiresApproval() && ($store === null || $signingKey === null || strlen($signingKey) < 32)) {
                throw new InvalidArgumentException('Approval requires a checkpoint store and a signing key of at least 32 bytes.');
            }
        }
    }

    /** @param Events|callable(RunEvent): void $listener */
    public function onEvent(Events|callable $listener): self
    {
        return new self($this->model, $this->instructions, $this->tools, $this->maxSteps, $this->store, $this->signingKey, $this->id, $this->approvalTtl, [...$this->listeners, $listener instanceof Events ? $listener : Closure::fromCallable($listener)]);
    }

    /**
     * Run as a child without ambient state; callers explicitly supply the parent context.
     * @param string|list<Message> $prompt
     */
    public function runWithContext(string|array $prompt, RunContext $parent, string $scope = ''): AgentResult
    {
        return $this->startRun($prompt, $scope, $parent);
    }

    /**
     * @param string|list<Message> $prompt */
    public function run(string|array $prompt, string $scope = ''): AgentResult
    {
        return $this->startRun($prompt, $scope);
    }

    /** @param string|list<Message> $prompt */
    private function startRun(string|array $prompt, string $scope, ?RunContext $parent = null): AgentResult
    {
        $messages = is_string($prompt) ? [Message::user($prompt)] : $prompt;
        foreach ($messages as $message) {
            if (! $message instanceof Message) {
                throw new InvalidArgumentException('Agent messages must be Message instances.');
            }
        }
        $messages = array_values($messages);
        if ($messages === []) {
            throw new InvalidArgumentException('Agent requires messages.');
        }

        $trace = new RunTrace(RunContext::start($parent), $this->listeners);
        $trace->start();

        try {
            return $this->advance($messages, $this->maxSteps, Usage::empty(), $scope, $trace);
        } catch (Throwable $error) {
            $trace->fail($error);

            throw $error;
        }
    }

    /**
     * @param array<string, bool> $decisions Tool call id => allow/deny. Every requested approval is required. */
    public function resume(string $token, array $decisions, string $scope): AgentResult
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, '');
        if ($this->store === null || $this->signingKey === null || ! hash_equals(hash_hmac('sha256', Json::encode([$payload, $scope, $this->id]), $this->signingKey), $signature)) {
            throw new ApprovalException('Invalid approval token or scope.');
        }
        $checkpoint = $this->store->consume($payload);
        if ($checkpoint === null || $checkpoint->expiresAt <= time() || $checkpoint->scope !== $scope || $checkpoint->agent !== $this->id) {
            throw new ApprovalException('Approval checkpoint is expired, already consumed, or invalid.');
        }
        $ids = array_keys($decisions);
        $expected = $checkpoint->approvalIds;
        sort($ids);
        sort($expected);
        if ($ids !== $expected || array_filter($decisions, static fn($decision): bool => ! is_bool($decision)) !== []) {
            // The token was authentic and unexpired, so a malformed submission must not destroy the pending approval.
            $this->store->put($payload, $checkpoint);

            throw new ApprovalException('Supply one boolean decision for every approval.');
        }
        $trace = new RunTrace($checkpoint->runContext ?? RunContext::start(), $this->listeners);

        try {
            $messages = $this->execute($checkpoint->messages, $checkpoint->calls, $decisions, $trace);

            if ($checkpoint->remainingSteps < 1) {
                return new AgentResult($this->lastStep($checkpoint), $messages, $checkpoint->usage, stepLimitReached: true, runContext: $trace->finish($checkpoint->usage));
            }

            return $this->advance($messages, $checkpoint->remainingSteps, $checkpoint->usage, $scope, $trace, $checkpoint->completedSteps);
        } catch (Throwable $error) {
            $trace->fail($error);

            throw $error;
        }
    }

    /** The step that requested approval, so a budget-exhausted resume still reports a result. */
    private function lastStep(AgentCheckpoint $checkpoint): TextResult
    {
        $assistant = null;
        foreach ($checkpoint->messages as $message) {
            if ($message->role === Message::ROLE_ASSISTANT) {
                $assistant = $message;
            }
        }

        return new TextResult($assistant?->text() ?? '', toolCalls: $checkpoint->calls, finishReason: FinishReason::ToolCalls, totalUsage: $checkpoint->usage);
    }

    /**
     * @param list<Message> $messages */
    private function advance(array $messages, int $remaining, Usage $usage, string $scope, RunTrace $trace, int $completedSteps = 0): AgentResult
    {
        $model = ModelResolver::resolve($this->model, TextModelInterface::class, 'Agent');
        $model = new ObservedTextModel($model, $trace->modelEvent(...), $trace->context);
        $result = null;
        for (; $remaining > 0; $remaining--) {
            $trace->stepStarted(++$completedSteps);
            $response = $model->generate(new TextModelRequest($messages, system: $this->instructions, tools: $this->tools));
            $usage = $usage->add($response->usage);
            $calls = array_values($response->toolCalls());
            $trace->stepFinished($completedSteps, $response->usage, $response->finishReason, $calls);
            $messages[] = Message::assistant($response->text(), toolCalls: $calls);
            $result = new TextResult($response->text(), reasoning: $response->reasoning(), toolCalls: $calls, finishReason: $response->finishReason, usage: $response->usage, rawResponse: $response->rawResponse, providerMetadata: $response->providerMetadata, totalUsage: $usage, response: $response->response);
            if ($calls === []) {
                return new AgentResult($result, $messages, $usage, runContext: $trace->finish($usage));
            }
            $seen = [];
            foreach ($calls as $call) {
                if ($call->id === '' || isset($seen[$call->id])) {
                    throw new InvalidResponseException('Agent tool call ids must be unique and nonempty within a step.');
                }
                $seen[$call->id] = true;
            }
            $approvals = array_values(array_filter($calls, fn(ToolCall $call): bool => $this->requiresApproval($call)));
            if ($approvals !== []) {
                if ($scope === '' || $this->store === null || $this->signingKey === null) {
                    throw new ApprovalException('A nonempty application authorization scope and checkpoint storage are required for approval.');
                }
                $key = bin2hex(random_bytes(32));
                $this->store->put($key, new AgentCheckpoint($scope, $this->id, time() + $this->approvalTtl, $messages, $calls, array_map(static fn(ToolCall $call): string => $call->id, $approvals), $remaining - 1, $usage, $trace->context, $completedSteps));
                $token = $key . '.' . hash_hmac('sha256', Json::encode([$key, $scope, $this->id]), $this->signingKey);

                $trace->emit(new \AiSdk\Observability\Events\RunPaused($trace->context, $usage));

                return new AgentResult($result, $messages, $usage, $approvals, $token, runContext: $trace->context);
            }
            $messages = $this->execute($messages, $calls, [], $trace);
        }

        return new AgentResult($result, $messages, $usage, stepLimitReached: true, runContext: $trace->finish($usage));
    }

    private function requiresApproval(ToolCall $call): bool
    {
        foreach ($this->tools as $tool) {
            if ($tool->name() === $call->name) {
                return $tool->requiresApproval();
            }
        }

        return false;
    }

    /**
     * @param list<Message> $messages
     * @param list<ToolCall> $calls
     * @param array<string, bool> $decisions
     * @return list<Message> */
    private function execute(array $messages, array $calls, array $decisions, RunTrace $trace): array
    {
        $executor = new ToolExecutor($this->tools, trace: $trace);
        $approvedExecutor = new ToolExecutor($this->tools, approved: true, trace: $trace);
        foreach ($calls as $call) {
            $result = match (true) {
                ($decisions[$call->id] ?? null) === false => new ToolResult($call->id, $call->name, ['error' => 'Tool execution denied by the user.'], isError: true),
                ($decisions[$call->id] ?? null) === true => $approvedExecutor->execute($call, $messages),
                default => $executor->execute($call, $messages),
            };
            $messages[] = Message::tool($call->id, is_string($result->output) ? $result->output : Json::encode($result->output), $call->name);
        }

        return $messages;
    }
}
