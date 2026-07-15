<?php

declare(strict_types=1);

namespace AiSdk\Live\Contracts;

interface ProviderLiveCallInterface
{
    public function id(): string;

    public function accept(): void;

    public function connect(TransportInterface $transport): LiveSessionDriverInterface;

    public function hangup(): void;
}
