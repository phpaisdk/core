<?php

declare(strict_types=1);

use AiSdk\FinishReason;
use AiSdk\Streaming\FinishPart;
use AiSdk\Streaming\Stream;
use AiSdk\Streaming\TextDeltaPart;
use AiSdk\Streaming\ToolCallDeltaPart;
use AiSdk\Streaming\ToolCallStartPart;
use AiSdk\Support\Usage;

function streamFrom(array $parts): Stream
{
    $gen = (function () use ($parts) {
        yield from $parts;
    })();

    return new Stream($gen);
}

it('accumulates multi-chunk tool call arguments into one decoded ToolCall', function () {
    // Arguments arrive as partial JSON fragments across multiple deltas.
    $stream = streamFrom([
        new ToolCallStartPart(0, 'call_1', 'get_weather'),
        new ToolCallDeltaPart(0, '{"ci'),
        new ToolCallDeltaPart(0, 'ty":"Lah'),
        new ToolCallDeltaPart(0, 'ore"}'),
        new FinishPart(FinishReason::ToolCalls, new Usage(5, 3)),
    ]);

    $result = $stream->run();

    expect($result->toolCalls)->toHaveCount(1);
    expect($result->toolCalls[0]->id)->toBe('call_1');
    expect($result->toolCalls[0]->name)->toBe('get_weather');
    expect($result->toolCalls[0]->arguments)->toBe(['city' => 'Lahore']);
});

it('keeps separate tool calls distinct by index', function () {
    $stream = streamFrom([
        new ToolCallStartPart(0, 'call_a', 'first'),
        new ToolCallStartPart(1, 'call_b', 'second'),
        new ToolCallDeltaPart(0, '{"x":1}'),
        new ToolCallDeltaPart(1, '{"y":2}'),
        new FinishPart(FinishReason::ToolCalls, Usage::empty()),
    ]);

    $result = $stream->run();

    expect($result->toolCalls)->toHaveCount(2);
    expect($result->toolCalls[0]->arguments)->toBe(['x' => 1]);
    expect($result->toolCalls[1]->arguments)->toBe(['y' => 2]);
});

it('accumulates text deltas and fires onChunk', function () {
    $chunks = [];
    $stream = streamFrom([
        new TextDeltaPart('Hel'),
        new TextDeltaPart('lo'),
        new FinishPart(FinishReason::Stop, Usage::empty()),
    ]);

    $stream->onChunk(function (string $c) use (&$chunks) {
        $chunks[] = $c;
    });

    $result = $stream->run();

    expect($chunks)->toBe(['Hel', 'lo']);
    expect($result->text)->toBe('Hello');
});
