<?php

declare(strict_types=1);

namespace AiSdk\Testing\Fakes;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\DecisionModelInterface;
use AiSdk\Requests\DecisionModelRequest;
use AiSdk\Responses\DecisionModelResponse;

final class FakeDecisionModel extends BaseModel implements DecisionModelInterface
{
    /** @var list<DecisionModelRequest> */
    public array $requests = [];
    public function __construct(private readonly DecisionModelResponse $response) {}
    public function provider(): string
    {
        return 'fake';
    }
    public function modelId(): string
    {
        return 'fake-decision';
    }
    public function decide(DecisionModelRequest $request): DecisionModelResponse
    {
        $this->requests[] = $request;

        return $this->response;
    }
}
