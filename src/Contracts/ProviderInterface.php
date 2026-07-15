<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

/**
 * Stable provider identity and the single public model selector.
 */
interface ProviderInterface
{
    public function name(): string;

    public function model(string $modelId): Model;
}
