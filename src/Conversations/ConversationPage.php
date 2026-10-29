<?php

declare(strict_types=1);

namespace AiSdk\Conversations;

final readonly class ConversationPage
{
    /** @param list<StoredMessage|StoredStep> $entries */
    public function __construct(public array $entries, public ?string $nextCursor = null)
    {
        if (count($entries) > 1000 || ! array_is_list($entries)) {
            throw new \AiSdk\Exceptions\ConversationException('Conversation pages require at most 1000 ordered entries.');
        }
        foreach ($entries as $entry) {
            if (! $entry instanceof StoredMessage && ! $entry instanceof StoredStep) {
                throw new \AiSdk\Exceptions\ConversationException('Conversation pages require stored message or step values.');
            }
        }
    }
}
