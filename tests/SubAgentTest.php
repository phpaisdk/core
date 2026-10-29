<?php

declare(strict_types=1);

use AiSdk\Agent;
use AiSdk\Exceptions\AgentDepthException;
use AiSdk\Exceptions\ApprovalException;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\FinishReason;
use AiSdk\Observability\Events\RunStarted;
use AiSdk\Observability\Events\ToolFailed;
use AiSdk\Responses\Parts\TextPart;
use AiSdk\Responses\Parts\ToolCallPart;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Schema;
use AiSdk\Support\Usage;
use AiSdk\Testing\Fakes\FakeTextModel;
use AiSdk\Testing\Fakes\InMemoryCheckpointStore;
use AiSdk\Testing\Fakes\RecordingEvents;
use AiSdk\Tool;
use AiSdk\ToolExecutionContext;

function nestedResponse(string $name, string $callId = 'delegate'): TextModelResponse
{
    return new TextModelResponse([new ToolCallPart($callId, $name, ['prompt' => 'child input'])], FinishReason::ToolCalls, new Usage(2, 3, reasoningTokens: 1, cachedInputTokens: 1, cacheWriteInputTokens: 1));
}

function nestedAnswer(string $text): TextModelResponse
{
    return new TextModelResponse([new TextPart($text)], FinishReason::Stop, new Usage(3, 4, reasoningTokens: 2, cachedInputTokens: 2, cacheWriteInputTokens: 2));
}

it('runs sub-agents as tools with parent links, forwarded events and rolled up usage', function () {
    $childModel = new FakeTextModel(nestedAnswer('child answer'));
    $child = new Agent($childModel);
    $parentModel = FakeTextModel::scripted([nestedResponse('child'), nestedAnswer('parent answer')]);
    $events = new RecordingEvents();
    $result = (new Agent($parentModel, tools: [$child->asTool('child', 'Delegate work')]))->onEvent($events)->run('hi');
    expect($result->result->text)->toBe('parent answer')->and($result->totalUsage->totalTokens)->toBe(19)
        ->and($result->totalUsage->reasoningTokens)->toBe(5)
        ->and($result->totalUsage->cachedInputTokens)->toBe(5)
        ->and($childModel->lastRequest->messages[0]->text())->toBe('child input')
        ->and($parentModel->lastRequest->messages[2]->text())->toBe('child answer');
    $runs = array_values(array_filter($events->events, fn($event) => $event instanceof RunStarted));
    expect($runs)->toHaveCount(2)->and($runs[1]->run->parentInvocationId)->toBe($runs[0]->run->invocationId)
        ->and($runs[1]->run->depth)->toBe(1);
});

it('validates custom sub-agent inputs before invoking the child', function () {
    $childModel = FakeTextModel::text('ok');
    $tool = (new Agent($childModel))->asTool('child', 'Delegate work', Schema::object('input', properties: [Schema::integer('order')->required()]));
    $model = FakeTextModel::scripted([
        new TextModelResponse([new ToolCallPart('bad', 'child', ['order' => 'bad']), new ToolCallPart('good', 'child', ['order' => 42])], FinishReason::ToolCalls, Usage::empty()),
        nestedAnswer('done'),
    ]);
    $result = (new Agent($model, tools: [$tool]))->run('hi');
    expect($childModel->requests)->toHaveCount(1)->and($childModel->lastRequest->messages[0]->text())->toBe('{"order":42}')
        ->and($result->messages[2]->text())->toContain('error');
});

it('bounds nested depth with the smallest shared ancestor limit', function () {
    $grandchild = new Agent(FakeTextModel::text('must not run'));
    $childModel = FakeTextModel::scripted([nestedResponse('grandchild'), nestedAnswer('child recovered')]);
    $child = new Agent($childModel, tools: [$grandchild->asTool('grandchild', 'Nested work')], maxDepth: 10);
    $parent = new Agent(FakeTextModel::scripted([nestedResponse('child'), nestedAnswer('done')]), tools: [$child->asTool('child', 'Child work')], maxDepth: 1);
    $events = new RecordingEvents();
    expect($parent->onEvent($events)->run('hi')->result->text)->toBe('done')
        ->and(json_decode($childModel->lastRequest->messages[2]->text(), true)['type'])->toBe(AgentDepthException::class);
    $failed = array_values(array_filter($events->events, fn($event) => $event instanceof ToolFailed));
    expect($failed)->toHaveCount(1)->and($failed[0]->exceptionClass)->toBe(AgentDepthException::class);
    expect(fn() => new Agent(FakeTextModel::text('ok'), maxDepth: 33))->toThrow(InvalidArgumentException::class);
});

