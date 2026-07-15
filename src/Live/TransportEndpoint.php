<?php

declare(strict_types=1);

namespace AiSdk\Live;

abstract readonly class TransportEndpoint
{
    /** @param array<string, string> $headers */
    public function __construct(public string $url, public array $headers = []) {}
}
