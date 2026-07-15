<?php

declare(strict_types=1);

namespace AiSdk\Live;

/** Identifies which side of a Live session produced a transcript. */
enum TranscriptSource: string
{
    case Input = 'input';
    case Output = 'output';
    case Unknown = 'unknown';
}
