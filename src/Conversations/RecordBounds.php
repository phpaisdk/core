<?php

declare(strict_types=1);

namespace AiSdk\Conversations;

use AiSdk\Exceptions\ConversationException;
use AiSdk\ToolCall;
use AiSdk\ToolResult;

/** @internal Portable record validation shared by DTOs and the reference implementation. */
final class RecordBounds
{
    public static function id(string $value): void
    {
        if (trim($value) === '' || strlen($value) > 256) {
            throw new ConversationException('Conversation keys and record ids must contain 1 to 256 bytes.');
        }
    }

    /** @param array<int, ToolCall> $calls
     * @param array<int, ToolResult> $results
     * @param list<Citation> $citations */
    public static function fields(array $calls, array $results, ?string $reasoning, array $citations): void
    {
        if (count($calls) > 4096 || count($results) > 4096 || count($citations) > 1000 || strlen($reasoning ?? '') > 1024 * 1024) {
            throw new ConversationException('Conversation record fields exceed their size limits.');
        }
        foreach ($calls as $call) {
            if (! $call instanceof ToolCall) {
                throw new ConversationException('Stored tool calls must be ToolCall values.');
            }
        }
        foreach ($results as $result) {
            if (! $result instanceof ToolResult) {
                throw new ConversationException('Stored tool results must be ToolResult values.');
            }
        }
        foreach ($citations as $citation) {
            if (! $citation instanceof Citation) {
                throw new ConversationException('Stored citations must be Citation values.');
            }
        }
    }

    public static function bytes(StoredMessage|StoredStep $record): int
    {
        $bytes = 0;
        $nodes = 0;
        self::measure($record, 0, new \SplObjectStorage(), $bytes, $nodes);

        return $bytes;
    }

    /** @param \SplObjectStorage<object, null> $seen */
    private static function measure(mixed $value, int $depth, \SplObjectStorage $seen, int &$bytes, int &$nodes): void
    {
        if ($depth > 32 || ++$nodes > 32768) {
            throw new ConversationException('A conversation record exceeds its nesting or value count limit.');
        }
        if (is_resource($value)) {
            throw new ConversationException('Conversation records cannot contain open resources.');
        }
        $bytes += is_string($value) ? strlen($value) : 16;
        if ($bytes > 1024 * 1024) {
            throw new ConversationException('A conversation record cannot exceed 1 MiB.');
        }
        if (is_object($value)) {
            if ($seen->offsetExists($value)) {
                return;
            }
            $seen->offsetSet($value, null);
            // Inspect data only: do not invoke serializers, accessors or other application code.
            $value = get_object_vars($value);
        }
        if (is_array($value)) {
            if (count($value) > 4096) {
                throw new ConversationException('Conversation record arrays cannot exceed 4096 values.');
            }
            foreach ($value as $key => $item) {
                self::measure($key, $depth + 1, $seen, $bytes, $nodes);
                self::measure($item, $depth + 1, $seen, $bytes, $nodes);
            }
        }
    }
}
