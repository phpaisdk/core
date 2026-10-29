<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\Exceptions\CapabilityNotSupportedException;
use AiSdk\Exceptions\NoSuchModelException;
use AiSdk\Live\Contracts\LiveModelInterface;
use AiSdk\ProviderModel;

/**
 * Optional base for providers. Public model references are resolved into
 * protected capability adapters only when an operation executes.
 */
abstract class BaseProvider implements ProviderInterface
{
    abstract public function name(): string;

    final public function model(string $modelId): Model
    {
        return new ProviderModel(
            $this->name(),
            $modelId,
            fn(string $contract): Model => $this->resolveModel($modelId, $contract),
        );
    }

    /** @param class-string<Model> $contract */
    final protected function resolveModel(string $modelId, string $contract): Model
    {
        return match ($contract) {
            DecisionModelInterface::class => $this->decisionModel($modelId),
            TextModelInterface::class => $this->textModel($modelId),
            ImageModelInterface::class => $this->imageModel($modelId),
            SpeechModelInterface::class => $this->speechModel($modelId),
            TranscriptionModelInterface::class => $this->transcriptionModel($modelId),
            EmbeddingModelInterface::class => $this->embeddingModel($modelId),
            VideoModelInterface::class => $this->videoModel($modelId),
            LiveModelInterface::class => $this->liveModel($modelId),
            default => throw NoSuchModelException::for($this->name(), $modelId, $contract),
        };
    }

    /**
     * Provider-account file storage. Providers that offer it override this method.
     */
    public function files(): FileStoreInterface
    {
        throw CapabilityNotSupportedException::forProvider($this->name(), 'file storage');
    }

    protected function decisionModel(string $modelId): DecisionModelInterface
    {
        throw NoSuchModelException::for($this->name(), $modelId, 'decisionModel');
    }

    protected function textModel(string $modelId): TextModelInterface
    {
        throw NoSuchModelException::for($this->name(), $modelId, 'textModel');
    }

    protected function imageModel(string $modelId): ImageModelInterface
    {
        throw NoSuchModelException::for($this->name(), $modelId, 'imageModel');
    }

    protected function speechModel(string $modelId): SpeechModelInterface
    {
        throw NoSuchModelException::for($this->name(), $modelId, 'speechModel');
    }

    protected function transcriptionModel(string $modelId): TranscriptionModelInterface
    {
        throw NoSuchModelException::for($this->name(), $modelId, 'transcriptionModel');
    }

    protected function embeddingModel(string $modelId): EmbeddingModelInterface
    {
        throw NoSuchModelException::for($this->name(), $modelId, 'embeddingModel');
    }

    protected function videoModel(string $modelId): VideoModelInterface
    {
        throw NoSuchModelException::for($this->name(), $modelId, 'videoModel');
    }

    protected function liveModel(string $modelId): LiveModelInterface
    {
        throw NoSuchModelException::for($this->name(), $modelId, 'liveModel');
    }
}
