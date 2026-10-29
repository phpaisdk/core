<?php

declare(strict_types=1);

namespace AiSdk\Testing\Fakes;

use AiSdk\Exceptions\AiSdkException;

/** Script a model failure without making a provider request. */
trait FailsModelOperations
{
    private ?AiSdkException $failure = null;

    public function failWith(AiSdkException $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    private function failIfRequested(): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}
