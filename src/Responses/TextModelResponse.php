<?php

declare(strict_types=1);

namespace AiSdk\Responses;

use AiSdk\FinishReason;
use AiSdk\Responses\Parts\ReasoningPart;
use AiSdk\Responses\Parts\ResponsePart;
use AiSdk\Responses\Parts\TextPart;
use AiSdk\Responses\Parts\ToolCallPart;
use AiSdk\Support\Usage;
use AiSdk\ToolCall;

/**
 * Normalized text-generation response. Providers emit typed {@see ResponsePart}
 * instances; this object never guesses array key names.
 */
final class TextModelResponse
{
    /**
     * @param  array<int, ResponsePart>  $parts
     * @param  array<string, mixed>  $rawResponse
     * @param  array<string, mixed>  $providerMetadata
     */
    public function __construct(
        public readonly array $parts,
        public readonly FinishReason $finishReason,
        public readonly Usage $usage,
        public readonly array $rawResponse = [],
        public readonly array $providerMetadata = [],
    ) {}

    public function text(): string
    {
        $text = '';
        foreach ($this->parts as $part) {
            if ($part instanceof TextPart) {
                $text .= $part->text;
            }
        }

        return $text;
    }

    public function reasoning(): ?string
    {
        $text = '';
        foreach ($this->parts as $part) {
            if ($part instanceof ReasoningPart) {
                $text .= $part->text;
            }
        }

        return $text === '' ? null : $text;
    }

    /**
     * @return array<int, ToolCall>
     */
    public function toolCalls(): array
    {
        $calls = [];
        foreach ($this->parts as $part) {
            if ($part instanceof ToolCallPart) {
                $calls[] = $part->toolCall();
            }
        }

        return $calls;
    }
}
