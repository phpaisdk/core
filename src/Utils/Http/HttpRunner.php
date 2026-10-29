<?php

declare(strict_types=1);

namespace AiSdk\Utils\Http;

use AiSdk\Exceptions\AiSdkException;
use AiSdk\Exceptions\APIConnectionException;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Support\Json;
use AiSdk\Support\Sdk;
use AiSdk\Utils\Errors\HttpErrorNormalizer;
use AiSdk\Utils\Errors\Redactor;
use AiSdk\Utils\Stream\SseParser;
use Generator;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
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
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function getJson(string $url, array $headers = [], string $provider = 'provider'): array
    {
        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $response = $this->send($request);
        $this->ensureSuccess($response, $provider);

        return Json::decode((string) $response->getBody(), $provider);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    public function postRaw(string $url, array $body, array $headers = [], string $provider = 'provider'): ResponseInterface
    {
        $response = $this->send($this->prepareJson('POST', $url, $body, $headers));
        $this->ensureSuccess($response, $provider);

        return $response;
    }

    public function sendRequest(RequestInterface $request, string $provider = 'provider'): ResponseInterface
    {
        if (! $request->hasHeader('User-Agent')) {
            $request = $request->withHeader('User-Agent', $this->userAgent);
        }

        $response = $this->send($request);
        $this->ensureSuccess($response, $provider);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @return Generator<int, array{event: ?string, data: string}>
     */
    public function postStream(string $url, array $body, array $headers = [], string $provider = 'provider'): Generator
    {
        $headers = array_replace(['Accept' => 'text/event-stream'], $headers);
        $response = $this->send($this->prepareJson('POST', $url, $body, $headers), stream: true);
        $this->ensureSuccess($response, $provider);
        $this->ensureEventStream($response, $provider);

        try {
            yield from SseParser::parseStream($response->getBody());
        } catch (AiSdkException $e) {
            throw $e;
        } catch (\Exception $e) {
            // The connection broke while the body was being read (reset, timeout, closed early).
            throw new APIConnectionException(
                message: 'The connection was interrupted while streaming the response: ' . Redactor::text($e->getMessage()),
                context: ['provider' => $provider],
                previous: $e,
            );
        }
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

    private function send(RequestInterface $request, bool $stream = false): ResponseInterface
    {
        try {
            if ($stream && $this->client instanceof GuzzleClientInterface) {
                return $this->client->send($request, [
                    'allow_redirects' => false,
                    'http_errors' => false,
                    'stream' => true,
                ]);
            }

            return $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new APIConnectionException(
                message: 'HTTP transport error: ' . Redactor::text($e->getMessage()),
                context: ['url' => Redactor::url((string) $request->getUri())],
                previous: $e,
            );
        } catch (\Throwable $e) {
            // A decorating client (for example one with retries) may already have produced a normalized error.
            // PSR-18 requires clients to throw ClientExceptionInterface; some do not. Errors (TypeError and
            // friends) are programmer mistakes and still propagate.
            if ($e instanceof AiSdkException || $e instanceof \Error) {
                throw $e;
            }

            throw new APIConnectionException(
                message: 'HTTP client failure (' . $e::class . '): ' . Redactor::text($e->getMessage()),
                context: ['url' => Redactor::url((string) $request->getUri())],
                previous: $e,
            );
        }
    }

    /**
     * A streaming endpoint that answers 200 with a JSON document or an HTML page (a gateway error, a login
     * wall, a misrouted request) would otherwise parse as an empty stream and look like a blank answer.
     */
    private function ensureEventStream(ResponseInterface $response, string $provider): void
    {
        $type = strtolower($response->getHeaderLine('content-type'));

        if (! str_contains($type, 'application/json') && ! str_contains($type, 'text/html')) {
            return;
        }

        $body = $response->getBody();
        $text = '';
        while (! $body->eof() && strlen($text) < 65536) {
            $text .= $body->read(8192);
        }

        $decoded = json_decode($text, true);

        throw HttpErrorNormalizer::normalize(
            provider: $provider,
            status: 502,
            body: is_array($decoded) ? $decoded : $text,
            requestId: $response->getHeaderLine('x-request-id') ?: null,
            previous: InvalidResponseException::forProvider($provider, "Expected an event stream but received {$type}."),
        );
    }

    /** Retry-After is either delay-seconds or an HTTP-date. */
    private static function retryAfterSeconds(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        $at = strtotime($value);

        return $at === false ? null : max(0, $at - time());
    }

    private function ensureSuccess(ResponseInterface $response, string $provider): void
    {
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            return;
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        $requestId = $response->getHeaderLine('x-request-id')
            ?: $response->getHeaderLine('request-id')
            ?: $response->getHeaderLine('anthropic-request-id')
            ?: null;

        $retryAfter = self::retryAfterSeconds($response->getHeaderLine('retry-after'));

        throw HttpErrorNormalizer::normalize(
            provider: $provider,
            status: $response->getStatusCode(),
            body: is_array($decoded) ? $decoded : $body,
            requestId: $requestId,
            retryAfter: $retryAfter,
        );
    }
}
