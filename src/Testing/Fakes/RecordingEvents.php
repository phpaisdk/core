<?php

declare(strict_types=1);

namespace AiSdk\Testing\Fakes;

use AiSdk\Observability\Events;
use AiSdk\Observability\RunEvent;

/** Application test listener. Instantiate a fresh recorder for each test. */
final class RecordingEvents implements Events
{
    /** @var list<RunEvent> */
    public array $events = [];

    public function handle(RunEvent $event): void
    {
        $this->events[] = $event;
    }
}
