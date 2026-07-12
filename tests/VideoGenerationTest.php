<?php

declare(strict_types=1);
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Generate;
use AiSdk\Responses\VideoJob;
use AiSdk\Results\VideoResult;
use AiSdk\Testing\Fakes\FakeVideoModel;

afterEach(fn() => Generate::reset());
it('generates and waits for video jobs', function () {
    $model = new FakeVideoModel(pollsUntilComplete: 2);
    $result = Generate::video('A PHP logo')->model($model)->duration(5)->aspectRatio('16:9')->run(timeout: 1, pollIntervalMs: 0);
    expect($result)->toBeInstanceOf(VideoResult::class)->and($result->output->url)->toBe('https://example.com/video.mp4')->and($model->lastRequest?->output?->duration)->toBe(5.0);
});
it('starts video jobs without waiting', function () {
    $job = Generate::videoJob('A PHP logo')->model(new FakeVideoModel())->async();
    expect($job)->toBeInstanceOf(VideoJob::class)->and($job->id)->toBe('fake-job');
});
it('requires a video prompt', function () {
    Generate::video()->model(new FakeVideoModel())->run();
})->throws(InvalidArgumentException::class);
it('requires a video model', function () {
    Generate::video('A PHP logo')->run();
})->throws(InvalidArgumentException::class);
