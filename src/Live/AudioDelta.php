<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class AudioDelta extends LiveEvent
{
    public function __construct(public string $bytes)
    {
        parent::__construct('audio.delta');
    }
}
