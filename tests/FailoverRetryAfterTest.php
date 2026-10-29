<?php

declare(strict_types=1);

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Exceptions\InsufficientCreditsException;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\RateLimitException;
use AiSdk\Failover\Failover;
use AiSdk\Generate;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Streaming\TextDeltaPart;
use AiSdk\Tests\Fakes\FakeTextModel;

function retryAfterModel(Throwable $error, bool $alwaysFail, bool $partial = false): TextModelInterface
{
    return new class ($error, $alwaysFail, $partial) extends BaseModel implements TextModelInterface {
        public int $calls = 0;

        public function __construct(private Throwable $error, private bool $alwaysFail, private bool $partial) {}

        public function provider(): string
        {
            return 'retry';
        }
        public function modelId(): string
        {
            return 'opaque';
        }

        public function generate(TextModelRequest $request): TextModelResponse
        {
            $this->calls++;
            if ($this->alwaysFail || $this->calls === 1) {
                throw $this->error;
            }

            return FakeTextModel::text('primary')->generate($request);
        }

        public function stream(TextModelRequest $request): Generator
        {
            if ($this->partial) {
                yield new TextDeltaPart('partial');
            }
            yield new TextDeltaPart($this->generate($request)->text());
        }
    };
}

afterEach(fn() => Generate::reset());

it('retries once within the threshold before deciding whether to switch', function (bool $streaming, bool $alwaysFail, int $threshold, float $delay, int $calls, array $waits) {
    $primary = retryAfterModel(new RateLimitException('slow', ['retryAfter' => $delay]), $alwaysFail);
    $backup = FakeTextModel::scripted([FakeTextModel::text('backup')->generate(new TextModelRequest([]))], [[new TextDeltaPart('backup')]]);
    $slept = [];
    $events = [];
    $failover = Failover::of($primary, $backup)->retryAfterUpTo($threshold)
        ->sleepUsing(function (float $seconds) use (&$slept): void {
            $slept[] = $seconds;
        })
        ->onFailover(function ($event) use (&$events): void {
            $events[] = $event;
        });
    $request = Generate::text('hi')->model($failover);
    if ($streaming) {
        $stream = $request->stream();
        foreach ($stream as $part) {
        }
        $text = $stream->result()->text;
    } else {
        $text = $request->run()->text;
    }
    $success = ! $alwaysFail && $calls === 2;
    expect($text)->toBe($success ? 'primary' : 'backup')
        ->and($primary->calls)->toBe($calls)->and($slept)->toBe($waits)
        ->and($events)->toHaveCount($success ? 0 : 1);
})->with([false, true])->with([
    'retry succeeds' => [false, 5, 2.0, 2, [2.0]],
    'retry fails' => [true, 5, 2.0, 2, [2.0]],
    'threshold exceeded' => [true, 5, 6.0, 1, []],
    'disabled' => [true, 0, 2.0, 1, []],
    'fractional seconds' => [false, 5, 0.25, 2, [0.25]],
    'zero delay' => [true, 5, 0.0, 1, []],
    'negative delay' => [true, 5, -1.0, 1, []],
    'inclusive threshold' => [false, 5, 5.0, 2, [5.0]],
]);

it('never retries or switches after the first stream part', function () {
    $primary = retryAfterModel(new RateLimitException('slow', ['retryAfter' => 1]), true, true);
    $slept = [];
    $model = Failover::of($primary, FakeTextModel::text('backup'))->sleepUsing(function ($seconds) use (&$slept): void {
        $slept[] = $seconds;
    });
    expect(function () use ($model): void {
        foreach (Generate::text('hi')->model($model)->stream() as $part) {
        }
    })->toThrow(RateLimitException::class);
    expect($slept)->toBe([])->and($primary->calls)->toBe(1);
});

it('does not retry non-retryable billing failures on the same account', function () {
    $primary = retryAfterModel(new InsufficientCreditsException('empty', ['retryAfter' => 1, 'retryable' => false]), true);
    $model = Failover::of($primary, FakeTextModel::text('backup'))->sleepUsing(function (): void {
        throw new RuntimeException('must not sleep');
    });
    expect(Generate::text('hi')->model($model)->run()->text)->toBe('backup')->and($primary->calls)->toBe(1);
});

it('rejects a negative retry threshold', function () {
    expect(fn() => Failover::of(FakeTextModel::text('a'), FakeTextModel::text('b'))->retryAfterUpTo(-1))->toThrow(InvalidArgumentException::class);
});
