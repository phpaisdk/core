<?php

declare(strict_types=1);

namespace AiSdk\Live\Contracts;

use AiSdk\Contracts\Model;
use AiSdk\Live\LiveRequest;

/** A provider adapter for a stateful Live session. */
interface LiveModelInterface extends Model
{
    public function createLiveSession(LiveRequest $request, TransportInterface $transport): LiveSessionDriverInterface;
}
