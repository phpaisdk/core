<?php

declare(strict_types=1);

namespace AiSdk\Testing\Fakes;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\EmbeddingModelInterface;
use AiSdk\Requests\EmbeddingRequest;
use AiSdk\Responses\EmbeddingResponse;
use AiSdk\Results\EmbeddingData;

final class FakeEmbeddingModel extends BaseModel implements EmbeddingModelInterface
{
    use FailsModelOperations;

    public ?EmbeddingRequest $lastRequest = null;

    public function __construct(private readonly EmbeddingResponse $response) {}

    public function provider(): string
    {
        return 'fake';
    }

    public function modelId(): string
    {
        return 'fake-embedding-1';
    }

    public function generate(EmbeddingRequest $request): EmbeddingResponse
    {
        $this->failIfRequested();
        $this->lastRequest = $request;

        return $this->response;
    }

    /**
     * @param  array<int, float>  $vector
     */
    public static function embedding(array $vector = [0.1, 0.2, 0.3]): self
    {
        return new self(new EmbeddingResponse([
            new EmbeddingData($vector, 0),
        ]));
    }
}
