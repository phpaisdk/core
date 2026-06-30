<?php

declare(strict_types=1);

namespace AiSdk\Support;

use AiSdk\Contracts\Model;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Configured runtime for the SDK. Holds the discovered PSR collaborators and
 * an optional default model. Immutable: withDefaultModel() returns a copy.
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
        );
    }
}
