<?php

declare(strict_types=1);

namespace AiSdk\Requests;

use AiSdk\Content;

final class TranscriptionRequest
{
    /**
     * @param  array<string, array<string, mixed>>  $providerOptions
     */
    public function __construct(
        public readonly Content $audio,
        public readonly array $providerOptions = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function providerOptionsFor(string $provider): array
    {
        $value = $this->providerOptions[$provider] ?? [];

        return is_array($value) ? $value : [];
    }
}
