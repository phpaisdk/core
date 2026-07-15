<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class ClientSecret
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $value,
        public ?int $expiresAt = null,
        public ?string $sessionId = null,
        public array $raw = [],
    ) {}
}
