<?php

declare(strict_types=1);

namespace AiSdk\Testing\Fakes;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\TranscriptionModelInterface;
use AiSdk\Requests\TranscriptionRequest;
use AiSdk\Responses\TranscriptionResponse;
use AiSdk\Results\TranscriptData;

final class FakeTranscriptionModel extends BaseModel implements TranscriptionModelInterface
{
    public ?TranscriptionRequest $lastRequest = null;

    public function __construct(private readonly TranscriptionResponse $response) {}

    public function provider(): string
    {
        return 'fake';
    }

    public function modelId(): string
    {
        return 'fake-transcription-1';
    }

    public function transcribe(TranscriptionRequest $request): TranscriptionResponse
    {
        $this->lastRequest = $request;

        return $this->response;
    }

    public static function transcription(string $text = 'Hello from audio.'): self
    {
        return new self(new TranscriptionResponse(new TranscriptData($text)));
    }
}
