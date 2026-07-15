<?php

declare(strict_types=1);

namespace AiSdk\Live\Contracts;

use AiSdk\Live\LiveEvent;

/** Provider-owned event codec and session lifecycle. */
interface LiveSessionDriverInterface
{
    public function sendAudio(string $bytes): void;

    public function sendText(string $text): void;

    public function commitAudio(): void;

    public function clearAudio(): void;

    public function requestResponse(): void;

    public function cancelResponse(): void;

    public function sendToolResult(string $callId, mixed $result): void;

    /** @return iterable<LiveEvent> */
    public function events(): iterable;

    public function close(): void;
}
