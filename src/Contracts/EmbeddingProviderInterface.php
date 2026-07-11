<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

interface EmbeddingProviderInterface extends ProviderInterface
{
    public function embeddingModel(string $modelId): EmbeddingModelInterface;
}
