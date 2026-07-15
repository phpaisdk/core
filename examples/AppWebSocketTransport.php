<?php

declare(strict_types=1);

namespace App\Ai;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\TransportException;
use AiSdk\Live\Contracts\TransportConnectionInterface;
use AiSdk\Live\Contracts\TransportInterface;
use AiSdk\Live\TransportEndpoint;
use AiSdk\Live\TransportFrame;
use AiSdk\Live\TransportFrameType;
use AiSdk\Live\WebSocketEndpoint;
use Amp\Websocket\Client\Rfc6455ConnectionFactory;
use Amp\Websocket\Client\Rfc6455Connector;
use Amp\Websocket\Client\WebsocketConnection as AmpWebsocketConnection;
use Amp\Websocket\Client\WebsocketHandshake;
use Amp\Websocket\Parser\Rfc6455ParserFactory;
use Amp\Websocket\WebsocketCloseCode;
use Throwable;

/**
 * Complete core-only example. Install amphp/websocket-client:^2.0 and pass an
 * instance to Live::voice(), Live::transcribe(), or Live::translate().
 */
final readonly class AppWebSocketTransport implements TransportInterface
{
    public function __construct(private int $maximumMessageBytes = 16_777_216)
    {
        if ($this->maximumMessageBytes < 1) {
            throw new InvalidArgumentException('The maximum WebSocket message size must be at least one byte.');
        }
    }

    public function supports(TransportEndpoint $endpoint): bool
    {
        return $endpoint instanceof WebSocketEndpoint;
    }

    public function connect(TransportEndpoint $endpoint): TransportConnectionInterface
    {
        if (! $endpoint instanceof WebSocketEndpoint) {
            throw new InvalidArgumentException('AppWebSocketTransport requires a WebSocketEndpoint.');
        }

        $headers = $endpoint->headers;
        if ($endpoint->subprotocols !== []) {
            $headers['Sec-WebSocket-Protocol'] = implode(', ', $endpoint->subprotocols);
        }

        try {
            $parserLimit = max(125, $this->maximumMessageBytes);
            $connector = new Rfc6455Connector(
                connectionFactory: new Rfc6455ConnectionFactory(
                    parserFactory: new Rfc6455ParserFactory(
                        messageSizeLimit: $parserLimit,
                        frameSizeLimit: $parserLimit,
                    ),
                ),
            );

            return new AppWebSocketConnection(
                $connector->connect(new WebsocketHandshake($endpoint->url, $headers)),
                $this->maximumMessageBytes,
            );
        } catch (Throwable $exception) {
            throw new TransportException(
                'Unable to establish the WebSocket connection: ' . $exception->getMessage(),
                ['endpoint' => self::endpointForContext($endpoint->url)],
                $exception,
            );
        }
    }

    private static function endpointForContext(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return '[invalid WebSocket URL]';
        }

        $scheme = is_string($parts['scheme'] ?? null) ? $parts['scheme'] . '://' : '';
        $host = is_string($parts['host'] ?? null) ? $parts['host'] : '';
        $port = is_int($parts['port'] ?? null) ? ':' . $parts['port'] : '';
        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '';

        return $scheme . $host . $port . $path;
    }
}

final readonly class AppWebSocketConnection implements TransportConnectionInterface
{
    public function __construct(
        private AmpWebsocketConnection $socket,
        private int $maximumMessageBytes,
    ) {}

    public function send(TransportFrame $frame): void
    {
        if (strlen($frame->payload) > $this->maximumMessageBytes) {
            throw new TransportException('A WebSocket message exceeded the configured byte limit.');
        }

        try {
            match ($frame->type) {
                TransportFrameType::Text => $this->socket->sendText($frame->payload),
                TransportFrameType::Binary => $this->socket->sendBinary($frame->payload),
            };
        } catch (Throwable $exception) {
            throw new TransportException(
                'Unable to send a WebSocket frame: ' . $exception->getMessage(),
                [],
                $exception,
            );
        }
    }

    public function receive(): ?TransportFrame
    {
        try {
            $message = $this->socket->receive();
            if ($message === null) {
                if ($this->socket->getCloseInfo()->getCode() === WebsocketCloseCode::MESSAGE_TOO_LARGE) {
                    throw new TransportException('A WebSocket message exceeded the configured byte limit.');
                }

                return null;
            }

            $payload = $message->buffer(limit: $this->maximumMessageBytes);

            return $message->isBinary()
                ? TransportFrame::binary($payload)
                : TransportFrame::text($payload);
        } catch (Throwable $exception) {
            throw new TransportException(
                'Unable to receive a WebSocket frame: ' . $exception->getMessage(),
                [],
                $exception,
            );
        }
    }

    public function finishSending(): void
    {
        try {
            if (! $this->socket->isClosed()) {
                $this->socket->close(1000);
            }
        } catch (Throwable $exception) {
            throw new TransportException(
                'Unable to finish the WebSocket connection: ' . $exception->getMessage(),
                [],
                $exception,
            );
        }
    }

    public function close(): void
    {
        try {
            if (! $this->socket->isClosed()) {
                $this->socket->close();
            }
        } catch (Throwable $exception) {
            throw new TransportException(
                'Unable to close the WebSocket connection: ' . $exception->getMessage(),
                [],
                $exception,
            );
        }
    }

    public function isClosed(): bool
    {
        return $this->socket->isClosed();
    }
}
