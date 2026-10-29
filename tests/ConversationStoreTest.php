<?php

declare(strict_types=1);

use AiSdk\Conversations\Citation;
use AiSdk\Conversations\ConversationStore;
use AiSdk\Conversations\InMemoryConversationStore;
use AiSdk\Conversations\StoredMessage;
use AiSdk\Conversations\StoredStep;
use AiSdk\Exceptions\ConversationException;
use AiSdk\FinishReason;
use AiSdk\Message;
use AiSdk\Support\Usage;
use AiSdk\Testing\Fakes\FakeConversationStore;
use AiSdk\ToolCall;
use AiSdk\ToolResult;

it('preserves ordered messages and steps with tool data reasoning and citations', function () {
    $store = new InMemoryConversationStore();
    $citation = new Citation('source', 'Source title', 'https://example.test/fact', 'The fact');
    $call = new ToolCall('call', 'lookup', ['query' => 'fact']);
    $toolResult = new ToolResult('call', 'lookup', ['answer' => 'fact'], isError: false);
    $message = new StoredMessage('assistant', Message::assistant('The answer', [$call]), reasoning: 'Rationale', citations: [$citation]);
    $step = new StoredStep('step', 'invocation', 1, new Usage(2, 3), FinishReason::ToolCalls, [$call], [$toolResult], 'Rationale', [$citation], ['assistant']);
    $store->appendMessage('owner', 'conversation', $message);
    $store->appendStep('owner', 'conversation', $step);
    $first = $store->load('owner', 'conversation', limit: 1);
    expect($first->entries)->toBe([$message])->and($first->nextCursor)->not->toBeNull();
    $second = $store->load('owner', 'conversation', $first->nextCursor, 1);
    expect($second->entries)->toBe([$step])->and($second->nextCursor)->toBeNull()
        ->and($second->entries[0]->toolResults[0]->output)->toBe(['answer' => 'fact'])
        ->and($second->entries[0]->citations[0]->excerpt)->toBe('The fact')
        ->and($second->entries[0]->reasoning)->toBe('Rationale');
    $info = $store->listByOwner('owner')->conversations[0];
    expect($info->messageCount)->toBe(1)->and($info->stepCount)->toBe(1);
});

it('isolates every append load list and pagination cursor by owner and conversation', function () {
    $store = new InMemoryConversationStore();
    foreach (['owner-a', 'owner-b'] as $owner) {
        foreach (['one', 'two'] as $conversation) {
            $store->appendMessage($owner, $conversation, new StoredMessage('id', Message::user($owner)));
            $store->appendMessage($owner, $conversation, new StoredMessage('second', Message::user('second')));
        }
    }
    expect($store->load('owner-a', 'one')->entries[0]->message->text())->toBe('owner-a')
        ->and($store->load('unknown', 'one')->entries)->toBe([])
        ->and($store->listByOwner('unknown')->conversations)->toBe([]);
    $page = $store->load('owner-a', 'one', limit: 1);
    expect(fn() => $store->load('owner-b', 'one', $page->nextCursor))->toThrow(ConversationException::class);
    expect(fn() => $store->load('owner-a', 'two', $page->nextCursor))->toThrow(ConversationException::class);
    expect(fn() => $store->listByOwner('owner-a', $page->nextCursor))->toThrow(ConversationException::class);
    expect(fn() => $store->load('owner-a', 'one', $page->nextCursor . 'tamper'))->toThrow(ConversationException::class);
    $list = $store->listByOwner('owner-a', limit: 1);
    expect($list->conversations[0]->id)->toBe('one')->and($store->listByOwner('owner-a', $list->nextCursor)->conversations[0]->id)->toBe('two');
});

