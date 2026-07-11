<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

interface ImageProviderInterface extends ProviderInterface
{
    public function imageModel(string $modelId): ImageModelInterface;
}
