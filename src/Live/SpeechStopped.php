<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class SpeechStopped extends LiveEvent
{
    public function __construct(public ?int $audioEndMs = null)
    {
        parent::__construct('speech.stopped');
    }
}
