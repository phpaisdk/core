<?php

declare(strict_types=1);

use AiSdk\Agent;
use AiSdk\Agents\AgentCheckpoint;
use AiSdk\Agents\CheckpointStore;
use AiSdk\Exceptions\ApprovalException;
use AiSdk\FinishReason;
use AiSdk\Responses\Parts\TextPart;
use AiSdk\Responses\Parts\ToolCallPart;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Support\Usage;
use AiSdk\Testing\Fakes\FakeTextModel;
use AiSdk\Tool;

function approvalStore(): CheckpointStore
{
    return new class implements CheckpointStore {
        private array $items = [];
        public function put(string $id, AgentCheckpoint $checkpoint): void
        {
            $this->items[$id] = $checkpoint;
        }
        public function consume(string $id): ?AgentCheckpoint
        {
            $value = $this->items[$id] ?? null;
            unset($this->items[$id]);

            return $value;
        }
    };
}

it('pauses the entire batch and resumes only once with a scoped decision', function (bool $approve) {
    $executions = 0;
    $model = FakeTextModel::scripted([
        new TextModelResponse([new ToolCallPart('call', 'send', [])], FinishReason::ToolCalls, new Usage(2, 3)),
        new TextModelResponse([new TextPart('done')], FinishReason::Stop, new Usage(3, 4)),
    ]);
    $tool = Tool::make('send')->needsApproval()->run(function () use (&$executions) {
        $executions++;

        return 'sent';
    });
    $agent = new Agent($model, tools: [$tool], store: approvalStore(), signingKey: str_repeat('x', 32));
    $pause = $agent->run('send it', 'tenant:1:user:2');
    expect($executions)->toBe(0)->and($pause->approvals)->toHaveCount(1)->and($model->requests)->toHaveCount(1);
    expect(fn() => $agent->resume($pause->resumeToken, ['call' => true], 'tenant:other'))->toThrow(ApprovalException::class);
    $done = $agent->resume($pause->resumeToken, ['call' => $approve], 'tenant:1:user:2');
    expect($done->result->text)->toBe('done')->and($executions)->toBe($approve ? 1 : 0)
        ->and($done->totalUsage->totalTokens)->toBe(12);
    expect(fn() => $agent->resume($pause->resumeToken, ['call' => true], 'tenant:1:user:2'))->toThrow(ApprovalException::class);
})->with([true, false]);

it('enforces a shared step budget across approval resumes', function () {
    $model = new FakeTextModel(new TextModelResponse([new ToolCallPart('call', 'send', [])], FinishReason::ToolCalls, Usage::empty()));
    $agent = new Agent($model, tools: [Tool::make('send')->needsApproval()->run(fn() => 'sent')], maxSteps: 1, store: approvalStore(), signingKey: str_repeat('x', 32));
    $pause = $agent->run('hi', 'user');
    $done = $agent->resume($pause->resumeToken, ['call' => true], 'user');
    expect($done->stepLimitReached)->toBeTrue()->and($model->requests)->toHaveCount(1);
});

it('does not execute tools when decisions are missing or the token was tampered with', function () {
    $executions = 0;
    $model = new FakeTextModel(new TextModelResponse([new ToolCallPart('call', 'send', [])], FinishReason::ToolCalls, Usage::empty()));
    $tool = Tool::make('send')->needsApproval()->run(function () use (&$executions) {
        $executions++;
    });
    $agent = new Agent($model, tools: [$tool], store: approvalStore(), signingKey: str_repeat('x', 32));
    $pause = $agent->run('hi', 'user');
    expect(fn() => $agent->resume($pause->resumeToken . 'bad', ['call' => true], 'user'))->toThrow(ApprovalException::class);
    expect(fn() => $agent->resume($pause->resumeToken, [], 'user'))->toThrow(ApprovalException::class);
    expect($executions)->toBe(0);
});

it('does not bypass approval in Generate tool loops', function () {
    $model = new FakeTextModel(new TextModelResponse([new ToolCallPart('call', 'send', [])], FinishReason::ToolCalls, Usage::empty()));
    expect(fn() => \AiSdk\Generate::text('hi')->model($model)->tool(Tool::make('send')->needsApproval()->run(fn() => 'sent'))->run())->toThrow(ApprovalException::class);
});

