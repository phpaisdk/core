<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Exceptions\InvalidArgumentException;

/**
 * Portable reasoning configuration. Providers decide which effort or budget
 * forms they can map without silently dropping unsupported settings.
 */
final class Reasoning
{
    public const string EFFORT_MINIMAL = 'minimal';
    public const string EFFORT_LOW = 'low';
    public const string EFFORT_MEDIUM = 'medium';
    public const string EFFORT_HIGH = 'high';

    public function __construct(
        public readonly ?string $effort = null,
        public readonly ?int $budgetTokens = null,
    ) {}

    public static function effort(string $effort): self
    {
        $effort = strtolower(trim($effort));

        if (! in_array($effort, [self::EFFORT_MINIMAL, self::EFFORT_LOW, self::EFFORT_MEDIUM, self::EFFORT_HIGH], true)) {
            throw new InvalidArgumentException('Reasoning effort must be one of: minimal, low, medium, high.');
        }

        return new self(effort: $effort);
    }

    public static function budget(int $tokens): self
    {
        if ($tokens < 1) {
            throw new InvalidArgumentException('Reasoning budget tokens must be greater than zero.');
        }

        return new self(budgetTokens: $tokens);
    }

}
