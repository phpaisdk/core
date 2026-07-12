<?php

declare(strict_types=1);

namespace AiSdk\Responses;

use AiSdk\Results\VideoData;
use AiSdk\Support\Usage;

final class VideoJob
{
    /**
     * @param  array<string, mixed>  $rawResponse
     * @param  array<string, mixed>  $providerMetadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $provider,
        public readonly string $modelId,
        public readonly VideoJobStatus $status = VideoJobStatus::Queued,
        public readonly ?VideoData $result = null,
        public readonly ?string $errorMessage = null,
        public readonly Usage $usage = new Usage(),
        public readonly array $rawResponse = [],
        public readonly array $providerMetadata = [],
    ) {}
}
