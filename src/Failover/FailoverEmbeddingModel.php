<?php

declare(strict_types=1);

namespace AiSdk\Failover;

use AiSdk\Contracts\EmbeddingModelInterface;
use AiSdk\Requests\EmbeddingRequest;
use AiSdk\Responses\EmbeddingResponse;

/**
 * @internal Created by Failover::resolve().
 */
final readonly class FailoverEmbeddingModel implements EmbeddingModelInterface
{
    /**
     * @param  FailoverRunner<EmbeddingModelInterface>  $runner
     */
    public function __construct(private FailoverRunner $runner) {}

    public function specificationVersion(): string
    {
        return 'v1';
    }

    public function provider(): string
    {
        return 'failover';
    }

    public function modelId(): string
    {
        return 'failover';
    }

    public function generate(EmbeddingRequest $request): EmbeddingResponse
    {
        return $this->runner->run('embedding', static fn(EmbeddingModelInterface $model): EmbeddingResponse => $model->generate($request));
    }
}
