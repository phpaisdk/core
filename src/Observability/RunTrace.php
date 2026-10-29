<?php

declare(strict_types=1);

namespace AiSdk\Observability;

use AiSdk\FinishReason;
use AiSdk\Observability\Events as Event;
use AiSdk\Support\Usage;
use AiSdk\ToolCall;
use Closure;
use Throwable;

/** @internal Per-execution dispatcher; no ambient or static state. */
final class RunTrace
{
    private bool $terminal = false;

    /** @param list<Events|Closure> $listeners */
    public function __construct(public RunContext $context, private array $listeners = []) {}

    public function emit(RunEvent $event): void
    {
        foreach ($this->listeners as $listener) {
            try {
                if ($listener instanceof Events) {
                    $listener->handle($event);
                } else {
                    $listener($event);
                }
            } catch (Throwable) {
                // Observers cannot invalidate or retry work, nor prevent other listeners running.
            }
        }
    }

    public function start(): void
    {
        $this->emit(new Event\RunStarted($this->context));
    }

    public function stepStarted(int $index): void
    {
        $this->emit(new Event\StepStarted($this->context, $index));
    }

    /** @param array<int, ToolCall> $calls */
    public function stepFinished(int $index, Usage $usage, FinishReason $reason, array $calls): void
    {
        $safe = array_map(static fn(ToolCall $call): ToolCall => new ToolCall($call->id, $call->name, []), array_values($calls));
        $this->emit(new Event\StepFinished($this->context, $index, $usage, $reason, $safe));
    }

    public function finish(Usage $usage): RunContext
    {
        if (! $this->terminal) {
            $this->terminal = true;
            $this->context = $this->context->finished();
            $this->emit(new Event\RunFinished($this->context, $usage));
        }

        return $this->context;
    }

    public function fail(Throwable $error): void
    {
        if (! $this->terminal) {
            $this->failedClass($error::class);
        }
    }

    public function modelEvent(ModelEvent $event): void
    {
        if ($event->phase === 'error' && ! $this->terminal) {
            $this->failedClass($event->errorType ?? \AiSdk\Exceptions\AiSdkException::class);
        }
    }

    private function failedClass(string $class): void
    {
        $this->terminal = true;
        $this->context = $this->context->finished();
        // Arbitrary provider/handler messages can contain unrecognizable credentials or private data.
        $this->emit(new Event\RunFailed($this->context, $class, 'Run failed.'));
    }
}
