<?php

declare(strict_types=1);

use AiSdk\Capability;
use AiSdk\Concentrate;
use AiSdk\Contracts\Model;
use AiSdk\Generate;
use AiSdk\OpenAI;
use AiSdk\OpenAICompatible\CompatibleChatModel;
use AiSdk\Schema;
use AiSdk\Support\Sdk;
use AiSdk\Tool;
use AiSdk\XAI;

/** These explicitly opted-in tests consume provider credits. Never run by default. */
beforeEach(function () {
    if (getenv('AISDK_LIVE_CONFORMANCE') !== '1') {
        $this->markTestSkipped('Set AISDK_LIVE_CONFORMANCE=1 and load the provider credentials to run billable tests.');
    }
});

afterEach(fn() => Generate::reset());

function liveConformanceModel(string $target): Model
{
    [$provider, $adapter] = explode(':', $target);
    $key = $_ENV[$provider === 'xai' ? 'XAI_API_KEY' : 'CONCENTRATE_API_KEY'] ?? '';
    if ($key === '') {
        throw new RuntimeException("Missing live credentials for {$provider}.");
    }
    $model = $provider === 'xai' ? 'grok-build-0.1' : 'ministral-3-3b';
    $url = $provider === 'xai' ? 'https://api.x.ai/v1' : 'https://api.concentrate.ai/v1';
    $factory = new GuzzleHttp\Psr7\HttpFactory();
    $sdk = new Sdk(new GuzzleHttp\Client(['timeout' => 45, 'connect_timeout' => 10]), $factory, $factory);
    Generate::configure($sdk);
    $config = ['apiKey' => $key, 'baseUrl' => $url, 'sdk' => $sdk];

    return match ($adapter) {
        'native' => ($provider === 'xai' ? XAI::create($config) : Concentrate::create($config))->model($model),
        'openai' => OpenAI::create($config)->model($model),
        'compatible' => new CompatibleChatModel($model, 'live-compatible', $url, ['Authorization' => 'Bearer ' . $key], $sdk, [Capability::TextGeneration, Capability::Streaming, Capability::ToolCalling, Capability::StructuredOutput, Capability::TextInput]),
        default => throw new InvalidArgumentException('Unknown conformance target.'),
    };
}

/** Prevent exception output from including credentials or request objects. */
function liveConformanceCall(callable $call): mixed
{
    try {
        return $call();
    } catch (Throwable $error) {
        $message = $error->getMessage();
        foreach (['XAI_API_KEY', 'CONCENTRATE_API_KEY'] as $name) {
            $key = $_ENV[$name] ?? '';
            if ($key !== '') {
                $message = str_replace($key, '[REDACTED]', $message);
            }
        }

        throw new RuntimeException($error::class . ': ' . $message);
    }
}

dataset('live text adapters', ['xai:native', 'xai:openai', 'xai:compatible', 'concentrate:native', 'concentrate:openai', 'concentrate:compatible']);

it('generates live text with usage and response metadata', function (string $target) {
    $result = liveConformanceCall(fn() => Generate::text('Reply with OK.')->model(liveConformanceModel($target))->maxTokens(24)->run());
    expect(trim($result->text))->not->toBe('')
        ->and($result->usage->inputTokens)->toBeGreaterThan(0)
        ->and($result->usage->outputTokens)->toBeGreaterThan(0)
        ->and($result->response->id)->not->toBeNull()
        ->and($result->response->rawFinishReason)->not->toBeNull();
})->with('live text adapters');

it('finalizes live streamed text and reports usage', function (string $target) {
    $stream = Generate::text('Reply with OK.')->model(liveConformanceModel($target))->maxTokens(24)->stream();
    $result = liveConformanceCall(fn() => $stream->run());
    expect(trim($result->text))->not->toBe('')
        ->and($stream->isComplete())->toBeTrue()
        ->and($stream->result())->toBe($result)
        ->and($result->usage->inputTokens)->toBeGreaterThan(0)
        ->and($result->response->id)->not->toBeNull();
})->with('live text adapters');

it('runs a live tool loop in synchronous and streaming modes', function (string $target, bool $streaming) {
    $executions = 0;
    $tool = Tool::fromCallable(function (int $a, int $b) use (&$executions): int {
        $executions++;

        return $a + $b;
    }, name: 'sum_numbers', description: 'Add two integers.');
    $pending = Generate::text('Call sum_numbers with a=2 and b=3 exactly once. After receiving the tool result reply with only that result.')->model(liveConformanceModel($target))->tools($tool)->maxSteps(2)->maxTokens(96);
    $result = liveConformanceCall(fn() => $streaming ? $pending->stream()->run() : $pending->run());
    expect($executions)->toBe(1)
        ->and($result->toolResults)->toHaveCount(1)
        ->and($result->toolResults[0]->output)->toBe(5)
        ->and($result->toolResults[0]->isError)->toBeFalse()
        ->and($result->text)->toContain('5')
        ->and($result->totalUsage->totalTokens)->toBeGreaterThan($result->usage->totalTokens);
})->with('live text adapters')->with([false, true]);

it('validates live structured output in synchronous and streaming modes', function (string $target, bool $streaming) {
    $schema = Schema::object('answer', properties: [Schema::integer('answer')->required()]);
    $pending = Generate::text('Return JSON with answer equal to 5.')->model(liveConformanceModel($target))->output($schema)->maxTokens(48);
    $result = liveConformanceCall(fn() => $streaming ? $pending->stream()->run() : $pending->run());
    expect($result->output)->toBe(['answer' => 5]);
})->with('live text adapters')->with([false, true]);
