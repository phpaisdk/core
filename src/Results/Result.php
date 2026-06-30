<?php

declare(strict_types=1);

namespace AiSdk\Results;

use AiSdk\Support\Usage;

abstract class Result
{
    /**
     * @param  array<string, mixed>  $providerMetadata
     */
    public function __construct(
        public readonly Usage $usage,
        public readonly array $providerMetadata = [],
    ) {}
}
