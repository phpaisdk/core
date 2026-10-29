<?php

declare(strict_types=1);

namespace AiSdk\Utils\Http;

final readonly class RemoteFetchResult
{
    public function __construct(
        public string $body,
        public ?string $mimeType,
        public string $finalUrl,
    ) {}
}
