<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

interface TranscriptionProviderInterface extends ProviderInterface
{
    public function transcriptionModel(string $modelId): TranscriptionModelInterface;
}
