<?php

declare(strict_types=1);

namespace AiSdk\Agents;

/** Application-owned durable storage. Implement consume atomically to prevent replay. */
interface CheckpointStore
{
    public function put(string $id, AgentCheckpoint $checkpoint): void;

    /** Return and delete the checkpoint atomically, or null if absent/expired. */
    public function consume(string $id): ?AgentCheckpoint;
}
