<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Capability;
use AiSdk\CapabilitySupport;
use AiSdk\ModelDefinition;

/**
 * Mutable runtime registry for user-registered models. Providers check
 * this registry before falling back to their bundled catalog, allowing
 * users to register new models without waiting for a package release.
 *
 * Lookup order:
 *   1. User-registered exact model ID
 *   2. Bundled catalog (handled by the provider/model)
 *   3. Unknown model fallback (TextGeneration allowed, others deferred to provider API errors)
 */
final class ModelRegistry
{
    /** @var array<string, array<string, ModelDefinition>> */
    private array $models = [];

    /**
     * Register a model definition for a given provider.
     */
    public function register(string $provider, ModelDefinition $model): self
    {
        $this->models[$provider][$model->id] = $model;

        return $this;
    }

    /**
     * Resolve a user-registered model by provider and model ID.
     */
    public function resolve(string $provider, string $modelId): ?ModelDefinition
    {
        return $this->models[$provider][$modelId] ?? null;
    }

    /**
     * Check capability support for a registered model. Returns null if
     * the model is not registered, so the caller can fall back to the
     * bundled catalog.
     */
    public function capability(string $provider, string $modelId, Capability $capability): ?CapabilitySupport
    {
        $model = $this->resolve($provider, $modelId);

        if ($model === null) {
            return null;
        }

        $names = $model->capabilityNames();

        if (! in_array($capability->name(), $names, true)) {
            return CapabilitySupport::notSupported($capability, "Model [{$modelId}] is not registered with capability [{$capability->name}].");
        }

        $adapted = $model->adaptedCapabilities[$capability->name()] ?? null;

        if (is_array($adapted)) {
            return CapabilitySupport::adapted(
                $capability,
                isset($adapted['strategy']) ? (string) $adapted['strategy'] : 'Provider adapter implements this capability.',
                'user-registered',
                $model->metadata,
            );
        }

        return CapabilitySupport::supported($capability, 'user-registered', $model->metadata);
    }
}
