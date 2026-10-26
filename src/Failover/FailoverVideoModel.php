<?php

declare(strict_types=1);

namespace AiSdk\Failover;

use AiSdk\Contracts\VideoModelInterface;
use AiSdk\Requests\VideoRequest;
use AiSdk\Responses\VideoJob;

/**
 * @internal Created by Failover::resolve().
 */
final readonly class FailoverVideoModel implements VideoModelInterface
{
    /**
     * @param  FailoverRunner<VideoModelInterface>  $runner
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

    public function generate(VideoRequest $request): VideoJob
    {
        return $this->runner->run('video', static fn(VideoModelInterface $model): VideoJob => $model->generate($request));
    }

    /**
     * A job can only be polled where it was created, so polling never fails over.
     */
    public function poll(VideoJob $job): VideoJob
    {
        $adapter = $this->runner->adapterFor($job->provider, $job->modelId);

        return $adapter instanceof VideoModelInterface ? $adapter->poll($job) : $job;
    }
}
