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

    public function add(self $usage): self
    {
        return new self(
            inputTokens: $this->inputTokens + $usage->inputTokens,
            outputTokens: $this->outputTokens + $usage->outputTokens,
            totalTokens: $this->totalTokens + $usage->totalTokens,
            reasoningTokens: self::sumNullable($this->reasoningTokens, $usage->reasoningTokens),
            cachedInputTokens: self::sumNullable($this->cachedInputTokens, $usage->cachedInputTokens),
        );
    }

    private static function sumNullable(?int $left, ?int $right): ?int
    {
        if ($left === null && $right === null) {
            return null;
        }

        return ($left ?? 0) + ($right ?? 0);
    }
}
