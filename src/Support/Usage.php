<?php

declare(strict_types=1);

namespace AiSdk\Support;

/**
 * Token counts for one call.
 *
 * $inputTokens is the whole prompt, including tokens read from or written to a
 * prompt cache; $cachedInputTokens and $cacheWriteInputTokens are subsets of it.
 */
final class Usage
{
    public readonly int $totalTokens;

    public function __construct(
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        ?int $totalTokens = null,
        public readonly ?int $reasoningTokens = null,
        public readonly ?int $cachedInputTokens = null,
        public readonly ?int $cacheWriteInputTokens = null,
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
            cacheWriteInputTokens: self::sumNullable($this->cacheWriteInputTokens, $usage->cacheWriteInputTokens),
        );
    }

    /**
     * Share of the prompt served from cache, or null when no cache data was reported.
     */
    public function cacheHitRate(): ?float
    {
        if ($this->cachedInputTokens === null || $this->inputTokens === 0) {
            return null;
        }

        return $this->cachedInputTokens / $this->inputTokens;
    }

    private static function sumNullable(?int $left, ?int $right): ?int
    {
        if ($left === null && $right === null) {
            return null;
        }

        return ($left ?? 0) + ($right ?? 0);
    }
}
