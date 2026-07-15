<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Content;
use AiSdk\Contracts\EmbeddingModelInterface;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Contracts\Model;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Contracts\TranscriptionModelInterface;
use AiSdk\Contracts\VideoModelInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Configured runtime for the SDK. Holds its PSR collaborators and independent
 * defaults for each modality. Immutable: withDefaultModel() returns a copy.
 */
final class Sdk
{
    public function __construct(
        public readonly ClientInterface $httpClient,
        public readonly RequestFactoryInterface $requestFactory,
        public readonly StreamFactoryInterface $streamFactory,
        public readonly ?LoggerInterface $logger = null,
        public readonly ?ContainerInterface $container = null,
        public readonly string $userAgent = 'aisdk-php/1.0',
        public readonly ?Model $defaultModel = null,
        public readonly ?Model $defaultTextModel = null,
        public readonly ?Model $defaultImageModel = null,
        public readonly ?Model $defaultSpeechModel = null,
        public readonly ?Model $defaultEmbeddingModel = null,
        public readonly ?Model $defaultVideoModel = null,
        public readonly ?Model $defaultTranscriptionModel = null,
    ) {}

    public function withDefaultModel(Model $model): self
    {
        return new self(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
            logger: $this->logger,
            container: $this->container,
            userAgent: $this->userAgent,
            defaultModel: $model,
            defaultTextModel: $model instanceof TextModelInterface ? $model : $this->defaultTextModel,
            defaultImageModel: $model instanceof ImageModelInterface ? $model : $this->defaultImageModel,
            defaultSpeechModel: $model instanceof SpeechModelInterface ? $model : $this->defaultSpeechModel,
            defaultTranscriptionModel: $model instanceof TranscriptionModelInterface ? $model : $this->defaultTranscriptionModel,
            defaultEmbeddingModel: $model instanceof EmbeddingModelInterface ? $model : $this->defaultEmbeddingModel,
            defaultVideoModel: $model instanceof VideoModelInterface ? $model : $this->defaultVideoModel,
        );
    }

    public function withDefaultTextModel(Model $model): self
    {
        return new self(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
            logger: $this->logger,
            container: $this->container,
            userAgent: $this->userAgent,
            defaultModel: $this->defaultModel,
            defaultTextModel: $model,
            defaultImageModel: $this->defaultImageModel,
            defaultSpeechModel: $this->defaultSpeechModel,
            defaultTranscriptionModel: $this->defaultTranscriptionModel,
            defaultEmbeddingModel: $this->defaultEmbeddingModel,
            defaultVideoModel: $this->defaultVideoModel,
        );
    }

    public function withDefaultImageModel(Model $model): self
    {
        return new self(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
            logger: $this->logger,
            container: $this->container,
            userAgent: $this->userAgent,
            defaultModel: $this->defaultModel,
            defaultTextModel: $this->defaultTextModel,
            defaultImageModel: $model,
            defaultSpeechModel: $this->defaultSpeechModel,
            defaultTranscriptionModel: $this->defaultTranscriptionModel,
            defaultEmbeddingModel: $this->defaultEmbeddingModel,
            defaultVideoModel: $this->defaultVideoModel,
        );
    }

    public function withDefaultSpeechModel(Model $model): self
    {
        return new self(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
            logger: $this->logger,
            container: $this->container,
            userAgent: $this->userAgent,
            defaultModel: $this->defaultModel,
            defaultTextModel: $this->defaultTextModel,
            defaultImageModel: $this->defaultImageModel,
            defaultSpeechModel: $model,
            defaultTranscriptionModel: $this->defaultTranscriptionModel,
            defaultEmbeddingModel: $this->defaultEmbeddingModel,
            defaultVideoModel: $this->defaultVideoModel,
        );
    }

    public function withDefaultTranscriptionModel(Model $model): self
    {
        return new self(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
            logger: $this->logger,
            container: $this->container,
            userAgent: $this->userAgent,
            defaultModel: $this->defaultModel,
            defaultTextModel: $this->defaultTextModel,
            defaultImageModel: $this->defaultImageModel,
            defaultSpeechModel: $this->defaultSpeechModel,
            defaultTranscriptionModel: $model,
            defaultEmbeddingModel: $this->defaultEmbeddingModel,
            defaultVideoModel: $this->defaultVideoModel,
        );
    }

    public function withDefaultEmbeddingModel(Model $model): self
    {
        return new self(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
            logger: $this->logger,
            container: $this->container,
            userAgent: $this->userAgent,
            defaultModel: $this->defaultModel,
            defaultTextModel: $this->defaultTextModel,
            defaultImageModel: $this->defaultImageModel,
            defaultSpeechModel: $this->defaultSpeechModel,
            defaultTranscriptionModel: $this->defaultTranscriptionModel,
            defaultEmbeddingModel: $model,
            defaultVideoModel: $this->defaultVideoModel,
        );
    }

    public function withDefaultVideoModel(Model $model): self
    {
        return new self(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
            logger: $this->logger,
            container: $this->container,
            userAgent: $this->userAgent,
            defaultModel: $this->defaultModel,
            defaultTextModel: $this->defaultTextModel,
            defaultImageModel: $this->defaultImageModel,
            defaultSpeechModel: $this->defaultSpeechModel,
            defaultTranscriptionModel: $this->defaultTranscriptionModel,
            defaultEmbeddingModel: $this->defaultEmbeddingModel,
            defaultVideoModel: $model,
        );
    }

    public function video(?string $prompt = null): PendingVideoRequest
    {
        $request = new PendingVideoRequest($this->defaultVideoModel ?? $this->defaultModel);

        return $prompt === null ? $request : $request->prompt($prompt);
    }

    public function text(?string $prompt = null): PendingTextRequest
    {
        $request = new PendingTextRequest($this->defaultTextModel ?? $this->defaultModel, $this->container);

        return $prompt === null ? $request : $request->prompt($prompt);
    }

    public function image(?string $prompt = null): PendingImageRequest
    {
        $request = new PendingImageRequest($this->defaultImageModel ?? $this->defaultModel);

        return $prompt === null ? $request : $request->prompt($prompt);
    }

    public function speech(?string $input = null): PendingSpeechRequest
    {
        $request = new PendingSpeechRequest($this->defaultSpeechModel ?? $this->defaultModel);

        return $input === null ? $request : $request->input($input);
    }

    public function transcription(?Content $audio = null): PendingTranscriptionRequest
    {
        $request = new PendingTranscriptionRequest($this->defaultTranscriptionModel ?? $this->defaultModel);

        return $audio === null ? $request : $request->audio($audio);
    }

    /**
     * @param  string|array<int, string>|null  $input
     */
    public function embedding(string|array|null $input = null): PendingEmbeddingRequest
    {
        $request = new PendingEmbeddingRequest($this->defaultEmbeddingModel ?? $this->defaultModel);

        return $input === null ? $request : $request->input($input);
    }
}
