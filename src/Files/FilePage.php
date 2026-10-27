<?php

declare(strict_types=1);

namespace AiSdk\Files;

final readonly class FilePage
{
    /** @param list<StoredFile> $files */
    public function __construct(public array $files, public ?string $nextCursor = null) {}
}
