<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

interface SpeechProviderInterface extends ProviderInterface
{
    public function speechModel(string $modelId): SpeechModelInterface;
}
