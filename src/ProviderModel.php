<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Contracts\Model;
use AiSdk\Contracts\ResolvableModelInterface;
use Closure;

/**
 * A provider-owned opaque model reference.
 *
 * A model identifier is selected once and resolved to the operation-specific
 * adapter only when an SDK operation needs it.
 */
final readonly class ProviderModel implements ResolvableModelInterface
{
    public function __construct(
        private string $providerName,
        private string $id,
        /** @var Closure(class-string<Model>): Model */
        private Closure $resolver,
    ) {}

    public function specificationVersion(): string
    {
        return 'v1';
    }

    public function provider(): string
    {
        return $this->providerName;
    }

    public function modelId(): string
    {
        return $this->id;
    }

    public function resolve(string $contract): Model
    {
        return ($this->resolver)($contract);
    }
}
