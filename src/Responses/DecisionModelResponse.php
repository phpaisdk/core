<?php

declare(strict_types=1);

namespace AiSdk\Responses;

use AiSdk\Decisions\Answer;
use AiSdk\Support\Usage;

final readonly class DecisionModelResponse
{
    /**
     * @param array<string, Answer> $answers
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(public array $answers, public Usage $usage = new Usage(), public array $rawResponse = []) {}
}
