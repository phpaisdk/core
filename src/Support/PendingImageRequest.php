<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Contracts\Model;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Requests\ImageRequest;
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

        $model = ModelResolver::resolve($this->model, ImageModelInterface::class, 'Generate::image()');

        $response = $model->generate($request);

        $output = $response->first();
        if ($output === null) {
            throw InvalidResponseException::forProvider(
                $model->provider(),
                sprintf('Provider [%s] returned no generated images.', $model->provider()),
                ['modelId' => $model->modelId(), 'body' => $response->rawResponse],
            );
        }

        return new ImageResult(
            output: $output,
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
