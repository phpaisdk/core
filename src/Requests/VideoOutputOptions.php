<?php

declare(strict_types=1);

namespace AiSdk\Requests;

final class VideoOutputOptions
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly ?string $aspectRatio = null,
        public readonly ?string $resolution = null,
        public readonly ?float $duration = null,
        public readonly ?int $fps = null,
        public readonly ?int $seed = null,
        public readonly array $extra = [],
    ) {}
}
