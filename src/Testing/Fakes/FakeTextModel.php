<?php

declare(strict_types=1);

namespace AiSdk\Testing\Fakes;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\FinishReason;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Responses\Parts\TextPart;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Streaming\FinishPart;
use AiSdk\Streaming\StreamPart;
use AiSdk\Streaming\TextDeltaPart;
use AiSdk\Support\Usage;
use Generator;

final class FakeTextModel extends BaseModel implements TextModelInterface
{
    public ?TextModelRequest $lastRequest = null;

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
        return 'fake-text-1';
    }

    public function generate(TextModelRequest $request): TextModelResponse
    {
        $this->lastRequest = $request;

        return $this->response;
    }

    public function stream(TextModelRequest $request): Generator
    {
        $this->lastRequest = $request;

        yield from $this->streamParts;
    }

    public static function text(string $text): self
    {
        return new self(new TextModelResponse(
            parts: [new TextPart($text)],
            finishReason: FinishReason::Stop,
            usage: Usage::empty(),
        ));
    }

    public static function streaming(string ...$chunks): self
    {
        $parts = [];
        foreach ($chunks as $chunk) {
            $parts[] = new TextDeltaPart($chunk);
        }
        $parts[] = new FinishPart(FinishReason::Stop, Usage::empty());

        return new self(
            response: new TextModelResponse(
                parts: [new TextPart(implode('', $chunks))],
                finishReason: FinishReason::Stop,
                usage: Usage::empty(),
            ),
            streamParts: $parts,
        );
    }
}