it('keeps append pagination stable and rejects duplicate records atomically', function () {
    $store = new InMemoryConversationStore();
    $store->appendMessage('owner', 'one', new StoredMessage('a', Message::user('a')));
    $store->appendMessage('owner', 'one', new StoredMessage('b', Message::user('b')));
    $page = $store->load('owner', 'one', limit: 1);
    $store->appendMessage('owner', 'one', new StoredMessage('c', Message::user('c')));
    $next = $store->load('owner', 'one', $page->nextCursor);
    expect(array_map(fn($entry) => $entry->id, $next->entries))->toBe(['b', 'c']);
    expect(fn() => $store->appendMessage('owner', 'one', new StoredMessage('a', Message::user('changed'))))->toThrow(ConversationException::class);
    expect($store->listByOwner('owner')->conversations[0]->messageCount)->toBe(3)
        ->and($store->load('owner', 'one')->entries[0]->message->text())->toBe('a');
});

it('enforces capacity before writes and bounds pages and records', function () {
    $store = new InMemoryConversationStore(maxConversations: 1, maxEntries: 2);
    $store->appendMessage('owner', 'one', new StoredMessage('a', Message::user('a')));
    expect(fn() => $store->appendMessage('owner', 'two', new StoredMessage('a', Message::user('a'))))->toThrow(ConversationException::class);
    expect($store->listByOwner('owner')->conversations)->toHaveCount(1);
    $store->appendStep('owner', 'one', new StoredStep('step', 'invocation', 1));
    expect(fn() => $store->appendMessage('owner', 'one', new StoredMessage('b', Message::user('b'))))->toThrow(ConversationException::class);
    expect(fn() => $store->load('owner', 'one', limit: 1001))->toThrow(ConversationException::class);
    expect(fn() => $store->load('', 'one'))->toThrow(ConversationException::class);
    expect(fn() => $store->load('owner', 'one', 'invalid'))->toThrow(ConversationException::class);
    expect(fn() => (new InMemoryConversationStore())->appendMessage('owner', 'one', new StoredMessage('huge', Message::user(str_repeat('a', 1024 * 1024 + 1)))))->toThrow(ConversationException::class);
    expect(fn() => (new InMemoryConversationStore(maxBytes: 1))->appendMessage('owner', 'one', new StoredMessage('a', Message::user('a'))))->toThrow(ConversationException::class);
});

it('does not require JSON encoding or invoke serializers to store data', function () {
    $called = false;
    $output = new class ($called) implements JsonSerializable {
        public string $text = 'output';
        public function __construct(private bool &$called) {}
        public function jsonSerialize(): mixed
        {
            $this->called = true;

            throw new RuntimeException('must not execute');
        }
    };
    $store = new InMemoryConversationStore();
    $message = new StoredMessage('binary', Message::user("\xFF\x00\x81"), toolResults: [new ToolResult('call', 'lookup', $output)]);
    $store->appendMessage('owner', 'one', $message);
    expect($called)->toBeFalse()->and($store->load('owner', 'one')->entries[0])->toBe($message);
});

it('provides a recording fake and scripted storage failures through the same contract', function () {
    $fake = new FakeConversationStore();
    expect($fake)->toBeInstanceOf(ConversationStore::class);
    $fake->appendMessage('owner', 'one', new StoredMessage('a', Message::user('a')));
    expect($fake->load('owner', 'one')->entries)->toHaveCount(1)
        ->and(array_column($fake->calls, 'operation'))->toBe(['appendMessage', 'load']);
    $failure = new ConversationException('storage unavailable');
    $fake->failWith($failure);
    expect(fn() => $fake->listByOwner('owner'))->toThrow($failure::class);
    $bounded = new FakeConversationStore(maxCalls: 1);
    $bounded->listByOwner('owner');
    expect(fn() => $bounded->listByOwner('owner'))->toThrow(ConversationException::class);
});

it('rejects malformed record fields with SDK exceptions', function () {
    expect(fn() => new StoredMessage('', Message::user('x')))->toThrow(ConversationException::class);
    expect(fn() => new StoredStep('id', 'run', 0))->toThrow(ConversationException::class);
    expect(fn() => new StoredStep('id', 'run', 1, citations: [new stdClass()]))->toThrow(ConversationException::class);
    expect(fn() => new StoredMessage('id', Message::user('x'), toolResults: ['bad']))->toThrow(ConversationException::class);
    expect(fn() => new Citation(''))->toThrow(ConversationException::class);
});
