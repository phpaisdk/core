<?php

declare(strict_types=1);

namespace AiSdk\Results;

use AiSdk\FinishReason;
use AiSdk\Support\Usage;
use AiSdk\ToolCall;
use AiSdk\ToolResult;

final class TextResult extends Result
{
    /**
     * @param  array<int, ToolCall>  $toolCalls
     * @param  array<int, ToolResult>  $toolResults
     * @param  array<string, mixed>  $rawResponse
     * @param  array<string, mixed>  $providerMetadata
     */
    public function __construct(
        public readonly string $text,
        public readonly ?string $reasoning = null,
        public readonly mixed $output = null,
        public readonly array $toolCalls = [],
        public readonly array $toolResults = [],
        public readonly FinishReason $finishReason = FinishReason::Stop,
        Usage $usage = new Usage(),
        public readonly array $rawResponse = [],
        array $providerMetadata = [],
    ) {
        parent::__construct($usage, $providerMetadata);
    }
}
