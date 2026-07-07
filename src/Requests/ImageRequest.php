<?php

declare(strict_types=1);

namespace AiSdk\Requests;

final class ImageRequest
{
    /**
     * @param  array<string, array<string, mixed>>  $providerOptions
     */
    public function __construct(
        public readonly string $prompt,
        public readonly int $count = 1,
        public readonly ?string $size = null,
        public readonly ?string $aspectRatio = null,
        public readonly ?int $seed = null,
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
