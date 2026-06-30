<?php

declare(strict_types=1);

namespace AiSdk\Utils\Stream;

use Generator;
use Psr\Http\Message\StreamInterface;

/**
 * Minimal Server-Sent Events parser. Yields {event, data} frames as they are
 * read from a PSR-7 stream body.
 */
final class SseParser
{
    /**
     * @return Generator<int, array{event: ?string, data: string}>
     */
    public static function parseStream(StreamInterface $stream): Generator
    {
        $buffer = '';

        while (! $stream->eof()) {
            $chunk = $stream->read(8192);
            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            while (($pos = self::frameBoundary($buffer)) !== null) {
                [$rawFrame, $length] = $pos;
                $buffer = substr($buffer, $length);

                $frame = self::parseFrame($rawFrame);
                if ($frame !== null) {
                    yield $frame;
                }
            }
        }

        $frame = self::parseFrame($buffer);
        if ($frame !== null) {
            yield $frame;
        }
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private static function frameBoundary(string $buffer): ?array
    {
        $rn = strpos($buffer, "\n\n");
        $crlf = strpos($buffer, "\r\n\r\n");

        if ($rn === false && $crlf === false) {
            return null;
        }

        if ($crlf !== false && ($rn === false || $crlf < $rn)) {
            return [substr($buffer, 0, $crlf), $crlf + 4];
        }

        return [substr($buffer, 0, (int) $rn), (int) $rn + 2];
    }

    /**
     * @return array{event: ?string, data: string}|null
     */
    private static function parseFrame(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $event = null;
        $data = [];

        foreach (preg_split('/\r\n|\n|\r/', $raw) ?: [] as $line) {
            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data[] = ltrim(substr($line, 5), ' ');
            }
        }

        if ($data === []) {
            return null;
        }

        return ['event' => $event, 'data' => implode("\n", $data)];
    }
}
