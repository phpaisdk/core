<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\Requests\TextModelRequest;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Streaming\StreamPart;
use Generator;

interface TextModelInterface extends Model
{
    public function generate(TextModelRequest $request): TextModelResponse;

    /**
     * @return Generator<int, StreamPart>
     */
    public function stream(TextModelRequest $request): Generator;
}
