<?php

declare(strict_types=1);

use AiSdk\Agent;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\FinishReason;
use AiSdk\Responses\Parts\TextPart;
use AiSdk\Responses\Parts\ToolCallPart;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Support\Usage;
use AiSdk\Testing\Fakes\FakeTextModel;
use AiSdk\Testing\Fakes\InMemoryCheckpointStore;
use AiSdk\Testing\Fakes\RecordingEvents;
use AiSdk\Tool;

it('replaces tools by name and adds tools without mutating the original agent', function () {
    $model = FakeTextModel::text('ok');
    $old = Tool::make('lookup')->run(fn() => 'old');
    $other = Tool::make('other')->run(fn() => 'other');
    $replacement = Tool::make('lookup')->run(fn() => 'new');
    $added = Tool::make('added')->run(fn() => 'added');
    $base = new Agent($model, instructions: 'retain instructions', tools: [$old, $other]);
    $clone = $base->withTools($replacement, $added);
    $clone->run('hi');
    expect($model->lastRequest->tools)->toBe([$replacement, $other, $added])->and($model->lastRequest->system)->toBe('retain instructions');
    $base->run('hi');
    expect($model->lastRequest->tools)->toBe([$old, $other]);
    $clone->without('lookup', 'missing')->run('hi');
    expect($model->lastRequest->tools)->toBe([$other, $added]);
    $clone->run('hi');
    expect($model->lastRequest->tools)->toBe([$replacement, $other, $added]);
});

it('retains uniqueness and approval configuration validation for overrides', function () {
    $agent = new Agent(FakeTextModel::text('ok'));
    expect(fn() => $agent->withTools(Tool::make('a'), Tool::make('a')))->toThrow(InvalidArgumentException::class);
    expect(fn() => $agent->withTools(Tool::make('')))->toThrow(InvalidArgumentException::class);
    expect(fn() => $agent->withTools(Tool::make('a')->needsApproval()))->toThrow(InvalidArgumentException::class);
});

it('preserves listeners and settles pending tools removed by a runtime clone', function () {
    $model = FakeTextModel::scripted([
        new TextModelResponse([new ToolCallPart('a', 'send', [])], FinishReason::ToolCalls, Usage::empty()),
        new TextModelResponse([new TextPart('done')], FinishReason::Stop, Usage::empty()),
    ]);
    $events = new RecordingEvents();
    $executed = false;
    $agent = (new Agent(
        $model,
        tools: [Tool::make('send')->needsApproval()->run(function () use (&$executed) {
            $executed = true;
        })],
        store: new InMemoryCheckpointStore(),
        signingKey: str_repeat('x', 32),
    ))->onEvent($events);
    $paused = $agent->run('hi', 'owner');
    $done = $agent->without('send')->resume($paused->resumeToken, [], 'owner');
    expect($done->result->text)->toBe('done')->and($executed)->toBeFalse()->and($events->events)->toHaveCount(9);
});

it('retains ancestor identity across tool override clones to prevent self cycles', function () {
    $model = new FakeTextModel(new TextModelResponse([new ToolCallPart('a', 'self', ['prompt' => 'hi'])], FinishReason::ToolCalls, Usage::empty()));
    $agent = new Agent($model, maxSteps: 1);
    $result = $agent->withTools($agent->asTool('self', 'Self'))->run('hi');
    expect($model->requests)->toHaveCount(1)->and($result->messages[2]->text())->toContain('AgentDepthException');
});
