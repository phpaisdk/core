<?php

declare(strict_types=1);

namespace AiSdk\Support\Concerns;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Reasoning;

trait ConfiguresGeneration
{
    protected ?int $maxTokens = null;
    protected ?float $temperature = null;
    protected ?float $topP = null;
    protected int $maxSteps = 1;
    protected ?Reasoning $reasoning = null;

    public function maxTokens(int $maxTokens): static
    {
        if ($maxTokens < 1) {
            throw new InvalidArgumentException('maxTokens must be at least 1.');
        }

        $this->maxTokens = $maxTokens;

        return $this;
    }

    public function temperature(float $temperature): static
    {
        if (! is_finite($temperature) || $temperature < 0.0) {
            throw new InvalidArgumentException('temperature must be a finite value of at least 0.');
        }

        $this->temperature = $temperature;

        return $this;
    }

    public function topP(float $topP): static
    {
        if (! is_finite($topP) || $topP < 0.0 || $topP > 1.0) {
            throw new InvalidArgumentException('topP must be a finite value between 0 and 1.');
        }

        $this->topP = $topP;

        return $this;
    }

    public function maxSteps(int $maxSteps): static
    {
        $this->maxSteps = max(1, $maxSteps);

        return $this;
    }

    public function reasoning(string|Reasoning $reasoning = Reasoning::EFFORT_MEDIUM): static
    {
        $this->reasoning = is_string($reasoning) ? Reasoning::effort($reasoning) : $reasoning;

        return $this;
    }
}
