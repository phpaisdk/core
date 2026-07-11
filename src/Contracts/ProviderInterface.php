<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

/**
 * Stable identity shared by every provider. Modality factories live on
 * capability-specific provider interfaces so adding a new SDK modality does
 * not break third-party providers.
 */
interface ProviderInterface
{
    public function name(): string;
}
