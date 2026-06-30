<?php

declare(strict_types=1);

namespace AiSdk;

/**
 * Maps a portable Reasoning configuration to a concrete token budget.
 * Providers that need provider-specific clamping or percentage curves
 * can subclass and override the protected constants.
 */
class ReasoningBudget
{
    /**
     * Effort level to percentage of max budget. Providers can override
     * these values in subclasses for provider-specific behaviour.
     *
     * @var array<string, float>
     */
    protected const EFFORT_PERCENTAGES = [
        Reasoning::EFFORT_MINIMAL => 0.02,
        Reasoning::EFFORT_LOW => 0.10,
        Reasoning::EFFORT_MEDIUM => 0.30,
        Reasoning::EFFORT_HIGH => 0.60,
    ];

    protected const int MIN_BUDGET = 1024;

    protected const float DEFAULT_PERCENTAGE = 0.30;

    /**
     * Calculate the reasoning token budget. Providers should pass their
     * model's maximum output tokens (or a provider-specific cap) as
     * $maxBudget. The result is clamped between $minBudget and $maxBudget.
     */
    public function calculate(Reasoning $reasoning, int $maxBudget, ?int $minBudget = null): int
    {
        $min = $minBudget ?? static::MIN_BUDGET;

        if ($reasoning->budgetTokens !== null) {
            return max($min, min($reasoning->budgetTokens, $maxBudget));
        }

        if ($reasoning->effort !== null) {
            $percentage = static::EFFORT_PERCENTAGES[$reasoning->effort] ?? static::DEFAULT_PERCENTAGE;
            $budget = (int) round($maxBudget * $percentage);

            return max($min, min($budget, $maxBudget));
        }

        return $min;
    }
}
