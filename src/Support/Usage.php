<?php

declare(strict_types=1);

namespace AiSdk\Support;

final class Usage
{
    public readonly int $totalTokens;

    public function __construct(
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        ?int $totalTokens = null,
        public readonly ?int $reasoningTokens = null,
        public readonly ?int $cachedInputTokens = null,
    ) {
        $this->totalTokens = $totalTokens ?? ($inputTokens + $outputTokens);
    }

    public static function empty(): self
    {
        return new self(0, 0, 0);
    }
}
