<?php

declare(strict_types=1);

namespace AiSdk\Requests;

use AiSdk\Content;

final class VideoRequest
{
    /**
     * @param  array<string, array<string, mixed>>  $providerOptions
     */
    public function __construct(
        public readonly string $prompt,
        public readonly ?Content $image = null,
        public readonly ?Content $video = null,
        public readonly ?VideoOutputOptions $output = null,
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
