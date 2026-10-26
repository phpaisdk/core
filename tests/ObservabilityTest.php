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
