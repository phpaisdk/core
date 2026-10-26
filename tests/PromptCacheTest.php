<?php

declare(strict_types=1);

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Generate;
use AiSdk\PromptCache;
use AiSdk\Support\Usage;
use AiSdk\Tests\Fakes\FakeTextModel;

afterEach(fn() => Generate::reset());

it('sends no cache hint unless asked', function () {
    $model = FakeTextModel::text('ok');
    Generate::text('Hi')->model($model)->run();

    expect($model->requests[0]->cache)->toBeNull();
});

it('accepts true, a ttl string or a PromptCache', function () {
    $model = FakeTextModel::text('ok');

    Generate::text('Hi')->model($model)->cache()->run();
    Generate::text('Hi')->model($model)->cache('1h')->run();
    Generate::text('Hi')->model($model)->cache(PromptCache::key('tenant-1')->withTtl('5m'))->run();
    Generate::text('Hi')->model($model)->cache()->cache(false)->run();

    expect($model->requests[0]->cache)->toEqual(PromptCache::auto())
        ->and($model->requests[1]->cache->ttl)->toBe('1h')
        ->and($model->requests[2]->cache->key)->toBe('tenant-1')
        ->and($model->requests[2]->cache->ttl)->toBe('5m')
        ->and($model->requests[3]->cache)->toBeNull();
});

it('rejects unsupported lifetimes and empty keys', function () {
    expect(fn() => PromptCache::ttl('2h'))->toThrow(InvalidArgumentException::class)
        ->and(fn() => PromptCache::key(''))->toThrow(InvalidArgumentException::class);
});

it('adds cache write tokens across steps and reports a hit rate', function () {
    $total = (new Usage(1000, 10, cachedInputTokens: 800, cacheWriteInputTokens: 100))
        ->add(new Usage(500, 5, cachedInputTokens: 500));

    expect($total->inputTokens)->toBe(1500)
        ->and($total->cachedInputTokens)->toBe(1300)
        ->and($total->cacheWriteInputTokens)->toBe(100)
        ->and($total->cacheHitRate())->toEqualWithDelta(1300 / 1500, 0.0001)
        ->and((new Usage(10, 1))->cacheHitRate())->toBeNull();
});
