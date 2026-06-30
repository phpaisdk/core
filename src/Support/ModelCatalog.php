<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Capability;
use AiSdk\CapabilitySupport;
use AiSdk\Exceptions\InvalidArgumentException;

final class ModelCatalog
{
    /**
     * @var array<string, self>
     */
    private static array $cache = [];

    /**
     * @param  array<int, array<string, mixed>>  $models
     */
    private function __construct(
        private readonly string $provider,
        private readonly array $models,
    ) {}

    public static function fromFile(string $path): self
    {
        return self::$cache[$path] ??= self::load($path);
    }

    /**
     * @return array<int, Capability>
     */
    public function capabilities(string $modelId): array
    {
        $model = $this->model($modelId);
        if ($model === null) {
            return [];
        }

        $capabilities = [];
        foreach ($this->capabilityNames($model) as $name) {
            $capability = Capability::fromName($name);
            if ($capability !== null) {
                $capabilities[] = $capability;
            }
        }

        return array_values(array_unique($capabilities, SORT_REGULAR));
    }

    public function capability(string $modelId, Capability $capability): CapabilitySupport
    {
        $model = $this->model($modelId);
        if ($model === null) {
            return CapabilitySupport::notSupported($capability, "Model [{$modelId}] is not listed in {$this->provider} resources/models.json.");
        }

        $name = $capability->name();
        if (! in_array($name, $this->capabilityNames($model), true)) {
            return CapabilitySupport::notSupported($capability, "Model [{$modelId}] is not marked as supporting [{$name}].");
        }

        $adapted = $model['adapted_capabilities'][$name] ?? null;
        if (is_array($adapted)) {
            return CapabilitySupport::adapted(
                $capability,
                isset($adapted['strategy']) ? (string) $adapted['strategy'] : 'Provider adapter implements this capability.',
                "{$this->provider} resources/models.json",
                $model,
            );
        }

        return CapabilitySupport::supported($capability, "{$this->provider} resources/models.json", $model);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function model(string $modelId): ?array
    {
        foreach ($this->models as $model) {
            if (($model['id'] ?? null) === $modelId) {
                return $model;
            }
        }

        foreach ($this->models as $model) {
            $pattern = $model['id'] ?? null;
            if (is_string($pattern) && str_contains($pattern, '*') && fnmatch($pattern, $modelId)) {
                return $model;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $model
     * @return array<int, string>
     */
    private function capabilityNames(array $model): array
    {
        $capabilities = isset($model['capabilities']) && is_array($model['capabilities'])
            ? array_values(array_filter($model['capabilities'], is_string(...)))
            : [];

        $adapted = isset($model['adapted_capabilities']) && is_array($model['adapted_capabilities'])
            ? array_keys($model['adapted_capabilities'])
            : [];

        return array_values(array_unique(array_merge($capabilities, $adapted)));
    }

    private static function load(string $path): self
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new InvalidArgumentException("Unable to read model catalog [{$path}].");
        }

        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException("Model catalog [{$path}] must decode to an object.");
        }

        $provider = isset($decoded['provider']) ? (string) $decoded['provider'] : 'unknown';
        $models = isset($decoded['models']) && is_array($decoded['models']) ? $decoded['models'] : [];

        return new self($provider, array_values(array_filter($models, is_array(...))));
    }
}
