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
    private string $identity;

    /**
     * @param list<Tool> $tools
     * @param list<Events|Closure> $listeners
     * @param list<\AiSdk\Agents\Middleware> $middleware */
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
        private array $middleware = [],
        private int $maxDepth = 3,
        ?string $identity = null,
    ) {
        if ($maxSteps < 1 || $approvalTtl < 1 || $id === '') {
            throw new InvalidArgumentException('Agent requires a positive step limit, positive approval TTL and nonempty id.');
        }
        if ($maxDepth < 0 || $maxDepth > 32) {
            throw new InvalidArgumentException('Agent nesting depth must be between zero and 32.');
        }
        $this->identity = $identity ?? bin2hex(random_bytes(16));
        $names = [];
        foreach ($tools as $tool) {
            if (! $tool instanceof Tool || $tool->name() === '' || isset($names[$tool->name()])) {
                throw new InvalidArgumentException('Agent tool names must be unique and nonempty.');
            }
            $names[$tool->name()] = true;
            if (($tool->requiresApproval() || ($tool instanceof \AiSdk\Agents\SubAgentTool && $tool->canPause)) && ($store === null || $signingKey === null || strlen($signingKey) < 32)) {
                throw new InvalidArgumentException('Approval requires a checkpoint store and a signing key of at least 32 bytes.');
            }
        }
    }

    /** @param Events|callable(RunEvent): void $listener */
    public function onEvent(Events|callable $listener): self
    {
        return new self($this->model, $this->instructions, $this->tools, $this->maxSteps, $this->store, $this->signingKey, $this->id, $this->approvalTtl, [...$this->listeners, $listener instanceof Events ? $listener : Closure::fromCallable($listener)], $this->middleware, $this->maxDepth, $this->identity);
    }

    public function withMiddleware(\AiSdk\Agents\Middleware ...$middleware): self
    {
        return new self($this->model, $this->instructions, $this->tools, $this->maxSteps, $this->store, $this->signingKey, $this->id, $this->approvalTtl, $this->listeners, [...$this->middleware, ...array_values($middleware)], $this->maxDepth, $this->identity);
    }

    public function withTools(Tool ...$tools): static
    {
        $merged = $this->tools;
        $seen = [];
        foreach ($tools as $tool) {
            $name = $tool->name();
            if ($name === '' || isset($seen[$name])) {
                throw new InvalidArgumentException('Runtime tool names must be unique and nonempty.');
            }
            $seen[$name] = true;
            $replaced = false;
            foreach ($merged as $index => $existing) {
                if ($existing->name() === $name) {
                    $merged[$index] = $tool;
                    $replaced = true;
                }
            }
            if (! $replaced) {
                $merged[] = $tool;
            }
        }

        return new static($this->model, $this->instructions, $merged, $this->maxSteps, $this->store, $this->signingKey, $this->id, $this->approvalTtl, $this->listeners, $this->middleware, $this->maxDepth, $this->identity);
    }

    public function without(string ...$names): static
    {
        $tools = array_values(array_filter($this->tools, static fn(Tool $tool): bool => ! in_array($tool->name(), $names, true)));

        return new static($this->model, $this->instructions, $tools, $this->maxSteps, $this->store, $this->signingKey, $this->id, $this->approvalTtl, $this->listeners, $this->middleware, $this->maxDepth, $this->identity);
    }

    public function asTool(string $name, string $description, ?Schema $input = null): Tool
    {
        if ($name === '') {
            throw new InvalidArgumentException('Sub-agent tool names must be nonempty.');
        }

        return new \AiSdk\Agents\SubAgentTool($this, $name, $description, $input, $this->store !== null);
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
    public function stream(string|array $prompt, string $scope = ''): \AiSdk\Agents\AgentStream
    {
        return new \AiSdk\Agents\AgentStream(fn(Closure $output): AgentResult => $this->startRun($prompt, $scope, streamOutput: $output));
    }

    /** @param array<string, bool> $decisions */
    public function resumeStream(string $token, array $decisions, string $scope): \AiSdk\Agents\AgentStream
    {
        return new \AiSdk\Agents\AgentStream(fn(Closure $output): AgentResult => $this->resumeRun($token, $decisions, $scope, $output));
    }

    /** @param string|list<Message> $prompt
     * @param Closure(RunEvent|\AiSdk\Streaming\StreamPart): void|null $streamOutput */
    private function startRun(string|array $prompt, string $scope, ?RunContext $parent = null, ?Closure $streamOutput = null): AgentResult
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

        $trace = new RunTrace(RunContext::start($parent, $this->identity, $this->maxDepth), $this->listeners, $streamOutput);
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
        return $this->resumeRun($token, $decisions, $scope);
    }

    /** @param array<string, bool> $decisions
     * @param Closure(RunEvent|\AiSdk\Streaming\StreamPart): void|null $streamOutput */
    private function resumeRun(string $token, array $decisions, string $scope, ?Closure $streamOutput = null): AgentResult
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, '');
        if ($this->store === null || $this->signingKey === null || ! hash_equals(hash_hmac('sha256', Json::encode([$payload, $scope, $this->id]), $this->signingKey), $signature)) {
            throw new ApprovalException('Invalid approval token or scope.');
        }
        $checkpoint = $this->store->consume($payload);
        if ($checkpoint === null || $checkpoint->expiresAt <= time() || $checkpoint->scope !== $scope || $checkpoint->agent !== $this->id) {
            throw new ApprovalException('Approval checkpoint is expired, already consumed, or invalid.');
        }
        $tools = $checkpoint->toolNames === null ? $this->tools : array_values(array_filter($this->tools, static fn(Tool $tool): bool => in_array($tool->name(), $checkpoint->toolNames, true)));
        $unavailable = [];
        foreach ($checkpoint->calls as $call) {
            $wasApproval = $checkpoint->child === null ? in_array($call->id, $checkpoint->approvalIds, true) : array_key_exists($call->id, $checkpoint->decisions);
            if ($wasApproval && ! $this->requiresApproval($call, $tools)) {
                $unavailable[] = $call->id;
            }
        }
        $ids = array_keys($decisions);
        $expected = $checkpoint->child === null ? array_diff($checkpoint->approvalIds, $unavailable) : $checkpoint->approvalIds;
        if ($checkpoint->child !== null) {
            $available = array_filter($tools, fn(Tool $tool): bool => $tool instanceof \AiSdk\Agents\SubAgentTool && $tool->name() === $checkpoint->child->call->name) !== [];
            if (! $available) {
                $expected = [];
                $unavailable[] = $checkpoint->child->call->id;
            }
        }
        if (array_diff($expected, $ids) !== [] || array_diff($ids, $checkpoint->approvalIds) !== [] || array_filter($decisions, static fn($decision): bool => ! is_bool($decision)) !== []) {
            // The token was authentic and unexpired, so a malformed submission must not destroy the pending approval.
            $this->store->put($payload, $checkpoint);

            throw new ApprovalException('Supply one boolean decision for every approval.');
        }
        $trace = new RunTrace($checkpoint->runContext ?? RunContext::start(agentIdentity: $this->identity, maxDepth: $this->maxDepth), $this->listeners, $streamOutput);

        try {
            $batch = $this->execute(
                $checkpoint->messages,
                $checkpoint->calls,
                $checkpoint->child === null ? $decisions : $checkpoint->decisions,
                $trace,
                $tools,
                $unavailable,
                $scope,
                $checkpoint->child,
                $decisions,
            );
            $usage = $checkpoint->usage->add($batch->usage);
            if ($batch->child !== null) {
                return $this->pause($this->lastStep($checkpoint), $batch->messages, $usage, $batch->pendingCalls, $batch->child->approvals, $checkpoint->remainingSteps, $scope, $trace, $checkpoint->completedSteps, $tools, $batch->child, $checkpoint->child === null ? $decisions : $checkpoint->decisions);
            }
            if ($checkpoint->remainingSteps < 1) {
                return new AgentResult($this->lastStep($checkpoint), $batch->messages, $usage, stepLimitReached: true, runContext: $trace->finish($usage));
            }

            return $this->advance($batch->messages, $checkpoint->remainingSteps, $usage, $scope, $trace, $checkpoint->completedSteps);
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

        return new TextResult($assistant?->text() ?? '', toolCalls: $assistant->toolCalls ?? $checkpoint->calls, finishReason: FinishReason::ToolCalls, totalUsage: $checkpoint->usage);
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
            $generated = false;
            $outcome = \AiSdk\Agents\StepPipeline::run(
                new \AiSdk\Agents\StepContext(new TextModelRequest($messages, system: $this->instructions, tools: $this->tools), $trace->context, $completedSteps, $usage),
                $this->middleware,
                function (\AiSdk\Agents\StepContext $step) use ($model, $trace, &$generated): \AiSdk\Agents\StepOutcome {
                    $generated = true;

                    return $this->generateStep($model, $step, $trace);
                },
            );
            $response = $outcome->response;
            if (! $generated && $trace->isStreaming()) {
                $this->streamOutcome($response, $trace);
            }
            $stepTools = array_values($outcome->request->tools ?? $this->tools);
            $messages = array_values($outcome->request->messages ?? $messages);
            $usage = $usage->add($response->usage);
            $calls = array_values($response->toolCalls());
            $trace->stepFinished($completedSteps, $response->usage, $response->finishReason, $calls);
            $messages[] = Message::assistant($response->text(), toolCalls: $calls);
            $result = new TextResult($response->text(), reasoning: $response->reasoning(), toolCalls: $calls, finishReason: $response->finishReason, usage: $response->usage, rawResponse: $response->rawResponse, providerMetadata: $response->providerMetadata, totalUsage: $usage, response: $response->response);
            if ($calls === [] || $outcome->stop) {
                return new AgentResult($result, $messages, $usage, runContext: $trace->finish($usage));
            }
            $seen = [];
            foreach ($calls as $call) {
                if ($call->id === '' || isset($seen[$call->id])) {
                    throw new InvalidResponseException('Agent tool call ids must be unique and nonempty within a step.');
                }
                $seen[$call->id] = true;
            }
            $approvals = array_values(array_filter($calls, fn(ToolCall $call): bool => $this->requiresApproval($call, $stepTools)));
            if ($approvals !== []) {
                if ($scope === '' || $this->store === null || $this->signingKey === null) {
                    throw new ApprovalException('A nonempty application authorization scope and checkpoint storage are required for approval.');
                }
                // Unknown siblings cannot request approval and have no handler to run. Settle them now.
                $pending = [];
                foreach ($calls as $call) {
                    $known = array_filter($stepTools, static fn(Tool $tool): bool => $tool->name() === $call->name) !== [];
                    if ($known) {
                        $pending[] = $call;
                    } else {
                        $messages = $this->execute($messages, [$call], [], $trace, $stepTools, scope: $scope)->messages;
                    }
                }

                return $this->pause($result, $messages, $usage, $pending, $approvals, $outcome->stepLimitReached ? 0 : $remaining - 1, $scope, $trace, $completedSteps, $stepTools);
            }
            $batch = $this->execute($messages, $calls, [], $trace, $stepTools, scope: $scope);
            $messages = $batch->messages;
            $usage = $usage->add($batch->usage);
            if ($batch->child !== null) {
                return $this->pause($result, $messages, $usage, $batch->pendingCalls, $batch->child->approvals, $outcome->stepLimitReached ? 0 : $remaining - 1, $scope, $trace, $completedSteps, $stepTools, $batch->child);
            }
            if ($outcome->stepLimitReached) {
                return new AgentResult($result, $messages, $usage, stepLimitReached: true, runContext: $trace->finish($usage));
            }
        }

        return new AgentResult($result, $messages, $usage, stepLimitReached: true, runContext: $trace->finish($usage));
    }

    private function streamOutcome(\AiSdk\Responses\TextModelResponse $response, RunTrace $trace): void
    {
        $index = 0;
        $bytes = 0;
        foreach ($response->parts as $part) {
            if ($part instanceof \AiSdk\Responses\Parts\TextPart || $part instanceof \AiSdk\Responses\Parts\ReasoningPart) {
                $bytes += strlen($part->text);
                if ($bytes > 16 * 1024 * 1024) {
                    throw new InvalidResponseException('An agent stream step exceeded its size limit.');
                }
                $trace->part($part instanceof \AiSdk\Responses\Parts\TextPart ? new \AiSdk\Streaming\TextDeltaPart($part->text) : new \AiSdk\Streaming\ReasoningDeltaPart($part->text));
            } elseif ($part instanceof \AiSdk\Responses\Parts\ToolCallPart) {
                $arguments = Json::encode($part->arguments);
                $bytes += strlen($arguments);
                if ($bytes > 16 * 1024 * 1024) {
                    throw new InvalidResponseException('An agent stream step exceeded its size limit.');
                }
                $trace->part(new \AiSdk\Streaming\ToolCallStartPart($index, $part->id, $part->name));
                $trace->part(new \AiSdk\Streaming\ToolCallDeltaPart($index++, $arguments));
            }
        }
        $trace->part(new \AiSdk\Streaming\FinishPart($response->finishReason, $response->usage, $response->response));
    }

    /** Both run() and stream() use advance(); only the model operation differs. */
    private function generateStep(TextModelInterface $model, \AiSdk\Agents\StepContext $step, RunTrace $trace): \AiSdk\Agents\StepOutcome
    {
        if (! $trace->isStreaming()) {
            return new \AiSdk\Agents\StepOutcome($model->generate($step->request), request: $step->request);
        }
        $state = new \AiSdk\Streaming\StreamState();
        $bytes = 0;
        foreach ($model->stream($step->request) as $part) {
            $bytes += match (true) {
                $part instanceof \AiSdk\Streaming\TextDeltaPart, $part instanceof \AiSdk\Streaming\ReasoningDeltaPart => strlen($part->text),
                $part instanceof \AiSdk\Streaming\ToolCallDeltaPart => strlen($part->argsJson),
                default => 0,
            };
            if ($bytes > 16 * 1024 * 1024 || $state->partCount() >= 65536) {
                throw new InvalidResponseException('An agent stream step exceeded its size limit.');
            }
            $state->record($part);
            $trace->part($part);
        }
        $parts = [new \AiSdk\Responses\Parts\TextPart($state->text())];
        if ($state->reasoning() !== null) {
            $parts[] = new \AiSdk\Responses\Parts\ReasoningPart($state->reasoning());
        }
        foreach ($state->toolCalls() as $call) {
            $parts[] = new \AiSdk\Responses\Parts\ToolCallPart($call->id, $call->name, $call->arguments);
        }
        $response = new \AiSdk\Responses\TextModelResponse($parts, $state->finishReason(), $state->usage(), providerMetadata: $state->providerMetadata(), response: $state->response());

        return new \AiSdk\Agents\StepOutcome($response, request: $step->request);
    }

    /** @param array<int, Tool> $tools */
    private function requiresApproval(ToolCall $call, array $tools): bool
    {
        foreach ($tools as $tool) {
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
     * @param array<int, Tool>|null $tools
     * @param list<string> $unavailable
     * @param array<string, bool> $childDecisions
     */
    private function execute(array $messages, array $calls, array $decisions, RunTrace $trace, ?array $tools = null, array $unavailable = [], string $scope = '', ?\AiSdk\Agents\ChildCheckpoint $child = null, array $childDecisions = []): \AiSdk\Agents\ToolBatch
    {
        $executor = new ToolExecutor($tools ?? $this->tools, trace: $trace, scope: $scope);
        $approvedExecutor = new ToolExecutor($tools ?? $this->tools, approved: true, trace: $trace, scope: $scope);
        $usage = Usage::empty();
        foreach ($calls as $index => $call) {
            $resuming = $child !== null && $child->call->id === $call->id;
            $chosenExecutor = ($decisions[$call->id] ?? false) ? $approvedExecutor : $executor;
            $result = match (true) {
                in_array($call->id, $unavailable, true) => $this->unavailableApproval($call, $trace),
                $resuming => $chosenExecutor->execute($call, $messages, $child, $childDecisions),
                ($decisions[$call->id] ?? null) === false => new ToolResult($call->id, $call->name, ['error' => 'Tool execution denied by the user.'], isError: true),
                default => $chosenExecutor->execute($call, $messages),
            };
            $observedNestedUsage = $trace->takeNestedUsage();
            if ($result->agentResult === null) {
                $usage = $usage->add($observedNestedUsage);
            }
            if ($result->agentResult !== null) {
                $nested = $result->agentResult;
                $usage = $usage->add($resuming && $child !== null ? $child->additionalUsage($nested->totalUsage) : $nested->totalUsage);
                if ($nested->resumeToken !== null) {
                    return new \AiSdk\Agents\ToolBatch($messages, $usage, new \AiSdk\Agents\ChildCheckpoint($call, $nested->resumeToken, $nested->totalUsage, $nested->approvals, $nested), array_values(array_slice($calls, $index)));
                }
            }
            $messages[] = Message::tool($call->id, is_string($result->output) ? $result->output : Json::encode($result->output), $call->name);
        }

        return new \AiSdk\Agents\ToolBatch($messages, $usage);
    }

    /**
     * @param list<Message> $messages
     * @param list<ToolCall> $calls
     * @param list<ToolCall> $approvals
     * @param array<int, Tool> $tools
     * @param array<string, bool> $decisions
     */
    private function pause(TextResult $result, array $messages, Usage $usage, array $calls, array $approvals, int $remaining, string $scope, RunTrace $trace, int $completedSteps, array $tools, ?\AiSdk\Agents\ChildCheckpoint $child = null, array $decisions = []): AgentResult
    {
        if ($scope === '' || $this->store === null || $this->signingKey === null || strlen($this->signingKey) < 32) {
            throw new ApprovalException('A nonempty application authorization scope and checkpoint storage are required for approval.');
        }
        $key = bin2hex(random_bytes(32));
        $this->store->put($key, new AgentCheckpoint($scope, $this->id, time() + $this->approvalTtl, $messages, $calls, array_map(static fn(ToolCall $call): string => $call->id, $approvals), $remaining, $usage, $trace->context, $completedSteps, array_values(array_map(static fn(Tool $tool): string => $tool->name(), $tools)), $child, $decisions));
        $token = $key . '.' . hash_hmac('sha256', Json::encode([$key, $scope, $this->id]), $this->signingKey);
        $trace->emit(new \AiSdk\Observability\Events\RunPaused($trace->context, $usage));

        return new AgentResult($result, $messages, $usage, $approvals, $token, runContext: $trace->context, pendingChild: $child?->result);
    }

    private function unavailableApproval(ToolCall $call, RunTrace $trace): ToolResult
    {
        $trace->emit(new \AiSdk\Observability\Events\ToolStarted($trace->context, $call->name, $call->id));
        $trace->emit(new \AiSdk\Observability\Events\ToolFailed($trace->context, $call->name, $call->id, 0, ApprovalException::class));

        return new ToolResult($call->id, $call->name, ['error' => 'The pending tool is no longer available for approval.'], isError: true);
    }
}
