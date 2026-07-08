<?php

declare(strict_types=1);

namespace AiSdk\Requests;

final class SpeechRequest
{
    /**
     * @param  array<string, array<string, mixed>>  $providerOptions
     */
    public function __construct(
        public readonly string $input,
        public readonly ?string $voice = null,
        public readonly ?string $format = null,
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
