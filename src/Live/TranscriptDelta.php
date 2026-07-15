<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class TranscriptDelta extends LiveEvent
{
    public function __construct(
        public string $delta,
        public ?string $itemId = null,
        public TranscriptSource $source = TranscriptSource::Unknown,
    ) {
        parent::__construct('transcript.delta');
    }
}
