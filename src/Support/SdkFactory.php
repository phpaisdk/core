<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Contracts\Model;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Fluent builder for an {@see Sdk}. Mirrors the with*()/make() pattern from
 * the reference API clients. PSR collaborators are auto-discovered if unset.
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

    public function withDefaultModel(Model $model): self
    {
        $this->defaultModel = $model;

        return $this;
    }

    public function defaultModel(): ?Model
    {
        return $this->defaultModel;
    }

    public function make(): Sdk
    {
        return new Sdk(
            httpClient: $this->httpClient ?? Psr18ClientDiscovery::find(),
            requestFactory: $this->requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            streamFactory: $this->streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            logger: $this->logger,
            container: $this->container,
            userAgent: $this->userAgent,
            defaultModel: $this->defaultModel,
        );
    }
}
