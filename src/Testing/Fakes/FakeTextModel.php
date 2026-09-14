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

    /** @var array<int, TextModelRequest> */
    public array $requests = [];

    /** @var array<int, TextModelResponse> */
    private array $responses;

    /** @var array<int, array<int, StreamPart>> */
    private array $streamSequences;

    /**
     * @param  array<int, StreamPart>  $streamParts
     */
    public function __construct(
        TextModelResponse $response,
        array $streamParts = [],
    ) {
        $this->responses = [$response];
        $this->streamSequences = [$streamParts];
    }

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
        $this->requests[] = $request;

        return $this->responses[min(count($this->requests) - 1, count($this->responses) - 1)];
    }

    public function stream(TextModelRequest $request): Generator
    {
        $this->lastRequest = $request;
        $this->requests[] = $request;
        $sequence = $this->streamSequences[min(count($this->requests) - 1, count($this->streamSequences) - 1)];

        yield from $sequence;
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

    /**
     * @param array<int, TextModelResponse> $responses
     * @param array<int, array<int, StreamPart>> $streamSequences
     */
    public static function scripted(array $responses, array $streamSequences = []): self
    {
        if ($responses === []) {
            throw new \InvalidArgumentException('FakeTextModel::scripted() requires at least one response.');
        }

        $fake = new self($responses[0], $streamSequences[0] ?? []);
        $fake->responses = $responses;
        $fake->streamSequences = $streamSequences === [] ? [[]] : $streamSequences;

        return $fake;
    }
}
