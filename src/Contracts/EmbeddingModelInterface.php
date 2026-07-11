<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\Requests\EmbeddingRequest;
use AiSdk\Responses\EmbeddingResponse;

interface EmbeddingModelInterface extends Model
{
    public function generate(EmbeddingRequest $request): EmbeddingResponse;
}
