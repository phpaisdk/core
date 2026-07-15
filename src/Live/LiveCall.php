<?php

declare(strict_types=1);

namespace AiSdk\Live;

use AiSdk\Live\Contracts\ProviderLiveCallInterface;
use AiSdk\Live\Contracts\TransportInterface;
use AiSdk\Tool;

final readonly class LiveCall
{
    /** @param list<Tool> $tools */
    public function __construct(private ProviderLiveCallInterface $call, private array $tools = []) {}

    public function id(): string
    {
        return $this->call->id();
    }

    public function accept(): self
    {
        $this->call->accept();

        return $this;
    }

    public function connect(TransportInterface $transport): LiveSession
    {
        return new LiveSession($this->call->connect($transport), $this->tools);
    }

    public function hangup(): void
    {
        $this->call->hangup();
    }
}
