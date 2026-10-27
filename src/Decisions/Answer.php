<?php

declare(strict_types=1);

namespace AiSdk\Decisions;

/** Typed values preserve probability, uncertainty and refusal without boolean coercion. */
final readonly class Answer
{
    /** @param array<string|int, float> $probabilities */
    public function __construct(
        public string $kind,
        public string|float|null $value,
        public ?float $confidence = null,
        public array $probabilities = [],
    ) {
        if (! in_array($kind, ['predicate', 'choice', 'score', 'refusal'], true)) {
            throw new \UnexpectedValueException('Unknown decision answer type.');
        }
        if (($kind === 'refusal' && $value !== null) || ($kind === 'choice' && ! is_string($value)) || (in_array($kind, ['predicate', 'score'], true) && (! is_float($value) || ! is_finite($value)))) {
            throw new \UnexpectedValueException('Decision answer value does not match its type.');
        }
        if ($kind === 'predicate') {
            self::probability($value);
        }
        if ($confidence !== null) {
            self::probability($confidence);
        }
        foreach ($probabilities as $probability) {
            self::probability($probability);
        }
    }

    public function yes(float $threshold = 0.5): bool
    {
        self::probability($threshold);
        if ($this->kind !== 'predicate' || ! is_float($this->value)) {
            throw new \LogicException('Only a non-refused predicate can be evaluated as yes/no.');
        }

        return $this->value >= $threshold;
    }

    public function probabilityOf(string|int $option): float
    {
        return $this->probabilities[$option] ?? throw new \OutOfBoundsException('No probability for this option.');
    }

    public static function probability(float $value): void
    {
        if (! is_finite($value) || $value < 0 || $value > 1) {
            throw new \UnexpectedValueException('Probabilities must be finite values between 0 and 1.');
        }
    }
}
