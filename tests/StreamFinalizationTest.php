<?php

declare(strict_types=1);

use AiSdk\Results\TextResult;
use AiSdk\Streaming\Stream;
use AiSdk\Streaming\TextDeltaPart;

it('distinguishes snapshots from final results and resumes after partial consumption', function () {
    $stream = new Stream((function () {
        yield new TextDeltaPart('a');
        yield new TextDeltaPart('b');
    })());
    expect(fn() => $stream->result())->toThrow(LogicException::class);
    foreach ($stream as $part) {
        break;
    }
    expect($stream->snapshot()->text)->toBe('a')->and($stream->isComplete())->toBeFalse();
    $result = $stream->run();
    expect($result->text)->toBe('ab')->and($stream->result())->toBe($result)->and($stream->isComplete())->toBeTrue();
});

it('finalizes once and replays without calling hooks again', function () {
    $count = $finished = 0;
    $stream = new Stream((function () {
        yield new TextDeltaPart('ok');
    })(), function () use (&$count) {
        $count++;

        return new TextResult('ok');
    });
    $stream->onFinish(function () use (&$finished) {
        $finished++;
    });
    $result = $stream->run();
    iterator_to_array($stream);
    expect($stream->run())->toBe($result)->and($count)->toBe(1)->and($finished)->toBe(1);
});

it('retains finalization errors without reporting successful completion', function () {
    $error = new RuntimeException('invalid output');
    $errors = 0;
    $stream = new Stream((function () {
        yield new TextDeltaPart('{');
    })(), fn() => throw $error);
    $stream->onError(function () use (&$errors) {
        $errors++;
    });
    expect(fn() => $stream->run())->toThrow($error)
        ->and(fn() => $stream->result())->toThrow($error)
        ->and(fn() => $stream->run())->toThrow($error)
        ->and($stream->isComplete())->toBeFalse()->and($errors)->toBe(1);
});

it('rejects overlapping consumers without poisoning a resumable stream', function () {
    $stream = new Stream((function () {
        yield new TextDeltaPart('a');
        yield new TextDeltaPart('b');
    })());
    $iterator = $stream->getIterator();
    $iterator->current();
    expect(fn() => $stream->run())->toThrow(LogicException::class);
    unset($iterator);
    expect($stream->run()->text)->toBe('ab');
});

it('allows snapshots during incomplete tool arguments but rejects malformed final arguments', function () {
    $stream = new Stream((function () {
        yield new \AiSdk\Streaming\ToolCallStartPart(0, 'call', 'tool');
        yield new \AiSdk\Streaming\ToolCallDeltaPart(0, '{"value":');
    })());
    foreach ($stream as $part) {
        if ($part instanceof \AiSdk\Streaming\ToolCallDeltaPart) {
            expect($stream->snapshot()->toolCalls)->toBe([]);
            break;
        }
    }
    expect(fn() => $stream->run())->toThrow(\AiSdk\Exceptions\JsonException::class);
});
