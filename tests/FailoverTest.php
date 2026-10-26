<?php

declare(strict_types=1);

use AiSdk\Contracts\Model;
use AiSdk\Exceptions\AuthenticationException;
use AiSdk\Exceptions\CapabilityNotSupportedException;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\NoSuchModelException;
use AiSdk\Exceptions\OverloadedException;
use AiSdk\Exceptions\RateLimitException;
use AiSdk\Failover\Failover;
use AiSdk\Failover\FailoverEvent;
use AiSdk\FinishReason;
use AiSdk\Generate;
use AiSdk\ProviderModel;
use AiSdk\Responses\Parts\TextPart;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Streaming\FinishPart;
use AiSdk\Streaming\TextDeltaPart;
use AiSdk\Support\Usage;
use AiSdk\Tests\Fakes\FakeImageModel;
use AiSdk\Tests\Fakes\FakeTextModel;
use AiSdk\Tests\Fakes\ThrowingTextModel;

afterEach(fn() => Generate::reset());

function streamingModel(string ...$chunks): FakeTextModel
{
    $parts = array_map(fn(string $chunk): TextDeltaPart => new TextDeltaPart($chunk), $chunks);
    $parts[] = new FinishPart(FinishReason::Stop, Usage::empty());

    return FakeTextModel::scripted(
        [new TextModelResponse([new TextPart(implode('', $chunks))], FinishReason::Stop, Usage::empty())],
        [$parts],
    );
}

it('moves to the next model when one is rate limited and reports the switch', function () {
    $primary = new ThrowingTextModel(new RateLimitException('slow down'), 'primary');
    $backup = FakeTextModel::text('from backup');
    $events = [];

    $result = Generate::text('Hi')
        ->model(Failover::of($primary, $backup)->onFailover(function (FailoverEvent $event) use (&$events): void {
            $events[] = $event;
        }))
        ->run();

    expect($result->text)->toBe('from backup')
        ->and($primary->calls)->toBe(1)
        ->and($events)->toHaveCount(1)
        ->and($events[0]->provider)->toBe('primary')
        ->and($events[0]->nextProvider)->toBe('fake')
        ->and($events[0]->exception)->toBeInstanceOf(RateLimitException::class);
});

it('does not fail over on errors another provider cannot fix', function () {
    $primary = new ThrowingTextModel(new AuthenticationException('bad key'));
    $backup = FakeTextModel::text('never used');

    expect(fn() => Generate::text('Hi')->model(Failover::of($primary, $backup))->run())
        ->toThrow(AuthenticationException::class);
    expect($backup->requests)->toBe([]);
});

it('rethrows the last error when every model fails', function () {
    $first = new ThrowingTextModel(new RateLimitException('one'), 'a');
    $second = new ThrowingTextModel(new OverloadedException('two'), 'b');

    expect(fn() => Generate::text('Hi')->model(Failover::of($first, $second))->run())
        ->toThrow(OverloadedException::class, 'two');
    expect($first->calls)->toBe(1)->and($second->calls)->toBe(1);
});

it('lets callers decide which errors fail over', function () {
    $primary = new ThrowingTextModel(new AuthenticationException('bad key'));
    $backup = FakeTextModel::text('rescued');

    $result = Generate::text('Hi')
        ->model(Failover::of($primary, $backup)->retryWhen(fn(Throwable $e): bool => true))
        ->run();

    expect($result->text)->toBe('rescued');
});

it('streams from the next model when the first fails before producing output', function () {
    $primary = new ThrowingTextModel(new OverloadedException('busy'));
    $backup = streamingModel('Hel', 'lo');

    $stream = Generate::text('Hi')->model(Failover::of($primary, $backup))->stream();
    $text = '';
    foreach ($stream as $part) {
        if ($part instanceof TextDeltaPart) {
            $text .= $part->text;
        }
    }

    expect($text)->toBe('Hello');
});

it('never switches models once a stream has started producing output', function () {
    $primary = new ThrowingTextModel(new OverloadedException('dropped'), 'primary', [new TextDeltaPart('partial')]);
    $backup = streamingModel('other');

    $stream = Generate::text('Hi')->model(Failover::of($primary, $backup))->stream();

    expect(function () use ($stream): void {
        foreach ($stream as $part) {
            // consume
        }
    })->toThrow(OverloadedException::class);
    expect($backup->requests)->toBe([]);
});

it('skips a model that just failed while its cooldown lasts', function () {
    $primary = new ThrowingTextModel(new RateLimitException('limited'));
    $backup = FakeTextModel::text('ok');
    $model = Failover::of($primary, $backup)->cooldown(60);

    Generate::text('one')->model($model)->run();
    Generate::text('two')->model($model)->run();

    expect($primary->calls)->toBe(1);
});

it('skips candidates that have no adapter for the requested capability', function () {
    $textOnly = new ProviderModel('textonly', 'm', function (string $contract): Model {
        throw NoSuchModelException::for('textonly', 'm', $contract);
    });
    $imageCapable = new ProviderModel('images', 'img', fn(string $contract): Model => FakeImageModel::image('aW1n'));

    $result = Generate::image('a cat')->model(Failover::of($textOnly, $imageCapable))->run();

    expect($result->output->base64)->toBe('aW1n');
});

it('requires at least two models', function () {
    expect(fn() => Failover::of(FakeTextModel::text('only')))->toThrow(InvalidArgumentException::class);
});

it('treats an unsupported capability as a reason to try the next model', function () {
    $unsupported = new ThrowingTextModel(CapabilityNotSupportedException::fromSupport(
        'broken',
        'm',
        AiSdk\CapabilitySupport::notSupported(AiSdk\Capability::ToolCalling, 'no tools'),
    ));
    $backup = FakeTextModel::text('has tools');

    expect(Generate::text('Hi')->model(Failover::of($unsupported, $backup))->run()->text)->toBe('has tools');
});
