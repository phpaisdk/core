<?php

declare(strict_types=1);

namespace AiSdk\Observability;

use DateTimeImmutable;

/** Immutable snapshot. A paused run retains its identity and original start time on resume. */
final readonly class RunContext
{
    /** @param list<string> $agentLineage */
    public function __construct(
        public string $invocationId,
        public ?string $parentInvocationId,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt = null,
        public int $depth = 0,
        public int $maxDepth = 3,
        public array $agentLineage = [],
    ) {}

    public static function start(?self $parent = null, ?string $agentIdentity = null, int $maxDepth = 3): self
    {
        $limit = min($maxDepth, $parent->maxDepth ?? $maxDepth);
        $depth = $parent === null ? 0 : $parent->depth + 1;
        $lineage = $parent->agentLineage ?? [];
        if ($depth > $limit || ($agentIdentity !== null && in_array($agentIdentity, $lineage, true))) {
            throw new \AiSdk\Exceptions\AgentDepthException('Nested agent depth exceeded or an agent cycle was detected.');
        }
        if ($agentIdentity !== null) {
            $lineage[] = $agentIdentity;
        }

        return new self(bin2hex(random_bytes(16)), $parent?->invocationId, new DateTimeImmutable(), depth: $depth, maxDepth: $limit, agentLineage: $lineage);
    }

    public function finished(): self
    {
        return new self($this->invocationId, $this->parentInvocationId, $this->startedAt, new DateTimeImmutable(), $this->depth, $this->maxDepth, $this->agentLineage);
    }
}
