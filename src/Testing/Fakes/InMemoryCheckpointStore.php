<?php

declare(strict_types=1);

namespace AiSdk\Testing\Fakes;

use AiSdk\Agents\AgentCheckpoint;
use AiSdk\Agents\CheckpointStore;

/** Process-local test storage. Use an application-owned atomic store for durable approvals. */
final class InMemoryCheckpointStore implements CheckpointStore
{
    /** @var array<string, AgentCheckpoint> */
    private array $items = [];

    public function put(string $id, AgentCheckpoint $checkpoint): void
    {
        $this->items[$id] = $checkpoint;
    }

    public function consume(string $id): ?AgentCheckpoint
    {
        $checkpoint = $this->items[$id] ?? null;
        unset($this->items[$id]);

        return $checkpoint;
    }
}
