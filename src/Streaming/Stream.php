<?php

declare(strict_types=1);

namespace AiSdk\Streaming;

use AiSdk\Results\TextResult;
use Generator;
use IteratorAggregate;
use Throwable;

/**
 * User-facing wrapper around a provider Generator<StreamPart>. Accumulates
 * via StreamState and fires hooks while the generator is consumed. SSE/PSR-7
 * rendering is intentionally NOT here — that belongs in a transport adapter.
 *
 * @implements IteratorAggregate<int, StreamPart>
 */
final class Stream implements IteratorAggregate
{
    /** @var callable|null */
    private $onChunk = null;

    /** @var callable|null */
    private $onToolCall = null;

    /** @var callable|null */
    private $onFinish = null;

    /** @var callable|null */
    private $onError = null;

    private readonly StreamState $state;

    /** @var array<int, StreamPart> */
    private array $history = [];

    private bool $complete = false;

    private ?Throwable $failure = null;

    private bool $advanceSource = false;

    /**
     * @param  Generator<int, StreamPart>  $parts
     */
    public function __construct(private readonly Generator $parts)
    {
        $this->state = new StreamState();
    }

    public function onChunk(callable $callback): self
    {
        $this->onChunk = $callback;

        return $this;
    }

    public function onToolCall(callable $callback): self
    {
        $this->onToolCall = $callback;

        return $this;
    }

    public function onFinish(callable $callback): self
    {
        $this->onFinish = $callback;

        return $this;
    }

    public function onError(callable $callback): self
    {
        $this->onError = $callback;

        return $this;
    }

    /**
     * @return Generator<int, StreamPart>
     */
    public function getIterator(): Generator
    {
        return $this->parts();
    }

    /**
     * @return Generator<int, StreamPart>
     */
    public function parts(): Generator
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        if ($this->complete) {
            yield from $this->history;

            return;
        }

        try {
            while (true) {
                if ($this->advanceSource) {
                    $this->parts->next();
                    $this->advanceSource = false;
                }

                if (! $this->parts->valid()) {
                    break;
                }

                $part = $this->parts->current();
                $this->advanceSource = true;

                if ($part instanceof ErrorPart) {
                    if ($this->onError !== null) {
                        ($this->onError)($part->error);

                        continue;
                    }

                    throw $part->error;
                }

                $this->history[] = $part;
                $this->state->record($part);

                if ($part instanceof TextDeltaPart && $this->onChunk !== null) {
                    ($this->onChunk)($part->text);
                }

                yield $part;
            }

            $this->complete = true;

            if ($this->onToolCall !== null) {
                foreach ($this->state->toolCalls() as $call) {
                    ($this->onToolCall)($call);
                }
            }

            if ($this->onFinish !== null) {
                ($this->onFinish)($this->result());
            }
        } catch (Throwable $e) {
            $this->failure = $e;

            if ($this->onError !== null) {
                ($this->onError)($e);
            }

            throw $e;
        }
    }

    /**
     * @return Generator<int, string>
     */
    public function chunks(): Generator
    {
        foreach ($this->parts() as $part) {
            if ($part instanceof TextDeltaPart) {
                yield $part->text;
            }
        }
    }

    public function run(): TextResult
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        if ($this->complete) {
            return $this->result();
        }

        foreach ($this->parts() as $_) {
            // drain to drive accumulation + hooks
        }

        return $this->result();
    }

    public function result(): TextResult
    {
        return new TextResult(
            text: $this->state->text(),
            reasoning: $this->state->reasoning(),
            output: null,
            toolCalls: $this->state->toolCalls(),
            finishReason: $this->state->finishReason(),
            usage: $this->state->usage(),
            providerMetadata: $this->state->providerMetadata(),
        );
    }
}