it('detects an agent revisited through a mutable forwarding tool', function () {
    $forward = new class ('loop') extends Tool {
        public ?Tool $target = null;
        public function call(array $args, ?ToolExecutionContext $context = null): mixed
        {
            return $this->target->call($args, $context);
        }
    };
    $model = FakeTextModel::scripted([nestedResponse('loop'), nestedAnswer('recovered')]);
    $agent = new Agent($model, tools: [$forward], maxDepth: 32);
    $forward->target = $agent->asTool('loop', 'Cycle');
    $result = $agent->run('hi');
    expect($model->requests)->toHaveCount(2)->and(json_decode($result->messages[2]->text(), true)['type'])->toBe(AgentDepthException::class);
});

it('propagates nested approval and resumes child then parent without replaying siblings', function (bool $allow) {
    $executed = [];
    $childModel = FakeTextModel::scripted([
        new TextModelResponse([new TextPart('I will write'), new ToolCallPart('write-id', 'write', [])], FinishReason::ToolCalls, new Usage(2, 3)),
        nestedAnswer('child done'),
    ]);
    $child = new Agent(
        $childModel,
        tools: [Tool::make('write')->needsApproval()->run(function () use (&$executed) {
            $executed[] = 'write';

            return 'ok';
        })],
        store: new InMemoryCheckpointStore(),
        signingKey: str_repeat('c', 32),
        id: 'child',
    );
    $parentModel = FakeTextModel::scripted([
        new TextModelResponse([new ToolCallPart('before', 'read', []), new ToolCallPart('nested', 'child', ['prompt' => 'work']), new ToolCallPart('after', 'read', [])], FinishReason::ToolCalls, new Usage(2, 3)),
        nestedAnswer('parent done'),
    ]);
    $parent = new Agent(
        $parentModel,
        tools: [$child->asTool('child', 'Work'), Tool::make('read')->run(function () use (&$executed) {
            $executed[] = 'read';

            return 'ok';
        })],
        store: new InMemoryCheckpointStore(),
        signingKey: str_repeat('p', 32),
        id: 'parent',
    );
    $paused = $parent->run('hi', 'owner');
    expect($executed)->toBe(['read'])->and($paused->approvals[0]->id)->toBe('write-id')
        ->and($paused->totalUsage->totalTokens)->toBe(10)->and($paused->pendingChild->messages[1]->text())->toBe('I will write');
    expect(fn() => $parent->resume($paused->resumeToken, [], 'owner'))->toThrow(ApprovalException::class);
    expect(fn() => $parent->resume($paused->resumeToken, ['write-id' => true], 'wrong'))->toThrow(ApprovalException::class);
    $done = $parent->resume($paused->resumeToken, ['write-id' => $allow], 'owner');
    expect($done->result->text)->toBe('parent done')->and($done->totalUsage->totalTokens)->toBe(24)
        ->and($executed)->toBe($allow ? ['read', 'write', 'read'] : ['read', 'read'])
        ->and($done->runContext->invocationId)->toBe($paused->runContext->invocationId)
        ->and($childModel->requests)->toHaveCount(2)->and($parentModel->requests)->toHaveCount(2)
        ->and($parentModel->lastRequest->messages[3]->text())->toBe('child done');
    expect(fn() => $parent->resume($paused->resumeToken, ['write-id' => true], 'owner'))->toThrow(ApprovalException::class);
})->with([true, false]);

it('propagates a grandchild pause across two parents and preserves all budgets', function () {
    $leafModel = FakeTextModel::scripted([nestedResponse('write', 'leaf-approval'), nestedAnswer('leaf done')]);
    $leaf = new Agent($leafModel, tools: [Tool::make('write')->needsApproval()->run(fn() => 'ok')], store: new InMemoryCheckpointStore(), signingKey: str_repeat('l', 32), id: 'leaf');
    $childModel = FakeTextModel::scripted([nestedResponse('leaf'), nestedAnswer('child done')]);
    $child = new Agent($childModel, tools: [$leaf->asTool('leaf', 'Leaf')], store: new InMemoryCheckpointStore(), signingKey: str_repeat('c', 32), id: 'child');
    $rootModel = new FakeTextModel(nestedResponse('child'));
    $root = new Agent($rootModel, tools: [$child->asTool('child', 'Child')], maxSteps: 1, store: new InMemoryCheckpointStore(), signingKey: str_repeat('r', 32), id: 'root');
    $paused = $root->run('hi', 'owner');
    $done = $root->resume($paused->resumeToken, ['leaf-approval' => true], 'owner');
    expect($paused->approvals[0]->id)->toBe('leaf-approval')->and($done->stepLimitReached)->toBeTrue()
        ->and($done->totalUsage->totalTokens)->toBe(29)->and($rootModel->requests)->toHaveCount(1);
});

