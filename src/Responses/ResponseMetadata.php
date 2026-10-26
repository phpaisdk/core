<?php

declare(strict_types=1);

namespace AiSdk\Responses;

/** Portable response identity; unavailable provider fields remain null. */
final readonly class ResponseMetadata
{
    public function __construct(
        public ?string $id = null,
        public ?string $model = null,
        public ?string $rawFinishReason = null,
    ) {}
}
