<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class ResponseCompleted extends LiveEvent
{
    /** @param array<string, mixed> $response */
    public function __construct(public ?string $responseId = null, public array $response = [])
    {
        parent::__construct('response.completed');
    }
}
