<?php

declare(strict_types=1);

namespace AiSdk\Utils\Http;

use Closure;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Opt-in PSR-18 decorator that retries transient failures (connection errors,
 * 408, 409, 429 and 5xx) with exponential backoff, full jitter and Retry-After.
 *
 * Only the time to the response headers is retried; once a response is
 * returned (including a streaming body) it is handed to the caller untouched.
 */
final class RetryingHttpClient implements ClientInterface
{
    private const array RETRYABLE_STATUSES = [408, 409, 429, 500, 502, 503, 504, 529];

    /** @var Closure(int): void */
    private readonly Closure $sleep;

    /** @var Closure(): float */
    private readonly Closure $random;

    /**
     * @param  int  $maxRetries  Retries after the first attempt.
     * @param  int  $baseDelayMs  Backoff base; attempt n waits up to base * 2^n.
     * @param  int  $maxDelayMs  Upper bound for any wait, including Retry-After.
     * @param  (callable(int): void)|null  $sleep  Receives microseconds; injectable for tests.
     * @param  (callable(): float)|null  $random  Returns 0.0-1.0; injectable for tests.
     */
    public function __construct(
        private readonly ClientInterface $inner,
        private readonly int $maxRetries = 2,
        private readonly int $baseDelayMs = 500,
        private readonly int $maxDelayMs = 30_000,
        ?callable $sleep = null,
        ?callable $random = null,
    ) {
        $this->sleep = Closure::fromCallable($sleep ?? usleep(...));
        $this->random = Closure::fromCallable($random ?? static fn(): float => mt_rand() / mt_getrandmax());
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        for ($attempt = 0; ; $attempt++) {
            $this->rewind($request);

            try {
                $response = $this->inner->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }

                $this->wait($attempt, null);

                continue;
            }

            if ($attempt >= $this->maxRetries || ! in_array($response->getStatusCode(), self::RETRYABLE_STATUSES, true)) {
                return $response;
            }

            $this->wait($attempt, $this->retryAfterMs($response));
        }
    }

    private function wait(int $attempt, ?int $retryAfterMs): void
    {
        $backoff = (int) (($this->random)() * min($this->maxDelayMs, $this->baseDelayMs * (2 ** $attempt)));
        $delayMs = min($this->maxDelayMs, max($backoff, $retryAfterMs ?? 0));

        ($this->sleep)($delayMs * 1000);
    }

    private function retryAfterMs(ResponseInterface $response): ?int
    {
        $header = trim($response->getHeaderLine('retry-after'));

        if ($header === '') {
            return null;
        }

        if (is_numeric($header)) {
            return (int) ceil((float) $header * 1000);
        }

        $timestamp = strtotime($header);

        return $timestamp === false ? null : max(0, ($timestamp - time()) * 1000);
    }

    private function rewind(RequestInterface $request): void
    {
        $body = $request->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }
    }
}
