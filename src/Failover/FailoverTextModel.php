<?php

declare(strict_types=1);

namespace AiSdk\Failover;

use AiSdk\Contracts\TextModelInterface;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Responses\TextModelResponse;
use Generator;

/**
 * @internal Created by Failover::resolve().
 */
final readonly class FailoverTextModel implements TextModelInterface
{
    /**
     * @param  FailoverRunner<TextModelInterface>  $runner
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

    public function generate(TextModelRequest $request): TextModelResponse
    {
        return $this->runner->run('text', static fn(TextModelInterface $model): TextModelResponse => $model->generate($request));
    }

    public function stream(TextModelRequest $request): Generator
    {
        return $this->runner->stream('text.stream', static fn(TextModelInterface $model): Generator => $model->stream($request));
    }
}
