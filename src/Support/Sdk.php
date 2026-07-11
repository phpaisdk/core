<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Contracts\Model;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Contracts\TextModelInterface;
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
        public readonly ?TextModelInterface $defaultTextModel = null,
        public readonly ?ImageModelInterface $defaultImageModel = null,
        public readonly ?SpeechModelInterface $defaultSpeechModel = null,
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
        );
    }

    public function withDefaultTextModel(TextModelInterface $model): self
    {
        return $this->withDefaultModel($model);
    }

    public function withDefaultImageModel(ImageModelInterface $model): self
    {
        return $this->withDefaultModel($model);
    }

    public function withDefaultSpeechModel(SpeechModelInterface $model): self
    {
        return $this->withDefaultModel($model);
    }

    public function text(?string $prompt = null): PendingTextRequest
    {
        $request = new PendingTextRequest($this->defaultTextModel, $this->container);

        return $prompt === null ? $request : $request->prompt($prompt);
    }

    public function image(?string $prompt = null): PendingImageRequest
    {
        $request = new PendingImageRequest($this->defaultImageModel);

        return $prompt === null ? $request : $request->prompt($prompt);
    }

    public function speech(?string $input = null): PendingSpeechRequest
    {
        $request = new PendingSpeechRequest($this->defaultSpeechModel);

        return $input === null ? $request : $request->input($input);
    }
}
