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
    /** @var array<int, TextModelResponse> */
    private array $responses;

    /** @var array<int, array<int, StreamPart>> */
    private array $streamSequences;

    /** @var array<int, TextModelRequest> */
    public array $requests = [];

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
        return 'fake-1';
    }

    public function generate(TextModelRequest $request): TextModelResponse
    {
        $this->requests[] = $request;

        return $this->responses[min(count($this->requests) - 1, count($this->responses) - 1)];
    }

    public function stream(TextModelRequest $request): Generator
    {
        $this->requests[] = $request;
        $sequence = $this->streamSequences[min(count($this->requests) - 1, count($this->streamSequences) - 1)];

        yield from $sequence;
    }

    public static function text(string $text): self
    {
        return new self(new TextModelResponse(
            parts: [new \AiSdk\Responses\Parts\TextPart($text)],
            finishReason: FinishReason::Stop,
            usage: new Usage(10, 5),
        ));
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
