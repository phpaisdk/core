<?php

declare(strict_types=1);

namespace AiSdk\Exceptions;

use RuntimeException;

class AiSdkException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = '',
        protected array $context = [],
        ?\Throwable $previous = null,
        int $code = 0,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    public function provider(): ?string
    {
        return $this->stringContext('provider');
    }

    public function modelId(): ?string
    {
        return $this->stringContext('modelId');
    }

    public function status(): ?int
    {
        $status = $this->context['status'] ?? null;

        return is_int($status) ? $status : null;
    }

    public function requestId(): ?string
    {
        return $this->stringContext('requestId');
    }

    public function errorType(): ?string
    {
        return $this->stringContext('errorType');
    }

    public function errorCode(): ?string
    {
        return $this->stringContext('errorCode');
    }

    public function retryAfter(): ?int
    {
        $retryAfter = $this->context['retryAfter'] ?? null;

        return is_int($retryAfter) ? $retryAfter : null;
    }

    public function isRetryable(): bool
    {
        return ($this->context['retryable'] ?? false) === true;
    }

    public function body(): mixed
    {
        return $this->context['body'] ?? null;
    }

    private function stringContext(string $key): ?string
    {
        $value = $this->context[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
