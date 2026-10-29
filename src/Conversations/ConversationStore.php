<?php

declare(strict_types=1);

namespace AiSdk\Conversations;

/**
 * Application/framework packages supply durable implementations and authorization.
 * Every operation is scoped by an application-owned opaque owner key. Appends are atomic.
 * Cursors are opaque and scoped to the owner, conversation and operation; pass them back unchanged.
 */
interface ConversationStore
{
    public function appendMessage(string $ownerKey, string $conversationId, StoredMessage $message): void;

    public function appendStep(string $ownerKey, string $conversationId, StoredStep $step): void;

    /** Entries in append order. A missing conversation returns an empty page. */
    public function load(string $ownerKey, string $conversationId, ?string $cursor = null, int $limit = 50): ConversationPage;

    /** Conversations in first-append order; entries from other owners must never appear. */
    public function listByOwner(string $ownerKey, ?string $cursor = null, int $limit = 50): ConversationList;
}
