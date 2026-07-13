<?php

declare(strict_types=1);

namespace AiSdk\Responses;

use AiSdk\Results\TranscriptData;
use AiSdk\Support\Usage;

final class TranscriptionResponse
{
    /**
     * @param  array<string, mixed>  $rawResponse
     * @param  array<string, mixed>  $providerMetadata
     */
    public function __construct(
        public readonly TranscriptData $transcript,
        public readonly Usage $usage = new Usage(),
        public readonly array $rawResponse = [],
        public readonly array $providerMetadata = [],
    ) {}
}
