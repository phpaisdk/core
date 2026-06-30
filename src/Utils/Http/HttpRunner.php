<?php

declare(strict_types=1);

namespace AiSdk\Utils\Http;

use AiSdk\Exceptions\APIConnectionException;
use AiSdk\Support\Json;
use AiSdk\Support\Sdk;
use AiSdk\Utils\Errors\HttpErrorNormalizer;
use AiSdk\Utils\Stream\SseParser;
use Generator;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Thin HTTP runner providers use to dispatch JSON and SSE requests through
 * whatever PSR-18 client the application configured.
 */
final class HttpRunner
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $userAgent = 'aisdk-php/1.0',
    ) {}

    public static function fromSdk(Sdk $sdk): self
    {
        return new self(
            client: $sdk->httpClient,
            requestFactory: $sdk->requestFactory,
            streamFactory: $sdk->streamFactory,
            userAgent: $sdk->userAgent,
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function postJson(string $url, array $body, array $headers = [], string $provider = 'provider'): array
    {
        $response = $this->send($this->prepareJson('POST', $url, $body, $headers));
        $this->ensureSuccess($response, $provider);

        return Json::decode((string) $response->getBody(), $provider);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @return Generator<int, array{event: ?string, data: string}>
     */
    public function postStream(string $url, array $body, array $headers = [], string $provider = 'provider'): Generator
    {
        $headers = array_replace(['Accept' => 'text/event-stream'], $headers);
        $response = $this->send($this->prepareJson('POST', $url, $body, $headers));
        $this->ensureSuccess($response, $provider);

        yield from SseParser::parseStream($response->getBody());
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    public function prepareJson(string $method, string $url, array $body, array $headers = []): RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $url)
            ->withBody($this->streamFactory->createStream(Json::encode($body)))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    private function send(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new APIConnectionException(
                message: 'HTTP transport error: ' . $e->getMessage(),
                context: ['url' => (string) $request->getUri()],
                previous: $e,
            );
        }
    }

    private function ensureSuccess(ResponseInterface $response, string $provider): void
    {
        if ($response->getStatusCode() < 400) {
            return;
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        $requestId = $response->getHeaderLine('x-request-id')
            ?: $response->getHeaderLine('request-id')
            ?: $response->getHeaderLine('anthropic-request-id')
            ?: null;

        $retryAfter = $response->getHeaderLine('retry-after');

        throw HttpErrorNormalizer::normalize(
            provider: $provider,
            status: $response->getStatusCode(),
            body: is_array($decoded) ? $decoded : $body,
            requestId: $requestId,
            retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
        );
    }
}
