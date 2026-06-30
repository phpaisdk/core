<?php

declare(strict_types=1);

namespace AiSdk\Support\Concerns;

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
        $this->maxTokens = $maxTokens;

        return $this;
    }

    public function temperature(float $temperature): static
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function topP(float $topP): static
    {
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
