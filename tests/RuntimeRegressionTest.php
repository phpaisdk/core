<?php

declare(strict_types=1);

use AiSdk\Exceptions\ProviderException;
use AiSdk\Exceptions\RateLimitException;
use AiSdk\FinishReason;
use AiSdk\Results\TextResult;
use AiSdk\Streaming\ErrorPart;
use AiSdk\Streaming\FinishPart;
use AiSdk\Streaming\Stream;
use AiSdk\Streaming\TextDeltaPart;
use AiSdk\Support\Sdk;
use AiSdk\Support\Usage;
use AiSdk\Testing\Fakes\FakeEmbeddingModel;
use AiSdk\Testing\Fakes\FakeTextModel;
use AiSdk\Utils\Http\HttpRunner;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;

it('returns a result after chunks have already been consumed', function () {
    $stream = new Stream((function () {
        yield new TextDeltaPart('Hello');
        yield new TextDeltaPart(' world');
        yield new FinishPart(FinishReason::Stop, Usage::empty());
    })());

    expect(iterator_to_array($stream->chunks()))->toBe(['Hello', ' world'])
        ->and($stream->run()->text)->toBe('Hello world')
        ->and(iterator_to_array($stream->chunks()))->toBe(['Hello', ' world']);
});

it('can resume a stream after a consumer stops iterating early', function () {
    $stream = new Stream((function (): Generator {
        yield new TextDeltaPart('one');
        yield new TextDeltaPart('two');
        yield new FinishPart(FinishReason::Stop, Usage::empty());
    })());

    foreach ($stream->chunks() as $chunk) {
        expect($chunk)->toBe('one');
        break;
    }

    expect($stream->run()->text)->toBe('onetwo');
});

it('reports exceptions thrown while consuming a stream', function () {
    $reported = null;
    $exception = new RuntimeException('stream failed');
    $stream = new Stream((function () use ($exception) {
        yield new TextDeltaPart('partial');

        throw $exception;
    })());

    try {
        $stream->onError(function (Throwable $error) use (&$reported): void {
            $reported = $error;
        })->run();
    } catch (RuntimeException $caught) {
        expect($caught)->toBe($exception);
    }

    expect($reported)->toBe($exception);
});

it('does not convert an error stream part into a successful stream', function () {
    $reported = 0;
    $finished = false;
    $exception = new RuntimeException('provider failed');
    $stream = new Stream((function () use ($exception) {
        yield new ErrorPart($exception);
    })());

    $stream
        ->onError(function () use (&$reported): void {
            $reported++;
        })
        ->onFinish(function () use (&$finished): void {
            $finished = true;
        });

    try {
        $stream->run();
    } catch (RuntimeException $caught) {
        expect($caught)->toBe($exception);
    }

    expect($reported)->toBe(1)
        ->and($finished)->toBeFalse();
});

it('keeps the TextResult positional constructor contract', function () {
    $result = new TextResult(
        'Done',
        null,
        null,
        [],
        [],
        FinishReason::Stop,
        Usage::empty(),
        ['provider' => 'raw'],
        ['example' => ['id' => 'request_1']],
    );

    expect($result->rawResponse)->toBe(['provider' => 'raw'])
        ->and($result->providerMetadata)->toBe(['example' => ['id' => 'request_1']])
        ->and($result->totalUsage)->toEqual(Usage::empty());
});

it('uses streaming mode with the default Guzzle transport', function () {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'text/event-stream'], "data: {\"choices\":[]}\n\n"),
    ]));
    $stack->push(Middleware::history($history));

    $factory = new Psr17Factory();
    $runner = HttpRunner::fromSdk(new Sdk(
        httpClient: new Client(['handler' => $stack]),
        requestFactory: $factory,
        streamFactory: $factory,
    ));

    iterator_to_array($runner->postStream('https://example.test/stream', []));

    expect($history[0]['options']['stream'] ?? null)->toBeTrue();
    expect($history[0]['options']['http_errors'] ?? null)->toBeFalse();
});

it('normalizes streamed HTTP failures like non-streaming failures', function () {
    $factory = new Psr17Factory();
    $runner = HttpRunner::fromSdk(new Sdk(
        httpClient: new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(429, ['x-request-id' => 'req_123', 'retry-after' => '12'], '{"error":{"message":"Slow down"}}'),
        ]))]),
        requestFactory: $factory,
        streamFactory: $factory,
    ));

    iterator_to_array($runner->postStream('https://example.test/stream', [], provider: 'example'));
})->throws(RateLimitException::class, 'Slow down');

it('rejects redirect responses as provider failures', function () {
    $factory = new Psr17Factory();
    $runner = HttpRunner::fromSdk(new Sdk(
        httpClient: new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(302, ['Location' => 'https://example.test/elsewhere']),
        ]))]),
        requestFactory: $factory,
        streamFactory: $factory,
    ));

    $runner->postJson('https://example.test/request', []);
})->throws(ProviderException::class);

it('supports provider discovery through json get requests', function () {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], '{"data":[]}'),
    ]));
    $stack->push(Middleware::history($history));
    $factory = new Psr17Factory();
    $runner = HttpRunner::fromSdk(new Sdk(
        httpClient: new Client(['handler' => $stack]),
        requestFactory: $factory,
        streamFactory: $factory,
    ));

    expect($runner->getJson('https://example.test/v1/models'))->toBe(['data' => []])
        ->and($history[0]['request']->getMethod())->toBe('GET');
});

it('ships consumer autoloadable testing fakes', function () {
    expect(class_exists(FakeTextModel::class))->toBeTrue()
        ->and(FakeTextModel::text('ok')->modelId())->toBe('fake-text-1')
        ->and(FakeTextModel::scripted([
            new \AiSdk\Responses\TextModelResponse([], FinishReason::Stop, Usage::empty()),
        ])->requests)->toBe([])
        ->and(class_exists(FakeEmbeddingModel::class))->toBeTrue()
        ->and(FakeEmbeddingModel::embedding()->modelId())->toBe('fake-embedding-1');
});
