<?php

declare(strict_types=1);

namespace AiSdk\Skills;

use AiSdk\Exceptions\SkillException;

/** @internal Reads only regular files beneath a canonical application-chosen directory. */
final class SkillReader
{
    public static function read(string $directory, string $relative, int $maxBytes): string
    {
        if ($relative === '' || strlen($relative) > 4096 || str_contains($relative, "\0") || str_contains($relative, '\\') || str_contains($relative, ':') || str_starts_with($relative, '/')) {
            throw new SkillException('Skill resource paths must be relative file names.');
        }
        $segments = explode('/', $relative);
        if (count($segments) > 32) {
            throw new SkillException('Skill resource paths may contain at most 32 segments.');
        }
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new SkillException('Skill resource path traversal is not allowed.');
            }
        }
        $root = realpath($directory);
        if ($root === false || $root !== rtrim($directory, DIRECTORY_SEPARATOR) || ! is_dir($root) || is_link($directory)) {
            throw new SkillException('The skill directory is unavailable.');
        }
        $path = $root . DIRECTORY_SEPARATOR . $relative;
        self::checkPath($root, $segments, $path);
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new SkillException('The skill file cannot be opened.');
        }

        try {
            $opened = fstat($handle);
            $current = @stat($path);
            if ($opened === false || $current === false || ($opened['mode'] & 0170000) !== 0100000 || $opened['size'] > $maxBytes || $opened['ino'] !== $current['ino'] || $opened['dev'] !== $current['dev']) {
                throw new SkillException('The skill file is not a bounded regular file.');
            }
            self::checkPath($root, $segments, $path);
            $text = stream_get_contents($handle, $maxBytes + 1);
            if ($text === false || strlen($text) > $maxBytes || preg_match('//u', $text) !== 1 || str_contains($text, "\0")) {
                throw new SkillException('The skill file exceeds its size limit or is not UTF-8 text.');
            }
            clearstatcache(true, $path);
            self::checkPath($root, $segments, $path);
            $after = @stat($path);
            if ($after === false || $opened['ino'] !== $after['ino'] || $opened['dev'] !== $after['dev']) {
                throw new SkillException('The skill file changed while being read.');
            }

            return $text;
        } finally {
            fclose($handle);
        }
    }

    /** @param list<string> $segments */
    private static function checkPath(string $root, array $segments, string $path): void
    {
        $cursor = $root;
        foreach ($segments as $segment) {
            $cursor .= DIRECTORY_SEPARATOR . $segment;
            clearstatcache(true, $cursor);
            if (is_link($cursor)) {
                throw new SkillException('Symlinks are not allowed in skill resource paths.');
            }
        }
        $resolved = realpath($path);
        if ($resolved === false || ! str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) {
            throw new SkillException('The skill resource must remain inside its skill directory.');
        }
    }
}
