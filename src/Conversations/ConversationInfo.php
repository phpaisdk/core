<?php

declare(strict_types=1);

namespace AiSdk\Conversations;

final readonly class ConversationInfo
{
    public function __construct(public string $id, public string $ownerKey, public int $messageCount = 0, public int $stepCount = 0)
    {
        RecordBounds::id($id);
        RecordBounds::id($ownerKey);
        if ($messageCount < 0 || $stepCount < 0) {
            throw new \AiSdk\Exceptions\ConversationException('Conversation record counts cannot be negative.');
        }
    }
}
