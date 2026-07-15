<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class WebSocketEndpoint extends TransportEndpoint
{
    /**
     * @param array<string, string> $headers
     * @param list<string> $subprotocols
     */
    public function __construct(string $url, array $headers = [], public array $subprotocols = [])
    {
        parent::__construct($url, $headers);
    }
}
