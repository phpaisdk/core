<?php

declare(strict_types=1);

namespace AiSdk;

final class CapabilitySupport
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        public readonly Capability $capability,
        public readonly CapabilitySupportState $state,
        public readonly ?string $reason = null,
        public readonly ?string $source = null,
        public readonly ?string $strategy = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function supported(Capability $capability, ?string $source = null, array $metadata = []): self
    {
        return new self($capability, CapabilitySupportState::Supported, source: $source, metadata: $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function notSupported(Capability $capability, ?string $reason = null, array $metadata = []): self
    {
        return new self($capability, CapabilitySupportState::NotSupported, reason: $reason, metadata: $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function adapted(Capability $capability, string $strategy, ?string $source = null, array $metadata = []): self
    {
        return new self($capability, CapabilitySupportState::Adapted, source: $source, strategy: $strategy, metadata: $metadata);
    }

    public function isSupported(): bool
    {
        return $this->state !== CapabilitySupportState::NotSupported;
    }
}
