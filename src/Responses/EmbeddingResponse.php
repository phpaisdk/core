<?php

declare(strict_types=1);

namespace AiSdk\Responses;

use AiSdk\Results\EmbeddingData;
use AiSdk\Support\Usage;

final class EmbeddingResponse
{
    /**
     * @param  array<int, EmbeddingData>  $embeddings
     * @param  array<string, mixed>  $rawResponse
     * @param  array<string, mixed>  $providerMetadata
     */
    public function __construct(
        public readonly array $embeddings,
        public readonly Usage $usage = new Usage(),
        public readonly array $rawResponse = [],
        public readonly array $providerMetadata = [],
    ) {}

    public function first(): ?EmbeddingData
    {
        return $this->embeddings[0] ?? null;
    }
}
