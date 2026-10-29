<?php

declare(strict_types=1);

use AiSdk\Exceptions\AiSdkException;
use AiSdk\Exceptions\APIConnectionException;
use AiSdk\Exceptions\AuthenticationException;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Exceptions\OverloadedException;
use AiSdk\Exceptions\RateLimitException;
use AiSdk\Streaming\Stream;
use AiSdk\Streaming\TextDeltaPart;
use AiSdk\Utils\Errors\HttpErrorNormalizer;
use AiSdk\Utils\Http\HttpRunner;
use AiSdk\Utils\Stream\SseParser;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

function runnerReturning(Closure $respond): HttpRunner
{
    $client = new class ($respond) implements ClientInterface {
        public function __construct(private readonly Closure $respond) {}

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            return ($this->respond)($request);
        }
    };
    $factory = new HttpFactory();

    return new HttpRunner($client, $factory, $factory);
}

it('redacts credentials a provider echoes back and bounds error messages', function () {
    $key = 'sk-live-SECRETKEY1234567890abcdefghij';
    $error = HttpErrorNormalizer::normalize('openai', 401, ['error' => ['message' => "Incorrect API key provided: {$key}"]]);

    expect($error)->toBeInstanceOf(AuthenticationException::class)
        ->and($error->getMessage())->not->toContain('SECRETKEY')
        ->and(json_encode($error->context()))->not->toContain('SECRETKEY');

    $page = '<html><body>' . str_repeat('Bad gateway ', 20000) . '</body></html>';
    $big = HttpErrorNormalizer::normalize('openai', 502, $page);

    expect(strlen($big->getMessage()))->toBeLessThan(1100)
        ->and(strlen(json_encode($big->context())))->toBeLessThan(9000)
        ->and($big->getMessage())->not->toContain('<html>');
});

it('turns unusual error bodies into messages without PHP warnings', function (mixed $body) {
    set_error_handler(static fn(int $no, string $str) => throw new ErrorException($str));

    try {
        $error = HttpErrorNormalizer::normalize('p', 400, $body);
    } finally {
        restore_error_handler();
    }

    expect($error)->toBeInstanceOf(AiSdkException::class)->and($error->getMessage())->not->toBe('');
})->with([
    'nested message' => [['error' => ['message' => ['nested' => 'x']]]],
    'numeric error' => [['error' => 42]],
    'list' => [['a', 'b']],
    'null' => [null],
    'empty string' => [''],
]);

it('maps errors delivered inside an event stream to typed exceptions', function (array $error, string $class) {
    expect(HttpErrorNormalizer::fromStreamError('p', $error))->toBeInstanceOf($class);
})->with([
    'rate limit' => [['type' => 'rate_limit_error', 'message' => 'slow down'], RateLimitException::class],
    'overloaded' => [['type' => 'overloaded_error', 'message' => 'busy'], OverloadedException::class],
    'auth' => [['code' => 'invalid_api_key', 'message' => 'no'], AuthenticationException::class],
    'unknown' => [['message' => 'odd'], InvalidResponseException::class],
]);

it('wraps clients that throw non-PSR exceptions but lets programmer errors through', function () {
    $runner = runnerReturning(fn() => throw new RuntimeException('boom'));
    expect(fn() => $runner->postJson('https://x.test', []))->toThrow(APIConnectionException::class);

    $broken = runnerReturning(fn() => throw new TypeError('bug'));
    expect(fn() => $broken->postJson('https://x.test', []))->toThrow(TypeError::class);
});

it('reads Retry-After given as an HTTP date', function () {
    $runner = runnerReturning(fn() => new Response(429, ['Retry-After' => gmdate('D, d M Y H:i:s', time() + 30) . ' GMT'], 'slow'));

    try {
        $runner->postJson('https://x.test', []);
    } catch (RateLimitException $e) {
        expect($e->context()['retryAfter'])->toBeBetween(25, 31);
    }
});

it('rejects a 200 answer to a streaming request that is not an event stream', function (string $type, string $body) {
    $runner = runnerReturning(fn() => new Response(200, ['Content-Type' => $type], $body));

    expect(fn() => iterator_to_array($runner->postStream('https://x.test', [], [], 'p')))->toThrow(AiSdkException::class);
})->with([
    'json error' => ['application/json', '{"error":{"message":"quota"}}'],
    'json document' => ['application/json', '{}'],
    'html page' => ['text/html', '<html>maintenance</html>'],
]);

it('reports a connection that dies while streaming as a connection error', function () {
    $body = new class implements Psr\Http\Message\StreamInterface {
        private bool $sent = false;

        public function __toString(): string
        {
            return '';
        }
        public function close(): void {}
        public function detach()
        {
            return null;
        }
        public function getSize(): ?int
        {
            return null;
        }
        public function tell(): int
        {
            return 0;
        }
        public function eof(): bool
        {
            return false;
        }
        public function isSeekable(): bool
        {
            return false;
        }
        public function seek(int $offset, int $whence = SEEK_SET): void {}
        public function rewind(): void {}
        public function isWritable(): bool
        {
            return false;
        }
        public function write(string $string): int
        {
            return 0;
        }
        public function isReadable(): bool
        {
            return true;
        }
        public function read(int $length): string
        {
            if (! $this->sent) {
                $this->sent = true;

                return "data: {}\n\n";
            }

            throw new RuntimeException('Error reading stream');
        }
        public function getContents(): string
        {
            return '';
        }
        public function getMetadata(?string $key = null)
        {
            return null;
        }
    };
    $runner = runnerReturning(fn() => new Response(200, ['Content-Type' => 'text/event-stream'], $body));

    expect(fn() => iterator_to_array($runner->postStream('https://x.test', [], [], 'p')))->toThrow(APIConnectionException::class);
});

it('refuses a single event larger than the frame limit', function () {
    $stream = (new HttpFactory())->createStream('data: ' . str_repeat('A', 5000));

    expect(fn() => iterator_to_array(SseParser::parseStream($stream, 1000)))->toThrow(InvalidResponseException::class);
});

it('fails a stream that ends without sending anything and marks cut-off output as unknown', function () {
    $empty = new Stream((function () {
        yield from [];
    })());
    expect(fn() => $empty->run())->toThrow(InvalidResponseException::class);

    $cut = new Stream((function () {
        yield new TextDeltaPart('partial');
    })());
    expect($cut->run()->finishReason)->toBe(AiSdk\FinishReason::Unknown);
});

it('treats providers that answer a bad API key with 400 as authentication failures', function (int $status, array $body) {
    expect(HttpErrorNormalizer::normalize('p', $status, $body))->toBeInstanceOf(AuthenticationException::class);
})->with([
    'xai' => [400, ['code' => 'invalid-argument', 'error' => 'Incorrect API key provided. You can obtain an API key from https://console.x.ai.']],
    'google' => [400, ['error' => ['code' => 400, 'message' => 'API key not valid. Please pass a valid API key.', 'status' => 'INVALID_ARGUMENT', 'details' => [['reason' => 'API_KEY_INVALID']]]]],
    'typed code' => [400, ['error' => ['code' => 'invalid_api_key', 'message' => 'nope']]],
]);

it('does not mistake ordinary bad requests for authentication failures', function () {
    expect(HttpErrorNormalizer::normalize('p', 400, ['error' => ['message' => 'max_tokens must be positive']]))->toBeInstanceOf(AiSdk\Exceptions\InvalidRequestException::class);
});
