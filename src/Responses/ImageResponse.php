<?php

declare(strict_types=1);

namespace AiSdk\Responses;

use AiSdk\Results\ImageData;
use AiSdk\Support\Usage;

final class ImageResponse
{
    /**
     * @param  array<int, ImageData>  $images
     * @param  array<string, mixed>  $rawResponse
     * @param  array<string, mixed>  $providerMetadata
     */
    public function __construct(
        public readonly array $images,
        public readonly Usage $usage = new Usage(),
        public readonly array $rawResponse = [],
        public readonly array $providerMetadata = [],
    ) {}

    public function first(): ?ImageData
    {
        return $this->images[0] ?? null;
    }
}
