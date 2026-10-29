<?php

declare(strict_types=1);

namespace AiSdk\Observability;

use DateTimeImmutable;

/** Immutable snapshot. A paused run retains its identity and original start time on resume. */
final readonly class RunContext
{
    public function __construct(
        public string $invocationId,
        public ?string $parentInvocationId,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt = null,
    ) {}

    public static function start(?self $parent = null): self
    {
        return new self(bin2hex(random_bytes(16)), $parent?->invocationId, new DateTimeImmutable());
    }

    public function finished(): self
    {
        return new self($this->invocationId, $this->parentInvocationId, $this->startedAt, new DateTimeImmutable());
    }
}
