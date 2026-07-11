<?php

declare(strict_types=1);

namespace AiSdk\Support\Concerns;

/**
 * Provider-namespaced options plus a single per-provider "raw" escape hatch.
 */
trait HasProviderOptions
{
    /** @var array<string, array<string, mixed>> */
    protected array $providerOptions = [];

    /**
     * @param  array<string, mixed>  $options
     */
    public function providerOptions(string $provider, array $options): static
    {
        $this->providerOptions[$provider] = array_replace(
            $this->providerOptions[$provider] ?? [],
            $options,
        );

        return $this;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function readProviderOptions(): array
    {
        return $this->providerOptions;
    }
}
