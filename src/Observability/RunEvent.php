<?php

declare(strict_types=1);

namespace AiSdk\Observability;

interface RunEvent
{
    public function context(): RunContext;
}
