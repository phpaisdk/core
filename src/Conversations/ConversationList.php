<?php

declare(strict_types=1);

namespace AiSdk\Conversations;

final readonly class ConversationList
{
    /** @param list<ConversationInfo> $conversations */
    public function __construct(public array $conversations, public ?string $nextCursor = null)
    {
        if (count($conversations) > 1000 || ! array_is_list($conversations)) {
            throw new \AiSdk\Exceptions\ConversationException('Conversation lists require at most 1000 ordered items.');
        }
        foreach ($conversations as $conversation) {
            if (! $conversation instanceof ConversationInfo) {
                throw new \AiSdk\Exceptions\ConversationException('Conversation lists require ConversationInfo values.');
            }
        }
    }
}
