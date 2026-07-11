<?php

declare(strict_types=1);

namespace AiSdk\Results;

final class EmbeddingData
{
    public readonly int $dimensions;

    /**
     * @param  array<int, float>  $vector
     */
    public function __construct(
        public readonly array $vector,
        public readonly ?int $index = null,
    ) {
        $this->dimensions = count($vector);
    }
}
