<?php

declare(strict_types=1);

use AiSdk\Exceptions\InsufficientCreditsException;
use AiSdk\Exceptions\RateLimitException;
use AiSdk\Failover\Failover;
use AiSdk\Generate;
use AiSdk\Tests\Fakes\FakeTextModel;
use AiSdk\Tests\Fakes\ThrowingTextModel;
use AiSdk\Utils\Errors\HttpErrorNormalizer;

it('recognizes billing failures in HTTP responses and stream errors', function (array $body) {
    $http = HttpErrorNormalizer::normalize('provider', 429, ['error' => $body], 'request', 'model', retryAfter: 2);
    $stream = HttpErrorNormalizer::fromStreamError('provider', $body);
    foreach ([$http, $stream] as $error) {
        expect($error)->toBeInstanceOf(InsufficientCreditsException::class)
            ->and($error->context()['retryable'])->toBeFalse();
    }
    expect($http->requestId())->toBe('request')->and($http->retryAfter())->toBe(2);
})->with([
    [['code' => 'insufficient_quota']],
    [['type' => 'insufficient_credits']],
    [['code' => 'billing_hard_limit_reached']],
    [['message' => 'Your credit balance is too low']],
    [['message' => 'You exceeded your current quota']],
    [['message' => 'Out of credits']],
    [['message' => 'Payment required']],
]);

it('recognizes HTTP 402 and stream status 402 without a billing message', function () {
    expect(HttpErrorNormalizer::normalize('provider', 402, null))->toBeInstanceOf(InsufficientCreditsException::class)
        ->and(HttpErrorNormalizer::fromStreamError('provider', ['status' => 402]))->toBeInstanceOf(InsufficientCreditsException::class);
});

it('keeps ordinary rate limits distinct from exhausted credits', function () {
    expect(HttpErrorNormalizer::normalize('provider', 429, ['message' => 'Too many requests']))->toBeInstanceOf(RateLimitException::class)
        ->and(HttpErrorNormalizer::fromStreamError('provider', ['type' => 'rate_limit_error']))->toBeInstanceOf(RateLimitException::class);
});

it('fails over when credits are exhausted without marking them retryable', function () {
    $error = HttpErrorNormalizer::normalize('provider', 402, ['message' => 'out of credits']);
    $primary = new ThrowingTextModel($error);
    expect(Generate::text('hi')->model(Failover::of($primary, FakeTextModel::text('backup')))->run()->text)->toBe('backup');
    expect($primary->calls)->toBe(1);
    Generate::reset();
});
