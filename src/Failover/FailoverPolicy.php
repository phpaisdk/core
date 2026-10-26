<?php

declare(strict_types=1);

namespace AiSdk\Failover;

use AiSdk\Exceptions\APIConnectionException;
use AiSdk\Exceptions\CapabilityNotSupportedException;
use AiSdk\Exceptions\InternalServerException;
use AiSdk\Exceptions\OverloadedException;
use AiSdk\Exceptions\ProviderException;
use AiSdk\Exceptions\RateLimitException;
use AiSdk\Exceptions\TimeoutException;
use AiSdk\Exceptions\TransportException;
use Throwable;

/**
 * Default rule: fail over on problems that another provider can plausibly
 * avoid, never on problems with the request or credentials themselves.
 */
final class FailoverPolicy
{
    private function __construct() {}

    public static function isTransient(Throwable $error): bool
    {
        if ($error instanceof RateLimitException
            || $error instanceof OverloadedException
            || $error instanceof InternalServerException
            || $error instanceof APIConnectionException
            || $error instanceof TimeoutException
            || $error instanceof TransportException
            || $error instanceof CapabilityNotSupportedException) {
            return true;
        }

        if ($error instanceof ProviderException) {
            $status = $error->status();

            return $status !== null && ($status === 408 || $status === 409 || $status === 429 || $status >= 500);
        }

        return false;
    }
}
