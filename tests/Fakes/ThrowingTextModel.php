<?php

declare(strict_types=1);

namespace AiSdk\Tests\Fakes;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Streaming\StreamPart;
use Generator;
use Throwable;

/**
 * A text model that fails with a given error, optionally after streaming some parts.
 */
final class ThrowingTextModel extends BaseModel implements TextModelInterface
{
    public int $calls = 0;

    /**
     * @param  array<int, StreamPart>  $partsBeforeError
     */
    public function __construct(
        private readonly Throwable $error,
        private readonly string $providerName = 'broken',
        private readonly array $partsBeforeError = [],
    ) {}

    public function provider(): string
    {
        return $this->providerName;
    }

    public function modelId(): string
    {
        return 'm';
    }

    public function generate(TextModelRequest $request): TextModelResponse
    {
        $this->calls++;

        throw $this->error;
    }

    public function stream(TextModelRequest $request): Generator
    {
        $this->calls++;

        yield from $this->partsBeforeError;

        throw $this->error;
    }
}
