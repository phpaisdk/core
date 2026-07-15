<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class SpeechStarted extends LiveEvent
{
    public function __construct(public ?int $audioStartMs = null)
    {
        parent::__construct('speech.started');
    }
}
