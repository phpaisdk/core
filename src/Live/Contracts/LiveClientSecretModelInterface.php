<?php

declare(strict_types=1);

namespace AiSdk\Live\Contracts;

use AiSdk\Live\ClientSecret;
use AiSdk\Live\LiveRequest;

/** Optional ephemeral credential lifecycle implemented by capable providers. */
interface LiveClientSecretModelInterface extends LiveModelInterface
{
    public function clientSecret(LiveRequest $request): ClientSecret;
}
