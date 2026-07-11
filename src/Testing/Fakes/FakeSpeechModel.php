<?php

declare(strict_types=1);

namespace AiSdk\Testing\Fakes;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Requests\SpeechRequest;
use AiSdk\Responses\SpeechResponse;
use AiSdk\Results\AudioData;

final class FakeSpeechModel extends BaseModel implements SpeechModelInterface
{
    public ?SpeechRequest $lastRequest = null;

    public function __construct(private readonly SpeechResponse $response) {}

    public function provider(): string
    {
        return 'fake';
    }

    public function modelId(): string
    {
        return 'fake-speech-1';
    }

    public function generate(SpeechRequest $request): SpeechResponse
    {
        $this->lastRequest = $request;

        return $this->response;
    }

    public static function speech(string $data = 'audio-bytes', string $mimeType = 'audio/mpeg'): self
    {
        return new self(new SpeechResponse(new AudioData(data: $data, mimeType: $mimeType)));
    }
}
