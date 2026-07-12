<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\Exceptions\NoSuchModelException;

/**
 * Optional base for providers. Every modality factory throws
 * NoSuchModelException by default; a concrete provider overrides only the
 * modalities it actually supports. Eliminates per-provider throw boilerplate.
 */
abstract class BaseProvider implements ProviderInterface
{
    abstract public function name(): string;

    public function textModel(string $modelId): TextModelInterface
    {
        throw NoSuchModelException::for($this->name(), $modelId, 'textModel');
    }

    public function imageModel(string $modelId): ImageModelInterface
    {
        throw NoSuchModelException::for($this->name(), $modelId, 'imageModel');
    }

    public function speechModel(string $modelId): SpeechModelInterface
    {
        throw NoSuchModelException::for($this->name(), $modelId, 'speechModel');
    }

    public function embeddingModel(string $modelId): EmbeddingModelInterface
    {
        throw NoSuchModelException::for($this->name(), $modelId, 'embeddingModel');
    }

    public function videoModel(string $modelId): VideoModelInterface
    {
        throw NoSuchModelException::for($this->name(), $modelId, 'videoModel');
    }

}
