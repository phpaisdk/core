<?php

declare(strict_types=1);

namespace AiSdk\Live;

use AiSdk\Tool;

/** @phpstan-type ProviderOptions array<string, array<string, mixed>> */
final readonly class LiveRequest
{
    /**
     * @param array<string, mixed> $options
     * @param array<string, array<string, mixed>> $providerOptions
     * @param list<Tool> $tools
     */
    public function __construct(
        public LiveOperation $operation,
        public array $options,
        public array $providerOptions,
        public array $tools,
    ) {}
}
