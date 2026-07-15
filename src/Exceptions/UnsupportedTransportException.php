<?php

declare(strict_types=1);

namespace AiSdk\Exceptions;

use AiSdk\Live\TransportEndpoint;

final class UnsupportedTransportException extends TransportException
{
    public static function for(TransportEndpoint $endpoint): self
    {
        return new self('No transport supports endpoint [' . $endpoint::class . '].');
    }
}
