<?php

declare(strict_types=1);

namespace AiSdk\Results;

use AiSdk\Decisions\Answer;
use AiSdk\Support\Usage;

final readonly class DecisionResult
{
    /**
     * @param array<string, Answer> $answers
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(public array $answers, public Usage $usage, public array $rawResponse = []) {}

    public function answer(string $name): Answer
    {
        return $this->answers[$name] ?? throw new \OutOfBoundsException("No decision answer named [{$name}].");
    }
}
