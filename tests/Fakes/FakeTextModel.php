<?php

declare(strict_types=1);

namespace AiSdk\Tests\Fakes;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\FinishReason;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Streaming\StreamPart;
use AiSdk\Support\Usage;
use Generator;

final class FakeTextModel extends BaseModel implements TextModelInterface
{
    /**
     * @param  array<int, StreamPart>  $streamParts
     */
    public function __construct(
        private readonly TextModelResponse $response,
        private readonly array $streamParts = [],
    ) {}

    public function provider(): string
    {
        return 'fake';
    }

    public function modelId(): string
    {
        return 'fake-1';
    }

    public function generate(TextModelRequest $request): TextModelResponse
    {
        return $this->response;
    }

    public function stream(TextModelRequest $request): Generator
    {
        yield from $this->streamParts;
    }

    public static function text(string $text): self
    {
        return new self(new TextModelResponse(
            parts: [new \AiSdk\Responses\Parts\TextPart($text)],
            finishReason: FinishReason::Stop,
            usage: new Usage(10, 5),
        ));
    }

}
