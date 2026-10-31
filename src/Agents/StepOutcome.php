<?php

declare(strict_types=1);

namespace AiSdk\Agents;

use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\FinishReason;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Responses\Parts\ResponsePart;
use AiSdk\Responses\Parts\ToolCallPart;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Support\Usage;

final readonly class StepOutcome
{
    public function __construct(
        public TextModelResponse $response,
        public bool $stop = false,
        public bool $stepLimitReached = false,
        public ?TextModelRequest $request = null,
    ) {
        if (count($response->parts) > 4096) {
            throw new InvalidResponseException('A step outcome cannot contain more than 4096 response parts.');
        }
        $ids = [];
        foreach ($response->parts as $part) {
            if (! $part instanceof ResponsePart) {
                throw new InvalidResponseException('Step outcomes require typed response parts.');
            }
            if ($part instanceof ToolCallPart) {
                if ($part->id === '' || $part->name === '' || isset($ids[$part->id])) {
                    throw new InvalidResponseException('Step outcome tool calls require unique nonempty ids and nonempty names.');
                }
                $ids[$part->id] = true;
            }
        }
        $usage = $response->usage;
        if ($usage->inputTokens < 0 || $usage->outputTokens < 0 || $usage->totalTokens < 0
            || ($usage->reasoningTokens ?? 0) < 0 || ($usage->cachedInputTokens ?? 0) < 0 || ($usage->cacheWriteInputTokens ?? 0) < 0) {
            throw new InvalidResponseException('Step outcome usage cannot be negative.');
        }
    }

    /** @param array<int, mixed> $parts */
    public static function fromParts(array $parts, FinishReason $finishReason = FinishReason::Stop, Usage $usage = new Usage()): self
    {
        // Validate before constructing the typed response: malformed middleware data is always an SDK error.
        foreach ($parts as $part) {
            if (! $part instanceof ResponsePart) {
                throw new InvalidResponseException('Step outcomes require typed response parts.');
            }
        }

        return new self(new TextModelResponse($parts, $finishReason, $usage));
    }

    public function stopped(): self
    {
        return new self($this->response, true, $this->stepLimitReached, $this->request);
    }

    public function budgetReached(): self
    {
        return new self($this->response, $this->stop, true, $this->request);
    }

    public function withRequest(TextModelRequest $request): self
    {
        return new self($this->response, $this->stop, $this->stepLimitReached, $request);
    }
}
