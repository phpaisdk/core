<?php

declare(strict_types=1);

namespace AiSdk\Failover;

use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Requests\SpeechRequest;
use AiSdk\Responses\SpeechResponse;

/**
 * @internal Created by Failover::resolve().
 */
final readonly class FailoverSpeechModel implements SpeechModelInterface
{
    /**
     * @param  FailoverRunner<SpeechModelInterface>  $runner
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

    public function generate(SpeechRequest $request): SpeechResponse
    {
        return $this->runner->run('speech', static fn(SpeechModelInterface $model): SpeechResponse => $model->generate($request));
    }
}
