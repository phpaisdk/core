<?php

declare(strict_types=1);

namespace AiSdk\Streaming;

use AiSdk\FinishReason;
use AiSdk\Support\Usage;

final class FinishPart extends StreamPart
{
    public function __construct(
        public readonly FinishReason $reason,
        public readonly Usage $usage,
    ) {}
}
