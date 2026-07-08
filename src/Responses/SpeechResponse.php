<?php

declare(strict_types=1);

namespace AiSdk\Responses;

use AiSdk\Results\AudioData;
use AiSdk\Support\Usage;

final class SpeechResponse
{
    /**
     * @param  array<string, mixed>  $rawResponse
     * @param  array<string, mixed>  $providerMetadata
     */
    public function __construct(
        public readonly AudioData $audio,
        public readonly Usage $usage = new Usage(),
        public readonly array $rawResponse = [],
        public readonly array $providerMetadata = [],
    ) {}
}
