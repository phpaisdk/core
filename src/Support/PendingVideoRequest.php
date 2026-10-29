<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Content;
use AiSdk\Contracts\Model;
use AiSdk\Contracts\VideoModelInterface;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\TimeoutException;
use AiSdk\Exceptions\VideoException;
use AiSdk\Requests\VideoOutputOptions;
use AiSdk\Requests\VideoRequest;
use AiSdk\Responses\VideoJob;
use AiSdk\Responses\VideoJobStatus;
use AiSdk\Results\VideoResult;

final class PendingVideoRequest
{
    use \AiSdk\Support\Concerns\ObservesModelOperations;

    private ?string $prompt = null;

    private ?Content $image = null;

    private ?Content $video = null;

    private ?string $aspectRatio = null;

    private ?string $resolution = null;

    private ?float $duration = null;

    private ?int $fps = null;

    private ?int $seed = null;

    /** @var array<string, mixed> */
    private array $extra = [];

    /** @var array<string, array<string, mixed>> */
    private array $providerOptions = [];

    public function __construct(private ?Model $model = null) {}

    public function model(Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function prompt(string $prompt): self
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function image(Content $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function video(Content $video): self
    {
        $this->video = $video;

        return $this;
    }

    public function aspectRatio(string $aspectRatio): self
    {
        if (! preg_match('/^\d+:\d+$/', $aspectRatio)) {
            throw new InvalidArgumentException('Video aspect ratio must use the format {width}:{height}.');
        }
        $this->aspectRatio = $aspectRatio;

        return $this;
    }

    public function resolution(string $resolution): self
    {
        $this->resolution = $resolution;

        return $this;
    }

    public function duration(float $seconds): self
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException('Video duration must be greater than zero.');
        } $this->duration = $seconds;

        return $this;
    }

    public function fps(int $fps): self
    {
        if ($fps < 1) {
            throw new InvalidArgumentException('Video FPS must be at least 1.');
        } $this->fps = $fps;

        return $this;
    }

    public function seed(int $seed): self
    {
        $this->seed = $seed;

        return $this;
    }

    /** @param array<string, mixed> $extra */
    public function outputOptions(array $extra): self
    {
        $this->extra = array_replace_recursive($this->extra, $extra);

        return $this;
    }

    /** @param array<string, mixed> $options */
    public function providerOptions(string $provider, array $options): self
    {
        $this->providerOptions[$provider] = array_replace_recursive($this->providerOptions[$provider] ?? [], $options);

        return $this;
    }

    /** @param VideoOutputOptions|array<string, mixed> $options */
    public function output(VideoOutputOptions|array $options): self
    {
        if ($options instanceof VideoOutputOptions) {
            $this->aspectRatio = $options->aspectRatio;
            $this->resolution = $options->resolution;
            $this->duration = $options->duration;
            $this->fps = $options->fps;
            $this->seed = $options->seed;
            $this->extra = $options->extra;
        } else {
            $this->aspectRatio = isset($options['aspectRatio']) ? (string) $options['aspectRatio'] : $this->aspectRatio;
            $this->resolution = isset($options['resolution']) ? (string) $options['resolution'] : $this->resolution;
            $this->duration = isset($options['duration']) ? (float) $options['duration'] : $this->duration;
            $this->fps = isset($options['fps']) ? (int) $options['fps'] : $this->fps;
            $this->seed = isset($options['seed']) ? (int) $options['seed'] : $this->seed;
            $this->extra = is_array($options['extra'] ?? null) ? $options['extra'] : $this->extra;
        }

        return $this;
    }

    public function run(int $timeout = 300, int $pollIntervalMs = 1000): VideoResult
    {
        return $this->wait($timeout, $pollIntervalMs);
    }

    public function wait(int $timeout = 300, int $pollIntervalMs = 1000): VideoResult
    {
        if ($timeout < 1 || $pollIntervalMs < 0) {
            throw new InvalidArgumentException('Video wait timeout must be positive and poll interval cannot be negative.');
        }
        $model = $this->resolvedModel();
        $job = $this->observeOperation($model, 'video.generate', fn() => $model->generate($this->request()));
        $started = hrtime(true);
        while (! in_array($job->status, [VideoJobStatus::Succeeded, VideoJobStatus::Failed, VideoJobStatus::Canceled, VideoJobStatus::Expired], true)) {
            if ((hrtime(true) - $started) / 1_000_000_000 >= $timeout) {
                throw new TimeoutException('Video generation timed out.', ['jobId' => $job->id, 'provider' => $job->provider, 'modelId' => $job->modelId, 'timeout' => $timeout, 'pollIntervalMs' => $pollIntervalMs]);
            }
            if ($pollIntervalMs > 0) {
                usleep($pollIntervalMs * 1000);
            }
            $job = $this->observeOperation($model, 'video.poll', fn() => $model->poll($job));
        }
        if ($job->status !== VideoJobStatus::Succeeded || $job->result === null) {
            throw new VideoException($job->errorMessage ?? "Video job [{$job->id}] ended with status [{$job->status->value}].", ['jobId' => $job->id, 'provider' => $job->provider, 'modelId' => $job->modelId, 'status' => $job->status->value, 'metadata' => $job->providerMetadata]);
        }

        return new VideoResult($job->result, $job->usage, $job->rawResponse, $job->providerMetadata);
    }

    public function job(): VideoJob
    {
        $model = $this->resolvedModel();

        return $this->observeOperation($model, 'video.generate', fn() => $model->generate($this->request()));
    }

    public function async(): VideoJob
    {
        return $this->job();
    }

    private function resolvedModel(): VideoModelInterface
    {
        return ModelResolver::resolve($this->model, VideoModelInterface::class, 'Generate::video()');
    }

    private function request(): VideoRequest
    {
        $prompt = trim((string) $this->prompt);
        if ($prompt === '') {
            throw new InvalidArgumentException('Generate::video() requires a prompt.');
        }

        return new VideoRequest(
            prompt: $prompt,
            image: $this->image,
            video: $this->video,
            output: new VideoOutputOptions($this->aspectRatio, $this->resolution, $this->duration, $this->fps, $this->seed, $this->extra),
            providerOptions: $this->providerOptions,
        );
    }
}
