<?php

declare(strict_types=1);

namespace AiSdk\Testing\Fakes;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\VideoModelInterface;
use AiSdk\Requests\VideoRequest;
use AiSdk\Responses\VideoJob;
use AiSdk\Responses\VideoJobStatus;
use AiSdk\Results\VideoData;

final class FakeVideoModel extends BaseModel implements VideoModelInterface
{
    public ?VideoRequest $lastRequest = null;

    private int $polls = 0;

    public function __construct(private readonly string $id = 'fake-video', private readonly int $pollsUntilComplete = 1) {}

    public function provider(): string
    {
        return 'fake';
    }

    public function modelId(): string
    {
        return $this->id;
    }

    public function generate(VideoRequest $request): VideoJob
    {
        $this->lastRequest = $request;

        return new VideoJob('fake-job', $this->provider(), $this->id);
    }

    public function poll(VideoJob $job): VideoJob
    {
        $this->polls++;

        return $this->polls >= $this->pollsUntilComplete ? new VideoJob($job->id, $job->provider, $job->modelId, VideoJobStatus::Succeeded, new VideoData(url: 'https://example.com/video.mp4')) : new VideoJob($job->id, $job->provider, $job->modelId, VideoJobStatus::Running);
    }
}
