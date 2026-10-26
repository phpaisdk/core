<?php

declare(strict_types=1);

namespace AiSdk\Failover;

use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Requests\ImageRequest;
use AiSdk\Responses\ImageResponse;

/**
 * @internal Created by Failover::resolve().
 */
final readonly class FailoverImageModel implements ImageModelInterface
{
    /**
     * @param  FailoverRunner<ImageModelInterface>  $runner
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

    public function generate(ImageRequest $request): ImageResponse
    {
        return $this->runner->run('image', static fn(ImageModelInterface $model): ImageResponse => $model->generate($request));
    }
}
