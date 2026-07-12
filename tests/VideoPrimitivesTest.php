<?php

declare(strict_types=1);

use AiSdk\Capability;
use AiSdk\Content;
use AiSdk\Exceptions\AiSdkException;
use AiSdk\Exceptions\VideoDataException;
use AiSdk\Exceptions\VideoException;
use AiSdk\Requests\VideoOutputOptions;
use AiSdk\Requests\VideoRequest;
use AiSdk\Responses\VideoJob;
use AiSdk\Responses\VideoJobStatus;
use AiSdk\Results\VideoData;
use AiSdk\Results\VideoResult;
use AiSdk\Support\Usage;

it('round trips video capability names', function () {
    $expected = [
        'VideoGeneration' => 'video_generation',
        'VideoInput' => 'video_input',
    ];

    foreach ($expected as $case => $name) {
        $capability = constant(Capability::class . '::' . $case);

        expect($capability->name())->toBe($name)
            ->and(Capability::fromName($name))->toBe($capability);
    }
});

it('round trips every video job status name and value', function () {
    $expected = [
        'Queued' => 'queued',
        'Running' => 'running',
        'Succeeded' => 'succeeded',
        'Failed' => 'failed',
        'Canceled' => 'canceled',
        'Expired' => 'expired',
    ];

    expect(VideoJobStatus::cases())->toHaveCount(count($expected));

    foreach ($expected as $name => $value) {
        $status = VideoJobStatus::from($value);

        expect($status->name)->toBe($name)
            ->and($status->value)->toBe($value);
    }
});

it('holds normalized video output options', function () {
    $options = new VideoOutputOptions(
        aspectRatio: '16:9',
        resolution: '1920x1080',
        duration: 5.5,
        fps: 24,
        seed: 42,
        extra: ['loop' => true],
    );

    expect($options->aspectRatio)->toBe('16:9')
        ->and($options->resolution)->toBe('1920x1080')
        ->and($options->duration)->toBe(5.5)
        ->and($options->fps)->toBe(24)
        ->and($options->seed)->toBe(42)
        ->and($options->extra)->toBe(['loop' => true]);
});

it('holds typed video request inputs and provider options', function () {
    $image = Content::image('image-bytes', mimeType: 'image/png');
    $video = Content::video('video-bytes', mimeType: 'video/mp4');
    $output = new VideoOutputOptions(duration: 8.0);
    $request = new VideoRequest(
        prompt: 'A cinematic SDK demo',
        image: $image,
        video: $video,
        output: $output,
        providerOptions: [
            'fake' => ['watermark' => false],
            'invalid' => 'not-an-array',
        ],
    );

    expect($request->prompt)->toBe('A cinematic SDK demo')
        ->and($request->image)->toBe($image)
        ->and($request->video)->toBe($video)
        ->and($request->output)->toBe($output)
        ->and($request->providerOptionsFor('fake'))->toBe(['watermark' => false])
        ->and($request->providerOptionsFor('missing'))->toBe([])
        ->and($request->providerOptionsFor('invalid'))->toBe([]);
});

it('describes video jobs immutably', function () {
    $data = new VideoData(url: 'https://example.com/result.mp4', duration: 4.0);
    $usage = new Usage(inputTokens: 2, outputTokens: 3);
    $job = new VideoJob(
        id: 'job-123',
        provider: 'fake',
        modelId: 'video-1',
        status: VideoJobStatus::Succeeded,
        result: $data,
        usage: $usage,
        rawResponse: ['status' => 'done'],
        providerMetadata: ['fake' => ['requestId' => 'req-123']],
    );

    expect($job->id)->toBe('job-123')
        ->and($job->provider)->toBe('fake')
        ->and($job->modelId)->toBe('video-1')
        ->and($job->status)->toBe(VideoJobStatus::Succeeded)
        ->and($job->result)->toBe($data)
        ->and($job->errorMessage)->toBeNull()
        ->and($job->usage)->toBe($usage)
        ->and($job->rawResponse)->toBe(['status' => 'done'])
        ->and($job->providerMetadata)->toBe(['fake' => ['requestId' => 'req-123']]);

    $reflection = new ReflectionClass(VideoJob::class);

    expect($reflection->isFinal())->toBeTrue();

    foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
        expect($property->isReadOnly())->toBeTrue();
    }
});

it('defaults video jobs to queued with empty telemetry', function () {
    $job = new VideoJob(id: 'job-queued', provider: 'fake', modelId: 'video-1');

    expect($job->status)->toBe(VideoJobStatus::Queued)
        ->and($job->result)->toBeNull()
        ->and($job->errorMessage)->toBeNull()
        ->and($job->usage->totalTokens)->toBe(0)
        ->and($job->rawResponse)->toBe([])
        ->and($job->providerMetadata)->toBe([]);
});

it('exposes video results with normalized telemetry', function () {
    $data = new VideoData(data: 'video-bytes', mimeType: 'video/webm', resolution: '1280x720');
    $usage = new Usage(inputTokens: 1, outputTokens: 2);
    $result = new VideoResult(
        output: $data,
        usage: $usage,
        rawResponse: ['id' => 'job-123'],
        providerMetadata: ['fake' => ['seed' => 42]],
    );

    expect($result->output)->toBe($data)
        ->and($result->usage)->toBe($usage)
        ->and($result->rawResponse)->toBe(['id' => 'job-123'])
        ->and($result->providerMetadata)->toBe(['fake' => ['seed' => 42]]);
});

it('writes raw video bytes to disk', function () {
    $path = sys_get_temp_dir() . '/aisdk-video-data-test-' . bin2hex(random_bytes(4)) . '.mp4';
    $video = new VideoData(data: "\x00\x00video-bytes");

    try {
        expect($video->save($path))->toBe($path)
            ->and(file_get_contents($path))->toBe("\x00\x00video-bytes");
    } finally {
        @unlink($path);
    }
});

it('rejects saving url-only video data with a typed exception', function () {
    $video = new VideoData(url: 'https://example.com/result.mp4');

    $video->save(sys_get_temp_dir() . '/must-not-be-written.mp4');
})->throws(VideoDataException::class, 'Video data has no binary payload to save; fetch the URL first.');

it('provides typed video sdk exceptions', function () {
    expect(is_subclass_of(VideoException::class, AiSdkException::class))->toBeTrue()
        ->and(is_subclass_of(VideoDataException::class, VideoException::class))->toBeTrue();
});
