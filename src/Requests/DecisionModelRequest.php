<?php

declare(strict_types=1);

namespace AiSdk\Requests;

use AiSdk\Content;
use AiSdk\Decisions\Question;

final readonly class DecisionModelRequest
{
    /**
     * @param string|array<string|int, mixed> $input Text or structured JSON evidence.
     * @param list<Question> $questions
     * @param list<Content> $images
     * @param array<string, mixed> $providerOptions
     */
    public function __construct(public string|array $input, public array $questions, public array $images = [], public array $providerOptions = []) {}
}
