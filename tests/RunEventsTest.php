<?php

declare(strict_types=1);

use AiSdk\Agent;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Exceptions\ToolExecutionException;
use AiSdk\FinishReason;
use AiSdk\Generate;
use AiSdk\Observability\Events\RunFailed;
use AiSdk\Observability\Events\RunFinished;
use AiSdk\Observability\Events\RunPaused;
use AiSdk\Observability\Events\RunStarted;
use AiSdk\Observability\Events\StepFinished;
use AiSdk\Observability\Events\StepStarted;
use AiSdk\Observability\Events\ToolFailed;
use AiSdk\Observability\Events\ToolFinished;
use AiSdk\Observability\Events\ToolStarted;
use AiSdk\Observability\RunContext;
use AiSdk\Observability\RunEvent;
use AiSdk\Responses\Parts\TextPart;
use AiSdk\Responses\Parts\ToolCallPart;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Streaming\FinishPart;
use AiSdk\Streaming\TextDeltaPart;
use AiSdk\Streaming\ToolCallStartPart as StreamToolCall;
use AiSdk\Support\Usage;
use AiSdk\Testing\Fakes\FakeTextModel;
use AiSdk\Testing\Fakes\RecordingEvents;
use AiSdk\Tool;
use AiSdk\ToolExecutionContext;

function eventLoopModel(): FakeTextModel
{
    return FakeTextModel::scripted([
        new TextModelResponse([new ToolCallPart('c', 'lookup', [])], FinishReason::ToolCalls, new Usage(2, 3)),
        new TextModelResponse([new TextPart('done')], FinishReason::Stop, new Usage(3, 4)),
    ], [
        [new StreamToolCall(0, 'c', 'lookup'), new FinishPart(FinishReason::ToolCalls, new Usage(2, 3))],
        [new TextDeltaPart('done'), new FinishPart(FinishReason::Stop, new Usage(3, 4))],
    ]);
}

it('threads one context through every step and tool in agents and both text loops', function (string $mode) {
    $record = new RecordingEvents();
    $receivedContext = null;
    $tool = Tool::make('lookup')->run(function (ToolExecutionContext $context) use (&$receivedContext): string {
        $receivedContext = $context->runContext;

        return 'ok';
    });
    $model = eventLoopModel();
    $result = match ($mode) {
        'agent' => (new Agent($model, tools: [$tool]))->onEvent($record)->run('hello'),
        'text' => Generate::text('hello')->model($model)->tools($tool)->maxSteps(3)->onEvent($record)->run(),
        'stream' => Generate::text('hello')->model($model)->tools($tool)->maxSteps(3)->onEvent($record)->stream()->run(),
    };
    expect(array_map(fn($e) => $e::class, $record->events))->toBe([
        RunStarted::class, StepStarted::class, StepFinished::class, ToolStarted::class, ToolFinished::class,
        StepStarted::class, StepFinished::class, RunFinished::class,
    ]);
    $ids = array_unique(array_map(fn($e) => $e->context()->invocationId, $record->events));
    expect($ids)->toHaveCount(1)->and($receivedContext->invocationId)->toBe($result->runContext->invocationId)
        ->and($result->runContext->finishedAt)->not->toBeNull()
        ->and($record->events[2]->usage->totalTokens)->toBe(5)
        ->and($record->events[4]->durationMicroseconds)->toBeGreaterThanOrEqual(0)
        ->and($record->events[6]->stepIndex)->toBe(2)
        ->and($record->events[7]->usage->totalTokens)->toBe(12);
})->with(['agent', 'text', 'stream']);

it('records safe tool errors without aborting later steps', function () {
    $record = new RecordingEvents();
    Generate::text('hello')->model(eventLoopModel())->tools(Tool::make('lookup')->run(fn() => throw new RuntimeException('password=unrecognizable-secret')))
        ->maxSteps(2)->onEvent($record)->run();
    expect($record->events[4])->toBeInstanceOf(ToolFailed::class)
        ->and($record->events[4]->exceptionClass)->toBe(ToolExecutionException::class)
        ->and($record->events[7])->toBeInstanceOf(RunFinished::class)
        ->and(json_encode($record->events))->not->toContain('unrecognizable-secret');
});

it('emits a single safe terminal failure for model errors', function (string $mode) {
    $record = new RecordingEvents();
    $failure = new InvalidResponseException('Bearer secret-and-private-prompt');
    $model = FakeTextModel::text('ignored')->failWith($failure);
    $operation = match ($mode) {
        'agent' => fn() => (new Agent($model))->onEvent($record)->run('private'),
        'text' => fn() => Generate::text('private')->model($model)->onEvent($record)->run(),
        'stream' => fn() => Generate::text('private')->model($model)->onEvent($record)->stream()->run(),
    };
    expect($operation)->toThrow(InvalidResponseException::class);
    expect(array_map(fn($e) => $e::class, $record->events))->toBe([RunStarted::class, StepStarted::class, RunFailed::class]);
    expect($record->events[2]->exceptionClass)->toBe($failure::class)
        ->and($record->events[2]->run->finishedAt)->not->toBeNull()
        ->and(json_encode($record->events))->not->toContain('secret-and-private-prompt');
})->with(['agent', 'text', 'stream']);

it('isolates every listener and makes a fresh invocation per reusable agent run', function () {
    $record = new RecordingEvents();
    $calls = 0;
    $agent = (new Agent(FakeTextModel::text('ok')))->onEvent(fn() => throw new RuntimeException('listener'))
        ->onEvent($record)->onEvent(function (RunEvent $event) use (&$calls): void {
            $calls++;
        });
    $a = $agent->run('a');
    $b = $agent->run('b');
    expect($calls)->toBe(8)->and($a->runContext->invocationId)->not->toBe($b->runContext->invocationId);
});

it('links explicit child runs and text tool loops without ambient state', function () {
    $parent = RunContext::start();
    $child = (new Agent(FakeTextModel::text('ok')))->runWithContext('child', $parent);
    $text = Generate::text('child')->model(FakeTextModel::text('ok'))->withRunContext($parent)->run();
    expect($child->runContext->parentInvocationId)->toBe($parent->invocationId)
        ->and($text->runContext->parentInvocationId)->toBe($parent->invocationId);
});

it('preserves invocation and step numbering across approval checkpoints', function () {
    $record = new RecordingEvents();
    $agent = (new Agent(
        eventLoopModel(),
        tools: [Tool::make('lookup')->needsApproval()->run(fn() => 'ok')],
        store: new \AiSdk\Testing\Fakes\InMemoryCheckpointStore(),
        signingKey: str_repeat('x', 32),
    ))->onEvent($record);
    $paused = $agent->run('hello', 'owner');
    expect($paused->runContext->finishedAt)->toBeNull()->and($record->events[3])->toBeInstanceOf(RunPaused::class);
    $done = $agent->resume($paused->resumeToken, ['c' => true], 'owner');
    expect($done->runContext->invocationId)->toBe($paused->runContext->invocationId)
        ->and($done->runContext->startedAt)->toBe($paused->runContext->startedAt)
        ->and($done->runContext->finishedAt)->not->toBeNull()
        ->and($record->events[6]->stepIndex)->toBe(2);
});
