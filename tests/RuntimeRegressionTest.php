<?php

declare(strict_types=1);

use AiSdk\Exceptions\ProviderException;
use AiSdk\FinishReason;
use AiSdk\Streaming\FinishPart;
use AiSdk\Streaming\Stream;
use AiSdk\Streaming\TextDeltaPart;
use AiSdk\Support\Sdk;
use AiSdk\Support\Usage;
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
});

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
        ->and(FakeTextModel::text('ok')->modelId())->toBe('fake-text-1');
});
