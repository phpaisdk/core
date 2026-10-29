<?php

declare(strict_types=1);

namespace AiSdk\Observability;

/** Listener failures are isolated from the operation being observed. */
interface Events
{
    public function handle(RunEvent $event): void;
}
