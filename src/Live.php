<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Live\PendingTranscribeRequest;
use AiSdk\Live\PendingTranslateRequest;
use AiSdk\Live\PendingVoiceRequest;

/** Stateful audio, transcription, and translation sessions. */
final class Live
{
    public static function voice(): PendingVoiceRequest
    {
        return new PendingVoiceRequest();
    }

    public static function transcribe(): PendingTranscribeRequest
    {
        return new PendingTranscribeRequest();
    }

    public static function translate(): PendingTranslateRequest
    {
        return new PendingTranslateRequest();
    }
}
