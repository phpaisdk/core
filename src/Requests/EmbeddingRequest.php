<?php

declare(strict_types=1);

namespace AiSdk\Requests;

final class EmbeddingRequest
{
    /**
     * @param  array<int, string>  $inputs
     * @param  array<string, array<string, mixed>>  $providerOptions
     */
    public function __construct(
        public readonly array $inputs,
        public readonly ?int $dimensions = null,
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
