<?php

declare(strict_types=1);

namespace AiSdk\Streaming;

final class ProviderMetadataPart extends StreamPart
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $provider,
        public readonly array $metadata,
    ) {}
}
