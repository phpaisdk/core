<?php

declare(strict_types=1);

namespace AiSdk\Files;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\LocalFile;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final readonly class FileUpload
{
    public function __construct(
        private string|LocalFile|StreamInterface $contents,
        public string $filename,
        public string $mimeType = 'application/octet-stream',
    ) {
        if ($filename === '' || $mimeType === '' || preg_match('/[\x00-\x1f\x7f"\\\\\/]/', $filename) || preg_match('/[\x00-\x1f\x7f]/', $mimeType)) {
            throw new InvalidArgumentException('Invalid upload filename or MIME type.');
        }
    }

    /** Plain strings are bytes, never paths or URLs. A caller-owned stream must be seekable. */
    public function stream(StreamFactoryInterface $factory, int $maxBytes): StreamInterface
    {
        $source = $this->contents;
        if ($source instanceof LocalFile) {
            $path = realpath($source->path);
            if ($path === false || ! is_file($path) || ! is_readable($path)) {
                throw new InvalidArgumentException('Upload source must be a readable local file.');
            }
            $source = $factory->createStreamFromFile($path, 'r');
        }
        $stream = is_string($source) ? $factory->createStream($source) : $source;
        $size = $stream->getSize();
        if ($size === null || $size > $maxBytes || ! $stream->isSeekable()) {
            throw new InvalidArgumentException('Upload must be seekable, have a known size, and fit the provider byte limit.');
        }
        $stream->rewind();

        return $stream;
    }
}
