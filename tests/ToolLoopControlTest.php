<?php

declare(strict_types=1);

use AiSdk\Exceptions\ToolExecutionException;
use AiSdk\FinishReason;
use AiSdk\Generate;
use AiSdk\Responses\Parts\TextPart;
use AiSdk\Responses\Parts\ToolCallPart;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Results\StepResult;
use AiSdk\Schema;
use AiSdk\StopWhen;
use AiSdk\Support\Usage;
use AiSdk\Tests\Fakes\FakeTextModel;
use AiSdk\Tool;

afterEach(fn() => Generate::reset());

function callsTool(string $name, array $arguments = [], string $id = 'call_1'): TextModelResponse
{
    return new TextModelResponse([new ToolCallPart($id, $name, $arguments)], FinishReason::ToolCalls, new Usage(10, 5));
}

function says(string $text): TextModelResponse
{
    return new TextModelResponse([new TextPart($text)], FinishReason::Stop, new Usage(10, 5));
}

function lastToolMessage(FakeTextModel $model): string
{
    $messages = $model->requests[array_key_last($model->requests)]->messages;

    return $messages[array_key_last($messages)]->text();
}

function weatherTool(): Tool
{
    return Tool::make('weather', 'Get the weather')
        ->input(Schema::object('weather_input', properties: [Schema::string('city')->required()]))
        ->run(fn(array $input): string => "Sunny in {$input['city']}");
}

it('tells the model when it calls an unknown tool and lets it continue', function () {
    $model = FakeTextModel::scripted([callsTool('nope'), says('recovered')]);

    $result = Generate::text('Hi')->model($model)->tools(weatherTool())->maxSteps(3)->run();

    expect($result->text)->toBe('recovered')
        ->and(lastToolMessage($model))->toContain('Unknown tool [nope]')->toContain('weather')
        ->and($result->toolResults[0]->isError)->toBeTrue();
});

it('returns precise input errors to the model so it can retry', function () {
    $model = FakeTextModel::scripted([callsTool('weather', []), callsTool('weather', ['city' => 'Lahore'], 'call_2'), says('done')]);

    $result = Generate::text('Hi')->model($model)->tools(weatherTool())->maxSteps(4)->run();

    expect($result->text)->toBe('done')
        ->and($result->toolResults[0]->isError)->toBeTrue()
        ->and(json_encode($result->toolResults[0]->output))->toContain('city')
        ->and($result->toolResults[1]->output)->toBe('Sunny in Lahore')
        ->and($result->toolResults[1]->isError)->toBeFalse();
});

it('does not leak handler exception messages to the model unless asked', function () {
    $explode = Tool::make('explode', 'Fails')->run(function (): never {
        throw new RuntimeException('db password is hunter2');
    });

    $hidden = FakeTextModel::scripted([callsTool('explode'), says('ok')]);
    Generate::text('Hi')->model($hidden)->tools($explode)->maxSteps(3)->run();
    expect(lastToolMessage($hidden))->toContain('failed to execute')->not->toContain('hunter2');

    $exposed = FakeTextModel::scripted([callsTool('explode'), says('ok')]);
    Generate::text('Hi')->model($exposed)->tools($explode)->exposeToolErrors()->maxSteps(3)->run();
    expect(lastToolMessage($exposed))->toContain('hunter2');
});

it('can still abort on tool errors', function () {
    $explode = Tool::make('explode', 'Fails')->run(function (): never {
        throw new RuntimeException('boom');
    });

    expect(fn() => Generate::text('Hi')->model(FakeTextModel::scripted([callsTool('explode'), says('x')]))->tools($explode)->throwOnToolError()->maxSteps(3)->run())
        ->toThrow(ToolExecutionException::class);
});

it('reports every step to onStepFinish', function () {
    $steps = [];
    $model = FakeTextModel::scripted([callsTool('weather', ['city' => 'Lahore']), says('final')]);

    Generate::text('Hi')->model($model)->tools(weatherTool())->maxSteps(3)
        ->onStepFinish(function (StepResult $step) use (&$steps): void {
            $steps[] = $step;
        })
        ->run();

    expect($steps)->toHaveCount(2)
        ->and($steps[0]->number)->toBe(1)
        ->and($steps[0]->calledTool('weather'))->toBeTrue()
        ->and($steps[0]->toolResults[0]->output)->toBe('Sunny in Lahore')
        ->and($steps[1]->text)->toBe('final')
        ->and($steps[1]->toolCalls)->toBe([]);
});

it('stops the loop when a stop condition matches', function () {
    $submit = Tool::make('submit_answer', 'Submit')->run(fn(): string => 'submitted');
    $model = FakeTextModel::scripted([callsTool('submit_answer'), says('should not be reached')]);

    $result = Generate::text('Hi')->model($model)->tools($submit)->maxSteps(10)->stopWhen(StopWhen::toolCalled('submit_answer'))->run();

    expect($model->requests)->toHaveCount(1)
        ->and($result->toolResults[0]->output)->toBe('submitted');
});

it('applies the same tool handling when streaming', function () {
    $model = FakeTextModel::scripted(
        [callsTool('nope'), says('after')],
        [
            [new AiSdk\Streaming\ToolCallStartPart(0, 'call_1', 'nope'), new AiSdk\Streaming\FinishPart(FinishReason::ToolCalls, new Usage(1, 1))],
            [new AiSdk\Streaming\TextDeltaPart('after'), new AiSdk\Streaming\FinishPart(FinishReason::Stop, new Usage(1, 1))],
        ],
    );

    $stream = Generate::text('Hi')->model($model)->tools(weatherTool())->maxSteps(3)->stream();
    foreach ($stream as $part) {
        // consume
    }

    expect($stream->result()->text)->toBe('after')
        ->and($stream->result()->toolResults[0]->isError)->toBeTrue();
});
