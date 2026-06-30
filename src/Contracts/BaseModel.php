<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\Capability;
use AiSdk\CapabilitySupport;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Generate;
use AiSdk\Support\Sdk;
use AiSdk\Utils\Http\HttpRunner;

/**
 * Shared scaffolding for provider models. Concrete models declare only
 * provider(), modelId(), capabilities(), and the actual generate()/stream().
 */
abstract class BaseModel implements Model
{
    protected const string SPEC_VERSION = 'v1';

    /** @var array<int, Capability> */
    private array $assumedCapabilities = [];

    private bool $allowUnknownCapabilities = false;

    public function specificationVersion(): string
    {
        return static::SPEC_VERSION;
    }

    public function supports(Capability $capability): bool
    {
        return $this->capability($capability)->isSupported();
    }

    public function capability(Capability $capability): CapabilitySupport
    {
        $configured = $this->configuredCapability($capability);
        if ($configured !== null) {
            return $configured;
        }

        if (in_array($capability, $this->capabilities(), true)) {
            return CapabilitySupport::supported($capability, static::class);
        }

        return CapabilitySupport::notSupported($capability);
    }

    /**
     * @param  array<int, Capability>  $capabilities
     */
    public function assume(array $capabilities): static
    {
        $clone = clone $this;
        $clone->assumedCapabilities = self::uniqueCapabilities([
            ...$this->assumedCapabilities,
            ...$capabilities,
        ]);

        return $clone;
    }

    public function allowUnknownCapabilities(): static
    {
        $clone = clone $this;
        $clone->allowUnknownCapabilities = true;

        return $clone;
    }

    /**
     * Resolve the HTTP runner from the optional per-provider SDK override,
     * falling back to the globally configured runtime.
     */
    protected function runner(?Sdk $sdk = null): HttpRunner
    {
        return HttpRunner::fromSdk($sdk ?? Generate::sdk());
    }

    protected function configuredCapability(Capability $capability): ?CapabilitySupport
    {
        if ($this->allowUnknownCapabilities) {
            return CapabilitySupport::supported($capability, 'user-allowed-unknown-capabilities');
        }

        if (in_array($capability, $this->assumedCapabilities, true)) {
            return CapabilitySupport::supported($capability, 'user-assumed');
        }

        return null;
    }

    /**
     * @param  array<int, Capability>  $capabilities
     * @return array<int, Capability>
     */
    protected function configuredCapabilities(array $capabilities): array
    {
        if ($this->allowUnknownCapabilities) {
            return Capability::cases();
        }

        return self::uniqueCapabilities([...$capabilities, ...$this->assumedCapabilities]);
    }

    /**
     * @param  array<int, mixed>  $capabilities
     * @return array<int, Capability>
     */
    private static function uniqueCapabilities(array $capabilities): array
    {
        $unique = [];

        foreach ($capabilities as $capability) {
            if (! $capability instanceof Capability) {
                throw new InvalidArgumentException('Assumed model capabilities must be Capability instances.');
            }

            $unique[$capability->name] = $capability;
        }

        return array_values($unique);
    }
}