it('requires parent checkpoint configuration when a child can pause', function () {
    $child = new Agent(FakeTextModel::text('ok'), store: new InMemoryCheckpointStore(), signingKey: str_repeat('x', 32));
    expect(fn() => new Agent(FakeTextModel::text('ok'), tools: [$child->asTool('child', 'Work')]))->toThrow(InvalidArgumentException::class);
});

it('handles repeated child pauses without double-counting usage', function () {
    $executed = 0;
    $childModel = FakeTextModel::scripted([nestedResponse('write', 'first'), nestedResponse('write', 'second'), nestedAnswer('child done')]);
    $child = new Agent(
        $childModel,
        tools: [Tool::make('write')->needsApproval()->run(function () use (&$executed) {
            return ++$executed;
        })],
        store: new InMemoryCheckpointStore(),
        signingKey: str_repeat('c', 32),
        id: 'child',
    );
    $parentModel = FakeTextModel::scripted([nestedResponse('child'), nestedAnswer('parent done')]);
    $parent = new Agent($parentModel, tools: [$child->asTool('child', 'Work')], store: new InMemoryCheckpointStore(), signingKey: str_repeat('p', 32), id: 'parent');
    $first = $parent->run('hi', 'owner');
    $second = $parent->resume($first->resumeToken, ['first' => true], 'owner');
    expect($second->approvals[0]->id)->toBe('second')->and($second->totalUsage->totalTokens)->toBe(15);
    $done = $parent->resume($second->resumeToken, ['second' => true], 'owner');
    expect($executed)->toBe(2)->and($done->totalUsage->totalTokens)->toBe(29)
        ->and($parentModel->requests)->toHaveCount(2)->and($childModel->requests)->toHaveCount(3);
});

it('retains parent approval decisions while child approval is pending', function () {
    $executed = [];
    $child = new Agent(
        FakeTextModel::scripted([nestedResponse('write', 'inside'), nestedAnswer('child done')]),
        tools: [Tool::make('write')->needsApproval()->run(fn() => 'ok')],
        store: new InMemoryCheckpointStore(),
        signingKey: str_repeat('c', 32),
        id: 'child',
    );
    $childTool = $child->asTool('child', 'Work')->needsApproval();
    $after = Tool::make('after')->needsApproval()->run(function () use (&$executed) {
        $executed[] = 'after';
    });
    $model = FakeTextModel::scripted([
        new TextModelResponse([new ToolCallPart('outside', 'child', ['prompt' => 'work']), new ToolCallPart('after', 'after', [])], FinishReason::ToolCalls, Usage::empty()),
        nestedAnswer('done'),
    ]);
    $parent = new Agent($model, tools: [$childTool, $after], store: new InMemoryCheckpointStore(), signingKey: str_repeat('p', 32), id: 'parent');
    $local = $parent->run('hi', 'owner');
    $nested = $parent->resume($local->resumeToken, ['outside' => true, 'after' => false], 'owner');
    $done = $parent->resume($nested->resumeToken, ['inside' => true], 'owner');
    expect($done->result->text)->toBe('done')->and($executed)->toBe([])
        ->and($done->messages[3]->text())->toContain('denied');
});

it('accounts for completed child steps even when a later child step fails', function () {
    $childModel = FakeTextModel::scripted([nestedResponse('break')]);
    $failure = new \AiSdk\Exceptions\InvalidResponseException('private');
    $child = new Agent($childModel, tools: [Tool::make('break')->run(function () use ($childModel, $failure) {
        $childModel->failWith($failure);

        return 'ok';
    })]);
    $parent = new Agent(FakeTextModel::scripted([nestedResponse('child'), nestedAnswer('recovered')]), tools: [$child->asTool('child', 'Work')]);
    expect($parent->run('hi')->totalUsage->totalTokens)->toBe(17);
});

it('uses a default shared nesting limit of three', function () {
    $agent = new Agent(FakeTextModel::text('unreachable leaf'));
    for ($i = 0; $i < 4; $i++) {
        $agent = new Agent(FakeTextModel::scripted([nestedResponse('next'), nestedAnswer('done')]), tools: [$agent->asTool('next', 'Nested work')]);
    }
    $events = new RecordingEvents();
    $agent->onEvent($events)->run('hi');
    $runs = array_values(array_filter($events->events, fn($event) => $event instanceof RunStarted));
    expect($runs)->toHaveCount(4)->and($runs[3]->run->depth)->toBe(3);
});
