<?php

declare(strict_types=1);

namespace AiSdk\Agents;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Observability\RunEvent;
use AiSdk\Streaming\StreamPart;
use AiSdk\Streaming\TextDeltaPart;
use Closure;
use Fiber;
use Generator;
use IteratorAggregate;
use Throwable;

/**
 * Pull-driven agent execution. No background work or unbounded history buffer.
 * @implements IteratorAggregate<int, StreamPart|RunEvent>
 */
final class AgentStream implements IteratorAggregate
{
    /** @var Fiber<void, void, AgentResult, StreamPart|RunEvent> */
    private Fiber $execution;
    private bool $consuming = false;
    private ?AgentResult $finalResult = null;
    private ?Throwable $failure = null;

    /** @param Closure(Closure(StreamPart|RunEvent): void): AgentResult $execute */
    public function __construct(Closure $execute)
    {
        $this->execution = new Fiber(static fn(): AgentResult => $execute(static function (StreamPart|RunEvent $part): void {
            Fiber::suspend($part);
        }));
    }

    /** @return Generator<int, StreamPart|RunEvent> */
    public function getIterator(): Generator
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if ($this->finalResult !== null) {
            return;
        }
        if ($this->consuming) {
            throw new InvalidArgumentException('An agent stream may have only one active consumer.');
        }
        $this->consuming = true;

        try {
            $part = $this->execution->isStarted() ? $this->execution->resume() : $this->execution->start();
            while (! $this->execution->isTerminated()) {
                if (! $part instanceof StreamPart && ! $part instanceof RunEvent) {
                    throw new InvalidResponseException('Agent streams require typed stream parts or run events.');
                }
                yield $part;
                $part = $this->execution->resume();
            }
            $result = $this->execution->getReturn();
            if (! $result instanceof AgentResult) {
                throw new InvalidResponseException('An agent stream must finish with an AgentResult.');
            }
            $this->finalResult = $result;
        } catch (Throwable $error) {
            $this->failure = $error;

            throw $error;
        } finally {
            $this->consuming = false;
        }
    }

    /** @return Generator<int, StreamPart|RunEvent> */
    public function parts(): Generator
    {
        yield from $this->getIterator();
    }

    /** @return Generator<int, string> */
    public function chunks(): Generator
    {
        foreach ($this as $part) {
            if ($part instanceof TextDeltaPart) {
                yield $part->text;
            }
        }
    }

    public function run(): AgentResult
    {
        foreach ($this as $_) {
            // Drive only the remaining work; completed tool handlers are never replayed.
        }

        return $this->result();
    }

    public function result(): AgentResult
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->finalResult ?? throw new InvalidArgumentException('Consume the agent stream before requesting its result.');
    }

    public function isComplete(): bool
    {
        return $this->finalResult !== null && $this->failure === null;
    }
}
