<?php

declare(strict_types=1);

use AiSdk\FinishReason;
use AiSdk\Generate;
use AiSdk\Observability\ModelEvent;
use AiSdk\Responses\ResponseMetadata;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Support\Usage;
use AiSdk\Testing\Fakes\FakeTextModel;

it('observes each model call without prompt or output data', function (bool $streaming) {
    $events = [];
    $pending = Generate::text('private')->model(FakeTextModel::streaming('secret'))->observe(function (ModelEvent $event) use (&$events) {
        $events[] = $event;
    });
    $result = $streaming ? $pending->stream()->run() : $pending->run();
    expect(array_column($events, 'phase'))->toBe(['start', 'finish'])
        ->and($events[1]->streaming)->toBe($streaming)
        ->and(json_encode($events))->not->toContain('private', 'secret')
        ->and($result->text)->toBe('secret');
})->with([false, true]);

it('isolates diagnostic listener errors', function () {
    expect(Generate::text('hi')->model(FakeTextModel::text('ok'))->observe(fn() => throw new RuntimeException('observer failed'))->run()->text)->toBe('ok');
});

it('carries portable response identity into the public result', function () {
    $metadata = new ResponseMetadata('id', 'actual-model', 'future-reason');
    $model = new FakeTextModel(new TextModelResponse([], FinishReason::Unknown, Usage::empty(), response: $metadata));
    expect(Generate::text('hi')->model($model)->run()->response)->toBe($metadata);
});

it('observes failures in every non-text generation operation with the shared seam', function (string $capability) {
    $failure = new \AiSdk\Exceptions\InvalidResponseException('arbitrary-private-value');
    $pending = match ($capability) {
        'image' => Generate::image('hi')->model(\AiSdk\Testing\Fakes\FakeImageModel::image()->failWith($failure)),
        'speech' => Generate::speech('hi')->model(\AiSdk\Testing\Fakes\FakeSpeechModel::speech()->failWith($failure)),
        'transcription' => Generate::transcription(\AiSdk\Content::audio('data:audio/wav;base64,YQ=='))->model(\AiSdk\Testing\Fakes\FakeTranscriptionModel::transcription()->failWith($failure)),
        'embedding' => Generate::embedding('hi')->model(\AiSdk\Testing\Fakes\FakeEmbeddingModel::embedding()->failWith($failure)),
        'decision' => Generate::decision('hi')->questions(\AiSdk\Decisions\Question::predicate('q', 'yes?'))->model((new \AiSdk\Testing\Fakes\FakeDecisionModel(new \AiSdk\Responses\DecisionModelResponse([])))->failWith($failure)),
        'video.generate' => Generate::video('hi')->model((new \AiSdk\Testing\Fakes\FakeVideoModel())->failWith($failure)),
    };
    $events = [];
    $pending->observe(function (ModelEvent $event) use (&$events): void {
        $events[] = $event;
    });
    expect(fn() => $pending->run())->toThrow($failure::class);
    expect(array_column($events, 'phase'))->toBe(['start', 'error'])
        ->and($events[1]->operation)->toBe($capability)
        ->and($events[1]->errorType)->toBe($failure::class)
        ->and(json_encode($events))->not->toContain('arbitrary-private-value');
})->with(['image', 'speech', 'transcription', 'embedding', 'decision', 'video.generate']);

it('observes video polling failures and isolates observers for other capabilities', function () {
    $model = new \AiSdk\Testing\Fakes\FakeVideoModel();
    $events = [];
    $pending = Generate::video('hi')->model($model)->observe(function (ModelEvent $event) use ($model, &$events): void {
        $events[] = $event;
        if ($event->phase === 'finish') {
            $model->failWith(new \AiSdk\Exceptions\InvalidResponseException('private'));
        }
    });
    expect(fn() => $pending->wait(pollIntervalMs: 0))->toThrow(\AiSdk\Exceptions\InvalidResponseException::class);
    expect(array_column($events, 'phase'))->toBe(['start', 'finish', 'start', 'error'])
        ->and($events[3]->operation)->toBe('video.poll');
    expect(Generate::image('hi')->model(\AiSdk\Testing\Fakes\FakeImageModel::image())->observe(fn() => throw new RuntimeException('observer'))->run())
        ->toBeInstanceOf(\AiSdk\Results\ImageResult::class);
});
