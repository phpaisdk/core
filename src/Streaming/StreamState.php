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

    /** @var array<int, int> */
    private array $activeToolCallSlots = [];

    private int $nextToolCall = 0;

    private ?Usage $usage = null;
    private ?FinishReason $finishReason = null;

    private \AiSdk\Responses\ResponseMetadata $response;

    public function response(): \AiSdk\Responses\ResponseMetadata
    {
        return $this->response ?? new \AiSdk\Responses\ResponseMetadata();
    }

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
        $key = $this->nextToolCall++;
        $this->activeToolCallSlots[$part->index] = $key;
        $this->toolCalls[$key] = [
            'id' => $part->id,
            'name' => $part->name,
            'args' => '',
        ];
    }

    private function appendToolCall(ToolCallDeltaPart $part): void
    {
        $key = $this->activeToolCallSlots[$part->index] ?? null;

        if ($key === null) {
            $key = $this->nextToolCall++;
            $this->activeToolCallSlots[$part->index] = $key;
            $this->toolCalls[$key] = [
                'id' => $part->id ?? '',
                'name' => $part->name ?? '',
                'args' => '',
            ];
        }

        if ($part->id !== null && $this->toolCalls[$key]['id'] === '') {
            $this->toolCalls[$key]['id'] = $part->id;
        }
        if ($part->name !== null && $this->toolCalls[$key]['name'] === '') {
            $this->toolCalls[$key]['name'] = $part->name;
        }

        $this->toolCalls[$key]['args'] .= $part->argsJson;
    }

    private function finish(FinishPart $part): void
    {
        $this->response = $part->response;
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
