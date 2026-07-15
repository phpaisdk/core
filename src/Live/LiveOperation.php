<?php

declare(strict_types=1);

namespace AiSdk\Live;

enum LiveOperation: string
{
    case Voice = 'voice';
    case Transcribe = 'transcribe';
    case Translate = 'translate';
}
