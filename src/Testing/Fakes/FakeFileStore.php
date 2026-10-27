<?php

declare(strict_types=1);

namespace AiSdk\Testing\Fakes;

use AiSdk\Contracts\FileStoreInterface;
use AiSdk\Files\FilePage;
use AiSdk\Files\FileReference;
use AiSdk\Files\FileUpload;
use AiSdk\Files\StoredFile;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class FakeFileStore implements FileStoreInterface
{
    /** @var array<string, array{file: StoredFile, data: string}> */
    private array $files = [];
    private int $sequence = 0;
    public function __construct(private readonly StreamFactoryInterface $factory) {}
    public function upload(FileUpload $file, array $options = []): StoredFile
    {
        $id = 'fake-' . ++$this->sequence;
        $data = (string) $file->stream($this->factory, 10_485_760);
        $stored = new StoredFile(new FileReference('fake', $id), $file->filename, strlen($data), $file->mimeType, true);
        $this->files[$id] = ['file' => $stored, 'data' => $data];

        return $stored;
    }
    public function retrieve(string $id): StoredFile
    {
        return $this->files[$id]['file'] ?? throw new \OutOfBoundsException('Unknown fake file.');
    }
    public function list(?string $cursor = null, int $limit = 20): FilePage
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException('Limit must be positive.');
        }
        $ids = array_keys($this->files);
        $position = $cursor === null ? -1 : array_search($cursor, $ids, true);
        if ($position === false) {
            throw new \OutOfBoundsException('Unknown fake cursor.');
        }
        $page = array_slice($ids, $position + 1, $limit);
        $next = $position + 1 + count($page) < count($ids) ? $page[count($page) - 1] : null;

        return new FilePage(array_map(fn(string $id): StoredFile => $this->retrieve($id), $page), $next);
    }
    public function delete(string $id): void
    {
        unset($this->files[$id]);
    }
    public function download(string $id): StreamInterface
    {
        $this->retrieve($id);

        return $this->factory->createStream($this->files[$id]['data']);
    }
}
