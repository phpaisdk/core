<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Exceptions\InvalidArgumentException;

/**
 * An explicit reference to a file on the local disk.
 *
 * Content factories never read the disk from a plain string, because strings
 * often come from user input. Wrap a path in LocalFile only when your code
 * chose it. For paths built from user input, use LocalFile::within() so the
 * result cannot escape a base directory.
 */
final class LocalFile
{
    private function __construct(public readonly string $path) {}

    /**
     * A path your application chose. Never pass user input here.
     */
    public static function at(string $path): self
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('A local file path must be a non-empty string without null bytes.');
        }

        return new self($path);
    }

    /**
     * A path relative to a base directory, rejected if it resolves outside it
     * (through "..", absolute paths or symlinks).
     */
    public static function within(string $baseDirectory, string $relativePath): self
    {
        if ($relativePath === '' || str_contains($relativePath, "\0")) {
            throw new InvalidArgumentException('A local file path must be a non-empty string without null bytes.');
        }

        $base = realpath($baseDirectory);
        $resolved = realpath(rtrim($baseDirectory, '/\\') . DIRECTORY_SEPARATOR . $relativePath);

        if ($base === false || $resolved === false) {
            throw new InvalidArgumentException('The local file does not exist inside the base directory.');
        }

        if ($resolved !== $base && ! str_starts_with($resolved, $base . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('The local file path escapes the base directory.');
        }

        return new self($resolved);
    }
}
