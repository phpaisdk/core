<?php

declare(strict_types=1);

namespace AiSdk;

/**
 * Immutable description of a model's capabilities and metadata. Used by
 * ModelRegistry for runtime model registration so users can register new
 * models without waiting for a package release.
 */
final class ModelDefinition
{
    /**
     * @param  string  $id  Model identifier (e.g. 'gpt-4o', 'claude-sonnet-4').
     * @param  array<int, Capability>  $capabilities  Capabilities the model natively supports.
     * @param  array<string, array<string, mixed>>  $adaptedCapabilities  Capabilities simulated by the provider adapter, keyed by capability name.
     * @param  array<string, mixed>  $metadata  Provider-specific metadata (limits, pricing, etc.).
     */
    public function __construct(
        public readonly string $id,
        public readonly array $capabilities = [],
        public readonly array $adaptedCapabilities = [],
        public readonly array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  Raw model definition data (from JSON catalog or runtime).
     */
    public static function fromArray(array $data): self
    {
        $id = is_string($data['id'] ?? null) ? (string) $data['id'] : '';

        $capabilities = [];
        if (isset($data['capabilities']) && is_array($data['capabilities'])) {
            foreach ($data['capabilities'] as $name) {
                if (is_string($name)) {
                    $capability = Capability::fromName($name);
                    if ($capability !== null) {
                        $capabilities[] = $capability;
                    }
                }
            }
        }

        $adapted = [];
        if (isset($data['adapted_capabilities']) && is_array($data['adapted_capabilities'])) {
            foreach ($data['adapted_capabilities'] as $name => $info) {
                if (is_string($name) && is_array($info)) {
                    $adapted[$name] = $info;
                }
            }
        }

        $metadata = array_filter([
            'status' => $data['status'] ?? null,
            'modalities' => $data['modalities'] ?? null,
            'limits' => $data['limits'] ?? null,
            'pricing' => $data['pricing'] ?? null,
            'released_at' => $data['released_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
            'lab' => $data['lab'] ?? null,
        ], fn($v) => $v !== null);

        return new self($id, $capabilities, $adapted, $metadata);
    }

    /**
     * @return array<int, string>
     */
    public function capabilityNames(): array
    {
        $names = array_map(
            fn(Capability $c) => $c->name(),
            $this->capabilities,
        );

        return array_values(array_unique(array_merge($names, array_keys($this->adaptedCapabilities))));
    }
}
