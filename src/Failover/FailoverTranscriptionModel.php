<?php

declare(strict_types=1);

namespace AiSdk\Failover;

use AiSdk\Contracts\TranscriptionModelInterface;
use AiSdk\Requests\TranscriptionRequest;
use AiSdk\Responses\TranscriptionResponse;

/**
 * @internal Created by Failover::resolve().
 */
final readonly class FailoverTranscriptionModel implements TranscriptionModelInterface
{
    /**
     * @param  FailoverRunner<TranscriptionModelInterface>  $runner
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

    public function transcribe(TranscriptionRequest $request): TranscriptionResponse
    {
        return $this->runner->run('transcription', static fn(TranscriptionModelInterface $model): TranscriptionResponse => $model->transcribe($request));
    }
}
