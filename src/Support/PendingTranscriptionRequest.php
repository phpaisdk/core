<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Content;
use AiSdk\Contracts\Model;
use AiSdk\Contracts\TranscriptionModelInterface;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Requests\TranscriptionRequest;
use AiSdk\Results\TranscriptResult;

final class PendingTranscriptionRequest
{
    private ?Content $audio = null;

    /** @var array<string, array<string, mixed>> */
    private array $providerOptions = [];

    public function __construct(private ?Model $model = null) {}

    public function model(Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function audio(Content $audio): self
    {
        if ($audio->type !== Content::TYPE_AUDIO) {
            throw new InvalidArgumentException('Generate::transcription() requires audio content.');
        }

        $this->audio = $audio;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function providerOptions(string $provider, array $options): self
    {
        $this->providerOptions[$provider] = array_replace_recursive(
            $this->providerOptions[$provider] ?? [],
            $options,
        );

        return $this;
    }

    public function run(): TranscriptResult
    {
        if ($this->audio === null) {
            throw new InvalidArgumentException('Generate::transcription() requires audio content.');
        }

        $model = ModelResolver::resolve($this->model, TranscriptionModelInterface::class, 'Generate::transcription()');
        $response = $model->transcribe(new TranscriptionRequest(
            audio: $this->audio,
            providerOptions: $this->providerOptions,
        ));

        return new TranscriptResult(
            output: $response->transcript,
            usage: $response->usage,
            rawResponse: $response->rawResponse,
            providerMetadata: $response->providerMetadata,
        );
    }
}
