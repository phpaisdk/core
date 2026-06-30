<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\Capability;
use AiSdk\Exceptions\NoSuchModelException;
use AiSdk\ModelDefinition;
use AiSdk\Support\ModelRegistry;

/**
 * Optional base for providers. Every modality factory throws
 * NoSuchModelException by default; a concrete provider overrides only the
 * modalities it actually supports. Eliminates per-provider throw boilerplate.
 *
 * Includes a mutable ModelRegistry so users can register custom models at
 * runtime without waiting for a package release.
 */
abstract class BaseProvider implements ProviderInterface
{
    private ?ModelRegistry $registry = null;

    abstract public function name(): string;

    public function textModel(string $modelId): TextModelInterface
    {
        throw NoSuchModelException::for($this->name(), $modelId, 'textModel');
    }

    /**
     * @param  ModelDefinition|string  $model
     * @param  array<int, Capability>  $capabilities
     */
    public function registerModel(ModelDefinition|string $model, array $capabilities = []): static
    {
        if (is_string($model)) {
            $model = new ModelDefinition(id: $model, capabilities: $capabilities);
        }

        $this->registry()->register($this->name(), $model);

        return $this;
    }

    /**
     * Get the runtime model registry for this provider.
     */
    protected function registry(): ModelRegistry
    {
        return $this->registry ??= new ModelRegistry();
    }

    /**
     * Resolve the model registry (or null if no models have been registered).
     */
    protected function modelRegistry(): ?ModelRegistry
    {
        return $this->registry;
    }
}
