<?php

declare(strict_types=1);

namespace AiSdk\Files;

final readonly class StoredFile
{
    /** @param array<string, mixed> $providerMetadata */
    public function __construct(
        public FileReference $reference,
        public string $filename,
        public int $bytes,
        public ?string $mimeType = null,
        public ?bool $downloadable = null,
        public array $providerMetadata = [],
    ) {}
}
