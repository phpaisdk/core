<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

interface VideoProviderInterface extends ProviderInterface
{
    public function videoModel(string $modelId): VideoModelInterface;
}
