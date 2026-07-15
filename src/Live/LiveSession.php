<?php

declare(strict_types=1);

namespace AiSdk\Live;

use AiSdk\Live\Contracts\LiveSessionDriverInterface;
use AiSdk\Tool;
use AiSdk\ToolExecutionContext;

final readonly class LiveSession
{
    /** @param list<Tool> $tools */
    public function __construct(private LiveSessionDriverInterface $driver, private array $tools = []) {}

    public function sendAudio(string $bytes): void
    {
        $this->driver->sendAudio($bytes);
    }
    public function sendText(string $text): void
    {
        $this->driver->sendText($text);
    }
    public function commitAudio(): void
    {
        $this->driver->commitAudio();
    }
    public function clearAudio(): void
    {
        $this->driver->clearAudio();
    }
    public function requestResponse(): void
    {
        $this->driver->requestResponse();
    }
    public function cancelResponse(): void
    {
        $this->driver->cancelResponse();
    }
    public function sendToolResult(string $callId, mixed $result): void
    {
        $this->driver->sendToolResult($callId, $result);
    }
    /** @return iterable<LiveEvent> */
    public function events(): iterable
    {
        foreach ($this->driver->events() as $event) {
            yield $event;

            if (! $event instanceof ToolCallEvent) {
                continue;
            }

            $tool = $this->tool($event->name);
            if ($tool === null || $tool->handler() === null) {
                continue;
            }

            $result = $tool->call($event->arguments, new ToolExecutionContext(
                toolCallId: $event->callId,
                toolName: $event->name,
                arguments: $event->arguments,
            ));
            $this->driver->sendToolResult($event->callId, $result);

            yield new ToolResultEvent($event->callId, $event->name, $result, true);
        }
    }
    public function close(): void
    {
        $this->driver->close();
    }

    private function tool(string $name): ?Tool
    {
        foreach ($this->tools as $tool) {
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        return null;
    }
}
