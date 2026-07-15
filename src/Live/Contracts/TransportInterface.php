<?php

declare(strict_types=1);

namespace AiSdk\Live\Contracts;

use AiSdk\Live\TransportEndpoint;

interface TransportInterface
{
    public function supports(TransportEndpoint $endpoint): bool;

    public function connect(TransportEndpoint $endpoint): TransportConnectionInterface;
}
