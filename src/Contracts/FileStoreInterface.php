<?php

declare(strict_types=1);

namespace AiSdk\Contracts;

use AiSdk\Files\FilePage;
use AiSdk\Files\FileUpload;
use AiSdk\Files\StoredFile;
use Psr\Http\Message\StreamInterface;

/** Provider-account resource, deliberately independent of model selection. */
interface FileStoreInterface
{
    /** @param array<string, scalar> $options Provider-owned upload options. */
    public function upload(FileUpload $file, array $options = []): StoredFile;
    public function retrieve(string $id): StoredFile;
    public function list(?string $cursor = null, int $limit = 20): FilePage;
    public function delete(string $id): void;
    public function download(string $id): StreamInterface;
}
