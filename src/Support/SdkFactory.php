<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Contracts\EmbeddingModelInterface;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Contracts\Model;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Contracts\TranscriptionModelInterface;
use AiSdk\Contracts\VideoModelInterface;
use AiSdk\Exceptions\InvalidArgumentException;
use GuzzleHttp\Client;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Fluent builder for an {@see Sdk}. A safe Guzzle client is used by default;
 * PSR-17 request and stream factories are discovered when not supplied.
 */
final class SdkFactory
{
    private ?ClientInterface $httpClient = null;
    private ?RequestFactoryInterface $requestFactory = null;
    private ?StreamFactoryInterface $streamFactory = null;
    private ?LoggerInterface $logger = null;
    private ?ContainerInterface $container = null;
    private string $userAgent = 'aisdk-php/1.0';
    private ?Model $defaultModel = null;
    private ?Model $defaultTextModel = null;
    private ?Model $defaultImageModel = null;
    private ?Model $defaultSpeechModel = null;
    private ?Model $defaultTranscriptionModel = null;
    private ?Model $defaultEmbeddingModel = null;
    private ?Model $defaultVideoModel = null;
    private float $connectTimeout = 10.0;
    private float $timeout = 120.0;

    public function withHttpClient(ClientInterface $client): self
    {
        $this->httpClient = $client;

        return $this;
    }

    public function withRequestFactory(RequestFactoryInterface $factory): self
    {
        $this->requestFactory = $factory;

        return $this;
    }

    public function withStreamFactory(StreamFactoryInterface $factory): self
    {
        $this->streamFactory = $factory;

        return $this;
    }

    public function withLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function withContainer(ContainerInterface $container): self
    {
        $this->container = $container;

        return $this;
    }

    public function withUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function withConnectTimeout(float $seconds): self
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException('Connect timeout must be greater than zero.');
        }

        $this->connectTimeout = $seconds;

        return $this;
    }

    public function withTimeout(float $seconds): self
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException('Timeout must be greater than zero.');
        }

        $this->timeout = $seconds;

        return $this;
    }

    public function withDefaultModel(Model $model): self
    {
        $this->defaultModel = $model;

        if ($model instanceof TextModelInterface) {
            $this->defaultTextModel = $model;
        }
        if ($model instanceof ImageModelInterface) {
            $this->defaultImageModel = $model;
        }
        if ($model instanceof SpeechModelInterface) {
            $this->defaultSpeechModel = $model;
        }
        if ($model instanceof TranscriptionModelInterface) {
            $this->defaultTranscriptionModel = $model;
        }
        if ($model instanceof EmbeddingModelInterface) {
            $this->defaultEmbeddingModel = $model;
        }
        if ($model instanceof VideoModelInterface) {
            $this->defaultVideoModel = $model;
        }

        return $this;
    }

    public function withDefaultTextModel(Model $model): self
    {
        $this->defaultTextModel = $model;

        return $this;
    }

    public function withDefaultImageModel(Model $model): self
    {
        $this->defaultImageModel = $model;

        return $this;
    }

    public function withDefaultSpeechModel(Model $model): self
    {
        $this->defaultSpeechModel = $model;

        return $this;
    }

    public function withDefaultTranscriptionModel(Model $model): self
    {
        $this->defaultTranscriptionModel = $model;

        return $this;
    }

    public function withDefaultEmbeddingModel(Model $model): self
    {
        $this->defaultEmbeddingModel = $model;

        return $this;
    }

    public function withDefaultVideoModel(Model $model): self
    {
        $this->defaultVideoModel = $model;

        return $this;
    }

    public function defaultModel(): ?Model
    {
        return $this->defaultModel;
    }

    public function defaultTextModel(): ?Model
    {
        return $this->defaultTextModel;
    }

    public function defaultImageModel(): ?Model
    {
        return $this->defaultImageModel;
    }

    public function defaultSpeechModel(): ?Model
    {
        return $this->defaultSpeechModel;
    }

    public function defaultTranscriptionModel(): ?Model
    {
        return $this->defaultTranscriptionModel;
    }

    public function defaultEmbeddingModel(): ?Model
    {
        return $this->defaultEmbeddingModel;
    }

    public function defaultVideoModel(): ?Model
    {
        return $this->defaultVideoModel;
    }

    public function make(): Sdk
    {
        return new Sdk(
            httpClient: $this->httpClient ?? new Client([
                'allow_redirects' => false,
                'connect_timeout' => $this->connectTimeout,
                'timeout' => $this->timeout,
            ]),
            requestFactory: $this->requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            streamFactory: $this->streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            logger: $this->logger,
            container: $this->container,
            userAgent: $this->userAgent,
            defaultModel: $this->defaultModel,
            defaultTextModel: $this->defaultTextModel,
            defaultImageModel: $this->defaultImageModel,
            defaultSpeechModel: $this->defaultSpeechModel,
            defaultTranscriptionModel: $this->defaultTranscriptionModel,
            defaultEmbeddingModel: $this->defaultEmbeddingModel,
            defaultVideoModel: $this->defaultVideoModel,
        );
    }
}
