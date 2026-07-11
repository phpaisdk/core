<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\ModelDefinition;

interface ModelInspectionProviderInterface extends ProviderInterface
{
    public function inspectModel(string $modelId): ModelDefinition;
}
