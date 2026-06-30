<?php

declare(strict_types=1);

namespace AiSdk\Streaming;

final class TextDeltaPart extends StreamPart
{
    public function __construct(public readonly string $text) {}
}
