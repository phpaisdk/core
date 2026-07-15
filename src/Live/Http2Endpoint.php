<?php

declare(strict_types=1);

namespace AiSdk\Live;

final readonly class Http2Endpoint extends TransportEndpoint
{
    public function __construct(string $url, array $headers = [], public string $method = 'POST')
    {
        parent::__construct($url, $headers);
    }
}
