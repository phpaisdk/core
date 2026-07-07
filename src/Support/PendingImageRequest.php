<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Capability;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Requests\ImageRequest;
use AiSdk\Results\ImageData;
use AiSdk\Results\ImageResult;

final class PendingImageRequest
{
    private ?string $prompt = null;
    private int $count = 1;
    private ?string $size = null;
    private ?string $aspectRatio = null;
    private ?int $seed = null;

    /** @var array<string, array<string, mixed>> */
    private array $providerOptions = [];

    public function __construct(private ?ImageModelInterface $model = null) {}

    public function model(ImageModelInterface $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function prompt(string $prompt): self
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function count(int $count): self
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Image count must be at least 1.');
        }

        $this->count = $count;

        return $this;
    }

    public function size(string $size): self
    {
        if (! preg_match('/^\d+x\d+$/', $size)) {
            throw new InvalidArgumentException('Image size must use the format {width}x{height}.');
        }

        $this->size = $size;

        return $this;
    }

    public function aspectRatio(string $aspectRatio): self
    {
        if (! preg_match('/^\d+:\d+$/', $aspectRatio)) {
            throw new InvalidArgumentException('Image aspect ratio must use the format {width}:{height}.');
        }

        $this->aspectRatio = $aspectRatio;

        return $this;
    }

    public function seed(int $seed): self
    {
        $this->seed = $seed;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function providerOptions(string $provider, array $options): self
    {
        $this->providerOptions[$provider] = array_replace_recursive(
            $this->providerOptions[$provider] ?? [],
            $options,
        );

        return $this;
    }

    public function run(): ImageResult
    {
        $request = $this->request();

        $model = ModelResolver::resolve(
            $this->model,
            ImageModelInterface::class,
            'Generate::image()',
            [Capability::ImageGeneration],
        );

        $response = $model->generate($request);

        return new ImageResult(
            output: $response->first() ?? new ImageData(),
            images: $response->images,
            usage: $response->usage,
            rawResponse: $response->rawResponse,
            providerMetadata: $response->providerMetadata,
        );
    }

    private function request(): ImageRequest
    {
        $prompt = trim((string) $this->prompt);
        if ($prompt === '') {
            throw new InvalidArgumentException('Generate::image() requires a prompt.');
        }

        return new ImageRequest(
            prompt: $prompt,
            count: $this->count,
            size: $this->size,
            aspectRatio: $this->aspectRatio,
            seed: $this->seed,
            providerOptions: $this->providerOptions,
        );
    }
}
