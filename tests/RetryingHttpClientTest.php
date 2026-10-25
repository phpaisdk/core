<?php

declare(strict_types=1);

use AiSdk\Utils\Http\RetryingHttpClient;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** @param array<int, ResponseInterface|Throwable> $script */
function scriptedClient(array $script): object
{
    return new class ($script) implements ClientInterface {
        public int $calls = 0;

        /** @var array<int, string> */
        public array $bodies = [];

        /** @param array<int, ResponseInterface|Throwable> $script */
        public function __construct(private array $script) {}

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $this->calls++;
            $this->bodies[] = (string) $request->getBody();
            $next = array_shift($this->script);

            if ($next instanceof Throwable) {
                throw $next;
            }

            return $next;
        }
    };
}

function retrying(object $inner, array &$sleeps, int $maxRetries = 2): RetryingHttpClient
{
    return new RetryingHttpClient(
        $inner,
        maxRetries: $maxRetries,
        baseDelayMs: 100,
        maxDelayMs: 5_000,
        sleep: function (int $micro) use (&$sleeps): void {
            $sleeps[] = $micro;
        },
        random: static fn(): float => 1.0,
    );
}

it('retries rate limits and server errors then returns the first success', function () {
    $inner = scriptedClient([new Response(429), new Response(503), new Response(200, [], 'ok')]);
    $sleeps = [];

    $response = retrying($inner, $sleeps)->sendRequest(new Request('POST', 'https://x.test', [], 'payload'));

    expect($response->getStatusCode())->toBe(200)
        ->and($inner->calls)->toBe(3)
        ->and($sleeps)->toBe([100_000, 200_000])
        ->and($inner->bodies)->toBe(['payload', 'payload', 'payload']);
});

it('honours Retry-After seconds as a floor for the wait', function () {
    $inner = scriptedClient([new Response(429, ['Retry-After' => '2']), new Response(200)]);
    $sleeps = [];

    retrying($inner, $sleeps)->sendRequest(new Request('POST', 'https://x.test'));

    expect($sleeps)->toBe([2_000_000]);
});

it('caps waits at the configured maximum', function () {
    $inner = scriptedClient([new Response(429, ['Retry-After' => '600']), new Response(200)]);
    $sleeps = [];

    retrying($inner, $sleeps)->sendRequest(new Request('POST', 'https://x.test'));

    expect($sleeps)->toBe([5_000_000]);
});

it('does not retry client errors', function () {
    $inner = scriptedClient([new Response(401)]);
    $sleeps = [];

    $response = retrying($inner, $sleeps)->sendRequest(new Request('POST', 'https://x.test'));

    expect($response->getStatusCode())->toBe(401)->and($inner->calls)->toBe(1)->and($sleeps)->toBe([]);
});

it('returns the last response once retries are exhausted', function () {
    $inner = scriptedClient([new Response(500), new Response(500), new Response(502)]);
    $sleeps = [];

    $response = retrying($inner, $sleeps)->sendRequest(new Request('GET', 'https://x.test'));

    expect($response->getStatusCode())->toBe(502)->and($inner->calls)->toBe(3);
});

it('retries transport exceptions and rethrows after the last attempt', function () {
    $failure = new class ('down') extends RuntimeException implements ClientExceptionInterface {};
    $sleeps = [];

    $recovering = scriptedClient([$failure, new Response(200)]);
    expect(retrying($recovering, $sleeps)->sendRequest(new Request('GET', 'https://x.test'))->getStatusCode())->toBe(200);

    $broken = scriptedClient([$failure, $failure, $failure]);
    expect(fn() => retrying($broken, $sleeps)->sendRequest(new Request('GET', 'https://x.test')))
        ->toThrow(get_class($failure));
    expect($broken->calls)->toBe(3);
});
