<?php

declare(strict_types=1);

namespace AiSdk\Conversations;

use AiSdk\Exceptions\ConversationException;
use AiSdk\Support\Json;

/** Bounded process-local reference implementation, without filesystem, database or framework dependencies. */
final class InMemoryConversationStore implements ConversationStore
{
    /** @var array<string, array<string, list<StoredMessage|StoredStep>>> */
    private array $entries = [];
    /** @var array<string, array<string, array<string, true>>> */
    private array $ids = [];
    /** @var array<string, array<string, ConversationInfo>> */
    private array $conversations = [];
    private int $entryCount = 0;
    private int $conversationCount = 0;
    private int $bytes = 0;
    private readonly string $cursorKey;

    public function __construct(private readonly int $maxConversations = 1000, private readonly int $maxEntries = 10000, private readonly int $maxBytes = 16777216)
    {
        if ($maxConversations < 1 || $maxEntries < 1 || $maxBytes < 1) {
            throw new ConversationException('Conversation store bounds must be positive.');
        }
        $this->cursorKey = random_bytes(32);
    }

    public function appendMessage(string $ownerKey, string $conversationId, StoredMessage $message): void
    {
        $this->append($ownerKey, $conversationId, $message);
    }

    public function appendStep(string $ownerKey, string $conversationId, StoredStep $step): void
    {
        $this->append($ownerKey, $conversationId, $step);
    }

    public function load(string $ownerKey, string $conversationId, ?string $cursor = null, int $limit = 50): ConversationPage
    {
        self::scope($ownerKey, $conversationId, $limit);
        $scope = hash('sha256', 'load:' . pack('N', strlen($ownerKey)) . $ownerKey . $conversationId);
        $offset = $this->offset($cursor, $scope);
        $entries = $this->entries[$ownerKey][$conversationId] ?? [];

        return new ConversationPage(array_values(array_slice($entries, $offset, $limit)), $offset + $limit < count($entries) ? $this->cursor($offset + $limit, $scope) : null);
    }

    public function listByOwner(string $ownerKey, ?string $cursor = null, int $limit = 50): ConversationList
    {
        self::scope($ownerKey, null, $limit);
        $scope = hash('sha256', 'list:' . $ownerKey);
        $offset = $this->offset($cursor, $scope);
        $items = array_values($this->conversations[$ownerKey] ?? []);

        return new ConversationList(array_values(array_slice($items, $offset, $limit)), $offset + $limit < count($items) ? $this->cursor($offset + $limit, $scope) : null);
    }

    private function append(string $ownerKey, string $conversationId, StoredMessage|StoredStep $record): void
    {
        self::scope($ownerKey, $conversationId);
        $id = ($record instanceof StoredMessage ? 'message:' : 'step:') . $record->id;
        if (isset($this->ids[$ownerKey][$conversationId][$id])) {
            throw new ConversationException('The conversation already contains this record id.');
        }
        $info = $this->conversations[$ownerKey][$conversationId] ?? null;
        $bytes = RecordBounds::bytes($record);
        if ($this->entryCount >= $this->maxEntries || $this->bytes + $bytes > $this->maxBytes || ($info === null && $this->conversationCount >= $this->maxConversations)) {
            throw new ConversationException('The conversation store reached its configured capacity.');
        }
        $info ??= new ConversationInfo($conversationId, $ownerKey);
        $this->entries[$ownerKey][$conversationId][] = $record;
        $this->ids[$ownerKey][$conversationId][$id] = true;
        $this->conversations[$ownerKey][$conversationId] = new ConversationInfo($conversationId, $ownerKey, $info->messageCount + ($record instanceof StoredMessage ? 1 : 0), $info->stepCount + ($record instanceof StoredStep ? 1 : 0));
        if ($info->messageCount + $info->stepCount === 0) {
            $this->conversationCount++;
        }
        $this->entryCount++;
        $this->bytes += $bytes;
    }

    private static function scope(string $ownerKey, ?string $conversationId = null, int $limit = 50): void
    {
        RecordBounds::id($ownerKey);
        if ($conversationId !== null) {
            RecordBounds::id($conversationId);
        }
        if ($limit < 1 || $limit > 1000) {
            throw new ConversationException('Conversation page limits must be between one and 1000.');
        }
    }

    private function cursor(int $offset, string $scope): string
    {
        $payload = rtrim(strtr(base64_encode(Json::encode([$scope, $offset])), '+/', '-_'), '=');

        return $payload . '.' . hash_hmac('sha256', $payload, $this->cursorKey);
    }

    private function offset(?string $cursor, string $scope): int
    {
        if ($cursor === null) {
            return 0;
        }
        if (strlen($cursor) > 1024) {
            throw new ConversationException('Invalid conversation cursor.');
        }
        [$payload, $signature] = array_pad(explode('.', $cursor, 2), 2, '');
        if (! hash_equals(hash_hmac('sha256', $payload, $this->cursorKey), $signature)) {
            throw new ConversationException('Invalid conversation cursor.');
        }
        $json = base64_decode(strtr($payload, '-_', '+/'), true);
        if ($json === false) {
            throw new ConversationException('Invalid conversation cursor.');
        }
        $value = json_decode($json, true);
        if (! is_array($value) || count($value) !== 2 || ($value[0] ?? null) !== $scope || ! is_int($value[1] ?? null) || $value[1] < 0) {
            throw new ConversationException('Invalid conversation cursor or owner scope.');
        }

        return $value[1];
    }
}
