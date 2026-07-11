<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\ModelDefinition;

interface AvailableModelsProviderInterface extends ProviderInterface
{
    /**
     * List models available in the provider's current runtime, account, or region.
     *
     * @return array<int, ModelDefinition>
     */
    public function availableModels(): array;
}
