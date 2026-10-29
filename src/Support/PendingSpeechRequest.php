<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Contracts\Model;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Requests\SpeechRequest;
use AiSdk\Results\AudioResult;

final class PendingSpeechRequest
{
    use \AiSdk\Support\Concerns\ObservesModelOperations;

    private ?string $input = null;
    private ?string $voice = null;
    private ?string $format = null;

    /** @var array<string, array<string, mixed>> */
    private array $providerOptions = [];

    public function __construct(private ?Model $model = null) {}

    public function model(Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function input(string $input): self
    {
        $this->input = $input;

        return $this;
    }

    public function voice(string $voice): self
    {
        $this->voice = $voice;

        return $this;
    }

    public function format(string $format): self
    {
        $format = strtolower($format);
        if (! in_array($format, ['mp3', 'opus', 'aac', 'flac', 'wav', 'pcm'], true)) {
            throw new InvalidArgumentException('Speech format must be one of: mp3, opus, aac, flac, wav, pcm.');
        }

        $this->format = $format;

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

    public function run(): AudioResult
    {
        $request = $this->request();

        $model = ModelResolver::resolve($this->model, SpeechModelInterface::class, 'Generate::speech()');

        $response = $this->observeOperation($model, 'speech', fn() => $model->generate($request));

        return new AudioResult(
            output: $response->audio,
            usage: $response->usage,
            rawResponse: $response->rawResponse,
            providerMetadata: $response->providerMetadata,
        );
    }

    private function request(): SpeechRequest
    {
        $input = trim((string) $this->input);
        if ($input === '') {
            throw new InvalidArgumentException('Generate::speech() requires input text.');
        }

        return new SpeechRequest(
            input: $input,
            voice: $this->voice,
            format: $this->format,
            providerOptions: $this->providerOptions,
        );
    }
}
