<?php

declare(strict_types=1);

namespace AiSdk\Results;

use AiSdk\Support\Usage;

final class EmbeddingResult extends Result
{
    /**
     * @param  array<int, EmbeddingData>  $embeddings
     * @param  array<string, mixed>  $rawResponse
     * @param  array<string, mixed>  $providerMetadata
     */
    public function __construct(
        public readonly EmbeddingData $output,
        public readonly array $embeddings = [],
        Usage $usage = new Usage(),
        public readonly array $rawResponse = [],
        array $providerMetadata = [],
    ) {
        parent::__construct($usage, $providerMetadata);
    }
}
