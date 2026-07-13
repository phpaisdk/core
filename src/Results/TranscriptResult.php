<?php

declare(strict_types=1);

namespace AiSdk\Results;

use AiSdk\Support\Usage;

final class TranscriptResult extends Result
{
    /**
     * @param  array<string, mixed>  $rawResponse
     * @param  array<string, mixed>  $providerMetadata
     */
    public function __construct(
        public readonly TranscriptData $output,
        Usage $usage = new Usage(),
        public readonly array $rawResponse = [],
        array $providerMetadata = [],
    ) {
        parent::__construct($usage, $providerMetadata);
    }
}
