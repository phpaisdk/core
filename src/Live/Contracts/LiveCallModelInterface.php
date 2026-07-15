<?php

declare(strict_types=1);

namespace AiSdk\Live\Contracts;

use AiSdk\Live\LiveRequest;

/** Optional provider-hosted call lifecycle implemented by capable providers. */
interface LiveCallModelInterface extends LiveModelInterface
{
    public function call(LiveRequest $request, string $callId): ProviderLiveCallInterface;
}
