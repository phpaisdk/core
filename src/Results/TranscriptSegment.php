<?php

declare(strict_types=1);

namespace AiSdk\Results;

final class TranscriptSegment
{
    public function __construct(
        public readonly string $text,
        public readonly float $start = 0.0,
        public readonly float $end = 0.0,
        public readonly ?string $speaker = null,
    ) {}
}
