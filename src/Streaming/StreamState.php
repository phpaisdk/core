<?php

declare(strict_types=1);

namespace AiSdk\Streaming;

use AiSdk\FinishReason;
use AiSdk\Support\Json;
use AiSdk\Support\Usage;
use AiSdk\ToolCall;

/**
 * Accumulates streamed parts into final state. Tool-call argument fragments
 * are appended per slot index and decoded once at the end — fixing the bug
 * where each fragment was decoded independently into a broken ToolCall.
 */
final class StreamState
{
    private string $text = '';
    private string $reasoning = '';

    /** @var array<int, array{id: string, name: string, args: string}> */
    private array $toolCalls = [];

    private ?Usage $usage = null;
    private ?FinishReason $finishReason = null;

    /** @var array<string, mixed> */
    private array $providerMetadata = [];

    public function record(StreamPart $part): void
    {
        match (true) {
            $part instanceof TextDeltaPart => $this->text .= $part->text,
            $part instanceof ReasoningDeltaPart => $this->reasoning .= $part->text,
            $part instanceof ToolCallStartPart => $this->startToolCall($part),
            $part instanceof ToolCallDeltaPart => $this->appendToolCall($part),
            $part instanceof FinishPart => $this->finish($part),
            $part instanceof ProviderMetadataPart => $this->mergeMetadata($part),
            default => null,
        };
    }

    public function text(): string
    {
        return $this->text;
    }

    public function reasoning(): ?string
    {
        return $this->reasoning === '' ? null : $this->reasoning;
    }

    public function usage(): Usage
    {
        return $this->usage ?? Usage::empty();
    }

    public function finishReason(): FinishReason
    {
        return $this->finishReason ?? FinishReason::Stop;
    }

    /**
     * @return array<string, mixed>
     */
    public function providerMetadata(): array
    {
        return $this->providerMetadata;
    }

    /**
     * @return array<int, ToolCall>
     */
    public function toolCalls(): array
    {
        $calls = [];
        foreach ($this->toolCalls as $slot) {
            $args = $slot['args'] === '' ? [] : Json::decodeValue($slot['args']);
            $calls[] = new ToolCall($slot['id'], $slot['name'], is_array($args) ? $args : []);
        }

        return $calls;
    }

    private function startToolCall(ToolCallStartPart $part): void
    {
        $this->toolCalls[$part->index] = [
            'id' => $part->id,
            'name' => $part->name,
            'args' => '',
        ];
    }

    private function appendToolCall(ToolCallDeltaPart $part): void
    {
        if (! isset($this->toolCalls[$part->index])) {
            $this->toolCalls[$part->index] = [
                'id' => $part->id ?? '',
                'name' => $part->name ?? '',
                'args' => '',
            ];
        }

        if ($part->id !== null && $this->toolCalls[$part->index]['id'] === '') {
            $this->toolCalls[$part->index]['id'] = $part->id;
        }
        if ($part->name !== null && $this->toolCalls[$part->index]['name'] === '') {
            $this->toolCalls[$part->index]['name'] = $part->name;
        }

        $this->toolCalls[$part->index]['args'] .= $part->argsJson;
    }

    private function finish(FinishPart $part): void
    {
        $this->finishReason = $part->reason;
        $this->usage = $part->usage;
    }

    private function mergeMetadata(ProviderMetadataPart $part): void
    {
        $this->providerMetadata[$part->provider] = array_merge(
            $this->providerMetadata[$part->provider] ?? [],
            $part->metadata,
        );
    }
}
