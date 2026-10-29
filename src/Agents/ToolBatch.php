<?php

declare(strict_types=1);

namespace AiSdk\Agents;

use AiSdk\Message;
use AiSdk\Support\Usage;
use AiSdk\ToolCall;

/** @internal */
final readonly class ToolBatch
{
    /**
     * @param list<Message> $messages
     * @param list<ToolCall> $pendingCalls
     */
    public function __construct(public array $messages, public Usage $usage = new Usage(), public ?ChildCheckpoint $child = null, public array $pendingCalls = []) {}
}
