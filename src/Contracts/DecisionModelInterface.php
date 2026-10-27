<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\Requests\DecisionModelRequest;
use AiSdk\Responses\DecisionModelResponse;

interface DecisionModelInterface extends Model
{
    public function decide(DecisionModelRequest $request): DecisionModelResponse;
}
