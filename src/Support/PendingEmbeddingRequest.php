<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Contracts\EmbeddingModelInterface;
use AiSdk\Contracts\Model;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Requests\EmbeddingRequest;
use AiSdk\Results\EmbeddingResult;

final class PendingEmbeddingRequest
{
    /** @var array<int, string>|null */
    private ?array $inputs = null;

    private ?int $dimensions = null;

    /** @var array<string, array<string, mixed>> */
    private array $providerOptions = [];

    public function __construct(private ?Model $model = null) {}

    public function model(Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @param  string|array<int, string>  $input
     */
    public function input(string|array $input): self
    {
        $values = is_array($input) ? $input : [$input];

        if ($values === [] || (is_array($input) && ! array_is_list($input))) {
            throw new InvalidArgumentException('Generate::embedding() input must be a string or a non-empty list of strings.');
        }

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException('Generate::embedding() input must contain only non-empty strings.');
            }
        }

        $this->inputs = array_values($values);

        return $this;
    }

    public function dimensions(int $dimensions): self
    {
        if ($dimensions < 1) {
            throw new InvalidArgumentException('Embedding dimensions must be at least 1.');
        }

        $this->dimensions = $dimensions;

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

    public function run(): EmbeddingResult
    {
        if ($this->inputs === null) {
            throw new InvalidArgumentException('Generate::embedding() requires input.');
        }

        $model = ModelResolver::resolve($this->model, EmbeddingModelInterface::class, 'Generate::embedding()');
        $response = $model->generate(new EmbeddingRequest(
            inputs: $this->inputs,
            dimensions: $this->dimensions,
            providerOptions: $this->providerOptions,
        ));

        $output = $response->first();
        if ($output === null) {
            throw InvalidResponseException::forProvider(
                $model->provider(),
                sprintf('Provider [%s] returned no embeddings.', $model->provider()),
                ['modelId' => $model->modelId(), 'body' => $response->rawResponse],
            );
        }

        return new EmbeddingResult(
            output: $output,
            embeddings: $response->embeddings,
            usage: $response->usage,
            rawResponse: $response->rawResponse,
            providerMetadata: $response->providerMetadata,
        );
    }
}
