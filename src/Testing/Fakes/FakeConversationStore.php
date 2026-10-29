<?php

declare(strict_types=1);

namespace AiSdk\Testing\Fakes;

use AiSdk\Conversations\ConversationList;
use AiSdk\Conversations\ConversationPage;
use AiSdk\Conversations\ConversationStore;
use AiSdk\Conversations\InMemoryConversationStore;
use AiSdk\Conversations\StoredMessage;
use AiSdk\Conversations\StoredStep;
use AiSdk\Exceptions\ConversationException;

/** A recording contract fake backed by the reference store; no external persistence. */
final class FakeConversationStore implements ConversationStore
{
    /** @var list<array{operation: string, ownerKey: string, conversationId: string|null}> */
    public array $calls = [];
    private InMemoryConversationStore $store;
    private ?ConversationException $failure = null;

    public function __construct(?InMemoryConversationStore $store = null, private readonly int $maxCalls = 10000)
    {
        if ($maxCalls < 1) {
            throw new ConversationException('The fake conversation call limit must be positive.');
        }
        $this->store = $store ?? new InMemoryConversationStore();
    }

    public function failWith(ConversationException $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    public function appendMessage(string $ownerKey, string $conversationId, StoredMessage $message): void
    {
        $this->record('appendMessage', $ownerKey, $conversationId);
        $this->store->appendMessage($ownerKey, $conversationId, $message);
    }

    public function appendStep(string $ownerKey, string $conversationId, StoredStep $step): void
    {
        $this->record('appendStep', $ownerKey, $conversationId);
        $this->store->appendStep($ownerKey, $conversationId, $step);
    }

    public function load(string $ownerKey, string $conversationId, ?string $cursor = null, int $limit = 50): ConversationPage
    {
        $this->record('load', $ownerKey, $conversationId);

        return $this->store->load($ownerKey, $conversationId, $cursor, $limit);
    }

    public function listByOwner(string $ownerKey, ?string $cursor = null, int $limit = 50): ConversationList
    {
        $this->record('listByOwner', $ownerKey, null);

        return $this->store->listByOwner($ownerKey, $cursor, $limit);
    }

    private function record(string $operation, string $ownerKey, ?string $conversationId): void
    {
        \AiSdk\Conversations\RecordBounds::id($ownerKey);
        if ($conversationId !== null) {
            \AiSdk\Conversations\RecordBounds::id($conversationId);
        }
        if (count($this->calls) >= $this->maxCalls) {
            throw new ConversationException('The fake conversation call limit was reached.');
        }
        $this->calls[] = ['operation' => $operation, 'ownerKey' => $ownerKey, 'conversationId' => $conversationId];
        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}
