<?php

declare(strict_types=1);

namespace AiSdk\Live\Contracts;

use AiSdk\Live\TransportFrame;

interface TransportConnectionInterface
{
    public function send(TransportFrame $frame): void;

    public function receive(): ?TransportFrame;

    public function finishSending(): void;

    public function close(): void;

    public function isClosed(): bool;
}
