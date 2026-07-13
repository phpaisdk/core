<?php

declare(strict_types=1);

namespace AiSdk\Results;

final class TranscriptData
{
    /**
     * @param  array<int, TranscriptSegment>  $segments
     */
    public function __construct(
        public readonly string $text,
        public readonly ?string $language = null,
        public readonly ?float $duration = null,
        public readonly array $segments = [],
    ) {}
}
