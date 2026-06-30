<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\Capability;
use AiSdk\CapabilitySupport;

interface Model
{
    public function specificationVersion(): string;

    public function provider(): string;

    public function modelId(): string;

    public function supports(Capability $capability): bool;

    public function capability(Capability $capability): CapabilitySupport;

    /**
     * @param  array<int, Capability>  $capabilities
     */
    public function assume(array $capabilities): static;

    public function allowUnknownCapabilities(): static;

    /**
     * @return array<int, Capability>
     */
    public function capabilities(): array;
}