it('pauses nonrestricted siblings too until approval is resolved', function () {
    $executions = [];
    $model = FakeTextModel::scripted([
        new TextModelResponse([new ToolCallPart('a', 'read', []), new ToolCallPart('b', 'write', [])], FinishReason::ToolCalls, Usage::empty()),
        new TextModelResponse([new TextPart('done')], FinishReason::Stop, Usage::empty()),
    ]);
    $agent = new Agent($model, tools: [
        Tool::make('read')->run(function () use (&$executions) {
            $executions[] = 'read';

            return 'ok';
        }),
        Tool::make('write')->needsApproval()->run(function () use (&$executions) {
            $executions[] = 'write';

            return 'ok';
        }),
    ], store: approvalStore(), signingKey: str_repeat('x', 32));
    $paused = $agent->run('hi', 'user');
    expect($executions)->toBe([]);
    $agent->resume($paused->resumeToken, ['b' => false], 'user');
    expect($executions)->toBe(['read']);
});

it('rejects expired checkpoints without running tools', function () {
    $store = new class implements CheckpointStore {
        private ?AgentCheckpoint $checkpoint = null;
        public function put(string $id, AgentCheckpoint $checkpoint): void
        {
            $this->checkpoint = new AgentCheckpoint($checkpoint->scope, $checkpoint->agent, time() - 1, $checkpoint->messages, $checkpoint->calls, $checkpoint->approvalIds, $checkpoint->remainingSteps, $checkpoint->usage);
        }
        public function consume(string $id): ?AgentCheckpoint
        {
            $value = $this->checkpoint;
            $this->checkpoint = null;

            return $value;
        }
    };
    $model = new FakeTextModel(new TextModelResponse([new ToolCallPart('a', 'write', [])], FinishReason::ToolCalls, Usage::empty()));
    $executions = 0;
    $agent = new Agent($model, tools: [Tool::make('write')->needsApproval()->run(function () use (&$executions) {
        $executions++;
    })], store: $store, signingKey: str_repeat('x', 32));
    $paused = $agent->run('hi', 'user');
    expect(fn() => $agent->resume($paused->resumeToken, ['a' => true], 'user'))->toThrow(ApprovalException::class);
    expect($executions)->toBe(0);
});

it('keeps the approval pending when a malformed decision is submitted', function () {
    $executions = 0;
    $model = FakeTextModel::scripted([
        new TextModelResponse([new ToolCallPart('call', 'send', [])], FinishReason::ToolCalls, Usage::empty()),
        new TextModelResponse([new TextPart('done')], FinishReason::Stop, Usage::empty()),
    ]);
    $agent = new Agent($model, tools: [Tool::make('send')->needsApproval()->run(function () use (&$executions) {
        $executions++;
    })], store: approvalStore(), signingKey: str_repeat('x', 32));
    $paused = $agent->run('hi', 'user');
    expect(fn() => $agent->resume($paused->resumeToken, [], 'user'))->toThrow(ApprovalException::class);
    $done = $agent->resume($paused->resumeToken, ['call' => true], 'user');
    expect($done->result->text)->toBe('done')->and($executions)->toBe(1);
});

it('still reports a result when approval is resolved on the last allowed step', function () {
    $model = FakeTextModel::scripted([
        new TextModelResponse([new TextPart('about to act'), new ToolCallPart('call', 'send', [])], FinishReason::ToolCalls, Usage::empty()),
    ]);
    $agent = new Agent($model, tools: [Tool::make('send')->needsApproval()->run(fn() => 'sent')], maxSteps: 1, store: approvalStore(), signingKey: str_repeat('x', 32));
    $paused = $agent->run('hi', 'user');
    $done = $agent->resume($paused->resumeToken, ['call' => true], 'user');
    expect($done->stepLimitReached)->toBeTrue()->and($done->result)->not->toBeNull()->and($done->result->text)->toBe('about to act');
});
