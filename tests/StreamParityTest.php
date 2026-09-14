<?php

declare(strict_types=1);

use AiSdk\FinishReason;
use AiSdk\Generate;
use AiSdk\Outputs\Output;
use AiSdk\Responses\Parts\TextPart;
use AiSdk\Responses\Parts\ToolCallPart;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Schema;
use AiSdk\Streaming\FinishPart;
use AiSdk\Streaming\TextDeltaPart;
use AiSdk\Streaming\ToolCallDeltaPart;
use AiSdk\Streaming\ToolCallStartPart;
use AiSdk\Support\Usage;
use AiSdk\Tests\Fakes\FakeTextModel;
use AiSdk\Tool;

afterEach(fn() => Generate::reset());

it('continues streamed tool calls and reports total usage', function () {
    $model = FakeTextModel::scripted(
        responses: [
            new TextModelResponse([new ToolCallPart('call_1', 'weather', ['city' => 'Lahore'])], FinishReason::ToolCalls, new Usage(3, 2)),
            new TextModelResponse([new TextPart('Sunny')], FinishReason::Stop, new Usage(4, 3)),
        ],
        streamSequences: [
            [new ToolCallStartPart(0, 'call_1', 'weather'), new ToolCallDeltaPart(0, '{"city":"Lahore"}'), new FinishPart(FinishReason::ToolCalls, new Usage(3, 2))],
            [new TextDeltaPart('Sunny'), new FinishPart(FinishReason::Stop, new Usage(4, 3))],
        ],
    );

    $result = Generate::text('Weather?')
        ->model($model)
        ->tool(Tool::make('weather')->input(Schema::string('city')->required())->run(fn(string $city): string => "Sunny in {$city}"))
        ->maxSteps(2)
        ->stream()
        ->run();

    expect($result->text)->toBe('Sunny')
        ->and($result->toolResults)->toHaveCount(1)
        ->and($result->usage->totalTokens)->toBe(7)
        ->and($result->totalUsage->totalTokens)->toBe(12)
        ->and($model->requests)->toHaveCount(2);
});

it('validates structured output after a stream completes', function () {
    $result = Generate::text('Return a city')
        ->model(FakeTextModel::scripted(
            responses: [new TextModelResponse([new TextPart('{"city":"Lahore"}')], FinishReason::Stop, Usage::empty())],
            streamSequences: [[new TextDeltaPart('{"city":"Lahore"}'), new FinishPart(FinishReason::Stop, Usage::empty())]],
        ))
        ->output(Output::schema(Schema::object('address', properties: [Schema::string('city')->required()])))
        ->stream()
        ->run();

    expect($result->output)->toBe(['city' => 'Lahore']);
});

it('uses the final streamed step for structured output', function () {
    $result = Generate::text('Return a city')
        ->model(FakeTextModel::scripted(
            responses: [
                new TextModelResponse([new TextPart('Checking…'), new ToolCallPart('call_1', 'lookup', [])], FinishReason::ToolCalls, Usage::empty()),
                new TextModelResponse([new TextPart('{"city":"Lahore"}')], FinishReason::Stop, Usage::empty()),
            ],
            streamSequences: [
                [new TextDeltaPart('Checking…'), new ToolCallStartPart(0, 'call_1', 'lookup'), new ToolCallDeltaPart(0, '{}'), new FinishPart(FinishReason::ToolCalls, Usage::empty())],
                [new TextDeltaPart('{"city":"Lahore"}'), new FinishPart(FinishReason::Stop, Usage::empty())],
            ],
        ))
        ->tool(Tool::make('lookup')->run(fn(): string => 'Found it'))
        ->output(Output::schema(Schema::object('address', properties: [Schema::string('city')->required()])))
        ->maxSteps(2)
        ->stream()
        ->run();

    expect($result->text)->toBe('{"city":"Lahore"}')
        ->and($result->output)->toBe(['city' => 'Lahore']);
});
