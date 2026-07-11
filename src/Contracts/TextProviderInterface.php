<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

interface TextProviderInterface extends ProviderInterface
{
    public function textModel(string $modelId): TextModelInterface;
}
